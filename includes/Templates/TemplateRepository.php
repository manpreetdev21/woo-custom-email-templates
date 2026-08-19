<?php
/**
 * Template storage: a private custom post type.
 *
 * post_title   => template name
 * post_content => JSON { blocks: [...], styles: {...} }
 * post_excerpt => description
 * post_status  => publish (active) | draft | private (inactive)
 *
 * Every self-reference uses `static::` rather than `self::` so
 * ComponentRepository can inherit the whole CRUD layer and only swap the
 * post type it operates on.
 *
 * @package Woo_Custom_Email_Templates
 */

namespace WCEM\Templates;

use WCEM\Builder\Blocks;
use WCEM\Core\Plugin;
use WP_Error;

defined( 'ABSPATH' ) || exit;

class TemplateRepository {

	public const POST_TYPE = 'wcem_template';

	/**
	 * Human-readable post type label.
	 */
	public static function label(): string {
		return \__( 'Email Templates', 'woo-custom-email-templates' );
	}

	/**
	 * Meta key => sanitize callback.
	 *
	 * @return array<string, callable-string>
	 */
	public static function meta_fields(): array {
		return array(
			'_wcem_subject'      => 'sanitize_text_field',
			'_wcem_preview_text' => 'sanitize_text_field',
		);
	}

	/**
	 * Registers the post type and its meta.
	 */
	public static function init(): void {
		\register_post_type(
			static::POST_TYPE,
			array(
				'label'               => static::label(),
				'public'              => false,
				'show_ui'             => false, // We render our own screens.
				'show_in_rest'        => false,
				'exclude_from_search' => true,
				'hierarchical'        => false,
				// 'revisions' gives the version history in Versions:: for free —
				// WordPress already snapshots post_content, which holds the
				// entire blocks + styles payload.
				'supports'            => array( 'title', 'author', 'revisions' ),
				'capability_type'     => 'post',
				'map_meta_cap'        => true,
				'delete_with_user'    => false,
			)
		);

		$auth = static function (): bool {
			return \current_user_can( Plugin::cap() );
		};

		foreach ( static::meta_fields() as $key => $sanitize ) {
			\register_post_meta(
				static::POST_TYPE,
				$key,
				array(
					'single'            => true,
					'type'              => 'string',
					'show_in_rest'      => false,
					'sanitize_callback' => $sanitize,
					'auth_callback'     => $auth,
				)
			);
		}
	}

	/**
	 * Default global style values. Deliberately email-safe fonts and
	 * web-safe fallbacks only.
	 *
	 * @return array<string, mixed>
	 */
	public static function default_styles(): array {
		return array(
			'width'         => 600,
			'bg_color'      => '#f4f4f5',
			'content_bg'    => '#ffffff',
			'text_color'    => '#3f3f46',
			'heading_color' => '#18181b',
			'link_color'    => '#2563eb',
			'button_bg'     => '#18181b',
			'button_text'   => '#ffffff',
			'footer_bg'     => '#18181b',
			'footer_text'   => '#a1a1aa',
			'font_family'   => 'Arial, Helvetica, sans-serif',
			'body_size'     => 15,
			'heading_size'  => 24,
			'line_height'   => 1.6,
			'button_size'   => 15,
			'radius'        => 6,
			'padding'       => 32,
		);
	}

	/**
	 * Decodes the JSON body into [ blocks, styles ], filling in any missing
	 * style keys from defaults so older/edited templates never render blank.
	 *
	 * @param string $raw_content post_content JSON.
	 * @return array{blocks: array, styles: array}
	 */
	public static function decode_body( string $raw_content ): array {
		$data = \json_decode( $raw_content, true );

		if ( ! \is_array( $data ) ) {
			$data = array();
		}

		$blocks = isset( $data['blocks'] ) && \is_array( $data['blocks'] ) ? $data['blocks'] : array();
		$styles = isset( $data['styles'] ) && \is_array( $data['styles'] ) ? $data['styles'] : array();

		return array(
			'blocks' => $blocks,
			'styles' => \wp_parse_args( $styles, static::default_styles() ),
		);
	}

