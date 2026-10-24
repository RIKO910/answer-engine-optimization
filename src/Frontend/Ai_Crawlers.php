<?php
/**
 * AI crawler registry, robots.txt rules, Content-Signal and crawler visit logging.
 *
 * @package AEO
 */

namespace AEO\Frontend;

use AEO\Core\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * AI crawlers.
 */
class Ai_Crawlers {

	/**
	 * Known AI user agents.
	 *
	 * purpose: training = collects data to train models,
	 *          search   = indexes pages for AI search answers,
	 *          user     = fetches a page live because a user asked the assistant.
	 * token_only: robots.txt token that never appears in a User-Agent header.
	 */
	const BOTS = array(
		'gptbot'               => array( 'name' => 'GPTBot', 'owner' => 'OpenAI', 'purpose' => 'training' ),
		'oai-searchbot'        => array( 'name' => 'OAI-SearchBot', 'owner' => 'OpenAI', 'purpose' => 'search' ),
		'chatgpt-user'         => array( 'name' => 'ChatGPT-User', 'owner' => 'OpenAI', 'purpose' => 'user' ),
		'claudebot'            => array( 'name' => 'ClaudeBot', 'owner' => 'Anthropic', 'purpose' => 'training' ),
		'claude-searchbot'     => array( 'name' => 'Claude-SearchBot', 'owner' => 'Anthropic', 'purpose' => 'search' ),
		'claude-user'          => array( 'name' => 'Claude-User', 'owner' => 'Anthropic', 'purpose' => 'user' ),
		'anthropic-ai'         => array( 'name' => 'anthropic-ai', 'owner' => 'Anthropic', 'purpose' => 'training' ),
		'perplexitybot'        => array( 'name' => 'PerplexityBot', 'owner' => 'Perplexity', 'purpose' => 'search' ),
		'perplexity-user'      => array( 'name' => 'Perplexity-User', 'owner' => 'Perplexity', 'purpose' => 'user' ),
		'google-extended'      => array( 'name' => 'Google-Extended', 'owner' => 'Google', 'purpose' => 'training', 'token_only' => true ),
		'google-cloudvertexbot' => array( 'name' => 'Google-CloudVertexBot', 'owner' => 'Google', 'purpose' => 'user' ),
		'applebot-extended'    => array( 'name' => 'Applebot-Extended', 'owner' => 'Apple', 'purpose' => 'training', 'token_only' => true ),
		'meta-externalagent'   => array( 'name' => 'meta-externalagent', 'owner' => 'Meta', 'purpose' => 'training' ),
		'meta-externalfetcher' => array( 'name' => 'meta-externalfetcher', 'owner' => 'Meta', 'purpose' => 'user' ),
		'facebookbot'          => array( 'name' => 'FacebookBot', 'owner' => 'Meta', 'purpose' => 'training' ),
		'amazonbot'            => array( 'name' => 'Amazonbot', 'owner' => 'Amazon', 'purpose' => 'search' ),
		'duckassistbot'        => array( 'name' => 'DuckAssistBot', 'owner' => 'DuckDuckGo', 'purpose' => 'search' ),
		'mistralai-user'       => array( 'name' => 'MistralAI-User', 'owner' => 'Mistral', 'purpose' => 'user' ),
		'bytespider'           => array( 'name' => 'Bytespider', 'owner' => 'ByteDance', 'purpose' => 'training' ),
		'ccbot'                => array( 'name' => 'CCBot', 'owner' => 'Common Crawl', 'purpose' => 'training' ),
		'cohere-ai'            => array( 'name' => 'cohere-ai', 'owner' => 'Cohere', 'purpose' => 'user' ),
		'cohere-training-data-crawler' => array( 'name' => 'cohere-training-data-crawler', 'owner' => 'Cohere', 'purpose' => 'training' ),
		'youbot'               => array( 'name' => 'YouBot', 'owner' => 'You.com', 'purpose' => 'search' ),
		'diffbot'              => array( 'name' => 'Diffbot', 'owner' => 'Diffbot', 'purpose' => 'training' ),
		'ai2bot'               => array( 'name' => 'AI2Bot', 'owner' => 'Allen Institute', 'purpose' => 'training' ),
		'timpibot'             => array( 'name' => 'Timpibot', 'owner' => 'Timpi', 'purpose' => 'training' ),
		'petalbot'             => array( 'name' => 'PetalBot', 'owner' => 'Huawei', 'purpose' => 'search' ),
		'img2dataset'          => array( 'name' => 'img2dataset', 'owner' => 'img2dataset', 'purpose' => 'training' ),
	);

	/**
	 * Hook up logging (front end only).
	 *
	 * @return void
	 */
	public static function register() {
		if ( Settings::get( 'traffic_logging', 1 ) ) {
			add_action( 'shutdown', array( __CLASS__, 'log_visit' ), 0 );
		}
	}

