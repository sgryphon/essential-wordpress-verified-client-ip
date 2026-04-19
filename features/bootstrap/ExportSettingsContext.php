<?php

declare(strict_types=1);

use Behat\Behat\Context\Context;
use Gryphon\VerifiedClientIp\Scheme;
use Gryphon\VerifiedClientIp\Settings;

require_once __DIR__ . '/wordpress-stubs.php';

final class ExportSettingsContext implements Context {

	/** @var Settings|null */
	private ?Settings $settings = null;

	/** @var array<string, mixed>|null */
	private ?array $export_result = null;

	/** @var string|null */
	private ?string $export_json = null;

	/** @var string|null */
	private ?string $filename = null;

	/**
	 * @BeforeScenario
	 */
	public function reset(): void {
		$this->settings      = null;
		$this->export_result = null;
		$this->export_json   = null;
		$this->filename      = null;
	}

	// ------------------------------------------------------------------
	// Given steps
	// ------------------------------------------------------------------

	/**
	 * @Given the plugin has default settings
	 */
	public function the_plugin_has_default_settings(): void {
		$this->settings = Settings::defaults();
	}

	/**
	 * @Given settings with enabled :enabled, forward_limit :limit, process_proto :proto, process_host :host
	 */
	public function settings_with_general_values( string $enabled, int $limit, string $proto, string $host ): void {
		$this->settings = new Settings(
			enabled: 'true' === $enabled,
			forward_limit: $limit,
			process_proto: 'true' === $proto,
			process_host: 'true' === $host,
			schemes: [],
		);
	}

	/**
	 * @Given a single scheme named :name enabled with header :header and no token and proxies :proxies and notes :notes
	 */
	public function a_single_scheme( string $name, string $header, string $proxies, string $notes ): void {
		if ( null === $this->settings ) {
			throw new RuntimeException( 'Settings must be initialised first.' );
		}

		$proxy_list = array_filter( array_map( 'trim', explode( ',', $proxies ) ) );

		$scheme = new Scheme(
			name: $name,
			enabled: true,
			proxies: $proxy_list,
			header: $header,
			token: null,
			notes: $notes,
		);

		$this->settings = new Settings(
			enabled: $this->settings->enabled,
			forward_limit: $this->settings->forward_limit,
			process_proto: $this->settings->process_proto,
			process_host: $this->settings->process_host,
			schemes: [ $scheme ],
		);
	}

	// ------------------------------------------------------------------
	// When steps
	// ------------------------------------------------------------------

	/**
	 * @When the settings are exported with site URL :url and timestamp :timestamp
	 */
	public function the_settings_are_exported( string $url, string $timestamp ): void {
		if ( null === $this->settings ) {
			throw new RuntimeException( 'Settings must be initialised first.' );
		}

		$this->export_result = $this->settings->to_export_array( $url, $timestamp );
	}

