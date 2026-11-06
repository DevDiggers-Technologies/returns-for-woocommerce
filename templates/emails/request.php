<?php
/**
 * Request email (HTML)
 *
 * Override by copying it to yourtheme/woocommerce/emails/request.php.
 *
 * @package DevDiggers Returns
 * @version 1.0.0
 *
 * @var \WC_Email $email
 * @var string    $email_heading
 * @var array     $request
 * @var array     $items
 * @var string    $intro
 * @var string    $note
 * @var array     $extra
 * @var array     $button
 * @var string    $additional_content
 * @var bool      $sent_to_admin
 * @var bool      $plain_text
 */

defined( 'ABSPATH' ) || exit();

$ddwcwr_request = wp_parse_args( (array) $request, array_fill_keys( [ 'customer_name', 'number', 'type', 'status' ], '' ) );

$ddwcwr_types = ddwcwr_get_request_types();

do_action( 'woocommerce_email_header', $email_heading, $email ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce core email hook.
?>
<?php if ( ! $sent_to_admin && $ddwcwr_request['customer_name'] ) : ?>
	<?php /* translators: %s: customer name */ ?>
	<p><?php echo esc_html( sprintf( __( 'Hi %s,', 'devdiggers-returns-for-woocommerce' ), $ddwcwr_request['customer_name'] ) ); ?></p>
<?php endif; ?>

<?php if ( $intro ) : ?>
	<p><?php echo esc_html( $intro ); ?></p>
<?php endif; ?>

<?php if ( '' !== trim( (string) $note ) ) : ?>
	<blockquote style="margin:0 0 16px;padding:12px 16px;border-left:4px solid #e5e7eb;background:#f9fafb;"><?php echo wp_kses_post( wpautop( esc_html( $note ) ) ); ?></blockquote>
<?php endif; ?>

<?php if ( ! empty( $extra ) ) : ?>
	<table cellspacing="0" cellpadding="6" border="1" style="width:100%;border-collapse:collapse;margin-bottom:16px;border-color:#e5e7eb;">
		<?php foreach ( $extra as $ddwcwr_label => $ddwcwr_value ) : ?>
			<tr>
				<th scope="row" style="text-align:left;width:40%;"><?php echo esc_html( $ddwcwr_label ); ?></th>
				<td style="text-align:left;"><?php echo wp_kses_post( $ddwcwr_value ); ?></td>
			</tr>
		<?php endforeach; ?>
	</table>
<?php endif; ?>

<?php if ( ! empty( $button[0] ) ) : ?>
	<p style="margin:0 0 20px;"><a href="<?php echo esc_url( $button[0] ); ?>" style="display:inline-block;padding:10px 18px;border-radius:6px;background:<?php echo esc_attr( get_option( 'woocommerce_email_base_color', '#7f54b3' ) ); ?>;color:#ffffff;text-decoration:none;font-weight:600;"><?php echo esc_html( $button[1] ); ?></a></p>
<?php endif; ?>

<h2>
	<?php
	/* translators: 1: request number, 2: request type, 3: order number */
	echo esc_html( sprintf( __( '%1$s – %2$s for order #%3$s', 'devdiggers-returns-for-woocommerce' ), $ddwcwr_request['number'], isset( $ddwcwr_types[ $ddwcwr_request['type'] ] ) ? $ddwcwr_types[ $ddwcwr_request['type'] ] : $ddwcwr_request['type'], $email->placeholders['{order_number}'] ) );
	?>
</h2>
<table cellspacing="0" cellpadding="6" border="1" style="width:100%;border-collapse:collapse;margin-bottom:16px;border-color:#e5e7eb;">
	<thead>
		<tr>
			<th style="text-align:left;"><?php esc_html_e( 'Item', 'devdiggers-returns-for-woocommerce' ); ?></th>
			<th style="text-align:left;"><?php esc_html_e( 'Qty', 'devdiggers-returns-for-woocommerce' ); ?></th>
			<th style="text-align:left;"><?php esc_html_e( 'Reason', 'devdiggers-returns-for-woocommerce' ); ?></th>
		</tr>
	</thead>
	<tbody>
		<?php foreach ( $items as $ddwcwr_item ) : ?>
			<tr>
				<td style="text-align:left;">
					<?php echo esc_html( $ddwcwr_item['name'] ); ?>
				</td>
				<td style="text-align:left;"><?php echo esc_html( $ddwcwr_item['qty'] ); ?></td>
				<td style="text-align:left;"><?php echo esc_html( ddwcwr_get_reason_label( $ddwcwr_item['reason'] ) ); ?></td>
			</tr>
		<?php endforeach; ?>
	</tbody>
	<tfoot>
		<tr>
			<th scope="row" colspan="2" style="text-align:left;"><?php esc_html_e( 'Status', 'devdiggers-returns-for-woocommerce' ); ?></th>
			<td style="text-align:left;"><?php echo esc_html( ddwcwr_get_status_label( $ddwcwr_request['status'] ) ); ?></td>
		</tr>
	</tfoot>
</table>

<?php
if ( $additional_content ) {
	echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
}

do_action( 'woocommerce_email_footer', $email ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce core email hook.
