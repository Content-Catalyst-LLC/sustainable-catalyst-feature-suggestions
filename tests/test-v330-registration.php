<?php
// Contract test for WordPress registrations without a full WordPress runtime.
define('ABSPATH', __DIR__ . '/');
$GLOBALS['scfs_registered_meta'] = array();
$GLOBALS['scfs_registered_routes'] = array();
$GLOBALS['scfs_shortcodes'] = array();
function add_action() { return true; }
function add_filter() { return true; }
function add_shortcode($name) { $GLOBALS['scfs_shortcodes'][] = $name; return true; }
function register_activation_hook() { return true; }
function register_deactivation_hook() { return true; }
function plugin_dir_path($file) { return dirname($file) . '/'; }
function plugin_basename($file) { return basename($file); }
function apply_filters($hook, $value) { return $value; }
function __($text) { return $text; }
function register_post_meta($post_type, $key, $args) { $GLOBALS['scfs_registered_meta'][$post_type][$key] = $args; }
function register_rest_route($namespace, $route, $args) { $GLOBALS['scfs_registered_routes'][$namespace . $route] = $args; }

require dirname(__DIR__) . '/wordpress/sustainable-catalyst-feature-suggestions/sustainable-catalyst-feature-suggestions.php';
$guided = SCFS_Guided_Resolution::instance();
$guided->register_meta();
$guided->register_shortcodes();
$guided->register_rest_routes();

$checks = array(
    'article aliases meta' => isset($GLOBALS['scfs_registered_meta']['sc_support_article']['_scfs_search_aliases']),
    'article error signatures meta' => isset($GLOBALS['scfs_registered_meta']['sc_support_article']['_scfs_error_signatures']),
    'known issue promoted meta' => isset($GLOBALS['scfs_registered_meta']['sc_known_issue']['_scfs_resolution_promoted']),
    'guided shortcode' => in_array('scfs_guided_resolution', $GLOBALS['scfs_shortcodes'], true),
    'legacy guided shortcode' => in_array('sustainable_catalyst_guided_resolution', $GLOBALS['scfs_shortcodes'], true),
    'schema route' => isset($GLOBALS['scfs_registered_routes']['scfs/v1/guided-resolution/schema']),
    'search route' => isset($GLOBALS['scfs_registered_routes']['scfs/v1/guided-resolution/search']),
    'handoff schema route' => isset($GLOBALS['scfs_registered_routes']['scfs/v1/guided-resolution/handoff-schema']),
    'handoff token route' => isset($GLOBALS['scfs_registered_routes']['scfs/v1/guided-resolution/handoffs/(?P<token>[a-f0-9]{48})']),
    'analytics route' => isset($GLOBALS['scfs_registered_routes']['scfs/v1/guided-resolution/analytics']),
);
foreach ($checks as $label => $passed) {
    echo ($passed ? 'PASS' : 'FAIL') . " - {$label}\n";
    if (!$passed) exit(1);
}
echo count($checks) . " checks passed.\n";
