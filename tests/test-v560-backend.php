<?php
$root = dirname(__DIR__);
$main = file_get_contents($root . '/backend/app/main.py');
$backend = file_get_contents($root . '/backend/app/feedback_product_signals.py');
$checks = array(
    'FastAPI capabilities route' => strpos($main, '/v1/feedback-product-signals/capabilities') !== false,
    'FastAPI score route' => strpos($main, '/v1/feedback-product-signals/score') !== false,
    'FastAPI portfolio route' => strpos($main, '/v1/feedback-product-signals/portfolio') !== false,
    'FastAPI cluster route' => strpos($main, '/v1/feedback-product-signals/clusters/prioritize') !== false,
    'product scoring function' => strpos($backend, 'def score_product_signal') !== false,
    'portfolio function' => strpos($backend, 'def summarize_product_signal_portfolio') !== false,
    'cluster function' => strpos($backend, 'def prioritize_product_signal_cluster') !== false,
    'backend schema identity' => strpos($backend, 'scfs-feedback-product-signals/1.0') !== false,
    'WordPress source of truth' => strpos($main, "'wordpress_source_of_truth': True") !== false,
);
$failed = array_keys(array_filter($checks, static function ($ok) { return !$ok; }));
foreach ($checks as $label => $ok) echo ($ok ? 'PASS' : 'FAIL') . " - {$label}\n";
if ($failed) { fwrite(STDERR, 'Failed checks: ' . implode(', ', $failed) . "\n"); exit(1); }
echo 'v5.6.0 Product Signals FastAPI contract passed (' . count($checks) . " checks).\n";
