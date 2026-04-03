<?php

declare(strict_types=1);

namespace Essential\VerifiedClientIp\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Essential\VerifiedClientIp\Scheme;
use Essential\VerifiedClientIp\Settings;

/**
 * Unit tests for the Settings data model and validation.
 */
final class SettingsTest extends TestCase {

	// ------------------------------------------------------------------
	// Factory defaults
	// ------------------------------------------------------------------

	public function testDefaultsReturnsSensibleValues(): void {
		$s = Settings::defaults();

		$this->assertTrue( $s->enabled );
		$this->assertSame( 1, $s->forward_limit );
		$this->assertTrue( $s->process_proto );
		$this->assertFalse( $s->process_host );
		$this->assertCount( 3, $s->schemes );
	}

	public function testDefaultSchemesMatchSpec(): void {
		$schemes = Settings::default_schemes();

		$this->assertSame( 'RFC 7239 Forwarded', $schemes[0]->name );
		$this->assertTrue( $schemes[0]->enabled );
		$this->assertSame( 'Forwarded', $schemes[0]->header );
		$this->assertSame( 'for', $schemes[0]->token );

		$this->assertSame( 'X-Forwarded-For', $schemes[1]->name );
		$this->assertTrue( $schemes[1]->enabled );
		$this->assertSame( 'X-Forwarded-For', $schemes[1]->header );
		$this->assertNull( $schemes[1]->token );

		$this->assertSame( 'Cloudflare', $schemes[2]->name );
		$this->assertFalse( $schemes[2]->enabled );
		$this->assertSame( 'CF-Connecting-IP', $schemes[2]->header );
	}

	public function testDefaultSchemesHavePrivateProxies(): void {
		$schemes = Settings::default_schemes();

		// RFC 7239 and XFF should have private IPv4 + localhost + ULA ranges.
		foreach ( [ $schemes[0], $schemes[1] ] as $scheme ) {
			$this->assertContains( '10.0.0.0/8', $scheme->proxies );
			$this->assertContains( '172.16.0.0/12', $scheme->proxies );
			$this->assertContains( '192.168.0.0/16', $scheme->proxies );
			$this->assertContains( '127.0.0.0/8', $scheme->proxies );
			$this->assertContains( '::1/128', $scheme->proxies );
			$this->assertContains( 'fc00::/7', $scheme->proxies );
		}
	}

	public function testDefaultCloudflareHasKnownRanges(): void {
		$schemes = Settings::default_schemes();
		$cf      = $schemes[2];

		$this->assertContains( '173.245.48.0/20', $cf->proxies );
		$this->assertContains( '104.16.0.0/13', $cf->proxies );
		$this->assertContains( '2606:4700::/32', $cf->proxies );
	}

	// ------------------------------------------------------------------
	// Serialisation round-trip
	// ------------------------------------------------------------------

	public function testToArrayAndFromArrayRoundTrip(): void {
		$original = new Settings(
			enabled: false,
			forward_limit: 3,
			process_proto: false,
			process_host: true,
			schemes: [
				new Scheme( 'Test', true, [ '10.0.0.1' ], 'X-Test', 'tok', 'A note' ),
			],
		);

		$array    = $original->to_array();
		$restored = Settings::from_array( $array );

		$this->assertSame( $original->enabled, $restored->enabled );
		$this->assertSame( $original->forward_limit, $restored->forward_limit );
		$this->assertSame( $original->process_proto, $restored->process_proto );
		$this->assertSame( $original->process_host, $restored->process_host );
		$this->assertCount( 1, $restored->schemes );
		$this->assertSame( 'Test', $restored->schemes[0]->name );
		$this->assertSame( 'tok', $restored->schemes[0]->token );
	}

	public function testFromArrayWithEmptySchemesGetsDefaults(): void {
		$s = Settings::from_array( [ 'schemes' => [] ] );

		$this->assertCount( 3, $s->schemes );
		$this->assertSame( 'RFC 7239 Forwarded', $s->schemes[0]->name );
	}

