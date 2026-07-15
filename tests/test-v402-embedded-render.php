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
function sanitize_key($value) { return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $value)); }
function sanitize_title($value) { return sanitize_key(str_replace(' ', '-', $value)); }
function wp_unslash($value) { return $value; }
function absint($value) { return abs((int) $value); }
function home_url($path = '') { return 'https://example.test' . $path; }
function get_option($key, $default = array()) { return $default; }
function wp_parse_args($args, $defaults = array()) { return array_merge($defaults, is_array($args) ? $args : array()); }
function shortcode_atts($pairs, $atts) { return array_merge($pairs, is_array($atts) ? $atts : array()); }
function wp_enqueue_style() { return true; }
function wp_count_posts() { return (object) array('publish' => 0, 'pending' => 0, 'draft' => 0, 'private' => 0); }
function post_type_exists() { return false; }
function get_terms() { return array(); }
function is_wp_error() { return false; }
function selected($a, $b, $echo = true) { return (string) $a === (string) $b ? 'selected="selected"' : ''; }
function remove_query_arg() { return 'https://example.test/support/'; }
function add_query_arg($args, $url = '') { return rtrim((string) $url, '?') . '?' . http_build_query((array) $args); }

require dirname(__DIR__) . '/wordpress/sustainable-catalyst-feature-suggestions/sustainable-catalyst-feature-suggestions.php';
$html = SCFS_Product_Support_Platform::instance()->render_shortcode(array(
    'mode' => 'embedded',
    'branding' => 'sustainable-catalyst',
    'default_view' => 'private-support',
    'show_product_filter' => '0',
));
$checks = array(
    'embedded wrapper class' => strpos($html, 'scfs-support-platform--mode-embedded') !== false,
    'site branding class' => strpos($html, 'scfs-support-platform--brand-sustainable-catalyst') !== false,
    'branding data attribute' => strpos($html, 'data-scfs-branding="sustainable-catalyst"') !== false,
    'scoped accent token' => strpos($html, '--scfs-token-accent:#9b1111') !== false,
    'page width class' => strpos($html, 'scfs-support-platform--page-width') !== false,
    'duplicate hero omitted' => strpos($html, 'scfs-support-platform__hero') === false,
    'status metrics omitted' => strpos($html, 'scfs-support-platform__status') === false,
    'screen reader title retained' => strpos($html, 'scfs-support-platform__sr-only') !== false,
    'compact navigation present' => strpos($html, 'scfs-support-platform__nav') !== false,
    'navigation descriptions omitted' => strpos($html, 'Status and starting points') === false,
    'private support view rendered' => strpos($html, 'Continue to Contact and Engagement') !== false,
    'product filter omitted by shortcode' => strpos($html, 'scfs-support-product-filter') === false,
);
foreach ($checks as $label => $passed) {
    echo ($passed ? 'PASS' : 'FAIL') . " - {$label}\n";
    if (!$passed) exit(1);
}
echo count($checks) . " checks passed.\n";
