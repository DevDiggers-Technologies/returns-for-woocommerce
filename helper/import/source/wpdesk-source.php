<?php
/**
 * Import source: Flexible Refund and Return Order for WooCommerce (WP Desk).
 *
 * Current versions keep requests in {prefix}fr_requests. Older versions kept one request per order in order meta
 * (fr_refund_request_data); WP Desk moves those into its table and keeps the order ID in legacy_order_id, so a request
 * found in both places is imported once.
 *
 * @package DevDiggers Returns
 * @version 1.0.0
 */

namespace DDWCWPReturns\Helper\Import\Source;

use DDWCWPReturns\Helper\Import\DDWCWR_Import_Source;

defined( 'ABSPATH' ) || exit();

if ( ! class_exists( 'DDWCWR_Wpdesk_Source' ) ) {
	/**
	 * WP Desk source class
	 */
	class DDWCWR_Wpdesk_Source extends DDWCWR_Import_Source {
		/**
		 * Label.
		 *
		 * @return string
		 */
		public function get_label() {
			return __( 'Flexible Refund and Return Order', 'devdiggers-returns' );
		}

		/**
		 * Vendor.
		 *
		 * @return string
		 */
		public function get_vendor() {
			return 'WP Desk';
		}

		/**
		 * Request types it holds.
		 *
		 * @return array
		 */
		public function get_types() {
			return [ 'return', 'warranty' ];
		}

		/**
		 * Plugin folders (free and paid).
		 *
		 * @return array
		 */
		public function get_plugin_dirs() {
			return [ 'flexible-refund-and-return-order-for-woocommerce', 'flexible-refund-and-return-order-for-woocommerce-pro' ];
		}

		/**
		 * Requests table.
		 *
		 * @return string
		 */
		protected function table() {
			global $wpdb;

			return $wpdb->prefix . 'fr_requests';
		}

		/**
		 * Count.
		 *
		 * @return int
		 */
		public function count() {
			global $wpdb;

			$rows = 0;

			if ( $this->table_exists( $this->table() ) ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$rows = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table()}" );
			}

			$legacy = array_diff( $this->order_ids_with_meta( 'fr_refund_request_data', 0, PHP_INT_MAX ), $this->moved_order_ids() );

			return $rows + count( $legacy );
		}

		/**
		 * Read a batch: table rows first, then orders that still keep the request in meta.
		 *
		 * @param string $cursor rows:ID or legacy:ID.
		 * @return array
		 */
		public function read( $cursor ) {
			global $wpdb;

			list( $phase, $after ) = array_pad( explode( ':', $cursor ? $cursor : 'rows:0' ), 2, 0 );
			$after                 = absint( $after );
			$requests              = [];
			$last                  = '';

			if ( 'rows' === $phase && $this->table_exists( $this->table() ) ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				foreach ( $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$this->table()} WHERE id > %d ORDER BY id ASC LIMIT %d", $after, self::BATCH ), ARRAY_A ) as $row ) {
					$last  = 'rows:' . $row['id'];
					$order = $this->get_order( $row['order_id'] );

					if ( $order ) {
						$snapshot = json_decode( (string) $row['form_snapshot'], true );
						$values   = json_decode( (string) $row['submitted_values'], true );
						// A row moved from order meta keeps the order's key, so it is never imported twice.
						$key        = $row['legacy_order_id'] ? 'wpdesk:order:' . $row['legacy_order_id'] : 'wpdesk:row:' . $row['id'];
						$requests[] = $this->build( $key, $order, $row['request_type'], $row['status'], is_array( $values ) ? $values : [], is_array( $snapshot ) ? $snapshot : [], (string) $row['note'], $this->from_gmt( $row['created_at'] ), $this->from_gmt( $row['updated_at'] ) );
					}
				}
			}

			if ( '' === $last ) {
				$after = 'legacy' === $phase ? $after : 0;
				$moved = $this->moved_order_ids();

				foreach ( $this->order_ids_with_meta( 'fr_refund_request_data', $after ) as $order_id ) {
					$last  = 'legacy:' . $order_id;
					$order = in_array( $order_id, $moved, true ) ? null : $this->get_order( $order_id );

					if ( $order ) {
						$values     = $order->get_meta( 'fr_refund_request_data' );
						$date       = (int) $order->get_meta( 'fr_refund_request_date' );
						$requests[] = $this->build( 'wpdesk:order:' . $order_id, $order, 'refund', (string) $order->get_meta( 'fr_refund_request_status' ), is_array( $values ) ? $values : [], [], (string) $order->get_meta( 'fr_refund_request_note' ), $date, 0 );
					}
				}
			}

			return [
				'requests' => $requests,
				'cursor'   => $last,
			];
		}

		/**
		 * Orders whose meta request is already in the WP Desk table.
		 *
		 * @return int[]
		 */
		protected function moved_order_ids() {
			global $wpdb;

			if ( ! $this->table_exists( $this->table() ) ) {
				return [];
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			return array_map( 'absint', $wpdb->get_col( "SELECT legacy_order_id FROM {$this->table()} WHERE legacy_order_id IS NOT NULL" ) );
		}

		/**
		 * Build a request.
		 *
		 * @param string    $key      Import key.
		 * @param \WC_Order $order    Order.
		 * @param string    $type     refund|reclamation|repair.
		 * @param string    $status   WP Desk status.
		 * @param array     $values   Submitted form values.
		 * @param array     $snapshot Form as it was when the customer sent it.
		 * @param string    $note     The shop's note to the customer.
		 * @param int       $created  GMT timestamp.
		 * @param int       $updated  GMT timestamp.
		 * @return array
		 */
		protected function build( $key, $order, $type, $status, $values, $snapshot, $note, $created, $updated ) {
			$map = [
				'requested' => 'pending',
				'shipment'  => 'awaiting_return',
				'verifying' => 'received',
				'approved'  => 'completed',
				'refused'   => 'rejected',
				'rejected'  => 'rejected',
				'canceled'  => 'cancelled',
			];

			$items = [];

			foreach ( isset( $values['items'] ) ? (array) $values['items'] : [] as $item_id => $item ) {
				$items[ absint( $item_id ) ] = [ 'qty' => isset( $item['qty'] ) ? absint( $item['qty'] ) : 1 ];
			}

			// Form fields (the store builds its own form) become the note, labeled as the customer saw them.
			$schema = isset( $snapshot['schema'] ) && is_array( $snapshot['schema'] ) ? $snapshot['schema'] : [];
			$lines  = [];
			$reason = '';

			foreach ( array_diff_key( $values, array_flip( [ 'items', 'total_refund_qty', 'attachments' ] ) ) as $field => $value ) {
				$value = trim( is_array( $value ) ? implode( ', ', array_map( 'strval', $value ) ) : (string) $value );

				if ( '' === $value ) {
					continue;
				}

				$field_type = isset( $schema[ $field ]['type'] ) ? $schema[ $field ]['type'] : '';
				$label      = ! empty( $schema[ $field ]['label'] ) ? $schema[ $field ]['label'] : ucfirst( str_replace( [ '_', '-' ], ' ', $field ) );
				$lines[]    = $label . ': ' . $value;

				// A choice field is the reason the customer picked.
				if ( '' === $reason && ( in_array( $field_type, [ 'select', 'radio' ], true ) || 'reason' === $field ) ) {
					$reason = $value;
				}
			}

			if ( ! empty( $values['attachments'] ) ) {
				$names = [];

				array_walk_recursive(
					$values['attachments'],
					function ( $file ) use ( &$names ) {
						if ( is_string( $file ) && '' !== $file ) {
							$names[] = wp_basename( $file );
						}
					}
				);

				if ( $names ) {
					/* translators: %s: file names */
					$lines[] = sprintf( __( 'Photos sent with the request: %s', 'devdiggers-returns' ), implode( ', ', array_unique( $names ) ) );
				}
			}

			$status   = isset( $map[ $status ] ) ? $map[ $status ] : 'pending';
			$types    = ddwcwr_get_request_types();
			$messages = [];

			if ( '' !== trim( $note ) ) {
				$messages[] = $this->message( 'staff', $note, $updated ? $updated : $created );
			}

			return $this->request(
				[
					'key'      => $key,
					'order'    => $order,
					// Complaints and repairs are warranty claims where that type exists.
					'type'     => 'refund' !== $type && isset( $types['warranty'] ) ? 'warranty' : 'return',
					'status'   => $status,
					'reason'   => $reason,
					'note'     => implode( "\n", $lines ),
					'items'    => $items,
					'created'  => $created,
					'closed'   => in_array( $status, [ 'completed', 'rejected', 'cancelled' ], true ) ? $updated : 0,
					'messages' => $messages,
				]
			);
		}
	}
}
