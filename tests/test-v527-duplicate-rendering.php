<?php
$root = dirname(__DIR__);
$plugin = $root . '/wordpress/sustainable-catalyst-feature-suggestions';
$support = file_get_contents($plugin . '/includes/class-scfs-product-support-platform.php');
$js = file_get_contents($plugin . '/assets/product-support-platform.js');
$css = file_get_contents($plugin . '/assets/product-support-platform.css');
$checks = array(
    'render signature registry' => strpos($support, 'private static $render_signatures = array();') !== false,
    'root ID registry' => strpos($support, 'private static $used_root_ids = array();') !== false,
    'duplicate setting default' => strpos($support, "'suppress_duplicate_centers' => '1'") !== false,
    'allow duplicate shortcode attribute' => strpos($support, "'allow_duplicate' => '0'") !== false,
    'request signature method' => strpos($support, 'private function render_signature(') !== false,
    'server duplicate suppression' => strpos($support, 'isset(self::$render_signatures[$signature])') !== false,
    'duplicate notice method' => strpos($support, 'private function duplicate_render_notice(') !== false,
    'canonical root ID' => strpos($support, '$root_id = !isset(self::$used_root_ids[$anchor]) ? $anchor') !== false,
    'client duplicate guard' => strpos($js, 'function canonicalizeRoot(root)') !== false,
    'explicit duplicate exception' => strpos($js, "root.dataset.scfsAllowDuplicate === '1'") !== false,
    'duplicate hidden CSS' => strpos($css, '[data-scfs-duplicate-suppressed="1"]') !== false,
);
$failed = array_keys(array_filter($checks, static function ($ok) { return !$ok; }));
foreach ($checks as $label => $ok) echo ($ok ? 'PASS' : 'FAIL') . " - {$label}\n";
if ($failed) { fwrite(STDERR, 'Failed checks: ' . implode(', ', $failed) . "\n"); exit(1); }
echo 'v5.2.7 duplicate Support Center rendering contract passed (' . count($checks) . " checks).\n";
