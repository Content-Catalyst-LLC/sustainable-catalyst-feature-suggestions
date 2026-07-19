<?php
$root = dirname(__DIR__);
$class = file_get_contents($root . '/wordpress/sustainable-catalyst-feature-suggestions/includes/class-scfs-unified-support-search.php');
$main = file_get_contents($root . '/wordpress/sustainable-catalyst-feature-suggestions/sustainable-catalyst-feature-suggestions.php');
$backend = file_get_contents($root . '/backend/app/main.py');
$checks = array(
    'unified schema route' => strpos($class, '/unified-support/schema') !== false,
    'unified search route' => strpos($class, '/unified-support/search') !== false,
    'unified journey route' => strpos($class, '/unified-support/journey') !== false,
    'REST namespace preserved' => strpos($main, "const REST_NAMESPACE = 'scfs/v1';") !== false,
    'suggestion CPT preserved' => strpos($main, "const POST_TYPE = 'sc_feature_suggest';") !== false,
    'FastAPI capabilities' => strpos($backend, '/v1/unified-support/capabilities') !== false,
    'FastAPI search' => strpos($backend, '/v1/unified-support/search') !== false,
    'FastAPI journey' => strpos($backend, '/v1/unified-support/journey') !== false,
    'no personal data storage' => strpos($class, "'personal_data_stored' => false") !== false,
    'automatic private case disabled' => strpos($class, "'automatic_case_creation' => false") !== false,
    'consent handoff reused' => strpos($class, "SCFS_Guided_Resolution::NONCE_ACTION") !== false,
    'legacy guided shortcode retained' => strpos(file_get_contents($root . '/wordpress/sustainable-catalyst-feature-suggestions/includes/class-scfs-guided-resolution.php'), "const SHORTCODE = 'scfs_guided_resolution';") !== false,
);
$failed = array_keys(array_filter($checks, static function ($ok) { return !$ok; }));
foreach ($checks as $label => $ok) echo ($ok ? 'PASS' : 'FAIL') . " - {$label}\n";
if ($failed) { fwrite(STDERR, 'Failed checks: ' . implode(', ', $failed) . "\n"); exit(1); }
echo 'v5.3.0 API, privacy, and compatibility contract passed (' . count($checks) . " checks).\n";
