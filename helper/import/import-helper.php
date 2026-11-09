<?php
/**
 * Import helper: copies return, cancellation and withdrawal requests from other plugins into DevDiggers Returns.
 *
 * The other plugin's data is only read, never changed or deleted. Each request is imported once (its import key is
 * kept in the request meta). No emails are sent and no request hooks fire: these are old requests.
 *
 * @package DevDiggers Returns
 * @version 1.0.0
 */

namespace DDWCWPReturns\Helper\Import;

use DDWCWPReturns\Helper\Import\Source;

defined( 'ABSPATH' ) || exit();

if ( ! class_exists( 'DDWCWR_Import_Helper' ) ) {
	/**
	 * Import helper class
	 */
	class DDWCWR_Import_Helper {
		/**
		 * Order statuses WooCommerce ships with.
		 */
		const CORE_STATUSES = [ 'pending', 'processing', 'on-hold', 'completed', 'cancelled', 'refunded', 'failed', 'checkout-draft' ];

		/**
		 * Option with order statuses of other plugins that imported orders still carry: slug => label.
		 */
		const KEPT_STATUSES_OPTION = 'ddwcwr_imported_order_statuses';

		/**
		 * Every source DevDiggers Returns can import from.
		 *
		 * @return DDWCWR_Import_Source[] slug => source.
		 */
		public function get_all_sources() {
			return apply_filters(
				'ddwcwr_import_sources',
				[
					'wps'        => new Source\DDWCWR_Wps_Source(),
					'wpdesk'     => new Source\DDWCWR_Wpdesk_Source(),
					'yith'       => new Source\DDWCWR_Yith_Source(),
					'germanized' => new Source\DDWCWR_Germanized_Source(),
					'shiptastic' => new Source\DDWCWR_Shiptastic_Source(),
					'wccancel'   => new Source\DDWCWR_Wccancel_Source(),
					'piweb'      => new Source\DDWCWR_Piweb_Source(),
					'yoohw'      => new Source\DDWCWR_Yoohw_Source(),
					'easyreturn' => new Source\DDWCWR_Easyreturn_Source(),
					'buddypilot' => new Source\DDWCWR_Buddypilot_Source(),
				]
			);
		}

		/**
		 * Every source with what the Import screen shows.
		 *
		 * @return array slug => label, vendor, types, active, count, imported, last_run.
		 */
		public function get_sources() {
			$runs    = (array) get_option( 'ddwcwr_import_last_run', [] );
			$sources = [];

			foreach ( $this->get_all_sources() as $slug => $source ) {
				$sources[ $slug ] = [
					'label'    => $source->get_label(),
					'vendor'   => $source->get_vendor(),
					'types'    => $source->get_types(),
					'active'   => $source->is_active(),
					'count'    => $source->count(),
					'imported' => $this->count_imported( $slug ),
					'last_run' => isset( $runs[ $slug ] ) ? (int) $runs[ $slug ] : 0,
				];
			}

			return $sources;
		}

		/**
		 * Requests already imported from a source.
		 *
		 * @param string $slug Source slug.
		 * @return int
		 */
		public function count_imported( $slug ) {
			global $wpdb;

			$unmatched = count(
				array_filter(
					self::unmatched_keys(),
					function ( $key ) use ( $slug ) {
						return 0 === strpos( $key, $slug . ':' );
					}
				)
			);

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			return $unmatched + (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}ddwcwr_requests WHERE source = 'import' AND meta LIKE %s", '%' . $wpdb->esc_like( '"imported_from":"' . $slug . ':' ) . '%' ) );
		}

		/**
		 * Import one batch.
		 *
		 * @param string $slug    Source slug.
		 * @param string $cursor  Where the last batch stopped ('' to start).
		 * @param bool   $dry_run Count only, write nothing.
		 * @return array|\WP_Error imported, skipped, cursor, done.
		 */
		public function run_batch( $slug, $cursor, $dry_run ) {
			$sources = $this->get_all_sources();

			if ( ! isset( $sources[ $slug ] ) ) {
				return new \WP_Error( 'ddwcwr_import_source', __( 'Unknown source.', 'devdiggers-returns-for-woocommerce' ) );
			}

			// One batch per source at a time, so two tabs cannot import the same request twice.
			$lock = 'ddwcwr_import_lock_' . $slug;

			if ( ! $dry_run ) {
				$locked = (int) get_option( $lock );

				if ( ( $locked && $locked > time() - MINUTE_IN_SECONDS ) || ( ! $locked && ! add_option( $lock, time(), '', false ) ) ) {
					return new \WP_Error( 'ddwcwr_import_running', __( 'This import is already running in another tab. Wait for it to finish, then run it again.', 'devdiggers-returns-for-woocommerce' ) );
				}

				update_option( $lock, time(), false );
			}

			$source = $sources[ $slug ];
			$batch  = $source->read( (string) $cursor );
			$result = [
				'imported' => 0,
				'skipped'  => 0,
				'cursor'   => $batch['cursor'],
				'done'     => '' === $batch['cursor'],
			];

			foreach ( $batch['requests'] as $request ) {
				if ( $this->is_imported( $request ) ) {
					++$result['skipped'];
				} elseif ( $dry_run || $this->insert( $request, $source ) ) {
					++$result['imported'];
				} else {
					++$result['skipped'];
				}
			}

			if ( ! $dry_run ) {
				delete_option( $lock );

				if ( $result['done'] ) {
					$runs          = (array) get_option( 'ddwcwr_import_last_run', [] );
					$runs[ $slug ] = time();
					update_option( 'ddwcwr_import_last_run', $runs, false );
				}
			}

			return $result;
		}

		/**
		 * Whether a request was imported before.
		 *
		 * @param array $request Request.
		 * @return bool
		 */
		protected function is_imported( $request ) {
			global $wpdb;

			if ( ! $request['order'] ) {
				return in_array( $request['key'], self::unmatched_keys(), true );
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			return (bool) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}ddwcwr_requests WHERE meta LIKE %s LIMIT 1", '%' . $wpdb->esc_like( '"imported_from":' . wp_json_encode( $request['key'] ) ) . '%' ) );
		}

		/**
		 * Write one request with its items and messages.
		 *
		 * @param array                $request Request (see DDWCWR_Import_Source).
		 * @param DDWCWR_Import_Source $source  Plugin it comes from.
		 * @return bool
		 */
		protected function insert( $request, $source ) {
			global $wpdb;

			// A withdrawal statement for an order nobody can find: keep it for staff, as the withdrawal form does.
			if ( ! $request['order'] ) {
				if ( empty( $request['unmatched'] ) ) {
					return false;
				}

				$log   = (array) get_option( 'ddwcwr_unmatched_withdrawals', [] );
				$log[] = $request['unmatched'] + [
					'ip_hash'    => '',
					'import_key' => $request['key'],
				];
				update_option( 'ddwcwr_unmatched_withdrawals', array_slice( $log, -200 ), false );

				// The log keeps the last 200 statements; the keys are kept apart so a trimmed one is never imported again.
				update_option( 'ddwcwr_imported_unmatched_keys', array_merge( self::unmatched_keys(), [ $request['key'] ] ), false );

				return true;
			}

			$order      = $request['order'];
			$adapter    = ddwcwr_order_adapter();
			$order_data = $adapter->get_order_data( $order );
			$lines      = $adapter->get_order_items( $order );
			$types      = ddwcwr_get_request_types();
			$statuses   = ddwcwr_get_statuses();
			$type       = isset( $types[ $request['type'] ] ) ? $request['type'] : 'return';
			$status     = isset( $statuses[ $request['status'] ] ) ? $request['status'] : 'pending';
			$items      = $request['items'];

			if ( $request['all_items'] ) {
				foreach ( $lines as $item_id => $line ) {
					$items[ $item_id ] = [ 'qty' => $line['qty'] ];
				}
			}

			$rows   = [];
			$total  = 0;
			$notes  = [];
			$reason = $this->match_reason( in_array( $type, [ 'withdrawal', 'cancellation' ], true ) ? '' : $request['reason'], $type );

			// A reason DevDiggers Returns has no match for (or a withdrawal or cancellation reason) stays readable in the note.
			if ( in_array( $reason, [ 'other', 'withdrawal', 'cancellation' ], true ) && '' !== trim( $request['reason'] ) && false === stripos( (string) $request['note'], trim( $request['reason'] ) ) ) {
				/* translators: %s: reason */
				$notes[] = sprintf( __( 'Reason: %s', 'devdiggers-returns-for-woocommerce' ), trim( $request['reason'] ) );
			}

			foreach ( $items as $item_id => $item ) {
				$item_id = absint( $item_id );

				if ( ! isset( $lines[ $item_id ] ) ) {
					continue;
				}

				$line        = $lines[ $item_id ];
				$qty         = min( max( 1, absint( $item['qty'] ) ), $line['qty'] );
				$item_reason = $reason;

				// Some plugins ask for a reason per item.
				if ( ! empty( $item['reason'] ) && ! in_array( $type, [ 'withdrawal', 'cancellation' ], true ) ) {
					$item_reason = $this->match_reason( $item['reason'], $type );
				}

				if ( ! empty( $item['reason'] ) && ( 'other' === $item_reason || in_array( $type, [ 'withdrawal', 'cancellation' ], true ) ) ) {
					$notes[] = $line['name'] . ': ' . trim( $item['reason'] );
				}

				$line_total = round( $line['unit_value'] * $qty, wc_get_price_decimals() );
				$total     += $line_total;
				$rows[]     = [
					'order_item_id' => $item_id,
					'product_id'    => $line['product_id'],
					'variation_id'  => $line['variation_id'],
					'name'          => $line['name'],
					'qty'           => $qty,
					'line_total'    => $line_total,
					'reason'        => $item_reason,
				];
			}

			if ( ! $rows ) {
				return false;
			}

			$note     = trim( implode( "\n", array_merge( $notes, [ trim( (string) $request['note'] ) ] ) ) );
			$created  = $this->local_date( $request['created'] );
			$final    = in_array( $status, ddwcwr_get_final_statuses(), true );
			$refunded = 0;

			if ( 'completed' === $status ) {
				if ( null !== $request['refunded'] ) {
					$refunded = (float) $request['refunded'];
				} else {
					// The order's refunds are shared by every request on it: claim only what earlier imports left.
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
					$claimed  = (float) $wpdb->get_var( $wpdb->prepare( "SELECT SUM(refunded_total) FROM {$wpdb->prefix}ddwcwr_requests WHERE order_id = %d AND source = 'import'", $order_data['id'] ) );
					$refunded = max( 0, min( $total, (float) $order->get_total_refunded() - $claimed ) );
				}
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->insert(
				$wpdb->prefix . 'ddwcwr_requests',
				[
					'order_id'         => $order_data['id'],
					'customer_id'      => $order_data['customer_id'],
					'customer_email'   => strtolower( $order_data['email'] ),
					'customer_name'    => $order_data['name'],
					'type'             => $type,
					'status'           => $status,
					'resolution'       => 'refund',
					'customer_note'    => $note,
					'items_total'      => $total,
					'resolution_total' => $total,
					'refunded_total'   => round( $refunded, wc_get_price_decimals() ),
					'source'           => 'import',
					'access_key'       => wp_generate_password( 32, false, false ),
					'country'          => substr( (string) $order_data['country'], 0, 2 ),
					'meta'             => wp_json_encode( [ 'imported_from' => $request['key'] ] + $request['meta'] ),
					'created'          => $created,
					// Counted as activity today, so automatic closing gives the shop its usual time to handle open requests.
					'updated'          => current_time( 'mysql' ),
					'closed'           => $final ? $this->local_date( $request['closed'] ? $request['closed'] : $request['created'] ) : null,
				]
			);

			$request_id = (int) $wpdb->insert_id;

			if ( ! $request_id ) {
				return false;
			}

			foreach ( $rows as $row ) {
				$row['request_id'] = $request_id;
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
				$wpdb->insert( $wpdb->prefix . 'ddwcwr_request_items', $row );
			}

			$messages = $request['messages'];

			if ( '' !== $note ) {
				$first = [
					'author_type' => 'customer',
					'kind'        => 'message',
					'message'     => $note,
					'created'     => $request['created'],
				];

				array_unshift( $messages, $first );
			}

			$messages[] = [
				'author_type' => 'system',
				'kind'        => 'log',
				/* translators: 1: plugin name, 2: plugin vendor, 3: order number */
				'message'     => sprintf( __( 'Imported from %1$s by %2$s (order #%3$s).', 'devdiggers-returns-for-woocommerce' ), $source->get_label(), $source->get_vendor(), $order_data['number'] ),
				'created'     => 0,
			];

			foreach ( $messages as $message ) {
				if ( '' === trim( (string) $message['message'] ) ) {
					continue;
				}

				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
				$wpdb->insert(
					$wpdb->prefix . 'ddwcwr_messages',
					[
						'request_id'  => $request_id,
						'author_id'   => 'customer' === $message['author_type'] ? $order_data['customer_id'] : ( empty( $message['author_id'] ) ? 0 : absint( $message['author_id'] ) ),
						'author_type' => $message['author_type'],
						'kind'        => $message['kind'],
						'message'     => $message['message'],
						'attachments' => '[]',
						'created'     => $message['created'] ? $this->local_date( $message['created'] ) : current_time( 'mysql' ),
					]
				);
			}

			$this->keep_order_status( $order );

			return true;
		}

		/**
		 * Import keys of withdrawal statements that named no known order.
		 *
		 * @return string[]
		 */
		protected static function unmatched_keys() {
			return array_values(
				array_unique(
					array_merge(
						(array) get_option( 'ddwcwr_imported_unmatched_keys', [] ),
						array_filter( array_column( (array) get_option( 'ddwcwr_unmatched_withdrawals', [] ), 'import_key' ) )
					)
				)
			);
		}

		/**
		 * Site-local MySQL date of a GMT timestamp (now when unknown).
		 *
		 * @param int $timestamp GMT timestamp.
		 * @return string
		 */
		protected function local_date( $timestamp ) {
			return $timestamp ? get_date_from_gmt( gmdate( 'Y-m-d H:i:s', (int) $timestamp ) ) : current_time( 'mysql' );
		}

		/**
		 * Reason slug whose label (or slug) matches the old reason text, or "other".
		 *
		 * @param string $text Reason text.
		 * @param string $type Request type.
		 * @return string
		 */
		protected function match_reason( $text, $type ) {
			if ( in_array( $type, [ 'withdrawal', 'cancellation' ], true ) ) {
				return $type;
			}

			$text = strtolower( trim( (string) $text ) );

			if ( '' !== $text ) {
				foreach ( ddwcwr_get_reasons( false ) as $slug => $reason ) {
					if ( strtolower( $reason['label'] ) === $text || sanitize_title( $text ) === $slug ) {
						return $slug;
					}
				}
			}

			return 'other';
		}

		/**
		 * Remember an order status another plugin added, so the order stays visible after that plugin is gone.
		 *
		 * @param \WC_Order $order Order.
		 * @return void
		 */
		protected function keep_order_status( $order ) {
			$status = $order->get_status();

			if ( in_array( $status, self::CORE_STATUSES, true ) ) {
				return;
			}

			$kept = (array) get_option( self::KEPT_STATUSES_OPTION, [] );

			if ( isset( $kept[ $status ] ) ) {
				return;
			}

			// The supported plugins' own names, for when the plugin was deactivated before the import.
			$known      = [
				'return-requested' => __( 'Refund Requested', 'devdiggers-returns-for-woocommerce' ),
				'return-approved'  => __( 'Refund Approved', 'devdiggers-returns-for-woocommerce' ),
				'return-cancelled' => __( 'Refund Cancelled', 'devdiggers-returns-for-woocommerce' ),
				'refund-request'   => __( 'Refund Requested', 'devdiggers-returns-for-woocommerce' ),
				'pending-wdraw'    => __( 'Pending Withdrawal', 'devdiggers-returns-for-woocommerce' ),
				'cancel-request'   => __( 'Cancel Request', 'devdiggers-returns-for-woocommerce' ),
				'request-cancel'   => __( 'Cancel Requested', 'devdiggers-returns-for-woocommerce' ),
				'request-return'   => __( 'Return Requested', 'devdiggers-returns-for-woocommerce' ),
				'withdrawal'       => __( 'Withdrawal', 'devdiggers-returns-for-woocommerce' ),
			];
			$registered = wc_get_order_statuses();

			if ( isset( $registered[ 'wc-' . $status ] ) ) {
				$kept[ $status ] = wp_strip_all_tags( $registered[ 'wc-' . $status ] );
			} else {
				$kept[ $status ] = isset( $known[ $status ] ) ? $known[ $status ] : ucwords( str_replace( [ '-', '_' ], ' ', $status ) );
			}

			update_option( self::KEPT_STATUSES_OPTION, $kept, false );
		}

		/**
		 * Register kept statuses no active plugin registers any more.
		 *
		 * @return void
		 */
		public static function register_kept_statuses() {
			foreach ( (array) get_option( self::KEPT_STATUSES_OPTION, [] ) as $status => $label ) {
				if ( get_post_status_object( 'wc-' . $status ) ) {
					continue;
				}

				register_post_status(
					'wc-' . $status,
					[
						'label'                     => $label,
						'public'                    => false,
						'exclude_from_search'       => false,
						'show_in_admin_all_list'    => true,
						'show_in_admin_status_list' => true,
						// The label is the other plugin's own (already translated) name, so it is not run through gettext.
						'label_count'               => [
							0          => $label . ' <span class="count">(%s)</span>',
							1          => $label . ' <span class="count">(%s)</span>',
							'singular' => $label . ' <span class="count">(%s)</span>',
							'plural'   => $label . ' <span class="count">(%s)</span>',
							'context'  => null,
							'domain'   => null,
						],
					]
				);
			}
		}

		/**
		 * Add the kept statuses to the WooCommerce status list when no active plugin does.
		 *
		 * @param array $statuses Order statuses.
		 * @return array
		 */
		public static function add_kept_statuses( $statuses ) {
			foreach ( (array) get_option( self::KEPT_STATUSES_OPTION, [] ) as $status => $label ) {
				if ( ! isset( $statuses[ 'wc-' . $status ] ) ) {
					$statuses[ 'wc-' . $status ] = $label;
				}
			}

			return $statuses;
		}
	}
}
