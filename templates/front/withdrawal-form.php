<?php
/**
 * EU withdrawal: the withdrawal function (step 1) and the statement with "Confirm withdrawal" (step 2).
 *
 * Article 11a of Directive 2011/83/EU (as amended by Directive (EU) 2023/2673).
 *
 * @package DevDiggers Returns
 * @version 1.0.0
 *
 * @var string $step          start|form.
 * @var array  $prefill       name, number, email.
 * @var array  $items         Items of a known order (logged-in customers).
 * @var array  $orders        Orders the logged-in customer can withdraw from.
 * @var array  $configuration Configuration.
 */

defined( 'ABSPATH' ) || exit();
?>
<?php if ( 'start' === $step ) : ?>
	<div class="ddwcwr-card ddwcwr-withdrawal-start">
		<span class="ddwcwr-icon-tile"><?php echo wp_kses( ddwcwr_icon( 'withdraw', 24 ), ddwcwr_kses_icon() ); ?></span>
		<h2><?php esc_html_e( 'Right of withdrawal', 'devdiggers-returns-for-woocommerce' ); ?></h2>
		<p class="ddwcwr-lead">
			<?php
			/* translators: %d: number of days */
			echo esc_html( sprintf( __( 'You can withdraw from your contract within %d days of receiving your goods, without giving any reason.', 'devdiggers-returns-for-woocommerce' ), absint( $configuration['withdrawal_days'] ) ) );
			?>
		</p>
		<ol class="ddwcwr-how">
			<li><?php esc_html_e( 'Fill in your name, order number and email.', 'devdiggers-returns-for-woocommerce' ); ?></li>
			<li><?php esc_html_e( 'Confirm the withdrawal.', 'devdiggers-returns-for-woocommerce' ); ?></li>
			<li><?php esc_html_e( 'Get an email confirming the date and time we received it.', 'devdiggers-returns-for-woocommerce' ); ?></li>
		</ol>
		<p><a class="ddwcwr-btn ddwcwr-btn-primary ddwcwr-btn-block ddwcwr-withdraw-button" href="<?php echo esc_url( add_query_arg( 'ddwcwr-step', 'form' ) ); ?>"><?php esc_html_e( 'Withdraw from contract here', 'devdiggers-returns-for-woocommerce' ); ?></a></p>
	</div>
	<?php if ( 'yes' === $configuration['withdrawal_model_form'] ) : ?>
		<details class="ddwcwr-card ddwcwr-model-form">
			<summary><?php esc_html_e( 'Prefer to write to us? Model withdrawal form', 'devdiggers-returns-for-woocommerce' ); ?></summary>
			<p class="ddwcwr-muted"><?php esc_html_e( 'Complete and return this form only if you wish to withdraw from the contract. You can also use the button above.', 'devdiggers-returns-for-woocommerce' ); ?></p>
			<ul>
				<li>
					<?php esc_html_e( 'To:', 'devdiggers-returns-for-woocommerce' ); ?>
					<address><?php echo wp_kses( implode( '<br />', array_map( 'esc_html', ddwcwr_get_trader_address() ) ), [ 'br' => [] ] ); ?></address>
				</li>
				<li><?php esc_html_e( 'I/We (*) hereby give notice that I/We (*) withdraw from my/our (*) contract of sale of the following goods (*)/for the provision of the following service (*),', 'devdiggers-returns-for-woocommerce' ); ?></li>
				<li><?php esc_html_e( 'Ordered on (*)/received on (*),', 'devdiggers-returns-for-woocommerce' ); ?></li>
				<li><?php esc_html_e( 'Name of consumer(s),', 'devdiggers-returns-for-woocommerce' ); ?></li>
				<li><?php esc_html_e( 'Address of consumer(s),', 'devdiggers-returns-for-woocommerce' ); ?></li>
				<li><?php esc_html_e( 'Signature of consumer(s) (only if this form is notified on paper),', 'devdiggers-returns-for-woocommerce' ); ?></li>
				<li><?php esc_html_e( 'Date', 'devdiggers-returns-for-woocommerce' ); ?></li>
			</ul>
			<p class="ddwcwr-muted"><?php esc_html_e( '(*) Delete as appropriate.', 'devdiggers-returns-for-woocommerce' ); ?></p>
		</details>
	<?php endif; ?>
