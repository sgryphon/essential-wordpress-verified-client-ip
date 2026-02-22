<?php

declare(strict_types=1);

namespace VerifiedClientIp;

/**
 * Simple logger for Verified Client IP.
 *
 * Wraps PHP's error_log() with severity levels and a consistent prefix.
 * Request-level logging is controlled by the WP_DEBUG / VCIP_LOG_REQUESTS
 * constants to avoid performance overhead in production.
 */
final class Logger
{
    /** @var string Log prefix for all messages. */
    private const PREFIX = 'Verified Client IP';

    /**
     * Log an error message.
     */
    public static function error(string $message, string $context = ''): void
    {
        self::log('ERROR', $message, $context);
    }

    /**
     * Log a warning message.
     *
     * Used for malformed headers, suspected spoofing, misconfigured schemes, etc.
     */
    public static function warning(string $message, string $context = ''): void
    {
        self::log('WARNING', $message, $context);
    }

    /**
     * Log an informational message.
     *
     * Used for admin actions (settings saved, diagnostics started, etc.).
     */
    public static function info(string $message, string $context = ''): void
    {
        self::log('INFO', $message, $context);
    }

    /**
     * Log a debug/request-level message.
     *
     * Only logs when WP_DEBUG is true or VCIP_LOG_REQUESTS is true.
     * This keeps request-level logging off by default for performance.
     */
    public static function debug(string $message, string $context = ''): void
    {
        if (!self::isRequestLoggingEnabled()) {
            return;
        }

        self::log('DEBUG', $message, $context);
    }

    /**
     * Whether request-level (debug) logging is enabled.
     */
    public static function isRequestLoggingEnabled(): bool
    {
        // Explicit constant takes precedence.
        if (\defined('VCIP_LOG_REQUESTS')) {
            return (bool) \constant('VCIP_LOG_REQUESTS');
        }

        // Fall back to WP_DEBUG.
        if (\defined('WP_DEBUG')) {
            return (bool) \constant('WP_DEBUG');
        }

        return false;
    }

    /**
     * Write a log entry via error_log().
     */
    private static function log(string $level, string $message, string $context): void
    {
        $entry = '[' . self::PREFIX . '] ' . $level . ': ' . $message;

        if ($context !== '') {
            $entry .= ' (' . $context . ')';
        }

        // @codeCoverageIgnoreStart
        if (\function_exists('error_log')) {
            \error_log($entry);
        }
        // @codeCoverageIgnoreEnd
    }
}
