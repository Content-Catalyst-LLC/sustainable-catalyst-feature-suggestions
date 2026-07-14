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

require dirname(__DIR__) . '/wordpress/sustainable-catalyst-feature-suggestions/sustainable-catalyst-feature-suggestions.php';
$checks = array(
    'main class loaded' => class_exists('Sustainable_Catalyst_Feature_Suggestions'),
    'version is 3.4.0' => Sustainable_Catalyst_Feature_Suggestions::VERSION === '3.4.0',
    'product integration loaded' => class_exists('SCFS_Product_Integration'),
    'knowledge base loaded' => class_exists('SCFS_Knowledge_Base_Foundation'),
    'guided resolution loaded' => class_exists('SCFS_Guided_Resolution'),
    'documentation intelligence loaded' => class_exists('SCFS_Documentation_Feature_Intelligence'),
    'opportunity workflow loaded' => class_exists('SCFS_Opportunity_Workflow'),
    'governance loaded' => class_exists('SCFS_Platform_Governance'),
);
foreach ($checks as $label => $passed) {
    echo ($passed ? 'PASS' : 'FAIL') . " - {$label}\n";
    if (!$passed) exit(1);
}
$schema = SCFS_Documentation_Feature_Intelligence::instance()->schema_record();
if (($schema['schema'] ?? '') !== 'scfs-documentation-feature-intelligence/1.0') {
    fwrite(STDERR, "FAIL - documentation intelligence schema\n"); exit(1);
}
if (!in_array('support_demand_opportunity_scoring', $schema['capabilities'] ?? array(), true)) {
    fwrite(STDERR, "FAIL - support demand capability\n"); exit(1);
}
if (($schema['privacy']['case_content_stored'] ?? true) !== false) {
    fwrite(STDERR, "FAIL - private case boundary\n"); exit(1);
}
echo "PASS - documentation intelligence schema\nPASS - support demand capability\nPASS - private case boundary\n11 checks passed.\n";
