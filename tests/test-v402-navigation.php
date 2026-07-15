<?php
define('ABSPATH', __DIR__ . '/');
class WP_Query {
    public $posts = array();
    public $found_posts = 0;
    public function __construct($args = array()) {}
    public function have_posts() { return false; }
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
function esc_attr__($text) { return $text; }
function esc_attr($text) { return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8'); }
function esc_url($text) { return (string) $text; }
function esc_url_raw($text) { return (string) $text; }
function sanitize_key($value) { return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $value)); }
function sanitize_title($value) { return sanitize_key(str_replace(' ', '-', $value)); }
function sanitize_text_field($value) { return trim((string) $value); }
function wp_unslash($value) { return $value; }
function absint($value) { return abs((int) $value); }
function home_url($path = '') { return 'https://example.test' . $path; }
function get_option($key, $default = array()) { return $default; }
function wp_parse_args($args, $defaults = array()) { return array_merge($defaults, is_array($args) ? $args : array()); }
function shortcode_atts($pairs, $atts) { return array_merge($pairs, is_array($atts) ? $atts : array()); }
function wp_enqueue_style() { return true; }
function wp_enqueue_script() { return true; }
function wp_count_posts() { return (object) array('publish' => 0, 'pending' => 0, 'draft' => 0, 'private' => 0); }
function post_type_exists() { return false; }
function get_terms() { return array(); }
function is_wp_error() { return false; }
function selected($a, $b, $echo = true) { return (string) $a === (string) $b ? 'selected="selected"' : ''; }
function remove_query_arg($keys = array(), $url = '') { return $url ?: 'https://example.test/support/'; }
function add_query_arg($args, $url = '') { return rtrim((string) $url, '?') . '?' . http_build_query((array) $args); }
function rest_url($path = '') { return 'https://example.test/wp-json/' . ltrim($path, '/'); }

require dirname(__DIR__) . '/wordpress/sustainable-catalyst-feature-suggestions/sustainable-catalyst-feature-suggestions.php';
$html = SCFS_Product_Support_Platform::instance()->render_shortcode(array(
    'mode' => 'embedded',
    'branding' => 'sustainable-catalyst',
    'default_view' => 'private-support',
    'show_product_filter' => '0',
    'anchor' => 'support-center',
));
$checks = array(
    'interactive root enabled' => strpos($html, 'data-scfs-interactive="1"') !== false,
    'view endpoint exposed' => strpos($html, 'data-scfs-endpoint="https://example.test/wp-json/scfs/v1/product-support/view"') !== false,
    'base URL exposed' => strpos($html, 'data-scfs-base-url="https://example.test/support/"') !== false,
    'anchor exposed' => strpos($html, 'data-scfs-anchor="support-center"') !== false,
    'current view exposed' => strpos($html, 'data-scfs-current-view="private-support"') !== false,
    'navigation links carry view data' => strpos($html, 'data-scfs-support-view="documentation"') !== false,
    'fallback link returns to support center' => strpos($html, 'scfs_support_view=documentation#support-center') !== false,
    'workspace announces updates' => strpos($html, 'aria-live="polite"') !== false,
    'workspace exposes active view' => strpos($html, 'data-scfs-view="private-support"') !== false,
);
foreach ($checks as $label => $passed) {
    echo ($passed ? 'PASS' : 'FAIL') . " - {$label}\n";
    if (!$passed) exit(1);
}
echo count($checks) . " checks passed.\n";
