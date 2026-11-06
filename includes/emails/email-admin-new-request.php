<?php
/**
 * New return request (admin) email
 *
 * @package DevDiggers Returns
 * @version 1.0.0
 */

namespace DDWCWPReturns\Includes\Emails;

defined( 'ABSPATH' ) || exit();

if ( ! class_exists( 'DDWCWR_Email_Admin_New_Request' ) ) {
	/**
	 * New return request (admin) email class.
	 */
	class DDWCWR_Email_Admin_New_Request extends DDWCWR_Email_Base {
		/**
		 * Construct
		 */
		public function __construct() {
			$this->id             = 'ddwcwr_admin_new_request';
			$this->customer_email = false;
			$this->title          = __( 'New return request (admin)', 'devdiggers-returns' );
			$this->description    = __( 'Sent to the store when a customer submits a request or withdrawal.', 'devdiggers-returns' );

			add_action( 'ddwcwr_request_created_notification', [ $this, 'trigger' ], 10, 5 );

			parent::__construct();

			$this->recipient = $this->get_option( 'recipient', get_option( 'admin_email' ) );
		}

		/**
		 * Default subject.
		 *
		 * @return string
		 */
		public function get_default_subject() {
			return __( '[{site_title}] New request {request_number} for order #{order_number}', 'devdiggers-returns' );
		}

		/**
		 * Default heading.
		 *
		 * @return string
		 */
		public function get_default_heading() {
			return __( 'New request {request_number}', 'devdiggers-returns' );
		}

		/**
		 * Trigger.
		 *
		 * @param int $request_id Request ID.
		 * @return void
		 */
		public function trigger( $request_id ) {
			if ( ! $this->setup_request( $request_id ) ) {
				$this->restore_locale();
				return;
			}

			$types = ddwcwr_get_request_types();
			/* translators: 1: customer name, 2: request type */
			$this->intro = sprintf( __( '%1$s submitted a new %2$s request.', 'devdiggers-returns' ), $this->request['customer_name'], strtolower( $types[ $this->request['type'] ] ) );
			$this->note  = $this->request['customer_note'];

			$this->dispatch();
		}

		/**
		 * Settings fields (adds the recipient).
		 *
		 * @return void
		 */
		public function init_form_fields() {
			parent::init_form_fields();

			$this->form_fields = array_merge(
				[
					'enabled'   => $this->form_fields['enabled'],
					'recipient' => [
						'title'       => __( 'Recipient(s)', 'devdiggers-returns' ),
						'type'        => 'text',
						/* translators: %s: admin email */
						'description' => sprintf( __( 'Comma separated. Defaults to %s.', 'devdiggers-returns' ), '<code>' . esc_html( get_option( 'admin_email' ) ) . '</code>' ),
						'placeholder' => '',
						'default'     => '',
					],
				],
				$this->form_fields
			);
		}
	}
}
