<?php

declare(strict_types=1);

namespace VerifiedClientIp;

/**
 * WordPress plugin lifecycle manager.
 *
 * Hooks into WordPress as early as possible to resolve the verified client IP
 * and replace REMOTE_ADDR before any other plugin reads it.
 */
final class Plugin
{
    private static ?self $instance = null;

    /** @var ResolverResult|null The most recent resolution result (available for diagnostics). */
    private ?ResolverResult $lastResult = null;

    /**
     * Boot the plugin.  Safe to call multiple times — only the first call has
     * any effect.
     */
    public static function boot(): void
    {
        if (self::$instance !== null) {
            return;
        }

        self::$instance = new self();
        self::$instance->resolveClientIp();
    }

    /**
     * Get the singleton instance (null until boot() is called).
     */
    public static function instance(): ?self
    {
        return self::$instance;
    }

    /**
     * Plugin version string.
     */
    public static function version(): string
    {
        return \defined('VCIP_VERSION') ? (string) \constant('VCIP_VERSION') : '0.1.0';
    }

    /**
     * Get the last resolution result (useful for diagnostics).
     */
    public function lastResult(): ?ResolverResult
    {
        return $this->lastResult;
    }

    // ------------------------------------------------------------------
    // Core resolution logic
    // ------------------------------------------------------------------

    /**
     * Run the IP resolution algorithm and apply results.
     */
    private function resolveClientIp(): void
    {
        $settings = $this->loadSettings();
        $schemes  = $this->buildSchemes($settings);

        // Allow other plugins to dynamically add trusted proxy addresses.
        if (\function_exists('apply_filters')) {
            /** @var array<Scheme> $schemes */
            $schemes = \apply_filters('vcip_trusted_proxies', $schemes);
        }

        $resolver = new Resolver();
        $result   = $resolver->resolve($_SERVER, $schemes, (int) ($settings['forward_limit'] ?? 1));

        $this->lastResult = $result;

        // Let other plugins override the resolved IP.
        $resolvedIp = $result->resolvedIp;
        if (\function_exists('apply_filters')) {
            /** @var string $resolvedIp */
            $resolvedIp = \apply_filters('vcip_resolved_ip', $resolvedIp, $result->steps);
        }

        // When the plugin is disabled, calculate but do not apply (for diagnostics).
        $enabled = (bool) ($settings['enabled'] ?? true);
        if (!$enabled) {
            return;
        }

        // Apply the result.
        if ($result->changed && $resolvedIp !== $_SERVER['REMOTE_ADDR']) {
            $originalIp = (string) $_SERVER['REMOTE_ADDR'];

            $_SERVER['REMOTE_ADDR'] = $resolvedIp;
            $_SERVER['HTTP_X_ORIGINAL_REMOTE_ADDR'] = $originalIp;

            // Proto processing (default: on).
            if (($settings['process_proto'] ?? true) && !empty($result->proto['proto'])) {
                $this->applyProto((string) $result->proto['proto']);
            }

            // Host processing (default: off).
            if (($settings['process_host'] ?? false) && !empty($result->proto['host'])) {
                $this->applyHost((string) $result->proto['host']);
            }

            // Fire the post-resolution action.
            if (\function_exists('do_action')) {
                \do_action('vcip_ip_resolved', $resolvedIp, $originalIp, $result->steps);
            }
        }
    }

    // ------------------------------------------------------------------
    // Proto & Host processing
    // ------------------------------------------------------------------

    /**
     * Apply the forwarded protocol (e.g. "https") to $_SERVER.
     */
    private function applyProto(string $proto): void
    {
        // Store originals.
        $_SERVER['HTTP_X_ORIGINAL_HTTPS'] = $_SERVER['HTTPS'] ?? '';
        $_SERVER['HTTP_X_ORIGINAL_REQUEST_SCHEME'] = $_SERVER['REQUEST_SCHEME'] ?? '';

        $proto = \strtolower($proto);

        if ($proto === 'https') {
            $_SERVER['HTTPS'] = 'on';
        }

        $_SERVER['REQUEST_SCHEME'] = $proto;
    }

    /**
     * Apply the forwarded host to $_SERVER.
     */
    private function applyHost(string $host): void
    {
        $_SERVER['HTTP_X_ORIGINAL_HOST'] = $_SERVER['HTTP_HOST'] ?? '';

        $_SERVER['HTTP_HOST']   = $host;
        $_SERVER['SERVER_NAME'] = $host;
    }

    // ------------------------------------------------------------------
    // Settings helpers
    // ------------------------------------------------------------------

    /**
     * Load plugin settings from WordPress options.
     *
     * @return array<string, mixed>
     */
    private function loadSettings(): array
    {
        $defaults = [
            'enabled'       => true,
            'forward_limit' => 1,
            'process_proto' => true,
            'process_host'  => false,
            'schemes'       => [],
        ];

        if (\function_exists('get_option')) {
            /** @var array<string, mixed>|false $stored */
            $stored = \get_option('vcip_settings', []);
            if (\is_array($stored)) {
                return \array_merge($defaults, $stored);
            }
        }

        return $defaults;
    }

    /**
     * Build Scheme objects from stored settings.
     *
     * Falls back to default scheme definitions when no schemes are configured.
     *
     * @param array<string, mixed> $settings
     *
     * @return array<Scheme>
     */
    private function buildSchemes(array $settings): array
    {
        $schemeDefs = $settings['schemes'] ?? [];

        if (\is_array($schemeDefs) && $schemeDefs !== []) {
            return \array_map(
                static fn (array $def): Scheme => Scheme::fromArray($def),
                $schemeDefs,
            );
        }

        // Default schemes per specification.
        return self::defaultSchemes();
    }

    /**
     * Return the default scheme definitions per the specification.
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
}
