<?php
$root = dirname(__DIR__);
$class = file_get_contents($root . '/wordpress/sustainable-catalyst-feature-suggestions/includes/class-scfs-help-desk-customer-portal.php');
$css = file_get_contents($root . '/wordpress/sustainable-catalyst-feature-suggestions/assets/help-desk-customer-portal.css');
$js = file_get_contents($root . '/wordpress/sustainable-catalyst-feature-suggestions/assets/help-desk-customer-portal.js');
$checks = array(
 'Customer Portal admin label' => strpos($class, "__('Customer Portal'") !== false,
 'publication masthead' => strpos($class, 'scfs-customer-portal__masthead') !== false,
 'conversation renderer' => strpos($class, 'scfs-customer-portal__conversation') !== false,
 'secure access state' => strpos($class, 'Secure access required') !== false,
 'privacy panel' => strpos($class, 'scfs-customer-portal__privacy-note') !== false,
 'Spartan headings' => strpos($css, 'font-family: Spartan') !== false,
 'cream panels' => strpos($css, '--scfs-portal-cream: #fff8e7') !== false,
 'responsive CSS' => strpos($css, '@media (max-width: 782px)') !== false,
 'reduced motion CSS' => strpos($css, '@media (prefers-reduced-motion: reduce)') !== false,
 'print CSS' => strpos($css, '@media print') !== false,
 'double-submit protection' => strpos($js, "button.disabled = true") !== false,
 'dynamic view init' => strpos($js, 'scfs:support-view-loaded') !== false,
);
$failed = array_keys(array_filter($checks, static function ($ok) { return !$ok; }));
foreach ($checks as $label => $ok) echo ($ok ? 'PASS' : 'FAIL') . " - {$label}\n";
if ($failed) { fwrite(STDERR, 'Failed checks: ' . implode(', ', $failed) . "\n"); exit(1); }
echo 'v6.3.0 Customer Portal interface contract passed (' . count($checks) . " checks).\n";
