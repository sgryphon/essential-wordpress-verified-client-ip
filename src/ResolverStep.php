<?php

declare(strict_types=1);

namespace Gryphon\VerifiedClientIp;

/**
 * One entry in the resolution step trace.
 */
final class ResolverStep {

	public function __construct(
		public readonly int $step,
		public readonly string $address,
		public readonly ?string $normalised_address,
		public readonly ?string $matched_scheme,
		public readonly ?string $header_used,
		public readonly string $action,
	) {}

	/**
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return [
			'step'               => $this->step,
			'address'            => $this->address,
			'normalised_address' => $this->normalised_address,
			'matched_scheme'     => $this->matched_scheme,
			'header_used'        => $this->header_used,
			'action'             => $this->action,
		];
	}
}
