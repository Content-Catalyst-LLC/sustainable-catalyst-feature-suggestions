<?php
$root = dirname(__DIR__);
$class = file_get_contents($root . '/wordpress/sustainable-catalyst-feature-suggestions/includes/class-scfs-feedback-product-signals.php');
$checks = array(
    'feature request collection' => strpos($class, 'private function collect_suggestions') !== false,
    'article feedback collection' => strpos($class, 'private function collect_article_feedback') !== false,
    'resolution signal collection' => strpos($class, 'private function collect_resolution_signals') !== false,
    'documentation gap collection' => strpos($class, 'private function collect_documentation_gaps') !== false,
    'Known Issue collection' => strpos($class, 'private function collect_known_issues') !== false,
    'support relationship collection' => strpos($class, 'private function collect_support_relationships') !== false,
    'deterministic product scoring' => strpos($class, 'public function score_product_record($record)') !== false,
    'snapshot builder' => strpos($class, 'public function build_snapshot($force = false, $filters = array())') !== false,
    'suggestion signal bridge' => strpos($class, 'public function signal_for_suggestion($post_id)') !== false,
    'advisory state model' => strpos($class, "'critical_review'") !== false && strpos($class, "'elevated'") !== false,
    'no automatic roadmap changes' => strpos($class, "'automatic_roadmap_changes' => false") !== false,
);
$failed = array_keys(array_filter($checks, static function ($ok) { return !$ok; }));
foreach ($checks as $label => $ok) echo ($ok ? 'PASS' : 'FAIL') . " - {$label}\n";
if ($failed) { fwrite(STDERR, 'Failed checks: ' . implode(', ', $failed) . "\n"); exit(1); }
echo 'v5.6.0 feedback product signal engine contract passed (' . count($checks) . " checks).\n";
