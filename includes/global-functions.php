<?php
/**
 * Global helper functions
 *
 * @package DevDiggers Returns
 * @version 1.0.0
 */

use DDWCWPReturns\Helper\Order\DDWCWR_WooCommerce_Order_Adapter;
use DDWCWPReturns\Helper\Order\DDWCWR_Order_Adapter_Interface;

defined( 'ABSPATH' ) || exit();

if ( ! function_exists( 'ddwcwr_order_adapter' ) ) {
	/**
	 * The order adapter every returns flow goes through.
	 *
	 * @return DDWCWR_Order_Adapter_Interface
	 */
	function ddwcwr_order_adapter() {
		static $adapter = null;

		if ( null === $adapter ) {
			$adapter = apply_filters( 'ddwcwr_order_adapter', new DDWCWR_WooCommerce_Order_Adapter() );
		}

		return $adapter;
	}
}

if ( ! function_exists( 'ddwcwr_get_statuses' ) ) {
	/**
	 * Request statuses keyed by slug.
	 *
	 * @return array
	 */
	function ddwcwr_get_statuses() {
		global $ddwcwr_configuration;

		$statuses = [];

		foreach ( (array) $ddwcwr_configuration['statuses'] as $status ) {
			if ( ! empty( $status['slug'] ) ) {
				$statuses[ $status['slug'] ] = wp_parse_args(
					$status,
					[
						'label'  => $status['slug'],
						'color'  => '#6b7280',
						'notify' => '',
						'core'   => '',
					]
				);
			}
		}

		return apply_filters( 'ddwcwr_statuses', $statuses );
	}
}

if ( ! function_exists( 'ddwcwr_get_final_statuses' ) ) {
	/**
	 * Statuses that close a request.
	 *
	 * @return array
	 */
	function ddwcwr_get_final_statuses() {
		return apply_filters( 'ddwcwr_final_statuses', [ 'completed', 'rejected', 'cancelled' ] );
	}
}

if ( ! function_exists( 'ddwcwr_get_status_label' ) ) {
	/**
	 * Status label.
	 *
	 * @param string $slug Status slug.
	 * @return string
	 */
	function ddwcwr_get_status_label( $slug ) {
		$statuses = ddwcwr_get_statuses();

		return isset( $statuses[ $slug ] ) ? $statuses[ $slug ]['label'] : ucwords( str_replace( [ '_', '-' ], ' ', (string) $slug ) );
	}
}

if ( ! function_exists( 'ddwcwr_get_status_badge' ) ) {
	/**
	 * Status badge markup (escaped).
	 *
	 * @param string $slug Status slug.
	 * @return string
	 */
	function ddwcwr_get_status_badge( $slug ) {
		$statuses = ddwcwr_get_statuses();
		$color    = isset( $statuses[ $slug ] ) ? sanitize_hex_color( $statuses[ $slug ]['color'] ) : '#6b7280';

		return sprintf(
			'<mark class="ddwcwr-status ddwcwr-status-%1$s" style="--ddwcwr-status-color:%2$s">%3$s</mark>',
			esc_attr( sanitize_html_class( $slug ) ),
			esc_attr( $color ? $color : '#6b7280' ),
			esc_html( ddwcwr_get_status_label( $slug ) )
		);
	}
}

if ( ! function_exists( 'ddwcwr_get_reasons' ) ) {
	/**
	 * Return reasons keyed by slug.
	 *
	 * @param bool $active_only Only active reasons.
	 * @return array
	 */
	function ddwcwr_get_reasons( $active_only = true ) {
		global $ddwcwr_configuration;

		$reasons = [];

		foreach ( (array) $ddwcwr_configuration['reasons'] as $reason ) {
			if ( empty( $reason['slug'] ) || ( $active_only && isset( $reason['status'] ) && 'active' !== $reason['status'] ) ) {
				continue;
			}

			$reasons[ $reason['slug'] ] = wp_parse_args(
				$reason,
				[
					'label'          => $reason['slug'],
					'require_photo'  => '',
					'merchant_fault' => '',
					'status'         => 'active',
				]
			);
		}

		return apply_filters( 'ddwcwr_reasons', $reasons );
	}
}

