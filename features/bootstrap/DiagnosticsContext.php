<?php

declare(strict_types=1);

use Behat\Behat\Context\Context;
use Behat\Gherkin\Node\TableNode;
use Gryphon\VerifiedClientIp\Diagnostics;
use Gryphon\VerifiedClientIp\ResolverResult;
use Gryphon\VerifiedClientIp\ResolverStep;

require_once __DIR__ . '/wordpress-stubs.php';

final class DiagnosticsContext implements Context {

	/** @var array<string, mixed> */
	private array $state = [];

	/** @var array<string, string> */
	private array $server_vars = [];

	/**
	 * @BeforeScenario
	 */
	public function reset_globals(): void {
		$GLOBALS['_vcip_test_transients'] = [];
		$this->state                      = [];
		$this->server_vars                = [];
	}

	// ------------------------------------------------------------------
	// Helpers
	// ------------------------------------------------------------------

	/**
	 * @return array<string, string>
	 */
	private function make_server_vars(): array {
		if ( [] !== $this->server_vars ) {
			return $this->server_vars;
		}
		return [
			'REMOTE_ADDR'    => '127.0.0.1',
			'REQUEST_URI'    => '/',
			'REQUEST_METHOD' => 'GET',
			'HTTP_HOST'      => 'localhost',
		];
	}

	private function make_result(
		string $resolved_ip = '127.0.0.1',
		string $original_ip = '127.0.0.1',
		bool $changed = false,
	): ResolverResult {
		return new ResolverResult( $resolved_ip, $original_ip, $changed, [] );
	}

	// ------------------------------------------------------------------
	// Given steps
	// ------------------------------------------------------------------

	/**
	 * @Given diagnostics recording is started with count :count
	 */
	public function diagnostics_recording_is_started_with_count( int $count ): void {
		Diagnostics::start_recording( $count );
	}

	/**
	 * @Given a diagnostic entry is recorded
	 */
	public function a_diagnostic_entry_is_recorded(): void {
		Diagnostics::maybe_record( $this->make_server_vars(), $this->make_result() );
	}

	/**
	 * @Given the diagnostic server vars are:
	 */
	public function the_diagnostic_server_vars_are( TableNode $table ): void {
		foreach ( $table->getHash() as $row ) {
			$this->server_vars[ $row['key'] ] = $row['value'];
		}
	}

	/**
	 * @Given :count diagnostic entries are recorded
	 */
	public function n_diagnostic_entries_are_recorded( int $count ): void {
		for ( $i = 0; $i < $count; $i++ ) {
			Diagnostics::maybe_record( $this->make_server_vars(), $this->make_result() );
		}
	}

	// ------------------------------------------------------------------
	// When steps
	// ------------------------------------------------------------------

	/**
	 * @When diagnostics recording state is checked
	 */
	public function diagnostics_recording_state_is_checked(): void {
		// is_recording() is checked in the Then step.
	}

	/**
	 * @When the diagnostics state is retrieved
	 */
	public function the_diagnostics_state_is_retrieved(): void {
		$this->state = Diagnostics::get_state();
	}

	/**
	 * @When diagnostics recording is started with default count
	 */
	public function diagnostics_recording_is_started_with_default_count(): void {
		Diagnostics::start_recording();
	}

	/**
	 * @When diagnostics recording is stopped
	 */
	public function diagnostics_recording_is_stopped(): void {
		Diagnostics::stop_recording();
	}

	/**
	 * @When diagnostics is cleared
	 */
	public function diagnostics_is_cleared(): void {
		Diagnostics::clear();
	}

	/**
	 * @When a diagnostic recording is attempted
	 */
	public function a_diagnostic_recording_is_attempted(): void {
		Diagnostics::maybe_record( $this->make_server_vars(), $this->make_result() );
	}

	/**
	 * @When a diagnostic entry is recorded with result :resolved original :original changed true
	 */
	public function a_diagnostic_entry_is_recorded_with_result( string $resolved, string $original ): void {
		Diagnostics::maybe_record( $this->make_server_vars(), $this->make_result( $resolved, $original, true ) );
	}

	/**
	 * @When a diagnostic entry is recorded with null result
	 */
	public function a_diagnostic_entry_is_recorded_with_null_result(): void {
		Diagnostics::maybe_record( $this->make_server_vars(), null );
	}

	/**
	 * @When a diagnostic entry is recorded with a two-step trace
	 */
	public function a_diagnostic_entry_is_recorded_with_two_step_trace(): void {
		$steps  = [
			new ResolverStep( 1, '10.0.0.1', '10.0.0.1', 'X-Forwarded-For', 'HTTP_X_FORWARDED_FOR', 'trusted_proxy' ),
			new ResolverStep( 2, '203.0.113.50', '203.0.113.50', null, null, 'untrusted_stop' ),
		];
		$result = new ResolverResult( '203.0.113.50', '10.0.0.1', true, $steps );
		Diagnostics::maybe_record( $this->make_server_vars(), $result );
	}

