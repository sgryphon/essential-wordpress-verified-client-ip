<?php
/**
 * Bootstrap file for PHPStan to define WordPress plugin constants.
 *
 * @package Essential\VerifiedClientIp
 */

if ( ! defined( 'VCIP_PLUGIN_FILE' ) ) {
	define( 'VCIP_PLUGIN_FILE', __DIR__ . '/essential-verified-client-ip.php' );
}

if ( ! defined( 'VCIP_VERSION' ) ) {
	define( 'VCIP_VERSION', '1.0.1' );
}