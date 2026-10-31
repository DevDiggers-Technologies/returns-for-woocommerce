<?php
/**
 * Self-check for the Free/Pro boundary.
 *
 * Every Pro feature is meant to be physically absent from this plugin, not switched off.
 * The easy ways to break that are a rebuild from a stale source tree or a file copied
 * across from the Pro plugin. This asserts that neither has happened.
 *
 *   php bin/test-free-boundary.php
 *
 * @package WPReturns
 */

$root   = dirname( __DIR__ ) . '/';
$errors = [];

/**
 * Read a file, or return '' when it is absent.
 *
 * @param string $path Absolute path.
 * @return string
 */
function ddwcwr_read( $path ) {
	return file_exists( $path ) ? (string) file_get_contents( $path ) : '';
}

/**
 * Every PHP file that belongs to this plugin, excluding the bundled framework and tooling.
 *
 * @param string $root Plugin root.
 * @return string[]
 */
function ddwcwr_plugin_php( $root ) {
	$files    = [];
	$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ) );

	foreach ( $iterator as $file ) {
		$path = $file->getPathname();

		if ( 'php' !== strtolower( $file->getExtension() ) ) {
			continue;
		}

		foreach ( [ '/node_modules/', '/devdiggers-framework/', '/bin/', '/build/' ] as $skip ) {
			if ( false !== strpos( $path, $skip ) ) {
				continue 2;
			}
		}

		$files[ str_replace( $root, '', $path ) ] = ddwcwr_read( $path );
	}

	return $files;
}

/**
 * Report every file that contains one of the needles.
 *
 * @param array  $files   relative path => contents.
 * @param array  $needles Strings that must not appear.
 * @param string $label   What a hit means.
 * @param array  $errors  Collected errors.
 * @return void
 */
function ddwcwr_forbid( $files, $needles, $label, &$errors ) {
	foreach ( $files as $relative => $contents ) {
		foreach ( $needles as $needle ) {
			if ( false !== strpos( $contents, $needle ) ) {
				$errors[] = "{$label} '{$needle}' is present in {$relative}.";
			}
		}
	}
}

$php = ddwcwr_plugin_php( $root );

// 1. Pro-only files are gone, not merely unhooked.
foreach ( [
	'helper/rule/rule-helper.php',
	'helper/fraud/fraud-helper.php',
	'helper/warranty/warranty-helper.php',
	'helper/label/easypost-provider.php',
	'helper/label/shippo-provider.php',
	'helper/label/interface-label-provider.php',
	'includes/integrations/wallet-integration.php',
	'includes/rest/rest-routes.php',
	'includes/emails/email-customer-ship-reminder.php',
	'templates/admin/rule/rules-list-template.php',
	'templates/admin/rule/manage-rule-template.php',
	'templates/admin/customer/customers-list-template.php',
	'templates/admin/configuration/wallet-configuration-template.php',
	'templates/common/return-slip.php',
	'templates/front/extended-warranty.php',
	'src/slip/index.js',
	'assets/js/slip.js',
] as $file ) {
	if ( file_exists( $root . $file ) ) {
		$errors[] = "Pro file {$file} is shipped.";
	}
}

// 2. No licensing, updater, remote calls or REST API.
ddwcwr_forbid( $php, [ 'ddfw_register_plugin_for_updates', 'get_license_layout', '_ddwcwr_purchase_code', '_ddwcwr_license_activated', 'DDFW_Plugin_Updater', 'wp_remote_', 'register_rest_route', 'rest_api_init' ], 'Licensing or remote reference', $errors );

foreach ( [ 'includes/class-ddfw-plugin-updater.php', 'includes/class-devdiggers-notifications.php', 'templates/layout/license.php' ] as $file ) {
	if ( file_exists( $root . 'devdiggers-framework/' . $file ) ) {
		$errors[] = "Bundled framework ships {$file}.";
	}
}

