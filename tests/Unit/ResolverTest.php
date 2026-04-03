<?php

declare(strict_types=1);

namespace Essential\VerifiedClientIp\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Essential\VerifiedClientIp\Resolver;
use Essential\VerifiedClientIp\Scheme;

/**
 * Extensive tests for the core IP resolution algorithm.
 */
final class ResolverTest extends TestCase {

	private Resolver $resolver;

	/** @var array<Scheme> Default schemes matching the spec. */
	private array $default_schemes;

	protected function setUp(): void {
		$this->resolver = new Resolver();

		$private_proxies = [
			'10.0.0.0/8',
			'172.16.0.0/12',
			'192.168.0.0/16',
			'127.0.0.0/8',
			'::1/128',
			'fc00::/7',
		];

		$cloudflare_proxies = [
			'173.245.48.0/20',
			'103.21.244.0/22',
			'103.22.200.0/22',
			'103.31.4.0/22',
			'141.101.64.0/18',
			'108.162.192.0/18',
			'190.93.240.0/20',
			'188.114.96.0/20',
			'197.234.240.0/22',
			'198.41.128.0/17',
			'162.158.0.0/15',
			'104.16.0.0/13',
			'104.24.0.0/14',
			'172.64.0.0/13',
			'131.0.72.0/22',
			'2400:cb00::/32',
			'2606:4700::/32',
			'2803:f800::/32',
			'2405:b500::/32',
			'2405:8100::/32',
			'2a06:98c0::/29',
			'2c0f:f248::/32',
		];

		$this->default_schemes = [
			new Scheme(
				name: 'RFC 7239 Forwarded',
				enabled: true,
				proxies: $private_proxies,
				header: 'Forwarded',
				token: 'for',
			),
			new Scheme(
				name: 'X-Forwarded-For',
				enabled: true,
				proxies: $private_proxies,
				header: 'X-Forwarded-For',
			),
			new Scheme(
				name: 'Cloudflare',
				enabled: true,
				proxies: $cloudflare_proxies,
				header: 'CF-Connecting-IP',
			),
		];
	}

	// ---------------------------------------------------------------
	// No-op scenarios
	// ---------------------------------------------------------------

	public function testNoOpWhenRemoteAddrNotTrustedProxy(): void {
		$server = [
			'REMOTE_ADDR'          => '203.0.113.50',
			'HTTP_X_FORWARDED_FOR' => '10.0.0.1',
		];

		$result = $this->resolver->resolve( $server, $this->default_schemes );

		$this->assertFalse( $result->changed );
		$this->assertSame( '203.0.113.50', $result->resolved_ip );
		$this->assertSame( '203.0.113.50', $result->original_ip );
		$this->assertNotEmpty( $result->steps );
		$this->assertStringContainsString( 'not a trusted proxy', $result->steps[0]->action );
	}

	public function testNoOpWhenNoHeaders(): void {
		$server = [
			'REMOTE_ADDR' => '203.0.113.50',
		];

		$result = $this->resolver->resolve( $server, $this->default_schemes );

		$this->assertFalse( $result->changed );
		$this->assertSame( '203.0.113.50', $result->resolved_ip );
	}

	public function testNoOpWhenRemoteAddrEmpty(): void {
		$server = [
			'REMOTE_ADDR' => '',
		];

		$result = $this->resolver->resolve( $server, $this->default_schemes );

		$this->assertFalse( $result->changed );
	}

	public function testNoOpWhenRemoteAddrMissing(): void {
		$server = [];

		$result = $this->resolver->resolve( $server, $this->default_schemes );

		$this->assertFalse( $result->changed );
	}

	public function testNoOpWhenRemoteAddrInvalid(): void {
		$server = [
			'REMOTE_ADDR' => 'not-an-ip',
		];

		$result = $this->resolver->resolve( $server, $this->default_schemes );

		$this->assertFalse( $result->changed );
	}

