<?php
$root = dirname(__DIR__);
$files = array(
 'RELEASE_NOTES_6.0.0.md',
 'SUSTAINABLE_CATALYST_PRODUCT_SUPPORT_AND_FEEDBACK_PLATFORM_V6.0.0_RELEASE_NOTES.md',
 'docs/connected-product-support-feedback-platform-v6.0.0.md',
 'examples/connected-product-support-platform-v6.0.0.json',
 'schemas/scfs-connected-product-support-feedback-platform-v1.schema.json',
 'release-manifest-v6.0.0.json',
 'feature_suggestions_manifest.json',
 'validate_v6_0_0.sh',
 'install_and_push_v6_0_0_macos.sh',
);
$checks = array();
foreach ($files as $file) $checks[$file] = is_file($root . '/' . $file);
$failed = array_keys(array_filter($checks, static function ($ok) { return !$ok; }));
foreach ($checks as $label => $ok) echo ($ok ? 'PASS' : 'FAIL') . " - {$label}\n";
if ($failed) { fwrite(STDERR, 'Missing release artifacts: ' . implode(', ', $failed) . "\n"); exit(1); }
echo 'v6.2.0 release artifact contract passed (' . count($checks) . " checks).\n";
