<?php
/**
 * WooCommerce order adapter
 *
 * @package DevDiggers Returns
 * @version 1.0.0
 */

namespace DDWCWPReturns\Helper\Order;

defined( 'ABSPATH' ) || exit();

if ( ! class_exists( 'DDWCWR_WooCommerce_Order_Adapter' ) ) {
	/**
	 * WooCommerce implementation of the order adapter.
	 */
	class DDWCWR_WooCommerce_Order_Adapter implements DDWCWR_Order_Adapter_Interface {
		/**
		 * Get an order object.
		 *
		 * @param int $order_id Order ID.
		 * @return \WC_Order|false
		 */
		public function get_order( $order_id ) {
			$order = wc_get_order( absint( $order_id ) );

			return $order instanceof \WC_Order && ! $order instanceof \WC_Order_Refund ? $order : false;
		}

		/**
		 * Find an order by the number the customer sees and the billing email.
		 *
		 * @param string $order_number Order number.
		 * @param string $email Billing email.
		 * @return \WC_Order|false
		 */
		public function find_order( $order_number, $email ) {
			$order_number = trim( ltrim( (string) $order_number, '#' ) );
			$email        = strtolower( trim( (string) $email ) );

			if ( '' === $order_number || ! is_email( $email ) ) {
				return false;
			}

			// Sequential order number plugins can map the visible number to an ID.
			$order_id = absint( apply_filters( 'ddwcwr_find_order_id_by_number', absint( $order_number ), $order_number ) );
			$order    = $this->get_order( $order_id );

			if ( $order && (string) $order->get_order_number() !== $order_number && (string) $order->get_id() !== $order_number ) {
				$order = false;
			}

			if ( ! $order || strtolower( $order->get_billing_email() ) !== $email ) {
				return false;
			}

			return $order;
		}

		/**
		 * Order summary.
		 *
		 * @param \WC_Order $order Order.
		 * @return array
		 */
		public function get_order_data( $order ) {
			$completed = $order->get_date_completed() ? $order->get_date_completed() : ( $order->get_date_paid() ? $order->get_date_paid() : $order->get_date_created() );

			return [
				'id'                   => $order->get_id(),
				'number'               => $order->get_order_number(),
				'customer_id'          => $order->get_customer_id(),
				'email'                => $order->get_billing_email(),
				'name'                 => trim( $order->get_formatted_billing_full_name() ),
				'country'              => $order->get_billing_country() ? $order->get_billing_country() : $order->get_shipping_country(),
				'status'               => 'wc-' . $order->get_status(),
				'date_created'         => $order->get_date_created() ? $order->get_date_created()->getTimestamp() : time(),
				'date_completed'       => $completed ? $completed->getTimestamp() : time(),
				'is_completed'         => (bool) $order->get_date_completed(),
				'total'                => (float) $order->get_total(),
				'refunded'             => (float) $order->get_total_refunded(),
				'currency'             => $order->get_currency(),
				'payment_method_title' => $order->get_payment_method_title(),
				'shipping_address'     => $order->get_formatted_shipping_address() ? $order->get_formatted_shipping_address() : $order->get_formatted_billing_address(),
				'view_url'             => $order->get_view_order_url(),
				'digital_consent'      => absint( $order->get_meta( '_ddwcwr_digital_consent' ) ),
			];
		}

		/**
		 * Line items with refundable quantities and unit values.
		 *
		 * @param \WC_Order $order Order.
		 * @return array
		 */
		public function get_order_items( $order ) {
			$items = [];

			foreach ( $order->get_items( 'line_item' ) as $item_id => $item ) {
				$qty      = (int) $item->get_quantity();
				$refunded = absint( $order->get_qty_refunded_for_item( $item_id ) );

				if ( $qty <= 0 ) {
					continue;
				}

				$product = $item->get_product();
				$gross   = (float) $item->get_total() + (float) $item->get_total_tax();

				$items[ $item_id ] = [
					'id'           => $item_id,
					'product_id'   => $item->get_product_id(),
					'variation_id' => $item->get_variation_id(),
					'name'         => $item->get_name(),
					'qty'          => $qty,
					'refunded_qty' => $refunded,
					'unit_value'   => $qty ? round( $gross / $qty, wc_get_price_decimals() ) : 0,
					'image'        => $product ? $product->get_image( [ 64, 64 ] ) : wc_placeholder_img( [ 64, 64 ] ),
					'parent_id'    => $product && $product->get_parent_id() ? $product->get_parent_id() : $item->get_product_id(),
					'downloadable' => $product && $product->is_downloadable(),
				];
			}

			return $items;
		}

		/**
		 * Whether a customer owns the order.
		 *
		 * @param \WC_Order $order Order.
		 * @param int       $user_id User ID.
		 * @param string    $email Email.
		 * @return bool
		 */
		public function order_belongs_to( $order, $user_id, $email = '' ) {
			if ( $user_id && (int) $order->get_customer_id() === (int) $user_id ) {
				return true;
			}

			return ! $order->get_customer_id() && $email && strtolower( $order->get_billing_email() ) === strtolower( $email );
		}

		/**
		 * Refund an amount.
		 *
		 * @param \WC_Order $order Order.
		 * @param float     $amount Amount.
		 * @param array     $lines [ order_item_id => qty ].
		 * @param string    $reason Reason.
		 * @param bool      $via_gateway Through the gateway.
		 * @param bool      $restock Restock.
		 * @return \WC_Order_Refund|\WP_Error
		 */
		public function create_refund( $order, $amount, $lines, $reason, $via_gateway, $restock ) {
			$amount    = wc_format_decimal( min( (float) $amount, (float) $order->get_remaining_refund_amount() ), wc_get_price_decimals() );
			$gross     = 0;
			$breakdown = [];

			foreach ( $lines as $item_id => $qty ) {
				$item = $order->get_item( $item_id );

				if ( ! $item || ! $item->get_quantity() ) {
					continue;
				}

				$ratio                 = min( 1, $qty / $item->get_quantity() );
				$taxes                 = $item->get_taxes();
				$breakdown[ $item_id ] = [
					'qty'   => $qty,
					'total' => (float) $item->get_total() * $ratio,
					'taxes' => array_map(
						function ( $tax ) use ( $ratio ) {
							return (float) $tax * $ratio;
						},
						isset( $taxes['total'] ) ? (array) $taxes['total'] : []
					),
				];
				$gross                += $breakdown[ $item_id ]['total'] + array_sum( $breakdown[ $item_id ]['taxes'] );
			}

			if ( $amount <= 0 ) {
				return new \WP_Error( 'ddwcwr_refund_amount', __( 'There is nothing left to refund on this order.', 'devdiggers-returns' ) );
			}

			// Fees make the refund smaller than the items: scale every line so the record adds up.
			$scale      = $gross > 0 ? min( 1, $amount / $gross ) : 0;
			$line_items = [];

			foreach ( $breakdown as $item_id => $line ) {
				$line_items[ $item_id ] = [
					'qty'          => $line['qty'],
					'refund_total' => wc_format_decimal( $line['total'] * $scale, wc_get_price_decimals() ),
					'refund_tax'   => array_map(
						function ( $tax ) use ( $scale ) {
							return wc_format_decimal( $tax * $scale, wc_get_price_decimals() );
						},
						$line['taxes']
					),
				];
			}

			$gateway = wc_get_payment_gateway_by_order( $order );

			if ( $via_gateway && ( ! $gateway || ! $gateway->supports( 'refunds' ) ) ) {
				$via_gateway = false;
			}

			return wc_create_refund(
				[
					'amount'         => $amount,
					'reason'         => $reason,
					'order_id'       => $order->get_id(),
					'line_items'     => $line_items,
					'refund_payment' => (bool) $via_gateway,
					'restock_items'  => (bool) $restock,
				]
			);
		}

		/**
		 * Put returned stock back.
		 *
		 * @param \WC_Order $order Order.
		 * @param int       $order_item_id Item ID.
		 * @param int       $qty Quantity.
		 * @return bool
		 */
		public function restock_item( $order, $order_item_id, $qty ) {
			$item = $order->get_item( $order_item_id );

			if ( ! $item || $qty <= 0 ) {
				return false;
			}

			$product = $item->get_product();

			if ( ! $product || ! $product->managing_stock() ) {
				return false;
			}

			$new_stock = wc_update_product_stock( $product, $qty, 'increase' );

			if ( is_wp_error( $new_stock ) || false === $new_stock ) {
				return false;
			}

			/* translators: 1: product name, 2: quantity, 3: new stock */
			$order->add_order_note( sprintf( __( 'DevDiggers Returns restocked %1$s × %2$d (stock now %3$s).', 'devdiggers-returns' ), $product->get_formatted_name(), $qty, $new_stock ) );

			return true;
		}

		/**
		 * Add a note to an order.
		 *
		 * @param \WC_Order $order Order.
		 * @param string    $note Note.
		 * @return void
		 */
		public function add_order_note( $order, $note ) {
			$order->add_order_note( $note );
		}

		/**
		 * Cancel an order.
		 *
		 * @param \WC_Order $order Order.
		 * @param string    $note Note.
		 * @return void
		 */
		public function cancel_order( $order, $note ) {
			$order->update_status( 'cancelled', $note );
		}

		/**
		 * Recent orders of a customer.
		 *
		 * @param int $customer_id Customer ID.
		 * @param int $limit Limit.
		 * @return array
		 */
		public function get_customer_orders( $customer_id, $limit = 20 ) {
			return wc_get_orders(
				[
					'customer_id' => absint( $customer_id ),
					'limit'       => $limit,
					'type'        => 'shop_order',
					'orderby'     => 'date',
					'order'       => 'DESC',
				]
			);
		}
	}
}
