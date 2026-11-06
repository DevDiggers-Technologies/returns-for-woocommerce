<?php
/**
 * Import source: Germanized for WooCommerce (EU withdrawal button).
 *
 * Each withdrawal is its own order of type shop_order_withdraw, a child of the original order, with "withdrawal"
 * items that point at the original order items (_parent_id). Read with SQL so it works with Germanized deactivated
 * (the order type is then unknown to WooCommerce). A withdrawal with no matching order goes to the unmatched list.
 *
 * @package DevDiggers Returns
 * @version 1.0.0
 */

namespace DDWCWPReturns\Helper\Import\Source;

use DDWCWPReturns\Helper\Import\DDWCWR_Import_Source;

defined( 'ABSPATH' ) || exit();

if ( ! class_exists( 'DDWCWR_Germanized_Source' ) ) {
	/**
	 * Germanized source class
	 */
	class DDWCWR_Germanized_Source extends DDWCWR_Import_Source {
		/**
		 * Label.
		 *
		 * @return string
		 */
		public function get_label() {
			return __( 'Germanized for WooCommerce', 'devdiggers-returns-for-woocommerce' );
		}

		/**
		 * Vendor.
		 *
		 * @return string
		 */
		public function get_vendor() {
			return 'vendidero';
		}

		/**
		 * Request types it holds.
		 *
		 * @return array
		 */
		public function get_types() {
			return [ 'withdrawal' ];
		}

		/**
		 * Plugin folders (free and paid).
		 *
		 * @return array
		 */
		public function get_plugin_dirs() {
			return [ 'woocommerce-germanized', 'woocommerce-germanized-pro' ];
		}

		/**
		 * SQL that lists withdrawals: id, status, parent, GMT date and customer note.
		 *
		 * @return string
		 */
		protected function select() {
			global $wpdb;

			if ( $this->is_hpos() ) {
				return "SELECT id, status, parent_order_id AS parent, date_created_gmt AS created, customer_note AS note FROM {$wpdb->prefix}wc_orders WHERE type = 'shop_order_withdraw' AND status NOT IN ( 'trash', 'auto-draft' )";
			}

			return "SELECT ID AS id, post_status AS status, post_parent AS parent, post_date_gmt AS created, post_excerpt AS note FROM {$wpdb->posts} WHERE post_type = 'shop_order_withdraw' AND post_status NOT IN ( 'trash', 'auto-draft' )";
		}

		/**
		 * Count.
		 *
		 * @return int
		 */
		public function count() {
			global $wpdb;

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
			return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ( ' . $this->select() . ' ) w' );
		}

		/**
		 * Read a batch.
		 *
		 * @param string $cursor Last withdrawal ID.
		 * @return array
		 */
		public function read( $cursor ) {
			global $wpdb;

			$requests = [];
			$last     = '';

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
			foreach ( $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ( ' . $this->select() . ' ) w WHERE id > %d ORDER BY id ASC LIMIT %d', absint( $cursor ), self::BATCH ), ARRAY_A ) as $row ) {
				$last       = (string) $row['id'];
				$requests[] = $this->build( $row );
			}

			return [
				'requests' => $requests,
				'cursor'   => $last,
			];
		}

		/**
		 * Meta of a withdrawal.
		 *
		 * @param int $id Withdrawal ID.
		 * @return array
		 */
		protected function meta( $id ) {
			global $wpdb;

			list( $table, $column ) = $this->order_meta_table();

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			return wp_list_pluck( $wpdb->get_results( $wpdb->prepare( "SELECT meta_key, meta_value FROM {$table} WHERE {$column} = %d", $id ), ARRAY_A ), 'meta_value', 'meta_key' );
		}

		/**
		 * Build a request.
		 *
		 * @param array $row Withdrawal.
		 * @return array
		 */
		protected function build( $row ) {
			global $wpdb;

			$meta    = $this->meta( $row['id'] );
			$get     = function ( $key ) use ( $meta ) {
				return isset( $meta[ $key ] ) ? (string) $meta[ $key ] : '';
			};
			$created = $this->from_gmt( $row['created'] );
			$name    = trim( $get( '_first_name' ) . ' ' . $get( '_last_name' ) );
			$email   = $get( '_email' );
			$order   = $this->get_order( $row['parent'] );

			if ( ! $order ) {
				return $this->request(
					[
						'key'       => 'germanized:' . $row['id'],
						'type'      => 'withdrawal',
						'created'   => $created,
						'unmatched' => [
							'name'  => $name,
							'order' => $get( '_order_number' ),
							'email' => $email,
							'date'  => gmdate( 'Y-m-d H:i:s', $created ),
						],
					]
				);
			}

			$items = [];

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			foreach ( $wpdb->get_col( $wpdb->prepare( "SELECT order_item_id FROM {$wpdb->prefix}woocommerce_order_items WHERE order_id = %d AND order_item_type = 'withdrawal'", $row['id'] ) ) as $item_id ) {
				$parent = absint( wc_get_order_item_meta( $item_id, '_parent_id', true ) );

				if ( $parent ) {
					$items[ $parent ] = [ 'qty' => max( 1, absint( wc_get_order_item_meta( $item_id, '_quantity', true ) ) ) ];
				}
			}

			$status   = str_replace( 'wc-', '', $row['status'] );
			$messages = [];

			if ( 'owb-rejected' === $status ) {
				$status = 'rejected';
				$closed = (int) $get( '_date_rejected' );

				if ( '' !== $get( '_rejection_reason' ) ) {
					$messages[] = $this->message( 'staff', $get( '_rejection_reason' ), $closed );
				}
			} elseif ( 'owb-confirmed' === $status ) {
				// Confirmed means acknowledged; the refund may still be open.
				$status = absint( $get( '_refund_id' ) ) ? 'completed' : 'approved';
				$closed = 'completed' === $status ? (int) $get( '_date_confirmed' ) : 0;
			} else {
				$status = 'pending';
				$closed = 0;
			}

			return $this->request(
				[
					'key'       => 'germanized:' . $row['id'],
					'order'     => $order,
					'type'      => 'withdrawal',
					'status'    => $status,
					'note'      => (string) $row['note'],
					'items'     => $items,
					'all_items' => empty( $items ),
					'created'   => $created,
					'closed'    => $closed,
					'messages'  => $messages,
					'meta'      => [
						'statement_name'  => '' !== $name ? $name : $this->order_name( $order ),
						'statement_email' => '' !== $email ? $email : $order->get_billing_email(),
						'statement_utc'   => gmdate( 'Y-m-d H:i:s', $created ),
					],
				]
			);
		}
	}
}
