<?php
/**
 * Add FAQ items to a page (merge with existing, dedupe by question).
 *
 * Used by the hotel FAQ starter and, in Pro, by the bulk FAQ review queue.
 *
 * @package AEO
 */

namespace AEO\Content;

use AEO\Services\AI\AI_Response;

defined( 'ABSPATH' ) || exit;

/**
 * FAQ storage helper.
 */
class Faq_Store {

	const META = '_aeo_faq_items';

	/**
	 * Merge items into a post's FAQs. Items without an answer are skipped.
	 *
	 * @param int                               $post_id Post ID.
	 * @param array<int, array<string, string>> $items   Items (question, answer).
	 * @return int Total FAQs on the post afterwards.
	 */
	public static function add( $post_id, array $items ) {
		$current = get_post_meta( $post_id, self::META, true );
		$current = is_array( $current ) ? $current : array();
		$merged  = AI_Response::normalize_faqs( array_merge( $current, $items ) );
		$merged  = array_values(
			array_filter(
				$merged,
				static function ( $f ) {
					return '' !== $f['answer'];
				}
			)
		);
		update_post_meta( $post_id, self::META, wp_slash( $merged ) );
		( new \AEO\Services\Score_Engine() )->calculate( $post_id );
		return count( $merged );
	}
}
