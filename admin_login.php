<?php
/**
 * admin_login.php
 * ---------------------------------------------------------------------------
 * Called via fetch() from form.php's Registration page when the customer
 * types the shop-owner's email (ADMIN_EMAIL, see admin_config.php) into the
 * Email field. Instead of continuing the normal order flow, form.php shows
 * a PIN prompt and posts { email, pin } here as JSON.
 *
 * On a correct PIN: starts an authenticated PHP session and returns the
 * page to redirect to (view_order.php). On an incorrect PIN, or if the
 * email doesn't match ADMIN_EMAIL, or if no PIN has been configured yet:
 * returns an error and no session is created — the customer flow is
 * completely unaffected either way.
 * ---------------------------------------------------------------------------
 */

require_once __DIR__ . '/admin_config.php';

session_start();
header('Content-Type: application/json');

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

$email = isset($data['email']) ? trim((string) $data['email']) : '';
$pin   = isset($data['pin']) ? (string) $data['pin'] : '';

// Only the configured admin email is even eligible to attempt a PIN login.
if ($email === '' || strcasecmp($email, ADMIN_EMAIL) !== 0) {
    echo json_encode(['status' => 'error', 'message' => 'Not an admin account.']);
    exit;
}

// If ADMIN_PASSWORD_HASH hasn't been set up yet (see admin_config.php),
// there is no valid PIN to check against — always reject.
if (ADMIN_PASSWORD_HASH === '' || !password_verify($pin, ADMIN_PASSWORD_HASH)) {
    echo json_encode(['status' => 'error', 'message' => 'Incorrect PIN. Access denied.']);
    exit;
}

// Regenerate the session ID on privilege escalation to prevent session
// fixation attacks, then mark this session as an authenticated admin.
session_regenerate_id(true);
$_SESSION['is_admin']        = true;
$_SESSION['admin_email']     = ADMIN_EMAIL;
$_SESSION['admin_login_time'] = time();

echo json_encode(['status' => 'success', 'redirect' => 'view_order.php']);
