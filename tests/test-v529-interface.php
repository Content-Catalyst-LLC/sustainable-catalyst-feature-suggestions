<?php
$root = dirname(__DIR__);
$css = file_get_contents($root . '/wordpress/sustainable-catalyst-feature-suggestions/assets/knowledge-base.css');
$js = file_get_contents($root . '/wordpress/sustainable-catalyst-feature-suggestions/assets/integrated-knowledge-base.js');
$checks = array(
    'breadcrumb CSS' => strpos($css, '.scfs-kb-discovery-breadcrumbs') !== false,
    'filter chip CSS' => strpos($css, '.scfs-kb-active-filters') !== false,
    'recovery CSS' => strpos($css, '.scfs-kb-empty--recovery') !== false,
    'responsive CSS' => strpos($css, '@media(max-width:760px)') !== false,
    'print CSS' => strpos($css, '@media print') !== false,
    'slash shortcut' => strpos($js, "event.key !== '/'") !== false,
    'escape clear' => strpos($js, "event.key === 'Escape'") !== false,
    'dynamic initialization' => strpos($js, "scfs:support-view-changed") !== false,
    'progressive navigation state' => strpos($js, "is-navigating") !== false,
);
$failed = array_keys(array_filter($checks, static function ($ok) { return !$ok; }));
foreach ($checks as $label => $ok) echo ($ok ? 'PASS' : 'FAIL') . " - {$label}\n";
if ($failed) { fwrite(STDERR, 'Failed checks: ' . implode(', ', $failed) . "\n"); exit(1); }
echo 'v5.2.9 discovery interface contract passed (' . count($checks) . " checks).\n";
