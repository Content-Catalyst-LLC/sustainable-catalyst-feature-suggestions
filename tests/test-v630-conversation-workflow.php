<?php
$root = dirname(__DIR__);
$class = file_get_contents($root . '/wordpress/sustainable-catalyst-feature-suggestions/includes/class-scfs-help-desk-customer-portal.php');
$workspace = file_get_contents($root . '/wordpress/sustainable-catalyst-feature-suggestions/includes/class-scfs-help-desk-agent-workspace.php');
$checks = array(
 'participant message query' => strpos($class, "visibility='participants'") !== false,
 'requester reply' => strpos($class, 'public function add_requester_reply(') !== false,
 'requester message type' => strpos($class, "'message_type' => 'requester_message'") !== false,
 'waiting requester reopen' => strpos($class, "if (\$case['status'] === 'waiting_requester')") !== false,
 'requester transition' => strpos($class, 'public function requester_transition(') !== false,
 'confirm resolution action' => strpos($class, "confirm_resolved") !== false,
 'bounded reopen' => strpos($class, 'can_reopen_case') !== false,
 'satisfaction feedback' => strpos($class, 'public function submit_satisfaction(') !== false,
 'feedback private event' => strpos($class, "'feedback_text_public' => false") !== false,
 'workspace extension hook' => strpos($workspace, "do_action('scfs_help_desk_case_workspace_after', \$case)") !== false,
 'agent portal panel' => strpos($class, 'render_agent_portal_panel') !== false,
 'notification queue' => strpos($class, 'queue_notification') !== false,
);
$failed = array_keys(array_filter($checks, static function ($ok) { return !$ok; }));
foreach ($checks as $label => $ok) echo ($ok ? 'PASS' : 'FAIL') . " - {$label}\n";
if ($failed) { fwrite(STDERR, 'Failed checks: ' . implode(', ', $failed) . "\n"); exit(1); }
echo 'v6.3.0 customer conversation workflow contract passed (' . count($checks) . " checks).\n";
