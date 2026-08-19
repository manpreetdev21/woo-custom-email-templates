<?php
/**
 * Dynamic {tag} placeholders, resolved from a WC_Order (or a WP_User for
 * account emails) at render time.
 *
 * @package Woo_Custom_Email_Templates
 */

namespace WCEM\Email;

use WC_Order;
use WP_User;

defined( 'ABSPATH' ) || exit;

final class EmailTags {

	/**
	 * Groups shown in the editor's "Insert Dynamic Data" picker.
	 *
	 * @return array<string, array<string, string>> Group label => [ tag => label ].
	 */
	public static function groups(): array {
		return array(
			\__( 'Customer', 'woo-custom-email-templates' ) => array(
				'customer_name'       => \__( 'Customer Name', 'woo-custom-email-templates' ),
				'customer_first_name' => \__( 'First Name', 'woo-custom-email-templates' ),
				'customer_last_name'  => \__( 'Last Name', 'woo-custom-email-templates' ),
				'customer_email'      => \__( 'Email', 'woo-custom-email-templates' ),
				'customer_note'       => \__( 'Customer Note', 'woo-custom-email-templates' ),
			),
			\__( 'Order', 'woo-custom-email-templates' )    => array(
				'order_id'         => \__( 'Order ID', 'woo-custom-email-templates' ),
				'order_number'     => \__( 'Order Number', 'woo-custom-email-templates' ),
				'order_date'       => \__( 'Order Date', 'woo-custom-email-templates' ),
				'order_status'     => \__( 'Order Status', 'woo-custom-email-templates' ),
				'order_total'      => \__( 'Order Total', 'woo-custom-email-templates' ),
				'payment_method'   => \__( 'Payment Method', 'woo-custom-email-templates' ),
				'shipping_method'  => \__( 'Shipping Method', 'woo-custom-email-templates' ),
				'billing_address'  => \__( 'Billing Address', 'woo-custom-email-templates' ),
				'shipping_address' => \__( 'Shipping Address', 'woo-custom-email-templates' ),
				'view_order_url'   => \__( 'View Order URL', 'woo-custom-email-templates' ),
			),
			\__( 'Store', 'woo-custom-email-templates' )    => array(
				'site_name' => \__( 'Store Name', 'woo-custom-email-templates' ),
				'site_url'  => \__( 'Store URL', 'woo-custom-email-templates' ),
				'shop_url'  => \__( 'Shop URL', 'woo-custom-email-templates' ),
			),
		);
	}

	/**
	 * Flat tag => label map.
	 *
	 * @return array<string, string>
	 */
	public static function all_tags(): array {
		$out = array();

		foreach ( self::groups() as $tags ) {
			$out += $tags;
		}

		return $out;
	}

