<?php

declare(strict_types=1);

namespace VerifiedClientIp;

/**
 * Plugin settings — data model, persistence via the WordPress Options API,
 * and input validation.
 *
 * All settings are stored in a single option: `vcip_settings`.
 */
final class Settings {

	/** WordPress option key. */
	public const OPTION_KEY = 'vcip_settings';

	/** Absolute minimum for forward limit. */
	public const FORWARD_LIMIT_MIN = 1;

	/** Absolute maximum for forward limit. */
	public const FORWARD_LIMIT_MAX = 20;

	/** Maximum length for a scheme name. */
	public const SCHEME_NAME_MAX_LENGTH = 80;

	/** Maximum length for a header name. */
	public const HEADER_NAME_MAX_LENGTH = 80;

	/** Maximum length for scheme notes. */
	public const NOTES_MAX_LENGTH = 500;

	/** Maximum number of proxy entries per scheme. */
	public const MAX_PROXIES_PER_SCHEME = 200;

	/** Maximum number of schemes. */
	public const MAX_SCHEMES = 20;

	/**
	 * @param bool          $enabled       Master enable/disable switch.
	 * @param int           $forward_limit  Maximum proxy hops to traverse (1–20).
	 * @param bool          $process_proto  Extract and apply proto (HTTPS/scheme) from headers.
	 * @param bool          $process_host   Extract and apply host from headers.
	 * @param array<Scheme> $schemes       Ordered list of forwarding schemes.
	 */
	public function __construct(
		public readonly bool $enabled = true,
		public readonly int $forward_limit = 1,
		public readonly bool $process_proto = true,
		public readonly bool $process_host = false,
		public readonly array $schemes = [],
	) {}

	// ------------------------------------------------------------------
	// Factory helpers
	// ------------------------------------------------------------------

	/**
	 * Return factory defaults (with the three default schemes).
	 */
	public static function defaults(): self {
		return new self(
			enabled: true,
			forward_limit: 1,
			process_proto: true,
			process_host: false,
			schemes: self::default_schemes(),
		);
	}

	/**
	 * Load settings from the WordPress Options API.
	 *
	 * Falls back to factory defaults when no option is stored yet.
	 */
	public static function load(): self {
		if ( ! \function_exists( 'get_option' ) ) {
			return self::defaults();
		}

		/** @var array<string, mixed>|false $raw */
		$raw = \get_option( self::OPTION_KEY, false );

		if ( ! \is_array( $raw ) ) {
			return self::defaults();
		}

		return self::from_array( $raw );
	}

	/**
	 * Reconstruct Settings from an associative array (e.g. stored option).
	 *
	 * @param array<string, mixed> $data
	 */
	public static function from_array( array $data ): self {
		$scheme_defs = $data['schemes'] ?? [];
		$schemes     = [];

		if ( \is_array( $scheme_defs ) ) {
			foreach ( $scheme_defs as $def ) {
				if ( \is_array( $def ) ) {
					$schemes[] = Scheme::from_array( $def );
				}
			}
		}

		// Fall back to default schemes when none are stored.
		if ( [] === $schemes ) {
			$schemes = self::default_schemes();
		}

		return new self(
			enabled: (bool) ( $data['enabled'] ?? true ),
			forward_limit: self::clamp_forward_limit(
				\is_numeric( $data['forward_limit'] ?? null )
					? (int) $data['forward_limit']
					: 1,
			),
			process_proto: (bool) ( $data['process_proto'] ?? true ),
			process_host: (bool) ( $data['process_host'] ?? false ),
			schemes: $schemes,
		);
	}

	/**
	 * Serialise to an associative array suitable for storage.
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return [
			'enabled'       => $this->enabled,
			'forward_limit' => $this->forward_limit,
			'process_proto' => $this->process_proto,
			'process_host'  => $this->process_host,
			'schemes'       => \array_map(
				static fn ( Scheme $s ): array => $s->to_array(),
				$this->schemes,
			),
		];
	}

	/**
	 * Persist current settings to the WordPress Options API.
	 */
	public function save(): void {
		if ( \function_exists( 'update_option' ) ) {
			\update_option( self::OPTION_KEY, $this->to_array() );
		}
	}

	// ------------------------------------------------------------------
	// Default schemes per specification
	// ------------------------------------------------------------------

