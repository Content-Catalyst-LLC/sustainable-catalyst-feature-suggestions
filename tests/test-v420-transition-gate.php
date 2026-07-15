<?php
define('ABSPATH', __DIR__ . '/');
class WP_Error {
    private $code;
    private $message;
    public function __construct($code, $message = '') { $this->code = $code; $this->message = $message; }
    public function get_error_code() { return $this->code; }
    public function get_error_message() { return $this->message; }
}
$GLOBALS['scfs_meta_values'] = array(
    '_scfs_editorial_author_id' => 1,
    '_scfs_editorial_reviewer_id' => 2,
    '_scfs_editorial_approver_id' => 3,
    '_scfs_editorial_change_summary' => 'Reviewed documentation changes.',
    '_scfs_editorial_approved_version_ids' => array(10),
    '_scfs_editorial_scheduled_at' => '2030-01-01 12:00:00',
);
function add_action() { return true; }
function add_filter() { return true; }
function add_shortcode() { return true; }
function register_activation_hook() { return true; }
function register_deactivation_hook() { return true; }
function plugin_dir_path($file) { return dirname($file) . '/'; }
function plugin_basename($file) { return basename($file); }
function apply_filters($hook, $value) { return $value; }
function __($text) { return $text; }
function sanitize_key($value) { return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $value)); }
function sanitize_text_field($value) { return trim((string) $value); }
function absint($value) { return abs((int) $value); }
function get_option($key, $default = array()) {
    if ($key === 'scfs_editorial_governance_settings') {
        return array('block_publish_on_standards_failure' => '0');
    }
    return $default;
}
function wp_parse_args($args, $defaults) { return array_merge($defaults, $args); }
function get_post_meta($post_id, $key, $single = true) { return $GLOBALS['scfs_meta_values'][$key] ?? ''; }
function wp_get_object_terms($post_id, $taxonomy, $args = array()) { return array(10); }
function is_wp_error($value) { return $value instanceof WP_Error; }

require dirname(__DIR__) . '/wordpress/sustainable-catalyst-feature-suggestions/sustainable-catalyst-feature-suggestions.php';
$governance = SCFS_Editorial_Governance::instance();
$valid = $governance->transition_gate(44, 'approved');
$GLOBALS['scfs_meta_values']['_scfs_editorial_approver_id'] = 1;
$separation = $governance->transition_gate(44, 'approved');
$GLOBALS['scfs_meta_values']['_scfs_editorial_approver_id'] = 3;
$GLOBALS['scfs_meta_values']['_scfs_editorial_approved_version_ids'] = array();
$version = $governance->transition_gate(44, 'approved');
$GLOBALS['scfs_meta_values']['_scfs_editorial_approved_version_ids'] = array(10);
$GLOBALS['scfs_meta_values']['_scfs_editorial_scheduled_at'] = '';
$schedule = $governance->transition_gate(44, 'scheduled');
$checks = array(
    'valid governed transition accepted' => $valid === true,
    'author approver separation enforced' => is_wp_error($separation) && $separation->get_error_code() === 'scfs_editorial_separation_required',
    'version approval enforced' => is_wp_error($version) && $version->get_error_code() === 'scfs_editorial_version_approval_required',
    'schedule date enforced' => is_wp_error($schedule) && $schedule->get_error_code() === 'scfs_editorial_schedule_required',
);
foreach ($checks as $label => $passed) {
    echo ($passed ? 'PASS' : 'FAIL') . " - {$label}\n";
    if (!$passed) exit(1);
}
echo count($checks) . " checks passed.\n";
