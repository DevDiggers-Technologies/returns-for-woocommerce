<?php
/**
 * Request email (plain text)
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
 */

defined( 'ABSPATH' ) || exit();

$ddwcwr_request = wp_parse_args( (array) $request, array_fill_keys( [ 'customer_name', 'number', 'type', 'status' ], '' ) );

echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n";
echo esc_html( wp_strip_all_tags( $email_heading ) );
echo "\n=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

if ( ! $sent_to_admin && $ddwcwr_request['customer_name'] ) {
	/* translators: %s: customer name */
	echo esc_html( sprintf( __( 'Hi %s,', 'devdiggers-returns-for-woocommerce' ), $ddwcwr_request['customer_name'] ) ) . "\n\n";
}

if ( $intro ) {
	echo esc_html( $intro ) . "\n\n";
}

if ( '' !== trim( (string) $note ) ) {
	echo '> ' . esc_html( str_replace( "\n", "\n> ", $note ) ) . "\n\n";
}

foreach ( $extra as $ddwcwr_label => $ddwcwr_value ) {
	echo esc_html( $ddwcwr_label ) . ': ' . esc_html( wp_strip_all_tags( html_entity_decode( $ddwcwr_value ) ) ) . "\n";
}

if ( ! empty( $button[0] ) ) {
	echo "\n" . esc_html( $button[1] ) . ': ' . esc_url_raw( $button[0] ) . "\n";
}

/* translators: 1: request number, 2: order number */
echo "\n" . esc_html( sprintf( __( '%1$s for order #%2$s', 'devdiggers-returns-for-woocommerce' ), $ddwcwr_request['number'], $email->placeholders['{order_number}'] ) ) . "\n";

foreach ( $items as $ddwcwr_item ) {
	echo '- ' . esc_html( $ddwcwr_item['name'] ) . ' × ' . esc_html( $ddwcwr_item['qty'] ) . ' (' . esc_html( ddwcwr_get_reason_label( $ddwcwr_item['reason'] ) ) . ")\n";
}

echo "\n" . esc_html__( 'Status', 'devdiggers-returns-for-woocommerce' ) . ': ' . esc_html( ddwcwr_get_status_label( $ddwcwr_request['status'] ) ) . "\n\n";

if ( $additional_content ) {
	echo esc_html( wp_strip_all_tags( wptexturize( $additional_content ) ) ) . "\n\n";
}

echo wp_kses_post( apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce core email filter.
