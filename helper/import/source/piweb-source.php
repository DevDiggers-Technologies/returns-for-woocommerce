<?php
/**
 * Import source: Cancel order / Refund request for WooCommerce (PI Web Solution).
 *
 * Whole-order cancellation requests in order meta (cancellation_date, predefined_reason, order_cancel_reason). The
 * plugin keeps no decision: the shop moves the order to Cancelled (approved) or back to Processing (declined). A new
 * request overwrites the old one, so only the latest exists.
 *
 * @package DevDiggers Returns
 * @version 1.0.0
 */

namespace DDWCWPReturns\Helper\Import\Source;

use DDWCWPReturns\Helper\Import\DDWCWR_Import_Source;

defined( 'ABSPATH' ) || exit();

if ( ! class_exists( 'DDWCWR_Piweb_Source' ) ) {
	/**
	 * PiWeb source class
	 */
	class DDWCWR_Piweb_Source extends DDWCWR_Import_Source {
		/**
		 * Label.
		 *
		 * @return string
		 */
		public function get_label() {
			return __( 'Cancel Order Request for WooCommerce', 'devdiggers-returns-for-woocommerce' );
		}

		/**
		 * Vendor.
		 *
		 * @return string
		 */
		public function get_vendor() {
			return 'PI Web Solution';
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
			return [ 'cancel-order-request-woocommerce', 'cancel-order-request-woocommerce-pro' ];
		}

		/**
		 * Count.
		 *
		 * @return int
		 */
		public function count() {
			return $this->count_orders_with_meta( 'cancellation_date' );
		}

		/**
		 * Read a batch.
		 *
		 * @param string $cursor Last order ID.
		 * @return array
		 */
		public function read( $cursor ) {
			$requests = [];
			$last     = '';

			foreach ( $this->order_ids_with_meta( 'cancellation_date', absint( $cursor ) ) as $order_id ) {
				$last  = (string) $order_id;
				$order = $this->get_order( $order_id );
				$date  = $order ? (string) $order->get_meta( 'cancellation_date' ) : '';

				if ( ! $order || '' === $date ) {
					continue;
				}

				$current = $order->get_status();

				if ( 'cancel-request' === $current ) {
					$status = 'pending';
				} elseif ( in_array( $current, [ 'cancelled', 'refunded' ], true ) ) {
					$status = 'completed';
				} else {
					$status = 'rejected';
				}

				// Stored as site-local "Y/m/d H:i".
				$created    = $this->from_local( str_replace( '/', '-', $date ) . ':00' );
				$requests[] = $this->request(
					[
						'key'       => 'piweb:' . $order_id . ':' . $date,
						'order'     => $order,
						'type'      => 'cancellation',
						'status'    => $status,
						'reason'    => (string) $order->get_meta( 'predefined_reason' ),
						'note'      => (string) $order->get_meta( 'order_cancel_reason' ),
						'all_items' => true,
						'created'   => $created,
						'closed'    => 'pending' === $status || ! $order->get_date_modified() ? 0 : $order->get_date_modified()->getTimestamp(),
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
