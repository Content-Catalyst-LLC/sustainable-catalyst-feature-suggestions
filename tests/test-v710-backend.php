<?php
$root = dirname(__DIR__);
$module = file_get_contents($root . '/backend/app/canonical_product_registry.py');
$main = file_get_contents($root . '/backend/app/main.py');
foreach (array('VERSION = "7.3.1"','SCHEMA = "scfs-canonical-product-registry/1.1"','ProductRegistryRecord','validate_product_registry','DEFAULT_PRODUCT_IDS') as $needle) {
    if (strpos($module, $needle) === false) { fwrite(STDERR, "FAIL backend module: {$needle}\n"); exit(1); }
}
foreach (array('/v1/product-registry/capabilities','/v1/product-registry/defaults','/v1/product-registry/validate','canonical_product_registry') as $needle) {
    if (strpos($main, $needle) === false) { fwrite(STDERR, "FAIL backend route: {$needle}\n"); exit(1); }
}
echo "v7.3.1 canonical product registry backend contract passed.\n";
