<?php
/**
 * Request helper: storage, validation, status changes, messages.
 *
 * @package WPReturns
 * @version 1.0.0
 */

namespace DDWCWPReturns\Helper\Request;

defined( 'ABSPATH' ) || exit();

if ( ! class_exists( 'DDWCWR_Request_Helper' ) ) {
	/**
	 * Request helper class
	 */
	class DDWCWR_Request_Helper {
		/**
		 * Get a request.
		 *
		 * @param int $id Request ID.
		 * @return array|null
		 */
		public function get_request( $id ) {
			global $wpdb;

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$request = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}ddwcwr_requests WHERE id = %d", absint( $id ) ), ARRAY_A );

			return $request ? $this->prepare_request( $request ) : null;
		}

		/**
		 * Decode JSON columns.
		 *
		 * @param array $request Row.
		 * @return array
		 */
		protected function prepare_request( $request ) {
			$request['meta']   = ddwcwr_decode( $request['meta'] );
			$request['number'] = ddwcwr_get_request_number( $request['id'] );

			return $request;
		}

		/**
		 * Items of a request.
		 *
		 * @param int $request_id Request ID.
		 * @return array
		 */
		public function get_request_items( $request_id ) {
			global $wpdb;

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}ddwcwr_request_items WHERE request_id = %d ORDER BY id ASC", absint( $request_id ) ), ARRAY_A );
		}

		/**
		 * Build a prepared WHERE clause for request lists.
		 *
		 * @param array $args Filters.
		 * @return string
		 */
		protected function build_where( $args ) {
			global $wpdb;

			$where = [ '1=1' ];

			foreach ( [ 'status', 'type', 'resolution', 'source' ] as $field ) {
				if ( ! empty( $args[ $field ] ) ) {
					$values  = (array) $args[ $field ];
					$where[] = $wpdb->prepare( "{$field} IN (" . implode( ',', array_fill( 0, count( $values ), '%s' ) ) . ')', $values ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Column from a fixed list.
				}
			}

			foreach ( [ 'customer_id', 'order_id' ] as $field ) {
				if ( isset( $args[ $field ] ) && '' !== $args[ $field ] && null !== $args[ $field ] ) {
					$where[] = $wpdb->prepare( "{$field} = %d", $args[ $field ] ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Column from a fixed list.
				}
			}

			if ( ! empty( $args['customer_email'] ) ) {
				$where[] = $wpdb->prepare( 'customer_email = %s', strtolower( $args['customer_email'] ) );
			}

			if ( ! empty( $args['open'] ) ) {
				$where[] = "status NOT IN ('completed','rejected','cancelled')";
			}

			if ( ! empty( $args['reason'] ) ) {
				$where[] = $wpdb->prepare( "id IN (SELECT request_id FROM {$wpdb->prefix}ddwcwr_request_items WHERE reason = %s)", $args['reason'] );
			}

			if ( ! empty( $args['date_from'] ) ) {
				$where[] = $wpdb->prepare( 'created >= %s', $args['date_from'] );
			}

			if ( ! empty( $args['date_to'] ) ) {
				$where[] = $wpdb->prepare( 'created <= %s', $args['date_to'] );
			}

			if ( ! empty( $args['search'] ) ) {
				$search = trim( $args['search'] );
				$like   = '%' . $wpdb->esc_like( $search ) . '%';
				$number = ddwcwr_parse_request_number( $search );

				if ( $number && preg_match( '/^(rma-?)?#?\d+$/i', $search ) ) {
					$where[] = $wpdb->prepare( '(id = %d OR order_id = %d)', $number, $number );
				} else {
					$where[] = $wpdb->prepare( '(customer_email LIKE %s OR customer_name LIKE %s OR tracking_number LIKE %s)', $like, $like, $like );
				}
			}

			return implode( ' AND ', $where );
		}

		/**
		 * List requests.
		 *
		 * @param array $args Filters plus orderby, order, limit, offset.
		 * @return array
		 */
		public function get_requests( $args = [] ) {
			global $wpdb;

			$args    = wp_parse_args(
				$args,
				[
					'orderby' => 'id',
					'order'   => 'DESC',
					'limit'   => 20,
					'offset'  => 0,
				]
			);
			$orderby = in_array( $args['orderby'], [ 'id', 'created', 'updated', 'status', 'items_total', 'order_id' ], true ) ? $args['orderby'] : 'id';
			$order   = 'ASC' === strtoupper( $args['order'] ) ? 'ASC' : 'DESC';
			$where   = $this->build_where( $args );
			$limit   = -1 === (int) $args['limit'] ? '' : $wpdb->prepare( 'LIMIT %d OFFSET %d', absint( $args['limit'] ), absint( $args['offset'] ) );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Fragments prepared/whitelisted above.
			$rows = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}ddwcwr_requests WHERE {$where} ORDER BY {$orderby} {$order} {$limit}", ARRAY_A );

			return array_map( [ $this, 'prepare_request' ], (array) $rows );
		}

		/**
		 * Count requests.
		 *
		 * @param array $args Filters.
		 * @return int
		 */
		public function get_requests_count( $args = [] ) {
			global $wpdb;

			$where = $this->build_where( $args );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Prepared fragments.
			return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}ddwcwr_requests WHERE {$where}" );
		}

		/**
		 * Request counts by status.
		 *
		 * @param string $type Only this request type.
		 * @return array
		 */
		public function get_status_counts( $type = '' ) {
			global $wpdb;

			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			if ( $type ) {
				$rows = $wpdb->get_results( $wpdb->prepare( "SELECT status, COUNT(*) AS total FROM {$wpdb->prefix}ddwcwr_requests WHERE type = %s GROUP BY status", $type ), ARRAY_A );
			} else {
				$rows = $wpdb->get_results( "SELECT status, COUNT(*) AS total FROM {$wpdb->prefix}ddwcwr_requests GROUP BY status", ARRAY_A );
			}
			// phpcs:enable

			return wp_list_pluck( (array) $rows, 'total', 'status' );
		}

		/**
		 * Requests of an order.
		 *
		 * @param int $order_id Order ID.
		 * @return array
		 */
		public function get_order_requests( $order_id ) {
			return $this->get_requests(
				[
					'order_id' => absint( $order_id ),
					'limit'    => 100,
				]
			);
		}

		/**
		 * Quantity already requested per order item. Rejected and canceled requests free their items again; requests
		 * that produced a WooCommerce refund are left out because the refund already counts the quantity.
		 *
		 * Warranty claims (saved by Pro) never use up a returnable quantity.
		 *
		 * @param int $order_id Order ID.
		 * @return array order_item_id => qty.
		 */
		public function get_requested_qty( $order_id ) {
			global $wpdb;

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT i.order_item_id, SUM(i.qty) AS qty FROM {$wpdb->prefix}ddwcwr_request_items i INNER JOIN {$wpdb->prefix}ddwcwr_requests r ON r.id = i.request_id WHERE r.order_id = %d AND r.type <> 'warranty' AND r.status NOT IN ('rejected','cancelled') AND r.refund_id = 0 GROUP BY i.order_item_id", absint( $order_id ) ), ARRAY_A );

			return array_map( 'intval', wp_list_pluck( (array) $rows, 'qty', 'order_item_id' ) );
		}

		/**
		 * Start of the window and deadline for an order.
		 *
		 * @param array  $order_data Order summary.
		 * @param int    $days Days.
		 * @param string $from completed|created.
		 * @return array start, deadline, used (percent), open.
		 */
		public function get_window( $order_data, $days, $from ) {
			$start    = 'created' === $from ? $order_data['date_created'] : $order_data['date_completed'];
			$deadline = $start + absint( $days ) * DAY_IN_SECONDS;
			$now      = time();

			return [
				'start'    => $start,
				'deadline' => $deadline,
				'used'     => $days ? round( max( 0, min( 100, ( $now - $start ) / ( $days * DAY_IN_SECONDS ) * 100 ) ), 1 ) : 100,
				'open'     => $now <= $deadline,
			];
		}

		/**
		 * Whether a type is enabled.
		 *
		 * @param string $type Type.
		 * @return bool
		 */
		public function is_type_enabled( $type ) {
			global $ddwcwr_configuration;

			$keys = [
				'return'       => 'allow_refund',
				'cancellation' => 'allow_cancellation',
				'withdrawal'   => 'withdrawal_enabled',
			];

			return isset( $keys[ $type ] ) && 'yes' === $ddwcwr_configuration[ $keys[ $type ] ];
		}

		/**
		 * Whether an order can get a request of a type at all (order status, type enabled).
		 *
		 * @param array  $order_data Order summary.
		 * @param string $type Type.
		 * @return bool
		 */
		public function order_status_allows( $order_data, $type ) {
			global $ddwcwr_configuration;

			if ( ! $this->is_type_enabled( $type ) ) {
				return false;
			}

			if ( 'cancellation' === $type ) {
				return in_array( $order_data['status'], (array) $ddwcwr_configuration['cancellation_statuses'], true );
			}

			if ( 'withdrawal' === $type ) {
				return ! in_array( $order_data['status'], [ 'wc-cancelled', 'wc-refunded', 'wc-failed', 'wc-checkout-draft' ], true ) && ddwcwr_withdrawal_applies_to_country( $order_data['country'] );
			}

			return in_array( $order_data['status'], (array) $ddwcwr_configuration['eligible_order_statuses'], true );
		}

		/**
		 * Items of an order with what can still be requested for a type.
		 *
		 * @param object $order Order.
		 * @param string $type Type.
		 * @return array Items with available, eligible, message, deadline, window.
		 */
		public function get_eligible_items( $order, $type ) {
			global $ddwcwr_configuration;

			$adapter    = ddwcwr_order_adapter();
			$order_data = $adapter->get_order_data( $order );
			$items      = $adapter->get_order_items( $order );
			$requested  = $this->get_requested_qty( $order_data['id'] );

			foreach ( $items as $item_id => $item ) {
				$already           = isset( $requested[ $item_id ] ) ? $requested[ $item_id ] : 0;
				$item['available'] = max( 0, $item['qty'] - $item['refunded_qty'] - $already );
				$item['eligible']  = $item['available'] > 0;
				$item['message']   = $item['available'] > 0 ? '' : __( 'Already requested or refunded.', 'wpreturns' );
				$item['deadline']  = 0;

				if ( $item['eligible'] && 'return' === $type && ddwcwr_is_final_sale( $item ) ) {
					$item['eligible'] = false;
					$item['message']  = __( 'This item is final sale and cannot be returned.', 'wpreturns' );
				} elseif ( $item['eligible'] && 'return' === $type ) {
					$state            = $this->get_window( $order_data, $ddwcwr_configuration['return_window_days'], $ddwcwr_configuration['window_from'] );
					$item['deadline'] = $state['deadline'];
					$item['window']   = $state;

					if ( ! $state['open'] ) {
						$item['eligible'] = false;
						/* translators: %s: date the return window closed */
						$item['message'] = sprintf( __( 'The return window closed on %s.', 'wpreturns' ), date_i18n( wc_date_format(), $state['deadline'] ) );
					}
				} elseif ( $item['eligible'] && 'withdrawal' === $type ) {
					if ( ddwcwr_is_withdrawal_excluded( $item ) ) {
						$item['eligible'] = false;
						$item['message']  = __( 'This item is exempt from the right of withdrawal.', 'wpreturns' );
					} elseif ( $item['downloadable'] && $order_data['digital_consent'] ) {
						$item['eligible'] = false;
						$item['message']  = __( 'You agreed to the immediate supply of this digital content at checkout, so the right of withdrawal does not apply.', 'wpreturns' );
					} elseif ( $order_data['is_completed'] ) {
						$state            = $this->get_window( $order_data, $ddwcwr_configuration['withdrawal_days'], $ddwcwr_configuration['withdrawal_from'] );
						$item['deadline'] = $state['deadline'];

						if ( ! $state['open'] ) {
							$item['eligible'] = false;
							/* translators: %s: date the withdrawal period ended */
							$item['message'] = sprintf( __( 'The withdrawal period ended on %s.', 'wpreturns' ), date_i18n( wc_date_format(), $state['deadline'] ) );
						}
					}
				}

				$items[ $item_id ] = $item;
			}

			return $items;
		}

		/**
		 * Validate and create a request.
		 *
		 * @param array $args order, type, items, note, attachments, source, by_admin, meta.
		 * @return int|\WP_Error Request ID.
		 */
		public function create_request( $args ) {
			global $ddwcwr_configuration, $wpdb;

			$args = wp_parse_args(
				$args,
				[
					'order'       => null,
					'type'        => 'return',
					'items'       => [],
					'note'        => '',
					'attachments' => [],
					'source'      => 'account',
					'by_admin'    => false,
					'meta'        => [],
					// Address the request (and its emails) go to, when it is not the order's billing email.
					'email'       => '',
				]
			);

			$order = $args['order'];

			if ( ! $order ) {
				return new \WP_Error( 'ddwcwr_order', __( 'We could not find that order.', 'wpreturns' ) );
			}

			$adapter    = ddwcwr_order_adapter();
			$order_data = $adapter->get_order_data( $order );
			$type       = $args['type'];

			if ( ! array_key_exists( $type, ddwcwr_get_request_types() ) || ( ! $args['by_admin'] && ! $this->order_status_allows( $order_data, $type ) ) ) {
				return new \WP_Error( 'ddwcwr_type', __( 'This order cannot get this kind of request right now.', 'wpreturns' ) );
			}

			$eligible = $this->get_eligible_items( $order, $type );
			$items    = $args['items'];

			// Withdrawals and cancellations cover the whole order unless the customer picked items.
			if ( empty( $items ) && in_array( $type, [ 'withdrawal', 'cancellation' ], true ) ) {
				foreach ( $eligible as $item_id => $item ) {
					if ( $item['eligible'] ) {
						$items[ $item_id ] = [ 'qty' => $item['available'] ];
					}
				}
			}

			$reasons    = ddwcwr_get_reasons();
			$conditions = ddwcwr_get_item_conditions();
			$rows       = [];
			$photos     = false;
			$total      = 0;

			foreach ( $items as $item_id => $line ) {
				$item_id = absint( $item_id );
				$qty     = isset( $line['qty'] ) ? absint( $line['qty'] ) : 0;

				if ( ! $qty ) {
					continue;
				}

				if ( ! isset( $eligible[ $item_id ] ) ) {
					return new \WP_Error( 'ddwcwr_item', __( 'One of the selected items is not part of this order.', 'wpreturns' ) );
				}

				$item = $eligible[ $item_id ];

				if ( ! $args['by_admin'] && ! $item['eligible'] ) {
					/* translators: 1: product name, 2: reason */
					return new \WP_Error( 'ddwcwr_item', sprintf( __( '%1$s: %2$s', 'wpreturns' ), $item['name'], $item['message'] ) );
				}

				if ( $qty > $item['available'] ) {
					/* translators: 1: product name, 2: quantity */
					return new \WP_Error( 'ddwcwr_qty', sprintf( __( 'You can request up to %2$d of %1$s.', 'wpreturns' ), $item['name'], $item['available'] ) );
				}

				if ( in_array( $type, [ 'withdrawal', 'cancellation' ], true ) ) {
					$reason = $type;
				} else {
					$reason = isset( $line['reason'] ) ? sanitize_key( $line['reason'] ) : '';

					if ( ! isset( $reasons[ $reason ] ) ) {
						/* translators: %s: product name */
						return new \WP_Error( 'ddwcwr_reason', sprintf( __( 'Please choose a reason for %s.', 'wpreturns' ), $item['name'] ) );
					}

					$photos = $photos || ! empty( $reasons[ $reason ]['require_photo'] );
				}

				$line_total = round( $item['unit_value'] * $qty, wc_get_price_decimals() );
				$total     += $line_total;
				$rows[]     = [
					'order_item_id'  => $item_id,
					'product_id'     => $item['product_id'],
					'variation_id'   => $item['variation_id'],
					'name'           => $item['name'],
					'qty'            => $qty,
					'line_total'     => $line_total,
					'reason'         => $reason,
					'item_condition' => isset( $line['condition'] ) && isset( $conditions[ $line['condition'] ] ) ? $line['condition'] : '',
				];
			}

			if ( empty( $rows ) ) {
				if ( in_array( $type, [ 'withdrawal', 'cancellation' ], true ) ) {
					return new \WP_Error( 'ddwcwr_items', __( 'Every item of this order is already covered by a request or a refund, or cannot be withdrawn from. Check your existing requests or contact us.', 'wpreturns' ) );
				}

				return new \WP_Error( 'ddwcwr_items', __( 'Please select at least one item.', 'wpreturns' ) );
			}

			if ( ! $args['by_admin'] && 'return' === $type && 'yes' === $ddwcwr_configuration['require_note'] && '' === trim( $args['note'] ) ) {
				return new \WP_Error( 'ddwcwr_note', __( 'Please add a short note about your request.', 'wpreturns' ) );
			}

			if ( ! $args['by_admin'] && 'return' === $type && $photos && empty( $args['attachments'] ) ) {
				return new \WP_Error( 'ddwcwr_photos', __( 'Please add a photo of the items.', 'wpreturns' ) );
			}

			$now = current_time( 'mysql' );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->insert(
				$wpdb->prefix . 'ddwcwr_requests',
				[
					'order_id'         => $order_data['id'],
					'customer_id'      => $order_data['customer_id'],
					'customer_email'   => strtolower( is_email( $args['email'] ) ? $args['email'] : $order_data['email'] ),
					'customer_name'    => $order_data['name'],
					'type'             => $type,
					'status'           => 'pending',
					'resolution'       => 'refund',
					'customer_note'    => $args['note'],
					'items_total'      => $total,
					'resolution_total' => $total,
					'source'           => $args['source'],
					'access_key'       => wp_generate_password( 32, false, false ),
					'country'          => substr( (string) $order_data['country'], 0, 2 ),
					'ip_hash'          => ddwcwr_get_client_ip_hash(),
					'meta'             => wp_json_encode( $args['meta'] ),
					'created'          => $now,
					'updated'          => $now,
				]
			);

			$request_id = (int) $wpdb->insert_id;

			if ( ! $request_id ) {
				return new \WP_Error( 'ddwcwr_db', __( 'Your request could not be saved. Please try again.', 'wpreturns' ) );
			}

			foreach ( $rows as $row ) {
				$row['request_id'] = $request_id;
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
				$wpdb->insert( $wpdb->prefix . 'ddwcwr_request_items', $row );
			}

			$types = ddwcwr_get_request_types();
			/* translators: 1: request type, 2: source */
			$this->log( $request_id, sprintf( __( '%1$s request submitted (%2$s).', 'wpreturns' ), $types[ $type ], $args['source'] ) );

			if ( '' !== trim( $args['note'] ) || ! empty( $args['attachments'] ) ) {
				$this->add_message( $request_id, $args['note'], 'message', $args['by_admin'] ? 'staff' : 'customer', get_current_user_id(), $args['attachments'], false );
			}

			/* translators: 1: request number, 2: request type */
			$adapter->add_order_note( $order, sprintf( __( 'WPReturns: %1$s (%2$s) was created for this order.', 'wpreturns' ), ddwcwr_get_request_number( $request_id ), $types[ $type ] ) );

			do_action( 'ddwcwr_request_created', $request_id, $this->get_request( $request_id ) );

			return $request_id;
		}

		/**
		 * Update request columns.
		 *
		 * @param int   $request_id Request ID.
		 * @param array $fields Column => value.
		 * @param bool  $touch Mark the request as updated.
		 * @return bool
		 */
		public function update_request( $request_id, $fields, $touch = true ) {
			global $wpdb;

			$allowed = [ 'status', 'resolution', 'refunded_total', 'refund_id', 'label_provider', 'label_url', 'label_file', 'tracking_number', 'carrier', 'meta', 'closed', 'customer_email' ];
			$fields  = array_intersect_key( $fields, array_flip( $allowed ) );

			if ( isset( $fields['meta'] ) && is_array( $fields['meta'] ) ) {
				$fields['meta'] = wp_json_encode( $fields['meta'] );
			}

			if ( $touch ) {
				$fields['updated'] = current_time( 'mysql' );
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			return false !== $wpdb->update( $wpdb->prefix . 'ddwcwr_requests', $fields, [ 'id' => absint( $request_id ) ] );
		}

		/**
		 * Update one request item.
		 *
		 * @param int   $item_id Item row ID.
		 * @param array $fields Fields.
		 * @return void
		 */
		public function update_item( $item_id, $fields ) {
			global $wpdb;

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update( $wpdb->prefix . 'ddwcwr_request_items', array_intersect_key( $fields, array_flip( [ 'restocked' ] ) ), [ 'id' => absint( $item_id ) ] );
		}

		/**
		 * Change the status of a request.
		 *
		 * @param int    $request_id Request ID.
		 * @param string $status New status.
		 * @param array  $args note (shown to the customer), author_id, notify.
		 * @return bool|\WP_Error
		 */
		public function change_status( $request_id, $status, $args = [] ) {
			$args     = wp_parse_args(
				$args,
				[
					'note'      => '',
					'author_id' => 0,
					'notify'    => true,
				]
			);
			$request  = $this->get_request( $request_id );
			$statuses = ddwcwr_get_statuses();

			if ( ! $request ) {
				return new \WP_Error( 'ddwcwr_request', __( 'Request not found.', 'wpreturns' ) );
			}

			if ( ! isset( $statuses[ $status ] ) ) {
				return new \WP_Error( 'ddwcwr_status', __( 'Unknown status.', 'wpreturns' ) );
			}

			$old = $request['status'];

			if ( $old === $status ) {
				return true;
			}

			$fields = [ 'status' => $status ];

			if ( in_array( $status, ddwcwr_get_final_statuses(), true ) ) {
				$fields['closed'] = current_time( 'mysql' );
			} elseif ( in_array( $old, ddwcwr_get_final_statuses(), true ) ) {
				$fields['closed'] = null;
			}

			$this->update_request( $request_id, $fields );

			/* translators: 1: old status, 2: new status */
			$this->log( $request_id, sprintf( __( 'Status changed from %1$s to %2$s.', 'wpreturns' ), ddwcwr_get_status_label( $old ), ddwcwr_get_status_label( $status ) ), $args['author_id'] );

			if ( '' !== trim( (string) $args['note'] ) ) {
				// Sent with the status email, not as a separate message email.
				$this->add_message( $request_id, $args['note'], 'message', $args['author_id'] ? 'staff' : 'system', $args['author_id'], [], false );
			}

			$request = $this->get_request( $request_id );

			do_action( 'ddwcwr_request_status_changed', $request_id, $old, $status, $request, $args );

			return true;
		}

		/**
		 * Add a conversation message, internal note or log entry.
		 *
		 * @param int    $request_id Request ID.
		 * @param string $message Text.
		 * @param string $kind message|note|log.
		 * @param string $author_type customer|staff|system.
		 * @param int    $author_id Author.
		 * @param array  $attachments Stored files.
		 * @param bool   $notify Fire the message action (emails).
		 * @return int Message ID.
		 */
		public function add_message( $request_id, $message, $kind = 'message', $author_type = 'system', $author_id = 0, $attachments = [], $notify = true ) {
			global $wpdb;

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->insert(
				$wpdb->prefix . 'ddwcwr_messages',
				[
					'request_id'  => absint( $request_id ),
					'author_id'   => absint( $author_id ),
					'author_type' => $author_type,
					'kind'        => $kind,
					'message'     => $message,
					'attachments' => wp_json_encode( array_values( (array) $attachments ) ),
					'created'     => current_time( 'mysql' ),
				]
			);

			$message_id = (int) $wpdb->insert_id;

			if ( 'log' !== $kind ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->update( $wpdb->prefix . 'ddwcwr_requests', [ 'updated' => current_time( 'mysql' ) ], [ 'id' => absint( $request_id ) ] );
			}

			if ( $notify && 'message' === $kind ) {
				do_action( 'ddwcwr_request_message_added', $request_id, $message_id, $author_type, $message );
			}

			return $message_id;
		}

		/**
		 * Log a history entry.
		 *
		 * @param int    $request_id Request ID.
		 * @param string $text Text.
		 * @param int    $author_id Author.
		 * @return void
		 */
		public function log( $request_id, $text, $author_id = 0 ) {
			$this->add_message( $request_id, $text, 'log', $author_id ? 'staff' : 'system', $author_id ? $author_id : get_current_user_id() );
		}

		/**
		 * Messages of a request.
		 *
		 * @param int   $request_id Request ID.
		 * @param array $kinds Kinds.
		 * @return array
		 */
		public function get_messages( $request_id, $kinds = [ 'message' ] ) {
			global $wpdb;

			$kinds        = array_values( array_intersect( (array) $kinds, [ 'message', 'note', 'log' ] ) );
			$placeholders = implode( ',', array_fill( 0, count( $kinds ), '%s' ) );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Placeholders built from the count.
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}ddwcwr_messages WHERE request_id = %d AND kind IN ({$placeholders}) ORDER BY id ASC", array_merge( [ absint( $request_id ) ], $kinds ) ), ARRAY_A );

			foreach ( $rows as &$row ) {
				$row['attachments'] = ddwcwr_decode( $row['attachments'] );
			}

			return (array) $rows;
		}

		/**
		 * Find an attachment on a request (photos, message files, label).
		 *
		 * @param int    $request_id Request ID.
		 * @param string $key File key.
		 * @return array|null
		 */
		public function find_attachment( $request_id, $key ) {
			$request = $this->get_request( $request_id );

			if ( ! $request ) {
				return null;
			}

			if ( $request['label_file'] && $request['label_file'] === $key ) {
				return [
					'key'  => $key,
					/* translators: %s: request number */
					'name' => sprintf( __( 'return-label-%s', 'wpreturns' ), $request['number'] ) . '.' . pathinfo( $key, PATHINFO_EXTENSION ),
				];
			}

			foreach ( $this->get_messages( $request_id, [ 'message', 'note' ] ) as $message ) {
				foreach ( $message['attachments'] as $file ) {
					if ( isset( $file['key'] ) && $file['key'] === $key ) {
						return $file + [ 'kind' => $message['kind'] ];
					}
				}
			}

			return null;
		}

		/**
		 * Whether the visitor may see a request: staff, the account owner, or a matching access key.
		 *
		 * @param array  $request Request.
		 * @param string $key Access key.
		 * @return bool
		 */
		public function can_view( $request, $key = '' ) {
			if ( ! $request ) {
				return false;
			}

			if ( ddwcwr_current_user_can() ) {
				return true;
			}

			if ( $request['customer_id'] && get_current_user_id() === (int) $request['customer_id'] ) {
				return true;
			}

			return '' !== (string) $key && hash_equals( (string) $request['access_key'], (string) $key );
		}

		/**
		 * Delete a request and everything attached to it.
		 *
		 * @param int $request_id Request ID.
		 * @return void
		 */
		public function delete_request( $request_id ) {
			global $wpdb;

			$upload = new \DDWCWPReturns\Helper\Upload\DDWCWR_Upload_Helper();
			$files  = [];

			foreach ( $this->get_messages( $request_id, [ 'message', 'note' ] ) as $message ) {
				$files = array_merge( $files, $message['attachments'] );
			}

			$request = $this->get_request( $request_id );

			if ( $request && $request['label_file'] ) {
				$files[] = [ 'key' => $request['label_file'] ];
			}

			$upload->delete_files( $files );

			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->delete( $wpdb->prefix . 'ddwcwr_messages', [ 'request_id' => absint( $request_id ) ], [ '%d' ] );
			$wpdb->delete( $wpdb->prefix . 'ddwcwr_request_items', [ 'request_id' => absint( $request_id ) ], [ '%d' ] );
			$wpdb->delete( $wpdb->prefix . 'ddwcwr_requests', [ 'id' => absint( $request_id ) ], [ '%d' ] );
			// phpcs:enable

			do_action( 'ddwcwr_request_deleted', $request_id );
		}
	}
}
