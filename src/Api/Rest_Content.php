<?php
/**
 * REST routes for content tools: score preview, freshness, FAQ storage, meta and alt text.
 * (Bulk FAQ queue, AI meta/alt and schema templates are in the Pro add-on.)
 *
 * @package AEO
 */

namespace AEO\Api;

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
	

		// Hotel.
		// Add FAQ items to a page (hotel FAQ starter; Pro's review queue uses the same store).
		$r(
			'/faqs/add',
			$post,
			static function ( WP_REST_Request $req ) {
				$p  = self::p( $req );
				$id = (int) ( $p['post_id'] ?? 0 );
				if ( ! $id || ! current_user_can( 'edit_post', $id ) ) {
					return Rest_Extra::respond( new \WP_Error( 'aeo_forbidden', 'Forbidden' ), 403 );
				}
				$items = array();
				foreach ( (array) ( $p['items'] ?? array() ) as $item ) {
					if ( is_array( $item ) ) {
						$items[] = array(
							'question' => sanitize_text_field( (string) ( $item['question'] ?? '' ) ),
							'answer'   => wp_kses_post( (string) ( $item['answer'] ?? '' ) ),
						);
					}
				}
				return Rest_Extra::respond( array( 'total' => \AEO\Content\Faq_Store::add( $id, $items ) ) );
			},
			$edit
		);
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
