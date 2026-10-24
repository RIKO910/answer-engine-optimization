<?php
/**
 * Suggested prompts to track, built from the site's own content and business details.
 *
 * @package AEO
 */

namespace AEO\Services;

use AEO\Core\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Prompt suggestions.
 */
class Prompt_Library {

	const QUESTION = '/^(what|how|why|when|where|who|which|can|could|should|does|do|is|are|will|would)\b/i';

	/**
	 * Suggested prompts not tracked yet.
	 *
	 * @param int $limit Max suggestions.
	 * @return array<int, array{prompt: string, source: string}>
	 */
	public static function suggest( $limit = 40 ) {
		$tracked = array_map( 'strtolower', (array) Settings::get( 'tracked_keywords', array() ) );
		$out     = array();
		$add     = static function ( $prompt, $source ) use ( &$out, $tracked ) {
			$prompt = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( (string) $prompt ) ) );
			if ( mb_strlen( $prompt ) < 8 || mb_strlen( $prompt ) > 160 ) {
				return;
			}
			$key = strtolower( $prompt );
			if ( in_array( $key, $tracked, true ) || isset( $out[ $key ] ) ) {
				return;
			}
			$out[ $key ] = array(
				'prompt' => $prompt,
				'source' => $source,
			);
		};

		// 1. Business-level prompts people ask assistants.
		$business = (array) Settings::get( 'business_info', array() );
		$name     = ! empty( $business['name'] ) ? $business['name'] : get_bloginfo( 'name' );
		foreach ( (array) get_option( 'aeo_local_locations', array() ) as $loc ) {
			if ( ! is_array( $loc ) || empty( $loc['name'] ) ) {
				continue;
			}
			$type  = self::type_label( $loc['type'] ?? 'LocalBusiness' );
			$place = trim( (string) ( $loc['city'] ?? '' ) );
			if ( $place ) {
				$add( sprintf( 'best %s in %s', $type, $place ), 'business' );
				$add( sprintf( 'affordable %s in %s', $type, $place ), 'business' );
				$add( sprintf( '%s in %s with free parking', $type, $place ), 'business' );
				if ( 'hotel' === $type || 'resort' === $type ) {
					$add( sprintf( 'family friendly hotel in %s', $place ), 'business' );
					$add( sprintf( 'hotel near the beach in %s', $place ), 'business' );
				}
			}
			$add( sprintf( 'Is %s a good place to stay?', $loc['name'] ), 'brand' );
			$add( sprintf( '%s reviews', $loc['name'] ), 'brand' );
		}
		$add( sprintf( 'What is %s?', $name ), 'brand' );

		// 2. Questions the site already answers (target questions, FAQs, question headings).
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$targets = $wpdb->get_col( "SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_aeo_target_question' AND meta_value <> '' LIMIT 50" );
		foreach ( (array) $targets as $q ) {
			$add( $q, 'target question' );
		}

		$posts = get_posts(
			array(
				'post_type'      => Settings::get_post_types(),
				'post_status'    => 'publish',
				'posts_per_page' => 60,
				'orderby'        => 'modified',
			)
		);
		foreach ( $posts as $post ) {
			$title = wp_strip_all_tags( get_the_title( $post ) );
			if ( preg_match( self::QUESTION, $title ) || '?' === substr( $title, -1 ) ) {
				$add( $title, 'post title' );
			}
			foreach ( (array) get_post_meta( $post->ID, '_aeo_faq_items', true ) as $faq ) {
				if ( is_array( $faq ) && ! empty( $faq['question'] ) ) {
					$add( $faq['question'], 'FAQ' );
				}
			}
			if ( preg_match_all( '/<h[2-3][^>]*>(.*?)<\/h[2-3]>/is', $post->post_content, $m ) ) {
				foreach ( $m[1] as $h ) {
					$h = wp_strip_all_tags( $h );
					if ( '?' === substr( trim( $h ), -1 ) ) {
						$add( $h, 'heading' );
					}
				}
			}
			if ( count( $out ) >= $limit * 2 ) {
				break;
			}
		}

		return array_slice( array_values( $out ), 0, $limit );
	}

	/**
	 * Readable business type.
	 *
	 * @param string $type Schema type.
	 * @return string
	 */
	private static function type_label( $type ) {
		$map = array(
			'Hotel'           => 'hotel',
			'Resort'          => 'resort',
			'BedAndBreakfast' => 'bed and breakfast',
			'Motel'           => 'motel',
			'Hostel'          => 'hostel',
			'LodgingBusiness' => 'place to stay',
			'Restaurant'      => 'restaurant',
			'Store'           => 'store',
		);
		return $map[ $type ] ?? 'business';
	}
}
