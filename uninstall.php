<?php
/**
 * Cleanup on uninstall.
 *
 * @package WP_Performance_Optimizer
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Delete main settings.
delete_option( 'i365_po_settings' );

// Delete settings backups.
delete_option( 'i365_po_settings_backups' );

// Delete custom profiles.
delete_option( 'i365_po_profiles' );

// Delete database version tracking.
delete_option( 'i365_po_db_version' );

// Delete legacy option if it exists.
delete_option( 'wppo_settings' );

// Delete local fonts transient.
delete_transient( 'i365_po_local_fonts_cache' );

// Clean up local fonts directory.
$i365_po_upload_dir = wp_upload_dir();
$i365_po_fonts_dir  = trailingslashit( $i365_po_upload_dir['basedir'] ) . 'i365-fonts';
if ( is_dir( $i365_po_fonts_dir ) ) {
	$i365_po_files = glob( $i365_po_fonts_dir . '/*' );
	if ( $i365_po_files ) {
		foreach ( $i365_po_files as $i365_po_file ) {
			if ( is_file( $i365_po_file ) ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
				unlink( $i365_po_file );
			}
		}
	}
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
	rmdir( $i365_po_fonts_dir );
}

// Clear scheduled database cleanup event.
$i365_po_timestamp = wp_next_scheduled( 'i365_po_scheduled_cleanup' );
if ( $i365_po_timestamp ) {
	wp_unschedule_event( $i365_po_timestamp, 'i365_po_scheduled_cleanup' );
}
