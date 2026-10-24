<?php
/**
 * Content freshness scanner.
 *
 * @package AEO
 */

namespace AEO\Content;

use AEO\Core\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Finds stale pages: not updated for months, or mentioning past years as current.
 */
class Freshness {

	/**
	 * Scan.
	 *
	 * @param int $months Age threshold.
	 * @return array<int, array<string, mixed>>
	 */
	public static function scan( $months = 12 ) {
		$year    = (int) current_time( 'Y' );
		$cutoff  = time() - $months * MONTH_IN_SECONDS;
		$results = array();

		$posts = get_posts(
			array(
				'post_type'      => Settings::get_post_types(),
				'post_status'    => 'publish',
				'posts_per_page' => 500,
				'orderby'        => 'modified',
				'order'          => 'ASC',
				'has_password'   => false,
			)
		);

		foreach ( $posts as $post ) {
			$reasons  = array();
			$modified = (int) get_post_modified_time( 'U', true, $post );
			$age      = (int) floor( ( time() - $modified ) / MONTH_IN_SECONDS );

			if ( $modified < $cutoff ) {
				/* translators: %d: months */
				$reasons[] = sprintf( _n( 'Not updated for %d month', 'Not updated for %d months', $age, 'answer-engine-optimization' ), $age );
			}

			$title = wp_strip_all_tags( $post->post_title );
			if ( preg_match_all( '/\b(20\d\d)\b/', $title, $m ) ) {
				foreach ( array_unique( $m[1] ) as $y ) {
					if ( (int) $y < $year && (int) $y >= $year - 6 ) {
						/* translators: %s: year */
						$reasons[] = sprintf( __( 'Title mentions %s', 'answer-engine-optimization' ), $y );
					}
				}
			}

			$text = wp_strip_all_tags( strip_shortcodes( $post->post_content ) );
			if ( preg_match_all( '/\b(?:in|for|of|since|updated|as of)\s+(20\d\d)\b|\b(20\d\d)\s+(?:guide|edition|update|prices?|rates?|season|list)\b/i', $text, $m ) ) {
				$years = array_unique( array_filter( array_merge( $m[1], $m[2] ) ) );
				foreach ( $years as $y ) {
					if ( (int) $y < $year && (int) $y >= $year - 6 ) {
						/* translators: %s: year */
						$reasons[] = sprintf( __( 'Text refers to %s as current', 'answer-engine-optimization' ), $y );
						break;
					}
				}
			}

			if ( ! $reasons ) {
				continue;
			}

			$results[] = array(
				'id'          => $post->ID,
				'title'       => $title,
				'url'         => get_permalink( $post ),
				'edit_url'    => get_edit_post_link( $post->ID, 'raw' ),
				'type'        => $post->post_type,
				'modified'    => get_post_modified_time( 'Y-m-d', true, $post ),
				'months_old'  => $age,
				'reasons'     => array_values( array_unique( $reasons ) ),
				'reviewed_at' => (string) get_post_meta( $post->ID, '_aeo_reviewed_at', true ),
			);
		}

		return $results;
	}

	/**
	 * Mark a page as reviewed: bumps the modified date (and the schema's dateModified).
	 *
	 * @param int $post_id Post ID.
	 * @return bool|\WP_Error
	 */
	public static function mark_reviewed( $post_id ) {
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return new \WP_Error( 'aeo_forbidden', __( 'You cannot edit this post.', 'answer-engine-optimization' ) );
		}
		$result = wp_update_post( array( 'ID' => (int) $post_id ), true );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		update_post_meta( $post_id, '_aeo_reviewed_at', current_time( 'mysql' ) );
		return true;
	}
}
