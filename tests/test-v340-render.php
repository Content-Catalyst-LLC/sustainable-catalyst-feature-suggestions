<?php
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
function esc_url($text) { return (string) $text; }
function selected($a, $b, $echo = true) { return (string) $a === (string) $b ? 'selected="selected"' : ''; }
function checked($a, $b, $echo = true) { return (string) $a === (string) $b ? 'checked="checked"' : ''; }
function is_singular($type) { return $type === 'sc_support_article'; }
function in_the_loop() { return true; }
function is_main_query() { return true; }
function get_the_ID() { return 42; }
function get_option($key, $default = array()) { return $default; }
function wp_parse_args($args, $defaults) { return array_merge($defaults, (array) $args); }
function wp_enqueue_style() { return true; }
function admin_url($path = '') { return 'https://example.test/wp-admin/' . ltrim($path, '/'); }
function wp_nonce_field($action, $name) { echo '<input type="hidden" name="' . $name . '" value="nonce">'; }
function absint($value) { return abs((int) $value); }
function sanitize_key($value) { return strtolower(preg_replace('/[^a-z0-9_\-]/', '', (string) $value)); }
function wp_unslash($value) { return $value; }

require dirname(__DIR__) . '/wordpress/sustainable-catalyst-feature-suggestions/sustainable-catalyst-feature-suggestions.php';
$html = SCFS_Documentation_Feature_Intelligence::instance()->append_article_feedback('<p>Article body</p>');
$checks = array(
    'article body retained' => strpos($html, 'Article body') !== false,
    'feedback wrapper' => strpos($html, 'scfs-article-feedback') !== false,
    'helpful question' => strpos($html, 'Was this article helpful?') !== false,
    'yes action' => strpos($html, 'value="1"') !== false,
    'no action' => strpos($html, 'value="0"') !== false,
    'reason selector' => strpos($html, 'name="reason"') !== false,
    'comment field' => strpos($html, 'name="comment"') !== false,
    'privacy warning' => strpos($html, 'credentials, private logs') !== false,
    'honeypot present' => strpos($html, 'scfs-feedback-honeypot') !== false,
);
foreach ($checks as $label => $passed) {
    echo ($passed ? 'PASS' : 'FAIL') . " - {$label}\n";
    if (!$passed) exit(1);
}
echo count($checks) . " checks passed.\n";
