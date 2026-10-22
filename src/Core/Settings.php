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
	 * Settings keys that hold encrypted API keys.
	 */
	const API_KEYS = array( 'openai_api_key', 'anthropic_api_key', 'gemini_api_key', 'perplexity_api_key' );

	/**
	 * Default model per provider. Overridable in Settings and via the `aeo_ai_model` filter.
	 */
	const DEFAULT_MODELS = array(
		'openai'     => 'gpt-5.4-mini',
		'anthropic'  => 'claude-haiku-5-5',
		'gemini'     => 'gemini-3.5-flash',
		'perplexity' => 'sonar',
	);

	/**
	 * Model to use for a provider.
	 *
	 * @param string $provider Provider slug.
	 * @return string
	 */
	public static function get_model( $provider ) {
		$model = (string) self::get( $provider . '_model', '' );
		if ( '' === $model ) {
			$model = self::DEFAULT_MODELS[ $provider ] ?? '';
		}
		return (string) apply_filters( 'aeo_ai_model', $model, $provider );
	}

	/**
	 * Post types the plugin scores, audits and lists in llms.txt.
	 *
	 * @return string[]
	 */
	public static function get_post_types() {
		$types = get_post_types( array( 'public' => true ), 'names' );
		unset( $types['attachment'] );
		return array_values( (array) apply_filters( 'aeo_post_types', array_values( $types ) ) );
	}

	/**
	 * Normalize a user-entered domain or URL to a bare host.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	public static function normalize_domain( $value ) {
		$value = trim( strtolower( sanitize_text_field( (string) $value ) ) );
		if ( '' === $value ) {
			return '';
		}
		if ( false === strpos( $value, '://' ) ) {
			$value = 'https://' . $value;
		}
		$host = wp_parse_url( $value, PHP_URL_HOST );
		if ( ! $host || false === strpos( $host, '.' ) ) {
			return '';
		}
		return preg_replace( '/^www\./', '', $host );
	}

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

		foreach ( array( 'openai_model', 'anthropic_model', 'gemini_model', 'perplexity_model' ) as $model_key ) {
			if ( isset( $input[ $model_key ] ) ) {
				// Model IDs only contain letters, digits, dots, dashes, underscores and slashes.
				$sanitized[ $model_key ] = preg_replace( '/[^A-Za-z0-9._\/-]/', '', (string) $input[ $model_key ] );
			}
		}

		if ( isset( $input['modules'] ) && is_array( $input['modules'] ) ) {
			$sanitized['modules'] = array_map( 'rest_sanitize_boolean', $input['modules'] );
		}

		if ( isset( $input['competitors'] ) && is_array( $input['competitors'] ) ) {
			$domains = array();
			foreach ( $input['competitors'] as $domain ) {
				$domain = self::normalize_domain( $domain );
				if ( $domain && ! in_array( $domain, $domains, true ) ) {
					$domains[] = $domain;
				}
			}
			$sanitized['competitors'] = array_slice( $domains, 0, 5 );
		}

		if ( isset( $input['tracked_keywords'] ) && is_array( $input['tracked_keywords'] ) ) {
			$keywords = array_filter( array_map( 'trim', array_map( 'sanitize_text_field', $input['tracked_keywords'] ) ) );
			$sanitized['tracked_keywords'] = array_slice( array_values( array_unique( $keywords ) ), 0, 50 );
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

		$checkboxes = array( 'aeo_enable_schema', 'aeo_auto_questions', 'aeo_voice_optimization', 'defer_to_seo_plugin' );
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
		foreach ( self::API_KEYS as $key ) {
			unset( $settings[ $key ] );
		}

		$settings['has_openai_key']     = ! empty( self::get( 'openai_api_key' ) );
		$settings['has_anthropic_key']  = ! empty( self::get( 'anthropic_api_key' ) );
		$settings['has_gemini_key']     = ! empty( self::get( 'gemini_api_key' ) );
		$settings['has_perplexity_key'] = ! empty( self::get( 'perplexity_api_key' ) );
		$settings['woocommerce_active'] = class_exists( 'WooCommerce' );
		$settings['seo_plugin']         = \AEO\Services\Seo_Compat::active_plugin_name();
		$settings['default_models']     = self::DEFAULT_MODELS;

		return $settings;
	}
}
