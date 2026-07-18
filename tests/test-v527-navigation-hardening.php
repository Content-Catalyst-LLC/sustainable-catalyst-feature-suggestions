<?php
$root = dirname(__DIR__);
$plugin = $root . '/wordpress/sustainable-catalyst-feature-suggestions';
$support = file_get_contents($plugin . '/includes/class-scfs-product-support-platform.php');
$integrated = file_get_contents($plugin . '/includes/class-scfs-integrated-knowledge-base.php');
$js = file_get_contents($plugin . '/assets/product-support-platform.js');
$kbjs = file_get_contents($plugin . '/assets/integrated-knowledge-base.js');
$checks = array(
    'support center root anchor' => strpos($support, "'anchor' => 'support-center'") !== false && strpos($support, 'id="\' . esc_attr($root_id)') !== false,
    'guided resolution anchor' => strpos($support, 'id="guided-resolution"') !== false,
    'knowledge base anchor' => strpos($support, 'id="knowledge-base"') !== false,
    'known issues anchor' => strpos($support, 'id="known-issues"') !== false,
    'release intelligence anchor' => strpos($support, 'id="release-intelligence"') !== false,
    'abort controller' => strpos($js, 'new AbortController()') !== false,
    'stale request cancellation' => strpos($js, 'previousController.abort()') !== false,
    'dynamic KB enhancement' => strpos($js, 'SCFSIntegratedKnowledgeBase.initAll(workspace)') !== false,
    'hash navigation' => strpos($js, 'function scrollToHash(') !== false && strpos($js, "window.addEventListener('hashchange'") !== false,
    'reduced motion' => strpos($js, "prefers-reduced-motion: reduce") !== false,
    'KB API exposure' => strpos($kbjs, 'window.SCFSIntegratedKnowledgeBase') !== false,
    'KB dynamic event' => strpos($kbjs, "document.addEventListener('scfs:support-view-changed'") !== false,
    'route contract 3.1' => strpos($integrated, "const ROUTE_VERSION = '3.1.0';") !== false,
    'redirect loop guard' => strpos($integrated, 'request_is_redirect_target') !== false,
    'redirect no-cache' => strpos($integrated, "function_exists('nocache_headers')") !== false,
);
$failed = array_keys(array_filter($checks, static function ($ok) { return !$ok; }));
foreach ($checks as $label => $ok) echo ($ok ? 'PASS' : 'FAIL') . " - {$label}\n";
if ($failed) { fwrite(STDERR, 'Failed checks: ' . implode(', ', $failed) . "\n"); exit(1); }
echo 'v5.2.7 navigation and legacy-route hardening contract passed (' . count($checks) . " checks).\n";
