<?php
/**
 * OpenAI provider.
 *
 * @package AEO
 */

namespace AEO\Services\AI;

use AEO\Core\Settings;
use AEO\Services\Encryption;

defined( 'ABSPATH' ) || exit;

/**
 * OpenAI GPT integration.
 */
class OpenAI_Provider implements AI_Provider_Interface {

	/**
	 * API endpoint.
	 *
	 * @var string
	 */
	private $endpoint = 'https://api.openai.com/v1/chat/completions';

	/**
	 * {@inheritDoc}
	 */
	public function generate_faqs( $content, $count = 5 ) {
		$prompt = sprintf(
			'Generate %d FAQ question-answer pairs from this content. Return JSON array with question and answer keys. Content: %s',
			$count,
			wp_trim_words( wp_strip_all_tags( $content ), 500 )
		);

		$response = $this->request( $prompt );
		if ( is_wp_error( $response ) ) {
			return ( new Local_AI_Provider() )->generate_faqs( $content, $count );
		}

		$parsed = json_decode( $response, true );
		return is_array( $parsed ) ? $parsed : ( new Local_AI_Provider() )->generate_faqs( $content, $count );
	}

	/**
	 * {@inheritDoc}
	 */
	public function rewrite_content( $content ) {
		$prompt = 'Rewrite this content for Answer Engine Optimization. Make it direct, question-answering format while preserving meaning. Return HTML: ' .
			wp_trim_words( wp_strip_all_tags( $content ), 800 );

		$response = $this->request( $prompt );
		if ( is_wp_error( $response ) ) {
			return ( new Local_AI_Provider() )->rewrite_content( $content );
		}

		return $response;
	}

	/**
	 * {@inheritDoc}
	 */
	public function generate_brief( $topic ) {
		$prompt = sprintf(
			'Create an AEO content brief for topic "%s". Return JSON with: target_questions (array), entities (array), schema_type (string), opportunity_score (0-100), faq_suggestions (array of question/answer)',
			$topic
		);

		$response = $this->request( $prompt );
		if ( is_wp_error( $response ) ) {
			return ( new Local_AI_Provider() )->generate_brief( $topic );
		}

		$parsed = json_decode( $response, true );
		return is_array( $parsed ) ? $parsed : ( new Local_AI_Provider() )->generate_brief( $topic );
	}

	/**
	 * {@inheritDoc}
	 */
	public function is_configured() {
		$key = Settings::get( 'openai_api_key', '' );
		return ! empty( $key );
	}

	/**
	 * Make API request.
	 *
	 * @param string $prompt User prompt.
	 * @return string|\WP_Error
	 */
	private function request( $prompt ) {
		$api_key = Encryption::decrypt( Settings::get( 'openai_api_key', '' ) );
		if ( empty( $api_key ) ) {
			return new \WP_Error( 'no_api_key', __( 'OpenAI API key not configured', 'answer-engine-optimization' ) );
		}

		$response = wp_remote_post(
			$this->endpoint,
			array(
				'timeout' => 60,
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'model'       => 'gpt-4o-mini',
						'messages'    => array(
							array( 'role' => 'user', 'content' => $prompt ),
						),
						'temperature' => 0.7,
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		return $body['choices'][0]['message']['content'] ?? new \WP_Error( 'invalid_response', __( 'Invalid AI response', 'answer-engine-optimization' ) );
	}
}
