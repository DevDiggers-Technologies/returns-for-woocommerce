<?php
/**
 * Create a request for a customer (phone, email or in-store returns).
 *
 * @package DevDiggers Returns
 * @version 1.0.0
 */

namespace DDWCWPReturns\Templates\Admin\Request;

use DDWCWPReturns\Helper\Request\DDWCWR_Request_Helper;

defined( 'ABSPATH' ) || exit();

if ( ! class_exists( 'DDWCWR_Request_Create_Template' ) ) {
	/**
	 * Create request template class
	 */
	class DDWCWR_Request_Create_Template {
		/**
		 * Construct
		 */
		public function __construct() {
			// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only routing.
			$order_id = ! empty( $_GET['order-id'] ) ? absint( ltrim( sanitize_text_field( wp_unslash( $_GET['order-id'] ) ), '#' ) ) : 0;
			$type     = ! empty( $_GET['type'] ) ? sanitize_key( wp_unslash( $_GET['type'] ) ) : 'return';

			if ( ! empty( $_GET['ddwcwr-message'] ) ) {
				ddfw_print_notification( sanitize_text_field( wp_unslash( $_GET['ddwcwr-message'] ) ), ! empty( $_GET['ddwcwr-error'] ) ? 'error' : 'success' );
			}
			// phpcs:enable

			$order = $order_id ? ddwcwr_order_adapter()->get_order( $order_id ) : false;
			?>
			<div class="ddfw-edit-screen-bar">
				<div class="ddfw-edit-screen-bar-title">
					<div class="ddfw-edit-screen-bar-heading">
						<h2><?php esc_html_e( 'New request', 'devdiggers-returns-for-woocommerce' ); ?></h2>
						<a class="ddfw-edit-screen-back" href="<?php echo esc_url( admin_url( 'admin.php?page=ddwcwr-dashboard&menu=requests' ) ); ?>">
							<svg width="15" height="15" viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false"><path d="M15 19.5 7.7 12.2l7.3-7.3" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"></path></svg>
							<?php esc_html_e( 'Requests', 'devdiggers-returns-for-woocommerce' ); ?>
						</a>
					</div>
					<p><?php esc_html_e( 'Create a request for a customer who called, emailed or came to the store. Return windows and rules do not block requests you create.', 'devdiggers-returns-for-woocommerce' ); ?></p>
				</div>
			</div>

			<div class="ddfw-edit-screen ddfw-edit-screen-single">
				<div class="ddfw-edit-screen-main">
					<form method="get" class="ddfw-fields-section">
						<?php
						ddfw_fields_heading(
							[
								'heading'     => esc_html__( '1. Find the order', 'devdiggers-returns-for-woocommerce' ),
								'description' => esc_html__( 'Load the order first, then choose the items below.', 'devdiggers-returns-for-woocommerce' ),
							]
						);
						?>
						<input type="hidden" name="page" value="ddwcwr-dashboard" />
						<input type="hidden" name="menu" value="requests" />
						<input type="hidden" name="action" value="add" />
						<table class="form-table" role="presentation">
							<tr>
								<th scope="row"><label for="ddwcwr-order-id"><?php esc_html_e( 'Order number', 'devdiggers-returns-for-woocommerce' ); ?></label></th>
								<td>
									<input type="text" id="ddwcwr-order-id" name="order-id" class="regular-text" value="<?php echo esc_attr( $order_id ? $order_id : '' ); ?>" aria-describedby="ddwcwr-order-id-description" />
									<p class="description" id="ddwcwr-order-id-description"><?php esc_html_e( 'The WooCommerce order number, with or without #.', 'devdiggers-returns-for-woocommerce' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="ddwcwr-new-type"><?php esc_html_e( 'Request type', 'devdiggers-returns-for-woocommerce' ); ?></label></th>
								<td>
									<select id="ddwcwr-new-type" name="type" aria-describedby="ddwcwr-new-type-description">
										<?php foreach ( ddwcwr_get_request_types() as $slug => $label ) : ?>
											<?php if ( 'withdrawal' !== $slug ) : ?>
												<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $type, $slug ); ?>><?php echo esc_html( $label ); ?></option>
											<?php endif; ?>
										<?php endforeach; ?>
									</select>
									<p class="description" id="ddwcwr-new-type-description"><?php esc_html_e( 'Return or cancellation. EU withdrawals can only come from the customer.', 'devdiggers-returns-for-woocommerce' ); ?></p>
								</td>
							</tr>
						</table>
						<p><button type="submit" class="button"><?php esc_html_e( 'Load order', 'devdiggers-returns-for-woocommerce' ); ?></button></p>
					</form>

					<?php
					if ( $order_id && ! $order ) {
						ddfw_print_notification( esc_html__( 'Order not found.', 'devdiggers-returns-for-woocommerce' ), 'error' );
					}

					if ( $order ) :
						$items = ( new DDWCWR_Request_Helper() )->get_eligible_items( $order, $type );
						?>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ddfw-fields-section">
							<?php
							ddfw_fields_heading(
								[
									/* translators: 1: order number, 2: customer */
									'heading'     => esc_html( sprintf( __( '2. Items of order #%1$s (%2$s)', 'devdiggers-returns-for-woocommerce' ), $order->get_order_number(), $order->get_formatted_billing_full_name() ) ),
									'description' => esc_html__( 'Set a quantity for each item the customer is sending back. Leave 0 for items that stay with the customer.', 'devdiggers-returns-for-woocommerce' ),
								]
							);
							?>
							<div class="ddfw-table-wrapper">
								<table class="widefat fixed ddfw-table">
									<thead>
										<tr>
											<th><?php esc_html_e( 'Item', 'devdiggers-returns-for-woocommerce' ); ?></th>
											<th><?php esc_html_e( 'Quantity', 'devdiggers-returns-for-woocommerce' ); ?></th>
											<th><?php esc_html_e( 'Reason', 'devdiggers-returns-for-woocommerce' ); ?></th>
											<th><?php esc_html_e( 'Condition', 'devdiggers-returns-for-woocommerce' ); ?></th>
										</tr>
									</thead>
									<tbody>
										<?php foreach ( $items as $item_id => $item ) : ?>
											<tr>
												<td><strong><?php echo esc_html( $item['name'] ); ?></strong><?php echo $item['message'] ? '<span class="ddwcwr-list-meta">' . esc_html( $item['message'] ) . '</span>' : ''; ?></td>
												<td>
													<select name="items[<?php echo esc_attr( $item_id ); ?>][qty]" aria-label="<?php esc_attr_e( 'Quantity', 'devdiggers-returns-for-woocommerce' ); ?>">
														<?php for ( $qty = 0; $qty <= $item['available']; $qty++ ) : ?>
															<option value="<?php echo esc_attr( $qty ); ?>"><?php echo esc_html( $qty ); ?></option>
														<?php endfor; ?>
													</select>
												</td>
												<td>
													<select name="items[<?php echo esc_attr( $item_id ); ?>][reason]" aria-label="<?php esc_attr_e( 'Reason', 'devdiggers-returns-for-woocommerce' ); ?>">
														<?php foreach ( ddwcwr_get_reasons() as $slug => $reason ) : ?>
															<option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $reason['label'] ); ?></option>
														<?php endforeach; ?>
													</select>
												</td>
												<td>
													<select name="items[<?php echo esc_attr( $item_id ); ?>][condition]" aria-label="<?php esc_attr_e( 'Condition', 'devdiggers-returns-for-woocommerce' ); ?>">
														<option value=""><?php esc_html_e( 'Not checked', 'devdiggers-returns-for-woocommerce' ); ?></option>
														<?php foreach ( ddwcwr_get_item_conditions() as $slug => $label ) : ?>
															<option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $label ); ?></option>
														<?php endforeach; ?>
													</select>
												</td>
											</tr>
										<?php endforeach; ?>
									</tbody>
								</table>
							</div>
							<table class="form-table" role="presentation">
								<tr>
									<th scope="row"><label for="ddwcwr-new-note"><?php esc_html_e( 'Note', 'devdiggers-returns-for-woocommerce' ); ?></label></th>
									<td>
										<textarea id="ddwcwr-new-note" name="note" rows="3" class="large-text" aria-describedby="ddwcwr-new-note-description"></textarea>
										<p class="description" id="ddwcwr-new-note-description"><?php esc_html_e( 'Saved as the customer\'s note on the request, for example what they told you on the phone.', 'devdiggers-returns-for-woocommerce' ); ?></p>
									</td>
								</tr>
							</table>
							<input type="hidden" name="action" value="ddwcwr_create_request" />
							<input type="hidden" name="order_id" value="<?php echo esc_attr( $order->get_id() ); ?>" />
							<input type="hidden" name="type" value="<?php echo esc_attr( $type ); ?>" />
							<?php wp_nonce_field( 'ddwcwr_create_request', 'ddwcwr_nonce' ); ?>
							<p><button type="submit" class="button button-primary"><?php esc_html_e( 'Create request', 'devdiggers-returns-for-woocommerce' ); ?></button></p>
						</form>
					<?php endif; ?>
				</div>
			</div>
			<?php
		}
	}
}
