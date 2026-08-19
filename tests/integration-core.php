<?php
/**
 * Functional test against the live WordPress + WooCommerce install.
 *
 * Creates its own scratch records and deletes them again; existing
 * templates are only read, never written.
 */

error_reporting( E_ALL );
ini_set( 'display_errors', '1' );

define( 'WP_USE_THEMES', false );

$GLOBALS['wcem_notices'] = array();
set_error_handler(
	static function ( $no, $str, $file, $line ) {
		if ( str_contains( (string) $file, 'woo-custom-email-templates' ) ) {
			$GLOBALS['wcem_notices'][] = "[$no] $str in " . basename( $file ) . ":$line";
		}
		return false;
	}
);

/**
 * The WordPress root, four levels up from this plugin's tests directory:
 * <wp>/wp-content/plugins/woo-custom-email-templates/tests.
 */
function wcem_wp_root(): string {
	return dirname( __DIR__, 4 );
}

require_once wcem_wp_root() . '/wp-load.php';

use WCEM\Builder\Blocks;
use WCEM\Core\Plugin;
use WCEM\Email\EmailManager;
use WCEM\Email\EmailSender;
use WCEM\Templates\ComponentRepository;
use WCEM\Templates\StarterTemplates;
use WCEM\Templates\TemplateRenderer;
use WCEM\Templates\TemplateRepository;
use WCEM\Templates\Versions;
use WCEM\Tools\ImportExport;

$fail = 0;
$n    = 0;

function check( $label, $ok, $detail = '' ) {
	global $fail, $n;
	++$n;
	if ( $ok ) {
		echo "  PASS  $label\n";
		return true;
	}
	++$fail;
	echo "  FAIL  $label" . ( $detail ? "\n        " . substr( (string) $detail, 0, 300 ) : '' ) . "\n";
	return false;
}

echo "\nLive functional test — WP " . get_bloginfo( 'version' ) . " / WC " . WC_VERSION . " / PHP " . PHP_VERSION . "\n";
echo str_repeat( '-', 66 ) . "\n";

/* -- 1. Bootstrap ------------------------------------------------------ */
check( 'Autoloader resolved Plugin in a real request', class_exists( Plugin::class ) );
check( 'Legacy alias WCEM_Template_Post_Type still resolves', class_exists( 'WCEM_Template_Post_Type' ) );
check( 'Legacy alias points at the new class', is_a( TemplateRepository::class, 'WCEM_Template_Post_Type', true ) );
check( 'WooCommerce detected by Plugin::woocommerce_active()', Plugin::woocommerce_active() );
check( 'Template post type registered', post_type_exists( 'wcem_template' ) );
check( 'Component post type registered', post_type_exists( 'wcem_component' ) );
check( 'Revisions enabled on templates (version history)', post_type_supports( 'wcem_template', 'revisions' ) );

/* -- 2. Reading pre-refactor (1.0-format) data ------------------------- */
$existing = TemplateRepository::all();
check( 'Existing 1.0 templates load through the new repository', count( $existing ) > 0, count( $existing ) . ' found' );

if ( $existing ) {
	$one = $existing[0];
	check( '1.0 template decodes blocks', ! empty( $one['blocks'] ), wp_json_encode( array_keys( $one ) ) );
	check( '1.0 template gets full style set merged', isset( $one['styles']['link_color'], $one['styles']['padding'] ) );
	$html = TemplateRenderer::render( $one, array( 'order' => null, 'user' => null ) );
	check( '1.0 template renders a full document', str_contains( $html, '<!doctype html>' ) && strlen( $html ) > 500, strlen( $html ) . ' bytes' );
	check( 'Rendered output leaves no unresolved {tags}', ! preg_match( '/\{[a-z_]+\}/', $html ) );
}

