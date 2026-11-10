<?php
/**
 * Dashboard helper: request figures for the selected date range.
 *
 * @package DevDiggers Returns
 * @version 1.0.0
 */

namespace DDWCWPReturns\Helper\Dashboard;

use DevDiggers\Framework\Includes\DDFW_Dashboard_Data;

defined( 'ABSPATH' ) || exit();

if ( ! class_exists( 'DDWCWR_Dashboard_Helper' ) ) {
	/**
	 * Dashboard helper class
	 */
	class DDWCWR_Dashboard_Helper {
		/**
		 * Date range.
		 *
		 * @var array
		 */
		protected $range;

		/**
		 * Construct
		 */
		public function __construct() {
			$this->range = DDFW_Dashboard_Data::get_date_range( '30_days' );
		}

		/**
		 * Previous period of the same length.
		 *
		 * @return array from, to.
		 */
		protected function get_previous_range() {
			$days = (int) ceil( ( strtotime( $this->range['to'] ) - strtotime( $this->range['from'] ) ) / DAY_IN_SECONDS ) + 1;

			return [
				'from' => gmdate( 'Y-m-d', strtotime( $this->range['from'] . ' -' . $days . ' days' ) ),
				'to'   => gmdate( 'Y-m-d', strtotime( $this->range['from'] . ' -1 day' ) ),
			];
		}

		/**
		 * Totals for a period.
		 *
		 * @param string $from From (Y-m-d).
		 * @param string $to To (Y-m-d).
		 * @return array
		 */
		protected function get_totals( $from, $to ) {
			global $wpdb;

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$row = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT SUM(CASE WHEN type <> 'withdrawal' THEN 1 ELSE 0 END) AS requests,
						SUM(CASE WHEN type = 'withdrawal' THEN 1 ELSE 0 END) AS withdrawals,
						COALESCE(SUM(refunded_total),0) AS refunded,
						SUM(CASE WHEN closed IS NOT NULL AND status = 'completed' THEN 1 ELSE 0 END) AS resolved
					FROM {$wpdb->prefix}ddwcwr_requests WHERE created BETWEEN %s AND %s",
					$from . ' 00:00:00',
					$to . ' 23:59:59'
				),
				ARRAY_A
			);

			// Time to resolve is averaged here: TIMESTAMPDIFF is MySQL only (SQLite, e.g. WordPress Playground, has no such function).
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$spans = $wpdb->get_results( $wpdb->prepare( "SELECT created, closed FROM {$wpdb->prefix}ddwcwr_requests WHERE closed IS NOT NULL AND status = 'completed' AND created BETWEEN %s AND %s", $from . ' 00:00:00', $to . ' 23:59:59' ), ARRAY_A );
			$hours = 0;

			foreach ( (array) $spans as $span ) {
				$hours += max( 0, strtotime( $span['closed'] ) - strtotime( $span['created'] ) ) / HOUR_IN_SECONDS;
			}

			$row          = array_map( 'floatval', (array) $row );
			$row['hours'] = $spans ? $hours / count( $spans ) : 0;

			return $row;
		}

		/**
		 * Percent change, null when there was nothing before.
		 *
		 * @param float $now Now.
		 * @param float $before Before.
		 * @return float|null
		 */
		protected function change( $now, $before ) {
			if ( $before > 0 ) {
				return round( ( $now - $before ) / $before * 100, 1 );
			}

			return $now ? null : 0;
		}

		/**
		 * All dashboard data.
		 *
		 * @return array
		 */
		public function get_dashboard_data() {
			global $wpdb;

			$previous = $this->get_previous_range();
			$now      = $this->get_totals( $this->range['from'], $this->range['to'] );
			$before   = $this->get_totals( $previous['from'], $previous['to'] );
			$summary  = [];

			foreach ( [ 'requests', 'withdrawals', 'refunded', 'hours' ] as $key ) {
				$summary[ $key ] = [
					'value'    => $now[ $key ],
					'previous' => $before[ $key ],
					'change'   => $this->change( $now[ $key ], $before[ $key ] ),
				];
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$rows     = $wpdb->get_results( $wpdb->prepare( "SELECT DATE(created) AS day, COUNT(*) AS requests FROM {$wpdb->prefix}ddwcwr_requests WHERE created BETWEEN %s AND %s GROUP BY DATE(created)", $this->range['from'] . ' 00:00:00', $this->range['to'] . ' 23:59:59' ), ARRAY_A );
			$requests = [];

			foreach ( (array) $rows as $row ) {
				$requests[ $row['day'] ] = (int) $row['requests'];
			}

			return [
				'summary'    => $summary,
				'resolved'   => [ $now['resolved'], $before['resolved'] ],
				'trend'      => DDFW_Dashboard_Data::build_time_series( $this->range['from'], $this->range['to'], [ 'requests' => $requests ] ),
				'date_range' => $this->range,
			];
		}
	}
}
