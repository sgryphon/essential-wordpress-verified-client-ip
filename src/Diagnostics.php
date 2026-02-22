<?php

declare(strict_types=1);

namespace VerifiedClientIp;

/**
 * Diagnostics recorder for Verified Client IP.
 *
 * Records a configurable number of incoming requests (default 10, max 100)
 * with timestamps, request URI, all $_SERVER headers, and the algorithm's
 * step-by-step calculation.  Data is stored as WordPress transients with a
 * 24-hour expiry.
 *
 * Diagnostics work even when the main plugin switch is off — the algorithm
 * calculates but does not apply (see Plugin.php).
 */
final class Diagnostics
{
    /** Transient key for the diagnostic log entries. */
    public const TRANSIENT_LOG = 'vcip_diagnostic_log';

    /** Transient key for the diagnostic state (recording, count, etc.). */
    public const TRANSIENT_STATE = 'vcip_diagnostic_state';

    /** Transient key used as a simple lock to prevent concurrent writes. */
    public const TRANSIENT_LOCK = 'vcip_diagnostic_lock';

    /** Default number of requests to record. */
    public const DEFAULT_REQUEST_COUNT = 10;

    /** Maximum number of requests that can be recorded. */
    public const MAX_REQUEST_COUNT = 100;

    /** Transient expiry in seconds (24 hours). */
    public const EXPIRY_SECONDS = 86400;

    /** Lock expiry in seconds (short — just long enough to prevent races). */
    public const LOCK_SECONDS = 5;

    /**
     * Record a request if diagnostics are active and the limit has not
     * been reached.
     *
     * Should be called from Plugin after resolution, regardless of the
     * enabled/disabled switch.
     *
     * @param array<string, mixed>  $serverVars Full $_SERVER snapshot.
     * @param ResolverResult|null   $result     The resolver result (null if not run).
     */
    public static function maybeRecord(array $serverVars, ?ResolverResult $result): void
    {
        $state = self::getState();

        if (!$state['recording']) {
            return;
        }

        // Already reached the configured limit?
        $log = self::getLog();
        if (\count($log) >= $state['max_requests']) {
            // Auto-stop.
            self::stopRecording();
            return;
        }

        // Acquire a simple lock to prevent concurrent writes.
        if (!self::acquireLock()) {
            return; // Another request is writing — skip this one.
        }

        try {
            // Re-read inside the lock (another request may have written).
            $log = self::getLog();
            if (\count($log) >= $state['max_requests']) {
                self::stopRecording();
                return;
            }

            $entry = self::buildEntry($serverVars, $result);
            $log[] = $entry;

            self::saveLog($log);

            // Auto-stop when limit reached.
            if (\count($log) >= $state['max_requests']) {
                self::stopRecording();
            }
        } finally {
            self::releaseLock();
        }
    }

    // ------------------------------------------------------------------
    // State management
    // ------------------------------------------------------------------

    /**
     * Start diagnostic recording.
     *
     * @param int $maxRequests Number of requests to record (1–100).
     */
    public static function startRecording(int $maxRequests = self::DEFAULT_REQUEST_COUNT): void
    {
        $maxRequests = \max(1, \min(self::MAX_REQUEST_COUNT, $maxRequests));

        $state = [
            'recording'    => true,
            'max_requests' => $maxRequests,
            'started_at'   => \time(),
        ];

        self::saveState($state);

        // Clear any previous log.
        self::clearLog();
    }

    /**
     * Stop diagnostic recording (preserves the recorded data).
     */
    public static function stopRecording(): void
    {
        $state = self::getState();
        $state['recording']  = false;
        $state['stopped_at'] = \time();

        self::saveState($state);
    }

    /**
     * Clear all diagnostic data and reset state.
     */
    public static function clear(): void
    {
        if (\function_exists('delete_transient')) {
            \delete_transient(self::TRANSIENT_LOG);
            \delete_transient(self::TRANSIENT_STATE);
        }
    }

    /**
     * Whether diagnostics are currently recording.
     */
    public static function isRecording(): bool
    {
        return self::getState()['recording'];
    }

