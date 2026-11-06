<?php
/**
 * Returns panel under an order.
 *
 * @package DevDiggers Returns
 * @version 1.0.0
 *
 * @var object $order    Order.
 * @var array  $requests Requests.
 * @var array  $links    Available request links: label, url and deadline (0 when the window has not started).
 * @var bool   $guest    Guest view.
 */

defined( 'ABSPATH' ) || exit();

$ddwcwr_types = ddwcwr_get_request_types();
?>
<section class="ddwcwr-ui ddwcwr-card ddwcwr-order-panel" aria-labelledby="ddwcwr-order-panel-title">
	<header class="ddwcwr-panel-head">
		<span class="ddwcwr-icon-tile"><?php echo wp_kses( ddwcwr_icon( 'return', 22 ), ddwcwr_kses_icon() ); ?></span>
		<div>
			<h2 id="ddwcwr-order-panel-title"><?php esc_html_e( 'Returns', 'devdiggers-returns-for-woocommerce' ); ?></h2>
			<p class="ddwcwr-meta"><?php echo $links ? esc_html__( 'Something not right? Start a request and we will sort it out.', 'devdiggers-returns-for-woocommerce' ) : esc_html__( 'Your requests for this order.', 'devdiggers-returns-for-woocommerce' ); ?></p>
			<?php
			foreach ( [ 'return', 'withdrawal' ] as $ddwcwr_type ) :
				if ( empty( $links[ $ddwcwr_type ]['deadline'] ) ) {
					continue;
				}

				$ddwcwr_days = max( 0, (int) ceil( ( $links[ $ddwcwr_type ]['deadline'] - time() ) / DAY_IN_SECONDS ) );
				$ddwcwr_date = date_i18n( wc_date_format(), $links[ $ddwcwr_type ]['deadline'] );
				?>
				<p class="ddwcwr-meta ddwcwr-order-deadline">
					<?php
					echo esc_html(
						'return' === $ddwcwr_type
							/* translators: 1: last day to request a return, 2: days left */
							? sprintf( _n( 'You can request a return until %1$s (%2$s day left).', 'You can request a return until %1$s (%2$s days left).', $ddwcwr_days, 'devdiggers-returns-for-woocommerce' ), $ddwcwr_date, number_format_i18n( $ddwcwr_days ) )
							/* translators: 1: last day of the withdrawal period, 2: days left */
							: sprintf( _n( 'You can withdraw from this contract until %1$s (%2$s day left).', 'You can withdraw from this contract until %1$s (%2$s days left).', $ddwcwr_days, 'devdiggers-returns-for-woocommerce' ), $ddwcwr_date, number_format_i18n( $ddwcwr_days ) )
					);
					?>
				</p>
			<?php endforeach; ?>
		</div>
	</header>

	<?php if ( $requests ) : ?>
		<ul class="ddwcwr-list">
			<?php foreach ( $requests as $ddwcwr_request ) : ?>
				<li>
					<a class="ddwcwr-list-item ddwcwr-list-item-compact" href="<?php echo esc_url( ddwcwr_get_request_view_url( $ddwcwr_request, $guest ) ); ?>">
						<span class="ddwcwr-list-body">
							<strong><?php echo esc_html( $ddwcwr_request['number'] ); ?></strong>
							<span class="ddwcwr-meta">
								<?php
								/* translators: 1: request type, 2: date */
								echo esc_html( sprintf( __( '%1$s · %2$s', 'devdiggers-returns-for-woocommerce' ), isset( $ddwcwr_types[ $ddwcwr_request['type'] ] ) ? $ddwcwr_types[ $ddwcwr_request['type'] ] : $ddwcwr_request['type'], date_i18n( wc_date_format(), strtotime( $ddwcwr_request['created'] ) ) ) );
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

	<?php if ( $links ) : ?>
		<div class="ddwcwr-order-panel-actions">
			<?php foreach ( $links as $ddwcwr_type => $ddwcwr_link ) : ?>
				<a class="ddwcwr-btn ddwcwr-btn-<?php echo 'return' === $ddwcwr_type ? 'primary' : 'secondary'; ?> ddwcwr-button-<?php echo esc_attr( $ddwcwr_type ); ?>" href="<?php echo esc_url( $ddwcwr_link['url'] ); ?>"><?php echo esc_html( $ddwcwr_link['label'] ); ?></a>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</section>
