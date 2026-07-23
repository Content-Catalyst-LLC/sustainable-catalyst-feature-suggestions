<?php
$root = dirname(__DIR__);
$files = array(
    'RELEASE_NOTES_7.7.0.md',
    'SUSTAINABLE_CATALYST_PRODUCT_SUPPORT_AND_FEEDBACK_PLATFORM_V7.7.0_RELEASE_NOTES.md',
    'docs/release-board-shortcode-v7.7.0.md',
    'schemas/scfs-release-board-v1.schema.json',
    'examples/release-board-v7.7.0.json',
    'feature_suggestions_manifest-v7.7.0.json',
    'release-manifest-v7.7.0.json',
    'validate_v7_3_1.sh',
    'install_and_push_sustainable_catalyst_product_support_feedback_v7_3_1_macos.sh',
    'V7_3_1_TERMINAL_COMMANDS.txt',
    'GIT_COMMIT_V7_3_1.txt',
);
foreach ($files as $file) {
    if (!is_file($root . '/' . $file) || filesize($root . '/' . $file) < 1) { fwrite(STDERR, "FAIL artifact: {$file}\n"); exit(1); }
}
$current = json_decode(file_get_contents($root . '/feature_suggestions_manifest.json'), true);
$versioned = json_decode(file_get_contents($root . '/feature_suggestions_manifest-v7.7.0.json'), true);
$release = json_decode(file_get_contents($root . '/release-manifest-v7.7.0.json'), true);
if ($current !== $versioned || ($current['version'] ?? '') !== '7.7.0' || ($current['release_name'] ?? '') !== 'Canonical Product Registry Administration') {
    fwrite(STDERR, "FAIL current/versioned manifest parity\n"); exit(1);
}
if (($release['version'] ?? '') !== '7.7.0' || ($release['release_name'] ?? '') !== 'Canonical Product Registry Administration') {
    fwrite(STDERR, "FAIL release manifest identity\n"); exit(1);
}
if (($current['release_board']['shortcode'] ?? '') !== 'sc_release_board' || !empty($current['release_board']['private_plugin_paths_exposed'])) {
    fwrite(STDERR, "FAIL release board manifest\n"); exit(1);
}
echo "v7.7.0 release artifacts contract passed.\n";
