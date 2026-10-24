<?php
/**
 * Import / export of plugin data, and migration of FAQ blocks from other SEO plugins.
 *
 * Export file format (JSON):
 *   { "plugin": "answer-engine-optimization", "version": "1.2.0", "exported": "...",
 *     "site": "https://…", "sections": { "settings": {…}, "global_schema": {…},
 *     "locations": […], "templates": […], "posts": [ { "slug", "type", "url", "faqs", "schema",
 *     "target_question", "direct_answer" } ] } }
 *
 * API keys and Google tokens are never exported. Posts are matched on import by
 * post type + slug, then by URL path, so an export from staging imports into production.
 *
 * @package AEO
 */

namespace AEO\Services;

use AEO\Core\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Import/export.
 */
class Import_Export {

	const SECTIONS = array( 'settings', 'global_schema', 'locations', 'templates', 'posts' );

	/**
	 * Settings keys that never leave the site.
	 */
	const PRIVATE_KEYS = array( 'google_client_secret', 'gsc_site', 'ga4_property' );

	/**
	 * Build an export.
	 *
	 * @param string[] $sections Sections to include.
	 * @return array<string, mixed>
	 */
	public static function export( array $sections = self::SECTIONS ) {
		$sections = array_values( array_intersect( self::SECTIONS, $sections ) );
		$out      = array();

		if ( in_array( 'settings', $sections, true ) ) {
			$settings = Settings::get_all();
			foreach ( array_merge( Settings::API_KEYS, self::PRIVATE_KEYS ) as $k ) {
				unset( $settings[ $k ] );
			}
			$out['settings'] = $settings;
		}
		if ( in_array( 'global_schema', $sections, true ) ) {
			$out['global_schema'] = get_option( 'aeo_optimizer_schema_global', array() );
		}
		if ( in_array( 'locations', $sections, true ) ) {
			$out['locations'] = get_option( 'aeo_local_locations', array() );
		}
		if ( in_array( 'templates', $sections, true ) ) {
			$out['templates'] = Schema_Templates::all();
		}
		if ( in_array( 'posts', $sections, true ) ) {
			$out['posts'] = self::export_posts();
		}

		return array(
			'plugin'   => 'answer-engine-optimization',
			'version'  => AEO_VERSION,
			'exported' => gmdate( 'c' ),
			'site'     => home_url( '/' ),
			'sections' => $out,
		);
	}

