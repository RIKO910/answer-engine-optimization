<?php
/**
 * Frontend feature manager.
 *
 * @package AEO
 */

namespace AEO\Frontend;

use AEO\Core\Settings;
use AEO\Services\Schema_Service;

defined( 'ABSPATH' ) || exit;

/**
 * Manages all frontend AEO output.
 */
class Frontend_Manager {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'wp_head', array( $this, 'output_global_schema' ), 1 );
		add_action( 'wp_head', array( $this, 'output_open_graph' ), 5 );
		add_action( 'wp_head', array( $this, 'output_post_schema' ), 2 );
		add_action( 'init', array( $this, 'register_llms_txt' ) );
		add_filter( 'robots_txt', array( $this, 'filter_robots_txt' ), 20, 2 );

		if ( class_exists( 'WooCommerce' ) && Settings::is_module_enabled( 'woocommerce' ) ) {
			new WooCommerce_Integration();
		}
	}

	/**
	 * Output global WebSite + Organization schema.
	 *
	 * @return void
	 */
	public function output_global_schema() {
		if ( ! Settings::get( 'aeo_enable_schema', 1 ) ) {
			return;
		}

		$service = new Schema_Service();
		$global  = $service->get_global_schema();

		foreach ( $global as $schema ) {
			echo '<script type="application/ld+json">' .
				wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) .
				'</script>' . "\n";
		}

		if ( Settings::is_module_enabled( 'local_business' ) ) {
			$this->output_local_business_schema();
		}
	}

	/**
	 * Output LocalBusiness schema for configured locations.
	 *
	 * @return void
	 */
	private function output_local_business_schema() {
		$locations = get_option( 'aeo_local_locations', array() );
		if ( empty( $locations ) || ! is_array( $locations ) ) {
			return;
		}

		foreach ( $locations as $location ) {
			if ( empty( $location['name'] ) ) {
				continue;
			}

			$schema = array(
				'@context'    => 'https://schema.org',
				'@type'       => 'LocalBusiness',
				'name'        => $location['name'],
				'address'     => $location['address'] ?? '',
				'telephone'   => $location['phone'] ?? '',
				'openingHours' => $location['hours'] ?? '',
			);

			if ( ! empty( $location['lat'] ) && ! empty( $location['lng'] ) ) {
				$schema['geo'] = array(
					'@type'     => 'GeoCoordinates',
					'latitude'  => $location['lat'],
					'longitude' => $location['lng'],
				);
			}

			echo '<script type="application/ld+json">' .
				wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) .
				'</script>' . "\n";
		}
	}

	/**
	 * Output post-specific schema from meta.
	 *
	 * @return void
	 */
	public function output_post_schema() {
		if ( ! is_singular() || ! Settings::get( 'aeo_enable_schema', 1 ) ) {
			return;
		}

		global $post;
		$service = new Schema_Service();
		$schema  = $service->get_post_schema( $post->ID );

		if ( ! empty( $schema ) ) {
			echo '<script type="application/ld+json">' .
				wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) .
				'</script>' . "\n";
		}
	}

	/**
	 * Output Open Graph meta tags.
	 *
	 * @return void
	 */
	public function output_open_graph() {
		if ( ! Settings::is_module_enabled( 'social_og' ) ) {
			return;
		}

		$title       = is_singular() ? get_the_title() : get_bloginfo( 'name' );
		$description = is_singular() ? wp_strip_all_tags( get_the_excerpt() ) : get_bloginfo( 'description' );
		$url         = is_singular() ? get_permalink() : home_url();
		$image       = '';

		if ( is_singular() && has_post_thumbnail() ) {
			$img = wp_get_attachment_image_src( get_post_thumbnail_id(), 'large' );
			$image = $img ? $img[0] : '';
		}

		echo '<meta property="og:title" content="' . esc_attr( $title ) . '" />' . "\n";
		echo '<meta property="og:description" content="' . esc_attr( $description ) . '" />' . "\n";
		echo '<meta property="og:url" content="' . esc_url( $url ) . '" />' . "\n";
		echo '<meta property="og:type" content="' . ( is_singular() ? 'article' : 'website' ) . '" />' . "\n";
		echo '<meta property="og:site_name" content="' . esc_attr( get_bloginfo( 'name' ) ) . '" />' . "\n";

		if ( $image ) {
			echo '<meta property="og:image" content="' . esc_url( $image ) . '" />' . "\n";
		}

		echo '<meta name="twitter:card" content="summary_large_image" />' . "\n";
		echo '<meta name="twitter:title" content="' . esc_attr( $title ) . '" />' . "\n";
		echo '<meta name="twitter:description" content="' . esc_attr( $description ) . '" />' . "\n";
	}

	/**
	 * Register llms.txt rewrite rule.
	 *
	 * @return void
	 */
	public function register_llms_txt() {
		add_rewrite_rule( 'llms\.txt$', 'index.php?aeo_llms_txt=1', 'top' );
		add_filter( 'query_vars', function ( $vars ) {
			$vars[] = 'aeo_llms_txt';
			return $vars;
		} );
		add_action( 'template_redirect', array( $this, 'serve_llms_txt' ) );
	}

	/**
	 * Serve llms.txt content.
	 *
	 * @return void
	 */
	public function serve_llms_txt() {
		if ( ! get_query_var( 'aeo_llms_txt' ) ) {
			return;
		}

		header( 'Content-Type: text/plain; charset=utf-8' );

		$posts = get_posts(
			array(
				'post_type'      => array( 'post', 'page' ),
				'post_status'    => 'publish',
				'posts_per_page' => 50,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);

		echo '# ' . esc_html( get_bloginfo( 'name' ) ) . "\n";
		echo '> ' . esc_html( get_bloginfo( 'description' ) ) . "\n\n";

		foreach ( $posts as $post ) {
			echo '- [' . esc_html( $post->post_title ) . '](' . esc_url( get_permalink( $post ) ) . ')' . "\n";
		}

		exit;
	}

	/**
	 * Add AI crawler directives to robots.txt.
	 *
	 * @param string $output Robots.txt output.
	 * @param bool   $public Whether site is public.
	 * @return string
	 */
	public function filter_robots_txt( $output, $public ) {
		if ( ! $public || ! Settings::is_module_enabled( 'technical' ) ) {
			return $output;
		}

		$crawlers = Settings::get( 'crawler_settings', array() );
		$bots     = array(
			'gptbot'        => 'GPTBot',
			'perplexitybot' => 'PerplexityBot',
			'claudebot'     => 'ClaudeBot',
			'googlebot_ai'  => 'Google-Extended',
		);

		$output .= "\n# Answer Engine Optimization - AI Crawler Directives\n";

		foreach ( $bots as $key => $bot ) {
			$setting = $crawlers[ $key ] ?? 'allow';
			if ( 'block' === $setting ) {
				$output .= "User-agent: {$bot}\nDisallow: /\n\n";
			} else {
				$output .= "User-agent: {$bot}\nAllow: /\n\n";
			}
		}

		$output .= "Sitemap: " . home_url( '/llms.txt' ) . "\n";

		return $output;
	}
}
