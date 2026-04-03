<?php

declare(strict_types=1);

namespace Essential\VerifiedClientIp\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Essential\VerifiedClientIp\Diagnostics;
use Essential\VerifiedClientIp\ResolverResult;
use Essential\VerifiedClientIp\ResolverStep;

/**
 * @covers \Essential\VerifiedClientIp\Diagnostics
 */
final class DiagnosticsTest extends TestCase {

	protected function setUp(): void {
		// Reset transient storage before each test.
		$GLOBALS['_vcip_test_transients'] = [];
	}

	// ------------------------------------------------------------------
	// State management
	// ------------------------------------------------------------------

	public function testNotRecordingByDefault(): void {
		$this->assertFalse( Diagnostics::is_recording() );
	}

	public function testDefaultState(): void {
		$state = Diagnostics::get_state();

		$this->assertFalse( $state['recording'] );
		$this->assertSame( 10, $state['max_requests'] );
		$this->assertNull( $state['started_at'] );
		$this->assertNull( $state['stopped_at'] );
	}

	public function testStartRecording(): void {
		Diagnostics::start_recording( 5 );

		$this->assertTrue( Diagnostics::is_recording() );

		$state = Diagnostics::get_state();
		$this->assertTrue( $state['recording'] );
		$this->assertSame( 5, $state['max_requests'] );
		$this->assertNotNull( $state['started_at'] );
		$this->assertNull( $state['stopped_at'] );
	}

	public function testStartRecordingDefaultCount(): void {
		Diagnostics::start_recording();

		$state = Diagnostics::get_state();
		$this->assertSame( Diagnostics::DEFAULT_REQUEST_COUNT, $state['max_requests'] );
	}

	public function testStopRecording(): void {
		Diagnostics::start_recording( 5 );
		Diagnostics::stop_recording();

		$this->assertFalse( Diagnostics::is_recording() );

		$state = Diagnostics::get_state();
		$this->assertFalse( $state['recording'] );
		$this->assertNotNull( $state['stopped_at'] );
	}

	public function testClear(): void {
		Diagnostics::start_recording( 3 );

		// Record an entry.
		$server = $this->makeServerVars();
		$result = $this->makeResult();
		Diagnostics::maybe_record( $server, $result );

		$this->assertCount( 1, Diagnostics::get_log() );

		Diagnostics::clear();

		$this->assertFalse( Diagnostics::is_recording() );
		$this->assertCount( 0, Diagnostics::get_log() );
	}

	// ------------------------------------------------------------------
	// Max request clamping
	// ------------------------------------------------------------------

	public function testStartRecordingClampsLow(): void {
		Diagnostics::start_recording( 0 );

		$state = Diagnostics::get_state();
		$this->assertSame( 1, $state['max_requests'] );
	}

	public function testStartRecordingClampsHigh(): void {
		Diagnostics::start_recording( 999 );

		$state = Diagnostics::get_state();
		$this->assertSame( Diagnostics::MAX_REQUEST_COUNT, $state['max_requests'] );
	}

	// ------------------------------------------------------------------
	// Recording
	// ------------------------------------------------------------------

	public function testMaybeRecordDoesNothingWhenNotRecording(): void {
		// Not recording — should do nothing.
		Diagnostics::maybe_record( $this->makeServerVars(), $this->makeResult() );

		$this->assertCount( 0, Diagnostics::get_log() );
	}

	public function testMaybeRecordRecordsEntry(): void {
		Diagnostics::start_recording( 5 );

		Diagnostics::maybe_record( $this->makeServerVars(), $this->makeResult() );

		$log = Diagnostics::get_log();
		$this->assertCount( 1, $log );

		$entry = $log[0];
		$this->assertArrayHasKey( 'timestamp', $entry );
		$this->assertArrayHasKey( 'request_uri', $entry );
		$this->assertArrayHasKey( 'method', $entry );
		$this->assertArrayHasKey( 'remote_addr', $entry );
		$this->assertArrayHasKey( 'headers', $entry );
		$this->assertArrayHasKey( 'resolved_ip', $entry );
		$this->assertArrayHasKey( 'original_ip', $entry );
		$this->assertArrayHasKey( 'changed', $entry );
		$this->assertArrayHasKey( 'steps', $entry );
	}

	public function testRecordedEntryContainsServerData(): void {
		Diagnostics::start_recording( 3 );

		$server = [
			'REMOTE_ADDR'          => '10.0.0.1',
			'REQUEST_URI'          => '/test-page',
			'REQUEST_METHOD'       => 'POST',
			'HTTP_HOST'            => 'example.com',
			'HTTP_X_FORWARDED_FOR' => '203.0.113.50',
			'SERVER_PORT'          => '443',
		];

		Diagnostics::maybe_record( $server, $this->makeResult( '203.0.113.50', '10.0.0.1', true ) );

		$entry = Diagnostics::get_log()[0];
		$this->assertSame( '/test-page', $entry['request_uri'] );
		$this->assertSame( 'POST', $entry['method'] );
		$this->assertSame( '10.0.0.1', $entry['remote_addr'] );
		$this->assertSame( '203.0.113.50', $entry['resolved_ip'] );
		$this->assertSame( '10.0.0.1', $entry['original_ip'] );
		$this->assertTrue( $entry['changed'] );

		// Headers should include HTTP_* and known keys.
		$this->assertSame( 'example.com', $entry['headers']['HTTP_HOST'] );
		$this->assertSame( '203.0.113.50', $entry['headers']['HTTP_X_FORWARDED_FOR'] );
	}

