<?php
/**
 * Front callbacks.
 *
 * @author DevDiggers
 * @package DevDiggers Returns
 * @version 1.0.0
 */

namespace DDWCWPReturns\Includes\Front;

use DDWCWPReturns\Helper\Request\DDWCWR_Request_Helper;
use DDWCWPReturns\Helper\Upload\DDWCWR_Upload_Helper;

defined( 'ABSPATH' ) || exit();

if ( ! class_exists( 'DDWCWR_Front_Functions' ) ) {
	/**
	 * Front functions class
	 */
	class DDWCWR_Front_Functions {
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
		 * Render a front template (themes can override in yourtheme/devdiggers-returns-for-woocommerce/front/).
		 *
		 * @param string $name Template name.
		 * @param array  $args Arguments.
		 * @return void
		 */
		protected function template( $name, $args = [] ) {
			wc_get_template( 'front/' . $name . '.php', $args, 'devdiggers-returns-for-woocommerce/', DDWCWR_PLUGIN_FILE . 'templates/' );
		}

		/**
		 * Enqueue front assets where they are needed.
		 *
		 * @return void
		 */
		public function ddwcwr_front_scripts() {
			wp_register_style( 'ddwcwr-front-style', DDWCWR_PLUGIN_URL . 'assets/css/front.css', [], filemtime( DDWCWR_PLUGIN_FILE . 'assets/css/front.css' ) );
			wp_register_script( 'ddwcwr-front-script', DDWCWR_PLUGIN_URL . 'assets/js/front.js', [], filemtime( DDWCWR_PLUGIN_FILE . 'assets/js/front.js' ), true );

			wp_localize_script(
				'ddwcwr-front-script',
				'ddwcwrFrontObj',
				[
					'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
					'nonce'    => wp_create_nonce( 'ddwcwr_front' ),
					'maxFiles' => absint( $this->ddwcwr_configuration['max_files'] ),
					'maxSize'  => (float) $this->ddwcwr_configuration['max_file_size'],
					'i18n'     => [
						'tooMany'      => __( 'You selected too many files.', 'devdiggers-returns-for-woocommerce' ),
						/* translators: %s: file name */
						'tooBig'       => __( '%s is too large.', 'devdiggers-returns-for-woocommerce' ),
						'selectItem'   => __( 'Please select at least one item.', 'devdiggers-returns-for-woocommerce' ),
						'confirmLeave' => __( 'Cancel this request?', 'devdiggers-returns-for-woocommerce' ),
					],
				]
			);

			if ( $this->ddwcwr_is_plugin_page() ) {
				wp_enqueue_style( 'ddwcwr-front-style' );
				wp_enqueue_script( 'ddwcwr-front-script' );
			} elseif ( is_product() || is_checkout() || 'yes' === $this->ddwcwr_configuration['withdrawal_footer_link'] ) {
				wp_enqueue_style( 'ddwcwr-front-style' );
			}
		}

		/**
		 * Whether the current page shows a returns screen.
		 *
		 * @return bool
		 */
		protected function ddwcwr_is_plugin_page() {
			global $post;

			if ( is_account_page() ) {
				return true;
			}

			return $post && ( has_shortcode( $post->post_content, 'ddwcwr_returns_portal' ) || has_shortcode( $post->post_content, 'ddwcwr_withdrawal_form' ) || has_block( 'ddwcwr/returns-portal', $post ) || has_block( 'ddwcwr/withdrawal-form', $post ) || in_array( (int) $post->ID, [ absint( $this->ddwcwr_configuration['portal_page_id'] ), absint( $this->ddwcwr_configuration['withdrawal_page_id'] ) ], true ) );
		}

		/**
		 * Guest access token for an order.
		 *
		 * @param int    $order_id Order ID.
		 * @param string $email Billing email.
		 * @param int    $expires Expiry timestamp.
		 * @return string
		 */
		protected function ddwcwr_make_token( $order_id, $email, $expires ) {
			return hash_hmac( 'sha256', absint( $order_id ) . '|' . strtolower( $email ) . '|' . absint( $expires ), wp_salt( 'nonce' ) );
		}

		/**
		 * Portal URL that gives a guest access to one order for two hours.
		 *
		 * @param object $order Order.
		 * @param array  $args Extra query args.
		 * @return string
		 */
		protected function ddwcwr_get_guest_order_url( $order, $args = [] ) {
			$expires = time() + 2 * HOUR_IN_SECONDS;

			return add_query_arg(
				array_merge(
					[
						'ddwcwr-order'   => $order->get_id(),
						'ddwcwr-expires' => $expires,
						'ddwcwr-token'   => $this->ddwcwr_make_token( $order->get_id(), $order->get_billing_email(), $expires ),
					],
					$args
				),
				ddwcwr_get_portal_url()
			);
		}

		/**
		 * Order the visitor may act on: their own order, or a guest token for it.
		 *
		 * @param int  $order_id Order ID.
		 * @param bool $from_post Read the token from the form post instead of the URL.
		 * @return object|false
		 */
		protected function ddwcwr_get_authorized_order( $order_id, $from_post = false ) {
			$order = ddwcwr_order_adapter()->get_order( $order_id );

			if ( ! $order ) {
				return false;
			}

			if ( is_user_logged_in() && ddwcwr_order_adapter()->order_belongs_to( $order, get_current_user_id() ) ) {
				return $order;
			}

			// phpcs:disable WordPress.Security.NonceVerification -- Token is an HMAC checked below; form posts verify their nonce before calling this.
			$source  = $from_post ? $_POST : $_GET;
			$expires = isset( $source['ddwcwr-expires'] ) ? absint( $source['ddwcwr-expires'] ) : 0;
			$token   = isset( $source['ddwcwr-token'] ) ? sanitize_text_field( wp_unslash( $source['ddwcwr-token'] ) ) : '';
			// phpcs:enable

			if ( $expires < time() || '' === $token || ! hash_equals( $this->ddwcwr_make_token( $order->get_id(), $order->get_billing_email(), $expires ), $token ) ) {
				return false;
			}

			return $order;
		}

		/**
		 * Rate limit by hashed IP.
		 *
		 * @param string $bucket Bucket.
		 * @return bool True when the limit is reached.
		 */
		protected function ddwcwr_rate_limited( $bucket ) {
			$limit = max( 1, absint( $this->ddwcwr_configuration['withdrawal_rate_limit'] ) );
			$key   = 'ddwcwr_rl_' . $bucket . '_' . substr( ddwcwr_get_client_ip_hash(), 0, 24 );
			$count = (int) get_transient( $key );

			if ( $count >= $limit ) {
				return true;
			}

			set_transient( $key, $count + 1, HOUR_IN_SECONDS );

			return false;
		}

		/**
		 * Give back one attempt, so only failed tries count toward the limit.
		 *
		 * @param string $bucket Bucket.
		 * @return void
		 */
		protected function ddwcwr_rate_limit_forgive( $bucket ) {
			$key   = 'ddwcwr_rl_' . $bucket . '_' . substr( ddwcwr_get_client_ip_hash(), 0, 24 );
			$count = (int) get_transient( $key );

			if ( $count > 0 ) {
				set_transient( $key, $count - 1, HOUR_IN_SECONDS );
			}
		}

		/**
		 * Handle every customer form post.
		 *
		 * @return void
		 */
		public function ddwcwr_handle_forms() {
			if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || 'POST' !== $_SERVER['REQUEST_METHOD'] || empty( $_POST['ddwcwr_action'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Router only; each action verifies its own nonce.
				return;
			}

			$action = sanitize_key( wp_unslash( $_POST['ddwcwr_action'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Router only.

			// Guests need a session cookie so notices survive the redirect after a post.
			if ( WC()->session && ! WC()->session->has_session() ) {
				WC()->session->set_customer_session_cookie( true );
			}

			switch ( $action ) {
				case 'submit_request':
					$this->ddwcwr_handle_submit_request();
					break;
				case 'send_message':
					$this->ddwcwr_handle_send_message();
					break;
				case 'add_tracking':
					$this->ddwcwr_handle_add_tracking();
					break;
				case 'cancel_request':
					$this->ddwcwr_handle_cancel_request();
					break;
				case 'portal_lookup':
					$this->ddwcwr_handle_portal_lookup();
					break;
				case 'withdrawal_confirm':
					$this->ddwcwr_handle_withdrawal();
					break;
			}
		}

		/**
		 * New request form post.
		 *
		 * @return void
		 */
		protected function ddwcwr_handle_submit_request() {
			if ( ! isset( $_POST['ddwcwr_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ddwcwr_nonce'] ) ), 'ddwcwr_submit_request' ) ) {
				wc_add_notice( __( 'Your session expired. Please try again.', 'devdiggers-returns-for-woocommerce' ), 'error' );
				return;
			}

			$order_id = isset( $_POST['ddwcwr_order_id'] ) ? absint( $_POST['ddwcwr_order_id'] ) : 0;
			$order    = $this->ddwcwr_get_authorized_order( $order_id, true );

			if ( ! $order ) {
				wc_add_notice( __( 'You do not have access to this order.', 'devdiggers-returns-for-woocommerce' ), 'error' );
				return;
			}

			$items = [];
			$raw   = isset( $_POST['ddwcwr_items'] ) && is_array( $_POST['ddwcwr_items'] ) ? wp_unslash( $_POST['ddwcwr_items'] ) : []; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Each field sanitized below.

			foreach ( $raw as $item_id => $line ) {
				if ( empty( $line['selected'] ) ) {
					continue;
				}

				$items[ absint( $item_id ) ] = [
					'qty'       => isset( $line['qty'] ) ? absint( $line['qty'] ) : 1,
					'reason'    => isset( $line['reason'] ) ? sanitize_key( $line['reason'] ) : '',
					'condition' => isset( $line['condition'] ) ? sanitize_key( $line['condition'] ) : '',
				];
			}

			$type   = isset( $_POST['ddwcwr_type'] ) ? sanitize_key( wp_unslash( $_POST['ddwcwr_type'] ) ) : 'return';
			$upload = new DDWCWR_Upload_Helper();
			$files  = 'yes' === $this->ddwcwr_configuration['allow_uploads'] ? $upload->handle_upload( 'ddwcwr_files' ) : [];

			if ( is_wp_error( $files ) ) {
				wc_add_notice( $files->get_error_message(), 'error' );
				return;
			}

			$request_id = $this->request_helper->create_request(
				[
					'order'       => $order,
					'type'        => $type,
					'items'       => $items,
					'note'        => isset( $_POST['ddwcwr_note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['ddwcwr_note'] ) ) : '',
					'attachments' => $files,
					'source'      => is_user_logged_in() ? 'account' : 'portal',
				]
			);

			if ( is_wp_error( $request_id ) ) {
				$upload->delete_files( $files );
				wc_add_notice( $request_id->get_error_message(), 'error' );
				return;
			}

			$request = $this->request_helper->get_request( $request_id );

			/* translators: %s: request number */
			wc_add_notice( sprintf( __( 'Thanks! Your request %s has been submitted. We have emailed you a copy.', 'devdiggers-returns-for-woocommerce' ), $request['number'] ) );
			wp_safe_redirect( ddwcwr_get_request_view_url( $request, ! is_user_logged_in() ) );
			exit;
		}

		/**
		 * Load a request the visitor may act on from the posted ID and key.
		 *
		 * @return array|null
		 */
		protected function ddwcwr_get_posted_request() {
			// phpcs:disable WordPress.Security.NonceVerification.Missing -- Callers verify the nonce first.
			$request_id = isset( $_POST['ddwcwr_request_id'] ) ? absint( $_POST['ddwcwr_request_id'] ) : 0;
			$key        = isset( $_POST['ddwcwr_key'] ) ? sanitize_text_field( wp_unslash( $_POST['ddwcwr_key'] ) ) : '';
			// phpcs:enable
			$request = $this->request_helper->get_request( $request_id );

			return $this->request_helper->can_view( $request, $key ) ? $request : null;
		}

		/**
		 * Customer message post.
		 *
		 * @return void
		 */
		protected function ddwcwr_handle_send_message() {
			if ( ! isset( $_POST['ddwcwr_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ddwcwr_nonce'] ) ), 'ddwcwr_request_action' ) ) {
				wc_add_notice( __( 'Your session expired. Please try again.', 'devdiggers-returns-for-woocommerce' ), 'error' );
				return;
			}

			$request = $this->ddwcwr_get_posted_request();

			if ( ! $request ) {
				wc_add_notice( __( 'You do not have access to this request.', 'devdiggers-returns-for-woocommerce' ), 'error' );
				return;
			}

			$message = isset( $_POST['ddwcwr_message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['ddwcwr_message'] ) ) : '';
			$upload  = new DDWCWR_Upload_Helper();
			$files   = 'yes' === $this->ddwcwr_configuration['allow_uploads'] ? $upload->handle_upload( 'ddwcwr_files' ) : [];

			if ( is_wp_error( $files ) ) {
				wc_add_notice( $files->get_error_message(), 'error' );
				return;
			}

			if ( '' === trim( $message ) && empty( $files ) ) {
				wc_add_notice( __( 'Please write a message.', 'devdiggers-returns-for-woocommerce' ), 'error' );
				return;
			}

			$this->request_helper->add_message( $request['id'], $message, 'message', 'customer', get_current_user_id(), $files );
			wc_add_notice( __( 'Message sent.', 'devdiggers-returns-for-woocommerce' ) );
			wp_safe_redirect( ddwcwr_get_request_view_url( $request, ! is_user_logged_in() ) . '#ddwcwr-conversation' );
			exit;
		}

		/**
		 * Customer adds the return tracking number.
		 *
		 * @return void
		 */
		protected function ddwcwr_handle_add_tracking() {
			if ( ! isset( $_POST['ddwcwr_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ddwcwr_nonce'] ) ), 'ddwcwr_request_action' ) ) {
				wc_add_notice( __( 'Your session expired. Please try again.', 'devdiggers-returns-for-woocommerce' ), 'error' );
				return;
			}

			$request = $this->ddwcwr_get_posted_request();

			if ( ! $request || in_array( $request['status'], ddwcwr_get_final_statuses(), true ) ) {
				wc_add_notice( __( 'You cannot change this request.', 'devdiggers-returns-for-woocommerce' ), 'error' );
				return;
			}

			$tracking = isset( $_POST['ddwcwr_tracking'] ) ? sanitize_text_field( wp_unslash( $_POST['ddwcwr_tracking'] ) ) : '';
			$carrier  = isset( $_POST['ddwcwr_carrier'] ) ? sanitize_text_field( wp_unslash( $_POST['ddwcwr_carrier'] ) ) : '';

			if ( '' === $tracking ) {
				wc_add_notice( __( 'Please enter the tracking number.', 'devdiggers-returns-for-woocommerce' ), 'error' );
				return;
			}

			$this->request_helper->update_request(
				$request['id'],
				[
					'tracking_number' => substr( $tracking, 0, 100 ),
					'carrier'         => substr( $carrier, 0, 100 ),
				]
			);
			/* translators: 1: carrier, 2: tracking number */
			$this->request_helper->log( $request['id'], sprintf( __( 'Customer added return tracking: %1$s %2$s', 'devdiggers-returns-for-woocommerce' ), $carrier, $tracking ) );

			if ( 'approved' === $request['status'] ) {
				$this->request_helper->change_status( $request['id'], 'awaiting_return', [ 'notify' => false ] );
			}

			wc_add_notice( __( 'Thanks, we saved your tracking number.', 'devdiggers-returns-for-woocommerce' ) );
			wp_safe_redirect( ddwcwr_get_request_view_url( $request, ! is_user_logged_in() ) );
			exit;
		}

		/**
		 * Customer cancels their own pending request.
		 *
		 * @return void
		 */
		protected function ddwcwr_handle_cancel_request() {
			if ( ! isset( $_POST['ddwcwr_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ddwcwr_nonce'] ) ), 'ddwcwr_request_action' ) ) {
				wc_add_notice( __( 'Your session expired. Please try again.', 'devdiggers-returns-for-woocommerce' ), 'error' );
				return;
			}

			$request = $this->ddwcwr_get_posted_request();

			if ( ! $request || 'pending' !== $request['status'] ) {
				wc_add_notice( __( 'Only requests that are still waiting for review can be canceled.', 'devdiggers-returns-for-woocommerce' ), 'error' );
				return;
			}

			$this->request_helper->change_status( $request['id'], 'cancelled', [ 'note' => __( 'Canceled by the customer.', 'devdiggers-returns-for-woocommerce' ) ] );
			wc_add_notice( __( 'Your request was canceled.', 'devdiggers-returns-for-woocommerce' ) );
			wp_safe_redirect( ddwcwr_get_request_view_url( $request, ! is_user_logged_in() ) );
			exit;
		}

		/**
		 * Guest portal lookup.
		 *
		 * @return void
		 */
		protected function ddwcwr_handle_portal_lookup() {
			if ( ! isset( $_POST['ddwcwr_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ddwcwr_nonce'] ) ), 'ddwcwr_portal_lookup' ) ) {
				wc_add_notice( __( 'Your session expired. Please try again.', 'devdiggers-returns-for-woocommerce' ), 'error' );
				return;
			}

			// Honeypot: people never fill the hidden field.
			if ( ! empty( $_POST['ddwcwr_website'] ) ) {
				return;
			}

			if ( $this->ddwcwr_rate_limited( 'lookup' ) ) {
				wc_add_notice( __( 'Too many attempts. Please wait an hour and try again.', 'devdiggers-returns-for-woocommerce' ), 'error' );
				return;
			}

			$number = isset( $_POST['ddwcwr_order_number'] ) ? sanitize_text_field( wp_unslash( $_POST['ddwcwr_order_number'] ) ) : '';
			$email  = isset( $_POST['ddwcwr_email'] ) ? sanitize_email( wp_unslash( $_POST['ddwcwr_email'] ) ) : '';
			$order  = ddwcwr_order_adapter()->find_order( $number, $email );

			if ( ! $order ) {
				wc_add_notice( __( 'We could not find an order with that number and email address. Please check both and try again.', 'devdiggers-returns-for-woocommerce' ), 'error' );
				return;
			}

			// A customer who finds their order is not guessing: only failed lookups count toward the limit.
			$this->ddwcwr_rate_limit_forgive( 'lookup' );

			wp_safe_redirect( $this->ddwcwr_get_guest_order_url( $order ) );
			exit;
		}

		/**
		 * EU withdrawal statement (step 2: "Confirm withdrawal").
		 *
		 * @return void
		 */
		protected function ddwcwr_handle_withdrawal() {
			if ( ! isset( $_POST['ddwcwr_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ddwcwr_nonce'] ) ), 'ddwcwr_withdrawal' ) ) {
				wc_add_notice( __( 'Your session expired. Please try again.', 'devdiggers-returns-for-woocommerce' ), 'error' );
				return;
			}

			if ( ! empty( $_POST['ddwcwr_website'] ) ) {
				return;
			}

			$name   = isset( $_POST['ddwcwr_name'] ) ? sanitize_text_field( wp_unslash( $_POST['ddwcwr_name'] ) ) : '';
			$number = isset( $_POST['ddwcwr_order_number'] ) ? sanitize_text_field( wp_unslash( $_POST['ddwcwr_order_number'] ) ) : '';
			$email  = isset( $_POST['ddwcwr_email'] ) ? sanitize_email( wp_unslash( $_POST['ddwcwr_email'] ) ) : '';
			$picked = isset( $_POST['ddwcwr_withdraw_items'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['ddwcwr_withdraw_items'] ) ) : [];

			if ( '' === $name || '' === $number || ! is_email( $email ) ) {
				wc_add_notice( __( 'Please fill in your name, the order number and your email address.', 'devdiggers-returns-for-woocommerce' ), 'error' );
				return;
			}

			if ( $this->ddwcwr_rate_limited( 'withdrawal' ) ) {
				wc_add_notice( __( 'Too many attempts. Please wait an hour and try again, or contact us directly.', 'devdiggers-returns-for-woocommerce' ), 'error' );
				return;
			}

			$order = ddwcwr_order_adapter()->find_order( $number, $email );

			// A logged-in customer may confirm to a different email for one of their own orders.
			if ( ! $order && is_user_logged_in() ) {
				$candidate = ddwcwr_order_adapter()->get_order( absint( ltrim( $number, '#' ) ) );
				$order     = $candidate && ddwcwr_order_adapter()->order_belongs_to( $candidate, get_current_user_id() ) ? $candidate : false;
			}

			if ( ! $order ) {
				// Keep the statement for staff, but send nothing to an unverified address.
				$log   = (array) get_option( '_ddwcwr_unmatched_withdrawals', [] );
				$log[] = [
					'name'    => $name,
					'order'   => $number,
					'email'   => $email,
					'date'    => current_time( 'mysql', true ),
					'ip_hash' => ddwcwr_get_client_ip_hash(),
				];
				update_option( '_ddwcwr_unmatched_withdrawals', array_slice( $log, -200 ), false );

				wc_add_notice( __( 'We could not match that order number and email address. We have recorded your statement and our team will contact you; please also check the details and try again.', 'devdiggers-returns-for-woocommerce' ), 'error' );
				return;
			}

			$items = [];

			if ( $picked ) {
				$eligible = $this->request_helper->get_eligible_items( $order, 'withdrawal' );

				foreach ( $picked as $item_id ) {
					if ( isset( $eligible[ $item_id ] ) && $eligible[ $item_id ]['eligible'] ) {
						$items[ $item_id ] = [ 'qty' => $eligible[ $item_id ]['available'] ];
					}
				}
			}

			$request_id = $this->request_helper->create_request(
				[
					'order'  => $order,
					'type'   => 'withdrawal',
					'items'  => $items,
					'source' => 'withdrawal',
					// The acknowledgement goes to the address the customer gave in the statement.
					'email'  => $email,
					'meta'   => [
						'statement_name'  => $name,
						'statement_email' => $email,
						'statement_utc'   => current_time( 'mysql', true ),
					],
				]
			);

			if ( is_wp_error( $request_id ) ) {
				wc_add_notice( $request_id->get_error_message(), 'error' );
				return;
			}

			$request = $this->request_helper->get_request( $request_id );

			wp_safe_redirect(
				add_query_arg(
					[
						'ddwcwr-done' => $request_id,
						'ddwcwr-key'  => rawurlencode( $request['access_key'] ),
					],
					ddwcwr_get_withdrawal_url()
				)
			);
			exit;
		}

		/**
		 * Add the endpoint query var.
		 *
		 * @param array $vars Vars.
		 * @return array
		 */
		public function ddwcwr_add_query_vars( $vars ) {
			$vars[] = $this->ddwcwr_configuration['my_account_endpoint'];

			return $vars;
		}

		/**
		 * Add "Returns" to My Account before Logout.
		 *
		 * @param array $items Items.
		 * @return array
		 */
		public function ddwcwr_add_account_menu_item( $items ) {
			$logout = isset( $items['customer-logout'] ) ? $items['customer-logout'] : false;

			unset( $items['customer-logout'] );

			$items[ $this->ddwcwr_configuration['my_account_endpoint'] ] = $this->ddwcwr_configuration['my_account_endpoint_title'];

			if ( $logout ) {
				$items['customer-logout'] = $logout;
			}

			return $items;
		}

		/**
		 * Endpoint title.
		 *
		 * @return string
		 */
		public function ddwcwr_account_endpoint_title() {
			return $this->ddwcwr_configuration['my_account_endpoint_title'];
		}

		/**
		 * My Account > Returns content: list, new request form or a request.
		 *
		 * @param string $value Endpoint value.
		 * @return void
		 */
		public function ddwcwr_account_endpoint_content( $value = '' ) {
			echo '<div class="ddwcwr-ui">';
			$this->ddwcwr_account_endpoint_screen( (string) $value );
			echo '</div>';
		}

		/**
		 * The screen inside My Account > Returns.
		 *
		 * @param string $value Endpoint value.
		 * @return void
		 */
		protected function ddwcwr_account_endpoint_screen( $value ) {

			if ( 0 === strpos( $value, 'view-' ) ) {
				$request = $this->request_helper->get_request( absint( substr( $value, 5 ) ) );

				if ( ! $request || get_current_user_id() !== (int) $request['customer_id'] ) {
					wc_print_notice( __( 'Request not found.', 'devdiggers-returns-for-woocommerce' ), 'error' );
					return;
				}

				$this->ddwcwr_render_request_view( $request );
				return;
			}

			if ( 0 === strpos( $value, 'new-' ) ) {
				$order = $this->ddwcwr_get_authorized_order( absint( substr( $value, 4 ) ) );
				$type  = isset( $_GET['type'] ) ? sanitize_key( wp_unslash( $_GET['type'] ) ) : 'return'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display only.

				if ( ! $order ) {
					wc_print_notice( __( 'Order not found.', 'devdiggers-returns-for-woocommerce' ), 'error' );
					return;
				}

				$this->ddwcwr_render_request_form( $order, $type, ddwcwr_get_account_url() );
				return;
			}

			$requests = $this->request_helper->get_requests(
				[
					'customer_id' => get_current_user_id(),
					'limit'       => 50,
				]
			);
			$orders   = [];

			foreach ( ddwcwr_order_adapter()->get_customer_orders( get_current_user_id(), 10 ) as $order ) {
				$actions = $this->ddwcwr_get_order_request_links( $order );

				if ( $actions ) {
					$orders[] = [
						'order'   => $order,
						'actions' => $actions,
					];
				}
			}

			$this->template(
				'account-requests',
				[
					'requests' => $requests,
					'orders'   => $orders,
				]
			);
		}

		/**
		 * Request types available for an order, with links.
		 *
		 * @param object $order Order.
		 * @return array type => [ label, url ].
		 */
		protected function ddwcwr_get_order_request_links( $order ) {
			$order_data = ddwcwr_order_adapter()->get_order_data( $order );
			$is_owner   = is_user_logged_in() && ddwcwr_order_adapter()->order_belongs_to( $order, get_current_user_id() );
			$links      = [];
			$labels     = [
				'return'       => __( 'Request a return', 'devdiggers-returns-for-woocommerce' ),
				'cancellation' => __( 'Request cancellation', 'devdiggers-returns-for-woocommerce' ),
				'withdrawal'   => __( 'Withdraw from contract here', 'devdiggers-returns-for-woocommerce' ),
			];

			foreach ( $labels as $type => $label ) {
				if ( ! $this->request_helper->order_status_allows( $order_data, $type ) ) {
					continue;
				}

				$items = array_filter( $this->request_helper->get_eligible_items( $order, $type ), fn( $item ) => $item['eligible'] );

				if ( ! $items ) {
					continue;
				}

				if ( 'withdrawal' === $type ) {
					$url = ddwcwr_get_withdrawal_url( $order->get_id() );
				} elseif ( $is_owner ) {
					$url = add_query_arg( 'type', $type, ddwcwr_get_account_url( 'new-' . $order->get_id() ) );
				} else {
					$url = $this->ddwcwr_get_guest_order_url( $order, [ 'ddwcwr-type' => $type ] );
				}

				$links[ $type ] = [
					'label'    => $label,
					'url'      => $url,
					'deadline' => max( wp_list_pluck( $items, 'deadline' ) ),
				];
			}

			return $links;
		}

		/**
		 * Render the request form.
		 *
		 * @param object $order Order.
		 * @param string $type Type.
		 * @param string $back_url Back link.
		 * @return void
		 */
		protected function ddwcwr_render_request_form( $order, $type, $back_url ) {
			if ( ! array_key_exists( $type, ddwcwr_get_request_types() ) || 'withdrawal' === $type ) {
				$type = 'return';
			}

			$order_data = ddwcwr_order_adapter()->get_order_data( $order );

			if ( ! $this->request_helper->order_status_allows( $order_data, $type ) ) {
				wc_print_notice( __( 'This order cannot get this kind of request right now.', 'devdiggers-returns-for-woocommerce' ), 'notice' );
				return;
			}

			$items = $this->request_helper->get_eligible_items( $order, $type );

			// phpcs:disable WordPress.Security.NonceVerification.Missing -- Re-filling the form after a failed, already verified post.
			$posted = isset( $_POST['ddwcwr_items'] ) && is_array( $_POST['ddwcwr_items'] ) ? map_deep( wp_unslash( $_POST['ddwcwr_items'] ), 'sanitize_text_field' ) : [];
			$note   = isset( $_POST['ddwcwr_note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['ddwcwr_note'] ) ) : '';
			// phpcs:enable

			$this->template(
				'request-form',
				[
					'order'         => $order,
					'order_data'    => $order_data,
					'type'          => $type,
					'items'         => $items,
					'reasons'       => ddwcwr_get_reasons(),
					'conditions'    => ddwcwr_get_item_conditions(),
					'configuration' => $this->ddwcwr_configuration,
					'posted'        => $posted,
					'note'          => $note,
					'back_url'      => $back_url,
					'token_fields'  => is_user_logged_in() && ddwcwr_order_adapter()->order_belongs_to( $order, get_current_user_id() ) ? [] : $this->ddwcwr_get_token_fields(),
				]
			);
		}

		/**
		 * Hidden token fields carried from the URL into a guest form.
		 *
		 * @return array
		 */
		protected function ddwcwr_get_token_fields() {
			// phpcs:disable WordPress.Security.NonceVerification -- Values are re-validated as an HMAC on submit.
			$source = ! empty( $_POST['ddwcwr-token'] ) ? $_POST : $_GET;

			return [
				'ddwcwr-expires' => isset( $source['ddwcwr-expires'] ) ? absint( $source['ddwcwr-expires'] ) : 0,
				'ddwcwr-token'   => isset( $source['ddwcwr-token'] ) ? sanitize_text_field( wp_unslash( $source['ddwcwr-token'] ) ) : '',
			];
			// phpcs:enable
		}

		/**
		 * Render a request for the customer.
		 *
		 * @param array  $request Request.
		 * @param string $key Access key (guests).
		 * @return void
		 */
		protected function ddwcwr_render_request_view( $request, $key = '' ) {
			$order    = ddwcwr_order_adapter()->get_order( $request['order_id'] );
			$messages = $this->request_helper->get_messages( $request['id'], [ 'message', 'log' ] );
			$this->template(
				'request-view',
				[
					'request'       => $request,
					'order'         => $order,
					'items'         => $this->request_helper->get_request_items( $request['id'] ),
					'messages'      => array_values(
						array_filter(
							$messages,
							function ( $message ) {
								return 'message' === $message['kind'];
							}
						)
					),
					'access_key'    => $key ? $key : ( is_user_logged_in() ? '' : $request['access_key'] ),
					'label_url'     => \DDWCWPReturns\Helper\Label\DDWCWR_Label_Helper::get_label_url( $request ),
					'configuration' => $this->ddwcwr_configuration,
					'back_url'      => is_user_logged_in() && get_current_user_id() === (int) $request['customer_id'] ? ddwcwr_get_account_url() : ddwcwr_get_portal_url(),
				]
			);
		}

		/**
		 * Return/cancel/withdraw actions in the My Account orders list.
		 *
		 * @param array  $actions Actions.
		 * @param object $order Order.
		 * @return array
		 */
		public function ddwcwr_add_order_actions( $actions, $order ) {
			foreach ( $this->ddwcwr_get_order_request_links( $order ) as $type => $link ) {
				if ( in_array( $type, [ 'return', 'cancellation', 'withdrawal' ], true ) ) {
					$actions[ 'ddwcwr-' . $type ] = [
						'url'  => $link['url'],
						'name' => $link['label'],
					];
				}
			}

			return $actions;
		}

		/**
		 * Returns panel under the order details (view order, order received, guest portal).
		 *
		 * @param object $order Order.
		 * @return void
		 */
		public function ddwcwr_order_returns_panel( $order ) {
			$requests = $this->request_helper->get_order_requests( $order->get_id() );
			$links    = $this->ddwcwr_get_order_request_links( $order );

			if ( ! $requests && ! $links ) {
				return;
			}

			wp_enqueue_style( 'ddwcwr-front-style' );

			$this->template(
				'order-panel',
				[
					'order'    => $order,
					'requests' => $requests,
					'links'    => $links,
					'guest'    => ! ( is_user_logged_in() && ddwcwr_order_adapter()->order_belongs_to( $order, get_current_user_id() ) ),
				]
			);
		}

		/**
		 * Guest returns portal shortcode.
		 *
		 * @return string
		 */
		public function ddwcwr_portal_shortcode() {
			wp_enqueue_style( 'ddwcwr-front-style' );
			wp_enqueue_script( 'ddwcwr-front-script' );

			ob_start();

			echo '<div class="ddwcwr-portal ddwcwr-ui woocommerce">';

			wc_print_notices();

			// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only routing; access is checked by key or HMAC token.
			$request_id = isset( $_GET['ddwcwr-request'] ) ? absint( $_GET['ddwcwr-request'] ) : 0;
			$key        = isset( $_GET['ddwcwr-key'] ) ? sanitize_text_field( wp_unslash( $_GET['ddwcwr-key'] ) ) : '';
			$order_id   = isset( $_GET['ddwcwr-order'] ) ? absint( $_GET['ddwcwr-order'] ) : 0;
			$type       = isset( $_GET['ddwcwr-type'] ) ? sanitize_key( wp_unslash( $_GET['ddwcwr-type'] ) ) : '';
			// phpcs:enable

			if ( $request_id ) {
				$request = $this->request_helper->get_request( $request_id );

				if ( $this->request_helper->can_view( $request, $key ) ) {
					$this->ddwcwr_render_request_view( $request, $key );
				} else {
					wc_print_notice( __( 'This link is not valid. Look up your order below.', 'devdiggers-returns-for-woocommerce' ), 'error' );
					$this->template( 'portal-lookup' );
				}
			} elseif ( $order_id ) {
				$order = $this->ddwcwr_get_authorized_order( $order_id );

				if ( ! $order ) {
					wc_print_notice( __( 'This link has expired. Look up your order again.', 'devdiggers-returns-for-woocommerce' ), 'notice' );
					$this->template( 'portal-lookup' );
				} elseif ( $type ) {
					$this->ddwcwr_render_request_form( $order, $type, $this->ddwcwr_get_guest_order_url( $order ) );
				} else {
					$this->template(
						'portal-order',
						[
							'order'      => $order,
							'order_data' => ddwcwr_order_adapter()->get_order_data( $order ),
							'items'      => ddwcwr_order_adapter()->get_order_items( $order ),
						]
					);
					$this->ddwcwr_order_returns_panel( $order );
				}
			} elseif ( is_user_logged_in() ) {
				$this->template( 'portal-lookup', [ 'account_url' => ddwcwr_get_account_url() ] );
			} else {
				$this->template( 'portal-lookup' );
			}

			echo '</div>';

			return ob_get_clean();
		}

		/**
		 * EU withdrawal shortcode: step 1 (function), statement form, step 2 (confirm), acknowledgement.
		 *
		 * @return string
		 */
		public function ddwcwr_withdrawal_shortcode() {
			wp_enqueue_style( 'ddwcwr-front-style' );
			wp_enqueue_script( 'ddwcwr-front-script' );

			ob_start();

			echo '<div class="ddwcwr-withdrawal ddwcwr-ui woocommerce">';

			wc_print_notices();

			if ( 'yes' !== $this->ddwcwr_configuration['withdrawal_enabled'] ) {
				wc_print_notice( __( 'Online withdrawal is not available on this store. Please contact us.', 'devdiggers-returns-for-woocommerce' ), 'notice' );
				echo '</div>';

				return ob_get_clean();
			}

			// phpcs:disable WordPress.Security.NonceVerification -- Read-only routing; the confirmation is shown only with the secret key.
			$done     = isset( $_GET['ddwcwr-done'] ) ? absint( $_GET['ddwcwr-done'] ) : 0;
			$key      = isset( $_GET['ddwcwr-key'] ) ? sanitize_text_field( wp_unslash( $_GET['ddwcwr-key'] ) ) : '';
			$order_id = isset( $_GET['ddwcwr-order'] ) ? absint( $_GET['ddwcwr-order'] ) : 0;
			$step     = isset( $_GET['ddwcwr-step'] ) || $order_id || isset( $_POST['ddwcwr_action'] ) ? 'form' : 'start';
			// phpcs:enable

			if ( $done ) {
				$request = $this->request_helper->get_request( $done );

				if ( $request && 'withdrawal' === $request['type'] && $this->request_helper->can_view( $request, $key ) ) {
					$this->template(
						'withdrawal-done',
						[
							'request' => $request,
							'items'   => $this->request_helper->get_request_items( $request['id'] ),
						]
					);
					echo '</div>';

					return ob_get_clean();
				}
			}

			$prefill = [
				'name'   => '',
				'number' => '',
				'email'  => '',
			];
			$order   = $order_id ? ddwcwr_order_adapter()->get_order( $order_id ) : false;
			$items   = [];
			$orders  = [];

			if ( is_user_logged_in() ) {
				$user             = wp_get_current_user();
				$prefill['name']  = trim( $user->first_name . ' ' . $user->last_name ) ? trim( $user->first_name . ' ' . $user->last_name ) : $user->display_name;
				$prefill['email'] = $user->user_email;

				foreach ( ddwcwr_order_adapter()->get_customer_orders( $user->ID, 20 ) as $customer_order ) {
					if ( $this->request_helper->order_status_allows( ddwcwr_order_adapter()->get_order_data( $customer_order ), 'withdrawal' ) ) {
						$orders[ $customer_order->get_order_number() ] = $customer_order;
					}
				}
			}

			if ( $order && ( is_user_logged_in() ? ddwcwr_order_adapter()->order_belongs_to( $order, get_current_user_id() ) : false ) ) {
				$data              = ddwcwr_order_adapter()->get_order_data( $order );
				$prefill['name']   = $data['name'];
				$prefill['number'] = $data['number'];
				$prefill['email']  = $data['email'];
				$items             = $this->request_helper->get_eligible_items( $order, 'withdrawal' );
			}

			// phpcs:disable WordPress.Security.NonceVerification.Missing -- Re-filling after a failed, verified post.
			foreach ( [
				'name'   => 'ddwcwr_name',
				'number' => 'ddwcwr_order_number',
				'email'  => 'ddwcwr_email',
			] as $field => $post_key ) {
				if ( isset( $_POST[ $post_key ] ) ) {
					$prefill[ $field ] = sanitize_text_field( wp_unslash( $_POST[ $post_key ] ) );
				}
			}
			// phpcs:enable

			$this->template(
				'withdrawal-form',
				[
					'step'          => $step,
					'prefill'       => $prefill,
					'items'         => $items,
					'orders'        => $orders,
					'configuration' => $this->ddwcwr_configuration,
				]
			);

			echo '</div>';

			return ob_get_clean();
		}

		/**
		 * "Withdraw from contract here" in the footer.
		 *
		 * @return void
		 */
		public function ddwcwr_footer_withdrawal_link() {
			if ( 'yes' !== $this->ddwcwr_configuration['withdrawal_enabled'] || 'yes' !== $this->ddwcwr_configuration['withdrawal_footer_link'] || ! $this->ddwcwr_configuration['withdrawal_page_id'] ) {
				return;
			}

			printf(
				'<div class="ddwcwr-footer-withdrawal"><a href="%1$s">%2$s</a></div>',
				esc_url( add_query_arg( 'ddwcwr-step', 'form', ddwcwr_get_withdrawal_url() ) ),
				esc_html__( 'Withdraw from contract here', 'devdiggers-returns-for-woocommerce' )
			);
		}

		/**
		 * Withdrawal link in customer order emails for orders it applies to.
		 *
		 * @param object $order Order.
		 * @param bool   $sent_to_admin Admin email.
		 * @param bool   $plain_text Plain.
		 * @param object $email Email.
		 * @return void
		 */
		public function ddwcwr_email_withdrawal_link( $order, $sent_to_admin = false, $plain_text = false, $email = null ) {
			if ( $sent_to_admin || 'yes' !== $this->ddwcwr_configuration['withdrawal_in_emails'] || ! $order instanceof \WC_Order || ! $this->request_helper->order_status_allows( ddwcwr_order_adapter()->get_order_data( $order ), 'withdrawal' ) ) {
				return;
			}

			// Only the processing and completed order emails, as the setting says. Refund, note and invoice emails can go
			// out long after the withdrawal period, and DevDiggers Returns' own emails carry their own wording.
			if ( $email && ! in_array( (string) $email->id, [ 'customer_processing_order', 'customer_completed_order' ], true ) ) {
				return;
			}

			$order_data = ddwcwr_order_adapter()->get_order_data( $order );

			// The period runs from delivery (or from the order date): once it is over, offering the link would mislead.
			if ( $order_data['is_completed'] || 'created' === $this->ddwcwr_configuration['withdrawal_from'] ) {
				$window = $this->request_helper->get_window( $order_data, $this->ddwcwr_configuration['withdrawal_days'], $this->ddwcwr_configuration['withdrawal_from'] );

				if ( ! $window['open'] ) {
					return;
				}
			}

			$url = ddwcwr_get_withdrawal_url( $order->get_id() );
			/* translators: %d: number of days */
			$text = sprintf( __( 'You can withdraw from this contract within %d days of receiving your goods without giving any reason.', 'devdiggers-returns-for-woocommerce' ), absint( $this->ddwcwr_configuration['withdrawal_days'] ) );

			if ( $plain_text ) {
				echo "\n" . esc_html( $text ) . ' ' . esc_html__( 'Withdraw from contract here', 'devdiggers-returns-for-woocommerce' ) . ': ' . esc_url_raw( $url ) . "\n";
				return;
			}

			printf( '<p style="margin:16px 0;">%1$s <a href="%2$s">%3$s</a></p>', esc_html( $text ), esc_url( $url ), esc_html__( 'Withdraw from contract here', 'devdiggers-returns-for-woocommerce' ) );
		}

		/**
		 * Return policy, final sale and withdrawal exemption on the product page.
		 *
		 * @return void
		 */
		public function ddwcwr_product_policy_info() {
			global $product;

			if ( ! $product instanceof \WC_Product ) {
				return;
			}

			$lines = [];

			if ( 'yes' === $this->ddwcwr_configuration['show_policy_product'] ) {
				$lines[] = ddwcwr_is_final_sale( [ 'parent_id' => $product->get_id() ] ) ? [ 'final', __( 'Final sale: this item cannot be returned.', 'devdiggers-returns-for-woocommerce' ) ] : [ 'policy', ddwcwr_get_policy_line( $this->ddwcwr_configuration['return_window_days'] ) ];
			}

			if ( 'yes' === $this->ddwcwr_configuration['withdrawal_enabled'] && ddwcwr_is_withdrawal_excluded( [ 'parent_id' => $product->get_id() ] ) ) {
				$lines[] = [ 'final', __( 'This item is exempt from the statutory right of withdrawal.', 'devdiggers-returns-for-woocommerce' ) ];
			}

			if ( $lines ) {
				$this->template(
					'product-policy',
					[
						'lines'      => $lines,
						'policy_url' => $this->ddwcwr_configuration['policy_page_id'] ? get_permalink( $this->ddwcwr_configuration['policy_page_id'] ) : '',
					]
				);
			}
		}

		/**
		 * Checkout notice text.
		 *
		 * @return string
		 */
		protected function ddwcwr_get_checkout_notice() {
			if ( 'yes' !== $this->ddwcwr_configuration['show_policy_checkout'] ) {
				return '';
			}

			$parts = [];

			$parts[] = esc_html( ddwcwr_get_policy_line( $this->ddwcwr_configuration['return_window_days'] ) );

			if ( $this->ddwcwr_configuration['policy_page_id'] ) {
				$parts[] = '<a href="' . esc_url( get_permalink( $this->ddwcwr_configuration['policy_page_id'] ) ) . '" target="_blank">' . esc_html__( 'Return policy', 'devdiggers-returns-for-woocommerce' ) . '</a>';
			}

			if ( 'yes' === $this->ddwcwr_configuration['withdrawal_enabled'] && $this->ddwcwr_configuration['withdrawal_page_id'] ) {
				/* translators: 1: days, 2: link open, 3: link close */
				$parts[] = sprintf( esc_html__( 'You can withdraw from this contract within %1$d days of delivery using our %2$swithdrawal function%3$s.', 'devdiggers-returns-for-woocommerce' ), absint( $this->ddwcwr_configuration['withdrawal_days'] ), '<a href="' . esc_url( ddwcwr_get_withdrawal_url() ) . '" target="_blank">', '</a>' );
			}

			return $parts ? '<p class="ddwcwr-checkout-policy">' . implode( ' ', $parts ) . '</p>' : '';
		}

		/**
		 * Classic checkout notice.
		 *
		 * @return void
		 */
		public function ddwcwr_checkout_policy_notice() {
			echo wp_kses_post( $this->ddwcwr_get_checkout_notice() );
		}

		/**
		 * Block checkout notice (before the place order button).
		 *
		 * @param string $content Block HTML.
		 * @return string
		 */
		public function ddwcwr_checkout_block_policy_notice( $content ) {
			return wp_kses_post( $this->ddwcwr_get_checkout_notice() ) . $content;
		}
	}
}
