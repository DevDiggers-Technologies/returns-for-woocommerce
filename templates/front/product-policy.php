<?php
/**
 * Product page: return policy and withdrawal exemption.
 *
 * @package DevDiggers Returns
 * @version 1.0.0
 *
 * @var array  $lines      [ type, text ].
 * @var string $policy_url Policy page URL.
 */

defined( 'ABSPATH' ) || exit();
?>
<ul class="ddwcwr-product-policy">
	<?php foreach ( $lines as $ddwcwr_line ) : ?>
		<li class="ddwcwr-product-policy-<?php echo esc_attr( $ddwcwr_line[0] ); ?>">
			<?php echo wp_kses( ddwcwr_icon( 'final' === $ddwcwr_line[0] ? 'alert' : 'return', 18 ), ddwcwr_kses_icon() ); ?>
			<span>
			<?php echo esc_html( $ddwcwr_line[1] ); ?>
			<?php if ( 'policy' === $ddwcwr_line[0] && $policy_url ) : ?>
				<a href="<?php echo esc_url( $policy_url ); ?>"><?php esc_html_e( 'Details', 'devdiggers-returns-for-woocommerce' ); ?></a>
			<?php endif; ?>
			</span>
		</li>
	<?php endforeach; ?>
</ul>
