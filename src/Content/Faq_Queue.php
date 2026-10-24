<?php
/**
 * Bulk FAQ generation with a review queue.
 *
 * Suggestions are stored per post in _aeo_faq_suggested and only published after
 * someone approves them.
 *
 * @package AEO
 */

namespace AEO\Content;

use AEO\Services\AI\AI_Manager;
use AEO\Services\AI\AI_Response;

defined( 'ABSPATH' ) || exit;

/**
 * FAQ queue.
 */
class Faq_Queue {

	const STATE = 'aeo_faq_queue_state';
	const META  = '_aeo_faq_suggested';

	/**
	 * Queue posts.
	 *
	 * @param int[] $post_ids Post IDs.
	 * @return array<string, mixed>
	 */
	public static function start( array $post_ids ) {
		$ids = array();
		foreach ( array_unique( array_map( 'intval', $post_ids ) ) as $id ) {
			if ( $id && current_user_can( 'edit_post', $id ) ) {
				$ids[] = $id;
			}
		}
		$state = array(
			'items'    => array_slice( $ids, 0, 200 ),
			'done'     => 0,
			'finished' => '',
		);
		update_option( self::STATE, $state, false );
		return self::progress( $state );
	}

	/**
	 * Process within a time budget.
	 *
	 * @param int $budget Seconds.
	 * @return array<string, mixed>
	 */
	public static function process( $budget = 20 ) {
		$state = get_option( self::STATE, array() );
		if ( empty( $state['items'] ) || ! empty( $state['finished'] ) ) {
			return self::progress( (array) $state );
		}
		$start = microtime( true );
		$ai    = ( new AI_Manager() )->get_provider();
		while ( $state['done'] < count( $state['items'] ) && ( microtime( true ) - $start ) < $budget ) {
			self::generate_for( (int) $state['items'][ $state['done'] ], $ai );
			$state['done']++;
			update_option( self::STATE, $state, false );
		}
		if ( $state['done'] >= count( $state['items'] ) ) {
			$state['finished'] = current_time( 'mysql' );
			update_option( self::STATE, $state, false );
		}
		return self::progress( $state );
	}

	/**
	 * Progress payload.
	 *
	 * @param array<string, mixed> $state State.
	 * @return array<string, mixed>
	 */
	private static function progress( array $state ) {
		return array(
			'total'    => count( (array) ( $state['items'] ?? array() ) ),
			'done'     => (int) ( $state['done'] ?? 0 ),
			'finished' => (string) ( $state['finished'] ?? '' ),
		);
	}

	/**
	 * Generate suggestions for one post.
	 *
	 * @param int                                       $post_id Post ID.
	 * @param \AEO\Services\AI\AI_Provider_Interface $ai      Provider.
	 * @return int Suggestions stored.
	 */
	public static function generate_for( $post_id, $ai ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return 0;
		}
		$existing = array();
		foreach ( (array) get_post_meta( $post_id, '_aeo_faq_items', true ) as $f ) {
			if ( is_array( $f ) && ! empty( $f['question'] ) ) {
				$existing[ strtolower( $f['question'] ) ] = true;
			}
		}
		$faqs = array_values(
			array_filter(
				AI_Response::normalize_faqs( $ai->generate_faqs( $post->post_content, 5 ) ),
				static function ( $f ) use ( $existing ) {
					return '' !== $f['answer'] && ! isset( $existing[ strtolower( $f['question'] ) ] );
				}
			)
		);
		if ( $faqs ) {
			update_post_meta( $post_id, self::META, wp_slash( $faqs ) );
		}
		return count( $faqs );
	}

	/**
	 * Posts waiting for review.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function pending() {
		$posts = get_posts(
			array(
				'post_type'      => 'any',
				'post_status'    => array( 'publish', 'draft', 'pending', 'future', 'private' ),
				'posts_per_page' => 200,
				'meta_key'       => self::META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			)
		);
		$out = array();
		foreach ( $posts as $post ) {
			if ( ! current_user_can( 'edit_post', $post->ID ) ) {
				continue;
			}
			$out[] = array(
				'id'          => $post->ID,
				'title'       => wp_strip_all_tags( get_the_title( $post ) ),
				'url'         => get_permalink( $post ),
				'suggestions' => array_values( (array) get_post_meta( $post->ID, self::META, true ) ),
				'existing'    => count( (array) get_post_meta( $post->ID, '_aeo_faq_items', true ) ),
			);
		}
		return $out;
	}

	/**
	 * Approve (possibly edited) suggestions.
	 *
	 * @param int                                $post_id Post ID.
	 * @param array<int, array<string, string>>  $items   Items to add.
	 * @return int Total FAQs on the post.
	 */
	public static function approve( $post_id, array $items ) {
		$current = get_post_meta( $post_id, '_aeo_faq_items', true );
		$current = is_array( $current ) ? $current : array();
		$merged  = AI_Response::normalize_faqs( array_merge( $current, $items ) );
		$merged  = array_values( array_filter( $merged, static function ( $f ) {
			return '' !== $f['answer'];
		} ) );
		update_post_meta( $post_id, '_aeo_faq_items', wp_slash( $merged ) );
		delete_post_meta( $post_id, self::META );
		( new \AEO\Services\Score_Engine() )->calculate( $post_id );
		return count( $merged );
	}

	/**
	 * Discard suggestions.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public static function reject( $post_id ) {
		delete_post_meta( $post_id, self::META );
	}
}
