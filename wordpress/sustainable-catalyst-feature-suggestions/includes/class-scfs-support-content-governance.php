<?php
/**
 * Support Content Operations and Editorial Governance.
 *
 * Coordinates content ownership, technical verification, review cadence,
 * publication readiness, supersession, audit history, and governance queues
 * across Support Articles, Known Issues, and Release Records. The layer builds
 * on the existing content-operations, editorial-workflow, and article-integrity
 * systems without replacing their identifiers or stored data.
 *
 * @package Sustainable_Catalyst_Feature_Suggestions
 */

if (!defined('ABSPATH')) {
    exit;
}

final class SCFS_Support_Content_Governance {
    const VERSION = '5.5.0';
    const SCHEMA = 'scfs-support-content-governance/1.0';
    const ADMIN_PAGE = 'scfs-content-governance';
    const SETTINGS_OPTION = 'scfs_content_governance_settings';
    const SUMMARY_OPTION = 'scfs_content_governance_summary';
    const SCHEMA_OPTION = 'scfs_content_governance_schema';
    const LAST_SCAN_OPTION = 'scfs_content_governance_last_scan';
    const CRON_HOOK = 'scfs_content_governance_daily';
    const CRON_LOCK = 'scfs_content_governance_lock';
    const NONCE_ACTION = 'scfs_support_content_governance';
    const NONCE_NAME = 'scfs_support_content_governance_nonce';
    const MAX_HISTORY_ENTRIES = 50;

    const META_OWNER = '_scfs_content_owner_id';
    const META_TECHNICAL_OWNER = '_scfs_content_technical_owner_id';
    const META_PRIORITY = '_scfs_content_governance_priority';
    const META_CADENCE = '_scfs_content_review_cadence_days';
    const META_VERIFICATION_STATE = '_scfs_content_verification_state';
    const META_LAST_VERIFIED = '_scfs_content_last_verified_at';
    const META_NEXT_REVIEW = '_scfs_content_next_review_at';
    const META_VERIFICATION_NOTE = '_scfs_content_verification_note';
    const META_SUPERSEDES = '_scfs_content_supersedes_id';
    const META_SUPERSEDED_BY = '_scfs_content_superseded_by_id';
    const META_HISTORY = '_scfs_content_governance_history';
    const META_SNAPSHOT = '_scfs_content_governance_snapshot';
    const META_LAST_SCANNED = '_scfs_content_governance_last_scanned_at';
    const META_SCHEMA = '_scfs_content_governance_schema';

    private static $instance = null;
    private static $saving = false;

    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('init', array($this, 'register_meta'), 14);
        add_action('admin_menu', array($this, 'register_admin_page'), 31);
        add_action('admin_enqueue_scripts', array($this, 'admin_assets'));
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'), 45);
        add_action('save_post', array($this, 'save_record_meta'), 95, 3);
        add_action('admin_post_scfs_content_governance_scan', array($this, 'scan_action'));
        add_action('admin_post_scfs_content_governance_bulk', array($this, 'bulk_action'));
        add_action('admin_post_scfs_content_governance_verify', array($this, 'verify_action'));
        add_action('admin_post_scfs_content_governance_export', array($this, 'export_action'));
        add_action('rest_api_init', array($this, 'register_rest_routes'));
        add_action(self::CRON_HOOK, array($this, 'run_scheduled_scan'));

        foreach ($this->managed_post_types() as $post_type) {
            add_filter('manage_' . $post_type . '_posts_columns', array($this, 'admin_columns'), 80);
            add_action('manage_' . $post_type . '_posts_custom_column', array($this, 'admin_column_content'), 80, 2);
        }

