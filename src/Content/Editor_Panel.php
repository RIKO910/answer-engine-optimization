<?php
/**
 * Live AEO score panel in the block editor.
 *
 * @package AEO
 */

namespace AEO\Content;

use AEO\Core\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Editor panel.
 */
class Editor_Panel {

	/**
	 * Register.
	 *
	 * @return void
	 */
	public static function register() {
		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'enqueue' ) );
	}

	/**
	 * Enqueue on supported post types only.
	 *
	 * @return void
	 */
	public static function enqueue() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'post' !== $screen->base || ! in_array( $screen->post_type, Settings::get_post_types(), true ) ) {
			return;
		}
		$deps = array( 'wp-plugins', 'wp-element', 'wp-data', 'wp-api-fetch', 'wp-i18n', 'wp-editor' );
		if ( wp_script_is( 'wp-edit-post', 'registered' ) ) {
			$deps[] = 'wp-edit-post';
		}
		wp_enqueue_script( 'aeo-editor-panel', AEO_PLUGIN_URL . 'assets/blocks/sidebar.js', $deps, AEO_VERSION, true );
		wp_set_script_translations( 'aeo-editor-panel', 'answer-engine-optimization', AEO_PLUGIN_DIR . 'languages' );
	}
}
