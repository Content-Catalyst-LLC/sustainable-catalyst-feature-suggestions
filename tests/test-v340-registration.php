<?php
define('ABSPATH', __DIR__ . '/');
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
$intel = SCFS_Documentation_Feature_Intelligence::instance();
$intel->register_gap_post_type();
$intel->register_meta();
$intel->register_rest_routes();
$checks = array(
    'gap post type registered' => isset($GLOBALS['scfs_post_types']['sc_doc_gap']),
    'gap post type private' => isset($GLOBALS['scfs_post_types']['sc_doc_gap']) && $GLOBALS['scfs_post_types']['sc_doc_gap']['public'] === false,
    'gap score meta' => isset($GLOBALS['scfs_meta']['sc_doc_gap']['_scfs_gap_score']),
    'gap article relation meta' => isset($GLOBALS['scfs_meta']['sc_doc_gap']['_scfs_gap_linked_article_id']),
    'gap suggestion relation meta' => isset($GLOBALS['scfs_meta']['sc_doc_gap']['_scfs_gap_linked_suggestion_id']),
    'schema route' => isset($GLOBALS['scfs_routes']['scfs/v1/documentation-intelligence/schema']),
    'feedback route' => isset($GLOBALS['scfs_routes']['scfs/v1/knowledge-base/articles/(?P<id>\d+)/feedback']),
    'feedback summary route' => isset($GLOBALS['scfs_routes']['scfs/v1/knowledge-base/articles/(?P<id>\d+)/feedback-summary']),
    'gaps route' => isset($GLOBALS['scfs_routes']['scfs/v1/documentation-intelligence/gaps']),
    'gap refresh route' => isset($GLOBALS['scfs_routes']['scfs/v1/documentation-intelligence/gaps/refresh']),
    'relationships route' => isset($GLOBALS['scfs_routes']['scfs/v1/documentation-intelligence/relationships']),
    'analytics route' => isset($GLOBALS['scfs_routes']['scfs/v1/documentation-intelligence/analytics']),
    'support demand route' => isset($GLOBALS['scfs_routes']['scfs/v1/suggestions/(?P<id>\d+)/support-demand']),
);
foreach ($checks as $label => $passed) {
    echo ($passed ? 'PASS' : 'FAIL') . " - {$label}\n";
    if (!$passed) exit(1);
}
echo count($checks) . " checks passed.\n";
