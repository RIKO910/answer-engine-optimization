<?php
/**
 * WooCommerce structured data enrichment.
 *
 * WooCommerce already prints Product JSON-LD on product pages. Printing a second
 * Product node creates duplicate, conflicting entities, so instead we hook into
 * WooCommerce's own data and add what it leaves out (brand, GTIN).
 *
 * @package AEO
 */

namespace AEO\Frontend;

defined( 'ABSPATH' ) || exit;

/**
 * WooCommerce integration.
 */
class WooCommerce_Integration {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_filter( 'woocommerce_structured_data_product', array( $this, 'enrich_product_data' ), 20, 2 );
	}

	/**
	 * Add brand and GTIN to WooCommerce's Product schema when missing.
	 *
	 * @param array<string, mixed> $markup  Product markup.
	 * @param \WC_Product          $product Product.
	 * @return array<string, mixed>
	 */
	public function enrich_product_data( $markup, $product ) {
		if ( ! is_array( $markup ) || ! is_object( $product ) ) {
			return $markup;
		}

		if ( empty( $markup['brand'] ) ) {
			$brand = '';
			if ( taxonomy_exists( 'product_brand' ) ) {
				$terms = get_the_terms( $product->get_id(), 'product_brand' );
				if ( is_array( $terms ) && ! empty( $terms ) ) {
					$brand = $terms[0]->name;
				}
			}
			if ( ! $brand && method_exists( $product, 'get_attribute' ) ) {
				$brand = (string) $product->get_attribute( 'brand' );
			}
			if ( $brand ) {
				$markup['brand'] = array(
					'@type' => 'Brand',
					'name'  => $brand,
				);
			}
		}

		if ( empty( $markup['gtin'] ) && method_exists( $product, 'get_global_unique_id' ) ) {
			$gtin = (string) $product->get_global_unique_id();
			if ( preg_match( '/^\d{8,14}$/', $gtin ) ) {
				$markup['gtin'] = $gtin;
			}
		}

		return (array) apply_filters( 'aeo_woocommerce_product_schema', $markup, $product );
	}
}
