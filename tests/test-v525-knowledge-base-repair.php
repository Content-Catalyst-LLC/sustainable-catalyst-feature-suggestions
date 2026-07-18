<?php
$root = dirname(__DIR__);
$plugin = $root . '/wordpress/sustainable-catalyst-feature-suggestions';
$integrated = file_get_contents($plugin . '/includes/class-scfs-integrated-knowledge-base.php');
$page = file_get_contents($plugin . '/content/knowledge-base/page.html');
$checks = array(
    'route contract bumped' => strpos($integrated, "const ROUTE_VERSION = '3.1.0';") !== false,
    'legacy route redirect registered' => strpos($integrated, "add_action('template_redirect', array(\$this, 'redirect_legacy_knowledge_base_routes'), 1)") !== false,
    'support page target helper' => strpos($integrated, 'private function support_page_url()') !== false,
    'permanent redirect' => strpos($integrated, "wp_safe_redirect(\$target, 301") !== false,
    'legacy filter entry point retained' => strpos($integrated, 'public function repair_dedicated_page_shortcode($content)') !== false,
    'legacy injector is a no-op' => strpos($integrated, "public function repair_dedicated_page_shortcode(\$content) {\n        return \$content;") !== false,
    'managed fallback persistence' => strpos($integrated, 'persist_legacy_route_fallback') !== false,
    'bundled fallback loader' => strpos($integrated, "content/knowledge-base/page.html") !== false,
    'fallback page has no browser shortcode' => strpos($page, '[scfs_support_knowledge_base]') === false,
    'fallback points to unified support' => strpos($page, '/support/?scfs_support_view=documentation#knowledge-base') !== false,
    'fallback structure bundled' => strpos($page, 'class="sc-kb-legacy-route"') !== false,
);
$failed = array_keys(array_filter($checks, static function ($ok) { return !$ok; }));
foreach ($checks as $label => $ok) echo ($ok ? 'PASS' : 'FAIL') . " - {$label}\n";
if ($failed) { fwrite(STDERR, 'Failed checks: ' . implode(', ', $failed) . "\n"); exit(1); }
echo 'v5.2.7 legacy Knowledge Base consolidation contract passed (' . count($checks) . " checks).\n";
