<?php
class AEO_Frontend {
    public function __construct() {
        add_action('wp_head', array($this, 'add_structured_data'), 1);
        add_filter('the_content', array($this, 'enhance_content'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
    }

    public function enqueue_frontend_assets() {
        wp_enqueue_style('aeo-frontend-css', AEO_PLUGIN_URL . 'assets/css/aeo-frontend.css', array(), AEO_VERSION);
    }

    public function add_structured_data() {
        if (!is_singular()) {
            return;
        }

        $options = get_option('aeo_settings');
        if (empty($options['aeo_enable_schema'])) {
            return;
        }

        global $post;
        $schema = array(
            '@context' => 'https://schema.org',
            '@type' => 'Article',
            'headline' => get_the_title($post),
            'description' => wp_strip_all_tags(get_the_excerpt($post)),
            'datePublished' => get_the_date('c', $post),
            'dateModified' => get_the_modified_date('c', $post),
            'author' => array(
                '@type' => 'Person',
                'name' => get_the_author_meta('display_name', $post->post_author)
            ),
            'publisher' => array(
                '@type' => 'Organization',
                'name' => get_bloginfo('name'),
                'logo' => array(
                    '@type' => 'ImageObject',
                    'url' => get_site_icon_url()
                )
            )
        );

        // Add FAQ schema if available
        $faq_items = get_post_meta($post->ID, '_aeo_faq_items', true);
        if (!empty($faq_items) && is_array($faq_items)) {
            $faq_schema = array();

            foreach ($faq_items as $item) {
                $faq_schema[] = array(
                    '@type' => 'Question',
                    'name' => $item['question'],
                    'acceptedAnswer' => array(
                        '@type' => 'Answer',
                        'text' => $item['answer']
                    )
                );
            }

            $schema['mainEntity'] = $faq_schema;
        }

        echo '<script type="application/ld+json">' . wp_json_encode($schema) . '</script>';
    }

    public function enhance_content($content) {
        if (!is_singular() || !in_the_loop() || !is_main_query()) {
            return $content;
        }

        global $post;
        $options = get_option('aeo_settings');

        // Add direct answer at the beginning if available
        $direct_answer = get_post_meta($post->ID, '_aeo_direct_answer', true);
        if (!empty($direct_answer) && !empty($options['aeo_voice_optimization'])) {
            $content = '<div class="aeo-direct-answer"><p>' . esc_html($direct_answer) . '</p></div>' . $content;
        }

        // Add FAQ section if available
        $faq_items = get_post_meta($post->ID, '_aeo_faq_items', true);
        if (!empty($faq_items) && is_array($faq_items)) {
            $faq_html = '<div class="aeo-faq-section"><h2>' . __('Frequently Asked Questions', 'answer-engine-optimization') . '</h2><div class="aeo-faq-items">';

            foreach ($faq_items as $item) {
                $faq_html .= '<div class="aeo-faq-item">';
                $faq_html .= '<h3 class="aeo-faq-question">' . esc_html($item['question']) . '</h3>';
                $faq_html .= '<div class="aeo-faq-answer">' . wpautop(esc_html($item['answer'])) . '</div>';
                $faq_html .= '</div>';
            }

            $faq_html .= '</div></div>';
            $content .= $faq_html;
        }

        return $content;
    }
}