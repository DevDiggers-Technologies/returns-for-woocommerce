<?php
/**
 * Return resolved email
 *
 * @package DevDiggers Returns
 * @version 1.0.0
 */

namespace DDWCWPReturns\Includes\Emails;

defined( 'ABSPATH' ) || exit();

if ( ! class_exists( 'DDWCWR_Email_Customer_Resolution' ) ) {
	/**
	 * Return resolved email class.
	 */
	class DDWCWR_Email_Customer_Resolution extends DDWCWR_Email_Base {
		/**
		 * Construct
		 */
		public function __construct() {
			$this->id             = 'ddwcwr_customer_resolution';
			$this->customer_email = true;
			$this->title          = __( 'Return resolved', 'devdiggers-returns' );
			$this->description    = __( 'Sent to the customer when the refund or cancellation is done.', 'devdiggers-returns' );

			add_action( 'ddwcwr_resolution_processed_notification', [ $this, 'trigger' ], 10, 5 );

			parent::__construct();
		}

		/**
		 * Default subject.
		 *
		 * @return string
		 */
		public function get_default_subject() {
			return __( '[{site_title}] Request {request_number} is resolved', 'devdiggers-returns' );
		}

		/**
		 * Default heading.
		 *
		 * @return string
		 */
		public function get_default_heading() {
			return __( 'Your request is resolved', 'devdiggers-returns' );
		}

		/**
		 * Trigger.
		 *
		 * @param int   $request_id Request ID.
		 * @param array $result Result.
		 * @return void
		 */
		public function trigger( $request_id, $result = [] ) {
			if ( ! $this->setup_request( $request_id ) ) {
				$this->restore_locale();
				return;
			}

			$currency = [ 'currency' => $this->object ? $this->object->get_currency() : get_woocommerce_currency() ];

			if ( 'cancellation' === $this->request['type'] && empty( $result['amount'] ) ) {
				$this->intro = __( 'Your order has been canceled.', 'devdiggers-returns' );
			} else {
				$this->intro                                  = __( 'Your refund has been issued. Depending on your bank it can take a few days to appear.', 'devdiggers-returns' );
				$this->extra[ __( 'Refunded', 'devdiggers-returns' ) ] = wc_price( isset( $result['amount'] ) ? $result['amount'] : 0, $currency );
			}

			$this->dispatch();
		}
	}
}
