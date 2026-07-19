<?php
$root = dirname(__DIR__);
$class = file_get_contents($root . '/wordpress/sustainable-catalyst-feature-suggestions/includes/class-scfs-support-content-governance.php');
$checks = array(
    'managed support content types' => strpos($class, 'public function managed_post_types()') !== false,
    'content owner meta' => strpos($class, "const META_OWNER = '_scfs_content_owner_id';") !== false,
    'technical owner meta' => strpos($class, "const META_TECHNICAL_OWNER = '_scfs_content_technical_owner_id';") !== false,
    'verification history meta' => strpos($class, "const META_HISTORY = '_scfs_content_governance_history';") !== false,
    'review cadence meta' => strpos($class, "const META_CADENCE = '_scfs_content_review_cadence_days';") !== false,
    'supersession metadata' => strpos($class, 'META_SUPERSEDES') !== false && strpos($class, 'META_SUPERSEDED_BY') !== false,
    'governance assessment' => strpos($class, 'public function assess_record($post_id)') !== false,
    'integrity integration' => strpos($class, "'_scfs_integrity_score'") !== false,
    'editorial workflow integration' => strpos($class, 'SCFS_Editorial_Governance::instance()->state_for_post') !== false,
    'publication blocked queue' => strpos($class, "'publication_blocked'") !== false,
    'overdue queue' => strpos($class, "'overdue'") !== false,
    'ready verification queue' => strpos($class, "'ready_for_verification'") !== false,
    'human verification method' => strpos($class, 'public function verify_record(') !== false,
    'verification does not publish' => strpos($class, "'automatic_publication' => false") !== false,
);
$failed = array_keys(array_filter($checks, static function ($ok) { return !$ok; }));
foreach ($checks as $label => $ok) echo ($ok ? 'PASS' : 'FAIL') . " - {$label}\n";
if ($failed) { fwrite(STDERR, 'Failed checks: ' . implode(', ', $failed) . "\n"); exit(1); }
echo 'v5.5.0 governance engine contract passed (' . count($checks) . " checks).\n";
