<?php
/**
 * AEO Score calculation engine.
 *
 * @package AEO
 */

namespace AEO\Services;

use AEO\Core\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Calculates proprietary 0-100 AEO readiness scores.
 */
class Score_Engine {

	/**
	 * Calculate AEO score for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return array<string, mixed>
	 */
	public function calculate( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return array( 'score' => 0, 'factors' => array() );
		}

		$factors = array(
			'content_structure' => $this->score_content_structure( $post ),
			'schema_coverage'   => $this->score_schema_coverage( $post_id ),
			'faq_presence'      => $this->score_faq_presence( $post_id ),
			'semantic_clarity'  => $this->score_semantic_clarity( $post ),
			'citation_signals'  => $this->score_citation_signals( $post_id ),
		);

		$weights = array(
			'content_structure' => 0.20,
			'schema_coverage'   => 0.25,
			'faq_presence'      => 0.15,
			'semantic_clarity'  => 0.20,
			'citation_signals'  => 0.20,
		);

		$score = 0;
		foreach ( $factors as $key => $value ) {
			$score += $value * $weights[ $key ];
		}

		$score = (int) round( $score );
		update_post_meta( $post_id, '_aeo_score', $score );

		return array(
			'score'   => $score,
			'level'   => $this->get_level( $score ),
			'factors' => $factors,
		);
	}

	/**
	 * Get site-wide average score.
	 *
	 * @return array<string, mixed>
	 */
	/**
	 * Unicode-aware word count (str_word_count() returns 0 for Bengali, Arabic, CJK, etc.).
	 *
	 * @param string $text Plain text.
	 * @return int
	 */
	public static function word_count( $text ) {
		return (int) preg_match_all( '/[\p{L}\p{N}][\p{L}\p{N}\p{M}\'’-]*/u', (string) $text );
	}

	/**
	 * Site-wide average score.
	 *
	 * @return array{average: int, total_posts: int}
	 */
	public function get_site_average() {
		global $wpdb;
		$types        = Settings::get_post_types();
		$placeholders = implode( ',', array_fill( 0, count( $types ), '%s' ) );
		// phpcs:disable WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT AVG(CAST(pm.meta_value AS UNSIGNED)) AS avg_score, COUNT(*) AS total FROM {$wpdb->postmeta} pm
				INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				WHERE pm.meta_key = '_aeo_score' AND p.post_status = 'publish' AND p.post_type IN ($placeholders)",
				$types
			),
			ARRAY_A
		);
		// phpcs:enable

		return array(
			'average'     => $row && $row['avg_score'] ? (int) round( (float) $row['avg_score'] ) : 0,
			'total_posts' => $row ? (int) $row['total'] : 0,
		);
	}

	/**
	 * Headings, lists, length and direct answer.
	 *
	 * @param \WP_Post $post Post.
	 * @return int
	 */
	private function score_content_structure( $post ) {
		$content = $post->post_content;
		$score   = 0;

		if ( preg_match( '/<h[2-3][^>]*>/i', $content ) ) {
			$score += 25;
		}
		if ( preg_match( '/<(ol|ul)[\s>]/i', $content ) ) {
			$score += 20;
		}
		if ( self::word_count( wp_strip_all_tags( strip_shortcodes( $content ) ) ) >= 300 ) {
			$score += 25;
		}
		if ( get_post_meta( $post->ID, '_aeo_direct_answer', true ) ) {
			$score += 30;
		}

		return min( 100, $score );
	}

	/**
	 * How complete the structured data for this post is.
	 *
	 * @param int $post_id Post ID.
	 * @return int
	 */
	private function score_schema_coverage( $post_id ) {
		if ( ! Settings::get( 'aeo_enable_schema', 1 ) ) {
			return 0;
		}

		$custom = get_post_meta( $post_id, '_aeo_schema', true );
		$score  = $custom ? 60 : 40; // A main node is always output (auto or custom).

		if ( has_post_thumbnail( $post_id ) ) {
			$score += 20;
		}
		if ( has_excerpt( $post_id ) || get_post_meta( $post_id, '_aeo_direct_answer', true ) ) {
			$score += 20;
		}
		if ( ( new Schema_Service() )->build_faq_node( $post_id ) ) {
			$score += 20;
		}

		return min( 100, $score );
	}

	/**
	 * FAQ count.
	 *
	 * @param int $post_id Post ID.
	 * @return int
	 */
	private function score_faq_presence( $post_id ) {
		$faqs = get_post_meta( $post_id, '_aeo_faq_items', true );
		if ( empty( $faqs ) || ! is_array( $faqs ) ) {
			return 0;
		}

		$count = count( $faqs );
		if ( $count >= 5 ) {
			return 100;
		}
		if ( $count >= 3 ) {
			return 75;
		}
		return 50;
	}

	/**
	 * Sentence length and direct answer.
	 *
	 * @param \WP_Post $post Post.
	 * @return int
	 */
	private function score_semantic_clarity( $post ) {
		$text       = wp_strip_all_tags( strip_shortcodes( $post->post_content ) );
		$word_count = self::word_count( $text );

		if ( $word_count < 50 ) {
			return 10;
		}

		$sentences = array_filter( array_map( 'trim', preg_split( '/[.!?।]+/u', $text ) ) );
		$avg_len   = $word_count / max( 1, count( $sentences ) );

		$score = 50;
		if ( $avg_len <= 20 ) {
			$score += 25;
		}
		if ( get_post_meta( $post->ID, '_aeo_direct_answer', true ) ) {
			$score += 25;
		}

		return min( 100, $score );
	}

	/**
	 * Signals that make a page easy to cite.
	 *
	 * @param int $post_id Post ID.
	 * @return int
	 */
	private function score_citation_signals( $post_id ) {
		$score = 0;

		if ( get_post_meta( $post_id, '_aeo_target_question', true ) ) {
			$score += 30;
		}
		if ( has_post_thumbnail( $post_id ) ) {
			$score += 20;
		}
		// Freshness: updated in the last 12 months.
		if ( (int) get_post_modified_time( 'U', true, $post_id ) > time() - YEAR_IN_SECONDS ) {
			$score += 20;
		}
		if ( ! Seo_Compat::is_noindex( $post_id ) ) {
			$score += 30;
		}

		return min( 100, $score );
	}

	/**
	 * Colour level.
	 *
	 * @param int $score Score.
	 * @return string
	 */
	private function get_level( $score ) {
		if ( $score >= 71 ) {
			return 'green';
		}
		if ( $score >= 41 ) {
			return 'orange';
		}
		return 'red';
	}
}
