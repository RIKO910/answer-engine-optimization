<?php
/**
 * Local fallback AI provider (no API key required).
 *
 * @package AEO
 */

namespace AEO\Services\AI;

defined( 'ABSPATH' ) || exit;

/**
 * Rule-based AI fallback when no API key is configured.
 */
class Local_AI_Provider implements AI_Provider_Interface {

	/**
	 * {@inheritDoc}
	 */
	public function generate_faqs( $content, $count = 5 ) {
		$text     = wp_strip_all_tags( $content );
		$keywords = $this->extract_keywords( $text );
		$faqs     = array();

		foreach ( array_slice( $keywords, 0, $count ) as $keyword ) {
			$faqs[] = array(
				/* translators: %s: extracted keyword */
				'question' => sprintf( __( 'What is %s?', 'answer-engine-optimization' ), $keyword ),
				'answer'   => $this->find_context( $text, $keyword ),
			);
		}

		$faqs[] = array(
			'question' => __( 'How does this work?', 'answer-engine-optimization' ),
			'answer'   => wp_trim_words( $text, 50 ),
		);

		return array_slice( $faqs, 0, $count );
	}

	/**
	 * {@inheritDoc}
	 */
	public function rewrite_content( $content ) {
		$text = wp_strip_all_tags( $content );
		$intro = wp_trim_words( $text, 30 );

		return '<div class="aeo-optimized-intro">' .
			'<p><strong>' . esc_html__( 'Quick Answer:', 'answer-engine-optimization' ) . '</strong> ' .
			esc_html( $intro ) . '</p>' .
			'</div>' . $content;
	}

	/**
	 * {@inheritDoc}
	 */
	public function generate_brief( $topic ) {
		return array(
			'topic'           => $topic,
			'target_questions' => array(
				/* translators: %s: brief topic */
				sprintf( __( 'What is %s?', 'answer-engine-optimization' ), $topic ),
				/* translators: %s: brief topic */
				sprintf( __( 'How to use %s?', 'answer-engine-optimization' ), $topic ),
				/* translators: %s: brief topic */
				sprintf( __( 'Why is %s important?', 'answer-engine-optimization' ), $topic ),
			),
			'entities'        => array( $topic ),
			'schema_type'     => 'Article',
			'opportunity_score' => rand( 60, 95 ),
			'faq_suggestions' => $this->generate_faqs( $topic, 3 ),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function is_configured() {
		return true;
	}

	/**
	 * Extract keywords from text.
	 *
	 * @param string $text Content text.
	 * @return array<int, string>
	 */
	private function extract_keywords( $text ) {
		$stop_words = array( 'the', 'and', 'a', 'to', 'of', 'in', 'is', 'it', 'that', 'for', 'you', 'on', 'with', 'as', 'at', 'be', 'this', 'by', 'from', 'are', 'was', 'were', 'can', 'will', 'has', 'have' );
		$words      = str_word_count( strtolower( $text ), 1 );
		$words      = array_diff( $words, $stop_words );
		$counts     = array_count_values( $words );
		arsort( $counts );
		return array_slice( array_keys( $counts ), 0, 10 );
	}

	/**
	 * Find contextual sentence for keyword.
	 *
	 * @param string $text    Full text.
	 * @param string $keyword Keyword.
	 * @return string
	 */
	private function find_context( $text, $keyword ) {
		$sentences = preg_split( '/[.!?]+/', $text );
		foreach ( $sentences as $sentence ) {
			if ( stripos( $sentence, $keyword ) !== false ) {
				return trim( $sentence ) . '.';
			}
		}
		return wp_trim_words( $text, 30 );
	}
}
