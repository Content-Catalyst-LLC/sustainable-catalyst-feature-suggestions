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
function register_post_meta($type, $key, $args) { $GLOBALS['scfs_meta'][$type][$key] = $args; }
function register_rest_route($namespace, $route, $args) { $GLOBALS['scfs_routes'][$namespace . $route] = $args; }
function current_user_can($capability) { $GLOBALS['scfs_capability'] = $capability; return true; }
function __return_true() { return true; }

require dirname(__DIR__) . '/wordpress/sustainable-catalyst-feature-suggestions/sustainable-catalyst-feature-suggestions.php';
$sync = SCFS_Repository_Release_Synchronization::instance();
$sync->register_meta();
$sync->register_rest_routes();
$schema = $sync->schema_record();
$checks = array(
    'article external id meta' => isset($GLOBALS['scfs_meta']['sc_support_article']['_scfs_sync_external_id']),
    'known issue repository URL meta' => isset($GLOBALS['scfs_meta']['sc_known_issue']['_scfs_sync_repository_url']),
    'release commit SHA meta' => isset($GLOBALS['scfs_meta']['sc_release_record']['_scfs_sync_commit_sha']),
    'remote hash remains private' => ($GLOBALS['scfs_meta']['sc_support_article']['_scfs_sync_remote_hash']['show_in_rest'] ?? true) === false,
    'link report remains private' => ($GLOBALS['scfs_meta']['sc_release_record']['_scfs_sync_link_report']['show_in_rest'] ?? true) === false,
    'schema route registered' => isset($GLOBALS['scfs_routes']['scfs/v1/repository-sync/schema']),
    'mappings route registered' => isset($GLOBALS['scfs_routes']['scfs/v1/repository-sync/mappings']),
    'preview route registered' => isset($GLOBALS['scfs_routes']['scfs/v1/repository-sync/preview/(?P<product_id>\d+)']),
    'apply route registered' => isset($GLOBALS['scfs_routes']['scfs/v1/repository-sync/apply/(?P<product_id>\d+)']),
    'drift route registered' => isset($GLOBALS['scfs_routes']['scfs/v1/repository-sync/drift']),
    'link health route registered' => isset($GLOBALS['scfs_routes']['scfs/v1/repository-sync/link-health/(?P<product_id>\d+)']),
    'webhook route registered' => isset($GLOBALS['scfs_routes']['scfs/v1/repository-sync/webhook/github']),
    'GitHub provider declared' => in_array('github_public', $schema['providers'] ?? array(), true),
    'drift capability declared' => in_array('documentation_drift_detection', $schema['capabilities'] ?? array(), true),
    'webhook capability declared' => in_array('signed_webhook_queue', $schema['capabilities'] ?? array(), true),
    'automatic approval disabled' => ($schema['governance']['automatic_approval'] ?? true) === false,
    'automatic publication disabled' => ($schema['governance']['automatic_publication'] ?? true) === false,
    'published records not overwritten' => ($schema['governance']['published_records_overwritten'] ?? true) === false,
    'manager capability defaults to manage options' => $sync->admin_capability() === 'manage_options',
    'REST permission uses manager capability' => $sync->rest_permission() === true && $GLOBALS['scfs_capability'] === 'manage_options',
);
foreach ($checks as $label => $passed) {
    echo ($passed ? 'PASS' : 'FAIL') . " - {$label}\n";
    if (!$passed) exit(1);
}
echo count($checks) . " checks passed.\n";
