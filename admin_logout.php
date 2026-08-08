<?php
/**
 * admin_logout.php
 * ---------------------------------------------------------------------------
 * Securely ends the shop owner's admin session. Linked from the "Logout"
 * button added to view_order.php.
 * ---------------------------------------------------------------------------
 */

session_start();

// Clear all session data, then remove the session cookie itself, then
// destroy the session server-side. Doing all three (rather than just
// session_destroy()) makes sure nothing about the session survives —
// including in browsers that would otherwise keep sending the old cookie.
$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

session_destroy();

header('Location: form.php');
exit;
