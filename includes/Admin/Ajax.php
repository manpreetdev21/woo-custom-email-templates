<?php
/**
 * AJAX endpoints for the dashboard and the template builder.
 *
 * Every handler checks the nonce and the manage_woocommerce capability
 * before touching anything.
 *
 * @package Woo_Custom_Email_Templates
 */

namespace WCEM\Admin;

use WCEM\Core\Plugin;
use WCEM\Email\EmailManager;
use WCEM\Email\EmailSender;
use WCEM\Templates\ComponentRepository;
use WCEM\Templates\TemplateRepository;
use WCEM\Templates\Versions;

defined( 'ABSPATH' ) || exit;

final class Ajax {

	/**
	 * Hooks every action.
	 */
	public static function init(): void {
		$actions = array(
			'save_template',
			'delete_template',
			'duplicate_template',
			'preview_template',
			'send_test_email',
			'assign_template',
			'toggle_enabled',
			'reset_assignment',
			'restore_version',
		);

		foreach ( $actions as $action ) {
			\add_action( 'wp_ajax_wcem_' . $action, array( self::class, $action ) );
		}
	}

	/**
	 * Verifies the request nonce and capability. Dies with a JSON error on failure.
	 */
	private static function guard(): void {
		if ( ! \current_user_can( Plugin::cap() ) ) {
			\wp_send_json_error( array( 'message' => \__( 'You do not have permission to do that.', 'woo-custom-email-templates' ) ), 403 );
		}

		\check_ajax_referer( 'wcem_admin', 'nonce' );
	}