	public function testFromArrayWithMissingSchemesGetsDefaults(): void {
		$s = Settings::from_array( [] );

		$this->assertCount( 3, $s->schemes );
	}

	public function testFromArrayClampsForwardLimit(): void {
		$s = Settings::from_array( [ 'forward_limit' => 999 ] );
		$this->assertSame( Settings::FORWARD_LIMIT_MAX, $s->forward_limit );

		$s = Settings::from_array( [ 'forward_limit' => -5 ] );
		$this->assertSame( Settings::FORWARD_LIMIT_MIN, $s->forward_limit );
	}

	// ------------------------------------------------------------------
	// Validation — top-level settings
	// ------------------------------------------------------------------

	public function testValidateAcceptsValidInput(): void {
		$result = Settings::validate(
			[
				'enabled'       => true,
				'forward_limit' => 2,
				'process_proto' => true,
				'process_host'  => false,
				'schemes'       => [
					[
						'name'    => 'XFF',
						'enabled' => true,
						'proxies' => [ '10.0.0.0/8' ],
						'header'  => 'X-Forwarded-For',
					],
				],
			]
		);

		$this->assertEmpty( $result['errors'] );
		$this->assertTrue( $result['settings']->enabled );
		$this->assertSame( 2, $result['settings']->forward_limit );
		$this->assertCount( 1, $result['settings']->schemes );
	}

	public function testValidateRejectsNonNumericForwardLimit(): void {
		$result = Settings::validate( [ 'forward_limit' => 'abc' ] );

		$this->assertNotEmpty( $result['errors'] );
		$this->assertSame( 1, $result['settings']->forward_limit ); // defaults
	}

	public function testValidateRejectsOutOfRangeForwardLimit(): void {
		$result = Settings::validate( [ 'forward_limit' => 100 ] );

		$this->assertNotEmpty( $result['errors'] );
		$this->assertSame( Settings::FORWARD_LIMIT_MAX, $result['settings']->forward_limit );
	}

	public function testValidateRejectsZeroForwardLimit(): void {
		$result = Settings::validate( [ 'forward_limit' => 0 ] );

		$this->assertNotEmpty( $result['errors'] );
		$this->assertSame( Settings::FORWARD_LIMIT_MIN, $result['settings']->forward_limit );
	}

	public function testValidateNoSchemesGetsDefaults(): void {
		$result = Settings::validate( [] );

		$this->assertCount( 3, $result['settings']->schemes );
	}

	// ------------------------------------------------------------------
	// Validation — schemes
	// ------------------------------------------------------------------

	public function testValidateSchemeRequiresName(): void {
		$result = Settings::validate_scheme(
			[
				'header'  => 'X-Test',
				'proxies' => [],
			],
			1
		);

		$this->assertNotEmpty( $result['errors'] );
		// A placeholder name is assigned.
		$this->assertSame( 'Scheme 1', $result['scheme']->name );
	}

	public function testValidateSchemeRequiresHeader(): void {
		$result = Settings::validate_scheme( [ 'name' => 'Test' ], 1 );

		$this->assertNotEmpty( $result['errors'] );
		$this->assertStringContainsString( 'header', $result['errors'][0] );
	}

	public function testValidateSchemeAcceptsValidProxies(): void {
		$result = Settings::validate_scheme(
			[
				'name'    => 'Test',
				'header'  => 'X-Test',
				'proxies' => [ '10.0.0.0/8', '192.168.1.1', '::1/128', '2001:db8::1' ],
			],
			1
		);

		$this->assertEmpty( $result['errors'] );
		$this->assertCount( 4, $result['scheme']->proxies );
	}

	public function testValidateSchemeRejectsInvalidProxy(): void {
		$result = Settings::validate_scheme(
			[
				'name'    => 'Test',
				'header'  => 'X-Test',
				'proxies' => [ '10.0.0.0/8', 'not-an-ip', '192.168.1.1' ],
			],
			1
		);

		$this->assertCount( 1, $result['errors'] );
		$this->assertStringContainsString( 'not-an-ip', $result['errors'][0] );
		// Valid ones still kept.
		$this->assertCount( 2, $result['scheme']->proxies );
	}

