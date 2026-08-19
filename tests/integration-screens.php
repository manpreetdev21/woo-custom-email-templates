<?php
/**
 * Renders every admin screen through the real WordPress admin stack and
 * reports fatals, notices, and obviously broken output. Read-only.
 */

error_reporting( E_ALL );
ini_set( 'display_errors', '1' );

define( 'WP_ADMIN', true );
define( 'WP_USE_THEMES', false );

$GLOBALS['wcem_notices'] = array();
set_error_handler(
	static function ( $no, $str, $file, $line ) {
		$GLOBALS['wcem_notices'][] = array(
			'ours' => str_contains( (string) $file, 'woo-custom-email-templates' ),
			'msg'  => "[$no] $str in " . basename( (string) $file ) . ":$line",
		);
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

use WCEM\Admin\Admin;
use WCEM\Core\Plugin;

$admin_id = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) )[0] ?? 0;
wp_set_current_user( $admin_id );
set_current_screen( 'dashboard' );

echo "Acting as user #$admin_id — can manage_woocommerce: " . ( current_user_can( 'manage_woocommerce' ) ? 'yes' : 'NO' ) . "\n";
echo str_repeat( '-', 66 ) . "\n";

// Fire admin_menu so Admin::$hooks is populated the way WordPress would.
global $menu, $submenu;
$menu    = array();
$submenu = array();
do_action( 'admin_menu' );

echo "Top-level menu entries containing 'wcem':\n";
foreach ( (array) $menu as $pos => $item ) {
	if ( isset( $item[2] ) && str_contains( (string) $item[2], 'wcem' ) ) {
		echo "  position $pos → slug '{$item[2]}', title '" . wp_strip_all_tags( (string) $item[0] ) . "', icon '{$item[6]}'\n";
	}
}

echo "\nNeighbouring menu positions (to confirm placement):\n";
$positions = array();
foreach ( (array) $menu as $pos => $item ) {
	if ( ! empty( $item[2] ) ) {
		$positions[ (string) $pos ] = wp_strip_all_tags( (string) $item[0] );
	}
}
uksort( $positions, static fn( $a, $b ) => (float) $a <=> (float) $b );
foreach ( $positions as $pos => $title ) {
	if ( '' === trim( $title ) ) {
		continue;
	}
	echo "  " . str_pad( $pos, 8 ) . " $title\n";
}

echo "\nVisible submenu under 'wcem':\n";
foreach ( (array) ( $submenu['wcem'] ?? array() ) as $item ) {
	echo "  - " . wp_strip_all_tags( (string) $item[0] ) . "  ({$item[2]})\n";
}

/*
 * Every screen must survive WordPress's own admin-page access check.
 *
 * This is the check that catches the remove_submenu_page() trap: removing a
 * page from $submenu during admin_menu makes get_admin_page_parent() return
 * '', WordPress computes the wrong hookname, and the screen dies with
 * "Sorry, you are not allowed to access this page." Rendering the view
 * directly does NOT exercise this — only user_can_access_admin_page() does.
 */
global $plugin_page, $pagenow;
$pagenow = 'admin.php';

$all_slugs = array( 'wcem', 'wcem-templates', 'wcem-library', 'wcem-components', 'wcem-assignments', 'wcem-settings', 'wcem-tools', 'wcem-template-edit', 'wcem-component-edit', 'wcem-onboarding' );

echo "\nAdmin-page access check (URL reachability):\n";
$access_fail = 0;
foreach ( $all_slugs as $slug ) {
	$plugin_page      = $slug;
	$_GET['page']     = $slug;
	$_REQUEST['page'] = $slug;

	$parent   = get_admin_page_parent();
	$hookname = get_plugin_page_hookname( $slug, $parent );
	$ok       = user_can_access_admin_page();

	printf(
		"  %s  %-22s parent=%-8s hook=%s\n",
		$ok ? 'PASS' : 'FAIL',
		$slug,
		var_export( $parent, true ),
		$hookname
	);

	if ( ! $ok ) {
		++$access_fail;
	}
}
$_GET = $_REQUEST = array();

// The sidebar must hide the utility screens — but only once admin_head has run.
$hidden = array( 'wcem-template-edit', 'wcem-component-edit', 'wcem-onboarding' );

$listed_before = wp_list_pluck( (array) ( $submenu['wcem'] ?? array() ), 2 );
echo "\nBefore admin_head, utility screens still registered as submenus (required for access):\n";
foreach ( $hidden as $h ) {
	echo "  " . ( in_array( $h, $listed_before, true ) ? 'PASS' : 'FAIL' ) . "  $h present during admin_menu\n";
	if ( ! in_array( $h, $listed_before, true ) ) {
		++$access_fail;
	}
}

