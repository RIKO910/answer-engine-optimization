<?php
/**
 * Gutenberg blocks: FAQ, HowTo, Key Takeaways, Direct Answer.
 *
 * Blocks render on the server, so the visible HTML and the JSON-LD built from the
 * same attributes always match. Schema nodes are collected by Content_Extractor.
 *
 * @package AEO
 */

namespace AEO\Content;

defined( 'ABSPATH' ) || exit;

/**
 * Block registration and rendering.
 */
class Blocks {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function register() {
		add_action( 'init', array( __CLASS__, 'register_blocks' ) );
		add_filter( 'block_categories_all', array( __CLASS__, 'category' ), 10, 1 );
	}

	/**
	 * Block category.
	 *
	 * @param array<int, array<string, string>> $categories Categories.
	 * @return array<int, array<string, string>>
	 */
	public static function category( $categories ) {
		$categories[] = array(
			'slug'  => 'aeo',
			'title' => __( 'Answer Engine Optimization', 'answer-engine-optimization' ),
		);
		return $categories;
	}

	/**
	 * Register scripts and blocks.
	 *
	 * @return void
	 */
	public static function register_blocks() {
		wp_register_script(
			'aeo-blocks',
			AEO_PLUGIN_URL . 'assets/blocks/blocks.js',
			array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n' ),
			AEO_VERSION,
			true
		);
		wp_set_script_translations( 'aeo-blocks', 'answer-engine-optimization', AEO_PLUGIN_DIR . 'languages' );
		wp_register_style( 'aeo-blocks-editor', AEO_PLUGIN_URL . 'assets/blocks/editor.css', array(), AEO_VERSION );
		wp_register_style( 'aeo-frontend-css', AEO_PLUGIN_URL . 'assets/css/aeo-frontend.css', array(), AEO_VERSION );

		$common = array(
			'editor_script' => 'aeo-blocks',
			'editor_style'  => 'aeo-blocks-editor',
			'style'         => 'aeo-frontend-css',
		);

		register_block_type(
			'aeo/faq',
			$common + array(
				'attributes'      => array(
					'items'   => array(
						'type'    => 'array',
						'default' => array(),
					),
					'heading' => array(
						'type'    => 'string',
						'default' => '',
					),
				),
				'render_callback' => array( __CLASS__, 'render_faq' ),
			)
		);

		register_block_type(
			'aeo/howto',
			$common + array(
				'attributes'      => array(
					'title'       => array( 'type' => 'string', 'default' => '' ),
					'description' => array( 'type' => 'string', 'default' => '' ),
					'minutes'     => array( 'type' => 'number', 'default' => 0 ),
					'supplies'    => array( 'type' => 'string', 'default' => '' ),
					'steps'       => array( 'type' => 'array', 'default' => array() ),
				),
				'render_callback' => array( __CLASS__, 'render_howto' ),
			)
		);

		register_block_type(
			'aeo/key-takeaways',
			$common + array(
				'attributes'      => array(
					'title' => array( 'type' => 'string', 'default' => '' ),
					'items' => array( 'type' => 'array', 'default' => array() ),
				),
				'render_callback' => array( __CLASS__, 'render_takeaways' ),
			)
		);

		register_block_type(
			'aeo/direct-answer',
			$common + array(
				'attributes'      => array(
					'question' => array( 'type' => 'string', 'default' => '' ),
					'answer'   => array( 'type' => 'string', 'default' => '' ),
				),
				'render_callback' => array( __CLASS__, 'render_direct_answer' ),
			)
		);
	}

	/**
	 * Allowed inline HTML in answers/steps.
	 *
	 * @param string $html HTML.
	 * @return string
	 */
	public static function inline_html( $html ) {
		return wp_kses(
			(string) $html,
			array(
				'a'      => array( 'href' => true, 'rel' => true, 'target' => true ),
				'strong' => array(),
				'b'      => array(),
				'em'     => array(),
				'i'      => array(),
				'br'     => array(),
				'code'   => array(),
			)
		);
	}

	/**
	 * FAQ block.
	 *
	 * @param array<string, mixed> $attrs Attributes.
	 * @return string
	 */
	public static function render_faq( $attrs ) {
		$items = self::faq_items( $attrs );
		if ( ! $items ) {
			return '';
		}
		$html = '<section class="aeo-faq-section aeo-faq-block">';
		if ( ! empty( $attrs['heading'] ) ) {
			$html .= '<h2>' . esc_html( wp_strip_all_tags( $attrs['heading'] ) ) . '</h2>';
		}
		foreach ( $items as $i => $item ) {
			$html .= '<details class="aeo-faq-item"' . ( 0 === $i ? ' open' : '' ) . '><summary class="aeo-faq-question">' . esc_html( $item['question'] ) . '</summary>';
			$html .= '<div class="aeo-faq-answer">' . wpautop( self::inline_html( $item['answer_html'] ) ) . '</div></details>';
		}
		return $html . '</section>';
	}

