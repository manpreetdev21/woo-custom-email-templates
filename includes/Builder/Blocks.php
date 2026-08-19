<?php
/**
 * Block registry: settings schema (defaults + sanitizers) and email-safe,
 * table-based HTML rendering for every block type.
 *
 * Every render_*() method returns one or more <tr> rows for the outer
 * content table — never a full document, that is TemplateRenderer's job.
 *
 * @package Woo_Custom_Email_Templates
 */

namespace WCEM\Builder;

use WCEM\Email\EmailTags;
use WC_Order;

defined( 'ABSPATH' ) || exit;

final class Blocks {

	/**
	 * Block type => [ label, icon (dashicon), group ]. Order here is the
	 * order shown in the editor's block palette.
	 *
	 * @return array<string, array{0: string, 1: string, 2: string}>
	 */
	public static function registry(): array {
		return array(
			'header'           => array( \__( 'Header', 'woo-custom-email-templates' ), 'dashicons-align-center', 'layout' ),
			'columns'          => array( \__( 'Columns', 'woo-custom-email-templates' ), 'dashicons-columns', 'layout' ),
			'heading'          => array( \__( 'Heading', 'woo-custom-email-templates' ), 'dashicons-heading', 'content' ),
			'text'             => array( \__( 'Text', 'woo-custom-email-templates' ), 'dashicons-text', 'content' ),
			'image'            => array( \__( 'Image', 'woo-custom-email-templates' ), 'dashicons-format-image', 'content' ),
			'button'           => array( \__( 'Button', 'woo-custom-email-templates' ), 'dashicons-button', 'content' ),
			'divider'          => array( \__( 'Divider', 'woo-custom-email-templates' ), 'dashicons-minus', 'content' ),
			'spacer'           => array( \__( 'Spacer', 'woo-custom-email-templates' ), 'dashicons-editor-expand', 'content' ),
			'html'             => array( \__( 'Custom HTML', 'woo-custom-email-templates' ), 'dashicons-editor-code', 'content' ),
			'order_details'    => array( \__( 'Order Details', 'woo-custom-email-templates' ), 'dashicons-cart', 'woocommerce' ),
			'order_totals'     => array( \__( 'Order Totals', 'woo-custom-email-templates' ), 'dashicons-money-alt', 'woocommerce' ),
			'customer_details' => array( \__( 'Customer Details', 'woo-custom-email-templates' ), 'dashicons-admin-users', 'woocommerce' ),
			'footer'           => array( \__( 'Footer', 'woo-custom-email-templates' ), 'dashicons-align-center', 'layout' ),
		);
	}

	/**
	 * Whether a block type is known.
	 *
	 * @param string $type Block type.
	 */
	public static function exists( string $type ): bool {
		$registry = \apply_filters( 'wcem_template_blocks', self::registry() );
		return isset( $registry[ $type ] );
	}

