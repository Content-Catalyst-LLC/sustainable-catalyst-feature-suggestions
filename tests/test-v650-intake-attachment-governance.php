<?php
$root = dirname(__DIR__);
$class = file_get_contents($root . '/wordpress/sustainable-catalyst-feature-suggestions/includes/class-scfs-help-desk-secure-evidence.php');
$checks = array(
 'evidence intake' => strpos($class, 'function create_intake') !== false,
 'attachment metadata validation' => strpos($class, 'function validate_attachment_payload') !== false,
 'blocked extensions' => strpos($class, 'blocked_filename_extensions') !== false,
 'sha256 required' => strpos($class, 'scfs_attachment_sha256_required') !== false,
 'consent required' => strpos($class, 'scfs_evidence_consent_required') !== false,
 'malware scan state' => strpos($class, "'scan_state'") !== false,
 'diagnostic bundle' => strpos($class, 'function register_diagnostic_bundle') !== false,
 'secret fields blocked' => strpos($class, 'scfs_diagnostic_secret_field_blocked') !== false,
 'hash-only access grant' => strpos($class, "'grant_hash' => hash('sha256', \$secret)") !== false,
 'access grant consumption' => strpos($class, 'function consume_access_grant') !== false && strpos($class, 'scfs_help_desk_evidence_resolve_download') !== false,
 'no raw download URL storage' => substr_count($class, 'raw_download_url_stored') >= 1,
);
$failed = array_keys(array_filter($checks, static function ($ok) { return !$ok; }));
foreach ($checks as $label => $ok) echo ($ok ? 'PASS' : 'FAIL') . " - {$label}\n";
if ($failed) { fwrite(STDERR, 'Failed checks: ' . implode(', ', $failed) . "\n"); exit(1); }
echo 'v6.11.0 intake and attachment governance contract passed.\n';
