<?php
$root = dirname(__DIR__);
$required = array(
 'RELEASE_NOTES_6.4.0.md',
 'SUSTAINABLE_CATALYST_PRODUCT_SUPPORT_AND_FEEDBACK_PLATFORM_V6.4.0_RELEASE_NOTES.md',
 'docs/help-desk-service-levels-v6.4.0.md',
 'release-manifest-v6.4.0.json',
 'feature_suggestions_manifest-v6.4.0.json',
 'schemas/scfs-help-desk-service-levels-v1.schema.json',
 'examples/help-desk-service-levels-v6.4.0.json',
 'wordpress/sustainable-catalyst-feature-suggestions/assets/help-desk-service-levels.css',
 'wordpress/sustainable-catalyst-feature-suggestions/assets/help-desk-service-levels.js',
 'wordpress/sustainable-catalyst-feature-suggestions/includes/class-scfs-help-desk-service-levels.php',
 'backend/app/help_desk_service_levels.py',
 'backend/tests/test_help_desk_service_levels.py',
 'validate_v6_4_0.sh',
 'install_and_push_v6_4_0_macos.sh',
);
$checks = array();
foreach ($required as $file) $checks[$file] = is_file($root . '/' . $file);
$failed = array_keys(array_filter($checks, static function ($ok) { return !$ok; }));
foreach ($checks as $label => $ok) echo ($ok ? 'PASS' : 'FAIL') . " - {$label}\n";
if ($failed) { fwrite(STDERR, 'Missing release artifacts: ' . implode(', ', $failed) . "\n"); exit(1); }
echo 'v6.4.0 release artifact contract passed (' . count($required) . " checks).\n";