	/**
	 * Default settings per block type.
	 *
	 * @param string $type Block type.
	 * @return array<string, mixed>
	 */
	public static function defaults( string $type ): array {
		$defaults = array(
			'header'           => array(
				'logo_id'    => 0,
				'logo_url'   => '',
				'logo_width' => 140,
				'align'      => 'center',
				'show_name'  => 1,
				'bg_color'   => '',
			),
			'columns'          => array(
				'count' => 2,
				'col1'  => \__( 'First column. Add any rich text here.', 'woo-custom-email-templates' ),
				'col2'  => \__( 'Second column.', 'woo-custom-email-templates' ),
				'col3'  => '',
				'gap'   => 16,
				'align' => 'left',
			),
			'heading'          => array(
				'text'  => \__( 'Your order is confirmed', 'woo-custom-email-templates' ),
				'tag'   => 'h2',
				'align' => 'left',
				'size'  => '',
				'color' => '',
			),
			'text'             => array(
				'content' => \__( 'Hi {customer_first_name}, thank you for your order.', 'woo-custom-email-templates' ),
				'align'   => 'left',
			),
			'image'            => array(
				'media_id' => 0,
				'url'      => '',
				'width'    => 300,
				'align'    => 'center',
				'link'     => '',
				'alt'      => '',
			),
			'button'           => array(
				'text'       => \__( 'View Order', 'woo-custom-email-templates' ),
				'url'        => '{view_order_url}',
				'align'      => 'left',
				'full_width' => 0,
				'bg_color'   => '',
				'text_color' => '',
			),
			'divider'          => array(
				'color'     => '#e4e4e7',
				'thickness' => 1,
			),
			'spacer'           => array(
				'height' => 24,
			),
			'html'             => array(
				'content' => '',
			),
			'order_details'    => array(
				'title'    => \__( 'Order Details', 'woo-custom-email-templates' ),
				'show_sku' => 0,
			),
			'order_totals'     => array(
				'title' => \__( 'Order Totals', 'woo-custom-email-templates' ),
			),
			'customer_details' => array(
				'title'         => \__( 'Customer Details', 'woo-custom-email-templates' ),
				'show_billing'  => 1,
				'show_shipping' => 1,
			),
			'footer'           => array(
				'text'        => \sprintf( '{site_name} · %s', \__( 'All rights reserved.', 'woo-custom-email-templates' ) ),
				'show_social' => 0,
				'facebook'    => '',
				'instagram'   => '',
				'twitter'     => '',
				'linkedin'    => '',
				'youtube'     => '',
				'pinterest'   => '',
				'bg_color'    => '',
				'text_color'  => '',
			),
		);

		return $defaults[ $type ] ?? array();
	}

	/**
	 * Sanitizes raw settings for one block type against its schema.
	 *
	 * @param string $type Block type.
	 * @param array  $raw  Untrusted settings.
	 * @return array<string, mixed>
	 */
	public static function sanitize_settings( string $type, array $raw ): array {
		$defaults = self::defaults( $type );
		$clean    = array();

		$colors = array( 'color', 'bg_color', 'text_color' );
		$ints   = array( 'logo_id', 'logo_width', 'media_id', 'width', 'thickness', 'height', 'size', 'gap' );
		$bools  = array( 'show_name', 'full_width', 'show_sku', 'show_billing', 'show_shipping', 'show_social' );
		$urls   = array( 'url', 'logo_url', 'link', 'facebook', 'instagram', 'twitter', 'linkedin', 'youtube', 'pinterest' );
		$rich   = array( 'content', 'text', 'col1', 'col2', 'col3' );

		foreach ( $defaults as $key => $default ) {
			$value = $raw[ $key ] ?? $default;

			if ( \in_array( $key, $colors, true ) ) {
				$clean[ $key ] = '' === $value ? '' : ( \sanitize_hex_color( $value ) ?: $default );
			} elseif ( \in_array( $key, $ints, true ) ) {
				$clean[ $key ] = \absint( $value );
			} elseif ( \in_array( $key, $bools, true ) ) {
				$clean[ $key ] = empty( $value ) ? 0 : 1;
			} elseif ( \in_array( $key, $urls, true ) ) {
				// Dynamic tags like {view_order_url} are not valid URLs yet; keep as text, escape on output.
				$clean[ $key ] = \sanitize_text_field( $value );
			} elseif ( 'count' === $key ) {
				$clean[ $key ] = \in_array( (int) $value, array( 2, 3 ), true ) ? (int) $value : 2;
			} elseif ( 'tag' === $key ) {
				$clean[ $key ] = \in_array( $value, array( 'h1', 'h2', 'h3' ), true ) ? $value : 'h2';
			} elseif ( 'align' === $key ) {
				$clean[ $key ] = \in_array( $value, array( 'left', 'center', 'right' ), true ) ? $value : 'left';
			} elseif ( \in_array( $key, $rich, true ) && 'html' === $type ) {
				$clean[ $key ] = self::sanitize_html( (string) $value );
			} elseif ( \in_array( $key, $rich, true ) ) {
				$clean[ $key ] = \wp_kses_post( (string) $value );
			} else {
				$clean[ $key ] = \sanitize_text_field( $value );
			}
		}

		return $clean;
	}

