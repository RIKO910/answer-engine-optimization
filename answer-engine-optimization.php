<?php
/**
 * Plugin Name:       Answer Engine Optimization
 * Plugin URI:        https://tarikul.top/plugins/answer-engine-optimization/
 * Description:       AI-Powered Answer Engine Optimization — optimize for ChatGPT, Perplexity, Google SGE, and all AI answer engines.
 * Version:           1.0.1
 * Requires at least: 6.5
 * Requires PHP:      7.2
 * Author:            Tarikul Islam Riko
 * Author URI:        https://tarikul.top/
 * License:           GPL v2 or later
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       answer-engine-optimization
 * Domain Path:       /languages
 */

defined( 'ABSPATH' ) || exit;

$aeo_autoload = __DIR__ . '/vendor/autoload.php';
if ( file_exists( $aeo_autoload ) ) {
	require_once $aeo_autoload;
} else {
	// Fallback PSR-4 autoloader when Composer is not installed.
	spl_autoload_register(
		function ( $class ) {
			$prefix   = 'AEO\\';
			$base_dir = __DIR__ . '/src/';
			$len      = strlen( $prefix );

			if ( strncmp( $prefix, $class, $len ) !== 0 ) {
				return;
			}

			$relative = substr( $class, $len );
			$file     = $base_dir . str_replace( '\\', '/', $relative ) . '.php';

			if ( file_exists( $file ) ) {
				require $file;
			}
		}
	);
}

// Legacy bootstrap for backward compatibility.
require_once __DIR__ . '/includes/class-answer-engine-optimization.php';

/**
 * Initialize the plugin.
 *
 * @since 1.0.0
 * @return \AEO\Plugin
 */
function aeo_init() {
	return \AEO\Plugin::get_instance( __FILE__ );
}
add_action( 'plugins_loaded', 'aeo_init', 5 );
