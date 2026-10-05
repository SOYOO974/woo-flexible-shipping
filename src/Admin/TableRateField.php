<?php
namespace WooFlexibleShipping\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Custom WooCommerce Settings API Field for rendering the Table Rate Rules matrix editor on dedicated pages.
 */
class TableRateField {

	const FIELD_TYPE = 'wfs_table_rate_matrix';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_filter( 'woocommerce_generate_' . self::FIELD_TYPE . '_html', array( static::class, 'generate_field_html' ), 10, 4 );
	}

	/**
	 * Render the rule table HTML for WooCommerce shipping method settings.
	 *
	 * @param string $html Current HTML.
	 * @param string $key Field key.
	 * @param array $data Field configuration array.
	 * @param \WC_Shipping_Method $shipping_method Shipping method instance.
	 * @return string Generated HTML.
	 */
	public static function generate_field_html( string $html, string $key, array $data, $shipping_method ): string {
		$field_name = $shipping_method->get_field_key( $key );
		$raw_value  = $shipping_method->get_option( $key, '[]' );

		if ( is_array( $raw_value ) ) {
			$json_value = json_encode( $raw_value, JSON_UNESCAPED_UNICODE );
		} else {
			$json_value = (string) $raw_value;
		}

		if ( empty( $json_value ) || 'null' === $json_value ) {
			$json_value = '[]';
		}

		$title       = $data['title'] ?? __( 'Table Rate Rules Matrix', 'woo-flexible-shipping' );
		$description = $data['description'] ?? __( 'Define tiered rules based on item count, package weight, or cart subtotal.', 'woo-flexible-shipping' );

		ob_start();
		?>
		<tr valign="top" class="wfs-table-rate-matrix-row">
			<th scope="row" class="titledesc" style="width: 220px !important; min-width: 220px !important; max-width: 220px !important;">
				<label for="<?php echo esc_attr( $field_name ); ?>"><?php echo esc_html( $title ); ?></label>
				<?php if ( ! empty( $description ) ) : ?>
					<br/><span class="description" style="display: inline-block; margin-top: 4px;"><?php echo esc_html( $description ); ?></span>
				<?php endif; ?>
			</th>
			<td class="forminp" style="padding-top: 10px;">
				<input type="hidden" id="<?php echo esc_attr( $field_name ); ?>" name="<?php echo esc_attr( $field_name ); ?>" value="<?php echo esc_attr( $json_value ); ?>" class="wfs-rules-json-input" />
				
				<div class="wfs-table-editor-container" data-field-id="<?php echo esc_attr( $field_name ); ?>">
					<div class="wfs-table-header-bar" style="margin-bottom: 10px; display: flex; align-items: center; justify-content: space-between;">
						<span class="wfs-rules-badge">
							<span class="wfs-rule-count">0</span> <?php esc_html_e( 'Active Rules Configured', 'woo-flexible-shipping' ); ?>
						</span>
					</div>

					<div class="wfs-table-scroll-wrapper">
						<table class="widefat striped wfs-rules-table">
							<thead>
								<tr>
									<th style="width: 240px; white-space: nowrap;"><?php esc_html_e( 'Based On', 'woo-flexible-shipping' ); ?></th>
									<th class="th-align-center" style="width: 85px; white-space: nowrap;"><?php esc_html_e( 'Min', 'woo-flexible-shipping' ); ?></th>
									<th class="th-align-center" style="width: 85px; white-space: nowrap;"><?php esc_html_e( 'Max', 'woo-flexible-shipping' ); ?></th>
									<th class="th-align-center" style="width: 110px; white-space: nowrap;"><?php esc_html_e( 'Base Surcharge (€)', 'woo-flexible-shipping' ); ?></th>
									<th class="th-align-center" style="width: 100px; white-space: nowrap;"><?php esc_html_e( 'Add. Cost (€)', 'woo-flexible-shipping' ); ?></th>
									<th class="th-align-center" style="width: 85px; white-space: nowrap;"><?php esc_html_e( 'Per Unit', 'woo-flexible-shipping' ); ?></th>
									<th class="th-align-center" style="width: 45px; white-space: nowrap;"><?php esc_html_e( 'Action', 'woo-flexible-shipping' ); ?></th>
								</tr>
							</thead>
							<tbody class="wfs-rules-tbody">
								<!-- Dynamic rows populated by admin-table-editor.js -->
							</tbody>
						</table>
					</div>

					<div style="margin-top: 12px;">
						<button type="button" class="button button-primary wfs-add-rule-btn">
							+ <?php esc_html_e( 'Add Shipping Rule', 'woo-flexible-shipping' ); ?>
						</button>
					</div>
				</div>
			</td>
		</tr>
		<?php
		return ob_get_clean();
	}

	/**
	 * Sanitize rule values before saving.
	 *
	 * @param mixed $value Raw input value.
	 * @return string Sanitized JSON string.
	 */
	public static function sanitize_rules( $value ): string {
		if ( is_string( $value ) ) {
			$decoded = json_decode( stripslashes( $value ), true );
		} else {
			$decoded = $value;
		}

		if ( ! is_array( $decoded ) ) {
			return '[]';
		}

		$sanitized = array();
		foreach ( $decoded as $rule ) {
			if ( ! is_array( $rule ) ) {
				continue;
			}

			$condition       = sanitize_text_field( $rule['condition'] ?? $rule['condition_id'] ?? 'item' );
			$min             = ( isset( $rule['min'] ) && '' !== $rule['min'] ) ? (float) $rule['min'] : '';
			$max             = ( isset( $rule['max'] ) && '' !== $rule['max'] ) ? (float) $rule['max'] : '';
			$cost_per_order  = (float) ( $rule['cost_per_order'] ?? 0 );
			$additional_cost = (float) ( $rule['additional_cost'] ?? 0 );
			$per_value       = (float) ( $rule['per_value'] ?? 1 );

			$sanitized[] = array(
				'condition'       => $condition,
				'min'             => $min,
				'max'             => $max,
				'cost_per_order'  => $cost_per_order,
				'additional_cost' => $additional_cost,
				'per_value'       => $per_value,
			);
		}

		return json_encode( $sanitized, JSON_UNESCAPED_UNICODE );
	}
}
