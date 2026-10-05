<?php
/**
 * Plugin Name: Woo Flexible Shipping Table Rate
 * Plugin URI: https://soyoo.re
 * Description: Ultra-lightweight, high-performance WooCommerce table rate shipping engine with order traceability, HPOS compatibility, and 1-click migration from Flexible Shipping PRO.
 * Version: 1.0.1
 * Author: Soyoo.re
 * Text Domain: woo-flexible-shipping
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 9.3
 *
 * @package WooFlexibleShipping
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WFS_VERSION', '1.0.1' );
define( 'WFS_PLUGIN_FILE', __FILE__ );
define( 'WFS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WFS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WFS_TEXT_DOMAIN', 'woo-flexible-shipping' );

// Automatic Updates via GitHub (plugin-update-checker v5.6)
if ( file_exists( __DIR__ . '/plugin-update-checker/plugin-update-checker.php' ) ) {
	require_once __DIR__ . '/plugin-update-checker/plugin-update-checker.php';
	$wfs_update_checker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
		'https://github.com/SOYOO974/woo-flexible-shipping/',
		__FILE__,
		'woo-flexible-shipping'
	);
	$wfs_update_checker->setBranch( 'main' );
	$wfs_update_checker->getVcsApi()->enableReleaseAssets();
}

// Register HPOS (High-Performance Order Storage) compatibility before WooCommerce initializes.
add_action(
	'before_woocommerce_init',
	function() {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', WFS_PLUGIN_FILE, true );
		}
	}
);

// Autoload plugin classes.
require_once WFS_PLUGIN_DIR . 'src/Autoloader.php';
WooFlexibleShipping\Autoloader::register();

// Boot plugin.
add_action(
	'plugins_loaded',
	function() {
		WooFlexibleShipping\Plugin::get_instance()->init();
	}
);
