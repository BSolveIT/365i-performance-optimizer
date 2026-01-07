<?php
/**
 * Cleanup on uninstall.
 *
 * @package WP_Performance_Optimizer
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'i365_po_settings' );
