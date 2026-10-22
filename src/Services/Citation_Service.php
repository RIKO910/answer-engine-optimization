<?php
/**
 * AI citation tracking and competitor comparison.
 *
 * Citations are checked for real with Perplexity's Sonar API, which returns the
 * source URLs it used for each answer. Nothing here is simulated: if no Perplexity
 * key is configured, scanning is unavailable.
 *
 * @package AEO
 */

namespace AEO\Services;

use AEO\Core\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Citation and competitor service.
 */
class Citation_Service {

	const ENGINE          = 'perplexity';
	const ENDPOINT        = 'https://api.perplexity.ai/v1/sonar';
	const LAST_SCAN       = 'aeo_last_citation_scan';
	const MAX_PER_SCAN    = 10;

	/**
	 * Whether real citation scanning is possible.
	 *
	 * @return bool
	 */
	public function is_available() {
		return '' !== $this->api_key();
	}

	/**
	 * Decrypted Perplexity key.
	 *
	 * @return string
	 */
	private function api_key() {
		return (string) Encryption::decrypt( Settings::get( 'perplexity_api_key', '' ) );
	}

	/**
	 * Stored citation analytics.
	 *
	 * @return array<string, mixed>
	 */
	public function get_analytics() {
		global $wpdb;
		$table = $wpdb->prefix . 'aeo_citations';

		// phpcs:disable WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$total     = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
		$by_engine = $wpdb->get_results( "SELECT engine, COUNT(*) as count FROM {$table} GROUP BY engine", ARRAY_A );
		$recent    = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} ORDER BY detected_at DESC LIMIT %d", 20 ), ARRAY_A );
		$trend     = $wpdb->get_results(
			"SELECT DATE(detected_at) as date, COUNT(*) as count FROM {$table}
			WHERE detected_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
			GROUP BY DATE(detected_at) ORDER BY date ASC",
			ARRAY_A
		);
		// phpcs:enable

		$last = get_option( self::LAST_SCAN, array() );

