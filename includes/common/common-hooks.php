<?php
/**
 * Common hooks (admin and front).
 *
 * @author DevDiggers
 * @package DevDiggers Returns
 * @version 1.0.0
 */

namespace DDWCWPReturns\Includes\Common;

defined( 'ABSPATH' ) || exit();

if ( ! class_exists( 'DDWCWR_Common_Hooks' ) ) {
	/**
	 * Common hooks class
	 */
	class DDWCWR_Common_Hooks extends DDWCWR_Common_Functions {
		/**
		 * Construct
		 *
		 * @param array $ddwcwr_configuration Configuration.
		 */
		public function __construct( $ddwcwr_configuration ) {
			parent::__construct( $ddwcwr_configuration );

			// Order statuses of a returns plugin the store imported from, kept after that plugin is deactivated.
			add_action( 'init', [ \DDWCWPReturns\Helper\Import\DDWCWR_Import_Helper::class, 'register_kept_statuses' ], 99 );
			add_filter( 'wc_order_statuses', [ \DDWCWPReturns\Helper\Import\DDWCWR_Import_Helper::class, 'add_kept_statuses' ], 99 );

			// Emails.
			add_filter( 'woocommerce_email_classes', [ $this, 'ddwcwr_add_email_classes' ] );
			add_filter( 'woocommerce_prepare_email_for_preview', [ $this, 'ddwcwr_prepare_email_preview' ] );
			add_filter( 'woocommerce_email_actions', [ $this, 'ddwcwr_add_email_actions' ] );

			// Request lifecycle side effects.
			add_action( 'ddwcwr_request_status_changed', [ $this, 'ddwcwr_on_status_changed' ], 20, 4 );

			// Protected files (customers and staff).
			add_action( 'admin_post_ddwcwr_file', [ $this, 'ddwcwr_serve_file' ] );
			add_action( 'admin_post_nopriv_ddwcwr_file', [ $this, 'ddwcwr_serve_file' ] );

			// Privacy.
			add_filter( 'wp_privacy_personal_data_exporters', [ $this, 'ddwcwr_register_exporter' ] );
			add_filter( 'wp_privacy_personal_data_erasers', [ $this, 'ddwcwr_register_eraser' ] );
			add_action( 'admin_init', [ $this, 'ddwcwr_add_privacy_policy_content' ] );

			// EU withdrawal: consent to supply digital content at checkout (Art. 16(m)).
			if ( 'yes' === $ddwcwr_configuration['withdrawal_enabled'] && 'yes' === $ddwcwr_configuration['withdrawal_digital_consent'] ) {
				add_action( 'woocommerce_review_order_before_submit', [ $this, 'ddwcwr_digital_consent_field' ], 9 );
				add_action( 'woocommerce_after_checkout_validation', [ $this, 'ddwcwr_validate_digital_consent' ], 10, 2 );
				add_action( 'woocommerce_checkout_create_order', [ $this, 'ddwcwr_save_digital_consent' ] );
				add_action( 'woocommerce_set_additional_field_value', [ $this, 'ddwcwr_save_block_digital_consent' ], 10, 4 );
				$this->ddwcwr_register_block_digital_consent();
			}

			// Orders that carry the consent show it, even after the setting is turned off.
			add_action( 'woocommerce_email_order_meta', [ $this, 'ddwcwr_email_digital_consent' ], 20, 3 );
			add_action( 'woocommerce_admin_order_data_after_order_details', [ $this, 'ddwcwr_admin_digital_consent' ] );

			// Block registration (server rendered).
			add_action( 'init', [ $this, 'ddwcwr_register_blocks' ], 20 );
		}
	}
}