	/**
	 * Normalized FAQ items from block attributes.
	 *
	 * @param array<string, mixed> $attrs Attributes.
	 * @return array<int, array{question: string, answer: string, answer_html: string}>
	 */
	public static function faq_items( $attrs ) {
		$out = array();
		foreach ( (array) ( $attrs['items'] ?? array() ) as $item ) {
			$q = trim( wp_strip_all_tags( (string) ( $item['q'] ?? $item['question'] ?? '' ) ) );
			$a = (string) ( $item['a'] ?? $item['answer'] ?? '' );
			if ( '' === $q || '' === trim( wp_strip_all_tags( $a ) ) ) {
				continue;
			}
			$out[] = array(
				'question'    => $q,
				'answer'      => trim( wp_strip_all_tags( $a ) ),
				'answer_html' => $a,
			);
		}
		return $out;
	}

	/**
	 * HowTo block.
	 *
	 * @param array<string, mixed> $attrs Attributes.
	 * @return string
	 */
	public static function render_howto( $attrs ) {
		$steps = self::howto_steps( $attrs );
		if ( ! $steps ) {
			return '';
		}
		$html = '<section class="aeo-howto">';
		if ( ! empty( $attrs['title'] ) ) {
			$html .= '<h2>' . esc_html( wp_strip_all_tags( $attrs['title'] ) ) . '</h2>';
		}
		if ( ! empty( $attrs['description'] ) ) {
			$html .= wpautop( self::inline_html( $attrs['description'] ) );
		}
		$meta = array();
		if ( ! empty( $attrs['minutes'] ) ) {
			/* translators: %d: minutes */
			$meta[] = sprintf( _n( 'Time: %d minute', 'Time: %d minutes', (int) $attrs['minutes'], 'answer-engine-optimization' ), (int) $attrs['minutes'] );
		}
		if ( ! empty( $attrs['supplies'] ) ) {
			/* translators: %s: supplies */
			$meta[] = sprintf( __( 'You need: %s', 'answer-engine-optimization' ), esc_html( wp_strip_all_tags( $attrs['supplies'] ) ) );
		}
		if ( $meta ) {
			$html .= '<p class="aeo-howto-meta">' . implode( ' · ', array_map( 'wp_kses_post', $meta ) ) . '</p>';
		}
		$html .= '<ol class="aeo-howto-steps">';
		foreach ( $steps as $step ) {
			$html .= '<li>' . ( $step['name'] ? '<strong>' . esc_html( $step['name'] ) . '</strong> ' : '' ) . self::inline_html( $step['text_html'] ) . '</li>';
		}
		return $html . '</ol></section>';
	}

	/**
	 * Normalized HowTo steps.
	 *
	 * @param array<string, mixed> $attrs Attributes.
	 * @return array<int, array{name: string, text: string, text_html: string}>
	 */
	public static function howto_steps( $attrs ) {
		$out = array();
		foreach ( (array) ( $attrs['steps'] ?? array() ) as $step ) {
			$text = (string) ( $step['text'] ?? '' );
			if ( '' === trim( wp_strip_all_tags( $text ) ) ) {
				continue;
			}
			$out[] = array(
				'name'      => trim( wp_strip_all_tags( (string) ( $step['name'] ?? '' ) ) ),
				'text'      => trim( wp_strip_all_tags( $text ) ),
				'text_html' => $text,
			);
		}
		return $out;
	}

	/**
	 * Key Takeaways block.
	 *
	 * @param array<string, mixed> $attrs Attributes.
	 * @return string
	 */
	public static function render_takeaways( $attrs ) {
		$items = array_values( array_filter( array_map( static function ( $i ) {
			return trim( (string) $i );
		}, (array) ( $attrs['items'] ?? array() ) ) ) );
		if ( ! $items ) {
			return '';
		}
		$title = ! empty( $attrs['title'] ) ? $attrs['title'] : __( 'Key takeaways', 'answer-engine-optimization' );
		$html  = '<aside class="aeo-key-takeaways" aria-label="' . esc_attr( wp_strip_all_tags( $title ) ) . '"><p class="aeo-key-takeaways-title"><strong>' . esc_html( wp_strip_all_tags( $title ) ) . '</strong></p><ul>';
		foreach ( $items as $item ) {
			$html .= '<li>' . self::inline_html( $item ) . '</li>';
		}
		return $html . '</ul></aside>';
	}

	/**
	 * Direct Answer block.
	 *
	 * @param array<string, mixed> $attrs Attributes.
	 * @return string
	 */
	public static function render_direct_answer( $attrs ) {
		$answer = trim( (string) ( $attrs['answer'] ?? '' ) );
		if ( '' === trim( wp_strip_all_tags( $answer ) ) ) {
			return '';
		}
		$html = '<div class="aeo-direct-answer">';
		if ( ! empty( $attrs['question'] ) ) {
			$html .= '<p class="aeo-direct-answer-question"><strong>' . esc_html( wp_strip_all_tags( $attrs['question'] ) ) . '</strong></p>';
		}
		return $html . '<p>' . self::inline_html( $answer ) . '</p></div>';
	}
}
