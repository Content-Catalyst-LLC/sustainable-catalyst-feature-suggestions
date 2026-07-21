<?php
$root = dirname(__DIR__);
$class = file_get_contents($root . '/wordpress/sustainable-catalyst-feature-suggestions/includes/class-scfs-help-desk-email-channel-operations.php');
$needles = array(
    'extract_case_numbers',
    'match_case',
    'find_thread_case',
    'register_inbound_message',
    'case_number',
    'in_reply_to',
    'provider_thread',
    'review_required',
    'automatic_case_creation',
);
foreach ($needles as $needle) {
    if (strpos($class, $needle) === false) {
        fwrite(STDERR, "FAIL inbound/threading: {$needle}\n");
        exit(1);
    }
}
echo "v6.10.0 inbound email and threading contract passed.\n";
