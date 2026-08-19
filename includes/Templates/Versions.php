<?php
/**
 * Template version history.
 *
 * Built on WordPress post revisions rather than a bespoke version store:
 * post_content already holds the entire blocks + styles payload, so every
 * wp_update_post() snapshot is a complete, restorable version for free.
 *
 * Known ceiling: revisions cover title, body and description. The subject
 * and preheader live in post meta, which WordPress does not revision, so
 * restoring a version restores the design but leaves the current subject
 * line in place.
 *
 * @package Woo_Custom_Email_Templates
 */

namespace WCEM\Templates;

use WP_Error;

defined( 'ABSPATH' ) || exit;

final class Versions {

	/** How many versions the UI lists. */
	public const LIMIT = 25;

	/**
	 * Version history for one template, newest first.
	 *
	 * @param int $template_id Template ID.
	 * @return array<int, array{id: int, date: string, author: string, blocks: int}>
	 */
	public static function all( int $template_id ): array {
		if ( ! TemplateRepository::get( $template_id ) ) {
			return array();
		}

		$revisions = \wp_get_post_revisions(
			$template_id,
			array(
				'posts_per_page' => self::LIMIT,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);

		$out = array();

		foreach ( $revisions as $revision ) {
			$body = TemplateRepository::decode_body( (string) $revision->post_content );

			$out[] = array(
				'id'     => (int) $revision->ID,
				'date'   => (string) $revision->post_modified,
				'author' => (string) \get_the_author_meta( 'display_name', (int) $revision->post_author ),
				'blocks' => \count( $body['blocks'] ),
			);
		}

		return $out;
	}

	/**
	 * Restores a template to one of its versions.
	 *
	 * @param int $revision_id Revision post ID.
	 * @return int|WP_Error The template ID that was restored.
	 */
	public static function restore( int $revision_id ) {
		$revision = \wp_get_post_revision( $revision_id );

		if ( ! $revision ) {
			return new WP_Error( 'wcem_no_revision', \__( 'That version no longer exists.', 'woo-custom-email-templates' ) );
		}

		$template_id = (int) $revision->post_parent;

		// Never let a revision id from another post type reach wp_restore_post_revision().
		if ( ! TemplateRepository::get( $template_id ) ) {
			return new WP_Error( 'wcem_no_revision', \__( 'That version does not belong to a template.', 'woo-custom-email-templates' ) );
		}

		$restored = \wp_restore_post_revision( $revision_id );

		if ( ! $restored ) {
			return new WP_Error( 'wcem_restore_failed', \__( 'That version could not be restored.', 'woo-custom-email-templates' ) );
		}

		return $template_id;
	}
}
