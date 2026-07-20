<?php
$root = dirname(__DIR__);
$class = file_get_contents($root . '/wordpress/sustainable-catalyst-feature-suggestions/includes/class-scfs-help-desk-service-levels.php');
$checks = array(
 'priority targets' => strpos($class, 'first_response_minutes') !== false && strpos($class, 'next_response_minutes') !== false && strpos($class, 'resolution_minutes') !== false,
 'four priorities' => strpos($class, "'critical' =>") !== false && strpos($class, "'high' =>") !== false && strpos($class, "'normal' =>") !== false && strpos($class, "'low' =>") !== false,
 'business hours' => strpos($class, 'weekly_hours_json') !== false && strpos($class, 'America/Chicago') !== false,
 'holiday support' => strpos($class, 'holidays_json') !== false,
 'business-time calculator' => strpos($class, 'add_business_minutes') !== false,
 'waiting requester pause' => strpos($class, "array('waiting_requester')") !== false,
);
$failed = array_keys(array_filter($checks, static function ($ok) { return !$ok; }));
foreach ($checks as $label => $ok) echo ($ok ? 'PASS' : 'FAIL') . " - {$label}\n";
if ($failed) { fwrite(STDERR, 'Failed checks: ' . implode(', ', $failed) . "\n"); exit(1); }
echo 'v6.4.0 policy and calendar contract passed (' . count($checks) . " checks).\n";
