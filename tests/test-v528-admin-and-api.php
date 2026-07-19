<?php
$root = dirname(__DIR__);
$plugin = $root . '/wordpress/sustainable-catalyst-feature-suggestions';
$class = file_get_contents($plugin . '/includes/class-scfs-support-article-integrity.php');
$css = file_get_contents($plugin . '/assets/support-article-integrity.css');
$main_py = file_get_contents($root . '/backend/app/main.py');
$module_py = file_get_contents($root . '/backend/app/support_article_integrity.py');
$checks = array(
    'Article Integrity admin page' => strpos($class, "const ADMIN_SLUG = 'scfs-support-article-integrity';") !== false,
    'Publication Readiness meta box' => strpos($class, "__('Publication Readiness'") !== false,
    'validate article action' => strpos($class, 'admin_post_scfs_validate_support_article') !== false,
    'validate all action' => strpos($class, 'admin_post_scfs_validate_all_support_articles') !== false,
    'CSV export action' => strpos($class, 'admin_post_scfs_export_support_article_integrity') !== false,
    'admin readiness column' => strpos($class, "'scfs_integrity'") !== false,
    'admin readiness filter' => strpos($class, 'scfs_integrity_state') !== false,
    'row validation action' => strpos($class, 'Validate readiness') !== false,
    'REST schema route' => strpos($class, "self::REST_BASE . '/schema'") !== false,
    'REST article route' => strpos($class, "self::REST_BASE . '/articles/(?P<id>\\d+)'" ) !== false,
    'REST scan route' => strpos($class, "self::REST_BASE . '/scan'") !== false,
    'WP-CLI command' => strpos($class, "WP_CLI::add_command('scfs article-integrity'") !== false,
    'admin responsive CSS' => strpos($css, '@media (max-width: 600px)') !== false,
    'readiness badge CSS' => strpos($css, '.scfs-integrity-badge') !== false,
    'FastAPI capabilities route' => strpos($main_py, '/v1/support-article-integrity/capabilities') !== false,
    'FastAPI assessment route' => strpos($main_py, '/v1/support-article-integrity/assess') !== false,
    'deterministic backend evaluator' => strpos($module_py, 'def assess_support_article_integrity') !== false,
);
$failed = array_keys(array_filter($checks, static function ($ok) { return !$ok; }));
foreach ($checks as $label => $ok) echo ($ok ? 'PASS' : 'FAIL') . " - {$label}\n";
if ($failed) { fwrite(STDERR, 'Failed checks: ' . implode(', ', $failed) . "\n"); exit(1); }
echo 'v5.2.8 Support Article integrity administration and API contract passed (' . count($checks) . " checks).\n";
