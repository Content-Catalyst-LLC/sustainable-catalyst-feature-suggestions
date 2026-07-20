<?php
$root = dirname(__DIR__);
$class = file_get_contents($root . '/wordpress/sustainable-catalyst-feature-suggestions/includes/class-scfs-help-desk-customer-portal.php');
$manifest = json_decode(file_get_contents($root . '/feature_suggestions_manifest.json'), true);
$portal = $manifest['help_desk_customer_portal'];
$notificationStart = strpos($class, '$this->queue_notification($case_id, \'portal_access_link\'');
$notificationEnd = strpos($class, 'return array(', $notificationStart);
$notificationBlock = substr($class, $notificationStart, $notificationEnd - $notificationStart);
$checks = array(
 'public case list false' => $portal['public_case_list_api'] === false,
 'case existence disclosure false' => $portal['case_existence_disclosure_without_session'] === false,
 'internal notes exposed false' => $portal['internal_notes_exposed'] === false,
 'private attachment bytes false' => $portal['private_attachment_bytes_exposed'] === false,
 'raw access tokens false' => $portal['raw_access_tokens_stored'] === false,
 'raw session secrets false' => $portal['raw_session_secrets_stored'] === false,
 'automatic case creation false' => $portal['automatic_case_creation'] === false,
 'automatic resolution false' => $portal['automatic_case_resolution'] === false,
 'automatic feedback publication false' => $portal['automatic_feedback_publication'] === false,
 'identity authority' => $portal['identity_authority'] === 'contact-engagement',
 'attachment authority' => $portal['attachment_authority'] === 'contact-engagement',
 'notification authority' => $portal['notification_authority'] === 'contact-engagement',
 'human review' => $portal['human_review_required'] === true,
 'schema record participant boundary' => strpos($class, "'participant_visible_messages_only' => true") !== false,
 'notification does not persist raw URL' => strpos($notificationBlock, "'portal_url'") === false,
 'one-time link transient' => strpos($class, 'stash_issued_link') !== false && strpos($class, 'consume_issued_link') !== false,
 'raw link absent from redirect query' => strpos($class, 'issued_portal_url') === false,
);
$failed = array_keys(array_filter($checks, static function ($ok) { return !$ok; }));
foreach ($checks as $label => $ok) echo ($ok ? 'PASS' : 'FAIL') . " - {$label}\n";
if ($failed) { fwrite(STDERR, 'Failed checks: ' . implode(', ', $failed) . "\n"); exit(1); }
echo 'v6.3.0 portal privacy and governance contract passed (' . count($checks) . " checks).\n";
