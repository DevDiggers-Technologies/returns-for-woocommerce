<?php
/**
 * Base for one plugin DevDiggers Returns can import requests from.
 *
 * A source reads the other plugin's data (with SQL, so it also works after that plugin is deactivated) and turns
 * each request into the shape DDWCWR_Import_Helper writes. It never writes to the other plugin's data.
 *
 * Request shape returned by read():
 * key         Unique import key, used to never import the same request twice.
 * order       \WC_Order, or null for a withdrawal statement that names no known order (see "unmatched").
 * type        return|cancellation|withdrawal|warranty.
 * status      A DevDiggers Returns status slug.
 * reason      Reason text of the whole request, matched against the DevDiggers Returns reasons.
 * note        What the customer wrote.
 * items       [ order_item_id => [ qty, reason ] ]. Empty with all_items true for whole-order requests.
 * all_items   Every line of the order.
 * created     GMT timestamp of the request.
 * closed      GMT timestamp it was decided (0 when not known).
 * refunded    Amount refunded for this request (null when the source does not record it).
 * messages    [ [ author_type customer|staff, author_id, kind message|note, message, created GMT timestamp ] ].
 * meta        Extra request meta (EU withdrawal statement fields).
 * unmatched   [ name, order, email, date ] when order is null.
 *
 * @package DevDiggers Returns
 * @version 1.0.0
 */

namespace DDWCWPReturns\Helper\Import;

use Automattic\WooCommerce\Utilities\OrderUtil;

defined( 'ABSPATH' ) || exit();

