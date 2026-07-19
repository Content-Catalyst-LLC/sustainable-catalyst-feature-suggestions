<?php
$root = dirname(__DIR__);
$css = file_get_contents($root . '/wordpress/sustainable-catalyst-feature-suggestions/assets/support-content-governance.css');
$js = file_get_contents($root . '/wordpress/sustainable-catalyst-feature-suggestions/assets/support-content-governance.js');
$class = file_get_contents($root . '/wordpress/sustainable-catalyst-feature-suggestions/includes/class-scfs-support-content-governance.php');
$checks = array(
    'summary grid CSS' => strpos($css, '.scfs-cg-summary') !== false,
    'queue table CSS' => strpos($css, '.scfs-cg-table') !== false,
    'state pill CSS' => strpos($css, '.scfs-cg-pill') !== false,
    'editor field CSS' => strpos($css, '.scfs-cg-field') !== false,
    'responsive CSS' => strpos($css, '@media (max-width: 782px)') !== false,
    'print CSS' => strpos($css, '@media print') !== false,
    'select all JavaScript' => strpos($js, 'data-scfs-cg-select-all') !== false,
    'bulk confirmation JavaScript' => strpos($js, "action.value === 'verify'") !== false,
    'CSS enqueued' => strpos($class, 'support-content-governance.css') !== false,
    'JavaScript enqueued' => strpos($class, 'support-content-governance.js') !== false,
);
$failed = array_keys(array_filter($checks, static function ($ok) { return !$ok; }));
foreach ($checks as $label => $ok) echo ($ok ? 'PASS' : 'FAIL') . " - {$label}\n";
if ($failed) { fwrite(STDERR, 'Failed checks: ' . implode(', ', $failed) . "\n"); exit(1); }
echo 'v5.5.0 content governance interface contract passed (' . count($checks) . " checks).\n";
