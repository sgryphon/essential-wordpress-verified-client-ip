<?php
/**
 * Bootstrap for integration tests.
 *
 * Defines WordPress function stubs so the Plugin class can be tested without
 * a full WordPress installation.
 */

declare(strict_types=1);

// Load Composer autoloader.
require_once dirname( __DIR__, 2 ) . '/vendor/autoload.php';

// -------------------------------------------------------------------
// WordPress function stubs
// -------------------------------------------------------------------

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/tmp/wordpress/' );
}

/** @var array<string, mixed> Simulated wp_options storage. */
$GLOBALS['_vcip_test_options'] = [];

/** @var array<string, mixed> Simulated transient storage. */
$GLOBALS['_vcip_test_transients'] = [];

/** @var array<string, array{callback: callable, args: list<mixed>}> Last applied filter info. */
$GLOBALS['_vcip_test_filters'] = [];

/** @var array<string, list<list<mixed>>> Last fired action info. */
$GLOBALS['_vcip_test_actions'] = [];

if ( ! function_exists( 'get_option' ) ) {
	/**
	 * @param mixed $default
	 * @return mixed
	 */
	function get_option( string $option, $default_value = false ) {
		return $GLOBALS['_vcip_test_options'][ $option ] ?? $default_value;
	}
}

if ( ! function_exists( 'update_option' ) ) {
	/**
	 * @param mixed $value
	 */
	function update_option( string $option, $value ): bool {
		$GLOBALS['_vcip_test_options'][ $option ] = $value;
		return true;
	}
}

if ( ! function_exists( 'get_transient' ) ) {
	/**
	 * @return mixed
	 */
	function get_transient( string $transient ) {
		return $GLOBALS['_vcip_test_transients'][ $transient ] ?? false;
	}
}

if ( ! function_exists( 'set_transient' ) ) {
	/**
	 * @param mixed $value
	 */
	function set_transient( string $transient, $value, int $expiration = 0 ): bool {
		$GLOBALS['_vcip_test_transients'][ $transient ] = $value;
		return true;
	}
}

if ( ! function_exists( 'delete_transient' ) ) {
	function delete_transient( string $transient ): bool {
		unset( $GLOBALS['_vcip_test_transients'][ $transient ] );
		return true;
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	/**
	 * @param mixed ...$args
	 * @return mixed
	 */
	function apply_filters( string $hook_name, ...$args ) {
		$GLOBALS['_vcip_test_filters'][ $hook_name ] = $args;

		// Return the first argument unchanged (default behaviour).
		return $args[0] ?? null;
	}
}

if ( ! function_exists( 'do_action' ) ) {
	/**
	 * @param mixed ...$args
	 */
	function do_action( string $hook_name, ...$args ): void {
		$GLOBALS['_vcip_test_actions'][ $hook_name ][] = $args;
	}
}

if ( ! function_exists( 'add_action' ) ) {
	function add_action( string $hook_name, callable $callback, int $priority = 10, int $accepted_args = 1 ): bool {
		return true;
	}
}

// -------------------------------------------------------------------
// Additional WordPress stubs for admin functionality
// -------------------------------------------------------------------

if ( ! function_exists( 'is_admin' ) ) {
	function is_admin(): bool {
		return $GLOBALS['_vcip_test_is_admin'] ?? false;
	}
}

if ( ! function_exists( 'current_user_can' ) ) {
	/**
	 * @param mixed ...$args
	 */
	function current_user_can( string $capability, ...$args ): bool {
		return $GLOBALS['_vcip_test_user_can'] ?? true;
	}
}

if ( ! function_exists( 'wp_verify_nonce' ) ) {
	function wp_verify_nonce( string $nonce, string $action = '-1' ): bool {
		return true;
	}
}

if ( ! function_exists( 'wp_nonce_field' ) ) {
	function wp_nonce_field( string $action = '-1', string $name = '_wpnonce', bool $referer = true, bool $display = true ): string {
		$html = '<input type="hidden" name="' . $name . '" value="test_nonce">';
		if ( $display ) {
			echo $html;
		}
		return $html;
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( string $str ): string {
		return trim( strip_tags( $str ) );
	}
}

if ( ! function_exists( 'wp_unslash' ) ) {
	/**
	 * @param string|array<mixed> $value
	 * @return string|array<mixed>
	 */
	function wp_unslash( $value ) {
		return is_string( $value ) ? stripslashes( $value ) : $value;
	}
}

if ( ! function_exists( 'add_options_page' ) ) {
	function add_options_page( string $page_title, string $menu_title, string $capability, string $menu_slug, callable $callback = null, int $position = null ): string {
		return 'settings_page_' . $menu_slug;
	}
}

if ( ! function_exists( 'add_settings_error' ) ) {
	/**
	 * @var array<array{setting: string, code: string, message: string, type: string}>
	 */
	function add_settings_error( string $setting, string $code, string $message, string $type = 'error' ): void {
		$GLOBALS['_vcip_test_settings_errors'][] = [
			'setting' => $setting,
			'code'    => $code,
			'message' => $message,
			'type'    => $type,
		];
	}
}

if ( ! function_exists( 'settings_errors' ) ) {
	function settings_errors( string $setting = '', bool $sanitize = false, bool $hide_on_update = false ): void {
		// No-op in tests.
	}
}

if ( ! function_exists( 'submit_button' ) ) {
	function submit_button( string $text = 'Save Changes', string $type = 'primary', string $name = 'submit', bool $wrap = true, $other_attributes = null ): void {
		echo '<input type="submit" class="button button-' . $type . '" value="' . $text . '">';
	}
}

if ( ! function_exists( '__' ) ) {
	function __( string $text, string $domain = 'default' ): string {
		return $text;
	}
}

if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( string $text, string $domain = 'default' ): string {
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_attr__' ) ) {
	function esc_attr__( string $text, string $domain = 'default' ): string {
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( string $text ): string {
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( string $text ): string {
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_textarea' ) ) {
	function esc_textarea( string $text ): string {
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	/**
	 * @param mixed $data
	 */
	function wp_json_encode( $data, int $options = 0, int $depth = 512 ): string {
		$encoded = json_encode( $data, $options | JSON_UNESCAPED_UNICODE, $depth );

		return $encoded ? $encoded : '""';
	}
}

if ( ! function_exists( 'delete_option' ) ) {
	function delete_option( string $option ): bool {
		unset( $GLOBALS['_vcip_test_options'][ $option ] );
		return true;
	}
}

if ( ! function_exists( 'register_deactivation_hook' ) ) {
	function register_deactivation_hook( string $file, callable $callback ): void {
		// No-op in tests.
	}
}

if ( ! function_exists( 'wp_cache_flush' ) ) {
	function wp_cache_flush(): bool {
		return true;
	}
}

if ( ! function_exists( 'plugin_dir_path' ) ) {
	function plugin_dir_path( string $file ): string {
		return dirname( $file ) . '/';
	}
}
