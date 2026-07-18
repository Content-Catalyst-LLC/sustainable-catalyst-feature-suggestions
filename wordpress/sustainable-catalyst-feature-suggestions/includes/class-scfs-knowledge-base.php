<?php
/**
 * Support Knowledge Base foundation: public support articles, documentation
 * collections, article templates, known issues, REST records, and typed
 * relationships to products, releases, and reviewed feature suggestions.
 *
 * @package Sustainable_Catalyst_Feature_Suggestions
 */

if (!defined('ABSPATH')) {
    exit;
}

final class SCFS_Knowledge_Base_Foundation {
    const VERSION = '5.2.4';
    const SCHEMA_VERSION = '1.0';
    const ARTICLE_POST_TYPE = 'sc_support_article';
    const ISSUE_POST_TYPE = 'sc_known_issue';
    const COLLECTION_TAXONOMY = 'scfs_doc_collection';
    const ARTICLE_TYPE_TAXONOMY = 'scfs_article_type';
    const SHORTCODE = 'scfs_support_knowledge_base';
    const LEGACY_SHORTCODE = 'sustainable_catalyst_support_knowledge_base';
    const SEED_OPTION = 'scfs_kb_seeded_version';
    const SCHEMA_OPTION = 'scfs_kb_schema_version';
    const NONCE_ACTION = 'scfs_save_kb_record';
    const NONCE_NAME = 'scfs_kb_nonce';

