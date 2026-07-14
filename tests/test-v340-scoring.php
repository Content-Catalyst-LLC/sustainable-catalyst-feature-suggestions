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
function get_option($key, $default = array()) { return $default; }
function wp_parse_args($args, $defaults) { return array_merge($defaults, (array) $args); }
function absint($value) { return abs((int) $value); }

require dirname(__DIR__) . '/wordpress/sustainable-catalyst-feature-suggestions/sustainable-catalyst-feature-suggestions.php';
$intel = SCFS_Documentation_Feature_Intelligence::instance();
$high = $intel->calculate_gap_score(array('search_count'=>12,'no_match_count'=>9,'low_confidence_count'=>3,'negative_feedback_count'=>2,'case_count'=>2));
$low = $intel->calculate_gap_score(array('search_count'=>1,'no_match_count'=>0,'low_confidence_count'=>1,'negative_feedback_count'=>0,'case_count'=>0));
$checks = array(
    'high evidence score' => $high >= 60,
    'score bounded' => $high <= 100,
    'low evidence lower' => $low < $high,
    'no evidence zero' => $intel->calculate_gap_score(array()) === 0.0,
);
foreach ($checks as $label => $passed) {
    echo ($passed ? 'PASS' : 'FAIL') . " - {$label}\n";
    if (!$passed) exit(1);
}
echo count($checks) . " checks passed.\n";
