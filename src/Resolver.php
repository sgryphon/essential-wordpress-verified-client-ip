<?php

declare(strict_types=1);

namespace Gryphon\VerifiedClientIp;

/**
 * Core IP resolution algorithm.
 *
 * Traverses trusted proxy hops to determine the verified client IP address.
 *
 * Forward Limit semantics:
 *   The Forward Limit sets the maximum number of trusted proxy hops between client
 *   and server. REMOTE_ADDR counts as the first forward (proxy → server). Each
 *   subsequent trusted address in the header chain counts as another forward.
 *
 *   The algorithm verifies up to $forward_limit addresses as trusted proxies, then
 *   returns the next address as the verified client IP WITHOUT checking whether
 *   it is trusted (since it's beyond the trust boundary).
 *
 *   If an untrusted or malformed address is encountered before the limit, it is
 *   returned immediately as the verified client IP (normal case: found the client).
 */
final class Resolver {

	/**
	 * Resolve the verified client IP from server variables and scheme configuration.
	 *
	 * @param array<string, string|array<string>> $server_vars   $_SERVER superglobal (or mock).
	 * @param array<Scheme>                       $schemes      Ordered list of schemes (highest priority first).
	 * @param int                                 $forward_limit Maximum number of proxy hops to verify (default 1).
	 *
	 * @return ResolverResult The resolution result with step trace.
	 */
	public function resolve( array $server_vars, array $schemes, int $forward_limit = 1 ): ResolverResult {
		$raw_addr    = $server_vars['REMOTE_ADDR'] ?? '';
		$remote_addr = \is_array( $raw_addr ) ? (string) ( $raw_addr[0] ?? '' ) : (string) $raw_addr;
		$steps       = [];
		$proto_info  = [];

		// Validate REMOTE_ADDR.
		$normalised = IpUtils::normalise( $remote_addr );

		if ( null === $normalised ) {
			$steps[] = new ResolverStep(
				step: 1,
				address: $remote_addr,
				normalised_address: null,
				matched_scheme: null,
				header_used: null,
				action: \sprintf( 'REMOTE_ADDR "%s" is invalid — no-op', $normalised ),
			);

			return new ResolverResult(
				resolved_ip: $remote_addr,
				original_ip: $remote_addr,
				changed: false,
				steps: $steps,
			);
		}

		$enabled_schemes = \array_values(
			\array_filter( $schemes, static fn ( Scheme $s ): bool => $s->enabled ),
		);

		// Find the first scheme where REMOTE_ADDR is a trusted proxy AND the
		// scheme's header is present.  If a proxy address matches a scheme but
		// no corresponding header exists, that scheme is skipped so the next
		// matching scheme (with a present header) can be tried.
		$matched_scheme = $this->find_matching_scheme_with_header( $normalised, $enabled_schemes, $server_vars );

		if ( null === $matched_scheme ) {
			$steps[] = new ResolverStep(
				step: 1,
				address: $remote_addr,
				normalised_address: $normalised,
				matched_scheme: null,
				header_used: null,
				action: \sprintf( 'REMOTE_ADDR "%s" is not a trusted proxy — no-op', $normalised ),
			);

			return new ResolverResult(
				resolved_ip: $remote_addr,
				original_ip: $remote_addr,
				changed: false,
				steps: $steps,
			);
		}

		$steps[] = new ResolverStep(
			step: 1,
			address: $remote_addr,
			normalised_address: $normalised,
			matched_scheme: $matched_scheme->name,
			header_used: $matched_scheme->header,
			action: \sprintf( 'REMOTE_ADDR "%s" matches scheme "%s"', $normalised, $matched_scheme->name ),
		);

		// Extract proto/host information from the matched scheme's headers.
		$proto_info = $this->extract_proto_info( $server_vars, $matched_scheme );

		// Parse the header addresses (returned rightmost-first by HeaderParser).
		$header_key   = $this->server_var_key( $matched_scheme->header );
		$header_value = $server_vars[ $header_key ] ?? '';
		$addresses    = HeaderParser::parse( $header_value, $matched_scheme->token );

		if ( [] === $addresses ) {
			$steps[] = new ResolverStep(
				step: \count( $steps ) + 1,
				address: $remote_addr,
				normalised_address: $normalised,
				matched_scheme: $matched_scheme->name,
				header_used: $matched_scheme->header,
				action: \sprintf( 'Header "%s" parsed to empty list — no-op', $matched_scheme->header ),
			);

			return new ResolverResult(
				resolved_ip: $remote_addr,
				original_ip: $remote_addr,
				changed: false,
				steps: $steps,
				proto: $proto_info,
			);
		}

		// ---------------------------------------------------------------
		// Walk the proxy chain.
		//
		// forwardCount starts at 1: REMOTE_ADDR → server is the first forward.
		// We iterate through the header addresses (rightmost first) and for
		// each one:
		//   1. If forwardCount >= forward_limit  → return the address as-is
		//      (the trust boundary is reached; we don't check further).
		//   2. If the address is malformed       → return it (untrusted).
		//   3. If the address is not a trusted proxy → return it (client).
		//   4. If trusted                        → forwardCount++, continue.
		//
		// When a trusted address matches a *different* scheme, we switch to
		// that scheme's header and restart the address walk.
		// ---------------------------------------------------------------

		$forward_count        = 1;
		$address_index        = 0;
		$current_scheme       = $matched_scheme;
		$last_trusted_address = null;

		while ( $address_index < \count( $addresses ) ) {
			$next_address = $addresses[ $address_index ];
			++$address_index;

			$normalised_next = IpUtils::normalise( $next_address );

			// --- Check 1: Forward Limit reached ---
			// Return this address without checking whether it is trusted.
			if ( $forward_count >= $forward_limit ) {
				$resolved_ip = $normalised_next ?? $next_address;
				$steps[]     = new ResolverStep(
					step: \count( $steps ) + 1,
					address: $next_address,
					normalised_address: $normalised_next,
					matched_scheme: null,
					header_used: $current_scheme->header,
					action: \sprintf(
						'Forward limit (%d) reached — "%s" is verified client IP',
						$forward_limit,
						$resolved_ip,
					),
				);

				return new ResolverResult(
					resolved_ip: $resolved_ip,
					original_ip: $remote_addr,
					changed: true,
					steps: $steps,
					proto: $proto_info,
				);
			}

			// --- Check 2: Malformed address ---
			if ( null === $normalised_next ) {
				$steps[] = new ResolverStep(
					step: \count( $steps ) + 1,
					address: $next_address,
					normalised_address: null,
					matched_scheme: null,
					header_used: $current_scheme->header,
					action: \sprintf( 'Malformed address "%s" — treated as verified client IP', $next_address ),
				);

				return new ResolverResult(
					resolved_ip: $next_address,
					original_ip: $remote_addr,
					changed: true,
					steps: $steps,
					proto: $proto_info,
				);
			}

			// --- Check 3: Untrusted address (= the real client) ---
			$next_scheme = $this->find_matching_scheme( $normalised_next, $enabled_schemes );

			if ( null === $next_scheme ) {
				$steps[] = new ResolverStep(
					step: \count( $steps ) + 1,
					address: $next_address,
					normalised_address: $normalised_next,
					matched_scheme: null,
					header_used: $current_scheme->header,
					action: \sprintf( 'Address "%s" is not a trusted proxy — verified client IP', $normalised_next ),
				);

				return new ResolverResult(
					resolved_ip: $normalised_next,
					original_ip: $remote_addr,
					changed: true,
					steps: $steps,
					proto: $proto_info,
				);
			}

			// --- Check 4: Trusted proxy — continue traversal ---
			++$forward_count;
			$last_trusted_address = $normalised_next;

			$steps[] = new ResolverStep(
				step: \count( $steps ) + 1,
				address: $next_address,
				normalised_address: $normalised_next,
				matched_scheme: $next_scheme->name,
				header_used: $current_scheme->header,
				action: \sprintf(
					'Address "%s" matches scheme "%s" — continue traversal',
					$normalised_next,
					$next_scheme->name,
				),
			);

			// If the matching scheme changed, switch to the new scheme's header.
			if ( $next_scheme->name !== $current_scheme->name ) {
				$new_header_key   = $this->server_var_key( $next_scheme->header );
				$new_header_value = $server_vars[ $new_header_key ] ?? null;

				if ( null !== $new_header_value && '' !== $new_header_value && [] !== $new_header_value ) {
					$new_addresses = HeaderParser::parse( $new_header_value, $next_scheme->token );

					if ( [] !== $new_addresses ) {
						$current_scheme = $next_scheme;
						$addresses      = $new_addresses;
						$address_index  = 0;
					}
					// If the new header parses to empty, continue with current header.
				}
				// If the new header is absent, continue with current header.
			}
		}

		// All addresses exhausted and all were trusted.
		// Return the outermost (leftmost in original header = last in rightmost-first list).
		$steps[] = new ResolverStep(
			step: \count( $steps ) + 1,
			address: $last_trusted_address,
			normalised_address: $last_trusted_address,
			matched_scheme: null,
			header_used: $current_scheme->header,
			action: 'All addresses trusted — using outermost forwarded address',
		);

		return new ResolverResult(
			resolved_ip: $last_trusted_address,
			original_ip: $remote_addr,
			changed: true,
			steps: $steps,
			proto: $proto_info,
		);
	}

