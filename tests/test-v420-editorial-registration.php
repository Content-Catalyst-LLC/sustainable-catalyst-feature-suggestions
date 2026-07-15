<?php
define('ABSPATH', __DIR__ . '/');
class WP_REST_Server { const READABLE = 'GET'; const CREATABLE = 'POST'; }
$GLOBALS['scfs_meta'] = array();
$GLOBALS['scfs_routes'] = array();
$GLOBALS['scfs_post_types'] = array();
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
function register_post_type($type, $args) { $GLOBALS['scfs_post_types'][$type] = $args; }
function register_post_meta($type, $key, $args) { $GLOBALS['scfs_meta'][$type][$key] = $args; }
function register_rest_route($namespace, $route, $args) { $GLOBALS['scfs_routes'][$namespace . $route] = $args; }
function current_user_can($capability) { $GLOBALS['scfs_capability'] = $capability; return true; }

require dirname(__DIR__) . '/wordpress/sustainable-catalyst-feature-suggestions/sustainable-catalyst-feature-suggestions.php';
$governance = SCFS_Editorial_Governance::instance();
$governance->register_note_post_type();
$governance->register_meta();
$governance->register_rest_routes();
$schema = $governance->schema_record();
$checks = array(
    'private editorial note type registered' => isset($GLOBALS['scfs_post_types']['sc_editorial_note']) && $GLOBALS['scfs_post_types']['sc_editorial_note']['public'] === false,
    'article editorial state meta' => isset($GLOBALS['scfs_meta']['sc_support_article']['_scfs_editorial_state']),
    'known issue reviewer meta' => isset($GLOBALS['scfs_meta']['sc_known_issue']['_scfs_editorial_reviewer_id']),
    'release approver meta' => isset($GLOBALS['scfs_meta']['sc_release_record']['_scfs_editorial_approver_id']),
    'version approval meta is array' => ($GLOBALS['scfs_meta']['sc_support_article']['_scfs_editorial_approved_version_ids']['type'] ?? '') === 'array',
    'change summary meta' => isset($GLOBALS['scfs_meta']['sc_support_article']['_scfs_editorial_change_summary']),
    'expiration meta' => isset($GLOBALS['scfs_meta']['sc_release_record']['_scfs_editorial_expires_at']),
    'schema route' => isset($GLOBALS['scfs_routes']['scfs/v1/editorial-governance/schema']),
    'queue route' => isset($GLOBALS['scfs_routes']['scfs/v1/editorial-governance/queue']),
    'transition route' => isset($GLOBALS['scfs_routes']['scfs/v1/editorial-governance/record/(?P<id>\d+)/transition']),
    'audit route' => isset($GLOBALS['scfs_routes']['scfs/v1/editorial-governance/audit']),
    'assignment capability' => in_array('author_reviewer_approver_assignments', $schema['capabilities'] ?? array(), true),
    'standards capability' => in_array('documentation_standards_scoring', $schema['capabilities'] ?? array(), true),
    'comments are private' => ($schema['governance']['private_comments_publicly_exposed'] ?? true) === false,
    'automatic approval disabled' => ($schema['governance']['automatic_approval'] ?? true) === false,
    'default manager capability' => $governance->admin_capability() === 'edit_others_posts',
    'permission uses manager capability' => $governance->rest_permission() === true && $GLOBALS['scfs_capability'] === 'edit_others_posts',
);
foreach ($checks as $label => $passed) {
    echo ($passed ? 'PASS' : 'FAIL') . " - {$label}\n";
    if (!$passed) exit(1);
}
echo count($checks) . " checks passed.\n";
