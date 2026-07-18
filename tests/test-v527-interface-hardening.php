<?php
$root = dirname(__DIR__);
$plugin = $root . '/wordpress/sustainable-catalyst-feature-suggestions';
$support_css = file_get_contents($plugin . '/assets/product-support-platform.css');
$kb_css = file_get_contents($plugin . '/assets/knowledge-base.css');
$classes = array(
    'scfs-unified-support-center-page',
    'scfs-support-integration-v527',
    'scfs-support-platform__guided-resolution',
    'scfs-support-platform__diagnostic',
    'scfs-support-platform__knowledge-base',
    'scfs-kb-library-layout',
    'scfs-kb-library-navigation',
    'scfs-kb-library-results',
    'scfs-kb-library-results-list',
    'scfs-support-library-article',
);
$checks = array(
    'v5.2.7 support CSS marker' => strpos($support_css, 'v5.2.7 Support Center production integration and interface hardening') !== false,
    'v5.2.7 KB CSS marker' => strpos($kb_css, 'v5.2.7 embedded browser production hardening') !== false,
    'horizontal overflow protection' => strpos($support_css, 'overflow-x: clip') !== false,
    'canonical scroll offsets' => strpos($support_css, 'scroll-margin-top: 104px') !== false,
    'two-panel desktop layout' => strpos($support_css, 'grid-template-columns: minmax(220px, 0.31fr) minmax(0, 1fr)') !== false,
    'tablet navigation layout' => strpos($support_css, 'grid-template-columns: repeat(3, minmax(0, 1fr))') !== false,
    'mobile navigation layout' => preg_match('/@media \(max-width: 700px\).*?scfs-kb-library-navigation.*?grid-template-columns: 1fr/s', $support_css) === 1,
    'print hardening' => strpos($support_css, '@media print') !== false && strpos($support_css, '.scfs-kb-library-search') !== false,
    'focus visible support' => strpos($kb_css, ':focus-visible') !== false,
);
foreach ($classes as $class) {
    $checks['CSS class ' . $class] = strpos($support_css . "\n" . $kb_css, '.' . $class) !== false;
}
$failed = array_keys(array_filter($checks, static function ($ok) { return !$ok; }));
foreach ($checks as $label => $ok) echo ($ok ? 'PASS' : 'FAIL') . " - {$label}\n";
if ($failed) { fwrite(STDERR, 'Failed checks: ' . implode(', ', $failed) . "\n"); exit(1); }
echo 'v5.2.7 Support Center interface hardening contract passed (' . count($checks) . " checks).\n";
