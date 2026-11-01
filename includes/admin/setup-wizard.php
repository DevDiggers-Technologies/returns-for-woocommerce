<?php
/**
 * Setup wizard: the few settings a store must decide before taking returns.
 *
 * @package WPReturns
 * @version 1.0.0
 */

namespace DDWCWPReturns\Includes\Admin;

use DevDiggers\Framework\Includes\DDFW_Form_Field;
use DevDiggers\Framework\Includes\DDFW_Setup_Wizard;

defined( 'ABSPATH' ) || exit();

if ( ! class_exists( 'DDWCWR_Setup_Wizard' ) ) {
	/**
	 * Setup wizard class
	 */
	class DDWCWR_Setup_Wizard {
		/**
		 * Wizard slug.
		 *
		 * @var string
		 */
		const SLUG = 'wpreturns';

		/**
		 * Options a wizard step may write.
		 *
		 * @var array
		 */
		protected $allowed = [
			'_ddwcwr_enabled',
			'_ddwcwr_return_window_days',
			'_ddwcwr_window_from',
			'_ddwcwr_allow_refund',
			'_ddwcwr_allow_cancellation',
			'_ddwcwr_refund_via_gateway',
			'_ddwcwr_restock_on_receive',
			'_ddwcwr_withdrawal_enabled',
			'_ddwcwr_withdrawal_scope',
			'_ddwcwr_withdrawal_footer_link',
		];

		/**
		 * Configuration.
		 *
		 * @var array
		 */
		protected $configuration;

		/**
		 * Construct
		 *
		 * @param array $ddwcwr_configuration Configuration.
		 */
		public function __construct( $ddwcwr_configuration ) {
			$this->configuration = $ddwcwr_configuration;

			// Stores that already saved settings don't need the wizard.
			if ( ! get_option( 'ddfw_setup_wizard_completed_' . self::SLUG ) && false !== get_option( '_ddwcwr_enabled', false ) ) {
				update_option( 'ddfw_setup_wizard_completed_' . self::SLUG, true );
			}

			new DDFW_Setup_Wizard( $this->get_wizard_config() );
		}

		/**
		 * Wizard configuration.
		 *
		 * @return array
		 */
		protected function get_wizard_config() {
			return [
				'plugin_slug'    => self::SLUG,
				'plugin_file'    => DDWCWR_PLUGIN_BASENAME,
				'dashboard_page' => 'ddwcwr-dashboard',
				'redirect_url'   => admin_url( 'admin.php?page=ddwcwr-dashboard' ),
				'brand'          => [
					'name'        => esc_html__( 'WPReturns', 'wpreturns' ),
					'description' => esc_html__( 'Returns, cancellations and the EU withdrawal button for WooCommerce. A few questions and your store is ready to take returns.', 'wpreturns' ),
				],
				'steps'          => [
					'welcome'     => [
						'label'         => esc_html__( 'Welcome', 'wpreturns' ),
						'view_callback' => [ $this, 'welcome_view' ],
					],
					'returns'     => [
						'label'         => esc_html__( 'Returns', 'wpreturns' ),
						'title'         => esc_html__( 'Your return policy', 'wpreturns' ),
						'description'   => esc_html__( 'How long customers have to ask for a return.', 'wpreturns' ),
						'view_callback' => [ $this, 'returns_view' ],
						'save_callback' => [ $this, 'save_fields' ],
					],
					'resolutions' => [
						'label'         => esc_html__( 'Refunds', 'wpreturns' ),
						'title'         => esc_html__( 'Refunds and cancellations', 'wpreturns' ),
						'description'   => esc_html__( 'How refunds are paid and whether customers can cancel orders that have not shipped.', 'wpreturns' ),
						'view_callback' => [ $this, 'resolutions_view' ],
						'save_callback' => [ $this, 'save_fields' ],
					],
					'withdrawal'  => [
						'label'         => esc_html__( 'EU withdrawal', 'wpreturns' ),
						'title'         => esc_html__( 'Withdrawal button', 'wpreturns' ),
						'description'   => esc_html__( 'From 19 June 2026, stores selling to EU consumers online must offer a "Withdraw from contract here" function.', 'wpreturns' ),
						'view_callback' => [ $this, 'withdrawal_view' ],
						'save_callback' => [ $this, 'save_fields' ],
					],
					'ready'       => [
						'label'             => esc_html__( 'Ready!', 'wpreturns' ),
						'ready_title'       => esc_html__( 'You are ready to take returns.', 'wpreturns' ),
						'ready_description' => esc_html__( 'Customers can now start returns from My Account and the returns page. Reasons, statuses and emails are under Configuration.', 'wpreturns' ),
					],
				],
			];
		}

		/**
		 * Logo.
		 *
		 * @param int $size Size.
		 * @return string
		 */
		protected function get_logo( $size ) {
			return '<svg width="' . absint( $size ) . '" height="' . absint( $size ) . '" viewBox="0 0 64 64" fill="none" aria-hidden="true" focusable="false" xmlns="http://www.w3.org/2000/svg"><rect width="64" height="64" rx="17" fill="#2f4be8"/><path d="M32 9 52 20.5 32 32 12 20.5Z" fill="#cbd2f9" stroke="#cbd2f9" stroke-width="3" stroke-linejoin="round"/><path d="M12 20.5 32 32v24L12 44.5Z" fill="#fff" stroke="#fff" stroke-width="3" stroke-linejoin="round"/><path d="M52 20.5 32 32v24l20-11.5Z" fill="#7b8cf0" stroke="#7b8cf0" stroke-width="3" stroke-linejoin="round"/><path d="M22 14.75 42 26.25v8" fill="none" stroke="#2f4be8" stroke-opacity=".35" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></svg>';
		}

		/**
		 * Welcome step.
		 *
		 * @return void
		 */
		public function welcome_view() {
			?>
			<div class="ddfw-setup-wizard-ready ddfw-setup-wizard-onboarding">
				<div class="ddfw-success-icon-wrap"><?php echo $this->get_logo( 100 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG. ?></div>
				<h2 class="ddfw-setup-wizard-ready-title"><?php esc_html_e( 'Welcome to WPReturns!', 'wpreturns' ); ?></h2>
				<p class="ddfw-setup-wizard-ready-desc"><?php esc_html_e( 'Let\'s set your return window, how refunds work and the EU withdrawal button. It takes about two minutes and you can change everything later.', 'wpreturns' ); ?></p>
			</div>
			<?php
		}

		/**
		 * Print fields.
		 *
		 * @param array $fields Fields.
		 * @return void
		 */
		protected function fields( $fields ) {
			?>
			<div class="ddfw-fields-section">
				<table class="form-table">
					<tbody>
						<?php
						foreach ( $fields as $field ) {
							$field['name'] = '_' . str_replace( '-', '_', $field['id'] );
							DDFW_Form_Field::display_form_field( $field );
						}
						?>
					</tbody>
				</table>
			</div>
			<?php
		}

		/**
		 * Checkbox field.
		 *
		 * @param string $key Configuration key.
		 * @param string $label Label.
		 * @param string $checkbox_label Checkbox label.
		 * @param string $description Description.
		 * @return array
		 */
		protected function checkbox( $key, $label, $checkbox_label, $description ) {
			return [
				'type'           => 'checkbox',
				'id'             => 'ddwcwr-' . str_replace( '_', '-', $key ),
				'label'          => $label,
				'checkbox_label' => $checkbox_label,
				'description'    => $description,
				'value'          => $this->configuration[ $key ],
			];
		}

		/**
		 * Returns step.
		 *
		 * @return void
		 */
		public function returns_view() {
			$this->fields(
				[
					$this->checkbox( 'enabled', esc_html__( 'Customer requests', 'wpreturns' ), esc_html__( 'Let customers request returns and cancellations', 'wpreturns' ), esc_html__( 'When off, customers see nothing; you can still create requests yourself.', 'wpreturns' ) ),
					[
						'type'              => 'number',
						'id'                => 'ddwcwr-return-window-days',
						'label'             => esc_html__( 'Days to request a return', 'wpreturns' ),
						'description'       => esc_html__( '30 is common. Under EU law consumers get at least 14 days to withdraw anyway.', 'wpreturns' ),
						'value'             => $this->configuration['return_window_days'],
						'custom_attributes' => [ 'min' => 0 ],
					],
					[
						'type'        => 'select',
						'id'          => 'ddwcwr-window-from',
						'label'       => esc_html__( 'Count days from', 'wpreturns' ),
						'description' => esc_html__( '"Completed" works best when you complete orders on delivery.', 'wpreturns' ),
						'options'     => [
							'completed' => esc_html__( 'Order completed (delivered)', 'wpreturns' ),
							'created'   => esc_html__( 'Order placed', 'wpreturns' ),
						],
						'value'       => $this->configuration['window_from'],
					],
				]
			);
		}

		/**
		 * Resolutions step.
		 *
		 * @return void
		 */
		public function resolutions_view() {
			$this->fields(
				[
					$this->checkbox( 'allow_refund', esc_html__( 'Returns', 'wpreturns' ), esc_html__( 'Let customers return items for a refund', 'wpreturns' ), esc_html__( 'Money back to the card or method the customer paid with, once you approve it.', 'wpreturns' ) ),
					$this->checkbox( 'refund_via_gateway', esc_html__( 'Gateway refunds', 'wpreturns' ), esc_html__( 'Send refunds through the payment gateway', 'wpreturns' ), esc_html__( 'When off, refunds are only recorded and you pay them back yourself. You can still choose per request.', 'wpreturns' ) ),
					$this->checkbox( 'restock_on_receive', esc_html__( 'Restock', 'wpreturns' ), esc_html__( 'Put returned items back in stock when they arrive', 'wpreturns' ), esc_html__( 'Damaged and defective items are never restocked.', 'wpreturns' ) ),
					$this->checkbox( 'allow_cancellation', esc_html__( 'Cancellations', 'wpreturns' ), esc_html__( 'Let customers ask to cancel an order before it ships', 'wpreturns' ), esc_html__( 'You approve each one; the order is canceled and refunded.', 'wpreturns' ) ),
				]
			);
		}

		/**
		 * Withdrawal step.
		 *
		 * @return void
		 */
		public function withdrawal_view() {
			$this->fields(
				[
					$this->checkbox( 'withdrawal_enabled', esc_html__( 'Withdrawal function', 'wpreturns' ), esc_html__( 'Show the "Withdraw from contract here" button', 'wpreturns' ), esc_html__( 'Customers confirm in two steps and get an acknowledgement email with the date and time.', 'wpreturns' ) ),
					[
						'type'        => 'select',
						'id'          => 'ddwcwr-withdrawal-scope',
						'label'       => esc_html__( 'Show it for', 'wpreturns' ),
						'description' => esc_html__( 'Based on the billing country of the order. You can pick individual countries on the EU Withdrawal tab.', 'wpreturns' ),
						'options'     => [
							'eu'  => esc_html__( 'EU and EEA countries', 'wpreturns' ),
							'all' => esc_html__( 'All countries', 'wpreturns' ),
						],
						'value'       => $this->configuration['withdrawal_scope'],
					],
					$this->checkbox( 'withdrawal_footer_link', esc_html__( 'Footer link', 'wpreturns' ), esc_html__( 'Add a "Withdraw from contract here" link to every page', 'wpreturns' ), esc_html__( 'The law asks for the function to be easy to find for the whole withdrawal period.', 'wpreturns' ) ),
				]
			);
		}

		/**
		 * Save a step (unchecked boxes arrive with an empty value).
		 *
		 * @param array $form_data name/value pairs.
		 * @return true
		 */
		public function save_fields( $form_data ) {
			foreach ( (array) $form_data as $field ) {
				if ( isset( $field['name'], $field['value'] ) && in_array( $field['name'], $this->allowed, true ) ) {
					update_option( $field['name'], $field['value'] ); // Registered sanitize callbacks run inside update_option().
				}
			}

			return true;
		}
	}
}
