<?php
/**
 * Backwards compatibility for the pre-namespace API.
 *
 * Every 1.0 class was a global `WCEM_*` class and every starter helper was
 * a global `wcem_*` function. Third-party code may reference either, so
 * both keep working.
 *
 * The class shims are wired through a second autoloader rather than a list
 * of class_alias() calls: an alias forces its target to load, which would
 * pull the entire plugin into memory on every request just in case someone
 * referenced a legacy name. This way nothing loads until a legacy name is
 * actually used.
 *
 * @deprecated 1.1.0 Use the WCEM\ namespace.
 *
 * @package Woo_Custom_Email_Templates
 */

defined( 'ABSPATH' ) || exit;

/**
 * Legacy class name => current class name.
 *
 * @return array<string, string>
 */
function wcem_legacy_class_map() {
	return array(
		'WCEM_Plugin'            => \WCEM\Core\Plugin::class,
		'WCEM_Template_Post_Type' => \WCEM\Templates\TemplateRepository::class,
		'WCEM_Template_Renderer' => \WCEM\Templates\TemplateRenderer::class,
		'WCEM_Blocks'            => \WCEM\Builder\Blocks::class,
		'WCEM_Email_Tags'        => \WCEM\Email\EmailTags::class,
		'WCEM_Email_Manager'     => \WCEM\Email\EmailManager::class,
		'WCEM_Email_Sender'      => \WCEM\Email\EmailSender::class,
		'WCEM_Woo_Bridge'        => \WCEM\WooCommerce\Bridge::class,
		'WCEM_Import_Export'     => \WCEM\Tools\ImportExport::class,
		'WCEM_Admin'             => \WCEM\Admin\Admin::class,
		'WCEM_Ajax'              => \WCEM\Admin\Ajax::class,
	);
}

spl_autoload_register(
	static function ( $class ) {
		$map = wcem_legacy_class_map();

		if ( isset( $map[ $class ] ) ) {
			class_alias( $map[ $class ], $class );
		}
	}
);

if ( ! function_exists( 'wcem_block' ) ) {
	/**
	 * A block, in the shape the sanitizer expects.
	 *
	 * @deprecated 1.1.0 Use WCEM\Templates\StarterTemplates::block().
	 *
	 * @param string $type     Block type.
	 * @param array  $settings Block settings.
	 * @return array
	 */
	function wcem_block( $type, $settings = array() ) {
		return \WCEM\Templates\StarterTemplates::block( (string) $type, (array) $settings );
	}
}

if ( ! function_exists( 'wcem_install_starter_templates' ) ) {
	/**
	 * Seeds the built-in starter library.
	 *
	 * @deprecated 1.1.0 Use WCEM\Templates\StarterTemplates::install().
	 */
	function wcem_install_starter_templates() {
		\WCEM\Templates\StarterTemplates::install();
	}
}
