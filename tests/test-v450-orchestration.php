<?php
define('ABSPATH', __DIR__ . '/');
function add_action() { return true; }
function add_filter() { return true; }
function add_shortcode() { return true; }
function register_activation_hook() { return true; }
function register_deactivation_hook() { return true; }
function plugin_dir_path($file) { return dirname($file) . '/'; }
function plugin_basename($file) { return basename($file); }
function apply_filters($hook, $value) { return $value; }
function __($text) { return $text; }
function wp_parse_args($args, $defaults = array()) { return array_merge($defaults, is_array($args) ? $args : array()); }
function absint($value) { return abs((int) $value); }
function sanitize_key($value) { return strtolower(preg_replace('/[^a-z0-9_\-]/', '', (string) $value)); }
function sanitize_title($value) { return trim(strtolower(preg_replace('/[^a-z0-9]+/i', '-', (string) $value)), '-'); }
function sanitize_text_field($value) { return trim(strip_tags((string) $value)); }
function get_option($key, $default = false) { return $default; }

require dirname(__DIR__) . '/wordpress/sustainable-catalyst-feature-suggestions/sustainable-catalyst-feature-suggestions.php';
$orchestration = SCFS_Cross_Product_Support_Orchestration::instance();
$graph = array(
    array('source'=>'research-librarian','relationship'=>'depends_on','target'=>'knowledge-library','component'=>'retrieval index','criticality'=>'high','active'=>true),
    array('source'=>'research-librarian','relationship'=>'routes_to','target'=>'workbench','component'=>'calculation handoff','criticality'=>'moderate','active'=>true),
    array('source'=>'site-intelligence','relationship'=>'provides_data_to','target'=>'research-librarian','component'=>'source registry','criticality'=>'moderate','active'=>true),
);
$impact = $orchestration->calculate_incident_impact(array('affected_products'=>4,'dependent_products'=>3,'criticality'=>'critical','blocked_releases'=>1,'shared_components'=>2));
$routes = $orchestration->recommend_routes('research-librarian', $graph, array('component'=>'calculation'));
$journey = $orchestration->journey_record('research-librarian', array('component'=>'retrieval','symptom'=>true));
$parsed = $orchestration->dependencies_from_text("research-librarian|depends_on|knowledge-library|retrieval index|high|1\nresearch-librarian|routes_to|workbench|calculation|moderate|1");
$checks = array(
    'critical incident impact' => $impact['state'] === 'critical' && $impact['score'] >= 80,
    'human review required' => $impact['human_review_required'] === true,
    'automatic incident declaration disabled' => $impact['automatic_incident_declaration'] === false,
    'automatic release blocking disabled' => $impact['automatic_release_blocking'] === false,
    'route recommendations generated' => count($routes) >= 2,
    'explicit support route ranks first' => ($routes[0]['product'] ?? '') === 'workbench',
    'journey includes platform status' => in_array('platform', array_column($journey['steps'], 'view'), true),
    'journey includes private support' => in_array('private-support', array_column($journey['steps'], 'view'), true),
    'journey stores no private case content' => $journey['private_case_content_stored'] === false,
    'dependency parser returns two records' => count($parsed) === 2,
    'dependency parser preserves criticality' => ($parsed[0]['criticality'] ?? '') === 'high',
);
foreach ($checks as $label => $passed) {
    echo ($passed ? 'PASS' : 'FAIL') . " - {$label}\n";
    if (!$passed) exit(1);
}
echo count($checks) . " checks passed.\n";