	/**
	 * @When the settings are exported as JSON with site URL :url and timestamp :timestamp
	 */
	public function the_settings_are_exported_as_json( string $url, string $timestamp ): void {
		if ( null === $this->settings ) {
			throw new RuntimeException( 'Settings must be initialised first.' );
		}

		$export_data       = $this->settings->to_export_array( $url, $timestamp );
		$this->export_json = wp_json_encode( $export_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
	}

	/**
	 * @When an export filename is generated at UTC time :datetime
	 */
	public function an_export_filename_is_generated_at( string $datetime ): void {
		$time           = new DateTimeImmutable( $datetime, new DateTimeZone( 'UTC' ) );
		$this->filename = 'vcip-settings-' . $time->format( 'Ymd-His' ) . '.json';
	}

	// ------------------------------------------------------------------
	// Then steps — metadata
	// ------------------------------------------------------------------

	/**
	 * @Then the export should contain metadata key :key with value :value
	 */
	public function the_export_should_contain_metadata_key( string $key, string $value ): void {
		$this->assert_export_available();

		if ( ! isset( $this->export_result['metadata'][ $key ] ) ) {
			throw new RuntimeException(
				sprintf( 'Metadata key "%s" not found.', $key )
			);
		}

		$actual = $this->export_result['metadata'][ $key ];
		if ( $actual !== $value ) {
			throw new RuntimeException(
				sprintf( 'Expected metadata[%s] to be "%s", got "%s".', $key, $value, $actual )
			);
		}
	}

	// ------------------------------------------------------------------
	// Then steps — general
	// ------------------------------------------------------------------

	/**
	 * @Then the export general key :key should be true
	 */
	public function the_export_general_key_should_be_true( string $key ): void {
		$this->assert_export_available();

		if ( true !== $this->export_result['general'][ $key ] ) {
			throw new RuntimeException(
				sprintf( 'Expected general[%s] to be true, got %s.', $key, var_export( $this->export_result['general'][ $key ], true ) )
			);
		}
	}

	/**
	 * @Then the export general key :key should be false
	 */
	public function the_export_general_key_should_be_false( string $key ): void {
		$this->assert_export_available();

		if ( false !== $this->export_result['general'][ $key ] ) {
			throw new RuntimeException(
				sprintf( 'Expected general[%s] to be false, got %s.', $key, var_export( $this->export_result['general'][ $key ], true ) )
			);
		}
	}

	/**
	 * @Then the export general key :key should be integer :value
	 */
	public function the_export_general_key_should_be_integer( string $key, int $value ): void {
		$this->assert_export_available();

		$actual = $this->export_result['general'][ $key ];
		if ( $actual !== $value ) {
			throw new RuntimeException(
				sprintf( 'Expected general[%s] to be %d, got %s.', $key, $value, var_export( $actual, true ) )
			);
		}
	}

	// ------------------------------------------------------------------
	// Then steps — schemes
	// ------------------------------------------------------------------

	/**
	 * @Then the export should contain :count schemes
	 */
	public function the_export_should_contain_schemes( int $count ): void {
		$this->assert_export_available();

		$actual = count( $this->export_result['schemes'] );
		if ( $actual !== $count ) {
			throw new RuntimeException(
				sprintf( 'Expected %d schemes, got %d.', $count, $actual )
			);
		}
	}

	/**
	 * @Then export scheme :index should have name :name
	 */
	public function export_scheme_should_have_name( int $index, string $name ): void {
		$actual = $this->get_export_scheme( $index )['name'];
		if ( $actual !== $name ) {
			throw new RuntimeException(
				sprintf( 'Expected scheme[%d] name "%s", got "%s".', $index, $name, $actual )
			);
		}
	}

	/**
	 * @Then export scheme :index should be enabled
	 */
	public function export_scheme_should_be_enabled( int $index ): void {
		if ( true !== $this->get_export_scheme( $index )['enabled'] ) {
			throw new RuntimeException(
				sprintf( 'Expected scheme[%d] to be enabled.', $index )
			);
		}
	}

	/**
	 * @Then export scheme :index should be disabled
	 */
	public function export_scheme_should_be_disabled( int $index ): void {
		if ( false !== $this->get_export_scheme( $index )['enabled'] ) {
			throw new RuntimeException(
				sprintf( 'Expected scheme[%d] to be disabled.', $index )
			);
		}
	}

	/**
	 * @Then export scheme :index should have a :key object
	 */
	public function export_scheme_should_have_object( int $index, string $key ): void {
		$scheme = $this->get_export_scheme( $index );
		if ( ! isset( $scheme[ $key ] ) || ! is_array( $scheme[ $key ] ) ) {
			throw new RuntimeException(
				sprintf( 'Expected scheme[%d] to have a "%s" object.', $index, $key )
			);
		}
	}

	/**
	 * @Then export scheme :index forwarding_scheme key :key should be :value
	 */
	public function export_scheme_forwarding_key_should_be( int $index, string $key, string $value ): void {
		$fs = $this->get_forwarding_scheme( $index );

		$actual = $fs[ $key ];
		if ( $actual !== $value ) {
			throw new RuntimeException(
				sprintf( 'Expected scheme[%d].forwarding_scheme[%s] to be "%s", got "%s".', $index, $key, $value, (string) $actual )
			);
		}
	}

	/**
	 * @Then export scheme :index forwarding_scheme :key should be null
	 */
	public function export_scheme_forwarding_key_should_be_null( int $index, string $key ): void {
		$fs = $this->get_forwarding_scheme( $index );

		if ( null !== $fs[ $key ] ) {
			throw new RuntimeException(
				sprintf( 'Expected scheme[%d].forwarding_scheme[%s] to be null, got %s.', $index, $key, var_export( $fs[ $key ], true ) )
			);
		}
	}

	/**
	 * @Then export scheme :index forwarding_scheme should have proxies
	 */
	public function export_scheme_forwarding_should_have_proxies( int $index ): void {
		$fs = $this->get_forwarding_scheme( $index );

		if ( empty( $fs['proxies'] ) || ! is_array( $fs['proxies'] ) ) {
			throw new RuntimeException(
				sprintf( 'Expected scheme[%d].forwarding_scheme to have a non-empty proxies array.', $index )
			);
		}
	}

	/**
	 * @Then export scheme :index forwarding_scheme proxies should contain :value
	 */
	public function export_scheme_forwarding_proxies_should_contain( int $index, string $value ): void {
		$fs = $this->get_forwarding_scheme( $index );

		if ( ! in_array( $value, $fs['proxies'], true ) ) {
			throw new RuntimeException(
				sprintf( 'Expected scheme[%d].forwarding_scheme.proxies to contain "%s".', $index, $value )
			);
		}
	}

	// ------------------------------------------------------------------
	// Then steps — JSON output
	// ------------------------------------------------------------------

	/**
	 * @Then the JSON should be valid
	 */
	public function the_json_should_be_valid(): void {
		if ( null === $this->export_json ) {
			throw new RuntimeException( 'No JSON export available.' );
		}

		$decoded = json_decode( $this->export_json, true );
		if ( null === $decoded && JSON_ERROR_NONE !== json_last_error() ) {
			throw new RuntimeException(
				sprintf( 'Invalid JSON: %s', json_last_error_msg() )
			);
		}
	}

	/**
	 * @Then the JSON should be pretty-printed
	 */
	public function the_json_should_be_pretty_printed(): void {
		if ( null === $this->export_json ) {
			throw new RuntimeException( 'No JSON export available.' );
		}

		// Pretty-printed JSON contains newlines and indentation.
		if ( ! str_contains( $this->export_json, "\n" ) ) {
			throw new RuntimeException( 'Expected JSON to be pretty-printed (contain newlines).' );
		}

		if ( ! str_contains( $this->export_json, '    ' ) ) {
			throw new RuntimeException( 'Expected JSON to be pretty-printed (contain indentation).' );
		}
	}

	// ------------------------------------------------------------------
	// Then steps — filename
	// ------------------------------------------------------------------

	/**
	 * @Then the filename should be :expected
	 */
	public function the_filename_should_be( string $expected ): void {
		if ( null === $this->filename ) {
			throw new RuntimeException( 'No filename available.' );
		}

		if ( $this->filename !== $expected ) {
			throw new RuntimeException(
				sprintf( 'Expected filename "%s", got "%s".', $expected, $this->filename )
			);
		}
	}

	// ------------------------------------------------------------------
	// Helpers
	// ------------------------------------------------------------------

	private function assert_export_available(): void {
		if ( null === $this->export_result ) {
			throw new RuntimeException( 'Export result not available — run an export step first.' );
		}
	}

	/**
	 * @return array<string, mixed>
	 */
	private function get_export_scheme( int $index ): array {
		$this->assert_export_available();

		if ( ! isset( $this->export_result['schemes'][ $index ] ) ) {
			throw new RuntimeException(
				sprintf( 'Scheme index %d not found in export.', $index )
			);
		}

		return $this->export_result['schemes'][ $index ];
	}

	/**
	 * @return array<string, mixed>
	 */
	private function get_forwarding_scheme( int $index ): array {
		$scheme = $this->get_export_scheme( $index );

		if ( ! isset( $scheme['forwarding_scheme'] ) || ! is_array( $scheme['forwarding_scheme'] ) ) {
			throw new RuntimeException(
				sprintf( 'Scheme[%d] has no forwarding_scheme object.', $index )
			);
		}

		return $scheme['forwarding_scheme'];
	}
}
