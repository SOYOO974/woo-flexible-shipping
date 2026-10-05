<?php
namespace WooFlexibleShipping\Migration;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WP-CLI command integration for 1-Click Migration from Flexible Shipping PRO.
 */
class CLICommand {

	/**
	 * Register WP-CLI commands if WP_CLI is present.
	 *
	 * @return void
	 */
	public static function init(): void {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command( 'woo-fs migrate', array( static::class, 'run_migration' ) );
			\WP_CLI::add_command( 'woo-flexible-shipping migrate', array( static::class, 'run_migration' ) );
		}
	}

	/**
	 * WP-CLI command execution handler.
	 *
	 * ## EXAMPLES
	 *
	 *     wp woo-fs migrate
	 *
	 * @param array $args Command arguments.
	 * @param array $assoc_args Command flags.
	 * @return void
	 */
	public static function run_migration( array $args, array $assoc_args ): void {
		\WP_CLI::log( 'Starting Flexible Shipping PRO -> Woo Flexible Shipping migration...' );

		$migrator = new Migrator();
		$report   = $migrator->migrate_all();

		if ( empty( $report['success'] ) ) {
			\WP_CLI::error( $report['error'] ?? 'Migration failed.' );
			return;
		}

		if ( $report['methods_migrated'] === 0 ) {
			\WP_CLI::warning( 'No Flexible Shipping PRO methods requiring migration were found.' );
			return;
		}

		\WP_CLI::success( sprintf( 'Successfully migrated %d shipping method(s) across %d zone(s).', $report['methods_migrated'], $report['zones_scanned'] ) );

		if ( ! empty( $report['details'] ) ) {
			\WP_CLI\Utils\format_items( 'table', $report['details'], array( 'zone_name', 'instance_id', 'title', 'rules_count' ) );
		}
	}
}
