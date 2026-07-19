<?php
$root = dirname(__DIR__);
$class = file_get_contents($root . '/wordpress/sustainable-catalyst-feature-suggestions/includes/class-scfs-support-analytics-documentation-effectiveness.php');
$checks = array(
    'resolution metrics' => strpos($class, 'public function resolution_metrics($product = \'all\')') !== false,
    'article metrics' => strpos($class, 'public function article_metrics($product = \'all\')') !== false,
    'Known Issue coverage' => strpos($class, 'public function issue_coverage_metrics($product = \'all\')') !== false,
    'release coverage' => strpos($class, 'public function release_coverage_metrics($product = \'all\')') !== false,
    'gap metrics' => strpos($class, 'public function gap_metrics($product = \'all\')') !== false,
    'effectiveness scoring' => strpos($class, 'public function evaluate_effectiveness($evidence)') !== false,
    'portfolio snapshot' => strpos($class, 'public function build_snapshot($force = false)') !== false,
    'trend history' => strpos($class, 'public function trend_record($product = \'all\')') !== false,
    'daily snapshots' => strpos($class, "const CRON_HOOK = 'scfs_support_analytics_effectiveness_daily';") !== false,
    'advisory states' => strpos($class, "'intervention'") !== false && strpos($class, "'effective'") !== false,
);
$failed = array_keys(array_filter($checks, static function ($ok) { return !$ok; }));
foreach ($checks as $label => $ok) echo ($ok ? 'PASS' : 'FAIL') . " - {$label}\n";
if ($failed) { fwrite(STDERR, 'Failed checks: ' . implode(', ', $failed) . "\n"); exit(1); }
echo 'v5.7.0 documentation effectiveness engine contract passed (' . count($checks) . " checks).\n";