    /**
     * Return the current diagnostic state.
     *
     * @return array{recording: bool, max_requests: int, started_at: int|null, stopped_at: int|null}
     */
    public static function getState(): array
    {
        $defaults = [
            'recording'    => false,
            'max_requests' => self::DEFAULT_REQUEST_COUNT,
            'started_at'   => null,
            'stopped_at'   => null,
        ];

        if (!\function_exists('get_transient')) {
            return $defaults;
        }

        /** @var array<string, mixed>|false $state */
        $state = \get_transient(self::TRANSIENT_STATE);

        if (!\is_array($state)) {
            return $defaults;
        }

        return [
            'recording'    => (bool) ($state['recording'] ?? false),
            'max_requests' => (int) ($state['max_requests'] ?? self::DEFAULT_REQUEST_COUNT),
            'started_at'   => isset($state['started_at']) ? (int) $state['started_at'] : null,
            'stopped_at'   => isset($state['stopped_at']) ? (int) $state['stopped_at'] : null,
        ];
    }

    /**
     * Return the diagnostic log entries.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function getLog(): array
    {
        if (!\function_exists('get_transient')) {
            return [];
        }

        /** @var array<int, array<string, mixed>>|false $log */
        $log = \get_transient(self::TRANSIENT_LOG);

        return \is_array($log) ? $log : [];
    }

    // ------------------------------------------------------------------
    // Entry builder
    // ------------------------------------------------------------------

    /**
     * Build a single diagnostic log entry.
     *
     * @param array<string, mixed> $serverVars
     *
     * @return array<string, mixed>
     */
    private static function buildEntry(array $serverVars, ?ResolverResult $result): array
    {
        // Extract only headers from $_SERVER (keys starting with HTTP_ or
        // known special keys).
        $headers = [];
        foreach ($serverVars as $key => $value) {
            if (\is_string($key) && (
                \str_starts_with($key, 'HTTP_')
                || \in_array($key, ['REMOTE_ADDR', 'HTTPS', 'REQUEST_SCHEME', 'SERVER_NAME', 'REQUEST_URI', 'REQUEST_METHOD', 'SERVER_PORT', 'SERVER_PROTOCOL'], true)
            )) {
                $headers[$key] = $value;
            }
        }

        $entry = [
            'timestamp'   => \gmdate('c'),
            'request_uri' => (string) ($serverVars['REQUEST_URI'] ?? ''),
            'method'      => (string) ($serverVars['REQUEST_METHOD'] ?? 'GET'),
            'remote_addr' => (string) ($serverVars['REMOTE_ADDR'] ?? ''),
            'headers'     => $headers,
        ];

        if ($result !== null) {
            $entry['resolved_ip']  = $result->resolvedIp;
            $entry['original_ip']  = $result->originalIp;
            $entry['changed']      = $result->changed;
            $entry['steps']        = \array_map(
                static fn (ResolverStep $s): array => $s->toArray(),
                $result->steps,
            );
            $entry['proto']        = $result->proto;
        }

        return $entry;
    }

    // ------------------------------------------------------------------
    // Persistence helpers
    // ------------------------------------------------------------------

    /**
     * @param array<string, mixed> $state
     */
    private static function saveState(array $state): void
    {
        if (\function_exists('set_transient')) {
            \set_transient(self::TRANSIENT_STATE, $state, self::EXPIRY_SECONDS);
        }
    }

    /**
     * @param array<int, array<string, mixed>> $log
     */
    private static function saveLog(array $log): void
    {
        if (\function_exists('set_transient')) {
            \set_transient(self::TRANSIENT_LOG, $log, self::EXPIRY_SECONDS);
        }
    }

    /**
     * Delete only the log transient.
     */
    private static function clearLog(): void
    {
        if (\function_exists('delete_transient')) {
            \delete_transient(self::TRANSIENT_LOG);
        }
    }

    // ------------------------------------------------------------------
    // Lock helpers
    // ------------------------------------------------------------------

    /**
     * Try to acquire a simple transient-based lock.
     */
    private static function acquireLock(): bool
    {
        if (!\function_exists('get_transient') || !\function_exists('set_transient')) {
            return true; // No WP — running in tests, proceed.
        }

        // If the lock transient exists, another request holds it.
        if (\get_transient(self::TRANSIENT_LOCK) !== false) {
            return false;
        }

        \set_transient(self::TRANSIENT_LOCK, '1', self::LOCK_SECONDS);

        return true;
    }

    /**
     * Release the lock.
     */
    private static function releaseLock(): void
    {
        if (\function_exists('delete_transient')) {
            \delete_transient(self::TRANSIENT_LOCK);
        }
    }
}
