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

require dirname(__DIR__) . '/wordpress/sustainable-catalyst-feature-suggestions/sustainable-catalyst-feature-suggestions.php';
$platform = SCFS_Product_Support_Platform::instance();
$schema = $platform->schema_record();
$handoff = $platform->handoff_schema();
$checks = array(
    'platform schema version' => ($schema['schema'] ?? '') === 'scfs-product-support-platform/1.0',
    'platform release version' => ($schema['version'] ?? '') === '5.0.0',
    'embedded rendering mode' => in_array('embedded', $schema['rendering_modes'] ?? array(), true),
    'client-side navigation' => ($schema['navigation']['client_side_view_switching'] ?? false) === true,
    'browser history navigation' => ($schema['navigation']['browser_history'] ?? false) === true,
    'anchored fallback navigation' => ($schema['navigation']['anchored_fallback'] ?? false) === true,
    'product context navigation' => ($schema['navigation']['product_context_preserved'] ?? false) === true,
    'custom branding preset' => in_array('custom', $schema['branding_presets'] ?? array(), true),
    'branding token contract' => in_array('accent', $schema['branding_tokens'] ?? array(), true) && in_array('heading_font_family', $schema['branding_tokens'] ?? array(), true),
    'guided resolution module' => in_array('guided_resolution', $schema['public_modules'] ?? array(), true),
    'knowledge base module' => in_array('support_knowledge_base', $schema['public_modules'] ?? array(), true),
    'known issue module' => in_array('known_issues', $schema['public_modules'] ?? array(), true),
    'release intelligence module' => in_array('release_intelligence', $schema['public_modules'] ?? array(), true),
    'feature suggestions module' => in_array('feature_suggestions', $schema['public_modules'] ?? array(), true),
    'voting module' => in_array('advisory_voting', $schema['public_modules'] ?? array(), true),
    'surveys module' => in_array('forms_and_surveys', $schema['public_modules'] ?? array(), true),
    'private integration' => in_array('contact_and_engagement', $schema['private_integrations'] ?? array(), true),
    'voting advisory' => ($schema['governance']['voting_is_advisory'] ?? false) === true,
    'human review required' => ($schema['governance']['human_review_required'] ?? false) === true,
    'handoff identity boundary' => ($handoff['privacy']['identity_collected_by_feature_suggestions'] ?? true) === false,
    'handoff case boundary' => ($handoff['privacy']['case_content_stored_by_feature_suggestions'] ?? true) === false,
    'handoff consent' => ($handoff['privacy']['consent_required'] ?? false) === true,
    'handoff destination' => ($handoff['destination'] ?? '') === 'contact_and_engagement',
);
foreach ($checks as $label => $passed) {
    echo ($passed ? 'PASS' : 'FAIL') . " - {$label}\n";
    if (!$passed) exit(1);
}
echo count($checks) . " checks passed.\n";