if ( ! function_exists( 'ddwcwr_get_reason_label' ) ) {
	/**
	 * Reason label.
	 *
	 * @param string $slug Reason slug.
	 * @return string
	 */
	function ddwcwr_get_reason_label( $slug ) {
		if ( 'withdrawal' === $slug ) {
			return __( 'Right of withdrawal', 'devdiggers-returns-for-woocommerce' );
		}

		// Cancellation items carry the type as their reason, like withdrawals.
		if ( 'cancellation' === $slug ) {
			return __( 'Order cancellation', 'devdiggers-returns-for-woocommerce' );
		}

		$reasons = ddwcwr_get_reasons( false );

		return isset( $reasons[ $slug ] ) ? $reasons[ $slug ]['label'] : $slug;
	}
}

if ( ! function_exists( 'ddwcwr_get_request_types' ) ) {
	/**
	 * Request types.
	 *
	 * @return array
	 */
	function ddwcwr_get_request_types() {
		return apply_filters(
			'ddwcwr_request_types',
			[
				'return'       => __( 'Return', 'devdiggers-returns-for-woocommerce' ),
				'withdrawal'   => __( 'EU withdrawal', 'devdiggers-returns-for-woocommerce' ),
				'cancellation' => __( 'Cancellation', 'devdiggers-returns-for-woocommerce' ),
			]
		);
	}
}

if ( ! function_exists( 'ddwcwr_get_resolutions' ) ) {
	/**
	 * Resolutions.
	 *
	 * @return array
	 */
	function ddwcwr_get_resolutions() {
		return apply_filters(
			'ddwcwr_resolutions',
			[
				'refund' => __( 'Refund to original payment', 'devdiggers-returns-for-woocommerce' ),
			]
		);
	}
}

if ( ! function_exists( 'ddwcwr_get_item_conditions' ) ) {
	/**
	 * Item conditions a customer can choose.
	 *
	 * @return array
	 */
	function ddwcwr_get_item_conditions() {
		return apply_filters(
			'ddwcwr_item_conditions',
			[
				'unopened'  => __( 'Unopened', 'devdiggers-returns-for-woocommerce' ),
				'opened'    => __( 'Opened, unused', 'devdiggers-returns-for-woocommerce' ),
				'used'      => __( 'Used or worn', 'devdiggers-returns-for-woocommerce' ),
				'damaged'   => __( 'Damaged', 'devdiggers-returns-for-woocommerce' ),
				'defective' => __( 'Defective', 'devdiggers-returns-for-woocommerce' ),
			]
		);
	}
}

if ( ! function_exists( 'ddwcwr_get_request_number' ) ) {
	/**
	 * Human request number, e.g. RMA-00042.
	 *
	 * @param int $request_id Request ID.
	 * @return string
	 */
	function ddwcwr_get_request_number( $request_id ) {
		return apply_filters( 'ddwcwr_request_number', 'RMA-' . str_pad( absint( $request_id ), 5, '0', STR_PAD_LEFT ), $request_id );
	}
}

if ( ! function_exists( 'ddwcwr_parse_request_number' ) ) {
	/**
	 * Request ID from a number such as "RMA-00042" or "42".
	 *
	 * @param string $number Number.
	 * @return int
	 */
	function ddwcwr_parse_request_number( $number ) {
		return absint( preg_replace( '/\D/', '', (string) $number ) );
	}
}

if ( ! function_exists( 'ddwcwr_get_portal_url' ) ) {
	/**
	 * Guest portal URL.
	 *
	 * @return string
	 */
	function ddwcwr_get_portal_url() {
		global $ddwcwr_configuration;

		$page_id = absint( $ddwcwr_configuration['portal_page_id'] );

		return $page_id ? get_permalink( $page_id ) : home_url( '/' );
	}
}

