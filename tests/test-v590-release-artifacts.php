<?php
$root = dirname(__DIR__);
$files = array(
 'RELEASE_NOTES_5.9.0.md',
 'SUSTAINABLE_CATALYST_PRODUCT_SUPPORT_AND_FEEDBACK_PLATFORM_V5.9.0_RELEASE_NOTES.md',
 'docs/public-api-embeds-institutional-support-v5.9.0.md',
 'examples/public-support-integration-v5.9.0.json',
 'schemas/scfs-public-support-integration-v1.schema.json',
 'release-manifest-v5.9.0.json',
 'feature_suggestions_manifest.json',
 'validate_v5_9_0.sh',
 'install_and_push_v5_9_0_macos.sh',
);
$checks = array();
foreach ($files as $file) $checks[$file] = is_file($root . '/' . $file);
$failed = array_keys(array_filter($checks, static function ($ok) { return !$ok; }));
foreach ($checks as $label => $ok) echo ($ok ? 'PASS' : 'FAIL') . " - {$label}\n";
if ($failed) { fwrite(STDERR, 'Missing release artifacts: ' . implode(', ', $failed) . "\n"); exit(1); }
echo 'v5.9.0 release artifact contract passed (' . count($checks) . " checks).\n";
