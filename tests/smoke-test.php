<?php
/**
 * Standalone smoke test for the pure-logic classes (block sanitization and
 * rendering, dynamic tags, template JSON storage). Runs with lightweight
 * stand-ins for the handful of WordPress functions these classes call,
 * rather than requiring a full WordPress + WooCommerce install — the CRUD
 * layer (TemplateRepository::save/get, EmailManager) is thin wrapping over
 * wp_insert_post()/get_option() and isn't worth testing without the real
 * database those functions hit.
 *
 * Usage:  php tests/smoke-test.php
 *
 * @package Woo_Custom_Email_Templates
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( "Run this from the command line.\n" );
}

// Satisfies every file's `defined( 'ABSPATH' ) || exit;` direct-access guard.
define( 'ABSPATH', __DIR__ . '/' );

/* -------------------------------------------------------------------------
 * Minimal WordPress function stand-ins
 * ---------------------------------------------------------------------- */

function __( $text ) { return $text; }
function esc_html( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES ); }
function esc_attr( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES ); }
function esc_html__( $text ) { return $text; }
function esc_url( $url ) { return (string) $url; }
function sanitize_text_field( $text ) { return trim( strip_tags( (string) $text ) ); }
function sanitize_textarea_field( $text ) { return trim( (string) $text ); }
function sanitize_key( $key ) { return preg_replace( '/[^a-z0-9_-]/', '', strtolower( (string) $key ) ); }
function sanitize_hex_color( $color ) { return preg_match( '/^#([A-Fa-f0-9]{3}){1,2}$/', (string) $color ) ? $color : ''; }
function absint( $n ) { return abs( (int) $n ); }
function wp_parse_args( $args, $defaults ) { return array_merge( $defaults, (array) $args ); }
function wp_json_encode( $data, $opts = 0 ) { return json_encode( $data, $opts ); }
function current_user_can( $cap ) { return false; }
function wpautop( $text ) { return '<p>' . trim( (string) $text ) . '</p>'; }
function get_bloginfo( $key = '' ) { return 'Test Store'; }
function home_url( $path = '/' ) { return 'https://example.test' . $path; }
function get_locale() { return 'en_US'; }
function date_i18n( $format ) { return date( (string) $format ); }
function get_option( $key, $default = false ) { return 'date_format' === $key ? 'Y-m-d' : $default; }
function wp_generate_password( $len = 12, $special = true ) { return substr( md5( (string) mt_rand() ), 0, $len ); }
function apply_filters( $tag, $value ) { return $value; }
function do_action( $tag ) {}
function wp_strip_all_tags( $text, $breaks = false ) { return trim( strip_tags( (string) $text ) ); }
function function_exists_stub() { return false; }

function wp_kses( $html, $allowed_html ) {
	$tags = array_keys( $allowed_html );
	$spec = '<' . implode( '><', $tags ) . '>';
	return strip_tags( (string) $html, $spec );
}

function wp_kses_post( $html ) {
	return wp_kses( $html, array_fill_keys( array( 'p', 'a', 'strong', 'em', 'b', 'i', 'br', 'ul', 'ol', 'li', 'span', 'div' ), array() ) );
}

require_once __DIR__ . '/../includes/Core/Autoloader.php';

WCEM\Core\Autoloader::register( 'WCEM\\', __DIR__ . '/../includes/' );

use WCEM\Builder\Blocks;
use WCEM\Email\EmailTags;
use WCEM\Templates\StarterTemplates;
use WCEM\Templates\TemplateRenderer;
use WCEM\Templates\TemplateRepository;

$failures = 0;
$checks   = 0;

/**
 * Asserts a condition and reports it.
 *
 * @param string $label     What is being checked.
 * @param bool   $condition Result.
 * @param string $detail    Extra context shown on failure.
 */
function wcem_check( $label, $condition, $detail = '' ) {
	global $failures, $checks;

	++$checks;

	if ( $condition ) {
		echo "  PASS  $label\n";
		return;
	}

	++$failures;
	echo "  FAIL  $label" . ( $detail ? "\n        $detail" : '' ) . "\n";
}

echo "\nWooCommerce Custom Email Templates — smoke test\n";
echo str_repeat( '-', 60 ) . "\n";

/* -------------------------------------------------------------------------
 * Autoloading
 * ---------------------------------------------------------------------- */

wcem_check( 'Autoloader: PSR-4 class resolves', class_exists( TemplateRepository::class ) );
wcem_check( 'Autoloader: nested namespace resolves', class_exists( Blocks::class ) );

/* -------------------------------------------------------------------------
 * Block sanitization
 * ---------------------------------------------------------------------- */

$heading = Blocks::sanitize_settings(
	'heading',
	array(
		'text'  => 'Hello <script>alert(1)</script> {customer_first_name}',
		'tag'   => 'h9',        // Invalid — must fall back to h2.
		'align' => 'sideways',  // Invalid — must fall back to left.
		'size'  => '999abc',
		'color' => 'not-a-color',
	)
);

