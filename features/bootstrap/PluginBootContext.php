<?php

declare(strict_types=1);

use Behat\Behat\Context\Context;
use Behat\Gherkin\Node\TableNode;
use Gryphon\VerifiedClientIp\Plugin;
use Gryphon\VerifiedClientIp\Scheme;

require_once __DIR__ . '/../../tests/Integration/bootstrap.php';

final class PluginBootContext implements Context {

	/** @var array<string, string> Backup of original $_SERVER. */
	private array $original_server = [];

	/**
	 * @BeforeScenario
	 */
	public function reset_globals(): void {
		$this->original_server = $_SERVER;

		// Reset the singleton so each test gets a fresh boot.
		$ref  = new ReflectionClass( Plugin::class );
		$prop = $ref->getProperty( 'instance' );
		$prop->setValue( null, null );

		// Reset test globals.
		$GLOBALS['_vcip_test_options']    = [];
		$GLOBALS['_vcip_test_filters']    = [];
		$GLOBALS['_vcip_test_actions']    = [];
		$GLOBALS['_vcip_test_transients'] = [];
	}

	/**
	 * @AfterScenario
	 */
	public function restore_server(): void {
		$_SERVER = $this->original_server;

		// Reset singleton again.
		$ref  = new ReflectionClass( Plugin::class );
		$prop = $ref->getProperty( 'instance' );
		$prop->setValue( null, null );
	}

	// ------------------------------------------------------------------
	// Helpers
	// ------------------------------------------------------------------

