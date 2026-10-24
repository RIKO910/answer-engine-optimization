<?php
/**
 * Elementor Key Takeaways widget.
 *
 * @package AEO
 */

namespace AEO\Integrations\Elementor;

use AEO\Content\Blocks;

defined( 'ABSPATH' ) || exit;

/**
 * Key takeaways widget (marked as speakable).
 */
class Takeaways_Widget extends \Elementor\Widget_Base {

	public function get_name() {
		return 'aeo-key-takeaways';
	}

	public function get_title() {
		return __( 'Key takeaways (AEO)', 'answer-engine-optimization' );
	}

	public function get_icon() {
		return 'eicon-bullet-list';
	}

	public function get_categories() {
		return array( 'aeo' );
	}

	public function get_style_depends() {
		return array( 'aeo-frontend-css' );
	}

	protected function register_controls() {
		$this->start_controls_section( 'content', array( 'label' => __( 'Takeaways', 'answer-engine-optimization' ) ) );
		$this->add_control(
			'title',
			array(
				'label'   => __( 'Title', 'answer-engine-optimization' ),
				'type'    => \Elementor\Controls_Manager::TEXT,
				'default' => __( 'Key takeaways', 'answer-engine-optimization' ),
			)
		);
		$repeater = new \Elementor\Repeater();
		$repeater->add_control(
			'text',
			array(
				'label'       => __( 'Point', 'answer-engine-optimization' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'label_block' => true,
			)
		);
		$this->add_control(
			'items',
			array(
				'label'       => __( 'Points', 'answer-engine-optimization' ),
				'type'        => \Elementor\Controls_Manager::REPEATER,
				'fields'      => $repeater->get_controls(),
				'title_field' => '{{{ text }}}',
			)
		);
		$this->end_controls_section();
	}

	protected function render() {
		$s = $this->get_settings_for_display();
		echo Blocks::render_takeaways( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in renderer.
			array(
				'title' => $s['title'] ?? '',
				'items' => array_map(
					static function ( $i ) {
						return (string) ( $i['text'] ?? '' );
					},
					(array) ( $s['items'] ?? array() )
				),
			)
		);
	}
}