// 3. No handler for a Pro action. Locked UI describes these; nothing may run them.
ddwcwr_forbid(
	$php,
	[
		'admin_post_ddwcwr_export_csv',
		'admin_post_ddwcwr_print_slip',
		'admin_post_ddwcwr_customer_flag',
		'ddwcwr_handle_save_rule',
		'wp_ajax_ddwcwr_search_products',
		'wp_ajax_nopriv_ddwcwr_search_products',
		"case 'assign'",
		"case 'create_label'",
		"case 'flag'",
		'get_bulk_actions',
		'process_bulk_action',
		'woocommerce_webhook_topics',
		'woocommerce_add_cart_item_data',
		'woocommerce_before_calculate_totals',
		'woocommerce_checkout_create_order_line_item',
		'woocommerce_product_bulk_edit_save',
		'woocommerce_save_product_variation',
		'as_schedule_single_action',
		'as_schedule_recurring_action',
		'wp_schedule_event',
		'ddpr_order_earning_points',
		'ddwcpr_order_earning_points',
	],
	'Pro registration',
	$errors
);

// 4. No Pro business logic left in shared files.
ddwcwr_forbid(
	$php,
	[
		'create_coupon',
		'create_exchange_order',
		'validate_exchange_product',
		'get_restocking_fee',
		'score_request',
		'is_returnless',
		'get_decision',
		'get_customer_stats',
		'set_flagged',
		'get_extended_options',
		'get_item_coverage',
		'credit_bonus\'       =>',
		"'store_credit' =>",
		"'exchange'     =>",
		"case 'store_credit'",
		"case 'exchange'",
		"case 'repair'",
		'ddwcwr_get_slip_url',
		'ddwcwr_get_return_shipping_fee',
		'get_appearance_css',
		'auto_approve_cancellation\']',
		'refund_permission_statuses',
		'ddwcwr_manage_requests',
		'ddwcwr_process_refunds',
		'add_cap(',
	],
	'Pro logic',
	$errors
);

// 5. No Pro-only setting is read or defaulted, so a stored Pro value changes nothing here.
$globals = ddwcwr_read( $root . 'includes/globals-variables.php' );

foreach ( [ 'allow_store_credit', 'allow_exchange', 'allow_warranty', 'auto_approve_cancellation', 'store_credit_', 'exchange_', 'return_shipping_fee', 'auto_refund', 'warranty_', 'label_provider', 'easypost', 'shippo', 'fraud_', 'serial_returner', 'blocklist', 'auto_close', 'reminder', 'refund_permission', 'accent_', 'card_color', 'corner_style', 'purchase_code' ] as $needle ) {
	if ( false !== strpos( $globals, "'{$needle}" ) ) {
		$errors[] = "Pro setting '{$needle}' has a default in includes/globals-variables.php.";
	}
}

// Locked fields are renamed and skipped, so they never become a registered setting.
$admin = ddwcwr_read( $root . 'includes/admin/admin-functions.php' );

if ( false === strpos( $admin, "false !== strpos( \$field['id'], '-locked-' )" ) ) {
	$errors[] = 'register_setting() no longer skips locked Pro fields.';
}

// 6. Free request types and resolution only.
$functions = ddwcwr_read( $root . 'includes/global-functions.php' );

foreach ( [ "'warranty'     =>", "'keep'", "'replace'", "'repair'" ] as $needle ) {
	if ( false !== strpos( $functions, $needle ) ) {
		$errors[] = "Pro type or resolution {$needle} is defined in includes/global-functions.php.";
	}
}

// 7. The built bundles carry no Pro flow, and still do the Free job.
$bundles = [
	'assets/js/admin.js'   => ddwcwr_read( $root . 'assets/js/admin.js' ),
	'assets/js/front.js'   => ddwcwr_read( $root . 'assets/js/front.js' ),
	'assets/js/blocks.js'  => ddwcwr_read( $root . 'assets/js/blocks.js' ),
	'assets/css/admin.css' => ddwcwr_read( $root . 'assets/css/admin.css' ),
	'assets/css/front.css' => ddwcwr_read( $root . 'assets/css/front.css' ),
];

