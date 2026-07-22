<?php
$root = dirname(__DIR__);
$files = array(
    'RELEASE_NOTES_7.0.1.md',
    'SUSTAINABLE_CATALYST_PRODUCT_SUPPORT_AND_FEEDBACK_PLATFORM_V7.0.1_RELEASE_NOTES.md',
    'docs/repository-identity-migration-v7.0.1.md',
    'feature_suggestions_manifest-v7.0.1.json',
    'release-manifest-v7.0.1.json',
    'validate_v7_0_1.sh',
    'install_and_push_sustainable_catalyst_product_support_feedback_v7_0_1_macos.sh',
    'V7_0_1_TERMINAL_COMMANDS.txt',
    'GIT_COMMIT_V7_0_1.txt',
);
foreach ($files as $file) {
    if (!is_file($root . '/' . $file) || filesize($root . '/' . $file) < 1) {
        fwrite(STDERR, "FAIL artifact: {$file}\n");
        exit(1);
    }
}
echo "v7.0.1 release artifacts contract passed.\n";
