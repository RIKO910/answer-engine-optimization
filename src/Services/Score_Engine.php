<?php
/**
 * AEO Score calculation engine.
 *
 * @package AEO
 */

namespace AEO\Services;

use AEO\Core\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Calculates proprietary 0-100 AEO readiness scores.
 */
class Score_Engine {

	/**
	 * Calculate and store the AEO score for a saved post.
	 *
	 * @param int $post_id Post ID.
	 * @return array<string, mixed>
	 */
	public function calculate( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return array(
				'score'   => 0,
				'factors' => array(),
				'checks'  => array(),
			);
		}
		$result = $this->compute( $this->context_from_post( $post ) );
		update_post_meta( $post_id, '_aeo_score', $result['score'] );
		update_post_meta( $post_id, '_aeo_score_checks', wp_slash( wp_json_encode( $result['checks'] ) ) );
		return $result;
	}

	/**
	 * Score unsaved editor content (block editor sidebar). Nothing is stored.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $title   Title.
	 * @param string $content Content (block markup).
	 * @return array<string, mixed>
	 */
	public function preview( $post_id, $title, $content ) {
		$post = get_post( $post_id );
		$ctx  = $post ? $this->context_from_post( $post ) : $this->context_from_post( new \WP_Post( (object) array( 'ID' => 0, 'post_type' => 'post', 'post_author' => get_current_user_id() ) ) );
		$ctx['title']   = (string) $title;
		$ctx['content'] = (string) $content;
		$blocks         = function_exists( 'parse_blocks' ) ? parse_blocks( (string) $content ) : array();
		$ctx['block_faqs']       = 0;
		$ctx['block_answer']     = false;
		$ctx['block_takeaways']  = false;
		$walk = static function ( $blocks ) use ( &$walk, &$ctx ) {
			foreach ( $blocks as $b ) {
				if ( 'aeo/faq' === $b['blockName'] ) {
					$ctx['block_faqs'] += count( \AEO\Content\Blocks::faq_items( (array) $b['attrs'] ) );
				} elseif ( 'aeo/direct-answer' === $b['blockName'] && '' !== trim( wp_strip_all_tags( (string) ( $b['attrs']['answer'] ?? '' ) ) ) ) {
					$ctx['block_answer'] = true;
				} elseif ( 'aeo/key-takeaways' === $b['blockName'] ) {
					$ctx['block_takeaways'] = true;
				}
				if ( ! empty( $b['innerBlocks'] ) ) {
					$walk( $b['innerBlocks'] );
				}
			}
		};
		$walk( $blocks );
		return $this->compute( $ctx );
	}

	/**
	 * Build the scoring context from a saved post.
	 *
	 * @param \WP_Post $post Post.
	 * @return array<string, mixed>
	 */
	private function context_from_post( \WP_Post $post ) {
		$id      = (int) $post->ID;
		$found   = $id ? \AEO\Content\Content_Extractor::extract( $id ) : array( 'faqs' => array(), 'direct_answer' => false, 'takeaways' => false );
		$meta    = $id ? get_post_meta( $id, '_aeo_faq_items', true ) : array();
		return array(
			'post_id'         => $id,
			'post_type'       => $post->post_type,
			'title'           => (string) $post->post_title,
			'content'         => (string) $post->post_content,
			'direct_answer'   => $id ? trim( (string) get_post_meta( $id, '_aeo_direct_answer', true ) ) : '',
			'target_question' => $id ? trim( (string) get_post_meta( $id, '_aeo_target_question', true ) ) : '',
			'meta_faqs'       => is_array( $meta ) ? count( $meta ) : 0,
			'block_faqs'      => count( $found['faqs'] ),
			'block_answer'    => (bool) $found['direct_answer'],
			'block_takeaways' => (bool) $found['takeaways'],
			'thumbnail'       => $id ? has_post_thumbnail( $id ) : false,
			'excerpt'         => $id ? has_excerpt( $id ) : false,
			'modified'        => $id ? (int) get_post_modified_time( 'U', true, $id ) : time(),
			'noindex'         => $id ? Seo_Compat::is_noindex( $id ) : false,
			'author_bio'      => '' !== trim( (string) get_user_meta( (int) $post->post_author, 'description', true ) ),
			'custom_schema'   => $id ? (bool) get_post_meta( $id, '_aeo_schema', true ) : false,
		);
	}

	/**
	 * Run every check.
	 *
	 * @param array<string, mixed> $c Context.
	 * @return array<string, mixed>
	 */
	public function compute( array $c ) {
		$html       = strip_shortcodes( (string) $c['content'] );
		if ( false === stripos( $html, '<p' ) ) {
			$html = wpautop( $html );
		}
		$text       = Schema_Service::plain_text( $html );
		$words      = self::word_count( $text );
		$sentences  = array_values( array_filter( array_map( 'trim', preg_split( '/[.!?।。]+/u', $text ) ) ) );
		$avg_sent   = $words / max( 1, count( $sentences ) );
		$paragraphs = array();
		if ( preg_match_all( '/<p[^>]*>(.*?)<\/p>/is', $html, $m ) ) {
			foreach ( $m[1] as $p ) {
				$n = self::word_count( wp_strip_all_tags( $p ) );
				if ( $n > 0 ) {
					$paragraphs[] = $n;
				}
			}
		}
		$first_para = $paragraphs ? $paragraphs[0] : 0;
		$avg_para   = $paragraphs ? array_sum( $paragraphs ) / count( $paragraphs ) : $words;

		preg_match_all( '/<h[23][^>]*>(.*?)<\/h[23]>/is', $html, $hm );
		$headings  = array_map( 'wp_strip_all_tags', $hm[1] );
		$questions = array_filter( $headings, static function ( $h ) {
			$h = trim( $h );
			return '?' === substr( $h, -1 ) || (bool) preg_match( Prompt_Library::QUESTION, $h );
		} );

		$has_list  = (bool) preg_match( '/<(ul|ol|table)[\s>]/i', $html );
		$numbers   = preg_match_all( '/\b\d+([.,]\d+)?\s?(%|percent|km|m|kg|min|minutes|hours|years|\$|usd|bdt|€|£)?/iu', $text );
		$home_host = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		$external  = 0;
		if ( preg_match_all( '/<a\s[^>]*href=["\']([^"\']+)["\']/i', $html, $lm ) ) {
			foreach ( $lm[1] as $href ) {
				$host = (string) wp_parse_url( $href, PHP_URL_HOST );
				if ( $host && $host !== $home_host ) {
					$external++;
				}
			}
		}
		$faq_count  = (int) $c['meta_faqs'] + (int) $c['block_faqs'];
		$has_answer = '' !== $c['direct_answer'] || ! empty( $c['block_answer'] );
		$fresh      = (int) $c['modified'] > time() - YEAR_IN_SECONDS;
		$schema_on  = (bool) Settings::get( 'aeo_enable_schema', 1 );

		$checks = array();
		$add    = static function ( $id, $group, $label, $max, $earned, $tip ) use ( &$checks ) {
			$checks[] = array(
				'id'     => $id,
				'group'  => $group,
				'label'  => $label,
				'max'    => $max,
				'points' => max( 0, min( $max, $earned ) ),
				'passed' => $earned >= $max,
				'tip'    => $earned >= $max ? '' : $tip,
			);
		};

		// Answer first (20).
		$add( 'direct_answer', 'answer_first', __( 'Direct answer', 'answer-engine-optimization' ), 8, $has_answer ? 8 : 0, __( 'Add a 1–2 sentence direct answer near the top (meta box or Direct answer block).', 'answer-engine-optimization' ) );
		$add( 'first_paragraph', 'answer_first', __( 'Short opening paragraph', 'answer-engine-optimization' ), 6, ( $first_para >= 8 && $first_para <= 60 ) ? 6 : ( $first_para > 60 && $first_para <= 90 ? 3 : 0 ), __( 'Open with a paragraph under 60 words that answers the main question.', 'answer-engine-optimization' ) );
		$add( 'target_question', 'answer_first', __( 'Target question set', 'answer-engine-optimization' ), 6, '' !== $c['target_question'] ? 6 : 0, __( 'Set the question this page answers (target question).', 'answer-engine-optimization' ) );

		// Structure (15).
		$add( 'headings', 'structure', __( 'Subheadings', 'answer-engine-optimization' ), 5, count( $headings ) >= 2 ? 5 : ( count( $headings ) ? 3 : 0 ), __( 'Break the page up with at least two H2/H3 subheadings.', 'answer-engine-optimization' ) );
		$add( 'question_headings', 'structure', __( 'Question-style headings', 'answer-engine-optimization' ), 5, count( $questions ) >= 2 ? 5 : ( count( $questions ) ? 3 : 0 ), __( 'Phrase some headings as the questions people ask ("How do I…?").', 'answer-engine-optimization' ) );
		$add( 'lists', 'structure', __( 'Lists or tables', 'answer-engine-optimization' ), 5, $has_list ? 5 : 0, __( 'Use a list or table for steps, options or prices.', 'answer-engine-optimization' ) );

		// Schema (20).
		$schema_pts = 0;
		if ( $schema_on ) {
			$schema_pts = $c['custom_schema'] ? 12 : 8;
			$schema_pts += $c['thumbnail'] ? 4 : 0;
			$schema_pts += ( $c['excerpt'] || $has_answer ) ? 4 : 0;
		}
		$add( 'schema', 'schema', __( 'Complete structured data', 'answer-engine-optimization' ), 20, $schema_pts, ! $schema_on ? __( 'Structured data output is turned off in Settings.', 'answer-engine-optimization' ) : ( ! $c['thumbnail'] && ! ( $c['excerpt'] || $has_answer ) ? __( 'Add a featured image and an excerpt or direct answer so the schema is complete.', 'answer-engine-optimization' ) : ( ! $c['thumbnail'] ? __( 'Add a featured image so the schema includes an image.', 'answer-engine-optimization' ) : __( 'Add an excerpt or direct answer so the schema has a description.', 'answer-engine-optimization' ) ) ) );

		// FAQs (10).
		$add( 'faqs', 'faqs', __( 'FAQs', 'answer-engine-optimization' ), 10, $faq_count >= 3 ? 10 : ( $faq_count ? 6 : 0 ), __( 'Add at least three FAQs (FAQ block, meta box or "Suggest questions").', 'answer-engine-optimization' ) );

		// Readability (15).
		$add( 'sentences', 'readability', __( 'Short sentences', 'answer-engine-optimization' ), 6, $words < 30 ? 0 : ( $avg_sent <= 20 ? 6 : ( $avg_sent <= 25 ? 3 : 0 ) ), sprintf( /* translators: %d: words */ __( 'Average sentence is %d words. Aim for 20 or fewer.', 'answer-engine-optimization' ), (int) round( $avg_sent ) ) );
		$add( 'paragraphs', 'readability', __( 'Short paragraphs', 'answer-engine-optimization' ), 4, $avg_para <= 80 && $words >= 30 ? 4 : 0, __( 'Keep paragraphs under about 80 words.', 'answer-engine-optimization' ) );
		$add( 'length', 'readability', __( 'Enough depth', 'answer-engine-optimization' ), 5, $words >= 300 ? 5 : ( $words >= 150 ? 2 : 0 ), sprintf( /* translators: %d: words */ __( '%d words. Aim for 300+ so the page fully answers the question.', 'answer-engine-optimization' ), $words ) );

		// Evidence (10).
		$add( 'facts', 'evidence', __( 'Specific facts and numbers', 'answer-engine-optimization' ), 4, $numbers >= 3 ? 4 : ( $numbers ? 2 : 0 ), __( 'Include concrete numbers: prices, distances, times, sizes.', 'answer-engine-optimization' ) );
		$add( 'sources', 'evidence', __( 'Links to sources', 'answer-engine-optimization' ), 3, $external ? 3 : 0, __( 'Link to at least one trustworthy outside source.', 'answer-engine-optimization' ) );
		$add( 'fresh', 'evidence', __( 'Updated in the last 12 months', 'answer-engine-optimization' ), 3, $fresh ? 3 : 0, __( 'Review and update this page; it has not changed in over a year.', 'answer-engine-optimization' ) );

		// Signals (10).
		$add( 'image', 'signals', __( 'Featured image', 'answer-engine-optimization' ), 4, $c['thumbnail'] ? 4 : 0, __( 'Set a featured image.', 'answer-engine-optimization' ) );
		$add( 'indexable', 'signals', __( 'Indexable', 'answer-engine-optimization' ), 4, $c['noindex'] ? 0 : 4, __( 'This page is set to noindex, so AI search engines will skip it.', 'answer-engine-optimization' ) );
		$add( 'author', 'signals', __( 'Author bio', 'answer-engine-optimization' ), 2, ( 'post' !== $c['post_type'] || $c['author_bio'] ) ? 2 : 0, __( "Add a short bio to the author's profile (Users → Profile).", 'answer-engine-optimization' ) );

		$score   = (int) array_sum( array_column( $checks, 'points' ) );
		$factors = array();
		foreach ( $checks as $check ) {
			$factors[ $check['group'] ]['points'] = ( $factors[ $check['group'] ]['points'] ?? 0 ) + $check['points'];
			$factors[ $check['group'] ]['max']    = ( $factors[ $check['group'] ]['max'] ?? 0 ) + $check['max'];
		}
		foreach ( $factors as $group => $f ) {
			$factors[ $group ] = (int) round( 100 * $f['points'] / max( 1, $f['max'] ) );
		}

		$failed = array_filter( $checks, static function ( $ch ) {
			return ! $ch['passed'];
		} );
		usort( $failed, static function ( $a, $b ) {
			return ( $b['max'] - $b['points'] ) <=> ( $a['max'] - $a['points'] );
		} );

		return array(
			'score'           => $score,
			'level'           => $this->get_level( $score ),
			'factors'         => $factors,
			'checks'          => $checks,
			'recommendations' => array_values( array_map( static function ( $ch ) {
				return array(
					'id'   => $ch['id'],
					'tip'  => $ch['tip'],
					'gain' => $ch['max'] - $ch['points'],
				);
			}, $failed ) ),
			'words'           => $words,
		);
	}

	/**
	 * Unicode-aware word count (str_word_count() returns 0 for Bengali, Arabic, CJK, etc.).
	 *
	 * @param string $text Plain text.
	 * @return int
	 */
	public static function word_count( $text ) {
		return (int) preg_match_all( '/[\p{L}\p{N}][\p{L}\p{N}\p{M}\'’-]*/u', (string) $text );
	}

	/**
	 * Site-wide average score.
	 *
	 * @return array{average: int, total_posts: int}
	 */
	public function get_site_average() {
		global $wpdb;
		$types        = Settings::get_post_types();
		$placeholders = implode( ',', array_fill( 0, count( $types ), '%s' ) );
		// phpcs:disable WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT AVG(CAST(pm.meta_value AS UNSIGNED)) AS avg_score, COUNT(*) AS total FROM {$wpdb->postmeta} pm
				INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				WHERE pm.meta_key = '_aeo_score' AND p.post_status = 'publish' AND p.post_type IN ($placeholders)",
				$types
			),
			ARRAY_A
		);
		// phpcs:enable

		return array(
			'average'     => $row && $row['avg_score'] ? (int) round( (float) $row['avg_score'] ) : 0,
			'total_posts' => $row ? (int) $row['total'] : 0,
		);
	}

	/**
	 * Colour level.
	 *
	 * @param int $score Score.
	 * @return string
	 */
	private function get_level( $score ) {
		if ( $score >= 71 ) {
			return 'green';
		}
		if ( $score >= 41 ) {
			return 'orange';
		}
		return 'red';
	}
}