	/**
	 * Narrower-than-wp_kses_post allow-list for the raw HTML block: table
	 * markup and inline styles email clients need, no scripts or iframes.
	 *
	 * @param string $html Raw HTML.
	 */
	public static function sanitize_html( string $html ): string {
		if ( \current_user_can( 'unfiltered_html' ) ) {
			return $html;
		}

		$global = array(
			'style' => true,
			'class' => true,
			'id'    => true,
			'align' => true,
			'dir'   => true,
		);
		$cell   = \array_merge(
			$global,
			array(
				'colspan' => true,
				'rowspan' => true,
				'width'   => true,
				'height'  => true,
				'valign'  => true,
				'bgcolor' => true,
			)
		);

		return \wp_kses(
			$html,
			array(
				'table'  => \array_merge(
					$cell,
					array(
						'border'      => true,
						'cellpadding' => true,
						'cellspacing' => true,
						'role'        => true,
					)
				),
				'tr'     => $cell,
				'td'     => $cell,
				'th'     => $cell,
				'tbody'  => $global,
				'thead'  => $global,
				'div'    => $global,
				'span'   => $global,
				'p'      => $global,
				'a'      => \array_merge(
					$global,
					array(
						'href'   => true,
						'title'  => true,
						'target' => true,
						'rel'    => true,
					)
				),
				'img'    => \array_merge(
					$global,
					array(
						'src'    => true,
						'alt'    => true,
						'width'  => true,
						'height' => true,
					)
				),
				'h1'     => $global,
				'h2'     => $global,
				'h3'     => $global,
				'ul'     => $global,
				'ol'     => $global,
				'li'     => $global,
				'strong' => $global,
				'b'      => $global,
				'em'     => $global,
				'i'      => $global,
				'br'     => array(),
				'hr'     => $global,
			)
		);
	}

	/* ---------------------------------------------------------------------
	 * Rendering
	 * ------------------------------------------------------------------ */

	/**
	 * Renders one block to a <tr> row (or rows) for the outer content table.
	 *
	 * @param array $block   [ type, settings ].
	 * @param array $styles  Resolved global styles.
	 * @param array $context Tag-resolution context ( order, user ).
	 */
	public static function render( array $block, array $styles, array $context ): string {
		$type     = $block['type'] ?? '';
		$settings = \wp_parse_args( $block['settings'] ?? array(), self::defaults( $type ) );

		$method = 'render_' . $type;

		if ( 'header' !== $type && 'footer' !== $type && \method_exists( __CLASS__, $method ) ) {
			return self::cell( self::$method( $settings, $styles, $context ), $styles );
		}

		if ( \method_exists( __CLASS__, $method ) ) {
			return self::$method( $settings, $styles, $context );
		}

		return (string) \apply_filters( 'wcem_custom_email_block', '', $type, $settings, $styles, $context );
	}

	/**
	 * Wraps inner HTML in a padded table row, the standard content cell.
	 *
	 * @param string $inner  Inner HTML.
	 * @param array  $styles Resolved global styles.
	 */
	private static function cell( string $inner, array $styles ): string {
		if ( '' === \trim( $inner ) ) {
			return '';
		}

		return \sprintf(
			'<tr><td style="padding:8px %1$dpx;font-family:%2$s;color:%3$s;font-size:%4$dpx;line-height:%5$s;">%6$s</td></tr>',
			(int) $styles['padding'],
			\esc_attr( $styles['font_family'] ),
			\esc_attr( $styles['text_color'] ),
			(int) $styles['body_size'],
			\esc_attr( $styles['line_height'] ),
			$inner
		);
	}

