<?php

declare(strict_types=1);

use Behat\Behat\Context\Context;
use Behat\Gherkin\Node\TableNode;
use Gryphon\VerifiedClientIp\AdminPage;
use Gryphon\VerifiedClientIp\Settings;

require_once __DIR__ . '/wordpress-stubs.php';

final class AdminPageContext implements Context {

	/** @var array<string, mixed> */
	private array $post = [];

	/** @var array<string, mixed> */
	private array $parsed = [];

	/** @var array{errors: list<string>, settings: Settings}|null */
	private ?array $validation_result = null;

	/**
	 * @BeforeScenario
	 */
	public function reset_globals(): void {
		$GLOBALS['_vcip_test_options']         = [];
		$GLOBALS['_vcip_test_filters']         = [];
		$GLOBALS['_vcip_test_actions']         = [];
		$GLOBALS['_vcip_test_settings_errors'] = [];
		$this->post                            = [];
		$this->parsed                          = [];
		$this->validation_result               = null;
		$this->existing_links                  = [];
		$this->action_links                    = [];
	}

	// ------------------------------------------------------------------
	// Given steps
	// ------------------------------------------------------------------

	/**
	 * @Given the admin form POST contains:
	 */
	public function the_admin_form_post_contains( TableNode $table ): void {
		foreach ( $table->getHash() as $row ) {
			$this->post[ $row['field'] ] = $row['value'];
		}
	}

	/**
	 * @Given the admin form POST contains schemes:
	 */
	public function the_admin_form_post_contains_schemes( TableNode $table ): void {
		$schemes = [];
		foreach ( $table->getHash() as $row ) {
			$scheme = [];
			foreach ( $row as $key => $value ) {
				if ( 'proxies' === $key ) {
					$value = str_replace( '\n', "\n", $value );
				}
				$scheme[ $key ] = $value;
			}
			$schemes[] = $scheme;
		}
		$this->post['vcip_schemes'] = $schemes;
	}

	// ------------------------------------------------------------------
	// When steps
	// ------------------------------------------------------------------

	/**
	 * @When the admin form input is parsed
	 */
	public function the_admin_form_input_is_parsed(): void {
		$this->parsed = AdminPage::parse_form_input( $this->post );
	}

	/**
	 * @When the admin form input is parsed and validated
	 */
	public function the_admin_form_input_is_parsed_and_validated(): void {
		$this->parsed            = AdminPage::parse_form_input( $this->post );
		$this->validation_result = Settings::validate( $this->parsed );
	}

	// ------------------------------------------------------------------
	// Then steps — form parsing
	// ------------------------------------------------------------------

	/**
	 * @Then the parsed boolean :key should be true
	 */
	public function the_parsed_boolean_should_be_true( string $key ): void {
		if ( true !== $this->parsed[ $key ] ) {
			throw new RuntimeException(
				sprintf( 'Expected parsed[%s] to be true, got %s.', $key, var_export( $this->parsed[ $key ], true ) )
			);
		}
	}

	/**
	 * @Then the parsed boolean :key should be false
	 */
	public function the_parsed_boolean_should_be_false( string $key ): void {
		if ( false !== $this->parsed[ $key ] ) {
			throw new RuntimeException(
				sprintf( 'Expected parsed[%s] to be false, got %s.', $key, var_export( $this->parsed[ $key ], true ) )
			);
		}
	}

	/**
	 * @Then the parsed setting :key should be :value
	 */
	public function the_parsed_setting_should_be( string $key, string $value ): void {
		$actual = (string) $this->parsed[ $key ];
		if ( $actual !== $value ) {
			throw new RuntimeException(
				sprintf( 'Expected parsed[%s] to be "%s", got "%s".', $key, $value, $actual )
			);
		}
	}

	/**
	 * @Then the parsed result should contain :count schemes
	 */
	public function the_parsed_result_should_contain_schemes( int $count ): void {
		$actual = count( $this->parsed['schemes'] );
		if ( $actual !== $count ) {
			throw new RuntimeException(
				sprintf( 'Expected %d schemes, got %d.', $count, $actual )
			);
		}
	}

	/**
	 * @Then parsed scheme :index should have name :name
	 */
	public function parsed_scheme_should_have_name( int $index, string $name ): void {
		$actual = $this->parsed['schemes'][ $index ]['name'];
		if ( $actual !== $name ) {
			throw new RuntimeException(
				sprintf( 'Expected scheme[%d] name "%s", got "%s".', $index, $name, $actual )
			);
		}
	}

