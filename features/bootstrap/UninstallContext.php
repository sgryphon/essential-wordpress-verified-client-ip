<?php

declare(strict_types=1);

use Behat\Behat\Context\Context;

require_once __DIR__ . '/../../tests/Integration/bootstrap.php';

final class UninstallContext implements Context {

	/**
	 * @BeforeScenario
	 */
	public function reset_globals(): void {
		$GLOBALS['_vcip_test_options']         = [];
		$GLOBALS['_vcip_test_transients']      = [];
		$GLOBALS['_vcip_test_uninstall_blogs'] = [];
		$GLOBALS['_vcip_test_is_multisite']    = false;

		$this->define_uninstall_stubs();
	}

	// ------------------------------------------------------------------
	// Helpers
	// ------------------------------------------------------------------

	/**
	 * Define stubs needed by uninstall.php that aren't in the bootstrap.
	 */
	private function define_uninstall_stubs(): void {
		if ( ! function_exists( 'is_multisite' ) ) {
			eval( 'function is_multisite(): bool { return $GLOBALS["_vcip_test_is_multisite"] ?? false; }' ); // phpcs:ignore Squiz.PHP.Eval.Discouraged
		}

		if ( ! function_exists( 'get_sites' ) ) {
			eval( 'function get_sites(array $args = []): array { return $GLOBALS["_vcip_test_sites"] ?? []; }' ); // phpcs:ignore Squiz.PHP.Eval.Discouraged
		}

		if ( ! function_exists( 'switch_to_blog' ) ) {
			eval( 'function switch_to_blog(int $id): bool { $GLOBALS["_vcip_test_uninstall_blogs"][] = $id; return true; }' ); // phpcs:ignore Squiz.PHP.Eval.Discouraged
		}

		if ( ! function_exists( 'restore_current_blog' ) ) {
			eval( 'function restore_current_blog(): bool { return true; }' ); // phpcs:ignore Squiz.PHP.Eval.Discouraged
		}
	}

	// ------------------------------------------------------------------
	// Given steps
	// ------------------------------------------------------------------

	/**
	 * @Given the option :name is populated
	 */
	public function the_option_is_populated( string $name ): void {
		$GLOBALS['_vcip_test_options'][ $name ] = [ 'test' => true ];
	}

	/**
	 * @Given the transient :name is populated
	 */
	public function the_transient_is_populated( string $name ): void {
		$GLOBALS['_vcip_test_transients'][ $name ] = [ 'test' => true ];
	}

	// ------------------------------------------------------------------
	// When steps
	// ------------------------------------------------------------------

	/**
	 * @When the uninstall script is executed
	 */
	public function the_uninstall_script_is_executed(): void {
		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', true );
		}

		include dirname( __DIR__, 2 ) . '/uninstall.php';
	}

	/**
	 * @When vcip_uninstall_site is called
	 */
	public function vcip_uninstall_site_is_called(): void {
		// Ensure the function is defined (from a previous uninstall include).
		if ( ! function_exists( 'vcip_uninstall_site' ) ) {
			if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
				define( 'WP_UNINSTALL_PLUGIN', true );
			}
			include dirname( __DIR__, 2 ) . '/uninstall.php';
			// Reset to repopulate for 'When' step.
			return;
		}

		vcip_uninstall_site();
	}

	// ------------------------------------------------------------------
	// Then steps
	// ------------------------------------------------------------------

	/**
	 * @Then the option :name should not exist
	 */
	public function the_option_should_not_exist( string $name ): void {
		if ( array_key_exists( $name, $GLOBALS['_vcip_test_options'] ) ) {
			throw new RuntimeException(
				sprintf( 'Expected option "%s" to not exist.', $name )
			);
		}
	}

	/**
	 * @Then the transient :name should not exist
	 */
	public function the_transient_should_not_exist( string $name ): void {
		if ( array_key_exists( $name, $GLOBALS['_vcip_test_transients'] ) ) {
			throw new RuntimeException(
				sprintf( 'Expected transient "%s" to not exist.', $name )
			);
		}
	}
}
