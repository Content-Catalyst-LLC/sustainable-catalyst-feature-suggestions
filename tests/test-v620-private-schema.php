<?php
$root = dirname(__DIR__);
$class = file_get_contents($root . '/wordpress/sustainable-catalyst-feature-suggestions/includes/class-scfs-help-desk-agent-workspace.php');
$checks = array(
 'saved views table' => strpos($class, "'saved_views' => \$wpdb->prefix . 'scfs_help_desk_saved_views'") !== false,
 'teams table' => strpos($class, "'teams' => \$wpdb->prefix . 'scfs_help_desk_teams'") !== false,
 'team members table' => strpos($class, "'team_members' => \$wpdb->prefix . 'scfs_help_desk_team_members'") !== false,
 'dbDelta saved views' => strpos($class, 'dbDelta($saved_views);') !== false,
 'dbDelta teams' => strpos($class, 'dbDelta($teams);') !== false,
 'dbDelta members' => strpos($class, 'dbDelta($members);') !== false,
 'additive DB version' => strpos($class, "const DB_VERSION = '1.1.0';") !== false,
 'case foundation dependency' => strpos($class, "SCFS_Help_Desk_Case_Foundation::instance()") !== false,
);
$failed = array_keys(array_filter($checks, static function ($ok) { return !$ok; }));
foreach ($checks as $label => $ok) echo ($ok ? 'PASS' : 'FAIL') . " - {$label}\n";
if ($failed) { fwrite(STDERR, 'Failed checks: ' . implode(', ', $failed) . "\n"); exit(1); }
echo 'v6.3.0 private workspace schema contract passed (' . count($checks) . " checks).\n";