		return array(
			'total'     => $total,
			'by_engine' => $by_engine ? $by_engine : array(),
			'recent'    => $recent ? $recent : array(),
			'trend'     => $trend ? $trend : array(),
			'available' => $this->is_available(),
			'last_scan' => is_array( $last ) ? $last : array(),
		);
	}

	/**
	 * Ask Perplexity each tracked keyword and record answers that cite this site.
	 *
	 * @return array<string, mixed>|\WP_Error
	 */
	public function scan_citations() {
		if ( ! $this->is_available() ) {
			return new \WP_Error( 'aeo_no_perplexity_key', __( 'Add a Perplexity API key in Settings to scan real AI citations.', 'answer-engine-optimization' ) );
		}

		$keywords = array_slice( (array) Settings::get( 'tracked_keywords', array() ), 0, self::MAX_PER_SCAN );
		if ( empty( $keywords ) ) {
			return new \WP_Error( 'aeo_no_keywords', __( 'Add tracked keywords in Settings first.', 'answer-engine-optimization' ) );
		}

		$own_host = $this->host( home_url() );
		$results  = array();
		$cited    = 0;
		$errors   = 0;

		global $wpdb;
		$table = $wpdb->prefix . 'aeo_citations';

		foreach ( $keywords as $keyword ) {
			$sources = $this->ask_perplexity( $keyword );
			if ( is_wp_error( $sources ) ) {
				$errors++;
				$results[] = array(
					'keyword' => $keyword,
					'cited'   => false,
					'urls'    => array(),
					'sources' => array(),
					'error'   => $sources->get_error_message(),
				);
				// Stop early on auth/billing errors; they will fail for every keyword.
				if ( in_array( $sources->get_error_code(), array( 'aeo_http_401', 'aeo_http_402', 'aeo_http_403' ), true ) ) {
					break;
				}
				continue;
			}

			$own = array();
			foreach ( $sources as $url ) {
				if ( $own_host && $this->host( $url ) === $own_host ) {
					$own[] = $url;
				}
			}
			$own = array_values( array_unique( $own ) );

			foreach ( $own as $url ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$exists = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE keyword = %s AND citation_url = %s AND engine = %s AND DATE(detected_at) = CURDATE()", $keyword, $url, self::ENGINE ) );
				if ( ! $exists ) {
					$wpdb->insert(
						$table,
						array(
							'keyword'      => $keyword,
							'page_url'     => $url,
							'engine'       => self::ENGINE,
							'citation_url' => $url,
							'detected_at'  => current_time( 'mysql' ),
						),
						array( '%s', '%s', '%s', '%s', '%s' )
					);
				}
			}

			if ( $own ) {
				$cited++;
			}

			$results[] = array(
				'keyword' => $keyword,
				'cited'   => ! empty( $own ),
				'urls'    => $own,
				'sources' => array_slice( array_values( array_unique( array_map( array( $this, 'host' ), $sources ) ) ), 0, 5 ),
			);
		}

		if ( $errors && $errors === count( $results ) ) {
			return new \WP_Error( 'aeo_scan_failed', $results[0]['error'] ?? __( 'Citation scan failed.', 'answer-engine-optimization' ) );
		}

		$summary = array(
			'scanned_at' => current_time( 'mysql' ),
			'engine'     => self::ENGINE,
			'checked'    => count( $results ),
			'cited'      => $cited,
			'results'    => $results,
		);
		update_option( self::LAST_SCAN, $summary, false );

		return $summary;
	}

	/**
	 * Ask Perplexity and return every source URL it used.
	 *
	 * @param string $keyword Question or keyword.
	 * @return string[]|\WP_Error
	 */
	private function ask_perplexity( $keyword ) {
		$response = wp_remote_post(
			self::ENDPOINT,
			array(
				'timeout' => 60,
				'headers' => array(
					'Authorization' => 'Bearer ' . $this->api_key(),
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'model'    => Settings::get_model( 'perplexity' ),
						'messages' => array(
							array(
								'role'    => 'user',
								'content' => $keyword,
							),
						),
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( $code < 200 || $code >= 300 || ! is_array( $body ) ) {
			$message = is_array( $body ) ? (string) ( $body['error']['message'] ?? '' ) : '';
			/* translators: 1: HTTP status, 2: API message */
			return new \WP_Error( 'aeo_http_' . $code, sprintf( __( 'Perplexity returned HTTP %1$d. %2$s', 'answer-engine-optimization' ), $code, $message ) );
		}

		return self::extract_source_urls( $body );
	}

	/**
	 * Collect source URLs from a Sonar or Agent API response body.
	 *
	 * @param array<string, mixed> $body Decoded body.
	 * @return string[]
	 */
	public static function extract_source_urls( array $body ) {
		$urls = array();

		foreach ( (array) ( $body['citations'] ?? array() ) as $url ) {
			if ( is_string( $url ) ) {
				$urls[] = $url;
			}
		}
		foreach ( (array) ( $body['search_results'] ?? array() ) as $result ) {
			if ( is_array( $result ) && ! empty( $result['url'] ) ) {
				$urls[] = (string) $result['url'];
			}
		}
		// Agent API shape: output[] items of type "search_results".
		foreach ( (array) ( $body['output'] ?? array() ) as $item ) {
			if ( is_array( $item ) && 'search_results' === ( $item['type'] ?? '' ) ) {
				foreach ( (array) ( $item['results'] ?? array() ) as $result ) {
					if ( is_array( $result ) && ! empty( $result['url'] ) ) {
						$urls[] = (string) $result['url'];
					}
				}
			}
		}

		$urls = array_filter(
			$urls,
			static function ( $url ) {
				return false !== filter_var( $url, FILTER_VALIDATE_URL ) && in_array( strtolower( (string) wp_parse_url( $url, PHP_URL_SCHEME ) ), array( 'http', 'https' ), true );
			}
		);
		return array_values( array_unique( $urls ) );
	}

	/**
	 * Host without www.
	 *
	 * @param string $url URL.
	 * @return string
	 */
	public function host( $url ) {
		$host = (string) wp_parse_url( (string) $url, PHP_URL_HOST );
		return preg_replace( '/^www\./', '', strtolower( $host ) );
	}

	/**
	 * Compare competitors' homepage structured data and llms.txt with this site.
	 *
	 * @param bool $refresh Ignore cached results.
	 * @return array<string, mixed>
	 */
	public function competitor_analysis( $refresh = false ) {
		$competitors = (array) Settings::get( 'competitors', array() );
		$own_types   = $this->own_schema_types();
		$rows        = array();

		foreach ( $competitors as $domain ) {
			$domain = Settings::normalize_domain( $domain );
			if ( ! $domain ) {
				continue;
			}

			$key  = 'aeo_comp_' . md5( $domain );
			$data = $refresh ? false : get_transient( $key );
			if ( false === $data ) {
				$data = $this->inspect_domain( $domain );
				set_transient( $key, $data, empty( $data['error'] ) ? 12 * HOUR_IN_SECONDS : HOUR_IN_SECONDS );
			}

			$data['missing_types'] = array_values( array_diff( $data['schema_types'], $own_types ) );
			$rows[]                = $data;
		}

		return array(
			'own_types'   => $own_types,
			'competitors' => $rows,
		);
	}

	/**
	 * Fetch a competitor homepage and llms.txt.
	 *
	 * @param string $domain Domain.
	 * @return array<string, mixed>
	 */
	private function inspect_domain( $domain ) {
		$args = array(
			'timeout'     => 15,
			'redirection' => 3,
			'user-agent'  => 'Mozilla/5.0 (compatible; AEO-Plugin/' . ( defined( 'AEO_VERSION' ) ? AEO_VERSION : '1' ) . '; +' . home_url( '/' ) . ')',
		);

		$result = array(
			'domain'       => $domain,
			'schema_types' => array(),
			'has_llms_txt' => false,
			'error'        => '',
			'checked_at'   => current_time( 'mysql' ),
		);

		$home = wp_safe_remote_get( 'https://' . $domain . '/', $args );
		if ( is_wp_error( $home ) || 200 !== (int) wp_remote_retrieve_response_code( $home ) ) {
			$result['error'] = is_wp_error( $home )
				? $home->get_error_message()
				/* translators: %d: HTTP status */
				: sprintf( __( 'Homepage returned HTTP %d', 'answer-engine-optimization' ), (int) wp_remote_retrieve_response_code( $home ) );
		} else {
			$result['schema_types'] = self::extract_schema_types( (string) wp_remote_retrieve_body( $home ) );
		}

		$llms = wp_safe_remote_get( 'https://' . $domain . '/llms.txt', $args );
		if ( ! is_wp_error( $llms ) && 200 === (int) wp_remote_retrieve_response_code( $llms ) ) {
			$type                   = (string) wp_remote_retrieve_header( $llms, 'content-type' );
			$body                   = ltrim( (string) wp_remote_retrieve_body( $llms ) );
			$result['has_llms_txt'] = false === stripos( $type, 'html' ) && 0 === strpos( $body, '#' );
		}

		return $result;
	}

	/**
	 * Every @type found in JSON-LD blocks of an HTML document.
	 *
	 * @param string $html HTML.
	 * @return string[]
	 */
	public static function extract_schema_types( $html ) {
		$types = array();
		if ( ! preg_match_all( '#<script[^>]+type=["\']application/ld\+json["\'][^>]*>(.*?)</script>#is', $html, $m ) ) {
			return $types;
		}
		$walk = static function ( $node ) use ( &$walk, &$types ) {
			if ( ! is_array( $node ) ) {
				return;
			}
			if ( isset( $node['@type'] ) ) {
				foreach ( (array) $node['@type'] as $t ) {
					if ( is_string( $t ) ) {
						$types[] = $t;
					}
				}
			}
			foreach ( $node as $value ) {
				if ( is_array( $value ) ) {
					$walk( $value );
				}
			}
		};
		foreach ( $m[1] as $json ) {
			$walk( json_decode( html_entity_decode( trim( $json ) ), true ) );
		}
		$types = array_values( array_unique( $types ) );
		sort( $types );
		return $types;
	}

	/**
	 * Schema types this site outputs (approximation from configuration).
	 *
	 * @return string[]
	 */
	public function own_schema_types() {
		global $wpdb;
		$types = array( 'WebSite', 'Organization', 'WebPage', 'ImageObject' );

		if ( get_posts( array( 'post_type' => 'post', 'post_status' => 'publish', 'posts_per_page' => 1, 'fields' => 'ids' ) ) ) {
			$types[] = 'Article';
			$types[] = 'Person';
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( $wpdb->get_var( "SELECT 1 FROM {$wpdb->postmeta} WHERE meta_key = '_aeo_faq_items' LIMIT 1" ) ) {
			$types = array_merge( $types, array( 'FAQPage', 'Question', 'Answer' ) );
		}
		if ( Settings::is_module_enabled( 'local_business' ) ) {
			foreach ( (array) get_option( 'aeo_local_locations', array() ) as $loc ) {
				if ( is_array( $loc ) && ! empty( $loc['name'] ) ) {
					$types[] = ! empty( $loc['type'] ) ? $loc['type'] : 'LocalBusiness';
					$types[] = 'PostalAddress';
				}
			}
		}
		if ( class_exists( 'WooCommerce' ) ) {
			$types = array_merge( $types, array( 'Product', 'Offer' ) );
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$custom = $wpdb->get_col( "SELECT DISTINCT schema_type FROM {$wpdb->prefix}aeo_schema_cache" );
		if ( is_array( $custom ) ) {
			$types = array_merge( $types, $custom );
		}

		$types = array_values( array_unique( array_filter( $types ) ) );
		sort( $types );
		return $types;
	}
}
