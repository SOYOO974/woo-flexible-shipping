<?php
namespace WooFlexibleShipping\Migration;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 1-Click Migration engine to convert Flexible Shipping PRO rules to Woo Flexible Shipping.
 */
class Migrator {

	/**
	 * Run full migration across all WooCommerce shipping zones.
	 *
	 * @return array Detailed log of migrated zones, methods, and rule counts.
	 */
	public function migrate_all(): array {
		$report = array(
			'success'          => true,
			'zones_scanned'    => 0,
			'methods_migrated' => 0,
			'details'          => array(),
		);

		if ( ! class_exists( 'WC_Shipping_Zones' ) ) {
			$report['success'] = false;
			$report['error']   = __( 'WooCommerce shipping zones class not found.', 'woo-flexible-shipping' );
			return $report;
		}

		$zones = \WC_Shipping_Zones::get_zones();

		// Add Default Zone (0).
		$default_zone               = new \WC_Shipping_Zone( 0 );
		$zones_data                 = array_values( $zones );
		$zones_data[]               = array(
			'id'               => 0,
			'zone_name'        => $default_zone->get_zone_name(),
			'shipping_methods' => $default_zone->get_shipping_methods(),
		);

		foreach ( $zones_data as $zone_data ) {
			$zone_id   = $zone_data['id'] ?? 0;
			$zone_name = $zone_data['zone_name'] ?? __( 'Unknown Zone', 'woo-flexible-shipping' );
			$methods   = $zone_data['shipping_methods'] ?? array();

			$report['zones_scanned']++;

			foreach ( $methods as $method ) {
				$instance_id = $method->instance_id ?? 0;
				if ( ! $instance_id ) {
					continue;
				}

				$option_key = 'woocommerce_flat_rate_' . $instance_id . '_settings';
				$settings   = get_option( $option_key, array() );

				if ( empty( $settings ) || ! is_array( $settings ) ) {
					continue;
				}

				$fs_enabled = $settings['fs_calculation_enabled'] ?? 'no';
				$fs_rules   = $settings['fs_method_rules'] ?? '[]';

				if ( 'yes' !== $fs_enabled && ( empty( $fs_rules ) || '[]' === $fs_rules ) ) {
					continue;
				}

				$normalized_rules = $this->convert_rules( $fs_rules );

				$settings['wfs_calculation_enabled'] = 'yes';
				$settings['wfs_method_rules']        = json_encode( $normalized_rules, JSON_UNESCAPED_UNICODE );

				update_option( $option_key, $settings );

				$report['methods_migrated']++;
				$report['details'][] = array(
					'zone_id'     => $zone_id,
					'zone_name'   => $zone_name,
					'instance_id' => $instance_id,
					'title'       => $method->title ?? ( $settings['title'] ?? 'Flat Rate' ),
					'rules_count' => count( $normalized_rules ),
				);
			}
		}

		return $report;
	}

	/**
	 * Convert Octolize Flexible Shipping PRO rules to normalized WFS format.
	 *
	 * @param mixed $raw_rules JSON string or raw array.
	 * @return array Normalized rules array.
	 */
	public function convert_rules( $raw_rules ): array {
		$rules = is_array( $raw_rules ) ? $raw_rules : json_decode( (string) $raw_rules, true );

		if ( ! is_array( $rules ) ) {
			return array();
		}

		$converted = array();

		foreach ( $rules as $rule ) {
			if ( ! is_array( $rule ) ) {
				continue;
			}

			// Extract condition, min, max.
			$condition = 'item';
			$min       = '';
			$max       = '';

			if ( ! empty( $rule['conditions'] ) && is_array( $rule['conditions'] ) ) {
				$first_cond = reset( $rule['conditions'] );
				$condition  = $first_cond['condition_id'] ?? 'item';
				$min        = ( isset( $first_cond['min'] ) && '' !== $first_cond['min'] ) ? (float) $first_cond['min'] : '';
				$max        = ( isset( $first_cond['max'] ) && '' !== $first_cond['max'] ) ? (float) $first_cond['max'] : '';
			} elseif ( isset( $rule['condition'] ) ) {
				$condition = $rule['condition'];
				$min       = ( isset( $rule['min'] ) && '' !== $rule['min'] ) ? (float) $rule['min'] : '';
				$max       = ( isset( $rule['max'] ) && '' !== $rule['max'] ) ? (float) $rule['max'] : '';
			}

			$cost_per_order  = (float) ( $rule['cost_per_order'] ?? 0.0 );
			$additional_cost = (float) ( $rule['additional_cost'] ?? 0.0 );
			$per_value       = (float) ( $rule['per_value'] ?? 1.0 );

			if ( empty( $additional_cost ) && ! empty( $rule['additional_costs'] ) && is_array( $rule['additional_costs'] ) ) {
				$first_add       = reset( $rule['additional_costs'] );
				$additional_cost = (float) ( $first_add['additional_cost'] ?? 0.0 );
				$per_value       = (float) ( $first_add['per_value'] ?? 1.0 );
			}

			$converted[] = array(
				'condition'       => strtolower( $condition ),
				'min'             => $min,
				'max'             => $max,
				'cost_per_order'  => $cost_per_order,
				'additional_cost' => $additional_cost,
				'per_value'       => $per_value > 0 ? $per_value : 1.0,
			);
		}

		return $converted;
	}
}
