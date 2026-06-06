<?php
/**
 * Anthropic Claude provider.
 *
 * @package AEO
 */

namespace AEO\Services\AI;

use AEO\Core\Settings;
use AEO\Services\Encryption;

defined( 'ABSPATH' ) || exit;

/**
 * Anthropic Claude integration.
 */
class Anthropic_Provider implements AI_Provider_Interface {

	/**
	 * {@inheritDoc}
	 */
	public function generate_faqs( $content, $count = 5 ) {
		$prompt = sprintf( 'Generate %d FAQ pairs as JSON array. Content: %s', $count, wp_trim_words( wp_strip_all_tags( $content ), 500 ) );
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
		$response = $this->request( 'Rewrite for AEO in HTML: ' . wp_trim_words( wp_strip_all_tags( $content ), 800 ) );
		return is_wp_error( $response ) ? ( new Local_AI_Provider() )->rewrite_content( $content ) : $response;
	}

	/**
	 * {@inheritDoc}
	 */
	public function generate_brief( $topic ) {
		$response = $this->request( 'Create AEO content brief JSON for: ' . $topic );
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
		return ! empty( Settings::get( 'anthropic_api_key', '' ) );
	}

	/**
	 * API request.
	 *
	 * @param string $prompt Prompt text.
	 * @return string|\WP_Error
	 */
	private function request( $prompt ) {
		$api_key = Encryption::decrypt( Settings::get( 'anthropic_api_key', '' ) );
		if ( empty( $api_key ) ) {
			return new \WP_Error( 'no_api_key', __( 'Anthropic API key not configured', 'answer-engine-optimization' ) );
		}

		$response = wp_remote_post(
			'https://api.anthropic.com/v1/messages',
			array(
				'timeout' => 60,
				'headers' => array(
					'x-api-key'         => $api_key,
					'anthropic-version' => '2023-06-01',
					'Content-Type'      => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'model'      => 'claude-3-5-sonnet-20241022',
						'max_tokens' => 4096,
						'messages'   => array(
							array( 'role' => 'user', 'content' => $prompt ),
						),
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		return $body['content'][0]['text'] ?? new \WP_Error( 'invalid_response', __( 'Invalid AI response', 'answer-engine-optimization' ) );
	}
}
