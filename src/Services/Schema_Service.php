<?php
/**
 * Schema.org JSON-LD generation, validation and storage.
 *
 * @package AEO
 */

namespace AEO\Services;

use AEO\Core\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Schema service.
 */
class Schema_Service {

	/**
	 * Top-level types accepted by the Schema Builder.
	 *
	 * @var string[]
	 */
	public static $types = array(
		'Article', 'NewsArticle', 'BlogPosting', 'TechArticle',
		'FAQPage', 'QAPage', 'HowTo',
		'WebPage', 'AboutPage', 'ContactPage', 'CollectionPage', 'ItemList',
		'Product', 'Review',
		'LocalBusiness', 'Restaurant', 'MedicalBusiness', 'Store',
		'Hotel', 'LodgingBusiness', 'Resort', 'BedAndBreakfast', 'Motel', 'Hostel',
		'HotelRoom', 'Suite', 'Accommodation', 'TouristAttraction', 'Place',
		'Person', 'Organization', 'Corporation', 'NGO', 'EducationalOrganization',
		'Event', 'BusinessEvent',
		'Recipe', 'Course',
		'JobPosting',
		'VideoObject', 'ImageObject', 'AudioObject',
		'BreadcrumbList', 'WebSite',
		'SoftwareApplication', 'MobileApplication',
		'Book', 'Movie',
		'Service', 'ProfessionalService', 'LegalService', 'FinancialService',
	);

	/**
	 * Required properties per type (missing = error).
	 *
	 * @var array<string, string[]>
	 */
	private static $required = array(
		'Article'             => array( 'headline' ),
		'NewsArticle'         => array( 'headline' ),
		'BlogPosting'         => array( 'headline' ),
		'TechArticle'         => array( 'headline' ),
		'FAQPage'             => array( 'mainEntity' ),
		'QAPage'              => array( 'mainEntity' ),
		'HowTo'               => array( 'name', 'step' ),
		'Product'             => array( 'name' ),
		'Review'              => array( 'itemReviewed', 'reviewRating', 'author' ),
		'LocalBusiness'       => array( 'name', 'address' ),
		'Restaurant'          => array( 'name', 'address' ),
		'Store'               => array( 'name', 'address' ),
		'Hotel'               => array( 'name', 'address' ),
		'LodgingBusiness'     => array( 'name', 'address' ),
		'Resort'              => array( 'name', 'address' ),
		'BedAndBreakfast'     => array( 'name', 'address' ),
		'Motel'               => array( 'name', 'address' ),
		'Hostel'              => array( 'name', 'address' ),
		'HotelRoom'           => array( 'name' ),
		'Suite'               => array( 'name' ),
		'Event'               => array( 'name', 'startDate', 'location' ),
		'BusinessEvent'       => array( 'name', 'startDate', 'location' ),
		'Recipe'              => array( 'name', 'image' ),
		'Course'              => array( 'name', 'description' ),
		'JobPosting'          => array( 'title', 'description', 'datePosted', 'hiringOrganization' ),
		'VideoObject'         => array( 'name', 'thumbnailUrl', 'uploadDate' ),
		'Person'              => array( 'name' ),
		'Organization'        => array( 'name' ),
		'SoftwareApplication' => array( 'name' ),
		'BreadcrumbList'      => array( 'itemListElement' ),
	);

	/**
	 * Recommended properties per type (missing = warning).
	 *
	 * @var array<string, string[]>
	 */
	private static $recommended = array(
		'Article'         => array( 'image', 'datePublished', 'author' ),
		'BlogPosting'     => array( 'image', 'datePublished', 'author' ),
		'NewsArticle'     => array( 'image', 'datePublished', 'author' ),
		'Product'         => array( 'image', 'offers' ),
		'LocalBusiness'   => array( 'telephone', 'geo', 'openingHoursSpecification' ),
		'Hotel'           => array( 'telephone', 'geo', 'priceRange', 'checkinTime', 'checkoutTime', 'amenityFeature', 'starRating' ),
		'LodgingBusiness' => array( 'telephone', 'geo', 'priceRange', 'checkinTime', 'checkoutTime' ),
		'Event'           => array( 'endDate', 'image', 'offers' ),
	);