	public function testAutoStopsAtLimit(): void {
		Diagnostics::start_recording( 3 );

		for ( $i = 0; $i < 5; $i++ ) {
			Diagnostics::maybe_record( $this->makeServerVars(), $this->makeResult() );
		}

		$log = Diagnostics::get_log();
		$this->assertCount( 3, $log );
		$this->assertFalse( Diagnostics::is_recording() );
	}

	public function testRecordsMultipleEntries(): void {
		Diagnostics::start_recording( 10 );

		for ( $i = 0; $i < 4; $i++ ) {
			Diagnostics::maybe_record( $this->makeServerVars(), $this->makeResult() );
		}

		$this->assertCount( 4, Diagnostics::get_log() );
		$this->assertTrue( Diagnostics::is_recording() );
	}

	public function testStartRecordingClearsPreviousLog(): void {
		Diagnostics::start_recording( 10 );
		Diagnostics::maybe_record( $this->makeServerVars(), $this->makeResult() );
		Diagnostics::maybe_record( $this->makeServerVars(), $this->makeResult() );
		$this->assertCount( 2, Diagnostics::get_log() );

		// Start again — should clear.
		Diagnostics::start_recording( 5 );
		$this->assertCount( 0, Diagnostics::get_log() );
		$this->assertTrue( Diagnostics::is_recording() );
	}

	public function testMaybeRecordWithNullResult(): void {
		Diagnostics::start_recording( 3 );

		Diagnostics::maybe_record( $this->makeServerVars(), null );

		$entry = Diagnostics::get_log()[0];
		$this->assertArrayHasKey( 'timestamp', $entry );
		$this->assertArrayHasKey( 'remote_addr', $entry );
		$this->assertArrayNotHasKey( 'resolved_ip', $entry );
		$this->assertArrayNotHasKey( 'steps', $entry );
	}

	public function testStopRecordingPreservesData(): void {
		Diagnostics::start_recording( 10 );
		Diagnostics::maybe_record( $this->makeServerVars(), $this->makeResult() );
		Diagnostics::maybe_record( $this->makeServerVars(), $this->makeResult() );

		Diagnostics::stop_recording();

		// Data should still be there.
		$this->assertCount( 2, Diagnostics::get_log() );
	}

	public function testEntryContainsStepTrace(): void {
		Diagnostics::start_recording( 3 );

		$steps = [
			new ResolverStep( 1, '10.0.0.1', '10.0.0.1', 'X-Forwarded-For', 'HTTP_X_FORWARDED_FOR', 'trusted_proxy' ),
			new ResolverStep( 2, '203.0.113.50', '203.0.113.50', null, null, 'untrusted_stop' ),
		];

		$result = new ResolverResult( '203.0.113.50', '10.0.0.1', true, $steps );
		Diagnostics::maybe_record( $this->makeServerVars(), $result );

		$entry = Diagnostics::get_log()[0];
		$this->assertCount( 2, $entry['steps'] );
		$this->assertSame( 1, $entry['steps'][0]['step'] );
		$this->assertSame( '10.0.0.1', $entry['steps'][0]['address'] );
		$this->assertSame( 'trusted_proxy', $entry['steps'][0]['action'] );
	}

	public function testEntryContainsProtoInfo(): void {
		Diagnostics::start_recording( 3 );

		$result = new ResolverResult(
			'203.0.113.50',
			'10.0.0.1',
			true,
			[],
			[
				'proto' => 'https',
				'host'  => 'example.com',
			],
		);

		Diagnostics::maybe_record( $this->makeServerVars(), $result );

		$entry = Diagnostics::get_log()[0];
		$this->assertSame(
			[
				'proto' => 'https',
				'host'  => 'example.com',
			],
			$entry['proto']
		);
	}

	// ------------------------------------------------------------------
	// Constants
	// ------------------------------------------------------------------

	public function testConstants(): void {
		$this->assertSame( 10, Diagnostics::DEFAULT_REQUEST_COUNT );
		$this->assertSame( 100, Diagnostics::MAX_REQUEST_COUNT );
		$this->assertSame( 86400, Diagnostics::EXPIRY_SECONDS );
	}

	// ------------------------------------------------------------------
	// Helpers
	// ------------------------------------------------------------------

	/**
	 * @return array<string, string>
	 */
	private function makeServerVars(): array {
		return [
			'REMOTE_ADDR'    => '127.0.0.1',
			'REQUEST_URI'    => '/',
			'REQUEST_METHOD' => 'GET',
			'HTTP_HOST'      => 'localhost',
		];
	}

	private function makeResult(
		string $resolved_ip = '127.0.0.1',
		string $original_ip = '127.0.0.1',
		bool $changed = false,
	): ResolverResult {
		return new ResolverResult( $resolved_ip, $original_ip, $changed, [] );
	}
}
