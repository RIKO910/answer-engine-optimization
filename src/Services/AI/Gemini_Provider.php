<?php
/**
 * Google Gemini provider.
 *
 * @package AEO
 */

namespace AEO\Services\AI;

use AEO\Core\Settings;
use AEO\Services\Encryption;

defined( 'ABSPATH' ) || exit;

/**
 * Google Gemini integration.
 */
class Gemini_Provider implements AI_Provider_Interface {

	/**
	 * {@inheritDoc}
	 */
	public function generate_faqs( $content, $count = 5 ) {
		$prompt = sprintf( 'Generate %d FAQ pairs as JSON. Content: %s', $count, wp_trim_words( wp_strip_all_tags( $content ), 500 ) );
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
		$response = $this->request( 'Create AEO brief JSON for: ' . $topic );
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
		return ! empty( Settings::get( 'gemini_api_key', '' ) );
	}

	/**
	 * API request.
	 *
	 * @param string $prompt Prompt text.
	 * @return string|\WP_Error
	 */
	private function request( $prompt ) {
		$api_key = Encryption::decrypt( Settings::get( 'gemini_api_key', '' ) );
		if ( empty( $api_key ) ) {
			return new \WP_Error( 'no_api_key', __( 'Gemini API key not configured', 'answer-engine-optimization' ) );
		}

		$url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-pro:generateContent?key=' . $api_key;

		$response = wp_remote_post(
			$url,
			array(
				'timeout' => 60,
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode(
					array(
						'contents' => array(
							array( 'parts' => array( array( 'text' => $prompt ) ) ),
						),
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		return $body['candidates'][0]['content']['parts'][0]['text'] ?? new \WP_Error( 'invalid_response', __( 'Invalid AI response', 'answer-engine-optimization' ) );
	}
}
