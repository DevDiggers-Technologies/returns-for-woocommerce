<?php
/**
 * Return request approved email
 *
 * @package WPReturns
 * @version 1.0.0
 */

namespace DDWCWPReturns\Includes\Emails;

defined( 'ABSPATH' ) || exit();

if ( ! class_exists( 'DDWCWR_Email_Customer_Request_Approved' ) ) {
	/**
	 * Return request approved email class.
	 */
	class DDWCWR_Email_Customer_Request_Approved extends DDWCWR_Email_Base {
		/**
		 * Construct
		 */
		public function __construct() {
			$this->id             = 'ddwcwr_customer_request_approved';
			$this->customer_email = true;
			$this->title          = __( 'Return request approved', 'wpreturns' );
			$this->description    = __( 'Sent to the customer when a request is approved.', 'wpreturns' );

			add_action( 'ddwcwr_request_status_changed_notification', [ $this, 'trigger' ], 10, 5 );

			parent::__construct();
		}

		/**
		 * Default subject.
		 *
		 * @return string
		 */
		public function get_default_subject() {
			return __( '[{site_title}] Your request {request_number} is approved', 'wpreturns' );
		}

		/**
		 * Default heading.
		 *
		 * @return string
		 */
		public function get_default_heading() {
			return __( 'Good news: request {request_number} is approved', 'wpreturns' );
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
			if ( 'approved' !== $status || ( isset( $args['notify'] ) && ! $args['notify'] ) || ! $this->setup_request( $request_id ) ) {
				$this->restore_locale();
				return;
			}

			if ( 'cancellation' === $this->request['type'] ) {
				$this->intro = __( 'Your cancellation request is approved. We have stopped the order and any payment will be refunded.', 'wpreturns' );
			} else {
				$this->intro = __( 'Your request is approved. Please pack the items below securely; we will send your return label or instructions shortly if they are not included here.', 'wpreturns' );
			}

			$this->note = ! empty( $args['note'] ) ? $args['note'] : '';
			$this->dispatch();
		}
	}
}
