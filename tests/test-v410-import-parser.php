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
function sanitize_key($value) { return strtolower(preg_replace('/[^a-z0-9_\-]/', '', (string) $value)); }
function sanitize_text_field($value) { return trim(strip_tags((string) $value)); }
function wp_strip_all_tags($value) { return strip_tags((string) $value); }
function wp_trim_words($value, $count) { $words = preg_split('/\s+/', trim($value)); return implode(' ', array_slice($words, 0, $count)); }

require dirname(__DIR__) . '/wordpress/sustainable-catalyst-feature-suggestions/sustainable-catalyst-feature-suggestions.php';
$ops = SCFS_Support_Content_Operations::instance();
$tmp = tempnam(sys_get_temp_dir(), 'scfs-');
file_put_contents($tmp, "# Product README\n\nSetup and first workflow.\n");
$readme = $ops->parse_import_file($tmp, 'README.md', 'auto');
file_put_contents($tmp, "## 4.5.0 - 2026-07-15\nAdded onboarding.\n\n## 4.0.2\nFixed navigation.\n");
$changelog = $ops->parse_import_file($tmp, 'CHANGELOG.md', 'auto');
unlink($tmp);
$checks = array(
    'readme parsed' => is_array($readme) && count($readme['records'] ?? array()) === 1,
    'readme article type' => ($readme['records'][0]['type'] ?? '') === 'article',
    'readme getting started' => ($readme['records'][0]['article_type'] ?? '') === 'getting-started',
    'changelog parsed' => is_array($changelog) && count($changelog['records'] ?? array()) === 2,
    'first release version' => ($changelog['records'][0]['source_version'] ?? '') === 'v4.5.0',
    'first release current' => ($changelog['records'][0]['release_status'] ?? '') === 'current',
    'older release superseded' => ($changelog['records'][1]['release_status'] ?? '') === 'superseded',
    'automatic publication absent' => !isset($changelog['records'][0]['status']),
);
foreach ($checks as $label => $passed) {
    echo ($passed ? 'PASS' : 'FAIL') . " - {$label}\n";
    if (!$passed) exit(1);
}
echo count($checks) . " checks passed.\n";