	public function testNoOpWhenNoSchemesEnabled(): void {
		$schemes = [
			new Scheme( 'Test', false, [ '10.0.0.0/8' ], 'X-Forwarded-For' ),
		];

		$server = [
			'REMOTE_ADDR'          => '10.0.0.1',
			'HTTP_X_FORWARDED_FOR' => '203.0.113.50',
		];

		$result = $this->resolver->resolve( $server, $schemes );

		$this->assertFalse( $result->changed );
	}

	public function testNoOpWhenHeaderNotPresent(): void {
		$server = [
			'REMOTE_ADDR' => '10.0.0.1',
			// No HTTP_X_FORWARDED_FOR header.
		];

		$result = $this->resolver->resolve( $server, $this->default_schemes );

		$this->assertFalse( $result->changed );
		$this->assertSame( '10.0.0.1', $result->resolved_ip );
	}

	public function testNoOpWhenHeaderEmpty(): void {
		$server = [
			'REMOTE_ADDR'          => '10.0.0.1',
			'HTTP_X_FORWARDED_FOR' => '',
		];

		$result = $this->resolver->resolve( $server, $this->default_schemes );

		$this->assertFalse( $result->changed );
	}

	// ---------------------------------------------------------------
	// Single hop (Forward Limit = 1)
	// ---------------------------------------------------------------

	public function testSingleHopXff(): void {
		$server = [
			'REMOTE_ADDR'          => '10.0.0.1',
			'HTTP_X_FORWARDED_FOR' => '203.0.113.50',
		];

		$result = $this->resolver->resolve( $server, $this->default_schemes, 1 );

		$this->assertTrue( $result->changed );
		$this->assertSame( '203.0.113.50', $result->resolved_ip );
		$this->assertSame( '10.0.0.1', $result->original_ip );
	}

	public function testSingleHopForwardedHeader(): void {
		$server = [
			'REMOTE_ADDR'    => '192.168.1.1',
			'HTTP_FORWARDED' => 'for=203.0.113.50;proto=https',
		];

		$result = $this->resolver->resolve( $server, $this->default_schemes, 1 );

		$this->assertTrue( $result->changed );
		$this->assertSame( '203.0.113.50', $result->resolved_ip );
		$this->assertSame( '192.168.1.1', $result->original_ip );
	}

	public function testSingleHopCloudflare(): void {
		$schemes = [
			new Scheme(
				name: 'Cloudflare',
				enabled: true,
				proxies: [ '173.245.48.0/20' ],
				header: 'CF-Connecting-IP',
			),
		];

		$server = [
			'REMOTE_ADDR'           => '173.245.48.1',
			'HTTP_CF_CONNECTING_IP' => '203.0.113.50',
		];

		$result = $this->resolver->resolve( $server, $schemes, 1 );

		$this->assertTrue( $result->changed );
		$this->assertSame( '203.0.113.50', $result->resolved_ip );
	}

	public function testSingleHopXffMultipleAddresses(): void {
		// Only the rightmost (most recently added) should be used for single hop.
		$server = [
			'REMOTE_ADDR'          => '10.0.0.1',
			'HTTP_X_FORWARDED_FOR' => '1.1.1.1, 203.0.113.50',
		];

		$result = $this->resolver->resolve( $server, $this->default_schemes, 1 );

		$this->assertTrue( $result->changed );
		$this->assertSame( '203.0.113.50', $result->resolved_ip );
	}

	// ---------------------------------------------------------------
	// Multi-hop scenarios
	// ---------------------------------------------------------------

	public function testMultiHopTwoProxies(): void {
		// client (203.0.113.50) -> proxy1 (10.0.0.1) -> proxy2 (192.168.1.1) -> server
		// XFF: "203.0.113.50, 10.0.0.1" — server sees REMOTE_ADDR=192.168.1.1
		$server = [
			'REMOTE_ADDR'          => '192.168.1.1',
			'HTTP_X_FORWARDED_FOR' => '203.0.113.50, 10.0.0.1',
		];

		$result = $this->resolver->resolve( $server, $this->default_schemes, 2 );

		$this->assertTrue( $result->changed );
		$this->assertSame( '203.0.113.50', $result->resolved_ip );
	}

