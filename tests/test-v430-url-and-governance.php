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
function wp_parse_url($url) { return parse_url($url); }

require dirname(__DIR__) . '/wordpress/sustainable-catalyst-feature-suggestions/sustainable-catalyst-feature-suggestions.php';
$sync = SCFS_Repository_Release_Synchronization::instance();
$valid = $sync->parse_github_repository_url('https://github.com/Content-Catalyst-LLC/sustainable-catalyst-feature-suggestions.git');
$invalid_host = $sync->parse_github_repository_url('https://example.com/owner/repo');
$invalid_path = $sync->parse_github_repository_url('https://github.com/owner');
$checks = array(
    'valid GitHub owner parsed' => ($valid['owner'] ?? '') === 'Content-Catalyst-LLC',
    'valid GitHub repository parsed' => ($valid['repository'] ?? '') === 'sustainable-catalyst-feature-suggestions',
    'git suffix removed' => ($valid['canonical_url'] ?? '') === 'https://github.com/Content-Catalyst-LLC/sustainable-catalyst-feature-suggestions',
    'API base generated' => strpos($valid['api_base'] ?? '', 'api.github.com/repos/') !== false,
    'non GitHub host rejected' => $invalid_host === null,
    'incomplete GitHub path rejected' => $invalid_path === null,
    'empty repository rejected' => $sync->parse_github_repository_url('') === null,
    'managed support articles retained' => in_array('sc_support_article', $sync->managed_post_types(), true),
    'managed known issues retained' => in_array('sc_known_issue', $sync->managed_post_types(), true),
    'managed release records retained' => in_array('sc_release_record', $sync->managed_post_types(), true),
);
foreach ($checks as $label => $passed) {
    echo ($passed ? 'PASS' : 'FAIL') . " - {$label}\n";
    if (!$passed) exit(1);
}
echo count($checks) . " checks passed.\n";
