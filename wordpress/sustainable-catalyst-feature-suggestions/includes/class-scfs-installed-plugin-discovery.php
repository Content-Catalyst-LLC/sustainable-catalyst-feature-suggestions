<?php
/**
 * Installed Plugin Discovery.
 *
 * Safely reconciles approved Sustainable Catalyst product records with the
 * WordPress plugins actually installed on the site. Unknown plugins remain in
 * an administrator-only review queue and are never published automatically.
 *
 * @package Sustainable_Catalyst_Feature_Suggestions
 */

if (!defined('ABSPATH')) {
    exit;
}

final class SCFS_Installed_Plugin_Discovery {
    const VERSION = '7.2.0';
    const SCHEMA = 'scfs-installed-plugin-discovery/1.0';
    const SCHEMA_OPTION = 'scfs_installed_plugin_discovery_schema';
    const SNAPSHOT_OPTION = 'scfs_installed_plugin_discovery_snapshot';
    const PENDING_OPTION = 'scfs_installed_plugin_discovery_pending';
    const AUDIT_OPTION = 'scfs_installed_plugin_discovery_audit';
    const CACHE_KEY = 'scfs_installed_plugin_discovery_cache';
    const ADMIN_SLUG = 'scfs-plugin-discovery';
    const MAX_AUDIT_ENTRIES = 250;
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
        if (!get_option(self::AUDIT_OPTION)) {
            add_option(self::AUDIT_OPTION, array(), '', false);
        }
        self::instance()->invalidate_cache('activation');
    }

    public function maybe_upgrade() {
        if (get_option(self::SCHEMA_OPTION) === self::SCHEMA) {
            return;
        }
        update_option(self::SCHEMA_OPTION, self::SCHEMA, false);
        if (!is_array(get_option(self::SNAPSHOT_OPTION))) {
            update_option(self::SNAPSHOT_OPTION, $this->empty_snapshot(), false);
        }
        if (!is_array(get_option(self::PENDING_OPTION))) {
            update_option(self::PENDING_OPTION, array(), false);
        }
        $this->invalidate_cache('schema_upgrade');
        $this->audit('discovery_schema_upgraded', array('schema' => self::SCHEMA));
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
            'version' => self::VERSION,
            'source' => 'installed_wordpress_plugins',
            'registry_authority' => 'scfs_canonical_product_registry',
            'matching_hierarchy' => array('exact_plugin_file', 'sc_product_id_header', 'plugin_slug', 'text_domain', 'approved_name_alias'),
            'approved_registry_only' => true,
            'unknown_plugins_auto_registered' => false,
            'unknown_plugins_publicly_exposed' => false,
            'absolute_plugin_paths_publicly_exposed' => false,
            'automatic_publication' => false,
            'manual_overrides_preserved' => true,
            'product_lock_supported' => true,
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
            'matches' => array(),
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

    public function summary_record() {
        $snapshot = $this->snapshot();
        return array(
            'schema' => self::SCHEMA,
            'version' => self::VERSION,
            'scanned_at' => $snapshot['scanned_at'],
            'installed_plugin_count' => absint($snapshot['installed_plugin_count']),
            'matched_product_count' => absint($snapshot['matched_product_count']),
            'pending_candidate_count' => absint($snapshot['pending_candidate_count']),
            'active_match_count' => absint($snapshot['active_match_count']),
            'inactive_match_count' => absint($snapshot['inactive_match_count']),
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
        $this->refresh(true, 'plugin_state_changed');
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

    private function active_plugin_files() {
        $active = array_map(array($this, 'normalize_plugin_file'), (array) get_option('active_plugins', array()));
        if (is_multisite()) {
            $network = (array) get_site_option('active_sitewide_plugins', array());
            $active = array_merge($active, array_map(array($this, 'normalize_plugin_file'), array_keys($network)));
        }
        return array_fill_keys(array_unique($active), true);
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

    private function installed_plugins() {
        if (!$this->load_plugin_api()) {
            return array();
        }
        $active = $this->active_plugin_files();
        $plugins = array();
        foreach ((array) get_plugins() as $plugin_file => $metadata) {
            $plugin_file = $this->normalize_plugin_file($plugin_file);
            $custom = $this->custom_headers($plugin_file);
            $plugins[$plugin_file] = array(
                'plugin_file' => $plugin_file,
                'directory_slug' => sanitize_key(dirname($plugin_file) === '.' ? pathinfo($plugin_file, PATHINFO_FILENAME) : dirname($plugin_file)),
                'name' => sanitize_text_field($metadata['Name'] ?? ''),
                'version' => sanitize_text_field($metadata['Version'] ?? ''),
                'author' => sanitize_text_field(wp_strip_all_tags($metadata['AuthorName'] ?? ($metadata['Author'] ?? ''))),
                'text_domain' => sanitize_key($metadata['TextDomain'] ?? ''),
                'active' => isset($active[$plugin_file]),
                'sc_product_id' => sanitize_key($custom['SCProductID'] ?? ''),
                'sc_product' => sanitize_text_field($custom['SCProduct'] ?? ''),
                'sc_public_release' => sanitize_text_field($custom['SCPublicRelease'] ?? ''),
            );
        }
        ksort($plugins, SORT_NATURAL | SORT_FLAG_CASE);
        return $plugins;
    }

    private function normalize_name($name) {
        $name = strtolower(wp_strip_all_tags((string) $name));
        return preg_replace('/[^a-z0-9]+/', '', $name);
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
                return array('product_id' => $product_id, 'strategy' => 'exact_plugin_file', 'confidence' => 100);
            }
        }
        if (!empty($plugin['sc_product_id']) && isset($registry[$plugin['sc_product_id']]) && ($registry[$plugin['sc_product_id']]['version_source'] ?? '') === 'wordpress_plugin' && !empty($registry[$plugin['sc_product_id']]['discovery_enabled'])) {
            return array('product_id' => $plugin['sc_product_id'], 'strategy' => 'sc_product_id_header', 'confidence' => 98);
        }
        foreach ($registry as $product_id => $record) {
            if (($record['version_source'] ?? '') !== 'wordpress_plugin' || empty($record['discovery_enabled'])) {
                continue;
            }
            $slug = sanitize_key($record['plugin_slug'] ?? '');
            if ($slug && hash_equals($slug, $plugin['directory_slug'])) {
                return array('product_id' => $product_id, 'strategy' => 'plugin_slug', 'confidence' => 95);
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
                return array('product_id' => $product_id, 'strategy' => 'text_domain', 'confidence' => 90);
            }
        }
        $approved_author = stripos($plugin['author'], 'Content Catalyst') !== false;
        if ($approved_author) {
            $plugin_name = $this->normalize_name($plugin['name']);
            foreach ($registry as $product_id => $record) {
                if (($record['version_source'] ?? '') !== 'wordpress_plugin' || empty($record['discovery_enabled'])) {
                    continue;
                }
                if ($plugin_name && in_array($plugin_name, $this->approved_aliases($record), true)) {
                    return array('product_id' => $product_id, 'strategy' => 'approved_name_alias', 'confidence' => 80);
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
        $matches = array();
        $pending = array();
        $matched_products = array();
        $active_count = 0;
        $inactive_count = 0;
        $scanned_at = gmdate('c');

        foreach ($plugins as $plugin) {
            $match = $this->match_plugin($plugin, $registry);
            if (!$match) {
                if ($this->catalyst_candidate($plugin)) {
                    $pending[] = array(
                        'plugin_file' => $plugin['plugin_file'],
                        'name' => $plugin['name'],
                        'version' => $plugin['version'],
                        'author' => $plugin['author'],
                        'text_domain' => $plugin['text_domain'],
                        'active' => (bool) $plugin['active'],
                        'suggested_product_id' => $plugin['sc_product_id'],
                        'review_state' => 'pending',
                    );
                }
                continue;
            }
            $product_id = $match['product_id'];
            if (isset($matched_products[$product_id])) {
                $pending[] = array(
                    'plugin_file' => $plugin['plugin_file'],
                    'name' => $plugin['name'],
                    'version' => $plugin['version'],
                    'author' => $plugin['author'],
                    'text_domain' => $plugin['text_domain'],
                    'active' => (bool) $plugin['active'],
                    'suggested_product_id' => $product_id,
                    'review_state' => 'duplicate_match',
                );
                continue;
            }
            $matched_products[$product_id] = true;
            $plugin['active'] ? $active_count++ : $inactive_count++;
            $matches[$product_id] = array(
                'product_id' => $product_id,
                'plugin_file' => $plugin['plugin_file'],
                'plugin_name' => $plugin['name'],
                'version' => $plugin['version'],
                'text_domain' => $plugin['text_domain'],
                'active' => (bool) $plugin['active'],
                'match_strategy' => $match['strategy'],
                'confidence' => absint($match['confidence']),
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
            $record['discovered_plugin_name'] = $found['plugin_name'];
            $record['discovered_plugin_version'] = $found['version'];
            $record['discovered_text_domain'] = $found['text_domain'];
            $record['last_discovered_at'] = $scanned_at;
            $record['last_verified_at'] = $scanned_at;
            if (empty($record['discovery_locked'])) {
                $record['installed_version'] = $found['version'];
                if (empty($record['public_version']) || $record['public_version'] === $previous_installed) {
                    $record['public_version'] = $found['version'];
                }
                $record['status'] = $found['active'] ? 'current' : 'inactive';
            }
        }
        unset($record);
        update_option(SCFS_Canonical_Product_Registry::OPTION_KEY, $registry_service->normalize_registry($registry), false);

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
            'matches' => array_values($matches),
        );
        update_option(self::SNAPSHOT_OPTION, $snapshot, false);
        update_option(self::PENDING_OPTION, array_values($pending), false);
        set_transient(self::CACHE_KEY, $snapshot, self::CACHE_TTL);
        $this->audit('plugin_discovery_completed', array(
            'reason' => sanitize_key($reason),
            'installed_plugin_count' => count($plugins),
            'matched_product_count' => count($matches),
            'pending_candidate_count' => count($pending),
        ));
        do_action('scfs_installed_plugin_discovery_completed', $snapshot, $pending);
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
        echo '<div class="wrap"><h1>' . esc_html__('Installed Plugin Discovery', 'sustainable-catalyst-feature-suggestions') . '</h1>';
        echo '<p>' . esc_html__('Discovery reads installed WordPress plugin metadata, matches only approved registry products, and keeps unknown Catalyst-looking plugins in a private review queue.', 'sustainable-catalyst-feature-suggestions') . '</p>';
        if (isset($_GET['rescanned'])) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Installed plugins rescanned and registry evidence refreshed.', 'sustainable-catalyst-feature-suggestions') . '</p></div>';
        }
        echo '<div class="notice notice-info inline"><p><strong>' . esc_html(sprintf(__('%d approved products matched', 'sustainable-catalyst-feature-suggestions'), $snapshot['matched_product_count'])) . '</strong> · ' . esc_html(sprintf(__('%d active', 'sustainable-catalyst-feature-suggestions'), $snapshot['active_match_count'])) . ' · ' . esc_html(sprintf(__('%d inactive', 'sustainable-catalyst-feature-suggestions'), $snapshot['inactive_match_count'])) . ' · ' . esc_html(sprintf(__('%d pending review', 'sustainable-catalyst-feature-suggestions'), $snapshot['pending_candidate_count'])) . '</p></div>';
        echo '<p><a class="button button-primary" href="' . esc_url(wp_nonce_url(admin_url('admin-post.php?action=scfs_rescan_installed_plugins'), 'scfs_rescan_installed_plugins')) . '">' . esc_html__('Rescan installed plugins', 'sustainable-catalyst-feature-suggestions') . '</a> ';
        echo '<a class="button" href="' . esc_url(wp_nonce_url(admin_url('admin-post.php?action=scfs_clear_plugin_discovery'), 'scfs_clear_plugin_discovery')) . '">' . esc_html__('Clear cached snapshot', 'sustainable-catalyst-feature-suggestions') . '</a></p>';
        echo '<p><strong>' . esc_html__('Last scan:', 'sustainable-catalyst-feature-suggestions') . '</strong> ' . esc_html($snapshot['scanned_at'] ?: __('Not yet scanned', 'sustainable-catalyst-feature-suggestions')) . '</p>';
        echo '<h2>' . esc_html__('Matched products', 'sustainable-catalyst-feature-suggestions') . '</h2>';
        echo '<table class="widefat striped"><thead><tr><th>' . esc_html__('Product', 'sustainable-catalyst-feature-suggestions') . '</th><th>' . esc_html__('Installed plugin', 'sustainable-catalyst-feature-suggestions') . '</th><th>' . esc_html__('Version', 'sustainable-catalyst-feature-suggestions') . '</th><th>' . esc_html__('State', 'sustainable-catalyst-feature-suggestions') . '</th><th>' . esc_html__('Match', 'sustainable-catalyst-feature-suggestions') . '</th></tr></thead><tbody>';
        if (!$snapshot['matches']) {
            echo '<tr><td colspan="5">' . esc_html__('No approved products have been matched yet.', 'sustainable-catalyst-feature-suggestions') . '</td></tr>';
        } else {
            foreach ($snapshot['matches'] as $match) {
                $product = SCFS_Canonical_Product_Registry::instance()->get_product($match['product_id']);
                echo '<tr><td><strong>' . esc_html($product ? $product['name'] : $match['product_id']) . '</strong></td><td>' . esc_html($match['plugin_name']) . '<br><code>' . esc_html($match['plugin_file']) . '</code></td><td>' . esc_html($match['version']) . '</td><td>' . esc_html($match['active'] ? __('Active', 'sustainable-catalyst-feature-suggestions') : __('Inactive', 'sustainable-catalyst-feature-suggestions')) . '</td><td><code>' . esc_html($match['match_strategy']) . '</code> ' . esc_html($match['confidence']) . '%</td></tr>';
            }
        }
        echo '</tbody></table>';
        echo '<h2>' . esc_html__('Pending private review', 'sustainable-catalyst-feature-suggestions') . '</h2>';
        echo '<p class="description">' . esc_html__('These entries are not added to the public registry or release board automatically.', 'sustainable-catalyst-feature-suggestions') . '</p>';
        echo '<table class="widefat striped"><thead><tr><th>' . esc_html__('Plugin', 'sustainable-catalyst-feature-suggestions') . '</th><th>' . esc_html__('Version', 'sustainable-catalyst-feature-suggestions') . '</th><th>' . esc_html__('State', 'sustainable-catalyst-feature-suggestions') . '</th><th>' . esc_html__('Reason', 'sustainable-catalyst-feature-suggestions') . '</th></tr></thead><tbody>';
        if (!$pending) {
            echo '<tr><td colspan="4">' . esc_html__('No pending Catalyst plugin candidates.', 'sustainable-catalyst-feature-suggestions') . '</td></tr>';
        } else {
            foreach ($pending as $candidate) {
                echo '<tr><td><strong>' . esc_html($candidate['name']) . '</strong><br><code>' . esc_html($candidate['plugin_file']) . '</code></td><td>' . esc_html($candidate['version']) . '</td><td>' . esc_html($candidate['active'] ? __('Active', 'sustainable-catalyst-feature-suggestions') : __('Inactive', 'sustainable-catalyst-feature-suggestions')) . '</td><td><code>' . esc_html($candidate['review_state']) . '</code></td></tr>';
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
        ));
    }

    public function rest_rescan() {
        return rest_ensure_response(array('ok' => true, 'snapshot' => $this->refresh(true, 'rest_rescan')));
    }

    public function cli_discover($args, $assoc_args) {
        $snapshot = $this->refresh(true, 'cli_rescan');
        WP_CLI::success(sprintf('Plugin discovery matched %d approved products; %d candidates require review.', $snapshot['matched_product_count'], $snapshot['pending_candidate_count']));
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
        ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
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
