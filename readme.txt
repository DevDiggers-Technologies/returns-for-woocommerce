=== Returns Portal & RMA for WooCommerce – EU Withdrawal Button, Refund & Cancel Order Requests ===
Contributors: devdiggers
Plugin URI: https://wpreturns.com/
Author: DevDiggers
Author URI: https://devdiggers.com/
Tags: rma, woocommerce returns, withdrawal button, refund request, cancel order
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
WC requires at least: 9.0
WC tested up to: 11.2
Stable tag: 1.0.0
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Returns portal and RMA for WooCommerce with the EU withdrawal button. Guest and partial returns, photos, refund and cancel order requests.

== Description ==

Most stores still handle returns by email. The customer writes in, you ask for the order number, then for photos, then you work out the refund by hand and hope you remember to restock the item.

This is a free **[WooCommerce returns plugin](https://wpreturns.com/)** that gives your customers a proper returns portal and gives you one RMA screen for every return, refund and cancel order request. Customers pick the items, a reason and a quantity, add photos and send the request. You approve it, upload a return label if you have one, mark the parcel received and refund it through WooCommerce in one click. Stock goes back automatically.

It also adds the **EU withdrawal button**. Since 19 June 2026, online shops selling to consumers in the EU must offer a "Withdraw from contract here" function with a confirmation step and an acknowledgement on a durable medium (Directive (EU) 2023/2673). The plugin adds the button, the confirmation step and the acknowledgement email, for guests and account holders. Check your own obligations with a lawyer in the countries you sell to.

Everything runs inside WooCommerce on your own server. No account to sign up for, no per-return fees, and no customer or order data sent anywhere.

https://www.youtube.com/watch?v=AFI6gmH5g7I

= Quick Links =

* Free Demo: use the Live Preview button on this page
* [Pro Demo](https://demo.devdiggers.com/wpreturns/)
* [Documentation](https://wpreturns.com/docs/)
* [Support](https://wordpress.org/support/plugin/devdiggers-returns-for-woocommerce/)
* [Free vs Pro Comparison](https://wpreturns.com/free-vs-pro/)
* [Upgrade to Pro](https://wpreturns.com/pricing/)

= How a return works =

1. The customer opens the order in My Account, or finds it on the guest returns portal with the order number and billing email. The order shows how many days are left to return it.
2. They choose the items, the quantity, a reason and the condition, write a note and add photos or videos.
3. You get an email. On the request screen you see the items, photos and the conversation, and approve or reject.
4. Upload a return label (any carrier) or let the customer use their own. They add the tracking number.
5. When the parcel arrives, mark it received. Items go back in stock, except damaged or defective ones.
6. Refund to the original payment method in one click. The customer is emailed at every step.

= Key Features =

=== Customer returns portal in My Account and for guests ===

Logged in customers get a Returns page in My Account and a "Request a return" button on every eligible order. Guests use the returns portal with their order number and billing email, no account needed.

* **Guest returns portal** with the shortcode `[ddwcwr_returns_portal]`, a Returns portal block, and a page created for you on activation.
* **Partial returns** with a quantity per item.
* A reason and condition per item, a customer note, and **photo and video uploads** with your own file count, size and type limits.
* Return reasons you manage yourself, as many as you like, with "Photo required" per reason.
* "You can request a return until 15 November (16 days left)" on every eligible order, so customers know where they stand.
* A request page with a progress tracker, a message thread with your team (with attachments), the return label download and a tracking number field.
* You choose which order statuses can be returned, and you can rename the My Account menu item and its URL.

=== Cancel order requests before shipping ===

Customers can ask to cancel an order that has not shipped yet. You choose which order statuses allow it.

* A "Request cancellation" button on eligible orders in My Account and on the guest portal.
* Approving the request cancels the order and refunds it through WooCommerce.

=== Return policy, return window and final-sale items ===

* One return window for the store, counted from delivery or from purchase.
* **Final-sale products and categories.** Tick "Final sale" on a product or pick final-sale categories, and those items can no longer be selected on the return form. The product page says so before the customer buys.
* The return policy line on product pages and above the Place order button, in classic and block checkout, with a link to your return policy page.

=== EU withdrawal button (Widerrufsbutton) ===

* Step 1: a "Withdraw from contract here" link in the footer, in My Account, on eligible orders and in the order emails.
* Step 2: the withdrawal statement (name, order, email), prefilled for logged in customers, then a "Confirm withdrawal" button.
* An **acknowledgement email** with the full statement and the date and time it was received, sent at once.
* The **model withdrawal form** (Annex I(B)) on the withdrawal page, addressed to your store, for customers who prefer to write.
* A withdrawal page created on activation, plus the shortcode `[ddwcwr_withdrawal_form]` and a Withdrawal form block.
* Order and email matching, a honeypot and a rate limit stop people using the form to email strangers. Statements that do not match an order are still kept for you.
* Art. 16 exemptions per product and per category (made to order, perishable, sealed hygiene goods and so on).
* **Consent for digital content** (Art. 16(m)): when the cart has downloadable products, checkout shows a required checkbox. The customer agrees to immediate supply, the order emails confirm it and those downloads leave the withdrawal flow. Works in classic and block checkout. Off by default.
* EU and EEA countries, all countries or the countries you choose, with your own withdrawal period.
* A Withdrawals screen with every statement and a **CSV export**, your record if there is ever a dispute.

=== RMA screen for the shop team ===

* A dashboard with requests, open requests, refunds, withdrawals and time to resolve, plus a requests chart.
* A Requests list with status tabs and counts, filters by type, reason and date, and search by request, order, name, email or tracking number.
* A request screen with a "Next step" card, items, photos, the conversation, internal notes and a full history.
* Approve, reject, upload a label, save tracking, mark received with restock, and **refund through WooCommerce** (to the gateway or recorded only).
* Create a request yourself for phone or email returns.
* Rename and recolor the seven built-in statuses, add your own (such as "Inspecting"), and choose which ones email the customer.
* A Returns box on the order screen and a Returns column in the orders list.
* A setup wizard on first activation.

=== Import requests from another returns plugin ===

Switching plugins does not mean losing your history. The Import tab finds requests left by other returns, cancellation and withdrawal plugins, even after they are deactivated, and brings them over with their items, status, dates and messages.

Supported: Return Refund and Exchange for WooCommerce, Flexible Refund and Return Order, YITH Advanced Refund System, Germanized for WooCommerce, Shiptastic for WooCommerce, WC Cancel Order, Cancel Order Request for WooCommerce, Order Cancellation & Returns, Withdrawal Button – Easy Return and BuddyPilot Withdrawal.

=== Built the WooCommerce way ===

* Every email is a WooCommerce email: edit it under WooCommerce > Settings > Emails or switch it off from the plugin.
* Photos are stored in a private folder and only shown to the customer and your team.
* GDPR export and erase for every request. Withdrawal records are anonymized, not deleted.
* HPOS, block and classic checkout, RTL and translation ready, with a `wpml-config.xml` so WPML and Polylang can translate your reasons, statuses and policy line.

= Free vs Pro =

The free plugin covers the full return, refund, cancel order and EU withdrawal workflow with no limits on requests. [Pro](https://wpreturns.com/pricing/) is for stores where returns are a cost worth managing: it turns refunds into store credit and exchanges, decides routine requests for you and shows where returns come from.

=== Store credit and exchanges (Pro) ===

* **Store credit** as a coupon or DevDiggers Wallet balance, with a bonus to make it the customer's first choice.
* **Exchanges** for another size, color or any product, with the price difference charged or refunded on a linked order.
* **Warranty claims** with warranty per product, and **extended warranties** sold on the product page.

=== Rules and automation (Pro) ===

* **Return rules**: windows per product, category, role or country, final-sale rules by item price, order total or customer, auto-approve and auto-reject, restocking fees, return shipping fees, free return shipping thresholds, photo rules and keep-it refunds.
* **Automation**: instant cancellations, label and ship-by reminders, auto-close of stale requests and automatic refunds after receipt.
* **Bulk actions** on the Requests list.

=== Shipping labels and fraud protection (Pro) ===

* **Prepaid return labels** from your EasyPost or Shippo account, automatically on approval, and a printable return slip with a QR code.
* **Fraud protection**: return rate per customer, serial returner, wear-and-return and high-value flags, a risk score, a Customers screen and a blocklist. EU withdrawals are only flagged, never refused.

=== Analytics, team and developers (Pro) ===

* **Analytics**: return rate, revenue kept, top reasons, categories and most returned products, with a full CSV export of every request.
* Team permissions and assignment, appearance settings, REST API and WooCommerce webhooks.

Pro uses the same tables and settings, so every request, message, photo and withdrawal you collect in the free plugin carries over when you upgrade.

== External services ==

The free plugin does not send customer or order data to any outside service. The bundled DevDiggers Framework, which builds the admin screens, connects to the services below, and WordPress core may load Gravatar images.

**1. DevDiggers plugin catalog (Extensions page)**

* What it is: A public endpoint on devdiggers.com that lists DevDiggers plugins.
* What it is used for: Showing other DevDiggers plugins on the Extensions admin page.
* When data is sent: Only when an administrator opens the Extensions page. The response is cached for 24 hours, so the request runs at most once a day. No other screen makes this request.
* What data is sent: A standard outbound HTTP request only, meaning your server's IP address and a user agent string. No personal data and no store data are sent. The page also loads each listed extension's image from devdiggers.com in the administrator's browser.
* Endpoint: https://devdiggers.com/wp-json/ddwcs/v1/plugins

**2. Newsletter subscription (optional)**

* What it is: A contact and newsletter endpoint on devdiggers.com.
* What it is used for: Adding your email address to the DevDiggers newsletter, only if you choose to subscribe.
* When data is sent: Only when an administrator submits the optional newsletter form on the DevDiggers Plugins dashboard. Nothing is sent automatically.
* What data is sent: The email address you enter and your site URL.
* Endpoint: https://devdiggers.com/?fluentcrm=1&route=contact

The services above are provided by DevDiggers: Terms and Conditions https://devdiggers.com/terms-and-conditions/ and Privacy Policy https://devdiggers.com/privacy-policy/.

**3. Gravatar (avatar images)**

* What it is: Gravatar, the avatar service operated by Automattic, used through WordPress core's `get_avatar()`.
* What it is used for: Showing the customer's avatar on the request screen and the administrator's avatar in the plugin dashboard header. This is standard WordPress behaviour.
* When data is sent: When those admin screens are viewed and avatars are enabled under Settings > Discussion. Turning avatars off there stops the requests.
* What data is sent: A hash of the email address inside the image URL, plus the usual request data such as IP address and user agent.
* Endpoint: https://secure.gravatar.com/avatar/
* Terms of Service: https://automattic.com/tos/ – Privacy Policy: https://automattic.com/privacy/

This plugin contains no licence checks, no update checks and no telemetry.

== Source code and build tools ==

The admin, storefront and block editor JavaScript and CSS are compiled from the `src/` folder with webpack and Babel. The complete, human readable source code, including all build configuration, is published in our public GitHub repository:

https://github.com/DevDiggers-Technologies/returns-for-woocommerce

To build the assets from source:

1. Run `npm install` to install the build dependencies listed in `package.json`.
2. Run `npm run build` to compile the production assets into `assets/js` and `assets/css`.

The build configuration is in `webpack.config.js` and `babel.config.js`.

The admin screens share the bundled DevDiggers Framework. The human readable source of its compiled files in `devdiggers-framework/assets/js` and `devdiggers-framework/assets/css` (framework, dashboard, dashboard analytics, setup wizard and review notice) is in the same GitHub repository under `devdiggers-framework/src`, with its own `package.json`, `webpack.config.js` and `babel.config.js`. To rebuild them, run `npm install` and then `npm run build` inside the `devdiggers-framework` folder.

Two third party libraries used by the admin screens ship unmodified under the MIT licence inside `devdiggers-framework/assets/js/`: Chart.js (https://www.chartjs.org) and Select2 (https://select2.org).

== Installation ==

1. Install and activate WooCommerce.
2. Go to Plugins > Add New Plugin, search for "returns portal withdrawal button", then install and activate this plugin. Or upload the `devdiggers-returns-for-woocommerce` folder to `/wp-content/plugins/` and activate it.
3. The setup wizard opens and asks a few questions.
4. The "Returns" and "Withdraw from contract" pages are created for you. Add them to your menus or footer.
5. Check DevDiggers Plugins > DevDiggers Returns > Configuration, especially the Return Policy and EU Withdrawal tabs.

== Frequently Asked Questions ==

= Do I need the EU withdrawal button? =

If you sell to consumers in the EU or EEA, yes. Since 19 June 2026 online shops must offer a withdrawal function next to the usual ways of withdrawing. It is on by default for orders from EU and EEA countries, and you can switch it off or choose the countries.

= Is the withdrawal function free forever? =

Yes. The whole EU withdrawal flow, the acknowledgement email, the model withdrawal form, the withdrawal log and its CSV export are part of the free plugin.

= What is the difference between a return and a withdrawal? =

A return follows your own return policy: your window, your reasons, your final-sale items. A withdrawal is the customer's legal right in the EU to cancel a distance contract within 14 days without a reason. The plugin keeps them apart, so a final-sale item cannot be returned but can still be withdrawn from unless it is exempt under Art. 16.

= Can guests return items without an account? =

Yes. The guest returns portal finds the order by order number and billing email, and guests get a private link to follow their request.

= Can customers return only some of the items? =

Yes. They pick the items and the quantity of each one.

= Can customers cancel an order from My Account? =

They can send a cancel order request for orders that have not shipped. You choose the order statuses that allow it under Configuration > General. When you approve the request, the order is cancelled and refunded through WooCommerce.

= How do I stop returns on sale items or certain products? =

Tick "Final sale" on the product's General tab, or choose final-sale categories under Configuration > Return Policy. Those items are shown as not returnable on the return form and the product page. Pro adds final-sale rules by item price, order total or customer.

= Does it refund through my payment gateway? =

Refunds are WooCommerce refunds, so they go through your gateway when it supports refunds (Stripe, PayPal and most others). You can also record a refund and pay it back yourself.

= Can I charge a restocking fee or offer store credit instead of a refund? =

Those are Pro features, along with exchanges, warranty claims, return rules and prepaid labels.

= How is it different from other WooCommerce returns plugins? =

The guest returns portal, partial returns with quantities and photo uploads are free here, while several other returns plugins keep them for their paid version. It is also a full EU withdrawal button, so you do not need a second plugin for that.

= Can I move my requests over from another returns plugin? =

Yes. Go to Configuration > Import. It lists the supported plugins it finds on your site, even deactivated ones, and imports their requests with items, status, dates and messages. Running it again only brings over new requests.

= Where are customer photos stored? =

In a private folder inside uploads with random file names, never in the media library. They are only served after checking that the visitor is the customer (or has the private request link) or a shop manager. On Apache the folder is also blocked with .htaccess. On nginx add `location ~ /ddwcwr-private- { deny all; }`, or move the folder with the `ddwcwr_private_upload_dir` filter.

= Can I translate the return reasons and statuses? =

Yes. All plugin text is translation ready, and the bundled `wpml-config.xml` lets WPML and Polylang translate your own reasons, status names, policy line and My Account menu label.

= I sell downloads. Do customers keep the right of withdrawal? =

Only until they agree to immediate supply. Turn on "Digital content" in the EU Withdrawal tab and checkout asks for that consent whenever the cart has downloadable products. The time of consent is saved on the order, shown on the order screen and confirmed in the order emails, and those downloads can no longer be withdrawn. In block checkout this uses WooCommerce's conditional checkout fields, so keep WooCommerce up to date.

= Does it work with HPOS and the block checkout? =

Yes, with both, and with classic checkout and the legacy order storage.

= I used the Pro version before. Will my data carry over? =

Yes. The free plugin uses the same tables and settings. Requests, messages, photos and withdrawals stay as they are, and Pro features come back when you activate Pro again.

= Where do I get support? =

Free version questions go to the support forum here on WordPress.org. The developers who build the plugin answer there. Pro license holders get direct support from DevDiggers through a ticket.

== Screenshots ==

1. Customers start a return from My Account: pick the items, a reason and the condition, then add a note or photos.
2. Guests find their order with the order number and email on the returns page, no account needed.
3. The EU "Withdraw from contract here" function: a two-step withdrawal statement for orders from the EU and EEA.
4. Customers get an on-screen confirmation and an email with the date and time their withdrawal was received.
5. Each request has its own page with the progress, the return label, tracking and messages with your team.
6. All returns, cancellations and EU withdrawals in one list with status tabs, filters and search.
7. The request screen: approve or reject, upload a return label, mark items received and refund from one place.
8. Set the return window, final sale categories and what customers can upload.
9. Turn on the EU withdrawal function and choose the countries, the withdrawal period and where the link shows.
10. Ten WooCommerce emails keep customers and your team updated at every step.

== Changelog ==

= 1.0.0 - 2026-10-31 =
* Initial free release.
* Returns and cancellations from My Account and from a guest returns portal (page, shortcode and block), with partial returns and a quantity per item.
* Return reasons you manage, condition per item, customer note, and photo and video uploads stored in a private folder.
* Return window from delivery or purchase, final-sale products and categories, and "days left to return" on every eligible order.
* Request page for the customer with progress, messages with attachments, the return label download and a tracking number field.
* Request screen for the shop team: next step card, approve or reject, upload a label, mark received with restock, refund through WooCommerce, internal notes and history.
* EU withdrawal function: "Withdraw from contract here" link, two-step statement with "Confirm withdrawal", acknowledgement email, model withdrawal form, Art. 16 exemptions and a withdrawal log with CSV export.
* Checkout consent for digital content (Art. 16(m)) in classic and block checkout, with the consent confirmed in the order emails.
* Dashboard, Requests list with filters and search, editable built-in and custom statuses, Returns box on the order screen and a setup wizard.
* Import of requests from ten other returns, cancellation and withdrawal plugins.
* WooCommerce emails, GDPR export and erase, HPOS, classic and block checkout, translation ready with WPML and Polylang support for your own texts.

== Upgrade Notice ==

= 1.0.0 =
First release. After activating, run the setup wizard and check the Return Policy and EU Withdrawal tabs.
