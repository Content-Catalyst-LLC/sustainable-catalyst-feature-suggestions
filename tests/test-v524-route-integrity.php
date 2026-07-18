<?php
$root = dirname(__DIR__);
$main = file_get_contents($root . '/wordpress/sustainable-catalyst-feature-suggestions/sustainable-catalyst-feature-suggestions.php');
$kb = file_get_contents($root . '/wordpress/sustainable-catalyst-feature-suggestions/includes/class-scfs-knowledge-base.php');
$integrated = file_get_contents($root . '/wordpress/sustainable-catalyst-feature-suggestions/includes/class-scfs-integrated-knowledge-base.php');
$manifest = json_decode(file_get_contents($root . '/feature_suggestions_manifest.json'), true);
$checks = array(
 'plugin version' => strpos($main, 'Version: 5.2.5') !== false,
 'article rewrite no longer captures support children' => strpos($kb, "'rewrite' => array('slug' => 'support/guides'") !== false,
 'archive uses nonconflicting route' => strpos($kb, "'has_archive' => 'support-documentation'") !== false,
 'dedicated page provisioner' => strpos($integrated, 'ensure_dedicated_knowledge_base_page') !== false,
 'upgrade rewrite repair' => strpos($integrated, 'maybe_repair_dedicated_page_route') !== false,
 'nested page path' => strpos($integrated, "get_page_by_path('support/knowledge-base'") !== false,
 'stored page permalink' => strpos($integrated, 'DEDICATED_PAGE_OPTION') !== false,
 'manifest version' => ($manifest['version'] ?? '') === '5.2.5',
 'manifest release name' => ($manifest['release_name'] ?? '') === 'Product Support and Feedback Platform Rebrand, Knowledge Base Rendering Repair, Library Browser Redesign, and Publication-Parity Support Articles',
 'release notes' => file_exists($root . '/RELEASE_NOTES_5.2.5.md'),
);
$failed=[]; foreach($checks as $label=>$ok){ echo ($ok?'PASS':'FAIL')." - $label\n"; if(!$ok)$failed[]=$label; }
if($failed){fwrite(STDERR,'Failed checks: '.implode(', ',$failed)."\n");exit(1);} echo count($checks)." checks passed.\n";
