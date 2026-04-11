<?php

declare(strict_types=1);

namespace Gryphon\VerifiedClientIp\Tests\Integration;

// Load WordPress function stubs for testing.
require_once __DIR__ . '/bootstrap.php';

use PHPUnit\Framework\TestCase;
use Gryphon\VerifiedClientIp\AdminPage;
use Gryphon\VerifiedClientIp\Settings;

/**
 * Integration tests for the AdminPage class.
 *
 * Tests form parsing, rendering logic, and form submission processing
 * using the WordPress function stubs defined in bootstrap.php.
 */
final class AdminPageTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['_vcip_test_options'] = [];
		$GLOBALS['_vcip_test_filters'] = [];
		$GLOBALS['_vcip_test_actions'] = [];
	}

	// ------------------------------------------------------------------
	// Form input parsing
	// ------------------------------------------------------------------

	public function testParseFormInputBasicSettings(): void {
		$post = [
			'vcip_enabled'       => '1',
			'vcip_forward_limit' => '3',
			'vcip_process_proto' => '1',
			'vcip_process_host'  => '0',
		];

		$result = AdminPage::parse_form_input( $post );

		$this->assertTrue( $result['enabled'] );
		$this->assertSame( '3', $result['forward_limit'] );
		$this->assertTrue( $result['process_proto'] );
		$this->assertFalse( $result['process_host'] );
	}

	public function testParseFormInputDisabledCheckboxes(): void {
		// Checkboxes not present in POST = disabled.
		$post = [
			'vcip_forward_limit' => '1',
		];

		$result = AdminPage::parse_form_input( $post );

		$this->assertFalse( $result['enabled'] );
		$this->assertFalse( $result['process_proto'] );
		$this->assertFalse( $result['process_host'] );
	}

	public function testParseFormInputSchemes(): void {
		$post = [
			'vcip_enabled'       => '1',
			'vcip_forward_limit' => '1',
			'vcip_schemes'       => [
				[
					'name'    => 'XFF',
					'enabled' => '1',
					'header'  => 'X-Forwarded-For',
					'token'   => '',
					'proxies' => "10.0.0.0/8\n192.168.0.0/16",
					'notes'   => 'Test scheme',
				],
				[
					'name'    => 'Forwarded',
					'enabled' => '0',
					'header'  => 'Forwarded',
					'token'   => 'for',
					'proxies' => '172.16.0.0/12',
					'notes'   => '',
				],
			],
		];

		$result = AdminPage::parse_form_input( $post );

		$this->assertCount( 2, $result['schemes'] );

		$this->assertSame( 'XFF', $result['schemes'][0]['name'] );
		$this->assertTrue( $result['schemes'][0]['enabled'] );
		$this->assertSame( 'X-Forwarded-For', $result['schemes'][0]['header'] );
		$this->assertSame( '', $result['schemes'][0]['token'] );
		$this->assertCount( 2, $result['schemes'][0]['proxies'] );
		$this->assertSame( '10.0.0.0/8', $result['schemes'][0]['proxies'][0] );
		$this->assertSame( '192.168.0.0/16', $result['schemes'][0]['proxies'][1] );

		$this->assertSame( 'Forwarded', $result['schemes'][1]['name'] );
		$this->assertFalse( $result['schemes'][1]['enabled'] );
		$this->assertSame( 'for', $result['schemes'][1]['token'] );
	}

	public function testParseFormInputNoSchemes(): void {
		$post = [
			'vcip_enabled'       => '1',
			'vcip_forward_limit' => '1',
		];

		$result = AdminPage::parse_form_input( $post );

		$this->assertArrayNotHasKey( 'schemes', $result );
	}

	public function testParseFormInputEmptyProxiesTextarea(): void {
		$post = [
			'vcip_enabled'       => '1',
			'vcip_forward_limit' => '1',
			'vcip_schemes'       => [
				[
					'name'    => 'Test',
					'header'  => 'X-Test',
					'proxies' => '',
				],
			],
		];

		$result = AdminPage::parse_form_input( $post );

		$this->assertEmpty( $result['schemes'][0]['proxies'] );
	}

	public function testParseFormInputProxiesWithBlankLines(): void {
		$post = [
			'vcip_schemes' => [
				[
					'name'    => 'Test',
					'header'  => 'X-Test',
					'proxies' => "10.0.0.0/8\n\n\n192.168.1.1\n",
				],
			],
		];

		$result = AdminPage::parse_form_input( $post );

		// Blank lines should be filtered out.
		$this->assertCount( 2, $result['schemes'][0]['proxies'] );
	}

	// ------------------------------------------------------------------
	// Form parsing → Validation round trip
	// ------------------------------------------------------------------

	public function testParseAndValidateRoundTrip(): void {
		$post = [
			'vcip_enabled'       => '1',
			'vcip_forward_limit' => '2',
			'vcip_process_proto' => '1',
			'vcip_process_host'  => '1',
			'vcip_schemes'       => [
				[
					'name'    => 'My Proxy',
					'enabled' => '1',
					'header'  => 'X-Forwarded-For',
					'token'   => '',
					'proxies' => "10.0.0.0/8\n192.168.0.0/16",
					'notes'   => 'Custom config',
				],
			],
		];

		$parsed = AdminPage::parse_form_input( $post );
		$result = Settings::validate( $parsed );

		$this->assertEmpty( $result['errors'] );
		$this->assertTrue( $result['settings']->enabled );
		$this->assertSame( 2, $result['settings']->forward_limit );
		$this->assertTrue( $result['settings']->process_proto );
		$this->assertTrue( $result['settings']->process_host );
		$this->assertCount( 1, $result['settings']->schemes );
		$this->assertSame( 'My Proxy', $result['settings']->schemes[0]->name );
		$this->assertSame( 'X-Forwarded-For', $result['settings']->schemes[0]->header );
		$this->assertNull( $result['settings']->schemes[0]->token );
		$this->assertCount( 2, $result['settings']->schemes[0]->proxies );
	}

	public function testParseAndValidateWithInvalidProxy(): void {
		$post = [
			'vcip_schemes' => [
				[
					'name'    => 'Bad',
					'enabled' => '1',
					'header'  => 'X-Test',
					'proxies' => "10.0.0.0/8\nnot-an-ip\n192.168.1.1",
				],
			],
		];

		$parsed = AdminPage::parse_form_input( $post );
		$result = Settings::validate( $parsed );

		$this->assertNotEmpty( $result['errors'] );
		// Valid proxies are kept, invalid one is flagged.
		$this->assertCount( 2, $result['settings']->schemes[0]->proxies );
	}
}
