<?php
/**
 * First-run wizard. One screen, three decisions, one submit — a
 * multi-page flow would only add state to lose.
 *
 * @package Woo_Custom_Email_Templates
 */

use WCEM\Admin\Admin;
use WCEM\Core\Plugin;
use WCEM\Email\EmailManager;
use WCEM\Templates\StarterTemplates;

defined( 'ABSPATH' ) || exit;

$wcem_library = StarterTemplates::library();
$wcem_emails  = EmailManager::all_emails();
$wcem_suggest = array( 'customer_processing_order', 'customer_completed_order', 'customer_invoice' );
?>
<div class="wrap wcem-wrap wcem-wrap--onboarding">
	<?php
	Admin::header(
		__( 'Welcome to WooCommerce Custom Email Templates', 'woo-custom-email-templates' ),
		__( 'Three quick choices and your first branded email is ready to edit.', 'woo-custom-email-templates' )
	);
	?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="wcem-onboarding">
		<input type="hidden" name="action" value="wcem_onboarding" />
		<?php wp_nonce_field( 'wcem_onboarding' ); ?>

		<fieldset class="wcem-onboarding__step">
			<legend><span class="wcem-step-number">1</span> <?php esc_html_e( 'Choose your starting design', 'woo-custom-email-templates' ); ?></legend>
			<div class="wcem-choice-grid">
				<?php foreach ( $wcem_library as $wcem_slug => $wcem_starter ) : ?>
					<label class="wcem-choice">
						<input type="radio" name="slug" value="<?php echo esc_attr( $wcem_slug ); ?>" <?php checked( 'modern-store', $wcem_slug ); ?> />
						<span class="wcem-choice__body">
							<strong><?php echo esc_html( $wcem_starter['name'] ); ?></strong>
							<span class="wcem-muted"><?php echo esc_html( $wcem_starter['description'] ); ?></span>
						</span>
					</label>
				<?php endforeach; ?>
			</div>
		</fieldset>

		<fieldset class="wcem-onboarding__step">
			<legend><span class="wcem-step-number">2</span> <?php esc_html_e( 'Pick your brand colour', 'woo-custom-email-templates' ); ?></legend>
			<p class="wcem-muted"><?php esc_html_e( 'Used for buttons and links. You can change every colour later in the builder.', 'woo-custom-email-templates' ); ?></p>
			<label class="wcem-field wcem-field--color" for="wcem-brand">
				<?php esc_html_e( 'Brand colour', 'woo-custom-email-templates' ); ?>
				<input type="color" id="wcem-brand" name="brand" value="#2563eb" />
			</label>
		</fieldset>

		<fieldset class="wcem-onboarding__step">
			<legend><span class="wcem-step-number">3</span> <?php esc_html_e( 'Which emails should use it?', 'woo-custom-email-templates' ); ?></legend>
			<?php if ( ! $wcem_emails ) : ?>
				<p class="wcem-muted"><?php esc_html_e( 'No WooCommerce email types were found yet. You can assign templates later from the Assignments screen.', 'woo-custom-email-templates' ); ?></p>
			<?php else : ?>
				<p class="wcem-muted"><?php esc_html_e( 'Each one you tick is overridden immediately. Untick anything and WooCommerce\'s own template comes straight back.', 'woo-custom-email-templates' ); ?></p>
				<div class="wcem-choice-grid wcem-choice-grid--compact">
					<?php foreach ( $wcem_emails as $wcem_id => $wcem_email ) : ?>
						<label class="wcem-choice wcem-choice--check">
							<input type="checkbox" name="emails[]" value="<?php echo esc_attr( $wcem_id ); ?>" <?php checked( in_array( $wcem_id, $wcem_suggest, true ) ); ?> />
							<span class="wcem-choice__body">
								<strong><?php echo esc_html( $wcem_email->get_title() ); ?></strong>
								<span class="wcem-muted"><code><?php echo esc_html( $wcem_id ); ?></code></span>
							</span>
						</label>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</fieldset>

		<p class="wcem-onboarding__actions">
			<button class="button button-primary button-hero"><?php esc_html_e( 'Get Started', 'woo-custom-email-templates' ); ?></button>
			<button type="submit" name="skip" value="1" class="button-link"><?php esc_html_e( 'Skip for now', 'woo-custom-email-templates' ); ?></button>
		</p>
	</form>

	<p class="wcem-muted">
		<a href="<?php echo esc_url( Plugin::url( 'templates' ) ); ?>"><?php esc_html_e( 'Go straight to Templates →', 'woo-custom-email-templates' ); ?></a>
	</p>
</div>
