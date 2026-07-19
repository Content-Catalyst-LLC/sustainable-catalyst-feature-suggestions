<?php
/**
 * Public Support API, Embeds, and Institutional Integration.
 *
 * Exposes privacy-safe public support contracts, product-scoped embeds,
 * version verification, and governed institutional integration profiles.
 *
 * @package Sustainable_Catalyst_Feature_Suggestions
 */

if (!defined('ABSPATH')) {
    exit;
}

final class SCFS_Public_Support_Integrations {
    const VERSION = '5.9.0';
    const SCHEMA = 'scfs-public-support-integration/1.0';
    const EMBED_SCHEMA = 'scfs-support-embed/1.0';
    const INSTITUTION_SCHEMA = 'scfs-institutional-support-integration/1.0';
    const ADMIN_PAGE = 'scfs-public-support-integrations';
    const OPTION_KEY = 'scfs_public_support_integration_settings';
    const PROFILE_OPTION = 'scfs_institutional_support_profiles';
    const SNAPSHOT_OPTION = 'scfs_public_support_integration_snapshot';
    const SHORTCODE = 'scfs_support_embed';
    const LEGACY_SHORTCODE = 'scfs_product_support_embed';
    const NONCE_ACTION = 'scfs_public_support_integrations';

    private static $instance = null;

    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('init', array($this, 'register_shortcodes'), 36);
        add_action('wp_enqueue_scripts', array($this, 'register_assets'));
        add_action('admin_enqueue_scripts', array($this, 'admin_assets'));
        add_action('admin_menu', array($this, 'register_admin_page'), 36);
        add_action('rest_api_init', array($this, 'register_rest_routes'));
        add_action('admin_post_scfs_public_support_export_contract', array($this, 'export_contract_action'));
        add_action('admin_post_scfs_public_support_refresh', array($this, 'refresh_action'));
        add_filter('scfs_support_navigation_items', array($this, 'support_navigation_item'), 30, 4);

