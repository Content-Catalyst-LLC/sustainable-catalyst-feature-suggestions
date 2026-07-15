<?php
define('ABSPATH', __DIR__ . '/');
class WP_REST_Server { const READABLE = 'GET'; const CREATABLE = 'POST'; }
$GLOBALS['scfs_meta'] = array();
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
function current_user_can($capability) { $GLOBALS['scfs_capability'] = $capability; return true; }
function is_multisite() { return false; }
function is_network_admin() { return false; }
function register_post_meta($type, $key, $args) { $GLOBALS['scfs_meta'][$type][$key] = $args; }
function register_rest_route($namespace, $route, $args) { $GLOBALS['scfs_routes'][$namespace . $route] = $args; }

require dirname(__DIR__) . '/wordpress/sustainable-catalyst-feature-suggestions/sustainable-catalyst-feature-suggestions.php';
$ops = SCFS_Support_Content_Operations::instance();
$ops->register_meta();
$ops->register_rest_routes();
$schema = $ops->schema_record();
$checks = array(
    'patch version' => SCFS_Support_Content_Operations::VERSION === '4.1.1',
    'reliability schema' => ($schema['schema'] ?? '') === 'scfs-support-content-operations/1.1',
    'rollback capability' => in_array('import_batch_rollback', $schema['capabilities'] ?? array(), true),
    'malformed import capability' => in_array('malformed_import_recovery', $schema['capabilities'] ?? array(), true),
    'duplicate refinement capability' => in_array('source_and_title_duplicate_detection', $schema['capabilities'] ?? array(), true),
    'progress capability' => in_array('operation_progress_reporting', $schema['capabilities'] ?? array(), true),
    'scheduled health capability' => in_array('scheduled_validation_health', $schema['capabilities'] ?? array(), true),
    'export integrity capability' => in_array('export_checksum_integrity', $schema['capabilities'] ?? array(), true),
    'role capability boundary' => in_array('multisite_and_role_capability_boundary', $schema['capabilities'] ?? array(), true),
    'rollback governance' => ($schema['governance']['rollback_moves_records_to_trash'] ?? false) === true,
    'manager governance' => ($schema['governance']['operations_require_manage_options'] ?? false) === true,
    'normalized title meta' => isset($GLOBALS['scfs_meta']['sc_support_article']['_scfs_normalized_title']),
    'import integrity meta' => isset($GLOBALS['scfs_meta']['sc_known_issue']['_scfs_import_integrity']),
    'rollback route' => isset($GLOBALS['scfs_routes']['scfs/v1/content-operations/rollback']),
    'health route' => isset($GLOBALS['scfs_routes']['scfs/v1/content-operations/health']),
    'default capability' => $ops->admin_capability() === 'manage_options',
    'permission check uses capability' => $ops->rest_admin_permission() === true && $GLOBALS['scfs_capability'] === 'manage_options',
);
foreach ($checks as $label => $passed) {
    echo ($passed ? 'PASS' : 'FAIL') . " - {$label}\n";
    if (!$passed) exit(1);
}
echo count($checks) . " checks passed.\n";
