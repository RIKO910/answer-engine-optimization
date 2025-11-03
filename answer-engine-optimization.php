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

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Define plugin constants
define('AEO_VERSION', '1.0.0');
define('AEO_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AEO_PLUGIN_URL', plugin_dir_url(__FILE__));
define('AEO_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Include the main plugin classes
require_once AEO_PLUGIN_DIR . 'includes/class-aeo-admin.php';
require_once AEO_PLUGIN_DIR . 'includes/class-aeo-frontend.php';
require_once AEO_PLUGIN_DIR . 'includes/class-aeo-questions.php';
require_once AEO_PLUGIN_DIR . 'includes/class-aeo-schema.php';

// Initialize the plugin
function aeo_init() {
    new AEO_Admin();
    new AEO_Frontend();
    new AEO_Questions();
    new AEO_Schema();
}
add_action('plugins_loaded', 'aeo_init');

// Activation hook
register_activation_hook(__FILE__, 'aeo_activate');
function aeo_activate() {
    $default_options = array(
        'aeo_enable_schema' => 1,
        'aeo_auto_questions' => 1,
        'aeo_target_questions' => '',
        'aeo_voice_optimization' => 1
    );

    if (!get_option('aeo_settings')) {
        update_option('aeo_settings', $default_options);
    }

    flush_rewrite_rules();
}

// Deactivation hook
register_deactivation_hook(__FILE__, 'aeo_deactivate');
function aeo_deactivate() {
    flush_rewrite_rules();
}

// Save post meta data
add_action('save_post', 'aeo_save_post_meta', 10, 2);
function aeo_save_post_meta($post_id, $post) {
    // Check if nonce is set
    if (!isset($_POST['aeo_meta_box_nonce'])) {
        return $post_id;
    }

    // Verify nonce
    if (!wp_verify_nonce($_POST['aeo_meta_box_nonce'], 'aeo_meta_box')) {
        return $post_id;
    }

    // Check autosave
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return $post_id;
    }

    // Check permissions
    if (!current_user_can('edit_post', $post_id)) {
        return $post_id;
    }

    // Save target question
    if (isset($_POST['aeo_target_question'])) {
        update_post_meta($post_id, '_aeo_target_question', sanitize_text_field($_POST['aeo_target_question']));
    }

    // Save direct answer
    if (isset($_POST['aeo_direct_answer'])) {
        update_post_meta($post_id, '_aeo_direct_answer', sanitize_textarea_field($_POST['aeo_direct_answer']));
    }

    // Save FAQ items
    if (isset($_POST['aeo_faq_question']) && isset($_POST['aeo_faq_answer'])) {
        $faq_items = array();
        $questions = $_POST['aeo_faq_question'];
        $answers = $_POST['aeo_faq_answer'];

        for ($i = 0; $i < count($questions); $i++) {
            if (!empty($questions[$i]) && !empty($answers[$i])) {
                $faq_items[] = array(
                    'question' => sanitize_text_field($questions[$i]),
                    'answer' => sanitize_textarea_field($answers[$i])
                );
            }
        }

        update_post_meta($post_id, '_aeo_faq_items', $faq_items);
    } else {
        delete_post_meta($post_id, '_aeo_faq_items');
    }
}