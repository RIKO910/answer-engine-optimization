<?php
/**
 * Import of the Bing Webmaster Tools "AI Performance" CSV export (Copilot citations).
 *
 * Bing has no public API for this report yet, so the site owner exports the CSV
 * from Bing Webmaster Tools and uploads it here. Column names are matched loosely.
 *
 * @package AEO
 */

namespace AEO\Services;

defined( 'ABSPATH' ) || exit;

/**
 * Bing AI Performance import.
 */
class Bing_Import {

	const OPTION = 'aeo_bing_ai_performance';

	/**
	 * Parse and store a CSV.
	 *
	 * @param string $csv CSV text.
	 * @return array<string, mixed>|\WP_Error Report.
	 */
	public static function import( $csv ) {
		$csv   = preg_replace( '/^\xEF\xBB\xBF/', '', (string) $csv );
		$lines = preg_split( '/\r\n|\r|\n/', trim( $csv ) );
		if ( count( $lines ) < 2 ) {
			return new \WP_Error( 'aeo_bing_empty', __( 'The file has no data rows.', 'answer-engine-optimization' ) );
		}

		$delimiter = substr_count( $lines[0], ';' ) > substr_count( $lines[0], ',' ) ? ';' : ( false !== strpos( $lines[0], "\t" ) ? "\t" : ',' );
		$header    = array_map( static function ( $h ) {
			return strtolower( trim( $h ) );
		}, str_getcsv( $lines[0], $delimiter ) );

		$find = static function ( array $needles ) use ( $header ) {
			foreach ( $header as $i => $h ) {
				foreach ( $needles as $n ) {
					if ( false !== strpos( $h, $n ) ) {
						return $i;
					}
				}
			}
			return -1;
		};

		$col_date  = $find( array( 'date', 'day' ) );
		$col_page  = $find( array( 'page', 'url' ) );
		$col_query = $find( array( 'grounding', 'query', 'queries' ) );
		$col_cites = $find( array( 'citation', 'cited', 'count' ) );

		if ( $col_page < 0 && $col_query < 0 ) {
			return new \WP_Error( 'aeo_bing_columns', __( 'Could not find a page/URL or query column. Export the AI Performance report from Bing Webmaster Tools as CSV.', 'answer-engine-optimization' ) );
		}

		$rows = array();
		foreach ( array_slice( $lines, 1 ) as $line ) {
			if ( '' === trim( $line ) ) {
				continue;
			}
			$cells  = str_getcsv( $line, $delimiter );
			$rows[] = array(
				'date'      => $col_date >= 0 ? substr( sanitize_text_field( $cells[ $col_date ] ?? '' ), 0, 10 ) : '',
				'page'      => $col_page >= 0 ? esc_url_raw( trim( $cells[ $col_page ] ?? '' ) ) : '',
				'query'     => $col_query >= 0 ? sanitize_text_field( $cells[ $col_query ] ?? '' ) : '',
				'citations' => $col_cites >= 0 ? (int) preg_replace( '/\D/', '', (string) ( $cells[ $col_cites ] ?? '1' ) ) : 1,
			);
			if ( count( $rows ) >= 20000 ) {
				break;
			}
		}

		$report = self::aggregate( $rows );
		update_option( self::OPTION, $report, false );
		return $report;
	}

	/**
	 * Aggregate rows into a compact report.
	 *
	 * @param array<int, array<string, mixed>> $rows Rows.
	 * @return array<string, mixed>
	 */
	private static function aggregate( array $rows ) {
		$by_page  = array();
		$by_query = array();
		$by_date  = array();
		$total    = 0;
		foreach ( $rows as $r ) {
			$c      = max( 0, (int) $r['citations'] );
			$total += $c;
			if ( $r['page'] ) {
				$by_page[ $r['page'] ] = ( $by_page[ $r['page'] ] ?? 0 ) + $c;
			}
			if ( $r['query'] ) {
				$by_query[ $r['query'] ] = ( $by_query[ $r['query'] ] ?? 0 ) + $c;
			}
			if ( $r['date'] ) {
				$by_date[ $r['date'] ] = ( $by_date[ $r['date'] ] ?? 0 ) + $c;
			}
		}
		arsort( $by_page );
		arsort( $by_query );
		ksort( $by_date );

		$pairs = static function ( $map, $key, $limit ) {
			$out = array();
			foreach ( array_slice( $map, 0, $limit, true ) as $k => $v ) {
				$out[] = array(
					$key        => $k,
					'citations' => $v,
				);
			}
			return $out;
		};

		return array(
			'imported_at' => current_time( 'mysql' ),
			'rows'        => count( $rows ),
			'total'       => $total,
			'pages'       => $pairs( $by_page, 'page', 20 ),
			'queries'     => $pairs( $by_query, 'query', 20 ),
			'daily'       => $pairs( $by_date, 'date', 400 ),
		);
	}

	/**
	 * Stored report.
	 *
	 * @return array<string, mixed>
	 */
	public static function report() {
		$r = get_option( self::OPTION, array() );
		return is_array( $r ) ? $r : array();
	}
}
