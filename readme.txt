=== Woo Flexible Shipping Table Rate ===
Contributors: agency
Tags: woocommerce, shipping, table rate, flexible shipping, rate calculation
Requires at least: 5.8
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 1.0.14
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

= 1.0.14 =
* Add Global & Per-Method Coupon & Loyalty Voucher Rules (`apply_all`, `ignore_patterns`, `ignore_all`) with comma-separated text pattern matching (e.g. `5EUR, 10EUR, FID`).
* Surgically reintegrate ignored coupon discounts into cart subtotal calculation while keeping marketing coupon deductions intact.
* Unify Free Shipping threshold (`method_free_shipping`) and Table Rate Rules Matrix (`wfs_method_rules`) under the same effective cart subtotal calculation.
* Add order shipping metadata traceability for neutralized coupons in admin order breakdown.

= 1.0.13 =
* Fix: Eliminate large vertical spacing on checkout shipping cards by setting top and bottom margins to 0 on shipping method logo images.
* Fix: Neutralize default paragraph margins in shipping method description and logo wrapper for compact presentation.

= 1.0.12 =
* Fix: Remove incompatible zone save hook in OrderMetadata to resolve PHP 8 ArgumentCountError when saving shipping zone methods.
* Fix: Use standard WooCommerce rate ID (get_rate_id()) instead of option key in TableRateMethod to ensure proper multi-method recognition and filtering.
* Fix: Enhance multi-method metadata attribution during checkout order shipping item creation.

= 1.0.11 =
* Map Flexible Shipping 'value' condition to 'price' (Cart Subtotal) across migration, calculation engine, and admin matrix editor.
* Add Free Shipping threshold and Free Shipping label support to Soyoo Table Rate shipping methods and 1-Click migration.

= 1.0.10 =
* Add Method Logo setting to Soyoo Table Rate shipping methods with WordPress Media Library picker and front-end logo display on Cart and Checkout.
* Support method_logo_id migration during 1-Click Flexible Shipping PRO migration.

= 1.0.9 =
* Add base tax rate fallback when location shipping tax rates return empty to guarantee tax extraction when prices_include_tax is enabled.

= 1.0.8 =
* Add Tax Included in Shipping Cost setting with dynamic tax_status toggle and tax-inclusive rate calculation.

= 1.0.7 =
* Preserve exact shipping zone method ordering by positioning new Soyoo Table Rate method directly below the migrated legacy method.

= 1.0.6 =
* Update database table wp_woocommerce_shipping_zone_methods during migration to set is_enabled = 0 for legacy Octolize methods.

= 1.0.5 =
* Update admin Method Description field width to 400px for full alignment with standard WooCommerce settings fields.

= 1.0.4 =
* Add Method Description setting field and render description HTML under shipping method options on cart and checkout.

= 1.0.3 =
* Automatically create native Soyoo Table Rate zone methods and disable legacy Octolize methods during 1-click migration.

= 1.0.2 =
* Expanded 1-click migration engine to convert standalone flexible_shipping_single methods (e.g. baches-mfm.com).

= 1.0.1 =
* Version bump and GitHub update checker verification.

= 1.0.0 =
* Initial production release.
