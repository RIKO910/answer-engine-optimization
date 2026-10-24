<?php
/**
 * Hotel module: lodging schema, Quotel Hotel Booking rooms, guest ratings,
 * nearby attractions, hotel FAQ template and an llms.txt hotel section.
 *
 * Quotel stores rooms as the "qhb_room" post type with meta such as
 * _qhb_base_price, _qhb_max_adults, _qhb_bed_type and the qhb_amenity taxonomy.
 *
 * @package AEO
 */

namespace AEO\Integrations;

use AEO\Core\Settings;
use AEO\Services\Schema_Service;

defined( 'ABSPATH' ) || exit;

/**
 * Hotel integration.
 */
class Hotel {

	const ROOM_TYPE = 'qhb_room';
	const LODGING   = array( 'Hotel', 'Resort', 'BedAndBreakfast', 'Motel', 'Hostel', 'LodgingBusiness' );

	/**
	 * Register.
	 *
	 * @return void
	 */
	public static function register() {
		add_filter( 'aeo_post_schema_nodes', array( __CLASS__, 'room_nodes' ), 10, 2 );
		add_filter( 'aeo_location_schema', array( __CLASS__, 'enrich_hotel' ), 10, 3 );
		add_filter( 'aeo_llms_txt_sections', array( __CLASS__, 'llms_section' ) );

		// Star ratings on room reviews (Quotel uses normal comments as reviews).
		add_action( 'comment_form_logged_in_after', array( __CLASS__, 'rating_field' ) );
		add_action( 'comment_form_after_fields', array( __CLASS__, 'rating_field' ) );
		add_action( 'comment_post', array( __CLASS__, 'save_rating' ) );
		add_filter( 'comment_text', array( __CLASS__, 'show_rating' ), 10, 2 );
	}

	/**
	 * Is Quotel (or a compatible rooms post type) active?
	 *
	 * @return bool
	 */
	public static function has_rooms() {
		return post_type_exists( self::ROOM_TYPE );
	}

	/**
	 * First lodging location, if any.
	 *
	 * @return array{0: array<string, string>, 1: int}|null Location and index.
	 */
	public static function hotel_location() {
		foreach ( array_values( (array) get_option( 'aeo_local_locations', array() ) ) as $i => $loc ) {
			if ( is_array( $loc ) && in_array( $loc['type'] ?? '', self::LODGING, true ) ) {
				return array( $loc, $i );
			}
		}
		return null;
	}

	/**
	 * @id of the hotel node.
	 *
	 * @return string
	 */
	public static function hotel_id() {
		$h = self::hotel_location();
		return trailingslashit( home_url() ) . '#location-' . ( $h ? $h[1] + 1 : 1 );
	}

	/**
	 * Quotel option with fallback.
	 *
	 * @param string $key     Option suffix.
	 * @param string $default Default.
	 * @return string
	 */
	private static function qhb( $key, $default = '' ) {
		return (string) get_option( 'qhb_' . $key, $default );
	}