	/**
	 * Sanitizes one block: known keys only, settings run through
	 * Blocks::sanitize_settings() for its type.
	 *
	 * @param mixed $block Raw block.
	 * @return array|null Null when the block type is unknown.
	 */
	public static function sanitize_block( $block ): ?array {
		if ( ! \is_array( $block ) || empty( $block['type'] ) ) {
			return null;
		}

		$type = \sanitize_key( $block['type'] );

		if ( ! Blocks::exists( $type ) ) {
			return null;
		}

		return array(
			'id'       => isset( $block['id'] ) ? \sanitize_key( $block['id'] ) : \wp_generate_password( 8, false ),
			'type'     => $type,
			// Component instance this block was expanded from, 0 when authored directly.
			'origin'   => isset( $block['origin'] ) ? \absint( $block['origin'] ) : 0,
			'settings' => Blocks::sanitize_settings( $type, (array) ( $block['settings'] ?? array() ) ),
		);
	}

	/**
	 * Sanitizes the whole blocks + styles payload and encodes it back to JSON.
	 *
	 * @param array $body Raw [ blocks, styles ] input.
	 */
	public static function sanitize_body( array $body ): string {
		$blocks_in = \is_array( $body['blocks'] ?? null ) ? $body['blocks'] : array();
		$blocks    = array();

		foreach ( $blocks_in as $block ) {
			$clean = static::sanitize_block( $block );

			if ( $clean ) {
				$blocks[] = $clean;
			}
		}

		$styles = static::sanitize_styles( \is_array( $body['styles'] ?? null ) ? $body['styles'] : array() );

		return (string) \wp_json_encode(
			array(
				'blocks' => $blocks,
				'styles' => $styles,
			)
		);
	}

	/**
	 * Sanitizes the global style settings.
	 *
	 * @param array $styles Raw input.
	 * @return array<string, mixed>
	 */
	public static function sanitize_styles( array $styles ): array {
		$defaults = static::default_styles();
		$clean    = array();

		$colors = array( 'bg_color', 'content_bg', 'text_color', 'heading_color', 'link_color', 'button_bg', 'button_text', 'footer_bg', 'footer_text' );
		$ints   = array( 'width', 'body_size', 'heading_size', 'button_size', 'radius', 'padding' );

		foreach ( $defaults as $key => $default ) {
			if ( \in_array( $key, $colors, true ) ) {
				$value         = isset( $styles[ $key ] ) ? \sanitize_hex_color( $styles[ $key ] ) : '';
				$clean[ $key ] = $value ? $value : $default;
			} elseif ( \in_array( $key, $ints, true ) ) {
				$clean[ $key ] = isset( $styles[ $key ] ) && '' !== $styles[ $key ] ? \absint( $styles[ $key ] ) : $default;
			} elseif ( 'line_height' === $key ) {
				$value         = isset( $styles[ $key ] ) ? (float) $styles[ $key ] : 0;
				$clean[ $key ] = ( $value > 0 && $value < 4 ) ? $value : $default;
			} else {
				$clean[ $key ] = isset( $styles[ $key ] ) ? \sanitize_text_field( $styles[ $key ] ) : $default;
			}
		}

		return $clean;
	}

	/**
	 * Loads one template as a flat array.
	 *
	 * @param int $id Template post ID.
	 * @return array|null Null when the ID is not a template of this type.
	 */
	public static function get( int $id ): ?array {
		$post = \get_post( $id );

		if ( ! $post || static::POST_TYPE !== $post->post_type ) {
			return null;
		}

		$body = static::decode_body( (string) $post->post_content );

		$data = array(
			'id'          => $post->ID,
			'name'        => $post->post_title,
			'blocks'      => $body['blocks'],
			'styles'      => $body['styles'],
			'description' => $post->post_excerpt,
			'status'      => $post->post_status,
			'author'      => (int) $post->post_author,
			'modified'    => $post->post_modified,
		);

		foreach ( \array_keys( static::meta_fields() ) as $key ) {
			$data[ \substr( $key, 6 ) ] = \get_post_meta( $post->ID, $key, true );
		}

		return $data;
	}

