<?php
/**
 * REST routes for crawlers, traffic, AI visibility, Google, Bing and reports.
 *
 * @package AEO
 */

namespace AEO\Api;

use AEO\Core\Settings;
use AEO\Services\Ai_Visibility;
use AEO\Services\Bing_Import;
use AEO\Services\Encryption;
use AEO\Services\Google_Service;
use AEO\Services\IndexNow;
use AEO\Services\Prompt_Library;
use AEO\Services\Report_Service;
use AEO\Services\Traffic_Log;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Extra admin routes.
 */
class Rest_Extra {

	const NS = 'aeo-genius/v1';

	/**
	 * Register.
	 *
	 * @return void
	 */
	public static function register() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	/**
	 * Permission helpers.
	 *
	 * @return bool
	 */
	public static function can_view() {
		return current_user_can( 'aeo_view_reports' );
	}

	/**
	 * Settings permission.
	 *
	 * @return bool
	 */
	public static function can_manage() {
		return current_user_can( 'aeo_manage_settings' );
	}

	/**
	 * AI permission.
	 *
	 * @return bool
	 */
	public static function can_ai() {
		return current_user_can( 'aeo_use_ai' );
	}

	/**
	 * Shorthand route registration.
	 *
	 * @param string   $route    Route.
	 * @param string   $methods  Methods.
	 * @param callable $callback Callback.
	 * @param callable $perm     Permission.
	 * @return void
	 */
	private static function route( $route, $methods, $callback, $perm ) {
		register_rest_route(
			self::NS,
			$route,
			array(
				'methods'             => $methods,
				'callback'            => $callback,
				'permission_callback' => $perm,
			)
		);
	}

	/**
	 * Wrap a value or WP_Error.
	 *
	 * @param mixed $value Value.
	 * @param int   $code  Error status.
	 * @return WP_REST_Response
	 */
	public static function respond( $value, $code = 400 ) {
		if ( is_wp_error( $value ) ) {
			return new WP_REST_Response(
				array(
					'message' => $value->get_error_message(),
					'code'    => $value->get_error_code(),
				),
				$code
			);
		}
		return new WP_REST_Response( $value );
	}

