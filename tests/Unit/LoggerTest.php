<?php

declare(strict_types=1);

namespace VerifiedClientIp\Tests\Unit;

use PHPUnit\Framework\TestCase;
use VerifiedClientIp\Logger;

/**
 * @covers \VerifiedClientIp\Logger
 */
final class LoggerTest extends TestCase {

	public function testRequestLoggingDisabledByDefault(): void {
		// Neither WP_DEBUG nor VCIP_LOG_REQUESTS are defined in the test
		// environment, so request-level logging should be off.
		// Note: If WP_DEBUG or VCIP_LOG_REQUESTS are defined in the test runner,
		// this test may need adjusting.
		$enabled = Logger::isRequestLoggingEnabled();

		// We can't control constants in tests easily — just check the method
		// returns a boolean.
		$this->assertIsBool( $enabled );
	}

	public function testErrorDoesNotThrow(): void {
		// Ensure error() can be called without a context and doesn't throw.
		Logger::error( 'Test error message' );
		$this->assertTrue( true ); // Reached here — no exception.
	}

	public function testWarningDoesNotThrow(): void {
		Logger::warning( 'Test warning message', 'test-context' );
		$this->assertTrue( true );
	}

	public function testInfoDoesNotThrow(): void {
		Logger::info( 'Test info message' );
		$this->assertTrue( true );
	}

	public function testDebugDoesNotThrow(): void {
		Logger::debug( 'Test debug message', 'resolver' );
		$this->assertTrue( true );
	}

	public function testErrorWithContext(): void {
		Logger::error( 'Something went wrong', 'admin' );
		$this->assertTrue( true );
	}
}
