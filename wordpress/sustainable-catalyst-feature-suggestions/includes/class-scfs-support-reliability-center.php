<?php
/**
 * Support Analytics and Product Reliability Center.
 *
 * Aggregates privacy-minimized support, documentation, issue, release,
 * editorial, onboarding, and repository signals into reviewable product
 * reliability records. Scores are advisory and never alter the roadmap or
 * declare incidents automatically.
 *
 * @package Sustainable_Catalyst_Feature_Suggestions
 */

if (!defined('ABSPATH')) {
    exit;
}

final class SCFS_Support_Reliability_Center {
    const VERSION = '5.1.0';
    const SCHEMA_VERSION = '1.0';
    const OPTION_KEY = 'scfs_support_reliability_settings';
    const SNAPSHOT_OPTION = 'scfs_support_reliability_snapshots';
    const LAST_RUN_OPTION = 'scfs_support_reliability_last_run';
    const CRON_HOOK = 'scfs_support_reliability_daily_snapshot';
    const CRON_LOCK = 'scfs_support_reliability_snapshot_lock';

    private static $instance = null;

    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_menu', array($this, 'register_admin_page'), 34);
        add_action('admin_enqueue_scripts', array($this, 'admin_assets'));
        add_action('rest_api_init', array($this, 'register_rest_routes'));
        add_action('admin_post_scfs_refresh_support_reliability', array($this, 'refresh_action'));
        add_action('admin_post_scfs_save_support_reliability_settings', array($this, 'save_settings_action'));
        add_action('admin_post_scfs_export_support_reliability_csv', array($this, 'export_csv_action'));
        add_action('admin_post_scfs_export_support_reliability_json', array($this, 'export_json_action'));
        add_action(self::CRON_HOOK, array($this, 'run_daily_snapshot'));
    }

    public static function activate() {
        $instance = self::instance();
        if (!get_option(self::OPTION_KEY)) {
            add_option(self::OPTION_KEY, $instance->default_settings(), '', false);
        }
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK);
        }
    }

    public static function deactivate() {
        $timestamp = wp_next_scheduled(self::CRON_HOOK);
        if ($timestamp) {
            wp_unschedule_event($timestamp, self::CRON_HOOK);
        }
        delete_transient(self::CRON_LOCK);
    }

    public function default_settings() {
        return array(
            'analysis_window_days' => '30',
            'snapshot_retention_days' => '180',
            'healthy_threshold' => '80',
            'watch_threshold' => '60',
            'attention_threshold' => '45',
            'high_priority_gap_threshold' => '70',
            'cluster_minimum_searches' => '2',
            'show_products_without_activity' => '1',
        );
    }

    public function settings() {
        $saved = get_option(self::OPTION_KEY, array());
        return wp_parse_args(is_array($saved) ? $saved : array(), $this->default_settings());
    }

    public function admin_capability() {
        $default = is_multisite() && is_network_admin() ? 'manage_network_options' : 'manage_options';
        return apply_filters('scfs_support_reliability_capability', $default);
    }

    public function can_manage() {
        return current_user_can($this->admin_capability());
    }

    public function schema_record() {
        return array(
            'schema' => 'scfs-support-reliability-center/' . self::SCHEMA_VERSION,
            'version' => self::VERSION,
            'signals' => array(
                'guided_resolution_success',
                'documentation_helpfulness',
                'known_issue_health',
                'release_readiness',
                'support_content_readiness',
                'repository_and_link_health',
                'editorial_governance_health',
                'documentation_gap_priority',
                'support_demand',
            ),
            'capabilities' => array(
                'product_reliability_scoring',
                'support_resolution_rate_analysis',
                'documentation_usefulness_trends',
                'known_issue_recurrence',
                'release_readiness_aggregation',
                'unresolved_query_clustering',
                'documentation_gap_prioritization',
                'scheduled_daily_snapshots',
                'csv_and_json_reliability_reports',
                'sha256_report_integrity',
            ),
            'privacy' => array(
                'ip_addresses_stored' => false,
                'private_case_content_stored' => false,
                'private_contact_details_stored' => false,
                'opaque_case_relationship_counts_only' => true,
            ),
            'governance' => array(
                'human_review_required' => true,
                'automatic_roadmap_change' => false,
                'automatic_incident_declaration' => false,
                'automatic_publication' => false,
            ),
        );
    }

    public function calculate_reliability_score($evidence) {
        $evidence = is_array($evidence) ? $evidence : array();
        $weights = array(
            'resolution_success' => 0.25,
            'documentation_helpfulness' => 0.20,
            'known_issue_health' => 0.15,
            'release_readiness' => 0.15,
            'content_readiness' => 0.10,
            'repository_health' => 0.10,
            'governance_health' => 0.05,
        );
        $labels = array(
            'resolution_success' => __('Resolution success', 'sustainable-catalyst-feature-suggestions'),
            'documentation_helpfulness' => __('Documentation usefulness', 'sustainable-catalyst-feature-suggestions'),
            'known_issue_health' => __('Known-issue health', 'sustainable-catalyst-feature-suggestions'),
            'release_readiness' => __('Release readiness', 'sustainable-catalyst-feature-suggestions'),
            'content_readiness' => __('Content readiness', 'sustainable-catalyst-feature-suggestions'),
            'repository_health' => __('Repository health', 'sustainable-catalyst-feature-suggestions'),
            'governance_health' => __('Editorial governance', 'sustainable-catalyst-feature-suggestions'),
        );
        $dimensions = array();
        $score = 0.0;
        foreach ($weights as $key => $weight) {
            $value = max(0, min(100, (float) ($evidence[$key] ?? 0)));
            $contribution = round($value * $weight, 2);
            $score += $contribution;
            $dimensions[] = array(
                'key' => $key,
                'label' => $labels[$key],
                'score' => round($value, 1),
                'weight' => $weight,
                'contribution' => $contribution,
            );
        }
        $blockers = array();
        $signals = array();
        $critical = absint($evidence['critical_open_issues'] ?? 0);
        $gaps = absint($evidence['high_priority_gaps'] ?? 0);
        $overdue = absint($evidence['overdue_reviews'] ?? 0);
        $unresolved = absint($evidence['unresolved_searches'] ?? 0);
        if ($critical) {
            $blockers[] = 'critical_open_issues';
            $score -= min(30, $critical * 12);
        }
        if ($gaps) {
            $blockers[] = 'high_priority_documentation_gaps';
            $score -= min(15, $gaps * 3);
        }
        if ($overdue) {
            $blockers[] = 'overdue_editorial_reviews';
            $score -= min(10, $overdue * 2);
        }
        if ($unresolved) {
            $signals[] = sprintf(__('%d unresolved searches require review.', 'sustainable-catalyst-feature-suggestions'), $unresolved);
        }
        if (($evidence['documentation_helpfulness'] ?? 0) < 60) {
            $signals[] = __('Documentation usefulness is below the review threshold.', 'sustainable-catalyst-feature-suggestions');
        }
        if (($evidence['repository_health'] ?? 0) < 70) {
            $signals[] = __('Repository drift or link health needs attention.', 'sustainable-catalyst-feature-suggestions');
        }
        $score = round(max(0, min(100, $score)), 1);
        $settings = $this->settings();
        if ($blockers && $score < (float) $settings['attention_threshold']) {
            $state = 'critical';
        } elseif ($score < (float) $settings['watch_threshold']) {
            $state = 'attention';
        } elseif ($score < (float) $settings['healthy_threshold']) {
            $state = 'watch';
        } else {
            $state = 'healthy';
        }
        return array(
            'schema' => 'scfs-product-reliability-score/' . self::SCHEMA_VERSION,
            'version' => self::VERSION,
            'score' => $score,
            'state' => $state,
            'dimensions' => $dimensions,
            'blockers' => $blockers,
            'signals' => $signals,
            'human_review_required' => true,
            'automatic_roadmap_change' => false,
        );
    }

    public function prioritize_cluster($evidence) {
        $evidence = is_array($evidence) ? $evidence : array();
        $searches = max(1, absint($evidence['searches'] ?? 0));
        $no_match = absint($evidence['no_match_searches'] ?? 0);
        $low = absint($evidence['low_confidence_searches'] ?? 0);
        $handoffs = absint($evidence['private_handoffs'] ?? 0);
        $negative = absint($evidence['negative_feedback'] ?? 0);
        $cases = absint($evidence['related_cases'] ?? 0);
        $recency = absint($evidence['recency_days'] ?? 30);
        $products = max(1, absint($evidence['product_count'] ?? 1));
        $score = min(35, $searches * 3);
        $score += min(1, $no_match / $searches) * 20;
        $score += min(1, $low / $searches) * 10;
        $score += min(15, $handoffs * 4);
        $score += min(10, $negative * 2.5);
        $score += min(10, $cases * 2.5);
        $score += $recency <= 7 ? 8 : ($recency <= 30 ? 4 : 0);
        $score += min(5, max(0, $products - 1) * 1.5);
        $score = round(max(0, min(100, $score)), 1);
        $priority = $score >= 80 ? 'critical' : ($score >= 60 ? 'high' : ($score >= 35 ? 'medium' : 'low'));
        return array('score' => $score, 'priority' => $priority, 'human_review_required' => true);
    }

    private function date_cutoff($days = null) {
        $days = $days === null ? absint($this->settings()['analysis_window_days']) : absint($days);
        return gmdate('Y-m-d H:i:s', time() - max(1, $days) * DAY_IN_SECONDS);
    }

    private function product_filter_sql($product_slug, $column = 'filters_json') {
        global $wpdb;
        if (!$product_slug || !isset($wpdb) || !method_exists($wpdb, 'prepare')) {
            return array('', array());
        }
        $needle = '%"product":"' . $wpdb->esc_like(sanitize_title($product_slug)) . '"%';
        return array(" AND {$column} LIKE %s", array($needle));
    }

    public function resolution_metrics($product_slug = '', $days = null) {
        global $wpdb;
        $result = array(
            'searches' => 0,
            'resolved_searches' => 0,
            'no_match_searches' => 0,
            'low_confidence_searches' => 0,
            'viewed_searches' => 0,
            'resolution_success_percent' => 0,
            'unresolved_searches' => 0,
        );
        if (!class_exists('SCFS_Guided_Resolution') || !isset($wpdb->prefix) || !method_exists($wpdb, 'get_row')) {
            return $result;
        }
        $table = SCFS_Guided_Resolution::instance()->table_name();
        $cutoff = $this->date_cutoff($days);
        list($product_sql, $params) = $this->product_filter_sql($product_slug);
        $sql = "SELECT COUNT(*) AS searches,
            SUM(CASE WHEN resolution_state NOT IN ('no_match','low_confidence') AND result_count > 0 THEN 1 ELSE 0 END) AS resolved_searches,
            SUM(CASE WHEN resolution_state = 'no_match' THEN 1 ELSE 0 END) AS no_match_searches,
            SUM(CASE WHEN resolution_state = 'low_confidence' THEN 1 ELSE 0 END) AS low_confidence_searches,
            SUM(CASE WHEN viewed_json IS NOT NULL AND viewed_json NOT IN ('','[]') THEN 1 ELSE 0 END) AS viewed_searches
            FROM {$table} WHERE event_type = 'search' AND created_at >= %s{$product_sql}";
        $query_params = array_merge(array($cutoff), $params);
        $prepared = call_user_func_array(array($wpdb, 'prepare'), array_merge(array($sql), $query_params));
        $row = $wpdb->get_row($prepared, ARRAY_A); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        if (!is_array($row)) {
            return $result;
        }
        foreach (array('searches','resolved_searches','no_match_searches','low_confidence_searches','viewed_searches') as $key) {
            $result[$key] = absint($row[$key] ?? 0);
        }
        $result['unresolved_searches'] = $result['no_match_searches'] + $result['low_confidence_searches'];
        $result['resolution_success_percent'] = $result['searches'] ? round(($result['resolved_searches'] / $result['searches']) * 100, 1) : 100;
        return $result;
    }

    public function documentation_metrics($product_slug = '', $days = null) {
        global $wpdb;
        $result = array('responses' => 0, 'helpful' => 0, 'unhelpful' => 0, 'helpfulness_percent' => 100, 'case_relationships' => 0);
        if (!class_exists('SCFS_Documentation_Feature_Intelligence') || !isset($wpdb->prefix) || !method_exists($wpdb, 'get_row')) {
            return $result;
        }
        $intel = SCFS_Documentation_Feature_Intelligence::instance();
        $feedback = $intel->feedback_table_name();
        $relationships = $intel->relationship_table_name();
        $cutoff = $this->date_cutoff($days);
        $product_where = '';
        $params = array($cutoff);
        if ($product_slug) {
            $product_where = ' AND product = %s';
            $params[] = sanitize_title($product_slug);
        }
        $sql = "SELECT COUNT(*) AS responses, SUM(helpful) AS helpful, SUM(CASE WHEN helpful = 0 THEN 1 ELSE 0 END) AS unhelpful FROM {$feedback} WHERE created_at >= %s{$product_where}";
        $prepared = call_user_func_array(array($wpdb, 'prepare'), array_merge(array($sql), $params));
        $row = $wpdb->get_row($prepared, ARRAY_A); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        if (is_array($row)) {
            $result['responses'] = absint($row['responses'] ?? 0);
            $result['helpful'] = absint($row['helpful'] ?? 0);
            $result['unhelpful'] = absint($row['unhelpful'] ?? 0);
            $result['helpfulness_percent'] = $result['responses'] ? round(($result['helpful'] / $result['responses']) * 100, 1) : 100;
        }
        if (method_exists($wpdb, 'get_var')) {
            $rel_sql = "SELECT COUNT(*) FROM {$relationships} WHERE updated_at >= %s";
            $rel_params = array($cutoff);
            if ($product_slug) {
                $rel_sql .= ' AND product = %s';
                $rel_params[] = sanitize_title($product_slug);
            }
            $prepared = call_user_func_array(array($wpdb, 'prepare'), array_merge(array($rel_sql), $rel_params));
            $result['case_relationships'] = absint($wpdb->get_var($prepared)); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        }
        return $result;
    }

    private function product_tax_query($product_slug) {
        if (!$product_slug) {
            return array();
        }
        return array(array(
            'taxonomy' => SCFS_Product_Integration::PRODUCT_TAXONOMY,
            'field' => 'slug',
            'terms' => sanitize_title($product_slug),
        ));
    }

    public function known_issue_metrics($product_slug = '') {
        $result = array('total' => 0, 'active' => 0, 'critical' => 0, 'high' => 0, 'resolved' => 0, 'health_percent' => 100, 'recurring' => array());
        if (!class_exists('SCFS_Knowledge_Base_Foundation') || !class_exists('WP_Query')) {
            return $result;
        }
        $args = array(
            'post_type' => SCFS_Knowledge_Base_Foundation::ISSUE_POST_TYPE,
            'post_status' => array('publish', 'draft', 'pending', 'private'),
            'posts_per_page' => 250,
            'no_found_rows' => true,
        );
        if ($product_slug) {
            $args['tax_query'] = $this->product_tax_query($product_slug);
        }
        $query = new WP_Query($args);
        foreach ((array) $query->posts as $post) {
            $post_id = is_object($post) ? $post->ID : absint($post);
            $status = sanitize_key(get_post_meta($post_id, '_scfs_issue_status', true) ?: 'investigating');
            $severity = sanitize_key(get_post_meta($post_id, '_scfs_issue_severity', true) ?: 'moderate');
            $result['total']++;
            if (in_array($status, array('resolved', 'closed'), true)) {
                $result['resolved']++;
                continue;
            }
            $result['active']++;
            if ($severity === 'critical') {
                $result['critical']++;
            } elseif (in_array($severity, array('high', 'major'), true)) {
                $result['high']++;
            }
            $case_count = class_exists('SCFS_Documentation_Feature_Intelligence') ? SCFS_Documentation_Feature_Intelligence::instance()->relationship_count('case_article', $post_id) : 0;
            if ($case_count > 1) {
                $result['recurring'][] = array('id' => $post_id, 'title' => get_the_title($post_id), 'cases' => $case_count, 'severity' => $severity, 'status' => $status);
            }
        }
        usort($result['recurring'], function ($a, $b) { return $b['cases'] <=> $a['cases']; });
        $penalty = min(100, ($result['critical'] * 35) + ($result['high'] * 15) + (max(0, $result['active'] - $result['critical'] - $result['high']) * 5));
        $result['health_percent'] = max(0, 100 - $penalty);
        $result['recurring'] = array_slice($result['recurring'], 0, 10);
        return $result;
    }

    public function release_metrics($product_slug = '') {
        $result = array('records' => 0, 'average_readiness' => 100, 'not_ready' => 0, 'review' => 0, 'ready' => 0, 'blockers' => array());
        if (!class_exists('SCFS_Product_Support_Platform') || !class_exists('WP_Query')) {
            return $result;
        }
        $args = array('post_type' => SCFS_Product_Support_Platform::RELEASE_POST_TYPE, 'post_status' => array('publish','draft','pending','private'), 'posts_per_page' => 100, 'no_found_rows' => true);
        if ($product_slug) {
            $args['tax_query'] = $this->product_tax_query($product_slug);
        }
        $query = new WP_Query($args);
        $total = 0;
        foreach ((array) $query->posts as $post) {
            $post_id = is_object($post) ? $post->ID : absint($post);
            $readiness = SCFS_Product_Support_Platform::instance()->calculate_release_readiness($post_id);
            $result['records']++;
            $total += (float) ($readiness['score'] ?? 0);
            $state = sanitize_key($readiness['state'] ?? 'not_ready');
            if (isset($result[$state])) {
                $result[$state]++;
            }
            foreach ((array) ($readiness['blockers'] ?? array()) as $blocker) {
                $result['blockers'][$blocker] = ($result['blockers'][$blocker] ?? 0) + 1;
            }
        }
        $result['average_readiness'] = $result['records'] ? round($total / $result['records'], 1) : 100;
        arsort($result['blockers']);
        return $result;
    }

    public function content_readiness_metrics($product) {
        $default = array('score' => 100, 'state' => 'ready', 'blockers' => array(), 'warnings' => array());
        if (!$product || !class_exists('SCFS_Support_Content_Operations')) {
            return $default;
        }
        $record = SCFS_Support_Content_Operations::instance()->readiness_record($product);
        if (!is_array($record)) {
            return $default;
        }
        return array(
            'score' => (float) ($record['score'] ?? 0),
            'state' => sanitize_key($record['state'] ?? 'not_ready'),
            'blockers' => array_values((array) ($record['blockers'] ?? array())),
            'warnings' => array_values((array) ($record['warnings'] ?? array())),
        );
    }

    public function repository_metrics($product_id = 0) {
        $result = array('health_percent' => 100, 'drift_attention' => 0, 'broken_links' => 0, 'timeout_links' => 0, 'mapped' => false);
        if (!$product_id || !class_exists('SCFS_Repository_Release_Synchronization')) {
            return $result;
        }
        $sync = SCFS_Repository_Release_Synchronization::instance();
        $mapping = $sync->mapping($product_id);
        $result['mapped'] = !empty($mapping['repository_url']);
        $drift = $sync->drift_report($product_id);
        foreach ((array) ($drift['records'] ?? array()) as $record) {
            if (!empty($record['attention_required']) || !in_array(($record['state'] ?? ''), array('aligned','unchanged'), true)) {
                $result['drift_attention']++;
            }
        }
        if (class_exists('WP_Query')) {
            $query = new WP_Query(array(
                'post_type' => $sync->managed_post_types(),
                'post_status' => array('publish','future','draft','pending','private'),
                'posts_per_page' => 250,
                'fields' => 'ids',
                'no_found_rows' => true,
                'tax_query' => array(array(
                    'taxonomy' => SCFS_Product_Integration::PRODUCT_TAXONOMY,
                    'field' => 'term_id',
                    'terms' => absint($product_id),
                )),
            ));
            foreach ((array) $query->posts as $post_id) {
                $result['broken_links'] += absint(get_post_meta($post_id, '_scfs_sync_broken_link_count', true));
                $report = json_decode((string) get_post_meta($post_id, '_scfs_sync_link_report', true), true);
                foreach ((array) $report as $item) {
                    if (!empty($item['error']) && stripos((string) $item['error'], 'timed out') !== false) {
                        $result['timeout_links']++;
                    }
                }
            }
        }
        $penalty = min(100, ($result['drift_attention'] * 10) + ($result['broken_links'] * 12) + ($result['timeout_links'] * 6) + ($result['mapped'] ? 0 : 10));
        $result['health_percent'] = max(0, 100 - $penalty);
        return $result;
    }

    public function governance_metrics() {
        $default = array('health_percent' => 100, 'overdue_reviews' => 0, 'standards_blocked' => 0, 'expiring_records' => 0, 'total' => 0);
        if (!class_exists('SCFS_Editorial_Governance')) {
            return $default;
        }
        $summary = SCFS_Editorial_Governance::instance()->governance_summary();
        $default['overdue_reviews'] = absint($summary['overdue_reviews'] ?? 0);
        $default['standards_blocked'] = absint($summary['standards_blocked'] ?? 0);
        $default['expiring_records'] = absint($summary['expiring_records'] ?? 0);
        $default['total'] = absint($summary['total'] ?? 0);
        $penalty = min(100, ($default['overdue_reviews'] * 8) + ($default['standards_blocked'] * 6) + ($default['expiring_records'] * 2));
        $default['health_percent'] = max(0, 100 - $penalty);
        return $default;
    }

    public function gap_metrics($product_slug = '') {
        $result = array('open' => 0, 'high_priority' => 0, 'top' => array());
        if (!class_exists('SCFS_Documentation_Feature_Intelligence')) {
            return $result;
        }
        $settings = $this->settings();
        $args = array('post_type' => SCFS_Documentation_Feature_Intelligence::GAP_POST_TYPE, 'post_status' => array('publish','draft','pending','private'), 'posts_per_page' => 200, 'fields' => 'ids', 'no_found_rows' => true);
        $ids = get_posts($args);
        foreach ((array) $ids as $id) {
            $gap_product = sanitize_title(get_post_meta($id, '_scfs_gap_product', true));
            if ($product_slug && $gap_product && $gap_product !== sanitize_title($product_slug)) {
                continue;
            }
            $status = sanitize_key(get_post_meta($id, '_scfs_gap_status', true) ?: 'open');
            if ($status !== 'open') {
                continue;
            }
            $score = (float) get_post_meta($id, '_scfs_gap_score', true);
            $result['open']++;
            if ($score >= (float) $settings['high_priority_gap_threshold']) {
                $result['high_priority']++;
            }
            $result['top'][] = array(
                'id' => absint($id),
                'title' => get_the_title($id),
                'score' => round($score, 1),
                'searches' => absint(get_post_meta($id, '_scfs_gap_search_count', true)),
                'negative_feedback' => absint(get_post_meta($id, '_scfs_gap_negative_feedback_count', true)),
                'cases' => absint(get_post_meta($id, '_scfs_gap_case_count', true)),
                'edit_url' => get_edit_post_link($id, 'raw'),
            );
        }
        usort($result['top'], function ($a, $b) { return $b['score'] <=> $a['score']; });
        $result['top'] = array_slice($result['top'], 0, 10);
        return $result;
    }

    public function unresolved_clusters($product_slug = '', $days = null, $limit = 20) {
        global $wpdb;
        $records = array();
        if (!class_exists('SCFS_Guided_Resolution') || !isset($wpdb->prefix) || !method_exists($wpdb, 'get_results')) {
            return $records;
        }
        $table = SCFS_Guided_Resolution::instance()->table_name();
        $cutoff = $this->date_cutoff($days);
        list($product_sql, $params) = $this->product_filter_sql($product_slug);
        $sql = "SELECT query_hash, MAX(query_text) AS query_text, COUNT(*) AS searches,
            SUM(CASE WHEN resolution_state='no_match' THEN 1 ELSE 0 END) AS no_match_searches,
            SUM(CASE WHEN resolution_state='low_confidence' THEN 1 ELSE 0 END) AS low_confidence_searches,
            MAX(created_at) AS last_seen
            FROM {$table} WHERE event_type='search' AND resolution_state IN ('no_match','low_confidence') AND created_at >= %s{$product_sql}
            GROUP BY query_hash ORDER BY searches DESC, last_seen DESC LIMIT %d";
        $query_params = array_merge(array($cutoff), $params, array(max(1, min(100, absint($limit)))));
        $prepared = call_user_func_array(array($wpdb, 'prepare'), array_merge(array($sql), $query_params));
        $rows = $wpdb->get_results($prepared, ARRAY_A); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $minimum = max(1, absint($this->settings()['cluster_minimum_searches']));
        foreach ((array) $rows as $row) {
            $searches = absint($row['searches'] ?? 0);
            if ($searches < $minimum) {
                continue;
            }
            $last_seen = sanitize_text_field($row['last_seen'] ?? '');
            $age = $last_seen ? max(0, (int) floor((time() - strtotime($last_seen . ' UTC')) / DAY_IN_SECONDS)) : 999;
            $priority = $this->prioritize_cluster(array(
                'searches' => $searches,
                'no_match_searches' => absint($row['no_match_searches'] ?? 0),
                'low_confidence_searches' => absint($row['low_confidence_searches'] ?? 0),
                'recency_days' => $age,
            ));
            $records[] = array(
                'query_hash' => sanitize_text_field($row['query_hash'] ?? ''),
                'query_text' => sanitize_text_field($row['query_text'] ?? ''),
                'searches' => $searches,
                'no_match_searches' => absint($row['no_match_searches'] ?? 0),
                'low_confidence_searches' => absint($row['low_confidence_searches'] ?? 0),
                'last_seen' => $last_seen,
                'priority' => $priority['priority'],
                'priority_score' => $priority['score'],
            );
        }
        usort($records, function ($a, $b) { return $b['priority_score'] <=> $a['priority_score']; });
        return array_slice($records, 0, max(1, min(100, absint($limit))));
    }

    public function product_record($product) {
        if (is_numeric($product)) {
            $product = get_term(absint($product), SCFS_Product_Integration::PRODUCT_TAXONOMY);
        }
        if (!$product || is_wp_error($product)) {
            return array();
        }
        $resolution = $this->resolution_metrics($product->slug);
        $documentation = $this->documentation_metrics($product->slug);
        $issues = $this->known_issue_metrics($product->slug);
        $releases = $this->release_metrics($product->slug);
        $content = $this->content_readiness_metrics($product);
        $repository = $this->repository_metrics($product->term_id);
        $governance = $this->governance_metrics();
        $gaps = $this->gap_metrics($product->slug);
        $cross_product = class_exists('SCFS_Cross_Product_Support_Orchestration') ? SCFS_Cross_Product_Support_Orchestration::instance()->overview_record($product->slug) : array();
        $score = $this->calculate_reliability_score(array(
            'resolution_success' => $resolution['resolution_success_percent'],
            'documentation_helpfulness' => $documentation['helpfulness_percent'],
            'known_issue_health' => $issues['health_percent'],
            'release_readiness' => $releases['average_readiness'],
            'content_readiness' => $content['score'],
            'repository_health' => $repository['health_percent'],
            'governance_health' => $governance['health_percent'],
            'unresolved_searches' => $resolution['unresolved_searches'],
            'critical_open_issues' => $issues['critical'],
            'high_priority_gaps' => $gaps['high_priority'],
            'overdue_reviews' => $governance['overdue_reviews'],
        ));
        return array(
            'schema' => 'scfs-product-reliability-record/' . self::SCHEMA_VERSION,
            'version' => self::VERSION,
            'generated_at' => gmdate('c'),
            'product' => array('id' => (int) $product->term_id, 'slug' => $product->slug, 'name' => $product->name),
            'score' => $score,
            'resolution' => $resolution,
            'documentation' => $documentation,
            'known_issues' => $issues,
            'releases' => $releases,
            'content_readiness' => $content,
            'repository' => $repository,
            'governance' => $governance,
            'documentation_gaps' => $gaps,
            'cross_product_support' => $cross_product,
            'unresolved_clusters' => $this->unresolved_clusters($product->slug, null, 10),
            'privacy' => array('private_case_content_exposed' => false, 'contact_details_exposed' => false),
            'human_review_required' => true,
        );
    }

    public function products() {
        $terms = get_terms(array('taxonomy' => SCFS_Product_Integration::PRODUCT_TAXONOMY, 'hide_empty' => false, 'orderby' => 'name', 'order' => 'ASC'));
        return is_wp_error($terms) ? array() : (array) $terms;
    }

    public function dashboard_record($selected_product = '') {
        $records = array();
        foreach ($this->products() as $product) {
            if ($selected_product && $product->slug !== sanitize_title($selected_product) && (string) $product->term_id !== (string) $selected_product) {
                continue;
            }
            $record = $this->product_record($product);
            if ($record) {
                $records[] = $record;
            }
        }
        usort($records, function ($a, $b) { return ($a['score']['score'] ?? 0) <=> ($b['score']['score'] ?? 0); });
        $summary = array('products' => count($records), 'healthy' => 0, 'watch' => 0, 'attention' => 0, 'critical' => 0, 'average_score' => 0, 'unresolved_searches' => 0, 'active_issues' => 0, 'priority_gaps' => 0);
        $total = 0;
        foreach ($records as $record) {
            $state = $record['score']['state'];
            if (isset($summary[$state])) {
                $summary[$state]++;
            }
            $total += (float) $record['score']['score'];
            $summary['unresolved_searches'] += absint($record['resolution']['unresolved_searches']);
            $summary['active_issues'] += absint($record['known_issues']['active']);
            $summary['priority_gaps'] += absint($record['documentation_gaps']['high_priority']);
        }
        $summary['average_score'] = $summary['products'] ? round($total / $summary['products'], 1) : 0;
        return array(
            'schema' => 'scfs-support-reliability-dashboard/' . self::SCHEMA_VERSION,
            'version' => self::VERSION,
            'generated_at' => gmdate('c'),
            'analysis_window_days' => absint($this->settings()['analysis_window_days']),
            'summary' => $summary,
            'products' => $records,
            'scheduled_task' => $this->cron_health(),
            'privacy' => $this->schema_record()['privacy'],
            'human_review_required' => true,
        );
    }

    private function snapshot_key($product_slug, $date) {
        return sanitize_title($product_slug) . ':' . sanitize_text_field($date);
    }

    public function store_snapshot($record, $date = '') {
        if (!is_array($record) || empty($record['product']['slug'])) {
            return false;
        }
        $date = $date ?: gmdate('Y-m-d');
        $snapshots = get_option(self::SNAPSHOT_OPTION, array());
        $snapshots = is_array($snapshots) ? $snapshots : array();
        $snapshot = array(
            'date' => $date,
            'product' => $record['product'],
            'score' => $record['score']['score'],
            'state' => $record['score']['state'],
            'resolution_success_percent' => $record['resolution']['resolution_success_percent'],
            'unresolved_searches' => $record['resolution']['unresolved_searches'],
            'documentation_helpfulness_percent' => $record['documentation']['helpfulness_percent'],
            'active_issues' => $record['known_issues']['active'],
            'critical_issues' => $record['known_issues']['critical'],
            'release_readiness_percent' => $record['releases']['average_readiness'],
            'content_readiness_percent' => $record['content_readiness']['score'],
            'repository_health_percent' => $record['repository']['health_percent'],
            'priority_gaps' => $record['documentation_gaps']['high_priority'],
            'generated_at' => gmdate('c'),
        );
        $snapshots[$this->snapshot_key($record['product']['slug'], $date)] = $snapshot;
        $retention = max(30, absint($this->settings()['snapshot_retention_days']));
        $cutoff = gmdate('Y-m-d', time() - $retention * DAY_IN_SECONDS);
        foreach ($snapshots as $key => $existing) {
            if (($existing['date'] ?? '') < $cutoff) {
                unset($snapshots[$key]);
            }
        }
        ksort($snapshots);
        update_option(self::SNAPSHOT_OPTION, $snapshots, false);
        return $snapshot;
    }

    public function trend_record($product_slug, $days = 30) {
        $snapshots = get_option(self::SNAPSHOT_OPTION, array());
        $snapshots = is_array($snapshots) ? $snapshots : array();
        $matching = array_values(array_filter($snapshots, function ($row) use ($product_slug) {
            return sanitize_title($row['product']['slug'] ?? '') === sanitize_title($product_slug);
        }));
        usort($matching, function ($a, $b) { return strcmp($a['date'], $b['date']); });
        $cutoff = gmdate('Y-m-d', time() - max(1, absint($days)) * DAY_IN_SECONDS);
        $matching = array_values(array_filter($matching, function ($row) use ($cutoff) { return ($row['date'] ?? '') >= $cutoff; }));
        $current = $matching ? end($matching) : array();
        $previous = count($matching) > 1 ? $matching[count($matching) - 2] : array();
        $delta = isset($current['score'], $previous['score']) ? round((float) $current['score'] - (float) $previous['score'], 1) : null;
        $direction = $delta === null ? 'insufficient_data' : ($delta >= 3 ? 'improving' : ($delta <= -3 ? 'declining' : 'stable'));
        return array('product' => sanitize_title($product_slug), 'direction' => $direction, 'score_delta' => $delta, 'current' => $current, 'previous' => $previous, 'series' => $matching);
    }

    public function run_daily_snapshot() {
        if (get_transient(self::CRON_LOCK)) {
            return array('status' => 'locked');
        }
        set_transient(self::CRON_LOCK, '1', 30 * MINUTE_IN_SECONDS);
        $result = array('status' => 'completed', 'products' => 0, 'snapshots' => 0, 'errors' => array(), 'completed_at' => gmdate('c'));
        try {
            foreach ($this->products() as $product) {
                $result['products']++;
                $record = $this->product_record($product);
                if ($record && $this->store_snapshot($record)) {
                    $result['snapshots']++;
                } else {
                    $result['errors'][] = sanitize_title($product->slug);
                }
            }
            update_option(self::LAST_RUN_OPTION, $result, false);
        } catch (Throwable $error) {
            $result['status'] = 'failed';
            $result['errors'][] = sanitize_text_field($error->getMessage());
            update_option(self::LAST_RUN_OPTION, $result, false);
        }
        delete_transient(self::CRON_LOCK);
        return $result;
    }

    public function cron_health() {
        $last = get_option(self::LAST_RUN_OPTION, array());
        $next = wp_next_scheduled(self::CRON_HOOK);
        return array(
            'hook' => self::CRON_HOOK,
            'scheduled' => (bool) $next,
            'next_run' => $next ? gmdate('c', $next) : '',
            'last_run' => is_array($last) ? $last : array(),
            'overdue' => $next ? $next < time() - HOUR_IN_SECONDS : true,
        );
    }

    public function report_payload($selected_product = '') {
        $dashboard = $this->dashboard_record($selected_product);
        $records = $dashboard['products'];
        usort($records, function ($a, $b) { return strcmp($a['product']['slug'], $b['product']['slug']); });
        $canonical = wp_json_encode($records, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return array(
            'schema' => 'scfs-support-reliability-report/' . self::SCHEMA_VERSION,
            'version' => self::VERSION,
            'generated_at' => gmdate('c'),
            'record_count' => count($records),
            'checksum_algorithm' => 'sha256',
            'records_checksum' => hash('sha256', (string) $canonical),
            'summary' => $dashboard['summary'],
            'records' => $records,
            'privacy' => $dashboard['privacy'],
            'human_review_required' => true,
        );
    }

    public function register_admin_page() {
        add_submenu_page(
            'edit.php?post_type=' . Sustainable_Catalyst_Feature_Suggestions::POST_TYPE,
            __('Support Analytics and Product Reliability Center', 'sustainable-catalyst-feature-suggestions'),
            __('Product Reliability', 'sustainable-catalyst-feature-suggestions'),
            $this->admin_capability(),
            'scfs-support-reliability',
            array($this, 'render_admin_page')
        );
    }

    private function asset_version($relative) {
        $path = plugin_dir_path(dirname(__FILE__)) . ltrim($relative, '/');
        return file_exists($path) ? (string) filemtime($path) : self::VERSION;
    }

    public function admin_assets($hook) {
        if (strpos((string) $hook, 'scfs-support-reliability') === false) {
            return;
        }
        wp_enqueue_style('scfs-support-reliability', plugins_url('../assets/support-reliability-center.css', __FILE__), array(), $this->asset_version('assets/support-reliability-center.css'));
        wp_enqueue_script('scfs-support-reliability', plugins_url('../assets/support-reliability-center.js', __FILE__), array(), $this->asset_version('assets/support-reliability-center.js'), true);
    }

    private function state_label($state) {
        $labels = array('healthy' => __('Healthy', 'sustainable-catalyst-feature-suggestions'), 'watch' => __('Watch', 'sustainable-catalyst-feature-suggestions'), 'attention' => __('Attention', 'sustainable-catalyst-feature-suggestions'), 'critical' => __('Critical', 'sustainable-catalyst-feature-suggestions'));
        return $labels[$state] ?? ucfirst((string) $state);
    }

    public function render_admin_page() {
        if (!$this->can_manage()) {
            wp_die(__('Permission denied.', 'sustainable-catalyst-feature-suggestions'));
        }
        $selected = isset($_GET['product']) ? sanitize_title(wp_unslash($_GET['product'])) : '';
        $dashboard = $this->dashboard_record($selected);
        $settings = $this->settings();
        $refresh_url = wp_nonce_url(admin_url('admin-post.php?action=scfs_refresh_support_reliability'), 'scfs_refresh_support_reliability');
        $csv_url = wp_nonce_url(admin_url('admin-post.php?action=scfs_export_support_reliability_csv&product=' . rawurlencode($selected)), 'scfs_export_support_reliability_csv');
        $json_url = wp_nonce_url(admin_url('admin-post.php?action=scfs_export_support_reliability_json&product=' . rawurlencode($selected)), 'scfs_export_support_reliability_json');
        echo '<div class="wrap scfs-reliability-admin"><h1>' . esc_html__('Support Analytics and Product Reliability Center', 'sustainable-catalyst-feature-suggestions') . '</h1>';
        echo '<p class="description">' . esc_html__('Review privacy-minimized support health across products. Scores combine resolution, documentation, known-issue, release, onboarding, repository, and editorial signals. All scores are advisory and require human interpretation.', 'sustainable-catalyst-feature-suggestions') . '</p>';
        if (!empty($_GET['refreshed'])) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Reliability snapshots refreshed.', 'sustainable-catalyst-feature-suggestions') . '</p></div>';
        }
        echo '<div class="scfs-reliability-toolbar"><form method="get"><input type="hidden" name="post_type" value="' . esc_attr(Sustainable_Catalyst_Feature_Suggestions::POST_TYPE) . '"><input type="hidden" name="page" value="scfs-support-reliability"><label for="scfs-reliability-product">' . esc_html__('Product', 'sustainable-catalyst-feature-suggestions') . '</label><select id="scfs-reliability-product" name="product"><option value="">' . esc_html__('All products', 'sustainable-catalyst-feature-suggestions') . '</option>';
        foreach ($this->products() as $product) {
            echo '<option value="' . esc_attr($product->slug) . '" ' . selected($selected, $product->slug, false) . '>' . esc_html($product->name) . '</option>';
        }
        echo '</select><button class="button">' . esc_html__('Apply', 'sustainable-catalyst-feature-suggestions') . '</button></form><div class="scfs-reliability-actions"><a class="button button-primary" href="' . esc_url($refresh_url) . '">' . esc_html__('Refresh snapshots', 'sustainable-catalyst-feature-suggestions') . '</a><a class="button" href="' . esc_url($csv_url) . '">' . esc_html__('Export CSV', 'sustainable-catalyst-feature-suggestions') . '</a><a class="button" href="' . esc_url($json_url) . '">' . esc_html__('Export JSON', 'sustainable-catalyst-feature-suggestions') . '</a></div></div>';
        echo '<div class="scfs-reliability-summary" aria-label="' . esc_attr__('Reliability summary', 'sustainable-catalyst-feature-suggestions') . '">';
        foreach (array('average_score' => __('Average score', 'sustainable-catalyst-feature-suggestions'), 'healthy' => __('Healthy products', 'sustainable-catalyst-feature-suggestions'), 'attention' => __('Need attention', 'sustainable-catalyst-feature-suggestions'), 'unresolved_searches' => __('Unresolved searches', 'sustainable-catalyst-feature-suggestions'), 'active_issues' => __('Active issues', 'sustainable-catalyst-feature-suggestions'), 'priority_gaps' => __('Priority gaps', 'sustainable-catalyst-feature-suggestions')) as $key => $label) {
            echo '<article><span>' . esc_html($label) . '</span><strong>' . esc_html((string) ($dashboard['summary'][$key] ?? 0)) . '</strong></article>';
        }
        echo '</div>';
        echo '<div class="scfs-reliability-products">';
        foreach ($dashboard['products'] as $record) {
            $score = $record['score'];
            $trend = $this->trend_record($record['product']['slug']);
            echo '<article class="scfs-reliability-product scfs-state-' . esc_attr($score['state']) . '"><header><div><p class="scfs-reliability-eyebrow">' . esc_html($record['product']['name']) . '</p><h2>' . esc_html(number_format_i18n($score['score'], 1)) . '<small>/100</small></h2></div><span class="scfs-reliability-state">' . esc_html($this->state_label($score['state'])) . '</span></header>';
            echo '<p class="scfs-reliability-trend">' . esc_html__('Trend:', 'sustainable-catalyst-feature-suggestions') . ' <strong>' . esc_html(str_replace('_', ' ', $trend['direction'])) . '</strong>' . ($trend['score_delta'] !== null ? ' (' . esc_html(($trend['score_delta'] > 0 ? '+' : '') . $trend['score_delta']) . ')' : '') . '</p>';
            echo '<div class="scfs-reliability-dimensions">';
            foreach ($score['dimensions'] as $dimension) {
                echo '<div><span>' . esc_html($dimension['label']) . '</span><meter min="0" max="100" value="' . esc_attr((string) $dimension['score']) . '">' . esc_html((string) $dimension['score']) . '</meter><strong>' . esc_html(number_format_i18n($dimension['score'], 1)) . '</strong></div>';
            }
            echo '</div>';
            echo '<div class="scfs-reliability-detail-grid"><section><h3>' . esc_html__('Operational signals', 'sustainable-catalyst-feature-suggestions') . '</h3><ul><li>' . sprintf(esc_html__('%1$d searches; %2$s%% resolution success', 'sustainable-catalyst-feature-suggestions'), absint($record['resolution']['searches']), esc_html(number_format_i18n($record['resolution']['resolution_success_percent'], 1))) . '</li><li>' . sprintf(esc_html__('%1$d documentation responses; %2$s%% helpful', 'sustainable-catalyst-feature-suggestions'), absint($record['documentation']['responses']), esc_html(number_format_i18n($record['documentation']['helpfulness_percent'], 1))) . '</li><li>' . sprintf(esc_html__('%1$d active issues; %2$d critical', 'sustainable-catalyst-feature-suggestions'), absint($record['known_issues']['active']), absint($record['known_issues']['critical'])) . '</li><li>' . sprintf(esc_html__('%1$d release records; %2$s%% average readiness', 'sustainable-catalyst-feature-suggestions'), absint($record['releases']['records']), esc_html(number_format_i18n($record['releases']['average_readiness'], 1))) . '</li></ul></section>';
            echo '<section><h3>' . esc_html__('Priority documentation gaps', 'sustainable-catalyst-feature-suggestions') . '</h3>';
            if (!$record['documentation_gaps']['top']) {
                echo '<p>' . esc_html__('No open documentation gaps match this product.', 'sustainable-catalyst-feature-suggestions') . '</p>';
            } else {
                echo '<ol>';
                foreach (array_slice($record['documentation_gaps']['top'], 0, 5) as $gap) {
                    echo '<li><a href="' . esc_url($gap['edit_url']) . '">' . esc_html($gap['title']) . '</a> <strong>' . esc_html(number_format_i18n($gap['score'], 1)) . '</strong></li>';
                }
                echo '</ol>';
            }
            echo '</section><section><h3>' . esc_html__('Unresolved query clusters', 'sustainable-catalyst-feature-suggestions') . '</h3>';
            if (!$record['unresolved_clusters']) {
                echo '<p>' . esc_html__('No repeated unresolved clusters meet the current threshold.', 'sustainable-catalyst-feature-suggestions') . '</p>';
            } else {
                echo '<ol>';
                foreach (array_slice($record['unresolved_clusters'], 0, 5) as $cluster) {
                    $label = $cluster['query_text'] ?: sprintf(__('Hashed query %s', 'sustainable-catalyst-feature-suggestions'), substr($cluster['query_hash'], 0, 10));
                    echo '<li><span>' . esc_html($label) . '</span> <strong>' . esc_html(ucfirst($cluster['priority'])) . ' · ' . esc_html(number_format_i18n($cluster['priority_score'], 1)) . '</strong></li>';
                }
                echo '</ol>';
            }
            echo '</section></div>';
            if ($score['blockers']) {
                echo '<div class="notice notice-warning inline"><p><strong>' . esc_html__('Review blockers:', 'sustainable-catalyst-feature-suggestions') . '</strong> ' . esc_html(implode(', ', array_map(function ($item) { return str_replace('_', ' ', $item); }, $score['blockers']))) . '</p></div>';
            }
            echo '</article>';
        }
        if (!$dashboard['products']) {
            echo '<div class="notice notice-info inline"><p>' . esc_html__('No product taxonomy records are available yet. Complete Product Onboarding first.', 'sustainable-catalyst-feature-suggestions') . '</p></div>';
        }
        echo '</div>';
        echo '<section class="scfs-reliability-settings"><h2>' . esc_html__('Reliability settings', 'sustainable-catalyst-feature-suggestions') . '</h2><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="scfs_save_support_reliability_settings">';
        wp_nonce_field('scfs_save_support_reliability_settings');
        echo '<div class="scfs-reliability-settings-grid">';
        $fields = array('analysis_window_days' => __('Analysis window (days)', 'sustainable-catalyst-feature-suggestions'), 'snapshot_retention_days' => __('Snapshot retention (days)', 'sustainable-catalyst-feature-suggestions'), 'healthy_threshold' => __('Healthy score threshold', 'sustainable-catalyst-feature-suggestions'), 'watch_threshold' => __('Watch score threshold', 'sustainable-catalyst-feature-suggestions'), 'attention_threshold' => __('Critical blocker threshold', 'sustainable-catalyst-feature-suggestions'), 'high_priority_gap_threshold' => __('Priority gap threshold', 'sustainable-catalyst-feature-suggestions'), 'cluster_minimum_searches' => __('Minimum searches per cluster', 'sustainable-catalyst-feature-suggestions'));
        foreach ($fields as $key => $label) {
            echo '<label><span>' . esc_html($label) . '</span><input type="number" min="1" max="1000" name="' . esc_attr($key) . '" value="' . esc_attr($settings[$key]) . '"></label>';
        }
        echo '</div><p><button class="button button-primary">' . esc_html__('Save reliability settings', 'sustainable-catalyst-feature-suggestions') . '</button></p></form><p class="description">' . esc_html__('Scores summarize evidence. They do not automatically change product roadmaps, publish content, close issues, or declare incidents.', 'sustainable-catalyst-feature-suggestions') . '</p></section></div>';
    }

    public function refresh_action() {
        if (!$this->can_manage()) {
            wp_die(__('Permission denied.', 'sustainable-catalyst-feature-suggestions'));
        }
        check_admin_referer('scfs_refresh_support_reliability');
        $this->run_daily_snapshot();
        wp_safe_redirect(admin_url('edit.php?post_type=' . Sustainable_Catalyst_Feature_Suggestions::POST_TYPE . '&page=scfs-support-reliability&refreshed=1'));
        exit;
    }

    public function save_settings_action() {
        if (!$this->can_manage()) {
            wp_die(__('Permission denied.', 'sustainable-catalyst-feature-suggestions'));
        }
        check_admin_referer('scfs_save_support_reliability_settings');
        $defaults = $this->default_settings();
        $settings = array();
        foreach ($defaults as $key => $default) {
            if ($key === 'show_products_without_activity') {
                $settings[$key] = !empty($_POST[$key]) ? '1' : '0';
            } else {
                $settings[$key] = (string) max(1, min(1000, absint($_POST[$key] ?? $default)));
            }
        }
        update_option(self::OPTION_KEY, $settings, false);
        wp_safe_redirect(admin_url('edit.php?post_type=' . Sustainable_Catalyst_Feature_Suggestions::POST_TYPE . '&page=scfs-support-reliability&settings-updated=1'));
        exit;
    }

    private function selected_product_request() {
        return isset($_GET['product']) ? sanitize_title(wp_unslash($_GET['product'])) : '';
    }

    public function export_csv_action() {
        if (!$this->can_manage()) {
            wp_die(__('Permission denied.', 'sustainable-catalyst-feature-suggestions'));
        }
        check_admin_referer('scfs_export_support_reliability_csv');
        $payload = $this->report_payload($this->selected_product_request());
        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="scfs-product-reliability-' . gmdate('Ymd-His') . '.csv"');
        header('X-SCFS-Records-Checksum: ' . $payload['records_checksum']);
        $out = fopen('php://output', 'w');
        fputcsv($out, array('product','score','state','resolution_success_percent','unresolved_searches','documentation_helpfulness_percent','active_issues','critical_issues','release_readiness_percent','content_readiness_percent','repository_health_percent','priority_gaps','generated_at'));
        foreach ($payload['records'] as $record) {
            fputcsv($out, array($record['product']['slug'], $record['score']['score'], $record['score']['state'], $record['resolution']['resolution_success_percent'], $record['resolution']['unresolved_searches'], $record['documentation']['helpfulness_percent'], $record['known_issues']['active'], $record['known_issues']['critical'], $record['releases']['average_readiness'], $record['content_readiness']['score'], $record['repository']['health_percent'], $record['documentation_gaps']['high_priority'], $record['generated_at']));
        }
        fclose($out);
        exit;
    }

    public function export_json_action() {
        if (!$this->can_manage()) {
            wp_die(__('Permission denied.', 'sustainable-catalyst-feature-suggestions'));
        }
        check_admin_referer('scfs_export_support_reliability_json');
        $payload = $this->report_payload($this->selected_product_request());
        nocache_headers();
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="scfs-product-reliability-' . gmdate('Ymd-His') . '.json"');
        header('X-SCFS-Records-Checksum: ' . $payload['records_checksum']);
        echo wp_json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function register_rest_routes() {
        $namespace = Sustainable_Catalyst_Feature_Suggestions::REST_NAMESPACE;
        register_rest_route($namespace, '/support-reliability/schema', array('methods' => 'GET', 'callback' => array($this, 'rest_schema'), 'permission_callback' => array($this, 'rest_permission')));
        register_rest_route($namespace, '/support-reliability/dashboard', array('methods' => 'GET', 'callback' => array($this, 'rest_dashboard'), 'permission_callback' => array($this, 'rest_permission')));
        register_rest_route($namespace, '/support-reliability/products/(?P<product>[a-z0-9-]+)', array('methods' => 'GET', 'callback' => array($this, 'rest_product'), 'permission_callback' => array($this, 'rest_permission')));
        register_rest_route($namespace, '/support-reliability/trends/(?P<product>[a-z0-9-]+)', array('methods' => 'GET', 'callback' => array($this, 'rest_trend'), 'permission_callback' => array($this, 'rest_permission')));
        register_rest_route($namespace, '/support-reliability/refresh', array('methods' => 'POST', 'callback' => array($this, 'rest_refresh'), 'permission_callback' => array($this, 'rest_permission')));
        register_rest_route($namespace, '/support-reliability/report', array('methods' => 'GET', 'callback' => array($this, 'rest_report'), 'permission_callback' => array($this, 'rest_permission')));
    }

    public function rest_permission() {
        return $this->can_manage();
    }

    public function rest_schema() {
        return rest_ensure_response($this->schema_record());
    }

    public function rest_dashboard($request) {
        return rest_ensure_response($this->dashboard_record(sanitize_title($request->get_param('product') ?: '')));
    }

    public function rest_product($request) {
        $term = get_term_by('slug', sanitize_title($request['product']), SCFS_Product_Integration::PRODUCT_TAXONOMY);
        if (!$term) {
            return new WP_Error('scfs_product_not_found', __('Product not found.', 'sustainable-catalyst-feature-suggestions'), array('status' => 404));
        }
        return rest_ensure_response($this->product_record($term));
    }

    public function rest_trend($request) {
        return rest_ensure_response($this->trend_record(sanitize_title($request['product']), absint($request->get_param('days') ?: 30)));
    }

    public function rest_refresh() {
        return rest_ensure_response($this->run_daily_snapshot());
    }

    public function rest_report($request) {
        return rest_ensure_response($this->report_payload(sanitize_title($request->get_param('product') ?: '')));
    }
}
