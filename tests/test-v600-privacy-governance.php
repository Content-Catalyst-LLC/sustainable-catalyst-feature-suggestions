<?php
$root = dirname(__DIR__);
$class = file_get_contents($root . '/wordpress/sustainable-catalyst-feature-suggestions/includes/class-scfs-connected-product-support-platform.php');
$backend = file_get_contents($root . '/backend/app/connected_product_support_platform.py');
$checks = array(
 'public records only' => strpos($class, "'public_records_only' => true") !== false,
 'no identifiers' => strpos($class, "'personal_identifiers_exposed' => false") !== false,
 'no raw search persistence' => strpos($class, "'raw_search_text_persisted' => false") !== false,
 'no private case content' => strpos($class, "'private_case_content_exposed' => false") !== false,
 'no private documents' => strpos($class, "'private_documents_exposed' => false") !== false,
 'specialist source of truth' => strpos($class, "'specialist_modules_remain_source_of_truth' => true") !== false,
 'human review' => strpos($class, "'human_review_required' => true") !== false,
 'no publication automation' => strpos($class, "'automatic_publication' => false") !== false,
 'no issue automation' => strpos($class, "'automatic_issue_resolution' => false") !== false,
 'no release automation' => strpos($class, "'automatic_release_change' => false") !== false,
 'no roadmap automation' => strpos($class, "'automatic_roadmap_change' => false") !== false,
 'no private case creation' => strpos($backend, 'automatic_private_case_creation: bool = False') !== false,
 'no automatic redirect' => strpos($class, "'automatic_redirect' => false") !== false,
);
$failed = array_keys(array_filter($checks, static function ($ok) { return !$ok; }));
foreach ($checks as $label => $ok) echo ($ok ? 'PASS' : 'FAIL') . " - {$label}\n";
if ($failed) { fwrite(STDERR, 'Failed checks: ' . implode(', ', $failed) . "\n"); exit(1); }
echo 'v6.2.0 privacy and governance contract passed (' . count($checks) . " checks).\n";
