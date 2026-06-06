<?php
/**
 * Schema builder service.
 *
 * @package AEO
 */

namespace AEO\Services;

defined( 'ABSPATH' ) || exit;

/**
 * Schema generation, validation, and management.
 */
class Schema_Service {

	/**
	 * Supported schema types.
	 *
	 * @var array<int, string>
	 */
	public static $types = array(
		'Article', 'NewsArticle', 'BlogPosting', 'TechArticle',
		'FAQPage', 'QAPage', 'HowTo',
		'Product', 'Offer', 'AggregateRating', 'Review',
		'LocalBusiness', 'Restaurant', 'MedicalBusiness',
		'Person', 'Organization', 'Corporation', 'NGO',
		'Event', 'OnlineEvent', 'BusinessEvent',
		'Recipe', 'Course', 'CourseInstance', 'EducationalOrganization',
		'JobPosting', 'HiringOrganization',
		'VideoObject', 'ImageObject', 'AudioObject',
		'BreadcrumbList', 'SiteLinksSearchBox', 'WebSite', 'WebPage',
		'SoftwareApplication', 'MobileApplication',
		'Book', 'Movie', 'TVSeries', 'MusicAlbum',
		'MedicalCondition', 'Drug', 'MedicalGuideline',
		'Vehicle', 'AutoDealer',
		'Service', 'ProfessionalService', 'LegalService', 'FinancialService',
	);

	/**
	 * Get schema for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return array<string, mixed>
	 */
	public function get_post_schema( $post_id ) {
		$stored = get_post_meta( $post_id, '_aeo_schema', true );
		if ( $stored ) {
			$decoded = json_decode( $stored, true );
			if ( is_array( $decoded ) ) {
				return $decoded;
			}
		}

		return $this->auto_generate( $post_id );
	}

	/**
	 * Save schema for a post.
	 *
	 * @param int                  $post_id Post ID.
	 * @param array<string, mixed> $schema  Schema data.
	 * @return bool
	 */
	public function save_post_schema( $post_id, array $schema ) {
		$validation = $this->validate( $schema );
		if ( ! $validation['valid'] ) {
			return false;
		}

		update_post_meta( $post_id, '_aeo_schema', wp_json_encode( $schema ) );
		$this->cache_schema( $post_id, $schema );

		return true;
	}

	/**
	 * Auto-generate schema based on post type.
	 *
	 * @param int $post_id Post ID.
	 * @return array<string, mixed>
	 */
	public function auto_generate( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return array();
		}

		$type = 'Article';
		if ( 'product' === $post->post_type && class_exists( 'WooCommerce' ) ) {
			$type = 'Product';
		} elseif ( 'page' === $post->post_type ) {
			$type = 'WebPage';
		}

		$schema = array(
			'@context'    => 'https://schema.org',
			'@type'       => $type,
			'headline'    => get_the_title( $post_id ),
			'description' => wp_trim_words( wp_strip_all_tags( $post->post_content ), 30 ),
			'datePublished' => get_the_date( 'c', $post_id ),
			'dateModified'  => get_the_modified_date( 'c', $post_id ),
			'url'           => get_permalink( $post_id ),
		);

		if ( has_post_thumbnail( $post_id ) ) {
			$image = wp_get_attachment_image_src( get_post_thumbnail_id( $post_id ), 'full' );
			if ( $image ) {
				$schema['image'] = $image[0];
			}
		}

		$faqs = get_post_meta( $post_id, '_aeo_faq_items', true );
		if ( ! empty( $faqs ) && is_array( $faqs ) ) {
			$schema['@type'] = 'FAQPage';
			$schema['mainEntity'] = array_map(
				function ( $faq ) {
					return array(
						'@type'          => 'Question',
						'name'           => $faq['question'] ?? '',
						'acceptedAnswer' => array(
							'@type' => 'Answer',
							'text'  => $faq['answer'] ?? '',
						),
					);
				},
				$faqs
			);
		}

		return $schema;
	}

	/**
	 * Get global site schema.
	 *
	 * @return array<string, mixed>
	 */
	public function get_global_schema() {
		$global   = get_option( 'aeo_optimizer_schema_global', array() );
		$settings = \AEO\Core\Settings::get( 'business_info', array() );

		$website = array(
			'@context' => 'https://schema.org',
			'@type'    => 'WebSite',
			'name'     => get_bloginfo( 'name' ),
			'url'      => home_url(),
			'potentialAction' => array(
				'@type'       => 'SearchAction',
				'target'      => home_url( '/?s={search_term_string}' ),
				'query-input' => 'required name=search_term_string',
			),
		);

		$organization = array(
			'@context'    => 'https://schema.org',
			'@type'       => 'Organization',
			'name'        => $settings['name'] ?? get_bloginfo( 'name' ),
			'description' => $settings['description'] ?? get_bloginfo( 'description' ),
			'url'         => home_url(),
		);

		if ( ! empty( $settings['logo_url'] ) ) {
			$organization['logo'] = $settings['logo_url'];
		}

		if ( ! empty( $settings['social'] ) ) {
			$organization['sameAs'] = array_values( $settings['social'] );
		}

		return array(
			'website'      => array_merge( $website, $global['website'] ?? array() ),
			'organization' => array_merge( $organization, $global['organization'] ?? array() ),
		);
	}

	/**
	 * Validate schema structure.
	 *
	 * @param array<string, mixed> $schema Schema data.
	 * @return array<string, mixed>
	 */
	public function validate( array $schema ) {
		$errors = array();

		if ( empty( $schema['@type'] ) ) {
			$errors[] = __( 'Missing @type property', 'answer-engine-optimization' );
		}

		if ( ! in_array( $schema['@type'] ?? '', self::$types, true ) ) {
			$errors[] = __( 'Unknown schema type', 'answer-engine-optimization' );
		}

		return array(
			'valid'  => empty( $errors ),
			'errors' => $errors,
		);
	}

	/**
	 * Apply schema template to multiple posts.
	 *
	 * @param array<int>           $post_ids Post IDs.
	 * @param array<string, mixed> $template Schema template.
	 * @return int Number of posts updated.
	 */
	public function bulk_apply( array $post_ids, array $template ) {
		$count = 0;
		foreach ( $post_ids as $post_id ) {
			$schema = $template;
			$schema['headline'] = get_the_title( $post_id );
			$schema['url']      = get_permalink( $post_id );
			if ( $this->save_post_schema( $post_id, $schema ) ) {
				$count++;
			}
		}
		return $count;
	}

	/**
	 * Cache schema in custom table.
	 *
	 * @param int                  $post_id Post ID.
	 * @param array<string, mixed> $schema  Schema data.
	 * @return void
	 */
	private function cache_schema( $post_id, array $schema ) {
		global $wpdb;
		$table = $wpdb->prefix . 'aeo_schema_cache';

		$wpdb->replace(
			$table,
			array(
				'post_id'     => $post_id,
				'schema_type' => $schema['@type'] ?? 'Unknown',
				'schema_data' => wp_json_encode( $schema ),
			),
			array( '%d', '%s', '%s' )
		);
	}
}
