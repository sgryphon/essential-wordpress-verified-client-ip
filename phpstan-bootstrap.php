<?php
/**
 * Bootstrap file for PHPStan to define WordPress plugin constants.
 *
 * @package Gryphon\VerifiedClientIp
 */

if ( ! defined( 'VCIP_PLUGIN_FILE' ) ) {
	define( 'VCIP_PLUGIN_FILE', __DIR__ . '/gryphon-verified-client-ip.php' );
}

if ( ! defined( 'VCIP_VERSION' ) ) {
	define( 'VCIP_VERSION', '1.1.0' );
}