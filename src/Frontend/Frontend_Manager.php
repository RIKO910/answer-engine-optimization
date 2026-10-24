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
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'wp_head', array( $this, 'output_schema' ), 2 );
		add_action( 'wp_head', array( $this, 'output_open_graph' ), 5 );

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
	 * Back-compat wrapper.
	 *
	 * @return string
	 */
	public static function build_llms_txt() {
		return Llms_Txt::build_index();
	}

	/**
	 * Back-compat wrapper.
	 *
	 * @param string $output Robots.
	 * @param bool   $public Public.
	 * @return string
	 */
	public static function filter_robots_txt( $output, $public ) {
		return Ai_Crawlers::filter_robots_txt( $output, $public );
	}
}
