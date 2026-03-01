<?php

declare(strict_types=1);

namespace VerifiedClientIp\Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * Tests for uninstall.php.
 *
 * @coversNothing
 */
final class UninstallTest extends TestCase {

	protected function setUp(): void {
		// Reset stores.
		$GLOBALS['_vcip_test_options']         = [];
		$GLOBALS['_vcip_test_transients']      = [];
		$GLOBALS['_vcip_test_uninstall_blogs'] = [];
	}

	public function testUninstallDeletesOptionsAndTransients(): void {
		// Populate data.
		$GLOBALS['_vcip_test_options']['vcip_settings']            = [ 'enabled' => true ];
		$GLOBALS['_vcip_test_transients']['vcip_diagnostic_log']   = [ [ 'entry' => 1 ] ];
		$GLOBALS['_vcip_test_transients']['vcip_diagnostic_state'] = [ 'recording' => true ];
		$GLOBALS['_vcip_test_transients']['vcip_diagnostic_lock']  = '1';

		// Simulate WP_UNINSTALL_PLUGIN being defined.
		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', true );
		}

		// Define uninstall stubs.
		$this->defineUninstallStubs( false );

		// Include uninstall.php.
		include dirname( __DIR__, 2 ) . '/uninstall.php';

		// Options should be deleted.
		$this->assertArrayNotHasKey( 'vcip_settings', $GLOBALS['_vcip_test_options'] );

		// Transients should be deleted.
		$this->assertArrayNotHasKey( 'vcip_diagnostic_log', $GLOBALS['_vcip_test_transients'] );
		$this->assertArrayNotHasKey( 'vcip_diagnostic_state', $GLOBALS['_vcip_test_transients'] );
		$this->assertArrayNotHasKey( 'vcip_diagnostic_lock', $GLOBALS['_vcip_test_transients'] );
	}

	/**
	 * @depends testUninstallDeletesOptionsAndTransients
	 */
	public function testUninstallCallsVcipUninstallSite(): void {
		// The function vcip_uninstall_site was defined in the previous test's
		// include — just check it exists and call it.
		$this->assertTrue( \function_exists( 'vcip_uninstall_site' ) );

		// Populate and run.
		$GLOBALS['_vcip_test_options']['vcip_settings']          = [ 'test' => true ];
		$GLOBALS['_vcip_test_transients']['vcip_diagnostic_log'] = [];

		vcip_uninstall_site();

		$this->assertArrayNotHasKey( 'vcip_settings', $GLOBALS['_vcip_test_options'] );
		$this->assertArrayNotHasKey( 'vcip_diagnostic_log', $GLOBALS['_vcip_test_transients'] );
	}

	/**
	 * Define stubs needed by uninstall.php that aren't in the bootstrap.
	 */
	private function defineUninstallStubs( bool $multisite ): void {
		if ( ! \function_exists( 'is_multisite' ) ) {
			eval( 'function is_multisite(): bool { return $GLOBALS["_vcip_test_is_multisite"] ?? false; }' ); // phpcs:ignore Squiz.PHP.Eval.Discouraged
		}
		$GLOBALS['_vcip_test_is_multisite'] = $multisite;

		if ( ! \function_exists( 'delete_option' ) ) {
			eval( 'function delete_option(string $option): bool { unset($GLOBALS["_vcip_test_options"][$option]); return true; }' ); // phpcs:ignore Squiz.PHP.Eval.Discouraged
		}

		if ( ! \function_exists( 'get_sites' ) ) {
			eval( 'function get_sites(array $args = []): array { return $GLOBALS["_vcip_test_sites"] ?? []; }' ); // phpcs:ignore Squiz.PHP.Eval.Discouraged
		}

		if ( ! \function_exists( 'switch_to_blog' ) ) {
			eval( 'function switch_to_blog(int $id): bool { $GLOBALS["_vcip_test_uninstall_blogs"][] = $id; return true; }' ); // phpcs:ignore Squiz.PHP.Eval.Discouraged
		}

		if ( ! \function_exists( 'restore_current_blog' ) ) {
			eval( 'function restore_current_blog(): bool { return true; }' ); // phpcs:ignore Squiz.PHP.Eval.Discouraged
		}
	}
}
