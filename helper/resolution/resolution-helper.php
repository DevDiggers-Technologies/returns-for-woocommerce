<?php
/**
 * Resolution helper: refunds, cancellations and restocking.
 *
 * @package WPReturns
 * @version 1.0.0
 */

namespace DDWCWPReturns\Helper\Resolution;

use DDWCWPReturns\Helper\Request\DDWCWR_Request_Helper;

defined( 'ABSPATH' ) || exit();

if ( ! class_exists( 'DDWCWR_Resolution_Helper' ) ) {
	/**
	 * Resolution helper class
	 */
	class DDWCWR_Resolution_Helper {
		/**
		 * Request helper.
		 *
		 * @var DDWCWR_Request_Helper
		 */
		protected $request_helper;

		/**
		 * Construct
		 */
		public function __construct() {
			$this->request_helper = new DDWCWR_Request_Helper();
		}

		/**
		 * Plain-text price for notes and messages.
		 *
		 * @param float $amount Amount.
		 * @param array $args wc_price() args.
		 * @return string
		 */
		protected function money( $amount, $args = [] ) {
			return html_entity_decode( wp_strip_all_tags( wc_price( $amount, $args ) ), ENT_QUOTES, 'UTF-8' );
		}

		/**
		 * Mark the parcel received and put items back in stock.
		 *
		 * @param int  $request_id Request ID.
		 * @param bool $restock Restock items (damaged and defective items are never restocked).
		 * @param int  $author_id Staff user.
		 * @return true|\WP_Error
		 */
		public function mark_received( $request_id, $restock, $author_id = 0 ) {
			$request = $this->request_helper->get_request( $request_id );

			if ( ! $request ) {
				return new \WP_Error( 'ddwcwr_request', __( 'Request not found.', 'wpreturns' ) );
			}

			$order = ddwcwr_order_adapter()->get_order( $request['order_id'] );

			if ( $restock && $order && 'cancellation' !== $request['type'] ) {
				foreach ( $this->request_helper->get_request_items( $request_id ) as $item ) {
					$qty = (int) $item['qty'] - (int) $item['restocked'];

					if ( $qty > 0 && ! in_array( $item['item_condition'], [ 'damaged', 'defective' ], true ) && ddwcwr_order_adapter()->restock_item( $order, $item['order_item_id'], $qty ) ) {
						$this->request_helper->update_item( $item['id'], [ 'restocked' => (int) $item['restocked'] + $qty ] );
						/* translators: 1: product name, 2: quantity */
						$this->request_helper->log( $request_id, sprintf( __( 'Restocked %1$s × %2$d.', 'wpreturns' ), $item['name'], $qty ), $author_id );
					}
				}
			}

			return $this->request_helper->change_status( $request_id, 'received', [ 'author_id' => $author_id ] );
		}

		/**
		 * Carry out the resolution of a request.
		 *
		 * @param int   $request_id Request ID.
		 * @param array $args refund_method (gateway|manual; via_gateway still works), amount (optional override), author_id, complete.
		 * @return array|\WP_Error Result summary.
		 */
		public function process( $request_id, $args = [] ) {
			global $ddwcwr_configuration;

			$request = $this->request_helper->get_request( $request_id );

			if ( ! $request ) {
				return new \WP_Error( 'ddwcwr_request', __( 'Request not found.', 'wpreturns' ) );
			}

			if ( ! empty( $request['meta']['processed'] ) ) {
				return new \WP_Error( 'ddwcwr_processed', __( 'This request has already been resolved.', 'wpreturns' ) );
			}

			if ( in_array( $request['status'], [ 'rejected', 'cancelled' ], true ) ) {
				return new \WP_Error( 'ddwcwr_status', __( 'Rejected or canceled requests cannot be resolved.', 'wpreturns' ) );
			}

			$args = wp_parse_args(
				$args,
				[
					'via_gateway' => 'yes' === $ddwcwr_configuration['refund_via_gateway'],
					'amount'      => null,
					'author_id'   => get_current_user_id(),
					'complete'    => true,
				]
			);

			if ( empty( $args['refund_method'] ) || ! in_array( $args['refund_method'], [ 'gateway', 'manual' ], true ) ) {
				$args['refund_method'] = $args['via_gateway'] ? 'gateway' : 'manual';
			}

			$order = ddwcwr_order_adapter()->get_order( $request['order_id'] );

			if ( ! $order ) {
				return new \WP_Error( 'ddwcwr_order', __( 'The order of this request no longer exists.', 'wpreturns' ) );
			}

			$items  = $this->request_helper->get_request_items( $request_id );
			$amount = null === $args['amount'] ? (float) $request['resolution_total'] : max( 0, (float) $args['amount'] );
			$lines  = [];

			foreach ( $items as $item ) {
				$lines[ $item['order_item_id'] ] = (int) $item['qty'];
			}

			// Every request is refunded here; a store credit or exchange request saved by Pro becomes a refund.
			$fields = [
				'meta'       => $request['meta'],
				'resolution' => 'refund',
			];
			$result = [ 'resolution' => 'refund' ];
			/* translators: %s: request number */
			$cancel_note = sprintf( __( 'Canceled by customer request %s.', 'wpreturns' ), $request['number'] );

			if ( 'cancellation' === $request['type'] && ! $order->get_date_paid() ) {
				ddwcwr_order_adapter()->cancel_order( $order, $cancel_note );
				$result['message'] = __( 'The order was canceled. It was not paid, so there was nothing to refund.', 'wpreturns' );
			} else {
				$refunded = 0;

				if ( $amount > 0 ) {
					/* translators: %s: request number */
					$refund = ddwcwr_order_adapter()->create_refund( $order, $amount, $lines, sprintf( __( 'WPReturns %s', 'wpreturns' ), $request['number'] ), 'gateway' === $args['refund_method'], 'cancellation' === $request['type'] );

					if ( is_wp_error( $refund ) ) {
						return $refund;
					}

					$fields['refund_id']   = $refund->get_id();
					$refunded              = (float) $refund->get_amount();
					$result['via_gateway'] = $refund->get_refunded_payment();
				}

				$fields['refunded_total'] = $refunded;
				$result['amount']         = $refunded;

				if ( 'cancellation' === $request['type'] && 'refunded' !== $order->get_status() ) {
					ddwcwr_order_adapter()->cancel_order( $order, $cancel_note );
				}

				$result['message'] = $refunded > 0
					/* translators: 1: amount, 2: how */
					? sprintf( __( 'Refunded %1$s %2$s.', 'wpreturns' ), $this->money( $refunded, [ 'currency' => $order->get_currency() ] ), ! empty( $result['via_gateway'] ) ? __( 'to the original payment method', 'wpreturns' ) : __( '(recorded in WooCommerce; pay it back manually)', 'wpreturns' ) )
					: __( 'Resolved without a refund.', 'wpreturns' );
			}

			$meta              = $fields['meta'];
			$meta['processed'] = current_time( 'mysql' );
			$meta['result']    = $result['message'];
			$fields['meta']    = $meta;

			$this->request_helper->update_request( $request_id, $fields );
			$this->request_helper->log( $request_id, $result['message'], $args['author_id'] );

			// Complete first, so the resolution email and webhooks show the final status. The status email stays quiet
			// for a processed request: the resolution email covers it.
			if ( $args['complete'] ) {
				$this->request_helper->change_status( $request_id, 'completed', [ 'author_id' => $args['author_id'] ] );
			}

			do_action( 'ddwcwr_resolution_processed', $request_id, $result, $this->request_helper->get_request( $request_id ) );

			return $result;
		}
	}
}
