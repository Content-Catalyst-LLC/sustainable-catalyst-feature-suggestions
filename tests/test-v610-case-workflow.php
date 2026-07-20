<?php
$root = dirname(__DIR__);
$class = file_get_contents($root . '/wordpress/sustainable-catalyst-feature-suggestions/includes/class-scfs-help-desk-case-foundation.php');
$checks = array(
 'case creation' => strpos($class, 'public function create_case(') !== false,
 'status transition' => strpos($class, 'public function transition_case(') !== false,
 'participants' => strpos($class, 'public function add_participant(') !== false,
 'messages' => strpos($class, 'public function add_message(') !== false,
 'internal notes' => strpos($class, "'internal_note'") !== false,
 'assignments' => strpos($class, 'public function assign_case(') !== false,
 'relationships' => strpos($class, 'public function add_relationship(') !== false,
 'attachment metadata' => strpos($class, 'public function register_attachment_metadata(') !== false,
 'SLA events' => strpos($class, 'public function add_sla_event(') !== false,
 'audit events' => strpos($class, 'public function add_event(') !== false,
 'timeline' => strpos($class, 'public function case_timeline(') !== false,
);
$failed = array_keys(array_filter($checks, static function ($ok) { return !$ok; }));
foreach ($checks as $label => $ok) echo ($ok ? 'PASS' : 'FAIL') . " - {$label}\n";
if ($failed) { fwrite(STDERR, 'Failed checks: ' . implode(', ', $failed) . "\n"); exit(1); }
echo 'v6.3.0 case workflow contract passed (' . count($checks) . " checks).\n";
