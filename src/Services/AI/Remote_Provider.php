<?php
/**
 * Shared logic for API-backed providers.
 *
 * @package AEO
 */

namespace AEO\Services\AI;

use AEO\Core\Settings;
use AEO\Services\Encryption;

defined( 'ABSPATH' ) || exit;

/**
 * Base class: subclasses only implement send().
 */
abstract class Remote_Provider implements AI_Provider_Interface {

	/**
	 * Provider slug (openai|anthropic|gemini).
	 *
	 * @var string
	 */
	protected $slug = '';

	/**
	 * Last error, for the REST layer.
	 *
	 * @var \WP_Error|null
	 */
	public $last_error = null;

	/**
	 * Send a prompt and return the text reply.
	 *
	 * @param string $prompt  Prompt.
	 * @param string $api_key Decrypted key.
	 * @param string $model   Model ID.
	 * @param bool   $json    Ask for JSON output.
	 * @return string|\WP_Error
	 */
	abstract protected function send( $prompt, $api_key, $model, $json );

	/**
	 * {@inheritdoc}
	 */
	public function is_configured() {
		return '' !== $this->api_key();
	}

	/**
	 * Decrypted API key.
	 *
	 * @return string
	 */
	protected function api_key() {
		return (string) Encryption::decrypt( Settings::get( $this->slug . '_api_key', '' ) );
	}

	/**
	 * Run a request with error capture.
	 *
	 * @param string $prompt Prompt.
	 * @param bool   $json   JSON mode.
	 * @return string|\WP_Error
	 */
	protected function request( $prompt, $json = false ) {
		$key = $this->api_key();
		if ( '' === $key ) {
			$this->last_error = new \WP_Error( 'no_api_key', __( 'API key not configured', 'answer-engine-optimization' ) );
			return $this->last_error;
		}
		$result = $this->send( $prompt, $key, Settings::get_model( $this->slug ), $json );
		if ( is_wp_error( $result ) ) {
			$this->last_error = $result;
		}
		return $result;
	}

	/**
	 * Decode an HTTP response or return a WP_Error with the API's message.
	 *
	 * @param array<string, mixed>|\WP_Error $response Response.
	 * @return array<string, mixed>|\WP_Error
	 */
	protected function decode( $response ) {
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( $code < 200 || $code >= 300 ) {
			$message = is_array( $body ) ? ( $body['error']['message'] ?? ( $body['message'] ?? '' ) ) : '';
			return new \WP_Error(
				'ai_http_' . $code,
				/* translators: 1: HTTP status, 2: API message */
				sprintf( __( 'AI provider returned HTTP %1$d. %2$s', 'answer-engine-optimization' ), $code, is_string( $message ) ? $message : '' )
			);
		}
		return is_array( $body ) ? $body : new \WP_Error( 'invalid_response', __( 'Invalid AI response', 'answer-engine-optimization' ) );
	}

	/**
	 * {@inheritdoc}
	 */
	public function generate_faqs( $content, $count = 5 ) {
		$response = $this->request( AI_Response::prompt( 'faqs', $content, $count ), true );
		$faqs     = is_wp_error( $response ) ? array() : AI_Response::normalize_faqs( AI_Response::extract_json( $response ) );
		return $faqs ? array_slice( $faqs, 0, $count ) : ( new Local_AI_Provider() )->generate_faqs( $content, $count );
	}

	/**
	 * {@inheritdoc}
	 */
	public function rewrite_content( $content ) {
		$response = $this->request( AI_Response::prompt( 'rewrite', $content ) );
		if ( is_wp_error( $response ) || '' === trim( (string) $response ) ) {
			return ( new Local_AI_Provider() )->rewrite_content( $content );
		}
		$html = preg_replace( '/^```(?:html)?\s*|\s*```$/i', '', trim( $response ) );
		return wp_kses_post( $html );
	}

	/**
	 * {@inheritdoc}
	 */
	public function generate_brief( $topic ) {
		$response = $this->request( AI_Response::prompt( 'brief', $topic ), true );
		$data     = is_wp_error( $response ) ? null : AI_Response::extract_json( $response );
		if ( ! is_array( $data ) ) {
			return ( new Local_AI_Provider() )->generate_brief( $topic );
		}
		return AI_Response::normalize_brief( $data, $topic );
	}
}
