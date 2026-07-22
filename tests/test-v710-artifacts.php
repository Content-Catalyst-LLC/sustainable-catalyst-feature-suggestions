<?php
$root = dirname(__DIR__);
$files = array(
    'RELEASE_NOTES_7.1.0.md',
    'SUSTAINABLE_CATALYST_PRODUCT_SUPPORT_AND_FEEDBACK_PLATFORM_V7.1.0_RELEASE_NOTES.md',
    'docs/canonical-product-registry-v7.1.0.md',
    'schemas/scfs-canonical-product-registry-v1.schema.json',
    'examples/canonical-product-registry-v7.1.0.json',
    'feature_suggestions_manifest-v7.1.0.json',
    'release-manifest-v7.1.0.json',
    'validate_v7_1_0.sh',
    'install_and_push_sustainable_catalyst_product_support_feedback_v7_1_0_macos.sh',
    'V7_1_0_TERMINAL_COMMANDS.txt',
    'GIT_COMMIT_V7_1_0.txt',
);
foreach ($files as $file) {
    if (!is_file($root . '/' . $file) || filesize($root . '/' . $file) < 1) { fwrite(STDERR, "FAIL artifact: {$file}\n"); exit(1); }
}
$manifest = json_decode(file_get_contents($root . '/feature_suggestions_manifest-v7.1.0.json'), true);
if (($manifest['version'] ?? '') !== '7.1.0' || empty($manifest['canonical_product_registry']['human_review_required'])) {
    fwrite(STDERR, "FAIL v7.1.0 manifest\n"); exit(1);
}
echo "v7.1.0 release artifacts contract passed.\n";