	/**
	 * Inlines a link colour onto every anchor that does not already carry a
	 * style attribute.
	 *
	 * Email clients strip <style> blocks (Gmail most aggressively) and do not
	 * inherit colour into <a>, so the global `link_color` setting has to be
	 * written onto each anchor to have any effect at all.
	 *
	 * @param string $html  Block HTML.
	 * @param string $color Hex colour.
	 */
	public static function color_links( string $html, string $color ): string {
		if ( '' === $color || false === \stripos( $html, '<a' ) ) {
			return $html;
		}

		return (string) \preg_replace(
			'/<a\b(?![^>]*\bstyle\s*=)([^>]*)>/i',
			'<a$1 style="color:' . \esc_attr( $color ) . ';">',
			$html
		);
	}

	private static function render_heading( array $s, array $styles ): string {
		$tag   = \in_array( $s['tag'], array( 'h1', 'h2', 'h3' ), true ) ? $s['tag'] : 'h2';
		$size  = $s['size'] ? \absint( $s['size'] ) : $styles['heading_size'];
		$color = $s['color'] ? $s['color'] : $styles['heading_color'];

		return \sprintf(
			'<%1$s style="margin:0;text-align:%2$s;font-family:%3$s;font-size:%4$dpx;color:%5$s;font-weight:600;">%6$s</%1$s>',
			$tag,
			\esc_attr( $s['align'] ),
			\esc_attr( $styles['font_family'] ),
			(int) $size,
			\esc_attr( $color ),
			\esc_html( $s['text'] )
		);
	}

	private static function render_text( array $s, array $styles ): string {
		$content = self::color_links( \wpautop( $s['content'] ), (string) $styles['link_color'] );

		return \sprintf( '<div style="text-align:%s;">%s</div>', \esc_attr( $s['align'] ), $content );
	}

	private static function render_columns( array $s, array $styles ): string {
		$count = \in_array( (int) $s['count'], array( 2, 3 ), true ) ? (int) $s['count'] : 2;
		$gap   = (int) $s['gap'];
		$cells = '';

		for ( $i = 1; $i <= $count; $i++ ) {
			$content = self::color_links( \wpautop( (string) ( $s[ 'col' . $i ] ?? '' ) ), (string) $styles['link_color'] );

			$cells .= \sprintf(
				'<td width="%1$d%%" style="vertical-align:top;text-align:%2$s;padding:0 %3$dpx;">%4$s</td>',
				(int) \floor( 100 / $count ),
				\esc_attr( $s['align'] ),
				(int) \round( $gap / 2 ),
				$content
			);
		}

		/*
		 * A plain <table> with percentage-width cells is the only column
		 * construct Outlook's Word renderer handles reliably; flexbox and
		 * CSS grid are not options in email.
		 */
		return \sprintf(
			'<table role="presentation" width="100%%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;"><tr>%s</tr></table>',
			$cells
		);
	}

	private static function render_image( array $s ): string {
		$src = $s['media_id'] ? \wp_get_attachment_image_url( $s['media_id'], 'large' ) : $s['url'];

		if ( ! $src ) {
			return '';
		}

		$img = \sprintf(
			'<img src="%s" alt="%s" width="%d" style="display:block;max-width:100%%;height:auto;border:0;margin:%s;" />',
			\esc_url( $src ),
			\esc_attr( $s['alt'] ),
			(int) $s['width'],
			'center' === $s['align'] ? '0 auto' : '0'
		);

		if ( $s['link'] ) {
			$img = \sprintf( '<a href="%s" target="_blank" rel="noopener">%s</a>', \esc_url( $s['link'] ), $img );
		}

		return \sprintf( '<div style="text-align:%s;">%s</div>', \esc_attr( $s['align'] ), $img );
	}

