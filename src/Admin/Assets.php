<?php
namespace WooFlexibleShipping\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enqueues CSS and JS assets for the admin table rate editor.
 */
class Assets {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Enqueue admin scripts & styles on shipping settings pages.
	 *
	 * @param string $hook_suffix Current admin page suffix.
	 * @return void
	 */
	public function enqueue_assets( string $hook_suffix ): void {
		// Only load on WooCommerce shipping settings pages or modal context.
		$is_wc_shipping = isset( $_GET['page'] ) && 'wc-settings' === $_GET['page'] && isset( $_GET['tab'] ) && 'shipping' === $_GET['tab'];

		if ( ! $is_wc_shipping && 'woocommerce_page_wc-settings' !== $hook_suffix ) {
			return;
		}

		wp_enqueue_style(
			'wfs-admin-editor-css',
			WFS_PLUGIN_URL . 'assets/css/admin-table-editor.css',
			array(),
			WFS_VERSION
		);

		wp_enqueue_script(
			'wfs-admin-editor-js',
			WFS_PLUGIN_URL . 'assets/js/admin-table-editor.js',
			array( 'jquery' ),
			WFS_VERSION,
			true
		);

		wp_localize_script(
			'wfs-admin-editor-js',
			'wfs_i18n',
			array(
				'confirm_delete' => __( 'Are you sure you want to remove this shipping rule?', 'woo-flexible-shipping' ),
				'min_max_error'  => __( 'Minimum boundary cannot be greater than maximum boundary.', 'woo-flexible-shipping' ),
				'item_label'     => __( 'Item Count (Quantity)', 'woo-flexible-shipping' ),
				'weight_label'   => __( 'Total Weight (kg/lbs)', 'woo-flexible-shipping' ),
				'price_label'    => __( 'Cart Subtotal (€)', 'woo-flexible-shipping' ),
			)
		);
	}
}
