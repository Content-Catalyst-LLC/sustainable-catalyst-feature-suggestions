<?php
/**
 * Installed Plugin Discovery.
 *
 * Safely reconciles approved Sustainable Catalyst product records with the
 * WordPress plugins actually installed on the site. Unknown, malformed, and
 * duplicate candidates remain administrator-only and never publish directly.
 *
 * @package Sustainable_Catalyst_Feature_Suggestions
 */

if (!defined('ABSPATH')) {
    exit;
}

final class SCFS_Installed_Plugin_Discovery {
    const VERSION = '7.3.3';
    const SCHEMA = 'scfs-installed-plugin-discovery/1.0';
    const DIAGNOSTICS_SCHEMA = 'scfs-plugin-discovery-diagnostics/1.0';
    const SCHEMA_OPTION = 'scfs_installed_plugin_discovery_schema';
    const SNAPSHOT_OPTION = 'scfs_installed_plugin_discovery_snapshot';
    const PENDING_OPTION = 'scfs_installed_plugin_discovery_pending';
    const DIAGNOSTICS_OPTION = 'scfs_installed_plugin_discovery_diagnostics';
    const AUDIT_OPTION = 'scfs_installed_plugin_discovery_audit';
    const CACHE_KEY = 'scfs_installed_plugin_discovery_cache';
    const ADMIN_SLUG = 'scfs-plugin-discovery';
    const MAX_AUDIT_ENTRIES = 250;
    const MAX_DIAGNOSTICS = 250;
    const CACHE_TTL = 900;

    private static $instance = null;

    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_init', array($this, 'maybe_upgrade'), 4);
        add_action('admin_menu', array($this, 'register_admin_page'), 22);
        add_action('admin_post_scfs_rescan_installed_plugins', array($this, 'rescan_action'));
        add_action('admin_post_scfs_clear_plugin_discovery', array($this, 'clear_action'));
        add_action('activated_plugin', array($this, 'plugin_state_changed'), 10, 2);
        add_action('deactivated_plugin', array($this, 'plugin_state_changed'), 10, 2);
        add_action('deleted_plugin', array($this, 'plugin_deleted'), 10, 2);
        add_action('upgrader_process_complete', array($this, 'upgrader_complete'), 10, 2);
        add_action('scfs_product_registry_updated', array($this, 'registry_changed'));
        add_action('rest_api_init', array($this, 'register_rest_routes'));

