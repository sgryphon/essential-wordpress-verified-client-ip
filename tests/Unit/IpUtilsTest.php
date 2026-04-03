<?php

declare(strict_types=1);

namespace Essential\VerifiedClientIp\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Essential\VerifiedClientIp\IpUtils;

/**
 * Tests for IpUtils: CIDR matching, normalisation, port stripping, validation.
 */
final class IpUtilsTest extends TestCase {

	// ---------------------------------------------------------------
	// normalise()
	// ---------------------------------------------------------------

	#[DataProvider( 'normaliseProvider' )]
	public function testNormalise( string $input, ?string $expected ): void {
		$this->assertSame( $expected, IpUtils::normalise( $input ) );
	}

	/**
	 * @return array<string, array{string, ?string}>
	 */
	public static function normaliseProvider(): array {
		return [
			'ipv4 plain'                 => [ '192.168.1.1', '192.168.1.1' ],
			'ipv4 with port'             => [ '192.168.1.1:8080', '192.168.1.1' ],
			'ipv6 plain'                 => [ '::1', '::1' ],
			'ipv6 full'                  => [ '2001:0db8:0000:0000:0000:0000:0000:0001', '2001:db8::1' ],
			'ipv6 bracketed'             => [ '[::1]', '::1' ],
			'ipv6 bracketed with port'   => [ '[2001:db8::1]:443', '2001:db8::1' ],
			'ipv4-mapped ipv6'           => [ '::ffff:192.168.1.1', '192.168.1.1' ],
			'ipv4-mapped ipv6 hex'       => [ '::ffff:c0a8:0101', '192.168.1.1' ],
			'ipv4-mapped ipv6 bracketed' => [ '[::ffff:10.0.0.1]:80', '10.0.0.1' ],
			'localhost v4'               => [ '127.0.0.1', '127.0.0.1' ],
			'localhost v6'               => [ '::1', '::1' ],
			'empty string'               => [ '', null ],
			'whitespace only'            => [ '   ', null ],
			'garbage'                    => [ 'not-an-ip', null ],
			'hostname'                   => [ 'example.com', null ],
			'unknown token'              => [ 'unknown', null ],
			'_hidden token'              => [ '_hidden', null ],
		];
	}

	// ---------------------------------------------------------------
	// strip_port()
	// ---------------------------------------------------------------

	#[DataProvider( 'stripPortProvider' )]
	public function testStripPort( string $input, string $expected ): void {
		$this->assertSame( $expected, IpUtils::strip_port( $input ) );
	}

	/**
	 * @return array<string, array{string, string}>
	 */
	public static function stripPortProvider(): array {
		return [
			'ipv4 no port'             => [ '10.0.0.1', '10.0.0.1' ],
			'ipv4 with port'           => [ '10.0.0.1:3128', '10.0.0.1' ],
			'ipv6 no port'             => [ '2001:db8::1', '2001:db8::1' ],
			'ipv6 bracketed no port'   => [ '[2001:db8::1]', '2001:db8::1' ],
			'ipv6 bracketed with port' => [ '[2001:db8::1]:443', '2001:db8::1' ],
			'ipv6 loopback'            => [ '::1', '::1' ],
			'ipv6 bracketed loopback'  => [ '[::1]:8080', '::1' ],
		];
	}

	// ---------------------------------------------------------------
	// is_valid()
	// ---------------------------------------------------------------

	#[DataProvider( 'isValidProvider' )]
	public function testIsValid( string $ip, bool $expected ): void {
		$this->assertSame( $expected, IpUtils::is_valid( $ip ) );
	}

	/**
	 * @return array<string, array{string, bool}>
	 */
	public static function isValidProvider(): array {
		return [
			'ipv4'           => [ '192.168.0.1', true ],
			'ipv6'           => [ '2001:db8::1', true ],
			'ipv6 loopback'  => [ '::1', true ],
			'ipv4 loopback'  => [ '127.0.0.1', true ],
			'empty'          => [ '', false ],
			'hostname'       => [ 'example.com', false ],
			'garbage'        => [ 'xyz', false ],
			'ipv4 with port' => [ '1.2.3.4:80', false ],
			'cidr'           => [ '10.0.0.0/8', false ],
		];
	}

	// ---------------------------------------------------------------
	// is_valid_cidr()
	// ---------------------------------------------------------------

	#[DataProvider( 'isValidCidrProvider' )]
	public function testIsValidCidr( string $cidr, bool $expected ): void {
		$this->assertSame( $expected, IpUtils::is_valid_cidr( $cidr ) );
	}

	/**
	 * @return array<string, array{string, bool}>
	 */
	public static function isValidCidrProvider(): array {
		return [
			'ipv4 /8'                 => [ '10.0.0.0/8', true ],
			'ipv4 /16'                => [ '172.16.0.0/12', true ],
			'ipv4 /32'                => [ '192.168.1.1/32', true ],
			'ipv4 bare'               => [ '192.168.1.1', true ],
			'ipv6 /7'                 => [ 'fc00::/7', true ],
			'ipv6 /128'               => [ '::1/128', true ],
			'ipv6 bare'               => [ '::1', true ],
			'invalid prefix negative' => [ '10.0.0.0/-1', false ],
			'ipv4 prefix too large'   => [ '10.0.0.0/33', false ],
			'ipv6 prefix too large'   => [ '::1/129', false ],
			'non-numeric prefix'      => [ '10.0.0.0/abc', false ],
			'invalid address'         => [ 'garbage/8', false ],
		];
	}

	// ---------------------------------------------------------------
	// is_in_range()
	// ---------------------------------------------------------------

