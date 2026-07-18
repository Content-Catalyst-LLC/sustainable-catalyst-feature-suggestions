<?php
define('ABSPATH', __DIR__ . '/');
class WP_REST_Server { const READABLE = 'GET'; const CREATABLE = 'POST'; }
$GLOBALS['scfs_meta'] = array();
$GLOBALS['scfs_routes'] = array();
function add_action() { return true; }
function add_filter() { return true; }
function add_shortcode() { return true; }
function register_activation_hook() { return true; }
function register_deactivation_hook() { return true; }
function plugin_dir_path($file) { return dirname($file) . '/'; }
function plugin_basename($file) { return basename($file); }
function apply_filters($hook, $value) { return $value; }
function __($text) { return $text; }
function current_user_can() { return true; }
function register_post_meta($type, $key, $args) { $GLOBALS['scfs_meta'][$type][$key] = $args; }
function register_rest_route($namespace, $route, $args) { $GLOBALS['scfs_routes'][$namespace . $route] = $args; }

require dirname(__DIR__) . '/wordpress/sustainable-catalyst-feature-suggestions/sustainable-catalyst-feature-suggestions.php';
$ops = SCFS_Support_Content_Operations::instance();
$ops->register_meta();
$ops->register_rest_routes();
$schema = $ops->schema_record();
$checks = array(
    'operations schema' => ($schema['schema'] ?? '') === 'scfs-support-content-operations/1.1',
    'operations version' => ($schema['version'] ?? '') === '5.1.0',
    'product onboarding capability' => in_array('product_onboarding_profiles', $schema['capabilities'] ?? array(), true),
    'readiness scoring capability' => in_array('support_readiness_scoring', $schema['capabilities'] ?? array(), true),
    'starter content capability' => in_array('idempotent_starter_content', $schema['capabilities'] ?? array(), true),
    'README import capability' => in_array('readme_and_documentation_import', $schema['capabilities'] ?? array(), true),
    'changelog import capability' => in_array('changelog_and_release_notes_import', $schema['capabilities'] ?? array(), true),
    'duplicate detection capability' => in_array('duplicate_fingerprint_detection', $schema['capabilities'] ?? array(), true),
    'article lifecycle meta' => isset($GLOBALS['scfs_meta']['sc_support_article']['_scfs_content_lifecycle']),
    'issue fingerprint meta' => isset($GLOBALS['scfs_meta']['sc_known_issue']['_scfs_source_fingerprint']),
    'release verified meta' => isset($GLOBALS['scfs_meta']['sc_release_record']['_scfs_verified_at']),
    'schema route' => isset($GLOBALS['scfs_routes']['scfs/v1/content-operations/schema']),
    'readiness route' => isset($GLOBALS['scfs_routes']['scfs/v1/content-operations/readiness']),
    'validation route' => isset($GLOBALS['scfs_routes']['scfs/v1/content-operations/validate']),
    'import route' => isset($GLOBALS['scfs_routes']['scfs/v1/content-operations/import']),
    'export route' => isset($GLOBALS['scfs_routes']['scfs/v1/content-operations/export']),
    'automatic publication disabled' => ($schema['governance']['automatic_publication'] ?? true) === false,
    'private case import disabled' => ($schema['governance']['private_case_content_imported'] ?? true) === false,
);
foreach ($checks as $label => $passed) {
    echo ($passed ? 'PASS' : 'FAIL') . " - {$label}\n";
    if (!$passed) exit(1);
}
echo count($checks) . " checks passed.\n";
