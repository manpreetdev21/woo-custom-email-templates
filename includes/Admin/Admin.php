<?php
/**
 * Admin menu, screens, assets and the non-AJAX form handlers.
 *
 * The plugin owns a top-level menu of its own, placed after WooCommerce's
 * cluster rather than nested inside WooCommerce's submenu — the builder is
 * a full application screen, not another WooCommerce settings tab.
 *
 * @package Woo_Custom_Email_Templates
 */

namespace WCEM\Admin;

use WCEM\Core\Plugin;
use WCEM\Email\EmailManager;
use WCEM\Templates\StarterTemplates;
use WCEM\Templates\TemplateRepository;
use WCEM\Tools\ImportExport;

defined( 'ABSPATH' ) || exit;

final class Admin {

	/** Top-level menu slug. */
	public const MENU_SLUG = 'wcem';

	/**
	 * Screens reachable by URL but never listed in the sidebar.
	 *
	 * @var string[]
	 */
	private const HIDDEN_SCREENS = array( 'wcem-template-edit', 'wcem-component-edit', 'wcem-onboarding' );

	/**
	 * Our page hook suffixes, filled in by register_menu().
	 *
	 * @var array<string, string>
	 */
	private static array $hooks = array();

	/**
	 * Hooks the admin layer.
	 */
	public static function init(): void {
		\add_action( 'admin_menu', array( self::class, 'register_menu' ) );
		\add_action( 'admin_head', array( self::class, 'hide_utility_screens' ) );
		\add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue' ) );
		\add_action( 'admin_init', array( self::class, 'maybe_redirect_to_onboarding' ) );

		\add_action( 'admin_post_wcem_save_settings', array( self::class, 'handle_save_settings' ) );
		\add_action( 'admin_post_wcem_export', array( self::class, 'handle_export' ) );
		\add_action( 'admin_post_wcem_import', array( self::class, 'handle_import' ) );
		\add_action( 'admin_post_wcem_clear_log', array( self::class, 'handle_clear_log' ) );
		\add_action( 'admin_post_wcem_use_starter', array( self::class, 'handle_use_starter' ) );
		\add_action( 'admin_post_wcem_onboarding', array( self::class, 'handle_onboarding' ) );
	}

	/**
	 * Registers the top-level menu and its screens.
	 */
	public static function register_menu(): void {
		$cap = Plugin::cap();

		/**
		 * Filters where the plugin's menu sits in the admin sidebar.
		 *
		 * Defaults to just below WooCommerce's own cluster — verified against
		 * WooCommerce 11, which registers WooCommerce at 55.5, Payments at 56,
		 * Analytics at 57 and Marketing at 58 — and above Appearance at 60.
		 * WooCommerce has moved these between releases, so the exact gap is
		 * not guaranteed; this filter is the escape hatch.
		 *
		 * @param float $position Menu position.
		 */
		$position = \apply_filters( 'wcem_menu_position', 58.7 );

		self::$hooks[ self::MENU_SLUG ] = \add_menu_page(
			\__( 'Email Templates', 'woo-custom-email-templates' ),
			\__( 'Email Templates', 'woo-custom-email-templates' ),
			$cap,
			self::MENU_SLUG,
			array( self::class, 'render_dashboard' ),
			'dashicons-email-alt',
			$position
		);

		$submenus = array(
			self::MENU_SLUG        => array( \__( 'Dashboard', 'woo-custom-email-templates' ), 'render_dashboard' ),
			'wcem-templates'       => array( \__( 'Templates', 'woo-custom-email-templates' ), 'render_templates' ),
			'wcem-library'         => array( \__( 'Template Library', 'woo-custom-email-templates' ), 'render_library' ),
			'wcem-components'      => array( \__( 'Components', 'woo-custom-email-templates' ), 'render_components' ),
			'wcem-assignments'     => array( \__( 'Assignments', 'woo-custom-email-templates' ), 'render_assignments' ),
			'wcem-settings'        => array( \__( 'Settings', 'woo-custom-email-templates' ), 'render_settings' ),
			'wcem-tools'           => array( \__( 'Tools', 'woo-custom-email-templates' ), 'render_tools' ),
			// Reachable by URL, hidden from the sidebar below.
			'wcem-template-edit'   => array( \__( 'Edit Template', 'woo-custom-email-templates' ), 'render_editor' ),
			'wcem-component-edit'  => array( \__( 'Edit Component', 'woo-custom-email-templates' ), 'render_editor' ),
			'wcem-onboarding'      => array( \__( 'Get Started', 'woo-custom-email-templates' ), 'render_onboarding' ),
		);

		foreach ( $submenus as $slug => $config ) {
			list( $title, $callback ) = $config;

			self::$hooks[ $slug ] = \add_submenu_page(
				self::MENU_SLUG,
				$title,
				$title,
				$cap,
				$slug,
				array( self::class, $callback )
			);
		}

	}