	/**
	 * Return the three default scheme definitions.
	 *
	 * @return array<Scheme>
	 */
	public static function default_schemes(): array {
		$private_proxies = [
			'10.0.0.0/8',
			'172.16.0.0/12',
			'192.168.0.0/16',
			'127.0.0.0/8',
			'::1/128',
			'fc00::/7',
		];

		return [
			new Scheme(
				name: 'RFC 7239 Forwarded',
				enabled: true,
				proxies: $private_proxies,
				header: 'Forwarded',
				token: 'for',
				notes: 'Standard RFC 7239 Forwarded header using the "for" token.',
			),
			new Scheme(
				name: 'X-Forwarded-For',
				enabled: true,
				proxies: $private_proxies,
				header: 'X-Forwarded-For',
				notes: 'Legacy X-Forwarded-For header, comma-separated addresses.',
			),
			new Scheme(
				name: 'Cloudflare',
				enabled: false,
				proxies: [
					// IPv4
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
					// IPv6
					'2400:cb00::/32',
					'2606:4700::/32',
					'2803:f800::/32',
					'2405:b500::/32',
					'2405:8100::/32',
					'2a06:98c0::/29',
					'2c0f:f248::/32',
				],
				header: 'CF-Connecting-IP',
				notes: 'Cloudflare proxy header. Verify ranges at https://www.cloudflare.com/ips-v4/ and https://www.cloudflare.com/ips-v6/',
			),
		];
	}

	// ------------------------------------------------------------------
	// Validation
	// ------------------------------------------------------------------

	/**
	 * Validate raw user input and return a sanitised Settings plus any errors.
	 *
	 * @param array<string, mixed> $input Raw form / API input.
	 *
	 * @return array{settings: self, errors: array<string>}
	 */
	public static function validate( array $input ): array {
		$errors = [];

		// --- enabled ---
		$enabled = (bool) ( $input['enabled'] ?? true );

		// --- forward_limit ---
		$raw_limit = $input['forward_limit'] ?? 1;
		if ( ! \is_numeric( $raw_limit ) ) {
			$errors[]      = 'Forward Limit must be a number.';
			$forward_limit = 1;
		} else {
			$forward_limit = (int) $raw_limit;
			if ( $forward_limit < self::FORWARD_LIMIT_MIN || $forward_limit > self::FORWARD_LIMIT_MAX ) {
				$errors[]      = \sprintf(
					'Forward Limit must be between %d and %d.',
					self::FORWARD_LIMIT_MIN,
					self::FORWARD_LIMIT_MAX,
				);
				$forward_limit = self::clamp_forward_limit( $forward_limit );
			}
		}

		// --- process_proto / process_host ---
		$process_proto = (bool) ( $input['process_proto'] ?? true );
		$process_host  = (bool) ( $input['process_host'] ?? false );

		// --- schemes ---
		$raw_schemes = $input['schemes'] ?? [];
		$schemes     = [];

		if ( \is_array( $raw_schemes ) ) {
			if ( \count( $raw_schemes ) > self::MAX_SCHEMES ) {
				$errors[]    = \sprintf( 'Maximum of %d schemes allowed.', self::MAX_SCHEMES );
				$raw_schemes = \array_slice( $raw_schemes, 0, self::MAX_SCHEMES );
			}

			foreach ( $raw_schemes as $i => $raw_scheme ) {
				if ( ! \is_array( $raw_scheme ) ) {
					$errors[] = \sprintf( 'Scheme #%d: invalid data.', $i + 1 );
					continue;
				}

				$scheme_result = self::validate_scheme( $raw_scheme, $i + 1 );
				$schemes[]     = $scheme_result['scheme'];

				foreach ( $scheme_result['errors'] as $err ) {
					$errors[] = $err;
				}
			}
		}

		// Fall back to defaults when no schemes provided.
		if ( [] === $schemes ) {
			$schemes = self::default_schemes();
		}

		return [
			'settings' => new self(
				enabled: $enabled,
				forward_limit: $forward_limit,
				process_proto: $process_proto,
				process_host: $process_host,
				schemes: $schemes,
			),
			'errors'   => $errors,
		];
	}

