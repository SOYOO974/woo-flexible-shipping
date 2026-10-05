<?php
namespace WooFlexibleShipping\Shipping;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Calculation Engine for Woo Flexible Shipping rules matrix.
 */
class CalculationEngine {

	/**
	 * Calculate shipping surcharges for a package given a rule matrix.
	 *
	 * @param array $package WooCommerce shipping package.
	 * @param array $rules Array of configured rule definitions.
	 * @return array Result containing total surcharge, metrics, and applied rules breakdown.
	 */
	public function calculate( array $package, array $rules ): array {
		$metrics = $this->extract_package_metrics( $package );

		$total_surcharge = 0.0;
		$applied_rules   = array();

		if ( empty( $rules ) || ! is_array( $rules ) ) {
			return array(
				'surcharge'     => 0.0,
				'applied_rules' => array(),
				'metrics'       => $metrics,
			);
		}

		foreach ( $rules as $rule ) {
			if ( ! is_array( $rule ) ) {
				continue;
			}

			$condition = strtolower( trim( $rule['condition'] ?? $rule['condition_id'] ?? 'item' ) );
			$val       = $metrics[ $condition ] ?? 0.0;

			$has_min = isset( $rule['min'] ) && $rule['min'] !== '' && $rule['min'] !== null;
			$has_max = isset( $rule['max'] ) && $rule['max'] !== '' && $rule['max'] !== null;

			$min_val = $has_min ? (float) $rule['min'] : 0.0;
			$max_val = $has_max ? (float) $rule['max'] : null;

			// Boundary checks (inclusive).
			if ( $has_min && $val < $min_val ) {
				continue;
			}

			if ( $has_max && $max_val !== null && $val > $max_val ) {
				continue;
			}

			// Rule matched: calculate base cost surcharge and incremental cost.
			$base_cost = (float) ( $rule['cost_per_order'] ?? 0.0 );
			$add_cost  = (float) ( $rule['additional_cost'] ?? 0.0 );
			$per_val   = (float) ( $rule['per_value'] ?? 0.0 );

			// Check nested Octolize additional_costs if present.
			if ( empty( $add_cost ) && ! empty( $rule['additional_costs'] ) && is_array( $rule['additional_costs'] ) ) {
				$first_add = reset( $rule['additional_costs'] );
				$add_cost  = (float) ( $first_add['additional_cost'] ?? 0.0 );
				$per_val   = (float) ( $first_add['per_value'] ?? 1.0 );
			}

			$incremental_cost = 0.0;
			if ( $add_cost != 0.0 && $per_val > 0.0 ) {
				$steps            = (int) ceil( $val / $per_val );
				$incremental_cost = $steps * $add_cost;
			}

			$rule_surcharge  = $base_cost + $incremental_cost;
			$total_surcharge += $rule_surcharge;

			$applied_rules[] = array(
				'condition'        => $condition,
				'val'              => $val,
				'min'              => $has_min ? $min_val : null,
				'max'              => $has_max ? $max_val : null,
				'cost_per_order'   => $base_cost,
				'additional_cost'  => $add_cost,
				'per_value'        => $per_val,
				'incremental_cost' => $incremental_cost,
				'rule_surcharge'   => $rule_surcharge,
				'label'            => $this->format_rule_label( $condition, $min_val, $max_val, $has_min, $has_max, $rule_surcharge ),
			);
		}

		return array(
			'surcharge'     => $total_surcharge,
			'applied_rules' => $applied_rules,
			'metrics'       => $metrics,
		);
	}

	/**
	 * Extract item count, weight, and subtotal metrics from WooCommerce package.
	 *
	 * @param array $package Shipping package.
	 * @return array ['item' => float, 'weight' => float, 'price' => float]
	 */
	public function extract_package_metrics( array $package ): array {
		$item_count = 0.0;
		$weight     = 0.0;
		$price      = 0.0;

		if ( ! empty( $package['contents'] ) && is_array( $package['contents'] ) ) {
			foreach ( $package['contents'] as $item ) {
				$qty = (float) ( $item['quantity'] ?? 1 );
				$item_count += $qty;

				if ( isset( $item['data'] ) && is_object( $item['data'] ) && method_exists( $item['data'], 'get_weight' ) ) {
					$item_weight = (float) $item['data']->get_weight();
					$weight     += ( $item_weight * $qty );
				}

				if ( isset( $item['line_total'] ) ) {
					$price += (float) $item['line_total'];
				} elseif ( isset( $item['line_subtotal'] ) ) {
					$price += (float) $item['line_subtotal'];
				}
			}
		}

		// Fallback to contents_cost if price wasn't computed item-by-item.
		if ( $price === 0.0 && isset( $package['contents_cost'] ) ) {
			$price = (float) $package['contents_cost'];
		}

		return array(
			'item'   => $item_count,
			'weight' => $weight,
			'price'  => $price,
		);
	}

	/**
	 * Format a human-readable rule label for breakdown metadata.
	 *
	 * @param string $condition Condition key.
	 * @param float $min Min boundary.
	 * @param float|null $max Max boundary.
	 * @param bool $has_min Whether min boundary was set.
	 * @param bool $has_max Whether max boundary was set.
	 * @param float $surcharge Rule surcharge amount.
	 * @return string Formatted label.
	 */
	private function format_rule_label( string $condition, float $min, ?float $max, bool $has_min, bool $has_max, float $surcharge ): string {
		$translate   = function( $str ) {
			return function_exists( '__' ) ? __( $str, 'woo-flexible-shipping' ) : $str;
		};
		$cond_labels = array(
			'item'   => $translate( 'Item Count' ),
			'weight' => $translate( 'Weight' ),
			'price'  => $translate( 'Price' ),
		);
		$cond_name = $cond_labels[ $condition ] ?? ucfirst( $condition );

		if ( $has_min && $has_max ) {
			$range = sprintf( '%s: %s - %s', $cond_name, $min, $max );
		} elseif ( $has_min ) {
			$range = sprintf( '%s >= %s', $cond_name, $min );
		} elseif ( $has_max ) {
			$range = sprintf( '%s <= %s', $cond_name, $max );
		} else {
			$range = sprintf( '%s (All)', $cond_name );
		}

		$formatted_cost = ( $surcharge >= 0 ? '+' : '' ) . number_format( $surcharge, 2, '.', '' );
		return sprintf( '%s (%s)', $range, $formatted_cost );
	}
}
