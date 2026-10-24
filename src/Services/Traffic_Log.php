<?php
/**
 * AI crawler and AI referral traffic log.
 *
 * @package AEO
 */

namespace AEO\Services;

defined( 'ABSPATH' ) || exit;

/**
 * Storage and reports for the aeo_ai_traffic table.
 */
class Traffic_Log {

	/**
	 * AI assistants that send visitors, by referrer host.
	 */
	const REFERRERS = array(
		'chatgpt.com'            => 'ChatGPT',
		'chat.openai.com'        => 'ChatGPT',
		'perplexity.ai'          => 'Perplexity',
		'gemini.google.com'      => 'Gemini',
		'bard.google.com'        => 'Gemini',
		'copilot.microsoft.com'  => 'Copilot',
		'copilot.cloud.microsoft' => 'Copilot',
		'claude.ai'              => 'Claude',
		'chat.deepseek.com'      => 'DeepSeek',
		'meta.ai'                => 'Meta AI',
		'grok.com'               => 'Grok',
		'you.com'                => 'You.com',
		'phind.com'              => 'Phind',
		'chat.mistral.ai'        => 'Mistral',
		'kagi.com'               => 'Kagi',
	);

	/**
	 * Table name.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'aeo_ai_traffic';
	}

	/**
	 * Map a referrer URL or utm_source value to an assistant name.
	 *
	 * @param string $value Referrer URL, host or utm_source.
	 * @return string
	 */
	public static function source_from( $value ) {
		$value = strtolower( trim( (string) $value ) );
		if ( '' === $value ) {
			return '';
		}
		$host = false !== strpos( $value, '://' ) ? (string) wp_parse_url( $value, PHP_URL_HOST ) : $value;
		$host = preg_replace( '/^www\./', '', $host );
		foreach ( self::REFERRERS as $domain => $name ) {
			if ( $host === $domain || substr( $host, -strlen( '.' . $domain ) ) === '.' . $domain ) {
				return $name;
			}
		}
		if ( 'bing.com' === $host && false !== strpos( $value, '/chat' ) ) {
			return 'Copilot';
		}
		return '';
	}

	/**
	 * Insert a row.
	 *
	 * @param array<string, mixed> $row type, agent, source, url, status.
	 * @return void
	 */
	public static function insert( array $row ) {
		global $wpdb;
		$wpdb->insert(
			self::table(),
			array(
				'type'       => substr( (string) ( $row['type'] ?? 'bot' ), 0, 20 ),
				'agent'      => substr( (string) ( $row['agent'] ?? '' ), 0, 64 ),
				'source'     => substr( (string) ( $row['source'] ?? '' ), 0, 64 ),
				'url'        => substr( (string) ( $row['url'] ?? '' ), 0, 500 ),
				'status'     => (int) ( $row['status'] ?? 0 ),
				'created_at' => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s', '%d', '%s' )
		);
	}

	/**
	 * Delete rows older than N days.
	 *
	 * @param int $days Days to keep.
	 * @return void
	 */
	public static function prune( $days = 90 ) {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE created_at < %s", gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS ) ) );
	}

	/**
	 * Report for the last N days.
	 *
	 * @param int $days Days.
	 * @return array<string, mixed>
	 */
	public static function report( $days = 30 ) {
		global $wpdb;
		$table = self::table();
		$since = gmdate( 'Y-m-d 00:00:00', time() - $days * DAY_IN_SECONDS );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$by_agent  = $wpdb->get_results( $wpdb->prepare( "SELECT agent, source, COUNT(*) AS hits, MAX(created_at) AS last_seen FROM {$table} WHERE type = 'bot' AND created_at >= %s GROUP BY agent, source ORDER BY hits DESC", $since ), ARRAY_A );
		$referrals = $wpdb->get_results( $wpdb->prepare( "SELECT source, COUNT(*) AS visits FROM {$table} WHERE type = 'referral' AND created_at >= %s GROUP BY source ORDER BY visits DESC", $since ), ARRAY_A );
		$daily     = $wpdb->get_results( $wpdb->prepare( "SELECT DATE(created_at) AS date, SUM(CASE WHEN type = 'bot' THEN 1 ELSE 0 END) AS bots, SUM(CASE WHEN type = 'referral' THEN 1 ELSE 0 END) AS referrals FROM {$table} WHERE created_at >= %s GROUP BY DATE(created_at) ORDER BY date ASC", $since ), ARRAY_A );
		$top_bot   = $wpdb->get_results( $wpdb->prepare( "SELECT url, COUNT(*) AS hits FROM {$table} WHERE type = 'bot' AND created_at >= %s GROUP BY url ORDER BY hits DESC LIMIT 10", $since ), ARRAY_A );
		$top_ref   = $wpdb->get_results( $wpdb->prepare( "SELECT url, COUNT(*) AS visits FROM {$table} WHERE type = 'referral' AND created_at >= %s GROUP BY url ORDER BY visits DESC LIMIT 10", $since ), ARRAY_A );
		$errors    = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE type = 'bot' AND status >= 400 AND created_at >= %s", $since ) );
		// phpcs:enable

		$cast = static function ( $rows, $keys ) {
			$rows = is_array( $rows ) ? $rows : array();
			foreach ( $rows as $i => $r ) {
				foreach ( $keys as $k ) {
					$rows[ $i ][ $k ] = (int) $r[ $k ];
				}
			}
			return $rows;
		};

		return array(
			'days'          => $days,
			'bots'          => $cast( $by_agent, array( 'hits' ) ),
			'referrals'     => $cast( $referrals, array( 'visits' ) ),
			'daily'         => $cast( $daily, array( 'bots', 'referrals' ) ),
			'top_bot_pages' => $cast( $top_bot, array( 'hits' ) ),
			'top_referred'  => $cast( $top_ref, array( 'visits' ) ),
			'bot_errors'    => $errors,
		);
	}
}
