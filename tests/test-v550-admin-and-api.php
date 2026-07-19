<?php
$root = dirname(__DIR__);
$class = file_get_contents($root . '/wordpress/sustainable-catalyst-feature-suggestions/includes/class-scfs-support-content-governance.php');
$main = file_get_contents($root . '/backend/app/main.py');
$checks = array(
    'admin page registration' => strpos($class, "const ADMIN_PAGE = 'scfs-content-governance';") !== false,
    'content operations label' => strpos($class, "__('Content Operations'") !== false,
    'editor meta box' => strpos($class, "__('Content Ownership and Verification'") !== false,
    'bulk actions' => strpos($class, 'public function apply_bulk_action(') !== false,
    'queue CSV export' => strpos($class, 'public function export_action()') !== false,
    'daily governance scan' => strpos($class, "const CRON_HOOK = 'scfs_content_governance_daily';") !== false,
    'schema REST route' => strpos($class, "'/content-governance/schema'") !== false,
    'queue REST route' => strpos($class, "'/content-governance/queue'") !== false,
    'record REST route' => strpos($class, "'/content-governance/record/(?P<id>\\d+)'") !== false,
    'scan REST route' => strpos($class, "'/content-governance/scan'") !== false,
    'bulk REST route' => strpos($class, "'/content-governance/bulk'") !== false,
    'verify REST route' => strpos($class, "'/content-governance/verify/(?P<id>\\d+)'") !== false,
    'WP CLI scan' => strpos($class, "scfs content-governance scan") !== false,
    'WP CLI queue' => strpos($class, "scfs content-governance queue") !== false,
    'WP CLI verify' => strpos($class, "scfs content-governance verify") !== false,
    'FastAPI capabilities route' => strpos($main, "/v1/content-governance/capabilities") !== false,
    'FastAPI evaluate route' => strpos($main, "/v1/content-governance/evaluate") !== false,
    'FastAPI summary route' => strpos($main, "/v1/content-governance/queue/summarize") !== false,
    'FastAPI bulk plan route' => strpos($main, "/v1/content-governance/bulk/plan") !== false,
);
$failed = array_keys(array_filter($checks, static function ($ok) { return !$ok; }));
foreach ($checks as $label => $ok) echo ($ok ? 'PASS' : 'FAIL') . " - {$label}\n";
if ($failed) { fwrite(STDERR, 'Failed checks: ' . implode(', ', $failed) . "\n"); exit(1); }
echo 'v5.5.0 admin and API contract passed (' . count($checks) . " checks).\n";
