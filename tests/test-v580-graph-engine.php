<?php
$root = dirname(__DIR__);
$class = file_get_contents($root . '/wordpress/sustainable-catalyst-feature-suggestions/includes/class-scfs-cross-product-support-graph.php');
$checks = array(
    'canonical catalog' => strpos($class, 'public function default_product_catalog()') !== false,
    'product registry' => strpos($class, 'public function product_registry()') !== false,
    'product nodes' => strpos($class, 'public function product_node_record($slug, $include_records = true)') !== false,
    'coverage score' => strpos($class, 'public function coverage_score($counts, $capability_count = 0)') !== false,
    'coverage states' => strpos($class, "return 'connected';") !== false && strpos($class, "return 'unmapped';") !== false,
    'graph edges' => strpos($class, 'public function graph_edges()') !== false,
    'graph record' => strpos($class, 'public function graph_record($include_records = false)') !== false,
    'handoff planner' => strpos($class, 'public function handoff_plan($product, $intent = \'\', $component = \'\', $version = \'\')') !== false,
    'shortest paths' => strpos($class, 'public function shortest_path($source, $target)') !== false,
    'integrity report' => strpos($class, 'public function integrity_report($nodes = null, $edges = null)') !== false,
    'existing orchestration reuse' => strpos($class, 'SCFS_Cross_Product_Support_Orchestration::instance()->dependency_records()') !== false,
    'extension filter' => strpos($class, "apply_filters('scfs_cross_product_support_catalog'") !== false,
);
$failed = array_keys(array_filter($checks, static function ($ok) { return !$ok; }));
foreach ($checks as $label => $ok) echo ($ok ? 'PASS' : 'FAIL') . " - {$label}\n";
if ($failed) { fwrite(STDERR, 'Failed checks: ' . implode(', ', $failed) . "\n"); exit(1); }
echo 'v5.8.0 Support Graph engine contract passed (' . count($checks) . " checks).\n";
