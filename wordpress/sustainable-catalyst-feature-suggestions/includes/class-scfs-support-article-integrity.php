<?php
/**
 * Support Article content integrity and publication-readiness validation.
 *
 * @package Sustainable_Catalyst_Feature_Suggestions
 */

if (!defined('ABSPATH')) {
    exit;
}

final class SCFS_Support_Article_Integrity {
    const VERSION = '5.2.8';
    const SCHEMA_VERSION = '1.0';
    const ADMIN_SLUG = 'scfs-support-article-integrity';
    const OPTION_KEY = 'scfs_support_article_integrity_settings';
    const LAST_SCAN_OPTION = 'scfs_support_article_integrity_last_scan';
    const NONCE_ACTION = 'scfs_support_article_integrity';
    const REST_BASE = 'support-article-integrity';

    private static $instance = null;

    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('init', array($this, 'register_meta'), 12);
        add_action('add_meta_boxes', array($this, 'add_meta_box'));
        add_action('save_post_' . SCFS_Knowledge_Base_Foundation::ARTICLE_POST_TYPE, array($this, 'validate_on_save'), 70, 3);
        add_action('transition_post_status', array($this, 'validate_on_transition'), 30, 3);
        add_action('admin_menu', array($this, 'register_admin_page'), 30);
        add_action('admin_enqueue_scripts', array($this, 'admin_assets'));
        add_action('admin_post_scfs_validate_support_article', array($this, 'validate_article_action'));
        add_action('admin_post_scfs_validate_all_support_articles', array($this, 'validate_all_action'));
        add_action('admin_post_scfs_export_support_article_integrity', array($this, 'export_action'));
        add_action('rest_api_init', array($this, 'register_rest_routes'));
        add_filter('manage_' . SCFS_Knowledge_Base_Foundation::ARTICLE_POST_TYPE . '_posts_columns', array($this, 'admin_columns'), 30);
        add_action('manage_' . SCFS_Knowledge_Base_Foundation::ARTICLE_POST_TYPE . '_posts_custom_column', array($this, 'admin_column_content'), 30, 2);
        add_action('restrict_manage_posts', array($this, 'admin_filter'));
        add_action('pre_get_posts', array($this, 'apply_admin_filter'));
        add_filter('post_row_actions', array($this, 'row_actions'), 20, 2);
        add_action('admin_notices', array($this, 'editor_notice'));

