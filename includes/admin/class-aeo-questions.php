<?php
defined( 'ABSPATH' ) || exit;

/**
 * Question suggestions in the post editor.
 *
 * Adds a "Suggest questions" modal to the AEO meta box. Suggestions come from the
 * configured AI provider, or from the post's own headings when no key is set.
 *
 * @since 1.0.0
 */
class AEO_Questions {

    public function __construct() {
        add_action( 'admin_init', array( $this, 'setup_question_suggestions' ) );
        add_action( 'wp_ajax_aeo_generate_questions', array( $this, 'generate_questions_ajax' ) );
    }

    public static function is_enabled() {
        return (bool) \AEO\Core\Settings::get( 'aeo_auto_questions', 1 );
    }

    public function setup_question_suggestions() {
        if ( self::is_enabled() ) {
            add_action( 'admin_footer', array( $this, 'question_suggestions_modal' ) );
            add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_question_scripts' ) );
        }
    }

    public function enqueue_question_scripts( $hook ) {
        if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) {
            return;
        }

        wp_enqueue_script(
            'aeo-questions-js',
            AEO_PLUGIN_URL . 'assets/js/aeo-questions.js',
            array( 'jquery' ),
            AEO_VERSION,
            true
        );

        wp_localize_script(
            'aeo-questions-js',
            'aeoQuestionsData',
            array(
                'ajaxurl'        => admin_url( 'admin-ajax.php' ),
                'nonce'          => wp_create_nonce( 'aeo_questions_nonce' ),
                'generatingText' => __( 'Generating…', 'answer-engine-optimization' ),
                'generateText'   => __( 'Suggest more', 'answer-engine-optimization' ),
                'errorText'      => __( 'Could not generate questions. Save the post first, then try again.', 'answer-engine-optimization' ),
                'emptyText'      => __( 'Select at least one question.', 'answer-engine-optimization' ),
                'questionLabel'  => __( 'Question', 'answer-engine-optimization' ),
                'answerLabel'    => __( 'Answer', 'answer-engine-optimization' ),
                'removeLabel'    => __( 'Remove', 'answer-engine-optimization' ),
            )
        );
    }

    public function question_suggestions_modal() {
        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
        if ( ! $screen || 'post' !== $screen->base || ! in_array( $screen->post_type, \AEO\Core\Settings::get_post_types(), true ) ) {
            return;
        }
        ?>
        <div id="aeo-questions-modal" class="aeo-modal" role="dialog" aria-modal="true" aria-labelledby="aeo-questions-title" style="display:none;">
            <div class="aeo-modal-content">
                <div class="aeo-modal-header">
                    <h2 id="aeo-questions-title"><?php esc_html_e( 'Suggested questions', 'answer-engine-optimization' ); ?></h2>
                    <button type="button" class="aeo-modal-close" aria-label="<?php esc_attr_e( 'Close', 'answer-engine-optimization' ); ?>">&times;</button>
                </div>
                <div class="aeo-modal-body">
                    <p><?php esc_html_e( 'Questions readers are likely to ask about this content. Pick the ones you want to add to the FAQ.', 'answer-engine-optimization' ); ?></p>
                    <div id="aeo-questions-list"></div>
                    <button type="button" id="aeo-generate-questions" class="button"><?php esc_html_e( 'Suggest more', 'answer-engine-optimization' ); ?></button>
                </div>
                <div class="aeo-modal-footer">
                    <button type="button" id="aeo-close-modal" class="button"><?php esc_html_e( 'Cancel', 'answer-engine-optimization' ); ?></button>
                    <button type="button" id="aeo-apply-selected" class="button button-primary"><?php esc_html_e( 'Add to FAQ', 'answer-engine-optimization' ); ?></button>
                </div>
            </div>
        </div>
        <?php
    }

    public function generate_questions_ajax() {
        check_ajax_referer( 'aeo_questions_nonce', 'nonce' );

        $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
        $post    = $post_id ? get_post( $post_id ) : null;

        if ( ! $post ) {
            wp_send_json_error( __( 'Post not found', 'answer-engine-optimization' ), 404 );
        }

        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            wp_send_json_error( __( 'Permission denied', 'answer-engine-optimization' ), 403 );
        }

        // Prefer the unsaved editor content when sent, else the saved content.
        $content = isset( $_POST['content'] ) ? wp_kses_post( wp_unslash( $_POST['content'] ) ) : '';
        if ( '' === trim( wp_strip_all_tags( $content ) ) ) {
            $content = $post->post_content;
        }

        $ai    = ( new \AEO\Services\AI\AI_Manager() )->get_provider();
        $items = $ai->generate_faqs( $content, 6 );
        $items = \AEO\Services\AI\AI_Response::normalize_faqs( $items );

        if ( empty( $items ) ) {
            wp_send_json_error( __( 'No questions could be generated', 'answer-engine-optimization' ) );
        }

        wp_send_json_success( $items );
    }
}
