<?php
/**
 * The built-in starter library.
 *
 * Definitions live here at runtime (not only at activation) so the
 * Template Library screen can preview them and create a template from any
 * of them at any time.
 *
 * @package Woo_Custom_Email_Templates
 */

namespace WCEM\Templates;

use WP_Error;

defined( 'ABSPATH' ) || exit;

final class StarterTemplates {

	/**
	 * A block, in the shape TemplateRepository::sanitize_block() expects.
	 *
	 * @param string $type     Block type.
	 * @param array  $settings Block settings.
	 * @return array<string, mixed>
	 */
	public static function block( string $type, array $settings = array() ): array {
		return array(
			'id'       => \wp_generate_password( 8, false ),
			'type'     => $type,
			'origin'   => 0,
			'settings' => $settings,
		);
	}

	/**
	 * The blocks common to every starter layout: a header, a greeting,
	 * order details/totals, a CTA and a footer.
	 *
	 * @return array<int, array>
	 */
	public static function default_blocks(): array {
		return array(
			self::block(
				'header',
				array(
					'show_name' => 1,
					'align'     => 'center',
				)
			),
			self::block(
				'heading',
				array(
					'text'  => \__( 'Thank you for your order!', 'woo-custom-email-templates' ),
					'tag'   => 'h2',
					'align' => 'center',
				)
			),
			self::block(
				'text',
				array(
					'content' => \__( 'Hi {customer_first_name}, we\'re getting your order ready. We\'ll let you know as soon as it ships.', 'woo-custom-email-templates' ),
					'align'   => 'center',
				)
			),
			self::block(
				'button',
				array(
					'text'  => \__( 'View Your Order', 'woo-custom-email-templates' ),
					'url'   => '{view_order_url}',
					'align' => 'center',
				)
			),
			self::block( 'divider' ),
			self::block( 'order_details' ),
			self::block( 'order_totals' ),
			self::block( 'divider' ),
			self::block( 'customer_details' ),
			self::block( 'footer', array( 'text' => '{site_name} · {shop_url}' ) ),
		);
	}

	/**
	 * The whole starter library, keyed by slug.
	 *
	 * @return array<string, array{name: string, description: string, category: string, blocks: array, styles: array}>
	 */
	public static function library(): array {
		$library = array(
			'minimal'      => array(
				'name'        => \__( 'Minimal', 'woo-custom-email-templates' ),
				'description' => \__( 'Clean white design with simple typography and square corners.', 'woo-custom-email-templates' ),
				'category'    => \__( 'Minimal', 'woo-custom-email-templates' ),
				'styles'      => array(
					'bg_color'     => '#ffffff',
					'content_bg'   => '#ffffff',
					'button_bg'    => '#18181b',
					'radius'       => 0,
					'heading_size' => 22,
				),
			),
			'modern-store' => array(
				'name'        => \__( 'Modern Store', 'woo-custom-email-templates' ),
				'description' => \__( 'Modern ecommerce design with a brand colour, order summary and a dark footer.', 'woo-custom-email-templates' ),
				'category'    => \__( 'Ecommerce', 'woo-custom-email-templates' ),
				'styles'      => array(
					'bg_color'    => '#f4f4f5',
					'content_bg'  => '#ffffff',
					'button_bg'   => '#2563eb',
					'link_color'  => '#2563eb',
					'radius'      => 10,
					'footer_bg'   => '#18181b',
					'footer_text' => '#d4d4d8',
				),
			),
			'premium'      => array(
				'name'        => \__( 'Premium', 'woo-custom-email-templates' ),
				'description' => \__( 'Elegant serif typography, generous spacing and a warm accent colour.', 'woo-custom-email-templates' ),
				'category'    => \__( 'Premium', 'woo-custom-email-templates' ),
				'styles'      => array(
					'bg_color'      => '#f5f4f0',
					'content_bg'    => '#ffffff',
					'button_bg'     => '#92400e',
					'heading_color' => '#1c1917',
					'heading_size'  => 28,
					'font_family'   => 'Georgia, \'Times New Roman\', serif',
					'radius'        => 4,
					'padding'       => 40,
				),
			),
			'dark'         => array(
				'name'        => \__( 'Dark', 'woo-custom-email-templates' ),
				'description' => \__( 'Dark content area with high-contrast headings and a light button.', 'woo-custom-email-templates' ),
				'category'    => \__( 'Dark', 'woo-custom-email-templates' ),
				'styles'      => array(
					'bg_color'      => '#09090b',
					'content_bg'    => '#18181b',
					'text_color'    => '#d4d4d8',
					'heading_color' => '#fafafa',
					'link_color'    => '#93c5fd',
					'button_bg'     => '#fafafa',
					'button_text'   => '#09090b',
					'footer_bg'     => '#09090b',
					'footer_text'   => '#71717a',
					'radius'        => 8,
				),
			),
			'compact'      => array(
				'name'        => \__( 'Compact', 'woo-custom-email-templates' ),
				'description' => \__( 'Tight spacing and small type, tuned for purely transactional mail.', 'woo-custom-email-templates' ),
				'category'    => \__( 'Transactional', 'woo-custom-email-templates' ),
				'styles'      => array(
					'bg_color'     => '#ffffff',
					'content_bg'   => '#ffffff',
					'padding'      => 20,
					'heading_size' => 18,
					'body_size'    => 13,
					'radius'       => 0,
				),
			),
		);

		foreach ( $library as $slug => $definition ) {
			$library[ $slug ]['blocks'] = self::default_blocks();
			$library[ $slug ]['styles'] = \wp_parse_args( $definition['styles'], TemplateRepository::default_styles() );
		}

		// A deliberately empty starting point, listed last.
		$library['blank'] = array(
			'name'        => \__( 'Blank', 'woo-custom-email-templates' ),
			'description' => \__( 'An empty canvas — a header and a footer, nothing else.', 'woo-custom-email-templates' ),
			'category'    => \__( 'Simple', 'woo-custom-email-templates' ),
			'blocks'      => array(
				self::block( 'header', array( 'show_name' => 1 ) ),
				self::block( 'footer' ),
			),
			'styles'      => TemplateRepository::default_styles(),
		);

		/**
		 * Filters the starter template library.
		 *
		 * @param array $library slug => [ name, description, category, blocks, styles ].
		 */
		return \apply_filters( 'wcem_available_templates', $library );
	}

	/**
	 * Creates a new draft template from a library entry.
	 *
	 * @param string $slug Library slug.
	 * @return int|WP_Error New template ID.
	 */
	public static function create( string $slug ) {
		$library = self::library();

		if ( ! isset( $library[ $slug ] ) ) {
			return new WP_Error( 'wcem_no_starter', \__( 'That starter template does not exist.', 'woo-custom-email-templates' ) );
		}

		$definition = $library[ $slug ];

		return TemplateRepository::save(
			array(
				'name'        => $definition['name'],
				'description' => $definition['description'],
				'status'      => 'draft',
				'blocks'      => $definition['blocks'],
				'styles'      => $definition['styles'],
				'subject'     => '',
			)
		);
	}

	/**
	 * Seeds the whole library on first activation.
	 */
	public static function install(): void {
		foreach ( \array_keys( self::library() ) as $slug ) {
			if ( 'blank' === $slug ) {
				continue; // Nothing to seed; the library screen offers it on demand.
			}

			self::create( $slug );
		}
	}
}
