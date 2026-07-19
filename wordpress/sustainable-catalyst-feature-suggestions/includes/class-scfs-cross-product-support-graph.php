<?php
/**
 * Cross-Product Support Graph and Platform Handoffs.
 *
 * Builds a public, privacy-safe support graph across Sustainable Catalyst
 * products and connects documentation, known issues, releases, examples,
 * troubleshooting guidance, and governed product handoffs.
 *
 * @package Sustainable_Catalyst_Feature_Suggestions
 */

if (!defined('ABSPATH')) {
    exit;
}

final class SCFS_Cross_Product_Support_Graph {
    const VERSION = '5.8.0';
    const SCHEMA = 'scfs-cross-product-support-graph/1.0';
    const HANDOFF_SCHEMA = 'scfs-platform-support-handoff/1.0';
    const ADMIN_PAGE = 'scfs-support-graph';
    const OPTION_KEY = 'scfs_cross_product_support_graph_settings';
    const SNAPSHOT_OPTION = 'scfs_cross_product_support_graph_snapshot';
    const CRON_HOOK = 'scfs_cross_product_support_graph_daily';
    const NONCE_ACTION = 'scfs_cross_product_support_graph';
    const SHORTCODE = 'scfs_cross_product_support_graph';
    const LEGACY_SHORTCODE = 'scfs_platform_support_graph';

    private static $instance = null;

    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('init', array($this, 'register_shortcodes'), 34);
        add_action('admin_menu', array($this, 'register_admin_page'), 34);
        add_action('admin_enqueue_scripts', array($this, 'admin_assets'));
        add_action('wp_enqueue_scripts', array($this, 'register_assets'));
        add_action('rest_api_init', array($this, 'register_rest_routes'));
        add_action('admin_post_scfs_support_graph_refresh', array($this, 'refresh_action'));
        add_action('admin_post_scfs_support_graph_export', array($this, 'export_action'));
        add_action(self::CRON_HOOK, array($this, 'scheduled_refresh'));
        add_action('save_post', array($this, 'invalidate_for_post'), 140, 3);
        add_filter('scfs_support_navigation_items', array($this, 'support_navigation_item'), 25, 4);
        add_filter('scfs_unified_support_result_context', array($this, 'unified_support_context'), 20, 2);

