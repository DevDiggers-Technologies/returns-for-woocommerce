<?php
/**
 * Global configuration
 *
 * Every setting the plugin reads, with its default. A setting that was never saved gets its default; a saved
 * empty checkbox stays empty, so turning a feature off sticks.
 *
 * @package DevDiggers Returns
 * @version 1.0.0
 */

defined( 'ABSPATH' ) || exit();

global $ddwcwr_configuration;

$ddwcwr_defaults = [
	// General.
	'enabled'                         => 'yes',
	'allow_refund'                    => 'yes',
	'allow_cancellation'              => 'yes',
	'cancellation_statuses'           => [ 'wc-pending', 'wc-on-hold', 'wc-processing' ],
	'return_window_days'              => 30,
	'window_from'                     => 'completed',
	'eligible_order_statuses'         => [ 'wc-completed' ],
	'my_account_endpoint'             => 'returns',
	'my_account_endpoint_title'       => __( 'Returns', 'devdiggers-returns' ),
	'portal_page_id'                  => 0,
	'require_note'                    => '',
	'final_sale_categories'           => [],
	'show_policy_product'             => 'yes',
	'show_policy_checkout'            => 'yes',
	'policy_page_id'                  => 0,
	'policy_text'                     => '',
	// Uploads.
	'allow_uploads'                   => 'yes',
	'max_files'                       => 5,
	'max_file_size'                   => 8,
	'allowed_file_types'              => [ 'jpg', 'jpeg', 'png', 'webp', 'gif', 'mp4', 'mov', 'webm', 'pdf' ],
	// Refunds.
	'refund_via_gateway'              => 'yes',
	'restock_on_receive'              => 'yes',
	// EU withdrawal.
	'withdrawal_enabled'              => 'yes',
	'withdrawal_scope'                => 'eu',
	'withdrawal_countries'            => [],
	'withdrawal_days'                 => 14,
	'withdrawal_from'                 => 'completed',
	'withdrawal_page_id'              => 0,
	'withdrawal_footer_link'          => 'yes',
	'withdrawal_in_emails'            => 'yes',
	'withdrawal_model_form'           => 'yes',
	'withdrawal_digital_consent'      => '',
	'withdrawal_digital_consent_text' => '',
	'withdrawal_rate_limit'           => 5,
	'withdrawal_excluded_categories'  => [],
	// Advanced.
	'delete_data_on_uninstall'        => '',
];

$ddwcwr_configuration = [];

foreach ( $ddwcwr_defaults as $ddwcwr_key => $ddwcwr_default ) {
	$ddwcwr_value = get_option( '_ddwcwr_' . $ddwcwr_key, null );

	// Numbers and lists fall back to the default when saved empty; text and checkboxes keep what was saved.
	if ( null === $ddwcwr_value || ( '' === $ddwcwr_value && ( is_int( $ddwcwr_default ) || is_array( $ddwcwr_default ) ) ) ) {
		$ddwcwr_value = $ddwcwr_default;
	}

	if ( is_array( $ddwcwr_default ) && ! is_array( $ddwcwr_value ) ) {
		$ddwcwr_value = array_filter( (array) $ddwcwr_value );
	}

	$ddwcwr_configuration[ $ddwcwr_key ] = $ddwcwr_value;
}

if ( empty( $ddwcwr_configuration['my_account_endpoint'] ) ) {
	$ddwcwr_configuration['my_account_endpoint'] = 'returns';
}

$ddwcwr_configuration['statuses'] = array_values( (array) get_option( '_ddwcwr_statuses', DDWCWR_Install::ddwcwr_get_default_statuses() ) );
$ddwcwr_configuration['reasons']  = get_option( '_ddwcwr_reasons', DDWCWR_Install::ddwcwr_get_default_reasons() );

$ddwcwr_configuration = apply_filters( 'ddwcwr_configuration', $ddwcwr_configuration );

unset( $ddwcwr_defaults, $ddwcwr_key, $ddwcwr_default, $ddwcwr_value );
