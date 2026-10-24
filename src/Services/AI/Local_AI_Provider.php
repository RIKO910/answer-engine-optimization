<?php
/**
 * Offline fallback provider (no API key required).
 *
 * Builds questions from the post's own headings and sentences instead of
 * templating the most frequent word ("What is the?").
 *
 * @package AEO
 */

namespace AEO\Services\AI;

defined( 'ABSPATH' ) || exit;

/**
 * Local heuristics.
 */
class Local_AI_Provider implements AI_Provider_Interface {

	const QUESTION_WORDS = '(what|how|why|when|where|who|which|can|could|should|does|do|is|are|will|would)';

	/**
	 * {@inheritdoc}
	 */
	public function generate_faqs( $content, $count = 5 ) {
		$content  = (string) strip_shortcodes( (string) $content );
		$sections = $this->split_sections( $content );
		$faqs     = array();

		// 1. Headings become questions; the text under them becomes the answer.
		foreach ( $sections as $section ) {
			$question = $this->heading_to_question( $section['heading'] );
			$answer   = $this->first_sentences( $section['body'], 2 );
			if ( $question && $answer ) {
				$faqs[] = array(
					'question' => $question,
					'answer'   => $answer,
				);
			}
		}

		// 2. Questions the author already wrote in the text.
		$text = $this->plain( $content );
		if ( preg_match_all( '/([A-Z][^.?!]{10,140}\?)\s+([^?]{20,400}?[.!])/u', $text, $m, PREG_SET_ORDER ) ) {
			foreach ( $m as $match ) {
				$faqs[] = array(
					'question' => trim( $match[1] ),
					'answer'   => trim( $match[2] ),
				);
			}
		}

		$faqs = AI_Response::normalize_faqs( $faqs );
		return array_slice( $faqs, 0, max( 1, (int) $count ) );
	}

	/**
	 * {@inheritdoc}
	 */
	public function rewrite_content( $content ) {
		$intro = $this->first_sentences( $this->plain( $content ), 2 );
		if ( '' === $intro ) {
			return $content;
		}
		return '<div class="aeo-optimized-intro"><p><strong>' . esc_html__( 'Quick answer:', 'answer-engine-optimization' ) . '</strong> ' .
			esc_html( $intro ) . '</p></div>' . $content;
	}

	/**
	 * {@inheritdoc}
	 */
	public function generate_brief( $topic ) {
		$topic = trim( (string) $topic );
		return array(
			'topic'             => $topic,
			'target_questions'  => array(
				/* translators: %s: topic */
				sprintf( __( 'What is %s?', 'answer-engine-optimization' ), $topic ),
				/* translators: %s: topic */
				sprintf( __( 'How does %s work?', 'answer-engine-optimization' ), $topic ),
				/* translators: %s: topic */
				sprintf( __( 'How much does %s cost?', 'answer-engine-optimization' ), $topic ),
				/* translators: %s: topic */
				sprintf( __( 'What are the alternatives to %s?', 'answer-engine-optimization' ), $topic ),
			),
			'entities'          => array( $topic ),
			'schema_type'       => 'Article',
			'faq_suggestions'   => array(),
			'opportunity_score' => \AEO\Services\Brief_Service::opportunity_score( $topic ),
			'source'            => 'template',
		);
	}

	/**
	 * Title + meta description from the content's own text.
	 *
	 * @param string $title   Title.
	 * @param string $content Content.
	 * @return array{title: string, description: string}
	 */
	public function generate_meta( $title, $content ) {
		$site = get_bloginfo( 'name' );
		$t    = trim( wp_strip_all_tags( $title ) );
		if ( $site && mb_strlen( $t . ' | ' . $site ) <= 60 ) {
			$t .= ' | ' . $site;
		}
		return array(
			'title'       => AI_Response::clean_line( $t, 70 ),
			'description' => AI_Response::clean_line( $this->first_sentences( $this->plain( $content ), 2 ), 155 ),
		);
	}

	/**
	 * Alt text without vision: humanized file name, with page context.
	 *
	 * @param string $file    Path.
	 * @param string $context Context.
	 * @return string
	 */
	public function describe_image( $file, $context = '' ) {
		$name = pathinfo( (string) $file, PATHINFO_FILENAME );
		$name = preg_replace( '/[-_]+/', ' ', $name );
		$name = trim( preg_replace( '/\b(img|image|photo|dsc|pxl|screenshot|scaled|\d{3,}x\d{3,}|\d+)\b/i', '', $name ) );
		if ( mb_strlen( $name ) < 3 ) {
			return $context ? AI_Response::clean_line( $context, 125 ) : '';
		}
		return AI_Response::clean_line( ucfirst( $name ), 125 );
	}

	/**
	 * {@inheritdoc}
	 */
	public function is_configured() {
		return true;
	}

	/**
	 * Split HTML into heading + body sections.
	 *
	 * @param string $html HTML.
	 * @return array<int, array{heading: string, body: string}>
	 */
	private function split_sections( $html ) {
		$parts    = preg_split( '/<h[2-4][^>]*>(.*?)<\/h[2-4]>/is', $html, -1, PREG_SPLIT_DELIM_CAPTURE );
		$sections = array();
		for ( $i = 1; $i < count( $parts ); $i += 2 ) {
			$sections[] = array(
				'heading' => $this->plain( $parts[ $i ] ),
				'body'    => $this->plain( $parts[ $i + 1 ] ?? '' ),
			);
		}
		return $sections;
	}

	/**
	 * Turn a heading into a question.
	 *
	 * @param string $heading Heading text.
	 * @return string
	 */
	private function heading_to_question( $heading ) {
		$heading = trim( rtrim( $heading, ':.' ) );
		if ( mb_strlen( $heading ) < 4 || mb_strlen( $heading ) > 120 ) {
			return '';
		}
		if ( '?' === substr( $heading, -1 ) ) {
			return $heading;
		}
		if ( preg_match( '/^' . self::QUESTION_WORDS . '\b/i', $heading ) ) {
			return $heading . '?';
		}
		if ( preg_match( '/^how to\b/i', $heading ) ) {
			return 'How do I ' . lcfirst( substr( $heading, 7 ) ) . '?';
		}
		/* translators: %s: section heading */
		return sprintf( __( 'What should I know about %s?', 'answer-engine-optimization' ), lcfirst( $heading ) );
	}

	/**
	 * First N sentences of a text.
	 *
	 * @param string $text Text.
	 * @param int    $n    Sentences.
	 * @return string
	 */
	private function first_sentences( $text, $n ) {
		$text = trim( $text );
		if ( '' === $text ) {
			return '';
		}
		$sentences = preg_split( '/(?<=[.!?])\s+/u', $text, $n + 1 );
		$out       = implode( ' ', array_slice( $sentences, 0, $n ) );
		return wp_html_excerpt( $out, 320, '…' );
	}

	/**
	 * HTML to plain text with normalized whitespace.
	 *
	 * @param string $html HTML.
	 * @return string
	 */
	private function plain( $html ) {
		$html = preg_replace( '/<\/(p|li|h[1-6]|div|br)>/i', '$0 ', (string) $html );
		return trim( preg_replace( '/\s+/u', ' ', html_entity_decode( wp_strip_all_tags( $html ), ENT_QUOTES, 'UTF-8' ) ) );
	}
}
