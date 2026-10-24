<?php
/**
 * WPGraphQL fields for headless sites.
 *
 * Adds aeoScore, aeoDirectAnswer, aeoTargetQuestion, aeoFaqs, aeoSchema (JSON-LD
 * string) and aeoMarkdown to every post type the plugin handles that is exposed in
 * GraphQL, so a decoupled front end can print the same schema and answers.
 *
 * @package AEO
 */

namespace AEO\Integrations;

use AEO\Core\Settings;
use AEO\Services\Schema_Service;

defined( 'ABSPATH' ) || exit;

/**
 * GraphQL registration.
 */
class Graphql {

	/**
	 * Register.
	 *
	 * @return void
	 */
	public static function register() {
		add_action( 'graphql_register_types', array( __CLASS__, 'types' ) );
	}

	/**
	 * Types and fields.
	 *
	 * @return void
	 */
	public static function types() {
		if ( ! function_exists( 'register_graphql_field' ) || ! function_exists( 'register_graphql_object_type' ) ) {
			return;
		}

		register_graphql_object_type(
			'AeoFaq',
			array(
				'description' => __( 'A question and answer pair.', 'answer-engine-optimization' ),
				'fields'      => array(
					'question' => array( 'type' => 'String' ),
					'answer'   => array( 'type' => 'String' ),
				),
			)
		);

		foreach ( Settings::get_post_types() as $type ) {
			$object = get_post_type_object( $type );
			if ( ! $object || empty( $object->show_in_graphql ) || empty( $object->graphql_single_name ) ) {
				continue;
			}
			$gql = ucfirst( $object->graphql_single_name );

			register_graphql_field(
				$gql,
				'aeoScore',
				array(
					'type'        => 'Int',
					'description' => __( 'AEO score (0–100).', 'answer-engine-optimization' ),
					'resolve'     => static function ( $post ) {
						$score = get_post_meta( (int) $post->databaseId, '_aeo_score', true );
						return '' === $score ? null : (int) $score;
					},
				)
			);
			register_graphql_field(
				$gql,
				'aeoTargetQuestion',
				array(
					'type'    => 'String',
					'resolve' => static function ( $post ) {
						return (string) get_post_meta( (int) $post->databaseId, '_aeo_target_question', true );
					},
				)
			);
			register_graphql_field(
				$gql,
				'aeoDirectAnswer',
				array(
					'type'    => 'String',
					'resolve' => static function ( $post ) {
						$id     = (int) $post->databaseId;
						$answer = (string) get_post_meta( $id, '_aeo_direct_answer', true );
						if ( '' === $answer ) {
							foreach ( parse_blocks( (string) get_post_field( 'post_content', $id ) ) as $block ) {
								if ( 'aeo/direct-answer' === $block['blockName'] ) {
									$answer = trim( wp_strip_all_tags( (string) ( $block['attrs']['answer'] ?? '' ) ) );
									break;
								}
							}
						}
						return $answer;
					},
				)
			);
			register_graphql_field(
				$gql,
				'aeoFaqs',
				array(
					'type'    => array( 'list_of' => 'AeoFaq' ),
					'resolve' => static function ( $post ) {
						return self::faqs( (int) $post->databaseId );
					},
				)
			);
			register_graphql_field(
				$gql,
				'aeoSchema',
				array(
					'type'        => 'String',
					'description' => __( 'JSON-LD @graph for this item, as a JSON string.', 'answer-engine-optimization' ),
					'resolve'     => static function ( $post ) {
						$nodes = ( new Schema_Service() )->get_post_nodes( (int) $post->databaseId );
						return $nodes ? (string) wp_json_encode(
							array(
								'@context' => 'https://schema.org',
								'@graph'   => array_values( $nodes ),
							),
							JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
						) : null;
					},
				)
			);
			register_graphql_field(
				$gql,
				'aeoMarkdown',
				array(
					'type'        => 'String',
					'description' => __( 'Clean Markdown version of the content, as served to AI agents.', 'answer-engine-optimization' ),
					'resolve'     => static function ( $post ) {
						$p = (int) $post->databaseId;
						return \AEO\Frontend\Llms_Txt::post_markdown( $p );
					},
				)
			);
		}
	}

	/**
	 * FAQs from meta and blocks, deduplicated.
	 *
	 * @param int $id Post ID.
	 * @return array<int, array{question: string, answer: string}>
	 */
	public static function faqs( $id ) {
		$out  = array();
		$seen = array();
		$meta = get_post_meta( $id, '_aeo_faq_items', true );
		$rows = array_merge( is_array( $meta ) ? $meta : array(), \AEO\Content\Content_Extractor::extract( $id )['faqs'] ?? array() );
		foreach ( $rows as $row ) {
			$q = trim( wp_strip_all_tags( (string) ( $row['question'] ?? '' ) ) );
			$a = trim( wp_strip_all_tags( (string) ( $row['answer'] ?? '' ) ) );
			$k = strtolower( $q );
			if ( '' === $q || '' === $a || isset( $seen[ $k ] ) ) {
				continue;
			}
			$seen[ $k ] = true;
			$out[]      = array(
				'question' => $q,
				'answer'   => $a,
			);
		}
		return $out;
	}
}