	/**
	 * Drops the utility screens from the sidebar while leaving them
	 * reachable by URL.
	 *
	 * This deliberately runs on `admin_head`, not on `admin_menu` where the
	 * pages are registered. remove_submenu_page() only unsets the entry from
	 * the $submenu array, and get_admin_page_parent() derives a page's parent
	 * by scanning exactly that array. Remove the entry during admin_menu and
	 * the parent resolves to '' instead of 'wcem', so WordPress looks for the
	 * hook `admin_page_wcem-template-edit` while add_submenu_page() actually
	 * registered `email-templates_page_wcem-template-edit` — the mismatch
	 * makes user_can_access_admin_page() fail and every one of these screens
	 * dies with "Sorry, you are not allowed to access this page."
	 *
	 * admin_head fires after that access check and before menu-header.php
	 * paints the sidebar, so removing here hides the items without breaking
	 * access, and the parent menu stays highlighted while editing.
	 */
	public static function hide_utility_screens(): void {
		foreach ( self::HIDDEN_SCREENS as $slug ) {
			\remove_submenu_page( self::MENU_SLUG, $slug );
		}
	}

	/**
	 * Sends a first-time administrator to the onboarding screen once.
	 */
	public static function maybe_redirect_to_onboarding(): void {
		if ( \wp_doing_ajax() || \get_option( Plugin::ONBOARDED ) || ! \current_user_can( Plugin::cap() ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only screen check.
		$page = isset( $_GET['page'] ) ? \sanitize_key( \wp_unslash( $_GET['page'] ) ) : '';

		if ( self::MENU_SLUG !== $page ) {
			return;
		}

		\wp_safe_redirect( Plugin::url( 'onboarding' ) );
		exit;
	}

	/**
	 * Whether the current screen belongs to this plugin.
	 *
	 * @param string $hook Current admin page hook.
	 */
	private static function is_plugin_screen( string $hook ): bool {
		return \in_array( $hook, self::$hooks, true );
	}

	/**
	 * Whether the current screen is one of the two builder screens.
	 *
	 * @param string $hook Current admin page hook.
	 */
	private static function is_editor_screen( string $hook ): bool {
		return \in_array(
			$hook,
			array( self::$hooks['wcem-template-edit'] ?? '', self::$hooks['wcem-component-edit'] ?? '' ),
			true
		);
	}

	/**
	 * Loads CSS and JS on this plugin's screens only.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public static function enqueue( $hook ): void {
		$hook = (string) $hook;

		if ( ! self::is_plugin_screen( $hook ) ) {
			return;
		}

		\wp_enqueue_style( 'wcem-admin', WCEM_URL . 'assets/css/admin.css', array(), Plugin::VERSION );
		\wp_enqueue_script( 'wcem-admin', WCEM_URL . 'assets/js/admin.js', array( 'jquery' ), Plugin::VERSION, true );

		\wp_localize_script(
			'wcem-admin',
			'wcem',
			array(
				'ajaxUrl' => \admin_url( 'admin-ajax.php' ),
				'nonce'   => \wp_create_nonce( 'wcem_admin' ),
				'i18n'    => array(
					'saving'         => \__( 'Saving…', 'woo-custom-email-templates' ),
					'saved'          => \__( 'Template saved.', 'woo-custom-email-templates' ),
					'save'           => \__( 'Save', 'woo-custom-email-templates' ),
					'error'          => \__( 'Something went wrong. Please try again.', 'woo-custom-email-templates' ),
					'deleteTitle'    => \__( 'Delete template?', 'woo-custom-email-templates' ),
					'deleteBody'     => \__( 'This action cannot be undone.', 'woo-custom-email-templates' ),
					'resetTitle'     => \__( 'Reset template?', 'woo-custom-email-templates' ),
					'resetBody'      => \__( 'Your custom design will be removed and the WooCommerce default template will be restored.', 'woo-custom-email-templates' ),
					'restoreTitle'   => \__( 'Restore this version?', 'woo-custom-email-templates' ),
					'restoreBody'    => \__( 'The current design will be replaced by the selected version. The current design is itself kept as a version.', 'woo-custom-email-templates' ),
					'restore'        => \__( 'Restore Version', 'woo-custom-email-templates' ),
					'syncTitle'      => \__( 'Re-sync components?', 'woo-custom-email-templates' ),
					'syncBody'       => \__( 'Blocks inserted from a component will be replaced with that component\'s current blocks. Edits you made to those blocks here will be lost.', 'woo-custom-email-templates' ),
					'sync'           => \__( 'Re-sync', 'woo-custom-email-templates' ),
					'synced'         => \__( 'Components re-synced.', 'woo-custom-email-templates' ),
					'nothingToSync'  => \__( 'This template has no component blocks to re-sync.', 'woo-custom-email-templates' ),
					'noComponents'   => \__( 'No components yet. Create one under Components to reuse it here.', 'woo-custom-email-templates' ),
					'cancel'         => \__( 'Cancel', 'woo-custom-email-templates' ),
					'confirmDelete'  => \__( 'Delete Template', 'woo-custom-email-templates' ),
					'confirmReset'   => \__( 'Reset Template', 'woo-custom-email-templates' ),
					'testSent'       => \__( 'Test email sent.', 'woo-custom-email-templates' ),
					'unsaved'        => \__( 'You have unsaved changes. Leave anyway?', 'woo-custom-email-templates' ),
					'copied'         => \__( 'Tag copied — paste it into the selected field.', 'woo-custom-email-templates' ),
					'selectBlock'    => \__( 'Select a block on the canvas to edit its settings.', 'woo-custom-email-templates' ),
					'emptyCanvas'    => \__( 'Add blocks from the left to start building your email.', 'woo-custom-email-templates' ),
					'settingsSuffix' => \__( 'Settings', 'woo-custom-email-templates' ),
					'globalDesign'   => \__( 'Global Design', 'woo-custom-email-templates' ),
					'components'     => \__( 'Components', 'woo-custom-email-templates' ),
					'selectImage'    => \__( 'Select Image', 'woo-custom-email-templates' ),
					'replaceImage'   => \__( 'Replace', 'woo-custom-email-templates' ),
					'removeImage'    => \__( 'Remove', 'woo-custom-email-templates' ),
					'duplicate'      => \__( 'Duplicate', 'woo-custom-email-templates' ),
					'delete'         => \__( 'Delete', 'woo-custom-email-templates' ),
					'layout'         => \__( 'Layout', 'woo-custom-email-templates' ),
					'content'        => \__( 'Content', 'woo-custom-email-templates' ),
					'woocommerce'    => \__( 'WooCommerce', 'woo-custom-email-templates' ),
				),
			)
		);

		if ( self::is_editor_screen( $hook ) ) {
			\wp_enqueue_media();
			\wp_enqueue_script( 'jquery-ui-sortable' );

			\wp_enqueue_script( 'wcem-editor', WCEM_URL . 'assets/js/editor.js', array( 'wcem-admin', 'jquery-ui-sortable' ), Plugin::VERSION, true );
		}
	}

	/* ---------------------------------------------------------------------
	 * Screens
	 * ------------------------------------------------------------------ */

	/**
	 * Renders one of the view files.
	 *
	 * @param string $view View file base name.
	 */
	private static function view( string $view ): void {
		Plugin::require_cap();

		$file = WCEM_DIR . 'admin/views/' . $view . '.php';

		if ( \file_exists( $file ) ) {
			require $file;
		}
	}

	/** Dashboard screen. */
	public static function render_dashboard(): void {
		self::view( 'dashboard' );
	}

	/** Templates list screen. */
	public static function render_templates(): void {
		self::view( 'templates' );
	}

	/** Starter library screen. */
	public static function render_library(): void {
		self::view( 'library' );
	}

	/** Reusable components list screen. */
	public static function render_components(): void {
		self::view( 'components' );
	}

	/** Template / component builder screen. */
	public static function render_editor(): void {
		self::view( 'editor' );
	}

	/** Assignments screen. */
	public static function render_assignments(): void {
		self::view( 'assignments' );
	}

	/** Settings screen. */
	public static function render_settings(): void {
		self::view( 'settings' );
	}

	/** Tools screen. */
	public static function render_tools(): void {
		self::view( 'tools' );
	}

	/** First-run onboarding screen. */
	public static function render_onboarding(): void {
		self::view( 'onboarding' );
	}

	/**
	 * Shared page header markup.
	 *
	 * @param string $title       Screen title.
	 * @param string $description Short description under the title.
	 * @param string $actions     Optional HTML for the right-hand actions.
	 */
	public static function header( string $title, string $description = '', string $actions = '' ): void {
		\printf(
			'<div class="wcem-header"><div><h1 class="wcem-header__title"><span class="dashicons dashicons-email-alt"></span> %s</h1>%s</div><div class="wcem-header__actions">%s</div></div>',
			\esc_html( $title ),
			$description ? '<p class="wcem-header__desc">' . \esc_html( $description ) . '</p>' : '',
			\wp_kses_post( $actions )
		);
	}

	/**
	 * Prints an admin notice queued through the redirect URL.
	 */
	public static function flash(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only message key.
		$notice = isset( $_GET['wcem_notice'] ) ? \sanitize_key( \wp_unslash( $_GET['wcem_notice'] ) ) : '';

		if ( ! $notice ) {
			return;
		}

		$messages = array(
			'settings_saved' => array( 'success', \__( 'Settings saved.', 'woo-custom-email-templates' ) ),
			'imported'       => array( 'success', \__( 'Templates imported.', 'woo-custom-email-templates' ) ),
			'import_failed'  => array( 'error', \__( 'That file could not be imported. Please upload a valid export file.', 'woo-custom-email-templates' ) ),
			'log_cleared'    => array( 'success', \__( 'Debug log cleared.', 'woo-custom-email-templates' ) ),
			'starter_added'  => array( 'success', \__( 'Template created from the library.', 'woo-custom-email-templates' ) ),
			'starter_failed' => array( 'error', \__( 'That starter template could not be created.', 'woo-custom-email-templates' ) ),
			'onboarded'      => array( 'success', \__( 'You are all set. Your template is ready to edit.', 'woo-custom-email-templates' ) ),
		);

		if ( ! isset( $messages[ $notice ] ) ) {
			return;
		}

		list( $type, $message ) = $messages[ $notice ];

		\printf( '<div class="wcem-alert wcem-alert--%s" role="status">%s</div>', \esc_attr( $type ), \esc_html( $message ) );
	}

	/* ---------------------------------------------------------------------
	 * Form handlers
	 * ------------------------------------------------------------------ */

	/**
	 * Verifies nonce and capability for an admin-post request.
	 *
	 * @param string $action Nonce action.
	 */
	private static function verify( string $action ): void {
		Plugin::require_cap();
		\check_admin_referer( $action );
	}

	/**
	 * Redirects back to a plugin screen with a notice.
	 *
	 * @param string $page   Page slug suffix.
	 * @param string $notice Notice key.
	 * @param array  $args   Extra query arguments.
	 */
	private static function redirect( string $page, string $notice, array $args = array() ): void {
		\wp_safe_redirect( Plugin::url( $page, array( 'wcem_notice' => $notice ) + $args ) );
		exit;
	}

	/** Saves the settings form. */
	public static function handle_save_settings(): void {
		self::verify( 'wcem_save_settings' );

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized field by field below.
		$input = \wp_unslash( (array) ( $_POST['settings'] ?? array() ) );

		$clean = array(
			'test_sender_name'    => \sanitize_text_field( $input['test_sender_name'] ?? '' ),
			'test_sender_email'   => \sanitize_email( $input['test_sender_email'] ?? '' ),
			'debug'               => empty( $input['debug'] ) ? 0 : 1,
			'delete_on_uninstall' => empty( $input['delete_on_uninstall'] ) ? 0 : 1,
		);

		\update_option( Plugin::SETTINGS, $clean );

		self::redirect( 'settings', 'settings_saved' );
	}

	/** Streams a JSON export of all templates. */
	public static function handle_export(): void {
		self::verify( 'wcem_export' );

		$payload = ImportExport::export_payload();

		\nocache_headers();
		\header( 'Content-Type: application/json; charset=utf-8' );
		\header( 'Content-Disposition: attachment; filename=woo-email-templates-' . \gmdate( 'Y-m-d' ) . '.json' );

		echo \wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		exit;
	}

	/** Imports templates from an uploaded JSON file. */
	public static function handle_import(): void {
		self::verify( 'wcem_import' );

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- validated inside read_upload().
		$data = ImportExport::read_upload( (array) ( $_FILES['import_file'] ?? array() ) );

		if ( \is_wp_error( $data ) ) {
			self::redirect( 'tools', 'import_failed' );
		}

		$imported = ImportExport::import( (array) $data );

		self::redirect( 'tools', ( \is_wp_error( $imported ) || ! $imported ) ? 'import_failed' : 'imported' );
	}

	/** Empties the debug log. */
	public static function handle_clear_log(): void {
		self::verify( 'wcem_clear_log' );

		\delete_option( 'wcem_log' );

		self::redirect( 'tools', 'log_cleared' );
	}

	/** Creates a template from a starter library entry. */
	public static function handle_use_starter(): void {
		self::verify( 'wcem_use_starter' );

		$slug   = \sanitize_key( \wp_unslash( $_POST['slug'] ?? '' ) );
		$result = StarterTemplates::create( $slug );

		if ( \is_wp_error( $result ) ) {
			self::redirect( 'library', 'starter_failed' );
		}

		self::redirect( 'template-edit', 'starter_added', array( 'template' => (int) $result ) );
	}

	/** Completes (or skips) the first-run wizard. */
	public static function handle_onboarding(): void {
		self::verify( 'wcem_onboarding' );

		\update_option( Plugin::ONBOARDED, 1 );

		if ( ! empty( $_POST['skip'] ) ) {
			self::redirect( '', 'onboarded' );
		}

		$slug   = \sanitize_key( \wp_unslash( $_POST['slug'] ?? 'modern-store' ) );
		$result = StarterTemplates::create( $slug );

		if ( \is_wp_error( $result ) ) {
			self::redirect( '', 'starter_failed' );
		}

		$template_id = (int) $result;

		$brand    = \sanitize_hex_color( \wp_unslash( $_POST['brand'] ?? '' ) );
		$template = TemplateRepository::get( $template_id );

		if ( $template ) {
			// Onboarding assigns this template to live emails, and only an
			// active template is allowed to override one — so publish it
			// rather than leaving the library's draft status in place.
			$template['status'] = 'publish';

			if ( $brand ) {
				$template['styles']['button_bg']  = $brand;
				$template['styles']['link_color'] = $brand;
			}

			TemplateRepository::save( $template );
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- each id sanitized below.
		foreach ( (array) \wp_unslash( $_POST['emails'] ?? array() ) as $email_id ) {
			EmailManager::assign( \sanitize_key( $email_id ), $template_id, true );
		}

		self::redirect( 'template-edit', 'onboarded', array( 'template' => $template_id ) );
	}
}
