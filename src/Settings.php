<?php

declare(strict_types=1);

namespace VerifiedClientIp;

/**
 * Plugin settings — data model, persistence via the WordPress Options API,
 * and input validation.
 *
 * All settings are stored in a single option: `vcip_settings`.
 */
final class Settings
{
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
     * @param int           $forwardLimit  Maximum proxy hops to traverse (1–20).
     * @param bool          $processProto  Extract and apply proto (HTTPS/scheme) from headers.
     * @param bool          $processHost   Extract and apply host from headers.
     * @param array<Scheme> $schemes       Ordered list of forwarding schemes.
     */
    public function __construct(
        public readonly bool $enabled = true,
        public readonly int $forwardLimit = 1,
        public readonly bool $processProto = true,
        public readonly bool $processHost = false,
        public readonly array $schemes = [],
    ) {}

    // ------------------------------------------------------------------
    // Factory helpers
    // ------------------------------------------------------------------

    /**
     * Return factory defaults (with the three default schemes).
     */
    public static function defaults(): self
    {
        return new self(
            enabled: true,
            forwardLimit: 1,
            processProto: true,
            processHost: false,
            schemes: self::defaultSchemes(),
        );
    }

    /**
     * Load settings from the WordPress Options API.
     *
     * Falls back to factory defaults when no option is stored yet.
     */
    public static function load(): self
    {
        if (!\function_exists('get_option')) {
            return self::defaults();
        }

        /** @var array<string, mixed>|false $raw */
        $raw = \get_option(self::OPTION_KEY, false);

        if (!\is_array($raw)) {
            return self::defaults();
        }

        return self::fromArray($raw);
    }

