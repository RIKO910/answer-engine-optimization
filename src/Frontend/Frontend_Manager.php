<?php
/**
 * Front-end output manager: JSON-LD, Open Graph, llms.txt and robots.txt.
 *
 * @package AEO
 */

namespace AEO\Frontend;

use AEO\Core\Settings;
use AEO\Services\Schema_Service;
use AEO\Services\Seo_Compat;

defined( 'ABSPATH' ) || exit;

/**
 * Handles front-end output.
 */
class Frontend_Manager {

	/**
	 * AI crawler user agents managed in robots.txt.
	 */
	const BOTS = array(
		'gptbot'        => 'GPTBot',
		'perplexitybot' => 'PerplexityBot',
		'claudebot'     => 'ClaudeBot',
		'googlebot_ai'  => 'Google-Extended',
	);

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'wp_head', array( $this, 'output_schema' ), 2 );
		add_action( 'wp_head', array( $this, 'output_open_graph' ), 5 );
		add_action( 'template_redirect', array( $this, 'serve_llms_txt' ), 0 );

		if ( class_exists( 'WooCommerce' ) && Settings::is_module_enabled( 'woocommerce' ) ) {
			new WooCommerce_Integration();
		}
	}

	/**
	 * Print one connected JSON-LD graph for the current request.
	 *
	 * @return void
	 */
	public function output_schema() {
		if ( ! Settings::get( 'aeo_enable_schema', 1 ) ) {
			return;
		}

		$service = new Schema_Service();
		$defer   = Seo_Compat::should_defer();
		$nodes   = array();

		if ( ! $defer ) {
			$global  = $service->get_global_schema();
			$nodes[] = $global['website'];
			$nodes[] = $global['organization'];
		}

		if ( Settings::is_module_enabled( 'local_business' ) && ( is_front_page() || is_home() ) ) {
			$locations = get_option( 'aeo_local_locations', array() );
			if ( is_array( $locations ) ) {
				foreach ( array_values( $locations ) as $i => $location ) {
					if ( is_array( $location ) ) {
						$nodes[] = $service->build_location_node( $location, $i );
					}
				}
			}
		}

		if ( is_singular() ) {
			$post_id = get_queried_object_id();
			if ( $post_id && ! post_password_required( $post_id ) ) {
				$nodes = array_merge( $nodes, $service->get_post_nodes( $post_id, $defer ) );
			}
		}

		Schema_Service::print_json_ld( Schema_Service::graph( $nodes ) );
	}

	/**
	 * Open Graph and Twitter Card tags (skipped when an SEO plugin handles them).
	 *
	 * @return void
	 */
	public function output_open_graph() {
		if ( ! Settings::is_module_enabled( 'social_og' ) || Seo_Compat::should_defer() ) {
			return;
		}

		$singular = is_singular();
		$post_id  = $singular ? get_queried_object_id() : 0;

		if ( $singular ) {
			$title       = wp_strip_all_tags( get_the_title( $post_id ) );
			$description = has_excerpt( $post_id )
				? wp_strip_all_tags( get_the_excerpt( $post_id ) )
				: Schema_Service::plain_text( (string) get_post_field( 'post_content', $post_id ), 30 );
			$url         = get_permalink( $post_id );
		} else {
			$title       = wp_get_document_title();
			$description = get_bloginfo( 'description' );
			$url         = home_url( add_query_arg( array() ) );
		}

		$image = '';
		if ( $singular && has_post_thumbnail( $post_id ) ) {
			$img   = wp_get_attachment_image_src( get_post_thumbnail_id( $post_id ), 'large' );
			$image = $img ? $img[0] : '';
		}
		if ( ! $image ) {
			$business = Settings::get( 'business_info', array() );
			$image    = ! empty( $business['logo_url'] ) ? $business['logo_url'] : get_site_icon_url( 512 );
		}

		$tags = array(
			'og:title'       => $title,
			'og:description' => $description,
			'og:url'         => $url,
			'og:type'        => $singular && 'post' === get_post_type( $post_id ) ? 'article' : 'website',
			'og:site_name'   => get_bloginfo( 'name' ),
			'og:locale'      => str_replace( '-', '_', get_bloginfo( 'language' ) ),
			'og:image'       => $image,
		);

		foreach ( $tags as $property => $content ) {
			if ( '' === (string) $content ) {
				continue;
			}
			$value = in_array( $property, array( 'og:url', 'og:image' ), true ) ? esc_url( $content ) : esc_attr( $content );
			echo '<meta property="' . esc_attr( $property ) . '" content="' . $value . '" />' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		echo '<meta name="twitter:card" content="' . ( $image ? 'summary_large_image' : 'summary' ) . '" />' . "\n";
		echo '<meta name="twitter:title" content="' . esc_attr( $title ) . '" />' . "\n";
		if ( $description ) {
			echo '<meta name="twitter:description" content="' . esc_attr( $description ) . '" />' . "\n";
		}
	}

	/**
	 * Serve /llms.txt (rewrite rule registered in Plugin::register_rewrites()).
	 *
	 * @return void
	 */
	public function serve_llms_txt() {
		if ( ! get_query_var( 'aeo_llms_txt' ) ) {
			return;
		}

		if ( ! Settings::is_module_enabled( 'technical' ) ) {
			status_header( 404 );
			nocache_headers();
			exit;
		}

		$body = get_transient( 'aeo_llms_txt' );
		if ( false === $body ) {
			$body = self::build_llms_txt();
			set_transient( 'aeo_llms_txt', $body, DAY_IN_SECONDS );
		}

		status_header( 200 );
		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'X-Robots-Tag: noindex' );
		echo $body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- plain text document.
		exit;
	}

	/**
	 * Build the llms.txt document (https://llmstxt.org format).
	 *
	 * @return string
	 */
	public static function build_llms_txt() {
		$business = Settings::get( 'business_info', array() );
		$name     = ! empty( $business['name'] ) ? $business['name'] : get_bloginfo( 'name' );
		$summary  = ! empty( $business['description'] ) ? $business['description'] : get_bloginfo( 'description' );
		$limit    = (int) apply_filters( 'aeo_llms_txt_limit', 100 );

		$clean = static function ( $text ) {
			return trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( (string) $text ) ) );
		};

		$out = '# ' . $clean( $name ) . "\n\n";
		if ( $summary ) {
			$out .= '> ' . $clean( $summary ) . "\n\n";
		}

		foreach ( Settings::get_post_types() as $post_type ) {
			$object = get_post_type_object( $post_type );
			if ( ! $object ) {
				continue;
			}

			$posts = get_posts(
				array(
					'post_type'        => $post_type,
					'post_status'      => 'publish',
					'posts_per_page'   => $limit,
					'orderby'          => 'page' === $post_type ? 'menu_order' : 'modified',
					'order'            => 'page' === $post_type ? 'ASC' : 'DESC',
					'has_password'     => false,
					'suppress_filters' => false,
				)
			);

			$lines = array();
			foreach ( $posts as $post ) {
				if ( Seo_Compat::is_noindex( $post->ID ) ) {
					continue;
				}
				$title = $clean( get_the_title( $post ) );
				if ( '' === $title ) {
					continue;
				}
				$desc = has_excerpt( $post )
					? $clean( get_the_excerpt( $post ) )
					: $clean( get_post_meta( $post->ID, '_aeo_direct_answer', true ) );
				$line = '- [' . str_replace( array( '[', ']' ), '', $title ) . '](' . esc_url_raw( get_permalink( $post ) ) . ')';
				if ( $desc ) {
					$line .= ': ' . wp_html_excerpt( $desc, 200, '…' );
				}
				$lines[] = $line;
			}

			if ( $lines ) {
				$out .= '## ' . $clean( $object->labels->name ) . "\n\n" . implode( "\n", $lines ) . "\n\n";
			}
		}

		return (string) apply_filters( 'aeo_llms_txt', rtrim( $out ) . "\n" );
	}

	/**
	 * Add AI crawler rules to robots.txt.
	 *
	 * Only blocked bots get their own group: a group like "User-agent: GPTBot / Allow: /"
	 * would make that bot ignore the site's "User-agent: *" rules (e.g. Disallow: /wp-admin/).
	 *
	 * @param string $output Existing robots.txt.
	 * @param bool   $public Whether the site is public.
	 * @return string
	 */
	public static function filter_robots_txt( $output, $public ) {
		if ( ! $public || ! Settings::is_module_enabled( 'technical' ) ) {
			return $output;
		}

		$crawlers = Settings::get( 'crawler_settings', array() );
		$blocked  = array();
		foreach ( self::BOTS as $key => $bot ) {
			if ( 'block' === ( $crawlers[ $key ] ?? 'allow' ) ) {
				$blocked[] = $bot;
			}
		}

		$output  = rtrim( (string) $output ) . "\n\n# Answer Engine Optimization\n";
		$output .= '# LLM-friendly site index: ' . home_url( '/llms.txt' ) . "\n";
		foreach ( $blocked as $bot ) {
			$output .= "User-agent: {$bot}\nDisallow: /\n\n";
		}

		return $output;
	}
}
