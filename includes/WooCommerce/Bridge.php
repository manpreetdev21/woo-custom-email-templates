<?php
/**
 * The WooCommerce integration layer.
 *
 * Templates are injected at send time through WooCommerce's own
 * `woocommerce_mail_content` and per-id `woocommerce_email_subject_*`
 * filters. Nothing is ever written into WooCommerce's own email settings,
 * and no WooCommerce file is touched — assigning a template is instantly
 * reversible, and an administrator's own subject/heading settings are
 * simply bypassed while enabled, never destroyed.
 *
 * @package Woo_Custom_Email_Templates
 */

namespace WCEM\WooCommerce;

use Automattic\WooCommerce\Utilities\FeaturesUtil;
use WCEM\Email\EmailManager;
use WCEM\Templates\TemplateRenderer;
use WCEM\Templates\TemplateRepository;
use WC_Email;
use WC_Order;
use WP_User;

defined( 'ABSPATH' ) || exit;

final class Bridge {

	/** The email currently being built, captured from its header action. */
	private static ?WC_Email $current_email = null;

	/**
	 * Email ids that already have a subject filter registered, keyed by id.
	 *
	 * @var array<string, bool>
	 */
	private static array $subject_filters = array();

	/**
	 * Tells WooCommerce which of its opt-in features this plugin is safe with.
	 *
	 * Without this, WooCommerce lists the plugin as "uncertain" for High-
	 * Performance Order Storage and warns the administrator that enabling
	 * HPOS may break it.
	 *
	 * The claim is honest: orders are only ever reached through wc_get_order(),
	 * wc_get_orders() and WC_Order's own accessors, which read whichever
	 * datastore is active. The plugin never queries the posts or postmeta
	 * tables for order data — its only get_post_meta() call reads the subject
	 * and preheader of its own wcem_template post type.
	 *
	 * Must be registered on `before_woocommerce_init`, which fires before
	 * WooCommerce decides whether it can turn HPOS on.
	 */
	public static function declare_compatibility(): void {
		if ( ! \class_exists( FeaturesUtil::class ) ) {
			return;
		}

		FeaturesUtil::declare_compatibility( 'custom_order_tables', WCEM_FILE, true );
	}

	/**
	 * Hooks the integration.
	 */
	public static function init(): void {
		\add_filter( 'woocommerce_email_classes', array( self::class, 'register_subject_filters' ), 20 );
		\add_action( 'woocommerce_email_header', array( self::class, 'capture_email' ), 5, 2 );
		\add_filter( 'woocommerce_mail_content', array( self::class, 'filter_content' ), 20 );
		\add_action( 'deleted_post', array( EmailManager::class, 'prune_assignments' ) );
	}

	/**
	 * Registers a per-id subject filter for every email WooCommerce reports,
	 * the first time each id is seen.
	 *
	 * @param mixed $classes id => WC_Email.
	 * @return mixed Unchanged; this hook is used only for its side effect.
	 */
	public static function register_subject_filters( $classes ) {
		foreach ( (array) $classes as $email ) {
			if ( ! $email instanceof WC_Email || ! $email->id || isset( self::$subject_filters[ $email->id ] ) ) {
				continue;
			}

			self::$subject_filters[ $email->id ] = true;
			\add_filter( 'woocommerce_email_subject_' . $email->id, array( self::class, 'filter_subject' ), 20, 2 );
		}

		return $classes;
	}

	/**
	 * Remembers which WC_Email is currently building its content, so the
	 * subject and content filters know which one to look up.
	 *
	 * @param string $email_heading Unused.
	 * @param mixed  $email         The sending email.
	 */
	public static function capture_email( $email_heading, $email = null ): void {
		self::$current_email = ( $email instanceof WC_Email ) ? $email : null;
	}

	/**
	 * Replaces WooCommerce's fully-built message with the assigned
	 * template's render, for HTML emails with an active override only.
	 *
	 * @param mixed $message WooCommerce's computed message.
	 * @return mixed
	 */
	public static function filter_content( $message ) {
		$email               = self::$current_email;
		self::$current_email = null; // Never let a stale capture leak into an unrelated send.

		if ( ! $email instanceof WC_Email || ! \str_contains( (string) $email->get_content_type(), 'html' ) ) {
			return $message;
		}

		$template = self::active_template_for( (string) $email->id );

		if ( ! $template ) {
			return $message;
		}

		return TemplateRenderer::render( $template, self::context_for( $email ) );
	}

	/**
	 * Replaces a WC_Email's subject when the assigned template defines one.
	 *
	 * @param mixed $subject WooCommerce's computed subject.
	 * @param mixed $object  The order (or user, for account emails) WooCommerce passed.
	 * @return mixed
	 */
	public static function filter_subject( $subject, $object = null ) {
		$id = \str_replace( 'woocommerce_email_subject_', '', (string) \current_filter() );

		$template = self::active_template_for( $id );

		if ( ! $template ) {
			return $subject;
		}

		$context = $object instanceof WC_Order
			? array(
				'order' => $object,
				'user'  => null,
			)
			: array(
				'order' => null,
				'user'  => $object instanceof WP_User ? $object : null,
			);

		return TemplateRenderer::subject( $template, (string) $subject, $context );
	}

	/**
	 * The assigned, active, still-existing template for an email id.
	 *
	 * @param string $email_id WC_Email id.
	 */
	private static function active_template_for( string $email_id ): ?array {
		$row = EmailManager::for_email( $email_id );

		if ( ! $row || empty( $row['enabled'] ) ) {
			return null;
		}

		$template = TemplateRepository::get( (int) $row['template_id'] );

		// Only an active template is allowed to take over a live email.
		return ( $template && 'publish' === $template['status'] ) ? $template : null;
	}

	/**
	 * Builds the tag-resolution context from a live WC_Email.
	 *
	 * @param WC_Email $email Sending email.
	 * @return array{order: WC_Order|null, user: WP_User|null}
	 */
	private static function context_for( WC_Email $email ): array {
		$object = $email->object ?? null;

		return array(
			'order' => $object instanceof WC_Order ? $object : null,
			'user'  => $object instanceof WP_User ? $object : null,
		);
	}
}
