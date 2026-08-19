<?php
/**
 * Import/export and the debug log.
 *
 * @package Woo_Custom_Email_Templates
 */

use WCEM\Admin\Admin;

defined( 'ABSPATH' ) || exit;

$log = array_reverse( (array) get_option( 'wcem_log', array() ) );
?>
<div class="wrap wcem-wrap">
	<?php Admin::header( __( 'Tools', 'woo-custom-email-templates' ) ); ?>
	<?php Admin::flash(); ?>

	<div class="wcem-cards wcem-cards--tools">
		<div class="wcem-card">
			<h2><?php esc_html_e( 'Export Templates', 'woo-custom-email-templates' ); ?></h2>
			<p><?php esc_html_e( 'Download every template as a JSON file you can re-import on this or another site.', 'woo-custom-email-templates' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="wcem_export" />
				<?php wp_nonce_field( 'wcem_export' ); ?>
				<button class="button button-primary"><?php esc_html_e( 'Export All', 'woo-custom-email-templates' ); ?></button>
			</form>
		</div>

		<div class="wcem-card">
			<h2><?php esc_html_e( 'Import Templates', 'woo-custom-email-templates' ); ?></h2>
			<p><?php esc_html_e( 'Upload a template export file. Imported templates are added as drafts.', 'woo-custom-email-templates' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
				<input type="hidden" name="action" value="wcem_import" />
				<?php wp_nonce_field( 'wcem_import' ); ?>
				<input type="file" name="import_file" accept="application/json" required />
				<button class="button button-primary"><?php esc_html_e( 'Import', 'woo-custom-email-templates' ); ?></button>
			</form>
		</div>
	</div>

	<div class="wcem-section-head">
		<h2><?php esc_html_e( 'Debug Log', 'woo-custom-email-templates' ); ?></h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="wcem_clear_log" />
			<?php wp_nonce_field( 'wcem_clear_log' ); ?>
			<button class="button-link"><?php esc_html_e( 'Clear log', 'woo-custom-email-templates' ); ?></button>
		</form>
	</div>

	<?php if ( ! $log ) : ?>
		<p class="wcem-muted"><?php esc_html_e( 'Nothing logged yet. Enable Debug Logging in Settings to start recording assignment and send activity.', 'woo-custom-email-templates' ); ?></p>
	<?php else : ?>
		<table class="wp-list-table widefat fixed striped wcem-table">
			<thead><tr><th style="width:180px;"><?php esc_html_e( 'Time', 'woo-custom-email-templates' ); ?></th><th><?php esc_html_e( 'Event', 'woo-custom-email-templates' ); ?></th></tr></thead>
			<tbody>
				<?php foreach ( array_slice( $log, 0, 100 ) as $entry ) : ?>
					<tr>
						<td><?php echo esc_html( wp_date( 'Y-m-d H:i:s', $entry['time'] ) ); ?></td>
						<td><?php echo esc_html( $entry['message'] ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
