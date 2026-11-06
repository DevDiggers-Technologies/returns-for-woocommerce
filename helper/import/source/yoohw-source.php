<?php
/**
 * Import source: Order Cancellation & Returns for WooCommerce (YoOhw Studio).
 *
 * Whole-order requests in order meta: one cancellation (request_cancellation_reason, or cancellation_reason when the
 * order was cancelled at once) and one return (request_return_reason) per order. No request date is stored; the
 * order note the plugin adds with the customer's reason gives it.
 *
 * @package DevDiggers Returns
 * @version 1.0.0
 */

namespace DDWCWPReturns\Helper\Import\Source;

use DDWCWPReturns\Helper\Import\DDWCWR_Import_Source;

defined( 'ABSPATH' ) || exit();

if ( ! class_exists( 'DDWCWR_Yoohw_Source' ) ) {
	/**
	 * YoOhw source class
	 */
	class DDWCWR_Yoohw_Source extends DDWCWR_Import_Source {
		/**
		 * Meta keys that mark a request.
		 *
		 * @var array
		 */
		protected $keys = [ 'request_cancellation_reason', 'cancellation_reason', 'request_return_reason' ];

		/**
		 * Label.
		 *
		 * @return string
		 */
		public function get_label() {
			return __( 'Order Cancellation & Returns', 'devdiggers-returns' );
		}

		/**
		 * Vendor.
		 *
		 * @return string
		 */
		public function get_vendor() {
			return 'YoOhw Studio';
		}

		/**
		 * Request types it holds.
		 *
		 * @return array
		 */
		public function get_types() {
			return [ 'cancellation', 'return' ];
		}

		/**
		 * Plugin folders (free and paid).
		 *
		 * @return array
		 */
		public function get_plugin_dirs() {
			return [ 'wc-order-cancellation-return' ];
		}

		/**
		 * Count: orders with a cancellation plus orders with a return.
		 *
		 * @return int
		 */
		public function count() {
			return $this->count_orders_with_meta( [ 'request_cancellation_reason', 'cancellation_reason' ] ) + $this->count_orders_with_meta( 'request_return_reason' );
		}

		/**
		 * Read a batch of orders.
		 *
		 * @param string $cursor Last order ID.
		 * @return array
		 */
		public function read( $cursor ) {
			$requests = [];
			$last     = '';

			foreach ( $this->order_ids_with_meta( $this->keys, absint( $cursor ) ) as $order_id ) {
				$last  = (string) $order_id;
				$order = $this->get_order( $order_id );

				if ( ! $order ) {
					continue;
				}

				$cancel_reason = (string) $order->get_meta( 'request_cancellation_reason' );
				$instant       = (string) $order->get_meta( 'cancellation_reason' );

				if ( '' !== $cancel_reason || '' !== $instant ) {
					$rejected   = (string) $order->get_meta( 'order_cancellation_rejection_reason' );
					$requests[] = $this->build( $order, 'cancellation', '' !== $cancel_reason ? $cancel_reason : $instant, '' !== $instant || $order->get_meta( 'request_cancellation_approved' ) || $order->has_status( 'cancelled' ), $rejected, $order->has_status( 'request-cancel' ) );
				}

				$return_reason = (string) $order->get_meta( 'request_return_reason' );

				if ( '' !== $return_reason ) {
					$rejected   = (string) $order->get_meta( 'order_return_rejection_reason' );
					$requests[] = $this->build( $order, 'return', $return_reason, $order->get_meta( 'request_return_approved' ) || $order->has_status( 'refunded' ), $rejected, $order->has_status( 'request-return' ) );
				}
			}

			return [
				'requests' => $requests,
				'cursor'   => $last,
			];
		}

		/**
		 * Build a request.
		 *
		 * @param \WC_Order $order    Order.
		 * @param string    $type     cancellation|return.
		 * @param string    $reason   Reason (a preset or the customer's own words).
		 * @param bool      $approved Approved.
		 * @param string    $rejected The shop's rejection reason.
		 * @param bool      $waiting  Order still waits for a decision.
		 * @return array
		 */
		protected function build( $order, $type, $reason, $approved, $rejected, $waiting ) {
			if ( '' !== $rejected ) {
				$status = 'rejected';
			} elseif ( $approved && ! $waiting ) {
				$status = 'completed';
			} else {
				$status = 'pending';
			}

			$created = $this->note_date( $order, $reason );

			if ( ! $created && $order->get_date_modified() ) {
				$created = $order->get_date_modified()->getTimestamp();
			}

			$closed = 'pending' === $status || ! $order->get_date_modified() ? 0 : $order->get_date_modified()->getTimestamp();

			return $this->request(
				[
					'key'       => 'yoohw:' . $type . ':' . $order->get_id(),
					'order'     => $order,
					'type'      => $type,
					'status'    => $status,
					'reason'    => $reason,
					'all_items' => true,
					'created'   => $created,
					'closed'    => $closed,
					'messages'  => '' !== $rejected ? [ $this->message( 'staff', $rejected, $closed ) ] : [],
				]
			);
		}
	}
}