wcem_check( 'Heading: unknown tag falls back to h2', 'h2' === $heading['tag'] );
wcem_check( 'Heading: unknown align falls back to left', 'left' === $heading['align'] );
wcem_check( 'Heading: size coerced to an integer', 999 === $heading['size'] );
wcem_check( 'Heading: invalid hex color rejected', '' === $heading['color'] );
wcem_check( 'Heading: script tag stripped from text', false === strpos( $heading['text'], '<script' ) );
wcem_check( 'Heading: dynamic tag left intact for later replacement', false !== strpos( $heading['text'], '{customer_first_name}' ) );

$html_block = Blocks::sanitize_settings( 'html', array( 'content' => '<table><tr><td>ok</td></tr></table><script>evil()</script>' ) );
wcem_check( 'HTML block: table markup preserved', false !== strpos( $html_block['content'], '<table>' ) );
wcem_check( 'HTML block: script tag stripped', false === strpos( $html_block['content'], '<script' ) );

wcem_check( 'Unknown block type rejected', null === TemplateRepository::sanitize_block( array( 'type' => 'not_a_real_block' ) ) );
wcem_check( 'Known block type accepted', null !== TemplateRepository::sanitize_block( array( 'type' => 'divider' ) ) );

/* -------------------------------------------------------------------------
 * Columns block
 * ---------------------------------------------------------------------- */

$columns = Blocks::sanitize_settings(
	'columns',
	array(
		'count' => 7, // Out of range — must clamp to 2.
		'col1'  => '<strong>Kept</strong><script>bad()</script>',
		'col2'  => 'Plain',
	)
);

wcem_check( 'Columns: out-of-range count clamped to 2', 2 === $columns['count'] );
wcem_check( 'Columns: rich markup preserved in a column', false !== strpos( $columns['col1'], '<strong>' ) );
wcem_check( 'Columns: script stripped from a column', false === strpos( $columns['col1'], '<script' ) );

$columns_html = Blocks::render(
	array(
		'type'     => 'columns',
		'settings' => array(
			'count' => 3,
			'col1'  => 'One',
			'col2'  => 'Two',
			'col3'  => 'Three',
		),
	),
	TemplateRepository::default_styles(),
	array( 'order' => null, 'user' => null )
);

wcem_check( 'Columns: renders three table cells', 3 === substr_count( $columns_html, '<td width="33%"' ), $columns_html );

/* -------------------------------------------------------------------------
 * Link colouring (the global link_color setting)
 * ---------------------------------------------------------------------- */

$coloured = Blocks::color_links( '<p>See <a href="#">this</a></p>', '#ff0000' );
wcem_check( 'Links: global link colour inlined onto a bare anchor', false !== strpos( $coloured, 'style="color:#ff0000;"' ), $coloured );

$already = Blocks::color_links( '<a href="#" style="color:#00ff00;">x</a>', '#ff0000' );
wcem_check( 'Links: an anchor with its own style is left alone', false === strpos( $already, '#ff0000' ), $already );

/* -------------------------------------------------------------------------
 * Global styles
 * ---------------------------------------------------------------------- */

$styles = TemplateRepository::sanitize_styles(
	array(
		'bg_color'    => 'garbage',
		'width'       => '640',
		'line_height' => '99', // Out of the 0-4 sane range.
	)
);

wcem_check( 'Styles: invalid color falls back to default', $styles['bg_color'] === TemplateRepository::default_styles()['bg_color'] );
wcem_check( 'Styles: numeric width preserved', 640 === $styles['width'] );
wcem_check( 'Styles: out-of-range line-height falls back to default', $styles['line_height'] === TemplateRepository::default_styles()['line_height'] );

/* -------------------------------------------------------------------------
 * Body JSON round-trip
 * ---------------------------------------------------------------------- */

$json = TemplateRepository::sanitize_body(
	array(
		'blocks' => array(
			array( 'type' => 'heading', 'settings' => array( 'text' => 'Hi' ), 'origin' => '42' ),
			array( 'type' => 'bogus' ),
		),
		'styles' => array( 'width' => '500' ),
	)
);

$decoded = TemplateRepository::decode_body( $json );

wcem_check( 'Body round-trip: only the valid block survives', 1 === count( $decoded['blocks'] ) );
wcem_check( 'Body round-trip: styles merged over defaults', 500 === $decoded['styles']['width'] && isset( $decoded['styles']['button_bg'] ) );
wcem_check( 'Body round-trip: component origin preserved', 42 === $decoded['blocks'][0]['origin'] );

/* -------------------------------------------------------------------------
 * Dynamic tags
 * ---------------------------------------------------------------------- */

$tagged = EmailTags::replace( 'Welcome to {site_name}, {customer_first_name}!', array( 'order' => null, 'user' => null ) );

wcem_check( 'Tags: {site_name} resolved', false !== strpos( $tagged, 'Test Store' ) );
wcem_check( 'Tags: {customer_first_name} resolved to blank without an order', false === strpos( $tagged, '{customer_first_name}' ) );

