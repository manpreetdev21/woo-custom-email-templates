<?php
/**
 * Templates list: search, filter, sort, paginate, per-row actions.
 *
 * @package Woo_Custom_Email_Templates
 */

use WCEM\Admin\Admin;
use WCEM\Core\Plugin;
use WCEM\Email\EmailManager;
use WCEM\Templates\TemplateRepository;

defined( 'ABSPATH' ) || exit;

$wcem_templates = TemplateRepository::all();

// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only display filters, no state change.
$wcem_search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
$wcem_status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
$wcem_orderby = isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : 'modified';
$wcem_order   = isset( $_GET['order'] ) && 'asc' === strtolower( sanitize_key( wp_unslash( $_GET['order'] ) ) ) ? 'asc' : 'desc';
$wcem_paged   = max( 1, isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1 );
// phpcs:enable WordPress.Security.NonceVerification.Recommended

if ( $wcem_search ) {
	$wcem_templates = array_filter(
		$wcem_templates,
		static fn( $t ) => false !== stripos( $t['name'] . ' ' . $t['description'], $wcem_search )
	);
}

if ( $wcem_status ) {
	$wcem_templates = array_filter( $wcem_templates, static fn( $t ) => $t['status'] === $wcem_status );
}

$wcem_orderby = in_array( $wcem_orderby, array( 'name', 'status', 'modified' ), true ) ? $wcem_orderby : 'modified';

usort(
	$wcem_templates,
	static function ( $a, $b ) use ( $wcem_orderby, $wcem_order ) {
		$result = strcmp( (string) $a[ $wcem_orderby ], (string) $b[ $wcem_orderby ] );
		return 'asc' === $wcem_order ? $result : -$result;
	}
);

$wcem_per_page = 20;
$wcem_total    = count( $wcem_templates );
$wcem_pages    = max( 1, (int) ceil( $wcem_total / $wcem_per_page ) );
$wcem_paged    = min( $wcem_paged, $wcem_pages );
$wcem_page_of  = array_slice( $wcem_templates, ( $wcem_paged - 1 ) * $wcem_per_page, $wcem_per_page );

/**
 * Builds a column-header sort link that flips direction on the active column.
 *
 * @param string $column  Column key.
 * @param string $label   Header label.
 * @param string $current Active sort column.
 * @param string $order   Active sort direction.
 * @return string
 */
