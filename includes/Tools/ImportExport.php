<?php
/**
 * JSON export/import of templates. Imported data is never trusted: every
 * template is re-run through TemplateRepository::save()'s sanitizers and
 * always lands as a new draft.
 *
 * @package Woo_Custom_Email_Templates
 */

namespace WCEM\Tools;

use WCEM\Core\Plugin;
use WCEM\Templates\TemplateRepository;
use WP_Error;

defined( 'ABSPATH' ) || exit;

final class ImportExport {

	public const FORMAT = 'wcem';

	/** Largest import file accepted, in bytes. */
	public const MAX_IMPORT_BYTES = 2097152; // 2 MB.

	/**
	 * Builds the exportable payload for one or more templates.
	 *
	 * @param array<int, int> $ids Template IDs. Empty = all templates.
	 * @return array<string, mixed>
	 */
	public static function export_payload( array $ids = array() ): array {
		$ids = $ids ? \array_map( 'absint', $ids ) : \array_keys( TemplateRepository::options() );

		$templates = array();

		foreach ( $ids as $id ) {
			$template = TemplateRepository::get( (int) $id );

			if ( ! $template ) {
				continue;
			}

			unset( $template['id'], $template['author'], $template['modified'] );

			$templates[] = $template;
		}

		return array(
			'format'    => self::FORMAT,
			'version'   => Plugin::VERSION,
			'exported'  => \gmdate( 'c' ),
			'templates' => $templates,
		);
	}

	/**
	 * Reads and decodes an uploaded export file.
	 *
	 * @param array $file One entry from $_FILES.
	 * @return array|WP_Error Decoded payload.
	 */
	public static function read_upload( array $file ) {
		$tmp = $file['tmp_name'] ?? '';

		if ( ! $tmp || ! \is_uploaded_file( $tmp ) ) {
			return new WP_Error( 'wcem_no_file', \__( 'No file was uploaded.', 'woo-custom-email-templates' ) );
		}

		// Guard before reading: an export is a few KB of JSON, so anything
		// large is either not ours or an attempt to exhaust memory.
		if ( \filesize( $tmp ) > self::MAX_IMPORT_BYTES ) {
			return new WP_Error( 'wcem_file_too_large', \__( 'That file is too large to be a template export.', 'woo-custom-email-templates' ) );
		}

		$raw  = (string) \file_get_contents( $tmp ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- server-side temp path from $_FILES.
		$data = \json_decode( $raw, true );

		if ( ! \is_array( $data ) ) {
			return new WP_Error( 'wcem_invalid_file', \__( 'That file is not a valid template export.', 'woo-custom-email-templates' ) );
		}

		return $data;
	}

	/**
	 * Imports templates from a decoded export payload.
	 *
	 * @param array $data Decoded JSON.
	 * @return int|WP_Error Number of templates imported.
	 */
	public static function import( array $data ) {
		if ( self::FORMAT !== ( $data['format'] ?? '' ) || empty( $data['templates'] ) || ! \is_array( $data['templates'] ) ) {
			return new WP_Error( 'wcem_invalid_file', \__( 'That file is not a valid template export.', 'woo-custom-email-templates' ) );
		}

		$imported = 0;

		foreach ( $data['templates'] as $template ) {
			if ( ! \is_array( $template ) || empty( $template['name'] ) ) {
				continue;
			}

			// Never trust an ID from a file: always insert as a new template.
			$template['id']     = 0;
			$template['status'] = 'draft'; // Imports land as drafts so nothing goes live unreviewed.

			if ( ! \is_wp_error( TemplateRepository::save( $template ) ) ) {
				++$imported;
			}
		}

		return $imported;
	}
}
