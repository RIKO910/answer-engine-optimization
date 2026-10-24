<?php
/**
 * DeepSeek provider (OpenAI-compatible Chat Completions API).
 *
 * @package AEO
 */

namespace AEO\Services\AI;

defined( 'ABSPATH' ) || exit;

/**
 * DeepSeek.
 */
class DeepSeek_Provider extends OpenAI_Provider {

	/**
	 * {@inheritdoc}
	 *
	 * @var string
	 */
	protected $slug = 'deepseek';

	/**
	 * {@inheritdoc}
	 *
	 * @var string
	 */
	protected $endpoint = 'https://api.deepseek.com/chat/completions';

	/**
	 * DeepSeek chat models do not accept images.
	 *
	 * {@inheritdoc}
	 */
	protected function send_image( $prompt, $api_key, $model, $mime, $b64 ) {
		return new \WP_Error( 'aeo_no_vision', __( 'DeepSeek cannot read images. Choose another provider for alt text.', 'answer-engine-optimization' ) );
	}
}
