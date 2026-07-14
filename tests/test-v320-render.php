<?php
// Render smoke test for the public Knowledge Base with an empty dataset.
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
function esc_html__($text) { return $text; }
function esc_attr__($text) { return $text; }
function esc_html($text) { return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8'); }
function esc_attr($text) { return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8'); }
function esc_url($text) { return (string) $text; }
function shortcode_atts($defaults, $atts) { return array_merge($defaults, (array) $atts); }
function wp_enqueue_style() { return true; }
function sanitize_text_field($value) { return trim(strip_tags((string) $value)); }
function wp_unslash($value) { return $value; }
function sanitize_title($value) { return strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', (string) $value), '-')); }
function absint($value) { return abs((int) $value); }
function get_terms() { return array(); }
function is_wp_error() { return false; }
function selected($a, $b, $echo = true) { return (string) $a === (string) $b ? 'selected="selected"' : ''; }
function remove_query_arg() { return '/support-knowledge-base/'; }
function _n($single, $plural, $number) { return ((int) $number === 1) ? $single : $plural; }
class WP_Query {
    public $posts = array();
    public $found_posts = 0;
    public $max_num_pages = 0;
    public function __construct($args = array()) {}
    public function have_posts() { return false; }
}
require dirname(__DIR__) . '/wordpress/sustainable-catalyst-feature-suggestions/sustainable-catalyst-feature-suggestions.php';
$html = SCFS_Knowledge_Base_Foundation::instance()->render_shortcode();
$checks = array(
    'knowledge base wrapper' => strpos($html, 'class="scfs-kb"') !== false,
    'search field' => strpos($html, 'name="scfs_kb_search"') !== false,
    'product filter' => strpos($html, 'name="scfs_kb_product"') !== false,
    'empty state' => strpos($html, 'No matching documentation found') !== false,
    'privacy-safe public copy' => strpos($html, 'Support Knowledge Base') !== false,
);
foreach ($checks as $label => $passed) {
    echo ($passed ? 'PASS' : 'FAIL') . " - {$label}\n";
    if (!$passed) exit(1);
}
echo count($checks) . " checks passed.\n";
