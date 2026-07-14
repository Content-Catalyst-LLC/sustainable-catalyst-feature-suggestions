<?php
// Contract test for WordPress registrations without a full WordPress runtime.
define('ABSPATH', __DIR__ . '/');
$GLOBALS['scfs_registered_post_types'] = array();
$GLOBALS['scfs_registered_taxonomies'] = array();
$GLOBALS['scfs_registered_meta'] = array();
$GLOBALS['scfs_registered_routes'] = array();
function add_action() { return true; }
function add_filter() { return true; }
function add_shortcode() { return true; }
function register_activation_hook() { return true; }
function register_deactivation_hook() { return true; }
function plugin_dir_path($file) { return dirname($file) . '/'; }
function plugin_basename($file) { return basename($file); }
function apply_filters($hook, $value) { return $value; }
function __($text) { return $text; }
function register_post_type($name, $args) { $GLOBALS['scfs_registered_post_types'][$name] = $args; }
function register_taxonomy($name, $objects, $args) { $GLOBALS['scfs_registered_taxonomies'][$name] = array('objects' => $objects, 'args' => $args); }
function register_post_meta($post_type, $key, $args) { $GLOBALS['scfs_registered_meta'][$post_type][$key] = $args; }
function register_term_meta() { return true; }
function register_rest_route($namespace, $route, $args) { $GLOBALS['scfs_registered_routes'][$namespace . $route] = $args; }

require dirname(__DIR__) . '/wordpress/sustainable-catalyst-feature-suggestions/sustainable-catalyst-feature-suggestions.php';
$kb = SCFS_Knowledge_Base_Foundation::instance();
$kb->register_content_types();
$kb->register_taxonomies();
$kb->register_meta();
$kb->register_rest_routes();

$checks = array(
    'support article registered' => isset($GLOBALS['scfs_registered_post_types']['sc_support_article']),
    'support article public' => !empty($GLOBALS['scfs_registered_post_types']['sc_support_article']['public']),
    'known issue registered' => isset($GLOBALS['scfs_registered_post_types']['sc_known_issue']),
    'known issue public' => !empty($GLOBALS['scfs_registered_post_types']['sc_known_issue']['public']),
    'documentation collection registered' => isset($GLOBALS['scfs_registered_taxonomies']['scfs_doc_collection']),
    'article type registered' => isset($GLOBALS['scfs_registered_taxonomies']['scfs_article_type']),
    'article relationship meta registered' => isset($GLOBALS['scfs_registered_meta']['sc_support_article']['_scfs_related_suggestions']),
    'known issue status meta registered' => isset($GLOBALS['scfs_registered_meta']['sc_known_issue']['_scfs_issue_status']),
    'schema REST route registered' => isset($GLOBALS['scfs_registered_routes']['scfs/v1/knowledge-base/schema']),
    'articles REST route registered' => isset($GLOBALS['scfs_registered_routes']['scfs/v1/knowledge-base/articles']),
    'known issues REST route registered' => isset($GLOBALS['scfs_registered_routes']['scfs/v1/knowledge-base/known-issues']),
);
foreach ($checks as $label => $passed) {
    echo ($passed ? 'PASS' : 'FAIL') . " - {$label}\n";
    if (!$passed) exit(1);
}
echo count($checks) . " checks passed.\n";
