<?php

/**
 * Uninstall handler for Essential Verified Client IP.
 *
 * Called when the plugin is deleted (not deactivated) via the WordPress
 * admin UI.  Removes all plugin options and diagnostic transients from
 * the database.  In multisite, iterates through all sites.
 *
 * @package Essential\VerifiedClientIp
 */

declare(strict_types=1);

// Abort if not called from WordPress uninstall.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Delete all Essential Verified Client IP data for the current site.
 */
function vcip_uninstall_site(): void {
	// Delete settings option.
	delete_option( 'vcip_settings' );

	// Delete diagnostic transients.
	delete_transient( 'vcip_diagnostic_log' );
	delete_transient( 'vcip_diagnostic_state' );
	delete_transient( 'vcip_diagnostic_lock' );
}

// Multisite: iterate all sites.
if ( is_multisite() ) {
	/** @var array<int, int> $vcip_sites */
	$vcip_sites = get_sites(
		[
			'fields' => 'ids',
			'number' => 0,
		]
	);

	foreach ( $vcip_sites as $vcip_site_id ) {
		switch_to_blog( (int) $vcip_site_id );
		vcip_uninstall_site();
		restore_current_blog();
	}
} else {
	vcip_uninstall_site();
}
