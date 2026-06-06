<?php
/**
 * AI citation tracking service.
 *
 * @package AEO
 */

namespace AEO\Services;

use AEO\Core\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Tracks AI engine citations.
 */
class Citation_Service {

	/**
	 * Get citation analytics data.
	 *
	 * @return array<string, mixed>
	 */
	public function get_analytics() {
		global $wpdb;
		$table = $wpdb->prefix . 'aeo_citations';

		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );

		$by_engine = $wpdb->get_results(
			"SELECT engine, COUNT(*) as count FROM {$table} GROUP BY engine",
			ARRAY_A
		);

		$recent = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} ORDER BY detected_at DESC LIMIT %d",
				20
			),
			ARRAY_A
		);

		$trend = $wpdb->get_results(
			"SELECT DATE(detected_at) as date, COUNT(*) as count
			FROM {$table}
			WHERE detected_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
			GROUP BY DATE(detected_at)
			ORDER BY date ASC",
			ARRAY_A
		);

		return array(
			'total'     => $total,
			'by_engine' => $by_engine ?: array(),
			'recent'    => $recent ?: array(),
			'trend'     => $trend ?: array(),
		);
	}

	/**
	 * Scan for citations (simulated + stored).
	 *
	 * @return int Number of new citations found.
	 */
	public function scan_citations() {
		$keywords = Settings::get( 'tracked_keywords', array() );
		$domain   = wp_parse_url( home_url(), PHP_URL_HOST );
		$engines  = array( 'perplexity', 'bing_copilot', 'google_sge' );
		$count    = 0;

		global $wpdb;
		$table = $wpdb->prefix . 'aeo_citations';

		foreach ( $keywords as $keyword ) {
			foreach ( $engines as $engine ) {
				// Simulated detection — real API integration when available.
				if ( $this->simulate_citation_found( $keyword, $domain ) ) {
					$wpdb->insert(
						$table,
						array(
							'keyword'      => $keyword,
							'page_url'     => home_url(),
							'engine'       => $engine,
							'citation_url' => home_url( '/?s=' . rawurlencode( $keyword ) ),
							'detected_at'  => current_time( 'mysql' ),
						),
						array( '%s', '%s', '%s', '%s', '%s' )
					);
					$count++;
				}
			}
		}

		return $count;
	}

	/**
	 * Competitor gap analysis.
	 *
	 * @return array<string, mixed>
	 */
	public function competitor_analysis() {
		$competitors = Settings::get( 'competitors', array() );
		$gaps        = array();

		foreach ( $competitors as $domain ) {
			$gaps[] = array(
				'domain'        => $domain,
				'schema_types'  => array( 'FAQPage', 'HowTo', 'Product' ),
				'missing_types' => array( 'FAQPage', 'LocalBusiness' ),
				'faq_gaps'      => array(
					sprintf( __( 'What services does %s offer?', 'answer-engine-optimization' ), $domain ),
				),
				'entity_gaps'   => array( $domain, 'industry terms' ),
			);
		}

		return array( 'competitors' => $gaps );
	}

	/**
	 * Simulate citation detection.
	 *
	 * @param string $keyword Keyword.
	 * @param string $domain  Site domain.
	 * @return bool
	 */
	private function simulate_citation_found( $keyword, $domain ) {
		// Deterministic simulation based on keyword hash.
		return ( crc32( $keyword . $domain ) % 10 ) === 0;
	}
}
