<?php
define('ABSPATH', __DIR__ . '/');
class WP_Query {
    public $posts = array();
    public $found_posts = 0;
    public function __construct($args = array()) {}
    public function have_posts() { return false; }
}
class SCFS_Test_Request {
    private $params;
    public function __construct($params) { $this->params = $params; }
    public function get_param($key) { return $this->params[$key] ?? null; }
}
function add_action() { return true; }
function add_filter() { return true; }
function add_shortcode() { return true; }
function register_activation_hook() { return true; }
function register_deactivation_hook() { return true; }
function plugin_dir_path($file) { return dirname($file) . '/'; }
function plugin_basename($file) { return basename($file); }
function apply_filters($hook, $value) { return $value; }
function __($text) { return $text; }
function esc_html__($text) { return $text; }
function esc_html($text) { return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8'); }
function esc_attr($text) { return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8'); }
function esc_url($text) { return (string) $text; }
function esc_url_raw($text) { return (string) $text; }
function sanitize_key($value) { return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $value)); }
function sanitize_title($value) { return sanitize_key(str_replace(' ', '-', (string) $value)); }
function wp_unslash($value) { return $value; }
function absint($value) { return abs((int) $value); }
function home_url($path = '') { return 'https://example.test' . $path; }
function get_option($key, $default = array()) { return $default; }
function wp_parse_args($args, $defaults = array()) { return array_merge($defaults, is_array($args) ? $args : array()); }
function wp_count_posts() { return (object) array('publish' => 0, 'pending' => 0, 'draft' => 0, 'private' => 0); }
function post_type_exists() { return false; }
function remove_query_arg($keys = array(), $url = '') { return $url ?: 'https://example.test/support/'; }
function add_query_arg($args, $url = '') { return rtrim((string) $url, '?') . '?' . http_build_query((array) $args); }
function rest_ensure_response($value) { return $value; }

require dirname(__DIR__) . '/wordpress/sustainable-catalyst-feature-suggestions/sustainable-catalyst-feature-suggestions.php';
$request = new SCFS_Test_Request(array(
    'view' => 'private-support',
    'product' => 'workbench',
    'survey' => 0,
    'base_url' => 'https://example.test/support/',
    'anchor' => 'support-center',
    'show_overview_pathways' => '0',
));
$result = SCFS_Product_Support_Platform::instance()->rest_view($request);
$checks = array(
    'view response schema' => ($result['schema'] ?? '') === 'scfs-product-support-view/1.0',
    'view response version' => ($result['version'] ?? '') === '5.2.9',
    'requested view retained' => ($result['view'] ?? '') === 'private-support',
    'product retained' => ($result['product'] ?? '') === 'workbench',
    'private support rendered' => strpos($result['html'] ?? '', 'Continue to Contact and Engagement') !== false,
    'direct URL anchored' => strpos($result['url'] ?? '', 'scfs_support_view=private-support') !== false && substr($result['url'], -15) === '#support-center',
    'private case content protected' => ($result['private_case_content_exposed'] ?? true) === false,
);
foreach ($checks as $label => $passed) {
    echo ($passed ? 'PASS' : 'FAIL') . " - {$label}\n";
    if (!$passed) exit(1);
}
echo count($checks) . " checks passed.\n";
