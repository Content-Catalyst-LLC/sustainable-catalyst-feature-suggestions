<?php
$root = dirname(__DIR__);
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
        fwrite(STDERR, "FAIL historical v7.5.0 artifact: {$file}\n"); exit(1);
    }
}
$manifest = json_decode(file_get_contents($root . '/feature_suggestions_manifest-v7.5.0.json'), true);
$release = json_decode(file_get_contents($root . '/release-manifest-v7.5.0.json'), true);
if (($manifest['version'] ?? '') !== '7.5.0' || ($manifest['release_name'] ?? '') !== 'Release Intelligence and Console Copy Controls') {
    fwrite(STDERR, "FAIL historical v7.5.0 manifest identity\n"); exit(1);
}
if (($release['version'] ?? '') !== '7.5.0' || ($release['release_name'] ?? '') !== 'Release Intelligence and Console Copy Controls') {
    fwrite(STDERR, "FAIL historical v7.5.0 release identity\n"); exit(1);
}
$board = $manifest['release_board'] ?? array();
foreach (array('release_intelligence','previous_version_comparison','release_date_display','change_summaries','validation_indicators','documentation_indicators','known_issue_counts','copy_controls') as $key) {
    if (empty($board[$key])) { fwrite(STDERR, "FAIL historical v7.5.0 capability: {$key}\n"); exit(1); }
}
echo "v7.5.0 historical Release Intelligence and Console Copy Controls artifacts preserved.\n";
