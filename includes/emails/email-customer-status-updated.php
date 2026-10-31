<?php
/**
 * Return status updated email
 *
 * @package WPReturns
 * @version 1.0.0
 */

namespace DDWCWPReturns\Includes\Emails;

defined( 'ABSPATH' ) || exit();

if ( ! class_exists( 'DDWCWR_Email_Customer_Status_Updated' ) ) {
	/**
	 * Return status updated email class.
	 */
	class DDWCWR_Email_Customer_Status_Updated extends DDWCWR_Email_Base {
		/**
		 * Construct
		 */
		public function __construct() {
			$this->id             = 'ddwcwr_customer_status_updated';
			$this->customer_email = true;
			$this->title          = __( 'Return status updated', 'wpreturns' );
			$this->description    = __( 'Sent to the customer when a request moves to any status marked “Notify customer” (other than approved and rejected, which have their own emails).', 'wpreturns' );

			add_action( 'ddwcwr_request_status_changed_notification', [ $this, 'trigger' ], 10, 5 );

			parent::__construct();
		}

		/**
		 * Default subject.
		 *
		 * @return string
		 */
		public function get_default_subject() {
			return __( '[{site_title}] Request {request_number} is now {status}', 'wpreturns' );
		}

		/**
		 * Default heading.
		 *
		 * @return string
		 */
		public function get_default_heading() {
			return __( 'Request {request_number}: {status}', 'wpreturns' );
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
			$statuses = ddwcwr_get_statuses();

			if ( in_array( $status, [ 'pending', 'approved', 'rejected' ], true ) || empty( $statuses[ $status ]['notify'] ) || ( isset( $args['notify'] ) && ! $args['notify'] ) || ! $this->setup_request( $request_id ) ) {
				$this->restore_locale();
				return;
			}

			// The resolution email already told the customer the outcome.
			if ( 'completed' === $status && ! empty( $this->request['meta']['processed'] ) ) {
				$this->restore_locale();
				return;
			}

			/* translators: %s: status */
			$this->intro = sprintf( __( 'Your request is now: %s.', 'wpreturns' ), ddwcwr_get_status_label( $status ) );

			if ( 'received' === $status ) {
				$this->intro = __( 'Your return has arrived and we are checking it now.', 'wpreturns' );
			}

			$this->note = ! empty( $args['note'] ) ? $args['note'] : '';
			$this->dispatch();
		}
	}
}
