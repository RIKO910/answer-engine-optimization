<?php
/**
 * REST routes for Tools: import/export and FAQ migration. (MCP is in the Pro add-on.)
 *
 * @package AEO
 */

namespace AEO\Api;

use AEO\Services\Import_Export;
use WP_REST_Request;

defined( 'ABSPATH' ) || exit;

/**
 * Tools routes.
 */
class Rest_Tools {

	/**
	 * Register.
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
		$ns     = 'aeo-genius/v1';
		$manage = array( Rest_Extra::class, 'can_manage' );
		$schema = static function () {
			return current_user_can( 'edit_posts' ) && current_user_can( 'aeo_edit_schema' );
		};

		register_rest_route(
			$ns,
			'/tools/export',
			array(
				'methods'             => 'GET',
				'callback'            => static function ( WP_REST_Request $r ) {
					$sections = array_filter( array_map( 'sanitize_key', explode( ',', (string) $r->get_param( 'sections' ) ) ) );
					return Rest_Extra::respond( Import_Export::export( $sections ? $sections : Import_Export::SECTIONS ) );
				},
				'permission_callback' => $manage,
			)
		);

		register_rest_route(
			$ns,
			'/tools/import',
			array(
				'methods'             => 'POST',
				'callback'            => static function ( WP_REST_Request $r ) {
					$p    = (array) $r->get_json_params();
					$data = $p['data'] ?? null;
					if ( is_string( $data ) ) {
						$data = json_decode( $data, true );
					}
					if ( ! is_array( $data ) ) {
						return Rest_Extra::respond( new \WP_Error( 'aeo_import_json', __( 'The file is not valid JSON.', 'answer-engine-optimization' ) ) );
					}
					$sections = array_values( array_intersect( Import_Export::SECTIONS, array_map( 'sanitize_key', (array) ( $p['sections'] ?? Import_Export::SECTIONS ) ) ) );
					return Rest_Extra::respond( Import_Export::import( $data, $sections, ! empty( $p['dry_run'] ) ) );
				},
				'permission_callback' => $manage,
			)
		);

		register_rest_route(
			$ns,
			'/tools/faq-migration',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => static function () {
						return Rest_Extra::respond( array( 'posts' => Import_Export::third_party_faq_candidates() ) );
					},
					'permission_callback' => $schema,
				),
				array(
					'methods'             => 'POST',
					'callback'            => static function ( WP_REST_Request $r ) {
						$p = (array) $r->get_json_params();
						return Rest_Extra::respond( Import_Export::import_third_party_faqs( array_map( 'intval', (array) ( $p['ids'] ?? array() ) ) ) );
					},
					'permission_callback' => $schema,
				),
			)
		);
	}
}
