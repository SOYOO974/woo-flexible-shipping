=== Woo Flexible Shipping Table Rate ===
Contributors: agency
Tags: woocommerce, shipping, table rate, flexible shipping, rate calculation
Requires at least: 5.8
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 1.0.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Ultra-lightweight, high-performance WooCommerce table rate shipping engine with order traceability, HPOS compatibility, and 1-click migration from Flexible Shipping PRO.

== Description ==

Woo Flexible Shipping Table Rate is a 100% agency-owned, license-free alternative to Flexible Shipping PRO. It provides clean, high-performance shipping table rate calculations based on item count, total package weight, or cart subtotal.

Features:
- Zero telemetry, zero bloat, total package size under 150 KB.
- Tiered surcharges and incremental cost calculations.
- Full HPOS (High-Performance Order Storage) compatibility.
- Order shipping line item metadata (`fs_costs`) breakdown for ERP and accounting compliance.
- 1-Click Migration assistant converting all 28+ Flexible Shipping PRO methods effortlessly.
- WP-CLI command `wp woo-fs migrate` included.
- Modern HTML/JS table editor inside WooCommerce Shipping Zone settings.

== Installation ==

1. Upload `woo-flexible-shipping` to `/wp-content/plugins/`.
2. Activate the plugin via WordPress Admin > Plugins.
3. Go to WooCommerce > Settings > Shipping > Table Rate Migration to run 1-Click Import.

== Changelog ==

= 1.0.2 =
* Expanded 1-click migration engine to convert standalone flexible_shipping_single methods (e.g. baches-mfm.com).

= 1.0.1 =
* Version bump and GitHub update checker verification.

= 1.0.0 =
* Initial production release.
