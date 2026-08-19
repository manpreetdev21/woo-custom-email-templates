<?php
/**
 * Plugin settings.
 *
 * @package Woo_Custom_Email_Templates
 */

use WCEM\Admin\Admin;
use WCEM\Core\Plugin;

defined( 'ABSPATH' ) || exit;

$settings = Plugin::settings();
?>
<div class="wrap wcem-wrap">
	<?php Admin::header( __( 'Settings', 'woo-custom-email-templates' ) ); ?>
	<?php Admin::flash(); ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="wcem-form">
		<input type="hidden" name="action" value="wcem_save_settings" />
		<?php wp_nonce_field( 'wcem_save_settings' ); ?>

		<h2><?php esc_html_e( 'Test Email', 'woo-custom-email-templates' ); ?></h2>
		<table class="form-table">
			<tr>
				<th><label for="wcem-test-sender-name"><?php esc_html_e( 'Sender Name', 'woo-custom-email-templates' ); ?></label></th>
				<td><input type="text" id="wcem-test-sender-name" name="settings[test_sender_name]" value="<?php echo esc_attr( $settings['test_sender_name'] ); ?>" class="regular-text" /></td>
			</tr>
			<tr>
				<th><label for="wcem-test-sender-email"><?php esc_html_e( 'Sender Email', 'woo-custom-email-templates' ); ?></label></th>
				<td><input type="email" id="wcem-test-sender-email" name="settings[test_sender_email]" value="<?php echo esc_attr( $settings['test_sender_email'] ); ?>" class="regular-text" /></td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Developer', 'woo-custom-email-templates' ); ?></h2>
		<table class="form-table">
			<tr>
				<th><?php esc_html_e( 'Debug Logging', 'woo-custom-email-templates' ); ?></th>
				<td>
					<label><input type="checkbox" name="settings[debug]" value="1" <?php checked( $settings['debug'] ); ?> /> <?php esc_html_e( 'Log assignment changes and test/send outcomes (no email content or customer data is ever logged).', 'woo-custom-email-templates' ); ?></label>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Uninstall', 'woo-custom-email-templates' ); ?></th>
				<td>
					<label><input type="checkbox" name="settings[delete_on_uninstall]" value="1" <?php checked( $settings['delete_on_uninstall'] ); ?> /> <?php esc_html_e( 'Delete all templates and settings when this plugin is uninstalled.', 'woo-custom-email-templates' ); ?></label>
					<p class="description"><?php esc_html_e( 'WooCommerce\'s own data is never touched.', 'woo-custom-email-templates' ); ?></p>
				</td>
			</tr>
		</table>

		<?php submit_button( __( 'Save Settings', 'woo-custom-email-templates' ) ); ?>
	</form>
</div>
