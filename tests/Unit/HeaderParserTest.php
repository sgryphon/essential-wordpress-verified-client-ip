<?php

declare(strict_types=1);

namespace Gryphon\VerifiedClientIp\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Gryphon\VerifiedClientIp\HeaderParser;

/**
 * Tests for HeaderParser: RFC 7239, X-Forwarded-For, single-value, multi-header.
 */
final class HeaderParserTest extends TestCase {

	// ---------------------------------------------------------------
	// X-Forwarded-For (comma-separated, no token)
	// ---------------------------------------------------------------

	public function testXffSingleAddress(): void {
		$result = HeaderParser::parse( '203.0.113.50' );
		$this->assertSame( [ '203.0.113.50' ], $result );
	}

	public function testXffMultipleAddresses(): void {
		// Rightmost first.
		$result = HeaderParser::parse( '203.0.113.50, 70.41.3.18, 150.172.238.178' );
		$this->assertSame( [ '150.172.238.178', '70.41.3.18', '203.0.113.50' ], $result );
	}

	public function testXffWithSpaces(): void {
		$result = HeaderParser::parse( '  203.0.113.50 ,  70.41.3.18  ' );
		$this->assertSame( [ '70.41.3.18', '203.0.113.50' ], $result );
	}

	public function testXffEmpty(): void {
		$this->assertSame( [], HeaderParser::parse( '' ) );
	}

	public function testXffWhitespaceOnly(): void {
		$this->assertSame( [], HeaderParser::parse( '   ' ) );
	}

	public function testXffIpv6Addresses(): void {
		$result = HeaderParser::parse( '2001:db8::1, ::ffff:10.0.0.1' );
		$this->assertSame( [ '::ffff:10.0.0.1', '2001:db8::1' ], $result );
	}

	// ---------------------------------------------------------------
	// RFC 7239 Forwarded header (with token)
	// ---------------------------------------------------------------

	public function testForwardedSingleFor(): void {
		$result = HeaderParser::parse( 'for=192.0.2.1', 'for' );
		$this->assertSame( [ '192.0.2.1' ], $result );
	}

	public function testForwardedMultipleFor(): void {
		$result = HeaderParser::parse( 'for=192.0.2.1, for=198.51.100.1', 'for' );
		$this->assertSame( [ '198.51.100.1', '192.0.2.1' ], $result );
	}

	public function testForwardedWithOtherTokens(): void {
		$result = HeaderParser::parse( 'for=192.0.2.1;proto=https;by=10.0.0.1', 'for' );
		$this->assertSame( [ '192.0.2.1' ], $result );
	}

	public function testForwardedProtoExtraction(): void {
		$result = HeaderParser::parse( 'for=192.0.2.1;proto=https', 'proto' );
		$this->assertSame( [ 'https' ], $result );
	}

	public function testForwardedQuotedValue(): void {
		$result = HeaderParser::parse( 'for="192.0.2.1"', 'for' );
		$this->assertSame( [ '192.0.2.1' ], $result );
	}

	public function testForwardedQuotedIpv6(): void {
		$result = HeaderParser::parse( 'for="[2001:db8::1]"', 'for' );
		$this->assertSame( [ '[2001:db8::1]' ], $result );
	}

	public function testForwardedQuotedIpv6WithPort(): void {
		$result = HeaderParser::parse( 'for="[2001:db8::1]:443"', 'for' );
		$this->assertSame( [ '[2001:db8::1]:443' ], $result );
	}

	public function testForwardedCaseInsensitiveToken(): void {
		$result = HeaderParser::parse( 'For=192.0.2.1', 'for' );
		$this->assertSame( [ '192.0.2.1' ], $result );

		$result = HeaderParser::parse( 'FOR=192.0.2.1', 'for' );
		$this->assertSame( [ '192.0.2.1' ], $result );
	}

	public function testForwardedMultipleElements(): void {
		$header = 'for=192.0.2.1;proto=http, for=198.51.100.1;proto=https';
		$result = HeaderParser::parse( $header, 'for' );
		$this->assertSame( [ '198.51.100.1', '192.0.2.1' ], $result );
	}

