<?php
$root = dirname(__DIR__);
$class = file_get_contents($root . '/wordpress/sustainable-catalyst-feature-suggestions/includes/class-scfs-public-support-integrations.php');
$css = file_get_contents($root . '/wordpress/sustainable-catalyst-feature-suggestions/assets/public-support-integrations.css');
$js = file_get_contents($root . '/wordpress/sustainable-catalyst-feature-suggestions/assets/public-support-integrations.js');
$checks = array(
 'primary shortcode' => strpos($class, "const SHORTCODE = 'scfs_support_embed';") !== false,
 'legacy shortcode' => strpos($class, "const LEGACY_SHORTCODE = 'scfs_product_support_embed';") !== false,
 'embed planner' => strpos($class, 'public function embed_plan(') !== false,
 'product context' => strpos($class, 'public function product_contract(') !== false,
 'version verification' => strpos($class, 'public function verify_version(') !== false,
 'article view' => strpos($class, "'articles'") !== false,
 'issue view' => strpos($class, "'issues'") !== false,
 'release view' => strpos($class, "'releases'") !== false,
 'desktop CSS' => strpos($css, 'grid-template-columns: repeat(2') !== false,
 'mobile CSS' => strpos($css, '@media (max-width: 560px)') !== false,
 'print CSS' => strpos($css, '@media print') !== false,
 'JS initialization' => strpos($js, '[data-scfs-support-embed]') !== false,
);
$failed = array_keys(array_filter($checks, static function ($ok) { return !$ok; }));
foreach ($checks as $label => $ok) echo ($ok ? 'PASS' : 'FAIL') . " - {$label}\n";
if ($failed) { fwrite(STDERR, 'Failed checks: ' . implode(', ', $failed) . "\n"); exit(1); }
echo 'v5.9.0 embed contract passed (' . count($checks) . " checks).\n";
