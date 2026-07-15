<?php
define('ABSPATH', __DIR__ . '/');
$GLOBALS['scfs_routes'] = array();
$GLOBALS['scfs_capability'] = '';
function add_action() { return true; }
function add_filter() { return true; }
function add_shortcode() { return true; }
function register_activation_hook() { return true; }
function register_deactivation_hook() { return true; }
function plugin_dir_path($file) { return dirname($file) . '/'; }
function plugin_basename($file) { return basename($file); }
function apply_filters($hook, $value) { return $value; }
function __($text) { return $text; }
function register_rest_route($namespace, $route, $args) { $GLOBALS['scfs_routes'][$namespace . $route] = $args; }
function current_user_can($capability) { $GLOBALS['scfs_capability'] = $capability; return true; }
function is_multisite() { return false; }
function is_network_admin() { return false; }

require dirname(__DIR__) . '/wordpress/sustainable-catalyst-feature-suggestions/sustainable-catalyst-feature-suggestions.php';
$center = SCFS_Support_Reliability_Center::instance();
$center->register_rest_routes();
$schema = $center->schema_record();
$checks = array(
    'schema route registered' => isset($GLOBALS['scfs_routes']['scfs/v1/support-reliability/schema']),
    'dashboard route registered' => isset($GLOBALS['scfs_routes']['scfs/v1/support-reliability/dashboard']),
    'product route registered' => isset($GLOBALS['scfs_routes']['scfs/v1/support-reliability/products/(?P<product>[a-z0-9-]+)']),
    'trend route registered' => isset($GLOBALS['scfs_routes']['scfs/v1/support-reliability/trends/(?P<product>[a-z0-9-]+)']),
    'refresh route registered' => isset($GLOBALS['scfs_routes']['scfs/v1/support-reliability/refresh']),
    'report route registered' => isset($GLOBALS['scfs_routes']['scfs/v1/support-reliability/report']),
    'resolution capability declared' => in_array('support_resolution_rate_analysis', $schema['capabilities'] ?? array(), true),
    'cluster capability declared' => in_array('unresolved_query_clustering', $schema['capabilities'] ?? array(), true),
    'private case content not stored' => ($schema['privacy']['private_case_content_stored'] ?? true) === false,
    'automatic roadmap change disabled' => ($schema['governance']['automatic_roadmap_change'] ?? true) === false,
    'automatic incident declaration disabled' => ($schema['governance']['automatic_incident_declaration'] ?? true) === false,
    'manager capability defaults to manage options' => $center->admin_capability() === 'manage_options',
    'REST permission uses manager capability' => $center->rest_permission() === true && $GLOBALS['scfs_capability'] === 'manage_options',
);
foreach ($checks as $label => $passed) {
    echo ($passed ? 'PASS' : 'FAIL') . " - {$label}\n";
    if (!$passed) exit(1);
}
echo count($checks) . " checks passed.\n";
