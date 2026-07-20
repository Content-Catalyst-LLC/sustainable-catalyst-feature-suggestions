<?php
$root = dirname(__DIR__);
$class = file_get_contents($root . '/wordpress/sustainable-catalyst-feature-suggestions/includes/class-scfs-help-desk-agent-workspace.php');
$manifest = json_decode(file_get_contents($root . '/feature_suggestions_manifest.json'), true);
$workspace = $manifest['help_desk_agent_workspace'];
$checks = array(
 'public workspace API false' => $workspace['public_workspace_api'] === false,
 'public shortcode false' => $workspace['public_workspace_shortcode'] === false,
 'private content exposure false' => $workspace['private_case_content_exposed'] === false,
 'automatic assignment false' => $workspace['automatic_assignment'] === false,
 'automatic reassignment false' => $workspace['automatic_reassignment'] === false,
 'identity authority' => $workspace['identity_authority'] === 'contact-engagement',
 'attachment authority' => $workspace['attachment_authority'] === 'contact-engagement',
 'human review required' => $workspace['human_review_required'] === true,
 'schema record privacy' => strpos($class, "'public_workspace_api' => false") !== false,
 'saved view private body guard documented' => strpos($class, 'query_json') !== false,
);
$failed = array_keys(array_filter($checks, static function ($ok) { return !$ok; }));
foreach ($checks as $label => $ok) echo ($ok ? 'PASS' : 'FAIL') . " - {$label}\n";
if ($failed) { fwrite(STDERR, 'Failed checks: ' . implode(', ', $failed) . "\n"); exit(1); }
echo 'v6.2.0 Agent Workspace privacy and governance contract passed (' . count($checks) . " checks).\n";
