<?php
/**
 * Customer replied (admin) email
 *
 * @package WPReturns
 * @version 1.0.0
 */

namespace DDWCWPReturns\Includes\Emails;

defined( 'ABSPATH' ) || exit();

if ( ! class_exists( 'DDWCWR_Email_Admin_Customer_Reply' ) ) {
	/**
	 * Customer replied (admin) email class.
	 */
	class DDWCWR_Email_Admin_Customer_Reply extends DDWCWR_Email_Base {
		/**
		 * Construct
		 */
		public function __construct() {
			$this->id             = 'ddwcwr_admin_customer_reply';
			$this->customer_email = false;
			$this->title          = __( 'Customer replied (admin)', 'wpreturns' );
			$this->description    = __( 'Sent to the store when a customer adds a message to a request.', 'wpreturns' );

			add_action( 'ddwcwr_request_message_added_notification', [ $this, 'trigger' ], 10, 5 );

			parent::__construct();

			$this->recipient = $this->get_option( 'recipient', get_option( 'admin_email' ) );
		}

		/**
		 * Default subject.
		 *
		 * @return string
		 */
		public function get_default_subject() {
			return __( '[{site_title}] {customer_name} replied on {request_number}', 'wpreturns' );
		}

		/**
		 * Default heading.
		 *
		 * @return string
		 */
		public function get_default_heading() {
			return __( 'New reply on {request_number}', 'wpreturns' );
		}

		/**
		 * Trigger.
		 *
		 * @param int    $request_id Request ID.
		 * @param int    $message_id Message ID.
		 * @param string $author_type Author type.
		 * @param string $message Message.
		 * @return void
		 */
		public function trigger( $request_id, $message_id = 0, $author_type = '', $message = '' ) {
			if ( 'customer' !== $author_type || ! $this->setup_request( $request_id ) ) {
				$this->restore_locale();
				return;
			}

			$this->intro = __( 'The customer wrote:', 'wpreturns' );
			$this->note  = $message;
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
						'title'       => __( 'Recipient(s)', 'wpreturns' ),
						'type'        => 'text',
						/* translators: %s: admin email */
						'description' => sprintf( __( 'Comma separated. Defaults to %s.', 'wpreturns' ), '<code>' . esc_html( get_option( 'admin_email' ) ) . '</code>' ),
						'placeholder' => '',
						'default'     => '',
					],
				],
				$this->form_fields
			);
		}
	}
}
