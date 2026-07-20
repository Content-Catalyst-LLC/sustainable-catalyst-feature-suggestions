<?php
$root = dirname(__DIR__);
$files = array(
 'RELEASE_NOTES_6.1.0.md',
 'SUSTAINABLE_CATALYST_PRODUCT_SUPPORT_AND_FEEDBACK_PLATFORM_V6.1.0_RELEASE_NOTES.md',
 'docs/help-desk-case-foundation-v6.1.0.md',
 'examples/help-desk-case-foundation-v6.1.0.json',
 'schemas/scfs-help-desk-case-v1.schema.json',
 'release-manifest-v6.1.0.json',
 'feature_suggestions_manifest.json',
 'feature_suggestions_manifest-v6.1.0.json',
 'validate_v6_1_0.sh',
 'install_and_push_v6_1_0_macos.sh',
);
$checks = array();
foreach ($files as $file) $checks[$file] = is_file($root . '/' . $file);
$failed = array_keys(array_filter($checks, static function ($ok) { return !$ok; }));
foreach ($checks as $label => $ok) echo ($ok ? 'PASS' : 'FAIL') . " - {$label}\n";
if ($failed) { fwrite(STDERR, 'Missing release artifacts: ' . implode(', ', $failed) . "\n"); exit(1); }
echo 'v6.1.0 release artifact contract passed (' . count($checks) . " checks).\n";
