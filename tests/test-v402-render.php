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
function esc_attr__($text) { return $text; }
function esc_attr($text) { return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8'); }
function esc_url($text) { return (string) $text; }
function add_query_arg($args, $url = '') { return $url . '?' . http_build_query((array) $args); }

require dirname(__DIR__) . '/wordpress/sustainable-catalyst-feature-suggestions/sustainable-catalyst-feature-suggestions.php';
$platform = SCFS_Product_Support_Platform::instance();
$method = new ReflectionMethod($platform, 'render_private_support');
$method->setAccessible(true);
ob_start();
$method->invoke($platform, array('contact_engagement_url' => 'https://example.test/contact/'), 'workbench');
$html = ob_get_clean();
$checks = array(
    'private support wrapper' => strpos($html, 'scfs-support-platform__private') !== false,
    'contact and engagement heading' => strpos($html, 'Continue to Contact and Engagement') !== false,
    'public system boundary' => strpos($html, 'Feature Suggestions keeps') !== false,
    'private system boundary' => strpos($html, 'Contact and Engagement keeps') !== false,
    'identity and files language' => strpos($html, 'private files') !== false,
    'private support CTA' => strpos($html, 'Open private support intake') !== false,
    'product handoff context' => strpos($html, 'support_product=workbench') !== false,
    'sensitive-data warning' => strpos($html, 'API keys') !== false,
);
foreach ($checks as $label => $passed) {
    echo ($passed ? 'PASS' : 'FAIL') . " - {$label}\n";
    if (!$passed) exit(1);
}
echo count($checks) . " checks passed.\n";
