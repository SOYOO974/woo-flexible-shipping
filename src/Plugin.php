<?php
namespace WooFlexibleShipping;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WooFlexibleShipping\Shipping\FlatRateExtension;
use WooFlexibleShipping\Shipping\TableRateMethod;
use WooFlexibleShipping\Shipping\OrderMetadata;
use WooFlexibleShipping\Admin\TableRateField;
use WooFlexibleShipping\Admin\Assets;
use WooFlexibleShipping\Migration\AdminMigrationPage;
use WooFlexibleShipping\Migration\CLICommand;
use WooFlexibleShipping\Updater\PluginUpdater;

/**
 * Main Singleton Plugin Orchestrator.
 */
class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static ?Plugin $instance = null;

	/**
	 * Get Singleton Instance.
	 *
	 * @return Plugin
	 */
	public static function get_instance(): Plugin {
		if ( null === static::$instance ) {
			static::$instance = new static();
		}
		return static::$instance;
	}

	/**
	 * Initialize plugin hooks and features.
	 *
	 * @return void
	 */
	public function init(): void {
		// Load textdomain for i18n.
		load_plugin_textdomain(
			WFS_TEXT_DOMAIN,
			false,
			dirname( plugin_basename( WFS_PLUGIN_FILE ) ) . '/languages'
		);

		// Initialize Custom Settings Field for WooCommerce API.
		TableRateField::init();

		// Initialize Shipping Extensions.
		( new FlatRateExtension() )->init();
		( new OrderMetadata() )->init();

		// Register Standalone Shipping Method.
		add_filter( 'woocommerce_shipping_methods', array( $this, 'register_shipping_method' ) );

		// Initialize Admin Assets & Migration UI.
		if ( is_admin() ) {
			( new Assets() )->init();
			( new AdminMigrationPage() )->init();
		}

		// Initialize WP-CLI.
		CLICommand::init();

		// Initialize Auto-Updater fallback if PUC is absent.
		if ( ! file_exists( WFS_PLUGIN_DIR . 'plugin-update-checker/plugin-update-checker.php' ) ) {
			( new PluginUpdater( WFS_PLUGIN_FILE, WFS_VERSION ) )->init();
		}
	}

	/**
	 * Register standalone WC_Shipping_Method with WooCommerce.
	 *
	 * @param array $methods Existing shipping methods array.
	 * @return array Modified methods array.
	 */
	public function register_shipping_method( array $methods ): array {
		$methods['soyoo_table_rate'] = TableRateMethod::class;
		return $methods;
	}
}
