<?php
/**
 * AI-written SEO titles, meta descriptions and image alt text.
 *
 * Titles and descriptions are saved into the active SEO plugin's own fields so they
 * show up there; with no SEO plugin, this plugin outputs them itself.
 *
 * @package AEO
 */

namespace AEO\Content;

use AEO\Core\Settings;
use AEO\Services\Seo_Compat;

defined( 'ABSPATH' ) || exit;

/**
 * Meta + alt text.
 */
class Meta_Service {

	/**
	 * Meta keys per SEO plugin.
	 */
	const FIELDS = array(
		'Yoast SEO'         => array( '_yoast_wpseo_title', '_yoast_wpseo_metadesc' ),
		'Rank Math'         => array( 'rank_math_title', 'rank_math_description' ),
		'SEOPress'          => array( '_seopress_titles_title', '_seopress_titles_desc' ),
		'The SEO Framework' => array( '_genesis_title', '_genesis_description' ),
		'Squirrly SEO'      => array( '_sq_title', '_sq_description' ),
	);

	/**
	 * Register front-end output (only used when no SEO plugin handles meta).
	 *
	 * @return void
	 */
	public static function register() {
		add_filter( 'pre_get_document_title', array( __CLASS__, 'document_title' ), 20 );
		add_action( 'wp_head', array( __CLASS__, 'meta_description' ), 1 );
	}

	/**
	 * Which fields to use.
	 *
	 * @return array{0: string, 1: string, 2: string} title key, description key, owner label
	 */
	public static function fields() {
		$plugin = Seo_Compat::active_plugin_name();
		if ( isset( self::FIELDS[ $plugin ] ) ) {
			return array( self::FIELDS[ $plugin ][0], self::FIELDS[ $plugin ][1], $plugin );
		}
		return array( '_aeo_meta_title', '_aeo_meta_description', '' );
	}

	/**
	 * Title filter.
	 *
	 * @param string $title Title.
	 * @return string
	 */
	public static function document_title( $title ) {
		if ( '' !== Seo_Compat::active_plugin_name() || ! is_singular() ) {
			return $title;
		}
		$custom = trim( (string) get_post_meta( get_queried_object_id(), '_aeo_meta_title', true ) );
		return '' !== $custom ? $custom : $title;
	}

	/**
	 * Meta description tag.
	 *
	 * @return void
	 */
	public static function meta_description() {
		if ( '' !== Seo_Compat::active_plugin_name() || ! is_singular() ) {
			return;
		}
		$desc = trim( (string) get_post_meta( get_queried_object_id(), '_aeo_meta_description', true ) );
		if ( '' !== $desc ) {
			echo '<meta name="description" content="' . esc_attr( $desc ) . '" />' . "\n";
		}
	}

	/**
	 * Pages with their current title and description.
	 *
	 * @param string $filter all|missing.
	 * @return array<string, mixed>
	 */
	public static function list_posts( $filter = 'all' ) {
		list( $tkey, $dkey, $owner ) = self::fields();
		$posts                       = get_posts(
			array(
				'post_type'      => Settings::get_post_types(),
				'post_status'    => 'publish',
				'posts_per_page' => 200,
				'orderby'        => 'modified',
			)
		);
		$rows = array();
		foreach ( $posts as $post ) {
			$title = (string) get_post_meta( $post->ID, $tkey, true );
			$desc  = (string) get_post_meta( $post->ID, $dkey, true );
			if ( 'missing' === $filter && '' !== $desc ) {
				continue;
			}
			$rows[] = array(
				'id'          => $post->ID,
				'post_title'  => wp_strip_all_tags( get_the_title( $post ) ),
				'url'         => get_permalink( $post ),
				'title'       => $title,
				'description' => $desc,
			);
		}
		return array(
			'owner' => $owner,
			'rows'  => $rows,
		);
	}


	/**
	 * Save title + description.
	 *
	 * @param int    $post_id     Post ID.
	 * @param string $title       Title.
	 * @param string $description Description.
	 * @return void
	 */
	public static function save( $post_id, $title, $description ) {
		list( $tkey, $dkey ) = self::fields();
		update_post_meta( $post_id, $tkey, sanitize_text_field( $title ) );
		update_post_meta( $post_id, $dkey, sanitize_textarea_field( $description ) );
	}

	/**
	 * Images without alt text.
	 *
	 * @param int $limit Limit.
	 * @return array<int, array<string, mixed>>
	 */
	public static function images_missing_alt( $limit = 60 ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT p.ID FROM {$wpdb->posts} p
				LEFT JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_wp_attachment_image_alt'
				WHERE p.post_type = 'attachment' AND p.post_mime_type LIKE 'image/%%' AND ( m.meta_value IS NULL OR m.meta_value = '' )
				ORDER BY p.ID DESC LIMIT %d",
				$limit
			)
		);
		$out = array();
		foreach ( (array) $ids as $id ) {
			$parent = (int) wp_get_post_parent_id( (int) $id );
			$thumb  = wp_get_attachment_image_src( (int) $id, 'thumbnail' );
			$out[]  = array(
				'id'     => (int) $id,
				'thumb'  => $thumb ? $thumb[0] : '',
				'file'   => basename( (string) get_attached_file( (int) $id ) ),
				'parent' => $parent ? wp_strip_all_tags( get_the_title( $parent ) ) : '',
			);
		}
		return $out;
	}


	/**
	 * Save alt text.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $alt           Alt text.
	 * @return void
	 */
	public static function save_alt( $attachment_id, $alt ) {
		update_post_meta( $attachment_id, '_wp_attachment_image_alt', sanitize_text_field( $alt ) );
	}
}