	/**
	 * Effective rule for every bot: allow|block.
	 *
	 * @return array<string, string>
	 */
	public static function rules() {
		$saved  = (array) Settings::get( 'crawler_settings', array() );
		$preset = (string) Settings::get( 'crawler_preset', 'custom' );
		$rules  = array();

		// 1.0.x used "googlebot_ai" for Google-Extended.
		if ( isset( $saved['googlebot_ai'] ) && ! isset( $saved['google-extended'] ) ) {
			$saved['google-extended'] = $saved['googlebot_ai'];
		}

		foreach ( self::BOTS as $key => $bot ) {
			switch ( $preset ) {
				case 'allow_all':
					$rules[ $key ] = 'allow';
					break;
				case 'block_training':
					$rules[ $key ] = 'training' === $bot['purpose'] ? 'block' : 'allow';
					break;
				case 'block_all':
					$rules[ $key ] = 'block';
					break;
				default:
					$rules[ $key ] = 'block' === ( $saved[ $key ] ?? 'allow' ) ? 'block' : 'allow';
			}
		}
		return $rules;
	}

	/**
	 * Bots with metadata and current rule, for the admin UI.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function for_admin() {
		$rules = self::rules();
		$out   = array();
		foreach ( self::BOTS as $key => $bot ) {
			$out[] = array_merge(
				$bot,
				array(
					'key'  => $key,
					'rule' => $rules[ $key ],
				)
			);
		}
		return $out;
	}

	/**
	 * robots.txt filter.
	 *
	 * Only blocked bots get their own group: a group like "User-agent: GPTBot / Allow: /"
	 * would make that bot ignore the site's "User-agent: *" rules.
	 *
	 * @param string $output Existing robots.txt.
	 * @param bool   $public Whether the site is public.
	 * @return string
	 */
	public static function filter_robots_txt( $output, $public ) {
		if ( ! $public || ! Settings::is_module_enabled( 'technical' ) ) {
			return $output;
		}

		$output = (string) $output;
		$signal = self::content_signal_line();
		if ( $signal ) {
			// Put Content-Signal inside the existing "User-agent: *" group when there is one.
			if ( preg_match( '/^User-agent:\s*\*\s*$/mi', $output ) ) {
				$output = preg_replace( '/^(User-agent:\s*\*\s*)$/mi', "$1\n" . $signal, $output, 1 );
			} else {
				$output = "User-agent: *\n" . $signal . "\n\n" . $output;
			}
		}

		$output  = rtrim( $output ) . "\n\n# Answer Engine Optimization\n";
		$output .= '# LLM-friendly site index: ' . home_url( '/llms.txt' ) . "\n";
		if ( Settings::get( 'llms_full', 1 ) ) {
			$output .= '# Full content for LLMs: ' . home_url( '/llms-full.txt' ) . "\n";
		}

		foreach ( self::rules() as $key => $rule ) {
			if ( 'block' === $rule ) {
				$output .= "\nUser-agent: " . self::BOTS[ $key ]['name'] . "\nDisallow: /\n";
			}
		}

		return $output;
	}

	/**
	 * Content-Signal directive (contentsignals.org), or '' when not configured.
	 *
	 * @return string
	 */
	public static function content_signal_line() {
		$signals = (array) Settings::get( 'content_signals', array() );
		$parts   = array();
		foreach ( array( 'search', 'ai-input', 'ai-train' ) as $signal ) {
			$value = $signals[ $signal ] ?? '';
			if ( 'yes' === $value || 'no' === $value ) {
				$parts[] = $signal . '=' . $value;
			}
		}
		return $parts ? 'Content-Signal: ' . implode( ', ', $parts ) : '';
	}

	/**
	 * Identify an AI bot from a User-Agent header.
	 *
	 * @param string $user_agent User agent.
	 * @return string Bot key or ''.
	 */
	public static function detect( $user_agent ) {
		$user_agent = strtolower( (string) $user_agent );
		if ( '' === $user_agent ) {
			return '';
		}
		foreach ( self::BOTS as $key => $bot ) {
			if ( ! empty( $bot['token_only'] ) ) {
				continue;
			}
			if ( false !== strpos( $user_agent, strtolower( $bot['name'] ) ) ) {
				return $key;
			}
		}
		return '';
	}

	/**
	 * Log a front-end request made by an AI crawler.
	 *
	 * @return void
	 */
	public static function log_visit() {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}
		$ua  = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
		$bot = self::detect( $ua );
		if ( '' === $bot ) {
			return;
		}

		$uri = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';
		\AEO\Services\Traffic_Log::insert(
			array(
				'type'   => 'bot',
				'agent'  => $bot,
				'source' => self::BOTS[ $bot ]['owner'],
				'url'    => $uri,
				'status' => (int) http_response_code(),
			)
		);
	}
}