	/**
	 * Add check-in/out, stars, amenities, rooms, ratings and attractions to a lodging node.
	 *
	 * @param array<string, mixed>  $node     Node.
	 * @param array<string, string> $location Location fields.
	 * @param int                   $index    Index.
	 * @return array<string, mixed>
	 */
	public static function enrich_hotel( $node, $location, $index ) {
		if ( ! in_array( $node['@type'] ?? '', self::LODGING, true ) ) {
			return $node;
		}

		$checkin  = ! empty( $location['checkin'] ) ? $location['checkin'] : self::qhb( 'check_in_time' );
		$checkout = ! empty( $location['checkout'] ) ? $location['checkout'] : self::qhb( 'check_out_time' );
		if ( preg_match( '/^\d{1,2}:\d{2}$/', $checkin ) ) {
			$node['checkinTime'] = $checkin . ':00';
		}
		if ( preg_match( '/^\d{1,2}:\d{2}$/', $checkout ) ) {
			$node['checkoutTime'] = $checkout . ':00';
		}
		if ( ! empty( $location['star_rating'] ) && is_numeric( $location['star_rating'] ) ) {
			$node['starRating'] = array(
				'@type'       => 'Rating',
				'ratingValue' => (float) $location['star_rating'],
			);
		}
		if ( isset( $location['pets_allowed'] ) && '' !== $location['pets_allowed'] ) {
			$node['petsAllowed'] = 'yes' === $location['pets_allowed'];
		}

		$amenities = array_filter( array_map( 'trim', explode( ',', (string) ( $location['amenities'] ?? '' ) ) ) );
		foreach ( $amenities as $amenity ) {
			$node['amenityFeature'][] = array(
				'@type' => 'LocationFeatureSpecification',
				'name'  => $amenity,
				'value' => true,
			);
		}

		if ( self::has_rooms() ) {
			$rooms = get_posts(
				array(
					'post_type'      => self::ROOM_TYPE,
					'post_status'    => 'publish',
					'posts_per_page' => 100,
					'fields'         => 'ids',
				)
			);
			if ( $rooms ) {
				$units = 0;
				foreach ( $rooms as $room_id ) {
					$units                  += max( 1, (int) get_post_meta( $room_id, '_qhb_total_units', true ) );
					$node['containsPlace'][] = array( '@id' => get_permalink( $room_id ) . '#room' );
				}
				if ( empty( $location['number_of_rooms'] ) ) {
					$node['numberOfRooms'] = $units;
				}
				if ( empty( $node['priceRange'] ) ) {
					$prices = array_filter( array_map( static function ( $id ) {
						return (float) get_post_meta( $id, '_qhb_base_price', true );
					}, $rooms ) );
					if ( $prices ) {
						$node['priceRange'] = self::qhb( 'currency', 'USD' ) . ' ' . min( $prices ) . '–' . max( $prices );
					}
				}
			}
		}
		if ( ! empty( $location['number_of_rooms'] ) ) {
			$node['numberOfRooms'] = (int) $location['number_of_rooms'];
		}

		$rating = self::aggregate_rating();
		if ( $rating ) {
			$node['aggregateRating'] = $rating;
		}

		return $node;
	}

