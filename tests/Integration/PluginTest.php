<?php

declare(strict_types=1);

namespace VerifiedClientIp\Tests\Integration;

// Load WordPress function stubs for testing.
require_once __DIR__ . '/bootstrap.php';

use PHPUnit\Framework\TestCase;
use VerifiedClientIp\Plugin;
use VerifiedClientIp\Scheme;

/**
 * Integration tests for the WordPress Plugin class.
 *
 * These tests verify that Plugin::boot() correctly:
 *   - Replaces REMOTE_ADDR
 *   - Stores original values in X-Original headers
 *   - Processes Proto and Host
 *   - Fires WordPress hooks (apply_filters / do_action)
 *   - Respects the enabled/disabled switch
 */
final class PluginTest extends TestCase {

	/** @var array<string, string> Backup of original $_SERVER. */
	private array $original_server;

	protected function setUp(): void {
		$this->original_server = $_SERVER;

		// Reset the singleton so each test gets a fresh boot.
		$ref  = new \ReflectionClass( Plugin::class );
		$prop = $ref->getProperty( 'instance' );
		$prop->setValue( null, null );

		// Reset test globals.
		$GLOBALS['_vcip_test_options'] = [];
		$GLOBALS['_vcip_test_filters'] = [];
		$GLOBALS['_vcip_test_actions'] = [];
	}

	protected function tearDown(): void {
		$_SERVER = $this->original_server;

		// Reset singleton again.
		$ref  = new \ReflectionClass( Plugin::class );
		$prop = $ref->getProperty( 'instance' );
		$prop->setValue( null, null );
	}

	// ------------------------------------------------------------------
	// Helper
	// ------------------------------------------------------------------

	/**
	 * Configure test settings in the simulated wp_options.
	 *
	 * @param array<string, mixed> $overrides
	 */
	private function setSettings( array $overrides = [] ): void {
		$defaults = [
			'enabled'       => true,
			'forward_limit' => 1,
			'process_proto' => true,
			'process_host'  => false,
		];

		$GLOBALS['_vcip_test_options']['vcip_settings'] = array_merge( $defaults, $overrides );
	}

	/**
	 * Set custom schemes in settings.
	 *
	 * @param array<Scheme> $schemes
	 */
	private function setSchemes( array $schemes ): void {
		$settings = $GLOBALS['_vcip_test_options']['vcip_settings'] ?? [];

		$settings['schemes'] = array_map(
			static fn ( Scheme $s ): array => $s->to_array(),
			$schemes,
		);

		$GLOBALS['_vcip_test_options']['vcip_settings'] = $settings;
	}

	// ------------------------------------------------------------------
	// Basic REMOTE_ADDR replacement
	// ------------------------------------------------------------------

	public function testBootReplacesRemoteAddr(): void {
		$this->setSettings();
		$this->setSchemes(
			[
				new Scheme( 'XFF', true, [ '10.0.0.0/8' ], 'X-Forwarded-For' ),
			]
		);

		$_SERVER['REMOTE_ADDR']          = '10.0.0.1';
		$_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.50';

		Plugin::boot();

		$this->assertSame( '203.0.113.50', $_SERVER['REMOTE_ADDR'] );
	}

	public function testOriginalRemoteAddrStored(): void {
		$this->setSettings();
		$this->setSchemes(
			[
				new Scheme( 'XFF', true, [ '10.0.0.0/8' ], 'X-Forwarded-For' ),
			]
		);

		$_SERVER['REMOTE_ADDR']          = '10.0.0.1';
		$_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.50';

		Plugin::boot();

		$this->assertSame( '10.0.0.1', $_SERVER['HTTP_X_ORIGINAL_REMOTE_ADDR'] );
	}

