<?php
/**
 * Front hooks.
 *
 * @author DevDiggers
 * @package WPReturns
 * @version 1.0.0
 */

namespace DDWCWPReturns\Includes\Front;

defined( 'ABSPATH' ) || exit();

if ( ! class_exists( 'DDWCWR_Front_Hooks' ) ) {
	/**
	 * Front hooks class
	 */
	class DDWCWR_Front_Hooks extends DDWCWR_Front_Functions {
		/**
		 * Construct
		 *
		 * @param array $ddwcwr_configuration Configuration.
		 */
		public function __construct( $ddwcwr_configuration ) {
			parent::__construct( $ddwcwr_configuration );

			add_action( 'wp_enqueue_scripts', [ $this, 'ddwcwr_front_scripts' ] );
			add_action( 'template_redirect', [ $this, 'ddwcwr_handle_forms' ] );

			// My Account.
			add_filter( 'query_vars', [ $this, 'ddwcwr_add_query_vars' ] );
			add_filter( 'woocommerce_account_menu_items', [ $this, 'ddwcwr_add_account_menu_item' ] );
			add_action( 'woocommerce_account_' . $ddwcwr_configuration['my_account_endpoint'] . '_endpoint', [ $this, 'ddwcwr_account_endpoint_content' ] );
			add_filter( 'woocommerce_endpoint_' . $ddwcwr_configuration['my_account_endpoint'] . '_title', [ $this, 'ddwcwr_account_endpoint_title' ] );
			add_filter( 'woocommerce_my_account_my_orders_actions', [ $this, 'ddwcwr_add_order_actions' ], 10, 2 );
			add_action( 'woocommerce_order_details_after_order_table', [ $this, 'ddwcwr_order_returns_panel' ] );

			// Guest portal and withdrawal.
			add_shortcode( 'ddwcwr_returns_portal', [ $this, 'ddwcwr_portal_shortcode' ] );
			add_shortcode( 'ddwcwr_withdrawal_form', [ $this, 'ddwcwr_withdrawal_shortcode' ] );
			add_action( 'wp_footer', [ $this, 'ddwcwr_footer_withdrawal_link' ] );
			add_action( 'woocommerce_email_after_order_table', [ $this, 'ddwcwr_email_withdrawal_link' ], 20, 4 );

			// Product page: return policy and withdrawal exemption.
			add_action( 'woocommerce_single_product_summary', [ $this, 'ddwcwr_product_policy_info' ], 35 );

			// Checkout policy link (classic and block checkout).
			add_action( 'woocommerce_review_order_before_submit', [ $this, 'ddwcwr_checkout_policy_notice' ] );
			add_filter( 'render_block_woocommerce/checkout-actions-block', [ $this, 'ddwcwr_checkout_block_policy_notice' ] );
		}
	}
}
