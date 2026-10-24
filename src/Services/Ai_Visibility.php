<?php
/**
 * Multi-engine AI visibility tracking.
 *
 * Asks real AI assistants (with web search turned on) each tracked prompt, using the
 * site owner's own API keys, and records:
 *  - whether the answer cites this site, and at which position among the sources,
 *  - whether the brand is mentioned in the answer text, and with what tone,
 *  - which competitors are cited or mentioned (share of voice).
 *
 * Engines: Perplexity (Sonar), ChatGPT (OpenAI Responses API + web_search),
 * Gemini (Google Search grounding), Claude (web_search tool).
 *
 * @package AEO
 */

namespace AEO\Services;

use AEO\Core\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * AI visibility scanner and reports.
 */
class Ai_Visibility {

	const STATE        = 'aeo_visibility_scan_state';
	const LOCK         = 'aeo_visibility_scan_lock'; // Processing lock (see lock()).
	const MAX_KEYWORDS = 25;

	const ENGINES = array(
		'perplexity' => array( 'label' => 'Perplexity', 'key' => 'perplexity_api_key' ),
		'openai'     => array( 'label' => 'ChatGPT', 'key' => 'openai_api_key' ),
		'gemini'     => array( 'label' => 'Gemini', 'key' => 'gemini_api_key' ),
		'anthropic'  => array( 'label' => 'Claude', 'key' => 'anthropic_api_key' ),
	);

	const POSITIVE = array( 'best', 'excellent', 'great', 'top', 'recommended', 'recommend', 'popular', 'highly rated', 'well-reviewed', 'friendly', 'clean', 'comfortable', 'beautiful', 'stunning', 'affordable', 'good value', 'trusted', 'reliable', 'favorite', 'favourite', 'outstanding', 'luxury', 'convenient', 'helpful', 'praised', 'loved' );
	const NEGATIVE = array( 'worst', 'bad', 'poor', 'dirty', 'noisy', 'overpriced', 'expensive', 'rude', 'complaint', 'complaints', 'avoid', 'disappointing', 'negative', 'scam', 'unreliable', 'outdated', 'mixed reviews', 'issues', 'problem', 'problems', 'closed' );

	/**
	 * Engines that have a key and are switched on.
	 *
	 * @return string[]
	 */
	public static function available_engines() {
		$chosen = Settings::get( 'citation_engines', array() );
		$chosen = is_array( $chosen ) && $chosen ? $chosen : array_keys( self::ENGINES );
		$out    = array();
		foreach ( self::ENGINES as $engine => $def ) {
			if ( in_array( $engine, $chosen, true ) && '' !== self::key( $engine ) ) {
				$out[] = $engine;
			}
		}
		return $out;
	}

	/**
	 * Decrypted key for an engine.
	 *
	 * @param string $engine Engine.
	 * @return string
	 */
	private static function key( $engine ) {
		return (string) Encryption::decrypt( Settings::get( self::ENGINES[ $engine ]['key'], '' ) );
	}

	/**
	 * Brand names to look for in answer text.
	 *
	 * @return string[]
	 */
	public static function brand_names() {
		$names    = array();
		$business = (array) Settings::get( 'business_info', array() );
		$names[]  = ! empty( $business['name'] ) ? $business['name'] : get_bloginfo( 'name' );
		foreach ( (array) Settings::get( 'brand_names', array() ) as $n ) {
			$names[] = $n;
		}
		foreach ( (array) get_option( 'aeo_local_locations', array() ) as $loc ) {
			if ( is_array( $loc ) && ! empty( $loc['name'] ) ) {
				$names[] = $loc['name'];
			}
		}
		$names = array_filter( array_map( 'trim', $names ), static function ( $n ) {
			return mb_strlen( $n ) >= 3;
		} );
		return array_values( array_unique( $names ) );
	}

	/* ------------------------------------------------------------------
	 * Scan lifecycle (chunked so it never hits PHP or HTTP timeouts)
	 * ------------------------------------------------------------------ */

