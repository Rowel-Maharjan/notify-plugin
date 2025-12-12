=== NotifyStore - Order Notifications ===
Contributors: nexbil
Tags: notifications, order notifications, ecommerce, webhook
Requires at least: 5.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A lightweight order notification tool that forwards store events using custom webhooks. Compatible with Woo-powered stores.

== Description ==

NotifyStore - Order Notifications helps store owners receive notifications for order-related events.  
It forwards important order data through custom webhooks to external services.

This plugin is compatible with WooCommerce stores, but does not use the “WooCommerce” trademark in its plugin name, ensuring full compliance with WordPress.org requirements.

== Features ==

* Sends order notifications via custom webhook URLs
* Supports order created, updated, and completed triggers
* Lightweight and easy to configure
* Developer-friendly structure for webhook extension

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`.
2. Activate the plugin via **Plugins** in WordPress.
3. Go to **Settings → NotifyStore** to configure webhook URLs.

== Frequently Asked Questions ==

= Does this work with WooCommerce? =
Yes. The plugin detects Woo-powered stores automatically and sends notifications related to order events.

= How do I add a webhook? =
Go to Settings → NotifyStore and enter your webhook endpoint.

== Changelog ==

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.0.0 =
First public release with core notification features.
