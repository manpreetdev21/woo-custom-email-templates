<?php
/**
 * Sample-data preview rendering and test-email sending.
 *
 * @package Woo_Custom_Email_Templates
 */

namespace WCEM\Email;

use WCEM\Core\Plugin;
use WCEM\Templates\TemplateRenderer;
use WC_Order;
use WP_Error;

defined( 'ABSPATH' ) || exit;

final class EmailSender {

	/** Seconds a user must wait between test sends. */
	private const THROTTLE_SECONDS = 60;

	/**
	 * Builds a context for previewing: a real order when one is picked,
	 * otherwise null (blocks fall back to their own sample content).
	 *
	 * @param int $order_id Optional real WooCommerce order ID.
	 * @return array{order: WC_Order|null, user: null, sample: bool}
	 */
	public static function context( int $order_id = 0 ): array {
		$order = null;

		if ( $order_id && \function_exists( 'wc_get_order' ) ) {
			$maybe_order = \wc_get_order( \absint( $order_id ) );
			$order       = $maybe_order instanceof WC_Order ? $maybe_order : null;
		}

		return array(
			'order'  => $order,
			'user'   => null,
			// With no real order behind it, tags resolve to demo values so the
			// preview reads like an email instead of showing blanks. Set only
			// here — the WooCommerce bridge never marks a live send as sample.
			'sample' => null === $order,
		);
	}

	/**
	 * Renders a template (saved or unsaved data straight from the editor)
	 * with sample or real order data.
	 *
	 * @param array $template Template data ( blocks, styles, subject... ).
	 * @param int   $order_id Optional real order ID.
	 * @return array{subject: string, html: string}
	 */
	public static function preview( array $template, int $order_id = 0 ): array {
		$context = self::context( $order_id );

		return array(
			'subject' => TemplateRenderer::subject( $template, \get_bloginfo( 'name' ), $context ),
			'html'    => TemplateRenderer::render( $template, $context ),
		);
	}

	/**
	 * The most recent orders, for the "preview with a real order" picker.
	 *
	 * @return array<int, string> id => label.
	 */
	public static function recent_orders(): array {
		if ( ! \function_exists( 'wc_get_orders' ) ) {
			return array();
		}

		$orders = \wc_get_orders(
			array(
				'limit'   => 20,
				'orderby' => 'date',
				'order'   => 'DESC',
			)
		);

		$out = array();

		foreach ( $orders as $order ) {
			if ( ! $order instanceof WC_Order ) {
				continue;
			}

			$out[ $order->get_id() ] = \sprintf(
				/* translators: 1: order number, 2: billing name, 3: order total */
				\__( '#%1$s — %2$s (%3$s)', 'woo-custom-email-templates' ),
				$order->get_order_number(),
				\trim( $order->get_formatted_billing_full_name() ) ?: \__( 'Guest', 'woo-custom-email-templates' ),
				\wp_strip_all_tags( $order->get_formatted_order_total() )
			);
		}

		return $out;
	}

	/**
	 * Sends a test email using sample (or a chosen real order's) data.
	 *
	 * @param array  $template  Template data.
	 * @param string $recipient Test recipient.
	 * @param int    $order_id  Optional real order ID.
	 * @return true|WP_Error
	 */
	public static function send_test( array $template, string $recipient, int $order_id = 0 ) {
		$recipient = \sanitize_email( $recipient );

		if ( ! \is_email( $recipient ) ) {
			return new WP_Error( 'wcem_bad_email', \__( 'Please enter a valid email address.', 'woo-custom-email-templates' ) );
		}

		// Cheap brake on using test sends as a mailer.
		$throttle_key = 'wcem_test_throttle_' . \get_current_user_id();

		if ( \get_transient( $throttle_key ) ) {
			return new WP_Error(
				'wcem_throttled',
				\__( 'Please wait a moment before sending another test email.', 'woo-custom-email-templates' )
			);
		}

		/**
		 * Fires before a test email is rendered and sent.
		 *
		 * @param array  $template  Template data.
		 * @param string $recipient Recipient address.
		 * @param int    $order_id  Real order ID, or 0 for sample data.
		 */
		\do_action( 'wcem_before_send_test_email', $template, $recipient, $order_id );

		$rendered = self::preview( $template, $order_id );

		$from_name  = \sanitize_text_field( (string) Plugin::setting( 'test_sender_name', \get_bloginfo( 'name' ) ) );
		$from_email = \sanitize_email( (string) Plugin::setting( 'test_sender_email', \get_option( 'admin_email' ) ) );

		if ( ! \is_email( $from_email ) ) {
			$from_email = (string) \get_option( 'admin_email' );
		}

		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
			// Quote the display name: an unquoted comma or angle bracket in a
			// store name would otherwise split the header into a second address.
			\sprintf( 'From: "%s" <%s>', \str_replace( '"', '', $from_name ), $from_email ),
		);

		$subject = \sprintf(
			/* translators: %s: rendered subject line */
			\__( '[TEST] %s', 'woo-custom-email-templates' ),
			$rendered['subject']
		);

		$sent = \wp_mail( $recipient, $subject, $rendered['html'], $headers );

		\set_transient( $throttle_key, 1, self::THROTTLE_SECONDS );

		Plugin::log( \sprintf( 'Test email to %s — %s.', $recipient, $sent ? 'Success' : 'Failed' ) );

		if ( ! $sent ) {
			return new WP_Error(
				'wcem_send_failed',
				\__( 'WordPress could not send the test email. Check your site\'s email configuration or SMTP plugin.', 'woo-custom-email-templates' )
			);
		}

		return true;
	}
}
