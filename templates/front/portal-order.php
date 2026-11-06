<?php
/**
 * Guest portal: order summary.
 *
 * @package DevDiggers Returns
 * @version 1.0.0
 *
 * @var object $order      Order.
 * @var array  $order_data Order summary.
 * @var array  $items      Items.
 */

defined( 'ABSPATH' ) || exit();
?>
<div class="ddwcwr-card ddwcwr-portal-order">
	<span class="ddwcwr-eyebrow"><?php esc_html_e( 'Your order', 'devdiggers-returns-for-woocommerce' ); ?></span>
	<h2>
		<?php
		/* translators: %s: order number */
		echo esc_html( sprintf( __( 'Order #%s', 'devdiggers-returns-for-woocommerce' ), $order_data['number'] ) );
		?>
	</h2>
	<p class="ddwcwr-muted"><?php echo esc_html( date_i18n( wc_date_format(), $order_data['date_created'] ) ); ?> · <?php echo esc_html( wc_get_order_status_name( $order->get_status() ) ); ?></p>
	<ul class="ddwcwr-item-rows">
		<?php foreach ( $items as $ddwcwr_item ) : ?>
			<li>
				<span class="ddwcwr-thumb"><?php echo wp_kses_post( $ddwcwr_item['image'] ); ?></span>
				<span class="ddwcwr-list-body">
					<strong><?php echo esc_html( $ddwcwr_item['name'] ); ?></strong>
					<?php /* translators: %d: quantity */ ?>
					<span class="ddwcwr-meta"><?php echo esc_html( sprintf( __( 'Qty %d', 'devdiggers-returns-for-woocommerce' ), $ddwcwr_item['qty'] ) ); ?></span>
				</span>
			</li>
		<?php endforeach; ?>
	</ul>
</div>