if ( ! function_exists( 'ddwcwr_get_withdrawal_url' ) ) {
	/**
	 * Withdrawal page URL, optionally for one order.
	 *
	 * @param int $order_id Order ID.
	 * @return string
	 */
	function ddwcwr_get_withdrawal_url( $order_id = 0 ) {
		global $ddwcwr_configuration;

		$page_id = absint( $ddwcwr_configuration['withdrawal_page_id'] );
		$url     = $page_id ? get_permalink( $page_id ) : home_url( '/' );

		return $order_id ? add_query_arg( 'ddwcwr-order', absint( $order_id ), $url ) : $url;
	}
}

if ( ! function_exists( 'ddwcwr_get_account_url' ) ) {
	/**
	 * My Account returns endpoint URL.
	 *
	 * @param string $value Endpoint value (e.g. "view-12" or "new-34").
	 * @return string
	 */
	function ddwcwr_get_account_url( $value = '' ) {
		global $ddwcwr_configuration;

		return wc_get_endpoint_url( $ddwcwr_configuration['my_account_endpoint'], $value, wc_get_page_permalink( 'myaccount' ) );
	}
}

if ( ! function_exists( 'ddwcwr_get_request_view_url' ) ) {
	/**
	 * Where the customer views a request: My Account for account holders, a keyed portal link otherwise.
	 *
	 * @param array $request Request row.
	 * @param bool  $force_key Always return the keyed link (emails to guests and customers alike).
	 * @return string
	 */
	function ddwcwr_get_request_view_url( $request, $force_key = false ) {
		if ( ! $force_key && ! empty( $request['customer_id'] ) ) {
			return ddwcwr_get_account_url( 'view-' . absint( $request['id'] ) );
		}

		return add_query_arg(
			[
				'ddwcwr-request' => absint( $request['id'] ),
				'ddwcwr-key'     => rawurlencode( $request['access_key'] ),
			],
			ddwcwr_get_portal_url()
		);
	}
}

if ( ! function_exists( 'ddwcwr_request_needs_shipping' ) ) {
	/**
	 * Whether the customer still has to send items back for a request.
	 *
	 * @param array $request Request.
	 * @return bool
	 */
	function ddwcwr_request_needs_shipping( $request ) {
		return 'cancellation' !== $request['type'] && in_array( $request['status'], [ 'approved', 'awaiting_return' ], true );
	}
}

if ( ! function_exists( 'ddwcwr_get_eu_countries' ) ) {
	/**
	 * EU + EEA countries where the withdrawal function applies.
	 *
	 * @return array
	 */
	function ddwcwr_get_eu_countries() {
		$eu = WC()->countries->get_european_union_countries();

		return apply_filters( 'ddwcwr_withdrawal_eu_countries', array_merge( $eu, [ 'IS', 'LI', 'NO' ] ) );
	}
}

if ( ! function_exists( 'ddwcwr_withdrawal_applies_to_country' ) ) {
	/**
	 * Whether the withdrawal button is offered for a billing country.
	 *
	 * @param string $country Country code.
	 * @return bool
	 */
	function ddwcwr_withdrawal_applies_to_country( $country ) {
		global $ddwcwr_configuration;

		if ( 'yes' !== $ddwcwr_configuration['withdrawal_enabled'] ) {
			return false;
		}

		switch ( $ddwcwr_configuration['withdrawal_scope'] ) {
			case 'all':
				return true;
			case 'countries':
				return in_array( $country, (array) $ddwcwr_configuration['withdrawal_countries'], true );
			default:
				return in_array( $country, ddwcwr_get_eu_countries(), true );
		}
	}
}

if ( ! function_exists( 'ddwcwr_get_digital_consent_text' ) ) {
	/**
	 * Checkout consent text for digital content (Art. 16(m)).
	 *
	 * @return string
	 */
	function ddwcwr_get_digital_consent_text() {
		global $ddwcwr_configuration;

		$text = trim( (string) $ddwcwr_configuration['withdrawal_digital_consent_text'] );

		return '' !== $text ? $text : __( 'I agree that you start supplying the digital content before the withdrawal period ends. I understand that I lose my right of withdrawal once the supply has started.', 'devdiggers-returns-for-woocommerce' );
	}
}

