<?php
$root = dirname(__DIR__);
$module = file_get_contents($root . '/backend/app/release_board.py');
$tests = file_get_contents($root . '/backend/tests/test_release_board.py');
$checks = array(
    'backend version' => strpos($module, 'VERSION = "7.3.1"') !== false,
    'backend schema' => strpos($module, 'SCHEMA = "scfs-release-board/1.1"') !== false,
    'terminal layout' => strpos($module, 'Literal["terminal", "blackboard", "compact", "directory"]') !== false,
    'terminal default' => strpos($module, 'layout: Layout = "terminal"') !== false,
    'public title' => strpos($module, '"public_title": "Release Telemetry"') !== false,
    'plugin source count' => strpos($module, 'wordpress_plugin_count') !== false,
    'manual source count' => strpos($module, 'manual_count') !== false,
    'knowledge library declaration' => strpos($module, '"knowledge_library_homepage_required": True') !== false,
    'analytics public label' => strpos($module, '"analytics_r_public_label": "Analytics R"') !== false,
    'backend telemetry test' => strpos($tests, 'test_release_telemetry_is_the_default_terminal_surface') !== false,
    'backend source count test' => strpos($tests, 'test_source_counts_distinguish_plugin_and_manual_records') !== false,
);
foreach ($checks as $label => $ok) {
    if (!$ok) { fwrite(STDERR, "FAIL: {$label}\n"); exit(1); }
}
echo "v7.3.1 Release Telemetry backend contract passed.\n";
