<?php
namespace WooFlexibleShipping\Migration;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WooFlexibleShipping\Shipping\CalculationEngine;

/**
 * Admin Settings & Migration Page in WooCommerce Shipping Settings.
 */
class AdminMigrationPage {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_filter( 'woocommerce_get_sections_shipping', array( $this, 'add_shipping_section' ) );
		add_action( 'woocommerce_settings_shipping', array( $this, 'render_migration_page' ) );
		add_filter( 'admin_body_class', array( $this, 'add_admin_body_class' ) );
	}

	/**
	 * Add body class when on wfs_migration section to cleanly hide global submit button via CSS.
	 *
	 * @param string $classes Admin body classes.
	 * @return string Modified classes.
	 */
	public function add_admin_body_class( string $classes ): string {
		if ( isset( $_GET['page'] ) && 'wc-settings' === $_GET['page'] && isset( $_GET['tab'] ) && 'shipping' === $_GET['tab'] && isset( $_GET['section'] ) && 'wfs_migration' === $_GET['section'] ) {
			$classes .= ' wfs-migration-tab-active';
		}
		return $classes;
	}

	/**
	 * Add "Table Rate Migration" section to WooCommerce Shipping settings.
	 *
	 * @param array $sections Existing sections.
	 * @return array Modified sections.
	 */
	public function add_shipping_section( array $sections ): array {
		$sections['wfs_migration'] = __( 'Table Rate Migration', 'woo-flexible-shipping' );
		return $sections;
	}

	/**
	 * Render Global Coupon Settings & 1-Click migration tab content.
	 *
	 * @return void
	 */
	public function render_migration_page(): void {
		global $current_section;

		if ( 'wfs_migration' !== $current_section ) {
			return;
		}

		$report         = null;
		$settings_saved = false;

		if ( isset( $_POST['wfs_save_global_settings'] ) && check_admin_referer( 'wfs_migrate_action', 'wfs_migrate_nonce' ) ) {
			if ( ! function_exists( 'current_user_can' ) || current_user_can( 'manage_woocommerce' ) ) {
				$raw_mode     = isset( $_POST['wfs_global_coupon_handling'] ) ? sanitize_text_field( wp_unslash( $_POST['wfs_global_coupon_handling'] ) ) : CalculationEngine::COUPON_MODE_APPLY_ALL;
				$raw_patterns = isset( $_POST['wfs_global_ignored_coupon_patterns'] ) ? sanitize_text_field( wp_unslash( $_POST['wfs_global_ignored_coupon_patterns'] ) ) : '';

				$valid_modes = array(
					CalculationEngine::COUPON_MODE_APPLY_ALL,
					CalculationEngine::COUPON_MODE_IGNORE_PATTERNS,
					CalculationEngine::COUPON_MODE_IGNORE_ALL,
				);
				if ( ! in_array( $raw_mode, $valid_modes, true ) ) {
					$raw_mode = CalculationEngine::COUPON_MODE_APPLY_ALL;
				}

				update_option( CalculationEngine::OPTION_GLOBAL_COUPON_MODE, $raw_mode );
				update_option( CalculationEngine::OPTION_GLOBAL_COUPON_PATTERNS, $raw_patterns );
				$settings_saved = true;
			}
		}

		if ( isset( $_POST['wfs_run_migration'] ) && check_admin_referer( 'wfs_migrate_action', 'wfs_migrate_nonce' ) ) {
			if ( ! function_exists( 'current_user_can' ) || current_user_can( 'manage_woocommerce' ) ) {
				$migrator = new Migrator();
				$report   = $migrator->migrate_all();
			}
		}

		$global_coupon_mode     = (string) get_option( CalculationEngine::OPTION_GLOBAL_COUPON_MODE, CalculationEngine::COUPON_MODE_APPLY_ALL );
		$global_coupon_patterns = (string) get_option( CalculationEngine::OPTION_GLOBAL_COUPON_PATTERNS, '' );

		?>
		<style>
			/* Suppress global floating bottom save button on migration tab */
			p.submit { display: none !important; }
		</style>

		<div class="wrap wfs-migration-wrapper" style="max-width: 920px; margin-top: 15px;">

			<form method="post" action="">
				<?php wp_nonce_field( 'wfs_migrate_action', 'wfs_migrate_nonce' ); ?>

				<?php if ( $settings_saved ) : ?>
					<div id="wfs-global-settings-saved-card" class="card" style="max-width: 100%; padding: 16px 22px; background: #f6fbf7; border: 1px solid #c3e6cb; border-left: 4px solid #155724; border-radius: 6px; margin-bottom: 20px;">
						<p style="margin: 0; font-size: 14px; font-weight: 600; color: #155724;">
							<?php esc_html_e( 'Global coupon & loyalty voucher rules saved successfully.', 'woo-flexible-shipping' ); ?>
						</p>
					</div>
				<?php endif; ?>

				<!-- Global Coupon & Loyalty Vouchers Settings Card -->
				<div class="card" style="max-width: 100%; padding: 28px; background: #fff; border: 1px solid #c3c4c7; border-radius: 8px; margin-bottom: 25px;">
					<h2 style="margin: 0 0 8px 0; font-size: 18px; font-weight: 700; color: #1d2327;">
						<?php esc_html_e( 'Global Coupon & Loyalty Voucher Rules', 'woo-flexible-shipping' ); ?>
					</h2>
					<p style="margin: 0 0 20px 0; font-size: 14px; line-height: 1.5; color: #50575e;">
						<?php esc_html_e( 'Define how promo codes and loyalty vouchers affect the cart amount used to evaluate shipping tiers and free shipping thresholds across all shipping methods (unless overridden inside a specific shipping method).', 'woo-flexible-shipping' ); ?>
					</p>

					<table class="form-table" role="presentation" style="margin-top: 0;">
						<tbody>
							<tr valign="top">
								<th scope="row" style="width: 240px; padding-left: 0;">
									<label for="wfs_global_coupon_handling"><?php esc_html_e( 'Coupons & Loyalty Vouchers', 'woo-flexible-shipping' ); ?></label>
								</th>
								<td class="forminp">
									<select name="wfs_global_coupon_handling" id="wfs_global_coupon_handling" class="wfs-coupon-handling-select" style="min-width: 380px; max-width: 100%;">
										<option value="<?php echo esc_attr( CalculationEngine::COUPON_MODE_APPLY_ALL ); ?>" <?php selected( $global_coupon_mode, CalculationEngine::COUPON_MODE_APPLY_ALL ); ?>>
											<?php esc_html_e( 'Deduct all coupons (net discounted amount)', 'woo-flexible-shipping' ); ?>
										</option>
										<option value="<?php echo esc_attr( CalculationEngine::COUPON_MODE_IGNORE_PATTERNS ); ?>" <?php selected( $global_coupon_mode, CalculationEngine::COUPON_MODE_IGNORE_PATTERNS ); ?>>
											<?php esc_html_e( 'Ignore specific coupons matching text patterns (e.g. loyalty)', 'woo-flexible-shipping' ); ?>
										</option>
										<option value="<?php echo esc_attr( CalculationEngine::COUPON_MODE_IGNORE_ALL ); ?>" <?php selected( $global_coupon_mode, CalculationEngine::COUPON_MODE_IGNORE_ALL ); ?>>
											<?php esc_html_e( 'Ignore all coupons (pre-discount subtotal)', 'woo-flexible-shipping' ); ?>
										</option>
									</select>
								</td>
							</tr>
							<tr valign="top" id="wfs_global_ignored_coupon_patterns_row" <?php echo ( CalculationEngine::COUPON_MODE_IGNORE_PATTERNS !== $global_coupon_mode ) ? 'style="display:none;"' : ''; ?>>
								<th scope="row" style="width: 240px; padding-left: 0;">
									<label for="wfs_global_ignored_coupon_patterns"><?php esc_html_e( 'Ignored Coupon Text Patterns', 'woo-flexible-shipping' ); ?></label>
								</th>
								<td class="forminp">
									<input
										type="text"
										name="wfs_global_ignored_coupon_patterns"
										id="wfs_global_ignored_coupon_patterns"
										class="regular-text wfs-ignored-coupon-patterns-input"
										style="min-width: 380px; max-width: 100%;"
										placeholder="<?php esc_attr_e( 'e.g. 5EUR, 10EUR, FID', 'woo-flexible-shipping' ); ?>"
										value="<?php echo esc_attr( $global_coupon_patterns ); ?>"
									/>
									<p class="description" style="margin-top: 6px; max-width: 580px;">
										<?php esc_html_e( 'Comma-separated list of text fragments (case-insensitive). If an applied coupon contains one of these texts (e.g. "5EUR" for loyalty vouchers), its discount will not reduce the order amount used for shipping tiers and free shipping thresholds. Other marketing coupons will still be deducted normally.', 'woo-flexible-shipping' ); ?>
									</p>
								</td>
							</tr>
						</tbody>
					</table>

					<div style="margin-top: 14px;">
						<button type="submit" name="wfs_save_global_settings" value="1" class="button button-primary" style="font-weight: 600; height: 38px; padding: 0 20px; font-size: 13px; border-radius: 6px;">
							<?php esc_html_e( 'Save Coupon Rules', 'woo-flexible-shipping' ); ?>
						</button>
					</div>
				</div>

				<!-- Hero Header Card -->
				<div class="card wfs-migration-hero" style="max-width: 100%;">
					<div style="display: flex; align-items: center; justify-content: space-between;">
						<div>
							<h2 style="margin: 0 0 8px 0; font-size: 20px; font-weight: 700; color: #1d2327;">
								<?php esc_html_e( '1-Click Flexible Shipping PRO Migration Assistant', 'woo-flexible-shipping' ); ?>
							</h2>
							<p style="margin: 0; font-size: 14px; line-height: 1.5; color: #3c434a;">
								<?php esc_html_e( 'Easily convert all 28+ existing flat rate methods configured with Octolize Flexible Shipping PRO into high-performance, zero-bloat Soyoo Table Rate rules.', 'woo-flexible-shipping' ); ?>
							</p>
						</div>
					</div>
				</div>

				<?php if ( $report ) : ?>
					<!-- Migration Report Card -->
					<div id="wfs-migration-report-card" class="card" style="max-width: 100%; padding: 24px; background: #f6fbf7; border: 1px solid #c3e6cb; border-radius: 8px; margin-bottom: 25px; box-shadow: 0 2px 6px rgba(0,0,0,0.04);">
						<div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;">
							<h3 style="margin: 0; color: #155724; font-size: 16px; font-weight: 700;">
								<?php esc_html_e( 'Migration Execution Complete!', 'woo-flexible-shipping' ); ?>
							</h3>
							<button type="button" class="button button-secondary" onclick="document.getElementById('wfs-migration-report-card').remove();" style="font-weight: 600; border-radius: 6px;">
								&times; <?php esc_html_e( 'Close Report', 'woo-flexible-shipping' ); ?>
							</button>
						</div>

						<p style="font-size: 14px; color: #155724; margin-bottom: 15px;">
							<?php
							printf(
								/* translators: 1: count of methods, 2: count of zones */
								esc_html__( 'Successfully converted %1$d method(s) across %2$d shipping zone(s).', 'woo-flexible-shipping' ),
								absint( $report['methods_migrated'] ?? 0 ),
								absint( $report['zones_scanned'] ?? 0 )
							);
							?>
						</p>

						<?php if ( ! empty( $report['details'] ) ) : ?>
							<table class="widefat striped" style="margin-top: 15px; border-radius: 6px; overflow: hidden; border: 1px solid #c3e6cb;">
								<thead>
									<tr>
										<th><?php esc_html_e( 'Shipping Zone', 'woo-flexible-shipping' ); ?></th>
										<th><?php esc_html_e( 'Instance ID', 'woo-flexible-shipping' ); ?></th>
										<th><?php esc_html_e( 'Method Title', 'woo-flexible-shipping' ); ?></th>
										<th style="text-align: center;"><?php esc_html_e( 'Rules Converted', 'woo-flexible-shipping' ); ?></th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ( $report['details'] as $item ) : ?>
										<tr>
											<td><strong><?php echo esc_html( $item['zone_name'] ); ?></strong></td>
											<td><span class="wfs-pill-badge-blue">#<?php echo esc_html( $item['instance_id'] ); ?></span></td>
											<td><?php echo esc_html( $item['title'] ); ?></td>
											<td style="text-align: center;"><span class="wfs-pill-badge"><?php echo esc_html( $item['rules_count'] ); ?> rules</span></td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						<?php endif; ?>
					</div>
				<?php endif; ?>

				<!-- Main Migration Action Card -->
				<div class="card" style="max-width: 100%; padding: 28px; background: #fff; border: 1px solid #c3c4c7; border-radius: 8px;">
					<h3 style="margin-top: 0; font-size: 17px; font-weight: 700; color: #1d2327;">
						<?php esc_html_e( 'Ready to Import Shipping Rules?', 'woo-flexible-shipping' ); ?>
					</h3>
					<p style="font-size: 14px; line-height: 1.6; color: #50575e; margin-bottom: 18px;">
						<?php esc_html_e( 'Clicking the button below will scan all shipping zones in WooCommerce, locate any active Flexible Shipping PRO rules (fs_method_rules), and convert them into Woo Flexible Shipping matrix format.', 'woo-flexible-shipping' ); ?>
					</p>

					<div style="padding: 14px 18px; background: #f8fafc; border-left: 4px solid #2271b1; border-radius: 4px; margin-bottom: 22px;">
						<strong style="color: #1d2327;"><?php esc_html_e( 'Safe & Non-Destructive:', 'woo-flexible-shipping' ); ?></strong>
						<span style="font-size: 13px; color: #50575e; display: inline-block; margin-left: 4px;">
							<?php esc_html_e( 'Migration reads existing rules and saves them safely under Soyoo Table Rate configuration without altering original Flexible Shipping PRO settings.', 'woo-flexible-shipping' ); ?>
						</span>
					</div>

					<button type="submit" name="wfs_run_migration" value="1" class="button button-primary button-large" style="font-weight: 600; height: 42px; padding: 0 24px; font-size: 14px; border-radius: 6px;">
						<?php esc_html_e( 'Import from Flexible Shipping PRO', 'woo-flexible-shipping' ); ?>
					</button>
				</div>
			</form>

		</div>
		<?php
	}
}
