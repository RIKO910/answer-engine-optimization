<?php
/**
 * Plugin Name:       Answer Engine Optimization
 * Plugin URI:        https://tarikul.blog/uncategorized/answer-engine-optimization/
 * Description:       Optimize your content for answer engines and featured snippets.
 * Version:           1.0.0
 * Requires at least: 6.5
 * Requires PHP:      7.2
 * Author:            Tarikul Islam Riko
 * Author URI:        https://tarikul.blog/
 * License:           GPL v2 or later
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       answer-engine-optimization
 * Domain Path:       /languages
 */

defined( 'ABSPATH' ) || exit;

/**
 * Initializing Plugin.
 *
 * @since 1.0.0
 * @retun Object Plugin object.
 */
function aeo_init() {
    return Answer_Engine_Optimization::get_instance(__FILE__);
}
add_action('plugins_loaded', 'aeo_init');
