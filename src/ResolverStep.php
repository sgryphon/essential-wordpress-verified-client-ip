<?php

declare(strict_types=1);

namespace VerifiedClientIp;

/**
 * One entry in the resolution step trace.
 */
final class ResolverStep
{
    public function __construct(
        public readonly int $step,
        public readonly string $address,
        public readonly ?string $normalisedAddress,
        public readonly ?string $matchedScheme,
        public readonly ?string $headerUsed,
        public readonly string $action,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'step' => $this->step,
            'address' => $this->address,
            'normalised_address' => $this->normalisedAddress,
            'matched_scheme' => $this->matchedScheme,
            'header_used' => $this->headerUsed,
            'action' => $this->action,
        ];
    }
}
