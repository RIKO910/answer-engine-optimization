<?php
/**
 * Reusable schema templates with display rules and field mapping.
 *
 * A template is JSON-LD with placeholders, applied to every post that matches its
 * rules (post types, taxonomy terms or specific posts). Placeholders:
 *
 *   {{title}} {{excerpt}} {{content}} {{url}} {{image}} {{date_published}} {{date_modified}}
 *   {{author}} {{site_name}} {{site_url}} {{id}}
 *   {{meta:key}}        post meta
 *   {{acf:field}}       Advanced Custom Fields (get_field)
 *   {{mb:field}}        Meta Box (rwmb_meta)
 *   {{term:taxonomy}}   first term name      {{terms:taxonomy}} all term names, comma separated
 *
 * A string that is exactly one placeholder keeps the field's type (number, array).
 * Properties that end up empty are removed.
 *
 * @package AEO
 */

namespace AEO\Services;

defined( 'ABSPATH' ) || exit;

/**
 * Templates.
 */
class Schema_Templates {

	const OPTION = 'aeo_schema_templates';

	/**
	 * All templates.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function all() {
		$t = get_option( self::OPTION, array() );
		return is_array( $t ) ? array_values( $t ) : array();
	}

	/**
	 * Save all templates (sanitized).
	 *
	 * @param array<int, array<string, mixed>> $templates Templates.
	 * @return array<int, array<string, mixed>>|\WP_Error
	 */
	public static function save_all( array $templates ) {
		$clean = array();
		foreach ( $templates as $t ) {
			if ( ! is_array( $t ) ) {
				continue;
			}
			$json = is_string( $t['schema'] ?? null ) ? $t['schema'] : wp_json_encode( $t['schema'] ?? array() );
			$data = json_decode( (string) $json, true );
			if ( ! is_array( $data ) || empty( $data['@type'] ) ) {
				/* translators: %s: template name */
				return new \WP_Error( 'aeo_template_json', sprintf( __( 'Template "%s" is not valid JSON-LD (it needs an @type).', 'answer-engine-optimization' ), sanitize_text_field( (string) ( $t['name'] ?? '' ) ) ) );
			}
			$rules = (array) ( $t['rules'] ?? array() );
			$id    = ! empty( $t['id'] ) ? sanitize_key( $t['id'] ) : '';
			if ( '' === $id || isset( $clean[ $id ] ) ) {
				$id = 'tpl_' . strtolower( wp_generate_password( 8, false ) );
			}
			$clean[ $id ] = array(
				'id'      => $id,
				'name'    => sanitize_text_field( (string) ( $t['name'] ?? __( 'Untitled template', 'answer-engine-optimization' ) ) ),
				'enabled' => ! empty( $t['enabled'] ),
				'schema'  => wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ),
				'rules'   => array(
					'post_types' => array_values( array_filter( array_map( 'sanitize_key', (array) ( $rules['post_types'] ?? array() ) ) ) ),
					'terms'      => array_values( array_filter( array_map( 'sanitize_text_field', (array) ( $rules['terms'] ?? array() ) ) ) ),
					'post_ids'   => array_values( array_filter( array_map( 'intval', (array) ( $rules['post_ids'] ?? array() ) ) ) ),
				),
			);
		}
		$clean = array_values( $clean );
		update_option( self::OPTION, $clean, false );
		return $clean;
	}

	/**
	 * Does a template apply to a post?
	 *
	 * Rules of different kinds combine with AND; values within a kind with OR.
	 *
	 * @param array<string, mixed> $template Template.
	 * @param int                  $post_id  Post ID.
	 * @return bool
	 */
	public static function matches( array $template, $post_id ) {
		if ( empty( $template['enabled'] ) ) {
			return false;
		}
		$rules = (array) ( $template['rules'] ?? array() );
		if ( empty( $rules['post_types'] ) && empty( $rules['terms'] ) && empty( $rules['post_ids'] ) ) {
			return false;
		}
		if ( ! empty( $rules['post_ids'] ) && ! in_array( (int) $post_id, array_map( 'intval', $rules['post_ids'] ), true ) ) {
			return false;
		}
		if ( ! empty( $rules['post_types'] ) && ! in_array( get_post_type( $post_id ), $rules['post_types'], true ) ) {
			return false;
		}
		if ( ! empty( $rules['terms'] ) ) {
			$hit = false;
			foreach ( $rules['terms'] as $term ) {
				$parts = explode( ':', $term, 2 );
				if ( 2 === count( $parts ) && has_term( $parts[1], $parts[0], $post_id ) ) {
					$hit = true;
					break;
				}
			}
			if ( ! $hit ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Nodes from every matching template.
	 *
	 * @param int $post_id Post ID.
	 * @return array<int, array<string, mixed>>
	 */
	public static function nodes_for( $post_id ) {
		$nodes = array();
		foreach ( self::all() as $template ) {
			if ( self::matches( $template, $post_id ) ) {
				$node = self::render( (string) $template['schema'], $post_id );
				if ( $node ) {
					if ( empty( $node['@id'] ) ) {
						$node['@id'] = get_permalink( $post_id ) . '#' . $template['id'];
					}
					$nodes[] = $node;
				}
			}
		}
		return $nodes;
	}

	/**
	 * Render a template for a post.
	 *
	 * @param string $json    Template JSON.
	 * @param int    $post_id Post ID.
	 * @return array<string, mixed>
	 */
	public static function render( $json, $post_id ) {
		$data = json_decode( $json, true );
		if ( ! is_array( $data ) ) {
			return array();
		}
		$data = self::walk( $data, $post_id );
		unset( $data['@context'] );
		return is_array( $data ) ? $data : array();
	}

	/**
	 * Replace placeholders recursively and drop empties.
	 *
	 * @param mixed $value   Value.
	 * @param int   $post_id Post ID.
	 * @return mixed
	 */
	private static function walk( $value, $post_id ) {
		if ( is_array( $value ) ) {
			$out = array();
			foreach ( $value as $k => $v ) {
				$v = self::walk( $v, $post_id );
				if ( null === $v || '' === $v || array() === $v ) {
					continue;
				}
				$out[ $k ] = $v;
			}
			// A node with nothing but @type is meaningless.
			if ( isset( $out['@type'] ) && 1 === count( $out ) && ! is_int( key( $value ) ) ) {
				return null;
			}
			return $out;
		}
		if ( ! is_string( $value ) || false === strpos( $value, '{{' ) ) {
			return $value;
		}
		if ( preg_match( '/^\{\{\s*([a-z_]+(?::[^}]+)?)\s*\}\}$/i', $value, $m ) ) {
			return self::resolve( $m[1], $post_id );
		}
		return preg_replace_callback(
			'/\{\{\s*([a-z_]+(?::[^}]+)?)\s*\}\}/i',
			static function ( $m ) use ( $post_id ) {
				$v = self::resolve( $m[1], $post_id );
				return is_scalar( $v ) ? (string) $v : ( is_array( $v ) ? implode( ', ', array_filter( array_map( 'strval', array_filter( $v, 'is_scalar' ) ) ) ) : '' );
			},
			$value
		);
	}

	/**
	 * Resolve one placeholder.
	 *
	 * @param string $token   Token.
	 * @param int    $post_id Post ID.
	 * @return mixed
	 */
	public static function resolve( $token, $post_id ) {
		$parts = explode( ':', $token, 2 );
		$name  = strtolower( $parts[0] );
		$arg   = $parts[1] ?? '';
		$post  = get_post( $post_id );
		if ( ! $post ) {
			return '';
		}

		switch ( $name ) {
			case 'id':
				return (int) $post_id;
			case 'title':
				return wp_strip_all_tags( get_the_title( $post ) );
			case 'excerpt':
				return has_excerpt( $post ) ? wp_strip_all_tags( get_the_excerpt( $post ) ) : Schema_Service::plain_text( $post->post_content, 30 );
			case 'content':
				return Schema_Service::plain_text( $post->post_content, 300 );
			case 'url':
				return get_permalink( $post );
			case 'image':
				$img = has_post_thumbnail( $post ) ? wp_get_attachment_image_src( get_post_thumbnail_id( $post ), 'full' ) : false;
				return $img ? $img[0] : '';
			case 'date_published':
				return get_the_date( 'c', $post );
			case 'date_modified':
				return get_the_modified_date( 'c', $post );
			case 'author':
				return get_the_author_meta( 'display_name', $post->post_author );
			case 'site_name':
				return get_bloginfo( 'name' );
			case 'site_url':
				return home_url( '/' );
			case 'meta':
				return self::scalar_or_list( get_post_meta( $post_id, $arg, true ) );
			case 'acf':
				return function_exists( 'get_field' ) ? self::scalar_or_list( get_field( $arg, $post_id ) ) : '';
			case 'mb':
				return function_exists( 'rwmb_meta' ) ? self::scalar_or_list( rwmb_meta( $arg, array(), $post_id ) ) : '';
			case 'term':
				$terms = get_the_terms( $post_id, $arg );
				return is_array( $terms ) && $terms ? $terms[0]->name : '';
			case 'terms':
				$terms = get_the_terms( $post_id, $arg );
				return is_array( $terms ) ? implode( ', ', wp_list_pluck( $terms, 'name' ) ) : '';
		}
		return '';
	}

	/**
	 * Normalize custom field values (ACF images return arrays, etc.).
	 *
	 * @param mixed $value Value.
	 * @return mixed
	 */
	private static function scalar_or_list( $value ) {
		if ( is_array( $value ) ) {
			if ( isset( $value['url'] ) ) {
				return (string) $value['url'];
			}
			$flat = array();
			foreach ( $value as $v ) {
				$v = self::scalar_or_list( $v );
				if ( is_scalar( $v ) && '' !== (string) $v ) {
					$flat[] = $v;
				}
			}
			return $flat;
		}
		if ( is_object( $value ) && isset( $value->name ) ) {
			return (string) $value->name;
		}
		return is_scalar( $value ) ? $value : '';
	}

	/**
	 * Preview a template on a post.
	 *
	 * @param string $json    Template JSON.
	 * @param int    $post_id Post ID.
	 * @return array<string, mixed>
	 */
	public static function preview( $json, $post_id ) {
		$node       = self::render( $json, $post_id );
		$validation = ( new Schema_Service() )->validate( $node );
		return array(
			'node'       => $node,
			'validation' => $validation,
		);
	}
}