	/**
	 * Validate a single scheme definition.
	 *
	 * @param array<string, mixed> $raw        Raw scheme data.
	 * @param int                  $scheme_index 1-based index for error messages.
	 *
	 * @return array{scheme: Scheme, errors: array<string>}
	 */
	public static function validate_scheme( array $raw, int $scheme_index = 1 ): array {
		$errors = [];

		// --- name ---
		$name = self::sanitise_string( $raw['name'] ?? '', self::SCHEME_NAME_MAX_LENGTH );
		if ( '' === $name ) {
			$errors[] = \sprintf( 'Scheme #%d: name is required.', $scheme_index );
			$name     = \sprintf( 'Scheme %d', $scheme_index );
		}

		// --- enabled ---
		$enabled = (bool) ( $raw['enabled'] ?? false );

		// --- header ---
		$header = self::sanitise_header_name( $raw['header'] ?? '' );
		if ( '' === $header ) {
			$errors[] = \sprintf( 'Scheme #%d (%s): header name is required.', $scheme_index, $name );
		}

		// --- token ---
		$token = isset( $raw['token'] ) && \is_string( $raw['token'] ) && '' !== $raw['token']
			? self::sanitise_string( $raw['token'], self::HEADER_NAME_MAX_LENGTH )
			: null;

		// --- notes ---
		$notes = self::sanitise_string( $raw['notes'] ?? '', self::NOTES_MAX_LENGTH );

		// --- proxies ---
		$raw_proxies = $raw['proxies'] ?? [];
		$proxies     = [];

		if ( \is_string( $raw_proxies ) ) {
			// Accept newline- or comma-separated text.
			$raw_proxies = \preg_split( '/[\r\n,]+/', $raw_proxies, -1, \PREG_SPLIT_NO_EMPTY );
		}

		if ( \is_array( $raw_proxies ) ) {
			if ( \count( $raw_proxies ) > self::MAX_PROXIES_PER_SCHEME ) {
				$errors[]    = \sprintf(
					'Scheme #%d (%s): maximum of %d proxy entries allowed.',
					$scheme_index,
					$name,
					self::MAX_PROXIES_PER_SCHEME,
				);
				$raw_proxies = \array_slice( $raw_proxies, 0, self::MAX_PROXIES_PER_SCHEME );
			}

			foreach ( $raw_proxies as $proxy ) {
				$proxy = \trim( (string) $proxy );
				if ( '' === $proxy ) {
					continue;
				}

				$validation_result = self::validate_proxy( $proxy );
				if ( null === $validation_result ) {
					$errors[] = \sprintf(
						'Scheme #%d (%s): invalid proxy entry "%s".',
						$scheme_index,
						$name,
						$proxy,
					);
				} else {
					$proxies[] = $validation_result;
				}
			}
		}

		return [
			'scheme' => new Scheme(
				name: $name,
				enabled: $enabled,
				proxies: $proxies,
				header: $header,
				token: $token,
				notes: $notes,
			),
			'errors' => $errors,
		];
	}

	/**
	 * Validate a single proxy entry (IP address or CIDR range).
	 *
	 * Returns the normalised string on success, or null on failure.
	 */
	public static function validate_proxy( string $proxy ): ?string {
		$proxy = \trim( $proxy );

		// CIDR notation (e.g. 10.0.0.0/8, fc00::/7).
		if ( \str_contains( $proxy, '/' ) ) {
			$parts = \explode( '/', $proxy, 2 );
			$ip    = $parts[0];
			$bits  = $parts[1];

			if ( ! \is_numeric( $bits ) ) {
				return null;
			}

			$ip = self::normalise_ip_for_validation( $ip );
			if ( null === $ip ) {
				return null;
			}

			$is_v6 = \str_contains( $ip, ':' );
			$max   = $is_v6 ? 128 : 32;
			$b     = (int) $bits;

			if ( $b < 0 || $b > $max ) {
				return null;
			}

			return $ip . '/' . $b;
		}

		// Plain IP address.
		$ip = self::normalise_ip_for_validation( $proxy );

		return $ip;
	}

	/**
	 * Validate a header name.
	 *
	 * @return string Sanitised header name, or '' on failure.
	 */
	public static function sanitise_header_name( string $header ): string {
		$header = \trim( $header );
		$header = \substr( $header, 0, self::HEADER_NAME_MAX_LENGTH );

		// Header names: letters, digits, hyphens, underscores.
		if ( '' !== $header && ! \preg_match( '/^[A-Za-z0-9\-_]+$/', $header ) ) {
			return '';
		}

		return $header;
	}

	// ------------------------------------------------------------------
	// Internal helpers
	// ------------------------------------------------------------------

	/**
	 * Clamp forward limit to the allowed range.
	 */
	private static function clamp_forward_limit( int $value ): int {
		return \max( self::FORWARD_LIMIT_MIN, \min( self::FORWARD_LIMIT_MAX, $value ) );
	}

	/**
	 * Sanitise a plain string value (trim + truncate).
	 */
	private static function sanitise_string( mixed $value, int $max_length ): string {
		if ( ! \is_string( $value ) && ! \is_numeric( $value ) ) {
			return '';
		}

		$value = \trim( (string) $value );

		return \substr( $value, 0, $max_length );
	}

	/**
	 * Normalise an IP address for validation.
	 *
	 * Returns the canonical representation, or null if invalid.
	 */
	private static function normalise_ip_for_validation( string $ip ): ?string {
		// Strip brackets from IPv6 (e.g. [::1]).
		$ip = \trim( $ip, '[]' );

		// Use filter_var for basic validation.
		$filtered = \filter_var( $ip, \FILTER_VALIDATE_IP );
		if ( false === $filtered ) {
			return null;
		}

		// Normalise IPv6 to full canonical form then back to inet_ntop.
		$packed = \inet_pton( $filtered );
		if ( false === $packed ) {
			return null;
		}

		$normalised = \inet_ntop( $packed );

		return false !== $normalised ? $normalised : null;
	}
}