if ( ! function_exists( 'ddwcwr_cart_needs_digital_consent' ) ) {
	/**
	 * Whether the cart holds downloadable products the customer could withdraw from, so checkout must ask for consent (Art. 16(m)).
	 *
	 * @return bool
	 */
	function ddwcwr_cart_needs_digital_consent() {
		global $ddwcwr_configuration;

		if ( 'yes' !== $ddwcwr_configuration['withdrawal_digital_consent'] || ! WC()->cart || ! WC()->customer ) {
			return false;
		}

		$country = WC()->customer->get_billing_country() ? WC()->customer->get_billing_country() : WC()->customer->get_shipping_country();

		if ( ! ddwcwr_withdrawal_applies_to_country( $country ) ) {
			return false;
		}

		foreach ( WC()->cart->get_cart() as $cart_item ) {
			$product = $cart_item['data'];

			if ( $product instanceof WC_Product && $product->is_downloadable() && ! ddwcwr_is_withdrawal_excluded( [ 'parent_id' => $product->get_parent_id() ? $product->get_parent_id() : $product->get_id() ] ) ) {
				return true;
			}
		}

		return false;
	}
}

if ( ! function_exists( 'ddwcwr_current_user_can' ) ) {
	/**
	 * Whether the current user may manage returns (shop managers and administrators).
	 *
	 * @return bool
	 */
	function ddwcwr_current_user_can() {
		return current_user_can( 'manage_woocommerce' );
	}
}

if ( ! function_exists( 'ddwcwr_decode' ) ) {
	/**
	 * Decode a JSON column to an array.
	 *
	 * @param mixed $value JSON string or array.
	 * @return array
	 */
	function ddwcwr_decode( $value ) {
		if ( is_array( $value ) ) {
			return $value;
		}

		$decoded = json_decode( (string) $value, true );

		return is_array( $decoded ) ? $decoded : [];
	}
}

if ( ! function_exists( 'ddwcwr_get_client_ip_hash' ) ) {
	/**
	 * Salted hash of the visitor IP (rate limits and abuse checks, never the raw IP).
	 *
	 * @return string
	 */
	function ddwcwr_get_client_ip_hash() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

		return $ip ? hash_hmac( 'sha256', $ip, wp_salt( 'auth' ) ) : '';
	}
}

if ( ! function_exists( 'ddwcwr_get_list_views' ) ) {
	/**
	 * Status tabs above the Requests and Withdrawals lists.
	 *
	 * @param string $menu Menu slug (requests|withdrawals).
	 * @param string $current Current status tab.
	 * @param string $type Only this request type.
	 * @return array
	 */
	function ddwcwr_get_list_views( $menu, $current, $type = '' ) {
		$counts = ( new \DDWCWPReturns\Helper\Request\DDWCWR_Request_Helper() )->get_status_counts( $type );
		$base   = admin_url( 'admin.php?page=ddwcwr-dashboard&menu=' . $menu );
		$open   = 0;
		$link   = function ( $slug, $label, $count ) use ( $base, $current ) {
			return sprintf( '<a href="%1$s"%2$s>%3$s <span class="count">(%4$d)</span></a>', esc_url( $slug ? add_query_arg( 'status', $slug, $base ) : $base ), $current === $slug ? ' class="current" aria-current="page"' : '', esc_html( $label ), $count );
		};

		foreach ( $counts as $slug => $count ) {
			$open += in_array( $slug, ddwcwr_get_final_statuses(), true ) ? 0 : (int) $count;
		}

		$views = [
			'all'  => $link( '', __( 'All', 'devdiggers-returns-for-woocommerce' ), array_sum( $counts ) ),
			'open' => $link( 'open', __( 'Open', 'devdiggers-returns-for-woocommerce' ), $open ),
		];

		foreach ( ddwcwr_get_statuses() as $slug => $status ) {
			if ( ! empty( $counts[ $slug ] ) ) {
				$views[ $slug ] = $link( $slug, $status['label'], (int) $counts[ $slug ] );
			}
		}

		if ( 'withdrawals' === $menu ) {
			$unmatched = count( (array) get_option( '_ddwcwr_unmatched_withdrawals', [] ) );

			if ( $unmatched ) {
				$views['unmatched'] = $link( 'unmatched', __( 'Unmatched statements', 'devdiggers-returns-for-woocommerce' ), $unmatched );
			}
		}

		return $views;
	}
}

