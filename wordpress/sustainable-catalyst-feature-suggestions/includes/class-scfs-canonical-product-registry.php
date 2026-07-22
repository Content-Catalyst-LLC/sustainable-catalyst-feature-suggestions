<?php
/**
 * Canonical Product Registry.
 *
 * Maintains the governed product catalog used by future release-board,
 * documentation, support, and product-intelligence surfaces. Product
 * classifications remain separate from the existing support taxonomies so
 * public product identity can evolve without disturbing case relationships.
 *
 * @package Sustainable_Catalyst_Feature_Suggestions
 */

if (!defined('ABSPATH')) {
    exit;
}

final class SCFS_Canonical_Product_Registry {
    const VERSION = '7.3.3';
    const SCHEMA = 'scfs-canonical-product-registry/1.1';
    const OPTION_KEY = 'scfs_canonical_product_registry';
    const SCHEMA_OPTION = 'scfs_canonical_product_registry_schema';
    const AUDIT_OPTION = 'scfs_canonical_product_registry_audit';
    const ADMIN_SLUG = 'scfs-product-registry';
    const NONCE_ACTION = 'scfs_save_product_registry';
    const MAX_AUDIT_ENTRIES = 250;

    private static $instance = null;

    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('init', array($this, 'maybe_upgrade'), 3);
        add_action('admin_menu', array($this, 'register_admin_page'), 21);
        add_action('admin_post_scfs_save_product_registry', array($this, 'save_registry_action'));
        add_action('admin_post_scfs_reset_product_registry', array($this, 'reset_registry_action'));
        add_action('admin_post_scfs_export_product_registry', array($this, 'export_registry_action'));
        add_action('rest_api_init', array($this, 'register_rest_routes'));

