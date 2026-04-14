<?php

declare(strict_types=1);

use Behat\Behat\Context\Context;
use Gryphon\VerifiedClientIp\Diagnostics;
use Gryphon\VerifiedClientIp\ResolverResult;
use Gryphon\VerifiedClientIp\ResolverStep;

require_once __DIR__ . '/../../tests/Integration/bootstrap.php';

final class DiagnosticsContext implements Context {

	/** @var array<string, mixed> */
	private array $state = [];

	/**
	 * @BeforeScenario
	 */
	public function reset_globals(): void {
		$GLOBALS['_vcip_test_transients'] = [];
		$this->state                      = [];
	}

	// ------------------------------------------------------------------
	// Helpers
	// ------------------------------------------------------------------

	/**
	 * @return array<string, string>
	 */
	private function make_server_vars(): array {
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
		if ( $actual !== Diagnostics::DEFAULT_REQUEST_COUNT ) {
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
		if ( $actual !== Diagnostics::MAX_REQUEST_COUNT ) {
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
}