        if (defined('WP_CLI') && WP_CLI) {
            WP_CLI::add_command('scfs article-integrity', array($this, 'cli_command'));
        }
    }

    public static function activate() {
        $instance = self::instance();
        $instance->register_meta();
        if (!get_option(self::OPTION_KEY)) {
            add_option(self::OPTION_KEY, $instance->default_settings(), '', false);
        }
    }

    public function default_settings() {
        return array(
            'minimum_word_count' => 180,
            'recommended_word_count' => 450,
            'stale_after_days' => 180,
            'ready_score' => 90,
            'review_score' => 75,
            'require_excerpt_or_summary' => '1',
            'require_product' => '1',
            'require_version' => '1',
            'require_component' => '1',
            'require_article_type' => '1',
            'require_collection' => '',
            'require_related_release_for_published' => '',
        );
    }

    public function settings() {
        $saved = (array) get_option(self::OPTION_KEY, array());
        return apply_filters('scfs_support_article_integrity_settings', wp_parse_args($saved, $this->default_settings()));
    }

    public function states() {
        return array(
            'ready' => __('Publication ready', 'sustainable-catalyst-feature-suggestions'),
            'review' => __('Review recommended', 'sustainable-catalyst-feature-suggestions'),
            'needs_work' => __('Needs work', 'sustainable-catalyst-feature-suggestions'),
            'blocked' => __('Publication blocked', 'sustainable-catalyst-feature-suggestions'),
            'unscanned' => __('Not validated', 'sustainable-catalyst-feature-suggestions'),
        );
    }

    public function register_meta() {
        $post_type = SCFS_Knowledge_Base_Foundation::ARTICLE_POST_TYPE;
        $definitions = array(
            '_scfs_integrity_schema' => array('type' => 'string', 'sanitize_callback' => 'sanitize_text_field'),
            '_scfs_integrity_score' => array('type' => 'integer', 'sanitize_callback' => 'absint'),
            '_scfs_integrity_state' => array('type' => 'string', 'sanitize_callback' => array($this, 'sanitize_state')),
            '_scfs_integrity_checked_at' => array('type' => 'string', 'sanitize_callback' => 'sanitize_text_field'),
            '_scfs_integrity_signature' => array('type' => 'string', 'sanitize_callback' => 'sanitize_text_field'),
            '_scfs_integrity_issues' => array('type' => 'string', 'sanitize_callback' => 'sanitize_textarea_field'),
            '_scfs_integrity_word_count' => array('type' => 'integer', 'sanitize_callback' => 'absint'),
            '_scfs_integrity_reading_minutes' => array('type' => 'integer', 'sanitize_callback' => 'absint'),
            '_scfs_integrity_stale' => array('type' => 'boolean', 'sanitize_callback' => 'rest_sanitize_boolean'),
        );
        foreach ($definitions as $key => $definition) {
            register_post_meta($post_type, $key, array(
                'type' => $definition['type'],
                'single' => true,
                'show_in_rest' => true,
                'sanitize_callback' => $definition['sanitize_callback'],
                'auth_callback' => function () {
                    return current_user_can('edit_posts');
                },
            ));
        }
    }

    public function sanitize_state($value) {
        $value = sanitize_key((string) $value);
        return isset($this->states()[$value]) ? $value : 'unscanned';
    }

    public function add_meta_box() {
        add_meta_box(
            'scfs-support-article-integrity',
            __('Publication Readiness', 'sustainable-catalyst-feature-suggestions'),
            array($this, 'render_meta_box'),
            SCFS_Knowledge_Base_Foundation::ARTICLE_POST_TYPE,
            'side',
            'high'
        );
    }

    public function admin_assets($hook) {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        $is_article = $screen && $screen->post_type === SCFS_Knowledge_Base_Foundation::ARTICLE_POST_TYPE;
        if (!$is_article && strpos((string) $hook, self::ADMIN_SLUG) === false) {
            return;
        }
        $file = dirname(__DIR__) . '/assets/support-article-integrity.css';
        wp_enqueue_style(
            'scfs-support-article-integrity',
            plugins_url('assets/support-article-integrity.css', dirname(__DIR__) . '/sustainable-catalyst-feature-suggestions.php'),
            array(),
            file_exists($file) ? (string) filemtime($file) : self::VERSION
        );
    }

    private function taxonomy_terms($post_id, $taxonomy) {
        $terms = get_the_terms($post_id, $taxonomy);
        return is_array($terms) ? $terms : array();
    }

    private function heading_records($content) {
        $records = array();
        if (preg_match_all('/<h([1-6])\b[^>]*>(.*?)<\/h\1>/is', (string) $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $records[] = array(
                    'level' => (int) $match[1],
                    'text' => trim(wp_strip_all_tags($match[2])),
                );
            }
        }
        return $records;
    }

    private function add_issue(&$issues, $code, $severity, $message, $context = array()) {
        $issues[] = array(
            'code' => sanitize_key($code),
            'severity' => in_array($severity, array('error', 'warning', 'info'), true) ? $severity : 'warning',
            'message' => sanitize_text_field($message),
            'context' => array_map('sanitize_text_field', array_map('strval', (array) $context)),
        );
    }

    private function local_link_issues($content) {
        $issues = array();
        if (!preg_match_all('/<a\b[^>]*href=["\']([^"\']*)["\'][^>]*>/is', (string) $content, $matches)) {
            return $issues;
        }
        foreach (array_unique($matches[1]) as $href) {
            $href = html_entity_decode(trim($href), ENT_QUOTES, 'UTF-8');
            if ($href === '' || $href === '#' || stripos($href, 'javascript:') === 0) {
                $issues[] = $href === '' ? '(empty)' : $href;
                continue;
            }
            if ($href[0] === '#') {
                $target = preg_quote(substr($href, 1), '/');
                if ($target !== '' && !preg_match('/\bid=["\']' . $target . '["\']/i', (string) $content)) {
                    $issues[] = $href;
                }
                continue;
            }
            if (function_exists('home_url') && strpos($href, home_url('/')) === 0 && function_exists('url_to_postid')) {
                $path = wp_parse_url($href, PHP_URL_PATH);
                if ($path && url_to_postid($href) === 0 && !preg_match('/\.(?:pdf|csv|json|zip|png|jpe?g|webp|svg)$/i', $path)) {
                    $issues[] = $href;
                }
            }
        }
        return $issues;
    }

    private function placeholder_matches($content) {
        $patterns = array(
            'Explain what this product or capability does',
            'List prerequisites, permissions, or required configuration',
            'Complete the first setup step',
            'State the outcome this guide helps the reader achieve',
            'Describe the first action',
            'Document common failure states and recovery steps',
            'Describe the visible error, message, or behavior',
            'List the most likely causes in diagnostic order',
            'Perform the safest corrective step',
            'Define the supported interface, field, endpoint, command, or behavior',
            'Replace this placeholder',
            'Lorem ipsum',
            'TODO',
            'TBD',
        );
        $matches = array();
        foreach ($patterns as $pattern) {
            if (stripos((string) $content, $pattern) !== false) {
                $matches[] = $pattern;
            }
        }
        return $matches;
    }

    private function relationship_context($post_id, $product_terms, $component_terms) {
        $release_terms = $this->taxonomy_terms($post_id, SCFS_Product_Integration::RELEASE_TAXONOMY);
        $related_suggestions = array_values(array_filter(array_map('absint', (array) get_post_meta($post_id, '_scfs_related_suggestions', true))));
        $known_issue_count = 0;
        $tax_query = array('relation' => 'OR');
        foreach (array(
            SCFS_Product_Integration::PRODUCT_TAXONOMY => $product_terms,
            SCFS_Product_Integration::COMPONENT_TAXONOMY => $component_terms,
        ) as $taxonomy => $terms) {
            if ($terms) {
                $tax_query[] = array(
                    'taxonomy' => $taxonomy,
                    'field' => 'term_id',
                    'terms' => wp_list_pluck($terms, 'term_id'),
                );
            }
        }
        if (count($tax_query) > 1) {
            $query = new WP_Query(array(
                'post_type' => SCFS_Knowledge_Base_Foundation::ISSUE_POST_TYPE,
                'post_status' => 'publish',
                'posts_per_page' => 1,
                'fields' => 'ids',
                'no_found_rows' => false,
                'tax_query' => $tax_query,
            ));
            $known_issue_count = (int) $query->found_posts;
        }
        return array(
            'release_count' => count($release_terms),
            'related_suggestion_count' => count($related_suggestions),
            'known_issue_count' => $known_issue_count,
        );
    }

    public function assess_article($post_id, $persist = true) {
        $post = get_post($post_id);
        if (!$post || $post->post_type !== SCFS_Knowledge_Base_Foundation::ARTICLE_POST_TYPE) {
            return new WP_Error('scfs_integrity_not_found', __('Support Article not found.', 'sustainable-catalyst-feature-suggestions'));
        }

        $settings = $this->settings();
        $issues = array();
        $content = (string) $post->post_content;
        $plain = trim(preg_replace('/\s+/', ' ', wp_strip_all_tags(strip_shortcodes($content))));
        $words = $plain === '' ? array() : preg_split('/\s+/', $plain);
        $word_count = count($words);
        $reading_minutes = max(1, (int) ceil($word_count / 220));
        $headings = $this->heading_records($content);
        $products = $this->taxonomy_terms($post_id, SCFS_Product_Integration::PRODUCT_TAXONOMY);
        $versions = $this->taxonomy_terms($post_id, SCFS_Product_Integration::VERSION_TAXONOMY);
        $components = $this->taxonomy_terms($post_id, SCFS_Product_Integration::COMPONENT_TAXONOMY);
        $article_types = $this->taxonomy_terms($post_id, SCFS_Knowledge_Base_Foundation::ARTICLE_TYPE_TAXONOMY);
        $collections = $this->taxonomy_terms($post_id, SCFS_Knowledge_Base_Foundation::COLLECTION_TAXONOMY);
        $verified_version = trim((string) get_post_meta($post_id, '_scfs_last_verified_version', true));
        $summary = trim((string) get_post_meta($post_id, '_scfs_kb_summary', true));
        $excerpt = trim((string) $post->post_excerpt);

        if (trim((string) $post->post_title) === '' || strlen(trim(wp_strip_all_tags((string) $post->post_title))) < 8) {
            $this->add_issue($issues, 'title_incomplete', 'error', __('Add a specific Support Article title.', 'sustainable-catalyst-feature-suggestions'));
        }
        if ($word_count < (int) $settings['minimum_word_count']) {
            $this->add_issue($issues, 'content_too_short', 'error', sprintf(__('Article body has %1$d words; the minimum is %2$d.', 'sustainable-catalyst-feature-suggestions'), $word_count, (int) $settings['minimum_word_count']));
        } elseif ($word_count < (int) $settings['recommended_word_count']) {
            $this->add_issue($issues, 'content_brief', 'warning', sprintf(__('Article body has %1$d words; %2$d or more is recommended for complete guidance.', 'sustainable-catalyst-feature-suggestions'), $word_count, (int) $settings['recommended_word_count']));
        }
        if ($settings['require_excerpt_or_summary'] === '1' && $excerpt === '' && $summary === '') {
            $this->add_issue($issues, 'summary_missing', 'error', __('Add an excerpt or Knowledge Base summary.', 'sustainable-catalyst-feature-suggestions'));
        }
        if ($settings['require_product'] === '1' && !$products) {
            $this->add_issue($issues, 'product_missing', 'error', __('Assign at least one product.', 'sustainable-catalyst-feature-suggestions'));
        }
        if ($settings['require_version'] === '1' && !$versions) {
            $this->add_issue($issues, 'version_missing', 'error', __('Assign at least one supported product version.', 'sustainable-catalyst-feature-suggestions'));
        }
        if ($settings['require_component'] === '1' && !$components) {
            $this->add_issue($issues, 'component_missing', 'warning', __('Assign the product component covered by this article.', 'sustainable-catalyst-feature-suggestions'));
        }
        if ($settings['require_article_type'] === '1' && !$article_types) {
            $this->add_issue($issues, 'article_type_missing', 'error', __('Assign an Article Type.', 'sustainable-catalyst-feature-suggestions'));
        }
        if ($settings['require_collection'] === '1' && !$collections) {
            $this->add_issue($issues, 'collection_missing', 'warning', __('Assign a documentation collection.', 'sustainable-catalyst-feature-suggestions'));
        }
        if ($verified_version === '') {
            $this->add_issue($issues, 'verified_version_missing', 'error', __('Set the last verified version.', 'sustainable-catalyst-feature-suggestions'));
        } elseif ($versions) {
            $version_values = array();
            foreach ($versions as $term) {
                $version_values[] = strtolower((string) $term->name);
                $version_values[] = strtolower((string) $term->slug);
            }
            $normalized_verified = strtolower(sanitize_title($verified_version));
            $normalized_names = array_map('sanitize_title', $version_values);
            if (!in_array(strtolower($verified_version), $version_values, true) && !in_array($normalized_verified, $normalized_names, true)) {
                $this->add_issue($issues, 'verified_version_unassigned', 'warning', __('The verified version does not match an assigned Product Version term.', 'sustainable-catalyst-feature-suggestions'), array('verified_version' => $verified_version));
            }
        }

        if (!$headings) {
            $this->add_issue($issues, 'headings_missing', 'error', __('Add descriptive H2 sections to structure the article.', 'sustainable-catalyst-feature-suggestions'));
        } else {
            $previous = 1;
            foreach ($headings as $heading) {
                if ($heading['level'] === 1) {
                    $this->add_issue($issues, 'content_h1_present', 'warning', __('Remove H1 headings from the article body; the publication title supplies the H1.', 'sustainable-catalyst-feature-suggestions'));
                }
                if ($heading['text'] === '') {
                    $this->add_issue($issues, 'empty_heading', 'error', __('Remove or name empty headings.', 'sustainable-catalyst-feature-suggestions'));
                }
                if ($heading['level'] > $previous + 1) {
                    $this->add_issue($issues, 'heading_level_jump', 'warning', __('Repair skipped heading levels.', 'sustainable-catalyst-feature-suggestions'), array('heading' => $heading['text'], 'level' => $heading['level']));
                }
                $previous = $heading['level'];
            }
            if (!array_filter($headings, static function ($heading) { return $heading['level'] === 2; })) {
                $this->add_issue($issues, 'h2_missing', 'error', __('Use H2 headings for the primary article sections.', 'sustainable-catalyst-feature-suggestions'));
            }
        }

        if (class_exists('SCFS_Editorial_Governance')) {
            $required = SCFS_Editorial_Governance::instance()->required_sections_for_post($post_id);
            $heading_text = array_map(static function ($heading) { return strtolower($heading['text']); }, $headings);
            foreach ((array) $required as $required_heading) {
                $needle = strtolower(trim((string) $required_heading));
                if ($needle !== '' && !array_filter($heading_text, static function ($value) use ($needle) { return $value === $needle || strpos($value, $needle) !== false; })) {
                    $this->add_issue($issues, 'required_section_missing', 'warning', sprintf(__('Add the required “%s” section.', 'sustainable-catalyst-feature-suggestions'), $required_heading), array('section' => $required_heading));
                }
            }
        }

        foreach ($this->placeholder_matches($content) as $placeholder) {
            $this->add_issue($issues, 'template_placeholder', 'error', __('Replace template placeholder text before publication.', 'sustainable-catalyst-feature-suggestions'), array('placeholder' => $placeholder));
        }
        foreach ($this->local_link_issues($content) as $href) {
            $this->add_issue($issues, 'link_invalid', 'warning', __('Repair an empty, unsafe, missing-anchor, or unresolved internal link.', 'sustainable-catalyst-feature-suggestions'), array('href' => $href));
        }

        if (preg_match_all('/<img\b[^>]*>/is', $content, $image_tags)) {
            foreach ($image_tags[0] as $tag) {
                if (!preg_match('/\balt=["\'][^"\']+["\']/i', $tag)) {
                    $this->add_issue($issues, 'image_alt_missing', 'warning', __('Add meaningful alternative text to each image.', 'sustainable-catalyst-feature-suggestions'));
                }
            }
        }
        if (preg_match_all('/<figure\b[^>]*>(.*?)<\/figure>/is', $content, $figures)) {
            foreach ($figures[1] as $figure) {
                if (stripos($figure, '<figcaption') === false) {
                    $this->add_issue($issues, 'figure_caption_missing', 'info', __('Add a figure caption when the image requires context or attribution.', 'sustainable-catalyst-feature-suggestions'));
                }
            }
        }
        if (stripos($content, '<table') !== false && stripos($content, '<th') === false) {
            $this->add_issue($issues, 'table_headers_missing', 'warning', __('Add table header cells so the table is understandable and accessible.', 'sustainable-catalyst-feature-suggestions'));
        }

        $relationships = $this->relationship_context($post_id, $products, $components);
        if ($post->post_status === 'publish' && $settings['require_related_release_for_published'] === '1' && $relationships['release_count'] === 0) {
            $this->add_issue($issues, 'related_release_missing', 'warning', __('Associate the published article with a release record.', 'sustainable-catalyst-feature-suggestions'));
        }
        if ($relationships['release_count'] === 0 && $relationships['related_suggestion_count'] === 0 && $relationships['known_issue_count'] === 0) {
            $this->add_issue($issues, 'relationship_context_empty', 'info', __('Consider linking this article to a release, known issue, or reviewed product suggestion.', 'sustainable-catalyst-feature-suggestions'));
        }

        $stale = false;
        $stale_days = max(1, (int) $settings['stale_after_days']);
        $modified_timestamp = strtotime($post->post_modified_gmt ?: $post->post_modified);
        if ($modified_timestamp && $modified_timestamp < time() - ($stale_days * DAY_IN_SECONDS)) {
            $stale = true;
            $this->add_issue($issues, 'content_stale', 'warning', sprintf(__('This article has not been updated in more than %d days.', 'sustainable-catalyst-feature-suggestions'), $stale_days));
        }
        $review_due = get_post_meta($post_id, '_scfs_editorial_review_due_at', true);
        if ($review_due && strtotime($review_due . ' 23:59:59') < time()) {
            $stale = true;
            $this->add_issue($issues, 'review_overdue', 'error', __('The editorial review date has passed.', 'sustainable-catalyst-feature-suggestions'), array('review_due' => $review_due));
        }

        $weights = array('error' => 16, 'warning' => 6, 'info' => 2);
        $score = 100;
        $counts = array('error' => 0, 'warning' => 0, 'info' => 0);
        foreach ($issues as $issue) {
            $counts[$issue['severity']]++;
            $score -= $weights[$issue['severity']];
        }
        $score = max(0, min(100, $score));
        if ($counts['error'] > 0) {
            $state = $score >= 50 ? 'needs_work' : 'blocked';
        } elseif ($score >= (int) $settings['ready_score']) {
            $state = 'ready';
        } elseif ($score >= (int) $settings['review_score']) {
            $state = 'review';
        } else {
            $state = 'needs_work';
        }

        $signature = hash('sha256', wp_json_encode(array(
            $post->post_title,
            $post->post_content,
            $post->post_excerpt,
            $post->post_modified_gmt,
            wp_list_pluck($products, 'term_id'),
            wp_list_pluck($versions, 'term_id'),
            wp_list_pluck($components, 'term_id'),
            wp_list_pluck($article_types, 'term_id'),
            wp_list_pluck($collections, 'term_id'),
            $verified_version,
            $review_due,
        )));

        $record = array(
            'schema' => 'scfs-support-article-integrity/' . self::SCHEMA_VERSION,
            'version' => self::VERSION,
            'post_id' => (int) $post_id,
            'title' => get_the_title($post_id),
            'status' => $post->post_status,
            'score' => $score,
            'state' => $state,
            'state_label' => $this->states()[$state],
            'stale' => $stale,
            'word_count' => $word_count,
            'reading_minutes' => $reading_minutes,
            'counts' => $counts,
            'issues' => $issues,
            'relationships' => $relationships,
            'checked_at' => gmdate('c'),
            'signature' => $signature,
            'edit_url' => get_edit_post_link($post_id, 'raw'),
            'public_url' => get_permalink($post_id),
        );

        if ($persist) {
            update_post_meta($post_id, '_scfs_integrity_schema', self::SCHEMA_VERSION);
            update_post_meta($post_id, '_scfs_integrity_score', $score);
            update_post_meta($post_id, '_scfs_integrity_state', $state);
            update_post_meta($post_id, '_scfs_integrity_checked_at', $record['checked_at']);
            update_post_meta($post_id, '_scfs_integrity_signature', $signature);
            update_post_meta($post_id, '_scfs_integrity_issues', wp_json_encode($issues));
            update_post_meta($post_id, '_scfs_integrity_word_count', $word_count);
            update_post_meta($post_id, '_scfs_integrity_reading_minutes', $reading_minutes);
            update_post_meta($post_id, '_scfs_integrity_stale', $stale ? 1 : 0);
            if (!get_post_meta($post_id, '_scfs_estimated_minutes', true)) {
                update_post_meta($post_id, '_scfs_estimated_minutes', $reading_minutes);
            }
            do_action('scfs_support_article_integrity_assessed', $post_id, $record);
        }
        return $record;
    }

    public function stored_record($post_id) {
        $issues = json_decode((string) get_post_meta($post_id, '_scfs_integrity_issues', true), true);
        $state = get_post_meta($post_id, '_scfs_integrity_state', true) ?: 'unscanned';
        return array(
            'post_id' => (int) $post_id,
            'score' => (int) get_post_meta($post_id, '_scfs_integrity_score', true),
            'state' => $this->sanitize_state($state),
            'state_label' => $this->states()[$this->sanitize_state($state)],
            'checked_at' => (string) get_post_meta($post_id, '_scfs_integrity_checked_at', true),
            'stale' => (bool) get_post_meta($post_id, '_scfs_integrity_stale', true),
            'word_count' => (int) get_post_meta($post_id, '_scfs_integrity_word_count', true),
            'reading_minutes' => (int) get_post_meta($post_id, '_scfs_integrity_reading_minutes', true),
            'issues' => is_array($issues) ? $issues : array(),
        );
    }

    public function validate_on_save($post_id, $post, $update) {
        if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id) || !$post || $post->post_type !== SCFS_Knowledge_Base_Foundation::ARTICLE_POST_TYPE) {
            return;
        }
        $this->assess_article($post_id, true);
    }

    public function validate_on_transition($new_status, $old_status, $post) {
        if (!$post || $post->post_type !== SCFS_Knowledge_Base_Foundation::ARTICLE_POST_TYPE || $new_status === $old_status) {
            return;
        }
        if (in_array($new_status, array('publish', 'future', 'pending'), true)) {
            $this->assess_article($post->ID, true);
        }
    }

    public function scan_all($args = array()) {
        $args = wp_parse_args($args, array(
            'post_status' => array('publish', 'future', 'pending', 'draft', 'private'),
            'posts_per_page' => -1,
            'product' => '',
        ));
        $query_args = array(
            'post_type' => SCFS_Knowledge_Base_Foundation::ARTICLE_POST_TYPE,
            'post_status' => $args['post_status'],
            'posts_per_page' => (int) $args['posts_per_page'],
            'fields' => 'ids',
            'orderby' => 'ID',
            'order' => 'ASC',
            'no_found_rows' => true,
        );
        if ($args['product']) {
            $query_args['tax_query'] = array(array(
                'taxonomy' => SCFS_Product_Integration::PRODUCT_TAXONOMY,
                'field' => 'slug',
                'terms' => sanitize_title($args['product']),
            ));
        }
        $ids = get_posts($query_args);
        $summary = array(
            'version' => self::VERSION,
            'total' => count($ids),
            'ready' => 0,
            'review' => 0,
            'needs_work' => 0,
            'blocked' => 0,
            'stale' => 0,
            'errors' => 0,
            'warnings' => 0,
            'records' => array(),
            'completed_at' => gmdate('c'),
        );
        foreach ($ids as $id) {
            $record = $this->assess_article($id, true);
            if (is_wp_error($record)) {
                continue;
            }
            $summary[$record['state']]++;
            $summary['stale'] += $record['stale'] ? 1 : 0;
            $summary['errors'] += $record['counts']['error'];
            $summary['warnings'] += $record['counts']['warning'];
            $summary['records'][] = array(
                'post_id' => $record['post_id'],
                'title' => $record['title'],
                'score' => $record['score'],
                'state' => $record['state'],
                'stale' => $record['stale'],
                'errors' => $record['counts']['error'],
                'warnings' => $record['counts']['warning'],
            );
        }
        update_option(self::LAST_SCAN_OPTION, $summary, false);
        return $summary;
    }

    public function register_admin_page() {
        add_submenu_page(
            'edit.php?post_type=' . Sustainable_Catalyst_Feature_Suggestions::POST_TYPE,
            __('Article Integrity', 'sustainable-catalyst-feature-suggestions'),
            __('Article Integrity', 'sustainable-catalyst-feature-suggestions'),
            'edit_others_posts',
            self::ADMIN_SLUG,
            array($this, 'render_admin_page')
        );
    }

    public function render_meta_box($post) {
        $record = $this->stored_record($post->ID);
        $state = $record['state'];
        echo '<div class="scfs-integrity-box scfs-integrity-state-' . esc_attr($state) . '">';
        echo '<div class="scfs-integrity-score"><strong>' . esc_html((string) $record['score']) . '</strong><span>/100</span></div>';
        echo '<p class="scfs-integrity-state"><strong>' . esc_html($record['state_label']) . '</strong></p>';
        if ($record['checked_at']) {
            echo '<p class="description">' . esc_html(sprintf(__('Checked %s', 'sustainable-catalyst-feature-suggestions'), $record['checked_at'])) . '</p>';
        } else {
            echo '<p class="description">' . esc_html__('This article has not been validated.', 'sustainable-catalyst-feature-suggestions') . '</p>';
        }
        if ($record['issues']) {
            echo '<ul class="scfs-integrity-issues">';
            foreach (array_slice($record['issues'], 0, 6) as $issue) {
                echo '<li class="is-' . esc_attr($issue['severity']) . '">' . esc_html($issue['message']) . '</li>';
            }
            echo '</ul>';
        }
        $url = wp_nonce_url(admin_url('admin-post.php?action=scfs_validate_support_article&post_id=' . $post->ID), self::NONCE_ACTION . '_' . $post->ID);
        echo '<p><a class="button button-secondary" href="' . esc_url($url) . '">' . esc_html__('Validate article now', 'sustainable-catalyst-feature-suggestions') . '</a></p>';
        echo '<p><a href="' . esc_url(admin_url('edit.php?post_type=' . Sustainable_Catalyst_Feature_Suggestions::POST_TYPE . '&page=' . self::ADMIN_SLUG)) . '">' . esc_html__('Open integrity report', 'sustainable-catalyst-feature-suggestions') . '</a></p>';
        echo '</div>';
    }

    public function render_admin_page() {
        if (!current_user_can('edit_others_posts')) {
            wp_die(__('You do not have permission to view Support Article integrity.', 'sustainable-catalyst-feature-suggestions'));
        }
        $last_scan = (array) get_option(self::LAST_SCAN_OPTION, array());
        $state = isset($_GET['integrity_state']) ? $this->sanitize_state(wp_unslash($_GET['integrity_state'])) : '';
        $meta_query = array();
        if ($state && $state !== 'unscanned') {
            $meta_query[] = array('key' => '_scfs_integrity_state', 'value' => $state);
        } elseif ($state === 'unscanned') {
            $meta_query[] = array('key' => '_scfs_integrity_state', 'compare' => 'NOT EXISTS');
        }
        $articles = get_posts(array(
            'post_type' => SCFS_Knowledge_Base_Foundation::ARTICLE_POST_TYPE,
            'post_status' => array('publish', 'future', 'pending', 'draft', 'private'),
            'posts_per_page' => 200,
            'orderby' => 'modified',
            'order' => 'DESC',
            'meta_query' => $meta_query,
        ));
        echo '<div class="wrap scfs-integrity-admin">';
        echo '<h1>' . esc_html__('Support Article Content Integrity', 'sustainable-catalyst-feature-suggestions') . '</h1>';
        echo '<p>' . esc_html__('Validate article completeness, metadata, version coverage, heading structure, accessibility, links, relationships, freshness, and publication readiness without changing existing article URLs or content.', 'sustainable-catalyst-feature-suggestions') . '</p>';
        echo '<div class="scfs-integrity-actions">';
        echo '<a class="button button-primary" href="' . esc_url(wp_nonce_url(admin_url('admin-post.php?action=scfs_validate_all_support_articles'), self::NONCE_ACTION . '_all')) . '">' . esc_html__('Validate all Support Articles', 'sustainable-catalyst-feature-suggestions') . '</a>';
        echo '<a class="button" href="' . esc_url(wp_nonce_url(admin_url('admin-post.php?action=scfs_export_support_article_integrity'), self::NONCE_ACTION . '_export')) . '">' . esc_html__('Export integrity CSV', 'sustainable-catalyst-feature-suggestions') . '</a>';
        echo '</div>';
        if ($last_scan) {
            echo '<div class="scfs-integrity-summary">';
            foreach (array('total' => __('Articles', 'sustainable-catalyst-feature-suggestions'), 'ready' => __('Ready', 'sustainable-catalyst-feature-suggestions'), 'review' => __('Review', 'sustainable-catalyst-feature-suggestions'), 'needs_work' => __('Needs work', 'sustainable-catalyst-feature-suggestions'), 'blocked' => __('Blocked', 'sustainable-catalyst-feature-suggestions'), 'stale' => __('Stale', 'sustainable-catalyst-feature-suggestions')) as $key => $label) {
                echo '<div><span>' . esc_html($label) . '</span><strong>' . esc_html((string) ($last_scan[$key] ?? 0)) . '</strong></div>';
            }
            echo '</div>';
        }
        echo '<form method="get" class="scfs-integrity-filter"><input type="hidden" name="post_type" value="' . esc_attr(Sustainable_Catalyst_Feature_Suggestions::POST_TYPE) . '"><input type="hidden" name="page" value="' . esc_attr(self::ADMIN_SLUG) . '"><label for="integrity_state">' . esc_html__('Readiness state', 'sustainable-catalyst-feature-suggestions') . '</label><select id="integrity_state" name="integrity_state"><option value="">' . esc_html__('All states', 'sustainable-catalyst-feature-suggestions') . '</option>';
        foreach ($this->states() as $key => $label) {
            echo '<option value="' . esc_attr($key) . '" ' . selected($state, $key, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select><button class="button">' . esc_html__('Filter', 'sustainable-catalyst-feature-suggestions') . '</button></form>';
        echo '<table class="widefat striped scfs-integrity-table"><thead><tr><th>' . esc_html__('Article', 'sustainable-catalyst-feature-suggestions') . '</th><th>' . esc_html__('Status', 'sustainable-catalyst-feature-suggestions') . '</th><th>' . esc_html__('Score', 'sustainable-catalyst-feature-suggestions') . '</th><th>' . esc_html__('Readiness', 'sustainable-catalyst-feature-suggestions') . '</th><th>' . esc_html__('Freshness', 'sustainable-catalyst-feature-suggestions') . '</th><th>' . esc_html__('Top issues', 'sustainable-catalyst-feature-suggestions') . '</th></tr></thead><tbody>';
        if (!$articles) {
            echo '<tr><td colspan="6">' . esc_html__('No Support Articles match this view.', 'sustainable-catalyst-feature-suggestions') . '</td></tr>';
        }
        foreach ($articles as $article) {
            $record = $this->stored_record($article->ID);
            $top = array_map(static function ($issue) { return $issue['message']; }, array_slice($record['issues'], 0, 3));
            echo '<tr><td><a href="' . esc_url(get_edit_post_link($article->ID)) . '"><strong>' . esc_html(get_the_title($article)) . '</strong></a><br><span class="description">ID ' . esc_html((string) $article->ID) . '</span></td><td>' . esc_html($article->post_status) . '</td><td><strong>' . esc_html((string) $record['score']) . '</strong></td><td><span class="scfs-integrity-badge is-' . esc_attr($record['state']) . '">' . esc_html($record['state_label']) . '</span></td><td>' . ($record['stale'] ? esc_html__('Stale', 'sustainable-catalyst-feature-suggestions') : esc_html__('Current', 'sustainable-catalyst-feature-suggestions')) . '</td><td>' . esc_html($top ? implode(' · ', $top) : __('No recorded issues', 'sustainable-catalyst-feature-suggestions')) . '</td></tr>';
        }
        echo '</tbody></table></div>';
    }

    public function validate_article_action() {
        if (!current_user_can('edit_posts')) {
            wp_die(__('Permission denied.', 'sustainable-catalyst-feature-suggestions'));
        }
        $post_id = isset($_GET['post_id']) ? absint($_GET['post_id']) : 0;
        check_admin_referer(self::NONCE_ACTION . '_' . $post_id);
        $this->assess_article($post_id, true);
        wp_safe_redirect(get_edit_post_link($post_id, 'raw'));
        exit;
    }

    public function validate_all_action() {
        if (!current_user_can('edit_others_posts')) {
            wp_die(__('Permission denied.', 'sustainable-catalyst-feature-suggestions'));
        }
        check_admin_referer(self::NONCE_ACTION . '_all');
        $this->scan_all();
        wp_safe_redirect(admin_url('edit.php?post_type=' . Sustainable_Catalyst_Feature_Suggestions::POST_TYPE . '&page=' . self::ADMIN_SLUG . '&scfs_integrity_scanned=1'));
        exit;
    }

    public function export_action() {
        if (!current_user_can('edit_others_posts')) {
            wp_die(__('Permission denied.', 'sustainable-catalyst-feature-suggestions'));
        }
        check_admin_referer(self::NONCE_ACTION . '_export');
        $ids = get_posts(array(
            'post_type' => SCFS_Knowledge_Base_Foundation::ARTICLE_POST_TYPE,
            'post_status' => array('publish', 'future', 'pending', 'draft', 'private'),
            'posts_per_page' => -1,
            'fields' => 'ids',
            'orderby' => 'ID',
            'order' => 'ASC',
        ));
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=scfs-support-article-integrity-' . gmdate('Y-m-d') . '.csv');
        $out = fopen('php://output', 'w');
        fputcsv($out, array('ID', 'Title', 'Status', 'Score', 'State', 'Stale', 'Word Count', 'Reading Minutes', 'Checked At', 'Issue Codes'));
        foreach ($ids as $id) {
            $record = $this->stored_record($id);
            fputcsv($out, array(
                $id,
                get_the_title($id),
                get_post_status($id),
                $record['score'],
                $record['state'],
                $record['stale'] ? 'yes' : 'no',
                $record['word_count'],
                $record['reading_minutes'],
                $record['checked_at'],
                implode(' | ', wp_list_pluck($record['issues'], 'code')),
            ));
        }
        fclose($out);
        exit;
    }

    public function admin_columns($columns) {
        $columns['scfs_integrity'] = __('Publication Readiness', 'sustainable-catalyst-feature-suggestions');
        return $columns;
    }

    public function admin_column_content($column, $post_id) {
        if ($column !== 'scfs_integrity') {
            return;
        }
        $record = $this->stored_record($post_id);
        echo '<span class="scfs-integrity-badge is-' . esc_attr($record['state']) . '">' . esc_html($record['state_label']) . '</span> <strong>' . esc_html((string) $record['score']) . '</strong>';
        if ($record['stale']) {
            echo '<br><span class="description">' . esc_html__('Reverification due', 'sustainable-catalyst-feature-suggestions') . '</span>';
        }
    }

    public function admin_filter() {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen || $screen->post_type !== SCFS_Knowledge_Base_Foundation::ARTICLE_POST_TYPE) {
            return;
        }
        $selected = isset($_GET['scfs_integrity_state']) ? $this->sanitize_state(wp_unslash($_GET['scfs_integrity_state'])) : '';
        echo '<select name="scfs_integrity_state"><option value="">' . esc_html__('All readiness states', 'sustainable-catalyst-feature-suggestions') . '</option>';
        foreach ($this->states() as $key => $label) {
            echo '<option value="' . esc_attr($key) . '" ' . selected($selected, $key, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select>';
    }

    public function apply_admin_filter($query) {
        if (!is_admin() || !$query->is_main_query() || $query->get('post_type') !== SCFS_Knowledge_Base_Foundation::ARTICLE_POST_TYPE || empty($_GET['scfs_integrity_state'])) {
            return;
        }
        $state = $this->sanitize_state(wp_unslash($_GET['scfs_integrity_state']));
        $meta_query = (array) $query->get('meta_query');
        if ($state === 'unscanned') {
            $meta_query[] = array('key' => '_scfs_integrity_state', 'compare' => 'NOT EXISTS');
        } else {
            $meta_query[] = array('key' => '_scfs_integrity_state', 'value' => $state);
        }
        $query->set('meta_query', $meta_query);
    }

    public function row_actions($actions, $post) {
        if (!$post || $post->post_type !== SCFS_Knowledge_Base_Foundation::ARTICLE_POST_TYPE || !current_user_can('edit_post', $post->ID)) {
            return $actions;
        }
        $url = wp_nonce_url(admin_url('admin-post.php?action=scfs_validate_support_article&post_id=' . $post->ID), self::NONCE_ACTION . '_' . $post->ID);
        $actions['scfs_integrity_validate'] = '<a href="' . esc_url($url) . '">' . esc_html__('Validate readiness', 'sustainable-catalyst-feature-suggestions') . '</a>';
        return $actions;
    }

    public function editor_notice() {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen || $screen->post_type !== SCFS_Knowledge_Base_Foundation::ARTICLE_POST_TYPE || !isset($_GET['post'])) {
            return;
        }
        $record = $this->stored_record(absint($_GET['post']));
        if (!in_array($record['state'], array('blocked', 'needs_work'), true)) {
            return;
        }
        $message = sprintf(__('Publication readiness: %1$s (%2$d/100). Review the Publication Readiness panel before publishing or reverifying this article.', 'sustainable-catalyst-feature-suggestions'), $record['state_label'], $record['score']);
        echo '<div class="notice notice-warning"><p>' . esc_html($message) . '</p></div>';
    }

    public function register_rest_routes() {
        $namespace = Sustainable_Catalyst_Feature_Suggestions::REST_NAMESPACE;
        register_rest_route($namespace, '/' . self::REST_BASE . '/schema', array(
            'methods' => 'GET',
            'callback' => function () { return rest_ensure_response($this->schema_record()); },
            'permission_callback' => '__return_true',
        ));
        register_rest_route($namespace, '/' . self::REST_BASE . '/articles/(?P<id>\d+)', array(
            array(
                'methods' => 'GET',
                'callback' => function ($request) { return rest_ensure_response($this->stored_record(absint($request['id']))); },
                'permission_callback' => function () { return current_user_can('edit_posts'); },
            ),
            array(
                'methods' => 'POST',
                'callback' => function ($request) { return rest_ensure_response($this->assess_article(absint($request['id']), true)); },
                'permission_callback' => function () { return current_user_can('edit_posts'); },
            ),
        ));
        register_rest_route($namespace, '/' . self::REST_BASE . '/scan', array(
            'methods' => 'POST',
            'callback' => function ($request) { return rest_ensure_response($this->scan_all($request->get_params())); },
            'permission_callback' => function () { return current_user_can('edit_others_posts'); },
        ));
        register_rest_route($namespace, '/' . self::REST_BASE . '/report', array(
            'methods' => 'GET',
            'callback' => function () { return rest_ensure_response((array) get_option(self::LAST_SCAN_OPTION, array())); },
            'permission_callback' => function () { return current_user_can('edit_others_posts'); },
        ));
    }

    public function schema_record() {
        return array(
            'schema' => 'scfs-support-article-integrity/' . self::SCHEMA_VERSION,
            'version' => self::VERSION,
            'post_type' => SCFS_Knowledge_Base_Foundation::ARTICLE_POST_TYPE,
            'states' => array_keys($this->states()),
            'checks' => array(
                'title',
                'content_length',
                'excerpt_or_summary',
                'product',
                'version',
                'component',
                'article_type',
                'collection',
                'verified_version',
                'heading_hierarchy',
                'required_sections',
                'template_placeholders',
                'internal_links',
                'image_alternative_text',
                'figure_captions',
                'table_headers',
                'relationship_context',
                'freshness',
                'editorial_review_due_date',
            ),
            'automatic_content_changes' => false,
            'automatic_publication' => false,
            'database_migration_required' => false,
        );
    }

    public function cli_command($args, $assoc_args) {
        $mode = isset($args[0]) ? sanitize_key($args[0]) : 'scan';
        if ($mode === 'scan') {
            $summary = $this->scan_all(array('product' => $assoc_args['product'] ?? ''));
            WP_CLI::success(sprintf('Validated %d Support Articles: %d ready, %d review, %d needs work, %d blocked.', $summary['total'], $summary['ready'], $summary['review'], $summary['needs_work'], $summary['blocked']));
            return;
        }
        if ($mode === 'article' && !empty($args[1])) {
            $record = $this->assess_article(absint($args[1]), true);
            if (is_wp_error($record)) {
                WP_CLI::error($record->get_error_message());
            }
            WP_CLI::line(wp_json_encode($record, JSON_PRETTY_PRINT));
            return;
        }
        WP_CLI::error('Usage: wp scfs article-integrity scan [--product=<slug>] or wp scfs article-integrity article <post-id>');
    }
}
