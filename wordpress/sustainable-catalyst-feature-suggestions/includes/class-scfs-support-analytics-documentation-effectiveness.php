<?php
/**
 * Support Analytics and Documentation Effectiveness.
 *
 * Produces privacy-safe, administrator-facing evidence about support search
 * success, article usefulness, publication integrity, freshness, known-issue
 * coverage, release documentation coverage, and documentation-gap resolution.
 * Analytics are advisory and never expose private case content or raw queries.
 *
 * @package Sustainable_Catalyst_Feature_Suggestions
 */

if (!defined('ABSPATH')) {
    exit;
}

final class SCFS_Support_Analytics_Documentation_Effectiveness {
    const VERSION = '5.7.0';
    const SCHEMA = 'scfs-support-analytics-documentation-effectiveness/1.0';
    const ADMIN_PAGE = 'scfs-support-analytics';
    const SETTINGS_OPTION = 'scfs_support_analytics_effectiveness_settings';
    const SNAPSHOT_OPTION = 'scfs_support_analytics_effectiveness_snapshot';
    const HISTORY_OPTION = 'scfs_support_analytics_effectiveness_history';
    const LAST_REFRESH_OPTION = 'scfs_support_analytics_effectiveness_last_refresh';
    const SCHEMA_OPTION = 'scfs_support_analytics_effectiveness_schema';
    const CRON_HOOK = 'scfs_support_analytics_effectiveness_daily';
    const CRON_LOCK = 'scfs_support_analytics_effectiveness_lock';
    const NONCE_ACTION = 'scfs_support_analytics_effectiveness';
    const SHORTCODE = 'scfs_support_analytics_summary';

    private static $instance = null;

    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_menu', array($this, 'register_admin_page'), 33);
        add_action('admin_enqueue_scripts', array($this, 'admin_assets'));
        add_action('admin_post_scfs_support_analytics_refresh', array($this, 'refresh_action'));
        add_action('admin_post_scfs_support_analytics_export', array($this, 'export_action'));
        add_action('rest_api_init', array($this, 'register_rest_routes'));
        add_action(self::CRON_HOOK, array($this, 'run_scheduled_refresh'));
        add_action('scfs_guided_resolution_search_recorded', array($this, 'invalidate_snapshot'), 30, 3);
        add_action('scfs_event', array($this, 'invalidate_snapshot'), 30, 1);
        add_action('save_post', array($this, 'invalidate_snapshot_for_post'), 130, 3);
        add_shortcode(self::SHORTCODE, array($this, 'render_shortcode'));