$wcem_sort_link = static function ( $column, $label, $current, $order ) {
	$next = ( $column === $current && 'asc' === $order ) ? 'desc' : 'asc';
	$url  = Plugin::url(
		'templates',
		array(
			'orderby' => $column,
			'order'   => $next,
		)
	);

	$arrow = $column === $current ? ( 'asc' === $order ? ' ↑' : ' ↓' ) : '';

	return sprintf( '<a href="%s">%s%s</a>', esc_url( $url ), esc_html( $label ), esc_html( $arrow ) );
};
?>
<div class="wrap wcem-wrap">
	<?php
	Admin::header(
		__( 'Templates', 'woo-custom-email-templates' ),
		'',
		sprintf(
			'<a href="%s" class="button button-primary">%s</a> <a href="%s" class="button">%s</a>',
			esc_url( Plugin::url( 'template-edit' ) ),
			esc_html__( 'Create Template', 'woo-custom-email-templates' ),
			esc_url( Plugin::url( 'library' ) ),
			esc_html__( 'Template Library', 'woo-custom-email-templates' )
		)
	);
	Admin::flash();
	?>

	<form method="get" class="wcem-toolbar">
		<input type="hidden" name="page" value="wcem-templates" />
		<input type="hidden" name="orderby" value="<?php echo esc_attr( $wcem_orderby ); ?>" />
		<input type="hidden" name="order" value="<?php echo esc_attr( $wcem_order ); ?>" />
		<label class="screen-reader-text" for="wcem-search"><?php esc_html_e( 'Search templates', 'woo-custom-email-templates' ); ?></label>
		<input type="search" id="wcem-search" name="s" value="<?php echo esc_attr( $wcem_search ); ?>" placeholder="<?php esc_attr_e( 'Search templates…', 'woo-custom-email-templates' ); ?>" />
		<label class="screen-reader-text" for="wcem-status"><?php esc_html_e( 'Filter by status', 'woo-custom-email-templates' ); ?></label>
		<select id="wcem-status" name="status">
			<option value=""><?php esc_html_e( 'All statuses', 'woo-custom-email-templates' ); ?></option>
			<option value="publish" <?php selected( $wcem_status, 'publish' ); ?>><?php esc_html_e( 'Active', 'woo-custom-email-templates' ); ?></option>
			<option value="draft" <?php selected( $wcem_status, 'draft' ); ?>><?php esc_html_e( 'Draft', 'woo-custom-email-templates' ); ?></option>
			<option value="private" <?php selected( $wcem_status, 'private' ); ?>><?php esc_html_e( 'Inactive', 'woo-custom-email-templates' ); ?></option>
		</select>
		<button class="button"><?php esc_html_e( 'Filter', 'woo-custom-email-templates' ); ?></button>
	</form>

	<?php if ( ! $wcem_page_of ) : ?>
		<div class="wcem-empty">
			<span class="dashicons dashicons-email-alt"></span>
			<h2><?php esc_html_e( 'No templates match', 'woo-custom-email-templates' ); ?></h2>
			<p><?php esc_html_e( 'Try a different search, or create a new template.', 'woo-custom-email-templates' ); ?></p>
		</div>
	<?php else : ?>
		<table class="wp-list-table widefat fixed striped wcem-table">
			<thead>
				<tr>
					<th><?php echo wp_kses_post( $wcem_sort_link( 'name', __( 'Name', 'woo-custom-email-templates' ), $wcem_orderby, $wcem_order ) ); ?></th>
					<th><?php esc_html_e( 'Used By', 'woo-custom-email-templates' ); ?></th>
					<th><?php echo wp_kses_post( $wcem_sort_link( 'status', __( 'Status', 'woo-custom-email-templates' ), $wcem_orderby, $wcem_order ) ); ?></th>
					<th><?php echo wp_kses_post( $wcem_sort_link( 'modified', __( 'Last Modified', 'woo-custom-email-templates' ), $wcem_orderby, $wcem_order ) ); ?></th>
					<th><?php esc_html_e( 'Actions', 'woo-custom-email-templates' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $wcem_page_of as $wcem_template ) : ?>
					<?php $wcem_used_by = EmailManager::emails_using( (int) $wcem_template['id'] ); ?>
					<tr data-template-id="<?php echo esc_attr( $wcem_template['id'] ); ?>">
						<td>
							<strong><a href="<?php echo esc_url( Plugin::url( 'template-edit', array( 'template' => $wcem_template['id'] ) ) ); ?>"><?php echo esc_html( $wcem_template['name'] ); ?></a></strong>
							<div class="wcem-muted"><?php echo esc_html( $wcem_template['description'] ); ?></div>
						</td>
						<td><?php echo $wcem_used_by ? esc_html( implode( ', ', $wcem_used_by ) ) : '—'; ?></td>
						<td><span class="wcem-badge wcem-badge--<?php echo esc_attr( $wcem_template['status'] ); ?>"><?php echo esc_html( TemplateRepository::status_label( $wcem_template['status'] ) ); ?></span></td>
						<td><?php echo esc_html( mysql2date( get_option( 'date_format' ), $wcem_template['modified'] ) ); ?></td>
						<td class="wcem-row-actions">
							<a href="<?php echo esc_url( Plugin::url( 'template-edit', array( 'template' => $wcem_template['id'] ) ) ); ?>"><?php esc_html_e( 'Edit', 'woo-custom-email-templates' ); ?></a>
							<button type="button" class="button-link wcem-js-duplicate" data-id="<?php echo esc_attr( $wcem_template['id'] ); ?>"><?php esc_html_e( 'Duplicate', 'woo-custom-email-templates' ); ?></button>
							<button type="button" class="button-link wcem-js-delete" data-id="<?php echo esc_attr( $wcem_template['id'] ); ?>"><?php esc_html_e( 'Delete', 'woo-custom-email-templates' ); ?></button>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<?php if ( $wcem_pages > 1 ) : ?>
			<div class="tablenav"><div class="tablenav-pages">
				<?php
				echo wp_kses_post(
					paginate_links(
						array(
							'base'      => esc_url_raw( add_query_arg( 'paged', '%#%' ) ),
							'format'    => '',
							'total'     => $wcem_pages,
							'current'   => $wcem_paged,
							'prev_text' => '‹',
							'next_text' => '›',
						)
					)
				);
				?>
			</div></div>
		<?php endif; ?>
	<?php endif; ?>
</div>
