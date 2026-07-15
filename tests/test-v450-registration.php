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

require dirname(__DIR__) . '/wordpress/sustainable-catalyst-feature-suggestions/sustainable-catalyst-feature-suggestions.php';
$orchestration = SCFS_Cross_Product_Support_Orchestration::instance();
$orchestration->register_rest_routes();
$schema = $orchestration->schema_record();
$checks = array(
    'schema route registered' => isset($GLOBALS['scfs_routes']['scfs/v1/cross-product/schema']),
    'overview route registered' => isset($GLOBALS['scfs_routes']['scfs/v1/cross-product/overview']),
    'incidents route registered' => isset($GLOBALS['scfs_routes']['scfs/v1/cross-product/incidents']),
    'routes route registered' => isset($GLOBALS['scfs_routes']['scfs/v1/cross-product/routes']),
    'journey route registered' => isset($GLOBALS['scfs_routes']['scfs/v1/cross-product/journey']),
    'snapshot route registered' => isset($GLOBALS['scfs_routes']['scfs/v1/cross-product/snapshot']),
    'cross product incidents declared' => in_array('cross_product_incidents', $schema['capabilities'] ?? array(), true),
    'shared components declared' => in_array('shared_component_relationships', $schema['capabilities'] ?? array(), true),
    'journeys declared' => in_array('cross_product_resolution_journeys', $schema['capabilities'] ?? array(), true),
    'private case content excluded' => ($schema['privacy']['private_case_content_stored'] ?? true) === false,
    'automatic incident declaration disabled' => ($schema['governance']['automatic_incident_declaration'] ?? true) === false,
    'automatic release blocking disabled' => ($schema['governance']['automatic_release_blocking'] ?? true) === false,
    'manager capability defaults to manage options' => $orchestration->admin_capability() === 'manage_options',
    'REST permission uses manager capability' => $orchestration->rest_permission() === true && $GLOBALS['scfs_capability'] === 'manage_options',
);
foreach ($checks as $label => $passed) {
    echo ($passed ? 'PASS' : 'FAIL') . " - {$label}\n";
    if (!$passed) exit(1);
}
echo count($checks) . " checks passed.\n";
