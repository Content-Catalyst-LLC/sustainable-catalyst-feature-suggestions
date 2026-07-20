<?php
$root = dirname(__DIR__);
$class = file_get_contents($root . '/wordpress/sustainable-catalyst-feature-suggestions/includes/class-scfs-help-desk-case-foundation.php');
$css = file_get_contents($root . '/wordpress/sustainable-catalyst-feature-suggestions/assets/help-desk-case-foundation.css');
$checks = array(
 'admin page' => strpos($class, "const ADMIN_PAGE = 'scfs-help-desk-cases';") !== false,
 'view capability' => strpos($class, 'scfs_view_help_desk') !== false,
 'reply capability' => strpos($class, 'scfs_reply_help_desk') !== false,
 'manage capability' => strpos($class, 'scfs_manage_help_desk') !== false,
 'case list route' => strpos($class, "'/help-desk/cases'") !== false,
 'transition route' => strpos($class, "/transition'") !== false,
 'messages route' => strpos($class, "/messages'") !== false,
 'assignments route' => strpos($class, "/assignments'") !== false,
 'relationships route' => strpos($class, "/relationships'") !== false,
 'attachments route' => strpos($class, "/attachments'") !== false,
 'integrity route' => strpos($class, "'/help-desk/integrity'") !== false,
 'CLI create' => strpos($class, "scfs help-desk create") !== false,
 'responsive CSS' => strpos($css, '@media (max-width: 782px)') !== false,
 'print CSS' => strpos($css, '@media print') !== false,
);
$failed = array_keys(array_filter($checks, static function ($ok) { return !$ok; }));
foreach ($checks as $label => $ok) echo ($ok ? 'PASS' : 'FAIL') . " - {$label}\n";
if ($failed) { fwrite(STDERR, 'Failed checks: ' . implode(', ', $failed) . "\n"); exit(1); }
echo 'v6.2.0 admin, API, and CLI contract passed (' . count($checks) . " checks).\n";
