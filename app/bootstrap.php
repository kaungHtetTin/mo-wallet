<?php

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Cache-Control: no-store');
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data:; form-action 'self'; frame-ancestors 'self'; base-uri 'self'");

if (!empty($_SESSION['user_id'])) {
    $owner = \Mini\Database::fetch('SELECT id, name, email, session_version FROM owner_users WHERE id = 1');
    if (!$owner || (int) $owner['session_version'] !== (int) ($_SESSION['session_version'] ?? 0) || time() - (int) ($_SESSION['last_active'] ?? 0) > 7200) {
        unset($_SESSION['user_id'], $_SESSION['owner'], $_SESSION['session_version']);
    } else {
        $_SESSION['owner'] = $owner;
        $_SESSION['last_active'] = time();
    }
}
