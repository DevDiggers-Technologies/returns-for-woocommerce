<?php
/**
 * Import source: BuddyPilot Withdrawal – Easy Returns for WooCommerce.
 *
 * EU withdrawal declarations in {prefix}buddypilot_withdrawal_declarations. Items are a JSON snapshot; a partial
 * resolution marks each item accepted or rejected. Dates are GMT; an operator may override the filing date.
 *
 * @package DevDiggers Returns
 * @version 1.0.0
 */

namespace DDWCWPReturns\Helper\Import\Source;

use DDWCWPReturns\Helper\Import\DDWCWR_Import_Source;

defined( 'ABSPATH' ) || exit();

if ( ! class_exists( 'DDWCWR_Buddypilot_Source' ) ) {
	/**
	 * BuddyPilot source class
	 */
	class DDWCWR_Buddypilot_Source extends DDWCWR_Import_Source {
		/**
		 * Label.
		 *
		 * @return string
		 */
		public function get_label() {
			return __( 'BuddyPilot Withdrawal', 'devdiggers-returns' );
		}

		/**
		 * Vendor.
		 *
		 * @return string
		 */
		public function get_vendor() {
			return 'BuddyPilot';
		}

		/**
		 * Request types it holds.
		 *
		 * @return array
		 */
		public function get_types() {
			return [ 'withdrawal' ];
		}

		/**
		 * Plugin folders (free and paid).
		 *
		 * @return array
		 */
		public function get_plugin_dirs() {
			return [ 'buddypilot-withdrawal' ];
		}

		/**
		 * Table.
		 *
		 * @return string
		 */
		protected function table() {
			global $wpdb;

			return $wpdb->prefix . 'buddypilot_withdrawal_declarations';
		}

		/**
		 * Count.
		 *
		 * @return int
		 */
		public function count() {
			global $wpdb;

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			return $this->table_exists( $this->table() ) ? (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table()}" ) : 0;
		}

		/**
		 * Read a batch.
		 *
		 * @param string $cursor Last declaration ID.
		 * @return array
		 */
		public function read( $cursor ) {
			global $wpdb;

			$requests = [];
			$last     = '';

			if ( ! $this->table_exists( $this->table() ) ) {
				return [
					'requests' => [],
					'cursor'   => '',
				];
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			foreach ( $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$this->table()} WHERE id > %d ORDER BY id ASC LIMIT %d", absint( $cursor ), self::BATCH ), ARRAY_A ) as $row ) {
				$last  = (string) $row['id'];
				$order = $this->get_order( $row['order_id'] );

				if ( $order ) {
					$requests[] = $this->build( $row, $order );
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
		 * @param array     $row   Declaration.
		 * @param \WC_Order $order Order.
		 * @return array
		 */
		protected function build( $row, $order ) {
			$snapshot = json_decode( (string) $row['items_json'], true );
			// A later format wraps the list in an object with "v" and "items" keys.
			$snapshot = is_array( $snapshot ) && isset( $snapshot['items'] ) ? $snapshot['items'] : $snapshot;
			$items    = [];
			$decided  = [];

			foreach ( is_array( $snapshot ) ? $snapshot : [] as $entry ) {
				if ( empty( $entry['item_id'] ) ) {
					continue;
				}

				$items[ absint( $entry['item_id'] ) ] = [ 'qty' => max( 1, absint( isset( $entry['quantity'] ) ? $entry['quantity'] : 1 ) ) ];

				if ( ! empty( $entry['resolution'] ) ) {
					$name      = isset( $entry['name'] ) ? $entry['name'] : '#' . $entry['item_id'];
					$decided[] = 'accepted' === $entry['resolution']
						/* translators: %s: item name */
						? sprintf( __( '%s: accepted', 'devdiggers-returns' ), $name )
						/* translators: %s: item name */
						: sprintf( __( '%s: rejected', 'devdiggers-returns' ), $name ) . ( empty( $entry['item_note'] ) ? '' : ' (' . $entry['item_note'] . ')' );
				}
			}

			if ( 'open' === $row['status'] ) {
				$status = 'pending';
			} elseif ( 'rejected' === $row['resolution_type'] ) {
				$status = 'rejected';
			} elseif ( in_array( $row['resolution_type'], [ 'accepted', 'partial' ], true ) ) {
				$status = 'completed';
			} else {
				$status = 'cancelled';
			}

			$created  = $this->from_gmt( $row['filing_date_override'] ? $row['filing_date_override'] : $row['declared_at'] );
			$closed   = 'pending' === $status ? 0 : $this->from_gmt( $row['updated_at'] );
			$messages = [];
			$note     = trim( (string) $row['operator_note'] . ( $decided ? "\n" . implode( "\n", $decided ) : '' ) );

			if ( '' !== $note ) {
				$messages[] = $this->message( 'staff', $note, $closed ? $closed : $created, 'note' );
			}

			return $this->request(
				[
					'key'      => 'buddypilot:' . $row['id'],
					'order'    => $order,
					'type'     => 'withdrawal',
					'status'   => $status,
					'note'     => (string) $row['reason'],
					'items'    => $items,
					'created'  => $created,
					'closed'   => $closed,
					'messages' => $messages,
					'meta'     => [
						'statement_name'  => $this->order_name( $order ),
						'statement_email' => $order->get_billing_email(),
						'statement_utc'   => gmdate( 'Y-m-d H:i:s', $created ),
					],
				]
			);
		}
	}
}
