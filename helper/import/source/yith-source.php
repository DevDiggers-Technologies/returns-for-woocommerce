<?php
/**
 * Import source: YITH Advanced Refund System for WooCommerce (free and Premium).
 *
 * Requests are yith_refund_request posts with _ywcars_* meta. The conversation is in {prefix}ywcars_messages; its
 * first message is the customer's reason. Message dates come from MySQL NOW(), so they are in the database clock.
 *
 * @package DevDiggers Returns
 * @version 1.0.0
 */

namespace DDWCWPReturns\Helper\Import\Source;

use DDWCWPReturns\Helper\Import\DDWCWR_Import_Source;

defined( 'ABSPATH' ) || exit();

if ( ! class_exists( 'DDWCWR_Yith_Source' ) ) {
	/**
	 * YITH source class
	 */
	class DDWCWR_Yith_Source extends DDWCWR_Import_Source {
		/**
		 * Label.
		 *
		 * @return string
		 */
		public function get_label() {
			return __( 'YITH Advanced Refund System', 'devdiggers-returns-for-woocommerce' );
		}

		/**
		 * Vendor.
		 *
		 * @return string
		 */
		public function get_vendor() {
			return 'YITH';
		}

		/**
		 * Request types it holds.
		 *
		 * @return array
		 */
		public function get_types() {
			return [ 'return' ];
		}

		/**
		 * Plugin folders (free and paid).
		 *
		 * @return array
		 */
		public function get_plugin_dirs() {
			return [ 'yith-advanced-refund-system-for-woocommerce', 'yith-advanced-refund-system-for-woocommerce-premium' ];
		}

		/**
		 * Count.
		 *
		 * @return int
		 */
		public function count() {
			global $wpdb;

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'yith_refund_request' AND post_status NOT IN ( 'trash', 'auto-draft' )" );
		}

		/**
		 * Read a batch.
		 *
		 * @param string $cursor Last post ID.
		 * @return array
		 */
		public function read( $cursor ) {
			global $wpdb;

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$posts    = $wpdb->get_results( $wpdb->prepare( "SELECT ID, post_status, post_date_gmt FROM {$wpdb->posts} WHERE post_type = 'yith_refund_request' AND post_status NOT IN ( 'trash', 'auto-draft' ) AND ID > %d ORDER BY ID ASC LIMIT %d", absint( $cursor ), self::BATCH ) );
			$requests = [];
			$last     = '';

			foreach ( $posts as $post ) {
				$last  = (string) $post->ID;
				$order = $this->get_order( get_post_meta( $post->ID, '_ywcars_order_id', true ) );

				if ( $order ) {
					$requests[] = $this->build( $post, $order );
				}
			}

			return [
				'requests' => $requests,
				'cursor'   => $last,
			];
		}

		/**
		 * Build a request.
		 *
		 * @param object    $post  Request post.
		 * @param \WC_Order $order Order.
		 * @return array
		 */
		protected function build( $post, $order ) {
			$map    = [
				'ywcars-new'        => 'pending',
				'ywcars-on-hold'    => 'pending',
				'ywcars-processing' => 'approved',
				'ywcars-approved'   => 'completed',
				'ywcars-coupon'     => 'completed',
				'ywcars-rejected'   => 'rejected',
			];
			$status = isset( $map[ $post->post_status ] ) ? $map[ $post->post_status ] : 'pending';

			// The shop closed the request without approving it.
			if ( 'pending' === $status || 'approved' === $status ) {
				$status = get_post_meta( $post->ID, '_ywcars_is_closed', true ) ? 'cancelled' : $status;
			}

			$items   = [];
			$item_id = absint( get_post_meta( $post->ID, '_ywcars_item_id', true ) );

			if ( ! get_post_meta( $post->ID, '_ywcars_whole_order', true ) && $item_id ) {
				$items[ $item_id ] = [ 'qty' => max( 1, absint( get_post_meta( $post->ID, '_ywcars_qty', true ) ) ) ];
			}

			$messages = $this->read_messages( $post->ID, $order );
			$created  = $this->from_gmt( $post->post_date_gmt );
			$note     = '';

			// The first customer message is the reason they gave on the request form.
			if ( $messages && 'customer' === $messages[0]['author_type'] ) {
				$note = array_shift( $messages )['message'];
			}

			$refunded = get_post_meta( $post->ID, '_ywcars_refunded', true );

			return $this->request(
				[
					'key'       => 'yith:' . $post->ID,
					'order'     => $order,
					'status'    => $status,
					'note'      => $note,
					'items'     => $items,
					'all_items' => empty( $items ),
					'created'   => $created,
					'refunded'  => '' !== $refunded ? (float) $refunded : null,
					'messages'  => $messages,
				]
			);
		}

		/**
		 * Conversation of a request.
		 *
		 * @param int       $request_id Request post ID.
		 * @param \WC_Order $order Order.
		 * @return array
		 */
		protected function read_messages( $request_id, $order ) {
			global $wpdb;

			$table = $wpdb->prefix . 'ywcars_messages';

			if ( ! $this->table_exists( $table ) ) {
				return [];
			}

			$meta_table = $wpdb->prefix . 'ywcars_messagemeta';
			$has_meta   = $this->table_exists( $meta_table );
			// MySQL NOW() is in the database's time zone, which is not always the site's.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$offset   = (int) $wpdb->get_var( 'SELECT TIMESTAMPDIFF( SECOND, UTC_TIMESTAMP(), NOW() )' );
			$messages = [];

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			foreach ( $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE request = %d ORDER BY date ASC, ID ASC", $request_id ) ) as $row ) {
				$text = (string) $row->message;

				if ( $has_meta ) {
					// Attachments are stored as file name => URL.
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					foreach ( $wpdb->get_results( $wpdb->prepare( "SELECT meta_key, meta_value FROM {$meta_table} WHERE message = %d", $row->ID ) ) as $file ) {
						/* translators: 1: file name, 2: file URL */
						$text .= "\n" . sprintf( __( 'Attached: %1$s (%2$s)', 'devdiggers-returns-for-woocommerce' ), $file->meta_key, $file->meta_value );
					}
				}

				$is_customer = (int) $row->author === (int) $order->get_customer_id() && ! user_can( (int) $row->author, 'manage_woocommerce' );
				$messages[]  = $this->message( $is_customer ? 'customer' : 'staff', $text, $this->from_gmt( $row->date ) - $offset, 'message', $is_customer ? 0 : (int) $row->author );
			}

			return $messages;
		}
	}
}
