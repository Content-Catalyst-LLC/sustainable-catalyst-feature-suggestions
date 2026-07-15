<?php
/**
 * Repository and Release Synchronization.
 *
 * Maps Sustainable Catalyst products to public repositories, inspects GitHub
 * releases and documentation sources, creates approval-gated WordPress drafts,
 * detects documentation drift, validates repository links, and preserves a
 * bounded synchronization audit trail.
 *
 * @package Sustainable_Catalyst_Feature_Suggestions
 */

if (!defined('ABSPATH')) {
    exit;
}

final class SCFS_Repository_Release_Synchronization {
    const VERSION = '4.4.0';
    const SCHEMA_VERSION = '1.0';
    const OPTION_KEY = 'scfs_repository_sync_settings';
    const MAPPING_OPTION = 'scfs_repository_sync_mappings';
    const LOG_OPTION = 'scfs_repository_sync_log';
    const STATE_OPTION = 'scfs_repository_sync_state';
    const QUEUE_OPTION = 'scfs_repository_sync_webhook_queue';
    const SCHEMA_OPTION = 'scfs_repository_sync_schema_version';
    const CRON_HOOK = 'scfs_repository_sync_scheduled';
    const WEBHOOK_HOOK = 'scfs_repository_sync_process_webhook';
    const CRON_LOCK = 'scfs_repository_sync_lock';
    const ADMIN_SLUG = 'scfs-repository-release-sync';
    const MAX_LOG_ENTRIES = 300;
    const MAX_RELEASES = 30;
    const MAX_DOCUMENTS = 40;

    private static $instance = null;

    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('init', array($this, 'register_meta'), 11);
        add_action('admin_menu', array($this, 'register_admin_page'), 26);
        add_action('admin_enqueue_scripts', array($this, 'admin_assets'));
        add_action('admin_post_scfs_save_repository_mapping', array($this, 'save_mapping_action'));
        add_action('admin_post_scfs_save_repository_sync_settings', array($this, 'save_settings_action'));
        add_action('admin_post_scfs_preview_repository_sync', array($this, 'preview_sync_action'));
        add_action('admin_post_scfs_apply_repository_sync', array($this, 'apply_sync_action'));
        add_action('admin_post_scfs_check_repository_links', array($this, 'check_links_action'));
        add_action('admin_post_scfs_export_repository_sync_log', array($this, 'export_log_action'));
        add_action(self::CRON_HOOK, array($this, 'run_scheduled_sync'));
        add_action(self::WEBHOOK_HOOK, array($this, 'process_webhook_queue'));
        add_action('rest_api_init', array($this, 'register_rest_routes'));