/* -- 3. WooCommerce email discovery ------------------------------------ */
$emails = EmailManager::all_emails();
check( 'WooCommerce emails discovered dynamically', count( $emails ) > 0, count( $emails ) . ' found' );
echo "        ids: " . implode( ', ', array_slice( array_keys( $emails ), 0, 20 ) ) . "\n";

$customer = array_filter( array_keys( $emails ), static fn( $id ) => EmailManager::is_customer_email( $id ) );
check( 'Customer emails split out from admin emails', count( $customer ) > 0 && count( $customer ) < count( $emails ) );

foreach ( array( 'new_order', 'customer_processing_order', 'customer_completed_order', 'customer_invoice', 'customer_new_account', 'customer_reset_password' ) as $want ) {
	check( "Email type present: $want", isset( $emails[ $want ] ) );
}

/* -- 4. Block rendering with real WooCommerce loaded ------------------- */
$styles = TemplateRepository::default_styles();
$ctx    = array( 'order' => null, 'user' => null, 'sample' => true ); // preview context: blocks may show demo rows
// html and image legitimately render nothing until configured — an empty
// block must not emit an empty padded row.
$may_be_empty = array( 'html', 'image' );

foreach ( array_keys( Blocks::registry() ) as $type ) {
	$out = Blocks::render( array( 'type' => $type, 'settings' => Blocks::defaults( $type ) ), $styles, $ctx );
	check(
		"Block renders: $type",
		is_string( $out ) && ( '' !== $out || in_array( $type, $may_be_empty, true ) ),
		gettype( $out )
	);
}

// ...but they must produce output once they have content.
$html_out = Blocks::render(
	array( 'type' => 'html', 'settings' => array( 'content' => '<table><tr><td>hi</td></tr></table>' ) ),
	$styles,
	$ctx
);
check( 'Block renders: html with content', str_contains( $html_out, '<table>' ), $html_out );

$img_out = Blocks::render(
	array( 'type' => 'image', 'settings' => array( 'url' => 'https://example.test/a.png', 'width' => 200, 'align' => 'center', 'alt' => 'A', 'link' => '', 'media_id' => 0 ) ),
	$styles,
	$ctx
);
check( 'Block renders: image with a source', str_contains( $img_out, '<img' ), $img_out );

/* -- 5. Full CRUD cycle on scratch records ----------------------------- */
wp_set_current_user( get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) )[0] ?? 0 );

$made = TemplateRepository::save(
	array(
		'name'         => 'ZZ Scratch Template',
		'description'  => 'created by the functional test',
		'status'       => 'publish',
		'subject'      => 'Order {order_number} confirmed',
		'preview_text' => 'Thanks for shopping with {site_name}',
		'blocks'       => StarterTemplates::default_blocks(),
		'styles'       => array( 'button_bg' => '#ff0000', 'link_color' => '#ff0000' ),
	)
);
check( 'Create template', ! is_wp_error( $made ), is_wp_error( $made ) ? $made->get_error_message() : '' );

$loaded = is_wp_error( $made ) ? null : TemplateRepository::get( (int) $made );
check( 'Read it back', $loaded && 'ZZ Scratch Template' === $loaded['name'] );
check( 'Subject meta round-tripped', $loaded && 'Order {order_number} confirmed' === $loaded['subject'] );
check( 'Preheader meta round-tripped', $loaded && str_contains( (string) $loaded['preview_text'], 'Thanks for shopping' ) );
check( 'Custom style persisted', $loaded && '#ff0000' === $loaded['styles']['button_bg'] );
check( 'Blocks carry the origin key', $loaded && array_key_exists( 'origin', $loaded['blocks'][0] ) );

/* Preheader + link colour actually reach the HTML. */
if ( $loaded ) {
	$html = TemplateRenderer::render( $loaded, $ctx );
	check( 'Preheader rendered into the email', str_contains( $html, 'mso-hide:all' ) && str_contains( $html, 'Thanks for shopping with' ), '' );
	check( 'Link colour inlined onto footer anchors or none present', ! str_contains( $html, '<a ' ) || str_contains( $html, 'color:#' ) );
	$subject = TemplateRenderer::subject( $loaded, 'WooCommerce fallback', $ctx );
	check( 'Subject resolves tags', str_contains( $subject, 'confirmed' ) && ! str_contains( $subject, '{order_number}' ), $subject );
}

