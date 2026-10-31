<?php
/**
 * Uninstall: removes WPReturns data only when "Delete all WPReturns data" is on (Configuration > Advanced).
 *
 * Order item warranty meta and WooCommerce refunds, coupons and exchange orders are kept: they belong to orders.
 *
 * @package WPReturns
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

if ( 'yes' !== get_option( '_ddwcwr_delete_data_on_uninstall' ) ) {
	return;
}

global $wpdb;

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Uninstall cleanup of the plugin's own tables and options.
foreach ( [ 'ddwcwr_requests', 'ddwcwr_request_items', 'ddwcwr_messages' ] as $ddwcwr_table ) {
	$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $wpdb->prefix . $ddwcwr_table ) );
}

foreach ( [ '_ddwcwr_portal_page_id', '_ddwcwr_withdrawal_page_id' ] as $ddwcwr_page_option ) {
	$ddwcwr_page_id = absint( get_option( $ddwcwr_page_option ) );

	if ( $ddwcwr_page_id ) {
		wp_delete_post( $ddwcwr_page_id, true );
	}
}

$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", $wpdb->esc_like( '_ddwcwr_' ) . '%', $wpdb->esc_like( '_transient_ddwcwr_' ) . '%' ) );
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE %s", $wpdb->esc_like( '_ddwcwr_' ) . '%' ) );
// phpcs:enable

// Private uploads folder(s).
$ddwcwr_uploads = wp_upload_dir( null, false );

foreach ( (array) glob( trailingslashit( $ddwcwr_uploads['basedir'] ) . 'ddwcwr-private-*', GLOB_ONLYDIR ) as $ddwcwr_dir ) {
	$ddwcwr_files = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $ddwcwr_dir, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );

	foreach ( $ddwcwr_files as $ddwcwr_file ) {
		if ( $ddwcwr_file->isDir() ) {
			rmdir( $ddwcwr_file->getPathname() ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Removing the plugin's private folder.
		} else {
			wp_delete_file( $ddwcwr_file->getPathname() );
		}
	}

	rmdir( $ddwcwr_dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
}
