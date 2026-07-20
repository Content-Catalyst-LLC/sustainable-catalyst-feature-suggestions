<?php
$root = dirname(__DIR__);
$required = array(
 'RELEASE_NOTES_6.2.0.md',
 'SUSTAINABLE_CATALYST_PRODUCT_SUPPORT_AND_FEEDBACK_PLATFORM_V6.2.0_RELEASE_NOTES.md',
 'docs/help-desk-agent-workspace-v6.2.0.md',
 'release-manifest-v6.2.0.json',
 'feature_suggestions_manifest-v6.2.0.json',
 'schemas/scfs-help-desk-agent-workspace-v1.schema.json',
 'examples/help-desk-agent-workspace-v6.2.0.json',
 'validate_v6_2_0.sh',
 'install_and_push_v6_2_0_macos.sh',
 'V6_2_0_TERMINAL_COMMANDS.txt',
);
$failed = array();
foreach ($required as $path) {
 $ok = is_file($root . '/' . $path);
 echo ($ok ? 'PASS' : 'FAIL') . " - {$path}\n";
 if (!$ok) $failed[] = $path;
}
if ($failed) { fwrite(STDERR, 'Missing artifacts: ' . implode(', ', $failed) . "\n"); exit(1); }
echo 'v6.2.0 release artifact contract passed (' . count($required) . " checks).\n";
