<?php
// Render smoke test for the guided-resolution form before a query is submitted.
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
function esc_html($text) { return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8'); }
function esc_attr($text) { return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8'); }
function esc_textarea($text) { return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8'); }
function esc_url($text) { return (string) $text; }
function shortcode_atts($defaults, $atts) { return array_merge($defaults, (array) $atts); }
function wp_enqueue_style() { return true; }
function sanitize_text_field($value) { return trim(strip_tags((string) $value)); }
function sanitize_textarea_field($value) { return trim(strip_tags((string) $value)); }
function wp_unslash($value) { return $value; }
function sanitize_title($value) { return strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', (string) $value), '-')); }
function get_terms() { return array(); }
function is_wp_error() { return false; }
function selected($a, $b, $echo = true) { return (string) $a === (string) $b ? 'selected="selected"' : ''; }
function remove_query_arg() { return '/support-knowledge-base/'; }
require dirname(__DIR__) . '/wordpress/sustainable-catalyst-feature-suggestions/sustainable-catalyst-feature-suggestions.php';
$html = SCFS_Guided_Resolution::instance()->render_shortcode();
$checks = array(
    'guided wrapper' => strpos($html, 'class="scfs-resolution') !== false,
    'query field' => strpos($html, 'name="scfs_resolution_query"') !== false,
    'error field' => strpos($html, 'name="scfs_resolution_error"') !== false,
    'product filter' => strpos($html, 'name="scfs_resolution_product"') !== false,
    'version filter' => strpos($html, 'name="scfs_resolution_version"') !== false,
    'component filter' => strpos($html, 'name="scfs_resolution_component"') !== false,
    'privacy warning' => strpos($html, 'Remove keys, passwords, personal data') !== false,
);
foreach ($checks as $label => $passed) {
    echo ($passed ? 'PASS' : 'FAIL') . " - {$label}\n";
    if (!$passed) exit(1);
}
echo count($checks) . " checks passed.\n";