	/**
	 * Per-post data.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private static function export_posts() {
		global $wpdb;
		$keys = array( '_aeo_faq_items', '_aeo_schema', '_aeo_target_question', '_aeo_direct_answer' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT post_id FROM {$wpdb->postmeta} WHERE meta_key IN (%s, %s, %s, %s) AND meta_value <> ''",
				$keys
			)
		);
		$rows = array();
		foreach ( array_map( 'intval', (array) $ids ) as $id ) {
			$post = get_post( $id );
			if ( ! $post || 'revision' === $post->post_type ) {
				continue;
			}
			$schema = get_post_meta( $id, '_aeo_schema', true );
			$rows[] = array(
				'slug'            => $post->post_name,
				'type'            => $post->post_type,
				'title'           => $post->post_title,
				'url'             => get_permalink( $post ),
				'faqs'            => (array) get_post_meta( $id, '_aeo_faq_items', true ),
				'schema'          => is_string( $schema ) && '' !== $schema ? json_decode( $schema, true ) : null,
				'target_question' => (string) get_post_meta( $id, '_aeo_target_question', true ),
				'direct_answer'   => (string) get_post_meta( $id, '_aeo_direct_answer', true ),
			);
		}
		return $rows;
	}

	/**
	 * Import.
	 *
	 * @param array<string, mixed> $data     Decoded export.
	 * @param string[]             $sections Sections to apply.
	 * @param bool                 $dry_run  Report only.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function import( array $data, array $sections = self::SECTIONS, $dry_run = false ) {
		if ( ( $data['plugin'] ?? '' ) !== 'answer-engine-optimization' || ! is_array( $data['sections'] ?? null ) ) {
			return new \WP_Error( 'aeo_import_format', __( 'This is not an Answer Engine Optimization export file.', 'answer-engine-optimization' ) );
		}
		$in     = $data['sections'];
		$report = array();
		$apply  = static function ( $key ) use ( $sections, $in ) {
			return in_array( $key, $sections, true ) && array_key_exists( $key, $in );
		};

		if ( $apply( 'settings' ) && is_array( $in['settings'] ) ) {
			$clean = $in['settings'];
			foreach ( array_merge( Settings::API_KEYS, self::PRIVATE_KEYS ) as $k ) {
				unset( $clean[ $k ] );
			}
			$sanitized = Settings::sanitize( $clean );
			$report['settings'] = count( $sanitized );
			if ( ! $dry_run ) {
				Settings::update( $sanitized );
			}
		}

		if ( $apply( 'global_schema' ) && is_array( $in['global_schema'] ) ) {
			$report['global_schema'] = 1;
			if ( ! $dry_run ) {
				update_option( 'aeo_optimizer_schema_global', self::clean_deep( $in['global_schema'] ) );
			}
		}

		if ( $apply( 'locations' ) && is_array( $in['locations'] ) ) {
			$report['locations'] = count( $in['locations'] );
			if ( ! $dry_run ) {
				$req = new \WP_REST_Request( 'POST', '/aeo-genius/v1/local/locations' );
				$req->set_header( 'content-type', 'application/json' );
				$req->set_body( (string) wp_json_encode( array_values( $in['locations'] ) ) );
				( new \AEO\Api\Rest_Api() )->save_locations( $req );
			}
		}

		if ( $apply( 'templates' ) && is_array( $in['templates'] ) ) {
			$merged = array();
			foreach ( Schema_Templates::all() as $t ) {
				$merged[ $t['id'] ] = $t;
			}
			foreach ( $in['templates'] as $t ) {
				if ( is_array( $t ) && ! empty( $t['id'] ) ) {
					$merged[ sanitize_key( $t['id'] ) ] = $t;
				}
			}
			$report['templates'] = count( $in['templates'] );
			if ( ! $dry_run ) {
				$saved = Schema_Templates::save_all( array_values( $merged ) );
				if ( is_wp_error( $saved ) ) {
					return $saved;
				}
			}
		}

		if ( $apply( 'posts' ) && is_array( $in['posts'] ) ) {
			$matched   = 0;
			$unmatched = array();
			foreach ( $in['posts'] as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				$id = self::find_post( $row );
				if ( ! $id ) {
					$unmatched[] = (string) ( $row['url'] ?? $row['slug'] ?? '' );
					continue;
				}
				++$matched;
				if ( $dry_run ) {
					continue;
				}
				if ( ! empty( $row['faqs'] ) && is_array( $row['faqs'] ) ) {
					update_post_meta( $id, '_aeo_faq_items', wp_slash( self::clean_faqs( $row['faqs'] ) ) );
				}
				if ( ! empty( $row['schema'] ) && is_array( $row['schema'] ) ) {
					update_post_meta( $id, '_aeo_schema', wp_slash( wp_json_encode( self::clean_deep( $row['schema'] ) ) ) );
				}
				foreach ( array( 'target_question', 'direct_answer' ) as $f ) {
					if ( isset( $row[ $f ] ) && '' !== $row[ $f ] ) {
						update_post_meta( $id, '_aeo_' . $f, sanitize_textarea_field( (string) $row[ $f ] ) );
					}
				}
			}
			$report['posts']     = $matched;
			$report['unmatched'] = array_slice( $unmatched, 0, 50 );
		}

		if ( ! $dry_run ) {
			\AEO\Frontend\Llms_Txt::flush();
			\AEO\Api\Public_Api::flush_schemamap();
		}
		$report['dry_run'] = (bool) $dry_run;
		return $report;
	}

	/**
	 * Match an exported post on this site.
	 *
	 * @param array<string, mixed> $row Row.
	 * @return int
	 */
	private static function find_post( array $row ) {
		$type = sanitize_key( (string) ( $row['type'] ?? '' ) );
		$slug = sanitize_title( (string) ( $row['slug'] ?? '' ) );
		if ( $type && $slug && post_type_exists( $type ) ) {
			$found = get_posts(
				array(
					'name'           => $slug,
					'post_type'      => $type,
					'post_status'    => 'any',
					'posts_per_page' => 1,
					'fields'         => 'ids',
				)
			);
			if ( $found ) {
				return (int) $found[0];
			}
		}
		if ( ! empty( $row['url'] ) ) {
			$path = wp_parse_url( (string) $row['url'], PHP_URL_PATH );
			if ( $path ) {
				$id = url_to_postid( home_url( $path ) );
				if ( $id ) {
					return (int) $id;
				}
			}
		}
		return 0;
	}

