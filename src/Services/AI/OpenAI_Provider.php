<?php
/**
 * OpenAI provider (Chat Completions API).
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
		if ( $json ) {
			$body['response_format'] = array( 'type' => 'json_object' );
		}

		$data = $this->decode(
			wp_remote_post(
				'https://api.openai.com/v1/chat/completions',
				array(
					'timeout' => 90,
					'headers' => array(
						'Authorization' => 'Bearer ' . $api_key,
						'Content-Type'  => 'application/json',
					),
					'body'    => wp_json_encode( $body ),
				)
			)
		);
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		return (string) ( $data['choices'][0]['message']['content'] ?? '' );
	}
}
