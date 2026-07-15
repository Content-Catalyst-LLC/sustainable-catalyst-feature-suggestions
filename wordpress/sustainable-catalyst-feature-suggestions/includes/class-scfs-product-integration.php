<?php
/**
 * Product taxonomy, migration, analytics context, and Contact and Engagement handoff contract.
 *
 * @package Sustainable_Catalyst_Feature_Suggestions
 */

if (!defined('ABSPATH')) {
    exit;
}

final class SCFS_Product_Integration {
    const VERSION = '4.1.0';
    const TAXONOMY_SCHEMA_VERSION = '1.0';
    const HANDOFF_SCHEMA_VERSION = '1.0';
    const MIGRATION_OPTION = 'scfs_product_taxonomy_migration';
    const SEED_OPTION = 'scfs_product_taxonomy_seeded_version';
    const TAXONOMY_SCHEMA_OPTION = 'scfs_product_taxonomy_schema_version';

    const PRODUCT_TAXONOMY = 'scfs_product';
    const VERSION_TAXONOMY = 'scfs_product_version';
    const COMPONENT_TAXONOMY = 'scfs_component';
    const ISSUE_TAXONOMY = 'scfs_issue_type';
    const RELEASE_TAXONOMY = 'scfs_release';

    private static $instance = null;

    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('init', array($this, 'register_taxonomies'), 8);
        add_action('init', array($this, 'register_term_meta'), 9);
        add_action('init', array($this, 'seed_foundation_terms'), 20);
        add_action('admin_menu', array($this, 'register_admin_page'), 25);
        add_action('admin_post_scfs_run_product_migration', array($this, 'run_product_migration_action'));
        add_action('admin_init', array($this, 'maybe_schedule_upgrade'), 1);
        add_action('admin_init', array($this, 'maybe_run_background_migration'), 20);
        add_action('rest_api_init', array($this, 'register_rest_routes'));
        add_action('save_post_' . Sustainable_Catalyst_Feature_Suggestions::POST_TYPE, array($this, 'mark_taxonomy_schema'), 30, 2);

