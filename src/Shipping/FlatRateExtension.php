<?php
namespace WooFlexibleShipping\Shipping;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WooFlexibleShipping\Admin\TableRateField;

/**
 * Extension for WooCommerce Flat Rate methods to add table rate calculation.
 */
class FlatRateExtension {

	const SETTING_ENABLED = 'wfs_calculation_enabled';
	const SETTING_RULES   = 'wfs_method_rules';

	// Legacy setting keys for 1-click & drop-in compatibility with Flexible Shipping PRO.
	const LEGACY_SETTING_ENABLED = 'fs_calculation_enabled';
	const LEGACY_SETTING_RULES   = 'fs_method_rules';

	/**
	 * Calculation engine instance.
	 *
	 * @var CalculationEngine
	 */
	private CalculationEngine $engine;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->engine = new CalculationEngine();
	}

	/**
	 * Hook into WooCommerce.
	 *
	 * @return void
	 */
	public function init(): void {
		add_filter( 'woocommerce_shipping_instance_form_fields_flat_rate', array( $this, 'add_table_rate_fields' ), 10, 1 );
		add_filter( 'woocommerce_package_rates', array( $this, 'filter_package_rates' ), 10, 2 );
		add_filter( 'woocommerce_shipping_method_supports', array( $this, 'remove_modal_support' ), 10, 3 );
	}

	/**
	 * Force WooCommerce to direct to dedicated page by removing instance-settings-modal support.
	 *
	 * @param bool $supports Whether feature is supported.
	 * @param string $feature Feature name.
	 * @param \WC_Shipping_Method $shipping_method Shipping method object.
	 * @return bool Modified supports bool.
	 */
	public function remove_modal_support( bool $supports, string $feature, $shipping_method ): bool {
		if ( 'instance-settings-modal' === $feature && is_object( $shipping_method ) ) {
			$id = $shipping_method->id ?? '';
			if ( 'soyoo_table_rate' === $id || 'flat_rate' === $id ) {
				return false;
			}
		}
		return $supports;
	}

	/**
	 * Add table rate settings fields to flat_rate instance form.
	 *
	 * @param array $fields Flat rate form fields.
	 * @return array Modified fields.
	 */
	public function add_table_rate_fields( array $fields ): array {
		$fields['wfs_section_title'] = array(
			'title'       => __( 'Flexible Table Rate Surcharges', 'woo-flexible-shipping' ),
			'type'        => 'title',
			'description' => __( 'Configure tiered rules based on item count, order weight, or subtotal.', 'woo-flexible-shipping' ),
		);

		$fields[ self::SETTING_ENABLED ] = array(
			'title'       => __( 'Enable Table Rate Rules', 'woo-flexible-shipping' ),
			'type'        => 'checkbox',
			'label'       => __( 'Enable tiered surcharge calculation table for this method', 'woo-flexible-shipping' ),
			'default'     => 'no',
			'class'       => 'wfs-calculation-enabled-checkbox',
		);

		$is_dedicated_page = ( function_exists( 'is_admin' ) && is_admin() ) && isset( $_GET['instance_id'] );

		if ( $is_dedicated_page || ( function_exists( 'is_admin' ) && ! is_admin() ) ) {
			$fields[ self::SETTING_RULES ] = array(
				'title'             => __( 'Table Rate Rules Matrix', 'woo-flexible-shipping' ),
				'type'              => TableRateField::FIELD_TYPE,
				'description'       => __( 'Set minimum, maximum thresholds and cost adjustments.', 'woo-flexible-shipping' ),
				'default'           => '[]',
				'sanitize_callback' => array( TableRateField::class, 'sanitize_rules' ),
			);
		} else {
			$fields['wfs_modal_notice'] = array(
				'title'       => __( 'Table Rate Matrix', 'woo-flexible-shipping' ),
				'type'        => 'title',
				'description' => __( 'Save changes to configure table rate rules on the dedicated page.', 'woo-flexible-shipping' ),
			);
		}

		return $fields;
	}

	/**
	 * Calculate and apply table rate surcharges to WooCommerce package rates.
	 *
	 * @param array $rates Array of WC_Shipping_Rate objects.
	 * @param array $package Package being rated.
	 * @return array Modified rates.
	 */
	public function filter_package_rates( array $rates, array $package ): array {
		foreach ( $rates as $rate_id => $rate ) {
			if ( ! is_object( $rate ) || empty( $rate->method_id ) ) {
				continue;
			}

			if ( 'flat_rate' !== $rate->method_id ) {
				continue;
			}

			$instance_id = $rate->instance_id ?? 0;
			if ( ! $instance_id ) {
				continue;
			}

			$option_key = 'woocommerce_flat_rate_' . $instance_id . '_settings';
			$settings   = get_option( $option_key, array() );

			if ( empty( $settings ) || ! is_array( $settings ) ) {
				continue;
			}

			$is_enabled = ( $settings[ self::SETTING_ENABLED ] ?? 'no' ) === 'yes'
				|| ( $settings[ self::LEGACY_SETTING_ENABLED ] ?? 'no' ) === 'yes';

			if ( ! $is_enabled ) {
				continue;
			}

			$raw_rules = $settings[ self::SETTING_RULES ] ?? '';
			if ( empty( $raw_rules ) || '[]' === $raw_rules ) {
				$raw_rules = $settings[ self::LEGACY_SETTING_RULES ] ?? '[]';
			}

			$rules = is_array( $raw_rules ) ? $raw_rules : json_decode( $raw_rules, true );

			$result = $this->engine->calculate( $package, is_array( $rules ) ? $rules : array() );

			$base_cost = (float) $rate->cost;
			$surcharge = (float) $result['surcharge'];
			$new_cost  = max( 0.0, $base_cost + $surcharge );

			$rate->cost = $new_cost;

			if ( ! empty( $result['applied_rules'] ) ) {
				$rule_labels = array_column( $result['applied_rules'], 'label' );
				$rule_text   = implode( '; ', $rule_labels );
			} else {
				$rule_text   = function_exists( '__' ) ? __( 'Base Shipping Only (No tier surcharge applied)', 'woo-flexible-shipping' ) : 'Base Shipping Only (No tier surcharge applied)';
			}

			$breakdown = array(
				'base'          => wc_price( $base_cost ),
				'additional'    => ( $surcharge >= 0 ? '+' : '' ) . wc_price( $surcharge ),
				'total'         => wc_price( $new_cost ),
				'rule_applied'  => $rule_text,
				'applied_rules' => $result['applied_rules'],
			);

			$rate->add_meta_data( 'fs_costs', json_encode( $breakdown, JSON_UNESCAPED_UNICODE ) );
			$rate->add_meta_data( '_table_rate_costs', json_encode( $breakdown, JSON_UNESCAPED_UNICODE ) );
		}

		return $rates;
	}
}
