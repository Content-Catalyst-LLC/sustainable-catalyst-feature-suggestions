<?php
$root = dirname(__DIR__);
$files = array(
    'RELEASE_NOTES_7.2.1.md',
    'SUSTAINABLE_CATALYST_PRODUCT_SUPPORT_AND_FEEDBACK_PLATFORM_V7.2.1_RELEASE_NOTES.md',
    'docs/discovery-compatibility-v7.2.1.md',
    'schemas/scfs-installed-plugin-discovery-v1.schema.json',
    'schemas/scfs-plugin-discovery-diagnostics-v1.schema.json',
    'examples/installed-plugin-discovery-v7.2.1.json',
    'feature_suggestions_manifest-v7.2.1.json',
    'release-manifest-v7.2.1.json',
    'validate_v7_2_1.sh',
    'install_and_push_sustainable_catalyst_product_support_feedback_v7_2_1_macos.sh',
    'V7_2_1_TERMINAL_COMMANDS.txt',
    'GIT_COMMIT_V7_2_1.txt',
);
foreach ($files as $file) {
    if (!is_file($root . '/' . $file) || filesize($root . '/' . $file) < 1) { fwrite(STDERR, "FAIL artifact: {$file}\n"); exit(1); }
}
$current = json_decode(file_get_contents($root . '/feature_suggestions_manifest.json'), true);
$versioned = json_decode(file_get_contents($root . '/feature_suggestions_manifest-v7.2.1.json'), true);
$release = json_decode(file_get_contents($root . '/release-manifest-v7.2.1.json'), true);
if ($current !== $versioned || ($current['version'] ?? '') !== '7.2.1' || ($current['release_name'] ?? '') !== 'Discovery and Compatibility Patch') {
    fwrite(STDERR, "FAIL current/versioned manifest parity\n"); exit(1);
}
if (($release['version'] ?? '') !== '7.2.1' || ($release['release_name'] ?? '') !== 'Discovery and Compatibility Patch') {
    fwrite(STDERR, "FAIL release manifest identity\n"); exit(1);
}
echo "v7.2.1 release artifacts contract passed.\n";
