<?php
/**
 * Import source: Return Refund and Exchange for WooCommerce (WP Swings).
 *
 * One request per order, in order meta wps_rma_return_product keyed by the request time. Order messages are in
 * wps_cutomer_order_msg (the plugin's own spelling) and photos in wps_rma_return_attachment.
 *
 * @package WPReturns
 * @version 1.0.0
 */

namespace DDWCWPReturns\Helper\Import\Source;

use DDWCWPReturns\Helper\Import\DDWCWR_Import_Source;

defined( 'ABSPATH' ) || exit();

if ( ! class_exists( 'DDWCWR_Wps_Source' ) ) {
	/**
	 * WP Swings source class
	 */
	class DDWCWR_Wps_Source extends DDWCWR_Import_Source {
		/**
		 * Label.
		 *
		 * @return string
		 */
		public function get_label() {
			return __( 'Return Refund and Exchange for WooCommerce', 'wpreturns' );
		}

		/**
		 * Vendor.
		 *
		 * @return string
		 */
		public function get_vendor() {
			return 'WP Swings';
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
			return [ 'woo-refund-and-exchange-lite', 'woocommerce-rma-for-return-refund-and-exchange' ];
		}

		/**
		 * Count.
		 *
		 * @return int
		 */
		public function count() {
			global $wpdb;

			list( $table ) = $this->order_meta_table();
			$total         = 0;

			// One order can hold several requests (one per request time), so count the requests, not the orders.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from order_meta_table().
			foreach ( $wpdb->get_col( $wpdb->prepare( "SELECT meta_value FROM {$table} WHERE meta_key = %s", 'wps_rma_return_product' ) ) as $value ) {
				$entries = maybe_unserialize( $value );

				foreach ( is_array( $entries ) ? $entries : [] as $entry ) {
					$total += is_array( $entry ) && ! empty( $entry['products'] ) ? 1 : 0;
				}
			}

			return $total;
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

			foreach ( $this->order_ids_with_meta( 'wps_rma_return_product', absint( $cursor ) ) as $order_id ) {
				$last  = (string) $order_id;
				$order = $this->get_order( $order_id );

				if ( $order ) {
					$requests = array_merge( $requests, $this->read_order( $order ) );
				}
			}

			return [
				'requests' => $requests,
				'cursor'   => $last,
			];
		}

		/**
		 * Requests of one order.
		 *
		 * @param \WC_Order $order Order.
		 * @return array
		 */
		protected function read_order( $order ) {
			$entries  = $order->get_meta( 'wps_rma_return_product' );
			$files    = (array) $order->get_meta( 'wps_rma_return_attachment' );
			$messages = $this->read_messages( $order );
			$requests = [];

			foreach ( is_array( $entries ) ? $entries : [] as $time => $entry ) {
				if ( ! is_array( $entry ) || empty( $entry['products'] ) ) {
					continue;
				}

				$items = [];

				foreach ( (array) $entry['products'] as $product ) {
					if ( ! empty( $product['item_id'] ) ) {
						$items[ absint( $product['item_id'] ) ] = [ 'qty' => isset( $product['qty'] ) ? absint( $product['qty'] ) : 1 ];
					}
				}

				$status  = isset( $entry['status'] ) ? $entry['status'] : 'pending';
				$created = is_numeric( $time ) ? (int) $time : 0;
				$closed  = 0;

				if ( 'complete' === $status ) {
					$status = $order->get_total_refunded() > 0 ? 'completed' : 'approved';
					$closed = isset( $entry['approve_date'] ) ? (int) $entry['approve_date'] : 0;
				} elseif ( 'cancel' === $status ) {
					// The shop declining and the customer withdrawing both store "cancel"; only the order note tells them apart.
					$status = $this->cancelled_by_customer( $order ) ? 'cancelled' : 'rejected';
					$closed = isset( $entry['cancel_date'] ) ? (int) $entry['cancel_date'] : 0;
				} else {
					$status = 'pending';
				}

				$note = isset( $entry['reason'] ) ? (string) $entry['reason'] : '';

				// WP Swings files photos under the request's date ("d-m-Y"), and "<order id>-" when none was sent.
				$day  = is_numeric( $time ) ? gmdate( 'd-m-Y', (int) $time ) : '';
				$sent = isset( $files[ $day ]['files'] ) ? (array) $files[ $day ]['files'] : [];

				// The only request on the order owns every photo, whatever date they were filed under.
				if ( ! $sent && 1 === count( $entries ) ) {
					foreach ( $files as $upload ) {
						$sent = isset( $upload['files'] ) ? array_merge( $sent, (array) $upload['files'] ) : $sent;
					}
				}

				$photos = $sent ? array_filter(
					array_map( 'wp_basename', $sent ),
					function ( $name ) use ( $order ) {
						return '' !== $name && $order->get_id() . '-' !== $name;
					}
				) : [];

				if ( $photos ) {
					/* translators: %s: file names */
					$note .= "\n" . sprintf( __( 'Photos sent with the request: %s', 'wpreturns' ), implode( ', ', $photos ) );
				}

				$requests[] = $this->request(
					[
						'key'      => 'wps:' . $order->get_id() . ':' . $time,
						'order'    => $order,
						'status'   => $status,
						'reason'   => isset( $entry['subject'] ) ? (string) $entry['subject'] : '',
						'note'     => trim( $note ),
						'items'    => $items,
						'created'  => $created,
						'closed'   => $closed,
						'messages' => $messages,
					]
				);

				// Messages belong to the order, so they go with its first request only.
				$messages = [];
			}

			return $requests;
		}

		/**
		 * Order messages.
		 *
		 * @param \WC_Order $order Order.
		 * @return array
		 */
		protected function read_messages( $order ) {
			$messages = [];

			foreach ( (array) $order->get_meta( 'wps_cutomer_order_msg' ) as $group ) {
				foreach ( (array) $group as $time => $message ) {
					if ( ! is_array( $message ) || '' === trim( isset( $message['msg'] ) ? (string) $message['msg'] : '' ) ) {
						continue;
					}

					$text = $message['msg'];

					if ( ! empty( $message['files'] ) ) {
						/* translators: %s: file names */
						$text .= "\n" . sprintf( __( 'Attached: %s', 'wpreturns' ), implode( ', ', array_map( 'wp_basename', (array) $message['files'] ) ) );
					}

					$messages[] = $this->message( isset( $message['sender'] ) && 'Customer' === $message['sender'] ? 'customer' : 'staff', $text, (int) $time );
				}
			}

			return $messages;
		}

		/**
		 * Whether the customer cancelled the request (an order note the plugin adds).
		 *
		 * @param \WC_Order $order Order.
		 * @return bool
		 */
		protected function cancelled_by_customer( $order ) {
			$texts = array_unique(
				[
					'User Request of Refund is cancelled by customer',
					// The other plugin's own note, in the site language when that plugin is active.
					translate( 'User Request of Refund is cancelled by customer', 'woo-refund-and-exchange-lite' ), // phpcs:ignore WordPress.WP.I18n

				]
			);

			foreach ( $texts as $text ) {
				if ( $this->note_date( $order, $text ) ) {
					return true;
				}
			}

			return false;
		}
	}
}
