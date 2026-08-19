<?php
/**
 * A minimal PSR-4 autoloader.
 *
 * Composer is deliberately not used: the plugin has no third-party
 * dependencies, and shipping a vendor/ directory only to map one namespace
 * onto one directory would complicate deployment for no gain.
 *
 * @package Woo_Custom_Email_Templates
 */

namespace WCEM\Core;

defined( 'ABSPATH' ) || exit;

final class Autoloader {

	/**
	 * Registers a PSR-4 mapping of one namespace prefix onto one directory.
	 *
	 * @param string $prefix   Namespace prefix, e.g. 'WCEM\'.
	 * @param string $base_dir Directory the prefix maps to, with a trailing slash.
	 */
	public static function register( string $prefix, string $base_dir ): void {
		\spl_autoload_register(
			static function ( string $class ) use ( $prefix, $base_dir ): void {
				if ( ! \str_starts_with( $class, $prefix ) ) {
					return;
				}

				$relative = \substr( $class, \strlen( $prefix ) );
				$file     = $base_dir . \strtr( $relative, '\\', '/' ) . '.php';

				if ( \is_readable( $file ) ) {
					require_once $file;
				}
			}
		);
	}
}
