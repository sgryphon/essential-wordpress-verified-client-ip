<?php

declare(strict_types=1);

namespace VerifiedClientIp;

/**
 * WordPress plugin lifecycle manager.
 *
 * Hooks into WordPress as early as possible to resolve the verified client IP
 * and replace REMOTE_ADDR before any other plugin reads it.
 */
final class Plugin {

	private static ?self $instance = null;

	/** @var ResolverResult|null The most recent resolution result (available for diagnostics). */
	private ?ResolverResult $last_result = null;

	/**
	 * Boot the plugin.  Safe to call multiple times — only the first call has
	 * any effect.
	 */
	public static function boot(): void {
		if ( null !== self::$instance ) {
			return;
		}

		self::$instance = new self();
		self::$instance->resolve_client_ip();
	}

	/**
	 * Get the singleton instance (null until boot() is called).
	 */
	public static function instance(): ?self {
		return self::$instance;
	}

	/**
	 * Plugin version string.
	 */
	public static function version(): string {
		return \defined( 'VCIP_VERSION' ) ? (string) \constant( 'VCIP_VERSION' ) : '1.0.0';
	}

	/**
	 * Get the last resolution result (useful for diagnostics).
	 */
	public function last_result(): ?ResolverResult {
		return $this->last_result;
	}

	// ------------------------------------------------------------------
	// Core resolution logic
	// ------------------------------------------------------------------

	/**
	 * Run the IP resolution algorithm and apply results.
	 */
	private function resolve_client_ip(): void {
		$settings = Settings::load();
		$schemes  = $settings->schemes;

		// Allow other plugins to dynamically add trusted proxy addresses.
		if ( \function_exists( 'apply_filters' ) ) {
			/** @var array<Scheme> $schemes */
			$schemes = \apply_filters( 'vcip_trusted_proxies', $schemes );
		}

		$resolver = new Resolver();
		$result   = $resolver->resolve( $_SERVER, $schemes, $settings->forward_limit );

		$this->last_result = $result;

		// Let other plugins override the resolved IP.
		$resolved_ip = $result->resolved_ip;
		if ( \function_exists( 'apply_filters' ) ) {
			/** @var string $resolved_ip */
			$resolved_ip = \apply_filters( 'vcip_resolved_ip', $resolved_ip, $result->steps );
		}

		// Record diagnostics (works even when the plugin is disabled).
		Diagnostics::maybe_record( $_SERVER, $result );

		// Request-level debug logging (off by default for performance).
		if ( $result->changed ) {
			Logger::debug(
				\sprintf( 'Resolved %s → %s', $result->original_ip, $resolved_ip ),
				'resolver'
			);
		}

		// Warn on malformed forwarded values found during resolution.
		foreach ( $result->steps as $step ) {
			if ( 'malformed_stop' === $step->action || 'malformed_value' === $step->action ) {
				Logger::warning(
					\sprintf(
						'Malformed forwarded value "%s" from header %s',
						$step->address,
						$step->header_used ?? 'unknown',
					),
					'resolver'
				);
			}
		}

		// When the plugin is disabled, calculate but do not apply (for diagnostics).
		if ( ! $settings->enabled ) {
			return;
		}

		// Apply the result.
		if ( $result->changed && $resolved_ip !== $_SERVER['REMOTE_ADDR'] ) {
			$original_ip = (string) $_SERVER['REMOTE_ADDR'];

			$_SERVER['REMOTE_ADDR']                 = $resolved_ip;
			$_SERVER['HTTP_X_ORIGINAL_REMOTE_ADDR'] = $original_ip;

			// Proto processing (default: on).
			if ( $settings->process_proto && ! empty( $result->proto['proto'] ) ) {
				$this->apply_proto( (string) $result->proto['proto'] );
			}

			// Host processing (default: off).
			if ( $settings->process_host && ! empty( $result->proto['host'] ) ) {
				$this->apply_host( (string) $result->proto['host'] );
			}

			// Fire the post-resolution action.
			if ( \function_exists( 'do_action' ) ) {
				\do_action( 'vcip_ip_resolved', $resolved_ip, $original_ip, $result->steps );
			}
		}
	}

	// ------------------------------------------------------------------
	// Proto & Host processing
	// ------------------------------------------------------------------

	/**
	 * Apply the forwarded protocol (e.g. "https") to $_SERVER.
	 */
	private function apply_proto( string $proto ): void {
		// Store originals.
		$_SERVER['HTTP_X_ORIGINAL_HTTPS']          = $_SERVER['HTTPS'] ?? '';
		$_SERVER['HTTP_X_ORIGINAL_REQUEST_SCHEME'] = $_SERVER['REQUEST_SCHEME'] ?? '';

		$proto = \strtolower( $proto );

		if ( 'https' === $proto ) {
			$_SERVER['HTTPS'] = 'on';
		}

		$_SERVER['REQUEST_SCHEME'] = $proto;
	}

	/**
	 * Apply the forwarded host to $_SERVER.
	 */
	private function apply_host( string $host ): void {
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
	 * Delegates to Settings::default_schemes() for the canonical list.
	 *
	 * @return array<Scheme>
	 */
	public static function default_schemes(): array {
		return Settings::default_schemes();
	}
}
