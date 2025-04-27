<?php
class AEO_Admin {
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'settings_init'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post', array($this, 'save_meta_box_data'));
    }

    public function activate() {
        // Set default options on activation
        $default_options = array(
            'aeo_enable_schema' => 1,
            'aeo_auto_questions' => 1,
            'aeo_target_questions' => '',
            'aeo_voice_optimization' => 1
        );

        if (!get_option('aeo_settings')) {
            update_option('aeo_settings', $default_options);
        }
    }

    public function deactivate() {
        // Clean up on deactivation if needed
    }

    public function add_admin_menu() {
        add_options_page(
            __('Answer Engine Optimization', 'answer-engine-optimization'),
            __('AEO Settings', 'answer-engine-optimization'),
            'manage_options',
            'answer-engine-optimization',
            array($this, 'settings_page')
        );
    }

    public function settings_init() {
        // phpcs:ignore
        register_setting('aeo_settings_group', 'aeo_settings');

        add_settings_section(
            'aeo_general_section',
            __('General Settings', 'answer-engine-optimization'),
            array($this, 'general_section_callback'),
            'answer-engine-optimization'
        );

        add_settings_field(
            'aeo_enable_schema',
            __('Enable Schema Markup', 'answer-engine-optimization'),
            array($this, 'checkbox_field_render'),
            'answer-engine-optimization',
            'aeo_general_section',
            array('name' => 'aeo_enable_schema', 'label' => __('Add structured data to your content', 'answer-engine-optimization'))
        );

        add_settings_field(
            'aeo_auto_questions',
            __('Auto-generate Questions', 'answer-engine-optimization'),
            array($this, 'checkbox_field_render'),
            'answer-engine-optimization',
            'aeo_general_section',
            array('name' => 'aeo_auto_questions', 'label' => __('Automatically suggest questions from your content', 'answer-engine-optimization'))
        );

        add_settings_field(
            'aeo_target_questions',
            __('Target Questions', 'answer-engine-optimization'),
            array($this, 'textarea_field_render'),
            'answer-engine-optimization',
            'aeo_general_section',
            array('name' => 'aeo_target_questions', 'label' => __('Enter questions you want to target (one per line)', 'answer-engine-optimization'))
        );

        add_settings_field(
            'aeo_voice_optimization',
            __('Voice Search Optimization', 'answer-engine-optimization'),
            array($this, 'checkbox_field_render'),
            'answer-engine-optimization',
            'aeo_general_section',
            array('name' => 'aeo_voice_optimization', 'label' => __('Optimize for voice search and assistants', 'answer-engine-optimization'))
        );
    }

    public function general_section_callback() {
        esc_html_e('Configure how your content is optimized for answer engines.', 'answer-engine-optimization');
    }

    public function checkbox_field_render($args) {
        $options = get_option('aeo_settings');
        ?>
        <input type="checkbox" name="aeo_settings[<?php echo esc_attr($args['name']); ?>]" value="1" <?php checked(1, $options[$args['name']] ?? 0); ?>>
        <label><?php echo esc_html($args['label']); ?></label>
        <?php
    }

    public function textarea_field_render($args) {
        $options = get_option('aeo_settings');
        ?>
        <textarea name="aeo_settings[<?php echo esc_attr($args['name']); ?>]" rows="5" cols="50"><?php echo esc_textarea($options[$args['name']] ?? ''); ?></textarea>
        <p class="description"><?php echo esc_html($args['label']); ?></p>
        <?php
    }

    public function settings_page() {
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

    public function enqueue_admin_assets($hook) {
        if ('settings_page_answer-engine-optimization' === $hook || 'post.php' === $hook || 'post-new.php' === $hook) {
            wp_enqueue_style('aeo-admin-css', AEO_PLUGIN_URL . 'assets/css/aeo-admin.css', array(), AEO_VERSION);
            wp_enqueue_script('aeo-admin-js', AEO_PLUGIN_URL . 'assets/js/aeo-admin.js', array('jquery'), AEO_VERSION, true);
        }
    }

    public function add_meta_boxes() {
        $post_types = get_post_types(array('public' => true));

        foreach ($post_types as $post_type) {
            add_meta_box(
                'aeo_meta_box',
                __('Answer Engine Optimization', 'answer-engine-optimization'),
                array($this, 'render_meta_box'),
                $post_type,
                'normal',
                'high'
            );
        }
    }

    public function render_meta_box($post) {
        wp_nonce_field('aeo_meta_box', 'aeo_meta_box_nonce');

        $target_question = get_post_meta($post->ID, '_aeo_target_question', true);
        $direct_answer = get_post_meta($post->ID, '_aeo_direct_answer', true);
        $faq_items = get_post_meta($post->ID, '_aeo_faq_items', true);

        ?>
        <div class="aeo-meta-box">
            <div class="aeo-field">
                <label for="aeo_target_question"><?php esc_html_e('Target Question', 'answer-engine-optimization'); ?></label>
                <input type="text" id="aeo_target_question" name="aeo_target_question" value="<?php echo esc_attr($target_question); ?>" class="widefat">
                <p class="description"><?php esc_html_e('The specific question this content answers (e.g., "How do I optimize for answer engines?")', 'answer-engine-optimization'); ?></p>
            </div>

            <div class="aeo-field">
                <label for="aeo_direct_answer"><?php esc_html_e('Direct Answer', 'answer-engine-optimization'); ?></label>
                <textarea id="aeo_direct_answer" name="aeo_direct_answer" rows="3" class="widefat"><?php echo esc_textarea($direct_answer); ?></textarea>
                <p class="description"><?php esc_html_e('A concise, 1-2 sentence answer to the target question', 'answer-engine-optimization'); ?></p>
            </div>

            <div class="aeo-field">
                <label><?php esc_html_e('FAQ Items', 'answer-engine-optimization'); ?></label>
                <div id="aeo-faq-items">
                    <?php
                    if (!empty($faq_items) && is_array($faq_items)) {
                        foreach ($faq_items as $index => $faq_item) {
                            ?>
                            <div class="aeo-faq-item">
                                <input type="text" name="aeo_faq_question[]" value="<?php echo esc_attr($faq_item['question']); ?>" placeholder="<?php esc_html_e('Question', 'answer-engine-optimization'); ?>" class="widefat">
                                <textarea name="aeo_faq_answer[]" rows="2" placeholder="<?php esc_html_e('Answer', 'answer-engine-optimization'); ?>" class="widefat"><?php echo esc_textarea($faq_item['answer']); ?></textarea>
                                <button type="button" class="button aeo-remove-faq"><?php esc_html_e('Remove', 'answer-engine-optimization'); ?></button>
                            </div>
                            <?php
                        }
                    }
                    ?>
                </div>
                <button type="button" id="aeo-add-faq" class="button"><?php esc_html_e('Add FAQ Item', 'answer-engine-optimization'); ?></button>
                <p class="description"><?php esc_html_e('Add questions and answers that are relevant to your content', 'answer-engine-optimization'); ?></p>
            </div>
        </div>
        <?php
    }

    public function save_meta_box_data($post_id) {
        // phpcs:ignore
        if (!isset($_POST['aeo_meta_box_nonce']) || !wp_verify_nonce($_POST['aeo_meta_box_nonce'], 'aeo_meta_box')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Save target question
        // phpcs:ignore
        if (isset($_POST['aeo_target_question'])) {
            // phpcs:ignore
            update_post_meta($post_id, '_aeo_target_question', sanitize_text_field($_POST['aeo_target_question']));
        }

        // Save direct answer
        // phpcs:ignore
        if (isset($_POST['aeo_direct_answer'])) {
            // phpcs:ignore
            update_post_meta($post_id, '_aeo_direct_answer', sanitize_textarea_field($_POST['aeo_direct_answer']));
        }

        // Save FAQ items
        $faq_items = array();
        // phpcs:ignore
        if (isset($_POST['aeo_faq_question']) && isset($_POST['aeo_faq_answer'])) {
            // phpcs:ignore
            $questions = $_POST['aeo_faq_question'];
            // phpcs:ignore
            $answers = $_POST['aeo_faq_answer'];

            for ($i = 0; $i < count($questions); $i++) {
                if (!empty($questions[$i]) && !empty($answers[$i])) {
                    $faq_items[] = array(
                        'question' => sanitize_text_field($questions[$i]),
                        'answer' => sanitize_textarea_field($answers[$i])
                    );
                }
            }
        }

        update_post_meta($post_id, '_aeo_faq_items', $faq_items);
    }
}