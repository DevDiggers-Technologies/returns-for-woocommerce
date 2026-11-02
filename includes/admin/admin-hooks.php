<?php
/**
 * This file handles all admin end action hooks.
 *
 * @author DevDiggers
 * @package WPReturns
 * @version 1.0.0
 */

namespace DDWCWPReturns\Includes\Admin;

use Automattic\WooCommerce\Utilities\OrderUtil;

defined( 'ABSPATH' ) || exit();

if ( ! class_exists( 'DDWCWR_Admin_Hooks' ) ) {
	/**
	 * Admin end hook handler class
	 */
	class DDWCWR_Admin_Hooks extends DDWCWR_Admin_Functions {
		/**
		 * Construct
		 *
		 * @param array $ddwcwr_configuration Configuration.
		 */
		public function __construct( $ddwcwr_configuration ) {
			parent::__construct( $ddwcwr_configuration );

			add_action( 'admin_init', [ $this, 'ddwcwr_register_settings' ] );
			add_action( 'admin_notices', [ $this, 'ddwcwr_wc_withdrawal_notice' ] );
			add_action( 'wp_ajax_ddwcwr_toggle_email', [ $this, 'ddwcwr_toggle_email' ] );
			add_action( 'wp_ajax_ddwcwr_import_batch', [ $this, 'ddwcwr_import_batch' ] );
			// A new My Account endpoint slug needs new rewrite rules (flushed on the next load).
			add_action(
				'update_option__ddwcwr_my_account_endpoint',
				function () {
					update_option( '_ddwcwr_flush_rewrite_rules', 'yes' );
				}
			);
			add_action( 'admin_init', [ $this, 'ddwcwr_strip_list_referer' ] );
			add_filter( 'set-screen-option', [ $this, 'ddwcwr_set_screen_option' ], 10, 3 );
			add_filter( 'set_screen_option_ddwcwr_requests_per_page', [ $this, 'ddwcwr_set_screen_option' ], 10, 3 );

			// Admin-post handlers.
			add_action( 'admin_post_ddwcwr_request_action', [ $this, 'ddwcwr_handle_request_action' ] );
			add_action( 'admin_post_ddwcwr_create_request', [ $this, 'ddwcwr_handle_create_request' ] );
			add_action( 'admin_post_ddwcwr_export_withdrawals', [ $this, 'ddwcwr_handle_export_withdrawals' ] );
			add_action( 'admin_post_ddwcwr_clear_unmatched', [ $this, 'ddwcwr_handle_clear_unmatched' ] );

			// Product withdrawal exemption (Art. 16).
			add_action( 'woocommerce_product_options_general_product_data', [ $this, 'ddwcwr_product_fields' ] );
			add_action( 'woocommerce_process_product_meta', [ $this, 'ddwcwr_save_product_fields' ] );

			// Order screen meta box (HPOS and legacy).
			add_action( 'add_meta_boxes', [ $this, 'ddwcwr_add_order_meta_box' ] );

			// Orders list column.
			$order_screen = class_exists( OrderUtil::class ) && OrderUtil::custom_orders_table_usage_is_enabled() ? 'woocommerce_page_wc-orders' : 'shop_order';

			add_filter( "manage_{$order_screen}_columns", [ $this, 'ddwcwr_add_orders_column' ], 20 );
			add_filter( "manage_edit-{$order_screen}_columns", [ $this, 'ddwcwr_add_orders_column' ], 20 );
			add_action( "manage_{$order_screen}_custom_column", [ $this, 'ddwcwr_orders_column_content' ], 10, 2 );
			add_action( "manage_{$order_screen}_posts_custom_column", [ $this, 'ddwcwr_orders_column_content' ], 10, 2 );
		}
	}
}
