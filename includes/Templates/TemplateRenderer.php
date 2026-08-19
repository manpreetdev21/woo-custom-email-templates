<?php
/**
 * Assembles a template's blocks + global styles into a full, table-based
 * HTML email document, and resolves the subject line.
 *
 * @package Woo_Custom_Email_Templates
 */

namespace WCEM\Templates;

use WCEM\Builder\Blocks;
use WCEM\Email\EmailTags;

defined( 'ABSPATH' ) || exit;

final class TemplateRenderer {

	/**
	 * Renders a template to a complete HTML document.
	 *
	 * @param array $template Template data from TemplateRepository::get().
	 * @param array $context  [ order => WC_Order|null, user => WP_User|null ].
	 */
	public static function render( array $template, array $context = array() ): string {
		/**
		 * Fires before a template is rendered.
		 *
		 * @param array $template Template data.
		 * @param array $context  Rendering context.
		 */
		\do_action( 'wcem_before_render_email', $template, $context );

		$styles = \wp_parse_args( $template['styles'] ?? array(), TemplateRepository::default_styles() );
		$blocks = $template['blocks'] ?? array();

		$rows = '';

		foreach ( $blocks as $block ) {
			$rows .= Blocks::render( (array) $block, $styles, $context );
		}

		if ( '' === \trim( $rows ) ) {
			$rows = \sprintf( '<tr><td style="padding:%dpx;">&nbsp;</td></tr>', (int) $styles['padding'] );
		}

		/*
		 * Resolve {tags} once, over the whole assembled body, rather than in
		 * each block's renderer — every block (including Custom HTML and any
		 * added later) then supports dynamic data for free. Values are escaped
		 * here; blocks that must resolve a tag *before* escaping it, such as
		 * the button's URL, do so themselves and simply have nothing left to
		 * substitute by this point.
		 */
		$rows      = EmailTags::replace( $rows, $context );
		$preheader = self::preheader( (string) ( $template['preview_text'] ?? '' ), $context );

		\ob_start();
		?>
<!doctype html>
<html xmlns="http://www.w3.org/1999/xhtml" lang="<?php echo \esc_attr( \get_locale() ); ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo \esc_html( \get_bloginfo( 'name' ) ); ?></title>
<!--[if mso]><style>table{border-collapse:collapse;}</style><![endif]-->
</head>
<body style="margin:0;padding:0;background:<?php echo \esc_attr( $styles['bg_color'] ); ?>;">
<?php echo $preheader; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in preheader(). ?>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:<?php echo \esc_attr( $styles['bg_color'] ); ?>;">
<tr>
<td align="center" style="padding:24px 12px;">
<table role="presentation" width="<?php echo (int) $styles['width']; ?>" cellpadding="0" cellspacing="0" style="width:<?php echo (int) $styles['width']; ?>px;max-width:100%;background:<?php echo \esc_attr( $styles['content_bg'] ); ?>;border-radius:<?php echo (int) $styles['radius']; ?>px;overflow:hidden;">
<?php echo $rows; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built entirely from Blocks::render(), which escapes every dynamic value it inserts. ?>
</table>
</td>
</tr>
</table>
</body>
</html>
		<?php
		$html = (string) \ob_get_clean();

		/**
		 * Filters the rendered email HTML.
		 *
		 * @param string $html     Rendered document.
		 * @param array  $template Template data.
		 * @param array  $context  Rendering context.
		 */
		return (string) \apply_filters( 'wcem_after_render_email', $html, $template, $context );
	}

	/**
	 * The hidden preheader line — the grey text an inbox shows next to the
	 * subject. Padded with zero-width joiners so the client does not pull
	 * the first line of real body copy in after it.
	 *
	 * @param string $text    Raw preview text, may contain {tags}.
	 * @param array  $context Rendering context.
	 */
	private static function preheader( string $text, array $context ): string {
		$text = \trim( EmailTags::replace( $text, $context, false ) );

		if ( '' === $text ) {
			return '';
		}

		return \sprintf(
			'<div style="display:none;max-height:0;overflow:hidden;mso-hide:all;font-size:1px;line-height:1px;color:transparent;">%s%s</div>',
			\esc_html( $text ),
			\str_repeat( '&#847;&zwnj;&nbsp;', 30 )
		);
	}

	/**
	 * Resolves the subject line, falling back to WooCommerce's own subject
	 * when the template leaves it blank.
	 *
	 * @param array  $template   Template data.
	 * @param string $wc_subject WooCommerce's computed subject.
	 * @param array  $context    Tag-resolution context.
	 */
	public static function subject( array $template, string $wc_subject, array $context = array() ): string {
		$subject = \trim( (string) ( $template['subject'] ?? '' ) );

		if ( '' === $subject ) {
			return $wc_subject;
		}

		return EmailTags::replace( $subject, $context, false );
	}
}
