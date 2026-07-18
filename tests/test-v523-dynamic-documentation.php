<?php
$root = dirname(__DIR__);
$main = file_get_contents($root . '/wordpress/sustainable-catalyst-feature-suggestions/sustainable-catalyst-feature-suggestions.php');
$class = file_get_contents($root . '/wordpress/sustainable-catalyst-feature-suggestions/includes/class-scfs-integrated-knowledge-base.php');
$css = file_get_contents($root . '/wordpress/sustainable-catalyst-feature-suggestions/assets/knowledge-base.css');
$manifest = json_decode(file_get_contents($root . '/feature_suggestions_manifest.json'), true);
$checks = array(
    'plugin version' => strpos($main, 'Version: 5.2.3') !== false,
    'runtime version' => strpos($main, "const VERSION = '5.2.3';") !== false,
    'integrated version' => strpos($class, "const VERSION = '5.2.3';") !== false,
    'published record query' => strpos($class, 'all_published_articles') !== false,
    'valid permalink guard' => strpos($class, 'if (!$permalink) continue;') !== false,
    'dynamic search matcher' => strpos($class, 'article_matches_search') !== false,
    'compact inline article results' => strpos($class, 'scfs-support-library-compact__articles') !== false,
    'refined two column layout' => strpos($class, 'scfs-kb-refined__layout') !== false,
    'refined css' => strpos($css, 'v5.2.3 Dynamic records, permalink integrity, refined full browser') !== false,
    'manifest version' => ($manifest['version'] ?? '') === '5.2.3',
    'manifest release name' => ($manifest['release_name'] ?? '') === 'Dynamic Documentation Records, Permalink Integrity, and Knowledge Base Interface Refinement',
    'release notes' => file_exists($root . '/RELEASE_NOTES_5.2.3.md'),
);
$failed = array_keys(array_filter($checks, fn($ok) => !$ok));
foreach ($checks as $label => $ok) echo ($ok ? 'PASS' : 'FAIL') . " - $label\n";
if ($failed) { echo 'Failed checks: ' . implode(', ', $failed) . "\n"; exit(1); }
echo 'v5.2.3 dynamic documentation contract passed (' . count($checks) . " checks).\n";