	private static function render_button( array $s, array $styles, array $context ): string {
		$url = \esc_url( EmailTags::replace( $s['url'], $context, false ) );
		$bg  = $s['bg_color'] ? $s['bg_color'] : $styles['button_bg'];
		$fg  = $s['text_color'] ? $s['text_color'] : $styles['button_text'];

		$link = \sprintf(
			'<a href="%1$s" target="_blank" rel="noopener" style="display:inline-block;background:%2$s;color:%3$s;text-decoration:none;font-family:%4$s;font-size:%5$dpx;font-weight:600;padding:12px 28px;border-radius:%6$dpx;%7$s">%8$s</a>',
			$url,
			\esc_attr( $bg ),
			\esc_attr( $fg ),
			\esc_attr( $styles['font_family'] ),
			(int) $styles['button_size'],
			(int) $styles['radius'],
			$s['full_width'] ? 'display:block;text-align:center;' : '',
			\esc_html( $s['text'] )
		);

		return \sprintf( '<div style="text-align:%s;">%s</div>', \esc_attr( $s['align'] ), $link );
	}

	private static function render_divider( array $s ): string {
		return \sprintf(
			'<hr style="border:none;border-top:%1$dpx solid %2$s;margin:0;" />',
			(int) $s['thickness'],
			\esc_attr( $s['color'] )
		);
	}

	private static function render_spacer( array $s ): string {
		return \sprintf( '<div style="line-height:%1$dpx;font-size:1px;">&nbsp;</div>', (int) $s['height'] );
	}

	private static function render_html( array $s ): string {
		return $s['content'];
	}

	private static function render_header( array $s, array $styles ): string {
		$logo_src = $s['logo_id'] ? \wp_get_attachment_image_url( $s['logo_id'], 'medium' ) : $s['logo_url'];
		$bg       = $s['bg_color'] ? $s['bg_color'] : $styles['content_bg'];

		$inner = '';

		if ( $logo_src ) {
			$inner .= \sprintf(
				'<img src="%s" alt="%s" width="%d" style="display:block;max-width:100%%;height:auto;border:0;margin:%s;" />',
				\esc_url( $logo_src ),
				\esc_attr( \get_bloginfo( 'name' ) ),
				(int) $s['logo_width'],
				'center' === $s['align'] ? '0 auto' : '0'
			);
		} elseif ( $s['show_name'] ) {
			$inner .= \sprintf(
				'<span style="font-family:%s;font-size:22px;font-weight:700;color:%s;">%s</span>',
				\esc_attr( $styles['font_family'] ),
				\esc_attr( $styles['heading_color'] ),
				\esc_html( \get_bloginfo( 'name' ) )
			);
		}

		return \sprintf(
			'<tr><td align="%1$s" style="padding:%2$dpx;background:%3$s;">%4$s</td></tr>',
			\esc_attr( $s['align'] ),
			(int) $styles['padding'],
			\esc_attr( $bg ),
			$inner
		);
	}

	private static function render_footer( array $s, array $styles, array $context ): string {
		$bg   = $s['bg_color'] ? $s['bg_color'] : $styles['footer_bg'];
		$fg   = $s['text_color'] ? $s['text_color'] : $styles['footer_text'];
		$text = self::color_links( EmailTags::replace( $s['text'], $context ), (string) $fg );

		$social_urls = array(
			'facebook'  => \__( 'Facebook', 'woo-custom-email-templates' ),
			'instagram' => \__( 'Instagram', 'woo-custom-email-templates' ),
			'twitter'   => \__( 'X / Twitter', 'woo-custom-email-templates' ),
			'linkedin'  => \__( 'LinkedIn', 'woo-custom-email-templates' ),
			'youtube'   => \__( 'YouTube', 'woo-custom-email-templates' ),
			'pinterest' => \__( 'Pinterest', 'woo-custom-email-templates' ),
		);

		$social = '';

		if ( $s['show_social'] ) {
			$links = array();

			foreach ( $social_urls as $key => $label ) {
				if ( ! empty( $s[ $key ] ) ) {
					$links[] = \sprintf(
						'<a href="%s" target="_blank" rel="noopener" style="color:%s;text-decoration:underline;margin:0 6px;">%s</a>',
						\esc_url( $s[ $key ] ),
						\esc_attr( $fg ),
						\esc_html( $label )
					);
				}
			}

			if ( $links ) {
				$social = '<div style="margin-top:10px;">' . \implode( '', $links ) . '</div>';
			}
		}

		return \sprintf(
			'<tr><td align="center" style="padding:%1$dpx;background:%2$s;font-family:%3$s;font-size:12px;color:%4$s;">%5$s%6$s</td></tr>',
			(int) $styles['padding'],
			\esc_attr( $bg ),
			\esc_attr( $styles['font_family'] ),
			\esc_attr( $fg ),
			$text,
			$social
		);
	}