	/**
	 * Find the first enabled scheme that matches an IP AND whose header is present
	 * in the server variables.
	 *
	 * This is used for the initial REMOTE_ADDR check: a scheme only "matches" if
	 * we can actually read its header.  This avoids the problem of a higher-priority
	 * scheme (e.g. RFC 7239 Forwarded) preventing a lower-priority scheme
	 * (e.g. X-Forwarded-For) from being used when both share the same proxy ranges
	 * but only one header is present.
	 *
	 * @param string                               $ip         Normalised IP address.
	 * @param array<Scheme>                        $schemes    Enabled schemes in priority order.
	 * @param array<string, string|array<string>>  $server_vars Server variables.
	 */
	private function find_matching_scheme_with_header( string $ip, array $schemes, array $server_vars ): ?Scheme {
		foreach ( $schemes as $scheme ) {
			if ( IpUtils::matches_any( $ip, $scheme->proxies ) ) {
				$header_key   = $this->server_var_key( $scheme->header );
				$header_value = $server_vars[ $header_key ] ?? null;

				if ( null !== $header_value && '' !== $header_value && [] !== $header_value ) {
					return $scheme;
				}
			}
		}

		return null;
	}

	/**
	 * Find the first enabled scheme whose proxy list matches the given IP.
	 *
	 * Used during chain traversal where we only need to know if an address is
	 * trusted (the header switch is handled separately).
	 *
	 * @param string        $ip      Normalised IP address.
	 * @param array<Scheme> $schemes Enabled schemes in priority order.
	 */
	private function find_matching_scheme( string $ip, array $schemes ): ?Scheme {
		foreach ( $schemes as $scheme ) {
			if ( IpUtils::matches_any( $ip, $scheme->proxies ) ) {
				return $scheme;
			}
		}

		return null;
	}

