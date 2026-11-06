<?php
/**
 * Return request rejected email
 *
 * @package DevDiggers Returns
 * @version 1.0.0
 */

namespace DDWCWPReturns\Includes\Emails;

defined( 'ABSPATH' ) || exit();

if ( ! class_exists( 'DDWCWR_Email_Customer_Request_Rejected' ) ) {
	/**
	 * Return request rejected email class.
	 */
	class DDWCWR_Email_Customer_Request_Rejected extends DDWCWR_Email_Base {
		/**
		 * Construct
		 */
		public function __construct() {
			$this->id             = 'ddwcwr_customer_request_rejected';
			$this->customer_email = true;
			$this->title          = __( 'Return request rejected', 'devdiggers-returns-for-woocommerce' );
			$this->description    = __( 'Sent to the customer when a request is rejected, with the reason you give.', 'devdiggers-returns-for-woocommerce' );

			add_action( 'ddwcwr_request_status_changed_notification', [ $this, 'trigger' ], 10, 5 );

			parent::__construct();
		}

		/**
		 * Default subject.
		 *
		 * @return string
		 */
		public function get_default_subject() {
			return __( '[{site_title}] Update on your request {request_number}', 'devdiggers-returns-for-woocommerce' );
		}

		/**
		 * Default heading.
		 *
		 * @return string
		 */
		public function get_default_heading() {
			return __( 'Request {request_number} could not be accepted', 'devdiggers-returns-for-woocommerce' );
		}

		/**
		 * Trigger.
		 *
		 * @param int    $request_id Request ID.
		 * @param string $old Old status.
		 * @param string $status New status.
		 * @param array  $request Request.
		 * @param array  $args Arguments.
		 * @return void
		 */
		public function trigger( $request_id, $old = '', $status = '', $request = [], $args = [] ) {
			if ( 'rejected' !== $status || ( isset( $args['notify'] ) && ! $args['notify'] ) || ! $this->setup_request( $request_id ) ) {
				$this->restore_locale();
				return;
			}

			$this->intro = __( 'We are sorry, we could not accept this request.', 'devdiggers-returns-for-woocommerce' );
			$this->note  = ! empty( $args['note'] ) ? $args['note'] : '';
			$this->dispatch();
		}
	}
}
