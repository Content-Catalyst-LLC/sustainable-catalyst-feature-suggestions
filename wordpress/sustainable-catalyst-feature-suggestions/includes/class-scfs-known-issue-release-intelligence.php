<?php
/**
 * Known Issue and Release Intelligence Integration.
 *
 * Connects published Known Issues, Support Articles, affected versions,
 * workarounds, target releases, fixed releases, and changelog evidence while
 * preserving the existing CPTs, taxonomies, URLs, REST namespace, and data.
 *
 * @package Sustainable_Catalyst_Feature_Suggestions
 */

if (!defined('ABSPATH')) {
    exit;
}

final class SCFS_Known_Issue_Release_Intelligence {
    const VERSION = '5.4.0';
    const SCHEMA = 'scfs-known-issue-release-intelligence/1.0';
    const SHORTCODE = 'scfs_issue_release_intelligence';
    const LEGACY_SHORTCODE = 'scfs_known_issue_release_intelligence';
    const ADMIN_PAGE = 'scfs-issue-release-intelligence';
    const NONCE_ACTION = 'scfs_save_issue_release_intelligence';
    const NONCE_NAME = 'scfs_issue_release_intelligence_nonce';
    const SCHEMA_OPTION = 'scfs_issue_release_intelligence_schema';
    const LAST_SYNC_OPTION = 'scfs_issue_release_intelligence_last_sync';

    const ISSUE_TARGET_RELEASES = '_scfs_issue_target_release_ids';
    const ISSUE_FIXED_RELEASES = '_scfs_issue_fixed_release_ids';
    const ISSUE_RELATED_ARTICLES = '_scfs_issue_related_article_ids';
    const ISSUE_PUBLIC_STATUS_NOTE = '_scfs_issue_public_status_note';
    const ISSUE_RELEASE_NOTE = '_scfs_issue_release_note';
    const ISSUE_LAST_VERIFIED = '_scfs_issue_last_verified_at';

    const RELEASE_OPEN_ISSUES = '_scfs_release_open_issue_ids';
    const RELEASE_RESOLVED_ISSUES = '_scfs_release_resolved_issue_ids';
    const RELEASE_VERIFICATION_STATE = '_scfs_release_verification_state';
    const RELEASE_LAST_VERIFIED = '_scfs_release_last_verified_at';