        if (defined('WP_CLI') && WP_CLI && class_exists('WP_CLI')) {
            WP_CLI::add_command('scfs products discover', array($this, 'cli_discover'));
            WP_CLI::add_command('scfs products discovery-status', array($this, 'cli_status'));
            WP_CLI::add_command('scfs products discovery-diagnostics', array($this, 'cli_diagnostics'));
        }
    }

    public static function activate() {
        update_option(self::SCHEMA_OPTION, self::SCHEMA, false);
        if (!get_option(self::SNAPSHOT_OPTION)) {
            add_option(self::SNAPSHOT_OPTION, self::instance()->empty_snapshot(), '', false);
        }
        if (!get_option(self::PENDING_OPTION)) {
            add_option(self::PENDING_OPTION, array(), '', false);
        }
        if (!get_option(self::DIAGNOSTICS_OPTION)) {
            add_option(self::DIAGNOSTICS_OPTION, self::instance()->empty_diagnostics(), '', false);
        }
        if (!get_option(self::AUDIT_OPTION)) {
            add_option(self::AUDIT_OPTION, array(), '', false);
        }
        self::instance()->invalidate_cache('activation');
    }

    public function maybe_upgrade() {
        if (get_option(self::SCHEMA_OPTION) === self::SCHEMA && is_array(get_option(self::DIAGNOSTICS_OPTION))) {
            return;
        }
        update_option(self::SCHEMA_OPTION, self::SCHEMA, false);
        if (!is_array(get_option(self::SNAPSHOT_OPTION))) {
            update_option(self::SNAPSHOT_OPTION, $this->empty_snapshot(), false);
        }
        if (!is_array(get_option(self::PENDING_OPTION))) {
            update_option(self::PENDING_OPTION, array(), false);
        }
        if (!is_array(get_option(self::DIAGNOSTICS_OPTION))) {
            update_option(self::DIAGNOSTICS_OPTION, $this->empty_diagnostics(), false);
        }
        $this->invalidate_cache('schema_upgrade');
        $this->audit('discovery_schema_upgraded', array('schema' => self::SCHEMA, 'version' => self::VERSION));
    }

    public function capability() {
        return apply_filters('scfs_plugin_discovery_capability', 'manage_options');
    }

    public function can_manage() {
        return current_user_can($this->capability());
    }

    public function schema_record() {
        return array(
            'schema' => self::SCHEMA,
            'diagnostics_schema' => self::DIAGNOSTICS_SCHEMA,
            'version' => self::VERSION,
            'source' => 'installed_wordpress_plugins',
            'registry_authority' => 'scfs_canonical_product_registry',
            'matching_hierarchy' => array(
                'exact_plugin_file',
                'legacy_plugin_file',
                'sc_product_id_header',
                'plugin_slug',
                'legacy_plugin_slug',
                'text_domain',
                'legacy_text_domain',
                'approved_name_alias',
            ),
            'approved_registry_only' => true,
            'unknown_plugins_auto_registered' => false,
            'unknown_plugins_publicly_exposed' => false,
            'absolute_plugin_paths_publicly_exposed' => false,
            'automatic_publication' => false,
            'manual_overrides_preserved' => true,
            'product_lock_supported' => true,
            'deterministic_duplicate_resolution' => true,
            'legacy_identifiers_supported' => true,
            'version_normalization' => true,
            'malformed_headers_quarantined' => true,
            'multisite_activation_scope' => true,
            'cache_ttl_seconds' => self::CACHE_TTL,
            'human_review_required' => true,
        );
    }

    private function empty_snapshot() {
        return array(
            'schema' => self::SCHEMA,
            'version' => self::VERSION,
            'scanned_at' => '',
            'reason' => 'not_scanned',
            'installed_plugin_count' => 0,
            'matched_product_count' => 0,
            'pending_candidate_count' => 0,
            'active_match_count' => 0,
            'inactive_match_count' => 0,
            'network_active_match_count' => 0,
            'diagnostic_count' => 0,
            'matches' => array(),
        );
    }

    private function empty_diagnostics() {
        return array(
            'schema' => self::DIAGNOSTICS_SCHEMA,
            'version' => self::VERSION,
            'generated_at' => '',
            'error_count' => 0,
            'warning_count' => 0,
            'info_count' => 0,
            'items' => array(),
        );
    }

    public function snapshot() {
        $snapshot = get_option(self::SNAPSHOT_OPTION, array());
        return is_array($snapshot) ? array_merge($this->empty_snapshot(), $snapshot) : $this->empty_snapshot();
    }

    public function pending_candidates() {
        $pending = get_option(self::PENDING_OPTION, array());
        return is_array($pending) ? array_values($pending) : array();
    }

    public function diagnostics() {
        $diagnostics = get_option(self::DIAGNOSTICS_OPTION, array());
        return is_array($diagnostics) ? array_merge($this->empty_diagnostics(), $diagnostics) : $this->empty_diagnostics();
    }

    public function summary_record() {
        $snapshot = $this->snapshot();
        $diagnostics = $this->diagnostics();
        return array(
            'schema' => self::SCHEMA,
            'version' => self::VERSION,
            'scanned_at' => $snapshot['scanned_at'],
            'installed_plugin_count' => absint($snapshot['installed_plugin_count']),
            'matched_product_count' => absint($snapshot['matched_product_count']),
            'pending_candidate_count' => absint($snapshot['pending_candidate_count']),
            'active_match_count' => absint($snapshot['active_match_count']),
            'inactive_match_count' => absint($snapshot['inactive_match_count']),
            'network_active_match_count' => absint($snapshot['network_active_match_count']),
            'diagnostic_count' => count((array) $diagnostics['items']),
            'diagnostic_errors' => absint($diagnostics['error_count']),
            'diagnostic_warnings' => absint($diagnostics['warning_count']),
            'cache_ttl_seconds' => self::CACHE_TTL,
            'human_review_required' => true,
        );
    }

    public function invalidate_cache($reason = 'manual') {
        delete_transient(self::CACHE_KEY);
        do_action('scfs_plugin_discovery_cache_invalidated', sanitize_key($reason));
    }

    public function plugin_state_changed($plugin = '', $network_wide = false) {
        $this->invalidate_cache('plugin_state_changed');
        $this->refresh(true, $network_wide ? 'network_plugin_state_changed' : 'plugin_state_changed');
    }

    public function plugin_deleted($plugin_file = '', $deleted = false) {
        if ($deleted) {
            $this->invalidate_cache('plugin_deleted');
            $this->refresh(true, 'plugin_deleted');
        }
    }

    public function upgrader_complete($upgrader, $options) {
        if (!is_array($options) || ($options['type'] ?? '') !== 'plugin') {
            return;
        }
        $this->invalidate_cache('plugin_upgrade');
        $this->refresh(true, 'plugin_upgrade');
    }

    public function registry_changed($registry = array()) {
        $this->invalidate_cache('registry_changed');
    }

    private function load_plugin_api() {
        if (!function_exists('get_plugins') && defined('ABSPATH')) {
            $plugin_api = ABSPATH . 'wp-admin/includes/plugin.php';
            if (is_file($plugin_api)) {
                require_once $plugin_api;
            }
        }
        return function_exists('get_plugins');
    }

    private function normalize_plugin_file($plugin_file) {
        $plugin_file = str_replace('\\', '/', (string) $plugin_file);
        $plugin_file = ltrim($plugin_file, '/');
        return preg_replace('#/+#', '/', $plugin_file);
    }

    private function active_plugin_scopes() {
        $scopes = array();
        foreach ((array) get_option('active_plugins', array()) as $plugin_file) {
            $plugin_file = $this->normalize_plugin_file($plugin_file);
            if ($plugin_file !== '') {
                $scopes[$plugin_file] = 'site';
            }
        }
        if (is_multisite()) {
            $network = (array) get_site_option('active_sitewide_plugins', array());
            foreach (array_keys($network) as $plugin_file) {
                $plugin_file = $this->normalize_plugin_file($plugin_file);
                if ($plugin_file === '') {
                    continue;
                }
                $scopes[$plugin_file] = isset($scopes[$plugin_file]) ? 'both' : 'network';
            }
        }
        return $scopes;
    }

    private function custom_headers($plugin_file) {
        $headers = array(
            'SCProductID' => 'SC Product ID',
            'SCProduct' => 'SC Product',
            'SCPublicRelease' => 'SC Public Release',
        );
        $full_path = defined('WP_PLUGIN_DIR') ? WP_PLUGIN_DIR . '/' . $plugin_file : '';
        if (!$full_path || !is_file($full_path) || !function_exists('get_file_data')) {
            return array_fill_keys(array_keys($headers), '');
        }
        $data = get_file_data($full_path, $headers, 'plugin');
        return is_array($data) ? $data : array_fill_keys(array_keys($headers), '');
    }

    private function clean_header($value) {
        $value = wp_strip_all_tags((string) $value);
        $value = preg_replace('/[\x00-\x1F\x7F]+/', ' ', $value);
        return trim(preg_replace('/\s+/', ' ', $value));
    }

    private function normalize_version($value) {
        $raw = $this->clean_header($value);
        if ($raw === '') {
            return array('raw' => '', 'normalized' => '', 'state' => 'missing');
        }
        $normalized = preg_replace('/^v(?=\d)/i', '', $raw);
        $normalized = preg_replace('/\s+/', '', $normalized);
        if (!preg_match('/^\d+(?:\.\d+){0,3}(?:[-+][0-9A-Za-z.-]+)?$/', $normalized)) {
            return array('raw' => $raw, 'normalized' => '', 'state' => 'malformed');
        }
        $state = preg_match('/(?:^|[-.])(dev|alpha|beta|rc|preview)(?:[.-]|$)/i', $normalized) ? 'development' : 'valid';
        return array('raw' => $raw, 'normalized' => $normalized, 'state' => $state);
    }

    private function installed_plugins() {
        if (!$this->load_plugin_api()) {
            return array();
        }
        $scopes = $this->active_plugin_scopes();
        $plugins = array();
        foreach ((array) get_plugins() as $plugin_file => $metadata) {
            $plugin_file = $this->normalize_plugin_file($plugin_file);
            if ($plugin_file === '') {
                continue;
            }
            $custom = $this->custom_headers($plugin_file);
            $directory_slug = sanitize_key(dirname($plugin_file) === '.' ? pathinfo($plugin_file, PATHINFO_FILENAME) : dirname($plugin_file));
            $name = $this->clean_header($metadata['Name'] ?? '');
            $version = $this->normalize_version($metadata['Version'] ?? '');
            $scope = isset($scopes[$plugin_file]) ? $scopes[$plugin_file] : 'inactive';
            $plugins[$plugin_file] = array(
                'plugin_file' => $plugin_file,
                'directory_slug' => $directory_slug,
                'name' => $name,
                'name_state' => $name === '' ? 'missing' : 'valid',
                'version' => $version['normalized'],
                'version_raw' => $version['raw'],
                'version_state' => $version['state'],
                'author' => $this->clean_header($metadata['AuthorName'] ?? ($metadata['Author'] ?? '')),
                'text_domain' => sanitize_key($metadata['TextDomain'] ?? ''),
                'active' => $scope !== 'inactive',
                'activation_scope' => $scope,
                'sc_product_id' => sanitize_key($custom['SCProductID'] ?? ''),
                'sc_product' => $this->clean_header($custom['SCProduct'] ?? ''),
                'sc_public_release' => $this->clean_header($custom['SCPublicRelease'] ?? ''),
            );
        }
        ksort($plugins, SORT_NATURAL | SORT_FLAG_CASE);
        return $plugins;
    }

    private function normalize_name($name) {
        $name = strtolower(wp_strip_all_tags((string) $name));
        return preg_replace('/[^a-z0-9]+/', '', $name);
    }

    private function normalize_identifier_list($values, $type = 'slug') {
        $out = array();
        foreach ((array) $values as $value) {
            $value = $type === 'file' ? $this->normalize_plugin_file($value) : sanitize_key($value);
            if ($value !== '') {
                $out[] = $value;
            }
        }
        return array_values(array_unique($out));
    }

    private function approved_aliases($record) {
        $aliases = array($record['name'] ?? '', $record['short_name'] ?? '');
        $aliases = array_merge($aliases, (array) ($record['legacy_names'] ?? array()));
        return array_values(array_unique(array_filter(array_map(array($this, 'normalize_name'), $aliases))));
    }

    private function match_plugin($plugin, $registry) {
        foreach ($registry as $product_id => $record) {
            if (($record['version_source'] ?? '') !== 'wordpress_plugin' || empty($record['discovery_enabled'])) {
                continue;
            }
            $configured_file = $this->normalize_plugin_file($record['plugin_file'] ?? '');
            if ($configured_file && hash_equals($configured_file, $plugin['plugin_file'])) {
                return array('product_id' => $product_id, 'strategy' => 'exact_plugin_file', 'confidence' => 100, 'legacy' => false);
            }
            if (in_array($plugin['plugin_file'], $this->normalize_identifier_list($record['legacy_plugin_files'] ?? array(), 'file'), true)) {
                return array('product_id' => $product_id, 'strategy' => 'legacy_plugin_file', 'confidence' => 99, 'legacy' => true);
            }
        }
        if (!empty($plugin['sc_product_id']) && isset($registry[$plugin['sc_product_id']]) && ($registry[$plugin['sc_product_id']]['version_source'] ?? '') === 'wordpress_plugin' && !empty($registry[$plugin['sc_product_id']]['discovery_enabled'])) {
            return array('product_id' => $plugin['sc_product_id'], 'strategy' => 'sc_product_id_header', 'confidence' => 98, 'legacy' => false);
        }
        foreach ($registry as $product_id => $record) {
            if (($record['version_source'] ?? '') !== 'wordpress_plugin' || empty($record['discovery_enabled'])) {
                continue;
            }
            $slug = sanitize_key($record['plugin_slug'] ?? '');
            if ($slug && hash_equals($slug, $plugin['directory_slug'])) {
                return array('product_id' => $product_id, 'strategy' => 'plugin_slug', 'confidence' => 95, 'legacy' => false);
            }
            if (in_array($plugin['directory_slug'], $this->normalize_identifier_list($record['legacy_plugin_slugs'] ?? array()), true)) {
                return array('product_id' => $product_id, 'strategy' => 'legacy_plugin_slug', 'confidence' => 94, 'legacy' => true);
            }
        }
        foreach ($registry as $product_id => $record) {
            if (($record['version_source'] ?? '') !== 'wordpress_plugin' || empty($record['discovery_enabled'])) {
                continue;
            }
            $text_domain = sanitize_key($record['plugin_text_domain'] ?? '');
            if (!$text_domain) {
                $text_domain = sanitize_key($record['plugin_slug'] ?? '');
            }
            if ($text_domain && !empty($plugin['text_domain']) && hash_equals($text_domain, $plugin['text_domain'])) {
                return array('product_id' => $product_id, 'strategy' => 'text_domain', 'confidence' => 90, 'legacy' => false);
            }
            if (!empty($plugin['text_domain']) && in_array($plugin['text_domain'], $this->normalize_identifier_list($record['legacy_text_domains'] ?? array()), true)) {
                return array('product_id' => $product_id, 'strategy' => 'legacy_text_domain', 'confidence' => 89, 'legacy' => true);
            }
        }
        $approved_author = stripos($plugin['author'], 'Content Catalyst') !== false;
        if ($approved_author && $plugin['name_state'] === 'valid') {
            $plugin_name = $this->normalize_name($plugin['name']);
            foreach ($registry as $product_id => $record) {
                if (($record['version_source'] ?? '') !== 'wordpress_plugin' || empty($record['discovery_enabled'])) {
                    continue;
                }
                if ($plugin_name && in_array($plugin_name, $this->approved_aliases($record), true)) {
                    return array('product_id' => $product_id, 'strategy' => 'approved_name_alias', 'confidence' => 80, 'legacy' => $plugin_name !== $this->normalize_name($record['name'] ?? ''));
                }
            }
        }
        return null;
    }

    private function catalyst_candidate($plugin) {
        return !empty($plugin['sc_product_id'])
            || !empty($plugin['sc_product'])
            || stripos($plugin['author'], 'Content Catalyst') !== false
            || strpos($plugin['text_domain'], 'sustainable-catalyst') === 0
            || strpos($plugin['text_domain'], 'catalyst-') === 0
            || strpos($plugin['directory_slug'], 'sustainable-catalyst') === 0
            || strpos($plugin['directory_slug'], 'catalyst-') === 0;
    }

    private function diagnostic($level, $code, $message, $plugin = array(), $product_id = '') {
        return array(
            'level' => in_array($level, array('error', 'warning', 'info'), true) ? $level : 'info',
            'code' => sanitize_key($code),
            'product_id' => sanitize_key($product_id),
            'plugin_file' => $this->normalize_plugin_file($plugin['plugin_file'] ?? ''),
            'message' => sanitize_text_field($message),
        );
    }

    private function registry_alias_diagnostics($registry) {
        $owners = array();
        $items = array();
        foreach ($registry as $product_id => $record) {
            if (($record['version_source'] ?? '') !== 'wordpress_plugin' || empty($record['discovery_enabled'])) {
                continue;
            }
            foreach ($this->approved_aliases($record) as $alias) {
                if (isset($owners[$alias]) && $owners[$alias] !== $product_id) {
                    $items[] = $this->diagnostic('warning', 'registry_alias_collision', 'The same approved name alias is assigned to more than one product.', array(), $product_id);
                } else {
                    $owners[$alias] = $product_id;
                }
            }
        }
        return $items;
    }

    private function candidate_score($candidate) {
        $state_score = array('valid' => 3, 'development' => 2, 'missing' => 1, 'malformed' => 0);
        return array(
            absint($candidate['confidence']),
            !empty($candidate['active']) ? 1 : 0,
            $state_score[$candidate['version_state']] ?? 0,
        );
    }

    private function compare_candidates($left, $right) {
        $left_score = $this->candidate_score($left);
        $right_score = $this->candidate_score($right);
        for ($i = 0; $i < count($left_score); $i++) {
            if ($left_score[$i] !== $right_score[$i]) {
                return $right_score[$i] <=> $left_score[$i];
            }
        }
        return strcasecmp($left['plugin_file'], $right['plugin_file']);
    }

    private function diagnostics_record($items, $generated_at) {
        $items = array_slice(array_values($items), 0, self::MAX_DIAGNOSTICS);
        $counts = array('error' => 0, 'warning' => 0, 'info' => 0);
        foreach ($items as $item) {
            $level = $item['level'] ?? 'info';
            if (isset($counts[$level])) {
                $counts[$level]++;
            }
        }
        return array(
            'schema' => self::DIAGNOSTICS_SCHEMA,
            'version' => self::VERSION,
            'generated_at' => $generated_at,
            'error_count' => $counts['error'],
            'warning_count' => $counts['warning'],
            'info_count' => $counts['info'],
            'items' => $items,
        );
    }

    public function refresh($force = false, $reason = 'manual') {
        if (!$force) {
            $cached = get_transient(self::CACHE_KEY);
            if (is_array($cached)) {
                return $cached;
            }
        }
        $plugins = $this->installed_plugins();
        $registry_service = SCFS_Canonical_Product_Registry::instance();
        $registry = $registry_service->registry();
        $candidate_groups = array();
        $matches = array();
        $pending = array();
        $diagnostics = $this->registry_alias_diagnostics($registry);
        $active_count = 0;
        $inactive_count = 0;
        $network_count = 0;
        $scanned_at = gmdate('c');

        foreach ($plugins as $plugin) {
            if ($plugin['name_state'] === 'missing' && $this->catalyst_candidate($plugin)) {
                $diagnostics[] = $this->diagnostic('warning', 'plugin_name_missing', 'The plugin header does not provide a usable Plugin Name.', $plugin);
            }
            if ($plugin['version_state'] === 'missing' && $this->catalyst_candidate($plugin)) {
                $diagnostics[] = $this->diagnostic('warning', 'plugin_version_missing', 'The plugin header does not provide a version.', $plugin);
            } elseif ($plugin['version_state'] === 'malformed' && $this->catalyst_candidate($plugin)) {
                $diagnostics[] = $this->diagnostic('warning', 'plugin_version_malformed', 'The plugin version is malformed and will not update release information.', $plugin);
            } elseif ($plugin['version_state'] === 'development' && $this->catalyst_candidate($plugin)) {
                $diagnostics[] = $this->diagnostic('info', 'development_version_detected', 'A development or prerelease plugin version was detected.', $plugin);
            }

            $match = $this->match_plugin($plugin, $registry);
            if (!$match) {
                if ($this->catalyst_candidate($plugin)) {
                    $pending[] = array(
                        'plugin_file' => $plugin['plugin_file'],
                        'name' => $plugin['name'] !== '' ? $plugin['name'] : $plugin['directory_slug'],
                        'version' => $plugin['version'],
                        'version_raw' => $plugin['version_raw'],
                        'version_state' => $plugin['version_state'],
                        'author' => $plugin['author'],
                        'text_domain' => $plugin['text_domain'],
                        'active' => (bool) $plugin['active'],
                        'activation_scope' => $plugin['activation_scope'],
                        'suggested_product_id' => $plugin['sc_product_id'],
                        'review_state' => $plugin['name_state'] === 'missing' || $plugin['version_state'] === 'malformed' ? 'malformed_header' : 'pending',
                    );
                }
                continue;
            }
            $product_id = $match['product_id'];
            $candidate_groups[$product_id][] = array_merge($plugin, array(
                'product_id' => $product_id,
                'match_strategy' => $match['strategy'],
                'confidence' => absint($match['confidence']),
                'legacy_match' => !empty($match['legacy']),
            ));
        }

        foreach ($candidate_groups as $product_id => $candidates) {
            usort($candidates, array($this, 'compare_candidates'));
            $winner = array_shift($candidates);
            if ($winner['legacy_match']) {
                $diagnostics[] = $this->diagnostic('info', 'legacy_identifier_match', 'A configured legacy plugin identifier was used to match this product.', $winner, $product_id);
            }
            $record = $registry[$product_id] ?? array();
            if ($winner['name_state'] === 'valid' && $this->normalize_name($winner['name']) !== $this->normalize_name($record['name'] ?? '')) {
                $diagnostics[] = $this->diagnostic('info', 'plugin_display_name_changed', 'The installed plugin display name differs from the canonical product name; the canonical public name is preserved.', $winner, $product_id);
            }
            foreach ($candidates as $duplicate) {
                $pending[] = array(
                    'plugin_file' => $duplicate['plugin_file'],
                    'name' => $duplicate['name'] !== '' ? $duplicate['name'] : $duplicate['directory_slug'],
                    'version' => $duplicate['version'],
                    'version_raw' => $duplicate['version_raw'],
                    'version_state' => $duplicate['version_state'],
                    'author' => $duplicate['author'],
                    'text_domain' => $duplicate['text_domain'],
                    'active' => (bool) $duplicate['active'],
                    'activation_scope' => $duplicate['activation_scope'],
                    'suggested_product_id' => $product_id,
                    'selected_plugin_file' => $winner['plugin_file'],
                    'review_state' => 'duplicate_match',
                );
                $diagnostics[] = $this->diagnostic('warning', 'duplicate_product_match', 'Multiple plugins matched the same canonical product; the highest-confidence deterministic match was selected.', $duplicate, $product_id);
            }
            $winner['active'] ? $active_count++ : $inactive_count++;
            if (in_array($winner['activation_scope'], array('network', 'both'), true)) {
                $network_count++;
            }
            $matches[$product_id] = array(
                'product_id' => $product_id,
                'plugin_file' => $winner['plugin_file'],
                'plugin_name' => $winner['name'] !== '' ? $winner['name'] : $winner['directory_slug'],
                'version' => $winner['version'],
                'version_raw' => $winner['version_raw'],
                'version_state' => $winner['version_state'],
                'text_domain' => $winner['text_domain'],
                'active' => (bool) $winner['active'],
                'activation_scope' => $winner['activation_scope'],
                'match_strategy' => $winner['match_strategy'],
                'confidence' => absint($winner['confidence']),
                'legacy_match' => (bool) $winner['legacy_match'],
                'discovered_at' => $scanned_at,
            );
        }

        foreach ($registry as $product_id => &$record) {
            if (($record['version_source'] ?? '') !== 'wordpress_plugin' || empty($record['discovery_enabled'])) {
                continue;
            }
            if (!isset($matches[$product_id])) {
                $record['discovery_state'] = 'not_found';
                $record['discovery_match'] = '';
                $record['discovered_active'] = '';
                $record['discovered_activation_scope'] = 'inactive';
                $record['last_discovered_at'] = $scanned_at;
                continue;
            }
            $found = $matches[$product_id];
            $previous_installed = (string) ($record['installed_version'] ?? '');
            $record['plugin_file'] = $found['plugin_file'];
            if (empty($record['plugin_slug'])) {
                $record['plugin_slug'] = sanitize_key(dirname($found['plugin_file']) === '.' ? pathinfo($found['plugin_file'], PATHINFO_FILENAME) : dirname($found['plugin_file']));
            }
            if (!empty($found['text_domain'])) {
                $record['plugin_text_domain'] = $found['text_domain'];
            }
            $record['discovery_state'] = $found['active'] ? 'active' : 'inactive';
            $record['discovery_match'] = $found['match_strategy'];
            $record['discovered_active'] = $found['active'] ? '1' : '';
            $record['discovered_activation_scope'] = $found['activation_scope'];
            $record['discovered_plugin_name'] = $found['plugin_name'];
            $record['discovered_plugin_version'] = $found['version'];
            $record['discovered_plugin_version_raw'] = $found['version_raw'];
            $record['discovered_version_state'] = $found['version_state'];
            $record['discovered_text_domain'] = $found['text_domain'];
            $record['last_discovered_at'] = $scanned_at;
            $record['last_verified_at'] = $scanned_at;
            if (empty($record['discovery_locked'])) {
                if (in_array($found['version_state'], array('valid', 'development'), true)) {
                    $record['installed_version'] = $found['version'];
                    if (empty($record['public_version']) || $record['public_version'] === $previous_installed) {
                        $record['public_version'] = $found['version'];
                    }
                }
                if (in_array($record['status'] ?? 'unverified', array('', 'unverified', 'current', 'inactive'), true)) {
                    $record['status'] = $found['active'] ? 'current' : 'inactive';
                }
            }
        }
        unset($record);
        update_option(SCFS_Canonical_Product_Registry::OPTION_KEY, $registry_service->normalize_registry($registry), false);

        $diagnostics_record = $this->diagnostics_record($diagnostics, $scanned_at);
        $snapshot = array(
            'schema' => self::SCHEMA,
            'version' => self::VERSION,
            'scanned_at' => $scanned_at,
            'reason' => sanitize_key($reason),
            'installed_plugin_count' => count($plugins),
            'matched_product_count' => count($matches),
            'pending_candidate_count' => count($pending),
            'active_match_count' => $active_count,
            'inactive_match_count' => $inactive_count,
            'network_active_match_count' => $network_count,
            'diagnostic_count' => count($diagnostics_record['items']),
            'matches' => array_values($matches),
        );
        update_option(self::SNAPSHOT_OPTION, $snapshot, false);
        update_option(self::PENDING_OPTION, array_values($pending), false);
        update_option(self::DIAGNOSTICS_OPTION, $diagnostics_record, false);
        set_transient(self::CACHE_KEY, $snapshot, self::CACHE_TTL);
        $this->audit('plugin_discovery_completed', array(
            'reason' => sanitize_key($reason),
            'installed_plugin_count' => count($plugins),
            'matched_product_count' => count($matches),
            'pending_candidate_count' => count($pending),
            'diagnostic_count' => count($diagnostics_record['items']),
        ));
        do_action('scfs_installed_plugin_discovery_completed', $snapshot, $pending, $diagnostics_record);
        return $snapshot;
    }

    public function register_admin_page() {
        add_submenu_page(
            'edit.php?post_type=' . Sustainable_Catalyst_Feature_Suggestions::POST_TYPE,
            __('Installed Plugin Discovery', 'sustainable-catalyst-feature-suggestions'),
            __('Plugin Discovery', 'sustainable-catalyst-feature-suggestions'),
            $this->capability(),
            self::ADMIN_SLUG,
            array($this, 'render_admin_page')
        );
    }

    public function render_admin_page() {
        if (!$this->can_manage()) {
            wp_die(esc_html__('You do not have permission to manage plugin discovery.', 'sustainable-catalyst-feature-suggestions'));
        }
        $snapshot = $this->snapshot();
        $pending = $this->pending_candidates();
        $diagnostics = $this->diagnostics();
        echo '<div class="wrap"><h1>' . esc_html__('Installed Plugin Discovery', 'sustainable-catalyst-feature-suggestions') . '</h1>';
        echo '<p>' . esc_html__('Discovery reads installed WordPress plugin metadata, deterministically matches only approved registry products, and keeps unknown, malformed, or duplicate candidates in a private review queue.', 'sustainable-catalyst-feature-suggestions') . '</p>';
        if (isset($_GET['rescanned'])) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Installed plugins rescanned and registry evidence refreshed.', 'sustainable-catalyst-feature-suggestions') . '</p></div>';
        }
        echo '<div class="notice notice-info inline"><p><strong>' . esc_html(sprintf(__('%d approved products matched', 'sustainable-catalyst-feature-suggestions'), $snapshot['matched_product_count'])) . '</strong> · ' . esc_html(sprintf(__('%d active', 'sustainable-catalyst-feature-suggestions'), $snapshot['active_match_count'])) . ' · ' . esc_html(sprintf(__('%d inactive', 'sustainable-catalyst-feature-suggestions'), $snapshot['inactive_match_count'])) . ' · ' . esc_html(sprintf(__('%d network-active', 'sustainable-catalyst-feature-suggestions'), $snapshot['network_active_match_count'])) . ' · ' . esc_html(sprintf(__('%d pending review', 'sustainable-catalyst-feature-suggestions'), $snapshot['pending_candidate_count'])) . '</p></div>';
        echo '<p><a class="button button-primary" href="' . esc_url(wp_nonce_url(admin_url('admin-post.php?action=scfs_rescan_installed_plugins'), 'scfs_rescan_installed_plugins')) . '">' . esc_html__('Rescan installed plugins', 'sustainable-catalyst-feature-suggestions') . '</a> ';
        echo '<a class="button" href="' . esc_url(wp_nonce_url(admin_url('admin-post.php?action=scfs_clear_plugin_discovery'), 'scfs_clear_plugin_discovery')) . '">' . esc_html__('Clear cached snapshot', 'sustainable-catalyst-feature-suggestions') . '</a></p>';
        echo '<p><strong>' . esc_html__('Last scan:', 'sustainable-catalyst-feature-suggestions') . '</strong> ' . esc_html($snapshot['scanned_at'] ?: __('Not yet scanned', 'sustainable-catalyst-feature-suggestions')) . '</p>';
        echo '<h2>' . esc_html__('Matched products', 'sustainable-catalyst-feature-suggestions') . '</h2>';
        echo '<table class="widefat striped"><thead><tr><th>' . esc_html__('Product', 'sustainable-catalyst-feature-suggestions') . '</th><th>' . esc_html__('Installed plugin', 'sustainable-catalyst-feature-suggestions') . '</th><th>' . esc_html__('Version', 'sustainable-catalyst-feature-suggestions') . '</th><th>' . esc_html__('Activation', 'sustainable-catalyst-feature-suggestions') . '</th><th>' . esc_html__('Match', 'sustainable-catalyst-feature-suggestions') . '</th></tr></thead><tbody>';
        if (!$snapshot['matches']) {
            echo '<tr><td colspan="5">' . esc_html__('No approved products have been matched yet.', 'sustainable-catalyst-feature-suggestions') . '</td></tr>';
        } else {
            foreach ($snapshot['matches'] as $match) {
                $product = SCFS_Canonical_Product_Registry::instance()->get_product($match['product_id']);
                $version = $match['version'] !== '' ? $match['version'] : __('Missing', 'sustainable-catalyst-feature-suggestions');
                echo '<tr><td><strong>' . esc_html($product ? $product['name'] : $match['product_id']) . '</strong></td><td>' . esc_html($match['plugin_name']) . '<br><code>' . esc_html($match['plugin_file']) . '</code></td><td>' . esc_html($version) . '<br><small>' . esc_html($match['version_state']) . '</small></td><td>' . esc_html($match['activation_scope']) . '</td><td><code>' . esc_html($match['match_strategy']) . '</code> ' . esc_html($match['confidence']) . '%</td></tr>';
            }
        }
        echo '</tbody></table>';
        echo '<h2>' . esc_html__('Pending private review', 'sustainable-catalyst-feature-suggestions') . '</h2>';
        echo '<p class="description">' . esc_html__('These entries are not added to the public registry or release board automatically.', 'sustainable-catalyst-feature-suggestions') . '</p>';
        echo '<table class="widefat striped"><thead><tr><th>' . esc_html__('Plugin', 'sustainable-catalyst-feature-suggestions') . '</th><th>' . esc_html__('Version', 'sustainable-catalyst-feature-suggestions') . '</th><th>' . esc_html__('Activation', 'sustainable-catalyst-feature-suggestions') . '</th><th>' . esc_html__('Reason', 'sustainable-catalyst-feature-suggestions') . '</th></tr></thead><tbody>';
        if (!$pending) {
            echo '<tr><td colspan="4">' . esc_html__('No pending Catalyst plugin candidates.', 'sustainable-catalyst-feature-suggestions') . '</td></tr>';
        } else {
            foreach ($pending as $candidate) {
                echo '<tr><td><strong>' . esc_html($candidate['name']) . '</strong><br><code>' . esc_html($candidate['plugin_file']) . '</code></td><td>' . esc_html($candidate['version'] ?: $candidate['version_raw']) . '<br><small>' . esc_html($candidate['version_state']) . '</small></td><td>' . esc_html($candidate['activation_scope']) . '</td><td><code>' . esc_html($candidate['review_state']) . '</code></td></tr>';
            }
        }
        echo '</tbody></table>';
        echo '<h2>' . esc_html__('Discovery diagnostics', 'sustainable-catalyst-feature-suggestions') . '</h2>';
        echo '<p>' . esc_html(sprintf(__('%d errors · %d warnings · %d informational notices', 'sustainable-catalyst-feature-suggestions'), $diagnostics['error_count'], $diagnostics['warning_count'], $diagnostics['info_count'])) . '</p>';
        echo '<table class="widefat striped"><thead><tr><th>' . esc_html__('Level', 'sustainable-catalyst-feature-suggestions') . '</th><th>' . esc_html__('Code', 'sustainable-catalyst-feature-suggestions') . '</th><th>' . esc_html__('Product or plugin', 'sustainable-catalyst-feature-suggestions') . '</th><th>' . esc_html__('Message', 'sustainable-catalyst-feature-suggestions') . '</th></tr></thead><tbody>';
        if (empty($diagnostics['items'])) {
            echo '<tr><td colspan="4">' . esc_html__('No discovery diagnostics.', 'sustainable-catalyst-feature-suggestions') . '</td></tr>';
        } else {
            foreach ($diagnostics['items'] as $item) {
                $subject = $item['product_id'] ?: $item['plugin_file'];
                echo '<tr><td>' . esc_html($item['level']) . '</td><td><code>' . esc_html($item['code']) . '</code></td><td>' . esc_html($subject) . '</td><td>' . esc_html($item['message']) . '</td></tr>';
            }
        }
        echo '</tbody></table></div>';
    }

    public function rescan_action() {
        if (!$this->can_manage()) {
            wp_die('Forbidden');
        }
        check_admin_referer('scfs_rescan_installed_plugins');
        $this->refresh(true, 'admin_rescan');
        wp_safe_redirect(admin_url('edit.php?post_type=' . Sustainable_Catalyst_Feature_Suggestions::POST_TYPE . '&page=' . self::ADMIN_SLUG . '&rescanned=1'));
        exit;
    }

    public function clear_action() {
        if (!$this->can_manage()) {
            wp_die('Forbidden');
        }
        check_admin_referer('scfs_clear_plugin_discovery');
        $this->invalidate_cache('admin_clear');
        update_option(self::SNAPSHOT_OPTION, $this->empty_snapshot(), false);
        update_option(self::PENDING_OPTION, array(), false);
        update_option(self::DIAGNOSTICS_OPTION, $this->empty_diagnostics(), false);
        $this->audit('plugin_discovery_cleared');
        wp_safe_redirect(admin_url('edit.php?post_type=' . Sustainable_Catalyst_Feature_Suggestions::POST_TYPE . '&page=' . self::ADMIN_SLUG));
        exit;
    }

    public function register_rest_routes() {
        register_rest_route(Sustainable_Catalyst_Feature_Suggestions::REST_NAMESPACE, '/product-registry/discovery', array(
            'methods' => 'GET',
            'callback' => array($this, 'rest_status'),
            'permission_callback' => array($this, 'rest_permission'),
        ));
        register_rest_route(Sustainable_Catalyst_Feature_Suggestions::REST_NAMESPACE, '/product-registry/discovery/rescan', array(
            'methods' => 'POST',
            'callback' => array($this, 'rest_rescan'),
            'permission_callback' => array($this, 'rest_permission'),
        ));
        register_rest_route(Sustainable_Catalyst_Feature_Suggestions::REST_NAMESPACE, '/product-registry/discovery/diagnostics', array(
            'methods' => 'GET',
            'callback' => array($this, 'rest_diagnostics'),
            'permission_callback' => array($this, 'rest_permission'),
        ));
    }

    public function rest_permission() {
        return current_user_can($this->capability());
    }

    public function rest_status() {
        return rest_ensure_response(array(
            'ok' => true,
            'schema' => $this->schema_record(),
            'snapshot' => $this->snapshot(),
            'pending' => $this->pending_candidates(),
            'diagnostics' => $this->diagnostics(),
        ));
    }

    public function rest_rescan() {
        return rest_ensure_response(array(
            'ok' => true,
            'snapshot' => $this->refresh(true, 'rest_rescan'),
            'diagnostics' => $this->diagnostics(),
        ));
    }

    public function rest_diagnostics() {
        return rest_ensure_response(array('ok' => true, 'diagnostics' => $this->diagnostics()));
    }

    public function cli_discover($args, $assoc_args) {
        $snapshot = $this->refresh(true, 'cli_rescan');
        WP_CLI::success(sprintf('Plugin discovery matched %d approved products; %d candidates require review; %d diagnostics recorded.', $snapshot['matched_product_count'], $snapshot['pending_candidate_count'], $snapshot['diagnostic_count']));
        if (!empty($assoc_args['format']) && $assoc_args['format'] === 'json') {
            WP_CLI::line(wp_json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }
    }

    public function cli_status($args, $assoc_args) {
        WP_CLI::line(wp_json_encode(array(
            'schema' => $this->schema_record(),
            'summary' => $this->summary_record(),
            'matches' => $this->snapshot()['matches'],
            'pending' => $this->pending_candidates(),
            'diagnostics' => $this->diagnostics(),
        ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    public function cli_diagnostics($args, $assoc_args) {
        WP_CLI::line(wp_json_encode($this->diagnostics(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
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