if ( ! function_exists( 'ddwcwr_get_policy_line' ) ) {
	/**
	 * One-line return policy for product pages and checkout.
	 *
	 * The store's own text when set ({days} is replaced), otherwise a line built from the return window.
	 *
	 * @param int $days Return window in days.
	 * @return string Plain text.
	 */
	function ddwcwr_get_policy_line( $days ) {
		global $ddwcwr_configuration;

		$days = absint( $days );

		if ( '' !== trim( (string) $ddwcwr_configuration['policy_text'] ) ) {
			return str_replace( '{days}', $days, $ddwcwr_configuration['policy_text'] );
		}

		return 'created' === $ddwcwr_configuration['window_from']
			/* translators: %d: days */
			? sprintf( _n( 'Returns accepted within %d day of purchase.', 'Returns accepted within %d days of purchase.', $days, 'devdiggers-returns-for-woocommerce' ), $days )
			/* translators: %d: days */
			: sprintf( _n( 'Returns accepted within %d day of delivery.', 'Returns accepted within %d days of delivery.', $days, 'devdiggers-returns-for-woocommerce' ), $days );
	}
}

if ( ! function_exists( 'ddwcwr_icon' ) ) {
	/**
	 * Small stroke icon for the customer screens.
	 *
	 * @param string $name Icon name.
	 * @param int    $size Size in px.
	 * @return string SVG markup.
	 */
	function ddwcwr_icon( $name, $size = 20 ) {
		$paths = [
			'return'   => '<path d="M9 14 4 9l5-5"/><path d="M4 9h10.5a5.5 5.5 0 0 1 0 11H11"/>',
			'box'      => '<path d="M21 8 12 3 3 8v8l9 5 9-5V8Z"/><path d="m3 8 9 5 9-5M12 13v8"/>',
			'check'    => '<path d="M20 6 9 17l-5-5"/>',
			'chevron'  => '<path d="m9 18 6-6-6-6"/>',
			'back'     => '<path d="m15 18-6-6 6-6"/>',
			'chat'     => '<path d="M21 12a8 8 0 0 1-11.6 7.1L4 20l1-4.6A8 8 0 1 1 21 12Z"/>',
			'label'    => '<path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><path d="M14 3v6h6M8 13h8M8 17h5"/>',
			'search'   => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
			'shield'   => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/>',
			'alert'    => '<circle cx="12" cy="12" r="9"/><path d="M12 8v4M12 16h.01"/>',
			'upload'   => '<path d="M12 16V4M7 9l5-5 5 5"/><path d="M4 16v3a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-3"/>',
			'withdraw' => '<path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><path d="M14 3v6h6"/><path d="m10 12-2.5 2.5L10 17"/><path d="M7.5 14.5H13a2.5 2.5 0 0 0 0-5h-1"/>',
			'document' => '<path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><path d="M14 3v6h6"/>',
		];

		if ( ! isset( $paths[ $name ] ) ) {
			return '';
		}

		return '<svg class="ddwcwr-icon-svg" width="' . absint( $size ) . '" height="' . absint( $size ) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $paths[ $name ] . '</svg>';
	}
}

if ( ! function_exists( 'ddwcwr_kses_icon' ) ) {
	/**
	 * Allowed tags for ddwcwr_icon() output.
	 *
	 * @return array
	 */
	function ddwcwr_kses_icon() {
		$shape = [
			'd'  => true,
			'cx' => true,
			'cy' => true,
			'r'  => true,
		];

		return [
			'svg'    => [
				'class'           => true,
				'width'           => true,
				'height'          => true,
				'viewbox'         => true,
				'fill'            => true,
				'stroke'          => true,
				'stroke-width'    => true,
				'stroke-linecap'  => true,
				'stroke-linejoin' => true,
				'aria-hidden'     => true,
				'focusable'       => true,
			],
			'path'   => $shape,
			'circle' => $shape,
		];
	}
}

