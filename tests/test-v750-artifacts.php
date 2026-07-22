<?php
$root = dirname(__DIR__);
$plugin = $root . '/wordpress/sustainable-catalyst-feature-suggestions';
$required = array(
    'RELEASE_NOTES_7.5.0.md',
    'SUSTAINABLE_CATALYST_PRODUCT_SUPPORT_AND_FEEDBACK_PLATFORM_V7.5.0_RELEASE_NOTES.md',
    'docs/release-console-v7.5.0.md',
    'docs/release-board-shortcode-v7.5.0.md',
    'docs/release-intelligence-v7.5.0.md',
    'examples/release-board-v7.5.0.json',
    'feature_suggestions_manifest-v7.5.0.json',
    'release-manifest-v7.5.0.json',
    'wordpress/sustainable-catalyst-feature-suggestions/includes/class-scfs-release-console-copy.php',
    'wordpress/sustainable-catalyst-feature-suggestions/assets/release-board-v7.5.0.css',
    'wordpress/sustainable-catalyst-feature-suggestions/assets/release-console-v7.5.0.js',
);
foreach ($required as $file) {
    if (!is_file($root . '/' . $file) || filesize($root . '/' . $file) < 1) {
        fwrite(STDERR, "FAIL artifact: {$file}\n"); exit(1);
    }
}
$current = json_decode(file_get_contents($root . '/feature_suggestions_manifest.json'), true);
$versioned = json_decode(file_get_contents($root . '/feature_suggestions_manifest-v7.5.0.json'), true);
$release = json_decode(file_get_contents($root . '/release-manifest-v7.5.0.json'), true);
$board_schema = json_decode(file_get_contents($root . '/schemas/scfs-release-board-v1.schema.json'), true);
$registry_schema = json_decode(file_get_contents($root . '/schemas/scfs-canonical-product-registry-v2.schema.json'), true);
if ($current !== $versioned) { fwrite(STDERR, "FAIL current manifest parity\n"); exit(1); }
if (($current['version'] ?? '') !== '7.5.0' || ($current['release_name'] ?? '') !== 'Release Intelligence and Console Copy Controls') { fwrite(STDERR, "FAIL manifest identity\n"); exit(1); }
$board = $current['release_board'] ?? array();
foreach (array('release_intelligence','previous_version_comparison','release_date_display','change_summaries','validation_indicators','documentation_indicators','known_issue_counts','recently_updated_indicator','maintenance_and_superseded_indicators','copy_controls','shortcode_copy_overrides') as $key) {
    if (empty($board[$key])) { fwrite(STDERR, "FAIL board capability: {$key}\n"); exit(1); }
}
if (($board['schema'] ?? '') !== 'scfs-release-board/1.3' || !empty($board['registry_facts_overridable_by_copy'])) { fwrite(STDERR, "FAIL board governance boundary\n"); exit(1); }
$registry = $current['canonical_product_registry'] ?? array();
foreach (array('release_intelligence_governed','previous_version_comparison','release_date_governed','change_summary_governed','validation_state_governed','documentation_state_governed','known_issue_count_governed') as $key) {
    if (empty($registry[$key])) { fwrite(STDERR, "FAIL registry intelligence: {$key}\n"); exit(1); }
}
$copy = $current['release_console_copy'] ?? array();
if (($copy['schema'] ?? '') !== 'scfs-release-console-copy/1.0' || ($copy['option_key'] ?? '') !== 'scfs_release_console_copy' || empty($copy['admin_screen']) || ($copy['filter'] ?? '') !== 'scfs_release_console_copy' || empty($copy['wordpress_settings']) || empty($copy['registry_authority_preserved']) || !array_key_exists('product_facts_editable_here', $copy) || $copy['product_facts_editable_here'] !== false) { fwrite(STDERR, "FAIL copy controls manifest\n"); exit(1); }
if (($release['version'] ?? '') !== '7.5.0' || ($release['release_name'] ?? '') !== 'Release Intelligence and Console Copy Controls') { fwrite(STDERR, "FAIL release manifest\n"); exit(1); }
if (($board_schema['properties']['version']['const'] ?? '') !== '7.5.0' || ($registry_schema['properties']['version']['const'] ?? '') !== '7.5.0') { fwrite(STDERR, "FAIL schema version\n"); exit(1); }
$copy_class = file_get_contents($plugin . '/includes/class-scfs-release-console-copy.php');
$board_class = file_get_contents($plugin . '/includes/class-scfs-release-board.php');
$registry_class = file_get_contents($plugin . '/includes/class-scfs-canonical-product-registry.php');
foreach (array("const OPTION_KEY = 'scfs_release_console_copy';", "apply_filters('scfs_release_console_copy'", "do_action('scfs_release_console_copy_updated'") as $needle) {
    if (strpos($copy_class, $needle) === false) { fwrite(STDERR, "FAIL copy source: {$needle}\n"); exit(1); }
}
foreach (array("'show_intelligence'", 'scfs-release-board__intelligence', 'scfs_release_console_product_intelligence', 'data-console-pause-label', 'data-console-play-label') as $needle) {
    if (strpos($board_class, $needle) === false) { fwrite(STDERR, "FAIL board source: {$needle}\n"); exit(1); }
}
foreach (array("'previous_version'", "'release_date'", "'change_summary'", "'validation_state'", "'documentation_state'", "'known_issue_count'", 'apply_v750_release_intelligence_migrations', 'is_recent_release') as $needle) {
    if (strpos($registry_class, $needle) === false) { fwrite(STDERR, "FAIL registry source: {$needle}\n"); exit(1); }
}
echo "v7.5.0 Release Intelligence and Console Copy Controls contract passed.\n";
