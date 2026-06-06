<?php
/**
 * Admin asset enqueuing.
 *
 * @package AEO
 */

namespace AEO\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Enqueues React admin assets.
 */
class Assets {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * Enqueue admin scripts on AEO pages.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue( $hook ) {
		if ( strpos( $hook, 'aeo-' ) === false && 'toplevel_page_aeo-dashboard' !== $hook ) {
			return;
		}

		$manifest_path = AEO_PLUGIN_DIR . 'assets/build/.vite/manifest.json';
		$js_file       = 'admin-app/src/main.tsx';
		$css_files     = array();

		if ( file_exists( $manifest_path ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$manifest = json_decode( file_get_contents( $manifest_path ), true );
			if ( isset( $manifest[ $js_file ] ) ) {
				$entry   = $manifest[ $js_file ];
				$js_file = 'assets/build/' . $entry['file'];
				if ( ! empty( $entry['css'] ) ) {
					foreach ( $entry['css'] as $css ) {
						$css_files[] = 'assets/build/' . $css;
					}
				}
			}
		} else {
			$js_file = 'assets/build/main.js';
		}

		foreach ( $css_files as $index => $css_file ) {
			wp_enqueue_style(
				'aeo-admin-app-css-' . $index,
				AEO_PLUGIN_URL . $css_file,
				array(),
				AEO_VERSION
			);
		}

		wp_enqueue_script(
			'aeo-admin-app',
			AEO_PLUGIN_URL . $js_file,
			array(),
			AEO_VERSION,
			true
		);

		wp_localize_script(
			'aeo-admin-app',
			'aeoGeniusData',
			array(
				'apiUrl'      => rest_url( 'aeo-genius/v1' ),
				'nonce'       => wp_create_nonce( 'wp_rest' ),
				'adminUrl'    => admin_url(),
				'siteUrl'     => home_url(),
				'siteName'    => get_bloginfo( 'name' ),
				'pluginUrl'   => AEO_PLUGIN_URL,
				'version'     => AEO_VERSION,
				'currentPage' => isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : 'aeo-dashboard', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			)
		);
	}
}
