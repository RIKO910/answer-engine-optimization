<?php
/**
 * llms.txt, llms-full.txt and Markdown versions of pages.
 *
 * - /llms.txt         index of public content (llmstxt.org format)
 * - /llms-full.txt    the same content in full, as Markdown
 * - /any-page.md      Markdown version of any public post or page
 * - Accept: text/markdown on a normal URL returns the Markdown version
 *
 * @package AEO
 */

namespace AEO\Frontend;

use AEO\Core\Settings;
use AEO\Services\Seo_Compat;
use AEO\Support\Html_To_Markdown;
use AEO\Support\Lang;

defined( 'ABSPATH' ) || exit;

/**
 * LLM-oriented text endpoints.
 */
class Llms_Txt {

	const CACHE_INDEX = 'aeo_llms_txt';
	const CACHE_FULL  = 'aeo_llms_full_txt';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function register() {
		add_action( 'template_redirect', array( __CLASS__, 'serve' ), 0 );
		add_action( 'wp_head', array( __CLASS__, 'alternate_link' ), 3 );
	}

	/**
	 * Rewrite rules (called on every request from Plugin::register_rewrites()).
	 *
	 * @return void
	 */
	public static function rewrites() {
		add_rewrite_rule( '^llms\.txt$', 'index.php?aeo_llms_txt=index', 'top' );
		add_rewrite_rule( '^llms-full\.txt$', 'index.php?aeo_llms_txt=full', 'top' );
		add_rewrite_rule( '^index\.md$', 'index.php?aeo_md=__home__', 'top' );
		add_rewrite_rule( '^(.+?)\.md$', 'index.php?aeo_md=$matches[1]', 'top' );
	}

	/**
	 * Clear caches.
	 *
	 * @return void
	 */
	public static function flush() {
		delete_transient( self::CACHE_INDEX );
		delete_transient( self::CACHE_FULL );
	}

	/**
	 * Is Markdown output enabled?
	 *
	 * @return bool
	 */
	public static function markdown_enabled() {
		return Settings::is_module_enabled( 'technical' ) && (bool) Settings::get( 'markdown_pages', 1 );
	}

	/**
	 * Route the request.
	 *
	 * @return void
	 */
	public static function serve() {
		$llms = get_query_var( 'aeo_llms_txt' );
		if ( $llms ) {
			if ( ! Settings::is_module_enabled( 'technical' ) || ( 'full' === $llms && ! Settings::get( 'llms_full', 1 ) ) ) {
				self::not_found();
			}
			$full  = 'full' === $llms;
			$key   = $full ? self::CACHE_FULL : self::CACHE_INDEX;
			$body  = get_transient( $key );
			if ( false === $body ) {
				$body = $full ? self::build_full() : self::build_index();
				set_transient( $key, $body, DAY_IN_SECONDS );
			}
			self::send( $body, 'text/plain' );
		}

		$md_path = get_query_var( 'aeo_md' );
		if ( $md_path ) {
			if ( ! self::markdown_enabled() ) {
				self::not_found();
			}
			if ( '1' === (string) $md_path && is_singular() ) {
				$post_id = get_queried_object_id();
			} elseif ( '__home__' === $md_path ) {
				$post_id = (int) get_option( 'page_on_front' );
			} else {
				$post_id = url_to_postid( home_url( user_trailingslashit( $md_path ) ) );
			}
			if ( ! $post_id && '1' !== (string) $md_path ) {
				$post_id = url_to_postid( home_url( '/' . $md_path ) );
			}
			if ( ! $post_id || ! self::is_public( $post_id ) ) {
				self::not_found();
			}
			self::send( self::post_markdown( $post_id ), 'text/markdown' );
		}

		// Content negotiation: Accept: text/markdown on a normal page URL.
		if ( self::markdown_enabled() && is_singular() && self::wants_markdown() ) {
			$post_id = get_queried_object_id();
			if ( self::is_public( $post_id ) ) {
				header( 'Vary: Accept' );
				self::send( self::post_markdown( $post_id ), 'text/markdown' );
			}
		}
	}

