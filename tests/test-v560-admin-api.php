<?php
$root = dirname(__DIR__);
$class = file_get_contents($root . '/wordpress/sustainable-catalyst-feature-suggestions/includes/class-scfs-feedback-product-signals.php');
$css = file_get_contents($root . '/wordpress/sustainable-catalyst-feature-suggestions/assets/feedback-product-signals.css');
$js = file_get_contents($root . '/wordpress/sustainable-catalyst-feature-suggestions/assets/feedback-product-signals.js');
$checks = array(
    'Product Signals admin page' => strpos($class, "const ADMIN_PAGE = 'scfs-feedback-product-signals';") !== false,
    'administrator capability' => strpos($class, "'edit_others_posts'") !== false,
    'summary REST route' => strpos($class, '/feedback-product-signals/summary') !== false,
    'products REST route' => strpos($class, '/feedback-product-signals/products') !== false,
    'product REST route' => strpos($class, '/feedback-product-signals/product/(?P<product>[a-z0-9-]+)') !== false,
    'protected refresh route' => strpos($class, '/feedback-product-signals/refresh') !== false && strpos($class, "current_user_can('manage_options')") !== false,
    'CSV export' => strpos($class, 'scfs-product-signals-') !== false,
    'daily refresh' => strpos($class, "const CRON_HOOK = 'scfs_feedback_product_signal_daily';") !== false,
    'WP-CLI commands' => strpos($class, 'scfs product-signals refresh') !== false && strpos($class, 'scfs product-signals summary') !== false,
    'summary CSS' => strpos($css, '.scfs-fps-summary') !== false,
    'responsive CSS' => strpos($css, '@media (max-width: 782px)') !== false,
    'print CSS' => strpos($css, '@media print') !== false,
    'JavaScript enhancement' => strpos($js, 'data-scfs-fps-state') !== false,
);
$failed = array_keys(array_filter($checks, static function ($ok) { return !$ok; }));
foreach ($checks as $label => $ok) echo ($ok ? 'PASS' : 'FAIL') . " - {$label}\n";
if ($failed) { fwrite(STDERR, 'Failed checks: ' . implode(', ', $failed) . "\n"); exit(1); }
echo 'v5.6.0 Product Signals admin and API contract passed (' . count($checks) . " checks).\n";
