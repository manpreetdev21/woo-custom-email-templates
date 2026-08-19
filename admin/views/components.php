<?php
/**
 * Reusable components list.
 *
 * @package Woo_Custom_Email_Templates
 */

use WCEM\Admin\Admin;
use WCEM\Core\Plugin;
use WCEM\Templates\ComponentRepository;

defined( 'ABSPATH' ) || exit;

$wcem_components = ComponentRepository::all();
?>
<div class="wrap wcem-wrap">
	<?php
	Admin::header(
		__( 'Components', 'woo-custom-email-templates' ),
		__( 'Save a group of blocks once — a store header, an order summary, a sign-off — and drop it into any template.', 'woo-custom-email-templates' ),
		sprintf(
			'<a href="%s" class="button button-primary">%s</a>',
			esc_url( Plugin::url( 'component-edit' ) ),
			esc_html__( 'Create Component', 'woo-custom-email-templates' )
		)
	);
	Admin::flash();
	?>

	<?php if ( ! $wcem_components ) : ?>
		<div class="wcem-empty">
			<span class="dashicons dashicons-screenoptions"></span>
			<h2><?php esc_html_e( 'No components yet', 'woo-custom-email-templates' ); ?></h2>
			<p><?php esc_html_e( 'Build a reusable section once and insert it into every template that needs it. Update the component later and re-sync the templates using it.', 'woo-custom-email-templates' ); ?></p>
			<a href="<?php echo esc_url( Plugin::url( 'component-edit' ) ); ?>" class="button button-primary"><?php esc_html_e( 'Create Component', 'woo-custom-email-templates' ); ?></a>
		</div>
	<?php else : ?>
		<table class="wp-list-table widefat fixed striped wcem-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Name', 'woo-custom-email-templates' ); ?></th>
					<th><?php esc_html_e( 'Blocks', 'woo-custom-email-templates' ); ?></th>
					<th><?php esc_html_e( 'Last Modified', 'woo-custom-email-templates' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'woo-custom-email-templates' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $wcem_components as $wcem_component ) : ?>
					<tr data-template-id="<?php echo esc_attr( $wcem_component['id'] ); ?>">
						<td>
							<strong><a href="<?php echo esc_url( Plugin::url( 'component-edit', array( 'component' => $wcem_component['id'] ) ) ); ?>"><?php echo esc_html( $wcem_component['name'] ); ?></a></strong>
							<div class="wcem-muted"><?php echo esc_html( $wcem_component['description'] ); ?></div>
						</td>
						<td><?php echo (int) count( $wcem_component['blocks'] ); ?></td>
						<td><?php echo esc_html( mysql2date( get_option( 'date_format' ), $wcem_component['modified'] ) ); ?></td>
						<td class="wcem-row-actions">
							<a href="<?php echo esc_url( Plugin::url( 'component-edit', array( 'component' => $wcem_component['id'] ) ) ); ?>"><?php esc_html_e( 'Edit', 'woo-custom-email-templates' ); ?></a>
							<button type="button" class="button-link wcem-js-duplicate" data-kind="component" data-id="<?php echo esc_attr( $wcem_component['id'] ); ?>"><?php esc_html_e( 'Duplicate', 'woo-custom-email-templates' ); ?></button>
							<button type="button" class="button-link wcem-js-delete" data-kind="component" data-id="<?php echo esc_attr( $wcem_component['id'] ); ?>"><?php esc_html_e( 'Delete', 'woo-custom-email-templates' ); ?></button>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
