<?php
/**
 * Multilingual helpers (Polylang, WPML, TranslatePress-safe).
 *
 * @package AEO
 */

namespace AEO\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Language detection.
 */
class Lang {

	/**
	 * Active multilingual plugin, if any.
	 *
	 * @return string polylang|wpml|''
	 */
	public static function provider() {
		if ( function_exists( 'pll_get_post_language' ) ) {
			return 'polylang';
		}
		if ( defined( 'ICL_SITEPRESS_VERSION' ) || has_filter( 'wpml_post_language_details' ) ) {
			return 'wpml';
		}
		return '';
	}

	/**
	 * BCP-47 language tag of a post (e.g. "bn-BD"), falling back to the site language.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	public static function post_language( $post_id ) {
		$locale = '';
		switch ( self::provider() ) {
			case 'polylang':
				$locale = (string) pll_get_post_language( $post_id, 'locale' );
				break;
			case 'wpml':
				$details = apply_filters( 'wpml_post_language_details', null, $post_id );
				if ( is_array( $details ) && ! empty( $details['locale'] ) ) {
					$locale = (string) $details['locale'];
				}
				break;
		}
		if ( '' === $locale ) {
			return get_bloginfo( 'language' );
		}
		return str_replace( '_', '-', $locale );
	}

	/**
	 * Human label for a language tag.
	 *
	 * @param string $tag Language tag.
	 * @return string
	 */
	public static function label( $tag ) {
		$locale = str_replace( '-', '_', $tag );
		if ( ! function_exists( 'wp_get_available_translations' ) && file_exists( ABSPATH . 'wp-admin/includes/translation-install.php' ) ) {
			require_once ABSPATH . 'wp-admin/includes/translation-install.php';
		}
		if ( function_exists( 'locale_get_display_name' ) ) {
			$name = locale_get_display_name( $locale, 'en' );
			if ( $name ) {
				return $name;
			}
		}
		if ( 'en_US' === $locale ) {
			return 'English (United States)';
		}
		$translations = function_exists( 'wp_get_available_translations' ) ? (array) get_site_transient( 'available_translations' ) : array();
		return isset( $translations[ $locale ]['english_name'] ) ? $translations[ $locale ]['english_name'] : $tag;
	}

	/**
	 * Translations of a post, keyed by language tag.
	 *
	 * @param int $post_id Post ID.
	 * @return array<string, string> tag => URL
	 */
	public static function translations( $post_id ) {
		$out = array();
		if ( 'polylang' === self::provider() && function_exists( 'pll_get_post_translations' ) ) {
			foreach ( (array) pll_get_post_translations( $post_id ) as $slug => $id ) {
				$out[ self::post_language( $id ) ] = get_permalink( $id );
			}
		} elseif ( 'wpml' === self::provider() ) {
			$type = apply_filters( 'wpml_element_type', get_post_type( $post_id ) );
			$trid = apply_filters( 'wpml_element_trid', null, $post_id, $type );
			$all  = $trid ? apply_filters( 'wpml_get_element_translations', null, $trid, $type ) : array();
			foreach ( (array) $all as $t ) {
				if ( isset( $t->element_id ) ) {
					$out[ self::post_language( (int) $t->element_id ) ] = get_permalink( (int) $t->element_id );
				}
			}
		}
		return $out;
	}
}
