<?php
/**
 * Discovers WooCommerce's registered email classes and stores which
 * template (if any) is assigned to each one.
 *
 * @package Woo_Custom_Email_Templates
 */

namespace WCEM\Email;

use WCEM\Core\Plugin;
use WCEM\Templates\TemplateRepository;
use WC_Email;
use WP_Error;

defined( 'ABSPATH' ) || exit;

final class EmailManager {

	public const OPTION = 'wcem_assignments';

	/**
	 * Every WC_Email WooCommerce (core or third-party) has registered,
	 * discovered through WooCommerce's own mailer — never hardcoded.
	 *
	 * @return array<string, WC_Email> id => WC_Email instance.
	 */
	public static function all_emails(): array {
		if ( ! Plugin::woocommerce_active() || ! \WC()->mailer() ) {
			return array();
		}

		$out = array();

		foreach ( \WC()->mailer()->get_emails() as $email ) {
			if ( $email instanceof WC_Email && $email->id ) {
				$out[ $email->id ] = $email;
			}
		}

		return $out;
	}

	/**
	 * One email by id.
	 *
	 * @param string $id WC_Email id, e.g. 'customer_processing_order'.
	 */
	public static function get_email( string $id ): ?WC_Email {
		$emails = self::all_emails();
		return $emails[ $id ] ?? null;
	}

	/**
	 * Whether an email id belongs to a customer-facing email (best-effort:
	 * WooCommerce prefixes its own customer email ids this way; third-party
	 * emails that don't follow the convention just land under "Other").
	 *
	 * @param string $id WC_Email id.
	 */
	public static function is_customer_email( string $id ): bool {
		return \str_starts_with( $id, 'customer_' );
	}

	/**
	 * The whole assignment map, sanitized.
	 *
	 * @return array<string, array{template_id: int, enabled: int}>
	 */
	public static function assignments(): array {
		$map   = (array) \get_option( self::OPTION, array() );
		$clean = array();

		foreach ( $map as $email_id => $row ) {
			$email_id = \sanitize_key( $email_id );

			if ( ! $email_id || ! \is_array( $row ) ) {
				continue;
			}

			$template_id = \absint( $row['template_id'] ?? 0 );

			if ( ! $template_id ) {
				continue;
			}

			$clean[ $email_id ] = array(
				'template_id' => $template_id,
				'enabled'     => empty( $row['enabled'] ) ? 0 : 1,
			);
		}

		return $clean;
	}

	/**
	 * The assignment for one email id.
	 *
	 * @param string $email_id WC_Email id.
	 * @return array|null [ template_id, enabled ], or null when unassigned.
	 */
	public static function for_email( string $email_id ): ?array {
		$map = self::assignments();
		return $map[ $email_id ] ?? null;
	}

	/**
	 * Assigns a template to an email type.
	 *
	 * @param string $email_id    WC_Email id.
	 * @param int    $template_id Template ID.
	 * @param bool   $enabled     Whether the override is live.
	 * @return true|WP_Error
	 */
	public static function assign( string $email_id, int $template_id, bool $enabled = true ) {
		$email_id    = \sanitize_key( $email_id );
		$template_id = \absint( $template_id );

		if ( ! self::get_email( $email_id ) ) {
			return new WP_Error( 'wcem_no_email', \__( 'That WooCommerce email type no longer exists.', 'woo-custom-email-templates' ) );
		}

		if ( ! TemplateRepository::get( $template_id ) ) {
			return new WP_Error( 'wcem_no_template', \__( 'That template no longer exists.', 'woo-custom-email-templates' ) );
		}

		$map              = self::assignments();
		$map[ $email_id ] = array(
			'template_id' => $template_id,
			'enabled'     => $enabled ? 1 : 0,
		);

		\update_option( self::OPTION, $map );

		Plugin::log( \sprintf( 'Template #%d assigned to %s (enabled: %s).', $template_id, $email_id, $enabled ? 'yes' : 'no' ) );

		/**
		 * Fires after a template is assigned to a WooCommerce email type.
		 *
		 * @param string $email_id    WC_Email id.
		 * @param int    $template_id Template ID.
		 * @param bool   $enabled     Whether the override is live.
		 */
		\do_action( 'wcem_email_template_assigned', $email_id, $template_id, $enabled );

		return true;
	}

	/**
	 * Toggles the enabled flag without changing the assigned template.
	 *
	 * @param string $email_id WC_Email id.
	 * @param bool   $enabled  New state.
	 * @return true|WP_Error
	 */
	public static function set_enabled( string $email_id, bool $enabled ) {
		$email_id = \sanitize_key( $email_id );
		$row      = self::for_email( $email_id );

		if ( ! $row ) {
			return new WP_Error( 'wcem_not_assigned', \__( 'Assign a template to this email first.', 'woo-custom-email-templates' ) );
		}

		return self::assign( $email_id, (int) $row['template_id'], $enabled );
	}

	/**
	 * Removes the assignment, restoring WooCommerce's own default template.
	 *
	 * @param string $email_id WC_Email id.
	 */
	public static function reset( string $email_id ): bool {
		$email_id = \sanitize_key( $email_id );
		$map      = self::assignments();

		unset( $map[ $email_id ] );

		\update_option( self::OPTION, $map );

		Plugin::log( \sprintf( 'Override reset for %s.', $email_id ) );

		return true;
	}

	/**
	 * Drops assignments pointing at a deleted template.
	 *
	 * @param int $post_id Deleted post ID.
	 */
	public static function prune_assignments( $post_id ): void {
		$post_id = (int) $post_id;
		$post    = \get_post( $post_id );

		if ( ! $post || TemplateRepository::POST_TYPE !== $post->post_type ) {
			return;
		}

		$map     = self::assignments();
		$changed = false;

		foreach ( $map as $email_id => $row ) {
			if ( (int) $row['template_id'] === $post_id ) {
				unset( $map[ $email_id ] );
				$changed = true;
			}
		}

		if ( $changed ) {
			\update_option( self::OPTION, $map );
		}
	}

	/**
	 * Email ids currently using a given template.
	 *
	 * @param int $template_id Template ID.
	 * @return array<int, string>
	 */
	public static function emails_using( int $template_id ): array {
		$out = array();

		foreach ( self::assignments() as $email_id => $row ) {
			if ( (int) $row['template_id'] === $template_id ) {
				$out[] = $email_id;
			}
		}

		return $out;
	}

	/**
	 * Count of email types with a live (enabled) override.
	 */
	public static function active_override_count(): int {
		$count = 0;

		foreach ( self::assignments() as $row ) {
			if ( ! empty( $row['enabled'] ) ) {
				++$count;
			}
		}

		return $count;
	}
}
