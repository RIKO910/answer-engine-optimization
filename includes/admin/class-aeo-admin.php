<?php
defined( 'ABSPATH' ) || exit;

/**
 * Admin Handler Class
 *
 * @since 1.0.0
 */
class AEO_Admin {

    /**
     * Constructor
     *
     * @since 1.0.0
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'aeo_add_admin_menu'));
        add_action('admin_init', array($this, 'aeo_settings_init'));
    }

    /**
     * Admin menu under settings.
     *
     * @return void
     * @since 1.0.0
     */
    public function aeo_add_admin_menu() {
        add_options_page(
            __('Answer Engine Optimization', 'answer-engine-optimization'),
            __('AEO Settings', 'answer-engine-optimization'),
            'manage_options',
            'answer-engine-optimization',
            array($this, 'aeo_settings_page')
        );
    }

    /**
     * Admin menu under settings.
     *
     * @return void
     * @since 1.0.0
     */
    public function aeo_settings_init() {
        register_setting(
            'aeo_settings_group',
            'aeo_settings',
            array(
                'sanitize_callback' => array($this, 'aeo_sanitize_settings')
            )
        );

        add_settings_section(
            'aeo_general_section',
            __('General Settings', 'answer-engine-optimization'),
            array($this, 'aeo_general_section_callback'),
            'answer-engine-optimization'
        );

        add_settings_field(
            'aeo_enable_schema',
            __('Enable Schema Markup', 'answer-engine-optimization'),
            array($this, 'aeo_checkbox_field_render'),
            'answer-engine-optimization',
            'aeo_general_section',
            array('name' => 'aeo_enable_schema', 'label' => __('Add structured data to your content', 'answer-engine-optimization'))
        );

        add_settings_field(
            'aeo_auto_questions',
            __('Auto-generate Questions', 'answer-engine-optimization'),
            array($this, 'aeo_checkbox_field_render'),
            'answer-engine-optimization',
            'aeo_general_section',
            array('name' => 'aeo_auto_questions', 'label' => __('Automatically suggest questions from your content', 'answer-engine-optimization'))
        );

        add_settings_field(
            'aeo_target_questions',
            __('Target Questions', 'answer-engine-optimization'),
            array($this, 'aeo_textarea_field_render'),
            'answer-engine-optimization',
            'aeo_general_section',
            array('name' => 'aeo_target_questions', 'label' => __('Enter questions you want to target (one per line)', 'answer-engine-optimization'))
        );

        add_settings_field(
            'aeo_voice_optimization',
            __('Voice Search Optimization', 'answer-engine-optimization'),
            array($this, 'aeo_checkbox_field_render'),
            'answer-engine-optimization',
            'aeo_general_section',
            array('name' => 'aeo_voice_optimization', 'label' => __('Optimize for voice search and assistants', 'answer-engine-optimization'))
        );
    }

    /**
     * Sanitize the plugin settings.
     *
     * @param array $input The unsanitized settings
     * @return array Sanitized settings
     * @since 1.0.0
     */
    public function aeo_sanitize_settings($input) {
        $sanitized = array();

        // Sanitize checkbox fields (should be 1 or 0)
        if (isset($input['aeo_enable_schema'])) {
            $sanitized['aeo_enable_schema'] = $input['aeo_enable_schema'] ? 1 : 0;
        }

        if (isset($input['aeo_auto_questions'])) {
            $sanitized['aeo_auto_questions'] = $input['aeo_auto_questions'] ? 1 : 0;
        }

        if (isset($input['aeo_voice_optimization'])) {
            $sanitized['aeo_voice_optimization'] = $input['aeo_voice_optimization'] ? 1 : 0;
        }

        // Sanitize textarea field (one question per line)
        if (isset($input['aeo_target_questions'])) {
            $questions = explode("\n", $input['aeo_target_questions']);
            $questions = array_map('sanitize_text_field', $questions);
            $sanitized['aeo_target_questions'] = implode("\n", $questions);
        }

        return $sanitized;
    }

    /**
     * General settings callback.
     *
     * @return void
     * @since 1.0.0
     */
    public function aeo_general_section_callback() {
        esc_html_e('Configure how your content is optimized for answer engines.', 'answer-engine-optimization');
    }

    /**
     * General settings checkbox field callback.
     *
     * @return void
     * @since 1.0.0
     */
    public function aeo_checkbox_field_render($args) {
        $options = get_option('aeo_settings');
        ?>
        <input type="checkbox" name="aeo_settings[<?php echo esc_attr($args['name']); ?>]" value="1" <?php checked(1, $options[$args['name']] ?? 0); ?>>
        <label><?php echo esc_html($args['label']); ?></label>
        <?php
    }

    /**
     * General settings textarea field callback.
     *
     * @return void
     * @since 1.0.0
     */
    public function aeo_textarea_field_render($args) {
        $options = get_option('aeo_settings');
        ?>
        <textarea name="aeo_settings[<?php echo esc_attr($args['name']); ?>]" rows="5" cols="50"><?php echo esc_textarea($options[$args['name']] ?? ''); ?></textarea>
        <p class="description"><?php echo esc_html($args['label']); ?></p>
        <?php
    }

    /**
     * General settings page callback.
     *
     * @return void
     * @since 1.0.0
     */
    public function aeo_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Answer Engine Optimization Settings', 'answer-engine-optimization'); ?></h1>
            <form action="options.php" method="post">
                <?php
                settings_fields('aeo_settings_group');
                do_settings_sections('answer-engine-optimization');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }
}