	public function testNoOpWhenRemoteAddrNotProxy(): void {
		$this->setSettings();
		$this->setSchemes(
			[
				new Scheme( 'XFF', true, [ '10.0.0.0/8' ], 'X-Forwarded-For' ),
			]
		);

		$_SERVER['REMOTE_ADDR']          = '203.0.113.50';
		$_SERVER['HTTP_X_FORWARDED_FOR'] = '1.2.3.4';

		Plugin::boot();

		$this->assertSame( '203.0.113.50', $_SERVER['REMOTE_ADDR'] );
		$this->assertArrayNotHasKey( 'HTTP_X_ORIGINAL_REMOTE_ADDR', $_SERVER );
	}

	// ------------------------------------------------------------------
	// Enabled / Disabled
	// ------------------------------------------------------------------

	public function testDisabledDoesNotReplace(): void {
		$this->setSettings( [ 'enabled' => false ] );
		$this->setSchemes(
			[
				new Scheme( 'XFF', true, [ '10.0.0.0/8' ], 'X-Forwarded-For' ),
			]
		);

		$_SERVER['REMOTE_ADDR']          = '10.0.0.1';
		$_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.50';

		Plugin::boot();

		// REMOTE_ADDR should NOT be changed when disabled.
		$this->assertSame( '10.0.0.1', $_SERVER['REMOTE_ADDR'] );
	}

	public function testDisabledStillCalculatesResult(): void {
		$this->setSettings( [ 'enabled' => false ] );
		$this->setSchemes(
			[
				new Scheme( 'XFF', true, [ '10.0.0.0/8' ], 'X-Forwarded-For' ),
			]
		);

		$_SERVER['REMOTE_ADDR']          = '10.0.0.1';
		$_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.50';

		Plugin::boot();

		// The result should still be available for diagnostics.
		$instance = Plugin::instance();
		$this->assertNotNull( $instance );
		$result = $instance->last_result();
		$this->assertNotNull( $result );
		$this->assertTrue( $result->changed );
		$this->assertSame( '203.0.113.50', $result->resolved_ip );
	}

	// ------------------------------------------------------------------
	// Proto processing
	// ------------------------------------------------------------------

	public function testProtoProcessingHttps(): void {
		$this->setSettings( [ 'process_proto' => true ] );
		$this->setSchemes(
			[
				new Scheme( 'Fwd', true, [ '10.0.0.0/8' ], 'Forwarded', 'for' ),
			]
		);

		$_SERVER['REMOTE_ADDR']    = '10.0.0.1';
		$_SERVER['HTTP_FORWARDED'] = 'for=203.0.113.50;proto=https';

		Plugin::boot();

		$this->assertSame( 'on', $_SERVER['HTTPS'] );
		$this->assertSame( 'https', $_SERVER['REQUEST_SCHEME'] );
	}

	public function testProtoProcessingStoresOriginals(): void {
		$this->setSettings( [ 'process_proto' => true ] );
		$this->setSchemes(
			[
				new Scheme( 'Fwd', true, [ '10.0.0.0/8' ], 'Forwarded', 'for' ),
			]
		);

		$_SERVER['REMOTE_ADDR']    = '10.0.0.1';
		$_SERVER['HTTP_FORWARDED'] = 'for=203.0.113.50;proto=https';
		$_SERVER['HTTPS']          = 'off';
		$_SERVER['REQUEST_SCHEME'] = 'http';

		Plugin::boot();

		$this->assertSame( 'off', $_SERVER['HTTP_X_ORIGINAL_HTTPS'] );
		$this->assertSame( 'http', $_SERVER['HTTP_X_ORIGINAL_REQUEST_SCHEME'] );
	}

	public function testProtoProcessingXForwardedProto(): void {
		$this->setSettings( [ 'process_proto' => true ] );
		$this->setSchemes(
			[
				new Scheme( 'XFF', true, [ '10.0.0.0/8' ], 'X-Forwarded-For' ),
			]
		);

		$_SERVER['REMOTE_ADDR']            = '10.0.0.1';
		$_SERVER['HTTP_X_FORWARDED_FOR']   = '203.0.113.50';
		$_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';

		Plugin::boot();

		$this->assertSame( 'on', $_SERVER['HTTPS'] );
		$this->assertSame( 'https', $_SERVER['REQUEST_SCHEME'] );
	}