	/**
	 * @Then parsed scheme :index should be enabled
	 */
	public function parsed_scheme_should_be_enabled( int $index ): void {
		if ( true !== $this->parsed['schemes'][ $index ]['enabled'] ) {
			throw new RuntimeException(
				sprintf( 'Expected scheme[%d] to be enabled.', $index )
			);
		}
	}

	/**
	 * @Then parsed scheme :index should be disabled
	 */
	public function parsed_scheme_should_be_disabled( int $index ): void {
		if ( false !== $this->parsed['schemes'][ $index ]['enabled'] ) {
			throw new RuntimeException(
				sprintf( 'Expected scheme[%d] to be disabled.', $index )
			);
		}
	}

	/**
	 * @Then parsed scheme :index should have header :header
	 */
	public function parsed_scheme_should_have_header( int $index, string $header ): void {
		$actual = $this->parsed['schemes'][ $index ]['header'];
		if ( $actual !== $header ) {
			throw new RuntimeException(
				sprintf( 'Expected scheme[%d] header "%s", got "%s".', $index, $header, $actual )
			);
		}
	}

	/**
	 * @Then parsed scheme :index should have token :token
	 */
	public function parsed_scheme_should_have_token( int $index, string $token ): void {
		$actual = $this->parsed['schemes'][ $index ]['token'];
		if ( $actual !== $token ) {
			throw new RuntimeException(
				sprintf( 'Expected scheme[%d] token "%s", got "%s".', $index, $token, $actual )
			);
		}
	}

	/**
	 * @Then parsed scheme :index should have :count proxies
	 */
	public function parsed_scheme_should_have_proxies( int $index, int $count ): void {
		$actual = count( $this->parsed['schemes'][ $index ]['proxies'] );
		if ( $actual !== $count ) {
			throw new RuntimeException(
				sprintf( 'Expected scheme[%d] to have %d proxies, got %d.', $index, $count, $actual )
			);
		}
	}

	/**
	 * @Then parsed scheme :index proxy :proxy_index should be :value
	 */
	public function parsed_scheme_proxy_should_be( int $index, int $proxy_index, string $value ): void {
		$actual = $this->parsed['schemes'][ $index ]['proxies'][ $proxy_index ];
		if ( $actual !== $value ) {
			throw new RuntimeException(
				sprintf( 'Expected scheme[%d] proxy[%d] "%s", got "%s".', $index, $proxy_index, $value, $actual )
			);
		}
	}

	/**
	 * @Then the parsed result should not contain a :key key
	 */
	public function the_parsed_result_should_not_contain_key( string $key ): void {
		if ( array_key_exists( $key, $this->parsed ) ) {
			throw new RuntimeException(
				sprintf( 'Expected parsed result not to contain key "%s".', $key )
			);
		}
	}

	// ------------------------------------------------------------------
	// Then steps — validation
	// ------------------------------------------------------------------

	/**
	 * @Then the validation result should have no errors
	 */
	public function the_validation_result_should_have_no_errors(): void {
		if ( null === $this->validation_result ) {
			throw new RuntimeException( 'Validation result is null.' );
		}
		if ( [] !== $this->validation_result['errors'] ) {
			throw new RuntimeException(
				sprintf( 'Expected no errors, got: %s', implode( ', ', $this->validation_result['errors'] ) )
			);
		}
	}

	/**
	 * @Then the validation result should have errors
	 */
	public function the_validation_result_should_have_errors(): void {
		if ( null === $this->validation_result ) {
			throw new RuntimeException( 'Validation result is null.' );
		}
		if ( [] === $this->validation_result['errors'] ) {
			throw new RuntimeException( 'Expected errors but got none.' );
		}
	}

	/**
	 * @Then the validated settings enabled should be true
	 */
	public function the_validated_settings_enabled_should_be_true(): void {
		if ( true !== $this->validation_result['settings']->enabled ) {
			throw new RuntimeException( 'Expected validated settings enabled to be true.' );
		}
	}

	/**
	 * @Then the validated settings forward_limit should be :value
	 */
	public function the_validated_settings_forward_limit_should_be( int $value ): void {
		$actual = $this->validation_result['settings']->forward_limit;
		if ( $actual !== $value ) {
			throw new RuntimeException(
				sprintf( 'Expected forward_limit %d, got %d.', $value, $actual )
			);
		}
	}

