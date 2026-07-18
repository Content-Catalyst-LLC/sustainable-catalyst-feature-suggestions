<?php
$root = dirname(__DIR__);
$base = $root . '/wordpress/sustainable-catalyst-feature-suggestions/content/knowledge-base';
$data = json_decode(file_get_contents($base . '/articles.json'), true);
$articles = is_array($data) ? ($data['articles'] ?? array()) : array();
$keys = array();
$products = array();
$samples = array();
$feature_count = 0;
$characters = 0;
$valid_html = true;
$valid_samples = true;
$valid_fields = true;
$sections = array(
    ':getting-started:' => 0,
    ':installation-configuration:' => 0,
    ':feature-guide:' => 0,
    ':sample-workflow:' => 0,
    ':troubleshooting:' => 0,
    ':technical-reference:' => 0,
);
foreach ($articles as $article) {
    foreach (array('content_key','title','slug','summary','product','product_slug','product_version','article_type','content','sample_csv','sample_json') as $field) {
        if (!isset($article[$field]) || trim((string) $article[$field]) === '') $valid_fields = false;
    }
    $key = (string) ($article['content_key'] ?? '');
    if ($key === '' || isset($keys[$key])) $valid_fields = false;
    $keys[$key] = true;
    $products[(string) ($article['product_slug'] ?? '')] = true;
    $content = (string) ($article['content'] ?? '');
    $characters += strlen($content);
    $feature_count += substr_count($content, 'class="sckb-demo"');
    if (strpos($content, '<article') === false || strpos($content, '<h2') === false || strpos($content, '{{sample_csv_url}}') === false || strpos($content, '{{sample_json_url}}') === false) $valid_html = false;
    foreach ($sections as $needle => $count) {
        if (strpos($key, $needle) !== false) $sections[$needle]++;
    }
    foreach (array('sample_csv','sample_json') as $sample_field) {
        $relative = ltrim((string) ($article[$sample_field] ?? ''), '/');
        $samples[$relative] = true;
        if ($relative === '' || !is_file($base . '/' . $relative)) $valid_samples = false;
    }
}
$checks = array(
    'corpus JSON parsed' => is_array($data),
    'corpus schema' => ($data['schema'] ?? '') === 'sckb-content-pack/1.0',
    '96 articles included' => count($articles) === 96,
    '16 products included' => count(array_filter(array_keys($products))) === 16,
    '32 unique sample files referenced' => count(array_filter(array_keys($samples))) === 32,
    'all sample files exist' => $valid_samples,
    'all required article fields exist' => $valid_fields,
    'all article content is structured HTML' => $valid_html,
    'substantial article corpus' => $characters > 900000,
    '283 feature demonstrations declared' => (int) ($data['feature_count'] ?? 0) === 283,
    'six getting started articles per product total' => $sections[':getting-started:'] === 16,
    'six setup articles per product total' => $sections[':installation-configuration:'] === 16,
    'six feature guide articles per product total' => $sections[':feature-guide:'] === 16,
    'six worked example articles per product total' => $sections[':sample-workflow:'] === 16,
    'six troubleshooting articles per product total' => $sections[':troubleshooting:'] === 16,
    'six reference articles per product total' => $sections[':technical-reference:'] === 16,
);
foreach ($checks as $label => $passed) {
    echo ($passed ? 'PASS' : 'FAIL') . " - {$label}\n";
    if (!$passed) exit(1);
}
echo count($checks) . " checks passed.\n";
