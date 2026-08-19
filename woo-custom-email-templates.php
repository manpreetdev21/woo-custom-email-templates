<?php
/**
 * Plugin Name:       WooCommerce Custom Email Templates
 * Plugin URI:        https://github.com/manpreetdev21/woo-custom-email-templates
 * Description:       Design modern, branded WooCommerce transactional emails with a drag-and-drop builder — without ever editing WooCommerce's own files.
 * Version:           1.1.0
 * Author:            Manpreet Singh
 * Author URI:        https://github.com/manpreetdev21/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       woo-custom-email-templates
 * Domain Path:       /languages
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * WC requires at least: 7.0
 * WC tested up to:   11.0
 *
 * @package Woo_Custom_Email_Templates
 */

defined( 'ABSPATH' ) || exit;

define( 'WCEM_VERSION', '1.1.0' );
define( 'WCEM_FILE', __FILE__ );
define( 'WCEM_DIR', plugin_dir_path( __FILE__ ) );
define( 'WCEM_URL', plugin_dir_url( __FILE__ ) );

require_once WCEM_DIR . 'includes/Core/Autoloader.php';
require_once WCEM_DIR . 'includes/compat.php';

WCEM\Core\Autoloader::register( 'WCEM\\', WCEM_DIR . 'includes/' );

/*
 * Feature compatibility must be declared before WooCommerce initialises,
 * which is earlier than `plugins_loaded` where the plugin itself boots.
 * The class reference lives inside the closure so nothing autoloads unless
 * WooCommerce is actually present to fire the hook.
 */
add_action(
	'before_woocommerce_init',
	static function () {
		WCEM\WooCommerce\Bridge::declare_compatibility();
	}
);

add_action( 'plugins_loaded', array( WCEM\Core\Plugin::class, 'boot' ) );
register_activation_hook( __FILE__, array( WCEM\Core\Plugin::class, 'activate' ) );
