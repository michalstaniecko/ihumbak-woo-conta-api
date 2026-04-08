<?php
/**
 * Plugin uninstall handler.
 *
 * Fired when the plugin is deleted via WordPress admin.
 *
 * @package Ihumbak\WooConta
 */

declare(strict_types=1);

// Abort if not called by WordPress.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Remove plugin options.
delete_option( 'ihumbak_wca_settings' );

// Optionally remove order meta data.
if ( apply_filters( 'ihumbak_wca_uninstall_remove_order_meta', false ) ) {
	global $wpdb;

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->delete(
		$wpdb->prefix . 'postmeta',
		[ 'meta_key' => '_ihumbak_wca_invoice_id' ] // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
	);

	// HPOS meta table.
	if ( $wpdb->get_var( "SHOW TABLES LIKE '{$wpdb->prefix}wc_orders_meta'" ) === $wpdb->prefix . 'wc_orders_meta' ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete(
			$wpdb->prefix . 'wc_orders_meta',
			[ 'meta_key' => '_ihumbak_wca_invoice_id' ] // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		);
	}
}