	/**
	 * Resolves every known tag for a rendering context.
	 *
	 * @param array $context [ order => WC_Order|null, user => WP_User|null ].
	 * @return array<string, string> Tag => value (plain text, not yet escaped).
	 */
	public static function values( array $context ): array {
		$order = $context['order'] ?? null;
		$user  = $context['user'] ?? null;

		$first = '';
		$last  = '';
		$email = '';

		if ( $order instanceof WC_Order ) {
			$first = $order->get_billing_first_name();
			$last  = $order->get_billing_last_name();
			$email = $order->get_billing_email();
		} elseif ( $user instanceof WP_User ) {
			$first = $user->first_name ? $user->first_name : $user->display_name;
			$last  = $user->last_name;
			$email = $user->user_email;
		}

		$values = array(
			'customer_name'       => \trim( $first . ' ' . $last ),
			'customer_first_name' => $first,
			'customer_last_name'  => $last,
			'customer_email'      => $email,
			'customer_note'       => $order instanceof WC_Order ? $order->get_customer_note() : '',
			'order_id'            => $order instanceof WC_Order ? (string) $order->get_id() : '',
			'order_number'        => $order instanceof WC_Order ? $order->get_order_number() : '',
			'order_date'          => ( $order instanceof WC_Order && $order->get_date_created() ) ? \wc_format_datetime( $order->get_date_created() ) : '',
			'order_status'        => $order instanceof WC_Order ? \wc_get_order_status_name( $order->get_status() ) : '',
			'order_total'         => $order instanceof WC_Order ? \wp_strip_all_tags( $order->get_formatted_order_total() ) : '',
			'payment_method'      => $order instanceof WC_Order ? $order->get_payment_method_title() : '',
			'shipping_method'     => $order instanceof WC_Order ? $order->get_shipping_method() : '',
			'billing_address'     => $order instanceof WC_Order ? \wp_strip_all_tags( $order->get_formatted_billing_address(), true ) : '',
			'shipping_address'    => $order instanceof WC_Order ? \wp_strip_all_tags( $order->get_formatted_shipping_address(), true ) : '',
			'view_order_url'      => $order instanceof WC_Order ? $order->get_view_order_url() : ( \function_exists( 'wc_get_page_permalink' ) ? \wc_get_page_permalink( 'myaccount' ) : \home_url( '/' ) ),
			'site_name'           => \get_bloginfo( 'name' ),
			'site_url'            => \home_url( '/' ),
			'shop_url'            => \function_exists( 'wc_get_page_permalink' ) ? \wc_get_page_permalink( 'shop' ) : \home_url( '/' ),
		);

		/*
		 * In a sample-data preview there is no order, so every order and
		 * customer tag would resolve to an empty string — the editor would
		 * show "Hi ," next to sample product rows the blocks *do* fill in.
		 * Fill the blanks with demo values that match those rows.
		 *
		 * Only EmailSender sets this flag, and only when no real order was
		 * chosen. The WooCommerce bridge never sets it, so a live email can
		 * never be sent carrying sample data.
		 */
		if ( ! empty( $context['sample'] ) ) {
			foreach ( self::sample_values() as $tag => $sample ) {
				if ( '' === ( $values[ $tag ] ?? '' ) ) {
					$values[ $tag ] = $sample;
				}
			}
		}

		return \apply_filters( 'wcem_email_tags', $values, $context );
	}

	/**
	 * Demo tag values for previews with no real order behind them.
	 *
	 * Deliberately consistent with the sample rows the WooCommerce blocks
	 * render: two items totalling $128.00, shipped to the same address.
	 *
	 * @return array<string, string>
	 */
	public static function sample_values(): array {
		return array(
			'customer_name'       => \__( 'Jane Doe', 'woo-custom-email-templates' ),
			'customer_first_name' => \__( 'Jane', 'woo-custom-email-templates' ),
			'customer_last_name'  => \__( 'Doe', 'woo-custom-email-templates' ),
			'customer_email'      => 'jane@example.com',
			'customer_note'       => \__( 'Please leave the parcel with a neighbour.', 'woo-custom-email-templates' ),
			'order_id'            => '1234',
			'order_number'        => '1234',
			'order_date'          => \date_i18n( \get_option( 'date_format' ) ),
			'order_status'        => \__( 'Processing', 'woo-custom-email-templates' ),
			'order_total'         => '$128.00',
			'payment_method'      => \__( 'Credit card', 'woo-custom-email-templates' ),
			'shipping_method'     => \__( 'Flat rate', 'woo-custom-email-templates' ),
			'billing_address'     => \__( '123 Sample St, Springfield, IL 62704', 'woo-custom-email-templates' ),
			'shipping_address'    => \__( '123 Sample St, Springfield, IL 62704', 'woo-custom-email-templates' ),
		);
	}

	/**
	 * Replaces every {tag} in a string.
	 *
	 * @param string $text    Raw text.
	 * @param array  $context Rendering context.
	 * @param bool   $escape  Whether to esc_html() each value (off for plain-text output).
	 */
	public static function replace( string $text, array $context, bool $escape = true ): string {
		if ( '' === $text || ! \str_contains( $text, '{' ) ) {
			return $text;
		}

		$search  = array();
		$replace = array();

		foreach ( self::values( $context ) as $tag => $value ) {
			$search[]  = '{' . $tag . '}';
			$replace[] = $escape ? \esc_html( $value ) : $value;
		}

		return \str_replace( $search, $replace, $text );
	}
}