	public function testMultiHopForwardLimitReached(): void {
		// 3-hop chain but forward limit is 1 — should only verify 1 proxy hop.
		// REMOTE_ADDR is the 1st forward (= the limit). The rightmost XFF address
		// is returned as the verified client IP WITHOUT being checked.
		$server = [
			'REMOTE_ADDR'          => '192.168.1.1',
			'HTTP_X_FORWARDED_FOR' => '203.0.113.50, 10.0.0.2, 10.0.0.1',
		];

		$result = $this->resolver->resolve( $server, $this->default_schemes, 1 );

		$this->assertTrue( $result->changed );
		// Forward limit 1: REMOTE_ADDR counts as forward #1 (= limit).
		// Next address (rightmost XFF = 10.0.0.1) returned without trust check.
		$this->assertSame( '10.0.0.1', $result->resolved_ip );
	}

	public function testMultiHopSchemeSwitch(): void {
		// client -> cloudflare (173.245.48.1) -> private proxy (10.0.0.1) -> server
		// REMOTE_ADDR=10.0.0.1, XFF="203.0.113.50, 173.245.48.1"
		// Hop 1: 10.0.0.1 matches XFF scheme, read XFF -> 173.245.48.1
		// Hop 2: 173.245.48.1 matches Cloudflare scheme, read CF header -> 203.0.113.50
		$server = [
			'REMOTE_ADDR'           => '10.0.0.1',
			'HTTP_X_FORWARDED_FOR'  => '203.0.113.50, 173.245.48.1',
			'HTTP_CF_CONNECTING_IP' => '203.0.113.50',
		];

		$result = $this->resolver->resolve( $server, $this->default_schemes, 2 );

		$this->assertTrue( $result->changed );
		$this->assertSame( '203.0.113.50', $result->resolved_ip );
	}

	// ---------------------------------------------------------------
	// Scheme priority
	// ---------------------------------------------------------------

	public function testSchemePriorityFirstMatchWins(): void {
		// An IP matches both schemes — the first (highest priority) should win.
		$schemes = [
			new Scheme( 'Scheme A', true, [ '10.0.0.0/8' ], 'X-Real-IP' ),
			new Scheme( 'Scheme B', true, [ '10.0.0.0/8' ], 'X-Forwarded-For' ),
		];

		$server = [
			'REMOTE_ADDR'          => '10.0.0.1',
			'HTTP_X_REAL_IP'       => '1.1.1.1',
			'HTTP_X_FORWARDED_FOR' => '2.2.2.2',
		];

		$result = $this->resolver->resolve( $server, $schemes, 1 );

		$this->assertTrue( $result->changed );
		$this->assertSame( '1.1.1.1', $result->resolved_ip );
	}

	// ---------------------------------------------------------------
	// Malformed header values
	// ---------------------------------------------------------------

	public function testMalformedValueBecomesVerifiedIp(): void {
		$server = [
			'REMOTE_ADDR'          => '10.0.0.1',
			'HTTP_X_FORWARDED_FOR' => 'unknown',
		];

		$result = $this->resolver->resolve( $server, $this->default_schemes, 1 );

		$this->assertTrue( $result->changed );
		$this->assertSame( 'unknown', $result->resolved_ip );
	}

	public function testHiddenTokenBecomesVerifiedIp(): void {
		$server = [
			'REMOTE_ADDR'          => '10.0.0.1',
			'HTTP_X_FORWARDED_FOR' => '_hidden',
		];

		$result = $this->resolver->resolve( $server, $this->default_schemes, 1 );

		$this->assertTrue( $result->changed );
		$this->assertSame( '_hidden', $result->resolved_ip );
	}

