<?php
/**
 * Assign a template to each WooCommerce email type, and enable/disable
 * the override.
 *
 * @package Woo_Custom_Email_Templates
 */

use WCEM\Admin\Admin;
use WCEM\Core\Plugin;
use WCEM\Email\EmailManager;
use WCEM\Templates\TemplateRepository;

defined( 'ABSPATH' ) || exit;

$wcem_emails      = EmailManager::all_emails();
$wcem_assignments = EmailManager::assignments();
$wcem_options     = TemplateRepository::options( true );

$wcem_customer = array_filter( $wcem_emails, static fn( $e ) => EmailManager::is_customer_email( (string) $e->id ) );
$wcem_admin    = array_diff_key( $wcem_emails, $wcem_customer );
?>
<div class="wrap wcem-wrap">
	<?php
	Admin::header(
		__( 'Assignments', 'woo-custom-email-templates' ),
		__( 'Choose which template each WooCommerce email uses. Turning an override off restores WooCommerce\'s own template instantly.', 'woo-custom-email-templates' )
	);
	Admin::flash();
	?>

	<?php if ( ! $wcem_options ) : ?>
		<div class="wcem-alert wcem-alert--error" role="status">
			<?php esc_html_e( 'No active templates yet — a template must be set to Active before it can override an email.', 'woo-custom-email-templates' ); ?>
		</div>
	<?php endif; ?>

	<?php
	foreach ( array(
		__( 'Customer Emails', 'woo-custom-email-templates' ) => $wcem_customer,
		__( 'Admin Emails', 'woo-custom-email-templates' )    => $wcem_admin,
	) as $wcem_group_title => $wcem_group_emails ) :
		if ( ! $wcem_group_emails ) {
			continue;
		}
		?>
		<h2 class="wcem-group-title"><?php echo esc_html( $wcem_group_title ); ?></h2>
		<table class="wp-list-table widefat fixed striped wcem-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Email', 'woo-custom-email-templates' ); ?></th>
					<th><?php esc_html_e( 'Template', 'woo-custom-email-templates' ); ?></th>
					<th><?php esc_html_e( 'Override', 'woo-custom-email-templates' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'woo-custom-email-templates' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $wcem_group_emails as $wcem_id => $wcem_email ) : ?>
					<?php
					$wcem_row = $wcem_assignments[ $wcem_id ] ?? array(
						'template_id' => 0,
						'enabled'     => 0,
					);
					?>
					<tr class="wcem-assign-row" data-email-id="<?php echo esc_attr( $wcem_id ); ?>">
						<td>
							<strong><?php echo esc_html( $wcem_email->get_title() ); ?></strong>
							<div class="wcem-muted"><?php echo esc_html( $wcem_email->get_description() ); ?></div>
						</td>
						<td>
							<label class="screen-reader-text" for="wcem-assign-<?php echo esc_attr( $wcem_id ); ?>"><?php esc_html_e( 'Template', 'woo-custom-email-templates' ); ?></label>
							<select id="wcem-assign-<?php echo esc_attr( $wcem_id ); ?>" class="wcem-js-assign-template">
								<option value="0"><?php esc_html_e( 'WooCommerce Default', 'woo-custom-email-templates' ); ?></option>
								<?php foreach ( $wcem_options as $wcem_tid => $wcem_tname ) : ?>
									<option value="<?php echo esc_attr( $wcem_tid ); ?>" <?php selected( (int) $wcem_row['template_id'], $wcem_tid ); ?>><?php echo esc_html( $wcem_tname ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
						<td>
							<label class="wcem-switch">
								<input type="checkbox" class="wcem-js-assign-enabled" <?php checked( ! empty( $wcem_row['enabled'] ) ); ?> <?php disabled( empty( $wcem_row['template_id'] ) ); ?> />
								<span></span>
								<span class="screen-reader-text"><?php esc_html_e( 'Enable custom template for this email', 'woo-custom-email-templates' ); ?></span>
							</label>
							<span class="wcem-badge wcem-badge--<?php echo ! empty( $wcem_row['enabled'] ) ? 'publish' : 'default'; ?>">
								<?php echo ! empty( $wcem_row['enabled'] ) ? esc_html__( 'Custom', 'woo-custom-email-templates' ) : esc_html__( 'Default', 'woo-custom-email-templates' ); ?>
							</span>
						</td>
						<td class="wcem-row-actions">
							<?php if ( $wcem_row['template_id'] ) : ?>
								<a href="<?php echo esc_url( Plugin::url( 'template-edit', array( 'template' => $wcem_row['template_id'] ) ) ); ?>"><?php esc_html_e( 'Edit Template', 'woo-custom-email-templates' ); ?></a>
								<button type="button" class="button-link wcem-js-reset-assignment"><?php esc_html_e( 'Reset', 'woo-custom-email-templates' ); ?></button>
							<?php else : ?>
								—
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endforeach; ?>

	<?php if ( ! $wcem_emails ) : ?>
		<p><?php esc_html_e( 'No WooCommerce email types were found.', 'woo-custom-email-templates' ); ?></p>
	<?php endif; ?>
</div>
