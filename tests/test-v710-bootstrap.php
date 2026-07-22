<?php
$root = dirname(__DIR__);
$main = file_get_contents($root . '/wordpress/sustainable-catalyst-feature-suggestions/sustainable-catalyst-feature-suggestions.php');
$class = file_get_contents($root . '/wordpress/sustainable-catalyst-feature-suggestions/includes/class-scfs-canonical-product-registry.php');
$checks = array(
    'plugin version' => strpos($main, 'Version: 7.3.3') !== false,
    'runtime version' => strpos($main, "const VERSION = '7.3.3';") !== false,
    'class loaded' => strpos($main, 'class-scfs-canonical-product-registry.php') !== false,
    'class initialized' => strpos($main, 'SCFS_Canonical_Product_Registry::instance();') !== false,
    'activation' => strpos($main, 'SCFS_Canonical_Product_Registry::activate();') !== false,
    'class version' => strpos($class, "const VERSION = '7.3.3';") !== false,
    'schema' => strpos($class, 'scfs-canonical-product-registry/1.1') !== false,
);
foreach ($checks as $label => $ok) {
    if (!$ok) { fwrite(STDERR, "FAIL: {$label}\n"); exit(1); }
}
echo "v7.3.3 canonical product registry bootstrap contract passed.\n";
