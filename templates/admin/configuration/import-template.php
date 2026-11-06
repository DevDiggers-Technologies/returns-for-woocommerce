<?php
/**
 * Import screen: one card per returns plugin DevDiggers Returns found data from.
 *
 * @package DevDiggers Returns
 * @version 1.0.0
 */

namespace DDWCWPReturns\Templates\Admin\Configuration;

use DDWCWPReturns\Helper\Import\DDWCWR_Import_Helper;

defined( 'ABSPATH' ) || exit();

if ( ! class_exists( 'DDWCWR_Import_Template' ) ) {
	/**
	 * Import template class
	 */
	class DDWCWR_Import_Template {
		/**
		 * Construct
		 */
		public function __construct() {
			$sources = ( new DDWCWR_Import_Helper() )->get_sources();
			$found   = array_filter(
				$sources,
				function ( $source ) {
					return $source['count'] > 0;
				}
			);
			$others  = array_diff_key( $sources, $found );
			?>
			<div class="ddwcwr-import">
				<div class="ddfw-fields-section ddwcwr-import-intro">
					<?php
					ddfw_fields_heading(
						[
							'heading'     => esc_html__( 'Import from another plugin', 'devdiggers-returns' ),
							'description' => esc_html__( 'Bring your old return, cancellation and EU withdrawal requests into DevDiggers Returns, with their items, status, dates and messages, so your history stays in one place.', 'devdiggers-returns' ),
						]
					);
					?>
					<ul class="ddwcwr-import-promises">
						<li><?php $this->icon( 'shield' ); ?><span><strong><?php esc_html_e( 'Read-only', 'devdiggers-returns' ); ?></strong> <?php esc_html_e( 'The other plugin\'s data is never changed or deleted.', 'devdiggers-returns' ); ?></span></li>
						<li><?php $this->icon( 'repeat' ); ?><span><strong><?php esc_html_e( 'Never twice', 'devdiggers-returns' ); ?></strong> <?php esc_html_e( 'Run it as often as you like; imported requests are skipped.', 'devdiggers-returns' ); ?></span></li>
						<li><?php $this->icon( 'mail' ); ?><span><strong><?php esc_html_e( 'No emails', 'devdiggers-returns' ); ?></strong> <?php esc_html_e( 'Customers are not notified about old requests.', 'devdiggers-returns' ); ?></span></li>
					</ul>
				</div>

				<?php if ( ! $found ) : ?>
					<div class="ddfw-fields-section">
						<?php
						ddfw_print_empty_state(
							[
								'title'       => esc_html__( 'No other returns plugin found', 'devdiggers-returns' ),
								'description' => esc_html__( 'DevDiggers Returns looks for requests from the plugins below, even after they are deactivated. Nothing to bring over here.', 'devdiggers-returns' ),
							]
						);
						?>
					</div>
				<?php else : ?>
					<?php $this->summary( $found ); ?>
					<div class="ddwcwr-import-grid">
						<?php
						foreach ( $found as $slug => $source ) {
							$this->card( $slug, $source );
						}
						?>
					</div>
					<?php $this->next_steps( $found ); ?>
				<?php endif; ?>

				<?php if ( $others ) : ?>
					<div class="ddwcwr-import-supported">
						<h4><?php echo $found ? esc_html__( 'Also supported', 'devdiggers-returns' ) : esc_html__( 'Supported plugins', 'devdiggers-returns' ); ?></h4>
						<ul>
							<?php foreach ( $others as $source ) : ?>
								<li><?php echo esc_html( $source['label'] ); ?> <span><?php echo esc_html( $source['vendor'] ); ?></span></li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php endif; ?>
			</div>
			<?php
		}

		/**
		 * Totals across the plugins found.
		 *
		 * @param array $found Sources with data.
		 * @return void
		 */
		protected function summary( $found ) {
			$tiles = [
				[
					'icon'  => 'plug',
					'label' => _n( 'Plugin with data', 'Plugins with data', count( $found ), 'devdiggers-returns' ),
					'value' => count( $found ),
				],
				[
					'icon'  => 'inbox',
					'label' => __( 'Requests found', 'devdiggers-returns' ),
					'value' => array_sum( wp_list_pluck( $found, 'count' ) ),
				],
				[
					'icon'  => 'check',
					'label' => __( 'Imported to DevDiggers Returns', 'devdiggers-returns' ),
					'value' => array_sum( wp_list_pluck( $found, 'imported' ) ),
					'class' => 'ddwcwr-import-total-imported',
				],
			];
			?>
			<div class="ddwcwr-import-summary">
				<?php foreach ( $tiles as $tile ) : ?>
					<div class="ddwcwr-import-stat <?php echo esc_attr( isset( $tile['class'] ) ? $tile['class'] : '' ); ?>">
						<span class="ddwcwr-import-stat-icon"><?php $this->icon( $tile['icon'] ); ?></span>
						<span class="ddwcwr-import-stat-text">
							<span class="ddwcwr-import-stat-label"><?php echo esc_html( $tile['label'] ); ?></span>
							<strong class="ddwcwr-import-stat-value"><?php echo esc_html( number_format_i18n( $tile['value'] ) ); ?></strong>
						</span>
					</div>
				<?php endforeach; ?>
			</div>
			<?php
		}

		/**
		 * One plugin.
		 *
		 * @param string $slug   Source slug.
		 * @param array  $source Source data.
		 * @return void
		 */
		protected function card( $slug, $source ) {
			$types = [
				'return'       => __( 'Returns', 'devdiggers-returns' ),
				'cancellation' => __( 'Cancellations', 'devdiggers-returns' ),
				'withdrawal'   => __( 'EU withdrawals', 'devdiggers-returns' ),
				'warranty'     => __( 'Warranty claims', 'devdiggers-returns' ),
			];
			$left  = max( 0, $source['count'] - $source['imported'] );
			$done  = $source['imported'] > 0 && ( $source['last_run'] || ! $left );

			if ( $done ) {
				$state = 'done';
				/* translators: %s: date */
				$badge = $source['last_run'] ? sprintf( __( 'Imported %s', 'devdiggers-returns' ), date_i18n( _x( 'M j', 'short import date', 'devdiggers-returns' ), $source['last_run'] ) ) : __( 'Imported', 'devdiggers-returns' );
			} elseif ( $source['imported'] > 0 ) {
				$state = 'partial';
				$badge = __( 'Partly imported', 'devdiggers-returns' );
			} else {
				$state = 'ready';
				$badge = __( 'Ready to import', 'devdiggers-returns' );
			}

			if ( $done ) {
				$button = __( 'Import new requests', 'devdiggers-returns' );
			} else {
				/* translators: %s: number of requests */
				$button = sprintf( _n( 'Import %s request', 'Import %s requests', $left, 'devdiggers-returns' ), number_format_i18n( $left ) );
			}

			$initials = strtoupper(
				implode(
					'',
					array_map(
						function ( $word ) {
							return mb_substr( $word, 0, 1 );
						},
						array_slice( preg_split( '/\s+/', trim( preg_replace( '/[^\p{L}\s]+/u', ' ', $source['vendor'] ) ) ), 0, 2 )
					)
				)
			);
			$percent  = $source['count'] ? min( 100, round( $source['imported'] / $source['count'] * 100 ) ) : 0;
			?>
			<div class="ddwcwr-import-card is-<?php echo esc_attr( $state ); ?>" data-source="<?php echo esc_attr( $slug ); ?>" data-count="<?php echo esc_attr( $source['count'] ); ?>" data-imported="<?php echo esc_attr( $source['imported'] ); ?>">
				<div class="ddwcwr-import-card-head">
					<?php if ( file_exists( DDWCWR_PLUGIN_FILE . 'assets/images/import/' . $slug . '.webp' ) ) : ?>
						<img class="ddwcwr-import-logo" src="<?php echo esc_url( DDWCWR_PLUGIN_URL . 'assets/images/import/' . $slug . '.webp' ); ?>" width="48" height="48" alt="" loading="lazy" />
					<?php else : ?>
						<span class="ddwcwr-import-logo" aria-hidden="true"><?php echo esc_html( $initials ); ?></span>
					<?php endif; ?>
					<div class="ddwcwr-import-card-title">
						<h3><?php echo esc_html( $source['label'] ); ?></h3>
						<p>
							<?php
							/* translators: %s: plugin vendor */
							echo esc_html( sprintf( __( 'by %s', 'devdiggers-returns' ), $source['vendor'] ) );
							?>
							<span class="ddwcwr-import-plugin-state <?php echo $source['active'] ? 'is-active' : ''; ?>">
								<?php echo $source['active'] ? esc_html__( 'Active', 'devdiggers-returns' ) : esc_html__( 'Deactivated', 'devdiggers-returns' ); ?>
							</span>
						</p>
					</div>
					<span class="ddwcwr-status ddwcwr-import-badge"><?php echo esc_html( $badge ); ?></span>
				</div>

				<ul class="ddwcwr-import-types">
					<?php foreach ( $source['types'] as $type ) : ?>
						<li><?php echo esc_html( $types[ $type ] ); ?></li>
					<?php endforeach; ?>
				</ul>

				<?php
				/* translators: %s: number of requests */
				$found_text = sprintf( _n( '<strong>%s</strong> request found', '<strong>%s</strong> requests found', $source['count'], 'devdiggers-returns' ), number_format_i18n( $source['count'] ) );
				/* translators: %s: number of requests */
				$imported_text = sprintf( __( '<strong>%s</strong> imported', 'devdiggers-returns' ), '<span class="ddwcwr-import-imported-count">' . number_format_i18n( $source['imported'] ) . '</span>' );
				$allowed       = [
					'strong' => [],
					'span'   => [ 'class' => [] ],
				];
				?>
				<div class="ddwcwr-import-figures">
					<span><?php echo wp_kses( $found_text, $allowed ); ?></span>
					<span class="ddwcwr-import-imported"><?php echo wp_kses( $imported_text, $allowed ); ?></span>
				</div>
				<div class="ddwcwr-import-progress" role="progressbar" aria-label="<?php esc_attr_e( 'Imported so far', 'devdiggers-returns' ); ?>" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?php echo esc_attr( $percent ); ?>">
					<span style="width: <?php echo esc_attr( $percent ); ?>%"></span>
				</div>
				<p class="ddwcwr-import-status" aria-live="polite"></p>

				<div class="ddwcwr-import-actions">
					<button type="button" class="button ddwcwr-import-run" data-dry-run="1"><?php esc_html_e( 'Check first', 'devdiggers-returns' ); ?></button>
					<button type="button" class="button <?php echo $done ? '' : 'button-primary'; ?> ddwcwr-import-run ddwcwr-import-go"><?php echo esc_html( $button ); ?></button>
				</div>
			</div>
			<?php
		}

		/**
		 * What to do after importing.
		 *
		 * @param array $found Sources with data.
		 * @return void
		 */
		protected function next_steps( $found ) {
			global $wpdb;

			$imported = array_sum( wp_list_pluck( $found, 'imported' ) );
			$active   = array_filter( wp_list_pluck( $found, 'active' ) );
			$final    = ddwcwr_get_final_statuses();
			$in       = implode( ',', array_fill( 0, count( $final ), '%s' ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- One %s per final status, built above.
			$open = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}ddwcwr_requests WHERE source = 'import' AND status NOT IN ({$in})", $final ) );
			?>
			<div class="ddwcwr-import-next<?php echo $imported ? '' : ' is-hidden'; ?>">
				<div class="ddwcwr-import-next-head">
					<span class="ddwcwr-import-next-icon"><?php $this->icon( 'check' ); ?></span>
					<div>
						<h4><?php esc_html_e( 'Next steps', 'devdiggers-returns' ); ?></h4>
						<p><?php esc_html_e( 'Your history is in DevDiggers Returns. Two things finish the move.', 'devdiggers-returns' ); ?></p>
					</div>
				</div>
				<ul class="ddwcwr-import-steps">
					<li class="<?php echo $open ? '' : 'is-done'; ?>">
						<span class="ddwcwr-import-step-mark">
							<?php
							if ( $open ) {
								echo '1';
							} else {
								$this->icon( 'tick' );
							}
							?>
						</span>
						<span class="ddwcwr-import-step-text">
							<strong><?php esc_html_e( 'Handle open requests in DevDiggers Returns', 'devdiggers-returns' ); ?></strong>
							<span>
								<?php
								if ( $open ) {
									/* translators: %s: number of requests */
									echo esc_html( sprintf( _n( '%s imported request is still open.', '%s imported requests are still open.', $open, 'devdiggers-returns' ), number_format_i18n( $open ) ) );
								} else {
									esc_html_e( 'Every imported request is closed. New requests now come in through DevDiggers Returns.', 'devdiggers-returns' );
								}
								?>
							</span>
						</span>
						<?php if ( $open ) : ?>
							<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=ddwcwr-dashboard&menu=requests' ) ); ?>"><?php esc_html_e( 'Open requests', 'devdiggers-returns' ); ?></a>
						<?php endif; ?>
					</li>
					<li class="<?php echo $active ? '' : 'is-done'; ?>">
						<span class="ddwcwr-import-step-mark">
							<?php
							if ( $active ) {
								echo '2';
							} else {
								$this->icon( 'tick' );
							}
							?>
						</span>
						<span class="ddwcwr-import-step-text">
							<strong><?php echo esc_html( _n( 'Switch off the old plugin', 'Switch off the old plugins', max( 1, count( $active ) ), 'devdiggers-returns' ) ); ?></strong>
							<span>
								<?php
								if ( $active ) {
									esc_html_e( 'Customers then see only one returns form. Your import stays, and orders keep their status in WooCommerce.', 'devdiggers-returns' );
								} else {
									esc_html_e( 'Done. Orders they gave their own status stay visible in WooCommerce.', 'devdiggers-returns' );
								}
								?>
							</span>
						</span>
						<?php if ( $active ) : ?>
							<a class="button" href="<?php echo esc_url( admin_url( 'plugins.php' ) ); ?>"><?php esc_html_e( 'Go to Plugins', 'devdiggers-returns' ); ?></a>
						<?php endif; ?>
					</li>
				</ul>
			</div>
			<?php
		}

		/**
		 * Print a small line icon.
		 *
		 * @param string $name Icon.
		 * @return void
		 */
		protected function icon( $name ) {
			$paths = [
				'shield' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/>',
				'repeat' => '<path d="M17 1l4 4-4 4"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><path d="M7 23l-4-4 4-4"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/>',
				'mail'   => '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 6-10 7L2 6"/><path d="M3 3l18 18"/>',
				'plug'   => '<path d="M9 2v6M15 2v6"/><path d="M6 8h12v4a6 6 0 0 1-12 0z"/><path d="M12 18v4"/>',
				'inbox'  => '<path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.5 5.1 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.5-6.9A2 2 0 0 0 16.8 4H7.2a2 2 0 0 0-1.7 1.1z"/>',
				'check'  => '<circle cx="12" cy="12" r="10"/><path d="m8 12 3 3 5-6"/>',
				'tick'   => '<path d="m5 12 5 5 9-10"/>',
			];

			echo '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . wp_kses( $paths[ $name ], ddfw_kses_allowed_svg_tags() ) . '</svg>';
		}
	}
}
