<?php
/**
 * Anthropic provider (Messages API).
 *
 * @package AEO
 */

namespace AEO\Services\AI;

defined( 'ABSPATH' ) || exit;

/**
 * Anthropic Claude.
 */
class Anthropic_Provider extends Remote_Provider {

	/**
	 * {@inheritdoc}
	 *
	 * @var string
	 */
	protected $slug = 'anthropic';

	/**
	 * {@inheritdoc}
	 */
	protected function send( $prompt, $api_key, $model, $json ) {
		$data = $this->decode(
			wp_remote_post(
				'https://api.anthropic.com/v1/messages',
				array(
					'timeout' => 90,
					'headers' => array(
						'x-api-key'         => $api_key,
						'anthropic-version' => '2023-06-01',
						'Content-Type'      => 'application/json',
					),
					'body'    => wp_json_encode(
						array(
							'model'      => $model,
							'max_tokens' => 4096,
							'messages'   => array(
								array(
									'role'    => 'user',
									'content' => $prompt,
								),
							),
						)
					),
				)
			)
		);
		if ( is_wp_error( $data ) ) {
			return $data;
		}

		$text = '';
		foreach ( (array) ( $data['content'] ?? array() ) as $block ) {
			if ( isset( $block['type'], $block['text'] ) && 'text' === $block['type'] ) {
				$text .= $block['text'];
			}
		}
		return $text;
	}

	/**
	 * {@inheritdoc}
	 */
	protected function send_image( $prompt, $api_key, $model, $mime, $b64 ) {
		$data = $this->decode(
			wp_remote_post(
				'https://api.anthropic.com/v1/messages',
				array(
					'timeout' => 90,
					'headers' => array(
						'x-api-key'         => $api_key,
						'anthropic-version' => '2023-06-01',
						'Content-Type'      => 'application/json',
					),
					'body'    => wp_json_encode(
						array(
							'model'      => $model,
							'max_tokens' => 300,
							'messages'   => array(
								array(
									'role'    => 'user',
									'content' => array(
										array(
											'type'   => 'image',
											'source' => array(
												'type'       => 'base64',
												'media_type' => $mime,
												'data'       => $b64,
											),
										),
										array(
											'type' => 'text',
											'text' => $prompt,
										),
									),
								),
							),
						)
					),
				)
			)
		);
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		$text = '';
		foreach ( (array) ( $data['content'] ?? array() ) as $block ) {
			$text .= (string) ( $block['text'] ?? '' );
		}
		return $text;
	}
}
