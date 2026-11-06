<?php
/**
 * Base class of every DevDiggers Returns email.
 *
 * @package DevDiggers Returns
 * @version 1.0.0
 */

namespace DDWCWPReturns\Includes\Emails;

use DDWCWPReturns\Helper\Request\DDWCWR_Request_Helper;

defined( 'ABSPATH' ) || exit();

if ( ! class_exists( 'DDWCWR_Email_Base' ) ) {
	/**
	 * Email base class.
	 */
	abstract class DDWCWR_Email_Base extends \WC_Email {
		/**
		 * Request.
		 *
		 * @var array
		 */
		public $request = [];

		/**
		 * Text shown above the request summary.
		 *
		 * @var string
		 */
		public $intro = '';

		/**
		 * Extra rows (label => html) or a quoted note.
		 *
		 * @var array
		 */
		public $extra = [];

		/**
		 * Quoted message.
		 *
		 * @var string
		 */
		public $note = '';

		/**
		 * Button [ url, label ].
		 *
		 * @var array
		 */
		public $button = [];

		/**
		 * Sample items for the WooCommerce email preview.
		 *
		 * @var array|null
		 */
		public $preview_items = null;

		/**
		 * Construct
		 */
		public function __construct() {
			$this->template_html  = 'emails/request.php';
			$this->template_plain = 'emails/plain/request.php';
			$this->template_base  = DDWCWR_PLUGIN_FILE . 'templates/';
			$this->placeholders   = array_merge(
				$this->placeholders,
				[
					'{request_number}' => '',
					'{order_number}'   => '',
					'{status}'         => '',
					'{customer_name}'  => '',
				]
			);

			parent::__construct();
		}

		/**
		 * Load the request and fill placeholders.
		 *
		 * @param int $request_id Request ID.
		 * @return bool Whether the request exists.
		 */
		protected function setup_request( $request_id ) {
			$this->setup_locale();

			$helper        = new DDWCWR_Request_Helper();
			$this->request = $helper->get_request( $request_id );
			$this->extra   = [];
			$this->note    = '';
			$this->button  = [];

			if ( ! $this->request ) {
				return false;
			}

			$order = ddwcwr_order_adapter()->get_order( $this->request['order_id'] );

			$this->object                           = $order;
			$this->placeholders['{request_number}'] = $this->request['number'];
			$this->placeholders['{order_number}']   = $order ? $order->get_order_number() : $this->request['order_id'];
			$this->placeholders['{status}']         = ddwcwr_get_status_label( $this->request['status'] );
			$this->placeholders['{customer_name}']  = $this->request['customer_name'];
			$this->placeholders['{order_date}']     = $order && $order->get_date_created() ? wc_format_datetime( $order->get_date_created() ) : '';

			if ( ! $this->is_customer_email() ) {
				$this->button = [ admin_url( 'admin.php?page=ddwcwr-dashboard&menu=requests&request-id=' . absint( $this->request['id'] ) ), __( 'Open request', 'devdiggers-returns-for-woocommerce' ) ];
			} else {
				$this->recipient = $this->request['customer_email'];
				$this->button    = [ ddwcwr_get_request_view_url( $this->request, empty( $this->request['customer_id'] ) ), __( 'View your request', 'devdiggers-returns-for-woocommerce' ) ];
			}

			return true;
		}

		/**
		 * Whether the request is still valid to send for.
		 *
		 * @return bool
		 */
		protected function can_send() {
			return $this->request && $this->is_enabled() && $this->get_recipient();
		}

		/**
		 * Send and restore the locale.
		 *
		 * @return void
		 */
		protected function dispatch() {
			$this->apply_custom_message();

			if ( $this->can_send() ) {
				$this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
			}

			$this->restore_locale();
		}

		/**
		 * Template arguments.
		 *
		 * @param bool $plain Plain text.
		 * @return array
		 */
		protected function get_template_args( $plain ) {
			$helper = new DDWCWR_Request_Helper();

			return [
				'email'              => $this,
				'email_heading'      => $this->get_heading(),
				'request'            => $this->request,
				'items'              => null !== $this->preview_items ? $this->preview_items : ( $this->request ? $helper->get_request_items( $this->request['id'] ) : [] ),
				'intro'              => $this->intro,
				'note'               => $this->note,
				'extra'              => $this->extra,
				'button'             => $this->button,
				'additional_content' => $this->get_additional_content(),
				'sent_to_admin'      => ! $this->is_customer_email(),
				'plain_text'         => $plain,
			];
		}

		/**
		 * Settings: the standard fields plus an editable message.
		 *
		 * @return void
		 */
		public function init_form_fields() {
			parent::init_form_fields();

			$fields = [];

			foreach ( $this->form_fields as $key => $field ) {
				$fields[ $key ] = $field;

				if ( 'heading' === $key ) {
					$fields['intro'] = [
						'title'       => __( 'Message', 'devdiggers-returns-for-woocommerce' ),
						'type'        => 'textarea',
						'css'         => 'width:400px; height:75px;',
						'placeholder' => __( 'Leave empty to use the built-in text, which adapts to the request (return, cancellation, withdrawal).', 'devdiggers-returns-for-woocommerce' ),
						'description' => $this->placeholder_text(),
						'desc_tip'    => false,
						'default'     => '',
					];
				}
			}

			$this->form_fields = $fields;
		}

		/**
		 * Use the message from the email settings when the store wrote one.
		 *
		 * @return void
		 */
		protected function apply_custom_message() {
			$message = trim( (string) $this->get_option( 'intro', '' ) );

			if ( '' !== $message ) {
				$this->intro = $this->format_string( $message );
			}
		}

		/**
		 * Fill the email with a sample request for the WooCommerce email preview.
		 *
		 * @return $this
		 */
		public function prepare_preview() {
			$this->request = [
				'id'             => 0,
				'number'         => ddwcwr_get_request_number( 1001 ),
				'order_id'       => 0,
				'type'           => 'return',
				'resolution'     => 'refund',
				'status'         => 'approved',
				'customer_id'    => 0,
				'customer_name'  => 'John Doe',
				'customer_email' => 'john@example.com',
				'access_key'     => '',
				'items_total'    => 49,
				'created'        => current_time( 'mysql' ),
				'meta'           => [],
			];

			$this->preview_items = [
				[
					'name'       => __( 'Sample product', 'devdiggers-returns-for-woocommerce' ),
					'qty'        => 1,
					'reason'     => 'wrong-size',
					'line_total' => 49,
				],
			];

			$this->placeholders['{request_number}'] = $this->request['number'];
			$this->placeholders['{status}']         = ddwcwr_get_status_label( $this->request['status'] );
			$this->placeholders['{customer_name}']  = $this->request['customer_name'];

			if ( $this->object && is_callable( [ $this->object, 'get_order_number' ] ) ) {
				$this->placeholders['{order_number}'] = $this->object->get_order_number();
			}

			$this->intro  = __( 'This is a preview with a sample request. The real email explains the next step for the customer\'s request.', 'devdiggers-returns-for-woocommerce' );
			$this->button = [ home_url( '/' ), $this->is_customer_email() ? __( 'View your request', 'devdiggers-returns-for-woocommerce' ) : __( 'Open request', 'devdiggers-returns-for-woocommerce' ) ];
			$this->apply_custom_message();

			return $this;
		}

		/**
		 * HTML content.
		 *
		 * @return string
		 */
		public function get_content_html() {
			return wc_get_template_html( $this->template_html, $this->get_template_args( false ), '', $this->template_base );
		}

		/**
		 * Plain content.
		 *
		 * @return string
		 */
		public function get_content_plain() {
			return wc_get_template_html( $this->template_plain, $this->get_template_args( true ), '', $this->template_base );
		}

		/**
		 * Placeholder list for the settings screen.
		 *
		 * @return string
		 */
		protected function placeholder_text() {
			/* translators: %s: list of placeholders */
			return sprintf( __( 'Available placeholders: %s', 'devdiggers-returns-for-woocommerce' ), '<code>' . implode( '</code>, <code>', array_keys( $this->placeholders ) ) . '</code>' );
		}
	}
}
