<?php
define('ABSPATH', __DIR__ . '/');
class WP_REST_Server { const READABLE = 'GET'; const CREATABLE = 'POST'; }
$GLOBALS['scfs_post_types'] = array();
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
function register_post_type($type, $args) { $GLOBALS['scfs_post_types'][$type] = $args; }
function register_post_meta($type, $key, $args) { $GLOBALS['scfs_meta'][$type][$key] = $args; }
function register_rest_route($namespace, $route, $args) { $GLOBALS['scfs_routes'][$namespace . $route] = $args; }
function current_user_can() { return true; }

require dirname(__DIR__) . '/wordpress/sustainable-catalyst-feature-suggestions/sustainable-catalyst-feature-suggestions.php';
$platform = SCFS_Product_Support_Platform::instance();
$platform->register_release_post_type();
$platform->register_release_meta();
$platform->register_rest_routes();
$checks = array(
    'release post type registered' => isset($GLOBALS['scfs_post_types']['sc_release_record']),
    'release post type public' => isset($GLOBALS['scfs_post_types']['sc_release_record']) && $GLOBALS['scfs_post_types']['sc_release_record']['public'] === true,
    'release archive registered' => ($GLOBALS['scfs_post_types']['sc_release_record']['has_archive'] ?? '') === 'support-releases',
    'release status meta' => isset($GLOBALS['scfs_meta']['sc_release_record']['_scfs_release_status']),
    'release date meta' => isset($GLOBALS['scfs_meta']['sc_release_record']['_scfs_release_date']),
    'release highlights meta' => isset($GLOBALS['scfs_meta']['sc_release_record']['_scfs_release_highlights']),
    'release article relationship meta' => isset($GLOBALS['scfs_meta']['sc_release_record']['_scfs_release_related_articles']),
    'release issue relationship meta' => isset($GLOBALS['scfs_meta']['sc_release_record']['_scfs_release_related_issues']),
    'release suggestion relationship meta' => isset($GLOBALS['scfs_meta']['sc_release_record']['_scfs_release_related_suggestions']),
    'schema route' => isset($GLOBALS['scfs_routes']['scfs/v1/product-support/schema']),
    'overview route' => isset($GLOBALS['scfs_routes']['scfs/v1/product-support/overview']),
    'releases route' => isset($GLOBALS['scfs_routes']['scfs/v1/product-support/releases']),
    'products route' => isset($GLOBALS['scfs_routes']['scfs/v1/product-support/products']),
    'handoff route' => isset($GLOBALS['scfs_routes']['scfs/v1/product-support/handoff-schema']),
    'snapshot route' => isset($GLOBALS['scfs_routes']['scfs/v1/product-support/snapshot']),
);
foreach ($checks as $label => $passed) {
    echo ($passed ? 'PASS' : 'FAIL') . " - {$label}\n";
    if (!$passed) exit(1);
}
echo count($checks) . " checks passed.\n";