	/**
	 * Does the client prefer Markdown over HTML?
	 *
	 * @return bool
	 */
	private static function wants_markdown() {
		$accept = isset( $_SERVER['HTTP_ACCEPT'] ) ? strtolower( sanitize_text_field( wp_unslash( $_SERVER['HTTP_ACCEPT'] ) ) ) : '';
		if ( false === strpos( $accept, 'text/markdown' ) ) {
			return false;
		}
		$md   = strpos( $accept, 'text/markdown' );
		$html = strpos( $accept, 'text/html' );
		return false === $html || $md < $html;
	}

	/**
	 * <link rel="alternate" type="text/markdown"> on singular pages.
	 *
	 * @return void
	 */
	public static function alternate_link() {
		if ( ! self::markdown_enabled() || ! is_singular() ) {
			return;
		}
		$post_id = get_queried_object_id();
		if ( self::is_public( $post_id ) ) {
			echo '<link rel="alternate" type="text/markdown" href="' . esc_url( self::markdown_url( $post_id ) ) . '" />' . "\n";
		}
	}

	/**
	 * Markdown URL for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	public static function markdown_url( $post_id ) {
		if ( (int) get_option( 'page_on_front' ) === (int) $post_id ) {
			return home_url( '/index.md' );
		}
		$url = untrailingslashit( get_permalink( $post_id ) );
		if ( false !== strpos( $url, '?' ) ) {
			return add_query_arg( 'aeo_md', '1', get_permalink( $post_id ) );
		}
		return $url . '.md';
	}

	/**
	 * Whether a post can be exposed to LLMs.
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public static function is_public( $post_id ) {
		$post = get_post( $post_id );
		return $post
			&& 'publish' === $post->post_status
			&& '' === $post->post_password
			&& in_array( $post->post_type, self::post_types(), true )
			&& ! Seo_Compat::is_noindex( $post_id );
	}

	/**
	 * Post types included (setting, default: all public types).
	 *
	 * @return string[]
	 */
	public static function post_types() {
		$all     = Settings::get_post_types();
		$chosen  = Settings::get( 'llms_post_types', array() );
		$chosen  = is_array( $chosen ) ? array_values( array_intersect( $chosen, $all ) ) : array();
		return $chosen ? $chosen : $all;
	}

	/**
	 * Posts to list, grouped by post type.
	 *
	 * @param int $limit Per post type.
	 * @return array<string, \WP_Post[]>
	 */
	private static function posts_by_type( $limit ) {
		$groups = array();
		foreach ( self::post_types() as $post_type ) {
			$posts = get_posts(
				array(
					'post_type'        => $post_type,
					'post_status'      => 'publish',
					'posts_per_page'   => $limit,
					'orderby'          => 'page' === $post_type ? 'menu_order title' : 'modified',
					'order'            => 'page' === $post_type ? 'ASC' : 'DESC',
					'has_password'     => false,
					'suppress_filters' => false,
					'lang'             => '', // Polylang: all languages.
				)
			);
			$posts = array_values(
				array_filter(
					$posts,
					static function ( $p ) {
						return ! Seo_Compat::is_noindex( $p->ID );
					}
				)
			);
			if ( $posts ) {
				$groups[ $post_type ] = $posts;
			}
		}
		return $groups;
	}

