<?php
/**
 * Dashboard: stats + recent templates.
 *
 * @package Woo_Custom_Email_Templates
 */

use WCEM\Admin\Admin;
use WCEM\Core\Plugin;
use WCEM\Email\EmailManager;
use WCEM\Templates\ComponentRepository;
use WCEM\Templates\TemplateRepository;

defined( 'ABSPATH' ) || exit;

$wcem_emails      = EmailManager::all_emails();
$wcem_counts      = TemplateRepository::counts();
$wcem_assignments = EmailManager::assignments();
$wcem_templates   = array_slice( TemplateRepository::all(), 0, 6 );
?>
<div class="wrap wcem-wrap">
	<?php
	Admin::header(
		__( 'Email Templates', 'woo-custom-email-templates' ),
		__( 'Design and assign custom layouts for your WooCommerce transactional emails.', 'woo-custom-email-templates' ),
		sprintf(
			'<a href="%s" class="button button-primary">%s</a> <a href="%s" class="button">%s</a> <a href="%s" class="button">%s</a>',
			esc_url( Plugin::url( 'template-edit' ) ),
			esc_html__( 'Create Template', 'woo-custom-email-templates' ),
			esc_url( Plugin::url( 'library' ) ),
			esc_html__( 'Template Library', 'woo-custom-email-templates' ),
			esc_url( Plugin::url( 'tools' ) ),
			esc_html__( 'Import Template', 'woo-custom-email-templates' )
		)
	);
	Admin::flash();
	?>

	<div class="wcem-stats">
		<div class="wcem-stat-card">
			<span class="wcem-stat-card__value"><?php echo (int) count( $wcem_emails ); ?></span>
			<span class="wcem-stat-card__label"><?php esc_html_e( 'Email Types', 'woo-custom-email-templates' ); ?></span>
		</div>
		<div class="wcem-stat-card">
			<span class="wcem-stat-card__value"><?php echo (int) $wcem_counts['total']; ?></span>
			<span class="wcem-stat-card__label"><?php esc_html_e( 'Custom Templates', 'woo-custom-email-templates' ); ?></span>
		</div>
		<div class="wcem-stat-card">
			<span class="wcem-stat-card__value"><?php echo (int) EmailManager::active_override_count(); ?></span>
			<span class="wcem-stat-card__label"><?php esc_html_e( 'Active Overrides', 'woo-custom-email-templates' ); ?></span>
		</div>
		<div class="wcem-stat-card">
			<span class="wcem-stat-card__value"><?php echo (int) ComponentRepository::counts()['total']; ?></span>
			<span class="wcem-stat-card__label"><?php esc_html_e( 'Reusable Components', 'woo-custom-email-templates' ); ?></span>
		</div>
	</div>

	<?php if ( empty( $wcem_templates ) ) : ?>
		<div class="wcem-empty">
			<span class="dashicons dashicons-email-alt"></span>
			<h2><?php esc_html_e( 'No custom templates yet', 'woo-custom-email-templates' ); ?></h2>
			<p><?php esc_html_e( 'Create your first WooCommerce email template and give your transactional emails a modern look.', 'woo-custom-email-templates' ); ?></p>
			<a href="<?php echo esc_url( Plugin::url( 'library' ) ); ?>" class="button button-primary"><?php esc_html_e( 'Browse the Library', 'woo-custom-email-templates' ); ?></a>
		</div>
	<?php else : ?>
		<div class="wcem-section-head">
			<h2><?php esc_html_e( 'Recent Templates', 'woo-custom-email-templates' ); ?></h2>
			<a href="<?php echo esc_url( Plugin::url( 'templates' ) ); ?>"><?php esc_html_e( 'View all →', 'woo-custom-email-templates' ); ?></a>
		</div>
		<div class="wcem-cards">
			<?php foreach ( $wcem_templates as $wcem_template ) : ?>
				<div class="wcem-card">
					<div class="wcem-card__head">
						<strong><?php echo esc_html( $wcem_template['name'] ); ?></strong>
						<span class="wcem-badge wcem-badge--<?php echo esc_attr( $wcem_template['status'] ); ?>"><?php echo esc_html( TemplateRepository::status_label( $wcem_template['status'] ) ); ?></span>
					</div>
					<p class="wcem-card__desc"><?php echo esc_html( $wcem_template['description'] ?: __( 'No description.', 'woo-custom-email-templates' ) ); ?></p>
					<div class="wcem-card__foot">
						<span><?php printf( /* translators: %d: number of blocks */ esc_html__( '%d blocks', 'woo-custom-email-templates' ), count( $wcem_template['blocks'] ) ); ?></span>
						<a href="<?php echo esc_url( Plugin::url( 'template-edit', array( 'template' => $wcem_template['id'] ) ) ); ?>" class="button button-small"><?php esc_html_e( 'Edit', 'woo-custom-email-templates' ); ?></a>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<div class="wcem-section-head">
		<h2><?php esc_html_e( 'Email Types', 'woo-custom-email-templates' ); ?></h2>
		<a href="<?php echo esc_url( Plugin::url( 'assignments' ) ); ?>"><?php esc_html_e( 'Manage assignments →', 'woo-custom-email-templates' ); ?></a>
	</div>
	<table class="wp-list-table widefat fixed striped wcem-table">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Email', 'woo-custom-email-templates' ); ?></th>
				<th><?php esc_html_e( 'Assigned Template', 'woo-custom-email-templates' ); ?></th>
				<th><?php esc_html_e( 'Status', 'woo-custom-email-templates' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( ! $wcem_emails ) : ?>
				<tr><td colspan="3"><?php esc_html_e( 'No WooCommerce email types were found.', 'woo-custom-email-templates' ); ?></td></tr>
			<?php else : ?>
				<?php foreach ( $wcem_emails as $wcem_id => $wcem_email ) : ?>
					<?php
					$wcem_row      = $wcem_assignments[ $wcem_id ] ?? null;
					$wcem_assigned = $wcem_row ? TemplateRepository::get( (int) $wcem_row['template_id'] ) : null;
					?>
					<tr>
						<td>
							<strong><?php echo esc_html( $wcem_email->get_title() ); ?></strong>
							<div class="wcem-muted"><code><?php echo esc_html( $wcem_id ); ?></code></div>
						</td>
						<td><?php echo $wcem_assigned ? esc_html( $wcem_assigned['name'] ) : '—'; ?></td>
						<td>
							<?php if ( $wcem_row && ! empty( $wcem_row['enabled'] ) && $wcem_assigned ) : ?>
								<span class="wcem-badge wcem-badge--publish"><?php esc_html_e( 'Custom', 'woo-custom-email-templates' ); ?></span>
							<?php else : ?>
								<span class="wcem-badge wcem-badge--default"><?php esc_html_e( 'WooCommerce Default', 'woo-custom-email-templates' ); ?></span>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>
</div>