        if (defined('WP_CLI') && WP_CLI && class_exists('WP_CLI')) {
            WP_CLI::add_command('scfs public-support contract', array($this, 'cli_contract'));
            WP_CLI::add_command('scfs public-support verify-version', array($this, 'cli_verify_version'));
            WP_CLI::add_command('scfs public-support embed', array($this, 'cli_embed'));
            WP_CLI::add_command('scfs public-support health', array($this, 'cli_health'));
        }
    }

    public static function activate() {
        $instance = self::instance();
        if (!get_option(self::OPTION_KEY)) {
            add_option(self::OPTION_KEY, $instance->default_settings(), '', false);
        }
        if (!get_option(self::PROFILE_OPTION)) {
            add_option(self::PROFILE_OPTION, array(), '', false);
        }
        $instance->build_snapshot(true);
    }

    public static function deactivate() {
        delete_transient('scfs_public_support_integration_health');
    }

    public function default_settings() {
        return array(
            'public_api_enabled' => '1',
            'public_embeds_enabled' => '1',
            'institutional_contracts_enabled' => '1',
            'require_public_key' => '0',
            'public_key_hash' => '',
            'allowed_origins' => '',
            'default_result_limit' => 6,
            'maximum_result_limit' => 20,
            'cache_seconds' => 300,
            'requests_per_minute' => 120,
        );
    }

    public function settings() {
        return wp_parse_args((array) get_option(self::OPTION_KEY, array()), $this->default_settings());
    }

    public function manage_capability() {
        return apply_filters('scfs_public_support_integration_capability', 'manage_options');
    }

    public function can_manage() {
        return current_user_can($this->manage_capability());
    }

    public function schema_record() {
        return array(
            'schema' => self::SCHEMA,
            'embed_schema' => self::EMBED_SCHEMA,
            'institution_schema' => self::INSTITUTION_SCHEMA,
            'version' => self::VERSION,
            'rest_namespace' => Sustainable_Catalyst_Feature_Suggestions::REST_NAMESPACE,
            'capabilities' => array(
                'public_product_catalog',
                'product_support_contracts',
                'version_verification',
                'support_article_discovery',
                'known_issue_context',
                'release_context',
                'product_scoped_embeds',
                'responsive_embed_cards',
                'institutional_integration_profiles',
                'origin_allowlists',
                'optional_public_api_keys',
                'request_rate_governance',
                'cache_and_etag_metadata',
                'cross_platform_support_handoffs',
                'json_contract_export',
            ),
            'shortcodes' => array(self::SHORTCODE, self::LEGACY_SHORTCODE),
            'public_routes' => array(
                '/public-support/schema',
                '/public-support/products',
                '/public-support/product/{product}',
                '/public-support/search',
                '/public-support/version/verify',
                '/public-support/embed',
            ),
            'institutional_routes' => array(
                '/institutional-support/contracts',
                '/institutional-support/contract/{key}',
                '/institutional-support/health',
            ),
            'privacy' => array(
                'public_records_only' => true,
                'personal_identifiers_exposed' => false,
                'raw_private_search_text_exposed' => false,
                'private_case_content_exposed' => false,
                'private_documents_exposed' => false,
                'contact_records_exposed' => false,
            ),
            'governance' => array(
                'read_only_public_api' => true,
                'optional_api_key_enforcement' => true,
                'origin_allowlist_supported' => true,
                'rate_governance_supported' => true,
                'human_review_required' => true,
                'automatic_private_case_creation' => false,
                'automatic_publication' => false,
                'automatic_issue_resolution' => false,
                'automatic_release_change' => false,
            ),
        );
    }

    public function integration_contract($key = 'public-support') {
        $key = sanitize_key($key);
        $contracts = array(
            'public-support' => array(
                'schema' => self::SCHEMA,
                'name' => 'Public Support API',
                'transport' => 'WordPress REST JSON',
                'namespace' => Sustainable_Catalyst_Feature_Suggestions::REST_NAMESPACE,
                'authentication' => 'Public read-only; optional site-managed API key',
                'data_classification' => 'Public support records only',
                'stability' => 'Versioned contract',
            ),
            'support-embed' => array(
                'schema' => self::EMBED_SCHEMA,
                'name' => 'Product Support Embed',
                'transport' => 'Shortcode or JSON embed plan',
                'authentication' => 'Public read-only',
                'data_classification' => 'Public product support context',
                'stability' => 'Versioned contract',
            ),
            'institutional-support' => array(
                'schema' => self::INSTITUTION_SCHEMA,
                'name' => 'Institutional Support Integration',
                'transport' => 'Administrator-governed REST contract',
                'authentication' => 'WordPress capability or approved integration profile',
                'data_classification' => 'Public support metadata; no private case content',
                'stability' => 'Versioned contract',
            ),
        );
        return isset($contracts[$key]) ? $contracts[$key] : array();
    }

    public function register_shortcodes() {
        add_shortcode(self::SHORTCODE, array($this, 'render_embed_shortcode'));
        add_shortcode(self::LEGACY_SHORTCODE, array($this, 'render_embed_shortcode'));
    }

    public function register_assets() {
        $css = dirname(__DIR__) . '/assets/public-support-integrations.css';
        $js = dirname(__DIR__) . '/assets/public-support-integrations.js';
        wp_register_style('scfs-public-support-integrations', plugins_url('../assets/public-support-integrations.css', __FILE__), array(), is_readable($css) ? (string) filemtime($css) : self::VERSION);
        wp_register_script('scfs-public-support-integrations', plugins_url('../assets/public-support-integrations.js', __FILE__), array(), is_readable($js) ? (string) filemtime($js) : self::VERSION, true);
    }

    public function admin_assets($hook) {
        if (strpos((string) $hook, self::ADMIN_PAGE) === false) {
            return;
        }
        $this->register_assets();
        wp_enqueue_style('scfs-public-support-integrations');
        wp_enqueue_script('scfs-public-support-integrations');
    }

    public function support_navigation_item($items, $settings, $product, $respect_content) {
        unset($settings, $product, $respect_content);
        $items['public-integrations'] = array(
            'label' => __('Support integrations', 'sustainable-catalyst-feature-suggestions'),
            'description' => __('Public APIs, embeds, version verification, and institutional contracts', 'sustainable-catalyst-feature-suggestions'),
        );
        return $items;
    }

    private function graph() {
        return class_exists('SCFS_Cross_Product_Support_Graph') ? SCFS_Cross_Product_Support_Graph::instance() : null;
    }

    public function product_catalog() {
        $graph = $this->graph();
        if (!$graph) {
            return array();
        }
        $items = array();
        foreach ((array) $graph->product_registry() as $slug => $node) {
            $items[] = array(
                'slug' => sanitize_title($slug),
                'name' => sanitize_text_field($node['name'] ?? $slug),
                'public_route' => esc_url_raw($node['public_route'] ?? ''),
                'support_route' => esc_url_raw($node['support_route'] ?? add_query_arg('scfs_support_product', $slug, home_url('/support/'))),
                'capabilities' => array_values(array_map('sanitize_text_field', (array) ($node['capabilities'] ?? array()))),
                'coverage_state' => sanitize_key($node['coverage_state'] ?? ''),
            );
        }
        return $items;
    }

    public function product_contract($product, $include_records = true, $limit = 6) {
        $product = sanitize_title($product);
        $graph = $this->graph();
        if (!$graph || !$product) {
            return array();
        }
        $node = $graph->product_node_record($product, (bool) $include_records);
        if (!$node) {
            return array();
        }
        $limit = max(1, min(absint($this->settings()['maximum_result_limit']), absint($limit)));
        foreach (array('support_articles', 'known_issues', 'releases') as $key) {
            if (isset($node[$key])) {
                $node[$key] = array_slice((array) $node[$key], 0, $limit);
            }
        }
        return array(
            'schema' => self::SCHEMA,
            'version' => self::VERSION,
            'generated_at' => current_time('c', true),
            'product' => $node,
            'links' => array(
                'support_center' => add_query_arg('scfs_support_product', $product, home_url('/support/')),
                'public_product' => $node['public_route'] ?? '',
                'api' => rest_url(Sustainable_Catalyst_Feature_Suggestions::REST_NAMESPACE . '/public-support/product/' . $product),
            ),
            'privacy' => $this->schema_record()['privacy'],
        );
    }

    private function public_query($product = '', $version = '', $component = '', $search = '', $limit = 6) {
        if (!class_exists('WP_Query')) {
            return array();
        }
        $limit = max(1, min(absint($this->settings()['maximum_result_limit']), absint($limit)));
        $tax_query = array('relation' => 'AND');
        $map = array(
            SCFS_Product_Integration::PRODUCT_TAXONOMY => sanitize_title($product),
            SCFS_Product_Integration::VERSION_TAXONOMY => sanitize_title($version),
            SCFS_Product_Integration::COMPONENT_TAXONOMY => sanitize_title($component),
        );
        foreach ($map as $taxonomy => $term) {
            if ($term && taxonomy_exists($taxonomy)) {
                $tax_query[] = array('taxonomy' => $taxonomy, 'field' => 'slug', 'terms' => $term);
            }
        }
        $args = array(
            'post_type' => array('sc_support_article', 'sc_known_issue', 'sc_release_record'),
            'post_status' => 'publish',
            'posts_per_page' => $limit,
            'orderby' => 'modified',
            'order' => 'DESC',
            's' => sanitize_text_field($search),
        );
        if (count($tax_query) > 1) {
            $args['tax_query'] = $tax_query;
        }
        $query = new WP_Query($args);
        $items = array();
        foreach ((array) $query->posts as $post) {
            $items[] = array(
                'id' => absint($post->ID),
                'type' => sanitize_key($post->post_type),
                'title' => sanitize_text_field(get_the_title($post)),
                'summary' => sanitize_textarea_field(wp_trim_words(get_the_excerpt($post) ?: wp_strip_all_tags($post->post_content), 34)),
                'url' => esc_url_raw(get_permalink($post)),
                'modified' => get_post_modified_time('c', true, $post),
            );
        }
        return $items;
    }

    public function verify_version($product, $version) {
        $product = sanitize_title($product);
        $version = sanitize_text_field($version);
        $normalized = ltrim(strtolower($version), 'v');
        $contract = $this->product_contract($product, true, 20);
        $matched = array();
        foreach ((array) ($contract['product']['releases'] ?? array()) as $release) {
            $haystack = strtolower(($release['title'] ?? '') . ' ' . ($release['url'] ?? ''));
            if ($normalized !== '' && (strpos($haystack, $normalized) !== false || strpos($haystack, 'v' . $normalized) !== false)) {
                $matched[] = $release;
            }
        }
        $known_product = !empty($contract);
        $state = !$known_product ? 'unknown-product' : ($version === '' ? 'version-required' : ($matched ? 'verified' : 'not-found'));
        return array(
            'schema' => 'scfs-support-version-verification/1.0',
            'version' => self::VERSION,
            'product' => $product,
            'requested_version' => $version,
            'normalized_version' => $normalized,
            'state' => $state,
            'verified' => $state === 'verified',
            'matched_releases' => $matched,
            'support_center_url' => add_query_arg(array('scfs_support_product' => $product, 'scfs_support_version' => sanitize_title($version)), home_url('/support/')),
            'human_review_required' => $state !== 'verified',
        );
    }

    public function embed_plan($product, $version = '', $component = '', $view = 'compact', $limit = 6) {
        $product = sanitize_title($product);
        $version = sanitize_text_field($version);
        $component = sanitize_title($component);
        $view = in_array($view, array('compact', 'standard', 'articles', 'issues', 'releases'), true) ? $view : 'compact';
        $limit = max(1, min(absint($this->settings()['maximum_result_limit']), absint($limit)));
        $contract = $this->product_contract($product, true, $limit);
        $records = $this->public_query($product, $version, $component, '', $limit);
        if ($view === 'articles') {
            $records = array_values(array_filter($records, static function ($item) { return $item['type'] === 'sc_support_article'; }));
        } elseif ($view === 'issues') {
            $records = array_values(array_filter($records, static function ($item) { return $item['type'] === 'sc_known_issue'; }));
        } elseif ($view === 'releases') {
            $records = array_values(array_filter($records, static function ($item) { return $item['type'] === 'sc_release_record'; }));
        }
        return array(
            'schema' => self::EMBED_SCHEMA,
            'version' => self::VERSION,
            'product' => $product,
            'product_name' => $contract['product']['name'] ?? ucwords(str_replace('-', ' ', $product)),
            'requested_version' => $version,
            'component' => $component,
            'view' => $view,
            'records' => array_slice($records, 0, $limit),
            'version_verification' => $version !== '' ? $this->verify_version($product, $version) : null,
            'support_center_url' => add_query_arg(array_filter(array('scfs_support_product' => $product, 'scfs_support_version' => sanitize_title($version), 'scfs_support_component' => $component)), home_url('/support/')),
            'handoffs' => $this->graph() ? array_slice((array) ($this->graph()->handoff_plan($product, '', $component, $version)['recommendations'] ?? array()), 0, 3) : array(),
        );
    }

    public function render_embed_shortcode($atts = array()) {
        if (empty($this->settings()['public_embeds_enabled'])) {
            return '';
        }
        $atts = shortcode_atts(array('product' => '', 'version' => '', 'component' => '', 'view' => 'compact', 'limit' => $this->settings()['default_result_limit'], 'heading' => 'Product support'), $atts, self::SHORTCODE);
        $plan = $this->embed_plan($atts['product'], $atts['version'], $atts['component'], $atts['view'], $atts['limit']);
        if (empty($plan['product'])) {
            return '<p class="scfs-support-embed__notice">' . esc_html__('Choose a product to render this support embed.', 'sustainable-catalyst-feature-suggestions') . '</p>';
        }
        $this->register_assets();
        wp_enqueue_style('scfs-public-support-integrations');
        wp_enqueue_script('scfs-public-support-integrations');
        ob_start();
        echo '<section class="scfs-support-embed" data-scfs-support-embed data-product="' . esc_attr($plan['product']) . '">';
        echo '<header class="scfs-support-embed__header"><p class="scfs-support-embed__eyebrow">' . esc_html__('Sustainable Catalyst Support', 'sustainable-catalyst-feature-suggestions') . '</p><h2>' . esc_html($atts['heading']) . '</h2><p>' . esc_html($plan['product_name']) . ($plan['requested_version'] ? ' · ' . esc_html($plan['requested_version']) : '') . '</p></header>';
        if ($plan['version_verification']) {
            echo '<div class="scfs-support-embed__verification" data-state="' . esc_attr($plan['version_verification']['state']) . '"><strong>' . esc_html__('Version check:', 'sustainable-catalyst-feature-suggestions') . '</strong> ' . esc_html(ucwords(str_replace('-', ' ', $plan['version_verification']['state']))) . '</div>';
        }
        echo '<div class="scfs-support-embed__records">';
        if (!$plan['records']) {
            echo '<p class="scfs-support-embed__empty">' . esc_html__('No matching public support records are available yet.', 'sustainable-catalyst-feature-suggestions') . '</p>';
        }
        foreach ($plan['records'] as $record) {
            echo '<article class="scfs-support-embed__record"><p class="scfs-support-embed__type">' . esc_html(ucwords(str_replace(array('sc_', '_'), array('', ' '), $record['type']))) . '</p><h3><a href="' . esc_url($record['url']) . '">' . esc_html($record['title']) . '</a></h3><p>' . esc_html($record['summary']) . '</p></article>';
        }
        echo '</div>';
        echo '<footer class="scfs-support-embed__footer"><a href="' . esc_url($plan['support_center_url']) . '">' . esc_html__('Open the full Support Center →', 'sustainable-catalyst-feature-suggestions') . '</a></footer>';
        echo '</section>';
        return ob_get_clean();
    }

    public function access_governance_record() {
        $settings = $this->settings();
        return array(
            'schema' => 'scfs-public-support-access-governance/1.0',
            'public_api_enabled' => !empty($settings['public_api_enabled']),
            'embeds_enabled' => !empty($settings['public_embeds_enabled']),
            'optional_public_key_required' => !empty($settings['require_public_key']),
            'origin_allowlist' => array_values(array_filter(array_map('trim', preg_split('/[\r\n,]+/', (string) $settings['allowed_origins'])))),
            'requests_per_minute' => absint($settings['requests_per_minute']),
            'cache_seconds' => absint($settings['cache_seconds']),
            'public_records_only' => true,
            'read_only' => true,
            'private_case_content_exposed' => false,
            'contact_records_exposed' => false,
        );
    }

    public function public_permission($request) {
        $settings = $this->settings();
        if (empty($settings['public_api_enabled'])) {
            return new WP_Error('scfs_public_support_disabled', __('The public support API is disabled.', 'sustainable-catalyst-feature-suggestions'), array('status' => 503));
        }
        $origin = sanitize_text_field($request->get_header('origin'));
        $allowed = array_values(array_filter(array_map('trim', preg_split('/[\r\n,]+/', (string) $settings['allowed_origins']))));
        if ($origin && $allowed && !in_array($origin, $allowed, true)) {
            return new WP_Error('scfs_public_support_origin_denied', __('This origin is not allowed.', 'sustainable-catalyst-feature-suggestions'), array('status' => 403));
        }
        if (!empty($settings['require_public_key'])) {
            $provided = (string) $request->get_header('x-scfs-public-key');
            $expected = (string) $settings['public_key_hash'];
            if (!$provided || !$expected || !hash_equals($expected, hash('sha256', $provided))) {
                return new WP_Error('scfs_public_support_key_denied', __('A valid public support API key is required.', 'sustainable-catalyst-feature-suggestions'), array('status' => 401));
            }
        }
        return true;
    }

    public function institutional_permission() {
        return $this->can_manage();
    }

    private function response($data, $status = 200) {
        $response = new WP_REST_Response($data, $status);
        $payload = wp_json_encode($data, JSON_UNESCAPED_SLASHES);
        $response->header('Cache-Control', 'public, max-age=' . absint($this->settings()['cache_seconds']));
        $response->header('ETag', '"' . hash('sha256', (string) $payload) . '"');
        $response->header('X-SCFS-Contract-Version', self::VERSION);
        return $response;
    }

    public function register_rest_routes() {
        $namespace = Sustainable_Catalyst_Feature_Suggestions::REST_NAMESPACE;
        $public = array($this, 'public_permission');
        $admin = array($this, 'institutional_permission');
        register_rest_route($namespace, '/public-support/schema', array('methods' => 'GET', 'callback' => array($this, 'rest_schema'), 'permission_callback' => $public));
        register_rest_route($namespace, '/public-support/products', array('methods' => 'GET', 'callback' => array($this, 'rest_products'), 'permission_callback' => $public));
        register_rest_route($namespace, '/public-support/product/(?P<product>[a-z0-9-]+)', array('methods' => 'GET', 'callback' => array($this, 'rest_product'), 'permission_callback' => $public));
        register_rest_route($namespace, '/public-support/search', array('methods' => 'GET', 'callback' => array($this, 'rest_search'), 'permission_callback' => $public));
        register_rest_route($namespace, '/public-support/version/verify', array('methods' => array('GET', 'POST'), 'callback' => array($this, 'rest_verify_version'), 'permission_callback' => $public));
        register_rest_route($namespace, '/public-support/embed', array('methods' => 'GET', 'callback' => array($this, 'rest_embed'), 'permission_callback' => $public));
        register_rest_route($namespace, '/institutional-support/contracts', array('methods' => 'GET', 'callback' => array($this, 'rest_contracts'), 'permission_callback' => $admin));
        register_rest_route($namespace, '/institutional-support/contract/(?P<key>[a-z0-9-]+)', array('methods' => 'GET', 'callback' => array($this, 'rest_contract'), 'permission_callback' => $admin));
        register_rest_route($namespace, '/institutional-support/health', array('methods' => 'GET', 'callback' => array($this, 'rest_health'), 'permission_callback' => $admin));
    }

    public function rest_schema() { return $this->response($this->schema_record()); }
    public function rest_products() { return $this->response(array('schema' => self::SCHEMA, 'version' => self::VERSION, 'items' => $this->product_catalog())); }
    public function rest_product($request) {
        $record = $this->product_contract($request['product'], true, $request->get_param('limit') ?: $this->settings()['default_result_limit']);
        return $record ? $this->response($record) : new WP_Error('scfs_public_support_product_not_found', __('Product not found.', 'sustainable-catalyst-feature-suggestions'), array('status' => 404));
    }
    public function rest_search($request) {
        return $this->response(array('schema' => 'scfs-public-support-search/1.0', 'version' => self::VERSION, 'items' => $this->public_query($request->get_param('product'), $request->get_param('version'), $request->get_param('component'), $request->get_param('q'), $request->get_param('limit') ?: $this->settings()['default_result_limit'])));
    }
    public function rest_verify_version($request) {
        $params = $request->get_method() === 'POST' ? (array) $request->get_json_params() : $request->get_params();
        return $this->response($this->verify_version($params['product'] ?? '', $params['version'] ?? ''));
    }
    public function rest_embed($request) {
        return $this->response($this->embed_plan($request->get_param('product'), $request->get_param('version'), $request->get_param('component'), $request->get_param('view') ?: 'compact', $request->get_param('limit') ?: $this->settings()['default_result_limit']));
    }
    public function rest_contracts() {
        return $this->response(array('schema' => self::INSTITUTION_SCHEMA, 'version' => self::VERSION, 'contracts' => array($this->integration_contract('public-support'), $this->integration_contract('support-embed'), $this->integration_contract('institutional-support')), 'access_governance' => $this->access_governance_record()));
    }
    public function rest_contract($request) {
        $contract = $this->integration_contract($request['key']);
        return $contract ? $this->response($contract) : new WP_Error('scfs_institutional_contract_not_found', __('Integration contract not found.', 'sustainable-catalyst-feature-suggestions'), array('status' => 404));
    }
    public function rest_health() { return $this->response($this->health_record()); }

    public function health_record() {
        $catalog = $this->product_catalog();
        $contracts = array_filter(array($this->integration_contract('public-support'), $this->integration_contract('support-embed'), $this->integration_contract('institutional-support')));
        return array(
            'schema' => 'scfs-institutional-support-health/1.0',
            'version' => self::VERSION,
            'ok' => count($contracts) === 3,
            'product_count' => count($catalog),
            'contract_count' => count($contracts),
            'public_api_enabled' => !empty($this->settings()['public_api_enabled']),
            'embeds_enabled' => !empty($this->settings()['public_embeds_enabled']),
            'access_governance' => $this->access_governance_record(),
            'checked_at' => current_time('c', true),
        );
    }

    public function build_snapshot($force = false) {
        if (!$force) {
            $stored = get_option(self::SNAPSHOT_OPTION, array());
            if ($stored) {
                return $stored;
            }
        }
        $snapshot = array(
            'schema' => self::SCHEMA,
            'version' => self::VERSION,
            'generated_at' => current_time('c', true),
            'products' => $this->product_catalog(),
            'contracts' => array($this->integration_contract('public-support'), $this->integration_contract('support-embed'), $this->integration_contract('institutional-support')),
            'health' => $this->health_record(),
        );
        update_option(self::SNAPSHOT_OPTION, $snapshot, false);
        return $snapshot;
    }

    public function register_admin_page() {
        add_submenu_page('edit.php?post_type=' . Sustainable_Catalyst_Feature_Suggestions::POST_TYPE, __('Public Support Integrations', 'sustainable-catalyst-feature-suggestions'), __('Public Integrations', 'sustainable-catalyst-feature-suggestions'), $this->manage_capability(), self::ADMIN_PAGE, array($this, 'render_admin_page'));
    }

    public function render_admin_page() {
        if (!$this->can_manage()) {
            wp_die(esc_html__('You do not have permission to manage public support integrations.', 'sustainable-catalyst-feature-suggestions'));
        }
        $health = $this->health_record();
        $schema = $this->schema_record();
        echo '<div class="wrap scfs-integration-admin" data-scfs-public-integrations><h1>' . esc_html__('Public APIs, Embeds, and Institutional Support', 'sustainable-catalyst-feature-suggestions') . '</h1>';
        echo '<p>' . esc_html__('Review versioned public support contracts, product embeds, version verification, access governance, and institutional integration health.', 'sustainable-catalyst-feature-suggestions') . '</p>';
        echo '<div class="scfs-integration-admin__metrics"><article><strong>' . esc_html($health['product_count']) . '</strong><span>' . esc_html__('Products', 'sustainable-catalyst-feature-suggestions') . '</span></article><article><strong>' . esc_html($health['contract_count']) . '</strong><span>' . esc_html__('Contracts', 'sustainable-catalyst-feature-suggestions') . '</span></article><article><strong>' . ($health['ok'] ? esc_html__('Healthy', 'sustainable-catalyst-feature-suggestions') : esc_html__('Review', 'sustainable-catalyst-feature-suggestions')) . '</strong><span>' . esc_html__('Integration state', 'sustainable-catalyst-feature-suggestions') . '</span></article></div>';
        echo '<h2>' . esc_html__('Public routes', 'sustainable-catalyst-feature-suggestions') . '</h2><ul class="scfs-integration-admin__routes">';
        foreach ($schema['public_routes'] as $route) echo '<li><code>' . esc_html('/wp-json/scfs/v1' . $route) . '</code></li>';
        echo '</ul><h2>' . esc_html__('Embed example', 'sustainable-catalyst-feature-suggestions') . '</h2><pre><code>[scfs_support_embed product="decision-studio" version="2.0.1" view="compact"]</code></pre>';
        echo '<p><a class="button button-primary" href="' . esc_url(wp_nonce_url(admin_url('admin-post.php?action=scfs_public_support_export_contract'), self::NONCE_ACTION)) . '">' . esc_html__('Export integration contracts', 'sustainable-catalyst-feature-suggestions') . '</a> <a class="button" href="' . esc_url(wp_nonce_url(admin_url('admin-post.php?action=scfs_public_support_refresh'), self::NONCE_ACTION)) . '">' . esc_html__('Refresh snapshot', 'sustainable-catalyst-feature-suggestions') . '</a></p></div>';
    }

    public function export_contract_action() {
        if (!$this->can_manage() || !check_admin_referer(self::NONCE_ACTION)) wp_die(esc_html__('Invalid request.', 'sustainable-catalyst-feature-suggestions'));
        nocache_headers();
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="scfs-public-support-integration-v5.9.0.json"');
        echo wp_json_encode($this->build_snapshot(true), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public function refresh_action() {
        if (!$this->can_manage() || !check_admin_referer(self::NONCE_ACTION)) wp_die(esc_html__('Invalid request.', 'sustainable-catalyst-feature-suggestions'));
        $this->build_snapshot(true);
        wp_safe_redirect(admin_url('edit.php?post_type=' . Sustainable_Catalyst_Feature_Suggestions::POST_TYPE . '&page=' . self::ADMIN_PAGE . '&scfs_refreshed=1'));
        exit;
    }

    public function cli_contract($args) {
        $key = sanitize_key($args[0] ?? 'public-support');
        WP_CLI::line(wp_json_encode($this->integration_contract($key), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
    public function cli_verify_version($args) {
        WP_CLI::line(wp_json_encode($this->verify_version($args[0] ?? '', $args[1] ?? ''), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
    public function cli_embed($args, $assoc_args) {
        WP_CLI::line(wp_json_encode($this->embed_plan($args[0] ?? '', $assoc_args['version'] ?? '', $assoc_args['component'] ?? '', $assoc_args['view'] ?? 'compact', $assoc_args['limit'] ?? 6), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
    public function cli_health() {
        WP_CLI::line(wp_json_encode($this->health_record(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}
