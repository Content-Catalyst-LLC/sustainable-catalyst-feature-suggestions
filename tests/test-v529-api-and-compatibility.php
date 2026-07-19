<?php
$root = dirname(__DIR__);
$main = file_get_contents($root . '/wordpress/sustainable-catalyst-feature-suggestions/sustainable-catalyst-feature-suggestions.php');
$class = file_get_contents($root . '/wordpress/sustainable-catalyst-feature-suggestions/includes/class-scfs-support-discovery.php');
$kb = file_get_contents($root . '/wordpress/sustainable-catalyst-feature-suggestions/includes/class-scfs-knowledge-base.php');
$js = file_get_contents($root . '/wordpress/sustainable-catalyst-feature-suggestions/assets/integrated-knowledge-base.js');
$checks = array(
    'legacy suggestion CPT' => strpos($main, "const POST_TYPE = 'sc_feature_suggest';") !== false,
    'legacy REST namespace' => strpos($main, "const REST_NAMESPACE = 'scfs/v1';") !== false,
    'support article CPT' => strpos($kb, "const ARTICLE_POST_TYPE = 'sc_support_article';") !== false,
    'article permalink base' => strpos($kb, "'rewrite' => array('slug' => 'support/guides'") !== false,
    'discovery schema route' => strpos($class, '/knowledge-base/discovery/schema') !== false,
    'discovery search route' => strpos($class, '/knowledge-base/discovery/search') !== false,
    'discovery suggest route' => strpos($class, '/knowledge-base/discovery/suggest') !== false,
    'discovery result article ID' => strpos($class, "'article_id' => (string) \$post->ID") !== false,
    'discovery result version' => substr_count($class, "'version' => self::VERSION") >= 2,
    'no personal search database' => strpos($class, "'personal_data_stored' => false") !== false,
    'no browser search history storage' => strpos($js, 'localStorage') === false,
    'FastAPI discovery endpoint' => strpos(file_get_contents($root . '/backend/app/main.py'), '/v1/support-discovery/search') !== false,
);
$failed = array_keys(array_filter($checks, static function ($ok) { return !$ok; }));
foreach ($checks as $label => $ok) echo ($ok ? 'PASS' : 'FAIL') . " - {$label}\n";
if ($failed) { fwrite(STDERR, 'Failed checks: ' . implode(', ', $failed) . "\n"); exit(1); }
echo 'v5.2.9 API and compatibility contract passed (' . count($checks) . " checks).\n";
