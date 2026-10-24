<?php
/**
 * REST routes for content tools: score preview, freshness, FAQ queue, meta and alt text.
 *
 * @package AEO
 */

namespace AEO\Api;

use AEO\Content\Faq_Queue;
use AEO\Content\Freshness;
use AEO\Content\Meta_Service;
use AEO\Services\Score_Engine;
use WP_REST_Request;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Content routes.
 */
class Rest_Content {

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
	 * Can edit content.
	 *
	 * @return bool
	 */
	public static function can_edit() {
		return current_user_can( 'edit_posts' ) && current_user_can( 'aeo_edit_schema' );
	}

	/**
	 * Can use AI.
	 *
	 * @return bool
	 */
	public static function can_ai() {
		return current_user_can( 'edit_posts' ) && current_user_can( 'aeo_use_ai' );
	}

	/**
	 * JSON params.
	 *
	 * @param WP_REST_Request $r Request.
	 * @return array<string, mixed>
	 */
	private static function p( WP_REST_Request $r ) {
		$p = $r->get_json_params();
		return is_array( $p ) ? $p : array();
	}

	/**
	 * Routes.
	 *
	 * @return void
	 */
	public static function routes() {
		$edit = array( __CLASS__, 'can_edit' );
		$ai   = array( __CLASS__, 'can_ai' );
		$get  = WP_REST_Server::READABLE;
		$post = WP_REST_Server::CREATABLE;
		$r    = static function ( $route, $methods, $cb, $perm ) {
			register_rest_route( self::NS, $route, array( 'methods' => $methods, 'callback' => $cb, 'permission_callback' => $perm ) );
		};

		$r(
			'/score-preview',
			$post,
			static function ( WP_REST_Request $req ) {
				$p  = self::p( $req );
				$id = (int) ( $p['post_id'] ?? 0 );
				if ( $id && ! current_user_can( 'edit_post', $id ) ) {
					return Rest_Extra::respond( new \WP_Error( 'aeo_forbidden', 'Forbidden' ), 403 );
				}
				return Rest_Extra::respond( ( new Score_Engine() )->preview( $id, (string) ( $p['title'] ?? '' ), (string) ( $p['content'] ?? '' ) ) );
			},
			static function () {
				return current_user_can( 'edit_posts' );
			}
		);

		register_rest_route(
			self::NS,
			'/score-details/(?P<id>\d+)',
			array(
				'methods'             => $get,
				'callback'            => static function ( WP_REST_Request $req ) {
					$id = (int) $req['id'];
					if ( ! current_user_can( 'edit_post', $id ) ) {
						return Rest_Extra::respond( new \WP_Error( 'aeo_forbidden', 'Forbidden' ), 403 );
					}
					return Rest_Extra::respond( ( new Score_Engine() )->calculate( $id ) );
				},
				'permission_callback' => $edit,
			)
		);

		// Freshness.
		$r(
			'/freshness',
			$get,
			static function ( WP_REST_Request $req ) {
				$months = max( 1, min( 60, (int) ( $req->get_param( 'months' ) ? $req->get_param( 'months' ) : 12 ) ) );
				return Rest_Extra::respond( Freshness::scan( $months ) );
			},
			$edit
		);
		$r(
			'/freshness/review',
			$post,
			static function ( WP_REST_Request $req ) {
				return Rest_Extra::respond( Freshness::mark_reviewed( (int) ( self::p( $req )['post_id'] ?? 0 ) ) );
			},
			$edit
		);

		// Bulk FAQ queue.
		$r(
			'/faq-queue',
			$get,
			static function () {
				return Rest_Extra::respond(
					array(
						'pending'  => Faq_Queue::pending(),
						'progress' => get_option( Faq_Queue::STATE, null ),
					)
				);
			},
			$edit
		);
		$r(
			'/faq-queue/start',
			$post,
			static function ( WP_REST_Request $req ) {
				$p   = self::p( $req );
				$ids = array_map( 'intval', (array) ( $p['post_ids'] ?? array() ) );
				if ( ! empty( $p['without_faqs'] ) ) {
					foreach ( get_posts( array( 'post_type' => \AEO\Core\Settings::get_post_types(), 'post_status' => 'publish', 'posts_per_page' => 50, 'fields' => 'ids' ) ) as $id ) {
						if ( ! ( new \AEO\Services\Schema_Service() )->build_faq_node( $id ) && ! get_post_meta( $id, Faq_Queue::META, true ) ) {
							$ids[] = $id;
						}
					}
				}
				Faq_Queue::start( $ids );
				return Rest_Extra::respond( Faq_Queue::process( 15 ) );
			},
			$ai
		);
		$r(
			'/faq-queue/continue',
			$post,
			static function () {
				return Rest_Extra::respond( Faq_Queue::process( 20 ) );
			},
			$ai
		);
		$r(
			'/faq-queue/approve',
			$post,
			static function ( WP_REST_Request $req ) {
				$p  = self::p( $req );
				$id = (int) ( $p['post_id'] ?? 0 );
				if ( ! current_user_can( 'edit_post', $id ) ) {
					return Rest_Extra::respond( new \WP_Error( 'aeo_forbidden', 'Forbidden' ), 403 );
				}
				return Rest_Extra::respond( array( 'total' => Faq_Queue::approve( $id, (array) ( $p['items'] ?? array() ) ) ) );
			},
			$edit
		);
		$r(
			'/faq-queue/reject',
			$post,
			static function ( WP_REST_Request $req ) {
				$id = (int) ( self::p( $req )['post_id'] ?? 0 );
				if ( ! current_user_can( 'edit_post', $id ) ) {
					return Rest_Extra::respond( new \WP_Error( 'aeo_forbidden', 'Forbidden' ), 403 );
				}
				Faq_Queue::reject( $id );
				return Rest_Extra::respond( array( 'ok' => true ) );
			},
			$edit
		);

		// Meta titles and descriptions.
		$r(
			'/meta',
			$get,
			static function ( WP_REST_Request $req ) {
				return Rest_Extra::respond( Meta_Service::list_posts( 'missing' === $req->get_param( 'filter' ) ? 'missing' : 'all' ) );
			},
			$edit
		);
		$r(
			'/meta/suggest',
			$post,
			static function ( WP_REST_Request $req ) {
				$id = (int) ( self::p( $req )['post_id'] ?? 0 );
				if ( ! current_user_can( 'edit_post', $id ) ) {
					return Rest_Extra::respond( new \WP_Error( 'aeo_forbidden', 'Forbidden' ), 403 );
				}
				return Rest_Extra::respond( Meta_Service::suggest( $id ) );
			},
			$ai
		);
		$r(
			'/meta/save',
			$post,
			static function ( WP_REST_Request $req ) {
				$p  = self::p( $req );
				$id = (int) ( $p['post_id'] ?? 0 );
				if ( ! current_user_can( 'edit_post', $id ) ) {
					return Rest_Extra::respond( new \WP_Error( 'aeo_forbidden', 'Forbidden' ), 403 );
				}
				Meta_Service::save( $id, (string) ( $p['title'] ?? '' ), (string) ( $p['description'] ?? '' ) );
				return Rest_Extra::respond( array( 'ok' => true ) );
			},
			$edit
		);

		// Alt text.
		$r(
			'/alt',
			$get,
			static function () {
				return Rest_Extra::respond( Meta_Service::images_missing_alt() );
			},
			$edit
		);
		$r(
			'/alt/suggest',
			$post,
			static function ( WP_REST_Request $req ) {
				$id = (int) ( self::p( $req )['id'] ?? 0 );
				if ( ! current_user_can( 'edit_post', $id ) ) {
					return Rest_Extra::respond( new \WP_Error( 'aeo_forbidden', 'Forbidden' ), 403 );
				}
				return Rest_Extra::respond( Meta_Service::suggest_alt( $id ) );
			},
			$ai
		);
		$r(
			'/alt/save',
			$post,
			static function ( WP_REST_Request $req ) {
				$p  = self::p( $req );
				$id = (int) ( $p['id'] ?? 0 );
				if ( ! current_user_can( 'edit_post', $id ) ) {
					return Rest_Extra::respond( new \WP_Error( 'aeo_forbidden', 'Forbidden' ), 403 );
				}
				Meta_Service::save_alt( $id, (string) ( $p['alt'] ?? '' ) );
				return Rest_Extra::respond( array( 'ok' => true ) );
			},
			$edit
		);
	
		// Schema templates (display rules + field mapping).
		$r(
			'/schema-templates',
			$get,
			static function () {
				return Rest_Extra::respond(
					array(
						'templates'  => \AEO\Services\Schema_Templates::all(),
						'post_types' => \AEO\Core\Settings::get_post_types(),
						'taxonomies' => array_values(
							array_map(
								static function ( $t ) {
									return array(
										'name'  => $t->name,
										'label' => $t->labels->singular_name,
									);
								},
								get_taxonomies( array( 'public' => true ), 'objects' )
							)
						),
						'acf'        => function_exists( 'get_field' ),
						'metabox'    => function_exists( 'rwmb_meta' ),
					)
				);
			},
			$edit
		);
		$r(
			'/schema-templates',
			$post,
			static function ( WP_REST_Request $req ) {
				$result = \AEO\Services\Schema_Templates::save_all( (array) ( self::p( $req )['templates'] ?? array() ) );
				\AEO\Api\Public_Api::flush_schemamap();
				return Rest_Extra::respond( $result );
			},
			static function () {
				return current_user_can( 'aeo_edit_schema' ) && current_user_can( 'aeo_manage_settings' );
			}
		);
		$r(
			'/schema-templates/preview',
			$post,
			static function ( WP_REST_Request $req ) {
				$p  = self::p( $req );
				$id = (int) ( $p['post_id'] ?? 0 );
				if ( ! $id || ! current_user_can( 'edit_post', $id ) ) {
					return Rest_Extra::respond( new \WP_Error( 'aeo_post', __( 'Choose a post to preview.', 'answer-engine-optimization' ) ) );
				}
				$json = is_string( $p['schema'] ?? null ) ? $p['schema'] : wp_json_encode( $p['schema'] ?? array() );
				return Rest_Extra::respond( \AEO\Services\Schema_Templates::preview( $json, $id ) );
			},
			$edit
		);

		// Hotel.
		$r(
			'/hotel',
			$get,
			static function () {
				$rooms = \AEO\Integrations\Hotel::has_rooms() ? get_posts( array( 'post_type' => \AEO\Integrations\Hotel::ROOM_TYPE, 'post_status' => 'publish', 'posts_per_page' => 100 ) ) : array();
				return Rest_Extra::respond(
					array(
						'quotel'        => \AEO\Integrations\Hotel::has_rooms(),
						'hotel'         => (bool) \AEO\Integrations\Hotel::hotel_location(),
						'faq_template'  => \AEO\Integrations\Hotel::faq_template(),
						'rating'        => \AEO\Integrations\Hotel::aggregate_rating(),
						'rooms'         => array_map(
							static function ( $room ) {
								$node = \AEO\Integrations\Hotel::room_node( $room->ID );
								return array(
									'id'    => $room->ID,
									'title' => wp_strip_all_tags( get_the_title( $room ) ),
									'url'   => get_permalink( $room ),
									'type'  => $node['@type'] ?? 'HotelRoom',
									'price' => (float) get_post_meta( $room->ID, '_qhb_base_price', true ),
									'props' => array_values( array_diff( array_keys( $node ), array( '@type', '@id', 'name', 'url', 'containedInPlace' ) ) ),
								);
							},
							$rooms
						),
						'currency'      => (string) get_option( 'qhb_currency', '' ),
					)
				);
			},
			$edit
		);
	}
}
