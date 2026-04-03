<?php

declare(strict_types=1);

namespace Essential\VerifiedClientIp;

/**
 * Parses proxy-forwarding HTTP headers into lists of IP addresses.
 *
 * Supports:
 * - RFC 7239 `Forwarded` header (token-based, e.g. for=1.2.3.4)
 * - `X-Forwarded-For` (comma-separated list)
 * - Single-value headers (e.g. CF-Connecting-IP)
 * - Multiple same-name headers concatenated per RFC 7230 §3.2.2
 */
final class HeaderParser {

	/**
	 * Extract IP addresses from a header value (or array of values).
	 *
	 * When a token is specified, the header is parsed as RFC 7239 structured
	 * data and only the matching token values are returned.
	 *
	 * When no token is specified, the header value is treated as a
	 * comma-separated list of addresses (X-Forwarded-For style) or a single
	 * address.
	 *
	 * Multiple header values (array input) are concatenated per RFC 7230 §3.2.2
	 * before parsing.
	 *
	 * Addresses are returned in **right-to-left** order (most recently added first).
	 *
	 * @param string|array<string> $header_value The raw header value(s).
	 * @param string|null          $token       Optional token to extract (e.g. "for").
	 *
	 * @return array<string> Extracted addresses, rightmost first.
	 */
	public static function parse( string|array $header_value, ?string $token = null ): array {
		// Concatenate multiple header values per RFC 7230 §3.2.2.
		if ( \is_array( $header_value ) ) {
			$header_value = \implode( ', ', $header_value );
		}

		$header_value = \trim( $header_value );
		if ( '' === $header_value ) {
			return [];
		}

		if ( null !== $token && '' !== $token ) {
			return self::parse_forwarded( $header_value, $token );
		}

		return self::parse_comma_separated( $header_value );
	}

	/**
	 * Parse an RFC 7239 `Forwarded` header, extracting values for a specific token.
	 *
	 * The header contains comma-separated forwarded-elements, each with
	 * semicolon-separated parameters (token=value pairs).
	 *
	 * Example: `for=192.0.2.1;proto=https, for=198.51.100.1`
	 *
	 * Quoted values are unquoted. Bracketed IPv6 notation is handled.
	 *
	 * @param string $header The concatenated Forwarded header value.
	 * @param string $token  The token to extract (e.g. "for").
	 *
	 * @return array<string> Extracted values, rightmost first.
	 */
	private static function parse_forwarded( string $header, string $token ): array {
		$addresses = [];
		$token     = \strtolower( $token );

		// Split into forwarded-elements (comma-separated).
		$elements = self::split_outside_quotes( $header, ',' );

		foreach ( $elements as $element ) {
			$element = \trim( $element );
			if ( '' === $element ) {
				continue;
			}

			// Each element has semicolon-separated parameters.
			$params = self::split_outside_quotes( $element, ';' );

			foreach ( $params as $param ) {
				$param = \trim( $param );
				if ( '' === $param ) {
					continue;
				}

				$eq_pos = \strpos( $param, '=' );
				if ( false === $eq_pos ) {
					continue;
				}

				$key   = \strtolower( \trim( \substr( $param, 0, $eq_pos ) ) );
				$value = \trim( \substr( $param, $eq_pos + 1 ) );

				if ( $key !== $token ) {
					continue;
				}

				// Unquote if quoted.
				$value = self::unquote( $value );

				$addresses[] = $value;
			}
		}

		// Return rightmost first.
		return \array_reverse( $addresses );
	}

	/**
	 * Parse a comma-separated list of IP addresses (X-Forwarded-For style).
	 *
	 * @return array<string> Addresses, rightmost first.
	 */
	private static function parse_comma_separated( string $header ): array {
		$parts     = \explode( ',', $header );
		$addresses = [];

		foreach ( $parts as $part ) {
			$part = \trim( $part );
			if ( '' !== $part ) {
				$addresses[] = $part;
			}
		}

		// Return rightmost first.
		return \array_reverse( $addresses );
	}

	/**
	 * Split a string by a delimiter, but not inside quoted strings.
	 *
	 * @return array<string>
	 */
	private static function split_outside_quotes( string $input, string $delimiter ): array {
		$result   = [];
		$current  = '';
		$in_quote = false;
		$length   = \strlen( $input );

		for ( $i = 0; $i < $length; $i++ ) {
			$char = $input[ $i ];

			if ( '"' === $char && ( 0 === $i || '\\' !== $input[ $i - 1 ] ) ) {
				$in_quote = ! $in_quote;
				$current .= $char;
			} elseif ( $char === $delimiter && ! $in_quote ) {
				$result[] = $current;
				$current  = '';
			} else {
				$current .= $char;
			}
		}

		$result[] = $current;

		return $result;
	}

	/**
	 * Remove surrounding quotes from a value and unescape.
	 */
	private static function unquote( string $value ): string {
		if ( \strlen( $value ) >= 2 && '"' === $value[0] && '"' === $value[-1] ) {
			$value = \substr( $value, 1, -1 );
			// Unescape backslash-escaped characters.
			$value = (string) \preg_replace( '/\\\\(.)/', '$1', $value );
		}

		return $value;
	}
}
