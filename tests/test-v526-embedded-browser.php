<?php
$root = dirname(__DIR__);
$plugin = $root . '/wordpress/sustainable-catalyst-feature-suggestions';
$class = file_get_contents($plugin . '/includes/class-scfs-integrated-knowledge-base.php');
$kb = file_get_contents($plugin . '/includes/class-scfs-knowledge-base.php');
$checks = array(
    'knowledge base version' => strpos($kb, "const VERSION = '5.2.6';") !== false,
    'integrated version' => strpos($class, "const VERSION = '5.2.6';") !== false,
    'two-panel browser retained' => strpos($class, 'scfs-kb-library-layout') !== false && strpos($class, 'scfs-kb-library-navigation') !== false,
    'browser results use embeddable region' => strpos($class, '<div class="scfs-kb-library-results" role="region"') !== false,
    'browser no nested main landmark' => strpos($class, "echo '<main class=\"scfs-kb-library-results\">';") === false,
    'support URL helper' => strpos($class, 'private function support_page_url()') !== false,
    'legacy dedicated URL maps to support view' => preg_match("/private function dedicated_knowledge_base_url.*scfs_support_view.*documentation.*#knowledge-base/s", $class) === 1,
    'article breadcrumbs use consolidated support URL' => strpos($class, '$kb_url = $this->support_knowledge_base_url();') !== false,
    'legacy shortcodes retained' => strpos($kb, "const SHORTCODE = 'scfs_support_knowledge_base';") !== false && strpos($kb, "const LEGACY_SHORTCODE = 'sustainable_catalyst_support_knowledge_base';") !== false,
);
$failed = array_keys(array_filter($checks, static function ($ok) { return !$ok; }));
foreach ($checks as $label => $ok) echo ($ok ? 'PASS' : 'FAIL') . " - {$label}\n";
if ($failed) { fwrite(STDERR, 'Failed checks: ' . implode(', ', $failed) . "\n"); exit(1); }
echo 'v5.2.6 embedded Knowledge Base browser contract passed (' . count($checks) . " checks).\n";
