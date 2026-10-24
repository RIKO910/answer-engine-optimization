<?php
/**
 * Bricks Key Takeaways element.
 *
 * @package AEO
 */

defined( 'ABSPATH' ) || exit;

/**
 * Key takeaways element (marked as speakable).
 */
class AEO_Bricks_Key_Takeaways extends \Bricks\Element {
	public $category = 'general';
	public $name     = 'aeo-key-takeaways';
	public $icon     = 'ti-list';

	public function get_label() {
		return esc_html__( 'Key takeaways (AEO)', 'answer-engine-optimization' );
	}

	public function set_controls() {
		$this->controls['title'] = array(
			'tab'     => 'content',
			'label'   => esc_html__( 'Title', 'answer-engine-optimization' ),
			'type'    => 'text',
			'default' => esc_html__( 'Key takeaways', 'answer-engine-optimization' ),
		);
		$this->controls['items'] = array(
			'tab'           => 'content',
			'label'         => esc_html__( 'Points', 'answer-engine-optimization' ),
			'type'          => 'repeater',
			'titleProperty' => 'text',
			'fields'        => array(
				'text' => array(
					'label' => esc_html__( 'Point', 'answer-engine-optimization' ),
					'type'  => 'text',
				),
			),
		);
	}

	public function render() {
		$items = array_map(
			static function ( $i ) {
				return (string) ( $i['text'] ?? '' );
			},
			(array) ( $this->settings['items'] ?? array() )
		);
		echo '<div ' . $this->render_attributes( '_root' ) . '>' . \AEO\Content\Blocks::render_takeaways( array( 'title' => $this->settings['title'] ?? '', 'items' => $items ) ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
