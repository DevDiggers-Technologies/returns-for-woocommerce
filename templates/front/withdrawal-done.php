<?php
/**
 * EU withdrawal: acknowledgement screen.
 *
 * @package DevDiggers Returns
 * @version 1.0.0
 *
 * @var array $request Request.
 * @var array $items   Items.
 */

defined( 'ABSPATH' ) || exit();

$ddwcwr_submitted = strtotime( get_gmt_from_date( $request['created'] ) . ' UTC' );
?>
<div class="ddwcwr-card ddwcwr-withdrawal-done" role="status">
	<span class="ddwcwr-icon-tile is-success"><?php echo wp_kses( ddwcwr_icon( 'check', 24 ), ddwcwr_kses_icon() ); ?></span>
	<h2><?php esc_html_e( 'Withdrawal received', 'devdiggers-returns-for-woocommerce' ); ?></h2>
	<p class="ddwcwr-lead">
		<?php
		/* translators: 1: date and time, 2: email */
		echo esc_html( sprintf( __( 'We received your withdrawal statement on %1$s. An acknowledgement has been sent to %2$s.', 'devdiggers-returns-for-woocommerce' ), wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) . ' T', $ddwcwr_submitted ), $request['customer_email'] ) );
		?>
	</p>
	<dl class="ddwcwr-rows">
		<div><dt><?php esc_html_e( 'Reference', 'devdiggers-returns-for-woocommerce' ); ?></dt>
		<dd><?php echo esc_html( $request['number'] ); ?></dd></div>
		<div><dt><?php esc_html_e( 'Goods', 'devdiggers-returns-for-woocommerce' ); ?></dt>
		<dd>
		<?php
		echo esc_html(
			implode(
				', ',
				array_map(
					function ( $item ) {
						return $item['name'] . ' × ' . $item['qty'];
					},
					$items
				)
			)
		); // phpcs:ignore Generic.Functions.OpeningFunctionBraceKernighanRitchie 
		?>
		</dd></div>
	</dl>
	<p><?php esc_html_e( 'Please send the goods back within 14 days. We will refund your payment, including standard delivery costs, after we receive the goods or proof that you sent them.', 'devdiggers-returns-for-woocommerce' ); ?></p>
	<p><a class="ddwcwr-btn ddwcwr-btn-secondary" href="<?php echo esc_url( ddwcwr_get_request_view_url( $request, true ) ); ?>"><?php esc_html_e( 'Track this withdrawal', 'devdiggers-returns-for-woocommerce' ); ?></a></p>
</div>
