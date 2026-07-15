<?php
define('ABSPATH', __DIR__ . '/');
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
function wp_strip_all_tags($value) { return strip_tags((string) $value); }

require dirname(__DIR__) . '/wordpress/sustainable-catalyst-feature-suggestions/sustainable-catalyst-feature-suggestions.php';
$governance = SCFS_Editorial_Governance::instance();
$states = $governance->workflow_states();
$transitions = $governance->allowed_transitions();
$settings = $governance->default_settings();
$headings = $governance->extract_headings('<h2>Overview</h2><p>Text</p><h3>Next steps</h3>');
$checks = array(
    'nine workflow states' => count($states) === 9,
    'draft can submit' => in_array('submitted', $transitions['draft'], true),
    'review can approve' => in_array('approved', $transitions['in_review'], true),
    'approval can schedule' => in_array('scheduled', $transitions['approved'], true),
    'published can expire' => in_array('expired', $transitions['published'], true),
    'archived can return to draft' => in_array('draft', $transitions['archived'], true),
    'review required by default' => $settings['require_review_before_publish'] === '1',
    'separate approver required' => $settings['require_separate_approver'] === '1',
    'version approval required' => $settings['require_version_approval'] === '1',
    'standards minimum is 80' => $settings['minimum_standards_score'] === 80,
    'getting started standard' => in_array('Overview', $settings['standards']['getting-started'], true),
    'heading extraction overview' => in_array('Overview', $headings, true),
    'heading extraction next steps' => in_array('Next steps', $headings, true),
    'invalid state falls back to draft' => $governance->sanitize_state('not-a-state') === 'draft',
    'valid date retained' => $governance->sanitize_date('2026-08-01') === '2026-08-01',
    'invalid date rejected' => $governance->sanitize_date('August 1') === '',
);
foreach ($checks as $label => $passed) {
    echo ($passed ? 'PASS' : 'FAIL') . " - {$label}\n";
    if (!$passed) exit(1);
}
echo count($checks) . " checks passed.\n";
