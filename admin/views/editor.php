<?php
/**
 * The visual builder: block palette, canvas, settings panel.
 *
 * Serves both templates and reusable components — the storage layer is the
 * same, so the only differences are which repository is read and which
 * panels make sense (a component has no subject line and no versions).
 *
 * @package Woo_Custom_Email_Templates
 */

use WCEM\Builder\Blocks;
use WCEM\Core\Plugin;
use WCEM\Email\EmailSender;
use WCEM\Email\EmailTags;
use WCEM\Templates\ComponentRepository;
use WCEM\Templates\TemplateRepository;
use WCEM\Templates\Versions;

defined( 'ABSPATH' ) || exit;

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only screen selector, no state change.
$wcem_page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
$wcem_kind = 'wcem-component-edit' === $wcem_page ? 'component' : 'template';
$wcem_repo = 'component' === $wcem_kind ? ComponentRepository::class : TemplateRepository::class;

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only screen selector, no state change.
$wcem_id     = isset( $_GET[ $wcem_kind ] ) ? absint( $_GET[ $wcem_kind ] ) : 0;
$wcem_record = $wcem_id ? $wcem_repo::get( $wcem_id ) : null;

$wcem_registry = Blocks::registry();
$wcem_types    = array_keys( $wcem_registry );

$wcem_boot = array(
	'kind'         => $wcem_kind,
	'id'           => $wcem_record['id'] ?? 0,
	'name'         => $wcem_record['name'] ?? ( 'component' === $wcem_kind
		? __( 'New Component', 'woo-custom-email-templates' )
		: __( 'New Template', 'woo-custom-email-templates' ) ),
	'description'  => $wcem_record['description'] ?? '',
	'status'       => $wcem_record['status'] ?? 'draft',
	'subject'      => $wcem_record['subject'] ?? '',
	'preview_text' => $wcem_record['preview_text'] ?? '',
	'blocks'       => $wcem_record['blocks'] ?? array(),
	'styles'       => wp_parse_args( $wcem_record['styles'] ?? array(), TemplateRepository::default_styles() ),
	'registry'     => $wcem_registry,
	'defaults'     => array_combine( $wcem_types, array_map( array( Blocks::class, 'defaults' ), $wcem_types ) ),
	'tagGroups'    => EmailTags::groups(),
	'orders'       => EmailSender::recent_orders(),
	// Components are not offered inside the component builder itself: a
	// component made of components would need recursive expansion for no
	// real gain at this size.
	'components'   => 'component' === $wcem_kind ? array() : ComponentRepository::for_editor(),
	'dashboardUrl' => Plugin::url(),
	'listUrl'      => Plugin::url( 'component' === $wcem_kind ? 'components' : 'templates' ),
);