	public function testHostnameBecomesVerifiedIp(): void {
		$server = [
			'REMOTE_ADDR'          => '10.0.0.1',
			'HTTP_X_FORWARDED_FOR' => 'example.com',
		];

		$result = $this->resolver->resolve( $server, $this->default_schemes, 1 );

		$this->assertTrue( $result->changed );
		$this->assertSame( 'example.com', $result->resolved_ip );
	}

	public function testGarbageStringBecomesVerifiedIp(): void {
		$server = [
			'REMOTE_ADDR'          => '10.0.0.1',
			'HTTP_X_FORWARDED_FOR' => '<script>alert(1)</script>',
		];

		$result = $this->resolver->resolve( $server, $this->default_schemes, 1 );

		$this->assertTrue( $result->changed );
		// Malformed = untrusted = verified client IP (even though it's garbage).
		$this->assertSame( '<script>alert(1)</script>', $result->resolved_ip );
	}

	// ---------------------------------------------------------------
	// IPv4-mapped IPv6
	// ---------------------------------------------------------------

	public function testIpv4MappedIpv6RemoteAddr(): void {
		// Server reports REMOTE_ADDR in IPv4-mapped form.
		$server = [
			'REMOTE_ADDR'          => '::ffff:10.0.0.1',
			'HTTP_X_FORWARDED_FOR' => '203.0.113.50',
		];

		$result = $this->resolver->resolve( $server, $this->default_schemes, 1 );

		$this->assertTrue( $result->changed );
		$this->assertSame( '203.0.113.50', $result->resolved_ip );
	}

	public function testIpv4MappedIpv6InForwardedHeader(): void {
		$server = [
			'REMOTE_ADDR'          => '10.0.0.1',
			'HTTP_X_FORWARDED_FOR' => '::ffff:203.0.113.50',
		];

		$result = $this->resolver->resolve( $server, $this->default_schemes, 1 );

		$this->assertTrue( $result->changed );
		// Should normalise to IPv4.
		$this->assertSame( '203.0.113.50', $result->resolved_ip );
	}

	// ---------------------------------------------------------------
	// IPv6 scenarios
	// ---------------------------------------------------------------

	public function testIpv6RemoteAddrWithUlaProxy(): void {
		$server = [
			'REMOTE_ADDR'          => 'fd00::1',
			'HTTP_X_FORWARDED_FOR' => '2001:db8::100',
		];

		$result = $this->resolver->resolve( $server, $this->default_schemes, 1 );

		$this->assertTrue( $result->changed );
		$this->assertSame( '2001:db8::100', $result->resolved_ip );
	}

	public function testIpv6Localhost(): void {
		$server = [
			'REMOTE_ADDR'          => '::1',
			'HTTP_X_FORWARDED_FOR' => '203.0.113.50',
		];

		$result = $this->resolver->resolve( $server, $this->default_schemes, 1 );

		$this->assertTrue( $result->changed );
		$this->assertSame( '203.0.113.50', $result->resolved_ip );
	}

	// ---------------------------------------------------------------
	// Spoofing / security tests
	// ---------------------------------------------------------------

	public function testSpoofedHeadersIgnoredWhenRemoteAddrNotProxy(): void {
		// Attacker sets XFF but connects directly — headers should be ignored.
		$server = [
			'REMOTE_ADDR'           => '203.0.113.50',
			'HTTP_X_FORWARDED_FOR'  => '10.0.0.1, 1.2.3.4',
			'HTTP_FORWARDED'        => 'for=10.0.0.1',
			'HTTP_CF_CONNECTING_IP' => '5.6.7.8',
		];

		$result = $this->resolver->resolve( $server, $this->default_schemes );

		$this->assertFalse( $result->changed );
		$this->assertSame( '203.0.113.50', $result->resolved_ip );
	}

