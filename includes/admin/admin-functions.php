<?php
/**
 * This file handles all admin end action callbacks.
 *
 * @author DevDiggers
 * @package WPReturns
 * @version 1.0.0
 */

namespace DDWCWPReturns\Includes\Admin;

use DDWCWPReturns\Helper\Request\DDWCWR_Request_Helper;
use DDWCWPReturns\Helper\Resolution\DDWCWR_Resolution_Helper;
use DDWCWPReturns\Helper\Label\DDWCWR_Label_Helper;
use DDWCWPReturns\Helper\Upload\DDWCWR_Upload_Helper;
use DDWCWPReturns\Templates\Admin\Configuration\DDWCWR_Configuration_Template;

defined( 'ABSPATH' ) || exit();

if ( ! class_exists( 'DDWCWR_Admin_Functions' ) ) {
	/**
	 * Admin Functions Class
	 */
	class DDWCWR_Admin_Functions {
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
		 * Turn one of our WooCommerce emails on or off from the Emails tab.
		 *
		 * @return void
		 */
		public function ddwcwr_toggle_email() {
			check_ajax_referer( 'ddwcwr_toggle_email', 'nonce' );

			if ( ! ddwcwr_current_user_can() ) {
				wp_send_json_error( null, 403 );
			}

			$id = isset( $_POST['email'] ) ? sanitize_key( wp_unslash( $_POST['email'] ) ) : '';

			if ( 0 !== strpos( $id, 'ddwcwr_' ) ) {
				wp_send_json_error( null, 400 );
			}

			$settings            = (array) get_option( 'woocommerce_' . $id . '_settings', [] );
			$settings['enabled'] = ! empty( $_POST['enabled'] ) ? 'yes' : 'no';

			update_option( 'woocommerce_' . $id . '_settings', $settings );
			wp_send_json_success( [ 'enabled' => $settings['enabled'] ] );
		}

		/**
		 * Register every configuration option with a sanitizer that matches its field.
		 *
		 * @return void
		 */
		public function ddwcwr_register_settings() {
			foreach ( DDWCWR_Configuration_Template::get_tabs() as $tab ) {
				$group = 'ddwcwr-' . $tab . '-configuration-fields';

				foreach ( DDWCWR_Configuration_Template::get_tab_sections( $tab, $this->ddwcwr_configuration ) as $section ) {
					foreach ( isset( $section['fields'] ) ? $section['fields'] : [] as $field ) {
						// Locked Pro fields are never registered, so a crafted options.php post has nothing to write.
						if ( empty( $field['id'] ) || 'field_html' === $field['type'] || false !== strpos( $field['id'], '-locked-' ) ) {
							continue;
						}

						register_setting(
							$group,
							'_' . str_replace( '-', '_', $field['id'] ),
							[
								'sanitize_callback' => function ( $value ) use ( $field ) {
									return $this->ddwcwr_sanitize_field( $value, $field );
								},
							]
						);
					}
				}
			}

			register_setting( 'ddwcwr-reasons-configuration-fields', '_ddwcwr_reasons', [ 'sanitize_callback' => [ $this, 'ddwcwr_sanitize_reasons' ] ] );
			register_setting( 'ddwcwr-statuses-configuration-fields', '_ddwcwr_statuses', [ 'sanitize_callback' => [ $this, 'ddwcwr_sanitize_statuses' ] ] );
		}

		/**
		 * Sanitize one configuration field.
		 *
		 * @param mixed $value Value.
		 * @param array $field Field.
		 * @return mixed
		 */
		public function ddwcwr_sanitize_field( $value, $field ) {
			$multiple = ! empty( $field['custom_attributes']['multiple'] ) || in_array( $field['type'], [ 'categories', 'products', 'user_roles' ], true );

			switch ( $field['type'] ) {
				case 'checkbox':
					return 'yes' === $value ? 'yes' : '';
				case 'number':
					$value = '' === $value || null === $value ? '' : (float) $value;

					if ( '' !== $value && isset( $field['custom_attributes']['min'] ) ) {
						$value = max( (float) $field['custom_attributes']['min'], $value );
					}

					return '' !== $value && floor( $value ) === $value ? (int) $value : $value;
				case 'textarea':
					return sanitize_textarea_field( (string) $value );
				case 'select':
					if ( $multiple ) {
						return array_values( array_intersect( array_map( 'sanitize_text_field', (array) $value ), array_map( 'strval', array_keys( $field['options'] ) ) ) );
					}

					return array_key_exists( (string) $value, $field['options'] ) ? sanitize_text_field( (string) $value ) : ( isset( $field['default'] ) ? $field['default'] : '' );
				case 'categories':
				case 'products':
					return array_values( array_filter( array_map( 'absint', (array) $value ) ) );
				case 'password':
					return trim( sanitize_text_field( (string) $value ) );
				case 'colorpicker':
					$color = sanitize_hex_color( (string) $value );

					return $color ? $color : '';
				default:
					if ( 'ddwcwr-my-account-endpoint' === $field['id'] ) {
						// Used in rewrite rules and URLs: keep it a slug.
						$slug = sanitize_title( (string) $value );

						return $slug ? $slug : 'returns';
					}

					return sanitize_text_field( (string) $value );
			}
		}

		/**
		 * Sanitize the reasons repeater.
		 *
		 * @param mixed $value Value.
		 * @return array
		 */
		public function ddwcwr_sanitize_reasons( $value ) {
			$reasons = [];
			$seen    = [];
			// Pro's "store's fault" flag has no column here: keep what is stored for each reason.
			$stored = array_column( (array) get_option( '_ddwcwr_reasons', [] ), null, 'slug' );

			foreach ( (array) $value as $row ) {
				$label = isset( $row['label'] ) ? sanitize_text_field( $row['label'] ) : '';

				if ( '' === $label ) {
					continue;
				}

				$slug = ! empty( $row['slug'] ) ? sanitize_key( $row['slug'] ) : sanitize_title( $label );

				if ( '' === $slug || isset( $seen[ $slug ] ) ) {
					$slug .= '-' . count( $reasons );
				}

				$seen[ $slug ] = true;
				$reasons[]     = [
					'slug'           => $slug,
					'label'          => $label,
					'require_photo'  => ! empty( $row['require_photo'] ) ? 'yes' : '',
					'merchant_fault' => ! empty( $stored[ $slug ]['merchant_fault'] ) ? 'yes' : '',
					'status'         => ! empty( $row['status'] ) ? 'active' : 'inactive',
				];
			}

			return $reasons;
		}

		/**
		 * Sanitize the built-in statuses: label, color and notify. Statuses saved by Pro are kept as they are.
		 *
		 * @param mixed $value Value.
		 * @return array
		 */
		public function ddwcwr_sanitize_statuses( $value ) {
			$posted   = array_column( array_filter( (array) $value, 'is_array' ), null, 'slug' );
			$statuses = [];

			foreach ( (array) get_option( '_ddwcwr_statuses', \DDWCWR_Install::ddwcwr_get_default_statuses() ) as $status ) {
				if ( ! empty( $status['core'] ) && isset( $posted[ $status['slug'] ] ) ) {
					$row   = $posted[ $status['slug'] ];
					$label = isset( $row['label'] ) ? sanitize_text_field( $row['label'] ) : '';
					$color = isset( $row['color'] ) ? sanitize_hex_color( $row['color'] ) : '';

					$status['label']  = '' === $label ? $status['label'] : $label;
					$status['color']  = $color ? $color : $status['color'];
					$status['notify'] = ! empty( $row['notify'] ) ? 'yes' : '';
				}

				$statuses[] = $status;
			}

			return $statuses;
		}

		/**
		 * Per-page screen option.
		 *
		 * @param mixed  $status Status.
		 * @param string $option Option.
		 * @param int    $value Value.
		 * @return mixed
		 */
		public function ddwcwr_set_screen_option( $status, $option, $value ) {
			return 'ddwcwr_requests_per_page' === $option ? absint( $value ) : $status;
		}

		/**
		 * List filter forms submit the referer fields in the URL; drop them before output.
		 *
		 * @return void
		 */
		public function ddwcwr_strip_list_referer() {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only removes query args.
			if ( empty( $_GET['page'] ) || 'ddwcwr-dashboard' !== $_GET['page'] || empty( $_GET['_wp_http_referer'] ) || empty( $_SERVER['REQUEST_URI'] ) ) {
				return;
			}

			wp_safe_redirect( remove_query_arg( [ '_wp_http_referer', '_wpnonce' ], esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) ) );
			exit;
		}

		/**
		 * Redirect back to a request with a message.
		 *
		 * @param int    $request_id Request ID.
		 * @param string $message Message.
		 * @param bool   $error Error.
		 * @return void
		 */
		protected function ddwcwr_back_to_request( $request_id, $message, $error = false ) {
			wp_safe_redirect(
				add_query_arg(
					array_filter(
						[
							'page'           => 'ddwcwr-dashboard',
							'menu'           => 'requests',
							'request-id'     => absint( $request_id ),
							'ddwcwr-message' => rawurlencode( $message ),
							'ddwcwr-error'   => $error ? 1 : 0,
						]
					),
					admin_url( 'admin.php' )
				)
			);
			exit;
		}

		/**
		 * Actions on the request screen.
		 *
		 * @return void
		 */
		public function ddwcwr_handle_request_action() {
			$request_id = isset( $_POST['request_id'] ) ? absint( $_POST['request_id'] ) : 0;

			if ( ! isset( $_POST['ddwcwr_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ddwcwr_nonce'] ) ), 'ddwcwr_request_action_' . $request_id ) ) {
				wp_die( esc_html__( 'The link has expired. Go back and try again.', 'wpreturns' ), '', [ 'response' => 403 ] );
			}

			if ( ! ddwcwr_current_user_can() ) {
				wp_die( esc_html__( 'You are not allowed to manage return requests.', 'wpreturns' ), '', [ 'response' => 403 ] );
			}

			$request = $this->request_helper->get_request( $request_id );
			$do      = isset( $_POST['ddwcwr_do'] ) ? sanitize_key( wp_unslash( $_POST['ddwcwr_do'] ) ) : '';
			$user_id = get_current_user_id();

			if ( ! $request ) {
				wp_die( esc_html__( 'Request not found.', 'wpreturns' ), '', [ 'response' => 404 ] );
			}

			switch ( $do ) {
				case 'status':
				case 'approve':
				case 'reject':
					$status = 'status' === $do ? ( isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : '' ) : ( 'approve' === $do ? 'approved' : 'rejected' );
					$note   = isset( $_POST['note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['note'] ) ) : '';

					$result = $this->request_helper->change_status(
						$request_id,
						$status,
						[
							'note'      => $note,
							'author_id' => $user_id,
						]
					);

					$this->ddwcwr_back_to_request( $request_id, is_wp_error( $result ) ? $result->get_error_message() : __( 'Status updated.', 'wpreturns' ), is_wp_error( $result ) );
					break;

				case 'message':
					$message = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';
					$files   = ( new DDWCWR_Upload_Helper() )->handle_upload( 'ddwcwr_files', 10 );

					if ( is_wp_error( $files ) ) {
						$this->ddwcwr_back_to_request( $request_id, $files->get_error_message(), true );
					}

					if ( '' === trim( $message ) && empty( $files ) ) {
						$this->ddwcwr_back_to_request( $request_id, __( 'Write a message first.', 'wpreturns' ), true );
					}

					$internal = ! empty( $_POST['internal'] );

					$this->request_helper->add_message( $request_id, $message, $internal ? 'note' : 'message', 'staff', $user_id, $files );
					$this->ddwcwr_back_to_request( $request_id, $internal ? __( 'Note added.', 'wpreturns' ) : __( 'Message sent to the customer.', 'wpreturns' ) );
					break;

				case 'process':
					$result = ( new DDWCWR_Resolution_Helper() )->process(
						$request_id,
						[
							'refund_method' => isset( $_POST['refund_method'] ) && 'manual' === $_POST['refund_method'] ? 'manual' : 'gateway',
							'amount'        => isset( $_POST['amount'] ) && '' !== $_POST['amount'] ? (float) wc_format_decimal( sanitize_text_field( wp_unslash( $_POST['amount'] ) ) ) : null,
							'author_id'     => $user_id,
						]
					);

					$this->ddwcwr_back_to_request( $request_id, is_wp_error( $result ) ? $result->get_error_message() : $result['message'], is_wp_error( $result ) );
					break;

				case 'upload_label':
					// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Validated by is_uploaded_file and the type check in store_file().
					$file = isset( $_FILES['label_file'] ) ? $_FILES['label_file'] : null;

					if ( ! $file || UPLOAD_ERR_OK !== (int) $file['error'] || ! is_uploaded_file( $file['tmp_name'] ) ) {
						$this->ddwcwr_back_to_request( $request_id, __( 'Choose a label file to upload.', 'wpreturns' ), true );
					}

					$stored = ( new DDWCWR_Upload_Helper() )->store_file( $file['tmp_name'], sanitize_file_name( $file['name'] ) );

					if ( is_wp_error( $stored ) ) {
						$this->ddwcwr_back_to_request( $request_id, $stored->get_error_message(), true );
					}

					( new DDWCWR_Label_Helper() )->attach_label( $request_id, $stored );
					$this->ddwcwr_back_to_request( $request_id, __( 'Label uploaded and sent to the customer.', 'wpreturns' ) );
					break;

				case 'tracking':
					$tracking = isset( $_POST['tracking_number'] ) ? sanitize_text_field( wp_unslash( $_POST['tracking_number'] ) ) : '';
					$carrier  = isset( $_POST['carrier'] ) ? sanitize_text_field( wp_unslash( $_POST['carrier'] ) ) : '';

					$this->request_helper->update_request(
						$request_id,
						[
							'tracking_number' => substr( $tracking, 0, 100 ),
							'carrier'         => substr( $carrier, 0, 100 ),
						]
					);
					/* translators: 1: carrier, 2: tracking */
					$this->request_helper->log( $request_id, sprintf( __( 'Tracking saved: %1$s %2$s', 'wpreturns' ), $carrier, $tracking ), $user_id );
					$this->ddwcwr_back_to_request( $request_id, __( 'Tracking saved.', 'wpreturns' ) );
					break;

				case 'receive':
					$result = ( new DDWCWR_Resolution_Helper() )->mark_received( $request_id, ! empty( $_POST['restock'] ), $user_id );

					$this->ddwcwr_back_to_request( $request_id, is_wp_error( $result ) ? $result->get_error_message() : __( 'Marked as received.', 'wpreturns' ), is_wp_error( $result ) );
					break;

				case 'delete':
					$this->request_helper->delete_request( $request_id );
					wp_safe_redirect( admin_url( 'admin.php?page=ddwcwr-dashboard&menu=requests&ddwcwr-message=' . rawurlencode( __( 'Request deleted.', 'wpreturns' ) ) ) );
					exit;
			}

			$this->ddwcwr_back_to_request( $request_id, __( 'Nothing changed.', 'wpreturns' ), true );
		}

		/**
		 * Staff create a request for an order (phone or email returns).
		 *
		 * @return void
		 */
		public function ddwcwr_handle_create_request() {
			if ( ! isset( $_POST['ddwcwr_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ddwcwr_nonce'] ) ), 'ddwcwr_create_request' ) ) {
				wp_die( esc_html__( 'The link has expired. Go back and try again.', 'wpreturns' ), '', [ 'response' => 403 ] );
			}

			if ( ! ddwcwr_current_user_can() ) {
				wp_die( esc_html__( 'You are not allowed to manage return requests.', 'wpreturns' ), '', [ 'response' => 403 ] );
			}

			$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
			$items    = [];
			$raw      = isset( $_POST['items'] ) && is_array( $_POST['items'] ) ? wp_unslash( $_POST['items'] ) : []; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized per field below.

			foreach ( $raw as $item_id => $line ) {
				if ( empty( $line['qty'] ) ) {
					continue;
				}

				$items[ absint( $item_id ) ] = [
					'qty'       => absint( $line['qty'] ),
					'reason'    => isset( $line['reason'] ) ? sanitize_key( $line['reason'] ) : '',
					'condition' => isset( $line['condition'] ) ? sanitize_key( $line['condition'] ) : '',
				];
			}

			$request_id = $this->request_helper->create_request(
				[
					'order'    => ddwcwr_order_adapter()->get_order( $order_id ),
					'type'     => isset( $_POST['type'] ) ? sanitize_key( wp_unslash( $_POST['type'] ) ) : 'return',
					'items'    => $items,
					'note'     => isset( $_POST['note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['note'] ) ) : '',
					'source'   => 'admin',
					'by_admin' => true,
				]
			);

			if ( is_wp_error( $request_id ) ) {
				wp_safe_redirect( admin_url( 'admin.php?page=ddwcwr-dashboard&menu=requests&action=add&order-id=' . $order_id . '&ddwcwr-error=1&ddwcwr-message=' . rawurlencode( $request_id->get_error_message() ) ) );
				exit;
			}

			$this->ddwcwr_back_to_request( $request_id, __( 'Request created.', 'wpreturns' ) );
		}

		/**
		 * CSV export of EU withdrawals (one row per item), the store's record of every statement received.
		 *
		 * @return void
		 */
		public function ddwcwr_handle_export_withdrawals() {
			if ( ! isset( $_GET['ddwcwr_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['ddwcwr_nonce'] ) ), 'ddwcwr_export_withdrawals' ) ) {
				wp_die( esc_html__( 'The link has expired. Go back and try again.', 'wpreturns' ), '', [ 'response' => 403 ] );
			}

			if ( ! ddwcwr_current_user_can() ) {
				wp_die( esc_html__( 'You are not allowed to export requests.', 'wpreturns' ), '', [ 'response' => 403 ] );
			}

			$requests = $this->request_helper->get_requests(
				[
					'type'  => 'withdrawal',
					'limit' => -1,
				]
			);
			$clean    = function ( $value ) {
				$value = (string) $value;

				// Spreadsheet formula injection guard.
				return preg_match( '/^[=+\-@\t\r]/', $value ) ? "'" . $value : $value;
			};

			nocache_headers();
			header( 'Content-Type: text/csv; charset=utf-8' );
			header( 'Content-Disposition: attachment; filename=wpreturns-withdrawals-' . gmdate( 'Y-m-d' ) . '.csv' );

			$out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Streaming the download.

			fputcsv( $out, [ 'Request', 'Order', 'Status', 'Name given', 'Confirmation email', 'Received (UTC)', 'Closed', 'Item', 'SKU', 'Qty', 'Item value', 'Paid back', 'Source' ], ',', '"', '' );

			foreach ( $requests as $request ) {
				foreach ( $this->request_helper->get_request_items( $request['id'] ) as $item ) {
					$product = wc_get_product( $item['variation_id'] ? $item['variation_id'] : $item['product_id'] );

					fputcsv(
						$out,
						array_map(
							$clean,
							[
								$request['number'],
								$request['order_id'],
								ddwcwr_get_status_label( $request['status'] ),
								isset( $request['meta']['statement_name'] ) ? $request['meta']['statement_name'] : $request['customer_name'],
								isset( $request['meta']['statement_email'] ) ? $request['meta']['statement_email'] : $request['customer_email'],
								isset( $request['meta']['statement_utc'] ) ? $request['meta']['statement_utc'] : get_gmt_from_date( $request['created'] ),
								$request['closed'],
								$item['name'],
								$product ? $product->get_sku() : '',
								$item['qty'],
								$item['line_total'],
								$request['refunded_total'],
								$request['source'],
							]
						),
						',',
						'"',
						''
					);
				}
			}

			fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			exit;
		}

		/**
		 * Clear the unmatched withdrawal log.
		 *
		 * @return void
		 */
		public function ddwcwr_handle_clear_unmatched() {
			if ( ! isset( $_POST['ddwcwr_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ddwcwr_nonce'] ) ), 'ddwcwr_clear_unmatched' ) ) {
				wp_die( esc_html__( 'The link has expired. Go back and try again.', 'wpreturns' ), '', [ 'response' => 403 ] );
			}

			if ( ! ddwcwr_current_user_can() ) {
				wp_die( esc_html__( 'You are not allowed to do this.', 'wpreturns' ), '', [ 'response' => 403 ] );
			}

			delete_option( '_ddwcwr_unmatched_withdrawals' );
			wp_safe_redirect( admin_url( 'admin.php?page=ddwcwr-dashboard&menu=withdrawals&ddwcwr-message=' . rawurlencode( __( 'Unmatched submissions cleared.', 'wpreturns' ) ) ) );
			exit;
		}

		/**
		 * Final sale and withdrawal exemption on the product General tab.
		 *
		 * @return void
		 */
		public function ddwcwr_product_fields() {
			global $product_object;

			echo '<div class="options_group ddwcwr-product-fields">';

			woocommerce_wp_checkbox(
				[
					'id'          => '_ddwcwr_final_sale',
					'label'       => __( 'Final sale', 'wpreturns' ),
					'value'       => $product_object->get_meta( '_ddwcwr_final_sale', true ),
					'cbvalue'     => 'yes',
					'description' => __( 'Customers cannot request a return for this product. The EU right of withdrawal is not affected.', 'wpreturns' ),
				]
			);

			woocommerce_wp_checkbox(
				[
					'id'          => '_ddwcwr_withdrawal_excluded',
					'label'       => __( 'Exempt from withdrawal', 'wpreturns' ),
					'value'       => $product_object->get_meta( '_ddwcwr_withdrawal_excluded', true ),
					'cbvalue'     => 'yes',
					'description' => __( 'Exempt under Art. 16 of the EU Consumer Rights Directive (made to order, perishable, sealed hygiene goods, etc.).', 'wpreturns' ),
				]
			);

			wp_nonce_field( 'ddwcwr_product_fields', 'ddwcwr_product_nonce' );

			echo '</div>';
		}

		/**
		 * Save product fields.
		 *
		 * @param int $product_id Product ID.
		 * @return void
		 */
		public function ddwcwr_save_product_fields( $product_id ) {
			if ( ! isset( $_POST['ddwcwr_product_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ddwcwr_product_nonce'] ) ), 'ddwcwr_product_fields' ) ) {
				return;
			}

			if ( ! current_user_can( 'edit_product', $product_id ) ) {
				return;
			}

			$product = wc_get_product( $product_id );

			if ( ! $product ) {
				return;
			}

			$product->update_meta_data( '_ddwcwr_final_sale', ! empty( $_POST['_ddwcwr_final_sale'] ) ? 'yes' : '' );
			$product->update_meta_data( '_ddwcwr_withdrawal_excluded', ! empty( $_POST['_ddwcwr_withdrawal_excluded'] ) ? 'yes' : '' );
			$product->save_meta_data();
		}

		/**
		 * Order screen meta box.
		 *
		 * @return void
		 */
		public function ddwcwr_add_order_meta_box() {
			$screen = function_exists( 'wc_get_page_screen_id' ) ? wc_get_page_screen_id( 'shop-order' ) : 'shop_order';

			add_meta_box( 'ddwcwr-order-requests', __( 'Returns', 'wpreturns' ), [ $this, 'ddwcwr_order_meta_box' ], $screen, 'side', 'default' );
		}

		/**
		 * Order meta box content.
		 *
		 * @param mixed $post_or_order Post or order.
		 * @return void
		 */
		public function ddwcwr_order_meta_box( $post_or_order ) {
			$order = $post_or_order instanceof \WC_Order ? $post_or_order : wc_get_order( $post_or_order->ID );

			if ( ! $order ) {
				return;
			}

			$requests = $this->request_helper->get_order_requests( $order->get_id() );

			if ( $requests ) {
				echo '<ul class="ddwcwr-order-box">';

				foreach ( $requests as $request ) {
					printf(
						'<li><a href="%1$s">%2$s</a> %3$s</li>',
						esc_url( admin_url( 'admin.php?page=ddwcwr-dashboard&menu=requests&request-id=' . absint( $request['id'] ) ) ),
						esc_html( $request['number'] ),
						wp_kses_post( ddwcwr_get_status_badge( $request['status'] ) )
					);
				}

				echo '</ul>';
			} else {
				echo '<p>' . esc_html__( 'No return requests for this order.', 'wpreturns' ) . '</p>';
			}

			if ( ddwcwr_current_user_can() ) {
				printf( '<p><a class="button" href="%1$s">%2$s</a></p>', esc_url( admin_url( 'admin.php?page=ddwcwr-dashboard&menu=requests&action=add&order-id=' . $order->get_id() ) ), esc_html__( 'Create request', 'wpreturns' ) );
			}
		}

		/**
		 * Orders list column.
		 *
		 * @param array $columns Columns.
		 * @return array
		 */
		public function ddwcwr_add_orders_column( $columns ) {
			$columns['ddwcwr_returns'] = __( 'Returns', 'wpreturns' );

			return $columns;
		}

		/**
		 * Orders list column content.
		 *
		 * @param string $column Column.
		 * @param mixed  $order_or_id Order or ID.
		 * @return void
		 */
		public function ddwcwr_orders_column_content( $column, $order_or_id ) {
			if ( 'ddwcwr_returns' !== $column ) {
				return;
			}

			$order_id = $order_or_id instanceof \WC_Order ? $order_or_id->get_id() : absint( $order_or_id );

			foreach ( array_slice( $this->request_helper->get_order_requests( $order_id ), 0, 2 ) as $request ) {
				printf( '<a href="%1$s">%2$s</a> ', esc_url( admin_url( 'admin.php?page=ddwcwr-dashboard&menu=requests&request-id=' . absint( $request['id'] ) ) ), wp_kses_post( ddwcwr_get_status_badge( $request['status'] ) ) );
			}
		}
	}
}
