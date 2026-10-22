<?php
/**
 * REST API controller.
 *
 * @package AEO
 */

namespace AEO\Api;

use AEO\Core\Settings;
use AEO\Services\AI\AI_Manager;
use AEO\Services\Analytics_Service;
use AEO\Services\Audit_Service;
use AEO\Services\Brief_Service;
use AEO\Services\Citation_Service;
use AEO\Services\Encryption;
use AEO\Services\Schema_Service;
use AEO\Services\Score_Engine;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Registers all REST API endpoints.
 */
class Rest_Api {

	/**
	 * API namespace.
	 *
	 * @var string
	 */
	const NAMESPACE = 'aeo-genius/v1';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register all routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		// Dashboard.
		register_rest_route(
			self::NAMESPACE,
			'/dashboard',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_dashboard' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);

		// Settings.
		register_rest_route(
			self::NAMESPACE,
			'/settings',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_settings' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_settings' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
			)
		);

		// Schema.
		register_rest_route(
			self::NAMESPACE,
			'/schema/(?P<post_id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_schema' ),
					'permission_callback' => '__return_true',
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'save_schema' ),
					'permission_callback' => array( $this, 'can_edit_posts' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/schema/types',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_schema_types' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/schema/global',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_global_schema' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'save_global_schema' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
			)
		);

		// AEO Score.
		register_rest_route(
			self::NAMESPACE,
			'/aeo-score/(?P<post_id>\d+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_aeo_score' ),
				'permission_callback' => array( $this, 'can_edit_posts' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/content/posts',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_content_posts' ),
				'permission_callback' => array( $this, 'can_edit_posts' ),
			)
		);

		// FAQ.
		register_rest_route(
			self::NAMESPACE,
			'/generate-faq',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'generate_faq' ),
				'permission_callback' => array( $this, 'can_edit_posts' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/faqs',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_all_faqs' ),
				'permission_callback' => array( $this, 'can_edit_posts' ),
			)
		);

		// Content rewrite.
		register_rest_route(
			self::NAMESPACE,
			'/rewrite-content',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'rewrite_content' ),
				'permission_callback' => array( $this, 'can_edit_posts' ),
			)
		);

		// Audit.
		register_rest_route(
			self::NAMESPACE,
			'/audit/site',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_audit' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'run_audit' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/audit/fix/(?P<issue_id>[a-zA-Z0-9_]+)',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'fix_audit_issue' ),
				'permission_callback' => array( $this, 'can_admin' ),
			)
		);

		// Analytics.
		register_rest_route(
			self::NAMESPACE,
			'/analytics/citations',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_citations' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'scan_citations' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/analytics/overview',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_analytics_overview' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/analytics/competitors',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_competitor_analysis' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);

		// Briefs.
		register_rest_route(
			self::NAMESPACE,
			'/briefs/opportunities',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_brief_opportunities' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/briefs/generate',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'generate_brief' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);

		// Bulk schema.
		register_rest_route(
			self::NAMESPACE,
			'/bulk/schema',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'bulk_schema' ),
				'permission_callback' => array( $this, 'can_admin' ),
			)
		);

		// Local business.
		register_rest_route(
			self::NAMESPACE,
			'/local/locations',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_locations' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'save_locations' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
			)
		);

		// Onboarding.
		register_rest_route(
			self::NAMESPACE,
			'/onboarding/complete',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'complete_onboarding' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);
	}

	/**
	 * Permission: manage_options.
	 *
	 * @return bool
	 */
	public function can_manage() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Permission: edit_posts.
	 *
	 * @return bool
	 */
	public function can_edit_posts() {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Permission: administrator.
	 *
	 * @return bool
	 */
	public function can_admin() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * GET /dashboard
	 *
	 * @return WP_REST_Response
	 */
	public function get_dashboard() {
		$analytics = ( new Analytics_Service() )->get_overview();
		$audit     = ( new Audit_Service() )->get_results();

		return new WP_REST_Response(
			array(
				'analytics' => $analytics,
				'audit'     => $audit,
				'settings'  => Settings::get_public(),
			)
		);
	}

	/**
	 * GET/POST /settings
	 *
	 * @return WP_REST_Response
	 */
	public function get_settings() {
		return new WP_REST_Response( Settings::get_public() );
	}

	/**
	 * Update settings.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function update_settings( WP_REST_Request $request ) {
		$data = $request->get_json_params();
		if ( ! is_array( $data ) ) {
			return new WP_REST_Response( array( 'error' => 'Invalid data' ), 400 );
		}

		$sanitized = Settings::sanitize( $data );

		// API keys: a non-empty value replaces the key, `clear_<key>: true` removes it,
		// anything else leaves the stored (encrypted) key untouched.
		foreach ( Settings::API_KEYS as $key_field ) {
			if ( ! empty( $data[ 'clear_' . $key_field ] ) ) {
				$sanitized[ $key_field ] = '';
			} elseif ( isset( $data[ $key_field ] ) && is_string( $data[ $key_field ] ) && '' !== trim( $data[ $key_field ] ) ) {
				$sanitized[ $key_field ] = Encryption::encrypt( trim( sanitize_text_field( $data[ $key_field ] ) ) );
			}
		}

		Settings::update( $sanitized );
		delete_transient( 'aeo_llms_txt' );

		return new WP_REST_Response( Settings::get_public() );
	}

	/**
	 * GET /schema/{post_id}
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function get_schema( WP_REST_Request $request ) {
		$post_id = (int) $request['post_id'];
		$post    = get_post( $post_id );

		if ( ! $post || 'publish' !== $post->post_status && ! current_user_can( 'edit_post', $post_id ) ) {
			return new WP_REST_Response( array( 'error' => 'Not found' ), 404 );
		}

		$service = new Schema_Service();
		return new WP_REST_Response( $service->get_post_schema( $post_id ) );
	}

	/**
	 * POST /schema/{post_id}
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function save_schema( WP_REST_Request $request ) {
		$post_id = (int) $request['post_id'];
		if ( ! get_post( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return new WP_REST_Response( array( 'message' => __( 'You cannot edit this post.', 'answer-engine-optimization' ) ), 403 );
		}

		$schema  = $request->get_json_params();
		$service = new Schema_Service();

		// An empty object resets the post to auto-generated schema.
		if ( empty( $schema ) ) {
			$service->delete_post_schema( $post_id );
			return new WP_REST_Response( array( 'success' => true, 'reset' => true ) );
		}

		$validation = $service->validate( is_array( $schema ) ? $schema : array() );
		if ( ! $validation['valid'] ) {
			return new WP_REST_Response(
				array(
					'message'  => implode( ' ', $validation['errors'] ),
					'errors'   => $validation['errors'],
					'warnings' => $validation['warnings'],
				),
				400
			);
		}

		$service->save_post_schema( $post_id, $schema );
		( new Score_Engine() )->calculate( $post_id );

		return new WP_REST_Response(
			array(
				'success'  => true,
				'warnings' => $validation['warnings'],
			)
		);
	}

	/**
	 * GET /schema/types
	 *
	 * @return WP_REST_Response
	 */
	public function get_schema_types() {
		return new WP_REST_Response( Schema_Service::$types );
	}

	/**
	 * GET/POST global schema.
	 *
	 * @return WP_REST_Response
	 */
	public function get_global_schema() {
		$service = new Schema_Service();
		return new WP_REST_Response( $service->get_global_schema() );
	}

	/**
	 * Save global schema.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function save_global_schema( WP_REST_Request $request ) {
		$data  = $request->get_json_params();
		$clean = array();
		foreach ( array( 'website', 'organization' ) as $key ) {
			if ( isset( $data[ $key ] ) && is_array( $data[ $key ] ) ) {
				$clean[ $key ] = self::sanitize_schema_value( $data[ $key ] );
			}
		}
		update_option( 'aeo_optimizer_schema_global', $clean );
		return new WP_REST_Response( array( 'success' => true ) );
	}

	/**
	 * GET /aeo-score/{post_id}
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function get_aeo_score( WP_REST_Request $request ) {
		$post_id = (int) $request['post_id'];
		if ( ! get_post( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return new WP_REST_Response( array( 'message' => __( 'Not found', 'answer-engine-optimization' ) ), 404 );
		}
		$engine = new Score_Engine();
		return new WP_REST_Response( $engine->calculate( $post_id ) );
	}

	/**
	 * GET /content/posts
	 *
	 * @return WP_REST_Response
	 */
	public function get_content_posts() {
		$posts = get_posts(
			array(
				'post_type'      => Settings::get_post_types(),
				'post_status'    => 'publish',
				'posts_per_page' => 100,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);

		$engine = new Score_Engine();
		$data   = array();

		foreach ( $posts as $post ) {
			$score = get_post_meta( $post->ID, '_aeo_score', true );
			if ( '' === $score ) {
				$result = $engine->calculate( $post->ID );
				$score  = $result['score'];
			}
			$data[] = array(
				'id'    => $post->ID,
				'title' => $post->post_title,
				'type'  => get_post_type_object( $post->post_type ) ? get_post_type_object( $post->post_type )->labels->singular_name : $post->post_type,
				'score' => (int) $score,
				'url'   => get_permalink( $post->ID ),
			);
		}

		return new WP_REST_Response( $data );
	}

	/**
	 * POST /generate-faq
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function generate_faq( WP_REST_Request $request ) {
		$params  = $request->get_json_params();
		$post_id = (int) ( $params['post_id'] ?? 0 );
		$count   = (int) ( $params['count'] ?? 5 );

		$count   = max( 1, min( 15, $count ) );

		if ( $post_id ) {
			$post = get_post( $post_id );
			if ( ! $post || ! current_user_can( 'edit_post', $post_id ) ) {
				return new WP_REST_Response( array( 'message' => __( 'You cannot edit this post.', 'answer-engine-optimization' ) ), 403 );
			}
			$content = $post->post_content;
		} else {
			$content = wp_kses_post( (string) ( $params['content'] ?? '' ) );
		}

		if ( '' === trim( wp_strip_all_tags( $content ) ) ) {
			return new WP_REST_Response( array( 'message' => __( 'There is no content to generate FAQs from.', 'answer-engine-optimization' ) ), 400 );
		}

		$ai   = ( new AI_Manager() )->get_provider();
		$faqs = \AEO\Services\AI\AI_Response::normalize_faqs( $ai->generate_faqs( $content, $count ) );

		if ( $post_id && ! empty( $params['save'] ) && ! empty( $faqs ) ) {
			update_post_meta( $post_id, '_aeo_faq_items', wp_slash( $faqs ) );
		}

		return new WP_REST_Response( array( 'faqs' => $faqs ) );
	}

	/**
	 * GET /faqs
	 *
	 * @return WP_REST_Response
	 */
	public function get_all_faqs() {
		global $wpdb;

		$results = $wpdb->get_results(
			"SELECT pm.post_id, pm.meta_value FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE pm.meta_key = '_aeo_faq_items' AND p.post_status IN ('publish','draft','pending','future','private') AND p.post_type <> 'revision'",
			ARRAY_A
		);

		$library = array();
		foreach ( $results as $row ) {
			$faqs = maybe_unserialize( $row['meta_value'] );
			if ( ! is_array( $faqs ) ) {
				continue;
			}
			foreach ( $faqs as $faq ) {
				$library[] = array(
					'post_id'  => (int) $row['post_id'],
					'post_title' => get_the_title( $row['post_id'] ),
					'question' => $faq['question'] ?? '',
					'answer'   => $faq['answer'] ?? '',
				);
			}
		}

		return new WP_REST_Response( $library );
	}

	/**
	 * POST /rewrite-content
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function rewrite_content( WP_REST_Request $request ) {
		$params  = $request->get_json_params();
		$post_id = (int) ( $params['post_id'] ?? 0 );
		$post    = get_post( $post_id );

		if ( ! $post || ! current_user_can( 'edit_post', $post_id ) ) {
			return new WP_REST_Response( array( 'message' => __( 'Post not found', 'answer-engine-optimization' ) ), 404 );
		}

		$original = $post->post_content;
		update_post_meta( $post_id, '_aeo_ai_rewrite_original', $original );

		$ai       = ( new AI_Manager() )->get_provider();
		$rewritten = $ai->rewrite_content( $original );

		return new WP_REST_Response(
			array(
				'original'  => $original,
				'rewritten' => $rewritten,
			)
		);
	}

	/**
	 * GET/POST /audit/site
	 *
	 * @return WP_REST_Response
	 */
	public function get_audit() {
		$service = new Audit_Service();
		return new WP_REST_Response( $service->get_results() );
	}

	/**
	 * Run site audit.
	 *
	 * @return WP_REST_Response
	 */
	public function run_audit() {
		$service = new Audit_Service();
		return new WP_REST_Response( $service->run_site_audit() );
	}

	/**
	 * POST /audit/fix/{issue_id}
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function fix_audit_issue( WP_REST_Request $request ) {
		$service = new Audit_Service();
		return new WP_REST_Response( $service->auto_fix( $request['issue_id'] ) );
	}

	/**
	 * GET/POST citations.
	 *
	 * @return WP_REST_Response
	 */
	public function get_citations() {
		$service = new Citation_Service();
		return new WP_REST_Response( $service->get_analytics() );
	}

	/**
	 * Scan citations.
	 *
	 * @return WP_REST_Response
	 */
	public function scan_citations() {
		$service = new Citation_Service();
		$result  = $service->scan_citations();
		if ( is_wp_error( $result ) ) {
			return new WP_REST_Response( array( 'message' => $result->get_error_message(), 'code' => $result->get_error_code() ), 400 );
		}
		return new WP_REST_Response( $result );
	}

	/**
	 * GET analytics overview.
	 *
	 * @return WP_REST_Response
	 */
	public function get_analytics_overview() {
		$service = new Analytics_Service();
		return new WP_REST_Response( $service->get_overview() );
	}

	/**
	 * GET competitor analysis.
	 *
	 * @return WP_REST_Response
	 */
	public function get_competitor_analysis( WP_REST_Request $request ) {
		$service = new Citation_Service();
		return new WP_REST_Response( $service->competitor_analysis( (bool) $request->get_param( 'refresh' ) ) );
	}

	/**
	 * GET brief opportunities.
	 *
	 * @return WP_REST_Response
	 */
	public function get_brief_opportunities() {
		return new WP_REST_Response( Brief_Service::opportunities() );
	}

	/**
	 * POST generate brief.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function generate_brief( WP_REST_Request $request ) {
		$params = $request->get_json_params();
		$topic  = sanitize_text_field( $params['topic'] ?? '' );
		if ( '' === trim( $topic ) ) {
			return new WP_REST_Response( array( 'message' => __( 'Enter a topic.', 'answer-engine-optimization' ) ), 400 );
		}
		$ai     = ( new AI_Manager() )->get_provider();
		$brief  = $ai->generate_brief( $topic );
		return new WP_REST_Response( $brief );
	}

	/**
	 * POST bulk schema.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function bulk_schema( WP_REST_Request $request ) {
		$params   = $request->get_json_params();
		$post_ids = array_map( 'intval', $params['post_ids'] ?? array() );
		$template = $params['template'] ?? array( '@type' => 'Article', '@context' => 'https://schema.org' );

		$service = new Schema_Service();
		$count   = $service->bulk_apply( $post_ids, $template );

		return new WP_REST_Response( array( 'updated' => $count ) );
	}

	/**
	 * GET/POST local locations.
	 *
	 * @return WP_REST_Response
	 */
	public function get_locations() {
		$locations = get_option( 'aeo_local_locations', array() );
		return new WP_REST_Response( is_array( $locations ) ? $locations : array() );
	}

	/**
	 * Save locations.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function save_locations( WP_REST_Request $request ) {
		$data      = $request->get_json_params();
		$locations = array();
		$text      = array( 'name', 'type', 'address', 'city', 'region', 'postal_code', 'country', 'phone', 'price_range' );

		foreach ( is_array( $data ) ? $data : array() as $raw ) {
			if ( ! is_array( $raw ) ) {
				continue;
			}
			$loc = array();
			foreach ( $text as $field ) {
				$loc[ $field ] = sanitize_text_field( (string) ( $raw[ $field ] ?? '' ) );
			}
			if ( ! in_array( $loc['type'], Schema_Service::$types, true ) ) {
				$loc['type'] = 'LocalBusiness';
			}
			$loc['hours'] = sanitize_textarea_field( (string) ( $raw['hours'] ?? '' ) );
			$loc['url']   = esc_url_raw( (string) ( $raw['url'] ?? '' ) );
			$loc['lat']   = is_numeric( $raw['lat'] ?? '' ) && abs( (float) $raw['lat'] ) <= 90 ? (string) (float) $raw['lat'] : '';
			$loc['lng']   = is_numeric( $raw['lng'] ?? '' ) && abs( (float) $raw['lng'] ) <= 180 ? (string) (float) $raw['lng'] : '';
			if ( '' !== $loc['name'] ) {
				$locations[] = $loc;
			}
		}

		update_option( 'aeo_local_locations', array_slice( $locations, 0, 50 ) );
		return new WP_REST_Response( array( 'success' => true, 'locations' => $locations ) );
	}

	/**
	 * POST complete onboarding.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function complete_onboarding( WP_REST_Request $request ) {
		$data = $request->get_json_params();
		Settings::update(
			array_merge(
				Settings::sanitize( is_array( $data ) ? $data : array() ),
				array( 'onboarding_complete' => true )
			)
		);

		// Run initial audit.
		( new Audit_Service() )->run_site_audit();

		return new WP_REST_Response( array( 'success' => true ) );
	}

	/**
	 * Recursively sanitize schema values supplied by an admin.
	 *
	 * @param mixed $value Value.
	 * @return mixed
	 */
	private static function sanitize_schema_value( $value ) {
		if ( is_array( $value ) ) {
			$out = array();
			foreach ( $value as $k => $v ) {
				$out[ is_int( $k ) ? $k : sanitize_text_field( (string) $k ) ] = self::sanitize_schema_value( $v );
			}
			return $out;
		}
		if ( is_bool( $value ) || is_int( $value ) || is_float( $value ) || null === $value ) {
			return $value;
		}
		return wp_kses( (string) $value, array() );
	}
}