	/**
	 * One-line clean text.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	private static function clean( $text ) {
		return trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( (string) $text ) ) );
	}

	/**
	 * Header shared by both files.
	 *
	 * @return string
	 */
	private static function header() {
		$business = Settings::get( 'business_info', array() );
		$name     = ! empty( $business['name'] ) ? $business['name'] : get_bloginfo( 'name' );
		$summary  = ! empty( $business['description'] ) ? $business['description'] : get_bloginfo( 'description' );

		$out = '# ' . self::clean( $name ) . "\n\n";
		if ( $summary ) {
			$out .= '> ' . self::clean( $summary ) . "\n\n";
		}

		$intro = trim( (string) Settings::get( 'llms_intro', '' ) );
		if ( '' !== $intro ) {
			$out .= $intro . "\n\n";
		}

		$links = array( '- Website: ' . home_url( '/' ) );
		if ( Settings::get( 'llms_full', 1 ) ) {
			$links[] = '- Full content: ' . home_url( '/llms-full.txt' );
		}
		$links[] = '- Structured data (JSON-LD): ' . rest_url( 'aeo-genius/v1/schemamap' );
		if ( self::markdown_enabled() ) {
			$links[] = '- Any page as Markdown: add .md to its URL';
		}
		return $out . implode( "\n", $links ) . "\n\n";
	}

	/**
	 * Section heading for a post type, split by language when multilingual.
	 *
	 * @param string    $post_type Post type.
	 * @param \WP_Post[] $posts    Posts.
	 * @return array<string, \WP_Post[]> heading => posts
	 */
	private static function sections( $post_type, array $posts ) {
		$object = get_post_type_object( $post_type );
		$label  = $object ? $object->labels->name : $post_type;

		if ( '' === Lang::provider() ) {
			return array( $label => $posts );
		}

		$by_lang = array();
		foreach ( $posts as $post ) {
			$by_lang[ Lang::post_language( $post->ID ) ][] = $post;
		}
		if ( count( $by_lang ) < 2 ) {
			return array( $label => $posts );
		}

		$out = array();
		foreach ( $by_lang as $tag => $list ) {
			$out[ $label . ' (' . $tag . ')' ] = $list;
		}
		return $out;
	}

	/**
	 * Short description for a post.
	 *
	 * @param \WP_Post $post Post.
	 * @return string
	 */
	private static function describe( \WP_Post $post ) {
		$desc = has_excerpt( $post ) ? get_the_excerpt( $post ) : get_post_meta( $post->ID, '_aeo_direct_answer', true );
		return wp_html_excerpt( self::clean( $desc ), 200, '…' );
	}

	/**
	 * Build /llms.txt.
	 *
	 * @return string
	 */
	public static function build_index() {
		$limit = (int) Settings::get( 'llms_limit', 100 );
		$limit = (int) apply_filters( 'aeo_llms_txt_limit', $limit > 0 ? $limit : 100 );
		$out   = self::header();

		foreach ( self::posts_by_type( $limit ) as $post_type => $posts ) {
			foreach ( self::sections( $post_type, $posts ) as $heading => $list ) {
				$lines = array();
				foreach ( $list as $post ) {
					$title = self::clean( get_the_title( $post ) );
					if ( '' === $title ) {
						continue;
					}
					$url  = self::markdown_enabled() ? self::markdown_url( $post->ID ) : get_permalink( $post );
					$line = '- [' . str_replace( array( '[', ']' ), '', $title ) . '](' . esc_url_raw( $url ) . ')';
					$desc = self::describe( $post );
					$lines[] = $desc ? $line . ': ' . $desc : $line;
				}
				if ( $lines ) {
					$out .= '## ' . self::clean( $heading ) . "\n\n" . implode( "\n", $lines ) . "\n\n";
				}
			}
		}

		foreach ( (array) apply_filters( 'aeo_llms_txt_sections', array() ) as $heading => $body ) {
			$out .= '## ' . self::clean( $heading ) . "\n\n" . trim( (string) $body ) . "\n\n";
		}

		$extra = trim( (string) Settings::get( 'llms_extra', '' ) );
		if ( '' !== $extra ) {
			$out .= $extra . "\n\n";
		}

		return (string) apply_filters( 'aeo_llms_txt', rtrim( $out ) . "\n" );
	}