	/**
	 * Start a new scan.
	 *
	 * @return array<string, mixed>|\WP_Error State.
	 */
	public function start() {
		$engines = self::available_engines();
		if ( ! $engines ) {
			return new \WP_Error( 'aeo_no_engines', __( 'Add at least one API key (Perplexity, OpenAI, Gemini or Anthropic) in Settings to track AI citations.', 'answer-engine-optimization' ) );
		}
		$keywords = array_slice( array_values( array_filter( (array) Settings::get( 'tracked_keywords', array() ) ) ), 0, self::MAX_KEYWORDS );
		if ( ! $keywords ) {
			return new \WP_Error( 'aeo_no_keywords', __( 'Add tracked prompts in Settings first.', 'answer-engine-optimization' ) );
		}

		$items = array();
		foreach ( $keywords as $keyword ) {
			foreach ( $engines as $engine ) {
				$items[] = array( $keyword, $engine );
			}
		}

		$state = array(
			'scan_id'  => wp_generate_password( 12, false ),
			'items'    => $items,
			'done'     => 0,
			'started'  => current_time( 'mysql' ),
			'finished' => '',
			'engines'  => $engines,
		);
		update_option( self::STATE, $state, false );
		return $state;
	}

	/**
	 * Current state.
	 *
	 * @return array<string, mixed>
	 */
	public function state() {
		$state = get_option( self::STATE, array() );
		return is_array( $state ) ? $state : array();
	}

	/**
	 * Process items until the time budget runs out.
	 *
	 * @param int $budget Seconds.
	 * @return array<string, mixed> Progress.
	 */
	public function process( $budget = 20 ) {
		$state = $this->state();
		if ( empty( $state['items'] ) || ! empty( $state['finished'] ) ) {
			return $this->progress( $state );
		}

		// Each check is a live web search that can take a minute; never let PHP's
		// max_execution_time (often 30 s on local servers) kill the request mid-check.
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( (int) $budget + 150 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}
		ignore_user_abort( true );

		// Only one request may work on a scan at a time. Otherwise a request that keeps
		// running after its tab closed and a "Resume" from another tab check the same
		// questions twice. Others just report progress and the browser polls again.
		if ( ! $this->lock( (int) $budget + 150 ) ) {
			return $this->progress( $state ) + array( 'busy' => true );
		}

		$start = microtime( true );
		$total = count( $state['items'] );

		while ( $state['done'] < $total && ( microtime( true ) - $start ) < $budget ) {
			list( $keyword, $engine ) = $state['items'][ $state['done'] ];
			$this->check( $state['scan_id'], $keyword, $engine );
			$state['done']++;
			// A new scan was started meanwhile: never overwrite it with this one.
			wp_cache_delete( self::STATE, 'options' );
			$current = $this->state();
			if ( ( $current['scan_id'] ?? '' ) !== $state['scan_id'] ) {
				$this->unlock();
				return $this->progress( $current );
			}
			update_option( self::STATE, $state, false );
		}
		$this->unlock();

		if ( $state['done'] >= $total ) {
			$state['finished'] = current_time( 'mysql' );
			update_option( self::STATE, $state, false );
			update_option( Citation_Service::LAST_SCAN, $this->scan_summary( $state['scan_id'] ), false );
		}

		return $this->progress( $state );
	}

	/**
	 * Take the processing lock. add_option() is atomic (unique option_name), so two
	 * requests cannot both get it; a lock left by a killed request expires.
	 *
	 * @param int $ttl Seconds.
	 * @return bool
	 */
	private function lock( $ttl ) {
		if ( add_option( self::LOCK, time() + $ttl, '', 'no' ) ) {
			return true;
		}
		$expires = (int) get_option( self::LOCK, 0 );
		if ( $expires && $expires < time() ) {
			delete_option( self::LOCK );
			return (bool) add_option( self::LOCK, time() + $ttl, '', 'no' );
		}
		return false;
	}

	/**
	 * Release the processing lock.
	 *
	 * @return void
	 */
	private function unlock() {
		delete_option( self::LOCK );
	}

