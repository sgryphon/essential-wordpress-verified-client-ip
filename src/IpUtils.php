<?php

declare(strict_types=1);

namespace Essential\VerifiedClientIp;

/**
 * IP address utility functions: validation, normalisation, CIDR matching.
 */
final class IpUtils {

	/**
	 * Check whether an IP address falls within a CIDR range.
	 *
	 * Supports IPv4 (e.g. 10.0.0.0/8) and IPv6 (e.g. fc00::/7).
	 * A bare address without prefix length is treated as a /32 (IPv4) or /128 (IPv6).
	 * IPv4-mapped IPv6 addresses are normalised before matching.
	 *
	 * @param string $ip   The IP address to check.
	 * @param string $cidr The CIDR range (e.g. "192.168.0.0/16" or "fc00::/7").
	 *
	 * @return bool True if the IP is within the range.
	 */
	public static function is_in_range( string $ip, string $cidr ): bool {
		$ip = self::normalise( $ip );
		if ( null === $ip ) {
			return false;
		}

		// Parse CIDR notation.
		if ( \str_contains( $cidr, '/' ) ) {
			[$range_ip, $prefix_len] = \explode( '/', $cidr, 2 );
			if ( ! \ctype_digit( $prefix_len ) ) {
				return false;
			}
			$prefix_len = (int) $prefix_len;
		} else {
			$range_ip   = $cidr;
			$prefix_len = null; // Will be set based on address family.
		}

		$range_ip = self::normalise( $range_ip );
		if ( null === $range_ip ) {
			return false;
		}

		// Both must be same family after normalisation.
		$ip_bin    = \inet_pton( $ip );
		$range_bin = \inet_pton( $range_ip );
		if ( false === $ip_bin || false === $range_bin ) {
			return false;
		}

		$ip_len    = \strlen( $ip_bin );
		$range_len = \strlen( $range_bin );

		if ( $ip_len !== $range_len ) {
			return false; // Different address families.
		}

		$max_bits     = $ip_len * 8; // 32 for IPv4, 128 for IPv6.
		$prefix_len ??= $max_bits;

		if ( $prefix_len < 0 || $prefix_len > $max_bits ) {
			return false;
		}

		// Build the netmask as a binary string.
		$mask      = \str_repeat( "\xff", (int) ( $prefix_len / 8 ) );
		$remainder = $prefix_len % 8;
		if ( $remainder > 0 ) {
			$mask .= \chr( 0xFF << ( 8 - $remainder ) & 0xFF );
		}
		$mask = \str_pad( $mask, $ip_len, "\x00" );

		return ( $ip_bin & $mask ) === ( $range_bin & $mask );
	}

	/**
	 * Check whether an IP address matches any entry in a list of addresses/CIDRs.
	 *
	 * @param string        $ip    The IP address to check.
	 * @param array<string> $cidrs List of CIDR ranges or individual IP addresses.
	 *
	 * @return bool True if the IP matches at least one entry.
	 */
	public static function matches_any( string $ip, array $cidrs ): bool {
		foreach ( $cidrs as $cidr ) {
			if ( self::is_in_range( $ip, $cidr ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Normalise an IP address.
	 *
	 * - Strips port numbers.
	 * - Converts IPv4-mapped IPv6 (::ffff:x.x.x.x) to plain IPv4.
	 * - Validates the result.
	 *
	 * Returns null if the address is not a valid IP.
	 */
	public static function normalise( string $ip ): ?string {
		$ip = \trim( $ip );
		$ip = self::strip_port( $ip );

		if ( '' === $ip ) {
			return null;
		}

		// Normalise via inet_pton + inet_ntop for consistent formatting.
		$packed = \inet_pton( $ip );
		if ( false === $packed ) {
			return null;
		}

		// Convert IPv4-mapped IPv6 to IPv4.
		if ( \strlen( $packed ) === 16 ) {
			// Check for ::ffff:x.x.x.x mapping (first 10 bytes zero, next 2 bytes 0xff).
			$prefix     = \substr( $packed, 0, 10 );
			$map_marker = \substr( $packed, 10, 2 );
			if ( \str_repeat( "\x00", 10 ) === $prefix && "\xff\xff" === $map_marker ) {
				$ipv4_bytes = \substr( $packed, 12, 4 );
				$ipv4_str   = \inet_ntop( $ipv4_bytes );

				return false !== $ipv4_str ? $ipv4_str : null;
			}
		}

		$normalised = \inet_ntop( $packed );

		return false !== $normalised ? $normalised : null;
	}

	/**
	 * Strip port from an IP address string.
	 *
	 * Handles:
	 * - IPv4 with port: "1.2.3.4:8080" -> "1.2.3.4"
	 * - Bracketed IPv6 with port: "[::1]:8080" -> "::1"
	 * - Bracketed IPv6 without port: "[::1]" -> "::1"
	 * - Plain IPv6: "::1" -> "::1" (unchanged)
	 */
	public static function strip_port( string $ip ): string {
		$ip = \trim( $ip );

		// Bracketed IPv6: [addr] or [addr]:port
		if ( \str_starts_with( $ip, '[' ) ) {
			$close_bracket = \strpos( $ip, ']' );
			if ( false !== $close_bracket ) {
				return \substr( $ip, 1, $close_bracket - 1 );
			}

			// Malformed bracket — return as-is without the leading bracket.
			return \substr( $ip, 1 );
		}

		// IPv4 with port: only if there's exactly one colon (IPv6 has multiple).
		if ( \substr_count( $ip, ':' ) === 1 ) {
			$parts = \explode( ':', $ip, 2 );

			// Only strip if the part before the colon looks like an IPv4 address.
			if ( \filter_var( $parts[0], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) !== false ) {
				return $parts[0];
			}
		}

		return $ip;
	}

	/**
	 * Validate whether a string is a valid IP address (v4 or v6).
	 *
	 * Does NOT accept ports or CIDR notation — use normalise() first if needed.
	 */
	public static function is_valid( string $ip ): bool {
		return \filter_var( $ip, FILTER_VALIDATE_IP ) !== false;
	}

	/**
	 * Validate whether a string is valid CIDR notation (address/prefix).
	 */
	public static function is_valid_cidr( string $cidr ): bool {
		if ( ! \str_contains( $cidr, '/' ) ) {
			return self::is_valid( $cidr );
		}

		[$address, $prefix] = \explode( '/', $cidr, 2 );
		if ( ! \ctype_digit( $prefix ) ) {
			return false;
		}

		$prefix_len = (int) $prefix;
		$packed     = \inet_pton( $address );
		if ( false === $packed ) {
			return false;
		}

		$max_bits = \strlen( $packed ) * 8;

		return $prefix_len >= 0 && $prefix_len <= $max_bits;
	}
}
