<?php
$root = dirname(__DIR__);
$class = file_get_contents($root . '/wordpress/sustainable-catalyst-feature-suggestions/includes/class-scfs-help-desk-case-foundation.php');
$main = file_get_contents($root . '/wordpress/sustainable-catalyst-feature-suggestions/sustainable-catalyst-feature-suggestions.php');
$checks = array(
 'no public case API' => strpos($class, "'public_case_api' => false") !== false,
 'no public shortcode' => strpos($class, "'public_case_shortcode' => false") !== false,
 'no private content exposure' => strpos($class, "'private_case_content_exposed' => false") !== false,
 'identity authority' => strpos($class, "'requester_identity_authority' => 'contact-engagement'") !== false,
 'attachment authority' => strpos($class, "'attachment_authority' => 'contact-engagement'") !== false,
 'no Media Library storage' => strpos($class, "'uploaded_files_stored_in_media_library' => false") !== false,
 'no automatic case creation' => strpos($class, "'automatic_case_creation' => false") !== false,
 'no automatic resolution' => strpos($class, "'automatic_case_resolution' => false") !== false,
 'legacy REST namespace' => strpos($main, "const REST_NAMESPACE = 'scfs/v1';") !== false,
 'legacy suggestion CPT' => strpos($main, "const POST_TYPE = 'sc_feature_suggest';") !== false,
);
$failed = array_keys(array_filter($checks, static function ($ok) { return !$ok; }));
foreach ($checks as $label => $ok) echo ($ok ? 'PASS' : 'FAIL') . " - {$label}\n";
if ($failed) { fwrite(STDERR, 'Failed checks: ' . implode(', ', $failed) . "\n"); exit(1); }
echo 'v6.3.0 privacy boundary contract passed (' . count($checks) . " checks).\n";