	/**
	 * Convert a header name to the $_SERVER key format.
	 *
	 * e.g. "X-Forwarded-For" -> "HTTP_X_FORWARDED_FOR"
	 *      "Forwarded"       -> "HTTP_FORWARDED"
	 *      "CF-Connecting-IP" -> "HTTP_CF_CONNECTING_IP"
	 *
	 * If the header already starts with HTTP_, it's returned as-is (uppercased).
	 */
	private function server_var_key( string $header ): string {
		$header = \strtoupper( $header );
		$header = \str_replace( '-', '_', $header );

		if ( ! \str_starts_with( $header, 'HTTP_' ) ) {
			$header = 'HTTP_' . $header;
		}

		return $header;
	}

	/**
	 * Extract proto and host information from proxy headers.
	 *
	 * @param array<string, string|array<string>> $server_vars
	 *
	 * @return array<string, string|null>
	 */
	private function extract_proto_info( array $server_vars, Scheme $scheme ): array {
		$info = [];

		// For RFC 7239 Forwarded header with token-based parsing.
		if ( null !== $scheme->token ) {
			$header_key   = $this->server_var_key( $scheme->header );
			$header_value = $server_vars[ $header_key ] ?? null;

			if ( null !== $header_value && '' !== $header_value && [] !== $header_value ) {
				$protos        = HeaderParser::parse( $header_value, 'proto' );
				$info['proto'] = [] !== $protos ? $protos[0] : null;

				$hosts        = HeaderParser::parse( $header_value, 'host' );
				$info['host'] = [] !== $hosts ? $hosts[0] : null;
			}
		} else {
			// For X-Forwarded-* style headers, check related headers.
			$proto_value = $server_vars['HTTP_X_FORWARDED_PROTO'] ?? null;
			if ( \is_string( $proto_value ) && '' !== $proto_value ) {
				$info['proto'] = \trim( $proto_value );
			}

			$host_value = $server_vars['HTTP_X_FORWARDED_HOST'] ?? null;
			if ( \is_string( $host_value ) && '' !== $host_value ) {
				$info['host'] = \trim( $host_value );
			}
		}

		return $info;
	}
}
