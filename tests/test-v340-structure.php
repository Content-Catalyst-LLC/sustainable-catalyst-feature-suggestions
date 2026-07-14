<?php
$root = dirname(__DIR__);
$plugin = $root . '/wordpress/sustainable-catalyst-feature-suggestions';
$main = file_get_contents($plugin . '/sustainable-catalyst-feature-suggestions.php');
$intel = file_get_contents($plugin . '/includes/class-scfs-documentation-intelligence.php');
$opportunity = file_get_contents($plugin . '/includes/class-scfs-opportunity-workflow.php');
$guided = file_get_contents($plugin . '/includes/class-scfs-guided-resolution.php');
$knowledge = file_get_contents($plugin . '/includes/class-scfs-knowledge-base.php');
$backend = file_get_contents($root . '/backend/app/main.py');
$manifest = json_decode(file_get_contents($root . '/feature_suggestions_manifest.json'), true);
$checks = array(
    'plugin version' => strpos($main, 'Version: 3.4.0') !== false,
    'runtime version' => strpos($main, "const VERSION = '3.4.0';") !== false,
    'intelligence class file' => file_exists($plugin . '/includes/class-scfs-documentation-intelligence.php'),
    'intelligence bootstrap' => strpos($main, 'SCFS_Documentation_Feature_Intelligence::instance();') !== false,
    'intelligence activation' => strpos($main, 'SCFS_Documentation_Feature_Intelligence::activate();') !== false,
    'article feedback table' => strpos($intel, 'scfs_article_feedback') !== false,
    'relationship table' => strpos($intel, 'scfs_support_relationships') !== false,
    'documentation gap post type' => strpos($intel, "const GAP_POST_TYPE = 'sc_doc_gap';") !== false,
    'feedback form' => strpos($intel, 'Was this article helpful?') !== false,
    'privacy redaction' => strpos($intel, '[secret removed]') !== false,
    'failed search source' => strpos($intel, "resolution_state IN ('no_match','low_confidence')") !== false,
    'gap scoring' => strpos($intel, 'calculate_gap_score') !== false,
    'case article relationship' => strpos($intel, 'case_article') !== false,
    'case suggestion relationship' => strpos($intel, 'case_suggestion') !== false,
    'opaque case reference boundary' => strpos($intel, 'external_case_reference_must_be_opaque') !== false,
    'feedback REST route' => strpos($intel, "'/knowledge-base/articles/(?P<id>\\d+)/feedback'") !== false,
    'gap refresh REST route' => strpos($intel, "'/documentation-intelligence/gaps/refresh'") !== false,
    'relationship REST route' => strpos($intel, "'/documentation-intelligence/relationships'") !== false,
    'support demand REST route' => strpos($intel, "'/suggestions/(?P<id>\\d+)/support-demand'") !== false,
    'support demand opportunity dimension' => strpos($opportunity, "'support_demand' => absint") !== false,
    'guided handoff relationship callback' => strpos($guided, 'documentation-intelligence/relationships') !== false,
    'article search attribution' => strpos($guided, "add_query_arg('scfs_search_id'") !== false,
    'knowledge base feedback aggregate' => strpos($knowledge, 'article_feedback_summary') !== false,
    'knowledge base privacy-safe case count' => strpos($knowledge, 'private_support_relationship_count') !== false,
    'deactivation clears gap refresh' => strpos($main, 'GAP_REFRESH_HOOK') !== false,
    'intelligence stylesheet' => file_exists($plugin . '/assets/documentation-intelligence.css'),
    'backend intelligence endpoint' => strpos($backend, '/v1/documentation-intelligence/gaps/score') !== false,
    'backend support demand endpoint' => strpos($backend, '/v1/documentation-intelligence/support-demand/score') !== false,
    'backend intelligence module' => file_exists($root . '/backend/app/documentation_intelligence.py'),
    'manifest version' => is_array($manifest) && ($manifest['version'] ?? '') === '3.4.0',
);
$failed = array();
foreach ($checks as $label => $passed) {
    echo ($passed ? 'PASS' : 'FAIL') . " - {$label}\n";
    if (!$passed) $failed[] = $label;
}
if ($failed) { fwrite(STDERR, 'Failed checks: ' . implode(', ', $failed) . "\n"); exit(1); }
echo count($checks) . " checks passed.\n";
