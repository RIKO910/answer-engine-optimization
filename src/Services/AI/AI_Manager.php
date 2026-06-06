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

		switch ( $provider ) {
			case 'openai':
				$instance = new OpenAI_Provider();
				if ( $instance->is_configured() ) {
					return $instance;
				}
				break;
			case 'anthropic':
				$instance = new Anthropic_Provider();
				if ( $instance->is_configured() ) {
					return $instance;
				}
				break;
			case 'gemini':
				$instance = new Gemini_Provider();
				if ( $instance->is_configured() ) {
					return $instance;
				}
				break;
		}

		return new Local_AI_Provider();
	}
}
