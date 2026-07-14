<?php
/**
 * Documentation and Feature Intelligence: article usefulness feedback,
 * documentation-gap detection, privacy-safe case relationships, and
 * support-demand signals for opportunity scoring.
 *
 * @package Sustainable_Catalyst_Feature_Suggestions
 */

if (!defined('ABSPATH')) {
    exit;
}

final class SCFS_Documentation_Feature_Intelligence {
    const VERSION = '3.4.0';
    const SCHEMA_VERSION = '1.0';
    const DB_VERSION_OPTION = 'scfs_documentation_intelligence_db_version';
    const OPTION_KEY = 'scfs_documentation_intelligence_settings';
    const FEEDBACK_TABLE_SUFFIX = 'scfs_article_feedback';
    const RELATIONSHIP_TABLE_SUFFIX = 'scfs_support_relationships';
    const GAP_POST_TYPE = 'sc_doc_gap';
    const FEEDBACK_NONCE_ACTION = 'scfs_submit_article_feedback';
    const GAP_NONCE_ACTION = 'scfs_save_documentation_gap';
    const GAP_REFRESH_HOOK = 'scfs_refresh_documentation_gaps_event';

    private static $instance = null;

    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('init', array($this, 'register_gap_post_type'), 7);
        add_action('init', array($this, 'register_meta'), 10);
        add_action('wp_enqueue_scripts', array($this, 'register_assets'));
        add_action('admin_enqueue_scripts', array($this, 'admin_assets'));
        add_filter('the_content', array($this, 'append_article_feedback'), 35);
        add_action('admin_post_scfs_submit_article_feedback', array($this, 'handle_article_feedback'));
        add_action('admin_post_nopriv_scfs_submit_article_feedback', array($this, 'handle_article_feedback'));
        add_action('admin_post_scfs_refresh_documentation_gaps', array($this, 'handle_refresh_gaps'));
        add_action('admin_post_scfs_save_documentation_intelligence_settings', array($this, 'save_settings'));
        add_action('admin_post_scfs_export_documentation_intelligence', array($this, 'export_intelligence'));
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post_' . self::GAP_POST_TYPE, array($this, 'save_gap'), 20, 2);
        add_action('admin_menu', array($this, 'register_admin_page'), 29);
        add_action('rest_api_init', array($this, 'register_rest_routes'));
        add_action('init', array($this, 'maybe_upgrade'), 6);
        add_action(self::GAP_REFRESH_HOOK, array($this, 'refresh_documentation_gaps'));
        add_action('scfs_guided_resolution_search_recorded', array($this, 'schedule_gap_refresh'), 10, 3);
        add_filter('manage_' . self::GAP_POST_TYPE . '_posts_columns', array($this, 'gap_columns'));
        add_action('manage_' . self::GAP_POST_TYPE . '_posts_custom_column', array($this, 'gap_column_content'), 10, 2);
    }

    public static function activate() {
        $instance = self::instance();
        $instance->register_gap_post_type();
        $instance->register_meta();
        $instance->create_tables();
        if (!get_option(self::OPTION_KEY)) {
            add_option(self::OPTION_KEY, $instance->default_settings(), '', false);
        }
        update_option(self::DB_VERSION_OPTION, self::SCHEMA_VERSION, false);
    }

    public function maybe_upgrade() {
        if ((string) get_option(self::DB_VERSION_OPTION, '') !== self::SCHEMA_VERSION) {
            $this->create_tables();
            update_option(self::DB_VERSION_OPTION, self::SCHEMA_VERSION, false);
        }
    }

    private function default_settings() {
        return array(
            'minimum_gap_searches' => '2',
            'gap_candidate_score' => '35',
            'feedback_comment_enabled' => '1',
            'feedback_retention_days' => '730',
            'relationship_retention_days' => '1825',
            'negative_feedback_weight' => '7',
            'case_relationship_weight' => '10',
        );
    }

    private function settings() {
        $saved = get_option(self::OPTION_KEY, array());
        return wp_parse_args(is_array($saved) ? $saved : array(), $this->default_settings());
    }

    public function feedback_table_name() {
        global $wpdb;
        return isset($wpdb->prefix) ? $wpdb->prefix . self::FEEDBACK_TABLE_SUFFIX : self::FEEDBACK_TABLE_SUFFIX;
    }

    public function relationship_table_name() {
        global $wpdb;
        return isset($wpdb->prefix) ? $wpdb->prefix . self::RELATIONSHIP_TABLE_SUFFIX : self::RELATIONSHIP_TABLE_SUFFIX;
    }

    public function create_tables() {
        global $wpdb;
        if (!isset($wpdb->prefix)) {
            return;
        }
        $charset = method_exists($wpdb, 'get_charset_collate') ? $wpdb->get_charset_collate() : '';
        $feedback = $this->feedback_table_name();
        $relationships = $this->relationship_table_name();
        $feedback_sql = "CREATE TABLE {$feedback} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            feedback_uuid char(36) NOT NULL,
            article_id bigint(20) unsigned NOT NULL,
            helpful tinyint(1) NOT NULL DEFAULT 0,
            reason varchar(64) NOT NULL DEFAULT '',
            comment_text varchar(1000) NOT NULL DEFAULT '',
            comment_hash char(64) NOT NULL DEFAULT '',
            search_id bigint(20) unsigned NOT NULL DEFAULT 0,
            product varchar(100) NOT NULL DEFAULT '',
            product_version varchar(100) NOT NULL DEFAULT '',
            component varchar(100) NOT NULL DEFAULT '',
            source varchar(32) NOT NULL DEFAULT 'article',
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY feedback_uuid (feedback_uuid),
            KEY article_id (article_id),
            KEY helpful (helpful),
            KEY search_id (search_id),
            KEY created_at (created_at)
        ) {$charset};";
        $relationship_sql = "CREATE TABLE {$relationships} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            relationship_uuid char(36) NOT NULL,
            external_case_reference varchar(191) NOT NULL,
            external_case_hash char(64) NOT NULL,
            relationship_type varchar(32) NOT NULL,
            object_id bigint(20) unsigned NOT NULL,
            source_system varchar(64) NOT NULL DEFAULT 'contact_and_engagement',
            outcome varchar(32) NOT NULL DEFAULT 'linked',
            product varchar(100) NOT NULL DEFAULT '',
            product_version varchar(100) NOT NULL DEFAULT '',
            component varchar(100) NOT NULL DEFAULT '',
            evidence_weight decimal(5,2) NOT NULL DEFAULT 1,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY relationship_uuid (relationship_uuid),
            UNIQUE KEY case_object (external_case_hash, relationship_type, object_id),
            KEY relationship_type (relationship_type),
            KEY object_id (object_id),
            KEY outcome (outcome),
            KEY updated_at (updated_at)
        ) {$charset};";
        if (defined('ABSPATH') && file_exists(ABSPATH . 'wp-admin/includes/upgrade.php')) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }
        if (function_exists('dbDelta')) {
            dbDelta($feedback_sql);
            dbDelta($relationship_sql);
        } elseif (method_exists($wpdb, 'query')) {
            $wpdb->query($feedback_sql); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $wpdb->query($relationship_sql); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        }
    }

    public function register_gap_post_type() {
        register_post_type(self::GAP_POST_TYPE, array(
            'labels' => array(
                'name' => __('Documentation Gaps', 'sustainable-catalyst-feature-suggestions'),
                'singular_name' => __('Documentation Gap', 'sustainable-catalyst-feature-suggestions'),
                'menu_name' => __('Documentation Gaps', 'sustainable-catalyst-feature-suggestions'),
                'add_new_item' => __('Add Documentation Gap', 'sustainable-catalyst-feature-suggestions'),
                'edit_item' => __('Review Documentation Gap', 'sustainable-catalyst-feature-suggestions'),
                'search_items' => __('Search Documentation Gaps', 'sustainable-catalyst-feature-suggestions'),
                'not_found' => __('No documentation gaps found.', 'sustainable-catalyst-feature-suggestions'),
            ),
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => 'edit.php?post_type=' . Sustainable_Catalyst_Feature_Suggestions::POST_TYPE,
            'show_in_rest' => false,
            'supports' => array('title', 'editor'),
            'capability_type' => 'post',
            'map_meta_cap' => true,
            'menu_icon' => 'dashicons-search',
        ));
    }

    public function gap_statuses() {
        return array(
            'open' => __('Open', 'sustainable-catalyst-feature-suggestions'),
            'planned' => __('Planned', 'sustainable-catalyst-feature-suggestions'),
            'documented' => __('Documented', 'sustainable-catalyst-feature-suggestions'),
            'dismissed' => __('Dismissed', 'sustainable-catalyst-feature-suggestions'),
        );
    }

    public function feedback_reasons() {
        return array(
            'resolved' => __('It resolved my issue', 'sustainable-catalyst-feature-suggestions'),
            'clear' => __('It was clear and complete', 'sustainable-catalyst-feature-suggestions'),
            'outdated' => __('The information appears outdated', 'sustainable-catalyst-feature-suggestions'),
            'missing_steps' => __('Important steps are missing', 'sustainable-catalyst-feature-suggestions'),
            'did_not_match' => __('It did not match my situation', 'sustainable-catalyst-feature-suggestions'),
            'unclear' => __('The explanation was unclear', 'sustainable-catalyst-feature-suggestions'),
            'other' => __('Other', 'sustainable-catalyst-feature-suggestions'),
        );
    }

    public function register_meta() {
        $string_meta = array(
            '_scfs_gap_key', '_scfs_gap_query_hash', '_scfs_gap_query_text', '_scfs_gap_product',
            '_scfs_gap_product_version', '_scfs_gap_component', '_scfs_gap_status',
            '_scfs_gap_first_seen', '_scfs_gap_last_seen', '_scfs_gap_recommendation',
        );
        foreach ($string_meta as $key) {
            register_post_meta(self::GAP_POST_TYPE, $key, array(
                'type' => 'string',
                'single' => true,
                'show_in_rest' => false,
                'sanitize_callback' => in_array($key, array('_scfs_gap_query_text', '_scfs_gap_recommendation'), true) ? 'sanitize_textarea_field' : 'sanitize_text_field',
                'auth_callback' => function () { return current_user_can('edit_posts'); },
            ));
        }
        foreach (array('_scfs_gap_search_count', '_scfs_gap_no_match_count', '_scfs_gap_low_confidence_count', '_scfs_gap_negative_feedback_count', '_scfs_gap_case_count', '_scfs_gap_linked_article_id', '_scfs_gap_linked_suggestion_id') as $key) {
            register_post_meta(self::GAP_POST_TYPE, $key, array(
                'type' => 'integer', 'single' => true, 'show_in_rest' => false,
                'sanitize_callback' => 'absint',
                'auth_callback' => function () { return current_user_can('edit_posts'); },
            ));
        }
        register_post_meta(self::GAP_POST_TYPE, '_scfs_gap_score', array(
            'type' => 'number', 'single' => true, 'show_in_rest' => false,
            'sanitize_callback' => array($this, 'sanitize_score'),
            'auth_callback' => function () { return current_user_can('edit_posts'); },
        ));
        register_post_meta(self::GAP_POST_TYPE, '_scfs_gap_evidence', array(
            'type' => 'object', 'single' => true, 'show_in_rest' => false,
            'sanitize_callback' => array($this, 'sanitize_evidence'),
            'auth_callback' => function () { return current_user_can('edit_posts'); },
        ));
    }

    public function sanitize_score($value) {
        return max(0, min(100, (float) $value));
    }

    public function sanitize_evidence($value) {
        return is_array($value) ? $value : array();
    }

    public function register_assets() {
        wp_register_style(
            'scfs-documentation-intelligence',
            plugins_url('../assets/documentation-intelligence.css', __FILE__),
            array(),
            self::VERSION
        );
    }

    public function admin_assets($hook) {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (($screen && in_array($screen->post_type, array(self::GAP_POST_TYPE, SCFS_Knowledge_Base_Foundation::ARTICLE_POST_TYPE), true)) || strpos((string) $hook, 'scfs-documentation-intelligence') !== false) {
            wp_enqueue_style('scfs-admin', plugins_url('../assets/feature-suggestions-admin.css', __FILE__), array(), self::VERSION);
        }
    }

    public function schema_record() {
        return array(
            'schema' => 'scfs-documentation-feature-intelligence/' . self::SCHEMA_VERSION,
            'version' => self::VERSION,
            'capabilities' => array(
                'article_feedback',
                'failed_search_analytics',
                'documentation_gap_detection',
                'case_to_article_relationships',
                'case_to_suggestion_relationships',
                'support_demand_opportunity_scoring',
            ),
            'records' => array(
                'documentation_gap' => self::GAP_POST_TYPE,
                'article_feedback_table' => self::FEEDBACK_TABLE_SUFFIX,
                'support_relationship_table' => self::RELATIONSHIP_TABLE_SUFFIX,
            ),
            'relationship_types' => array('case_article', 'case_suggestion'),
            'privacy' => array(
                'ip_address_stored' => false,
                'contact_fields_collected' => false,
                'contact_details_prohibited' => true,
                'free_text_common_contact_patterns_redacted' => true,
                'case_content_stored' => false,
                'external_case_reference_must_be_opaque' => true,
                'human_review_required' => true,
            ),
        );
    }

    private function redact_feedback_text($text) {
        $text = sanitize_textarea_field((string) $text);
        $patterns = array(
            '/\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}\b/i' => '[email removed]',
            '/\b(?:api[_ -]?key|password|secret|token)\s*[:=]\s*\S+/i' => '[secret removed]',
            '/\b(?:\d[ -]*?){13,16}\b/' => '[number removed]',
            '/(?<![A-Za-z0-9])(?:\+?1[ .-]?)?(?:\(?\d{3}\)?[ .-]?)\d{3}[ .-]?\d{4}(?![A-Za-z0-9])/' => '[phone removed]',
        );
        return substr((string) preg_replace(array_keys($patterns), array_values($patterns), $text), 0, 1000);
    }

    public function append_article_feedback($content) {
        if (!is_singular(SCFS_Knowledge_Base_Foundation::ARTICLE_POST_TYPE) || !in_the_loop() || !is_main_query()) {
            return $content;
        }
        $article_id = get_the_ID();
        $settings = $this->settings();
        $status = isset($_GET['scfs_feedback']) ? sanitize_key(wp_unslash($_GET['scfs_feedback'])) : '';
        wp_enqueue_style('scfs-documentation-intelligence');
        ob_start();
        echo '<aside class="scfs-article-feedback" aria-labelledby="scfs-article-feedback-title">';
        echo '<p class="scfs-resolution-eyebrow">' . esc_html__('Documentation feedback', 'sustainable-catalyst-feature-suggestions') . '</p>';
        echo '<h2 id="scfs-article-feedback-title">' . esc_html__('Was this article helpful?', 'sustainable-catalyst-feature-suggestions') . '</h2>';
        echo '<p>' . esc_html__('Your response improves documentation and product planning. Do not include contact details, credentials, private logs, or sensitive information.', 'sustainable-catalyst-feature-suggestions') . '</p>';
        if ($status === 'received') {
            echo '<div class="scfs-feedback-notice" role="status">' . esc_html__('Thank you. Your documentation feedback was recorded.', 'sustainable-catalyst-feature-suggestions') . '</div>';
        } elseif ($status === 'duplicate') {
            echo '<div class="scfs-feedback-notice" role="status">' . esc_html__('Your recent response for this article is already recorded.', 'sustainable-catalyst-feature-suggestions') . '</div>';
        }
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="scfs_submit_article_feedback"><input type="hidden" name="article_id" value="' . esc_attr((string) $article_id) . '">';
        echo '<input type="hidden" name="search_id" value="' . esc_attr(isset($_GET['scfs_search_id']) ? absint($_GET['scfs_search_id']) : 0) . '">';
        echo '<input class="scfs-feedback-honeypot" type="text" name="website" value="" tabindex="-1" autocomplete="off" aria-hidden="true">';
        wp_nonce_field(self::FEEDBACK_NONCE_ACTION . '_' . $article_id, 'scfs_feedback_nonce');
        echo '<div class="scfs-feedback-choice"><button type="submit" name="helpful" value="1">' . esc_html__('Yes, it helped', 'sustainable-catalyst-feature-suggestions') . '</button><button type="submit" name="helpful" value="0">' . esc_html__('No, not yet', 'sustainable-catalyst-feature-suggestions') . '</button></div>';
        echo '<label><span>' . esc_html__('Reason', 'sustainable-catalyst-feature-suggestions') . '</span><select name="reason"><option value="">' . esc_html__('Select an optional reason', 'sustainable-catalyst-feature-suggestions') . '</option>';
        foreach ($this->feedback_reasons() as $key => $label) {
            echo '<option value="' . esc_attr($key) . '">' . esc_html($label) . '</option>';
        }
        echo '</select></label>';
        if ($settings['feedback_comment_enabled'] === '1') {
            echo '<label><span>' . esc_html__('What should be improved?', 'sustainable-catalyst-feature-suggestions') . '</span><textarea name="comment" maxlength="1000" rows="3" placeholder="Keep this brief and non-sensitive."></textarea></label>';
        }
        echo '</form></aside>';
        return $content . ob_get_clean();
    }

    private function feedback_session_token() {
        $cookie = isset($_COOKIE['scfs_feedback_session']) ? sanitize_text_field(wp_unslash($_COOKIE['scfs_feedback_session'])) : '';
        if (!preg_match('/^[a-f0-9]{32}$/', $cookie)) {
            $cookie = bin2hex(random_bytes(16));
            if (!headers_sent()) {
                setcookie('scfs_feedback_session', $cookie, time() + (defined('YEAR_IN_SECONDS') ? YEAR_IN_SECONDS : 31536000), (defined('COOKIEPATH') && COOKIEPATH ? COOKIEPATH : '/'), (defined('COOKIE_DOMAIN') ? COOKIE_DOMAIN : ''), function_exists('is_ssl') ? is_ssl() : false, true);
            }
        }
        return $cookie;
    }

    private function store_feedback($data) {
        $article_id = absint(isset($data['article_id']) ? $data['article_id'] : 0);
        if (!$article_id || get_post_type($article_id) !== SCFS_Knowledge_Base_Foundation::ARTICLE_POST_TYPE || get_post_status($article_id) !== 'publish') {
            return new WP_Error('scfs_invalid_article', __('A published support article is required.', 'sustainable-catalyst-feature-suggestions'), array('status' => 400));
        }
        $helpful = !empty($data['helpful']) ? 1 : 0;
        $reason = sanitize_key(isset($data['reason']) ? $data['reason'] : '');
        if ($reason !== '' && !isset($this->feedback_reasons()[$reason])) {
            $reason = 'other';
        }
        $comment = $this->redact_feedback_text(isset($data['comment']) ? $data['comment'] : '');
        $search_id = absint(isset($data['search_id']) ? $data['search_id'] : 0);
        $source = sanitize_key(isset($data['source']) ? $data['source'] : 'article');
        $session = isset($data['session']) ? sanitize_text_field($data['session']) : '';
        if ($session === '') $session = $this->feedback_session_token();
        $dedupe_key = 'scfs_feedback_dedupe_' . hash('sha256', $article_id . '|' . $session);
        if (get_transient($dedupe_key)) {
            return new WP_Error('scfs_feedback_duplicate', __('A recent response for this article is already recorded.', 'sustainable-catalyst-feature-suggestions'), array('status' => 409));
        }
        $product_context = class_exists('SCFS_Product_Integration') ? SCFS_Product_Integration::instance()->product_context($article_id) : array();
        $first_slug = function ($items) {
            if (!is_array($items) || !$items) return '';
            $first = reset($items);
            return is_array($first) && isset($first['slug']) ? sanitize_title($first['slug']) : '';
        };
        global $wpdb;
        if (!isset($wpdb->prefix) || !method_exists($wpdb, 'insert')) {
            return new WP_Error('scfs_feedback_unavailable', __('Feedback storage is unavailable.', 'sustainable-catalyst-feature-suggestions'), array('status' => 503));
        }
        $uuid = wp_generate_uuid4();
        $inserted = $wpdb->insert($this->feedback_table_name(), array(
            'feedback_uuid' => $uuid,
            'article_id' => $article_id,
            'helpful' => $helpful,
            'reason' => $reason,
            'comment_text' => $comment,
            'comment_hash' => $comment !== '' ? hash('sha256', strtolower($comment)) : '',
            'search_id' => $search_id,
            'product' => $first_slug(isset($product_context['products']) ? $product_context['products'] : array()),
            'product_version' => $first_slug(isset($product_context['versions']) ? $product_context['versions'] : array()),
            'component' => $first_slug(isset($product_context['components']) ? $product_context['components'] : array()),
            'source' => $source ?: 'article',
            'created_at' => current_time('mysql', true),
        ), array('%s', '%d', '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s'));
        if (!$inserted) {
            return new WP_Error('scfs_feedback_insert_failed', __('The feedback record could not be saved.', 'sustainable-catalyst-feature-suggestions'), array('status' => 500));
        }
        set_transient($dedupe_key, 1, 600);
        $this->update_article_feedback_meta($article_id);
        if (!$helpful) $this->schedule_gap_refresh();
        do_action('scfs_event', array(
            'schema_version' => Sustainable_Catalyst_Feature_Suggestions::EVENT_SCHEMA_VERSION,
            'event_type' => 'documentation.feedback_recorded',
            'source' => 'feature_suggestions',
            'article_id' => $article_id,
            'helpful' => (bool) $helpful,
            'reason' => $reason,
            'timestamp' => gmdate('c'),
        ));
        return array('feedback_uuid' => $uuid, 'article_id' => $article_id, 'helpful' => (bool) $helpful, 'recorded_at' => gmdate('c'));
    }

    public function handle_article_feedback() {
        $article_id = isset($_POST['article_id']) ? absint($_POST['article_id']) : 0;
        if (!isset($_POST['scfs_feedback_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['scfs_feedback_nonce'])), self::FEEDBACK_NONCE_ACTION . '_' . $article_id)) {
            wp_die(__('The documentation feedback form expired. Please return to the article and try again.', 'sustainable-catalyst-feature-suggestions'));
        }
        if (!empty($_POST['website'])) {
            wp_safe_redirect(get_permalink($article_id));
            exit;
        }
        $result = $this->store_feedback(array(
            'article_id' => $article_id,
            'helpful' => isset($_POST['helpful']) ? absint($_POST['helpful']) : 0,
            'reason' => isset($_POST['reason']) ? sanitize_key(wp_unslash($_POST['reason'])) : '',
            'comment' => isset($_POST['comment']) ? wp_unslash($_POST['comment']) : '',
            'search_id' => isset($_POST['search_id']) ? absint($_POST['search_id']) : 0,
            'source' => 'article',
        ));
        $status = is_wp_error($result) && $result->get_error_code() === 'scfs_feedback_duplicate' ? 'duplicate' : (is_wp_error($result) ? 'error' : 'received');
        wp_safe_redirect(add_query_arg('scfs_feedback', $status, get_permalink($article_id)) . '#scfs-article-feedback-title');
        exit;
    }

    public function article_feedback_summary($article_id) {
        global $wpdb;
        $summary = array('article_id' => absint($article_id), 'total' => 0, 'helpful' => 0, 'unhelpful' => 0, 'helpfulness_rate' => null, 'reasons' => array());
        if (!isset($wpdb->prefix) || !method_exists($wpdb, 'get_results')) {
            return $summary;
        }
        $rows = $wpdb->get_results($wpdb->prepare('SELECT helpful, reason, COUNT(*) AS responses FROM ' . $this->feedback_table_name() . ' WHERE article_id = %d GROUP BY helpful, reason', absint($article_id)), ARRAY_A); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        foreach ((array) $rows as $row) {
            $count = absint($row['responses']);
            $summary['total'] += $count;
            if ((int) $row['helpful'] === 1) $summary['helpful'] += $count; else $summary['unhelpful'] += $count;
            $reason = sanitize_key($row['reason']);
            if ($reason !== '') $summary['reasons'][$reason] = ($summary['reasons'][$reason] ?? 0) + $count;
        }
        $summary['helpfulness_rate'] = $summary['total'] ? round($summary['helpful'] / $summary['total'], 4) : null;
        arsort($summary['reasons']);
        return $summary;
    }

    private function update_article_feedback_meta($article_id) {
        $summary = $this->article_feedback_summary($article_id);
        update_post_meta($article_id, '_scfs_feedback_helpful_count', $summary['helpful']);
        update_post_meta($article_id, '_scfs_feedback_unhelpful_count', $summary['unhelpful']);
        update_post_meta($article_id, '_scfs_feedback_helpfulness_rate', $summary['helpfulness_rate']);
        update_post_meta($article_id, '_scfs_feedback_last_received', gmdate('c'));
    }

    private function guided_events() {
        global $wpdb;
        if (!class_exists('SCFS_Guided_Resolution') || !isset($wpdb->prefix) || !method_exists($wpdb, 'get_results')) {
            return array();
        }
        $table = SCFS_Guided_Resolution::instance()->table_name();
        return (array) $wpdb->get_results("SELECT id, query_text, query_hash, error_hash, filters_json, resolution_state, confidence, result_count, viewed_json, created_at FROM {$table} WHERE event_type = 'search' AND resolution_state IN ('no_match','low_confidence') ORDER BY id ASC", ARRAY_A); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    }

    private function negative_feedback_by_article() {
        global $wpdb;
        if (!isset($wpdb->prefix) || !method_exists($wpdb, 'get_results')) return array();
        $rows = $wpdb->get_results('SELECT article_id, COUNT(*) AS responses FROM ' . $this->feedback_table_name() . ' WHERE helpful = 0 GROUP BY article_id', ARRAY_A); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $result = array();
        foreach ((array) $rows as $row) $result[absint($row['article_id'])] = absint($row['responses']);
        return $result;
    }

    private function case_counts($relationship_type, $object_id) {
        global $wpdb;
        if (!isset($wpdb->prefix) || !method_exists($wpdb, 'get_var')) return 0;
        return (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . $this->relationship_table_name() . ' WHERE relationship_type = %s AND object_id = %d', sanitize_key($relationship_type), absint($object_id))); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
    }

    /**
     * Return an aggregate relationship count without exposing private case identifiers.
     */
    public function relationship_count($relationship_type, $object_id) {
        if (!in_array($relationship_type, array('case_article', 'case_suggestion'), true)) {
            return 0;
        }
        return $this->case_counts($relationship_type, $object_id);
    }

    public function calculate_gap_score($evidence) {
        $searches = max(0, absint(isset($evidence['search_count']) ? $evidence['search_count'] : 0));
        $no_match = max(0, absint(isset($evidence['no_match_count']) ? $evidence['no_match_count'] : 0));
        $low = max(0, absint(isset($evidence['low_confidence_count']) ? $evidence['low_confidence_count'] : 0));
        $negative = max(0, absint(isset($evidence['negative_feedback_count']) ? $evidence['negative_feedback_count'] : 0));
        $cases = max(0, absint(isset($evidence['case_count']) ? $evidence['case_count'] : 0));
        $settings = $this->settings();
        $pressure = min(40, log(1 + $searches, 2) * 12);
        $no_match_pressure = $searches ? min(25, ($no_match / $searches) * 25) : 0;
        $low_pressure = $searches ? min(10, ($low / $searches) * 10) : 0;
        $feedback_pressure = min(20, $negative * max(1, absint($settings['negative_feedback_weight'])));
        $case_pressure = min(25, $cases * max(1, absint($settings['case_relationship_weight'])));
        return round(max(0, min(100, $pressure + $no_match_pressure + $low_pressure + $feedback_pressure + $case_pressure)), 1);
    }


    public function schedule_gap_refresh() {
        if (function_exists('wp_next_scheduled') && function_exists('wp_schedule_single_event') && !wp_next_scheduled(self::GAP_REFRESH_HOOK)) {
            wp_schedule_single_event(time() + 60, self::GAP_REFRESH_HOOK);
        }
    }

    public function refresh_documentation_gaps() {
        $aggregates = array();
        foreach ($this->guided_events() as $row) {
            $query_text = sanitize_text_field($row['query_text']);
            $hash = $query_text !== '' ? sanitize_text_field($row['query_hash']) : sanitize_text_field(isset($row['error_hash']) ? $row['error_hash'] : '');
            if ($hash === '') continue;
            $key = ($query_text !== '' ? 'search:' : 'error:') . $hash;
            $filters = json_decode((string) $row['filters_json'], true);
            $filters = is_array($filters) ? $filters : array();
            if (!isset($aggregates[$key])) {
                $aggregates[$key] = array(
                    'key' => $key,
                    'query_hash' => $hash,
                    'query_text' => $query_text !== '' ? $query_text : sprintf(__('Repeated unresolved error signature %s', 'sustainable-catalyst-feature-suggestions'), substr($hash, 0, 10)),
                    'product' => sanitize_title(isset($filters['product']) ? $filters['product'] : ''),
                    'product_version' => sanitize_title(isset($filters['product_version']) ? $filters['product_version'] : ''),
                    'component' => sanitize_title(isset($filters['component']) ? $filters['component'] : ''),
                    'search_count' => 0, 'no_match_count' => 0, 'low_confidence_count' => 0,
                    'negative_feedback_count' => 0, 'case_count' => 0,
                    'first_seen' => $row['created_at'], 'last_seen' => $row['created_at'],
                    'linked_article_id' => 0, 'linked_suggestion_id' => 0,
                );
            }
            $aggregates[$key]['search_count']++;
            if ($row['resolution_state'] === 'no_match') $aggregates[$key]['no_match_count']++;
            if ($row['resolution_state'] === 'low_confidence') $aggregates[$key]['low_confidence_count']++;
            $aggregates[$key]['last_seen'] = $row['created_at'];
        }
        foreach ($this->negative_feedback_by_article() as $article_id => $count) {
            $key = 'article:' . $article_id;
            $aggregates[$key] = array(
                'key' => $key,
                'query_hash' => hash('sha256', $key),
                'query_text' => sprintf(__('Improve support article: %s', 'sustainable-catalyst-feature-suggestions'), get_the_title($article_id)),
                'product' => '', 'product_version' => '', 'component' => '',
                'search_count' => 0, 'no_match_count' => 0, 'low_confidence_count' => 0,
                'negative_feedback_count' => $count,
                'case_count' => $this->case_counts('case_article', $article_id),
                'first_seen' => gmdate('Y-m-d H:i:s'), 'last_seen' => gmdate('Y-m-d H:i:s'),
                'linked_article_id' => $article_id, 'linked_suggestion_id' => 0,
            );
        }
        $settings = $this->settings();
        $minimum_searches = max(1, absint($settings['minimum_gap_searches']));
        $created = 0; $updated = 0; $skipped = 0;
        foreach ($aggregates as $gap) {
            if ($gap['search_count'] > 0 && $gap['search_count'] < $minimum_searches) {
                $skipped++;
                continue;
            }
            $score = $this->calculate_gap_score($gap);
            $existing = get_posts(array(
                'post_type' => self::GAP_POST_TYPE,
                'post_status' => array('publish', 'draft', 'pending', 'private'),
                'posts_per_page' => 1,
                'fields' => 'ids',
                'meta_key' => '_scfs_gap_key',
                'meta_value' => $gap['key'],
                'no_found_rows' => true,
            ));
            $post_id = $existing ? absint($existing[0]) : 0;
            $title = $gap['query_text'] ?: __('Unresolved documentation need', 'sustainable-catalyst-feature-suggestions');
            $postarr = array(
                'ID' => $post_id,
                'post_type' => self::GAP_POST_TYPE,
                'post_status' => 'publish',
                'post_title' => wp_trim_words($title, 18, '…'),
                'post_content' => sprintf(
                    "Evidence-generated documentation gap.\n\nSearches: %d\nNo matches: %d\nLow-confidence matches: %d\nNegative article responses: %d\nLinked private cases: %d",
                    $gap['search_count'], $gap['no_match_count'], $gap['low_confidence_count'], $gap['negative_feedback_count'], $gap['case_count']
                ),
            );
            $post_id = $post_id ? wp_update_post($postarr, true) : wp_insert_post($postarr, true);
            if (is_wp_error($post_id) || !$post_id) {
                $skipped++;
                continue;
            }
            $status = get_post_meta($post_id, '_scfs_gap_status', true);
            if (!isset($this->gap_statuses()[$status])) $status = 'open';
            $meta = array(
                '_scfs_gap_key' => $gap['key'], '_scfs_gap_query_hash' => $gap['query_hash'], '_scfs_gap_query_text' => $gap['query_text'],
                '_scfs_gap_product' => $gap['product'], '_scfs_gap_product_version' => $gap['product_version'], '_scfs_gap_component' => $gap['component'],
                '_scfs_gap_search_count' => $gap['search_count'], '_scfs_gap_no_match_count' => $gap['no_match_count'], '_scfs_gap_low_confidence_count' => $gap['low_confidence_count'],
                '_scfs_gap_negative_feedback_count' => $gap['negative_feedback_count'], '_scfs_gap_case_count' => $gap['case_count'], '_scfs_gap_score' => $score,
                '_scfs_gap_status' => $status, '_scfs_gap_first_seen' => $gap['first_seen'], '_scfs_gap_last_seen' => $gap['last_seen'],
                '_scfs_gap_linked_article_id' => $gap['linked_article_id'], '_scfs_gap_linked_suggestion_id' => $gap['linked_suggestion_id'],
                '_scfs_gap_evidence' => $gap,
            );
            foreach ($meta as $key => $value) update_post_meta($post_id, $key, $value);
            if ($existing) $updated++; else $created++;
        }
        return array('created' => $created, 'updated' => $updated, 'skipped' => $skipped, 'considered' => count($aggregates), 'refreshed_at' => gmdate('c'));
    }

    public function handle_refresh_gaps() {
        if (!current_user_can('edit_posts')) wp_die(__('Permission denied.', 'sustainable-catalyst-feature-suggestions'));
        check_admin_referer('scfs_refresh_documentation_gaps');
        $result = $this->refresh_documentation_gaps();
        set_transient('scfs_gap_refresh_result_' . get_current_user_id(), $result, 120);
        wp_safe_redirect(admin_url('edit.php?post_type=' . Sustainable_Catalyst_Feature_Suggestions::POST_TYPE . '&page=scfs-documentation-intelligence&gaps-refreshed=1'));
        exit;
    }

    public function add_meta_boxes() {
        add_meta_box('scfs-documentation-gap-evidence', __('Documentation gap evidence', 'sustainable-catalyst-feature-suggestions'), array($this, 'render_gap_meta_box'), self::GAP_POST_TYPE, 'normal', 'high');
        add_meta_box('scfs-documentation-gap-links', __('Resolution links', 'sustainable-catalyst-feature-suggestions'), array($this, 'render_gap_links_meta_box'), self::GAP_POST_TYPE, 'side', 'default');
        add_meta_box('scfs-article-feedback-summary', __('Article feedback intelligence', 'sustainable-catalyst-feature-suggestions'), array($this, 'render_article_feedback_meta_box'), SCFS_Knowledge_Base_Foundation::ARTICLE_POST_TYPE, 'side', 'default');
        add_meta_box('scfs-support-demand', __('Support demand intelligence', 'sustainable-catalyst-feature-suggestions'), array($this, 'render_suggestion_demand_meta_box'), Sustainable_Catalyst_Feature_Suggestions::POST_TYPE, 'side', 'default');
    }

    public function render_gap_meta_box($post) {
        wp_nonce_field(self::GAP_NONCE_ACTION, 'scfs_gap_nonce');
        $evidence = get_post_meta($post->ID, '_scfs_gap_evidence', true);
        $score = get_post_meta($post->ID, '_scfs_gap_score', true);
        echo '<p><strong>' . esc_html__('Gap score:', 'sustainable-catalyst-feature-suggestions') . '</strong> ' . esc_html((string) $score) . ' / 100</p>';
        echo '<pre style="white-space:pre-wrap">' . esc_html(wp_json_encode(is_array($evidence) ? $evidence : array(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) . '</pre>';
    }

    public function render_gap_links_meta_box($post) {
        $status = get_post_meta($post->ID, '_scfs_gap_status', true) ?: 'open';
        $article = absint(get_post_meta($post->ID, '_scfs_gap_linked_article_id', true));
        $suggestion = absint(get_post_meta($post->ID, '_scfs_gap_linked_suggestion_id', true));
        $recommendation = get_post_meta($post->ID, '_scfs_gap_recommendation', true);
        echo '<p><label><strong>' . esc_html__('Status', 'sustainable-catalyst-feature-suggestions') . '</strong><br><select class="widefat" name="scfs_gap_status">';
        foreach ($this->gap_statuses() as $key => $label) echo '<option value="' . esc_attr($key) . '" ' . selected($status, $key, false) . '>' . esc_html($label) . '</option>';
        echo '</select></label></p>';
        echo '<p><label><strong>' . esc_html__('Linked support article ID', 'sustainable-catalyst-feature-suggestions') . '</strong><br><input class="widefat" type="number" min="0" name="scfs_gap_article_id" value="' . esc_attr((string) $article) . '"></label></p>';
        echo '<p><label><strong>' . esc_html__('Linked feature suggestion ID', 'sustainable-catalyst-feature-suggestions') . '</strong><br><input class="widefat" type="number" min="0" name="scfs_gap_suggestion_id" value="' . esc_attr((string) $suggestion) . '"></label></p>';
        echo '<p><label><strong>' . esc_html__('Recommended response', 'sustainable-catalyst-feature-suggestions') . '</strong><br><textarea class="widefat" rows="4" name="scfs_gap_recommendation">' . esc_textarea($recommendation) . '</textarea></label></p>';
    }

    public function render_article_feedback_meta_box($post) {
        $summary = $this->article_feedback_summary($post->ID);
        $rate = $summary['helpfulness_rate'] === null ? '—' : number_format_i18n($summary['helpfulness_rate'] * 100, 1) . '%';
        echo '<p><strong>' . esc_html__('Responses:', 'sustainable-catalyst-feature-suggestions') . '</strong> ' . esc_html((string) $summary['total']) . '</p>';
        echo '<p><strong>' . esc_html__('Helpful:', 'sustainable-catalyst-feature-suggestions') . '</strong> ' . esc_html((string) $summary['helpful']) . '<br><strong>' . esc_html__('Needs improvement:', 'sustainable-catalyst-feature-suggestions') . '</strong> ' . esc_html((string) $summary['unhelpful']) . '<br><strong>' . esc_html__('Helpfulness rate:', 'sustainable-catalyst-feature-suggestions') . '</strong> ' . esc_html($rate) . '</p>';
        echo '<p><strong>' . esc_html__('Related private support cases:', 'sustainable-catalyst-feature-suggestions') . '</strong> ' . esc_html((string) $this->relationship_count('case_article', $post->ID)) . '</p>';
        echo '<p class="description">' . esc_html__('Only the aggregate relationship count is shown here. Private case identifiers and case content remain in Contact and Engagement.', 'sustainable-catalyst-feature-suggestions') . '</p>';
        if ($summary['reasons']) echo '<details><summary>' . esc_html__('Reasons', 'sustainable-catalyst-feature-suggestions') . '</summary><pre>' . esc_html(wp_json_encode($summary['reasons'], JSON_PRETTY_PRINT)) . '</pre></details>';
    }

    public function render_suggestion_demand_meta_box($post) {
        $demand = $this->support_demand_for_suggestion($post->ID);
        echo '<p><strong>' . esc_html__('Support-demand score:', 'sustainable-catalyst-feature-suggestions') . '</strong> ' . esc_html((string) $demand['score']) . ' / 5</p>';
        echo '<ul><li>' . esc_html(sprintf(__('Private case relationships: %d', 'sustainable-catalyst-feature-suggestions'), $demand['case_relationships'])) . '</li><li>' . esc_html(sprintf(__('Linked documentation gaps: %d', 'sustainable-catalyst-feature-suggestions'), $demand['gap_count'])) . '</li><li>' . esc_html(sprintf(__('Unresolved-search evidence: %d', 'sustainable-catalyst-feature-suggestions'), $demand['unresolved_searches'])) . '</li><li>' . esc_html(sprintf(__('Guided-result views: %d', 'sustainable-catalyst-feature-suggestions'), $demand['guided_views'])) . '</li></ul>';
        echo '<p class="description">' . esc_html__('Support demand is advisory evidence. It does not automatically approve or schedule a feature.', 'sustainable-catalyst-feature-suggestions') . '</p>';
    }

    public function save_gap($post_id, $post) {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!$post || !isset($_POST['scfs_gap_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['scfs_gap_nonce'])), self::GAP_NONCE_ACTION)) return;
        if (!current_user_can('edit_post', $post_id)) return;
        $status = sanitize_key(isset($_POST['scfs_gap_status']) ? wp_unslash($_POST['scfs_gap_status']) : 'open');
        if (!isset($this->gap_statuses()[$status])) $status = 'open';
        update_post_meta($post_id, '_scfs_gap_status', $status);
        update_post_meta($post_id, '_scfs_gap_linked_article_id', isset($_POST['scfs_gap_article_id']) ? absint($_POST['scfs_gap_article_id']) : 0);
        update_post_meta($post_id, '_scfs_gap_linked_suggestion_id', isset($_POST['scfs_gap_suggestion_id']) ? absint($_POST['scfs_gap_suggestion_id']) : 0);
        update_post_meta($post_id, '_scfs_gap_recommendation', isset($_POST['scfs_gap_recommendation']) ? sanitize_textarea_field(wp_unslash($_POST['scfs_gap_recommendation'])) : '');
    }

    private function valid_case_reference($value) {
        $value = sanitize_text_field((string) $value);
        return preg_match('/^[A-Za-z0-9._:-]{1,191}$/', $value) ? $value : '';
    }

    public function store_case_relationship($data) {
        $case_reference = $this->valid_case_reference(isset($data['case_reference']) ? $data['case_reference'] : '');
        $type = sanitize_key(isset($data['relationship_type']) ? $data['relationship_type'] : '');
        $object_id = absint(isset($data['object_id']) ? $data['object_id'] : 0);
        if ($case_reference === '' || !in_array($type, array('case_article', 'case_suggestion'), true) || !$object_id) {
            return new WP_Error('scfs_invalid_relationship', __('An opaque case reference, supported relationship type, and object ID are required.', 'sustainable-catalyst-feature-suggestions'), array('status' => 400));
        }
        $expected_type = $type === 'case_article' ? SCFS_Knowledge_Base_Foundation::ARTICLE_POST_TYPE : Sustainable_Catalyst_Feature_Suggestions::POST_TYPE;
        if (get_post_type($object_id) !== $expected_type) {
            return new WP_Error('scfs_relationship_object_mismatch', __('The relationship object does not match the requested type.', 'sustainable-catalyst-feature-suggestions'), array('status' => 400));
        }
        $source = sanitize_key(isset($data['source_system']) ? $data['source_system'] : 'contact_and_engagement');
        $outcome = sanitize_key(isset($data['outcome']) ? $data['outcome'] : 'linked');
        if (!in_array($outcome, array('linked', 'viewed', 'resolved', 'unresolved', 'escalated'), true)) $outcome = 'linked';
        $hash = hash('sha256', strtolower($source . '|' . $case_reference));
        $uuid = isset($data['relationship_uuid']) && preg_match('/^[a-f0-9-]{36}$/i', $data['relationship_uuid']) ? strtolower($data['relationship_uuid']) : wp_generate_uuid4();
        $weight = max(0.1, min(5, (float) (isset($data['evidence_weight']) ? $data['evidence_weight'] : 1)));
        $now = current_time('mysql', true);
        global $wpdb;
        if (!isset($wpdb->prefix) || !method_exists($wpdb, 'query')) return new WP_Error('scfs_relationship_storage_unavailable', __('Relationship storage is unavailable.', 'sustainable-catalyst-feature-suggestions'), array('status' => 503));
        $sql = $wpdb->prepare(
            'INSERT INTO ' . $this->relationship_table_name() . ' (relationship_uuid, external_case_reference, external_case_hash, relationship_type, object_id, source_system, outcome, product, product_version, component, evidence_weight, created_at, updated_at) VALUES (%s,%s,%s,%s,%d,%s,%s,%s,%s,%s,%f,%s,%s) ON DUPLICATE KEY UPDATE outcome = VALUES(outcome), product = VALUES(product), product_version = VALUES(product_version), component = VALUES(component), evidence_weight = VALUES(evidence_weight), updated_at = VALUES(updated_at)',
            $uuid, $case_reference, $hash, $type, $object_id, $source, $outcome,
            sanitize_title(isset($data['product']) ? $data['product'] : ''), sanitize_title(isset($data['product_version']) ? $data['product_version'] : ''), sanitize_title(isset($data['component']) ? $data['component'] : ''),
            $weight, $now, $now
        );
        $wpdb->query($sql); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $this->schedule_gap_refresh();
        do_action('scfs_event', array(
            'schema_version' => Sustainable_Catalyst_Feature_Suggestions::EVENT_SCHEMA_VERSION,
            'event_type' => 'support.relationship_recorded',
            'source' => 'feature_suggestions',
            'relationship_type' => $type,
            'object_id' => $object_id,
            'outcome' => $outcome,
            'timestamp' => gmdate('c'),
        ));
        return array('relationship_uuid' => $uuid, 'case_reference' => $case_reference, 'relationship_type' => $type, 'object_id' => $object_id, 'outcome' => $outcome, 'updated_at' => gmdate('c'));
    }

    private function guided_suggestion_views($suggestion_id) {
        global $wpdb;
        if (!class_exists('SCFS_Guided_Resolution') || !isset($wpdb->prefix) || !method_exists($wpdb, 'get_var')) return 0;
        $needle = '%"kind":"public_suggestion","id":' . absint($suggestion_id) . '%';
        return (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . SCFS_Guided_Resolution::instance()->table_name() . ' WHERE viewed_json LIKE %s', $needle)); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
    }

    public function support_demand_for_suggestion($suggestion_id) {
        $suggestion_id = absint($suggestion_id);
        $case_count = $this->case_counts('case_suggestion', $suggestion_id);
        $gap_ids = get_posts(array(
            'post_type' => self::GAP_POST_TYPE,
            'post_status' => array('publish', 'draft', 'pending', 'private'),
            'posts_per_page' => -1,
            'fields' => 'ids',
            'meta_key' => '_scfs_gap_linked_suggestion_id',
            'meta_value' => $suggestion_id,
            'no_found_rows' => true,
        ));
        $gap_count = count($gap_ids);
        $unresolved = 0;
        $gap_score_total = 0;
        foreach ($gap_ids as $gap_id) {
            $unresolved += absint(get_post_meta($gap_id, '_scfs_gap_search_count', true));
            $gap_score_total += (float) get_post_meta($gap_id, '_scfs_gap_score', true);
        }
        $views = $this->guided_suggestion_views($suggestion_id);
        $score = min(5,
            min(2.5, log(1 + $case_count, 2) * 1.25) +
            min(1.5, log(1 + $unresolved, 2) * 0.45) +
            min(0.75, $gap_score_total / 250) +
            min(0.75, log(1 + $views, 2) * 0.25)
        );
        return array(
            'score' => round($score, 2),
            'case_relationships' => $case_count,
            'gap_count' => $gap_count,
            'unresolved_searches' => $unresolved,
            'gap_score_total' => round($gap_score_total, 1),
            'guided_views' => $views,
            'evidence_count' => ($case_count > 0 ? 1 : 0) + ($gap_count > 0 ? 1 : 0) + ($unresolved > 0 ? 1 : 0) + ($views > 0 ? 1 : 0),
        );
    }

    public function analytics_summary() {
        global $wpdb;
        $summary = array(
            'feedback_total' => 0, 'helpful' => 0, 'unhelpful' => 0, 'helpfulness_rate' => null,
            'case_relationships' => 0, 'case_article_relationships' => 0, 'case_suggestion_relationships' => 0,
            'open_gaps' => 0, 'high_priority_gaps' => 0, 'top_articles' => array(), 'recent_relationships' => array(), 'top_gaps' => array(),
        );
        if (isset($wpdb->prefix) && method_exists($wpdb, 'get_var')) {
            $summary['feedback_total'] = (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . $this->feedback_table_name()); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $summary['helpful'] = (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . $this->feedback_table_name() . ' WHERE helpful = 1'); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $summary['unhelpful'] = (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . $this->feedback_table_name() . ' WHERE helpful = 0'); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $summary['case_relationships'] = (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . $this->relationship_table_name()); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $summary['case_article_relationships'] = (int) $wpdb->get_var("SELECT COUNT(*) FROM " . $this->relationship_table_name() . " WHERE relationship_type = 'case_article'"); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $summary['case_suggestion_relationships'] = (int) $wpdb->get_var("SELECT COUNT(*) FROM " . $this->relationship_table_name() . " WHERE relationship_type = 'case_suggestion'"); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            if (method_exists($wpdb, 'get_results')) {
                $summary['top_articles'] = (array) $wpdb->get_results('SELECT article_id, COUNT(*) AS responses, SUM(helpful) AS helpful FROM ' . $this->feedback_table_name() . ' GROUP BY article_id ORDER BY responses DESC LIMIT 10', ARRAY_A); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
                $summary['recent_relationships'] = (array) $wpdb->get_results('SELECT relationship_uuid, external_case_reference, relationship_type, object_id, source_system, outcome, updated_at FROM ' . $this->relationship_table_name() . ' ORDER BY updated_at DESC LIMIT 20', ARRAY_A); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            }
        }
        $settings = $this->settings();
        $gap_ids = get_posts(array('post_type' => self::GAP_POST_TYPE, 'post_status' => array('publish','draft','pending','private'), 'posts_per_page' => -1, 'fields' => 'ids', 'no_found_rows' => true));
        foreach ($gap_ids as $gap_id) {
            $status = get_post_meta($gap_id, '_scfs_gap_status', true) ?: 'open';
            $score = (float) get_post_meta($gap_id, '_scfs_gap_score', true);
            if ($status === 'open') $summary['open_gaps']++;
            if ($status === 'open' && $score >= (float) $settings['gap_candidate_score']) $summary['high_priority_gaps']++;
            $summary['top_gaps'][] = array('id' => $gap_id, 'title' => get_the_title($gap_id), 'score' => $score, 'status' => $status, 'searches' => absint(get_post_meta($gap_id, '_scfs_gap_search_count', true)), 'negative_feedback' => absint(get_post_meta($gap_id, '_scfs_gap_negative_feedback_count', true)), 'edit_url' => get_edit_post_link($gap_id, 'raw'));
        }
        usort($summary['top_gaps'], function ($a, $b) { return $b['score'] <=> $a['score']; });
        $summary['top_gaps'] = array_slice($summary['top_gaps'], 0, 20);
        $summary['helpfulness_rate'] = $summary['feedback_total'] ? round($summary['helpful'] / $summary['feedback_total'], 4) : null;
        return $summary;
    }

    public function register_admin_page() {
        add_submenu_page(
            'edit.php?post_type=' . Sustainable_Catalyst_Feature_Suggestions::POST_TYPE,
            __('Documentation and Feature Intelligence', 'sustainable-catalyst-feature-suggestions'),
            __('Documentation Intelligence', 'sustainable-catalyst-feature-suggestions'),
            'edit_posts',
            'scfs-documentation-intelligence',
            array($this, 'render_admin_page')
        );
    }

    public function render_admin_page() {
        if (!current_user_can('edit_posts')) wp_die(__('Permission denied.', 'sustainable-catalyst-feature-suggestions'));
        $summary = $this->analytics_summary();
        $settings = $this->settings();
        $refresh = wp_nonce_url(admin_url('admin-post.php?action=scfs_refresh_documentation_gaps'), 'scfs_refresh_documentation_gaps');
        $export = wp_nonce_url(admin_url('admin-post.php?action=scfs_export_documentation_intelligence'), 'scfs_export_documentation_intelligence');
        echo '<div class="wrap scfs-documentation-intelligence-admin"><h1>' . esc_html__('Documentation and Feature Intelligence', 'sustainable-catalyst-feature-suggestions') . '</h1><p>' . esc_html__('Connect public documentation feedback, unresolved searches, privacy-safe support-case relationships, and feature opportunity scoring. Private case content and contact details remain in Contact and Engagement.', 'sustainable-catalyst-feature-suggestions') . '</p>';
        if (!empty($_GET['gaps-refreshed'])) echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Documentation gaps refreshed from current evidence.', 'sustainable-catalyst-feature-suggestions') . '</p></div>';
        echo '<div class="scfs-intel-cards">';
        $cards = array(
            'feedback_total' => __('Article responses', 'sustainable-catalyst-feature-suggestions'),
            'helpfulness_rate' => __('Helpfulness rate', 'sustainable-catalyst-feature-suggestions'),
            'unhelpful' => __('Needs improvement', 'sustainable-catalyst-feature-suggestions'),
            'open_gaps' => __('Open gaps', 'sustainable-catalyst-feature-suggestions'),
            'high_priority_gaps' => __('Priority gaps', 'sustainable-catalyst-feature-suggestions'),
            'case_relationships' => __('Case relationships', 'sustainable-catalyst-feature-suggestions'),
        );
        foreach ($cards as $key => $label) {
            $value = $key === 'helpfulness_rate' ? ($summary[$key] === null ? '—' : number_format_i18n($summary[$key] * 100, 1) . '%') : $summary[$key];
            echo '<div class="scfs-intel-card"><span>' . esc_html($label) . '</span><strong>' . esc_html((string) $value) . '</strong></div>';
        }
        echo '</div><p><a class="button button-primary" href="' . esc_url($refresh) . '">' . esc_html__('Refresh documentation gaps', 'sustainable-catalyst-feature-suggestions') . '</a> <a class="button" href="' . esc_url($export) . '">' . esc_html__('Export intelligence CSV', 'sustainable-catalyst-feature-suggestions') . '</a></p>';
        echo '<h2>' . esc_html__('Highest-priority documentation gaps', 'sustainable-catalyst-feature-suggestions') . '</h2><table class="widefat striped"><thead><tr><th>Gap</th><th>Score</th><th>Searches</th><th>Negative feedback</th><th>Status</th></tr></thead><tbody>';
        if (!$summary['top_gaps']) echo '<tr><td colspan="5">' . esc_html__('No documentation gaps have been generated yet.', 'sustainable-catalyst-feature-suggestions') . '</td></tr>';
        foreach ($summary['top_gaps'] as $gap) echo '<tr><td><a href="' . esc_url($gap['edit_url']) . '"><strong>' . esc_html($gap['title']) . '</strong></a></td><td>' . esc_html((string) $gap['score']) . '</td><td>' . esc_html((string) $gap['searches']) . '</td><td>' . esc_html((string) $gap['negative_feedback']) . '</td><td>' . esc_html($this->gap_statuses()[$gap['status']] ?? $gap['status']) . '</td></tr>';
        echo '</tbody></table><h2>' . esc_html__('Most-evaluated articles', 'sustainable-catalyst-feature-suggestions') . '</h2><table class="widefat striped"><thead><tr><th>Article</th><th>Responses</th><th>Helpful</th></tr></thead><tbody>';
        if (!$summary['top_articles']) echo '<tr><td colspan="3">' . esc_html__('No article feedback yet.', 'sustainable-catalyst-feature-suggestions') . '</td></tr>';
        foreach ($summary['top_articles'] as $row) echo '<tr><td><a href="' . esc_url(get_edit_post_link(absint($row['article_id']), 'raw')) . '">' . esc_html(get_the_title(absint($row['article_id']))) . '</a></td><td>' . esc_html((string) $row['responses']) . '</td><td>' . esc_html((string) $row['helpful']) . '</td></tr>';
        echo '</tbody></table><h2>' . esc_html__('Intelligence settings', 'sustainable-catalyst-feature-suggestions') . '</h2><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="scfs_save_documentation_intelligence_settings">';
        wp_nonce_field('scfs_save_documentation_intelligence_settings');
        echo '<table class="form-table"><tbody>';
        echo '<tr><th>Minimum unresolved searches</th><td><input type="number" min="1" max="100" name="minimum_gap_searches" value="' . esc_attr($settings['minimum_gap_searches']) . '"></td></tr>';
        echo '<tr><th>Priority gap threshold</th><td><input type="number" min="0" max="100" name="gap_candidate_score" value="' . esc_attr($settings['gap_candidate_score']) . '"></td></tr>';
        echo '<tr><th>Article comments</th><td><label><input type="checkbox" name="feedback_comment_enabled" value="1" ' . checked($settings['feedback_comment_enabled'], '1', false) . '> Allow optional non-sensitive improvement comments</label></td></tr>';
        echo '<tr><th>Feedback retention</th><td><input type="number" min="30" max="3650" name="feedback_retention_days" value="' . esc_attr($settings['feedback_retention_days']) . '"> days</td></tr>';
        echo '<tr><th>Relationship retention</th><td><input type="number" min="30" max="3650" name="relationship_retention_days" value="' . esc_attr($settings['relationship_retention_days']) . '"> days</td></tr>';
        echo '</tbody></table><p><button class="button button-primary" type="submit">' . esc_html__('Save intelligence settings', 'sustainable-catalyst-feature-suggestions') . '</button></p></form>';
        echo '<h2>' . esc_html__('Integration endpoints', 'sustainable-catalyst-feature-suggestions') . '</h2><p><code>/wp-json/' . esc_html(Sustainable_Catalyst_Feature_Suggestions::REST_NAMESPACE) . '/documentation-intelligence/relationships</code></p><p class="description">' . esc_html__('Contact and Engagement should send only an opaque case reference, relationship type, public record ID, product context, and outcome. Do not send contact details or case narrative.', 'sustainable-catalyst-feature-suggestions') . '</p></div>';
    }

    public function save_settings() {
        if (!current_user_can('edit_posts')) wp_die(__('Permission denied.', 'sustainable-catalyst-feature-suggestions'));
        check_admin_referer('scfs_save_documentation_intelligence_settings');
        $settings = array(
            'minimum_gap_searches' => (string) max(1, min(100, absint(isset($_POST['minimum_gap_searches']) ? $_POST['minimum_gap_searches'] : 2))),
            'gap_candidate_score' => (string) max(0, min(100, absint(isset($_POST['gap_candidate_score']) ? $_POST['gap_candidate_score'] : 35))),
            'feedback_comment_enabled' => !empty($_POST['feedback_comment_enabled']) ? '1' : '0',
            'feedback_retention_days' => (string) max(30, min(3650, absint(isset($_POST['feedback_retention_days']) ? $_POST['feedback_retention_days'] : 730))),
            'relationship_retention_days' => (string) max(30, min(3650, absint(isset($_POST['relationship_retention_days']) ? $_POST['relationship_retention_days'] : 1825))),
            'negative_feedback_weight' => '7',
            'case_relationship_weight' => '10',
        );
        update_option(self::OPTION_KEY, $settings, false);
        $this->purge_old_records($settings);
        wp_safe_redirect(admin_url('edit.php?post_type=' . Sustainable_Catalyst_Feature_Suggestions::POST_TYPE . '&page=scfs-documentation-intelligence&settings-updated=1'));
        exit;
    }

    private function purge_old_records($settings) {
        global $wpdb;
        if (!isset($wpdb->prefix) || !method_exists($wpdb, 'query')) return;
        $wpdb->query($wpdb->prepare('DELETE FROM ' . $this->feedback_table_name() . ' WHERE created_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL %d DAY)', absint($settings['feedback_retention_days']))); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $wpdb->query($wpdb->prepare('DELETE FROM ' . $this->relationship_table_name() . ' WHERE updated_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL %d DAY)', absint($settings['relationship_retention_days']))); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
    }

    public function register_rest_routes() {
        $namespace = Sustainable_Catalyst_Feature_Suggestions::REST_NAMESPACE;
        register_rest_route($namespace, '/documentation-intelligence/schema', array('methods' => 'GET', 'callback' => array($this, 'rest_schema'), 'permission_callback' => '__return_true'));
        register_rest_route($namespace, '/knowledge-base/articles/(?P<id>\d+)/feedback', array('methods' => 'POST', 'callback' => array($this, 'rest_submit_feedback'), 'permission_callback' => '__return_true'));
        register_rest_route($namespace, '/knowledge-base/articles/(?P<id>\d+)/feedback-summary', array('methods' => 'GET', 'callback' => array($this, 'rest_feedback_summary'), 'permission_callback' => '__return_true'));
        register_rest_route($namespace, '/documentation-intelligence/gaps', array('methods' => 'GET', 'callback' => array($this, 'rest_gaps'), 'permission_callback' => array($this, 'rest_admin_permission')));
        register_rest_route($namespace, '/documentation-intelligence/gaps/refresh', array('methods' => 'POST', 'callback' => array($this, 'rest_refresh_gaps'), 'permission_callback' => array($this, 'rest_admin_permission')));
        register_rest_route($namespace, '/documentation-intelligence/relationships', array(
            array('methods' => 'GET', 'callback' => array($this, 'rest_relationships'), 'permission_callback' => array($this, 'rest_admin_permission')),
            array('methods' => 'POST', 'callback' => array($this, 'rest_store_relationship'), 'permission_callback' => array($this, 'rest_admin_permission')),
        ));
        register_rest_route($namespace, '/documentation-intelligence/analytics', array('methods' => 'GET', 'callback' => array($this, 'rest_analytics'), 'permission_callback' => array($this, 'rest_admin_permission')));
        register_rest_route($namespace, '/suggestions/(?P<id>\d+)/support-demand', array('methods' => 'GET', 'callback' => array($this, 'rest_support_demand'), 'permission_callback' => array($this, 'rest_admin_permission')));
    }

    public function rest_admin_permission() {
        return current_user_can('edit_posts');
    }

    public function rest_schema() { return rest_ensure_response($this->schema_record()); }

    public function rest_submit_feedback($request) {
        $result = $this->store_feedback(array(
            'article_id' => absint($request['id']),
            'helpful' => filter_var($request->get_param('helpful'), FILTER_VALIDATE_BOOLEAN),
            'reason' => sanitize_key($request->get_param('reason')),
            'comment' => $request->get_param('comment'),
            'search_id' => absint($request->get_param('search_id')),
            'source' => 'rest',
            'session' => sanitize_text_field($request->get_header('X-SCFS-Feedback-Session')),
        ));
        return is_wp_error($result) ? $result : rest_ensure_response($result);
    }

    public function rest_feedback_summary($request) { return rest_ensure_response($this->article_feedback_summary(absint($request['id']))); }

    public function gap_record($gap_id) {
        return array(
            'id' => absint($gap_id), 'title' => get_the_title($gap_id),
            'query' => get_post_meta($gap_id, '_scfs_gap_query_text', true),
            'product_context' => array('product' => get_post_meta($gap_id, '_scfs_gap_product', true), 'product_version' => get_post_meta($gap_id, '_scfs_gap_product_version', true), 'component' => get_post_meta($gap_id, '_scfs_gap_component', true)),
            'score' => (float) get_post_meta($gap_id, '_scfs_gap_score', true),
            'status' => get_post_meta($gap_id, '_scfs_gap_status', true) ?: 'open',
            'evidence' => get_post_meta($gap_id, '_scfs_gap_evidence', true),
            'linked_article_id' => absint(get_post_meta($gap_id, '_scfs_gap_linked_article_id', true)),
            'linked_suggestion_id' => absint(get_post_meta($gap_id, '_scfs_gap_linked_suggestion_id', true)),
            'recommendation' => get_post_meta($gap_id, '_scfs_gap_recommendation', true),
        );
    }

    public function rest_gaps() {
        $ids = get_posts(array('post_type' => self::GAP_POST_TYPE, 'post_status' => array('publish','draft','pending','private'), 'posts_per_page' => -1, 'fields' => 'ids', 'no_found_rows' => true));
        return rest_ensure_response(array('items' => array_map(array($this, 'gap_record'), $ids), 'count' => count($ids)));
    }

    public function rest_refresh_gaps() { return rest_ensure_response($this->refresh_documentation_gaps()); }

    public function rest_store_relationship($request) {
        $context = $request->get_param('product_context');
        $context = is_array($context) ? $context : array();
        $result = $this->store_case_relationship(array(
            'relationship_uuid' => $request->get_param('relationship_uuid'),
            'case_reference' => $request->get_param('case_reference'),
            'relationship_type' => $request->get_param('relationship_type'),
            'object_id' => $request->get_param('object_id'),
            'source_system' => $request->get_param('source_system'),
            'outcome' => $request->get_param('outcome'),
            'evidence_weight' => $request->get_param('evidence_weight'),
            'product' => isset($context['product']) ? $context['product'] : '',
            'product_version' => isset($context['product_version']) ? $context['product_version'] : '',
            'component' => isset($context['component']) ? $context['component'] : '',
        ));
        return is_wp_error($result) ? $result : rest_ensure_response($result);
    }

    public function rest_relationships($request) {
        global $wpdb;
        if (!isset($wpdb->prefix) || !method_exists($wpdb, 'get_results')) return rest_ensure_response(array('items' => array(), 'count' => 0));
        $case_reference = $this->valid_case_reference($request->get_param('case_reference'));
        if ($case_reference !== '') {
            $source = sanitize_key($request->get_param('source_system') ?: 'contact_and_engagement');
            $hash = hash('sha256', strtolower($source . '|' . $case_reference));
            $rows = $wpdb->get_results($wpdb->prepare('SELECT relationship_uuid, external_case_reference, relationship_type, object_id, source_system, outcome, product, product_version, component, evidence_weight, created_at, updated_at FROM ' . $this->relationship_table_name() . ' WHERE external_case_hash = %s ORDER BY updated_at DESC', $hash), ARRAY_A); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        } else {
            $rows = $wpdb->get_results('SELECT relationship_uuid, external_case_reference, relationship_type, object_id, source_system, outcome, product, product_version, component, evidence_weight, created_at, updated_at FROM ' . $this->relationship_table_name() . ' ORDER BY updated_at DESC LIMIT 200', ARRAY_A); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        }
        return rest_ensure_response(array('items' => (array) $rows, 'count' => count((array) $rows)));
    }

    public function rest_analytics() { return rest_ensure_response($this->analytics_summary()); }

    public function rest_support_demand($request) { return rest_ensure_response($this->support_demand_for_suggestion(absint($request['id']))); }

    public function export_intelligence() {
        if (!current_user_can('edit_posts')) wp_die(__('Permission denied.', 'sustainable-catalyst-feature-suggestions'));
        check_admin_referer('scfs_export_documentation_intelligence');
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=scfs-documentation-intelligence-' . gmdate('Y-m-d') . '.csv');
        $out = fopen('php://output', 'w');
        fputcsv($out, array('Record Type', 'ID', 'Title/Case', 'Relationship/Status', 'Score/Helpful', 'Object/Search Count', 'Updated'));
        foreach ($this->analytics_summary()['top_gaps'] as $gap) fputcsv($out, array('documentation_gap', $gap['id'], $gap['title'], $gap['status'], $gap['score'], $gap['searches'], ''));
        global $wpdb;
        if (isset($wpdb->prefix) && method_exists($wpdb, 'get_results')) {
            $feedback = $wpdb->get_results('SELECT id, article_id, helpful, reason, created_at FROM ' . $this->feedback_table_name() . ' ORDER BY id DESC', ARRAY_A); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            foreach ((array) $feedback as $row) fputcsv($out, array('article_feedback', $row['id'], get_the_title(absint($row['article_id'])), $row['reason'], $row['helpful'], $row['article_id'], $row['created_at']));
            $relationships = $wpdb->get_results('SELECT relationship_uuid, external_case_reference, relationship_type, object_id, outcome, updated_at FROM ' . $this->relationship_table_name() . ' ORDER BY id DESC', ARRAY_A); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            foreach ((array) $relationships as $row) fputcsv($out, array('case_relationship', $row['relationship_uuid'], $row['external_case_reference'], $row['relationship_type'] . ':' . $row['outcome'], '', $row['object_id'], $row['updated_at']));
        }
        fclose($out);
        exit;
    }

    public function gap_columns($columns) {
        $columns['scfs_gap_score'] = __('Gap score', 'sustainable-catalyst-feature-suggestions');
        $columns['scfs_gap_evidence'] = __('Evidence', 'sustainable-catalyst-feature-suggestions');
        $columns['scfs_gap_status'] = __('Status', 'sustainable-catalyst-feature-suggestions');
        return $columns;
    }

    public function gap_column_content($column, $post_id) {
        if ($column === 'scfs_gap_score') echo '<strong>' . esc_html((string) get_post_meta($post_id, '_scfs_gap_score', true)) . '</strong> / 100';
        if ($column === 'scfs_gap_evidence') echo esc_html(sprintf(__('%d searches · %d negative responses', 'sustainable-catalyst-feature-suggestions'), absint(get_post_meta($post_id, '_scfs_gap_search_count', true)), absint(get_post_meta($post_id, '_scfs_gap_negative_feedback_count', true))));
        if ($column === 'scfs_gap_status') { $status = get_post_meta($post_id, '_scfs_gap_status', true) ?: 'open'; echo esc_html($this->gap_statuses()[$status] ?? $status); }
    }
}