	public function testProtoProcessingDisabled(): void {
		$this->setSettings( [ 'process_proto' => false ] );
		$this->setSchemes(
			[
				new Scheme( 'Fwd', true, [ '10.0.0.0/8' ], 'Forwarded', 'for' ),
			]
		);

		$_SERVER['REMOTE_ADDR']    = '10.0.0.1';
		$_SERVER['HTTP_FORWARDED'] = 'for=203.0.113.50;proto=https';

		Plugin::boot();

		// REMOTE_ADDR should be replaced but HTTPS should not be touched.
		$this->assertSame( '203.0.113.50', $_SERVER['REMOTE_ADDR'] );
		$this->assertArrayNotHasKey( 'HTTPS', $_SERVER );
	}

	// ------------------------------------------------------------------
	// Host processing
	// ------------------------------------------------------------------

	public function testHostProcessingEnabled(): void {
		$this->setSettings( [ 'process_host' => true ] );
		$this->setSchemes(
			[
				new Scheme( 'Fwd', true, [ '10.0.0.0/8' ], 'Forwarded', 'for' ),
			]
		);

		$_SERVER['REMOTE_ADDR']    = '10.0.0.1';
		$_SERVER['HTTP_FORWARDED'] = 'for=203.0.113.50;host=example.com';
		$_SERVER['HTTP_HOST']      = 'internal.local';

		Plugin::boot();

		$this->assertSame( 'example.com', $_SERVER['HTTP_HOST'] );
		$this->assertSame( 'example.com', $_SERVER['SERVER_NAME'] );
		$this->assertSame( 'internal.local', $_SERVER['HTTP_X_ORIGINAL_HOST'] );
	}

	public function testHostProcessingDisabledByDefault(): void {
		$this->setSettings(); // process_host defaults to false
		$this->setSchemes(
			[
				new Scheme( 'Fwd', true, [ '10.0.0.0/8' ], 'Forwarded', 'for' ),
			]
		);

		$_SERVER['REMOTE_ADDR']    = '10.0.0.1';
		$_SERVER['HTTP_FORWARDED'] = 'for=203.0.113.50;host=example.com';
		$_SERVER['HTTP_HOST']      = 'internal.local';

		Plugin::boot();

		// Host should NOT be changed.
		$this->assertSame( 'internal.local', $_SERVER['HTTP_HOST'] );
	}

	// ------------------------------------------------------------------
	// WordPress hooks
	// ------------------------------------------------------------------

	public function testVcipResolvedIpFilterCalled(): void {
		$this->setSettings();
		$this->setSchemes(
			[
				new Scheme( 'XFF', true, [ '10.0.0.0/8' ], 'X-Forwarded-For' ),
			]
		);

		$_SERVER['REMOTE_ADDR']          = '10.0.0.1';
		$_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.50';

		Plugin::boot();

		$this->assertArrayHasKey( 'vcip_resolved_ip', $GLOBALS['_vcip_test_filters'] );
		$this->assertSame( '203.0.113.50', $GLOBALS['_vcip_test_filters']['vcip_resolved_ip'][0] );
	}

	public function testVcipTrustedProxiesFilterCalled(): void {
		$this->setSettings();
		$this->setSchemes(
			[
				new Scheme( 'XFF', true, [ '10.0.0.0/8' ], 'X-Forwarded-For' ),
			]
		);

		$_SERVER['REMOTE_ADDR']          = '10.0.0.1';
		$_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.50';

		Plugin::boot();

		$this->assertArrayHasKey( 'vcip_trusted_proxies', $GLOBALS['_vcip_test_filters'] );
	}

