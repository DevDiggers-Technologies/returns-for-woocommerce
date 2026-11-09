<?php
/**
 * Common callbacks (admin and front).
 *
 * @author DevDiggers
 * @package DevDiggers Returns
 * @version 1.0.0
 */

namespace DDWCWPReturns\Includes\Common;

use DDWCWPReturns\Helper\Request\DDWCWR_Request_Helper;
use DDWCWPReturns\Helper\Resolution\DDWCWR_Resolution_Helper;
use DDWCWPReturns\Helper\Upload\DDWCWR_Upload_Helper;

defined( 'ABSPATH' ) || exit();

if ( ! class_exists( 'DDWCWR_Common_Functions' ) ) {
	/**
	 * Common functions class
	 */
	class DDWCWR_Common_Functions {
		/**
		 * Configuration Variable
		 *
		 * @var array
		 */
		protected $ddwcwr_configuration;

		/**
		 * Request helper.
		 *
		 * @var DDWCWR_Request_Helper
		 */
		protected $request_helper;

		/**
		 * Construct
		 *
		 * @param array $ddwcwr_configuration Configuration.
		 */
		public function __construct( $ddwcwr_configuration ) {
			$this->ddwcwr_configuration = $ddwcwr_configuration;
			$this->request_helper       = new DDWCWR_Request_Helper();
		}

		/**
		 * Give our emails a sample request in the WooCommerce email preview.
		 *
		 * @param \WC_Email $email Email.
		 * @return \WC_Email
		 */
		public function ddwcwr_prepare_email_preview( $email ) {
			return $email instanceof \DDWCWPReturns\Includes\Emails\DDWCWR_Email_Base ? $email->prepare_preview() : $email;
		}

		/**
		 * Register email classes.
		 *
		 * @param array $emails Emails.
		 * @return array
		 */
		public function ddwcwr_add_email_classes( $emails ) {
			$classes = [
				'DDWCWR_Email_Customer_Request_Received',
				'DDWCWR_Email_Customer_Request_Approved',
				'DDWCWR_Email_Customer_Request_Rejected',
				'DDWCWR_Email_Customer_Status_Updated',
				'DDWCWR_Email_Customer_New_Message',
				'DDWCWR_Email_Customer_Label_Ready',
				'DDWCWR_Email_Customer_Resolution',
				'DDWCWR_Email_Customer_Withdrawal_Acknowledgement',
				'DDWCWR_Email_Admin_New_Request',
				'DDWCWR_Email_Admin_Customer_Reply',
			];

			foreach ( $classes as $class ) {
				$fqcn             = '\\DDWCWPReturns\\Includes\\Emails\\' . $class;
				$emails[ $class ] = new $fqcn();
			}

			return $emails;
		}

		/**
		 * Actions that send emails (loaded through the WooCommerce mailer, deferred when WooCommerce defers).
		 *
		 * @param array $actions Actions.
		 * @return array
		 */
		public function ddwcwr_add_email_actions( $actions ) {
			return array_merge(
				$actions,
				[
					'ddwcwr_request_created',
					'ddwcwr_request_status_changed',
					'ddwcwr_request_message_added',
					'ddwcwr_resolution_processed',
					'ddwcwr_label_ready',
				]
			);
		}

		/**
		 * Side effects of status changes.
		 *
		 * @param int    $request_id Request ID.
		 * @param string $old Old status.
		 * @param string $status New status.
		 * @param array  $request Request.
		 * @return void
		 */
		public function ddwcwr_on_status_changed( $request_id, $old, $status, $request ) {
			// An approved cancellation needs nothing back from the customer: cancel and refund now.
			if ( 'approved' === $status && 'cancellation' === $request['type'] ) {
				$result = ( new DDWCWR_Resolution_Helper() )->process( $request_id, [ 'author_id' => get_current_user_id() ] );

				if ( is_wp_error( $result ) ) {
					$this->request_helper->add_message( $request_id, $result->get_error_message(), 'note', 'system' );
				}
			}
		}

		/**
		 * Serve a protected file to staff, the owner, or a holder of the request key.
		 *
		 * @return void
		 */
		public function ddwcwr_serve_file() {
			// Links shown on the site carry a nonce. E-mailed links (the return label) cannot, because nonces
			// expire and are tied to a session, so they carry the request's secret key instead.
			// phpcs:disable WordPress.Security.NonceVerification.Recommended
			$access_key = isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : '';
			// phpcs:enable

			if ( '' === $access_key && ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'ddwcwr_file' ) ) ) {
				wp_die( esc_html__( 'This link has expired. Please reload the page and try again.', 'devdiggers-returns-for-woocommerce' ), '', [ 'response' => 403 ] );
			}

			$request_id = isset( $_GET['request'] ) ? absint( $_GET['request'] ) : 0;
			$file_key   = isset( $_GET['file'] ) ? sanitize_text_field( wp_unslash( $_GET['file'] ) ) : '';

			$request = $this->request_helper->get_request( $request_id );

			if ( ! $this->request_helper->can_view( $request, $access_key ) ) {
				wp_die( esc_html__( 'You are not allowed to view this file.', 'devdiggers-returns-for-woocommerce' ), '', [ 'response' => 403 ] );
			}

			$file = $this->request_helper->find_attachment( $request_id, $file_key );

			// Internal note attachments are staff only.
			if ( ! $file || ( isset( $file['kind'] ) && 'note' === $file['kind'] && ! ddwcwr_current_user_can() ) ) {
				wp_die( esc_html__( 'File not found.', 'devdiggers-returns-for-woocommerce' ), '', [ 'response' => 404 ] );
			}

			( new DDWCWR_Upload_Helper() )->serve( $file['key'], $file['name'] );
		}

		/**
		 * Register the personal data exporter.
		 *
		 * @param array $exporters Exporters.
		 * @return array
		 */
		public function ddwcwr_register_exporter( $exporters ) {
			$exporters['ddwcwr-requests'] = [
				'exporter_friendly_name' => __( 'Return and withdrawal requests', 'devdiggers-returns-for-woocommerce' ),
				'callback'               => [ $this, 'ddwcwr_export_personal_data' ],
			];

			return $exporters;
		}

		/**
		 * Register the personal data eraser.
		 *
		 * @param array $erasers Erasers.
		 * @return array
		 */
		public function ddwcwr_register_eraser( $erasers ) {
			$erasers['ddwcwr-requests'] = [
				'eraser_friendly_name' => __( 'Return and withdrawal requests', 'devdiggers-returns-for-woocommerce' ),
				'callback'             => [ $this, 'ddwcwr_erase_personal_data' ],
			];

			return $erasers;
		}

		/**
		 * Requests of an email address (page of 20).
		 *
		 * @param string $email Email.
		 * @param int    $page Page.
		 * @return array
		 */
		protected function ddwcwr_get_requests_for_email( $email, $page ) {
			$user = get_user_by( 'email', $email );
			$args = [
				'limit'  => 20,
				'offset' => ( max( 1, $page ) - 1 ) * 20,
				'order'  => 'ASC',
			];

			if ( $user ) {
				$by_user = $this->request_helper->get_requests( $args + [ 'customer_id' => $user->ID ] );
				$by_mail = $this->request_helper->get_requests(
					$args + [
						'customer_email' => $email,
						'customer_id'    => 0,
					]
				);

				return array_merge( $by_user, $by_mail );
			}

			return $this->request_helper->get_requests( $args + [ 'customer_email' => $email ] );
		}

		/**
		 * Export personal data.
		 *
		 * @param string $email Email.
		 * @param int    $page Page.
		 * @return array
		 */
		public function ddwcwr_export_personal_data( $email, $page = 1 ) {
			$requests = $this->ddwcwr_get_requests_for_email( $email, $page );
			$data     = [];
			$types    = ddwcwr_get_request_types();

			foreach ( $requests as $request ) {
				$items    = $this->request_helper->get_request_items( $request['id'] );
				$messages = $this->request_helper->get_messages( $request['id'], [ 'message' ] );

				$data[] = [
					'group_id'    => 'ddwcwr-requests',
					'group_label' => __( 'Return and withdrawal requests', 'devdiggers-returns-for-woocommerce' ),
					'item_id'     => 'ddwcwr-request-' . $request['id'],
					'data'        => [
						[
							'name'  => __( 'Request', 'devdiggers-returns-for-woocommerce' ),
							'value' => $request['number'],
						],
						[
							'name'  => __( 'Order', 'devdiggers-returns-for-woocommerce' ),
							'value' => $request['order_id'],
						],
						[
							'name'  => __( 'Type', 'devdiggers-returns-for-woocommerce' ),
							'value' => isset( $types[ $request['type'] ] ) ? $types[ $request['type'] ] : $request['type'],
						],
						[
							'name'  => __( 'Status', 'devdiggers-returns-for-woocommerce' ),
							'value' => ddwcwr_get_status_label( $request['status'] ),
						],
						[
							'name'  => __( 'Name', 'devdiggers-returns-for-woocommerce' ),
							'value' => $request['customer_name'],
						],
						[
							'name'  => __( 'Email', 'devdiggers-returns-for-woocommerce' ),
							'value' => $request['customer_email'],
						],
						[
							'name'  => __( 'Submitted', 'devdiggers-returns-for-woocommerce' ),
							'value' => $request['created'],
						],
						[
							'name'  => __( 'Items', 'devdiggers-returns-for-woocommerce' ),
							'value' => implode(
								', ',
								array_map(
									function ( $item ) {
										return $item['name'] . ' × ' . $item['qty'] . ' (' . ddwcwr_get_reason_label( $item['reason'] ) . ')';
									},
									$items
								)
							),
						],
						[
							'name'  => __( 'Messages', 'devdiggers-returns-for-woocommerce' ),
							'value' => implode( "\n", wp_list_pluck( $messages, 'message' ) ),
						],
						[
							'name'  => __( 'Return tracking number', 'devdiggers-returns-for-woocommerce' ),
							'value' => $request['tracking_number'],
						],
					],
				];
			}

			return [
				'data' => $data,
				'done' => count( $requests ) < 20,
			];
		}

		/**
		 * Erase personal data: anonymize requests (withdrawal and refund records are kept as evidence), delete files.
		 *
		 * @param string $email Email.
		 * @return array
		 */
		public function ddwcwr_erase_personal_data( $email ) {
			global $wpdb;

			// Always page 1: anonymized rows no longer match the email.
			$requests = $this->ddwcwr_get_requests_for_email( $email, 1 );
			$upload   = new DDWCWR_Upload_Helper();
			$removed  = false;

			foreach ( $requests as $request ) {
				$files = [];

				foreach ( $this->request_helper->get_messages( $request['id'], [ 'message', 'note' ] ) as $message ) {
					$files = array_merge( $files, $message['attachments'] );
				}

				$upload->delete_files( $files );

				// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->update(
					$wpdb->prefix . 'ddwcwr_messages',
					[
						'message'     => wp_privacy_anonymize_data( 'longtext' ),
						'attachments' => '[]',
					],
					[
						'request_id' => $request['id'],
						'kind'       => 'message',
					]
				);
				$wpdb->update( $wpdb->prefix . 'ddwcwr_messages', [ 'attachments' => '[]' ], [ 'request_id' => $request['id'] ] );
				$wpdb->update(
					$wpdb->prefix . 'ddwcwr_requests',
					[
						'customer_email'  => wp_privacy_anonymize_data( 'email' ),
						'customer_name'   => wp_privacy_anonymize_data( 'text' ),
						'customer_note'   => wp_privacy_anonymize_data( 'longtext' ),
						'tracking_number' => '',
						'ip_hash'         => '',
						'customer_id'     => 0,
					],
					[ 'id' => $request['id'] ]
				);
				// phpcs:enable

				$removed = true;
			}

			return [
				'items_removed'  => $removed,
				'items_retained' => false,
				'messages'       => $removed ? [ __( 'Return requests were anonymized; their order and refund records are kept for accounting.', 'devdiggers-returns-for-woocommerce' ) ] : [],
				'done'           => true,
			];
		}

		/**
		 * Suggested privacy policy text.
		 *
		 * @return void
		 */
		public function ddwcwr_add_privacy_policy_content() {
			if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
				return;
			}

			wp_add_privacy_policy_content(
				__( 'DevDiggers Returns', 'devdiggers-returns-for-woocommerce' ),
				'<p>' . esc_html__( 'When you request a return or an order cancellation, or withdraw from a contract, we store your name, email address, order number, the items and reasons you choose, your messages, any photos or videos you upload, and the tracking number of your return package. We use this to handle your request and keep a record of it. Photos and videos are stored privately and are only visible to you and our staff.', 'devdiggers-returns-for-woocommerce' ) . '</p>'
			);
		}

		/**
		 * Register the guest portal block (server rendered, no build step needed in the editor).
		 *
		 * @return void
		 */
		public function ddwcwr_register_blocks() {
			if ( ! function_exists( 'register_block_type' ) ) {
				return;
			}

			wp_register_script( 'ddwcwr-blocks', DDWCWR_PLUGIN_URL . 'assets/js/blocks.js', [ 'wp-blocks', 'wp-element', 'wp-i18n', 'wp-components', 'wp-server-side-render', 'wp-block-editor' ], filemtime( DDWCWR_PLUGIN_FILE . 'assets/js/blocks.js' ), true );
			wp_set_script_translations( 'ddwcwr-blocks', 'devdiggers-returns-for-woocommerce', DDWCWR_PLUGIN_FILE . 'i18n' );

			register_block_type(
				'ddwcwr/returns-portal',
				[
					'api_version'     => 3,
					'title'           => __( 'Returns portal', 'devdiggers-returns-for-woocommerce' ),
					'category'        => 'woocommerce',
					'editor_script'   => 'ddwcwr-blocks',
					'render_callback' => function () {
						return wp_kses( do_shortcode( '[ddwcwr_returns_portal]' ), ddwcwr_kses_allowed_html() );
					},
				]
			);

			register_block_type(
				'ddwcwr/withdrawal-form',
				[
					'api_version'     => 3,
					'title'           => __( 'Withdrawal form', 'devdiggers-returns-for-woocommerce' ),
					'category'        => 'woocommerce',
					'editor_script'   => 'ddwcwr-blocks',
					'render_callback' => function () {
						return wp_kses( do_shortcode( '[ddwcwr_withdrawal_form]' ), ddwcwr_kses_allowed_html() );
					},
				]
			);
		}

		/**
		 * Classic checkout: consent checkbox for digital content above the Place order button.
		 *
		 * @return void
		 */
		public function ddwcwr_digital_consent_field() {
			if ( ! ddwcwr_cart_needs_digital_consent() ) {
				return;
			}

			woocommerce_form_field(
				'ddwcwr_digital_consent',
				[
					'type'     => 'checkbox',
					'class'    => [ 'form-row', 'ddwcwr-digital-consent' ],
					'label'    => esc_html( ddwcwr_get_digital_consent_text() ),
					'required' => true,
				]
			);
		}

		/**
		 * Classic checkout: block the order until the consent box is ticked.
		 *
		 * @param array    $data Posted checkout data.
		 * @param WP_Error $errors Checkout errors.
		 * @return void
		 */
		public function ddwcwr_validate_digital_consent( $data, $errors ) {
			// WooCommerce verified this nonce before running the hook; checked again here before reading the post.
			if ( ! isset( $_POST['woocommerce-process-checkout-nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['woocommerce-process-checkout-nonce'] ) ), 'woocommerce-process_checkout' ) ) {
				return;
			}

			if ( ddwcwr_cart_needs_digital_consent() && empty( $_POST['ddwcwr_digital_consent'] ) ) {
				$errors->add( 'ddwcwr_digital_consent', esc_html__( 'Please agree to the immediate supply of the digital content to place your order.', 'devdiggers-returns-for-woocommerce' ) );
			}
		}

		/**
		 * Classic checkout: record when the customer consented.
		 *
		 * @param \WC_Order $order Order being created.
		 * @return void
		 */
		public function ddwcwr_save_digital_consent( $order ) {
			// WooCommerce verified this nonce before running the hook; checked again here before reading the post.
			if ( ! isset( $_POST['woocommerce-process-checkout-nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['woocommerce-process-checkout-nonce'] ) ), 'woocommerce-process_checkout' ) ) {
				return;
			}

			if ( ddwcwr_cart_needs_digital_consent() && ! empty( $_POST['ddwcwr_digital_consent'] ) ) {
				$order->update_meta_data( '_ddwcwr_digital_consent', time() );
			}
		}

		/**
		 * Block checkout: a required checkbox that only shows while the cart needs consent.
		 * Needs the conditional field rules of newer WooCommerce versions; older ones get the classic checkout only.
		 *
		 * @return void
		 */
		public function ddwcwr_register_block_digital_consent() {
			if ( ! function_exists( 'woocommerce_register_additional_checkout_field' ) || ! function_exists( 'woocommerce_store_api_register_endpoint_data' ) || ! class_exists( 'Automattic\WooCommerce\Blocks\Domain\Services\CheckoutFieldsSchema\Validation' ) ) {
				return;
			}

			woocommerce_store_api_register_endpoint_data(
				[
					'endpoint'        => 'cart',
					'namespace'       => 'ddwcwr',
					'data_callback'   => function () {
						return [ 'digital_consent' => ddwcwr_cart_needs_digital_consent() ];
					},
					'schema_callback' => function () {
						return [
							'digital_consent' => [
								'description' => __( 'Whether checkout asks for consent to supply digital content.', 'devdiggers-returns-for-woocommerce' ),
								'type'        => 'boolean',
								'readonly'    => true,
							],
						];
					},
				]
			);

			$rule = function ( $needed ) {
				return [
					'type'       => 'object',
					'properties' => [
						'cart' => [
							'properties' => [
								'extensions' => [
									'properties' => [
										'ddwcwr' => [
											'properties' => [
												'digital_consent' => [ 'const' => $needed ],
											],
										],
									],
								],
							],
						],
					],
				];
			};

			try {
				woocommerce_register_additional_checkout_field(
					[
						'id'       => 'ddwcwr/digital-consent',
						'label'    => ddwcwr_get_digital_consent_text(),
						'location' => 'order',
						'type'     => 'checkbox',
						'required' => $rule( true ),
						'hidden'   => $rule( false ),
					]
				);
			} catch ( \Exception $e ) {
				wc_get_logger()->warning( $e->getMessage(), [ 'source' => 'devdiggers-returns-for-woocommerce' ] );
			}
		}

		/**
		 * Block checkout: record when the customer consented.
		 *
		 * @param string   $key Field key.
		 * @param mixed    $value Field value.
		 * @param string   $group Field group.
		 * @param \WC_Data $wc_object Order or customer.
		 * @return void
		 */
		public function ddwcwr_save_block_digital_consent( $key, $value, $group, $wc_object ) {
			if ( 'ddwcwr/digital-consent' === $key && $value && $wc_object instanceof \WC_Order ) {
				$wc_object->update_meta_data( '_ddwcwr_digital_consent', time() );
			}
		}

		/**
		 * Downloadable items an order's consent covers.
		 *
		 * @param \WC_Order $order Order.
		 * @return string[] Item names.
		 */
		protected function ddwcwr_get_consented_items( $order ) {
			$names = [];

			foreach ( ddwcwr_order_adapter()->get_order_items( $order ) as $item ) {
				if ( $item['downloadable'] ) {
					$names[] = $item['name'];
				}
			}

			return $names;
		}

		/**
		 * Order emails: confirm the consent on a durable medium (Art. 8(7)).
		 *
		 * @param \WC_Order $order Order.
		 * @param bool      $sent_to_admin Sent to admin.
		 * @param bool      $plain_text Plain text email.
		 * @return void
		 */
		public function ddwcwr_email_digital_consent( $order, $sent_to_admin, $plain_text ) {
			$time  = $order instanceof \WC_Order ? absint( $order->get_meta( '_ddwcwr_digital_consent' ) ) : 0;
			$items = $time ? $this->ddwcwr_get_consented_items( $order ) : [];

			if ( ! $items ) {
				return;
			}

			/* translators: 1: date and time, 2: product names */
			$text = sprintf( __( 'On %1$s you agreed that we start supplying the digital content (%2$s) before the withdrawal period ends, and you acknowledged that you lose your right of withdrawal once the supply has started.', 'devdiggers-returns-for-woocommerce' ), wc_format_datetime( ( new \WC_DateTime() )->setTimestamp( $time ), wc_date_format() . ' ' . wc_time_format() ), implode( ', ', $items ) );

			if ( $plain_text ) {
				echo "\n" . esc_html( $text ) . "\n";
			} else {
				echo '<p class="ddwcwr-digital-consent">' . esc_html( $text ) . '</p>';
			}
		}

		/**
		 * Edit order screen: show when the customer consented.
		 *
		 * @param \WC_Order $order Order.
		 * @return void
		 */
		public function ddwcwr_admin_digital_consent( $order ) {
			$time = absint( $order->get_meta( '_ddwcwr_digital_consent' ) );

			if ( ! $time ) {
				return;
			}

			/* translators: %s: date and time */
			echo '<p class="form-field form-field-wide">' . esc_html( sprintf( __( 'Digital content consent (Art. 16(m)) given on %s.', 'devdiggers-returns-for-woocommerce' ), wc_format_datetime( ( new \WC_DateTime() )->setTimestamp( $time ), wc_date_format() . ' ' . wc_time_format() ) ) ) . '</p>';
		}
	}
}
