<?php
/**
 * Unified Product Support and Feedback Platform.
 *
 * Brings guided resolution, documentation, known issues, feature suggestions,
 * public voting, surveys, release intelligence, and private support handoff
 * into one product-aware public support center.
 *
 * @package Sustainable_Catalyst_Feature_Suggestions
 */

if (!defined('ABSPATH')) {
    exit;
}

final class SCFS_Product_Support_Platform {
    const VERSION = '5.0.0';
    const SCHEMA_VERSION = '1.0';
    const RELEASE_POST_TYPE = 'sc_release_record';
    const SHORTCODE = 'scfs_product_support_center';
    const LEGACY_SHORTCODE = 'scfs_support_center';
    const OPTION_KEY = 'scfs_product_support_platform';
    const SCHEMA_OPTION = 'scfs_product_support_schema_version';
    const NONCE_ACTION = 'scfs_save_release_intelligence';
    const NONCE_NAME = 'scfs_release_intelligence_nonce';

    private static $instance = null;
    private static $render_count = 0;
    private $active_base_url = '';
    private $active_anchor = 'support-center';
    private $active_show_overview_pathways = true;

    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('init', array($this, 'register_release_post_type'), 6);
        add_action('init', array($this, 'register_release_meta'), 9);
        add_action('init', array($this, 'register_shortcodes'), 31);
        add_action('wp_enqueue_scripts', array($this, 'register_assets'));
        add_action('admin_enqueue_scripts', array($this, 'admin_assets'));
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post_' . self::RELEASE_POST_TYPE, array($this, 'save_release'), 20, 2);
        add_action('admin_menu', array($this, 'register_admin_page'), 23);
        add_action('admin_post_scfs_save_product_support_settings', array($this, 'save_settings'));
        add_action('rest_api_init', array($this, 'register_rest_routes'));
        add_filter('template_include', array($this, 'template_include'));
        add_filter('the_content', array($this, 'decorate_release_content'), 22);
        add_filter('manage_' . self::RELEASE_POST_TYPE . '_posts_columns', array($this, 'release_columns'));
        add_action('manage_' . self::RELEASE_POST_TYPE . '_posts_custom_column', array($this, 'release_column_content'), 10, 2);
    }

    public static function activate() {
        $instance = self::instance();
        $instance->register_release_post_type();
        $instance->register_release_meta();
        update_option(self::SCHEMA_OPTION, self::SCHEMA_VERSION, false);
        if (!get_option(self::OPTION_KEY)) {
            add_option(self::OPTION_KEY, $instance->default_settings(), '', false);
        }
    }

    private function default_settings() {
        return array(
            'support_center_title' => 'Sustainable Catalyst Product Support',
            'support_center_intro' => 'Find documentation, check known issues, review releases, support public ideas, participate in surveys, suggest improvements, or continue to private support when public guidance is not enough.',
            'default_view' => 'overview',
            'default_mode' => 'standalone',
            'embedded_default_view' => 'resolve',
            'contact_engagement_url' => home_url('/contact/'),
            'show_surveys' => '1',
            'show_public_ideas' => '1',
            'show_release_intelligence' => '1',
            'featured_release_limit' => '4',
            'branding_preset' => 'platform',
            'brand_accent' => '#6f1225',
            'brand_accent_contrast' => '#ffffff',
            'brand_ink' => '#131313',
            'brand_muted' => '#5e6268',
            'brand_surface' => '#ffffff',
            'brand_soft' => '#f6f7f8',
            'brand_line' => '#d9dde2',
            'brand_success' => '#176b3a',
            'brand_warning' => '#8a5a00',
            'brand_danger' => '#9b1111',
            'brand_radius' => '0',
            'brand_shadow' => 'subtle',
            'brand_max_width' => '1180',
            'brand_font_family' => 'inherit',
            'brand_heading_font_family' => 'inherit',
            'embedded_hide_header' => '1',
            'embedded_hide_status' => '1',
            'embedded_hide_nav_descriptions' => '1',
            'embedded_hide_overview_pathways' => '1',
            'embedded_use_page_width' => '1',
            'hide_zero_status' => '1',
            'nav_columns' => '3',
        );
    }

    private function settings() {
        return wp_parse_args((array) get_option(self::OPTION_KEY, array()), $this->default_settings());
    }

    private function choice($value, $allowed, $default) {
        $value = sanitize_key((string) $value);
        return in_array($value, $allowed, true) ? $value : $default;
    }

    private function bool_value($value, $default = false) {
        if ($value === null || $value === '') {
            return (bool) $default;
        }
        if (is_bool($value)) {
            return $value;
        }
        return in_array(strtolower((string) $value), array('1', 'true', 'yes', 'on', 'show'), true);
    }

    private function color($value, $default) {
        $value = trim((string) $value);
        return preg_match('/^#[0-9a-fA-F]{6}$/', $value) ? strtolower($value) : $default;
    }

    private function font_stack($value, $default = 'inherit') {
        $value = trim((string) $value);
        if ($value === '') {
            return $default;
        }
        $value = preg_replace("/[^a-zA-Z0-9,\\- _\"]/", '', $value);
        return $value !== '' ? $value : $default;
    }

    private function branding_presets() {
        return array(
            'platform' => array(
                'accent' => '#6f1225', 'accent_contrast' => '#ffffff', 'ink' => '#131313',
                'muted' => '#5e6268', 'surface' => '#ffffff', 'soft' => '#f6f7f8',
                'line' => '#d9dde2', 'success' => '#176b3a', 'warning' => '#8a5a00',
                'danger' => '#9b1111', 'font_family' => 'inherit', 'heading_font_family' => 'inherit',
            ),
            'sustainable-catalyst' => array(
                'accent' => '#9b1111', 'accent_contrast' => '#ffffff', 'ink' => '#000000',
                'muted' => '#555555', 'surface' => '#ffffff', 'soft' => '#f7f3ea',
                'line' => '#d9d2c4', 'success' => '#176b3a', 'warning' => '#8a5a00',
                'danger' => '#9b1111', 'font_family' => 'Montserrat, Arial, sans-serif',
                'heading_font_family' => 'Spartan, Montserrat, Arial, sans-serif',
            ),
        );
    }

    private function branding_context($settings, $atts, $raw_atts = array()) {
        $preset = $this->choice($atts['branding'], array('platform', 'sustainable-catalyst', 'inherit', 'custom'), $settings['branding_preset']);
        $presets = $this->branding_presets();
        $custom = array(
            'accent' => $this->color($atts['accent'], $this->color($settings['brand_accent'], '#6f1225')),
            'accent_contrast' => $this->color($atts['accent_contrast'], $this->color($settings['brand_accent_contrast'], '#ffffff')),
            'ink' => $this->color($atts['ink'], $this->color($settings['brand_ink'], '#131313')),
            'muted' => $this->color($atts['muted'], $this->color($settings['brand_muted'], '#5e6268')),
            'surface' => $this->color($atts['surface'], $this->color($settings['brand_surface'], '#ffffff')),
            'soft' => $this->color($atts['soft'], $this->color($settings['brand_soft'], '#f6f7f8')),
            'line' => $this->color($atts['line'], $this->color($settings['brand_line'], '#d9dde2')),
            'success' => $this->color($atts['success'], $this->color($settings['brand_success'], '#176b3a')),
            'warning' => $this->color($atts['warning'], $this->color($settings['brand_warning'], '#8a5a00')),
            'danger' => $this->color($atts['danger'], $this->color($settings['brand_danger'], '#9b1111')),
            'font_family' => $this->font_stack($atts['font_family'], $this->font_stack($settings['brand_font_family'], 'inherit')),
            'heading_font_family' => $this->font_stack($atts['heading_font_family'], $this->font_stack($settings['brand_heading_font_family'], 'inherit')),
        );
        if ($preset === 'inherit') {
            $tokens = array();
        } elseif ($preset === 'custom') {
            $tokens = $custom;
        } else {
            $tokens = $presets[$preset] ?? $presets['platform'];
            foreach ($custom as $key => $value) {
                if (array_key_exists($key, $raw_atts) && trim((string) $atts[$key]) !== '') {
                    $tokens[$key] = $value;
                }
            }
        }
        $radius = min(24, max(0, absint($atts['radius'] !== '' ? $atts['radius'] : $settings['brand_radius'])));
        $max_width = min(1800, max(680, absint($atts['max_width'] !== '' ? $atts['max_width'] : $settings['brand_max_width'])));
        $shadow_choice = $this->choice($atts['shadow'] !== '' ? $atts['shadow'] : $settings['brand_shadow'], array('none', 'subtle', 'raised'), 'subtle');
        $shadows = array('none' => 'none', 'subtle' => '0 10px 28px rgba(0,0,0,.055)', 'raised' => '0 18px 48px rgba(0,0,0,.12)');
        $tokens['radius'] = $radius . 'px';
        $tokens['max_width'] = $max_width . 'px';
        $tokens['shadow'] = $shadows[$shadow_choice];
        $tokens = apply_filters('scfs_support_branding_tokens', $tokens, $preset, $settings, $atts);
        return array('preset' => $preset, 'tokens' => is_array($tokens) ? $tokens : array());
    }

    private function branding_style($tokens) {
        $map = array(
            'accent' => '--scfs-token-accent', 'accent_contrast' => '--scfs-token-accent-contrast', 'ink' => '--scfs-token-ink',
            'muted' => '--scfs-token-muted', 'surface' => '--scfs-token-surface', 'soft' => '--scfs-token-soft',
            'line' => '--scfs-token-line', 'success' => '--scfs-token-success', 'warning' => '--scfs-token-warning',
            'danger' => '--scfs-token-danger', 'radius' => '--scfs-token-radius', 'shadow' => '--scfs-token-shadow',
            'max_width' => '--scfs-token-max-width', 'font_family' => '--scfs-token-font-family',
            'heading_font_family' => '--scfs-token-heading-font-family',
        );
        $rules = array();
        foreach ($map as $key => $property) {
            if (isset($tokens[$key]) && $tokens[$key] !== '') {
                $rules[] = $property . ':' . str_replace(array(';', '{', '}'), '', (string) $tokens[$key]);
            }
        }
        return implode(';', $rules);
    }

    private function display_context($settings, $raw_atts, $atts) {
        $mode = $this->choice($atts['mode'], array('standalone', 'embedded'), $settings['default_mode']);
        if (!array_key_exists('mode', $raw_atts) && ($atts['compact'] ?? '0') === '1') {
            $mode = 'embedded';
        }
        $embedded = $mode === 'embedded';
        $default_view = array_key_exists('default_view', $raw_atts)
            ? $atts['default_view']
            : ($embedded ? $settings['embedded_default_view'] : $settings['default_view']);
        return array(
            'mode' => $mode,
            'embedded' => $embedded,
            'default_view' => $this->choice($default_view, array_keys($this->navigation_items($settings)), 'overview'),
            'show_header' => $this->bool_value($atts['show_header'], !$embedded || empty($settings['embedded_hide_header'])),
            'show_status' => $this->bool_value($atts['show_status'], !$embedded || empty($settings['embedded_hide_status'])),
            'show_product_filter' => $this->bool_value($atts['show_product_filter'], true),
            'show_navigation' => $this->bool_value($atts['show_navigation'], true),
            'show_nav_descriptions' => $this->bool_value($atts['show_nav_descriptions'], !$embedded || empty($settings['embedded_hide_nav_descriptions'])),
            'show_overview_pathways' => $this->bool_value($atts['show_overview_pathways'], !$embedded || empty($settings['embedded_hide_overview_pathways'])),
            'hide_zero_status' => $this->bool_value($atts['hide_zero_status'], !empty($settings['hide_zero_status'])),
            'use_page_width' => $this->bool_value($atts['use_page_width'], $embedded && !empty($settings['embedded_use_page_width'])),
            'nav_columns' => min(4, max(1, absint($atts['nav_columns'] !== '' ? $atts['nav_columns'] : $settings['nav_columns']))),
        );
    }

    public function release_statuses() {
        return array(
            'planned' => __('Planned', 'sustainable-catalyst-feature-suggestions'),
            'preview' => __('Preview', 'sustainable-catalyst-feature-suggestions'),
            'current' => __('Current', 'sustainable-catalyst-feature-suggestions'),
            'maintenance' => __('Maintenance', 'sustainable-catalyst-feature-suggestions'),
            'superseded' => __('Superseded', 'sustainable-catalyst-feature-suggestions'),
            'retired' => __('Retired', 'sustainable-catalyst-feature-suggestions'),
        );
    }

    public function register_release_post_type() {
        register_post_type(self::RELEASE_POST_TYPE, array(
            'labels' => array(
                'name' => __('Release Intelligence', 'sustainable-catalyst-feature-suggestions'),
                'singular_name' => __('Release Record', 'sustainable-catalyst-feature-suggestions'),
                'menu_name' => __('Releases', 'sustainable-catalyst-feature-suggestions'),
                'add_new_item' => __('Add Release Record', 'sustainable-catalyst-feature-suggestions'),
                'edit_item' => __('Edit Release Record', 'sustainable-catalyst-feature-suggestions'),
                'new_item' => __('New Release Record', 'sustainable-catalyst-feature-suggestions'),
                'view_item' => __('View Release Record', 'sustainable-catalyst-feature-suggestions'),
                'search_items' => __('Search Release Records', 'sustainable-catalyst-feature-suggestions'),
                'not_found' => __('No release records found.', 'sustainable-catalyst-feature-suggestions'),
                'all_items' => __('All Release Records', 'sustainable-catalyst-feature-suggestions'),
            ),
            'public' => true,
            'publicly_queryable' => true,
            'show_ui' => true,
            'show_in_menu' => 'edit.php?post_type=' . Sustainable_Catalyst_Feature_Suggestions::POST_TYPE,
            'show_in_rest' => true,
            'rest_base' => 'support-releases',
            'supports' => array('title', 'editor', 'excerpt', 'author', 'revisions', 'custom-fields'),
            'has_archive' => 'support-releases',
            'rewrite' => array('slug' => 'support-releases', 'with_front' => false),
            'taxonomies' => array(
                SCFS_Product_Integration::PRODUCT_TAXONOMY,
                SCFS_Product_Integration::VERSION_TAXONOMY,
                SCFS_Product_Integration::COMPONENT_TAXONOMY,
                SCFS_Product_Integration::ISSUE_TAXONOMY,
                SCFS_Product_Integration::RELEASE_TAXONOMY,
            ),
            'capability_type' => 'post',
            'map_meta_cap' => true,
        ));
    }

    public function register_release_meta() {
        $definitions = array(
            '_scfs_release_status' => array('type' => 'string', 'sanitize_callback' => array($this, 'sanitize_release_status')),
            '_scfs_release_date' => array('type' => 'string', 'sanitize_callback' => array($this, 'sanitize_date')),
            '_scfs_release_summary' => array('type' => 'string', 'sanitize_callback' => 'sanitize_textarea_field'),
            '_scfs_release_support_note' => array('type' => 'string', 'sanitize_callback' => 'sanitize_textarea_field'),
            '_scfs_release_highlights' => array('type' => 'string', 'sanitize_callback' => 'sanitize_textarea_field'),
            '_scfs_release_known_limitations' => array('type' => 'string', 'sanitize_callback' => 'sanitize_textarea_field'),
            '_scfs_release_docs_url' => array('type' => 'string', 'sanitize_callback' => 'esc_url_raw'),
            '_scfs_release_changelog_url' => array('type' => 'string', 'sanitize_callback' => 'esc_url_raw'),
            '_scfs_release_term_id' => array('type' => 'integer', 'sanitize_callback' => 'absint'),
            '_scfs_release_related_articles' => array('type' => 'array', 'sanitize_callback' => array($this, 'sanitize_id_array')),
            '_scfs_release_related_issues' => array('type' => 'array', 'sanitize_callback' => array($this, 'sanitize_id_array')),
            '_scfs_release_related_suggestions' => array('type' => 'array', 'sanitize_callback' => array($this, 'sanitize_id_array')),
        );
        foreach ($definitions as $key => $definition) {
            $args = array(
                'type' => $definition['type'],
                'single' => true,
                'show_in_rest' => $definition['type'] === 'array' ? array('schema' => array('type' => 'array', 'items' => array('type' => 'integer'))) : true,
                'sanitize_callback' => $definition['sanitize_callback'],
                'auth_callback' => function () { return current_user_can('edit_posts'); },
            );
            register_post_meta(self::RELEASE_POST_TYPE, $key, $args);
        }
    }

    public function sanitize_release_status($value) {
        $value = sanitize_key($value);
        return isset($this->release_statuses()[$value]) ? $value : 'planned';
    }

    public function sanitize_date($value) {
        $value = sanitize_text_field($value);
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : '';
    }

    public function sanitize_id_array($value) {
        if (is_string($value)) {
            $value = preg_split('/[\s,]+/', $value);
        }
        return array_values(array_unique(array_filter(array_map('absint', (array) $value))));
    }

    public function register_shortcodes() {
        add_shortcode(self::SHORTCODE, array($this, 'render_shortcode'));
        add_shortcode(self::LEGACY_SHORTCODE, array($this, 'render_shortcode'));
    }

    public function register_assets() {
        $style_path = dirname(__DIR__) . '/assets/product-support-platform.css';
        $script_path = dirname(__DIR__) . '/assets/product-support-platform.js';
        $style_version = is_readable($style_path) ? (string) filemtime($style_path) : self::VERSION;
        $script_version = is_readable($script_path) ? (string) filemtime($script_path) : self::VERSION;
        wp_register_style(
            'scfs-product-support-platform',
            plugins_url('../assets/product-support-platform.css', __FILE__),
            array('scfs-guided-resolution', 'scfs-knowledge-base'),
            $style_version
        );
        wp_register_script(
            'scfs-product-support-platform',
            plugins_url('../assets/product-support-platform.js', __FILE__),
            array(),
            $script_version,
            true
        );
    }

    private function enqueue_child_assets() {
        if (function_exists('wp_enqueue_style')) {
            wp_enqueue_style('scfs-feature-suggestions');
            wp_enqueue_style('scfs-forms');
        }
        if (function_exists('wp_enqueue_script')) {
            wp_enqueue_script('scfs-forms');
        }
        if (class_exists('SCFS_Public_Ideas') && method_exists(SCFS_Public_Ideas::instance(), 'enqueue_public_assets')) {
            SCFS_Public_Ideas::instance()->enqueue_public_assets();
        }
    }

    public function admin_assets($hook) {
        if (strpos((string) $hook, 'scfs-product-support-platform') !== false || strpos((string) $hook, self::RELEASE_POST_TYPE) !== false) {
            wp_enqueue_style('scfs-admin');
        }
    }

    public function add_meta_boxes() {
        add_meta_box(
            'scfs-release-intelligence',
            __('Release Intelligence', 'sustainable-catalyst-feature-suggestions'),
            array($this, 'release_meta_box'),
            self::RELEASE_POST_TYPE,
            'normal',
            'high'
        );
    }

    public function release_meta_box($post) {
        wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME);
        $status = get_post_meta($post->ID, '_scfs_release_status', true) ?: 'planned';
        $date = get_post_meta($post->ID, '_scfs_release_date', true);
        $summary = get_post_meta($post->ID, '_scfs_release_summary', true);
        $support = get_post_meta($post->ID, '_scfs_release_support_note', true);
        $highlights = get_post_meta($post->ID, '_scfs_release_highlights', true);
        $limitations = get_post_meta($post->ID, '_scfs_release_known_limitations', true);
        $docs = get_post_meta($post->ID, '_scfs_release_docs_url', true);
        $changelog = get_post_meta($post->ID, '_scfs_release_changelog_url', true);
        $term_id = absint(get_post_meta($post->ID, '_scfs_release_term_id', true));
        $articles = implode(', ', $this->sanitize_id_array(get_post_meta($post->ID, '_scfs_release_related_articles', true)));
        $issues = implode(', ', $this->sanitize_id_array(get_post_meta($post->ID, '_scfs_release_related_issues', true)));
        $suggestions = implode(', ', $this->sanitize_id_array(get_post_meta($post->ID, '_scfs_release_related_suggestions', true)));
        echo '<div class="scfs-release-editor"><div class="scfs-grid scfs-grid-two">';
        echo '<p><label><strong>' . esc_html__('Release status', 'sustainable-catalyst-feature-suggestions') . '</strong><br><select name="scfs_release_status">';
        foreach ($this->release_statuses() as $key => $label) {
            echo '<option value="' . esc_attr($key) . '" ' . selected($status, $key, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select></label></p>';
        echo '<p><label><strong>' . esc_html__('Release date', 'sustainable-catalyst-feature-suggestions') . '</strong><br><input type="date" name="scfs_release_date" value="' . esc_attr($date) . '"></label></p></div>';
        echo '<p><label><strong>' . esc_html__('Public summary', 'sustainable-catalyst-feature-suggestions') . '</strong><br><textarea class="widefat" rows="3" name="scfs_release_summary">' . esc_textarea($summary) . '</textarea></label></p>';
        echo '<p><label><strong>' . esc_html__('Support and compatibility note', 'sustainable-catalyst-feature-suggestions') . '</strong><br><textarea class="widefat" rows="3" name="scfs_release_support_note">' . esc_textarea($support) . '</textarea></label></p>';
        echo '<p><label><strong>' . esc_html__('Highlights', 'sustainable-catalyst-feature-suggestions') . '</strong><br><textarea class="widefat" rows="5" name="scfs_release_highlights" placeholder="One highlight per line">' . esc_textarea($highlights) . '</textarea></label></p>';
        echo '<p><label><strong>' . esc_html__('Known limitations', 'sustainable-catalyst-feature-suggestions') . '</strong><br><textarea class="widefat" rows="4" name="scfs_release_known_limitations" placeholder="One limitation per line">' . esc_textarea($limitations) . '</textarea></label></p>';
        echo '<div class="scfs-grid scfs-grid-two"><p><label><strong>' . esc_html__('Documentation URL', 'sustainable-catalyst-feature-suggestions') . '</strong><br><input class="widefat" type="url" name="scfs_release_docs_url" value="' . esc_attr($docs) . '"></label></p>';
        echo '<p><label><strong>' . esc_html__('Changelog URL', 'sustainable-catalyst-feature-suggestions') . '</strong><br><input class="widefat" type="url" name="scfs_release_changelog_url" value="' . esc_attr($changelog) . '"></label></p></div>';
        echo '<p><label><strong>' . esc_html__('Canonical Release taxonomy term ID', 'sustainable-catalyst-feature-suggestions') . '</strong><br><input type="number" min="0" name="scfs_release_term_id" value="' . esc_attr((string) $term_id) . '"></label></p>';
        echo '<div class="scfs-grid scfs-grid-three"><p><label><strong>' . esc_html__('Related article IDs', 'sustainable-catalyst-feature-suggestions') . '</strong><br><input class="widefat" name="scfs_release_related_articles" value="' . esc_attr($articles) . '"></label></p>';
        echo '<p><label><strong>' . esc_html__('Related known-issue IDs', 'sustainable-catalyst-feature-suggestions') . '</strong><br><input class="widefat" name="scfs_release_related_issues" value="' . esc_attr($issues) . '"></label></p>';
        echo '<p><label><strong>' . esc_html__('Related public suggestion IDs', 'sustainable-catalyst-feature-suggestions') . '</strong><br><input class="widefat" name="scfs_release_related_suggestions" value="' . esc_attr($suggestions) . '"></label></p></div>';
        echo '<p class="description">' . esc_html__('Release records are public only when published. Private feature-suggestion text is never exposed through release intelligence.', 'sustainable-catalyst-feature-suggestions') . '</p></div>';
    }

    public function save_release($post_id, $post) {
        if (!isset($_POST[self::NONCE_NAME]) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[self::NONCE_NAME])), self::NONCE_ACTION)) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (!$post || !current_user_can('edit_post', $post_id)) {
            return;
        }
        update_post_meta($post_id, '_scfs_release_status', $this->sanitize_release_status($_POST['scfs_release_status'] ?? 'planned'));
        update_post_meta($post_id, '_scfs_release_date', $this->sanitize_date($_POST['scfs_release_date'] ?? ''));
        update_post_meta($post_id, '_scfs_release_summary', sanitize_textarea_field(wp_unslash($_POST['scfs_release_summary'] ?? '')));
        update_post_meta($post_id, '_scfs_release_support_note', sanitize_textarea_field(wp_unslash($_POST['scfs_release_support_note'] ?? '')));
        update_post_meta($post_id, '_scfs_release_highlights', sanitize_textarea_field(wp_unslash($_POST['scfs_release_highlights'] ?? '')));
        update_post_meta($post_id, '_scfs_release_known_limitations', sanitize_textarea_field(wp_unslash($_POST['scfs_release_known_limitations'] ?? '')));
        update_post_meta($post_id, '_scfs_release_docs_url', esc_url_raw(wp_unslash($_POST['scfs_release_docs_url'] ?? '')));
        update_post_meta($post_id, '_scfs_release_changelog_url', esc_url_raw(wp_unslash($_POST['scfs_release_changelog_url'] ?? '')));
        update_post_meta($post_id, '_scfs_release_term_id', absint($_POST['scfs_release_term_id'] ?? 0));
        update_post_meta($post_id, '_scfs_release_related_articles', $this->sanitize_id_array(wp_unslash($_POST['scfs_release_related_articles'] ?? '')));
        update_post_meta($post_id, '_scfs_release_related_issues', $this->sanitize_id_array(wp_unslash($_POST['scfs_release_related_issues'] ?? '')));
        update_post_meta($post_id, '_scfs_release_related_suggestions', $this->sanitize_id_array(wp_unslash($_POST['scfs_release_related_suggestions'] ?? '')));
        update_post_meta($post_id, '_scfs_product_support_schema_version', self::SCHEMA_VERSION);
        $record = $this->release_record(get_post($post_id), true);
        do_action('scfs_release_intelligence_saved', $post_id, $record);
        $event = array(
            'event_id' => function_exists('wp_generate_uuid4') ? wp_generate_uuid4() : uniqid('scfs-release-', true),
            'event_type' => 'release.intelligence_updated',
            'schema_version' => Sustainable_Catalyst_Feature_Suggestions::EVENT_SCHEMA_VERSION,
            'source' => 'feature_suggestions',
            'source_version' => self::VERSION,
            'occurred_at' => gmdate('c'),
            'data' => array(
                'release_record_id' => (int) $post_id,
                'status' => $record['status'] ?? 'planned',
                'release_date' => $record['release_date'] ?? '',
                'product_slugs' => array_values(wp_list_pluck($record['products'] ?? array(), 'slug')),
                'readiness' => $record['readiness'] ?? array(),
                'public_record' => $post->post_status === 'publish',
            ),
        );
        do_action('scfs_event', $event);
        do_action('sc_platform_event', $event);
        do_action('scfs_dispatch_event', $event);
    }

    public function schema_record() {
        return array(
            'schema' => 'scfs-product-support-platform/' . self::SCHEMA_VERSION,
            'version' => self::VERSION,
            'platform' => 'Sustainable Catalyst Product Support and Feedback Platform',
            'public_modules' => array(
                'guided_resolution',
                'support_knowledge_base',
                'known_issues',
                'cross_product_support_orchestration',
                'platform_incident_summaries',
                'release_intelligence',
                'feature_suggestions',
                'public_ideas',
                'advisory_voting',
                'forms_and_surveys',
            ),
            'private_integrations' => array('contact_and_engagement'),
            'operational_modules' => array('support_content_operations', 'product_onboarding', 'support_readiness', 'cross_product_orchestration'),
            'shared_context' => array('product', 'product_version', 'component', 'issue_type', 'release'),
            'shortcodes' => array(self::SHORTCODE, self::LEGACY_SHORTCODE),
            'rendering_modes' => array('standalone', 'embedded'),
            'branding_presets' => array('platform', 'sustainable-catalyst', 'inherit', 'custom'),
            'branding_tokens' => array('accent', 'accent_contrast', 'ink', 'muted', 'surface', 'soft', 'line', 'success', 'warning', 'danger', 'radius', 'shadow', 'max_width', 'font_family', 'heading_font_family'),
            'navigation' => array(
                'client_side_view_switching' => true,
                'browser_history' => true,
                'direct_links' => true,
                'anchored_fallback' => true,
                'product_context_preserved' => true,
                'empty_sections_hidden_until_published' => class_exists('SCFS_Support_Content_Operations') ? !empty(SCFS_Support_Content_Operations::instance()->settings()['hide_empty_support_sections']) : false,
            ),
            'routes' => array(
                '/product-support/schema',
                '/product-support/overview',
                '/product-support/releases',
                '/product-support/products',
                '/product-support/view',
                '/product-support/handoff-schema',
                '/product-support/snapshot',
            ),
            'governance' => array(
                'public_support_source_of_truth' => 'feature_suggestions',
                'private_case_source_of_truth' => 'contact_and_engagement',
                'voting_is_advisory' => true,
                'surveys_do_not_auto_prioritize' => true,
                'human_review_required' => true,
                'automatic_case_creation' => false,
            ),
        );
    }

    public function handoff_schema() {
        return array(
            'schema' => 'sc-product-support-contact-handoff/' . self::SCHEMA_VERSION,
            'source' => 'sustainable-catalyst-feature-suggestions',
            'destination' => 'contact_and_engagement',
            'trigger' => 'unresolved_public_support',
            'context' => array(
                'product', 'product_version', 'component', 'issue_type', 'release',
                'search_query', 'search_confidence', 'articles_viewed', 'known_issues_viewed',
                'release_records_viewed', 'public_suggestions_viewed', 'platform_incidents_viewed',
                'related_products', 'dependency_path', 'unresolved_reason',
            ),
            'privacy' => array(
                'identity_collected_by_feature_suggestions' => false,
                'case_content_stored_by_feature_suggestions' => false,
                'consent_required' => true,
                'token_expiration_minutes' => 30,
            ),
            'routing' => array(
                'automatic_case_creation' => false,
                'human_review_required' => true,
                'private_documents_supported_by_destination' => true,
            ),
        );
    }

    private function count_post_type($post_type, $statuses = array('publish')) {
        $counts = wp_count_posts($post_type);
        $total = 0;
        foreach ($statuses as $status) {
            $total += isset($counts->{$status}) ? (int) $counts->{$status} : 0;
        }
        return $total;
    }

    private function active_known_issue_count($product = '') {
        $args = array(
            'post_type' => SCFS_Knowledge_Base_Foundation::ISSUE_POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => 1,
            'fields' => 'ids',
            'no_found_rows' => false,
            'meta_query' => array(array('key' => '_scfs_issue_status', 'value' => array('resolved', 'closed'), 'compare' => 'NOT IN')),
        );
        if ($product !== '') {
            $args['tax_query'] = array(array('taxonomy' => SCFS_Product_Integration::PRODUCT_TAXONOMY, 'field' => 'slug', 'terms' => $product));
        }
        $query = new WP_Query($args);
        return (int) $query->found_posts;
    }

    private function public_idea_count($product = '') {
        $args = array(
            'post_type' => Sustainable_Catalyst_Feature_Suggestions::POST_TYPE,
            'post_status' => array('publish', 'pending', 'draft', 'private'),
            'posts_per_page' => 1,
            'fields' => 'ids',
            'no_found_rows' => false,
            'meta_query' => array(array('key' => '_scfs_public_visibility', 'value' => '1')),
        );
        if ($product !== '') {
            $args['tax_query'] = array(array('taxonomy' => SCFS_Product_Integration::PRODUCT_TAXONOMY, 'field' => 'slug', 'terms' => $product));
        }
        $query = new WP_Query($args);
        return (int) $query->found_posts;
    }

    private function open_survey_count() {
        if (!post_type_exists(SCFS_Forms_Foundation::FORM_TYPE)) {
            return 0;
        }
        $query = new WP_Query(array(
            'post_type' => SCFS_Forms_Foundation::FORM_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => 1,
            'fields' => 'ids',
            'no_found_rows' => false,
            'meta_query' => array(
                array('key' => '_scfs_form_mode', 'value' => 'survey'),
                array('key' => '_scfs_form_open', 'value' => '1'),
            ),
        ));
        return (int) $query->found_posts;
    }

    public function overview_record($product = '') {
        $product = sanitize_title($product);
        $latest = $this->release_query($product, 1);
        $current_release = $latest->posts ? $this->release_record($latest->posts[0], false) : null;
        return array(
            'schema' => 'scfs-product-support-overview/' . self::SCHEMA_VERSION,
            'version' => self::VERSION,
            'generated_at' => gmdate('c'),
            'product' => $product,
            'counts' => array(
                'support_articles' => $this->count_post_type(SCFS_Knowledge_Base_Foundation::ARTICLE_POST_TYPE),
                'active_known_issues' => $this->active_known_issue_count($product),
                'public_ideas' => $this->public_idea_count($product),
                'open_surveys' => $this->open_survey_count(),
                'release_records' => $this->count_post_type(self::RELEASE_POST_TYPE),
            ),
            'current_release' => $current_release,
            'privacy' => array(
                'public_records_only' => true,
                'private_case_content_exposed' => false,
                'contact_and_engagement_is_private_system_of_record' => true,
            ),
        );
    }

    private function release_query($product = '', $limit = 12, $status = '') {
        $args = array(
            'post_type' => self::RELEASE_POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => max(1, min(100, absint($limit))),
            'meta_key' => '_scfs_release_date',
            'orderby' => array('meta_value' => 'DESC', 'modified' => 'DESC'),
            'order' => 'DESC',
            'no_found_rows' => true,
        );
        if ($status !== '' && isset($this->release_statuses()[$status])) {
            $args['meta_query'] = array(array('key' => '_scfs_release_status', 'value' => $status));
        }
        if ($product !== '') {
            $args['tax_query'] = array(array('taxonomy' => SCFS_Product_Integration::PRODUCT_TAXONOMY, 'field' => 'slug', 'terms' => $product));
        }
        return new WP_Query($args);
    }

    private function term_records($post_id, $taxonomy) {
        $terms = get_the_terms($post_id, $taxonomy);
        if (!$terms || is_wp_error($terms)) {
            return array();
        }
        return array_map(function ($term) {
            return array('id' => (int) $term->term_id, 'slug' => $term->slug, 'name' => $term->name);
        }, $terms);
    }

    private function public_related_suggestions($ids) {
        $records = array();
        foreach ($this->sanitize_id_array($ids) as $id) {
            $post = get_post($id);
            if (!$post || $post->post_type !== Sustainable_Catalyst_Feature_Suggestions::POST_TYPE || get_post_meta($id, '_scfs_public_visibility', true) !== '1') {
                continue;
            }
            $records[] = array(
                'id' => (int) $id,
                'title' => get_the_title($id),
                'state' => get_post_meta($id, '_scfs_public_state', true) ?: 'under_review',
                'supports' => absint(get_post_meta($id, '_scfs_support_votes', true)),
                'private_submission_text_exposed' => false,
            );
        }
        return $records;
    }

    private function public_related_posts($ids, $post_type) {
        $records = array();
        foreach ($this->sanitize_id_array($ids) as $id) {
            $post = get_post($id);
            if (!$post || $post->post_type !== $post_type || $post->post_status !== 'publish') {
                continue;
            }
            $records[] = array('id' => (int) $id, 'title' => get_the_title($id), 'url' => get_permalink($id));
        }
        return $records;
    }

    public function calculate_release_readiness($post_id) {
        $summary = trim((string) get_post_meta($post_id, '_scfs_release_summary', true));
        $support = trim((string) get_post_meta($post_id, '_scfs_release_support_note', true));
        $date = trim((string) get_post_meta($post_id, '_scfs_release_date', true));
        $changelog = trim((string) get_post_meta($post_id, '_scfs_release_changelog_url', true));
        $products = $this->term_records($post_id, SCFS_Product_Integration::PRODUCT_TAXONOMY);
        $articles = $this->public_related_posts(get_post_meta($post_id, '_scfs_release_related_articles', true), SCFS_Knowledge_Base_Foundation::ARTICLE_POST_TYPE);
        $issues = $this->public_related_posts(get_post_meta($post_id, '_scfs_release_related_issues', true), SCFS_Knowledge_Base_Foundation::ISSUE_POST_TYPE);
        $score = 0;
        $missing = array();
        foreach (array(
            'public_summary' => array($summary !== '', 15),
            'support_note' => array($support !== '', 15),
            'release_date' => array($date !== '', 15),
            'changelog' => array($changelog !== '', 10),
            'product_context' => array(!empty($products), 15),
        ) as $label => $signal) {
            if ($signal[0]) {
                $score += $signal[1];
            } else {
                $missing[] = $label;
            }
        }
        $score += min(20, count($articles) * 5);
        $score += min(10, count($issues) * 2);
        $critical = 0;
        foreach ($issues as $issue) {
            $severity = get_post_meta($issue['id'], '_scfs_issue_severity', true);
            $status = get_post_meta($issue['id'], '_scfs_issue_status', true);
            if ($severity === 'critical' && !in_array($status, array('resolved', 'closed'), true)) {
                $critical++;
            }
        }
        $blockers = array();
        if ($critical > 0) {
            $score -= min(60, $critical * 25);
            $blockers[] = 'unresolved_critical_issues';
        }
        $score = max(0, min(100, $score));
        $state = ($blockers || $score < 50) ? 'not_ready' : ($score < 80 ? 'review' : 'ready');
        return array(
            'schema' => 'scfs-release-readiness/' . self::SCHEMA_VERSION,
            'score' => (int) $score,
            'state' => $state,
            'missing' => $missing,
            'blockers' => $blockers,
            'human_review_required' => true,
        );
    }

    public function release_record($post, $include_internal = false) {
        if (is_numeric($post)) {
            $post = get_post(absint($post));
        }
        if (!$post || $post->post_type !== self::RELEASE_POST_TYPE) {
            return array();
        }
        $record = array(
            'schema' => 'scfs-release-intelligence/' . self::SCHEMA_VERSION,
            'id' => (int) $post->ID,
            'slug' => $post->post_name,
            'title' => $post->post_title,
            'summary' => get_post_meta($post->ID, '_scfs_release_summary', true) ?: wp_trim_words(wp_strip_all_tags($post->post_excerpt ?: $post->post_content), 45),
            'status' => get_post_meta($post->ID, '_scfs_release_status', true) ?: 'planned',
            'release_date' => get_post_meta($post->ID, '_scfs_release_date', true),
            'support_note' => get_post_meta($post->ID, '_scfs_release_support_note', true),
            'highlights' => array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string) get_post_meta($post->ID, '_scfs_release_highlights', true))))),
            'known_limitations' => array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string) get_post_meta($post->ID, '_scfs_release_known_limitations', true))))),
            'documentation_url' => get_post_meta($post->ID, '_scfs_release_docs_url', true),
            'changelog_url' => get_post_meta($post->ID, '_scfs_release_changelog_url', true),
            'url' => get_permalink($post),
            'products' => $this->term_records($post->ID, SCFS_Product_Integration::PRODUCT_TAXONOMY),
            'product_versions' => $this->term_records($post->ID, SCFS_Product_Integration::VERSION_TAXONOMY),
            'components' => $this->term_records($post->ID, SCFS_Product_Integration::COMPONENT_TAXONOMY),
            'release_terms' => $this->term_records($post->ID, SCFS_Product_Integration::RELEASE_TAXONOMY),
            'related_articles' => $this->public_related_posts(get_post_meta($post->ID, '_scfs_release_related_articles', true), SCFS_Knowledge_Base_Foundation::ARTICLE_POST_TYPE),
            'related_known_issues' => $this->public_related_posts(get_post_meta($post->ID, '_scfs_release_related_issues', true), SCFS_Knowledge_Base_Foundation::ISSUE_POST_TYPE),
            'related_public_suggestions' => $this->public_related_suggestions(get_post_meta($post->ID, '_scfs_release_related_suggestions', true)),
            'readiness' => $this->calculate_release_readiness($post->ID),
            'private_suggestion_text_exposed' => false,
        );
        if ($include_internal) {
            $record['canonical_release_term_id'] = absint(get_post_meta($post->ID, '_scfs_release_term_id', true));
            $record['post_status'] = $post->post_status;
        }
        return $record;
    }

    private function allowed_views($settings = null) {
        $settings = is_array($settings) ? $settings : $this->settings();
        return array_keys($this->navigation_items($settings));
    }

    private function current_context() {
        $settings = $this->settings();
        $allowed = $this->allowed_views($settings);
        $view = isset($_GET['scfs_support_view']) ? sanitize_key(wp_unslash($_GET['scfs_support_view'])) : sanitize_key($settings['default_view']);
        if (!in_array($view, $allowed, true)) {
            $view = 'overview';
        }
        return array(
            'view' => $view,
            'product' => isset($_GET['scfs_support_product']) ? sanitize_title(wp_unslash($_GET['scfs_support_product'])) : '',
            'survey' => isset($_GET['scfs_support_survey']) ? absint($_GET['scfs_support_survey']) : 0,
        );
    }

    private function support_base_url($candidate = '') {
        $candidate = trim((string) $candidate);
        if ($candidate === '' && function_exists('is_singular') && is_singular() && function_exists('get_permalink')) {
            $candidate = (string) get_permalink();
        }
        if ($candidate === '') {
            $candidate = function_exists('remove_query_arg')
                ? (string) remove_query_arg(array('scfs_support_view', 'scfs_support_product', 'scfs_support_survey'))
                : home_url('/');
        }
        if (function_exists('esc_url_raw')) {
            $candidate = esc_url_raw($candidate);
        }
        $parse = function_exists('wp_parse_url') ? 'wp_parse_url' : 'parse_url';
        $home_parts = call_user_func($parse, home_url('/'));
        $candidate_parts = call_user_func($parse, $candidate);
        if (!is_array($candidate_parts) || !empty($candidate_parts['host']) && is_array($home_parts) && !empty($home_parts['host']) && strtolower($candidate_parts['host']) !== strtolower($home_parts['host'])) {
            $candidate = home_url('/');
        }
        return function_exists('remove_query_arg')
            ? (string) remove_query_arg(array('scfs_support_view', 'scfs_support_product', 'scfs_support_survey'), $candidate)
            : $candidate;
    }

    private function normalized_anchor($anchor) {
        $anchor = sanitize_title((string) $anchor);
        return $anchor !== '' ? $anchor : 'support-center';
    }

    private function view_url($view, $product = '', $extra = array(), $anchor = null) {
        $args = array_merge(array('scfs_support_view' => $view), $extra);
        if ($product !== '') {
            $args['scfs_support_product'] = $product;
        }
        $base = $this->active_base_url !== '' ? $this->active_base_url : $this->support_base_url();
        $url = add_query_arg($args, $base);
        $anchor = $anchor === null ? $this->active_anchor : $this->normalized_anchor($anchor);
        return $anchor !== '' ? $url . '#' . rawurlencode($anchor) : $url;
    }

    private function view_link_attributes($view, $product = '', $extra = array()) {
        $parts = array(
            'data-scfs-support-view="' . esc_attr($view) . '"',
            'data-scfs-support-product="' . esc_attr($product) . '"',
        );
        if (!empty($extra['scfs_support_survey'])) {
            $parts[] = 'data-scfs-support-survey="' . esc_attr(absint($extra['scfs_support_survey'])) . '"';
        }
        return implode(' ', $parts);
    }

    private function render_product_select($selected, $view = 'overview') {
        $terms = get_terms(array('taxonomy' => SCFS_Product_Integration::PRODUCT_TAXONOMY, 'hide_empty' => false, 'orderby' => 'name'));
        if (is_wp_error($terms)) {
            $terms = array();
        }
        $action = $this->active_base_url !== '' ? $this->active_base_url : $this->support_base_url();
        if ($this->active_anchor !== '') {
            $action .= '#' . rawurlencode($this->active_anchor);
        }
        echo '<form class="scfs-support-product-filter" method="get" action="' . esc_url($action) . '" data-scfs-support-filter data-scfs-current-view="' . esc_attr($view) . '">';
        echo '<input type="hidden" name="scfs_support_view" value="' . esc_attr($view) . '">';
        echo '<label><span>' . esc_html__('Support context', 'sustainable-catalyst-feature-suggestions') . '</span><select name="scfs_support_product"><option value="">' . esc_html__('All Sustainable Catalyst products', 'sustainable-catalyst-feature-suggestions') . '</option>';
        foreach ($terms as $term) {
            echo '<option value="' . esc_attr($term->slug) . '" ' . selected($selected, $term->slug, false) . '>' . esc_html($term->name) . '</option>';
        }
        echo '</select></label><button type="submit">' . esc_html__('Apply product', 'sustainable-catalyst-feature-suggestions') . '</button></form>';
    }

    private function navigation_items($settings, $product = '', $respect_content = false) {
        $items = array(
            'overview' => array('label' => __('Support overview', 'sustainable-catalyst-feature-suggestions'), 'description' => __('Status and starting points', 'sustainable-catalyst-feature-suggestions')),
            'resolve' => array('label' => __('Find a resolution', 'sustainable-catalyst-feature-suggestions'), 'description' => __('Guided search and known issues', 'sustainable-catalyst-feature-suggestions')),
            'documentation' => array('label' => __('Knowledge Base', 'sustainable-catalyst-feature-suggestions'), 'description' => __('How-to and reference articles', 'sustainable-catalyst-feature-suggestions')),
            'issues' => array('label' => __('Known issues', 'sustainable-catalyst-feature-suggestions'), 'description' => __('Current product problems', 'sustainable-catalyst-feature-suggestions')),
            'releases' => array('label' => __('Releases', 'sustainable-catalyst-feature-suggestions'), 'description' => __('Changes and compatibility', 'sustainable-catalyst-feature-suggestions')),
            'ideas' => array('label' => __('Public ideas', 'sustainable-catalyst-feature-suggestions'), 'description' => __('Vote and follow decisions', 'sustainable-catalyst-feature-suggestions')),
            'suggest' => array('label' => __('Suggest a feature', 'sustainable-catalyst-feature-suggestions'), 'description' => __('Submit a public product idea', 'sustainable-catalyst-feature-suggestions')),
            'surveys' => array('label' => __('Surveys', 'sustainable-catalyst-feature-suggestions'), 'description' => __('Participate in product research', 'sustainable-catalyst-feature-suggestions')),
            'private-support' => array('label' => __('Private support', 'sustainable-catalyst-feature-suggestions'), 'description' => __('Continue to Contact and Engagement', 'sustainable-catalyst-feature-suggestions')),
        );
        if (empty($settings['show_surveys'])) {
            unset($items['surveys']);
        }
        if (empty($settings['show_public_ideas'])) {
            unset($items['ideas']);
        }
        if (empty($settings['show_release_intelligence'])) {
            unset($items['releases']);
        }
        if ($respect_content && class_exists('SCFS_Support_Content_Operations')) {
            $operations_settings = SCFS_Support_Content_Operations::instance()->settings();
            if (!empty($operations_settings['hide_empty_support_sections'])) {
                $counts = SCFS_Support_Content_Operations::instance()->product_content_counts($product, false);
                $content_views = array(
                    'documentation' => 'support_articles',
                    'issues' => 'known_issues',
                    'releases' => 'release_records',
                    'ideas' => 'public_ideas',
                    'surveys' => 'open_surveys',
                );
                foreach ($content_views as $view => $count_key) {
                    if (isset($items[$view]) && empty($counts[$count_key])) {
                        unset($items[$view]);
                    }
                }
            }
        }
        return apply_filters('scfs_support_navigation_items', $items, $settings, $product, $respect_content);
    }

    public function render_shortcode($atts = array()) {
        $settings = $this->settings();
        $raw_atts = is_array($atts) ? $atts : array();
        $atts = shortcode_atts(array(
            'title' => $settings['support_center_title'],
            'intro' => $settings['support_center_intro'],
            'default_view' => '',
            'compact' => '0',
            'mode' => $settings['default_mode'],
            'branding' => $settings['branding_preset'],
            'show_header' => '',
            'show_status' => '',
            'show_product_filter' => '1',
            'show_navigation' => '1',
            'show_nav_descriptions' => '',
            'show_overview_pathways' => '',
            'hide_zero_status' => '',
            'use_page_width' => '',
            'nav_columns' => '',
            'accent' => '',
            'accent_contrast' => '',
            'ink' => '',
            'muted' => '',
            'surface' => '',
            'soft' => '',
            'line' => '',
            'success' => '',
            'warning' => '',
            'danger' => '',
            'radius' => '',
            'shadow' => '',
            'max_width' => '',
            'font_family' => '',
            'heading_font_family' => '',
            'class' => '',
            'anchor' => 'support-center',
            'interactive' => '1',
        ), $raw_atts, self::SHORTCODE);
        $atts = apply_filters('scfs_product_support_center_atts', $atts, $raw_atts, $settings);
        wp_enqueue_style('scfs-product-support-platform');
        if (function_exists('wp_enqueue_script')) {
            wp_enqueue_script('scfs-product-support-platform');
        }
        $this->enqueue_child_assets();
        if (class_exists('SCFS_Cross_Product_Support_Orchestration')) {
            wp_enqueue_style('scfs-cross-product-orchestration');
        }
        $display = $this->display_context($settings, $raw_atts, $atts);
        $branding = $this->branding_context($settings, $atts, $raw_atts);
        $context = $this->current_context();
        if (!isset($_GET['scfs_support_view'])) {
            $context['view'] = $display['default_view'];
        }
        $overview = $this->overview_record($context['product']);
        self::$render_count++;
        $instance_id = 'scfs-support-platform-' . self::$render_count;
        $base_url = $this->support_base_url();
        $anchor = $this->normalized_anchor($atts['anchor']);
        $interactive = $this->bool_value($atts['interactive'], true);
        $previous_base_url = $this->active_base_url;
        $previous_anchor = $this->active_anchor;
        $previous_pathways = $this->active_show_overview_pathways;
        $this->active_base_url = $base_url;
        $this->active_anchor = $anchor;
        $this->active_show_overview_pathways = $display['show_overview_pathways'];
        $classes = array(
            'scfs-support-platform',
            'scfs-support-platform--mode-' . $display['mode'],
            'scfs-support-platform--brand-' . $branding['preset'],
            'scfs-support-platform--nav-' . $display['nav_columns'],
        );
        if (($atts['compact'] ?? '0') === '1') {
            $classes[] = 'scfs-support-platform--compact';
        }
        if ($display['use_page_width']) {
            $classes[] = 'scfs-support-platform--page-width';
        }
        if (!$display['show_nav_descriptions']) {
            $classes[] = 'scfs-support-platform--nav-labels-only';
        }
        $custom_classes = preg_split('/\s+/', trim((string) $atts['class']));
        foreach ($custom_classes as $custom_class) {
            $custom_class = preg_replace('/[^a-zA-Z0-9_-]/', '', $custom_class);
            if ($custom_class !== '') {
                $classes[] = $custom_class;
            }
        }
        $style = $this->branding_style($branding['tokens']);
        $aria = $display['show_header']
            ? ' aria-labelledby="' . esc_attr($instance_id . '-title') . '"'
            : ' aria-label="' . esc_attr($atts['title']) . '"';
        ob_start();
        $endpoint = function_exists('rest_url') ? rest_url(Sustainable_Catalyst_Feature_Suggestions::REST_NAMESPACE . '/product-support/view') : '';
        echo '<section id="' . esc_attr($instance_id) . '" class="' . esc_attr(implode(' ', array_unique($classes))) . '" data-scfs-mode="' . esc_attr($display['mode']) . '" data-scfs-branding="' . esc_attr($branding['preset']) . '" data-scfs-interactive="' . ($interactive ? '1' : '0') . '" data-scfs-endpoint="' . esc_url($endpoint) . '" data-scfs-base-url="' . esc_url($base_url) . '" data-scfs-anchor="' . esc_attr($anchor) . '" data-scfs-current-view="' . esc_attr($context['view']) . '" data-scfs-show-overview-pathways="' . ($display['show_overview_pathways'] ? '1' : '0') . '" style="' . esc_attr($style) . '"' . $aria . '>';
        if ($display['show_header']) {
            echo '<header class="scfs-support-platform__hero"><p class="scfs-support-platform__eyebrow">' . esc_html__('Product Support and Feedback Platform', 'sustainable-catalyst-feature-suggestions') . '</p><h2 id="' . esc_attr($instance_id . '-title') . '">' . esc_html($atts['title']) . '</h2><p>' . esc_html($atts['intro']) . '</p>';
            echo '<div class="scfs-support-platform__boundary"><strong>' . esc_html__('Public support center:', 'sustainable-catalyst-feature-suggestions') . '</strong> ' . esc_html__('Documentation, known issues, releases, feature ideas, voting, and surveys live here. Private cases, messages, contact details, and documents remain in Contact and Engagement.', 'sustainable-catalyst-feature-suggestions') . '</div></header>';
        } else {
            echo '<h2 class="scfs-support-platform__sr-only">' . esc_html($atts['title']) . '</h2>';
        }
        if ($display['show_product_filter']) {
            $this->render_product_select($context['product'], $context['view']);
        }
        if ($display['show_status']) {
            $status_items = array(
                'support_articles' => __('Support articles', 'sustainable-catalyst-feature-suggestions'),
                'active_known_issues' => __('Active known issues', 'sustainable-catalyst-feature-suggestions'),
                'release_records' => __('Release records', 'sustainable-catalyst-feature-suggestions'),
                'public_ideas' => __('Public ideas', 'sustainable-catalyst-feature-suggestions'),
                'open_surveys' => __('Open surveys', 'sustainable-catalyst-feature-suggestions'),
            );
            if ($display['hide_zero_status']) {
                $status_items = array_filter($status_items, function ($label, $key) use ($overview) {
                    return !empty($overview['counts'][$key]);
                }, ARRAY_FILTER_USE_BOTH);
            }
            if ($status_items) {
                echo '<div class="scfs-support-platform__status" aria-label="' . esc_attr__('Support status summary', 'sustainable-catalyst-feature-suggestions') . '">';
                foreach ($status_items as $key => $label) {
                    echo '<div><strong>' . esc_html((string) $overview['counts'][$key]) . '</strong><span>' . esc_html($label) . '</span></div>';
                }
                echo '</div>';
            }
        }
        if ($display['show_navigation']) {
            echo '<nav class="scfs-support-platform__nav" aria-label="' . esc_attr__('Product support sections', 'sustainable-catalyst-feature-suggestions') . '">';
            foreach ($this->navigation_items($settings, $context['product'], true) as $key => $item) {
                $active = $context['view'] === $key;
                echo '<a class="' . ($active ? 'is-active' : '') . '" href="' . esc_url($this->view_url($key, $context['product'])) . '" ' . $this->view_link_attributes($key, $context['product']) . ($active ? ' aria-current="page"' : '') . '><strong>' . esc_html($item['label']) . '</strong>';
                if ($display['show_nav_descriptions']) {
                    echo '<span>' . esc_html($item['description']) . '</span>';
                }
                echo '</a>';
            }
            echo '</nav>';
        }
        echo '<div class="scfs-support-platform__workspace" data-scfs-view="' . esc_attr($context['view']) . '" aria-live="polite" aria-busy="false">';
        $this->render_view($context, $overview, $settings, $display);
        echo '</div></section>';
        $html = ob_get_clean();
        $this->active_base_url = $previous_base_url;
        $this->active_anchor = $previous_anchor;
        $this->active_show_overview_pathways = $previous_pathways;
        return $html;
    }

    private function with_request_value($key, $value, $callback) {
        $exists = array_key_exists($key, $_GET);
        $previous = $exists ? $_GET[$key] : null;
        if ($value !== '' && !$exists) {
            $_GET[$key] = $value;
        }
        $result = call_user_func($callback);
        if (!$exists) {
            unset($_GET[$key]);
        } else {
            $_GET[$key] = $previous;
        }
        return $result;
    }

    private function render_view($context, $overview, $settings, $display = array()) {
        switch ($context['view']) {
            case 'resolve':
                echo $this->with_request_value('scfs_resolution_product', $context['product'], function () {
                    return SCFS_Guided_Resolution::instance()->render_shortcode(array('title' => __('Find a resolution', 'sustainable-catalyst-feature-suggestions'), 'compact' => '1', 'show_handoff' => '1'));
                }); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                break;
            case 'documentation':
                echo $this->with_request_value('scfs_kb_product', $context['product'], function () {
                    return SCFS_Knowledge_Base_Foundation::instance()->render_shortcode(array('title' => __('Support Knowledge Base', 'sustainable-catalyst-feature-suggestions'), 'show_known_issues' => '0'));
                }); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                break;
            case 'issues':
                $this->render_known_issues($context['product']);
                break;
            case 'platform':
                if (class_exists('SCFS_Cross_Product_Support_Orchestration')) {
                    SCFS_Cross_Product_Support_Orchestration::instance()->render_public_panel($context['product'], true, 30);
                } else {
                    echo '<div class="scfs-support-platform__empty"><h3>' . esc_html__('Platform status unavailable', 'sustainable-catalyst-feature-suggestions') . '</h3></div>';
                }
                break;
            case 'releases':
                $this->render_releases($context['product'], 30);
                break;
            case 'ideas':
                echo SCFS_Public_Ideas::instance()->shortcode(array('title' => __('Public product ideas', 'sustainable-catalyst-feature-suggestions'), 'limit' => '30', 'product' => $context['product'])); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                break;
            case 'suggest':
                echo Sustainable_Catalyst_Feature_Suggestions::instance()->render_shortcode(array('title' => __('Suggest a product improvement', 'sustainable-catalyst-feature-suggestions'), 'intro' => __('Submit a non-confidential feature idea. Product context, public participation, and roadmap decisions remain subject to human review.', 'sustainable-catalyst-feature-suggestions'))); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                break;
            case 'surveys':
                $this->render_surveys($context['survey'], $context['product']);
                break;
            case 'private-support':
                $this->render_private_support($settings, $context['product']);
                break;
            case 'overview':
            default:
                $this->render_overview($overview, $context['product'], $settings, $display);
                break;
        }
    }

    private function render_overview($overview, $product, $settings, $display = array()) {
        echo '<div class="scfs-support-platform__overview"><section><h3>' . esc_html__('Start with guided resolution', 'sustainable-catalyst-feature-suggestions') . '</h3><p>' . esc_html__('Describe the task, symptom, or exact error fragment. Current known issues are prioritized before general documentation.', 'sustainable-catalyst-feature-suggestions') . '</p>';
        echo $this->with_request_value('scfs_resolution_product', $product, function () {
            return SCFS_Guided_Resolution::instance()->render_shortcode(array('title' => __('Search product support', 'sustainable-catalyst-feature-suggestions'), 'compact' => '1', 'show_handoff' => '1'));
        }); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo '</section>';
        $content_counts = class_exists('SCFS_Support_Content_Operations') ? SCFS_Support_Content_Operations::instance()->product_content_counts($product, false) : array();
        $hide_empty = class_exists('SCFS_Support_Content_Operations') && !empty(SCFS_Support_Content_Operations::instance()->settings()['hide_empty_support_sections']);
        if (!isset($display['show_overview_pathways']) || $display['show_overview_pathways']) {
            echo '<section class="scfs-support-platform__pathways"><h3>' . esc_html__('Choose another support pathway', 'sustainable-catalyst-feature-suggestions') . '</h3><div class="scfs-support-platform__cards">';
        $cards = array(
            'documentation' => array(__('Browse documentation', 'sustainable-catalyst-feature-suggestions'), __('Read task-based guidance, troubleshooting, technical references, and product collections.', 'sustainable-catalyst-feature-suggestions')),
            'issues' => array(__('Check known issues', 'sustainable-catalyst-feature-suggestions'), __('Review verified problems, current status, workarounds, and planned resolutions.', 'sustainable-catalyst-feature-suggestions')),
            'ideas' => array(__('Review and support ideas', 'sustainable-catalyst-feature-suggestions'), __('Vote on moderated public suggestions and follow official roadmap decisions.', 'sustainable-catalyst-feature-suggestions')),
            'suggest' => array(__('Suggest an improvement', 'sustainable-catalyst-feature-suggestions'), __('Submit a structured, non-confidential feature or documentation request.', 'sustainable-catalyst-feature-suggestions')),
            'surveys' => array(__('Participate in research', 'sustainable-catalyst-feature-suggestions'), __('Respond to open product surveys. Survey results inform review but do not automatically set priorities.', 'sustainable-catalyst-feature-suggestions')),
            'private-support' => array(__('Continue to private support', 'sustainable-catalyst-feature-suggestions'), __('Use Contact and Engagement for identity, correspondence, private documents, and case lifecycle management.', 'sustainable-catalyst-feature-suggestions')),
        );
        $content_view_counts = array('documentation' => 'support_articles', 'issues' => 'known_issues', 'ideas' => 'public_ideas', 'surveys' => 'open_surveys');
        foreach ($cards as $view => $card) {
            if (($view === 'surveys' && empty($settings['show_surveys'])) || ($view === 'ideas' && empty($settings['show_public_ideas']))) {
                continue;
            }
            if ($hide_empty && isset($content_view_counts[$view]) && empty($content_counts[$content_view_counts[$view]])) {
                continue;
            }
            echo '<a class="scfs-support-platform__pathway-card" href="' . esc_url($this->view_url($view, $product)) . '" ' . $this->view_link_attributes($view, $product) . '><h4>' . esc_html($card[0]) . '</h4><p>' . esc_html($card[1]) . '</p><span class="scfs-support-platform__pathway-action">' . esc_html__('Open pathway', 'sustainable-catalyst-feature-suggestions') . ' →</span></a>';
        }
            echo '</div></section>';
        }
        if (!empty($settings['show_release_intelligence']) && (!$hide_empty || !empty($content_counts['release_records']))) {
            echo '<section><div class="scfs-support-platform__section-head"><div><h3>' . esc_html__('Release intelligence', 'sustainable-catalyst-feature-suggestions') . '</h3><p>' . esc_html__('Current, planned, maintenance, and retired releases with documentation and known-issue relationships.', 'sustainable-catalyst-feature-suggestions') . '</p></div><a href="' . esc_url($this->view_url('releases', $product)) . '" ' . $this->view_link_attributes('releases', $product) . '>' . esc_html__('View all releases', 'sustainable-catalyst-feature-suggestions') . '</a></div>';
            $this->render_releases($product, absint($settings['featured_release_limit']), true);
            echo '</section>';
        }
        echo '</div>';
    }

    private function render_known_issues($product) {
        $args = array(
            'post_type' => SCFS_Knowledge_Base_Foundation::ISSUE_POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => 50,
            'orderby' => 'modified',
            'order' => 'DESC',
        );
        if ($product !== '') {
            $args['tax_query'] = array(array('taxonomy' => SCFS_Product_Integration::PRODUCT_TAXONOMY, 'field' => 'slug', 'terms' => $product));
        }
        $query = new WP_Query($args);
        echo '<section class="scfs-support-platform__issues"><header><p class="scfs-support-platform__kicker">' . esc_html__('Verified product status', 'sustainable-catalyst-feature-suggestions') . '</p><h3>' . esc_html__('Known issues', 'sustainable-catalyst-feature-suggestions') . '</h3><p>' . esc_html__('Published issue records show status, severity, visible symptoms, workarounds, and release relationships.', 'sustainable-catalyst-feature-suggestions') . '</p></header><div class="scfs-support-platform__issue-grid">';
        if (!$query->have_posts()) {
            echo '<div class="scfs-support-platform__empty"><h4>' . esc_html__('No published known issues match this product', 'sustainable-catalyst-feature-suggestions') . '</h4><p>' . esc_html__('Use Guided Resolution for broader troubleshooting or continue to private support when the problem is not documented.', 'sustainable-catalyst-feature-suggestions') . '</p></div>';
        }
        foreach ($query->posts as $issue) {
            $status = get_post_meta($issue->ID, '_scfs_issue_status', true) ?: 'investigating';
            $severity = get_post_meta($issue->ID, '_scfs_issue_severity', true) ?: 'moderate';
            $symptom = get_post_meta($issue->ID, '_scfs_issue_symptom', true);
            $workaround = get_post_meta($issue->ID, '_scfs_issue_workaround', true);
            echo '<article><div class="scfs-support-platform__badges"><span class="status-' . esc_attr($status) . '">' . esc_html(ucwords(str_replace('_', ' ', $status))) . '</span><span class="severity-' . esc_attr($severity) . '">' . esc_html(ucwords(str_replace('_', ' ', $severity))) . '</span></div><h4><a href="' . esc_url(get_permalink($issue)) . '">' . esc_html(get_the_title($issue)) . '</a></h4>';
            if ($symptom) {
                echo '<p>' . esc_html(wp_trim_words($symptom, 35)) . '</p>';
            }
            if ($workaround) {
                echo '<p class="scfs-support-platform__workaround"><strong>' . esc_html__('Workaround:', 'sustainable-catalyst-feature-suggestions') . '</strong> ' . esc_html(wp_trim_words($workaround, 28)) . '</p>';
            }
            echo '<a href="' . esc_url(get_permalink($issue)) . '">' . esc_html__('View issue details', 'sustainable-catalyst-feature-suggestions') . ' →</a></article>';
        }
        echo '</div></section>';
    }

    private function render_releases($product, $limit = 12, $embedded = false) {
        $query = $this->release_query($product, $limit);
        if (!$embedded) {
            echo '<section class="scfs-support-platform__releases"><header><p class="scfs-support-platform__kicker">' . esc_html__('Product change intelligence', 'sustainable-catalyst-feature-suggestions') . '</p><h3>' . esc_html__('Releases', 'sustainable-catalyst-feature-suggestions') . '</h3><p>' . esc_html__('Review current, planned, maintenance, superseded, and retired releases with support implications and linked public records.', 'sustainable-catalyst-feature-suggestions') . '</p></header>';
        }
        echo '<div class="scfs-support-platform__release-grid">';
        if (!$query->have_posts()) {
            echo '<div class="scfs-support-platform__empty"><h4>' . esc_html__('No published release records match this product', 'sustainable-catalyst-feature-suggestions') . '</h4><p>' . esc_html__('Release taxonomy relationships remain available in Support Articles, Known Issues, and public ideas.', 'sustainable-catalyst-feature-suggestions') . '</p></div>';
        }
        foreach ($query->posts as $post) {
            $record = $this->release_record($post, false);
            echo '<article class="scfs-support-platform__release-card"><div class="scfs-support-platform__release-meta"><span class="status-' . esc_attr($record['status']) . '">' . esc_html($this->release_statuses()[$record['status']] ?? $record['status']) . '</span>';
            if ($record['release_date']) {
                echo '<time datetime="' . esc_attr($record['release_date']) . '">' . esc_html($record['release_date']) . '</time>';
            }
            echo '</div><h4><a href="' . esc_url($record['url']) . '">' . esc_html($record['title']) . '</a></h4><p>' . esc_html($record['summary']) . '</p>';
            if ($record['products']) {
                echo '<p class="scfs-support-platform__products">' . esc_html(implode(' · ', wp_list_pluck($record['products'], 'name'))) . '</p>';
            }
            echo '<div class="scfs-support-platform__release-links"><a href="' . esc_url($record['url']) . '">' . esc_html__('Release details', 'sustainable-catalyst-feature-suggestions') . '</a>';
            if ($record['documentation_url']) {
                echo '<a href="' . esc_url($record['documentation_url']) . '">' . esc_html__('Documentation', 'sustainable-catalyst-feature-suggestions') . '</a>';
            }
            if ($record['changelog_url']) {
                echo '<a href="' . esc_url($record['changelog_url']) . '">' . esc_html__('Changelog', 'sustainable-catalyst-feature-suggestions') . '</a>';
            }
            echo '</div></article>';
        }
        echo '</div>';
        if (!$embedded) {
            echo '</section>';
        }
    }

    private function render_surveys($selected_id, $product) {
        if (!post_type_exists(SCFS_Forms_Foundation::FORM_TYPE)) {
            echo '<div class="scfs-support-platform__empty"><h3>' . esc_html__('Survey system unavailable', 'sustainable-catalyst-feature-suggestions') . '</h3></div>';
            return;
        }
        if ($selected_id) {
            $form = get_post($selected_id);
            if ($form && $form->post_type === SCFS_Forms_Foundation::FORM_TYPE && $form->post_status === 'publish' && get_post_meta($form->ID, '_scfs_form_mode', true) === 'survey') {
                echo '<p><a href="' . esc_url($this->view_url('surveys', $product)) . '" ' . $this->view_link_attributes('surveys', $product) . '>← ' . esc_html__('Back to surveys', 'sustainable-catalyst-feature-suggestions') . '</a></p>';
                echo SCFS_Forms_Foundation::instance()->render_shortcode(array('id' => (string) $form->ID, 'class' => 'scfs-support-platform-survey')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                return;
            }
        }
        $query = new WP_Query(array(
            'post_type' => SCFS_Forms_Foundation::FORM_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => 30,
            'orderby' => 'modified',
            'order' => 'DESC',
            'meta_query' => array(
                array('key' => '_scfs_form_mode', 'value' => 'survey'),
                array('key' => '_scfs_form_open', 'value' => '1'),
            ),
        ));
        echo '<section class="scfs-support-platform__surveys"><header><p class="scfs-support-platform__kicker">' . esc_html__('Product research', 'sustainable-catalyst-feature-suggestions') . '</p><h3>' . esc_html__('Open surveys', 'sustainable-catalyst-feature-suggestions') . '</h3><p>' . esc_html__('Survey participation informs human review. Responses do not automatically approve features, set roadmap state, or create private support cases.', 'sustainable-catalyst-feature-suggestions') . '</p></header><div class="scfs-support-platform__survey-grid">';
        if (!$query->have_posts()) {
            echo '<div class="scfs-support-platform__empty"><h4>' . esc_html__('No surveys are currently open', 'sustainable-catalyst-feature-suggestions') . '</h4></div>';
        }
        foreach ($query->posts as $survey) {
            echo '<article><h4>' . esc_html(get_the_title($survey)) . '</h4><p>' . esc_html(wp_trim_words(wp_strip_all_tags($survey->post_content), 35)) . '</p><a href="' . esc_url($this->view_url('surveys', $product, array('scfs_support_survey' => $survey->ID))) . '" ' . $this->view_link_attributes('surveys', $product, array('scfs_support_survey' => $survey->ID)) . '>' . esc_html__('Take survey', 'sustainable-catalyst-feature-suggestions') . ' →</a></article>';
        }
        echo '</div></section>';
    }

    private function render_private_support($settings, $product) {
        $url = $settings['contact_engagement_url'];
        if ($product !== '') {
            $url = add_query_arg(array('support_product' => $product, 'source' => 'product-support-platform'), $url);
        }
        echo '<section class="scfs-support-platform__private"><p class="scfs-support-platform__kicker">' . esc_html__('Private case handoff', 'sustainable-catalyst-feature-suggestions') . '</p><h3>' . esc_html__('Continue to Contact and Engagement', 'sustainable-catalyst-feature-suggestions') . '</h3><p>' . esc_html__('Use private support when public documentation and known issues do not resolve the problem, or when you need to share identity, correspondence, private files, institutional context, or other non-public information.', 'sustainable-catalyst-feature-suggestions') . '</p>';
        echo '<div class="scfs-support-platform__privacy-grid"><div><strong>' . esc_html__('Feature Suggestions keeps', 'sustainable-catalyst-feature-suggestions') . '</strong><p>' . esc_html__('Public support context, public records viewed, privacy-minimized search confidence, and an opaque handoff token.', 'sustainable-catalyst-feature-suggestions') . '</p></div><div><strong>' . esc_html__('Contact and Engagement keeps', 'sustainable-catalyst-feature-suggestions') . '</strong><p>' . esc_html__('Contact details, private messages, documents, case records, communication history, and lifecycle management.', 'sustainable-catalyst-feature-suggestions') . '</p></div></div>';
        echo '<p><a class="scfs-support-platform__primary-action" href="' . esc_url($url) . '">' . esc_html__('Open private support intake', 'sustainable-catalyst-feature-suggestions') . '</a></p><p class="scfs-support-platform__privacy-note">' . esc_html__('Do not paste passwords, API keys, payment information, medical information, or regulated data into public support searches or feature suggestions.', 'sustainable-catalyst-feature-suggestions') . '</p></section>';
    }

    public function template_include($template) {
        if (is_post_type_archive(self::RELEASE_POST_TYPE)) {
            $candidate = plugin_dir_path(__DIR__) . 'templates/archive-support-releases.php';
            if (file_exists($candidate)) {
                return $candidate;
            }
        }
        return $template;
    }

    public function decorate_release_content($content) {
        if (!is_singular(self::RELEASE_POST_TYPE) || !in_the_loop() || !is_main_query()) {
            return $content;
        }
        $record = $this->release_record(get_the_ID(), false);
        if (!$record) {
            return $content;
        }
        ob_start();
        echo '<aside class="scfs-release-summary"><div class="scfs-support-platform__release-meta"><span class="status-' . esc_attr($record['status']) . '">' . esc_html($this->release_statuses()[$record['status']] ?? $record['status']) . '</span>';
        if ($record['release_date']) {
            echo '<time datetime="' . esc_attr($record['release_date']) . '">' . esc_html($record['release_date']) . '</time>';
        }
        echo '</div>';
        if ($record['summary']) {
            echo '<p>' . esc_html($record['summary']) . '</p>';
        }
        if ($record['support_note']) {
            echo '<p><strong>' . esc_html__('Support note:', 'sustainable-catalyst-feature-suggestions') . '</strong> ' . esc_html($record['support_note']) . '</p>';
        }
        if ($record['products']) {
            echo '<p><strong>' . esc_html__('Products:', 'sustainable-catalyst-feature-suggestions') . '</strong> ' . esc_html(implode(', ', wp_list_pluck($record['products'], 'name'))) . '</p>';
        }
        echo '</aside>';
        if ($record['highlights']) {
            echo '<section class="scfs-release-related"><h2>' . esc_html__('Release highlights', 'sustainable-catalyst-feature-suggestions') . '</h2><ul>';
            foreach ($record['highlights'] as $item) {
                echo '<li>' . esc_html($item) . '</li>';
            }
            echo '</ul></section>';
        }
        if ($record['known_limitations']) {
            echo '<section class="scfs-release-related"><h2>' . esc_html__('Known limitations', 'sustainable-catalyst-feature-suggestions') . '</h2><ul>';
            foreach ($record['known_limitations'] as $item) {
                echo '<li>' . esc_html($item) . '</li>';
            }
            echo '</ul></section>';
        }
        $groups = array(
            __('Related documentation', 'sustainable-catalyst-feature-suggestions') => $record['related_articles'],
            __('Related known issues', 'sustainable-catalyst-feature-suggestions') => $record['related_known_issues'],
        );
        foreach ($groups as $title => $items) {
            if (!$items) {
                continue;
            }
            echo '<section class="scfs-release-related"><h2>' . esc_html($title) . '</h2><ul>';
            foreach ($items as $item) {
                echo '<li><a href="' . esc_url($item['url']) . '">' . esc_html($item['title']) . '</a></li>';
            }
            echo '</ul></section>';
        }
        if ($record['related_public_suggestions']) {
            echo '<section class="scfs-release-related"><h2>' . esc_html__('Related public ideas', 'sustainable-catalyst-feature-suggestions') . '</h2><ul>';
            foreach ($record['related_public_suggestions'] as $item) {
                echo '<li>' . esc_html($item['title']) . ' — ' . esc_html(ucwords(str_replace('_', ' ', $item['state']))) . '</li>';
            }
            echo '</ul><p class="description">' . esc_html__('Only moderated public idea metadata is shown. Private submission text and contact information are not exposed.', 'sustainable-catalyst-feature-suggestions') . '</p></section>';
        }
        return $content . ob_get_clean();
    }

    public function release_columns($columns) {
        $columns['scfs_release_status'] = __('Status', 'sustainable-catalyst-feature-suggestions');
        $columns['scfs_release_date'] = __('Release Date', 'sustainable-catalyst-feature-suggestions');
        $columns['scfs_release_products'] = __('Products', 'sustainable-catalyst-feature-suggestions');
        return $columns;
    }

    public function release_column_content($column, $post_id) {
        if ($column === 'scfs_release_status') {
            $status = get_post_meta($post_id, '_scfs_release_status', true) ?: 'planned';
            echo esc_html($this->release_statuses()[$status] ?? $status);
        } elseif ($column === 'scfs_release_date') {
            echo esc_html(get_post_meta($post_id, '_scfs_release_date', true) ?: '—');
        } elseif ($column === 'scfs_release_products') {
            echo esc_html(implode(', ', SCFS_Product_Integration::instance()->flat_term_names($post_id, SCFS_Product_Integration::PRODUCT_TAXONOMY)) ?: '—');
        }
    }

    public function register_admin_page() {
        add_submenu_page(
            'edit.php?post_type=' . Sustainable_Catalyst_Feature_Suggestions::POST_TYPE,
            __('Product Support and Feedback Platform', 'sustainable-catalyst-feature-suggestions'),
            __('Support Platform', 'sustainable-catalyst-feature-suggestions'),
            'edit_posts',
            'scfs-product-support-platform',
            array($this, 'render_admin_page')
        );
    }

    public function render_admin_page() {
        if (!current_user_can('edit_posts')) {
            wp_die(__('You do not have permission to manage the Product Support Platform.', 'sustainable-catalyst-feature-suggestions'));
        }
        $settings = $this->settings();
        $overview = $this->overview_record('');
        echo '<div class="wrap scfs-platform-center"><h1>' . esc_html__('Product Support and Feedback Platform', 'sustainable-catalyst-feature-suggestions') . '</h1><p>' . esc_html__('Unified public support with embedded reliability, configurable branding, and product-by-product content operations.', 'sustainable-catalyst-feature-suggestions') . '</p>';
        echo '<div class="scfs-intel-cards">';
        foreach ($overview['counts'] as $key => $value) {
            echo '<div class="scfs-intel-card"><span>' . esc_html(ucwords(str_replace('_', ' ', $key))) . '</span><strong>' . esc_html((string) $value) . '</strong></div>';
        }
        echo '</div><p><a class="button button-primary" href="' . esc_url(admin_url('post-new.php?post_type=' . self::RELEASE_POST_TYPE)) . '">' . esc_html__('Add Release Record', 'sustainable-catalyst-feature-suggestions') . '</a> <a class="button" href="' . esc_url(admin_url('edit.php?post_type=' . self::RELEASE_POST_TYPE)) . '">' . esc_html__('Manage Releases', 'sustainable-catalyst-feature-suggestions') . '</a></p>';
        echo '<h2>' . esc_html__('Public shortcodes', 'sustainable-catalyst-feature-suggestions') . '</h2><p><code>[' . esc_html(self::SHORTCODE) . ']</code></p><p><code>[' . esc_html(self::SHORTCODE) . ' mode="embedded" default_view="resolve"]</code></p>';
        echo '<h2>' . esc_html__('Platform settings', 'sustainable-catalyst-feature-suggestions') . '</h2><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="scfs_save_product_support_settings">';
        wp_nonce_field('scfs_save_product_support_settings', 'scfs_product_support_nonce');
        echo '<table class="form-table"><tr><th>' . esc_html__('Support center title', 'sustainable-catalyst-feature-suggestions') . '</th><td><input class="regular-text" name="support_center_title" value="' . esc_attr($settings['support_center_title']) . '"></td></tr>';
        echo '<tr><th>' . esc_html__('Intro', 'sustainable-catalyst-feature-suggestions') . '</th><td><textarea class="large-text" rows="4" name="support_center_intro">' . esc_textarea($settings['support_center_intro']) . '</textarea></td></tr>';
        echo '<tr><th>' . esc_html__('Default rendering mode', 'sustainable-catalyst-feature-suggestions') . '</th><td><select name="default_mode"><option value="standalone" ' . selected($settings['default_mode'], 'standalone', false) . '>' . esc_html__('Standalone application', 'sustainable-catalyst-feature-suggestions') . '</option><option value="embedded" ' . selected($settings['default_mode'], 'embedded', false) . '>' . esc_html__('Embedded in a designed page', 'sustainable-catalyst-feature-suggestions') . '</option></select><p class="description">' . esc_html__('Embedded mode removes duplicate application chrome and inherits the page width.', 'sustainable-catalyst-feature-suggestions') . '</p></td></tr>';
        echo '<tr><th>' . esc_html__('Default standalone section', 'sustainable-catalyst-feature-suggestions') . '</th><td><select name="default_view">';
        foreach ($this->navigation_items($settings) as $key => $item) {
            echo '<option value="' . esc_attr($key) . '" ' . selected($settings['default_view'], $key, false) . '>' . esc_html($item['label']) . '</option>';
        }
        echo '</select></td></tr><tr><th>' . esc_html__('Default embedded section', 'sustainable-catalyst-feature-suggestions') . '</th><td><select name="embedded_default_view">';
        foreach ($this->navigation_items($settings) as $key => $item) {
            echo '<option value="' . esc_attr($key) . '" ' . selected($settings['embedded_default_view'], $key, false) . '>' . esc_html($item['label']) . '</option>';
        }
        echo '</select></td></tr><tr><th>' . esc_html__('Contact and Engagement URL', 'sustainable-catalyst-feature-suggestions') . '</th><td><input class="regular-text" type="url" name="contact_engagement_url" value="' . esc_attr($settings['contact_engagement_url']) . '"><p class="description">' . esc_html__('Private support case intake destination. Automatic case creation remains disabled.', 'sustainable-catalyst-feature-suggestions') . '</p></td></tr>';
        echo '<tr><th>' . esc_html__('Visible modules', 'sustainable-catalyst-feature-suggestions') . '</th><td><label><input type="checkbox" name="show_surveys" value="1" ' . checked($settings['show_surveys'], '1', false) . '> ' . esc_html__('Surveys', 'sustainable-catalyst-feature-suggestions') . '</label><br><label><input type="checkbox" name="show_public_ideas" value="1" ' . checked($settings['show_public_ideas'], '1', false) . '> ' . esc_html__('Public ideas and voting', 'sustainable-catalyst-feature-suggestions') . '</label><br><label><input type="checkbox" name="show_release_intelligence" value="1" ' . checked($settings['show_release_intelligence'], '1', false) . '> ' . esc_html__('Release intelligence', 'sustainable-catalyst-feature-suggestions') . '</label></td></tr>';
        echo '<tr><th>' . esc_html__('Featured release limit', 'sustainable-catalyst-feature-suggestions') . '</th><td><input type="number" min="1" max="12" name="featured_release_limit" value="' . esc_attr($settings['featured_release_limit']) . '"></td></tr></table>';
        echo '<h2>' . esc_html__('Branding and site integration', 'sustainable-catalyst-feature-suggestions') . '</h2><p>' . esc_html__('Use a preset, inherit Astra/WordPress theme variables, or define a custom scoped brand. These tokens also restyle Guided Resolution, the Knowledge Base, forms, surveys, and public ideas when they appear inside the Support Center.', 'sustainable-catalyst-feature-suggestions') . '</p>';
        echo '<table class="form-table"><tr><th>' . esc_html__('Branding preset', 'sustainable-catalyst-feature-suggestions') . '</th><td><select name="branding_preset"><option value="platform" ' . selected($settings['branding_preset'], 'platform', false) . '>' . esc_html__('Platform default', 'sustainable-catalyst-feature-suggestions') . '</option><option value="sustainable-catalyst" ' . selected($settings['branding_preset'], 'sustainable-catalyst', false) . '>' . esc_html__('Sustainable Catalyst: red, black, white, cream', 'sustainable-catalyst-feature-suggestions') . '</option><option value="inherit" ' . selected($settings['branding_preset'], 'inherit', false) . '>' . esc_html__('Inherit active site theme', 'sustainable-catalyst-feature-suggestions') . '</option><option value="custom" ' . selected($settings['branding_preset'], 'custom', false) . '>' . esc_html__('Custom tokens below', 'sustainable-catalyst-feature-suggestions') . '</option></select></td></tr>';
        $colors = array('brand_accent' => __('Accent', 'sustainable-catalyst-feature-suggestions'), 'brand_accent_contrast' => __('Accent text', 'sustainable-catalyst-feature-suggestions'), 'brand_ink' => __('Text', 'sustainable-catalyst-feature-suggestions'), 'brand_muted' => __('Muted text', 'sustainable-catalyst-feature-suggestions'), 'brand_surface' => __('Surface', 'sustainable-catalyst-feature-suggestions'), 'brand_soft' => __('Soft background', 'sustainable-catalyst-feature-suggestions'), 'brand_line' => __('Borders', 'sustainable-catalyst-feature-suggestions'), 'brand_success' => __('Success', 'sustainable-catalyst-feature-suggestions'), 'brand_warning' => __('Warning', 'sustainable-catalyst-feature-suggestions'), 'brand_danger' => __('Danger', 'sustainable-catalyst-feature-suggestions'));
        echo '<tr><th>' . esc_html__('Custom color tokens', 'sustainable-catalyst-feature-suggestions') . '</th><td><div class="scfs-brand-token-grid">';
        foreach ($colors as $key => $label) {
            echo '<label><span>' . esc_html($label) . '</span><input type="color" name="' . esc_attr($key) . '" value="' . esc_attr($settings[$key]) . '"><code>' . esc_html($settings[$key]) . '</code></label>';
        }
        echo '</div></td></tr>';
        echo '<tr><th>' . esc_html__('Typography', 'sustainable-catalyst-feature-suggestions') . '</th><td><p><label>' . esc_html__('Body font stack', 'sustainable-catalyst-feature-suggestions') . '<br><input class="regular-text" name="brand_font_family" value="' . esc_attr($settings['brand_font_family']) . '" placeholder="inherit"></label></p><p><label>' . esc_html__('Heading font stack', 'sustainable-catalyst-feature-suggestions') . '<br><input class="regular-text" name="brand_heading_font_family" value="' . esc_attr($settings['brand_heading_font_family']) . '" placeholder="inherit"></label></p></td></tr>';
        echo '<tr><th>' . esc_html__('Shape and spacing', 'sustainable-catalyst-feature-suggestions') . '</th><td><label>' . esc_html__('Corner radius', 'sustainable-catalyst-feature-suggestions') . ' <input type="number" min="0" max="24" name="brand_radius" value="' . esc_attr($settings['brand_radius']) . '"> px</label><br><label>' . esc_html__('Maximum width', 'sustainable-catalyst-feature-suggestions') . ' <input type="number" min="680" max="1800" name="brand_max_width" value="' . esc_attr($settings['brand_max_width']) . '"> px</label><br><label>' . esc_html__('Shadow', 'sustainable-catalyst-feature-suggestions') . ' <select name="brand_shadow"><option value="none" ' . selected($settings['brand_shadow'], 'none', false) . '>' . esc_html__('None', 'sustainable-catalyst-feature-suggestions') . '</option><option value="subtle" ' . selected($settings['brand_shadow'], 'subtle', false) . '>' . esc_html__('Subtle', 'sustainable-catalyst-feature-suggestions') . '</option><option value="raised" ' . selected($settings['brand_shadow'], 'raised', false) . '>' . esc_html__('Raised', 'sustainable-catalyst-feature-suggestions') . '</option></select></label><br><label>' . esc_html__('Navigation columns', 'sustainable-catalyst-feature-suggestions') . ' <input type="number" min="1" max="4" name="nav_columns" value="' . esc_attr($settings['nav_columns']) . '"></label></td></tr>';
        echo '<tr><th>' . esc_html__('Embedded-mode defaults', 'sustainable-catalyst-feature-suggestions') . '</th><td><label><input type="checkbox" name="embedded_hide_header" value="1" ' . checked($settings['embedded_hide_header'], '1', false) . '> ' . esc_html__('Hide duplicate application header', 'sustainable-catalyst-feature-suggestions') . '</label><br><label><input type="checkbox" name="embedded_hide_status" value="1" ' . checked($settings['embedded_hide_status'], '1', false) . '> ' . esc_html__('Hide status counters', 'sustainable-catalyst-feature-suggestions') . '</label><br><label><input type="checkbox" name="embedded_hide_nav_descriptions" value="1" ' . checked($settings['embedded_hide_nav_descriptions'], '1', false) . '> ' . esc_html__('Use compact navigation labels', 'sustainable-catalyst-feature-suggestions') . '</label><br><label><input type="checkbox" name="embedded_hide_overview_pathways" value="1" ' . checked($settings['embedded_hide_overview_pathways'], '1', false) . '> ' . esc_html__('Suppress duplicate overview pathway cards', 'sustainable-catalyst-feature-suggestions') . '</label><br><label><input type="checkbox" name="embedded_use_page_width" value="1" ' . checked($settings['embedded_use_page_width'], '1', false) . '> ' . esc_html__('Use the containing page width', 'sustainable-catalyst-feature-suggestions') . '</label><br><label><input type="checkbox" name="hide_zero_status" value="1" ' . checked($settings['hide_zero_status'], '1', false) . '> ' . esc_html__('Hide empty status metrics', 'sustainable-catalyst-feature-suggestions') . '</label></td></tr></table>';
        echo '<div class="scfs-brand-preview" style="--preview-accent:' . esc_attr($settings['brand_accent']) . ';--preview-ink:' . esc_attr($settings['brand_ink']) . ';--preview-surface:' . esc_attr($settings['brand_surface']) . ';--preview-soft:' . esc_attr($settings['brand_soft']) . ';--preview-line:' . esc_attr($settings['brand_line']) . '"><p class="scfs-brand-preview__eyebrow">' . esc_html__('Brand preview', 'sustainable-catalyst-feature-suggestions') . '</p><h3>' . esc_html__('Embedded Support Center', 'sustainable-catalyst-feature-suggestions') . '</h3><p>' . esc_html__('Scoped design tokens prevent broad page styles from turning every application control into the same button or card.', 'sustainable-catalyst-feature-suggestions') . '</p><span>' . esc_html__('Active navigation', 'sustainable-catalyst-feature-suggestions') . '</span></div>';
        echo '<p><button class="button button-primary">' . esc_html__('Save support platform and branding settings', 'sustainable-catalyst-feature-suggestions') . '</button></p></form>';
        echo '<h2>' . esc_html__('Integration boundary', 'sustainable-catalyst-feature-suggestions') . '</h2><pre>' . esc_html(wp_json_encode($this->handoff_schema(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) . '</pre></div>';
    }

    public function save_settings() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to update platform settings.', 'sustainable-catalyst-feature-suggestions'));
        }
        check_admin_referer('scfs_save_product_support_settings', 'scfs_product_support_nonce');
        $allowed_views = array_keys($this->navigation_items($this->settings()));
        $default_view = sanitize_key($_POST['default_view'] ?? 'overview');
        $embedded_default_view = sanitize_key($_POST['embedded_default_view'] ?? 'resolve');
        if (!in_array($default_view, $allowed_views, true)) {
            $default_view = 'overview';
        }
        if (!in_array($embedded_default_view, $allowed_views, true)) {
            $embedded_default_view = 'resolve';
        }
        $defaults = $this->default_settings();
        update_option(self::OPTION_KEY, array(
            'support_center_title' => sanitize_text_field(wp_unslash($_POST['support_center_title'] ?? '')),
            'support_center_intro' => sanitize_textarea_field(wp_unslash($_POST['support_center_intro'] ?? '')),
            'default_view' => $default_view,
            'default_mode' => $this->choice($_POST['default_mode'] ?? 'standalone', array('standalone', 'embedded'), 'standalone'),
            'embedded_default_view' => $embedded_default_view,
            'contact_engagement_url' => esc_url_raw(wp_unslash($_POST['contact_engagement_url'] ?? '')),
            'show_surveys' => isset($_POST['show_surveys']) ? '1' : '',
            'show_public_ideas' => isset($_POST['show_public_ideas']) ? '1' : '',
            'show_release_intelligence' => isset($_POST['show_release_intelligence']) ? '1' : '',
            'featured_release_limit' => min(12, max(1, absint($_POST['featured_release_limit'] ?? 4))),
            'branding_preset' => $this->choice($_POST['branding_preset'] ?? 'platform', array('platform', 'sustainable-catalyst', 'inherit', 'custom'), 'platform'),
            'brand_accent' => $this->color($_POST['brand_accent'] ?? '', $defaults['brand_accent']),
            'brand_accent_contrast' => $this->color($_POST['brand_accent_contrast'] ?? '', $defaults['brand_accent_contrast']),
            'brand_ink' => $this->color($_POST['brand_ink'] ?? '', $defaults['brand_ink']),
            'brand_muted' => $this->color($_POST['brand_muted'] ?? '', $defaults['brand_muted']),
            'brand_surface' => $this->color($_POST['brand_surface'] ?? '', $defaults['brand_surface']),
            'brand_soft' => $this->color($_POST['brand_soft'] ?? '', $defaults['brand_soft']),
            'brand_line' => $this->color($_POST['brand_line'] ?? '', $defaults['brand_line']),
            'brand_success' => $this->color($_POST['brand_success'] ?? '', $defaults['brand_success']),
            'brand_warning' => $this->color($_POST['brand_warning'] ?? '', $defaults['brand_warning']),
            'brand_danger' => $this->color($_POST['brand_danger'] ?? '', $defaults['brand_danger']),
            'brand_radius' => (string) min(24, max(0, absint($_POST['brand_radius'] ?? 0))),
            'brand_shadow' => $this->choice($_POST['brand_shadow'] ?? 'subtle', array('none', 'subtle', 'raised'), 'subtle'),
            'brand_max_width' => (string) min(1800, max(680, absint($_POST['brand_max_width'] ?? 1180))),
            'brand_font_family' => $this->font_stack(wp_unslash($_POST['brand_font_family'] ?? 'inherit'), 'inherit'),
            'brand_heading_font_family' => $this->font_stack(wp_unslash($_POST['brand_heading_font_family'] ?? 'inherit'), 'inherit'),
            'embedded_hide_header' => isset($_POST['embedded_hide_header']) ? '1' : '',
            'embedded_hide_status' => isset($_POST['embedded_hide_status']) ? '1' : '',
            'embedded_hide_nav_descriptions' => isset($_POST['embedded_hide_nav_descriptions']) ? '1' : '',
            'embedded_hide_overview_pathways' => isset($_POST['embedded_hide_overview_pathways']) ? '1' : '',
            'embedded_use_page_width' => isset($_POST['embedded_use_page_width']) ? '1' : '',
            'hide_zero_status' => isset($_POST['hide_zero_status']) ? '1' : '',
            'nav_columns' => (string) min(4, max(1, absint($_POST['nav_columns'] ?? 3))),
        ), false);
        wp_safe_redirect(admin_url('edit.php?post_type=' . Sustainable_Catalyst_Feature_Suggestions::POST_TYPE . '&page=scfs-product-support-platform&updated=1'));
        exit;
    }

    public function snapshot() {
        return array(
            'ok' => true,
            'schema' => 'scfs-product-support-snapshot/' . self::SCHEMA_VERSION,
            'version' => self::VERSION,
            'generated_at' => gmdate('c'),
            'platform' => $this->schema_record(),
            'overview' => $this->overview_record(''),
            'product_taxonomy' => SCFS_Product_Integration::instance()->taxonomy_schema(),
            'knowledge_base' => SCFS_Knowledge_Base_Foundation::instance()->schema_record(),
            'guided_resolution' => SCFS_Guided_Resolution::instance()->schema_record(),
            'documentation_intelligence' => SCFS_Documentation_Feature_Intelligence::instance()->schema_record(),
            'contact_and_engagement_handoff' => $this->handoff_schema(),
            'human_review_required' => true,
        );
    }

    public function register_rest_routes() {
        $namespace = Sustainable_Catalyst_Feature_Suggestions::REST_NAMESPACE;
        register_rest_route($namespace, '/product-support/schema', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'rest_schema'),
            'permission_callback' => '__return_true',
        ));
        register_rest_route($namespace, '/product-support/overview', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'rest_overview'),
            'permission_callback' => '__return_true',
        ));
        register_rest_route($namespace, '/product-support/releases', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'rest_releases'),
            'permission_callback' => '__return_true',
        ));
        register_rest_route($namespace, '/product-support/products', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'rest_products'),
            'permission_callback' => '__return_true',
        ));
        register_rest_route($namespace, '/product-support/view', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'rest_view'),
            'permission_callback' => '__return_true',
            'args' => array(
                'view' => array('sanitize_callback' => 'sanitize_key'),
                'product' => array('sanitize_callback' => 'sanitize_title'),
                'survey' => array('sanitize_callback' => 'absint'),
                'base_url' => array('sanitize_callback' => 'esc_url_raw'),
                'anchor' => array('sanitize_callback' => 'sanitize_title'),
            ),
        ));
        register_rest_route($namespace, '/product-support/handoff-schema', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'rest_handoff_schema'),
            'permission_callback' => '__return_true',
        ));
        register_rest_route($namespace, '/product-support/snapshot', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'rest_snapshot'),
            'permission_callback' => function () { return current_user_can('edit_posts'); },
        ));
    }

    public function rest_schema() {
        return rest_ensure_response($this->schema_record());
    }

    public function rest_overview($request) {
        return rest_ensure_response($this->overview_record(sanitize_title($request->get_param('product'))));
    }

    public function rest_releases($request) {
        $product = sanitize_title($request->get_param('product'));
        $status = sanitize_key($request->get_param('status'));
        $limit = min(100, max(1, absint($request->get_param('limit') ?: 20)));
        $query = $this->release_query($product, $limit, $status);
        return rest_ensure_response(array(
            'schema' => 'scfs-release-intelligence-list/' . self::SCHEMA_VERSION,
            'version' => self::VERSION,
            'items' => array_values(array_map(function ($post) { return $this->release_record($post, false); }, $query->posts)),
            'public_records_only' => true,
        ));
    }

    public function rest_products() {
        $terms = get_terms(array('taxonomy' => SCFS_Product_Integration::PRODUCT_TAXONOMY, 'hide_empty' => false, 'orderby' => 'name'));
        if (is_wp_error($terms)) {
            $terms = array();
        }
        $items = array();
        foreach ($terms as $term) {
            $items[] = array(
                'id' => (int) $term->term_id,
                'slug' => $term->slug,
                'name' => $term->name,
                'overview' => $this->overview_record($term->slug),
            );
        }
        return rest_ensure_response(array('schema' => 'scfs-product-support-products/' . self::SCHEMA_VERSION, 'items' => $items));
    }

    public function rest_view($request) {
        $settings = $this->settings();
        $view = $this->choice($request->get_param('view'), $this->allowed_views($settings), 'overview');
        $product = sanitize_title($request->get_param('product'));
        $survey = absint($request->get_param('survey'));
        $base_url = $this->support_base_url($request->get_param('base_url'));
        $anchor = $this->normalized_anchor($request->get_param('anchor'));
        $show_pathways = $this->bool_value($request->get_param('show_overview_pathways'), $this->active_show_overview_pathways);
        $context = array('view' => $view, 'product' => $product, 'survey' => $survey);
        $display = array('show_overview_pathways' => $show_pathways);
        $previous_base_url = $this->active_base_url;
        $previous_anchor = $this->active_anchor;
        $previous_pathways = $this->active_show_overview_pathways;
        $this->active_base_url = $base_url;
        $this->active_anchor = $anchor;
        $this->active_show_overview_pathways = $show_pathways;
        ob_start();
        $this->render_view($context, $this->overview_record($product), $settings, $display);
        $html = ob_get_clean();
        $url = $this->view_url($view, $product, $survey ? array('scfs_support_survey' => $survey) : array());
        $this->active_base_url = $previous_base_url;
        $this->active_anchor = $previous_anchor;
        $this->active_show_overview_pathways = $previous_pathways;
        return rest_ensure_response(array(
            'schema' => 'scfs-product-support-view/' . self::SCHEMA_VERSION,
            'version' => self::VERSION,
            'view' => $view,
            'product' => $product,
            'survey' => $survey,
            'url' => $url,
            'html' => $html,
            'private_case_content_exposed' => false,
        ));
    }

    public function rest_handoff_schema() {
        return rest_ensure_response($this->handoff_schema());
    }

    public function rest_snapshot() {
        return rest_ensure_response($this->snapshot());
    }
}
