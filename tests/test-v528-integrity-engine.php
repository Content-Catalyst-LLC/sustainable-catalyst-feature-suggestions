<?php
$root = dirname(__DIR__);
$class = file_get_contents($root . '/wordpress/sustainable-catalyst-feature-suggestions/includes/class-scfs-support-article-integrity.php');
$checks = array(
    'article assessment method' => strpos($class, 'function assess_article') !== false,
    'bulk scan method' => strpos($class, 'function scan_all') !== false,
    'stored record method' => strpos($class, 'function stored_record') !== false,
    'title validation' => strpos($class, 'title_incomplete') !== false,
    'content length validation' => strpos($class, 'content_too_short') !== false,
    'summary validation' => strpos($class, 'summary_missing') !== false,
    'product validation' => strpos($class, 'product_missing') !== false,
    'version validation' => strpos($class, 'version_missing') !== false,
    'component validation' => strpos($class, 'component_missing') !== false,
    'article type validation' => strpos($class, 'article_type_missing') !== false,
    'verified version validation' => strpos($class, 'verified_version_unassigned') !== false,
    'heading hierarchy validation' => strpos($class, 'heading_level_jump') !== false,
    'required section validation' => strpos($class, 'required_section_missing') !== false,
    'placeholder validation' => strpos($class, 'template_placeholder') !== false,
    'link validation' => strpos($class, 'link_invalid') !== false,
    'image alt validation' => strpos($class, 'image_alt_missing') !== false,
    'figure caption validation' => strpos($class, 'figure_caption_missing') !== false,
    'table header validation' => strpos($class, 'table_headers_missing') !== false,
    'relationship validation' => strpos($class, 'relationship_context_empty') !== false,
    'freshness validation' => strpos($class, 'content_stale') !== false,
    'review due validation' => strpos($class, 'review_overdue') !== false,
    'readiness score' => strpos($class, "'score' => \$score") !== false,
    'readiness states' => strpos($class, "'ready'") !== false && strpos($class, "'blocked'") !== false,
    'no automatic content change' => strpos($class, "'automatic_content_changes' => false") !== false,
    'no automatic publication' => strpos($class, "'automatic_publication' => false") !== false,
);
$failed = array_keys(array_filter($checks, static function ($ok) { return !$ok; }));
foreach ($checks as $label => $ok) echo ($ok ? 'PASS' : 'FAIL') . " - {$label}\n";
if ($failed) { fwrite(STDERR, 'Failed checks: ' . implode(', ', $failed) . "\n"); exit(1); }
echo 'v5.2.8 Support Article integrity engine contract passed (' . count($checks) . " checks).\n";
