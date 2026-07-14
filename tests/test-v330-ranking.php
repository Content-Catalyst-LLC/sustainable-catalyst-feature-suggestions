<?php
// Deterministic ranking smoke test with a current known issue and a general article.
define('ABSPATH', __DIR__ . '/');
function add_action() { return true; }
function add_filter() { return true; }
function add_shortcode() { return true; }
function register_activation_hook() { return true; }
function register_deactivation_hook() { return true; }
function plugin_dir_path($file) { return dirname($file) . '/'; }
function plugin_basename($file) { return basename($file); }
function apply_filters($hook, $value) { return $value; }
function __($text) { return $text; }
function get_option($key, $default = array()) { return $default; }
function wp_parse_args($args, $defaults) { return array_merge($defaults, (array) $args); }
function home_url($path = '') { return 'https://example.test' . $path; }
function sanitize_text_field($value) { return trim(strip_tags((string) $value)); }
function sanitize_textarea_field($value) { return trim(strip_tags((string) $value)); }
function sanitize_title($value) { return strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', (string) $value), '-')); }
function wp_strip_all_tags($value) { return strip_tags((string) $value); }
function wp_trim_words($value, $count) { return implode(' ', array_slice(preg_split('/\s+/', trim((string) $value)), 0, $count)); }
function absint($value) { return abs((int) $value); }
function is_wp_error() { return false; }
function get_permalink($post) { return 'https://example.test/?p=' . $post->ID; }
function get_terms($args) { return array(); }
function get_the_terms($post_id, $taxonomy) { return array(); }
function wp_get_object_terms($post_id, $taxonomy, $args = array()) { return array(); }
function get_term_meta() { return ''; }
function has_term($slug, $taxonomy, $post_id) {
    $terms = array(
        10 => array('scfs_product' => array('research-librarian'), 'scfs_component' => array('rest-api')),
        20 => array('scfs_product' => array('research-librarian'), 'scfs_component' => array('rest-api')),
    );
    return in_array($slug, $terms[$post_id][$taxonomy] ?? array(), true);
}
function get_post_meta($post_id, $key, $single = true) {
    $meta = array(
        10 => array(
            '_scfs_issue_symptom' => 'Endpoint unavailable and connection refused from WordPress.',
            '_scfs_issue_workaround' => 'Verify the WordPress REST endpoint.',
            '_scfs_issue_status' => 'investigating',
            '_scfs_issue_severity' => 'high',
            '_scfs_error_signatures' => "endpoint unavailable\nconnection refused",
            '_scfs_resolution_priority' => 80,
        ),
        20 => array(
            '_scfs_kb_summary' => 'General endpoint configuration guidance.',
            '_scfs_resolution_priority' => 10,
        ),
    );
    return $meta[$post_id][$key] ?? '';
}
class WP_Query {
    public $posts = array();
    public function __construct($args = array()) {
        if (($args['post_type'] ?? '') === 'sc_known_issue') {
            $this->posts = array((object) array('ID' => 10, 'post_type' => 'sc_known_issue', 'post_title' => 'Research Librarian endpoint unavailable', 'post_content' => 'Connection refused', 'post_excerpt' => ''));
        } elseif (($args['post_type'] ?? '') === 'sc_support_article') {
            $this->posts = array((object) array('ID' => 20, 'post_type' => 'sc_support_article', 'post_title' => 'Configure Research Librarian', 'post_content' => 'General REST endpoint configuration', 'post_excerpt' => ''));
        }
    }
}
require dirname(__DIR__) . '/wordpress/sustainable-catalyst-feature-suggestions/sustainable-catalyst-feature-suggestions.php';
$result = SCFS_Guided_Resolution::instance()->search(array(
    'query' => 'endpoint unavailable',
    'error_message' => 'connection refused',
    'product' => 'research-librarian',
    'product_version' => '',
    'component' => 'rest-api',
    'source' => 'test',
), false);
$issue = $result['groups']['known_issues'][0] ?? array();
$article = $result['groups']['support_articles'][0] ?? array();
$checks = array(
    'known issue ranked' => ($issue['id'] ?? 0) === 10,
    'article ranked' => ($article['id'] ?? 0) === 20,
    'known issue wins' => ($issue['score'] ?? 0) > ($article['score'] ?? 0),
    'strong confidence' => $result['resolution_state'] === 'strong_match',
    'error reason exposed' => in_array('Error signature match', $issue['match_reasons'] ?? array(), true),
);
foreach ($checks as $label => $passed) {
    echo ($passed ? 'PASS' : 'FAIL') . " - {$label}\n";
    if (!$passed) exit(1);
}
echo count($checks) . " checks passed.\n";
