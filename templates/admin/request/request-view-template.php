<?php
/**
 * Request detail screen
 *
 * @package DevDiggers Returns
 * @version 1.0.0
 */

namespace DDWCWPReturns\Templates\Admin\Request;

use DDWCWPReturns\Helper\Request\DDWCWR_Request_Helper;
use DDWCWPReturns\Helper\Label\DDWCWR_Label_Helper;

defined( 'ABSPATH' ) || exit();

if ( ! class_exists( 'DDWCWR_Request_View_Template' ) ) {
	/**
	 * Request view class
	 */
	class DDWCWR_Request_View_Template {
		/**
		 * Request.
		 *
		 * @var array
		 */
		protected $request;

		/**
		 * Configuration.
		 *
		 * @var array
		 */
		protected $configuration;

		/**
		 * Construct
		 *
		 * @param int   $request_id Request ID.
		 * @param array $ddwcwr_configuration Configuration.
		 */
		public function __construct( $request_id, $ddwcwr_configuration ) {
			$this->configuration = $ddwcwr_configuration;
			$helper              = new DDWCWR_Request_Helper();
			$this->request       = $helper->get_request( $request_id );

			if ( ! $this->request ) {
				ddfw_print_empty_state(
					[
						'title'       => esc_html__( 'Request not found', 'devdiggers-returns-for-woocommerce' ),
						'description' => esc_html__( 'It may have been deleted.', 'devdiggers-returns-for-woocommerce' ),
					]
				);
				return;
			}

			$this->render( $helper );
		}

		/**
		 * Admin-post form opening.
		 *
		 * @param string $action_name Action, or empty when the submit buttons carry it.
		 * @param bool   $files Multipart.
		 * @return void
		 */
		protected function form_open( $action_name, $files = false ) {
			?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"<?php echo $files ? ' enctype="multipart/form-data"' : ''; ?> class="ddwcwr-admin-form">
				<input type="hidden" name="action" value="ddwcwr_request_action" />
				<?php if ( $action_name ) : ?>
					<input type="hidden" name="ddwcwr_do" value="<?php echo esc_attr( $action_name ); ?>" />
				<?php endif; ?>
				<input type="hidden" name="request_id" value="<?php echo esc_attr( $this->request['id'] ); ?>" />
				<?php wp_nonce_field( 'ddwcwr_request_action_' . $this->request['id'], 'ddwcwr_nonce' ); ?>
			<?php
		}

		/**
		 * File URL for staff.
		 *
		 * @param string $key File key.
		 * @return string
		 */
		protected function file_url( $key ) {
			return add_query_arg(
				[
					'action'  => 'ddwcwr_file',
					'request' => $this->request['id'],
					'file'     => $key,
					'_wpnonce' => wp_create_nonce( 'ddwcwr_file' ),
				],
				admin_url( 'admin-post.php' )
			);
		}

		/**
		 * Workflow steps for this request and the index of the current one.
		 *
		 * @return array steps, current.
		 */
		protected function get_steps() {
			$request = $this->request;
			$short   = 'cancellation' === $request['type'];
			$steps   = [
				'pending'  => __( 'Requested', 'devdiggers-returns-for-woocommerce' ),
				'approved' => __( 'Approved', 'devdiggers-returns-for-woocommerce' ),
			];

			if ( ! $short ) {
				$steps['awaiting_return'] = __( 'On its way back', 'devdiggers-returns-for-woocommerce' );
				$steps['received']        = __( 'Received', 'devdiggers-returns-for-woocommerce' );
			}

			$steps['completed'] = __( 'Resolved', 'devdiggers-returns-for-woocommerce' );
			$keys               = array_keys( $steps );
			$current            = array_search( $request['status'], $keys, true );

			if ( false === $current ) {
				$current = in_array( $request['status'], [ 'rejected', 'cancelled' ], true ) ? 0 : 1;
			}

			if ( ! empty( $request['meta']['processed'] ) && 'completed' !== $request['status'] ) {
				$current = max( $current, count( $keys ) - 2 );
			}

			return [ $steps, (int) $current ];
		}

		/**
		 * Refund form.
		 *
		 * @return void
		 */
		protected function render_process_form() {
			$request = $this->request;

			$this->form_open( 'process' );
			?>
				<div class="ddwcwr-fields-grid">
					<p>
						<label for="ddwcwr-amount"><?php esc_html_e( 'Refund amount', 'devdiggers-returns-for-woocommerce' ); ?></label>
						<input id="ddwcwr-amount" type="number" step="0.01" min="0" name="amount" value="<?php echo esc_attr( wc_format_decimal( $request['resolution_total'], wc_get_price_decimals() ) ); ?>" />
						<span class="description"><?php esc_html_e( 'The value of the returned items. Change it for a partial refund.', 'devdiggers-returns-for-woocommerce' ); ?></span>
					</p>
					<p>
						<label for="ddwcwr-refund-method"><?php esc_html_e( 'Refund goes to', 'devdiggers-returns-for-woocommerce' ); ?></label>
						<select id="ddwcwr-refund-method" name="refund_method">
							<option value="gateway" <?php selected( 'yes' === $this->configuration['refund_via_gateway'] ); ?>><?php esc_html_e( 'The original payment method', 'devdiggers-returns-for-woocommerce' ); ?></option>
							<option value="manual" <?php selected( 'yes' !== $this->configuration['refund_via_gateway'] ); ?>><?php esc_html_e( 'Record only (I pay it back myself)', 'devdiggers-returns-for-woocommerce' ); ?></option>
						</select>
						<span class="description"><?php esc_html_e( 'Gateways without refund support record the refund only.', 'devdiggers-returns-for-woocommerce' ); ?></span>
					</p>
					<p class="ddfw-upgrade-to-pro-tag-wrapper ddwcwr-locked-field">
						<label for="ddwcwr-locked-resolution"><?php esc_html_e( 'Resolve with', 'devdiggers-returns-for-woocommerce' ); ?> <?php echo wp_kses_post( ddfw_get_pro_tag() ); ?></label>
						<select id="ddwcwr-locked-resolution" disabled="disabled">
							<option><?php esc_html_e( 'Store credit, exchange or keep-it refund', 'devdiggers-returns-for-woocommerce' ); ?></option>
						</select>
						<span class="description"><?php esc_html_e( 'Keep the revenue: pay store credit with a bonus, or send a replacement instead.', 'devdiggers-returns-for-woocommerce' ); ?></span>
					</p>
				</div>
				<?php if ( 'withdrawal' === $request['type'] && ! in_array( $request['status'], [ 'received', 'completed' ], true ) ) : ?>
					<p class="description"><?php esc_html_e( 'For withdrawals you may wait until the goods arrive, or until the customer shows proof of sending, before refunding (Art. 13(3)).', 'devdiggers-returns-for-woocommerce' ); ?></p>
				<?php endif; ?>
				<p class="ddwcwr-actions"><button type="submit" class="button button-primary ddwcwr-confirm-process"><?php esc_html_e( 'Refund and resolve', 'devdiggers-returns-for-woocommerce' ); ?></button></p>
			</form>
			<?php
		}

		/**
		 * The "Next step" card: where the request is and the one action that moves it on.
		 *
		 * @return void
		 */
		protected function render_next_step() {
			$request               = $this->request;
			list( $steps, $index ) = $this->get_steps();
			$status                = $request['status'];
			$processed             = ! empty( $request['meta']['processed'] );
			$closed                = in_array( $status, [ 'rejected', 'cancelled' ], true );
			$short                 = 'cancellation' === $request['type'];
			?>
			<div class="ddfw-fields-section ddwcwr-next-step">
				<ol class="ddwcwr-steps<?php echo $closed ? ' is-closed' : ''; ?>">
					<?php $i = 0; ?>
					<?php foreach ( $steps as $label ) : ?>
						<li class="<?php echo esc_attr( $i < $index || ( $i === $index && 'completed' === $status ) ? 'is-done' : ( $i === $index ? 'is-current' : '' ) ); ?>"><span><?php echo esc_html( $label ); ?></span></li>
						<?php ++$i; ?>
					<?php endforeach; ?>
				</ol>

				<div class="ddwcwr-next-step-body">
					<?php if ( $closed ) : ?>
						<h3><?php echo 'rejected' === $status ? esc_html__( 'This request was rejected', 'devdiggers-returns-for-woocommerce' ) : esc_html__( 'This request was canceled', 'devdiggers-returns-for-woocommerce' ); ?></h3>
						<p><?php esc_html_e( 'Nothing else to do. You can reopen it from "Change status" in the sidebar.', 'devdiggers-returns-for-woocommerce' ); ?></p>

					<?php elseif ( $processed ) : ?>
						<h3><?php echo 'completed' === $status ? esc_html__( 'Resolved', 'devdiggers-returns-for-woocommerce' ) : esc_html__( 'Resolved, ready to close', 'devdiggers-returns-for-woocommerce' ); ?></h3>
						<div class="ddwcwr-result"><?php echo esc_html( isset( $request['meta']['result'] ) ? $request['meta']['result'] : __( 'Resolved.', 'devdiggers-returns-for-woocommerce' ) ); ?></div>
						<?php if ( 'completed' !== $status ) : ?>
							<?php $this->form_open( 'status' ); ?>
								<input type="hidden" name="status" value="completed" />
								<p class="ddwcwr-actions"><button type="submit" class="button button-primary"><?php esc_html_e( 'Mark as completed', 'devdiggers-returns-for-woocommerce' ); ?></button></p>
							</form>
						<?php endif; ?>

					<?php elseif ( 'pending' === $status ) : ?>
						<h3><?php esc_html_e( 'Review the request', 'devdiggers-returns-for-woocommerce' ); ?></h3>
						<p><?php esc_html_e( 'Check the items, reasons and photos below, then approve or reject. The customer gets an email either way.', 'devdiggers-returns-for-woocommerce' ); ?></p>
						<?php $this->form_open( '' ); ?>
							<label for="ddwcwr-decision-note" class="screen-reader-text"><?php esc_html_e( 'Message to the customer', 'devdiggers-returns-for-woocommerce' ); ?></label>
							<textarea id="ddwcwr-decision-note" name="note" rows="2" placeholder="<?php esc_attr_e( 'Message to the customer (optional), e.g. how to pack the items or why you rejected it', 'devdiggers-returns-for-woocommerce' ); ?>"></textarea>
							<p class="ddwcwr-actions">
								<button type="submit" name="ddwcwr_do" value="approve" class="button button-primary"><?php esc_html_e( 'Approve', 'devdiggers-returns-for-woocommerce' ); ?></button>
								<button type="submit" name="ddwcwr_do" value="reject" class="button ddwcwr-button-danger"><?php esc_html_e( 'Reject', 'devdiggers-returns-for-woocommerce' ); ?></button>
							</p>
						</form>

					<?php elseif ( ! $short && 'received' !== $status ) : ?>
						<h3><?php esc_html_e( 'Wait for the items to come back', 'devdiggers-returns-for-woocommerce' ); ?></h3>
						<p>
							<?php
							if ( DDWCWR_Label_Helper::get_label_url( $request ) ) {
								esc_html_e( 'The customer has a return label. When the package arrives, check the items and mark them as received.', 'devdiggers-returns-for-woocommerce' );
							} else {
								esc_html_e( 'The customer has no return label yet. Send one from "Return shipping" in the sidebar, or let them use their own carrier. When the package arrives, mark it as received.', 'devdiggers-returns-for-woocommerce' );
							}
							?>
						</p>
						<?php $this->form_open( 'receive' ); ?>
							<label class="ddwcwr-check"><input type="checkbox" name="restock" value="1" <?php checked( 'yes' === $this->configuration['restock_on_receive'] ); ?> /> <?php esc_html_e( 'Put the items back in stock (damaged and defective items are skipped)', 'devdiggers-returns-for-woocommerce' ); ?></label>
							<p class="ddwcwr-actions"><button type="submit" class="button button-primary"><?php esc_html_e( 'Mark as received', 'devdiggers-returns-for-woocommerce' ); ?></button></p>
						</form>
						<details class="ddwcwr-more">
							<summary><?php esc_html_e( 'Resolve without waiting for the items', 'devdiggers-returns-for-woocommerce' ); ?></summary>
							<?php $this->render_process_form(); ?>
						</details>

					<?php else : ?>
						<h3><?php echo 'received' === $status ? esc_html__( 'Items received: resolve the request', 'devdiggers-returns-for-woocommerce' ) : esc_html__( 'Resolve the request', 'devdiggers-returns-for-woocommerce' ); ?></h3>
						<p><?php esc_html_e( 'Refund the items. The customer is emailed and the request is completed.', 'devdiggers-returns-for-woocommerce' ); ?></p>
						<?php $this->render_process_form(); ?>
					<?php endif; ?>
				</div>
			</div>
			<?php
		}

		/**
		 * Render.
		 *
		 * @param DDWCWR_Request_Helper $helper Helper.
		 * @return void
		 */
		protected function render( $helper ) {
			$request    = $this->request;
			$order      = ddwcwr_order_adapter()->get_order( $request['order_id'] );
			$items      = $helper->get_request_items( $request['id'] );
			$messages   = $helper->get_messages( $request['id'], [ 'message', 'note', 'log' ] );
			$types      = ddwcwr_get_request_types();
			$currency   = [ 'currency' => $order ? $order->get_currency() : get_woocommerce_currency() ];
			$final      = in_array( $request['status'], ddwcwr_get_final_statuses(), true );
			$photos     = [];
			$conditions = ddwcwr_get_item_conditions();
			$note_shown = false;

			foreach ( $messages as $message ) {
				foreach ( $message['attachments'] as $file ) {
					if ( 'customer' === $message['author_type'] ) {
						$photos[] = $file;
					}
				}
			}

			ddwcwr_print_admin_notice();
			?>
			<div class="ddwcwr-request-screen">
				<div class="ddfw-edit-screen-bar">
					<div class="ddfw-edit-screen-bar-title">
						<div class="ddfw-edit-screen-bar-heading">
							<h2><?php echo esc_html( $request['number'] ); ?> <?php echo wp_kses_post( ddwcwr_get_status_badge( $request['status'] ) ); ?></h2>
							<a class="ddfw-edit-screen-back" href="<?php echo esc_url( admin_url( 'admin.php?page=ddwcwr-dashboard&menu=' . ( 'withdrawal' === $request['type'] ? 'withdrawals' : 'requests' ) ) ); ?>">
								<svg width="15" height="15" viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false"><path d="M15 19.5 7.7 12.2l7.3-7.3" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"></path></svg>
								<?php echo 'withdrawal' === $request['type'] ? esc_html__( 'Withdrawals', 'devdiggers-returns-for-woocommerce' ) : esc_html__( 'Requests', 'devdiggers-returns-for-woocommerce' ); ?>
							</a>
						</div>
						<p>
							<?php
							/* translators: 1: type, 2: source, 3: date */
							echo esc_html( sprintf( __( '%1$s · via %2$s · %3$s', 'devdiggers-returns-for-woocommerce' ), isset( $types[ $request['type'] ] ) ? $types[ $request['type'] ] : $request['type'], $request['source'], date_i18n( wc_date_format() . ' ' . wc_time_format(), strtotime( $request['created'] ) ) ) );
							?>
						</p>
					</div>
					<div class="ddfw-edit-screen-bar-actions">
						<?php if ( $order ) : ?>
							<a class="button" href="<?php echo esc_url( $order->get_edit_order_url() ); ?>">
								<?php
								/* translators: %s: order number */
								echo esc_html( sprintf( __( 'Order #%s', 'devdiggers-returns-for-woocommerce' ), $order->get_order_number() ) );
								?>
							</a>
						<?php endif; ?>
						<?php if ( 'cancellation' !== $request['type'] ) : ?>
							<span class="ddfw-upgrade-to-pro-tag-wrapper ddwcwr-locked-action">
								<button type="button" class="button" disabled="disabled"><?php esc_html_e( 'Print return slip', 'devdiggers-returns-for-woocommerce' ); ?></button>
								<?php echo wp_kses_post( ddfw_get_pro_tag() ); ?>
							</span>
						<?php endif; ?>
					</div>
				</div>

				<div class="ddfw-edit-screen">
					<div class="ddfw-edit-screen-main">
						<?php $this->render_next_step(); ?>

						<?php if ( 'withdrawal' === $request['type'] ) : ?>
							<div class="ddfw-fields-section ddwcwr-statement">
								<?php
								ddfw_fields_heading(
									[
										'heading'     => esc_html__( 'Withdrawal statement', 'devdiggers-returns-for-woocommerce' ),
										'description' => esc_html__( 'Received through the "Withdraw from contract here" function. The customer got the acknowledgement email automatically.', 'devdiggers-returns-for-woocommerce' ),
									]
								);
								?>
								<dl class="ddwcwr-dl">
									<dt><?php esc_html_e( 'Name given', 'devdiggers-returns-for-woocommerce' ); ?></dt>
									<dd><?php echo esc_html( isset( $request['meta']['statement_name'] ) ? $request['meta']['statement_name'] : $request['customer_name'] ); ?></dd>
									<dt><?php esc_html_e( 'Confirmation email', 'devdiggers-returns-for-woocommerce' ); ?></dt>
									<dd><?php echo esc_html( isset( $request['meta']['statement_email'] ) ? $request['meta']['statement_email'] : $request['customer_email'] ); ?></dd>
									<dt><?php esc_html_e( 'Received (UTC)', 'devdiggers-returns-for-woocommerce' ); ?></dt>
									<dd><?php echo esc_html( isset( $request['meta']['statement_utc'] ) ? $request['meta']['statement_utc'] : get_gmt_from_date( $request['created'] ) ); ?></dd>
								</dl>
							</div>
						<?php endif; ?>

						<div class="ddfw-fields-section">
							<?php ddfw_fields_heading( [ 'heading' => esc_html__( 'Items', 'devdiggers-returns-for-woocommerce' ) ] ); ?>
							<div class="ddfw-table-wrapper">
								<table class="widefat fixed ddfw-table ddwcwr-items-table">
									<thead>
										<tr>
											<th scope="col" class="ddwcwr-col-item"><?php esc_html_e( 'Item', 'devdiggers-returns-for-woocommerce' ); ?></th>
											<th scope="col"><?php esc_html_e( 'Qty', 'devdiggers-returns-for-woocommerce' ); ?></th>
											<th scope="col"><?php esc_html_e( 'Reason', 'devdiggers-returns-for-woocommerce' ); ?></th>
											<th scope="col"><?php esc_html_e( 'Condition', 'devdiggers-returns-for-woocommerce' ); ?></th>
											<th scope="col"><?php esc_html_e( 'Value', 'devdiggers-returns-for-woocommerce' ); ?></th>
										</tr>
									</thead>
									<tbody>
										<?php foreach ( $items as $item ) : ?>
											<?php $product = wc_get_product( $item['variation_id'] ? $item['variation_id'] : $item['product_id'] ); ?>
											<tr>
												<td class="ddwcwr-col-item">
													<div class="ddwcwr-item-cell">
														<?php echo $product ? wp_kses_post( $product->get_image( [ 40, 40 ] ) ) : ''; ?>
														<div>
															<strong><?php echo esc_html( $item['name'] ); ?></strong>
															<?php if ( $product && $product->get_sku() ) : ?>
																<span class="ddwcwr-list-meta"><?php echo esc_html( $product->get_sku() ); ?></span>
															<?php endif; ?>
															<?php if ( $item['restocked'] ) : ?>
																<span class="ddwcwr-list-meta">
																	<?php
																	/* translators: %d: quantity */
																	echo esc_html( sprintf( __( 'Restocked: %d', 'devdiggers-returns-for-woocommerce' ), $item['restocked'] ) );
																	?>
																</span>
															<?php endif; ?>
														</div>
													</div>
												</td>
												<td><?php echo esc_html( $item['qty'] ); ?></td>
												<td><?php echo esc_html( ddwcwr_get_reason_label( $item['reason'] ) ); ?></td>
												<td><?php echo esc_html( isset( $conditions[ $item['item_condition'] ] ) ? $conditions[ $item['item_condition'] ] : '—' ); ?></td>
												<td><?php echo wp_kses_post( wc_price( $item['line_total'], $currency ) ); ?></td>
											</tr>
										<?php endforeach; ?>
									</tbody>
								</table>
							</div>

							<?php if ( $request['customer_note'] ) : ?>
								<figure class="ddwcwr-note-card">
									<?php echo get_avatar( $request['customer_email'], 32 ); ?>
									<div>
										<figcaption>
											<?php
											/* translators: %s: customer name */
											echo esc_html( sprintf( __( '%s wrote', 'devdiggers-returns-for-woocommerce' ), $request['customer_name'] ? $request['customer_name'] : __( 'The customer', 'devdiggers-returns-for-woocommerce' ) ) );
											?>
										</figcaption>
										<blockquote><?php echo wp_kses_post( wpautop( esc_html( $request['customer_note'] ) ) ); ?></blockquote>
									</div>
								</figure>
							<?php endif; ?>

							<?php if ( $photos ) : ?>
								<h4><?php esc_html_e( 'Photos and files', 'devdiggers-returns-for-woocommerce' ); ?></h4>
								<ul class="ddwcwr-photos">
									<?php foreach ( $photos as $file ) : ?>
										<li>
											<a href="<?php echo esc_url( $this->file_url( $file['key'] ) ); ?>" target="_blank" rel="noopener">
												<?php if ( 0 === strpos( (string) $file['type'], 'image/' ) ) : ?>
													<img src="<?php echo esc_url( $this->file_url( $file['key'] ) ); ?>" alt="<?php echo esc_attr( $file['name'] ); ?>" loading="lazy" />
												<?php else : ?>
													<span class="ddwcwr-file-chip"><?php echo esc_html( $file['name'] ); ?></span>
												<?php endif; ?>
											</a>
										</li>
									<?php endforeach; ?>
								</ul>
							<?php endif; ?>
						</div>

						<div class="ddfw-fields-section" id="ddwcwr-conversation">
							<?php
							ddfw_fields_heading(
								[
									'heading'     => esc_html__( 'Conversation', 'devdiggers-returns-for-woocommerce' ),
									'description' => esc_html__( 'Messages are emailed to the customer. Internal notes are only visible to your team.', 'devdiggers-returns-for-woocommerce' ),
								]
							);
							?>
							<ol class="ddwcwr-thread">
								<?php foreach ( $messages as $message ) : ?>
									<?php
									// The opening note is shown with the items (its files with the photos).
									if ( 'log' === $message['kind'] || ( ! $note_shown && 'customer' === $message['author_type'] && '' !== trim( (string) $request['customer_note'] ) && trim( (string) $message['message'] ) === trim( (string) $request['customer_note'] ) ) ) {
										$note_shown = $note_shown || 'log' !== $message['kind'];
										continue;
									}

									$author = $message['author_id'] ? get_userdata( $message['author_id'] ) : false;
									$name   = 'customer' === $message['author_type'] ? ( $request['customer_name'] ? $request['customer_name'] : __( 'Customer', 'devdiggers-returns-for-woocommerce' ) ) : ( $author ? $author->display_name : ( 'staff' === $message['author_type'] ? get_bloginfo( 'name' ) : __( 'System', 'devdiggers-returns-for-woocommerce' ) ) );
									?>
									<li class="ddwcwr-thread-item ddwcwr-thread-<?php echo esc_attr( 'note' === $message['kind'] ? 'note' : $message['author_type'] ); ?>">
										<div class="ddwcwr-thread-meta">
											<strong><?php echo esc_html( $name ); ?></strong>
											<?php if ( 'note' === $message['kind'] ) : ?>
												<span class="ddwcwr-pill"><?php esc_html_e( 'Internal note', 'devdiggers-returns-for-woocommerce' ); ?></span>
											<?php endif; ?>
											<time><?php echo esc_html( date_i18n( wc_date_format() . ' ' . wc_time_format(), strtotime( $message['created'] ) ) ); ?></time>
										</div>
										<?php if ( '' !== trim( (string) $message['message'] ) ) : ?>
											<div class="ddwcwr-thread-body"><?php echo wp_kses_post( wpautop( esc_html( $message['message'] ) ) ); ?></div>
										<?php endif; ?>
										<?php foreach ( $message['attachments'] as $file ) : ?>
											<a class="ddwcwr-file-chip" href="<?php echo esc_url( $this->file_url( $file['key'] ) ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $file['name'] ); ?></a>
										<?php endforeach; ?>
									</li>
								<?php endforeach; ?>
							</ol>

							<?php $this->form_open( 'message', true ); ?>
								<label for="ddwcwr-admin-message" class="screen-reader-text"><?php esc_html_e( 'Message', 'devdiggers-returns-for-woocommerce' ); ?></label>
								<textarea id="ddwcwr-admin-message" name="message" rows="3" placeholder="<?php esc_attr_e( 'Write to the customer or add an internal note…', 'devdiggers-returns-for-woocommerce' ); ?>"></textarea>
								<div class="ddwcwr-form-row">
									<label class="ddwcwr-file-button">
										<input type="file" name="ddwcwr_files[]" multiple />
										<span class="dashicons dashicons-paperclip" aria-hidden="true"></span>
										<span class="ddwcwr-file-name" data-empty="<?php esc_attr_e( 'Attach files', 'devdiggers-returns-for-woocommerce' ); ?>"><?php esc_html_e( 'Attach files', 'devdiggers-returns-for-woocommerce' ); ?></span>
									</label>
									<label class="ddwcwr-check"><input type="checkbox" name="internal" value="1" /> <?php esc_html_e( 'Internal note (not sent to the customer)', 'devdiggers-returns-for-woocommerce' ); ?></label>
									<button type="submit" class="button button-primary"><?php esc_html_e( 'Send', 'devdiggers-returns-for-woocommerce' ); ?></button>
								</div>
							</form>
						</div>

						<details class="ddfw-fields-section ddwcwr-history-card" open>
							<summary><span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span><h3><?php esc_html_e( 'History', 'devdiggers-returns-for-woocommerce' ); ?></h3><span class="ddwcwr-list-meta"><?php esc_html_e( 'Every status change, label and refund, with who did it.', 'devdiggers-returns-for-woocommerce' ); ?></span></summary>
							<ol class="ddwcwr-history">
								<?php foreach ( array_reverse( $messages ) as $message ) : ?>
									<?php
									if ( 'log' !== $message['kind'] ) {
										continue;
									}

									$author = $message['author_id'] ? get_userdata( $message['author_id'] ) : false;
									?>
									<li>
										<span class="ddwcwr-history-text"><?php echo esc_html( $message['message'] ); ?></span>
										<span class="ddwcwr-list-meta"><?php echo esc_html( date_i18n( wc_date_format() . ' ' . wc_time_format(), strtotime( $message['created'] ) ) . ( $author ? ' · ' . $author->display_name : '' ) ); ?></span>
									</li>
								<?php endforeach; ?>
							</ol>
						</details>
					</div>

					<div class="ddfw-edit-screen-side">
						<div class="ddfw-fields-section">
							<?php ddfw_fields_heading( [ 'heading' => esc_html__( 'Summary', 'devdiggers-returns-for-woocommerce' ) ] ); ?>
							<dl class="ddwcwr-rows">
								<div><dt><?php esc_html_e( 'Items', 'devdiggers-returns-for-woocommerce' ); ?></dt><dd><?php echo wp_kses_post( wc_price( $request['items_total'], $currency ) ); ?></dd></div>
								<div class="is-total"><dt><?php esc_html_e( 'To pay back', 'devdiggers-returns-for-woocommerce' ); ?></dt><dd><?php echo wp_kses_post( wc_price( $request['resolution_total'], $currency ) ); ?></dd></div>
								<?php if ( (float) $request['refunded_total'] > 0 ) : ?>
									<div class="is-result"><dt><?php esc_html_e( 'Paid back', 'devdiggers-returns-for-woocommerce' ); ?></dt><dd><?php echo wp_kses_post( wc_price( $request['refunded_total'], $currency ) ); ?></dd></div>
								<?php endif; ?>
							</dl>
						</div>

						<div class="ddfw-fields-section">
							<?php ddfw_fields_heading( [ 'heading' => esc_html__( 'Customer', 'devdiggers-returns-for-woocommerce' ) ] ); ?>
							<div class="ddwcwr-person">
								<?php echo get_avatar( $request['customer_email'], 40 ); ?>
								<div>
									<strong><?php echo esc_html( $request['customer_name'] ? $request['customer_name'] : __( 'Guest', 'devdiggers-returns-for-woocommerce' ) ); ?></strong>
									<a href="mailto:<?php echo esc_attr( $request['customer_email'] ); ?>"><?php echo esc_html( $request['customer_email'] ); ?></a>
								</div>
							</div>
							<p class="ddwcwr-actions">
								<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=ddwcwr-dashboard&menu=requests&s=' . rawurlencode( $request['customer_email'] ) ) ); ?>"><?php esc_html_e( 'All their requests', 'devdiggers-returns-for-woocommerce' ); ?></a>
							</p>
							<p class="ddfw-upgrade-to-pro-tag-wrapper ddwcwr-locked-field">
								<span class="ddwcwr-field-title"><?php esc_html_e( 'Return rate and risk flags', 'devdiggers-returns-for-woocommerce' ); ?> <?php echo wp_kses_post( ddfw_get_pro_tag() ); ?></span>
								<span class="description"><?php esc_html_e( 'See how often this customer returns, flag serial returners and hold their requests for review.', 'devdiggers-returns-for-woocommerce' ); ?></span>
							</p>
						</div>

						<?php if ( 'cancellation' !== $request['type'] ) : ?>
							<div class="ddfw-fields-section">
								<?php
								ddfw_fields_heading(
									[
										'heading'     => esc_html__( 'Return shipping', 'devdiggers-returns-for-woocommerce' ),
										'description' => esc_html__( 'The label is emailed to the customer and shown on their request page.', 'devdiggers-returns-for-woocommerce' ),
									]
								);
								?>
								<?php $label_url = DDWCWR_Label_Helper::get_label_url( $request ); ?>
								<?php if ( $label_url ) : ?>
									<p class="ddwcwr-actions">
										<a class="button" href="<?php echo esc_url( $request['label_file'] ? $this->file_url( $request['label_file'] ) : $label_url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Download label', 'devdiggers-returns-for-woocommerce' ); ?></a>
									</p>
								<?php endif; ?>

								<?php if ( ! $final ) : ?>
									<p class="ddwcwr-actions ddfw-upgrade-to-pro-tag-wrapper ddwcwr-locked-action">
										<button type="button" class="button" disabled="disabled"><?php esc_html_e( 'Buy return label', 'devdiggers-returns-for-woocommerce' ); ?></button>
										<?php echo wp_kses_post( ddfw_get_pro_tag() ); ?>
										<span class="ddwcwr-list-meta"><?php esc_html_e( 'EasyPost or Shippo, emailed automatically', 'devdiggers-returns-for-woocommerce' ); ?></span>
									</p>
									<?php $this->form_open( 'upload_label', true ); ?>
										<span class="ddwcwr-field-title"><?php esc_html_e( 'Upload a label', 'devdiggers-returns-for-woocommerce' ); ?></span>
										<label class="ddwcwr-file-button ddwcwr-file-drop">
											<input id="ddwcwr-label-file" type="file" name="label_file" accept=".pdf,.png,.jpg,.jpeg" />
											<span class="dashicons dashicons-upload" aria-hidden="true"></span>
											<span class="ddwcwr-file-name" data-empty="<?php esc_attr_e( 'Choose a PDF or image', 'devdiggers-returns-for-woocommerce' ); ?>"><?php esc_html_e( 'Choose a PDF or image', 'devdiggers-returns-for-woocommerce' ); ?></span>
										</label>
										<span class="description"><?php esc_html_e( 'PDF or image from your carrier.', 'devdiggers-returns-for-woocommerce' ); ?></span>
										<p class="ddwcwr-actions"><button type="submit" class="button"><?php esc_html_e( 'Upload label', 'devdiggers-returns-for-woocommerce' ); ?></button></p>
									</form>
								<?php endif; ?>

								<?php $this->form_open( 'tracking' ); ?>
									<label for="ddwcwr-carrier"><?php esc_html_e( 'Carrier', 'devdiggers-returns-for-woocommerce' ); ?></label>
									<input id="ddwcwr-carrier" type="text" name="carrier" value="<?php echo esc_attr( $request['carrier'] ); ?>" />
									<label for="ddwcwr-tracking"><?php esc_html_e( 'Tracking number', 'devdiggers-returns-for-woocommerce' ); ?></label>
									<input id="ddwcwr-tracking" type="text" name="tracking_number" value="<?php echo esc_attr( $request['tracking_number'] ); ?>" />
									<span class="description"><?php esc_html_e( 'Customers can add this themselves too.', 'devdiggers-returns-for-woocommerce' ); ?></span>
									<p class="ddwcwr-actions"><button type="submit" class="button"><?php esc_html_e( 'Save tracking', 'devdiggers-returns-for-woocommerce' ); ?></button></p>
								</form>
							</div>
						<?php endif; ?>

						<div class="ddfw-fields-section">
							<?php
							ddfw_fields_heading(
								[
									'heading'     => esc_html__( 'Manage', 'devdiggers-returns-for-woocommerce' ),
									'description' => esc_html__( 'Most requests only need the "Next step" card. Use this to set any status yourself.', 'devdiggers-returns-for-woocommerce' ),
								]
							);
							?>
							<?php $this->form_open( 'status' ); ?>
								<label for="ddwcwr-status"><?php esc_html_e( 'Change status', 'devdiggers-returns-for-woocommerce' ); ?></label>
								<select id="ddwcwr-status" name="status">
									<?php foreach ( ddwcwr_get_statuses() as $slug => $status ) : ?>
										<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $request['status'], $slug ); ?>><?php echo esc_html( $status['label'] ); ?></option>
									<?php endforeach; ?>
								</select>
								<label for="ddwcwr-status-note"><?php esc_html_e( 'Message to the customer', 'devdiggers-returns-for-woocommerce' ); ?></label>
								<textarea id="ddwcwr-status-note" name="note" rows="2"></textarea>
								<span class="description"><?php esc_html_e( 'Optional. Sent with the status email.', 'devdiggers-returns-for-woocommerce' ); ?></span>
								<p class="ddwcwr-actions"><button type="submit" class="button"><?php esc_html_e( 'Update status', 'devdiggers-returns-for-woocommerce' ); ?></button></p>
							</form>

							<p class="ddfw-upgrade-to-pro-tag-wrapper ddwcwr-locked-field">
								<label for="ddwcwr-locked-assign"><?php esc_html_e( 'Assigned to', 'devdiggers-returns-for-woocommerce' ); ?> <?php echo wp_kses_post( ddfw_get_pro_tag() ); ?></label>
								<select id="ddwcwr-locked-assign" disabled="disabled"><option><?php esc_html_e( 'Unassigned', 'devdiggers-returns-for-woocommerce' ); ?></option></select>
								<span class="description"><?php esc_html_e( 'Share the work: assign requests to your team and filter by assignee.', 'devdiggers-returns-for-woocommerce' ); ?></span>
							</p>

							<?php if ( 'withdrawal' !== $request['type'] ) : ?>
								<?php $this->form_open( 'delete' ); ?>
									<p><button type="submit" class="button ddwcwr-button-delete ddwcwr-confirm-delete"><?php esc_html_e( 'Delete request', 'devdiggers-returns-for-woocommerce' ); ?></button></p>
								</form>
							<?php endif; ?>
						</div>
					</div>
				</div>
			</div>
			<?php
		}
	}
}
