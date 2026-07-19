<?php
/**
 * Search and Guided Resolution: deterministic product-aware support search,
 * error matching, known-issue prioritization, privacy-minimized analytics,
 * and a reviewable Contact and Engagement handoff token.
 *
 * @package Sustainable_Catalyst_Feature_Suggestions
 */

if (!defined('ABSPATH')) {
    exit;
}

final class SCFS_Guided_Resolution {
    const VERSION = '5.3.0';
    const SCHEMA_VERSION = '1.0';
    const SHORTCODE = 'scfs_guided_resolution';
    const LEGACY_SHORTCODE = 'sustainable_catalyst_guided_resolution';
    const OPTION_KEY = 'scfs_guided_resolution_settings';
    const DB_VERSION_OPTION = 'scfs_guided_resolution_db_version';
    const TABLE_SUFFIX = 'scfs_resolution_events';
    const NONCE_ACTION = 'scfs_guided_resolution_handoff';
    const SETTINGS_NONCE_ACTION = 'scfs_save_guided_resolution_settings';
    const HANDOFF_TTL = 1800;

    private static $instance = null;

    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('init', array($this, 'register_meta'), 10);
        add_action('init', array($this, 'register_shortcodes'), 31);
        add_action('wp_enqueue_scripts', array($this, 'register_assets'));
        add_action('admin_enqueue_scripts', array($this, 'admin_assets'));
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post_' . SCFS_Knowledge_Base_Foundation::ARTICLE_POST_TYPE, array($this, 'save_search_guidance'), 35, 2);
        add_action('save_post_' . SCFS_Knowledge_Base_Foundation::ISSUE_POST_TYPE, array($this, 'save_search_guidance'), 35, 2);
        add_action('admin_menu', array($this, 'register_admin_page'), 28);
        add_action('admin_post_scfs_save_guided_resolution_settings', array($this, 'save_settings'));
        add_action('admin_post_scfs_export_resolution_analytics', array($this, 'export_analytics'));
        add_action('admin_post_scfs_guided_resolution_handoff', array($this, 'handle_handoff'));
        add_action('admin_post_nopriv_scfs_guided_resolution_handoff', array($this, 'handle_handoff'));
        add_action('admin_post_scfs_resolution_view', array($this, 'handle_result_view'));
        add_action('admin_post_nopriv_scfs_resolution_view', array($this, 'handle_result_view'));
        add_action('rest_api_init', array($this, 'register_rest_routes'));
        add_action('admin_init', array($this, 'maybe_upgrade_table'));
    }

    public static function activate() {
        $instance = self::instance();
        $instance->register_meta();
        $instance->create_analytics_table();
        if (!get_option(self::OPTION_KEY)) {
            add_option(self::OPTION_KEY, $instance->default_settings(), '', false);
        }
        update_option(self::DB_VERSION_OPTION, self::SCHEMA_VERSION, false);
    }

    public function maybe_upgrade_table() {
        if ((string) get_option(self::DB_VERSION_OPTION, '') !== self::SCHEMA_VERSION) {
            $this->create_analytics_table();
            update_option(self::DB_VERSION_OPTION, self::SCHEMA_VERSION, false);
        }
    }

    public function table_name() {
        global $wpdb;
        return isset($wpdb->prefix) ? $wpdb->prefix . self::TABLE_SUFFIX : self::TABLE_SUFFIX;
    }

    public function create_analytics_table() {
        global $wpdb;
        if (!isset($wpdb->prefix)) {
            return;
        }
        $charset = method_exists($wpdb, 'get_charset_collate') ? $wpdb->get_charset_collate() : '';
        $table = $this->table_name();
        $sql = "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            event_type varchar(24) NOT NULL DEFAULT 'search',
            query_text varchar(500) NOT NULL DEFAULT '',
            query_hash char(64) NOT NULL DEFAULT '',
            error_hash char(64) NOT NULL DEFAULT '',
            filters_json longtext NULL,
            result_count int(11) unsigned NOT NULL DEFAULT 0,
            top_score decimal(8,2) NOT NULL DEFAULT 0,
            confidence decimal(5,4) NOT NULL DEFAULT 0,
            resolution_state varchar(32) NOT NULL DEFAULT 'no_match',
            viewed_json longtext NULL,
            source varchar(32) NOT NULL DEFAULT 'shortcode',
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY event_type (event_type),
            KEY query_hash (query_hash),
            KEY resolution_state (resolution_state),
            KEY created_at (created_at)
        ) {$charset};";
        if (defined('ABSPATH') && file_exists(ABSPATH . 'wp-admin/includes/upgrade.php')) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }
        if (function_exists('dbDelta')) {
            dbDelta($sql);
        } elseif (method_exists($wpdb, 'query')) {
            // Used only as a compatibility fallback outside normal WordPress activation.
            $wpdb->query($sql); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        }
    }

    private function default_settings() {
        return array(
            'private_support_url' => home_url('/contact/'),
            'minimum_confidence' => '0.42',
            'high_confidence' => '0.72',
            'max_results_per_group' => '6',
            'synonyms' => implode("\n", array(
                'endpoint unavailable = backend unavailable, cannot connect, connection refused, service unavailable',
                'fatal error = runtime exception, critical error, white screen, crash',
                'not indexing = missing records, sync incomplete, index count wrong',
                'shortcode visible = shortcode not rendering, raw shortcode, shortcode text',
                'mobile layout = responsive, phone layout, small screen',
            )),
            'promoted_results' => '',
            'analytics_retention_days' => '365',
        );
    }

    private function settings() {
        $saved = get_option(self::OPTION_KEY, array());
        return wp_parse_args(is_array($saved) ? $saved : array(), $this->default_settings());
    }

    public function register_meta() {
        $types = array(
            SCFS_Knowledge_Base_Foundation::ARTICLE_POST_TYPE,
            SCFS_Knowledge_Base_Foundation::ISSUE_POST_TYPE,
        );
        foreach ($types as $post_type) {
            register_post_meta($post_type, '_scfs_search_aliases', array(
                'type' => 'string', 'single' => true, 'show_in_rest' => true,
                'sanitize_callback' => 'sanitize_textarea_field',
                'auth_callback' => function () { return current_user_can('edit_posts'); },
            ));
            register_post_meta($post_type, '_scfs_error_signatures', array(
                'type' => 'string', 'single' => true, 'show_in_rest' => true,
                'sanitize_callback' => 'sanitize_textarea_field',
                'auth_callback' => function () { return current_user_can('edit_posts'); },
            ));
            register_post_meta($post_type, '_scfs_resolution_promoted', array(
                'type' => 'boolean', 'single' => true, 'show_in_rest' => true,
                'sanitize_callback' => array($this, 'sanitize_boolean'),
                'auth_callback' => function () { return current_user_can('edit_posts'); },
            ));
            register_post_meta($post_type, '_scfs_resolution_priority', array(
                'type' => 'integer', 'single' => true, 'show_in_rest' => true,
                'sanitize_callback' => array($this, 'sanitize_priority'),
                'auth_callback' => function () { return current_user_can('edit_posts'); },
            ));
        }
    }

    public function sanitize_boolean($value) {
        return !empty($value);
    }

    public function sanitize_priority($value) {
        return max(0, min(100, absint($value)));
    }

    public function register_shortcodes() {
        add_shortcode(self::SHORTCODE, array($this, 'render_shortcode'));
        add_shortcode(self::LEGACY_SHORTCODE, array($this, 'render_shortcode'));
    }

    public function register_assets() {
        wp_register_style(
            'scfs-guided-resolution',
            plugins_url('../assets/guided-resolution.css', __FILE__),
            array('scfs-knowledge-base'),
            self::VERSION
        );
    }

    public function admin_assets($hook) {
        if (strpos((string) $hook, 'scfs-guided-resolution') !== false) {
            wp_enqueue_style('scfs-admin');
        }
    }

    public function add_meta_boxes() {
        foreach (array(SCFS_Knowledge_Base_Foundation::ARTICLE_POST_TYPE, SCFS_Knowledge_Base_Foundation::ISSUE_POST_TYPE) as $post_type) {
            add_meta_box(
                'scfs-resolution-search-guidance',
                __('Search and Guided Resolution', 'sustainable-catalyst-feature-suggestions'),
                array($this, 'search_guidance_meta_box'),
                $post_type,
                'side',
                'default'
            );
        }
    }

    public function search_guidance_meta_box($post) {
        wp_nonce_field('scfs_save_search_guidance', 'scfs_search_guidance_nonce');
        $aliases = get_post_meta($post->ID, '_scfs_search_aliases', true);
        $errors = get_post_meta($post->ID, '_scfs_error_signatures', true);
        $promoted = get_post_meta($post->ID, '_scfs_resolution_promoted', true) === '1';
        $priority = absint(get_post_meta($post->ID, '_scfs_resolution_priority', true));
        echo '<p><label><strong>' . esc_html__('Search aliases', 'sustainable-catalyst-feature-suggestions') . '</strong><br><textarea class="widefat" rows="4" name="scfs_search_aliases" placeholder="One phrase per line">' . esc_textarea($aliases) . '</textarea></label></p>';
        echo '<p><label><strong>' . esc_html__('Error signatures', 'sustainable-catalyst-feature-suggestions') . '</strong><br><textarea class="widefat" rows="5" name="scfs_error_signatures" placeholder="Paste stable, non-secret error fragments only">' . esc_textarea($errors) . '</textarea></label></p>';
        echo '<p><label><input type="checkbox" name="scfs_resolution_promoted" value="1" ' . checked($promoted, true, false) . '> ' . esc_html__('Promote in relevant results', 'sustainable-catalyst-feature-suggestions') . '</label></p>';
        echo '<p><label><strong>' . esc_html__('Editorial priority', 'sustainable-catalyst-feature-suggestions') . '</strong><br><input type="number" min="0" max="100" name="scfs_resolution_priority" value="' . esc_attr((string) $priority) . '"></label></p>';
        echo '<p class="description">' . esc_html__('Do not enter API keys, passwords, personal data, or complete private logs.', 'sustainable-catalyst-feature-suggestions') . '</p>';
    }

    public function save_search_guidance($post_id, $post) {
        if (!isset($_POST['scfs_search_guidance_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['scfs_search_guidance_nonce'])), 'scfs_save_search_guidance')) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (!$post || !current_user_can('edit_post', $post_id)) {
            return;
        }
        update_post_meta($post_id, '_scfs_search_aliases', isset($_POST['scfs_search_aliases']) ? sanitize_textarea_field(wp_unslash($_POST['scfs_search_aliases'])) : '');
        update_post_meta($post_id, '_scfs_error_signatures', isset($_POST['scfs_error_signatures']) ? sanitize_textarea_field(wp_unslash($_POST['scfs_error_signatures'])) : '');
        update_post_meta($post_id, '_scfs_resolution_promoted', !empty($_POST['scfs_resolution_promoted']) ? '1' : '0');
        update_post_meta($post_id, '_scfs_resolution_priority', isset($_POST['scfs_resolution_priority']) ? $this->sanitize_priority($_POST['scfs_resolution_priority']) : 0);
    }

    public function schema_record() {
        return array(
            'schema' => 'scfs-guided-resolution/' . self::SCHEMA_VERSION,
            'version' => self::VERSION,
            'workflow' => array('describe', 'narrow_context', 'review_known_issues', 'review_articles', 'private_support_handoff'),
            'filters' => array('product', 'product_version', 'component'),
            'result_groups' => array('known_issues', 'support_articles', 'releases', 'public_suggestions'),
            'ranking_signals' => array('exact_phrase', 'token_overlap', 'error_signature', 'taxonomy_context', 'known_issue_status', 'severity', 'editorial_promotion'),
            'shortcodes' => array(self::SHORTCODE, self::LEGACY_SHORTCODE),
            'privacy' => array(
                'search_analytics_redacted' => true,
                'ip_address_stored' => false,
                'private_case_stored_in_feature_suggestions' => false,
                'handoff_context_requires_consent' => true,
                'automatic_case_creation' => false,
            ),
        );
    }

    private function request_context($source = 'shortcode') {
        return array(
            'query' => isset($_REQUEST['scfs_resolution_query']) ? sanitize_text_field(wp_unslash($_REQUEST['scfs_resolution_query'])) : '',
            'error_message' => isset($_REQUEST['scfs_resolution_error']) ? sanitize_textarea_field(wp_unslash($_REQUEST['scfs_resolution_error'])) : '',
            'product' => isset($_REQUEST['scfs_resolution_product']) ? sanitize_title(wp_unslash($_REQUEST['scfs_resolution_product'])) : '',
            'product_version' => isset($_REQUEST['scfs_resolution_version']) ? sanitize_title(wp_unslash($_REQUEST['scfs_resolution_version'])) : '',
            'component' => isset($_REQUEST['scfs_resolution_component']) ? sanitize_title(wp_unslash($_REQUEST['scfs_resolution_component'])) : '',
            'source' => $source,
        );
    }

    public function render_shortcode($atts = array()) {
        $atts = shortcode_atts(array(
            'title' => __('Guided Support Resolution', 'sustainable-catalyst-feature-suggestions'),
            'intro' => __('Describe what is happening. The system will prioritize current known issues, then documentation, releases, and related public ideas.', 'sustainable-catalyst-feature-suggestions'),
            'compact' => '0',
            'show_handoff' => '1',
        ), $atts, self::SHORTCODE);
        wp_enqueue_style('scfs-guided-resolution');
        $context = $this->request_context('shortcode');
        $has_query = $context['query'] !== '' || $context['error_message'] !== '';
        $resolution = $has_query ? $this->search($context, true) : null;

        ob_start();
        echo '<section class="scfs-resolution' . ($atts['compact'] === '1' ? ' scfs-resolution-compact' : '') . '" aria-labelledby="scfs-resolution-title">';
        echo '<header class="scfs-resolution-hero"><p class="scfs-resolution-eyebrow">' . esc_html__('Product Support and Feedback Platform', 'sustainable-catalyst-feature-suggestions') . '</p><h2 id="scfs-resolution-title">' . esc_html($atts['title']) . '</h2><p>' . esc_html($atts['intro']) . '</p></header>';
        $this->render_search_form($context);
        if ($resolution) {
            $this->render_resolution($resolution, $atts['show_handoff'] === '1');
        } else {
            echo '<div class="scfs-resolution-start"><h3>' . esc_html__('Start with the symptom, task, or exact error fragment', 'sustainable-catalyst-feature-suggestions') . '</h3><p>' . esc_html__('Product context improves relevance, but you can search without knowing the version or component.', 'sustainable-catalyst-feature-suggestions') . '</p></div>';
        }
        echo '</section>';
        return ob_get_clean();
    }

    private function render_search_form($context) {
        echo '<form class="scfs-resolution-form" method="get" role="search">';
        echo '<label class="scfs-resolution-query"><span>' . esc_html__('What are you trying to do or fix?', 'sustainable-catalyst-feature-suggestions') . '</span><input type="search" name="scfs_resolution_query" value="' . esc_attr($context['query']) . '" placeholder="Example: Research Librarian endpoint unavailable"></label>';
        echo '<label><span>' . esc_html__('Error message or visible symptom', 'sustainable-catalyst-feature-suggestions') . '</span><textarea name="scfs_resolution_error" rows="3" placeholder="Paste a short, non-sensitive error fragment. Remove keys, passwords, personal data, and private logs.">' . esc_textarea($context['error_message']) . '</textarea></label>';
        echo '<div class="scfs-resolution-filter-grid">';
        $this->render_term_select('scfs_resolution_product', SCFS_Product_Integration::PRODUCT_TAXONOMY, __('All products', 'sustainable-catalyst-feature-suggestions'), $context['product']);
        $this->render_term_select('scfs_resolution_version', SCFS_Product_Integration::VERSION_TAXONOMY, __('All versions', 'sustainable-catalyst-feature-suggestions'), $context['product_version']);
        $this->render_term_select('scfs_resolution_component', SCFS_Product_Integration::COMPONENT_TAXONOMY, __('All components', 'sustainable-catalyst-feature-suggestions'), $context['component']);
        echo '</div><div class="scfs-resolution-actions"><button type="submit">' . esc_html__('Find a resolution', 'sustainable-catalyst-feature-suggestions') . '</button><a href="' . esc_url(remove_query_arg(array('scfs_resolution_query', 'scfs_resolution_error', 'scfs_resolution_product', 'scfs_resolution_version', 'scfs_resolution_component'))) . '">' . esc_html__('Start over', 'sustainable-catalyst-feature-suggestions') . '</a></div></form>';
    }

    private function render_term_select($name, $taxonomy, $placeholder, $selected) {
        $terms = get_terms(array('taxonomy' => $taxonomy, 'hide_empty' => false, 'orderby' => 'name', 'order' => 'ASC'));
        if (is_wp_error($terms)) {
            $terms = array();
        }
        echo '<label><span>' . esc_html($placeholder) . '</span><select name="' . esc_attr($name) . '"><option value="">' . esc_html($placeholder) . '</option>';
        foreach ($terms as $term) {
            echo '<option value="' . esc_attr($term->slug) . '" ' . selected($selected, $term->slug, false) . '>' . esc_html($term->name) . '</option>';
        }
        echo '</select></label>';
    }

    public function search($context, $track = true) {
        $context = wp_parse_args((array) $context, array(
            'query' => '', 'error_message' => '', 'product' => '', 'product_version' => '', 'component' => '', 'source' => 'rest',
        ));
        $context['query'] = sanitize_text_field($context['query']);
        $context['error_message'] = sanitize_textarea_field($context['error_message']);
        $context['product'] = sanitize_title($context['product']);
        $context['product_version'] = sanitize_title($context['product_version']);
        $context['component'] = sanitize_title($context['component']);
        $expanded = $this->expanded_query($context['query'] . ' ' . $context['error_message']);
        $tokens = $this->tokens($expanded);
        $filters = array(
            SCFS_Product_Integration::PRODUCT_TAXONOMY => $context['product'],
            SCFS_Product_Integration::VERSION_TAXONOMY => $context['product_version'],
            SCFS_Product_Integration::COMPONENT_TAXONOMY => $context['component'],
        );
        $groups = array(
            'known_issues' => $this->rank_posts(SCFS_Knowledge_Base_Foundation::ISSUE_POST_TYPE, $context, $tokens, $filters),
            'support_articles' => $this->rank_posts(SCFS_Knowledge_Base_Foundation::ARTICLE_POST_TYPE, $context, $tokens, $filters),
            'releases' => $this->rank_releases($context, $tokens),
            'public_suggestions' => $this->rank_public_suggestions($context, $tokens, $filters),
        );
        $settings = $this->settings();
        $limit = max(1, min(20, absint($settings['max_results_per_group'])));
        foreach ($groups as $key => $items) {
            usort($items, array($this, 'sort_results'));
            $groups[$key] = array_slice($items, 0, $limit);
        }
        $all = array_merge($groups['known_issues'], $groups['support_articles'], $groups['releases'], $groups['public_suggestions']);
        usort($all, array($this, 'sort_results'));
        $top_score = $all ? (float) $all[0]['score'] : 0.0;
        $confidence = min(0.99, round($top_score / 140, 4));
        $minimum = (float) $settings['minimum_confidence'];
        $high = (float) $settings['high_confidence'];
        $state = !$all ? 'no_match' : ($confidence >= $high ? 'strong_match' : ($confidence >= $minimum ? 'possible_match' : 'low_confidence'));
        $result = array(
            'schema' => 'scfs-guided-resolution-result/' . self::SCHEMA_VERSION,
            'query' => $context['query'],
            'product_context' => array('product' => $context['product'], 'product_version' => $context['product_version'], 'component' => $context['component']),
            'groups' => $groups,
            'result_count' => count($all),
            'top_score' => $top_score,
            'confidence' => $confidence,
            'resolution_state' => $state,
            'human_review_required' => true,
            'search_id' => 0,
        );
        if ($track) {
            $result['search_id'] = $this->record_search($context, $result);
        }
        return $result;
    }

    public function sort_results($a, $b) {
        if ((float) $a['score'] === (float) $b['score']) {
            return strcmp((string) $a['title'], (string) $b['title']);
        }
        return ((float) $a['score'] > (float) $b['score']) ? -1 : 1;
    }

    private function rank_posts($post_type, $context, $tokens, $filters) {
        $args = array(
            'post_type' => $post_type,
            'post_status' => 'publish',
            'posts_per_page' => 100,
            'orderby' => 'modified',
            'order' => 'DESC',
            'no_found_rows' => true,
        );
        $tax_query = array('relation' => 'AND');
        foreach ($filters as $taxonomy => $slug) {
            if ($slug !== '') {
                $tax_query[] = array('taxonomy' => $taxonomy, 'field' => 'slug', 'terms' => $slug);
            }
        }
        if (count($tax_query) > 1) {
            $args['tax_query'] = $tax_query;
        }
        $query = new WP_Query($args);
        $results = array();
        foreach ((array) $query->posts as $post) {
            $result = $this->score_post($post, $context, $tokens);
            if ($result['score'] > 0) {
                $results[] = $result;
            }
        }
        return $results;
    }

    private function score_post($post, $context, $tokens) {
        $kind = $post->post_type === SCFS_Knowledge_Base_Foundation::ISSUE_POST_TYPE ? 'known_issue' : 'support_article';
        $title = (string) $post->post_title;
        $body = wp_strip_all_tags((string) $post->post_content . ' ' . (string) $post->post_excerpt);
        $aliases = (string) get_post_meta($post->ID, '_scfs_search_aliases', true);
        $signatures = (string) get_post_meta($post->ID, '_scfs_error_signatures', true);
        if ($kind === 'known_issue') {
            $body .= ' ' . get_post_meta($post->ID, '_scfs_issue_symptom', true) . ' ' . get_post_meta($post->ID, '_scfs_issue_workaround', true) . ' ' . get_post_meta($post->ID, '_scfs_issue_resolution', true);
        }
        $haystack = strtolower($title . ' ' . $body . ' ' . $aliases . ' ' . $signatures);
        $score = $this->lexical_score($tokens, strtolower($title), $haystack);
        $reasons = array();
        if ($context['query'] !== '' && strpos($haystack, strtolower($context['query'])) !== false) {
            $score += 28;
            $reasons[] = 'Exact query phrase';
        }
        if ($context['error_message'] !== '') {
            $error = strtolower(trim($context['error_message']));
            if ($error !== '' && (strpos(strtolower($signatures), $error) !== false || strpos($haystack, $error) !== false)) {
                $score += 46;
                $reasons[] = 'Error signature match';
            } else {
                $error_tokens = $this->tokens($error);
                $error_overlap = count(array_intersect($error_tokens, $this->tokens($signatures . ' ' . $body)));
                if ($error_overlap > 0) {
                    $score += min(28, $error_overlap * 7);
                    $reasons[] = 'Error-language overlap';
                }
            }
        }
        $score += $this->taxonomy_score($post->ID, $context, $reasons);
        if (get_post_meta($post->ID, '_scfs_resolution_promoted', true) === '1') {
            $score += 28;
            $reasons[] = 'Editorially promoted';
        }
        $priority = absint(get_post_meta($post->ID, '_scfs_resolution_priority', true));
        $score += min(20, $priority / 5);
        if ($kind === 'known_issue') {
            $status = get_post_meta($post->ID, '_scfs_issue_status', true) ?: 'investigating';
            $severity = get_post_meta($post->ID, '_scfs_issue_severity', true) ?: 'moderate';
            if (!in_array($status, array('resolved', 'closed'), true)) {
                $score += 24;
                $reasons[] = 'Current known issue';
            }
            $severity_scores = array('critical' => 22, 'high' => 16, 'moderate' => 9, 'low' => 3);
            $score += isset($severity_scores[$severity]) ? $severity_scores[$severity] : 0;
        }
        $score += $this->global_promotion_score($kind, $post->ID);
        $summary = get_post_meta($post->ID, '_scfs_kb_summary', true);
        if (!$summary && $kind === 'known_issue') {
            $summary = get_post_meta($post->ID, '_scfs_issue_symptom', true);
        }
        if (!$summary) {
            $summary = wp_trim_words($body, 34);
        }
        return array(
            'kind' => $kind,
            'id' => (int) $post->ID,
            'title' => $title,
            'url' => get_permalink($post),
            'summary' => $summary,
            'score' => round($score, 2),
            'match_reasons' => array_values(array_unique($reasons)),
            'product_context' => SCFS_Product_Integration::instance()->product_context($post->ID),
            'status' => $kind === 'known_issue' ? (get_post_meta($post->ID, '_scfs_issue_status', true) ?: 'investigating') : '',
            'severity' => $kind === 'known_issue' ? (get_post_meta($post->ID, '_scfs_issue_severity', true) ?: 'moderate') : '',
        );
    }

    private function lexical_score($tokens, $title, $haystack) {
        $score = 0;
        foreach ($tokens as $token) {
            if (strpos($title, $token) !== false) {
                $score += 11;
            } elseif (strpos($haystack, $token) !== false) {
                $score += 4;
            }
        }
        return min(70, $score);
    }

    private function taxonomy_score($post_id, $context, &$reasons) {
        $score = 0;
        $map = array(
            'product' => array(SCFS_Product_Integration::PRODUCT_TAXONOMY, 24, 'Product match'),
            'product_version' => array(SCFS_Product_Integration::VERSION_TAXONOMY, 18, 'Version match'),
            'component' => array(SCFS_Product_Integration::COMPONENT_TAXONOMY, 22, 'Component match'),
        );
        foreach ($map as $key => $definition) {
            if ($context[$key] !== '' && has_term($context[$key], $definition[0], $post_id)) {
                $score += $definition[1];
                $reasons[] = $definition[2];
            }
        }
        return $score;
    }

    private function rank_releases($context, $tokens) {
        $terms = get_terms(array('taxonomy' => SCFS_Product_Integration::RELEASE_TAXONOMY, 'hide_empty' => false, 'orderby' => 'name', 'order' => 'DESC'));
        if (is_wp_error($terms)) {
            return array();
        }
        $results = array();
        foreach ($terms as $term) {
            $haystack = strtolower($term->name . ' ' . $term->slug . ' ' . $term->description);
            $score = $this->lexical_score($tokens, strtolower($term->name), $haystack);
            if ($context['product_version'] !== '' && ($term->slug === $context['product_version'] || strpos($haystack, str_replace('-', '.', $context['product_version'])) !== false)) {
                $score += 24;
            }
            $score += $this->global_promotion_score('release', $term->term_id, $term->slug);
            if ($score > 0) {
                $results[] = array(
                    'kind' => 'release', 'id' => (int) $term->term_id, 'title' => $term->name,
                    'url' => '', 'summary' => $term->description, 'score' => round($score, 2),
                    'match_reasons' => array('Release context'), 'product_context' => array(), 'status' => '', 'severity' => '',
                );
            }
        }
        return $results;
    }

    private function rank_public_suggestions($context, $tokens, $filters) {
        $args = array(
            'post_type' => Sustainable_Catalyst_Feature_Suggestions::POST_TYPE,
            'post_status' => array('publish', 'private', 'pending'),
            'posts_per_page' => 80,
            'no_found_rows' => true,
            'meta_query' => array(array('key' => '_scfs_public_visibility', 'value' => '1')),
        );
        $tax_query = array('relation' => 'AND');
        foreach ($filters as $taxonomy => $slug) {
            if ($slug !== '') {
                $tax_query[] = array('taxonomy' => $taxonomy, 'field' => 'slug', 'terms' => $slug);
            }
        }
        if (count($tax_query) > 1) {
            $args['tax_query'] = $tax_query;
        }
        $query = new WP_Query($args);
        $results = array();
        foreach ((array) $query->posts as $post) {
            $summary = get_post_meta($post->ID, '_scfs_public_summary', true);
            if (!$summary) {
                $summary = get_post_meta($post->ID, '_scfs_suggestion', true);
            }
            $haystack = strtolower($post->post_title . ' ' . $summary);
            $score = $this->lexical_score($tokens, strtolower($post->post_title), $haystack);
            $reasons = array();
            $score += $this->taxonomy_score($post->ID, $context, $reasons);
            $score += $this->global_promotion_score('suggestion', $post->ID);
            if ($score > 0) {
                $results[] = array(
                    'kind' => 'public_suggestion', 'id' => (int) $post->ID, 'title' => $post->post_title,
                    'url' => apply_filters('scfs_public_idea_url', home_url('/platform/feature-suggestions/?idea=' . absint($post->ID)), $post->ID),
                    'summary' => wp_trim_words(wp_strip_all_tags($summary), 34), 'score' => round($score, 2),
                    'match_reasons' => array_values(array_unique(array_merge(array('Related public idea'), $reasons))),
                    'product_context' => SCFS_Product_Integration::instance()->product_context($post->ID), 'status' => get_post_meta($post->ID, '_scfs_public_state', true), 'severity' => '',
                );
            }
        }
        return $results;
    }

    private function expanded_query($query) {
        $query = strtolower(trim((string) $query));
        foreach ($this->synonym_map() as $canonical => $aliases) {
            foreach ($aliases as $alias) {
                if ($alias !== '' && strpos($query, $alias) !== false) {
                    $query .= ' ' . $canonical . ' ' . implode(' ', $aliases);
                    break;
                }
            }
        }
        return $query;
    }

    private function synonym_map() {
        $map = array();
        $settings = $this->settings();
        foreach (preg_split('/\r\n|\r|\n/', (string) $settings['synonyms']) as $line) {
            if (strpos($line, '=') === false) {
                continue;
            }
            list($canonical, $aliases) = array_map('trim', explode('=', $line, 2));
            if ($canonical === '') {
                continue;
            }
            $values = array_filter(array_map('trim', explode(',', strtolower($aliases))));
            $values[] = strtolower($canonical);
            $map[strtolower($canonical)] = array_values(array_unique($values));
        }
        return $map;
    }

    private function tokens($text) {
        $text = strtolower(wp_strip_all_tags((string) $text));
        preg_match_all('/[a-z0-9][a-z0-9._-]{2,}/', $text, $matches);
        $stop = array('the', 'and', 'for', 'with', 'this', 'that', 'from', 'into', 'when', 'what', 'where', 'your', 'have', 'does', 'error', 'issue', 'please');
        return array_values(array_unique(array_diff($matches[0], $stop)));
    }

    private function global_promotion_score($kind, $id, $slug = '') {
        $settings = $this->settings();
        $needles = array($kind . ':' . (string) $id);
        if ($slug !== '') {
            $needles[] = $kind . ':' . $slug;
        }
        foreach (preg_split('/[\r\n,]+/', (string) $settings['promoted_results']) as $line) {
            if (in_array(trim(strtolower($line)), array_map('strtolower', $needles), true)) {
                return 34;
            }
        }
        return 0;
    }

    private function render_resolution($resolution, $show_handoff) {
        $labels = array(
            'strong_match' => __('Strong match', 'sustainable-catalyst-feature-suggestions'),
            'possible_match' => __('Possible match', 'sustainable-catalyst-feature-suggestions'),
            'low_confidence' => __('Low-confidence results', 'sustainable-catalyst-feature-suggestions'),
            'no_match' => __('No confident match', 'sustainable-catalyst-feature-suggestions'),
        );
        $state = $resolution['resolution_state'];
        echo '<div class="scfs-resolution-summary scfs-resolution-state-' . esc_attr($state) . '"><div><span>' . esc_html(isset($labels[$state]) ? $labels[$state] : $state) . '</span><strong>' . esc_html(number_format_i18n($resolution['confidence'] * 100, 0) . '%') . '</strong></div><p>' . esc_html(sprintf(_n('%d relevant record was found.', '%d relevant records were found.', $resolution['result_count'], 'sustainable-catalyst-feature-suggestions'), $resolution['result_count'])) . '</p></div>';
        $group_labels = array(
            'known_issues' => __('Known issues', 'sustainable-catalyst-feature-suggestions'),
            'support_articles' => __('Suggested support articles', 'sustainable-catalyst-feature-suggestions'),
            'releases' => __('Related releases', 'sustainable-catalyst-feature-suggestions'),
            'public_suggestions' => __('Related public suggestions', 'sustainable-catalyst-feature-suggestions'),
        );
        foreach ($group_labels as $key => $label) {
            if (empty($resolution['groups'][$key])) {
                continue;
            }
            echo '<section class="scfs-resolution-group"><div class="scfs-resolution-group-head"><h3>' . esc_html($label) . '</h3><span>' . esc_html((string) count($resolution['groups'][$key])) . '</span></div><div class="scfs-resolution-grid">';
            foreach ($resolution['groups'][$key] as $item) {
                $this->render_result_card($item, $resolution['search_id']);
            }
            echo '</div></section>';
        }
        if ($resolution['result_count'] === 0) {
            echo '<div class="scfs-resolution-empty"><h3>' . esc_html__('No matching public guidance was found', 'sustainable-catalyst-feature-suggestions') . '</h3><p>' . esc_html__('Try a shorter error fragment or remove a product filter. You can also carry this search context into a private support request.', 'sustainable-catalyst-feature-suggestions') . '</p></div>';
        }
        if ($show_handoff) {
            $this->render_handoff_form($resolution);
        }
    }

    private function render_result_card($item, $search_id) {
        $url = $item['url'];
        if ($url !== '' && $search_id) {
            $url = wp_nonce_url(add_query_arg(array(
                'action' => 'scfs_resolution_view', 'search_id' => absint($search_id),
                'kind' => sanitize_key($item['kind']), 'result_id' => absint($item['id']),
                'destination' => $url,
            ), admin_url('admin-post.php')), 'scfs_resolution_view_' . absint($search_id));
        }
        echo '<article class="scfs-resolution-card scfs-resolution-card-' . esc_attr($item['kind']) . '">';
        echo '<div class="scfs-resolution-card-meta"><span>' . esc_html(ucwords(str_replace('_', ' ', $item['kind']))) . '</span><strong>' . esc_html(number_format_i18n($item['score'], 0)) . '</strong></div>';
        echo '<h4>' . ($url !== '' ? '<a href="' . esc_url($url) . '">' . esc_html($item['title']) . '</a>' : esc_html($item['title'])) . '</h4>';
        if ($item['status']) {
            echo '<p class="scfs-resolution-status">' . esc_html(ucwords(str_replace('_', ' ', $item['status']))) . ($item['severity'] ? ' · ' . esc_html(ucwords($item['severity'])) : '') . '</p>';
        }
        if ($item['summary']) {
            echo '<p>' . esc_html(wp_trim_words(wp_strip_all_tags($item['summary']), 34)) . '</p>';
        }
        if (!empty($item['match_reasons'])) {
            echo '<ul class="scfs-resolution-reasons">';
            foreach (array_slice($item['match_reasons'], 0, 3) as $reason) {
                echo '<li>' . esc_html($reason) . '</li>';
            }
            echo '</ul>';
        }
        echo '</article>';
    }

    private function render_handoff_form($resolution) {
        echo '<aside class="scfs-resolution-handoff"><div><p class="scfs-resolution-eyebrow">' . esc_html__('Still unresolved?', 'sustainable-catalyst-feature-suggestions') . '</p><h3>' . esc_html__('Carry this context into a private support request', 'sustainable-catalyst-feature-suggestions') . '</h3><p>' . esc_html__('Feature Suggestions will transfer only the search context and records you viewed. Contact and Engagement remains the private case-management system and will collect any contact details there.', 'sustainable-catalyst-feature-suggestions') . '</p></div>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="scfs_guided_resolution_handoff">';
        wp_nonce_field(self::NONCE_ACTION, 'scfs_resolution_nonce');
        echo '<input type="hidden" name="search_id" value="' . esc_attr((string) $resolution['search_id']) . '">';
        echo '<input type="hidden" name="query" value="' . esc_attr($resolution['query']) . '">';
        foreach ($resolution['product_context'] as $key => $value) {
            echo '<input type="hidden" name="' . esc_attr($key) . '" value="' . esc_attr($value) . '">';
        }
        echo '<label><span>' . esc_html__('Why are these results not enough?', 'sustainable-catalyst-feature-suggestions') . '</span><textarea required name="unresolved_reason" rows="3" maxlength="1000"></textarea></label>';
        echo '<label class="scfs-resolution-consent"><input required type="checkbox" name="consent" value="1"> <span>' . esc_html__('I consent to transfer this support-search context to the private Contact and Engagement workflow. I have removed secrets and sensitive personal information.', 'sustainable-catalyst-feature-suggestions') . '</span></label>';
        echo '<button type="submit">' . esc_html__('Continue to private support', 'sustainable-catalyst-feature-suggestions') . '</button></form></aside>';
    }

    private function redact_for_analytics($text) {
        $text = sanitize_text_field((string) $text);
        $patterns = array(
            '/\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}\b/i' => '[email]',
            '/\b(?:api[_ -]?key|password|secret|token)\s*[:=]\s*\S+/i' => '[secret removed]',
            '/\b(?:\d[ -]*?){13,16}\b/' => '[number removed]',
        );
        $text = preg_replace(array_keys($patterns), array_values($patterns), $text);
        return substr((string) $text, 0, 500);
    }

    private function record_search($context, $result) {
        global $wpdb;
        if (!isset($wpdb->prefix) || !method_exists($wpdb, 'insert')) {
            return 0;
        }
        $query = $this->redact_for_analytics($context['query']);
        $error = $this->redact_for_analytics($context['error_message']);
        $wpdb->insert($this->table_name(), array(
            'event_type' => 'search',
            'query_text' => $query,
            'query_hash' => hash('sha256', strtolower($query)),
            'error_hash' => $error !== '' ? hash('sha256', strtolower($error)) : '',
            'filters_json' => wp_json_encode(array('product' => $context['product'], 'product_version' => $context['product_version'], 'component' => $context['component'])),
            'result_count' => absint($result['result_count']),
            'top_score' => (float) $result['top_score'],
            'confidence' => (float) $result['confidence'],
            'resolution_state' => sanitize_key($result['resolution_state']),
            'viewed_json' => '[]',
            'source' => sanitize_key($context['source']),
            'created_at' => current_time('mysql', true),
        ), array('%s', '%s', '%s', '%s', '%s', '%d', '%f', '%f', '%s', '%s', '%s', '%s'));
        $search_id = isset($wpdb->insert_id) ? absint($wpdb->insert_id) : 0;
        do_action('scfs_guided_resolution_search_recorded', $search_id, $context, $result);
        return $search_id;
    }

    public function handle_result_view() {
        $search_id = isset($_GET['search_id']) ? absint($_GET['search_id']) : 0;
        check_admin_referer('scfs_resolution_view_' . $search_id);
        $kind = isset($_GET['kind']) ? sanitize_key(wp_unslash($_GET['kind'])) : '';
        $result_id = isset($_GET['result_id']) ? absint($_GET['result_id']) : 0;
        $destination = isset($_GET['destination']) ? rawurldecode(wp_unslash($_GET['destination'])) : home_url('/');
        if ($search_id && $kind && $result_id) {
            global $wpdb;
            if (isset($wpdb->prefix) && method_exists($wpdb, 'get_var') && method_exists($wpdb, 'update')) {
                $current = $wpdb->get_var($wpdb->prepare('SELECT viewed_json FROM ' . $this->table_name() . ' WHERE id = %d', $search_id)); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
                $viewed = json_decode((string) $current, true);
                $viewed = is_array($viewed) ? $viewed : array();
                $viewed[] = array('kind' => $kind, 'id' => $result_id, 'viewed_at' => gmdate('c'));
                $wpdb->update($this->table_name(), array('viewed_json' => wp_json_encode(array_slice($viewed, -25))), array('id' => $search_id), array('%s'), array('%d'));
            }
        }
        if ($search_id && $kind === 'support_article') {
            $destination = add_query_arg('scfs_search_id', $search_id, $destination);
        }
        wp_safe_redirect(wp_validate_redirect($destination, home_url('/')));
        exit;
    }

    public function handoff_schema() {
        return array(
            'schema' => 'sc-contact-engagement-resolution-handoff/' . self::SCHEMA_VERSION,
            'direction' => 'feature_suggestions_guided_resolution_to_contact_and_engagement',
            'purpose' => 'Carry unresolved public support-search context into a private support workflow after explicit consent.',
            'context_fields' => array('original_query', 'product', 'product_version', 'component', 'records_viewed', 'search_confidence', 'unresolved_reason'),
            'relationship_callback' => '/wp-json/' . Sustainable_Catalyst_Feature_Suggestions::REST_NAMESPACE . '/documentation-intelligence/relationships',
            'privacy' => array('classification' => 'private', 'contact_details_collected_at_destination' => true, 'automatic_case_creation' => false, 'consent_required' => true),
        );
    }

    public function build_handoff_payload($data) {
        $search_id = absint(isset($data['search_id']) ? $data['search_id'] : 0);
        $viewed = array();
        $confidence = 0;
        $state = 'unresolved';
        if ($search_id) {
            global $wpdb;
            if (isset($wpdb->prefix) && method_exists($wpdb, 'get_row')) {
                $row = $wpdb->get_row($wpdb->prepare('SELECT viewed_json, confidence, resolution_state FROM ' . $this->table_name() . ' WHERE id = %d', $search_id), ARRAY_A); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
                if (is_array($row)) {
                    $decoded = json_decode((string) $row['viewed_json'], true);
                    $viewed = is_array($decoded) ? $decoded : array();
                    $confidence = (float) $row['confidence'];
                    $state = sanitize_key($row['resolution_state']);
                }
            }
        }
        return array(
            'schema' => 'sc-contact-engagement-resolution-handoff/' . self::SCHEMA_VERSION,
            'handoff_type' => 'unresolved_product_support',
            'handoff_id' => wp_generate_uuid4(),
            'generated_at' => gmdate('c'),
            'source' => array('system' => 'sustainable-catalyst-feature-suggestions', 'version' => self::VERSION, 'workflow' => 'guided_resolution', 'search_id' => $search_id),
            'product_context' => array(
                'product' => sanitize_title(isset($data['product']) ? $data['product'] : ''),
                'product_version' => sanitize_title(isset($data['product_version']) ? $data['product_version'] : ''),
                'component' => sanitize_title(isset($data['component']) ? $data['component'] : ''),
            ),
            'resolution_context' => array(
                'original_query' => sanitize_text_field(isset($data['query']) ? $data['query'] : ''),
                'records_viewed' => $viewed,
                'search_confidence' => $confidence,
                'resolution_state' => $state,
                'unresolved_reason' => sanitize_textarea_field(isset($data['unresolved_reason']) ? $data['unresolved_reason'] : ''),
            ),
            'privacy' => array('classification' => 'private', 'consent_confirmed' => !empty($data['consent']), 'contains_contact_details' => false, 'contact_details_collected_at_destination' => true),
            'routing' => array('destination' => 'contact_and_engagement', 'queue' => 'product_support', 'create_case' => false, 'human_review_required' => true, 'relationship_callback' => '/wp-json/' . Sustainable_Catalyst_Feature_Suggestions::REST_NAMESPACE . '/documentation-intelligence/relationships'),
        );
    }

    public function handle_handoff() {
        check_admin_referer(self::NONCE_ACTION, 'scfs_resolution_nonce');
        if (empty($_POST['consent'])) {
            wp_die(__('Consent is required before transferring support-search context.', 'sustainable-catalyst-feature-suggestions'));
        }
        $payload = $this->build_handoff_payload(array(
            'search_id' => isset($_POST['search_id']) ? absint($_POST['search_id']) : 0,
            'query' => isset($_POST['query']) ? sanitize_text_field(wp_unslash($_POST['query'])) : '',
            'product' => isset($_POST['product']) ? sanitize_title(wp_unslash($_POST['product'])) : '',
            'product_version' => isset($_POST['product_version']) ? sanitize_title(wp_unslash($_POST['product_version'])) : '',
            'component' => isset($_POST['component']) ? sanitize_title(wp_unslash($_POST['component'])) : '',
            'unresolved_reason' => isset($_POST['unresolved_reason']) ? sanitize_textarea_field(wp_unslash($_POST['unresolved_reason'])) : '',
            'consent' => true,
        ));
        $token = bin2hex(random_bytes(24));
        set_transient('scfs_resolution_handoff_' . hash('sha256', $token), $payload, self::HANDOFF_TTL);
        $settings = $this->settings();
        $destination = $settings['private_support_url'] ?: home_url('/contact/');
        $destination = add_query_arg(array('scfs_handoff' => $token, 'source' => 'guided-resolution'), $destination);
        wp_safe_redirect($destination);
        exit;
    }

    public function register_rest_routes() {
        $namespace = Sustainable_Catalyst_Feature_Suggestions::REST_NAMESPACE;
        register_rest_route($namespace, '/guided-resolution/schema', array('methods' => 'GET', 'callback' => array($this, 'rest_schema'), 'permission_callback' => '__return_true'));
        register_rest_route($namespace, '/guided-resolution/search', array('methods' => array('GET', 'POST'), 'callback' => array($this, 'rest_search'), 'permission_callback' => '__return_true'));
        register_rest_route($namespace, '/guided-resolution/handoff-schema', array('methods' => 'GET', 'callback' => array($this, 'rest_handoff_schema'), 'permission_callback' => '__return_true'));
        register_rest_route($namespace, '/guided-resolution/handoffs/(?P<token>[a-f0-9]{48})', array('methods' => 'GET', 'callback' => array($this, 'rest_handoff'), 'permission_callback' => '__return_true'));
        register_rest_route($namespace, '/guided-resolution/analytics', array('methods' => 'GET', 'callback' => array($this, 'rest_analytics'), 'permission_callback' => array($this, 'rest_admin_permission')));
    }

    public function rest_admin_permission() {
        return current_user_can('edit_posts');
    }

    public function rest_schema() {
        return rest_ensure_response($this->schema_record());
    }

    public function rest_search($request) {
        $context = array(
            'query' => sanitize_text_field($request->get_param('query')),
            'error_message' => sanitize_textarea_field($request->get_param('error_message')),
            'product' => sanitize_title($request->get_param('product')),
            'product_version' => sanitize_title($request->get_param('product_version')),
            'component' => sanitize_title($request->get_param('component')),
            'source' => 'rest',
        );
        if ($context['query'] === '' && $context['error_message'] === '') {
            return new WP_Error('scfs_resolution_query_required', __('A query or error message is required.', 'sustainable-catalyst-feature-suggestions'), array('status' => 400));
        }
        return rest_ensure_response($this->search($context, true));
    }

    public function rest_handoff_schema() {
        return rest_ensure_response($this->handoff_schema());
    }

    public function rest_handoff($request) {
        $token = sanitize_text_field($request['token']);
        $key = 'scfs_resolution_handoff_' . hash('sha256', $token);
        $payload = get_transient($key);
        if (!$payload) {
            return new WP_Error('scfs_handoff_not_found', __('The handoff token is invalid or expired.', 'sustainable-catalyst-feature-suggestions'), array('status' => 404));
        }
        if ($request->get_param('consume') === '1') {
            delete_transient($key);
        }
        return rest_ensure_response($payload);
    }

    public function rest_analytics() {
        return rest_ensure_response($this->analytics_summary());
    }

    public function register_admin_page() {
        add_submenu_page(
            'edit.php?post_type=' . Sustainable_Catalyst_Feature_Suggestions::POST_TYPE,
            __('Search and Guided Resolution', 'sustainable-catalyst-feature-suggestions'),
            __('Guided Resolution', 'sustainable-catalyst-feature-suggestions'),
            'edit_posts',
            'scfs-guided-resolution',
            array($this, 'render_admin_page')
        );
    }

    public function analytics_summary() {
        global $wpdb;
        $summary = array('total_searches' => 0, 'no_match' => 0, 'low_confidence' => 0, 'strong_match' => 0, 'average_confidence' => 0, 'recent' => array(), 'top_failed_queries' => array());
        if (!isset($wpdb->prefix) || !method_exists($wpdb, 'get_var')) {
            return $summary;
        }
        $table = $this->table_name();
        $summary['total_searches'] = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE event_type = 'search'"); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $summary['no_match'] = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE resolution_state = 'no_match'"); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $summary['low_confidence'] = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE resolution_state = 'low_confidence'"); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $summary['strong_match'] = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE resolution_state = 'strong_match'"); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $summary['average_confidence'] = round((float) $wpdb->get_var("SELECT AVG(confidence) FROM {$table} WHERE event_type = 'search'"), 4); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        if (method_exists($wpdb, 'get_results')) {
            $summary['recent'] = $wpdb->get_results("SELECT id, query_text, result_count, confidence, resolution_state, created_at FROM {$table} WHERE event_type = 'search' ORDER BY id DESC LIMIT 30", ARRAY_A); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $summary['top_failed_queries'] = $wpdb->get_results("SELECT query_text, COUNT(*) AS searches FROM {$table} WHERE resolution_state IN ('no_match','low_confidence') AND query_text <> '' GROUP BY query_hash, query_text ORDER BY searches DESC LIMIT 15", ARRAY_A); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        }
        return $summary;
    }

    public function render_admin_page() {
        if (!current_user_can('edit_posts')) {
            wp_die(__('You do not have permission to view guided resolution.', 'sustainable-catalyst-feature-suggestions'));
        }
        $settings = $this->settings();
        $analytics = $this->analytics_summary();
        $export = wp_nonce_url(admin_url('admin-post.php?action=scfs_export_resolution_analytics'), 'scfs_export_resolution_analytics');
        echo '<div class="wrap scfs-guided-resolution-admin"><h1>' . esc_html__('Search and Guided Resolution', 'sustainable-catalyst-feature-suggestions') . '</h1><p>' . esc_html__('Tune product-aware search, error matching, promoted results, and the private support handoff. Search analytics are privacy-minimized and do not store IP addresses.', 'sustainable-catalyst-feature-suggestions') . '</p>';
        if (!empty($_GET['settings-updated'])) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Guided-resolution settings saved.', 'sustainable-catalyst-feature-suggestions') . '</p></div>';
        }
        echo '<div class="scfs-intel-cards">';
        foreach (array('total_searches' => 'Searches', 'strong_match' => 'Strong matches', 'low_confidence' => 'Low confidence', 'no_match' => 'No matches', 'average_confidence' => 'Average confidence') as $key => $label) {
            $value = $key === 'average_confidence' ? number_format_i18n($analytics[$key] * 100, 1) . '%' : $analytics[$key];
            echo '<div class="scfs-intel-card"><span>' . esc_html($label) . '</span><strong>' . esc_html((string) $value) . '</strong></div>';
        }
        echo '</div><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="scfs_save_guided_resolution_settings">';
        wp_nonce_field(self::SETTINGS_NONCE_ACTION, 'scfs_guided_settings_nonce');
        echo '<table class="form-table"><tbody>';
        echo '<tr><th><label for="scfs_private_support_url">Private support URL</label></th><td><input class="regular-text" type="url" id="scfs_private_support_url" name="private_support_url" value="' . esc_attr($settings['private_support_url']) . '"><p class="description">Contact and Engagement page that receives the handoff token.</p></td></tr>';
        echo '<tr><th><label for="scfs_synonyms">Synonyms and aliases</label></th><td><textarea class="large-text code" rows="10" id="scfs_synonyms" name="synonyms">' . esc_textarea($settings['synonyms']) . '</textarea><p class="description">One mapping per line: canonical phrase = alias one, alias two.</p></td></tr>';
        echo '<tr><th><label for="scfs_promoted_results">Promoted results</label></th><td><textarea class="large-text code" rows="6" id="scfs_promoted_results" name="promoted_results">' . esc_textarea($settings['promoted_results']) . '</textarea><p class="description">Use article:123, known_issue:456, suggestion:789, or release:v3-3-0.</p></td></tr>';
        echo '<tr><th>Confidence thresholds</th><td><label>Minimum <input type="number" step="0.01" min="0" max="1" name="minimum_confidence" value="' . esc_attr($settings['minimum_confidence']) . '"></label> &nbsp; <label>Strong <input type="number" step="0.01" min="0" max="1" name="high_confidence" value="' . esc_attr($settings['high_confidence']) . '"></label></td></tr>';
        echo '<tr><th><label for="scfs_max_results">Results per group</label></th><td><input type="number" min="1" max="20" id="scfs_max_results" name="max_results_per_group" value="' . esc_attr($settings['max_results_per_group']) . '"></td></tr>';
        echo '<tr><th><label for="scfs_retention">Analytics retention</label></th><td><input type="number" min="30" max="1825" id="scfs_retention" name="analytics_retention_days" value="' . esc_attr($settings['analytics_retention_days']) . '"> days</td></tr>';
        echo '</tbody></table><p><button class="button button-primary" type="submit">' . esc_html__('Save guided-resolution settings', 'sustainable-catalyst-feature-suggestions') . '</button> <a class="button" href="' . esc_url($export) . '">' . esc_html__('Export search analytics CSV', 'sustainable-catalyst-feature-suggestions') . '</a></p></form>';
        echo '<h2>' . esc_html__('Top unresolved searches', 'sustainable-catalyst-feature-suggestions') . '</h2><table class="widefat striped"><thead><tr><th>Query</th><th>Searches</th></tr></thead><tbody>';
        if (empty($analytics['top_failed_queries'])) {
            echo '<tr><td colspan="2">' . esc_html__('No unresolved search data yet.', 'sustainable-catalyst-feature-suggestions') . '</td></tr>';
        } else {
            foreach ($analytics['top_failed_queries'] as $row) {
                echo '<tr><td>' . esc_html($row['query_text']) . '</td><td>' . esc_html((string) $row['searches']) . '</td></tr>';
            }
        }
        echo '</tbody></table><h2>' . esc_html__('Public shortcode', 'sustainable-catalyst-feature-suggestions') . '</h2><code>[' . esc_html(self::SHORTCODE) . ']</code><h2>' . esc_html__('REST API', 'sustainable-catalyst-feature-suggestions') . '</h2><p><code>/wp-json/' . esc_html(Sustainable_Catalyst_Feature_Suggestions::REST_NAMESPACE) . '/guided-resolution/search</code></p></div>';
    }

    public function save_settings() {
        if (!current_user_can('edit_posts')) {
            wp_die(__('You do not have permission to change guided-resolution settings.', 'sustainable-catalyst-feature-suggestions'));
        }
        check_admin_referer(self::SETTINGS_NONCE_ACTION, 'scfs_guided_settings_nonce');
        $minimum = isset($_POST['minimum_confidence']) ? (float) $_POST['minimum_confidence'] : 0.42;
        $high = isset($_POST['high_confidence']) ? (float) $_POST['high_confidence'] : 0.72;
        $settings = array(
            'private_support_url' => isset($_POST['private_support_url']) ? esc_url_raw(wp_unslash($_POST['private_support_url'])) : '',
            'minimum_confidence' => (string) max(0, min(1, $minimum)),
            'high_confidence' => (string) max(0, min(1, max($minimum, $high))),
            'max_results_per_group' => (string) max(1, min(20, absint(isset($_POST['max_results_per_group']) ? $_POST['max_results_per_group'] : 6))),
            'synonyms' => isset($_POST['synonyms']) ? sanitize_textarea_field(wp_unslash($_POST['synonyms'])) : '',
            'promoted_results' => isset($_POST['promoted_results']) ? sanitize_textarea_field(wp_unslash($_POST['promoted_results'])) : '',
            'analytics_retention_days' => (string) max(30, min(1825, absint(isset($_POST['analytics_retention_days']) ? $_POST['analytics_retention_days'] : 365))),
        );
        update_option(self::OPTION_KEY, $settings, false);
        $this->purge_old_analytics(absint($settings['analytics_retention_days']));
        wp_safe_redirect(admin_url('edit.php?post_type=' . Sustainable_Catalyst_Feature_Suggestions::POST_TYPE . '&page=scfs-guided-resolution&settings-updated=1'));
        exit;
    }

    private function purge_old_analytics($days) {
        global $wpdb;
        if (isset($wpdb->prefix) && method_exists($wpdb, 'query')) {
            $wpdb->query($wpdb->prepare('DELETE FROM ' . $this->table_name() . ' WHERE created_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL %d DAY)', max(30, $days))); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        }
    }

    public function export_analytics() {
        if (!current_user_can('edit_posts')) {
            wp_die(__('You do not have permission to export search analytics.', 'sustainable-catalyst-feature-suggestions'));
        }
        check_admin_referer('scfs_export_resolution_analytics');
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=scfs-guided-resolution-' . gmdate('Y-m-d') . '.csv');
        $out = fopen('php://output', 'w');
        fputcsv($out, array('ID', 'Query', 'Result Count', 'Top Score', 'Confidence', 'Resolution State', 'Source', 'Created At'));
        global $wpdb;
        if (isset($wpdb->prefix) && method_exists($wpdb, 'get_results')) {
            $rows = $wpdb->get_results('SELECT id, query_text, result_count, top_score, confidence, resolution_state, source, created_at FROM ' . $this->table_name() . ' ORDER BY id DESC', ARRAY_A); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            foreach ((array) $rows as $row) {
                fputcsv($out, array($row['id'], $row['query_text'], $row['result_count'], $row['top_score'], $row['confidence'], $row['resolution_state'], $row['source'], $row['created_at']));
            }
        }
        fclose($out);
        exit;
    }
}
