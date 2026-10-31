<?php
/**
 * This file handles the admin dashboard (menus, screens, assets).
 *
 * @package WPReturns
 * @version 1.0.0
 */

namespace DDWCWPReturns\Includes;

use DevDiggers\Framework\Includes\DDFW_Plugin_Dashboard;
use DevDiggers\Framework\Includes\DDFW_Assets;
use DevDiggers\Framework\Includes\DDFW_SVG;
use DDWCWPReturns\Templates\Admin;
use DDWCWPReturns\Helper\Request\DDWCWR_Request_Helper;

defined( 'ABSPATH' ) || exit();

if ( ! class_exists( 'DDWCWR_Admin_Dashboard' ) ) {
	/**
	 * Admin Dashboard Class
	 */
	class DDWCWR_Admin_Dashboard {
		/**
		 * Configuration Variable
		 *
		 * @var array
		 */
		protected $ddwcwr_configuration;

		/**
		 * Dashboard Variable
		 *
		 * @var DDFW_Plugin_Dashboard
		 */
		protected $dashboard;

		/**
		 * Construct
		 *
		 * @param array $ddwcwr_configuration Configuration.
		 */
		public function __construct( $ddwcwr_configuration ) {
			$this->ddwcwr_configuration = $ddwcwr_configuration;

			if ( ! class_exists( '\DevDiggers\Framework\Includes\DDFW_Plugin_Dashboard' ) ) {
				return;
			}

			add_filter( 'ddfw_modify_svg_icons', [ $this, 'ddwcwr_add_svg_icons' ], 10, 2 );

			$this->ddwcwr_add_dashboard_menu();

			add_action( 'admin_enqueue_scripts', [ $this, 'ddwcwr_enqueue_admin_scripts' ] );
			add_filter( 'admin_footer_text', [ $this, 'ddwcwr_set_admin_footer_text' ], 99 );
		}

		/**
		 * Icons for the configuration tabs.
		 *
		 * @param array $icons Icons.
		 * @param array $args Args.
		 * @return array
		 */
		public function ddwcwr_add_svg_icons( $icons, $args ) {
			$size = ! empty( $args['size'] ) ? (int) $args['size'] : 24;
			$open = '<svg xmlns="http://www.w3.org/2000/svg" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">';
			$list = [
				'ddwcwr-policy'     => '<path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 3v5h5"/>',
				'ddwcwr-reasons'    => '<path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/>',
				'ddwcwr-statuses'   => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/>',
				'ddwcwr-resolution' => '<path d="M17 1l4 4-4 4"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><path d="M7 23l-4-4 4-4"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/>',
				'ddwcwr-eu'         => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18"/>',
				'ddwcwr-warranty'   => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',
				'ddwcwr-label'      => '<rect x="3" y="7" width="18" height="13" rx="2"/><path d="M8 7V4h8v3M7 12h10M7 16h6"/>',
				'ddwcwr-fraud'      => '<path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/><path d="M12 9v4M12 17h.01"/>',
				'ddwcwr-automation' => '<path d="M13 2 3 14h9l-1 8 10-12h-9l1-8z"/>',
				'ddwcwr-emails'     => '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 6-10 7L2 6"/>',
				'ddwcwr-appearance' => '<circle cx="13.5" cy="6.5" r="1.5"/><circle cx="17.5" cy="10.5" r="1.5"/><circle cx="8.5" cy="7.5" r="1.5"/><circle cx="6.5" cy="12.5" r="1.5"/><path d="M12 2a10 10 0 0 0 0 20c1 0 1.7-.8 1.7-1.7 0-.4-.2-.8-.4-1.1-.3-.3-.4-.7-.4-1.1 0-.9.8-1.7 1.7-1.7h2A5.5 5.5 0 0 0 22 11c0-5-4.5-9-10-9z"/>',
				'ddwcwr-advanced'   => '<path d="M4 21v-7M4 10V3M12 21v-9M12 8V3M20 21v-5M20 12V3M1 14h6M9 8h6M17 16h6"/>',
			];

			foreach ( $list as $name => $paths ) {
				$icons[ $name ] = $open . $paths . '</svg>';
			}

			return $icons;
		}

		/**
		 * Add Admin menu function
		 *
		 * @return void
		 */
		public function ddwcwr_add_dashboard_menu() {
			ob_start();
			?>
			<svg width="32" height="32" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg">
				<circle cx="16" cy="16" r="15" fill="var(--ddfw-tab-background-color)"/>
				<path d="M16 10.5 22 13.5 16 16.5 10 13.5Z" fill="var(--ddfw-primary-color)" fill-opacity="0.45"/>
				<path d="M10 13.5 16 16.5V23.5L10 20.5Z" fill="var(--ddfw-primary-color)"/>
				<path d="M22 13.5 16 16.5V23.5L22 20.5Z" fill="var(--ddfw-primary-color)" fill-opacity="0.75"/>
				<path d="M13 12 19 15" stroke="white" stroke-width="1.2" stroke-linecap="round"/>
				<path d="M9.5 9.5a7.5 7.5 0 0 1 11-1.5" stroke="var(--ddfw-primary-color)" stroke-width="1.6" stroke-linecap="round"/>
				<path d="m8.6 7.2.9 2.3 2.3-.7" stroke="var(--ddfw-primary-color)" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
			</svg>
			<?php esc_html_e( 'WPReturns', 'wpreturns' ); ?>
			<?php
			$plugin_name = ob_get_clean();

			$tab = function ( $label, $icon, $callback ) {
				return [
					'label'    => $label,
					'icon'     => DDFW_SVG::get_svg_icon( $icon, true, [ 'size' => 18 ] ),
					'callback' => [ $this, $callback ],
				];
			};

			$pending = 0;

			if ( ddwcwr_current_user_can() ) {
				$pending = ( new DDWCWR_Request_Helper() )->get_requests_count( [ 'status' => 'pending' ] );
			}

			$args = [
				'page_title'              => esc_html__( 'WPReturns – Returns & EU Withdrawal', 'wpreturns' ),
				'menu_title'              => $pending ? esc_html__( 'WPReturns', 'wpreturns' ) . ' <span class="awaiting-mod">' . absint( $pending ) . '</span>' : esc_html__( 'WPReturns', 'wpreturns' ),
				'slug'                    => 'ddwcwr-dashboard',
				'plugin_name'             => $plugin_name,
				'capability'              => 'manage_woocommerce',
				'upgrade_url'             => 'https://wpreturns.com/',
				'screen_options_callback' => [ $this, 'ddwcwr_add_screen_options' ],
				'menus'                   => [
					'dashboard'     => [
						'label'    => esc_html__( 'Dashboard', 'wpreturns' ),
						'callback' => [ $this, 'ddwcwr_get_dashboard_template' ],
						'layout'   => 'full-width',
					],
					'requests'      => [
						'label'    => esc_html__( 'Requests', 'wpreturns' ),
						'callback' => [ $this, 'ddwcwr_get_requests_template' ],
						'layout'   => 'full-width',
					],
					'withdrawals'   => [
						'label'    => esc_html__( 'Withdrawals', 'wpreturns' ),
						'callback' => [ $this, 'ddwcwr_get_withdrawals_template' ],
						'layout'   => 'full-width',
					],
					'rules'         => [
						'label'    => esc_html__( 'Rules', 'wpreturns' ),
						'callback' => [ $this, 'ddwcwr_get_rules_template' ],
						'layout'   => 'full-width',
					],
					'customers'     => [
						'label'    => esc_html__( 'Customers', 'wpreturns' ),
						'callback' => [ $this, 'ddwcwr_get_customers_template' ],
						'layout'   => 'full-width',
					],
					'configuration' => [
						'label'  => esc_html__( 'Configuration', 'wpreturns' ),
						'layout' => 'sidebar',
						'tabs'   => [
							'general'     => $tab( esc_html__( 'General', 'wpreturns' ), 'general', 'ddwcwr_configuration_tab' ),
							'policy'      => $tab( esc_html__( 'Return Policy', 'wpreturns' ), 'ddwcwr-policy', 'ddwcwr_configuration_tab' ),
							'reasons'     => $tab( esc_html__( 'Reasons', 'wpreturns' ), 'ddwcwr-reasons', 'ddwcwr_configuration_tab' ),
							'statuses'    => $tab( esc_html__( 'Statuses', 'wpreturns' ), 'ddwcwr-statuses', 'ddwcwr_configuration_tab' ),
							'resolutions' => $tab( esc_html__( 'Resolutions', 'wpreturns' ), 'ddwcwr-resolution', 'ddwcwr_configuration_tab' ),
							'withdrawal'  => $tab( esc_html__( 'EU Withdrawal', 'wpreturns' ), 'ddwcwr-eu', 'ddwcwr_configuration_tab' ),
							'warranty'    => $tab( esc_html__( 'Warranty', 'wpreturns' ), 'ddwcwr-warranty', 'ddwcwr_configuration_tab' ),
							'labels'      => $tab( esc_html__( 'Shipping Labels', 'wpreturns' ), 'ddwcwr-label', 'ddwcwr_configuration_tab' ),
							'fraud'       => $tab( esc_html__( 'Fraud Protection', 'wpreturns' ), 'ddwcwr-fraud', 'ddwcwr_configuration_tab' ),
							'automation'  => $tab( esc_html__( 'Automation', 'wpreturns' ), 'ddwcwr-automation', 'ddwcwr_configuration_tab' ),
							'emails'      => $tab( esc_html__( 'Emails', 'wpreturns' ), 'ddwcwr-emails', 'ddwcwr_configuration_tab' ),
							'appearance'  => $tab( esc_html__( 'Appearance', 'wpreturns' ), 'ddwcwr-appearance', 'ddwcwr_configuration_tab' ),
							'advanced'    => $tab( esc_html__( 'Advanced', 'wpreturns' ), 'ddwcwr-advanced', 'ddwcwr_configuration_tab' ),
						],
					],
				],
			];

			$this->dashboard = new DDFW_Plugin_Dashboard( $args );
		}

		/**
		 * Screen options for list screens.
		 *
		 * @return void
		 */
		public function ddwcwr_add_screen_options() {
			// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound, WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase -- $myListTable is the global the framework screen options read.
			global $myListTable;

			$menu = ! empty( $_GET['menu'] ) ? sanitize_title( wp_unslash( $_GET['menu'] ) ) : 'dashboard'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$args = [
				'label'    => esc_html__( 'Results per page', 'wpreturns' ),
				'default'  => 20,
				'sanitize' => 'intval',
			];

			switch ( $menu ) {
				case 'requests':
				case 'withdrawals':
					if ( empty( $_GET['request-id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
						$args['option'] = 'ddwcwr_requests_per_page';
						$myListTable    = new Admin\Request\DDWCWR_Requests_List_Template( 'withdrawals' === $menu ? 'withdrawal' : '' );
					}
					break;
			}

			// phpcs:enable

			if ( ! empty( $args['option'] ) ) {
				add_screen_option( 'per_page', $args );
			}
		}

		/**
		 * Dashboard Template
		 *
		 * @return void
		 */
		public function ddwcwr_get_dashboard_template() {
			new Admin\Dashboard\DDWCWR_Dashboard_Template();
		}

		/**
		 * Requests Template
		 *
		 * @param string $type Fixed type filter.
		 * @return void
		 */
		public function ddwcwr_get_requests_template( $type = '' ) {
			// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only routing.
			$request_id = ! empty( $_GET['request-id'] ) ? absint( $_GET['request-id'] ) : 0;
			$action     = ! empty( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';
			$status     = ! empty( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
			// phpcs:enable

			if ( $request_id ) {
				new Admin\Request\DDWCWR_Request_View_Template( $request_id, $this->ddwcwr_configuration );
				return;
			}

			if ( 'add' === $action ) {
				new Admin\Request\DDWCWR_Request_Create_Template();
				return;
			}

			$this->ddwcwr_print_notices();

			$obj  = new Admin\Request\DDWCWR_Requests_List_Template( is_string( $type ) ? $type : '' );
			$page = 'ddwcwr-dashboard';
			$menu = 'withdrawal' === $type ? 'withdrawals' : 'requests';
			?>
			<h1 class="wp-heading-inline"><?php echo 'withdrawal' === $type ? esc_html__( 'EU Withdrawals', 'wpreturns' ) : esc_html__( 'Requests', 'wpreturns' ); ?></h1>
			<?php if ( 'withdrawal' === $type ) : ?>
				<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=ddwcwr_export_withdrawals' ), 'ddwcwr_export_withdrawals', 'ddwcwr_nonce' ) ); ?>" class="page-title-action button"><?php esc_html_e( 'Export CSV', 'wpreturns' ); ?></a>
			<?php else : ?>
				<a href="<?php echo esc_url( admin_url( "admin.php?page={$page}&menu={$menu}&action=add" ) ); ?>" class="page-title-action button-primary button">
					<?php
					DDFW_SVG::get_svg_icon( 'plus', false, [ 'size' => 15 ] );
					esc_html_e( 'Add New', 'wpreturns' );
					?>
				</a>
				<span class="ddfw-upgrade-to-pro-tag-wrapper ddwcwr-locked-action">
					<button type="button" class="page-title-action button" disabled="disabled"><?php esc_html_e( 'Export CSV', 'wpreturns' ); ?></button>
					<?php echo wp_kses_post( ddfw_get_pro_tag() ); ?>
				</span>
			<?php endif; ?>
			<hr class="wp-header-end" />
			<?php if ( 'withdrawal' === $type ) : ?>
				<p class="description"><?php esc_html_e( 'Every withdrawal statement submitted through the "Withdraw from contract here" function, with the date and time it was received. Keep these as your record.', 'wpreturns' ); ?></p>
				<?php
				if ( 'unmatched' === $status ) {
					new Admin\Request\DDWCWR_Unmatched_Withdrawals_Template();
					return;
				}
				?>
			<?php endif; ?>
			<form method="get" class="ddwcwr-list-form">
				<input type="hidden" name="page" value="<?php echo esc_attr( $page ); ?>" />
				<input type="hidden" name="menu" value="<?php echo esc_attr( $menu ); ?>" />
				<?php if ( $status ) : ?>
					<input type="hidden" name="status" value="<?php echo esc_attr( $status ); ?>" />
				<?php endif; ?>
				<?php
				wp_nonce_field( 'ddwcwr_nonce_action', 'ddwcwr_nonce' );
				$obj->prepare_items();
				$obj->views();
				$obj->search_box( esc_html__( 'Search', 'wpreturns' ), 'ddwcwr-search' );
				$obj->display();
				?>
			</form>
			<?php
		}

		/**
		 * Withdrawals Template
		 *
		 * @return void
		 */
		public function ddwcwr_get_withdrawals_template() {
			$this->ddwcwr_get_requests_template( 'withdrawal' );
		}

		/**
		 * Rules Template
		 *
		 * @return void
		 */
		public function ddwcwr_get_rules_template() {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inside the framework helper.
			echo ddfw_kses_form_html(
				ddwcwr_get_upgrade_card(
					'rules',
					[
						'heading'       => esc_html__( 'Set different return rules for different products', 'wpreturns' ),
						'description'   => esc_html__( 'One return window fits most stores to start with. Rules let you treat sale items, big-ticket products and loyal customers differently, and decide simple requests for you.', 'wpreturns' ),
						'list_features' => [
							esc_html__( 'Return windows per product, category, customer role or country', 'wpreturns' ),
							esc_html__( 'Final-sale rules by item price, order total or customer, with your own message', 'wpreturns' ),
							esc_html__( 'Auto-approve or auto-reject requests by reason, value or customer', 'wpreturns' ),
							esc_html__( 'Restocking fees, fixed or a percentage, never on store-fault reasons', 'wpreturns' ),
							esc_html__( 'Free return shipping above an order value, and photo rules per item value', 'wpreturns' ),
							esc_html__( 'Keep-it refunds: refund cheap items without asking for them back', 'wpreturns' ),
						],
					]
				)
			);
		}

		/**
		 * Customers (return history and fraud) Template
		 *
		 * @return void
		 */
		public function ddwcwr_get_customers_template() {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inside the framework helper.
			echo ddfw_kses_form_html(
				ddwcwr_get_upgrade_card(
					'customers',
					[
						'heading'       => esc_html__( 'Spot serial returners before you refund them', 'wpreturns' ),
						'description'   => esc_html__( 'See every customer who has asked for a return, with their return rate by number of orders and by spend, and stop abuse without blocking honest customers.', 'wpreturns' ),
						'list_features' => [
							esc_html__( 'Return rate by orders and by value for every customer', 'wpreturns' ),
							esc_html__( 'Serial returner, very quick return, late-in-window and high-value flags', 'wpreturns' ),
							esc_html__( 'Risk score on every request and in the list', 'wpreturns' ),
							esc_html__( 'Flag a customer to hold their requests for review, or block by email and domain', 'wpreturns' ),
							esc_html__( 'EU withdrawals are never refused, only flagged', 'wpreturns' ),
						],
					]
				)
			);
		}

		/**
		 * Configuration tab (one template class, one method per tab).
		 *
		 * @return void
		 */
		public function ddwcwr_configuration_tab() {
			$tab = ! empty( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'general'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

			new Admin\Configuration\DDWCWR_Configuration_Template( $tab, $this->ddwcwr_configuration );
		}

		/**
		 * Notices passed through the redirect after an admin-post action.
		 *
		 * @return void
		 */
		protected function ddwcwr_print_notices() {
			// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Display only.
			if ( ! empty( $_GET['ddwcwr-message'] ) ) {
				ddfw_print_notification( sanitize_text_field( wp_unslash( $_GET['ddwcwr-message'] ) ), ! empty( $_GET['ddwcwr-error'] ) ? 'error' : 'success' );
			}
			// phpcs:enable
		}

		/**
		 * Enqueue admin scripts.
		 *
		 * @return void
		 */
		public function ddwcwr_enqueue_admin_scripts() {
			$screen = get_current_screen();

			// Order and product screens get the small order meta box / product fields styles.
			if ( $screen && in_array( $screen->id, [ 'product', 'edit-product', 'shop_order', 'woocommerce_page_wc-orders', 'user-edit', 'profile' ], true ) ) {
				wp_enqueue_style( 'ddwcwr-admin-style', DDWCWR_PLUGIN_URL . 'assets/css/admin.css', [], filemtime( DDWCWR_PLUGIN_FILE . 'assets/css/admin.css' ) );
			}

			if ( ! $this->dashboard || ! $this->dashboard->is_a_plugin_page() ) {
				return;
			}

			wp_enqueue_media();
			wp_enqueue_style( 'ddwcwr-admin-style', DDWCWR_PLUGIN_URL . 'assets/css/admin.css', [ DDFW_Assets::$framework_css_handle ], filemtime( DDWCWR_PLUGIN_FILE . 'assets/css/admin.css' ) );
			wp_enqueue_script( 'ddwcwr-admin-script', DDWCWR_PLUGIN_URL . 'assets/js/admin.js', [ DDFW_Assets::$framework_js_handle, 'jquery-ui-sortable' ], filemtime( DDWCWR_PLUGIN_FILE . 'assets/js/admin.js' ), true );
			wp_localize_script(
				'ddwcwr-admin-script',
				'ddwcwrAdminObj',
				[
					'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
					'emailNonce' => wp_create_nonce( 'ddwcwr_toggle_email' ),
					'i18n'       => [
						'confirmDelete'  => esc_html__( 'Delete this permanently? This cannot be undone.', 'wpreturns' ),
						'confirmProcess' => esc_html__( 'Refund this request now? Refunds cannot be undone from here.', 'wpreturns' ),
						'chooseLabel'    => esc_html__( 'Choose a label file', 'wpreturns' ),
						'useFile'        => esc_html__( 'Use this file', 'wpreturns' ),
						'remove'         => esc_html__( 'Remove', 'wpreturns' ),
					],
				]
			);
		}

		/**
		 * Ask for a review in the admin footer of the plugin pages.
		 *
		 * @param string $footer_text Footer text.
		 * @return string
		 */
		public function ddwcwr_set_admin_footer_text( $footer_text ) {
			if ( ! $this->dashboard || ! $this->dashboard->is_a_plugin_page() ) {
				return $footer_text;
			}

			/* translators: %s: five-star link */
			return sprintf( esc_html__( 'If you really like our plugin, please leave us a %s rating, we\'ll really appreciate it.', 'wpreturns' ), \DDWCWR_Free_Init::ddwcwr_get_review_link() );
		}
	}
}
