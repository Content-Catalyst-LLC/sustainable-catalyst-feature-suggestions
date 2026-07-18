<?php
$root = dirname(__DIR__);
$class = file_get_contents($root . '/wordpress/sustainable-catalyst-feature-suggestions/includes/class-scfs-integrated-knowledge-base.php');
$css = file_get_contents($root . '/wordpress/sustainable-catalyst-feature-suggestions/assets/knowledge-base.css');
$js = file_get_contents($root . '/wordpress/sustainable-catalyst-feature-suggestions/assets/integrated-knowledge-base.js');
$main = file_get_contents($root . '/wordpress/sustainable-catalyst-feature-suggestions/sustainable-catalyst-feature-suggestions.php');
$manifest = json_decode(file_get_contents($root . '/feature_suggestions_manifest.json'), true);
$checks = array(
    'plugin version header' => strpos($main, 'Version: 5.2.1') !== false,
    'runtime version constant' => strpos($main, "const VERSION = '5.2.1';") !== false,
    'knowledge base version' => strpos($class, "const VERSION = '5.2.1';") !== false,
    'category grouping' => strpos($class, 'browser_category_groups') !== false,
    'category-first view' => strpos($class, 'data-scfs-library-panel="categories"') !== false,
    'product alternate view' => strpos($class, 'data-scfs-library-panel="products"') !== false,
    'generated article rows' => strpos($class, 'render_library_article_row') !== false,
    'category details expanded' => strpos($class, 'data-scfs-kb-category open') !== false,
    'first product expanded' => strpos($class, '$product_index === 0') !== false,
    'section details expandable' => strpos($class, 'class="scfs-kb-section-folder"><summary>') !== false,
    'library controls' => strpos($class, 'scfs-support-library-controls') !== false,
    'view session persistence' => strpos($js, 'scfs-support-library-view') !== false,
    'expand all behavior' => strpos($js, 'data-scfs-kb-expand') !== false,
    'category library styling' => strpos($css, '.scfs-support-library-category') !== false,
    'article list styling' => strpos($css, '.scfs-support-library-article') !== false,
    'manifest version' => ($manifest['version'] ?? '') === '5.2.1',
    'manifest release name' => ($manifest['release_name'] ?? '') === 'Support Page Library Interface and Automatic Knowledge Base Rendering',
    'release notes' => file_exists($root . '/RELEASE_NOTES_5.2.1.md'),
);
$failed = array_keys(array_filter($checks, function ($value) { return !$value; }));
foreach ($checks as $label => $ok) echo ($ok ? 'PASS' : 'FAIL') . " - {$label}\n";
if ($failed) { echo 'Failed checks: ' . implode(', ', $failed) . "\n"; exit(1); }
echo 'v5.2.1 support documentation library contract passed (' . count($checks) . " checks).\n";