	/**
	 * Encode and print a JSON-LD block safely.
	 *
	 * JSON_HEX_TAG turns "<" and ">" into < / > so user content such as
	 * "</script>" in an FAQ answer can never break out of the script element.
	 *
	 * @param array<string, mixed> $data Schema data.
	 * @return void
	 */
	public static function print_json_ld( array $data ) {
		if ( empty( $data ) ) {
			return;
		}
		$json = wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP );
		if ( $json ) {
			echo '<script type="application/ld+json" class="aeo-schema">' . $json . '</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON encoded with HEX_TAG.
		}
	}

	/**
	 * Wrap nodes in a single @graph document.
	 *
	 * @param array<int, array<string, mixed>> $nodes Nodes.
	 * @return array<string, mixed>
	 */
	public static function graph( array $nodes ) {
		$nodes = array_values( array_filter( $nodes ) );
		foreach ( $nodes as &$node ) {
			unset( $node['@context'] );
		}
		unset( $node );
		if ( empty( $nodes ) ) {
			return array();
		}
		return array(
			'@context' => 'https://schema.org',
			'@graph'   => $nodes,
		);
	}

	/**
	 * HTML to readable plain text (keeps word boundaries between blocks).
	 *
	 * @param string $html  HTML.
	 * @param int    $words Optional word limit.
	 * @return string
	 */
	public static function plain_text( $html, $words = 0 ) {
		$html = preg_replace( '/<\/(p|li|h[1-6]|div|td|th|blockquote|figcaption)>|<br\s*\/?>/i', '$0 ', strip_shortcodes( (string) $html ) );
		$text = trim( preg_replace( '/\s+/u', ' ', html_entity_decode( wp_strip_all_tags( $html ), ENT_QUOTES, 'UTF-8' ) ) );
		return $words > 0 ? wp_trim_words( $text, $words, '…' ) : $text;
	}

	/**
	 * Stable @id for the site organization.
	 *
	 * @return string
	 */
	public static function organization_id() {
		return trailingslashit( home_url() ) . '#organization';
	}

	/**
	 * Stable @id for the website.
	 *
	 * @return string
	 */
	public static function website_id() {
		return trailingslashit( home_url() ) . '#website';
	}

	/**
	 * Editable main schema for a post (custom if saved, otherwise auto-generated).
	 *
	 * @param int $post_id Post ID.
	 * @return array<string, mixed>
	 */
	public function get_post_schema( $post_id ) {
		$custom = $this->get_custom_schema( $post_id );
		if ( $custom ) {
			return $custom;
		}
		return $this->auto_generate( $post_id );
	}

	/**
	 * Saved custom schema, if any.
	 *
	 * @param int $post_id Post ID.
	 * @return array<string, mixed>
	 */
	public function get_custom_schema( $post_id ) {
		$stored = get_post_meta( $post_id, '_aeo_schema', true );
		if ( $stored ) {
			$decoded = is_array( $stored ) ? $stored : json_decode( $stored, true );
			if ( is_array( $decoded ) ) {
				return $decoded;
			}
		}
		return array();
	}

	/**
	 * All nodes this plugin outputs for a singular post.
	 *
	 * @param int  $post_id    Post ID.
	 * @param bool $skip_base  Skip the auto Article/WebPage node (another SEO plugin prints it).
	 * @return array<int, array<string, mixed>>
	 */
	public function get_post_nodes( $post_id, $skip_base = false ) {
		$nodes  = array();
		$custom = $this->get_custom_schema( $post_id );

		if ( $custom ) {
			if ( isset( $custom['@graph'] ) && is_array( $custom['@graph'] ) ) {
				$nodes = array_merge( $nodes, $custom['@graph'] );
			} else {
				$nodes[] = $custom;
			}
		} elseif ( ! $skip_base ) {
			$base = $this->auto_generate( $post_id );
			if ( $base ) {
				$nodes[] = $base;
			}
		}

		$has_faq_node = false;
		foreach ( $nodes as $node ) {
			if ( isset( $node['@type'] ) && 'FAQPage' === $node['@type'] ) {
				$has_faq_node = true;
			}
		}

		if ( ! $has_faq_node && Settings::is_module_enabled( 'faq_automation' ) ) {
			$faq = $this->build_faq_node( $post_id );
			if ( $faq ) {
				$nodes[] = $faq;
			}
		}

		// When deferring, still add Speakable to the SEO plugin's page via a WebPage reference.
		if ( $skip_base && ! $custom ) {
			$speakable = $this->build_speakable( $post_id );
			if ( $speakable ) {
				$nodes[] = array(
					'@type'     => 'WebPage',
					'@id'       => get_permalink( $post_id ),
					'url'       => get_permalink( $post_id ),
					'speakable' => $speakable,
				);
			}
		}

		return (array) apply_filters( 'aeo_post_schema_nodes', $nodes, $post_id );
	}

	/**
	 * Save custom schema for a post.
	 *
	 * @param int                  $post_id Post ID.
	 * @param array<string, mixed> $schema  Schema.
	 * @return bool
	 */
	public function save_post_schema( $post_id, array $schema ) {
		$validation = $this->validate( $schema );
		if ( ! $validation['valid'] ) {
			return false;
		}

		update_post_meta( $post_id, '_aeo_schema', wp_slash( wp_json_encode( $schema ) ) );
		$this->cache_schema( $post_id, $schema );
		return true;
	}

	/**
	 * Remove custom schema so the post falls back to auto-generated schema.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function delete_post_schema( $post_id ) {
		delete_post_meta( $post_id, '_aeo_schema' );
		global $wpdb;
		$wpdb->delete( $wpdb->prefix . 'aeo_schema_cache', array( 'post_id' => (int) $post_id ), array( '%d' ) );
	}

	/**
	 * Auto-generate the main node for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return array<string, mixed>
	 */
	public function auto_generate( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return array();
		}

		// WooCommerce prints its own Product schema; we enrich it via a filter instead.
		if ( 'product' === $post->post_type && class_exists( 'WooCommerce' ) ) {
			return array();
		}

		$url         = get_permalink( $post_id );
		$is_article  = 'post' === $post->post_type;
		$description = has_excerpt( $post_id )
			? wp_strip_all_tags( get_the_excerpt( $post_id ) )
			: self::plain_text( $post->post_content, 30 );

		$schema = array(
			'@context'      => 'https://schema.org',
			'@type'         => $is_article ? 'Article' : 'WebPage',
			'@id'           => $url . ( $is_article ? '#article' : '' ),
			'url'           => $url,
			'datePublished' => get_the_date( 'c', $post_id ),
			'dateModified'  => get_the_modified_date( 'c', $post_id ),
			'inLanguage'    => get_bloginfo( 'language' ),
			'isPartOf'      => array( '@id' => self::website_id() ),
		);

		$title = wp_strip_all_tags( get_the_title( $post_id ) );
		if ( $is_article ) {
			$schema['headline']         = mb_substr( $title, 0, 110 );
			$schema['mainEntityOfPage'] = array( '@id' => $url );
			$schema['publisher']        = array( '@id' => self::organization_id() );
			$schema['author']           = array(
				'@type' => 'Person',
				'name'  => get_the_author_meta( 'display_name', $post->post_author ),
				'url'   => get_author_posts_url( $post->post_author ),
			);
		} else {
			$schema['name'] = $title;
		}

		if ( $description ) {
			$schema['description'] = $description;
		}

		if ( has_post_thumbnail( $post_id ) ) {
			$image = wp_get_attachment_image_src( get_post_thumbnail_id( $post_id ), 'full' );
			if ( $image ) {
				$schema['image'] = array(
					'@type'  => 'ImageObject',
					'url'    => $image[0],
					'width'  => (int) $image[1],
					'height' => (int) $image[2],
				);
			}
		}

		$speakable = $this->build_speakable( $post_id );
		if ( $speakable ) {
			$schema['speakable'] = $speakable;
		}

		return (array) apply_filters( 'aeo_auto_schema', $schema, $post_id );
	}

	/**
	 * FAQPage node from the post's FAQ items.
	 *
	 * @param int $post_id Post ID.
	 * @return array<string, mixed>
	 */
	public function build_faq_node( $post_id ) {
		$faqs = get_post_meta( $post_id, '_aeo_faq_items', true );
		if ( empty( $faqs ) || ! is_array( $faqs ) ) {
			return array();
		}

		$questions = array();
		foreach ( $faqs as $faq ) {
			$q = isset( $faq['question'] ) ? trim( wp_strip_all_tags( $faq['question'] ) ) : '';
			$a = isset( $faq['answer'] ) ? trim( wp_strip_all_tags( $faq['answer'] ) ) : '';
			if ( '' === $q || '' === $a ) {
				continue;
			}
			$questions[] = array(
				'@type'          => 'Question',
				'name'           => $q,
				'acceptedAnswer' => array(
					'@type' => 'Answer',
					'text'  => $a,
				),
			);
		}

		if ( empty( $questions ) ) {
			return array();
		}

		return array(
			'@type'      => 'FAQPage',
			'@id'        => get_permalink( $post_id ) . '#faq',
			'url'        => get_permalink( $post_id ),
			'mainEntity' => $questions,
		);
	}

	/**
	 * Speakable specification pointing at the visible direct answer.
	 *
	 * @param int $post_id Post ID.
	 * @return array<string, mixed>
	 */
	public function build_speakable( $post_id ) {
		if ( ! Settings::get( 'aeo_voice_optimization', 1 ) ) {
			return array();
		}
		if ( '' === trim( (string) get_post_meta( $post_id, '_aeo_direct_answer', true ) ) ) {
			return array();
		}
		return array(
			'@type'       => 'SpeakableSpecification',
			'cssSelector' => array( '.aeo-direct-answer' ),
		);
	}

	/**
	 * WebSite + Organization nodes.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function get_global_schema() {
		$global   = get_option( 'aeo_optimizer_schema_global', array() );
		$global   = is_array( $global ) ? $global : array();
		$settings = Settings::get( 'business_info', array() );

		$website = array(
			'@context'        => 'https://schema.org',
			'@type'           => 'WebSite',
			'@id'             => self::website_id(),
			'name'            => get_bloginfo( 'name' ),
			'url'             => home_url( '/' ),
			'inLanguage'      => get_bloginfo( 'language' ),
			'publisher'       => array( '@id' => self::organization_id() ),
			'potentialAction' => array(
				'@type'       => 'SearchAction',
				'target'      => array(
					'@type'       => 'EntryPoint',
					'urlTemplate' => home_url( '/?s={search_term_string}' ),
				),
				'query-input' => 'required name=search_term_string',
			),
		);

		$name = ! empty( $settings['name'] ) ? $settings['name'] : get_bloginfo( 'name' );
		$desc = ! empty( $settings['description'] ) ? $settings['description'] : get_bloginfo( 'description' );

		$organization = array(
			'@context' => 'https://schema.org',
			'@type'    => 'Organization',
			'@id'      => self::organization_id(),
			'name'     => $name,
			'url'      => home_url( '/' ),
		);
		if ( $desc ) {
			$organization['description'] = $desc;
		}

		$logo = ! empty( $settings['logo_url'] ) ? $settings['logo_url'] : '';
		if ( ! $logo && get_theme_mod( 'custom_logo' ) ) {
			$img  = wp_get_attachment_image_src( get_theme_mod( 'custom_logo' ), 'full' );
			$logo = $img ? $img[0] : '';
		}
		if ( $logo ) {
			$organization['logo'] = array(
				'@type' => 'ImageObject',
				'url'   => $logo,
			);
		}

		if ( ! empty( $settings['social'] ) && is_array( $settings['social'] ) ) {
			$organization['sameAs'] = array_values( array_filter( $settings['social'] ) );
		}

		$website_overrides      = isset( $global['website'] ) && is_array( $global['website'] ) ? $global['website'] : array();
		$organization_overrides = isset( $global['organization'] ) && is_array( $global['organization'] ) ? $global['organization'] : array();
		// Never let overrides break the @id links.
		unset( $website_overrides['@id'], $organization_overrides['@id'] );

		return array(
			'website'      => array_merge( $website, $website_overrides ),
			'organization' => array_merge( $organization, $organization_overrides ),
		);
	}

	/**
	 * LocalBusiness (or subtype such as Hotel) node for a saved location.
	 *
	 * @param array<string, string> $location Location fields.
	 * @param int                   $index    Position.
	 * @return array<string, mixed>
	 */
	public function build_location_node( array $location, $index = 0 ) {
		if ( empty( $location['name'] ) ) {
			return array();
		}

		$type = ! empty( $location['type'] ) && in_array( $location['type'], self::$types, true ) ? $location['type'] : 'LocalBusiness';

		$address = array_filter(
			array(
				'@type'           => 'PostalAddress',
				'streetAddress'   => $location['address'] ?? '',
				'addressLocality' => $location['city'] ?? '',
				'addressRegion'   => $location['region'] ?? '',
				'postalCode'      => $location['postal_code'] ?? '',
				'addressCountry'  => $location['country'] ?? '',
			)
		);

		$node = array(
			'@type'              => $type,
			'@id'                => trailingslashit( home_url() ) . '#location-' . ( (int) $index + 1 ),
			'name'               => $location['name'],
			'url'                => ! empty( $location['url'] ) ? $location['url'] : home_url( '/' ),
			'parentOrganization' => array( '@id' => self::organization_id() ),
		);

		if ( count( $address ) > 1 ) {
			$node['address'] = $address;
		}
		if ( ! empty( $location['phone'] ) ) {
			$node['telephone'] = $location['phone'];
		}
		if ( ! empty( $location['hours'] ) ) {
			// schema.org openingHours text, e.g. "Mo-Fr 09:00-17:00".
			$node['openingHours'] = array_values( array_filter( array_map( 'trim', preg_split( '/[\n,;]+/', $location['hours'] ) ) ) );
		}
		if ( ! empty( $location['price_range'] ) ) {
			$node['priceRange'] = $location['price_range'];
		}
		if ( is_numeric( $location['lat'] ?? '' ) && is_numeric( $location['lng'] ?? '' ) ) {
			$node['geo'] = array(
				'@type'     => 'GeoCoordinates',
				'latitude'  => (float) $location['lat'],
				'longitude' => (float) $location['lng'],
			);
		}

		return (array) apply_filters( 'aeo_location_schema', $node, $location, $index );
	}

	/**
	 * Validate a schema object (or @graph).
	 *
	 * @param array<string, mixed> $schema Schema.
	 * @return array{valid: bool, errors: string[], warnings: string[]}
	 */
	public function validate( array $schema ) {
		$errors   = array();
		$warnings = array();

		$nodes = isset( $schema['@graph'] ) && is_array( $schema['@graph'] ) ? $schema['@graph'] : array( $schema );

		if ( empty( $nodes ) ) {
			$errors[] = __( 'Schema is empty', 'answer-engine-optimization' );
		}

		foreach ( $nodes as $i => $node ) {
			$prefix = count( $nodes ) > 1 ? sprintf( '#%d ', $i + 1 ) : '';
			if ( ! is_array( $node ) || empty( $node['@type'] ) ) {
				$errors[] = $prefix . __( 'Missing @type property', 'answer-engine-optimization' );
				continue;
			}
			$type = is_array( $node['@type'] ) ? (string) reset( $node['@type'] ) : (string) $node['@type'];
			if ( ! in_array( $type, self::$types, true ) ) {
				/* translators: %s: schema type */
				$errors[] = $prefix . sprintf( __( 'Unsupported schema type "%s"', 'answer-engine-optimization' ), $type );
				continue;
			}
			foreach ( self::$required[ $type ] ?? array() as $prop ) {
				if ( ! isset( $node[ $prop ] ) || '' === $node[ $prop ] || array() === $node[ $prop ] ) {
					/* translators: 1: type, 2: property */
					$errors[] = $prefix . sprintf( __( '%1$s is missing required property "%2$s"', 'answer-engine-optimization' ), $type, $prop );
				}
			}
			foreach ( self::$recommended[ $type ] ?? array() as $prop ) {
				if ( empty( $node[ $prop ] ) ) {
					/* translators: 1: type, 2: property */
					$warnings[] = $prefix . sprintf( __( '%1$s should include "%2$s"', 'answer-engine-optimization' ), $type, $prop );
				}
			}
		}

		return array(
			'valid'    => empty( $errors ),
			'errors'   => $errors,
			'warnings' => $warnings,
		);
	}

	/**
	 * Apply a schema template to many posts.
	 *
	 * @param int[]                $post_ids Post IDs.
	 * @param array<string, mixed> $template Template.
	 * @return int Number updated.
	 */
	public function bulk_apply( array $post_ids, array $template ) {
		$count = 0;
		foreach ( $post_ids as $post_id ) {
			if ( ! get_post( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
				continue;
			}
			$schema = $template;
			$title  = wp_strip_all_tags( get_the_title( $post_id ) );
			if ( in_array( $schema['@type'] ?? '', array( 'Article', 'NewsArticle', 'BlogPosting', 'TechArticle' ), true ) ) {
				$schema['headline'] = $title;
			} else {
				$schema['name'] = $schema['name'] ?? $title;
			}
			$schema['url'] = get_permalink( $post_id );
			if ( $this->save_post_schema( $post_id, $schema ) ) {
				$count++;
			}
		}
		return $count;
	}

	/**
	 * Keep one cache row per post.
	 *
	 * @param int                  $post_id Post ID.
	 * @param array<string, mixed> $schema  Schema.
	 * @return void
	 */
	private function cache_schema( $post_id, array $schema ) {
		global $wpdb;
		$table = $wpdb->prefix . 'aeo_schema_cache';
		$wpdb->delete( $table, array( 'post_id' => (int) $post_id ), array( '%d' ) );
		$wpdb->insert(
			$table,
			array(
				'post_id'     => (int) $post_id,
				'schema_type' => is_string( $schema['@type'] ?? null ) ? $schema['@type'] : 'Graph',
				'schema_data' => wp_json_encode( $schema ),
			),
			array( '%d', '%s', '%s' )
		);
	}
}
