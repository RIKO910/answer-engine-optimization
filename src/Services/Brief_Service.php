<?php
/**
 * Content opportunity scoring.
 *
 * @package AEO
 */

namespace AEO\Services;

use AEO\Core\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Scores how open a topic is on this site.
 */
class Brief_Service {

	/**
	 * Opportunity score 0-100: high when the site has little content on the topic.
	 *
	 * Deterministic and explainable: starts at 95, minus 12 for each published post
	 * that mentions the topic (max 5), minus 25 more if a title already targets it.
	 *
	 * @param string $topic Topic or keyword.
	 * @return int
	 */
	public static function opportunity_score( $topic ) {
		$topic = trim( (string) $topic );
		if ( '' === $topic ) {
			return 0;
		}

		$cache_key = 'aeo_opp_' . md5( strtolower( $topic ) );
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return (int) $cached;
		}

		$query = new \WP_Query(
			array(
				's'                      => $topic,
				'post_type'              => Settings::get_post_types(),
				'post_status'            => 'publish',
				'posts_per_page'         => 5,
				'no_found_rows'          => false,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		$score = 95 - 12 * min( 5, (int) $query->found_posts );
		foreach ( $query->posts as $post ) {
			if ( false !== stripos( $post->post_title, $topic ) ) {
				$score -= 25;
				break;
			}
		}

		$score = max( 5, min( 95, $score ) );
		set_transient( $cache_key, $score, 6 * HOUR_IN_SECONDS );
		return $score;
	}

	/**
	 * Opportunities from tracked keywords.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function opportunities() {
		$topics = array();
		foreach ( (array) Settings::get( 'tracked_keywords', array() ) as $keyword ) {
			$topics[] = array(
				'topic'             => $keyword,
				'opportunity_score' => self::opportunity_score( $keyword ),
				'status'            => 'open',
			);
		}
		usort(
			$topics,
			static function ( $a, $b ) {
				return $b['opportunity_score'] <=> $a['opportunity_score'];
			}
		);
		return $topics;
	}
}