foreach ( $bundles as $bundle => $contents ) {
	if ( '' === $contents ) {
		$errors[] = "{$bundle} is missing. Run `npm run build`.";
	}
}

ddwcwr_forbid( $bundles, [ 'ddwcwr_search_products', 'exchange', 'warranty', 'wallet', 'slip', 'rule-type', 'console.log', 'document.cookie', 'localStorage' ], 'Pro identifier', $errors );

foreach ( [ 'assets/js/front.js' => 'ddwcwr-photo-required', 'assets/js/admin.js' => 'ddwcwr_toggle_email', 'assets/js/blocks.js' => 'ddwcwr/withdrawal-form' ] as $bundle => $needle ) {
	if ( '' !== $bundles[ $bundle ] && false === strpos( $bundles[ $bundle ], $needle ) ) {
		$errors[] = "Free behaviour '{$needle}' is missing from {$bundle}.";
	}
}

// 8. Every built asset maps to a webpack entry.
$webpack = ddwcwr_read( $root . 'webpack.config.js' );

foreach ( [ 'assets/js', 'assets/css' ] as $dir ) {
	foreach ( (array) glob( $root . $dir . '/*' ) as $path ) {
		$name = basename( $path );

		if ( 'index.php' === $name ) {
			continue;
		}

		if ( ! preg_match( '/\b' . preg_quote( pathinfo( $name, PATHINFO_FILENAME ), '/' ) . '\s*:/', $webpack ) ) {
			$errors[] = "{$dir}/{$name} has no webpack entry.";
		}
	}
}

// 9. The Free bootstrap yields to Pro and loads the framework on its own pages.
$bootstrap = ddwcwr_read( $root . 'functions.php' );

foreach ( [ "class_exists( 'DDWCWR_Init' )", "'ddwcwr' !== \$prefix", 'DDWCWR_Free_Init', 'Text Domain: wpreturns' ] as $needle ) {
	if ( false === strpos( $bootstrap, $needle ) ) {
		$errors[] = "Bootstrap guard '{$needle}' is missing from functions.php.";
	}
}

// 10. Free features added after competitor research stay in Free.
foreach ( [
	'includes/global-functions.php'      => [ 'function ddwcwr_is_final_sale', 'function ddwcwr_get_trader_address', 'function ddwcwr_cart_needs_digital_consent' ],
	'includes/common/common-hooks.php'   => [ 'ddwcwr_register_block_digital_consent' ],
	'helper/request/request-helper.php'  => [ 'ddwcwr_is_final_sale( $item )' ],
	'templates/front/order-panel.php'    => [ 'ddwcwr-order-deadline' ],
	'templates/front/withdrawal-form.php' => [ 'ddwcwr-model-form' ],
	'wpml-config.xml'                    => [ '_ddwcwr_reasons' ],
] as $file => $needles ) {
	foreach ( $needles as $needle ) {
		if ( false === strpos( ddwcwr_read( $root . $file ), $needle ) ) {
			$errors[] = "Free feature '{$needle}' is missing from {$file}.";
		}
	}
}

// 11. No constants or upgrade routine a first release has no use for.
ddwcwr_forbid( $php, [ 'DDWCWR_VERSION', 'DDWCWR_DB_VERSION', '_ddwcwr_db_version', 'wp_unschedule_hook', 'as_unschedule_all_actions' ], 'Unneeded', $errors );

if ( $errors ) {
	echo "Free/Pro boundary check FAILED:\n";

	foreach ( $errors as $error ) {
		echo ' - ' . $error . "\n";
	}

	exit( 1 );
}

echo "Free/Pro boundary check passed.\n";
