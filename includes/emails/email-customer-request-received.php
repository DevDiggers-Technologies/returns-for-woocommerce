<?php
/**
 * Return request received email
 *
 * @package DevDiggers Returns
 * @version 1.0.0
 */

namespace DDWCWPReturns\Includes\Emails;

defined( 'ABSPATH' ) || exit();

if ( ! class_exists( 'DDWCWR_Email_Customer_Request_Received' ) ) {
	/**
	 * Return request received email class.
	 */
	class DDWCWR_Email_Customer_Request_Received extends DDWCWR_Email_Base {
		/**
		 * Construct
		 */
		public function __construct() {
			$this->id             = 'ddwcwr_customer_request_received';
			$this->customer_email = true;
			$this->title          = __( 'Return request received', 'devdiggers-returns-for-woocommerce' );
			$this->description    = __( 'Sent to the customer when a return or cancellation request is submitted.', 'devdiggers-returns-for-woocommerce' );

			add_action( 'ddwcwr_request_created_notification', [ $this, 'trigger' ], 10, 5 );

			parent::__construct();
		}

		/**
		 * Default subject.
		 *
		 * @return string
		 */
		public function get_default_subject() {
			return __( '[{site_title}] We received your request {request_number}', 'devdiggers-returns-for-woocommerce' );
		}

		/**
		 * Default heading.
		 *
		 * @return string
		 */
		public function get_default_heading() {
			return __( 'Request {request_number} received', 'devdiggers-returns-for-woocommerce' );
		}

		/**
		 * Trigger.
		 *
		 * @param int $request_id Request ID.
		 * @return void
		 */
		public function trigger( $request_id ) {
			if ( ! $this->setup_request( $request_id ) || 'withdrawal' === $this->request['type'] ) {
				$this->restore_locale();
				return;
			}

			$this->intro = __( 'Thanks, we have your request and will review it shortly. Here is what you asked for:', 'devdiggers-returns-for-woocommerce' );
			$this->dispatch();
		}
	}
}
