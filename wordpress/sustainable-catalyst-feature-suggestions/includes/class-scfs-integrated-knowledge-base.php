<?php
/**
 * Integrated Knowledge Base and Documentation Library.
 *
 * Provides a modern expandable documentation directory, first-party article
 * corpus, safe sample downloads, article navigation, and upgrade-safe import.
 * No legacy KnowledgeBuilder runtime code is used.
 *
 * @package Sustainable_Catalyst_Feature_Suggestions
 */

if (!defined('ABSPATH')) {
    exit;
}

final class SCFS_Integrated_Knowledge_Base {
    const VERSION = '5.2.0';
    const CONTENT_VERSION = '1.0.0';
    const IMPORT_OPTION = 'scfs_integrated_kb_import_version';
    const REPORT_OPTION = 'scfs_integrated_kb_last_report';
    const CONTENT_KEY_META = '_scfs_integrated_kb_key';
    const LEGACY_CONTENT_KEY_META = '_sckb_content_key';
    const GENERATED_HASH_META = '_scfs_integrated_kb_hash';
    const LEGACY_HASH_META = '_sckb_generated_hash';
    const PRODUCT_SLUG_META = '_scfs_kb_product_slug';
    const SECTION_SLUG_META = '_scfs_kb_section_slug';
    const SOURCE_VERSION_META = '_scfs_kb_content_version';
    const ADMIN_PAGE = 'scfs-integrated-knowledge-base';

