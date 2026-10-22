<?php
/**
 * Compatibility with full SEO plugins (Yoast, Rank Math, AIOSEO, SEOPress, The SEO Framework).
 *
 * When one of them is active it already prints WebSite, Organization, Article/WebPage
 * JSON-LD and Open Graph tags. Printing them again creates duplicate, conflicting
 * entities, so this plugin defers to the SEO plugin for those and only adds what the
 * SEO plugin does not: FAQPage, Speakable, LocalBusiness and custom schema.
 *
 * @package AEO
 */

namespace AEO\Services;

use AEO\Core\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * SEO plugin detection helpers.
 */
class Seo_Compat {

	/**
	 * Name of the active SEO plugin, or empty string.
	 *
	 * @return string
	 */
	public static function active_plugin_name() {
		if ( defined( 'WPSEO_VERSION' ) ) {
			return 'Yoast SEO';
		}
		if ( defined( 'RANK_MATH_VERSION' ) || class_exists( 'RankMath' ) ) {
			return 'Rank Math';
		}
		if ( defined( 'AIOSEO_VERSION' ) || function_exists( 'aioseo' ) ) {
			return 'All in One SEO';
		}
		if ( defined( 'SEOPRESS_VERSION' ) ) {
			return 'SEOPress';
		}
		if ( defined( 'THE_SEO_FRAMEWORK_VERSION' ) || function_exists( 'the_seo_framework' ) ) {
			return 'The SEO Framework';
		}
		if ( defined( 'SQ_VERSION' ) ) {
			return 'Squirrly SEO';
		}
		return '';
	}

	/**
	 * Whether this plugin should skip the base graph (WebSite, Organization, Article)
	 * and social tags because another SEO plugin handles them.
	 *
	 * @return bool
	 */
	public static function should_defer() {
		$defer = '' !== self::active_plugin_name() && (bool) Settings::get( 'defer_to_seo_plugin', 1 );
		return (bool) apply_filters( 'aeo_defer_to_seo_plugin', $defer );
	}

	/**
	 * Whether a post is marked noindex by this site's SEO plugin.
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public static function is_noindex( $post_id ) {
		if ( '1' === (string) get_post_meta( $post_id, '_yoast_wpseo_meta-robots-noindex', true ) ) {
			return true;
		}
		$rank_math = get_post_meta( $post_id, 'rank_math_robots', true );
		if ( is_array( $rank_math ) && in_array( 'noindex', $rank_math, true ) ) {
			return true;
		}
		if ( 'yes' === (string) get_post_meta( $post_id, '_seopress_robots_index', true ) ) {
			return true;
		}
		if ( '1' === (string) get_post_meta( $post_id, '_genesis_noindex', true ) ) {
			return true;
		}
		if ( 'noindex' === (string) get_post_meta( $post_id, '_aeo_index_status', true ) ) {
			return true;
		}
		return (bool) apply_filters( 'aeo_is_noindex', false, $post_id );
	}
}
