<?php
defined( 'ABSPATH' ) || exit;

/**
 * Schema Handler Class
 *
 * @since 1.0.0
 */
class AEO_Schema {
    public function __construct() {
        add_action('wp_head', array($this, 'output_schema_markup'), 2);
        add_filter('the_content', array($this, 'add_howto_schema'), 20);
        add_filter('the_content', array($this, 'add_definition_schema'), 20);
    }

    /**
     * Output all schema markup in the head section
     */
    public function output_schema_markup() {
        if (!is_singular()) {
            return;
        }

        global $post;
        $options = get_option('aeo_settings');

        // Main article schema
        $this->output_article_schema($post);

        // FAQ schema (handled in Frontend class)

        // HowTo schema (if detected in content)
        if (!empty($options['aeo_enable_schema'])) {
            $this->output_howto_schema($post);
        }

        // Definition schema (if detected in content)
        if (!empty($options['aeo_enable_schema'])) {
            $this->output_definition_schema($post);
        }
    }

    /**
     * Output Article schema markup
     */
    private function output_article_schema($post) {
        $options = get_option('aeo_settings');
        if (empty($options['aeo_enable_schema'])) {
            return;
        }

        $schema = array(
            '@context' => 'https://schema.org',
            '@type' => 'Article',
            'headline' => get_the_title($post),
            'description' => wp_strip_all_tags(get_the_excerpt($post)),
            'datePublished' => get_the_date('c', $post),
            'dateModified' => get_the_modified_date('c', $post),
            'mainEntityOfPage' => array(
                '@type' => 'WebPage',
                '@id' => get_permalink($post)
            ),
            'author' => array(
                '@type' => 'Person',
                'name' => get_the_author_meta('display_name', $post->post_author)
            ),
            'publisher' => array(
                '@type' => 'Organization',
                'name' => get_bloginfo('name'),
                'logo' => array(
                    '@type' => 'ImageObject',
                    'url' => $this->get_site_logo_url()
                )
            )
        );

        // Add featured image if available
        if (has_post_thumbnail($post->ID)) {
            $image = wp_get_attachment_image_src(get_post_thumbnail_id($post->ID), 'full');
            $schema['image'] = array(
                '@type' => 'ImageObject',
                'url' => $image[0],
                'width' => $image[1],
                'height' => $image[2]
            );
        }

        echo '<script type="application/ld+json">' . wp_json_encode($schema) . '</script>';
    }

    /**
     * Output HowTo schema markup if content contains steps
     */
    private function output_howto_schema($post) {
        $content = $post->post_content;

        // Check for ordered lists which typically indicate steps
        if (preg_match_all('/<ol.*?>(.*?)<\/ol>/is', $content, $matches)) {
            $steps = array();
            $step_count = 0;

            foreach ($matches[1] as $list) {
                if (preg_match_all('/<li.*?>(.*?)<\/li>/is', $list, $list_items)) {
                    foreach ($list_items[1] as $item) {
                        $step_count++;
                        $steps[] = array(
                            '@type' => 'HowToStep',
                            'text' => wp_strip_all_tags($item),
                            // translators: %d is the step number in a how-to guide.
                            'name' => sprintf(__('Step %d', 'answer-engine-optimization'), $step_count),
                            'url' => get_permalink($post) . '#step-' . $step_count
                        );
                    }
                }
            }

            if (!empty($steps)) {
                $howto_schema = array(
                    '@context' => 'https://schema.org',
                    '@type' => 'HowTo',
                    'name' => get_the_title($post),
                    'description' => wp_strip_all_tags(get_the_excerpt($post)),
                    'totalTime' => $this->estimate_reading_time($content),
                    'step' => $steps
                );

                echo '<script type="application/ld+json">' . wp_json_encode($howto_schema) . '</script>';
            }
        }
    }

    /**
     * Output Definition schema markup for key terms
     */
    private function output_definition_schema($post) {
        $content = $post->post_content;

        // Look for definition patterns (term followed by definition)
        if (preg_match_all('/<strong>(.*?)<\/strong>\s*[—:-]\s*(.*?)(?=<strong>|$)/is', $content, $matches, PREG_SET_ORDER)) {
            $definitions = array();

            foreach ($matches as $match) {
                $term = wp_strip_all_tags($match[1]);
                $definition = wp_strip_all_tags($match[2]);

                if (str_word_count($definition) > 3) { // Only include proper definitions
                    $definitions[] = array(
                        '@type' => 'DefinedTerm',
                        'name' => $term,
                        'description' => $definition,
                        'inDefinedTermSet' => get_bloginfo('name')
                    );
                }
            }

            if (!empty($definitions)) {
                $definition_schema = array(
                    '@context' => 'https://schema.org',
                    '@type' => 'DefinedTermSet',
                    // translators: %s is the terms defined guide.
                    'name' => sprintf(__('Terms defined in %s', 'answer-engine-optimization'), get_the_title($post)),
                    'description' => __('Key terms and their definitions from this article', 'answer-engine-optimization'),
                    'hasDefinedTerm' => $definitions
                );

                echo '<script type="application/ld+json">' . wp_json_encode($definition_schema) . '</script>';
            }
        }
    }

    /**
     * Enhance content with HowTo structured data
     */
    public function add_howto_schema($content) {
        if (!is_singular() || !in_the_loop() || !is_main_query()) {
            return $content;
        }

        $options = get_option('aeo_settings');
        if (empty($options['aeo_enable_schema'])) {
            return $content;
        }

        // Add IDs to list items for step anchoring
        if (preg_match_all('/<ol.*?>/i', $content, $matches, PREG_OFFSET_CAPTURE)) {
            $offset = 0;
            $step_count = 0;

            foreach ($matches[0] as $match) {
                $step_count++;
                $pos = $match[1] + $offset;
                $tag = $match[0];
                $new_tag = str_replace('<ol', '<ol data-aeo-howto="true"', $tag);
                $content = substr_replace($content, $new_tag, $pos, strlen($tag));
                $offset += strlen($new_tag) - strlen($tag);
            }
        }

        return $content;
    }

    /**
     * Enhance content with Definition structured data
     */
    public function add_definition_schema($content) {
        if (!is_singular() || !in_the_loop() || !is_main_query()) {
            return $content;
        }

        $options = get_option('aeo_settings');
        if (empty($options['aeo_enable_schema'])) {
            return $content;
        }

        // Mark definition terms with microdata
        $content = preg_replace_callback(
            '/<strong>(.*?)<\/strong>\s*[—:-]\s*(.*?)(?=<strong>|$)/is',
            function($matches) {
                return sprintf(
                    '<strong itemprop="name" data-aeo-definition="true">%s</strong> — <span itemprop="description">%s</span>',
                    $matches[1],
                    $matches[2]
                );
            },
            $content
        );

        return $content;
    }

    /**
     * Get site logo URL for schema markup
     */
    private function get_site_logo_url() {
        $custom_logo_id = get_theme_mod('custom_logo');
        if ($custom_logo_id) {
            $logo = wp_get_attachment_image_src($custom_logo_id, 'full');
            return $logo[0];
        }
        return get_site_icon_url();
    }

    /**
     * Estimate reading time for HowTo schema
     */
    private function estimate_reading_time($content) {
        // phpcs:ignore
        $word_count = str_word_count(strip_tags($content));
        $minutes = ceil($word_count / 200); // Average reading speed

        return 'PT' . $minutes . 'M'; // ISO 8601 duration format
    }
}