<?php
/**
 * Order adapter interface
 *
 * Every order, item, refund, stock, customer and coupon call the returns logic makes goes through this
 * interface, so another commerce platform can be added by writing one more implementation.
 *
 * @package WPReturns
 * @version 1.0.0
 */

namespace DDWCWPReturns\Helper\Order;

defined( 'ABSPATH' ) || exit();

/**
 * Order adapter interface.
 */
interface DDWCWR_Order_Adapter_Interface {
	/**
	 * Get an order object.
	 *
	 * @param int $order_id Order ID.
	 * @return object|false
	 */
	public function get_order( $order_id );

	/**
	 * Find an order by the number the customer sees and the billing email.
	 *
	 * @param string $order_number Order number.
	 * @param string $email Billing email.
	 * @return object|false
	 */
	public function find_order( $order_number, $email );

	/**
	 * Order summary used by the returns screens.
	 *
	 * @param object $order Order.
	 * @return array id, number, customer_id, email, name, country, status, date_created, date_completed, total, currency, payment_method_title.
	 */
	public function get_order_data( $order );

	/**
	 * Line items with refundable quantities and unit values.
	 *
	 * @param object $order Order.
	 * @return array
	 */
	public function get_order_items( $order );

	/**
	 * Whether a customer (account or email) owns the order.
	 *
	 * @param object $order Order.
	 * @param int    $user_id User ID.
	 * @param string $email Email.
	 * @return bool
	 */
	public function order_belongs_to( $order, $user_id, $email = '' );

	/**
	 * Refund an amount, recording line items, optionally through the gateway.
	 *
	 * @param object $order Order.
	 * @param float  $amount Amount.
	 * @param array  $lines [ order_item_id => qty ].
	 * @param string $reason Reason.
	 * @param bool   $via_gateway Refund through the payment gateway.
	 * @param bool   $restock Restock items.
	 * @return object|\WP_Error Refund object.
	 */
	public function create_refund( $order, $amount, $lines, $reason, $via_gateway, $restock );

	/**
	 * Put returned stock back.
	 *
	 * @param object $order Order.
	 * @param int    $order_item_id Item ID.
	 * @param int    $qty Quantity.
	 * @return bool
	 */
	public function restock_item( $order, $order_item_id, $qty );

	/**
	 * Add a note to an order.
	 *
	 * @param object $order Order.
	 * @param string $note Note.
	 * @return void
	 */
	public function add_order_note( $order, $note );

	/**
	 * Cancel an order (cancellation requests).
	 *
	 * @param object $order Order.
	 * @param string $note Note.
	 * @return void
	 */
	public function cancel_order( $order, $note );

	/**
	 * Orders of a customer that can still get a request, newest first.
	 *
	 * @param int $customer_id Customer ID.
	 * @param int $limit Limit.
	 * @return array Orders.
	 */
	public function get_customer_orders( $customer_id, $limit = 20 );
}