    private static $instance = null;

    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('init', array($this, 'register_content_types'), 7);
        add_action('init', array($this, 'register_taxonomies'), 8);
        add_action('init', array($this, 'register_meta'), 9);
        add_action('init', array($this, 'seed_foundation_terms'), 21);
        add_action('init', array($this, 'register_shortcodes'), 30);
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post_' . self::ARTICLE_POST_TYPE, array($this, 'save_article'), 20, 2);
        add_action('save_post_' . self::ISSUE_POST_TYPE, array($this, 'save_known_issue'), 20, 2);
        add_action('admin_menu', array($this, 'register_admin_page'), 27);
        add_action('admin_enqueue_scripts', array($this, 'admin_assets'));
        add_action('wp_enqueue_scripts', array($this, 'register_assets'));
        add_action('rest_api_init', array($this, 'register_rest_routes'));
        add_filter('template_include', array($this, 'template_include'));
        add_filter('body_class', array($this, 'knowledge_base_body_classes'));
        add_filter('astra_page_layout', array($this, 'force_full_width_layout'));
        add_filter('astra_get_content_layout', array($this, 'force_full_width_layout'));
        add_filter('astra_the_title_enabled', array($this, 'disable_theme_title'));
        add_filter('astra_single_post_meta', array($this, 'disable_theme_post_meta'));
        add_filter('the_content', array($this, 'decorate_single_content'), 20);
        add_filter('manage_' . self::ARTICLE_POST_TYPE . '_posts_columns', array($this, 'article_columns'));
        add_action('manage_' . self::ARTICLE_POST_TYPE . '_posts_custom_column', array($this, 'article_column_content'), 10, 2);
        add_filter('manage_' . self::ISSUE_POST_TYPE . '_posts_columns', array($this, 'issue_columns'));
        add_action('manage_' . self::ISSUE_POST_TYPE . '_posts_custom_column', array($this, 'issue_column_content'), 10, 2);
        add_action('restrict_manage_posts', array($this, 'admin_filters'));
        add_action('pre_get_posts', array($this, 'apply_admin_filters'));
    }

    public static function activate() {
        $instance = self::instance();
        $instance->register_content_types();
        $instance->register_taxonomies();
        $instance->register_meta();
        $instance->seed_foundation_terms(true);
        update_option(self::SCHEMA_OPTION, self::SCHEMA_VERSION, false);
    }

    public function register_content_types() {
        register_post_type(self::ARTICLE_POST_TYPE, array(
            'labels' => array(
                'name' => __('Support Articles', 'sustainable-catalyst-feature-suggestions'),
                'singular_name' => __('Support Article', 'sustainable-catalyst-feature-suggestions'),
                'menu_name' => __('Support Articles', 'sustainable-catalyst-feature-suggestions'),
                'add_new_item' => __('Add Support Article', 'sustainable-catalyst-feature-suggestions'),
                'edit_item' => __('Edit Support Article', 'sustainable-catalyst-feature-suggestions'),
                'new_item' => __('New Support Article', 'sustainable-catalyst-feature-suggestions'),
                'view_item' => __('View Support Article', 'sustainable-catalyst-feature-suggestions'),
                'search_items' => __('Search Support Articles', 'sustainable-catalyst-feature-suggestions'),
                'not_found' => __('No support articles found.', 'sustainable-catalyst-feature-suggestions'),
                'all_items' => __('All Support Articles', 'sustainable-catalyst-feature-suggestions'),
            ),
            'public' => true,
            'publicly_queryable' => true,
            'show_ui' => true,
            'show_in_menu' => 'edit.php?post_type=' . Sustainable_Catalyst_Feature_Suggestions::POST_TYPE,
            'show_in_rest' => true,
            'rest_base' => 'support-articles',
            'menu_icon' => 'dashicons-media-document',
            'supports' => array('title', 'editor', 'excerpt', 'author', 'thumbnail', 'revisions', 'custom-fields'),
            'has_archive' => 'support-documentation',
            'rewrite' => array('slug' => 'support/guides', 'with_front' => false),
            'taxonomies' => array(
                SCFS_Product_Integration::PRODUCT_TAXONOMY,
                SCFS_Product_Integration::VERSION_TAXONOMY,
                SCFS_Product_Integration::COMPONENT_TAXONOMY,
                SCFS_Product_Integration::ISSUE_TAXONOMY,
                SCFS_Product_Integration::RELEASE_TAXONOMY,
                self::COLLECTION_TAXONOMY,
                self::ARTICLE_TYPE_TAXONOMY,
            ),
            'capability_type' => 'post',
            'map_meta_cap' => true,
        ));

        register_post_type(self::ISSUE_POST_TYPE, array(
            'labels' => array(
                'name' => __('Known Issues', 'sustainable-catalyst-feature-suggestions'),
                'singular_name' => __('Known Issue', 'sustainable-catalyst-feature-suggestions'),
                'menu_name' => __('Known Issues', 'sustainable-catalyst-feature-suggestions'),
                'add_new_item' => __('Add Known Issue', 'sustainable-catalyst-feature-suggestions'),
                'edit_item' => __('Edit Known Issue', 'sustainable-catalyst-feature-suggestions'),
                'new_item' => __('New Known Issue', 'sustainable-catalyst-feature-suggestions'),
                'view_item' => __('View Known Issue', 'sustainable-catalyst-feature-suggestions'),
                'search_items' => __('Search Known Issues', 'sustainable-catalyst-feature-suggestions'),
                'not_found' => __('No known issues found.', 'sustainable-catalyst-feature-suggestions'),
                'all_items' => __('All Known Issues', 'sustainable-catalyst-feature-suggestions'),
            ),
            'public' => true,
            'publicly_queryable' => true,
            'show_ui' => true,
            'show_in_menu' => 'edit.php?post_type=' . Sustainable_Catalyst_Feature_Suggestions::POST_TYPE,
            'show_in_rest' => true,
            'rest_base' => 'known-issues',
            'menu_icon' => 'dashicons-warning',
            'supports' => array('title', 'editor', 'excerpt', 'author', 'revisions', 'custom-fields'),
            'has_archive' => 'known-issues',
            'rewrite' => array('slug' => 'known-issues', 'with_front' => false),
            'taxonomies' => array(
                SCFS_Product_Integration::PRODUCT_TAXONOMY,
                SCFS_Product_Integration::VERSION_TAXONOMY,
                SCFS_Product_Integration::COMPONENT_TAXONOMY,
                SCFS_Product_Integration::ISSUE_TAXONOMY,
                SCFS_Product_Integration::RELEASE_TAXONOMY,
                self::COLLECTION_TAXONOMY,
            ),
            'capability_type' => 'post',
            'map_meta_cap' => true,
        ));
    }

    public function register_taxonomies() {
        register_taxonomy(self::COLLECTION_TAXONOMY, array(self::ARTICLE_POST_TYPE, self::ISSUE_POST_TYPE), array(
            'labels' => array(
                'name' => __('Documentation Collections', 'sustainable-catalyst-feature-suggestions'),
                'singular_name' => __('Documentation Collection', 'sustainable-catalyst-feature-suggestions'),
                'search_items' => __('Search Documentation Collections', 'sustainable-catalyst-feature-suggestions'),
                'all_items' => __('All Documentation Collections', 'sustainable-catalyst-feature-suggestions'),
                'parent_item' => __('Parent Documentation Collection', 'sustainable-catalyst-feature-suggestions'),
                'edit_item' => __('Edit Documentation Collection', 'sustainable-catalyst-feature-suggestions'),
                'add_new_item' => __('Add Documentation Collection', 'sustainable-catalyst-feature-suggestions'),
                'menu_name' => __('Documentation Collections', 'sustainable-catalyst-feature-suggestions'),
            ),
            'public' => true,
            'publicly_queryable' => true,
            'show_ui' => true,
            'show_admin_column' => true,
            'show_in_rest' => true,
            'rest_base' => 'documentation-collections',
            'hierarchical' => true,
            'rewrite' => array('slug' => 'support-collection', 'with_front' => false),
            'capabilities' => array(
                'manage_terms' => 'edit_posts',
                'edit_terms' => 'edit_posts',
                'delete_terms' => 'manage_options',
                'assign_terms' => 'edit_posts',
            ),
        ));

        register_taxonomy(self::ARTICLE_TYPE_TAXONOMY, array(self::ARTICLE_POST_TYPE), array(
            'labels' => array(
                'name' => __('Article Types', 'sustainable-catalyst-feature-suggestions'),
                'singular_name' => __('Article Type', 'sustainable-catalyst-feature-suggestions'),
                'search_items' => __('Search Article Types', 'sustainable-catalyst-feature-suggestions'),
                'all_items' => __('All Article Types', 'sustainable-catalyst-feature-suggestions'),
                'edit_item' => __('Edit Article Type', 'sustainable-catalyst-feature-suggestions'),
                'add_new_item' => __('Add Article Type', 'sustainable-catalyst-feature-suggestions'),
                'menu_name' => __('Article Types', 'sustainable-catalyst-feature-suggestions'),
            ),
            'public' => true,
            'publicly_queryable' => true,
            'show_ui' => true,
            'show_admin_column' => true,
            'show_in_rest' => true,
            'rest_base' => 'support-article-types',
            'hierarchical' => true,
            'rewrite' => array('slug' => 'support-type', 'with_front' => false),
            'capabilities' => array(
                'manage_terms' => 'edit_posts',
                'edit_terms' => 'edit_posts',
                'delete_terms' => 'manage_options',
                'assign_terms' => 'edit_posts',
            ),
        ));
    }

    public function register_meta() {
        foreach (array(self::COLLECTION_TAXONOMY, self::ARTICLE_TYPE_TAXONOMY) as $taxonomy) {
            register_term_meta($taxonomy, 'scfs_canonical_id', array(
                'type' => 'string',
                'single' => true,
                'show_in_rest' => true,
                'sanitize_callback' => 'sanitize_text_field',
                'auth_callback' => function () {
                    return current_user_can('edit_posts');
                },
            ));
        }

        foreach (array(self::ARTICLE_POST_TYPE, self::ISSUE_POST_TYPE) as $post_type) {
            register_post_meta($post_type, '_scfs_kb_schema_version', array(
                'type' => 'string',
                'single' => true,
                'show_in_rest' => true,
                'sanitize_callback' => 'sanitize_text_field',
                'auth_callback' => function () {
                    return current_user_can('edit_posts');
                },
            ));
        }

        $article_meta = array(
            '_scfs_kb_summary' => array('type' => 'string', 'sanitize_callback' => 'sanitize_textarea_field'),
            '_scfs_article_template' => array('type' => 'string', 'sanitize_callback' => 'sanitize_key'),
            '_scfs_article_audience' => array('type' => 'string', 'sanitize_callback' => 'sanitize_text_field'),
            '_scfs_article_prerequisites' => array('type' => 'string', 'sanitize_callback' => 'sanitize_textarea_field'),
            '_scfs_estimated_minutes' => array('type' => 'integer', 'sanitize_callback' => 'absint'),
            '_scfs_last_verified_version' => array('type' => 'string', 'sanitize_callback' => 'sanitize_text_field'),
            '_scfs_related_suggestions' => array(
                'type' => 'array',
                'sanitize_callback' => array($this, 'sanitize_related_suggestions'),
                'show_in_rest' => array('schema' => array('type' => 'array', 'items' => array('type' => 'integer'))),
            ),
        );
        foreach ($article_meta as $key => $args) {
            $show_in_rest = isset($args['show_in_rest']) ? $args['show_in_rest'] : true;
            register_post_meta(self::ARTICLE_POST_TYPE, $key, array(
                'type' => $args['type'],
                'single' => true,
                'show_in_rest' => $show_in_rest,
                'sanitize_callback' => $args['sanitize_callback'],
                'auth_callback' => function () {
                    return current_user_can('edit_posts');
                },
            ));
        }

        $issue_meta = array(
            '_scfs_issue_status' => array('type' => 'string', 'sanitize_callback' => array($this, 'sanitize_issue_status')),
            '_scfs_issue_severity' => array('type' => 'string', 'sanitize_callback' => array($this, 'sanitize_issue_severity')),
            '_scfs_issue_symptom' => array('type' => 'string', 'sanitize_callback' => 'sanitize_textarea_field'),
            '_scfs_issue_workaround' => array('type' => 'string', 'sanitize_callback' => 'wp_kses_post'),
            '_scfs_issue_resolution' => array('type' => 'string', 'sanitize_callback' => 'wp_kses_post'),
            '_scfs_first_observed' => array('type' => 'string', 'sanitize_callback' => 'sanitize_text_field'),
            '_scfs_resolved_at' => array('type' => 'string', 'sanitize_callback' => 'sanitize_text_field'),
            '_scfs_related_suggestions' => array(
                'type' => 'array',
                'sanitize_callback' => array($this, 'sanitize_related_suggestions'),
                'show_in_rest' => array('schema' => array('type' => 'array', 'items' => array('type' => 'integer'))),
            ),
        );
        foreach ($issue_meta as $key => $args) {
            $show_in_rest = isset($args['show_in_rest']) ? $args['show_in_rest'] : true;
            register_post_meta(self::ISSUE_POST_TYPE, $key, array(
                'type' => $args['type'],
                'single' => true,
                'show_in_rest' => $show_in_rest,
                'sanitize_callback' => $args['sanitize_callback'],
                'auth_callback' => function () {
                    return current_user_can('edit_posts');
                },
            ));
        }
    }

    public function sanitize_related_suggestions($value) {
        $ids = is_array($value) ? $value : preg_split('/[\s,]+/', (string) $value);
        $ids = array_values(array_unique(array_filter(array_map('absint', (array) $ids))));
        $valid = array();
        foreach ($ids as $id) {
            if (get_post_type($id) === Sustainable_Catalyst_Feature_Suggestions::POST_TYPE) {
                $valid[] = $id;
            }
        }
        return $valid;
    }

    public function issue_statuses() {
        return array(
            'investigating' => __('Investigating', 'sustainable-catalyst-feature-suggestions'),
            'identified' => __('Identified', 'sustainable-catalyst-feature-suggestions'),
            'workaround_available' => __('Workaround available', 'sustainable-catalyst-feature-suggestions'),
            'fix_planned' => __('Fix planned', 'sustainable-catalyst-feature-suggestions'),
            'resolved' => __('Resolved', 'sustainable-catalyst-feature-suggestions'),
            'monitoring' => __('Monitoring', 'sustainable-catalyst-feature-suggestions'),
            'closed' => __('Closed', 'sustainable-catalyst-feature-suggestions'),
        );
    }

    public function issue_severities() {
        return array(
            'informational' => __('Informational', 'sustainable-catalyst-feature-suggestions'),
            'minor' => __('Minor', 'sustainable-catalyst-feature-suggestions'),
            'moderate' => __('Moderate', 'sustainable-catalyst-feature-suggestions'),
            'major' => __('Major', 'sustainable-catalyst-feature-suggestions'),
            'critical' => __('Critical', 'sustainable-catalyst-feature-suggestions'),
        );
    }

    public function sanitize_issue_status($value) {
        $value = sanitize_key($value);
        return isset($this->issue_statuses()[$value]) ? $value : 'investigating';
    }

    public function sanitize_issue_severity($value) {
        $value = sanitize_key($value);
        return isset($this->issue_severities()[$value]) ? $value : 'moderate';
    }

    public function article_templates() {
        return apply_filters('scfs_kb_article_templates', array(
            'getting-started' => array(
                'label' => __('Getting Started', 'sustainable-catalyst-feature-suggestions'),
                'type' => 'getting-started',
                'content' => "<!-- wp:heading --><h2>Overview</h2><!-- /wp:heading -->\n<!-- wp:paragraph --><p>Explain what this product or capability does and when to use it.</p><!-- /wp:paragraph -->\n<!-- wp:heading --><h2>Before you begin</h2><!-- /wp:heading -->\n<!-- wp:list --><ul><li>List prerequisites, permissions, or required configuration.</li></ul><!-- /wp:list -->\n<!-- wp:heading --><h2>Steps</h2><!-- /wp:heading -->\n<!-- wp:list {\"ordered\":true} --><ol><li>Complete the first setup step.</li><li>Verify the expected result.</li></ol><!-- /wp:list -->\n<!-- wp:heading --><h2>Next steps</h2><!-- /wp:heading -->\n<!-- wp:paragraph --><p>Link to the next relevant guide or reference article.</p><!-- /wp:paragraph -->",
            ),
            'how-to' => array(
                'label' => __('How-to Guide', 'sustainable-catalyst-feature-suggestions'),
                'type' => 'how-to',
                'content' => "<!-- wp:heading --><h2>Goal</h2><!-- /wp:heading -->\n<!-- wp:paragraph --><p>State the outcome this guide helps the reader achieve.</p><!-- /wp:paragraph -->\n<!-- wp:heading --><h2>Requirements</h2><!-- /wp:heading -->\n<!-- wp:list --><ul><li>List required access, data, or software.</li></ul><!-- /wp:list -->\n<!-- wp:heading --><h2>Procedure</h2><!-- /wp:heading -->\n<!-- wp:list {\"ordered\":true} --><ol><li>Describe the first action.</li><li>Describe validation and expected output.</li></ol><!-- /wp:list -->\n<!-- wp:heading --><h2>Troubleshooting</h2><!-- /wp:heading -->\n<!-- wp:paragraph --><p>Document common failure states and recovery steps.</p><!-- /wp:paragraph -->",
            ),
            'troubleshooting' => array(
                'label' => __('Troubleshooting', 'sustainable-catalyst-feature-suggestions'),
                'type' => 'troubleshooting',
                'content' => "<!-- wp:heading --><h2>Symptoms</h2><!-- /wp:heading -->\n<!-- wp:list --><ul><li>Describe the visible error, message, or behavior.</li></ul><!-- /wp:list -->\n<!-- wp:heading --><h2>Likely causes</h2><!-- /wp:heading -->\n<!-- wp:list --><ul><li>List the most likely causes in diagnostic order.</li></ul><!-- /wp:list -->\n<!-- wp:heading --><h2>Resolution</h2><!-- /wp:heading -->\n<!-- wp:list {\"ordered\":true} --><ol><li>Perform the safest corrective step.</li><li>Confirm that the issue is resolved.</li></ol><!-- /wp:list -->\n<!-- wp:heading --><h2>Escalation information</h2><!-- /wp:heading -->\n<!-- wp:paragraph --><p>List the product, version, component, error message, and evidence to include in a private support case.</p><!-- /wp:paragraph -->",
            ),
            'reference' => array(
                'label' => __('Technical Reference', 'sustainable-catalyst-feature-suggestions'),
                'type' => 'reference',
                'content' => "<!-- wp:heading --><h2>Purpose</h2><!-- /wp:heading -->\n<!-- wp:paragraph --><p>Define the interface, data model, command, endpoint, shortcode, or setting.</p><!-- /wp:paragraph -->\n<!-- wp:heading --><h2>Syntax or structure</h2><!-- /wp:heading -->\n<!-- wp:code --><pre class=\"wp-block-code\"><code>Provide a canonical example.</code></pre><!-- /wp:code -->\n<!-- wp:heading --><h2>Parameters and fields</h2><!-- /wp:heading -->\n<!-- wp:paragraph --><p>Document accepted values, defaults, constraints, and permissions.</p><!-- /wp:paragraph -->\n<!-- wp:heading --><h2>Examples</h2><!-- /wp:heading -->\n<!-- wp:paragraph --><p>Provide validated examples and expected results.</p><!-- /wp:paragraph -->",
            ),
            'known-issue-companion' => array(
                'label' => __('Known Issue Companion', 'sustainable-catalyst-feature-suggestions'),
                'type' => 'known-issue',
                'content' => "<!-- wp:heading --><h2>Issue summary</h2><!-- /wp:heading -->\n<!-- wp:paragraph --><p>Summarize the affected behavior and scope.</p><!-- /wp:paragraph -->\n<!-- wp:heading --><h2>Affected products and versions</h2><!-- /wp:heading -->\n<!-- wp:paragraph --><p>Identify verified affected versions and components.</p><!-- /wp:paragraph -->\n<!-- wp:heading --><h2>Workaround</h2><!-- /wp:heading -->\n<!-- wp:paragraph --><p>Document a safe temporary workaround, or state that none is available.</p><!-- /wp:paragraph -->\n<!-- wp:heading --><h2>Resolution status</h2><!-- /wp:heading -->\n<!-- wp:paragraph --><p>Link to the known-issue record and target release when available.</p><!-- /wp:paragraph -->",
            ),
        ));
    }

    public function seed_foundation_terms($force = false) {
        if (!$force && get_option(self::SEED_OPTION) === self::VERSION) {
            return;
        }
        $collections = array(
            'platform' => 'Sustainable Catalyst Platform',
            'research-librarian' => 'Research Librarian',
            'research-lab' => 'Research Lab',
            'knowledge-library' => 'Knowledge Library',
            'workbench' => 'Workbench',
            'decision-studio' => 'Decision Studio',
            'site-intelligence' => 'Site Intelligence',
            'feature-suggestions' => 'Feature Suggestions',
            'contact-and-engagement' => 'Contact and Engagement',
            'installation-upgrades' => 'Installation and Upgrades',
            'api-integrations' => 'APIs and Integrations',
            'troubleshooting' => 'Troubleshooting',
        );
        $types = array(
            'getting-started' => 'Getting Started',
            'how-to' => 'How-to Guide',
            'troubleshooting' => 'Troubleshooting',
            'reference' => 'Technical Reference',
            'faq' => 'Frequently Asked Questions',
            'known-issue' => 'Known Issue Companion',
            'release-note' => 'Release Note',
        );
        $this->seed_terms(self::COLLECTION_TAXONOMY, $collections);
        $this->seed_terms(self::ARTICLE_TYPE_TAXONOMY, $types);
        update_option(self::SEED_OPTION, self::VERSION, false);
        update_option(self::SCHEMA_OPTION, self::SCHEMA_VERSION, false);
    }

    private function seed_terms($taxonomy, $terms) {
        foreach ($terms as $slug => $name) {
            $term = get_term_by('slug', $slug, $taxonomy);
            if (!$term) {
                $created = wp_insert_term($name, $taxonomy, array('slug' => $slug));
                if (is_wp_error($created)) {
                    continue;
                }
                $term = get_term($created['term_id'], $taxonomy);
            }
            if ($term && !is_wp_error($term) && !get_term_meta($term->term_id, 'scfs_canonical_id', true)) {
                update_term_meta($term->term_id, 'scfs_canonical_id', 'scfs:' . $taxonomy . ':' . $slug);
            }
        }
    }

    public function register_shortcodes() {
        add_shortcode(self::SHORTCODE, array($this, 'render_shortcode'));
        add_shortcode(self::LEGACY_SHORTCODE, array($this, 'render_shortcode'));
    }

    public function register_assets() {
        wp_register_style(
            'scfs-knowledge-base',
            plugins_url('../assets/knowledge-base.css', __FILE__),
            array(),
            self::VERSION
        );
        if (is_post_type_archive(self::ARTICLE_POST_TYPE) || is_post_type_archive(self::ISSUE_POST_TYPE) || is_singular(array(self::ARTICLE_POST_TYPE, self::ISSUE_POST_TYPE))) {
            wp_enqueue_style('scfs-knowledge-base');
        }
    }

    public function admin_assets() {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if ($screen && in_array($screen->post_type, array(self::ARTICLE_POST_TYPE, self::ISSUE_POST_TYPE), true)) {
            wp_enqueue_style('scfs-admin', plugins_url('../assets/feature-suggestions-admin.css', __FILE__), array(), self::VERSION);
        }
    }

    public function add_meta_boxes() {
        add_meta_box('scfs-kb-article-details', __('Support Article Details', 'sustainable-catalyst-feature-suggestions'), array($this, 'article_meta_box'), self::ARTICLE_POST_TYPE, 'normal', 'high');
        add_meta_box('scfs-kb-relationships', __('Related Suggestions and Releases', 'sustainable-catalyst-feature-suggestions'), array($this, 'relationships_meta_box'), self::ARTICLE_POST_TYPE, 'side', 'default');
        add_meta_box('scfs-known-issue-details', __('Known Issue Status', 'sustainable-catalyst-feature-suggestions'), array($this, 'known_issue_meta_box'), self::ISSUE_POST_TYPE, 'normal', 'high');
        add_meta_box('scfs-known-issue-relationships', __('Related Suggestions and Releases', 'sustainable-catalyst-feature-suggestions'), array($this, 'relationships_meta_box'), self::ISSUE_POST_TYPE, 'side', 'default');
    }

    public function article_meta_box($post) {
        wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME);
        $template = get_post_meta($post->ID, '_scfs_article_template', true);
        $summary = get_post_meta($post->ID, '_scfs_kb_summary', true);
        $audience = get_post_meta($post->ID, '_scfs_article_audience', true);
        $prerequisites = get_post_meta($post->ID, '_scfs_article_prerequisites', true);
        $minutes = absint(get_post_meta($post->ID, '_scfs_estimated_minutes', true));
        $verified = get_post_meta($post->ID, '_scfs_last_verified_version', true);
        echo '<p><label for="scfs_article_template"><strong>' . esc_html__('Article template', 'sustainable-catalyst-feature-suggestions') . '</strong></label><br>';
        echo '<select class="widefat" id="scfs_article_template" name="scfs_article_template"><option value="">' . esc_html__('No template', 'sustainable-catalyst-feature-suggestions') . '</option>';
        foreach ($this->article_templates() as $key => $definition) {
            echo '<option value="' . esc_attr($key) . '" ' . selected($template, $key, false) . '>' . esc_html($definition['label']) . '</option>';
        }
        echo '</select></p><p class="description">' . esc_html__('When the article content is empty, the selected template is inserted on save. Existing content is never overwritten.', 'sustainable-catalyst-feature-suggestions') . '</p>';
        echo '<p><label for="scfs_kb_summary"><strong>' . esc_html__('Support summary', 'sustainable-catalyst-feature-suggestions') . '</strong></label><br><textarea class="widefat" rows="3" id="scfs_kb_summary" name="scfs_kb_summary">' . esc_textarea($summary) . '</textarea></p>';
        echo '<div class="scfs-admin-grid"><p><label for="scfs_article_audience"><strong>' . esc_html__('Audience', 'sustainable-catalyst-feature-suggestions') . '</strong></label><br><input class="widefat" id="scfs_article_audience" name="scfs_article_audience" value="' . esc_attr($audience) . '"></p>';
        echo '<p><label for="scfs_estimated_minutes"><strong>' . esc_html__('Estimated minutes', 'sustainable-catalyst-feature-suggestions') . '</strong></label><br><input class="small-text" type="number" min="0" max="999" id="scfs_estimated_minutes" name="scfs_estimated_minutes" value="' . esc_attr((string) $minutes) . '"></p>';
        echo '<p><label for="scfs_last_verified_version"><strong>' . esc_html__('Last verified version', 'sustainable-catalyst-feature-suggestions') . '</strong></label><br><input class="widefat" id="scfs_last_verified_version" name="scfs_last_verified_version" value="' . esc_attr($verified) . '" placeholder="v4.0.2"></p></div>';
        echo '<p><label for="scfs_article_prerequisites"><strong>' . esc_html__('Prerequisites', 'sustainable-catalyst-feature-suggestions') . '</strong></label><br><textarea class="widefat" rows="3" id="scfs_article_prerequisites" name="scfs_article_prerequisites">' . esc_textarea($prerequisites) . '</textarea></p>';
    }

    public function relationships_meta_box($post) {
        if (!wp_script_is('jquery', 'enqueued')) {
            wp_enqueue_script('jquery');
        }
        if (!isset($_POST[self::NONCE_NAME])) {
            wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME);
        }
        $ids = (array) get_post_meta($post->ID, '_scfs_related_suggestions', true);
        echo '<p><label for="scfs_related_suggestions"><strong>' . esc_html__('Feature suggestion IDs', 'sustainable-catalyst-feature-suggestions') . '</strong></label><br><input class="widefat" id="scfs_related_suggestions" name="scfs_related_suggestions" value="' . esc_attr(implode(', ', array_map('absint', $ids))) . '"></p>';
        echo '<p class="description">' . esc_html__('Comma-separated reviewed suggestion IDs. Private feedback text is never exposed publicly.', 'sustainable-catalyst-feature-suggestions') . '</p>';
        if ($ids) {
            echo '<ul class="scfs-related-admin-list">';
            foreach ($ids as $id) {
                $edit = get_edit_post_link($id, 'raw');
                if ($edit) {
                    echo '<li><a href="' . esc_url($edit) . '">#' . esc_html((string) $id) . ' — ' . esc_html(get_the_title($id)) . '</a></li>';
                }
            }
            echo '</ul>';
        }
        echo '<p class="description">' . esc_html__('Assign Release taxonomy terms in the sidebar to connect the article or issue to planned and completed releases.', 'sustainable-catalyst-feature-suggestions') . '</p>';
    }

    public function known_issue_meta_box($post) {
        wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME);
        $status = get_post_meta($post->ID, '_scfs_issue_status', true) ?: 'investigating';
        $severity = get_post_meta($post->ID, '_scfs_issue_severity', true) ?: 'moderate';
        $symptom = get_post_meta($post->ID, '_scfs_issue_symptom', true);
        $workaround = get_post_meta($post->ID, '_scfs_issue_workaround', true);
        $resolution = get_post_meta($post->ID, '_scfs_issue_resolution', true);
        $observed = get_post_meta($post->ID, '_scfs_first_observed', true);
        $resolved = get_post_meta($post->ID, '_scfs_resolved_at', true);
        echo '<div class="scfs-admin-grid"><p><label for="scfs_issue_status"><strong>' . esc_html__('Status', 'sustainable-catalyst-feature-suggestions') . '</strong></label><br><select class="widefat" id="scfs_issue_status" name="scfs_issue_status">';
        foreach ($this->issue_statuses() as $key => $label) {
            echo '<option value="' . esc_attr($key) . '" ' . selected($status, $key, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select></p><p><label for="scfs_issue_severity"><strong>' . esc_html__('Severity', 'sustainable-catalyst-feature-suggestions') . '</strong></label><br><select class="widefat" id="scfs_issue_severity" name="scfs_issue_severity">';
        foreach ($this->issue_severities() as $key => $label) {
            echo '<option value="' . esc_attr($key) . '" ' . selected($severity, $key, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select></p><p><label for="scfs_first_observed"><strong>' . esc_html__('First observed', 'sustainable-catalyst-feature-suggestions') . '</strong></label><br><input class="widefat" type="date" id="scfs_first_observed" name="scfs_first_observed" value="' . esc_attr($observed) . '"></p><p><label for="scfs_resolved_at"><strong>' . esc_html__('Resolved date', 'sustainable-catalyst-feature-suggestions') . '</strong></label><br><input class="widefat" type="date" id="scfs_resolved_at" name="scfs_resolved_at" value="' . esc_attr($resolved) . '"></p></div>';
        echo '<p><label for="scfs_issue_symptom"><strong>' . esc_html__('Symptoms or error message', 'sustainable-catalyst-feature-suggestions') . '</strong></label><br><textarea class="widefat" rows="3" id="scfs_issue_symptom" name="scfs_issue_symptom">' . esc_textarea($symptom) . '</textarea></p>';
        echo '<p><label for="scfs_issue_workaround"><strong>' . esc_html__('Workaround', 'sustainable-catalyst-feature-suggestions') . '</strong></label><br><textarea class="widefat" rows="4" id="scfs_issue_workaround" name="scfs_issue_workaround">' . esc_textarea($workaround) . '</textarea></p>';
        echo '<p><label for="scfs_issue_resolution"><strong>' . esc_html__('Resolution', 'sustainable-catalyst-feature-suggestions') . '</strong></label><br><textarea class="widefat" rows="4" id="scfs_issue_resolution" name="scfs_issue_resolution">' . esc_textarea($resolution) . '</textarea></p>';
    }

    private function can_save($post_id, $post) {
        if (!isset($_POST[self::NONCE_NAME]) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[self::NONCE_NAME])), self::NONCE_ACTION)) {
            return false;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return false;
        }
        if (wp_is_post_revision($post_id) || !$post || !current_user_can('edit_post', $post_id)) {
            return false;
        }
        return true;
    }

    public function save_article($post_id, $post) {
        if (!$this->can_save($post_id, $post)) {
            return;
        }
        $template = isset($_POST['scfs_article_template']) ? sanitize_key(wp_unslash($_POST['scfs_article_template'])) : '';
        if (!isset($this->article_templates()[$template])) {
            $template = '';
        }
        update_post_meta($post_id, '_scfs_article_template', $template);
        $fields = array(
            'scfs_kb_summary' => array('_scfs_kb_summary', 'sanitize_textarea_field'),
            'scfs_article_audience' => array('_scfs_article_audience', 'sanitize_text_field'),
            'scfs_article_prerequisites' => array('_scfs_article_prerequisites', 'sanitize_textarea_field'),
            'scfs_last_verified_version' => array('_scfs_last_verified_version', 'sanitize_text_field'),
        );
        foreach ($fields as $field => $definition) {
            if (isset($_POST[$field])) {
                update_post_meta($post_id, $definition[0], call_user_func($definition[1], wp_unslash($_POST[$field])));
            }
        }
        if (isset($_POST['scfs_estimated_minutes'])) {
            update_post_meta($post_id, '_scfs_estimated_minutes', min(999, absint($_POST['scfs_estimated_minutes'])));
        }
        $this->save_relationships($post_id);
        update_post_meta($post_id, '_scfs_kb_schema_version', self::SCHEMA_VERSION);
        if ($template && trim((string) $post->post_content) === '') {
            $templates = $this->article_templates();
            remove_action('save_post_' . self::ARTICLE_POST_TYPE, array($this, 'save_article'), 20);
            wp_update_post(array('ID' => $post_id, 'post_content' => $templates[$template]['content']));
            wp_set_object_terms($post_id, array($templates[$template]['type']), self::ARTICLE_TYPE_TAXONOMY, false);
            add_action('save_post_' . self::ARTICLE_POST_TYPE, array($this, 'save_article'), 20, 2);
        }
        do_action('scfs_kb_article_saved', $post_id, $this->article_record(get_post($post_id), true));
    }

    public function save_known_issue($post_id, $post) {
        if (!$this->can_save($post_id, $post)) {
            return;
        }
        $fields = array(
            'scfs_issue_status' => array('_scfs_issue_status', array($this, 'sanitize_issue_status')),
            'scfs_issue_severity' => array('_scfs_issue_severity', array($this, 'sanitize_issue_severity')),
            'scfs_issue_symptom' => array('_scfs_issue_symptom', 'sanitize_textarea_field'),
            'scfs_issue_workaround' => array('_scfs_issue_workaround', 'wp_kses_post'),
            'scfs_issue_resolution' => array('_scfs_issue_resolution', 'wp_kses_post'),
            'scfs_first_observed' => array('_scfs_first_observed', 'sanitize_text_field'),
            'scfs_resolved_at' => array('_scfs_resolved_at', 'sanitize_text_field'),
        );
        foreach ($fields as $field => $definition) {
            if (isset($_POST[$field])) {
                update_post_meta($post_id, $definition[0], call_user_func($definition[1], wp_unslash($_POST[$field])));
            }
        }
        $this->save_relationships($post_id);
        update_post_meta($post_id, '_scfs_kb_schema_version', self::SCHEMA_VERSION);
        do_action('scfs_known_issue_saved', $post_id, $this->known_issue_record(get_post($post_id), true));
    }

    private function save_relationships($post_id) {
        if (isset($_POST['scfs_related_suggestions'])) {
            update_post_meta($post_id, '_scfs_related_suggestions', $this->sanitize_related_suggestions(wp_unslash($_POST['scfs_related_suggestions'])));
        }
    }

    public function register_admin_page() {
        add_submenu_page(
            'edit.php?post_type=' . Sustainable_Catalyst_Feature_Suggestions::POST_TYPE,
            __('Support Knowledge Base', 'sustainable-catalyst-feature-suggestions'),
            __('Knowledge Base', 'sustainable-catalyst-feature-suggestions'),
            'edit_posts',
            'scfs-knowledge-base',
            array($this, 'render_admin_page')
        );
    }

    public function render_admin_page() {
        if (!current_user_can('edit_posts')) {
            wp_die(__('You do not have permission to manage the Knowledge Base.', 'sustainable-catalyst-feature-suggestions'));
        }
        $article_counts = wp_count_posts(self::ARTICLE_POST_TYPE);
        $issue_counts = wp_count_posts(self::ISSUE_POST_TYPE);
        $active_issues = new WP_Query(array(
            'post_type' => self::ISSUE_POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => 1,
            'fields' => 'ids',
            'meta_query' => array(array('key' => '_scfs_issue_status', 'value' => array('resolved', 'closed'), 'compare' => 'NOT IN')),
        ));
        echo '<div class="wrap scfs-kb-admin"><h1>' . esc_html__('Support Knowledge Base Foundation', 'sustainable-catalyst-feature-suggestions') . '</h1>';
        echo '<p>' . esc_html__('Publish product documentation and known issues using the shared product, version, component, issue, and release taxonomies established in v3.1.0.', 'sustainable-catalyst-feature-suggestions') . '</p>';
        echo '<div class="scfs-intel-cards">';
        $cards = array(
            __('Published articles', 'sustainable-catalyst-feature-suggestions') => isset($article_counts->publish) ? (int) $article_counts->publish : 0,
            __('Draft articles', 'sustainable-catalyst-feature-suggestions') => isset($article_counts->draft) ? (int) $article_counts->draft : 0,
            __('Published known issues', 'sustainable-catalyst-feature-suggestions') => isset($issue_counts->publish) ? (int) $issue_counts->publish : 0,
            __('Active known issues', 'sustainable-catalyst-feature-suggestions') => (int) $active_issues->found_posts,
        );
        foreach ($cards as $label => $value) {
            echo '<div class="scfs-intel-card"><span>' . esc_html($label) . '</span><strong>' . esc_html((string) $value) . '</strong></div>';
        }
        echo '</div><div class="scfs-kb-admin-actions">';
        echo '<a class="button button-primary" href="' . esc_url(admin_url('post-new.php?post_type=' . self::ARTICLE_POST_TYPE)) . '">' . esc_html__('Add Support Article', 'sustainable-catalyst-feature-suggestions') . '</a> ';
        echo '<a class="button" href="' . esc_url(admin_url('post-new.php?post_type=' . self::ISSUE_POST_TYPE)) . '">' . esc_html__('Add Known Issue', 'sustainable-catalyst-feature-suggestions') . '</a> ';
        echo '<a class="button" href="' . esc_url(get_post_type_archive_link(self::ARTICLE_POST_TYPE)) . '">' . esc_html__('View Public Knowledge Base', 'sustainable-catalyst-feature-suggestions') . '</a></div>';
        echo '<h2>' . esc_html__('Public shortcode', 'sustainable-catalyst-feature-suggestions') . '</h2><code>[' . esc_html(self::SHORTCODE) . ']</code>';
        echo '<h2>' . esc_html__('REST foundation', 'sustainable-catalyst-feature-suggestions') . '</h2><p><code>/wp-json/' . esc_html(Sustainable_Catalyst_Feature_Suggestions::REST_NAMESPACE) . '/knowledge-base/schema</code></p>';
        echo '<p class="description">' . esc_html__('Public endpoints expose only published documentation, known issues, taxonomy context, releases, and explicitly public feature ideas. Private suggestion text and contact data remain protected.', 'sustainable-catalyst-feature-suggestions') . '</p></div>';
    }

    public function article_columns($columns) {
        $columns['scfs_kb_product'] = __('Products', 'sustainable-catalyst-feature-suggestions');
        $columns['scfs_kb_type'] = __('Article Type', 'sustainable-catalyst-feature-suggestions');
        $columns['scfs_kb_verified'] = __('Verified', 'sustainable-catalyst-feature-suggestions');
        return $columns;
    }

    public function article_column_content($column, $post_id) {
        if ($column === 'scfs_kb_product') {
            echo esc_html(implode(', ', SCFS_Product_Integration::instance()->flat_term_names($post_id, SCFS_Product_Integration::PRODUCT_TAXONOMY)) ?: '—');
        } elseif ($column === 'scfs_kb_type') {
            echo esc_html(implode(', ', $this->term_names($post_id, self::ARTICLE_TYPE_TAXONOMY)) ?: '—');
        } elseif ($column === 'scfs_kb_verified') {
            echo esc_html(get_post_meta($post_id, '_scfs_last_verified_version', true) ?: '—');
        }
    }

    public function issue_columns($columns) {
        $columns['scfs_issue_status'] = __('Status', 'sustainable-catalyst-feature-suggestions');
        $columns['scfs_issue_severity'] = __('Severity', 'sustainable-catalyst-feature-suggestions');
        $columns['scfs_kb_product'] = __('Products', 'sustainable-catalyst-feature-suggestions');
        return $columns;
    }

    public function issue_column_content($column, $post_id) {
        if ($column === 'scfs_issue_status') {
            $status = get_post_meta($post_id, '_scfs_issue_status', true) ?: 'investigating';
            echo esc_html(isset($this->issue_statuses()[$status]) ? $this->issue_statuses()[$status] : $status);
        } elseif ($column === 'scfs_issue_severity') {
            $severity = get_post_meta($post_id, '_scfs_issue_severity', true) ?: 'moderate';
            echo esc_html(isset($this->issue_severities()[$severity]) ? $this->issue_severities()[$severity] : $severity);
        } elseif ($column === 'scfs_kb_product') {
            echo esc_html(implode(', ', SCFS_Product_Integration::instance()->flat_term_names($post_id, SCFS_Product_Integration::PRODUCT_TAXONOMY)) ?: '—');
        }
    }

    public function admin_filters() {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen || !in_array($screen->post_type, array(self::ARTICLE_POST_TYPE, self::ISSUE_POST_TYPE), true)) {
            return;
        }
        foreach (array(
            SCFS_Product_Integration::PRODUCT_TAXONOMY => __('All products', 'sustainable-catalyst-feature-suggestions'),
            self::COLLECTION_TAXONOMY => __('All collections', 'sustainable-catalyst-feature-suggestions'),
        ) as $taxonomy => $label) {
            wp_dropdown_categories(array(
                'show_option_all' => $label,
                'taxonomy' => $taxonomy,
                'name' => $taxonomy,
                'orderby' => 'name',
                'selected' => isset($_GET[$taxonomy]) ? sanitize_text_field(wp_unslash($_GET[$taxonomy])) : '',
                'hierarchical' => true,
                'hide_empty' => false,
                'value_field' => 'slug',
            ));
        }
        if ($screen->post_type === self::ISSUE_POST_TYPE) {
            $selected = isset($_GET['scfs_issue_status']) ? sanitize_key(wp_unslash($_GET['scfs_issue_status'])) : '';
            echo '<select name="scfs_issue_status"><option value="">' . esc_html__('All issue statuses', 'sustainable-catalyst-feature-suggestions') . '</option>';
            foreach ($this->issue_statuses() as $key => $label) {
                echo '<option value="' . esc_attr($key) . '" ' . selected($selected, $key, false) . '>' . esc_html($label) . '</option>';
            }
            echo '</select>';
        }
    }

    public function apply_admin_filters($query) {
        if (!is_admin() || !$query->is_main_query()) {
            return;
        }
        $post_type = $query->get('post_type');
        if (!in_array($post_type, array(self::ARTICLE_POST_TYPE, self::ISSUE_POST_TYPE), true)) {
            return;
        }
        $tax_query = array();
        foreach (array(SCFS_Product_Integration::PRODUCT_TAXONOMY, self::COLLECTION_TAXONOMY) as $taxonomy) {
            if (!empty($_GET[$taxonomy])) {
                $tax_query[] = array('taxonomy' => $taxonomy, 'field' => 'slug', 'terms' => sanitize_title(wp_unslash($_GET[$taxonomy])));
            }
        }
        if ($tax_query) {
            $query->set('tax_query', $tax_query);
        }
        if ($post_type === self::ISSUE_POST_TYPE && !empty($_GET['scfs_issue_status'])) {
            $query->set('meta_query', array(array('key' => '_scfs_issue_status', 'value' => sanitize_key(wp_unslash($_GET['scfs_issue_status'])))));
        }
    }

    public function render_shortcode($atts = array()) {
        if (class_exists('SCFS_Integrated_Knowledge_Base')) {
            return SCFS_Integrated_Knowledge_Base::instance()->render_browser($atts);
        }
        $atts = shortcode_atts(array(
            'title' => __('Support Knowledge Base', 'sustainable-catalyst-feature-suggestions'),
            'intro' => __('Search product documentation, browse collections, and review current known issues.', 'sustainable-catalyst-feature-suggestions'),
            'per_page' => 12,
            'show_known_issues' => '1',
            'product' => '',
            'collection' => '',
        ), $atts, self::SHORTCODE);
        wp_enqueue_style('scfs-knowledge-base');
        $search = isset($_GET['scfs_kb_search']) ? sanitize_text_field(wp_unslash($_GET['scfs_kb_search'])) : '';
        $product = isset($_GET['scfs_kb_product']) ? sanitize_title(wp_unslash($_GET['scfs_kb_product'])) : sanitize_title($atts['product']);
        $component = isset($_GET['scfs_kb_component']) ? sanitize_title(wp_unslash($_GET['scfs_kb_component'])) : '';
        $version = isset($_GET['scfs_kb_version']) ? sanitize_title(wp_unslash($_GET['scfs_kb_version'])) : '';
        $collection = isset($_GET['scfs_kb_collection']) ? sanitize_title(wp_unslash($_GET['scfs_kb_collection'])) : sanitize_title($atts['collection']);
        $article_type = isset($_GET['scfs_kb_type']) ? sanitize_title(wp_unslash($_GET['scfs_kb_type'])) : '';
        $page = isset($_GET['scfs_kb_page']) ? max(1, absint($_GET['scfs_kb_page'])) : 1;
        $args = array(
            'post_type' => self::ARTICLE_POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => min(50, max(1, absint($atts['per_page']))),
            'paged' => $page,
            'orderby' => array('menu_order' => 'ASC', 'modified' => 'DESC'),
            's' => $search,
        );
        $tax_query = $this->build_tax_query(array(
            SCFS_Product_Integration::PRODUCT_TAXONOMY => $product,
            SCFS_Product_Integration::COMPONENT_TAXONOMY => $component,
            SCFS_Product_Integration::VERSION_TAXONOMY => $version,
            self::COLLECTION_TAXONOMY => $collection,
            self::ARTICLE_TYPE_TAXONOMY => $article_type,
        ));
        if ($tax_query) {
            $args['tax_query'] = $tax_query;
        }
        $query = new WP_Query($args);
        ob_start();
        echo '<section class="scfs-kb" aria-labelledby="scfs-kb-title">';
        echo '<header class="scfs-kb-hero"><p class="scfs-kb-eyebrow">' . esc_html__('Product Support and Feedback', 'sustainable-catalyst-feature-suggestions') . '</p><h2 id="scfs-kb-title">' . esc_html($atts['title']) . '</h2><p>' . esc_html($atts['intro']) . '</p></header>';
        $this->render_search_form($search, $product, $version, $component, $collection, $article_type);
        if ($atts['show_known_issues'] === '1') {
            $this->render_active_issues($product, $component);
        }
        echo '<div class="scfs-kb-results-head"><h3>' . esc_html__('Documentation', 'sustainable-catalyst-feature-suggestions') . '</h3><span>' . esc_html(sprintf(_n('%d article', '%d articles', (int) $query->found_posts, 'sustainable-catalyst-feature-suggestions'), (int) $query->found_posts)) . '</span></div>';
        if (!$query->have_posts()) {
            echo '<div class="scfs-kb-empty"><h3>' . esc_html__('No matching documentation found', 'sustainable-catalyst-feature-suggestions') . '</h3><p>' . esc_html__('Try a broader search or remove one of the product, component, collection, or article-type filters.', 'sustainable-catalyst-feature-suggestions') . '</p></div>';
        } else {
            echo '<div class="scfs-kb-grid">';
            foreach ($query->posts as $post) {
                $this->render_article_card($post);
            }
            echo '</div>';
            $this->render_pagination($query, $page);
        }
        echo '</section>';
        return ob_get_clean();
    }

    private function render_search_form($search, $product, $version, $component, $collection, $article_type) {
        echo '<form class="scfs-kb-search" method="get" role="search">';
        echo '<label class="scfs-kb-search-main"><span>' . esc_html__('Search documentation', 'sustainable-catalyst-feature-suggestions') . '</span><input type="search" name="scfs_kb_search" value="' . esc_attr($search) . '" placeholder="' . esc_attr__('Search by task, error message, feature, or topic', 'sustainable-catalyst-feature-suggestions') . '"></label>';
        echo '<div class="scfs-kb-filter-grid">';
        $this->render_term_select('scfs_kb_product', SCFS_Product_Integration::PRODUCT_TAXONOMY, __('All products', 'sustainable-catalyst-feature-suggestions'), $product);
        $this->render_term_select('scfs_kb_version', SCFS_Product_Integration::VERSION_TAXONOMY, __('All versions', 'sustainable-catalyst-feature-suggestions'), $version);
        $this->render_term_select('scfs_kb_component', SCFS_Product_Integration::COMPONENT_TAXONOMY, __('All components', 'sustainable-catalyst-feature-suggestions'), $component);
        $this->render_term_select('scfs_kb_collection', self::COLLECTION_TAXONOMY, __('All collections', 'sustainable-catalyst-feature-suggestions'), $collection);
        $this->render_term_select('scfs_kb_type', self::ARTICLE_TYPE_TAXONOMY, __('All article types', 'sustainable-catalyst-feature-suggestions'), $article_type);
        echo '</div><div class="scfs-kb-search-actions"><button type="submit">' . esc_html__('Search Knowledge Base', 'sustainable-catalyst-feature-suggestions') . '</button>';
        echo '<a href="' . esc_url(remove_query_arg(array('scfs_kb_search', 'scfs_kb_product', 'scfs_kb_version', 'scfs_kb_component', 'scfs_kb_collection', 'scfs_kb_type', 'scfs_kb_page'))) . '">' . esc_html__('Clear filters', 'sustainable-catalyst-feature-suggestions') . '</a></div></form>';
    }

    private function render_term_select($name, $taxonomy, $placeholder, $selected) {
        $terms = get_terms(array('taxonomy' => $taxonomy, 'hide_empty' => false, 'orderby' => 'name'));
        if (is_wp_error($terms)) {
            $terms = array();
        }
        echo '<label><span>' . esc_html($placeholder) . '</span><select name="' . esc_attr($name) . '"><option value="">' . esc_html($placeholder) . '</option>';
        foreach ($terms as $term) {
            echo '<option value="' . esc_attr($term->slug) . '" ' . selected($selected, $term->slug, false) . '>' . esc_html($term->name) . '</option>';
        }
        echo '</select></label>';
    }

    private function build_tax_query($filters) {
        $tax_query = array('relation' => 'AND');
        foreach ($filters as $taxonomy => $slug) {
            if ($slug !== '') {
                $tax_query[] = array('taxonomy' => $taxonomy, 'field' => 'slug', 'terms' => $slug);
            }
        }
        return count($tax_query) > 1 ? $tax_query : array();
    }

    private function render_active_issues($product, $component) {
        $args = array(
            'post_type' => self::ISSUE_POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => 5,
            'orderby' => 'modified',
            'order' => 'DESC',
            'meta_query' => array(array('key' => '_scfs_issue_status', 'value' => array('resolved', 'closed'), 'compare' => 'NOT IN')),
        );
        $tax_query = $this->build_tax_query(array(
            SCFS_Product_Integration::PRODUCT_TAXONOMY => $product,
            SCFS_Product_Integration::COMPONENT_TAXONOMY => $component,
        ));
        if ($tax_query) {
            $args['tax_query'] = $tax_query;
        }
        $issues = new WP_Query($args);
        if (!$issues->have_posts()) {
            return;
        }
        echo '<aside class="scfs-kb-known-issues" aria-labelledby="scfs-kb-known-issues-title"><div class="scfs-kb-section-head"><h3 id="scfs-kb-known-issues-title">' . esc_html__('Current known issues', 'sustainable-catalyst-feature-suggestions') . '</h3><a href="' . esc_url(get_post_type_archive_link(self::ISSUE_POST_TYPE)) . '">' . esc_html__('View all known issues', 'sustainable-catalyst-feature-suggestions') . '</a></div><div class="scfs-kb-issue-list">';
        foreach ($issues->posts as $issue) {
            $status = get_post_meta($issue->ID, '_scfs_issue_status', true) ?: 'investigating';
            $severity = get_post_meta($issue->ID, '_scfs_issue_severity', true) ?: 'moderate';
            echo '<article class="scfs-kb-issue-card"><div><span class="scfs-kb-badge scfs-kb-status-' . esc_attr($status) . '">' . esc_html(isset($this->issue_statuses()[$status]) ? $this->issue_statuses()[$status] : $status) . '</span><span class="scfs-kb-badge scfs-kb-severity-' . esc_attr($severity) . '">' . esc_html(isset($this->issue_severities()[$severity]) ? $this->issue_severities()[$severity] : $severity) . '</span></div><h4><a href="' . esc_url(get_permalink($issue)) . '">' . esc_html(get_the_title($issue)) . '</a></h4>';
            $symptom = get_post_meta($issue->ID, '_scfs_issue_symptom', true);
            if ($symptom) {
                echo '<p>' . esc_html(wp_trim_words($symptom, 24)) . '</p>';
            }
            echo '</article>';
        }
        echo '</div></aside>';
    }

    private function render_article_card($post) {
        $summary = get_post_meta($post->ID, '_scfs_kb_summary', true);
        if (!$summary) {
            $summary = has_excerpt($post) ? $post->post_excerpt : wp_trim_words(wp_strip_all_tags($post->post_content), 34);
        }
        $products = $this->term_names($post->ID, SCFS_Product_Integration::PRODUCT_TAXONOMY);
        $types = $this->term_names($post->ID, self::ARTICLE_TYPE_TAXONOMY);
        $minutes = absint(get_post_meta($post->ID, '_scfs_estimated_minutes', true));
        echo '<article class="scfs-kb-card"><div class="scfs-kb-card-meta">';
        if ($types) {
            echo '<span>' . esc_html($types[0]) . '</span>';
        }
        if ($minutes) {
            echo '<span>' . esc_html(sprintf(__('%d min', 'sustainable-catalyst-feature-suggestions'), $minutes)) . '</span>';
        }
        echo '</div><h3><a href="' . esc_url(get_permalink($post)) . '">' . esc_html(get_the_title($post)) . '</a></h3><p>' . esc_html($summary) . '</p>';
        if ($products) {
            echo '<div class="scfs-kb-card-products">' . esc_html(implode(' · ', $products)) . '</div>';
        }
        echo '<a class="scfs-kb-read" href="' . esc_url(get_permalink($post)) . '">' . esc_html__('Read article', 'sustainable-catalyst-feature-suggestions') . '<span aria-hidden="true"> →</span></a></article>';
    }

    private function render_pagination($query, $page) {
        if ($query->max_num_pages <= 1) {
            return;
        }
        echo '<nav class="scfs-kb-pagination" aria-label="' . esc_attr__('Knowledge Base pagination', 'sustainable-catalyst-feature-suggestions') . '">';
        for ($i = 1; $i <= (int) $query->max_num_pages; $i++) {
            $url = add_query_arg('scfs_kb_page', $i);
            if ($i === $page) {
                echo '<span aria-current="page">' . esc_html((string) $i) . '</span>';
            } else {
                echo '<a href="' . esc_url($url) . '">' . esc_html((string) $i) . '</a>';
            }
        }
        echo '</nav>';
    }


    /**
     * Marks public Knowledge Base views so the theme and plugin stylesheet can
     * provide a distraction-free, full-width documentation layout.
     */
    public function knowledge_base_body_classes($classes) {
        if ($this->is_public_knowledge_base_view()) {
            $classes[] = 'scfs-kb-full-width';
            $classes[] = 'scfs-kb-no-publications-sidebar';
        }
        return array_values(array_unique($classes));
    }

    /**
     * Astra reads this filter before rendering its sidebar. Returning
     * no-sidebar removes the Publications widget area at the layout source.
     */
    public function force_full_width_layout($layout) {
        return $this->is_public_knowledge_base_view() ? 'no-sidebar' : $layout;
    }

    /**
     * Prevent Astra from rendering a second H1 above the first-party
     * Knowledge Base publication masthead.
     */
    public function disable_theme_title($enabled) {
        return is_singular(self::ARTICLE_POST_TYPE) ? false : $enabled;
    }

    /**
     * Suppress Astra's author/date row on support articles. The Knowledge Base
     * renders verified version, audience, reading time, and status itself.
     */
    public function disable_theme_post_meta($meta) {
        return is_singular(self::ARTICLE_POST_TYPE) ? '' : $meta;
    }

    private function is_public_knowledge_base_view() {
        return is_post_type_archive(self::ARTICLE_POST_TYPE)
            || is_singular(self::ARTICLE_POST_TYPE);
    }

    public function template_include($template) {
        if (is_post_type_archive(self::ARTICLE_POST_TYPE)) {
            $plugin_template = plugin_dir_path(dirname(__FILE__)) . 'templates/archive-support-knowledge-base.php';
            return file_exists($plugin_template) ? $plugin_template : $template;
        }
        if (is_post_type_archive(self::ISSUE_POST_TYPE)) {
            $plugin_template = plugin_dir_path(dirname(__FILE__)) . 'templates/archive-known-issues.php';
            return file_exists($plugin_template) ? $plugin_template : $template;
        }
        return $template;
    }

    public function decorate_single_content($content) {
        if (!is_singular(array(self::ARTICLE_POST_TYPE, self::ISSUE_POST_TYPE)) || !in_the_loop() || !is_main_query()) {
            return $content;
        }
        $post_id = get_the_ID();
        $context = $this->context_markup($post_id);
        $relationships = $this->relationships_markup($post_id);
        if (get_post_type($post_id) === self::ARTICLE_POST_TYPE) {
            $summary = get_post_meta($post_id, '_scfs_kb_summary', true);
            $audience = get_post_meta($post_id, '_scfs_article_audience', true);
            $prerequisites = get_post_meta($post_id, '_scfs_article_prerequisites', true);
            $verified = get_post_meta($post_id, '_scfs_last_verified_version', true);
            $minutes = absint(get_post_meta($post_id, '_scfs_estimated_minutes', true));
            $header = '<aside class="scfs-kb-single-summary">';
            if ($summary) {
                $header .= '<p class="scfs-kb-single-lede">' . esc_html($summary) . '</p>';
            }
            $details = array();
            if ($audience) {
                $details[] = '<strong>' . esc_html__('Audience:', 'sustainable-catalyst-feature-suggestions') . '</strong> ' . esc_html($audience);
            }
            if ($minutes) {
                $details[] = '<strong>' . esc_html__('Time:', 'sustainable-catalyst-feature-suggestions') . '</strong> ' . esc_html(sprintf(__('%d minutes', 'sustainable-catalyst-feature-suggestions'), $minutes));
            }
            if ($verified) {
                $details[] = '<strong>' . esc_html__('Verified:', 'sustainable-catalyst-feature-suggestions') . '</strong> ' . esc_html($verified);
            }
            if ($details) {
                $header .= '<div class="scfs-kb-single-facts">' . implode('', array_map(function ($item) { return '<span>' . $item . '</span>'; }, $details)) . '</div>';
            }
            if ($prerequisites) {
                $header .= '<p><strong>' . esc_html__('Prerequisites:', 'sustainable-catalyst-feature-suggestions') . '</strong> ' . esc_html($prerequisites) . '</p>';
            }
            $header .= $context . '</aside>';
            return $header . $content . $relationships;
        }
        $status = get_post_meta($post_id, '_scfs_issue_status', true) ?: 'investigating';
        $severity = get_post_meta($post_id, '_scfs_issue_severity', true) ?: 'moderate';
        $symptom = get_post_meta($post_id, '_scfs_issue_symptom', true);
        $workaround = get_post_meta($post_id, '_scfs_issue_workaround', true);
        $resolution = get_post_meta($post_id, '_scfs_issue_resolution', true);
        $status_label = isset($this->issue_statuses()[$status]) ? $this->issue_statuses()[$status] : ucwords(str_replace('_', ' ', $status));
        $severity_label = isset($this->issue_severities()[$severity]) ? $this->issue_severities()[$severity] : ucwords(str_replace('_', ' ', $severity));
        $header = '<aside class="scfs-kb-single-summary scfs-kb-single-issue"><div class="scfs-kb-badge-row"><span class="scfs-kb-badge scfs-kb-status-' . esc_attr($status) . '">' . esc_html($status_label) . '</span><span class="scfs-kb-badge scfs-kb-severity-' . esc_attr($severity) . '">' . esc_html($severity_label) . '</span></div>';
        if ($symptom) {
            $header .= '<p><strong>' . esc_html__('Symptoms:', 'sustainable-catalyst-feature-suggestions') . '</strong> ' . esc_html($symptom) . '</p>';
        }
        $header .= $context . '</aside>';
        $after = '';
        if ($workaround) {
            $after .= '<section class="scfs-kb-callout"><h2>' . esc_html__('Workaround', 'sustainable-catalyst-feature-suggestions') . '</h2>' . wpautop(wp_kses_post($workaround)) . '</section>';
        }
        if ($resolution) {
            $after .= '<section class="scfs-kb-callout"><h2>' . esc_html__('Resolution', 'sustainable-catalyst-feature-suggestions') . '</h2>' . wpautop(wp_kses_post($resolution)) . '</section>';
        }
        return $header . $content . $after . $relationships;
    }

    private function context_markup($post_id) {
        $groups = array(
            __('Products', 'sustainable-catalyst-feature-suggestions') => SCFS_Product_Integration::PRODUCT_TAXONOMY,
            __('Versions', 'sustainable-catalyst-feature-suggestions') => SCFS_Product_Integration::VERSION_TAXONOMY,
            __('Components', 'sustainable-catalyst-feature-suggestions') => SCFS_Product_Integration::COMPONENT_TAXONOMY,
            __('Collections', 'sustainable-catalyst-feature-suggestions') => self::COLLECTION_TAXONOMY,
        );
        $items = array();
        foreach ($groups as $label => $taxonomy) {
            $names = $this->term_names($post_id, $taxonomy);
            if ($names) {
                $items[] = '<span><strong>' . esc_html($label) . ':</strong> ' . esc_html(implode(', ', $names)) . '</span>';
            }
        }
        return $items ? '<div class="scfs-kb-context">' . implode('', $items) . '</div>' : '';
    }

    private function relationships_markup($post_id) {
        $release_terms = get_the_terms($post_id, SCFS_Product_Integration::RELEASE_TAXONOMY);
        $public_suggestions = $this->related_suggestions($post_id, false);
        if ((!$release_terms || is_wp_error($release_terms)) && !$public_suggestions) {
            return '';
        }
        $markup = '<aside class="scfs-kb-relationships"><h2>' . esc_html__('Related product intelligence', 'sustainable-catalyst-feature-suggestions') . '</h2>';
        if ($release_terms && !is_wp_error($release_terms)) {
            $markup .= '<p><strong>' . esc_html__('Releases:', 'sustainable-catalyst-feature-suggestions') . '</strong> ' . esc_html(implode(', ', wp_list_pluck($release_terms, 'name'))) . '</p>';
        }
        if ($public_suggestions) {
            $markup .= '<h3>' . esc_html__('Related public feature ideas', 'sustainable-catalyst-feature-suggestions') . '</h3><ul>';
            foreach ($public_suggestions as $suggestion) {
                $markup .= '<li>' . esc_html($suggestion['title']) . ' — ' . esc_html($suggestion['state_label']) . '</li>';
            }
            $markup .= '</ul>';
        }
        return $markup . '</aside>';
    }

    private function term_names($post_id, $taxonomy) {
        $terms = get_the_terms($post_id, $taxonomy);
        return (!$terms || is_wp_error($terms)) ? array() : array_values(wp_list_pluck($terms, 'name'));
    }

    public function schema_record() {
        return array(
            'schema' => 'scfs-support-knowledge-base/' . self::SCHEMA_VERSION,
            'version' => self::VERSION,
            'post_types' => array(
                'support_article' => self::ARTICLE_POST_TYPE,
                'known_issue' => self::ISSUE_POST_TYPE,
            ),
            'taxonomies' => array(
                'documentation_collection' => self::COLLECTION_TAXONOMY,
                'article_type' => self::ARTICLE_TYPE_TAXONOMY,
                'product' => SCFS_Product_Integration::PRODUCT_TAXONOMY,
                'product_version' => SCFS_Product_Integration::VERSION_TAXONOMY,
                'component' => SCFS_Product_Integration::COMPONENT_TAXONOMY,
                'issue_type' => SCFS_Product_Integration::ISSUE_TAXONOMY,
                'release' => SCFS_Product_Integration::RELEASE_TAXONOMY,
            ),
            'article_templates' => array_map(function ($definition) {
                return array('label' => $definition['label'], 'article_type' => $definition['type']);
            }, $this->article_templates()),
            'known_issue_statuses' => $this->issue_statuses(),
            'known_issue_severities' => $this->issue_severities(),
            'shortcodes' => array(self::SHORTCODE, self::LEGACY_SHORTCODE),
            'privacy' => array(
                'published_content_only' => true,
                'private_suggestion_text_exposed' => false,
                'public_suggestions_require_explicit_publication' => true,
            ),
        );
    }

    public function register_rest_routes() {
        $namespace = Sustainable_Catalyst_Feature_Suggestions::REST_NAMESPACE;
        register_rest_route($namespace, '/knowledge-base/schema', array('methods' => 'GET', 'callback' => array($this, 'rest_schema'), 'permission_callback' => '__return_true'));
        register_rest_route($namespace, '/knowledge-base/articles', array('methods' => 'GET', 'callback' => array($this, 'rest_articles'), 'permission_callback' => '__return_true'));
        register_rest_route($namespace, '/knowledge-base/articles/(?P<id>\d+)', array('methods' => 'GET', 'callback' => array($this, 'rest_article'), 'permission_callback' => '__return_true'));
        register_rest_route($namespace, '/knowledge-base/known-issues', array('methods' => 'GET', 'callback' => array($this, 'rest_known_issues'), 'permission_callback' => '__return_true'));
        register_rest_route($namespace, '/knowledge-base/known-issues/(?P<id>\d+)', array('methods' => 'GET', 'callback' => array($this, 'rest_known_issue'), 'permission_callback' => '__return_true'));
        register_rest_route($namespace, '/knowledge-base/collections', array('methods' => 'GET', 'callback' => array($this, 'rest_collections'), 'permission_callback' => '__return_true'));
        register_rest_route($namespace, '/knowledge-base/templates', array('methods' => 'GET', 'callback' => array($this, 'rest_templates'), 'permission_callback' => '__return_true'));
    }

    public function rest_schema() {
        return rest_ensure_response($this->schema_record());
    }

    public function rest_articles($request) {
        $args = $this->rest_query_args($request, self::ARTICLE_POST_TYPE);
        $query = new WP_Query($args);
        $items = array();
        foreach ($query->posts as $post) {
            $items[] = $this->article_record($post, current_user_can('edit_posts'));
        }
        return $this->rest_collection_response($items, $query, $args);
    }

    public function rest_article($request) {
        $post = get_post(absint($request['id']));
        if (!$post || $post->post_type !== self::ARTICLE_POST_TYPE || ($post->post_status !== 'publish' && !current_user_can('edit_post', $post->ID))) {
            return new WP_Error('scfs_kb_not_found', __('Support article not found.', 'sustainable-catalyst-feature-suggestions'), array('status' => 404));
        }
        return rest_ensure_response($this->article_record($post, current_user_can('edit_post', $post->ID)));
    }

    public function rest_known_issues($request) {
        $args = $this->rest_query_args($request, self::ISSUE_POST_TYPE);
        $status = sanitize_key($request->get_param('status'));
        if ($status && isset($this->issue_statuses()[$status])) {
            $args['meta_query'][] = array('key' => '_scfs_issue_status', 'value' => $status);
        } elseif ($request->get_param('active')) {
            $args['meta_query'][] = array('key' => '_scfs_issue_status', 'value' => array('resolved', 'closed'), 'compare' => 'NOT IN');
        }
        $severity = sanitize_key($request->get_param('severity'));
        if ($severity && isset($this->issue_severities()[$severity])) {
            $args['meta_query'][] = array('key' => '_scfs_issue_severity', 'value' => $severity);
        }
        $query = new WP_Query($args);
        $items = array();
        foreach ($query->posts as $post) {
            $items[] = $this->known_issue_record($post, current_user_can('edit_posts'));
        }
        return $this->rest_collection_response($items, $query, $args);
    }

    public function rest_known_issue($request) {
        $post = get_post(absint($request['id']));
        if (!$post || $post->post_type !== self::ISSUE_POST_TYPE || ($post->post_status !== 'publish' && !current_user_can('edit_post', $post->ID))) {
            return new WP_Error('scfs_known_issue_not_found', __('Known issue not found.', 'sustainable-catalyst-feature-suggestions'), array('status' => 404));
        }
        return rest_ensure_response($this->known_issue_record($post, current_user_can('edit_post', $post->ID)));
    }

    public function rest_collections() {
        $terms = get_terms(array('taxonomy' => self::COLLECTION_TAXONOMY, 'hide_empty' => false, 'orderby' => 'name'));
        if (is_wp_error($terms)) {
            return $terms;
        }
        $items = array();
        foreach ($terms as $term) {
            $items[] = array(
                'id' => (int) $term->term_id,
                'name' => $term->name,
                'slug' => $term->slug,
                'description' => $term->description,
                'parent' => (int) $term->parent,
                'count' => (int) $term->count,
                'canonical_id' => get_term_meta($term->term_id, 'scfs_canonical_id', true),
                'url' => is_wp_error(get_term_link($term)) ? '' : get_term_link($term),
            );
        }
        return rest_ensure_response(array('schema' => 'scfs-documentation-collections/1.0', 'items' => $items));
    }

    public function rest_templates() {
        $items = array();
        foreach ($this->article_templates() as $key => $definition) {
            $items[] = array('id' => $key, 'label' => $definition['label'], 'article_type' => $definition['type']);
        }
        return rest_ensure_response(array('schema' => 'scfs-support-article-templates/1.0', 'items' => $items));
    }

    private function rest_query_args($request, $post_type) {
        $per_page = min(100, max(1, absint($request->get_param('per_page') ?: 20)));
        $page = max(1, absint($request->get_param('page') ?: 1));
        $args = array(
            'post_type' => $post_type,
            'post_status' => current_user_can('edit_posts') && $request->get_param('include_unpublished') ? array('publish', 'draft', 'pending', 'private') : 'publish',
            'posts_per_page' => $per_page,
            'paged' => $page,
            'orderby' => 'modified',
            'order' => 'DESC',
            's' => sanitize_text_field($request->get_param('search')),
        );
        $filters = array(
            SCFS_Product_Integration::PRODUCT_TAXONOMY => sanitize_title($request->get_param('product')),
            SCFS_Product_Integration::VERSION_TAXONOMY => sanitize_title($request->get_param('product_version')),
            SCFS_Product_Integration::COMPONENT_TAXONOMY => sanitize_title($request->get_param('component')),
            SCFS_Product_Integration::ISSUE_TAXONOMY => sanitize_title($request->get_param('issue_type')),
            SCFS_Product_Integration::RELEASE_TAXONOMY => sanitize_title($request->get_param('release')),
            self::COLLECTION_TAXONOMY => sanitize_title($request->get_param('collection')),
        );
        if ($post_type === self::ARTICLE_POST_TYPE) {
            $filters[self::ARTICLE_TYPE_TAXONOMY] = sanitize_title($request->get_param('article_type'));
        }
        $tax_query = $this->build_tax_query($filters);
        if ($tax_query) {
            $args['tax_query'] = $tax_query;
        }
        return $args;
    }

    private function rest_collection_response($items, $query, $args) {
        return rest_ensure_response(array(
            'items' => $items,
            'page' => isset($args['paged']) ? (int) $args['paged'] : 1,
            'per_page' => isset($args['posts_per_page']) ? (int) $args['posts_per_page'] : 20,
            'total' => (int) $query->found_posts,
            'total_pages' => (int) $query->max_num_pages,
        ));
    }

    public function article_record($post, $include_private = false) {
        $record = $this->base_record($post, 'support_article');
        $record['summary'] = get_post_meta($post->ID, '_scfs_kb_summary', true) ?: (has_excerpt($post) ? $post->post_excerpt : wp_trim_words(wp_strip_all_tags($post->post_content), 45));
        $record['article_template'] = get_post_meta($post->ID, '_scfs_article_template', true);
        $record['audience'] = get_post_meta($post->ID, '_scfs_article_audience', true);
        $record['prerequisites'] = get_post_meta($post->ID, '_scfs_article_prerequisites', true);
        $record['estimated_minutes'] = absint(get_post_meta($post->ID, '_scfs_estimated_minutes', true));
        $record['last_verified_version'] = get_post_meta($post->ID, '_scfs_last_verified_version', true);
        $record['article_types'] = $this->term_records($post->ID, self::ARTICLE_TYPE_TAXONOMY);
        $related = $this->related_suggestions($post->ID, $include_private);
        $record['related_suggestions'] = $related;
        $record['related_suggestion_count'] = count($related);
        if (class_exists('SCFS_Documentation_Feature_Intelligence')) {
            $intelligence = SCFS_Documentation_Feature_Intelligence::instance();
            $record['article_feedback'] = $intelligence->article_feedback_summary($post->ID);
            $record['private_support_relationship_count'] = $intelligence->relationship_count('case_article', $post->ID);
        }
        if ($include_private) {
            $record['total_related_suggestion_count'] = count((array) get_post_meta($post->ID, '_scfs_related_suggestions', true));
        }
        return $record;
    }

    public function known_issue_record($post, $include_private = false) {
        $record = $this->base_record($post, 'known_issue');
        $status = get_post_meta($post->ID, '_scfs_issue_status', true) ?: 'investigating';
        $severity = get_post_meta($post->ID, '_scfs_issue_severity', true) ?: 'moderate';
        $record['status'] = array('key' => $status, 'label' => isset($this->issue_statuses()[$status]) ? $this->issue_statuses()[$status] : $status);
        $record['severity'] = array('key' => $severity, 'label' => isset($this->issue_severities()[$severity]) ? $this->issue_severities()[$severity] : $severity);
        $record['symptom'] = get_post_meta($post->ID, '_scfs_issue_symptom', true);
        $record['workaround'] = get_post_meta($post->ID, '_scfs_issue_workaround', true);
        $record['resolution'] = get_post_meta($post->ID, '_scfs_issue_resolution', true);
        $record['first_observed'] = get_post_meta($post->ID, '_scfs_first_observed', true);
        $record['resolved_at'] = get_post_meta($post->ID, '_scfs_resolved_at', true);
        $related = $this->related_suggestions($post->ID, $include_private);
        $record['related_suggestions'] = $related;
        $record['related_suggestion_count'] = count($related);
        if ($include_private) {
            $record['total_related_suggestion_count'] = count((array) get_post_meta($post->ID, '_scfs_related_suggestions', true));
        }
        return $record;
    }

    private function base_record($post, $kind) {
        return array(
            'schema' => 'scfs-' . str_replace('_', '-', $kind) . '/1.0',
            'kind' => $kind,
            'id' => (int) $post->ID,
            'publication_status' => $post->post_status,
            'title' => $post->post_title,
            'slug' => $post->post_name,
            'url' => get_permalink($post),
            'excerpt' => has_excerpt($post) ? $post->post_excerpt : wp_trim_words(wp_strip_all_tags($post->post_content), 45),
            'content' => apply_filters('the_content', $post->post_content),
            'published_at' => get_post_time('c', true, $post),
            'modified_at' => get_post_modified_time('c', true, $post),
            'product_context' => SCFS_Product_Integration::instance()->product_context($post->ID),
            'collections' => $this->term_records($post->ID, self::COLLECTION_TAXONOMY),
            'releases' => $this->term_records($post->ID, SCFS_Product_Integration::RELEASE_TAXONOMY),
            'schema_version' => get_post_meta($post->ID, '_scfs_kb_schema_version', true) ?: self::SCHEMA_VERSION,
        );
    }

    private function term_records($post_id, $taxonomy) {
        $terms = get_the_terms($post_id, $taxonomy);
        if (!$terms || is_wp_error($terms)) {
            return array();
        }
        $records = array();
        foreach ($terms as $term) {
            $records[] = array(
                'id' => (int) $term->term_id,
                'name' => $term->name,
                'slug' => $term->slug,
                'canonical_id' => get_term_meta($term->term_id, 'scfs_canonical_id', true),
            );
        }
        return $records;
    }

    private function related_suggestions($post_id, $include_private) {
        $records = array();
        foreach ((array) get_post_meta($post_id, '_scfs_related_suggestions', true) as $id) {
            $id = absint($id);
            if (!$id || get_post_type($id) !== Sustainable_Catalyst_Feature_Suggestions::POST_TYPE) {
                continue;
            }
            $is_public = get_post_meta($id, '_scfs_public_visibility', true) === '1' && !absint(get_post_meta($id, '_scfs_canonical_idea_id', true));
            if (!$include_private && !$is_public) {
                continue;
            }
            $state = get_post_meta($id, '_scfs_public_state', true) ?: 'under_review';
            $states = class_exists('SCFS_Public_Ideas') ? SCFS_Public_Ideas::instance()->states() : array();
            $record = array(
                'id' => $id,
                'title' => get_the_title($id),
                'public' => $is_public,
                'state' => $state,
                'state_label' => isset($states[$state]) ? $states[$state] : ucwords(str_replace('_', ' ', $state)),
            );
            if ($include_private) {
                $record['review_status'] = get_post_meta($id, '_scfs_review_status', true);
                $record['edit_url'] = get_edit_post_link($id, 'raw');
            }
            $records[] = $record;
        }
        return $records;
    }
}
