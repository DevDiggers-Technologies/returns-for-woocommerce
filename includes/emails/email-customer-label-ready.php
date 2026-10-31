<?php
/**
 * Return label ready email
 *
 * @package WPReturns
 * @version 1.0.0
 */

namespace DDWCWPReturns\Includes\Emails;

defined( 'ABSPATH' ) || exit();

if ( ! class_exists( 'DDWCWR_Email_Customer_Label_Ready' ) ) {
	/**
	 * Return label ready email class.
	 */
	class DDWCWR_Email_Customer_Label_Ready extends DDWCWR_Email_Base {
		/**
		 * Construct
		 */
		public function __construct() {
			$this->id             = 'ddwcwr_customer_label_ready';
			$this->customer_email = true;
			$this->title          = __( 'Return label ready', 'wpreturns' );
			$this->description    = __( 'Sent to the customer when a return label is added, and as a reminder when the label has not been used.', 'wpreturns' );

			add_action( 'ddwcwr_label_ready_notification', [ $this, 'trigger' ], 10, 5 );

			parent::__construct();
		}

		/**
		 * Default subject.
		 *
		 * @return string
		 */
		public function get_default_subject() {
			return __( '[{site_title}] Your return label for {request_number}', 'wpreturns' );
		}

		/**
		 * Default heading.
		 *
		 * @return string
		 */
		public function get_default_heading() {
			return __( 'Your return label is ready', 'wpreturns' );
		}

		/**
		 * Trigger.
		 *
		 * @param int  $request_id Request ID.
		 * @param bool $reminder Reminder.
		 * @return void
		 */
		public function trigger( $request_id, $reminder = false ) {
			if ( ! $this->setup_request( $request_id ) ) {
				$this->restore_locale();
				return;
			}

			$this->intro = $reminder ? __( 'Just a reminder: your return label is ready and we have not seen the package moving yet. Print the label, attach it to the package and drop it off.', 'wpreturns' ) : __( 'Print the label, attach it to your package and drop it off with the carrier.', 'wpreturns' );

			if ( $this->request['tracking_number'] ) {
				$this->extra[ __( 'Tracking number', 'wpreturns' ) ] = esc_html( trim( $this->request['carrier'] . ' ' . $this->request['tracking_number'] ) );
			}

			$label        = \DDWCWPReturns\Helper\Label\DDWCWR_Label_Helper::get_label_url( $this->request );
			$this->button = $label ? [ $label, __( 'Download return label', 'wpreturns' ) ] : $this->button;
			$this->dispatch();
		}
	}
}
