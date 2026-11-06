<?php
/**
 * Import source: Withdrawal Button – Easy Return.
 *
 * EU withdrawals in {prefix}withbuea_returns with one row per item (and its reason) in {prefix}withbuea_return_items.
 * Dates are site-local. The bank account some customers enter is not imported.
 *
 * @package DevDiggers Returns
 * @version 1.0.0
 */

namespace DDWCWPReturns\Helper\Import\Source;

use DDWCWPReturns\Helper\Import\DDWCWR_Import_Source;

defined( 'ABSPATH' ) || exit();

if ( ! class_exists( 'DDWCWR_Easyreturn_Source' ) ) {
	/**
	 * Easy Return source class
	 */
	class DDWCWR_Easyreturn_Source extends DDWCWR_Import_Source {
		/**
		 * Label.
		 *
		 * @return string
		 */
		public function get_label() {
			return __( 'Withdrawal Button – Easy Return', 'devdiggers-returns' );
		}

		/**
		 * Vendor.
		 *
		 * @return string
		 */
		public function get_vendor() {
			return 'Easy Return';
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
			return [ 'easy-return-eu-withdrawal-button-for-woocommerce' ];
		}

		/**
		 * Returns table.
		 *
		 * @return string
		 */
		protected function table() {
			global $wpdb;

			return $wpdb->prefix . 'withbuea_returns';
		}

		/**
		 * Count.
		 *
		 * @return int
		 */
		public function count() {
			global $wpdb;

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			return $this->table_exists( $this->table() ) ? (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table()}" ) : 0;
		}

		/**
		 * Read a batch.
		 *
		 * @param string $cursor Last return ID.
		 * @return array
		 */
		public function read( $cursor ) {
			global $wpdb;

			$requests = [];
			$last     = '';

			if ( ! $this->table_exists( $this->table() ) ) {
				return [
					'requests' => [],
					'cursor'   => '',
				];
			}

			$map = [
				'requested' => 'pending',
				'accepted'  => 'completed',
				'rejected'  => 'rejected',
			];

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			foreach ( $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$this->table()} WHERE id > %d ORDER BY id ASC LIMIT %d", absint( $cursor ), self::BATCH ), ARRAY_A ) as $row ) {
				$last  = (string) $row['id'];
				$order = $this->get_order( $row['order_id'] );

				if ( ! $order ) {
					continue;
				}

				$items = [];

				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				foreach ( $wpdb->get_results( $wpdb->prepare( "SELECT order_item_id, quantity_returned, reason FROM {$wpdb->prefix}withbuea_return_items WHERE return_id = %d", $row['id'] ), ARRAY_A ) as $item ) {
					$items[ absint( $item['order_item_id'] ) ] = [
						'qty'    => absint( $item['quantity_returned'] ),
						'reason' => (string) $item['reason'],
					];
				}

				$created    = $this->from_local( $row['created_at'] );
				$status     = isset( $map[ $row['status'] ] ) ? $map[ $row['status'] ] : 'pending';
				$requests[] = $this->request(
					[
						'key'     => 'easyreturn:' . $row['id'],
						'order'   => $order,
						'type'    => 'withdrawal',
						'status'  => $status,
						'items'   => $items,
						'created' => $created,
						'closed'  => 'pending' === $status ? 0 : $this->from_local( $row['updated_at'] ),
						'meta'    => [
							'statement_name'  => $this->order_name( $order ),
							'statement_email' => $order->get_billing_email(),
							'statement_utc'   => gmdate( 'Y-m-d H:i:s', $created ),
						],
					]
				);
			}

			return [
				'requests' => $requests,
				'cursor'   => $last,
			];
		}
	}
}
