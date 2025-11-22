<?php
defined( 'ABSPATH' ) || exit;


/**
 * Frontend Handler Class
 *
 * @since 1.0.0
 */
class AEO_Frontend {
    public function __construct() {
        add_action('wp_head', array($this, 'add_structured_data'), 1);
        add_filter('the_content', array($this, 'enhance_content'), 15);
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
    }

    public function enqueue_frontend_assets() {
        if (is_singular()) {
            wp_enqueue_style(
                'aeo-frontend-css',
                AEO_PLUGIN_URL . 'assets/css/aeo-frontend.css',
                array(),
                AEO_VERSION
            );

            wp_enqueue_script(
                'aeo-frontend-js',
                AEO_PLUGIN_URL . 'assets/js/aeo-frontend.js',
                array('jquery'),
                AEO_VERSION,
                true
            );
        }
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

        $schema_data = array();

        // Main Article Schema
        $article_schema = array(
            '@context' => 'https://schema.org',
            '@type' => 'Article',
            'headline' => get_the_title($post->ID),
            'datePublished' => get_the_date('c', $post->ID),
            'dateModified' => get_the_modified_date('c', $post->ID),
            'mainEntityOfPage' => array(
                '@type' => 'WebPage',
                '@id' => get_permalink($post->ID)
            ),
            'author' => array(
                '@type' => 'Person',
                'name' => get_the_author_meta('display_name', $post->post_author),
                'url' => get_author_posts_url($post->post_author)
            ),
            'publisher' => array(
                '@type' => 'Organization',
                'name' => get_bloginfo('name'),
                'url' => home_url(),
                'logo' => array(
                    '@type' => 'ImageObject',
                    'url' => $this->get_site_logo_url()
                )
            )
        );

        // Add description
        $excerpt = get_the_excerpt($post->ID);
        if ($excerpt) {
            $article_schema['description'] = wp_strip_all_tags($excerpt);
        }

        // Add featured image
        if (has_post_thumbnail($post->ID)) {
            $image = wp_get_attachment_image_src(get_post_thumbnail_id($post->ID), 'full');
            if ($image) {
                $article_schema['image'] = array(
                    '@type' => 'ImageObject',
                    'url' => $image[0],
                    'width' => $image[1],
                    'height' => $image[2]
                );
            }
        }

        $schema_data[] = $article_schema;

        // FAQ Schema
        $faq_items = get_post_meta($post->ID, '_aeo_faq_items', true);

        if (!empty($faq_items) && is_array($faq_items)) {
            $faq_questions = array();

            foreach ($faq_items as $item) {
                if (!empty($item['question']) && !empty($item['answer'])) {
                    $faq_questions[] = array(
                        '@type' => 'Question',
                        'name' => $item['question'],
                        'acceptedAnswer' => array(
                            '@type' => 'Answer',
                            'text' => $item['answer']
                        )
                    );
                }
            }

            if (!empty($faq_questions)) {
                $faq_schema = array(
                    '@context' => 'https://schema.org',
                    '@type' => 'FAQPage',
                    'mainEntity' => $faq_questions
                );

                $schema_data[] = $faq_schema;
            }
        }

        // Output schema
        foreach ($schema_data as $schema) {
            echo '<script type="application/ld+json">' .
                wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) .
                '</script>' . "\n";
        }
    }

    public function enhance_content($content) {
        if (!is_singular() || !in_the_loop() || !is_main_query()) {
            return $content;
        }

        global $post;
        $options = get_option('aeo_settings');
        $enhanced_content = $content;

        // Add direct answer at the beginning
        $direct_answer = get_post_meta($post->ID, '_aeo_direct_answer', true);

        if (!empty($direct_answer) && !empty($options['aeo_voice_optimization'])) {
            $answer_html = '<div class="aeo-direct-answer">';
            $answer_html .= '<p><strong>' . esc_html__('Quick Answer:', 'answer-engine-optimization') . '</strong> ';
            $answer_html .= esc_html($direct_answer) . '</p>';
            $answer_html .= '</div>';

            $enhanced_content = $answer_html . $enhanced_content;
        }

        // Add FAQ section at the end
        $faq_items = get_post_meta($post->ID, '_aeo_faq_items', true);

        if (!empty($faq_items) && is_array($faq_items)) {
            $faq_html = '<div class="aeo-faq-section">';
            $faq_html .= '<h2>' . esc_html__('Frequently Asked Questions', 'answer-engine-optimization') . '</h2>';
            $faq_html .= '<div class="aeo-faq-items">';

            foreach ($faq_items as $index => $item) {
                if (!empty($item['question']) && !empty($item['answer'])) {
                    $faq_html .= '<div class="aeo-faq-item">';
                    $faq_html .= '<h3 class="aeo-faq-question" data-index="' . esc_attr($index) . '">';
                    $faq_html .= esc_html($item['question']);
                    $faq_html .= '</h3>';
                    $faq_html .= '<div class="aeo-faq-answer">';
                    $faq_html .= wpautop(wp_kses_post($item['answer']));
                    $faq_html .= '</div>';
                    $faq_html .= '</div>';
                }
            }

            $faq_html .= '</div></div>';
            $enhanced_content .= $faq_html;
        }

        return $enhanced_content;
    }

    private function get_site_logo_url() {
        $custom_logo_id = get_theme_mod('custom_logo');

        if ($custom_logo_id) {
            $logo = wp_get_attachment_image_src($custom_logo_id, 'full');
            if ($logo) {
                return $logo[0];
            }
        }

        $site_icon = get_site_icon_url();

        if ($site_icon) {
            return $site_icon;
        }

        // Fallback to WordPress logo
        return includes_url('images/w-logo-blue.png');
    }
}