<?php
$root = dirname(__DIR__);
$class = file_get_contents($root . '/wordpress/sustainable-catalyst-feature-suggestions/includes/class-scfs-cross-product-support-graph.php');
$css = file_get_contents($root . '/wordpress/sustainable-catalyst-feature-suggestions/assets/cross-product-support-graph.css');
$js = file_get_contents($root . '/wordpress/sustainable-catalyst-feature-suggestions/assets/cross-product-support-graph.js');
$checks = array(
    'primary shortcode' => strpos($class, "const SHORTCODE = 'scfs_cross_product_support_graph';") !== false,
    'compatibility shortcode' => strpos($class, "const LEGACY_SHORTCODE = 'scfs_platform_support_graph';") !== false,
    'Support Center navigation' => strpos($class, "'support-graph'") !== false,
    'product selector' => strpos($class, 'scfs_graph_product') !== false,
    'intent field' => strpos($class, 'scfs_graph_intent') !== false,
    'component field' => strpos($class, 'scfs_graph_component') !== false,
    'handoff cards' => strpos($class, 'scfs-support-graph__handoff') !== false,
    'privacy note' => strpos($class, 'The graph uses public support records') !== false,
    'desktop CSS' => strpos($css, 'grid-template-columns: minmax(180px, 0.8fr)') !== false,
    'tablet CSS' => strpos($css, '@media (max-width: 980px)') !== false,
    'mobile CSS' => strpos($css, '@media (max-width: 700px)') !== false,
    'print CSS' => strpos($css, '@media print') !== false,
    'JavaScript initialization' => strpos($js, '[data-scfs-support-graph]') !== false,
);
$failed = array_keys(array_filter($checks, static function ($ok) { return !$ok; }));
foreach ($checks as $label => $ok) echo ($ok ? 'PASS' : 'FAIL') . " - {$label}\n";
if ($failed) { fwrite(STDERR, 'Failed checks: ' . implode(', ', $failed) . "\n"); exit(1); }
echo 'v5.8.0 Support Graph interface contract passed (' . count($checks) . " checks).\n";