	/**
	 * Configure test settings in the simulated wp_options.
	 *
	 * @param array<string, mixed> $overrides
	 */
	private function set_settings( array $overrides = [] ): void {
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
	private function set_schemes( array $schemes ): void {
		$settings = $GLOBALS['_vcip_test_options']['vcip_settings'] ?? [];

		$settings['schemes'] = array_map(
			static fn ( Scheme $s ): array => $s->to_array(),
			$schemes,
		);

		$GLOBALS['_vcip_test_options']['vcip_settings'] = $settings;
	}

	// ------------------------------------------------------------------
	// Given steps
	// ------------------------------------------------------------------

	/**
	 * @Given the plugin settings are:
	 */
	public function the_plugin_settings_are( TableNode $table ): void {
		$settings = [];
		foreach ( $table->getHash() as $row ) {
			$value = $row['value'];
			if ( is_numeric( $value ) ) {
				$value = (int) $value;
			}
			$settings[ $row['key'] ] = $value;
		}

		// Convert 0/1 to bool for known boolean keys.
		foreach ( [ 'enabled', 'process_proto', 'process_host' ] as $bool_key ) {
			if ( array_key_exists( $bool_key, $settings ) ) {
				$settings[ $bool_key ] = (bool) $settings[ $bool_key ];
			}
		}

		$this->set_settings( $settings );
	}

	/**
	 * @Given the plugin has an XFF scheme trusting :cidr
	 */
	public function the_plugin_has_an_xff_scheme_trusting( string $cidr ): void {
		$this->set_schemes(
			[
				new Scheme( 'XFF', true, [ $cidr ], 'X-Forwarded-For' ),
			]
		);
	}

	/**
	 * @Given the plugin has an XFF scheme trusting :cidr1 and :cidr2
	 */
	public function the_plugin_has_an_xff_scheme_trusting_two( string $cidr1, string $cidr2 ): void {
		$this->set_schemes(
			[
				new Scheme( 'XFF', true, [ $cidr1, $cidr2 ], 'X-Forwarded-For' ),
			]
		);
	}

	/**
	 * @Given the plugin has a Forwarded scheme trusting :cidr
	 */
	public function the_plugin_has_a_forwarded_scheme_trusting( string $cidr ): void {
		$this->set_schemes(
			[
				new Scheme( 'Fwd', true, [ $cidr ], 'Forwarded', 'for' ),
			]
		);
	}

	/**
	 * @Given the server var :key is :value
	 */
	public function the_server_var_is( string $key, string $value ): void {
		$_SERVER[ $key ] = $value;
	}

	// ------------------------------------------------------------------
	// When steps
	// ------------------------------------------------------------------

	/**
	 * @When the plugin boots
	 */
	public function the_plugin_boots(): void {
		Plugin::boot();
	}

	/**
	 * @When the server var :key is changed to :value
	 */
	public function the_server_var_is_changed_to( string $key, string $value ): void {
		$_SERVER[ $key ] = $value;
	}

	/**
	 * @When the plugin boots again
	 */
	public function the_plugin_boots_again(): void {
		Plugin::boot();
	}

	// ------------------------------------------------------------------
	// Then steps — server vars
	// ------------------------------------------------------------------

	/**
	 * @Then the server var :key should be :value
	 */
	public function the_server_var_should_be( string $key, string $value ): void {
		$actual = $_SERVER[ $key ] ?? null;
		if ( (string) $actual !== $value ) {
			throw new RuntimeException(
				sprintf( 'Expected $_SERVER[%s] to be "%s", got "%s".', $key, $value, (string) $actual )
			);
		}
	}

	/**
	 * @Then the server var :key should not be set
	 */
	public function the_server_var_should_not_be_set( string $key ): void {
		if ( array_key_exists( $key, $_SERVER ) ) {
			throw new RuntimeException(
				sprintf( 'Expected $_SERVER[%s] not to be set, but it is "%s".', $key, (string) $_SERVER[ $key ] )
			);
		}
	}

	// ------------------------------------------------------------------
	// Then steps — plugin result
	// ------------------------------------------------------------------

	/**
	 * @Then the plugin last result should not be null
	 */
	public function the_plugin_last_result_should_not_be_null(): void {
		$instance = Plugin::instance();
		if ( null === $instance || null === $instance->last_result() ) {
			throw new RuntimeException( 'Expected plugin last_result() to be non-null.' );
		}
	}

	/**
	 * @Then the plugin last result changed should be true
	 */
	public function the_plugin_last_result_changed_should_be_true(): void {
		$result = Plugin::instance()->last_result();
		if ( true !== $result->changed ) {
			throw new RuntimeException( 'Expected last_result()->changed to be true.' );
		}
	}

	/**
	 * @Then the plugin last result resolved_ip should be :value
	 */
	public function the_plugin_last_result_resolved_ip_should_be( string $value ): void {
		$result = Plugin::instance()->last_result();
		if ( $result->resolved_ip !== $value ) {
			throw new RuntimeException(
				sprintf( 'Expected last_result()->resolved_ip "%s", got "%s".', $value, $result->resolved_ip )
			);
		}
	}

	// ------------------------------------------------------------------
	// Then steps — WordPress hooks
	// ------------------------------------------------------------------

	/**
	 * @Then the filter :name should have been applied
	 */
	public function the_filter_should_have_been_applied( string $name ): void {
		if ( ! array_key_exists( $name, $GLOBALS['_vcip_test_filters'] ) ) {
			throw new RuntimeException(
				sprintf( 'Expected filter "%s" to have been applied.', $name )
			);
		}
	}

	/**
	 * @Then the filter :name first argument should be :value
	 */
	public function the_filter_first_argument_should_be( string $name, string $value ): void {
		$actual = (string) $GLOBALS['_vcip_test_filters'][ $name ][0];
		if ( $actual !== $value ) {
			throw new RuntimeException(
				sprintf( 'Expected filter "%s" first arg "%s", got "%s".', $name, $value, $actual )
			);
		}
	}

	/**
	 * @Then the action :name should have been fired
	 */
	public function the_action_should_have_been_fired( string $name ): void {
		if ( ! array_key_exists( $name, $GLOBALS['_vcip_test_actions'] ) ) {
			throw new RuntimeException(
				sprintf( 'Expected action "%s" to have been fired.', $name )
			);
		}
	}

	/**
	 * @Then the action :name should not have been fired
	 */
	public function the_action_should_not_have_been_fired( string $name ): void {
		if ( array_key_exists( $name, $GLOBALS['_vcip_test_actions'] ) ) {
			throw new RuntimeException(
				sprintf( 'Expected action "%s" not to have been fired.', $name )
			);
		}
	}

	/**
	 * @Then the action :name argument :index should be :value
	 */
	public function the_action_argument_should_be( string $name, int $index, string $value ): void {
		$actual = (string) $GLOBALS['_vcip_test_actions'][ $name ][0][ $index ];
		if ( $actual !== $value ) {
			throw new RuntimeException(
				sprintf( 'Expected action "%s" arg %d to be "%s", got "%s".', $name, $index, $value, $actual )
			);
		}
	}

	/**
	 * @Then the action :name argument :index should be an array
	 */
	public function the_action_argument_should_be_array( string $name, int $index ): void {
		$actual = $GLOBALS['_vcip_test_actions'][ $name ][0][ $index ];
		if ( ! is_array( $actual ) ) {
			throw new RuntimeException(
				sprintf( 'Expected action "%s" arg %d to be an array.', $name, $index )
			);
		}
	}

	// ------------------------------------------------------------------
	// Then steps — default schemes
	// ------------------------------------------------------------------

	/**
	 * @Then the default schemes should contain :count entries
	 */
	public function the_default_schemes_should_contain_entries( int $count ): void {
		$schemes = Plugin::default_schemes();
		$actual  = count( $schemes );
		if ( $actual !== $count ) {
			throw new RuntimeException(
				sprintf( 'Expected %d default schemes, got %d.', $count, $actual )
			);
		}
	}

	/**
	 * @Then the default scheme :index name should be :name
	 */
	public function the_default_scheme_name_should_be( int $index, string $name ): void {
		$schemes = Plugin::default_schemes();
		$actual  = $schemes[ $index ]->name;
		if ( $actual !== $name ) {
			throw new RuntimeException(
				sprintf( 'Expected default scheme[%d] name "%s", got "%s".', $index, $name, $actual )
			);
		}
	}

	/**
	 * @Then the default scheme :index should be enabled
	 */
	public function the_default_scheme_should_be_enabled( int $index ): void {
		$schemes = Plugin::default_schemes();
		if ( true !== $schemes[ $index ]->enabled ) {
			throw new RuntimeException(
				sprintf( 'Expected default scheme[%d] to be enabled.', $index )
			);
		}
	}

	/**
	 * @Then the default scheme :index should be disabled
	 */
	public function the_default_scheme_should_be_disabled( int $index ): void {
		$schemes = Plugin::default_schemes();
		if ( false !== $schemes[ $index ]->enabled ) {
			throw new RuntimeException(
				sprintf( 'Expected default scheme[%d] to be disabled.', $index )
			);
		}
	}
}
