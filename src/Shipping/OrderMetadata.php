<?php
namespace WooFlexibleShipping\Shipping;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles attaching cost breakdown metadata to WC_Order_Item_Shipping and displaying it in WooCommerce Admin.
 */
class OrderMetadata {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'woocommerce_checkout_create_order_shipping_item', array( $this, 'attach_shipping_meta' ), 10, 3 );
		add_action( 'woocommerce_after_shipping_rate', array( $this, 'render_cart_rate_breakdown' ), 10, 1 );
		add_action( 'woocommerce_after_order_itemmeta', array( $this, 'render_admin_order_item_meta' ), 10, 3 );
		add_filter( 'woocommerce_hidden_order_itemmeta', array( $this, 'hide_raw_meta_keys' ) );
	}

	/**
	 * Hide raw JSON meta keys from WooCommerce core's default text dump in admin order tables.
	 *
	 * @param array $hidden_keys Hidden meta keys.
	 * @return array Modified hidden meta keys.
	 */
	public function hide_raw_meta_keys( array $hidden_keys ): array {
		$hidden_keys[] = 'fs_costs';
		$hidden_keys[] = '_table_rate_costs';
		return $hidden_keys;
	}

	/**
	 * Transfer fs_costs metadata from shipping rate to order shipping line item.
	 *
	 * @param \WC_Order_Item_Shipping|mixed $item Order shipping line item.
	 * @param string $package_key Package key.
	 * @param array $package Shipping rate package.
	 * @return void
	 */
	public function attach_shipping_meta( $item = null, $package_key = '', $package = array() ): void {
		if ( ! is_object( $item ) || ! method_exists( $item, 'add_meta_data' ) ) {
			return;
		}

		if ( ! isset( $package['rates'] ) || ! is_array( $package['rates'] ) || empty( $package['rates'] ) ) {
			return;
		}

		$item_method_id   = method_exists( $item, 'get_method_id' ) ? (string) $item->get_method_id() : '';
		$item_instance_id = method_exists( $item, 'get_instance_id' ) ? absint( $item->get_instance_id() ) : 0;
		$chosen_rate_id   = $item_instance_id > 0 ? $item_method_id . ':' . $item_instance_id : $item_method_id;

		$matched_rate = null;

		// 1. Try finding rate by exact rate ID matching the order shipping item.
		if ( ! empty( $chosen_rate_id ) && isset( $package['rates'][ $chosen_rate_id ] ) ) {
			$matched_rate = $package['rates'][ $chosen_rate_id ];
		} else {
			foreach ( $package['rates'] as $rate_id => $rate ) {
				if ( is_object( $rate ) ) {
					if ( (string) $rate_id === $chosen_rate_id || ( method_exists( $rate, 'get_instance_id' ) && $item_instance_id > 0 && $rate->get_instance_id() === $item_instance_id ) ) {
						$matched_rate = $rate;
						break;
					}
				}
			}
		}

		// 2. Fallback: match first available rate with fs_costs.
		if ( ! $matched_rate ) {
			foreach ( $package['rates'] as $rate ) {
				if ( is_object( $rate ) && method_exists( $rate, 'get_meta_data' ) ) {
					$meta = $rate->get_meta_data();
					if ( ! empty( $meta['fs_costs'] ) ) {
						$matched_rate = $rate;
						break;
					}
				}
			}
		}

		if ( is_object( $matched_rate ) && method_exists( $matched_rate, 'get_meta_data' ) ) {
			$meta = $matched_rate->get_meta_data();
			if ( ! empty( $meta['fs_costs'] ) ) {
				$item->add_meta_data( 'fs_costs', $meta['fs_costs'], true );
				$item->add_meta_data( '_table_rate_costs', $meta['fs_costs'], true );
			}
		}
	}

	/**
	 * Display formatted table rate shipping cost breakdown in WordPress Admin Order details view.
	 *
	 * @param int $item_id Item ID.
	 * @param \WC_Order_Item $item Order item.
	 * @param \WC_Order $order Order instance.
	 * @return void
	 */
	public function render_admin_order_item_meta( $item_id, $item, $order ): void {
		if ( ! is_a( $item, 'WC_Order_Item_Shipping' ) ) {
			return;
		}

		$raw_meta = $item->get_meta( 'fs_costs', true );
		if ( empty( $raw_meta ) ) {
			$raw_meta = $item->get_meta( '_table_rate_costs', true );
		}

		if ( empty( $raw_meta ) ) {
			return;
		}

		$data = is_array( $raw_meta ) ? $raw_meta : json_decode( $raw_meta, true );
		if ( empty( $data ) || ! is_array( $data ) ) {
			return;
		}

		echo '<div class="wfs-order-shipping-breakdown" style="margin-top:8px; padding:10px 14px; background:#f8fafc; border-left:4px solid #2271b1; border-radius:6px; font-size:12px; line-height:1.6; color:#1d2327; box-shadow:0 1px 3px rgba(0,0,0,0.03);">';
		echo '<strong style="color:#1d2327; font-size:13px;">' . esc_html__( 'Table Rate Calculation Breakdown:', 'woo-flexible-shipping' ) . '</strong><br/>';

		if ( ! empty( $data['base'] ) ) {
			echo esc_html__( 'Base Price:', 'woo-flexible-shipping' ) . ' <strong>' . esc_html( wp_strip_all_tags( $data['base'] ) ) . '</strong><br/>';
		}
		if ( isset( $data['additional'] ) ) {
			echo esc_html__( 'Tier Surcharge:', 'woo-flexible-shipping' ) . ' <strong>' . esc_html( wp_strip_all_tags( $data['additional'] ) ) . '</strong><br/>';
		}
		if ( ! empty( $data['rule_applied'] ) ) {
			echo '<span style="color:#475569;"><em>' . esc_html__( 'Rule Applied:', 'woo-flexible-shipping' ) . ' ' . esc_html( $data['rule_applied'] ) . '</em></span>';
		}

		echo '<div style="margin-top: 8px; padding-top: 6px; border-top: 1px solid #e2e8f0; font-size: 11px; color: #64748b;">';
		echo 'ℹ️ ' . esc_html__( 'Saved in order shipping itemmeta (fs_costs & _table_rate_costs) for ERP & audit traceability.', 'woo-flexible-shipping' );
		echo '</div>';

		echo '</div>';
	}

	/**
	 * Render cart breakdown description if desired.
	 *
	 * @param \WC_Shipping_Rate $rate Rate object.
	 * @return void
	 */
	public function render_cart_rate_breakdown( $rate ): void {
		// Optional subtle breakdown in cart if needed.
	}
}
