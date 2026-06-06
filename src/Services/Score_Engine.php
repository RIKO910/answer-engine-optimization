<?php
/**
 * AEO Score calculation engine.
 *
 * @package AEO
 */

namespace AEO\Services;

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
	public function get_site_average() {
		global $wpdb;

		$avg = $wpdb->get_var(
			"SELECT AVG(meta_value) FROM {$wpdb->postmeta} pm
			INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
			WHERE pm.meta_key = '_aeo_score'
			AND p.post_status = 'publish'
			AND p.post_type IN ('post','page')"
		);

		$count = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->postmeta} pm
			INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
			WHERE pm.meta_key = '_aeo_score'
			AND p.post_status = 'publish'"
		);

		return array(
			'average'     => $avg ? (int) round( (float) $avg ) : 0,
			'total_posts' => $count,
		);
	}

	/**
	 * Score content structure factor.
	 *
	 * @param \WP_Post $post Post object.
	 * @return int
	 */
	private function score_content_structure( $post ) {
		$content = $post->post_content;
		$score   = 0;

		if ( preg_match( '/<h[2-3][^>]*>/i', $content ) ) {
			$score += 25;
		}
		if ( preg_match( '/<ol|<ul/i', $content ) ) {
			$score += 20;
		}
		if ( str_word_count( wp_strip_all_tags( $content ) ) >= 300 ) {
			$score += 25;
		}
		if ( get_post_meta( $post->ID, '_aeo_direct_answer', true ) ) {
			$score += 30;
		}

		return min( 100, $score );
	}

	/**
	 * Score schema coverage factor.
	 *
	 * @param int $post_id Post ID.
	 * @return int
	 */
	private function score_schema_coverage( $post_id ) {
		$schema = get_post_meta( $post_id, '_aeo_schema', true );
		if ( ! empty( $schema ) ) {
			return 100;
		}

		$score = 40; // Base article schema always output.
		if ( get_post_meta( $post_id, '_aeo_faq_items', true ) ) {
			$score += 40;
		}
		if ( get_post_meta( $post_id, '_aeo_target_question', true ) ) {
			$score += 20;
		}

		return min( 100, $score );
	}

	/**
	 * Score FAQ presence factor.
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
		if ( $count >= 1 ) {
			return 50;
		}

		return 0;
	}

	/**
	 * Score semantic clarity factor.
	 *
	 * @param \WP_Post $post Post object.
	 * @return int
	 */
	private function score_semantic_clarity( $post ) {
		$text       = wp_strip_all_tags( $post->post_content );
		$word_count = str_word_count( $text );

		if ( $word_count < 50 ) {
			return 10;
		}

		$sentences = preg_split( '/[.!?]+/', $text );
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
	 * Score citation signals factor.
	 *
	 * @param int $post_id Post ID.
	 * @return int
	 */
	private function score_citation_signals( $post_id ) {
		$score = 30;

		if ( get_post_meta( $post_id, '_aeo_target_question', true ) ) {
			$score += 30;
		}
		if ( has_post_thumbnail( $post_id ) ) {
			$score += 20;
		}
		if ( get_post_meta( $post_id, '_aeo_canonical', true ) ) {
			$score += 10;
		}
		if ( 'noindex' !== get_post_meta( $post_id, '_aeo_index_status', true ) ) {
			$score += 10;
		}

		return min( 100, $score );
	}

	/**
	 * Get color level for score.
	 *
	 * @param int $score Score value.
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
