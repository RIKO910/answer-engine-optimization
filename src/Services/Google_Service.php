<?php
/**
 * Google Search Console + GA4 connection (OAuth with the site owner's own Google Cloud client).
 *
 * @package AEO
 */

namespace AEO\Services;

use AEO\Core\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Google APIs.
 */
class Google_Service {

	const TOKENS = 'aeo_google_tokens';
	const SCOPES = 'https://www.googleapis.com/auth/webmasters.readonly https://www.googleapis.com/auth/analytics.readonly';

	/**
	 * Register the OAuth callback.
	 *
	 * @return void
	 */
	public static function register() {
		add_action( 'admin_post_aeo_google_callback', array( __CLASS__, 'callback' ) );
	}

	/**
	 * Redirect URI to enter in Google Cloud Console.
	 *
	 * @return string
	 */
	public static function redirect_uri() {
		return admin_url( 'admin-post.php?action=aeo_google_callback' );
	}

	/**
	 * Client credentials.
	 *
	 * @return array{0: string, 1: string}
	 */
	private static function client() {
		return array(
			(string) Settings::get( 'google_client_id', '' ),
			(string) Encryption::decrypt( Settings::get( 'google_client_secret', '' ) ),
		);
	}

	/**
	 * Status for the UI.
	 *
	 * @return array<string, mixed>
	 */
	public static function status() {
		list( $id, $secret ) = self::client();
		$tokens              = self::tokens();
		return array(
			'configured'   => '' !== $id && '' !== $secret,
			'connected'    => ! empty( $tokens['refresh_token'] ),
			'redirect_uri' => self::redirect_uri(),
			'auth_url'     => ( '' !== $id && '' !== $secret ) ? self::auth_url() : '',
			'gsc_site'     => (string) Settings::get( 'gsc_site', '' ),
			'ga4_property' => (string) Settings::get( 'ga4_property', '' ),
		);
	}

	/**
	 * OAuth consent URL.
	 *
	 * @return string
	 */
	public static function auth_url() {
		list( $id ) = self::client();
		return add_query_arg(
			array(
				'client_id'     => rawurlencode( $id ),
				'redirect_uri'  => rawurlencode( self::redirect_uri() ),
				'response_type' => 'code',
				'scope'         => rawurlencode( self::SCOPES ),
				'access_type'   => 'offline',
				'prompt'        => 'consent',
				'state'         => wp_create_nonce( 'aeo_google_oauth' ),
			),
			'https://accounts.google.com/o/oauth2/v2/auth'
		);
	}

	/**
	 * OAuth callback (admin-post.php).
	 *
	 * @return void
	 */
	public static function callback() {
		if ( ! current_user_can( 'aeo_manage_settings' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'answer-engine-optimization' ), 403 );
		}
		$state = isset( $_GET['state'] ) ? sanitize_text_field( wp_unslash( $_GET['state'] ) ) : '';
		if ( ! wp_verify_nonce( $state, 'aeo_google_oauth' ) ) {
			wp_die( esc_html__( 'The link expired. Start the connection again.', 'answer-engine-optimization' ), 400 );
		}
		$back = admin_url( 'admin.php?page=aeo-traffic' );
		if ( isset( $_GET['error'] ) ) {
			wp_safe_redirect( add_query_arg( 'aeo_google', 'denied', $back ) );
			exit;
		}
		$code = isset( $_GET['code'] ) ? sanitize_text_field( wp_unslash( $_GET['code'] ) ) : '';