do_action( 'admin_head' );

$listed_after = wp_list_pluck( (array) ( $submenu['wcem'] ?? array() ), 2 );
echo "\nAfter admin_head, they are gone from the sidebar:\n";
foreach ( $hidden as $h ) {
	echo "  " . ( in_array( $h, $listed_after, true ) ? 'FAIL' : 'PASS' ) . "  $h hidden from sidebar\n";
	if ( in_array( $h, $listed_after, true ) ) {
		++$access_fail;
	}
}

/* ------------------------------------------------------------------ */

$screens = array(
	'render_dashboard'   => array( 'page' => 'wcem' ),
	'render_templates'   => array( 'page' => 'wcem-templates' ),
	'render_library'     => array( 'page' => 'wcem-library' ),
	'render_components'  => array( 'page' => 'wcem-components' ),
	'render_assignments' => array( 'page' => 'wcem-assignments' ),
	'render_settings'    => array( 'page' => 'wcem-settings' ),
	'render_tools'       => array( 'page' => 'wcem-tools' ),
	'render_onboarding'  => array( 'page' => 'wcem-onboarding' ),
	'render_editor'      => array( 'page' => 'wcem-template-edit' ),
);

// Also exercise the editor with a real saved template, and as a component.
$existing_id = ( get_posts( array( 'post_type' => 'wcem_template', 'post_status' => 'any', 'posts_per_page' => 1, 'fields' => 'ids' ) )[0] ?? 0 );
if ( $existing_id ) {
	$screens['render_editor (existing template)'] = array( 'page' => 'wcem-template-edit', 'template' => $existing_id );
}
$screens['render_editor (component)'] = array( 'page' => 'wcem-component-edit' );

// And the list screens with search / filter / sort / paging applied.
$screens['render_templates (filtered)'] = array( 'page' => 'wcem-templates', 's' => 'modern', 'status' => 'draft', 'orderby' => 'name', 'order' => 'asc', 'paged' => 1 );
$screens['render_library (filtered)']   = array( 'page' => 'wcem-library', 'category' => 'Dark' );
$screens['render_dashboard (notice)']   = array( 'page' => 'wcem', 'wcem_notice' => 'settings_saved' );

echo "\n" . str_repeat( '-', 66 ) . "\n";

$fail = 0;

foreach ( $screens as $label => $query ) {
	$method = explode( ' ', $label )[0];

	$_GET     = $query;
	$_REQUEST = $query;

	$before = count( $GLOBALS['wcem_notices'] );

	ob_start();
	try {
		Admin::$method();
		$html = (string) ob_get_clean();
		$err  = '';
	} catch ( \Throwable $e ) {
		ob_end_clean();
		$html = '';
		$err  = get_class( $e ) . ': ' . $e->getMessage();
	}

	$new_notices = array_slice( $GLOBALS['wcem_notices'], $before );
	$ours        = array_filter( $new_notices, static fn( $x ) => $x['ours'] );

	$ok = '' === $err && strlen( $html ) > 400 && str_contains( $html, 'wcem-' ) && ! $ours;

	printf(
		"  %s  %-40s %6d bytes%s\n",
		$ok ? 'PASS' : 'FAIL',
		$label,
		strlen( $html ),
		$err ? "  << $err" : ''
	);

	foreach ( $ours as $notice ) {
		echo "         ! {$notice['msg']}\n";
	}

	if ( ! $ok ) {
		++$fail;
		if ( '' === $err ) {
			echo "         output head: " . substr( preg_replace( '/\s+/', ' ', $html ), 0, 200 ) . "\n";
		}
	}
}

echo str_repeat( '-', 66 ) . "\n";
$fail += $access_fail;

echo count( $screens ) . " screens rendered, " . count( $all_slugs ) . " access checks, $fail failed.\n";

$all_ours = array_filter( $GLOBALS['wcem_notices'], static fn( $x ) => $x['ours'] );
if ( $all_ours ) {
	echo "\nNotices from plugin files:\n";
	foreach ( array_unique( wp_list_pluck( $all_ours, 'msg' ) ) as $m ) {
		echo "  ! $m\n";
	}
} else {
	echo "No PHP notices from plugin files across any screen.\n";
}

$foreign = array_filter( $GLOBALS['wcem_notices'], static fn( $x ) => ! $x['ours'] );
if ( $foreign ) {
	echo "\n(For context — notices from WordPress/WooCommerce core, not ours: " . count( $foreign ) . ")\n";
	foreach ( array_slice( array_unique( wp_list_pluck( $foreign, 'msg' ) ), 0, 5 ) as $m ) {
		echo "  · $m\n";
	}
}

exit( $fail ? 1 : 0 );
