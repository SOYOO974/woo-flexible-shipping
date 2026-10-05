<?php
namespace WooFlexibleShipping;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Lightweight PSR-4 Autoloader for WooFlexibleShipping namespace.
 */
class Autoloader {

	/**
	 * Register the autoloader.
	 *
	 * @return void
	 */
	public static function register(): void {
		spl_autoload_register( array( static::class, 'autoload' ) );
	}

	/**
	 * Autoload class file.
	 *
	 * @param string $class Class name.
	 * @return void
	 */
	public static function autoload( string $class ): void {
		$prefix   = 'WooFlexibleShipping\\';
		$base_dir = WFS_PLUGIN_DIR . 'src/';

		$len = strlen( $prefix );
		if ( strncmp( $prefix, $class, $len ) !== 0 ) {
			return;
		}

		$relative_class = substr( $class, $len );
		$file           = $base_dir . str_replace( '\\', '/', $relative_class ) . '.php';

		if ( file_exists( $file ) ) {
			require_once $file;
		}
	}
}
