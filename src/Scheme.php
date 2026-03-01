<?php

declare(strict_types=1);

namespace VerifiedClientIp;

/**
 * Represents a forwarding scheme configuration.
 *
 * Each scheme defines a set of trusted proxy addresses, the header to read
 * forwarded addresses from, and an optional token for structured headers.
 */
final class Scheme {

	/**
	 * @param string        $name    Human-readable name (e.g. "RFC 7239 Forwarded").
	 * @param bool          $enabled Whether this scheme is active.
	 * @param array<string> $proxies Trusted proxy IP addresses or CIDR ranges.
	 * @param string        $header  The HTTP header name to read (e.g. "Forwarded", "X-Forwarded-For").
	 * @param string|null   $token   Token to extract from structured headers (e.g. "for"). Null for plain lists.
	 * @param string        $notes   Optional notes / comments.
	 */
	public function __construct(
		public readonly string $name,
		public readonly bool $enabled,
		public readonly array $proxies,
		public readonly string $header,
		public readonly ?string $token = null,
		public readonly string $notes = '',
	) {}

	/**
	 * Create a Scheme from an associative array.
	 *
	 * @param array<string, mixed> $data
	 */
	public static function from_array( array $data ): self {
		return new self(
			name: (string) ( $data['name'] ?? '' ),
			enabled: (bool) ( $data['enabled'] ?? false ),
			proxies: (array) ( $data['proxies'] ?? [] ),
			header: (string) ( $data['header'] ?? '' ),
			token: isset( $data['token'] ) && '' !== $data['token'] ? (string) $data['token'] : null,
			notes: (string) ( $data['notes'] ?? '' ),
		);
	}

	/**
	 * Convert to an associative array.
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return [
			'name'    => $this->name,
			'enabled' => $this->enabled,
			'proxies' => $this->proxies,
			'header'  => $this->header,
			'token'   => $this->token,
			'notes'   => $this->notes,
		];
	}
}
