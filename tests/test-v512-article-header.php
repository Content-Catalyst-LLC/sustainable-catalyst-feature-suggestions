<?php
$root = dirname(__DIR__);
$php = file_get_contents($root . '/wordpress/sustainable-catalyst-feature-suggestions/includes/class-scfs-integrated-knowledge-base.php');
$css = file_get_contents($root . '/wordpress/sustainable-catalyst-feature-suggestions/assets/knowledge-base.css');
$checks = array(
    'custom article header' => strpos($php, 'scfs-kb-article-header') !== false,
    'custom article title' => strpos($php, 'scfs-kb-article-title') !== false,
    'article summary' => strpos($php, 'scfs-kb-article-summary') !== false,
    'theme header hidden only for support article' => strpos($css, '.type-sc_support_article>.entry-header{display:none!important}') !== false,
    'balanced responsive title' => strpos($css, 'text-wrap:balance') !== false,
    'publication article palette' => strpos($css, '--scfs-publication-panel:#faf5e8') !== false,
    'publication style section rule' => strpos($css, '.scfs-kb-article-content h2::after') !== false,
    'publication style breakout tables' => strpos($css, '.sckb-table-wrap') !== false && strpos($css, 'transform:translateX(-50%)') !== false,
    'directory expanded by default' => strpos($php, 'class="scfs-kb-directory" open') !== false,
    'product folders expanded by default' => strpos($php, 'data-scfs-kb-product open') !== false,
    'section folders expanded by default' => strpos($php, 'class="scfs-kb-section-folder" open') !== false,
);
foreach ($checks as $label => $ok) { if (!$ok) { fwrite(STDERR, "FAIL: $label\n"); exit(1); } }
echo 'PASS - ' . count($checks) . " Knowledge Base article header checks\n";
