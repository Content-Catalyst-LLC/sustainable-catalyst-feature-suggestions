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
$ops = SCFS_Connected_Support_Operations::instance();
$ops->register_rest_routes();
$schema = $ops->schema_record();
$checks = array(
    'schema route registered' => isset($GLOBALS['scfs_routes']['scfs/v1/connected-operations/schema']),
    'dashboard route registered' => isset($GLOBALS['scfs_routes']['scfs/v1/connected-operations/dashboard']),
    'product route registered' => isset($GLOBALS['scfs_routes']['scfs/v1/connected-operations/products/(?P<product>[a-z0-9-]+)']),
    'actions route registered' => isset($GLOBALS['scfs_routes']['scfs/v1/connected-operations/actions']),
    'refresh route registered' => isset($GLOBALS['scfs_routes']['scfs/v1/connected-operations/refresh']),
    'report route registered' => isset($GLOBALS['scfs_routes']['scfs/v1/connected-operations/report']),
    'unified dashboard declared' => in_array('unified_operations_dashboard', $schema['capabilities'] ?? array(), true),
    'product dossiers declared' => in_array('product_operations_dossiers', $schema['capabilities'] ?? array(), true),
    'governed queue declared' => in_array('governed_action_queue', $schema['capabilities'] ?? array(), true),
    'private case narrative excluded' => ($schema['privacy']['private_case_narrative_stored'] ?? true) === false,
    'automatic publication disabled' => ($schema['governance']['automatic_publication'] ?? true) === false,
    'automatic incident declaration disabled' => ($schema['governance']['automatic_incident_declaration'] ?? true) === false,
    'automatic private case creation disabled' => ($schema['governance']['automatic_private_case_creation'] ?? true) === false,
    'manager capability defaults to manage options' => $ops->admin_capability() === 'manage_options',
    'REST permission uses manager capability' => $ops->rest_permission() === true && $GLOBALS['scfs_capability'] === 'manage_options',
);
foreach ($checks as $label => $passed) {
    echo ($passed ? 'PASS' : 'FAIL') . " - {$label}\n";
    if (!$passed) exit(1);
}
echo count($checks) . " checks passed.\n";
