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
function sanitize_key($value) { return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $value)); }
function absint($value) { return abs((int) $value); }
function home_url($path = '') { return 'https://example.test' . $path; }
function get_option($key, $default = array()) { return $default; }
function wp_parse_args($args, $defaults = array()) { return array_merge($defaults, is_array($args) ? $args : array()); }

require dirname(__DIR__) . '/wordpress/sustainable-catalyst-feature-suggestions/sustainable-catalyst-feature-suggestions.php';
$platform = SCFS_Product_Support_Platform::instance();
$get = function ($method, ...$args) use ($platform) {
    $reflection = new ReflectionMethod($platform, $method);
    $reflection->setAccessible(true);
    return $reflection->invokeArgs($platform, $args);
};
$settings = $get('default_settings');
$embedded = $get('display_context', $settings, array('mode' => 'embedded'), array_merge(array(
    'mode' => 'embedded', 'compact' => '0', 'default_view' => '', 'show_header' => '', 'show_status' => '',
    'show_product_filter' => '1', 'show_navigation' => '1', 'show_nav_descriptions' => '',
    'show_overview_pathways' => '', 'hide_zero_status' => '', 'use_page_width' => '', 'nav_columns' => '',
), array()));
$custom_atts = array(
    'branding' => 'custom', 'accent' => '#123456', 'accent_contrast' => '#ffffff',
    'ink' => '#111111', 'muted' => '#555555', 'surface' => '#ffffff', 'soft' => '#f4f4f4',
    'line' => '#cccccc', 'success' => '#008000', 'warning' => '#996600', 'danger' => '#990000',
    'font_family' => 'Montserrat, Arial, sans-serif', 'heading_font_family' => 'Spartan, Arial, sans-serif',
    'radius' => '8', 'shadow' => 'none', 'max_width' => '1400',
);
$custom = $get('branding_context', $settings, $custom_atts, $custom_atts);
$style = $get('branding_style', $custom['tokens']);
$invalid_atts = $custom_atts;
$invalid_atts['accent'] = 'red; background:black';
$invalid = $get('branding_context', $settings, $invalid_atts, $invalid_atts);
$schema = $platform->schema_record();
$checks = array(
    'embedded mode selected' => $embedded['mode'] === 'embedded',
    'embedded default resolution' => $embedded['default_view'] === 'resolve',
    'embedded header suppressed' => $embedded['show_header'] === false,
    'embedded status suppressed' => $embedded['show_status'] === false,
    'embedded navigation compact' => $embedded['show_nav_descriptions'] === false,
    'embedded pathways suppressed' => $embedded['show_overview_pathways'] === false,
    'embedded page width enabled' => $embedded['use_page_width'] === true,
    'custom accent accepted' => ($custom['tokens']['accent'] ?? '') === '#123456',
    'custom radius bounded' => ($custom['tokens']['radius'] ?? '') === '8px',
    'custom width bounded' => ($custom['tokens']['max_width'] ?? '') === '1400px',
    'custom shadow disabled' => ($custom['tokens']['shadow'] ?? '') === 'none',
    'scoped accent variable emitted' => strpos($style, '--scfs-token-accent:#123456') !== false,
    'scoped heading variable emitted' => strpos($style, '--scfs-token-heading-font-family:Spartan, Arial, sans-serif') !== false,
    'invalid color rejected' => ($invalid['tokens']['accent'] ?? '') === '#6f1225',
    'schema exposes branding presets' => in_array('custom', $schema['branding_presets'] ?? array(), true),
    'schema exposes embedded mode' => in_array('embedded', $schema['rendering_modes'] ?? array(), true),
);
foreach ($checks as $label => $passed) {
    echo ($passed ? 'PASS' : 'FAIL') . " - {$label}\n";
    if (!$passed) exit(1);
}
echo count($checks) . " checks passed.\n";