        if (defined('WP_CLI') && WP_CLI && class_exists('WP_CLI')) {
            WP_CLI::add_command('scfs content-governance scan', array($this, 'cli_scan'));
            WP_CLI::add_command('scfs content-governance queue', array($this, 'cli_queue'));
            WP_CLI::add_command('scfs content-governance verify', array($this, 'cli_verify'));
        }
    }

    public static function activate() {
        $instance = self::instance();
        $instance->register_meta();
        if (!get_option(self::SETTINGS_OPTION)) {
            add_option(self::SETTINGS_OPTION, $instance->default_settings(), '', false);
        }
        update_option(self::SCHEMA_OPTION, self::SCHEMA, false);
        if (function_exists('wp_next_scheduled') && function_exists('wp_schedule_event') && !wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK);
        }
    }

    public static function deactivate() {
        if (!function_exists('wp_next_scheduled')) {
            return;
        }
        $timestamp = wp_next_scheduled(self::CRON_HOOK);
        if ($timestamp && function_exists('wp_unschedule_event')) {
            wp_unschedule_event($timestamp, self::CRON_HOOK);
        }
        if (function_exists('delete_transient')) {
            delete_transient(self::CRON_LOCK);
        }
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

    public function default_settings() {
        return array(
            'default_review_cadence_days' => 180,
            'review_warning_days' => 30,
            'minimum_integrity_score' => 80,
            'require_content_owner' => '1',
            'require_technical_owner_for_issues' => '1',
            'require_verification_note' => '1',
            'automatic_next_review_date' => '1',
            'daily_scan_enabled' => '1',
            'include_non_public_records' => '1',
            'email_overdue_summary' => '0',
            'email_recipient' => '',
            'maximum_history_entries' => self::MAX_HISTORY_ENTRIES,
        );
    }

    public function settings() {
        $saved = function_exists('get_option') ? (array) get_option(self::SETTINGS_OPTION, array()) : array();
        return function_exists('wp_parse_args')
            ? wp_parse_args($saved, $this->default_settings())
            : array_merge($this->default_settings(), $saved);
    }

    public function priorities() {
        return array(
            'low' => __('Low', 'sustainable-catalyst-feature-suggestions'),
            'normal' => __('Normal', 'sustainable-catalyst-feature-suggestions'),
            'high' => __('High', 'sustainable-catalyst-feature-suggestions'),
            'critical' => __('Critical', 'sustainable-catalyst-feature-suggestions'),
        );
    }

    public function verification_states() {
        return array(
            'not_verified' => __('Not verified', 'sustainable-catalyst-feature-suggestions'),
            'review_required' => __('Review required', 'sustainable-catalyst-feature-suggestions'),
            'verified' => __('Verified', 'sustainable-catalyst-feature-suggestions'),
            'verified_with_limitations' => __('Verified with limitations', 'sustainable-catalyst-feature-suggestions'),
            'superseded' => __('Superseded', 'sustainable-catalyst-feature-suggestions'),
        );
    }

    public function queue_states() {
        return array(
            'publication_blocked' => __('Publication blocked', 'sustainable-catalyst-feature-suggestions'),
            'overdue' => __('Review overdue', 'sustainable-catalyst-feature-suggestions'),
            'due_soon' => __('Review due soon', 'sustainable-catalyst-feature-suggestions'),
            'unassigned' => __('Ownership required', 'sustainable-catalyst-feature-suggestions'),
            'review_required' => __('Review required', 'sustainable-catalyst-feature-suggestions'),
            'ready_for_verification' => __('Ready for verification', 'sustainable-catalyst-feature-suggestions'),
            'verified' => __('Verified', 'sustainable-catalyst-feature-suggestions'),
            'superseded' => __('Superseded', 'sustainable-catalyst-feature-suggestions'),
        );
    }

    public function sanitize_priority($value) {
        $value = sanitize_key((string) $value);
        return isset($this->priorities()[$value]) ? $value : 'normal';
    }

    public function sanitize_verification_state($value) {
        $value = sanitize_key((string) $value);
        return isset($this->verification_states()[$value]) ? $value : 'not_verified';
    }

    public function sanitize_date($value) {
        $value = sanitize_text_field((string) $value);
        if ($value === '') {
            return '';
        }
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : '';
    }

    public function sanitize_history($value) {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : array();
        }
        $history = array();
        foreach ((array) $value as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $history[] = array(
                'event' => sanitize_key((string) ($entry['event'] ?? 'updated')),
                'at' => sanitize_text_field((string) ($entry['at'] ?? '')),
                'actor_id' => absint($entry['actor_id'] ?? 0),
                'state' => $this->sanitize_verification_state($entry['state'] ?? 'not_verified'),
                'note' => sanitize_textarea_field((string) ($entry['note'] ?? '')),
                'content_hash' => sanitize_text_field((string) ($entry['content_hash'] ?? '')),
            );
        }
        $maximum = max(5, min(200, absint($this->settings()['maximum_history_entries'])));
        return array_slice($history, -$maximum);
    }

    public function register_meta() {
        $definitions = array(
            self::META_OWNER => array('type' => 'integer', 'sanitize_callback' => 'absint'),
            self::META_TECHNICAL_OWNER => array('type' => 'integer', 'sanitize_callback' => 'absint'),
            self::META_PRIORITY => array('type' => 'string', 'sanitize_callback' => array($this, 'sanitize_priority')),
            self::META_CADENCE => array('type' => 'integer', 'sanitize_callback' => 'absint'),
            self::META_VERIFICATION_STATE => array('type' => 'string', 'sanitize_callback' => array($this, 'sanitize_verification_state')),
            self::META_LAST_VERIFIED => array('type' => 'string', 'sanitize_callback' => array($this, 'sanitize_date')),
            self::META_NEXT_REVIEW => array('type' => 'string', 'sanitize_callback' => array($this, 'sanitize_date')),
            self::META_VERIFICATION_NOTE => array('type' => 'string', 'sanitize_callback' => 'sanitize_textarea_field'),
            self::META_SUPERSEDES => array('type' => 'integer', 'sanitize_callback' => 'absint'),
            self::META_SUPERSEDED_BY => array('type' => 'integer', 'sanitize_callback' => 'absint'),
            self::META_HISTORY => array('type' => 'array', 'sanitize_callback' => array($this, 'sanitize_history')),
            self::META_SNAPSHOT => array('type' => 'string', 'sanitize_callback' => 'sanitize_textarea_field'),
            self::META_LAST_SCANNED => array('type' => 'string', 'sanitize_callback' => 'sanitize_text_field'),
            self::META_SCHEMA => array('type' => 'string', 'sanitize_callback' => 'sanitize_text_field'),
        );
        foreach ($this->managed_post_types() as $post_type) {
            foreach ($definitions as $key => $definition) {
                $show_in_rest = true;
                if ($definition['type'] === 'array') {
                    $show_in_rest = array(
                        'schema' => array(
                            'type' => 'array',
                            'items' => array('type' => 'object'),
                        ),
                    );
                }
                register_post_meta($post_type, $key, array(
                    'type' => $definition['type'],
                    'single' => true,
                    'show_in_rest' => $show_in_rest,
                    'sanitize_callback' => $definition['sanitize_callback'],
                    'auth_callback' => function () {
                        return current_user_can($this->manage_capability());
                    },
                ));
            }
        }
    }

    public function manage_capability() {
        return apply_filters('scfs_content_governance_capability', 'edit_others_posts');
    }

    public function verify_capability() {
        return apply_filters('scfs_content_governance_verify_capability', 'publish_posts');
    }

    public function can_manage() {
        return current_user_can($this->manage_capability());
    }

    public function can_verify() {
        return current_user_can($this->verify_capability());
    }

    public function register_admin_page() {
        add_submenu_page(
            'edit.php?post_type=' . Sustainable_Catalyst_Feature_Suggestions::POST_TYPE,
            __('Content Operations', 'sustainable-catalyst-feature-suggestions'),
            __('Content Operations', 'sustainable-catalyst-feature-suggestions'),
            $this->manage_capability(),
            self::ADMIN_PAGE,
            array($this, 'render_admin_page')
        );
    }

    public function admin_assets($hook) {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        $managed = $screen && in_array((string) $screen->post_type, $this->managed_post_types(), true);
        if (!$managed && strpos((string) $hook, self::ADMIN_PAGE) === false) {
            return;
        }
        $css = dirname(__DIR__) . '/assets/support-content-governance.css';
        $js = dirname(__DIR__) . '/assets/support-content-governance.js';
        wp_enqueue_style(
            'scfs-support-content-governance',
            plugins_url('../assets/support-content-governance.css', __FILE__),
            array('scfs-editorial-governance', 'scfs-support-article-integrity'),
            is_readable($css) ? (string) filemtime($css) : self::VERSION
        );
        wp_enqueue_script(
            'scfs-support-content-governance',
            plugins_url('../assets/support-content-governance.js', __FILE__),
            array(),
            is_readable($js) ? (string) filemtime($js) : self::VERSION,
            true
        );
    }

    public function add_meta_boxes() {
        foreach ($this->managed_post_types() as $post_type) {
            add_meta_box(
                'scfs-support-content-governance',
                __('Content Ownership and Verification', 'sustainable-catalyst-feature-suggestions'),
                array($this, 'render_meta_box'),
                $post_type,
                'side',
                'high'
            );
        }
    }

    private function author_users() {
        return get_users(array(
            'orderby' => 'display_name',
            'order' => 'ASC',
            'who' => 'authors',
        ));
    }

    private function render_user_select($name, $label, $selected, $users) {
        echo '<label class="scfs-cg-field"><span>' . esc_html($label) . '</span><select name="' . esc_attr($name) . '">';
        echo '<option value="0">' . esc_html__('Not assigned', 'sustainable-catalyst-feature-suggestions') . '</option>';
        foreach ($users as $user) {
            echo '<option value="' . esc_attr((string) $user->ID) . '" ' . selected((int) $selected, (int) $user->ID, false) . '>' . esc_html($user->display_name) . '</option>';
        }
        echo '</select></label>';
    }

    public function render_meta_box($post) {
        if (!$this->can_manage()) {
            echo '<p>' . esc_html__('You do not have permission to manage content governance.', 'sustainable-catalyst-feature-suggestions') . '</p>';
            return;
        }
        wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME);
        $record = $this->record($post->ID);
        $users = $this->author_users();
        echo '<div class="scfs-cg-meta">';
        echo '<p class="scfs-cg-state scfs-cg-state--' . esc_attr($record['queue_state']) . '"><strong>' . esc_html($record['queue_label']) . '</strong><br>' . esc_html(sprintf(__('Governance score: %d/100', 'sustainable-catalyst-feature-suggestions'), $record['governance_score'])) . '</p>';
        $this->render_user_select('scfs_content_owner_id', __('Content owner', 'sustainable-catalyst-feature-suggestions'), $record['owner_id'], $users);
        $this->render_user_select('scfs_content_technical_owner_id', __('Technical owner', 'sustainable-catalyst-feature-suggestions'), $record['technical_owner_id'], $users);
        echo '<label class="scfs-cg-field"><span>' . esc_html__('Priority', 'sustainable-catalyst-feature-suggestions') . '</span><select name="scfs_content_governance_priority">';
        foreach ($this->priorities() as $key => $label) {
            echo '<option value="' . esc_attr($key) . '" ' . selected($record['priority'], $key, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select></label>';
        echo '<label class="scfs-cg-field"><span>' . esc_html__('Review cadence (days)', 'sustainable-catalyst-feature-suggestions') . '</span><input type="number" min="1" max="1825" name="scfs_content_review_cadence_days" value="' . esc_attr((string) $record['review_cadence_days']) . '"></label>';
        echo '<label class="scfs-cg-field"><span>' . esc_html__('Verification state', 'sustainable-catalyst-feature-suggestions') . '</span><select name="scfs_content_verification_state">';
        foreach ($this->verification_states() as $key => $label) {
            echo '<option value="' . esc_attr($key) . '" ' . selected($record['verification_state'], $key, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select></label>';
        echo '<label class="scfs-cg-field"><span>' . esc_html__('Last verified', 'sustainable-catalyst-feature-suggestions') . '</span><input type="date" name="scfs_content_last_verified_at" value="' . esc_attr($record['last_verified_at']) . '"></label>';
        echo '<label class="scfs-cg-field"><span>' . esc_html__('Next review', 'sustainable-catalyst-feature-suggestions') . '</span><input type="date" name="scfs_content_next_review_at" value="' . esc_attr($record['next_review_at']) . '"></label>';
        echo '<label class="scfs-cg-field"><span>' . esc_html__('Verification note', 'sustainable-catalyst-feature-suggestions') . '</span><textarea rows="3" name="scfs_content_verification_note">' . esc_textarea($record['verification_note']) . '</textarea></label>';
        echo '<label class="scfs-cg-field"><span>' . esc_html__('Supersedes record ID', 'sustainable-catalyst-feature-suggestions') . '</span><input type="number" min="0" name="scfs_content_supersedes_id" value="' . esc_attr((string) $record['supersedes_id']) . '"></label>';
        echo '<label class="scfs-cg-field"><span>' . esc_html__('Superseded by record ID', 'sustainable-catalyst-feature-suggestions') . '</span><input type="number" min="0" name="scfs_content_superseded_by_id" value="' . esc_attr((string) $record['superseded_by_id']) . '"></label>';
        if ($record['blockers']) {
            echo '<div class="scfs-cg-blockers"><strong>' . esc_html__('Required before verification', 'sustainable-catalyst-feature-suggestions') . '</strong><ul>';
            foreach ($record['blockers'] as $blocker) {
                echo '<li>' . esc_html($this->humanize_code($blocker)) . '</li>';
            }
            echo '</ul></div>';
        }
        echo '<p class="description">' . esc_html__('Verification does not publish content or change public URLs. Existing editorial workflow and integrity checks remain authoritative.', 'sustainable-catalyst-feature-suggestions') . '</p>';
        echo '</div>';
    }

    public function save_record_meta($post_id, $post, $update) {
        if (self::$saving || !$post || !in_array($post->post_type, $this->managed_post_types(), true)) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
            return;
        }
        $has_form = isset($_POST[self::NONCE_NAME]);
        if ($has_form && (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[self::NONCE_NAME])), self::NONCE_ACTION) || !current_user_can('edit_post', $post_id))) {
            return;
        }

        self::$saving = true;
        $before = $this->record($post_id);
        if ($has_form) {
            update_post_meta($post_id, self::META_OWNER, absint($_POST['scfs_content_owner_id'] ?? 0));
            update_post_meta($post_id, self::META_TECHNICAL_OWNER, absint($_POST['scfs_content_technical_owner_id'] ?? 0));
            update_post_meta($post_id, self::META_PRIORITY, $this->sanitize_priority($_POST['scfs_content_governance_priority'] ?? 'normal'));
            $cadence = max(1, min(1825, absint($_POST['scfs_content_review_cadence_days'] ?? 0)));
            update_post_meta($post_id, self::META_CADENCE, $cadence);
            $new_state = $this->sanitize_verification_state($_POST['scfs_content_verification_state'] ?? 'not_verified');
            update_post_meta($post_id, self::META_VERIFICATION_STATE, $new_state);
            update_post_meta($post_id, self::META_LAST_VERIFIED, $this->sanitize_date($_POST['scfs_content_last_verified_at'] ?? ''));
            update_post_meta($post_id, self::META_NEXT_REVIEW, $this->sanitize_date($_POST['scfs_content_next_review_at'] ?? ''));
            update_post_meta($post_id, self::META_VERIFICATION_NOTE, sanitize_textarea_field(wp_unslash($_POST['scfs_content_verification_note'] ?? '')));
            update_post_meta($post_id, self::META_SUPERSEDES, absint($_POST['scfs_content_supersedes_id'] ?? 0));
            update_post_meta($post_id, self::META_SUPERSEDED_BY, absint($_POST['scfs_content_superseded_by_id'] ?? 0));
            $this->maintain_supersession($post_id);
            if ($new_state !== $before['verification_state'] || $this->sanitize_date($_POST['scfs_content_last_verified_at'] ?? '') !== $before['last_verified_at']) {
                $this->append_history($post_id, 'verification_state_changed', sanitize_textarea_field(wp_unslash($_POST['scfs_content_verification_note'] ?? '')), $new_state);
            }
        }
        $this->maintain_defaults($post_id, $post);
        $this->store_snapshot($post_id);
        self::$saving = false;
    }

    private function maintain_defaults($post_id, $post) {
        $settings = $this->settings();
        if (!get_post_meta($post_id, self::META_OWNER, true)) {
            update_post_meta($post_id, self::META_OWNER, absint($post->post_author));
        }
        if (!get_post_meta($post_id, self::META_PRIORITY, true)) {
            update_post_meta($post_id, self::META_PRIORITY, 'normal');
        }
        $cadence = absint(get_post_meta($post_id, self::META_CADENCE, true));
        if (!$cadence) {
            $cadence = max(1, absint($settings['default_review_cadence_days']));
            update_post_meta($post_id, self::META_CADENCE, $cadence);
        }
        if (!get_post_meta($post_id, self::META_VERIFICATION_STATE, true)) {
            update_post_meta($post_id, self::META_VERIFICATION_STATE, 'not_verified');
        }
        if (!get_post_meta($post_id, self::META_NEXT_REVIEW, true) && $settings['automatic_next_review_date'] === '1') {
            $base = (string) get_post_meta($post_id, self::META_LAST_VERIFIED, true);
            $timestamp = $base ? strtotime($base . ' 00:00:00 UTC') : time();
            update_post_meta($post_id, self::META_NEXT_REVIEW, gmdate('Y-m-d', $timestamp + ($cadence * DAY_IN_SECONDS)));
        }
        update_post_meta($post_id, self::META_SCHEMA, self::SCHEMA);
    }

    private function maintain_supersession($post_id) {
        $supersedes = absint(get_post_meta($post_id, self::META_SUPERSEDES, true));
        $superseded_by = absint(get_post_meta($post_id, self::META_SUPERSEDED_BY, true));
        if ($supersedes && $supersedes !== $post_id && in_array(get_post_type($supersedes), $this->managed_post_types(), true)) {
            update_post_meta($supersedes, self::META_SUPERSEDED_BY, $post_id);
            update_post_meta($supersedes, self::META_VERIFICATION_STATE, 'superseded');
            update_post_meta($supersedes, '_scfs_content_lifecycle', 'superseded');
        }
        if ($superseded_by && $superseded_by !== $post_id && in_array(get_post_type($superseded_by), $this->managed_post_types(), true)) {
            update_post_meta($superseded_by, self::META_SUPERSEDES, $post_id);
            update_post_meta($post_id, self::META_VERIFICATION_STATE, 'superseded');
            update_post_meta($post_id, '_scfs_content_lifecycle', 'superseded');
        }
    }

    private function content_hash($post_id) {
        $post = get_post($post_id);
        if (!$post) {
            return '';
        }
        return hash('sha256', implode('|', array(
            $post->post_title,
            $post->post_content,
            $post->post_excerpt,
            $post->post_modified_gmt,
        )));
    }

    private function append_history($post_id, $event, $note = '', $state = '') {
        $history = $this->sanitize_history(get_post_meta($post_id, self::META_HISTORY, true));
        $history[] = array(
            'event' => sanitize_key($event),
            'at' => current_time('mysql', true),
            'actor_id' => function_exists('get_current_user_id') ? get_current_user_id() : 0,
            'state' => $this->sanitize_verification_state($state ?: get_post_meta($post_id, self::META_VERIFICATION_STATE, true)),
            'note' => sanitize_textarea_field($note),
            'content_hash' => $this->content_hash($post_id),
        );
        update_post_meta($post_id, self::META_HISTORY, $this->sanitize_history($history));
    }

    private function store_snapshot($post_id) {
        $record = $this->record($post_id, false);
        $snapshot = array(
            'schema' => self::SCHEMA,
            'record_id' => $post_id,
            'queue_state' => $record['queue_state'],
            'governance_score' => $record['governance_score'],
            'verification_state' => $record['verification_state'],
            'workflow_state' => $record['workflow_state'],
            'integrity_score' => $record['integrity_score'],
            'blockers' => $record['blockers'],
            'required_actions' => $record['required_actions'],
            'generated_at' => current_time('mysql', true),
        );
        update_post_meta($post_id, self::META_SNAPSHOT, wp_json_encode($snapshot));
        update_post_meta($post_id, self::META_LAST_SCANNED, current_time('mysql', true));
        update_post_meta($post_id, self::META_SCHEMA, self::SCHEMA);
    }

    private function user_name($user_id) {
        $user_id = absint($user_id);
        if (!$user_id) {
            return '';
        }
        $user = get_user_by('id', $user_id);
        return $user ? $user->display_name : '';
    }

    private function workflow_state($post_id) {
        if (class_exists('SCFS_Editorial_Governance')) {
            return SCFS_Editorial_Governance::instance()->state_for_post($post_id);
        }
        $status = get_post_status($post_id);
        return $status === 'publish' ? 'published' : ($status === 'future' ? 'scheduled' : 'draft');
    }

    private function integrity_record($post_id) {
        $score = (int) get_post_meta($post_id, '_scfs_integrity_score', true);
        $state = (string) get_post_meta($post_id, '_scfs_integrity_state', true);
        if ($state === '') {
            $state = 'unscanned';
        }
        return array(
            'score' => $score,
            'state' => $state,
            'stale' => (bool) get_post_meta($post_id, '_scfs_integrity_stale', true),
        );
    }

    private function product_names($post_id) {
        if (!class_exists('SCFS_Product_Integration')) {
            return array();
        }
        $terms = wp_get_object_terms($post_id, SCFS_Product_Integration::PRODUCT_TAXONOMY, array('fields' => 'names'));
        return is_wp_error($terms) ? array() : array_values(array_filter(array_map('strval', $terms)));
    }

    private function days_until($date) {
        if (!$date) {
            return null;
        }
        $timestamp = strtotime($date . ' 23:59:59 UTC');
        if (!$timestamp) {
            return null;
        }
        return (int) floor(($timestamp - time()) / DAY_IN_SECONDS);
    }

    public function assess_record($post_id) {
        $post = get_post($post_id);
        if (!$post || !in_array($post->post_type, $this->managed_post_types(), true)) {
            return array(
                'queue_state' => 'publication_blocked',
                'governance_score' => 0,
                'blockers' => array('record_invalid'),
                'required_actions' => array('select_valid_record'),
            );
        }
        $settings = $this->settings();
        $owner_id = absint(get_post_meta($post_id, self::META_OWNER, true));
        $technical_owner_id = absint(get_post_meta($post_id, self::META_TECHNICAL_OWNER, true));
        $verification_state = $this->sanitize_verification_state(get_post_meta($post_id, self::META_VERIFICATION_STATE, true));
        $next_review = $this->sanitize_date(get_post_meta($post_id, self::META_NEXT_REVIEW, true));
        $last_verified = $this->sanitize_date(get_post_meta($post_id, self::META_LAST_VERIFIED, true));
        $workflow = $this->workflow_state($post_id);
        $integrity = $this->integrity_record($post_id);
        $superseded_by = absint(get_post_meta($post_id, self::META_SUPERSEDED_BY, true));
        $days_until = $this->days_until($next_review);
        $blockers = array();
        $actions = array();
        $score = 100;

        if ($superseded_by || $verification_state === 'superseded') {
            return array(
                'queue_state' => 'superseded',
                'governance_score' => 100,
                'blockers' => array(),
                'required_actions' => array(),
            );
        }
        if ($settings['require_content_owner'] === '1' && !$owner_id) {
            $blockers[] = 'content_owner_required';
            $actions[] = 'assign_content_owner';
            $score -= 20;
        }
        if ($settings['require_technical_owner_for_issues'] === '1' && class_exists('SCFS_Knowledge_Base_Foundation') && $post->post_type === SCFS_Knowledge_Base_Foundation::ISSUE_POST_TYPE && !$technical_owner_id) {
            $blockers[] = 'technical_owner_required';
            $actions[] = 'assign_technical_owner';
            $score -= 20;
        }
        if (in_array($workflow, array('changes_requested', 'expired'), true)) {
            $blockers[] = 'editorial_workflow_blocked';
            $actions[] = 'resolve_editorial_workflow';
            $score -= 30;
        }
        if ($post->post_type === (class_exists('SCFS_Knowledge_Base_Foundation') ? SCFS_Knowledge_Base_Foundation::ARTICLE_POST_TYPE : '') && $integrity['state'] !== 'unscanned') {
            if ($integrity['state'] === 'publication_blocked' || $integrity['score'] < absint($settings['minimum_integrity_score'])) {
                $blockers[] = 'integrity_threshold_not_met';
                $actions[] = 'resolve_article_integrity';
                $score -= 30;
            } elseif ($integrity['stale']) {
                $actions[] = 'refresh_integrity_scan';
                $score -= 10;
            }
        }
        if (!$next_review) {
            $actions[] = 'set_next_review_date';
            $score -= 10;
        } elseif ($days_until !== null && $days_until < 0) {
            $blockers[] = 'review_overdue';
            $actions[] = 'complete_verification_review';
            $score -= 30;
        } elseif ($days_until !== null && $days_until <= absint($settings['review_warning_days'])) {
            $actions[] = 'schedule_verification_review';
            $score -= 10;
        }
        if (!$last_verified || !in_array($verification_state, array('verified', 'verified_with_limitations'), true)) {
            $actions[] = 'verify_content';
            $score -= 15;
        }

        $score = max(0, min(100, $score));
        if (in_array('editorial_workflow_blocked', $blockers, true) || in_array('integrity_threshold_not_met', $blockers, true)) {
            $queue_state = 'publication_blocked';
        } elseif (in_array('review_overdue', $blockers, true)) {
            $queue_state = 'overdue';
        } elseif (in_array('content_owner_required', $blockers, true) || in_array('technical_owner_required', $blockers, true)) {
            $queue_state = 'unassigned';
        } elseif ($days_until !== null && $days_until <= absint($settings['review_warning_days'])) {
            $queue_state = 'due_soon';
        } elseif (in_array($verification_state, array('verified', 'verified_with_limitations'), true) && !$blockers) {
            $queue_state = 'verified';
        } elseif (!$blockers && $score >= 80) {
            $queue_state = 'ready_for_verification';
        } else {
            $queue_state = 'review_required';
        }
        return array(
            'queue_state' => $queue_state,
            'governance_score' => $score,
            'blockers' => array_values(array_unique($blockers)),
            'required_actions' => array_values(array_unique($actions)),
        );
    }

    public function record($post_id, $include_history = true) {
        $post = get_post($post_id);
        if (!$post) {
            return array();
        }
        $assessment = $this->assess_record($post_id);
        $queue_state = $assessment['queue_state'];
        $cadence = absint(get_post_meta($post_id, self::META_CADENCE, true));
        if (!$cadence) {
            $cadence = max(1, absint($this->settings()['default_review_cadence_days']));
        }
        $integrity = $this->integrity_record($post_id);
        $owner_id = absint(get_post_meta($post_id, self::META_OWNER, true));
        $technical_owner_id = absint(get_post_meta($post_id, self::META_TECHNICAL_OWNER, true));
        $record = array(
            'schema' => self::SCHEMA,
            'version' => self::VERSION,
            'record_id' => (int) $post_id,
            'post_type' => $post->post_type,
            'post_status' => $post->post_status,
            'title' => get_the_title($post),
            'public_url' => get_permalink($post),
            'edit_url' => get_edit_post_link($post_id, 'raw'),
            'products' => $this->product_names($post_id),
            'owner_id' => $owner_id,
            'owner_name' => $this->user_name($owner_id),
            'technical_owner_id' => $technical_owner_id,
            'technical_owner_name' => $this->user_name($technical_owner_id),
            'priority' => $this->sanitize_priority(get_post_meta($post_id, self::META_PRIORITY, true)),
            'review_cadence_days' => $cadence,
            'verification_state' => $this->sanitize_verification_state(get_post_meta($post_id, self::META_VERIFICATION_STATE, true)),
            'verification_note' => (string) get_post_meta($post_id, self::META_VERIFICATION_NOTE, true),
            'last_verified_at' => $this->sanitize_date(get_post_meta($post_id, self::META_LAST_VERIFIED, true)),
            'next_review_at' => $this->sanitize_date(get_post_meta($post_id, self::META_NEXT_REVIEW, true)),
            'days_until_review' => $this->days_until($this->sanitize_date(get_post_meta($post_id, self::META_NEXT_REVIEW, true))),
            'workflow_state' => $this->workflow_state($post_id),
            'integrity_score' => $integrity['score'],
            'integrity_state' => $integrity['state'],
            'integrity_stale' => $integrity['stale'],
            'supersedes_id' => absint(get_post_meta($post_id, self::META_SUPERSEDES, true)),
            'superseded_by_id' => absint(get_post_meta($post_id, self::META_SUPERSEDED_BY, true)),
            'queue_state' => $queue_state,
            'queue_label' => $this->queue_states()[$queue_state] ?? $queue_state,
            'governance_score' => $assessment['governance_score'],
            'blockers' => $assessment['blockers'],
            'required_actions' => $assessment['required_actions'],
            'last_scanned_at' => (string) get_post_meta($post_id, self::META_LAST_SCANNED, true),
            'human_review_required' => true,
            'automatic_publication' => false,
        );
        if ($include_history) {
            $record['verification_history'] = $this->sanitize_history(get_post_meta($post_id, self::META_HISTORY, true));
        }
        return $record;
    }

    private function query_ids($args = array()) {
        $settings = $this->settings();
        $defaults = array(
            'post_type' => $this->managed_post_types(),
            'post_status' => $settings['include_non_public_records'] === '1'
                ? array('publish', 'draft', 'pending', 'future', 'private')
                : array('publish'),
            'posts_per_page' => -1,
            'fields' => 'ids',
            'orderby' => 'modified',
            'order' => 'DESC',
            'no_found_rows' => true,
        );
        $query = new WP_Query(array_merge($defaults, $args));
        return array_map('absint', $query->posts);
    }

    public function scan_all($store = true) {
        $summary = array(
            'schema' => self::SCHEMA,
            'version' => self::VERSION,
            'total' => 0,
            'state_counts' => array_fill_keys(array_keys($this->queue_states()), 0),
            'priority_counts' => array_fill_keys(array_keys($this->priorities()), 0),
            'post_type_counts' => array(),
            'generated_at' => current_time('mysql', true),
            'human_review_required' => true,
            'automatic_publication' => false,
        );
        foreach ($this->query_ids() as $post_id) {
            $post = get_post($post_id);
            if (!$post) {
                continue;
            }
            $this->maintain_defaults($post_id, $post);
            $this->store_snapshot($post_id);
            $record = $this->record($post_id, false);
            $summary['total']++;
            $summary['state_counts'][$record['queue_state']] = ($summary['state_counts'][$record['queue_state']] ?? 0) + 1;
            $summary['priority_counts'][$record['priority']] = ($summary['priority_counts'][$record['priority']] ?? 0) + 1;
            $summary['post_type_counts'][$record['post_type']] = ($summary['post_type_counts'][$record['post_type']] ?? 0) + 1;
        }
        if ($store) {
            update_option(self::SUMMARY_OPTION, $summary, false);
            update_option(self::LAST_SCAN_OPTION, $summary['generated_at'], false);
        }
        return $summary;
    }

    public function queue_records($filters = array()) {
        $records = array();
        $queue_state = sanitize_key((string) ($filters['queue_state'] ?? ''));
        $priority = sanitize_key((string) ($filters['priority'] ?? ''));
        $post_type = sanitize_key((string) ($filters['post_type'] ?? ''));
        $owner_id = absint($filters['owner_id'] ?? 0);
        $query_args = array();
        if ($post_type && in_array($post_type, $this->managed_post_types(), true)) {
            $query_args['post_type'] = array($post_type);
        }
        foreach ($this->query_ids($query_args) as $post_id) {
            $record = $this->record($post_id, false);
            if ($queue_state && $record['queue_state'] !== $queue_state) {
                continue;
            }
            if ($priority && $record['priority'] !== $priority) {
                continue;
            }
            if ($owner_id && $record['owner_id'] !== $owner_id) {
                continue;
            }
            $records[] = $record;
        }
        usort($records, static function ($a, $b) {
            $order = array('publication_blocked' => 0, 'overdue' => 1, 'unassigned' => 2, 'due_soon' => 3, 'review_required' => 4, 'ready_for_verification' => 5, 'verified' => 6, 'superseded' => 7);
            $left = $order[$a['queue_state']] ?? 99;
            $right = $order[$b['queue_state']] ?? 99;
            if ($left === $right) {
                return strcmp((string) $a['title'], (string) $b['title']);
            }
            return $left <=> $right;
        });
        return $records;
    }

    public function verify_record($post_id, $state = 'verified', $note = '', $actor_id = 0) {
        $post_id = absint($post_id);
        $post = get_post($post_id);
        if (!$post || !in_array($post->post_type, $this->managed_post_types(), true)) {
            return new WP_Error('scfs_content_governance_record_invalid', __('The support content record could not be found.', 'sustainable-catalyst-feature-suggestions'));
        }
        $state = $this->sanitize_verification_state($state);
        if (!in_array($state, array('verified', 'verified_with_limitations'), true)) {
            return new WP_Error('scfs_content_governance_state_invalid', __('Verification must use a verified state.', 'sustainable-catalyst-feature-suggestions'));
        }
        $note = sanitize_textarea_field($note);
        if ($this->settings()['require_verification_note'] === '1' && $note === '') {
            return new WP_Error('scfs_content_governance_note_required', __('Add a verification note before marking this record as verified.', 'sustainable-catalyst-feature-suggestions'));
        }
        $assessment = $this->assess_record($post_id);
        $hard_blockers = array_intersect($assessment['blockers'], array('record_invalid', 'content_owner_required', 'technical_owner_required', 'editorial_workflow_blocked', 'integrity_threshold_not_met'));
        if ($hard_blockers) {
            return new WP_Error('scfs_content_governance_blocked', __('Resolve ownership, workflow, and integrity blockers before verification.', 'sustainable-catalyst-feature-suggestions'), $hard_blockers);
        }
        $actor_id = absint($actor_id);
        if (!$actor_id && function_exists('get_current_user_id')) {
            $actor_id = get_current_user_id();
        }
        $today = gmdate('Y-m-d');
        $cadence = absint(get_post_meta($post_id, self::META_CADENCE, true));
        if (!$cadence) {
            $cadence = max(1, absint($this->settings()['default_review_cadence_days']));
        }
        update_post_meta($post_id, self::META_VERIFICATION_STATE, $state);
        update_post_meta($post_id, self::META_LAST_VERIFIED, $today);
        update_post_meta($post_id, self::META_NEXT_REVIEW, gmdate('Y-m-d', time() + ($cadence * DAY_IN_SECONDS)));
        update_post_meta($post_id, self::META_VERIFICATION_NOTE, $note);
        update_post_meta($post_id, '_scfs_verified_at', $today);
        update_post_meta($post_id, '_scfs_review_due_at', gmdate('Y-m-d', time() + ($cadence * DAY_IN_SECONDS)));
        $this->append_history($post_id, 'verified', $note, $state);
        $this->store_snapshot($post_id);
        do_action('scfs_content_governance_verified', $post_id, $state, $actor_id, $note);
        return $this->record($post_id);
    }

    public function apply_bulk_action($record_ids, $action, $value = '', $note = '') {
        $ids = array_values(array_unique(array_filter(array_map('absint', (array) $record_ids))));
        $action = sanitize_key($action);
        $result = array('updated' => array(), 'failed' => array());
        foreach ($ids as $post_id) {
            if (!in_array(get_post_type($post_id), $this->managed_post_types(), true)) {
                $result['failed'][$post_id] = 'invalid_record';
                continue;
            }
            if ($action === 'verify') {
                $verified = $this->verify_record($post_id, 'verified', $note);
                if (is_wp_error($verified)) {
                    $result['failed'][$post_id] = $verified->get_error_code();
                } else {
                    $result['updated'][] = $post_id;
                }
                continue;
            }
            if ($action === 'request_review') {
                update_post_meta($post_id, self::META_VERIFICATION_STATE, 'review_required');
                $this->append_history($post_id, 'review_requested', $note, 'review_required');
            } elseif ($action === 'set_priority') {
                update_post_meta($post_id, self::META_PRIORITY, $this->sanitize_priority($value));
                $this->append_history($post_id, 'priority_changed', $note, get_post_meta($post_id, self::META_VERIFICATION_STATE, true));
            } elseif ($action === 'assign_owner') {
                update_post_meta($post_id, self::META_OWNER, absint($value));
                $this->append_history($post_id, 'owner_assigned', $note, get_post_meta($post_id, self::META_VERIFICATION_STATE, true));
            } elseif ($action === 'assign_technical_owner') {
                update_post_meta($post_id, self::META_TECHNICAL_OWNER, absint($value));
                $this->append_history($post_id, 'technical_owner_assigned', $note, get_post_meta($post_id, self::META_VERIFICATION_STATE, true));
            } elseif ($action === 'set_cadence') {
                $cadence = max(1, min(1825, absint($value)));
                update_post_meta($post_id, self::META_CADENCE, $cadence);
                update_post_meta($post_id, self::META_NEXT_REVIEW, gmdate('Y-m-d', time() + ($cadence * DAY_IN_SECONDS)));
                $this->append_history($post_id, 'review_cadence_changed', $note, get_post_meta($post_id, self::META_VERIFICATION_STATE, true));
            } else {
                $result['failed'][$post_id] = 'unsupported_action';
                continue;
            }
            $this->store_snapshot($post_id);
            $result['updated'][] = $post_id;
        }
        return $result;
    }

    public function render_admin_page() {
        if (!$this->can_manage()) {
            wp_die(esc_html__('You do not have permission to view content operations.', 'sustainable-catalyst-feature-suggestions'));
        }
        $summary = get_option(self::SUMMARY_OPTION, array());
        if (!$summary || !isset($summary['state_counts'])) {
            $summary = $this->scan_all(true);
        }
        $filters = array(
            'queue_state' => sanitize_key(wp_unslash($_GET['queue_state'] ?? '')),
            'priority' => sanitize_key(wp_unslash($_GET['priority'] ?? '')),
            'post_type' => sanitize_key(wp_unslash($_GET['content_type'] ?? '')),
            'owner_id' => absint($_GET['owner_id'] ?? 0),
        );
        $records = $this->queue_records($filters);
        $users = $this->author_users();
        echo '<div class="wrap scfs-cg-admin">';
        echo '<h1>' . esc_html__('Support Content Operations', 'sustainable-catalyst-feature-suggestions') . '</h1>';
        echo '<p>' . esc_html__('Coordinate ownership, verification, review cadence, publication readiness, supersession, and editorial follow-up across public support content.', 'sustainable-catalyst-feature-suggestions') . '</p>';
        $this->render_admin_notices();
        echo '<div class="scfs-cg-summary">';
        $cards = array(
            'total' => array(__('Managed records', 'sustainable-catalyst-feature-suggestions'), (int) ($summary['total'] ?? 0)),
            'publication_blocked' => array(__('Publication blocked', 'sustainable-catalyst-feature-suggestions'), (int) ($summary['state_counts']['publication_blocked'] ?? 0)),
            'overdue' => array(__('Review overdue', 'sustainable-catalyst-feature-suggestions'), (int) ($summary['state_counts']['overdue'] ?? 0)),
            'due_soon' => array(__('Due soon', 'sustainable-catalyst-feature-suggestions'), (int) ($summary['state_counts']['due_soon'] ?? 0)),
            'unassigned' => array(__('Ownership required', 'sustainable-catalyst-feature-suggestions'), (int) ($summary['state_counts']['unassigned'] ?? 0)),
            'verified' => array(__('Verified', 'sustainable-catalyst-feature-suggestions'), (int) ($summary['state_counts']['verified'] ?? 0)),
        );
        foreach ($cards as $key => $card) {
            echo '<article class="scfs-cg-summary__card scfs-cg-summary__card--' . esc_attr($key) . '"><span>' . esc_html($card[0]) . '</span><strong>' . esc_html((string) $card[1]) . '</strong></article>';
        }
        echo '</div>';
        echo '<div class="scfs-cg-toolbar">';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="scfs_content_governance_scan">';
        wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME);
        submit_button(__('Run governance scan', 'sustainable-catalyst-feature-suggestions'), 'secondary', 'submit', false);
        echo '</form>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="scfs_content_governance_export">';
        wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME);
        submit_button(__('Export queue CSV', 'sustainable-catalyst-feature-suggestions'), 'secondary', 'submit', false);
        echo '</form></div>';

        echo '<form method="get" class="scfs-cg-filters"><input type="hidden" name="post_type" value="' . esc_attr(Sustainable_Catalyst_Feature_Suggestions::POST_TYPE) . '"><input type="hidden" name="page" value="' . esc_attr(self::ADMIN_PAGE) . '">';
        echo '<select name="queue_state"><option value="">' . esc_html__('All queue states', 'sustainable-catalyst-feature-suggestions') . '</option>';
        foreach ($this->queue_states() as $key => $label) {
            echo '<option value="' . esc_attr($key) . '" ' . selected($filters['queue_state'], $key, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select><select name="priority"><option value="">' . esc_html__('All priorities', 'sustainable-catalyst-feature-suggestions') . '</option>';
        foreach ($this->priorities() as $key => $label) {
            echo '<option value="' . esc_attr($key) . '" ' . selected($filters['priority'], $key, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select><select name="content_type"><option value="">' . esc_html__('All content types', 'sustainable-catalyst-feature-suggestions') . '</option>';
        foreach ($this->managed_post_types() as $type) {
            $object = get_post_type_object($type);
            echo '<option value="' . esc_attr($type) . '" ' . selected($filters['post_type'], $type, false) . '>' . esc_html($object ? $object->labels->singular_name : $type) . '</option>';
        }
        echo '</select><select name="owner_id"><option value="0">' . esc_html__('All owners', 'sustainable-catalyst-feature-suggestions') . '</option>';
        foreach ($users as $user) {
            echo '<option value="' . esc_attr((string) $user->ID) . '" ' . selected($filters['owner_id'], (int) $user->ID, false) . '>' . esc_html($user->display_name) . '</option>';
        }
        echo '</select>';
        submit_button(__('Filter', 'sustainable-catalyst-feature-suggestions'), 'secondary', 'submit', false);
        echo '</form>';

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="scfs-cg-bulk">';
        echo '<input type="hidden" name="action" value="scfs_content_governance_bulk">';
        wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME);
        echo '<div class="scfs-cg-bulk__controls"><select name="bulk_action"><option value="">' . esc_html__('Bulk action', 'sustainable-catalyst-feature-suggestions') . '</option><option value="request_review">' . esc_html__('Request review', 'sustainable-catalyst-feature-suggestions') . '</option><option value="verify">' . esc_html__('Mark verified', 'sustainable-catalyst-feature-suggestions') . '</option><option value="set_priority">' . esc_html__('Set priority', 'sustainable-catalyst-feature-suggestions') . '</option><option value="assign_owner">' . esc_html__('Assign content owner', 'sustainable-catalyst-feature-suggestions') . '</option><option value="assign_technical_owner">' . esc_html__('Assign technical owner', 'sustainable-catalyst-feature-suggestions') . '</option><option value="set_cadence">' . esc_html__('Set review cadence', 'sustainable-catalyst-feature-suggestions') . '</option></select><input type="text" name="bulk_value" placeholder="' . esc_attr__('Value or user ID', 'sustainable-catalyst-feature-suggestions') . '"><input type="text" name="bulk_note" placeholder="' . esc_attr__('Governance note', 'sustainable-catalyst-feature-suggestions') . '">';
        submit_button(__('Apply', 'sustainable-catalyst-feature-suggestions'), 'secondary', 'submit', false);
        echo '</div>';
        echo '<table class="widefat striped scfs-cg-table"><thead><tr><td class="check-column"><input type="checkbox" data-scfs-cg-select-all></td><th>' . esc_html__('Content', 'sustainable-catalyst-feature-suggestions') . '</th><th>' . esc_html__('State', 'sustainable-catalyst-feature-suggestions') . '</th><th>' . esc_html__('Owner', 'sustainable-catalyst-feature-suggestions') . '</th><th>' . esc_html__('Verification', 'sustainable-catalyst-feature-suggestions') . '</th><th>' . esc_html__('Next review', 'sustainable-catalyst-feature-suggestions') . '</th><th>' . esc_html__('Score', 'sustainable-catalyst-feature-suggestions') . '</th></tr></thead><tbody>';
        if (!$records) {
            echo '<tr><td colspan="7">' . esc_html__('No support content matches the selected filters.', 'sustainable-catalyst-feature-suggestions') . '</td></tr>';
        }
        foreach ($records as $record) {
            echo '<tr><th class="check-column"><input type="checkbox" name="record_ids[]" value="' . esc_attr((string) $record['record_id']) . '"></th><td><strong><a href="' . esc_url($record['edit_url']) . '">' . esc_html($record['title']) . '</a></strong><br><span class="description">' . esc_html($record['post_type'] . ' · ' . implode(', ', $record['products'])) . '</span></td><td><span class="scfs-cg-pill scfs-cg-pill--' . esc_attr($record['queue_state']) . '">' . esc_html($record['queue_label']) . '</span></td><td>' . esc_html($record['owner_name'] ?: __('Unassigned', 'sustainable-catalyst-feature-suggestions')) . '</td><td>' . esc_html($this->verification_states()[$record['verification_state']] ?? $record['verification_state']) . '</td><td>' . esc_html($record['next_review_at'] ?: '—') . '</td><td><strong>' . esc_html((string) $record['governance_score']) . '</strong>/100</td></tr>';
        }
        echo '</tbody></table></form>';
        echo '<p class="description">' . esc_html(sprintf(__('Last full scan: %s', 'sustainable-catalyst-feature-suggestions'), (string) get_option(self::LAST_SCAN_OPTION, __('Not yet scanned', 'sustainable-catalyst-feature-suggestions')))) . '</p>';
        echo '</div>';
    }

    private function render_admin_notices() {
        $message = sanitize_key(wp_unslash($_GET['scfs_cg_message'] ?? ''));
        if (!$message) {
            return;
        }
        $messages = array(
            'scan_complete' => __('The content-governance scan completed.', 'sustainable-catalyst-feature-suggestions'),
            'bulk_complete' => __('The selected governance action completed.', 'sustainable-catalyst-feature-suggestions'),
            'verified' => __('The support content record was verified.', 'sustainable-catalyst-feature-suggestions'),
            'action_failed' => __('The governance action could not be completed. Review the record requirements and try again.', 'sustainable-catalyst-feature-suggestions'),
        );
        if (isset($messages[$message])) {
            echo '<div class="notice ' . ($message === 'action_failed' ? 'notice-error' : 'notice-success') . ' is-dismissible"><p>' . esc_html($messages[$message]) . '</p></div>';
        }
    }

    private function admin_redirect($message) {
        wp_safe_redirect(add_query_arg(array(
            'post_type' => Sustainable_Catalyst_Feature_Suggestions::POST_TYPE,
            'page' => self::ADMIN_PAGE,
            'scfs_cg_message' => sanitize_key($message),
        ), admin_url('edit.php')));
        exit;
    }

    private function verify_admin_request() {
        if (!$this->can_manage()) {
            wp_die(esc_html__('You do not have permission to manage content governance.', 'sustainable-catalyst-feature-suggestions'));
        }
        check_admin_referer(self::NONCE_ACTION, self::NONCE_NAME);
    }

    public function scan_action() {
        $this->verify_admin_request();
        $this->scan_all(true);
        $this->admin_redirect('scan_complete');
    }

    public function bulk_action() {
        $this->verify_admin_request();
        $action = sanitize_key(wp_unslash($_POST['bulk_action'] ?? ''));
        $value = sanitize_text_field(wp_unslash($_POST['bulk_value'] ?? ''));
        $note = sanitize_textarea_field(wp_unslash($_POST['bulk_note'] ?? ''));
        $ids = array_map('absint', (array) ($_POST['record_ids'] ?? array()));
        if ($action === 'verify' && !$this->can_verify()) {
            $this->admin_redirect('action_failed');
        }
        $result = $this->apply_bulk_action($ids, $action, $value, $note);
        $this->scan_all(true);
        $this->admin_redirect($result['updated'] ? 'bulk_complete' : 'action_failed');
    }

    public function verify_action() {
        $this->verify_admin_request();
        if (!$this->can_verify()) {
            $this->admin_redirect('action_failed');
        }
        $post_id = absint($_POST['record_id'] ?? 0);
        $state = $this->sanitize_verification_state(wp_unslash($_POST['verification_state'] ?? 'verified'));
        $note = sanitize_textarea_field(wp_unslash($_POST['verification_note'] ?? ''));
        $result = $this->verify_record($post_id, $state, $note);
        $this->scan_all(true);
        $this->admin_redirect(is_wp_error($result) ? 'action_failed' : 'verified');
    }

    public function export_action() {
        $this->verify_admin_request();
        $records = $this->queue_records();
        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="support-content-governance-' . gmdate('Y-m-d-His') . '.csv"');
        $output = fopen('php://output', 'w');
        fputcsv($output, array('Record ID', 'Title', 'Post Type', 'Status', 'Products', 'Queue State', 'Priority', 'Owner', 'Technical Owner', 'Verification State', 'Last Verified', 'Next Review', 'Workflow State', 'Integrity Score', 'Governance Score', 'Blockers', 'Required Actions', 'Edit URL', 'Public URL'));
        foreach ($records as $record) {
            fputcsv($output, array(
                $record['record_id'],
                $record['title'],
                $record['post_type'],
                $record['post_status'],
                implode(' | ', $record['products']),
                $record['queue_state'],
                $record['priority'],
                $record['owner_name'],
                $record['technical_owner_name'],
                $record['verification_state'],
                $record['last_verified_at'],
                $record['next_review_at'],
                $record['workflow_state'],
                $record['integrity_score'],
                $record['governance_score'],
                implode(' | ', $record['blockers']),
                implode(' | ', $record['required_actions']),
                $record['edit_url'],
                $record['public_url'],
            ));
        }
        fclose($output);
        exit;
    }

    public function run_scheduled_scan() {
        if ($this->settings()['daily_scan_enabled'] !== '1') {
            return;
        }
        if (get_transient(self::CRON_LOCK)) {
            return;
        }
        set_transient(self::CRON_LOCK, '1', 30 * MINUTE_IN_SECONDS);
        $previous = (array) get_option(self::SUMMARY_OPTION, array());
        $summary = $this->scan_all(true);
        delete_transient(self::CRON_LOCK);
        do_action('scfs_content_governance_scan_completed', $summary, $previous);
        if ($this->settings()['email_overdue_summary'] === '1' && ($summary['state_counts']['overdue'] ?? 0) > 0) {
            $recipient = sanitize_email($this->settings()['email_recipient']);
            if (!$recipient) {
                $recipient = get_option('admin_email');
            }
            if ($recipient && function_exists('wp_mail')) {
                wp_mail(
                    $recipient,
                    __('Support content review queue requires attention', 'sustainable-catalyst-feature-suggestions'),
                    sprintf(
                        __('%1$d records are overdue and %2$d records are publication blocked. Review the Content Operations queue in WordPress.', 'sustainable-catalyst-feature-suggestions'),
                        (int) ($summary['state_counts']['overdue'] ?? 0),
                        (int) ($summary['state_counts']['publication_blocked'] ?? 0)
                    )
                );
            }
        }
    }

    public function admin_columns($columns) {
        $columns['scfs_content_governance'] = __('Governance', 'sustainable-catalyst-feature-suggestions');
        return $columns;
    }

    public function admin_column_content($column, $post_id) {
        if ($column !== 'scfs_content_governance') {
            return;
        }
        $record = $this->record($post_id, false);
        echo '<span class="scfs-cg-pill scfs-cg-pill--' . esc_attr($record['queue_state']) . '">' . esc_html($record['queue_label']) . '</span><br><small>' . esc_html(sprintf(__('%d/100 · %s', 'sustainable-catalyst-feature-suggestions'), $record['governance_score'], $record['next_review_at'] ?: __('No review date', 'sustainable-catalyst-feature-suggestions'))) . '</small>';
    }

    public function register_rest_routes() {
        register_rest_route(Sustainable_Catalyst_Feature_Suggestions::REST_NAMESPACE, '/content-governance/schema', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'rest_schema'),
            'permission_callback' => '__return_true',
        ));
        register_rest_route(Sustainable_Catalyst_Feature_Suggestions::REST_NAMESPACE, '/content-governance/queue', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'rest_queue'),
            'permission_callback' => array($this, 'rest_manage_permission'),
        ));
        register_rest_route(Sustainable_Catalyst_Feature_Suggestions::REST_NAMESPACE, '/content-governance/record/(?P<id>\d+)', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'rest_record'),
            'permission_callback' => array($this, 'rest_manage_permission'),
        ));
        register_rest_route(Sustainable_Catalyst_Feature_Suggestions::REST_NAMESPACE, '/content-governance/scan', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'rest_scan'),
            'permission_callback' => array($this, 'rest_manage_permission'),
        ));
        register_rest_route(Sustainable_Catalyst_Feature_Suggestions::REST_NAMESPACE, '/content-governance/bulk', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'rest_bulk'),
            'permission_callback' => array($this, 'rest_manage_permission'),
        ));
        register_rest_route(Sustainable_Catalyst_Feature_Suggestions::REST_NAMESPACE, '/content-governance/verify/(?P<id>\d+)', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'rest_verify'),
            'permission_callback' => array($this, 'rest_verify_permission'),
        ));
    }

    public function rest_manage_permission() {
        return current_user_can($this->manage_capability());
    }

    public function rest_verify_permission() {
        return current_user_can($this->verify_capability());
    }

    public function rest_schema() {
        return rest_ensure_response(array(
            'schema' => self::SCHEMA,
            'version' => self::VERSION,
            'record_types' => $this->managed_post_types(),
            'queue_states' => array_keys($this->queue_states()),
            'verification_states' => array_keys($this->verification_states()),
            'priorities' => array_keys($this->priorities()),
            'public_record_data_exposed' => false,
            'automatic_publication' => false,
            'automatic_editorial_approval' => false,
            'human_review_required' => true,
        ));
    }

    public function rest_queue(WP_REST_Request $request) {
        return rest_ensure_response(array(
            'schema' => self::SCHEMA,
            'summary' => (array) get_option(self::SUMMARY_OPTION, array()),
            'records' => $this->queue_records(array(
                'queue_state' => $request->get_param('queue_state'),
                'priority' => $request->get_param('priority'),
                'post_type' => $request->get_param('post_type'),
                'owner_id' => $request->get_param('owner_id'),
            )),
        ));
    }

    public function rest_record(WP_REST_Request $request) {
        $record = $this->record(absint($request['id']));
        if (!$record) {
            return new WP_Error('scfs_content_governance_not_found', __('The support content record could not be found.', 'sustainable-catalyst-feature-suggestions'), array('status' => 404));
        }
        return rest_ensure_response($record);
    }

    public function rest_scan() {
        return rest_ensure_response($this->scan_all(true));
    }

    public function rest_bulk(WP_REST_Request $request) {
        $params = (array) $request->get_json_params();
        return rest_ensure_response($this->apply_bulk_action(
            (array) ($params['record_ids'] ?? array()),
            (string) ($params['action'] ?? ''),
            (string) ($params['value'] ?? ''),
            (string) ($params['note'] ?? '')
        ));
    }

    public function rest_verify(WP_REST_Request $request) {
        $params = (array) $request->get_json_params();
        $result = $this->verify_record(
            absint($request['id']),
            (string) ($params['state'] ?? 'verified'),
            (string) ($params['note'] ?? '')
        );
        return is_wp_error($result) ? $result : rest_ensure_response($result);
    }

    public function cli_scan($args, $assoc_args) {
        $summary = $this->scan_all(true);
        WP_CLI::success(sprintf('Scanned %d support content records.', (int) $summary['total']));
        WP_CLI::log(wp_json_encode($summary, JSON_PRETTY_PRINT));
    }

    public function cli_queue($args, $assoc_args) {
        $records = $this->queue_records(array(
            'queue_state' => $assoc_args['state'] ?? '',
            'priority' => $assoc_args['priority'] ?? '',
            'post_type' => $assoc_args['post_type'] ?? '',
            'owner_id' => $assoc_args['owner'] ?? 0,
        ));
        $rows = array_map(static function ($record) {
            return array(
                'id' => $record['record_id'],
                'type' => $record['post_type'],
                'title' => $record['title'],
                'state' => $record['queue_state'],
                'score' => $record['governance_score'],
                'owner' => $record['owner_name'],
                'next_review' => $record['next_review_at'],
            );
        }, $records);
        WP_CLI\Utils\format_items($assoc_args['format'] ?? 'table', $rows, array('id', 'type', 'title', 'state', 'score', 'owner', 'next_review'));
    }

    public function cli_verify($args, $assoc_args) {
        $post_id = absint($args[0] ?? 0);
        $note = (string) ($assoc_args['note'] ?? 'Verified through WP-CLI.');
        $state = (string) ($assoc_args['state'] ?? 'verified');
        $result = $this->verify_record($post_id, $state, $note);
        if (is_wp_error($result)) {
            WP_CLI::error($result->get_error_message());
        }
        WP_CLI::success(sprintf('Verified support content record %d.', $post_id));
    }

    private function humanize_code($code) {
        return ucwords(str_replace('_', ' ', (string) $code));
    }
}
