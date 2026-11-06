<?php
/**
 * Import source: Shiptastic for WooCommerce (returns, also used by Germanized).
 *
 * Returns are shipments of type "return" in {prefix}woocommerce_stc_shipments, with their items in
 * woocommerce_stc_shipment_items and a reason code per item (_return_reason_code). Reason labels are in the
 * woocommerce_shiptastic_return_reasons option.
 *
 * @package DevDiggers Returns
 * @version 1.0.0
 */

namespace DDWCWPReturns\Helper\Import\Source;

use DDWCWPReturns\Helper\Import\DDWCWR_Import_Source;

defined( 'ABSPATH' ) || exit();

if ( ! class_exists( 'DDWCWR_Shiptastic_Source' ) ) {
	/**
	 * Shiptastic source class
	 */
	class DDWCWR_Shiptastic_Source extends DDWCWR_Import_Source {
		/**
		 * Label.
		 *
		 * @return string
		 */
		public function get_label() {
			return __( 'Shiptastic for WooCommerce', 'devdiggers-returns' );
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
			return [ 'return' ];
		}

		/**
		 * Plugin folders (free and paid).
		 *
		 * @return array
		 */
		public function get_plugin_dirs() {
			return [ 'shiptastic-for-woocommerce' ];
		}

		/**
		 * Shipments table.
		 *
		 * @return string
		 */
		protected function table() {
			global $wpdb;

			return $wpdb->prefix . 'woocommerce_stc_shipments';
		}

		/**
		 * Count.
		 *
		 * @return int
		 */
		public function count() {
			global $wpdb;

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			return $this->table_exists( $this->table() ) ? (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table()} WHERE shipment_type = 'return' AND shipment_status <> 'deleted'" ) : 0;
		}

		/**
		 * Read a batch.
		 *
		 * @param string $cursor Last shipment ID.
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

			$reasons = [];

			foreach ( (array) get_option( 'woocommerce_shiptastic_return_reasons', [] ) as $reason ) {
				if ( is_array( $reason ) && ! empty( $reason['code'] ) ) {
					$reasons[ $reason['code'] ] = isset( $reason['reason'] ) ? $reason['reason'] : $reason['code'];
				}
			}

			$map = [
				'draft'              => 'pending',
				'requested'          => 'pending',
				'processing'         => 'approved',
				'ready-for-shipping' => 'awaiting_return',
				'shipped'            => 'awaiting_return',
				'delivered'          => 'received',
				'rejected'           => 'rejected',
			];

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			foreach ( $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$this->table()} WHERE shipment_type = 'return' AND shipment_status <> 'deleted' AND shipment_id > %d ORDER BY shipment_id ASC LIMIT %d", absint( $cursor ), self::BATCH ), ARRAY_A ) as $row ) {
				$last  = (string) $row['shipment_id'];
				$order = $this->get_order( $row['shipment_order_id'] );

				if ( ! $order ) {
					continue;
				}

				$items = [];

				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				foreach ( $wpdb->get_results( $wpdb->prepare( "SELECT i.shipment_item_order_item_id AS item_id, i.shipment_item_quantity AS qty, m.meta_value AS code FROM {$wpdb->prefix}woocommerce_stc_shipment_items i LEFT JOIN {$wpdb->prefix}woocommerce_stc_shipment_itemmeta m ON m.stc_shipment_item_id = i.shipment_item_id AND m.meta_key = '_return_reason_code' WHERE i.shipment_id = %d", $row['shipment_id'] ), ARRAY_A ) as $item ) {
					$code = (string) $item['code'];

					$items[ absint( $item['item_id'] ) ] = [
						'qty'    => max( 1, absint( $item['qty'] ) ),
						'reason' => isset( $reasons[ $code ] ) ? $reasons[ $code ] : $code,
					];
				}

				$created    = $this->from_gmt( $row['shipment_date_created_gmt'] );
				$status     = isset( $map[ $row['shipment_status'] ] ) ? $map[ $row['shipment_status'] ] : 'pending';
				$requests[] = $this->request(
					[
						'key'     => 'shiptastic:' . $row['shipment_id'],
						'order'   => $order,
						'status'  => $status,
						'items'   => $items,
						'created' => $created,
						'closed'  => 'rejected' === $status ? $this->from_gmt( $row['shipment_date_modified_gmt'] ) : 0,
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