/* Sample mode fills the blanks; a live context must never get demo data. */
$live_vals   = EmailTags::values( array( 'order' => null, 'user' => null ) );
$sample_vals = EmailTags::values( array( 'order' => null, 'user' => null, 'sample' => true ) );

wcem_check( 'Tags: live context leaves customer name empty', '' === $live_vals['customer_first_name'] );
wcem_check( 'Tags: live context leaves order number empty', '' === $live_vals['order_number'] );
wcem_check( 'Tags: sample context fills the customer name', 'Jane' === $sample_vals['customer_first_name'] );
wcem_check( 'Tags: sample context fills the order number', '1234' === $sample_vals['order_number'] );
wcem_check( 'Tags: sample order total matches the blocks sample rows', '$128.00' === $sample_vals['order_total'] );
wcem_check( 'Tags: sample mode does not overwrite real store values', 'Test Store' === $sample_vals['site_name'] );

$sample_text = EmailTags::replace( 'Hi {customer_first_name}, order {order_number}.', array( 'order' => null, 'user' => null, 'sample' => true ) );
wcem_check( 'Tags: sample preview reads as a real sentence', 'Hi Jane, order 1234.' === $sample_text, $sample_text );

/* -------------------------------------------------------------------------
 * Starter library
 * ---------------------------------------------------------------------- */

$library = StarterTemplates::library();

wcem_check( 'Library: ships more than one starter', count( $library ) > 1 );
wcem_check( 'Library: every entry has blocks, styles and a category', ! array_filter(
	$library,
	static fn( $t ) => empty( $t['blocks'] ) || empty( $t['styles'] ) || empty( $t['category'] )
) );

/* -------------------------------------------------------------------------
 * Full render
 * ---------------------------------------------------------------------- */

$template = array(
	'subject'      => '',
	'preview_text' => 'Your {site_name} order is on its way',
	'blocks'       => array(
		array( 'id' => 'a', 'type' => 'heading', 'settings' => array( 'text' => 'Order Confirmed' ) ),
		array( 'id' => 'b', 'type' => 'order_details', 'settings' => array( 'title' => 'Items' ) ),
		// Tags in a heading and a paragraph must resolve, not just in the footer.
		array( 'id' => 'c', 'type' => 'heading', 'settings' => array( 'text' => 'Thanks {customer_first_name}' ) ),
		array( 'id' => 'd', 'type' => 'text', 'settings' => array( 'content' => 'Order {order_number} from {site_name}.' ) ),
	),
	'styles'       => TemplateRepository::default_styles(),
);

$html = TemplateRenderer::render( $template, array( 'order' => null, 'user' => null, 'sample' => true ) );

wcem_check( 'Render: produces a full HTML document', false !== strpos( $html, '<!doctype html>' ) );
wcem_check( 'Render: heading text present', false !== strpos( $html, 'Order Confirmed' ) );
wcem_check( 'Render: order-details sample fallback present in a preview', false !== strpos( $html, 'Classic T-Shirt' ) );

/*
 * The same template in a live context with no order — what an account or
 * password-reset email looks like — must not invent order data.
 */
$live_html = TemplateRenderer::render( $template, array( 'order' => null, 'user' => null ) );

wcem_check( 'Render: live send with no order omits sample products', false === strpos( $live_html, 'Classic T-Shirt' ), 'fabricated order lines reached a live email' );
wcem_check( 'Render: live send with no order omits the sample address', false === strpos( $live_html, '123 Sample St' ) );
wcem_check( 'Render: live send with no order omits sample totals', false === strpos( $live_html, '$128.00' ) );
wcem_check( 'Render: live send still renders the real content blocks', false !== strpos( $live_html, 'Order Confirmed' ) );
wcem_check( 'Render: configured width applied', false !== strpos( $html, 'width:' . $template['styles']['width'] . 'px' ) );
wcem_check( 'Render: {site_name} resolved inside a text block', false !== strpos( $html, 'Test Store' ) );
wcem_check( 'Render: preheader emitted and hidden', false !== strpos( $html, 'Your Test Store order is on its way' ) && false !== strpos( $html, 'mso-hide:all' ) );
wcem_check(
	'Render: no unresolved {tags} survive into the email',
	! preg_match( '/\{[a-z_]+\}/', $html ),
	'found: ' . implode( ', ', array_slice( preg_match_all( '/\{[a-z_]+\}/', $html, $m ) ? $m[0] : array(), 0, 5 ) )
);

$no_preheader = TemplateRenderer::render(
	array( 'blocks' => array(), 'styles' => TemplateRepository::default_styles() ),
	array( 'order' => null, 'user' => null )
);

wcem_check( 'Render: no preheader div when preview text is blank', false === strpos( $no_preheader, 'mso-hide:all' ) );

echo str_repeat( '-', 60 ) . "\n";
echo "$checks checks, $failures failed.\n\n";

exit( $failures ? 1 : 0 );
