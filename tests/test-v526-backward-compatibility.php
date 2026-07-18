<?php
$root = dirname(__DIR__);
$plugin = $root . '/wordpress/sustainable-catalyst-feature-suggestions';
$main = file_get_contents($plugin . '/sustainable-catalyst-feature-suggestions.php');
$kb = file_get_contents($plugin . '/includes/class-scfs-knowledge-base.php');
$support = file_get_contents($plugin . '/includes/class-scfs-product-support-platform.php');
$manifest = json_decode(file_get_contents($root . '/feature_suggestions_manifest.json'), true);
$checks = array(
    'legacy PHP class' => strpos($main, 'final class Sustainable_Catalyst_Feature_Suggestions') !== false,
    'legacy suggestion CPT' => strpos($main, "const POST_TYPE = 'sc_feature_suggest';") !== false,
    'legacy text domain' => strpos($main, 'Text Domain: sustainable-catalyst-feature-suggestions') !== false,
    'legacy REST namespace' => strpos($main, "const REST_NAMESPACE = 'scfs/v1';") !== false,
    'legacy settings option' => strpos($main, "const OPTION_KEY = 'scfs_settings';") !== false,
    'support article CPT' => strpos($kb, "const ARTICLE_POST_TYPE = 'sc_support_article';") !== false,
    'known issue CPT' => strpos($kb, "const ISSUE_POST_TYPE = 'sc_known_issue';") !== false,
    'support center shortcode' => strpos($support, "const SHORTCODE = 'scfs_product_support_center';") !== false,
    'legacy support center shortcode' => strpos($support, "const LEGACY_SHORTCODE = 'scfs_support_center';") !== false,
    'knowledge base shortcode' => strpos($kb, "const SHORTCODE = 'scfs_support_knowledge_base';") !== false,
    'article URL base preserved' => strpos($kb, "'rewrite' => array('slug' => 'support/guides'") !== false,
    'no database migration required' => ($manifest['compatibility']['database_migration_required'] ?? null) === false,
);
$failed = array_keys(array_filter($checks, static function ($ok) { return !$ok; }));
foreach ($checks as $label => $ok) echo ($ok ? 'PASS' : 'FAIL') . " - {$label}\n";
if ($failed) { fwrite(STDERR, 'Failed checks: ' . implode(', ', $failed) . "\n"); exit(1); }
echo 'v5.2.6 backward compatibility contract passed (' . count($checks) . " checks).\n";