        if (defined('WP_CLI') && WP_CLI && class_exists('WP_CLI')) {
            WP_CLI::add_command('scfs products list', array($this, 'cli_list'));
            WP_CLI::add_command('scfs products export', array($this, 'cli_export'));
            WP_CLI::add_command('scfs products seed', array($this, 'cli_seed'));
        }
    }

    public static function activate() {
        $instance = self::instance();
        $existing = get_option(self::OPTION_KEY, array());
        $needs_upgrade = get_option(self::SCHEMA_OPTION) !== self::SCHEMA;
        $registry = (!is_array($existing) || !$existing)
            ? $instance->default_products()
            : $instance->merge_defaults($existing);
        if ($needs_upgrade) {
            $registry = $instance->apply_v731_presentation_migrations($registry);
        }
        if (!is_array($existing) || !$existing) {
            add_option(self::OPTION_KEY, $registry, '', false);
        } else {
            update_option(self::OPTION_KEY, $registry, false);
        }
        update_option(self::SCHEMA_OPTION, self::SCHEMA, false);
        if (!get_option(self::AUDIT_OPTION)) {
            add_option(self::AUDIT_OPTION, array(), '', false);
        }
    }

    public function maybe_upgrade() {
        if (get_option(self::SCHEMA_OPTION) === self::SCHEMA) {
            return;
        }
        $existing = get_option(self::OPTION_KEY, array());
        $registry = $this->merge_defaults(is_array($existing) ? $existing : array());
        $registry = $this->apply_v731_presentation_migrations($registry);
        update_option(self::OPTION_KEY, $registry, false);
        update_option(self::SCHEMA_OPTION, self::SCHEMA, false);
        $this->audit('registry_schema_upgraded', array(
            'schema' => self::SCHEMA,
            'knowledge_library_homepage_visible' => true,
            'analytics_r_public_label' => 'Analytics R',
        ));
        do_action('scfs_product_registry_updated', $registry);
    }

    public function capability() {
        return apply_filters('scfs_product_registry_capability', 'manage_options');
    }

    public function can_manage() {
        return current_user_can($this->capability());
    }

    public function families() {
        return array(
            'foundation' => __('Foundation', 'sustainable-catalyst-feature-suggestions'),
            'research-intelligence' => __('Research and Intelligence', 'sustainable-catalyst-feature-suggestions'),
            'data-analysis' => __('Data and Analysis', 'sustainable-catalyst-feature-suggestions'),
            'creation-systems' => __('Creation and Systems', 'sustainable-catalyst-feature-suggestions'),
            'commercial' => __('Commercial Release', 'sustainable-catalyst-feature-suggestions'),
        );
    }

    public function version_sources() {
        return array(
            'wordpress_plugin' => __('Installed WordPress plugin', 'sustainable-catalyst-feature-suggestions'),
            'manual' => __('Manual entry', 'sustainable-catalyst-feature-suggestions'),
            'remote_manifest' => __('Remote manifest (reserved)', 'sustainable-catalyst-feature-suggestions'),
            'service_endpoint' => __('Service endpoint (reserved)', 'sustainable-catalyst-feature-suggestions'),
            'package_manifest' => __('Package manifest (reserved)', 'sustainable-catalyst-feature-suggestions'),
        );
    }

    public function statuses() {
        return array(
            'current' => __('Current', 'sustainable-catalyst-feature-suggestions'),
            'stable' => __('Stable', 'sustainable-catalyst-feature-suggestions'),
            'development' => __('Development', 'sustainable-catalyst-feature-suggestions'),
            'preview' => __('Preview', 'sustainable-catalyst-feature-suggestions'),
            'private_beta' => __('Private beta', 'sustainable-catalyst-feature-suggestions'),
            'release_candidate' => __('Release candidate', 'sustainable-catalyst-feature-suggestions'),
            'maintenance' => __('Maintenance', 'sustainable-catalyst-feature-suggestions'),
            'update_available' => __('Update available', 'sustainable-catalyst-feature-suggestions'),
            'inactive' => __('Inactive', 'sustainable-catalyst-feature-suggestions'),
            'deprecated' => __('Deprecated', 'sustainable-catalyst-feature-suggestions'),
            'unavailable' => __('Unavailable', 'sustainable-catalyst-feature-suggestions'),
            'unverified' => __('Unverified', 'sustainable-catalyst-feature-suggestions'),
        );
    }

    public function channels() {
        return array(
            'stable' => __('Stable', 'sustainable-catalyst-feature-suggestions'),
            'development' => __('Development', 'sustainable-catalyst-feature-suggestions'),
            'preview' => __('Preview', 'sustainable-catalyst-feature-suggestions'),
            'private_beta' => __('Private beta', 'sustainable-catalyst-feature-suggestions'),
            'release_candidate' => __('Release candidate', 'sustainable-catalyst-feature-suggestions'),
            'maintenance' => __('Maintenance', 'sustainable-catalyst-feature-suggestions'),
        );
    }

    public function product_types() {
        return array(
            'wordpress_plugin' => __('WordPress plugin', 'sustainable-catalyst-feature-suggestions'),
            'python_application' => __('Python application', 'sustainable-catalyst-feature-suggestions'),
            'r_package' => __('R package', 'sustainable-catalyst-feature-suggestions'),
            'hosted_service' => __('Hosted service', 'sustainable-catalyst-feature-suggestions'),
            'platform_module' => __('Platform module', 'sustainable-catalyst-feature-suggestions'),
            'multi_runtime_platform' => __('Multi-runtime platform', 'sustainable-catalyst-feature-suggestions'),
        );
    }

    private function product($id, $name, $short_name, $family, $order, $overrides = array()) {
        return array_merge(array(
            'canonical_id' => $id,
            'name' => $name,
            'short_name' => $short_name,
            'legacy_names' => array(),
            'family' => $family,
            'product_type' => 'wordpress_plugin',
            'version_source' => 'wordpress_plugin',
            'plugin_file' => '',
            'plugin_slug' => '',
            'plugin_text_domain' => '',
            'legacy_plugin_files' => array(),
            'legacy_plugin_slugs' => array(),
            'legacy_text_domains' => array(),
            'discovery_enabled' => '1',
            'discovery_locked' => '',
            'discovery_state' => 'unscanned',
            'discovery_match' => '',
            'discovered_active' => '',
            'discovered_activation_scope' => 'inactive',
            'discovered_plugin_name' => '',
            'discovered_plugin_version' => '',
            'discovered_plugin_version_raw' => '',
            'discovered_version_state' => 'unscanned',
            'discovered_text_domain' => '',
            'last_discovered_at' => '',
            'installed_version' => '',
            'public_version' => '',
            'release_channel' => 'stable',
            'status' => 'unverified',
            'public_visible' => '1',
            'homepage_visible' => '1',
            'display_order' => $order,
            'product_url' => '',
            'documentation_url' => '',
            'support_url' => '/support/',
            'release_notes_url' => '',
            'owner' => 'Content Catalyst LLC',
            'commercial' => '',
            'public_interest' => '1',
            'manual_notes' => '',
            'last_verified_at' => '',
        ), $overrides);
    }

    public function default_products() {
        $plugin_file = 'sustainable-catalyst-feature-suggestions/sustainable-catalyst-feature-suggestions.php';
        return array(
            'sustainable-catalyst-core' => $this->product('sustainable-catalyst-core', 'Sustainable Catalyst Core', 'Core', 'foundation', 10, array('plugin_slug' => 'sustainable-catalyst-core')),
            'product-support-feedback' => $this->product('product-support-feedback', 'Sustainable Catalyst Product Support and Feedback Platform', 'Support and Feedback', 'foundation', 20, array(
                'legacy_names' => array('Feature Suggestions', 'Sustainable Catalyst Feature Suggestions'),
                'plugin_file' => $plugin_file,
                'plugin_slug' => 'sustainable-catalyst-feature-suggestions',
                'legacy_plugin_files' => array(
                    'sustainable-catalyst-product-support-feedback/sustainable-catalyst-product-support-feedback.php',
                    'sustainable-catalyst-product-support-feedback-platform/sustainable-catalyst-product-support-feedback-platform.php',
                ),
                'legacy_plugin_slugs' => array('sustainable-catalyst-product-support-feedback', 'sustainable-catalyst-product-support-feedback-platform'),
                'legacy_text_domains' => array('sustainable-catalyst-product-support-feedback', 'sustainable-catalyst-product-support-feedback-platform'),
                'installed_version' => self::VERSION,
                'public_version' => self::VERSION,
                'status' => 'current',
                'last_verified_at' => gmdate('c'),
            )),
            'contact-engagement' => $this->product('contact-engagement', 'Sustainable Catalyst Contact and Engagement Platform', 'Contact and Engagement', 'foundation', 30, array('plugin_slug' => 'sustainable-catalyst-contact-engagement')),
            'knowledge-library' => $this->product('knowledge-library', 'Sustainable Catalyst Knowledge Library', 'Knowledge Library', 'foundation', 40, array('plugin_slug' => 'sustainable-catalyst-library', 'product_url' => '/library/')),
            'research-librarian' => $this->product('research-librarian', 'Sustainable Catalyst Research Librarian', 'Research Librarian', 'research-intelligence', 110, array('plugin_slug' => 'sustainable-catalyst-research-librarian')),
            'site-intelligence' => $this->product('site-intelligence', 'Sustainable Catalyst Site Intelligence', 'Site Intelligence', 'research-intelligence', 120, array('plugin_slug' => 'sustainable-catalyst-site-intelligence')),
            'decision-studio' => $this->product('decision-studio', 'Sustainable Catalyst Decision Studio', 'Decision Studio', 'research-intelligence', 130, array('plugin_slug' => 'sustainable-catalyst-decision-studio')),
            'narrative-risk' => $this->product('narrative-risk', 'Catalyst Narrative Risk', 'Narrative Risk', 'research-intelligence', 140, array('plugin_slug' => 'catalyst-narrative-risk')),
            'catalyst-data' => $this->product('catalyst-data', 'Catalyst Data', 'Catalyst Data', 'data-analysis', 210, array('plugin_slug' => 'catalyst-data')),
            'catalyst-analytics-r' => $this->product('catalyst-analytics-r', 'Catalyst Analytics R', 'Analytics R', 'data-analysis', 220, array('product_type' => 'r_package', 'version_source' => 'manual', 'discovery_enabled' => '')),
            'catalyst-finance' => $this->product('catalyst-finance', 'Catalyst Finance', 'Catalyst Finance', 'data-analysis', 230, array('plugin_slug' => 'catalyst-finance')),
            'global-impact-catalyst' => $this->product('global-impact-catalyst', 'Global Impact Catalyst', 'Global Impact Catalyst', 'data-analysis', 240, array('plugin_slug' => 'global-impact-catalyst')),
            'catalyst-canvas' => $this->product('catalyst-canvas', 'Catalyst Canvas', 'Catalyst Canvas', 'creation-systems', 310, array('plugin_slug' => 'catalyst-canvas')),
            'catalyst-grit' => $this->product('catalyst-grit', 'Catalyst Grit', 'Catalyst Grit', 'creation-systems', 320, array('plugin_slug' => 'catalyst-grit')),
            'workbench' => $this->product('workbench', 'Sustainable Catalyst Workbench', 'Workbench', 'creation-systems', 330, array('plugin_slug' => 'sustainable-catalyst-workbench')),
            'sustainable-catalyst-lab' => $this->product('sustainable-catalyst-lab', 'Sustainable Catalyst Lab', 'Lab', 'creation-systems', 340, array('plugin_slug' => 'sustainable-catalyst-lab')),
            'catalyst-intelligence' => $this->product('catalyst-intelligence', 'Catalyst Intelligence Platform', 'Catalyst Intelligence', 'commercial', 410, array(
                'product_type' => 'multi_runtime_platform',
                'version_source' => 'manual',
                'discovery_enabled' => '',
                'installed_version' => '',
                'public_version' => '0.23.1',
                'release_channel' => 'development',
                'status' => 'development',
                'commercial' => '1',
                'public_interest' => '',
                'manual_notes' => 'Private commercial platform. Public version is maintained manually until a governed release manifest is available.',
                'last_verified_at' => gmdate('c'),
            )),
        );
    }

    public function merge_defaults($existing) {
        $merged = is_array($existing) ? $existing : array();
        foreach ($this->default_products() as $id => $record) {
            if (!isset($merged[$id]) || !is_array($merged[$id])) {
                $merged[$id] = $record;
                continue;
            }
            $merged[$id] = array_merge($record, $merged[$id]);
        }
        $merged['product-support-feedback']['installed_version'] = self::VERSION;
        $merged['product-support-feedback']['public_version'] = self::VERSION;
        $merged['product-support-feedback']['status'] = 'current';
        return $this->normalize_registry($merged);
    }

    private function apply_v731_presentation_migrations($records) {
        $records = is_array($records) ? $records : array();
        if (isset($records['knowledge-library']) && is_array($records['knowledge-library'])) {
            $records['knowledge-library']['public_visible'] = '1';
            $records['knowledge-library']['homepage_visible'] = '1';
            $records['knowledge-library']['short_name'] = 'Knowledge Library';
        }
        if (isset($records['catalyst-analytics-r']) && is_array($records['catalyst-analytics-r'])) {
            $legacy_names = isset($records['catalyst-analytics-r']['legacy_names'])
                ? (array) $records['catalyst-analytics-r']['legacy_names']
                : array();
            $legacy_names[] = 'Catalyst AnalyticsR';
            $records['catalyst-analytics-r']['legacy_names'] = array_values(array_unique($legacy_names));
            $records['catalyst-analytics-r']['name'] = 'Catalyst Analytics R';
            $records['catalyst-analytics-r']['short_name'] = 'Analytics R';
        }
        return $this->normalize_registry($records);
    }

    public function registry() {
        $saved = get_option(self::OPTION_KEY, array());
        if (!is_array($saved) || !$saved) {
            return $this->default_products();
        }
        return $this->normalize_registry($saved);
    }

    public function get_product($product_id) {
        $product_id = sanitize_key($product_id);
        $registry = $this->registry();
        return isset($registry[$product_id]) ? $registry[$product_id] : null;
    }

    public function normalize_registry($records) {
        $normalized = array();
        foreach ((array) $records as $key => $record) {
            if (!is_array($record)) {
                continue;
            }
            $key_id = is_string($key) ? sanitize_key($key) : '';
            $record_id = sanitize_key(isset($record['canonical_id']) ? $record['canonical_id'] : '');
            $id = $record_id !== '' ? $record_id : $key_id;
            if (!$id || isset($normalized[$id])) {
                continue;
            }
            $normalized[$id] = $this->normalize_record($id, $record);
        }
        uasort($normalized, function ($left, $right) {
            $order = absint($left['display_order']) <=> absint($right['display_order']);
            return $order !== 0 ? $order : strcasecmp($left['name'], $right['name']);
        });
        return $normalized;
    }

    private function normalize_record($id, $record) {
        $families = array_keys($this->families());
        $sources = array_keys($this->version_sources());
        $statuses = array_keys($this->statuses());
        $channels = array_keys($this->channels());
        $types = array_keys($this->product_types());
        $legacy = isset($record['legacy_names']) ? $record['legacy_names'] : array();
        if (is_string($legacy)) {
            $legacy = preg_split('/[\r\n,]+/', $legacy);
        }
        $legacy = array_values(array_unique(array_filter(array_map('sanitize_text_field', (array) $legacy))));
        $legacy_plugin_files = isset($record['legacy_plugin_files']) ? $record['legacy_plugin_files'] : array();
        $legacy_plugin_slugs = isset($record['legacy_plugin_slugs']) ? $record['legacy_plugin_slugs'] : array();
        $legacy_text_domains = isset($record['legacy_text_domains']) ? $record['legacy_text_domains'] : array();
        foreach (array('legacy_plugin_files', 'legacy_plugin_slugs', 'legacy_text_domains') as $list_key) {
            if (is_string($$list_key)) {
                $$list_key = preg_split('/[\r\n,]+/', $$list_key);
            }
        }
        $legacy_plugin_files = array_values(array_unique(array_filter(array_map(function ($value) {
            $value = str_replace('\\', '/', sanitize_text_field($value));
            return ltrim(preg_replace('#/+#', '/', $value), '/');
        }, (array) $legacy_plugin_files))));
        $legacy_plugin_slugs = array_values(array_unique(array_filter(array_map('sanitize_key', (array) $legacy_plugin_slugs))));
        $legacy_text_domains = array_values(array_unique(array_filter(array_map('sanitize_key', (array) $legacy_text_domains))));
        $family = sanitize_key(isset($record['family']) ? $record['family'] : 'foundation');
        $source = sanitize_key(isset($record['version_source']) ? $record['version_source'] : 'manual');
        $status = sanitize_key(isset($record['status']) ? $record['status'] : 'unverified');
        $channel = sanitize_key(isset($record['release_channel']) ? $record['release_channel'] : 'stable');
        $type = sanitize_key(isset($record['product_type']) ? $record['product_type'] : 'platform_module');
        $activation_scope = sanitize_key(isset($record['discovered_activation_scope']) ? $record['discovered_activation_scope'] : 'inactive');
        $version_state = sanitize_key(isset($record['discovered_version_state']) ? $record['discovered_version_state'] : 'unscanned');
        return array(
            'canonical_id' => $id,
            'name' => sanitize_text_field(isset($record['name']) ? $record['name'] : $id),
            'short_name' => sanitize_text_field(isset($record['short_name']) ? $record['short_name'] : ''),
            'legacy_names' => $legacy,
            'family' => in_array($family, $families, true) ? $family : 'foundation',
            'product_type' => in_array($type, $types, true) ? $type : 'platform_module',
            'version_source' => in_array($source, $sources, true) ? $source : 'manual',
            'plugin_file' => sanitize_text_field(isset($record['plugin_file']) ? $record['plugin_file'] : ''),
            'plugin_slug' => sanitize_key(isset($record['plugin_slug']) ? $record['plugin_slug'] : ''),
            'plugin_text_domain' => sanitize_key(isset($record['plugin_text_domain']) ? $record['plugin_text_domain'] : ''),
            'legacy_plugin_files' => $legacy_plugin_files,
            'legacy_plugin_slugs' => $legacy_plugin_slugs,
            'legacy_text_domains' => $legacy_text_domains,
            'discovery_enabled' => !empty($record['discovery_enabled']) ? '1' : '',
            'discovery_locked' => !empty($record['discovery_locked']) ? '1' : '',
            'discovery_state' => sanitize_key(isset($record['discovery_state']) ? $record['discovery_state'] : 'unscanned'),
            'discovery_match' => sanitize_key(isset($record['discovery_match']) ? $record['discovery_match'] : ''),
            'discovered_active' => !empty($record['discovered_active']) ? '1' : '',
            'discovered_activation_scope' => in_array($activation_scope, array('inactive', 'site', 'network', 'both'), true) ? $activation_scope : 'inactive',
            'discovered_plugin_name' => sanitize_text_field(isset($record['discovered_plugin_name']) ? $record['discovered_plugin_name'] : ''),
            'discovered_plugin_version' => sanitize_text_field(isset($record['discovered_plugin_version']) ? $record['discovered_plugin_version'] : ''),
            'discovered_plugin_version_raw' => sanitize_text_field(isset($record['discovered_plugin_version_raw']) ? $record['discovered_plugin_version_raw'] : ''),
            'discovered_version_state' => in_array($version_state, array('unscanned', 'valid', 'development', 'missing', 'malformed'), true) ? $version_state : 'unscanned',
            'discovered_text_domain' => sanitize_key(isset($record['discovered_text_domain']) ? $record['discovered_text_domain'] : ''),
            'last_discovered_at' => sanitize_text_field(isset($record['last_discovered_at']) ? $record['last_discovered_at'] : ''),
            'installed_version' => sanitize_text_field(isset($record['installed_version']) ? $record['installed_version'] : ''),
            'public_version' => sanitize_text_field(isset($record['public_version']) ? $record['public_version'] : ''),
            'release_channel' => in_array($channel, $channels, true) ? $channel : 'stable',
            'status' => in_array($status, $statuses, true) ? $status : 'unverified',
            'public_visible' => !empty($record['public_visible']) ? '1' : '',
            'homepage_visible' => !empty($record['homepage_visible']) ? '1' : '',
            'display_order' => absint(isset($record['display_order']) ? $record['display_order'] : 999),
            'product_url' => esc_url_raw(isset($record['product_url']) ? $record['product_url'] : ''),
            'documentation_url' => esc_url_raw(isset($record['documentation_url']) ? $record['documentation_url'] : ''),
            'support_url' => esc_url_raw(isset($record['support_url']) ? $record['support_url'] : ''),
            'release_notes_url' => esc_url_raw(isset($record['release_notes_url']) ? $record['release_notes_url'] : ''),
            'owner' => sanitize_text_field(isset($record['owner']) ? $record['owner'] : ''),
            'commercial' => !empty($record['commercial']) ? '1' : '',
            'public_interest' => !empty($record['public_interest']) ? '1' : '',
            'manual_notes' => sanitize_textarea_field(isset($record['manual_notes']) ? $record['manual_notes'] : ''),
            'last_verified_at' => sanitize_text_field(isset($record['last_verified_at']) ? $record['last_verified_at'] : ''),
        );
    }

    public function public_products($homepage_only = false) {
        $records = array();
        foreach ($this->registry() as $record) {
            if (empty($record['public_visible']) || ($homepage_only && empty($record['homepage_visible']))) {
                continue;
            }
            $records[] = $this->public_record($record);
        }
        return $records;
    }

    private function public_record($record) {
        return array(
            'canonical_id' => $record['canonical_id'],
            'name' => $record['name'],
            'short_name' => $record['short_name'],
            'family' => $record['family'],
            'product_type' => $record['product_type'],
            'version_source' => $record['version_source'],
            'version' => $record['public_version'] !== '' ? $record['public_version'] : $record['installed_version'],
            'release_channel' => $record['release_channel'],
            'status' => $record['status'],
            'display_order' => absint($record['display_order']),
            'product_url' => $record['product_url'],
            'documentation_url' => $record['documentation_url'],
            'support_url' => $record['support_url'],
            'release_notes_url' => $record['release_notes_url'],
            'commercial' => !empty($record['commercial']),
            'public_interest' => !empty($record['public_interest']),
            'last_verified_at' => $record['last_verified_at'],
        );
    }

    public function schema_record() {
        return array(
            'schema' => self::SCHEMA,
            'version' => self::VERSION,
            'storage' => 'wordpress_option',
            'option_key' => self::OPTION_KEY,
            'source_of_truth' => 'product_support_feedback_platform',
            'families' => array_keys($this->families()),
            'version_sources' => array_keys($this->version_sources()),
            'active_version_sources' => array('wordpress_plugin', 'manual'),
            'reserved_version_sources' => array('remote_manifest', 'service_endpoint', 'package_manifest'),
            'statuses' => array_keys($this->statuses()),
            'release_channels' => array_keys($this->channels()),
            'product_types' => array_keys($this->product_types()),
            'canonical_id_immutable' => true,
            'public_visibility_governed' => true,
            'homepage_visibility_governed' => true,
            'private_repository_fields_publicly_exposed' => false,
            'automatic_publication' => false,
            'human_review_required' => true,
            'installed_plugin_discovery' => true,
            'unknown_plugins_auto_registered' => false,
            'discovery_respects_product_lock' => true,
        );
    }

    public function summary_record() {
        $registry = $this->registry();
        $families = array_fill_keys(array_keys($this->families()), 0);
        $sources = array_fill_keys(array_keys($this->version_sources()), 0);
        $manual = 0;
        $visible = 0;
        foreach ($registry as $record) {
            if (isset($families[$record['family']])) {
                $families[$record['family']]++;
            }
            if (isset($sources[$record['version_source']])) {
                $sources[$record['version_source']]++;
            }
            if ($record['version_source'] === 'manual') {
                $manual++;
            }
            if (!empty($record['public_visible'])) {
                $visible++;
            }
        }
        return array(
            'schema' => self::SCHEMA,
            'version' => self::VERSION,
            'product_count' => count($registry),
            'public_product_count' => $visible,
            'manual_product_count' => $manual,
            'families' => $families,
            'version_sources' => $sources,
            'generated_at' => gmdate('c'),
        );
    }

    public function register_admin_page() {
        add_submenu_page(
            'edit.php?post_type=' . Sustainable_Catalyst_Feature_Suggestions::POST_TYPE,
            __('Canonical Product Registry', 'sustainable-catalyst-feature-suggestions'),
            __('Product Registry', 'sustainable-catalyst-feature-suggestions'),
            $this->capability(),
            self::ADMIN_SLUG,
            array($this, 'render_admin_page')
        );
    }

    private function select($name, $selected_value, $options) {
        echo '<select name="' . esc_attr($name) . '">';
        foreach ($options as $value => $label) {
            echo '<option value="' . esc_attr($value) . '" ' . selected($selected_value, $value, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select>';
    }

    public function render_admin_page() {
        if (!$this->can_manage()) {
            wp_die(esc_html__('You do not have permission to manage the product registry.', 'sustainable-catalyst-feature-suggestions'));
        }
        $registry = $this->registry();
        $summary = $this->summary_record();
        echo '<div class="wrap"><h1>' . esc_html__('Canonical Product Registry', 'sustainable-catalyst-feature-suggestions') . '</h1>';
        echo '<p>' . esc_html__('This registry is the governed source of product identity for release boards, support documentation, release records, and future product discovery. Installed-plugin discovery is active in v7.3.3. Use the Plugin Discovery screen to rescan safely.', 'sustainable-catalyst-feature-suggestions') . '</p>';
        if (isset($_GET['updated'])) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Product registry saved.', 'sustainable-catalyst-feature-suggestions') . '</p></div>';
        }
        echo '<div class="notice notice-info inline"><p><strong>' . esc_html(sprintf(__('%d products registered', 'sustainable-catalyst-feature-suggestions'), $summary['product_count'])) . '</strong> · ' . esc_html(sprintf(__('%d public', 'sustainable-catalyst-feature-suggestions'), $summary['public_product_count'])) . ' · ' . esc_html(sprintf(__('%d manually maintained', 'sustainable-catalyst-feature-suggestions'), $summary['manual_product_count'])) . '</p></div>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field(self::NONCE_ACTION, 'scfs_product_registry_nonce');
        echo '<input type="hidden" name="action" value="scfs_save_product_registry">';
        foreach ($registry as $id => $record) {
            $field = 'products[' . $id . ']';
            echo '<details style="background:#fff;border:1px solid #ccd0d4;margin:12px 0;padding:12px" ' . ($id === 'product-support-feedback' || $id === 'catalyst-intelligence' ? 'open' : '') . '>';
            echo '<summary style="cursor:pointer;font-size:15px"><strong>' . esc_html($record['name']) . '</strong> <code>' . esc_html($id) . '</code> — ' . esc_html($record['public_version'] ?: __('version not verified', 'sustainable-catalyst-feature-suggestions')) . '</summary>';
            echo '<input type="hidden" name="' . esc_attr($field . '[canonical_id]') . '" value="' . esc_attr($id) . '">';
            echo '<table class="form-table" role="presentation"><tbody>';
            echo '<tr><th><label>' . esc_html__('Public name', 'sustainable-catalyst-feature-suggestions') . '</label></th><td><input class="regular-text" name="' . esc_attr($field . '[name]') . '" value="' . esc_attr($record['name']) . '"></td><th><label>' . esc_html__('Short name', 'sustainable-catalyst-feature-suggestions') . '</label></th><td><input name="' . esc_attr($field . '[short_name]') . '" value="' . esc_attr($record['short_name']) . '"></td></tr>';
            echo '<tr><th>' . esc_html__('Family', 'sustainable-catalyst-feature-suggestions') . '</th><td>';
            $this->select($field . '[family]', $record['family'], $this->families());
            echo '</td><th>' . esc_html__('Product type', 'sustainable-catalyst-feature-suggestions') . '</th><td>';
            $this->select($field . '[product_type]', $record['product_type'], $this->product_types());
            echo '</td></tr>';
            echo '<tr><th>' . esc_html__('Version source', 'sustainable-catalyst-feature-suggestions') . '</th><td>';
            $this->select($field . '[version_source]', $record['version_source'], $this->version_sources());
            echo '</td><th>' . esc_html__('Display order', 'sustainable-catalyst-feature-suggestions') . '</th><td><input type="number" min="0" name="' . esc_attr($field . '[display_order]') . '" value="' . esc_attr($record['display_order']) . '"></td></tr>';
            echo '<tr><th>' . esc_html__('Installed version', 'sustainable-catalyst-feature-suggestions') . '</th><td><input name="' . esc_attr($field . '[installed_version]') . '" value="' . esc_attr($record['installed_version']) . '"></td><th>' . esc_html__('Public version', 'sustainable-catalyst-feature-suggestions') . '</th><td><input name="' . esc_attr($field . '[public_version]') . '" value="' . esc_attr($record['public_version']) . '"></td></tr>';
            echo '<tr><th>' . esc_html__('Release channel', 'sustainable-catalyst-feature-suggestions') . '</th><td>';
            $this->select($field . '[release_channel]', $record['release_channel'], $this->channels());
            echo '</td><th>' . esc_html__('Status', 'sustainable-catalyst-feature-suggestions') . '</th><td>';
            $this->select($field . '[status]', $record['status'], $this->statuses());
            echo '</td></tr>';
            echo '<tr><th>' . esc_html__('Plugin slug', 'sustainable-catalyst-feature-suggestions') . '</th><td><input class="regular-text" name="' . esc_attr($field . '[plugin_slug]') . '" value="' . esc_attr($record['plugin_slug']) . '"></td><th>' . esc_html__('Plugin file', 'sustainable-catalyst-feature-suggestions') . '</th><td><input class="regular-text" name="' . esc_attr($field . '[plugin_file]') . '" value="' . esc_attr($record['plugin_file']) . '"></td></tr>';
            echo '<tr><th>' . esc_html__('Plugin text domain', 'sustainable-catalyst-feature-suggestions') . '</th><td><input class="regular-text" name="' . esc_attr($field . '[plugin_text_domain]') . '" value="' . esc_attr($record['plugin_text_domain']) . '"></td><th>' . esc_html__('Discovery state', 'sustainable-catalyst-feature-suggestions') . '</th><td><code>' . esc_html($record['discovery_state']) . '</code> ' . esc_html($record['discovered_plugin_version']) . '</td></tr>';
            echo '<tr><th>' . esc_html__('Plugin discovery', 'sustainable-catalyst-feature-suggestions') . '</th><td colspan="3"><label style="margin-right:18px"><input type="checkbox" name="' . esc_attr($field . '[discovery_enabled]') . '" value="1" ' . checked(!empty($record['discovery_enabled']), true, false) . '> ' . esc_html__('Allow automatic installed-version detection', 'sustainable-catalyst-feature-suggestions') . '</label><label><input type="checkbox" name="' . esc_attr($field . '[discovery_locked]') . '" value="1" ' . checked(!empty($record['discovery_locked']), true, false) . '> ' . esc_html__('Lock version and status overrides', 'sustainable-catalyst-feature-suggestions') . '</label><p class="description">' . esc_html__('A lock keeps the discovery evidence current without replacing the configured installed version, public version, or status.', 'sustainable-catalyst-feature-suggestions') . '</p></td></tr>';
            echo '<tr><th>' . esc_html__('Product URL', 'sustainable-catalyst-feature-suggestions') . '</th><td><input class="regular-text" name="' . esc_attr($field . '[product_url]') . '" value="' . esc_attr($record['product_url']) . '"></td><th>' . esc_html__('Documentation URL', 'sustainable-catalyst-feature-suggestions') . '</th><td><input class="regular-text" name="' . esc_attr($field . '[documentation_url]') . '" value="' . esc_attr($record['documentation_url']) . '"></td></tr>';
            echo '<tr><th>' . esc_html__('Support URL', 'sustainable-catalyst-feature-suggestions') . '</th><td><input class="regular-text" name="' . esc_attr($field . '[support_url]') . '" value="' . esc_attr($record['support_url']) . '"></td><th>' . esc_html__('Release notes URL', 'sustainable-catalyst-feature-suggestions') . '</th><td><input class="regular-text" name="' . esc_attr($field . '[release_notes_url]') . '" value="' . esc_attr($record['release_notes_url']) . '"></td></tr>';
            echo '<tr><th>' . esc_html__('Owner', 'sustainable-catalyst-feature-suggestions') . '</th><td><input class="regular-text" name="' . esc_attr($field . '[owner]') . '" value="' . esc_attr($record['owner']) . '"></td><th>' . esc_html__('Last verified', 'sustainable-catalyst-feature-suggestions') . '</th><td><input class="regular-text" name="' . esc_attr($field . '[last_verified_at]') . '" value="' . esc_attr($record['last_verified_at']) . '"></td></tr>';
            echo '<tr><th>' . esc_html__('Visibility and role', 'sustainable-catalyst-feature-suggestions') . '</th><td colspan="3">';
            foreach (array('public_visible' => __('Public registry', 'sustainable-catalyst-feature-suggestions'), 'homepage_visible' => __('Homepage board', 'sustainable-catalyst-feature-suggestions'), 'commercial' => __('Commercial', 'sustainable-catalyst-feature-suggestions'), 'public_interest' => __('Public-interest infrastructure', 'sustainable-catalyst-feature-suggestions')) as $key => $label) {
                echo '<label style="margin-right:18px"><input type="checkbox" name="' . esc_attr($field . '[' . $key . ']') . '" value="1" ' . checked(!empty($record[$key]), true, false) . '> ' . esc_html($label) . '</label>';
            }
            echo '</td></tr>';
            echo '<tr><th>' . esc_html__('Legacy names', 'sustainable-catalyst-feature-suggestions') . '</th><td colspan="3"><input class="large-text" name="' . esc_attr($field . '[legacy_names]') . '" value="' . esc_attr(implode(', ', $record['legacy_names'])) . '"><p class="description">' . esc_html__('Comma-separated aliases used for migration and matching.', 'sustainable-catalyst-feature-suggestions') . '</p></td></tr>';
            echo '<tr><th>' . esc_html__('Legacy plugin identifiers', 'sustainable-catalyst-feature-suggestions') . '</th><td colspan="3">';
            echo '<label>' . esc_html__('Plugin files', 'sustainable-catalyst-feature-suggestions') . '<br><input class="large-text" name="' . esc_attr($field . '[legacy_plugin_files]') . '" value="' . esc_attr(implode(', ', $record['legacy_plugin_files'])) . '"></label><br>';
            echo '<label>' . esc_html__('Folder slugs', 'sustainable-catalyst-feature-suggestions') . '<br><input class="large-text" name="' . esc_attr($field . '[legacy_plugin_slugs]') . '" value="' . esc_attr(implode(', ', $record['legacy_plugin_slugs'])) . '"></label><br>';
            echo '<label>' . esc_html__('Text domains', 'sustainable-catalyst-feature-suggestions') . '<br><input class="large-text" name="' . esc_attr($field . '[legacy_text_domains]') . '" value="' . esc_attr(implode(', ', $record['legacy_text_domains'])) . '"></label>';
            echo '<p class="description">' . esc_html__('Comma-separated compatibility identifiers used only for controlled discovery matching.', 'sustainable-catalyst-feature-suggestions') . '</p></td></tr>';
            echo '<tr><th>' . esc_html__('Administrative notes', 'sustainable-catalyst-feature-suggestions') . '</th><td colspan="3"><textarea class="large-text" rows="3" name="' . esc_attr($field . '[manual_notes]') . '">' . esc_textarea($record['manual_notes']) . '</textarea></td></tr>';
            echo '</tbody></table></details>';
        }
        submit_button(__('Save Product Registry', 'sustainable-catalyst-feature-suggestions'));
        echo '</form>';
        echo '<hr><p><a class="button" href="' . esc_url(wp_nonce_url(admin_url('admin-post.php?action=scfs_export_product_registry'), 'scfs_export_product_registry')) . '">' . esc_html__('Export registry JSON', 'sustainable-catalyst-feature-suggestions') . '</a> ';
        echo '<a class="button" href="' . esc_url(wp_nonce_url(admin_url('admin-post.php?action=scfs_reset_product_registry'), 'scfs_reset_product_registry')) . '" onclick="return confirm(\'' . esc_js(__('Reset the product registry to the v7.3.3 defaults?', 'sustainable-catalyst-feature-suggestions')) . '\')">' . esc_html__('Reset defaults', 'sustainable-catalyst-feature-suggestions') . '</a></p>';
        echo '</div>';
    }

    public function save_registry_action() {
        if (!$this->can_manage()) {
            wp_die('Forbidden');
        }
        check_admin_referer(self::NONCE_ACTION, 'scfs_product_registry_nonce');
        $products = isset($_POST['products']) && is_array($_POST['products']) ? wp_unslash($_POST['products']) : array();
        $normalized = $this->normalize_registry($products);
        if (!$normalized) {
            wp_die(esc_html__('The registry cannot be empty.', 'sustainable-catalyst-feature-suggestions'));
        }
        update_option(self::OPTION_KEY, $normalized, false);
        update_option(self::SCHEMA_OPTION, self::SCHEMA, false);
        $this->audit('registry_saved', array('product_count' => count($normalized)));
        do_action('scfs_product_registry_updated', $normalized);
        wp_safe_redirect(admin_url('edit.php?post_type=' . Sustainable_Catalyst_Feature_Suggestions::POST_TYPE . '&page=' . self::ADMIN_SLUG . '&updated=1'));
        exit;
    }

    public function reset_registry_action() {
        if (!$this->can_manage()) {
            wp_die('Forbidden');
        }
        check_admin_referer('scfs_reset_product_registry');
        $defaults = $this->default_products();
        update_option(self::OPTION_KEY, $defaults, false);
        update_option(self::SCHEMA_OPTION, self::SCHEMA, false);
        $this->audit('registry_reset', array('product_count' => count($defaults)));
        do_action('scfs_product_registry_updated', $defaults);
        wp_safe_redirect(admin_url('edit.php?post_type=' . Sustainable_Catalyst_Feature_Suggestions::POST_TYPE . '&page=' . self::ADMIN_SLUG . '&updated=1'));
        exit;
    }

    public function export_registry_action() {
        if (!$this->can_manage()) {
            wp_die('Forbidden');
        }
        check_admin_referer('scfs_export_product_registry');
        $payload = array(
            'schema' => self::SCHEMA,
            'version' => self::VERSION,
            'exported_at' => gmdate('c'),
            'summary' => $this->summary_record(),
            'products' => array_values($this->registry()),
        );
        $this->audit('registry_exported', array('product_count' => count($payload['products'])));
        nocache_headers();
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename=scfs-product-registry-' . gmdate('Y-m-d') . '.json');
        echo wp_json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public function register_rest_routes() {
        register_rest_route(Sustainable_Catalyst_Feature_Suggestions::REST_NAMESPACE, '/product-registry/schema', array(
            'methods' => 'GET',
            'callback' => array($this, 'rest_schema'),
            'permission_callback' => array($this, 'rest_permission'),
        ));
        register_rest_route(Sustainable_Catalyst_Feature_Suggestions::REST_NAMESPACE, '/product-registry', array(
            'methods' => 'GET',
            'callback' => array($this, 'rest_registry'),
            'permission_callback' => array($this, 'rest_permission'),
        ));
        register_rest_route(Sustainable_Catalyst_Feature_Suggestions::REST_NAMESPACE, '/product-registry/(?P<product_id>[a-z0-9-]+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'rest_product'),
            'permission_callback' => array($this, 'rest_permission'),
        ));
    }

    public function rest_permission() {
        return current_user_can('edit_posts');
    }

    public function rest_schema() {
        return rest_ensure_response($this->schema_record());
    }

    public function rest_registry() {
        return rest_ensure_response(array(
            'ok' => true,
            'summary' => $this->summary_record(),
            'products' => array_values($this->registry()),
        ));
    }

    public function rest_product($request) {
        $record = $this->get_product($request['product_id']);
        if (!$record) {
            return new WP_Error('scfs_product_not_found', __('Product not found.', 'sustainable-catalyst-feature-suggestions'), array('status' => 404));
        }
        return rest_ensure_response(array('ok' => true, 'product' => $record));
    }

    public function cli_list($args, $assoc_args) {
        $rows = array();
        foreach ($this->registry() as $record) {
            $rows[] = array(
                'id' => $record['canonical_id'],
                'name' => $record['name'],
                'family' => $record['family'],
                'source' => $record['version_source'],
                'version' => $record['public_version'] ?: $record['installed_version'],
                'status' => $record['status'],
                'public' => !empty($record['public_visible']) ? 'yes' : 'no',
            );
        }
        WP_CLI\Utils\format_items(isset($assoc_args['format']) ? $assoc_args['format'] : 'table', $rows, array('id', 'name', 'family', 'source', 'version', 'status', 'public'));
    }

    public function cli_export($args, $assoc_args) {
        WP_CLI::line(wp_json_encode(array(
            'schema' => self::SCHEMA,
            'version' => self::VERSION,
            'products' => array_values($this->registry()),
        ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    public function cli_seed($args, $assoc_args) {
        $force = !empty($assoc_args['force']);
        $existing = get_option(self::OPTION_KEY, array());
        if ($existing && !$force) {
            WP_CLI::warning('Registry already exists. Use --force to reset it.');
            return;
        }
        $defaults = $this->default_products();
        update_option(self::OPTION_KEY, $defaults, false);
        update_option(self::SCHEMA_OPTION, self::SCHEMA, false);
        $this->audit('registry_seeded_cli', array('force' => $force, 'product_count' => count($defaults)));
        WP_CLI::success('Canonical product registry seeded with ' . count($defaults) . ' products.');
    }

    private function audit($action, $details = array()) {
        $log = (array) get_option(self::AUDIT_OPTION, array());
        $user = function_exists('wp_get_current_user') ? wp_get_current_user() : null;
        $log[] = array(
            'timestamp' => gmdate('c'),
            'action' => sanitize_key($action),
            'user' => $user && $user->exists() ? $user->user_login : 'system',
            'details' => $details,
        );
        if (count($log) > self::MAX_AUDIT_ENTRIES) {
            $log = array_slice($log, -self::MAX_AUDIT_ENTRIES);
        }
        update_option(self::AUDIT_OPTION, $log, false);
    }
}
