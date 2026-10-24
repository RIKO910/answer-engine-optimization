<?php
/**
 * OpenAI provider (Chat Completions API). Base class for OpenAI-compatible APIs.
 *
 * @package AEO
 */

namespace AEO\Services\AI;

defined( 'ABSPATH' ) || exit;

/**
 * OpenAI.
 */
class OpenAI_Provider extends Remote_Provider {

	/**
	 * {@inheritdoc}
	 *
	 * @var string
	 */
	protected $slug = 'openai';

	/**
	 * Chat Completions endpoint. OpenAI-compatible providers override this.
	 *
	 * @var string
	 */
	protected $endpoint = 'https://api.openai.com/v1/chat/completions';

	/**
	 * Does the provider accept response_format json_object?
	 *
	 * @var bool
	 */
	protected $json_mode = true;

	/**
	 * Extra request headers.
	 *
	 * @param string $api_key Key.
	 * @return array<string, string>
	 */
	protected function headers( $api_key ) {
		return array(
			'Authorization' => 'Bearer ' . $api_key,
			'Content-Type'  => 'application/json',
		);
	}

	/**
	 * {@inheritdoc}
	 */
	protected function send( $prompt, $api_key, $model, $json ) {
		$body = array(
			'model'    => $model,
			'messages' => array(
				array(
					'role'    => 'user',
					'content' => $prompt,
				),
			),
		);
		if ( $json && $this->json_mode ) {
			$body['response_format'] = array( 'type' => 'json_object' );
		}

		$data = $this->decode(
			wp_remote_post(
				$this->endpoint,
				array(
					'timeout' => 90,
					'headers' => $this->headers( $api_key ),
					'body'    => wp_json_encode( $body ),
				)
			)
		);
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		return (string) ( $data['choices'][0]['message']['content'] ?? '' );
	}

	/**
	 * {@inheritdoc}
	 */
	protected function send_image( $prompt, $api_key, $model, $mime, $b64 ) {
		$data = $this->decode(
			wp_remote_post(
				$this->endpoint,
				array(
					'timeout' => 90,
					'headers' => $this->headers( $api_key ),
					'body'    => wp_json_encode(
						array(
							'model'    => $model,
							'messages' => array(
								array(
									'role'    => 'user',
									'content' => array(
										array(
											'type' => 'text',
											'text' => $prompt,
										),
										array(
											'type'      => 'image_url',
											'image_url' => array( 'url' => 'data:' . $mime . ';base64,' . $b64 ),
										),
									),
								),
							),
						)
					),
				)
			)
		);
		return is_wp_error( $data ) ? $data : (string) ( $data['choices'][0]['message']['content'] ?? '' );
	}
}
