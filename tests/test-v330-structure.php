<?php
$root = dirname(__DIR__);
$main = file_get_contents($root . '/wordpress/sustainable-catalyst-feature-suggestions/sustainable-catalyst-feature-suggestions.php');
$guided = file_get_contents($root . '/wordpress/sustainable-catalyst-feature-suggestions/includes/class-scfs-guided-resolution.php');
$kb = file_get_contents($root . '/wordpress/sustainable-catalyst-feature-suggestions/includes/class-scfs-knowledge-base.php');
$backend = file_get_contents($root . '/backend/app/main.py');
$manifest = json_decode(file_get_contents($root . '/feature_suggestions_manifest.json'), true);
$checks = array(
    'plugin version' => strpos($main, 'Version: 3.3.0') !== false,
    'guided bootstrap' => strpos($main, 'SCFS_Guided_Resolution::instance();') !== false,
    'guided class file' => file_exists($root . '/wordpress/sustainable-catalyst-feature-suggestions/includes/class-scfs-guided-resolution.php'),
    'guided shortcode' => strpos($guided, "const SHORTCODE = 'scfs_guided_resolution';") !== false,
    'product version filter' => strpos($guided, 'scfs_resolution_version') !== false,
    'error matching' => strpos($guided, '_scfs_error_signatures') !== false,
    'known issue prioritization' => strpos($guided, 'Current known issue') !== false,
    'synonym controls' => strpos($guided, 'synonyms') !== false,
    'promoted result controls' => strpos($guided, 'promoted_results') !== false,
    'privacy minimized analytics' => strpos($guided, 'ip_address_stored') !== false,
    'analytics table' => strpos($guided, 'scfs_resolution_events') !== false,
    'private handoff token' => strpos($guided, 'scfs_resolution_handoff_') !== false,
    'automatic case disabled' => strpos($guided, "'automatic_case_creation' => false") !== false,
    'guided REST search' => strpos($guided, "'/guided-resolution/search'") !== false,
    'archive upgraded' => strpos(file_get_contents($root . '/wordpress/sustainable-catalyst-feature-suggestions/templates/archive-support-knowledge-base.php'), '[scfs_guided_resolution]') !== false,
    'legacy KB shortcode retained' => strpos($kb, "const SHORTCODE = 'scfs_support_knowledge_base';") !== false,
    'KB version filter added' => strpos($kb, 'scfs_kb_version') !== false,
    'guided stylesheet' => file_exists($root . '/wordpress/sustainable-catalyst-feature-suggestions/assets/guided-resolution.css'),
    'backend ranking endpoint' => strpos($backend, "/v1/guided-resolution/rank") !== false,
    'httpx pinned' => strpos(file_get_contents($root . '/backend/requirements.txt'), 'httpx==') !== false,
    'manifest version' => is_array($manifest) && isset($manifest['version']) && $manifest['version'] === '3.3.0',
);
$failed = array();
foreach ($checks as $label => $passed) {
    echo ($passed ? 'PASS' : 'FAIL') . " - {$label}\n";
    if (!$passed) $failed[] = $label;
}
if ($failed) { fwrite(STDERR, 'Failed checks: ' . implode(', ', $failed) . "\n"); exit(1); }
echo count($checks) . " checks passed.\n";
