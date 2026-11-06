<?php
/**
 * Return request message email
 *
 * @package DevDiggers Returns
 * @version 1.0.0
 */

namespace DDWCWPReturns\Includes\Emails;

defined( 'ABSPATH' ) || exit();

if ( ! class_exists( 'DDWCWR_Email_Customer_New_Message' ) ) {
	/**
	 * Return request message email class.
	 */
	class DDWCWR_Email_Customer_New_Message extends DDWCWR_Email_Base {
		/**
		 * Construct
		 */
		public function __construct() {
			$this->id             = 'ddwcwr_customer_new_message';
			$this->customer_email = true;
			$this->title          = __( 'Return request message', 'devdiggers-returns' );
			$this->description    = __( 'Sent to the customer when your team replies on a request.', 'devdiggers-returns' );

			add_action( 'ddwcwr_request_message_added_notification', [ $this, 'trigger' ], 10, 5 );

			parent::__construct();
		}

		/**
		 * Default subject.
		 *
		 * @return string
		 */
		public function get_default_subject() {
			return __( '[{site_title}] New message about request {request_number}', 'devdiggers-returns' );
		}

		/**
		 * Default heading.
		 *
		 * @return string
		 */
		public function get_default_heading() {
			return __( 'New message about {request_number}', 'devdiggers-returns' );
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
			if ( 'staff' !== $author_type || ! $this->setup_request( $request_id ) ) {
				$this->restore_locale();
				return;
			}

			$this->intro = __( 'We sent you a message about your request:', 'devdiggers-returns' );
			$this->note  = $message;
			$this->dispatch();
		}
	}
}
