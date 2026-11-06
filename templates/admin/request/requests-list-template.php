<?php
/**
 * Requests List Template
 *
 * @package DevDiggers Returns
 * @version 1.0.0
 */

namespace DDWCWPReturns\Templates\Admin\Request;

use DDWCWPReturns\Helper\Request\DDWCWR_Request_Helper;

defined( 'ABSPATH' ) || exit();

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

if ( ! class_exists( 'DDWCWR_Requests_List_Template' ) ) {
	/**
	 * Requests list class
	 */
	class DDWCWR_Requests_List_Template extends \WP_List_Table {
		/**
		 * Request helper.
		 *
		 * @var DDWCWR_Request_Helper
		 */
		protected $request_helper;

		/**
		 * Fixed type (withdrawal screen).
		 *
		 * @var string
		 */
		protected $fixed_type;

		/**
		 * Class constructor
		 *
		 * @param string $fixed_type Fixed type.
		 */
		public function __construct( $fixed_type = '' ) {
			$this->request_helper = new DDWCWR_Request_Helper();
			$this->fixed_type     = $fixed_type;

			parent::__construct(
				[
					'singular' => 'ddwcwr-request',
					'plural'   => 'ddwcwr-requests',
					'ajax'     => false,
				]
			);
		}

		/**
		 * Current filters from the URL.
		 *
		 * @return array
		 */
		protected function get_filters() {
			// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only list filters.
			$filters = [
				'status'    => ! empty( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '',
				'type'      => $this->fixed_type ? $this->fixed_type : ( ! empty( $_GET['type'] ) ? sanitize_key( wp_unslash( $_GET['type'] ) ) : '' ),
				'search'    => ! empty( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '',
				'order_id'  => ! empty( $_GET['order-id'] ) ? absint( $_GET['order-id'] ) : '',
				'reason'    => ! empty( $_GET['reason'] ) ? sanitize_key( wp_unslash( $_GET['reason'] ) ) : '',
				'date_from' => ! empty( $_GET['from'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', sanitize_text_field( wp_unslash( $_GET['from'] ) ) ) ? sanitize_text_field( wp_unslash( $_GET['from'] ) ) . ' 00:00:00' : '',
				'date_to'   => ! empty( $_GET['to'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', sanitize_text_field( wp_unslash( $_GET['to'] ) ) ) ? sanitize_text_field( wp_unslash( $_GET['to'] ) ) . ' 23:59:59' : '',
			];
			// phpcs:enable

			if ( 'open' === $filters['status'] ) {
				$filters['status'] = '';
				$filters['open']   = true;
			}

			return $filters;
		}

		/**
		 * Prepare Items
		 *
		 * @return void
		 */
		public function prepare_items() {
			$this->_column_headers = $this->get_column_info();

			$filters  = $this->get_filters();
			$per_page = $this->get_items_per_page( 'ddwcwr_requests_per_page', 20 );
			$orderby  = ! empty( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : 'id'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$order    = ! empty( $_GET['order'] ) ? sanitize_key( wp_unslash( $_GET['order'] ) ) : 'desc'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$total    = $this->request_helper->get_requests_count( $filters );

			$this->set_pagination_args(
				[
					'total_items' => $total,
					'per_page'    => $per_page,
				]
			);

			$this->items = $this->request_helper->get_requests(
				$filters + [
					'orderby' => $orderby,
					'order'   => $order,
					'limit'   => $per_page,
					'offset'  => ( $this->get_pagenum() - 1 ) * $per_page,
				]
			);
		}

		/**
		 * Status views with counts.
		 *
		 * @return array
		 */
		protected function get_views() {
			$current = ! empty( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$menu    = $this->fixed_type ? 'withdrawals' : 'requests';

			return ddwcwr_get_list_views( $menu, $current, $this->fixed_type );
		}

		/**
		 * Filter dropdowns.
		 *
		 * @param string $which top|bottom.
		 * @return void
		 */
		protected function extra_tablenav( $which ) {
			if ( 'top' !== $which ) {
				return;
			}

			$filters = $this->get_filters();
			?>
			<div class="alignleft actions ddfw-upgrade-to-pro-tag-wrapper ddwcwr-locked-action">
				<label class="screen-reader-text" for="ddwcwr-locked-bulk"><?php esc_html_e( 'Bulk actions', 'devdiggers-returns' ); ?></label>
				<select id="ddwcwr-locked-bulk" disabled="disabled"><option><?php esc_html_e( 'Bulk actions', 'devdiggers-returns' ); ?></option></select>
				<?php echo wp_kses_post( ddfw_get_pro_tag() ); ?>
			</div>
			<div class="alignleft actions ddwcwr-filters">
				<?php if ( ! $this->fixed_type ) : ?>
					<label class="screen-reader-text" for="ddwcwr-filter-type"><?php esc_html_e( 'Filter by type', 'devdiggers-returns' ); ?></label>
					<select name="type" id="ddwcwr-filter-type" data-placeholder="<?php esc_attr_e( 'All types', 'devdiggers-returns' ); ?>">
						<option value=""><?php esc_html_e( 'All types', 'devdiggers-returns' ); ?></option>
						<?php foreach ( ddwcwr_get_request_types() as $slug => $label ) : ?>
							<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $filters['type'], $slug ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				<?php endif; ?>
				<?php if ( ! $this->fixed_type ) : ?>
				<label class="screen-reader-text" for="ddwcwr-filter-reason"><?php esc_html_e( 'Filter by reason', 'devdiggers-returns' ); ?></label>
				<select name="reason" id="ddwcwr-filter-reason" data-placeholder="<?php esc_attr_e( 'All reasons', 'devdiggers-returns' ); ?>">
					<option value=""><?php esc_html_e( 'All reasons', 'devdiggers-returns' ); ?></option>
					<?php foreach ( ddwcwr_get_reasons( false ) as $slug => $reason ) : ?>
						<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $filters['reason'], $slug ); ?>><?php echo esc_html( $reason['label'] ); ?></option>
					<?php endforeach; ?>
				</select>
				<?php endif; ?>
				<label class="screen-reader-text" for="ddwcwr-filter-from"><?php esc_html_e( 'From date', 'devdiggers-returns' ); ?></label>
				<input type="date" id="ddwcwr-filter-from" name="from" value="<?php echo esc_attr( substr( $filters['date_from'], 0, 10 ) ); ?>" />
				<label class="screen-reader-text" for="ddwcwr-filter-to"><?php esc_html_e( 'To date', 'devdiggers-returns' ); ?></label>
				<input type="date" id="ddwcwr-filter-to" name="to" value="<?php echo esc_attr( substr( $filters['date_to'], 0, 10 ) ); ?>" />
				<?php submit_button( __( 'Filter', 'devdiggers-returns' ), '', 'filter_action', false ); ?>
				<?php
				$active = $filters;
				unset( $active['status'], $active['open'] );

				if ( $this->fixed_type ) {
					unset( $active['type'] );
				}

				if ( array_filter( $active, static fn( $value ) => '' !== $value && false !== $value ) ) :
					?>
					<button type="button" class="button ddwcwr-reset-filters"><?php esc_html_e( 'Reset filters', 'devdiggers-returns' ); ?></button>
				<?php endif; ?>
			</div>
			<?php
		}

		/**
		 * No items.
		 *
		 * @return void
		 */
		public function no_items() {
			ddfw_print_empty_state(
				[
					'title'       => $this->fixed_type ? esc_html__( 'No withdrawals yet', 'devdiggers-returns' ) : esc_html__( 'No requests found', 'devdiggers-returns' ),
					'description' => $this->fixed_type ? esc_html__( 'Statements sent with the "Withdraw from contract here" button will appear here.', 'devdiggers-returns' ) : esc_html__( 'Returns and cancellations your customers submit will appear here.', 'devdiggers-returns' ),
				]
			);
		}

		/**
		 * Columns.
		 *
		 * @return array
		 */
		public function get_columns() {
			$columns = [
				'request'  => esc_html__( 'Request', 'devdiggers-returns' ),
				'customer' => esc_html__( 'Customer', 'devdiggers-returns' ),
				'items'    => esc_html__( 'Items', 'devdiggers-returns' ),
				'value'    => esc_html__( 'Value', 'devdiggers-returns' ),
				'status'   => esc_html__( 'Status', 'devdiggers-returns' ),
				'created'  => 'withdrawal' === $this->fixed_type ? esc_html__( 'Received', 'devdiggers-returns' ) : esc_html__( 'Date', 'devdiggers-returns' ),
			];

			return apply_filters( 'ddwcwr_requests_list_columns', $columns, $this->fixed_type );
		}

		/**
		 * Sortable columns.
		 *
		 * @return array
		 */
		public function get_sortable_columns() {
			return [
				'request' => [ 'id', true ],
				'value'   => [ 'items_total', false ],
				'status'  => [ 'status', false ],
				'created' => [ 'created', false ],
			];
		}

		/**
		 * Request column.
		 *
		 * @param array $item Item.
		 * @return string
		 */
		public function column_request( $item ) {
			$url     = admin_url( 'admin.php?page=ddwcwr-dashboard&menu=requests&request-id=' . absint( $item['id'] ) );
			$types   = ddwcwr_get_request_types();
			$order   = ddwcwr_order_adapter()->get_order( $item['order_id'] );
			$actions = [
				'view' => sprintf( '<a href="%s">%s</a>', esc_url( $url ), esc_html__( 'Open', 'devdiggers-returns' ) ),
			];

			if ( $order ) {
				$actions['order'] = sprintf( '<a href="%s">%s</a>', esc_url( $order->get_edit_order_url() ), esc_html__( 'Order', 'devdiggers-returns' ) );
			}

			return sprintf(
				'<a class="row-title" href="%1$s">%2$s</a><span class="ddwcwr-list-meta">%3$s · %4$s</span>%5$s',
				esc_url( $url ),
				esc_html( $item['number'] ),
				esc_html( isset( $types[ $item['type'] ] ) ? $types[ $item['type'] ] : $item['type'] ),
				/* translators: %s: order number */
				esc_html( sprintf( __( 'Order #%s', 'devdiggers-returns' ), $order ? $order->get_order_number() : $item['order_id'] ) ),
				$this->row_actions( $actions )
			);
		}

		/**
		 * Customer column.
		 *
		 * @param array $item Item.
		 * @return string
		 */
		public function column_customer( $item ) {
			return sprintf(
				'<strong>%1$s</strong><span class="ddwcwr-list-meta">%2$s</span>',
				esc_html( $item['customer_name'] ? $item['customer_name'] : __( 'Guest', 'devdiggers-returns' ) ),
				esc_html( $item['customer_email'] )
			);
		}

		/**
		 * Items column.
		 *
		 * @param array $item Item.
		 * @return string
		 */
		public function column_items( $item ) {
			$lines = [];

			foreach ( $this->request_helper->get_request_items( $item['id'] ) as $line ) {
				$lines[] = esc_html( $line['name'] . ' × ' . $line['qty'] ) . '<span class="ddwcwr-list-meta">' . esc_html( ddwcwr_get_reason_label( $line['reason'] ) ) . '</span>';
			}

			return implode( '', array_slice( $lines, 0, 3 ) ) . ( count( $lines ) > 3 ? '<span class="ddwcwr-list-meta">+' . absint( count( $lines ) - 3 ) . '</span>' : '' );
		}

		/**
		 * Value column.
		 *
		 * @param array $item Item.
		 * @return string
		 */
		public function column_value( $item ) {
			return wp_kses_post( wc_price( $item['items_total'] ) ) . ( (float) $item['refunded_total'] > 0 ? '<span class="ddwcwr-list-meta">' . esc_html__( 'Paid back:', 'devdiggers-returns' ) . ' ' . wp_kses_post( wc_price( $item['refunded_total'] ) ) . '</span>' : '' );
		}

		/**
		 * Status column.
		 *
		 * @param array $item Item.
		 * @return string
		 */
		public function column_status( $item ) {
			return ddwcwr_get_status_badge( $item['status'] );
		}

		/**
		 * Date column.
		 *
		 * @param array $item Item.
		 * @return string
		 */
		public function column_created( $item ) {
			$time = strtotime( $item['created'] );

			return sprintf(
				'<time datetime="%1$s" title="%2$s">%3$s</time>',
				esc_attr( mysql_to_rfc3339( $item['created'] ) ),
				esc_attr( date_i18n( wc_date_format() . ' ' . wc_time_format(), $time ) ),
				/* translators: %s: human time difference */
				esc_html( abs( current_time( 'timestamp' ) - $time ) < DAY_IN_SECONDS ? sprintf( __( '%s ago', 'devdiggers-returns' ), human_time_diff( $time, current_time( 'timestamp' ) ) ) : date_i18n( wc_date_format(), $time ) ) // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- Compared with a local mysql time.
			);
		}

		/**
		 * Default column.
		 *
		 * @param array  $item Item.
		 * @param string $column_name Column.
		 * @return string
		 */
		public function column_default( $item, $column_name ) {
			return '';
		}
	}
}
