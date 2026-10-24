<?php
/**
 * Elementor widgets and Bricks elements for FAQ and Key Takeaways.
 *
 * Both reuse the block renderers, and Content_Extractor reads their saved
 * settings so the matching schema is output.
 *
 * @package AEO
 */

namespace AEO\Integrations;

defined( 'ABSPATH' ) || exit;

/**
 * Page builder integrations.
 */
class Page_Builders {

	/**
	 * Register.
	 *
	 * @return void
	 */
	public static function register() {
		add_action( 'elementor/widgets/register', array( __CLASS__, 'elementor' ) );
		add_action( 'elementor/elements/categories_registered', array( __CLASS__, 'elementor_category' ) );
		add_action( 'init', array( __CLASS__, 'bricks' ), 11 );
	}

	/**
	 * Elementor category.
	 *
	 * @param object $manager Elements manager.
	 * @return void
	 */
	public static function elementor_category( $manager ) {
		if ( method_exists( $manager, 'add_category' ) ) {
			$manager->add_category(
				'aeo',
				array(
					'title' => __( 'Answer Engine Optimization', 'answer-engine-optimization' ),
					'icon'  => 'eicon-help-o',
				)
			);
		}
	}

	/**
	 * Elementor widgets.
	 *
	 * @param object $widgets_manager Widgets manager.
	 * @return void
	 */
	public static function elementor( $widgets_manager ) {
		if ( ! class_exists( '\Elementor\Widget_Base' ) ) {
			return;
		}
		require_once __DIR__ . '/elementor/class-faq-widget.php';
		require_once __DIR__ . '/elementor/class-takeaways-widget.php';
		$widgets_manager->register( new \AEO\Integrations\Elementor\Faq_Widget() );
		$widgets_manager->register( new \AEO\Integrations\Elementor\Takeaways_Widget() );
	}

	/**
	 * Bricks elements.
	 *
	 * @return void
	 */
	public static function bricks() {
		if ( ! class_exists( '\Bricks\Elements' ) ) {
			return;
		}
		\Bricks\Elements::register_element( __DIR__ . '/bricks/element-aeo-faq.php', 'aeo-faq', 'AEO_Bricks_Faq' );
		\Bricks\Elements::register_element( __DIR__ . '/bricks/element-aeo-key-takeaways.php', 'aeo-key-takeaways', 'AEO_Bricks_Key_Takeaways' );
	}
}