	/**
	 * Creates or updates a template from untrusted input.
	 *
	 * @param array $input Raw field values.
	 * @return int|WP_Error Template ID.
	 */
	public static function save( array $input ) {
		$id   = isset( $input['id'] ) ? \absint( $input['id'] ) : 0;
		$name = \sanitize_text_field( $input['name'] ?? '' );

		if ( '' === \trim( $name ) ) {
			return new WP_Error( 'wcem_no_name', \__( 'Please give the template a name.', 'woo-custom-email-templates' ) );
		}

		$status  = $input['status'] ?? 'publish';
		$allowed = array( 'publish', 'draft', 'private' );

		if ( ! \in_array( $status, $allowed, true ) ) {
			$status = 'publish';
		}

		$postarr = array(
			'post_type'    => static::POST_TYPE,
			'post_title'   => $name,
			'post_content' => static::sanitize_body(
				array(
					'blocks' => $input['blocks'] ?? array(),
					'styles' => $input['styles'] ?? array(),
				)
			),
			'post_excerpt' => \sanitize_textarea_field( $input['description'] ?? '' ),
			'post_status'  => $status,
		);

		if ( $id && static::get( $id ) ) {
			$postarr['ID'] = $id;
			$result        = \wp_update_post( \wp_slash( $postarr ), true );
		} else {
			$result = \wp_insert_post( \wp_slash( $postarr ), true );
		}

		if ( \is_wp_error( $result ) ) {
			return $result;
		}

		$id = (int) $result;

		foreach ( static::meta_fields() as $key => $sanitize ) {
			$field = \substr( $key, 6 );

			if ( ! \array_key_exists( $field, $input ) ) {
				continue;
			}

			\update_post_meta( $id, $key, \call_user_func( $sanitize, $input[ $field ] ) );
		}

		return $id;
	}

	/**
	 * Copies a template, blocks and styles included.
	 *
	 * @param int $id Source template ID.
	 * @return int|WP_Error New template ID.
	 */
	public static function duplicate( int $id ) {
		$source = static::get( $id );

		if ( ! $source ) {
			return new WP_Error( 'wcem_not_found', \__( 'Template not found.', 'woo-custom-email-templates' ) );
		}

		$copy = $source;

		$copy['id'] = 0;
		/* translators: %s: original template name */
		$copy['name']   = \sprintf( \__( '%s Copy', 'woo-custom-email-templates' ), $source['name'] );
		$copy['status'] = 'draft'; // A copy should never go live by accident.

		return static::save( $copy );
	}

	/**
	 * Counts templates by status.
	 *
	 * @return array<string, int>
	 */
	public static function counts(): array {
		$counts = (array) \wp_count_posts( static::POST_TYPE );

		$out = array(
			'publish' => (int) ( $counts['publish'] ?? 0 ),
			'draft'   => (int) ( $counts['draft'] ?? 0 ),
			'private' => (int) ( $counts['private'] ?? 0 ),
		);

		$out['total'] = \array_sum( $out );

		return $out;
	}

	/**
	 * All templates as id => name, for select controls.
	 *
	 * @param bool $active_only Restrict to active templates.
	 * @return array<int, string>
	 */
	public static function options( bool $active_only = false ): array {
		$posts = \get_posts(
			array(
				'post_type'      => static::POST_TYPE,
				'post_status'    => $active_only ? array( 'publish' ) : array( 'publish', 'draft', 'private' ),
				'posts_per_page' => 200,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		$out = array();

		foreach ( $posts as $post ) {
			$out[ $post->ID ] = $post->post_title;
		}

		return $out;
	}

	/**
	 * All templates as flat arrays, for the dashboard and list screens.
	 *
	 * @return array<int, array>
	 */
	public static function all(): array {
		$out = array();

		foreach ( \array_keys( static::options() ) as $id ) {
			$template = static::get( (int) $id );

			if ( $template ) {
				$out[] = $template;
			}
		}

		return $out;
	}

	/**
	 * Human-readable status label.
	 *
	 * @param string $status Post status.
	 */
	public static function status_label( string $status ): string {
		return match ( $status ) {
			'publish' => \__( 'Active', 'woo-custom-email-templates' ),
			'draft'   => \__( 'Draft', 'woo-custom-email-templates' ),
			'private' => \__( 'Inactive', 'woo-custom-email-templates' ),
			default   => $status,
		};
	}
}