	public function testVcipIpResolvedActionFired(): void {
		$this->setSettings();
		$this->setSchemes(
			[
				new Scheme( 'XFF', true, [ '10.0.0.0/8' ], 'X-Forwarded-For' ),
			]
		);

		$_SERVER['REMOTE_ADDR']          = '10.0.0.1';
		$_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.50';

		Plugin::boot();

		$this->assertArrayHasKey( 'vcip_ip_resolved', $GLOBALS['_vcip_test_actions'] );
		$action_args = $GLOBALS['_vcip_test_actions']['vcip_ip_resolved'][0];
		$this->assertSame( '203.0.113.50', $action_args[0] ); // new IP
		$this->assertSame( '10.0.0.1', $action_args[1] );     // original IP
		$this->assertIsArray( $action_args[2] );               // step trace
	}

	public function testNoActionFiredWhenNoChange(): void {
		$this->setSettings();
		$this->setSchemes(
			[
				new Scheme( 'XFF', true, [ '10.0.0.0/8' ], 'X-Forwarded-For' ),
			]
		);

		$_SERVER['REMOTE_ADDR'] = '203.0.113.50';

		Plugin::boot();

		$this->assertArrayNotHasKey( 'vcip_ip_resolved', $GLOBALS['_vcip_test_actions'] );
	}

	// ------------------------------------------------------------------
	// Default schemes
	// ------------------------------------------------------------------

	public function testDefaultSchemesUsedWhenNoneConfigured(): void {
		// No schemes in settings → default schemes are used.
		$this->setSettings();

		$_SERVER['REMOTE_ADDR']          = '10.0.0.1';
		$_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.50';

		Plugin::boot();

		$this->assertSame( '203.0.113.50', $_SERVER['REMOTE_ADDR'] );
	}

	public function testDefaultSchemesReturnedByHelper(): void {
		$schemes = Plugin::default_schemes();

		$this->assertCount( 3, $schemes );
		$this->assertSame( 'RFC 7239 Forwarded', $schemes[0]->name );
		$this->assertTrue( $schemes[0]->enabled );
		$this->assertSame( 'X-Forwarded-For', $schemes[1]->name );
		$this->assertTrue( $schemes[1]->enabled );
		$this->assertSame( 'Cloudflare', $schemes[2]->name );
		$this->assertFalse( $schemes[2]->enabled );
	}

	// ------------------------------------------------------------------
	// Singleton behaviour
	// ------------------------------------------------------------------

	public function testBootOnlyRunsOnce(): void {
		$this->setSettings();
		$this->setSchemes(
			[
				new Scheme( 'XFF', true, [ '10.0.0.0/8' ], 'X-Forwarded-For' ),
			]
		);

		$_SERVER['REMOTE_ADDR']          = '10.0.0.1';
		$_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.50';

		Plugin::boot();

		$this->assertSame( '203.0.113.50', $_SERVER['REMOTE_ADDR'] );

		// Change REMOTE_ADDR and boot again — should be a no-op.
		$_SERVER['REMOTE_ADDR']          = '10.0.0.2';
		$_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.1';

		Plugin::boot();

		// Still the result from the first boot.
		$this->assertSame( '10.0.0.2', $_SERVER['REMOTE_ADDR'] );
	}

	// ------------------------------------------------------------------
	// Forward Limit integration
	// ------------------------------------------------------------------

	public function testForwardLimitFromSettings(): void {
		$this->setSettings( [ 'forward_limit' => 2 ] );
		$this->setSchemes(
			[
				new Scheme( 'XFF', true, [ '10.0.0.0/8', '192.168.0.0/16' ], 'X-Forwarded-For' ),
			]
		);

		$_SERVER['REMOTE_ADDR']          = '10.0.0.1';
		$_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.50, 192.168.1.1';

		Plugin::boot();

		// 2 hops: 10.0.0.1 (forward #1), 192.168.1.1 (forward #2 = limit).
		// Next address 203.0.113.50 returned.
		$this->assertSame( '203.0.113.50', $_SERVER['REMOTE_ADDR'] );
	}
}
