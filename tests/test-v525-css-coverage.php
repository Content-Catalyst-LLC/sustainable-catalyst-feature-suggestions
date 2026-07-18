<?php
$root = dirname(__DIR__);
$plugin = $root . '/wordpress/sustainable-catalyst-feature-suggestions';
$page = file_get_contents($plugin . '/content/knowledge-base/page.html');
$css = file_get_contents($plugin . '/assets/knowledge-base.css');
$classes = array();
preg_match_all('/class=(?:"([^"]+)"|\'([^\']+)\')/', $page, $matches, PREG_SET_ORDER);
foreach ($matches as $match) {
    $value = $match[1] !== '' ? $match[1] : $match[2];
    foreach (preg_split('/\s+/', trim($value)) as $class) if ($class !== '') $classes[$class] = true;
}
$renderer_classes = array(
    'scfs-kb', 'scfs-kb--library-browser', 'scfs-kb-library-masthead', 'scfs-kb-eyebrow',
    'scfs-kb-library-layout', 'scfs-kb-library-navigation', 'scfs-kb-library-nav-group',
    'scfs-kb-library-nav-list', 'scfs-kb-library-nav-list--compact', 'scfs-kb-library-results',
    'scfs-kb-library-results-header', 'scfs-kb-library-search', 'scfs-kb-library-search-main',
    'scfs-kb-library-filter-grid', 'scfs-kb-library-search-actions', 'scfs-kb-library-results-list',
    'scfs-kb-empty', 'scfs-support-library-article', 'scfs-support-library-article-main',
    'scfs-support-library-product', 'scfs-support-library-article-context',
    'scfs-support-library-article-meta', 'scfs-support-library-article-open',
    'scfs-kb-publication', 'scfs-kb-publication-breadcrumbs', 'scfs-kb-publication-header',
    'scfs-kb-publication-kicker', 'scfs-kb-publication-deck', 'scfs-kb-publication-meta',
    'scfs-kb-publication-tools', 'scfs-kb-publication-note', 'scfs-kb-publication-note-label',
    'scfs-kb-publication-body', 'scfs-kb-publication-intelligence',
    'scfs-kb-publication-intelligence-grid', 'scfs-kb-publication-related',
    'scfs-kb-publication-related-grid', 'scfs-kb-publication-navigation'
);
foreach ($renderer_classes as $class) $classes[$class] = true;
$missing = array();
foreach (array_keys($classes) as $class) {
    if (strpos($css, '.' . $class) === false) $missing[] = $class;
}
sort($missing);
if ($missing) {
    fwrite(STDERR, 'Missing CSS selectors: ' . implode(', ', $missing) . "\n");
    exit(1);
}
echo 'v5.2.5 Knowledge Base CSS coverage passed (' . count($classes) . " classes).\n";
