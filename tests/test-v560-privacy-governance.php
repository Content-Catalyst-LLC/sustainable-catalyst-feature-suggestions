<?php
$root = dirname(__DIR__);
$class = file_get_contents($root . '/wordpress/sustainable-catalyst-feature-suggestions/includes/class-scfs-feedback-product-signals.php');
$backend = file_get_contents($root . '/backend/app/feedback_product_signals.py');
$checks = array(
    'administrator-only snapshot' => strpos($class, "'administrator_only' => true") !== false,
    'personal identifiers excluded' => strpos($class, "'personal_identifiers_exposed' => false") !== false,
    'private case content excluded' => strpos($class, "'private_case_content_exposed' => false") !== false,
    'raw search text excluded' => strpos($class, "'raw_search_text_exposed' => false") !== false,
    'human review required' => strpos($class, "'human_review_required' => true") !== false,
    'no automatic roadmap changes' => strpos($backend, 'automatic_roadmap_changes: bool = False') !== false,
    'no automatic issue declaration' => strpos($backend, 'automatic_issue_declaration: bool = False') !== false,
    'no automatic publication' => strpos($backend, 'automatic_publication: bool = False') !== false,
    'backend administrator boundary' => strpos($backend, 'administrator_only: bool = True') !== false,
    'backend raw search boundary' => strpos($backend, 'raw_search_text_exposed: bool = False') !== false,
);
$failed = array_keys(array_filter($checks, static function ($ok) { return !$ok; }));
foreach ($checks as $label => $ok) echo ($ok ? 'PASS' : 'FAIL') . " - {$label}\n";
if ($failed) { fwrite(STDERR, 'Failed checks: ' . implode(', ', $failed) . "\n"); exit(1); }
echo 'v5.6.0 Product Signals privacy and governance contract passed (' . count($checks) . " checks).\n";