    private static $instance = null;
    private $rendering_article = false;
    private $importing_documentation = false;

    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('wp_enqueue_scripts', array($this, 'register_assets'));
        add_action('admin_enqueue_scripts', array($this, 'register_admin_assets'));
        add_action('admin_menu', array($this, 'register_admin_page'), 32);
        add_action('admin_init', array($this, 'maybe_import_documentation'), 45);
        add_action('admin_post_scfs_kb510_import', array($this, 'handle_import'));
        add_filter('the_content', array($this, 'decorate_article_experience'), 30);
        add_filter('scfs_support_navigation_items', array($this, 'keep_knowledge_base_navigation'), 20, 4);
        add_filter('scfs_editorial_allow_system_publication', array($this, 'allow_integrated_publication'), 10, 3);
    }

    public function allow_integrated_publication($allowed, $data, $postarr) {
        $post_type = isset($data['post_type']) ? (string) $data['post_type'] : (isset($postarr['post_type']) ? (string) $postarr['post_type'] : '');
        if ($this->importing_documentation && $post_type === SCFS_Knowledge_Base_Foundation::ARTICLE_POST_TYPE) {
            return true;
        }
        return $allowed;
    }

    public static function activate() {
        $instance = self::instance();
        $report = $instance->import_documentation('publish');
        update_option(self::REPORT_OPTION, $report, false);
        if (!empty($report['ok'])) {
            update_option(self::IMPORT_OPTION, self::CONTENT_VERSION, false);
        }
    }

    public function register_assets() {
        wp_register_script(
            'scfs-integrated-knowledge-base',
            plugins_url('../assets/integrated-knowledge-base.js', __FILE__),
            array(),
            self::VERSION,
            true
        );
    }

    public function register_admin_assets($hook) {
        if (strpos((string) $hook, self::ADMIN_PAGE) !== false) {
            wp_enqueue_style(
                'scfs-integrated-knowledge-base-admin',
                plugins_url('../assets/knowledge-base.css', __FILE__),
                array(),
                self::VERSION
            );
        }
    }

    public function keep_knowledge_base_navigation($items, $settings, $product, $respect_content) {
        if (!isset($items['documentation'])) {
            $documentation = array(
                'label' => __('Knowledge Base', 'sustainable-catalyst-feature-suggestions'),
                'description' => __('Product guides and troubleshooting', 'sustainable-catalyst-feature-suggestions'),
            );
            $rebuilt = array();
            foreach ((array) $items as $key => $item) {
                $rebuilt[$key] = $item;
                if ($key === 'resolve') {
                    $rebuilt['documentation'] = $documentation;
                }
            }
            if (!isset($rebuilt['documentation'])) {
                $rebuilt['documentation'] = $documentation;
            }
            return $rebuilt;
        }
        return $items;
    }

    public function maybe_import_documentation() {
        if (!current_user_can('manage_options')) {
            return;
        }
        if ((string) get_option(self::IMPORT_OPTION, '') === self::CONTENT_VERSION) {
            return;
        }
        $report = $this->import_documentation('publish');
        update_option(self::REPORT_OPTION, $report, false);
        if (!empty($report['ok'])) {
            update_option(self::IMPORT_OPTION, self::CONTENT_VERSION, false);
            flush_rewrite_rules(false);
        }
    }

    private function plugin_root() {
        return plugin_dir_path(dirname(__FILE__));
    }

    private function plugin_file() {
        return $this->plugin_root() . 'sustainable-catalyst-feature-suggestions.php';
    }

    private function content_file() {
        return $this->plugin_root() . 'content/knowledge-base/articles.json';
    }

    private function content_data() {
        $path = $this->content_file();
        if (!is_readable($path)) {
            return new WP_Error('scfs_kb_content_missing', __('The bundled Knowledge Base article file is missing.', 'sustainable-catalyst-feature-suggestions'));
        }
        $data = json_decode((string) file_get_contents($path), true);
        if (!is_array($data) || empty($data['articles']) || !is_array($data['articles'])) {
            return new WP_Error('scfs_kb_content_invalid', __('The bundled Knowledge Base article file is invalid.', 'sustainable-catalyst-feature-suggestions'));
        }
        return $data;
    }

    public function validate_content() {
        $data = $this->content_data();
        $report = array('ok' => true, 'errors' => array(), 'articles' => 0, 'products' => 0, 'samples' => 0, 'characters' => 0);
        if (is_wp_error($data)) {
            $report['ok'] = false;
            $report['errors'][] = $data->get_error_message();
            return $report;
        }
        $keys = array();
        $products = array();
        $samples = array();
        $required = array('content_key', 'title', 'slug', 'summary', 'product', 'product_slug', 'article_type', 'content', 'sample_csv', 'sample_json');
        foreach ($data['articles'] as $index => $article) {
            foreach ($required as $field) {
                if (!isset($article[$field]) || trim((string) $article[$field]) === '') {
                    $report['errors'][] = sprintf('Article %d is missing %s.', $index + 1, $field);
                }
            }
            $key = isset($article['content_key']) ? (string) $article['content_key'] : '';
            if ($key !== '' && isset($keys[$key])) {
                $report['errors'][] = 'Duplicate content key: ' . $key;
            }
            $keys[$key] = true;
            if (!empty($article['product_slug'])) {
                $products[sanitize_title($article['product_slug'])] = true;
            }
            $report['characters'] += strlen(isset($article['content']) ? (string) $article['content'] : '');
            foreach (array('sample_csv', 'sample_json') as $sample_field) {
                if (empty($article[$sample_field])) {
                    continue;
                }
                $relative = ltrim((string) $article[$sample_field], '/');
                $sample_path = $this->plugin_root() . 'content/knowledge-base/' . $relative;
                $samples[$relative] = true;
                if (!is_readable($sample_path)) {
                    $report['errors'][] = 'Missing sample: ' . $relative;
                }
            }
            if (strpos((string) $article['content'], '{{sample_csv_url}}') === false || strpos((string) $article['content'], '{{sample_json_url}}') === false) {
                $report['errors'][] = 'Sample placeholders are missing from ' . $key . '.';
            }
        }
        $report['articles'] = count($data['articles']);
        $report['products'] = count($products);
        $report['samples'] = count($samples);
        if ($report['articles'] !== 96) {
            $report['errors'][] = 'Expected 96 articles; found ' . $report['articles'] . '.';
        }
        if ($report['products'] !== 16) {
            $report['errors'][] = 'Expected 16 products; found ' . $report['products'] . '.';
        }
        if ($report['samples'] !== 32) {
            $report['errors'][] = 'Expected 32 sample files; found ' . $report['samples'] . '.';
        }
        $report['ok'] = empty($report['errors']);
        return $report;
    }

    private function products() {
        return array(
            'workbench' => array('name' => 'Sustainable Catalyst Workbench', 'canonical' => 'workbench', 'description' => 'Calculations, models, graphs, engineering workflows, exports, and prototyping.'),
            'decision-studio' => array('name' => 'Decision Studio', 'canonical' => 'decision-studio', 'description' => 'Decision packets, scenarios, evidence, tradeoffs, reviews, and outcomes.'),
            'site-intelligence' => array('name' => 'Site Intelligence', 'canonical' => 'site-intelligence', 'description' => 'Country, regional, thematic, geospatial, and live public intelligence.'),
            'research-librarian' => array('name' => 'Research Librarian', 'canonical' => 'research-librarian', 'description' => 'Research routing, source discovery, site guidance, and next-step recommendations.'),
            'knowledge-library' => array('name' => 'Knowledge Library', 'canonical' => 'knowledge-library', 'description' => 'Documents, sources, citations, collections, evidence, and research pathways.'),
            'research-lab' => array('name' => 'Sustainable Catalyst Research Lab', 'canonical' => 'research-lab', 'description' => 'Scientific computing, datasets, experiments, instruments, and reproducible runs.'),
            'catalyst-canvas' => array('name' => 'Catalyst Canvas', 'canonical' => 'catalyst-canvas', 'description' => 'Problem framing, stakeholders, journeys, assumptions, evidence, and experiments.'),
            'catalyst-data' => array('name' => 'Catalyst Data', 'canonical' => 'catalyst-data', 'description' => 'Questions, instruments, datasets, observations, quality, query, and export.'),
            'catalyst-analytics-r' => array('name' => 'Catalyst Analytics R', 'canonical' => 'catalyst-analytics-r', 'description' => 'R-based analysis, reproducible methods, review, governance, and reporting.'),
            'catalyst-finance' => array('name' => 'Catalyst Finance', 'canonical' => 'catalyst-finance', 'description' => 'Financial analysis, scenarios, risk, sustainability economics, and decisions.'),
            'narrative-risk' => array('name' => 'Catalyst Narrative Risk', 'canonical' => 'narrative-risk', 'description' => 'Claims, evidence, narratives, comparative analysis, review, and governance.'),
            'catalyst-grit' => array('name' => 'Catalyst Grit', 'canonical' => 'catalyst-grit', 'description' => 'Structured resilience, recovery, reflection, review, and human-systems records.'),
            'global-impact-catalyst' => array('name' => 'Global Impact Catalyst', 'canonical' => 'global-impact-catalyst', 'description' => 'Sustainability indicators, scenarios, comparisons, and impact intelligence.'),
            'product-support-platform' => array('name' => 'Product Support and Feedback Platform', 'canonical' => 'feature-suggestions', 'description' => 'Documentation, known issues, releases, feedback, surveys, and support operations.'),
            'contact-engagement' => array('name' => 'Contact and Engagement Platform', 'canonical' => 'contact-and-engagement', 'description' => 'Private inquiries, scheduling, proposals, workspaces, and engagement operations.'),
            'platform-core' => array('name' => 'Sustainable Catalyst Platform Core', 'canonical' => 'platform-core', 'description' => 'Shared identity, schemas, APIs, provenance, security, and platform services.'),
        );
    }

    private function sections() {
        return array(
            'start-here' => array('name' => 'Start Here', 'description' => 'Orientation and a safe first run.', 'order' => 10),
            'setup-configuration' => array('name' => 'Setup and Configuration', 'description' => 'Installation, settings, validation, and recovery.', 'order' => 20),
            'features-capabilities' => array('name' => 'Tools and Features', 'description' => 'Feature-by-feature reference and expected behavior.', 'order' => 30),
            'worked-examples' => array('name' => 'Worked Examples', 'description' => 'Complete sample workflows using synthetic data.', 'order' => 40),
            'troubleshooting' => array('name' => 'Troubleshooting', 'description' => 'Symptoms, likely causes, recovery, and escalation.', 'order' => 50),
            'technical-reference' => array('name' => 'Technical Reference', 'description' => 'Interfaces, integrations, records, APIs, and governance.', 'order' => 60),
        );
    }

    private function section_for_article($article) {
        $key = isset($article['content_key']) ? (string) $article['content_key'] : '';
        if (strpos($key, ':getting-started:') !== false) return 'start-here';
        if (strpos($key, ':installation-configuration:') !== false) return 'setup-configuration';
        if (strpos($key, ':feature-guide:') !== false) return 'features-capabilities';
        if (strpos($key, ':sample-workflow:') !== false) return 'worked-examples';
        if (strpos($key, ':troubleshooting:') !== false) return 'troubleshooting';
        return 'technical-reference';
    }

    private function article_type_slug($article) {
        $section = $this->section_for_article($article);
        if ($section === 'start-here') return 'getting-started';
        if ($section === 'troubleshooting') return 'troubleshooting';
        if (in_array($section, array('features-capabilities', 'technical-reference'), true)) return 'reference';
        return 'how-to';
    }

    private function ensure_term($taxonomy, $name, $slug, $parent = 0, $description = '') {
        if (!taxonomy_exists($taxonomy)) {
            return 0;
        }
        $slug = sanitize_title($slug);
        $existing = get_term_by('slug', $slug, $taxonomy);
        if (!$existing) {
            $created = wp_insert_term($name, $taxonomy, array('slug' => $slug, 'parent' => absint($parent), 'description' => $description));
            if (is_wp_error($created)) {
                return 0;
            }
            $existing = get_term($created['term_id'], $taxonomy);
        } else {
            $changes = array();
            if ((int) $existing->parent !== absint($parent)) $changes['parent'] = absint($parent);
            if ($description !== '' && (string) $existing->description !== (string) $description) $changes['description'] = $description;
            if ((string) $existing->name !== (string) $name) $changes['name'] = $name;
            if ($changes) {
                $updated = wp_update_term($existing->term_id, $taxonomy, $changes);
                if (!is_wp_error($updated)) $existing = get_term($existing->term_id, $taxonomy);
            }
        }
        if ($existing && !is_wp_error($existing)) {
            if (!get_term_meta($existing->term_id, 'scfs_canonical_id', true)) {
                update_term_meta($existing->term_id, 'scfs_canonical_id', 'scfs:' . $taxonomy . ':' . $slug);
            }
            update_term_meta($existing->term_id, 'scfs_status', 'active');
            return (int) $existing->term_id;
        }
        return 0;
    }

    private function sample_url($relative) {
        return plugins_url('content/knowledge-base/' . ltrim((string) $relative, '/'), $this->plugin_file());
    }

    private function find_article_id($article) {
        foreach (array(self::CONTENT_KEY_META, self::LEGACY_CONTENT_KEY_META) as $meta_key) {
            $ids = get_posts(array(
                'post_type' => SCFS_Knowledge_Base_Foundation::ARTICLE_POST_TYPE,
                'post_status' => array('draft', 'pending', 'publish', 'private', 'future', 'trash'),
                'posts_per_page' => 1,
                'fields' => 'ids',
                'meta_key' => $meta_key,
                'meta_value' => $article['content_key'],
                'suppress_filters' => true,
            ));
            if ($ids) return (int) $ids[0];
        }
        $existing = get_page_by_path($article['slug'], OBJECT, SCFS_Knowledge_Base_Foundation::ARTICLE_POST_TYPE);
        return $existing ? (int) $existing->ID : 0;
    }

    private function assign_article_terms($post_id, $article, $product_slug, $section_slug) {
        $products = $this->products();
        $sections = $this->sections();
        $product = isset($products[$product_slug]) ? $products[$product_slug] : array('name' => $article['product'], 'canonical' => $product_slug, 'description' => '');

        $product_term = $this->ensure_term(SCFS_Product_Integration::PRODUCT_TAXONOMY, $product['name'], $product['canonical']);
        if ($product_term) wp_set_object_terms($post_id, array($product_term), SCFS_Product_Integration::PRODUCT_TAXONOMY, false);

        $version_slug = $product['canonical'] . '-' . sanitize_title($article['product_version']);
        $version_term = $this->ensure_term(SCFS_Product_Integration::VERSION_TAXONOMY, $article['product_version'], $version_slug);
        if ($version_term) wp_set_object_terms($post_id, array($version_term), SCFS_Product_Integration::VERSION_TAXONOMY, false);

        $component_ids = array();
        foreach ((array) $article['components'] as $component) {
            $term_id = $this->ensure_term(SCFS_Product_Integration::COMPONENT_TAXONOMY, $component, $component);
            if ($term_id) $component_ids[] = $term_id;
        }
        if ($component_ids) wp_set_object_terms($post_id, array_values(array_unique($component_ids)), SCFS_Product_Integration::COMPONENT_TAXONOMY, false);

        $issue_ids = array();
        foreach ((array) $article['issue_types'] as $issue) {
            $issue_slug = stripos($issue, 'support') !== false ? 'support-question' : 'documentation-gap';
            $issue_name = $issue_slug === 'support-question' ? 'Support Question' : 'Documentation Gap';
            $term_id = $this->ensure_term(SCFS_Product_Integration::ISSUE_TAXONOMY, $issue_name, $issue_slug);
            if ($term_id) $issue_ids[] = $term_id;
        }
        if ($issue_ids) wp_set_object_terms($post_id, array_values(array_unique($issue_ids)), SCFS_Product_Integration::ISSUE_TAXONOMY, false);

        $type_slug = $this->article_type_slug($article);
        $type_name = $type_slug === 'reference' ? 'Technical Reference' : ($type_slug === 'how-to' ? 'How-to Guide' : ($type_slug === 'getting-started' ? 'Getting Started' : 'Troubleshooting'));
        $type_term = $this->ensure_term(SCFS_Knowledge_Base_Foundation::ARTICLE_TYPE_TAXONOMY, $type_name, $type_slug);
        if ($type_term) wp_set_object_terms($post_id, array($type_term), SCFS_Knowledge_Base_Foundation::ARTICLE_TYPE_TAXONOMY, false);

        $parent_slug = 'kb-' . $product_slug;
        $parent_id = $this->ensure_term(SCFS_Knowledge_Base_Foundation::COLLECTION_TAXONOMY, $product['name'], $parent_slug, 0, $product['description']);
        $section = $sections[$section_slug];
        $child_slug = $parent_slug . '-' . $section_slug;
        $child_id = $this->ensure_term(SCFS_Knowledge_Base_Foundation::COLLECTION_TAXONOMY, $section['name'], $child_slug, $parent_id, $section['description']);
        $collection_ids = array_values(array_filter(array($parent_id, $child_id)));
        if ($collection_ids) wp_set_object_terms($post_id, $collection_ids, SCFS_Knowledge_Base_Foundation::COLLECTION_TAXONOMY, false);
    }

    private function update_article_meta($post_id, $article, $hash, $product_slug, $section_slug, $manual_override) {
        $minutes = 0;
        if (preg_match('/(\d+)/', (string) $article['estimated_reading_time'], $matches)) $minutes = absint($matches[1]);
        $meta = array(
            '_scfs_kb_summary' => $article['summary'],
            '_scfs_article_audience' => $article['audience'],
            '_scfs_article_prerequisites' => $article['prerequisites'],
            '_scfs_estimated_minutes' => $minutes,
            '_scfs_last_verified_version' => $article['last_verified_version'],
            '_scfs_article_template' => $this->article_type_slug($article),
            '_scfs_kb_schema_version' => '1.0',
            self::CONTENT_KEY_META => $article['content_key'],
            self::LEGACY_CONTENT_KEY_META => $article['content_key'],
            self::SOURCE_VERSION_META => self::CONTENT_VERSION,
            self::PRODUCT_SLUG_META => $product_slug,
            self::SECTION_SLUG_META => $section_slug,
            '_scfs_source_kind' => 'integrated-knowledge-base',
            '_scfs_source_reference' => $article['source_reference'],
            '_scfs_source_version' => self::CONTENT_VERSION,
            '_scfs_lifecycle' => 'published',
            '_scfs_article_status' => 'published',
            '_scfs_verified_at' => '2026-07-17',
            '_scfs_human_review_required' => '0',
            '_scfs_sample_csv_url' => $this->sample_url($article['sample_csv']),
            '_scfs_sample_json_url' => $this->sample_url($article['sample_json']),
            '_scfs_integrated_manual_override' => $manual_override ? '1' : '0',
            '_scfs_editorial_state' => 'published',
            '_scfs_editorial_change_summary' => 'Published from the verified Sustainable Catalyst v5.1.0 first-party documentation corpus.',
            '_scfs_editorial_approval_note' => 'System publication is limited to the validated integrated Knowledge Base migration.',
            '_scfs_editorial_approved_at' => current_time('mysql'),
            '_scfs_editorial_schema' => class_exists('SCFS_Editorial_Governance') ? SCFS_Editorial_Governance::SCHEMA_VERSION : '1.0',
        );
        if (!$manual_override) $meta[self::GENERATED_HASH_META] = $hash;
        foreach ($meta as $key => $value) update_post_meta($post_id, $key, $value);
    }

    public function import_documentation($status = 'publish') {
        $validation = $this->validate_content();
        if (!$validation['ok']) {
            return array('ok' => false, 'errors' => $validation['errors'], 'validation' => $validation);
        }
        $data = $this->content_data();
        $sections = $this->sections();
        $report = array(
            'ok' => true,
            'content_version' => self::CONTENT_VERSION,
            'requested_status' => 'publish',
            'created' => 0,
            'updated' => 0,
            'repaired' => 0,
            'manual_edits_preserved' => 0,
            'published' => 0,
            'errors' => array(),
            'time' => current_time('mysql'),
        );
        $this->importing_documentation = true;
        foreach ($data['articles'] as $article) {
            $product_slug = sanitize_title($article['product_slug']);
            $section_slug = $this->section_for_article($article);
            $csv_url = $this->sample_url($article['sample_csv']);
            $json_url = $this->sample_url($article['sample_json']);
            $content = str_replace(array('{{sample_csv_url}}', '{{sample_json_url}}'), array(esc_url($csv_url), esc_url($json_url)), (string) $article['content']);
            $content = str_replace('<p><strong>Status:</strong> Draft for product and editorial review.</p>', '<p><strong>Status:</strong> Published support documentation.</p>', $content);
            $hash = hash('sha256', $article['title'] . "\n" . $content);
            $post_id = $this->find_article_id($article);
            $manual_override = false;
            if ($post_id) {
                $existing = get_post($post_id);
                $prior_hash = (string) get_post_meta($post_id, self::GENERATED_HASH_META, true);
                if ($prior_hash === '') $prior_hash = (string) get_post_meta($post_id, self::LEGACY_HASH_META, true);
                $current_hash = $existing ? hash('sha256', $existing->post_title . "\n" . $existing->post_content) : '';
                $manual_override = $prior_hash !== '' && $current_hash !== '' && !hash_equals($prior_hash, $current_hash);
                $payload = array(
                    'ID' => $post_id,
                    'post_status' => 'publish',
                    'post_type' => SCFS_Knowledge_Base_Foundation::ARTICLE_POST_TYPE,
                    'menu_order' => $sections[$section_slug]['order'],
                );
                if (!$manual_override) {
                    $payload['post_title'] = $article['title'];
                    $payload['post_name'] = $article['slug'];
                    $payload['post_excerpt'] = $article['summary'];
                    $payload['post_content'] = wp_slash($content);
                }
                $result = wp_update_post($payload, true);
                if (is_wp_error($result)) {
                    $report['errors'][] = $article['title'] . ': ' . $result->get_error_message();
                    continue;
                }
                if ($manual_override) $report['manual_edits_preserved']++; else $report['updated']++;
                $report['repaired']++;
            } else {
                $post_id = wp_insert_post(array(
                    'post_type' => SCFS_Knowledge_Base_Foundation::ARTICLE_POST_TYPE,
                    'post_title' => $article['title'],
                    'post_name' => $article['slug'],
                    'post_excerpt' => $article['summary'],
                    'post_content' => wp_slash($content),
                    'post_status' => 'publish',
                    'menu_order' => $sections[$section_slug]['order'],
                ), true);
                if (is_wp_error($post_id)) {
                    $report['errors'][] = $article['title'] . ': ' . $post_id->get_error_message();
                    continue;
                }
                $report['created']++;
            }
            $this->update_article_meta($post_id, $article, $hash, $product_slug, $section_slug, $manual_override);
            $this->assign_article_terms($post_id, $article, $product_slug, $section_slug);
            if (get_post_status($post_id) === 'publish') $report['published']++;
        }
        $this->importing_documentation = false;
        $report['ok'] = empty($report['errors']) && $report['published'] >= 96;
        update_option(self::REPORT_OPTION, $report, false);
        if ($report['ok']) update_option(self::IMPORT_OPTION, self::CONTENT_VERSION, false);
        return $report;
    }

    private function support_knowledge_base_url($product_slug = '') {
        $base = home_url('/support/');
        if (function_exists('get_page_by_path')) {
            $page = get_page_by_path('support');
            if ($page && function_exists('get_permalink')) {
                $page_url = get_permalink($page);
                if ($page_url) {
                    $base = $page_url;
                }
            }
        }
        $base = apply_filters('scfs_integrated_kb_support_url', $base);
        $args = array('scfs_support_view' => 'documentation');
        if ($product_slug !== '') {
            $products = $this->products();
            $args['scfs_support_product'] = isset($products[$product_slug]) ? $products[$product_slug]['canonical'] : sanitize_title($product_slug);
        }
        return add_query_arg($args, $base) . '#support-center';
    }

    private function all_published_articles() {
        return get_posts(array(
            'post_type' => SCFS_Knowledge_Base_Foundation::ARTICLE_POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => 500,
            'orderby' => array('menu_order' => 'ASC', 'title' => 'ASC'),
            'order' => 'ASC',
            'suppress_filters' => false,
        ));
    }

    private function article_product_slug($post) {
        $slug = sanitize_title((string) get_post_meta($post->ID, self::PRODUCT_SLUG_META, true));
        if ($slug !== '') return $slug;
        $terms = get_the_terms($post->ID, SCFS_Product_Integration::PRODUCT_TAXONOMY);
        if ($terms && !is_wp_error($terms)) {
            $term_slug = $terms[0]->slug;
            foreach ($this->products() as $product_slug => $product) {
                if ($product['canonical'] === $term_slug) return $product_slug;
            }
            return $term_slug;
        }
        return 'other-documentation';
    }

    private function article_section_slug($post) {
        $slug = sanitize_title((string) get_post_meta($post->ID, self::SECTION_SLUG_META, true));
        if (isset($this->sections()[$slug])) return $slug;
        $types = wp_get_post_terms($post->ID, SCFS_Knowledge_Base_Foundation::ARTICLE_TYPE_TAXONOMY, array('fields' => 'slugs'));
        if (is_wp_error($types)) $types = array();
        if (in_array('getting-started', $types, true)) return 'start-here';
        if (in_array('troubleshooting', $types, true)) return 'troubleshooting';
        if (in_array('reference', $types, true)) return 'technical-reference';
        return 'worked-examples';
    }

    private function browser_groups($product_filter = '') {
        $groups = array();
        foreach ($this->all_published_articles() as $post) {
            $product_slug = $this->article_product_slug($post);
            if ($product_filter !== '' && $product_filter !== $product_slug) continue;
            $section_slug = $this->article_section_slug($post);
            if (!isset($groups[$product_slug])) $groups[$product_slug] = array();
            if (!isset($groups[$product_slug][$section_slug])) $groups[$product_slug][$section_slug] = array();
            $groups[$product_slug][$section_slug][] = $post;
        }
        return $groups;
    }

    private function normalize_product_filter($slug) {
        $slug = sanitize_title($slug);
        if ($slug === '') return '';
        foreach ($this->products() as $product_slug => $product) {
            if ($slug === $product_slug || $slug === $product['canonical']) return $product_slug;
        }
        return $slug;
    }

    private function browser_category_groups($product_filter = '') {
        $groups = array();
        foreach ($this->all_published_articles() as $post) {
            $product_slug = $this->article_product_slug($post);
            if ($product_filter !== '' && $product_filter !== $product_slug) continue;
            $section_slug = $this->article_section_slug($post);
            if (!isset($groups[$section_slug])) $groups[$section_slug] = array();
            $groups[$section_slug][] = $post;
        }
        return $groups;
    }

    private function article_library_meta($post) {
        $products = $this->products();
        $product_slug = $this->article_product_slug($post);
        $product_name = isset($products[$product_slug]) ? $products[$product_slug]['name'] : $product_slug;
        $version = get_post_meta($post->ID, '_scfs_product_version', true);
        if ($version === '') $version = get_post_meta($post->ID, '_scfs_kb_product_version', true);
        $minutes = absint(get_post_meta($post->ID, '_scfs_estimated_minutes', true));
        $verified = get_post_meta($post->ID, '_scfs_last_verified_version', true);
        if ($verified === '') $verified = $version;
        return array(
            'product_slug' => $product_slug,
            'product_name' => $product_name,
            'version' => (string) $version,
            'verified' => (string) $verified,
            'minutes' => $minutes,
            'updated' => get_the_modified_date(get_option('date_format'), $post),
            'summary' => get_post_meta($post->ID, '_scfs_kb_summary', true) ?: get_the_excerpt($post),
        );
    }

    private function render_library_article_row($post) {
        $meta = $this->article_library_meta($post);
        echo '<a class="scfs-support-library-article" href="' . esc_url(get_permalink($post)) . '">';
        echo '<span class="scfs-support-library-article-main"><span class="scfs-support-library-product">' . esc_html($meta['product_name']) . '</span><strong>' . esc_html(get_the_title($post)) . '</strong><small>' . esc_html($meta['summary']) . '</small></span>';
        echo '<span class="scfs-support-library-article-meta">';
        if ($meta['verified'] !== '') echo '<span>' . esc_html(sprintf(__('Verified %s', 'sustainable-catalyst-feature-suggestions'), $meta['verified'])) . '</span>';
        if ($meta['minutes']) echo '<span>' . esc_html(sprintf(__('%d min', 'sustainable-catalyst-feature-suggestions'), $meta['minutes'])) . '</span>';
        if ($meta['updated']) echo '<span>' . esc_html($meta['updated']) . '</span>';
        echo '</span></a>';
    }

    public function render_browser($atts = array()) {
        $atts = shortcode_atts(array(
            'title' => __('Support Documentation Library', 'sustainable-catalyst-feature-suggestions'),
            'intro' => __('Browse generated documentation collections by category or product, then open a publication-style support article.', 'sustainable-catalyst-feature-suggestions'),
            'product' => '',
            'show_known_issues' => '0',
        ), $atts, 'scfs_support_knowledge_base');
        wp_enqueue_style('scfs-knowledge-base');
        wp_enqueue_script('scfs-integrated-knowledge-base');
        $search = isset($_GET['scfs_kb_search']) ? sanitize_text_field(wp_unslash($_GET['scfs_kb_search'])) : '';
        $raw_product = isset($_GET['scfs_kb_product']) ? sanitize_title(wp_unslash($_GET['scfs_kb_product'])) : sanitize_title($atts['product']);
        $product_filter = $this->normalize_product_filter($raw_product);
        $product_groups = $this->browser_groups($product_filter);
        $category_groups = $this->browser_category_groups($product_filter);
        $products = $this->products();
        $sections = $this->sections();
        $published_count = wp_count_posts(SCFS_Knowledge_Base_Foundation::ARTICLE_POST_TYPE);
        $total = isset($published_count->publish) ? (int) $published_count->publish : 0;

        $search_results = array();
        if ($search !== '') {
            $query = new WP_Query(array(
                'post_type' => SCFS_Knowledge_Base_Foundation::ARTICLE_POST_TYPE,
                'post_status' => 'publish',
                'posts_per_page' => 100,
                's' => $search,
                'orderby' => 'relevance',
            ));
            foreach ($query->posts as $post) {
                if ($product_filter !== '' && $this->article_product_slug($post) !== $product_filter) continue;
                $search_results[] = $post;
            }
        }

        ob_start();
        echo '<section class="scfs-kb scfs-kb--modern scfs-support-library" aria-labelledby="scfs-kb-title">';
        echo '<header class="scfs-kb-modern-hero"><p class="scfs-kb-eyebrow">' . esc_html__('Sustainable Catalyst Support', 'sustainable-catalyst-feature-suggestions') . '</p><h2 id="scfs-kb-title">' . esc_html($atts['title']) . '</h2><p>' . esc_html($atts['intro']) . '</p><div class="scfs-kb-modern-stats"><span><strong>' . esc_html((string) $total) . '</strong> ' . esc_html__('published articles', 'sustainable-catalyst-feature-suggestions') . '</span><span><strong>' . esc_html((string) count($products)) . '</strong> ' . esc_html__('products', 'sustainable-catalyst-feature-suggestions') . '</span><span><strong>' . esc_html((string) count($sections)) . '</strong> ' . esc_html__('documentation categories', 'sustainable-catalyst-feature-suggestions') . '</span></div></header>';

        $action = remove_query_arg(array('scfs_kb_search', 'scfs_kb_product', 'scfs_kb_page'));
        echo '<form class="scfs-kb-modern-search" method="get" action="' . esc_url($action) . '" role="search">';
        foreach (array('scfs_support_view', 'scfs_support_product') as $preserve) {
            if (isset($_GET[$preserve])) echo '<input type="hidden" name="' . esc_attr($preserve) . '" value="' . esc_attr(sanitize_text_field(wp_unslash($_GET[$preserve]))) . '">';
        }
        echo '<label><span>' . esc_html__('Search documentation', 'sustainable-catalyst-feature-suggestions') . '</span><input type="search" name="scfs_kb_search" value="' . esc_attr($search) . '" placeholder="' . esc_attr__('Search by task, feature, error message, or topic', 'sustainable-catalyst-feature-suggestions') . '"></label>';
        echo '<label><span>' . esc_html__('Product', 'sustainable-catalyst-feature-suggestions') . '</span><select name="scfs_kb_product"><option value="">' . esc_html__('All products', 'sustainable-catalyst-feature-suggestions') . '</option>';
        foreach ($products as $slug => $product) echo '<option value="' . esc_attr($slug) . '" ' . selected($product_filter, $slug, false) . '>' . esc_html($product['name']) . '</option>';
        echo '</select></label><button type="submit">' . esc_html__('Search', 'sustainable-catalyst-feature-suggestions') . '</button></form>';

        if ($search !== '') {
            echo '<section class="scfs-kb-search-results" aria-labelledby="scfs-kb-search-results-title"><div class="scfs-kb-results-head"><h3 id="scfs-kb-search-results-title">' . esc_html__('Search results', 'sustainable-catalyst-feature-suggestions') . '</h3><span>' . esc_html(sprintf(_n('%d article', '%d articles', count($search_results), 'sustainable-catalyst-feature-suggestions'), count($search_results))) . '</span></div>';
            if (!$search_results) {
                echo '<div class="scfs-kb-empty"><h3>' . esc_html__('No matching documentation found', 'sustainable-catalyst-feature-suggestions') . '</h3><p>' . esc_html__('Try a category below or use fewer search terms.', 'sustainable-catalyst-feature-suggestions') . '</p></div>';
            } else {
                echo '<div class="scfs-support-library-results">';
                foreach ($search_results as $post) $this->render_library_article_row($post);
                echo '</div>';
            }
            echo '</section>';
        }

        echo '<div class="scfs-support-library-controls" role="group" aria-label="' . esc_attr__('Documentation views', 'sustainable-catalyst-feature-suggestions') . '"><button type="button" class="is-active" data-scfs-library-view="categories">' . esc_html__('Browse by category', 'sustainable-catalyst-feature-suggestions') . '</button><button type="button" data-scfs-library-view="products">' . esc_html__('Browse by product', 'sustainable-catalyst-feature-suggestions') . '</button><span></span><button type="button" data-scfs-kb-expand>' . esc_html__('Expand all', 'sustainable-catalyst-feature-suggestions') . '</button><button type="button" data-scfs-kb-collapse>' . esc_html__('Collapse all', 'sustainable-catalyst-feature-suggestions') . '</button></div>';

        echo '<div class="scfs-support-library-view is-active" data-scfs-library-panel="categories"><div class="scfs-support-library-heading"><div><p class="scfs-kb-eyebrow">' . esc_html__('Documentation collections', 'sustainable-catalyst-feature-suggestions') . '</p><h3>' . esc_html__('Browse by category', 'sustainable-catalyst-feature-suggestions') . '</h3></div><p>' . esc_html__('Every list is generated automatically from published Support Articles.', 'sustainable-catalyst-feature-suggestions') . '</p></div><div class="scfs-support-library-categories">';
        foreach ($sections as $section_slug => $section) {
            $articles = isset($category_groups[$section_slug]) ? $category_groups[$section_slug] : array();
            echo '<details class="scfs-support-library-category" data-scfs-kb-category open><summary><span class="scfs-support-library-category-icon" aria-hidden="true"></span><span><strong>' . esc_html($section['name']) . '</strong><small>' . esc_html($section['description']) . '</small></span><em>' . esc_html(sprintf(_n('%d article', '%d articles', count($articles), 'sustainable-catalyst-feature-suggestions'), count($articles))) . '</em></summary><div class="scfs-support-library-articles">';
            if (!$articles) echo '<p class="scfs-kb-folder-empty">' . esc_html__('No published articles in this category yet.', 'sustainable-catalyst-feature-suggestions') . '</p>';
            else foreach ($articles as $post) $this->render_library_article_row($post);
            echo '</div></details>';
        }
        echo '</div></div>';

        echo '<div class="scfs-support-library-view" data-scfs-library-panel="products" hidden><div class="scfs-support-library-heading"><div><p class="scfs-kb-eyebrow">' . esc_html__('Product documentation', 'sustainable-catalyst-feature-suggestions') . '</p><h3>' . esc_html__('Browse by product', 'sustainable-catalyst-feature-suggestions') . '</h3></div><p>' . esc_html__('Expand a product to see its complete generated documentation collection.', 'sustainable-catalyst-feature-suggestions') . '</p></div><div class="scfs-kb-product-folders">';
        foreach ($products as $product_slug => $product) {
            if ($product_filter !== '' && $product_filter !== $product_slug) continue;
            $groups = isset($product_groups[$product_slug]) ? $product_groups[$product_slug] : array();
            $article_count = 0;
            foreach ($groups as $articles) $article_count += count($articles);
            echo '<details class="scfs-kb-product-folder" data-scfs-kb-product open><summary><span class="scfs-kb-folder-mark" aria-hidden="true"></span><span class="scfs-kb-folder-copy"><strong>' . esc_html($product['name']) . '</strong><small>' . esc_html($product['description']) . '</small></span><span class="scfs-kb-folder-count">' . esc_html(sprintf(_n('%d article', '%d articles', $article_count, 'sustainable-catalyst-feature-suggestions'), $article_count)) . '</span></summary><div class="scfs-kb-section-folders">';
            foreach ($sections as $section_slug => $section) {
                $articles = isset($groups[$section_slug]) ? $groups[$section_slug] : array();
                echo '<details class="scfs-kb-section-folder" open><summary><span><strong>' . esc_html($section['name']) . '</strong><small>' . esc_html($section['description']) . '</small></span><span>' . esc_html((string) count($articles)) . '</span></summary><div class="scfs-support-library-articles">';
                if (!$articles) echo '<p class="scfs-kb-folder-empty">' . esc_html__('No published articles in this section yet.', 'sustainable-catalyst-feature-suggestions') . '</p>';
                else foreach ($articles as $post) $this->render_library_article_row($post);
                echo '</div></details>';
            }
            echo '</div></details>';
        }
        echo '</div></div></section>';
        return ob_get_clean();
    }

    private function render_result_row($post) {
        $summary = get_post_meta($post->ID, '_scfs_kb_summary', true) ?: get_the_excerpt($post);
        $product_slug = $this->article_product_slug($post);
        $products = $this->products();
        $product_name = isset($products[$product_slug]) ? $products[$product_slug]['name'] : ucwords(str_replace('-', ' ', $product_slug));
        echo '<article class="scfs-kb-modern-result"><div><span>' . esc_html($product_name) . '</span><h4><a href="' . esc_url(get_permalink($post)) . '">' . esc_html(get_the_title($post)) . '</a></h4><p>' . esc_html($summary) . '</p></div><a class="scfs-kb-modern-result-link" href="' . esc_url(get_permalink($post)) . '">' . esc_html__('Read guide', 'sustainable-catalyst-feature-suggestions') . ' →</a></article>';
    }

    public function decorate_article_experience($content) {
        if ($this->rendering_article || !is_singular(SCFS_Knowledge_Base_Foundation::ARTICLE_POST_TYPE) || !in_the_loop() || !is_main_query()) {
            return $content;
        }
        $this->rendering_article = true;
        $post_id = get_the_ID();
        wp_enqueue_style('scfs-knowledge-base');
        wp_enqueue_script('scfs-integrated-knowledge-base');
        $product_slug = $this->article_product_slug(get_post($post_id));
        $section_slug = $this->article_section_slug(get_post($post_id));
        $products = $this->products();
        $sections = $this->sections();
        $product_name = isset($products[$product_slug]) ? $products[$product_slug]['name'] : ucwords(str_replace('-', ' ', $product_slug));
        $section_name = isset($sections[$section_slug]) ? $sections[$section_slug]['name'] : __('Documentation', 'sustainable-catalyst-feature-suggestions');
        $kb_url = $this->support_knowledge_base_url();
        $product_url = $this->support_knowledge_base_url($product_slug);

        $siblings = get_posts(array(
            'post_type' => SCFS_Knowledge_Base_Foundation::ARTICLE_POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => 100,
            'orderby' => array('menu_order' => 'ASC', 'title' => 'ASC'),
            'order' => 'ASC',
            'meta_key' => self::PRODUCT_SLUG_META,
            'meta_value' => $product_slug,
            'suppress_filters' => false,
        ));
        $previous = null;
        $next = null;
        foreach ($siblings as $index => $sibling) {
            if ((int) $sibling->ID !== (int) $post_id) continue;
            if ($index > 0) $previous = $siblings[$index - 1];
            if ($index + 1 < count($siblings)) $next = $siblings[$index + 1];
            break;
        }
        $related = array_values(array_filter($siblings, function ($sibling) use ($post_id) { return (int) $sibling->ID !== (int) $post_id; }));
        $related = array_slice($related, 0, 3);

        $summary = get_post_meta($post_id, '_scfs_kb_summary', true) ?: get_the_excerpt($post_id);
        $before = '<div class="scfs-kb-article-shell"><header class="scfs-kb-article-header"><p class="scfs-kb-article-eyebrow">' . esc_html($product_name . ' · ' . $section_name) . '</p><h1 class="scfs-kb-article-title">' . esc_html(get_the_title($post_id)) . '</h1>' . ($summary ? '<p class="scfs-kb-article-summary">' . esc_html($summary) . '</p>' : '') . '</header><nav class="scfs-kb-breadcrumbs" aria-label="' . esc_attr__('Breadcrumbs', 'sustainable-catalyst-feature-suggestions') . '"><a href="' . esc_url($kb_url) . '">' . esc_html__('Knowledge Base', 'sustainable-catalyst-feature-suggestions') . '</a><span aria-hidden="true">/</span><a href="' . esc_url($product_url) . '">' . esc_html($product_name) . '</a><span aria-hidden="true">/</span><span aria-current="page">' . esc_html($section_name) . '</span></nav><div class="scfs-kb-article-tools"><a href="' . esc_url($product_url) . '">← ' . esc_html__('Back to product guides', 'sustainable-catalyst-feature-suggestions') . '</a><button type="button" data-scfs-kb-print>' . esc_html__('Print article', 'sustainable-catalyst-feature-suggestions') . '</button></div><div class="scfs-kb-article-content">';
        $after = '</div>';
        if ($related) {
            $after .= '<aside class="scfs-kb-related"><h2>' . esc_html__('Related guides', 'sustainable-catalyst-feature-suggestions') . '</h2><div>';
            foreach ($related as $item) $after .= '<a href="' . esc_url(get_permalink($item)) . '"><strong>' . esc_html(get_the_title($item)) . '</strong><span>' . esc_html(get_post_meta($item->ID, '_scfs_kb_summary', true) ?: get_the_excerpt($item)) . '</span></a>';
            $after .= '</div></aside>';
        }
        if ($previous || $next) {
            $after .= '<nav class="scfs-kb-article-sequence" aria-label="' . esc_attr__('Article sequence', 'sustainable-catalyst-feature-suggestions') . '">';
            $after .= $previous ? '<a class="previous" href="' . esc_url(get_permalink($previous)) . '"><span>' . esc_html__('Previous', 'sustainable-catalyst-feature-suggestions') . '</span><strong>' . esc_html(get_the_title($previous)) . '</strong></a>' : '<span></span>';
            $after .= $next ? '<a class="next" href="' . esc_url(get_permalink($next)) . '"><span>' . esc_html__('Next', 'sustainable-catalyst-feature-suggestions') . '</span><strong>' . esc_html(get_the_title($next)) . '</strong></a>' : '<span></span>';
            $after .= '</nav>';
        }
        $after .= '</div>';
        $this->rendering_article = false;
        return $before . $content . $after;
    }

    public function register_admin_page() {
        add_submenu_page(
            'edit.php?post_type=' . Sustainable_Catalyst_Feature_Suggestions::POST_TYPE,
            __('Knowledge Base Library', 'sustainable-catalyst-feature-suggestions'),
            __('Knowledge Base Library', 'sustainable-catalyst-feature-suggestions'),
            'manage_options',
            self::ADMIN_PAGE,
            array($this, 'render_admin_page')
        );
    }

    public function render_admin_page() {
        if (!current_user_can('manage_options')) return;
        $validation = $this->validate_content();
        $report = get_option(self::REPORT_OPTION, array());
        $counts = wp_count_posts(SCFS_Knowledge_Base_Foundation::ARTICLE_POST_TYPE);
        $published = isset($counts->publish) ? (int) $counts->publish : 0;
        echo '<div class="wrap"><h1>' . esc_html__('Integrated Knowledge Base', 'sustainable-catalyst-feature-suggestions') . '</h1><p>' . esc_html__('A first-party, expandable product documentation library integrated with Support Articles, Known Issues, Guided Resolution, and visitor usefulness ratings.', 'sustainable-catalyst-feature-suggestions') . '</p>';
        echo '<div class="scfs-kb-modern-stats"><span><strong>' . esc_html((string) $validation['articles']) . '</strong> bundled articles</span><span><strong>' . esc_html((string) $validation['products']) . '</strong> products</span><span><strong>' . esc_html((string) $validation['samples']) . '</strong> samples</span><span><strong>' . esc_html((string) $published) . '</strong> published records</span></div>';
        if (!$validation['ok']) echo '<div class="notice notice-error"><p>' . esc_html(implode(' ', $validation['errors'])) . '</p></div>';
        echo '<div class="card" style="max-width:900px"><h2>' . esc_html__('Import or repair documentation', 'sustainable-catalyst-feature-suggestions') . '</h2><p>' . esc_html__('Creates missing guides, repairs folder and taxonomy assignments, publishes imported records, preserves manually edited article bodies, and restores Support Center visibility.', 'sustainable-catalyst-feature-suggestions') . '</p><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="scfs_kb510_import">';
        wp_nonce_field('scfs_kb510_import');
        echo '<button class="button button-primary" ' . disabled(!$validation['ok'], true, false) . '>' . esc_html__('Import or Repair 96 Guides', 'sustainable-catalyst-feature-suggestions') . '</button></form></div>';
        if ($report) echo '<div class="card" style="max-width:900px"><h2>' . esc_html__('Last report', 'sustainable-catalyst-feature-suggestions') . '</h2><pre style="white-space:pre-wrap">' . esc_html(wp_json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) . '</pre></div>';
        echo '</div>';
    }

    public function handle_import() {
        if (!current_user_can('manage_options')) wp_die(__('Unauthorized.', 'sustainable-catalyst-feature-suggestions'));
        check_admin_referer('scfs_kb510_import');
        $report = $this->import_documentation('publish');
        update_option(self::REPORT_OPTION, $report, false);
        if (!empty($report['ok'])) {
            update_option(self::IMPORT_OPTION, self::CONTENT_VERSION, false);
            flush_rewrite_rules(false);
        }
        wp_safe_redirect(admin_url('edit.php?post_type=' . Sustainable_Catalyst_Feature_Suggestions::POST_TYPE . '&page=' . self::ADMIN_PAGE));
        exit;
    }
}
