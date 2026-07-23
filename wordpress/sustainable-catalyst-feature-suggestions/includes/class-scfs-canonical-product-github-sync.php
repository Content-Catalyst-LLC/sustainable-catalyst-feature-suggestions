<?php
/**
 * Canonical Product GitHub Synchronization.
 *
 * Connects canonical console products to GitHub repositories and keeps public
 * release intelligence current through signed webhooks with hourly polling as
 * a fallback. Credentials may be supplied through wp-config.php, environment
 * variables, or the encrypted administrator GitHub Connection screen.
 *
 * @package Sustainable_Catalyst_Feature_Suggestions
 */

if (!defined('ABSPATH')) {
    exit;
}

final class SCFS_Canonical_Product_GitHub_Sync {
    const VERSION = '7.6.0';
    const SCHEMA = 'scfs-canonical-product-github-sync/1.0';
    const CRON_HOOK = 'scfs_canonical_product_github_sync';
    const AUDIT_OPTION = 'scfs_canonical_product_github_sync_audit';
    const LAST_RUN_OPTION = 'scfs_canonical_product_github_sync_last_run';
    const MAX_AUDIT_ENTRIES = 250;

    private static $instance = null;

    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action(self::CRON_HOOK, array($this, 'sync_all'));
        add_action('rest_api_init', array($this, 'register_rest_routes'));
        add_action('scfs_canonical_product_connection_saved', array($this, 'connection_saved'), 10, 1);
        add_action('admin_post_scfs_sync_canonical_github', array($this, 'admin_sync_action'));
        add_action('admin_init', array($this, 'ensure_schedule'));
        if (defined('WP_CLI') && WP_CLI && class_exists('WP_CLI')) {
            WP_CLI::add_command('scfs products github-sync', array($this, 'cli_sync'));
        }
    }

    public static function activate() {
        if (!get_option(self::AUDIT_OPTION)) {
            add_option(self::AUDIT_OPTION, array(), '', false);
        }
        if (!get_option(self::LAST_RUN_OPTION)) {
            add_option(self::LAST_RUN_OPTION, array(), '', false);
        }
        if (function_exists('wp_next_scheduled') && function_exists('wp_schedule_event') && !wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'hourly', self::CRON_HOOK);
        }
    }

    public function ensure_schedule() {
        if (function_exists('wp_next_scheduled') && function_exists('wp_schedule_event') && !wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'hourly', self::CRON_HOOK);
        }
    }

    public function next_scheduled_sync() {
        return function_exists('wp_next_scheduled') ? wp_next_scheduled(self::CRON_HOOK) : false;
    }

    public static function deactivate() {
        if (!function_exists('wp_next_scheduled')) {
            return;
        }
        $timestamp = wp_next_scheduled(self::CRON_HOOK);
        if ($timestamp && function_exists('wp_unschedule_event')) {
            wp_unschedule_event($timestamp, self::CRON_HOOK);
        }
    }

    public function schema_record() {
        return array(
            'schema' => self::SCHEMA,
            'version' => self::VERSION,
            'identity_authority' => 'scfs_canonical_product_registry',
            'plugin_connection_source' => 'scfs_installed_plugin_discovery_mappings',
            'repository_provider' => 'github',
            'signed_webhook_supported' => true,
            'hourly_polling_fallback' => true,
            'hourly_schedule_self_repair' => true,
            'semantic_version_tag_fallback' => true,
            'version_authority_order' => array('github_release', 'github_tag', 'existing_registry_version'),
            'private_repository_token_constant' => 'SCFS_GITHUB_TOKEN',
            'webhook_secret_constant' => 'SCFS_GITHUB_WEBHOOK_SECRET',
            'administrator_connection_screen' => 'scfs-github-connection',
            'encrypted_wordpress_credentials_supported' => true,
            'automatic_console_refresh' => true,
            'automatic_publication' => false,
            'repository_credentials_stored' => 'encrypted_optional',
        );
    }

    public function parse_repository_url($url) {
        $url = trim((string) $url);
        if ($url === '') {
            return null;
        }
        $parts = wp_parse_url($url);
        if (!is_array($parts) || strtolower($parts['host'] ?? '') !== 'github.com') {
            return null;
        }
        $segments = array_values(array_filter(explode('/', trim($parts['path'] ?? '', '/'))));
        if (count($segments) < 2) {
            return null;
        }
        $owner = preg_replace('/[^A-Za-z0-9_.-]/', '', $segments[0]);
        $repository = preg_replace('/\.git$/i', '', preg_replace('/[^A-Za-z0-9_.-]/', '', $segments[1]));
        if ($owner === '' || $repository === '') {
            return null;
        }
        return array(
            'owner' => $owner,
            'repository' => $repository,
            'canonical_url' => 'https://github.com/' . $owner . '/' . $repository,
            'api_base' => 'https://api.github.com/repos/' . rawurlencode($owner) . '/' . rawurlencode($repository),
        );
    }

    private function github_token() {
        if (class_exists('SCFS_GitHub_Connection_Settings')) {
            return SCFS_GitHub_Connection_Settings::instance()->token();
        }
        if (defined('SCFS_GITHUB_TOKEN') && SCFS_GITHUB_TOKEN) {
            return trim((string) SCFS_GITHUB_TOKEN);
        }
        $value = getenv('SCFS_GITHUB_TOKEN');
        return is_string($value) ? trim($value) : '';
    }

    private function webhook_secret() {
        if (class_exists('SCFS_GitHub_Connection_Settings')) {
            return SCFS_GitHub_Connection_Settings::instance()->webhook_secret();
        }
        if (defined('SCFS_GITHUB_WEBHOOK_SECRET') && SCFS_GITHUB_WEBHOOK_SECRET) {
            return (string) SCFS_GITHUB_WEBHOOK_SECRET;
        }
        $value = getenv('SCFS_GITHUB_WEBHOOK_SECRET');
        return is_string($value) ? trim($value) : '';
    }

    private function headers() {
        $headers = array(
            'Accept' => 'application/vnd.github+json',
            'User-Agent' => 'Sustainable-Catalyst-Product-Support/' . self::VERSION,
            'X-GitHub-Api-Version' => '2022-11-28',
        );
        $token = $this->github_token();
        if ($token !== '') {
            $headers['Authorization'] = 'Bearer ' . $token;
        }
        return $headers;
    }

    private function request($url) {
        $response = wp_remote_get($url, array(
            'timeout' => 15,
            'redirection' => 3,
            'headers' => $this->headers(),
        ));
        if (is_wp_error($response)) {
            return $response;
        }
        $code = (int) wp_remote_retrieve_response_code($response);
        $body = (string) wp_remote_retrieve_body($response);
        if ($code < 200 || $code >= 300) {
            $decoded = json_decode($body, true);
            $message = is_array($decoded) && !empty($decoded['message']) ? sanitize_text_field($decoded['message']) : __('GitHub request failed.', 'sustainable-catalyst-feature-suggestions');
            $message = sprintf(__('GitHub returned HTTP %1$d: %2$s', 'sustainable-catalyst-feature-suggestions'), $code, $message);
            return new WP_Error('scfs_canonical_github_request_failed', $message, array('status' => $code));
        }
        $decoded = json_decode($body, true);
        return is_array($decoded) ? $decoded : new WP_Error('scfs_canonical_github_invalid_json', __('GitHub returned invalid JSON.', 'sustainable-catalyst-feature-suggestions'));
    }


    public function diagnose_repository($url) {
        $repository = $this->parse_repository_url($url);
        if (!$repository) {
            return new WP_Error('scfs_canonical_github_repository_invalid', __('Use a valid GitHub repository URL.', 'sustainable-catalyst-feature-suggestions'));
        }
        $metadata = $this->request($repository['api_base']);
        if (is_wp_error($metadata)) {
            return $metadata;
        }
        $branch = sanitize_text_field($metadata['default_branch'] ?? 'main');
        $releases = $this->request($repository['api_base'] . '/releases?per_page=1');
        if (is_wp_error($releases)) {
            return $releases;
        }
        $has_release = isset($releases[0]) && is_array($releases[0]) && empty($releases[0]['draft']);
        $tag = array();
        if (!$has_release) {
            $tag = $this->latest_semantic_tag($repository);
            if (is_wp_error($tag)) {
                return $tag;
            }
        }
        $has_tag = !empty($tag['version']);
        $latest_version = $has_release ? $this->normalize_version($releases[0]['tag_name'] ?? '') : ($tag['version'] ?? '');
        $source = $has_release ? 'github_release' : ($has_tag ? 'github_tag' : 'none');
        return array(
            'ok' => true,
            'repository' => $repository['canonical_url'],
            'private' => !empty($metadata['private']),
            'default_branch' => $branch,
            'has_release' => $has_release,
            'has_semantic_tag' => $has_tag,
            'version_source' => $source,
            'latest_version' => $latest_version,
            'message' => $has_release
                ? sprintf(__('Accessible; latest published release %s.', 'sustainable-catalyst-feature-suggestions'), $latest_version)
                : ($has_tag
                    ? sprintf(__('Accessible; no GitHub Release is published. Console version can use tag %s.', 'sustainable-catalyst-feature-suggestions'), $tag['name'])
                    : __('Accessible; no GitHub Release or semantic version tag is published yet.', 'sustainable-catalyst-feature-suggestions')),
        );
    }

    private function semantic_version($tag) {
        $normalized = $this->normalize_version($tag);
        if (!preg_match('/^\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$/', $normalized)) {
            return '';
        }
        return $normalized;
    }

    private function latest_semantic_tag($repository) {
        $tags = $this->request($repository['api_base'] . '/tags?per_page=100');
        if (is_wp_error($tags)) {
            return $tags;
        }
        $best = array();
        foreach ((array) $tags as $tag) {
            if (!is_array($tag)) {
                continue;
            }
            $name = sanitize_text_field($tag['name'] ?? '');
            $version = $this->semantic_version($name);
            if ($version === '') {
                continue;
            }
            if (!$best || version_compare($version, $best['version'], '>')) {
                $best = array(
                    'name' => $name,
                    'version' => $version,
                    'url' => $repository['canonical_url'] . '/tree/' . rawurlencode($name),
                    'commit_sha' => sanitize_text_field($tag['commit']['sha'] ?? ''),
                );
            }
        }
        return $best;
    }

    private function normalize_version($tag) {
        $tag = trim(sanitize_text_field((string) $tag));
        return preg_replace('/^v(?=\d)/i', '', $tag);
    }

    private function release_summary($release) {
        $name = trim(sanitize_text_field($release['name'] ?? ''));
        if ($name !== '') {
            return function_exists('mb_substr') ? mb_substr($name, 0, 240) : substr($name, 0, 240);
        }
        $body = wp_strip_all_tags((string) ($release['body'] ?? ''));
        $body = trim(preg_replace('/\s+/', ' ', $body));
        return function_exists('mb_substr') ? mb_substr($body, 0, 240) : substr($body, 0, 240);
    }

    private function save_error($product_id, $message) {
        $service = SCFS_Canonical_Product_Registry::instance();
        $registry = $service->registry();
        if (!isset($registry[$product_id])) {
            return;
        }
        $registry[$product_id]['github_sync_state'] = 'error';
        $registry[$product_id]['github_sync_message'] = sanitize_text_field($message);
        $registry[$product_id]['github_last_synced_at'] = gmdate('c');
        $registry[$product_id]['record_updated_at'] = gmdate('c');
        update_option(SCFS_Canonical_Product_Registry::OPTION_KEY, $service->normalize_registry($registry), false);
        do_action('scfs_product_registry_updated', $registry);
    }

    public function sync_product($product_id, $reason = 'manual') {
        $product_id = sanitize_key($product_id);
        $service = SCFS_Canonical_Product_Registry::instance();
        $registry = $service->registry();
        if (!isset($registry[$product_id])) {
            return new WP_Error('scfs_canonical_product_missing', __('Canonical product not found.', 'sustainable-catalyst-feature-suggestions'));
        }
        $record = $registry[$product_id];
        if (empty($record['github_sync_enabled'])) {
            return new WP_Error('scfs_canonical_github_sync_disabled', __('GitHub synchronization is disabled for this product.', 'sustainable-catalyst-feature-suggestions'));
        }
        $repository = $this->parse_repository_url($record['github_repository_url'] ?? '');
        if (!$repository) {
            return new WP_Error('scfs_canonical_github_repository_missing', __('Map a valid GitHub repository before synchronizing.', 'sustainable-catalyst-feature-suggestions'));
        }

        $metadata = $this->request($repository['api_base']);
        if (is_wp_error($metadata)) {
            $this->save_error($product_id, $metadata->get_error_message());
            return $metadata;
        }
        $branch = sanitize_text_field($metadata['default_branch'] ?? ($record['github_default_branch'] ?? 'main'));
        $releases = $this->request($repository['api_base'] . '/releases?per_page=1');
        if (is_wp_error($releases)) {
            $this->save_error($product_id, $releases->get_error_message());
            return $releases;
        }
        $release = isset($releases[0]) && is_array($releases[0]) && empty($releases[0]['draft']) ? $releases[0] : array();
        $tag = array();
        if (!$release) {
            $tag = $this->latest_semantic_tag($repository);
            if (is_wp_error($tag)) {
                $this->save_error($product_id, $tag->get_error_message());
                return $tag;
            }
        }
        $commit = $this->request($repository['api_base'] . '/commits/' . rawurlencode($branch));
        $commit_sha = is_wp_error($commit) ? sanitize_text_field($tag['commit_sha'] ?? '') : sanitize_text_field($commit['sha'] ?? '');
        $now = gmdate('c');
        $latest_version = $release ? $this->normalize_version($release['tag_name'] ?? '') : sanitize_text_field($tag['version'] ?? '');
        $version_source = $release ? 'github_release' : ($latest_version !== '' ? 'github_tag' : 'none');
        $published_at = sanitize_text_field($release['published_at'] ?? '');
        $release_date = preg_match('/^\d{4}-\d{2}-\d{2}/', $published_at, $match) ? $match[0] : '';
        $previous = trim((string) ($record['github_latest_version'] ?? ''));

        $record['github_repository_url'] = $repository['canonical_url'];
        $record['repository_slug'] = sanitize_key($repository['repository']);
        $record['github_default_branch'] = $branch ?: 'main';
        $record['github_sync_state'] = 'current';
        if ($version_source === 'github_release') {
            $record['github_sync_message'] = '';
        } elseif ($version_source === 'github_tag') {
            $record['github_sync_message'] = sprintf(__('Repository connected. No GitHub Release is published; console version synchronized from tag %s.', 'sustainable-catalyst-feature-suggestions'), $tag['name']);
        } else {
            $record['github_sync_message'] = __('Repository connected. No GitHub Release or semantic version tag is published; repository and commit evidence were synchronized.', 'sustainable-catalyst-feature-suggestions');
        }
        $record['github_latest_version'] = $latest_version;
        $record['github_version_source'] = $version_source;
        $record['github_latest_release_name'] = sanitize_text_field($release['name'] ?? '');
        $record['github_latest_release_url'] = esc_url_raw($release['html_url'] ?? '');
        $record['github_latest_release_at'] = $published_at;
        $record['github_latest_tag_name'] = sanitize_text_field($tag['name'] ?? '');
        $record['github_latest_tag_url'] = esc_url_raw($tag['url'] ?? '');
        $record['github_latest_commit_sha'] = $commit_sha;
        $record['github_repository_updated_at'] = sanitize_text_field($metadata['pushed_at'] ?? ($metadata['updated_at'] ?? ''));
        $record['github_last_synced_at'] = $now;
        $record['verification_source'] = 'github';
        $record['source_verified_at'] = $now;
        $record['last_verified_at'] = $now;
        $record['record_updated_at'] = $now;

        if (empty($record['github_sync_locked']) && $latest_version !== '') {
            $installed = trim((string) ($record['installed_version'] ?? ''));
            if ($previous !== '' && $previous !== $latest_version) {
                $record['previous_version'] = $previous;
            } elseif ($previous === '' && $installed !== '' && $installed !== $latest_version) {
                $record['previous_version'] = $installed;
            }
            $record['public_version'] = $latest_version;
            $record['version_source'] = $version_source;
            $record['version_precedence'] = 'github';
            if ($version_source === 'github_release') {
                $record['release_date'] = $release_date;
                $record['change_summary'] = $this->release_summary($release);
                $record['release_notes_url'] = esc_url_raw($release['html_url'] ?? $repository['canonical_url']);
                $record['release_channel'] = !empty($release['prerelease']) ? 'preview' : 'stable';
            } else {
                $record['release_date'] = '';
                $record['change_summary'] = sprintf(__('Version tag %s synchronized from GitHub; no published GitHub Release is available.', 'sustainable-catalyst-feature-suggestions'), $tag['name']);
                $record['release_notes_url'] = esc_url_raw($tag['url'] ?? $repository['canonical_url']);
                $record['release_channel'] = preg_match('/(?:alpha|beta|rc|preview)/i', (string) ($tag['name'] ?? '')) ? 'preview' : 'stable';
            }
            if ($installed !== '' && version_compare($installed, $latest_version, '<')) {
                $record['status'] = 'update_available';
            } elseif (!empty($record['discovered_active'])) {
                $record['status'] = 'current';
            } else {
                $record['status'] = $record['release_channel'] === 'preview' ? 'preview' : 'stable';
            }
        }

        $registry[$product_id] = $record;
        $normalized = $service->normalize_registry($registry);
        update_option(SCFS_Canonical_Product_Registry::OPTION_KEY, $normalized, false);
        $this->audit('github_product_synchronized', array(
            'product_id' => $product_id,
            'repository_url' => $repository['canonical_url'],
            'version' => $latest_version,
            'version_source' => $version_source,
            'reason' => sanitize_key($reason),
        ));
        do_action('scfs_product_registry_updated', $normalized);
        do_action('scfs_canonical_product_github_synced', $product_id, $normalized[$product_id], $reason);
        return $normalized[$product_id];
    }

    public function sync_all($reason = 'scheduled_poll') {
        $results = array();
        foreach (SCFS_Canonical_Product_Registry::instance()->registry() as $product_id => $record) {
            if (empty($record['github_sync_enabled']) || empty($record['github_repository_url'])) {
                continue;
            }
            $result = $this->sync_product($product_id, $reason);
            $results[$product_id] = is_wp_error($result) ? array('ok' => false, 'message' => $result->get_error_message()) : array('ok' => true, 'version' => $result['github_latest_version'] ?? '', 'source' => $result['github_version_source'] ?? '');
        }
        update_option(self::LAST_RUN_OPTION, array('ran_at' => gmdate('c'), 'reason' => sanitize_key($reason), 'results' => $results), false);
        return $results;
    }

    public function connection_saved($product_id) {
        $record = SCFS_Canonical_Product_Registry::instance()->get_product($product_id);
        if ($record && !empty($record['github_sync_enabled']) && !empty($record['github_repository_url'])) {
            $this->sync_product($product_id, 'connection_saved');
        }
    }

    private function verify_webhook_signature($body, $signature) {
        $secret = $this->webhook_secret();
        if ($secret === '' || !is_string($signature) || strpos($signature, 'sha256=') !== 0) {
            return false;
        }
        return hash_equals('sha256=' . hash_hmac('sha256', $body, $secret), trim($signature));
    }

    public function register_rest_routes() {
        register_rest_route(Sustainable_Catalyst_Feature_Suggestions::REST_NAMESPACE, '/product-registry/github/webhook', array(
            'methods' => 'POST',
            'callback' => array($this, 'rest_webhook'),
            'permission_callback' => '__return_true',
        ));
        register_rest_route(Sustainable_Catalyst_Feature_Suggestions::REST_NAMESPACE, '/product-registry/github/sync', array(
            'methods' => 'POST',
            'callback' => array($this, 'rest_sync'),
            'permission_callback' => function () {
                return current_user_can('manage_options');
            },
            'args' => array('product_id' => array('required' => false, 'type' => 'string')),
        ));
    }

    public function rest_webhook($request) {
        $body = (string) $request->get_body();
        $signature = (string) $request->get_header('x-hub-signature-256');
        if (!$this->verify_webhook_signature($body, $signature)) {
            return new WP_Error('scfs_canonical_github_signature_invalid', __('Invalid GitHub webhook signature.', 'sustainable-catalyst-feature-suggestions'), array('status' => 401));
        }
        $payload = json_decode($body, true);
        $repository_url = esc_url_raw($payload['repository']['html_url'] ?? '');
        $parsed = $this->parse_repository_url($repository_url);
        if (!$parsed) {
            return new WP_Error('scfs_canonical_github_webhook_repository_missing', __('Webhook repository is missing or invalid.', 'sustainable-catalyst-feature-suggestions'), array('status' => 400));
        }
        $product_id = '';
        foreach (SCFS_Canonical_Product_Registry::instance()->registry() as $id => $record) {
            $candidate = $this->parse_repository_url($record['github_repository_url'] ?? '');
            if ($candidate && strtolower($candidate['canonical_url']) === strtolower($parsed['canonical_url'])) {
                $product_id = $id;
                break;
            }
        }
        if ($product_id === '') {
            return new WP_Error('scfs_canonical_github_webhook_unmapped', __('Webhook repository is not connected to a canonical product.', 'sustainable-catalyst-feature-suggestions'), array('status' => 404));
        }
        $event = sanitize_key((string) $request->get_header('x-github-event'));
        $result = $this->sync_product($product_id, 'webhook_' . ($event ?: 'unknown'));
        if (is_wp_error($result)) {
            $result->add_data(array('status' => 502));
            return $result;
        }
        return rest_ensure_response(array('ok' => true, 'product_id' => $product_id, 'event' => $event, 'version' => $result['github_latest_version'] ?? ''));
    }

    public function rest_sync($request) {
        $product_id = sanitize_key((string) $request->get_param('product_id'));
        $result = $product_id !== '' ? $this->sync_product($product_id, 'rest_manual') : $this->sync_all('rest_manual');
        return is_wp_error($result) ? $result : rest_ensure_response(array('ok' => true, 'result' => $result));
    }

    public function admin_sync_action() {
        if (!current_user_can('manage_options')) {
            wp_die('Forbidden');
        }
        check_admin_referer('scfs_sync_canonical_github');
        $product_id = sanitize_key(wp_unslash($_GET['product_id'] ?? ''));
        $result = $product_id !== '' ? $this->sync_product($product_id, 'admin_manual') : $this->sync_all('admin_manual');
        if (is_wp_error($result)) {
            set_transient('scfs_github_sync_error_' . get_current_user_id(), $result->get_error_message(), 5 * MINUTE_IN_SECONDS);
            $status = 'github_sync_error=1';
        } else {
            $status = 'github_synced=1';
        }
        wp_safe_redirect(admin_url('edit.php?post_type=' . Sustainable_Catalyst_Feature_Suggestions::POST_TYPE . '&page=' . SCFS_Installed_Plugin_Discovery::ADMIN_SLUG . '&' . $status));
        exit;
    }

    public function cli_sync($args, $assoc_args) {
        $product_id = sanitize_key($assoc_args['product'] ?? '');
        $result = $product_id !== '' ? $this->sync_product($product_id, 'cli') : $this->sync_all('cli');
        if (is_wp_error($result)) {
            WP_CLI::error($result->get_error_message());
        }
        WP_CLI::line(wp_json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    private function audit($action, $details = array()) {
        $log = (array) get_option(self::AUDIT_OPTION, array());
        $log[] = array('at' => gmdate('c'), 'action' => sanitize_key($action), 'details' => is_array($details) ? $details : array());
        update_option(self::AUDIT_OPTION, array_slice($log, -self::MAX_AUDIT_ENTRIES), false);
    }
}
