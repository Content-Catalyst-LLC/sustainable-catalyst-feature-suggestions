<?php
$root = dirname(__DIR__);
$class = file_get_contents($root . '/wordpress/sustainable-catalyst-feature-suggestions/includes/class-scfs-help-desk-customer-portal.php');
$checks = array(
 'random secret' => strpos($class, 'random_bytes(32)') !== false,
 'SHA-256 hash' => strpos($class, "hash('sha256'") !== false,
 'raw token stored false' => strpos($class, "'raw_access_tokens_stored' => false") !== false,
 'raw session stored false' => strpos($class, "'raw_session_secrets_stored' => false") !== false,
 'token-to-session exchange' => strpos($class, 'bootstrap_session_from_link') !== false,
 'clean URL redirect' => strpos($class, 'wp_safe_redirect($this->portal_url())') !== false,
 'HttpOnly cookie' => strpos($class, "'httponly' => true") !== false,
 'SameSite Lax' => strpos($class, "'samesite' => 'Lax'") !== false,
 'no-store response' => strpos($class, 'private, no-store, no-cache') !== false,
 'no-referrer response' => strpos($class, 'Referrer-Policy: no-referrer') !== false,
 'noindex response' => strpos($class, 'X-Robots-Tag: noindex') !== false,
 'session revocation' => strpos($class, 'handle_portal_logout') !== false,
);
$failed = array_keys(array_filter($checks, static function ($ok) { return !$ok; }));
foreach ($checks as $label => $ok) echo ($ok ? 'PASS' : 'FAIL') . " - {$label}\n";
if ($failed) { fwrite(STDERR, 'Failed checks: ' . implode(', ', $failed) . "\n"); exit(1); }
echo 'v6.3.0 portal access and session security contract passed (' . count($checks) . " checks).\n";
