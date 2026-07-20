<?php
$root = dirname(__DIR__);
$class = file_get_contents($root . '/wordpress/sustainable-catalyst-feature-suggestions/includes/class-scfs-connected-product-support-platform.php');
$checks = array(
 'support center layer' => strpos($class, "'support_center'") !== false,
 'publication library layer' => strpos($class, "'publication_library'") !== false,
 'operational intelligence layer' => strpos($class, "'operational_intelligence'") !== false,
 'feedback intelligence layer' => strpos($class, "'feedback_intelligence'") !== false,
 'platform integration layer' => strpos($class, "'platform_integration'") !== false,
 'module inventory' => strpos($class, 'public function module_inventory()') !== false,
 'layer records' => strpos($class, 'public function layer_records()') !== false,
 'product dossiers' => strpos($class, 'public function product_record(') !== false,
 'platform overview' => strpos($class, 'public function overview_record(') !== false,
 'health record' => strpos($class, 'public function health_record()') !== false,
);
$failed = array_keys(array_filter($checks, static function ($ok) { return !$ok; }));
foreach ($checks as $label => $ok) echo ($ok ? 'PASS' : 'FAIL') . " - {$label}\n";
if ($failed) { fwrite(STDERR, 'Failed checks: ' . implode(', ', $failed) . "\n"); exit(1); }
echo 'v6.2.0 five-layer platform contract passed (' . count($checks) . " checks).\n";
