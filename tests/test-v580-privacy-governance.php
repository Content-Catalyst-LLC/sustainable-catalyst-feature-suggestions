<?php
$root = dirname(__DIR__);
$class = file_get_contents($root . '/wordpress/sustainable-catalyst-feature-suggestions/includes/class-scfs-cross-product-support-graph.php');
$backend = file_get_contents($root . '/backend/app/support_graph_handoffs.py');
$checks = array(
    'public records only' => strpos($class, "'public_records_only' => true") !== false,
    'no identifiers' => strpos($class, "'personal_identifiers_exposed' => false") !== false,
    'no raw search text' => strpos($class, "'raw_search_text_exposed' => false") !== false,
    'no private case content' => strpos($class, "'private_case_content_exposed' => false") !== false,
    'no private documents' => strpos($class, "'private_documents_exposed' => false") !== false,
    'no automatic redirect' => strpos($class, "'automatic_product_redirect' => false") !== false,
    'no automatic case creation' => strpos($class, "'automatic_private_case_creation' => false") !== false,
    'no roadmap changes' => strpos($class, "'automatic_roadmap_change' => false") !== false,
    'human review' => strpos($class, "'human_review_required' => true") !== false,
    'backend privacy boundary' => strpos($backend, 'private_case_content_exposed: bool = False') !== false,
    'backend redirect boundary' => strpos($backend, 'automatic_redirect: bool = False') !== false,
);
$failed = array_keys(array_filter($checks, static function ($ok) { return !$ok; }));
foreach ($checks as $label => $ok) echo ($ok ? 'PASS' : 'FAIL') . " - {$label}\n";
if ($failed) { fwrite(STDERR, 'Failed checks: ' . implode(', ', $failed) . "\n"); exit(1); }
echo 'v5.8.0 privacy and governance contract passed (' . count($checks) . " checks).\n";
