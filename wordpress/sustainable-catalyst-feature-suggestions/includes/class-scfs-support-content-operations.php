<?php
/**
 * Support Content Operations and Product Onboarding.
 *
 * Provides product-by-product onboarding, starter-content generation,
 * documentation/release imports, readiness scoring, lifecycle controls,
 * validation, duplicate protection, and bulk export for the Product Support
 * and Feedback Platform.
 *
 * @package Sustainable_Catalyst_Feature_Suggestions
 */

if (!defined('ABSPATH')) {
    exit;
}

final class SCFS_Support_Content_Operations {
    const VERSION = '4.1.0';
    const SCHEMA_VERSION = '1.0';
    const OPTION_KEY = 'scfs_support_content_operations';
    const PROFILE_OPTION = 'scfs_support_product_profiles';
    const LOG_OPTION = 'scfs_support_content_import_log';
    const VALIDATION_OPTION = 'scfs_support_content_validation';
    const SCHEMA_OPTION = 'scfs_support_content_schema_version';
    const ADMIN_SLUG = 'scfs-support-content-operations';
    const MAX_IMPORT_BYTES = 2097152;

    private static $instance = null;

    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('init', array($this, 'register_meta'), 10);
        add_action('admin_menu', array($this, 'register_admin_page'), 24);
        add_action('add_meta_boxes', array($this, 'add_content_meta_boxes'));
        add_action('admin_enqueue_scripts', array($this, 'admin_assets'));
        add_action('admin_post_scfs_save_product_onboarding', array($this, 'save_product_onboarding'));
        add_action('admin_post_scfs_create_support_starters', array($this, 'create_support_starters_action'));
        add_action('admin_post_scfs_import_support_content', array($this, 'import_support_content_action'));
        add_action('admin_post_scfs_export_support_content', array($this, 'export_support_content_action'));
        add_action('admin_post_scfs_validate_support_content', array($this, 'validate_support_content_action'));
        add_action('save_post', array($this, 'save_content_operations_meta'), 30, 3);
        add_action('save_post', array($this, 'maintain_record_metadata'), 40, 3);
        add_action('rest_api_init', array($this, 'register_rest_routes'));