        if (defined('WP_CLI') && WP_CLI && class_exists('WP_CLI')) {
            WP_CLI::add_command('scfs repository-preview', array($this, 'cli_preview'));
            WP_CLI::add_command('scfs repository-sync', array($this, 'cli_sync'));
            WP_CLI::add_command('scfs repository-links', array($this, 'cli_links'));
        }
    }

    public static function activate() {
        $instance = self::instance();
        $instance->register_meta();
        if (!get_option(self::OPTION_KEY)) {
            add_option(self::OPTION_KEY, $instance->default_settings(), '', false);
        }
        if (!get_option(self::MAPPING_OPTION)) {
            add_option(self::MAPPING_OPTION, array(), '', false);
        }
        if (!get_option(self::LOG_OPTION)) {
            add_option(self::LOG_OPTION, array(), '', false);
        }
        if (!get_option(self::STATE_OPTION)) {
            add_option(self::STATE_OPTION, array(), '', false);
        }
        if (!get_option(self::QUEUE_OPTION)) {
            add_option(self::QUEUE_OPTION, array(), '', false);
        }
        update_option(self::SCHEMA_OPTION, self::SCHEMA_VERSION, false);
        if (function_exists('wp_next_scheduled') && function_exists('wp_schedule_event') && !wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK);
        }
    }

    public static function deactivate() {
        if (!function_exists('wp_next_scheduled')) {
            return;
        }
        foreach (array(self::CRON_HOOK, self::WEBHOOK_HOOK) as $hook) {
            $timestamp = wp_next_scheduled($hook);
            if ($timestamp && function_exists('wp_unschedule_event')) {
                wp_unschedule_event($timestamp, $hook);
            }
        }
        if (function_exists('delete_transient')) {
            delete_transient(self::CRON_LOCK);
        }
    }

    public function default_settings() {
        return array(
            'provider' => 'github',
            'default_branch' => 'main',
            'request_timeout' => 15,
            'maximum_releases' => 20,
            'maximum_documents' => 20,
            'scheduled_inspection' => '1',
            'scheduled_draft_ingestion' => '',
            'create_status' => 'draft',
            'submit_for_editorial_review' => '1',
            'sync_readme' => '1',
            'sync_changelog' => '1',
            'sync_releases' => '1',
            'sync_documentation' => '1',
            'detect_drift' => '1',
            'check_links' => '1',
            'webhook_enabled' => '',
            'preserve_local_edits' => '1',
            'never_publish_automatically' => '1',
            'log_retention' => self::MAX_LOG_ENTRIES,
        );
    }

    public function settings() {
        $saved = function_exists('get_option') ? (array) get_option(self::OPTION_KEY, array()) : array();
        $settings = function_exists('wp_parse_args') ? wp_parse_args($saved, $this->default_settings()) : array_merge($this->default_settings(), $saved);
        $settings['request_timeout'] = max(5, min(30, absint($settings['request_timeout'])));
        $settings['maximum_releases'] = max(1, min(self::MAX_RELEASES, absint($settings['maximum_releases'])));
        $settings['maximum_documents'] = max(1, min(self::MAX_DOCUMENTS, absint($settings['maximum_documents'])));
        $settings['log_retention'] = max(50, min(self::MAX_LOG_ENTRIES, absint($settings['log_retention'])));
        return $settings;
    }

    public function admin_capability() {
        return apply_filters('scfs_repository_sync_capability', 'manage_options');
    }

    public function can_manage() {
        return current_user_can($this->admin_capability());
    }

    public function managed_post_types() {
        $types = array();
        if (class_exists('SCFS_Knowledge_Base_Foundation')) {
            $types[] = SCFS_Knowledge_Base_Foundation::ARTICLE_POST_TYPE;
            $types[] = SCFS_Knowledge_Base_Foundation::ISSUE_POST_TYPE;
        }
        if (class_exists('SCFS_Product_Support_Platform')) {
            $types[] = SCFS_Product_Support_Platform::RELEASE_POST_TYPE;
        }
        return array_values(array_unique(array_filter($types)));
    }

    public function register_meta() {
        $definitions = array(
            '_scfs_sync_provider' => array('type' => 'string', 'sanitize_callback' => 'sanitize_key', 'show_in_rest' => true),
            '_scfs_sync_repository_url' => array('type' => 'string', 'sanitize_callback' => 'esc_url_raw', 'show_in_rest' => true),
            '_scfs_sync_external_id' => array('type' => 'string', 'sanitize_callback' => 'sanitize_text_field', 'show_in_rest' => true),
            '_scfs_sync_external_url' => array('type' => 'string', 'sanitize_callback' => 'esc_url_raw', 'show_in_rest' => true),
            '_scfs_sync_source_path' => array('type' => 'string', 'sanitize_callback' => 'sanitize_text_field', 'show_in_rest' => true),
            '_scfs_sync_commit_sha' => array('type' => 'string', 'sanitize_callback' => 'sanitize_text_field', 'show_in_rest' => true),
            '_scfs_sync_remote_hash' => array('type' => 'string', 'sanitize_callback' => 'sanitize_text_field', 'show_in_rest' => false),
            '_scfs_sync_local_hash' => array('type' => 'string', 'sanitize_callback' => 'sanitize_text_field', 'show_in_rest' => false),
            '_scfs_sync_state' => array('type' => 'string', 'sanitize_callback' => 'sanitize_key', 'show_in_rest' => true),
            '_scfs_sync_last_seen_at' => array('type' => 'string', 'sanitize_callback' => 'sanitize_text_field', 'show_in_rest' => true),
            '_scfs_sync_requires_review' => array('type' => 'boolean', 'sanitize_callback' => 'rest_sanitize_boolean', 'show_in_rest' => true),
            '_scfs_sync_replaces_post_id' => array('type' => 'integer', 'sanitize_callback' => 'absint', 'show_in_rest' => false),
            '_scfs_sync_link_check_state' => array('type' => 'string', 'sanitize_callback' => 'sanitize_key', 'show_in_rest' => true),
            '_scfs_sync_link_checked_at' => array('type' => 'string', 'sanitize_callback' => 'sanitize_text_field', 'show_in_rest' => true),
            '_scfs_sync_broken_link_count' => array('type' => 'integer', 'sanitize_callback' => 'absint', 'show_in_rest' => true),
            '_scfs_sync_link_report' => array('type' => 'string', 'sanitize_callback' => 'sanitize_textarea_field', 'show_in_rest' => false),
        );
        foreach ($this->managed_post_types() as $post_type) {
            foreach ($definitions as $key => $definition) {
                register_post_meta($post_type, $key, array(
                    'type' => $definition['type'],
                    'single' => true,
                    'show_in_rest' => $definition['show_in_rest'],
                    'sanitize_callback' => $definition['sanitize_callback'],
                    'auth_callback' => function () {
                        return $this->can_manage();
                    },
                ));
            }
        }
    }

    public function register_admin_page() {
        add_submenu_page(
            'edit.php?post_type=' . Sustainable_Catalyst_Feature_Suggestions::POST_TYPE,
            __('Repository Synchronization', 'sustainable-catalyst-feature-suggestions'),
            __('Repository Sync', 'sustainable-catalyst-feature-suggestions'),
            $this->admin_capability(),
            self::ADMIN_SLUG,
            array($this, 'render_admin_page')
        );
    }

    public function admin_assets($hook) {
        if (strpos((string) $hook, self::ADMIN_SLUG) === false) {
            return;
        }
        wp_enqueue_style(
            'scfs-repository-sync',
            plugins_url('assets/repository-release-sync.css', dirname(__DIR__) . '/sustainable-catalyst-feature-suggestions.php'),
            array(),
            $this->asset_version('assets/repository-release-sync.css')
        );
        wp_enqueue_script(
            'scfs-repository-sync',
            plugins_url('assets/repository-release-sync.js', dirname(__DIR__) . '/sustainable-catalyst-feature-suggestions.php'),
            array(),
            $this->asset_version('assets/repository-release-sync.js'),
            true
        );
    }

    private function asset_version($relative) {
        $path = dirname(__DIR__) . '/' . ltrim($relative, '/');
        return file_exists($path) ? (string) filemtime($path) : self::VERSION;
    }

    public function mappings() {
        $mappings = function_exists('get_option') ? get_option(self::MAPPING_OPTION, array()) : array();
        return is_array($mappings) ? $mappings : array();
    }

    public function mapping($product_id) {
        $product_id = absint($product_id);
        $all = $this->mappings();
        $profile = array();
        if (class_exists('SCFS_Support_Content_Operations')) {
            $profile = SCFS_Support_Content_Operations::instance()->product_profile($product_id);
        }
        $defaults = array(
            'product_id' => $product_id,
            'enabled' => '1',
            'provider' => 'github',
            'repository_url' => $profile['repository_url'] ?? '',
            'default_branch' => $this->settings()['default_branch'],
            'readme_path' => 'README.md',
            'changelog_path' => 'CHANGELOG.md',
            'documentation_paths' => array('docs'),
            'release_notes_paths' => array(),
            'sync_readme' => '1',
            'sync_changelog' => '1',
            'sync_releases' => '1',
            'sync_documentation' => '1',
            'last_preview_at' => '',
            'last_sync_at' => '',
            'last_commit_sha' => '',
            'last_status' => 'not_checked',
        );
        $saved = isset($all[$product_id]) && is_array($all[$product_id]) ? $all[$product_id] : array();
        return function_exists('wp_parse_args') ? wp_parse_args($saved, $defaults) : array_merge($defaults, $saved);
    }

    public function sanitize_mapping($input, $product_id) {
        $repository_url = esc_url_raw(wp_unslash($input['repository_url'] ?? ''));
        $parsed = $this->parse_github_repository_url($repository_url);
        if ($repository_url !== '' && !$parsed) {
            return new WP_Error('scfs_invalid_repository_url', __('Use a public GitHub repository URL in the form https://github.com/owner/repository.', 'sustainable-catalyst-feature-suggestions'));
        }
        return array(
            'product_id' => absint($product_id),
            'enabled' => empty($input['enabled']) ? '' : '1',
            'provider' => 'github',
            'repository_url' => $repository_url,
            'default_branch' => sanitize_text_field(wp_unslash($input['default_branch'] ?? 'main')),
            'readme_path' => $this->sanitize_repository_path($input['readme_path'] ?? 'README.md'),
            'changelog_path' => $this->sanitize_repository_path($input['changelog_path'] ?? 'CHANGELOG.md'),
            'documentation_paths' => $this->sanitize_path_list($input['documentation_paths'] ?? ''),
            'release_notes_paths' => $this->sanitize_path_list($input['release_notes_paths'] ?? ''),
            'sync_readme' => empty($input['sync_readme']) ? '' : '1',
            'sync_changelog' => empty($input['sync_changelog']) ? '' : '1',
            'sync_releases' => empty($input['sync_releases']) ? '' : '1',
            'sync_documentation' => empty($input['sync_documentation']) ? '' : '1',
            'last_preview_at' => sanitize_text_field($input['last_preview_at'] ?? ''),
            'last_sync_at' => sanitize_text_field($input['last_sync_at'] ?? ''),
            'last_commit_sha' => sanitize_text_field($input['last_commit_sha'] ?? ''),
            'last_status' => sanitize_key($input['last_status'] ?? 'not_checked'),
        );
    }

    private function sanitize_repository_path($value) {
        $value = trim(str_replace('\\', '/', sanitize_text_field(wp_unslash((string) $value))));
        $value = preg_replace('#/+#', '/', $value);
        $value = ltrim($value, '/');
        if (strpos($value, '..') !== false) {
            return '';
        }
        return $value;
    }

    private function sanitize_path_list($value) {
        if (is_string($value)) {
            $value = preg_split('/[\r\n,]+/', wp_unslash($value));
        }
        $paths = array();
        foreach ((array) $value as $path) {
            $path = $this->sanitize_repository_path($path);
            if ($path !== '') {
                $paths[] = $path;
            }
        }
        return array_values(array_unique($paths));
    }

    public function save_mapping($product_id, $mapping) {
        $product_id = absint($product_id);
        if (!$product_id) {
            return new WP_Error('scfs_product_required', __('Choose a product before saving a repository mapping.', 'sustainable-catalyst-feature-suggestions'));
        }
        if (is_wp_error($mapping)) {
            return $mapping;
        }
        $all = $this->mappings();
        $all[$product_id] = $mapping;
        update_option(self::MAPPING_OPTION, $all, false);
        $this->append_log('mapping_saved', $product_id, array(
            'repository_url' => $mapping['repository_url'],
            'default_branch' => $mapping['default_branch'],
        ));
        return $mapping;
    }

    public function parse_github_repository_url($url) {
        $url = trim((string) $url);
        if ($url === '') {
            return null;
        }
        $parts = wp_parse_url($url);
        if (!is_array($parts) || strtolower($parts['host'] ?? '') !== 'github.com') {
            return null;
        }
        $path = trim($parts['path'] ?? '', '/');
        $segments = array_values(array_filter(explode('/', $path)));
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
        if (defined('SCFS_GITHUB_TOKEN') && SCFS_GITHUB_TOKEN) {
            return (string) SCFS_GITHUB_TOKEN;
        }
        $environment = getenv('SCFS_GITHUB_TOKEN');
        return is_string($environment) ? trim($environment) : '';
    }

    private function github_headers() {
        $headers = array(
            'Accept' => 'application/vnd.github+json',
            'User-Agent' => 'Sustainable-Catalyst-Feature-Suggestions/' . self::VERSION,
            'X-GitHub-Api-Version' => '2022-11-28',
        );
        $token = $this->github_token();
        if ($token !== '') {
            $headers['Authorization'] = 'Bearer ' . $token;
        }
        return $headers;
    }

    private function github_request($url) {
        $settings = $this->settings();
        $response = wp_remote_get($url, array(
            'timeout' => $settings['request_timeout'],
            'redirection' => 3,
            'headers' => $this->github_headers(),
        ));
        if (is_wp_error($response)) {
            return $response;
        }
        $code = (int) wp_remote_retrieve_response_code($response);
        $body = (string) wp_remote_retrieve_body($response);
        if ($code < 200 || $code >= 300) {
            $message = __('Repository request failed.', 'sustainable-catalyst-feature-suggestions');
            $decoded = json_decode($body, true);
            if (is_array($decoded) && !empty($decoded['message'])) {
                $message .= ' ' . sanitize_text_field($decoded['message']);
            }
            return new WP_Error('scfs_repository_request_failed', $message, array('status' => $code));
        }
        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            return new WP_Error('scfs_repository_invalid_response', __('The repository provider returned invalid JSON.', 'sustainable-catalyst-feature-suggestions'));
        }
        return $decoded;
    }

    public function repository_snapshot($product_id) {
        $mapping = $this->mapping($product_id);
        if (empty($mapping['repository_url'])) {
            return new WP_Error('scfs_repository_not_mapped', __('Map a repository URL before running synchronization.', 'sustainable-catalyst-feature-suggestions'));
        }
        $repository = $this->parse_github_repository_url($mapping['repository_url']);
        if (!$repository) {
            return new WP_Error('scfs_repository_invalid', __('The mapped repository URL is invalid.', 'sustainable-catalyst-feature-suggestions'));
        }
        $branch = $mapping['default_branch'] ?: 'main';
        $metadata = $this->github_request($repository['api_base']);
        if (is_wp_error($metadata)) {
            return $metadata;
        }
        if (!empty($metadata['private'])) {
            return new WP_Error('scfs_private_repository_not_supported', __('Private repository synchronization is disabled. Use public repositories or add a reviewed extension.', 'sustainable-catalyst-feature-suggestions'));
        }
        if (empty($mapping['default_branch']) && !empty($metadata['default_branch'])) {
            $branch = sanitize_text_field($metadata['default_branch']);
        }
        $commit = $this->github_request($repository['api_base'] . '/commits/' . rawurlencode($branch));
        $commit_sha = is_wp_error($commit) ? '' : sanitize_text_field($commit['sha'] ?? '');
        return array(
            'schema' => 'scfs-repository-snapshot/' . self::SCHEMA_VERSION,
            'version' => self::VERSION,
            'product_id' => absint($product_id),
            'repository' => array(
                'owner' => $repository['owner'],
                'name' => $repository['repository'],
                'url' => $repository['canonical_url'],
                'description' => sanitize_text_field($metadata['description'] ?? ''),
                'default_branch' => $branch,
                'commit_sha' => $commit_sha,
                'updated_at' => sanitize_text_field($metadata['updated_at'] ?? ''),
                'archived' => !empty($metadata['archived']),
            ),
            'captured_at' => gmdate('c'),
            'human_review_required' => true,
            'automatic_publication' => false,
        );
    }

    private function fetch_repository_file($repository, $path, $branch) {
        $path = $this->sanitize_repository_path($path);
        if ($path === '') {
            return new WP_Error('scfs_repository_path_required', __('Repository path is empty.', 'sustainable-catalyst-feature-suggestions'));
        }
        $url = $repository['api_base'] . '/contents/' . str_replace('%2F', '/', rawurlencode($path)) . '?ref=' . rawurlencode($branch);
        $response = $this->github_request($url);
        if (is_wp_error($response)) {
            return $response;
        }
        if (($response['type'] ?? '') !== 'file' || empty($response['content'])) {
            return new WP_Error('scfs_repository_file_not_found', sprintf(__('Repository path %s is not a readable file.', 'sustainable-catalyst-feature-suggestions'), $path));
        }
        $content = base64_decode(str_replace(array("\n", "\r"), '', (string) $response['content']), true);
        if ($content === false) {
            return new WP_Error('scfs_repository_file_decode_failed', sprintf(__('Repository path %s could not be decoded.', 'sustainable-catalyst-feature-suggestions'), $path));
        }
        return array(
            'path' => $path,
            'content' => $content,
            'sha' => sanitize_text_field($response['sha'] ?? ''),
            'html_url' => esc_url_raw($response['html_url'] ?? ''),
            'download_url' => esc_url_raw($response['download_url'] ?? ''),
        );
    }

    private function fetch_repository_directory($repository, $path, $branch, $limit, $depth = 0) {
        $path = $this->sanitize_repository_path($path);
        if ($path === '' || $limit <= 0 || $depth > 3) {
            return array();
        }
        $url = $repository['api_base'] . '/contents/' . str_replace('%2F', '/', rawurlencode($path)) . '?ref=' . rawurlencode($branch);
        $response = $this->github_request($url);
        if (is_wp_error($response)) {
            return $response;
        }
        $files = array();
        $directories = array();
        foreach ((array) $response as $item) {
            if (($item['type'] ?? '') === 'dir') {
                $directories[] = $item['path'] ?? '';
                continue;
            }
            if (count($files) >= $limit || ($item['type'] ?? '') !== 'file') {
                continue;
            }
            $name = strtolower((string) ($item['name'] ?? ''));
            if (!preg_match('/\.(md|markdown|txt)$/', $name)) {
                continue;
            }
            $file = $this->fetch_repository_file($repository, $item['path'] ?? '', $branch);
            if (!is_wp_error($file)) {
                $files[] = $file;
            }
        }
        foreach ($directories as $directory) {
            $remaining = $limit - count($files);
            if ($remaining <= 0) {
                break;
            }
            $nested = $this->fetch_repository_directory($repository, $directory, $branch, $remaining, $depth + 1);
            if (!is_wp_error($nested)) {
                $files = array_merge($files, $nested);
            }
        }
        return array_slice($files, 0, $limit);
    }

    private function fetch_github_releases($repository, $limit) {
        $response = $this->github_request($repository['api_base'] . '/releases?per_page=' . max(1, min(self::MAX_RELEASES, absint($limit))));
        if (is_wp_error($response)) {
            return $response;
        }
        $records = array();
        foreach ((array) $response as $release) {
            if (!is_array($release) || !empty($release['draft'])) {
                continue;
            }
            $tag = sanitize_text_field($release['tag_name'] ?? '');
            $name = sanitize_text_field($release['name'] ?? '');
            $records[] = array(
                'source_type' => 'github_release',
                'external_id' => 'github-release:' . absint($release['id'] ?? 0),
                'title' => $name !== '' ? $name : $tag,
                'version_label' => $tag,
                'content' => (string) ($release['body'] ?? ''),
                'external_url' => esc_url_raw($release['html_url'] ?? ''),
                'source_path' => 'releases/' . $tag,
                'source_sha' => sanitize_text_field($release['target_commitish'] ?? ''),
                'published_at' => sanitize_text_field($release['published_at'] ?? ''),
                'prerelease' => !empty($release['prerelease']),
            );
        }
        return $records;
    }

    private function markdown_title($content, $fallback) {
        if (preg_match('/^#\s+(.+)$/m', (string) $content, $match)) {
            return sanitize_text_field(trim($match[1]));
        }
        $fallback = pathinfo((string) $fallback, PATHINFO_FILENAME);
        return ucwords(str_replace(array('-', '_'), ' ', $fallback));
    }

    private function document_candidate($file, $type = 'documentation') {
        $title = $this->markdown_title($file['content'], basename($file['path']));
        $article_type = 'technical-reference';
        $path = strtolower((string) $file['path']);
        if ($type === 'readme' || strpos($path, 'getting-started') !== false || strpos($path, 'installation') !== false) {
            $article_type = 'getting-started';
        } elseif (strpos($path, 'troubleshoot') !== false || strpos($path, 'error') !== false) {
            $article_type = 'troubleshooting';
        } elseif (strpos($path, 'how-to') !== false || strpos($path, 'guide') !== false) {
            $article_type = 'how-to';
        }
        return array(
            'source_type' => $type,
            'record_type' => 'article',
            'external_id' => 'github-file:' . $file['path'],
            'title' => $title,
            'content' => (string) $file['content'],
            'external_url' => $file['html_url'],
            'source_path' => $file['path'],
            'source_sha' => $file['sha'],
            'article_type' => $article_type,
        );
    }

    private function changelog_candidates($file) {
        $content = (string) $file['content'];
        $matches = array();
        preg_match_all('/^##\s+(?:\[)?(v?\d+(?:\.\d+){1,3})(?:\])?[^\n]*\n(.*?)(?=^##\s+|\z)/ims', $content, $matches, PREG_SET_ORDER);
        $records = array();
        foreach ($matches as $match) {
            $version = sanitize_text_field($match[1]);
            $body = trim($match[2]);
            $records[] = array(
                'source_type' => 'changelog',
                'record_type' => 'release',
                'external_id' => 'github-changelog:' . $file['path'] . ':' . strtolower($version),
                'title' => $version,
                'version_label' => $version,
                'content' => $body,
                'external_url' => $file['html_url'],
                'source_path' => $file['path'],
                'source_sha' => $file['sha'],
                'published_at' => '',
                'prerelease' => false,
            );
        }
        if (!$records && trim($content) !== '') {
            $records[] = array(
                'source_type' => 'changelog',
                'record_type' => 'article',
                'external_id' => 'github-file:' . $file['path'],
                'title' => $this->markdown_title($content, basename($file['path'])),
                'content' => $content,
                'external_url' => $file['html_url'],
                'source_path' => $file['path'],
                'source_sha' => $file['sha'],
                'article_type' => 'technical-reference',
            );
        }
        return $records;
    }

    public function collect_candidates($product_id) {
        $mapping = $this->mapping($product_id);
        $snapshot = $this->repository_snapshot($product_id);
        if (is_wp_error($snapshot)) {
            return $snapshot;
        }
        $repository = $this->parse_github_repository_url($mapping['repository_url']);
        $branch = $snapshot['repository']['default_branch'];
        $settings = $this->settings();
        $candidates = array();
        $warnings = array();

        if ($mapping['sync_readme'] === '1' && $mapping['readme_path'] !== '') {
            $file = $this->fetch_repository_file($repository, $mapping['readme_path'], $branch);
            if (is_wp_error($file)) {
                $warnings[] = $file->get_error_message();
            } else {
                $candidates[] = $this->document_candidate($file, 'readme');
            }
        }
        if ($mapping['sync_changelog'] === '1' && $mapping['changelog_path'] !== '') {
            $file = $this->fetch_repository_file($repository, $mapping['changelog_path'], $branch);
            if (is_wp_error($file)) {
                $warnings[] = $file->get_error_message();
            } else {
                $candidates = array_merge($candidates, $this->changelog_candidates($file));
            }
        }
        if ($mapping['sync_documentation'] === '1') {
            $remaining = $settings['maximum_documents'];
            foreach ($mapping['documentation_paths'] as $path) {
                if ($remaining <= 0) {
                    break;
                }
                $files = $this->fetch_repository_directory($repository, $path, $branch, $remaining);
                if (is_wp_error($files)) {
                    $warnings[] = $files->get_error_message();
                    continue;
                }
                foreach ($files as $file) {
                    $candidates[] = $this->document_candidate($file, 'documentation');
                    $remaining--;
                    if ($remaining <= 0) {
                        break;
                    }
                }
            }
            foreach ($mapping['release_notes_paths'] as $path) {
                if ($remaining <= 0) {
                    break;
                }
                $file = $this->fetch_repository_file($repository, $path, $branch);
                if (is_wp_error($file)) {
                    $warnings[] = $file->get_error_message();
                    continue;
                }
                foreach ($this->changelog_candidates($file) as $candidate) {
                    $candidates[] = $candidate;
                    $remaining--;
                    if ($remaining <= 0) {
                        break;
                    }
                }
            }
        }
        if ($mapping['sync_releases'] === '1') {
            $releases = $this->fetch_github_releases($repository, $settings['maximum_releases']);
            if (is_wp_error($releases)) {
                $warnings[] = $releases->get_error_message();
            } else {
                foreach ($releases as $release) {
                    $release['record_type'] = 'release';
                    $candidates[] = $release;
                }
            }
        }

        $unique = array();
        foreach ($candidates as $candidate) {
            if (empty($candidate['external_id'])) {
                continue;
            }
            $candidate['remote_hash'] = $this->content_hash($candidate['title'], $candidate['content']);
            $unique[$candidate['external_id']] = $candidate;
        }
        return array(
            'schema' => 'scfs-repository-candidates/' . self::SCHEMA_VERSION,
            'version' => self::VERSION,
            'product_id' => absint($product_id),
            'snapshot' => $snapshot,
            'candidates' => array_values($unique),
            'warnings' => array_values(array_unique($warnings)),
            'human_review_required' => true,
            'automatic_publication' => false,
        );
    }

    public function content_hash($title, $content) {
        $text = strtolower(wp_strip_all_tags((string) $title . "\n" . (string) $content));
        $text = preg_replace('/\s+/', ' ', $text);
        return hash('sha256', trim($text));
    }

    private function find_synced_record($external_id, $product_id = 0) {
        $args = array(
            'post_type' => $this->managed_post_types(),
            'post_status' => array('publish', 'future', 'draft', 'pending', 'private', 'trash'),
            'posts_per_page' => 1,
            'no_found_rows' => true,
            'meta_query' => array(array('key' => '_scfs_sync_external_id', 'value' => sanitize_text_field($external_id))),
        );
        if ($product_id && class_exists('SCFS_Product_Integration')) {
            $args['tax_query'] = array(array(
                'taxonomy' => SCFS_Product_Integration::PRODUCT_TAXONOMY,
                'field' => 'term_id',
                'terms' => absint($product_id),
            ));
        }
        $query = new WP_Query($args);
        return $query->posts ? $query->posts[0] : null;
    }

    public function evaluate_candidate($candidate, $product_id) {
        $existing = $this->find_synced_record($candidate['external_id'], $product_id);
        if (!$existing) {
            return array(
                'action' => 'create_draft',
                'state' => 'new',
                'existing_post_id' => 0,
                'local_changed' => false,
                'remote_changed' => true,
                'reason' => 'No synchronized WordPress record exists.',
                'human_review_required' => true,
            );
        }
        $last_remote_hash = (string) get_post_meta($existing->ID, '_scfs_sync_remote_hash', true);
        $last_local_hash = (string) get_post_meta($existing->ID, '_scfs_sync_local_hash', true);
        $current_local_hash = $this->content_hash($existing->post_title, $existing->post_content);
        $remote_changed = $last_remote_hash === '' || !hash_equals($last_remote_hash, $candidate['remote_hash']);
        $local_changed = $last_local_hash !== '' && !hash_equals($last_local_hash, $current_local_hash);
        $published = in_array($existing->post_status, array('publish', 'future'), true);

        if (!$remote_changed) {
            return array(
                'action' => 'none',
                'state' => $local_changed ? 'local_edit' : 'unchanged',
                'existing_post_id' => (int) $existing->ID,
                'local_changed' => $local_changed,
                'remote_changed' => false,
                'reason' => $local_changed ? 'The repository source is unchanged and the WordPress record has local edits.' : 'The repository source is unchanged.',
                'human_review_required' => true,
            );
        }
        if ($local_changed || $published) {
            return array(
                'action' => 'create_review_copy',
                'state' => $local_changed ? 'conflict' : 'published_update',
                'existing_post_id' => (int) $existing->ID,
                'local_changed' => $local_changed,
                'remote_changed' => true,
                'reason' => $local_changed ? 'Both repository and WordPress content changed; preserve local edits and create a review copy.' : 'Published records are never overwritten; create a review copy.',
                'human_review_required' => true,
            );
        }
        return array(
            'action' => 'update_draft',
            'state' => 'remote_update',
            'existing_post_id' => (int) $existing->ID,
            'local_changed' => false,
            'remote_changed' => true,
            'reason' => 'The repository changed and the existing draft has no local edits.',
            'human_review_required' => true,
        );
    }

    public function preview_sync($product_id) {
        $collected = $this->collect_candidates($product_id);
        if (is_wp_error($collected)) {
            return $collected;
        }
        $items = array();
        $counts = array('new' => 0, 'remote_update' => 0, 'published_update' => 0, 'conflict' => 0, 'local_edit' => 0, 'unchanged' => 0);
        foreach ($collected['candidates'] as $candidate) {
            $decision = $this->evaluate_candidate($candidate, $product_id);
            $state = $decision['state'];
            if (!isset($counts[$state])) {
                $counts[$state] = 0;
            }
            $counts[$state]++;
            $items[] = array_merge($candidate, array('decision' => $decision));
        }
        $mapping = $this->mapping($product_id);
        $mapping['last_preview_at'] = gmdate('c');
        $mapping['last_commit_sha'] = $collected['snapshot']['repository']['commit_sha'];
        $mapping['last_status'] = 'previewed';
        $this->save_mapping($product_id, $mapping);
        $report = array(
            'schema' => 'scfs-repository-sync-preview/' . self::SCHEMA_VERSION,
            'version' => self::VERSION,
            'product_id' => absint($product_id),
            'repository' => $collected['snapshot']['repository'],
            'counts' => $counts,
            'items' => $items,
            'warnings' => $collected['warnings'],
            'previewed_at' => gmdate('c'),
            'human_review_required' => true,
            'automatic_publication' => false,
        );
        $state = get_option(self::STATE_OPTION, array());
        $state[absint($product_id)] = $this->summarize_preview_for_storage($report);
        update_option(self::STATE_OPTION, $state, false);
        $this->append_log('preview_completed', $product_id, array('counts' => $counts, 'warnings' => count($collected['warnings'])));
        return $report;
    }

    private function summarize_preview_for_storage($report) {
        $items = array();
        foreach ($report['items'] as $item) {
            $items[] = array(
                'external_id' => $item['external_id'],
                'title' => $item['title'],
                'record_type' => $item['record_type'] ?? 'article',
                'source_path' => $item['source_path'] ?? '',
                'external_url' => $item['external_url'] ?? '',
                'remote_hash' => $item['remote_hash'] ?? '',
                'decision' => $item['decision'],
            );
        }
        return array(
            'repository' => $report['repository'],
            'counts' => $report['counts'],
            'items' => $items,
            'warnings' => $report['warnings'],
            'previewed_at' => $report['previewed_at'],
        );
    }

    public function apply_sync($product_id) {
        $preview = $this->preview_sync($product_id);
        if (is_wp_error($preview)) {
            return $preview;
        }
        $created = array();
        $updated = array();
        $skipped = array();
        $failed = array();
        foreach ($preview['items'] as $item) {
            $action = $item['decision']['action'];
            if ($action === 'none') {
                $skipped[] = array('external_id' => $item['external_id'], 'reason' => $item['decision']['reason']);
                continue;
            }
            $result = $this->apply_candidate($product_id, $item, $item['decision']);
            if (is_wp_error($result)) {
                $failed[] = array('external_id' => $item['external_id'], 'error' => $result->get_error_message());
                continue;
            }
            if ($result['created']) {
                $created[] = $result;
            } else {
                $updated[] = $result;
            }
        }
        $mapping = $this->mapping($product_id);
        $mapping['last_sync_at'] = gmdate('c');
        $mapping['last_commit_sha'] = $preview['repository']['commit_sha'];
        $mapping['last_status'] = $failed ? 'completed_with_errors' : 'completed';
        $this->save_mapping($product_id, $mapping);
        $result = array(
            'schema' => 'scfs-repository-sync-result/' . self::SCHEMA_VERSION,
            'version' => self::VERSION,
            'product_id' => absint($product_id),
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'failed' => $failed,
            'completed_at' => gmdate('c'),
            'human_review_required' => true,
            'automatic_publication' => false,
        );
        $this->append_log('sync_completed', $product_id, array(
            'created' => count($created),
            'updated' => count($updated),
            'skipped' => count($skipped),
            'failed' => count($failed),
        ));
        return $result;
    }

    private function apply_candidate($product_id, $candidate, $decision) {
        $post_type = ($candidate['record_type'] ?? 'article') === 'release'
            ? SCFS_Product_Support_Platform::RELEASE_POST_TYPE
            : SCFS_Knowledge_Base_Foundation::ARTICLE_POST_TYPE;
        $title = sanitize_text_field($candidate['title']);
        if ($title === '') {
            $title = __('Repository synchronization draft', 'sustainable-catalyst-feature-suggestions');
        }
        $postarr = array(
            'post_type' => $post_type,
            'post_status' => 'draft',
            'post_title' => $title,
            'post_content' => wp_kses_post($candidate['content']),
            'post_excerpt' => wp_trim_words(wp_strip_all_tags($candidate['content']), 35),
        );
        $created = true;
        $replaces = absint($decision['existing_post_id'] ?? 0);
        if ($decision['action'] === 'update_draft' && $replaces) {
            $postarr['ID'] = $replaces;
            $post_id = wp_update_post($postarr, true);
            $created = false;
        } else {
            if ($decision['action'] === 'create_review_copy' && $replaces) {
                $postarr['post_title'] = sprintf(__('[Repository Review] %s', 'sustainable-catalyst-feature-suggestions'), $title);
            }
            $post_id = wp_insert_post($postarr, true);
        }
        if (is_wp_error($post_id)) {
            return $post_id;
        }
        wp_set_object_terms($post_id, array(absint($product_id)), SCFS_Product_Integration::PRODUCT_TAXONOMY, false);
        if ($post_type === SCFS_Knowledge_Base_Foundation::ARTICLE_POST_TYPE && !empty($candidate['article_type'])) {
            $term = get_term_by('slug', sanitize_title($candidate['article_type']), SCFS_Knowledge_Base_Foundation::ARTICLE_TYPE_TAXONOMY);
            if ($term && !is_wp_error($term)) {
                wp_set_object_terms($post_id, array((int) $term->term_id), SCFS_Knowledge_Base_Foundation::ARTICLE_TYPE_TAXONOMY, false);
            }
            update_post_meta($post_id, '_scfs_kb_summary', wp_trim_words(wp_strip_all_tags($candidate['content']), 32));
            update_post_meta($post_id, '_scfs_article_template', str_replace('-', '_', sanitize_key($candidate['article_type'])));
        }
        if ($post_type === SCFS_Product_Support_Platform::RELEASE_POST_TYPE) {
            $version = sanitize_text_field($candidate['version_label'] ?? '');
            update_post_meta($post_id, '_scfs_release_status', !empty($candidate['prerelease']) ? 'preview' : 'current');
            update_post_meta($post_id, '_scfs_release_summary', wp_trim_words(wp_strip_all_tags($candidate['content']), 38));
            update_post_meta($post_id, '_scfs_release_date', $this->date_only($candidate['published_at'] ?? ''));
            if ($version !== '') {
                $this->assign_release_terms($post_id, $product_id, $version, $candidate['published_at'] ?? '');
            }
        }
        $local_hash = $this->content_hash($postarr['post_title'], $postarr['post_content']);
        $metadata = array(
            '_scfs_sync_provider' => 'github',
            '_scfs_sync_repository_url' => $this->mapping($product_id)['repository_url'],
            '_scfs_sync_external_id' => $candidate['external_id'],
            '_scfs_sync_external_url' => $candidate['external_url'] ?? '',
            '_scfs_sync_source_path' => $candidate['source_path'] ?? '',
            '_scfs_sync_commit_sha' => $candidate['source_sha'] ?? '',
            '_scfs_sync_remote_hash' => $candidate['remote_hash'],
            '_scfs_sync_local_hash' => $local_hash,
            '_scfs_sync_state' => $decision['state'],
            '_scfs_sync_last_seen_at' => gmdate('c'),
            '_scfs_sync_requires_review' => 1,
            '_scfs_sync_replaces_post_id' => $decision['action'] === 'create_review_copy' ? $replaces : 0,
            '_scfs_source_kind' => sanitize_key($candidate['source_type'] ?? 'repository'),
            '_scfs_source_reference' => sanitize_text_field($candidate['source_path'] ?? ''),
            '_scfs_source_version' => sanitize_text_field($candidate['version_label'] ?? ''),
            '_scfs_source_fingerprint' => $candidate['remote_hash'],
            '_scfs_verified_at' => gmdate('Y-m-d'),
            '_scfs_content_lifecycle' => 'review',
            '_scfs_content_operations_schema' => class_exists('SCFS_Support_Content_Operations') ? SCFS_Support_Content_Operations::SCHEMA_VERSION : '1.1',
            '_scfs_editorial_state' => $this->settings()['submit_for_editorial_review'] === '1' ? 'submitted' : 'draft',
            '_scfs_editorial_change_summary' => sprintf(__('Repository synchronization candidate from %s. Human review is required before publication.', 'sustainable-catalyst-feature-suggestions'), sanitize_text_field($candidate['source_path'] ?? $candidate['external_id'])),
        );
        foreach ($metadata as $key => $value) {
            update_post_meta($post_id, $key, $value);
        }
        if (class_exists('SCFS_Editorial_Governance')) {
            SCFS_Editorial_Governance::instance()->append_audit($post_id, 'repository_sync_candidate', array(
                'external_id' => $candidate['external_id'],
                'decision' => $decision['action'],
                'replaces_post_id' => $replaces,
            ));
        }
        return array(
            'post_id' => (int) $post_id,
            'created' => $created,
            'action' => $decision['action'],
            'state' => $decision['state'],
            'external_id' => $candidate['external_id'],
            'replaces_post_id' => $decision['action'] === 'create_review_copy' ? $replaces : 0,
        );
    }

    private function assign_release_terms($post_id, $product_id, $version, $published_at = '') {
        $version_term = term_exists($version, SCFS_Product_Integration::VERSION_TAXONOMY);
        if (!$version_term) {
            $version_term = wp_insert_term($version, SCFS_Product_Integration::VERSION_TAXONOMY, array('slug' => sanitize_title($version)));
        }
        if (!is_wp_error($version_term)) {
            $version_id = is_array($version_term) ? absint($version_term['term_id']) : absint($version_term);
            update_term_meta($version_id, 'scfs_product_ids', array(absint($product_id)));
            wp_set_object_terms($post_id, array($version_id), SCFS_Product_Integration::VERSION_TAXONOMY, false);
        }
        $release_term = term_exists($version, SCFS_Product_Integration::RELEASE_TAXONOMY);
        if (!$release_term) {
            $release_term = wp_insert_term($version, SCFS_Product_Integration::RELEASE_TAXONOMY, array('slug' => sanitize_title($version)));
        }
        if (!is_wp_error($release_term)) {
            $release_id = is_array($release_term) ? absint($release_term['term_id']) : absint($release_term);
            update_term_meta($release_id, 'scfs_product_ids', array(absint($product_id)));
            if ($published_at !== '') {
                update_term_meta($release_id, 'scfs_release_date', $this->date_only($published_at));
            }
            wp_set_object_terms($post_id, array($release_id), SCFS_Product_Integration::RELEASE_TAXONOMY, false);
        }
    }

    private function date_only($value) {
        $timestamp = strtotime((string) $value);
        return $timestamp ? gmdate('Y-m-d', $timestamp) : '';
    }

    public function drift_report($product_id = 0) {
        $args = array(
            'post_type' => $this->managed_post_types(),
            'post_status' => array('publish', 'future', 'draft', 'pending', 'private'),
            'posts_per_page' => -1,
            'no_found_rows' => true,
            'meta_query' => array(array('key' => '_scfs_sync_external_id', 'compare' => 'EXISTS')),
        );
        if ($product_id) {
            $args['tax_query'] = array(array(
                'taxonomy' => SCFS_Product_Integration::PRODUCT_TAXONOMY,
                'field' => 'term_id',
                'terms' => absint($product_id),
            ));
        }
        $query = new WP_Query($args);
        $records = array();
        $counts = array('aligned' => 0, 'local_edit' => 0, 'remote_update' => 0, 'conflict' => 0, 'unknown' => 0);
        foreach ($query->posts as $post) {
            $local_hash = $this->content_hash($post->post_title, $post->post_content);
            $synced_local = (string) get_post_meta($post->ID, '_scfs_sync_local_hash', true);
            $state = $synced_local !== '' && hash_equals($synced_local, $local_hash) ? 'aligned' : 'local_edit';
            $stored_state = sanitize_key(get_post_meta($post->ID, '_scfs_sync_state', true));
            if (in_array($stored_state, array('remote_update', 'conflict'), true)) {
                $state = $stored_state;
            }
            if (!isset($counts[$state])) {
                $state = 'unknown';
            }
            $counts[$state]++;
            $records[] = array(
                'post_id' => (int) $post->ID,
                'post_type' => $post->post_type,
                'title' => $post->post_title,
                'post_status' => $post->post_status,
                'state' => $state,
                'external_id' => get_post_meta($post->ID, '_scfs_sync_external_id', true),
                'source_path' => get_post_meta($post->ID, '_scfs_sync_source_path', true),
                'last_seen_at' => get_post_meta($post->ID, '_scfs_sync_last_seen_at', true),
            );
        }
        return array(
            'schema' => 'scfs-repository-drift-report/' . self::SCHEMA_VERSION,
            'version' => self::VERSION,
            'product_id' => absint($product_id),
            'counts' => $counts,
            'records' => $records,
            'generated_at' => gmdate('c'),
            'human_review_required' => true,
        );
    }

    private function extract_urls($text) {
        preg_match_all('#https?://[^\s<>"\']+#i', (string) $text, $matches);
        $urls = array();
        foreach ((array) ($matches[0] ?? array()) as $url) {
            $url = rtrim($url, '.,);]}');
            if (filter_var($url, FILTER_VALIDATE_URL)) {
                $urls[] = $url;
            }
        }
        return array_values(array_unique($urls));
    }

    private function check_url($url) {
        $settings = $this->settings();
        $args = array('timeout' => min(12, $settings['request_timeout']), 'redirection' => 3, 'user-agent' => 'Sustainable-Catalyst-Link-Checker/' . self::VERSION);
        $response = wp_remote_head($url, $args);
        if (is_wp_error($response) || (int) wp_remote_retrieve_response_code($response) === 405) {
            $response = wp_remote_get($url, array_merge($args, array('limit_response_size' => 1024)));
        }
        if (is_wp_error($response)) {
            return array('url' => $url, 'ok' => false, 'status' => 0, 'error' => $response->get_error_message());
        }
        $code = (int) wp_remote_retrieve_response_code($response);
        return array('url' => $url, 'ok' => $code >= 200 && $code < 400, 'status' => $code, 'error' => '');
    }

    public function check_product_links($product_id, $limit = 100) {
        $args = array(
            'post_type' => $this->managed_post_types(),
            'post_status' => array('publish', 'future', 'draft', 'pending', 'private'),
            'posts_per_page' => 50,
            'no_found_rows' => true,
        );
        if ($product_id) {
            $args['tax_query'] = array(array(
                'taxonomy' => SCFS_Product_Integration::PRODUCT_TAXONOMY,
                'field' => 'term_id',
                'terms' => absint($product_id),
            ));
        }
        $query = new WP_Query($args);
        $checked = 0;
        $broken = 0;
        $records = array();
        foreach ($query->posts as $post) {
            if ($checked >= $limit) {
                break;
            }
            $urls = $this->extract_urls($post->post_content . "\n" . get_post_meta($post->ID, '_scfs_sync_external_url', true));
            $report = array();
            foreach ($urls as $url) {
                if ($checked >= $limit) {
                    break;
                }
                $result = $this->check_url($url);
                $report[] = $result;
                $checked++;
                if (!$result['ok']) {
                    $broken++;
                }
            }
            if ($report) {
                $post_broken = count(array_filter($report, function ($item) { return !$item['ok']; }));
                update_post_meta($post->ID, '_scfs_sync_link_check_state', $post_broken ? 'broken' : 'healthy');
                update_post_meta($post->ID, '_scfs_sync_link_checked_at', gmdate('c'));
                update_post_meta($post->ID, '_scfs_sync_broken_link_count', $post_broken);
                update_post_meta($post->ID, '_scfs_sync_link_report', wp_json_encode($report));
                $records[] = array('post_id' => (int) $post->ID, 'title' => $post->post_title, 'broken' => $post_broken, 'checked' => count($report));
            }
        }
        $result = array(
            'schema' => 'scfs-repository-link-health/' . self::SCHEMA_VERSION,
            'version' => self::VERSION,
            'product_id' => absint($product_id),
            'checked_links' => $checked,
            'broken_links' => $broken,
            'state' => $broken ? 'attention' : 'healthy',
            'records' => $records,
            'checked_at' => gmdate('c'),
            'human_review_required' => true,
        );
        $this->append_log('link_check_completed', $product_id, array('checked' => $checked, 'broken' => $broken));
        return $result;
    }

    public function append_log($action, $product_id = 0, $context = array()) {
        $log = get_option(self::LOG_OPTION, array());
        if (!is_array($log)) {
            $log = array();
        }
        array_unshift($log, array(
            'id' => function_exists('wp_generate_uuid4') ? wp_generate_uuid4() : uniqid('scfs-sync-', true),
            'at' => gmdate('c'),
            'action' => sanitize_key($action),
            'product_id' => absint($product_id),
            'actor_id' => function_exists('get_current_user_id') ? get_current_user_id() : 0,
            'context' => $this->sanitize_log_context($context),
        ));
        $limit = $this->settings()['log_retention'];
        $log = array_slice($log, 0, $limit);
        update_option(self::LOG_OPTION, $log, false);
        return $log[0];
    }

    private function sanitize_log_context($context) {
        $safe = array();
        foreach ((array) $context as $key => $value) {
            $key = sanitize_key($key);
            if (is_scalar($value)) {
                $safe[$key] = sanitize_text_field((string) $value);
            } elseif (is_array($value)) {
                $safe[$key] = array_map(function ($item) {
                    return is_scalar($item) ? sanitize_text_field((string) $item) : '';
                }, $value);
            }
        }
        return $safe;
    }

    public function run_scheduled_sync() {
        $settings = $this->settings();
        if ($settings['scheduled_inspection'] !== '1') {
            return array('status' => 'disabled');
        }
        if (get_transient(self::CRON_LOCK)) {
            return array('status' => 'locked');
        }
        set_transient(self::CRON_LOCK, '1', 20 * MINUTE_IN_SECONDS);
        $results = array();
        foreach ($this->mappings() as $product_id => $mapping) {
            if (empty($mapping['enabled']) || empty($mapping['repository_url'])) {
                continue;
            }
            $result = $settings['scheduled_draft_ingestion'] === '1' ? $this->apply_sync($product_id) : $this->preview_sync($product_id);
            $results[$product_id] = is_wp_error($result) ? array('error' => $result->get_error_message()) : array('ok' => true, 'counts' => $result['counts'] ?? array());
        }
        update_option(self::STATE_OPTION . '_cron', array('last_run_at' => gmdate('c'), 'results' => $results), false);
        delete_transient(self::CRON_LOCK);
        $this->append_log('scheduled_inspection_completed', 0, array('products' => count($results), 'draft_ingestion' => $settings['scheduled_draft_ingestion']));
        return array('status' => 'complete', 'results' => $results);
    }

    private function webhook_secret() {
        if (defined('SCFS_GITHUB_WEBHOOK_SECRET') && SCFS_GITHUB_WEBHOOK_SECRET) {
            return (string) SCFS_GITHUB_WEBHOOK_SECRET;
        }
        $environment = getenv('SCFS_GITHUB_WEBHOOK_SECRET');
        return is_string($environment) ? trim($environment) : '';
    }

    public function verify_github_signature($body, $signature) {
        $secret = $this->webhook_secret();
        if ($secret === '' || strpos((string) $signature, 'sha256=') !== 0) {
            return false;
        }
        $expected = 'sha256=' . hash_hmac('sha256', (string) $body, $secret);
        return hash_equals($expected, (string) $signature);
    }

    public function rest_webhook($request) {
        if ($this->settings()['webhook_enabled'] !== '1') {
            return new WP_Error('scfs_webhook_disabled', __('Repository webhooks are disabled.', 'sustainable-catalyst-feature-suggestions'), array('status' => 403));
        }
        $body = $request->get_body();
        $signature = $request->get_header('x-hub-signature-256');
        if (!$this->verify_github_signature($body, $signature)) {
            return new WP_Error('scfs_webhook_signature_invalid', __('The repository webhook signature is invalid.', 'sustainable-catalyst-feature-suggestions'), array('status' => 401));
        }
        $payload = json_decode($body, true);
        $repository_url = esc_url_raw($payload['repository']['html_url'] ?? '');
        $event = sanitize_key($request->get_header('x-github-event'));
        $product_id = 0;
        foreach ($this->mappings() as $mapped_product => $mapping) {
            if ($this->canonical_repository_url($mapping['repository_url'] ?? '') === $this->canonical_repository_url($repository_url)) {
                $product_id = absint($mapped_product);
                break;
            }
        }
        if (!$product_id) {
            return new WP_Error('scfs_webhook_repository_unmapped', __('The webhook repository is not mapped to a product.', 'sustainable-catalyst-feature-suggestions'), array('status' => 404));
        }
        $queue = get_option(self::QUEUE_OPTION, array());
        $queue[] = array(
            'product_id' => $product_id,
            'event' => $event,
            'delivery' => sanitize_text_field($request->get_header('x-github-delivery')),
            'repository_url' => $repository_url,
            'received_at' => gmdate('c'),
        );
        $queue = array_slice($queue, -50);
        update_option(self::QUEUE_OPTION, $queue, false);
        if (function_exists('wp_schedule_single_event') && !wp_next_scheduled(self::WEBHOOK_HOOK)) {
            wp_schedule_single_event(time() + 15, self::WEBHOOK_HOOK);
        }
        $this->append_log('webhook_queued', $product_id, array('event' => $event));
        return rest_ensure_response(array(
            'ok' => true,
            'queued' => true,
            'product_id' => $product_id,
            'event' => $event,
            'human_review_required' => true,
            'automatic_publication' => false,
        ));
    }

    private function canonical_repository_url($url) {
        $parsed = $this->parse_github_repository_url($url);
        return $parsed ? strtolower($parsed['canonical_url']) : '';
    }

    public function process_webhook_queue() {
        $queue = get_option(self::QUEUE_OPTION, array());
        update_option(self::QUEUE_OPTION, array(), false);
        $results = array();
        foreach ((array) $queue as $entry) {
            $product_id = absint($entry['product_id'] ?? 0);
            if (!$product_id) {
                continue;
            }
            $preview = $this->preview_sync($product_id);
            $results[] = array(
                'product_id' => $product_id,
                'event' => sanitize_key($entry['event'] ?? ''),
                'ok' => !is_wp_error($preview),
                'error' => is_wp_error($preview) ? $preview->get_error_message() : '',
            );
        }
        $this->append_log('webhook_queue_processed', 0, array('events' => count($results)));
        return $results;
    }

    public function schema_record() {
        return array(
            'schema' => 'scfs-repository-release-synchronization/' . self::SCHEMA_VERSION,
            'version' => self::VERSION,
            'providers' => array('github_public'),
            'capabilities' => array(
                'product_repository_mapping',
                'github_release_ingestion',
                'readme_and_changelog_ingestion',
                'documentation_path_ingestion',
                'commit_and_tag_provenance',
                'documentation_drift_detection',
                'broken_repository_link_checks',
                'approval_gated_draft_creation',
                'synchronization_logs',
                'signed_webhook_queue',
                'manual_and_scheduled_inspection',
            ),
            'governance' => array(
                'human_review_required' => true,
                'automatic_approval' => false,
                'automatic_publication' => false,
                'published_records_overwritten' => false,
                'private_repository_sync_enabled' => false,
                'github_tokens_exposed_in_rest' => false,
                'webhook_events_create_public_content' => false,
            ),
        );
    }

    public function register_rest_routes() {
        register_rest_route(Sustainable_Catalyst_Feature_Suggestions::REST_NAMESPACE, '/repository-sync/schema', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => function () { return rest_ensure_response($this->schema_record()); },
            'permission_callback' => '__return_true',
        ));
        register_rest_route(Sustainable_Catalyst_Feature_Suggestions::REST_NAMESPACE, '/repository-sync/mappings', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => function () { return rest_ensure_response(array('version' => self::VERSION, 'mappings' => $this->mappings())); },
            'permission_callback' => array($this, 'rest_permission'),
        ));
        register_rest_route(Sustainable_Catalyst_Feature_Suggestions::REST_NAMESPACE, '/repository-sync/preview/(?P<product_id>\d+)', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'rest_preview'),
            'permission_callback' => array($this, 'rest_permission'),
        ));
        register_rest_route(Sustainable_Catalyst_Feature_Suggestions::REST_NAMESPACE, '/repository-sync/apply/(?P<product_id>\d+)', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'rest_apply'),
            'permission_callback' => array($this, 'rest_permission'),
        ));
        register_rest_route(Sustainable_Catalyst_Feature_Suggestions::REST_NAMESPACE, '/repository-sync/drift', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'rest_drift'),
            'permission_callback' => array($this, 'rest_permission'),
        ));
        register_rest_route(Sustainable_Catalyst_Feature_Suggestions::REST_NAMESPACE, '/repository-sync/link-health/(?P<product_id>\d+)', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'rest_links'),
            'permission_callback' => array($this, 'rest_permission'),
        ));
        register_rest_route(Sustainable_Catalyst_Feature_Suggestions::REST_NAMESPACE, '/repository-sync/log', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => function () { return rest_ensure_response(array('version' => self::VERSION, 'items' => get_option(self::LOG_OPTION, array()))); },
            'permission_callback' => array($this, 'rest_permission'),
        ));
        register_rest_route(Sustainable_Catalyst_Feature_Suggestions::REST_NAMESPACE, '/repository-sync/webhook/github', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'rest_webhook'),
            'permission_callback' => '__return_true',
        ));
    }

    public function rest_permission() {
        return $this->can_manage();
    }

    public function rest_preview($request) {
        $result = $this->preview_sync(absint($request['product_id']));
        return is_wp_error($result) ? $result : rest_ensure_response($result);
    }

    public function rest_apply($request) {
        $result = $this->apply_sync(absint($request['product_id']));
        return is_wp_error($result) ? $result : rest_ensure_response($result);
    }

    public function rest_drift($request) {
        return rest_ensure_response($this->drift_report(absint($request->get_param('product_id'))));
    }

    public function rest_links($request) {
        return rest_ensure_response($this->check_product_links(absint($request['product_id']), absint($request->get_param('limit')) ?: 100));
    }

    public function save_settings_action() {
        if (!$this->can_manage()) {
            wp_die(__('You do not have permission to manage repository synchronization settings.', 'sustainable-catalyst-feature-suggestions'));
        }
        check_admin_referer('scfs_save_repository_sync_settings', 'scfs_repository_sync_settings_nonce');
        $defaults = $this->default_settings();
        $settings = array(
            'provider' => 'github',
            'default_branch' => sanitize_text_field(wp_unslash($_POST['default_branch'] ?? $defaults['default_branch'])),
            'request_timeout' => max(5, min(30, absint($_POST['request_timeout'] ?? $defaults['request_timeout']))),
            'maximum_releases' => max(1, min(self::MAX_RELEASES, absint($_POST['maximum_releases'] ?? $defaults['maximum_releases']))),
            'maximum_documents' => max(1, min(self::MAX_DOCUMENTS, absint($_POST['maximum_documents'] ?? $defaults['maximum_documents']))),
            'scheduled_inspection' => empty($_POST['scheduled_inspection']) ? '' : '1',
            'scheduled_draft_ingestion' => empty($_POST['scheduled_draft_ingestion']) ? '' : '1',
            'create_status' => 'draft',
            'submit_for_editorial_review' => empty($_POST['submit_for_editorial_review']) ? '' : '1',
            'sync_readme' => '1',
            'sync_changelog' => '1',
            'sync_releases' => '1',
            'sync_documentation' => '1',
            'detect_drift' => empty($_POST['detect_drift']) ? '' : '1',
            'check_links' => empty($_POST['check_links']) ? '' : '1',
            'webhook_enabled' => empty($_POST['webhook_enabled']) ? '' : '1',
            'preserve_local_edits' => '1',
            'never_publish_automatically' => '1',
            'log_retention' => max(50, min(self::MAX_LOG_ENTRIES, absint($_POST['log_retention'] ?? $defaults['log_retention']))),
        );
        update_option(self::OPTION_KEY, $settings, false);
        $this->append_log('settings_saved', 0, array(
            'scheduled_inspection' => $settings['scheduled_inspection'],
            'scheduled_draft_ingestion' => $settings['scheduled_draft_ingestion'],
            'webhook_enabled' => $settings['webhook_enabled'],
        ));
        $this->redirect_admin(absint($_POST['product_term_id'] ?? 0), array('settings_saved' => 1));
    }

    public function save_mapping_action() {
        if (!$this->can_manage()) {
            wp_die(__('You do not have permission to manage repository synchronization.', 'sustainable-catalyst-feature-suggestions'));
        }
        check_admin_referer('scfs_save_repository_mapping', 'scfs_repository_mapping_nonce');
        $product_id = absint($_POST['product_term_id'] ?? 0);
        $mapping = $this->sanitize_mapping($_POST, $product_id);
        $result = $this->save_mapping($product_id, $mapping);
        $this->redirect_admin($product_id, is_wp_error($result) ? array('error' => rawurlencode($result->get_error_message())) : array('saved' => 1));
    }

    public function preview_sync_action() {
        if (!$this->can_manage()) {
            wp_die(__('You do not have permission to inspect repositories.', 'sustainable-catalyst-feature-suggestions'));
        }
        check_admin_referer('scfs_preview_repository_sync');
        $product_id = absint($_GET['product_term_id'] ?? 0);
        $result = $this->preview_sync($product_id);
        $this->redirect_admin($product_id, is_wp_error($result) ? array('error' => rawurlencode($result->get_error_message())) : array('previewed' => count($result['items'])));
    }

    public function apply_sync_action() {
        if (!$this->can_manage()) {
            wp_die(__('You do not have permission to synchronize repositories.', 'sustainable-catalyst-feature-suggestions'));
        }
        check_admin_referer('scfs_apply_repository_sync');
        $product_id = absint($_GET['product_term_id'] ?? 0);
        $result = $this->apply_sync($product_id);
        $this->redirect_admin($product_id, is_wp_error($result) ? array('error' => rawurlencode($result->get_error_message())) : array('created' => count($result['created']), 'updated' => count($result['updated']), 'failed' => count($result['failed'])));
    }

    public function check_links_action() {
        if (!$this->can_manage()) {
            wp_die(__('You do not have permission to check repository links.', 'sustainable-catalyst-feature-suggestions'));
        }
        check_admin_referer('scfs_check_repository_links');
        $product_id = absint($_GET['product_term_id'] ?? 0);
        $result = $this->check_product_links($product_id, 100);
        $this->redirect_admin($product_id, array('checked' => $result['checked_links'], 'broken' => $result['broken_links']));
    }

    public function export_log_action() {
        if (!$this->can_manage()) {
            wp_die(__('You do not have permission to export synchronization logs.', 'sustainable-catalyst-feature-suggestions'));
        }
        check_admin_referer('scfs_export_repository_sync_log');
        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="scfs-repository-sync-log-' . gmdate('Y-m-d') . '.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, array('Log ID', 'Time GMT', 'Action', 'Product ID', 'Actor ID', 'Context JSON'));
        foreach ((array) get_option(self::LOG_OPTION, array()) as $entry) {
            fputcsv($out, array($entry['id'] ?? '', $entry['at'] ?? '', $entry['action'] ?? '', $entry['product_id'] ?? 0, $entry['actor_id'] ?? 0, wp_json_encode($entry['context'] ?? array())));
        }
        fclose($out);
        exit;
    }

    private function redirect_admin($product_id, $args = array()) {
        wp_safe_redirect(add_query_arg(array_merge(array(
            'post_type' => Sustainable_Catalyst_Feature_Suggestions::POST_TYPE,
            'page' => self::ADMIN_SLUG,
            'product' => absint($product_id),
        ), $args), admin_url('edit.php')));
        exit;
    }

    public function render_admin_page() {
        if (!$this->can_manage()) {
            wp_die(__('You do not have permission to manage repository synchronization.', 'sustainable-catalyst-feature-suggestions'));
        }
        $products = get_terms(array('taxonomy' => SCFS_Product_Integration::PRODUCT_TAXONOMY, 'hide_empty' => false, 'orderby' => 'name'));
        if (is_wp_error($products)) {
            $products = array();
        }
        $selected_id = absint($_GET['product'] ?? 0);
        if (!$selected_id && $products) {
            $selected_id = (int) $products[0]->term_id;
        }
        $selected = $selected_id ? get_term($selected_id, SCFS_Product_Integration::PRODUCT_TAXONOMY) : null;
        $mapping = $selected_id ? $this->mapping($selected_id) : array();
        $state = get_option(self::STATE_OPTION, array());
        $preview = $state[$selected_id] ?? array();
        $drift = $selected_id ? $this->drift_report($selected_id) : array('counts' => array(), 'records' => array());
        $logs = array_slice((array) get_option(self::LOG_OPTION, array()), 0, 20);

        echo '<div class="wrap scfs-repository-sync"><h1>' . esc_html__('Repository and Release Synchronization', 'sustainable-catalyst-feature-suggestions') . '</h1>';
        echo '<p>' . esc_html__('Map public product repositories, inspect documentation and releases, create reviewable WordPress drafts, and detect drift without automatically publishing or overwriting reviewed content.', 'sustainable-catalyst-feature-suggestions') . '</p>';
        $this->render_admin_notices();
        if (!$products) {
            echo '<div class="notice notice-warning"><p>' . esc_html__('Create Product taxonomy terms before mapping repositories.', 'sustainable-catalyst-feature-suggestions') . '</p></div></div>';
            return;
        }
        echo '<form class="scfs-sync-product-picker" method="get"><input type="hidden" name="post_type" value="' . esc_attr(Sustainable_Catalyst_Feature_Suggestions::POST_TYPE) . '"><input type="hidden" name="page" value="' . esc_attr(self::ADMIN_SLUG) . '"><label><span>' . esc_html__('Product', 'sustainable-catalyst-feature-suggestions') . '</span><select name="product">';
        foreach ($products as $product) {
            echo '<option value="' . esc_attr((string) $product->term_id) . '" ' . selected($selected_id, $product->term_id, false) . '>' . esc_html($product->name) . '</option>';
        }
        echo '</select></label><button class="button">' . esc_html__('Open mapping', 'sustainable-catalyst-feature-suggestions') . '</button></form>';

        if ($selected && !is_wp_error($selected)) {
            echo '<div class="scfs-sync-summary-grid"><section><span>' . esc_html__('Repository', 'sustainable-catalyst-feature-suggestions') . '</span><strong>' . esc_html($mapping['repository_url'] ?: __('Not mapped', 'sustainable-catalyst-feature-suggestions')) . '</strong></section><section><span>' . esc_html__('Last inspection', 'sustainable-catalyst-feature-suggestions') . '</span><strong>' . esc_html($mapping['last_preview_at'] ?: __('Never', 'sustainable-catalyst-feature-suggestions')) . '</strong></section><section><span>' . esc_html__('Last synchronization', 'sustainable-catalyst-feature-suggestions') . '</span><strong>' . esc_html($mapping['last_sync_at'] ?: __('Never', 'sustainable-catalyst-feature-suggestions')) . '</strong></section><section><span>' . esc_html__('Drift signals', 'sustainable-catalyst-feature-suggestions') . '</span><strong>' . esc_html((string) array_sum(array_intersect_key($drift['counts'], array_flip(array('local_edit', 'remote_update', 'conflict'))))) . '</strong></section></div>';
            $this->render_mapping_form($selected, $mapping);
            $this->render_actions($selected_id, $mapping);
            $this->render_preview($preview);
            $this->render_drift($drift);
        }
        $this->render_global_settings($selected_id);
        $this->render_log($logs);
        echo '</div>';
    }

    private function render_mapping_form($product, $mapping) {
        echo '<section class="scfs-sync-card"><h2>' . esc_html__('Repository mapping', 'sustainable-catalyst-feature-suggestions') . '</h2><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="scfs_save_repository_mapping"><input type="hidden" name="product_term_id" value="' . esc_attr((string) $product->term_id) . '">';
        wp_nonce_field('scfs_save_repository_mapping', 'scfs_repository_mapping_nonce');
        echo '<table class="form-table" role="presentation"><tr><th><label for="scfs-repository-url">' . esc_html__('Public GitHub repository URL', 'sustainable-catalyst-feature-suggestions') . '</label></th><td><input id="scfs-repository-url" class="regular-text code" type="url" name="repository_url" value="' . esc_attr($mapping['repository_url']) . '" placeholder="https://github.com/Content-Catalyst-LLC/repository"><p class="description">' . esc_html__('Public repositories work without a token. Optional GitHub credentials must be supplied through SCFS_GITHUB_TOKEN, never through this page.', 'sustainable-catalyst-feature-suggestions') . '</p></td></tr>';
        echo '<tr><th><label for="scfs-default-branch">' . esc_html__('Default branch', 'sustainable-catalyst-feature-suggestions') . '</label></th><td><input id="scfs-default-branch" class="regular-text" name="default_branch" value="' . esc_attr($mapping['default_branch']) . '"></td></tr>';
        echo '<tr><th><label for="scfs-readme-path">' . esc_html__('README path', 'sustainable-catalyst-feature-suggestions') . '</label></th><td><input id="scfs-readme-path" class="regular-text code" name="readme_path" value="' . esc_attr($mapping['readme_path']) . '"></td></tr>';
        echo '<tr><th><label for="scfs-changelog-path">' . esc_html__('CHANGELOG path', 'sustainable-catalyst-feature-suggestions') . '</label></th><td><input id="scfs-changelog-path" class="regular-text code" name="changelog_path" value="' . esc_attr($mapping['changelog_path']) . '"></td></tr>';
        echo '<tr><th><label for="scfs-doc-paths">' . esc_html__('Documentation directories', 'sustainable-catalyst-feature-suggestions') . '</label></th><td><textarea id="scfs-doc-paths" class="large-text code" rows="4" name="documentation_paths">' . esc_textarea(implode("\n", $mapping['documentation_paths'])) . '</textarea><p class="description">' . esc_html__('One repository directory per line. Markdown and text files are inspected.', 'sustainable-catalyst-feature-suggestions') . '</p></td></tr>';
        echo '<tr><th><label for="scfs-release-paths">' . esc_html__('Additional release-note files', 'sustainable-catalyst-feature-suggestions') . '</label></th><td><textarea id="scfs-release-paths" class="large-text code" rows="3" name="release_notes_paths">' . esc_textarea(implode("\n", $mapping['release_notes_paths'])) . '</textarea></td></tr></table>';
        echo '<fieldset><legend class="screen-reader-text">' . esc_html__('Synchronization sources', 'sustainable-catalyst-feature-suggestions') . '</legend><label><input type="checkbox" name="enabled" value="1" ' . checked($mapping['enabled'], '1', false) . '> ' . esc_html__('Enable this mapping', 'sustainable-catalyst-feature-suggestions') . '</label><br><label><input type="checkbox" name="sync_readme" value="1" ' . checked($mapping['sync_readme'], '1', false) . '> ' . esc_html__('Inspect README', 'sustainable-catalyst-feature-suggestions') . '</label><br><label><input type="checkbox" name="sync_changelog" value="1" ' . checked($mapping['sync_changelog'], '1', false) . '> ' . esc_html__('Inspect CHANGELOG', 'sustainable-catalyst-feature-suggestions') . '</label><br><label><input type="checkbox" name="sync_releases" value="1" ' . checked($mapping['sync_releases'], '1', false) . '> ' . esc_html__('Inspect GitHub releases', 'sustainable-catalyst-feature-suggestions') . '</label><br><label><input type="checkbox" name="sync_documentation" value="1" ' . checked($mapping['sync_documentation'], '1', false) . '> ' . esc_html__('Inspect documentation paths', 'sustainable-catalyst-feature-suggestions') . '</label></fieldset>';
        submit_button(__('Save repository mapping', 'sustainable-catalyst-feature-suggestions'));
        echo '</form></section>';
    }

    private function render_actions($product_id, $mapping) {
        echo '<section class="scfs-sync-card"><h2>' . esc_html__('Inspection and synchronization', 'sustainable-catalyst-feature-suggestions') . '</h2><p>' . esc_html__('Inspection compares remote sources with WordPress. Synchronization only creates or updates drafts. Published records and local edits are preserved through review copies.', 'sustainable-catalyst-feature-suggestions') . '</p><div class="scfs-sync-actions">';
        $preview_url = wp_nonce_url(add_query_arg(array('action' => 'scfs_preview_repository_sync', 'product_term_id' => $product_id), admin_url('admin-post.php')), 'scfs_preview_repository_sync');
        $sync_url = wp_nonce_url(add_query_arg(array('action' => 'scfs_apply_repository_sync', 'product_term_id' => $product_id), admin_url('admin-post.php')), 'scfs_apply_repository_sync');
        $links_url = wp_nonce_url(add_query_arg(array('action' => 'scfs_check_repository_links', 'product_term_id' => $product_id), admin_url('admin-post.php')), 'scfs_check_repository_links');
        echo '<a class="button button-primary" href="' . esc_url($preview_url) . '">' . esc_html__('Inspect repository', 'sustainable-catalyst-feature-suggestions') . '</a>';
        echo '<a class="button scfs-sync-confirm" data-confirm="' . esc_attr__('Create or update review drafts from the current repository sources?', 'sustainable-catalyst-feature-suggestions') . '" href="' . esc_url($sync_url) . '">' . esc_html__('Create review drafts', 'sustainable-catalyst-feature-suggestions') . '</a>';
        echo '<a class="button" href="' . esc_url($links_url) . '">' . esc_html__('Check content links', 'sustainable-catalyst-feature-suggestions') . '</a></div>';
        if (empty($mapping['repository_url'])) {
            echo '<p class="description">' . esc_html__('Save a repository URL before running inspection.', 'sustainable-catalyst-feature-suggestions') . '</p>';
        }
        echo '<div class="notice notice-info inline"><p><strong>' . esc_html__('Governance boundary:', 'sustainable-catalyst-feature-suggestions') . '</strong> ' . esc_html__('Repository synchronization cannot approve or publish content. Every imported change enters the editorial workflow.', 'sustainable-catalyst-feature-suggestions') . '</p></div></section>';
    }

    private function render_preview($preview) {
        echo '<section class="scfs-sync-card"><h2>' . esc_html__('Latest repository inspection', 'sustainable-catalyst-feature-suggestions') . '</h2>';
        if (!$preview) {
            echo '<p>' . esc_html__('No inspection has been run for this product.', 'sustainable-catalyst-feature-suggestions') . '</p></section>';
            return;
        }
        echo '<div class="scfs-sync-counts">';
        foreach ((array) ($preview['counts'] ?? array()) as $state => $count) {
            echo '<div><strong>' . esc_html((string) $count) . '</strong><span>' . esc_html(ucwords(str_replace('_', ' ', $state))) . '</span></div>';
        }
        echo '</div><div class="scfs-sync-table-wrap" tabindex="0"><table class="widefat striped"><thead><tr><th>' . esc_html__('Record', 'sustainable-catalyst-feature-suggestions') . '</th><th>' . esc_html__('Source', 'sustainable-catalyst-feature-suggestions') . '</th><th>' . esc_html__('Decision', 'sustainable-catalyst-feature-suggestions') . '</th><th>' . esc_html__('Reason', 'sustainable-catalyst-feature-suggestions') . '</th></tr></thead><tbody>';
        foreach ((array) ($preview['items'] ?? array()) as $item) {
            $decision = $item['decision'] ?? array();
            echo '<tr><td><strong>' . esc_html($item['title'] ?? '') . '</strong><br><code>' . esc_html($item['record_type'] ?? '') . '</code></td><td>';
            if (!empty($item['external_url'])) {
                echo '<a href="' . esc_url($item['external_url']) . '" target="_blank" rel="noopener">' . esc_html($item['source_path'] ?? $item['external_id']) . '</a>';
            } else {
                echo esc_html($item['source_path'] ?? $item['external_id']);
            }
            echo '</td><td><span class="scfs-sync-state scfs-sync-state--' . esc_attr($decision['state'] ?? 'unknown') . '">' . esc_html(ucwords(str_replace('_', ' ', $decision['state'] ?? 'unknown'))) . '</span></td><td>' . esc_html($decision['reason'] ?? '') . '</td></tr>';
        }
        echo '</tbody></table></div></section>';
    }

    private function render_drift($drift) {
        echo '<section class="scfs-sync-card"><h2>' . esc_html__('Documentation drift', 'sustainable-catalyst-feature-suggestions') . '</h2><div class="scfs-sync-counts">';
        foreach ((array) ($drift['counts'] ?? array()) as $state => $count) {
            echo '<div><strong>' . esc_html((string) $count) . '</strong><span>' . esc_html(ucwords(str_replace('_', ' ', $state))) . '</span></div>';
        }
        echo '</div>';
        if (empty($drift['records'])) {
            echo '<p>' . esc_html__('No synchronized WordPress records exist for this product.', 'sustainable-catalyst-feature-suggestions') . '</p>';
        } else {
            echo '<div class="scfs-sync-table-wrap" tabindex="0"><table class="widefat striped"><thead><tr><th>' . esc_html__('Record', 'sustainable-catalyst-feature-suggestions') . '</th><th>' . esc_html__('Status', 'sustainable-catalyst-feature-suggestions') . '</th><th>' . esc_html__('Source', 'sustainable-catalyst-feature-suggestions') . '</th><th>' . esc_html__('Last seen', 'sustainable-catalyst-feature-suggestions') . '</th></tr></thead><tbody>';
            foreach (array_slice($drift['records'], 0, 50) as $record) {
                echo '<tr><td><a href="' . esc_url(get_edit_post_link($record['post_id'])) . '">' . esc_html($record['title']) . '</a></td><td>' . esc_html(ucwords(str_replace('_', ' ', $record['state']))) . '</td><td><code>' . esc_html($record['source_path']) . '</code></td><td>' . esc_html($record['last_seen_at']) . '</td></tr>';
            }
            echo '</tbody></table></div>';
        }
        echo '</section>';
    }

    private function render_global_settings($product_id) {
        $settings = $this->settings();
        $webhook_secret_available = $this->webhook_secret() !== '';
        echo '<section class="scfs-sync-card"><h2>' . esc_html__('Synchronization settings', 'sustainable-catalyst-feature-suggestions') . '</h2><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="scfs_save_repository_sync_settings"><input type="hidden" name="product_term_id" value="' . esc_attr((string) $product_id) . '">';
        wp_nonce_field('scfs_save_repository_sync_settings', 'scfs_repository_sync_settings_nonce');
        echo '<table class="form-table" role="presentation"><tr><th><label for="scfs-sync-default-branch">' . esc_html__('Default branch', 'sustainable-catalyst-feature-suggestions') . '</label></th><td><input id="scfs-sync-default-branch" class="regular-text" name="default_branch" value="' . esc_attr($settings['default_branch']) . '"></td></tr>';
        echo '<tr><th><label for="scfs-sync-timeout">' . esc_html__('Request timeout', 'sustainable-catalyst-feature-suggestions') . '</label></th><td><input id="scfs-sync-timeout" type="number" min="5" max="30" name="request_timeout" value="' . esc_attr((string) $settings['request_timeout']) . '"> ' . esc_html__('seconds', 'sustainable-catalyst-feature-suggestions') . '</td></tr>';
        echo '<tr><th><label for="scfs-sync-max-releases">' . esc_html__('Maximum releases per inspection', 'sustainable-catalyst-feature-suggestions') . '</label></th><td><input id="scfs-sync-max-releases" type="number" min="1" max="' . esc_attr((string) self::MAX_RELEASES) . '" name="maximum_releases" value="' . esc_attr((string) $settings['maximum_releases']) . '"></td></tr>';
        echo '<tr><th><label for="scfs-sync-max-documents">' . esc_html__('Maximum documents per inspection', 'sustainable-catalyst-feature-suggestions') . '</label></th><td><input id="scfs-sync-max-documents" type="number" min="1" max="' . esc_attr((string) self::MAX_DOCUMENTS) . '" name="maximum_documents" value="' . esc_attr((string) $settings['maximum_documents']) . '"></td></tr>';
        echo '<tr><th><label for="scfs-sync-log-retention">' . esc_html__('Synchronization log entries', 'sustainable-catalyst-feature-suggestions') . '</label></th><td><input id="scfs-sync-log-retention" type="number" min="50" max="' . esc_attr((string) self::MAX_LOG_ENTRIES) . '" name="log_retention" value="' . esc_attr((string) $settings['log_retention']) . '"></td></tr></table>';
        echo '<fieldset><legend><strong>' . esc_html__('Automation boundaries', 'sustainable-catalyst-feature-suggestions') . '</strong></legend><label><input type="checkbox" name="scheduled_inspection" value="1" ' . checked($settings['scheduled_inspection'], '1', false) . '> ' . esc_html__('Run daily repository inspection', 'sustainable-catalyst-feature-suggestions') . '</label><br><label><input type="checkbox" name="scheduled_draft_ingestion" value="1" ' . checked($settings['scheduled_draft_ingestion'], '1', false) . '> ' . esc_html__('Allow scheduled tasks to create review drafts', 'sustainable-catalyst-feature-suggestions') . '</label><p class="description">' . esc_html__('Scheduled ingestion can create drafts only. It cannot approve, schedule, or publish content.', 'sustainable-catalyst-feature-suggestions') . '</p><label><input type="checkbox" name="submit_for_editorial_review" value="1" ' . checked($settings['submit_for_editorial_review'], '1', false) . '> ' . esc_html__('Submit synchronized drafts to the editorial review queue', 'sustainable-catalyst-feature-suggestions') . '</label><br><label><input type="checkbox" name="detect_drift" value="1" ' . checked($settings['detect_drift'], '1', false) . '> ' . esc_html__('Enable documentation drift reporting', 'sustainable-catalyst-feature-suggestions') . '</label><br><label><input type="checkbox" name="check_links" value="1" ' . checked($settings['check_links'], '1', false) . '> ' . esc_html__('Enable repository content link checks', 'sustainable-catalyst-feature-suggestions') . '</label><br><label><input type="checkbox" name="webhook_enabled" value="1" ' . checked($settings['webhook_enabled'], '1', false) . ' ' . disabled($webhook_secret_available, false, false) . '> ' . esc_html__('Enable signed GitHub webhook queue', 'sustainable-catalyst-feature-suggestions') . '</label><p class="description">' . ($webhook_secret_available ? esc_html__('SCFS_GITHUB_WEBHOOK_SECRET is available.', 'sustainable-catalyst-feature-suggestions') : esc_html__('Define SCFS_GITHUB_WEBHOOK_SECRET before enabling webhooks.', 'sustainable-catalyst-feature-suggestions')) . '</p></fieldset>';
        submit_button(__('Save synchronization settings', 'sustainable-catalyst-feature-suggestions'));
        echo '</form></section>';
    }

    private function render_log($logs) {
        echo '<section class="scfs-sync-card"><div class="scfs-sync-card-head"><div><h2>' . esc_html__('Synchronization log', 'sustainable-catalyst-feature-suggestions') . '</h2><p>' . esc_html__('Bounded operational history. Repository tokens and webhook secrets are never logged.', 'sustainable-catalyst-feature-suggestions') . '</p></div>';
        $export = wp_nonce_url(add_query_arg(array('action' => 'scfs_export_repository_sync_log'), admin_url('admin-post.php')), 'scfs_export_repository_sync_log');
        echo '<a class="button" href="' . esc_url($export) . '">' . esc_html__('Export CSV', 'sustainable-catalyst-feature-suggestions') . '</a></div>';
        if (!$logs) {
            echo '<p>' . esc_html__('No synchronization activity has been recorded.', 'sustainable-catalyst-feature-suggestions') . '</p></section>';
            return;
        }
        echo '<div class="scfs-sync-table-wrap" tabindex="0"><table class="widefat striped"><thead><tr><th>' . esc_html__('Time', 'sustainable-catalyst-feature-suggestions') . '</th><th>' . esc_html__('Action', 'sustainable-catalyst-feature-suggestions') . '</th><th>' . esc_html__('Product', 'sustainable-catalyst-feature-suggestions') . '</th><th>' . esc_html__('Context', 'sustainable-catalyst-feature-suggestions') . '</th></tr></thead><tbody>';
        foreach ($logs as $entry) {
            echo '<tr><td>' . esc_html($entry['at'] ?? '') . '</td><td>' . esc_html(ucwords(str_replace('_', ' ', $entry['action'] ?? ''))) . '</td><td>' . esc_html((string) ($entry['product_id'] ?? 0)) . '</td><td><code>' . esc_html(wp_json_encode($entry['context'] ?? array())) . '</code></td></tr>';
        }
        echo '</tbody></table></div></section>';
    }

    private function render_admin_notices() {
        if (!empty($_GET['settings_saved'])) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Repository synchronization settings saved.', 'sustainable-catalyst-feature-suggestions') . '</p></div>';
        }
        if (!empty($_GET['saved'])) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Repository mapping saved.', 'sustainable-catalyst-feature-suggestions') . '</p></div>';
        }
        if (isset($_GET['previewed'])) {
            echo '<div class="notice notice-success is-dismissible"><p>' . sprintf(esc_html__('Repository inspection completed with %d candidate records.', 'sustainable-catalyst-feature-suggestions'), absint($_GET['previewed'])) . '</p></div>';
        }
        if (isset($_GET['created']) || isset($_GET['updated'])) {
            echo '<div class="notice notice-success is-dismissible"><p>' . sprintf(esc_html__('Repository synchronization created %1$d drafts and updated %2$d drafts. %3$d records failed.', 'sustainable-catalyst-feature-suggestions'), absint($_GET['created'] ?? 0), absint($_GET['updated'] ?? 0), absint($_GET['failed'] ?? 0)) . '</p></div>';
        }
        if (isset($_GET['checked'])) {
            echo '<div class="notice notice-info is-dismissible"><p>' . sprintf(esc_html__('Checked %1$d links; %2$d require attention.', 'sustainable-catalyst-feature-suggestions'), absint($_GET['checked']), absint($_GET['broken'] ?? 0)) . '</p></div>';
        }
        if (!empty($_GET['error'])) {
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html(sanitize_text_field(wp_unslash($_GET['error']))) . '</p></div>';
        }
    }

    public function cli_preview($args, $assoc_args) {
        $product_id = absint($assoc_args['product'] ?? ($args[0] ?? 0));
        $result = $this->preview_sync($product_id);
        if (is_wp_error($result)) {
            WP_CLI::error($result->get_error_message());
        }
        WP_CLI::success(wp_json_encode($result));
    }

    public function cli_sync($args, $assoc_args) {
        $product_id = absint($assoc_args['product'] ?? ($args[0] ?? 0));
        $result = $this->apply_sync($product_id);
        if (is_wp_error($result)) {
            WP_CLI::error($result->get_error_message());
        }
        WP_CLI::success(wp_json_encode($result));
    }

    public function cli_links($args, $assoc_args) {
        $product_id = absint($assoc_args['product'] ?? ($args[0] ?? 0));
        WP_CLI::success(wp_json_encode($this->check_product_links($product_id, absint($assoc_args['limit'] ?? 100))));
    }
}
