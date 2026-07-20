<?php
$root = dirname(__DIR__);
$class = file_get_contents($root . '/wordpress/sustainable-catalyst-feature-suggestions/includes/class-scfs-help-desk-agent-workspace.php');
$css = file_get_contents($root . '/wordpress/sustainable-catalyst-feature-suggestions/assets/help-desk-agent-workspace.css');
$js = file_get_contents($root . '/wordpress/sustainable-catalyst-feature-suggestions/assets/help-desk-agent-workspace.js');
$checks = array(
 'Agent Workspace admin label' => strpos($class, "__('Agent Workspace'") !== false,
 'queue workspace renderer' => strpos($class, 'render_queue_workspace') !== false,
 'case workspace renderer' => strpos($class, 'render_case_workspace') !== false,
 'internal notes' => strpos($class, "value=\"internal_note\"") !== false,
 'requester-visible replies' => strpos($class, "value=\"support_reply\"") !== false,
 'saved views renderer' => strpos($class, 'render_saved_views') !== false,
 'workload panel' => strpos($class, 'render_workload_panel') !== false,
 'responsive CSS' => strpos($css, '@media (max-width: 782px)') !== false,
 'print CSS' => strpos($css, '@media print') !== false,
 'select-all JS' => strpos($js, 'data-scfs-select-all') !== false,
 'keyboard search shortcut' => strpos($js, "event.key !== '/'") !== false,
);
$failed = array_keys(array_filter($checks, static function ($ok) { return !$ok; }));
foreach ($checks as $label => $ok) echo ($ok ? 'PASS' : 'FAIL') . " - {$label}\n";
if ($failed) { fwrite(STDERR, 'Failed checks: ' . implode(', ', $failed) . "\n"); exit(1); }
echo 'v6.2.0 Agent Workspace interface contract passed (' . count($checks) . " checks).\n";
