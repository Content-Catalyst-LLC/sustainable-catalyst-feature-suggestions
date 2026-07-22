<?php
$root = dirname(__DIR__);
$files = array(
    'RELEASE_NOTES_7.2.0.md',
    'SUSTAINABLE_CATALYST_PRODUCT_SUPPORT_AND_FEEDBACK_PLATFORM_V7.2.0_RELEASE_NOTES.md',
    'docs/installed-plugin-discovery-v7.2.0.md',
    'schemas/scfs-installed-plugin-discovery-v1.schema.json',
    'examples/installed-plugin-discovery-v7.2.0.json',
    'feature_suggestions_manifest-v7.2.0.json',
    'release-manifest-v7.2.0.json',
    'validate_v7_2_0.sh',
    'install_and_push_sustainable_catalyst_product_support_feedback_v7_2_0_macos.sh',
    'V7_2_0_TERMINAL_COMMANDS.txt',
    'GIT_COMMIT_V7_2_0.txt',
);
foreach ($files as $file) {
    if (!is_file($root . '/' . $file) || filesize($root . '/' . $file) < 1) { fwrite(STDERR, "FAIL artifact: {$file}
"); exit(1); }
}
$manifest = json_decode(file_get_contents($root . '/feature_suggestions_manifest-v7.2.0.json'), true);
if (($manifest['version'] ?? '') !== '7.2.0' || empty($manifest['installed_plugin_discovery']['approved_registry_only']) || !empty($manifest['installed_plugin_discovery']['unknown_plugins_auto_registered'])) {
    fwrite(STDERR, "FAIL v7.2.0 manifest
"); exit(1);
}
echo "v7.2.0 release artifacts contract passed.
";