	public function testForwardedEmpty(): void {
		$this->assertSame( [], HeaderParser::parse( '', 'for' ) );
	}

	public function testForwardedNoMatchingToken(): void {
		$result = HeaderParser::parse( 'by=10.0.0.1;proto=https', 'for' );
		$this->assertSame( [], $result );
	}

	public function testForwardedMalformedNoEquals(): void {
		$result = HeaderParser::parse( 'for192.0.2.1', 'for' );
		$this->assertSame( [], $result );
	}

	// ---------------------------------------------------------------
	// Single-value header (e.g. CF-Connecting-IP)
	// ---------------------------------------------------------------

	public function testSingleValueHeader(): void {
		$result = HeaderParser::parse( '203.0.113.50' );
		$this->assertSame( [ '203.0.113.50' ], $result );
	}

	public function testSingleValueIpv6(): void {
		$result = HeaderParser::parse( '2001:db8::1' );
		// IPv6 with colons but no commas should be returned as single address.
		$this->assertSame( [ '2001:db8::1' ], $result );
	}

	// ---------------------------------------------------------------
	// Multiple same-name headers (RFC 7230 §3.2.2)
	// ---------------------------------------------------------------

	public function testMultipleHeadersConcatenated(): void {
		$result = HeaderParser::parse( [ '203.0.113.50', '70.41.3.18' ] );
		// Concatenated as "203.0.113.50, 70.41.3.18", rightmost first.
		$this->assertSame( [ '70.41.3.18', '203.0.113.50' ], $result );
	}

	public function testMultipleForwardedHeadersConcatenated(): void {
		$result = HeaderParser::parse(
			[ 'for=192.0.2.1', 'for=198.51.100.1;proto=https' ],
			'for',
		);
		$this->assertSame( [ '198.51.100.1', '192.0.2.1' ], $result );
	}

	public function testMultipleHeadersWithExistingCommas(): void {
		$result = HeaderParser::parse( [ '203.0.113.50, 70.41.3.18', '150.172.238.178' ] );
		$this->assertSame( [ '150.172.238.178', '70.41.3.18', '203.0.113.50' ], $result );
	}

	public function testEmptyArrayInput(): void {
		$this->assertSame( [], HeaderParser::parse( [] ) );
	}

	// ---------------------------------------------------------------
	// Edge cases & malformed values
	// ---------------------------------------------------------------

	public function testMalformedValuesPreserved(): void {
		// Malformed values should be returned as-is — the resolver decides treatment.
		$result = HeaderParser::parse( 'unknown, 203.0.113.50' );
		$this->assertSame( [ '203.0.113.50', 'unknown' ], $result );
	}

	public function testEmptyEntriesSkipped(): void {
		$result = HeaderParser::parse( ',, 203.0.113.50,, ' );
		$this->assertSame( [ '203.0.113.50' ], $result );
	}

	public function testForwardedWithQuotedComma(): void {
		// A quoted value containing a comma should NOT be split.
		$result = HeaderParser::parse( 'for="192.0.2.1, spoofed"', 'for' );
		$this->assertSame( [ '192.0.2.1, spoofed' ], $result );
	}

	public function testForwardedEscapedQuote(): void {
		$result = HeaderParser::parse( 'for="test\\"value"', 'for' );
		$this->assertSame( [ 'test"value' ], $result );
	}

	public function testNullTokenFallsToCommaSeparated(): void {
		$result = HeaderParser::parse( '203.0.113.50, 10.0.0.1', null );
		$this->assertSame( [ '10.0.0.1', '203.0.113.50' ], $result );
	}

	public function testEmptyTokenFallsToCommaSeparated(): void {
		$result = HeaderParser::parse( '203.0.113.50, 10.0.0.1', '' );
		$this->assertSame( [ '10.0.0.1', '203.0.113.50' ], $result );
	}
}
