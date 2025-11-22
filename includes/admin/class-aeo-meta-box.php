<?php
defined( 'ABSPATH' ) || exit;

/**
 * Meta Handler Class
 *
 * @since 1.0.0
 */
class AEO_Meta_Box {

    /**
     * Constructor
     *
     * @since 1.0.0
     */
    public function __construct() {
        add_action('add_meta_boxes', array($this, 'aeo_add_meta_boxes'));
        add_action('save_post', array($this, 'aeo_save_post_meta'), 10, 2);
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }


    /**
     * Post edit page below add meta box.
     *
     * @return void
     * @since 1.0.0
     */
    public function aeo_add_meta_boxes() {
        $post_types = get_post_types(array('public' => true));

        foreach ($post_types as $post_type) {
            add_meta_box(
                'aeo_meta_box',
                __('Answer Engine Optimization', 'answer-engine-optimization'),
                array($this, 'aeo_render_meta_box'),
                $post_type,
                'normal',
                'high'
            );
        }
    }

    /**
     * Post edit page render settings options.
     *
     * @return void
     * @since 1.0.0
     */
    public function aeo_render_meta_box($post) {
        wp_nonce_field('aeo_meta_box', 'aeo_meta_box_nonce');

        $target_question = get_post_meta($post->ID, '_aeo_target_question', true);
        $direct_answer   = get_post_meta($post->ID, '_aeo_direct_answer', true);
        $faq_items       = get_post_meta($post->ID, '_aeo_faq_items', true);

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


    /**
     * Post edit page render settings save options.
     *
     * @param int $post_id The ID of the post being saved.
     * @param WP_Post $post The post object.
     * @return void | mixed
     * @since 1.0.0
     */
    public function aeo_save_post_meta($post_id, $post) {
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

    /**
     * Enqueue scripts and styles
     *
     * @param $hook
     * @return void
     * @since 1.0.0
     */
    public function enqueue_admin_assets($hook) {
        if ('settings_page_answer-engine-optimization' === $hook || 'post.php' === $hook || 'post-new.php' === $hook) {

            wp_enqueue_style(
                'aeo-admin-css',
                AEO_PLUGIN_URL . 'assets/css/aeo-admin.css',
                array(),
                AEO_VERSION
            );

            wp_enqueue_script(
                'aeo-admin-js',
                AEO_PLUGIN_URL . 'assets/js/aeo-admin.js',
                array( 'jquery' ),
                AEO_VERSION,
                true
            );
        }
    }
}