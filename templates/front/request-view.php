<?php
/**
 * Request view for customers.
 *
 * @package WPReturns
 * @version 1.0.0
 *
 * @var array  $request       Request.
 * @var object $order         Order.
 * @var array  $items         Items.
 * @var array  $messages      Conversation.
 * @var array  $timeline      Status log entries.
 * @var string $access_key    Guest key.
 * @var string $label_url     Label download URL.
 * @var array  $configuration Configuration.
 * @var string $back_url      Back link.
 */

defined( 'ABSPATH' ) || exit();

$ddwcwr_types    = ddwcwr_get_request_types();
$ddwcwr_currency = [ 'currency' => $order ? $order->get_currency() : get_woocommerce_currency() ];
$ddwcwr_final    = in_array( $request['status'], ddwcwr_get_final_statuses(), true );
$ddwcwr_ships    = 'cancellation' !== $request['type'];

// Progress steps.
$ddwcwr_steps             = [ 'pending' => __( 'Submitted', 'wpreturns' ) ];
$ddwcwr_steps['approved'] = __( 'Approved', 'wpreturns' );
if ( $ddwcwr_ships ) {
	$ddwcwr_steps['awaiting_return'] = __( 'On its way back', 'wpreturns' );
	$ddwcwr_steps['received']        = __( 'Received', 'wpreturns' );
}
$ddwcwr_steps['completed'] = __( 'Completed', 'wpreturns' );
$ddwcwr_step_keys          = array_keys( $ddwcwr_steps );
$ddwcwr_current            = array_search( $request['status'], $ddwcwr_step_keys, true );
if ( false === $ddwcwr_current ) {
	$ddwcwr_current = in_array( $request['status'], [ 'rejected', 'cancelled' ], true ) ? -1 : 1;
}
$ddwcwr_url_args = $access_key ? [ 'key' => $access_key ] : [];

// What the customer should know right now.
$ddwcwr_tone  = 'info';
$ddwcwr_title = '';
$ddwcwr_text  = '';

