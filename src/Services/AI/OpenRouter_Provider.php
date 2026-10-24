<?php
/**
 * OpenRouter provider (OpenAI-compatible Chat Completions API).
 *
 * @package AEO
 */

namespace AEO\Services\AI;

defined( 'ABSPATH' ) || exit;

/**
 * OpenRouter.
 */
class OpenRouter_Provider extends OpenAI_Provider {

	/**
	 * {@inheritdoc}
	 *
	 * @var string
	 */
	protected $slug = 'openrouter';

	/**
	 * {@inheritdoc}
	 *
	 * @var string
	 */
	protected $endpoint = 'https://openrouter.ai/api/v1/chat/completions';

	/**
	 * {@inheritdoc}
	 */
	protected function headers( $api_key ) {
		return parent::headers( $api_key ) + array(
			'HTTP-Referer' => home_url( '/' ),
			'X-Title'      => get_bloginfo( 'name' ),
		);
	}
}
