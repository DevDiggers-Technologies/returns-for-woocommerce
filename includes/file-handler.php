<?php
/**
 * File handler
 *
 * @author DevDiggers
 * @package WPReturns
 * @version 1.0.0
 */

namespace DDWCWPReturns\Includes;

use DDWCWPReturns\Includes\Admin;
use DDWCWPReturns\Includes\Common;
use DDWCWPReturns\Includes\Front;

defined( 'ABSPATH' ) || exit();

if ( ! class_exists( 'DDWCWR_File_Handler' ) ) {
	/**
	 * File handler class
	 */
	class DDWCWR_File_Handler {
		/**
		 * Construct
		 */
		public function __construct() {
			global $ddwcwr_configuration;

			// Pages only: on EP_ROOT the endpoint would swallow a root-level page with the same slug (the portal page "returns").
			add_rewrite_endpoint( $ddwcwr_configuration['my_account_endpoint'], EP_PAGES );

			if ( 'yes' === get_option( '_ddwcwr_flush_rewrite_rules' ) ) {
				delete_option( '_ddwcwr_flush_rewrite_rules' );
				flush_rewrite_rules();
			}

			new Common\DDWCWR_Common_Hooks( $ddwcwr_configuration );

			if ( is_admin() ) {
				new DDWCWR_Admin_Dashboard( $ddwcwr_configuration );
				new Admin\DDWCWR_Admin_Hooks( $ddwcwr_configuration );
				new Admin\DDWCWR_Setup_Wizard( $ddwcwr_configuration );
			}

			if ( 'yes' === $ddwcwr_configuration['enabled'] && ( ! is_admin() || wp_doing_ajax() ) ) {
				new Front\DDWCWR_Front_Hooks( $ddwcwr_configuration );
			}
		}
	}
}
