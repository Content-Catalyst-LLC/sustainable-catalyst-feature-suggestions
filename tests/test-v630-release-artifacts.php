<?php
$root = dirname(__DIR__);
$required = array(
 'RELEASE_NOTES_6.3.0.md',
 'SUSTAINABLE_CATALYST_PRODUCT_SUPPORT_AND_FEEDBACK_PLATFORM_V6.3.0_RELEASE_NOTES.md',
 'docs/help-desk-customer-portal-v6.3.0.md',
 'release-manifest-v6.3.0.json',
 'feature_suggestions_manifest-v6.3.0.json',
 'schemas/scfs-help-desk-customer-portal-v1.schema.json',
 'examples/help-desk-customer-portal-v6.3.0.json',
 'wordpress/sustainable-catalyst-feature-suggestions/assets/help-desk-customer-portal.css',
 'wordpress/sustainable-catalyst-feature-suggestions/assets/help-desk-customer-portal.js',
 'wordpress/sustainable-catalyst-feature-suggestions/includes/class-scfs-help-desk-customer-portal.php',
 'backend/app/help_desk_customer_portal.py',
 'backend/tests/test_help_desk_customer_portal.py',
 'validate_v6_3_0.sh',
 'install_and_push_v6_3_0_macos.sh',
);
$checks = array();
foreach ($required as $file) $checks[$file] = is_file($root . '/' . $file);
$failed = array_keys(array_filter($checks, static function ($ok) { return !$ok; }));
foreach ($checks as $label => $ok) echo ($ok ? 'PASS' : 'FAIL') . " - {$label}\n";
if ($failed) { fwrite(STDERR, 'Missing release artifacts: ' . implode(', ', $failed) . "\n"); exit(1); }
echo 'v6.3.0 release artifact contract passed (' . count($required) . " checks).\n";
