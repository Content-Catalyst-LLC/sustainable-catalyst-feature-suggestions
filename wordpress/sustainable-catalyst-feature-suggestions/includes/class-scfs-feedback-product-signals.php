<?php
/**
 * Feedback Intelligence and Product Signals.
 *
 * Aggregates privacy-minimized evidence from feature suggestions, public votes,
 * Support Article feedback, Guided Resolution outcomes, Documentation Gaps,
 * Known Issues, and release context. The resulting signals are advisory and do
 * not automatically change roadmap, issue, release, or publication state.
 *
 * @package Sustainable_Catalyst_Feature_Suggestions
 */

if (!defined('ABSPATH')) {
    exit;
}

final class SCFS_Feedback_Product_Signals {
    const VERSION = '5.6.0';
    const SCHEMA = 'scfs-feedback-product-signals/1.0';
    const ADMIN_PAGE = 'scfs-feedback-product-signals';
    const SETTINGS_OPTION = 'scfs_feedback_product_signal_settings';
    const SNAPSHOT_OPTION = 'scfs_feedback_product_signal_snapshot';
    const LAST_REFRESH_OPTION = 'scfs_feedback_product_signal_last_refresh';
    const SCHEMA_OPTION = 'scfs_feedback_product_signal_schema';
    const CRON_HOOK = 'scfs_feedback_product_signal_daily';
    const CRON_LOCK = 'scfs_feedback_product_signal_lock';
    const NONCE_ACTION = 'scfs_feedback_product_signals';
    const NONCE_NAME = 'scfs_feedback_product_signals_nonce';
    const SHORTCODE = 'scfs_feedback_product_signals';

    private static $instance = null;

    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_menu', array($this, 'register_admin_page'), 32);
        add_action('admin_enqueue_scripts', array($this, 'admin_assets'));
        add_action('admin_post_scfs_feedback_product_signals_refresh', array($this, 'refresh_action'));
        add_action('admin_post_scfs_feedback_product_signals_export', array($this, 'export_action'));
        add_action('rest_api_init', array($this, 'register_rest_routes'));
        add_action(self::CRON_HOOK, array($this, 'run_scheduled_refresh'));
        add_action('scfs_guided_resolution_search_recorded', array($this, 'invalidate_snapshot'), 20, 3);
        add_action('scfs_event', array($this, 'invalidate_snapshot'), 20, 1);
        add_action('save_post', array($this, 'invalidate_snapshot_for_post'), 120, 3);
        add_shortcode(self::SHORTCODE, array($this, 'render_shortcode'));