<?php else : ?>
	<form method="post" class="ddwcwr-card ddwcwr-form ddwcwr-withdrawal-form">
		<span class="ddwcwr-eyebrow"><?php esc_html_e( 'Right of withdrawal', 'devdiggers-returns-for-woocommerce' ); ?></span>
		<h2><?php esc_html_e( 'Withdrawal statement', 'devdiggers-returns-for-woocommerce' ); ?></h2>
		<p class="ddwcwr-lead"><?php esc_html_e( 'Check the details below and confirm. We will email you an acknowledgement with the date and time we received your statement.', 'devdiggers-returns-for-woocommerce' ); ?></p>

		<p class="form-row">
			<label for="ddwcwr-withdraw-name"><?php esc_html_e( 'Your name', 'devdiggers-returns-for-woocommerce' ); ?> <span class="required" aria-hidden="true">*</span></label>
			<input type="text" class="input-text" id="ddwcwr-withdraw-name" name="ddwcwr_name" value="<?php echo esc_attr( $prefill['name'] ); ?>" required autocomplete="name" />
		</p>

		<p class="form-row">
			<label for="ddwcwr-withdraw-order"><?php esc_html_e( 'Order number', 'devdiggers-returns-for-woocommerce' ); ?> <span class="required" aria-hidden="true">*</span></label>
			<?php if ( $orders && '' === $prefill['number'] ) : ?>
				<select id="ddwcwr-withdraw-order" name="ddwcwr_order_number" required>
					<option value=""><?php esc_html_e( 'Choose an order', 'devdiggers-returns-for-woocommerce' ); ?></option>
					<?php foreach ( $orders as $ddwcwr_number => $ddwcwr_order ) : ?>
						<?php /* translators: 1: order number, 2: date */ ?>
						<option value="<?php echo esc_attr( $ddwcwr_number ); ?>"><?php echo esc_html( sprintf( __( '#%1$s – %2$s', 'devdiggers-returns-for-woocommerce' ), $ddwcwr_number, wc_format_datetime( $ddwcwr_order->get_date_created() ) ) ); ?></option>
					<?php endforeach; ?>
				</select>
			<?php else : ?>
				<input type="text" class="input-text" id="ddwcwr-withdraw-order" name="ddwcwr_order_number" value="<?php echo esc_attr( $prefill['number'] ); ?>" required autocomplete="off" />
			<?php endif; ?>
		</p>

		<p class="form-row">
			<label for="ddwcwr-withdraw-email"><?php esc_html_e( 'Email for the confirmation', 'devdiggers-returns-for-woocommerce' ); ?> <span class="required" aria-hidden="true">*</span></label>
			<input type="email" class="input-text" id="ddwcwr-withdraw-email" name="ddwcwr_email" value="<?php echo esc_attr( $prefill['email'] ); ?>" required autocomplete="email" aria-describedby="ddwcwr-withdraw-email-help" />
			<span class="ddwcwr-muted" id="ddwcwr-withdraw-email-help"><?php esc_html_e( 'Use the email address of the order.', 'devdiggers-returns-for-woocommerce' ); ?></span>
		</p>

		<?php if ( $items ) : ?>
			<fieldset class="ddwcwr-withdraw-items">
				<legend><?php esc_html_e( 'Goods you withdraw from', 'devdiggers-returns-for-woocommerce' ); ?></legend>
				<?php foreach ( $items as $ddwcwr_item_id => $ddwcwr_item ) : ?>
					<label class="ddwcwr-check<?php echo $ddwcwr_item['eligible'] ? '' : ' is-disabled'; ?>">
						<input type="checkbox" name="ddwcwr_withdraw_items[]" value="<?php echo esc_attr( $ddwcwr_item_id ); ?>" <?php checked( $ddwcwr_item['eligible'] ); ?> <?php disabled( ! $ddwcwr_item['eligible'] ); ?> />
						<?php echo esc_html( $ddwcwr_item['name'] . ' × ' . $ddwcwr_item['available'] ); ?>
						<?php if ( ! $ddwcwr_item['eligible'] ) : ?>
							<span class="ddwcwr-muted">(<?php echo esc_html( $ddwcwr_item['message'] ); ?>)</span>
						<?php endif; ?>
					</label>
				<?php endforeach; ?>
			</fieldset>
		<?php else : ?>
			<p class="ddwcwr-muted"><?php esc_html_e( 'The statement covers every item of the order that can still be withdrawn from.', 'devdiggers-returns-for-woocommerce' ); ?></p>
		<?php endif; ?>

		<p class="ddwcwr-hp" aria-hidden="true">
			<label for="ddwcwr-withdraw-website"><?php esc_html_e( 'Leave this field empty', 'devdiggers-returns-for-woocommerce' ); ?></label>
			<input type="text" id="ddwcwr-withdraw-website" name="ddwcwr_website" value="" tabindex="-1" autocomplete="off" />
		</p>

		<?php wp_nonce_field( 'ddwcwr_withdrawal', 'ddwcwr_nonce' ); ?>
		<input type="hidden" name="ddwcwr_action" value="withdrawal_confirm" />
		<button type="submit" class="ddwcwr-btn ddwcwr-btn-primary ddwcwr-btn-block ddwcwr-submit ddwcwr-confirm-withdrawal"><?php esc_html_e( 'Confirm withdrawal', 'devdiggers-returns-for-woocommerce' ); ?></button>
	</form>
<?php endif; ?>