	public function testInjectedHeadersBeforeProxy(): void {
		// client (203.0.113.50) -> proxy (10.0.0.1) -> server
		// Client injects "1.1.1.1" into XFF before hitting the proxy.
		// XFF becomes: "1.1.1.1, 203.0.113.50" (proxy appends real client IP).
		// With forward limit 1, we should get 203.0.113.50 (rightmost).
		$server = [
			'REMOTE_ADDR'          => '10.0.0.1',
			'HTTP_X_FORWARDED_FOR' => '1.1.1.1, 203.0.113.50',
		];

		$result = $this->resolver->resolve( $server, $this->default_schemes, 1 );

		$this->assertTrue( $result->changed );
		$this->assertSame( '203.0.113.50', $result->resolved_ip );
	}

	public function testMultipleInjectedHeaders(): void {
		// Client injects multiple fake addresses.
		// With forward limit 1, only the rightmost is used.
		$server = [
			'REMOTE_ADDR'          => '10.0.0.1',
			'HTTP_X_FORWARDED_FOR' => '6.6.6.6, 7.7.7.7, 8.8.8.8, 203.0.113.50',
		];

		$result = $this->resolver->resolve( $server, $this->default_schemes, 1 );

		$this->assertTrue( $result->changed );
		$this->assertSame( '203.0.113.50', $result->resolved_ip );
	}

	// ---------------------------------------------------------------
	// All-trusted chain
	// ---------------------------------------------------------------

	public function testAllTrustedUsesOutermost(): void {
		// All addresses are trusted proxies.
		// Should use the outermost (leftmost) forwarded address.
		$server = [
			'REMOTE_ADDR'          => '10.0.0.1',
			'HTTP_X_FORWARDED_FOR' => '10.0.0.3, 10.0.0.2',
		];

		$result = $this->resolver->resolve( $server, $this->default_schemes, 3 );

		$this->assertTrue( $result->changed );
		$this->assertSame( '10.0.0.3', $result->resolved_ip );
	}

	// ---------------------------------------------------------------
	// Graceful degradation
	// ---------------------------------------------------------------

	public function testWrongHeaderNameFallsBackToRemoteAddr(): void {
		// Proxy sends X-Real-IP but scheme expects X-Forwarded-For.
		$server = [
			'REMOTE_ADDR'    => '10.0.0.1',
			'HTTP_X_REAL_IP' => '203.0.113.50',
		];

		$result = $this->resolver->resolve( $server, $this->default_schemes, 1 );

		// No XFF or Forwarded header present → no-op.
		$this->assertFalse( $result->changed );
		$this->assertSame( '10.0.0.1', $result->resolved_ip );
	}

	public function testMalformedProxyHeaderDoesNotCrash(): void {
		$server = [
			'REMOTE_ADDR'          => '10.0.0.1',
			'HTTP_X_FORWARDED_FOR' => ',,,,',
		];

		$result = $this->resolver->resolve( $server, $this->default_schemes, 1 );

		// Empty after parsing → no-op.
		$this->assertFalse( $result->changed );
	}

	// ---------------------------------------------------------------
	// Step trace
	// ---------------------------------------------------------------

	public function testStepTraceProduced(): void {
		$server = [
			'REMOTE_ADDR'          => '10.0.0.1',
			'HTTP_X_FORWARDED_FOR' => '203.0.113.50',
		];

		$result = $this->resolver->resolve( $server, $this->default_schemes, 1 );

		$this->assertNotEmpty( $result->steps );
		$this->assertSame( 1, $result->steps[0]->step );
		$this->assertSame( '10.0.0.1', $result->steps[0]->address );
	}

	public function testStepTraceToArrayConversion(): void {
		$server = [
			'REMOTE_ADDR'          => '10.0.0.1',
			'HTTP_X_FORWARDED_FOR' => '203.0.113.50',
		];

		$result = $this->resolver->resolve( $server, $this->default_schemes, 1 );

		foreach ( $result->steps as $step ) {
			$arr = $step->to_array();
			$this->assertArrayHasKey( 'step', $arr );
			$this->assertArrayHasKey( 'address', $arr );
			$this->assertArrayHasKey( 'action', $arr );
		}
	}

	// ---------------------------------------------------------------
	// Proto extraction
	// ---------------------------------------------------------------

