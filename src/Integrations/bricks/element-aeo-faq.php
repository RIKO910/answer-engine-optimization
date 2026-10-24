<?php
/**
 * Bricks FAQ element.
 *
 * @package AEO
 */

defined( 'ABSPATH' ) || exit;

/**
 * FAQ element (data feeds FAQPage schema via Content_Extractor).
 */
class AEO_Bricks_Faq extends \Bricks\Element {
	public $category = 'general';
	public $name     = 'aeo-faq';
	public $icon     = 'ti-help-alt';

	public function get_label() {
		return esc_html__( 'FAQ (AEO)', 'answer-engine-optimization' );
	}

	public function set_controls() {
		$this->controls['heading'] = array(
			'tab'   => 'content',
			'label' => esc_html__( 'Heading', 'answer-engine-optimization' ),
			'type'  => 'text',
		);
		$this->controls['items']   = array(
			'tab'           => 'content',
			'label'         => esc_html__( 'Questions', 'answer-engine-optimization' ),
			'type'          => 'repeater',
			'titleProperty' => 'question',
			'fields'        => array(
				'question' => array(
					'label' => esc_html__( 'Question', 'answer-engine-optimization' ),
					'type'  => 'text',
				),
				'answer'   => array(
					'label' => esc_html__( 'Answer', 'answer-engine-optimization' ),
					'type'  => 'textarea',
				),
			),
		);
	}

	public function render() {
		$items = array();
		foreach ( (array) ( $this->settings['items'] ?? array() ) as $item ) {
			$items[] = array(
				'q' => $item['question'] ?? '',
				'a' => $item['answer'] ?? '',
			);
		}
		echo '<div ' . $this->render_attributes( '_root' ) . '>' . \AEO\Content\Blocks::render_faq( array( 'items' => $items, 'heading' => $this->settings['heading'] ?? '' ) ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
