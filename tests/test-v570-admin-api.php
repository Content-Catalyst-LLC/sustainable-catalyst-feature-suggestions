<?php
$root = dirname(__DIR__);
$class = file_get_contents($root . '/wordpress/sustainable-catalyst-feature-suggestions/includes/class-scfs-support-analytics-documentation-effectiveness.php');
$main = file_get_contents($root . '/backend/app/main.py');
$checks = array(
    'admin page' => strpos($class, "const ADMIN_PAGE = 'scfs-support-analytics';") !== false,
    'admin menu label' => strpos($class, "__('Support Analytics'") !== false,
    'CSV export' => strpos($class, 'public function export_action()') !== false,
    'WordPress schema route' => strpos($class, '/support-analytics/schema') !== false,
    'WordPress summary route' => strpos($class, '/support-analytics/summary') !== false,
    'WordPress product route' => strpos($class, '/support-analytics/product/') !== false,
    'WordPress trend route' => strpos($class, '/support-analytics/trend/') !== false,
    'FastAPI capabilities' => strpos($main, "/v1/support-analytics/capabilities") !== false,
    'FastAPI evaluate' => strpos($main, "/v1/support-analytics/evaluate") !== false,
    'FastAPI portfolio' => strpos($main, "/v1/support-analytics/portfolio") !== false,
    'FastAPI trends' => strpos($main, "/v1/support-analytics/trends/compare") !== false,
    'FastAPI report verification' => strpos($main, "/v1/support-analytics/reports/verify") !== false,
    'WP-CLI commands' => strpos($class, "scfs support-analytics refresh") !== false,
);
$failed = array_keys(array_filter($checks, static function ($ok) { return !$ok; }));
foreach ($checks as $label => $ok) echo ($ok ? 'PASS' : 'FAIL') . " - {$label}\n";
if ($failed) { fwrite(STDERR, 'Failed checks: ' . implode(', ', $failed) . "\n"); exit(1); }
echo 'v5.7.0 Support Analytics admin and API contract passed (' . count($checks) . " checks).\n";
