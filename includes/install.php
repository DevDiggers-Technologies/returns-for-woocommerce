<?php
/**
 * Install routine: schema, defaults and pages.
 *
 * @package DevDiggers Returns
 * @version 1.0.0
 */

defined( 'ABSPATH' ) || exit();

if ( ! class_exists( 'DDWCWR_Install' ) ) {
	/**
	 * Install class
	 */
	class DDWCWR_Install {
		/**
		 * Schema and defaults version this copy installs.
		 */
		const DB_VERSION = '1.0.0';

		/**
		 * On plugin activation.
		 *
		 * @return void
		 */
		public static function ddwcwr_on_plugin_activation() {
			self::ddwcwr_migrate_option_names();

			// Read by the review notice.
			add_option( 'ddwcwr_installed_at', time() );

			// First activation of a store that has not saved settings yet: open the setup wizard.
			if ( false === get_option( 'ddwcwr_enabled', false ) ) {
				set_transient( 'ddfw_activation_redirect_devdiggers-returns-for-woocommerce', true, 30 );
			}

			self::ddwcwr_install();

			// Endpoints are registered on init; flush on the next request.
			update_option( 'ddwcwr_flush_rewrite_rules', 'yes' );
		}

		/**
		 * Full install: schema, defaults and pages. Staff access is the WooCommerce manage_woocommerce capability.
		 *
		 * @return void
		 */
		public static function ddwcwr_install() {
			self::ddwcwr_migrate_option_names();
			self::ddwcwr_create_schema();
			self::ddwcwr_add_default_options();
			self::ddwcwr_create_pages();

			update_option( 'ddwcwr_free_db_version', self::DB_VERSION );
		}

		/**
		 * Settings saved by earlier builds under "_ddwcwr_<name>" move to "ddwcwr_<name>". Safe to repeat.
		 *
		 * @return void
		 */
		public static function ddwcwr_migrate_option_names() {
			global $wpdb;

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time rename of this plugin's own options.
			$names = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( '_ddwcwr_' ) . '%' ) );

			foreach ( $names as $old_name ) {
				$new_name = substr( $old_name, 1 );

				if ( false === get_option( $new_name, false ) ) {
					update_option( $new_name, get_option( $old_name ) );
				}

				delete_option( $old_name );
			}
		}

		/**
		 * Create Schema
		 *
		 * @return void
		 */
		public static function ddwcwr_create_schema() {
			global $wpdb;

			require_once ABSPATH . 'wp-admin/includes/upgrade.php';

			$charset_collate = $wpdb->get_charset_collate();

			dbDelta(
				"CREATE TABLE {$wpdb->prefix}ddwcwr_requests (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				order_id bigint(20) unsigned NOT NULL DEFAULT 0,
				customer_id bigint(20) unsigned NOT NULL DEFAULT 0,
				customer_email varchar(200) NOT NULL DEFAULT '',
				customer_name varchar(200) NOT NULL DEFAULT '',
				type varchar(20) NOT NULL DEFAULT 'return',
				status varchar(50) NOT NULL DEFAULT 'pending',
				resolution varchar(20) NOT NULL DEFAULT 'refund',
				customer_note text NULL,
				items_total decimal(19,4) NOT NULL DEFAULT 0,
				restocking_fee decimal(19,4) NOT NULL DEFAULT 0,
				shipping_fee decimal(19,4) NOT NULL DEFAULT 0,
				credit_bonus decimal(19,4) NOT NULL DEFAULT 0,
				resolution_total decimal(19,4) NOT NULL DEFAULT 0,
				refunded_total decimal(19,4) NOT NULL DEFAULT 0,
				refund_id bigint(20) unsigned NOT NULL DEFAULT 0,
				coupon_code varchar(100) NOT NULL DEFAULT '',
				exchange_order_id bigint(20) unsigned NOT NULL DEFAULT 0,
				label_provider varchar(30) NOT NULL DEFAULT '',
				label_url text NULL,
				label_file varchar(255) NOT NULL DEFAULT '',
				tracking_number varchar(100) NOT NULL DEFAULT '',
				carrier varchar(100) NOT NULL DEFAULT '',
				assigned_to bigint(20) unsigned NOT NULL DEFAULT 0,
				risk_score int(11) NOT NULL DEFAULT 0,
				risk_flags text NULL,
				source varchar(20) NOT NULL DEFAULT 'account',
				access_key varchar(64) NOT NULL DEFAULT '',
				country varchar(2) NOT NULL DEFAULT '',
				ip_hash varchar(64) NOT NULL DEFAULT '',
				meta longtext NULL,
				created datetime NOT NULL,
				updated datetime NOT NULL,
				closed datetime NULL,
				PRIMARY KEY  (id),
				KEY order_id (order_id),
				KEY customer_id (customer_id),
				KEY customer_email (customer_email(100)),
				KEY status (status),
				KEY type (type),
				KEY created (created)
				) $charset_collate;"
			);

			dbDelta(
				"CREATE TABLE {$wpdb->prefix}ddwcwr_request_items (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				request_id bigint(20) unsigned NOT NULL,
				order_item_id bigint(20) unsigned NOT NULL DEFAULT 0,
				product_id bigint(20) unsigned NOT NULL DEFAULT 0,
				variation_id bigint(20) unsigned NOT NULL DEFAULT 0,
				name varchar(255) NOT NULL DEFAULT '',
				qty int(11) NOT NULL DEFAULT 1,
				line_total decimal(19,4) NOT NULL DEFAULT 0,
				reason varchar(100) NOT NULL DEFAULT '',
				item_condition varchar(50) NOT NULL DEFAULT '',
				exchange_product_id bigint(20) unsigned NOT NULL DEFAULT 0,
				exchange_qty int(11) NOT NULL DEFAULT 0,
				restocked int(11) NOT NULL DEFAULT 0,
				warranty_until datetime NULL,
				PRIMARY KEY  (id),
				KEY request_id (request_id),
				KEY product_id (product_id),
				KEY order_item_id (order_item_id)
				) $charset_collate;"
			);

			dbDelta(
				"CREATE TABLE {$wpdb->prefix}ddwcwr_messages (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				request_id bigint(20) unsigned NOT NULL,
				author_id bigint(20) unsigned NOT NULL DEFAULT 0,
				author_type varchar(20) NOT NULL DEFAULT 'system',
				kind varchar(20) NOT NULL DEFAULT 'log',
				message longtext NULL,
				attachments text NULL,
				created datetime NOT NULL,
				PRIMARY KEY  (id),
				KEY request_id (request_id),
				KEY kind (kind)
				) $charset_collate;"
			);
		}

		/**
		 * Default statuses.
		 *
		 * @return array
		 */
		public static function ddwcwr_get_default_statuses() {
			return [
				[
					'slug'   => 'pending',
					'label'  => __( 'Requested', 'devdiggers-returns-for-woocommerce' ),
					'color'  => '#b33a0b',
					'notify' => 'yes',
					'core'   => 'yes',
				],
				[
					'slug'   => 'approved',
					'label'  => __( 'Approved', 'devdiggers-returns-for-woocommerce' ),
					'color'  => '#0256ff',
					'notify' => 'yes',
					'core'   => 'yes',
				],
				[
					'slug'   => 'awaiting_return',
					'label'  => __( 'Awaiting return', 'devdiggers-returns-for-woocommerce' ),
					'color'  => '#6d28d9',
					'notify' => 'yes',
					'core'   => 'yes',
				],
				[
					'slug'   => 'received',
					'label'  => __( 'Received', 'devdiggers-returns-for-woocommerce' ),
					'color'  => '#0f766e',
					'notify' => 'yes',
					'core'   => 'yes',
				],
				[
					'slug'   => 'completed',
					'label'  => __( 'Completed', 'devdiggers-returns-for-woocommerce' ),
					'color'  => '#15703a',
					'notify' => 'yes',
					'core'   => 'yes',
				],
				[
					'slug'   => 'rejected',
					'label'  => __( 'Rejected', 'devdiggers-returns-for-woocommerce' ),
					'color'  => '#c81e1e',
					'notify' => 'yes',
					'core'   => 'yes',
				],
				[
					'slug'   => 'cancelled',
					'label'  => __( 'Canceled', 'devdiggers-returns-for-woocommerce' ),
					'color'  => '#556274',
					'notify' => 'yes',
					'core'   => 'yes',
				],
			];
		}

		/**
		 * Default reasons.
		 *
		 * @return array
		 */
		public static function ddwcwr_get_default_reasons() {
			$reasons = [
				'wrong-size'       => [ __( 'Wrong size or fit', 'devdiggers-returns-for-woocommerce' ), '' ],
				'not-as-described' => [ __( 'Not as described', 'devdiggers-returns-for-woocommerce' ), 'yes' ],
				'damaged'          => [ __( 'Arrived damaged', 'devdiggers-returns-for-woocommerce' ), 'yes' ],
				'defective'        => [ __( 'Defective or stopped working', 'devdiggers-returns-for-woocommerce' ), 'yes' ],
				'wrong-item'       => [ __( 'Wrong item sent', 'devdiggers-returns-for-woocommerce' ), 'yes' ],
				'changed-mind'     => [ __( 'Changed my mind', 'devdiggers-returns-for-woocommerce' ), '' ],
				'better-price'     => [ __( 'Found a better price', 'devdiggers-returns-for-woocommerce' ), '' ],
				'other'            => [ __( 'Other', 'devdiggers-returns-for-woocommerce' ), '' ],
			];

			$list = [];

			foreach ( $reasons as $slug => $reason ) {
				$list[] = [
					'slug'           => $slug,
					'label'          => $reason[0],
					'require_photo'  => $reason[1],
					'merchant_fault' => in_array( $slug, [ 'damaged', 'defective', 'wrong-item', 'not-as-described' ], true ) ? 'yes' : '',
					'status'         => 'active',
				];
			}

			return $list;
		}

		/**
		 * Add default options once.
		 *
		 * @return void
		 */
		public static function ddwcwr_add_default_options() {
			add_option( 'ddwcwr_statuses', self::ddwcwr_get_default_statuses() );
			add_option( 'ddwcwr_reasons', self::ddwcwr_get_default_reasons() );
		}

		/**
		 * Create the guest portal and withdrawal pages once.
		 *
		 * @return void
		 */
		public static function ddwcwr_create_pages() {
			$pages = [
				'ddwcwr_portal_page_id'     => [
					'title'   => __( 'Returns', 'devdiggers-returns-for-woocommerce' ),
					'slug'    => 'returns',
					'content' => '<!-- wp:shortcode -->[ddwcwr_returns_portal]<!-- /wp:shortcode -->',
				],
				'ddwcwr_withdrawal_page_id' => [
					'title'   => __( 'Withdraw from contract', 'devdiggers-returns-for-woocommerce' ),
					'slug'    => 'withdrawal',
					'content' => '<!-- wp:shortcode -->[ddwcwr_withdrawal_form]<!-- /wp:shortcode -->',
				],
			];

			foreach ( $pages as $option => $page ) {
				$page_id = absint( get_option( $option ) );

				if ( $page_id && get_post( $page_id ) && 'trash' !== get_post_status( $page_id ) ) {
					continue;
				}

				// A page that already holds the shortcode (from an earlier install) is reused, not duplicated.
				$shortcode = substr( $page['content'], strpos( $page['content'], '[' ), strpos( $page['content'], ']' ) - strpos( $page['content'], '[' ) + 1 );
				$existing  = get_posts(
					[
						'post_type'   => 'page',
						'post_status' => 'publish',
						's'           => $shortcode,
						'numberposts' => 1,
						'fields'      => 'ids',
					]
				);

				if ( $existing ) {
					update_option( $option, $existing[0] );
					continue;
				}

				$page_id = wp_insert_post(
					[
						'post_title'     => $page['title'],
						'post_name'      => $page['slug'],
						'post_content'   => $page['content'],
						'post_status'    => 'publish',
						'post_type'      => 'page',
						'comment_status' => 'closed',
					]
				);

				if ( $page_id && ! is_wp_error( $page_id ) ) {
					update_option( $option, $page_id );
				}
			}
		}
	}
}
