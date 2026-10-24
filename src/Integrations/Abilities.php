<?php
/**
 * Plugin abilities: registered with the WordPress Abilities API (WP 6.9+) and served
 * to AI assistants through this plugin's MCP endpoint.
 *
 * @package AEO
 */

namespace AEO\Integrations;

use AEO\Core\Settings;
use AEO\Frontend\Ai_Crawlers;
use AEO\Frontend\Llms_Txt;
use AEO\Services\Ai_Visibility;
use AEO\Services\Analytics_Service;
use AEO\Services\Audit_Service;
use AEO\Services\Prompt_Library;
use AEO\Services\Schema_Service;
use AEO\Services\Score_Engine;
use AEO\Services\Traffic_Log;
use AEO\Services\AI\AI_Response;

defined( 'ABSPATH' ) || exit;

/**
 * Ability definitions.
 */
class Abilities {

	/**
	 * Register with the core Abilities API when available.
	 *
	 * @return void
	 */
	public static function register() {
		add_action( 'wp_abilities_api_categories_init', array( __CLASS__, 'register_category' ) );
		add_action( 'wp_abilities_api_init', array( __CLASS__, 'register_abilities' ) );
	}

	/**
	 * Category.
	 *
	 * @return void
	 */
	public static function register_category() {
		if ( function_exists( 'wp_register_ability_category' ) ) {
			wp_register_ability_category(
				'aeo',
				array(
					'label'       => __( 'Answer Engine Optimization', 'answer-engine-optimization' ),
					'description' => __( 'Check and improve how AI answer engines understand and cite this site.', 'answer-engine-optimization' ),
				)
			);
		}
	}

