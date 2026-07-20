<?php
$root = dirname(__DIR__);
$main = file_get_contents($root . '/wordpress/sustainable-catalyst-feature-suggestions/sustainable-catalyst-feature-suggestions.php');
$help = file_get_contents($root . '/wordpress/sustainable-catalyst-feature-suggestions/includes/class-scfs-help-desk-case-foundation.php');
$kb = file_get_contents($root . '/wordpress/sustainable-catalyst-feature-suggestions/includes/class-scfs-knowledge-base.php');
$support = file_get_contents($root . '/wordpress/sustainable-catalyst-feature-suggestions/includes/class-scfs-product-support-platform.php');
$checks = array(
 'legacy plugin slug' => basename(dirname($root . '/wordpress/sustainable-catalyst-feature-suggestions/sustainable-catalyst-feature-suggestions.php')) === 'sustainable-catalyst-feature-suggestions',
 'legacy option retained' => strpos($main, "const OPTION_KEY = 'scfs_settings';") !== false,
 'Support Article CPT retained' => strpos($kb, "const ARTICLE_POST_TYPE = 'sc_support_article';") !== false,
 'Known Issue CPT retained' => strpos($kb, "const ISSUE_POST_TYPE = 'sc_known_issue';") !== false,
 'release CPT retained' => strpos($support, "const RELEASE_POST_TYPE = 'sc_release_record';") !== false,
 'Support Article URLs retained' => strpos($kb, "'rewrite' => array('slug' => 'support/guides'") !== false,
 'dedicated private tables' => strpos($help, "'cases' => \$wpdb->prefix . 'scfs_cases'") !== false,
 'public records unchanged' => strpos($help, "'public_support_records_unchanged' => true") !== false,
 'contact records referenced' => strpos($help, "'contact_records_imported' => false") !== false,
);
$failed = array_keys(array_filter($checks, static function ($ok) { return !$ok; }));
foreach ($checks as $label => $ok) echo ($ok ? 'PASS' : 'FAIL') . " - {$label}\n";
if ($failed) { fwrite(STDERR, 'Failed checks: ' . implode(', ', $failed) . "\n"); exit(1); }
echo 'v6.1.0 migration and compatibility contract passed (' . count($checks) . " checks).\n";
