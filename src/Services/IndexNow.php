<?php
/**
 * IndexNow: tell Bing, Copilot, Yandex, Seznam and Naver about new and updated URLs.
 *
 * @package AEO
 */

namespace AEO\Services;

use AEO\Core\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * IndexNow client.
 */
class IndexNow {

	const HOOK     = 'aeo_indexnow_ping';
	const QUEUE    = 'aeo_indexnow_queue';
	const ENDPOINT = 'https://api.indexnow.org/indexnow';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function register() {
		add_action( 'transition_post_status', array( __CLASS__, 'on_transition' ), 10, 3 );
		add_action( self::HOOK, array( __CLASS__, 'flush_queue' ) );
		add_action( 'template_redirect', array( __CLASS__, 'serve_key' ), 0 );
	}

	/**
	 * Rewrite rule for the key file.
	 *
	 * @return void
	 */
	public static function rewrites() {
		add_rewrite_rule( '^([a-f0-9]{32})\.txt$', 'index.php?aeo_indexnow_key=$matches[1]', 'top' );
	}

	/**
	 * Enabled?
	 *
	 * @return bool
	 */
	public static function enabled() {
		return (bool) Settings::get( 'indexnow', 1 ) && get_option( 'blog_public' ) && ! self::is_local();
	}

	/**
	 * Local/dev sites can't be crawled, so don't ping for them.
	 *
	 * @return bool
	 */
	public static function is_local() {
		$host = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		return in_array( $host, array( 'localhost', '127.0.0.1' ), true )
			|| (bool) preg_match( '/\.(test|local|localhost|invalid|example)$/', $host )
			|| ( function_exists( 'wp_get_environment_type' ) && 'local' === wp_get_environment_type() );
	}

	/**
	 * The site's IndexNow key (generated once).
	 *
	 * @return string
	 */
	public static function key() {
		$key = (string) get_option( 'aeo_indexnow_key', '' );
		if ( ! preg_match( '/^[a-f0-9]{32}$/', $key ) ) {
			$key = md5( wp_generate_password( 32, true, true ) . home_url() );
			update_option( 'aeo_indexnow_key', $key, false );
		}
		return $key;
	}

	/**
	 * Serve /{key}.txt.
	 *
	 * @return void
	 */
	public static function serve_key() {
		$requested = get_query_var( 'aeo_indexnow_key' );
		if ( ! $requested ) {
			return;
		}
		if ( $requested !== self::key() ) {
			status_header( 404 );
			exit;
		}
		status_header( 200 );
		header( 'Content-Type: text/plain; charset=utf-8' );
		echo esc_html( self::key() );
		exit;
	}

	/**
	 * Queue a URL when a post is published, updated or unpublished.
	 *
	 * @param string   $new  New status.
	 * @param string   $old  Old status.
	 * @param \WP_Post $post Post.
	 * @return void
	 */
	public static function on_transition( $new, $old, $post ) {
		if ( ! self::enabled() || wp_is_post_revision( $post ) || wp_is_post_autosave( $post ) ) {
			return;
		}
		if ( ! in_array( $post->post_type, Settings::get_post_types(), true ) ) {
			return;
		}
		if ( 'publish' !== $new && 'publish' !== $old ) {
			return;
		}
		if ( '' !== $post->post_password || Seo_Compat::is_noindex( $post->ID ) ) {
			return;
		}
		self::queue( get_permalink( $post ) );
	}

	/**
	 * Add a URL to the queue and schedule a send in 60s (batches rapid edits).
	 *
	 * @param string $url URL.
	 * @return void
	 */
	public static function queue( $url ) {
		$queue   = (array) get_option( self::QUEUE, array() );
		$queue[] = $url;
		update_option( self::QUEUE, array_slice( array_values( array_unique( $queue ) ), -1000 ), false );
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_single_event( time() + 60, self::HOOK );
		}
	}

	/**
	 * Send queued URLs.
	 *
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function flush_queue() {
		$urls = array_values( array_filter( (array) get_option( self::QUEUE, array() ) ) );
		delete_option( self::QUEUE );
		if ( ! $urls ) {
			return array( 'sent' => 0 );
		}
		return self::submit( $urls );
	}

	/**
	 * Submit URLs.
	 *
	 * @param string[] $urls URLs on this host.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function submit( array $urls ) {
		$host = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		$urls = array_values(
			array_filter(
				$urls,
				static function ( $u ) use ( $host ) {
					return (string) wp_parse_url( $u, PHP_URL_HOST ) === $host;
				}
			)
		);
		if ( ! $urls ) {
			return new \WP_Error( 'aeo_indexnow_empty', __( 'No URLs on this site to submit.', 'answer-engine-optimization' ) );
		}

		$response = wp_remote_post(
			self::ENDPOINT,
			array(
				'timeout' => 15,
				'headers' => array( 'Content-Type' => 'application/json; charset=utf-8' ),
				'body'    => wp_json_encode(
					array(
						'host'        => $host,
						'key'         => self::key(),
						'keyLocation' => home_url( '/' . self::key() . '.txt' ),
						'urlList'     => array_slice( $urls, 0, 10000 ),
					)
				),
			)
		);

		$code   = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );
		$result = array(
			'time'  => current_time( 'mysql' ),
			'sent'  => count( $urls ),
			'code'  => $code,
			'ok'    => in_array( $code, array( 200, 202 ), true ),
			'error' => is_wp_error( $response ) ? $response->get_error_message() : '',
		);
		update_option( 'aeo_indexnow_last', $result, false );

		return $result['ok'] ? $result : new \WP_Error( 'aeo_indexnow_failed', $result['error'] ? $result['error'] : sprintf( 'HTTP %d', $code ) );
	}
}
