<?php
// Lightweight bootstrap test: verifies the plugin can load without a WordPress runtime.
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

require dirname(__DIR__) . '/wordpress/sustainable-catalyst-feature-suggestions/sustainable-catalyst-feature-suggestions.php';

$checks = array(
    'main class loaded' => class_exists('Sustainable_Catalyst_Feature_Suggestions'),
    'version is 3.1.0' => Sustainable_Catalyst_Feature_Suggestions::VERSION === '3.1.0',
    'product integration loaded' => class_exists('SCFS_Product_Integration'),
    'forms loaded' => class_exists('SCFS_Forms_Foundation'),
    'governance loaded' => class_exists('SCFS_Platform_Governance'),
);
foreach ($checks as $label => $passed) {
    echo ($passed ? 'PASS' : 'FAIL') . " - {$label}\n";
    if (!$passed) exit(1);
}
$schema = SCFS_Product_Integration::instance()->taxonomy_schema();
if (($schema['schema'] ?? '') !== 'scfs-product-taxonomy/1.0') {
    fwrite(STDERR, "FAIL - taxonomy schema\n");
    exit(1);
}
echo "PASS - taxonomy schema\n6 checks passed.\n";
