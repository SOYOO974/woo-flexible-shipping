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

		// Render method description on Cart & Checkout.
		add_action( 'woocommerce_after_shipping_rate', array( $this, 'render_shipping_method_description' ), 10, 2 );

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

	/**
	 * Render shipping method description and logo under shipping rate option on checkout/cart.
	 *
	 * @param \WC_Shipping_Rate $method Shipping rate object.
	 * @param int|string $index Rate index.
	 * @return void
	 */
	public function render_shipping_method_description( $method, $index ): void {
		if ( ! is_object( $method ) || ! method_exists( $method, 'get_meta_data' ) ) {
			return;
		}

		$meta_data   = $method->get_meta_data();
		$description = $meta_data['method_description'] ?? '';
		$logo_url    = $meta_data['method_logo_url'] ?? '';
		$logo_alt    = $meta_data['method_logo_alt'] ?? '';

		if ( empty( $description ) && empty( $logo_url ) ) {
			return;
		}

		if ( ! empty( $description ) ) {
			$formatted_desc = function_exists( 'wpautop' ) ? wpautop( trim( $description ) ) : esc_html( $description );
			$formatted_desc = str_replace( '<p>', '<p style="margin: 0; padding: 0;">', $formatted_desc );
			$desc_margin    = ! empty( $logo_url ) ? 'margin-bottom: 0;' : '';
			echo '<div class="wfs-shipping-method-description shipping-method-description" style="font-size: 0.88em; color: #666; margin-top: 4px; line-height: 1.35;' . esc_attr( $desc_margin ) . '">'
				. wp_kses_post( $formatted_desc )
				. '</div>';
		}

		if ( ! empty( $logo_url ) ) {
			echo '<p class="wfs-shipping-method-logo shipping-method-logo" style="margin: 4px 0 0 0 !important; padding: 0 !important; line-height: 1 !important;">'
				. '<img src="' . esc_url( $logo_url ) . '" alt="' . esc_attr( $logo_alt ) . '" style="max-width:96px;max-height:48px;width:auto;height:auto;margin:0 !important;margin-top:0 !important;margin-bottom:0 !important;display:inline-block;vertical-align:middle;" />'
				. '</p>';
		}
	}
}