	/**
	 * Abilities.
	 *
	 * @return void
	 */
	public static function register_abilities() {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}
		foreach ( self::definitions() as $name => $def ) {
			wp_register_ability(
				'aeo/' . $name,
				array(
					'label'               => $def['label'],
					'description'         => $def['description'],
					'category'            => 'aeo',
					'input_schema'        => $def['input'],
					'execute_callback'    => static function ( $input = null ) use ( $def ) {
						return call_user_func( $def['run'], is_array( $input ) ? $input : array() );
					},
					'permission_callback' => static function ( $input = null ) use ( $def ) {
						return self::allowed( $def, is_array( $input ) ? $input : array() );
					},
					'meta'                => array(
						'annotations'  => array(
							'readonly'    => $def['readonly'],
							'destructive' => false,
							'idempotent'  => $def['readonly'],
						),
						'show_in_rest' => true,
					),
				)
			);
		}
	}

	/**
	 * Permission check for a definition.
	 *
	 * @param array<string, mixed> $def   Definition.
	 * @param array<string, mixed> $input Input.
	 * @return bool
	 */
	public static function allowed( array $def, array $input ) {
		if ( ! current_user_can( $def['cap'] ) ) {
			return false;
		}
		if ( ! empty( $input['post_id'] ) && ! $def['readonly'] && ! current_user_can( 'edit_post', (int) $input['post_id'] ) ) {
			return false;
		}
		if ( ! empty( $input['post_id'] ) && $def['readonly'] && ! current_user_can( 'read_post', (int) $input['post_id'] ) ) {
			return false;
		}
		return true;
	}

	/**
	 * Post ID schema.
	 *
	 * @return array<string, mixed>
	 */
	private static function post_id() {
		return array(
			'type'        => 'integer',
			'description' => 'WordPress post ID.',
			'minimum'     => 1,
		);
	}

	/**
	 * All definitions.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function definitions() {
		$empty = array(
			'type'                 => 'object',
			'properties'           => new \stdClass(),
			'additionalProperties' => false,
		);

		return array(
			'get-site-overview'   => array(
				'label'       => __( 'Get site AEO overview', 'answer-engine-optimization' ),
				'description' => 'Average AEO score, schema and FAQ coverage, open audit issues, and the latest AI citation results for this site.',
				'input'       => $empty,
				'readonly'    => true,
				'cap'         => 'aeo_view_reports',
				'run'         => static function () {
					$v = ( new Ai_Visibility() )->scan_summary();
					return array(
						'analytics'     => ( new Analytics_Service() )->get_overview(),
						'audit_summary' => ( new Audit_Service() )->get_results()['summary'] ?? array(),
						'ai_citations'  => $v ? array_intersect_key( $v, array_flip( array( 'scanned_at', 'checked', 'cited', 'mentioned', 'engines', 'share_of_voice', 'sentiment' ) ) ) : null,
					);
				},
			),
			'list-posts'          => array(
				'label'       => __( 'List posts by AEO score', 'answer-engine-optimization' ),
				'description' => 'Published posts and pages with their AEO score (0-100). Use order "worst" to find pages to improve first.',
				'input'       => array(
					'type'       => 'object',
					'properties' => array(
						'order' => array( 'type' => 'string', 'enum' => array( 'worst', 'best' ), 'default' => 'worst' ),
						'limit' => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 50, 'default' => 10 ),
					),
				),
				'readonly'    => true,
				'cap'         => 'aeo_view_reports',
				'run'         => static function ( $in ) {
					$a     = new Analytics_Service();
					$limit = max( 1, min( 50, (int) ( $in['limit'] ?? 10 ) ) );
					return 'best' === ( $in['order'] ?? 'worst' ) ? $a->get_top_pages( $limit ) : $a->get_worst_pages( $limit );
				},
			),
			'get-post-score'      => array(
				'label'       => __( 'Get a post\'s AEO score and fixes', 'answer-engine-optimization' ),
				'description' => 'Recalculates one post\'s AEO score and returns every check with concrete recommendations, biggest gain first.',
				'input'       => array(
					'type'       => 'object',
					'properties' => array( 'post_id' => self::post_id() ),
					'required'   => array( 'post_id' ),
				),
				'readonly'    => true,
				'cap'         => 'aeo_view_reports',
				'run'         => static function ( $in ) {
					return ( new Score_Engine() )->calculate( (int) $in['post_id'] );
				},
			),
			'get-post-schema'     => array(
				'label'       => __( 'Get a post\'s structured data', 'answer-engine-optimization' ),
				'description' => 'The JSON-LD nodes this plugin outputs for a post (Article/WebPage, FAQPage, HowTo, HotelRoom, etc.).',
				'input'       => array(
					'type'       => 'object',
					'properties' => array( 'post_id' => self::post_id() ),
					'required'   => array( 'post_id' ),
				),
				'readonly'    => true,
				'cap'         => 'aeo_view_reports',
				'run'         => static function ( $in ) {
					return ( new Schema_Service() )->get_post_nodes( (int) $in['post_id'], false );
				},
			),
			'get-faqs'            => array(
				'label'       => __( 'Get a post\'s FAQs', 'answer-engine-optimization' ),
				'description' => 'FAQ question/answer pairs stored for a post (meta box FAQs, not block FAQs).',
				'input'       => array(
					'type'       => 'object',
					'properties' => array( 'post_id' => self::post_id() ),
					'required'   => array( 'post_id' ),
				),
				'readonly'    => true,
				'cap'         => 'aeo_view_reports',
				'run'         => static function ( $in ) {
					$faqs = get_post_meta( (int) $in['post_id'], '_aeo_faq_items', true );
					return is_array( $faqs ) ? array_values( $faqs ) : array();
				},
			),
			'set-faqs'            => array(
				'label'       => __( 'Replace a post\'s FAQs', 'answer-engine-optimization' ),
				'description' => 'Replaces the FAQ items shown on the page and marked up as FAQPage. Answers should be 1-3 factual sentences taken from the page content.',
				'input'       => array(
					'type'       => 'object',
					'properties' => array(
						'post_id' => self::post_id(),
						'faqs'    => array(
							'type'  => 'array',
							'items' => array(
								'type'       => 'object',
								'properties' => array(
									'question' => array( 'type' => 'string' ),
									'answer'   => array( 'type' => 'string' ),
								),
								'required'   => array( 'question', 'answer' ),
							),
						),
					),
					'required'   => array( 'post_id', 'faqs' ),
				),
				'readonly'    => false,
				'cap'         => 'aeo_edit_schema',
				'run'         => static function ( $in ) {
					$faqs = array_values( array_filter( AI_Response::normalize_faqs( (array) $in['faqs'] ), static function ( $f ) {
						return '' !== $f['answer'];
					} ) );
					update_post_meta( (int) $in['post_id'], '_aeo_faq_items', wp_slash( $faqs ) );
					( new Score_Engine() )->calculate( (int) $in['post_id'] );
					return array( 'saved' => count( $faqs ) );
				},
			),
			'set-direct-answer'   => array(
				'label'       => __( 'Set a post\'s direct answer', 'answer-engine-optimization' ),
				'description' => 'Sets the target question and the 1-2 sentence direct answer shown at the top of the post (also used for Speakable schema).',
				'input'       => array(
					'type'       => 'object',
					'properties' => array(
						'post_id'  => self::post_id(),
						'question' => array( 'type' => 'string' ),
						'answer'   => array( 'type' => 'string', 'maxLength' => 600 ),
					),
					'required'   => array( 'post_id', 'answer' ),
				),
				'readonly'    => false,
				'cap'         => 'aeo_edit_schema',
				'run'         => static function ( $in ) {
					$id = (int) $in['post_id'];
					if ( isset( $in['question'] ) ) {
						update_post_meta( $id, '_aeo_target_question', sanitize_text_field( (string) $in['question'] ) );
					}
					update_post_meta( $id, '_aeo_direct_answer', sanitize_textarea_field( (string) $in['answer'] ) );
					return ( new Score_Engine() )->calculate( $id );
				},
			),
			'run-site-audit'      => array(
				'label'       => __( 'Run the site audit', 'answer-engine-optimization' ),
				'description' => 'Scans published content for AEO issues and returns them grouped by severity.',
				'input'       => $empty,
				'readonly'    => false,
				'cap'         => 'aeo_manage_settings',
				'run'         => static function () {
					$r = ( new Audit_Service() )->run_site_audit();
					return array(
						'summary' => $r['summary'],
						'issues'  => array_slice( $r['issues'], 0, 100 ),
					);
				},
			),
			'get-llms-txt'        => array(
				'label'       => __( 'Get llms.txt', 'answer-engine-optimization' ),
				'description' => 'The current /llms.txt index of this site.',
				'input'       => $empty,
				'readonly'    => true,
				'cap'         => 'aeo_view_reports',
				'run'         => static function () {
					return array( 'llms_txt' => Llms_Txt::build_index() );
				},
			),
			'get-ai-visibility'   => array(
				'label'       => __( 'Get AI citation results', 'answer-engine-optimization' ),
				'description' => 'Latest check of tracked prompts on ChatGPT, Perplexity, Gemini and Claude: citations, brand mentions, share of voice and sentiment.',
				'input'       => $empty,
				'readonly'    => true,
				'cap'         => 'aeo_view_reports',
				'run'         => static function () {
					return ( new Ai_Visibility() )->scan_summary();
				},
			),
			'get-ai-traffic'      => array(
				'label'       => __( 'Get AI crawler and referral traffic', 'answer-engine-optimization' ),
				'description' => 'AI crawler requests and visits referred by AI assistants over the last N days.',
				'input'       => array(
					'type'       => 'object',
					'properties' => array( 'days' => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 90, 'default' => 30 ) ),
				),
				'readonly'    => true,
				'cap'         => 'aeo_view_reports',
				'run'         => static function ( $in ) {
					return Traffic_Log::report( max( 1, min( 90, (int) ( $in['days'] ?? 30 ) ) ) );
				},
			),
			'get-crawler-rules'   => array(
				'label'       => __( 'Get AI crawler rules', 'answer-engine-optimization' ),
				'description' => 'Every known AI crawler with its purpose (training, search, user) and whether it is allowed or blocked in robots.txt.',
				'input'       => $empty,
				'readonly'    => true,
				'cap'         => 'aeo_view_reports',
				'run'         => static function () {
					return array(
						'preset' => Settings::get( 'crawler_preset', 'custom' ),
						'bots'   => Ai_Crawlers::for_admin(),
					);
				},
			),
			'set-crawler-rule'    => array(
				'label'       => __( 'Allow or block an AI crawler', 'answer-engine-optimization' ),
				'description' => 'Changes one AI crawler rule in robots.txt. Switches the preset to "custom".',
				'input'       => array(
					'type'       => 'object',
					'properties' => array(
						'bot'  => array( 'type' => 'string', 'enum' => array_keys( Ai_Crawlers::BOTS ) ),
						'rule' => array( 'type' => 'string', 'enum' => array( 'allow', 'block' ) ),
					),
					'required'   => array( 'bot', 'rule' ),
				),
				'readonly'    => false,
				'cap'         => 'aeo_manage_settings',
				'run'         => static function ( $in ) {
					$rules               = Ai_Crawlers::rules();
					$rules[ $in['bot'] ] = 'block' === $in['rule'] ? 'block' : 'allow';
					Settings::update(
						array(
							'crawler_preset'   => 'custom',
							'crawler_settings' => $rules,
						)
					);
					return array( 'bots' => Ai_Crawlers::for_admin() );
				},
			),
			'suggest-prompts'     => array(
				'label'       => __( 'Suggest prompts to track', 'answer-engine-optimization' ),
				'description' => 'Questions people are likely to ask AI assistants about this business, built from its content and location.',
				'input'       => $empty,
				'readonly'    => true,
				'cap'         => 'aeo_view_reports',
				'run'         => static function () {
					return Prompt_Library::suggest( 30 );
				},
			),
		);
	}
}