	/**
	 * Which repository this request operates on.
	 *
	 * Components and templates share one storage layer, so the builder and
	 * the list screens post a `kind` and get the matching repository back
	 * instead of duplicating every CRUD endpoint.
	 *
	 * @return class-string<TemplateRepository>
	 */
	private static function repo(): string {
		$kind = \sanitize_key( \wp_unslash( $_POST['kind'] ?? 'template' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- guard() runs first in every caller.

		return 'component' === $kind ? ComponentRepository::class : TemplateRepository::class;
	}

	/**
	 * Reads and JSON-decodes one POST field.
	 *
	 * @param string $key POST field name.
	 * @return array<mixed>
	 */
	private static function json_field( string $key ): array {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Missing -- decoded then run through dedicated sanitizers; guard() runs first.
		$raw  = \wp_unslash( $_POST[ $key ] ?? '' );
		$data = \json_decode( (string) $raw, true );

		return \is_array( $data ) ? $data : array();
	}

	/**
	 * A template payload assembled from unsaved editor state.
	 *
	 * @param class-string<TemplateRepository> $repo Repository to sanitize against.
	 * @return array<string, mixed>
	 */
	private static function editor_payload( string $repo ): array {
		return array(
			'subject'      => \sanitize_text_field( \wp_unslash( $_POST['subject'] ?? '' ) ), // phpcs:ignore WordPress.Security.NonceVerification.Missing -- guard() runs first.
			'preview_text' => \sanitize_text_field( \wp_unslash( $_POST['preview_text'] ?? '' ) ), // phpcs:ignore WordPress.Security.NonceVerification.Missing -- guard() runs first.
			'blocks'       => \array_values( \array_filter( \array_map( array( $repo, 'sanitize_block' ), self::json_field( 'blocks' ) ) ) ),
			'styles'       => $repo::sanitize_styles( self::json_field( 'styles' ) ),
		);
	}

	/** Saves (creates or updates) a template or component. */
	public static function save_template(): void {
		self::guard();

		$repo = self::repo();
		$kind = ComponentRepository::class === $repo ? 'component' : 'template';

		$result = $repo::save(
			array(
				'id'           => \absint( $_POST['id'] ?? 0 ),
				'name'         => \sanitize_text_field( \wp_unslash( $_POST['name'] ?? '' ) ),
				'description'  => \sanitize_textarea_field( \wp_unslash( $_POST['description'] ?? '' ) ),
				'status'       => \sanitize_key( \wp_unslash( $_POST['status'] ?? 'publish' ) ),
				'subject'      => \sanitize_text_field( \wp_unslash( $_POST['subject'] ?? '' ) ),
				'preview_text' => \sanitize_text_field( \wp_unslash( $_POST['preview_text'] ?? '' ) ),
				'blocks'       => self::json_field( 'blocks' ),
				'styles'       => self::json_field( 'styles' ),
			)
		);

		if ( \is_wp_error( $result ) ) {
			\wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		\wp_send_json_success(
			array(
				'id'      => $result,
				'editUrl' => Plugin::url( $kind . '-edit', array( $kind => $result ) ),
				'message' => \__( 'Template saved.', 'woo-custom-email-templates' ),
			)
		);
	}

	/** Deletes a template or component permanently. */
	public static function delete_template(): void {
		self::guard();

		$repo = self::repo();
		$id   = \absint( $_POST['id'] ?? 0 );

		if ( ! $id || ! $repo::get( $id ) || ! \wp_delete_post( $id, true ) ) {
			\wp_send_json_error( array( 'message' => \__( 'Could not delete that template.', 'woo-custom-email-templates' ) ) );
		}

		EmailManager::prune_assignments( $id );

		\wp_send_json_success( array( 'message' => \__( 'Template deleted.', 'woo-custom-email-templates' ) ) );
	}

	/** Duplicates a template or component. */
	public static function duplicate_template(): void {
		self::guard();

		$repo   = self::repo();
		$kind   = ComponentRepository::class === $repo ? 'component' : 'template';
		$result = $repo::duplicate( \absint( $_POST['id'] ?? 0 ) );

		if ( \is_wp_error( $result ) ) {
			\wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		\wp_send_json_success(
			array(
				'id'      => $result,
				'editUrl' => Plugin::url( $kind . '-edit', array( $kind => $result ) ),
				'message' => \__( 'Template duplicated.', 'woo-custom-email-templates' ),
			)
		);
	}

	/** Renders a live preview from unsaved editor state. */
	public static function preview_template(): void {
		self::guard();

		$rendered = EmailSender::preview(
			self::editor_payload( self::repo() ),
			\absint( $_POST['order_id'] ?? 0 )
		);

		\wp_send_json_success( $rendered );
	}

	/** Sends a test email from unsaved editor state. */
	public static function send_test_email(): void {
		self::guard();

		$result = EmailSender::send_test(
			self::editor_payload( self::repo() ),
			\sanitize_email( \wp_unslash( $_POST['recipient'] ?? '' ) ),
			\absint( $_POST['order_id'] ?? 0 )
		);

		if ( \is_wp_error( $result ) ) {
			\wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		\wp_send_json_success( array( 'message' => \__( 'Test email sent.', 'woo-custom-email-templates' ) ) );
	}

	/** Assigns a template to a WooCommerce email type. */
	public static function assign_template(): void {
		self::guard();

		$email_id    = \sanitize_key( \wp_unslash( $_POST['email_id'] ?? '' ) );
		$template_id = \absint( $_POST['template_id'] ?? 0 );
		$enabled     = ! empty( $_POST['enabled'] );

		$result = $template_id
			? EmailManager::assign( $email_id, $template_id, $enabled )
			: EmailManager::reset( $email_id );

		if ( \is_wp_error( $result ) ) {
			\wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		\wp_send_json_success( array( 'message' => \__( 'Assignment saved.', 'woo-custom-email-templates' ) ) );
	}

	/** Toggles an assignment's enabled state without changing the template. */
	public static function toggle_enabled(): void {
		self::guard();

		$result = EmailManager::set_enabled(
			\sanitize_key( \wp_unslash( $_POST['email_id'] ?? '' ) ),
			! empty( $_POST['enabled'] )
		);

		if ( \is_wp_error( $result ) ) {
			\wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		\wp_send_json_success( array( 'message' => \__( 'Updated.', 'woo-custom-email-templates' ) ) );
	}

	/** Resets (removes) an email type's override, restoring WooCommerce's default. */
	public static function reset_assignment(): void {
		self::guard();

		EmailManager::reset( \sanitize_key( \wp_unslash( $_POST['email_id'] ?? '' ) ) );

		\wp_send_json_success( array( 'message' => \__( 'Reset to the WooCommerce default.', 'woo-custom-email-templates' ) ) );
	}

	/** Restores a template to an earlier version. */
	public static function restore_version(): void {
		self::guard();

		$result = Versions::restore( \absint( $_POST['revision_id'] ?? 0 ) );

		if ( \is_wp_error( $result ) ) {
			\wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		\wp_send_json_success(
			array(
				'message' => \__( 'Version restored.', 'woo-custom-email-templates' ),
				'editUrl' => Plugin::url( 'template-edit', array( 'template' => $result ) ),
			)
		);
	}
}
