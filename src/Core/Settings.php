<?php
/**
 * Plugin settings manager.
 *
 * @package AEO
 */

namespace AEO\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Centralized settings access.
 */
class Settings {

	const OPTION_KEY = 'aeo_optimizer_settings';

	/**
	 * Get all settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_all() {
		$settings = get_option( self::OPTION_KEY, array() );
		return is_array( $settings ) ? $settings : array();
	}

	/**
	 * Get a setting value.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	public static function get( $key, $default = null ) {
		$settings = self::get_all();
		return $settings[ $key ] ?? $default;
	}

	/**
	 * Update settings.
	 *
	 * @param array<string, mixed> $data Settings data.
	 * @return bool
	 */
	public static function update( array $data ) {
		$current = self::get_all();
		$merged  = array_merge( $current, $data );
		return update_option( self::OPTION_KEY, $merged );
	}

	/**
	 * Check if a module is enabled.
	 *
	 * @param string $module Module slug.
	 * @return bool
	 */
	public static function is_module_enabled( $module ) {
		$modules = self::get( 'modules', array() );
		return ! empty( $modules[ $module ] );
	}

	/**
	 * Sanitize settings for REST API.
	 *
	 * @param array<string, mixed> $input Raw input.
	 * @return array<string, mixed>
	 */
	public static function sanitize( array $input ) {
		$sanitized = array();

		if ( isset( $input['site_type'] ) ) {
			$sanitized['site_type'] = sanitize_text_field( $input['site_type'] );
		}

		if ( isset( $input['onboarding_complete'] ) ) {
			$sanitized['onboarding_complete'] = (bool) $input['onboarding_complete'];
		}

		if ( isset( $input['ai_provider'] ) ) {
			$allowed = array( 'openai', 'anthropic', 'gemini' );
			$sanitized['ai_provider'] = in_array( $input['ai_provider'], $allowed, true )
				? $input['ai_provider']
				: 'openai';
		}

		if ( isset( $input['modules'] ) && is_array( $input['modules'] ) ) {
			$sanitized['modules'] = array_map( 'rest_sanitize_boolean', $input['modules'] );
		}

		if ( isset( $input['competitors'] ) && is_array( $input['competitors'] ) ) {
			$sanitized['competitors'] = array_slice(
				array_map( 'sanitize_text_field', $input['competitors'] ),
				0,
				5
			);
		}

		if ( isset( $input['tracked_keywords'] ) && is_array( $input['tracked_keywords'] ) ) {
			$sanitized['tracked_keywords'] = array_map( 'sanitize_text_field', $input['tracked_keywords'] );
		}

		if ( isset( $input['business_info'] ) && is_array( $input['business_info'] ) ) {
			$sanitized['business_info'] = array(
				'name'        => sanitize_text_field( $input['business_info']['name'] ?? '' ),
				'description' => sanitize_textarea_field( $input['business_info']['description'] ?? '' ),
				'logo_url'    => esc_url_raw( $input['business_info']['logo_url'] ?? '' ),
				'social'      => isset( $input['business_info']['social'] ) && is_array( $input['business_info']['social'] )
					? array_map( 'esc_url_raw', $input['business_info']['social'] )
					: array(),
			);
		}

		$checkboxes = array( 'aeo_enable_schema', 'aeo_auto_questions', 'aeo_voice_optimization' );
		foreach ( $checkboxes as $checkbox ) {
			if ( isset( $input[ $checkbox ] ) ) {
				$sanitized[ $checkbox ] = $input[ $checkbox ] ? 1 : 0;
			}
		}

		if ( isset( $input['aeo_target_questions'] ) ) {
			$questions = explode( "\n", $input['aeo_target_questions'] );
			$questions = array_map( 'sanitize_text_field', $questions );
			$sanitized['aeo_target_questions'] = implode( "\n", $questions );
		}

		if ( isset( $input['notifications'] ) && is_array( $input['notifications'] ) ) {
			$sanitized['notifications'] = array(
				'email_citations' => ! empty( $input['notifications']['email_citations'] ),
				'email_audit'     => ! empty( $input['notifications']['email_audit'] ),
			);
		}

		if ( isset( $input['crawler_settings'] ) && is_array( $input['crawler_settings'] ) ) {
			$allowed_bots = array( 'gptbot', 'perplexitybot', 'claudebot', 'googlebot_ai' );
			$crawlers     = array();
			foreach ( $allowed_bots as $bot ) {
				if ( isset( $input['crawler_settings'][ $bot ] ) ) {
					$crawlers[ $bot ] = 'block' === $input['crawler_settings'][ $bot ] ? 'block' : 'allow';
				}
			}
			if ( ! empty( $crawlers ) ) {
				$sanitized['crawler_settings'] = $crawlers;
			}
		}

		if ( isset( $input['agency'] ) && is_array( $input['agency'] ) ) {
			$sanitized['agency'] = array(
				'white_label_name' => sanitize_text_field( $input['agency']['white_label_name'] ?? '' ),
				'white_label_logo' => esc_url_raw( $input['agency']['white_label_logo'] ?? '' ),
			);
		}

		return $sanitized;
	}

	/**
	 * Get settings safe for frontend (no API keys).
	 *
	 * @return array<string, mixed>
	 */
	public static function get_public() {
		$settings = self::get_all();
		unset( $settings['openai_api_key'], $settings['anthropic_api_key'], $settings['gemini_api_key'] );

		$settings['has_openai_key']    = ! empty( self::get( 'openai_api_key' ) );
		$settings['has_anthropic_key'] = ! empty( self::get( 'anthropic_api_key' ) );
		$settings['has_gemini_key']    = ! empty( self::get( 'gemini_api_key' ) );
		$settings['woocommerce_active'] = class_exists( 'WooCommerce' );

		return $settings;
	}
}