	public function testProtoExtractionFromForwardedHeader(): void {
		$server = [
			'REMOTE_ADDR'    => '10.0.0.1',
			'HTTP_FORWARDED' => 'for=203.0.113.50;proto=https',
		];

		$result = $this->resolver->resolve( $server, $this->default_schemes, 1 );

		$this->assertSame( 'https', $result->proto['proto'] ?? null );
	}

	public function testProtoExtractionFromXForwardedProto(): void {
		$server = [
			'REMOTE_ADDR'            => '10.0.0.1',
			'HTTP_X_FORWARDED_FOR'   => '203.0.113.50',
			'HTTP_X_FORWARDED_PROTO' => 'https',
		];

		// Make XFF match first (it's higher priority than Forwarded in our default schemes? Let's use only XFF)
		$schemes = [
			new Scheme( 'XFF', true, [ '10.0.0.0/8' ], 'X-Forwarded-For' ),
		];

		$result = $this->resolver->resolve( $server, $schemes, 1 );

		$this->assertSame( 'https', $result->proto['proto'] ?? null );
	}

	// ---------------------------------------------------------------
	// RFC 7239 Forwarded header specific
	// ---------------------------------------------------------------

	public function testForwardedHeaderWithQuotedIpv6(): void {
		$server = [
			'REMOTE_ADDR'    => '10.0.0.1',
			'HTTP_FORWARDED' => 'for="[2001:db8::1]:8080"',
		];

		$result = $this->resolver->resolve( $server, $this->default_schemes, 1 );

		$this->assertTrue( $result->changed );
		$this->assertSame( '2001:db8::1', $result->resolved_ip );
	}

	public function testForwardedHeaderMultipleElements(): void {
		// for=client, for=proxy1 — proxy1 is rightmost/most recent.
		$server = [
			'REMOTE_ADDR'    => '10.0.0.1',
			'HTTP_FORWARDED' => 'for=1.1.1.1, for=203.0.113.50',
		];

		$result = $this->resolver->resolve( $server, $this->default_schemes, 1 );

		$this->assertTrue( $result->changed );
		$this->assertSame( '203.0.113.50', $result->resolved_ip );
	}

	// ---------------------------------------------------------------
	// Disabled scheme
	// ---------------------------------------------------------------

	public function testDisabledSchemeSkipped(): void {
		$schemes = [
			new Scheme( 'Disabled', false, [ '10.0.0.0/8' ], 'X-Forwarded-For' ),
			new Scheme( 'Enabled', true, [ '10.0.0.0/8' ], 'X-Real-IP' ),
		];

		$server = [
			'REMOTE_ADDR'          => '10.0.0.1',
			'HTTP_X_FORWARDED_FOR' => '1.1.1.1',
			'HTTP_X_REAL_IP'       => '2.2.2.2',
		];

		$result = $this->resolver->resolve( $server, $schemes, 1 );

		$this->assertTrue( $result->changed );
		$this->assertSame( '2.2.2.2', $result->resolved_ip );
	}

	// ---------------------------------------------------------------
	// Empty schemes
	// ---------------------------------------------------------------

	public function testEmptySchemesArray(): void {
		$server = [
			'REMOTE_ADDR'          => '10.0.0.1',
			'HTTP_X_FORWARDED_FOR' => '203.0.113.50',
		];

		$result = $this->resolver->resolve( $server, [], 1 );

		$this->assertFalse( $result->changed );
	}

	// ---------------------------------------------------------------
	// Cloudflare multi-hop scenario
	// ---------------------------------------------------------------

	public function testClientThroughCloudflareAndPrivateProxy(): void {
		// Real scenario: client -> Cloudflare -> private proxy -> server
		// REMOTE_ADDR=10.0.0.1 (private proxy)
		// XFF: "203.0.113.50, 173.245.48.1" (cloudflare added real IP, then private proxy appended CF IP)
		// CF-Connecting-IP: "203.0.113.50" (cloudflare's view of the client)

		$server = [
			'REMOTE_ADDR'           => '10.0.0.1',
			'HTTP_X_FORWARDED_FOR'  => '203.0.113.50, 173.245.48.1',
			'HTTP_CF_CONNECTING_IP' => '203.0.113.50',
		];

		$result = $this->resolver->resolve( $server, $this->default_schemes, 2 );

		$this->assertTrue( $result->changed );
		$this->assertSame( '203.0.113.50', $result->resolved_ip );
	}