	/**
	 * Run a whole scan synchronously (WP-Cron / WP-CLI).
	 *
	 * @return array<string, mixed>|\WP_Error
	 */
	public function run_all() {
		$state = $this->start();
		if ( is_wp_error( $state ) ) {
			return $state;
		}
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 0 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}
		$rounds = 0;
		do {
			$progress = $this->process( 300 );
			if ( ! empty( $progress['busy'] ) ) {
				sleep( 5 ); // Another request holds the lock; wait for it.
			}
		} while ( empty( $progress['finished'] ) && ++$rounds < 200 );
		return $this->scan_summary( $state['scan_id'] );
	}

	/**
	 * Progress payload.
	 *
	 * @param array<string, mixed> $state State.
	 * @return array<string, mixed>
	 */
	private function progress( array $state ) {
		return array(
			'scan_id'  => $state['scan_id'] ?? '',
			'total'    => isset( $state['items'] ) ? count( $state['items'] ) : 0,
			'done'     => (int) ( $state['done'] ?? 0 ),
			'finished' => (string) ( $state['finished'] ?? '' ),
			'started'  => (string) ( $state['started'] ?? '' ),
		);
	}

	/* ------------------------------------------------------------------
	 * One check
	 * ------------------------------------------------------------------ */

	/**
	 * Ask one engine one prompt and store the analysed answer.
	 *
	 * @param string $scan_id Scan ID.
	 * @param string $keyword Prompt.
	 * @param string $engine  Engine.
	 * @return array<string, mixed>
	 */
	public function check( $scan_id, $keyword, $engine ) {
		$answer = $this->ask( $engine, $keyword );
		$row    = array(
			'scan_id'     => $scan_id,
			'keyword'     => $keyword,
			'engine'      => $engine,
			'cited'       => 0,
			'mentioned'   => 0,
			'position'    => 0,
			'sentiment'   => '',
			'cited_urls'  => '[]',
			'sources'     => '[]',
			'competitors' => '{}',
			'answer'      => '',
			'error'       => '',
			'created_at'  => current_time( 'mysql' ),
		);

		if ( is_wp_error( $answer ) ) {
			$row['error'] = mb_substr( $answer->get_error_message(), 0, 250 );
		} else {
			$analysis = self::analyse( $answer['text'], $answer['sources'] );
			$row      = array_merge(
				$row,
				array(
					'cited'       => $analysis['cited'] ? 1 : 0,
					'mentioned'   => $analysis['mentioned'] ? 1 : 0,
					'position'    => $analysis['position'],
					'sentiment'   => $analysis['sentiment'],
					'cited_urls'  => wp_json_encode( $analysis['cited_urls'] ),
					'sources'     => wp_json_encode( array_slice( $answer['sources'], 0, 20 ) ),
					'competitors' => wp_json_encode( $analysis['competitors'] ),
					'answer'      => mb_substr( (string) $answer['text'], 0, 8000 ),
				)
			);

			// Keep the simple citations log used for the trend chart.
			global $wpdb;
			foreach ( $analysis['cited_urls'] as $url ) {
				$wpdb->insert(
					$wpdb->prefix . 'aeo_citations',
					array(
						'keyword'      => $keyword,
						'page_url'     => $url,
						'engine'       => $engine,
						'citation_url' => $url,
						'detected_at'  => current_time( 'mysql' ),
					),
					array( '%s', '%s', '%s', '%s', '%s' )
				);
			}
		}

		global $wpdb;
		$wpdb->insert( $wpdb->prefix . 'aeo_ai_answers', $row );
		return $row;
	}

	/**
	 * Analyse an answer.
	 *
	 * @param string                                   $text    Answer text.
	 * @param array<int, array{url: string, title?: string}> $sources Sources in order.
	 * @return array<string, mixed>
	 */
	public static function analyse( $text, array $sources ) {
		$own_host = self::own_host();
		$hosts    = array();
		foreach ( $sources as $source ) {
			$h = self::source_host( $source );
			if ( $h && ! in_array( $h, $hosts, true ) ) {
				$hosts[] = $h;
			}
		}

		$cited_urls = array();
		foreach ( $sources as $source ) {
			if ( self::source_host( $source ) === $own_host ) {
				$cited_urls[] = $source['url'];
			}
		}
		$position = array_search( $own_host, $hosts, true );

		$mentioned = false;
		$sentiment = '';
		foreach ( self::brand_names() as $name ) {
			if ( false !== mb_stripos( $text, $name ) ) {
				$mentioned = true;
				$sentiment = self::sentiment_around( $text, $name );
				break;
			}
		}

		$competitors = array();
		foreach ( (array) Settings::get( 'competitors', array() ) as $domain ) {
			$domain = Settings::normalize_domain( $domain );
			if ( ! $domain ) {
				continue;
			}
			$root                   = explode( '.', $domain )[0];
			$competitors[ $domain ] = array(
				'cited'     => in_array( $domain, $hosts, true ),
				'mentioned' => mb_strlen( $root ) >= 4 && false !== mb_stripos( $text, $root ),
			);
		}

		return array(
			'cited'       => ! empty( $cited_urls ),
			'cited_urls'  => array_values( array_unique( $cited_urls ) ),
			'position'    => false === $position ? 0 : $position + 1,
			'mentioned'   => $mentioned,
			'sentiment'   => $sentiment,
			'competitors' => $competitors,
		);
	}

	/**
	 * Lexicon-based tone of the sentences that mention the brand.
	 *
	 * @param string $text Text.
	 * @param string $name Brand name.
	 * @return string positive|neutral|negative
	 */
	public static function sentiment_around( $text, $name ) {
		$sentences = preg_split( '/(?<=[.!?\n])\s+/u', (string) $text );
		$score     = 0;
		foreach ( $sentences as $sentence ) {
			if ( false === mb_stripos( $sentence, $name ) ) {
				continue;
			}
			$lower = mb_strtolower( $sentence );
			foreach ( self::POSITIVE as $w ) {
				if ( preg_match( '/\b' . preg_quote( $w, '/' ) . '\b/u', $lower ) ) {
					$score++;
				}
			}
			foreach ( self::NEGATIVE as $w ) {
				if ( preg_match( '/\b' . preg_quote( $w, '/' ) . '\b/u', $lower ) ) {
					$score--;
				}
			}
		}
		return $score > 0 ? 'positive' : ( $score < 0 ? 'negative' : 'neutral' );
	}

	/**
	 * Domain counted as "you": the visibility_domain setting (for local/staging copies
	 * of a live site) or this site's host.
	 *
	 * @return string
	 */
	public static function own_host() {
		$domain = (string) Settings::get( 'visibility_domain', '' );
		$host   = '' !== $domain ? $domain : self::host( home_url() );
		return preg_replace( '/^www\./', '', strtolower( $host ) );
	}

	/**
	 * Is the site address one AI engines can never link to (localhost, .test, .local…)?
	 *
	 * @return bool
	 */
	public static function site_is_local() {
		return \AEO\Services\IndexNow::is_local();
	}

	/**
	 * Host of a URL without www.
	 *
	 * @param string $url URL.
	 * @return string
	 */
	public static function host( $url ) {
		return preg_replace( '/^www\./', '', strtolower( (string) wp_parse_url( (string) $url, PHP_URL_HOST ) ) );
	}

	/**
	 * Host of a source. Gemini grounding links are redirects; their title holds the domain.
	 *
	 * @param array<string, string> $source Source.
	 * @return string
	 */
	private static function source_host( array $source ) {
		$host = self::host( $source['url'] ?? '' );
		if ( ( '' === $host || false !== strpos( $host, 'vertexaisearch.cloud.google.com' ) ) && ! empty( $source['title'] ) && preg_match( '/^[a-z0-9.-]+\.[a-z]{2,}$/i', trim( $source['title'] ) ) ) {
			return preg_replace( '/^www\./', '', strtolower( trim( $source['title'] ) ) );
		}
		return $host;
	}

	/* ------------------------------------------------------------------
	 * Engines
	 * ------------------------------------------------------------------ */

	/**
	 * Ask an engine.
	 *
	 * @param string $engine Engine.
	 * @param string $prompt Prompt.
	 * @return array{text: string, sources: array<int, array{url: string, title: string}>}|\WP_Error
	 */
	public function ask( $engine, $prompt ) {
		$key = self::key( $engine );
		if ( '' === $key ) {
			return new \WP_Error( 'aeo_no_key', __( 'No API key', 'answer-engine-optimization' ) );
		}
		$result = apply_filters( 'aeo_visibility_pre_ask', null, $engine, $prompt );
		if ( null !== $result ) {
			return $result;
		}
		switch ( $engine ) {
			case 'perplexity':
				return $this->ask_perplexity( $key, $prompt );
			case 'openai':
				return $this->ask_openai( $key, $prompt );
			case 'gemini':
				return $this->ask_gemini( $key, $prompt );
			case 'anthropic':
				return $this->ask_anthropic( $key, $prompt );
		}
		return new \WP_Error( 'aeo_engine', 'Unknown engine' );
	}

	/**
	 * POST JSON and decode.
	 *
	 * @param string               $url     URL.
	 * @param array<string, mixed> $body    Body.
	 * @param array<string, string> $headers Headers.
	 * @return array<string, mixed>|\WP_Error
	 */
	private function post( $url, array $body, array $headers ) {
		$response = wp_remote_post(
			$url,
			array(
				'timeout' => (int) apply_filters( 'aeo_visibility_timeout', 120 ),
				'headers' => array_merge( array( 'Content-Type' => 'application/json' ), $headers ),
				'body'    => wp_json_encode( $body ),
			)
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( $code < 200 || $code >= 300 || ! is_array( $data ) ) {
			$msg = is_array( $data ) ? (string) ( $data['error']['message'] ?? ( $data['message'] ?? '' ) ) : '';
			return new \WP_Error( 'aeo_http_' . $code, trim( sprintf( 'HTTP %d %s', $code, $msg ) ) );
		}
		return $data;
	}

	/**
	 * Perplexity Sonar.
	 *
	 * @param string $key    Key.
	 * @param string $prompt Prompt.
	 * @return array<string, mixed>|\WP_Error
	 */
	private function ask_perplexity( $key, $prompt ) {
		$data = $this->post(
			Citation_Service::ENDPOINT,
			array(
				'model'    => Settings::get_model( 'perplexity' ),
				'messages' => array(
					array(
						'role'    => 'user',
						'content' => $prompt,
					),
				),
			),
			array( 'Authorization' => 'Bearer ' . $key )
		);
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		$text = (string) ( $data['choices'][0]['message']['content'] ?? ( $data['output_text'] ?? '' ) );
		if ( '' === $text ) {
			foreach ( (array) ( $data['output'] ?? array() ) as $item ) {
				foreach ( (array) ( $item['content'] ?? array() ) as $c ) {
					$text .= (string) ( $c['text'] ?? '' );
				}
			}
		}
		$sources = array();
		foreach ( Citation_Service::extract_source_urls( $data ) as $url ) {
			$sources[] = array(
				'url'   => $url,
				'title' => '',
			);
		}
		return array(
			'text'    => $text,
			'sources' => $sources,
		);
	}

	/**
	 * OpenAI Responses API with the web_search tool.
	 *
	 * @param string $key    Key.
	 * @param string $prompt Prompt.
	 * @return array<string, mixed>|\WP_Error
	 */
	private function ask_openai( $key, $prompt ) {
		$data = $this->post(
			'https://api.openai.com/v1/responses',
			array(
				'model' => (string) apply_filters( 'aeo_visibility_model', Settings::get_model( 'openai' ), 'openai' ),
				'input' => $prompt,
				'tools' => array( array( 'type' => (string) apply_filters( 'aeo_openai_web_search_tool', 'web_search' ) ) ),
			),
			array( 'Authorization' => 'Bearer ' . $key )
		);
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		$text    = '';
		$sources = array();
		foreach ( (array) ( $data['output'] ?? array() ) as $item ) {
			if ( 'message' === ( $item['type'] ?? '' ) ) {
				foreach ( (array) ( $item['content'] ?? array() ) as $c ) {
					$text .= (string) ( $c['text'] ?? '' );
					foreach ( (array) ( $c['annotations'] ?? array() ) as $a ) {
						if ( 'url_citation' === ( $a['type'] ?? '' ) && ! empty( $a['url'] ) ) {
							$sources[] = array(
								'url'   => (string) $a['url'],
								'title' => (string) ( $a['title'] ?? '' ),
							);
						}
					}
				}
			} elseif ( 'web_search_call' === ( $item['type'] ?? '' ) ) {
				foreach ( (array) ( $item['action']['sources'] ?? array() ) as $src ) {
					if ( ! empty( $src['url'] ) ) {
						$sources[] = array(
							'url'   => (string) $src['url'],
							'title' => '',
						);
					}
				}
			}
		}
		return array(
			'text'    => $text,
			'sources' => self::unique_sources( $sources ),
		);
	}

	/**
	 * Gemini with Google Search grounding.
	 *
	 * @param string $key    Key.
	 * @param string $prompt Prompt.
	 * @return array<string, mixed>|\WP_Error
	 */
	private function ask_gemini( $key, $prompt ) {
		$model = (string) apply_filters( 'aeo_visibility_model', Settings::get_model( 'gemini' ), 'gemini' );
		$data  = $this->post(
			'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode( $model ) . ':generateContent',
			array(
				'contents' => array(
					array(
						'role'  => 'user',
						'parts' => array( array( 'text' => $prompt ) ),
					),
				),
				'tools'    => array( array( 'google_search' => new \stdClass() ) ),
			),
			array( 'x-goog-api-key' => $key )
		);
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		$text = '';
		foreach ( (array) ( $data['candidates'][0]['content']['parts'] ?? array() ) as $part ) {
			$text .= (string) ( $part['text'] ?? '' );
		}
		$sources = array();
		foreach ( (array) ( $data['candidates'][0]['groundingMetadata']['groundingChunks'] ?? array() ) as $chunk ) {
			if ( ! empty( $chunk['web']['uri'] ) ) {
				$sources[] = array(
					'url'   => (string) $chunk['web']['uri'],
					'title' => (string) ( $chunk['web']['title'] ?? '' ),
				);
			}
		}
		return array(
			'text'    => $text,
			'sources' => self::unique_sources( $sources ),
		);
	}

	/**
	 * Claude with the server-side web search tool.
	 *
	 * @param string $key    Key.
	 * @param string $prompt Prompt.
	 * @return array<string, mixed>|\WP_Error
	 */
	private function ask_anthropic( $key, $prompt ) {
		$data = $this->post(
			'https://api.anthropic.com/v1/messages',
			array(
				'model'      => (string) apply_filters( 'aeo_visibility_model', Settings::get_model( 'anthropic' ), 'anthropic' ),
				'max_tokens' => 2048,
				'messages'   => array(
					array(
						'role'    => 'user',
						'content' => $prompt,
					),
				),
				'tools'      => array(
					array(
						'type'     => (string) apply_filters( 'aeo_anthropic_web_search_tool', 'web_search_20250305' ),
						'name'     => 'web_search',
						'max_uses' => 3,
					),
				),
			),
			array(
				'x-api-key'         => $key,
				'anthropic-version' => '2023-06-01',
			)
		);
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		$text    = '';
		$sources = array();
		foreach ( (array) ( $data['content'] ?? array() ) as $block ) {
			$type = $block['type'] ?? '';
			if ( 'text' === $type ) {
				$text .= (string) ( $block['text'] ?? '' );
				foreach ( (array) ( $block['citations'] ?? array() ) as $c ) {
					if ( ! empty( $c['url'] ) ) {
						$sources[] = array(
							'url'   => (string) $c['url'],
							'title' => (string) ( $c['title'] ?? '' ),
						);
					}
				}
			} elseif ( 'web_search_tool_result' === $type ) {
				foreach ( (array) ( $block['content'] ?? array() ) as $r ) {
					if ( ! empty( $r['url'] ) ) {
						$sources[] = array(
							'url'   => (string) $r['url'],
							'title' => (string) ( $r['title'] ?? '' ),
						);
					}
				}
			}
		}
		return array(
			'text'    => $text,
			'sources' => self::unique_sources( $sources ),
		);
	}

	/**
	 * De-duplicate sources by URL, keeping order.
	 *
	 * @param array<int, array<string, string>> $sources Sources.
	 * @return array<int, array<string, string>>
	 */
	private static function unique_sources( array $sources ) {
		$seen = array();
		$out  = array();
		foreach ( $sources as $s ) {
			if ( isset( $seen[ $s['url'] ] ) ) {
				continue;
			}
			$seen[ $s['url'] ] = true;
			$out[]             = $s;
		}
		return $out;
	}

	/* ------------------------------------------------------------------
	 * Reports
	 * ------------------------------------------------------------------ */

	/**
	 * Latest finished scan ID.
	 *
	 * @return string
	 */
	public function latest_scan_id() {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return (string) $wpdb->get_var( "SELECT scan_id FROM {$wpdb->prefix}aeo_ai_answers ORDER BY id DESC LIMIT 1" );
	}

	/**
	 * Summary of one scan: per engine, per keyword, share of voice, sentiment.
	 *
	 * @param string $scan_id Scan ID ('' = latest).
	 * @return array<string, mixed>
	 */
	public function scan_summary( $scan_id = '' ) {
		global $wpdb;
		$scan_id = $scan_id ? $scan_id : $this->latest_scan_id();
		if ( '' === $scan_id ) {
			return array();
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}aeo_ai_answers WHERE scan_id = %s ORDER BY id ASC", $scan_id ), ARRAY_A );

		$engines   = array();
		$keywords  = array();
		$sov       = array( 'you' => 0 );
		$sentiment = array(
			'positive' => 0,
			'neutral'  => 0,
			'negative' => 0,
		);
		$checked   = 0;
		$cited     = 0;
		$mentioned = 0;
		$scanned   = '';

		foreach ( $rows as $r ) {
			$scanned = $r['created_at'];
			$e       = $r['engine'];
			if ( ! isset( $engines[ $e ] ) ) {
				$engines[ $e ] = array(
					'engine'    => $e,
					'label'     => self::ENGINES[ $e ]['label'] ?? $e,
					'checked'   => 0,
					'cited'     => 0,
					'mentioned' => 0,
					'errors'    => 0,
					'error'     => '',
					'positions' => array(),
				);
			}
			if ( '' !== $r['error'] ) {
				$engines[ $e ]['errors']++;
				$engines[ $e ]['error'] = (string) $r['error'];
			} else {
				$engines[ $e ]['checked']++;
				$checked++;
				if ( (int) $r['cited'] ) {
					$engines[ $e ]['cited']++;
					$cited++;
					$sov['you']++;
					if ( (int) $r['position'] ) {
						$engines[ $e ]['positions'][] = (int) $r['position'];
					}
				}
				if ( (int) $r['mentioned'] ) {
					$engines[ $e ]['mentioned']++;
					$mentioned++;
					if ( isset( $sentiment[ $r['sentiment'] ] ) ) {
						$sentiment[ $r['sentiment'] ]++;
					}
				}
				foreach ( (array) json_decode( $r['competitors'], true ) as $domain => $c ) {
					if ( ! isset( $sov[ $domain ] ) ) {
						$sov[ $domain ] = 0;
					}
					if ( ! empty( $c['cited'] ) ) {
						$sov[ $domain ]++;
					}
				}
			}

			$keywords[ $r['keyword'] ][ $e ] = array(
				'cited'     => (bool) $r['cited'],
				'mentioned' => (bool) $r['mentioned'],
				'position'  => (int) $r['position'],
				'sentiment' => $r['sentiment'],
				'urls'      => (array) json_decode( $r['cited_urls'], true ),
				'sources'   => array_slice(
					array_values(
						array_unique(
							array_map(
								static function ( $s ) {
									return self::source_host( (array) $s );
								},
								(array) json_decode( $r['sources'], true )
							)
						)
					),
					0,
					6
				),
				'error'     => $r['error'],
				'id'        => (int) $r['id'],
			);
		}

		foreach ( $engines as &$eng ) {
			$eng['avg_position'] = $eng['positions'] ? round( array_sum( $eng['positions'] ) / count( $eng['positions'] ), 1 ) : null;
			unset( $eng['positions'] );
		}
		unset( $eng );

		$sov_total = array_sum( $sov );
		$sov_rows  = array();
		foreach ( $sov as $name => $count ) {
			$sov_rows[] = array(
				'name'    => 'you' === $name ? self::own_host() : $name,
				'is_you'  => 'you' === $name,
				'cited'   => $count,
				'percent' => $sov_total ? (int) round( 100 * $count / $sov_total ) : 0,
			);
		}

		$keyword_rows = array();
		foreach ( $keywords as $k => $by_engine ) {
			$keyword_rows[] = array(
				'keyword' => $k,
				'engines' => $by_engine,
			);
		}

		return array(
			'scan_id'       => $scan_id,
			'scanned_at'    => $scanned,
			'checked'       => $checked,
			'cited'         => $cited,
			'mentioned'     => $mentioned,
			'engines'       => array_values( $engines ),
			'keywords'      => $keyword_rows,
			'share_of_voice' => $sov_rows,
			'sentiment'     => $sentiment,
			// Back-compat with 1.1 dashboard fields.
			'results'       => array_map(
				static function ( $row ) {
					$any = array_filter( $row['engines'], static function ( $e ) {
						return $e['cited'];
					} );
					$first = $any ? reset( $any ) : reset( $row['engines'] );
					return array(
						'keyword' => $row['keyword'],
						'cited'   => (bool) $any,
						'urls'    => $first ? $first['urls'] : array(),
						'sources' => $first ? $first['sources'] : array(),
						'error'   => $first ? $first['error'] : '',
					);
				},
				$keyword_rows
			),
		);
	}

	/**
	 * Cited/mentioned rate per scan, for the trend chart.
	 *
	 * @param int $limit Scans.
	 * @return array<int, array<string, mixed>>
	 */
	public function history( $limit = 12 ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT scan_id, MIN(created_at) AS date, SUM(error = '') AS checked, SUM(cited) AS cited, SUM(mentioned) AS mentioned FROM {$wpdb->prefix}aeo_ai_answers GROUP BY scan_id ORDER BY MIN(id) DESC LIMIT %d", $limit ), ARRAY_A );
		$out  = array();
		foreach ( array_reverse( (array) $rows ) as $r ) {
			$checked = max( 1, (int) $r['checked'] );
			$out[]   = array(
				'scan_id'        => $r['scan_id'],
				'date'           => substr( $r['date'], 0, 10 ),
				'cited_rate'     => (int) round( 100 * (int) $r['cited'] / $checked ),
				'mentioned_rate' => (int) round( 100 * (int) $r['mentioned'] / $checked ),
			);
		}
		return $out;
	}

	/**
	 * One stored answer (for the "view answer" drawer).
	 *
	 * @param int $id Row ID.
	 * @return array<string, mixed>|null
	 */
	public function answer( $id ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}aeo_ai_answers WHERE id = %d", $id ), ARRAY_A );
		if ( ! $row ) {
			return null;
		}
		$row['sources']     = json_decode( $row['sources'], true );
		$row['cited_urls']  = json_decode( $row['cited_urls'], true );
		$row['competitors'] = json_decode( $row['competitors'], true );
		return $row;
	}
}
