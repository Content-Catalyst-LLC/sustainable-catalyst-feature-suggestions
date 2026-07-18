<?php
$root = dirname(__DIR__);
$plugin = $root . '/wordpress/sustainable-catalyst-feature-suggestions';
$integrated = file_get_contents($plugin . '/includes/class-scfs-integrated-knowledge-base.php');
$page = file_get_contents($plugin . '/content/knowledge-base/page.html');
$checks = array(
    'route contract bumped' => strpos($integrated, "const ROUTE_VERSION = '2.1.0';") !== false,
    'runtime shortcode repair filter' => strpos($integrated, "add_filter('the_content', array(\$this, 'repair_dedicated_page_shortcode'), 8)") !== false,
    'comment stripping before shortcode check' => strpos($integrated, "preg_replace('/<!--.*?-->/s'") !== false,
    'commented shortcode recovery' => strpos($integrated, 'preg_quote(SCFS_Knowledge_Base_Foundation::SHORTCODE') !== false,
    'library-region injection' => strpos($integrated, 'sc-kb-page__library') !== false,
    'persistent route repair' => strpos($integrated, 'persist_dedicated_page_shortcode_repair') !== false,
    'bundled page loader' => strpos($integrated, "content/knowledge-base/page.html") !== false,
    'early dedicated page asset loading' => strpos($integrated, 'if ($this->is_dedicated_knowledge_base_page())') !== false && strpos($integrated, "wp_enqueue_style('scfs-knowledge-base')") !== false,
    'page has executable shortcode' => strpos(preg_replace('/<!--.*?-->/s', '', $page), '[scfs_support_knowledge_base]') !== false,
    'page no commented shortcode placeholder' => !preg_match('/<!--(?:(?!-->).)*\[scfs_support_knowledge_base[^\]]*\](?:(?!-->).)*-->/s', $page),
    'full page structure bundled' => strpos($page, 'class="sc-kb-page__hero"') !== false && strpos($page, 'class="sc-kb-page__footer-panel"') !== false,
);
$failed = array_keys(array_filter($checks, static function ($ok) { return !$ok; }));
foreach ($checks as $label => $ok) echo ($ok ? 'PASS' : 'FAIL') . " - {$label}\n";
if ($failed) { fwrite(STDERR, 'Failed checks: ' . implode(', ', $failed) . "\n"); exit(1); }
echo 'v5.2.5 Knowledge Base rendering repair contract passed (' . count($checks) . " checks).\n";
