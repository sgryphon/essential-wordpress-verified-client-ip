<?php

declare(strict_types=1);

namespace VerifiedClientIp;

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
	public static function isInRange( string $ip, string $cidr ): bool {
		$ip = self::normalise( $ip );
		if ( $ip === null ) {
			return false;
		}

		// Parse CIDR notation.
		if ( \str_contains( $cidr, '/' ) ) {
			[$rangeIp, $prefixLen] = \explode( '/', $cidr, 2 );
			if ( ! \ctype_digit( $prefixLen ) ) {
				return false;
			}
			$prefixLen = (int) $prefixLen;
		} else {
			$rangeIp   = $cidr;
			$prefixLen = null; // Will be set based on address family.
		}

		$rangeIp = self::normalise( $rangeIp );
		if ( $rangeIp === null ) {
			return false;
		}

		// Both must be same family after normalisation.
		$ipBin    = @\inet_pton( $ip );
		$rangeBin = @\inet_pton( $rangeIp );
		if ( $ipBin === false || $rangeBin === false ) {
			return false;
		}

		$ipLen    = \strlen( $ipBin );
		$rangeLen = \strlen( $rangeBin );

		if ( $ipLen !== $rangeLen ) {
			return false; // Different address families.
		}

		$maxBits     = $ipLen * 8; // 32 for IPv4, 128 for IPv6.
		$prefixLen ??= $maxBits;

		if ( $prefixLen < 0 || $prefixLen > $maxBits ) {
			return false;
		}

		// Build the netmask as a binary string.
		$mask      = \str_repeat( "\xff", (int) ( $prefixLen / 8 ) );
		$remainder = $prefixLen % 8;
		if ( $remainder > 0 ) {
			$mask .= \chr( 0xFF << ( 8 - $remainder ) & 0xFF );
		}
		$mask = \str_pad( $mask, $ipLen, "\x00" );

		return ( $ipBin & $mask ) === ( $rangeBin & $mask );
	}

	/**
	 * Check whether an IP address matches any entry in a list of addresses/CIDRs.
	 *
	 * @param string        $ip    The IP address to check.
	 * @param array<string> $cidrs List of CIDR ranges or individual IP addresses.
	 *
	 * @return bool True if the IP matches at least one entry.
	 */
	public static function matchesAny( string $ip, array $cidrs ): bool {
		foreach ( $cidrs as $cidr ) {
			if ( self::isInRange( $ip, $cidr ) ) {
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
		$ip = self::stripPort( $ip );

		if ( $ip === '' ) {
			return null;
		}

		// Normalise via inet_pton + inet_ntop for consistent formatting.
		$packed = @\inet_pton( $ip );
		if ( $packed === false ) {
			return null;
		}

		// Convert IPv4-mapped IPv6 to IPv4.
		if ( \strlen( $packed ) === 16 ) {
			// Check for ::ffff:x.x.x.x mapping (first 10 bytes zero, next 2 bytes 0xff).
			$prefix    = \substr( $packed, 0, 10 );
			$mapMarker = \substr( $packed, 10, 2 );
			if ( $prefix === \str_repeat( "\x00", 10 ) && $mapMarker === "\xff\xff" ) {
				$ipv4Bytes = \substr( $packed, 12, 4 );

				return \inet_ntop( $ipv4Bytes ) ?: null;
			}
		}

		$normalised = \inet_ntop( $packed );

		return $normalised !== false ? $normalised : null;
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
	public static function stripPort( string $ip ): string {
		$ip = \trim( $ip );

		// Bracketed IPv6: [addr] or [addr]:port
		if ( \str_starts_with( $ip, '[' ) ) {
			$closeBracket = \strpos( $ip, ']' );
			if ( $closeBracket !== false ) {
				return \substr( $ip, 1, $closeBracket - 1 );
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
	public static function isValid( string $ip ): bool {
		return \filter_var( $ip, FILTER_VALIDATE_IP ) !== false;
	}

	/**
	 * Validate whether a string is valid CIDR notation (address/prefix).
	 */
	public static function isValidCidr( string $cidr ): bool {
		if ( ! \str_contains( $cidr, '/' ) ) {
			return self::isValid( $cidr );
		}

		[$address, $prefix] = \explode( '/', $cidr, 2 );
		if ( ! \ctype_digit( $prefix ) ) {
			return false;
		}

		$prefixLen = (int) $prefix;
		$packed    = @\inet_pton( $address );
		if ( $packed === false ) {
			return false;
		}

		$maxBits = \strlen( $packed ) * 8;

		return $prefixLen >= 0 && $prefixLen <= $maxBits;
	}
}
