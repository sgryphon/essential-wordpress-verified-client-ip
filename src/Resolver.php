<?php

declare(strict_types=1);

namespace VerifiedClientIp;

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
 *   The algorithm verifies up to $forwardLimit addresses as trusted proxies, then
 *   returns the next address as the verified client IP WITHOUT checking whether
 *   it is trusted (since it's beyond the trust boundary).
 *
 *   If an untrusted or malformed address is encountered before the limit, it is
 *   returned immediately as the verified client IP (normal case: found the client).
 */
final class Resolver
{
    /**
     * Resolve the verified client IP from server variables and scheme configuration.
     *
     * @param array<string, string|array<string>> $serverVars   $_SERVER superglobal (or mock).
     * @param array<Scheme>                       $schemes      Ordered list of schemes (highest priority first).
     * @param int                                 $forwardLimit Maximum number of proxy hops to verify (default 1).
     *
     * @return ResolverResult The resolution result with step trace.
     */
    public function resolve(array $serverVars, array $schemes, int $forwardLimit = 1): ResolverResult
    {
        $remoteAddr = (string) ($serverVars['REMOTE_ADDR'] ?? '');
        $steps = [];
        $protoInfo = [];

        // Validate REMOTE_ADDR.
        $normalised = IpUtils::normalise($remoteAddr);

        if ($normalised === null) {
            $steps[] = new ResolverStep(
                step: 1,
                address: $remoteAddr,
                normalisedAddress: null,
                matchedScheme: null,
                headerUsed: null,
                action: 'REMOTE_ADDR is invalid — no-op',
            );

            return new ResolverResult(
                resolvedIp: $remoteAddr,
                originalIp: $remoteAddr,
                changed: false,
                steps: $steps,
            );
        }

        $enabledSchemes = \array_values(
            \array_filter($schemes, static fn (Scheme $s): bool => $s->enabled),
        );

        // Find the first scheme where REMOTE_ADDR is a trusted proxy AND the
        // scheme's header is present.  If a proxy address matches a scheme but
        // no corresponding header exists, that scheme is skipped so the next
        // matching scheme (with a present header) can be tried.
        $matchedScheme = $this->findMatchingSchemeWithHeader($normalised, $enabledSchemes, $serverVars);

        if ($matchedScheme === null) {
            $steps[] = new ResolverStep(
                step: 1,
                address: $remoteAddr,
                normalisedAddress: $normalised,
                matchedScheme: null,
                headerUsed: null,
                action: 'REMOTE_ADDR is not a trusted proxy — no-op',
            );

            return new ResolverResult(
                resolvedIp: $remoteAddr,
                originalIp: $remoteAddr,
                changed: false,
                steps: $steps,
            );
        }

        $steps[] = new ResolverStep(
            step: 1,
            address: $remoteAddr,
            normalisedAddress: $normalised,
            matchedScheme: $matchedScheme->name,
            headerUsed: $matchedScheme->header,
            action: \sprintf('REMOTE_ADDR matches scheme "%s"', $matchedScheme->name),
        );

        // Extract proto/host information from the matched scheme's headers.
        $protoInfo = $this->extractProtoInfo($serverVars, $matchedScheme);

        // Parse the header addresses (returned rightmost-first by HeaderParser).
        $headerKey = $this->serverVarKey($matchedScheme->header);
        $headerValue = $serverVars[$headerKey] ?? '';
        $addresses = HeaderParser::parse($headerValue, $matchedScheme->token);

        if ($addresses === []) {
            $steps[] = new ResolverStep(
                step: \count($steps) + 1,
                address: $remoteAddr,
                normalisedAddress: $normalised,
                matchedScheme: $matchedScheme->name,
                headerUsed: $matchedScheme->header,
                action: \sprintf('Header "%s" parsed to empty list — no-op', $matchedScheme->header),
            );

            return new ResolverResult(
                resolvedIp: $remoteAddr,
                originalIp: $remoteAddr,
                changed: false,
                steps: $steps,
                proto: $protoInfo,
            );
        }

        // ---------------------------------------------------------------
        // Walk the proxy chain.
        //
        // forwardCount starts at 1: REMOTE_ADDR → server is the first forward.
        // We iterate through the header addresses (rightmost first) and for
        // each one:
        //   1. If forwardCount >= forwardLimit  → return the address as-is
        //      (the trust boundary is reached; we don't check further).
        //   2. If the address is malformed       → return it (untrusted).
        //   3. If the address is not a trusted proxy → return it (client).
        //   4. If trusted                        → forwardCount++, continue.
        //
        // When a trusted address matches a *different* scheme, we switch to
        // that scheme's header and restart the address walk.
        // ---------------------------------------------------------------

        $forwardCount = 1;
        $addressIndex = 0;
        $currentScheme = $matchedScheme;
        $lastTrustedAddress = null;

        while ($addressIndex < \count($addresses)) {
            $nextAddress = $addresses[$addressIndex];
            $addressIndex++;

            $normalisedNext = IpUtils::normalise($nextAddress);

            // --- Check 1: Forward Limit reached ---
            // Return this address without checking whether it is trusted.
            if ($forwardCount >= $forwardLimit) {
                $resolvedIp = $normalisedNext ?? $nextAddress;
                $steps[] = new ResolverStep(
                    step: \count($steps) + 1,
                    address: $nextAddress,
                    normalisedAddress: $normalisedNext,
                    matchedScheme: null,
                    headerUsed: $currentScheme->header,
                    action: \sprintf(
                        'Forward limit (%d) reached — "%s" is verified client IP',
                        $forwardLimit,
                        $resolvedIp,
                    ),
                );

                return new ResolverResult(
                    resolvedIp: $resolvedIp,
                    originalIp: $remoteAddr,
                    changed: true,
                    steps: $steps,
                    proto: $protoInfo,
                );
            }

            // --- Check 2: Malformed address ---
            if ($normalisedNext === null) {
                $steps[] = new ResolverStep(
                    step: \count($steps) + 1,
                    address: $nextAddress,
                    normalisedAddress: null,
                    matchedScheme: null,
                    headerUsed: $currentScheme->header,
                    action: \sprintf('Malformed address "%s" — treated as verified client IP', $nextAddress),
                );

                return new ResolverResult(
                    resolvedIp: $nextAddress,
                    originalIp: $remoteAddr,
                    changed: true,
                    steps: $steps,
                    proto: $protoInfo,
                );
            }

            // --- Check 3: Untrusted address (= the real client) ---
            $nextScheme = $this->findMatchingScheme($normalisedNext, $enabledSchemes);

            if ($nextScheme === null) {
                $steps[] = new ResolverStep(
                    step: \count($steps) + 1,
                    address: $nextAddress,
                    normalisedAddress: $normalisedNext,
                    matchedScheme: null,
                    headerUsed: $currentScheme->header,
                    action: \sprintf('Address "%s" is not a trusted proxy — verified client IP', $normalisedNext),
                );

                return new ResolverResult(
                    resolvedIp: $normalisedNext,
                    originalIp: $remoteAddr,
                    changed: true,
                    steps: $steps,
                    proto: $protoInfo,
                );
            }

            // --- Check 4: Trusted proxy — continue traversal ---
            $forwardCount++;
            $lastTrustedAddress = $normalisedNext;

            $steps[] = new ResolverStep(
                step: \count($steps) + 1,
                address: $nextAddress,
                normalisedAddress: $normalisedNext,
                matchedScheme: $nextScheme->name,
                headerUsed: $currentScheme->header,
                action: \sprintf(
                    'Address "%s" matches scheme "%s" — continue traversal',
                    $normalisedNext,
                    $nextScheme->name,
                ),
            );

            // If the matching scheme changed, switch to the new scheme's header.
            if ($nextScheme->name !== $currentScheme->name) {
                $newHeaderKey = $this->serverVarKey($nextScheme->header);
                $newHeaderValue = $serverVars[$newHeaderKey] ?? null;

                if ($newHeaderValue !== null && $newHeaderValue !== '' && $newHeaderValue !== []) {
                    $newAddresses = HeaderParser::parse($newHeaderValue, $nextScheme->token);

                    if ($newAddresses !== []) {
                        $currentScheme = $nextScheme;
                        $addresses = $newAddresses;
                        $addressIndex = 0;
                    }
                    // If the new header parses to empty, continue with current header.
                }
                // If the new header is absent, continue with current header.
            }
        }

        // All addresses exhausted and all were trusted.
        // Return the outermost (leftmost in original header = last in rightmost-first list).
        if ($lastTrustedAddress !== null) {
            $steps[] = new ResolverStep(
                step: \count($steps) + 1,
                address: $lastTrustedAddress,
                normalisedAddress: $lastTrustedAddress,
                matchedScheme: null,
                headerUsed: $currentScheme->header,
                action: 'All addresses trusted — using outermost forwarded address',
            );

            return new ResolverResult(
                resolvedIp: $lastTrustedAddress,
                originalIp: $remoteAddr,
                changed: true,
                steps: $steps,
                proto: $protoInfo,
            );
        }

        // Fallback: no change.
        return new ResolverResult(
            resolvedIp: $remoteAddr,
            originalIp: $remoteAddr,
            changed: false,
            steps: $steps,
            proto: $protoInfo,
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
     * @param array<string, string|array<string>>  $serverVars Server variables.
     */
    private function findMatchingSchemeWithHeader(string $ip, array $schemes, array $serverVars): ?Scheme
    {
        foreach ($schemes as $scheme) {
            if (IpUtils::matchesAny($ip, $scheme->proxies)) {
                $headerKey = $this->serverVarKey($scheme->header);
                $headerValue = $serverVars[$headerKey] ?? null;

                if ($headerValue !== null && $headerValue !== '' && $headerValue !== []) {
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
    private function findMatchingScheme(string $ip, array $schemes): ?Scheme
    {
        foreach ($schemes as $scheme) {
            if (IpUtils::matchesAny($ip, $scheme->proxies)) {
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
    private function serverVarKey(string $header): string
    {
        $header = \strtoupper($header);
        $header = \str_replace('-', '_', $header);

        if (!\str_starts_with($header, 'HTTP_')) {
            $header = 'HTTP_' . $header;
        }

        return $header;
    }

    /**
     * Extract proto and host information from proxy headers.
     *
     * @param array<string, string|array<string>> $serverVars
     *
     * @return array<string, string|null>
     */
    private function extractProtoInfo(array $serverVars, Scheme $scheme): array
    {
        $info = [];

        // For RFC 7239 Forwarded header with token-based parsing.
        if ($scheme->token !== null) {
            $headerKey = $this->serverVarKey($scheme->header);
            $headerValue = $serverVars[$headerKey] ?? null;

            if ($headerValue !== null && $headerValue !== '' && $headerValue !== []) {
                $protos = HeaderParser::parse($headerValue, 'proto');
                $info['proto'] = $protos !== [] ? $protos[0] : null;

                $hosts = HeaderParser::parse($headerValue, 'host');
                $info['host'] = $hosts !== [] ? $hosts[0] : null;
            }
        } else {
            // For X-Forwarded-* style headers, check related headers.
            $protoValue = $serverVars['HTTP_X_FORWARDED_PROTO'] ?? null;
            if (\is_string($protoValue) && $protoValue !== '') {
                $info['proto'] = \trim($protoValue);
            }

            $hostValue = $serverVars['HTTP_X_FORWARDED_HOST'] ?? null;
            if (\is_string($hostValue) && $hostValue !== '') {
                $info['host'] = \trim($hostValue);
            }
        }

        return $info;
    }
}