    private static $instance = null;
    private static $syncing = false;

    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('init', array($this, 'register_meta'), 12);
        add_action('init', array($this, 'register_shortcodes'), 34);
        add_action('wp_enqueue_scripts', array($this, 'register_assets'));
        add_action('admin_enqueue_scripts', array($this, 'admin_assets'));
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'), 30);
        add_action('save_post_' . SCFS_Knowledge_Base_Foundation::ISSUE_POST_TYPE, array($this, 'save_issue_relationships'), 35, 2);
        add_action('save_post_' . SCFS_Product_Support_Platform::RELEASE_POST_TYPE, array($this, 'save_release_relationships'), 35, 2);
        add_action('admin_menu', array($this, 'register_admin_page'), 28);
        add_action('admin_post_scfs_sync_issue_release_intelligence', array($this, 'sync_all_action'));
        add_action('rest_api_init', array($this, 'register_rest_routes'));
        add_filter('the_content', array($this, 'decorate_issue_content'), 26);
        add_filter('the_content', array($this, 'decorate_release_content'), 27);
        add_filter('manage_' . SCFS_Knowledge_Base_Foundation::ISSUE_POST_TYPE . '_posts_columns', array($this, 'issue_columns'), 40);
        add_action('manage_' . SCFS_Knowledge_Base_Foundation::ISSUE_POST_TYPE . '_posts_custom_column', array($this, 'issue_column_content'), 40, 2);
        add_filter('manage_' . SCFS_Product_Support_Platform::RELEASE_POST_TYPE . '_posts_columns', array($this, 'release_columns'), 40);
        add_action('manage_' . SCFS_Product_Support_Platform::RELEASE_POST_TYPE . '_posts_custom_column', array($this, 'release_column_content'), 40, 2);
    }

    public static function activate() {
        $instance = self::instance();
        $instance->register_meta();
        update_option(self::SCHEMA_OPTION, self::SCHEMA, false);
        $instance->sync_all(false);
    }

    public function register_shortcodes() {
        add_shortcode(self::SHORTCODE, array($this, 'render_shortcode'));
        add_shortcode(self::LEGACY_SHORTCODE, array($this, 'render_shortcode'));
    }

    public function register_assets() {
        $path = dirname(__DIR__) . '/assets/issue-release-intelligence.css';
        wp_register_style(
            'scfs-issue-release-intelligence',
            plugins_url('../assets/issue-release-intelligence.css', __FILE__),
            array('scfs-product-support-platform', 'scfs-knowledge-base'),
            is_readable($path) ? (string) filemtime($path) : self::VERSION
        );
    }

    public function admin_assets($hook) {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        $post_types = array(
            SCFS_Knowledge_Base_Foundation::ISSUE_POST_TYPE,
            SCFS_Product_Support_Platform::RELEASE_POST_TYPE,
        );
        if (($screen && in_array($screen->post_type, $post_types, true)) || strpos((string) $hook, self::ADMIN_PAGE) !== false) {
            wp_enqueue_style('scfs-issue-release-intelligence');
        }
    }

    public function register_meta() {
        $array_schema = array('schema' => array('type' => 'array', 'items' => array('type' => 'integer')));
        $issue_meta = array(
            self::ISSUE_TARGET_RELEASES => array('type' => 'array', 'sanitize_callback' => array($this, 'sanitize_release_ids'), 'show_in_rest' => $array_schema),
            self::ISSUE_FIXED_RELEASES => array('type' => 'array', 'sanitize_callback' => array($this, 'sanitize_release_ids'), 'show_in_rest' => $array_schema),
            self::ISSUE_RELATED_ARTICLES => array('type' => 'array', 'sanitize_callback' => array($this, 'sanitize_article_ids'), 'show_in_rest' => $array_schema),
            self::ISSUE_PUBLIC_STATUS_NOTE => array('type' => 'string', 'sanitize_callback' => 'sanitize_textarea_field', 'show_in_rest' => true),
            self::ISSUE_RELEASE_NOTE => array('type' => 'string', 'sanitize_callback' => 'sanitize_textarea_field', 'show_in_rest' => true),
            self::ISSUE_LAST_VERIFIED => array('type' => 'string', 'sanitize_callback' => array($this, 'sanitize_date'), 'show_in_rest' => true),
        );
        foreach ($issue_meta as $key => $args) {
            register_post_meta(SCFS_Knowledge_Base_Foundation::ISSUE_POST_TYPE, $key, array(
                'type' => $args['type'],
                'single' => true,
                'show_in_rest' => $args['show_in_rest'],
                'sanitize_callback' => $args['sanitize_callback'],
                'auth_callback' => function () { return current_user_can('edit_posts'); },
            ));
        }

        $release_meta = array(
            self::RELEASE_OPEN_ISSUES => array('type' => 'array', 'sanitize_callback' => array($this, 'sanitize_issue_ids'), 'show_in_rest' => $array_schema),
            self::RELEASE_RESOLVED_ISSUES => array('type' => 'array', 'sanitize_callback' => array($this, 'sanitize_issue_ids'), 'show_in_rest' => $array_schema),
            self::RELEASE_VERIFICATION_STATE => array('type' => 'string', 'sanitize_callback' => array($this, 'sanitize_verification_state'), 'show_in_rest' => true),
            self::RELEASE_LAST_VERIFIED => array('type' => 'string', 'sanitize_callback' => array($this, 'sanitize_date'), 'show_in_rest' => true),
        );
        foreach ($release_meta as $key => $args) {
            register_post_meta(SCFS_Product_Support_Platform::RELEASE_POST_TYPE, $key, array(
                'type' => $args['type'],
                'single' => true,
                'show_in_rest' => $args['show_in_rest'],
                'sanitize_callback' => $args['sanitize_callback'],
                'auth_callback' => function () { return current_user_can('edit_posts'); },
            ));
        }
    }

    public function sanitize_date($value) {
        $value = sanitize_text_field((string) $value);
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : '';
    }

    public function verification_states() {
        return array(
            'not_reviewed' => __('Not reviewed', 'sustainable-catalyst-feature-suggestions'),
            'review_required' => __('Review required', 'sustainable-catalyst-feature-suggestions'),
            'verified' => __('Verified', 'sustainable-catalyst-feature-suggestions'),
            'verified_with_limitations' => __('Verified with limitations', 'sustainable-catalyst-feature-suggestions'),
        );
    }

    public function sanitize_verification_state($value) {
        $value = sanitize_key((string) $value);
        return isset($this->verification_states()[$value]) ? $value : 'not_reviewed';
    }

    private function sanitize_ids_for_type($value, $post_type) {
        if (is_string($value)) {
            $value = preg_split('/[\s,]+/', $value);
        }
        $ids = array_values(array_unique(array_filter(array_map('absint', (array) $value))));
        $valid = array();
        foreach ($ids as $id) {
            if (get_post_type($id) === $post_type) {
                $valid[] = $id;
            }
        }
        return $valid;
    }

    public function sanitize_release_ids($value) {
        return $this->sanitize_ids_for_type($value, SCFS_Product_Support_Platform::RELEASE_POST_TYPE);
    }

    public function sanitize_issue_ids($value) {
        return $this->sanitize_ids_for_type($value, SCFS_Knowledge_Base_Foundation::ISSUE_POST_TYPE);
    }

    public function sanitize_article_ids($value) {
        return $this->sanitize_ids_for_type($value, SCFS_Knowledge_Base_Foundation::ARTICLE_POST_TYPE);
    }

    public function add_meta_boxes() {
        add_meta_box(
            'scfs-issue-release-intelligence',
            __('Release and Documentation Relationships', 'sustainable-catalyst-feature-suggestions'),
            array($this, 'render_issue_meta_box'),
            SCFS_Knowledge_Base_Foundation::ISSUE_POST_TYPE,
            'normal',
            'high'
        );
        add_meta_box(
            'scfs-release-issue-intelligence',
            __('Known Issue and Verification Relationships', 'sustainable-catalyst-feature-suggestions'),
            array($this, 'render_release_meta_box'),
            SCFS_Product_Support_Platform::RELEASE_POST_TYPE,
            'normal',
            'high'
        );
    }

    private function relationship_posts($post_type) {
        return get_posts(array(
            'post_type' => $post_type,
            'post_status' => array('publish', 'draft', 'pending', 'private', 'future'),
            'posts_per_page' => 500,
            'orderby' => 'title',
            'order' => 'ASC',
        ));
    }

    private function render_multiselect($name, $posts, $selected, $description) {
        echo '<select class="scfs-iri-multiselect" name="' . esc_attr($name) . '[]" multiple size="8">';
        foreach ($posts as $post) {
            echo '<option value="' . esc_attr((string) $post->ID) . '" ' . selected(in_array((int) $post->ID, $selected, true), true, false) . '>' . esc_html(get_the_title($post) . ' [' . $post->post_status . ']') . '</option>';
        }
        echo '</select><p class="description">' . esc_html($description) . '</p>';
    }

    public function render_issue_meta_box($post) {
        wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME);
        $targets = $this->sanitize_release_ids(get_post_meta($post->ID, self::ISSUE_TARGET_RELEASES, true));
        $fixed = $this->sanitize_release_ids(get_post_meta($post->ID, self::ISSUE_FIXED_RELEASES, true));
        $articles = $this->sanitize_article_ids(get_post_meta($post->ID, self::ISSUE_RELATED_ARTICLES, true));
        $releases = $this->relationship_posts(SCFS_Product_Support_Platform::RELEASE_POST_TYPE);
        $support_articles = $this->relationship_posts(SCFS_Knowledge_Base_Foundation::ARTICLE_POST_TYPE);
        echo '<div class="scfs-iri-editor-grid">';
        echo '<div><h4>' . esc_html__('Target releases', 'sustainable-catalyst-feature-suggestions') . '</h4>';
        $this->render_multiselect('scfs_issue_target_release_ids', $releases, $targets, __('Planned releases expected to contain a fix or material mitigation.', 'sustainable-catalyst-feature-suggestions'));
        echo '</div><div><h4>' . esc_html__('Fixed in releases', 'sustainable-catalyst-feature-suggestions') . '</h4>';
        $this->render_multiselect('scfs_issue_fixed_release_ids', $releases, $fixed, __('Verified releases in which the issue is resolved.', 'sustainable-catalyst-feature-suggestions'));
        echo '</div><div><h4>' . esc_html__('Related Support Articles', 'sustainable-catalyst-feature-suggestions') . '</h4>';
        $this->render_multiselect('scfs_issue_related_article_ids', $support_articles, $articles, __('Publication-grade instructions, troubleshooting, or upgrade guidance related to this issue.', 'sustainable-catalyst-feature-suggestions'));
        echo '</div></div>';
        echo '<p><label><strong>' . esc_html__('Public status note', 'sustainable-catalyst-feature-suggestions') . '</strong><br><textarea class="widefat" rows="3" name="scfs_issue_public_status_note">' . esc_textarea((string) get_post_meta($post->ID, self::ISSUE_PUBLIC_STATUS_NOTE, true)) . '</textarea></label></p>';
        echo '<p><label><strong>' . esc_html__('Release note', 'sustainable-catalyst-feature-suggestions') . '</strong><br><textarea class="widefat" rows="3" name="scfs_issue_release_note">' . esc_textarea((string) get_post_meta($post->ID, self::ISSUE_RELEASE_NOTE, true)) . '</textarea></label></p>';
        echo '<p><label><strong>' . esc_html__('Last verified date', 'sustainable-catalyst-feature-suggestions') . '</strong><br><input type="date" name="scfs_issue_last_verified_at" value="' . esc_attr((string) get_post_meta($post->ID, self::ISSUE_LAST_VERIFIED, true)) . '"></label></p>';
        echo '<p class="description">' . esc_html__('Saving synchronizes reverse release relationships without changing public URLs, publication status, or article content.', 'sustainable-catalyst-feature-suggestions') . '</p>';
    }

    public function render_release_meta_box($post) {
        wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME);
        $related_issues = $this->sanitize_issue_ids(get_post_meta($post->ID, '_scfs_release_related_issues', true));
        $related_articles = $this->sanitize_article_ids(get_post_meta($post->ID, '_scfs_release_related_articles', true));
        $issues = $this->relationship_posts(SCFS_Knowledge_Base_Foundation::ISSUE_POST_TYPE);
        $articles = $this->relationship_posts(SCFS_Knowledge_Base_Foundation::ARTICLE_POST_TYPE);
        echo '<div class="scfs-iri-editor-grid">';
        echo '<div><h4>' . esc_html__('Related Known Issues', 'sustainable-catalyst-feature-suggestions') . '</h4>';
        $this->render_multiselect('scfs_release_related_issues', $issues, $related_issues, __('Issues affected, mitigated, monitored, or resolved by this release.', 'sustainable-catalyst-feature-suggestions'));
        echo '</div><div><h4>' . esc_html__('Related Support Articles', 'sustainable-catalyst-feature-suggestions') . '</h4>';
        $this->render_multiselect('scfs_release_related_articles', $articles, $related_articles, __('Setup, upgrade, migration, troubleshooting, and reference guidance for this release.', 'sustainable-catalyst-feature-suggestions'));
        echo '</div></div>';
        $state = $this->sanitize_verification_state(get_post_meta($post->ID, self::RELEASE_VERIFICATION_STATE, true));
        echo '<p><label><strong>' . esc_html__('Verification state', 'sustainable-catalyst-feature-suggestions') . '</strong><br><select name="scfs_release_verification_state">';
        foreach ($this->verification_states() as $key => $label) {
            echo '<option value="' . esc_attr($key) . '" ' . selected($state, $key, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select></label></p>';
        echo '<p><label><strong>' . esc_html__('Last verified date', 'sustainable-catalyst-feature-suggestions') . '</strong><br><input type="date" name="scfs_release_last_verified_at" value="' . esc_attr((string) get_post_meta($post->ID, self::RELEASE_LAST_VERIFIED, true)) . '"></label></p>';
        echo '<p class="description">' . esc_html__('Open and resolved issue groupings are derived from current issue status when the release is saved or synchronized.', 'sustainable-catalyst-feature-suggestions') . '</p>';
    }

    private function can_save($post_id, $post) {
        if (self::$syncing || !$post || wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
            return false;
        }
        if (!isset($_POST[self::NONCE_NAME]) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[self::NONCE_NAME])), self::NONCE_ACTION)) {
            return false;
        }
        return current_user_can('edit_post', $post_id);
    }

    private function request_ids($key, $type) {
        $value = isset($_POST[$key]) ? (array) wp_unslash($_POST[$key]) : array();
        if ($type === 'release') return $this->sanitize_release_ids($value);
        if ($type === 'issue') return $this->sanitize_issue_ids($value);
        return $this->sanitize_article_ids($value);
    }

    public function save_issue_relationships($post_id, $post) {
        if (!$this->can_save($post_id, $post)) return;
        $old_releases = array_values(array_unique(array_merge(
            $this->sanitize_release_ids(get_post_meta($post_id, self::ISSUE_TARGET_RELEASES, true)),
            $this->sanitize_release_ids(get_post_meta($post_id, self::ISSUE_FIXED_RELEASES, true))
        )));
        $targets = $this->request_ids('scfs_issue_target_release_ids', 'release');
        $fixed = $this->request_ids('scfs_issue_fixed_release_ids', 'release');
        $articles = $this->request_ids('scfs_issue_related_article_ids', 'article');
        update_post_meta($post_id, self::ISSUE_TARGET_RELEASES, $targets);
        update_post_meta($post_id, self::ISSUE_FIXED_RELEASES, $fixed);
        update_post_meta($post_id, self::ISSUE_RELATED_ARTICLES, $articles);
        update_post_meta($post_id, self::ISSUE_PUBLIC_STATUS_NOTE, sanitize_textarea_field(isset($_POST['scfs_issue_public_status_note']) ? wp_unslash($_POST['scfs_issue_public_status_note']) : ''));
        update_post_meta($post_id, self::ISSUE_RELEASE_NOTE, sanitize_textarea_field(isset($_POST['scfs_issue_release_note']) ? wp_unslash($_POST['scfs_issue_release_note']) : ''));
        update_post_meta($post_id, self::ISSUE_LAST_VERIFIED, $this->sanitize_date(isset($_POST['scfs_issue_last_verified_at']) ? wp_unslash($_POST['scfs_issue_last_verified_at']) : ''));
        $this->sync_issue_to_releases($post_id, $old_releases);
    }

    public function save_release_relationships($post_id, $post) {
        if (!$this->can_save($post_id, $post)) return;
        $issues = $this->request_ids('scfs_release_related_issues', 'issue');
        $articles = $this->request_ids('scfs_release_related_articles', 'article');
        update_post_meta($post_id, '_scfs_release_related_issues', $issues);
        update_post_meta($post_id, '_scfs_release_related_articles', $articles);
        update_post_meta($post_id, self::RELEASE_VERIFICATION_STATE, $this->sanitize_verification_state(isset($_POST['scfs_release_verification_state']) ? wp_unslash($_POST['scfs_release_verification_state']) : 'not_reviewed'));
        update_post_meta($post_id, self::RELEASE_LAST_VERIFIED, $this->sanitize_date(isset($_POST['scfs_release_last_verified_at']) ? wp_unslash($_POST['scfs_release_last_verified_at']) : ''));
        $this->sync_release_issue_groups($post_id);
    }

    private function update_id_meta($post_id, $key, $ids) {
        update_post_meta($post_id, $key, array_values(array_unique(array_filter(array_map('absint', (array) $ids)))));
    }

    public function sync_issue_to_releases($issue_id, $previous_release_ids = array()) {
        if (get_post_type($issue_id) !== SCFS_Knowledge_Base_Foundation::ISSUE_POST_TYPE) return;
        self::$syncing = true;
        $targets = $this->sanitize_release_ids(get_post_meta($issue_id, self::ISSUE_TARGET_RELEASES, true));
        $fixed = $this->sanitize_release_ids(get_post_meta($issue_id, self::ISSUE_FIXED_RELEASES, true));
        $current = array_values(array_unique(array_merge($targets, $fixed)));
        $all = array_values(array_unique(array_merge($current, (array) $previous_release_ids)));
        foreach ($all as $release_id) {
            if (get_post_type($release_id) !== SCFS_Product_Support_Platform::RELEASE_POST_TYPE) continue;
            $related = $this->sanitize_issue_ids(get_post_meta($release_id, '_scfs_release_related_issues', true));
            if (in_array($release_id, $current, true)) {
                $related[] = $issue_id;
            } else {
                $related = array_values(array_diff($related, array($issue_id)));
            }
            $this->update_id_meta($release_id, '_scfs_release_related_issues', $related);
            $this->sync_release_issue_groups($release_id);
        }
        self::$syncing = false;
    }

    public function sync_release_issue_groups($release_id) {
        if (get_post_type($release_id) !== SCFS_Product_Support_Platform::RELEASE_POST_TYPE) return;
        $related = $this->sanitize_issue_ids(get_post_meta($release_id, '_scfs_release_related_issues', true));
        $open = array();
        $resolved = array();
        foreach ($related as $issue_id) {
            $status = sanitize_key((string) get_post_meta($issue_id, '_scfs_issue_status', true));
            if (in_array($status, array('resolved', 'closed'), true)) {
                $resolved[] = $issue_id;
            } else {
                $open[] = $issue_id;
            }
        }
        $this->update_id_meta($release_id, self::RELEASE_OPEN_ISSUES, $open);
        $this->update_id_meta($release_id, self::RELEASE_RESOLVED_ISSUES, $resolved);
    }

    public function sync_all($store_timestamp = true) {
        $issues = get_posts(array('post_type' => SCFS_Knowledge_Base_Foundation::ISSUE_POST_TYPE, 'post_status' => array('publish', 'draft', 'pending', 'private', 'future'), 'posts_per_page' => -1, 'fields' => 'ids'));
        $releases = get_posts(array('post_type' => SCFS_Product_Support_Platform::RELEASE_POST_TYPE, 'post_status' => array('publish', 'draft', 'pending', 'private', 'future'), 'posts_per_page' => -1, 'fields' => 'ids'));
        foreach ($issues as $issue_id) {
            $this->sync_issue_to_releases((int) $issue_id);
        }
        foreach ($releases as $release_id) {
            $this->sync_release_issue_groups((int) $release_id);
        }
        if ($store_timestamp) {
            update_option(self::LAST_SYNC_OPTION, gmdate('c'), false);
        }
        return array('issues' => count($issues), 'releases' => count($releases), 'synced_at' => gmdate('c'));
    }

    public function sync_all_action() {
        if (!current_user_can('manage_options')) wp_die(esc_html__('You do not have permission to synchronize support intelligence.', 'sustainable-catalyst-feature-suggestions'));
        check_admin_referer('scfs_sync_issue_release_intelligence');
        $this->sync_all(true);
        wp_safe_redirect(add_query_arg('scfs_synced', '1', admin_url('edit.php?post_type=' . Sustainable_Catalyst_Feature_Suggestions::POST_TYPE . '&page=' . self::ADMIN_PAGE)));
        exit;
    }

    private function term_records($post_id, $taxonomy) {
        $terms = get_the_terms($post_id, $taxonomy);
        if (is_wp_error($terms) || !$terms) return array();
        return array_map(static function ($term) {
            return array('id' => (int) $term->term_id, 'slug' => $term->slug, 'name' => $term->name);
        }, $terms);
    }

    private function linked_posts($ids, $post_type) {
        $records = array();
        foreach ((array) $ids as $id) {
            $post = get_post(absint($id));
            if (!($post instanceof WP_Post) || $post->post_type !== $post_type) continue;
            $records[] = array(
                'id' => (int) $post->ID,
                'title' => get_the_title($post),
                'status' => $post->post_status,
                'url' => $post->post_status === 'publish' ? get_permalink($post) : '',
            );
        }
        return $records;
    }

    public function issue_record($issue_id, $public_only = true) {
        $post = get_post(absint($issue_id));
        if (!($post instanceof WP_Post) || $post->post_type !== SCFS_Knowledge_Base_Foundation::ISSUE_POST_TYPE) return array();
        if ($public_only && $post->post_status !== 'publish') return array();
        $status = sanitize_key((string) get_post_meta($post->ID, '_scfs_issue_status', true)) ?: 'investigating';
        $severity = sanitize_key((string) get_post_meta($post->ID, '_scfs_issue_severity', true)) ?: 'moderate';
        $targets = $this->sanitize_release_ids(get_post_meta($post->ID, self::ISSUE_TARGET_RELEASES, true));
        $fixed = $this->sanitize_release_ids(get_post_meta($post->ID, self::ISSUE_FIXED_RELEASES, true));
        $articles = $this->sanitize_article_ids(get_post_meta($post->ID, self::ISSUE_RELATED_ARTICLES, true));
        $health = $this->issue_health($post->ID);
        return array(
            'schema' => self::SCHEMA,
            'version' => self::VERSION,
            'id' => (int) $post->ID,
            'title' => get_the_title($post),
            'url' => $post->post_status === 'publish' ? get_permalink($post) : '',
            'post_status' => $post->post_status,
            'status' => $status,
            'severity' => $severity,
            'symptom' => (string) get_post_meta($post->ID, '_scfs_issue_symptom', true),
            'workaround' => (string) get_post_meta($post->ID, '_scfs_issue_workaround', true),
            'resolution' => (string) get_post_meta($post->ID, '_scfs_issue_resolution', true),
            'public_status_note' => (string) get_post_meta($post->ID, self::ISSUE_PUBLIC_STATUS_NOTE, true),
            'release_note' => (string) get_post_meta($post->ID, self::ISSUE_RELEASE_NOTE, true),
            'first_observed' => (string) get_post_meta($post->ID, '_scfs_first_observed', true),
            'resolved_at' => (string) get_post_meta($post->ID, '_scfs_resolved_at', true),
            'last_verified_at' => (string) get_post_meta($post->ID, self::ISSUE_LAST_VERIFIED, true),
            'updated_at' => get_post_modified_time('c', true, $post),
            'products' => $this->term_records($post->ID, SCFS_Product_Integration::PRODUCT_TAXONOMY),
            'affected_versions' => $this->term_records($post->ID, SCFS_Product_Integration::VERSION_TAXONOMY),
            'components' => $this->term_records($post->ID, SCFS_Product_Integration::COMPONENT_TAXONOMY),
            'target_releases' => $this->linked_posts($targets, SCFS_Product_Support_Platform::RELEASE_POST_TYPE),
            'fixed_releases' => $this->linked_posts($fixed, SCFS_Product_Support_Platform::RELEASE_POST_TYPE),
            'related_articles' => $this->linked_posts($articles, SCFS_Knowledge_Base_Foundation::ARTICLE_POST_TYPE),
            'health' => $health,
            'human_review_required' => true,
        );
    }

    public function release_record($release_id, $public_only = true) {
        $post = get_post(absint($release_id));
        if (!($post instanceof WP_Post) || $post->post_type !== SCFS_Product_Support_Platform::RELEASE_POST_TYPE) return array();
        if ($public_only && $post->post_status !== 'publish') return array();
        $related = $this->sanitize_issue_ids(get_post_meta($post->ID, '_scfs_release_related_issues', true));
        $open = $this->sanitize_issue_ids(get_post_meta($post->ID, self::RELEASE_OPEN_ISSUES, true));
        $resolved = $this->sanitize_issue_ids(get_post_meta($post->ID, self::RELEASE_RESOLVED_ISSUES, true));
        if (!$open && !$resolved && $related) {
            $this->sync_release_issue_groups($post->ID);
            $open = $this->sanitize_issue_ids(get_post_meta($post->ID, self::RELEASE_OPEN_ISSUES, true));
            $resolved = $this->sanitize_issue_ids(get_post_meta($post->ID, self::RELEASE_RESOLVED_ISSUES, true));
        }
        $articles = $this->sanitize_article_ids(get_post_meta($post->ID, '_scfs_release_related_articles', true));
        return array(
            'schema' => self::SCHEMA,
            'version' => self::VERSION,
            'id' => (int) $post->ID,
            'title' => get_the_title($post),
            'url' => $post->post_status === 'publish' ? get_permalink($post) : '',
            'post_status' => $post->post_status,
            'status' => sanitize_key((string) get_post_meta($post->ID, '_scfs_release_status', true)) ?: 'planned',
            'release_date' => (string) get_post_meta($post->ID, '_scfs_release_date', true),
            'summary' => (string) get_post_meta($post->ID, '_scfs_release_summary', true),
            'support_note' => (string) get_post_meta($post->ID, '_scfs_release_support_note', true),
            'known_limitations' => (string) get_post_meta($post->ID, '_scfs_release_known_limitations', true),
            'documentation_url' => (string) get_post_meta($post->ID, '_scfs_release_docs_url', true),
            'changelog_url' => (string) get_post_meta($post->ID, '_scfs_release_changelog_url', true),
            'verification_state' => $this->sanitize_verification_state(get_post_meta($post->ID, self::RELEASE_VERIFICATION_STATE, true)),
            'last_verified_at' => (string) get_post_meta($post->ID, self::RELEASE_LAST_VERIFIED, true),
            'updated_at' => get_post_modified_time('c', true, $post),
            'products' => $this->term_records($post->ID, SCFS_Product_Integration::PRODUCT_TAXONOMY),
            'versions' => $this->term_records($post->ID, SCFS_Product_Integration::VERSION_TAXONOMY),
            'components' => $this->term_records($post->ID, SCFS_Product_Integration::COMPONENT_TAXONOMY),
            'open_issues' => $this->linked_posts($open, SCFS_Knowledge_Base_Foundation::ISSUE_POST_TYPE),
            'resolved_issues' => $this->linked_posts($resolved, SCFS_Knowledge_Base_Foundation::ISSUE_POST_TYPE),
            'related_articles' => $this->linked_posts($articles, SCFS_Knowledge_Base_Foundation::ARTICLE_POST_TYPE),
            'health' => $this->release_health($post->ID),
            'human_review_required' => true,
        );
    }

    public function issue_health($issue_id) {
        $status = sanitize_key((string) get_post_meta($issue_id, '_scfs_issue_status', true)) ?: 'investigating';
        $severity = sanitize_key((string) get_post_meta($issue_id, '_scfs_issue_severity', true)) ?: 'moderate';
        $workaround = trim((string) wp_strip_all_tags(get_post_meta($issue_id, '_scfs_issue_workaround', true)));
        $targets = $this->sanitize_release_ids(get_post_meta($issue_id, self::ISSUE_TARGET_RELEASES, true));
        $fixed = $this->sanitize_release_ids(get_post_meta($issue_id, self::ISSUE_FIXED_RELEASES, true));
        $articles = $this->sanitize_article_ids(get_post_meta($issue_id, self::ISSUE_RELATED_ARTICLES, true));
        $warnings = array();
        if (in_array($status, array('workaround_available', 'fix_planned'), true) && $workaround === '') $warnings[] = 'workaround_missing';
        if ($status === 'fix_planned' && !$targets) $warnings[] = 'target_release_missing';
        if (in_array($status, array('resolved', 'closed'), true) && !$fixed) $warnings[] = 'fixed_release_missing';
        if (in_array($severity, array('major', 'critical'), true) && !$articles) $warnings[] = 'support_article_missing';
        return array(
            'state' => $warnings ? 'review_required' : 'linked',
            'warnings' => $warnings,
            'relationship_count' => count(array_unique(array_merge($targets, $fixed, $articles))),
        );
    }

    public function release_health($release_id) {
        $status = sanitize_key((string) get_post_meta($release_id, '_scfs_release_status', true)) ?: 'planned';
        $open = $this->sanitize_issue_ids(get_post_meta($release_id, self::RELEASE_OPEN_ISSUES, true));
        $articles = $this->sanitize_article_ids(get_post_meta($release_id, '_scfs_release_related_articles', true));
        $changelog = trim((string) get_post_meta($release_id, '_scfs_release_changelog_url', true));
        $warnings = array();
        $major_open = 0;
        foreach ($open as $issue_id) {
            if (in_array(get_post_meta($issue_id, '_scfs_issue_severity', true), array('major', 'critical'), true)) $major_open++;
        }
        if (in_array($status, array('current', 'maintenance'), true) && $major_open > 0) $warnings[] = 'major_open_issues';
        if (in_array($status, array('current', 'maintenance', 'superseded'), true) && $changelog === '') $warnings[] = 'changelog_missing';
        if (in_array($status, array('current', 'maintenance'), true) && !$articles) $warnings[] = 'support_articles_missing';
        return array(
            'state' => $warnings ? 'review_required' : 'linked',
            'warnings' => $warnings,
            'open_issue_count' => count($open),
            'major_open_issue_count' => $major_open,
        );
    }

    public function public_summary($product = '') {
        $issue_args = array('post_type' => SCFS_Knowledge_Base_Foundation::ISSUE_POST_TYPE, 'post_status' => 'publish', 'posts_per_page' => 200, 'fields' => 'ids', 'no_found_rows' => true);
        $release_args = array('post_type' => SCFS_Product_Support_Platform::RELEASE_POST_TYPE, 'post_status' => 'publish', 'posts_per_page' => 200, 'fields' => 'ids', 'no_found_rows' => true);
        if ($product !== '') {
            $tax = array(array('taxonomy' => SCFS_Product_Integration::PRODUCT_TAXONOMY, 'field' => 'slug', 'terms' => sanitize_title($product)));
            $issue_args['tax_query'] = $tax;
            $release_args['tax_query'] = $tax;
        }
        $issues = get_posts($issue_args);
        $releases = get_posts($release_args);
        $active = 0;
        $resolved = 0;
        $with_workarounds = 0;
        $linked = 0;
        foreach ($issues as $issue_id) {
            $status = get_post_meta($issue_id, '_scfs_issue_status', true);
            if (in_array($status, array('resolved', 'closed'), true)) $resolved++; else $active++;
            if (trim((string) get_post_meta($issue_id, '_scfs_issue_workaround', true)) !== '') $with_workarounds++;
            if ($this->issue_health($issue_id)['relationship_count'] > 0) $linked++;
        }
        return array(
            'schema' => self::SCHEMA,
            'version' => self::VERSION,
            'product' => sanitize_title($product),
            'known_issues' => count($issues),
            'active_issues' => $active,
            'resolved_issues' => $resolved,
            'issues_with_workarounds' => $with_workarounds,
            'issues_with_release_or_article_links' => $linked,
            'release_records' => count($releases),
            'generated_at' => gmdate('c'),
            'public_records_only' => true,
            'human_review_required' => true,
        );
    }

    public function render_shortcode($atts = array()) {
        $atts = shortcode_atts(array('product' => '', 'limit' => '6', 'show_summary' => '1'), $atts, self::SHORTCODE);
        $product = sanitize_title((string) $atts['product']);
        if ($product === '' && isset($_GET['scfs_support_product'])) $product = sanitize_title(wp_unslash($_GET['scfs_support_product']));
        $limit = min(20, max(1, absint($atts['limit'])));
        wp_enqueue_style('scfs-issue-release-intelligence');
        $issue_args = array('post_type' => SCFS_Knowledge_Base_Foundation::ISSUE_POST_TYPE, 'post_status' => 'publish', 'posts_per_page' => $limit, 'orderby' => 'modified', 'order' => 'DESC');
        $release_args = array('post_type' => SCFS_Product_Support_Platform::RELEASE_POST_TYPE, 'post_status' => 'publish', 'posts_per_page' => $limit, 'orderby' => 'meta_value', 'meta_key' => '_scfs_release_date', 'order' => 'DESC');
        if ($product !== '') {
            $tax = array(array('taxonomy' => SCFS_Product_Integration::PRODUCT_TAXONOMY, 'field' => 'slug', 'terms' => $product));
            $issue_args['tax_query'] = $tax;
            $release_args['tax_query'] = $tax;
        }
        $issues = get_posts($issue_args);
        $releases = get_posts($release_args);
        ob_start();
        echo '<section class="scfs-iri" data-schema="' . esc_attr(self::SCHEMA) . '"><header class="scfs-iri__header"><p class="scfs-iri__eyebrow">' . esc_html__('Operational support intelligence', 'sustainable-catalyst-feature-suggestions') . '</p><h2>' . esc_html__('Known issues and releases', 'sustainable-catalyst-feature-suggestions') . '</h2><p>' . esc_html__('Review affected versions, workarounds, planned fixes, resolved releases, changelog context, and related publication-grade guidance.', 'sustainable-catalyst-feature-suggestions') . '</p></header>';
        if ($atts['show_summary'] !== '0') {
            $summary = $this->public_summary($product);
            echo '<dl class="scfs-iri__summary"><div><dt>' . esc_html__('Active issues', 'sustainable-catalyst-feature-suggestions') . '</dt><dd>' . esc_html((string) $summary['active_issues']) . '</dd></div><div><dt>' . esc_html__('Resolved issues', 'sustainable-catalyst-feature-suggestions') . '</dt><dd>' . esc_html((string) $summary['resolved_issues']) . '</dd></div><div><dt>' . esc_html__('Release records', 'sustainable-catalyst-feature-suggestions') . '</dt><dd>' . esc_html((string) $summary['release_records']) . '</dd></div><div><dt>' . esc_html__('Linked guidance', 'sustainable-catalyst-feature-suggestions') . '</dt><dd>' . esc_html((string) $summary['issues_with_release_or_article_links']) . '</dd></div></dl>';
        }
        echo '<div class="scfs-iri__columns"><section><h3>' . esc_html__('Known Issues', 'sustainable-catalyst-feature-suggestions') . '</h3><div class="scfs-iri__records">';
        if (!$issues) echo '<p class="scfs-iri__empty">' . esc_html__('No published Known Issues match this context.', 'sustainable-catalyst-feature-suggestions') . '</p>';
        foreach ($issues as $issue) $this->render_issue_card($this->issue_record($issue->ID, true));
        echo '</div></section><section><h3>' . esc_html__('Release Intelligence', 'sustainable-catalyst-feature-suggestions') . '</h3><div class="scfs-iri__records">';
        if (!$releases) echo '<p class="scfs-iri__empty">' . esc_html__('No published release records match this context.', 'sustainable-catalyst-feature-suggestions') . '</p>';
        foreach ($releases as $release) $this->render_release_card($this->release_record($release->ID, true));
        echo '</div></section></div></section>';
        return ob_get_clean();
    }

    public function render_issue_card($record) {
        if (!$record) return;
        echo '<article class="scfs-iri__card scfs-iri__card--issue"><div class="scfs-iri__badges"><span class="status-' . esc_attr($record['status']) . '">' . esc_html(ucwords(str_replace('_', ' ', $record['status']))) . '</span><span class="severity-' . esc_attr($record['severity']) . '">' . esc_html(ucwords($record['severity'])) . '</span></div><h4><a href="' . esc_url($record['url']) . '">' . esc_html($record['title']) . '</a></h4>';
        if ($record['symptom']) echo '<p>' . esc_html(wp_trim_words($record['symptom'], 28)) . '</p>';
        if ($record['workaround']) echo '<p class="scfs-iri__workaround"><strong>' . esc_html__('Workaround:', 'sustainable-catalyst-feature-suggestions') . '</strong> ' . esc_html(wp_trim_words(wp_strip_all_tags($record['workaround']), 22)) . '</p>';
        $fixed = wp_list_pluck($record['fixed_releases'], 'title');
        $targets = wp_list_pluck($record['target_releases'], 'title');
        if ($fixed) echo '<p class="scfs-iri__relationship"><strong>' . esc_html__('Fixed in:', 'sustainable-catalyst-feature-suggestions') . '</strong> ' . esc_html(implode(', ', $fixed)) . '</p>';
        elseif ($targets) echo '<p class="scfs-iri__relationship"><strong>' . esc_html__('Target release:', 'sustainable-catalyst-feature-suggestions') . '</strong> ' . esc_html(implode(', ', $targets)) . '</p>';
        echo '<a class="scfs-iri__link" href="' . esc_url($record['url']) . '">' . esc_html__('View issue details', 'sustainable-catalyst-feature-suggestions') . ' →</a></article>';
    }

    public function render_release_card($record) {
        if (!$record) return;
        echo '<article class="scfs-iri__card scfs-iri__card--release"><div class="scfs-iri__badges"><span class="status-' . esc_attr($record['status']) . '">' . esc_html(ucwords(str_replace('_', ' ', $record['status']))) . '</span><span class="verification-' . esc_attr($record['verification_state']) . '">' . esc_html($this->verification_states()[$record['verification_state']] ?? $record['verification_state']) . '</span></div><h4><a href="' . esc_url($record['url']) . '">' . esc_html($record['title']) . '</a></h4>';
        if ($record['summary']) echo '<p>' . esc_html(wp_trim_words($record['summary'], 30)) . '</p>';
        echo '<p class="scfs-iri__relationship"><strong>' . esc_html__('Open issues:', 'sustainable-catalyst-feature-suggestions') . '</strong> ' . esc_html((string) count($record['open_issues'])) . ' · <strong>' . esc_html__('Resolved:', 'sustainable-catalyst-feature-suggestions') . '</strong> ' . esc_html((string) count($record['resolved_issues'])) . '</p>';
        echo '<div class="scfs-iri__links"><a href="' . esc_url($record['url']) . '">' . esc_html__('Release details', 'sustainable-catalyst-feature-suggestions') . '</a>';
        if ($record['changelog_url']) echo '<a href="' . esc_url($record['changelog_url']) . '">' . esc_html__('Changelog', 'sustainable-catalyst-feature-suggestions') . '</a>';
        if ($record['documentation_url']) echo '<a href="' . esc_url($record['documentation_url']) . '">' . esc_html__('Documentation', 'sustainable-catalyst-feature-suggestions') . '</a>';
        echo '</div></article>';
    }

    private function render_operational_relationships($record, $kind) {
        ob_start();
        echo '<section class="scfs-iri-publication" aria-label="' . esc_attr__('Operational support intelligence', 'sustainable-catalyst-feature-suggestions') . '"><p class="scfs-iri__eyebrow">' . esc_html__('Operational support intelligence', 'sustainable-catalyst-feature-suggestions') . '</p>';
        if ($kind === 'issue') {
            echo '<h2>' . esc_html__('Affected versions and release status', 'sustainable-catalyst-feature-suggestions') . '</h2><dl class="scfs-iri-publication__meta">';
            $this->render_meta_item(__('Affected versions', 'sustainable-catalyst-feature-suggestions'), implode(', ', wp_list_pluck($record['affected_versions'], 'name')));
            $this->render_meta_item(__('Components', 'sustainable-catalyst-feature-suggestions'), implode(', ', wp_list_pluck($record['components'], 'name')));
            $this->render_meta_item(__('Last verified', 'sustainable-catalyst-feature-suggestions'), $record['last_verified_at']);
            echo '</dl>';
            if ($record['public_status_note']) echo '<div class="scfs-iri-publication__note"><strong>' . esc_html__('Current status', 'sustainable-catalyst-feature-suggestions') . '</strong><p>' . esc_html($record['public_status_note']) . '</p></div>';
            $groups = array(
                __('Target releases', 'sustainable-catalyst-feature-suggestions') => $record['target_releases'],
                __('Fixed in releases', 'sustainable-catalyst-feature-suggestions') => $record['fixed_releases'],
                __('Related Support Articles', 'sustainable-catalyst-feature-suggestions') => $record['related_articles'],
            );
        } else {
            echo '<h2>' . esc_html__('Issue and documentation coverage', 'sustainable-catalyst-feature-suggestions') . '</h2><dl class="scfs-iri-publication__meta">';
            $this->render_meta_item(__('Verification', 'sustainable-catalyst-feature-suggestions'), $this->verification_states()[$record['verification_state']] ?? $record['verification_state']);
            $this->render_meta_item(__('Last verified', 'sustainable-catalyst-feature-suggestions'), $record['last_verified_at']);
            $this->render_meta_item(__('Open issues', 'sustainable-catalyst-feature-suggestions'), (string) count($record['open_issues']));
            echo '</dl>';
            $groups = array(
                __('Open Known Issues', 'sustainable-catalyst-feature-suggestions') => $record['open_issues'],
                __('Resolved Known Issues', 'sustainable-catalyst-feature-suggestions') => $record['resolved_issues'],
                __('Related Support Articles', 'sustainable-catalyst-feature-suggestions') => $record['related_articles'],
            );
        }
        foreach ($groups as $title => $items) {
            if (!$items) continue;
            echo '<div class="scfs-iri-publication__group"><h3>' . esc_html($title) . '</h3><ul>';
            foreach ($items as $item) {
                echo '<li>' . ($item['url'] ? '<a href="' . esc_url($item['url']) . '">' . esc_html($item['title']) . '</a>' : esc_html($item['title'])) . '</li>';
            }
            echo '</ul></div>';
        }
        echo '</section>';
        return ob_get_clean();
    }

    private function render_meta_item($label, $value) {
        if (trim((string) $value) === '') return;
        echo '<div><dt>' . esc_html($label) . '</dt><dd>' . esc_html($value) . '</dd></div>';
    }

    public function decorate_issue_content($content) {
        if (!is_singular(SCFS_Knowledge_Base_Foundation::ISSUE_POST_TYPE) || !in_the_loop() || !is_main_query()) return $content;
        $record = $this->issue_record(get_the_ID(), true);
        if (!$record) return $content;
        wp_enqueue_style('scfs-issue-release-intelligence');
        return $content . $this->render_operational_relationships($record, 'issue');
    }

    public function decorate_release_content($content) {
        if (!is_singular(SCFS_Product_Support_Platform::RELEASE_POST_TYPE) || !in_the_loop() || !is_main_query()) return $content;
        $record = $this->release_record(get_the_ID(), true);
        if (!$record) return $content;
        wp_enqueue_style('scfs-issue-release-intelligence');
        return $content . $this->render_operational_relationships($record, 'release');
    }

    public function register_admin_page() {
        add_submenu_page(
            'edit.php?post_type=' . Sustainable_Catalyst_Feature_Suggestions::POST_TYPE,
            __('Issue and Release Intelligence', 'sustainable-catalyst-feature-suggestions'),
            __('Issue & Release Links', 'sustainable-catalyst-feature-suggestions'),
            'manage_options',
            self::ADMIN_PAGE,
            array($this, 'render_admin_page')
        );
    }

    public function render_admin_page() {
        if (!current_user_can('manage_options')) return;
        $summary = $this->public_summary('');
        $issues = get_posts(array('post_type' => SCFS_Knowledge_Base_Foundation::ISSUE_POST_TYPE, 'post_status' => array('publish', 'draft', 'pending', 'private'), 'posts_per_page' => 500, 'orderby' => 'modified', 'order' => 'DESC'));
        $review = array();
        foreach ($issues as $issue) {
            $health = $this->issue_health($issue->ID);
            if ($health['warnings']) $review[] = array('post' => $issue, 'health' => $health);
        }
        echo '<div class="wrap scfs-iri-admin"><h1>' . esc_html__('Known Issue and Release Intelligence', 'sustainable-catalyst-feature-suggestions') . '</h1><p>' . esc_html__('Review operational relationships among Known Issues, affected versions, workarounds, Support Articles, target releases, fixed releases, and changelog evidence.', 'sustainable-catalyst-feature-suggestions') . '</p>';
        if (isset($_GET['scfs_synced'])) echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Issue and release relationships synchronized.', 'sustainable-catalyst-feature-suggestions') . '</p></div>';
        echo '<div class="scfs-iri-admin__summary"><div><strong>' . esc_html((string) $summary['active_issues']) . '</strong><span>' . esc_html__('Active issues', 'sustainable-catalyst-feature-suggestions') . '</span></div><div><strong>' . esc_html((string) $summary['resolved_issues']) . '</strong><span>' . esc_html__('Resolved issues', 'sustainable-catalyst-feature-suggestions') . '</span></div><div><strong>' . esc_html((string) $summary['release_records']) . '</strong><span>' . esc_html__('Release records', 'sustainable-catalyst-feature-suggestions') . '</span></div><div><strong>' . esc_html((string) count($review)) . '</strong><span>' . esc_html__('Needs relationship review', 'sustainable-catalyst-feature-suggestions') . '</span></div></div>';
        $sync_url = wp_nonce_url(admin_url('admin-post.php?action=scfs_sync_issue_release_intelligence'), 'scfs_sync_issue_release_intelligence');
        echo '<p><a class="button button-primary" href="' . esc_url($sync_url) . '">' . esc_html__('Synchronize all relationships', 'sustainable-catalyst-feature-suggestions') . '</a></p>';
        echo '<h2>' . esc_html__('Relationship review queue', 'sustainable-catalyst-feature-suggestions') . '</h2><table class="widefat striped"><thead><tr><th>' . esc_html__('Known Issue', 'sustainable-catalyst-feature-suggestions') . '</th><th>' . esc_html__('Status', 'sustainable-catalyst-feature-suggestions') . '</th><th>' . esc_html__('Warnings', 'sustainable-catalyst-feature-suggestions') . '</th><th>' . esc_html__('Relationships', 'sustainable-catalyst-feature-suggestions') . '</th></tr></thead><tbody>';
        if (!$review) echo '<tr><td colspan="4">' . esc_html__('No relationship warnings were found.', 'sustainable-catalyst-feature-suggestions') . '</td></tr>';
        foreach ($review as $item) {
            $post = $item['post'];
            echo '<tr><td><a href="' . esc_url(get_edit_post_link($post->ID)) . '">' . esc_html(get_the_title($post)) . '</a></td><td>' . esc_html(ucwords(str_replace('_', ' ', (string) get_post_meta($post->ID, '_scfs_issue_status', true)))) . '</td><td>' . esc_html(implode(', ', array_map(static function ($warning) { return str_replace('_', ' ', $warning); }, $item['health']['warnings']))) . '</td><td>' . esc_html((string) $item['health']['relationship_count']) . '</td></tr>';
        }
        echo '</tbody></table><p class="description">' . esc_html__('The review queue is advisory. Publication, incident declaration, roadmap changes, and release status remain human-controlled.', 'sustainable-catalyst-feature-suggestions') . '</p></div>';
    }

    public function issue_columns($columns) {
        $columns['scfs_iri_releases'] = __('Release Links', 'sustainable-catalyst-feature-suggestions');
        $columns['scfs_iri_health'] = __('Relationship Health', 'sustainable-catalyst-feature-suggestions');
        return $columns;
    }

    public function issue_column_content($column, $post_id) {
        if ($column === 'scfs_iri_releases') {
            $targets = $this->sanitize_release_ids(get_post_meta($post_id, self::ISSUE_TARGET_RELEASES, true));
            $fixed = $this->sanitize_release_ids(get_post_meta($post_id, self::ISSUE_FIXED_RELEASES, true));
            echo esc_html(sprintf(__('Target: %1$d · Fixed: %2$d', 'sustainable-catalyst-feature-suggestions'), count($targets), count($fixed)));
        }
        if ($column === 'scfs_iri_health') {
            $health = $this->issue_health($post_id);
            echo esc_html($health['warnings'] ? __('Review required', 'sustainable-catalyst-feature-suggestions') : __('Linked', 'sustainable-catalyst-feature-suggestions'));
        }
    }

    public function release_columns($columns) {
        $columns['scfs_iri_issue_coverage'] = __('Issue Coverage', 'sustainable-catalyst-feature-suggestions');
        $columns['scfs_iri_verification'] = __('Verification', 'sustainable-catalyst-feature-suggestions');
        return $columns;
    }

    public function release_column_content($column, $post_id) {
        if ($column === 'scfs_iri_issue_coverage') {
            $open = $this->sanitize_issue_ids(get_post_meta($post_id, self::RELEASE_OPEN_ISSUES, true));
            $resolved = $this->sanitize_issue_ids(get_post_meta($post_id, self::RELEASE_RESOLVED_ISSUES, true));
            echo esc_html(sprintf(__('Open: %1$d · Resolved: %2$d', 'sustainable-catalyst-feature-suggestions'), count($open), count($resolved)));
        }
        if ($column === 'scfs_iri_verification') {
            $state = $this->sanitize_verification_state(get_post_meta($post_id, self::RELEASE_VERIFICATION_STATE, true));
            echo esc_html($this->verification_states()[$state] ?? $state);
        }
    }

    public function register_rest_routes() {
        register_rest_route(Sustainable_Catalyst_Feature_Suggestions::REST_NAMESPACE, '/issue-release-intelligence/schema', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'rest_schema'),
            'permission_callback' => '__return_true',
        ));
        register_rest_route(Sustainable_Catalyst_Feature_Suggestions::REST_NAMESPACE, '/issue-release-intelligence/issues', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'rest_issues'),
            'permission_callback' => '__return_true',
        ));
        register_rest_route(Sustainable_Catalyst_Feature_Suggestions::REST_NAMESPACE, '/issue-release-intelligence/releases', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'rest_releases'),
            'permission_callback' => '__return_true',
        ));
        register_rest_route(Sustainable_Catalyst_Feature_Suggestions::REST_NAMESPACE, '/issue-release-intelligence/record/(?P<id>\d+)', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'rest_record'),
            'permission_callback' => '__return_true',
            'args' => array('id' => array('sanitize_callback' => 'absint')),
        ));
        register_rest_route(Sustainable_Catalyst_Feature_Suggestions::REST_NAMESPACE, '/issue-release-intelligence/sync', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'rest_sync'),
            'permission_callback' => function () { return current_user_can('manage_options'); },
        ));
    }

    public function rest_schema() {
        return rest_ensure_response(array(
            'schema' => self::SCHEMA,
            'version' => self::VERSION,
            'record_types' => array('known_issue', 'release_record', 'support_article'),
            'relationships' => array('affected_versions', 'components', 'target_releases', 'fixed_releases', 'open_issues', 'resolved_issues', 'related_articles', 'changelog'),
            'public_records_only' => true,
            'automatic_incident_declaration' => false,
            'automatic_release_status_changes' => false,
            'automatic_publication' => false,
            'human_review_required' => true,
        ));
    }

    private function rest_product($request) {
        return sanitize_title((string) $request->get_param('product'));
    }

    public function rest_issues($request) {
        $args = array('post_type' => SCFS_Knowledge_Base_Foundation::ISSUE_POST_TYPE, 'post_status' => 'publish', 'posts_per_page' => min(100, max(1, absint($request->get_param('limit') ?: 25))), 'orderby' => 'modified', 'order' => 'DESC');
        $product = $this->rest_product($request);
        if ($product) $args['tax_query'] = array(array('taxonomy' => SCFS_Product_Integration::PRODUCT_TAXONOMY, 'field' => 'slug', 'terms' => $product));
        $items = array();
        foreach (get_posts($args) as $post) $items[] = $this->issue_record($post->ID, true);
        return rest_ensure_response(array('schema' => self::SCHEMA, 'version' => self::VERSION, 'count' => count($items), 'items' => $items));
    }

    public function rest_releases($request) {
        $args = array('post_type' => SCFS_Product_Support_Platform::RELEASE_POST_TYPE, 'post_status' => 'publish', 'posts_per_page' => min(100, max(1, absint($request->get_param('limit') ?: 25))), 'orderby' => 'meta_value', 'meta_key' => '_scfs_release_date', 'order' => 'DESC');
        $product = $this->rest_product($request);
        if ($product) $args['tax_query'] = array(array('taxonomy' => SCFS_Product_Integration::PRODUCT_TAXONOMY, 'field' => 'slug', 'terms' => $product));
        $items = array();
        foreach (get_posts($args) as $post) $items[] = $this->release_record($post->ID, true);
        return rest_ensure_response(array('schema' => self::SCHEMA, 'version' => self::VERSION, 'count' => count($items), 'items' => $items));
    }

    public function rest_record($request) {
        $id = absint($request['id']);
        $type = get_post_type($id);
        if ($type === SCFS_Knowledge_Base_Foundation::ISSUE_POST_TYPE) $record = $this->issue_record($id, true);
        elseif ($type === SCFS_Product_Support_Platform::RELEASE_POST_TYPE) $record = $this->release_record($id, true);
        else return new WP_Error('scfs_invalid_intelligence_record', __('The requested public intelligence record was not found.', 'sustainable-catalyst-feature-suggestions'), array('status' => 404));
        if (!$record) return new WP_Error('scfs_intelligence_record_unavailable', __('The requested intelligence record is not public.', 'sustainable-catalyst-feature-suggestions'), array('status' => 404));
        return rest_ensure_response($record);
    }

    public function rest_sync() {
        return rest_ensure_response(array('ok' => true, 'schema' => self::SCHEMA, 'result' => $this->sync_all(true)));
    }
}
