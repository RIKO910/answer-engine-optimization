<?php
/**
 * AI provider manager.
 *
 * @package AEO
 */

namespace AEO\Services\AI;

use AEO\Core\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Factory for AI provider instances.
 */
class AI_Manager {

	/**
	 * Get active AI provider.
	 *
	 * @return AI_Provider_Interface
	 */
	public function get_provider() {
		$provider = Settings::get( 'ai_provider', 'openai' );

		$map = array(
			'openai'     => OpenAI_Provider::class,
			'anthropic'  => Anthropic_Provider::class,
			'gemini'     => Gemini_Provider::class,
			'mistral'    => Mistral_Provider::class,
			'deepseek'   => DeepSeek_Provider::class,
			'openrouter' => OpenRouter_Provider::class,
		);
		if ( isset( $map[ $provider ] ) ) {
			$instance = new $map[ $provider ]();
			if ( $instance->is_configured() ) {
				return $instance;
			}
		}

		return new Local_AI_Provider();
	}
}
