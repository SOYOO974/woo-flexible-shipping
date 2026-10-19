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
			'method_logo_id'             => array(
				'title'             => __( 'Method Logo', 'woo-flexible-shipping' ),
				'type'              => 'method_logo',
				'description'       => __( 'Choose an image from the media library to display after the shipping method description in checkout.', 'woo-flexible-shipping' ),
				'default'           => '',
				'desc_tip'          => true,
				'sanitize_callback' => 'absint',
			),
			'method_free_shipping'       => array(
				'title'       => __( 'Free shipping threshold', 'woo-flexible-shipping' ),
				'type'        => 'text',
				'placeholder' => '0.00',
				'description' => __( 'Enter a minimum cart subtotal value which once reached will result in granting customers free shipping.', 'woo-flexible-shipping' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'method_free_shipping_label' => array(
				'title'       => __( 'Free Shipping Label', 'woo-flexible-shipping' ),
				'type'        => 'text',
				'placeholder' => __( 'e.g. Free or Offerte', 'woo-flexible-shipping' ),
				'description' => __( 'Enter the text for the additional shipping method label which will be displayed once free shipping is granted or calculated cost is 0.00.', 'woo-flexible-shipping' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'wfs_coupon_handling'        => array(
				'title'       => __( 'Coupons & Loyalty Vouchers', 'woo-flexible-shipping' ),
				'type'        => 'select',
				'class'       => 'wfs-coupon-handling-select',
				'default'     => CalculationEngine::COUPON_MODE_INHERIT,
				'options'     => array(
					CalculationEngine::COUPON_MODE_INHERIT         => __( 'Use global plugin setting (Default)', 'woo-flexible-shipping' ),
					CalculationEngine::COUPON_MODE_APPLY_ALL       => __( 'Deduct all coupons (net discounted amount)', 'woo-flexible-shipping' ),
					CalculationEngine::COUPON_MODE_IGNORE_PATTERNS => __( 'Ignore specific coupons matching text patterns (e.g. loyalty)', 'woo-flexible-shipping' ),
					CalculationEngine::COUPON_MODE_IGNORE_ALL      => __( 'Ignore all coupons (pre-discount subtotal)', 'woo-flexible-shipping' ),
				),
				'description' => __( 'Choose whether promo codes and loyalty vouchers reduce the cart amount used for shipping tiers and free shipping threshold.', 'woo-flexible-shipping' ),
				'desc_tip'    => true,
			),
			'wfs_ignored_coupon_patterns' => array(
				'title'       => __( 'Ignored Coupon Text Patterns', 'woo-flexible-shipping' ),
				'type'        => 'text',
				'class'       => 'wfs-ignored-coupon-patterns-input',
				'placeholder' => __( 'e.g. 5EUR, 10EUR, FID', 'woo-flexible-shipping' ),
				'default'     => '',
				'description' => __( 'Comma-separated list of text fragments (case-insensitive). Any applied coupon containing one of these texts will not reduce the order amount for shipping calculations.', 'woo-flexible-shipping' ),
				'desc_tip'    => true,
			),
			'tax_status'                 => array(
				'title'   => __( 'Tax status', 'woo-flexible-shipping' ),
				'type'    => 'select',
				'default' => 'taxable',
				'options' => array(
					'taxable' => __( 'Taxable', 'woo-flexible-shipping' ),
					'none'    => __( 'None', 'woo-flexible-shipping' ),
				),
			),
			'prices_include_tax' => array(
				'title'       => __( 'Tax included in shipping cost', 'woo-flexible-shipping' ),
				'type'        => 'select',
				'default'     => 'no',
				'options'     => array(
					'yes' => __( 'Yes, I will enter the shipping cost inclusive of tax', 'woo-flexible-shipping' ),
					'no'  => __( 'No, I will enter the shipping cost exclusive of tax', 'woo-flexible-shipping' ),
				),
				'description' => __( 'Choose whether shipping rates entered in the table rate matrix include taxes.', 'woo-flexible-shipping' ),
				'desc_tip'    => true,
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
	 * Generate HTML for method_logo custom field.
	 *
	 * @param string $key Field key.
	 * @param array  $data Field parameters.
	 * @return string HTML for the field row.
	 */
	public function generate_method_logo_html( string $key, array $data ): string {
		$field_key     = $this->get_field_key( $key );
		$attachment_id = absint( $this->get_option( $key, 0 ) );
		$image_url     = '';
		$image_alt     = '';

		if ( $attachment_id > 0 && function_exists( 'wp_attachment_is_image' ) && wp_attachment_is_image( $attachment_id ) ) {
			$image = wp_get_attachment_image_src( $attachment_id, 'thumbnail' );
			if ( is_array( $image ) && ! empty( $image[0] ) ) {
				$image_url = (string) $image[0];
			}
			$image_alt = trim( (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) );
		}

		$title       = $data['title'] ?? __( 'Method Logo', 'woo-flexible-shipping' );
		$description = $data['description'] ?? '';
		$tooltip     = $data['desc_tip'] ?? false;

		$tooltip_html = '';
		if ( $tooltip && ! empty( $description ) && function_exists( 'wc_help_tip' ) ) {
			$tooltip_html = wc_help_tip( $description );
		}

		$select_label = ( $attachment_id > 0 ) ? __( 'Change logo', 'woo-flexible-shipping' ) : __( 'Choose logo', 'woo-flexible-shipping' );
		$remove_label = __( 'Remove logo', 'woo-flexible-shipping' );

		ob_start();
		?>
		<tr valign="top">
			<th scope="row" class="titledesc">
				<label for="<?php echo esc_attr( $field_key ); ?>"><?php echo esc_html( $title ); ?> <?php echo $tooltip_html; // WPCS: XSS ok. ?></label>
			</th>
			<td class="forminp">
				<div class="wfs-method-logo-field" data-field-key="<?php echo esc_attr( $field_key ); ?>">
					<input
						type="hidden"
						name="<?php echo esc_attr( $field_key ); ?>"
						id="<?php echo esc_attr( $field_key ); ?>"
						value="<?php echo esc_attr( $attachment_id ? $attachment_id : '' ); ?>"
					/>
					<div class="wfs-method-logo-field__preview" <?php echo empty( $image_url ) ? 'style="display:none;"' : ''; ?>>
						<img
							class="wfs-method-logo-field__image"
							src="<?php echo esc_url( $image_url ); ?>"
							alt="<?php echo esc_attr( $image_alt ); ?>"
							style="max-width:96px;max-height:48px;width:auto;height:auto;display:block;margin-bottom:8px;border:1px solid #dcdcde;border-radius:4px;background:#fff;padding:2px;"
						/>
					</div>
					<p class="wfs-method-logo-field__actions" style="margin:0 0 5px 0;">
						<button type="button" class="button wfs-method-logo-field__select" data-choose="<?php esc_attr_e( 'Choose logo', 'woo-flexible-shipping' ); ?>" data-change="<?php esc_attr_e( 'Change logo', 'woo-flexible-shipping' ); ?>">
							<?php echo esc_html( $select_label ); ?>
						</button>
						<a
							href="#"
							class="button-link-delete wfs-method-logo-field__remove"
							style="<?php echo empty( $attachment_id ) ? 'display:none;margin-left:10px;' : 'margin-left:10px;'; ?>"
						>
							<?php echo esc_html( $remove_label ); ?>
						</a>
					</p>
					<?php if ( ! $tooltip && ! empty( $description ) ) : ?>
						<p class="description"><?php echo wp_kses_post( $description ); ?></p>
					<?php endif; ?>
				</div>
			</td>
		</tr>
		<?php
		return ob_get_clean();
	}

	/**
	 * Calculate shipping rate for a package.
	 *
	 * @param array $package Package being rated.
	 * @return void
	 */
	public function calculate_shipping( $package = array() ): void {
		$base_cost          = (float) wc_format_decimal( $this->get_option( 'cost', '0.00' ) );
		$description        = $this->get_option( 'method_description', '' );
		$prices_include_tax = $this->get_option( 'prices_include_tax', 'no' );
		$raw_rules          = $this->get_option( 'wfs_method_rules', '[]' );
		$rules              = is_array( $raw_rules ) ? $raw_rules : json_decode( $raw_rules, true );

		$coupon_settings = array(
			'wfs_coupon_handling'         => $this->get_option( 'wfs_coupon_handling', CalculationEngine::COUPON_MODE_INHERIT ),
			'wfs_ignored_coupon_patterns' => $this->get_option( 'wfs_ignored_coupon_patterns', '' ),
		);

		$engine = new CalculationEngine();
		$result = $engine->calculate( $package, is_array( $rules ) ? $rules : array(), $coupon_settings );

		$surcharge = (float) $result['surcharge'];
		$total     = max( 0.0, $base_cost + $surcharge );

		// Free Shipping Threshold Check (uses unified effective price from CalculationEngine)
		$free_shipping_threshold = trim( (string) $this->get_option( 'method_free_shipping', '' ) );
		$free_shipping_label     = trim( (string) $this->get_option( 'method_free_shipping_label', '' ) );
		$is_free_shipping        = false;

		if ( '' !== $free_shipping_threshold && is_numeric( $free_shipping_threshold ) ) {
			$effective_cart_price = isset( $result['metrics']['price'] )
				? (float) $result['metrics']['price']
				: (float) ( $package['contents_cost'] ?? 0.0 );
			if ( $effective_cart_price >= (float) $free_shipping_threshold ) {
				$is_free_shipping = true;
				$total            = 0.0;
			}
		}

		if ( $is_free_shipping ) {
			$rule_text = function_exists( '__' ) ? __( 'Free Shipping threshold reached', 'woo-flexible-shipping' ) : 'Free Shipping threshold reached';
		} elseif ( ! empty( $result['applied_rules'] ) ) {
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

		if ( ! empty( $result['metrics']['ignored_coupons'] ) && ( $result['metrics']['ignored_discount'] ?? 0.0 ) > 0.0 ) {
			$breakdown['ignored_coupons']  = array_values( $result['metrics']['ignored_coupons'] );
			$breakdown['ignored_discount'] = wc_price( (float) $result['metrics']['ignored_discount'] );
			$breakdown['effective_price']  = wc_price( (float) $result['metrics']['price'] );
		}

		$meta_data = array(
			'fs_costs'          => json_encode( $breakdown, JSON_UNESCAPED_UNICODE ),
			'_table_rate_costs' => json_encode( $breakdown, JSON_UNESCAPED_UNICODE ),
		);

		if ( ! empty( $description ) ) {
			$meta_data['method_description'] = $description;
		}

		$logo_id = absint( $this->get_option( 'method_logo_id', 0 ) );
		if ( $logo_id > 0 && function_exists( 'wp_attachment_is_image' ) && wp_attachment_is_image( $logo_id ) ) {
			$image = wp_get_attachment_image_src( $logo_id, 'thumbnail' );
			if ( is_array( $image ) && ! empty( $image[0] ) ) {
				$meta_data['method_logo_url'] = (string) $image[0];
				$meta_data['method_logo_alt'] = trim( (string) get_post_meta( $logo_id, '_wp_attachment_image_alt', true ) );
				$meta_data['method_logo_id']  = $logo_id;
			}
		}

		$rate_cost  = $total;
		$rate_taxes = array();

		if ( 'taxable' === $this->tax_status && class_exists( '\WC_Tax' ) && function_exists( 'wc_tax_enabled' ) && wc_tax_enabled() ) {
			$dest      = $package['destination'] ?? array();
			$tax_rates = \WC_Tax::get_shipping_tax_rates( $dest['country'] ?? '', $dest['state'] ?? '', $dest['postcode'] ?? '', $dest['city'] ?? '' );

			// Fallback to shop base tax rates if location-specific shipping tax rates returned empty
			if ( empty( $tax_rates ) ) {
				$tax_rates = \WC_Tax::get_base_tax_rates();
			}

			if ( ! empty( $tax_rates ) ) {
				if ( 'yes' === $prices_include_tax ) {
					// Extract tax from gross total (passing price_includes_tax = true)
					$taxes      = \WC_Tax::calc_tax( $total, $tax_rates, true );
					$rate_cost  = max( 0.0, $total - array_sum( $taxes ) );
					$rate_taxes = $taxes;
				} else {
					// Calculate tax on top of net total (passing price_includes_tax = false)
					$rate_taxes = \WC_Tax::calc_shipping_tax( $total, $tax_rates );
				}
			}
		}

		$rate_label = $this->title;
		if ( $total <= 0.0 && ! empty( $free_shipping_label ) ) {
			$rate_label .= ' (' . $free_shipping_label . ')';
		}

		$rate = array(
			'id'        => $this->get_rate_id(),
			'label'     => $rate_label,
			'cost'      => $rate_cost,
			'package'   => $package,
			'meta_data' => $meta_data,
		);

		if ( ! empty( $rate_taxes ) ) {
			$rate['taxes'] = $rate_taxes;
		} elseif ( 'none' === $this->tax_status ) {
			$rate['taxes'] = false;
		}

		$this->add_rate( $rate );
	}
}
