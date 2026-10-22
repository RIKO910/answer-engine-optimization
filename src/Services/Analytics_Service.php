<?php
/**
 * Analytics dashboard service.
 *
 * @package AEO
 */

namespace AEO\Services;

use AEO\Core\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Site-wide AEO analytics.
 */
class Analytics_Service {

	/**
	 * Get dashboard overview data.
	 *
	 * @return array<string, mixed>
	 */
	public function get_overview() {
		$score_engine = new Score_Engine();
		$site_avg     = $score_engine->get_site_average();
		$citations    = ( new Citation_Service() )->get_analytics();

		return array(
			'aeo_score'       => $site_avg,
			'schema_coverage' => $this->get_schema_coverage(),
			'faq_coverage'    => $this->get_faq_coverage(),
			'citations'       => $citations,
			'top_pages'       => $this->get_top_pages(),
			'worst_pages'     => $this->get_worst_pages(),
		);
	}

	/**
	 * Published post IDs the plugin covers.
	 *
	 * @return int[]
	 */
	private function published_ids() {
		return array_map(
			'intval',
			get_posts(
				array(
					'post_type'      => Settings::get_post_types(),
					'post_status'    => 'publish',
					'posts_per_page' => 1000,
					'has_password'   => false,
					'fields'         => 'ids',
				)
			)
		);
	}

	/**
	 * Share of pages whose schema is complete (main node + image + description).
	 *
	 * @return array<string, int>
	 */
	public function get_schema_coverage() {
		$ids      = $this->published_ids();
		$complete = 0;

		if ( Settings::get( 'aeo_enable_schema', 1 ) ) {
			foreach ( $ids as $id ) {
				if ( has_post_thumbnail( $id ) && ( has_excerpt( $id ) || get_post_meta( $id, '_aeo_direct_answer', true ) || get_post_meta( $id, '_aeo_schema', true ) ) ) {
					$complete++;
				}
			}
		}

		$total = count( $ids );
		return array(
			'percent'     => $total > 0 ? (int) round( ( $complete / $total ) * 100 ) : 0,
			'total'       => $total,
			'with_schema' => $complete,
		);
	}

	/**
	 * Share of pages with at least one complete FAQ.
	 *
	 * @return array<string, int>
	 */
	public function get_faq_coverage() {
		$ids      = $this->published_ids();
		$schema   = new Schema_Service();
		$with_faq = 0;
		foreach ( $ids as $id ) {
			if ( $schema->build_faq_node( $id ) ) {
				$with_faq++;
			}
		}

		$total = count( $ids );
		return array(
			'percent'  => $total > 0 ? (int) round( ( $with_faq / $total ) * 100 ) : 0,
			'total'    => $total,
			'with_faq' => $with_faq,
		);
	}

	/**
	 * Highest scores.
	 *
	 * @param int $limit Limit.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_top_pages( $limit = 10 ) {
		global $wpdb;

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.ID, p.post_title, pm.meta_value as score
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
				WHERE pm.meta_key = '_aeo_score'
				AND p.post_status = 'publish'
				ORDER BY CAST(pm.meta_value AS UNSIGNED) DESC
				LIMIT %d",
				$limit
			),
			ARRAY_A
		);

		return array_map(
			function ( $row ) {
				return array(
					'id'    => (int) $row['ID'],
					'title' => $row['post_title'],
					'score' => (int) $row['score'],
					'url'   => get_permalink( $row['ID'] ),
				);
			},
			$results ?: array()
		);
	}

	/**
	 * Get worst performing pages.
	 *
	 * @param int $limit Result limit.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_worst_pages( $limit = 10 ) {
		global $wpdb;

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.ID, p.post_title, COALESCE(pm.meta_value, '0') as score
				FROM {$wpdb->posts} p
				LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_aeo_score'
				WHERE p.post_status = 'publish' AND pm.meta_value IS NOT NULL
				ORDER BY CAST(pm.meta_value AS UNSIGNED) ASC
				LIMIT %d",
				$limit
			),
			ARRAY_A
		);

		return array_map(
			function ( $row ) {
				return array(
					'id'    => (int) $row['ID'],
					'title' => $row['post_title'],
					'score' => (int) $row['score'],
					'url'   => get_permalink( $row['ID'] ),
				);
			},
			$results ?: array()
		);
	}
}
