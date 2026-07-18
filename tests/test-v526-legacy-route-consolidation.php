<?php
$root = dirname(__DIR__);
$plugin = $root . '/wordpress/sustainable-catalyst-feature-suggestions';
$class = file_get_contents($plugin . '/includes/class-scfs-integrated-knowledge-base.php');
$page = file_get_contents($plugin . '/content/knowledge-base/page.html');
$checks = array(
    'route version 3' => strpos($class, "const ROUTE_VERSION = '3.1.0';") !== false,
    'template redirect hook' => strpos($class, "add_action('template_redirect', array(\$this, 'redirect_legacy_knowledge_base_routes'), 1)") !== false,
    'legacy child route recognized' => strpos($class, "'/support/knowledge-base'") !== false,
    'legacy archive route recognized' => strpos($class, "'/support-documentation'") !== false,
    'archive query recognized' => strpos($class, 'is_post_type_archive(SCFS_Knowledge_Base_Foundation::ARTICLE_POST_TYPE)') !== false,
    'filter query preserved' => strpos($class, "'scfs_kb_search'") !== false && strpos($class, "'scfs_kb_product'") !== false && strpos($class, "'scfs_kb_version'") !== false,
    'redirect status is 301' => strpos($class, 'wp_safe_redirect($target, 301') !== false,
    'support anchor target' => substr_count($class, "'#knowledge-base'") >= 2,
    'support guides excluded by design' => strpos($class, 'Support Article permalinks under /support/guides/') !== false,
    'fallback does not render second browser' => strpos($page, '[scfs_support_knowledge_base]') === false,
    'fallback CSS class' => strpos($page, 'sc-kb-legacy-route') !== false,
);
$failed = array_keys(array_filter($checks, static function ($ok) { return !$ok; }));
foreach ($checks as $label => $ok) echo ($ok ? 'PASS' : 'FAIL') . " - {$label}\n";
if ($failed) { fwrite(STDERR, 'Failed checks: ' . implode(', ', $failed) . "\n"); exit(1); }
echo 'v5.2.7 legacy route consolidation contract passed (' . count($checks) . " checks).\n";
