<?php
/**
 * Elementor FAQ widget.
 *
 * @package AEO
 */

namespace AEO\Integrations\Elementor;

use AEO\Content\Blocks;

defined( 'ABSPATH' ) || exit;

/**
 * FAQ widget (data feeds FAQPage schema via Content_Extractor).
 */
class Faq_Widget extends \Elementor\Widget_Base {

	public function get_name() {
		return 'aeo-faq';
	}

	public function get_title() {
		return __( 'FAQ (AEO)', 'answer-engine-optimization' );
	}

	public function get_icon() {
		return 'eicon-help-o';
	}

	public function get_categories() {
		return array( 'aeo' );
	}

	public function get_keywords() {
		return array( 'faq', 'question', 'answer', 'aeo' );
	}

	public function get_style_depends() {
		return array( 'aeo-frontend-css' );
	}

	protected function register_controls() {
		$this->start_controls_section( 'content', array( 'label' => __( 'Questions', 'answer-engine-optimization' ) ) );
		$this->add_control(
			'heading',
			array(
				'label'   => __( 'Heading', 'answer-engine-optimization' ),
				'type'    => \Elementor\Controls_Manager::TEXT,
				'default' => '',
			)
		);
		$repeater = new \Elementor\Repeater();
		$repeater->add_control(
			'question',
			array(
				'label'       => __( 'Question', 'answer-engine-optimization' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'label_block' => true,
			)
		);
		$repeater->add_control(
			'answer',
			array(
				'label' => __( 'Answer', 'answer-engine-optimization' ),
				'type'  => \Elementor\Controls_Manager::TEXTAREA,
			)
		);
		$this->add_control(
			'items',
			array(
				'label'       => __( 'Items', 'answer-engine-optimization' ),
				'type'        => \Elementor\Controls_Manager::REPEATER,
				'fields'      => $repeater->get_controls(),
				'title_field' => '{{{ question }}}',
			)
		);
		$this->end_controls_section();
	}

	protected function render() {
		$s     = $this->get_settings_for_display();
		$items = array();
		foreach ( (array) ( $s['items'] ?? array() ) as $item ) {
			$items[] = array(
				'q' => $item['question'] ?? '',
				'a' => $item['answer'] ?? '',
			);
		}
		echo Blocks::render_faq( array( 'items' => $items, 'heading' => $s['heading'] ?? '' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in renderer.
	}
}