	/**
	 * @When a diagnostic entry is recorded with proto :proto and host :host
	 */
	public function a_diagnostic_entry_is_recorded_with_proto_and_host( string $proto, string $host ): void {
		$result = new ResolverResult(
			'203.0.113.50',
			'10.0.0.1',
			true,
			[],
			[
				'proto' => $proto,
				'host'  => $host,
			],
		);
		Diagnostics::maybe_record( $this->make_server_vars(), $result );
	}

	// ------------------------------------------------------------------
	// Then steps — state management
	// ------------------------------------------------------------------

	/**
	 * @Then diagnostics should be recording
	 */
	public function diagnostics_should_be_recording(): void {
		if ( true !== Diagnostics::is_recording() ) {
			throw new RuntimeException( 'Expected diagnostics to be recording.' );
		}
	}

	/**
	 * @Then diagnostics should not be recording
	 */
	public function diagnostics_should_not_be_recording(): void {
		if ( false !== Diagnostics::is_recording() ) {
			throw new RuntimeException( 'Expected diagnostics not to be recording.' );
		}
	}

	/**
	 * @Then the diagnostics state recording should be false
	 */
	public function the_diagnostics_state_recording_should_be_false(): void {
		$state = Diagnostics::get_state();
		if ( false !== $state['recording'] ) {
			throw new RuntimeException( 'Expected state recording to be false.' );
		}
	}

	/**
	 * @Then the diagnostics state max_requests should be :value
	 */
	public function the_diagnostics_state_max_requests_should_be( int $value ): void {
		$state  = Diagnostics::get_state();
		$actual = $state['max_requests'];
		if ( $actual !== $value ) {
			throw new RuntimeException(
				sprintf( 'Expected max_requests %d, got %d.', $value, $actual )
			);
		}
	}

	/**
	 * @Then the diagnostics state started_at should be null
	 */
	public function the_diagnostics_state_started_at_should_be_null(): void {
		$state = Diagnostics::get_state();
		if ( null !== $state['started_at'] ) {
			throw new RuntimeException( 'Expected started_at to be null.' );
		}
	}

	/**
	 * @Then the diagnostics state started_at should not be null
	 */
	public function the_diagnostics_state_started_at_should_not_be_null(): void {
		$state = Diagnostics::get_state();
		if ( null === $state['started_at'] ) {
			throw new RuntimeException( 'Expected started_at to be non-null.' );
		}
	}

	/**
	 * @Then the diagnostics state stopped_at should be null
	 */
	public function the_diagnostics_state_stopped_at_should_be_null(): void {
		$state = Diagnostics::get_state();
		if ( null !== $state['stopped_at'] ) {
			throw new RuntimeException( 'Expected stopped_at to be null.' );
		}
	}

	/**
	 * @Then the diagnostics state stopped_at should not be null
	 */
	public function the_diagnostics_state_stopped_at_should_not_be_null(): void {
		$state = Diagnostics::get_state();
		if ( null === $state['stopped_at'] ) {
			throw new RuntimeException( 'Expected stopped_at to be non-null.' );
		}
	}

	/**
	 * @Then the diagnostics state max_requests should be the default request count
	 */
	public function the_diagnostics_state_max_requests_should_be_default(): void {
		$state  = Diagnostics::get_state();
		$actual = $state['max_requests'];
		if ( Diagnostics::DEFAULT_REQUEST_COUNT !== $actual ) {
			throw new RuntimeException(
				sprintf( 'Expected max_requests %d, got %d.', Diagnostics::DEFAULT_REQUEST_COUNT, $actual )
			);
		}
	}

	/**
	 * @Then the diagnostics state max_requests should be the max request count
	 */
	public function the_diagnostics_state_max_requests_should_be_max(): void {
		$state  = Diagnostics::get_state();
		$actual = $state['max_requests'];
		if ( Diagnostics::MAX_REQUEST_COUNT !== $actual ) {
			throw new RuntimeException(
				sprintf( 'Expected max_requests %d, got %d.', Diagnostics::MAX_REQUEST_COUNT, $actual )
			);
		}
	}

	/**
	 * @Then the diagnostics log should be empty
	 */
	public function the_diagnostics_log_should_be_empty(): void {
		$log = Diagnostics::get_log();
		if ( [] !== $log ) {
			throw new RuntimeException(
				sprintf( 'Expected empty log, got %d entries.', count( $log ) )
			);
		}
	}

	// ------------------------------------------------------------------
	// Then steps — recording
	// ------------------------------------------------------------------

	/**
	 * @Then the diagnostics log should have :count entry
	 * @Then the diagnostics log should have :count entries
	 */
	public function the_diagnostics_log_should_have_entries( int $count ): void {
		$actual = count( Diagnostics::get_log() );
		if ( $actual !== $count ) {
			throw new RuntimeException(
				sprintf( 'Expected %d log entries, got %d.', $count, $actual )
			);
		}
	}

	/**
	 * @Then the diagnostics log entry :index should have key :key
	 */
	public function the_diagnostics_log_entry_should_have_key( int $index, string $key ): void {
		$entry = Diagnostics::get_log()[ $index ];
		if ( ! array_key_exists( $key, $entry ) ) {
			throw new RuntimeException(
				sprintf( 'Expected log entry %d to have key "%s".', $index, $key )
			);
		}
	}

