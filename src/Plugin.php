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
        $settings = Settings::load();
        $schemes  = $settings->schemes;

        // Allow other plugins to dynamically add trusted proxy addresses.
        if (\function_exists('apply_filters')) {
            /** @var array<Scheme> $schemes */
            $schemes = \apply_filters('vcip_trusted_proxies', $schemes);
        }

        $resolver = new Resolver();
        $result   = $resolver->resolve($_SERVER, $schemes, $settings->forwardLimit);

        $this->lastResult = $result;

        // Let other plugins override the resolved IP.
        $resolvedIp = $result->resolvedIp;
        if (\function_exists('apply_filters')) {
            /** @var string $resolvedIp */
            $resolvedIp = \apply_filters('vcip_resolved_ip', $resolvedIp, $result->steps);
        }

        // Record diagnostics (works even when the plugin is disabled).
        Diagnostics::maybeRecord($_SERVER, $result);

        // When the plugin is disabled, calculate but do not apply (for diagnostics).
        if (!$settings->enabled) {
            return;
        }

        // Apply the result.
        if ($result->changed && $resolvedIp !== $_SERVER['REMOTE_ADDR']) {
            $originalIp = (string) $_SERVER['REMOTE_ADDR'];

            $_SERVER['REMOTE_ADDR'] = $resolvedIp;
            $_SERVER['HTTP_X_ORIGINAL_REMOTE_ADDR'] = $originalIp;

            // Proto processing (default: on).
            if ($settings->processProto && !empty($result->proto['proto'])) {
                $this->applyProto((string) $result->proto['proto']);
            }

            // Host processing (default: off).
            if ($settings->processHost && !empty($result->proto['host'])) {
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
     * Return the default scheme definitions per the specification.
     *
     * Delegates to Settings::defaultSchemes() for the canonical list.
     *
     * @return array<Scheme>
     */
    public static function defaultSchemes(): array
    {
        return Settings::defaultSchemes();
    }
}