/* Versioning: a second save must create a restorable revision. */
if ( $loaded ) {
	$loaded['name'] = 'ZZ Scratch Template v2';
	TemplateRepository::save( $loaded );
	$versions = Versions::all( (int) $made );
	check( 'Second save produced a version', count( $versions ) > 0, count( $versions ) . ' versions' );

	if ( $versions ) {
		$restored = Versions::restore( (int) $versions[0]['id'] );
		check( 'Version restores without error', ! is_wp_error( $restored ), is_wp_error( $restored ) ? $restored->get_error_message() : '' );
	}
}

/* Duplicate. */
$dup = is_wp_error( $made ) ? $made : TemplateRepository::duplicate( (int) $made );
check( 'Duplicate template', ! is_wp_error( $dup ) );
$dup_loaded = is_wp_error( $dup ) ? null : TemplateRepository::get( (int) $dup );
check( 'Duplicate lands as a draft', $dup_loaded && 'draft' === $dup_loaded['status'] );

/* -- 6. Assignment + the WooCommerce bridge ---------------------------- */
$target = isset( $emails['customer_processing_order'] ) ? 'customer_processing_order' : array_key_first( $emails );
$ok     = EmailManager::assign( $target, (int) $made, true );
check( "Assign template to $target", true === $ok, is_wp_error( $ok ) ? $ok->get_error_message() : '' );
check( 'Assignment reads back enabled', ( EmailManager::for_email( $target )['enabled'] ?? 0 ) == 1 );
check( 'Active override counted', EmailManager::active_override_count() >= 1 );
check( 'emails_using() finds it', in_array( $target, EmailManager::emails_using( (int) $made ), true ) );

/* Drive the bridge exactly as WooCommerce would. */
$wc_email = $emails[ $target ];
do_action( 'woocommerce_email_header', 'Heading', $wc_email );
$content = apply_filters( 'woocommerce_mail_content', '<p>WooCommerce original body</p>' );
check( 'Bridge replaced the email body with our template', str_contains( $content, '<!doctype html>' ), substr( $content, 0, 120 ) );

$subject_out = apply_filters( 'woocommerce_email_subject_' . $target, 'WooCommerce original subject', null );
check( 'Bridge replaced the subject', str_contains( $subject_out, 'confirmed' ), $subject_out );

/*
 * Sample tag values exist for previews. A real send must never carry them —
 * a customer receiving "Hi Jane" or order #1234 would be a data-integrity bug.
 */
foreach ( array( 'Jane', '1234', 'jane@example.com', '123 Sample St' ) as $demo ) {
	check( "Live send carries no sample data: '$demo'", ! str_contains( $content, $demo ), 'leaked into the sent body' );
}
check( 'Live send subject carries no sample order number', ! str_contains( $subject_out, '1234' ), $subject_out );

// ...whereas the preview of that same template deliberately does fill them in.
$sample_preview = EmailSender::preview( TemplateRepository::get( (int) $made ), 0 );
check( 'Preview fills sample customer data', str_contains( $sample_preview['html'], 'Jane' ), 'preview left the greeting blank' );
check( 'Preview subject fills the sample order number', str_contains( $sample_preview['subject'], '1234' ), $sample_preview['subject'] );

/* Disable it and confirm WooCommerce's own output comes straight back. */
EmailManager::set_enabled( $target, false );
do_action( 'woocommerce_email_header', 'Heading', $wc_email );
$content_off = apply_filters( 'woocommerce_mail_content', '<p>WooCommerce original body</p>' );
check( 'Disabling the override restores WooCommerce output', '<p>WooCommerce original body</p>' === $content_off, $content_off );

