<?php
/**
 * Dashboard Template
 *
 * Builds the request figures and renders them through the shared devdiggers-framework dashboard builder.
 *
 * @package WPReturns
 * @version 1.0.0
 */

namespace DDWCWPReturns\Templates\Admin\Dashboard;

use DDWCWPReturns\Helper\Dashboard\DDWCWR_Dashboard_Helper;
use DDWCWPReturns\Helper\Request\DDWCWR_Request_Helper;
use DevDiggers\Framework\Includes\DDFW_Dashboard;

defined( 'ABSPATH' ) || exit();

if ( ! class_exists( 'DDWCWR_Dashboard_Template' ) ) {
	/**
	 * Dashboard template class
	 */
	class DDWCWR_Dashboard_Template {
		/**
		 * Dashboard data.
		 *
		 * @var array
		 */
		protected $data;

		/**
		 * Construct
		 */
		public function __construct() {
			if ( ! class_exists( '\DevDiggers\Framework\Includes\DDFW_Dashboard' ) ) {
				return;
			}

			$this->data = ( new DDWCWR_Dashboard_Helper() )->get_dashboard_data();

			$this->render();
		}

		/**
		 * Render.
		 *
		 * @return void
		 */
		protected function render() {
			$summary = $this->data['summary'];
			$label   = $this->data['date_range']['label'];
			$open    = ( new DDWCWR_Request_Helper() )->get_requests_count( [ 'open' => true ] );

			new DDFW_Dashboard(
				[
					'columns'       => 5,
					'header'        => [
						/* translators: %s: current user display name. */
						'welcome'  => esc_html__( 'Welcome back, %s! 👋🏻', 'wpreturns' ),
						'subtitle' => esc_html__( 'Here\'s how returns, cancellations and EU withdrawals are going.', 'wpreturns' ),
						'actions'  => [
							[
								'label'   => esc_html__( 'Open requests', 'wpreturns' ),
								'url'     => admin_url( 'admin.php?page=ddwcwr-dashboard&menu=requests&status=open' ),
								'primary' => true,
							],
						],
					],
					'summary_cards' => [
						[
							'title'       => esc_html__( 'Requests', 'wpreturns' ),
							'value'       => $summary['requests']['value'],
							'change'      => $summary['requests']['change'],
							'is_positive' => $summary['requests']['change'] <= 0,
							'previous'    => number_format_i18n( $summary['requests']['previous'] ),
							'note'        => esc_html__( 'Returns and cancellations', 'wpreturns' ),
							'icon'        => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none"><path d="M3 12a9 9 0 1 0 3-6.7L3 8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M3 3v5h5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>',
						],
						[
							'title'      => esc_html__( 'Open now', 'wpreturns' ),
							'value'      => $open,
							'value_type' => 'text',
							'note'       => esc_html__( 'Waiting for you or the customer', 'wpreturns' ),
							'icon'       => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none"><path d="M21 8 12 3 3 8v8l9 5 9-5V8Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><path d="m3 8 9 5 9-5M12 13v8" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/></svg>',
						],
						[
							'title'       => esc_html__( 'Refunded', 'wpreturns' ),
							'value'       => wc_price( $summary['refunded']['value'] ),
							'value_type'  => 'html',
							'change'      => $summary['refunded']['change'],
							'is_positive' => $summary['refunded']['change'] <= 0,
							'previous'    => wc_price( $summary['refunded']['previous'] ),
							'icon'        => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>',
						],
						[
							'title'       => esc_html__( 'EU withdrawals', 'wpreturns' ),
							'value'       => $summary['withdrawals']['value'],
							'change'      => $summary['withdrawals']['change'],
							'is_positive' => $summary['withdrawals']['change'] <= 0,
							'previous'    => number_format_i18n( $summary['withdrawals']['previous'] ),
							'icon'        => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2"/><path d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18" stroke="currentColor" stroke-width="2"/></svg>',
						],
						[
							'title'       => esc_html__( 'Time to resolve', 'wpreturns' ),
							'value'       => $this->format_duration( $summary['hours']['value'], $this->data['resolved'][0] ),
							'value_type'  => 'text',
							'change'      => $summary['hours']['change'],
							'is_positive' => $summary['hours']['change'] <= 0,
							'previous'    => $this->format_duration( $summary['hours']['previous'], $this->data['resolved'][1] ),
							'note'        => esc_html__( 'Average, completed requests', 'wpreturns' ),
							'icon'        => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2"/><path d="M12 7v5l3 3" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>',
						],
					],
					'charts'        => [
						[
							'id'         => 'trend',
							'title'      => esc_html__( 'Requests over time', 'wpreturns' ),
							'date_label' => $label,
							'full_width' => true,
							'type'       => 'line',
							'data'       => $this->data['trend'],
							'x_key'      => 'date',
							'series'     => [
								[
									'key'   => 'requests',
									'label' => esc_html__( 'Requests', 'wpreturns' ),
									'color' => '#0256ff',
								],
							],
							'empty'      => [
								'title' => esc_html__( 'No requests yet', 'wpreturns' ),
								'desc'  => esc_html__( 'Requests will show here as customers submit them.', 'wpreturns' ),
							],
						],
					],
					'widgets'       => [
						[
							'title'  => esc_html__( 'Latest requests', 'wpreturns' ),
							'render' => [ $this, 'render_recent_requests' ],
						],
						[
							'title'  => esc_html__( 'Return analytics', 'wpreturns' ),
							'render' => [ $this, 'render_analytics_upgrade' ],
						],
					],
				]
			);
		}

		/**
		 * What the Pro analytics add, in place of the reports.
		 *
		 * @return void
		 */
		public function render_analytics_upgrade() {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inside the framework helper.
			echo ddfw_kses_form_html(
				ddwcwr_get_upgrade_card(
					'analytics',
					[
						'heading'       => esc_html__( 'See why items come back', 'wpreturns' ),
						'description'   => esc_html__( 'Find the products, reasons and categories that cost you the most, and the revenue store credit and exchanges kept in your shop.', 'wpreturns' ),
						'list_features' => [
							esc_html__( 'Return rate against your paid orders', 'wpreturns' ),
							esc_html__( 'Revenue kept through store credit and exchanges', 'wpreturns' ),
							esc_html__( 'Top reasons, resolutions and categories', 'wpreturns' ),
							esc_html__( 'Most returned products with value', 'wpreturns' ),
							esc_html__( 'CSV export of every request and item', 'wpreturns' ),
						],
					]
				)
			);
		}

		/**
		 * Hours as "5 hours" or "2.5 days".
		 *
		 * @param float $hours Hours.
		 * @param int   $resolved Completed requests in the period.
		 * @return string
		 */
		protected function format_duration( $hours, $resolved ) {
			if ( ! $resolved ) {
				return '—';
			}

			if ( $hours < 1 ) {
				return __( 'Under an hour', 'wpreturns' );
			}

			if ( $hours < 24 ) {
				$hours = max( 0, (int) round( $hours ) );

				/* translators: %d: number of hours */
				return sprintf( _n( '%d hour', '%d hours', $hours, 'wpreturns' ), $hours );
			}

			$days = round( $hours / 24, 1 );

			/* translators: %s: number of days */
			return sprintf( _n( '%s day', '%s days', (int) ceil( $days ), 'wpreturns' ), number_format_i18n( $days, 1 ) );
		}

		/**
		 * Latest requests widget.
		 *
		 * @return void
		 */
		public function render_recent_requests() {
			$items = [];
			$types = ddwcwr_get_request_types();

			foreach ( ( new DDWCWR_Request_Helper() )->get_requests( [ 'limit' => 6 ] ) as $request ) {
				$items[] = [
					'title' => $request['number'] . ' · ' . ( $request['customer_name'] ? $request['customer_name'] : $request['customer_email'] ),
					/* translators: %s: human time difference */
					'meta'  => ( isset( $types[ $request['type'] ] ) ? $types[ $request['type'] ] : $request['type'] ) . ' · ' . sprintf( __( '%s ago', 'wpreturns' ), human_time_diff( strtotime( $request['created'] ), current_time( 'timestamp' ) ) ), // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested
					'value' => ddwcwr_get_status_badge( $request['status'] ),
					'url'   => admin_url( 'admin.php?page=ddwcwr-dashboard&menu=requests&request-id=' . absint( $request['id'] ) ),
				];
			}

			DDFW_Dashboard::render_list( $items, __( 'No requests yet.', 'wpreturns' ), false );
		}
	}
}
