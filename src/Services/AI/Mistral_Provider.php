<?php
/**
 * Mistral provider (OpenAI-compatible Chat Completions API).
 *
 * @package AEO
 */

namespace AEO\Services\AI;

defined( 'ABSPATH' ) || exit;

/**
 * Mistral.
 */
class Mistral_Provider extends OpenAI_Provider {

	/**
	 * {@inheritdoc}
	 *
	 * @var string
	 */
	protected $slug = 'mistral';

	/**
	 * {@inheritdoc}
	 *
	 * @var string
	 */
	protected $endpoint = 'https://api.mistral.ai/v1/chat/completions';
}
