<?php
/**
 * Unified Product Support and Feedback Platform.
 *
 * Brings guided resolution, documentation, known issues, feature suggestions,
 * public voting, surveys, release intelligence, and private support handoff
 * into one product-aware public support center.
 *
 * @package Sustainable_Catalyst_Feature_Suggestions
 */

if (!defined('ABSPATH')) {
    exit;
}

final class SCFS_Product_Support_Platform {
    const VERSION = '4.0.0';
    const SCHEMA_VERSION = '1.0';
    const RELEASE_POST_TYPE = 'sc_release_record';
    const SHORTCODE = 'scfs_product_support_center';
    const LEGACY_SHORTCODE = 'scfs_support_center';
    const OPTION_KEY = 'scfs_product_support_platform';
    const SCHEMA_OPTION = 'scfs_product_support_schema_version';
    const NONCE_ACTION = 'scfs_save_release_intelligence';
    const NONCE_NAME = 'scfs_release_intelligence_nonce';

    private static $instance = null;

    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('init', array($this, 'register_release_post_type'), 6);
        add_action('init', array($this, 'register_release_meta'), 9);
        add_action('init', array($this, 'register_shortcodes'), 31);
        add_action('wp_enqueue_scripts', array($this, 'register_assets'));
        add_action('admin_enqueue_scripts', array($this, 'admin_assets'));
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post_' . self::RELEASE_POST_TYPE, array($this, 'save_release'), 20, 2);
        add_action('admin_menu', array($this, 'register_admin_page'), 23);
        add_action('admin_post_scfs_save_product_support_settings', array($this, 'save_settings'));
        add_action('rest_api_init', array($this, 'register_rest_routes'));
        add_filter('template_include', array($this, 'template_include'));
        add_filter('the_content', array($this, 'decorate_release_content'), 22);
        add_filter('manage_' . self::RELEASE_POST_TYPE . '_posts_columns', array($this, 'release_columns'));
        add_action('manage_' . self::RELEASE_POST_TYPE . '_posts_custom_column', array($this, 'release_column_content'), 10, 2);
    }

    public static function activate() {
        $instance = self::instance();
        $instance->register_release_post_type();
        $instance->register_release_meta();
        update_option(self::SCHEMA_OPTION, self::SCHEMA_VERSION, false);
        if (!get_option(self::OPTION_KEY)) {
            add_option(self::OPTION_KEY, $instance->default_settings(), '', false);
        }
    }

    private function default_settings() {
        return array(
            'support_center_title' => 'Sustainable Catalyst Product Support',
            'support_center_intro' => 'Find documentation, check known issues, review releases, support public ideas, participate in surveys, suggest improvements, or continue to private support when public guidance is not enough.',
            'default_view' => 'overview',
            'contact_engagement_url' => home_url('/contact/'),
            'show_surveys' => '1',
            'show_public_ideas' => '1',
            'show_release_intelligence' => '1',
            'featured_release_limit' => '4',
        );
    }

    private function settings() {
        return wp_parse_args((array) get_option(self::OPTION_KEY, array()), $this->default_settings());
    }

    public function release_statuses() {
        return array(
            'planned' => __('Planned', 'sustainable-catalyst-feature-suggestions'),
            'preview' => __('Preview', 'sustainable-catalyst-feature-suggestions'),
            'current' => __('Current', 'sustainable-catalyst-feature-suggestions'),
            'maintenance' => __('Maintenance', 'sustainable-catalyst-feature-suggestions'),
            'superseded' => __('Superseded', 'sustainable-catalyst-feature-suggestions'),
            'retired' => __('Retired', 'sustainable-catalyst-feature-suggestions'),
        );
    }

    public function register_release_post_type() {
        register_post_type(self::RELEASE_POST_TYPE, array(
            'labels' => array(
                'name' => __('Release Intelligence', 'sustainable-catalyst-feature-suggestions'),
                'singular_name' => __('Release Record', 'sustainable-catalyst-feature-suggestions'),
                'menu_name' => __('Releases', 'sustainable-catalyst-feature-suggestions'),
                'add_new_item' => __('Add Release Record', 'sustainable-catalyst-feature-suggestions'),
                'edit_item' => __('Edit Release Record', 'sustainable-catalyst-feature-suggestions'),
                'new_item' => __('New Release Record', 'sustainable-catalyst-feature-suggestions'),
                'view_item' => __('View Release Record', 'sustainable-catalyst-feature-suggestions'),
                'search_items' => __('Search Release Records', 'sustainable-catalyst-feature-suggestions'),
                'not_found' => __('No release records found.', 'sustainable-catalyst-feature-suggestions'),
                'all_items' => __('All Release Records', 'sustainable-catalyst-feature-suggestions'),
            ),
            'public' => true,
            'publicly_queryable' => true,
            'show_ui' => true,
            'show_in_menu' => 'edit.php?post_type=' . Sustainable_Catalyst_Feature_Suggestions::POST_TYPE,
            'show_in_rest' => true,
            'rest_base' => 'support-releases',
            'supports' => array('title', 'editor', 'excerpt', 'author', 'revisions', 'custom-fields'),
            'has_archive' => 'support-releases',
            'rewrite' => array('slug' => 'support-releases', 'with_front' => false),
            'taxonomies' => array(
                SCFS_Product_Integration::PRODUCT_TAXONOMY,
                SCFS_Product_Integration::VERSION_TAXONOMY,
                SCFS_Product_Integration::COMPONENT_TAXONOMY,
                SCFS_Product_Integration::ISSUE_TAXONOMY,
                SCFS_Product_Integration::RELEASE_TAXONOMY,
            ),
            'capability_type' => 'post',
            'map_meta_cap' => true,
        ));
    }

    public function register_release_meta() {
        $definitions = array(
            '_scfs_release_status' => array('type' => 'string', 'sanitize_callback' => array($this, 'sanitize_release_status')),
            '_scfs_release_date' => array('type' => 'string', 'sanitize_callback' => array($this, 'sanitize_date')),
            '_scfs_release_summary' => array('type' => 'string', 'sanitize_callback' => 'sanitize_textarea_field'),
            '_scfs_release_support_note' => array('type' => 'string', 'sanitize_callback' => 'sanitize_textarea_field'),
            '_scfs_release_highlights' => array('type' => 'string', 'sanitize_callback' => 'sanitize_textarea_field'),
            '_scfs_release_known_limitations' => array('type' => 'string', 'sanitize_callback' => 'sanitize_textarea_field'),
            '_scfs_release_docs_url' => array('type' => 'string', 'sanitize_callback' => 'esc_url_raw'),
            '_scfs_release_changelog_url' => array('type' => 'string', 'sanitize_callback' => 'esc_url_raw'),
            '_scfs_release_term_id' => array('type' => 'integer', 'sanitize_callback' => 'absint'),
            '_scfs_release_related_articles' => array('type' => 'array', 'sanitize_callback' => array($this, 'sanitize_id_array')),
            '_scfs_release_related_issues' => array('type' => 'array', 'sanitize_callback' => array($this, 'sanitize_id_array')),
            '_scfs_release_related_suggestions' => array('type' => 'array', 'sanitize_callback' => array($this, 'sanitize_id_array')),
        );
        foreach ($definitions as $key => $definition) {
            $args = array(
                'type' => $definition['type'],
                'single' => true,
                'show_in_rest' => $definition['type'] === 'array' ? array('schema' => array('type' => 'array', 'items' => array('type' => 'integer'))) : true,
                'sanitize_callback' => $definition['sanitize_callback'],
                'auth_callback' => function () { return current_user_can('edit_posts'); },
            );
            register_post_meta(self::RELEASE_POST_TYPE, $key, $args);
        }
    }

    public function sanitize_release_status($value) {
        $value = sanitize_key($value);
        return isset($this->release_statuses()[$value]) ? $value : 'planned';
    }

    public function sanitize_date($value) {
        $value = sanitize_text_field($value);
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : '';
    }

    public function sanitize_id_array($value) {
        if (is_string($value)) {
            $value = preg_split('/[\s,]+/', $value);
        }
        return array_values(array_unique(array_filter(array_map('absint', (array) $value))));
    }

    public function register_shortcodes() {
        add_shortcode(self::SHORTCODE, array($this, 'render_shortcode'));
        add_shortcode(self::LEGACY_SHORTCODE, array($this, 'render_shortcode'));
    }

    public function register_assets() {
        wp_register_style(
            'scfs-product-support-platform',
            plugins_url('../assets/product-support-platform.css', __FILE__),
            array('scfs-guided-resolution', 'scfs-knowledge-base'),
            self::VERSION
        );
    }

    public function admin_assets($hook) {
        if (strpos((string) $hook, 'scfs-product-support-platform') !== false || strpos((string) $hook, self::RELEASE_POST_TYPE) !== false) {
            wp_enqueue_style('scfs-admin');
        }
    }

    public function add_meta_boxes() {
        add_meta_box(
            'scfs-release-intelligence',
            __('Release Intelligence', 'sustainable-catalyst-feature-suggestions'),
            array($this, 'release_meta_box'),
            self::RELEASE_POST_TYPE,
            'normal',
            'high'
        );
    }

    public function release_meta_box($post) {
        wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME);
        $status = get_post_meta($post->ID, '_scfs_release_status', true) ?: 'planned';
        $date = get_post_meta($post->ID, '_scfs_release_date', true);
        $summary = get_post_meta($post->ID, '_scfs_release_summary', true);
        $support = get_post_meta($post->ID, '_scfs_release_support_note', true);
        $highlights = get_post_meta($post->ID, '_scfs_release_highlights', true);
        $limitations = get_post_meta($post->ID, '_scfs_release_known_limitations', true);
        $docs = get_post_meta($post->ID, '_scfs_release_docs_url', true);
        $changelog = get_post_meta($post->ID, '_scfs_release_changelog_url', true);
        $term_id = absint(get_post_meta($post->ID, '_scfs_release_term_id', true));
        $articles = implode(', ', $this->sanitize_id_array(get_post_meta($post->ID, '_scfs_release_related_articles', true)));
        $issues = implode(', ', $this->sanitize_id_array(get_post_meta($post->ID, '_scfs_release_related_issues', true)));
        $suggestions = implode(', ', $this->sanitize_id_array(get_post_meta($post->ID, '_scfs_release_related_suggestions', true)));
        echo '<div class="scfs-release-editor"><div class="scfs-grid scfs-grid-two">';
        echo '<p><label><strong>' . esc_html__('Release status', 'sustainable-catalyst-feature-suggestions') . '</strong><br><select name="scfs_release_status">';
        foreach ($this->release_statuses() as $key => $label) {
            echo '<option value="' . esc_attr($key) . '" ' . selected($status, $key, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select></label></p>';
        echo '<p><label><strong>' . esc_html__('Release date', 'sustainable-catalyst-feature-suggestions') . '</strong><br><input type="date" name="scfs_release_date" value="' . esc_attr($date) . '"></label></p></div>';
        echo '<p><label><strong>' . esc_html__('Public summary', 'sustainable-catalyst-feature-suggestions') . '</strong><br><textarea class="widefat" rows="3" name="scfs_release_summary">' . esc_textarea($summary) . '</textarea></label></p>';
        echo '<p><label><strong>' . esc_html__('Support and compatibility note', 'sustainable-catalyst-feature-suggestions') . '</strong><br><textarea class="widefat" rows="3" name="scfs_release_support_note">' . esc_textarea($support) . '</textarea></label></p>';
        echo '<p><label><strong>' . esc_html__('Highlights', 'sustainable-catalyst-feature-suggestions') . '</strong><br><textarea class="widefat" rows="5" name="scfs_release_highlights" placeholder="One highlight per line">' . esc_textarea($highlights) . '</textarea></label></p>';
        echo '<p><label><strong>' . esc_html__('Known limitations', 'sustainable-catalyst-feature-suggestions') . '</strong><br><textarea class="widefat" rows="4" name="scfs_release_known_limitations" placeholder="One limitation per line">' . esc_textarea($limitations) . '</textarea></label></p>';
        echo '<div class="scfs-grid scfs-grid-two"><p><label><strong>' . esc_html__('Documentation URL', 'sustainable-catalyst-feature-suggestions') . '</strong><br><input class="widefat" type="url" name="scfs_release_docs_url" value="' . esc_attr($docs) . '"></label></p>';
        echo '<p><label><strong>' . esc_html__('Changelog URL', 'sustainable-catalyst-feature-suggestions') . '</strong><br><input class="widefat" type="url" name="scfs_release_changelog_url" value="' . esc_attr($changelog) . '"></label></p></div>';
        echo '<p><label><strong>' . esc_html__('Canonical Release taxonomy term ID', 'sustainable-catalyst-feature-suggestions') . '</strong><br><input type="number" min="0" name="scfs_release_term_id" value="' . esc_attr((string) $term_id) . '"></label></p>';
        echo '<div class="scfs-grid scfs-grid-three"><p><label><strong>' . esc_html__('Related article IDs', 'sustainable-catalyst-feature-suggestions') . '</strong><br><input class="widefat" name="scfs_release_related_articles" value="' . esc_attr($articles) . '"></label></p>';
        echo '<p><label><strong>' . esc_html__('Related known-issue IDs', 'sustainable-catalyst-feature-suggestions') . '</strong><br><input class="widefat" name="scfs_release_related_issues" value="' . esc_attr($issues) . '"></label></p>';
        echo '<p><label><strong>' . esc_html__('Related public suggestion IDs', 'sustainable-catalyst-feature-suggestions') . '</strong><br><input class="widefat" name="scfs_release_related_suggestions" value="' . esc_attr($suggestions) . '"></label></p></div>';
        echo '<p class="description">' . esc_html__('Release records are public only when published. Private feature-suggestion text is never exposed through release intelligence.', 'sustainable-catalyst-feature-suggestions') . '</p></div>';
    }

    public function save_release($post_id, $post) {
        if (!isset($_POST[self::NONCE_NAME]) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[self::NONCE_NAME])), self::NONCE_ACTION)) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (!$post || !current_user_can('edit_post', $post_id)) {
            return;
        }
        update_post_meta($post_id, '_scfs_release_status', $this->sanitize_release_status($_POST['scfs_release_status'] ?? 'planned'));
        update_post_meta($post_id, '_scfs_release_date', $this->sanitize_date($_POST['scfs_release_date'] ?? ''));
        update_post_meta($post_id, '_scfs_release_summary', sanitize_textarea_field(wp_unslash($_POST['scfs_release_summary'] ?? '')));
        update_post_meta($post_id, '_scfs_release_support_note', sanitize_textarea_field(wp_unslash($_POST['scfs_release_support_note'] ?? '')));
        update_post_meta($post_id, '_scfs_release_highlights', sanitize_textarea_field(wp_unslash($_POST['scfs_release_highlights'] ?? '')));
        update_post_meta($post_id, '_scfs_release_known_limitations', sanitize_textarea_field(wp_unslash($_POST['scfs_release_known_limitations'] ?? '')));
        update_post_meta($post_id, '_scfs_release_docs_url', esc_url_raw(wp_unslash($_POST['scfs_release_docs_url'] ?? '')));
        update_post_meta($post_id, '_scfs_release_changelog_url', esc_url_raw(wp_unslash($_POST['scfs_release_changelog_url'] ?? '')));
        update_post_meta($post_id, '_scfs_release_term_id', absint($_POST['scfs_release_term_id'] ?? 0));
        update_post_meta($post_id, '_scfs_release_related_articles', $this->sanitize_id_array(wp_unslash($_POST['scfs_release_related_articles'] ?? '')));
        update_post_meta($post_id, '_scfs_release_related_issues', $this->sanitize_id_array(wp_unslash($_POST['scfs_release_related_issues'] ?? '')));
        update_post_meta($post_id, '_scfs_release_related_suggestions', $this->sanitize_id_array(wp_unslash($_POST['scfs_release_related_suggestions'] ?? '')));
        update_post_meta($post_id, '_scfs_product_support_schema_version', self::SCHEMA_VERSION);
        $record = $this->release_record(get_post($post_id), true);
        do_action('scfs_release_intelligence_saved', $post_id, $record);
        $event = array(
            'event_id' => function_exists('wp_generate_uuid4') ? wp_generate_uuid4() : uniqid('scfs-release-', true),
            'event_type' => 'release.intelligence_updated',
            'schema_version' => Sustainable_Catalyst_Feature_Suggestions::EVENT_SCHEMA_VERSION,
            'source' => 'feature_suggestions',
            'source_version' => self::VERSION,
            'occurred_at' => gmdate('c'),
            'data' => array(
                'release_record_id' => (int) $post_id,
                'status' => $record['status'] ?? 'planned',
                'release_date' => $record['release_date'] ?? '',
                'product_slugs' => array_values(wp_list_pluck($record['products'] ?? array(), 'slug')),
                'readiness' => $record['readiness'] ?? array(),
                'public_record' => $post->post_status === 'publish',
            ),
        );
        do_action('scfs_event', $event);
        do_action('sc_platform_event', $event);
        do_action('scfs_dispatch_event', $event);
    }

    public function schema_record() {
        return array(
            'schema' => 'scfs-product-support-platform/' . self::SCHEMA_VERSION,
            'version' => self::VERSION,
            'platform' => 'Sustainable Catalyst Product Support and Feedback Platform',
            'public_modules' => array(
                'guided_resolution',
                'support_knowledge_base',
                'known_issues',
                'release_intelligence',
                'feature_suggestions',
                'public_ideas',
                'advisory_voting',
                'forms_and_surveys',
            ),
            'private_integrations' => array('contact_and_engagement'),
            'shared_context' => array('product', 'product_version', 'component', 'issue_type', 'release'),
            'shortcodes' => array(self::SHORTCODE, self::LEGACY_SHORTCODE),
            'routes' => array(
                '/product-support/schema',
                '/product-support/overview',
                '/product-support/releases',
                '/product-support/products',
                '/product-support/handoff-schema',
                '/product-support/snapshot',
            ),
            'governance' => array(
                'public_support_source_of_truth' => 'feature_suggestions',
                'private_case_source_of_truth' => 'contact_and_engagement',
                'voting_is_advisory' => true,
                'surveys_do_not_auto_prioritize' => true,
                'human_review_required' => true,
                'automatic_case_creation' => false,
            ),
        );
    }

    public function handoff_schema() {
        return array(
            'schema' => 'sc-product-support-contact-handoff/' . self::SCHEMA_VERSION,
            'source' => 'sustainable-catalyst-feature-suggestions',
            'destination' => 'contact_and_engagement',
            'trigger' => 'unresolved_public_support',
            'context' => array(
                'product', 'product_version', 'component', 'issue_type', 'release',
                'search_query', 'search_confidence', 'articles_viewed', 'known_issues_viewed',
                'release_records_viewed', 'public_suggestions_viewed', 'unresolved_reason',
            ),
            'privacy' => array(
                'identity_collected_by_feature_suggestions' => false,
                'case_content_stored_by_feature_suggestions' => false,
                'consent_required' => true,
                'token_expiration_minutes' => 30,
            ),
            'routing' => array(
                'automatic_case_creation' => false,
                'human_review_required' => true,
                'private_documents_supported_by_destination' => true,
            ),
        );
    }

    private function count_post_type($post_type, $statuses = array('publish')) {
        $counts = wp_count_posts($post_type);
        $total = 0;
        foreach ($statuses as $status) {
            $total += isset($counts->{$status}) ? (int) $counts->{$status} : 0;
        }
        return $total;
    }

    private function active_known_issue_count($product = '') {
        $args = array(
            'post_type' => SCFS_Knowledge_Base_Foundation::ISSUE_POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => 1,
            'fields' => 'ids',
            'no_found_rows' => false,
            'meta_query' => array(array('key' => '_scfs_issue_status', 'value' => array('resolved', 'closed'), 'compare' => 'NOT IN')),
        );
        if ($product !== '') {
            $args['tax_query'] = array(array('taxonomy' => SCFS_Product_Integration::PRODUCT_TAXONOMY, 'field' => 'slug', 'terms' => $product));
        }
        $query = new WP_Query($args);
        return (int) $query->found_posts;
    }

    private function public_idea_count($product = '') {
        $args = array(
            'post_type' => Sustainable_Catalyst_Feature_Suggestions::POST_TYPE,
            'post_status' => array('publish', 'pending', 'draft', 'private'),
            'posts_per_page' => 1,
            'fields' => 'ids',
            'no_found_rows' => false,
            'meta_query' => array(array('key' => '_scfs_public_visibility', 'value' => '1')),
        );
        if ($product !== '') {
            $args['tax_query'] = array(array('taxonomy' => SCFS_Product_Integration::PRODUCT_TAXONOMY, 'field' => 'slug', 'terms' => $product));
        }
        $query = new WP_Query($args);
        return (int) $query->found_posts;
    }

    private function open_survey_count() {
        if (!post_type_exists(SCFS_Forms_Foundation::FORM_TYPE)) {
            return 0;
        }
        $query = new WP_Query(array(
            'post_type' => SCFS_Forms_Foundation::FORM_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => 1,
            'fields' => 'ids',
            'no_found_rows' => false,
            'meta_query' => array(
                array('key' => '_scfs_form_mode', 'value' => 'survey'),
                array('key' => '_scfs_form_open', 'value' => '1'),
            ),
        ));
        return (int) $query->found_posts;
    }

    public function overview_record($product = '') {
        $product = sanitize_title($product);
        $latest = $this->release_query($product, 1);
        $current_release = $latest->posts ? $this->release_record($latest->posts[0], false) : null;
        return array(
            'schema' => 'scfs-product-support-overview/' . self::SCHEMA_VERSION,
            'version' => self::VERSION,
            'generated_at' => gmdate('c'),
            'product' => $product,
            'counts' => array(
                'support_articles' => $this->count_post_type(SCFS_Knowledge_Base_Foundation::ARTICLE_POST_TYPE),
                'active_known_issues' => $this->active_known_issue_count($product),
                'public_ideas' => $this->public_idea_count($product),
                'open_surveys' => $this->open_survey_count(),
                'release_records' => $this->count_post_type(self::RELEASE_POST_TYPE),
            ),
            'current_release' => $current_release,
            'privacy' => array(
                'public_records_only' => true,
                'private_case_content_exposed' => false,
                'contact_and_engagement_is_private_system_of_record' => true,
            ),
        );
    }

    private function release_query($product = '', $limit = 12, $status = '') {
        $args = array(
            'post_type' => self::RELEASE_POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => max(1, min(100, absint($limit))),
            'meta_key' => '_scfs_release_date',
            'orderby' => array('meta_value' => 'DESC', 'modified' => 'DESC'),
            'order' => 'DESC',
            'no_found_rows' => true,
        );
        if ($status !== '' && isset($this->release_statuses()[$status])) {
            $args['meta_query'] = array(array('key' => '_scfs_release_status', 'value' => $status));
        }
        if ($product !== '') {
            $args['tax_query'] = array(array('taxonomy' => SCFS_Product_Integration::PRODUCT_TAXONOMY, 'field' => 'slug', 'terms' => $product));
        }
        return new WP_Query($args);
    }

    private function term_records($post_id, $taxonomy) {
        $terms = get_the_terms($post_id, $taxonomy);
        if (!$terms || is_wp_error($terms)) {
            return array();
        }
        return array_map(function ($term) {
            return array('id' => (int) $term->term_id, 'slug' => $term->slug, 'name' => $term->name);
        }, $terms);
    }

    private function public_related_suggestions($ids) {
        $records = array();
        foreach ($this->sanitize_id_array($ids) as $id) {
            $post = get_post($id);
            if (!$post || $post->post_type !== Sustainable_Catalyst_Feature_Suggestions::POST_TYPE || get_post_meta($id, '_scfs_public_visibility', true) !== '1') {
                continue;
            }
            $records[] = array(
                'id' => (int) $id,
                'title' => get_the_title($id),
                'state' => get_post_meta($id, '_scfs_public_state', true) ?: 'under_review',
                'supports' => absint(get_post_meta($id, '_scfs_support_votes', true)),
                'private_submission_text_exposed' => false,
            );
        }
        return $records;
    }

    private function public_related_posts($ids, $post_type) {
        $records = array();
        foreach ($this->sanitize_id_array($ids) as $id) {
            $post = get_post($id);
            if (!$post || $post->post_type !== $post_type || $post->post_status !== 'publish') {
                continue;
            }
            $records[] = array('id' => (int) $id, 'title' => get_the_title($id), 'url' => get_permalink($id));
        }
        return $records;
    }

    public function calculate_release_readiness($post_id) {
        $summary = trim((string) get_post_meta($post_id, '_scfs_release_summary', true));
        $support = trim((string) get_post_meta($post_id, '_scfs_release_support_note', true));
        $date = trim((string) get_post_meta($post_id, '_scfs_release_date', true));
        $changelog = trim((string) get_post_meta($post_id, '_scfs_release_changelog_url', true));
        $products = $this->term_records($post_id, SCFS_Product_Integration::PRODUCT_TAXONOMY);
        $articles = $this->public_related_posts(get_post_meta($post_id, '_scfs_release_related_articles', true), SCFS_Knowledge_Base_Foundation::ARTICLE_POST_TYPE);
        $issues = $this->public_related_posts(get_post_meta($post_id, '_scfs_release_related_issues', true), SCFS_Knowledge_Base_Foundation::ISSUE_POST_TYPE);
        $score = 0;
        $missing = array();
        foreach (array(
            'public_summary' => array($summary !== '', 15),
            'support_note' => array($support !== '', 15),
            'release_date' => array($date !== '', 15),
            'changelog' => array($changelog !== '', 10),
            'product_context' => array(!empty($products), 15),
        ) as $label => $signal) {
            if ($signal[0]) {
                $score += $signal[1];
            } else {
                $missing[] = $label;
            }
        }
        $score += min(20, count($articles) * 5);
        $score += min(10, count($issues) * 2);
        $critical = 0;
        foreach ($issues as $issue) {
            $severity = get_post_meta($issue['id'], '_scfs_issue_severity', true);
            $status = get_post_meta($issue['id'], '_scfs_issue_status', true);
            if ($severity === 'critical' && !in_array($status, array('resolved', 'closed'), true)) {
                $critical++;
            }
        }
        $blockers = array();
        if ($critical > 0) {
            $score -= min(60, $critical * 25);
            $blockers[] = 'unresolved_critical_issues';
        }
        $score = max(0, min(100, $score));
        $state = ($blockers || $score < 50) ? 'not_ready' : ($score < 80 ? 'review' : 'ready');
        return array(
            'schema' => 'scfs-release-readiness/' . self::SCHEMA_VERSION,
            'score' => (int) $score,
            'state' => $state,
            'missing' => $missing,
            'blockers' => $blockers,
            'human_review_required' => true,
        );
    }

    public function release_record($post, $include_internal = false) {
        if (is_numeric($post)) {
            $post = get_post(absint($post));
        }
        if (!$post || $post->post_type !== self::RELEASE_POST_TYPE) {
            return array();
        }
        $record = array(
            'schema' => 'scfs-release-intelligence/' . self::SCHEMA_VERSION,
            'id' => (int) $post->ID,
            'slug' => $post->post_name,
            'title' => $post->post_title,
            'summary' => get_post_meta($post->ID, '_scfs_release_summary', true) ?: wp_trim_words(wp_strip_all_tags($post->post_excerpt ?: $post->post_content), 45),
            'status' => get_post_meta($post->ID, '_scfs_release_status', true) ?: 'planned',
            'release_date' => get_post_meta($post->ID, '_scfs_release_date', true),
            'support_note' => get_post_meta($post->ID, '_scfs_release_support_note', true),
            'highlights' => array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string) get_post_meta($post->ID, '_scfs_release_highlights', true))))),
            'known_limitations' => array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string) get_post_meta($post->ID, '_scfs_release_known_limitations', true))))),
            'documentation_url' => get_post_meta($post->ID, '_scfs_release_docs_url', true),
            'changelog_url' => get_post_meta($post->ID, '_scfs_release_changelog_url', true),
            'url' => get_permalink($post),
            'products' => $this->term_records($post->ID, SCFS_Product_Integration::PRODUCT_TAXONOMY),
            'product_versions' => $this->term_records($post->ID, SCFS_Product_Integration::VERSION_TAXONOMY),
            'components' => $this->term_records($post->ID, SCFS_Product_Integration::COMPONENT_TAXONOMY),
            'release_terms' => $this->term_records($post->ID, SCFS_Product_Integration::RELEASE_TAXONOMY),
            'related_articles' => $this->public_related_posts(get_post_meta($post->ID, '_scfs_release_related_articles', true), SCFS_Knowledge_Base_Foundation::ARTICLE_POST_TYPE),
            'related_known_issues' => $this->public_related_posts(get_post_meta($post->ID, '_scfs_release_related_issues', true), SCFS_Knowledge_Base_Foundation::ISSUE_POST_TYPE),
            'related_public_suggestions' => $this->public_related_suggestions(get_post_meta($post->ID, '_scfs_release_related_suggestions', true)),
            'readiness' => $this->calculate_release_readiness($post->ID),
            'private_suggestion_text_exposed' => false,
        );
        if ($include_internal) {
            $record['canonical_release_term_id'] = absint(get_post_meta($post->ID, '_scfs_release_term_id', true));
            $record['post_status'] = $post->post_status;
        }
        return $record;
    }

    private function current_context() {
        $settings = $this->settings();
        $allowed = array('overview', 'resolve', 'documentation', 'issues', 'releases', 'ideas', 'suggest', 'surveys', 'private-support');
        $view = isset($_GET['scfs_support_view']) ? sanitize_key(wp_unslash($_GET['scfs_support_view'])) : sanitize_key($settings['default_view']);
        if (!in_array($view, $allowed, true)) {
            $view = 'overview';
        }
        return array(
            'view' => $view,
            'product' => isset($_GET['scfs_support_product']) ? sanitize_title(wp_unslash($_GET['scfs_support_product'])) : '',
            'survey' => isset($_GET['scfs_support_survey']) ? absint($_GET['scfs_support_survey']) : 0,
        );
    }

    private function view_url($view, $product = '', $extra = array()) {
        $args = array_merge(array('scfs_support_view' => $view), $extra);
        if ($product !== '') {
            $args['scfs_support_product'] = $product;
        }
        return add_query_arg($args, remove_query_arg(array('scfs_support_view', 'scfs_support_product', 'scfs_support_survey')));
    }

    private function render_product_select($selected) {
        $terms = get_terms(array('taxonomy' => SCFS_Product_Integration::PRODUCT_TAXONOMY, 'hide_empty' => false, 'orderby' => 'name'));
        if (is_wp_error($terms)) {
            $terms = array();
        }
        echo '<form class="scfs-support-product-filter" method="get">';
        echo '<input type="hidden" name="scfs_support_view" value="overview">';
        echo '<label><span>' . esc_html__('Support context', 'sustainable-catalyst-feature-suggestions') . '</span><select name="scfs_support_product"><option value="">' . esc_html__('All Sustainable Catalyst products', 'sustainable-catalyst-feature-suggestions') . '</option>';
        foreach ($terms as $term) {
            echo '<option value="' . esc_attr($term->slug) . '" ' . selected($selected, $term->slug, false) . '>' . esc_html($term->name) . '</option>';
        }
        echo '</select></label><button type="submit">' . esc_html__('Apply product', 'sustainable-catalyst-feature-suggestions') . '</button></form>';
    }

    private function navigation_items($settings) {
        $items = array(
            'overview' => array('label' => __('Support overview', 'sustainable-catalyst-feature-suggestions'), 'description' => __('Status and starting points', 'sustainable-catalyst-feature-suggestions')),
            'resolve' => array('label' => __('Find a resolution', 'sustainable-catalyst-feature-suggestions'), 'description' => __('Guided search and known issues', 'sustainable-catalyst-feature-suggestions')),
            'documentation' => array('label' => __('Knowledge Base', 'sustainable-catalyst-feature-suggestions'), 'description' => __('How-to and reference articles', 'sustainable-catalyst-feature-suggestions')),
            'issues' => array('label' => __('Known issues', 'sustainable-catalyst-feature-suggestions'), 'description' => __('Current product problems', 'sustainable-catalyst-feature-suggestions')),
            'releases' => array('label' => __('Releases', 'sustainable-catalyst-feature-suggestions'), 'description' => __('Changes and compatibility', 'sustainable-catalyst-feature-suggestions')),
            'ideas' => array('label' => __('Public ideas', 'sustainable-catalyst-feature-suggestions'), 'description' => __('Vote and follow decisions', 'sustainable-catalyst-feature-suggestions')),
            'suggest' => array('label' => __('Suggest a feature', 'sustainable-catalyst-feature-suggestions'), 'description' => __('Submit a public product idea', 'sustainable-catalyst-feature-suggestions')),
            'surveys' => array('label' => __('Surveys', 'sustainable-catalyst-feature-suggestions'), 'description' => __('Participate in product research', 'sustainable-catalyst-feature-suggestions')),
            'private-support' => array('label' => __('Private support', 'sustainable-catalyst-feature-suggestions'), 'description' => __('Continue to Contact and Engagement', 'sustainable-catalyst-feature-suggestions')),
        );
        if (empty($settings['show_surveys'])) {
            unset($items['surveys']);
        }
        if (empty($settings['show_public_ideas'])) {
            unset($items['ideas']);
        }
        if (empty($settings['show_release_intelligence'])) {
            unset($items['releases']);
        }
        return $items;
    }

    public function render_shortcode($atts = array()) {
        $settings = $this->settings();
        $atts = shortcode_atts(array(
            'title' => $settings['support_center_title'],
            'intro' => $settings['support_center_intro'],
            'default_view' => $settings['default_view'],
            'compact' => '0',
        ), $atts, self::SHORTCODE);
        wp_enqueue_style('scfs-product-support-platform');
        $context = $this->current_context();
        if (!isset($_GET['scfs_support_view']) && in_array(sanitize_key($atts['default_view']), array_keys($this->navigation_items($settings)), true)) {
            $context['view'] = sanitize_key($atts['default_view']);
        }
        $overview = $this->overview_record($context['product']);
        ob_start();
        echo '<section class="scfs-support-platform' . ($atts['compact'] === '1' ? ' scfs-support-platform--compact' : '') . '" aria-labelledby="scfs-support-platform-title">';
        echo '<header class="scfs-support-platform__hero"><p class="scfs-support-platform__eyebrow">' . esc_html__('Product Support and Feedback Platform', 'sustainable-catalyst-feature-suggestions') . '</p><h2 id="scfs-support-platform-title">' . esc_html($atts['title']) . '</h2><p>' . esc_html($atts['intro']) . '</p>';
        echo '<div class="scfs-support-platform__boundary"><strong>' . esc_html__('Public support center:', 'sustainable-catalyst-feature-suggestions') . '</strong> ' . esc_html__('Documentation, known issues, releases, feature ideas, voting, and surveys live here. Private cases, messages, contact details, and documents remain in Contact and Engagement.', 'sustainable-catalyst-feature-suggestions') . '</div></header>';
        $this->render_product_select($context['product']);
        echo '<div class="scfs-support-platform__status" aria-label="' . esc_attr__('Support status summary', 'sustainable-catalyst-feature-suggestions') . '">';
        foreach (array(
            'support_articles' => __('Support articles', 'sustainable-catalyst-feature-suggestions'),
            'active_known_issues' => __('Active known issues', 'sustainable-catalyst-feature-suggestions'),
            'release_records' => __('Release records', 'sustainable-catalyst-feature-suggestions'),
            'public_ideas' => __('Public ideas', 'sustainable-catalyst-feature-suggestions'),
            'open_surveys' => __('Open surveys', 'sustainable-catalyst-feature-suggestions'),
        ) as $key => $label) {
            echo '<div><strong>' . esc_html((string) $overview['counts'][$key]) . '</strong><span>' . esc_html($label) . '</span></div>';
        }
        echo '</div>';
        echo '<nav class="scfs-support-platform__nav" aria-label="' . esc_attr__('Product support sections', 'sustainable-catalyst-feature-suggestions') . '">';
        foreach ($this->navigation_items($settings) as $key => $item) {
            echo '<a class="' . ($context['view'] === $key ? 'is-active' : '') . '" href="' . esc_url($this->view_url($key, $context['product'])) . '"><strong>' . esc_html($item['label']) . '</strong><span>' . esc_html($item['description']) . '</span></a>';
        }
        echo '</nav><div class="scfs-support-platform__workspace">';
        $this->render_view($context, $overview, $settings);
        echo '</div></section>';
        return ob_get_clean();
    }

    private function with_request_value($key, $value, $callback) {
        $exists = array_key_exists($key, $_GET);
        $previous = $exists ? $_GET[$key] : null;
        if ($value !== '' && !$exists) {
            $_GET[$key] = $value;
        }
        $result = call_user_func($callback);
        if (!$exists) {
            unset($_GET[$key]);
        } else {
            $_GET[$key] = $previous;
        }
        return $result;
    }

    private function render_view($context, $overview, $settings) {
        switch ($context['view']) {
            case 'resolve':
                echo $this->with_request_value('scfs_resolution_product', $context['product'], function () {
                    return SCFS_Guided_Resolution::instance()->render_shortcode(array('title' => __('Find a resolution', 'sustainable-catalyst-feature-suggestions'), 'compact' => '1', 'show_handoff' => '1'));
                }); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                break;
            case 'documentation':
                echo $this->with_request_value('scfs_kb_product', $context['product'], function () {
                    return SCFS_Knowledge_Base_Foundation::instance()->render_shortcode(array('title' => __('Support Knowledge Base', 'sustainable-catalyst-feature-suggestions'), 'show_known_issues' => '0'));
                }); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                break;
            case 'issues':
                $this->render_known_issues($context['product']);
                break;
            case 'releases':
                $this->render_releases($context['product'], 30);
                break;
            case 'ideas':
                echo SCFS_Public_Ideas::instance()->shortcode(array('title' => __('Public product ideas', 'sustainable-catalyst-feature-suggestions'), 'limit' => '30', 'product' => $context['product'])); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                break;
            case 'suggest':
                echo Sustainable_Catalyst_Feature_Suggestions::instance()->render_shortcode(array('title' => __('Suggest a product improvement', 'sustainable-catalyst-feature-suggestions'), 'intro' => __('Submit a non-confidential feature idea. Product context, public participation, and roadmap decisions remain subject to human review.', 'sustainable-catalyst-feature-suggestions'))); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                break;
            case 'surveys':
                $this->render_surveys($context['survey'], $context['product']);
                break;
            case 'private-support':
                $this->render_private_support($settings, $context['product']);
                break;
            case 'overview':
            default:
                $this->render_overview($overview, $context['product'], $settings);
                break;
        }
    }

    private function render_overview($overview, $product, $settings) {
        echo '<div class="scfs-support-platform__overview"><section><h3>' . esc_html__('Start with guided resolution', 'sustainable-catalyst-feature-suggestions') . '</h3><p>' . esc_html__('Describe the task, symptom, or exact error fragment. Current known issues are prioritized before general documentation.', 'sustainable-catalyst-feature-suggestions') . '</p>';
        echo $this->with_request_value('scfs_resolution_product', $product, function () {
            return SCFS_Guided_Resolution::instance()->render_shortcode(array('title' => __('Search product support', 'sustainable-catalyst-feature-suggestions'), 'compact' => '1', 'show_handoff' => '1'));
        }); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo '</section><section class="scfs-support-platform__pathways"><h3>' . esc_html__('Choose another support pathway', 'sustainable-catalyst-feature-suggestions') . '</h3><div class="scfs-support-platform__cards">';
        $cards = array(
            'documentation' => array(__('Browse documentation', 'sustainable-catalyst-feature-suggestions'), __('Read task-based guidance, troubleshooting, technical references, and product collections.', 'sustainable-catalyst-feature-suggestions')),
            'issues' => array(__('Check known issues', 'sustainable-catalyst-feature-suggestions'), __('Review verified problems, current status, workarounds, and planned resolutions.', 'sustainable-catalyst-feature-suggestions')),
            'ideas' => array(__('Review and support ideas', 'sustainable-catalyst-feature-suggestions'), __('Vote on moderated public suggestions and follow official roadmap decisions.', 'sustainable-catalyst-feature-suggestions')),
            'suggest' => array(__('Suggest an improvement', 'sustainable-catalyst-feature-suggestions'), __('Submit a structured, non-confidential feature or documentation request.', 'sustainable-catalyst-feature-suggestions')),
            'surveys' => array(__('Participate in research', 'sustainable-catalyst-feature-suggestions'), __('Respond to open product surveys. Survey results inform review but do not automatically set priorities.', 'sustainable-catalyst-feature-suggestions')),
            'private-support' => array(__('Continue to private support', 'sustainable-catalyst-feature-suggestions'), __('Use Contact and Engagement for identity, correspondence, private documents, and case lifecycle management.', 'sustainable-catalyst-feature-suggestions')),
        );
        foreach ($cards as $view => $card) {
            if (($view === 'surveys' && empty($settings['show_surveys'])) || ($view === 'ideas' && empty($settings['show_public_ideas']))) {
                continue;
            }
            echo '<a href="' . esc_url($this->view_url($view, $product)) . '"><h4>' . esc_html($card[0]) . '</h4><p>' . esc_html($card[1]) . '</p><span>' . esc_html__('Open pathway', 'sustainable-catalyst-feature-suggestions') . ' →</span></a>';
        }
        echo '</div></section>';
        if (!empty($settings['show_release_intelligence'])) {
            echo '<section><div class="scfs-support-platform__section-head"><div><h3>' . esc_html__('Release intelligence', 'sustainable-catalyst-feature-suggestions') . '</h3><p>' . esc_html__('Current, planned, maintenance, and retired releases with documentation and known-issue relationships.', 'sustainable-catalyst-feature-suggestions') . '</p></div><a href="' . esc_url($this->view_url('releases', $product)) . '">' . esc_html__('View all releases', 'sustainable-catalyst-feature-suggestions') . '</a></div>';
            $this->render_releases($product, absint($settings['featured_release_limit']), true);
            echo '</section>';
        }
        echo '</div>';
    }

    private function render_known_issues($product) {
        $args = array(
            'post_type' => SCFS_Knowledge_Base_Foundation::ISSUE_POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => 50,
            'orderby' => 'modified',
            'order' => 'DESC',
        );
        if ($product !== '') {
            $args['tax_query'] = array(array('taxonomy' => SCFS_Product_Integration::PRODUCT_TAXONOMY, 'field' => 'slug', 'terms' => $product));
        }
        $query = new WP_Query($args);
        echo '<section class="scfs-support-platform__issues"><header><p class="scfs-support-platform__kicker">' . esc_html__('Verified product status', 'sustainable-catalyst-feature-suggestions') . '</p><h3>' . esc_html__('Known issues', 'sustainable-catalyst-feature-suggestions') . '</h3><p>' . esc_html__('Published issue records show status, severity, visible symptoms, workarounds, and release relationships.', 'sustainable-catalyst-feature-suggestions') . '</p></header><div class="scfs-support-platform__issue-grid">';
        if (!$query->have_posts()) {
            echo '<div class="scfs-support-platform__empty"><h4>' . esc_html__('No published known issues match this product', 'sustainable-catalyst-feature-suggestions') . '</h4><p>' . esc_html__('Use Guided Resolution for broader troubleshooting or continue to private support when the problem is not documented.', 'sustainable-catalyst-feature-suggestions') . '</p></div>';
        }
        foreach ($query->posts as $issue) {
            $status = get_post_meta($issue->ID, '_scfs_issue_status', true) ?: 'investigating';
            $severity = get_post_meta($issue->ID, '_scfs_issue_severity', true) ?: 'moderate';
            $symptom = get_post_meta($issue->ID, '_scfs_issue_symptom', true);
            $workaround = get_post_meta($issue->ID, '_scfs_issue_workaround', true);
            echo '<article><div class="scfs-support-platform__badges"><span class="status-' . esc_attr($status) . '">' . esc_html(ucwords(str_replace('_', ' ', $status))) . '</span><span class="severity-' . esc_attr($severity) . '">' . esc_html(ucwords(str_replace('_', ' ', $severity))) . '</span></div><h4><a href="' . esc_url(get_permalink($issue)) . '">' . esc_html(get_the_title($issue)) . '</a></h4>';
            if ($symptom) {
                echo '<p>' . esc_html(wp_trim_words($symptom, 35)) . '</p>';
            }
            if ($workaround) {
                echo '<p class="scfs-support-platform__workaround"><strong>' . esc_html__('Workaround:', 'sustainable-catalyst-feature-suggestions') . '</strong> ' . esc_html(wp_trim_words($workaround, 28)) . '</p>';
            }
            echo '<a href="' . esc_url(get_permalink($issue)) . '">' . esc_html__('View issue details', 'sustainable-catalyst-feature-suggestions') . ' →</a></article>';
        }
        echo '</div></section>';
    }

    private function render_releases($product, $limit = 12, $embedded = false) {
        $query = $this->release_query($product, $limit);
        if (!$embedded) {
            echo '<section class="scfs-support-platform__releases"><header><p class="scfs-support-platform__kicker">' . esc_html__('Product change intelligence', 'sustainable-catalyst-feature-suggestions') . '</p><h3>' . esc_html__('Releases', 'sustainable-catalyst-feature-suggestions') . '</h3><p>' . esc_html__('Review current, planned, maintenance, superseded, and retired releases with support implications and linked public records.', 'sustainable-catalyst-feature-suggestions') . '</p></header>';
        }
        echo '<div class="scfs-support-platform__release-grid">';
        if (!$query->have_posts()) {
            echo '<div class="scfs-support-platform__empty"><h4>' . esc_html__('No published release records match this product', 'sustainable-catalyst-feature-suggestions') . '</h4><p>' . esc_html__('Release taxonomy relationships remain available in Support Articles, Known Issues, and public ideas.', 'sustainable-catalyst-feature-suggestions') . '</p></div>';
        }
        foreach ($query->posts as $post) {
            $record = $this->release_record($post, false);
            echo '<article class="scfs-support-platform__release-card"><div class="scfs-support-platform__release-meta"><span class="status-' . esc_attr($record['status']) . '">' . esc_html($this->release_statuses()[$record['status']] ?? $record['status']) . '</span>';
            if ($record['release_date']) {
                echo '<time datetime="' . esc_attr($record['release_date']) . '">' . esc_html($record['release_date']) . '</time>';
            }
            echo '</div><h4><a href="' . esc_url($record['url']) . '">' . esc_html($record['title']) . '</a></h4><p>' . esc_html($record['summary']) . '</p>';
            if ($record['products']) {
                echo '<p class="scfs-support-platform__products">' . esc_html(implode(' · ', wp_list_pluck($record['products'], 'name'))) . '</p>';
            }
            echo '<div class="scfs-support-platform__release-links"><a href="' . esc_url($record['url']) . '">' . esc_html__('Release details', 'sustainable-catalyst-feature-suggestions') . '</a>';
            if ($record['documentation_url']) {
                echo '<a href="' . esc_url($record['documentation_url']) . '">' . esc_html__('Documentation', 'sustainable-catalyst-feature-suggestions') . '</a>';
            }
            if ($record['changelog_url']) {
                echo '<a href="' . esc_url($record['changelog_url']) . '">' . esc_html__('Changelog', 'sustainable-catalyst-feature-suggestions') . '</a>';
            }
            echo '</div></article>';
        }
        echo '</div>';
        if (!$embedded) {
            echo '</section>';
        }
    }

    private function render_surveys($selected_id, $product) {
        if (!post_type_exists(SCFS_Forms_Foundation::FORM_TYPE)) {
            echo '<div class="scfs-support-platform__empty"><h3>' . esc_html__('Survey system unavailable', 'sustainable-catalyst-feature-suggestions') . '</h3></div>';
            return;
        }
        if ($selected_id) {
            $form = get_post($selected_id);
            if ($form && $form->post_type === SCFS_Forms_Foundation::FORM_TYPE && $form->post_status === 'publish' && get_post_meta($form->ID, '_scfs_form_mode', true) === 'survey') {
                echo '<p><a href="' . esc_url($this->view_url('surveys', $product)) . '">← ' . esc_html__('Back to surveys', 'sustainable-catalyst-feature-suggestions') . '</a></p>';
                echo SCFS_Forms_Foundation::instance()->render_shortcode(array('id' => (string) $form->ID, 'class' => 'scfs-support-platform-survey')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                return;
            }
        }
        $query = new WP_Query(array(
            'post_type' => SCFS_Forms_Foundation::FORM_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => 30,
            'orderby' => 'modified',
            'order' => 'DESC',
            'meta_query' => array(
                array('key' => '_scfs_form_mode', 'value' => 'survey'),
                array('key' => '_scfs_form_open', 'value' => '1'),
            ),
        ));
        echo '<section class="scfs-support-platform__surveys"><header><p class="scfs-support-platform__kicker">' . esc_html__('Product research', 'sustainable-catalyst-feature-suggestions') . '</p><h3>' . esc_html__('Open surveys', 'sustainable-catalyst-feature-suggestions') . '</h3><p>' . esc_html__('Survey participation informs human review. Responses do not automatically approve features, set roadmap state, or create private support cases.', 'sustainable-catalyst-feature-suggestions') . '</p></header><div class="scfs-support-platform__survey-grid">';
        if (!$query->have_posts()) {
            echo '<div class="scfs-support-platform__empty"><h4>' . esc_html__('No surveys are currently open', 'sustainable-catalyst-feature-suggestions') . '</h4></div>';
        }
        foreach ($query->posts as $survey) {
            echo '<article><h4>' . esc_html(get_the_title($survey)) . '</h4><p>' . esc_html(wp_trim_words(wp_strip_all_tags($survey->post_content), 35)) . '</p><a href="' . esc_url($this->view_url('surveys', $product, array('scfs_support_survey' => $survey->ID))) . '">' . esc_html__('Take survey', 'sustainable-catalyst-feature-suggestions') . ' →</a></article>';
        }
        echo '</div></section>';
    }

    private function render_private_support($settings, $product) {
        $url = $settings['contact_engagement_url'];
        if ($product !== '') {
            $url = add_query_arg(array('support_product' => $product, 'source' => 'product-support-platform'), $url);
        }
        echo '<section class="scfs-support-platform__private"><p class="scfs-support-platform__kicker">' . esc_html__('Private case handoff', 'sustainable-catalyst-feature-suggestions') . '</p><h3>' . esc_html__('Continue to Contact and Engagement', 'sustainable-catalyst-feature-suggestions') . '</h3><p>' . esc_html__('Use private support when public documentation and known issues do not resolve the problem, or when you need to share identity, correspondence, private files, institutional context, or other non-public information.', 'sustainable-catalyst-feature-suggestions') . '</p>';
        echo '<div class="scfs-support-platform__privacy-grid"><div><strong>' . esc_html__('Feature Suggestions keeps', 'sustainable-catalyst-feature-suggestions') . '</strong><p>' . esc_html__('Public support context, public records viewed, privacy-minimized search confidence, and an opaque handoff token.', 'sustainable-catalyst-feature-suggestions') . '</p></div><div><strong>' . esc_html__('Contact and Engagement keeps', 'sustainable-catalyst-feature-suggestions') . '</strong><p>' . esc_html__('Contact details, private messages, documents, case records, communication history, and lifecycle management.', 'sustainable-catalyst-feature-suggestions') . '</p></div></div>';
        echo '<p><a class="scfs-support-platform__primary-action" href="' . esc_url($url) . '">' . esc_html__('Open private support intake', 'sustainable-catalyst-feature-suggestions') . '</a></p><p class="scfs-support-platform__privacy-note">' . esc_html__('Do not paste passwords, API keys, payment information, medical information, or regulated data into public support searches or feature suggestions.', 'sustainable-catalyst-feature-suggestions') . '</p></section>';
    }

    public function template_include($template) {
        if (is_post_type_archive(self::RELEASE_POST_TYPE)) {
            $candidate = plugin_dir_path(__DIR__) . 'templates/archive-support-releases.php';
            if (file_exists($candidate)) {
                return $candidate;
            }
        }
        return $template;
    }

    public function decorate_release_content($content) {
        if (!is_singular(self::RELEASE_POST_TYPE) || !in_the_loop() || !is_main_query()) {
            return $content;
        }
        $record = $this->release_record(get_the_ID(), false);
        if (!$record) {
            return $content;
        }
        ob_start();
        echo '<aside class="scfs-release-summary"><div class="scfs-support-platform__release-meta"><span class="status-' . esc_attr($record['status']) . '">' . esc_html($this->release_statuses()[$record['status']] ?? $record['status']) . '</span>';
        if ($record['release_date']) {
            echo '<time datetime="' . esc_attr($record['release_date']) . '">' . esc_html($record['release_date']) . '</time>';
        }
        echo '</div>';
        if ($record['summary']) {
            echo '<p>' . esc_html($record['summary']) . '</p>';
        }
        if ($record['support_note']) {
            echo '<p><strong>' . esc_html__('Support note:', 'sustainable-catalyst-feature-suggestions') . '</strong> ' . esc_html($record['support_note']) . '</p>';
        }
        if ($record['products']) {
            echo '<p><strong>' . esc_html__('Products:', 'sustainable-catalyst-feature-suggestions') . '</strong> ' . esc_html(implode(', ', wp_list_pluck($record['products'], 'name'))) . '</p>';
        }
        echo '</aside>';
        if ($record['highlights']) {
            echo '<section class="scfs-release-related"><h2>' . esc_html__('Release highlights', 'sustainable-catalyst-feature-suggestions') . '</h2><ul>';
            foreach ($record['highlights'] as $item) {
                echo '<li>' . esc_html($item) . '</li>';
            }
            echo '</ul></section>';
        }
        if ($record['known_limitations']) {
            echo '<section class="scfs-release-related"><h2>' . esc_html__('Known limitations', 'sustainable-catalyst-feature-suggestions') . '</h2><ul>';
            foreach ($record['known_limitations'] as $item) {
                echo '<li>' . esc_html($item) . '</li>';
            }
            echo '</ul></section>';
        }
        $groups = array(
            __('Related documentation', 'sustainable-catalyst-feature-suggestions') => $record['related_articles'],
            __('Related known issues', 'sustainable-catalyst-feature-suggestions') => $record['related_known_issues'],
        );
        foreach ($groups as $title => $items) {
            if (!$items) {
                continue;
            }
            echo '<section class="scfs-release-related"><h2>' . esc_html($title) . '</h2><ul>';
            foreach ($items as $item) {
                echo '<li><a href="' . esc_url($item['url']) . '">' . esc_html($item['title']) . '</a></li>';
            }
            echo '</ul></section>';
        }
        if ($record['related_public_suggestions']) {
            echo '<section class="scfs-release-related"><h2>' . esc_html__('Related public ideas', 'sustainable-catalyst-feature-suggestions') . '</h2><ul>';
            foreach ($record['related_public_suggestions'] as $item) {
                echo '<li>' . esc_html($item['title']) . ' — ' . esc_html(ucwords(str_replace('_', ' ', $item['state']))) . '</li>';
            }
            echo '</ul><p class="description">' . esc_html__('Only moderated public idea metadata is shown. Private submission text and contact information are not exposed.', 'sustainable-catalyst-feature-suggestions') . '</p></section>';
        }
        return $content . ob_get_clean();
    }

    public function release_columns($columns) {
        $columns['scfs_release_status'] = __('Status', 'sustainable-catalyst-feature-suggestions');
        $columns['scfs_release_date'] = __('Release Date', 'sustainable-catalyst-feature-suggestions');
        $columns['scfs_release_products'] = __('Products', 'sustainable-catalyst-feature-suggestions');
        return $columns;
    }

    public function release_column_content($column, $post_id) {
        if ($column === 'scfs_release_status') {
            $status = get_post_meta($post_id, '_scfs_release_status', true) ?: 'planned';
            echo esc_html($this->release_statuses()[$status] ?? $status);
        } elseif ($column === 'scfs_release_date') {
            echo esc_html(get_post_meta($post_id, '_scfs_release_date', true) ?: '—');
        } elseif ($column === 'scfs_release_products') {
            echo esc_html(implode(', ', SCFS_Product_Integration::instance()->flat_term_names($post_id, SCFS_Product_Integration::PRODUCT_TAXONOMY)) ?: '—');
        }
    }

    public function register_admin_page() {
        add_submenu_page(
            'edit.php?post_type=' . Sustainable_Catalyst_Feature_Suggestions::POST_TYPE,
            __('Product Support and Feedback Platform', 'sustainable-catalyst-feature-suggestions'),
            __('Support Platform', 'sustainable-catalyst-feature-suggestions'),
            'edit_posts',
            'scfs-product-support-platform',
            array($this, 'render_admin_page')
        );
    }

    public function render_admin_page() {
        if (!current_user_can('edit_posts')) {
            wp_die(__('You do not have permission to manage the Product Support Platform.', 'sustainable-catalyst-feature-suggestions'));
        }
        $settings = $this->settings();
        $overview = $this->overview_record('');
        echo '<div class="wrap scfs-platform-center"><h1>' . esc_html__('Product Support and Feedback Platform', 'sustainable-catalyst-feature-suggestions') . '</h1><p>' . esc_html__('Unified public support, documentation, known issues, release intelligence, suggestions, voting, surveys, and privacy-bounded Contact and Engagement integration.', 'sustainable-catalyst-feature-suggestions') . '</p>';
        echo '<div class="scfs-intel-cards">';
        foreach ($overview['counts'] as $key => $value) {
            echo '<div class="scfs-intel-card"><span>' . esc_html(ucwords(str_replace('_', ' ', $key))) . '</span><strong>' . esc_html((string) $value) . '</strong></div>';
        }
        echo '</div><p><a class="button button-primary" href="' . esc_url(admin_url('post-new.php?post_type=' . self::RELEASE_POST_TYPE)) . '">' . esc_html__('Add Release Record', 'sustainable-catalyst-feature-suggestions') . '</a> <a class="button" href="' . esc_url(admin_url('edit.php?post_type=' . self::RELEASE_POST_TYPE)) . '">' . esc_html__('Manage Releases', 'sustainable-catalyst-feature-suggestions') . '</a></p>';
        echo '<h2>' . esc_html__('Public shortcode', 'sustainable-catalyst-feature-suggestions') . '</h2><p><code>[' . esc_html(self::SHORTCODE) . ']</code></p>';
        echo '<h2>' . esc_html__('Platform settings', 'sustainable-catalyst-feature-suggestions') . '</h2><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="scfs_save_product_support_settings">';
        wp_nonce_field('scfs_save_product_support_settings', 'scfs_product_support_nonce');
        echo '<table class="form-table"><tr><th>' . esc_html__('Support center title', 'sustainable-catalyst-feature-suggestions') . '</th><td><input class="regular-text" name="support_center_title" value="' . esc_attr($settings['support_center_title']) . '"></td></tr>';
        echo '<tr><th>' . esc_html__('Intro', 'sustainable-catalyst-feature-suggestions') . '</th><td><textarea class="large-text" rows="4" name="support_center_intro">' . esc_textarea($settings['support_center_intro']) . '</textarea></td></tr>';
        echo '<tr><th>' . esc_html__('Default section', 'sustainable-catalyst-feature-suggestions') . '</th><td><select name="default_view">';
        foreach ($this->navigation_items($settings) as $key => $item) {
            echo '<option value="' . esc_attr($key) . '" ' . selected($settings['default_view'], $key, false) . '>' . esc_html($item['label']) . '</option>';
        }
        echo '</select></td></tr><tr><th>' . esc_html__('Contact and Engagement URL', 'sustainable-catalyst-feature-suggestions') . '</th><td><input class="regular-text" type="url" name="contact_engagement_url" value="' . esc_attr($settings['contact_engagement_url']) . '"><p class="description">' . esc_html__('Private support case intake destination. Automatic case creation remains disabled.', 'sustainable-catalyst-feature-suggestions') . '</p></td></tr>';
        echo '<tr><th>' . esc_html__('Visible modules', 'sustainable-catalyst-feature-suggestions') . '</th><td><label><input type="checkbox" name="show_surveys" value="1" ' . checked($settings['show_surveys'], '1', false) . '> ' . esc_html__('Surveys', 'sustainable-catalyst-feature-suggestions') . '</label><br><label><input type="checkbox" name="show_public_ideas" value="1" ' . checked($settings['show_public_ideas'], '1', false) . '> ' . esc_html__('Public ideas and voting', 'sustainable-catalyst-feature-suggestions') . '</label><br><label><input type="checkbox" name="show_release_intelligence" value="1" ' . checked($settings['show_release_intelligence'], '1', false) . '> ' . esc_html__('Release intelligence', 'sustainable-catalyst-feature-suggestions') . '</label></td></tr>';
        echo '<tr><th>' . esc_html__('Featured release limit', 'sustainable-catalyst-feature-suggestions') . '</th><td><input type="number" min="1" max="12" name="featured_release_limit" value="' . esc_attr($settings['featured_release_limit']) . '"></td></tr></table><p><button class="button button-primary">' . esc_html__('Save support platform settings', 'sustainable-catalyst-feature-suggestions') . '</button></p></form>';
        echo '<h2>' . esc_html__('Integration boundary', 'sustainable-catalyst-feature-suggestions') . '</h2><pre>' . esc_html(wp_json_encode($this->handoff_schema(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) . '</pre></div>';
    }

    public function save_settings() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to update platform settings.', 'sustainable-catalyst-feature-suggestions'));
        }
        check_admin_referer('scfs_save_product_support_settings', 'scfs_product_support_nonce');
        $allowed_views = array_keys($this->navigation_items($this->settings()));
        $default_view = sanitize_key($_POST['default_view'] ?? 'overview');
        if (!in_array($default_view, $allowed_views, true)) {
            $default_view = 'overview';
        }
        update_option(self::OPTION_KEY, array(
            'support_center_title' => sanitize_text_field(wp_unslash($_POST['support_center_title'] ?? '')),
            'support_center_intro' => sanitize_textarea_field(wp_unslash($_POST['support_center_intro'] ?? '')),
            'default_view' => $default_view,
            'contact_engagement_url' => esc_url_raw(wp_unslash($_POST['contact_engagement_url'] ?? '')),
            'show_surveys' => isset($_POST['show_surveys']) ? '1' : '',
            'show_public_ideas' => isset($_POST['show_public_ideas']) ? '1' : '',
            'show_release_intelligence' => isset($_POST['show_release_intelligence']) ? '1' : '',
            'featured_release_limit' => min(12, max(1, absint($_POST['featured_release_limit'] ?? 4))),
        ), false);
        wp_safe_redirect(admin_url('edit.php?post_type=' . Sustainable_Catalyst_Feature_Suggestions::POST_TYPE . '&page=scfs-product-support-platform&updated=1'));
        exit;
    }

    public function snapshot() {
        return array(
            'ok' => true,
            'schema' => 'scfs-product-support-snapshot/' . self::SCHEMA_VERSION,
            'version' => self::VERSION,
            'generated_at' => gmdate('c'),
            'platform' => $this->schema_record(),
            'overview' => $this->overview_record(''),
            'product_taxonomy' => SCFS_Product_Integration::instance()->taxonomy_schema(),
            'knowledge_base' => SCFS_Knowledge_Base_Foundation::instance()->schema_record(),
            'guided_resolution' => SCFS_Guided_Resolution::instance()->schema_record(),
            'documentation_intelligence' => SCFS_Documentation_Feature_Intelligence::instance()->schema_record(),
            'contact_and_engagement_handoff' => $this->handoff_schema(),
            'human_review_required' => true,
        );
    }

    public function register_rest_routes() {
        $namespace = Sustainable_Catalyst_Feature_Suggestions::REST_NAMESPACE;
        register_rest_route($namespace, '/product-support/schema', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'rest_schema'),
            'permission_callback' => '__return_true',
        ));
        register_rest_route($namespace, '/product-support/overview', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'rest_overview'),
            'permission_callback' => '__return_true',
        ));
        register_rest_route($namespace, '/product-support/releases', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'rest_releases'),
            'permission_callback' => '__return_true',
        ));
        register_rest_route($namespace, '/product-support/products', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'rest_products'),
            'permission_callback' => '__return_true',
        ));
        register_rest_route($namespace, '/product-support/handoff-schema', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'rest_handoff_schema'),
            'permission_callback' => '__return_true',
        ));
        register_rest_route($namespace, '/product-support/snapshot', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'rest_snapshot'),
            'permission_callback' => function () { return current_user_can('edit_posts'); },
        ));
    }

    public function rest_schema() {
        return rest_ensure_response($this->schema_record());
    }

    public function rest_overview($request) {
        return rest_ensure_response($this->overview_record(sanitize_title($request->get_param('product'))));
    }

    public function rest_releases($request) {
        $product = sanitize_title($request->get_param('product'));
        $status = sanitize_key($request->get_param('status'));
        $limit = min(100, max(1, absint($request->get_param('limit') ?: 20)));
        $query = $this->release_query($product, $limit, $status);
        return rest_ensure_response(array(
            'schema' => 'scfs-release-intelligence-list/' . self::SCHEMA_VERSION,
            'version' => self::VERSION,
            'items' => array_values(array_map(function ($post) { return $this->release_record($post, false); }, $query->posts)),
            'public_records_only' => true,
        ));
    }

    public function rest_products() {
        $terms = get_terms(array('taxonomy' => SCFS_Product_Integration::PRODUCT_TAXONOMY, 'hide_empty' => false, 'orderby' => 'name'));
        if (is_wp_error($terms)) {
            $terms = array();
        }
        $items = array();
        foreach ($terms as $term) {
            $items[] = array(
                'id' => (int) $term->term_id,
                'slug' => $term->slug,
                'name' => $term->name,
                'overview' => $this->overview_record($term->slug),
            );
        }
        return rest_ensure_response(array('schema' => 'scfs-product-support-products/' . self::SCHEMA_VERSION, 'items' => $items));
    }

    public function rest_handoff_schema() {
        return rest_ensure_response($this->handoff_schema());
    }

    public function rest_snapshot() {
        return rest_ensure_response($this->snapshot());
    }
}
