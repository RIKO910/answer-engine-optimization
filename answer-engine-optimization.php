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
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Define plugin constants
define('AEO_VERSION', '1.0.0');
define('AEO_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AEO_PLUGIN_URL', plugin_dir_url(__FILE__));

// Include the main plugin classes
require_once AEO_PLUGIN_DIR . 'includes/class-aeo-admin.php';
require_once AEO_PLUGIN_DIR . 'includes/class-aeo-frontend.php';
require_once AEO_PLUGIN_DIR . 'includes/class-aeo-questions.php';
require_once AEO_PLUGIN_DIR . 'includes/class-aeo-schema.php';

// Initialize the plugin
function aeo_init() {
    $admin = new AEO_Admin();
    $frontend = new AEO_Frontend();
    $questions = new AEO_Questions();
    $schema = new AEO_Schema();

    // Register activation/deactivation hooks
    register_activation_hook(__FILE__, array($admin, 'activate'));
    register_deactivation_hook(__FILE__, array($admin, 'deactivate'));
}
add_action('plugins_loaded', 'aeo_init');

// Load text domain for internationalization
function aeo_load_textdomain() {
    load_plugin_textdomain('answer-engine-optimization', false, dirname(plugin_basename(__FILE__)) . '/languages/');
}
add_action('init', 'aeo_load_textdomain');