        if (defined('WP_CLI') && WP_CLI && class_exists('WP_CLI')) {
            WP_CLI::add_command('scfs support-analytics refresh', array($this, 'cli_refresh'));
            WP_CLI::add_command('scfs support-analytics summary', array($this, 'cli_summary'));
            WP_CLI::add_command('scfs support-analytics product', array($this, 'cli_product'));
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
            'lookback_days' => 90,
            'freshness_days' => 180,
            'minimum_evidence' => 5,
            'effective_threshold' => 80,
            'healthy_threshold' => 65,
            'watch_threshold' => 45,
            'history_days' => 365,
            'maximum_products' => 100,
            'daily_refresh_enabled' => '1',
        );
    }

    public function settings() {
        $saved = function_exists('get_option') ? (array) get_option(self::SETTINGS_OPTION, array()) : array();
        return function_exists('wp_parse_args')
            ? wp_parse_args($saved, $this->default_settings())
            : array_merge($this->default_settings(), $saved);
    }

    public function manage_capability() {
        return apply_filters('scfs_support_analytics_capability', 'edit_others_posts');
    }

    public function can_manage() {
        return current_user_can($this->manage_capability());
    }

    public function register_admin_page() {
        add_submenu_page(
            'edit.php?post_type=' . Sustainable_Catalyst_Feature_Suggestions::POST_TYPE,
            __('Support Analytics', 'sustainable-catalyst-feature-suggestions'),
            __('Support Analytics', 'sustainable-catalyst-feature-suggestions'),
            $this->manage_capability(),
            self::ADMIN_PAGE,
            array($this, 'render_admin_page')
        );
    }

    public function admin_assets($hook) {
        if (strpos((string) $hook, self::ADMIN_PAGE) === false) {
            return;
        }
        $css = dirname(__DIR__) . '/assets/support-analytics-documentation-effectiveness.css';
        $js = dirname(__DIR__) . '/assets/support-analytics-documentation-effectiveness.js';
        wp_enqueue_style(
            'scfs-support-analytics-documentation-effectiveness',
            plugins_url('../assets/support-analytics-documentation-effectiveness.css', __FILE__),
            array(),
            is_readable($css) ? (string) filemtime($css) : self::VERSION
        );
        wp_enqueue_script(
            'scfs-support-analytics-documentation-effectiveness',
            plugins_url('../assets/support-analytics-documentation-effectiveness.js', __FILE__),
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
            class_exists('SCFS_Knowledge_Base_Foundation') ? SCFS_Knowledge_Base_Foundation::ARTICLE_POST_TYPE : 'sc_support_article',
            class_exists('SCFS_Knowledge_Base_Foundation') ? SCFS_Knowledge_Base_Foundation::ISSUE_POST_TYPE : 'sc_known_issue',
            class_exists('SCFS_Product_Support_Platform') ? SCFS_Product_Support_Platform::RELEASE_POST_TYPE : 'sc_release_record',
            class_exists('SCFS_Documentation_Feature_Intelligence') ? SCFS_Documentation_Feature_Intelligence::GAP_POST_TYPE : 'sc_doc_gap',
        );
        if (in_array((string) $post->post_type, $types, true)) {
            $this->invalidate_snapshot();
        }
    }

    private function product_key($value) {
        $value = sanitize_title((string) $value);
        return $value !== '' ? $value : 'unassigned';
    }

    private function percent($numerator, $denominator, $empty = 100.0) {
        $denominator = (float) $denominator;
        if ($denominator <= 0) {
            return round((float) $empty, 1);
        }
        return round(((float) $numerator / $denominator) * 100, 1);
    }

    private function table_exists($table) {
        global $wpdb;
        if (!isset($wpdb) || !method_exists($wpdb, 'get_var')) {
            return false;
        }
        $found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
        return (string) $found === (string) $table;
    }

    private function product_tax_query($product) {
        $product = $this->product_key($product);
        if ($product === 'all' || $product === 'unassigned') {
            return array();
        }
        return array(array(
            'taxonomy' => 'scfs_product',
            'field' => 'slug',
            'terms' => array($product),
        ));
    }

    public function products() {
        $records = array(array('slug' => 'all', 'name' => __('All products', 'sustainable-catalyst-feature-suggestions')));
        if (!function_exists('get_terms')) {
            return $records;
        }
        $terms = get_terms(array('taxonomy' => 'scfs_product', 'hide_empty' => false));
        if (!is_wp_error($terms)) {
            foreach ((array) $terms as $term) {
                $records[] = array('slug' => sanitize_title($term->slug), 'name' => sanitize_text_field($term->name));
            }
        }
        return array_slice($records, 0, max(1, absint($this->settings()['maximum_products'])));
    }

    public function resolution_metrics($product = 'all') {
        global $wpdb;
        $record = array(
            'searches' => 0,
            'matched_searches' => 0,
            'no_match_searches' => 0,
            'low_confidence_searches' => 0,
            'viewed_searches' => 0,
            'search_success_percent' => 100.0,
            'engagement_percent' => 100.0,
            'unresolved_percent' => 0.0,
        );
        if (!class_exists('SCFS_Guided_Resolution') || !isset($wpdb) || !method_exists($wpdb, 'get_row')) {
            return $record;
        }
        $table = SCFS_Guided_Resolution::instance()->table_name();
        if (!$this->table_exists($table)) {
            return $record;
        }
        $settings = $this->settings();
        $cutoff = gmdate('Y-m-d H:i:s', time() - absint($settings['lookback_days']) * DAY_IN_SECONDS);
        $where = ' WHERE event_type = %s AND created_at >= %s';
        $params = array('search', $cutoff);
        if ($this->product_key($product) !== 'all') {
            $where .= ' AND filters_json LIKE %s';
            $params[] = '%"product":"' . $wpdb->esc_like($this->product_key($product)) . '"%';
        }
        $sql = "SELECT COUNT(*) AS searches,
            SUM(CASE WHEN result_count > 0 AND resolution_state NOT IN ('no_match','low_confidence') THEN 1 ELSE 0 END) AS matched_searches,
            SUM(CASE WHEN resolution_state = 'no_match' THEN 1 ELSE 0 END) AS no_match_searches,
            SUM(CASE WHEN resolution_state = 'low_confidence' THEN 1 ELSE 0 END) AS low_confidence_searches,
            SUM(CASE WHEN viewed_json IS NOT NULL AND viewed_json NOT IN ('','[]') THEN 1 ELSE 0 END) AS viewed_searches
            FROM {$table}{$where}";
        $prepared = call_user_func_array(array($wpdb, 'prepare'), array_merge(array($sql), $params));
        $row = $wpdb->get_row($prepared, ARRAY_A); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        if (is_array($row)) {
            foreach (array('searches', 'matched_searches', 'no_match_searches', 'low_confidence_searches', 'viewed_searches') as $key) {
                $record[$key] = absint($row[$key] ?? 0);
            }
        }
        $unresolved = $record['no_match_searches'] + $record['low_confidence_searches'];
        $record['search_success_percent'] = $this->percent($record['matched_searches'], $record['searches']);
        $record['engagement_percent'] = $this->percent($record['viewed_searches'], $record['searches']);
        $record['unresolved_percent'] = $this->percent($unresolved, $record['searches'], 0);
        return $record;
    }

    private function article_ids($product = 'all') {
        if (!function_exists('get_posts')) {
            return array();
        }
        $args = array(
            'post_type' => class_exists('SCFS_Knowledge_Base_Foundation') ? SCFS_Knowledge_Base_Foundation::ARTICLE_POST_TYPE : 'sc_support_article',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'no_found_rows' => true,
        );
        $tax_query = $this->product_tax_query($product);
        if ($tax_query) {
            $args['tax_query'] = $tax_query;
        }
        return array_map('absint', (array) get_posts($args));
    }

    public function article_metrics($product = 'all') {
        global $wpdb;
        $ids = $this->article_ids($product);
        $record = array(
            'published_articles' => count($ids),
            'feedback_responses' => 0,
            'helpful_responses' => 0,
            'unhelpful_responses' => 0,
            'helpfulness_percent' => 100.0,
            'average_integrity_score' => 100.0,
            'publication_ready_articles' => 0,
            'stale_articles' => 0,
            'fresh_articles' => 0,
            'freshness_percent' => 100.0,
            'articles_needing_attention' => array(),
        );
        if (!$ids) {
            return $record;
        }
        $integrity_total = 0;
        $settings = $this->settings();
        $fresh_cutoff = time() - absint($settings['freshness_days']) * DAY_IN_SECONDS;
        foreach ($ids as $article_id) {
            $score = absint(get_post_meta($article_id, '_scfs_integrity_score', true));
            if (!$score) {
                $score = 50;
            }
            $integrity_total += $score;
            $state = (string) get_post_meta($article_id, '_scfs_integrity_state', true);
            if (in_array($state, array('publication_ready', 'ready'), true) || $score >= 85) {
                $record['publication_ready_articles']++;
            }
            $verified = (string) get_post_meta($article_id, '_scfs_last_verified_date', true);
            $modified = get_post_modified_time('U', true, $article_id);
            $fresh_time = $verified ? strtotime($verified . ' 00:00:00 UTC') : $modified;
            $stale = (bool) get_post_meta($article_id, '_scfs_integrity_stale', true) || !$fresh_time || $fresh_time < $fresh_cutoff;
            if ($stale) {
                $record['stale_articles']++;
            } else {
                $record['fresh_articles']++;
            }
            $helpful = absint(get_post_meta($article_id, '_scfs_feedback_helpful_count', true));
            $unhelpful = absint(get_post_meta($article_id, '_scfs_feedback_unhelpful_count', true));
            if ($score < 70 || $stale || $unhelpful > $helpful) {
                $record['articles_needing_attention'][] = array(
                    'id' => $article_id,
                    'title' => get_the_title($article_id),
                    'integrity_score' => $score,
                    'stale' => $stale,
                    'helpful' => $helpful,
                    'unhelpful' => $unhelpful,
                    'edit_url' => get_edit_post_link($article_id, 'raw'),
                );
            }
        }
        $record['average_integrity_score'] = round($integrity_total / max(1, count($ids)), 1);
        $record['freshness_percent'] = $this->percent($record['fresh_articles'], count($ids));
        usort($record['articles_needing_attention'], static function ($a, $b) {
            $left = ($a['stale'] ? 100 : 0) + $a['unhelpful'] - $a['helpful'] - $a['integrity_score'];
            $right = ($b['stale'] ? 100 : 0) + $b['unhelpful'] - $b['helpful'] - $b['integrity_score'];
            return $right <=> $left;
        });
        $record['articles_needing_attention'] = array_slice($record['articles_needing_attention'], 0, 20);

        if (class_exists('SCFS_Documentation_Feature_Intelligence') && isset($wpdb) && method_exists($wpdb, 'get_row')) {
            $table = SCFS_Documentation_Feature_Intelligence::instance()->feedback_table_name();
            if ($this->table_exists($table)) {
                $cutoff = gmdate('Y-m-d H:i:s', time() - absint($settings['lookback_days']) * DAY_IN_SECONDS);
                $where = ' WHERE created_at >= %s';
                $params = array($cutoff);
                if ($this->product_key($product) !== 'all') {
                    $where .= ' AND product = %s';
                    $params[] = $this->product_key($product);
                }
                $sql = "SELECT COUNT(*) AS responses, SUM(helpful) AS helpful,
                    SUM(CASE WHEN helpful = 0 THEN 1 ELSE 0 END) AS unhelpful FROM {$table}{$where}";
                $prepared = call_user_func_array(array($wpdb, 'prepare'), array_merge(array($sql), $params));
                $row = $wpdb->get_row($prepared, ARRAY_A); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
                if (is_array($row)) {
                    $record['feedback_responses'] = absint($row['responses'] ?? 0);
                    $record['helpful_responses'] = absint($row['helpful'] ?? 0);
                    $record['unhelpful_responses'] = absint($row['unhelpful'] ?? 0);
                    $record['helpfulness_percent'] = $this->percent($record['helpful_responses'], $record['feedback_responses']);
                }
            }
        }
        return $record;
    }

    private function count_linked_records($post_type, $product, $meta_key, $extra_meta = array()) {
        if (!function_exists('get_posts')) {
            return array('total' => 0, 'covered' => 0, 'coverage_percent' => 100.0);
        }
        $args = array(
            'post_type' => $post_type,
            'post_status' => array('publish', 'private', 'draft', 'pending'),
            'posts_per_page' => -1,
            'fields' => 'ids',
            'no_found_rows' => true,
        );
        $tax_query = $this->product_tax_query($product);
        if ($tax_query) {
            $args['tax_query'] = $tax_query;
        }
        $ids = array_map('absint', (array) get_posts($args));
        $covered = 0;
        foreach ($ids as $id) {
            $value = get_post_meta($id, $meta_key, true);
            $has_link = is_array($value) ? !empty(array_filter($value)) : trim((string) $value) !== '';
            foreach ($extra_meta as $key) {
                $extra = get_post_meta($id, $key, true);
                $has_link = $has_link || (is_array($extra) ? !empty(array_filter($extra)) : trim((string) $extra) !== '');
            }
            if ($has_link) {
                $covered++;
            }
        }
        return array('total' => count($ids), 'covered' => $covered, 'coverage_percent' => $this->percent($covered, count($ids)));
    }

    public function issue_coverage_metrics($product = 'all') {
        $type = class_exists('SCFS_Knowledge_Base_Foundation') ? SCFS_Knowledge_Base_Foundation::ISSUE_POST_TYPE : 'sc_known_issue';
        return $this->count_linked_records($type, $product, '_scfs_issue_related_article_ids', array('_scfs_issue_workaround'));
    }

    public function release_coverage_metrics($product = 'all') {
        $type = class_exists('SCFS_Product_Support_Platform') ? SCFS_Product_Support_Platform::RELEASE_POST_TYPE : 'sc_release_record';
        return $this->count_linked_records($type, $product, '_scfs_release_related_articles', array('_scfs_release_docs_url', '_scfs_release_changelog_url', '_scfs_release_support_note'));
    }

    public function gap_metrics($product = 'all') {
        if (!function_exists('get_posts')) {
            return array('total' => 0, 'open' => 0, 'resolved' => 0, 'linked' => 0, 'closure_percent' => 100.0, 'linkage_percent' => 100.0);
        }
        $type = class_exists('SCFS_Documentation_Feature_Intelligence') ? SCFS_Documentation_Feature_Intelligence::GAP_POST_TYPE : 'sc_doc_gap';
        $args = array('post_type' => $type, 'post_status' => array('publish','private','draft','pending'), 'posts_per_page' => -1, 'fields' => 'ids', 'no_found_rows' => true);
        $ids = array_map('absint', (array) get_posts($args));
        $record = array('total' => 0, 'open' => 0, 'resolved' => 0, 'linked' => 0, 'closure_percent' => 100.0, 'linkage_percent' => 100.0);
        foreach ($ids as $id) {
            $gap_product = $this->product_key(get_post_meta($id, '_scfs_gap_product', true));
            if ($this->product_key($product) !== 'all' && $gap_product !== $this->product_key($product)) {
                continue;
            }
            $record['total']++;
            $status = (string) get_post_meta($id, '_scfs_gap_status', true);
            if (in_array($status, array('resolved', 'closed', 'completed'), true)) {
                $record['resolved']++;
            } else {
                $record['open']++;
            }
            if (absint(get_post_meta($id, '_scfs_gap_linked_article_id', true))) {
                $record['linked']++;
            }
        }
        $record['closure_percent'] = $this->percent($record['resolved'], $record['total']);
        $record['linkage_percent'] = $this->percent($record['linked'], $record['total']);
        return $record;
    }

    public function evaluate_effectiveness($evidence) {
        $settings = $this->settings();
        $dimensions = array(
            'search_success' => array('label' => __('Search success', 'sustainable-catalyst-feature-suggestions'), 'score' => (float) ($evidence['resolution']['search_success_percent'] ?? 100), 'weight' => 0.20),
            'search_engagement' => array('label' => __('Search-to-guidance engagement', 'sustainable-catalyst-feature-suggestions'), 'score' => (float) ($evidence['resolution']['engagement_percent'] ?? 100), 'weight' => 0.10),
            'article_helpfulness' => array('label' => __('Article helpfulness', 'sustainable-catalyst-feature-suggestions'), 'score' => (float) ($evidence['articles']['helpfulness_percent'] ?? 100), 'weight' => 0.20),
            'publication_integrity' => array('label' => __('Publication integrity', 'sustainable-catalyst-feature-suggestions'), 'score' => (float) ($evidence['articles']['average_integrity_score'] ?? 100), 'weight' => 0.15),
            'content_freshness' => array('label' => __('Content freshness', 'sustainable-catalyst-feature-suggestions'), 'score' => (float) ($evidence['articles']['freshness_percent'] ?? 100), 'weight' => 0.10),
            'known_issue_coverage' => array('label' => __('Known Issue guidance coverage', 'sustainable-catalyst-feature-suggestions'), 'score' => (float) ($evidence['known_issue_coverage']['coverage_percent'] ?? 100), 'weight' => 0.10),
            'release_coverage' => array('label' => __('Release documentation coverage', 'sustainable-catalyst-feature-suggestions'), 'score' => (float) ($evidence['release_coverage']['coverage_percent'] ?? 100), 'weight' => 0.10),
            'gap_resolution' => array('label' => __('Documentation-gap resolution', 'sustainable-catalyst-feature-suggestions'), 'score' => (float) ($evidence['documentation_gaps']['closure_percent'] ?? 100), 'weight' => 0.05),
        );
        $score = 0.0;
        $output = array();
        foreach ($dimensions as $key => $dimension) {
            $value = max(0, min(100, $dimension['score']));
            $contribution = round($value * $dimension['weight'], 2);
            $score += $contribution;
            $output[] = array('key' => $key, 'label' => $dimension['label'], 'score' => round($value, 1), 'weight' => $dimension['weight'], 'contribution' => $contribution);
        }
        $score = round(max(0, min(100, $score)), 1);
        $evidence_count = absint($evidence['resolution']['searches'] ?? 0) + absint($evidence['articles']['feedback_responses'] ?? 0) + absint($evidence['articles']['published_articles'] ?? 0) + absint($evidence['known_issue_coverage']['total'] ?? 0) + absint($evidence['release_coverage']['total'] ?? 0);
        if ($evidence_count < absint($settings['minimum_evidence'])) {
            $state = 'insufficient_evidence';
        } elseif ($score >= (float) $settings['effective_threshold']) {
            $state = 'effective';
        } elseif ($score >= (float) $settings['healthy_threshold']) {
            $state = 'healthy';
        } elseif ($score >= (float) $settings['watch_threshold']) {
            $state = 'watch';
        } else {
            $state = 'intervention';
        }
        $recommendations = array();
        if (($evidence['resolution']['search_success_percent'] ?? 100) < 65) $recommendations[] = 'improve_search_relevance_and_no_results_recovery';
        if (($evidence['articles']['helpfulness_percent'] ?? 100) < 70) $recommendations[] = 'review_low_helpfulness_articles';
        if (($evidence['articles']['freshness_percent'] ?? 100) < 80) $recommendations[] = 'reverify_stale_support_articles';
        if (($evidence['known_issue_coverage']['coverage_percent'] ?? 100) < 80) $recommendations[] = 'link_known_issues_to_workarounds_and_articles';
        if (($evidence['release_coverage']['coverage_percent'] ?? 100) < 80) $recommendations[] = 'complete_release_documentation_relationships';
        if (($evidence['documentation_gaps']['closure_percent'] ?? 100) < 50 && ($evidence['documentation_gaps']['total'] ?? 0) > 0) $recommendations[] = 'prioritize_open_documentation_gaps';
        if (!$recommendations) $recommendations[] = 'continue_monitoring_documentation_effectiveness';
        return array(
            'schema' => self::SCHEMA,
            'version' => self::VERSION,
            'score' => $score,
            'state' => $state,
            'evidence_count' => $evidence_count,
            'dimensions' => $output,
            'recommendations' => array_values(array_unique($recommendations)),
            'administrator_only' => true,
            'personal_identifiers_exposed' => false,
            'raw_search_text_exposed' => false,
            'private_case_content_exposed' => false,
            'human_review_required' => true,
            'automatic_publication' => false,
            'automatic_issue_resolution' => false,
            'automatic_roadmap_changes' => false,
        );
    }

    public function product_record($product = 'all') {
        $product = $this->product_key($product);
        $evidence = array(
            'resolution' => $this->resolution_metrics($product),
            'articles' => $this->article_metrics($product),
            'known_issue_coverage' => $this->issue_coverage_metrics($product),
            'release_coverage' => $this->release_coverage_metrics($product),
            'documentation_gaps' => $this->gap_metrics($product),
        );
        return array(
            'schema' => self::SCHEMA,
            'version' => self::VERSION,
            'product' => $product,
            'effectiveness' => $this->evaluate_effectiveness($evidence),
            'evidence' => $evidence,
            'generated_at' => gmdate('c'),
        );
    }

    public function build_snapshot($force = false) {
        if (!$force) {
            $stored = function_exists('get_option') ? get_option(self::SNAPSHOT_OPTION, array()) : array();
            if (is_array($stored) && !empty($stored['products']) && !empty($stored['generated_at'])) {
                return $stored;
            }
        }
        $records = array();
        foreach ($this->products() as $product) {
            $records[] = $this->product_record($product['slug']);
        }
        usort($records, static function ($a, $b) {
            return ($a['effectiveness']['score'] ?? 0) <=> ($b['effectiveness']['score'] ?? 0);
        });
        $state_counts = array();
        foreach ($records as $record) {
            $state = (string) ($record['effectiveness']['state'] ?? 'insufficient_evidence');
            $state_counts[$state] = isset($state_counts[$state]) ? $state_counts[$state] + 1 : 1;
        }
        $snapshot = array(
            'schema' => 'scfs-support-analytics-portfolio/1.0',
            'version' => self::VERSION,
            'generated_at' => gmdate('c'),
            'lookback_days' => absint($this->settings()['lookback_days']),
            'products' => $records,
            'state_counts' => $state_counts,
            'lowest_effectiveness_products' => array_slice($records, 0, 10),
            'privacy' => array(
                'personal_identifiers_exposed' => false,
                'raw_search_text_exposed' => false,
                'private_case_content_exposed' => false,
            ),
            'governance' => array(
                'administrator_only' => true,
                'human_review_required' => true,
                'automatic_publication' => false,
                'automatic_issue_resolution' => false,
                'automatic_roadmap_changes' => false,
            ),
        );
        if (function_exists('update_option')) {
            update_option(self::SNAPSHOT_OPTION, $snapshot, false);
            update_option(self::LAST_REFRESH_OPTION, $snapshot['generated_at'], false);
            $this->store_history($snapshot);
        }
        return $snapshot;
    }

    private function store_history($snapshot) {
        $history = (array) get_option(self::HISTORY_OPTION, array());
        $history[gmdate('Y-m-d')] = array(
            'generated_at' => $snapshot['generated_at'],
            'products' => array_map(static function ($record) {
                return array('product' => $record['product'], 'score' => $record['effectiveness']['score'], 'state' => $record['effectiveness']['state']);
            }, $snapshot['products']),
        );
        $cutoff = time() - absint($this->settings()['history_days']) * DAY_IN_SECONDS;
        foreach (array_keys($history) as $date) {
            if (strtotime($date . ' 00:00:00 UTC') < $cutoff) unset($history[$date]);
        }
        ksort($history);
        update_option(self::HISTORY_OPTION, $history, false);
    }

    public function trend_record($product = 'all') {
        $history = (array) get_option(self::HISTORY_OPTION, array());
        $points = array();
        foreach ($history as $date => $snapshot) {
            foreach ((array) ($snapshot['products'] ?? array()) as $record) {
                if (($record['product'] ?? '') === $this->product_key($product)) {
                    $points[] = array('date' => $date, 'score' => (float) $record['score'], 'state' => (string) $record['state']);
                    break;
                }
            }
        }
        $direction = 'stable';
        $delta = 0.0;
        if (count($points) >= 2) {
            $delta = round(end($points)['score'] - reset($points)['score'], 1);
            $direction = $delta >= 3 ? 'improving' : ($delta <= -3 ? 'declining' : 'stable');
        }
        return array('product' => $this->product_key($product), 'direction' => $direction, 'score_delta' => $delta, 'points' => array_slice($points, -30));
    }

    public function run_scheduled_refresh() {
        if (($this->settings()['daily_refresh_enabled'] ?? '1') !== '1' || get_transient(self::CRON_LOCK)) {
            return;
        }
        set_transient(self::CRON_LOCK, '1', 30 * MINUTE_IN_SECONDS);
        $this->build_snapshot(true);
        delete_transient(self::CRON_LOCK);
    }

    public function refresh_action() {
        if (!$this->can_manage()) wp_die(__('You do not have permission to refresh Support Analytics.', 'sustainable-catalyst-feature-suggestions'));
        check_admin_referer(self::NONCE_ACTION);
        $this->build_snapshot(true);
        wp_safe_redirect(admin_url('edit.php?post_type=' . Sustainable_Catalyst_Feature_Suggestions::POST_TYPE . '&page=' . self::ADMIN_PAGE . '&refreshed=1'));
        exit;
    }

    public function export_action() {
        if (!$this->can_manage()) wp_die(__('You do not have permission to export Support Analytics.', 'sustainable-catalyst-feature-suggestions'));
        check_admin_referer(self::NONCE_ACTION);
        $snapshot = $this->build_snapshot(false);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=scfs-support-analytics-' . gmdate('Y-m-d') . '.csv');
        $out = fopen('php://output', 'w');
        fputcsv($out, array('product','effectiveness_score','state','searches','search_success_percent','engagement_percent','published_articles','feedback_responses','helpfulness_percent','integrity_score','freshness_percent','issue_coverage_percent','release_coverage_percent','gap_closure_percent','generated_at'));
        foreach ((array) $snapshot['products'] as $record) {
            $e = $record['evidence'];
            fputcsv($out, array($record['product'], $record['effectiveness']['score'], $record['effectiveness']['state'], $e['resolution']['searches'], $e['resolution']['search_success_percent'], $e['resolution']['engagement_percent'], $e['articles']['published_articles'], $e['articles']['feedback_responses'], $e['articles']['helpfulness_percent'], $e['articles']['average_integrity_score'], $e['articles']['freshness_percent'], $e['known_issue_coverage']['coverage_percent'], $e['release_coverage']['coverage_percent'], $e['documentation_gaps']['closure_percent'], $record['generated_at']));
        }
        fclose($out);
        exit;
    }

    public function register_rest_routes() {
        register_rest_route(Sustainable_Catalyst_Feature_Suggestions::REST_NAMESPACE, '/support-analytics/schema', array('methods' => 'GET', 'callback' => array($this, 'rest_schema'), 'permission_callback' => array($this, 'can_manage')));
        register_rest_route(Sustainable_Catalyst_Feature_Suggestions::REST_NAMESPACE, '/support-analytics/summary', array('methods' => 'GET', 'callback' => array($this, 'rest_summary'), 'permission_callback' => array($this, 'can_manage')));
        register_rest_route(Sustainable_Catalyst_Feature_Suggestions::REST_NAMESPACE, '/support-analytics/products', array('methods' => 'GET', 'callback' => array($this, 'rest_products'), 'permission_callback' => array($this, 'can_manage')));
        register_rest_route(Sustainable_Catalyst_Feature_Suggestions::REST_NAMESPACE, '/support-analytics/product/(?P<product>[a-z0-9\-]+)', array('methods' => 'GET', 'callback' => array($this, 'rest_product'), 'permission_callback' => array($this, 'can_manage')));
        register_rest_route(Sustainable_Catalyst_Feature_Suggestions::REST_NAMESPACE, '/support-analytics/trend/(?P<product>[a-z0-9\-]+)', array('methods' => 'GET', 'callback' => array($this, 'rest_trend'), 'permission_callback' => array($this, 'can_manage')));
        register_rest_route(Sustainable_Catalyst_Feature_Suggestions::REST_NAMESPACE, '/support-analytics/refresh', array('methods' => 'POST', 'callback' => array($this, 'rest_refresh'), 'permission_callback' => array($this, 'can_manage')));
    }

    public function rest_schema() {
        return rest_ensure_response(array('schema' => self::SCHEMA, 'version' => self::VERSION, 'administrator_only' => true, 'personal_identifiers_exposed' => false, 'raw_search_text_exposed' => false, 'private_case_content_exposed' => false, 'human_review_required' => true));
    }

    public function rest_summary() { return rest_ensure_response($this->build_snapshot(false)); }
    public function rest_products() { return rest_ensure_response(array('products' => $this->products())); }
    public function rest_product($request) { return rest_ensure_response($this->product_record($request['product'])); }
    public function rest_trend($request) { return rest_ensure_response($this->trend_record($request['product'])); }
    public function rest_refresh() { return rest_ensure_response($this->build_snapshot(true)); }

    private function state_label($state) {
        $labels = array('insufficient_evidence' => __('Insufficient evidence', 'sustainable-catalyst-feature-suggestions'), 'effective' => __('Effective', 'sustainable-catalyst-feature-suggestions'), 'healthy' => __('Healthy', 'sustainable-catalyst-feature-suggestions'), 'watch' => __('Watch', 'sustainable-catalyst-feature-suggestions'), 'intervention' => __('Intervention review', 'sustainable-catalyst-feature-suggestions'));
        return $labels[$state] ?? ucwords(str_replace('_', ' ', $state));
    }

    private function render_snapshot($snapshot) {
        $all = null;
        foreach ((array) $snapshot['products'] as $record) if ($record['product'] === 'all') { $all = $record; break; }
        if (!$all && !empty($snapshot['products'])) $all = $snapshot['products'][0];
        echo '<div class="scfs-analytics-shell" data-scfs-support-analytics>';
        if ($all) {
            echo '<section class="scfs-analytics-hero"><div><p class="scfs-analytics-eyebrow">' . esc_html__('Support Analytics', 'sustainable-catalyst-feature-suggestions') . '</p><h2>' . esc_html__('Documentation effectiveness', 'sustainable-catalyst-feature-suggestions') . '</h2><p>' . esc_html__('Review how effectively public guidance helps people find answers, use Support Articles, understand Known Issues, and navigate releases.', 'sustainable-catalyst-feature-suggestions') . '</p></div><div class="scfs-analytics-score"><strong>' . esc_html(number_format_i18n($all['effectiveness']['score'], 1)) . '</strong><span>' . esc_html($this->state_label($all['effectiveness']['state'])) . '</span></div></section>';
            $e = $all['evidence'];
            echo '<div class="scfs-analytics-metrics">';
            foreach (array(
                __('Search success', 'sustainable-catalyst-feature-suggestions') => $e['resolution']['search_success_percent'] . '%',
                __('Article helpfulness', 'sustainable-catalyst-feature-suggestions') => $e['articles']['helpfulness_percent'] . '%',
                __('Content freshness', 'sustainable-catalyst-feature-suggestions') => $e['articles']['freshness_percent'] . '%',
                __('Issue coverage', 'sustainable-catalyst-feature-suggestions') => $e['known_issue_coverage']['coverage_percent'] . '%',
                __('Release coverage', 'sustainable-catalyst-feature-suggestions') => $e['release_coverage']['coverage_percent'] . '%',
                __('Gap closure', 'sustainable-catalyst-feature-suggestions') => $e['documentation_gaps']['closure_percent'] . '%',
            ) as $label => $value) echo '<article><span>' . esc_html($label) . '</span><strong>' . esc_html($value) . '</strong></article>';
            echo '</div>';
        }
        echo '<section class="scfs-analytics-table-wrap"><h3>' . esc_html__('Product effectiveness', 'sustainable-catalyst-feature-suggestions') . '</h3><table class="widefat striped"><thead><tr><th>' . esc_html__('Product', 'sustainable-catalyst-feature-suggestions') . '</th><th>' . esc_html__('Score', 'sustainable-catalyst-feature-suggestions') . '</th><th>' . esc_html__('State', 'sustainable-catalyst-feature-suggestions') . '</th><th>' . esc_html__('Search', 'sustainable-catalyst-feature-suggestions') . '</th><th>' . esc_html__('Helpful', 'sustainable-catalyst-feature-suggestions') . '</th><th>' . esc_html__('Fresh', 'sustainable-catalyst-feature-suggestions') . '</th><th>' . esc_html__('Needs attention', 'sustainable-catalyst-feature-suggestions') . '</th></tr></thead><tbody>';
        foreach ((array) $snapshot['products'] as $record) {
            $e = $record['evidence'];
            echo '<tr><td><strong>' . esc_html(ucwords(str_replace('-', ' ', $record['product']))) . '</strong></td><td>' . esc_html(number_format_i18n($record['effectiveness']['score'], 1)) . '</td><td><span class="scfs-analytics-state scfs-analytics-state--' . esc_attr($record['effectiveness']['state']) . '">' . esc_html($this->state_label($record['effectiveness']['state'])) . '</span></td><td>' . esc_html(number_format_i18n($e['resolution']['search_success_percent'], 1)) . '%</td><td>' . esc_html(number_format_i18n($e['articles']['helpfulness_percent'], 1)) . '%</td><td>' . esc_html(number_format_i18n($e['articles']['freshness_percent'], 1)) . '%</td><td>' . esc_html((string) count($e['articles']['articles_needing_attention'])) . '</td></tr>';
        }
        echo '</tbody></table></section><p class="scfs-analytics-boundary">' . esc_html__('Analytics are privacy-minimized, administrator-only, and advisory. They do not expose raw searches, private case content, or requester identities, and they never publish, resolve, or reprioritize records automatically.', 'sustainable-catalyst-feature-suggestions') . '</p></div>';
    }

    public function render_admin_page() {
        if (!$this->can_manage()) wp_die(__('You do not have permission to view Support Analytics.', 'sustainable-catalyst-feature-suggestions'));
        $snapshot = $this->build_snapshot(false);
        $refresh = wp_nonce_url(admin_url('admin-post.php?action=scfs_support_analytics_refresh'), self::NONCE_ACTION);
        $export = wp_nonce_url(admin_url('admin-post.php?action=scfs_support_analytics_export'), self::NONCE_ACTION);
        echo '<div class="wrap"><h1>' . esc_html__('Support Analytics and Documentation Effectiveness', 'sustainable-catalyst-feature-suggestions') . '</h1><p>' . esc_html__('Measure whether public support guidance is discoverable, useful, current, and connected to operational product context.', 'sustainable-catalyst-feature-suggestions') . '</p><p><a class="button button-primary" href="' . esc_url($refresh) . '">' . esc_html__('Refresh analytics', 'sustainable-catalyst-feature-suggestions') . '</a> <a class="button" href="' . esc_url($export) . '">' . esc_html__('Export CSV', 'sustainable-catalyst-feature-suggestions') . '</a></p>';
        $this->render_snapshot($snapshot);
        echo '</div>';
    }

    public function render_shortcode() {
        if (!$this->can_manage()) return '';
        ob_start();
        $this->render_snapshot($this->build_snapshot(false));
        return ob_get_clean();
    }

    public function cli_refresh() { $snapshot = $this->build_snapshot(true); WP_CLI::success('Support Analytics refreshed at ' . $snapshot['generated_at']); }
    public function cli_summary() { WP_CLI::line(wp_json_encode($this->build_snapshot(false), JSON_PRETTY_PRINT)); }
    public function cli_product($args = array()) { $product = isset($args[0]) ? $args[0] : 'all'; WP_CLI::line(wp_json_encode($this->product_record($product), JSON_PRETTY_PRINT)); }
}
