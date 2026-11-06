<?php
/**
 * Label helper: attaches the return label the store uploads and keeps a private copy.
 *
 * @package DevDiggers Returns
 * @version 1.0.0
 */

namespace DDWCWPReturns\Helper\Label;

use DDWCWPReturns\Helper\Request\DDWCWR_Request_Helper;
use DDWCWPReturns\Helper\Upload\DDWCWR_Upload_Helper;

defined( 'ABSPATH' ) || exit();

if ( ! class_exists( 'DDWCWR_Label_Helper' ) ) {
	/**
	 * Label helper class
	 */
	class DDWCWR_Label_Helper {
		/**
		 * Request helper.
		 *
		 * @var DDWCWR_Request_Helper
		 */
		protected $request_helper;

		/**
		 * Construct
		 */
		public function __construct() {
			$this->request_helper = new DDWCWR_Request_Helper();
		}

		/**
		 * Attach an uploaded label, move the request to "awaiting return" and email the customer.
		 *
		 * @param int   $request_id Request ID.
		 * @param array $file Stored file (from the upload helper).
		 * @return true|\WP_Error
		 */
		public function attach_label( $request_id, $file ) {
			$request = $this->request_helper->get_request( $request_id );

			if ( ! $request ) {
				return new \WP_Error( 'ddwcwr_request', __( 'Request not found.', 'devdiggers-returns' ) );
			}

			if ( $request['label_file'] && $request['label_file'] !== $file['key'] ) {
				( new DDWCWR_Upload_Helper() )->delete_files( [ [ 'key' => $request['label_file'] ] ] );
			}

			$this->request_helper->update_request(
				$request_id,
				[
					'label_file'     => $file['key'],
					'label_url'      => '',
					'label_provider' => 'manual',
					'meta'           => array_merge( $request['meta'], [ 'label_created' => current_time( 'mysql' ) ] ),
				]
			);

			$this->request_helper->log( $request_id, __( 'Return label uploaded.', 'devdiggers-returns' ) );

			if ( in_array( $request['status'], [ 'pending', 'approved' ], true ) ) {
				$this->request_helper->change_status( $request_id, 'awaiting_return', [ 'notify' => false ] );
			}

			do_action( 'ddwcwr_label_ready', $request_id, false );

			return true;
		}

		/**
		 * Customer-facing label URL. Labels a Pro install bought from a provider keep their provider link.
		 *
		 * @param array $request Request.
		 * @return string
		 */
		public static function get_label_url( $request ) {
			if ( $request['label_file'] ) {
				return add_query_arg(
					[
						'action'  => 'ddwcwr_file',
						'request' => $request['id'],
						'file'    => $request['label_file'],
						'key'     => $request['access_key'],
					],
					admin_url( 'admin-post.php' )
				);
			}

			return $request['label_url'];
		}
	}
}