if ( ! class_exists( 'DDWCWR_Import_Source' ) ) {
	/**
	 * Import source base class
	 */
	abstract class DDWCWR_Import_Source {
		/**
		 * Requests (or orders) read per batch.
		 */
		const BATCH = 25;

		/**
		 * Plugin name shown to the store owner.
		 *
		 * @return string
		 */
		abstract public function get_label();

		/**
		 * Company that makes the plugin.
		 *
		 * @return string
		 */
		abstract public function get_vendor();

		/**
		 * Request types the plugin holds: return, cancellation, withdrawal, warranty.
		 *
		 * @return array
		 */
		abstract public function get_types();

		/**
		 * Folders the plugin installs to, to tell whether it is active.
		 *
		 * @return array
		 */
		abstract public function get_plugin_dirs();

		/**
		 * Whether the plugin is active.
		 *
		 * @return bool
		 */
		public function is_active() {
			foreach ( (array) get_option( 'active_plugins', [] ) as $plugin ) {
				if ( in_array( strtok( $plugin, '/' ), $this->get_plugin_dirs(), true ) ) {
					return true;
				}
			}

			return false;
		}

		/**
		 * Number of requests found.
		 *
		 * @return int
		 */
		abstract public function count();

		/**
		 * Read one batch.
		 *
		 * @param string $cursor Where the previous batch stopped ('' to start).
		 * @return array requests, cursor ('' when there is nothing left).
		 */
		abstract public function read( $cursor );

		/**
		 * A request with every field set.
		 *
		 * @param array $request Fields.
		 * @return array
		 */
		protected function request( $request ) {
			return wp_parse_args(
				$request,
				[
					'key'       => '',
					'order'     => null,
					'type'      => 'return',
					'status'    => 'pending',
					'reason'    => '',
					'note'      => '',
					'items'     => [],
					'all_items' => false,
					'created'   => 0,
					'closed'    => 0,
					'refunded'  => null,
					'messages'  => [],
					'meta'      => [],
					'unmatched' => [],
				]
			);
		}

		/**
		 * A message.
		 *
		 * @param string $author_type customer|staff.
		 * @param string $message Text.
		 * @param int    $created GMT timestamp.
		 * @param string $kind message (customer sees it) or note (staff only).
		 * @param int    $author_id Staff user, when the other plugin recorded one.
		 * @return array
		 */
		protected function message( $author_type, $message, $created, $kind = 'message', $author_id = 0 ) {
			return [
				'author_type' => $author_type,
				'author_id'   => absint( $author_id ),
				'kind'        => $kind,
				'message'     => trim( (string) $message ),
				'created'     => (int) $created,
			];
		}

		/**
		 * A shop order, never a refund or another order type.
		 *
		 * @param int $order_id Order ID.
		 * @return \WC_Order|null
		 */
		protected function get_order( $order_id ) {
			$order = $order_id ? wc_get_order( $order_id ) : false;

			// A trashed order's requests are not worth importing.
			return $order instanceof \WC_Order && 'shop_order' === $order->get_type() && 'trash' !== $order->get_status() ? $order : null;
		}

		/**
		 * Whether a table exists.
		 *
		 * @param string $table Full table name.
		 * @return bool
		 */
		protected function table_exists( $table ) {
			global $wpdb;

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			return $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
		}

		/**
		 * Whether orders are stored in the HPOS tables.
		 *
		 * @return bool
		 */
		protected function is_hpos() {
			return class_exists( OrderUtil::class ) && OrderUtil::custom_orders_table_usage_is_enabled();
		}

		/**
		 * Order meta table, its order ID column and the order meta key column.
		 *
		 * @return array
		 */
		protected function order_meta_table() {
			global $wpdb;

			return $this->is_hpos() ? [ "{$wpdb->prefix}wc_orders_meta", 'order_id' ] : [ $wpdb->postmeta, 'post_id' ];
		}

		/**
		 * Order IDs that have one of the meta keys, in ID order.
		 *
		 * @param string|array $keys  Meta key(s).
		 * @param int          $after Only IDs above this.
		 * @param int          $limit Limit.
		 * @return int[]
		 */
		protected function order_ids_with_meta( $keys, $after, $limit = self::BATCH ) {
			global $wpdb;

			list( $table, $column ) = $this->order_meta_table();
			$keys                   = (array) $keys;
			$in                     = implode( ',', array_fill( 0, count( $keys ), '%s' ) );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- One %s per key, built above.
			return array_map( 'absint', $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT {$column} FROM {$table} WHERE meta_key IN ({$in}) AND {$column} > %d ORDER BY {$column} ASC LIMIT %d", array_merge( $keys, [ $after, $limit ] ) ) ) );
		}

		/**
		 * Number of orders that have one of the meta keys.
		 *
		 * @param string|array $keys Meta key(s).
		 * @return int
		 */
		protected function count_orders_with_meta( $keys ) {
			global $wpdb;

			list( $table, $column ) = $this->order_meta_table();
			$keys                   = (array) $keys;
			$in                     = implode( ',', array_fill( 0, count( $keys ), '%s' ) );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- One %s per key, built above.
			return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(DISTINCT {$column}) FROM {$table} WHERE meta_key IN ({$in})", $keys ) );
		}

		/**
		 * GMT timestamp of a site-local MySQL date.
		 *
		 * @param string $date Local date.
		 * @return int
		 */
		protected function from_local( $date ) {
			return $date && '0000-00-00 00:00:00' !== $date ? (int) strtotime( get_gmt_from_date( $date ) . ' UTC' ) : 0;
		}

		/**
		 * GMT timestamp of a GMT MySQL date.
		 *
		 * @param string $date GMT date.
		 * @return int
		 */
		protected function from_gmt( $date ) {
			return $date && '0000-00-00 00:00:00' !== $date ? (int) strtotime( $date . ' UTC' ) : 0;
		}

		/**
		 * Date of the first order note that contains a text (for plugins that keep no request date).
		 *
		 * @param \WC_Order $order Order.
		 * @param string    $text  Text.
		 * @return int GMT timestamp, 0 when not found.
		 */
		protected function note_date( $order, $text ) {
			if ( '' === trim( (string) $text ) ) {
				return 0;
			}

			$found = 0;

			foreach ( wc_get_order_notes( [ 'order_id' => $order->get_id() ] ) as $note ) {
				if ( false !== strpos( $note->content, $text ) ) {
					// Notes come newest first: keep going to the oldest match.
					$found = $note->date_created->getTimestamp();
				}
			}

			return $found;
		}

		/**
		 * Name on the order.
		 *
		 * @param \WC_Order $order Order.
		 * @return string
		 */
		protected function order_name( $order ) {
			return trim( $order->get_formatted_billing_full_name() );
		}
	}
}
