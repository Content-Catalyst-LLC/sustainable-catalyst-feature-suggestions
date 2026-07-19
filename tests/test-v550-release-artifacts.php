<?php
$root = dirname(__DIR__);
$required = array(
    'RELEASE_NOTES_5.5.0.md',
    'SUSTAINABLE_CATALYST_PRODUCT_SUPPORT_AND_FEEDBACK_PLATFORM_V5.5.0_RELEASE_NOTES.md',
    'release-manifest-v5.5.0.json',
    'feature_suggestions_manifest.json',
    'docs/support-content-operations-editorial-governance-v5.5.0.md',
    'schemas/scfs-support-content-governance-v1.schema.json',
    'examples/support-content-governance-v5.5.0.json',
    'backend/app/content_governance.py',
    'backend/tests/test_content_governance.py',
    'wordpress/sustainable-catalyst-feature-suggestions/assets/support-content-governance.css',
    'wordpress/sustainable-catalyst-feature-suggestions/assets/support-content-governance.js',
);
$failed = array();
foreach ($required as $file) {
    $ok = is_file($root . '/' . $file) && filesize($root . '/' . $file) > 0;
    echo ($ok ? 'PASS' : 'FAIL') . " - {$file}\n";
    if (!$ok) $failed[] = $file;
}
$manifest = json_decode(file_get_contents($root . '/release-manifest-v5.5.0.json'), true);
$schema = json_decode(file_get_contents($root . '/schemas/scfs-support-content-governance-v1.schema.json'), true);
$example = json_decode(file_get_contents($root . '/examples/support-content-governance-v5.5.0.json'), true);
$checks = array(
    'release manifest version' => ($manifest['version'] ?? '') === '5.5.0',
    'release manifest name' => ($manifest['release_name'] ?? '') === 'Support Content Operations and Editorial Governance',
    'governance schema identity' => ($manifest['support_content_governance']['schema'] ?? '') === 'scfs-support-content-governance/1.0',
    'no database migration' => ($manifest['compatibility']['database_migration_required'] ?? null) === false,
    'schema version contract' => ($schema['properties']['version']['const'] ?? '') === '5.5.0',
    'example schema contract' => ($example['schema'] ?? '') === 'scfs-support-content-governance/1.0',
    'human review boundary' => ($example['human_review_required'] ?? false) === true,
    'automatic publication boundary' => ($example['automatic_publication'] ?? true) === false,
);
foreach ($checks as $label => $ok) {
    echo ($ok ? 'PASS' : 'FAIL') . " - {$label}\n";
    if (!$ok) $failed[] = $label;
}
if ($failed) { fwrite(STDERR, 'Failed checks: ' . implode(', ', $failed) . "\n"); exit(1); }
echo 'v5.5.0 release artifact contract passed (' . (count($required) + count($checks)) . " checks).\n";
