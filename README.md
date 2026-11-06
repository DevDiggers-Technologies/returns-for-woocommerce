# DevDiggers Returns

Free wordpress.org plugin by [DevDiggers](https://devdiggers.com/). A returns portal and RMA workflow for WooCommerce plus the EU "Withdraw from contract here" function (Directive (EU) 2023/2673). DevDiggers Returns Pro (`wpreturns-pro`) is an add-on that requires this plugin.

| | |
|---|---|
| Slug and text domain | `devdiggers-returns-for-woocommerce` |
| Prefix / namespace | `ddwcwr` / `DDWCWPReturns` |
| Requires | WordPress 6.5, PHP 7.4, WooCommerce 9.0 |
| Tested up to | WordPress 7.1, WooCommerce 11.2 |
| License | GPL-3.0-or-later |

## What it does

Customers request returns and cancellations from My Account or from a guest returns portal (order number and billing email). They pick items and quantities, a reason and condition per item, and attach photos or videos. The shop team approves, uploads a label, marks the parcel received (with restock) and refunds through WooCommerce from one request screen.

The EU withdrawal function runs separately from returns: a two-step statement with "Confirm withdrawal", an acknowledgement email with the received date and time, the Annex I(B) model form, Art. 16 exemptions per product and category, the Art. 16(m) consent checkbox for downloads at checkout, and a withdrawal log with CSV export.

## Free vs Pro

| Feature | Free | Pro |
|---|---|---|
| My Account returns page and guest returns portal (shortcode and block) | Yes | Yes |
| Partial returns, reasons, condition, photo and video uploads | Yes | Yes |
| Messages, uploaded return labels, customer tracking number | Yes | Yes |
| Cancellation requests with automatic refund on approval | Yes | Yes |
| Return window, final-sale products and categories | Yes | Yes |
| "Days left to return" on orders | Yes | Yes |
| EU withdrawal button, acknowledgement email, model form, log and CSV | Yes | Yes |
| Checkout consent for digital content (Art. 16(m)), classic and block checkout | Yes | Yes |
| Refund through WooCommerce with restock | Yes | Yes |
| Dashboard (requests, refunds, withdrawals, time to resolve, trend) | Yes | Yes |
| Seven built-in statuses, renamed and recolored | Yes | Yes |
| Store credit (coupon or DevDiggers Wallet) with bonus | No | Yes |
| Exchanges (same product or any product, price difference handled) | No | Yes |
| Warranty claims and extended warranties | No | Yes |
| Return rules: windows, final-sale rules, auto-approve, fees, keep-it refunds | No | Yes |
| EasyPost and Shippo labels, printable return slip | No | Yes |
| Fraud flags, risk score, blocklist, Customers screen | No | Yes |
| Analytics and requests CSV export | No | Yes |
| Bulk actions, custom statuses, team permissions and assignment | No | Yes |
| Automation (reminders, auto-close, automatic refunds) | No | Yes |
| Appearance settings, REST API, WooCommerce webhooks | No | Yes |

Pro features are physically absent from this plugin. The admin shows them as locked fields (renamed ids, never registered) or, for sections that are Pro from top to bottom, one upgrade card with a blurred screenshot from `assets/images/pro/`. Pro uses the same tables, options and tabs, so activating Pro on a site that used Free keeps every request and setting. When Pro is active, this plugin stops loading its own instance and Pro takes over.

## Layout

```
functions.php                 bootstrap: DDWCWR_Free_Init, yields to Pro (DDWCWR_Init), loads the bundled framework
autoload/autoload.php         DDWCWPReturns\A\B\DDWCWR_Foo_Bar -> a/b/foo-bar.php
includes/                     file-handler, admin-dashboard, install, global functions and configuration
includes/{admin,common,front} *-hooks.php register hooks, *-functions.php hold callbacks
includes/emails/              WC_Email classes, template templates/emails/request.php
helper/order/                 order adapter interface + WooCommerce adapter (the only place touching WC orders)
helper/{request,resolution,label,upload,dashboard}/  domain logic
templates/admin|front         screens (themes override front templates in yourtheme/devdiggers-returns-for-woocommerce/front/)
src/ -> assets/               webpack (front, admin, blocks)
bin/test-free-boundary.php    asserts no Pro code ships here (npm test)
wpml-config.xml               reasons, statuses, policy line and menu label for WPML/Polylang
```

## Hooks worth knowing

| Filter | Use |
|---|---|
| `ddwcwr_item_final_sale` | Mark more order items as final sale (returns only). |
| `ddwcwr_withdrawal_item_excluded` | Mark more order items as exempt from withdrawal. |
| `ddwcwr_trader_address` | Lines of the store address on the model withdrawal form. |
| `ddwcwr_private_upload_dir` | Move the private uploads folder. |

## Build

```
npm install
npm run build          # webpack, then text domain replacement in the framework and i18n
npm test               # Free/Pro boundary check
npm run build:release  # zip without dev files (.distignore)
```

## Support

- [Support forum](https://wordpress.org/support/plugin/devdiggers-returns-for-woocommerce/)
- [DevDiggers Returns Pro](https://wpreturns.com/)

## Changelog

See `readme.txt`.
