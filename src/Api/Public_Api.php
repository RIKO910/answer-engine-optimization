<?php
/**
 * Public (unauthenticated) endpoints: schema map and AI referral beacon.
 *
 * @package AEO
 */

namespace AEO\Api;

use AEO\Core\Settings;
use AEO\Frontend\Llms_Txt;
use AEO\Services\Schema_Service;
use AEO\Services\Traffic_Log;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Public REST routes.
 */
class Public_Api {

	const NS = 'aeo-genius/v1';

	/**
	 * Register on rest_api_init.
	 *
	 * @return void
	 */
	public static function register() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	/**
	 * Routes.
	 *
	 * @return void
	 */
	public static function routes() {
		register_rest_route(
			self::NS,
			'/schemamap',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'schemamap' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'page' => array(
						'type'    => 'integer',
						'default' => 1,
						'minimum' => 1,
					),
				),
			)
		);

		register_rest_route(
			self::NS,
			'/track',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'track' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Every entity this site describes, as one paginated JSON-LD graph.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function schemamap( WP_REST_Request $request ) {
		if ( ! Settings::get( 'aeo_enable_schema', 1 ) ) {
			return new WP_REST_Response( array( 'message' => 'Structured data is disabled.' ), 404 );
		}

		$page     = max( 1, (int) $request['page'] );
		$per_page = 50;
		$key      = 'aeo_schemamap_' . $page;
		$data     = get_transient( $key );

		if ( false === $data ) {
			$service = new Schema_Service();
			$nodes   = array();

			if ( 1 === $page ) {
				$global  = $service->get_global_schema();
				$nodes[] = $global['website'];
				$nodes[] = $global['organization'];
				foreach ( array_values( (array) get_option( 'aeo_local_locations', array() ) ) as $i => $location ) {
					if ( is_array( $location ) ) {
						$nodes[] = $service->build_location_node( $location, $i );
					}
				}
			}

			$query = new \WP_Query(
				array(
					'post_type'      => Llms_Txt::post_types(),
					'post_status'    => 'publish',
					'has_password'   => false,
					'posts_per_page' => $per_page,
					'paged'          => $page,
					'orderby'        => 'ID',
					'order'          => 'ASC',
					'fields'         => 'ids',
				)
			);

			foreach ( $query->posts as $post_id ) {
				if ( Llms_Txt::is_public( $post_id ) ) {
					$nodes = array_merge( $nodes, $service->get_post_nodes( $post_id, false ) );
				}
			}

			$graph = Schema_Service::graph( $nodes );
			$data  = array(
				'@context'    => 'https://schema.org',
				'@graph'      => $graph['@graph'] ?? array(),
				'page'        => $page,
				'total_pages' => max( 1, (int) $query->max_num_pages ),
			);
			if ( $page < $data['total_pages'] ) {
				$data['next'] = add_query_arg( 'page', $page + 1, rest_url( self::NS . '/schemamap' ) );
			}
			set_transient( $key, $data, HOUR_IN_SECONDS );
		}

		$response = new WP_REST_Response( $data );
		$response->header( 'Content-Type', 'application/ld+json; charset=utf-8' );
		$response->header( 'Cache-Control', 'public, max-age=3600' );
		return $response;
	}

	/**
	 * Clear cached schema map pages.
	 *
	 * @return void
	 */
	public static function flush_schemamap() {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_aeo\_schemamap\_%' OR option_name LIKE '\_transient\_timeout\_aeo\_schemamap\_%'" );
	}

	/**
	 * Record a visit referred by an AI assistant (sent by the front-end beacon).
	 *
	 * Stores only the assistant name and the landing path: no IP, no cookies, no user data.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function track( WP_REST_Request $request ) {
		if ( ! Settings::get( 'traffic_logging', 1 ) ) {
			return new WP_REST_Response( null, 204 );
		}

		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = $request->get_params();
		}

		$source = Traffic_Log::source_from( (string) ( $params['ref'] ?? '' ) );
		if ( '' === $source ) {
			$source = Traffic_Log::source_from( (string) ( $params['utm'] ?? '' ) );
		}
		$path = (string) ( $params['path'] ?? '' );
		if ( '' === $source || '' === $path || '/' !== $path[0] || strlen( $path ) > 500 ) {
			return new WP_REST_Response( null, 204 );
		}

		// Light rate limit per client, stored as a hash only.
		$ip   = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$rl   = 'aeo_rl_' . substr( md5( $ip . wp_salt( 'nonce' ) ), 0, 16 );
		$hits = (int) get_transient( $rl );
		if ( $hits >= 30 ) {
			return new WP_REST_Response( null, 204 );
		}
		set_transient( $rl, $hits + 1, HOUR_IN_SECONDS );

		Traffic_Log::insert(
			array(
				'type'   => 'referral',
				'agent'  => 'visitor',
				'source' => $source,
				'url'    => esc_url_raw( $path ),
				'status' => 200,
			)
		);

		return new WP_REST_Response( null, 204 );
	}
}