		list( $id, $secret ) = self::client();
		$response            = wp_remote_post(
			'https://oauth2.googleapis.com/token',
			array(
				'timeout' => 20,
				'body'    => array(
					'code'          => $code,
					'client_id'     => $id,
					'client_secret' => $secret,
					'redirect_uri'  => self::redirect_uri(),
					'grant_type'    => 'authorization_code',
				),
			)
		);
		$data = is_wp_error( $response ) ? null : json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $data['access_token'] ) ) {
			wp_safe_redirect( add_query_arg( 'aeo_google', 'error', $back ) );
			exit;
		}
		self::save_tokens(
			array(
				'access_token'  => $data['access_token'],
				'refresh_token' => $data['refresh_token'] ?? '',
				'expires'       => time() + (int) ( $data['expires_in'] ?? 3600 ) - 60,
			)
		);
		wp_safe_redirect( add_query_arg( 'aeo_google', 'connected', $back ) );
		exit;
	}

	/**
	 * Stored tokens.
	 *
	 * @return array<string, mixed>
	 */
	private static function tokens() {
		$raw = (string) get_option( self::TOKENS, '' );
		$dec = $raw ? json_decode( (string) Encryption::decrypt( $raw ), true ) : null;
		return is_array( $dec ) ? $dec : array();
	}

	/**
	 * Save tokens encrypted.
	 *
	 * @param array<string, mixed> $tokens Tokens.
	 * @return void
	 */
	private static function save_tokens( array $tokens ) {
		update_option( self::TOKENS, Encryption::encrypt( wp_json_encode( $tokens ) ), false );
	}

	/**
	 * Disconnect.
	 *
	 * @return void
	 */
	public static function disconnect() {
		$tokens = self::tokens();
		if ( ! empty( $tokens['refresh_token'] ) ) {
			wp_remote_post( 'https://oauth2.googleapis.com/revoke', array( 'body' => array( 'token' => $tokens['refresh_token'] ) ) );
		}
		delete_option( self::TOKENS );
		delete_transient( 'aeo_gsc_report' );
		delete_transient( 'aeo_ga4_report' );
	}

	/**
	 * Valid access token (refreshing when needed).
	 *
	 * @return string|\WP_Error
	 */
	private static function access_token() {
		$tokens = self::tokens();
		if ( empty( $tokens['refresh_token'] ) ) {
			return new \WP_Error( 'aeo_google_not_connected', __( 'Google is not connected.', 'answer-engine-optimization' ) );
		}
		if ( ! empty( $tokens['access_token'] ) && (int) ( $tokens['expires'] ?? 0 ) > time() ) {
			return $tokens['access_token'];
		}
		list( $id, $secret ) = self::client();
		$response            = wp_remote_post(
			'https://oauth2.googleapis.com/token',
			array(
				'timeout' => 20,
				'body'    => array(
					'client_id'     => $id,
					'client_secret' => $secret,
					'refresh_token' => $tokens['refresh_token'],
					'grant_type'    => 'refresh_token',
				),
			)
		);
		$data = is_wp_error( $response ) ? null : json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $data['access_token'] ) ) {
			return new \WP_Error( 'aeo_google_refresh', __( 'Could not refresh the Google connection. Connect again.', 'answer-engine-optimization' ) );
		}
		$tokens['access_token'] = $data['access_token'];
		$tokens['expires']      = time() + (int) ( $data['expires_in'] ?? 3600 ) - 60;
		self::save_tokens( $tokens );
		return $tokens['access_token'];
	}

	/**
	 * Call a Google API.
	 *
	 * @param string                    $method GET|POST.
	 * @param string                    $url    URL.
	 * @param array<string, mixed>|null $body   JSON body.
	 * @return array<string, mixed>|\WP_Error
	 */
	private static function api( $method, $url, $body = null ) {
		$token = self::access_token();
		if ( is_wp_error( $token ) ) {
			return $token;
		}
		$args = array(
			'method'  => $method,
			'timeout' => 30,
			'headers' => array(
				'Authorization' => 'Bearer ' . $token,
				'Content-Type'  => 'application/json',
			),
		);
		if ( null !== $body ) {
			$args['body'] = wp_json_encode( $body );
		}
		$response = wp_remote_request( $url, $args );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( $code >= 300 || ! is_array( $data ) ) {
			return new \WP_Error( 'aeo_google_http', is_array( $data ) ? (string) ( $data['error']['message'] ?? 'HTTP ' . $code ) : 'HTTP ' . $code );
		}
		return $data;
	}

	/**
	 * Search Console properties.
	 *
	 * @return array<int, string>|\WP_Error
	 */
	public static function gsc_sites() {
		$data = self::api( 'GET', 'https://www.googleapis.com/webmasters/v3/sites' );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		return array_values( array_map( static function ( $s ) {
			return (string) $s['siteUrl'];
		}, (array) ( $data['siteEntry'] ?? array() ) ) );
	}

	/**
	 * GA4 properties.
	 *
	 * @return array<int, array{id: string, name: string}>|\WP_Error
	 */
	public static function ga4_properties() {
		$data = self::api( 'GET', 'https://analyticsadmin.googleapis.com/v1beta/accountSummaries?pageSize=200' );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		$out = array();
		foreach ( (array) ( $data['accountSummaries'] ?? array() ) as $account ) {
			foreach ( (array) ( $account['propertySummaries'] ?? array() ) as $p ) {
				$out[] = array(
					'id'   => str_replace( 'properties/', '', (string) $p['property'] ),
					'name' => ( $account['displayName'] ?? '' ) . ' / ' . ( $p['displayName'] ?? '' ),
				);
			}
		}
		return $out;
	}

	/**
	 * Search Console: top queries, question-style queries and top pages (last 28 days).
	 *
	 * @param bool $refresh Ignore cache.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function gsc_report( $refresh = false ) {
		$site = (string) Settings::get( 'gsc_site', '' );
		if ( '' === $site ) {
			return new \WP_Error( 'aeo_gsc_site', __( 'Choose a Search Console property.', 'answer-engine-optimization' ) );
		}
		$cached = $refresh ? false : get_transient( 'aeo_gsc_report' );
		if ( false !== $cached ) {
			return $cached;
		}

		$url   = 'https://www.googleapis.com/webmasters/v3/sites/' . rawurlencode( $site ) . '/searchAnalytics/query';
		$range = array(
			'startDate' => gmdate( 'Y-m-d', time() - 30 * DAY_IN_SECONDS ),
			'endDate'   => gmdate( 'Y-m-d', time() - 2 * DAY_IN_SECONDS ),
		);

		$queries = self::api( 'POST', $url, $range + array( 'dimensions' => array( 'query' ), 'rowLimit' => 250 ) );
		if ( is_wp_error( $queries ) ) {
			return $queries;
		}
		$pages = self::api( 'POST', $url, $range + array( 'dimensions' => array( 'page' ), 'rowLimit' => 25 ) );
		if ( is_wp_error( $pages ) ) {
			return $pages;
		}

		$map = static function ( $row ) {
			return array(
				'key'         => (string) ( $row['keys'][0] ?? '' ),
				'clicks'      => (int) ( $row['clicks'] ?? 0 ),
				'impressions' => (int) ( $row['impressions'] ?? 0 ),
				'ctr'         => round( 100 * (float) ( $row['ctr'] ?? 0 ), 1 ),
				'position'    => round( (float) ( $row['position'] ?? 0 ), 1 ),
			);
		};
		$q_rows    = array_map( $map, (array) ( $queries['rows'] ?? array() ) );
		$questions = array_values(
			array_filter(
				$q_rows,
				static function ( $r ) {
					return (bool) preg_match( Prompt_Library::QUESTION, $r['key'] ) || substr_count( $r['key'], ' ' ) >= 5;
				}
			)
		);

		$report = array(
			'range'     => $range,
			'queries'   => array_slice( $q_rows, 0, 25 ),
			'questions' => array_slice( $questions, 0, 25 ),
			'pages'     => array_map( $map, (array) ( $pages['rows'] ?? array() ) ),
		);
		set_transient( 'aeo_gsc_report', $report, 6 * HOUR_IN_SECONDS );
		return $report;
	}

	/**
	 * GA4: sessions from AI assistants and their landing pages (last 28 days).
	 *
	 * @param bool $refresh Ignore cache.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function ga4_report( $refresh = false ) {
		$property = preg_replace( '/\D/', '', (string) Settings::get( 'ga4_property', '' ) );
		if ( '' === $property ) {
			return new \WP_Error( 'aeo_ga4_property', __( 'Choose a GA4 property.', 'answer-engine-optimization' ) );
		}
		$cached = $refresh ? false : get_transient( 'aeo_ga4_report' );
		if ( false !== $cached ) {
			return $cached;
		}

		$regex  = implode( '|', array_map( 'preg_quote', array_keys( Traffic_Log::REFERRERS ) ) );
		$filter = array(
			'filter' => array(
				'fieldName'    => 'sessionSource',
				'stringFilter' => array(
					'matchType'     => 'FULL_REGEXP',
					'value'         => '.*(' . $regex . ').*',
					'caseSensitive' => false,
				),
			),
		);
		$url    = 'https://analyticsdata.googleapis.com/v1beta/properties/' . $property . ':runReport';
		$base   = array(
			'dateRanges'      => array( array( 'startDate' => '28daysAgo', 'endDate' => 'today' ) ),
			'dimensionFilter' => $filter,
			'limit'           => 50,
		);

		$sources = self::api(
			'POST',
			$url,
			$base + array(
				'dimensions' => array( array( 'name' => 'sessionSource' ) ),
				'metrics'    => array( array( 'name' => 'sessions' ), array( 'name' => 'engagedSessions' ), array( 'name' => 'keyEvents' ) ),
			)
		);
		if ( is_wp_error( $sources ) ) {
			return $sources;
		}
		$pages = self::api(
			'POST',
			$url,
			$base + array(
				'dimensions' => array( array( 'name' => 'landingPage' ) ),
				'metrics'    => array( array( 'name' => 'sessions' ) ),
			)
		);
		if ( is_wp_error( $pages ) ) {
			return $pages;
		}

		$rows = static function ( $data ) {
			$out = array();
			foreach ( (array) ( $data['rows'] ?? array() ) as $row ) {
				$item = array( 'key' => (string) ( $row['dimensionValues'][0]['value'] ?? '' ) );
				foreach ( (array) ( $row['metricValues'] ?? array() ) as $i => $m ) {
					$item[ 'm' . $i ] = (int) ( $m['value'] ?? 0 );
				}
				$out[] = $item;
			}
			return $out;
		};

		$report = array(
			'sources' => array_map(
				static function ( $r ) {
					return array(
						'source'    => $r['key'],
						'assistant' => Traffic_Log::source_from( $r['key'] ),
						'sessions'  => $r['m0'] ?? 0,
						'engaged'   => $r['m1'] ?? 0,
						'key_events' => $r['m2'] ?? 0,
					);
				},
				$rows( $sources )
			),
			'pages'   => array_map(
				static function ( $r ) {
					return array(
						'page'     => $r['key'],
						'sessions' => $r['m0'] ?? 0,
					);
				},
				$rows( $pages )
			),
		);
		set_transient( 'aeo_ga4_report', $report, 6 * HOUR_IN_SECONDS );
		return $report;
	}
}
