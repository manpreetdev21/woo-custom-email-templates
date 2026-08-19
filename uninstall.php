<?php
/**
 * Removes plugin data on uninstall, but only when the administrator opted
 * in via Settings → "Delete plugin data on uninstall". WooCommerce's own
 * data is never touched.
 *
 * @package Woo_Custom_Email_Templates
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$settings = get_option( 'wcem_settings', array() );

if ( empty( $settings['delete_on_uninstall'] ) ) {
	return;
}

foreach ( array( 'wcem_template', 'wcem_component' ) as $wcem_post_type ) {
	$wcem_posts = get_posts(
		array(
			'post_type'      => $wcem_post_type,
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		)
	);

	foreach ( $wcem_posts as $wcem_id ) {
		wp_delete_post( $wcem_id, true );
	}
}

foreach ( array( 'wcem_settings', 'wcem_assignments', 'wcem_log', 'wcem_seeded', 'wcem_onboarded' ) as $wcem_option ) {
	delete_option( $wcem_option );
}
