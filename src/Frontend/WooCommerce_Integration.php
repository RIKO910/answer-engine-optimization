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

		$markup = $this->add_policies( $markup );

		if ( \AEO\Core\Settings::get( 'woo_product_group', 0 ) && $product->is_type( 'variable' ) ) {
			$markup = $this->to_product_group( $markup, $product );
		}

		return (array) apply_filters( 'aeo_woocommerce_product_schema', $markup, $product );
	}

	/**
	 * Return policy and shipping details from settings, added to every offer.
	 *
	 * @param array<string, mixed> $markup Markup.
	 * @return array<string, mixed>
	 */
	private function add_policies( array $markup ) {
		$w = (array) \AEO\Core\Settings::get( 'woo', array() );

		$policy = null;
		if ( ! empty( $w['return_country'] ) ) {
			$days   = (int) ( $w['return_days'] ?? 0 );
			$policy = array(
				'@type'                => 'MerchantReturnPolicy',
				'applicableCountry'    => strtoupper( $w['return_country'] ),
				'returnPolicyCategory' => $days > 0 ? 'https://schema.org/MerchantReturnFiniteReturnWindow' : 'https://schema.org/MerchantReturnNotPermitted',
			);
			if ( $days > 0 ) {
				$policy['merchantReturnDays'] = $days;
				$policy['returnMethod']       = 'https://schema.org/' . ( array( 'store' => 'ReturnInStore', 'kiosk' => 'ReturnAtKiosk' )[ $w['return_method'] ?? '' ] ?? 'ReturnByMail' );
				$policy['returnFees']         = 'https://schema.org/' . ( 'customer' === ( $w['return_fees'] ?? '' ) ? 'ReturnFeesCustomerResponsibility' : 'FreeReturn' );
			}
		}

		$shipping = null;
		if ( ! empty( $w['ship_country'] ) && isset( $w['ship_rate'] ) && '' !== (string) $w['ship_rate'] ) {
			$shipping = array(
				'@type'               => 'OfferShippingDetails',
				'shippingRate'        => array(
					'@type'    => 'MonetaryAmount',
					'value'    => (float) $w['ship_rate'],
					'currency' => get_woocommerce_currency(),
				),
				'shippingDestination' => array(
					'@type'          => 'DefinedRegion',
					'addressCountry' => strtoupper( $w['ship_country'] ),
				),
				'deliveryTime'        => array(
					'@type'        => 'ShippingDeliveryTime',
					'handlingTime' => array(
						'@type'    => 'QuantitativeValue',
						'minValue' => (int) ( $w['handling_min'] ?? 0 ),
						'maxValue' => (int) ( $w['handling_max'] ?? 1 ),
						'unitCode' => 'DAY',
					),
					'transitTime'  => array(
						'@type'    => 'QuantitativeValue',
						'minValue' => (int) ( $w['transit_min'] ?? 1 ),
						'maxValue' => (int) ( $w['transit_max'] ?? 5 ),
						'unitCode' => 'DAY',
					),
				),
			);
		}

		if ( ( $policy || $shipping ) && ! empty( $markup['offers'] ) && is_array( $markup['offers'] ) ) {
			$offers = isset( $markup['offers']['@type'] ) ? array( $markup['offers'] ) : $markup['offers'];
			foreach ( $offers as &$offer ) {
				if ( $policy && empty( $offer['hasMerchantReturnPolicy'] ) ) {
					$offer['hasMerchantReturnPolicy'] = $policy;
				}
				if ( $shipping && empty( $offer['shippingDetails'] ) ) {
					$offer['shippingDetails'] = $shipping;
				}
				if ( empty( $offer['priceValidUntil'] ) ) {
					$offer['priceValidUntil'] = gmdate( 'Y-12-31', strtotime( '+1 year' ) );
				}
			}
			unset( $offer );
			$markup['offers'] = isset( $markup['offers']['@type'] ) ? $offers[0] : $offers;
		}
		return $markup;
	}

	/**
	 * Variable product as a ProductGroup with one Product per variation.
	 *
	 * @param array<string, mixed> $markup  Markup.
	 * @param \WC_Product_Variable $product Product.
	 * @return array<string, mixed>
	 */
	private function to_product_group( array $markup, $product ) {
		$known    = array(
			'size'     => 'https://schema.org/size',
			'color'    => 'https://schema.org/color',
			'colour'   => 'https://schema.org/color',
			'material' => 'https://schema.org/material',
			'pattern'  => 'https://schema.org/pattern',
		);
		$varies   = array();
		foreach ( array_keys( $product->get_variation_attributes() ) as $attr ) {
			$label    = strtolower( wc_attribute_label( $attr ) );
			$varies[] = $known[ $label ] ?? $known[ str_replace( 'pa_', '', strtolower( $attr ) ) ] ?? $label;
		}

		$offer_extras = array();
		$first_offer  = isset( $markup['offers'][0] ) ? $markup['offers'][0] : ( $markup['offers'] ?? array() );
		foreach ( array( 'hasMerchantReturnPolicy', 'shippingDetails', 'priceValidUntil' ) as $k ) {
			if ( isset( $first_offer[ $k ] ) ) {
				$offer_extras[ $k ] = $first_offer[ $k ];
			}
		}

		$variants = array();
		foreach ( array_slice( $product->get_children(), 0, 50 ) as $child_id ) {
			$v = wc_get_product( $child_id );
			if ( ! $v || ! $v->is_purchasable() ) {
				continue;
			}
			$item = array(
				'@type'  => 'Product',
				'name'   => wp_strip_all_tags( $v->get_name() ),
				'sku'    => $v->get_sku(),
				'offers' => array_merge(
					array(
						'@type'         => 'Offer',
						'price'         => wc_format_decimal( $v->get_price(), wc_get_price_decimals() ),
						'priceCurrency' => get_woocommerce_currency(),
						'availability'  => 'https://schema.org/' . ( $v->is_in_stock() ? 'InStock' : 'OutOfStock' ),
						'url'           => $v->get_permalink(),
					),
					$offer_extras
				),
			);
			if ( $v->get_image_id() ) {
				$img = wp_get_attachment_image_src( $v->get_image_id(), 'full' );
				if ( $img ) {
					$item['image'] = $img[0];
				}
			}
			foreach ( $v->get_variation_attributes( false ) as $attr => $value ) {
				$label = strtolower( wc_attribute_label( $attr ) );
				if ( isset( $known[ $label ] ) ) {
					$item[ basename( $known[ $label ] ) ] = $value;
				}
			}
			$variants[] = array_filter( $item );
		}

		if ( ! $variants ) {
			return $markup;
		}

		$markup['@type']          = 'ProductGroup';
		$markup['productGroupID'] = $product->get_sku() ? $product->get_sku() : (string) $product->get_id();
		$markup['variesBy']       = array_values( array_unique( $varies ) );
		$markup['hasVariant']     = $variants;
		unset( $markup['offers'] );
		return $markup;
	}
}
