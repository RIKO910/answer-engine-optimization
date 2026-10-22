<?php
/**
 * Helpers for parsing and normalizing AI model output.
 *
 * @package AEO
 */

namespace AEO\Services\AI;

defined( 'ABSPATH' ) || exit;

/**
 * Static helpers shared by all providers.
 */
class AI_Response {

	/**
	 * Pull a JSON value out of model text (handles ```json fences and leading prose).
	 *
	 * @param string $text Model output.
	 * @return mixed|null Decoded value or null.
	 */
	public static function extract_json( $text ) {
		if ( ! is_string( $text ) ) {
			return null;
		}
		$text = trim( $text );

		if ( preg_match( '/```(?:json)?\s*(.+?)\s*```/is', $text, $m ) ) {
			$text = $m[1];
		}

		$decoded = json_decode( $text, true );
		if ( null !== $decoded ) {
			return $decoded;
		}

		// Fall back to the outermost {...} or [...] block.
		$starts = array_filter( array( strpos( $text, '{' ), strpos( $text, '[' ) ), 'is_int' );
		if ( empty( $starts ) ) {
			return null;
		}
		$start = min( $starts );
		$end   = max( (int) strrpos( $text, '}' ), (int) strrpos( $text, ']' ) );
		if ( $end <= $start ) {
			return null;
		}
		return json_decode( substr( $text, $start, $end - $start + 1 ), true );
	}

	/**
	 * Normalize any FAQ-shaped data to a list of {question, answer} strings.
	 *
	 * @param mixed $data Raw data.
	 * @return array<int, array{question: string, answer: string}>
	 */
	public static function normalize_faqs( $data ) {
		if ( is_array( $data ) && isset( $data['faqs'] ) && is_array( $data['faqs'] ) ) {
			$data = $data['faqs'];
		}
		if ( ! is_array( $data ) ) {
			return array();
		}

		$out  = array();
		$seen = array();
		foreach ( $data as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$q = $item['question'] ?? ( $item['q'] ?? '' );
			$a = $item['answer'] ?? ( $item['a'] ?? '' );
			$q = trim( sanitize_text_field( (string) $q ) );
			$a = trim( sanitize_textarea_field( (string) $a ) );
			if ( '' === $q || isset( $seen[ strtolower( $q ) ] ) ) {
				continue;
			}
			$seen[ strtolower( $q ) ] = true;
			$out[]                    = array(
				'question' => $q,
				'answer'   => $a,
			);
		}
		return $out;
	}

	/**
	 * Normalize a content brief.
	 *
	 * @param mixed  $data  Raw data.
	 * @param string $topic Topic.
	 * @return array<string, mixed>
	 */
	public static function normalize_brief( $data, $topic ) {
		$data = is_array( $data ) ? $data : array();

		$questions = array();
		foreach ( (array) ( $data['target_questions'] ?? array() ) as $q ) {
			$q = trim( sanitize_text_field( is_array( $q ) ? (string) ( $q['question'] ?? '' ) : (string) $q ) );
			if ( '' !== $q ) {
				$questions[] = $q;
			}
		}

		$entities = array();
		foreach ( (array) ( $data['entities'] ?? array() ) as $e ) {
			$e = trim( sanitize_text_field( is_array( $e ) ? (string) ( $e['name'] ?? '' ) : (string) $e ) );
			if ( '' !== $e ) {
				$entities[] = $e;
			}
		}

		$schema_type = sanitize_text_field( (string) ( $data['schema_type'] ?? 'Article' ) );

		return array(
			'topic'             => $topic,
			'target_questions'  => array_slice( $questions, 0, 10 ),
			'entities'          => array_slice( $entities, 0, 15 ),
			'schema_type'       => '' !== $schema_type ? $schema_type : 'Article',
			'faq_suggestions'   => self::normalize_faqs( $data['faq_suggestions'] ?? array() ),
			// Never trust a model-invented score; computed from the site's own content.
			'opportunity_score' => \AEO\Services\Brief_Service::opportunity_score( $topic ),
		);
	}

	/**
	 * Shared prompts.
	 *
	 * @param string $task    faqs|rewrite|brief.
	 * @param string $content Content or topic.
	 * @param int    $count   Item count.
	 * @return string
	 */
	public static function prompt( $task, $content, $count = 5 ) {
		switch ( $task ) {
			case 'faqs':
				return sprintf(
					"Write %d FAQ question-and-answer pairs a reader of the content below is likely to ask. Answer each in 1-3 plain sentences using only facts from the content. Return only JSON: {\"faqs\":[{\"question\":\"...\",\"answer\":\"...\"}]}\n\nContent:\n%s",
					$count,
					wp_trim_words( wp_strip_all_tags( strip_shortcodes( $content ) ), 1200 )
				);
			case 'rewrite':
				return "Rewrite the content below so AI answer engines can quote it: start with a 1-2 sentence direct answer, use question-style H2 headings, short paragraphs and lists. Keep every fact; do not invent new ones. Return only HTML using <p>, <h2>, <h3>, <ul>, <ol>, <li>, <strong>.\n\nContent:\n" .
					wp_trim_words( wp_strip_all_tags( strip_shortcodes( $content ) ), 1500 );
			case 'brief':
				return sprintf(
					'Create an answer-engine-optimization content brief for the topic "%s". Return only JSON: {"target_questions":["..."],"entities":["..."],"schema_type":"Article|HowTo|FAQPage|Product|LocalBusiness|Hotel|Event","faq_suggestions":[{"question":"...","answer":"..."}]}',
					$content
				);
		}
		return $content;
	}
}