	/**
	 * Routes.
	 *
	 * @return void
	 */
	public static function routes() {
		$view   = array( __CLASS__, 'can_view' );
		$manage = array( __CLASS__, 'can_manage' );
		$ai     = array( __CLASS__, 'can_ai' );
		$get    = WP_REST_Server::READABLE;
		$post   = WP_REST_Server::CREATABLE;

		// IndexNow.
		self::route(
			'/indexnow/submit',
			$post,
			static function () {
				if ( IndexNow::is_local() ) {
					return self::respond( new \WP_Error( 'aeo_local', __( 'This looks like a local site, which search engines cannot reach.', 'answer-engine-optimization' ) ) );
				}
				$urls = array( home_url( '/' ) );
				foreach ( get_posts( array( 'post_type' => Settings::get_post_types(), 'post_status' => 'publish', 'posts_per_page' => 200, 'orderby' => 'modified', 'has_password' => false ) ) as $p ) {
					$urls[] = get_permalink( $p );
				}
				return self::respond( IndexNow::submit( $urls ) );
			},
			$manage
		);

		// AI traffic.
		self::route(
			'/traffic',
			$get,
			static function ( WP_REST_Request $r ) {
				$days = max( 1, min( 90, (int) ( $r->get_param( 'days' ) ? $r->get_param( 'days' ) : 30 ) ) );
				return self::respond( Traffic_Log::report( $days ) );
			},
			$view
		);

		// AI visibility (multi-engine citations).
		self::route(
			'/visibility',
			$get,
			static function () {
				$v     = new Ai_Visibility();
				$state = $v->state();
				return self::respond(
					array(
						'engines'   => Ai_Visibility::available_engines(),
						'all'       => array_map(
							static function ( $k, $d ) {
								return array(
									'engine' => $k,
									'label'  => $d['label'],
								);
							},
							array_keys( Ai_Visibility::ENGINES ),
							Ai_Visibility::ENGINES
						),
						'summary'   => $v->scan_summary(),
						'history'   => $v->history(),
						'progress'  => $state ? array(
							'done'     => (int) $state['done'],
							'total'    => count( $state['items'] ),
							'finished' => (string) $state['finished'],
						) : null,
						'brands'    => Ai_Visibility::brand_names(),
					)
				);
			},
			$view
		);
		self::route(
			'/visibility/start',
			$post,
			static function () {
				$v     = new Ai_Visibility();
				$state = $v->start();
				if ( is_wp_error( $state ) ) {
					return self::respond( $state );
				}
				return self::respond( $v->process( 15 ) );
			},
			$ai
		);
		self::route(
			'/visibility/continue',
			$post,
			static function () {
				return self::respond( ( new Ai_Visibility() )->process( 20 ) );
			},
			$ai
		);
		register_rest_route(
			self::NS,
			'/visibility/answer/(?P<id>\d+)',
			array(
				'methods'             => $get,
				'callback'            => static function ( WP_REST_Request $r ) {
					$row = ( new Ai_Visibility() )->answer( (int) $r['id'] );
					return $row ? self::respond( $row ) : self::respond( new \WP_Error( 'aeo_404', 'Not found' ), 404 );
				},
				'permission_callback' => $view,
			)
		);

		// Prompt library.
		self::route(
			'/prompts/suggest',
			$get,
			static function () {
				return self::respond( Prompt_Library::suggest() );
			},
			$view
		);

		// Google.
		self::route(
			'/google/status',
			$get,
			static function () {
				return self::respond( Google_Service::status() );
			},
			$view
		);
		self::route(
			'/google/credentials',
			$post,
			static function ( WP_REST_Request $r ) {
				$p = (array) $r->get_json_params();
				$u = array( 'google_client_id' => sanitize_text_field( (string) ( $p['client_id'] ?? '' ) ) );
				if ( ! empty( $p['client_secret'] ) ) {
					$u['google_client_secret'] = Encryption::encrypt( sanitize_text_field( (string) $p['client_secret'] ) );
				}
				Settings::update( $u );
				return self::respond( Google_Service::status() );
			},
			$manage
		);
		self::route(
			'/google/sites',
			$get,
			static function () {
				return self::respond(
					array(
						'sites'      => Google_Service::gsc_sites(),
						'properties' => Google_Service::ga4_properties(),
					)
				);
			},
			$manage
		);
		self::route(
			'/google/select',
			$post,
			static function ( WP_REST_Request $r ) {
				$p = (array) $r->get_json_params();
				Settings::update(
					array(
						'gsc_site'     => sanitize_text_field( (string) ( $p['gsc_site'] ?? '' ) ),
						'ga4_property' => preg_replace( '/\D/', '', (string) ( $p['ga4_property'] ?? '' ) ),
					)
				);
				delete_transient( 'aeo_gsc_report' );
				delete_transient( 'aeo_ga4_report' );
				return self::respond( Google_Service::status() );
			},
			$manage
		);
		self::route(
			'/google/reports',
			$get,
			static function ( WP_REST_Request $r ) {
				$refresh = (bool) $r->get_param( 'refresh' );
				$gsc     = Settings::get( 'gsc_site', '' ) ? Google_Service::gsc_report( $refresh ) : null;
				$ga4     = Settings::get( 'ga4_property', '' ) ? Google_Service::ga4_report( $refresh ) : null;
				return self::respond(
					array(
						'gsc'       => is_wp_error( $gsc ) ? null : $gsc,
						'gsc_error' => is_wp_error( $gsc ) ? $gsc->get_error_message() : '',
						'ga4'       => is_wp_error( $ga4 ) ? null : $ga4,
						'ga4_error' => is_wp_error( $ga4 ) ? $ga4->get_error_message() : '',
					)
				);
			},
			$view
		);
		self::route(
			'/google/disconnect',
			$post,
			static function () {
				Google_Service::disconnect();
				return self::respond( Google_Service::status() );
			},
			$manage
		);

		// Bing AI Performance import.
		self::route(
			'/bing',
			$get,
			static function () {
				return self::respond( Bing_Import::report() );
			},
			$view
		);
		self::route(
			'/bing/import',
			$post,
			static function ( WP_REST_Request $r ) {
				$p = (array) $r->get_json_params();
				return self::respond( Bing_Import::import( (string) ( $p['csv'] ?? '' ) ) );
			},
			$manage
		);

		// Client report.
		self::route(
			'/report-url',
			$get,
			static function () {
				return self::respond( array( 'url' => Report_Service::url() ) );
			},
			$view
		);
	}
}
