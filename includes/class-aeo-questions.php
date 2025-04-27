<?php
class AEO_Questions {
    public function __construct() {
        add_action('admin_init', array($this, 'setup_question_suggestions'));
        add_filter('the_content', array($this, 'auto_insert_questions'), 20);
        add_action('wp_ajax_aeo_generate_questions', array($this, 'generate_questions_ajax'));
    }

    public function setup_question_suggestions() {
        $options = get_option('aeo_settings');
        if (!empty($options['aeo_auto_questions'])) {
            add_action('admin_footer', array($this, 'question_suggestions_modal'));
            add_action('admin_enqueue_scripts', array($this, 'enqueue_question_scripts'));
        }
    }

    public function enqueue_question_scripts($hook) {
        if ('post.php' === $hook || 'post-new.php' === $hook) {
            wp_enqueue_script(
                'aeo-questions-js',
                AEO_PLUGIN_URL . 'assets/js/aeo-questions.js',
                array('jquery'),
                AEO_VERSION,
                true
            );

            wp_localize_script(
                'aeo-questions-js',
                'aeoQuestionsData',
                array(
                    'ajaxurl' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('aeo_questions_nonce'),
                    'generatingText' => __('Generating questions...', 'answer-engine-optimization'),
                    'errorText' => __('Error generating questions. Please try again.', 'answer-engine-optimization')
                )
            );
        }
    }

    public function question_suggestions_modal() {
        global $post;
        if (!$post || !in_array($post->post_type, get_post_types(array('public' => true)))) {
            return;
        }
        ?>
        <div id="aeo-questions-modal" class="aeo-modal" style="display:none;">
            <div class="aeo-modal-content">
                <div class="aeo-modal-header">
                    <h2><?php _e('Suggested Questions', 'answer-engine-optimization'); ?></h2>
                    <span class="aeo-modal-close">&times;</span>
                </div>
                <div class="aeo-modal-body">
                    <p><?php _e('Here are some questions we detected in your content that you might want to explicitly answer:', 'answer-engine-optimization'); ?></p>
                    <div id="aeo-questions-list"></div>
                    <button id="aeo-generate-questions" class="button button-primary">
                        <?php _e('Generate More Questions', 'answer-engine-optimization'); ?>
                    </button>
                </div>
                <div class="aeo-modal-footer">
                    <button id="aeo-apply-selected" class="button button-primary">
                        <?php _e('Add Selected to FAQ', 'answer-engine-optimization'); ?>
                    </button>
                    <button id="aeo-close-modal" class="button">
                        <?php _e('Close', 'answer-engine-optimization'); ?>
                    </button>
                </div>
            </div>
        </div>
        <?php
    }

    public function generate_questions_ajax() {
        check_ajax_referer('aeo_questions_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(__('Permission denied', 'answer-engine-optimization'));
        }

        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        $post = get_post($post_id);

        if (!$post) {
            wp_send_json_error(__('Post not found', 'answer-engine-optimization'));
        }

        // Get content and clean it up
        $content = $post->post_content;
        $content = wp_strip_all_tags($content);
        $content = preg_replace('/\[.*?\]/', '', $content); // Remove shortcodes

        // Generate questions (this is a simple implementation - consider using an API for better results)
        $questions = $this->generate_questions_from_content($content);

        if (empty($questions)) {
            wp_send_json_error(__('No questions could be generated', 'answer-engine-optimization'));
        }

        wp_send_json_success($questions);
    }

    private function generate_questions_from_content($content) {
        // This is a basic implementation that looks for question patterns
        // In a real-world scenario, you might want to use an NLP API

        $questions = array();
        $sentences = preg_split('/(?<=[.?!])\s+(?=[a-z])/i', $content);

        // Look for sentences that might imply questions
        foreach ($sentences as $sentence) {
            $sentence = trim($sentence);

            // Skip short sentences
            if (str_word_count($sentence) < 5) {
                continue;
            }

            // Check for question words or patterns
            $question_words = array('what', 'how', 'why', 'when', 'where', 'who', 'which', 'can', 'could', 'would', 'should', 'does', 'do', 'is', 'are', 'was', 'were');

            foreach ($question_words as $word) {
                if (stripos($sentence, $word . ' ') === 0 ||
                    stripos($sentence, ' ' . $word . ' ') !== false) {
                    // Convert statement to question
                    $question = $this->statement_to_question($sentence);
                    if ($question && !in_array($question, $questions)) {
                        $questions[] = $question;
                    }
                    break;
                }
            }
        }

        // If we didn't find enough questions, generate some based on the content
        if (count($questions) < 3) {
            $keywords = $this->extract_keywords($content);

            foreach ($keywords as $keyword) {
                $questions[] = 'What is ' . $keyword . '?';
                $questions[] = 'How does ' . $keyword . ' work?';
                $questions[] = 'Why is ' . $keyword . ' important?';
            }

            $questions = array_unique($questions);
            $questions = array_slice($questions, 0, 5); // Limit to 5 questions
        }

        return $questions;
    }

