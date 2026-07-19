<?php
$root = dirname(__DIR__);
$class = file_get_contents($root . '/wordpress/sustainable-catalyst-feature-suggestions/includes/class-scfs-cross-product-support-graph.php');
$main = file_get_contents($root . '/backend/app/main.py');
$checks = array(
    'admin page' => strpos($class, "const ADMIN_PAGE = 'scfs-support-graph';") !== false,
    'admin menu label' => strpos($class, "__('Support Graph'") !== false,
    'JSON export' => strpos($class, 'public function export_action()') !== false,
    'WordPress schema route' => strpos($class, '/support-graph/schema') !== false,
    'WordPress overview route' => strpos($class, '/support-graph/overview') !== false,
    'WordPress product route' => strpos($class, '/support-graph/product/') !== false,
    'WordPress handoff route' => strpos($class, '/support-graph/handoffs') !== false,
    'WordPress path route' => strpos($class, '/support-graph/path') !== false,
    'WordPress integrity route' => strpos($class, '/support-graph/integrity') !== false,
    'FastAPI capabilities' => strpos($main, "/v1/support-graph/capabilities") !== false,
    'FastAPI graph build' => strpos($main, "/v1/support-graph/build") !== false,
    'FastAPI handoff planning' => strpos($main, "/v1/support-graph/handoffs/plan") !== false,
    'FastAPI path finding' => strpos($main, "/v1/support-graph/paths/find") !== false,
    'FastAPI integrity' => strpos($main, "/v1/support-graph/integrity/verify") !== false,
    'WP-CLI commands' => strpos($class, 'scfs support-graph refresh') !== false,
);
$failed = array_keys(array_filter($checks, static function ($ok) { return !$ok; }));
foreach ($checks as $label => $ok) echo ($ok ? 'PASS' : 'FAIL') . " - {$label}\n";
if ($failed) { fwrite(STDERR, 'Failed checks: ' . implode(', ', $failed) . "\n"); exit(1); }
echo 'v5.8.0 Support Graph admin and API contract passed (' . count($checks) . " checks).\n";