	public function testValidateSchemeRejectsBadCidr(): void {
		$result = Settings::validate_scheme(
			[
				'name'    => 'Test',
				'header'  => 'X-Test',
				'proxies' => [ '10.0.0.0/33' ], // /33 is invalid for IPv4
			],
			1
		);

		$this->assertCount( 1, $result['errors'] );
		$this->assertEmpty( $result['scheme']->proxies );
	}

	public function testValidateSchemeRejectsNegativeCidr(): void {
		$result = Settings::validate_scheme(
			[
				'name'    => 'Test',
				'header'  => 'X-Test',
				'proxies' => [ '10.0.0.0/-1' ],
			],
			1
		);

		$this->assertCount( 1, $result['errors'] );
	}

	public function testValidateSchemeAcceptsProxiesAsNewlineSeparatedString(): void {
		$result = Settings::validate_scheme(
			[
				'name'    => 'Test',
				'header'  => 'X-Test',
				'proxies' => "10.0.0.0/8\n192.168.1.1\n::1",
			],
			1
		);

		$this->assertEmpty( $result['errors'] );
		$this->assertCount( 3, $result['scheme']->proxies );
	}

	public function testValidateSchemeAcceptsProxiesAsCommaSeparatedString(): void {
		$result = Settings::validate_scheme(
			[
				'name'    => 'Test',
				'header'  => 'X-Test',
				'proxies' => '10.0.0.0/8, 192.168.1.1',
			],
			1
		);

		$this->assertEmpty( $result['errors'] );
		$this->assertCount( 2, $result['scheme']->proxies );
	}

	public function testValidateSchemeTruncatesLongName(): void {
		$long_name = str_repeat( 'A', 200 );
		$result    = Settings::validate_scheme(
			[
				'name'   => $long_name,
				'header' => 'X-Test',
			],
			1
		);

		$this->assertSame( Settings::SCHEME_NAME_MAX_LENGTH, strlen( $result['scheme']->name ) );
	}

	public function testValidateSchemePreservesToken(): void {
		$result = Settings::validate_scheme(
			[
				'name'   => 'Fwd',
				'header' => 'Forwarded',
				'token'  => 'for',
			],
			1
		);

		$this->assertEmpty( $result['errors'] );
		$this->assertSame( 'for', $result['scheme']->token );
	}

	public function testValidateSchemeEmptyTokenBecomesNull(): void {
		$result = Settings::validate_scheme(
			[
				'name'   => 'XFF',
				'header' => 'X-Forwarded-For',
				'token'  => '',
			],
			1
		);

		$this->assertNull( $result['scheme']->token );
	}

	// ------------------------------------------------------------------
	// Validation — proxy entries
	// ------------------------------------------------------------------

	public function testValidateProxyAcceptsIpv4(): void {
		$this->assertNotNull( Settings::validate_proxy( '10.0.0.1' ) );
		$this->assertNotNull( Settings::validate_proxy( '192.168.1.1' ) );
		$this->assertNotNull( Settings::validate_proxy( '255.255.255.255' ) );
	}

	public function testValidateProxyAcceptsIpv6(): void {
		$this->assertNotNull( Settings::validate_proxy( '::1' ) );
		$this->assertNotNull( Settings::validate_proxy( '2001:db8::1' ) );
		$this->assertNotNull( Settings::validate_proxy( 'fc00::' ) );
	}

	public function testValidateProxyAcceptsCidrV4(): void {
		$this->assertSame( '10.0.0.0/8', Settings::validate_proxy( '10.0.0.0/8' ) );
		$this->assertSame( '192.168.0.0/16', Settings::validate_proxy( '192.168.0.0/16' ) );
		$this->assertSame( '172.16.0.0/12', Settings::validate_proxy( '172.16.0.0/12' ) );
	}

