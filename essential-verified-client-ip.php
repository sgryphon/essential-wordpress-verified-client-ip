<?php

/**
 * Plugin Name:       Essential Verified Client IP
 * Plugin URI:        https://github.com/sgryphon/essential-wordpress-verified-client-ip
 * Description:       Determines the true client IP by verifying Forwarded and similar headers, traversing only trusted proxy hops.
 * Version:           1.1.0
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Author:            Sly Gryphon
 * Author URI:        https://github.com/sgryphon
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       essential-verified-client-ip
 *
 * @package Essential\VerifiedClientIp
 */

declare(strict_types=1);

// Abort if called directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin version constant.
define( 'VCIP_VERSION', '1.1.0' );

// Plugin file constant.
define( 'VCIP_PLUGIN_FILE', __FILE__ );

// Plugin directory constant.
define( 'VCIP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

// Autoload classes.
if ( file_exists( VCIP_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
	require_once VCIP_PLUGIN_DIR . 'vendor/autoload.php';
}

// Register IP resolution at the earliest possible hook.
// For must-use plugins, call Essential\VerifiedClientIp\Plugin::boot() directly instead.
add_action( 'plugins_loaded', [ Essential\VerifiedClientIp\Plugin::class, 'boot' ], 0 );

// Deactivation hook — flush caches only, do NOT remove data.
register_deactivation_hook(
	__FILE__,
	static function (): void {
		if ( function_exists( 'wp_cache_flush' ) ) {
			wp_cache_flush();
		}
	}
);

// Register admin settings page (only loaded in admin context).
if ( is_admin() ) {
	Essential\VerifiedClientIp\AdminPage::register();
}
