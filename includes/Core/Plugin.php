<?php
/**
 * Bootstrap, environment guards and shared settings access.
 *
 * @package Woo_Custom_Email_Templates
 */

namespace WCEM\Core;

use WCEM\Admin\Admin;
use WCEM\Admin\Ajax;
use WCEM\Templates\ComponentRepository;
use WCEM\Templates\StarterTemplates;
use WCEM\Templates\TemplateRepository;
use WCEM\WooCommerce\Bridge;

defined( 'ABSPATH' ) || exit;

final class Plugin {

	/** Plugin version. Mirrors the WCEM_VERSION bootstrap constant. */
	public const VERSION = '1.0.0';

	/** Option holding the plugin settings array. */
	public const SETTINGS = 'wcem_settings';

	/** Option flagging that the starter library has been seeded. */
	public const SEEDED = 'wcem_seeded';

	/** Option flagging that onboarding has been completed or skipped. */
	public const ONBOARDED = 'wcem_onboarded';

	/**
	 * Loads the plugin once WordPress and WooCommerce are available.
	 */
	public static function boot(): void {
		\load_plugin_textdomain(
			'woo-custom-email-templates',
			false,
			\dirname( \plugin_basename( WCEM_FILE ) ) . '/languages'
		);

		if ( ! self::woocommerce_active() ) {
			\add_action( 'admin_notices', array( __CLASS__, 'render_requirement_notice' ) );
			return;
		}

		// Post types belong on `init`: $wp_rewrite does not exist yet on plugins_loaded.
		\add_action( 'init', array( TemplateRepository::class, 'init' ) );
		\add_action( 'init', array( ComponentRepository::class, 'init' ) );

		Bridge::init();

		if ( \is_admin() ) {
			Admin::init();
			Ajax::init();
		}
	}

	/**
	 * Seeds the starter library and default settings on first activation.
	 *
	 * Activation runs after `plugins_loaded`, so boot() never fires on this
	 * request and the post types must be registered here directly.
	 */
	public static function activate(): void {
		if ( ! self::woocommerce_active() ) {
			return; // Nothing to seed yet; boot() will nag in the admin.
		}

		TemplateRepository::init();
		ComponentRepository::init();

		\add_option( self::SETTINGS, self::default_settings() );

		if ( ! \get_option( self::SEEDED ) ) {
			StarterTemplates::install();
			\update_option( self::SEEDED, self::VERSION );
		}
	}

	/**
	 * Whether a usable WooCommerce is active.
	 *
	 * Deliberately does NOT test for WC_Email: WooCommerce only loads its
	 * email classes when the mailer first initialises, which is long after
	 * `plugins_loaded` where boot() runs. Requiring it here made this always
	 * return false, silently disabling the whole plugin. Code that actually
	 * touches WC_Email goes through WC()->mailer(), which loads it on demand.
	 */
	public static function woocommerce_active(): bool {
		return \class_exists( 'WooCommerce' ) && \function_exists( 'WC' );
	}

	/**
	 * Admin notice shown when WooCommerce is missing.
	 */
	public static function render_requirement_notice(): void {
		if ( ! \current_user_can( 'activate_plugins' ) ) {
			return;
		}

		\wp_admin_notice(
			\esc_html__( 'WooCommerce Custom Email Templates requires WooCommerce to be installed and activated.', 'woo-custom-email-templates' ),
			array( 'type' => 'warning' )
		);
	}

	/**
	 * Capability required to manage templates.
	 */
	public static function cap(): string {
		return 'manage_woocommerce';
	}

	/**
	 * Dies with a friendly message when the current user lacks access.
	 */
	public static function require_cap(): void {
		if ( ! \current_user_can( self::cap() ) ) {
			\wp_die(
				\esc_html__( 'You do not have permission to manage email templates.', 'woo-custom-email-templates' ),
				403
			);
		}
	}

	/**
	 * Default plugin settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function default_settings(): array {
		return array(
			'test_sender_name'    => \get_bloginfo( 'name' ),
			'test_sender_email'   => \get_option( 'admin_email' ),
			'debug'               => 0,
			'delete_on_uninstall' => 0,
		);
	}

	/**
	 * All settings, merged over defaults.
	 *
	 * @return array<string, mixed>
	 */
	public static function settings(): array {
		return \wp_parse_args( (array) \get_option( self::SETTINGS, array() ), self::default_settings() );
	}

	/**
	 * A single setting value.
	 *
	 * @param string $key     Setting name.
	 * @param mixed  $default Fallback when unset.
	 * @return mixed
	 */
	public static function setting( string $key, $default = '' ) {
		$settings = self::settings();
		return $settings[ $key ] ?? $default;
	}

	/**
	 * Writes a line to the debug log when debug mode is enabled.
	 * Order/customer data is never logged.
	 *
	 * @param string $message Context line.
	 */
	public static function log( string $message ): void {
		if ( ! self::setting( 'debug' ) ) {
			return;
		}

		$log   = (array) \get_option( 'wcem_log', array() );
		$log[] = array(
			'time'    => \time(),
			'message' => $message,
		);

		// Keep the log bounded; this is a diagnostic aid, not an archive.
		\update_option( 'wcem_log', \array_slice( $log, -200 ), false );
	}

	/**
	 * Admin URL for one of the plugin screens.
	 *
	 * @param string               $page Page slug suffix, e.g. 'templates'.
	 * @param array<string, mixed> $args Extra query arguments.
	 */
	public static function url( string $page = '', array $args = array() ): string {
		$slug = 'wcem' . ( $page ? '-' . $page : '' );
		return \add_query_arg( array( 'page' => $slug ) + $args, \admin_url( 'admin.php' ) );
	}
}