if ( 'rejected' === $request['status'] ) {
	$ddwcwr_tone  = 'muted';
	$ddwcwr_title = __( 'We could not accept this request', 'wpreturns' );
	$ddwcwr_text  = __( 'See our message below for the reason. Reply there if you have questions.', 'wpreturns' );
} elseif ( 'cancelled' === $request['status'] ) {
	$ddwcwr_tone  = 'muted';
	$ddwcwr_title = __( 'This request was canceled', 'wpreturns' );
	$ddwcwr_text  = __( 'Nothing else to do. You can start a new request from your orders.', 'wpreturns' );
} elseif ( ! empty( $request['meta']['processed'] ) || 'completed' === $request['status'] ) {
	$ddwcwr_tone  = 'success';
	$ddwcwr_title = __( 'All done', 'wpreturns' );
	$ddwcwr_text  = __( 'Your request has been resolved.', 'wpreturns' );
} elseif ( 'pending' === $request['status'] ) {
	$ddwcwr_title = __( 'We are reviewing your request', 'wpreturns' );
	$ddwcwr_text  = __( 'You will get an email as soon as we decide. Please do not send anything back yet.', 'wpreturns' );
} elseif ( 'received' === $request['status'] ) {
	$ddwcwr_title = __( 'We have your items', 'wpreturns' );
	$ddwcwr_text  = __( 'We are checking them now. Your refund follows shortly.', 'wpreturns' );
} elseif ( $ddwcwr_ships ) {
	$ddwcwr_tone  = 'action';
	$ddwcwr_title = __( 'Approved: send your items back', 'wpreturns' );
	$ddwcwr_text  = $label_url ? __( 'Print the return label, stick it on the package and drop it off with the carrier.', 'wpreturns' ) : __( 'Pack the items securely and send them to us, then add the tracking number below.', 'wpreturns' );
} else {
	$ddwcwr_tone  = 'success';
	$ddwcwr_title = __( 'Approved', 'wpreturns' );
	$ddwcwr_text  = __( 'No need to send anything back. Your refund is on its way.', 'wpreturns' );
}
?>
<div class="ddwcwr-request-view">
	<a class="ddwcwr-back" href="<?php echo esc_url( $back_url ); ?>"><?php echo wp_kses( ddwcwr_icon( 'back', 16 ), ddwcwr_kses_icon() ); ?><?php esc_html_e( 'Back', 'wpreturns' ); ?></a>

	<header class="ddwcwr-card ddwcwr-request-header">
		<div class="ddwcwr-request-heading">
			<div>
				<span class="ddwcwr-eyebrow"><?php echo esc_html( isset( $ddwcwr_types[ $request['type'] ] ) ? $ddwcwr_types[ $request['type'] ] : $request['type'] ); ?></span>
				<h2><?php echo esc_html( $request['number'] ); ?></h2>
				<p class="ddwcwr-meta">
					<?php
					/* translators: 1: order number, 2: date */
					echo esc_html( sprintf( __( 'Order #%1$s · Sent %2$s', 'wpreturns' ), $order ? $order->get_order_number() : $request['order_id'], date_i18n( wc_date_format(), strtotime( $request['created'] ) ) ) );
					?>
				</p>
			</div>
			<?php echo wp_kses_post( ddwcwr_get_status_badge( $request['status'] ) ); ?>
		</div>

		<?php if ( -1 !== $ddwcwr_current ) : ?>
			<ol class="ddwcwr-progress" aria-label="<?php esc_attr_e( 'Request progress', 'wpreturns' ); ?>">
				<?php foreach ( $ddwcwr_step_keys as $ddwcwr_index => $ddwcwr_key ) : ?>
					<?php $ddwcwr_is_done = $ddwcwr_index < $ddwcwr_current || ( $ddwcwr_index === $ddwcwr_current && 'completed' === $request['status'] ); ?>
					<li class="<?php echo $ddwcwr_is_done ? 'is-done' : ( $ddwcwr_index === $ddwcwr_current ? 'is-current' : '' ); ?>"<?php echo $ddwcwr_index === $ddwcwr_current ? ' aria-current="step"' : ''; ?>>
						<span class="ddwcwr-progress-dot" aria-hidden="true"><?php echo $ddwcwr_is_done ? wp_kses( ddwcwr_icon( 'check', 12 ), ddwcwr_kses_icon() ) : ''; ?></span>
						<span class="ddwcwr-progress-label"><?php echo esc_html( $ddwcwr_index === $ddwcwr_current && ! isset( $ddwcwr_steps[ $request['status'] ] ) ? ddwcwr_get_status_label( $request['status'] ) : $ddwcwr_steps[ $ddwcwr_key ] ); ?></span>
					</li>
				<?php endforeach; ?>
			</ol>
		<?php endif; ?>
	</header>

	<div class="ddwcwr-callout is-<?php echo esc_attr( $ddwcwr_tone ); ?>" role="status">
		<span class="ddwcwr-callout-icon"><?php echo wp_kses( ddwcwr_icon( 'success' === $ddwcwr_tone ? 'check' : ( 'action' === $ddwcwr_tone ? 'box' : 'alert' ), 20 ), ddwcwr_kses_icon() ); ?></span>
		<div>
			<h3><?php echo esc_html( $ddwcwr_title ); ?></h3>
			<p><?php echo esc_html( $ddwcwr_text ); ?></p>

			<?php if ( ! empty( $request['meta']['result'] ) && (float) $request['refunded_total'] > 0 ) : ?>
				<p>
					<?php
					/* translators: %s: amount */
					echo wp_kses_post( sprintf( __( '%s is on its way back to your original payment method. Banks usually take a few days to show it.', 'wpreturns' ), wc_price( $request['refunded_total'], $ddwcwr_currency ) ) );
					?>
				</p>
			<?php endif; ?>
		</div>
	</div>

	<?php if ( $ddwcwr_ships && ! $ddwcwr_final && in_array( $request['status'], [ 'approved', 'awaiting_return' ], true ) ) : ?>
		<section class="ddwcwr-card ddwcwr-shipping">
			<h3><?php echo wp_kses( ddwcwr_icon( 'label', 18 ), ddwcwr_kses_icon() ); ?><?php esc_html_e( 'Return shipping', 'wpreturns' ); ?></h3>
			<p class="ddwcwr-shipping-actions">
				<?php if ( $label_url ) : ?>
					<a class="ddwcwr-btn ddwcwr-btn-primary" href="<?php echo esc_url( $label_url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Download return label', 'wpreturns' ); ?></a>
				<?php else : ?>
					<span class="ddwcwr-muted"><?php esc_html_e( 'Use any carrier you like and keep the receipt.', 'wpreturns' ); ?></span>
				<?php endif; ?>
			</p>

			<?php if ( $request['tracking_number'] ) : ?>
				<dl class="ddwcwr-rows">
					<div><dt><?php esc_html_e( 'Tracking', 'wpreturns' ); ?></dt><dd><?php echo esc_html( trim( $request['carrier'] . ' ' . $request['tracking_number'] ) ); ?></dd></div>
				</dl>
			<?php else : ?>
				<form method="post" class="ddwcwr-form ddwcwr-inline-form">
					<p class="form-row">
						<label for="ddwcwr-carrier"><?php esc_html_e( 'Carrier', 'wpreturns' ); ?></label>
						<input type="text" class="input-text" id="ddwcwr-carrier" name="ddwcwr_carrier" autocomplete="off" placeholder="<?php esc_attr_e( 'e.g. UPS', 'wpreturns' ); ?>" />
					</p>
					<p class="form-row">
						<label for="ddwcwr-tracking"><?php esc_html_e( 'Tracking number', 'wpreturns' ); ?></label>
						<input type="text" class="input-text" id="ddwcwr-tracking" name="ddwcwr_tracking" required autocomplete="off" />
					</p>
					<?php wp_nonce_field( 'ddwcwr_request_action', 'ddwcwr_nonce' ); ?>
					<input type="hidden" name="ddwcwr_action" value="add_tracking" />
					<input type="hidden" name="ddwcwr_request_id" value="<?php echo esc_attr( $request['id'] ); ?>" />
					<input type="hidden" name="ddwcwr_key" value="<?php echo esc_attr( $access_key ); ?>" />
					<button type="submit" class="ddwcwr-btn ddwcwr-btn-secondary"><?php esc_html_e( 'Save tracking', 'wpreturns' ); ?></button>
				</form>
			<?php endif; ?>
		</section>
	<?php endif; ?>

	<section class="ddwcwr-card">
		<h3><?php echo wp_kses( ddwcwr_icon( 'box', 18 ), ddwcwr_kses_icon() ); ?><?php esc_html_e( 'Items', 'wpreturns' ); ?></h3>
		<ul class="ddwcwr-item-rows">
			<?php foreach ( $items as $ddwcwr_item ) : ?>
				<?php $ddwcwr_product = wc_get_product( $ddwcwr_item['variation_id'] ? $ddwcwr_item['variation_id'] : $ddwcwr_item['product_id'] ); ?>
				<li>
					<span class="ddwcwr-thumb"><?php echo $ddwcwr_product ? wp_kses_post( $ddwcwr_product->get_image( [ 96, 96 ] ) ) : ''; ?></span>
					<span class="ddwcwr-list-body">
						<strong><?php echo esc_html( $ddwcwr_item['name'] ); ?></strong>
						<span class="ddwcwr-meta">
							<?php
							/* translators: 1: quantity, 2: reason */
							echo esc_html( sprintf( __( 'Qty %1$d · %2$s', 'wpreturns' ), $ddwcwr_item['qty'], ddwcwr_get_reason_label( $ddwcwr_item['reason'] ) ) );
							?>
						</span>
					</span>
					<span class="ddwcwr-amount"><?php echo wp_kses_post( wc_price( $ddwcwr_item['line_total'], $ddwcwr_currency ) ); ?></span>
				</li>
			<?php endforeach; ?>
		</ul>

		<dl class="ddwcwr-rows ddwcwr-totals">
			<div class="is-total"><dt><?php echo (float) $request['refunded_total'] > 0 ? esc_html__( 'Paid back', 'wpreturns' ) : esc_html__( 'You will get', 'wpreturns' ); ?></dt><dd><?php echo wp_kses_post( wc_price( (float) $request['refunded_total'] > 0 ? $request['refunded_total'] : $request['resolution_total'], $ddwcwr_currency ) ); ?></dd></div>
		</dl>
	</section>

	<section class="ddwcwr-card" id="ddwcwr-conversation">
		<h3><?php echo wp_kses( ddwcwr_icon( 'chat', 18 ), ddwcwr_kses_icon() ); ?><?php esc_html_e( 'Messages', 'wpreturns' ); ?></h3>
		<?php if ( empty( $messages ) ) : ?>
			<p class="ddwcwr-muted"><?php esc_html_e( 'No messages yet. Ask us anything about this request below.', 'wpreturns' ); ?></p>
		<?php else : ?>
			<ol class="ddwcwr-messages">
				<?php foreach ( $messages as $ddwcwr_message ) : ?>
					<li class="ddwcwr-message ddwcwr-message-<?php echo esc_attr( $ddwcwr_message['author_type'] ); ?>">
						<div class="ddwcwr-message-meta">
							<strong><?php echo 'customer' === $ddwcwr_message['author_type'] ? esc_html__( 'You', 'wpreturns' ) : esc_html( get_bloginfo( 'name' ) ); ?></strong>
							<time datetime="<?php echo esc_attr( mysql_to_rfc3339( $ddwcwr_message['created'] ) ); ?>"><?php echo esc_html( date_i18n( wc_date_format() . ' ' . wc_time_format(), strtotime( $ddwcwr_message['created'] ) ) ); ?></time>
						</div>
						<?php if ( '' !== trim( (string) $ddwcwr_message['message'] ) ) : ?>
							<div class="ddwcwr-message-body"><?php echo wp_kses_post( wpautop( esc_html( $ddwcwr_message['message'] ) ) ); ?></div>
						<?php endif; ?>
						<?php if ( $ddwcwr_message['attachments'] ) : ?>
							<ul class="ddwcwr-attachments">
								<?php foreach ( $ddwcwr_message['attachments'] as $ddwcwr_file ) : ?>
									<?php
									$ddwcwr_file_url = add_query_arg(
										array_merge(
											[
												'action'  => 'ddwcwr_file',
												'request' => $request['id'],
												'file'    => $ddwcwr_file['key'],
											],
											$ddwcwr_url_args
										),
										admin_url( 'admin-post.php' )
									);
									?>
									<li><a href="<?php echo esc_url( $ddwcwr_file_url ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $ddwcwr_file['name'] ); ?></a></li>
								<?php endforeach; ?>
							</ul>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ol>
		<?php endif; ?>

		<?php if ( ! $ddwcwr_final ) : ?>
			<form method="post" enctype="multipart/form-data" class="ddwcwr-form ddwcwr-reply-form">
				<p class="form-row">
					<label for="ddwcwr-message"><?php esc_html_e( 'Your message', 'wpreturns' ); ?></label>
					<textarea id="ddwcwr-message" name="ddwcwr_message" rows="3" class="input-text" placeholder="<?php esc_attr_e( 'Ask us anything about this request…', 'wpreturns' ); ?>"></textarea>
				</p>
				<?php if ( 'yes' === $configuration['allow_uploads'] ) : ?>
					<div class="form-row ddwcwr-upload">
						<input type="file" class="ddwcwr-file-input" id="ddwcwr-reply-files" name="ddwcwr_files[]" multiple accept="<?php echo esc_attr( '.' . implode( ',.', (array) $configuration['allowed_file_types'] ) ); ?>" />
						<label for="ddwcwr-reply-files" class="ddwcwr-attach"><?php echo wp_kses( ddwcwr_icon( 'upload', 16 ), ddwcwr_kses_icon() ); ?><?php esc_html_e( 'Attach files', 'wpreturns' ); ?></label>
						<span class="ddwcwr-file-list" aria-live="polite"></span>
					</div>
				<?php endif; ?>
				<?php wp_nonce_field( 'ddwcwr_request_action', 'ddwcwr_nonce' ); ?>
				<input type="hidden" name="ddwcwr_action" value="send_message" />
				<input type="hidden" name="ddwcwr_request_id" value="<?php echo esc_attr( $request['id'] ); ?>" />
				<input type="hidden" name="ddwcwr_key" value="<?php echo esc_attr( $access_key ); ?>" />
				<button type="submit" class="ddwcwr-btn ddwcwr-btn-primary"><?php esc_html_e( 'Send message', 'wpreturns' ); ?></button>
			</form>
		<?php endif; ?>
	</section>

	<?php if ( 'pending' === $request['status'] && 'withdrawal' !== $request['type'] ) : ?>
		<form method="post" class="ddwcwr-cancel-form">
			<?php wp_nonce_field( 'ddwcwr_request_action', 'ddwcwr_nonce' ); ?>
			<input type="hidden" name="ddwcwr_action" value="cancel_request" />
			<input type="hidden" name="ddwcwr_request_id" value="<?php echo esc_attr( $request['id'] ); ?>" />
			<input type="hidden" name="ddwcwr_key" value="<?php echo esc_attr( $access_key ); ?>" />
			<button type="submit" class="ddwcwr-link-button" data-confirm="1"><?php esc_html_e( 'Cancel this request', 'wpreturns' ); ?></button>
		</form>
	<?php endif; ?>
</div>