/* A draft template must never take over a live email. */
EmailManager::assign( $target, (int) $dup, true ); // $dup is a draft.
do_action( 'woocommerce_email_header', 'Heading', $wc_email );
$content_draft = apply_filters( 'woocommerce_mail_content', '<p>WooCommerce original body</p>' );
check( 'A draft template does not override a live email', '<p>WooCommerce original body</p>' === $content_draft );

/* -- 7. Components ----------------------------------------------------- */
$comp = ComponentRepository::save(
	array(
		'name'   => 'ZZ Scratch Component',
		'status' => 'publish',
		'blocks' => array(
			array( 'type' => 'heading', 'settings' => array( 'text' => 'Component heading' ) ),
			array( 'type' => 'button', 'settings' => array( 'text' => 'Component CTA' ) ),
		),
		'styles' => array(),
	)
);
check( 'Create component', ! is_wp_error( $comp ) );
check( 'Component stored under its own post type', ! is_wp_error( $comp ) && 'wcem_component' === get_post_type( (int) $comp ) );
check( 'Component invisible to the template repository', ! is_wp_error( $comp ) && null === TemplateRepository::get( (int) $comp ) );
check( 'Template invisible to the component repository', ! is_wp_error( $made ) && null === ComponentRepository::get( (int) $made ) );
$for_editor = ComponentRepository::for_editor();
check( 'Components exposed to the editor with blocks', count( $for_editor ) > 0 && ! empty( $for_editor[0]['blocks'] ) );

/* -- 8. Import / export ------------------------------------------------ */
$payload = ImportExport::export_payload( array( (int) $made ) );
check( 'Export payload well-formed', 'wcem' === $payload['format'] && 1 === count( $payload['templates'] ) );
$before   = count( TemplateRepository::all() );
$imported = ImportExport::import( $payload );
check( 'Import accepted the export', 1 === $imported, var_export( $imported, true ) );
check( 'Import created a new template', count( TemplateRepository::all() ) === $before + 1 );
check( 'Bad payload rejected', is_wp_error( ImportExport::import( array( 'format' => 'nope' ) ) ) );

/* -- 9. Preview + starter library -------------------------------------- */
$prev = EmailSender::preview( $loaded ?: array(), 0 );
check( 'Preview returns subject and html', ! empty( $prev['html'] ) && isset( $prev['subject'] ) );
check( 'Recent orders picker does not error', is_array( EmailSender::recent_orders() ) );

$library = StarterTemplates::library();
check( 'Starter library available at runtime', count( $library ) >= 6, count( $library ) . ' entries' );
foreach ( $library as $slug => $def ) {
	$lib_html = TemplateRenderer::render( array( 'blocks' => $def['blocks'], 'styles' => $def['styles'] ), $ctx );
	check( "Library entry renders: $slug", str_contains( $lib_html, '<!doctype html>' ) );
}

/* -- 10. Clean up ------------------------------------------------------ */
EmailManager::reset( $target );

$scratch = get_posts(
	array(
		'post_type'      => array( 'wcem_template', 'wcem_component' ),
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		's'              => 'ZZ Scratch',
	)
);
foreach ( $scratch as $id ) {
	wp_delete_post( $id, true );
}
check( 'Scratch records cleaned up', count( $scratch ) > 0, count( $scratch ) . ' removed' );
check( 'Assignments pruned on delete', null === EmailManager::for_email( $target ) );

echo str_repeat( '-', 66 ) . "\n";
echo "$n checks, $fail failed.\n";

if ( $GLOBALS['wcem_notices'] ) {
	echo "\nPHP notices/warnings raised from plugin files:\n";
	foreach ( array_unique( $GLOBALS['wcem_notices'] ) as $notice ) {
		echo "  ! $notice\n";
	}
} else {
	echo "\nNo PHP notices, warnings or deprecations from plugin files.\n";
}

exit( $fail ? 1 : 0 );
