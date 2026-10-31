<?php
/**
 * Withdrawal acknowledgement email
 *
 * @package WPReturns
 * @version 1.0.0
 */

namespace DDWCWPReturns\Includes\Emails;

defined( 'ABSPATH' ) || exit();

if ( ! class_exists( 'DDWCWR_Email_Customer_Withdrawal_Acknowledgement' ) ) {
	/**
	 * Withdrawal acknowledgement email class.
	 */
	class DDWCWR_Email_Customer_Withdrawal_Acknowledgement extends DDWCWR_Email_Base {
		/**
		 * Construct
		 */
		public function __construct() {
			$this->id             = 'ddwcwr_customer_withdrawal_acknowledgement';
			$this->customer_email = true;
			$this->title          = __( 'Withdrawal acknowledgement', 'wpreturns' );
			$this->description    = __( 'Sent to the customer right after an EU withdrawal is submitted: the acknowledgement of receipt on a durable medium, with the content of the statement and the date and time it was sent (Article 11a, Directive 2011/83/EU).', 'wpreturns' );

			add_action( 'ddwcwr_request_created_notification', [ $this, 'trigger' ], 10, 5 );

			parent::__construct();
		}

		/**
		 * Default subject.
		 *
		 * @return string
		 */
		public function get_default_subject() {
			return __( '[{site_title}] Confirmation of your withdrawal for order {order_number}', 'wpreturns' );
		}

		/**
		 * Default heading.
		 *
		 * @return string
		 */
		public function get_default_heading() {
			return __( 'We received your withdrawal', 'wpreturns' );
		}

		/**
		 * Trigger.
		 *
		 * @param int $request_id Request ID.
		 * @return void
		 */
		public function trigger( $request_id ) {
			if ( ! $this->setup_request( $request_id ) || 'withdrawal' !== $this->request['type'] ) {
				$this->restore_locale();
				return;
			}

			$created = strtotime( get_gmt_from_date( $this->request['created'] ) . ' UTC' );
			$items   = ( new \DDWCWPReturns\Helper\Request\DDWCWR_Request_Helper() )->get_request_items( $request_id );
			$goods   = implode(
				', ',
				array_map(
					function ( $item ) {
						return $item['name'] . ' × ' . $item['qty'];
					},
					$items
				)
			);

			$this->intro = __( 'This confirms that we received your statement withdrawing from the contract below. Please keep this email as your record.', 'wpreturns' );
			/* translators: 1: goods, 2: order number */
			$this->note = sprintf( __( 'I hereby give notice that I withdraw from my contract for the sale of the following goods: %1$s (order #%2$s).', 'wpreturns' ), $goods, $this->placeholders['{order_number}'] );

			$this->extra = [
				__( 'Name', 'wpreturns' )      => esc_html( $this->request['customer_name'] ),
				__( 'Email for confirmation', 'wpreturns' ) => esc_html( $this->request['customer_email'] ),
				__( 'Order', 'wpreturns' )     => esc_html( '#' . $this->placeholders['{order_number}'] . ' (' . $this->placeholders['{order_date}'] . ')' ),
				__( 'Submitted', 'wpreturns' ) => esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) . ' T', $created ) . ' (' . gmdate( 'Y-m-d H:i:s', $created ) . ' UTC)' ),
				__( 'Reference', 'wpreturns' ) => esc_html( $this->request['number'] ),
			];

			$this->dispatch();
		}
	}
}
