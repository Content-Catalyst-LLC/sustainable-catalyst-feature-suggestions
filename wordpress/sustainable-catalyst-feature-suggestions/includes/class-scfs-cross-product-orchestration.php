<?php
/**
 * Cross-product support orchestration.
 *
 * Coordinates public incidents, product dependencies, shared components,
 * multi-product support records, release dependencies, related-product routes,
 * and cross-product resolution journeys without importing private case data.
 *
 * @package Sustainable_Catalyst_Feature_Suggestions
 */

if (!defined('ABSPATH')) {
    exit;
}

final class SCFS_Cross_Product_Support_Orchestration {
    const VERSION = '4.5.0';
    const SCHEMA_VERSION = '1.0';
    const INCIDENT_POST_TYPE = 'sc_platform_incident';
    const SHORTCODE = 'scfs_cross_product_support';
    const OPTION_KEY = 'scfs_cross_product_orchestration';
    const NONCE_ACTION = 'scfs_cross_product_orchestration';
    const NONCE_NAME = 'scfs_cross_product_nonce';

    private static $instance = null;

    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('init', array($this, 'register_incident_post_type'), 7);
        add_action('init', array($this, 'register_meta'), 10);
        add_action('init', array($this, 'register_shortcode'), 32);
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post_' . self::INCIDENT_POST_TYPE, array($this, 'save_incident'), 20, 2);
        add_action('save_post_sc_known_issue', array($this, 'save_relationships'), 35, 2);
        add_action('save_post_sc_support_article', array($this, 'save_relationships'), 35, 2);
        add_action('save_post_sc_release_record', array($this, 'save_relationships'), 35, 2);
        add_action('admin_menu', array($this, 'register_admin_page'), 36);
        add_action('admin_enqueue_scripts', array($this, 'admin_assets'));
        add_action('wp_enqueue_scripts', array($this, 'register_assets'));
        add_action('admin_post_scfs_save_cross_product_settings', array($this, 'save_settings_action'));
        add_action('rest_api_init', array($this, 'register_rest_routes'));
        add_filter('the_content', array($this, 'decorate_incident_content'), 24);
        add_filter('manage_' . self::INCIDENT_POST_TYPE . '_posts_columns', array($this, 'incident_columns'));
        add_action('manage_' . self::INCIDENT_POST_TYPE . '_posts_custom_column', array($this, 'incident_column_content'), 10, 2);
        add_filter('scfs_support_navigation_items', array($this, 'support_navigation_item'), 20, 4);
    }

    public static function activate() {
        $instance = self::instance();
        $instance->register_incident_post_type();
        $instance->register_meta();
        if (!get_option(self::OPTION_KEY)) {
            add_option(self::OPTION_KEY, $instance->default_settings(), '', false);
        }
    }

    public static function deactivate() {
        // No destructive cleanup. Incident and dependency records remain available.
    }

    public function default_settings() {
        return array(
            'show_platform_status_view' => '1',
            'show_related_product_recommendations' => '1',
            'show_dependency_notices' => '1',
            'show_resolved_incidents_days' => '30',
            'dependency_lines' => '',
        );
    }

    public function settings() {
        return wp_parse_args((array) get_option(self::OPTION_KEY, array()), $this->default_settings());
    }

    public function admin_capability() {
        return apply_filters('scfs_cross_product_admin_capability', 'manage_options');
    }

    public function can_manage() {
        return current_user_can($this->admin_capability());
    }

    public function incident_statuses() {
        return array(
            'investigating' => __('Investigating', 'sustainable-catalyst-feature-suggestions'),
            'identified' => __('Identified', 'sustainable-catalyst-feature-suggestions'),
            'monitoring' => __('Monitoring', 'sustainable-catalyst-feature-suggestions'),
            'resolved' => __('Resolved', 'sustainable-catalyst-feature-suggestions'),
        );
    }

    public function incident_severities() {
        return array(
            'low' => __('Low', 'sustainable-catalyst-feature-suggestions'),
            'moderate' => __('Moderate', 'sustainable-catalyst-feature-suggestions'),
            'high' => __('High', 'sustainable-catalyst-feature-suggestions'),
            'critical' => __('Critical', 'sustainable-catalyst-feature-suggestions'),
        );
    }

    public function relationship_types() {
        return array(
            'depends_on' => __('Depends on', 'sustainable-catalyst-feature-suggestions'),
            'integrates_with' => __('Integrates with', 'sustainable-catalyst-feature-suggestions'),
            'shares_component' => __('Shares component with', 'sustainable-catalyst-feature-suggestions'),
            'routes_to' => __('Routes support to', 'sustainable-catalyst-feature-suggestions'),
            'provides_data_to' => __('Provides data to', 'sustainable-catalyst-feature-suggestions'),
        );
    }

    public function schema_record() {
        return array(
            'schema' => 'scfs-cross-product-support-orchestration/' . self::SCHEMA_VERSION,
            'version' => self::VERSION,
            'capabilities' => array(
                'cross_product_incidents',
                'product_dependency_graph',
                'shared_component_relationships',
                'multi_product_support_articles',
                'dependency_aware_known_issues',
                'platform_wide_release_dependencies',
                'product_handoff_pathways',
                'related_product_recommendations',
                'cross_product_resolution_journeys',
                'public_platform_incident_summaries',
            ),
            'public_records' => array(self::INCIDENT_POST_TYPE, 'sc_support_article', 'sc_known_issue', 'sc_release_record'),
            'shared_context' => array('product', 'product_version', 'component', 'issue_type', 'release'),
            'shortcode' => self::SHORTCODE,
            'rest_routes' => array(
                '/cross-product/schema',
                '/cross-product/overview',
                '/cross-product/incidents',
                '/cross-product/routes',
                '/cross-product/journey',
                '/cross-product/snapshot',
            ),
            'privacy' => array(
                'private_case_content_stored' => false,
                'contact_identity_stored' => false,
                'private_documents_stored' => false,
                'opaque_case_relationships_only' => true,
            ),
            'governance' => array(
                'human_review_required' => true,
                'automatic_incident_declaration' => false,
                'automatic_release_blocking' => false,
                'automatic_roadmap_change' => false,
                'automatic_private_case_creation' => false,
            ),
        );
    }

    public function register_incident_post_type() {
        register_post_type(self::INCIDENT_POST_TYPE, array(
            'labels' => array(
                'name' => __('Platform Incidents', 'sustainable-catalyst-feature-suggestions'),
                'singular_name' => __('Platform Incident', 'sustainable-catalyst-feature-suggestions'),
                'menu_name' => __('Platform Incidents', 'sustainable-catalyst-feature-suggestions'),
                'add_new_item' => __('Add Platform Incident', 'sustainable-catalyst-feature-suggestions'),
                'edit_item' => __('Edit Platform Incident', 'sustainable-catalyst-feature-suggestions'),
                'view_item' => __('View Platform Incident', 'sustainable-catalyst-feature-suggestions'),
                'search_items' => __('Search Platform Incidents', 'sustainable-catalyst-feature-suggestions'),
                'not_found' => __('No platform incidents found.', 'sustainable-catalyst-feature-suggestions'),
            ),
            'public' => true,
            'show_ui' => true,
            'show_in_menu' => 'edit.php?post_type=' . Sustainable_Catalyst_Feature_Suggestions::POST_TYPE,
            'show_in_rest' => false,
            'supports' => array('title', 'editor', 'excerpt', 'revisions'),
            'has_archive' => 'support-platform-incidents',
            'rewrite' => array('slug' => 'support/platform-incidents'),
            'menu_icon' => 'dashicons-warning',
            'map_meta_cap' => true,
            'taxonomies' => array(
                SCFS_Product_Integration::PRODUCT_TAXONOMY,
                SCFS_Product_Integration::VERSION_TAXONOMY,
                SCFS_Product_Integration::COMPONENT_TAXONOMY,
                SCFS_Product_Integration::ISSUE_TAXONOMY,
                SCFS_Product_Integration::RELEASE_TAXONOMY,
            ),
        ));
    }

    public function register_meta() {
        $incident_meta = array(
            '_scfs_incident_status' => 'sanitize_key',
            '_scfs_incident_severity' => 'sanitize_key',
            '_scfs_incident_summary' => 'sanitize_textarea_field',
            '_scfs_incident_workaround' => 'sanitize_textarea_field',
            '_scfs_incident_started_at' => 'sanitize_text_field',
            '_scfs_incident_resolved_at' => 'sanitize_text_field',
            '_scfs_incident_primary_product' => 'sanitize_title',
        );
        foreach ($incident_meta as $key => $sanitize_callback) {
            register_post_meta(self::INCIDENT_POST_TYPE, $key, array(
                'type' => 'string',
                'single' => true,
                'show_in_rest' => false,
                'sanitize_callback' => $sanitize_callback,
                'auth_callback' => function () { return current_user_can('edit_posts'); },
            ));
        }
        $relationship_types = array('sc_support_article', 'sc_known_issue', 'sc_release_record');
        foreach ($relationship_types as $post_type) {
            register_post_meta($post_type, '_scfs_orchestration_relationships', array(
                'type' => 'object',
                'single' => true,
                'show_in_rest' => false,
                'sanitize_callback' => array($this, 'sanitize_relationship_meta'),
                'auth_callback' => function () { return current_user_can('edit_posts'); },
            ));
        }
    }

    public function sanitize_relationship_meta($value) {
        $value = is_array($value) ? $value : array();
        return array(
            'related_products' => array_values(array_unique(array_filter(array_map('sanitize_title', (array) ($value['related_products'] ?? array()))))),
            'related_incidents' => array_values(array_unique(array_filter(array_map('absint', (array) ($value['related_incidents'] ?? array()))))),
            'dependent_releases' => array_values(array_unique(array_filter(array_map('absint', (array) ($value['dependent_releases'] ?? array()))))),
            'journey_stage' => in_array(($value['journey_stage'] ?? ''), array('diagnose', 'configure', 'integrate', 'recover', 'verify'), true) ? $value['journey_stage'] : '',
        );
    }

    public function register_shortcode() {
        add_shortcode(self::SHORTCODE, array($this, 'render_shortcode'));
    }

    public function register_assets() {
        wp_register_style(
            'scfs-cross-product-orchestration',
            plugins_url('assets/cross-product-orchestration.css', dirname(__DIR__) . '/sustainable-catalyst-feature-suggestions.php'),
            array('scfs-product-support-platform'),
            self::VERSION
        );
    }

    public function admin_assets($hook) {
        if (strpos((string) $hook, 'scfs-cross-product') === false && strpos((string) $hook, self::INCIDENT_POST_TYPE) === false) {
            return;
        }
        wp_enqueue_style(
            'scfs-cross-product-orchestration-admin',
            plugins_url('assets/cross-product-orchestration.css', dirname(__DIR__) . '/sustainable-catalyst-feature-suggestions.php'),
            array(),
            self::VERSION
        );
    }

    public function support_navigation_item($items, $settings, $product, $respect_content) {
        $orchestration = $this->settings();
        if (empty($orchestration['show_platform_status_view'])) {
            return $items;
        }
        $new = array();
        foreach ($items as $key => $item) {
            $new[$key] = $item;
            if ($key === 'issues') {
                $new['platform'] = array(
                    'label' => __('Platform status', 'sustainable-catalyst-feature-suggestions'),
                    'description' => __('Cross-product incidents and dependencies', 'sustainable-catalyst-feature-suggestions'),
                );
            }
        }
        if (!isset($new['platform'])) {
            $new['platform'] = array(
                'label' => __('Platform status', 'sustainable-catalyst-feature-suggestions'),
                'description' => __('Cross-product incidents and dependencies', 'sustainable-catalyst-feature-suggestions'),
            );
        }
        return $new;
    }

    public function normalize_dependency($record) {
        $record = is_array($record) ? $record : array();
        $types = array_keys($this->relationship_types());
        $criticalities = array('low', 'moderate', 'high', 'critical');
        $source = sanitize_title($record['source'] ?? '');
        $target = sanitize_title($record['target'] ?? '');
        $relationship = sanitize_key($record['relationship'] ?? 'depends_on');
        $criticality = sanitize_key($record['criticality'] ?? 'moderate');
        if (!in_array($relationship, $types, true)) {
            $relationship = 'depends_on';
        }
        if (!in_array($criticality, $criticalities, true)) {
            $criticality = 'moderate';
        }
        return array(
            'source' => $source,
            'target' => $target,
            'relationship' => $relationship,
            'component' => sanitize_text_field($record['component'] ?? ''),
            'criticality' => $criticality,
            'active' => !isset($record['active']) || !empty($record['active']),
        );
    }

    public function dependencies_from_text($text) {
        $records = array();
        foreach (preg_split('/\r\n|\r|\n/', (string) $text) as $line) {
            $line = trim($line);
            if ($line === '' || strpos($line, '#') === 0) {
                continue;
            }
            $parts = array_map('trim', explode('|', $line));
            $record = $this->normalize_dependency(array(
                'source' => $parts[0] ?? '',
                'relationship' => $parts[1] ?? 'depends_on',
                'target' => $parts[2] ?? '',
                'component' => $parts[3] ?? '',
                'criticality' => $parts[4] ?? 'moderate',
                'active' => !isset($parts[5]) || !in_array(strtolower($parts[5]), array('0', 'false', 'inactive'), true),
            ));
            if ($record['source'] !== '' && $record['target'] !== '' && $record['source'] !== $record['target']) {
                $key = implode('|', array($record['source'], $record['relationship'], $record['target'], strtolower($record['component'])));
                $records[$key] = $record;
            }
        }
        return array_values($records);
    }

    public function dependency_records() {
        $settings = $this->settings();
        return $this->dependencies_from_text($settings['dependency_lines'] ?? '');
    }

    public function dependency_lines($records) {
        $lines = array();
        foreach ((array) $records as $record) {
            $record = $this->normalize_dependency($record);
            if ($record['source'] === '' || $record['target'] === '') {
                continue;
            }
            $lines[] = implode('|', array(
                $record['source'],
                $record['relationship'],
                $record['target'],
                $record['component'],
                $record['criticality'],
                $record['active'] ? '1' : '0',
            ));
        }
        return implode("\n", $lines);
    }

    public function calculate_incident_impact($evidence) {
        $evidence = wp_parse_args((array) $evidence, array(
            'affected_products' => 0,
            'dependent_products' => 0,
            'criticality' => 'moderate',
            'active_known_issues' => 0,
            'blocked_releases' => 0,
            'support_handoffs' => 0,
            'shared_components' => 0,
        ));
        $criticality_weights = array('low' => 8, 'moderate' => 18, 'high' => 32, 'critical' => 48);
        $criticality = sanitize_key($evidence['criticality']);
        if (!isset($criticality_weights[$criticality])) {
            $criticality = 'moderate';
        }
        $score = $criticality_weights[$criticality];
        $score += min(24, absint($evidence['affected_products']) * 6);
        $score += min(12, absint($evidence['dependent_products']) * 3);
        $score += min(8, absint($evidence['active_known_issues']) * 2);
        $score += min(10, absint($evidence['blocked_releases']) * 5);
        $score += min(8, absint($evidence['support_handoffs']) * 2);
        $score += min(6, absint($evidence['shared_components']) * 2);
        $score = max(0, min(100, $score));
        if ($score >= 80) {
            $state = 'critical';
        } elseif ($score >= 60) {
            $state = 'high';
        } elseif ($score >= 35) {
            $state = 'moderate';
        } else {
            $state = 'low';
        }
        $signals = array();
        if (absint($evidence['affected_products']) > 1) {
            $signals[] = 'The incident affects multiple products.';
        }
        if (absint($evidence['dependent_products']) > 0) {
            $signals[] = 'Downstream product dependencies may propagate the incident.';
        }
        if (absint($evidence['blocked_releases']) > 0) {
            $signals[] = 'One or more release records require human review.';
        }
        return array(
            'schema' => 'scfs-cross-product-incident-impact/1.0',
            'version' => self::VERSION,
            'score' => $score,
            'state' => $state,
            'signals' => $signals,
            'human_review_required' => true,
            'automatic_incident_declaration' => false,
            'automatic_release_blocking' => false,
        );
    }

    public function recommend_routes($product, $graph = null, $context = array()) {
        $product = sanitize_title($product);
        $graph = $graph === null ? $this->dependency_records() : (array) $graph;
        $component = strtolower(sanitize_text_field($context['component'] ?? ''));
        $recommendations = array();
        foreach ($graph as $edge) {
            $edge = $this->normalize_dependency($edge);
            if (!$edge['active']) {
                continue;
            }
            $direction = '';
            $candidate = '';
            if ($edge['source'] === $product) {
                $direction = 'outbound';
                $candidate = $edge['target'];
            } elseif ($edge['target'] === $product) {
                $direction = 'inbound';
                $candidate = $edge['source'];
            }
            if ($candidate === '') {
                continue;
            }
            $score = 40;
            if ($edge['relationship'] === 'routes_to') {
                $score += 25;
            } elseif ($edge['relationship'] === 'depends_on') {
                $score += 18;
            } elseif ($edge['relationship'] === 'shares_component') {
                $score += 15;
            }
            $criticality_bonus = array('low' => 0, 'moderate' => 5, 'high' => 10, 'critical' => 15);
            $score += $criticality_bonus[$edge['criticality']] ?? 5;
            if ($component !== '' && $edge['component'] !== '' && strpos(strtolower($edge['component']), $component) !== false) {
                $score += 15;
            }
            if (!isset($recommendations[$candidate]) || $recommendations[$candidate]['score'] < $score) {
                $recommendations[$candidate] = array(
                    'product' => $candidate,
                    'relationship' => $edge['relationship'],
                    'component' => $edge['component'],
                    'criticality' => $edge['criticality'],
                    'direction' => $direction,
                    'score' => min(100, $score),
                    'reason' => $this->relationship_types()[$edge['relationship']] ?? $edge['relationship'],
                );
            }
        }
        $recommendations = array_values($recommendations);
        usort($recommendations, function ($a, $b) {
            if ($a['score'] === $b['score']) {
                return strcmp($a['product'], $b['product']);
            }
            return $b['score'] <=> $a['score'];
        });
        return $recommendations;
    }

    public function journey_record($product = '', $context = array()) {
        $product = sanitize_title($product);
        $routes = $this->recommend_routes($product, null, $context);
        $steps = array(
            array('key' => 'diagnose', 'label' => __('Diagnose the visible symptom', 'sustainable-catalyst-feature-suggestions'), 'view' => 'resolve', 'product' => $product),
            array('key' => 'status', 'label' => __('Check product and platform incidents', 'sustainable-catalyst-feature-suggestions'), 'view' => 'platform', 'product' => $product),
            array('key' => 'learn', 'label' => __('Review product-aware documentation', 'sustainable-catalyst-feature-suggestions'), 'view' => 'documentation', 'product' => $product),
        );
        foreach (array_slice($routes, 0, 3) as $route) {
            $steps[] = array(
                'key' => 'related-product',
                'label' => sprintf(__('Review related product: %s', 'sustainable-catalyst-feature-suggestions'), $route['product']),
                'view' => 'resolve',
                'product' => $route['product'],
                'relationship' => $route['relationship'],
                'component' => $route['component'],
            );
        }
        $steps[] = array('key' => 'private-support', 'label' => __('Continue to private support when public guidance is insufficient', 'sustainable-catalyst-feature-suggestions'), 'view' => 'private-support', 'product' => $product);
        return array(
            'schema' => 'scfs-cross-product-resolution-journey/' . self::SCHEMA_VERSION,
            'version' => self::VERSION,
            'starting_product' => $product,
            'context' => array(
                'component' => sanitize_text_field($context['component'] ?? ''),
                'issue_type' => sanitize_key($context['issue_type'] ?? ''),
                'symptom_hash_only' => !empty($context['symptom']),
            ),
            'steps' => $steps,
            'related_products' => $routes,
            'private_case_content_stored' => false,
            'human_review_required' => true,
        );
    }

    private function post_terms($post_id, $taxonomy) {
        $terms = get_the_terms($post_id, $taxonomy);
        if (!$terms || is_wp_error($terms)) {
            return array();
        }
        return array_values(array_map(function ($term) {
            return array('id' => (int) $term->term_id, 'slug' => $term->slug, 'name' => $term->name);
        }, $terms));
    }

    public function incident_record($post, $include_internal = false) {
        $post = is_numeric($post) ? get_post(absint($post)) : $post;
        if (!$post || $post->post_type !== self::INCIDENT_POST_TYPE) {
            return array();
        }
        $products = $this->post_terms($post->ID, SCFS_Product_Integration::PRODUCT_TAXONOMY);
        $components = $this->post_terms($post->ID, SCFS_Product_Integration::COMPONENT_TAXONOMY);
        $severity = get_post_meta($post->ID, '_scfs_incident_severity', true) ?: 'moderate';
        $routes = array();
        foreach ($products as $product) {
            $routes = array_merge($routes, $this->recommend_routes($product['slug'], null, array('component' => implode(' ', wp_list_pluck($components, 'name')))));
        }
        $route_map = array();
        foreach ($routes as $route) {
            if (!isset($route_map[$route['product']]) || $route_map[$route['product']]['score'] < $route['score']) {
                $route_map[$route['product']] = $route;
            }
        }
        $impact = $this->calculate_incident_impact(array(
            'affected_products' => count($products),
            'dependent_products' => count($route_map),
            'criticality' => $severity,
            'shared_components' => count($components),
        ));
        $record = array(
            'schema' => 'scfs-platform-incident/1.0',
            'version' => self::VERSION,
            'id' => (int) $post->ID,
            'title' => get_the_title($post),
            'url' => get_permalink($post),
            'status' => get_post_meta($post->ID, '_scfs_incident_status', true) ?: 'investigating',
            'severity' => $severity,
            'summary' => get_post_meta($post->ID, '_scfs_incident_summary', true) ?: wp_trim_words(wp_strip_all_tags($post->post_excerpt ?: $post->post_content), 45),
            'workaround' => get_post_meta($post->ID, '_scfs_incident_workaround', true),
            'started_at' => get_post_meta($post->ID, '_scfs_incident_started_at', true),
            'resolved_at' => get_post_meta($post->ID, '_scfs_incident_resolved_at', true),
            'products' => $products,
            'components' => $components,
            'versions' => $this->post_terms($post->ID, SCFS_Product_Integration::VERSION_TAXONOMY),
            'issue_types' => $this->post_terms($post->ID, SCFS_Product_Integration::ISSUE_TAXONOMY),
            'releases' => $this->post_terms($post->ID, SCFS_Product_Integration::RELEASE_TAXONOMY),
            'related_product_routes' => array_values($route_map),
            'impact' => $impact,
            'updated_at' => get_post_modified_time('c', true, $post),
            'private_case_content_exposed' => false,
        );
        if ($include_internal) {
            $record['post_status'] = $post->post_status;
            $record['primary_product'] = get_post_meta($post->ID, '_scfs_incident_primary_product', true);
        }
        return $record;
    }

    public function incident_query($product = '', $include_resolved = true, $limit = 20) {
        $meta_query = array();
        if (!$include_resolved) {
            $meta_query[] = array('key' => '_scfs_incident_status', 'value' => 'resolved', 'compare' => '!=');
        }
        $args = array(
            'post_type' => self::INCIDENT_POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => max(1, min(100, absint($limit))),
            'orderby' => 'modified',
            'order' => 'DESC',
            'meta_query' => $meta_query,
        );
        if ($product !== '') {
            $args['tax_query'] = array(array(
                'taxonomy' => SCFS_Product_Integration::PRODUCT_TAXONOMY,
                'field' => 'slug',
                'terms' => sanitize_title($product),
            ));
        }
        return new WP_Query($args);
    }

    public function overview_record($product = '') {
        $query = $this->incident_query($product, true, 50);
        $active = 0;
        $critical = 0;
        $records = array();
        foreach ($query->posts as $post) {
            $record = $this->incident_record($post, false);
            if (($record['status'] ?? '') !== 'resolved') {
                $active++;
            }
            if (($record['severity'] ?? '') === 'critical' && ($record['status'] ?? '') !== 'resolved') {
                $critical++;
            }
            $records[] = $record;
        }
        $routes = $product !== '' ? $this->recommend_routes($product) : array();
        return array(
            'schema' => 'scfs-cross-product-support-overview/' . self::SCHEMA_VERSION,
            'version' => self::VERSION,
            'product' => sanitize_title($product),
            'active_incidents' => $active,
            'critical_incidents' => $critical,
            'published_incidents' => count($records),
            'dependency_count' => count($this->dependency_records()),
            'related_products' => $routes,
            'incidents' => $records,
            'private_case_content_stored' => false,
            'human_review_required' => true,
        );
    }

    public function render_shortcode($atts = array()) {
        $atts = shortcode_atts(array('product' => '', 'show_resolved' => '1', 'limit' => '12', 'title' => __('Platform status and related products', 'sustainable-catalyst-feature-suggestions')), $atts, self::SHORTCODE);
        wp_enqueue_style('scfs-cross-product-orchestration');
        ob_start();
        echo '<section class="scfs-cross-product-support">';
        if ($atts['title'] !== '') {
            echo '<header><p class="scfs-support-platform__kicker">' . esc_html__('Cross-product support orchestration', 'sustainable-catalyst-feature-suggestions') . '</p><h3>' . esc_html($atts['title']) . '</h3><p>' . esc_html__('Review incidents that affect more than one product, understand product dependencies, and follow related-product resolution paths.', 'sustainable-catalyst-feature-suggestions') . '</p></header>';
        }
        $this->render_public_panel(sanitize_title($atts['product']), in_array((string) $atts['show_resolved'], array('1', 'true', 'yes'), true), absint($atts['limit']));
        echo '</section>';
        return ob_get_clean();
    }

    public function render_public_panel($product = '', $show_resolved = true, $limit = 12) {
        wp_enqueue_style('scfs-cross-product-orchestration');
        $settings = $this->settings();
        $query = $this->incident_query($product, $show_resolved, $limit);
        echo '<div class="scfs-cross-product-support__grid">';
        echo '<section class="scfs-cross-product-support__incidents"><h4>' . esc_html__('Platform incidents', 'sustainable-catalyst-feature-suggestions') . '</h4>';
        if (!$query->have_posts()) {
            echo '<div class="scfs-support-platform__empty"><strong>' . esc_html__('No published platform incidents match this context', 'sustainable-catalyst-feature-suggestions') . '</strong><p>' . esc_html__('Product-specific Known Issues and Guided Resolution remain available.', 'sustainable-catalyst-feature-suggestions') . '</p></div>';
        }
        foreach ($query->posts as $post) {
            $record = $this->incident_record($post, false);
            echo '<article class="scfs-cross-product-support__incident"><div class="scfs-cross-product-support__badges"><span class="status-' . esc_attr($record['status']) . '">' . esc_html($this->incident_statuses()[$record['status']] ?? $record['status']) . '</span><span class="severity-' . esc_attr($record['severity']) . '">' . esc_html($this->incident_severities()[$record['severity']] ?? $record['severity']) . '</span></div><h5><a href="' . esc_url($record['url']) . '">' . esc_html($record['title']) . '</a></h5><p>' . esc_html($record['summary']) . '</p>';
            if ($record['products']) {
                echo '<p class="scfs-cross-product-support__products">' . esc_html(implode(' · ', wp_list_pluck($record['products'], 'name'))) . '</p>';
            }
            if ($record['workaround']) {
                echo '<p><strong>' . esc_html__('Current workaround:', 'sustainable-catalyst-feature-suggestions') . '</strong> ' . esc_html(wp_trim_words($record['workaround'], 32)) . '</p>';
            }
            echo '<a href="' . esc_url($record['url']) . '">' . esc_html__('View incident summary', 'sustainable-catalyst-feature-suggestions') . ' →</a></article>';
        }
        echo '</section>';
        if ($product !== '' && !empty($settings['show_related_product_recommendations'])) {
            $routes = $this->recommend_routes($product);
            echo '<aside class="scfs-cross-product-support__routes"><h4>' . esc_html__('Related product paths', 'sustainable-catalyst-feature-suggestions') . '</h4>';
            if (!$routes) {
                echo '<p>' . esc_html__('No related-product routes are configured for this product.', 'sustainable-catalyst-feature-suggestions') . '</p>';
            }
            foreach (array_slice($routes, 0, 8) as $route) {
                $url = add_query_arg(array('scfs_support_view' => 'resolve', 'scfs_support_product' => $route['product']));
                echo '<article><h5>' . esc_html(ucwords(str_replace('-', ' ', $route['product']))) . '</h5><p>' . esc_html($route['reason']) . ($route['component'] !== '' ? ': ' . esc_html($route['component']) : '') . '</p><a href="' . esc_url($url . '#support-center') . '">' . esc_html__('Open related support context', 'sustainable-catalyst-feature-suggestions') . ' →</a></article>';
            }
            echo '</aside>';
        }
        echo '</div>';
        if ($product !== '' && !empty($settings['show_dependency_notices'])) {
            $journey = $this->journey_record($product);
            echo '<section class="scfs-cross-product-support__journey"><h4>' . esc_html__('Cross-product resolution journey', 'sustainable-catalyst-feature-suggestions') . '</h4><ol>';
            foreach ($journey['steps'] as $step) {
                $url = add_query_arg(array('scfs_support_view' => $step['view'], 'scfs_support_product' => $step['product']));
                echo '<li><a href="' . esc_url($url . '#support-center') . '">' . esc_html($step['label']) . '</a></li>';
            }
            echo '</ol></section>';
        }
    }

    public function add_meta_boxes() {
        add_meta_box('scfs-platform-incident-details', __('Platform Incident Details', 'sustainable-catalyst-feature-suggestions'), array($this, 'render_incident_meta_box'), self::INCIDENT_POST_TYPE, 'normal', 'high');
        foreach (array('sc_support_article', 'sc_known_issue', 'sc_release_record') as $post_type) {
            add_meta_box('scfs-cross-product-relationships', __('Cross-Product Orchestration', 'sustainable-catalyst-feature-suggestions'), array($this, 'render_relationship_meta_box'), $post_type, 'side', 'default');
        }
    }

    public function render_incident_meta_box($post) {
        wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME);
        $status = get_post_meta($post->ID, '_scfs_incident_status', true) ?: 'investigating';
        $severity = get_post_meta($post->ID, '_scfs_incident_severity', true) ?: 'moderate';
        $summary = get_post_meta($post->ID, '_scfs_incident_summary', true);
        $workaround = get_post_meta($post->ID, '_scfs_incident_workaround', true);
        $started = get_post_meta($post->ID, '_scfs_incident_started_at', true);
        $resolved = get_post_meta($post->ID, '_scfs_incident_resolved_at', true);
        echo '<p><label><strong>' . esc_html__('Status', 'sustainable-catalyst-feature-suggestions') . '</strong><br><select name="scfs_incident_status">';
        foreach ($this->incident_statuses() as $key => $label) {
            echo '<option value="' . esc_attr($key) . '" ' . selected($status, $key, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select></label></p><p><label><strong>' . esc_html__('Severity', 'sustainable-catalyst-feature-suggestions') . '</strong><br><select name="scfs_incident_severity">';
        foreach ($this->incident_severities() as $key => $label) {
            echo '<option value="' . esc_attr($key) . '" ' . selected($severity, $key, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select></label></p>';
        echo '<p><label><strong>' . esc_html__('Public summary', 'sustainable-catalyst-feature-suggestions') . '</strong><br><textarea class="widefat" rows="4" name="scfs_incident_summary">' . esc_textarea($summary) . '</textarea></label></p>';
        echo '<p><label><strong>' . esc_html__('Public workaround', 'sustainable-catalyst-feature-suggestions') . '</strong><br><textarea class="widefat" rows="4" name="scfs_incident_workaround">' . esc_textarea($workaround) . '</textarea></label></p>';
        echo '<p><label><strong>' . esc_html__('Started at', 'sustainable-catalyst-feature-suggestions') . '</strong><br><input class="widefat" type="datetime-local" name="scfs_incident_started_at" value="' . esc_attr($started) . '"></label></p>';
        echo '<p><label><strong>' . esc_html__('Resolved at', 'sustainable-catalyst-feature-suggestions') . '</strong><br><input class="widefat" type="datetime-local" name="scfs_incident_resolved_at" value="' . esc_attr($resolved) . '"></label></p>';
        echo '<p class="description">' . esc_html__('Assign every affected product, component, version, issue type, and release using the shared taxonomy panels. Publication remains a human editorial decision.', 'sustainable-catalyst-feature-suggestions') . '</p>';
    }

    public function render_relationship_meta_box($post) {
        wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME);
        $meta = $this->sanitize_relationship_meta(get_post_meta($post->ID, '_scfs_orchestration_relationships', true));
        echo '<p><label><strong>' . esc_html__('Related product slugs', 'sustainable-catalyst-feature-suggestions') . '</strong><br><textarea class="widefat" rows="3" name="scfs_related_product_slugs">' . esc_textarea(implode(', ', $meta['related_products'])) . '</textarea></label></p>';
        echo '<p><label><strong>' . esc_html__('Related platform incident IDs', 'sustainable-catalyst-feature-suggestions') . '</strong><br><input class="widefat" name="scfs_related_incident_ids" value="' . esc_attr(implode(', ', $meta['related_incidents'])) . '"></label></p>';
        if ($post->post_type === 'sc_release_record') {
            echo '<p><label><strong>' . esc_html__('Dependent release record IDs', 'sustainable-catalyst-feature-suggestions') . '</strong><br><input class="widefat" name="scfs_dependent_release_ids" value="' . esc_attr(implode(', ', $meta['dependent_releases'])) . '"></label></p>';
        }
        echo '<p><label><strong>' . esc_html__('Resolution journey stage', 'sustainable-catalyst-feature-suggestions') . '</strong><br><select name="scfs_journey_stage"><option value="">' . esc_html__('Not assigned', 'sustainable-catalyst-feature-suggestions') . '</option>';
        foreach (array('diagnose', 'configure', 'integrate', 'recover', 'verify') as $stage) {
            echo '<option value="' . esc_attr($stage) . '" ' . selected($meta['journey_stage'], $stage, false) . '>' . esc_html(ucfirst($stage)) . '</option>';
        }
        echo '</select></label></p><p class="description">' . esc_html__('These relationships are public orchestration metadata only. Do not enter private case details.', 'sustainable-catalyst-feature-suggestions') . '</p>';
    }

    private function can_save($post_id, $post) {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return false;
        }
        if (!isset($_POST[self::NONCE_NAME]) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[self::NONCE_NAME])), self::NONCE_ACTION)) {
            return false;
        }
        return current_user_can('edit_post', $post_id) && !wp_is_post_revision($post_id) && $post;
    }

    public function save_incident($post_id, $post) {
        if (!$this->can_save($post_id, $post)) {
            return;
        }
        $status = sanitize_key($_POST['scfs_incident_status'] ?? 'investigating');
        $severity = sanitize_key($_POST['scfs_incident_severity'] ?? 'moderate');
        if (!isset($this->incident_statuses()[$status])) {
            $status = 'investigating';
        }
        if (!isset($this->incident_severities()[$severity])) {
            $severity = 'moderate';
        }
        update_post_meta($post_id, '_scfs_incident_status', $status);
        update_post_meta($post_id, '_scfs_incident_severity', $severity);
        update_post_meta($post_id, '_scfs_incident_summary', sanitize_textarea_field(wp_unslash($_POST['scfs_incident_summary'] ?? '')));
        update_post_meta($post_id, '_scfs_incident_workaround', sanitize_textarea_field(wp_unslash($_POST['scfs_incident_workaround'] ?? '')));
        update_post_meta($post_id, '_scfs_incident_started_at', sanitize_text_field(wp_unslash($_POST['scfs_incident_started_at'] ?? '')));
        update_post_meta($post_id, '_scfs_incident_resolved_at', sanitize_text_field(wp_unslash($_POST['scfs_incident_resolved_at'] ?? '')));
        $products = wp_get_post_terms($post_id, SCFS_Product_Integration::PRODUCT_TAXONOMY, array('fields' => 'slugs'));
        if (!is_wp_error($products) && $products) {
            update_post_meta($post_id, '_scfs_incident_primary_product', sanitize_title(reset($products)));
        }
    }

    public function save_relationships($post_id, $post) {
        if (!$this->can_save($post_id, $post)) {
            return;
        }
        $products = preg_split('/[\s,]+/', (string) wp_unslash($_POST['scfs_related_product_slugs'] ?? ''), -1, PREG_SPLIT_NO_EMPTY);
        $incidents = preg_split('/[\s,]+/', (string) wp_unslash($_POST['scfs_related_incident_ids'] ?? ''), -1, PREG_SPLIT_NO_EMPTY);
        $releases = preg_split('/[\s,]+/', (string) wp_unslash($_POST['scfs_dependent_release_ids'] ?? ''), -1, PREG_SPLIT_NO_EMPTY);
        $meta = $this->sanitize_relationship_meta(array(
            'related_products' => $products,
            'related_incidents' => $incidents,
            'dependent_releases' => $releases,
            'journey_stage' => sanitize_key($_POST['scfs_journey_stage'] ?? ''),
        ));
        update_post_meta($post_id, '_scfs_orchestration_relationships', $meta);
    }

    public function register_admin_page() {
        add_submenu_page(
            'edit.php?post_type=' . Sustainable_Catalyst_Feature_Suggestions::POST_TYPE,
            __('Cross-Product Support', 'sustainable-catalyst-feature-suggestions'),
            __('Cross-Product Support', 'sustainable-catalyst-feature-suggestions'),
            $this->admin_capability(),
            'scfs-cross-product-support',
            array($this, 'render_admin_page')
        );
    }

    public function render_admin_page() {
        if (!$this->can_manage()) {
            wp_die(__('You do not have permission to manage cross-product support.', 'sustainable-catalyst-feature-suggestions'));
        }
        $settings = $this->settings();
        $overview = $this->overview_record('');
        echo '<div class="wrap scfs-cross-product-admin"><h1>' . esc_html__('Cross-Product Support Orchestration', 'sustainable-catalyst-feature-suggestions') . '</h1><p>' . esc_html__('Coordinate public incidents, product dependencies, shared components, support routes, and multi-product resolution journeys. All incident declarations and publication decisions require human review.', 'sustainable-catalyst-feature-suggestions') . '</p>';
        if (!empty($_GET['updated'])) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Cross-product settings saved.', 'sustainable-catalyst-feature-suggestions') . '</p></div>';
        }
        echo '<div class="scfs-cross-product-admin__cards"><article><span>' . esc_html__('Published incidents', 'sustainable-catalyst-feature-suggestions') . '</span><strong>' . esc_html((string) $overview['published_incidents']) . '</strong></article><article><span>' . esc_html__('Active incidents', 'sustainable-catalyst-feature-suggestions') . '</span><strong>' . esc_html((string) $overview['active_incidents']) . '</strong></article><article><span>' . esc_html__('Critical incidents', 'sustainable-catalyst-feature-suggestions') . '</span><strong>' . esc_html((string) $overview['critical_incidents']) . '</strong></article><article><span>' . esc_html__('Dependency edges', 'sustainable-catalyst-feature-suggestions') . '</span><strong>' . esc_html((string) $overview['dependency_count']) . '</strong></article></div>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="scfs_save_cross_product_settings">';
        wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME);
        echo '<h2>' . esc_html__('Product dependency graph', 'sustainable-catalyst-feature-suggestions') . '</h2><p>' . esc_html__('Enter one relationship per line using: source-product | relationship | target-product | shared component | criticality | active.', 'sustainable-catalyst-feature-suggestions') . '</p><textarea class="large-text code" rows="14" name="dependency_lines" aria-describedby="scfs-dependency-help">' . esc_textarea($settings['dependency_lines']) . '</textarea><p id="scfs-dependency-help" class="description">' . esc_html__('Relationships: depends_on, integrates_with, shares_component, routes_to, provides_data_to. Criticality: low, moderate, high, critical.', 'sustainable-catalyst-feature-suggestions') . '</p>';
        echo '<table class="form-table"><tr><th>' . esc_html__('Public platform view', 'sustainable-catalyst-feature-suggestions') . '</th><td><label><input type="checkbox" name="show_platform_status_view" value="1" ' . checked($settings['show_platform_status_view'], '1', false) . '> ' . esc_html__('Show Platform status in the public Support Center', 'sustainable-catalyst-feature-suggestions') . '</label></td></tr><tr><th>' . esc_html__('Recommendations', 'sustainable-catalyst-feature-suggestions') . '</th><td><label><input type="checkbox" name="show_related_product_recommendations" value="1" ' . checked($settings['show_related_product_recommendations'], '1', false) . '> ' . esc_html__('Show related-product support recommendations', 'sustainable-catalyst-feature-suggestions') . '</label></td></tr><tr><th>' . esc_html__('Resolution journeys', 'sustainable-catalyst-feature-suggestions') . '</th><td><label><input type="checkbox" name="show_dependency_notices" value="1" ' . checked($settings['show_dependency_notices'], '1', false) . '> ' . esc_html__('Show cross-product resolution journeys', 'sustainable-catalyst-feature-suggestions') . '</label></td></tr></table><p><button class="button button-primary">' . esc_html__('Save orchestration settings', 'sustainable-catalyst-feature-suggestions') . '</button> <a class="button" href="' . esc_url(admin_url('post-new.php?post_type=' . self::INCIDENT_POST_TYPE)) . '">' . esc_html__('Add platform incident', 'sustainable-catalyst-feature-suggestions') . '</a></p></form>';
        echo '<h2>' . esc_html__('Current dependency records', 'sustainable-catalyst-feature-suggestions') . '</h2><table class="widefat striped"><thead><tr><th>' . esc_html__('Source', 'sustainable-catalyst-feature-suggestions') . '</th><th>' . esc_html__('Relationship', 'sustainable-catalyst-feature-suggestions') . '</th><th>' . esc_html__('Target', 'sustainable-catalyst-feature-suggestions') . '</th><th>' . esc_html__('Component', 'sustainable-catalyst-feature-suggestions') . '</th><th>' . esc_html__('Criticality', 'sustainable-catalyst-feature-suggestions') . '</th></tr></thead><tbody>';
        $dependencies = $this->dependency_records();
        if (!$dependencies) {
            echo '<tr><td colspan="5">' . esc_html__('No product dependencies have been configured.', 'sustainable-catalyst-feature-suggestions') . '</td></tr>';
        }
        foreach ($dependencies as $edge) {
            echo '<tr><td><code>' . esc_html($edge['source']) . '</code></td><td>' . esc_html($this->relationship_types()[$edge['relationship']] ?? $edge['relationship']) . '</td><td><code>' . esc_html($edge['target']) . '</code></td><td>' . esc_html($edge['component']) . '</td><td>' . esc_html(ucfirst($edge['criticality'])) . '</td></tr>';
        }
        echo '</tbody></table></div>';
    }

    public function save_settings_action() {
        if (!$this->can_manage()) {
            wp_die(__('Forbidden', 'sustainable-catalyst-feature-suggestions'));
        }
        check_admin_referer(self::NONCE_ACTION, self::NONCE_NAME);
        $records = $this->dependencies_from_text(wp_unslash($_POST['dependency_lines'] ?? ''));
        $settings = array(
            'show_platform_status_view' => isset($_POST['show_platform_status_view']) ? '1' : '',
            'show_related_product_recommendations' => isset($_POST['show_related_product_recommendations']) ? '1' : '',
            'show_dependency_notices' => isset($_POST['show_dependency_notices']) ? '1' : '',
            'show_resolved_incidents_days' => absint($_POST['show_resolved_incidents_days'] ?? 30),
            'dependency_lines' => $this->dependency_lines($records),
        );
        update_option(self::OPTION_KEY, $settings, false);
        wp_safe_redirect(admin_url('edit.php?post_type=' . Sustainable_Catalyst_Feature_Suggestions::POST_TYPE . '&page=scfs-cross-product-support&updated=1'));
        exit;
    }

    public function decorate_incident_content($content) {
        if (!is_singular(self::INCIDENT_POST_TYPE) || !in_the_loop() || !is_main_query()) {
            return $content;
        }
        $record = $this->incident_record(get_the_ID(), false);
        if (!$record) {
            return $content;
        }
        $summary = '<section class="scfs-cross-product-support__single"><div class="scfs-cross-product-support__badges"><span class="status-' . esc_attr($record['status']) . '">' . esc_html($this->incident_statuses()[$record['status']] ?? $record['status']) . '</span><span class="severity-' . esc_attr($record['severity']) . '">' . esc_html($this->incident_severities()[$record['severity']] ?? $record['severity']) . '</span></div>';
        if ($record['summary']) {
            $summary .= '<p><strong>' . esc_html__('Summary:', 'sustainable-catalyst-feature-suggestions') . '</strong> ' . esc_html($record['summary']) . '</p>';
        }
        if ($record['products']) {
            $summary .= '<p><strong>' . esc_html__('Affected products:', 'sustainable-catalyst-feature-suggestions') . '</strong> ' . esc_html(implode(', ', wp_list_pluck($record['products'], 'name'))) . '</p>';
        }
        if ($record['workaround']) {
            $summary .= '<p><strong>' . esc_html__('Current workaround:', 'sustainable-catalyst-feature-suggestions') . '</strong> ' . esc_html($record['workaround']) . '</p>';
        }
        $summary .= '<p class="scfs-support-platform__privacy-note">' . esc_html__('This public record does not contain requester identity, private correspondence, confidential documents, or private case content.', 'sustainable-catalyst-feature-suggestions') . '</p></section>';
        return $summary . $content;
    }

    public function incident_columns($columns) {
        $columns['scfs_incident_status'] = __('Status', 'sustainable-catalyst-feature-suggestions');
        $columns['scfs_incident_severity'] = __('Severity', 'sustainable-catalyst-feature-suggestions');
        $columns['scfs_incident_products'] = __('Affected Products', 'sustainable-catalyst-feature-suggestions');
        return $columns;
    }

    public function incident_column_content($column, $post_id) {
        if ($column === 'scfs_incident_status') {
            $key = get_post_meta($post_id, '_scfs_incident_status', true) ?: 'investigating';
            echo esc_html($this->incident_statuses()[$key] ?? $key);
        } elseif ($column === 'scfs_incident_severity') {
            $key = get_post_meta($post_id, '_scfs_incident_severity', true) ?: 'moderate';
            echo esc_html($this->incident_severities()[$key] ?? $key);
        } elseif ($column === 'scfs_incident_products') {
            echo esc_html(implode(', ', wp_list_pluck($this->post_terms($post_id, SCFS_Product_Integration::PRODUCT_TAXONOMY), 'name')));
        }
    }

    public function register_rest_routes() {
        $namespace = Sustainable_Catalyst_Feature_Suggestions::REST_NAMESPACE;
        register_rest_route($namespace, '/cross-product/schema', array('methods' => 'GET', 'callback' => array($this, 'rest_schema'), 'permission_callback' => '__return_true'));
        register_rest_route($namespace, '/cross-product/overview', array('methods' => 'GET', 'callback' => array($this, 'rest_overview'), 'permission_callback' => '__return_true'));
        register_rest_route($namespace, '/cross-product/incidents', array('methods' => 'GET', 'callback' => array($this, 'rest_incidents'), 'permission_callback' => '__return_true'));
        register_rest_route($namespace, '/cross-product/routes', array('methods' => 'GET', 'callback' => array($this, 'rest_routes'), 'permission_callback' => '__return_true'));
        register_rest_route($namespace, '/cross-product/journey', array('methods' => 'POST', 'callback' => array($this, 'rest_journey'), 'permission_callback' => '__return_true'));
        register_rest_route($namespace, '/cross-product/snapshot', array('methods' => 'GET', 'callback' => array($this, 'rest_snapshot'), 'permission_callback' => array($this, 'rest_permission')));
    }

    public function rest_permission() {
        return current_user_can($this->admin_capability());
    }

    public function rest_schema() {
        return rest_ensure_response($this->schema_record());
    }

    public function rest_overview($request) {
        return rest_ensure_response($this->overview_record(sanitize_title($request->get_param('product'))));
    }

    public function rest_incidents($request) {
        $product = sanitize_title($request->get_param('product'));
        $query = $this->incident_query($product, true, min(100, max(1, absint($request->get_param('limit') ?: 20))));
        return rest_ensure_response(array('ok' => true, 'version' => self::VERSION, 'items' => array_values(array_map(function ($post) { return $this->incident_record($post, false); }, $query->posts))));
    }

    public function rest_routes($request) {
        $product = sanitize_title($request->get_param('product'));
        return rest_ensure_response(array('ok' => true, 'version' => self::VERSION, 'product' => $product, 'routes' => $this->recommend_routes($product, null, array('component' => sanitize_text_field($request->get_param('component'))))));
    }

    public function rest_journey($request) {
        $params = (array) $request->get_json_params();
        return rest_ensure_response($this->journey_record(sanitize_title($params['product'] ?? ''), array(
            'component' => sanitize_text_field($params['component'] ?? ''),
            'issue_type' => sanitize_key($params['issue_type'] ?? ''),
            'symptom' => !empty($params['symptom']),
        )));
    }

    public function rest_snapshot() {
        return rest_ensure_response(array(
            'ok' => true,
            'version' => self::VERSION,
            'schema' => $this->schema_record(),
            'overview' => $this->overview_record(''),
            'dependencies' => $this->dependency_records(),
            'private_case_content_stored' => false,
            'human_review_required' => true,
        ));
    }
}