if ( ! function_exists( 'ddwcwr_get_trader_address' ) ) {
	/**
	 * Store name, address and email for the model withdrawal form, from the WooCommerce store settings.
	 *
	 * @return string[] Lines.
	 */
	function ddwcwr_get_trader_address() {
		$countries = WC()->countries;
		$address   = $countries->get_formatted_address(
			[
				'company'   => get_bloginfo( 'name' ),
				'address_1' => $countries->get_base_address(),
				'address_2' => $countries->get_base_address_2(),
				'city'      => $countries->get_base_city(),
				'state'     => $countries->get_base_state(),
				'postcode'  => $countries->get_base_postcode(),
				'country'   => $countries->get_base_country(),
			],
			"\n"
		);
		$lines     = array_filter( array_map( 'trim', explode( "\n", wp_strip_all_tags( $address ) ) ) );
		$lines[]   = get_option( 'woocommerce_email_from_address', get_option( 'admin_email' ) );

		return (array) apply_filters( 'ddwcwr_trader_address', array_values( $lines ) );
	}
}

if ( ! function_exists( 'ddwcwr_is_final_sale' ) ) {
	/**
	 * Whether an order item is final sale (no returns): the product's own box or a final-sale category.
	 * Returns only. The EU right of withdrawal has its own exemptions.
	 *
	 * @param array $item Order item summary from the order adapter, or [ 'parent_id' => product ID ].
	 * @return bool
	 */
	function ddwcwr_is_final_sale( $item ) {
		global $ddwcwr_configuration;

		$product = wc_get_product( $item['parent_id'] );

		if ( $product && 'yes' === $product->get_meta( '_ddwcwr_final_sale', true ) ) {
			return true;
		}

		$categories = array_map( 'intval', (array) $ddwcwr_configuration['final_sale_categories'] );

		if ( $categories ) {
			$terms = wp_get_post_terms( $item['parent_id'], 'product_cat', [ 'fields' => 'ids' ] );

			if ( ! is_wp_error( $terms ) && array_intersect( $categories, array_map( 'intval', $terms ) ) ) {
				return true;
			}
		}

		return (bool) apply_filters( 'ddwcwr_item_final_sale', false, $item );
	}
}

if ( ! function_exists( 'ddwcwr_is_withdrawal_excluded' ) ) {
	/**
	 * Whether an order item is exempt from the right of withdrawal (Art. 16): the product's own box or an exempt category.
	 *
	 * @param array $item Order item summary from the order adapter.
	 * @return bool
	 */
	function ddwcwr_is_withdrawal_excluded( $item ) {
		global $ddwcwr_configuration;

		$product = wc_get_product( $item['parent_id'] );

		if ( $product && 'yes' === $product->get_meta( '_ddwcwr_withdrawal_excluded', true ) ) {
			return true;
		}

		$excluded = array_map( 'intval', (array) $ddwcwr_configuration['withdrawal_excluded_categories'] );

		if ( $excluded ) {
			$terms = wp_get_post_terms( $item['parent_id'], 'product_cat', [ 'fields' => 'ids' ] );

			if ( ! is_wp_error( $terms ) && array_intersect( $excluded, array_map( 'intval', $terms ) ) ) {
				return true;
			}
		}

		return (bool) apply_filters( 'ddwcwr_withdrawal_item_excluded', false, $item );
	}
}

if ( ! function_exists( 'ddwcwr_get_upgrade_card' ) ) {
	/**
	 * Upgrade card for a Pro feature, with its screenshot when one ships in assets/images/pro.
	 *
	 * @param string $image Screenshot name without extension.
	 * @param array  $args  Heading, description and list_features.
	 * @return string
	 */
	function ddwcwr_get_upgrade_card( $image, $args ) {
		$file = 'assets/images/pro/' . sanitize_file_name( $image ) . '.webp';

		return ddfw_get_upgrade_to_pro_section(
			array_merge(
				$args,
				[
					'image_url'   => file_exists( DDWCWR_PLUGIN_FILE . $file ) ? DDWCWR_PLUGIN_URL . $file : '',
					'upgrade_url' => 'https://wpreturns.com/',
				]
			)
		);
	}
}
