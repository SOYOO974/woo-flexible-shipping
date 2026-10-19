<?php
namespace WooFlexibleShipping\Shipping;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Calculation Engine for Woo Flexible Shipping rules matrix.
 */
class CalculationEngine {

	const COUPON_MODE_INHERIT         = 'inherit';
	const COUPON_MODE_APPLY_ALL       = 'apply_all';
	const COUPON_MODE_IGNORE_PATTERNS = 'ignore_patterns';
	const COUPON_MODE_IGNORE_ALL      = 'ignore_all';

	const OPTION_GLOBAL_COUPON_MODE     = 'wfs_global_coupon_handling';
	const OPTION_GLOBAL_COUPON_PATTERNS = 'wfs_global_ignored_coupon_patterns';

	/**
	 * Calculate shipping surcharges for a package given a rule matrix.
	 *
	 * @param array $package WooCommerce shipping package.
	 * @param array $rules Array of configured rule definitions.
	 * @param array $coupon_settings Optional coupon handling settings from method instance.
	 * @return array Result containing total surcharge, metrics, and applied rules breakdown.
	 */
	public function calculate( array $package, array $rules, array $coupon_settings = array() ): array {
		$metrics = $this->extract_package_metrics( $package, $coupon_settings );

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

			$condition = strtolower( trim( (string) ( $rule['condition'] ?? $rule['condition_id'] ?? 'item' ) ) );
			if ( 'value' === $condition || 'subtotal' === $condition ) {
				$condition = 'price';
			}
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
	 * @param array $coupon_settings Optional coupon handling settings.
	 * @return array Metrics array including effective price and coupon neutralization details.
	 */
	public function extract_package_metrics( array $package, array $coupon_settings = array() ): array {
		$item_count    = 0.0;
		$weight        = 0.0;
		$line_total    = 0.0;
		$line_subtotal = 0.0;

		if ( ! empty( $package['contents'] ) && is_array( $package['contents'] ) ) {
			foreach ( $package['contents'] as $item ) {
				$qty = (float) ( $item['quantity'] ?? 1 );
				$item_count += $qty;

				if ( isset( $item['data'] ) && is_object( $item['data'] ) && method_exists( $item['data'], 'get_weight' ) ) {
					$item_weight = (float) $item['data']->get_weight();
					$weight     += ( $item_weight * $qty );
				}

				$item_total    = isset( $item['line_total'] ) ? (float) $item['line_total'] : ( isset( $item['line_subtotal'] ) ? (float) $item['line_subtotal'] : 0.0 );
				$item_subtotal = isset( $item['line_subtotal'] ) ? (float) $item['line_subtotal'] : $item_total;

				$line_total    += $item_total;
				$line_subtotal += $item_subtotal;
			}
		}

		// Fallback to contents_cost if price wasn't computed item-by-item.
		if ( 0.0 === $line_total && isset( $package['contents_cost'] ) ) {
			$line_total = (float) $package['contents_cost'];
		}

		if ( $line_subtotal < $line_total ) {
			$line_subtotal = $line_total;
		}

		$resolved         = $this->resolve_coupon_settings( $coupon_settings );
		$mode             = $resolved['mode'];
		$patterns         = $resolved['patterns'];
		$ignored_discount = 0.0;
		$ignored_coupons  = array();

		$applied_coupons = array();
		if ( ! empty( $package['applied_coupons'] ) && is_array( $package['applied_coupons'] ) ) {
			$applied_coupons = $package['applied_coupons'];
		} elseif ( function_exists( 'WC' ) && isset( WC()->cart ) && is_object( WC()->cart ) && method_exists( WC()->cart, 'get_applied_coupons' ) ) {
			$applied_coupons = WC()->cart->get_applied_coupons();
		}

		if ( self::COUPON_MODE_IGNORE_ALL === $mode ) {
			foreach ( $applied_coupons as $code ) {
				$code_str = trim( (string) $code );
				if ( '' !== $code_str ) {
					$ignored_coupons[] = $code_str;
				}
			}

			if ( $line_subtotal > $line_total ) {
				$ignored_discount = $line_subtotal - $line_total;
			} elseif ( ! empty( $ignored_coupons ) ) {
				foreach ( $ignored_coupons as $ignored_code ) {
					$ignored_discount += $this->get_single_coupon_discount( $ignored_code, $package );
				}
			}
		} elseif ( self::COUPON_MODE_IGNORE_PATTERNS === $mode && ! empty( $applied_coupons ) ) {
			foreach ( $applied_coupons as $code ) {
				$code_str = trim( (string) $code );
				if ( '' !== $code_str && $this->should_ignore_coupon( $code_str, $patterns, $package, $resolved ) ) {
					$ignored_coupons[] = $code_str;
				}
			}

			if ( ! empty( $ignored_coupons ) ) {
				$all_coupons_ignored = count( $ignored_coupons ) === count( $applied_coupons );

				if ( $all_coupons_ignored && $line_subtotal > $line_total ) {
					$ignored_discount = $line_subtotal - $line_total;
				} else {
					foreach ( $ignored_coupons as $ignored_code ) {
						$ignored_discount += $this->get_single_coupon_discount( $ignored_code, $package );
					}
					if ( $line_subtotal > $line_total ) {
						$ignored_discount = min( $ignored_discount, $line_subtotal - $line_total );
					}
				}
			}
		}

		$ignored_discount = max( 0.0, round( $ignored_discount, 2 ) );
		$effective_price  = round( $line_total + $ignored_discount, 2 );

		return array(
			'item'                    => $item_count,
			'weight'                  => $weight,
			'price'                   => $effective_price,
			'value'                   => $effective_price,
			'subtotal'                => $effective_price,
			'raw_price'               => round( $line_total, 2 ),
			'subtotal_before_coupons' => round( $line_subtotal, 2 ),
			'ignored_discount'        => $ignored_discount,
			'ignored_coupons'         => $ignored_coupons,
		);
	}

	/**
	 * Resolve active coupon handling mode and pattern list from instance settings or global fallback.
	 *
	 * @param array $instance_settings Shipping method instance settings.
	 * @return array ['mode' => string, 'patterns' => string[]]
	 */
	public function resolve_coupon_settings( array $instance_settings = array() ): array {
		$mode         = isset( $instance_settings['wfs_coupon_handling'] ) ? trim( (string) $instance_settings['wfs_coupon_handling'] ) : self::COUPON_MODE_INHERIT;
		$patterns_raw = isset( $instance_settings['wfs_ignored_coupon_patterns'] ) ? (string) $instance_settings['wfs_ignored_coupon_patterns'] : '';

		if ( '' === $mode || self::COUPON_MODE_INHERIT === $mode ) {
			$mode         = function_exists( 'get_option' ) ? (string) get_option( self::OPTION_GLOBAL_COUPON_MODE, self::COUPON_MODE_APPLY_ALL ) : self::COUPON_MODE_APPLY_ALL;
			$patterns_raw = function_exists( 'get_option' ) ? (string) get_option( self::OPTION_GLOBAL_COUPON_PATTERNS, '' ) : '';
		}

		$valid_modes = array(
			self::COUPON_MODE_APPLY_ALL,
			self::COUPON_MODE_IGNORE_PATTERNS,
			self::COUPON_MODE_IGNORE_ALL,
		);

		if ( ! in_array( $mode, $valid_modes, true ) ) {
			$mode = self::COUPON_MODE_APPLY_ALL;
		}

		return array(
			'mode'     => $mode,
			'patterns' => self::parse_coupon_patterns( $patterns_raw ),
		);
	}

	/**
	 * Parse comma/semicolon/newline-separated coupon patterns into a clean array.
	 *
	 * @param string $raw_patterns Raw pattern string.
	 * @return array<int, string>
	 */
	public static function parse_coupon_patterns( string $raw_patterns ): array {
		if ( '' === trim( $raw_patterns ) ) {
			return array();
		}

		$parts    = preg_split( '/[,;\r\n]+/', $raw_patterns );
		$patterns = array();

		if ( is_array( $parts ) ) {
			foreach ( $parts as $part ) {
				$trimmed = trim( (string) $part );
				if ( '' !== $trimmed ) {
					$patterns[] = $trimmed;
				}
			}
		}

		return array_values( array_unique( $patterns ) );
	}

	/**
	 * Check whether a specific coupon code should be ignored for shipping calculation.
	 *
	 * @param string $coupon_code Coupon code.
	 * @param array  $patterns List of substring patterns.
	 * @param array  $package Shipping package.
	 * @param array  $resolved_settings Resolved coupon settings.
	 * @return bool True if the coupon discount should be neutralized.
	 */
	private function should_ignore_coupon( string $coupon_code, array $patterns, array $package, array $resolved_settings ): bool {
		$matched = false;

		foreach ( $patterns as $pattern ) {
			if ( '' !== $pattern && false !== stripos( $coupon_code, $pattern ) ) {
				$matched = true;
				break;
			}
		}

		if ( function_exists( 'apply_filters' ) ) {
			$matched = (bool) apply_filters( 'wfs_ignore_coupon_for_shipping', $matched, $coupon_code, $package, $resolved_settings );
		}

		return $matched;
	}

	/**
	 * Retrieve the discount amount for a single coupon code from package or WC_Cart.
	 *
	 * @param string $coupon_code Coupon code.
	 * @param array  $package Shipping package.
	 * @return float Discount amount (ex-tax basis matching package line_total).
	 */
	private function get_single_coupon_discount( string $coupon_code, array $package ): float {
		if ( isset( $package['coupon_discounts'] ) && is_array( $package['coupon_discounts'] ) ) {
			foreach ( $package['coupon_discounts'] as $code => $amount ) {
				if ( 0 === strcasecmp( (string) $code, $coupon_code ) ) {
					return max( 0.0, (float) $amount );
				}
			}
		}

		if ( function_exists( 'WC' ) && isset( WC()->cart ) && is_object( WC()->cart ) && method_exists( WC()->cart, 'get_coupon_discount_amount' ) ) {
			return max( 0.0, (float) WC()->cart->get_coupon_discount_amount( $coupon_code, true ) );
		}

		return 0.0;
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