	/**
	 * Average guest rating across room reviews.
	 *
	 * @return array<string, mixed>|null
	 */
	public static function aggregate_rating() {
		if ( ! Settings::get( 'review_ratings', 0 ) || ! self::has_rooms() ) {
			return null;
		}
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT AVG(CAST(cm.meta_value AS DECIMAL(3,2))) AS avg_rating, COUNT(*) AS total
				FROM {$wpdb->commentmeta} cm
				INNER JOIN {$wpdb->comments} c ON c.comment_ID = cm.comment_id
				INNER JOIN {$wpdb->posts} p ON p.ID = c.comment_post_ID
				WHERE cm.meta_key = 'aeo_rating' AND c.comment_approved = '1' AND p.post_type = %s",
				self::ROOM_TYPE
			),
			ARRAY_A
		);
		if ( ! $row || (int) $row['total'] < 1 ) {
			return null;
		}
		return array(
			'@type'       => 'AggregateRating',
			'ratingValue' => round( (float) $row['avg_rating'], 1 ),
			'reviewCount' => (int) $row['total'],
			'bestRating'  => 5,
			'worstRating' => 1,
		);
	}

	/**
	 * Nearby attractions as TouristAttraction nodes.
	 *
	 * Format per line: Name | distance | URL (distance and URL optional).
	 *
	 * @param array<string, string> $location Location.
	 * @return array<int, array<string, mixed>>
	 */
	public static function attraction_nodes( array $location ) {
		$nodes = array();
		$lines = array_filter( array_map( 'trim', preg_split( '/[\r\n]+/', (string) ( $location['attractions'] ?? '' ) ) ) );
		foreach ( array_values( $lines ) as $i => $line ) {
			$parts = array_map( 'trim', explode( '|', $line ) );
			if ( '' === $parts[0] ) {
				continue;
			}
			$node = array(
				'@type' => 'TouristAttraction',
				'@id'   => trailingslashit( home_url() ) . '#attraction-' . ( $i + 1 ),
				'name'  => $parts[0],
			);
			if ( ! empty( $parts[1] ) ) {
				/* translators: 1: distance, 2: hotel name */
				$node['description'] = sprintf( __( '%1$s from %2$s', 'answer-engine-optimization' ), $parts[1], $location['name'] ?? get_bloginfo( 'name' ) );
			}
			if ( ! empty( $parts[2] ) && wp_http_validate_url( $parts[2] ) ) {
				$node['url'] = esc_url_raw( $parts[2] );
			}
			$nodes[] = $node;
		}
		return $nodes;
	}

	/**
	 * HotelRoom (or Suite) + Offer nodes on Quotel room pages.
	 *
	 * @param array<int, array<string, mixed>> $nodes   Nodes.
	 * @param int                              $post_id Post ID.
	 * @return array<int, array<string, mixed>>
	 */
	public static function room_nodes( $nodes, $post_id ) {
		if ( self::ROOM_TYPE !== get_post_type( $post_id ) || ! Settings::get( 'hotel_rooms_schema', 1 ) ) {
			return $nodes;
		}
		$room = self::room_node( $post_id );
		if ( $room ) {
			$nodes[] = $room;
			$offer   = self::room_offer( $post_id );
			if ( $offer ) {
				$nodes[] = $offer;
			}
		}
		return $nodes;
	}

	/**
	 * Room node.
	 *
	 * @param int $post_id Room ID.
	 * @return array<string, mixed>
	 */
	public static function room_node( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return array();
		}
		$m     = static function ( $key ) use ( $post_id ) {
			return get_post_meta( $post_id, '_qhb_' . $key, true );
		};
		$types = wp_get_post_terms( $post_id, 'qhb_room_type', array( 'fields' => 'names' ) );
		$is_suite = false;
		foreach ( is_array( $types ) ? $types : array() as $t ) {
			if ( false !== stripos( $t, 'suite' ) ) {
				$is_suite = true;
			}
		}
		if ( false !== stripos( $post->post_title, 'suite' ) ) {
			$is_suite = true;
		}

		$node = array(
			'@type'           => $is_suite ? 'Suite' : 'HotelRoom',
			'@id'             => get_permalink( $post_id ) . '#room',
			'name'            => wp_strip_all_tags( get_the_title( $post ) ),
			'url'             => get_permalink( $post_id ),
			'description'     => has_excerpt( $post ) ? wp_strip_all_tags( get_the_excerpt( $post ) ) : Schema_Service::plain_text( $post->post_content, 40 ),
			'containedInPlace' => array( '@id' => self::hotel_id() ),
		);

		if ( has_post_thumbnail( $post_id ) ) {
			$img = wp_get_attachment_image_src( get_post_thumbnail_id( $post_id ), 'full' );
			if ( $img ) {
				$node['image'] = $img[0];
			}
		}

		$bed_type = trim( (string) $m( 'bed_type' ) );
		$beds     = (int) $m( 'beds' );
		if ( $bed_type || $beds ) {
			$node['bed'] = array_filter(
				array(
					'@type'        => 'BedDetails',
					'typeOfBed'    => $bed_type,
					'numberOfBeds' => $beds ? $beds : null,
				)
			);
		}

		$adults   = (int) $m( 'max_adults' );
		$children = (int) $m( 'max_children' );
		if ( $adults ) {
			$node['occupancy'] = array(
				'@type'    => 'QuantitativeValue',
				'maxValue' => $adults + $children,
				'unitText' => $children ? sprintf( '%d adults, %d children', $adults, $children ) : sprintf( '%d adults', $adults ),
			);
		}

		$size = (float) $m( 'room_size' );
		if ( $size > 0 ) {
			$node['floorSize'] = array(
				'@type'    => 'QuantitativeValue',
				'value'    => $size,
				'unitCode' => (string) apply_filters( 'aeo_room_size_unit', 'MTK' ), // MTK = square metre, FTK = square foot.
			);
		}

		$features = array();
		$amenities = wp_get_post_terms( $post_id, 'qhb_amenity', array( 'fields' => 'names' ) );
		foreach ( is_array( $amenities ) ? $amenities : array() as $a ) {
			$features[] = array( $a, true );
		}
		if ( $m( 'view_type' ) ) {
			/* translators: %s: view */
			$features[] = array( sprintf( __( '%s view', 'answer-engine-optimization' ), $m( 'view_type' ) ), true );
		}
		if ( '' !== (string) $m( 'smoking_allowed' ) ) {
			$features[] = array( __( 'Smoking allowed', 'answer-engine-optimization' ), 'yes' === $m( 'smoking_allowed' ) );
		}
		foreach ( $features as $f ) {
			$node['amenityFeature'][] = array(
				'@type' => 'LocationFeatureSpecification',
				'name'  => $f[0],
				'value' => $f[1],
			);
		}
		if ( '' !== (string) $m( 'pets_allowed' ) ) {
			$node['petsAllowed'] = 'yes' === $m( 'pets_allowed' );
		}

		return (array) apply_filters( 'aeo_room_schema', $node, $post_id );
	}

	/**
	 * Nightly price offer for a room.
	 *
	 * @param int $post_id Room ID.
	 * @return array<string, mixed>
	 */
	public static function room_offer( $post_id ) {
		$price = (float) get_post_meta( $post_id, '_qhb_base_price', true );
		if ( $price <= 0 ) {
			return array();
		}
		return array(
			'@type'              => 'Offer',
			'@id'                => get_permalink( $post_id ) . '#offer',
			'url'                => get_permalink( $post_id ),
			'itemOffered'        => array( '@id' => get_permalink( $post_id ) . '#room' ),
			'businessFunction'   => 'http://purl.org/goodrelations/v1#LeaseOut',
			'availability'       => 'https://schema.org/InStock',
			'priceSpecification' => array(
				'@type'         => 'UnitPriceSpecification',
				'price'         => $price,
				'priceCurrency' => self::qhb( 'currency', 'USD' ),
				'unitCode'      => 'DAY',
				'unitText'      => __( 'per night', 'answer-engine-optimization' ),
			),
			'offeredBy'          => array( '@id' => self::hotel_id() ),
		);
	}

	/**
	 * Hotel section for llms.txt.
	 *
	 * @param array<string, string> $sections Sections.
	 * @return array<string, string>
	 */
	public static function llms_section( $sections ) {
		$hotel = self::hotel_location();
		if ( ! $hotel && ! self::has_rooms() ) {
			return $sections;
		}
		$lines = array();
		if ( $hotel ) {
			list( $loc ) = $hotel;
			$address     = implode( ', ', array_filter( array( $loc['address'] ?? '', $loc['city'] ?? '', $loc['region'] ?? '', $loc['country'] ?? '' ) ) );
			$lines[]     = '- Name: ' . $loc['name'];
			if ( $address ) {
				$lines[] = '- Address: ' . $address;
			}
			if ( ! empty( $loc['phone'] ) ) {
				$lines[] = '- Phone: ' . $loc['phone'];
			}
			if ( ! empty( $loc['star_rating'] ) ) {
				$lines[] = '- Star rating: ' . $loc['star_rating'];
			}
			if ( ! empty( $loc['amenities'] ) ) {
				$lines[] = '- Amenities: ' . $loc['amenities'];
			}
			if ( isset( $loc['pets_allowed'] ) && '' !== $loc['pets_allowed'] ) {
				$lines[] = '- Pets: ' . ( 'yes' === $loc['pets_allowed'] ? 'allowed' : 'not allowed' );
			}
			foreach ( self::attraction_nodes( $loc ) as $a ) {
				$lines[] = '- Nearby: ' . $a['name'] . ( isset( $a['description'] ) ? ' (' . $a['description'] . ')' : '' );
			}
		}
		$in  = self::qhb( 'check_in_time' );
		$out = self::qhb( 'check_out_time' );
		if ( $in || $out ) {
			$lines[] = '- Check-in: ' . ( $in ? $in : '—' ) . ', check-out: ' . ( $out ? $out : '—' );
		}
		$sections['Hotel'] = implode( "\n", $lines );

		if ( self::has_rooms() ) {
			$rooms = get_posts(
				array(
					'post_type'      => self::ROOM_TYPE,
					'post_status'    => 'publish',
					'posts_per_page' => 50,
					'orderby'        => 'menu_order title',
					'order'          => 'ASC',
				)
			);
			$room_lines = array();
			foreach ( $rooms as $room ) {
				$price  = (float) get_post_meta( $room->ID, '_qhb_base_price', true );
				$adults = (int) get_post_meta( $room->ID, '_qhb_max_adults', true );
				$bits   = array();
				if ( $price > 0 ) {
					$bits[] = 'from ' . self::qhb( 'currency', 'USD' ) . ' ' . $price . ' per night';
				}
				if ( $adults ) {
					$bits[] = 'up to ' . ( $adults + (int) get_post_meta( $room->ID, '_qhb_max_children', true ) ) . ' guests';
				}
				$bed = (string) get_post_meta( $room->ID, '_qhb_bed_type', true );
				if ( $bed ) {
					$bits[] = $bed . ' bed';
				}
				$room_lines[] = '- [' . wp_strip_all_tags( get_the_title( $room ) ) . '](' . get_permalink( $room ) . ')' . ( $bits ? ': ' . implode( ', ', $bits ) : '' );
			}
			if ( $room_lines ) {
				$sections['Rooms and rates'] = implode( "\n", $room_lines );
			}
		}
		return $sections;
	}

	/**
	 * Hotel FAQ starter set, pre-filled from settings where possible.
	 *
	 * @return array<int, array{question: string, answer: string}>
	 */
	public static function faq_template() {
		$hotel = self::hotel_location();
		$loc   = $hotel ? $hotel[0] : array();
		$name  = $loc['name'] ?? get_bloginfo( 'name' );
		$in    = ! empty( $loc['checkin'] ) ? $loc['checkin'] : self::qhb( 'check_in_time' );
		$out   = ! empty( $loc['checkout'] ) ? $loc['checkout'] : self::qhb( 'check_out_time' );
		$pets  = $loc['pets_allowed'] ?? '';
		$items = array(
			array( 'What time is check-in and check-out?', $in && $out ? sprintf( 'Check-in is from %s and check-out is until %s.', $in, $out ) : '' ),
			array( sprintf( 'Does %s have free parking?', $name ), '' ),
			array( 'Is breakfast included?', '' ),
			array( 'Are pets allowed?', 'yes' === $pets ? 'Yes, pets are welcome.' : ( 'no' === $pets ? 'No, pets are not allowed.' : '' ) ),
			array( 'What is the cancellation policy?', '' ),
			array( sprintf( 'How far is %s from the airport?', $name ), '' ),
			array( 'Is there free Wi-Fi?', false !== stripos( (string) ( $loc['amenities'] ?? '' ), 'wifi' ) || false !== stripos( (string) ( $loc['amenities'] ?? '' ), 'wi-fi' ) ? 'Yes, free Wi-Fi is available throughout the property.' : '' ),
			array( 'Do you offer airport transfers?', '' ),
			array( 'Is the hotel family friendly?', '' ),
			array( 'Can I request early check-in or late check-out?', '' ),
		);
		return array_map(
			static function ( $i ) {
				return array(
					'question' => $i[0],
					'answer'   => $i[1],
				);
			},
			$items
		);
	}

	/* ---------- Star ratings on room reviews ---------- */

	/**
	 * Rating select on the room review form.
	 *
	 * @return void
	 */
	public static function rating_field() {
		if ( ! Settings::get( 'review_ratings', 0 ) || ! is_singular( self::ROOM_TYPE ) ) {
			return;
		}
		echo '<p class="comment-form-aeo-rating"><label for="aeo_rating">' . esc_html__( 'Your rating', 'answer-engine-optimization' ) . '</label> <select name="aeo_rating" id="aeo_rating"><option value="">' . esc_html__( 'Choose…', 'answer-engine-optimization' ) . '</option>';
		for ( $i = 5; $i >= 1; $i-- ) {
			/* translators: %d: stars */
			echo '<option value="' . (int) $i . '">' . esc_html( sprintf( _n( '%d star', '%d stars', $i, 'answer-engine-optimization' ), $i ) ) . '</option>';
		}
		echo '</select></p>';
	}

	/**
	 * Save rating.
	 *
	 * @param int $comment_id Comment ID.
	 * @return void
	 */
	public static function save_rating( $comment_id ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- core comment form; rating is validated.
		$rating = isset( $_POST['aeo_rating'] ) ? (int) $_POST['aeo_rating'] : 0;
		$post   = get_post( (int) get_comment( $comment_id )->comment_post_ID );
		if ( $rating >= 1 && $rating <= 5 && $post && self::ROOM_TYPE === $post->post_type ) {
			add_comment_meta( $comment_id, 'aeo_rating', $rating, true );
		}
	}

	/**
	 * Show stars above the review text.
	 *
	 * @param string           $text    Text.
	 * @param \WP_Comment|null $comment Comment.
	 * @return string
	 */
	public static function show_rating( $text, $comment = null ) {
		if ( ! $comment || is_admin() ) {
			return $text;
		}
		$rating = (int) get_comment_meta( $comment->comment_ID, 'aeo_rating', true );
		if ( $rating < 1 ) {
			return $text;
		}
		/* translators: %d: rating */
		$label = sprintf( __( 'Rated %d out of 5', 'answer-engine-optimization' ), $rating );
		return '<p class="aeo-rating" aria-label="' . esc_attr( $label ) . '">' . str_repeat( '★', $rating ) . str_repeat( '☆', 5 - $rating ) . '</p>' . $text;
	}
}