    /**
     * Reconstruct Settings from an associative array (e.g. stored option).
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $schemeDefs = $data['schemes'] ?? [];
        $schemes    = [];

        if (\is_array($schemeDefs)) {
            foreach ($schemeDefs as $def) {
                if (\is_array($def)) {
                    $schemes[] = Scheme::fromArray($def);
                }
            }
        }

        // Fall back to default schemes when none are stored.
        if ($schemes === []) {
            $schemes = self::defaultSchemes();
        }

        return new self(
            enabled: (bool) ($data['enabled'] ?? true),
            forwardLimit: self::clampForwardLimit(
                \is_numeric($data['forward_limit'] ?? null)
                    ? (int) $data['forward_limit']
                    : 1,
            ),
            processProto: (bool) ($data['process_proto'] ?? true),
            processHost: (bool) ($data['process_host'] ?? false),
            schemes: $schemes,
        );
    }

    /**
     * Serialise to an associative array suitable for storage.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'enabled' => $this->enabled,
            'forward_limit' => $this->forwardLimit,
            'process_proto' => $this->processProto,
            'process_host' => $this->processHost,
            'schemes' => \array_map(
                static fn (Scheme $s): array => $s->toArray(),
                $this->schemes,
            ),
        ];
    }

    /**
     * Persist current settings to the WordPress Options API.
     */
    public function save(): void
    {
        if (\function_exists('update_option')) {
            \update_option(self::OPTION_KEY, $this->toArray());
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
    public static function defaultSchemes(): array
    {
        $privateProxies = [
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
                proxies: $privateProxies,
                header: 'Forwarded',
                token: 'for',
                notes: 'Standard RFC 7239 Forwarded header using the "for" token.',
            ),
            new Scheme(
                name: 'X-Forwarded-For',
                enabled: true,
                proxies: $privateProxies,
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
    public static function validate(array $input): array
    {
        $errors = [];

        // --- enabled ---
        $enabled = (bool) ($input['enabled'] ?? true);

        // --- forward_limit ---
        $rawLimit = $input['forward_limit'] ?? 1;
        if (!\is_numeric($rawLimit)) {
            $errors[] = 'Forward Limit must be a number.';
            $forwardLimit = 1;
        } else {
            $forwardLimit = (int) $rawLimit;
            if ($forwardLimit < self::FORWARD_LIMIT_MIN || $forwardLimit > self::FORWARD_LIMIT_MAX) {
                $errors[] = \sprintf(
                    'Forward Limit must be between %d and %d.',
                    self::FORWARD_LIMIT_MIN,
                    self::FORWARD_LIMIT_MAX,
                );
                $forwardLimit = self::clampForwardLimit($forwardLimit);
            }
        }

        // --- process_proto / process_host ---
        $processProto = (bool) ($input['process_proto'] ?? true);
        $processHost  = (bool) ($input['process_host'] ?? false);

        // --- schemes ---
        $rawSchemes = $input['schemes'] ?? [];
        $schemes    = [];

        if (\is_array($rawSchemes)) {
            if (\count($rawSchemes) > self::MAX_SCHEMES) {
                $errors[] = \sprintf('Maximum of %d schemes allowed.', self::MAX_SCHEMES);
                $rawSchemes = \array_slice($rawSchemes, 0, self::MAX_SCHEMES);
            }

            foreach ($rawSchemes as $i => $rawScheme) {
                if (!\is_array($rawScheme)) {
                    $errors[] = \sprintf('Scheme #%d: invalid data.', $i + 1);
                    continue;
                }

                $schemeResult = self::validateScheme($rawScheme, $i + 1);
                $schemes[]    = $schemeResult['scheme'];

                foreach ($schemeResult['errors'] as $err) {
                    $errors[] = $err;
                }
            }
        }

        // Fall back to defaults when no schemes provided.
        if ($schemes === []) {
            $schemes = self::defaultSchemes();
        }

        return [
            'settings' => new self(
                enabled: $enabled,
                forwardLimit: $forwardLimit,
                processProto: $processProto,
                processHost: $processHost,
                schemes: $schemes,
            ),
            'errors' => $errors,
        ];
    }

    /**
     * Validate a single scheme definition.
     *
     * @param array<string, mixed> $raw        Raw scheme data.
     * @param int                  $schemeIndex 1-based index for error messages.
     *
     * @return array{scheme: Scheme, errors: array<string>}
     */
    public static function validateScheme(array $raw, int $schemeIndex = 1): array
    {
        $errors = [];

        // --- name ---
        $name = self::sanitiseString($raw['name'] ?? '', self::SCHEME_NAME_MAX_LENGTH);
        if ($name === '') {
            $errors[] = \sprintf('Scheme #%d: name is required.', $schemeIndex);
            $name = \sprintf('Scheme %d', $schemeIndex);
        }

        // --- enabled ---
        $enabled = (bool) ($raw['enabled'] ?? false);

        // --- header ---
        $header = self::sanitiseHeaderName($raw['header'] ?? '');
        if ($header === '') {
            $errors[] = \sprintf('Scheme #%d (%s): header name is required.', $schemeIndex, $name);
        }

        // --- token ---
        $token = isset($raw['token']) && \is_string($raw['token']) && $raw['token'] !== ''
            ? self::sanitiseString($raw['token'], self::HEADER_NAME_MAX_LENGTH)
            : null;

        // --- notes ---
        $notes = self::sanitiseString($raw['notes'] ?? '', self::NOTES_MAX_LENGTH);

        // --- proxies ---
        $rawProxies = $raw['proxies'] ?? [];
        $proxies    = [];

        if (\is_string($rawProxies)) {
            // Accept newline- or comma-separated text.
            $rawProxies = \preg_split('/[\r\n,]+/', $rawProxies, -1, \PREG_SPLIT_NO_EMPTY);
        }

        if (\is_array($rawProxies)) {
            if (\count($rawProxies) > self::MAX_PROXIES_PER_SCHEME) {
                $errors[] = \sprintf(
                    'Scheme #%d (%s): maximum of %d proxy entries allowed.',
                    $schemeIndex,
                    $name,
                    self::MAX_PROXIES_PER_SCHEME,
                );
                $rawProxies = \array_slice($rawProxies, 0, self::MAX_PROXIES_PER_SCHEME);
            }

            foreach ($rawProxies as $proxy) {
                $proxy = \trim((string) $proxy);
                if ($proxy === '') {
                    continue;
                }

                $validationResult = self::validateProxy($proxy);
                if ($validationResult === null) {
                    $errors[] = \sprintf(
                        'Scheme #%d (%s): invalid proxy entry "%s".',
                        $schemeIndex,
                        $name,
                        $proxy,
                    );
                } else {
                    $proxies[] = $validationResult;
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
    public static function validateProxy(string $proxy): ?string
    {
        $proxy = \trim($proxy);

        // CIDR notation (e.g. 10.0.0.0/8, fc00::/7).
        if (\str_contains($proxy, '/')) {
            $parts = \explode('/', $proxy, 2);
            $ip    = $parts[0];
            $bits  = $parts[1];

            if (!\is_numeric($bits)) {
                return null;
            }

            $ip = self::normaliseIpForValidation($ip);
            if ($ip === null) {
                return null;
            }

            $isV6 = \str_contains($ip, ':');
            $max  = $isV6 ? 128 : 32;
            $b    = (int) $bits;

            if ($b < 0 || $b > $max) {
                return null;
            }

            return $ip . '/' . $b;
        }

        // Plain IP address.
        $ip = self::normaliseIpForValidation($proxy);

        return $ip;
    }

    /**
     * Validate a header name.
     *
     * @return string Sanitised header name, or '' on failure.
     */
    public static function sanitiseHeaderName(string $header): string
    {
        $header = \trim($header);
        $header = \substr($header, 0, self::HEADER_NAME_MAX_LENGTH);

        // Header names: letters, digits, hyphens, underscores.
        if ($header !== '' && !\preg_match('/^[A-Za-z0-9\-_]+$/', $header)) {
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
    private static function clampForwardLimit(int $value): int
    {
        return \max(self::FORWARD_LIMIT_MIN, \min(self::FORWARD_LIMIT_MAX, $value));
    }

    /**
     * Sanitise a plain string value (trim + truncate).
     */
    private static function sanitiseString(mixed $value, int $maxLength): string
    {
        if (!\is_string($value) && !\is_numeric($value)) {
            return '';
        }

        $value = \trim((string) $value);

        return \substr($value, 0, $maxLength);
    }

    /**
     * Normalise an IP address for validation.
     *
     * Returns the canonical representation, or null if invalid.
     */
    private static function normaliseIpForValidation(string $ip): ?string
    {
        // Strip brackets from IPv6 (e.g. [::1]).
        $ip = \trim($ip, '[]');

        // Use filter_var for basic validation.
        $filtered = \filter_var($ip, \FILTER_VALIDATE_IP);
        if ($filtered === false) {
            return null;
        }

        // Normalise IPv6 to full canonical form then back to inet_ntop.
        $packed = @\inet_pton($filtered);
        if ($packed === false) {
            return null;
        }

        return \inet_ntop($packed) ?: null;
    }
}
