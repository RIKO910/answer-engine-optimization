<?php
/**
 * Google Gemini provider (generateContent API).
 *
 * @package AEO
 */

namespace AEO\Services\AI;

defined( 'ABSPATH' ) || exit;

/**
 * Gemini.
 */
class Gemini_Provider extends Remote_Provider {

	/**
	 * {@inheritdoc}
	 *
	 * @var string
	 */
	protected $slug = 'gemini';

	/**
	 * {@inheritdoc}
	 */
	protected function send( $prompt, $api_key, $model, $json ) {
		$body = array(
			'contents' => array(
				array(
					'role'  => 'user',
					'parts' => array( array( 'text' => $prompt ) ),
				),
			),
		);
		if ( $json ) {
			$body['generationConfig'] = array( 'responseMimeType' => 'application/json' );
		}

		// Key goes in a header, not the URL, so it never lands in server or proxy logs.
		$data = $this->decode(
			wp_remote_post(
				'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode( $model ) . ':generateContent',
				array(
					'timeout' => 90,
					'headers' => array(
						'x-goog-api-key' => $api_key,
						'Content-Type'   => 'application/json',
					),
					'body'    => wp_json_encode( $body ),
				)
			)
		);
		if ( is_wp_error( $data ) ) {
			return $data;
		}

		$text = '';
		foreach ( (array) ( $data['candidates'][0]['content']['parts'] ?? array() ) as $part ) {
			$text .= (string) ( $part['text'] ?? '' );
		}
		return $text;
	}

	/**
	 * {@inheritdoc}
	 */
	protected function send_image( $prompt, $api_key, $model, $mime, $b64 ) {
		$data = $this->decode(
			wp_remote_post(
				'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode( $model ) . ':generateContent',
				array(
					'timeout' => 90,
					'headers' => array(
						'x-goog-api-key' => $api_key,
						'Content-Type'   => 'application/json',
					),
					'body'    => wp_json_encode(
						array(
							'contents' => array(
								array(
									'role'  => 'user',
									'parts' => array(
										array(
											'inline_data' => array(
												'mime_type' => $mime,
												'data'      => $b64,
											),
										),
										array( 'text' => $prompt ),
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
		foreach ( (array) ( $data['candidates'][0]['content']['parts'] ?? array() ) as $part ) {
			$text .= (string) ( $part['text'] ?? '' );
		}
		return $text;
	}
}
