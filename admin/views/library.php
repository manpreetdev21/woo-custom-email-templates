<?php
/**
 * Template library: the built-in starter layouts, each previewed live from
 * the same renderer that builds the real email.
 *
 * @package Woo_Custom_Email_Templates
 */

use WCEM\Admin\Admin;
use WCEM\Templates\StarterTemplates;
use WCEM\Templates\TemplateRenderer;

defined( 'ABSPATH' ) || exit;

$wcem_library = StarterTemplates::library();

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only display filter.
$wcem_category = isset( $_GET['category'] ) ? sanitize_text_field( wp_unslash( $_GET['category'] ) ) : '';

$wcem_categories = array_values( array_unique( wp_list_pluck( $wcem_library, 'category' ) ) );
sort( $wcem_categories );

if ( $wcem_category ) {
	$wcem_library = array_filter( $wcem_library, static fn( $t ) => $t['category'] === $wcem_category );
}
?>
<div class="wrap wcem-wrap">
	<?php
	Admin::header(
		__( 'Template Library', 'woo-custom-email-templates' ),
		__( 'Start from a ready-made layout. Every one is a normal template once created — edit it however you like.', 'woo-custom-email-templates' )
	);
	Admin::flash();
	?>

	<form method="get" class="wcem-toolbar">
		<input type="hidden" name="page" value="wcem-library" />
		<label class="screen-reader-text" for="wcem-category"><?php esc_html_e( 'Filter by category', 'woo-custom-email-templates' ); ?></label>
		<select id="wcem-category" name="category">
			<option value=""><?php esc_html_e( 'All categories', 'woo-custom-email-templates' ); ?></option>
			<?php foreach ( $wcem_categories as $wcem_cat ) : ?>
				<option value="<?php echo esc_attr( $wcem_cat ); ?>" <?php selected( $wcem_category, $wcem_cat ); ?>><?php echo esc_html( $wcem_cat ); ?></option>
			<?php endforeach; ?>
		</select>
		<button class="button"><?php esc_html_e( 'Filter', 'woo-custom-email-templates' ); ?></button>
	</form>

	<div class="wcem-cards wcem-cards--library">
		<?php foreach ( $wcem_library as $wcem_slug => $wcem_starter ) : ?>
			<?php
			$wcem_html = TemplateRenderer::render(
				array(
					'blocks' => $wcem_starter['blocks'],
					'styles' => $wcem_starter['styles'],
				),
				array(
					'order'  => null,
					'user'   => null,
					// Library cards are previews: let the WooCommerce blocks
					// show their demo rows so the layout reads as a real email.
					'sample' => true,
				)
			);
			?>
			<div class="wcem-card wcem-card--library">
				<div class="wcem-library-preview">
					<iframe
						class="wcem-library-preview__frame"
						title="<?php echo esc_attr( sprintf( /* translators: %s: template name */ __( '%s preview', 'woo-custom-email-templates' ), $wcem_starter['name'] ) ); ?>"
						srcdoc="<?php echo esc_attr( $wcem_html ); ?>"
						loading="lazy"
						scrolling="no"
						tabindex="-1"
					></iframe>
				</div>
				<div class="wcem-card__head">
					<strong><?php echo esc_html( $wcem_starter['name'] ); ?></strong>
					<span class="wcem-badge wcem-badge--default"><?php echo esc_html( $wcem_starter['category'] ); ?></span>
				</div>
				<p class="wcem-card__desc"><?php echo esc_html( $wcem_starter['description'] ); ?></p>
				<div class="wcem-card__foot">
					<span><?php printf( /* translators: %d: number of blocks */ esc_html__( '%d blocks', 'woo-custom-email-templates' ), count( $wcem_starter['blocks'] ) ); ?></span>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="wcem_use_starter" />
						<input type="hidden" name="slug" value="<?php echo esc_attr( $wcem_slug ); ?>" />
						<?php wp_nonce_field( 'wcem_use_starter' ); ?>
						<button class="button button-primary button-small"><?php esc_html_e( 'Use Template', 'woo-custom-email-templates' ); ?></button>
					</form>
				</div>
			</div>
		<?php endforeach; ?>
	</div>
</div>
