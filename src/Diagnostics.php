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
final class Diagnostics {

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
	 * @param array<string, mixed>  $server_vars Full $_SERVER snapshot.
	 * @param ResolverResult|null   $result     The resolver result (null if not run).
	 */
	public static function maybe_record( array $server_vars, ?ResolverResult $result ): void {
		$state = self::get_state();

		if ( ! $state['recording'] ) {
			return;
		}

		// Already reached the configured limit?
		$log = self::get_log();
		if ( \count( $log ) >= $state['max_requests'] ) {
			// Auto-stop.
			self::stop_recording();
			return;
		}

		// Acquire a simple lock to prevent concurrent writes.
		if ( ! self::acquire_lock() ) {
			return; // Another request is writing — skip this one.
		}

		try {
			// Re-read inside the lock (another request may have written).
			$log = self::get_log();
			if ( \count( $log ) >= $state['max_requests'] ) {
				self::stop_recording();
				return;
			}

			$entry = self::build_entry( $server_vars, $result );
			$log[] = $entry;

			self::save_log( $log );

			// Auto-stop when limit reached.
			if ( \count( $log ) >= $state['max_requests'] ) {
				self::stop_recording();
			}
		} finally {
			self::release_lock();
		}
	}

	// ------------------------------------------------------------------
	// State management
	// ------------------------------------------------------------------

	/**
	 * Start diagnostic recording.
	 *
	 * @param int $max_requests Number of requests to record (1–100).
	 */
	public static function start_recording( int $max_requests = self::DEFAULT_REQUEST_COUNT ): void {
		$max_requests = \max( 1, \min( self::MAX_REQUEST_COUNT, $max_requests ) );

		$state = [
			'recording'    => true,
			'max_requests' => $max_requests,
			'started_at'   => \time(),
		];

		self::save_state( $state );

		// Clear any previous log.
		self::clear_log();
	}

	/**
	 * Stop diagnostic recording (preserves the recorded data).
	 */
	public static function stop_recording(): void {
		$state               = self::get_state();
		$state['recording']  = false;
		$state['stopped_at'] = \time();

		self::save_state( $state );
	}

	/**
	 * Clear all diagnostic data and reset state.
	 */
	public static function clear(): void {
		if ( \function_exists( 'delete_transient' ) ) {
			\delete_transient( self::TRANSIENT_LOG );
			\delete_transient( self::TRANSIENT_STATE );
		}
	}

	/**
	 * Whether diagnostics are currently recording.
	 */
	public static function is_recording(): bool {
		return self::get_state()['recording'];
	}

	/**
	 * Return the current diagnostic state.
	 *
	 * @return array{recording: bool, max_requests: int, started_at: int|null, stopped_at: int|null}
	 */
	public static function get_state(): array {
		$defaults = [
			'recording'    => false,
			'max_requests' => self::DEFAULT_REQUEST_COUNT,
			'started_at'   => null,
			'stopped_at'   => null,
		];

		if ( ! \function_exists( 'get_transient' ) ) {
			return $defaults;
		}

		/** @var array<string, mixed>|false $state */
		$state = \get_transient( self::TRANSIENT_STATE );

		if ( ! \is_array( $state ) ) {
			return $defaults;
		}

		return [
			'recording'    => (bool) ( $state['recording'] ?? false ),
			'max_requests' => (int) ( $state['max_requests'] ?? self::DEFAULT_REQUEST_COUNT ),
			'started_at'   => isset( $state['started_at'] ) ? (int) $state['started_at'] : null,
			'stopped_at'   => isset( $state['stopped_at'] ) ? (int) $state['stopped_at'] : null,
		];
	}

	/**
	 * Return the diagnostic log entries.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_log(): array {
		if ( ! \function_exists( 'get_transient' ) ) {
			return [];
		}

		/** @var array<int, array<string, mixed>>|false $log */
		$log = \get_transient( self::TRANSIENT_LOG );

		return \is_array( $log ) ? $log : [];
	}

	// ------------------------------------------------------------------
	// Entry builder
	// ------------------------------------------------------------------

	/**
	 * Build a single diagnostic log entry.
	 *
	 * @param array<string, mixed> $server_vars
	 *
	 * @return array<string, mixed>
	 */
	private static function build_entry( array $server_vars, ?ResolverResult $result ): array {
		// Extract only headers from $_SERVER (keys starting with HTTP_ or
		// known special keys).
		$headers = [];
		foreach ( $server_vars as $key => $value ) {
			if ( \is_string( $key ) && (
				\str_starts_with( $key, 'HTTP_' )
				|| \in_array( $key, [ 'REMOTE_ADDR', 'HTTPS', 'REQUEST_SCHEME', 'SERVER_NAME', 'REQUEST_URI', 'REQUEST_METHOD', 'SERVER_PORT', 'SERVER_PROTOCOL' ], true )
			) ) {
				$headers[ $key ] = $value;
			}
		}

		$entry = [
			'timestamp'   => \gmdate( 'c' ),
			'request_uri' => (string) ( $server_vars['REQUEST_URI'] ?? '' ),
			'method'      => (string) ( $server_vars['REQUEST_METHOD'] ?? 'GET' ),
			'remote_addr' => (string) ( $server_vars['REMOTE_ADDR'] ?? '' ),
			'headers'     => $headers,
		];

		if ( null !== $result ) {
			$entry['resolved_ip'] = $result->resolved_ip;
			$entry['original_ip'] = $result->original_ip;
			$entry['changed']     = $result->changed;
			$entry['steps']       = \array_map(
				static fn ( ResolverStep $s ): array => $s->to_array(),
				$result->steps,
			);
			$entry['proto']       = $result->proto;
		}

		return $entry;
	}

	// ------------------------------------------------------------------
	// Persistence helpers
	// ------------------------------------------------------------------

	/**
	 * @param array<string, mixed> $state
	 */
	private static function save_state( array $state ): void {
		if ( \function_exists( 'set_transient' ) ) {
			\set_transient( self::TRANSIENT_STATE, $state, self::EXPIRY_SECONDS );
		}
	}

	/**
	 * @param array<int, array<string, mixed>> $log
	 */
	private static function save_log( array $log ): void {
		if ( \function_exists( 'set_transient' ) ) {
			\set_transient( self::TRANSIENT_LOG, $log, self::EXPIRY_SECONDS );
		}
	}

	/**
	 * Delete only the log transient.
	 */
	private static function clear_log(): void {
		if ( \function_exists( 'delete_transient' ) ) {
			\delete_transient( self::TRANSIENT_LOG );
		}
	}

	// ------------------------------------------------------------------
	// Lock helpers
	// ------------------------------------------------------------------

	/**
	 * Try to acquire a simple transient-based lock.
	 */
	private static function acquire_lock(): bool {
		if ( ! \function_exists( 'get_transient' ) || ! \function_exists( 'set_transient' ) ) {
			return true; // No WP — running in tests, proceed.
		}

		// If the lock transient exists, another request holds it.
		if ( \get_transient( self::TRANSIENT_LOCK ) !== false ) {
			return false;
		}

		\set_transient( self::TRANSIENT_LOCK, '1', self::LOCK_SECONDS );

		return true;
	}

	/**
	 * Release the lock.
	 */
	private static function release_lock(): void {
		if ( \function_exists( 'delete_transient' ) ) {
			\delete_transient( self::TRANSIENT_LOCK );
		}
	}
}
