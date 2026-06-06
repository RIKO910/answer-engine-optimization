<?php
/**
 * WooCommerce AEO integration.
 *
 * @package AEO
 */

namespace AEO\Frontend;

defined( 'ABSPATH' ) || exit;

/**
 * Product schema for WooCommerce.
 */
class WooCommerce_Integration {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'wp_head', array( $this, 'output_product_schema' ), 3 );
	}

	/**
	 * Output Product schema on product pages.
	 *
	 * @return void
	 */
	public function output_product_schema() {
		if ( ! is_product() ) {
			return;
		}

		global $product;
		if ( ! $product ) {
			return;
		}

		$schema = array(
			'@context'    => 'https://schema.org',
			'@type'       => 'Product',
			'name'        => $product->get_name(),
			'description' => wp_strip_all_tags( $product->get_short_description() ?: $product->get_description() ),
			'sku'         => $product->get_sku(),
			'url'         => get_permalink( $product->get_id() ),
			'offers'      => array(
				'@type'         => 'Offer',
				'price'         => $product->get_price(),
				'priceCurrency' => get_woocommerce_currency(),
				'availability'  => $product->is_in_stock()
					? 'https://schema.org/InStock'
					: 'https://schema.org/OutOfStock',
				'url'           => get_permalink( $product->get_id() ),
			),
		);

		if ( $product->get_image_id() ) {
			$image = wp_get_attachment_image_src( $product->get_image_id(), 'full' );
			if ( $image ) {
				$schema['image'] = $image[0];
			}
		}

		$rating_count = $product->get_rating_count();
		if ( $rating_count > 0 ) {
			$schema['aggregateRating'] = array(
				'@type'       => 'AggregateRating',
				'ratingValue' => $product->get_average_rating(),
				'reviewCount' => $rating_count,
			);
		}

		$brand = $product->get_attribute( 'brand' );
		if ( $brand ) {
			$schema['brand'] = array(
				'@type' => 'Brand',
				'name'  => $brand,
			);
		}

		echo '<script type="application/ld+json">' .
			wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) .
			'</script>' . "\n";
	}
}