	/**
	 * @Then the diagnostics log entry :index should not have key :key
	 */
	public function the_diagnostics_log_entry_should_not_have_key( int $index, string $key ): void {
		$entry = Diagnostics::get_log()[ $index ];
		if ( array_key_exists( $key, $entry ) ) {
			throw new RuntimeException(
				sprintf( 'Expected log entry %d not to have key "%s".', $index, $key )
			);
		}
	}

	/**
	 * @Then the diagnostics log entry :index field :field should be :value
	 */
	public function the_diagnostics_log_entry_field_should_be( int $index, string $field, string $value ): void {
		$actual = (string) Diagnostics::get_log()[ $index ][ $field ];
		if ( $actual !== $value ) {
			throw new RuntimeException(
				sprintf( 'Expected entry %d field "%s" to be "%s", got "%s".', $index, $field, $value, $actual )
			);
		}
	}

	/**
	 * @Then the diagnostics log entry :index changed field should be true
	 */
	public function the_diagnostics_log_entry_changed_should_be_true( int $index ): void {
		$entry = Diagnostics::get_log()[ $index ];
		if ( true !== $entry['changed'] ) {
			throw new RuntimeException(
				sprintf( 'Expected entry %d field "changed" to be true.', $index )
			);
		}
	}

	/**
	 * @Then the diagnostics log entry :index header :header should be :value
	 */
	public function the_diagnostics_log_entry_header_should_be( int $index, string $header, string $value ): void {
		$entry  = Diagnostics::get_log()[ $index ];
		$actual = $entry['headers'][ $header ] ?? null;
		if ( $actual !== $value ) {
			throw new RuntimeException(
				sprintf( 'Expected entry %d header "%s" to be "%s", got "%s".', $index, $header, $value, (string) $actual )
			);
		}
	}

	/**
	 * @Then the diagnostics log entry :index should have :count steps
	 */
	public function the_diagnostics_log_entry_should_have_steps( int $index, int $count ): void {
		$entry  = Diagnostics::get_log()[ $index ];
		$actual = count( $entry['steps'] );
		if ( $actual !== $count ) {
			throw new RuntimeException(
				sprintf( 'Expected entry %d to have %d steps, got %d.', $index, $count, $actual )
			);
		}
	}

	/**
	 * @Then the diagnostics log entry :index step :step_index field :field should be :value
	 */
	public function the_diagnostics_log_entry_step_field_should_be_string( int $index, int $step_index, string $field, string $value ): void {
		$entry  = Diagnostics::get_log()[ $index ];
		$actual = $entry['steps'][ $step_index ][ $field ];
		if ( is_int( $actual ) ) {
			$value = (int) $value;
		}
		if ( $actual !== $value ) {
			throw new RuntimeException(
				sprintf(
					'Expected entry %d step %d field "%s" to be "%s", got "%s".',
					$index,
					$step_index,
					$field,
					(string) $value,
					(string) $actual
				)
			);
		}
	}

	/**
	 * @Then the diagnostics log entry :index proto field :field should be :value
	 */
	public function the_diagnostics_log_entry_proto_field_should_be( int $index, string $field, string $value ): void {
		$entry  = Diagnostics::get_log()[ $index ];
		$actual = $entry['proto'][ $field ] ?? null;
		if ( $actual !== $value ) {
			throw new RuntimeException(
				sprintf( 'Expected entry %d proto field "%s" to be "%s", got "%s".', $index, $field, $value, (string) $actual )
			);
		}
	}

	/**
	 * @Then the diagnostics DEFAULT_REQUEST_COUNT should be :value
	 */
	public function the_diagnostics_default_request_count_should_be( int $value ): void {
		if ( Diagnostics::DEFAULT_REQUEST_COUNT !== $value ) {
			throw new RuntimeException(
				sprintf( 'Expected DEFAULT_REQUEST_COUNT %d, got %d.', $value, Diagnostics::DEFAULT_REQUEST_COUNT )
			);
		}
	}

	/**
	 * @Then the diagnostics MAX_REQUEST_COUNT should be :value
	 */
	public function the_diagnostics_max_request_count_should_be( int $value ): void {
		if ( Diagnostics::MAX_REQUEST_COUNT !== $value ) {
			throw new RuntimeException(
				sprintf( 'Expected MAX_REQUEST_COUNT %d, got %d.', $value, Diagnostics::MAX_REQUEST_COUNT )
			);
		}
	}

	/**
	 * @Then the diagnostics EXPIRY_SECONDS should be :value
	 */
	public function the_diagnostics_expiry_seconds_should_be( int $value ): void {
		if ( Diagnostics::EXPIRY_SECONDS !== $value ) {
			throw new RuntimeException(
				sprintf( 'Expected EXPIRY_SECONDS %d, got %d.', $value, Diagnostics::EXPIRY_SECONDS )
			);
		}
	}
}