	// ---------------------------------------------------------------
	// Port handling in addresses
	// ---------------------------------------------------------------

	public function testPortStrippedFromForwardedAddress(): void {
		$server = [
			'REMOTE_ADDR'    => '10.0.0.1',
			'HTTP_FORWARDED' => 'for="192.0.2.1:8080"',
		];

		$result = $this->resolver->resolve( $server, $this->default_schemes, 1 );

		$this->assertTrue( $result->changed );
		$this->assertSame( '192.0.2.1', $result->resolved_ip );
	}

	// ---------------------------------------------------------------
	// Scheme to_array / from_array
	// ---------------------------------------------------------------

	public function testSchemeRoundTrip(): void {
		$scheme = new Scheme(
			name: 'Test',
			enabled: true,
			proxies: [ '10.0.0.0/8' ],
			header: 'X-Forwarded-For',
			token: null,
			notes: 'A note',
		);

		$arr      = $scheme->to_array();
		$restored = Scheme::from_array( $arr );

		$this->assertSame( $scheme->name, $restored->name );
		$this->assertSame( $scheme->enabled, $restored->enabled );
		$this->assertSame( $scheme->proxies, $restored->proxies );
		$this->assertSame( $scheme->header, $restored->header );
		$this->assertSame( $scheme->token, $restored->token );
		$this->assertSame( $scheme->notes, $restored->notes );
	}

	// ---------------------------------------------------------------
	// Forward Limit semantics (user-specified examples)
	// ---------------------------------------------------------------

	public function testForwardLimitTwoPublicRemoteAddr(): void {
		// Limit=2, REMOTE_ADDR is public → no-op (not a proxy at all).
		$server = [
			'REMOTE_ADDR'          => '8.8.8.8',
			'HTTP_X_FORWARDED_FOR' => '1.2.3.4',
		];

		$result = $this->resolver->resolve( $server, $this->default_schemes, 2 );

		$this->assertFalse( $result->changed );
		$this->assertSame( '8.8.8.8', $result->resolved_ip );
	}

	public function testForwardLimitTwoOneProxyUntrustedClient(): void {
		// Limit=2, REMOTE_ADDR=10.0.0.1 (trusted, forward #1).
		// XFF has one address: 8.8.8.8 (public, untrusted).
		// 1 < 2 so we check 8.8.8.8 → not trusted → return as client.
		$server = [
			'REMOTE_ADDR'          => '10.0.0.1',
			'HTTP_X_FORWARDED_FOR' => '8.8.8.8',
		];

		$result = $this->resolver->resolve( $server, $this->default_schemes, 2 );

		$this->assertTrue( $result->changed );
		$this->assertSame( '8.8.8.8', $result->resolved_ip );
	}

	public function testForwardLimitTwoTwoProxiesUntrustedNext(): void {
		// Limit=2.
		// REMOTE_ADDR=10.0.0.1 (trusted, forward #1).
		// XFF rightmost=192.168.1.1 (trusted, forward #2 = limit reached).
		// Next address=8.8.8.8 → returned WITHOUT trust check (limit hit).
		$server = [
			'REMOTE_ADDR'          => '10.0.0.1',
			'HTTP_X_FORWARDED_FOR' => '8.8.8.8, 192.168.1.1',
		];

		$result = $this->resolver->resolve( $server, $this->default_schemes, 2 );

		$this->assertTrue( $result->changed );
		$this->assertSame( '8.8.8.8', $result->resolved_ip );
	}