        if (defined('WP_CLI') && WP_CLI && class_exists('WP_CLI')) {
            WP_CLI::add_command('scfs product-signals refresh', array($this, 'cli_refresh'));
            WP_CLI::add_command('scfs product-signals summary', array($this, 'cli_summary'));
            WP_CLI::add_command('scfs product-signals product', array($this, 'cli_product'));
        }
    }

    public static function activate() {
        $instance = self::instance();
        if (!get_option(self::SETTINGS_OPTION)) {
            add_option(self::SETTINGS_OPTION, $instance->default_settings(), '', false);
        }
        update_option(self::SCHEMA_OPTION, self::SCHEMA, false);
        if (function_exists('wp_next_scheduled') && function_exists('wp_schedule_event') && !wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK);
        }
    }

    public static function deactivate() {
        if (function_exists('wp_next_scheduled')) {
            $timestamp = wp_next_scheduled(self::CRON_HOOK);
            if ($timestamp && function_exists('wp_unschedule_event')) {
                wp_unschedule_event($timestamp, self::CRON_HOOK);
            }
        }
        if (function_exists('delete_transient')) {
            delete_transient(self::CRON_LOCK);
        }
    }

    public function default_settings() {
        return array(
            'lookback_days' => 180,
            'minimum_evidence' => 3,
            'elevated_score' => 45,
            'critical_score' => 70,
            'negative_feedback_weight' => 8,
            'unresolved_search_weight' => 7,
            'documentation_gap_weight' => 10,
            'known_issue_weight' => 9,
            'feature_request_weight' => 4,
            'public_vote_weight' => 1,
            'daily_refresh_enabled' => '1',
            'maximum_products' => 100,
            'maximum_clusters' => 50,
        );
    }

    public function settings() {
        $saved = function_exists('get_option') ? (array) get_option(self::SETTINGS_OPTION, array()) : array();
        return function_exists('wp_parse_args')
            ? wp_parse_args($saved, $this->default_settings())
            : array_merge($this->default_settings(), $saved);
    }

    public function manage_capability() {
        return apply_filters('scfs_feedback_product_signals_capability', 'edit_others_posts');
    }

    public function can_manage() {
        return current_user_can($this->manage_capability());
    }

    public function register_admin_page() {
        add_submenu_page(
            'edit.php?post_type=' . Sustainable_Catalyst_Feature_Suggestions::POST_TYPE,
            __('Product Signals', 'sustainable-catalyst-feature-suggestions'),
            __('Product Signals', 'sustainable-catalyst-feature-suggestions'),
            $this->manage_capability(),
            self::ADMIN_PAGE,
            array($this, 'render_admin_page')
        );
    }

    public function admin_assets($hook) {
        if (strpos((string) $hook, self::ADMIN_PAGE) === false) {
            return;
        }
        $css = dirname(__DIR__) . '/assets/feedback-product-signals.css';
        $js = dirname(__DIR__) . '/assets/feedback-product-signals.js';
        wp_enqueue_style(
            'scfs-feedback-product-signals',
            plugins_url('../assets/feedback-product-signals.css', __FILE__),
            array(),
            is_readable($css) ? (string) filemtime($css) : self::VERSION
        );
        wp_enqueue_script(
            'scfs-feedback-product-signals',
            plugins_url('../assets/feedback-product-signals.js', __FILE__),
            array(),
            is_readable($js) ? (string) filemtime($js) : self::VERSION,
            true
        );
    }

    public function invalidate_snapshot() {
        if (function_exists('delete_option')) {
            delete_option(self::SNAPSHOT_OPTION);
        }
    }

    public function invalidate_snapshot_for_post($post_id, $post, $update) {
        unset($update);
        if (!$post || wp_is_post_revision($post_id)) {
            return;
        }
        $types = array(
            Sustainable_Catalyst_Feature_Suggestions::POST_TYPE,
            class_exists('SCFS_Documentation_Feature_Intelligence') ? SCFS_Documentation_Feature_Intelligence::GAP_POST_TYPE : 'sc_doc_gap',
            class_exists('SCFS_Knowledge_Base_Foundation') ? SCFS_Knowledge_Base_Foundation::ARTICLE_POST_TYPE : 'sc_support_article',
            class_exists('SCFS_Knowledge_Base_Foundation') ? SCFS_Knowledge_Base_Foundation::ISSUE_POST_TYPE : 'sc_known_issue',
            class_exists('SCFS_Product_Support_Platform') ? SCFS_Product_Support_Platform::RELEASE_POST_TYPE : 'sc_release_record',
        );
        if (in_array((string) $post->post_type, $types, true)) {
            $this->invalidate_snapshot();
        }
    }

    private function product_key($value) {
        $value = sanitize_title((string) $value);
        return $value !== '' ? $value : 'unassigned';
    }

    private function term_slugs($post_id, $taxonomy) {
        if (!function_exists('wp_get_object_terms')) {
            return array();
        }
        $terms = wp_get_object_terms($post_id, $taxonomy, array('fields' => 'slugs'));
        return is_wp_error($terms) ? array() : array_values(array_filter(array_map('sanitize_title', (array) $terms)));
    }

    private function term_names($post_id, $taxonomy) {
        if (!function_exists('wp_get_object_terms')) {
            return array();
        }
        $terms = wp_get_object_terms($post_id, $taxonomy, array('fields' => 'names'));
        return is_wp_error($terms) ? array() : array_values(array_filter(array_map('sanitize_text_field', (array) $terms)));
    }

    private function new_product_record($slug, $name = '') {
        return array(
            'product' => $slug,
            'name' => $name !== '' ? $name : ucwords(str_replace('-', ' ', $slug)),
            'feature_requests' => 0,
            'public_votes' => 0,
            'article_feedback_total' => 0,
            'article_feedback_negative' => 0,
            'unresolved_searches' => 0,
            'low_confidence_searches' => 0,
            'failed_resolution_paths' => 0,
            'documentation_gaps' => 0,
            'high_priority_documentation_gaps' => 0,
            'active_known_issues' => 0,
            'critical_known_issues' => 0,
            'support_relationships' => 0,
            'components' => array(),
            'versions' => array(),
            'feedback_reasons' => array(),
            'evidence_count' => 0,
            'signal_score' => 0,
            'signal_state' => 'insufficient_evidence',
            'recommended_actions' => array(),
            'human_review_required' => true,
        );
    }

    private function ensure_product(&$products, $slug, $name = '') {
        $slug = $this->product_key($slug);
        if (!isset($products[$slug])) {
            $products[$slug] = $this->new_product_record($slug, $name);
        } elseif ($name !== '' && ($products[$slug]['name'] === '' || $products[$slug]['name'] === ucwords(str_replace('-', ' ', $slug)))) {
            $products[$slug]['name'] = $name;
        }
        return $slug;
    }

    private function increment(&$bucket, $key, $amount = 1) {
        $key = sanitize_key((string) $key);
        if ($key === '') {
            $key = 'other';
        }
        $bucket[$key] = isset($bucket[$key]) ? (int) $bucket[$key] + (int) $amount : (int) $amount;
    }

    private function add_context(&$record, $components, $versions) {
        foreach ((array) $components as $component) {
            $component = sanitize_title((string) $component);
            if ($component !== '') {
                $this->increment($record['components'], $component);
            }
        }
        foreach ((array) $versions as $version) {
            $version = sanitize_title((string) $version);
            if ($version !== '') {
                $this->increment($record['versions'], $version);
            }
        }
    }

    private function collect_suggestions(&$products) {
        if (!function_exists('get_posts')) {
            return;
        }
        $ids = get_posts(array(
            'post_type' => Sustainable_Catalyst_Feature_Suggestions::POST_TYPE,
            'post_status' => array('publish', 'private', 'draft', 'pending'),
            'posts_per_page' => -1,
            'fields' => 'ids',
            'no_found_rows' => true,
        ));
        foreach ((array) $ids as $id) {
            $slugs = $this->term_slugs($id, 'scfs_product');
            $names = $this->term_names($id, 'scfs_product');
            if (!$slugs) {
                $slugs = array($this->product_key(get_post_meta($id, '_scfs_roadmap_area', true)));
            }
            $components = $this->term_slugs($id, 'scfs_component');
            $versions = $this->term_slugs($id, 'scfs_product_version');
            $votes = absint(get_post_meta($id, '_scfs_support_votes', true));
            foreach ($slugs as $index => $slug) {
                $key = $this->ensure_product($products, $slug, isset($names[$index]) ? $names[$index] : '');
                $products[$key]['feature_requests']++;
                $products[$key]['public_votes'] += $votes;
                $this->add_context($products[$key], $components, $versions);
            }
        }
    }

    private function table_exists($table) {
        global $wpdb;
        if (!isset($wpdb) || !method_exists($wpdb, 'get_var') || !method_exists($wpdb, 'prepare')) {
            return false;
        }
        $found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
        return (string) $found === (string) $table;
    }

    private function collect_article_feedback(&$products, &$clusters, $lookback_days) {
        if (!class_exists('SCFS_Documentation_Feature_Intelligence')) {
            return;
        }
        global $wpdb;
        $table = SCFS_Documentation_Feature_Intelligence::instance()->feedback_table_name();
        if (!$this->table_exists($table) || !method_exists($wpdb, 'get_results')) {
            return;
        }
        $days = max(1, absint($lookback_days));
        $sql = $wpdb->prepare(
            'SELECT product, product_version, component, helpful, reason, COUNT(*) AS responses FROM ' . $table . ' WHERE created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL %d DAY) GROUP BY product, product_version, component, helpful, reason',
            $days
        );
        $rows = $wpdb->get_results($sql, ARRAY_A); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        foreach ((array) $rows as $row) {
            $product = $this->ensure_product($products, $row['product'] ?? '');
            $count = absint($row['responses'] ?? 0);
            $products[$product]['article_feedback_total'] += $count;
            if (empty($row['helpful'])) {
                $products[$product]['article_feedback_negative'] += $count;
                $reason = sanitize_key((string) ($row['reason'] ?? 'other'));
                $this->increment($products[$product]['feedback_reasons'], $reason, $count);
                $cluster_key = 'feedback:' . $product . ':' . $reason;
                $clusters[$cluster_key] = array(
                    'cluster_key' => $cluster_key,
                    'signal_type' => 'negative_article_feedback',
                    'product' => $product,
                    'component' => sanitize_title((string) ($row['component'] ?? '')),
                    'product_version' => sanitize_title((string) ($row['product_version'] ?? '')),
                    'label' => ucwords(str_replace('_', ' ', $reason)),
                    'evidence_count' => $count,
                    'priority_score' => min(100, $count * 8),
                    'recommended_action' => 'review_support_article_quality',
                );
            }
            $this->add_context($products[$product], array($row['component'] ?? ''), array($row['product_version'] ?? ''));
        }
    }

    private function collect_resolution_signals(&$products, &$clusters, $lookback_days) {
        if (!class_exists('SCFS_Guided_Resolution')) {
            return;
        }
        global $wpdb;
        $table = SCFS_Guided_Resolution::instance()->table_name();
        if (!$this->table_exists($table) || !method_exists($wpdb, 'get_results')) {
            return;
        }
        $days = max(1, absint($lookback_days));
        $sql = $wpdb->prepare(
            'SELECT filters_json, resolution_state, confidence, result_count, query_hash, COUNT(*) AS searches FROM ' . $table . ' WHERE event_type = %s AND created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL %d DAY) GROUP BY filters_json, resolution_state, confidence, result_count, query_hash',
            'search',
            $days
        );
        $rows = $wpdb->get_results($sql, ARRAY_A); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        foreach ((array) $rows as $row) {
            $filters = json_decode((string) ($row['filters_json'] ?? ''), true);
            $filters = is_array($filters) ? $filters : array();
            $product = $this->ensure_product($products, $filters['product'] ?? '');
            $count = absint($row['searches'] ?? 0);
            $state = sanitize_key((string) ($row['resolution_state'] ?? 'no_match'));
            $confidence = (float) ($row['confidence'] ?? 0);
            $result_count = absint($row['result_count'] ?? 0);
            if (in_array($state, array('no_match', 'unresolved', 'low_confidence'), true) || $result_count === 0) {
                $products[$product]['unresolved_searches'] += $count;
            }
            if ($confidence < 0.42) {
                $products[$product]['low_confidence_searches'] += $count;
            }
            if (in_array($state, array('unresolved', 'handoff_recommended'), true)) {
                $products[$product]['failed_resolution_paths'] += $count;
            }
            $this->add_context($products[$product], array($filters['component'] ?? ''), array($filters['product_version'] ?? ''));
            if ($count > 0 && ($result_count === 0 || $confidence < 0.42)) {
                $query_hash = sanitize_text_field((string) ($row['query_hash'] ?? 'unknown'));
                $cluster_key = 'resolution:' . $product . ':' . substr($query_hash, 0, 16);
                $clusters[$cluster_key] = array(
                    'cluster_key' => $cluster_key,
                    'signal_type' => 'unresolved_support_search',
                    'product' => $product,
                    'component' => sanitize_title((string) ($filters['component'] ?? '')),
                    'product_version' => sanitize_title((string) ($filters['product_version'] ?? '')),
                    'label' => __('Unresolved support-search cluster', 'sustainable-catalyst-feature-suggestions'),
                    'evidence_count' => $count,
                    'priority_score' => min(100, ($count * 7) + ($result_count === 0 ? 15 : 0)),
                    'recommended_action' => 'review_search_and_documentation_gap',
                );
            }
        }
    }

    private function collect_documentation_gaps(&$products, &$clusters) {
        if (!class_exists('SCFS_Documentation_Feature_Intelligence') || !function_exists('get_posts')) {
            return;
        }
        $ids = get_posts(array(
            'post_type' => SCFS_Documentation_Feature_Intelligence::GAP_POST_TYPE,
            'post_status' => array('publish', 'private', 'draft', 'pending'),
            'posts_per_page' => -1,
            'fields' => 'ids',
            'no_found_rows' => true,
        ));
        foreach ((array) $ids as $id) {
            $status = sanitize_key((string) get_post_meta($id, '_scfs_gap_status', true));
            if (in_array($status, array('documented', 'dismissed'), true)) {
                continue;
            }
            $product = $this->ensure_product($products, get_post_meta($id, '_scfs_gap_product', true));
            $score = (float) get_post_meta($id, '_scfs_gap_score', true);
            $products[$product]['documentation_gaps']++;
            if ($score >= 60) {
                $products[$product]['high_priority_documentation_gaps']++;
            }
            $component = sanitize_title((string) get_post_meta($id, '_scfs_gap_component', true));
            $version = sanitize_title((string) get_post_meta($id, '_scfs_gap_product_version', true));
            $this->add_context($products[$product], array($component), array($version));
            $cluster_key = 'gap:' . absint($id);
            $clusters[$cluster_key] = array(
                'cluster_key' => $cluster_key,
                'signal_type' => 'documentation_gap',
                'product' => $product,
                'component' => $component,
                'product_version' => $version,
                'label' => get_the_title($id),
                'evidence_count' => max(1, absint(get_post_meta($id, '_scfs_gap_search_count', true)) + absint(get_post_meta($id, '_scfs_gap_negative_feedback_count', true))),
                'priority_score' => max(0, min(100, $score)),
                'recommended_action' => 'review_documentation_gap',
                'edit_url' => get_edit_post_link($id, 'raw'),
            );
        }
    }

    private function collect_known_issues(&$products) {
        if (!class_exists('SCFS_Knowledge_Base_Foundation') || !function_exists('get_posts')) {
            return;
        }
        $ids = get_posts(array(
            'post_type' => SCFS_Knowledge_Base_Foundation::ISSUE_POST_TYPE,
            'post_status' => array('publish', 'private', 'draft', 'pending'),
            'posts_per_page' => -1,
            'fields' => 'ids',
            'no_found_rows' => true,
        ));
        foreach ((array) $ids as $id) {
            $state = sanitize_key((string) get_post_meta($id, '_scfs_issue_status', true));
            if (in_array($state, array('resolved', 'closed', 'fixed', 'dismissed'), true)) {
                continue;
            }
            $products_for_issue = $this->term_slugs($id, 'scfs_product');
            if (!$products_for_issue) {
                $products_for_issue = array('unassigned');
            }
            $severity = sanitize_key((string) get_post_meta($id, '_scfs_issue_severity', true));
            $components = $this->term_slugs($id, 'scfs_component');
            $versions = array_merge(
                $this->term_slugs($id, 'scfs_product_version'),
                (array) get_post_meta($id, '_scfs_issue_affected_versions', true)
            );
            foreach ($products_for_issue as $slug) {
                $product = $this->ensure_product($products, $slug);
                $products[$product]['active_known_issues']++;
                if (in_array($severity, array('critical', 'major', 'high'), true)) {
                    $products[$product]['critical_known_issues']++;
                }
                $this->add_context($products[$product], $components, $versions);
            }
        }
    }

    private function collect_support_relationships(&$products) {
        if (!class_exists('SCFS_Documentation_Feature_Intelligence')) {
            return;
        }
        global $wpdb;
        $table = SCFS_Documentation_Feature_Intelligence::instance()->relationship_table_name();
        if (!$this->table_exists($table) || !method_exists($wpdb, 'get_results')) {
            return;
        }
        $rows = $wpdb->get_results('SELECT product, product_version, component, COUNT(*) AS relationships FROM ' . $table . ' GROUP BY product, product_version, component', ARRAY_A); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        foreach ((array) $rows as $row) {
            $product = $this->ensure_product($products, $row['product'] ?? '');
            $products[$product]['support_relationships'] += absint($row['relationships'] ?? 0);
            $this->add_context($products[$product], array($row['component'] ?? ''), array($row['product_version'] ?? ''));
        }
    }

    public function score_product_record($record) {
        $settings = $this->settings();
        $negative = absint($record['article_feedback_negative'] ?? 0);
        $unresolved = absint($record['unresolved_searches'] ?? 0);
        $gaps = absint($record['documentation_gaps'] ?? 0);
        $high_gaps = absint($record['high_priority_documentation_gaps'] ?? 0);
        $issues = absint($record['active_known_issues'] ?? 0);
        $critical = absint($record['critical_known_issues'] ?? 0);
        $requests = absint($record['feature_requests'] ?? 0);
        $votes = absint($record['public_votes'] ?? 0);
        $relationships = absint($record['support_relationships'] ?? 0);
        $failed_paths = absint($record['failed_resolution_paths'] ?? 0);

        $score = 0;
        $score += min(24, $negative * (float) $settings['negative_feedback_weight']);
        $score += min(24, $unresolved * (float) $settings['unresolved_search_weight']);
        $score += min(20, ($gaps + $high_gaps) * (float) $settings['documentation_gap_weight']);
        $score += min(20, ($issues + $critical) * (float) $settings['known_issue_weight']);
        $score += min(8, $requests * (float) $settings['feature_request_weight']);
        $score += min(6, $votes * (float) $settings['public_vote_weight']);
        $score += min(8, $relationships * 2);
        $score += min(10, $failed_paths * 5);
        $score = (int) max(0, min(100, round($score)));

        $evidence = $negative + $unresolved + $gaps + $issues + $requests + $relationships + $failed_paths;
        $state = 'insufficient_evidence';
        if ($evidence >= absint($settings['minimum_evidence'])) {
            if ($score >= absint($settings['critical_score'])) {
                $state = 'critical_review';
            } elseif ($score >= absint($settings['elevated_score'])) {
                $state = 'elevated';
            } elseif ($score >= 20) {
                $state = 'emerging';
            } else {
                $state = 'monitor';
            }
        }

        $actions = array();
        if ($unresolved > 0 || $gaps > 0 || $negative > 0) {
            $actions[] = 'review_documentation_and_search_experience';
        }
        if ($issues > 0 || $failed_paths > 0) {
            $actions[] = 'review_known_issue_and_resolution_path';
        }
        if ($requests > 0 || $votes > 0 || $relationships > 0) {
            $actions[] = 'review_product_opportunity_evidence';
        }
        if (!$actions) {
            $actions[] = 'continue_monitoring';
        }

        $record['evidence_count'] = $evidence;
        $record['signal_score'] = $score;
        $record['signal_state'] = $state;
        $record['recommended_actions'] = array_values(array_unique($actions));
        return $record;
    }

    public function build_snapshot($force = false, $filters = array()) {
        if (!$force && !$filters) {
            $cached = get_option(self::SNAPSHOT_OPTION, array());
            if (is_array($cached) && ($cached['schema'] ?? '') === self::SCHEMA && !empty($cached['generated_at'])) {
                return $cached;
            }
        }
        $settings = $this->settings();
        $products = array();
        $clusters = array();
        $this->collect_suggestions($products);
        $this->collect_article_feedback($products, $clusters, $settings['lookback_days']);
        $this->collect_resolution_signals($products, $clusters, $settings['lookback_days']);
        $this->collect_documentation_gaps($products, $clusters);
        $this->collect_known_issues($products);
        $this->collect_support_relationships($products);

        foreach ($products as $key => $record) {
            arsort($record['components']);
            arsort($record['versions']);
            arsort($record['feedback_reasons']);
            $products[$key] = $this->score_product_record($record);
        }
        uasort($products, static function ($a, $b) {
            if ((int) $a['signal_score'] === (int) $b['signal_score']) {
                return strcasecmp((string) $a['name'], (string) $b['name']);
            }
            return (int) $b['signal_score'] <=> (int) $a['signal_score'];
        });
        uasort($clusters, static function ($a, $b) {
            return (float) $b['priority_score'] <=> (float) $a['priority_score'];
        });

        $filter_product = sanitize_title((string) ($filters['product'] ?? ''));
        if ($filter_product !== '') {
            $products = isset($products[$filter_product]) ? array($filter_product => $products[$filter_product]) : array();
            $clusters = array_filter($clusters, static function ($cluster) use ($filter_product) {
                return ($cluster['product'] ?? '') === $filter_product;
            });
        }
        $maximum_products = max(1, min(500, absint($settings['maximum_products'])));
        $maximum_clusters = max(1, min(500, absint($settings['maximum_clusters'])));
        $products = array_slice($products, 0, $maximum_products, true);
        $clusters = array_slice($clusters, 0, $maximum_clusters, true);

        $state_counts = array();
        $totals = array(
            'products' => count($products),
            'evidence' => 0,
            'feature_requests' => 0,
            'public_votes' => 0,
            'negative_feedback' => 0,
            'unresolved_searches' => 0,
            'documentation_gaps' => 0,
            'active_known_issues' => 0,
        );
        foreach ($products as $record) {
            $this->increment($state_counts, $record['signal_state']);
            $totals['evidence'] += absint($record['evidence_count']);
            $totals['feature_requests'] += absint($record['feature_requests']);
            $totals['public_votes'] += absint($record['public_votes']);
            $totals['negative_feedback'] += absint($record['article_feedback_negative']);
            $totals['unresolved_searches'] += absint($record['unresolved_searches']);
            $totals['documentation_gaps'] += absint($record['documentation_gaps']);
            $totals['active_known_issues'] += absint($record['active_known_issues']);
        }

        $snapshot = array(
            'schema' => self::SCHEMA,
            'version' => self::VERSION,
            'generated_at' => gmdate('c'),
            'lookback_days' => absint($settings['lookback_days']),
            'totals' => $totals,
            'state_counts' => $state_counts,
            'products' => array_values($products),
            'clusters' => array_values($clusters),
            'privacy' => array(
                'public_aggregate_only' => false,
                'administrator_only' => true,
                'personal_identifiers_exposed' => false,
                'private_case_content_exposed' => false,
                'raw_search_text_exposed' => false,
            ),
            'governance' => array(
                'human_review_required' => true,
                'automatic_roadmap_changes' => false,
                'automatic_issue_declaration' => false,
                'automatic_publication' => false,
            ),
        );
        if (!$filters) {
            update_option(self::SNAPSHOT_OPTION, $snapshot, false);
            update_option(self::LAST_REFRESH_OPTION, $snapshot['generated_at'], false);
        }
        do_action('scfs_feedback_product_signals_refreshed', $snapshot);
        return $snapshot;
    }

    public function product_record($product, $force = false) {
        $product = sanitize_title((string) $product);
        $snapshot = $this->build_snapshot($force, $product !== '' ? array('product' => $product) : array());
        return !empty($snapshot['products'][0]) ? $snapshot['products'][0] : null;
    }

    public function signal_for_suggestion($post_id) {
        $products = $this->term_slugs($post_id, 'scfs_product');
        if (!$products) {
            $products = array($this->product_key(get_post_meta($post_id, '_scfs_roadmap_area', true)));
        }
        $snapshot = $this->build_snapshot(false);
        $indexed = array();
        foreach ((array) ($snapshot['products'] ?? array()) as $record) {
            $indexed[$record['product']] = $record;
        }
        $matches = array();
        foreach ($products as $product) {
            if (isset($indexed[$product])) {
                $matches[] = $indexed[$product];
            }
        }
        usort($matches, static function ($a, $b) {
            return (int) $b['signal_score'] <=> (int) $a['signal_score'];
        });
        return array(
            'schema' => self::SCHEMA,
            'suggestion_id' => absint($post_id),
            'products' => $products,
            'strongest_signal' => $matches ? $matches[0] : null,
            'signals' => $matches,
            'human_review_required' => true,
            'automatic_roadmap_changes' => false,
        );
    }

    public function run_scheduled_refresh() {
        $settings = $this->settings();
        if (($settings['daily_refresh_enabled'] ?? '1') !== '1' || get_transient(self::CRON_LOCK)) {
            return;
        }
        set_transient(self::CRON_LOCK, 1, 10 * MINUTE_IN_SECONDS);
        $this->build_snapshot(true);
        delete_transient(self::CRON_LOCK);
    }

    public function refresh_action() {
        if (!$this->can_manage()) {
            wp_die(__('You do not have permission to refresh product signals.', 'sustainable-catalyst-feature-suggestions'));
        }
        check_admin_referer(self::NONCE_ACTION);
        $this->build_snapshot(true);
        wp_safe_redirect(add_query_arg(array('post_type' => Sustainable_Catalyst_Feature_Suggestions::POST_TYPE, 'page' => self::ADMIN_PAGE, 'refreshed' => '1'), admin_url('edit.php')));
        exit;
    }

    public function export_action() {
        if (!$this->can_manage()) {
            wp_die(__('You do not have permission to export product signals.', 'sustainable-catalyst-feature-suggestions'));
        }
        check_admin_referer(self::NONCE_ACTION . '_export');
        $snapshot = $this->build_snapshot(false);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=scfs-product-signals-' . gmdate('Y-m-d') . '.csv');
        header('Pragma: no-cache');
        header('Expires: 0');
        $out = fopen('php://output', 'w');
        fputcsv($out, array('Product', 'State', 'Score', 'Evidence', 'Feature Requests', 'Public Votes', 'Negative Article Feedback', 'Unresolved Searches', 'Documentation Gaps', 'Active Known Issues', 'Recommended Actions'));
        foreach ((array) ($snapshot['products'] ?? array()) as $record) {
            fputcsv($out, array(
                $record['name'],
                $record['signal_state'],
                $record['signal_score'],
                $record['evidence_count'],
                $record['feature_requests'],
                $record['public_votes'],
                $record['article_feedback_negative'],
                $record['unresolved_searches'],
                $record['documentation_gaps'],
                $record['active_known_issues'],
                implode(' | ', $record['recommended_actions']),
            ));
        }
        fclose($out);
        exit;
    }

    public function register_rest_routes() {
        $namespace = Sustainable_Catalyst_Feature_Suggestions::REST_NAMESPACE;
        $permission = function () {
            return current_user_can($this->manage_capability());
        };
        register_rest_route($namespace, '/feedback-product-signals/schema', array(
            'methods' => 'GET',
            'callback' => array($this, 'rest_schema'),
            'permission_callback' => $permission,
        ));
        register_rest_route($namespace, '/feedback-product-signals/summary', array(
            'methods' => 'GET',
            'callback' => array($this, 'rest_summary'),
            'permission_callback' => $permission,
        ));
        register_rest_route($namespace, '/feedback-product-signals/products', array(
            'methods' => 'GET',
            'callback' => array($this, 'rest_products'),
            'permission_callback' => $permission,
        ));
        register_rest_route($namespace, '/feedback-product-signals/product/(?P<product>[a-z0-9-]+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'rest_product'),
            'permission_callback' => $permission,
        ));
        register_rest_route($namespace, '/feedback-product-signals/refresh', array(
            'methods' => 'POST',
            'callback' => array($this, 'rest_refresh'),
            'permission_callback' => function () {
                return current_user_can('manage_options');
            },
        ));
    }

    public function rest_schema() {
        return rest_ensure_response(array(
            'ok' => true,
            'schema' => self::SCHEMA,
            'version' => self::VERSION,
            'sources' => array('feature_suggestions', 'public_votes', 'article_feedback', 'guided_resolution', 'documentation_gaps', 'known_issues', 'support_relationships'),
            'states' => array('insufficient_evidence', 'monitor', 'emerging', 'elevated', 'critical_review'),
            'administrator_only' => true,
            'raw_search_text_exposed' => false,
            'private_case_content_exposed' => false,
            'human_review_required' => true,
            'automatic_roadmap_changes' => false,
        ));
    }

    public function rest_summary($request) {
        $force = rest_sanitize_boolean($request->get_param('refresh'));
        return rest_ensure_response(array('ok' => true, 'data' => $this->build_snapshot($force)));
    }

    public function rest_products($request) {
        $snapshot = $this->build_snapshot(rest_sanitize_boolean($request->get_param('refresh')));
        return rest_ensure_response(array('ok' => true, 'schema' => self::SCHEMA, 'items' => $snapshot['products'], 'generated_at' => $snapshot['generated_at']));
    }

    public function rest_product($request) {
        $record = $this->product_record($request['product'], rest_sanitize_boolean($request->get_param('refresh')));
        if (!$record) {
            return new WP_Error('scfs_product_signal_not_found', __('No product signal record was found.', 'sustainable-catalyst-feature-suggestions'), array('status' => 404));
        }
        return rest_ensure_response(array('ok' => true, 'schema' => self::SCHEMA, 'item' => $record));
    }

    public function rest_refresh() {
        return rest_ensure_response(array('ok' => true, 'data' => $this->build_snapshot(true)));
    }

    private function state_label($state) {
        $labels = array(
            'insufficient_evidence' => __('Insufficient evidence', 'sustainable-catalyst-feature-suggestions'),
            'monitor' => __('Monitor', 'sustainable-catalyst-feature-suggestions'),
            'emerging' => __('Emerging', 'sustainable-catalyst-feature-suggestions'),
            'elevated' => __('Elevated', 'sustainable-catalyst-feature-suggestions'),
            'critical_review' => __('Critical review', 'sustainable-catalyst-feature-suggestions'),
        );
        return $labels[$state] ?? ucwords(str_replace('_', ' ', $state));
    }

    private function render_snapshot($snapshot) {
        $totals = (array) ($snapshot['totals'] ?? array());
        echo '<div class="scfs-fps-summary">';
        $cards = array(
            'products' => __('Products', 'sustainable-catalyst-feature-suggestions'),
            'evidence' => __('Evidence signals', 'sustainable-catalyst-feature-suggestions'),
            'feature_requests' => __('Feature requests', 'sustainable-catalyst-feature-suggestions'),
            'public_votes' => __('Public votes', 'sustainable-catalyst-feature-suggestions'),
            'negative_feedback' => __('Negative feedback', 'sustainable-catalyst-feature-suggestions'),
            'unresolved_searches' => __('Unresolved searches', 'sustainable-catalyst-feature-suggestions'),
            'documentation_gaps' => __('Documentation gaps', 'sustainable-catalyst-feature-suggestions'),
            'active_known_issues' => __('Active issues', 'sustainable-catalyst-feature-suggestions'),
        );
        foreach ($cards as $key => $label) {
            echo '<section><span>' . esc_html($label) . '</span><strong>' . esc_html((string) absint($totals[$key] ?? 0)) . '</strong></section>';
        }
        echo '</div>';

        echo '<section class="scfs-fps-panel"><div class="scfs-fps-panel__head"><div><p class="scfs-fps-eyebrow">' . esc_html__('Product intelligence', 'sustainable-catalyst-feature-suggestions') . '</p><h2>' . esc_html__('Review product-level support demand', 'sustainable-catalyst-feature-suggestions') . '</h2></div><p>' . esc_html__('Scores combine privacy-minimized demand and quality signals. They are advisory and never change roadmap or release state automatically.', 'sustainable-catalyst-feature-suggestions') . '</p></div>';
        echo '<div class="scfs-fps-table-wrap"><table class="widefat striped scfs-fps-table"><thead><tr><th>' . esc_html__('Product', 'sustainable-catalyst-feature-suggestions') . '</th><th>' . esc_html__('State', 'sustainable-catalyst-feature-suggestions') . '</th><th>' . esc_html__('Score', 'sustainable-catalyst-feature-suggestions') . '</th><th>' . esc_html__('Evidence', 'sustainable-catalyst-feature-suggestions') . '</th><th>' . esc_html__('Demand and quality signals', 'sustainable-catalyst-feature-suggestions') . '</th><th>' . esc_html__('Recommended review', 'sustainable-catalyst-feature-suggestions') . '</th></tr></thead><tbody>';
        if (empty($snapshot['products'])) {
            echo '<tr><td colspan="6">' . esc_html__('No product signals are available yet.', 'sustainable-catalyst-feature-suggestions') . '</td></tr>';
        }
        foreach ((array) ($snapshot['products'] ?? array()) as $record) {
            $details = sprintf(
                __('%1$d requests · %2$d votes · %3$d negative article responses · %4$d unresolved searches · %5$d gaps · %6$d active issues', 'sustainable-catalyst-feature-suggestions'),
                absint($record['feature_requests']),
                absint($record['public_votes']),
                absint($record['article_feedback_negative']),
                absint($record['unresolved_searches']),
                absint($record['documentation_gaps']),
                absint($record['active_known_issues'])
            );
            echo '<tr data-scfs-fps-state="' . esc_attr($record['signal_state']) . '"><td><strong>' . esc_html($record['name']) . '</strong><br><code>' . esc_html($record['product']) . '</code></td><td><span class="scfs-fps-pill scfs-fps-pill--' . esc_attr($record['signal_state']) . '">' . esc_html($this->state_label($record['signal_state'])) . '</span></td><td><strong>' . esc_html((string) absint($record['signal_score'])) . '</strong> / 100</td><td>' . esc_html((string) absint($record['evidence_count'])) . '</td><td>' . esc_html($details) . '</td><td>' . esc_html(implode(', ', array_map(static function ($action) { return ucwords(str_replace('_', ' ', $action)); }, $record['recommended_actions']))) . '</td></tr>';
        }
        echo '</tbody></table></div></section>';

        echo '<section class="scfs-fps-panel"><div class="scfs-fps-panel__head"><div><p class="scfs-fps-eyebrow">' . esc_html__('Evidence clusters', 'sustainable-catalyst-feature-suggestions') . '</p><h2>' . esc_html__('Highest-priority gaps and unresolved patterns', 'sustainable-catalyst-feature-suggestions') . '</h2></div></div><div class="scfs-fps-clusters">';
        if (empty($snapshot['clusters'])) {
            echo '<p>' . esc_html__('No unresolved evidence clusters are available.', 'sustainable-catalyst-feature-suggestions') . '</p>';
        }
        foreach ((array) ($snapshot['clusters'] ?? array()) as $cluster) {
            echo '<article><div><span class="scfs-fps-cluster-type">' . esc_html(ucwords(str_replace('_', ' ', $cluster['signal_type']))) . '</span><h3>' . esc_html($cluster['label']) . '</h3><p>' . esc_html(sprintf(__('%1$s · %2$d evidence records', 'sustainable-catalyst-feature-suggestions'), $cluster['product'], absint($cluster['evidence_count']))) . '</p></div><strong>' . esc_html((string) round((float) $cluster['priority_score'])) . '</strong></article>';
        }
        echo '</div></section>';
    }

    public function render_admin_page() {
        if (!$this->can_manage()) {
            wp_die(__('You do not have permission to view product signals.', 'sustainable-catalyst-feature-suggestions'));
        }
        $snapshot = $this->build_snapshot(false);
        $refresh = wp_nonce_url(admin_url('admin-post.php?action=scfs_feedback_product_signals_refresh'), self::NONCE_ACTION);
        $export = wp_nonce_url(admin_url('admin-post.php?action=scfs_feedback_product_signals_export'), self::NONCE_ACTION . '_export');
        echo '<div class="wrap scfs-fps"><div class="scfs-fps-hero"><div><p class="scfs-fps-eyebrow">' . esc_html__('Support & Feedback', 'sustainable-catalyst-feature-suggestions') . '</p><h1>' . esc_html__('Feedback Intelligence and Product Signals', 'sustainable-catalyst-feature-suggestions') . '</h1><p>' . esc_html__('Review privacy-minimized evidence from public feedback, documentation use, support discovery, Known Issues, and feature demand.', 'sustainable-catalyst-feature-suggestions') . '</p></div><div class="scfs-fps-actions"><a class="button button-primary" href="' . esc_url($refresh) . '">' . esc_html__('Refresh signals', 'sustainable-catalyst-feature-suggestions') . '</a><a class="button" href="' . esc_url($export) . '">' . esc_html__('Export CSV', 'sustainable-catalyst-feature-suggestions') . '</a></div></div>';
        echo '<div class="notice notice-info inline"><p>' . esc_html__('Product signals are advisory. Human reviewers retain authority over documentation, issue, release, and roadmap decisions. Raw search text, contact details, and private case content are not displayed.', 'sustainable-catalyst-feature-suggestions') . '</p></div>';
        $this->render_snapshot($snapshot);
        echo '</div>';
    }

    public function render_shortcode() {
        if (!$this->can_manage()) {
            return '';
        }
        ob_start();
        echo '<div class="scfs-fps scfs-fps--embedded">';
        $this->render_snapshot($this->build_snapshot(false));
        echo '</div>';
        return ob_get_clean();
    }

    public function cli_refresh($args = array(), $assoc_args = array()) {
        unset($args, $assoc_args);
        $snapshot = $this->build_snapshot(true);
        WP_CLI::success(sprintf('Refreshed %d product signal records.', count($snapshot['products'])));
    }

    public function cli_summary($args = array(), $assoc_args = array()) {
        unset($args, $assoc_args);
        WP_CLI::line(wp_json_encode($this->build_snapshot(false), JSON_PRETTY_PRINT));
    }

    public function cli_product($args = array(), $assoc_args = array()) {
        unset($assoc_args);
        $product = isset($args[0]) ? sanitize_title($args[0]) : '';
        if ($product === '') {
            WP_CLI::error('Provide a product slug.');
        }
        $record = $this->product_record($product, false);
        if (!$record) {
            WP_CLI::error('Product signal record not found.');
        }
        WP_CLI::line(wp_json_encode($record, JSON_PRETTY_PRINT));
    }
}
