<?php
namespace WooFlexibleShipping\Migration;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin Migration Page & WooCommerce Settings Tab for 1-Click Flexible Shipping PRO Import.
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
	 * Render 1-Click migration tab content.
	 *
	 * @return void
	 */
	public function render_migration_page(): void {
		global $current_section;

		if ( 'wfs_migration' !== $current_section ) {
			return;
		}

		$report = null;

		if ( isset( $_POST['wfs_run_migration'] ) && check_admin_referer( 'wfs_migrate_action', 'wfs_migrate_nonce' ) ) {
			$migrator = new Migrator();
			$report   = $migrator->migrate_all();
		}

		?>
		<style>
			/* Suppress global floating bottom save button on migration tab */
			p.submit { display: none !important; }
		</style>

		<div class="wrap wfs-migration-wrapper" style="max-width: 920px; margin-top: 15px;">
			
			<!-- Hero Header Card -->
			<div class="card wfs-migration-hero">
				<div style="display: flex; align-items: center; justify-content: space-between;">
					<div>
						<h2 style="margin: 0 0 8px 0; font-size: 20px; font-weight: 700; color: #1d2327;">
							⚡ <?php esc_html_e( '1-Click Flexible Shipping PRO Migration Assistant', 'woo-flexible-shipping' ); ?>
						</h2>
						<p style="margin: 0; font-size: 14px; line-height: 1.5; color: #3c434a;">
							<?php esc_html_e( 'Easily convert all 28+ existing flat rate methods configured with Octolize Flexible Shipping PRO into high-performance, zero-bloat Soyoo Table Rate rules.', 'woo-flexible-shipping' ); ?>
						</p>
					</div>
				</div>
			</div>

			<?php if ( $report ) : ?>
				<!-- Migration Report Card -->
				<div id="wfs-migration-report-card" class="card" style="padding: 24px; background: #f6fbf7; border: 1px solid #c3e6cb; border-radius: 8px; margin-bottom: 25px; box-shadow: 0 2px 6px rgba(0,0,0,0.04);">
					<div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;">
						<h3 style="margin: 0; color: #155724; font-size: 16px; font-weight: 700;">
							🎉 <?php esc_html_e( 'Migration Execution Complete!', 'woo-flexible-shipping' ); ?>
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
			<div class="card" style="padding: 28px; background: #fff; border: 1px solid #c3c4c7; border-radius: 8px;">
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

				<form method="post" action="">
					<?php wp_nonce_field( 'wfs_migrate_action', 'wfs_migrate_nonce' ); ?>
					<button type="submit" name="wfs_run_migration" class="button button-primary button-large" style="font-weight: 600; height: 42px; padding: 0 24px; font-size: 14px; border-radius: 6px;">
						🚀 <?php esc_html_e( 'Import from Flexible Shipping PRO', 'woo-flexible-shipping' ); ?>
					</button>
				</form>
			</div>

		</div>
		<?php
	}
}
