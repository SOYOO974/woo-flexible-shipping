<?php
namespace WooFlexibleShipping\Shipping;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WooFlexibleShipping\Admin\TableRateField;

/**
 * Standalone WooCommerce Shipping Method for Table Rate Shipping.
 * Inherits from WC_Shipping_Method to be added to any WooCommerce shipping zone.
 */
class TableRateMethod extends \WC_Shipping_Method {

	/**
	 * Constructor.
	 *
	 * @param int $instance_id Instance ID.
	 */
	public function __construct( int $instance_id = 0 ) {
		$this->id                 = 'soyoo_table_rate';
		$this->instance_id        = function_exists( 'absint' ) ? absint( $instance_id ) : (int) $instance_id;
		$this->method_title       = function_exists( '__' ) ? __( 'Soyoo Table Rate', 'woo-flexible-shipping' ) : 'Soyoo Table Rate';
		$this->method_description = function_exists( '__' ) ? __( 'Tiered table rate shipping based on item quantity, package weight, or cart subtotal.', 'woo-flexible-shipping' ) : 'Tiered table rate shipping based on item quantity, package weight, or cart subtotal.';
		
		// Remove 'instance-settings-modal' so WooCommerce links directly to dedicated edit page.
		$this->supports = array(
			'shipping-zones',
			'instance-settings',
		);

		$this->init();
	}

	/**
	 * Initialize settings form fields and load settings.
	 *
	 * @return void
	 */
	public function init(): void {
		$this->init_form_fields();
		$this->init_settings();

		$this->title      = $this->get_option( 'title', $this->method_title );
		$this->tax_status = $this->get_option( 'tax_status', 'taxable' );

		add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
	}

	/**
	 * Define admin form fields for the shipping method.
	 *
	 * @return void
	 */
	public function init_form_fields(): void {
		$fields = array(
			'title'      => array(
				'title'       => __( 'Method Title', 'woo-flexible-shipping' ),
				'type'        => 'text',
				'description' => __( 'Title shown to customers during checkout.', 'woo-flexible-shipping' ),
				'default'     => __( 'Soyoo Table Rate', 'woo-flexible-shipping' ),
				'desc_tip'    => true,
			),
			'method_description' => array(
				'title'       => __( 'Method Description', 'woo-flexible-shipping' ),
				'type'        => 'textarea',
				'description' => __( 'Optional description displayed to customers below the shipping method name on checkout.', 'woo-flexible-shipping' ),
				'default'     => '',
				'desc_tip'    => true,
				'css'         => 'width: 400px; height: 65px;',
			),
			'tax_status'         => array(
				'title'   => __( 'Tax status', 'woo-flexible-shipping' ),
				'type'    => 'select',
				'default' => 'taxable',
				'options' => array(
					'taxable' => __( 'Taxable', 'woo-flexible-shipping' ),
					'none'    => __( 'None', 'woo-flexible-shipping' ),
				),
			),
			'cost'               => array(
				'title'       => __( 'Base Cost', 'woo-flexible-shipping' ),
				'type'        => 'text',
				'placeholder' => '0.00',
				'description' => __( 'Base shipping cost before tiered rules apply.', 'woo-flexible-shipping' ),
				'default'     => '0.00',
				'desc_tip'    => true,
			),
		);

		// Include the Table Rate Rules Matrix when editing on dedicated page.
		$is_dedicated_page = ( function_exists( 'is_admin' ) && is_admin() ) && ( isset( $_GET['instance_id'] ) || ( isset( $_GET['page'] ) && 'wc-settings' === $_GET['page'] && isset( $_GET['tab'] ) && 'shipping' === $_GET['tab'] && isset( $_GET['instance_id'] ) ) );

		if ( $is_dedicated_page || ( function_exists( 'is_admin' ) && ! is_admin() ) ) {
			$fields['wfs_method_rules'] = array(
				'title'             => __( 'Table Rate Rules Matrix', 'woo-flexible-shipping' ),
				'type'              => TableRateField::FIELD_TYPE,
				'description'       => __( 'Define tiered rules based on item count, package weight, or cart subtotal.', 'woo-flexible-shipping' ),
				'default'           => '[]',
				'sanitize_callback' => array( TableRateField::class, 'sanitize_rules' ),
			);
		} else {
			// In modal view, show callout notice directing to dedicated page.
			$fields['wfs_modal_notice'] = array(
				'title'       => __( 'Table Rate Rules Configuration', 'woo-flexible-shipping' ),
				'type'        => 'title',
				'description' => __( 'Save changes to open the dedicated full-page editor and configure tiered shipping rules.', 'woo-flexible-shipping' ),
			);
		}

		$this->instance_form_fields = $fields;
	}

	/**
	 * Calculate shipping rate for a package.
	 *
	 * @param array $package Package being rated.
	 * @return void
	 */
	public function calculate_shipping( $package = array() ): void {
		$base_cost   = (float) wc_format_decimal( $this->get_option( 'cost', '0.00' ) );
		$description = $this->get_option( 'method_description', '' );
		$raw_rules   = $this->get_option( 'wfs_method_rules', '[]' );
		$rules       = is_array( $raw_rules ) ? $raw_rules : json_decode( $raw_rules, true );

		$engine = new CalculationEngine();
		$result = $engine->calculate( $package, is_array( $rules ) ? $rules : array() );

		$surcharge = (float) $result['surcharge'];
		$total     = max( 0.0, $base_cost + $surcharge );

		if ( ! empty( $result['applied_rules'] ) ) {
			$rule_labels = array_column( $result['applied_rules'], 'label' );
			$rule_text   = implode( '; ', $rule_labels );
		} else {
			$rule_text   = function_exists( '__' ) ? __( 'Base Shipping Only (No tier surcharge applied)', 'woo-flexible-shipping' ) : 'Base Shipping Only (No tier surcharge applied)';
		}

		$breakdown = array(
			'base'          => wc_price( $base_cost ),
			'additional'    => ( $surcharge >= 0 ? '+' : '' ) . wc_price( $surcharge ),
			'total'         => wc_price( $total ),
			'rule_applied'  => $rule_text,
			'applied_rules' => $result['applied_rules'],
		);

		$meta_data = array(
			'fs_costs'          => json_encode( $breakdown, JSON_UNESCAPED_UNICODE ),
			'_table_rate_costs' => json_encode( $breakdown, JSON_UNESCAPED_UNICODE ),
		);

		if ( ! empty( $description ) ) {
			$meta_data['method_description'] = $description;
		}

		$rate = array(
			'id'        => $this->get_option_key(),
			'label'     => $this->title,
			'cost'      => $total,
			'package'   => $package,
			'meta_data' => $meta_data,
		);

		$this->add_rate( $rate );
	}
}
