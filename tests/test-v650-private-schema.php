<?php
$root = dirname(__DIR__);
$class = file_get_contents($root . '/wordpress/sustainable-catalyst-feature-suggestions/includes/class-scfs-help-desk-secure-evidence.php');
$tables = array('scfs_help_desk_evidence_intakes','scfs_help_desk_diagnostic_bundles','scfs_help_desk_attachment_access','scfs_help_desk_attachment_events','scfs_help_desk_retention_actions');
$checks = array();
foreach ($tables as $table) $checks[$table] = strpos($class, $table) !== false;
$checks['append-only integrity hash'] = strpos($class, 'integrity_hash char(64)') !== false && strpos($class, 'event_hash(') !== false;
$checks['external attachment authority'] = strpos($class, "'attachment_authority' => 'contact-engagement'") !== false;
$checks['no media library storage'] = strpos($class, 'upload_bytes_accepted_by_this_plugin') !== false && strpos($class, 'bytes_stored_by_scfs') !== false;
$failed = array_keys(array_filter($checks, static function ($ok) { return !$ok; }));
foreach ($checks as $label => $ok) echo ($ok ? 'PASS' : 'FAIL') . " - {$label}\n";
if ($failed) { fwrite(STDERR, 'Failed checks: ' . implode(', ', $failed) . "\n"); exit(1); }
echo 'v6.7.0 private evidence schema contract passed.\n';
