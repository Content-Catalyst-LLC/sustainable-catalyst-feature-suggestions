<?php
$root = dirname(__DIR__);
$plugin = $root . '/wordpress/sustainable-catalyst-feature-suggestions';
$foundation = file_get_contents($plugin . '/includes/class-scfs-knowledge-base.php');
$integrated = file_get_contents($plugin . '/includes/class-scfs-integrated-knowledge-base.php');
$css = file_get_contents($plugin . '/assets/knowledge-base.css');
$checks = array(
    'duplicate article decorator bypassed' => strpos($foundation, "get_post_type(\$post_id) === self::ARTICLE_POST_TYPE && class_exists('SCFS_Integrated_Knowledge_Base')") !== false,
    'publication renderer' => strpos($integrated, 'scfs-kb-publication') !== false,
    'publication masthead' => strpos($integrated, 'scfs-kb-publication-header') !== false,
    'publication metadata' => strpos($integrated, 'publication_meta_items') !== false,
    'verified version metadata' => strpos($integrated, "__('Verified version'") !== false,
    'product metadata' => strpos($integrated, "__('Product'") !== false,
    'component metadata' => strpos($integrated, "__('Component'") !== false,
    'updated metadata' => strpos($integrated, "__('Updated'") !== false,
    'reading time metadata' => strpos($integrated, "__('Reading time'") !== false,
    'related releases' => strpos($integrated, "__('Related releases'") !== false,
    'related known issues' => strpos($integrated, "__('Related known issues'") !== false,
    'related support articles' => strpos($integrated, "__('Related support articles'") !== false,
    'previous navigation' => strpos($integrated, "__('Previous article'") !== false,
    'next navigation' => strpos($integrated, "__('Next article'") !== false,
    'publication typography CSS' => strpos($css, '.scfs-kb-publication-body') !== false && strpos($css, '"Spartan"') !== false && strpos($css, '"Montserrat"') !== false,
    'cream information panel CSS' => strpos($css, '.scfs-kb-publication-note') !== false,
    'code block CSS' => strpos($css, '.scfs-kb-publication-body pre') !== false,
    'table CSS' => strpos($css, '.scfs-kb-publication-body table') !== false,
    'figure CSS' => strpos($css, '.scfs-kb-publication-body figure') !== false,
    'related cards CSS' => strpos($css, '.scfs-kb-publication-related-grid') !== false,
    'previous next CSS' => strpos($css, '.scfs-kb-publication-navigation') !== false,
    'feedback controls preserved in body flow' => strpos($integrated, "echo '<div class=\"scfs-kb-publication-body\">' . \$content . '</div>'") !== false,
    'responsive publication CSS' => strpos($css, '@media (max-width: 760px)') !== false,
    'print publication CSS' => strpos($css, '@media print') !== false,
);
$failed = array_keys(array_filter($checks, static function ($ok) { return !$ok; }));
foreach ($checks as $label => $ok) echo ($ok ? 'PASS' : 'FAIL') . " - {$label}\n";
if ($failed) { fwrite(STDERR, 'Failed checks: ' . implode(', ', $failed) . "\n"); exit(1); }
echo 'v5.2.5 publication-parity Support Article contract passed (' . count($checks) . " checks).\n";
