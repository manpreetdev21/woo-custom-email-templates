<?php
/**
 * Reusable components: named groups of blocks that can be inserted into
 * any template and later re-synced.
 *
 * Storage is identical to a template — the same JSON body, the same
 * sanitizers, the same CRUD — so this only swaps the post type. Global
 * styles are stored but ignored on insert: a component adopts the styles
 * of whatever template it lands in.
 *
 * @package Woo_Custom_Email_Templates
 */

namespace WCEM\Templates;

defined( 'ABSPATH' ) || exit;

final class ComponentRepository extends TemplateRepository {

	public const POST_TYPE = 'wcem_component';

	/**
	 * Human-readable post type label.
	 */
	public static function label(): string {
		return \__( 'Email Components', 'woo-custom-email-templates' );
	}

	/**
	 * Every component with its blocks, for the editor's palette.
	 *
	 * @return array<int, array{id: int, name: string, blocks: array}>
	 */
	public static function for_editor(): array {
		$out = array();

		foreach ( self::all() as $component ) {
			$out[] = array(
				'id'     => (int) $component['id'],
				'name'   => $component['name'],
				'blocks' => $component['blocks'],
			);
		}

		return $out;
	}
}