        if (defined('WP_CLI') && WP_CLI && class_exists('WP_CLI')) {
            WP_CLI::add_command('scfs support-graph refresh', array($this, 'cli_refresh'));
            WP_CLI::add_command('scfs support-graph product', array($this, 'cli_product'));
            WP_CLI::add_command('scfs support-graph handoff', array($this, 'cli_handoff'));
            WP_CLI::add_command('scfs support-graph integrity', array($this, 'cli_integrity'));
        }
    }

    public static function activate() {
        $instance = self::instance();
        if (!get_option(self::OPTION_KEY)) {
            add_option(self::OPTION_KEY, $instance->default_settings(), '', false);
        }
        if (function_exists('wp_next_scheduled') && function_exists('wp_schedule_event') && !wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK);
        }
        $instance->build_snapshot(true);
    }

    public static function deactivate() {
        if (function_exists('wp_next_scheduled')) {
            $timestamp = wp_next_scheduled(self::CRON_HOOK);
            if ($timestamp && function_exists('wp_unschedule_event')) {
                wp_unschedule_event($timestamp, self::CRON_HOOK);
            }
        }
    }

    public function default_settings() {
        return array(
            'show_support_graph_view' => '1',
            'show_product_routes' => '1',
            'show_handoff_recommendations' => '1',
            'maximum_products' => 50,
            'maximum_records_per_product' => 8,
            'daily_refresh_enabled' => '1',
        );
    }

    public function settings() {
        return wp_parse_args((array) get_option(self::OPTION_KEY, array()), $this->default_settings());
    }

    public function manage_capability() {
        return apply_filters('scfs_support_graph_capability', 'edit_others_posts');
    }

    public function can_manage() {
        return current_user_can($this->manage_capability());
    }

    public function schema_record() {
        return array(
            'schema' => self::SCHEMA,
            'handoff_schema' => self::HANDOFF_SCHEMA,
            'version' => self::VERSION,
            'capabilities' => array(
                'canonical_product_nodes',
                'product_version_component_context',
                'capability_registry',
                'support_article_links',
                'known_issue_links',
                'release_links',
                'example_and_troubleshooting_links',
                'cross_product_dependency_edges',
                'governed_platform_handoffs',
                'shortest_support_paths',
                'graph_integrity_reporting',
                'public_support_graph',
            ),
            'shortcodes' => array(self::SHORTCODE, self::LEGACY_SHORTCODE),
            'rest_routes' => array(
                '/support-graph/schema',
                '/support-graph/overview',
                '/support-graph/products',
                '/support-graph/product/{product}',
                '/support-graph/handoffs',
                '/support-graph/path',
                '/support-graph/integrity',
                '/support-graph/refresh',
            ),
            'privacy' => array(
                'public_records_only' => true,
                'personal_identifiers_exposed' => false,
                'raw_search_text_exposed' => false,
                'private_case_content_exposed' => false,
                'private_documents_exposed' => false,
            ),
            'governance' => array(
                'human_review_required' => true,
                'automatic_private_case_creation' => false,
                'automatic_product_redirect' => false,
                'automatic_roadmap_change' => false,
                'automatic_issue_resolution' => false,
            ),
        );
    }

    public function register_shortcodes() {
        add_shortcode(self::SHORTCODE, array($this, 'render_shortcode'));
        add_shortcode(self::LEGACY_SHORTCODE, array($this, 'render_shortcode'));
    }

    public function register_assets() {
        $css = dirname(__DIR__) . '/assets/cross-product-support-graph.css';
        $js = dirname(__DIR__) . '/assets/cross-product-support-graph.js';
        wp_register_style(
            'scfs-cross-product-support-graph',
            plugins_url('../assets/cross-product-support-graph.css', __FILE__),
            array(),
            is_readable($css) ? (string) filemtime($css) : self::VERSION
        );
        wp_register_script(
            'scfs-cross-product-support-graph',
            plugins_url('../assets/cross-product-support-graph.js', __FILE__),
            array(),
            is_readable($js) ? (string) filemtime($js) : self::VERSION,
            true
        );
    }

    public function admin_assets($hook) {
        if (strpos((string) $hook, self::ADMIN_PAGE) === false) {
            return;
        }
        $this->register_assets();
        wp_enqueue_style('scfs-cross-product-support-graph');
        wp_enqueue_script('scfs-cross-product-support-graph');
    }

    public function support_navigation_item($items, $settings, $product, $respect_content) {
        unset($settings, $product, $respect_content);
        if (empty($this->settings()['show_support_graph_view'])) {
            return $items;
        }
        $new = array();
        foreach ((array) $items as $key => $item) {
            $new[$key] = $item;
            if ($key === 'platform') {
                $new['support-graph'] = array(
                    'label' => __('Support graph', 'sustainable-catalyst-feature-suggestions'),
                    'description' => __('Products, capabilities, dependencies, and platform handoffs', 'sustainable-catalyst-feature-suggestions'),
                );
            }
        }
        if (!isset($new['support-graph'])) {
            $new['support-graph'] = array(
                'label' => __('Support graph', 'sustainable-catalyst-feature-suggestions'),
                'description' => __('Products, capabilities, dependencies, and platform handoffs', 'sustainable-catalyst-feature-suggestions'),
            );
        }
        return $new;
    }

    public function default_product_catalog() {
        $catalog = array(
            'decision-studio' => array(
                'name' => 'Decision Studio',
                'public_route' => '/platform/decision-studio/',
                'capabilities' => array('decision analysis', 'scenario comparison', 'decision briefs', 'outcomes', 'reassessment'),
                'handoffs' => array('catalyst-canvas', 'catalyst-data', 'catalyst-finance', 'site-intelligence', 'knowledge-library'),
            ),
            'catalyst-canvas' => array(
                'name' => 'Catalyst Canvas',
                'public_route' => '/platform/catalyst-canvas/',
                'capabilities' => array('personas', 'stakeholders', 'journeys', 'research evidence', 'assumption ledgers'),
                'handoffs' => array('decision-studio', 'catalyst-data', 'research-librarian', 'knowledge-library'),
            ),
            'catalyst-data' => array(
                'name' => 'Catalyst Data',
                'public_route' => '/platform/catalyst-data/',
                'capabilities' => array('datasets', 'instruments', 'observations', 'provenance', 'data quality', 'exports'),
                'handoffs' => array('decision-studio', 'site-intelligence', 'workbench', 'sustainable-catalyst-lab'),
            ),
            'catalyst-finance' => array(
                'name' => 'Catalyst Finance',
                'public_route' => '/platform/catalyst-finance/',
                'capabilities' => array('financial analysis', 'valuation', 'portfolio decisions', 'risk', 'scenario finance'),
                'handoffs' => array('decision-studio', 'catalyst-data', 'site-intelligence', 'workbench'),
            ),
            'catalyst-grit' => array(
                'name' => 'Catalyst Grit',
                'public_route' => '/platform/catalyst-grit/',
                'capabilities' => array('resilience assessment', 'human systems', 'institutional capacity', 'publication', 'monitoring'),
                'handoffs' => array('decision-studio', 'catalyst-canvas', 'catalyst-data', 'knowledge-library'),
            ),
            'catalyst-narrative-risk' => array(
                'name' => 'Catalyst Narrative Risk',
                'public_route' => '/platform/catalyst-narrative-risk/',
                'capabilities' => array('narrative analysis', 'claim mapping', 'evidence ledgers', 'risk assessment', 'case review'),
                'handoffs' => array('decision-studio', 'research-librarian', 'knowledge-library', 'catalyst-data'),
            ),
            'site-intelligence' => array(
                'name' => 'Site Intelligence',
                'public_route' => '/platform/site-intelligence/',
                'capabilities' => array('indicators', 'country intelligence', 'geospatial analysis', 'monitoring', 'alerts', 'briefings'),
                'handoffs' => array('decision-studio', 'catalyst-data', 'catalyst-finance', 'knowledge-library'),
            ),
            'sustainable-catalyst-lab' => array(
                'name' => 'Sustainable Catalyst Lab',
                'public_route' => '/lab/',
                'capabilities' => array('experiments', 'scientific models', 'notebooks', 'distributed compute', 'instrumentation'),
                'handoffs' => array('workbench', 'catalyst-data', 'decision-studio', 'knowledge-library'),
            ),
            'workbench' => array(
                'name' => 'Workbench',
                'public_route' => '/platform/workbench/',
                'capabilities' => array('calculations', 'graphs', 'simulations', 'engineering analysis', 'reports'),
                'handoffs' => array('sustainable-catalyst-lab', 'decision-studio', 'catalyst-data', 'knowledge-library'),
            ),
            'knowledge-library' => array(
                'name' => 'Knowledge Library',
                'public_route' => '/library/',
                'capabilities' => array('documents', 'collections', 'research pathways', 'publications', 'evidence access'),
                'handoffs' => array('research-librarian', 'decision-studio', 'site-intelligence', 'catalyst-canvas'),
            ),
            'research-librarian' => array(
                'name' => 'Research Librarian',
                'public_route' => '/research-librarian/',
                'capabilities' => array('research guidance', 'source retrieval', 'question routing', 'citation support', 'knowledge navigation'),
                'handoffs' => array('knowledge-library', 'catalyst-narrative-risk', 'catalyst-canvas', 'decision-studio'),
            ),
            'platform-core' => array(
                'name' => 'Platform Core and Infrastructure',
                'public_route' => '/platform/',
                'capabilities' => array('navigation', 'accessibility', 'authentication', 'APIs', 'performance', 'shared contracts'),
                'handoffs' => array('decision-studio', 'site-intelligence', 'knowledge-library', 'sustainable-catalyst-lab'),
            ),
        );
        return apply_filters('scfs_cross_product_support_catalog', $catalog);
    }

    private function support_url($slug) {
        $url = add_query_arg(array('scfs_product' => sanitize_title($slug)), home_url('/support/'));
        return $url . '#knowledge-base';
    }

    public function product_registry() {
        $catalog = $this->default_product_catalog();
        if (function_exists('get_terms') && class_exists('SCFS_Product_Integration')) {
            $terms = get_terms(array('taxonomy' => SCFS_Product_Integration::PRODUCT_TAXONOMY, 'hide_empty' => false));
            if (!is_wp_error($terms)) {
                foreach ((array) $terms as $term) {
                    $slug = sanitize_title($term->slug);
                    if (!isset($catalog[$slug])) {
                        $catalog[$slug] = array(
                            'name' => sanitize_text_field($term->name),
                            'public_route' => '/platform/',
                            'capabilities' => array(),
                            'handoffs' => array(),
                        );
                    } else {
                        $catalog[$slug]['name'] = sanitize_text_field($term->name);
                    }
                }
            }
        }
        $records = array();
        foreach ($catalog as $slug => $record) {
            $slug = sanitize_title($slug);
            if ($slug === '') {
                continue;
            }
            $records[$slug] = array(
                'slug' => $slug,
                'name' => sanitize_text_field($record['name'] ?? ucwords(str_replace('-', ' ', $slug))),
                'public_route' => esc_url_raw(home_url($record['public_route'] ?? '/platform/')),
                'support_route' => esc_url_raw($this->support_url($slug)),
                'capabilities' => array_values(array_unique(array_filter(array_map('sanitize_text_field', (array) ($record['capabilities'] ?? array()))))),
                'handoffs' => array_values(array_unique(array_filter(array_map('sanitize_title', (array) ($record['handoffs'] ?? array()))))),
            );
        }
        ksort($records);
        return array_slice($records, 0, max(1, absint($this->settings()['maximum_products'])), true);
    }

    private function post_type_counts($slug) {
        $counts = array('articles' => 0, 'known_issues' => 0, 'releases' => 0, 'examples' => 0, 'troubleshooting' => 0);
        if (!class_exists('WP_Query') || !class_exists('SCFS_Product_Integration')) {
            return $counts;
        }
        $types = array(
            'articles' => class_exists('SCFS_Knowledge_Base_Foundation') ? SCFS_Knowledge_Base_Foundation::ARTICLE_POST_TYPE : 'sc_support_article',
            'known_issues' => class_exists('SCFS_Knowledge_Base_Foundation') ? SCFS_Knowledge_Base_Foundation::ISSUE_POST_TYPE : 'sc_known_issue',
            'releases' => class_exists('SCFS_Product_Support_Platform') ? SCFS_Product_Support_Platform::RELEASE_POST_TYPE : 'sc_release_record',
        );
        foreach ($types as $key => $post_type) {
            $query = new WP_Query(array(
                'post_type' => $post_type,
                'post_status' => 'publish',
                'posts_per_page' => 1,
                'fields' => 'ids',
                'no_found_rows' => false,
                'tax_query' => array(array(
                    'taxonomy' => SCFS_Product_Integration::PRODUCT_TAXONOMY,
                    'field' => 'slug',
                    'terms' => $slug,
                )),
            ));
            $counts[$key] = absint($query->found_posts);
        }
        if (class_exists('SCFS_Knowledge_Base_Foundation')) {
            $type_tax = SCFS_Knowledge_Base_Foundation::ARTICLE_TYPE_TAXONOMY;
            foreach (array('examples' => array('example', 'worked-example'), 'troubleshooting' => array('troubleshooting', 'known-issue')) as $key => $terms) {
                $query = new WP_Query(array(
                    'post_type' => SCFS_Knowledge_Base_Foundation::ARTICLE_POST_TYPE,
                    'post_status' => 'publish',
                    'posts_per_page' => 1,
                    'fields' => 'ids',
                    'no_found_rows' => false,
                    'tax_query' => array(
                        'relation' => 'AND',
                        array('taxonomy' => SCFS_Product_Integration::PRODUCT_TAXONOMY, 'field' => 'slug', 'terms' => $slug),
                        array('taxonomy' => $type_tax, 'field' => 'slug', 'terms' => $terms),
                    ),
                ));
                $counts[$key] = absint($query->found_posts);
            }
        }
        return $counts;
    }

    private function recent_records($slug, $post_type, $limit = 5) {
        if (!class_exists('WP_Query') || !class_exists('SCFS_Product_Integration')) {
            return array();
        }
        $query = new WP_Query(array(
            'post_type' => $post_type,
            'post_status' => 'publish',
            'posts_per_page' => max(1, min(20, absint($limit))),
            'orderby' => 'modified',
            'order' => 'DESC',
            'tax_query' => array(array(
                'taxonomy' => SCFS_Product_Integration::PRODUCT_TAXONOMY,
                'field' => 'slug',
                'terms' => $slug,
            )),
        ));
        $records = array();
        foreach ((array) $query->posts as $post) {
            $records[] = array(
                'id' => absint($post->ID),
                'title' => sanitize_text_field(get_the_title($post)),
                'url' => esc_url_raw(get_permalink($post)),
                'modified' => get_post_modified_time('c', true, $post),
            );
        }
        return $records;
    }

    public function product_node_record($slug, $include_records = true) {
        $slug = sanitize_title($slug);
        $registry = $this->product_registry();
        if (!isset($registry[$slug])) {
            return array();
        }
        $node = $registry[$slug];
        $node['counts'] = $this->post_type_counts($slug);
        $node['coverage_score'] = $this->coverage_score($node['counts'], count($node['capabilities']));
        $node['coverage_state'] = $this->coverage_state($node['coverage_score']);
        if ($include_records) {
            $limit = absint($this->settings()['maximum_records_per_product']);
            $node['support_articles'] = $this->recent_records($slug, 'sc_support_article', $limit);
            $node['known_issues'] = $this->recent_records($slug, 'sc_known_issue', $limit);
            $node['releases'] = $this->recent_records($slug, 'sc_release_record', $limit);
        }
        return $node;
    }

    public function coverage_score($counts, $capability_count = 0) {
        $counts = wp_parse_args((array) $counts, array('articles' => 0, 'known_issues' => 0, 'releases' => 0, 'examples' => 0, 'troubleshooting' => 0));
        $score = 0;
        $score += min(30, absint($counts['articles']) * 3);
        $score += min(15, absint($counts['known_issues']) * 3);
        $score += min(20, absint($counts['releases']) * 4);
        $score += min(15, absint($counts['examples']) * 5);
        $score += min(15, absint($counts['troubleshooting']) * 5);
        $score += min(5, absint($capability_count));
        return max(0, min(100, $score));
    }

    public function coverage_state($score) {
        $score = (float) $score;
        if ($score >= 80) {
            return 'connected';
        }
        if ($score >= 60) {
            return 'strong';
        }
        if ($score >= 35) {
            return 'partial';
        }
        if ($score > 0) {
            return 'limited';
        }
        return 'unmapped';
    }

    public function graph_edges() {
        $registry = $this->product_registry();
        $edges = array();
        foreach ($registry as $slug => $node) {
            foreach ((array) $node['handoffs'] as $target) {
                if (!isset($registry[$target]) || $target === $slug) {
                    continue;
                }
                $key = $slug . '|routes_to|' . $target;
                $edges[$key] = array(
                    'source' => $slug,
                    'target' => $target,
                    'relationship' => 'routes_to',
                    'component' => '',
                    'criticality' => 'moderate',
                    'active' => true,
                    'source_type' => 'catalog',
                );
            }
        }
        if (class_exists('SCFS_Cross_Product_Support_Orchestration')) {
            foreach ((array) SCFS_Cross_Product_Support_Orchestration::instance()->dependency_records() as $edge) {
                $source = sanitize_title($edge['source'] ?? '');
                $target = sanitize_title($edge['target'] ?? '');
                if ($source === '' || $target === '' || $source === $target) {
                    continue;
                }
                $relationship = sanitize_key($edge['relationship'] ?? 'depends_on');
                $key = $source . '|' . $relationship . '|' . $target;
                $edges[$key] = array(
                    'source' => $source,
                    'target' => $target,
                    'relationship' => $relationship,
                    'component' => sanitize_text_field($edge['component'] ?? ''),
                    'criticality' => sanitize_key($edge['criticality'] ?? 'moderate'),
                    'active' => !empty($edge['active']),
                    'source_type' => 'configured',
                );
            }
        }
        ksort($edges);
        return array_values($edges);
    }

    public function graph_record($include_records = false) {
        $nodes = array();
        $total_score = 0;
        foreach ($this->product_registry() as $slug => $record) {
            $node = $this->product_node_record($slug, $include_records);
            $nodes[] = $node;
            $total_score += (float) ($node['coverage_score'] ?? 0);
        }
        $integrity = $this->integrity_report($nodes, $this->graph_edges());
        return array(
            'schema' => self::SCHEMA,
            'version' => self::VERSION,
            'generated_at' => gmdate('c'),
            'product_count' => count($nodes),
            'edge_count' => count($this->graph_edges()),
            'average_coverage_score' => count($nodes) ? round($total_score / count($nodes), 1) : 0,
            'nodes' => $nodes,
            'edges' => $this->graph_edges(),
            'integrity' => $integrity,
            'privacy' => $this->schema_record()['privacy'],
            'governance' => $this->schema_record()['governance'],
        );
    }

    private function tokenize($value) {
        $value = strtolower(wp_strip_all_tags((string) $value));
        $parts = preg_split('/[^a-z0-9]+/', $value);
        return array_values(array_unique(array_filter($parts)));
    }

    public function handoff_plan($product, $intent = '', $component = '', $version = '') {
        $product = sanitize_title($product);
        $registry = $this->product_registry();
        $tokens = $this->tokenize($intent . ' ' . $component);
        $scores = array();
        foreach ($registry as $slug => $node) {
            if ($slug === $product) {
                continue;
            }
            $score = 0;
            $reasons = array();
            foreach ($tokens as $token) {
                foreach ((array) $node['capabilities'] as $capability) {
                    if (strpos(strtolower($capability), $token) !== false) {
                        $score += 12;
                        $reasons[] = sprintf(__('Matches capability: %s', 'sustainable-catalyst-feature-suggestions'), $capability);
                    }
                }
                if (strpos(strtolower($node['name']), $token) !== false) {
                    $score += 8;
                }
            }
            foreach ($this->graph_edges() as $edge) {
                if (empty($edge['active'])) {
                    continue;
                }
                if ($edge['source'] === $product && $edge['target'] === $slug) {
                    $score += $edge['relationship'] === 'routes_to' ? 30 : 20;
                    $reasons[] = sprintf(__('Configured relationship: %s', 'sustainable-catalyst-feature-suggestions'), str_replace('_', ' ', $edge['relationship']));
                } elseif ($edge['target'] === $product && $edge['source'] === $slug) {
                    $score += 10;
                    $reasons[] = __('Related upstream product', 'sustainable-catalyst-feature-suggestions');
                }
                if ($component !== '' && !empty($edge['component']) && strpos(strtolower($edge['component']), strtolower($component)) !== false) {
                    $score += 12;
                    $reasons[] = __('Shares the selected component', 'sustainable-catalyst-feature-suggestions');
                }
            }
            if ($score <= 0) {
                continue;
            }
            $node_record = $this->product_node_record($slug, false);
            $scores[] = array(
                'product' => $slug,
                'name' => $node['name'],
                'score' => min(100, $score),
                'reasons' => array_values(array_unique($reasons)),
                'support_route' => $node['support_route'],
                'public_route' => $node['public_route'],
                'coverage_score' => $node_record['coverage_score'] ?? 0,
                'coverage_state' => $node_record['coverage_state'] ?? 'unmapped',
            );
        }
        usort($scores, function ($a, $b) {
            if ($a['score'] === $b['score']) {
                return strcmp($a['name'], $b['name']);
            }
            return $b['score'] <=> $a['score'];
        });
        $recommendations = array_slice($scores, 0, 5);
        return array(
            'schema' => self::HANDOFF_SCHEMA,
            'version' => self::VERSION,
            'starting_product' => $product,
            'context' => array(
                'version' => sanitize_text_field($version),
                'component' => sanitize_text_field($component),
                'intent_tokens' => $tokens,
            ),
            'recommendations' => $recommendations,
            'recommended_start' => $recommendations[0] ?? null,
            'private_support_route' => esc_url_raw(home_url('/institution/contact/')),
            'automatic_redirect' => false,
            'automatic_private_case_creation' => false,
            'human_review_required' => true,
        );
    }

    public function shortest_path($source, $target) {
        $source = sanitize_title($source);
        $target = sanitize_title($target);
        if ($source === '' || $target === '') {
            return array();
        }
        if ($source === $target) {
            return array($source);
        }
        $adjacency = array();
        foreach ($this->graph_edges() as $edge) {
            if (empty($edge['active'])) {
                continue;
            }
            $adjacency[$edge['source']][] = $edge['target'];
            $adjacency[$edge['target']][] = $edge['source'];
        }
        $queue = array(array($source));
        $visited = array($source => true);
        while ($queue) {
            $path = array_shift($queue);
            $current = end($path);
            foreach (array_unique((array) ($adjacency[$current] ?? array())) as $next) {
                if (isset($visited[$next])) {
                    continue;
                }
                $next_path = array_merge($path, array($next));
                if ($next === $target) {
                    return $next_path;
                }
                $visited[$next] = true;
                $queue[] = $next_path;
            }
        }
        return array();
    }

    public function integrity_report($nodes = null, $edges = null) {
        $nodes = $nodes === null ? array_values($this->product_registry()) : (array) $nodes;
        $edges = $edges === null ? $this->graph_edges() : (array) $edges;
        $slugs = array();
        $issues = array();
        foreach ($nodes as $node) {
            $slug = sanitize_title($node['slug'] ?? '');
            if ($slug === '') {
                $issues[] = array('code' => 'missing_slug', 'severity' => 'error', 'message' => 'A product node is missing its slug.');
                continue;
            }
            if (isset($slugs[$slug])) {
                $issues[] = array('code' => 'duplicate_slug', 'severity' => 'error', 'message' => 'Duplicate product slug: ' . $slug);
            }
            $slugs[$slug] = true;
            if (empty($node['support_route'])) {
                $issues[] = array('code' => 'missing_support_route', 'severity' => 'warning', 'message' => 'Missing support route: ' . $slug);
            }
            if (empty($node['capabilities'])) {
                $issues[] = array('code' => 'missing_capabilities', 'severity' => 'warning', 'message' => 'No capabilities registered: ' . $slug);
            }
        }
        $edge_keys = array();
        foreach ($edges as $edge) {
            $source = sanitize_title($edge['source'] ?? '');
            $target = sanitize_title($edge['target'] ?? '');
            $relationship = sanitize_key($edge['relationship'] ?? '');
            $key = $source . '|' . $relationship . '|' . $target;
            if ($source === $target && $source !== '') {
                $issues[] = array('code' => 'self_edge', 'severity' => 'error', 'message' => 'Self-referencing edge: ' . $source);
            }
            if (!isset($slugs[$source]) || !isset($slugs[$target])) {
                $issues[] = array('code' => 'unknown_node', 'severity' => 'warning', 'message' => 'Edge references an unknown product: ' . $key);
            }
            if (isset($edge_keys[$key])) {
                $issues[] = array('code' => 'duplicate_edge', 'severity' => 'warning', 'message' => 'Duplicate edge: ' . $key);
            }
            $edge_keys[$key] = true;
        }
        $errors = count(array_filter($issues, function ($issue) { return $issue['severity'] === 'error'; }));
        $warnings = count($issues) - $errors;
        $payload = wp_json_encode(array('nodes' => array_keys($slugs), 'edges' => array_keys($edge_keys)));
        return array(
            'schema' => 'scfs-cross-product-support-graph-integrity/1.0',
            'version' => self::VERSION,
            'valid' => $errors === 0,
            'node_count' => count($slugs),
            'edge_count' => count($edge_keys),
            'error_count' => $errors,
            'warning_count' => $warnings,
            'issues' => $issues,
            'checksum' => hash('sha256', (string) $payload),
            'deterministic_ordering' => true,
        );
    }

    public function build_snapshot($force = false) {
        if (!$force) {
            $cached = get_option(self::SNAPSHOT_OPTION, array());
            if (is_array($cached) && !empty($cached['version']) && $cached['version'] === self::VERSION) {
                return $cached;
            }
        }
        $snapshot = $this->graph_record(false);
        update_option(self::SNAPSHOT_OPTION, $snapshot, false);
        return $snapshot;
    }

    public function scheduled_refresh() {
        if (!empty($this->settings()['daily_refresh_enabled'])) {
            $this->build_snapshot(true);
        }
    }

    public function invalidate_for_post($post_id, $post, $update) {
        unset($post_id, $update);
        if (!$post || wp_is_post_revision($post->ID)) {
            return;
        }
        $types = array('sc_support_article', 'sc_known_issue', 'sc_release_record', 'sc_platform_incident');
        if (in_array((string) $post->post_type, $types, true)) {
            delete_option(self::SNAPSHOT_OPTION);
        }
    }

    public function unified_support_context($context, $request) {
        $request = (array) $request;
        $product = sanitize_title($request['product'] ?? '');
        if ($product === '') {
            return $context;
        }
        $context['support_graph'] = array(
            'product' => $this->product_node_record($product, false),
            'handoffs' => $this->handoff_plan($product, $request['query'] ?? '', $request['component'] ?? '', $request['version'] ?? ''),
        );
        return $context;
    }

    public function register_admin_page() {
        add_submenu_page(
            'edit.php?post_type=' . Sustainable_Catalyst_Feature_Suggestions::POST_TYPE,
            __('Support Graph', 'sustainable-catalyst-feature-suggestions'),
            __('Support Graph', 'sustainable-catalyst-feature-suggestions'),
            $this->manage_capability(),
            self::ADMIN_PAGE,
            array($this, 'render_admin_page')
        );
    }

    public function render_admin_page() {
        if (!$this->can_manage()) {
            wp_die(__('Forbidden', 'sustainable-catalyst-feature-suggestions'));
        }
        $snapshot = $this->build_snapshot(false);
        echo '<div class="wrap scfs-support-graph-admin"><h1>' . esc_html__('Cross-Product Support Graph', 'sustainable-catalyst-feature-suggestions') . '</h1>';
        echo '<p>' . esc_html__('Review product capabilities, support coverage, dependency edges, and governed handoff routes across the Sustainable Catalyst platform.', 'sustainable-catalyst-feature-suggestions') . '</p>';
        echo '<div class="scfs-support-graph__summary"><div><strong>' . esc_html($snapshot['product_count']) . '</strong><span>' . esc_html__('Products', 'sustainable-catalyst-feature-suggestions') . '</span></div><div><strong>' . esc_html($snapshot['edge_count']) . '</strong><span>' . esc_html__('Graph edges', 'sustainable-catalyst-feature-suggestions') . '</span></div><div><strong>' . esc_html($snapshot['average_coverage_score']) . '</strong><span>' . esc_html__('Average coverage', 'sustainable-catalyst-feature-suggestions') . '</span></div><div><strong>' . esc_html($snapshot['integrity']['warning_count']) . '</strong><span>' . esc_html__('Warnings', 'sustainable-catalyst-feature-suggestions') . '</span></div></div>';
        echo '<p><a class="button button-primary" href="' . esc_url(wp_nonce_url(admin_url('admin-post.php?action=scfs_support_graph_refresh'), self::NONCE_ACTION)) . '">' . esc_html__('Refresh graph', 'sustainable-catalyst-feature-suggestions') . '</a> <a class="button" href="' . esc_url(wp_nonce_url(admin_url('admin-post.php?action=scfs_support_graph_export'), self::NONCE_ACTION)) . '">' . esc_html__('Export JSON', 'sustainable-catalyst-feature-suggestions') . '</a></p>';
        echo '<table class="widefat striped"><thead><tr><th>' . esc_html__('Product', 'sustainable-catalyst-feature-suggestions') . '</th><th>' . esc_html__('Capabilities', 'sustainable-catalyst-feature-suggestions') . '</th><th>' . esc_html__('Articles', 'sustainable-catalyst-feature-suggestions') . '</th><th>' . esc_html__('Issues', 'sustainable-catalyst-feature-suggestions') . '</th><th>' . esc_html__('Releases', 'sustainable-catalyst-feature-suggestions') . '</th><th>' . esc_html__('Coverage', 'sustainable-catalyst-feature-suggestions') . '</th></tr></thead><tbody>';
        foreach ((array) $snapshot['nodes'] as $node) {
            echo '<tr><td><strong>' . esc_html($node['name']) . '</strong><br><code>' . esc_html($node['slug']) . '</code></td><td>' . esc_html(implode(', ', $node['capabilities'])) . '</td><td>' . esc_html($node['counts']['articles']) . '</td><td>' . esc_html($node['counts']['known_issues']) . '</td><td>' . esc_html($node['counts']['releases']) . '</td><td><span class="scfs-support-graph__state state-' . esc_attr($node['coverage_state']) . '">' . esc_html($node['coverage_score'] . ' · ' . ucfirst($node['coverage_state'])) . '</span></td></tr>';
        }
        echo '</tbody></table>';
        if (!empty($snapshot['integrity']['issues'])) {
            echo '<h2>' . esc_html__('Integrity findings', 'sustainable-catalyst-feature-suggestions') . '</h2><ul class="scfs-support-graph__findings">';
            foreach ($snapshot['integrity']['issues'] as $issue) {
                echo '<li class="severity-' . esc_attr($issue['severity']) . '"><strong>' . esc_html(strtoupper($issue['severity'])) . '</strong> ' . esc_html($issue['message']) . '</li>';
            }
            echo '</ul>';
        }
        echo '</div>';
    }

    public function refresh_action() {
        if (!$this->can_manage()) {
            wp_die(__('Forbidden', 'sustainable-catalyst-feature-suggestions'));
        }
        check_admin_referer(self::NONCE_ACTION);
        $this->build_snapshot(true);
        wp_safe_redirect(admin_url('edit.php?post_type=' . Sustainable_Catalyst_Feature_Suggestions::POST_TYPE . '&page=' . self::ADMIN_PAGE . '&refreshed=1'));
        exit;
    }

    public function export_action() {
        if (!$this->can_manage()) {
            wp_die(__('Forbidden', 'sustainable-catalyst-feature-suggestions'));
        }
        check_admin_referer(self::NONCE_ACTION);
        nocache_headers();
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="scfs-support-graph-' . gmdate('Y-m-d') . '.json"');
        echo wp_json_encode($this->graph_record(true), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public function render_shortcode($atts = array()) {
        $atts = shortcode_atts(array(
            'product' => '',
            'intent' => '',
            'component' => '',
            'show_handoffs' => '1',
            'title' => __('Connected product support', 'sustainable-catalyst-feature-suggestions'),
        ), $atts, self::SHORTCODE);
        wp_enqueue_style('scfs-cross-product-support-graph');
        wp_enqueue_script('scfs-cross-product-support-graph');
        $product = sanitize_title($atts['product'] ?: ($_GET['scfs_graph_product'] ?? ''));
        $intent = sanitize_text_field($atts['intent'] ?: ($_GET['scfs_graph_intent'] ?? ''));
        $component = sanitize_text_field($atts['component'] ?: ($_GET['scfs_graph_component'] ?? ''));
        $registry = $this->product_registry();
        ob_start();
        echo '<section class="scfs-support-graph" data-scfs-support-graph>';
        echo '<header class="scfs-support-graph__header"><p class="scfs-support-platform__kicker">' . esc_html__('Platform support graph', 'sustainable-catalyst-feature-suggestions') . '</p><h2>' . esc_html($atts['title']) . '</h2><p>' . esc_html__('Move between product guidance without losing product, version, component, or issue context.', 'sustainable-catalyst-feature-suggestions') . '</p></header>';
        echo '<form class="scfs-support-graph__form" method="get"><label><span>' . esc_html__('Starting product', 'sustainable-catalyst-feature-suggestions') . '</span><select name="scfs_graph_product"><option value="">' . esc_html__('Choose a product', 'sustainable-catalyst-feature-suggestions') . '</option>';
        foreach ($registry as $slug => $node) {
            echo '<option value="' . esc_attr($slug) . '" ' . selected($product, $slug, false) . '>' . esc_html($node['name']) . '</option>';
        }
        echo '</select></label><label><span>' . esc_html__('Task, symptom, or capability', 'sustainable-catalyst-feature-suggestions') . '</span><input type="search" name="scfs_graph_intent" value="' . esc_attr($intent) . '" placeholder="' . esc_attr__('Example: compare scenarios or troubleshoot an export', 'sustainable-catalyst-feature-suggestions') . '"></label><label><span>' . esc_html__('Component', 'sustainable-catalyst-feature-suggestions') . '</span><input type="text" name="scfs_graph_component" value="' . esc_attr($component) . '"></label><button type="submit">' . esc_html__('Find support path', 'sustainable-catalyst-feature-suggestions') . '</button></form>';
        if ($product !== '' && isset($registry[$product])) {
            $node = $this->product_node_record($product, true);
            echo '<article class="scfs-support-graph__product"><div><p class="scfs-support-graph__eyebrow">' . esc_html__('Current product', 'sustainable-catalyst-feature-suggestions') . '</p><h3>' . esc_html($node['name']) . '</h3><p>' . esc_html(implode(' · ', $node['capabilities'])) . '</p></div><div class="scfs-support-graph__metrics"><span><strong>' . esc_html($node['counts']['articles']) . '</strong>' . esc_html__('Articles', 'sustainable-catalyst-feature-suggestions') . '</span><span><strong>' . esc_html($node['counts']['known_issues']) . '</strong>' . esc_html__('Issues', 'sustainable-catalyst-feature-suggestions') . '</span><span><strong>' . esc_html($node['counts']['releases']) . '</strong>' . esc_html__('Releases', 'sustainable-catalyst-feature-suggestions') . '</span></div></article>';
            if (!empty($atts['show_handoffs'])) {
                $plan = $this->handoff_plan($product, $intent, $component, '');
                echo '<div class="scfs-support-graph__handoffs"><h3>' . esc_html__('Recommended platform handoffs', 'sustainable-catalyst-feature-suggestions') . '</h3>';
                if (empty($plan['recommendations'])) {
                    echo '<p class="scfs-support-graph__empty">' . esc_html__('No strong cross-product route was found. Continue with this product’s Support Articles or use private support when public guidance is insufficient.', 'sustainable-catalyst-feature-suggestions') . '</p>';
                }
                foreach ($plan['recommendations'] as $recommendation) {
                    echo '<article class="scfs-support-graph__handoff"><div><p class="scfs-support-graph__eyebrow">' . esc_html__('Related product', 'sustainable-catalyst-feature-suggestions') . '</p><h4>' . esc_html($recommendation['name']) . '</h4><p>' . esc_html(implode(' ', $recommendation['reasons'])) . '</p></div><div><span class="scfs-support-graph__score">' . esc_html($recommendation['score']) . '</span><a href="' . esc_url($recommendation['support_route']) . '">' . esc_html__('Open related support', 'sustainable-catalyst-feature-suggestions') . '</a></div></article>';
                }
                echo '</div>';
            }
        } else {
            echo '<div class="scfs-support-graph__catalog">';
            foreach ($registry as $node) {
                echo '<article><h3>' . esc_html($node['name']) . '</h3><p>' . esc_html(implode(' · ', array_slice($node['capabilities'], 0, 4))) . '</p><a href="' . esc_url(add_query_arg('scfs_graph_product', $node['slug'])) . '">' . esc_html__('Explore support connections', 'sustainable-catalyst-feature-suggestions') . '</a></article>';
            }
            echo '</div>';
        }
        echo '<p class="scfs-support-graph__privacy">' . esc_html__('The graph uses public support records and product contracts only. Private inquiries, requester identity, correspondence, and uploaded documents remain outside this system.', 'sustainable-catalyst-feature-suggestions') . '</p>';
        echo '</section>';
        return ob_get_clean();
    }

    public function register_rest_routes() {
        $namespace = Sustainable_Catalyst_Feature_Suggestions::REST_NAMESPACE;
        register_rest_route($namespace, '/support-graph/schema', array('methods' => 'GET', 'callback' => array($this, 'rest_schema'), 'permission_callback' => '__return_true'));
        register_rest_route($namespace, '/support-graph/overview', array('methods' => 'GET', 'callback' => array($this, 'rest_overview'), 'permission_callback' => '__return_true'));
        register_rest_route($namespace, '/support-graph/products', array('methods' => 'GET', 'callback' => array($this, 'rest_products'), 'permission_callback' => '__return_true'));
        register_rest_route($namespace, '/support-graph/product/(?P<product>[a-z0-9-]+)', array('methods' => 'GET', 'callback' => array($this, 'rest_product'), 'permission_callback' => '__return_true'));
        register_rest_route($namespace, '/support-graph/handoffs', array('methods' => 'POST', 'callback' => array($this, 'rest_handoffs'), 'permission_callback' => '__return_true'));
        register_rest_route($namespace, '/support-graph/path', array('methods' => 'GET', 'callback' => array($this, 'rest_path'), 'permission_callback' => '__return_true'));
        register_rest_route($namespace, '/support-graph/integrity', array('methods' => 'GET', 'callback' => array($this, 'rest_integrity'), 'permission_callback' => array($this, 'rest_manage_permission')));
        register_rest_route($namespace, '/support-graph/refresh', array('methods' => 'POST', 'callback' => array($this, 'rest_refresh'), 'permission_callback' => array($this, 'rest_manage_permission')));
    }

    public function rest_manage_permission() {
        return $this->can_manage();
    }

    public function rest_schema() {
        return rest_ensure_response($this->schema_record());
    }

    public function rest_overview() {
        return rest_ensure_response($this->build_snapshot(false));
    }

    public function rest_products() {
        return rest_ensure_response(array('ok' => true, 'version' => self::VERSION, 'items' => array_values($this->product_registry())));
    }

    public function rest_product($request) {
        $record = $this->product_node_record(sanitize_title($request['product']), true);
        if (!$record) {
            return new WP_Error('scfs_support_graph_product_not_found', __('Product not found.', 'sustainable-catalyst-feature-suggestions'), array('status' => 404));
        }
        return rest_ensure_response($record);
    }

    public function rest_handoffs($request) {
        $params = (array) $request->get_json_params();
        return rest_ensure_response($this->handoff_plan(
            sanitize_title($params['product'] ?? ''),
            sanitize_text_field($params['intent'] ?? ''),
            sanitize_text_field($params['component'] ?? ''),
            sanitize_text_field($params['version'] ?? '')
        ));
    }

    public function rest_path($request) {
        $source = sanitize_title($request->get_param('source'));
        $target = sanitize_title($request->get_param('target'));
        return rest_ensure_response(array(
            'schema' => 'scfs-cross-product-support-path/1.0',
            'version' => self::VERSION,
            'source' => $source,
            'target' => $target,
            'path' => $this->shortest_path($source, $target),
        ));
    }

    public function rest_integrity() {
        return rest_ensure_response($this->integrity_report());
    }

    public function rest_refresh() {
        return rest_ensure_response($this->build_snapshot(true));
    }

    public function cli_refresh() {
        WP_CLI::success(wp_json_encode($this->build_snapshot(true), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    public function cli_product($args) {
        $slug = sanitize_title($args[0] ?? '');
        $record = $this->product_node_record($slug, true);
        if (!$record) {
            WP_CLI::error('Product not found.');
        }
        WP_CLI::line(wp_json_encode($record, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    public function cli_handoff($args, $assoc_args) {
        $product = sanitize_title($args[0] ?? '');
        WP_CLI::line(wp_json_encode($this->handoff_plan($product, $assoc_args['intent'] ?? '', $assoc_args['component'] ?? '', $assoc_args['version'] ?? ''), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    public function cli_integrity() {
        $report = $this->integrity_report();
        WP_CLI::line(wp_json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        if (!$report['valid']) {
            WP_CLI::halt(1);
        }
    }
}
