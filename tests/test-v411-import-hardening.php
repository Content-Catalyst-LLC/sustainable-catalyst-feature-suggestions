<?php
define('ABSPATH', __DIR__ . '/');
class WP_Error {
    private $code; private $message;
    public function __construct($code, $message) { $this->code=$code; $this->message=$message; }
    public function get_error_code() { return $this->code; }
    public function get_error_message() { return $this->message; }
}
function is_wp_error($value) { return $value instanceof WP_Error; }
function add_action() { return true; }
function add_filter() { return true; }
function add_shortcode() { return true; }
function register_activation_hook() { return true; }
function register_deactivation_hook() { return true; }
function plugin_dir_path($file) { return dirname($file) . '/'; }
function plugin_basename($file) { return basename($file); }
function apply_filters($hook, $value) { return $value; }
function __($text) { return $text; }
function sanitize_key($value) { return strtolower(preg_replace('/[^a-z0-9_\-]/', '', (string) $value)); }
function sanitize_text_field($value) { return trim(strip_tags((string) $value)); }
function wp_strip_all_tags($value) { return strip_tags((string) $value); }
function wp_trim_words($value, $count) { $words=preg_split('/\s+/', trim($value)); return implode(' ', array_slice($words,0,$count)); }
function wp_kses_post($value) { return (string) $value; }

require dirname(__DIR__) . '/wordpress/sustainable-catalyst-feature-suggestions/sustainable-catalyst-feature-suggestions.php';
$ops = SCFS_Support_Content_Operations::instance();
$tmp = tempnam(sys_get_temp_dir(), 'scfs-');
file_put_contents($tmp, '');
$empty = $ops->parse_import_file($tmp, 'empty.json', 'auto');
file_put_contents($tmp, '{"records": [}');
$invalid_json = $ops->parse_import_file($tmp, 'broken.json', 'auto');
file_put_contents($tmp, "# Bad\0Text");
$invalid_text = $ops->parse_import_file($tmp, 'bad.md', 'auto');
file_put_contents($tmp, "\xEF\xBB\xBF{\"records\":[{\"type\":\"article\",\"title\":\"Guide\",\"content\":\"Useful content\"}]}");
$valid = $ops->parse_import_file($tmp, 'support.json', 'auto');
unlink($tmp);
$invalid_type = $ops->validate_import_record(array('type'=>'private_case','title'=>'No','content'=>'No'), 0);
$missing = $ops->validate_import_record(array('type'=>'article','title'=>'','content'=>''), 1);
$good = $ops->validate_import_record(array('type'=>'article','title'=>'Workbench v4.4.0 Guide','content'=>'Useful'), 2);
$checks = array(
    'empty file rejected' => is_wp_error($empty) && $empty->get_error_code() === 'scfs_import_empty',
    'invalid JSON explained' => is_wp_error($invalid_json) && $invalid_json->get_error_code() === 'scfs_import_json',
    'null bytes rejected' => is_wp_error($invalid_text) && $invalid_text->get_error_code() === 'scfs_import_encoding',
    'BOM JSON accepted' => is_array($valid) && count($valid['records'] ?? array()) === 1,
    'source checksum returned' => is_array($valid) && preg_match('/^[a-f0-9]{64}$/', $valid['checksum'] ?? ''),
    'unsupported record type rejected' => is_wp_error($invalid_type) && $invalid_type->get_error_code() === 'scfs_invalid_import_type',
    'missing record content rejected' => is_wp_error($missing) && $missing->get_error_code() === 'scfs_import_missing_content',
    'valid record normalized' => is_array($good) && ($good['type'] ?? '') === 'article',
    'version-insensitive title normalization' => $ops->normalize_title('Workbench v4.4.0 Guide') === $ops->normalize_title('Workbench v4.4.0 Guide'),
);
foreach ($checks as $label => $passed) {
    echo ($passed ? 'PASS' : 'FAIL') . " - {$label}\n";
    if (!$passed) exit(1);
}
echo count($checks) . " checks passed.\n";