$wcem_versions = ( 'template' === $wcem_kind && $wcem_id ) ? Versions::all( $wcem_id ) : array();
?>
<div class="wrap wcem-wrap wcem-wrap--editor">
	<div id="wcem-editor" class="wcem-editor" data-boot="<?php echo esc_attr( wp_json_encode( $wcem_boot ) ); ?>">

		<div class="wcem-editor__toolbar">
			<a href="<?php echo esc_url( $wcem_boot['listUrl'] ); ?>" class="wcem-editor__back">←
				<?php echo 'component' === $wcem_kind ? esc_html__( 'Components', 'woo-custom-email-templates' ) : esc_html__( 'Templates', 'woo-custom-email-templates' ); ?>
			</a>
			<label class="screen-reader-text" for="wcem-f-name"><?php esc_html_e( 'Name', 'woo-custom-email-templates' ); ?></label>
			<input type="text" class="wcem-editor__name" id="wcem-f-name" value="<?php echo esc_attr( $wcem_boot['name'] ); ?>" />
			<div class="wcem-editor__devices" role="group" aria-label="<?php esc_attr_e( 'Preview size', 'woo-custom-email-templates' ); ?>">
				<button type="button" class="wcem-device active" data-device="desktop" aria-pressed="true" title="<?php esc_attr_e( 'Desktop', 'woo-custom-email-templates' ); ?>"><span class="dashicons dashicons-desktop"></span><span class="screen-reader-text"><?php esc_html_e( 'Desktop', 'woo-custom-email-templates' ); ?></span></button>
				<button type="button" class="wcem-device" data-device="tablet" aria-pressed="false" title="<?php esc_attr_e( 'Tablet', 'woo-custom-email-templates' ); ?>"><span class="dashicons dashicons-tablet"></span><span class="screen-reader-text"><?php esc_html_e( 'Tablet', 'woo-custom-email-templates' ); ?></span></button>
				<button type="button" class="wcem-device" data-device="mobile" aria-pressed="false" title="<?php esc_attr_e( 'Mobile', 'woo-custom-email-templates' ); ?>"><span class="dashicons dashicons-smartphone"></span><span class="screen-reader-text"><?php esc_html_e( 'Mobile', 'woo-custom-email-templates' ); ?></span></button>
			</div>
			<div class="wcem-editor__history" role="group" aria-label="<?php esc_attr_e( 'Undo and redo', 'woo-custom-email-templates' ); ?>">
				<button type="button" class="button wcem-icon-button" id="wcem-btn-undo" disabled title="<?php esc_attr_e( 'Undo (Ctrl+Z)', 'woo-custom-email-templates' ); ?>">
					<span class="dashicons dashicons-undo"></span><span class="screen-reader-text"><?php esc_html_e( 'Undo', 'woo-custom-email-templates' ); ?></span>
				</button>
				<button type="button" class="button wcem-icon-button" id="wcem-btn-redo" disabled title="<?php esc_attr_e( 'Redo (Ctrl+Shift+Z)', 'woo-custom-email-templates' ); ?>">
					<span class="dashicons dashicons-redo"></span><span class="screen-reader-text"><?php esc_html_e( 'Redo', 'woo-custom-email-templates' ); ?></span>
				</button>
			</div>
			<div class="wcem-editor__actions">
				<button type="button" class="button" id="wcem-btn-preview"><?php esc_html_e( 'Preview', 'woo-custom-email-templates' ); ?></button>
				<button type="button" class="button" id="wcem-btn-test"><?php esc_html_e( 'Send Test', 'woo-custom-email-templates' ); ?></button>
				<label class="screen-reader-text" for="wcem-f-status"><?php esc_html_e( 'Status', 'woo-custom-email-templates' ); ?></label>
				<select id="wcem-f-status">
					<option value="publish" <?php selected( $wcem_boot['status'], 'publish' ); ?>><?php esc_html_e( 'Active', 'woo-custom-email-templates' ); ?></option>
					<option value="draft" <?php selected( $wcem_boot['status'], 'draft' ); ?>><?php esc_html_e( 'Draft', 'woo-custom-email-templates' ); ?></option>
					<option value="private" <?php selected( $wcem_boot['status'], 'private' ); ?>><?php esc_html_e( 'Inactive', 'woo-custom-email-templates' ); ?></option>
				</select>
				<button type="button" class="button button-primary" id="wcem-btn-save"><?php esc_html_e( 'Save', 'woo-custom-email-templates' ); ?></button>
			</div>
		</div>

		<div class="wcem-editor__body">
			<div class="wcem-editor__blocks">
				<h3><?php esc_html_e( 'Blocks', 'woo-custom-email-templates' ); ?></h3>
				<div id="wcem-palette" class="wcem-palette"></div>

				<?php if ( 'template' === $wcem_kind ) : ?>
					<h3><?php esc_html_e( 'Components', 'woo-custom-email-templates' ); ?></h3>
					<div id="wcem-components" class="wcem-palette"></div>
				<?php endif; ?>

				<h3><?php esc_html_e( 'Insert Dynamic Data', 'woo-custom-email-templates' ); ?></h3>
				<div id="wcem-tags" class="wcem-tags"></div>
			</div>

			<div class="wcem-editor__canvas-wrap">
				<div class="wcem-canvas-frame" id="wcem-canvas-frame">
					<div id="wcem-canvas" class="wcem-canvas"></div>
				</div>
			</div>

			<div class="wcem-editor__settings" id="wcem-settings">
				<div class="wcem-settings-tabs" role="tablist">
					<button type="button" class="active" data-tab="block" role="tab" aria-selected="true"><?php esc_html_e( 'Block', 'woo-custom-email-templates' ); ?></button>
					<button type="button" data-tab="styles" role="tab" aria-selected="false"><?php esc_html_e( 'Design', 'woo-custom-email-templates' ); ?></button>
					<button type="button" data-tab="email" role="tab" aria-selected="false"><?php esc_html_e( 'Email', 'woo-custom-email-templates' ); ?></button>
					<?php if ( 'template' === $wcem_kind && $wcem_id ) : ?>
						<button type="button" data-tab="versions" role="tab" aria-selected="false"><?php esc_html_e( 'Versions', 'woo-custom-email-templates' ); ?></button>
					<?php endif; ?>
				</div>

				<div id="wcem-tab-block" class="wcem-settings-panel wcem-settings-panel--active" role="tabpanel">
					<p class="wcem-muted"><?php esc_html_e( 'Select a block on the canvas to edit its settings.', 'woo-custom-email-templates' ); ?></p>
				</div>

				<div id="wcem-tab-styles" class="wcem-settings-panel" role="tabpanel"></div>

				<div id="wcem-tab-email" class="wcem-settings-panel" role="tabpanel">
					<label class="wcem-field"><?php esc_html_e( 'Description', 'woo-custom-email-templates' ); ?>
						<textarea id="wcem-f-description" rows="2"><?php echo esc_textarea( $wcem_boot['description'] ); ?></textarea>
					</label>
					<?php if ( 'template' === $wcem_kind ) : ?>
						<label class="wcem-field"><?php esc_html_e( 'Subject (leave blank to keep WooCommerce\'s own)', 'woo-custom-email-templates' ); ?>
							<input type="text" id="wcem-f-subject" value="<?php echo esc_attr( $wcem_boot['subject'] ); ?>" />
						</label>
						<label class="wcem-field"><?php esc_html_e( 'Preheader / preview text', 'woo-custom-email-templates' ); ?>
							<input type="text" id="wcem-f-preview-text" value="<?php echo esc_attr( $wcem_boot['preview_text'] ); ?>" />
							<span class="wcem-muted"><?php esc_html_e( 'The grey line an inbox shows next to the subject. Supports {tags}.', 'woo-custom-email-templates' ); ?></span>
						</label>
						<?php if ( $wcem_boot['components'] ) : ?>
							<p><button type="button" class="button" id="wcem-btn-sync"><?php esc_html_e( 'Re-sync components', 'woo-custom-email-templates' ); ?></button></p>
						<?php endif; ?>
					<?php endif; ?>
				</div>

				<?php if ( 'template' === $wcem_kind && $wcem_id ) : ?>
					<div id="wcem-tab-versions" class="wcem-settings-panel" role="tabpanel">
						<h4><?php esc_html_e( 'Version History', 'woo-custom-email-templates' ); ?></h4>
						<?php if ( ! $wcem_versions ) : ?>
							<p class="wcem-muted"><?php esc_html_e( 'No earlier versions yet. Each save you make from now on is kept here.', 'woo-custom-email-templates' ); ?></p>
						<?php else : ?>
							<ul class="wcem-versions">
								<?php foreach ( $wcem_versions as $wcem_version ) : ?>
									<li class="wcem-versions__item">
										<div>
											<strong><?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $wcem_version['date'] ) ); ?></strong>
											<div class="wcem-muted">
												<?php
												printf(
													/* translators: 1: author name, 2: number of blocks */
													esc_html__( '%1$s · %2$d blocks', 'woo-custom-email-templates' ),
													esc_html( $wcem_version['author'] ),
													(int) $wcem_version['blocks']
												);
												?>
											</div>
										</div>
										<button type="button" class="button button-small wcem-js-restore" data-revision="<?php echo esc_attr( $wcem_version['id'] ); ?>"><?php esc_html_e( 'Restore', 'woo-custom-email-templates' ); ?></button>
									</li>
								<?php endforeach; ?>
							</ul>
							<p class="wcem-muted"><?php esc_html_e( 'Restoring replaces the design only — the subject line and preheader are left as they are.', 'woo-custom-email-templates' ); ?></p>
						<?php endif; ?>
					</div>
				<?php endif; ?>
			</div>
		</div>
	</div>

	<div id="wcem-modal-preview" class="wcem-modal" hidden>
		<div class="wcem-modal__panel wcem-modal__panel--wide" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'Email preview', 'woo-custom-email-templates' ); ?>">
			<div class="wcem-modal__head">
				<strong><?php esc_html_e( 'Preview', 'woo-custom-email-templates' ); ?></strong>
				<label class="screen-reader-text" for="wcem-preview-order"><?php esc_html_e( 'Preview data', 'woo-custom-email-templates' ); ?></label>
				<select id="wcem-preview-order">
					<option value="0"><?php esc_html_e( 'Sample data', 'woo-custom-email-templates' ); ?></option>
					<?php foreach ( $wcem_boot['orders'] as $wcem_order_id => $wcem_label ) : ?>
						<option value="<?php echo esc_attr( $wcem_order_id ); ?>"><?php echo esc_html( $wcem_label ); ?></option>
					<?php endforeach; ?>
				</select>
				<button type="button" class="wcem-modal__close" data-close aria-label="<?php esc_attr_e( 'Close', 'woo-custom-email-templates' ); ?>">&times;</button>
			</div>
			<div class="wcem-modal__body">
				<iframe id="wcem-preview-frame" title="<?php esc_attr_e( 'Email preview', 'woo-custom-email-templates' ); ?>"></iframe>
			</div>
		</div>
	</div>

	<div id="wcem-modal-test" class="wcem-modal" hidden>
		<div class="wcem-modal__panel" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'Send test email', 'woo-custom-email-templates' ); ?>">
			<div class="wcem-modal__head">
				<strong><?php esc_html_e( 'Send Test Email', 'woo-custom-email-templates' ); ?></strong>
				<button type="button" class="wcem-modal__close" data-close aria-label="<?php esc_attr_e( 'Close', 'woo-custom-email-templates' ); ?>">&times;</button>
			</div>
			<div class="wcem-modal__body">
				<label class="wcem-field"><?php esc_html_e( 'Send to', 'woo-custom-email-templates' ); ?>
					<input type="email" id="wcem-test-email" value="<?php echo esc_attr( wp_get_current_user()->user_email ); ?>" />
				</label>
				<button type="button" class="button button-primary" id="wcem-btn-send-test"><?php esc_html_e( 'Send test', 'woo-custom-email-templates' ); ?></button>
			</div>
		</div>
	</div>
</div>
