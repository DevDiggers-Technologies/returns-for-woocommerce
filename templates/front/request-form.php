<?php
/**
 * New request form.
 *
 * @package DevDiggers Returns
 * @version 1.0.0
 *
 * @var object $order         Order.
 * @var array  $order_data    Order summary.
 * @var string $type          Request type.
 * @var array  $items         Items with eligibility.
 * @var array  $reasons       Reasons.
 * @var array  $conditions    Item conditions.
 * @var array  $configuration Configuration.
 * @var array  $posted        Previously posted items.
 * @var string $note          Previously posted note.
 * @var string $back_url      Back link.
 * @var array  $token_fields  Guest token fields.
 */

defined( 'ABSPATH' ) || exit();

$ddwcwr_titles       = [
	/* translators: %s: order number */
	'return'       => __( 'Return items from order #%s', 'devdiggers-returns-for-woocommerce' ),
	/* translators: %s: order number */
	'cancellation' => __( 'Cancel order #%s', 'devdiggers-returns-for-woocommerce' ),
];
$ddwcwr_has_eligible = (bool) array_filter( wp_list_pluck( $items, 'eligible' ) );
?>
<div class="ddwcwr-request-form-wrap">
	<a class="ddwcwr-back" href="<?php echo esc_url( $back_url ); ?>"><?php echo wp_kses( ddwcwr_icon( 'back', 16 ), ddwcwr_kses_icon() ); ?><?php esc_html_e( 'Back', 'devdiggers-returns-for-woocommerce' ); ?></a>
	<header class="ddwcwr-hero">
		<span class="ddwcwr-eyebrow"><?php echo esc_html( date_i18n( wc_date_format(), $order_data['date_created'] ) ); ?></span>
		<h2 class="ddwcwr-form-title"><?php echo esc_html( sprintf( $ddwcwr_titles[ $type ], $order_data['number'] ) ); ?></h2>
		<p><?php echo 'cancellation' === $type ? esc_html__( 'Choose what to cancel. We stop the order before it ships and refund you.', 'devdiggers-returns-for-woocommerce' ) : esc_html__( 'Choose the items and tell us what went wrong. It takes about a minute.', 'devdiggers-returns-for-woocommerce' ); ?></p>
	</header>

	<?php if ( ! $ddwcwr_has_eligible ) : ?>
		<div class="ddwcwr-empty">
			<?php echo wp_kses( ddwcwr_icon( 'alert', 32 ), ddwcwr_kses_icon() ); ?>
			<h3><?php esc_html_e( 'Nothing in this order can be requested right now', 'devdiggers-returns-for-woocommerce' ); ?></h3>
			<ul>
				<?php foreach ( $items as $ddwcwr_item ) : ?>
					<li><?php echo esc_html( $ddwcwr_item['name'] . ': ' . $ddwcwr_item['message'] ); ?></li>
				<?php endforeach; ?>
			</ul>
		</div>
		<?php
		return;
	endif;
	?>

	<form method="post" enctype="multipart/form-data" class="ddwcwr-form ddwcwr-request-form" data-type="<?php echo esc_attr( $type ); ?>">
		<fieldset class="ddwcwr-step">
			<legend class="ddwcwr-step-title"><span class="ddwcwr-step-number" aria-hidden="true">1</span><?php echo 'cancellation' === $type ? esc_html__( 'Items to cancel', 'devdiggers-returns-for-woocommerce' ) : esc_html__( 'Which items?', 'devdiggers-returns-for-woocommerce' ); ?></legend>

			<ul class="ddwcwr-items">
				<?php foreach ( $items as $ddwcwr_item_id => $ddwcwr_item ) : ?>
					<?php
					$ddwcwr_prev     = isset( $posted[ $ddwcwr_item_id ] ) ? $posted[ $ddwcwr_item_id ] : [];
					$ddwcwr_checked  = ! empty( $ddwcwr_prev['selected'] ) || ( 'cancellation' === $type && $ddwcwr_item['eligible'] ) || ( 1 === count( $items ) && $ddwcwr_item['eligible'] );
					$ddwcwr_field_id = 'ddwcwr-item-' . $ddwcwr_item_id;
					?>
					<li class="ddwcwr-item<?php echo $ddwcwr_item['eligible'] ? '' : ' is-disabled'; ?><?php echo $ddwcwr_checked ? ' is-selected' : ''; ?>">
						<div class="ddwcwr-item-head">
							<input type="checkbox" class="ddwcwr-item-toggle" id="<?php echo esc_attr( $ddwcwr_field_id ); ?>" name="ddwcwr_items[<?php echo esc_attr( $ddwcwr_item_id ); ?>][selected]" value="1" <?php checked( $ddwcwr_checked ); ?> <?php disabled( ! $ddwcwr_item['eligible'] ); ?> aria-describedby="<?php echo esc_attr( $ddwcwr_field_id ); ?>-meta" />
							<label for="<?php echo esc_attr( $ddwcwr_field_id ); ?>" class="ddwcwr-item-label">
								<span class="ddwcwr-item-image"><?php echo wp_kses_post( $ddwcwr_item['image'] ); ?></span>
								<span class="ddwcwr-item-name"><?php echo esc_html( $ddwcwr_item['name'] ); ?></span>
							</label>
							<span class="ddwcwr-item-meta" id="<?php echo esc_attr( $ddwcwr_field_id ); ?>-meta">
								<?php echo wp_kses_post( wc_price( $ddwcwr_item['unit_value'], [ 'currency' => $order_data['currency'] ] ) ); ?>
								<?php if ( $ddwcwr_item['eligible'] && $ddwcwr_item['deadline'] ) : ?>
									<?php /* translators: %s: date */ ?>
									· <?php echo esc_html( sprintf( __( 'Request by %s', 'devdiggers-returns-for-woocommerce' ), date_i18n( wc_date_format(), $ddwcwr_item['deadline'] ) ) ); ?>
								<?php endif; ?>
								<?php if ( ! $ddwcwr_item['eligible'] ) : ?>
									<span class="ddwcwr-item-blocked"><?php echo esc_html( $ddwcwr_item['message'] ); ?></span>
								<?php endif; ?>
							</span>
						</div>

						<?php if ( $ddwcwr_item['eligible'] ) : ?>
							<div class="ddwcwr-item-details"<?php echo $ddwcwr_checked ? '' : ' hidden'; ?>>
								<p class="form-row ddwcwr-col">
									<label for="<?php echo esc_attr( $ddwcwr_field_id ); ?>-qty"><?php esc_html_e( 'Quantity', 'devdiggers-returns-for-woocommerce' ); ?></label>
									<?php $ddwcwr_qty_value = isset( $ddwcwr_prev['qty'] ) ? min( max( 1, (int) $ddwcwr_prev['qty'] ), $ddwcwr_item['available'] ) : $ddwcwr_item['available']; ?>
									<span class="ddwcwr-stepper">
										<button type="button" data-step="-1" aria-label="<?php esc_attr_e( 'Fewer', 'devdiggers-returns-for-woocommerce' ); ?>" <?php disabled( $ddwcwr_qty_value <= 1 ); ?>>&minus;</button>
										<input type="number" id="<?php echo esc_attr( $ddwcwr_field_id ); ?>-qty" name="ddwcwr_items[<?php echo esc_attr( $ddwcwr_item_id ); ?>][qty]" value="<?php echo esc_attr( $ddwcwr_qty_value ); ?>" min="1" max="<?php echo esc_attr( $ddwcwr_item['available'] ); ?>" step="1" inputmode="numeric" />
										<button type="button" data-step="1" aria-label="<?php esc_attr_e( 'More', 'devdiggers-returns-for-woocommerce' ); ?>" <?php disabled( $ddwcwr_qty_value >= $ddwcwr_item['available'] ); ?>>+</button>
									</span>
								</p>

								<?php if ( 'cancellation' !== $type ) : ?>
									<p class="form-row ddwcwr-col">
										<label for="<?php echo esc_attr( $ddwcwr_field_id ); ?>-reason"><?php esc_html_e( 'Reason', 'devdiggers-returns-for-woocommerce' ); ?> <span class="required" aria-hidden="true">*</span></label>
										<select id="<?php echo esc_attr( $ddwcwr_field_id ); ?>-reason" name="ddwcwr_items[<?php echo esc_attr( $ddwcwr_item_id ); ?>][reason]" class="ddwcwr-reason">
											<option value=""><?php esc_html_e( 'Choose a reason', 'devdiggers-returns-for-woocommerce' ); ?></option>
											<?php foreach ( $reasons as $ddwcwr_slug => $ddwcwr_reason ) : ?>
												<option value="<?php echo esc_attr( $ddwcwr_slug ); ?>" data-photo="<?php echo esc_attr( $ddwcwr_reason['require_photo'] ? 'yes' : '' ); ?>" <?php selected( isset( $ddwcwr_prev['reason'] ) ? $ddwcwr_prev['reason'] : '', $ddwcwr_slug ); ?>><?php echo esc_html( $ddwcwr_reason['label'] ); ?></option>
											<?php endforeach; ?>
										</select>
									</p>
									<p class="form-row ddwcwr-col">
										<label for="<?php echo esc_attr( $ddwcwr_field_id ); ?>-condition"><?php esc_html_e( 'Condition', 'devdiggers-returns-for-woocommerce' ); ?></label>
										<select id="<?php echo esc_attr( $ddwcwr_field_id ); ?>-condition" name="ddwcwr_items[<?php echo esc_attr( $ddwcwr_item_id ); ?>][condition]">
											<option value=""><?php esc_html_e( 'Select', 'devdiggers-returns-for-woocommerce' ); ?></option>
											<?php foreach ( $conditions as $ddwcwr_slug => $ddwcwr_label ) : ?>
												<option value="<?php echo esc_attr( $ddwcwr_slug ); ?>" <?php selected( isset( $ddwcwr_prev['condition'] ) ? $ddwcwr_prev['condition'] : '', $ddwcwr_slug ); ?>><?php echo esc_html( $ddwcwr_label ); ?></option>
											<?php endforeach; ?>
										</select>
									</p>
								<?php endif; ?>

							</div>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>
		</fieldset>

		<?php if ( 'cancellation' !== $type ) : ?>
			<fieldset class="ddwcwr-step">
				<legend class="ddwcwr-step-title"><span class="ddwcwr-step-number" aria-hidden="true">2</span><?php esc_html_e( 'Anything else?', 'devdiggers-returns-for-woocommerce' ); ?></legend>
				<p class="form-row">
					<label for="ddwcwr-note"><?php esc_html_e( 'Note', 'devdiggers-returns-for-woocommerce' ); ?><?php echo 'yes' === $configuration['require_note'] ? ' <span class="required" aria-hidden="true">*</span>' : ''; ?></label>
					<textarea id="ddwcwr-note" name="ddwcwr_note" rows="4" class="input-text" <?php echo 'yes' === $configuration['require_note'] ? 'required' : ''; ?>><?php echo esc_textarea( $note ); ?></textarea>
				</p>
				<?php if ( 'yes' === $configuration['allow_uploads'] ) : ?>
					<div class="form-row ddwcwr-upload">
						<input type="file" class="ddwcwr-file-input" id="ddwcwr-files" name="ddwcwr_files[]" multiple accept="<?php echo esc_attr( '.' . implode( ',.', (array) $configuration['allowed_file_types'] ) ); ?>" aria-describedby="ddwcwr-files-help" />
						<label for="ddwcwr-files" class="ddwcwr-dropzone">
							<?php echo wp_kses( ddwcwr_icon( 'upload', 22 ), ddwcwr_kses_icon() ); ?>
							<strong><?php esc_html_e( 'Add photos or videos', 'devdiggers-returns-for-woocommerce' ); ?></strong>
						</label>
						<span class="ddwcwr-muted" id="ddwcwr-files-help">
							<?php
							/* translators: 1: number of files, 2: size in MB, 3: file types */
							echo esc_html( sprintf( __( 'Up to %1$d files, %2$s MB each (%3$s).', 'devdiggers-returns-for-woocommerce' ), $configuration['max_files'], $configuration['max_file_size'], strtoupper( implode( ', ', (array) $configuration['allowed_file_types'] ) ) ) );
							?>
							<span class="ddwcwr-photo-required" hidden><?php esc_html_e( 'Photos are required for this request.', 'devdiggers-returns-for-woocommerce' ); ?></span>
						</span>
						<span class="ddwcwr-file-list" aria-live="polite"></span>
					</div>
				<?php endif; ?>
			</fieldset>
		<?php endif; ?>

		<div class="ddwcwr-form-error" role="alert" hidden></div>

		<?php wp_nonce_field( 'ddwcwr_submit_request', 'ddwcwr_nonce' ); ?>
		<input type="hidden" name="ddwcwr_action" value="submit_request" />
		<input type="hidden" name="ddwcwr_order_id" value="<?php echo esc_attr( $order_data['id'] ); ?>" />
		<input type="hidden" name="ddwcwr_type" value="<?php echo esc_attr( $type ); ?>" />
		<?php foreach ( $token_fields as $ddwcwr_name => $ddwcwr_value ) : ?>
			<input type="hidden" name="<?php echo esc_attr( $ddwcwr_name ); ?>" value="<?php echo esc_attr( $ddwcwr_value ); ?>" />
		<?php endforeach; ?>

		<button type="submit" class="ddwcwr-btn ddwcwr-btn-primary ddwcwr-btn-block ddwcwr-submit"><?php echo 'cancellation' === $type ? esc_html__( 'Request cancellation', 'devdiggers-returns-for-woocommerce' ) : esc_html__( 'Submit request', 'devdiggers-returns-for-woocommerce' ); ?></button>
	</form>
</div>
