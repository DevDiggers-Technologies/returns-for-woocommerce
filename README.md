# Returns Portal & RMA for WooCommerce

Free WooCommerce returns plugin by [DevDiggers](https://devdiggers.com/): a returns portal and RMA workflow plus the EU "Withdraw from contract here" button (Directive (EU) 2023/2673). It shows in the Plugins screen as **DevDiggers Returns for WooCommerce**. The Pro add-on (`wpreturns-pro`) requires this plugin.

**Live Demo:** [Pro demo](https://demo.devdiggers.com/wpreturns/). The free plugin can be tried with the Live Preview button on its wordpress.org page.

| | |
|---|---|
| Slug and text domain | `devdiggers-returns-for-woocommerce` |
| Prefix / namespace | `ddwcwr` / `DDWCWPReturns` |
| Requires | WordPress 6.5, PHP 7.4, WooCommerce 9.0 |
| Tested up to | WordPress 7.1, WooCommerce 11.2 |
| Stable tag | 1.1.0 |
| License | GPL-3.0-or-later |

## What it does

Customers request returns and cancellations from My Account or from a guest returns portal (order number and billing email). They pick items and quantities, a reason and condition per item, and attach photos or videos. The shop team approves, uploads a label, marks the parcel received (with restock) and refunds through WooCommerce from one request screen.

The EU withdrawal function runs separately from returns: a two-step statement with "Confirm withdrawal", an acknowledgement email with the received date and time, the Annex I(B) model form, Art. 16 exemptions per product and category, the Art. 16(m) consent checkbox for downloads at checkout, and a withdrawal log with CSV export.

Everything stays on the store's own server. No customer or order data leaves the site.

[Watch the plugin video on YouTube](https://www.youtube.com/watch?v=AFI6gmH5g7I)

## Setup

1. Activate WooCommerce, then this plugin. The setup wizard opens.
2. The Returns portal and Withdraw from contract pages are created on activation. Add them to a menu or the footer.
3. Review **DevDiggers Plugins > Returns > Configuration**, mainly the Return Policy and EU Withdrawal tabs.

Configuration tabs in the free build: General, Return Policy, Reasons, Statuses, Resolutions, EU Withdrawal, Emails, Advanced and Import. Warranty, Shipping Labels, Fraud Protection, Automation and Appearance show a Pro upgrade card.

## Shortcodes and blocks

| Shortcode | Block | Shows |
|---|---|---|
| `[ddwcwr_returns_portal]` | `ddwcwr/returns-portal` | Guest returns portal (order number and billing email lookup) |
| `[ddwcwr_withdrawal_form]` | `ddwcwr/withdrawal-form` | EU withdrawal statement and model withdrawal form |

## AI assistants (MCP)

Ask Claude, ChatGPT, Cursor, VS Code or any MCP client about your returns in plain words: "What is waiting for me?", "Why are customers returning this month?", "Can order 1234 still be returned?", "Show me Ben's return history".

- Tools for a returns overview (the dashboard totals for any period against the previous one, requests by status, type and resolution, top reasons and products, the open queue), the requests list with filters, one request in full (items, reasons, conversation, notes, history), an order's returns and what is still returnable, a customer's return history, the most returned products, reasons and categories, and the return policy settings
- Triage pending returns and Returns review prompts
- Optional: let the assistant approve, reject or change the status of a request with a note to the customer, add an internal note or message the customer, and mark returned items received with restock. Every change goes through the request screen's own logic (emails, history) and is logged under WooCommerce > Status > Logs. Refunds and approving cancellations stay with you in wp-admin
- Same access as the Requests screen: store managers only
- Managed in DevDiggers Plugins > AI & MCP: one MCP server for every DevDiggers plugin (`/wp-json/devdiggers/v1/mcp`), sign-in with OAuth (you click Allow in WordPress, nothing to copy), an on / off switch per plugin and per write tool, a Try panel on every tool and an activity log
- Built on the WordPress Abilities API (WordPress 6.9+) and the MCP Adapter bundled with WooCommerce 10.3+

## Import from other plugins

Configuration > Import finds requests left by these plugins, active or deactivated, and copies them with items, status, dates and messages: Return Refund and Exchange for WooCommerce (WP Swings), Flexible Refund and Return Order (WP Desk), YITH Advanced Refund System, Germanized, Shiptastic, WC Cancel Order, Cancel Order Request for WooCommerce (PiWeb), Order Cancellation & Returns, Withdrawal Button – Easy Return and BuddyPilot Withdrawal. Sources live in `helper/import/source/`.

## Free vs Pro

| Feature | Free | Pro |
|---|---|---|
| My Account returns page and guest returns portal (shortcode and block) | Yes | Yes |
| Partial returns, reasons, condition, photo and video uploads | Yes | Yes |
| Messages, uploaded return labels, customer tracking number | Yes | Yes |
| Cancel order requests with refund on approval | Yes | Yes |
| Return window, final-sale products and categories | Yes | Yes |
| "Days left to return" on orders | Yes | Yes |
| EU withdrawal button, acknowledgement email, model form, log and CSV | Yes | Yes |
| Checkout consent for digital content (Art. 16(m)), classic and block checkout | Yes | Yes |
| Refund through WooCommerce with restock | Yes | Yes |
| Dashboard (requests, refunds, withdrawals, time to resolve, trend) | Yes | Yes |
| Seven built-in statuses (renamed, recolored) and custom statuses | Yes | Yes |
| Import from ten other returns and withdrawal plugins | Yes | Yes |
| Store credit (coupon or DevDiggers Wallet) with bonus | No | Yes |
| Exchanges (same product or any product, price difference handled) | No | Yes |
| Warranty claims and extended warranties | No | Yes |
| Return rules: windows, final-sale rules, auto-approve, fees, keep-it refunds | No | Yes |
| EasyPost and Shippo labels, printable return slip | No | Yes |
| Fraud flags, risk score, blocklist, Customers screen | No | Yes |
| Analytics and requests CSV export | No | Yes |
| Bulk actions, team permissions and assignment | No | Yes |
| Automation (instant cancellations, reminders, auto-close, automatic refunds) | No | Yes |
| Appearance settings, REST API, WooCommerce webhooks | No | Yes |

Pro features are absent from this plugin. The admin shows them as locked fields (renamed ids, never registered) or, for sections that are Pro from top to bottom, one upgrade card. Pro uses the same tables, options and tabs, so activating Pro on a site that used Free keeps every request and setting. When Pro is active, this plugin stops loading its own instance and Pro takes over.

## Layout

```
functions.php                 bootstrap: DDWCWR_Free_Init, yields to Pro (DDWCWR_Init), loads the bundled framework
autoload/autoload.php         DDWCWPReturns\A\B\DDWCWR_Foo_Bar -> a/b/foo-bar.php
includes/                     file-handler, admin-dashboard, install, global functions and configuration
includes/{admin,common,front} *-hooks.php register hooks, *-functions.php hold callbacks
includes/emails/              WC_Email classes, template templates/emails/request.php
helper/order/                 order adapter interface + WooCommerce adapter (the only place touching WC orders)
helper/{request,resolution,label,upload,dashboard,import}/  domain logic
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
| `ddwcwr_mcp_abilities` | Add, change or remove AI assistant (MCP) tools. They live in `includes/integrations/mcp-integration.php` under the `returns/` namespace. |

## Build

```
npm install
npm run build          # webpack, then text domain replacement in the framework and i18n
npm test               # Free/Pro boundary check
npm run build:release  # zip without dev files (.distignore)
```

`src/` does not ship in the zip (`.distignore`). The source is public at [github.com/DevDiggers-Technologies/returns-for-woocommerce](https://github.com/DevDiggers-Technologies/returns-for-woocommerce), which `readme.txt` links under Source code and build tools.

## Support

- [Support forum](https://wordpress.org/support/plugin/devdiggers-returns-for-woocommerce/) (free plugin)
- [Documentation](https://wpreturns.com/docs/)
- [Free vs Pro](https://wpreturns.com/free-vs-pro/)
- [Pro plans](https://wpreturns.com/pricing/)

## Changelog

See `readme.txt`.
