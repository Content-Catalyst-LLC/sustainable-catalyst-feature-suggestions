<?php
$root = dirname(__DIR__);
$class = file_get_contents($root . '/wordpress/sustainable-catalyst-feature-suggestions/includes/class-scfs-connected-product-support-platform.php');
$main = file_get_contents($root . '/backend/app/main.py');
$css = file_get_contents($root . '/wordpress/sustainable-catalyst-feature-suggestions/assets/connected-product-support-platform.css');
$js = file_get_contents($root . '/wordpress/sustainable-catalyst-feature-suggestions/assets/connected-product-support-platform.js');
$checks = array(
 'primary shortcode' => strpos($class, "const SHORTCODE = 'scfs_connected_product_support_platform';") !== false,
 'legacy shortcode' => strpos($class, "const LEGACY_SHORTCODE = 'scfs_connected_support_platform';") !== false,
 'schema route' => strpos($class, '/connected-platform/schema') !== false,
 'overview route' => strpos($class, '/connected-platform/overview') !== false,
 'products route' => strpos($class, '/connected-platform/products') !== false,
 'product route' => strpos($class, '/connected-platform/product/') !== false,
 'journey route' => strpos($class, '/connected-platform/journey') !== false,
 'health route' => strpos($class, '/connected-platform/health') !== false,
 'FastAPI capabilities' => strpos($main, '/v1/connected-platform/capabilities') !== false,
 'FastAPI evaluate' => strpos($main, '/v1/connected-platform/evaluate') !== false,
 'responsive CSS' => strpos($css, '@media (max-width: 780px)') !== false,
 'print CSS' => strpos($css, '@media print') !== false,
 'JS initialization' => strpos($js, '[data-scfs-connected-platform]') !== false,
);
$failed = array_keys(array_filter($checks, static function ($ok) { return !$ok; }));
foreach ($checks as $label => $ok) echo ($ok ? 'PASS' : 'FAIL') . " - {$label}\n";
if ($failed) { fwrite(STDERR, 'Failed checks: ' . implode(', ', $failed) . "\n"); exit(1); }
echo 'v6.0.0 API and interface contract passed (' . count($checks) . " checks).\n";
