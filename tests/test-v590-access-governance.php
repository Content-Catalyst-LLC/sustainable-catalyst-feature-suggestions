<?php
$root = dirname(__DIR__);
$class = file_get_contents($root . '/wordpress/sustainable-catalyst-feature-suggestions/includes/class-scfs-public-support-integrations.php');
$backend = file_get_contents($root . '/backend/app/public_support_integrations.py');
$checks = array(
 'public records only' => strpos($class, "'public_records_only' => true") !== false,
 'no identifiers' => strpos($class, "'personal_identifiers_exposed' => false") !== false,
 'no private case content' => strpos($class, "'private_case_content_exposed' => false") !== false,
 'no private documents' => strpos($class, "'private_documents_exposed' => false") !== false,
 'no contact records' => strpos($class, "'contact_records_exposed' => false") !== false,
 'read-only API' => strpos($class, "'read_only_public_api' => true") !== false,
 'optional API key' => strpos($class, 'x-scfs-public-key') !== false,
 'origin allowlist' => strpos($class, 'origin_allowlist') !== false,
 'cache header' => strpos($class, "header('Cache-Control'") !== false,
 'ETag header' => strpos($class, "header('ETag'") !== false,
 'backend private boundary' => strpos($backend, 'private_case_content_exposed: bool = False') !== false,
 'human review' => strpos($class, "'human_review_required' => true") !== false,
);
$failed = array_keys(array_filter($checks, static function ($ok) { return !$ok; }));
foreach ($checks as $label => $ok) echo ($ok ? 'PASS' : 'FAIL') . " - {$label}\n";
if ($failed) { fwrite(STDERR, 'Failed checks: ' . implode(', ', $failed) . "\n"); exit(1); }
echo 'v5.9.0 access governance contract passed (' . count($checks) . " checks).\n";
