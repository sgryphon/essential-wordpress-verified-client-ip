<?php

declare(strict_types=1);

namespace Gryphon\VerifiedClientIp;

/**
 * Result of the IP resolution algorithm.
 */
final class ResolverResult {

	/**
	 * @param string               $resolved_ip  The verified client IP (or original REMOTE_ADDR if no-op).
	 * @param string               $original_ip  The original REMOTE_ADDR.
	 * @param bool                 $changed     Whether REMOTE_ADDR should be replaced.
	 * @param array<ResolverStep>  $steps       Step-by-step trace of the algorithm.
	 * @param array<string, string|null> $proto  Proto information if available (keys: proto, host).
	 */
	public function __construct(
		public readonly string $resolved_ip,
		public readonly string $original_ip,
		public readonly bool $changed,
		public readonly array $steps,
		public readonly array $proto = [],
	) {}
}