        if (defined('WP_CLI') && WP_CLI && class_exists('WP_CLI')) {
            WP_CLI::add_command('scfs support-onboard', array($this, 'cli_onboard'));
            WP_CLI::add_command('scfs support-validate', array($this, 'cli_validate'));
        }
    }

    public static function activate() {
        $instance = self::instance();
        $instance->register_meta();
        if (!get_option(self::OPTION_KEY)) {
            add_option(self::OPTION_KEY, $instance->default_settings(), '', false);
        }
        if (!get_option(self::PROFILE_OPTION)) {
            add_option(self::PROFILE_OPTION, array(), '', false);
        }
        update_option(self::SCHEMA_OPTION, self::SCHEMA_VERSION, false);
    }

    public function default_settings() {
        return array(
            'freshness_days' => 180,
            'starter_post_status' => 'draft',
            'hide_empty_support_sections' => '1',
            'require_product_context' => '1',
            'default_import_status' => 'draft',
            'duplicate_policy' => 'skip',
            'max_import_records' => 100,
        );
    }

    public function settings() {
        $saved = function_exists('get_option') ? (array) get_option(self::OPTION_KEY, array()) : array();
        return function_exists('wp_parse_args') ? wp_parse_args($saved, $this->default_settings()) : array_merge($this->default_settings(), $saved);
    }

    public function lifecycle_states() {
        return array(
            'draft' => __('Draft', 'sustainable-catalyst-feature-suggestions'),
            'review' => __('In Review', 'sustainable-catalyst-feature-suggestions'),
            'scheduled' => __('Scheduled', 'sustainable-catalyst-feature-suggestions'),
            'published' => __('Published', 'sustainable-catalyst-feature-suggestions'),
            'superseded' => __('Superseded', 'sustainable-catalyst-feature-suggestions'),
            'archived' => __('Archived', 'sustainable-catalyst-feature-suggestions'),
        );
    }

    public function onboarding_states() {
        return array(
            'not_started' => __('Not Started', 'sustainable-catalyst-feature-suggestions'),
            'mapping' => __('Mapping Product Context', 'sustainable-catalyst-feature-suggestions'),
            'drafting' => __('Drafting Support Content', 'sustainable-catalyst-feature-suggestions'),
            'review' => __('Editorial Review', 'sustainable-catalyst-feature-suggestions'),
            'ready' => __('Support Ready', 'sustainable-catalyst-feature-suggestions'),
            'paused' => __('Paused', 'sustainable-catalyst-feature-suggestions'),
        );
    }

    public function register_meta() {
        $post_types = $this->managed_post_types();
        $definitions = array(
            '_scfs_content_lifecycle' => array('type' => 'string', 'sanitize_callback' => array($this, 'sanitize_lifecycle')),
            '_scfs_source_kind' => array('type' => 'string', 'sanitize_callback' => 'sanitize_key'),
            '_scfs_source_reference' => array('type' => 'string', 'sanitize_callback' => 'sanitize_text_field'),
            '_scfs_source_version' => array('type' => 'string', 'sanitize_callback' => 'sanitize_text_field'),
            '_scfs_source_fingerprint' => array('type' => 'string', 'sanitize_callback' => 'sanitize_text_field'),
            '_scfs_verified_at' => array('type' => 'string', 'sanitize_callback' => array($this, 'sanitize_date')),
            '_scfs_review_due_at' => array('type' => 'string', 'sanitize_callback' => array($this, 'sanitize_date')),
            '_scfs_superseded_by' => array('type' => 'integer', 'sanitize_callback' => 'absint'),
            '_scfs_content_operations_schema' => array('type' => 'string', 'sanitize_callback' => 'sanitize_text_field'),
            '_scfs_starter_key' => array('type' => 'string', 'sanitize_callback' => 'sanitize_key'),
            '_scfs_import_batch_id' => array('type' => 'string', 'sanitize_callback' => 'sanitize_text_field'),
        );
        foreach ($post_types as $post_type) {
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
    }

    public function managed_post_types() {
        return array(
            SCFS_Knowledge_Base_Foundation::ARTICLE_POST_TYPE,
            SCFS_Knowledge_Base_Foundation::ISSUE_POST_TYPE,
            SCFS_Product_Support_Platform::RELEASE_POST_TYPE,
        );
    }

    public function sanitize_lifecycle($value) {
        $value = sanitize_key((string) $value);
        return isset($this->lifecycle_states()[$value]) ? $value : 'draft';
    }

    public function sanitize_date($value) {
        $value = sanitize_text_field((string) $value);
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : '';
    }

    public function sanitize_url($value) {
        $value = trim((string) $value);
        return $value === '' ? '' : esc_url_raw($value);
    }

    public function admin_assets($hook) {
        if (strpos((string) $hook, self::ADMIN_SLUG) === false) {
            return;
        }
        $path = dirname(__DIR__) . '/assets/support-content-operations.css';
        wp_enqueue_style(
            'scfs-support-content-operations',
            plugins_url('assets/support-content-operations.css', dirname(__DIR__) . '/sustainable-catalyst-feature-suggestions.php'),
            array('scfs-admin'),
            is_readable($path) ? (string) filemtime($path) : self::VERSION
        );
    }

    public function add_content_meta_boxes() {
        foreach ($this->managed_post_types() as $post_type) {
            add_meta_box(
                'scfs-support-content-operations',
                __('Support Content Operations', 'sustainable-catalyst-feature-suggestions'),
                array($this, 'render_content_meta_box'),
                $post_type,
                'side',
                'default'
            );
        }
    }

    public function render_content_meta_box($post) {
        wp_nonce_field('scfs_save_content_operations_meta', 'scfs_content_operations_meta_nonce');
        $lifecycle = get_post_meta($post->ID, '_scfs_content_lifecycle', true) ?: ($post->post_status === 'publish' ? 'published' : 'draft');
        $source_kind = get_post_meta($post->ID, '_scfs_source_kind', true);
        $source_reference = get_post_meta($post->ID, '_scfs_source_reference', true);
        $source_version = get_post_meta($post->ID, '_scfs_source_version', true);
        $verified = get_post_meta($post->ID, '_scfs_verified_at', true);
        $review_due = get_post_meta($post->ID, '_scfs_review_due_at', true);
        $superseded_by = absint(get_post_meta($post->ID, '_scfs_superseded_by', true));
        echo '<p><label><strong>' . esc_html__('Lifecycle', 'sustainable-catalyst-feature-suggestions') . '</strong><br><select class="widefat" name="scfs_content_lifecycle">';
        foreach ($this->lifecycle_states() as $key => $label) {
            echo '<option value="' . esc_attr($key) . '" ' . selected($lifecycle, $key, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select></label></p><p><label><strong>' . esc_html__('Source kind', 'sustainable-catalyst-feature-suggestions') . '</strong><br><input class="widefat" name="scfs_source_kind" value="' . esc_attr($source_kind) . '" placeholder="manual, readme, changelog"></label></p><p><label><strong>' . esc_html__('Source reference', 'sustainable-catalyst-feature-suggestions') . '</strong><br><input class="widefat" name="scfs_source_reference" value="' . esc_attr($source_reference) . '" placeholder="README.md or repository path"></label></p><p><label><strong>' . esc_html__('Source version', 'sustainable-catalyst-feature-suggestions') . '</strong><br><input class="widefat" name="scfs_source_version" value="' . esc_attr($source_version) . '" placeholder="v1.0.0"></label></p><p><label><strong>' . esc_html__('Verified on', 'sustainable-catalyst-feature-suggestions') . '</strong><br><input class="widefat" type="date" name="scfs_verified_at" value="' . esc_attr($verified) . '"></label></p><p><label><strong>' . esc_html__('Review due', 'sustainable-catalyst-feature-suggestions') . '</strong><br><input class="widefat" type="date" name="scfs_review_due_at" value="' . esc_attr($review_due) . '"></label></p><p><label><strong>' . esc_html__('Superseded by record ID', 'sustainable-catalyst-feature-suggestions') . '</strong><br><input class="widefat" type="number" min="0" name="scfs_superseded_by" value="' . esc_attr((string) $superseded_by) . '"></label></p><p class="description">' . esc_html__('Publishing and lifecycle remain separate controls. Mark superseded content and link its replacement instead of deleting history.', 'sustainable-catalyst-feature-suggestions') . '</p>';
    }

    public function save_content_operations_meta($post_id, $post, $update) {
        if (!$post || !in_array($post->post_type, $this->managed_post_types(), true) || wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
            return;
        }
        if (empty($_POST['scfs_content_operations_meta_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['scfs_content_operations_meta_nonce'])), 'scfs_save_content_operations_meta') || !current_user_can('edit_post', $post_id)) {
            return;
        }
        $fields = array(
            '_scfs_content_lifecycle' => array('input' => 'scfs_content_lifecycle', 'sanitize' => array($this, 'sanitize_lifecycle')),
            '_scfs_source_kind' => array('input' => 'scfs_source_kind', 'sanitize' => 'sanitize_key'),
            '_scfs_source_reference' => array('input' => 'scfs_source_reference', 'sanitize' => 'sanitize_text_field'),
            '_scfs_source_version' => array('input' => 'scfs_source_version', 'sanitize' => 'sanitize_text_field'),
            '_scfs_verified_at' => array('input' => 'scfs_verified_at', 'sanitize' => array($this, 'sanitize_date')),
            '_scfs_review_due_at' => array('input' => 'scfs_review_due_at', 'sanitize' => array($this, 'sanitize_date')),
            '_scfs_superseded_by' => array('input' => 'scfs_superseded_by', 'sanitize' => 'absint'),
        );
        foreach ($fields as $meta_key => $definition) {
            $raw = isset($_POST[$definition['input']]) ? wp_unslash($_POST[$definition['input']]) : '';
            $value = call_user_func($definition['sanitize'], $raw);
            if ($value === '' || $value === 0) {
                delete_post_meta($post_id, $meta_key);
            } else {
                update_post_meta($post_id, $meta_key, $value);
            }
        }
        update_post_meta($post_id, '_scfs_content_operations_schema', self::SCHEMA_VERSION);
    }

    public function register_admin_page() {
        add_submenu_page(
            'edit.php?post_type=' . Sustainable_Catalyst_Feature_Suggestions::POST_TYPE,
            __('Support Content Operations and Product Onboarding', 'sustainable-catalyst-feature-suggestions'),
            __('Content Operations', 'sustainable-catalyst-feature-suggestions'),
            'edit_posts',
            self::ADMIN_SLUG,
            array($this, 'render_admin_page')
        );
    }

    public function profiles() {
        $profiles = get_option(self::PROFILE_OPTION, array());
        return is_array($profiles) ? $profiles : array();
    }

    public function product_profile($term_id) {
        $term_id = absint($term_id);
        $profiles = $this->profiles();
        $default = array(
            'product_term_id' => $term_id,
            'status' => 'not_started',
            'owner' => '',
            'repository_url' => '',
            'documentation_url' => '',
            'support_url' => '',
            'current_version' => '',
            'components' => array(),
            'known_issues_reviewed' => '',
            'source_notes' => '',
            'updated_at' => '',
        );
        return wp_parse_args(isset($profiles[$term_id]) && is_array($profiles[$term_id]) ? $profiles[$term_id] : array(), $default);
    }

    public function save_profile($term_id, $profile) {
        $term_id = absint($term_id);
        if (!$term_id) {
            return new WP_Error('scfs_invalid_product', __('A valid product is required.', 'sustainable-catalyst-feature-suggestions'));
        }
        $term = get_term($term_id, SCFS_Product_Integration::PRODUCT_TAXONOMY);
        if (!$term || is_wp_error($term)) {
            return new WP_Error('scfs_product_not_found', __('The selected product was not found.', 'sustainable-catalyst-feature-suggestions'));
        }
        $status = sanitize_key($profile['status'] ?? 'not_started');
        if (!isset($this->onboarding_states()[$status])) {
            $status = 'not_started';
        }
        $components = $profile['components'] ?? array();
        if (is_string($components)) {
            $components = preg_split('/\r\n|\r|\n|,/', $components);
        }
        $components = array_values(array_unique(array_filter(array_map('sanitize_text_field', (array) $components))));
        $record = array(
            'product_term_id' => $term_id,
            'status' => $status,
            'owner' => sanitize_text_field($profile['owner'] ?? ''),
            'repository_url' => $this->sanitize_url($profile['repository_url'] ?? ''),
            'documentation_url' => $this->sanitize_url($profile['documentation_url'] ?? ''),
            'support_url' => $this->sanitize_url($profile['support_url'] ?? ''),
            'current_version' => sanitize_text_field($profile['current_version'] ?? ''),
            'components' => $components,
            'known_issues_reviewed' => !empty($profile['known_issues_reviewed']) ? '1' : '',
            'source_notes' => sanitize_textarea_field($profile['source_notes'] ?? ''),
            'updated_at' => gmdate('c'),
        );
        $profiles = $this->profiles();
        $profiles[$term_id] = $record;
        update_option(self::PROFILE_OPTION, $profiles, false);
        $this->ensure_profile_terms($term_id, $record);
        return $record;
    }

    private function ensure_profile_terms($product_id, $profile) {
        if (!empty($profile['current_version'])) {
            $version = $this->ensure_term(
                SCFS_Product_Integration::VERSION_TAXONOMY,
                $profile['current_version'],
                sanitize_title($profile['current_version']),
                array($product_id)
            );
            if (!is_wp_error($version) && $version) {
                update_term_meta($version, 'scfs_status', 'current');
            }
        }
        foreach ((array) $profile['components'] as $component) {
            $this->ensure_term(
                SCFS_Product_Integration::COMPONENT_TAXONOMY,
                $component,
                sanitize_title($component),
                array($product_id)
            );
        }
    }

    private function ensure_term($taxonomy, $name, $slug = '', $product_ids = array()) {
        $slug = $slug !== '' ? sanitize_title($slug) : sanitize_title($name);
        $existing = get_term_by('slug', $slug, $taxonomy);
        if ($existing && !is_wp_error($existing)) {
            $term_id = (int) $existing->term_id;
        } else {
            $created = wp_insert_term($name, $taxonomy, array('slug' => $slug));
            if (is_wp_error($created)) {
                return $created;
            }
            $term_id = (int) $created['term_id'];
        }
        if ($product_ids) {
            $stored = (array) get_term_meta($term_id, 'scfs_product_ids', true);
            $merged = array_values(array_unique(array_filter(array_map('absint', array_merge($stored, $product_ids)))));
            update_term_meta($term_id, 'scfs_product_ids', $merged);
        }
        if (!get_term_meta($term_id, 'scfs_canonical_id', true)) {
            update_term_meta($term_id, 'scfs_canonical_id', $taxonomy . ':' . $slug);
        }
        return $term_id;
    }

    public function readiness_record($product) {
        $term = is_object($product) ? $product : get_term(absint($product), SCFS_Product_Integration::PRODUCT_TAXONOMY);
        if (!$term || is_wp_error($term)) {
            return array(
                'schema' => 'scfs-support-readiness/' . self::SCHEMA_VERSION,
                'version' => self::VERSION,
                'product' => array(),
                'score' => 0,
                'state' => 'not_started',
                'criteria' => array(),
                'blockers' => array('missing_product'),
                'human_review_required' => true,
            );
        }
        $profile = $this->product_profile($term->term_id);
        $counts = $this->product_content_counts($term->slug, true);
        $article_types = $this->product_article_type_slugs($term->slug);
        $required_article_types = array('getting-started', 'how-to', 'troubleshooting', 'technical-reference');
        $criteria = array();
        $score = 0;

        $criteria['product_profile'] = array('points' => 10, 'earned' => $profile['status'] !== 'not_started' ? 10 : 0);
        $criteria['current_version'] = array('points' => 10, 'earned' => $profile['current_version'] !== '' ? 10 : 0);
        $criteria['component_map'] = array('points' => 10, 'earned' => min(10, count($profile['components']) * 2));
        $covered = count(array_intersect($required_article_types, $article_types));
        $criteria['required_articles'] = array('points' => 35, 'earned' => (int) round(($covered / count($required_article_types)) * 35), 'covered' => $covered, 'required' => count($required_article_types));
        $criteria['release_intelligence'] = array('points' => 15, 'earned' => $counts['release_records'] > 0 ? 15 : 0);
        $criteria['known_issue_review'] = array('points' => 10, 'earned' => ($counts['known_issues'] > 0 || !empty($profile['known_issues_reviewed'])) ? 10 : 0);
        $criteria['freshness'] = array('points' => 10, 'earned' => $this->fresh_content_percent($term->slug) >= 80 ? 10 : ($this->fresh_content_percent($term->slug) >= 50 ? 5 : 0));
        foreach ($criteria as $criterion) {
            $score += (int) $criterion['earned'];
        }
        $score = min(100, max(0, $score));
        $blockers = array();
        if ($counts['support_articles'] === 0) {
            $blockers[] = 'no_support_articles';
        }
        if ($counts['release_records'] === 0) {
            $blockers[] = 'no_release_record';
        }
        if ($profile['current_version'] === '') {
            $blockers[] = 'current_version_unset';
        }
        if ($score >= 80 && !$blockers) {
            $state = 'ready';
        } elseif ($score >= 60) {
            $state = 'review';
        } elseif ($score >= 20) {
            $state = 'building';
        } else {
            $state = 'not_started';
        }
        return array(
            'schema' => 'scfs-support-readiness/' . self::SCHEMA_VERSION,
            'version' => self::VERSION,
            'product' => array('id' => (int) $term->term_id, 'slug' => $term->slug, 'name' => $term->name),
            'profile_status' => $profile['status'],
            'score' => $score,
            'state' => $state,
            'criteria' => $criteria,
            'counts' => $counts,
            'fresh_content_percent' => $this->fresh_content_percent($term->slug),
            'blockers' => $blockers,
            'human_review_required' => true,
        );
    }

    public function product_content_counts($product_slug = '', $include_nonpublic = false) {
        $statuses = $include_nonpublic ? array('publish', 'future', 'draft', 'pending', 'private') : array('publish');
        $counts = array(
            'support_articles' => $this->count_posts_for_product(SCFS_Knowledge_Base_Foundation::ARTICLE_POST_TYPE, $product_slug, $statuses),
            'known_issues' => $this->count_posts_for_product(SCFS_Knowledge_Base_Foundation::ISSUE_POST_TYPE, $product_slug, $statuses),
            'release_records' => $this->count_posts_for_product(SCFS_Product_Support_Platform::RELEASE_POST_TYPE, $product_slug, $statuses),
            'public_ideas' => $this->count_public_ideas($product_slug),
            'open_surveys' => $this->count_open_surveys($product_slug),
        );
        return $counts;
    }

    private function count_posts_for_product($post_type, $product_slug, $statuses) {
        $args = array(
            'post_type' => $post_type,
            'post_status' => $statuses,
            'posts_per_page' => 1,
            'fields' => 'ids',
            'no_found_rows' => false,
        );
        if ($product_slug !== '') {
            $args['tax_query'] = array(array(
                'taxonomy' => SCFS_Product_Integration::PRODUCT_TAXONOMY,
                'field' => 'slug',
                'terms' => sanitize_title($product_slug),
            ));
        }
        $query = new WP_Query($args);
        return (int) $query->found_posts;
    }

    private function count_public_ideas($product_slug) {
        $args = array(
            'post_type' => Sustainable_Catalyst_Feature_Suggestions::POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => 1,
            'fields' => 'ids',
            'meta_query' => array(array('key' => '_scfs_public_visibility', 'value' => 'public')),
            'no_found_rows' => false,
        );
        if ($product_slug !== '') {
            $args['tax_query'] = array(array('taxonomy' => SCFS_Product_Integration::PRODUCT_TAXONOMY, 'field' => 'slug', 'terms' => sanitize_title($product_slug)));
        }
        $query = new WP_Query($args);
        return (int) $query->found_posts;
    }

    private function count_open_surveys($product_slug) {
        if (!class_exists('SCFS_Forms_Foundation')) {
            return 0;
        }
        $args = array(
            'post_type' => SCFS_Forms_Foundation::FORM_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => 1,
            'fields' => 'ids',
            'meta_query' => array(array('key' => '_scfs_form_open', 'value' => '1')),
            'no_found_rows' => false,
        );
        $query = new WP_Query($args);
        return (int) $query->found_posts;
    }

    private function product_article_type_slugs($product_slug) {
        $args = array(
            'post_type' => SCFS_Knowledge_Base_Foundation::ARTICLE_POST_TYPE,
            'post_status' => array('publish', 'future', 'draft', 'pending', 'private'),
            'posts_per_page' => -1,
            'fields' => 'ids',
            'no_found_rows' => true,
        );
        if ($product_slug !== '') {
            $args['tax_query'] = array(array('taxonomy' => SCFS_Product_Integration::PRODUCT_TAXONOMY, 'field' => 'slug', 'terms' => sanitize_title($product_slug)));
        }
        $query = new WP_Query($args);
        $slugs = array();
        foreach ($query->posts as $post_id) {
            $terms = get_the_terms($post_id, SCFS_Knowledge_Base_Foundation::ARTICLE_TYPE_TAXONOMY);
            if (!is_wp_error($terms)) {
                foreach ((array) $terms as $term) {
                    $slugs[] = $term->slug;
                }
            }
        }
        return array_values(array_unique($slugs));
    }

    public function fresh_content_percent($product_slug) {
        $settings = $this->settings();
        $cutoff = strtotime('-' . max(1, absint($settings['freshness_days'])) . ' days');
        $args = array(
            'post_type' => $this->managed_post_types(),
            'post_status' => array('publish', 'future', 'draft', 'pending', 'private'),
            'posts_per_page' => -1,
            'fields' => 'ids',
            'no_found_rows' => true,
        );
        if ($product_slug !== '') {
            $args['tax_query'] = array(array('taxonomy' => SCFS_Product_Integration::PRODUCT_TAXONOMY, 'field' => 'slug', 'terms' => sanitize_title($product_slug)));
        }
        $query = new WP_Query($args);
        if (!$query->posts) {
            return 0;
        }
        $fresh = 0;
        foreach ($query->posts as $post_id) {
            $verified = get_post_meta($post_id, '_scfs_verified_at', true);
            $timestamp = $verified ? strtotime($verified . ' 00:00:00 UTC') : get_post_modified_time('U', true, $post_id);
            if ($timestamp && $timestamp >= $cutoff) {
                $fresh++;
            }
        }
        return (int) round(($fresh / count($query->posts)) * 100);
    }

    public function starter_definitions($product_name, $version = '') {
        $version_label = $version !== '' ? $version : __('current release', 'sustainable-catalyst-feature-suggestions');
        return array(
            array(
                'key' => 'getting-started',
                'type' => 'article',
                'article_type' => 'getting-started',
                'title' => sprintf(__('%s: Getting Started', 'sustainable-catalyst-feature-suggestions'), $product_name),
                'content' => "## Overview\n\nExplain what {$product_name} does, who it is for, and the fastest safe way to begin.\n\n## Requirements\n\nList WordPress, browser, service, permission, or data requirements.\n\n## First workflow\n\nProvide one complete task from start to verified result.\n\n## Next steps\n\nLink to task guidance, troubleshooting, and technical reference material.",
            ),
            array(
                'key' => 'installation-configuration',
                'type' => 'article',
                'article_type' => 'how-to',
                'title' => sprintf(__('%s: Installation and Configuration', 'sustainable-catalyst-feature-suggestions'), $product_name),
                'content' => "## Installation\n\nDocument the supported installation or deployment path.\n\n## Configuration\n\nList required settings, environment variables, endpoints, permissions, and defaults.\n\n## Validation\n\nExplain how to verify that the product is operating correctly.\n\n## Upgrade notes\n\nDescribe upgrade and rollback expectations.",
            ),
            array(
                'key' => 'common-workflows',
                'type' => 'article',
                'article_type' => 'how-to',
                'title' => sprintf(__('%s: Common Workflows', 'sustainable-catalyst-feature-suggestions'), $product_name),
                'content' => "## Workflow catalog\n\nDocument the most common user goals.\n\n## Inputs\n\nState required inputs and assumptions.\n\n## Steps\n\nProvide concise, testable steps.\n\n## Expected result\n\nDescribe a successful output and common variations.",
            ),
            array(
                'key' => 'troubleshooting',
                'type' => 'article',
                'article_type' => 'troubleshooting',
                'title' => sprintf(__('%s: Troubleshooting', 'sustainable-catalyst-feature-suggestions'), $product_name),
                'content' => "## Before you begin\n\nRemove secrets and personal data from screenshots, logs, and error fragments.\n\n## Common symptoms\n\nList user-visible symptoms and exact safe error fragments.\n\n## Diagnostic steps\n\nProvide a shortest-path sequence that isolates configuration, connectivity, permissions, data, and version issues.\n\n## Escalation\n\nExplain when to continue to private support.",
            ),
            array(
                'key' => 'technical-reference',
                'type' => 'article',
                'article_type' => 'technical-reference',
                'title' => sprintf(__('%s: Technical Reference', 'sustainable-catalyst-feature-suggestions'), $product_name),
                'content' => "## Supported version\n\nReference {$version_label}.\n\n## Architecture\n\nSummarize public components, data boundaries, and integrations.\n\n## Interfaces\n\nDocument shortcodes, REST routes, schemas, exports, and extension hooks.\n\n## Security and privacy\n\nState what is public, private, retained, or excluded.",
            ),
            array(
                'key' => 'current-release',
                'type' => 'release',
                'title' => sprintf(__('%s %s', 'sustainable-catalyst-feature-suggestions'), $product_name, $version !== '' ? $version : __('Current Release', 'sustainable-catalyst-feature-suggestions')),
                'content' => "## Release overview\n\nSummarize the release and its support implications.\n\n## Highlights\n\nList major changes.\n\n## Known limitations\n\nList verified limitations or explicitly state that review found none.\n\n## Documentation\n\nLink the release to relevant Support Articles and Known Issues.",
            ),
        );
    }

    public function create_starter_content($product_id, $version = '', $status = 'draft') {
        $product = get_term(absint($product_id), SCFS_Product_Integration::PRODUCT_TAXONOMY);
        if (!$product || is_wp_error($product)) {
            return new WP_Error('scfs_product_not_found', __('The selected product was not found.', 'sustainable-catalyst-feature-suggestions'));
        }
        $status = in_array($status, array('draft', 'pending'), true) ? $status : 'draft';
        $created = array();
        $skipped = array();
        foreach ($this->starter_definitions($product->name, $version) as $definition) {
            $existing = $this->find_starter($product->term_id, $definition['key']);
            if ($existing) {
                $skipped[] = array('key' => $definition['key'], 'post_id' => $existing);
                continue;
            }
            $post_type = $definition['type'] === 'release' ? SCFS_Product_Support_Platform::RELEASE_POST_TYPE : SCFS_Knowledge_Base_Foundation::ARTICLE_POST_TYPE;
            $post_id = wp_insert_post(array(
                'post_type' => $post_type,
                'post_status' => $status,
                'post_title' => $definition['title'],
                'post_content' => $definition['content'],
                'post_excerpt' => wp_trim_words(wp_strip_all_tags($definition['content']), 30),
            ), true);
            if (is_wp_error($post_id)) {
                $skipped[] = array('key' => $definition['key'], 'error' => $post_id->get_error_message());
                continue;
            }
            wp_set_object_terms($post_id, array((int) $product->term_id), SCFS_Product_Integration::PRODUCT_TAXONOMY, false);
            if ($version !== '') {
                $version_id = $this->ensure_term(SCFS_Product_Integration::VERSION_TAXONOMY, $version, sanitize_title($version), array((int) $product->term_id));
                if (!is_wp_error($version_id)) {
                    wp_set_object_terms($post_id, array((int) $version_id), SCFS_Product_Integration::VERSION_TAXONOMY, false);
                }
            }
            update_post_meta($post_id, '_scfs_starter_key', $definition['key']);
            update_post_meta($post_id, '_scfs_source_kind', 'starter_template');
            update_post_meta($post_id, '_scfs_source_version', $version);
            update_post_meta($post_id, '_scfs_content_lifecycle', $status === 'pending' ? 'review' : 'draft');
            update_post_meta($post_id, '_scfs_content_operations_schema', self::SCHEMA_VERSION);
            update_post_meta($post_id, '_scfs_source_fingerprint', $this->fingerprint($definition['title'], $definition['content']));
            if ($post_type === SCFS_Knowledge_Base_Foundation::ARTICLE_POST_TYPE) {
                $article_type = get_term_by('slug', $definition['article_type'], SCFS_Knowledge_Base_Foundation::ARTICLE_TYPE_TAXONOMY);
                if ($article_type && !is_wp_error($article_type)) {
                    wp_set_object_terms($post_id, array((int) $article_type->term_id), SCFS_Knowledge_Base_Foundation::ARTICLE_TYPE_TAXONOMY, false);
                }
                update_post_meta($post_id, '_scfs_article_template', str_replace('-', '_', $definition['article_type']));
                update_post_meta($post_id, '_scfs_kb_summary', wp_trim_words(wp_strip_all_tags($definition['content']), 28));
                update_post_meta($post_id, '_scfs_last_verified_version', $version);
            } else {
                update_post_meta($post_id, '_scfs_release_status', 'planned');
                update_post_meta($post_id, '_scfs_release_summary', wp_trim_words(wp_strip_all_tags($definition['content']), 30));
            }
            $created[] = array('key' => $definition['key'], 'post_id' => (int) $post_id, 'post_type' => $post_type);
        }
        $profile = $this->product_profile($product->term_id);
        if ($profile['status'] === 'not_started' || $profile['status'] === 'mapping') {
            $profile['status'] = 'drafting';
            if ($version !== '') {
                $profile['current_version'] = $version;
            }
            $this->save_profile($product->term_id, $profile);
        }
        return array(
            'schema' => 'scfs-support-starter-result/' . self::SCHEMA_VERSION,
            'version' => self::VERSION,
            'product' => array('id' => (int) $product->term_id, 'slug' => $product->slug, 'name' => $product->name),
            'created' => $created,
            'skipped' => $skipped,
            'idempotent' => true,
        );
    }

    private function find_starter($product_id, $key) {
        $query = new WP_Query(array(
            'post_type' => $this->managed_post_types(),
            'post_status' => array('publish', 'future', 'draft', 'pending', 'private', 'trash'),
            'posts_per_page' => 1,
            'fields' => 'ids',
            'meta_query' => array(array('key' => '_scfs_starter_key', 'value' => sanitize_key($key))),
            'tax_query' => array(array('taxonomy' => SCFS_Product_Integration::PRODUCT_TAXONOMY, 'field' => 'term_id', 'terms' => absint($product_id))),
            'no_found_rows' => true,
        ));
        return $query->posts ? (int) $query->posts[0] : 0;
    }

    public function fingerprint($title, $content) {
        $text = strtolower(wp_strip_all_tags((string) $title . ' ' . (string) $content));
        $text = preg_replace('/\s+/', ' ', $text);
        $text = trim($text);
        return hash('sha256', $text);
    }

    public function maintain_record_metadata($post_id, $post, $update) {
        if (!$post || !in_array($post->post_type, $this->managed_post_types(), true) || wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
            return;
        }
        $fingerprint = $this->fingerprint($post->post_title, $post->post_content);
        update_post_meta($post_id, '_scfs_source_fingerprint', $fingerprint);
        update_post_meta($post_id, '_scfs_content_operations_schema', self::SCHEMA_VERSION);
        if (!get_post_meta($post_id, '_scfs_content_lifecycle', true)) {
            $lifecycle = 'draft';
            if ($post->post_status === 'publish') {
                $lifecycle = 'published';
            } elseif ($post->post_status === 'future') {
                $lifecycle = 'scheduled';
            } elseif ($post->post_status === 'pending') {
                $lifecycle = 'review';
            }
            update_post_meta($post_id, '_scfs_content_lifecycle', $lifecycle);
        }
        if ($post->post_status === 'publish' && !get_post_meta($post_id, '_scfs_verified_at', true)) {
            update_post_meta($post_id, '_scfs_verified_at', gmdate('Y-m-d'));
        }
    }

    public function detect_duplicate($title, $content, $exclude_id = 0) {
        $fingerprint = $this->fingerprint($title, $content);
        $query = new WP_Query(array(
            'post_type' => $this->managed_post_types(),
            'post_status' => array('publish', 'future', 'draft', 'pending', 'private'),
            'posts_per_page' => 2,
            'fields' => 'ids',
            'post__not_in' => $exclude_id ? array(absint($exclude_id)) : array(),
            'meta_query' => array(array('key' => '_scfs_source_fingerprint', 'value' => $fingerprint)),
            'no_found_rows' => true,
        ));
        return $query->posts ? (int) $query->posts[0] : 0;
    }

    public function import_records($records, $product_id, $defaults = array()) {
        $product = get_term(absint($product_id), SCFS_Product_Integration::PRODUCT_TAXONOMY);
        if (!$product || is_wp_error($product)) {
            return new WP_Error('scfs_product_not_found', __('The selected product was not found.', 'sustainable-catalyst-feature-suggestions'));
        }
        $settings = $this->settings();
        $limit = min(500, max(1, absint($settings['max_import_records'])));
        $records = array_slice(array_values((array) $records), 0, $limit);
        $batch_id = function_exists('wp_generate_uuid4') ? wp_generate_uuid4() : uniqid('scfs-import-', true);
        $created = array();
        $skipped = array();
        foreach ($records as $index => $record) {
            if (!is_array($record)) {
                $skipped[] = array('index' => $index, 'reason' => 'invalid_record');
                continue;
            }
            $type = sanitize_key($record['type'] ?? $defaults['type'] ?? 'article');
            if (!in_array($type, array('article', 'known_issue', 'release'), true)) {
                $type = 'article';
            }
            $title = sanitize_text_field($record['title'] ?? '');
            $content = wp_kses_post($record['content'] ?? '');
            if ($title === '' || trim(wp_strip_all_tags($content)) === '') {
                $skipped[] = array('index' => $index, 'title' => $title, 'reason' => 'missing_title_or_content');
                continue;
            }
            $duplicate = $this->detect_duplicate($title, $content);
            if ($duplicate && ($settings['duplicate_policy'] ?? 'skip') === 'skip') {
                $skipped[] = array('index' => $index, 'title' => $title, 'reason' => 'duplicate', 'post_id' => $duplicate);
                continue;
            }
            $post_type = $type === 'release' ? SCFS_Product_Support_Platform::RELEASE_POST_TYPE : ($type === 'known_issue' ? SCFS_Knowledge_Base_Foundation::ISSUE_POST_TYPE : SCFS_Knowledge_Base_Foundation::ARTICLE_POST_TYPE);
            $post_status = sanitize_key($record['status'] ?? $defaults['status'] ?? $settings['default_import_status']);
            if (!in_array($post_status, array('draft', 'pending', 'publish'), true)) {
                $post_status = 'draft';
            }
            if ($post_status === 'publish' && !current_user_can('publish_posts')) {
                $post_status = 'pending';
            }
            $post_id = wp_insert_post(array(
                'post_type' => $post_type,
                'post_status' => $post_status,
                'post_title' => $title,
                'post_content' => $content,
                'post_excerpt' => sanitize_textarea_field($record['excerpt'] ?? wp_trim_words(wp_strip_all_tags($content), 35)),
            ), true);
            if (is_wp_error($post_id)) {
                $skipped[] = array('index' => $index, 'title' => $title, 'reason' => $post_id->get_error_message());
                continue;
            }
            wp_set_object_terms($post_id, array((int) $product->term_id), SCFS_Product_Integration::PRODUCT_TAXONOMY, false);
            $this->assign_import_taxonomies($post_id, $product->term_id, $record);
            update_post_meta($post_id, '_scfs_source_kind', sanitize_key($record['source_kind'] ?? $defaults['source_kind'] ?? 'json_import'));
            update_post_meta($post_id, '_scfs_source_reference', sanitize_text_field($record['source_reference'] ?? $defaults['source_reference'] ?? ''));
            update_post_meta($post_id, '_scfs_source_version', sanitize_text_field($record['source_version'] ?? $defaults['source_version'] ?? ''));
            update_post_meta($post_id, '_scfs_source_fingerprint', $this->fingerprint($title, $content));
            update_post_meta($post_id, '_scfs_import_batch_id', $batch_id);
            update_post_meta($post_id, '_scfs_content_lifecycle', $post_status === 'publish' ? 'published' : ($post_status === 'pending' ? 'review' : 'draft'));
            update_post_meta($post_id, '_scfs_content_operations_schema', self::SCHEMA_VERSION);
            if (!empty($record['verified_at'])) {
                update_post_meta($post_id, '_scfs_verified_at', $this->sanitize_date($record['verified_at']));
            }
            if ($type === 'article') {
                $article_type = sanitize_title($record['article_type'] ?? 'how-to');
                $term = get_term_by('slug', $article_type, SCFS_Knowledge_Base_Foundation::ARTICLE_TYPE_TAXONOMY);
                if ($term && !is_wp_error($term)) {
                    wp_set_object_terms($post_id, array((int) $term->term_id), SCFS_Knowledge_Base_Foundation::ARTICLE_TYPE_TAXONOMY, false);
                }
                update_post_meta($post_id, '_scfs_kb_summary', sanitize_textarea_field($record['summary'] ?? wp_trim_words(wp_strip_all_tags($content), 30)));
                update_post_meta($post_id, '_scfs_last_verified_version', sanitize_text_field($record['source_version'] ?? $defaults['source_version'] ?? ''));
            } elseif ($type === 'known_issue') {
                update_post_meta($post_id, '_scfs_issue_status', sanitize_key($record['issue_status'] ?? 'investigating'));
                update_post_meta($post_id, '_scfs_issue_severity', sanitize_key($record['severity'] ?? 'medium'));
                update_post_meta($post_id, '_scfs_issue_symptom', sanitize_textarea_field($record['symptom'] ?? ''));
                update_post_meta($post_id, '_scfs_issue_workaround', wp_kses_post($record['workaround'] ?? ''));
            } else {
                update_post_meta($post_id, '_scfs_release_status', sanitize_key($record['release_status'] ?? 'planned'));
                update_post_meta($post_id, '_scfs_release_date', $this->sanitize_date($record['release_date'] ?? ''));
                update_post_meta($post_id, '_scfs_release_summary', sanitize_textarea_field($record['summary'] ?? wp_trim_words(wp_strip_all_tags($content), 30)));
                update_post_meta($post_id, '_scfs_release_support_note', sanitize_textarea_field($record['support_note'] ?? ''));
            }
            $created[] = array('index' => $index, 'post_id' => (int) $post_id, 'post_type' => $post_type, 'title' => $title);
        }
        $result = array(
            'schema' => 'scfs-support-import-result/' . self::SCHEMA_VERSION,
            'version' => self::VERSION,
            'batch_id' => $batch_id,
            'product' => array('id' => (int) $product->term_id, 'slug' => $product->slug, 'name' => $product->name),
            'created' => $created,
            'skipped' => $skipped,
            'human_review_required' => true,
        );
        $this->append_log($result);
        return $result;
    }

    private function assign_import_taxonomies($post_id, $product_id, $record) {
        $maps = array(
            'product_versions' => SCFS_Product_Integration::VERSION_TAXONOMY,
            'components' => SCFS_Product_Integration::COMPONENT_TAXONOMY,
            'issue_types' => SCFS_Product_Integration::ISSUE_TAXONOMY,
            'releases' => SCFS_Product_Integration::RELEASE_TAXONOMY,
        );
        foreach ($maps as $field => $taxonomy) {
            $values = $record[$field] ?? array();
            if (is_string($values)) {
                $values = preg_split('/\r\n|\r|\n|,/', $values);
            }
            $term_ids = array();
            foreach (array_filter(array_map('sanitize_text_field', (array) $values)) as $value) {
                $term_id = $this->ensure_term($taxonomy, $value, sanitize_title($value), array(absint($product_id)));
                if (!is_wp_error($term_id)) {
                    $term_ids[] = (int) $term_id;
                }
            }
            if ($term_ids) {
                wp_set_object_terms($post_id, $term_ids, $taxonomy, false);
            }
        }
        if (!empty($record['collection'])) {
            $collection = $this->ensure_term(SCFS_Knowledge_Base_Foundation::COLLECTION_TAXONOMY, sanitize_text_field($record['collection']), sanitize_title($record['collection']), array());
            if (!is_wp_error($collection)) {
                wp_set_object_terms($post_id, array((int) $collection), SCFS_Knowledge_Base_Foundation::COLLECTION_TAXONOMY, false);
            }
        }
    }

    public function parse_import_file($path, $filename, $type = 'auto') {
        if (!is_readable($path)) {
            return new WP_Error('scfs_import_unreadable', __('The import file could not be read.', 'sustainable-catalyst-feature-suggestions'));
        }
        $size = filesize($path);
        if ($size === false || $size > self::MAX_IMPORT_BYTES) {
            return new WP_Error('scfs_import_too_large', __('Import files must be 2 MB or smaller.', 'sustainable-catalyst-feature-suggestions'));
        }
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (!in_array($extension, array('json', 'md', 'markdown', 'txt'), true)) {
            return new WP_Error('scfs_import_extension', __('Only JSON, Markdown, and text files are supported.', 'sustainable-catalyst-feature-suggestions'));
        }
        $content = file_get_contents($path);
        if ($content === false) {
            return new WP_Error('scfs_import_read_failed', __('The import file could not be read.', 'sustainable-catalyst-feature-suggestions'));
        }
        if ($extension === 'json') {
            $decoded = json_decode($content, true);
            if (!is_array($decoded)) {
                return new WP_Error('scfs_import_json', __('The JSON import is invalid.', 'sustainable-catalyst-feature-suggestions'));
            }
            $records = isset($decoded['records']) && is_array($decoded['records']) ? $decoded['records'] : ($this->is_list_array($decoded) ? $decoded : array($decoded));
            return array('records' => $records, 'source_kind' => 'json_import');
        }
        $lower_name = strtolower($filename);
        $resolved_type = sanitize_key($type);
        if ($resolved_type === 'auto') {
            $resolved_type = (strpos($lower_name, 'change') !== false || strpos($lower_name, 'release') !== false) ? 'release' : 'article';
        }
        if ($resolved_type === 'release') {
            $records = $this->parse_release_markdown($content, $filename);
        } else {
            $title = $this->markdown_title($content, pathinfo($filename, PATHINFO_FILENAME));
            $records = array(array(
                'type' => 'article',
                'title' => $title,
                'content' => $content,
                'article_type' => strpos($lower_name, 'readme') !== false ? 'getting-started' : 'technical-reference',
                'source_kind' => strpos($lower_name, 'readme') !== false ? 'readme' : 'documentation_file',
                'source_reference' => $filename,
            ));
        }
        return array('records' => $records, 'source_kind' => $resolved_type === 'release' ? 'changelog' : 'documentation_file');
    }

    private function is_list_array($value) {
        if (!is_array($value)) {
            return false;
        }
        $expected = 0;
        foreach (array_keys($value) as $key) {
            if ($key !== $expected) {
                return false;
            }
            $expected++;
        }
        return true;
    }

    private function markdown_title($content, $fallback) {
        if (preg_match('/^#\s+(.+)$/m', (string) $content, $match)) {
            return sanitize_text_field(trim($match[1]));
        }
        $fallback = str_replace(array('-', '_'), ' ', (string) $fallback);
        return sanitize_text_field(ucwords($fallback));
    }

    private function parse_release_markdown($content, $filename) {
        $pattern = '/^##\s+(?:\[)?(?:v|version\s*)?([0-9]+(?:\.[0-9]+){1,3}(?:[-+][A-Za-z0-9.-]+)?)(?:\])?(?:\s*[-–—]\s*(\d{4}-\d{2}-\d{2}))?\s*$/mi';
        if (!preg_match_all($pattern, (string) $content, $matches, PREG_OFFSET_CAPTURE)) {
            return array(array(
                'type' => 'release',
                'title' => $this->markdown_title($content, pathinfo($filename, PATHINFO_FILENAME)),
                'content' => $content,
                'release_status' => 'planned',
                'source_kind' => 'release_notes',
                'source_reference' => $filename,
            ));
        }
        $records = array();
        $count = count($matches[0]);
        for ($i = 0; $i < $count; $i++) {
            $start = $matches[0][$i][1];
            $end = $i + 1 < $count ? $matches[0][$i + 1][1] : strlen($content);
            $section = trim(substr($content, $start, $end - $start));
            $version = sanitize_text_field($matches[1][$i][0]);
            $date = isset($matches[2][$i][0]) ? $this->sanitize_date($matches[2][$i][0]) : '';
            $records[] = array(
                'type' => 'release',
                'title' => 'v' . $version,
                'content' => $section,
                'summary' => wp_trim_words(wp_strip_all_tags($section), 35),
                'release_status' => $i === 0 ? 'current' : 'superseded',
                'release_date' => $date,
                'product_versions' => array('v' . $version),
                'releases' => array('v' . $version),
                'source_version' => 'v' . $version,
                'source_kind' => 'changelog',
                'source_reference' => $filename,
            );
        }
        return array_slice($records, 0, 50);
    }

    private function append_log($record) {
        $log = get_option(self::LOG_OPTION, array());
        if (!is_array($log)) {
            $log = array();
        }
        array_unshift($log, array(
            'at' => gmdate('c'),
            'batch_id' => $record['batch_id'] ?? '',
            'product' => $record['product'] ?? array(),
            'created' => count($record['created'] ?? array()),
            'skipped' => count($record['skipped'] ?? array()),
            'user_id' => get_current_user_id(),
        ));
        update_option(self::LOG_OPTION, array_slice($log, 0, 50), false);
    }

    public function validate_content($product_id = 0) {
        $product_id = absint($product_id);
        $args = array(
            'post_type' => $this->managed_post_types(),
            'post_status' => array('publish', 'future', 'draft', 'pending', 'private'),
            'posts_per_page' => -1,
            'no_found_rows' => true,
        );
        if ($product_id) {
            $args['tax_query'] = array(array('taxonomy' => SCFS_Product_Integration::PRODUCT_TAXONOMY, 'field' => 'term_id', 'terms' => $product_id));
        }
        $query = new WP_Query($args);
        $settings = $this->settings();
        $cutoff = strtotime('-' . max(1, absint($settings['freshness_days'])) . ' days');
        $issues = array();
        $fingerprints = array();
        foreach ($query->posts as $post) {
            $post_id = (int) $post->ID;
            $products = wp_get_object_terms($post_id, SCFS_Product_Integration::PRODUCT_TAXONOMY, array('fields' => 'ids'));
            if (empty($products) && !empty($settings['require_product_context'])) {
                $issues[] = $this->validation_issue($post, 'missing_product_context', 'error');
            }
            $fingerprint = get_post_meta($post_id, '_scfs_source_fingerprint', true) ?: $this->fingerprint($post->post_title, $post->post_content);
            if (isset($fingerprints[$fingerprint])) {
                $issues[] = $this->validation_issue($post, 'duplicate_content', 'warning', array('duplicate_of' => $fingerprints[$fingerprint]));
            } else {
                $fingerprints[$fingerprint] = $post_id;
            }
            $verified = get_post_meta($post_id, '_scfs_verified_at', true);
            $timestamp = $verified ? strtotime($verified . ' 00:00:00 UTC') : get_post_modified_time('U', true, $post_id);
            if ($timestamp && $timestamp < $cutoff) {
                $issues[] = $this->validation_issue($post, 'stale_content', 'warning', array('verified_at' => $verified));
            }
            $lifecycle = get_post_meta($post_id, '_scfs_content_lifecycle', true);
            if ($lifecycle === 'superseded') {
                $superseded_by = absint(get_post_meta($post_id, '_scfs_superseded_by', true));
                if (!$superseded_by || !get_post($superseded_by)) {
                    $issues[] = $this->validation_issue($post, 'invalid_supersession', 'error');
                }
            }
            if ($post->post_status === 'publish' && in_array($lifecycle, array('draft', 'review'), true)) {
                $issues[] = $this->validation_issue($post, 'lifecycle_status_mismatch', 'warning');
            }
        }
        $report = array(
            'schema' => 'scfs-support-content-validation/' . self::SCHEMA_VERSION,
            'version' => self::VERSION,
            'generated_at' => gmdate('c'),
            'product_term_id' => $product_id,
            'records_scanned' => count($query->posts),
            'issues' => $issues,
            'issue_counts' => array(
                'error' => count(array_filter($issues, function ($item) { return $item['severity'] === 'error'; })),
                'warning' => count(array_filter($issues, function ($item) { return $item['severity'] === 'warning'; })),
            ),
            'human_review_required' => true,
        );
        update_option(self::VALIDATION_OPTION, $report, false);
        return $report;
    }

    private function validation_issue($post, $code, $severity, $context = array()) {
        return array(
            'post_id' => (int) $post->ID,
            'post_type' => $post->post_type,
            'title' => $post->post_title,
            'code' => $code,
            'severity' => $severity,
            'edit_url' => get_edit_post_link($post->ID, 'raw'),
            'context' => $context,
        );
    }

    public function export_record($post) {
        $products = wp_get_object_terms($post->ID, SCFS_Product_Integration::PRODUCT_TAXONOMY, array('fields' => 'slugs'));
        $versions = wp_get_object_terms($post->ID, SCFS_Product_Integration::VERSION_TAXONOMY, array('fields' => 'names'));
        $components = wp_get_object_terms($post->ID, SCFS_Product_Integration::COMPONENT_TAXONOMY, array('fields' => 'names'));
        $type = $post->post_type === SCFS_Product_Support_Platform::RELEASE_POST_TYPE ? 'release' : ($post->post_type === SCFS_Knowledge_Base_Foundation::ISSUE_POST_TYPE ? 'known_issue' : 'article');
        return array(
            'id' => (int) $post->ID,
            'type' => $type,
            'status' => $post->post_status,
            'title' => $post->post_title,
            'content' => $post->post_content,
            'excerpt' => $post->post_excerpt,
            'products' => is_wp_error($products) ? array() : array_values($products),
            'product_versions' => is_wp_error($versions) ? array() : array_values($versions),
            'components' => is_wp_error($components) ? array() : array_values($components),
            'lifecycle' => get_post_meta($post->ID, '_scfs_content_lifecycle', true),
            'source_kind' => get_post_meta($post->ID, '_scfs_source_kind', true),
            'source_reference' => get_post_meta($post->ID, '_scfs_source_reference', true),
            'source_version' => get_post_meta($post->ID, '_scfs_source_version', true),
            'verified_at' => get_post_meta($post->ID, '_scfs_verified_at', true),
            'review_due_at' => get_post_meta($post->ID, '_scfs_review_due_at', true),
            'fingerprint' => get_post_meta($post->ID, '_scfs_source_fingerprint', true),
        );
    }

    public function product_export($product_id = 0) {
        $args = array(
            'post_type' => $this->managed_post_types(),
            'post_status' => array('publish', 'future', 'draft', 'pending', 'private'),
            'posts_per_page' => -1,
            'orderby' => 'post_type title',
            'order' => 'ASC',
            'no_found_rows' => true,
        );
        if ($product_id) {
            $args['tax_query'] = array(array('taxonomy' => SCFS_Product_Integration::PRODUCT_TAXONOMY, 'field' => 'term_id', 'terms' => absint($product_id)));
        }
        $query = new WP_Query($args);
        return array(
            'schema' => 'scfs-support-content-export/' . self::SCHEMA_VERSION,
            'version' => self::VERSION,
            'generated_at' => gmdate('c'),
            'product_term_id' => absint($product_id),
            'profile' => $product_id ? $this->product_profile($product_id) : array(),
            'records' => array_values(array_map(array($this, 'export_record'), $query->posts)),
            'private_case_content_included' => false,
        );
    }

    public function save_product_onboarding() {
        if (!current_user_can('edit_posts')) {
            wp_die(__('You do not have permission to manage product onboarding.', 'sustainable-catalyst-feature-suggestions'));
        }
        check_admin_referer('scfs_save_product_onboarding', 'scfs_content_operations_nonce');
        $product_id = absint($_POST['product_term_id'] ?? 0);
        $this->save_profile($product_id, wp_unslash($_POST));
        $settings = $this->settings();
        foreach (array('freshness_days', 'max_import_records') as $numeric) {
            if (isset($_POST[$numeric])) {
                $settings[$numeric] = absint($_POST[$numeric]);
            }
        }
        foreach (array('hide_empty_support_sections', 'require_product_context') as $checkbox) {
            $settings[$checkbox] = !empty($_POST[$checkbox]) ? '1' : '';
        }
        foreach (array('starter_post_status', 'default_import_status', 'duplicate_policy') as $choice) {
            if (isset($_POST[$choice])) {
                $settings[$choice] = sanitize_key($_POST[$choice]);
            }
        }
        update_option(self::OPTION_KEY, $settings, false);
        wp_safe_redirect($this->admin_url(array('product' => $product_id, 'updated' => '1')));
        exit;
    }

    public function create_support_starters_action() {
        if (!current_user_can('edit_posts')) {
            wp_die(__('You do not have permission to create support content.', 'sustainable-catalyst-feature-suggestions'));
        }
        check_admin_referer('scfs_create_support_starters', 'scfs_starter_nonce');
        $product_id = absint($_POST['product_term_id'] ?? 0);
        $version = sanitize_text_field(wp_unslash($_POST['current_version'] ?? ''));
        $status = sanitize_key($_POST['starter_post_status'] ?? 'draft');
        $result = $this->create_starter_content($product_id, $version, $status);
        $created = is_wp_error($result) ? 0 : count($result['created']);
        wp_safe_redirect($this->admin_url(array('product' => $product_id, 'starters' => $created, 'error' => is_wp_error($result) ? rawurlencode($result->get_error_message()) : '')));
        exit;
    }

    public function import_support_content_action() {
        if (!current_user_can('edit_posts')) {
            wp_die(__('You do not have permission to import support content.', 'sustainable-catalyst-feature-suggestions'));
        }
        check_admin_referer('scfs_import_support_content', 'scfs_import_nonce');
        $product_id = absint($_POST['product_term_id'] ?? 0);
        if (empty($_FILES['source_file']['tmp_name'])) {
            wp_safe_redirect($this->admin_url(array('product' => $product_id, 'error' => rawurlencode(__('Choose a file to import.', 'sustainable-catalyst-feature-suggestions')))));
            exit;
        }
        $file = $_FILES['source_file'];
        if (!empty($file['error'])) {
            wp_safe_redirect($this->admin_url(array('product' => $product_id, 'error' => rawurlencode(__('The upload failed.', 'sustainable-catalyst-feature-suggestions')))));
            exit;
        }
        $parsed = $this->parse_import_file($file['tmp_name'], sanitize_file_name($file['name']), sanitize_key($_POST['import_type'] ?? 'auto'));
        if (is_wp_error($parsed)) {
            wp_safe_redirect($this->admin_url(array('product' => $product_id, 'error' => rawurlencode($parsed->get_error_message()))));
            exit;
        }
        $result = $this->import_records($parsed['records'], $product_id, array(
            'status' => sanitize_key($_POST['import_status'] ?? 'draft'),
            'source_kind' => $parsed['source_kind'],
            'source_reference' => sanitize_file_name($file['name']),
            'source_version' => sanitize_text_field(wp_unslash($_POST['source_version'] ?? '')),
        ));
        $created = is_wp_error($result) ? 0 : count($result['created']);
        $skipped = is_wp_error($result) ? 0 : count($result['skipped']);
        wp_safe_redirect($this->admin_url(array('product' => $product_id, 'imported' => $created, 'skipped' => $skipped, 'error' => is_wp_error($result) ? rawurlencode($result->get_error_message()) : '')));
        exit;
    }

    public function export_support_content_action() {
        if (!current_user_can('edit_posts')) {
            wp_die(__('You do not have permission to export support content.', 'sustainable-catalyst-feature-suggestions'));
        }
        check_admin_referer('scfs_export_support_content');
        $product_id = absint($_GET['product_term_id'] ?? 0);
        $payload = $this->product_export($product_id);
        $filename = 'scfs-support-content-' . ($product_id ?: 'all') . '-' . gmdate('Ymd-His') . '.json';
        nocache_headers();
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo wp_json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public function validate_support_content_action() {
        if (!current_user_can('edit_posts')) {
            wp_die(__('You do not have permission to validate support content.', 'sustainable-catalyst-feature-suggestions'));
        }
        check_admin_referer('scfs_validate_support_content');
        $product_id = absint($_GET['product_term_id'] ?? 0);
        $report = $this->validate_content($product_id);
        wp_safe_redirect($this->admin_url(array('product' => $product_id, 'validated' => count($report['issues']))));
        exit;
    }

    private function admin_url($args = array()) {
        return add_query_arg(array_merge(array(
            'post_type' => Sustainable_Catalyst_Feature_Suggestions::POST_TYPE,
            'page' => self::ADMIN_SLUG,
        ), array_filter($args, function ($value) { return $value !== ''; })), admin_url('edit.php'));
    }

    public function render_admin_page() {
        if (!current_user_can('edit_posts')) {
            wp_die(__('You do not have permission to manage support content operations.', 'sustainable-catalyst-feature-suggestions'));
        }
        $products = get_terms(array('taxonomy' => SCFS_Product_Integration::PRODUCT_TAXONOMY, 'hide_empty' => false, 'orderby' => 'name'));
        if (is_wp_error($products)) {
            $products = array();
        }
        $selected_id = absint($_GET['product'] ?? 0);
        if (!$selected_id && $products) {
            $selected_id = (int) $products[0]->term_id;
        }
        $selected = $selected_id ? get_term($selected_id, SCFS_Product_Integration::PRODUCT_TAXONOMY) : null;
        $profile = $selected_id ? $this->product_profile($selected_id) : array();
        $readiness = $selected_id ? $this->readiness_record($selected_id) : array();
        $settings = $this->settings();
        $validation = get_option(self::VALIDATION_OPTION, array());
        $log = get_option(self::LOG_OPTION, array());
        echo '<div class="wrap scfs-content-operations"><h1>' . esc_html__('Support Content Operations and Product Onboarding', 'sustainable-catalyst-feature-suggestions') . '</h1><p>' . esc_html__('Onboard each Sustainable Catalyst product, create a reviewable starter set, import existing documentation and release history, and measure whether public support is ready.', 'sustainable-catalyst-feature-suggestions') . '</p>';
        $this->render_notices();
        if (!$products) {
            echo '<div class="notice notice-warning"><p>' . esc_html__('Create or seed Product taxonomy terms before onboarding support content.', 'sustainable-catalyst-feature-suggestions') . '</p></div></div>';
            return;
        }
        echo '<form class="scfs-content-product-picker" method="get"><input type="hidden" name="post_type" value="' . esc_attr(Sustainable_Catalyst_Feature_Suggestions::POST_TYPE) . '"><input type="hidden" name="page" value="' . esc_attr(self::ADMIN_SLUG) . '"><label><span>' . esc_html__('Product', 'sustainable-catalyst-feature-suggestions') . '</span><select name="product">';
        foreach ($products as $product) {
            echo '<option value="' . esc_attr((string) $product->term_id) . '" ' . selected($selected_id, $product->term_id, false) . '>' . esc_html($product->name) . '</option>';
        }
        echo '</select></label><button class="button">' . esc_html__('Open product', 'sustainable-catalyst-feature-suggestions') . '</button></form>';
        $this->render_product_readiness_table($products, $selected_id);
        if ($selected && !is_wp_error($selected)) {
            echo '<div class="scfs-content-ops-grid"><section class="scfs-content-ops-card scfs-content-readiness"><p class="scfs-content-kicker">' . esc_html__('Support readiness', 'sustainable-catalyst-feature-suggestions') . '</p><h2>' . esc_html($selected->name) . '</h2><div class="scfs-readiness-score"><strong>' . esc_html((string) ($readiness['score'] ?? 0)) . '</strong><span>/ 100</span></div><p><b>' . esc_html(ucwords(str_replace('_', ' ', $readiness['state'] ?? 'not_started'))) . '</b></p><div class="scfs-readiness-bar"><span style="width:' . esc_attr((string) ($readiness['score'] ?? 0)) . '%"></span></div>';
            if (!empty($readiness['blockers'])) {
                echo '<p class="description">' . esc_html__('Blockers:', 'sustainable-catalyst-feature-suggestions') . ' ' . esc_html(implode(', ', array_map(function ($value) { return str_replace('_', ' ', $value); }, $readiness['blockers']))) . '</p>';
            }
            echo '</section><section class="scfs-content-ops-card"><p class="scfs-content-kicker">' . esc_html__('Content inventory', 'sustainable-catalyst-feature-suggestions') . '</p><h2>' . esc_html__('Current records', 'sustainable-catalyst-feature-suggestions') . '</h2><div class="scfs-content-counts">';
            foreach (($readiness['counts'] ?? array()) as $key => $value) {
                echo '<div><strong>' . esc_html((string) $value) . '</strong><span>' . esc_html(ucwords(str_replace('_', ' ', $key))) . '</span></div>';
            }
            echo '</div><p>' . esc_html(sprintf(__('Fresh content: %d%%', 'sustainable-catalyst-feature-suggestions'), (int) ($readiness['fresh_content_percent'] ?? 0))) . '</p></section></div>';
            $this->render_profile_form($selected, $profile, $settings);
            $this->render_starter_form($selected, $profile, $settings);
            $this->render_import_form($selected, $settings);
            $this->render_validation_panel($selected, $validation);
            $this->render_log($log);
        }
        echo '</div>';
    }

    private function render_notices() {
        if (!empty($_GET['updated'])) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Product onboarding settings saved.', 'sustainable-catalyst-feature-suggestions') . '</p></div>';
        }
        if (isset($_GET['starters'])) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html(sprintf(__('%d starter records created. Existing starter records were preserved.', 'sustainable-catalyst-feature-suggestions'), absint($_GET['starters']))) . '</p></div>';
        }
        if (isset($_GET['imported'])) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html(sprintf(__('%d records imported; %d skipped.', 'sustainable-catalyst-feature-suggestions'), absint($_GET['imported']), absint($_GET['skipped'] ?? 0))) . '</p></div>';
        }
        if (isset($_GET['validated'])) {
            echo '<div class="notice notice-info is-dismissible"><p>' . esc_html(sprintf(__('Validation completed with %d issue(s) requiring review.', 'sustainable-catalyst-feature-suggestions'), absint($_GET['validated']))) . '</p></div>';
        }
        if (!empty($_GET['error'])) {
            echo '<div class="notice notice-error"><p>' . esc_html(sanitize_text_field(wp_unslash($_GET['error']))) . '</p></div>';
        }
    }

    private function render_product_readiness_table($products, $selected_id) {
        echo '<section class="scfs-content-ops-section"><h2>' . esc_html__('Product support-readiness dashboard', 'sustainable-catalyst-feature-suggestions') . '</h2><table class="widefat striped"><thead><tr><th>' . esc_html__('Product', 'sustainable-catalyst-feature-suggestions') . '</th><th>' . esc_html__('Onboarding', 'sustainable-catalyst-feature-suggestions') . '</th><th>' . esc_html__('Score', 'sustainable-catalyst-feature-suggestions') . '</th><th>' . esc_html__('State', 'sustainable-catalyst-feature-suggestions') . '</th><th>' . esc_html__('Blockers', 'sustainable-catalyst-feature-suggestions') . '</th></tr></thead><tbody>';
        foreach ($products as $product) {
            $readiness = $this->readiness_record($product);
            $profile = $this->product_profile($product->term_id);
            $url = $this->admin_url(array('product' => $product->term_id));
            echo '<tr' . ((int) $selected_id === (int) $product->term_id ? ' class="is-selected"' : '') . '><td><a href="' . esc_url($url) . '"><strong>' . esc_html($product->name) . '</strong></a></td><td>' . esc_html($this->onboarding_states()[$profile['status']] ?? $profile['status']) . '</td><td>' . esc_html((string) $readiness['score']) . '</td><td>' . esc_html(ucwords(str_replace('_', ' ', $readiness['state']))) . '</td><td>' . esc_html($readiness['blockers'] ? implode(', ', array_map(function ($value) { return str_replace('_', ' ', $value); }, $readiness['blockers'])) : '—') . '</td></tr>';
        }
        echo '</tbody></table></section>';
    }

    private function render_profile_form($product, $profile, $settings) {
        echo '<section class="scfs-content-ops-section"><h2>' . esc_html__('1. Product onboarding profile', 'sustainable-catalyst-feature-suggestions') . '</h2><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="scfs_save_product_onboarding"><input type="hidden" name="product_term_id" value="' . esc_attr((string) $product->term_id) . '">';
        wp_nonce_field('scfs_save_product_onboarding', 'scfs_content_operations_nonce');
        echo '<table class="form-table"><tr><th>' . esc_html__('Onboarding state', 'sustainable-catalyst-feature-suggestions') . '</th><td><select name="status">';
        foreach ($this->onboarding_states() as $key => $label) {
            echo '<option value="' . esc_attr($key) . '" ' . selected($profile['status'], $key, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select></td></tr><tr><th>' . esc_html__('Owner', 'sustainable-catalyst-feature-suggestions') . '</th><td><input class="regular-text" name="owner" value="' . esc_attr($profile['owner']) . '"></td></tr><tr><th>' . esc_html__('Current version', 'sustainable-catalyst-feature-suggestions') . '</th><td><input class="regular-text" name="current_version" value="' . esc_attr($profile['current_version']) . '" placeholder="v1.0.0"></td></tr><tr><th>' . esc_html__('Repository URL', 'sustainable-catalyst-feature-suggestions') . '</th><td><input class="regular-text" type="url" name="repository_url" value="' . esc_attr($profile['repository_url']) . '"></td></tr><tr><th>' . esc_html__('Documentation URL', 'sustainable-catalyst-feature-suggestions') . '</th><td><input class="regular-text" type="url" name="documentation_url" value="' . esc_attr($profile['documentation_url']) . '"></td></tr><tr><th>' . esc_html__('Product support URL', 'sustainable-catalyst-feature-suggestions') . '</th><td><input class="regular-text" type="url" name="support_url" value="' . esc_attr($profile['support_url']) . '"></td></tr><tr><th>' . esc_html__('Components', 'sustainable-catalyst-feature-suggestions') . '</th><td><textarea class="large-text" rows="5" name="components" placeholder="Public interface&#10;REST API&#10;Admin settings">' . esc_textarea(implode("\n", (array) $profile['components'])) . '</textarea><p class="description">' . esc_html__('One component per line. Missing component terms are created and linked to this product.', 'sustainable-catalyst-feature-suggestions') . '</p></td></tr><tr><th>' . esc_html__('Known-issue review', 'sustainable-catalyst-feature-suggestions') . '</th><td><label><input type="checkbox" name="known_issues_reviewed" value="1" ' . checked($profile['known_issues_reviewed'], '1', false) . '> ' . esc_html__('The product was reviewed and currently has no additional known issues to publish.', 'sustainable-catalyst-feature-suggestions') . '</label></td></tr><tr><th>' . esc_html__('Source notes', 'sustainable-catalyst-feature-suggestions') . '</th><td><textarea class="large-text" rows="4" name="source_notes">' . esc_textarea($profile['source_notes']) . '</textarea></td></tr>';
        echo '<tr><th>' . esc_html__('Operations defaults', 'sustainable-catalyst-feature-suggestions') . '</th><td><label>' . esc_html__('Freshness window', 'sustainable-catalyst-feature-suggestions') . ' <input type="number" min="30" max="1095" name="freshness_days" value="' . esc_attr((string) $settings['freshness_days']) . '"> ' . esc_html__('days', 'sustainable-catalyst-feature-suggestions') . '</label><br><label><input type="checkbox" name="hide_empty_support_sections" value="1" ' . checked($settings['hide_empty_support_sections'], '1', false) . '> ' . esc_html__('Hide empty Knowledge Base, Known Issues, Releases, Public Ideas, and Surveys navigation until content exists', 'sustainable-catalyst-feature-suggestions') . '</label><br><label><input type="checkbox" name="require_product_context" value="1" ' . checked($settings['require_product_context'], '1', false) . '> ' . esc_html__('Require product context during validation', 'sustainable-catalyst-feature-suggestions') . '</label><br><label>' . esc_html__('Duplicate import policy', 'sustainable-catalyst-feature-suggestions') . ' <select name="duplicate_policy"><option value="skip" ' . selected($settings['duplicate_policy'], 'skip', false) . '>' . esc_html__('Skip exact duplicates', 'sustainable-catalyst-feature-suggestions') . '</option><option value="create" ' . selected($settings['duplicate_policy'], 'create', false) . '>' . esc_html__('Create and flag for review', 'sustainable-catalyst-feature-suggestions') . '</option></select></label><br><label>' . esc_html__('Maximum records per import', 'sustainable-catalyst-feature-suggestions') . ' <input type="number" min="1" max="500" name="max_import_records" value="' . esc_attr((string) $settings['max_import_records']) . '"></label></td></tr></table><p><button class="button button-primary">' . esc_html__('Save onboarding profile', 'sustainable-catalyst-feature-suggestions') . '</button></p></form></section>';
    }

    private function render_starter_form($product, $profile, $settings) {
        echo '<section class="scfs-content-ops-section"><h2>' . esc_html__('2. Create the starter support set', 'sustainable-catalyst-feature-suggestions') . '</h2><p>' . esc_html__('Create idempotent drafts for Getting Started, installation/configuration, common workflows, troubleshooting, technical reference, and the current release. Existing starter records are never overwritten.', 'sustainable-catalyst-feature-suggestions') . '</p><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="scfs_create_support_starters"><input type="hidden" name="product_term_id" value="' . esc_attr((string) $product->term_id) . '">';
        wp_nonce_field('scfs_create_support_starters', 'scfs_starter_nonce');
        echo '<label>' . esc_html__('Version', 'sustainable-catalyst-feature-suggestions') . ' <input name="current_version" value="' . esc_attr($profile['current_version']) . '" placeholder="v1.0.0"></label> <label>' . esc_html__('Status', 'sustainable-catalyst-feature-suggestions') . ' <select name="starter_post_status"><option value="draft" ' . selected($settings['starter_post_status'], 'draft', false) . '>' . esc_html__('Draft', 'sustainable-catalyst-feature-suggestions') . '</option><option value="pending" ' . selected($settings['starter_post_status'], 'pending', false) . '>' . esc_html__('Pending review', 'sustainable-catalyst-feature-suggestions') . '</option></select></label> <button class="button button-primary">' . esc_html__('Create missing starter records', 'sustainable-catalyst-feature-suggestions') . '</button></form></section>';
    }

    private function render_import_form($product, $settings) {
        echo '<section class="scfs-content-ops-section"><h2>' . esc_html__('3. Import existing documentation and release history', 'sustainable-catalyst-feature-suggestions') . '</h2><p>' . esc_html__('Upload a README, Markdown/text documentation file, CHANGELOG, release-notes file, or structured JSON export. Imported records remain drafts or pending review unless an authorized editor explicitly chooses publish.', 'sustainable-catalyst-feature-suggestions') . '</p><form method="post" enctype="multipart/form-data" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="scfs_import_support_content"><input type="hidden" name="product_term_id" value="' . esc_attr((string) $product->term_id) . '">';
        wp_nonce_field('scfs_import_support_content', 'scfs_import_nonce');
        echo '<p><input type="file" name="source_file" accept=".json,.md,.markdown,.txt" required></p><p><label>' . esc_html__('Interpret as', 'sustainable-catalyst-feature-suggestions') . ' <select name="import_type"><option value="auto">' . esc_html__('Auto-detect', 'sustainable-catalyst-feature-suggestions') . '</option><option value="article">' . esc_html__('Support Article', 'sustainable-catalyst-feature-suggestions') . '</option><option value="release">' . esc_html__('Release history', 'sustainable-catalyst-feature-suggestions') . '</option></select></label> <label>' . esc_html__('Create as', 'sustainable-catalyst-feature-suggestions') . ' <select name="import_status"><option value="draft" ' . selected($settings['default_import_status'], 'draft', false) . '>' . esc_html__('Draft', 'sustainable-catalyst-feature-suggestions') . '</option><option value="pending" ' . selected($settings['default_import_status'], 'pending', false) . '>' . esc_html__('Pending review', 'sustainable-catalyst-feature-suggestions') . '</option><option value="publish" ' . selected($settings['default_import_status'], 'publish', false) . '>' . esc_html__('Publish after import', 'sustainable-catalyst-feature-suggestions') . '</option></select></label> <label>' . esc_html__('Source version', 'sustainable-catalyst-feature-suggestions') . ' <input name="source_version" placeholder="v1.0.0"></label></p><p><button class="button button-primary">' . esc_html__('Import support content', 'sustainable-catalyst-feature-suggestions') . '</button></p></form></section>';
    }

    private function render_validation_panel($product, $validation) {
        $validate_url = wp_nonce_url(admin_url('admin-post.php?action=scfs_validate_support_content&product_term_id=' . absint($product->term_id)), 'scfs_validate_support_content');
        $export_url = wp_nonce_url(admin_url('admin-post.php?action=scfs_export_support_content&product_term_id=' . absint($product->term_id)), 'scfs_export_support_content');
        echo '<section class="scfs-content-ops-section"><h2>' . esc_html__('4. Validate and export', 'sustainable-catalyst-feature-suggestions') . '</h2><p><a class="button button-primary" href="' . esc_url($validate_url) . '">' . esc_html__('Run content validation', 'sustainable-catalyst-feature-suggestions') . '</a> <a class="button" href="' . esc_url($export_url) . '">' . esc_html__('Export product support JSON', 'sustainable-catalyst-feature-suggestions') . '</a></p>';
        if (is_array($validation) && !empty($validation['generated_at'])) {
            echo '<p class="description">' . esc_html(sprintf(__('Last validation: %s — %d records, %d errors, %d warnings.', 'sustainable-catalyst-feature-suggestions'), $validation['generated_at'], (int) $validation['records_scanned'], (int) ($validation['issue_counts']['error'] ?? 0), (int) ($validation['issue_counts']['warning'] ?? 0))) . '</p>';
            if (!empty($validation['issues'])) {
                echo '<table class="widefat striped"><thead><tr><th>' . esc_html__('Record', 'sustainable-catalyst-feature-suggestions') . '</th><th>' . esc_html__('Issue', 'sustainable-catalyst-feature-suggestions') . '</th><th>' . esc_html__('Severity', 'sustainable-catalyst-feature-suggestions') . '</th></tr></thead><tbody>';
                foreach (array_slice($validation['issues'], 0, 25) as $issue) {
                    echo '<tr><td><a href="' . esc_url($issue['edit_url']) . '">' . esc_html($issue['title']) . '</a></td><td>' . esc_html(str_replace('_', ' ', $issue['code'])) . '</td><td>' . esc_html($issue['severity']) . '</td></tr>';
                }
                echo '</tbody></table>';
            }
        }
        echo '</section>';
    }

    private function render_log($log) {
        if (!is_array($log) || !$log) {
            return;
        }
        echo '<section class="scfs-content-ops-section"><h2>' . esc_html__('Recent imports', 'sustainable-catalyst-feature-suggestions') . '</h2><table class="widefat striped"><thead><tr><th>' . esc_html__('Time', 'sustainable-catalyst-feature-suggestions') . '</th><th>' . esc_html__('Product', 'sustainable-catalyst-feature-suggestions') . '</th><th>' . esc_html__('Created', 'sustainable-catalyst-feature-suggestions') . '</th><th>' . esc_html__('Skipped', 'sustainable-catalyst-feature-suggestions') . '</th></tr></thead><tbody>';
        foreach (array_slice($log, 0, 10) as $entry) {
            echo '<tr><td>' . esc_html($entry['at'] ?? '') . '</td><td>' . esc_html($entry['product']['name'] ?? '') . '</td><td>' . esc_html((string) ($entry['created'] ?? 0)) . '</td><td>' . esc_html((string) ($entry['skipped'] ?? 0)) . '</td></tr>';
        }
        echo '</tbody></table></section>';
    }

    public function schema_record() {
        return array(
            'schema' => 'scfs-support-content-operations/' . self::SCHEMA_VERSION,
            'version' => self::VERSION,
            'capabilities' => array(
                'product_onboarding_profiles',
                'support_readiness_scoring',
                'idempotent_starter_content',
                'readme_and_documentation_import',
                'changelog_and_release_notes_import',
                'json_import_export',
                'content_lifecycle_metadata',
                'freshness_validation',
                'duplicate_fingerprint_detection',
                'product_version_component_mapping',
                'empty_support_section_suppression',
            ),
            'managed_post_types' => $this->managed_post_types(),
            'import_formats' => array('json', 'md', 'markdown', 'txt'),
            'lifecycle_states' => array_keys($this->lifecycle_states()),
            'onboarding_states' => array_keys($this->onboarding_states()),
            'governance' => array(
                'imports_require_human_review' => true,
                'starter_content_never_overwrites_existing_records' => true,
                'private_case_content_imported' => false,
                'automatic_publication' => false,
            ),
        );
    }

    public function register_rest_routes() {
        $namespace = Sustainable_Catalyst_Feature_Suggestions::REST_NAMESPACE;
        register_rest_route($namespace, '/content-operations/schema', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => function () { return rest_ensure_response($this->schema_record()); },
            'permission_callback' => '__return_true',
        ));
        register_rest_route($namespace, '/content-operations/readiness', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'rest_readiness'),
            'permission_callback' => array($this, 'rest_admin_permission'),
            'args' => array('product' => array('sanitize_callback' => 'absint')),
        ));
        register_rest_route($namespace, '/content-operations/validate', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'rest_validate'),
            'permission_callback' => array($this, 'rest_admin_permission'),
            'args' => array('product' => array('sanitize_callback' => 'absint')),
        ));
        register_rest_route($namespace, '/content-operations/import', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'rest_import'),
            'permission_callback' => array($this, 'rest_admin_permission'),
        ));
        register_rest_route($namespace, '/content-operations/export', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'rest_export'),
            'permission_callback' => array($this, 'rest_admin_permission'),
            'args' => array('product' => array('sanitize_callback' => 'absint')),
        ));
    }

    public function rest_admin_permission() {
        return current_user_can('edit_posts');
    }

    public function rest_readiness($request) {
        return rest_ensure_response($this->readiness_record(absint($request->get_param('product'))));
    }

    public function rest_validate($request) {
        return rest_ensure_response($this->validate_content(absint($request->get_param('product'))));
    }

    public function rest_import($request) {
        $product_id = absint($request->get_param('product'));
        $records = $request->get_param('records');
        if (!is_array($records)) {
            return new WP_Error('scfs_import_records_required', __('A records array is required.', 'sustainable-catalyst-feature-suggestions'), array('status' => 400));
        }
        $result = $this->import_records($records, $product_id, array('status' => 'draft', 'source_kind' => 'rest_import'));
        return is_wp_error($result) ? $result : rest_ensure_response($result);
    }

    public function rest_export($request) {
        return rest_ensure_response($this->product_export(absint($request->get_param('product'))));
    }

    public function cli_onboard($args, $assoc_args) {
        $slug = sanitize_title($assoc_args['product'] ?? '');
        $term = get_term_by('slug', $slug, SCFS_Product_Integration::PRODUCT_TAXONOMY);
        if (!$term || is_wp_error($term)) {
            WP_CLI::error('Product not found: ' . $slug);
        }
        $version = sanitize_text_field($assoc_args['version'] ?? '');
        $profile = $this->product_profile($term->term_id);
        $profile['status'] = 'drafting';
        $profile['current_version'] = $version;
        $this->save_profile($term->term_id, $profile);
        $result = $this->create_starter_content($term->term_id, $version, 'draft');
        if (is_wp_error($result)) {
            WP_CLI::error($result->get_error_message());
        }
        WP_CLI::success(sprintf('Onboarded %s; created %d starter records; skipped %d existing records.', $term->name, count($result['created']), count($result['skipped'])));
    }

    public function cli_validate($args, $assoc_args) {
        $product_id = 0;
        if (!empty($assoc_args['product'])) {
            $term = get_term_by('slug', sanitize_title($assoc_args['product']), SCFS_Product_Integration::PRODUCT_TAXONOMY);
            if (!$term || is_wp_error($term)) {
                WP_CLI::error('Product not found.');
            }
            $product_id = (int) $term->term_id;
        }
        $report = $this->validate_content($product_id);
        WP_CLI::success(sprintf('Validated %d records: %d errors, %d warnings.', $report['records_scanned'], $report['issue_counts']['error'], $report['issue_counts']['warning']));
    }
}
