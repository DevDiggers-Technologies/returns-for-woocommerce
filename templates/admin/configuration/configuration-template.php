<?php
/**
 * Configuration template: every settings tab.
 *
 * Field definitions live here once and are reused by DDWCWR_Admin_Functions::ddwcwr_register_settings(), so every
 * field shown is registered and sanitized by its type. Pro settings are shown as locked fields (renamed, never
 * registered) or, when a whole section is Pro, as one upgrade card.
 *
 * @package WPReturns
 * @version 1.0.0
 */

namespace DDWCWPReturns\Templates\Admin\Configuration;

use DevDiggers\Framework\Includes\DDFW_Layout;
use DDWCWPReturns\Helper\Upload\DDWCWR_Upload_Helper;

defined( 'ABSPATH' ) || exit();

if ( ! class_exists( 'DDWCWR_Configuration_Template' ) ) {
	/**
	 * Configuration template class
	 */
	class DDWCWR_Configuration_Template {
		/**
		 * Construct
		 *
		 * @param string $tab Tab.
		 * @param array  $ddwcwr_configuration Configuration.
		 */
		public function __construct( $tab, $ddwcwr_configuration ) {
			if ( ! in_array( $tab, self::get_tabs(), true ) ) {
				$tab = 'general';
			}

			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display only.
			if ( ! empty( $_GET['settings-updated'] ) && in_array( $tab, [ 'general' ], true ) ) {
				flush_rewrite_rules();
			}

			$layout = new DDFW_Layout();
			// The Emails tab only links to WooCommerce email settings and Pro tabs hold no setting: no form to save.
			$layout->get_form_section_layout( self::get_tab_sections( $tab, $ddwcwr_configuration ), in_array( $tab, self::get_pro_tabs(), true ) || 'emails' === $tab ? '' : 'ddwcwr-' . $tab . '-configuration-fields' );
		}

		/**
		 * Tabs (same as Pro, so upgrading keeps every setting where the store expects it).
		 *
		 * @return array
		 */
		public static function get_tabs() {
			return [ 'general', 'policy', 'reasons', 'statuses', 'resolutions', 'withdrawal', 'warranty', 'labels', 'fraud', 'automation', 'emails', 'appearance', 'advanced' ];
		}

		/**
		 * Tabs whose every setting is a Pro feature.
		 *
		 * @return array
		 */
		public static function get_pro_tabs() {
			return [ 'warranty', 'labels', 'fraud', 'automation', 'appearance' ];
		}

		/**
		 * Page options.
		 *
		 * @return array
		 */
		protected static function get_page_options() {
			$pages = [ '' => __( 'Select a page', 'wpreturns' ) ];

			foreach ( get_pages( [ 'post_status' => 'publish' ] ) as $page ) {
				$pages[ $page->ID ] = $page->post_title;
			}

			return $pages;
		}

		/**
		 * Multi-select field.
		 *
		 * @param string $id Field ID.
		 * @param string $label Label.
		 * @param string $description Description.
		 * @param array  $options Options.
		 * @param array  $value Value.
		 * @return array
		 */
		protected static function multi_select( $id, $label, $description, $options, $value ) {
			return [
				'type'              => 'select',
				'id'                => $id,
				'name'              => '_' . str_replace( '-', '_', $id ) . '[]',
				'label'             => $label,
				'description'       => $description,
				'options'           => $options,
				'value'             => array_map( 'strval', (array) $value ),
				'placeholder'       => __( 'Choose…', 'wpreturns' ),
				'custom_attributes' => [ 'multiple' => 'multiple' ],
			];
		}

		/**
		 * Disabled Pro field, labelled PRO. Its id is renamed, so no setting of that name exists to save.
		 *
		 * @param array $field Field.
		 * @return array
		 */
		protected static function locked( $field ) {
			return ddfw_locked_field( $field + [ 'value' => '' ], 'ddwcwr' );
		}

		/**
		 * Section that is Pro from top to bottom: heading plus one upgrade card, no fields.
		 *
		 * @param string $image Screenshot name in assets/images/pro.
		 * @param string $heading Section heading.
		 * @param string $description Section description.
		 * @param array  $card Card heading, description and list_features.
		 * @return array
		 */
		protected static function pro_section( $image, $heading, $description, $card ) {
			return [
				'header'            => [
					'heading'     => $heading,
					'description' => $description,
				],
				'after_header_html' => ddwcwr_get_upgrade_card( $image, $card ),
			];
		}

		/**
		 * Sections of a tab.
		 *
		 * @param string $tab Tab.
		 * @param array  $c Configuration.
		 * @return array
		 */
		public static function get_tab_sections( $tab, $c ) {
			$checkbox = function ( $id, $label, $checkbox_label, $description, $key ) use ( $c ) {
				return [
					'type'           => 'checkbox',
					'id'             => $id,
					'label'          => $label,
					'checkbox_label' => $checkbox_label,
					'description'    => $description,
					'value'          => $c[ $key ],
				];
			};
			$number   = function ( $id, $label, $description, $key, $min = 0, $step = 1 ) use ( $c ) {
				return [
					'type'              => 'number',
					'id'                => $id,
					'label'             => $label,
					'description'       => $description,
					'value'             => $c[ $key ],
					'custom_attributes' => [
						'min'  => $min,
						'step' => $step,
					],
				];
			};

			switch ( $tab ) {
				case 'general':
					return [
						[
							'header' => [
								'heading'     => esc_html__( 'General', 'wpreturns' ),
								'description' => esc_html__( 'Turn the customer side on, choose which requests customers can make, and where they make them.', 'wpreturns' ),
							],
							'fields' => [
								$checkbox( 'ddwcwr-enabled', esc_html__( 'Customer requests', 'wpreturns' ), esc_html__( 'Let customers request returns and cancellations', 'wpreturns' ), esc_html__( 'When off, customers see nothing; you can still create requests yourself. The EU withdrawal button has its own switch.', 'wpreturns' ), 'enabled' ),
								self::multi_select( 'ddwcwr-eligible-order-statuses', esc_html__( 'Orders that can be returned', 'wpreturns' ), esc_html__( 'Order statuses that show the "Request a return" button.', 'wpreturns' ), wc_get_order_statuses(), $c['eligible_order_statuses'] ),
								$checkbox( 'ddwcwr-allow-refund', esc_html__( 'Returns', 'wpreturns' ), esc_html__( 'Let customers return items for a refund', 'wpreturns' ), esc_html__( 'Money goes back to the card or method they paid with, once you approve the return.', 'wpreturns' ), 'allow_refund' ),
								self::locked(
									[
										'type'           => 'checkbox',
										'id'             => 'allow-store-credit',
										'label'          => esc_html__( 'Store credit', 'wpreturns' ),
										'checkbox_label' => esc_html__( 'Offer store credit, with an optional bonus', 'wpreturns' ),
										'description'    => esc_html__( 'Keep the money in your shop: customers pick a coupon or DevDiggers Wallet credit instead of a refund.', 'wpreturns' ),
									]
								),
								self::locked(
									[
										'type'           => 'checkbox',
										'id'             => 'allow-exchange',
										'label'          => esc_html__( 'Exchanges', 'wpreturns' ),
										'checkbox_label' => esc_html__( 'Offer exchanges for another size, color or any product', 'wpreturns' ),
										'description'    => esc_html__( 'A linked exchange order is created for you; the price difference is charged or refunded.', 'wpreturns' ),
									]
								),
								self::locked(
									[
										'type'           => 'checkbox',
										'id'             => 'allow-warranty',
										'label'          => esc_html__( 'Warranty claims', 'wpreturns' ),
										'checkbox_label' => esc_html__( 'Let customers file warranty claims for repair or replacement', 'wpreturns' ),
										'description'    => esc_html__( 'Warranty per product, checked automatically when a claim comes in.', 'wpreturns' ),
									]
								),
								$checkbox( 'ddwcwr-allow-cancellation', esc_html__( 'Cancellation requests', 'wpreturns' ), esc_html__( 'Let customers ask to cancel an order before it ships', 'wpreturns' ), esc_html__( 'Adds a "Request cancellation" button to orders that have not shipped yet. Approving one cancels and refunds the order.', 'wpreturns' ), 'allow_cancellation' ),
								self::multi_select( 'ddwcwr-cancellation-statuses', esc_html__( 'Cancellable order statuses', 'wpreturns' ), esc_html__( 'Orders in these statuses can get a cancellation request.', 'wpreturns' ), wc_get_order_statuses(), $c['cancellation_statuses'] ),
								self::locked(
									[
										'type'           => 'checkbox',
										'id'             => 'auto-approve-cancellation',
										'label'          => esc_html__( 'Instant cancellations', 'wpreturns' ),
										'checkbox_label' => esc_html__( 'Approve, cancel and refund cancellation requests automatically', 'wpreturns' ),
										'description'    => esc_html__( 'Unshipped orders are canceled and refunded the moment the customer asks.', 'wpreturns' ),
									]
								),
							],
						],
						[
							'header' => [
								'heading'     => esc_html__( 'Where customers make requests', 'wpreturns' ),
								'description' => esc_html__( 'Logged-in customers use My Account; guests use the returns portal page (shortcode [ddwcwr_returns_portal] or the Returns portal block).', 'wpreturns' ),
							],
							'fields' => [
								[
									'type'        => 'text',
									'id'          => 'ddwcwr-my-account-endpoint',
									'label'       => esc_html__( 'My Account endpoint', 'wpreturns' ),
									'description' => esc_html__( 'URL part of the My Account returns page, e.g. "returns".', 'wpreturns' ),
									'value'       => $c['my_account_endpoint'],
								],
								[
									'type'        => 'text',
									'id'          => 'ddwcwr-my-account-endpoint-title',
									'label'       => esc_html__( 'My Account menu label', 'wpreturns' ),
									'description' => esc_html__( 'The name of the returns page in the customer\'s My Account menu.', 'wpreturns' ),
									'value'       => $c['my_account_endpoint_title'],
								],
								[
									'type'        => 'select',
									'id'          => 'ddwcwr-portal-page-id',
									'label'       => esc_html__( 'Guest returns portal page', 'wpreturns' ),
									'description' => esc_html__( 'Created on activation. Emails link guests here.', 'wpreturns' ),
									'options'     => self::get_page_options(),
									'value'       => (string) $c['portal_page_id'],
								],
							],
						],
					];

				case 'policy':
					return [
						[
							'header' => [
								'heading'     => esc_html__( 'Return window', 'wpreturns' ),
								'description' => esc_html__( 'How long customers have to ask for a return, for every product.', 'wpreturns' ),
							],
							'fields' => [
								$number( 'ddwcwr-return-window-days', esc_html__( 'Days to request a return', 'wpreturns' ), esc_html__( 'Items outside the window can no longer be selected on the return form.', 'wpreturns' ), 'return_window_days' ),
								[
									'type'        => 'select',
									'id'          => 'ddwcwr-window-from',
									'label'       => esc_html__( 'Count days from', 'wpreturns' ),
									'description' => esc_html__( 'Completed works best when you complete orders on delivery.', 'wpreturns' ),
									'options'     => [
										'completed' => esc_html__( 'Order completed (delivered)', 'wpreturns' ),
										'created'   => esc_html__( 'Order placed', 'wpreturns' ),
									],
									'value'       => $c['window_from'],
									'default'     => 'completed',
								],
								[
									'type'              => 'categories',
									'id'                => 'ddwcwr-final-sale-categories',
									'name'              => '_ddwcwr_final_sale_categories[]',
									'label'             => esc_html__( 'Final-sale categories', 'wpreturns' ),
									'description'       => esc_html__( 'Products in these categories cannot be returned. Products also have their own "Final sale" box. The EU right of withdrawal is not affected.', 'wpreturns' ),
									'value'             => (array) $c['final_sale_categories'],
									'custom_attributes' => [ 'multiple' => 'multiple' ],
								],
								$checkbox( 'ddwcwr-require-note', esc_html__( 'Customer note', 'wpreturns' ), esc_html__( 'Require a note with every return', 'wpreturns' ), esc_html__( 'Customers must explain the problem in a few words before they can send the request.', 'wpreturns' ), 'require_note' ),
							],
						],
						[
							'header' => [
								'heading'     => esc_html__( 'Photos and videos', 'wpreturns' ),
								'description' => esc_html__( 'Files are stored privately and only shown to the customer and your team.', 'wpreturns' ),
							],
							'fields' => [
								$checkbox( 'ddwcwr-allow-uploads', esc_html__( 'Uploads', 'wpreturns' ), esc_html__( 'Let customers attach photos and videos', 'wpreturns' ), esc_html__( 'Reasons marked "Photo required" ask for at least one.', 'wpreturns' ), 'allow_uploads' ),
								$number( 'ddwcwr-max-files', esc_html__( 'Maximum files', 'wpreturns' ), esc_html__( 'How many photos or videos a customer can attach to one request.', 'wpreturns' ), 'max_files', 1 ),
								$number( 'ddwcwr-max-file-size', esc_html__( 'Maximum size per file (MB)', 'wpreturns' ), esc_html__( 'Larger files are refused with a message. Your server\'s upload limit still applies.', 'wpreturns' ), 'max_file_size', 1 ),
								self::multi_select( 'ddwcwr-allowed-file-types', esc_html__( 'Allowed file types', 'wpreturns' ), esc_html__( 'Only these file types can be uploaded with a request or a message.', 'wpreturns' ), DDWCWR_Upload_Helper::get_file_type_options(), $c['allowed_file_types'] ),
							],
						],
						[
							'header' => [
								'heading'     => esc_html__( 'Show the policy', 'wpreturns' ),
								'description' => esc_html__( 'Tell shoppers how returns work before they buy.', 'wpreturns' ),
							],
							'fields' => [
								$checkbox( 'ddwcwr-show-policy-product', esc_html__( 'Product page', 'wpreturns' ), esc_html__( 'Show the return window on product pages', 'wpreturns' ), esc_html__( 'Shoppers see it under the Add to cart button.', 'wpreturns' ), 'show_policy_product' ),
								$checkbox( 'ddwcwr-show-policy-checkout', esc_html__( 'Checkout', 'wpreturns' ), esc_html__( 'Show the policy and withdrawal notice above the Place order button', 'wpreturns' ), esc_html__( 'Works with classic and block checkout.', 'wpreturns' ), 'show_policy_checkout' ),
								[
									'type'        => 'text',
									'id'          => 'ddwcwr-policy-text',
									'label'       => esc_html__( 'Policy line', 'wpreturns' ),
									'description' => esc_html__( 'Leave empty to build it from your return window, as shown. Your own text can use {days} for the return window.', 'wpreturns' ),
									'value'       => $c['policy_text'],
									'placeholder' => ddwcwr_get_policy_line( $c['return_window_days'] ),
								],
								[
									'type'        => 'select',
									'id'          => 'ddwcwr-policy-page-id',
									'label'       => esc_html__( 'Return policy page', 'wpreturns' ),
									'description' => esc_html__( 'Linked from the product page and checkout.', 'wpreturns' ),
									'options'     => self::get_page_options(),
									'value'       => (string) $c['policy_page_id'],
								],
							],
						],
					];

				case 'reasons':
					return [
						[
							'header'            => [
								'heading'     => esc_html__( 'Return reasons', 'wpreturns' ),
								'description' => esc_html__( 'Customers pick one reason per item. Add as many as you need, drag to reorder, and ask for a photo where it helps.', 'wpreturns' ),
							],
							'after_header_html' => self::get_reasons_html( $c['reasons'] ),
						],
					];

				case 'statuses':
					return [
						[
							'header'            => [
								'heading'     => esc_html__( 'Statuses', 'wpreturns' ),
								'description' => esc_html__( 'Rename, recolor and add your own statuses, such as "Inspecting" or "Sent to repair". Customers get an email when a request enters a status with "Notify" on.', 'wpreturns' ),
							],
							'after_header_html' => self::get_statuses_html( $c['statuses'] ),
						],
						self::pro_section(
							'team',
							esc_html__( 'Permissions', 'wpreturns' ),
							esc_html__( 'Choose who on your team can refund and close requests.', 'wpreturns' ),
							[
								'heading'       => esc_html__( 'Let your whole team work on returns', 'wpreturns' ),
								'description'   => esc_html__( 'Give support staff access to requests without giving them the power to refund, and share the work.', 'wpreturns' ),
								'list_features' => [
									esc_html__( 'Separate permissions to manage requests, refund, change settings and see reports', 'wpreturns' ),
									esc_html__( 'Statuses only refund-permitted staff can set', 'wpreturns' ),
									esc_html__( 'Assign requests to a team member and filter by assignee', 'wpreturns' ),
								],
							]
						),
					];

				case 'resolutions':
					return [
						[
							'header' => [
								'heading'     => esc_html__( 'Refunds and stock', 'wpreturns' ),
								'description' => esc_html__( 'What happens when you refund a return and when the items come back.', 'wpreturns' ),
							],
							'fields' => [
								$checkbox( 'ddwcwr-refund-via-gateway', esc_html__( 'Gateway refunds', 'wpreturns' ), esc_html__( 'Refund through the payment gateway by default', 'wpreturns' ), esc_html__( 'Gateways without refund support always record a manual refund. You can choose per request.', 'wpreturns' ), 'refund_via_gateway' ),
								$checkbox( 'ddwcwr-restock-on-receive', esc_html__( 'Restock', 'wpreturns' ), esc_html__( 'Tick "Put items back in stock" when marking a return received', 'wpreturns' ), esc_html__( 'Damaged and defective items are never restocked.', 'wpreturns' ), 'restock_on_receive' ),
								self::locked(
									[
										'type'        => 'number',
										'id'          => 'return-shipping-fee',
										'label'       => esc_html__( 'Return shipping fee', 'wpreturns' ),
										'description' => esc_html__( 'Deducted from refunds, with its own amount for store credit and exchanges. Never charged for store-fault reasons or EU withdrawals.', 'wpreturns' ),
										'custom_attributes' => [
											'min'  => 0,
											'step' => 0.01,
										],
									]
								),
							],
						],
						self::pro_section(
							'store-credit',
							esc_html__( 'Store credit', 'wpreturns' ),
							esc_html__( 'Credit keeps the revenue in your store.', 'wpreturns' ),
							[
								'heading'       => esc_html__( 'Turn refunds into repeat sales', 'wpreturns' ),
								'description'   => esc_html__( 'Most customers take store credit when it is offered first and comes with a small bonus. That money stays in your shop.', 'wpreturns' ),
								'list_features' => [
									esc_html__( 'Store credit as a single-use coupon or DevDiggers Wallet balance', 'wpreturns' ),
									esc_html__( 'Bonus credit, e.g. 10% extra on top of the item value', 'wpreturns' ),
									esc_html__( 'Credit shown before refund on the return form', 'wpreturns' ),
									esc_html__( 'Coupon expiry and refunds to the wallet', 'wpreturns' ),
								],
							]
						),
						self::pro_section(
							'exchanges',
							esc_html__( 'Exchanges', 'wpreturns' ),
							esc_html__( 'Swap instead of refund.', 'wpreturns' ),
							[
								'heading'       => esc_html__( 'Exchanges that keep the sale', 'wpreturns' ),
								'description'   => esc_html__( 'Customers pick another size, color or any product on the return form. The exchange order is created for you with the credit and the price difference on it.', 'wpreturns' ),
								'list_features' => [
									esc_html__( 'Exchange for another variation of the same product', 'wpreturns' ),
									esc_html__( 'Exchange for any product in the store, with live search', 'wpreturns' ),
									esc_html__( 'Higher price paid through a payment link, lower price refunded', 'wpreturns' ),
									esc_html__( 'Keep-it refunds and repair or replacement for warranty claims', 'wpreturns' ),
								],
							]
						),
					];

				case 'withdrawal':
					$countries = WC()->countries->get_countries();

					return [
						[
							'header' => [
								'heading'     => esc_html__( 'EU withdrawal button', 'wpreturns' ),
								'description' => esc_html__( 'Directive (EU) 2023/2673 (Art. 11a of Directive 2011/83/EU, in force since 19 June 2026): a "Withdraw from contract here" function, a statement form, a "Confirm withdrawal" button and an acknowledgement on a durable medium.', 'wpreturns' ),
							],
							'fields' => [
								$checkbox( 'ddwcwr-withdrawal-enabled', esc_html__( 'Withdrawal function', 'wpreturns' ), esc_html__( 'Offer the online withdrawal function', 'wpreturns' ), esc_html__( 'On by default. Required when you sell to consumers in the EU.', 'wpreturns' ), 'withdrawal_enabled' ),
								[
									'type'        => 'select',
									'id'          => 'ddwcwr-withdrawal-scope',
									'label'       => esc_html__( 'Offer it for orders from', 'wpreturns' ),
									'description' => esc_html__( 'Based on the billing country of the order.', 'wpreturns' ),
									'options'     => [
										'eu'        => esc_html__( 'EU and EEA countries', 'wpreturns' ),
										'all'       => esc_html__( 'All countries', 'wpreturns' ),
										'countries' => esc_html__( 'Countries I choose', 'wpreturns' ),
									],
									'value'       => $c['withdrawal_scope'],
									'default'     => 'eu',
								],
								self::multi_select( 'ddwcwr-withdrawal-countries', esc_html__( 'Countries', 'wpreturns' ), esc_html__( 'Used when "Countries I choose" is selected.', 'wpreturns' ), $countries, $c['withdrawal_countries'] ),
								$number( 'ddwcwr-withdrawal-days', esc_html__( 'Withdrawal period (days)', 'wpreturns' ), esc_html__( 'At least 14. Customers can always withdraw before the goods arrive.', 'wpreturns' ), 'withdrawal_days', 14 ),
								[
									'type'        => 'select',
									'id'          => 'ddwcwr-withdrawal-from',
									'label'       => esc_html__( 'Period starts', 'wpreturns' ),
									'description' => esc_html__( 'Under EU law the 14 days start when the customer receives the goods, so "completed" is right if you complete orders on delivery.', 'wpreturns' ),
									'options'     => [
										'completed' => esc_html__( 'When the order is completed (delivered)', 'wpreturns' ),
										'created'   => esc_html__( 'When the order is placed', 'wpreturns' ),
									],
									'value'       => $c['withdrawal_from'],
									'default'     => 'completed',
								],
								[
									'type'        => 'select',
									'id'          => 'ddwcwr-withdrawal-page-id',
									'label'       => esc_html__( 'Withdrawal page', 'wpreturns' ),
									'description' => esc_html__( 'Holds [ddwcwr_withdrawal_form]. Created on activation.', 'wpreturns' ),
									'options'     => self::get_page_options(),
									'value'       => (string) $c['withdrawal_page_id'],
								],
								$checkbox( 'ddwcwr-withdrawal-footer-link', esc_html__( 'Footer link', 'wpreturns' ), esc_html__( 'Show "Withdraw from contract here" in the site footer', 'wpreturns' ), esc_html__( 'The function must be easy to find for the whole withdrawal period. It also shows on eligible orders in My Account.', 'wpreturns' ), 'withdrawal_footer_link' ),
								$checkbox( 'ddwcwr-withdrawal-in-emails', esc_html__( 'Order emails', 'wpreturns' ), esc_html__( 'Add the withdrawal link to customer order emails', 'wpreturns' ), esc_html__( 'Adds a "Withdraw from contract here" link to the processing and completed order emails.', 'wpreturns' ), 'withdrawal_in_emails' ),
								$checkbox( 'ddwcwr-withdrawal-model-form', esc_html__( 'Model form', 'wpreturns' ), esc_html__( 'Show the model withdrawal form on the withdrawal page', 'wpreturns' ), esc_html__( 'The Annex I(B) form, addressed to your store name, address and email from WooCommerce > Settings. For customers who prefer to write.', 'wpreturns' ), 'withdrawal_model_form' ),
								$checkbox( 'ddwcwr-withdrawal-digital-consent', esc_html__( 'Digital content', 'wpreturns' ), esc_html__( 'Ask for consent at checkout when the cart has downloadable products', 'wpreturns' ), esc_html__( 'A required checkbox (Art. 16(m)): the customer agrees to immediate supply and loses the right of withdrawal for those downloads. The order emails confirm it. Classic checkout, and block checkout on WooCommerce versions with conditional checkout fields.', 'wpreturns' ), 'withdrawal_digital_consent' ),
								[
									'type'        => 'text',
									'id'          => 'ddwcwr-withdrawal-digital-consent-text',
									'label'       => esc_html__( 'Consent text', 'wpreturns' ),
									'description' => esc_html__( 'Label of the checkout checkbox. Leave empty for the default.', 'wpreturns' ),
									'placeholder' => ddwcwr_get_digital_consent_text(),
									'value'       => $c['withdrawal_digital_consent_text'],
								],
								$number( 'ddwcwr-withdrawal-rate-limit', esc_html__( 'Attempts per hour', 'wpreturns' ), esc_html__( 'Per visitor, for the withdrawal form and the guest order lookup. Stops people using the form to send email to strangers.', 'wpreturns' ), 'withdrawal_rate_limit', 1 ),
								[
									'type'              => 'categories',
									'id'                => 'ddwcwr-withdrawal-excluded-categories',
									'name'              => '_ddwcwr_withdrawal_excluded_categories[]',
									'label'             => esc_html__( 'Exempt categories', 'wpreturns' ),
									'description'       => esc_html__( 'Goods exempt under Art. 16 (made to order, perishable, sealed hygiene goods…). Products also have their own "Exempt from withdrawal" box.', 'wpreturns' ),
									'value'             => (array) $c['withdrawal_excluded_categories'],
									'custom_attributes' => [ 'multiple' => 'multiple' ],
								],
							],
						],
					];

				case 'warranty':
					return [
						self::pro_section(
							'warranty',
							esc_html__( 'Warranty', 'wpreturns' ),
							esc_html__( 'Warranty terms per product, claims from My Account and the returns portal, and extended warranties sold at checkout.', 'wpreturns' ),
							[
								'heading'       => esc_html__( 'Handle warranty claims and sell extended warranties', 'wpreturns' ),
								'description'   => esc_html__( 'Customers file a claim long after the return window has closed, and the plugin checks the warranty for you. Extended warranties add revenue to every order.', 'wpreturns' ),
								'list_features' => [
									esc_html__( 'Warranty per product or variation: none, limited or lifetime, with bulk edit', 'wpreturns' ),
									esc_html__( 'Warranty shown on product pages, even when the product is out of stock', 'wpreturns' ),
									esc_html__( 'Warranty claims checked against the purchase date, resolved by repair or replacement', 'wpreturns' ),
									esc_html__( 'Paid extended warranties on the product page, in classic and block checkout', 'wpreturns' ),
									esc_html__( 'Terms saved with each order, so later changes never affect past purchases', 'wpreturns' ),
								],
							]
						),
					];

				case 'labels':
					return [
						self::pro_section(
							'labels',
							esc_html__( 'Return shipping labels', 'wpreturns' ),
							esc_html__( 'You can already upload a label on any request and the customer gets it by email.', 'wpreturns' ),
							[
								'heading'       => esc_html__( 'Buy prepaid return labels in one click', 'wpreturns' ),
								'description'   => esc_html__( 'Connect your own EasyPost or Shippo account and buy the return label from the request screen, or automatically the moment you approve a return.', 'wpreturns' ),
								'list_features' => [
									esc_html__( 'EasyPost and Shippo return labels at your own rates', 'wpreturns' ),
									esc_html__( 'Automatic labels on approval, emailed with the tracking number', 'wpreturns' ),
									esc_html__( 'One label per request, however many items come back', 'wpreturns' ),
									esc_html__( 'Printable return slip with a QR code for your warehouse', 'wpreturns' ),
								],
							]
						),
					];

				case 'fraud':
					return [
						self::pro_section(
							'fraud',
							esc_html__( 'Fraud protection', 'wpreturns' ),
							esc_html__( 'Signals on every request so you can stop return abuse without hurting honest customers.', 'wpreturns' ),
							[
								'heading'       => esc_html__( 'Stop serial returners and wardrobing', 'wpreturns' ),
								'description'   => esc_html__( 'Every request gets a risk score. Flagged customers wait for a person, and blocked ones are asked to contact you. EU withdrawals are never refused, only flagged.', 'wpreturns' ),
								'list_features' => [
									esc_html__( 'Return rate per customer, by number of orders and by spend', 'wpreturns' ),
									esc_html__( 'Serial returner, very quick return, late-in-window and high-value flags', 'wpreturns' ),
									esc_html__( 'Hold or automatically reject serial returners', 'wpreturns' ),
									esc_html__( 'Blocklist by email address or @domain', 'wpreturns' ),
								],
							]
						),
					];

				case 'automation':
					return [
						self::pro_section(
							'automation',
							esc_html__( 'Automation', 'wpreturns' ),
							esc_html__( 'Let returns run themselves.', 'wpreturns' ),
							[
								'heading'       => esc_html__( 'Spend less time chasing returns', 'wpreturns' ),
								'description'   => esc_html__( 'Daily jobs remind customers, close stale requests and refund received returns for you. They only act on requests submitted after you switch them on.', 'wpreturns' ),
								'list_features' => [
									esc_html__( 'Reminder when a label was sent but nothing was shipped', 'wpreturns' ),
									esc_html__( 'Ship-by reminder before a request is closed', 'wpreturns' ),
									esc_html__( 'Close stale requests after a number of days', 'wpreturns' ),
									esc_html__( 'Refund, credit or exchange automatically a few hours after the parcel arrives', 'wpreturns' ),
									esc_html__( 'REST API and WooCommerce webhooks for your other tools', 'wpreturns' ),
								],
							]
						),
					];

				case 'emails':
					return [
						[
							'header'            => [
								'heading'     => esc_html__( 'Emails', 'wpreturns' ),
								'description' => esc_html__( 'Every WPReturns email is a WooCommerce email: edit the subject, heading, text and recipients, or turn it off, in WooCommerce > Settings > Emails.', 'wpreturns' ),
							],
							'after_header_html' => self::get_emails_html(),
						],
					];

				case 'appearance':
					return [
						self::pro_section(
							'appearance',
							esc_html__( 'Customer screens', 'wpreturns' ),
							esc_html__( 'The returns pages use a clean default design that sits well in most themes.', 'wpreturns' ),
							[
								'heading'       => esc_html__( 'Make the returns pages look like your store', 'wpreturns' ),
								'description'   => esc_html__( 'Match My Account > Returns, the return form, the guest portal and the withdrawal pages to your brand without writing CSS.', 'wpreturns' ),
								'list_features' => [
									esc_html__( 'Your accent and button text colors', 'wpreturns' ),
									esc_html__( 'Card background for dark themes', 'wpreturns' ),
									esc_html__( 'Sharp, soft or round corners', 'wpreturns' ),
								],
							]
						),
					];

				case 'advanced':
					return [
						[
							'header' => [
								'heading'     => esc_html__( 'Advanced', 'wpreturns' ),
								'description' => esc_html__( 'Settings you rarely need to change.', 'wpreturns' ),
							],
							'fields' => [
								$checkbox( 'ddwcwr-delete-data-on-uninstall', esc_html__( 'Uninstall', 'wpreturns' ), esc_html__( 'Delete all WPReturns data when the plugin is deleted', 'wpreturns' ), esc_html__( 'Requests, settings and uploaded files. Leave off to keep your records.', 'wpreturns' ), 'delete_data_on_uninstall' ),
							],
						],
					];
			}

			return [];
		}

		/**
		 * Reasons repeater.
		 *
		 * @param array $reasons Reasons.
		 * @return string
		 */
		protected static function get_reasons_html( $reasons ) {
			$row = function ( $index, $reason ) {
				return '<tr class="ddwcwr-repeater-row">' . self::get_drag_handle_html()
					. '<td><input type="hidden" name="_ddwcwr_reasons[' . esc_attr( $index ) . '][slug]" value="' . esc_attr( $reason['slug'] ) . '" /><input type="text" class="regular-text" name="_ddwcwr_reasons[' . esc_attr( $index ) . '][label]" value="' . esc_attr( $reason['label'] ) . '" aria-label="' . esc_attr__( 'Reason', 'wpreturns' ) . '" /></td>'
					. '<td class="ddwcwr-col-toggle"><input type="checkbox" name="_ddwcwr_reasons[' . esc_attr( $index ) . '][require_photo]" value="yes" ' . checked( ! empty( $reason['require_photo'] ), true, false ) . ' aria-label="' . esc_attr__( 'Photo required', 'wpreturns' ) . '" /></td>'
					. '<td class="ddwcwr-col-toggle"><input type="checkbox" name="_ddwcwr_reasons[' . esc_attr( $index ) . '][status]" value="active" ' . checked( empty( $reason['status'] ) || 'active' === $reason['status'], true, false ) . ' aria-label="' . esc_attr__( 'Active', 'wpreturns' ) . '" /></td>'
					. '<td class="ddwcwr-col-actions">' . self::get_remove_button_html() . '</td>'
					. '</tr>';
			};

			$html  = '<div class="ddfw-table-wrapper"><table class="widefat fixed ddfw-table ddwcwr-settings-table ddwcwr-repeater"><thead><tr>';
			$html .= '<th class="ddwcwr-col-drag"><span class="screen-reader-text">' . esc_html__( 'Order', 'wpreturns' ) . '</span></th><th>' . esc_html__( 'Reason', 'wpreturns' ) . '</th><th class="ddwcwr-col-toggle">' . esc_html__( 'Photo required', 'wpreturns' ) . '</th><th class="ddwcwr-col-toggle">' . esc_html__( 'Active', 'wpreturns' ) . '</th><th class="ddwcwr-col-actions"><span class="screen-reader-text">' . esc_html__( 'Actions', 'wpreturns' ) . '</span></th>';
			$html .= '</tr></thead><tbody>';

			foreach ( array_values( (array) $reasons ) as $index => $reason ) {
				$html .= $row( $index, $reason );
			}

			$html .= '</tbody></table></div>';
			$html .= '<script type="text/html" id="ddwcwr-repeater-template">' . $row(
				'__i__',
				[
					'slug'   => '',
					'label'  => '',
					'status' => 'active',
				]
			) . '</script>';
			$html .= '<p><button type="button" class="button ddwcwr-repeater-add">' . esc_html__( 'Add reason', 'wpreturns' ) . '</button></p>';

			return $html;
		}

		/**
		 * Statuses repeater. Built-in statuses can be renamed but not removed.
		 *
		 * @param array $statuses Statuses.
		 * @return string
		 */
		protected static function get_statuses_html( $statuses ) {
			$row = function ( $index, $status ) {
				$actions = ! empty( $status['core'] )
					? '<span class="ddwcwr-icon-button ddwcwr-icon-button-muted" title="' . esc_attr__( 'Built-in statuses cannot be removed', 'wpreturns' ) . '"><span class="dashicons dashicons-lock" aria-hidden="true"></span><span class="screen-reader-text">' . esc_html__( 'Built in', 'wpreturns' ) . '</span></span>'
					: self::get_remove_button_html();

				return '<tr class="ddwcwr-repeater-row">' . self::get_drag_handle_html()
					. '<td><input type="hidden" name="_ddwcwr_statuses[' . esc_attr( $index ) . '][slug]" value="' . esc_attr( $status['slug'] ) . '" /><input type="text" class="regular-text" name="_ddwcwr_statuses[' . esc_attr( $index ) . '][label]" value="' . esc_attr( $status['label'] ) . '" aria-label="' . esc_attr__( 'Status name', 'wpreturns' ) . '" />' . ( $status['slug'] ? '<span class="ddwcwr-list-meta">' . esc_html( $status['slug'] ) . '</span>' : '' ) . '</td>'
					. '<td class="ddwcwr-col-color"><input type="colorpicker" class="ddfw-color-picker" name="_ddwcwr_statuses[' . esc_attr( $index ) . '][color]" value="' . esc_attr( $status['color'] ) . '" data-default-value="' . esc_attr( $status['color'] ) . '" aria-label="' . esc_attr__( 'Color', 'wpreturns' ) . '" /></td>'
					. '<td class="ddwcwr-col-toggle"><input type="checkbox" name="_ddwcwr_statuses[' . esc_attr( $index ) . '][notify]" value="yes" ' . checked( ! empty( $status['notify'] ), true, false ) . ' aria-label="' . esc_attr__( 'Notify customer', 'wpreturns' ) . '" /></td>'
					. '<td class="ddwcwr-col-actions">' . $actions . '</td>'
					. '</tr>';
			};

			$html  = '<div class="ddfw-table-wrapper"><table class="widefat fixed ddfw-table ddwcwr-settings-table ddwcwr-repeater"><thead><tr>';
			$html .= '<th class="ddwcwr-col-drag"><span class="screen-reader-text">' . esc_html__( 'Order', 'wpreturns' ) . '</span></th><th>' . esc_html__( 'Status', 'wpreturns' ) . '</th><th class="ddwcwr-col-color">' . esc_html__( 'Color', 'wpreturns' ) . '</th><th class="ddwcwr-col-toggle">' . esc_html__( 'Notify customer', 'wpreturns' ) . '</th><th class="ddwcwr-col-actions"><span class="screen-reader-text">' . esc_html__( 'Actions', 'wpreturns' ) . '</span></th>';
			$html .= '</tr></thead><tbody>';

			foreach ( array_values( (array) $statuses ) as $index => $status ) {
				$html .= $row( $index, $status );
			}

			$html .= '</tbody></table></div>';
			$html .= '<script type="text/html" id="ddwcwr-repeater-template">' . $row(
				'__i__',
				[
					'slug'   => '',
					'label'  => '',
					'color'  => '#6b7280',
					'notify' => 'yes',
				]
			) . '</script>';
			$html .= '<p><button type="button" class="button ddwcwr-repeater-add">' . esc_html__( 'Add status', 'wpreturns' ) . '</button></p>';

			return $html;
		}

		/**
		 * Drag handle cell for a repeater row (arrow keys move the row too).
		 *
		 * @return string
		 */
		protected static function get_drag_handle_html() {
			return '<td class="ddwcwr-col-drag"><span class="ddwcwr-drag-handle" role="button" tabindex="0" aria-label="' . esc_attr__( 'Reorder. Drag, or use the up and down arrow keys.', 'wpreturns' ) . '" title="' . esc_attr__( 'Drag to reorder', 'wpreturns' ) . '"><svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false"><circle cx="9" cy="5" r="1.6"/><circle cx="15" cy="5" r="1.6"/><circle cx="9" cy="12" r="1.6"/><circle cx="15" cy="12" r="1.6"/><circle cx="9" cy="19" r="1.6"/><circle cx="15" cy="19" r="1.6"/></svg></span></td>';
		}

		/**
		 * Remove button for a repeater row.
		 *
		 * @return string
		 */
		protected static function get_remove_button_html() {
			return '<button type="button" class="ddwcwr-icon-button ddwcwr-icon-button-danger ddwcwr-repeater-remove" aria-label="' . esc_attr__( 'Remove', 'wpreturns' ) . '" title="' . esc_attr__( 'Remove', 'wpreturns' ) . '"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6M10 11v6M14 11v6"/></svg></button>';
		}

		/**
		 * Email list with links to WooCommerce email settings.
		 *
		 * @return string
		 */
		protected static function get_emails_html() {
			$html = '<div class="ddfw-table-wrapper"><table class="widefat fixed striped ddfw-table ddwcwr-settings-table ddwcwr-emails-table"><thead><tr><th>' . esc_html__( 'Email', 'wpreturns' ) . '</th><th class="ddwcwr-col-recipient">' . esc_html__( 'Sent to', 'wpreturns' ) . '</th><th class="ddwcwr-col-toggle">' . esc_html__( 'Send', 'wpreturns' ) . '</th><th class="ddwcwr-col-actions"><span class="screen-reader-text">' . esc_html__( 'Actions', 'wpreturns' ) . '</span></th></tr></thead><tbody>';

			foreach ( WC()->mailer()->get_emails() as $email_key => $email ) {
				if ( 0 !== strpos( $email->id, 'ddwcwr_' ) ) {
					continue;
				}

				$html .= '<tr><td><strong>' . esc_html( $email->get_title() ) . '</strong><span class="ddwcwr-list-meta">' . esc_html( $email->get_description() ) . '</span></td>';
				$html .= '<td class="ddwcwr-col-recipient">' . ( $email->is_customer_email() ? esc_html__( 'Customer', 'wpreturns' ) : esc_html( $email->get_recipient() ) ) . '</td>';
				$html .= '<td class="ddwcwr-col-toggle"><label class="ddwcwr-email-toggle"><input type="checkbox" data-email="' . esc_attr( $email->id ) . '" ' . checked( $email->is_enabled(), true, false ) . ' aria-label="' . esc_attr( sprintf( /* translators: %s: email title */ __( 'Send "%s"', 'wpreturns' ), $email->get_title() ) ) . '" /></label></td>';
				$html .= '<td class="ddwcwr-col-actions"><a class="button button-small" href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=email&section=' . strtolower( $email_key ) ) ) . '">' . esc_html__( 'Manage', 'wpreturns' ) . '</a></td></tr>';
			}

			return $html . '</tbody></table></div>';
		}
	}
}
