<?php
$root = dirname(__DIR__);
$class = file_get_contents($root . '/wordpress/sustainable-catalyst-feature-suggestions/includes/class-scfs-integrated-knowledge-base.php');
$checks = array(
    'two-panel outer layout' => strpos($class, 'scfs-kb-library-layout') !== false,
    'left navigation panel' => strpos($class, 'scfs-kb-library-navigation') !== false,
    'products navigation' => strpos($class, "esc_html__('Products'") !== false,
    'all-products navigation' => strpos($class, "esc_html__('All products'") !== false,
    'versions navigation' => strpos($class, "esc_html__('Versions'") !== false,
    'categories navigation' => strpos($class, "esc_html__('Categories'") !== false,
    'right results panel' => strpos($class, 'scfs-kb-library-results') !== false,
    'support articles heading' => strpos($class, "esc_html__('Support Articles'") !== false,
    'search interface' => strpos($class, 'scfs-kb-library-search') !== false,
    'component filter' => strpos($class, "render_browser_select('scfs_kb_component'") !== false,
    'article type filter' => strpos($class, "render_browser_select('scfs_kb_type'") !== false,
    'product query retained' => strpos($class, "scfs_kb_product") !== false,
    'version query retained' => strpos($class, "scfs_kb_version") !== false,
    'category query retained' => strpos($class, "scfs_kb_section") !== false,
    'search query retained' => strpos($class, "scfs_kb_search") !== false,
    'server-rendered article rows' => strpos($class, 'render_library_article_row') !== false,
);
$failed = array_keys(array_filter($checks, static function ($ok) { return !$ok; }));
foreach ($checks as $label => $ok) echo ($ok ? 'PASS' : 'FAIL') . " - {$label}\n";
if ($failed) { fwrite(STDERR, 'Failed checks: ' . implode(', ', $failed) . "\n"); exit(1); }
echo 'v5.2.7 two-panel library browser contract passed (' . count($checks) . " checks).\n";
