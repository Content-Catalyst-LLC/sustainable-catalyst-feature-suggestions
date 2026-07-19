<?php
$root = dirname(__DIR__);
$class = file_get_contents($root . '/wordpress/sustainable-catalyst-feature-suggestions/includes/class-scfs-unified-support-search.php');
$checks = array(
    'journey builder' => strpos($class, 'private function build_journey') !== false,
    'known issue first' => strpos($class, "'key' => 'known_issues'") !== false,
    'verified guidance second' => strpos($class, "'key' => 'support_articles'") !== false,
    'release context third' => strpos($class, "'key' => 'releases'") !== false,
    'public improvements fourth' => strpos($class, "'key' => 'public_suggestions'") !== false,
    'private support final' => strpos($class, "'key' => 'private_support'") !== false,
    'start here state' => strpos($class, "'start_here'") !== false,
    'recommended state' => strpos($class, "'recommended'") !== false,
    'summary builder' => strpos($class, 'private function build_summary') !== false,
    'human review required' => substr_count($class, "'human_review_required' => true") >= 2,
);
$failed = array_keys(array_filter($checks, static function ($ok) { return !$ok; }));
foreach ($checks as $label => $ok) echo ($ok ? 'PASS' : 'FAIL') . " - {$label}\n";
if ($failed) { fwrite(STDERR, 'Failed checks: ' . implode(', ', $failed) . "\n"); exit(1); }
echo 'v5.4.0 resolution journey contract passed (' . count($checks) . " checks).\n";
