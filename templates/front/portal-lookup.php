<?php
/**
 * Guest portal: order lookup.
 *
 * @package DevDiggers Returns
 * @version 1.0.0
 *
 * @var string $account_url My Account returns URL (logged-in visitors).
 */

defined( 'ABSPATH' ) || exit();

// phpcs:disable WordPress.Security.NonceVerification.Missing -- Re-filling after a verified post.
$ddwcwr_number = isset( $_POST['ddwcwr_order_number'] ) ? sanitize_text_field( wp_unslash( $_POST['ddwcwr_order_number'] ) ) : '';
$ddwcwr_email  = isset( $_POST['ddwcwr_email'] ) ? sanitize_email( wp_unslash( $_POST['ddwcwr_email'] ) ) : '';
// phpcs:enable
?>
<div class="ddwcwr-card ddwcwr-lookup">
	<span class="ddwcwr-icon-tile"><?php echo wp_kses( ddwcwr_icon( 'return', 24 ), ddwcwr_kses_icon() ); ?></span>
	<h2><?php esc_html_e( 'Start a return', 'devdiggers-returns-for-woocommerce' ); ?></h2>
	<p class="ddwcwr-lead"><?php esc_html_e( 'Enter your order number and the email address you used at checkout. No account needed.', 'devdiggers-returns-for-woocommerce' ); ?></p>

	<form method="post" class="ddwcwr-form ddwcwr-lookup-form" novalidate>
		<p class="form-row">
			<label for="ddwcwr-order-number"><?php esc_html_e( 'Order number', 'devdiggers-returns-for-woocommerce' ); ?> <span class="required" aria-hidden="true">*</span></label>
			<input type="text" class="input-text" id="ddwcwr-order-number" name="ddwcwr_order_number" value="<?php echo esc_attr( $ddwcwr_number ); ?>" required autocomplete="off" inputmode="numeric" placeholder="<?php esc_attr_e( 'e.g. 1042', 'devdiggers-returns-for-woocommerce' ); ?>" />
		</p>
		<p class="form-row">
			<label for="ddwcwr-email"><?php esc_html_e( 'Billing email', 'devdiggers-returns-for-woocommerce' ); ?> <span class="required" aria-hidden="true">*</span></label>
			<input type="email" class="input-text" id="ddwcwr-email" name="ddwcwr_email" value="<?php echo esc_attr( $ddwcwr_email ); ?>" required autocomplete="email" placeholder="<?php esc_attr_e( 'you@example.com', 'devdiggers-returns-for-woocommerce' ); ?>" />
		</p>
		<p class="ddwcwr-hp" aria-hidden="true">
			<label for="ddwcwr-website"><?php esc_html_e( 'Leave this field empty', 'devdiggers-returns-for-woocommerce' ); ?></label>
			<input type="text" id="ddwcwr-website" name="ddwcwr_website" value="" tabindex="-1" autocomplete="off" />
		</p>
		<?php wp_nonce_field( 'ddwcwr_portal_lookup', 'ddwcwr_nonce' ); ?>
		<input type="hidden" name="ddwcwr_action" value="portal_lookup" />
		<button type="submit" class="ddwcwr-btn ddwcwr-btn-primary ddwcwr-btn-block ddwcwr-submit"><?php esc_html_e( 'Find my order', 'devdiggers-returns-for-woocommerce' ); ?></button>
	</form>

	<?php if ( ! empty( $account_url ) ) : ?>
		<p class="ddwcwr-muted ddwcwr-center">
			<?php
			/* translators: %1$s: link open, %2$s: link close */
			printf( esc_html__( 'Signed in? %1$sSee your orders in My Account%2$s.', 'devdiggers-returns-for-woocommerce' ), '<a href="' . esc_url( $account_url ) . '">', '</a>' );
			?>
		</p>
	<?php endif; ?>
</div>
