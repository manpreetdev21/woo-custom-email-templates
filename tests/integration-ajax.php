<?php
/**
 * Exercises every AJAX endpoint through the real WordPress AJAX stack:
 * real nonces, real capability checks, real handlers. Cleans up after itself.
 */

error_reporting( E_ALL );
ini_set( 'display_errors', '1' );

define( 'DOING_AJAX', true );
define( 'WP_ADMIN', true );
define( 'WP_USE_THEMES', false );

$GLOBALS['wcem_notices'] = array();
set_error_handler(
	static function ( $no, $str, $file, $line ) {
		if ( str_contains( (string) $file, 'woo-custom-email-templates' ) ) {
			$GLOBALS['wcem_notices'][] = "[$no] $str in " . basename( (string) $file ) . ":$line";
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
require_once ABSPATH . 'wp-admin/includes/admin.php';

use WCEM\Templates\ComponentRepository;
use WCEM\Templates\StarterTemplates;
use WCEM\Templates\TemplateRepository;
use WCEM\Templates\Versions;

/** Thrown instead of exiting, so one script can drive many endpoints. */
final class AjaxDone extends \Exception {}

add_filter(
	'wp_die_ajax_handler',
	static fn() => static function () {
		throw new AjaxDone();
	}
);

$fail = 0;
$n    = 0;

function check( $label, $ok, $detail = '' ) {
	global $fail, $n;
	++$n;
	echo ( $ok ? '  PASS  ' : '  FAIL  ' ) . $label . ( $ok ? '' : "\n        " . substr( (string) $detail, 0, 300 ) ) . "\n";
	if ( ! $ok ) {
		++$fail;
	}
	return $ok;
}

/**
 * Fires one wp_ajax_wcem_* action and returns the decoded JSON response.
 */
function call_ajax( string $action, array $post ): array {
	$_POST    = $post;
	$_REQUEST = $post;

	ob_start();
	try {
		do_action( 'wp_ajax_wcem_' . $action );
	} catch ( AjaxDone $e ) {
		// Expected: wp_send_json_* finished the request.
	} catch ( \Throwable $e ) {
		ob_end_clean();
		return array( '_throw' => get_class( $e ) . ': ' . $e->getMessage() );
	}
	$raw = (string) ob_get_clean();

	$decoded = json_decode( $raw, true );

	return is_array( $decoded ) ? $decoded : array( '_raw' => substr( $raw, 0, 200 ) );
}

$admin_id = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) )[0] ?? 0;
wp_set_current_user( $admin_id );

$nonce = wp_create_nonce( 'wcem_admin' );

echo "\nAJAX endpoint test — user #$admin_id, nonce issued\n";
echo str_repeat( '-', 66 ) . "\n";

$blocks = wp_json_encode( StarterTemplates::default_blocks() );
$styles = wp_json_encode( array( 'button_bg' => '#00aa00', 'link_color' => '#00aa00' ) );

/* -- Security: every endpoint must reject a bad nonce ------------------ */
$bad = call_ajax( 'save_template', array( 'nonce' => 'not-a-real-nonce', 'name' => 'X' ) );
check( 'Bad nonce rejected on save_template', empty( $bad['success'] ), wp_json_encode( $bad ) );

/* -- Security: a subscriber must be refused --------------------------- */
$sub = get_users( array( 'role' => 'subscriber', 'number' => 1, 'fields' => 'ID' ) )[0] ?? 0;
if ( ! $sub ) {
	$sub = wp_insert_user( array( 'user_login' => 'zz_scratch_sub', 'user_pass' => wp_generate_password(), 'role' => 'subscriber' ) );
	$made_sub = true;
}
wp_set_current_user( $sub );
$denied = call_ajax( 'save_template', array( 'nonce' => wp_create_nonce( 'wcem_admin' ), 'name' => 'X' ) );
check( 'Subscriber refused by capability check', empty( $denied['success'] ), wp_json_encode( $denied ) );
wp_set_current_user( $admin_id );

/* -- save_template ----------------------------------------------------- */
$res = call_ajax(
	'save_template',
	array(
		'nonce'        => $nonce,
		'kind'         => 'template',
		'name'         => 'ZZ Ajax Template',
		'description'  => 'from the ajax test',
		'status'       => 'publish',
		'subject'      => 'Hi {customer_first_name}',
		'preview_text' => 'Peek from {site_name}',
		'blocks'       => $blocks,
		'styles'       => $styles,
	)
);
check( 'save_template creates a template', ! empty( $res['success'] ) && ! empty( $res['data']['id'] ), wp_json_encode( $res ) );
$tid = (int) ( $res['data']['id'] ?? 0 );
check( 'save_template returns an edit URL for the template', str_contains( (string) ( $res['data']['editUrl'] ?? '' ), 'wcem-template-edit' ), $res['data']['editUrl'] ?? '' );

$stored = $tid ? TemplateRepository::get( $tid ) : null;
check( 'Saved template persisted with its meta', $stored && 'Hi {customer_first_name}' === $stored['subject'] && str_contains( (string) $stored['preview_text'], 'Peek from' ) );
check( 'Saved styles persisted', $stored && '#00aa00' === $stored['styles']['button_bg'] );

/* Empty name must be rejected, not silently saved. */
$bad_name = call_ajax( 'save_template', array( 'nonce' => $nonce, 'name' => '   ', 'blocks' => '[]', 'styles' => '{}' ) );
check( 'Blank template name rejected with a message', empty( $bad_name['success'] ) && ! empty( $bad_name['data']['message'] ), wp_json_encode( $bad_name ) );

/* -- save_template as a component -------------------------------------- */
$cres = call_ajax(
	'save_template',
	array(
		'nonce'  => $nonce,
		'kind'   => 'component',
		'name'   => 'ZZ Ajax Component',
		'status' => 'publish',
		'blocks' => wp_json_encode( array( array( 'type' => 'heading', 'settings' => array( 'text' => 'Reusable' ) ) ) ),
		'styles' => '{}',
	)
);
$cid = (int) ( $cres['data']['id'] ?? 0 );
check( 'save_template with kind=component routes to the component repo', $cid && 'wcem_component' === get_post_type( $cid ), wp_json_encode( $cres ) );
check( 'Component edit URL points at the component screen', str_contains( (string) ( $cres['data']['editUrl'] ?? '' ), 'wcem-component-edit' ) );

/* -- preview_template --------------------------------------------------- */
$prev = call_ajax(
	'preview_template',
	array(
		'nonce'        => $nonce,
		'kind'         => 'template',
		'subject'      => 'Preview {site_name}',
		'preview_text' => 'Preheader {site_name}',
		'blocks'       => $blocks,
		'styles'       => $styles,
		'order_id'     => 0,
	)
);
check( 'preview_template returns HTML', ! empty( $prev['data']['html'] ) && str_contains( $prev['data']['html'], '<!doctype html>' ), wp_json_encode( array_keys( (array) ( $prev['data'] ?? array() ) ) ) );
check( 'preview_template resolves the subject', ! empty( $prev['data']['subject'] ) && ! str_contains( $prev['data']['subject'], '{site_name}' ), $prev['data']['subject'] ?? '' );
check( 'preview_template renders the preheader', str_contains( (string) ( $prev['data']['html'] ?? '' ), 'mso-hide:all' ) );

/* Hostile block payload must be sanitised away, not rendered. */
$evil = call_ajax(
	'preview_template',
	array(
		'nonce'  => $nonce,
		'blocks' => wp_json_encode(
			array(
				array( 'type' => 'heading', 'settings' => array( 'text' => '<script>alert(1)</script>' ) ),
				array( 'type' => 'not_a_block', 'settings' => array() ),
			)
		),
		'styles' => wp_json_encode( array( 'bg_color' => 'javascript:alert(1)' ) ),
	)
);
$evil_html = (string) ( $evil['data']['html'] ?? '' );
check( 'Script in a block never reaches rendered HTML', ! str_contains( $evil_html, '<script>alert' ), substr( $evil_html, 0, 150 ) );
check( 'Unknown block type dropped', ! str_contains( $evil_html, 'not_a_block' ) );
check( 'Bogus colour rejected, default used', ! str_contains( $evil_html, 'javascript:' ) );

/* -- duplicate_template -------------------------------------------------- */
$dup = call_ajax( 'duplicate_template', array( 'nonce' => $nonce, 'kind' => 'template', 'id' => $tid ) );
$did = (int) ( $dup['data']['id'] ?? 0 );
check( 'duplicate_template copies it', ! empty( $dup['success'] ) && $did && $did !== $tid, wp_json_encode( $dup ) );

/* -- assign_template / toggle_enabled / reset_assignment ----------------- */
$target = 'customer_processing_order';

$as = call_ajax( 'assign_template', array( 'nonce' => $nonce, 'email_id' => $target, 'template_id' => $tid, 'enabled' => 1 ) );
check( "assign_template assigns to $target", ! empty( $as['success'] ), wp_json_encode( $as ) );

$tg = call_ajax( 'toggle_enabled', array( 'nonce' => $nonce, 'email_id' => $target, 'enabled' => 0 ) );
check( 'toggle_enabled turns the override off', ! empty( $tg['success'] ) && 0 == \WCEM\Email\EmailManager::for_email( $target )['enabled'] );

$bogus = call_ajax( 'assign_template', array( 'nonce' => $nonce, 'email_id' => 'no_such_email', 'template_id' => $tid, 'enabled' => 1 ) );
check( 'Assigning to an unknown email id is refused', empty( $bogus['success'] ), wp_json_encode( $bogus ) );

$rs = call_ajax( 'reset_assignment', array( 'nonce' => $nonce, 'email_id' => $target ) );
check( 'reset_assignment clears it', ! empty( $rs['success'] ) && null === \WCEM\Email\EmailManager::for_email( $target ) );

/* -- restore_version ------------------------------------------------------ */
if ( $stored ) {
	$stored['name'] = 'ZZ Ajax Template edited';
	TemplateRepository::save( $stored );
	$versions = Versions::all( $tid );

	if ( $versions ) {
		$rv = call_ajax( 'restore_version', array( 'nonce' => $nonce, 'revision_id' => $versions[0]['id'] ) );
		check( 'restore_version restores a template', ! empty( $rv['success'] ), wp_json_encode( $rv ) );
	} else {
		check( 'restore_version had a version to restore', false, 'no revisions created' );
	}

	$rv_bad = call_ajax( 'restore_version', array( 'nonce' => $nonce, 'revision_id' => 999999 ) );
	check( 'restore_version refuses a bogus revision id', empty( $rv_bad['success'] ), wp_json_encode( $rv_bad ) );
}

/* -- send_test_email ------------------------------------------------------ */
$mail_calls = array();
add_filter(
	'pre_wp_mail',
	static function ( $null, $atts ) use ( &$mail_calls ) {
		$mail_calls[] = $atts;
		return true; // Intercept: never actually send from a test run.
	},
	10,
	2
);

$te = call_ajax(
	'send_test_email',
	array(
		'nonce'     => $nonce,
		'recipient' => 'nobody@example.test',
		'blocks'    => $blocks,
		'styles'    => $styles,
		'subject'   => 'Test {site_name}',
		'order_id'  => 0,
	)
);
check( 'send_test_email succeeds', ! empty( $te['success'] ), wp_json_encode( $te ) );
check( 'send_test_email actually called wp_mail once', 1 === count( $mail_calls ), count( $mail_calls ) . ' calls' );

if ( $mail_calls ) {
	$m = $mail_calls[0];
	check( 'Test mail addressed to the requested recipient', 'nobody@example.test' === $m['to'] );
	check( 'Test mail subject prefixed with [TEST]', str_starts_with( (string) $m['subject'], '[TEST]' ), $m['subject'] );
	check( 'Test mail subject resolved its tags', ! str_contains( (string) $m['subject'], '{site_name}' ), $m['subject'] );
	check( 'Test mail sent as HTML', (bool) preg_grep( '#text/html#', (array) $m['headers'] ) );
	check( 'From header display name is quoted', (bool) preg_grep( '/^From: ".*" <.*>$/', (array) $m['headers'] ), implode( ' | ', (array) $m['headers'] ) );
	check( 'Test mail body is the rendered email', str_contains( (string) $m['message'], '<!doctype html>' ) );
}

/* Throttle must block an immediate second send. */
$te2 = call_ajax(
	'send_test_email',
	array( 'nonce' => $nonce, 'recipient' => 'nobody@example.test', 'blocks' => $blocks, 'styles' => $styles )
);
check( 'Second test send is throttled', empty( $te2['success'] ), wp_json_encode( $te2 ) );
delete_transient( 'wcem_test_throttle_' . $admin_id );

$te_bad = call_ajax( 'send_test_email', array( 'nonce' => $nonce, 'recipient' => 'not-an-email', 'blocks' => '[]', 'styles' => '{}' ) );
check( 'Invalid recipient rejected', empty( $te_bad['success'] ), wp_json_encode( $te_bad ) );
delete_transient( 'wcem_test_throttle_' . $admin_id );

/* -- delete_template ------------------------------------------------------ */
$del = call_ajax( 'delete_template', array( 'nonce' => $nonce, 'kind' => 'template', 'id' => $did ) );
check( 'delete_template removes the duplicate', ! empty( $del['success'] ) && null === TemplateRepository::get( $did ) );

$del_bad = call_ajax( 'delete_template', array( 'nonce' => $nonce, 'id' => 999999 ) );
check( 'delete_template refuses a non-existent id', empty( $del_bad['success'] ) );

/* -- Clean up -------------------------------------------------------------- */
foreach ( get_posts( array( 'post_type' => array( 'wcem_template', 'wcem_component' ), 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids', 's' => 'ZZ Ajax' ) ) as $id ) {
	wp_delete_post( $id, true );
}
if ( ! empty( $made_sub ) ) {
	require_once ABSPATH . 'wp-admin/includes/user.php';
	wp_delete_user( $sub );
}
\WCEM\Email\EmailManager::reset( $target );

echo str_repeat( '-', 66 ) . "\n";
echo "$n checks, $fail failed.\n";
echo $GLOBALS['wcem_notices']
	? "\nNotices:\n  " . implode( "\n  ", array_unique( $GLOBALS['wcem_notices'] ) ) . "\n"
	: "No PHP notices from plugin files.\n";

exit( $fail ? 1 : 0 );
