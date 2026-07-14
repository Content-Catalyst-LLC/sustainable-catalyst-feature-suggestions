<?php
// Lightweight bootstrap test: verifies the complete plugin can load without WordPress.
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
    'version is 3.2.0' => Sustainable_Catalyst_Feature_Suggestions::VERSION === '3.2.0',
    'product integration loaded' => class_exists('SCFS_Product_Integration'),
    'knowledge base loaded' => class_exists('SCFS_Knowledge_Base_Foundation'),
    'forms loaded' => class_exists('SCFS_Forms_Foundation'),
    'governance loaded' => class_exists('SCFS_Platform_Governance'),
);
foreach ($checks as $label => $passed) {
    echo ($passed ? 'PASS' : 'FAIL') . " - {$label}\n";
    if (!$passed) exit(1);
}
$schema = SCFS_Knowledge_Base_Foundation::instance()->schema_record();
if (($schema['schema'] ?? '') !== 'scfs-support-knowledge-base/1.0') {
    fwrite(STDERR, "FAIL - knowledge base schema\n");
    exit(1);
}
if (($schema['post_types']['support_article'] ?? '') !== 'sc_support_article') {
    fwrite(STDERR, "FAIL - support article schema\n");
    exit(1);
}
echo "PASS - knowledge base schema\nPASS - support article schema\n8 checks passed.\n";
