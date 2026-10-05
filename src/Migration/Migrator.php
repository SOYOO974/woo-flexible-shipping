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

			$zone_obj = new \WC_Shipping_Zone( $zone_id );

			foreach ( $methods as $method ) {
				$instance_id = $method->instance_id ?? 0;
				$method_id   = $method->id ?? ( $method->method_id ?? '' );
				if ( ! $instance_id ) {
					continue;
				}

				if ( 'soyoo_table_rate' === $method_id ) {
					continue;
				}

				$possible_keys = array(
					'woocommerce_' . $method_id . '_' . $instance_id . '_settings',
					'woocommerce_flat_rate_' . $instance_id . '_settings',
					'woocommerce_flexible_shipping_single_' . $instance_id . '_settings',
					'woocommerce_flexible_shipping_' . $instance_id . '_settings',
				);

				$settings  = array();
				$found_key = '';

				foreach ( $possible_keys as $key ) {
					$opt = get_option( $key, array() );
					if ( ! empty( $opt ) && is_array( $opt ) ) {
						$settings  = $opt;
						$found_key = $key;
						break;
					}
				}

				if ( empty( $settings ) && isset( $method->instance_settings ) && is_array( $method->instance_settings ) ) {
					$settings  = $method->instance_settings;
					$found_key = 'woocommerce_' . ( $method_id ? $method_id : 'flat_rate' ) . '_' . $instance_id . '_settings';
				}

				if ( empty( $settings ) ) {
					continue;
				}

				$raw_rules = $settings['wfs_method_rules'] ?? ( $settings['fs_method_rules'] ?? ( $settings['method_rules'] ?? ( $settings['rules'] ?? '' ) ) );

				if ( empty( $raw_rules ) || '[]' === $raw_rules ) {
					continue;
				}

				$normalized_rules = $this->convert_rules( $raw_rules );
				if ( empty( $normalized_rules ) ) {
					continue;
				}

				$method_title       = $method->title ?? ( $settings['title'] ?? ( $settings['method_title'] ?? 'Table Rate' ) );
				$method_description = $settings['method_description'] ?? ( $settings['description'] ?? '' );

				if ( 'flexible_shipping_single' === $method_id || 'flexible_shipping' === $method_id ) {
					// Query old method order in zone database table.
					global $wpdb;
					$old_order = 0;
					if ( isset( $wpdb->prefix ) ) {
						$old_order = (int) $wpdb->get_var( $wpdb->prepare(
							"SELECT method_order FROM {$wpdb->prefix}woocommerce_shipping_zone_methods WHERE instance_id = %d",
							$instance_id
						) );
					}

					// Standalone Octolize method: Add a new native Soyoo Table Rate method to the shipping zone.
					$new_instance_id = $zone_obj->add_shipping_method( 'soyoo_table_rate' );
					if ( $new_instance_id ) {
						$new_option_key = 'woocommerce_soyoo_table_rate_' . $new_instance_id . '_settings';
						$new_settings   = array(
							'title'              => $method_title,
							'method_description' => $method_description,
							'tax_status'         => $settings['tax_status'] ?? ( $settings['tax_heading'] ?? 'taxable' ),
							'cost'               => '0.00',
							'wfs_method_rules'   => json_encode( $normalized_rules, JSON_UNESCAPED_UNICODE ),
						);
						update_option( $new_option_key, $new_settings );

						// Position the new method directly below the old method.
						if ( isset( $wpdb->prefix ) && $old_order > 0 ) {
							$table_name = $wpdb->prefix . 'woocommerce_shipping_zone_methods';
							$wpdb->query( $wpdb->prepare(
								"UPDATE {$table_name} SET method_order = method_order + 1 WHERE zone_id = %d AND method_order > %d",
								$zone_id,
								$old_order
							) );
							$wpdb->update(
								$table_name,
								array( 'method_order' => $old_order + 1 ),
								array( 'instance_id' => $new_instance_id )
							);
						}
					}

					// Disable original Octolize method in WooCommerce shipping zone methods database table.
					if ( isset( $wpdb->prefix ) ) {
						$wpdb->update(
							$wpdb->prefix . 'woocommerce_shipping_zone_methods',
							array( 'is_enabled' => 0 ),
							array( 'instance_id' => $instance_id )
						);
					}

					// Disable original Octolize method in options and append [OLD - Octolize] tag.
					$settings['enabled']                 = 'no';
					$settings['wfs_calculation_enabled'] = 'no';
					$old_prefix                          = '[OLD - Octolize] ';
					if ( isset( $settings['title'] ) && strpos( $settings['title'], $old_prefix ) === false ) {
						$settings['title'] = $old_prefix . $settings['title'];
					}
					if ( isset( $settings['method_title'] ) && strpos( $settings['method_title'], $old_prefix ) === false ) {
						$settings['method_title'] = $old_prefix . $settings['method_title'];
					}

					if ( $found_key ) {
						update_option( $found_key, $settings );
					}
				} else {
					// Flat Rate extension (e.g. conforama.re)
					$settings['wfs_calculation_enabled'] = 'yes';
					$settings['wfs_method_rules']        = json_encode( $normalized_rules, JSON_UNESCAPED_UNICODE );

					if ( $found_key ) {
						update_option( $found_key, $settings );
					}
				}

				$report['methods_migrated']++;
				$report['details'][] = array(
					'zone_id'     => $zone_id,
					'zone_name'   => $zone_name,
					'instance_id' => $instance_id,
					'method_id'   => $method_id,
					'title'       => $method_title,
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
