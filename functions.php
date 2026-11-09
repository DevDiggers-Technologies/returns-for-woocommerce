<?php
/**
 * Plugin Name: DevDiggers Returns for WooCommerce
 * Description: Let customers request returns and cancellations from My Account or a guest returns portal, add the EU "Withdraw from contract here" button, and handle every request, photo, message and refund from one screen.
 * Plugin URI: https://wpreturns.com/
 * Author: DevDiggers
 * Author URI: https://devdiggers.com/
 * Version: 1.0.0
 * Text Domain: devdiggers-returns-for-woocommerce
 * Domain Path: /i18n
 * Requires at least: 6.5
 * Requires PHP: 7.4
 * WC requires at least: 9.0
 * WC tested up to: 11.2
 * DevDiggersPrefix: ddwcwr
 * Requires Plugins: woocommerce
 * License: GPL-3.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 *
 * @package DevDiggers Returns
 */

// ddwcwr: DevDiggers Returns – Returns, RMA & EU Withdrawal Button for WooCommerce.

use DDWCWPReturns\Includes\DDWCWR_File_Handler;

defined( 'ABSPATH' ) || exit();

/**
 * Point the plugin constants at this directory.
 *
 * @return void
 */
function ddwcwr_free_define_constants() {
	defined( 'DDWCWR_PLUGIN_FILE' ) || define( 'DDWCWR_PLUGIN_FILE', plugin_dir_path( __FILE__ ) );
	defined( 'DDWCWR_PLUGIN_URL' ) || define( 'DDWCWR_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
	defined( 'DDWCWR_PLUGIN_BASENAME' ) || define( 'DDWCWR_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
}

if ( ! class_exists( 'DDWCWR_Free_Init' ) ) {
	/**
	 * Free Init class.
	 */
	final class DDWCWR_Free_Init {
		/**
		 * The single instance of this class.
		 *
		 * @var DDWCWR_Free_Init|null
		 */
		private static $instance = null;

		/**
		 * Class constructor.
		 */
		public function __construct() {
			add_action( 'init', [ $this, 'ddwcwr_init' ], 5 );
			add_filter( 'plugin_action_links_' . DDWCWR_PLUGIN_BASENAME, [ $this, 'ddwcwr_plugin_settings_link' ] );
			add_filter( 'plugin_row_meta', [ $this, 'ddwcwr_plugin_row_meta' ], 10, 2 );
		}

		/**
		 * Create a plugin instance.
		 *
		 * @return static
		 */
		public static function get_instance() {
			if ( null === self::$instance ) {
				self::$instance = new self();

				/**
				 * Fires when the main plugin instance is loaded.
				 *
				 * @since 1.0.0
				 */
				do_action( 'ddwcwr_loaded' );
			}

			return self::$instance;
		}

		/**
		 * Init function.
		 *
		 * @return void
		 */
		public function ddwcwr_init() {
			// WordPress.org loads plugin translations automatically.
			if ( ! class_exists( 'WooCommerce' ) ) {
				add_action(
					'admin_notices',
					function () {
						?>
						<div class="notice notice-error">
							<p>
								<?php
								/* translators: %1$s: opening link tag, %2$s: closing link tag */
								printf( esc_html__( 'DevDiggers Returns is activated but not effective. It requires %1$sWooCommerce%2$s in order to work.', 'devdiggers-returns-for-woocommerce' ), '<a href="' . esc_url( 'https://wordpress.org/plugins/woocommerce/' ) . '" target="_blank">', '</a>' );
								?>
							</p>
						</div>
						<?php
					}
				);

				return;
			}

			require_once DDWCWR_PLUGIN_FILE . 'autoload/autoload.php';
			require_once DDWCWR_PLUGIN_FILE . 'includes/global-functions.php';
			require_once DDWCWR_PLUGIN_FILE . 'includes/install.php';

			// Settings saved by earlier builds under the old names move before they are read.
			if ( DDWCWR_Install::DB_VERSION !== get_option( 'ddwcwr_free_db_version' ) ) {
				DDWCWR_Install::ddwcwr_migrate_option_names();
			}

			require_once DDWCWR_PLUGIN_FILE . 'includes/globals-variables.php';

			// The activation hook does not run on every site of a network, or after a manual upload over an older
			// copy: install whenever this site has not been set up for this version. Every step is safe to repeat.
			if ( DDWCWR_Install::DB_VERSION !== get_option( 'ddwcwr_free_db_version' ) ) {
				DDWCWR_Install::ddwcwr_install();
			}

			new DDWCWR_File_Handler();

			// The review notice only belongs on this plugin's own screen and its dismiss request.
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin routing input.
			$on_plugin_page = is_admin() && isset( $_GET['page'] ) && 'ddwcwr-dashboard' === sanitize_key( wp_unslash( $_GET['page'] ) );

			if ( ( $on_plugin_page || wp_doing_ajax() ) && class_exists( '\DevDiggers\Framework\Includes\DDFW_Review_Notice' ) ) {
				new \DevDiggers\Framework\Includes\DDFW_Review_Notice(
					[
						'plugin_name'   => esc_html__( 'DevDiggers Returns', 'devdiggers-returns-for-woocommerce' ),
						'plugin_prefix' => 'ddwcwr',
						'review_url'    => 'https://wordpress.org/support/plugin/devdiggers-returns-for-woocommerce/reviews/#new-post',
					]
				);
			}
		}

		/**
		 * Plugin settings link.
		 *
		 * @param array $links Links array.
		 * @return array
		 */
		public function ddwcwr_plugin_settings_link( $links ) {
			$action_links = [
				'dashboard'     => '<a href="' . esc_url( admin_url( 'admin.php?page=ddwcwr-dashboard' ) ) . '">' . esc_html__( 'Dashboard', 'devdiggers-returns-for-woocommerce' ) . '</a>',
				'configuration' => '<a href="' . esc_url( admin_url( 'admin.php?page=ddwcwr-dashboard&menu=configuration' ) ) . '">' . esc_html__( 'Configuration', 'devdiggers-returns-for-woocommerce' ) . '</a>',
				'setup-wizard'  => '<a href="' . esc_url( admin_url( 'admin.php?page=ddwcwr-dashboard&setup-wizard=true' ) ) . '">' . esc_html__( 'Setup Wizard', 'devdiggers-returns-for-woocommerce' ) . '</a>',
				'upgrade'       => '<a href="' . esc_url( 'https://wpreturns.com/' ) . '" style="color: #0256ff; font-weight: bold;" target="_blank">' . esc_html__( 'Upgrade to Pro', 'devdiggers-returns-for-woocommerce' ) . '</a>',
			];

			return array_merge( $action_links, $links );
		}

		/**
		 * Plugin row meta links.
		 *
		 * @param array  $links Links.
		 * @param string $file  Plugin file.
		 * @return array
		 */
		public function ddwcwr_plugin_row_meta( $links, $file ) {
			if ( DDWCWR_PLUGIN_BASENAME === $file ) {
				$links = array_merge(
					$links,
					[
						'support'       => '<a href="https://wpreturns.com/support/" aria-label="' . esc_attr__( 'Support', 'devdiggers-returns-for-woocommerce' ) . '">' . esc_html__( 'Support', 'devdiggers-returns-for-woocommerce' ) . '</a>',
						'documentation' => '<a href="https://wpreturns.com/docs/" target="_blank" aria-label="' . esc_attr__( 'Documentation', 'devdiggers-returns-for-woocommerce' ) . '">' . esc_html__( 'Documentation', 'devdiggers-returns-for-woocommerce' ) . '</a>',
						'review'        => self::ddwcwr_get_review_link(),
					]
				);
			}

			return $links;
		}

		/**
		 * Five-star link to the plugin reviews on WordPress.org.
		 *
		 * @return string
		 */
		public static function ddwcwr_get_review_link() {
			return '<a href="https://wordpress.org/support/plugin/devdiggers-returns-for-woocommerce/reviews/#new-post" target="_blank" rel="noopener noreferrer" title="' . esc_attr__( 'Review', 'devdiggers-returns-for-woocommerce' ) . '" aria-label="' . esc_attr__( 'Review on WordPress.org', 'devdiggers-returns-for-woocommerce' ) . '"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 192 32" height="10"><path d="M16 26.534L6.111 32 8 20.422l-8-8.2 11.056-1.688L16 0l4.944 10.534L32 12.223l-8 8.2L25.889 32zm40 0L46.111 32 48 20.422l-8-8.2 11.056-1.688L56 0l4.944 10.534L72 12.223l-8 8.2L65.889 32zm40 0L86.111 32 88 20.422l-8-8.2 11.056-1.688L96 0l4.944 10.534L112 12.223l-8 8.2L105.889 32zm40 0L126.111 32 128 20.422l-8-8.2 11.056-1.688L136 0l4.944 10.534L152 12.223l-8 8.2L145.889 32zm40 0L166.111 32 168 20.422l-8-8.2 11.056-1.688L176 0l4.944 10.534L192 12.223l-8 8.2L185.889 32z" fill="#F5A623" fill-rule="evenodd"/></svg></a>';
		}
	}
}

// Load the Free version and the DevDiggers Framework only when the Pro plugin is not active.
add_action(
	'plugins_loaded',
	function () {
		if ( class_exists( 'DDWCWR_Init' ) ) {
			return;
		}

		ddwcwr_free_define_constants();

		DDWCWR_Free_Init::get_instance();

		// Load DevDiggers Framework if not loaded already.
		if ( ! defined( 'DDFW_LOADED' ) && file_exists( DDWCWR_PLUGIN_FILE . 'devdiggers-framework/init.php' ) ) {
			$should_load = true;

			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin routing input.
			if ( ! empty( $_GET['page'] ) ) {
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin routing input.
				$current_page = sanitize_text_field( wp_unslash( $_GET['page'] ) );
				$prefix       = explode( '-', $current_page )[0];

				if ( 0 === strpos( $prefix, 'ddwc' ) || 0 === strpos( $prefix, 'ddwp' ) ) {
					$pro_class  = strtoupper( $prefix ) . '_Init';
					$free_class = strtoupper( $prefix ) . '_Free_Init';

					// Yield to a sibling DevDiggers plugin except on this plugin's own pages.
					if ( class_exists( $free_class ) && ! class_exists( $pro_class ) && 'ddwcwr' !== $prefix ) {
						$should_load = false;
					}
				}
			}

			if ( $should_load ) {
				require DDWCWR_PLUGIN_FILE . 'devdiggers-framework/init.php';
			}
		}
	},
	10
);

register_activation_hook(
	__FILE__,
	function () {
		ddwcwr_free_define_constants();

		require_once DDWCWR_PLUGIN_FILE . 'includes/install.php';

		DDWCWR_Install::ddwcwr_on_plugin_activation();
	}
);

register_deactivation_hook(
	__FILE__,
	function () {
		flush_rewrite_rules();
	}
);

// HPOS and Cart/Checkout Blocks compatibility.
add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
		}
	}
);
