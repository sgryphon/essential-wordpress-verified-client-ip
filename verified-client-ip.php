<?php

/**
 * Plugin Name:       Verified Client IP
 * Plugin URI:        https://github.com/sgryphon/essential-wordpress-verified-client-ip
 * Description:       Determines the true client IP address by verifying Forwarded-For and similar headers, traversing only trusted proxy hops.
 * Version:           0.1.0
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Author:            Sly Gryphon
 * Author URI:        https://github.com/sgryphon
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       verified-client-ip
 * Domain Path:       /languages
 *
 * @package VerifiedClientIp
 */

declare(strict_types=1);

// Abort if called directly.
if (! defined('ABSPATH')) {
    exit;
}

// Plugin version constant.
define('VCIP_VERSION', '0.1.0');

// Plugin file constant.
define('VCIP_PLUGIN_FILE', __FILE__);

// Plugin directory constant.
define('VCIP_PLUGIN_DIR', plugin_dir_path(__FILE__));

// Autoload classes.
if (file_exists(VCIP_PLUGIN_DIR . 'vendor/autoload.php')) {
    require_once VCIP_PLUGIN_DIR . 'vendor/autoload.php';
}
