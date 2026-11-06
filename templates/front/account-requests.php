<?php
/**
 * My Account > Returns
 *
 * @package DevDiggers Returns
 * @version 1.0.0
 *
 * @var array $requests Requests.
 * @var array $orders   Orders with request links.
 */

defined( 'ABSPATH' ) || exit();

$ddwcwr_types    = ddwcwr_get_request_types();
$ddwcwr_open     = 0;
$ddwcwr_done     = 0;
$ddwcwr_paid     = 0;
$ddwcwr_view     = isset( $_GET['ddwcwr-view'] ) ? sanitize_key( wp_unslash( $_GET['ddwcwr-view'] ) ) : ( $requests ? 'requests' : 'new' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display only.
$ddwcwr_base_url = ddwcwr_get_account_url();

foreach ( $requests as $ddwcwr_request ) {
	if ( in_array( $ddwcwr_request['status'], ddwcwr_get_final_statuses(), true ) ) {
		++$ddwcwr_done;
	} else {
		++$ddwcwr_open;
	}

	$ddwcwr_paid += (float) $ddwcwr_request['refunded_total'];
}
?>
<div class="ddwcwr-account">
	<header class="ddwcwr-hero">
		<span class="ddwcwr-eyebrow"><?php esc_html_e( 'Returns', 'devdiggers-returns-for-woocommerce' ); ?></span>
		<h2><?php esc_html_e( 'Send something back', 'devdiggers-returns-for-woocommerce' ); ?></h2>
		<p><?php esc_html_e( 'Return items from your orders and follow every request in one place.', 'devdiggers-returns-for-woocommerce' ); ?></p>
	</header>

	<?php if ( $requests ) : ?>
		<ul class="ddwcwr-stats">
			<li><span class="ddwcwr-eyebrow"><?php esc_html_e( 'In progress', 'devdiggers-returns-for-woocommerce' ); ?></span><strong><?php echo esc_html( $ddwcwr_open ); ?></strong></li>
			<li><span class="ddwcwr-eyebrow"><?php esc_html_e( 'Completed', 'devdiggers-returns-for-woocommerce' ); ?></span><strong><?php echo esc_html( $ddwcwr_done ); ?></strong></li>
			<li><span class="ddwcwr-eyebrow"><?php esc_html_e( 'Paid back to you', 'devdiggers-returns-for-woocommerce' ); ?></span><strong><?php echo wp_kses_post( wc_price( $ddwcwr_paid ) ); ?></strong></li>
		</ul>
	<?php endif; ?>

	<nav class="ddwcwr-nav" aria-label="<?php esc_attr_e( 'Returns', 'devdiggers-returns-for-woocommerce' ); ?>">
		<a class="ddwcwr-nav-item<?php echo 'new' !== $ddwcwr_view ? ' is-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'ddwcwr-view', 'requests', $ddwcwr_base_url ) ); ?>"<?php echo 'new' !== $ddwcwr_view ? ' aria-current="page"' : ''; ?>>
			<?php esc_html_e( 'Your requests', 'devdiggers-returns-for-woocommerce' ); ?>
			<?php if ( $ddwcwr_open ) : ?>
				<span class="ddwcwr-nav-count"><?php echo esc_html( $ddwcwr_open ); ?></span>
			<?php endif; ?>
		</a>
		<a class="ddwcwr-nav-item<?php echo 'new' === $ddwcwr_view ? ' is-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'ddwcwr-view', 'new', $ddwcwr_base_url ) ); ?>"<?php echo 'new' === $ddwcwr_view ? ' aria-current="page"' : ''; ?>>
			<?php esc_html_e( 'Start a return', 'devdiggers-returns-for-woocommerce' ); ?>
		</a>
	</nav>

	<?php if ( 'new' !== $ddwcwr_view ) : ?>
		<?php if ( empty( $requests ) ) : ?>
			<div class="ddwcwr-empty">
				<?php echo wp_kses( ddwcwr_icon( 'box', 32 ), ddwcwr_kses_icon() ); ?>
				<h3><?php esc_html_e( 'No requests yet', 'devdiggers-returns-for-woocommerce' ); ?></h3>
				<p><?php esc_html_e( 'When you return something, you can follow it here.', 'devdiggers-returns-for-woocommerce' ); ?></p>
				<a class="ddwcwr-btn ddwcwr-btn-primary" href="<?php echo esc_url( add_query_arg( 'ddwcwr-view', 'new', $ddwcwr_base_url ) ); ?>"><?php esc_html_e( 'Start a return', 'devdiggers-returns-for-woocommerce' ); ?></a>
			</div>
		<?php else : ?>
			<ul class="ddwcwr-list">
				<?php foreach ( $requests as $ddwcwr_request ) : ?>
					<?php
					$ddwcwr_order = ddwcwr_order_adapter()->get_order( $ddwcwr_request['order_id'] );
					$ddwcwr_time  = strtotime( $ddwcwr_request['created'] );
					?>
					<li>
						<a class="ddwcwr-list-item" href="<?php echo esc_url( ddwcwr_get_request_view_url( $ddwcwr_request ) ); ?>">
							<span class="ddwcwr-date-tile" aria-hidden="true"><small><?php echo esc_html( date_i18n( 'M', $ddwcwr_time ) ); ?></small><b><?php echo esc_html( date_i18n( 'j', $ddwcwr_time ) ); ?></b></span>
							<span class="ddwcwr-list-body">
								<strong><?php echo esc_html( $ddwcwr_request['number'] ); ?></strong>
								<span class="ddwcwr-meta">
									<?php
									/* translators: 1: request type, 2: order number */
									echo esc_html( sprintf( __( '%1$s · Order #%2$s', 'devdiggers-returns-for-woocommerce' ), isset( $ddwcwr_types[ $ddwcwr_request['type'] ] ) ? $ddwcwr_types[ $ddwcwr_request['type'] ] : $ddwcwr_request['type'], $ddwcwr_order ? $ddwcwr_order->get_order_number() : $ddwcwr_request['order_id'] ) );
									?>
								</span>
							</span>
							<span class="ddwcwr-list-end">
								<?php echo wp_kses_post( ddwcwr_get_status_badge( $ddwcwr_request['status'] ) ); ?>
								<?php echo wp_kses( ddwcwr_icon( 'chevron', 18 ), ddwcwr_kses_icon() ); ?>
							</span>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	<?php elseif ( empty( $orders ) ) : ?>
		<div class="ddwcwr-empty">
			<?php echo wp_kses( ddwcwr_icon( 'return', 32 ), ddwcwr_kses_icon() ); ?>
			<h3><?php esc_html_e( 'Nothing to return right now', 'devdiggers-returns-for-woocommerce' ); ?></h3>
			<p><?php esc_html_e( 'Orders appear here while they can still be returned or canceled.', 'devdiggers-returns-for-woocommerce' ); ?></p>
		</div>
	<?php else : ?>
		<p class="ddwcwr-lead"><?php esc_html_e( 'Pick the order and what you would like to do. You choose the items on the next screen.', 'devdiggers-returns-for-woocommerce' ); ?></p>
		<ul class="ddwcwr-list">
			<?php foreach ( $orders as $ddwcwr_entry ) : ?>
				<?php
				$ddwcwr_order = $ddwcwr_entry['order'];
				$ddwcwr_time  = $ddwcwr_order->get_date_created() ? $ddwcwr_order->get_date_created()->getTimestamp() : time();
				$ddwcwr_count = $ddwcwr_order->get_item_count();
				?>
				<li class="ddwcwr-list-item ddwcwr-order-row">
					<span class="ddwcwr-date-tile" aria-hidden="true"><small><?php echo esc_html( date_i18n( 'M', $ddwcwr_time ) ); ?></small><b><?php echo esc_html( date_i18n( 'j', $ddwcwr_time ) ); ?></b></span>
					<span class="ddwcwr-list-body">
						<strong>
							<?php
							/* translators: %s: order number */
							echo esc_html( sprintf( __( 'Order #%s', 'devdiggers-returns-for-woocommerce' ), $ddwcwr_order->get_order_number() ) );
							?>
						</strong>
						<span class="ddwcwr-meta">
							<?php
							/* translators: %d: number of items */
							echo esc_html( sprintf( _n( '%d item', '%d items', $ddwcwr_count, 'devdiggers-returns-for-woocommerce' ), $ddwcwr_count ) );
							?>
							· <?php echo wp_kses_post( $ddwcwr_order->get_formatted_order_total() ); ?>
						</span>
					</span>
					<span class="ddwcwr-actions">
						<?php foreach ( $ddwcwr_entry['actions'] as $ddwcwr_type => $ddwcwr_link ) : ?>
							<a class="ddwcwr-btn ddwcwr-btn-<?php echo 'return' === $ddwcwr_type ? 'primary' : 'secondary'; ?> ddwcwr-button-<?php echo esc_attr( $ddwcwr_type ); ?>" href="<?php echo esc_url( $ddwcwr_link['url'] ); ?>"><?php echo esc_html( $ddwcwr_link['label'] ); ?></a>
						<?php endforeach; ?>
					</span>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
</div>
