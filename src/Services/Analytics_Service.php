<?php
/**
 * Analytics dashboard service.
 *
 * @package AEO
 */

namespace AEO\Services;

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
	 * Calculate schema coverage percentage.
	 *
	 * @return array<string, mixed>
	 */
	public function get_schema_coverage() {
		global $wpdb;

		$total = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->posts}
			WHERE post_status = 'publish' AND post_type IN ('post','page')"
		);

		$with_schema = (int) $wpdb->get_var(
			"SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta}
			WHERE meta_key = '_aeo_schema' AND meta_value != ''"
		);

		$percent = $total > 0 ? round( ( $with_schema / $total ) * 100 ) : 0;

		return array(
			'percent'     => $percent,
			'total'       => $total,
			'with_schema' => $with_schema,
		);
	}

	/**
	 * Calculate FAQ coverage percentage.
	 *
	 * @return array<string, mixed>
	 */
	public function get_faq_coverage() {
		global $wpdb;

		$total = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->posts}
			WHERE post_status = 'publish' AND post_type IN ('post','page')"
		);

		$with_faq = (int) $wpdb->get_var(
			"SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta}
			WHERE meta_key = '_aeo_faq_items' AND meta_value != '' AND meta_value != 'a:0:{}'"
		);

		$percent = $total > 0 ? round( ( $with_faq / $total ) * 100 ) : 0;

		return array(
			'percent'  => $percent,
			'total'    => $total,
			'with_faq' => $with_faq,
		);
	}

	/**
	 * Get top performing pages.
	 *
	 * @param int $limit Result limit.
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
				WHERE p.post_status = 'publish' AND p.post_type IN ('post','page')
				ORDER BY CAST(COALESCE(pm.meta_value, '0') AS UNSIGNED) ASC
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
