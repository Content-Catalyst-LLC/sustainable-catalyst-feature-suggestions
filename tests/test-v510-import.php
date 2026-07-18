<?php
define('ABSPATH', __DIR__ . '/');
define('OBJECT', 'OBJECT');
class WP_Error {
    private $code; private $message;
    public function __construct($code, $message = '') { $this->code = $code; $this->message = $message; }
    public function get_error_message() { return $this->message; }
    public function get_error_code() { return $this->code; }
}
class SCFS_Knowledge_Base_Foundation {
    const ARTICLE_POST_TYPE = 'sc_support_article';
    const ARTICLE_TYPE_TAXONOMY = 'scfs_article_type';
    const COLLECTION_TAXONOMY = 'scfs_doc_collection';
}
class SCFS_Product_Integration {
    const PRODUCT_TAXONOMY = 'scfs_product';
    const VERSION_TAXONOMY = 'scfs_product_version';
    const COMPONENT_TAXONOMY = 'scfs_component';
    const ISSUE_TAXONOMY = 'scfs_issue_type';
}
class SCFS_Editorial_Governance { const SCHEMA_VERSION = '1.0'; }
class Sustainable_Catalyst_Feature_Suggestions { const POST_TYPE = 'sc_feature_suggest'; }
$GLOBALS['scfs_posts'] = array();
$GLOBALS['scfs_meta'] = array();
$GLOBALS['scfs_terms'] = array();
$GLOBALS['scfs_term_meta'] = array();
$GLOBALS['scfs_object_terms'] = array();
$GLOBALS['scfs_options'] = array();
$GLOBALS['scfs_next_post_id'] = 1;
$GLOBALS['scfs_next_term_id'] = 1;
function add_action() { return true; }
function add_filter() { return true; }
function plugin_dir_path($file) { return dirname($file) . '/'; }
function plugins_url($path, $file = '') { return 'https://example.test/wp-content/plugins/sustainable-catalyst-feature-suggestions/' . ltrim(str_replace('../', '', $path), '/'); }
function __($text) { return $text; }
function is_wp_error($value) { return $value instanceof WP_Error; }
function sanitize_title($value) { $value = strtolower(trim((string) $value)); $value = preg_replace('/[^a-z0-9]+/', '-', $value); return trim($value, '-'); }
function absint($value) { return abs((int) $value); }
function esc_url($value) { return (string) $value; }
function wp_slash($value) { return $value; }
function current_time($type, $gmt = false) { return '2026-07-17 18:00:00'; }
function wp_json_encode($value, $flags = 0) { return json_encode($value, $flags); }
function get_posts($args = array()) {
    $found = array();
    foreach ($GLOBALS['scfs_posts'] as $id => $post) {
        if (!empty($args['post_type']) && $post->post_type !== $args['post_type']) continue;
        if (!empty($args['post_status'])) {
            $statuses = (array) $args['post_status'];
            if (!in_array($post->post_status, $statuses, true)) continue;
        }
        if (!empty($args['meta_key'])) {
            $value = $GLOBALS['scfs_meta'][$id][$args['meta_key']] ?? null;
            if ((string) $value !== (string) ($args['meta_value'] ?? '')) continue;
        }
        $found[] = !empty($args['fields']) && $args['fields'] === 'ids' ? $id : clone $post;
    }
    if (!empty($args['posts_per_page']) && $args['posts_per_page'] > 0) $found = array_slice($found, 0, (int) $args['posts_per_page']);
    return $found;
}
function get_page_by_path($slug, $output = OBJECT, $post_type = '') {
    foreach ($GLOBALS['scfs_posts'] as $post) if ($post->post_name === $slug && (!$post_type || $post->post_type === $post_type)) return clone $post;
    return null;
}
function get_post($id) { return isset($GLOBALS['scfs_posts'][$id]) ? clone $GLOBALS['scfs_posts'][$id] : null; }
function get_post_status($id) { return isset($GLOBALS['scfs_posts'][$id]) ? $GLOBALS['scfs_posts'][$id]->post_status : false; }
function wp_insert_post($payload, $wp_error = false) {
    $id = $GLOBALS['scfs_next_post_id']++;
    $post = (object) array_merge(array('ID' => $id, 'post_type' => 'post', 'post_status' => 'draft', 'post_title' => '', 'post_name' => '', 'post_excerpt' => '', 'post_content' => '', 'menu_order' => 0), $payload);
    $post->ID = $id;
    $GLOBALS['scfs_posts'][$id] = $post;
    return $id;
}
function wp_update_post($payload, $wp_error = false) {
    $id = (int) ($payload['ID'] ?? 0);
    if (!$id || !isset($GLOBALS['scfs_posts'][$id])) return new WP_Error('missing', 'Post not found');
    foreach ($payload as $key => $value) if ($key !== 'ID') $GLOBALS['scfs_posts'][$id]->{$key} = $value;
    return $id;
}
function get_post_meta($id, $key, $single = true) { return $GLOBALS['scfs_meta'][$id][$key] ?? ''; }
function update_post_meta($id, $key, $value) { $GLOBALS['scfs_meta'][$id][$key] = $value; return true; }
function taxonomy_exists($taxonomy) { return true; }
function get_term_by($field, $value, $taxonomy) { return isset($GLOBALS['scfs_terms'][$taxonomy][$value]) ? clone $GLOBALS['scfs_terms'][$taxonomy][$value] : false; }
function wp_insert_term($name, $taxonomy, $args = array()) {
    $slug = $args['slug'] ?? sanitize_title($name);
    if (isset($GLOBALS['scfs_terms'][$taxonomy][$slug])) return new WP_Error('exists', 'Term exists');
    $id = $GLOBALS['scfs_next_term_id']++;
    $term = (object) array('term_id' => $id, 'name' => $name, 'slug' => $slug, 'parent' => (int) ($args['parent'] ?? 0), 'description' => (string) ($args['description'] ?? ''));
    $GLOBALS['scfs_terms'][$taxonomy][$slug] = $term;
    return array('term_id' => $id, 'term_taxonomy_id' => $id);
}
function get_term($id, $taxonomy = '') {
    foreach ($GLOBALS['scfs_terms'] as $tax => $terms) foreach ($terms as $term) if ((int) $term->term_id === (int) $id && (!$taxonomy || $taxonomy === $tax)) return clone $term;
    return null;
}
function wp_update_term($id, $taxonomy, $changes) {
    foreach ($GLOBALS['scfs_terms'][$taxonomy] as $slug => $term) {
        if ((int) $term->term_id !== (int) $id) continue;
        foreach ($changes as $key => $value) $term->{$key} = $value;
        $GLOBALS['scfs_terms'][$taxonomy][$slug] = $term;
        return array('term_id' => $id);
    }
    return new WP_Error('missing_term', 'Term not found');
}
function get_term_meta($id, $key, $single = true) { return $GLOBALS['scfs_term_meta'][$id][$key] ?? ''; }
function update_term_meta($id, $key, $value) { $GLOBALS['scfs_term_meta'][$id][$key] = $value; return true; }
function wp_set_object_terms($post_id, $terms, $taxonomy, $append = false) { $GLOBALS['scfs_object_terms'][$post_id][$taxonomy] = array_map('intval', (array) $terms); return $terms; }
function update_option($key, $value, $autoload = null) { $GLOBALS['scfs_options'][$key] = $value; return true; }
function get_option($key, $default = false) { return $GLOBALS['scfs_options'][$key] ?? $default; }
require dirname(__DIR__) . '/wordpress/sustainable-catalyst-feature-suggestions/includes/class-scfs-integrated-knowledge-base.php';
$kb = SCFS_Integrated_Knowledge_Base::instance();
$first = $kb->import_documentation('publish');
$first_post = $GLOBALS['scfs_posts'][1];
$first_content = $first_post->post_content;
$GLOBALS['scfs_posts'][1]->post_content .= '<p>Human editorial note.</p>';
$second = $kb->import_documentation('publish');
$manual_preserved = strpos($GLOBALS['scfs_posts'][1]->post_content, 'Human editorial note.') !== false;
$product_folders = $GLOBALS['scfs_terms'][SCFS_Knowledge_Base_Foundation::COLLECTION_TAXONOMY] ?? array();
$parent_count = 0; $child_count = 0;
foreach ($product_folders as $term) { if ((int) $term->parent === 0) $parent_count++; else $child_count++; }
$sample_links_direct = strpos($first_content, 'wp-content/plugins/sustainable-catalyst-feature-suggestions/content/knowledge-base/samples/') !== false;
$checks = array(
    'first import succeeded' => !empty($first['ok']),
    '96 articles created' => (int) $first['created'] === 96 && count($GLOBALS['scfs_posts']) === 96,
    '96 articles published' => (int) $first['published'] === 96,
    'second import remained successful' => !empty($second['ok']),
    'second import created no duplicates' => (int) $second['created'] === 0 && count($GLOBALS['scfs_posts']) === 96,
    'manual edit detected and preserved' => (int) $second['manual_edits_preserved'] >= 1 && $manual_preserved,
    '16 product collection folders created' => $parent_count === 16,
    '96 section collection folders created' => $child_count === 96,
    'sample URLs are direct plugin links' => $sample_links_direct,
    'published editorial state stored' => get_post_meta(1, '_scfs_editorial_state', true) === 'published',
    'integrated content key stored' => get_post_meta(1, SCFS_Integrated_Knowledge_Base::CONTENT_KEY_META, true) !== '',
);
foreach ($checks as $label => $passed) {
    echo ($passed ? 'PASS' : 'FAIL') . " - {$label}\n";
    if (!$passed) exit(1);
}
echo count($checks) . " checks passed.\n";