	/**
	 * Build /llms-full.txt (capped at ~2 MB).
	 *
	 * @return string
	 */
	public static function build_full() {
		$limit    = (int) apply_filters( 'aeo_llms_full_limit', 200 );
		$max_size = (int) apply_filters( 'aeo_llms_full_max_bytes', 2 * MB_IN_BYTES );
		$out      = self::header();

		foreach ( (array) apply_filters( 'aeo_llms_txt_sections', array() ) as $heading => $body ) {
			$out .= '## ' . self::clean( $heading ) . "\n\n" . trim( (string) $body ) . "\n\n";
		}

		foreach ( self::posts_by_type( $limit ) as $post_type => $posts ) {
			foreach ( $posts as $post ) {
				$doc = "---\n\n" . self::post_markdown( $post->ID, false ) . "\n";
				if ( strlen( $out ) + strlen( $doc ) > $max_size ) {
					$out .= "---\n\n(Truncated. See /llms.txt for the full index.)\n";
					break 2;
				}
				$out .= $doc;
			}
		}

		return (string) apply_filters( 'aeo_llms_full_txt', rtrim( $out ) . "\n" );
	}

	/**
	 * Markdown document for one post.
	 *
	 * @param int  $post_id      Post ID.
	 * @param bool $front_matter Include YAML front matter.
	 * @return string
	 */
	public static function post_markdown( $post_id, $front_matter = true ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return '';
		}

		$cache_key = 'aeo_md_' . $post_id . '_' . md5( $post->post_modified_gmt . (int) $front_matter );
		$cached    = wp_cache_get( $cache_key, 'aeo' );
		if ( false !== $cached ) {
			return $cached;
		}

		// Render blocks and shortcodes the way visitors see them.
		$GLOBALS['post'] = $post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		setup_postdata( $post );
		$html = apply_filters( 'the_content', $post->post_content ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
		wp_reset_postdata();

		$title = self::clean( get_the_title( $post ) );
		$out   = '';

		if ( $front_matter ) {
			$meta = array(
				'title'    => $title,
				'url'      => get_permalink( $post ),
				'language' => Lang::post_language( $post_id ),
				'updated'  => get_post_modified_time( 'c', true, $post ),
			);
			$out .= "---\n";
			foreach ( $meta as $k => $v ) {
				$out .= $k . ': ' . wp_json_encode( (string) $v, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n";
			}
			$out .= "---\n\n";
		}

		$out .= '# ' . $title . "\n\n";
		if ( ! $front_matter ) {
			$out .= 'URL: ' . get_permalink( $post ) . "\n\n";
		}

		$answer = trim( (string) get_post_meta( $post_id, '_aeo_direct_answer', true ) );
		$body   = Html_To_Markdown::convert( $html );
		// The direct answer is already in the rendered content when shown on the page.
		if ( '' !== $answer && false === strpos( $body, $answer ) ) {
			$out .= '> ' . $answer . "\n\n";
		}
		$out .= $body;

		$out = (string) apply_filters( 'aeo_post_markdown', $out, $post_id );
		wp_cache_set( $cache_key, $out, 'aeo', HOUR_IN_SECONDS );
		return $out;
	}

	/**
	 * Send a text response and stop.
	 *
	 * @param string $body Body.
	 * @param string $type MIME type.
	 * @return void
	 */
	private static function send( $body, $type ) {
		status_header( 200 );
		header( 'Content-Type: ' . $type . '; charset=utf-8' );
		header( 'X-Robots-Tag: noindex' );
		header( 'Cache-Control: public, max-age=3600' );
		echo $body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- plain text/markdown document.
		exit;
	}

	/**
	 * 404 and stop.
	 *
	 * @return void
	 */
	private static function not_found() {
		status_header( 404 );
		nocache_headers();
		header( 'Content-Type: text/plain; charset=utf-8' );
		echo 'Not found';
		exit;
	}
}
