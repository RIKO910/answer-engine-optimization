<?php
/**
 * Pulls answer-engine content (FAQs, HowTo steps, takeaways, direct answers) out of
 * a post, whichever editor built it: blocks, Elementor or Bricks.
 *
 * @package AEO
 */

namespace AEO\Content;

defined( 'ABSPATH' ) || exit;

/**
 * Extractor.
 */
class Content_Extractor {

	/**
	 * Per-request cache.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private static $cache = array();

	/**
	 * Extract everything for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return array{faqs: array<int, array{question: string, answer: string}>, howtos: array<int, array<string, mixed>>, takeaways: bool, direct_answer: bool}
	 */
	public static function extract( $post_id ) {
		if ( isset( self::$cache[ $post_id ] ) ) {
			return self::$cache[ $post_id ];
		}

		$out = array(
			'faqs'          => array(),
			'howtos'        => array(),
			'takeaways'     => false,
			'direct_answer' => false,
			'third_party_faqs' => array(),
		);

		$post = get_post( $post_id );
		if ( ! $post ) {
			return $out;
		}

		if ( function_exists( 'parse_blocks' ) && has_blocks( $post->post_content ) ) {
			self::walk_blocks( parse_blocks( $post->post_content ), $out );
		}

		$elementor = get_post_meta( $post_id, '_elementor_data', true );
		if ( $elementor && 'builder' === get_post_meta( $post_id, '_elementor_edit_mode', true ) ) {
			$data = is_array( $elementor ) ? $elementor : json_decode( (string) $elementor, true );
			if ( is_array( $data ) ) {
				self::walk_elementor( $data, $out );
			}
		}

		$bricks = get_post_meta( $post_id, '_bricks_page_content_2', true );
		if ( is_array( $bricks ) ) {
			foreach ( $bricks as $el ) {
				self::from_widget( (string) ( $el['name'] ?? '' ), (array) ( $el['settings'] ?? array() ), $out, 'bricks' );
			}
		}

		self::$cache[ $post_id ] = $out;
		return $out;
	}

	/**
	 * Reset cache (after saving).
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public static function forget( $post_id ) {
		unset( self::$cache[ $post_id ] );
	}

	/**
	 * Walk block tree.
	 *
	 * @param array<int, array<string, mixed>> $blocks Blocks.
	 * @param array<string, mixed>             $out    Output.
	 * @return void
	 */
	private static function walk_blocks( array $blocks, array &$out ) {
		foreach ( $blocks as $block ) {
			$name  = (string) ( $block['blockName'] ?? '' );
			$attrs = (array) ( $block['attrs'] ?? array() );
			switch ( $name ) {
				case 'aeo/faq':
					foreach ( Blocks::faq_items( $attrs ) as $item ) {
						$out['faqs'][] = array(
							'question' => $item['question'],
							'answer'   => $item['answer'],
						);
					}
					break;
				case 'aeo/howto':
					$steps = Blocks::howto_steps( $attrs );
					if ( $steps ) {
						$out['howtos'][] = array(
							'title'       => (string) ( $attrs['title'] ?? '' ),
							'description' => wp_strip_all_tags( (string) ( $attrs['description'] ?? '' ) ),
							'minutes'     => (int) ( $attrs['minutes'] ?? 0 ),
							'supplies'    => (string) ( $attrs['supplies'] ?? '' ),
							'steps'       => $steps,
						);
					}
					break;
				case 'aeo/key-takeaways':
					$out['takeaways'] = $out['takeaways'] || ! empty( array_filter( (array) ( $attrs['items'] ?? array() ) ) );
					break;
				case 'aeo/direct-answer':
					$out['direct_answer'] = $out['direct_answer'] || '' !== trim( wp_strip_all_tags( (string) ( $attrs['answer'] ?? '' ) ) );
					break;
				case 'yoast/faq-block':
				case 'rank-math/faq-block':
					foreach ( self::third_party_faqs( $block ) as $item ) {
						$out['third_party_faqs'][] = $item;
					}
					break;
			}
			if ( ! empty( $block['innerBlocks'] ) ) {
				self::walk_blocks( $block['innerBlocks'], $out );
			}
		}
	}

	/**
	 * FAQs from Yoast or Rank Math FAQ blocks (used for import, not schema output,
	 * because those plugins print their own FAQ schema).
	 *
	 * @param array<string, mixed> $block Block.
	 * @return array<int, array{question: string, answer: string}>
	 */
	public static function third_party_faqs( array $block ) {
		$out   = array();
		$attrs = (array) ( $block['attrs'] ?? array() );
		$list  = 'yoast/faq-block' === $block['blockName'] ? (array) ( $attrs['questions'] ?? array() ) : (array) ( $attrs['listItems'] ?? array() );
		foreach ( $list as $q ) {
			$question = 'yoast/faq-block' === $block['blockName'] ? ( $q['jsonQuestion'] ?? wp_strip_all_tags( is_array( $q['question'] ?? '' ) ? '' : (string) ( $q['question'] ?? '' ) ) ) : ( $q['title'] ?? '' );
			$answer   = 'yoast/faq-block' === $block['blockName'] ? ( $q['jsonAnswer'] ?? '' ) : ( $q['content'] ?? '' );
			$question = trim( wp_strip_all_tags( (string) $question ) );
			$answer   = trim( wp_strip_all_tags( (string) $answer ) );
			if ( '' !== $question && '' !== $answer ) {
				$out[] = array(
					'question' => $question,
					'answer'   => $answer,
				);
			}
		}
		return $out;
	}

	/**
	 * Walk Elementor element tree.
	 *
	 * @param array<int, array<string, mixed>> $elements Elements.
	 * @param array<string, mixed>             $out      Output.
	 * @return void
	 */
	private static function walk_elementor( array $elements, array &$out ) {
		foreach ( $elements as $el ) {
			if ( 'widget' === ( $el['elType'] ?? '' ) ) {
				self::from_widget( (string) ( $el['widgetType'] ?? '' ), (array) ( $el['settings'] ?? array() ), $out, 'elementor' );
			}
			if ( ! empty( $el['elements'] ) && is_array( $el['elements'] ) ) {
				self::walk_elementor( $el['elements'], $out );
			}
		}
	}

	/**
	 * Map a page-builder widget's settings.
	 *
	 * @param string               $name     Widget name.
	 * @param array<string, mixed> $settings Settings.
	 * @param array<string, mixed> $out      Output.
	 * @param string               $builder  Builder.
	 * @return void
	 */
	private static function from_widget( $name, array $settings, array &$out, $builder ) {
		if ( 'aeo-faq' === $name ) {
			foreach ( (array) ( $settings['items'] ?? array() ) as $item ) {
				$q = trim( wp_strip_all_tags( (string) ( $item['question'] ?? '' ) ) );
				$a = trim( wp_strip_all_tags( (string) ( $item['answer'] ?? '' ) ) );
				if ( '' !== $q && '' !== $a ) {
					$out['faqs'][] = array(
						'question' => $q,
						'answer'   => $a,
					);
				}
			}
		} elseif ( 'aeo-key-takeaways' === $name ) {
			$out['takeaways'] = $out['takeaways'] || ! empty( $settings['items'] );
		}
	}
}
