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
function wp_json_encode($value, $flags=0) { return json_encode($value, $flags); }
require dirname(__DIR__) . '/wordpress/sustainable-catalyst-feature-suggestions/sustainable-catalyst-feature-suggestions.php';
$ops = SCFS_Support_Content_Operations::instance();
$records = array(array('id'=>1,'type'=>'article','title'=>'A'));
$canonical = wp_json_encode($records, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
$payload = array('records'=>$records,'integrity'=>array('records_checksum'=>hash('sha256',$canonical)));
$tampered = $payload;
$tampered['records'][0]['title']='B';
$checks = array(
    'valid export checksum accepted' => $ops->validate_export_payload($payload) === true,
    'tampered export rejected' => $ops->validate_export_payload($tampered) === false,
    'missing integrity rejected' => $ops->validate_export_payload(array('records'=>$records)) === false,
);
foreach ($checks as $label => $passed) {
    echo ($passed ? 'PASS' : 'FAIL') . " - {$label}\n";
    if (!$passed) exit(1);
}
echo count($checks) . " checks passed.\n";