	#[DataProvider( 'isInRangeProvider' )]
	public function testIsInRange( string $ip, string $cidr, bool $expected ): void {
		$this->assertSame( $expected, IpUtils::is_in_range( $ip, $cidr ) );
	}

	/**
	 * @return array<string, array{string, string, bool}>
	 */
	public static function isInRangeProvider(): array {
		return [
			// IPv4 basic ranges
			'ipv4 in 10/8'                => [ '10.0.0.1', '10.0.0.0/8', true ],
			'ipv4 10.255.255.255 in 10/8' => [ '10.255.255.255', '10.0.0.0/8', true ],
			'ipv4 not in 10/8'            => [ '11.0.0.1', '10.0.0.0/8', false ],
			'ipv4 in 172.16/12'           => [ '172.20.5.1', '172.16.0.0/12', true ],
			'ipv4 not in 172.16/12'       => [ '172.32.0.1', '172.16.0.0/12', false ],
			'ipv4 in 192.168/16'          => [ '192.168.100.5', '192.168.0.0/16', true ],
			'ipv4 not in 192.168/16'      => [ '192.169.0.1', '192.168.0.0/16', false ],
			'ipv4 exact match /32'        => [ '1.2.3.4', '1.2.3.4/32', true ],
			'ipv4 no match /32'           => [ '1.2.3.5', '1.2.3.4/32', false ],
			'ipv4 bare address match'     => [ '1.2.3.4', '1.2.3.4', true ],
			'ipv4 bare address no match'  => [ '1.2.3.5', '1.2.3.4', false ],
			'ipv4 localhost'              => [ '127.0.0.1', '127.0.0.0/8', true ],

			// IPv6 basic ranges
			'ipv6 in fc00::/7'            => [ 'fd00::1', 'fc00::/7', true ],
			'ipv6 fc00 itself'            => [ 'fc00::1', 'fc00::/7', true ],
			'ipv6 not in fc00::/7'        => [ '2001:db8::1', 'fc00::/7', false ],
			'ipv6 exact match /128'       => [ '::1', '::1/128', true ],
			'ipv6 no match /128'          => [ '::2', '::1/128', false ],
			'ipv6 bare match'             => [ '::1', '::1', true ],

			// IPv4-mapped IPv6 matching IPv4 ranges
			'mapped ipv6 in 10/8'         => [ '::ffff:10.0.0.1', '10.0.0.0/8', true ],
			'mapped ipv6 in 192.168/16'   => [ '::ffff:192.168.1.1', '192.168.0.0/16', true ],
			'mapped ipv6 not in 10/8'     => [ '::ffff:11.0.0.1', '10.0.0.0/8', false ],

			// IPv4 matching IPv4-mapped IPv6 range — maps range too
			'ipv4 vs mapped range'        => [ '10.0.0.1', '::ffff:10.0.0.0/8', true ], // Both normalise to IPv4; range becomes 10.0.0.0/8

			// Cross-family should not match
			'ipv4 vs ipv6'                => [ '10.0.0.1', 'fc00::/7', false ],
			'ipv6 vs ipv4'                => [ '::1', '127.0.0.0/8', false ],

			// With ports — should normalise
			'ipv4 with port in range'     => [ '10.0.0.1:8080', '10.0.0.0/8', true ],
			'ipv6 bracketed with port'    => [ '[fd00::1]:443', 'fc00::/7', true ],

			// Invalid inputs
			'garbage ip'                  => [ 'not-an-ip', '10.0.0.0/8', false ],
			'garbage cidr'                => [ '10.0.0.1', 'garbage/8', false ],
			'empty ip'                    => [ '', '10.0.0.0/8', false ],

			// Cloudflare ranges (spot check)
			'cf range 173.245.48/20'      => [ '173.245.48.1', '173.245.48.0/20', true ],
			'cf range 104.16/13'          => [ '104.16.0.1', '104.16.0.0/13', true ],
			'cf ipv6 2400:cb00::/32'      => [ '2400:cb00::1', '2400:cb00::/32', true ],
		];
	}

	// ---------------------------------------------------------------
	// matches_any()
	// ---------------------------------------------------------------

	public function testMatchesAnyTrue(): void {
		$cidrs = [ '10.0.0.0/8', '172.16.0.0/12', '192.168.0.0/16' ];
		$this->assertTrue( IpUtils::matches_any( '172.20.0.1', $cidrs ) );
	}

	public function testMatchesAnyFalse(): void {
		$cidrs = [ '10.0.0.0/8', '172.16.0.0/12', '192.168.0.0/16' ];
		$this->assertFalse( IpUtils::matches_any( '8.8.8.8', $cidrs ) );
	}

	public function testMatchesAnyEmpty(): void {
		$this->assertFalse( IpUtils::matches_any( '10.0.0.1', [] ) );
	}

	// ---------------------------------------------------------------
	// IPv4-mapped IPv6 edge cases
	// ---------------------------------------------------------------

	public function testMappedIpv6NormalisesToIpv4(): void {
		$this->assertSame( '10.0.0.1', IpUtils::normalise( '::ffff:10.0.0.1' ) );
		$this->assertSame( '192.168.1.1', IpUtils::normalise( '::ffff:c0a8:0101' ) );
	}

	public function testMappedIpv6MatchesIpv4Range(): void {
		$this->assertTrue( IpUtils::is_in_range( '::ffff:10.0.0.1', '10.0.0.0/8' ) );
		$this->assertTrue( IpUtils::is_in_range( '::ffff:192.168.1.100', '192.168.0.0/16' ) );
	}

	public function testNormalIpv6NotMapped(): void {
		// Regular IPv6 should NOT be normalised to IPv4.
		$this->assertSame( '2001:db8::1', IpUtils::normalise( '2001:db8::1' ) );
	}
}
