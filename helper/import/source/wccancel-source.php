<?php
/**
 * Import source: WC Cancel Order (WpExperts).
 *
 * Whole-order cancellation requests, one row per order in {prefix}wc_cancel_orders (is_approved: 0 waiting,
 * 1 approved, 2 declined). The customer's text is order meta _wc_cancel_additional_txt.
 *
 * @package WPReturns
 * @version 1.0.0
 */

namespace DDWCWPReturns\Helper\Import\Source;

use DDWCWPReturns\Helper\Import\DDWCWR_Import_Source;

defined( 'ABSPATH' ) || exit();

if ( ! class_exists( 'DDWCWR_Wccancel_Source' ) ) {
	/**
	 * WC Cancel Order source class
	 */
	class DDWCWR_Wccancel_Source extends DDWCWR_Import_Source {
		/**
		 * Label.
		 *
		 * @return string
		 */
		public function get_label() {
			return __( 'WC Cancel Order', 'wpreturns' );
		}

		/**
		 * Vendor.
		 *
		 * @return string
		 */
		public function get_vendor() {
			return 'WpExperts';
		}

		/**
		 * Request types it holds.
		 *
		 * @return array
		 */
		public function get_types() {
			return [ 'cancellation' ];
		}

		/**
		 * Plugin folders (free and paid).
		 *
		 * @return array
		 */
		public function get_plugin_dirs() {
			return [ 'wc-cancel-order' ];
		}

		/**
		 * Table.
		 *
		 * @return string
		 */
		protected function table() {
			global $wpdb;

			return $wpdb->prefix . 'wc_cancel_orders';
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
		 * @param string $cursor Last row ID.
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
				0 => 'pending',
				1 => 'completed',
				2 => 'rejected',
			];

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			foreach ( $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$this->table()} WHERE id > %d ORDER BY id ASC LIMIT %d", absint( $cursor ), self::BATCH ), ARRAY_A ) as $row ) {
				$last  = (string) $row['id'];
				$order = $this->get_order( $row['order_id'] );

				if ( ! $order ) {
					continue;
				}

				$decision   = $order->get_meta( '_wc_cancel_request_data' );
				$status     = isset( $map[ (int) $row['is_approved'] ] ) ? $map[ (int) $row['is_approved'] ] : 'pending';
				$requests[] = $this->request(
					[
						'key'       => 'wccancel:' . $row['id'],
						'order'     => $order,
						'type'      => 'cancellation',
						'status'    => $status,
						'note'      => (string) $order->get_meta( '_wc_cancel_additional_txt' ),
						'all_items' => true,
						'created'   => $this->from_gmt( $row['cancel_request_date'] ),
						'closed'    => is_array( $decision ) && ! empty( $decision['date'] ) ? $this->from_gmt( $decision['date'] ) : 0,
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