	public function testValidateProxyAcceptsCidrV6(): void {
		$result = Settings::validate_proxy( 'fc00::/7' );
		$this->assertNotNull( $result );
		$this->assertStringContainsString( '/7', $result );

		$result = Settings::validate_proxy( '2001:db8::/32' );
		$this->assertNotNull( $result );
		$this->assertStringContainsString( '/32', $result );
	}

	public function testValidateProxyRejectsGarbage(): void {
		$this->assertNull( Settings::validate_proxy( 'not-an-ip' ) );
		$this->assertNull( Settings::validate_proxy( 'hello world' ) );
		$this->assertNull( Settings::validate_proxy( '' ) );
	}

	public function testValidateProxyRejectsInvalidCidr(): void {
		$this->assertNull( Settings::validate_proxy( '10.0.0.0/33' ) );
		$this->assertNull( Settings::validate_proxy( '10.0.0.0/-1' ) );
		$this->assertNull( Settings::validate_proxy( '::1/129' ) );
		$this->assertNull( Settings::validate_proxy( '10.0.0.0/abc' ) );
	}

	public function testValidateProxyStripsWhitespace(): void {
		$result = Settings::validate_proxy( '  10.0.0.1  ' );
		$this->assertSame( '10.0.0.1', $result );
	}

	public function testValidateProxyStripsBrackets(): void {
		$result = Settings::validate_proxy( '[::1]' );
		$this->assertNotNull( $result );
		$this->assertSame( '::1', $result );
	}

	// ------------------------------------------------------------------
	// Validation — header names
	// ------------------------------------------------------------------

	public function testSanitiseHeaderNameAcceptsValid(): void {
		$this->assertSame( 'X-Forwarded-For', Settings::sanitise_header_name( 'X-Forwarded-For' ) );
		$this->assertSame( 'Forwarded', Settings::sanitise_header_name( 'Forwarded' ) );
		$this->assertSame( 'CF-Connecting-IP', Settings::sanitise_header_name( 'CF-Connecting-IP' ) );
		$this->assertSame( 'X_Custom_Header', Settings::sanitise_header_name( 'X_Custom_Header' ) );
	}

	public function testSanitiseHeaderNameRejectsSpecialChars(): void {
		$this->assertSame( '', Settings::sanitise_header_name( 'X-Forwarded For' ) );  // space
		$this->assertSame( '', Settings::sanitise_header_name( 'Header<script>' ) );   // angle brackets
	}

	public function testSanitiseHeaderNameTrims(): void {
		$this->assertSame( 'X-Test', Settings::sanitise_header_name( '  X-Test  ' ) );
	}

	public function testSanitiseHeaderNameTruncates(): void {
		$long = str_repeat( 'A', 200 );
		$this->assertSame( Settings::HEADER_NAME_MAX_LENGTH, strlen( Settings::sanitise_header_name( $long ) ) );
	}

	// ------------------------------------------------------------------
	// Validation — scheme count limits
	// ------------------------------------------------------------------

	public function testValidateRejectsTooManySchemes(): void {
		$schemes = [];
		for ( $i = 0; $i < 25; $i++ ) {
			$schemes[] = [
				'name'    => "Scheme $i",
				'header'  => 'X-Test',
				'enabled' => true,
				'proxies' => [ '10.0.0.1' ],
			];
		}

		$result = Settings::validate( [ 'schemes' => $schemes ] );

		$this->assertNotEmpty( $result['errors'] );
		$this->assertCount( Settings::MAX_SCHEMES, $result['settings']->schemes );
	}

	// ------------------------------------------------------------------
	// Load (without WordPress — falls back to defaults)
	// ------------------------------------------------------------------

	public function testLoadWithoutWordPressFallsToDefaults(): void {
		// Since we're in a pure unit test, function_exists('get_option')
		// may or may not be true depending on integration bootstrap.
		// In a pure environment, it should return defaults.
		$s = Settings::load();

		$this->assertTrue( $s->enabled );
		$this->assertSame( 1, $s->forward_limit );
		$this->assertCount( 3, $s->schemes );
	}
}