	/**
	 * @Then the validated settings process_proto should be true
	 */
	public function the_validated_settings_process_proto_should_be_true(): void {
		if ( true !== $this->validation_result['settings']->process_proto ) {
			throw new RuntimeException( 'Expected validated settings process_proto to be true.' );
		}
	}

	/**
	 * @Then the validated settings process_host should be true
	 */
	public function the_validated_settings_process_host_should_be_true(): void {
		if ( true !== $this->validation_result['settings']->process_host ) {
			throw new RuntimeException( 'Expected validated settings process_host to be true.' );
		}
	}

	/**
	 * @Then the validated settings should have :count scheme
	 */
	public function the_validated_settings_should_have_schemes( int $count ): void {
		$actual = count( $this->validation_result['settings']->schemes );
		if ( $actual !== $count ) {
			throw new RuntimeException(
				sprintf( 'Expected %d validated schemes, got %d.', $count, $actual )
			);
		}
	}

	/**
	 * @Then the validated scheme :index should have name :name
	 */
	public function the_validated_scheme_should_have_name( int $index, string $name ): void {
		$actual = $this->validation_result['settings']->schemes[ $index ]->name;
		if ( $actual !== $name ) {
			throw new RuntimeException(
				sprintf( 'Expected validated scheme[%d] name "%s", got "%s".', $index, $name, $actual )
			);
		}
	}

	/**
	 * @Then the validated scheme :index should have header :header
	 */
	public function the_validated_scheme_should_have_header( int $index, string $header ): void {
		$actual = $this->validation_result['settings']->schemes[ $index ]->header;
		if ( $actual !== $header ) {
			throw new RuntimeException(
				sprintf( 'Expected validated scheme[%d] header "%s", got "%s".', $index, $header, $actual )
			);
		}
	}

	/**
	 * @Then the validated scheme :index should have a null token
	 */
	public function the_validated_scheme_should_have_null_token( int $index ): void {
		if ( null !== $this->validation_result['settings']->schemes[ $index ]->token ) {
			throw new RuntimeException(
				sprintf( 'Expected validated scheme[%d] token to be null.', $index )
			);
		}
	}

	/**
	 * @Then the validated scheme :index should have :count proxies
	 */
	public function the_validated_scheme_should_have_proxies( int $index, int $count ): void {
		$actual = count( $this->validation_result['settings']->schemes[ $index ]->proxies );
		if ( $actual !== $count ) {
			throw new RuntimeException(
				sprintf( 'Expected validated scheme[%d] to have %d proxies, got %d.', $index, $count, $actual )
			);
		}
	}

	// ------------------------------------------------------------------
	// Action links
	// ------------------------------------------------------------------

	/** @var array<string, string> */
	private array $existing_links = [];

	/** @var array<string, string> */
	private array $action_links = [];

	/**
	 * @Given the existing plugin action links are:
	 */
	public function the_existing_plugin_action_links_are( TableNode $table ): void {
		foreach ( $table->getHash() as $row ) {
			$this->existing_links[ $row['key'] ] = $row['html'];
		}
	}

	/**
	 * @When the action links are generated
	 */
	public function the_action_links_are_generated(): void {
		$this->action_links = AdminPage::add_action_links( $this->existing_links );
	}

	/**
	 * @Then the action links should contain :count entries
	 */
	public function the_action_links_should_contain_entries( int $count ): void {
		$actual = count( $this->action_links );
		if ( $actual !== $count ) {
			throw new RuntimeException(
				sprintf( 'Expected %d action links, got %d.', $count, $actual )
			);
		}
	}

	/**
	 * @Then the action link keys should be :keys
	 */
	public function the_action_link_keys_should_be( string $keys ): void {
		$expected = array_map( 'trim', explode( ',', $keys ) );
		$actual   = array_keys( $this->action_links );
		if ( $expected !== $actual ) {
			throw new RuntimeException(
				sprintf( 'Expected keys [%s], got [%s].', implode( ', ', $expected ), implode( ', ', $actual ) )
			);
		}
	}

	/**
	 * @Then the action link :key should contain :text
	 */
	public function the_action_link_should_contain( string $key, string $text ): void {
		if ( ! isset( $this->action_links[ $key ] ) ) {
			throw new RuntimeException( sprintf( 'Action link "%s" not found.', $key ) );
		}
		if ( false === strpos( $this->action_links[ $key ], $text ) ) {
			throw new RuntimeException(
				sprintf( 'Action link "%s" does not contain "%s". Got: %s', $key, $text, $this->action_links[ $key ] )
			);
		}
	}
}