	/**
	 * Whether a block with no order behind it may show demo content.
	 *
	 * True only in an editor preview. In a live send there is no such thing
	 * as harmless placeholder order data: an Order Details block placed in an
	 * email that carries a user rather than an order (New Account, Reset
	 * Password) would otherwise post a fabricated basket and address to a real
	 * customer. Those blocks render nothing instead, and cell() drops the row.
	 *
	 * @param array $context Rendering context.
	 */
	private static function may_show_sample( array $context ): bool {
		return ! empty( $context['sample'] );
	}

	private static function render_order_details( array $s, array $styles, array $context ): string {
		$order = $context['order'] ?? null;

		if ( ! $order instanceof WC_Order && ! self::may_show_sample( $context ) ) {
			return '';
		}

		$rows = '';

		if ( $order instanceof WC_Order ) {
			foreach ( $order->get_items() as $item ) {
				$product = $item->get_product();
				$sku     = ( $s['show_sku'] && $product ) ? \sprintf( ' <span style="color:#71717a;">(%s)</span>', \esc_html( $product->get_sku() ) ) : '';

				$rows .= \sprintf(
					'<tr><td style="padding:8px 0;border-bottom:1px solid #e4e4e7;">%1$s%2$s × %3$d</td><td style="padding:8px 0;border-bottom:1px solid #e4e4e7;text-align:right;">%4$s</td></tr>',
					\esc_html( $item->get_name() ),
					$sku,
					$item->get_quantity(),
					\wp_kses_post( $order->get_formatted_line_subtotal( $item ) )
				);
			}
		} else {
			$rows = self::sample_order_rows();
		}

		return \sprintf(
			'<div>%1$s<table role="presentation" width="100%%" style="border-collapse:collapse;margin-top:8px;font-family:%2$s;font-size:%3$dpx;color:%4$s;">%5$s</table></div>',
			$s['title'] ? \sprintf( '<h3 style="margin:0 0 4px;font-size:16px;color:%s;">%s</h3>', \esc_attr( $styles['heading_color'] ), \esc_html( $s['title'] ) ) : '',
			\esc_attr( $styles['font_family'] ),
			(int) $styles['body_size'] - 1,
			\esc_attr( $styles['text_color'] ),
			$rows
		);
	}