    private function statement_to_question($sentence) {
        // Simple conversion of statements to questions
        $sentence = rtrim($sentence, '.');

        if (stripos($sentence, ' is ') !== false) {
            return 'Is ' . preg_replace('/.* is /i', '', $sentence) . '?';
        }

        if (stripos($sentence, ' are ') !== false) {
            return 'Are ' . preg_replace('/.* are /i', '', $sentence) . '?';
        }

        if (stripos($sentence, ' can ') !== false) {
            return 'Can ' . preg_replace('/.* can /i', '', $sentence) . '?';
        }

        return 'What is ' . strtolower($sentence) . '?';
    }

    private function extract_keywords($content, $limit = 5) {
        // Remove stop words
        $stop_words = array('the', 'and', 'a', 'to', 'of', 'in', 'is', 'it', 'that', 'for', 'you', 'on', 'with', 'as', 'at', 'be', 'this', 'by', 'from');
        $words = str_word_count(strtolower($content), 1);
        $words = array_diff($words, $stop_words);

        // Count word frequency
        $word_counts = array_count_values($words);
        arsort($word_counts);

        // Get top words
        $keywords = array_slice(array_keys($word_counts), 0, $limit);

        return $keywords;
    }

    public function auto_insert_questions($content) {
        if (!is_singular() || !in_the_loop() || !is_main_query()) {
            return $content;
        }

        $options = get_option('aeo_settings');
        if (empty($options['aeo_auto_questions'])) {
            return $content;
        }

        global $post;

        // Check if we already have FAQ items
        $faq_items = get_post_meta($post->ID, '_aeo_faq_items', true);
        if (!empty($faq_items)) {
            return $content;
        }

        // Get target questions from settings
        $target_questions = array();
        if (!empty($options['aeo_target_questions'])) {
            $target_questions = array_filter(array_map('trim', explode("\n", $options['aeo_target_questions'])));
        }

        // Generate questions from content
        $generated_questions = $this->generate_questions_from_content($content);

        // Combine and limit questions
        $all_questions = array_unique(array_merge($target_questions, $generated_questions));
        $questions_to_answer = array_slice($all_questions, 0, 3); // Limit to 3 questions

        if (empty($questions_to_answer)) {
            return $content;
        }

        // Create FAQ section
        $faq_html = '<div class="aeo-auto-faq"><h2>' . __('People Also Ask', 'answer-engine-optimization') . '</h2>';
        $faq_html .= '<div class="aeo-auto-faq-items">';

        foreach ($questions_to_answer as $question) {
            // Try to find answer in content
            $answer = $this->find_answer_in_content($question, $content);

            if (!$answer) {
                $answer = __('This question is addressed in the article above.', 'answer-engine-optimization');
            }

            $faq_html .= '<div class="aeo-auto-faq-item">';
            $faq_html .= '<h3 class="aeo-auto-faq-question">' . esc_html($question) . '</h3>';
            $faq_html .= '<div class="aeo-auto-faq-answer">' . wpautop(esc_html($answer)) . '</div>';
            $faq_html .= '</div>';
        }

        $faq_html .= '</div></div>';

        // Insert after the first paragraph
        $paragraphs = explode('</p>', $content);
        if (count($paragraphs) > 1) {
            $paragraphs[1] .= $faq_html;
            $content = implode('</p>', $paragraphs);
        } else {
            $content .= $faq_html;
        }

        return $content;
    }

    private function find_answer_in_content($question, $content) {
        $clean_content = wp_strip_all_tags($content);

        // Extract question keywords
        $question_keywords = $this->extract_keywords($question, 3);

        // Find sentences containing these keywords
        $sentences = preg_split('/(?<=[.?!])\s+(?=[a-z])/i', $clean_content);
        $matching_sentences = array();

        foreach ($sentences as $sentence) {
            $sentence = trim($sentence);
            $sentence_keywords = $this->extract_keywords($sentence, 10);

            $matches = array_intersect($question_keywords, $sentence_keywords);
            if (count($matches) >= 2) { // At least 2 matching keywords
                $matching_sentences[] = $sentence;
            }
        }

        if (!empty($matching_sentences)) {
            return implode(' ', array_slice($matching_sentences, 0, 2)); // Return first 2 matching sentences
        }

        return false;
    }
}