	public function testForwardLimitTwoTwoProxiesTrustedNext(): void {
		// Limit=2.
		// REMOTE_ADDR=10.0.0.1 (trusted, forward #1).
		// XFF rightmost=192.168.1.1 (trusted, forward #2 = limit reached).
		// Next address=10.5.6.7 → returned WITHOUT trust check because limit hit
		// (even though 10.5.6.7 is a private/trusted address).
		$server = [
			'REMOTE_ADDR'          => '10.0.0.1',
			'HTTP_X_FORWARDED_FOR' => '10.5.6.7, 192.168.1.1',
		];

		$result = $this->resolver->resolve( $server, $this->default_schemes, 2 );

		$this->assertTrue( $result->changed );
		$this->assertSame( '10.5.6.7', $result->resolved_ip );
	}

	public function testForwardLimitThreeReturnsAtLimit(): void {
		// Limit=3.
		// REMOTE_ADDR=10.0.0.1 (forward #1).
		// XFF: "203.0.113.50, 192.168.2.1, 192.168.1.1"
		// rightmost-first: [192.168.1.1, 192.168.2.1, 203.0.113.50]
		//
		// 192.168.1.1: trusted, forward #2 (< 3, continue).
		// 192.168.2.1: trusted, forward #3 (= 3, limit reached).
		// 203.0.113.50: returned without trust check.
		$server = [
			'REMOTE_ADDR'          => '10.0.0.1',
			'HTTP_X_FORWARDED_FOR' => '203.0.113.50, 192.168.2.1, 192.168.1.1',
		];

		$result = $this->resolver->resolve( $server, $this->default_schemes, 3 );

		$this->assertTrue( $result->changed );
		$this->assertSame( '203.0.113.50', $result->resolved_ip );
	}

	public function testForwardLimitStopsEarlyOnUntrusted(): void {
		// Limit=3, but an untrusted address is found at forward #2.
		// REMOTE_ADDR=10.0.0.1 (forward #1).
		// XFF: "10.0.0.3, 203.0.113.50, 192.168.1.1"
		// rightmost-first: [192.168.1.1, 203.0.113.50, 10.0.0.3]
		//
		// 192.168.1.1: trusted, forward #2 (< 3, check next).
		// 203.0.113.50: NOT trusted → verified client IP (before limit).
		$server = [
			'REMOTE_ADDR'          => '10.0.0.1',
			'HTTP_X_FORWARDED_FOR' => '10.0.0.3, 203.0.113.50, 192.168.1.1',
		];

		$result = $this->resolver->resolve( $server, $this->default_schemes, 3 );

		$this->assertTrue( $result->changed );
		$this->assertSame( '203.0.113.50', $result->resolved_ip );
	}

	public function testForwardLimitDiagnosticStepShowsReason(): void {
		// When forward limit is reached, the step trace should say "Forward limit".
		$server = [
			'REMOTE_ADDR'          => '10.0.0.1',
			'HTTP_X_FORWARDED_FOR' => '10.5.6.7, 192.168.1.1',
		];

		$result = $this->resolver->resolve( $server, $this->default_schemes, 2 );

		// Find the step that mentions forward limit.
		$limit_step = null;
		foreach ( $result->steps as $step ) {
			if ( \str_contains( $step->action, 'Forward limit' ) ) {
				$limit_step = $step;
				break;
			}
		}

		$this->assertNotNull( $limit_step, 'Expected a step mentioning "Forward limit"' );
		$this->assertStringContainsString( '10.5.6.7', $limit_step->action );
	}

	public function testForwardLimitDiagnosticStepShowsUntrusted(): void {
		// When an untrusted address is found before the limit, the step trace
		// should say "not a trusted proxy".
		$server = [
			'REMOTE_ADDR'          => '10.0.0.1',
			'HTTP_X_FORWARDED_FOR' => '8.8.8.8',
		];

		$result = $this->resolver->resolve( $server, $this->default_schemes, 2 );

		$last_step = $result->steps[ \count( $result->steps ) - 1 ];
		$this->assertStringContainsString( 'not a trusted proxy', $last_step->action );
	}
}
