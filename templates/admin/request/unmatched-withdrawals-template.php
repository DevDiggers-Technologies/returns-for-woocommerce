<?php
/**
 * Withdrawal statements that matched no order.
 *
 * @package DevDiggers Returns
 * @version 1.0.0
 */

namespace DDWCWPReturns\Templates\Admin\Request;

defined( 'ABSPATH' ) || exit();

if ( ! class_exists( 'DDWCWR_Unmatched_Withdrawals_Template' ) ) {
	/**
	 * Unmatched withdrawals class
	 */
	class DDWCWR_Unmatched_Withdrawals_Template {
		/**
		 * Construct
		 */
		public function __construct() {
			$log = array_reverse( (array) get_option( '_ddwcwr_unmatched_withdrawals', [] ) );
			?>
			<div class="ddwcwr-list-form">
				<ul class="subsubsub">
					<?php
					foreach ( ddwcwr_get_list_views( 'withdrawals', 'unmatched', 'withdrawal' ) as $view ) {
						echo '<li>' . wp_kses_post( $view ) . '</li>';
					}
					?>
				</ul>
			</div>
			<div class="ddwcwr-unmatched">
				<p class="description"><?php esc_html_e( 'These were sent with an order number and email that did not match any order, so no acknowledgement email went out. If one looks genuine, contact the customer and create the request yourself.', 'devdiggers-returns-for-woocommerce' ); ?></p>
				<?php if ( empty( $log ) ) : ?>
					<?php
					ddfw_print_empty_state(
						[
							'title'       => esc_html__( 'Nothing to review', 'devdiggers-returns-for-woocommerce' ),
							'description' => esc_html__( 'Every statement matched an order.', 'devdiggers-returns-for-woocommerce' ),
						]
					);
					?>
				<?php else : ?>
				<div class="ddfw-table-wrapper"><table class="widefat fixed striped ddfw-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Received (UTC)', 'devdiggers-returns-for-woocommerce' ); ?></th>
							<th><?php esc_html_e( 'Name', 'devdiggers-returns-for-woocommerce' ); ?></th>
							<th><?php esc_html_e( 'Order number given', 'devdiggers-returns-for-woocommerce' ); ?></th>
							<th><?php esc_html_e( 'Email given', 'devdiggers-returns-for-woocommerce' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $log as $entry ) : ?>
							<tr>
								<td><?php echo esc_html( $entry['date'] ); ?></td>
								<td><?php echo esc_html( $entry['name'] ); ?></td>
								<td><?php echo esc_html( $entry['order'] ); ?></td>
								<td><?php echo esc_html( $entry['email'] ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table></div>
					<?php if ( ddwcwr_current_user_can() ) : ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="ddwcwr_clear_unmatched" />
						<?php wp_nonce_field( 'ddwcwr_clear_unmatched', 'ddwcwr_nonce' ); ?>
						<p><button type="submit" class="button ddwcwr-confirm-delete"><?php esc_html_e( 'Clear list', 'devdiggers-returns-for-woocommerce' ); ?></button></p>
					</form>
				<?php endif; ?>
				<?php endif; ?>
			</div>
			<?php
		}
	}
}