	/**
	 * Sanitize FAQ rows.
	 *
	 * @param array<int, mixed> $faqs FAQs.
	 * @return array<int, array{question: string, answer: string}>
	 */
	public static function clean_faqs( array $faqs ) {
		$out = array();
		foreach ( $faqs as $f ) {
			if ( ! is_array( $f ) ) {
				continue;
			}
			$q = sanitize_text_field( (string) ( $f['question'] ?? '' ) );
			$a = wp_kses_post( (string) ( $f['answer'] ?? '' ) );
			if ( '' !== $q && '' !== trim( wp_strip_all_tags( $a ) ) ) {
				$out[] = array(
					'question' => $q,
					'answer'   => $a,
				);
			}
		}
		return $out;
	}

	/**
	 * Strip tags from every string in a nested array (schema/locations).
	 *
	 * @param mixed $value Value.
	 * @return mixed
	 */
	private static function clean_deep( $value ) {
		if ( is_array( $value ) ) {
			$out = array();
			foreach ( $value as $k => $v ) {
				$out[ is_int( $k ) ? $k : preg_replace( '/[^A-Za-z0-9@_:\-]/', '', (string) $k ) ] = self::clean_deep( $v );
			}
			return $out;
		}
		return is_string( $value ) ? wp_strip_all_tags( $value ) : ( is_scalar( $value ) || null === $value ? $value : '' );
	}

	/**
	 * Find FAQs written with Yoast or Rank Math FAQ blocks that are not yet in the plugin.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function third_party_faq_candidates() {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$ids = $wpdb->get_col(
			"SELECT ID FROM {$wpdb->posts} WHERE post_status IN ('publish','draft','future','private')
			AND ( post_content LIKE '%<!-- wp:yoast/faq-block%' OR post_content LIKE '%<!-- wp:rank-math/faq-block%' )
			ORDER BY post_modified DESC LIMIT 500"
		);
		$out = array();
		foreach ( array_map( 'intval', (array) $ids ) as $id ) {
			if ( ! current_user_can( 'edit_post', $id ) ) {
				continue;
			}
			$found = \AEO\Content\Content_Extractor::extract( $id )['third_party_faqs'] ?? array();
			if ( ! $found ) {
				continue;
			}
			$existing = (array) get_post_meta( $id, '_aeo_faq_items', true );
			$out[]    = array(
				'id'       => $id,
				'title'    => get_the_title( $id ),
				'edit_url' => get_edit_post_link( $id, 'raw' ),
				'count'    => count( $found ),
				'existing' => count( array_filter( $existing ) ),
			);
		}
		return $out;
	}

	/**
	 * Copy third-party FAQ block items into the plugin's FAQ meta (merge, dedupe).
	 *
	 * The visible Yoast/Rank Math block stays in the content; the plugin then outputs
	 * FAQPage schema for it as part of its connected graph. If the SEO plugin also
	 * outputs FAQ schema, turn that off there to avoid duplicates.
	 *
	 * @param int[] $ids Post IDs (empty = all candidates).
	 * @return array<string, int>
	 */
	public static function import_third_party_faqs( array $ids = array() ) {
		if ( ! $ids ) {
			$ids = wp_list_pluck( self::third_party_faq_candidates(), 'id' );
		}
		$posts = 0;
		$items = 0;
		foreach ( array_map( 'intval', $ids ) as $id ) {
			if ( ! current_user_can( 'edit_post', $id ) ) {
				continue;
			}
			$found = \AEO\Content\Content_Extractor::extract( $id )['third_party_faqs'] ?? array();
			if ( ! $found ) {
				continue;
			}
			$existing = (array) get_post_meta( $id, '_aeo_faq_items', true );
			$seen     = array();
			$merged   = array();
			foreach ( array_merge( array_filter( $existing, 'is_array' ), $found ) as $f ) {
				$k = strtolower( trim( wp_strip_all_tags( (string) ( $f['question'] ?? '' ) ) ) );
				if ( '' === $k || isset( $seen[ $k ] ) ) {
					continue;
				}
				$seen[ $k ] = true;
				$merged[]   = $f;
			}
			$merged = self::clean_faqs( $merged );
			$added  = count( $merged ) - count( self::clean_faqs( array_filter( $existing, 'is_array' ) ) );
			if ( $added > 0 ) {
				update_post_meta( $id, '_aeo_faq_items', wp_slash( $merged ) );
				++$posts;
				$items += $added;
			}
		}
		\AEO\Frontend\Llms_Txt::flush();
		return array(
			'posts' => $posts,
			'faqs'  => $items,
		);
	}
}