        if (defined('WP_CLI') && WP_CLI && class_exists('WP_CLI')) {
            WP_CLI::add_command('scfs migrate-product-taxonomies', array($this, 'cli_migrate'));
        }
    }

    public static function activate() {
        $instance = self::instance();
        $instance->register_taxonomies();
        $instance->register_term_meta();
        $instance->seed_foundation_terms(true);
        update_option(self::TAXONOMY_SCHEMA_OPTION, self::TAXONOMY_SCHEMA_VERSION, false);
        $state = get_option(self::MIGRATION_OPTION, array());
        if (!is_array($state) || empty($state['status']) || $state['status'] === 'complete') {
            update_option(self::MIGRATION_OPTION, array(
                'status' => 'pending',
                'page' => 1,
                'processed' => 0,
                'assigned_products' => 0,
                'assigned_components' => 0,
                'assigned_issue_types' => 0,
                'assigned_versions' => 0,
                'assigned_releases' => 0,
                'unmapped' => 0,
                'errors' => array(),
                'started_at' => '',
                'completed_at' => '',
                'schema_version' => self::TAXONOMY_SCHEMA_VERSION,
            ), false);
        }
    }

    public function taxonomy_definitions() {
        return array(
            self::PRODUCT_TAXONOMY => array(
                'singular' => __('Product', 'sustainable-catalyst-feature-suggestions'),
                'plural' => __('Products', 'sustainable-catalyst-feature-suggestions'),
                'hierarchical' => true,
                'rest_base' => 'scfs-products',
                'description' => __('Canonical Sustainable Catalyst products and platform areas.', 'sustainable-catalyst-feature-suggestions'),
            ),
            self::VERSION_TAXONOMY => array(
                'singular' => __('Product Version', 'sustainable-catalyst-feature-suggestions'),
                'plural' => __('Product Versions', 'sustainable-catalyst-feature-suggestions'),
                'hierarchical' => false,
                'rest_base' => 'scfs-product-versions',
                'description' => __('Affected or reported product versions.', 'sustainable-catalyst-feature-suggestions'),
            ),
            self::COMPONENT_TAXONOMY => array(
                'singular' => __('Component', 'sustainable-catalyst-feature-suggestions'),
                'plural' => __('Components', 'sustainable-catalyst-feature-suggestions'),
                'hierarchical' => true,
                'rest_base' => 'scfs-components',
                'description' => __('Reusable product and platform components.', 'sustainable-catalyst-feature-suggestions'),
            ),
            self::ISSUE_TAXONOMY => array(
                'singular' => __('Issue Type', 'sustainable-catalyst-feature-suggestions'),
                'plural' => __('Issue Types', 'sustainable-catalyst-feature-suggestions'),
                'hierarchical' => true,
                'rest_base' => 'scfs-issue-types',
                'description' => __('Canonical issue, request, and support classifications.', 'sustainable-catalyst-feature-suggestions'),
            ),
            self::RELEASE_TAXONOMY => array(
                'singular' => __('Release', 'sustainable-catalyst-feature-suggestions'),
                'plural' => __('Releases', 'sustainable-catalyst-feature-suggestions'),
                'hierarchical' => false,
                'rest_base' => 'scfs-releases',
                'description' => __('Planned, active, and completed product releases.', 'sustainable-catalyst-feature-suggestions'),
            ),
        );
    }

    public function register_taxonomies() {
        $object_types = apply_filters('scfs_product_taxonomy_object_types', array(
            Sustainable_Catalyst_Feature_Suggestions::POST_TYPE,
            'sc_support_article',
            'sc_known_issue',
            'sc_release_record',
        ));
        $object_types = array_values(array_unique(array_filter(array_map('sanitize_key', (array) $object_types))));

        foreach ($this->taxonomy_definitions() as $taxonomy => $definition) {
            $labels = array(
                'name' => $definition['plural'],
                'singular_name' => $definition['singular'],
                'search_items' => sprintf(__('Search %s', 'sustainable-catalyst-feature-suggestions'), $definition['plural']),
                'all_items' => sprintf(__('All %s', 'sustainable-catalyst-feature-suggestions'), $definition['plural']),
                'edit_item' => sprintf(__('Edit %s', 'sustainable-catalyst-feature-suggestions'), $definition['singular']),
                'update_item' => sprintf(__('Update %s', 'sustainable-catalyst-feature-suggestions'), $definition['singular']),
                'add_new_item' => sprintf(__('Add New %s', 'sustainable-catalyst-feature-suggestions'), $definition['singular']),
                'new_item_name' => sprintf(__('New %s Name', 'sustainable-catalyst-feature-suggestions'), $definition['singular']),
                'menu_name' => $definition['plural'],
            );
            register_taxonomy($taxonomy, $object_types, array(
                'labels' => $labels,
                'description' => $definition['description'],
                'public' => false,
                'publicly_queryable' => false,
                'show_ui' => true,
                'show_admin_column' => false,
                'show_in_nav_menus' => false,
                'show_tagcloud' => false,
                'show_in_quick_edit' => true,
                'hierarchical' => (bool) $definition['hierarchical'],
                'show_in_rest' => true,
                'rest_base' => $definition['rest_base'],
                'rewrite' => false,
                'capabilities' => array(
                    'manage_terms' => 'edit_posts',
                    'edit_terms' => 'edit_posts',
                    'delete_terms' => 'manage_options',
                    'assign_terms' => 'edit_posts',
                ),
            ));
        }
    }

    public function register_term_meta() {
        foreach (array_keys($this->taxonomy_definitions()) as $taxonomy) {
            register_term_meta($taxonomy, 'scfs_canonical_id', array(
                'type' => 'string',
                'single' => true,
                'show_in_rest' => true,
                'sanitize_callback' => 'sanitize_text_field',
                'auth_callback' => function () {
                    return current_user_can('edit_posts');
                },
            ));
            register_term_meta($taxonomy, 'scfs_status', array(
                'type' => 'string',
                'single' => true,
                'show_in_rest' => true,
                'sanitize_callback' => 'sanitize_key',
                'auth_callback' => function () {
                    return current_user_can('edit_posts');
                },
            ));
            register_term_meta($taxonomy, 'scfs_product_ids', array(
                'type' => 'array',
                'single' => true,
                'show_in_rest' => array(
                    'schema' => array(
                        'type' => 'array',
                        'items' => array('type' => 'integer'),
                    ),
                ),
                'sanitize_callback' => array($this, 'sanitize_id_array'),
                'auth_callback' => function () {
                    return current_user_can('edit_posts');
                },
            ));
        }
        register_term_meta(self::RELEASE_TAXONOMY, 'scfs_release_date', array(
            'type' => 'string',
            'single' => true,
            'show_in_rest' => true,
            'sanitize_callback' => 'sanitize_text_field',
            'auth_callback' => function () {
                return current_user_can('edit_posts');
            },
        ));
    }

    public function sanitize_id_array($value) {
        return array_values(array_unique(array_filter(array_map('absint', (array) $value))));
    }

    public function seed_foundation_terms($force = false) {
        if (!$force && get_option(self::SEED_OPTION) === self::VERSION) {
            return;
        }
        $products = array(
            'sustainable-catalyst-platform' => 'Sustainable Catalyst Platform',
            'platform-core' => 'Platform Core',
            'research-librarian' => 'Research Librarian',
            'research-lab' => 'Research Lab',
            'knowledge-library' => 'Knowledge Library',
            'workbench' => 'Workbench',
            'decision-studio' => 'Decision Studio',
            'site-intelligence' => 'Site Intelligence',
            'feature-suggestions' => 'Feature Suggestions',
            'contact-and-engagement' => 'Contact and Engagement',
        );
        $components = array(
            'frontend-interface' => 'Frontend and Interface',
            'wordpress-admin' => 'WordPress Administration',
            'rest-api' => 'REST API',
            'python-backend' => 'Python Backend',
            'ai-triage' => 'AI Triage',
            'analytics-intelligence' => 'Analytics and Intelligence',
            'forms-surveys' => 'Forms and Surveys',
            'public-ideas-voting' => 'Public Ideas and Voting',
            'search-discovery' => 'Search and Discovery',
            'documentation' => 'Documentation',
            'exports-reports' => 'Exports and Reports',
            'integrations-handoffs' => 'Integrations and Handoffs',
            'data-storage-migration' => 'Data Storage and Migration',
            'accessibility-usability' => 'Accessibility and Usability',
            'security-privacy' => 'Security and Privacy',
            'performance-reliability' => 'Performance and Reliability',
        );
        $issues = array(
            'feature-request' => 'Feature Request',
            'bug-defect' => 'Bug or Defect',
            'documentation-gap' => 'Documentation Gap',
            'accessibility-usability' => 'Accessibility or Usability',
            'integration-request' => 'Integration Request',
            'data-export-request' => 'Data or Export Request',
            'performance-reliability' => 'Performance or Reliability',
            'security-privacy' => 'Security or Privacy',
            'support-question' => 'Support Question',
            'other' => 'Other',
        );
        $this->seed_terms(self::PRODUCT_TAXONOMY, $products);
        $this->seed_terms(self::COMPONENT_TAXONOMY, $components);
        $this->seed_terms(self::ISSUE_TAXONOMY, $issues);
        update_option(self::SEED_OPTION, self::VERSION, false);
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
            if ($term && !is_wp_error($term)) {
                if (!get_term_meta($term->term_id, 'scfs_canonical_id', true)) {
                    update_term_meta($term->term_id, 'scfs_canonical_id', 'scfs:' . $taxonomy . ':' . $slug);
                }
                if (!get_term_meta($term->term_id, 'scfs_status', true)) {
                    update_term_meta($term->term_id, 'scfs_status', 'active');
                }
            }
        }
    }

    public function render_public_context_fields($values = array()) {
        $values = wp_parse_args($values, array(
            'product' => '',
            'product_version' => '',
            'component' => '',
            'issue_type' => '',
        ));
        $fields = array(
            'product' => array(self::PRODUCT_TAXONOMY, __('Product or platform area', 'sustainable-catalyst-feature-suggestions'), __('Choose a product', 'sustainable-catalyst-feature-suggestions')),
            'product_version' => array(self::VERSION_TAXONOMY, __('Affected version', 'sustainable-catalyst-feature-suggestions'), __('Version unknown or not listed', 'sustainable-catalyst-feature-suggestions')),
            'component' => array(self::COMPONENT_TAXONOMY, __('Component', 'sustainable-catalyst-feature-suggestions'), __('Choose a component', 'sustainable-catalyst-feature-suggestions')),
            'issue_type' => array(self::ISSUE_TAXONOMY, __('Issue or request type', 'sustainable-catalyst-feature-suggestions'), __('Choose an issue type', 'sustainable-catalyst-feature-suggestions')),
        );
        echo '<fieldset class="scfs-product-context"><legend>' . esc_html__('Product context', 'sustainable-catalyst-feature-suggestions') . '</legend>';
        echo '<p class="scfs-product-context-intro">' . esc_html__('These fields connect the suggestion to shared product, component, issue, and release intelligence.', 'sustainable-catalyst-feature-suggestions') . '</p>';
        echo '<div class="scfs-grid scfs-grid-two">';
        foreach ($fields as $key => $field) {
            $terms = get_terms(array('taxonomy' => $field[0], 'hide_empty' => false, 'orderby' => 'name', 'order' => 'ASC'));
            if (is_wp_error($terms)) {
                $terms = array();
            }
            echo '<p class="scfs-field"><label for="scfs_' . esc_attr($key) . '">' . esc_html($field[1]) . '</label>';
            echo '<select id="scfs_' . esc_attr($key) . '" name="scfs_' . esc_attr($key) . '">';
            echo '<option value="">' . esc_html($field[2]) . '</option>';
            foreach ($terms as $term) {
                echo '<option value="' . esc_attr($term->slug) . '" ' . selected($values[$key], $term->slug, false) . '>' . esc_html($term->name) . '</option>';
            }
            echo '</select></p>';
        }
        echo '</div></fieldset>';
    }

    public function assign_context($post_id, $context, $create_missing = false) {
        $map = array(
            'product' => self::PRODUCT_TAXONOMY,
            'products' => self::PRODUCT_TAXONOMY,
            'product_version' => self::VERSION_TAXONOMY,
            'product_versions' => self::VERSION_TAXONOMY,
            'component' => self::COMPONENT_TAXONOMY,
            'components' => self::COMPONENT_TAXONOMY,
            'issue_type' => self::ISSUE_TAXONOMY,
            'issue_types' => self::ISSUE_TAXONOMY,
            'release' => self::RELEASE_TAXONOMY,
            'releases' => self::RELEASE_TAXONOMY,
        );
        $assigned = array();
        foreach ($map as $key => $taxonomy) {
            if (!isset($context[$key]) || $context[$key] === '' || $context[$key] === array()) {
                continue;
            }
            $values = is_array($context[$key]) ? $context[$key] : array($context[$key]);
            $term_ids = array();
            foreach ($values as $value) {
                $term = false;
                if (is_numeric($value)) {
                    $term = get_term(absint($value), $taxonomy);
                } else {
                    $slug = sanitize_title((string) $value);
                    $term = get_term_by('slug', $slug, $taxonomy);
                    if (!$term) {
                        $term = get_term_by('name', sanitize_text_field((string) $value), $taxonomy);
                    }
                    if (!$term && $create_missing && in_array($taxonomy, array(self::VERSION_TAXONOMY, self::RELEASE_TAXONOMY), true)) {
                        $created = wp_insert_term(sanitize_text_field((string) $value), $taxonomy, array('slug' => $slug));
                        if (!is_wp_error($created)) {
                            $term = get_term($created['term_id'], $taxonomy);
                        }
                    }
                }
                if ($term && !is_wp_error($term)) {
                    $term_ids[] = (int) $term->term_id;
                }
            }
            if ($term_ids) {
                $term_ids = array_values(array_unique($term_ids));
                wp_set_object_terms($post_id, $term_ids, $taxonomy, false);
                $assigned[$taxonomy] = $term_ids;
            }
        }
        if ($assigned) {
            update_post_meta($post_id, '_scfs_taxonomy_schema_version', self::TAXONOMY_SCHEMA_VERSION);
            update_post_meta($post_id, '_scfs_taxonomy_updated_at', gmdate('c'));
        }
        return $assigned;
    }

    public function product_context($post_id) {
        $context = array();
        foreach ($this->taxonomy_definitions() as $taxonomy => $definition) {
            $terms = wp_get_object_terms($post_id, $taxonomy, array('orderby' => 'name', 'order' => 'ASC'));
            if (is_wp_error($terms)) {
                $terms = array();
            }
            $key = array_search($taxonomy, array(
                'products' => self::PRODUCT_TAXONOMY,
                'versions' => self::VERSION_TAXONOMY,
                'components' => self::COMPONENT_TAXONOMY,
                'issue_types' => self::ISSUE_TAXONOMY,
                'releases' => self::RELEASE_TAXONOMY,
            ), true);
            $context[$key] = array_map(array($this, 'term_record'), $terms);
        }
        $context['schema_version'] = self::TAXONOMY_SCHEMA_VERSION;
        return $context;
    }

    public function term_record($term) {
        return array(
            'id' => (int) $term->term_id,
            'canonical_id' => get_term_meta($term->term_id, 'scfs_canonical_id', true),
            'taxonomy' => $term->taxonomy,
            'slug' => $term->slug,
            'name' => $term->name,
            'description' => $term->description,
            'parent_id' => (int) $term->parent,
            'status' => get_term_meta($term->term_id, 'scfs_status', true) ?: 'active',
            'product_ids' => $this->sanitize_id_array(get_term_meta($term->term_id, 'scfs_product_ids', true)),
            'count' => (int) $term->count,
        );
    }

    public function flat_term_names($post_id, $taxonomy) {
        $terms = wp_get_object_terms($post_id, $taxonomy, array('fields' => 'names'));
        return is_wp_error($terms) ? array() : array_values($terms);
    }

    public function mark_taxonomy_schema($post_id, $post) {
        if (!$post || $post->post_type !== Sustainable_Catalyst_Feature_Suggestions::POST_TYPE || wp_is_post_revision($post_id)) {
            return;
        }
        $has_context = false;
        foreach (array_keys($this->taxonomy_definitions()) as $taxonomy) {
            if (has_term('', $taxonomy, $post_id)) {
                $has_context = true;
                break;
            }
        }
        if ($has_context) {
            update_post_meta($post_id, '_scfs_taxonomy_schema_version', self::TAXONOMY_SCHEMA_VERSION);
            update_post_meta($post_id, '_scfs_taxonomy_updated_at', gmdate('c'));
        }
    }

    public function maybe_schedule_upgrade() {
        $schema = get_option(self::TAXONOMY_SCHEMA_OPTION, '');
        if ($schema === self::TAXONOMY_SCHEMA_VERSION) {
            return;
        }
        update_option(self::TAXONOMY_SCHEMA_OPTION, self::TAXONOMY_SCHEMA_VERSION, false);
        $state = get_option(self::MIGRATION_OPTION, array());
        if (!is_array($state) || empty($state['status']) || $state['status'] === 'complete') {
            update_option(self::MIGRATION_OPTION, array(
                'status' => 'pending',
                'page' => 1,
                'processed' => 0,
                'assigned_products' => 0,
                'assigned_components' => 0,
                'assigned_issue_types' => 0,
                'assigned_versions' => 0,
                'assigned_releases' => 0,
                'unmapped' => 0,
                'errors' => array(),
                'started_at' => '',
                'completed_at' => '',
                'schema_version' => self::TAXONOMY_SCHEMA_VERSION,
            ), false);
        }
    }

    public function maybe_run_background_migration() {
        if (!current_user_can('edit_posts')) {
            return;
        }
        $state = get_option(self::MIGRATION_OPTION, array());
        if (!is_array($state) || !in_array(isset($state['status']) ? $state['status'] : '', array('pending', 'running'), true)) {
            return;
        }
        $this->migrate_batch(50);
    }

    public function migrate_batch($batch_size = 50) {
        $state = wp_parse_args(get_option(self::MIGRATION_OPTION, array()), array(
            'status' => 'pending',
            'page' => 1,
            'processed' => 0,
            'assigned_products' => 0,
            'assigned_components' => 0,
            'assigned_issue_types' => 0,
            'assigned_versions' => 0,
            'assigned_releases' => 0,
            'unmapped' => 0,
            'errors' => array(),
            'started_at' => '',
            'completed_at' => '',
            'schema_version' => self::TAXONOMY_SCHEMA_VERSION,
        ));
        if ($state['started_at'] === '') {
            $state['started_at'] = gmdate('c');
        }
        $state['status'] = 'running';
        $query = new WP_Query(array(
            'post_type' => Sustainable_Catalyst_Feature_Suggestions::POST_TYPE,
            'post_status' => array('publish', 'pending', 'draft', 'private', 'trash'),
            'posts_per_page' => max(1, min(500, absint($batch_size))),
            'paged' => max(1, absint($state['page'])),
            'orderby' => 'ID',
            'order' => 'ASC',
            'fields' => 'ids',
            'no_found_rows' => false,
        ));
        foreach ($query->posts as $post_id) {
            $result = $this->migrate_suggestion($post_id);
            $state['processed']++;
            foreach (array('assigned_products', 'assigned_components', 'assigned_issue_types', 'assigned_versions', 'assigned_releases', 'unmapped') as $key) {
                $state[$key] += isset($result[$key]) ? absint($result[$key]) : 0;
            }
            if (!empty($result['error'])) {
                $state['errors'][] = array('post_id' => (int) $post_id, 'message' => sanitize_text_field($result['error']));
                $state['errors'] = array_slice($state['errors'], -20);
            }
        }
        if ($state['page'] >= max(1, (int) $query->max_num_pages)) {
            $state['status'] = 'complete';
            $state['completed_at'] = gmdate('c');
            $state['page'] = 1;
        } else {
            $state['page']++;
        }
        update_option(self::MIGRATION_OPTION, $state, false);
        return $state;
    }

    public function migrate_all($batch_size = 200) {
        update_option(self::MIGRATION_OPTION, array(
            'status' => 'pending',
            'page' => 1,
            'processed' => 0,
            'assigned_products' => 0,
            'assigned_components' => 0,
            'assigned_issue_types' => 0,
            'assigned_versions' => 0,
            'assigned_releases' => 0,
            'unmapped' => 0,
            'errors' => array(),
            'started_at' => gmdate('c'),
            'completed_at' => '',
            'schema_version' => self::TAXONOMY_SCHEMA_VERSION,
        ), false);
        $guard = 0;
        do {
            $state = $this->migrate_batch($batch_size);
            $guard++;
        } while ($state['status'] !== 'complete' && $guard < 100);
        return $state;
    }

    public function migrate_suggestion($post_id) {
        $result = array(
            'assigned_products' => 0,
            'assigned_components' => 0,
            'assigned_issue_types' => 0,
            'assigned_versions' => 0,
            'assigned_releases' => 0,
            'unmapped' => 0,
            'error' => '',
        );
        $post = get_post($post_id);
        if (!$post || $post->post_type !== Sustainable_Catalyst_Feature_Suggestions::POST_TYPE) {
            $result['error'] = 'Invalid feature suggestion record.';
            return $result;
        }
        $analysis = get_post_meta($post_id, '_scfs_ai_analysis', true);
        $analysis = is_array($analysis) ? $analysis : array();
        $text = strtolower(implode(' ', array_filter(array(
            $post->post_title,
            get_post_meta($post_id, '_scfs_category', true),
            get_post_meta($post_id, '_scfs_roadmap_area', true),
            get_post_meta($post_id, '_scfs_problem', true),
            get_post_meta($post_id, '_scfs_suggestion', true),
            get_post_meta($post_id, '_scfs_implementation_notes', true),
            isset($analysis['platform_area']) ? $analysis['platform_area'] : '',
            isset($analysis['feature_type']) ? $analysis['feature_type'] : '',
            isset($analysis['suggested_roadmap_destination']) ? $analysis['suggested_roadmap_destination'] : '',
        ))));

        if (!has_term('', self::PRODUCT_TAXONOMY, $post_id)) {
            $product = $this->infer_slug($text, $this->product_patterns());
            if ($product) {
                $assigned = $this->assign_context($post_id, array('product' => $product));
                $result['assigned_products'] += !empty($assigned[self::PRODUCT_TAXONOMY]) ? 1 : 0;
            }
        }
        if (!has_term('', self::COMPONENT_TAXONOMY, $post_id)) {
            $component = $this->infer_slug($text, $this->component_patterns());
            if ($component) {
                $assigned = $this->assign_context($post_id, array('component' => $component));
                $result['assigned_components'] += !empty($assigned[self::COMPONENT_TAXONOMY]) ? 1 : 0;
            }
        }
        if (!has_term('', self::ISSUE_TAXONOMY, $post_id)) {
            $issue = $this->infer_slug($text, $this->issue_patterns());
            if (!$issue) {
                $issue = 'feature-request';
            }
            $assigned = $this->assign_context($post_id, array('issue_type' => $issue));
            $result['assigned_issue_types'] += !empty($assigned[self::ISSUE_TAXONOMY]) ? 1 : 0;
        }
        if (!has_term('', self::VERSION_TAXONOMY, $post_id)) {
            $version = get_post_meta($post_id, '_scfs_product_version', true);
            if (!$version && isset($analysis['product_version'])) {
                $version = sanitize_text_field($analysis['product_version']);
            }
            if ($version) {
                $assigned = $this->assign_context($post_id, array('product_version' => $version), true);
                $result['assigned_versions'] += !empty($assigned[self::VERSION_TAXONOMY]) ? 1 : 0;
            }
        }
        if (!has_term('', self::RELEASE_TAXONOMY, $post_id)) {
            $release = get_post_meta($post_id, '_scfs_target_release', true);
            if ($release) {
                $assigned = $this->assign_context($post_id, array('release' => $release), true);
                $result['assigned_releases'] += !empty($assigned[self::RELEASE_TAXONOMY]) ? 1 : 0;
            }
        }
        if (!has_term('', self::PRODUCT_TAXONOMY, $post_id)) {
            $result['unmapped'] = 1;
        }
        update_post_meta($post_id, '_scfs_taxonomy_migrated_at', gmdate('c'));
        update_post_meta($post_id, '_scfs_taxonomy_schema_version', self::TAXONOMY_SCHEMA_VERSION);
        return $result;
    }

    private function infer_slug($text, $patterns) {
        foreach ($patterns as $slug => $needles) {
            foreach ((array) $needles as $needle) {
                if ($needle !== '' && strpos($text, strtolower($needle)) !== false) {
                    return $slug;
                }
            }
        }
        return '';
    }

    private function product_patterns() {
        return array(
            'feature-suggestions' => array('feature suggestion', 'public ideas', 'voting', 'survey'),
            'contact-and-engagement' => array('contact and engagement', 'engagement intake', 'support case', 'advisory inquiry'),
            'research-librarian' => array('research librarian', 'research guidance'),
            'research-lab' => array('research lab', 'laboratory', 'scientific lab'),
            'knowledge-library' => array('knowledge library', 'research library', 'article map', 'library suggestion'),
            'workbench' => array('workbench', 'calculator', 'symbolic math', 'graph studio'),
            'decision-studio' => array('decision studio', 'decision packet', 'decision brief'),
            'site-intelligence' => array('site intelligence', 'country intelligence', 'earth observation', 'dashboard'),
            'platform-core' => array('platform core', 'core gateway', 'knowledge graph', 'evidence ledger'),
            'sustainable-catalyst-platform' => array('platform', 'website', 'sitewide', 'site-wide'),
        );
    }

    private function component_patterns() {
        return array(
            'accessibility-usability' => array('accessibility', 'usability', 'mobile', 'responsive', 'interface'),
            'security-privacy' => array('security', 'privacy', 'secret', 'authentication', 'permission'),
            'performance-reliability' => array('performance', 'reliability', 'crash', 'fatal', 'timeout', 'unavailable', 'broken'),
            'rest-api' => array('rest api', 'endpoint', 'api'),
            'python-backend' => array('python', 'fastapi', 'backend', 'render'),
            'ai-triage' => array('ai triage', 'classification', 'gemini', 'model'),
            'analytics-intelligence' => array('analytics', 'intelligence', 'score', 'metric'),
            'forms-surveys' => array('form', 'survey', 'questionnaire'),
            'public-ideas-voting' => array('public idea', 'vote', 'voting', 'support count'),
            'search-discovery' => array('search', 'filter', 'discovery'),
            'documentation' => array('documentation', 'knowledge base', 'help article', 'docs'),
            'exports-reports' => array('export', 'csv', 'pdf', 'report'),
            'integrations-handoffs' => array('integration', 'handoff', 'webhook'),
            'data-storage-migration' => array('migration', 'database', 'storage', 'schema', 'taxonomy'),
            'wordpress-admin' => array('wordpress admin', 'settings page', 'admin'),
            'frontend-interface' => array('page', 'frontend', 'shortcode', 'layout', 'button'),
        );
    }

    private function issue_patterns() {
        return array(
            'bug-defect' => array('bug report', 'bug', 'broken', 'error', 'fatal', 'crash', 'not working'),
            'documentation-gap' => array('documentation improvement', 'documentation', 'docs', 'knowledge base', 'help article'),
            'accessibility-usability' => array('accessibility', 'usability', 'mobile', 'responsive'),
            'integration-request' => array('integration', 'webhook', 'handoff', 'connector'),
            'data-export-request' => array('data or export', 'export', 'csv', 'download'),
            'performance-reliability' => array('performance', 'reliability', 'slow', 'timeout', 'unavailable'),
            'security-privacy' => array('security', 'privacy', 'permission', 'secret'),
            'support-question' => array('support question', 'how do i', 'help me', 'cannot find'),
            'feature-request' => array('new platform module', 'feature request', 'improvement', 'add ', 'would like'),
        );
    }

    public function taxonomy_schema() {
        $taxonomies = array();
        foreach ($this->taxonomy_definitions() as $taxonomy => $definition) {
            $taxonomies[$taxonomy] = array(
                'label' => $definition['plural'],
                'hierarchical' => (bool) $definition['hierarchical'],
                'rest_base' => $definition['rest_base'],
                'term_meta' => array('scfs_canonical_id', 'scfs_status', 'scfs_product_ids'),
            );
        }
        return array(
            'schema' => 'scfs-product-taxonomy/' . self::TAXONOMY_SCHEMA_VERSION,
            'version' => self::VERSION,
            'object_types' => apply_filters('scfs_product_taxonomy_object_types', array(
                Sustainable_Catalyst_Feature_Suggestions::POST_TYPE,
                'sc_support_article',
                'sc_known_issue',
                'sc_release_record',
            )),
            'taxonomies' => $taxonomies,
            'relationship_rules' => array(
                'product' => 'Canonical product or platform area.',
                'product_version' => 'Version reported by the submitter or assigned by a reviewer.',
                'component' => 'Reusable functional or technical component.',
                'issue_type' => 'Canonical request, issue, or support classification.',
                'release' => 'Target, planned, current, or completed release.',
            ),
        );
    }

    public function handoff_schema() {
        return array(
            'schema' => 'sc-contact-engagement-handoff/' . self::HANDOFF_SCHEMA_VERSION,
            'direction' => 'feature_suggestions_to_contact_and_engagement',
            'purpose' => 'Create or enrich a private product-support case only after an authorized human action.',
            'required_fields' => array(
                'schema', 'handoff_type', 'source', 'product_context', 'case', 'privacy', 'routing',
            ),
            'privacy_boundary' => array(
                'classification' => 'private',
                'public_payloads_must_exclude' => array('name', 'email', 'free_text', 'attachments', 'ip_hash'),
                'authorized_handoff_may_include' => array('requester_name', 'requester_email', 'follow_up_consent', 'problem', 'suggestion'),
            ),
            'human_review_required' => true,
            'automatic_case_creation' => false,
        );
    }

    public function build_contact_handoff($post_id) {
        $post = get_post($post_id);
        if (!$post || $post->post_type !== Sustainable_Catalyst_Feature_Suggestions::POST_TYPE) {
            return new WP_Error('scfs_not_found', __('Feature suggestion not found.', 'sustainable-catalyst-feature-suggestions'));
        }
        $uuid = get_post_meta($post_id, '_scfs_submission_uuid', true);
        if (!$uuid) {
            $uuid = wp_generate_uuid4();
            update_post_meta($post_id, '_scfs_submission_uuid', $uuid);
        }
        $name = get_post_meta($post_id, '_scfs_name', true);
        $email = get_post_meta($post_id, '_scfs_email', true);
        $follow_up = get_post_meta($post_id, '_scfs_follow_up', true) === '1';
        $payload = array(
            'schema' => 'sc-contact-engagement-handoff/' . self::HANDOFF_SCHEMA_VERSION,
            'handoff_type' => 'product_support',
            'handoff_id' => 'scfs:' . $uuid,
            'generated_at' => gmdate('c'),
            'source' => array(
                'system' => 'sustainable-catalyst-feature-suggestions',
                'version' => self::VERSION,
                'record_type' => 'feature_suggestion',
                'record_id' => (int) $post_id,
                'record_uuid' => $uuid,
                'correlation_id' => get_post_meta($post_id, '_scfs_correlation_id', true),
            ),
            'product_context' => $this->product_context($post_id),
            'requester' => array(
                'name' => $name,
                'email' => $email,
                'follow_up_allowed' => $follow_up,
            ),
            'case' => array(
                'subject' => $post->post_title,
                'problem' => get_post_meta($post_id, '_scfs_problem', true),
                'requested_change' => get_post_meta($post_id, '_scfs_suggestion', true),
                'success_criteria' => get_post_meta($post_id, '_scfs_success_criteria', true),
                'implementation_context' => get_post_meta($post_id, '_scfs_implementation_notes', true),
                'relevant_url' => get_post_meta($post_id, '_scfs_relevant_url', true),
                'priority' => get_post_meta($post_id, '_scfs_priority', true),
                'review_status' => get_post_meta($post_id, '_scfs_review_status', true),
            ),
            'relationships' => array(
                'feature_suggestion_id' => (int) $post_id,
                'github_issue_url' => get_post_meta($post_id, '_scfs_github_issue_url', true),
                'roadmap_area' => get_post_meta($post_id, '_scfs_roadmap_area', true),
                'target_release' => get_post_meta($post_id, '_scfs_target_release', true),
            ),
            'privacy' => array(
                'classification' => 'private',
                'contains_personal_data' => (bool) ($name || $email),
                'follow_up_consent' => $follow_up,
                'attachments_included' => false,
            ),
            'routing' => array(
                'destination' => 'contact_and_engagement',
                'queue' => 'product_support',
                'create_case' => false,
                'human_review_required' => true,
            ),
        );
        return apply_filters('scfs_contact_engagement_handoff_payload', $payload, $post_id);
    }

    public function coverage() {
        $counts = wp_count_posts(Sustainable_Catalyst_Feature_Suggestions::POST_TYPE);
        $total = 0;
        foreach (array('publish', 'pending', 'draft', 'private') as $status) {
            $total += isset($counts->{$status}) ? (int) $counts->{$status} : 0;
        }
        $coverage = array('total' => $total);
        foreach ($this->taxonomy_definitions() as $taxonomy => $definition) {
            $query = new WP_Query(array(
                'post_type' => Sustainable_Catalyst_Feature_Suggestions::POST_TYPE,
                'post_status' => array('publish', 'pending', 'draft', 'private'),
                'posts_per_page' => 1,
                'fields' => 'ids',
                'tax_query' => array(array('taxonomy' => $taxonomy, 'operator' => 'EXISTS')),
                'no_found_rows' => false,
            ));
            $coverage[$taxonomy] = array(
                'assigned' => (int) $query->found_posts,
                'percent' => $total ? round(((int) $query->found_posts / $total) * 100, 1) : 0,
                'terms' => is_wp_error(wp_count_terms(array('taxonomy' => $taxonomy, 'hide_empty' => false))) ? 0 : (int) wp_count_terms(array('taxonomy' => $taxonomy, 'hide_empty' => false)),
            );
        }
        return $coverage;
    }

    public function register_admin_page() {
        add_submenu_page(
            'edit.php?post_type=' . Sustainable_Catalyst_Feature_Suggestions::POST_TYPE,
            __('Product Taxonomy and Integration', 'sustainable-catalyst-feature-suggestions'),
            __('Products & Integration', 'sustainable-catalyst-feature-suggestions'),
            'edit_posts',
            'scfs-product-integration',
            array($this, 'render_admin_page')
        );
    }

    public function render_admin_page() {
        if (!current_user_can('edit_posts')) {
            wp_die(__('You do not have permission to view product integration.', 'sustainable-catalyst-feature-suggestions'));
        }
        $coverage = $this->coverage();
        $migration = get_option(self::MIGRATION_OPTION, array());
        $run_url = wp_nonce_url(admin_url('admin-post.php?action=scfs_run_product_migration'), 'scfs_run_product_migration');
        ?>
        <div class="wrap scfs-product-integration">
            <h1><?php esc_html_e('Product Taxonomy and Platform Integration', 'sustainable-catalyst-feature-suggestions'); ?></h1>
            <p><?php esc_html_e('Shared products, versions, components, issue types, and releases now provide the canonical context for suggestions, future support articles, known issues, and release intelligence.', 'sustainable-catalyst-feature-suggestions'); ?></p>
            <?php if (!empty($_GET['migration'])) : ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Product taxonomy migration completed.', 'sustainable-catalyst-feature-suggestions'); ?></p></div>
            <?php endif; ?>
            <div class="scfs-intel-cards">
                <div class="scfs-intel-card"><span><?php esc_html_e('Suggestions', 'sustainable-catalyst-feature-suggestions'); ?></span><strong><?php echo esc_html((string) $coverage['total']); ?></strong></div>
                <?php foreach ($this->taxonomy_definitions() as $taxonomy => $definition) : ?>
                    <div class="scfs-intel-card"><span><?php echo esc_html($definition['plural']); ?></span><strong><?php echo esc_html((string) $coverage[$taxonomy]['terms']); ?></strong><small><?php echo esc_html($coverage[$taxonomy]['percent'] . '% assigned'); ?></small></div>
                <?php endforeach; ?>
            </div>
            <h2><?php esc_html_e('Existing suggestion migration', 'sustainable-catalyst-feature-suggestions'); ?></h2>
            <p><?php esc_html_e('The migration is idempotent: it preserves existing taxonomy assignments and only infers missing context from roadmap area, category, submission text, AI triage, product-version metadata, and target release.', 'sustainable-catalyst-feature-suggestions'); ?></p>
            <table class="widefat striped"><tbody>
                <?php foreach (array('status', 'processed', 'assigned_products', 'assigned_components', 'assigned_issue_types', 'assigned_versions', 'assigned_releases', 'unmapped', 'started_at', 'completed_at') as $key) : ?>
                    <tr><th><?php echo esc_html(ucwords(str_replace('_', ' ', $key))); ?></th><td><?php echo esc_html(isset($migration[$key]) ? (string) $migration[$key] : '—'); ?></td></tr>
                <?php endforeach; ?>
            </tbody></table>
            <p><a class="button button-primary" href="<?php echo esc_url($run_url); ?>"><?php esc_html_e('Run full taxonomy migration', 'sustainable-catalyst-feature-suggestions'); ?></a></p>
            <h2><?php esc_html_e('Taxonomy administration', 'sustainable-catalyst-feature-suggestions'); ?></h2>
            <p>
                <?php foreach ($this->taxonomy_definitions() as $taxonomy => $definition) : ?>
                    <a class="button" href="<?php echo esc_url(admin_url('edit-tags.php?taxonomy=' . $taxonomy . '&post_type=' . Sustainable_Catalyst_Feature_Suggestions::POST_TYPE)); ?>"><?php echo esc_html($definition['plural']); ?></a>
                <?php endforeach; ?>
            </p>
            <h2><?php esc_html_e('Contact and Engagement handoff contract', 'sustainable-catalyst-feature-suggestions'); ?></h2>
            <p><?php esc_html_e('The contract is private, review-gated, and does not create a support case automatically. Authorized integrations can request a typed payload from the protected REST endpoint for a specific suggestion.', 'sustainable-catalyst-feature-suggestions'); ?></p>
            <pre><?php echo esc_html(wp_json_encode($this->handoff_schema(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)); ?></pre>
        </div>
        <?php
    }

    public function run_product_migration_action() {
        if (!current_user_can('edit_posts')) {
            wp_die(__('You do not have permission to migrate product taxonomies.', 'sustainable-catalyst-feature-suggestions'));
        }
        check_admin_referer('scfs_run_product_migration');
        $this->migrate_all(200);
        wp_safe_redirect(admin_url('edit.php?post_type=' . Sustainable_Catalyst_Feature_Suggestions::POST_TYPE . '&page=scfs-product-integration&migration=complete'));
        exit;
    }

    public function cli_migrate($args, $assoc_args) {
        $batch = isset($assoc_args['batch']) ? max(1, min(500, absint($assoc_args['batch']))) : 200;
        $state = $this->migrate_all($batch);
        WP_CLI::success(sprintf('Migration %s. Processed %d suggestions; assigned %d products; %d remain unmapped.', $state['status'], $state['processed'], $state['assigned_products'], $state['unmapped']));
    }

    public function register_rest_routes() {
        register_rest_route(Sustainable_Catalyst_Feature_Suggestions::REST_NAMESPACE, '/taxonomy/schema', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'rest_taxonomy_schema'),
            'permission_callback' => '__return_true',
        ));
        register_rest_route(Sustainable_Catalyst_Feature_Suggestions::REST_NAMESPACE, '/taxonomy/terms', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'rest_taxonomy_terms'),
            'permission_callback' => '__return_true',
        ));
        register_rest_route(Sustainable_Catalyst_Feature_Suggestions::REST_NAMESPACE, '/taxonomy/migration', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'rest_migration_status'),
            'permission_callback' => array($this, 'rest_admin_permission'),
        ));
        register_rest_route(Sustainable_Catalyst_Feature_Suggestions::REST_NAMESPACE, '/contact-engagement/handoff-schema', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'rest_handoff_schema'),
            'permission_callback' => '__return_true',
        ));
        register_rest_route(Sustainable_Catalyst_Feature_Suggestions::REST_NAMESPACE, '/suggestions/(?P<id>\d+)/contact-engagement-handoff', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'rest_contact_handoff'),
            'permission_callback' => array($this, 'rest_admin_permission'),
        ));
    }

    public function rest_admin_permission() {
        return current_user_can('edit_posts');
    }

    public function rest_taxonomy_schema() {
        return rest_ensure_response($this->taxonomy_schema());
    }

    public function rest_taxonomy_terms($request) {
        $allowed = array_keys($this->taxonomy_definitions());
        $taxonomy = sanitize_key($request->get_param('taxonomy'));
        if ($taxonomy && !in_array($taxonomy, $allowed, true)) {
            return new WP_Error('scfs_invalid_taxonomy', __('Invalid product taxonomy.', 'sustainable-catalyst-feature-suggestions'), array('status' => 400));
        }
        $selected = $taxonomy ? array($taxonomy) : $allowed;
        $payload = array();
        foreach ($selected as $tax) {
            $terms = get_terms(array('taxonomy' => $tax, 'hide_empty' => false, 'orderby' => 'name', 'order' => 'ASC'));
            $payload[$tax] = is_wp_error($terms) ? array() : array_map(array($this, 'term_record'), $terms);
        }
        return rest_ensure_response(array('schema_version' => self::TAXONOMY_SCHEMA_VERSION, 'terms' => $payload));
    }

    public function rest_migration_status() {
        return rest_ensure_response(array(
            'migration' => get_option(self::MIGRATION_OPTION, array()),
            'coverage' => $this->coverage(),
        ));
    }

    public function rest_handoff_schema() {
        return rest_ensure_response($this->handoff_schema());
    }

    public function rest_contact_handoff($request) {
        $payload = $this->build_contact_handoff(absint($request['id']));
        if (is_wp_error($payload)) {
            return $payload;
        }
        return rest_ensure_response($payload);
    }
}
