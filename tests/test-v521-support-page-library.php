<?php
$root = dirname(__DIR__);
$class = file_get_contents($root . '/wordpress/sustainable-catalyst-feature-suggestions/includes/class-scfs-integrated-knowledge-base.php');
$css = file_get_contents($root . '/wordpress/sustainable-catalyst-feature-suggestions/assets/knowledge-base.css');
$js = file_get_contents($root . '/wordpress/sustainable-catalyst-feature-suggestions/assets/integrated-knowledge-base.js');
$main = file_get_contents($root . '/wordpress/sustainable-catalyst-feature-suggestions/sustainable-catalyst-feature-suggestions.php');
$manifest = json_decode(file_get_contents($root . '/feature_suggestions_manifest.json'), true);
$checks = array(
  'plugin version' => strpos($main, 'Version: 5.2.1') !== false,
  'runtime version' => strpos($main, "const VERSION = '5.2.1';") !== false,
  'automatic support rendering hook' => strpos($class, 'automatically_render_on_support_page') !== false,
  'support slug detection' => strpos($class, "'support-center'") !== false,
  'duplicate shortcode guard' => strpos($class, 'has_shortcode') !== false,
  'product view is default' => strpos($js, "var stored = 'products';") !== false,
  'library interface CSS' => strpos($css, 'v5.2.1 Support page library interface') !== false,
  'manifest version' => ($manifest['version'] ?? '') === '5.2.1',
  'manifest release name' => ($manifest['release_name'] ?? '') === 'Support Page Library Interface and Automatic Knowledge Base Rendering',
  'release notes' => file_exists($root . '/RELEASE_NOTES_5.2.1.md'),
);
foreach ($checks as $label => $pass) {
  if (!$pass) { fwrite(STDERR, "FAIL - $label\n"); exit(1); }
  echo "PASS - $label\n";
}
echo 'v5.2.1 support page library contract passed (' . count($checks) . " checks).\n";