	private static function render_order_totals( array $s, array $styles, array $context ): string {
		$order = $context['order'] ?? null;

		if ( ! $order instanceof WC_Order && ! self::may_show_sample( $context ) ) {
			return '';
		}

		$rows = '';

		if ( $order instanceof WC_Order ) {
			foreach ( $order->get_order_item_totals() as $total ) {
				$rows .= \sprintf(
					'<tr><td style="padding:4px 0;">%1$s</td><td style="padding:4px 0;text-align:right;">%2$s</td></tr>',
					\esc_html( $total['label'] ),
					\wp_kses_post( $total['value'] )
				);
			}
		} else {
			$rows = '<tr><td style="padding:4px 0;">' . \esc_html__( 'Subtotal:', 'woo-custom-email-templates' ) . '</td><td style="padding:4px 0;text-align:right;">$120.00</td></tr>'
				. '<tr><td style="padding:4px 0;">' . \esc_html__( 'Shipping:', 'woo-custom-email-templates' ) . '</td><td style="padding:4px 0;text-align:right;">$8.00</td></tr>'
				. '<tr><td style="padding:4px 0;"><strong>' . \esc_html__( 'Total:', 'woo-custom-email-templates' ) . '</strong></td><td style="padding:4px 0;text-align:right;"><strong>$128.00</strong></td></tr>';
		}

		return \sprintf(
			'<div>%1$s<table role="presentation" width="100%%" style="border-collapse:collapse;font-family:%2$s;font-size:%3$dpx;color:%4$s;">%5$s</table></div>',
			$s['title'] ? \sprintf( '<h3 style="margin:0 0 4px;font-size:16px;color:%s;">%s</h3>', \esc_attr( $styles['heading_color'] ), \esc_html( $s['title'] ) ) : '',
			\esc_attr( $styles['font_family'] ),
			(int) $styles['body_size'] - 1,
			\esc_attr( $styles['text_color'] ),
			$rows
		);
	}

	private static function render_customer_details( array $s, array $styles, array $context ): string {
		$order = $context['order'] ?? null;

		if ( ! $order instanceof WC_Order && ! self::may_show_sample( $context ) ) {
			return '';
		}

		$cols = '';

		$sample_address = '123 Sample St<br>Springfield, IL 62704<br>United States';

		$billing  = $order instanceof WC_Order ? \wp_kses_post( $order->get_formatted_billing_address( \__( 'N/A', 'woo-custom-email-templates' ) ) ) : $sample_address;
		$shipping = $order instanceof WC_Order ? \wp_kses_post( $order->get_formatted_shipping_address( \__( 'Same as billing address', 'woo-custom-email-templates' ) ) ) : $sample_address;

		if ( $s['show_billing'] ) {
			$cols .= \sprintf( '<td style="padding-right:16px;vertical-align:top;"><strong>%s</strong><br>%s</td>', \esc_html__( 'Billing Address', 'woo-custom-email-templates' ), $billing );
		}

		if ( $s['show_shipping'] ) {
			$cols .= \sprintf( '<td style="vertical-align:top;"><strong>%s</strong><br>%s</td>', \esc_html__( 'Shipping Address', 'woo-custom-email-templates' ), $shipping );
		}

		return \sprintf(
			'<div>%1$s<table role="presentation" width="100%%" style="font-family:%2$s;font-size:%3$dpx;color:%4$s;"><tr>%5$s</tr></table></div>',
			$s['title'] ? \sprintf( '<h3 style="margin:0 0 8px;font-size:16px;color:%s;">%s</h3>', \esc_attr( $styles['heading_color'] ), \esc_html( $s['title'] ) ) : '',
			\esc_attr( $styles['font_family'] ),
			(int) $styles['body_size'] - 1,
			\esc_attr( $styles['text_color'] ),
			$cols
		);
	}

	/**
	 * Placeholder rows for the order-details block when there is no order
	 * to render (template editor preview with sample data).
	 */
	private static function sample_order_rows(): string {
		$items = array(
			array( \__( 'Classic T-Shirt', 'woo-custom-email-templates' ), 2, '$40.00' ),
			array( \__( 'Canvas Tote Bag', 'woo-custom-email-templates' ), 1, '$18.00' ),
		);

		$rows = '';

		foreach ( $items as list( $name, $qty, $price ) ) {
			$rows .= \sprintf(
				'<tr><td style="padding:8px 0;border-bottom:1px solid #e4e4e7;">%1$s × %2$d</td><td style="padding:8px 0;border-bottom:1px solid #e4e4e7;text-align:right;">%3$s</td></tr>',
				\esc_html( $name ),
				$qty,
				\esc_html( $price )
			);
		}

		return $rows;
	}
}
