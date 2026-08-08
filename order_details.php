<?php
/**
 * order_details.php
 * ---------------------------------------------------------------------------
 * Receives the JSON payload from form.php when the customer clicks
 * "Confirm Order" on the Summary page, saves it to the `orders` table, then
 * fires off the order-confirmation email (and the SMS placeholder).
 *
 * Reused fields from the earlier version of this file are kept; new fields
 * (email, payment_method, prescription_attached, note_to_owner, reminder_*,
 * order_uid, status, upi_transaction_id) are added alongside them — nothing
 * that worked before has been removed.
 *
 * ADDED (UPI payment): requires a new `upi_transaction_id VARCHAR(50) NULL`
 * column on the `orders` table, e.g.:
 *   ALTER TABLE orders ADD COLUMN upi_transaction_id VARCHAR(50) NULL AFTER payment_method;
 * ---------------------------------------------------------------------------
 */
header("Content-Type: application/json");

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/mailer.php';

try {
    $conn = get_db_connection();
} catch (RuntimeException $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    exit;
}

// ── Read + validate the incoming JSON ───────────────────────────────────
$rawInput = file_get_contents("php://input");
error_log("order_details.php raw input (" . strlen($rawInput) . " bytes): " . $rawInput);

if ($rawInput === '') {
    // Almost always means this file was opened directly (a GET request with
    // no body) rather than submitted from form.php's "Confirm Order" button.
    echo json_encode([
        "status" => "error",
        "message" => "No data was received. This endpoint expects a JSON POST from form.php's " .
                      "\"Confirm Order\" button — visiting order_details.php directly in the " .
                      "browser (or refreshing it) sends an empty GET request, which triggers " .
                      "this exact message. Place an order through form.php instead."
    ]);
    exit;
}

$data = json_decode($rawInput, true);
if (!is_array($data)) {
    echo json_encode([
        "status" => "error",
        "message" => "Received a request body, but it wasn't valid JSON (json error: " .
                      json_last_error_msg() . "). Check the Network tab in DevTools, click the " .
                      "order_details.php request, and compare its Request Payload against what " .
                      "was logged in your PHP error log (search for 'order_details.php raw input')."
    ]);
    exit;
}

function clean(array $d, string $key, string $default = ''): string {
    return isset($d[$key]) ? trim((string)$d[$key]) : $default;
}

$orderUid   = clean($data, 'orderId');           // e.g. "GMS-482913", generated client-side for display
$firstName  = clean($data, 'firstName');
$lastName   = clean($data, 'lastName');
$gender     = clean($data, 'gender');
$phone      = clean($data, 'phone');
$email      = clean($data, 'email');
$address    = clean($data, 'address');
$discount   = (int) ($data['discount'] ?? 0);
$payment    = clean($data, 'payment', 'Cash on Delivery');
$upiTransactionId = mb_substr(clean($data, 'upiTransactionId'), 0, 50); // optional, customer-entered
$noteToOwner = mb_substr(clean($data, 'noteToOwner'), 0, 500); // hard cap at 500 chars, matches column size
$prescriptionAttached = !empty($data['prescriptionAttached']) ? 1 : 0;

// ── Save the uploaded prescription file (if any) ────────────────────────
// FIX: previously only $prescriptionAttached (a plain yes/no flag) was
// saved — the actual image/PDF data the customer uploaded was never sent
// to the server at all, so view_order.php had nothing to display. The
// front end now sends the file as a base64 data URL (the same one the
// browser already holds for the on-page preview); it's decoded and
// written to uploads/prescriptions/ here, and the resulting relative path
// is what gets stored in the new prescription_path column below.
$prescriptionPath = save_prescription_file($data['prescription'] ?? null, $orderUid);

/**
 * Decodes a { name, type, dataUrl } payload (dataUrl = "data:<mime>;base64,...",
 * exactly what FileReader.readAsDataURL() produces client-side) and writes
 * it to uploads/prescriptions/ next to this script. Returns the path to
 * store in the DB (relative to this file, so view_order.php — which lives
 * in the same folder — can use it directly as an <img>/<a> src), or null
 * if there was nothing to save or the data was malformed.
 */
function save_prescription_file(?array $file, string $orderUid): ?string {
    if (!$file || empty($file['dataUrl']) || !is_string($file['dataUrl'])) {
        return null;
    }
    if (!preg_match('/^data:([\w\-\.\/\+]+);base64,(.+)$/s', $file['dataUrl'], $m)) {
        return null;
    }

    $mime   = $m[1];
    $binary = base64_decode($m[2], true);
    if ($binary === false) return null;

    $extByMime = [
        'image/jpeg'      => 'jpg',
        'image/jpg'       => 'jpg',
        'image/png'       => 'png',
        'application/pdf' => 'pdf',
    ];
    $ext = $extByMime[$mime] ?? 'bin';
    if ($ext === 'bin') return null; // not one of the types the upload form accepts

    $dir = __DIR__ . '/uploads/prescriptions';
    if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
        error_log("order_details.php: could not create prescriptions upload dir: {$dir}");
        return null;
    }
    if (!is_writable($dir)) {
        error_log("order_details.php: prescriptions upload dir not writable: {$dir}");
        return null;
    }

    $safeUid  = preg_replace('/[^A-Za-z0-9_\-]/', '', $orderUid) ?: ('order_' . time());
    $filename = $safeUid . '_' . bin2hex(random_bytes(4)) . '.' . $ext;

    if (file_put_contents($dir . '/' . $filename, $binary) === false) {
        error_log("order_details.php: failed writing prescription file to {$dir}/{$filename}");
        return null;
    }

    return 'uploads/prescriptions/' . $filename;
}

$medicinesArr = is_array($data['medicines'] ?? null) ? $data['medicines'] : [];
$medicinesJson = json_encode($medicinesArr);

// Basic required-field validation (mirrors the client-side checks in form.php,
// but never trust the client alone)
if ($firstName === '' || $lastName === '' || $phone === '' || $address === '' || empty($medicinesArr)) {
    echo json_encode(["status" => "error", "message" => "Missing required order fields."]);
    exit;
}

// ── Reminder selection ──────────────────────────────────────────────────
// reminderOption: 'none' | 'custom' | '7d' | '15d' | '30d' | '45d' | '60d'
// MODIFIED: codes changed from 1w/2w/1m/2m/3m (7/14/30/60/90 days) to
// 7d/15d/30d/45d/60d, matching form.php's toBackendReminderOption().
$reminderOption = clean($data, 'reminderOption', 'none');
$reminderEnabled = 0;
$reminderDays = null;
$reminderDate = null;

$reminderDayMap = [
    '7d'  => 7,
    '15d' => 15,
    '30d' => 30,
    '45d' => 45,
    '60d' => 60,
];

if ($reminderOption === 'custom') {
    $customDays = (int) ($data['reminderCustomDays'] ?? 0);
    if ($customDays > 0) {
        $reminderEnabled = 1;
        $reminderDays = $customDays;
    }
} elseif (isset($reminderDayMap[$reminderOption])) {
    $reminderEnabled = 1;
    $reminderDays = $reminderDayMap[$reminderOption];
}

if ($reminderEnabled && $reminderDays) {
    $reminderDate = date('Y-m-d H:i:s', strtotime("+{$reminderDays} days"));
}

// ── ADDED: duplicate-submission guard ───────────────────────────────────
// form.php generates order_uid client-side (e.g. "GMS-482913") once, when the
// customer reaches the Summary page, and confirmOrder() only fires on a
// button click — but a flaky connection can still cause the browser to retry
// the same POST (fetch() auto-retry, a double click before the button
// disables, etc.). If a row with this order_uid already exists, treat the
// request as already handled: return the existing order instead of creating
// a second row and firing a second confirmation email.
if ($orderUid !== '') {
    $dupStmt = $conn->prepare("SELECT id, order_uid FROM orders WHERE order_uid = ? LIMIT 1");
    $dupStmt->bind_param("s", $orderUid);
    $dupStmt->execute();
    $dupRow = $dupStmt->get_result()->fetch_assoc();
    $dupStmt->close();

    if ($dupRow) {
        echo json_encode(["status" => "success", "id" => (int)$dupRow['id'], "order_uid" => $dupRow['order_uid']]);
        $conn->close();
        exit;
    }
}

// ── Insert using a prepared statement (prevents SQL injection — the old
//    version of this file built raw SQL strings from user input) ────────
// ADDED (prescription image fix): requires a new
// `prescription_path VARCHAR(255) NULL` column on the `orders` table, e.g.:
//   ALTER TABLE orders ADD COLUMN prescription_path VARCHAR(255) NULL AFTER prescription_attached;
$sql = "INSERT INTO orders
    (order_uid, first_name, last_name, gender, phone, email, address, medicines,
     discount, payment_method, upi_transaction_id, prescription_attached, prescription_path, note_to_owner,
     reminder_enabled, reminder_option, reminder_days, reminder_date, status)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending')";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(["status" => "error", "message" => "Database error: " . $conn->error]);
    exit;
}

$stmt->bind_param(
    "ssssssssississisis",
    $orderUid, $firstName, $lastName, $gender, $phone, $email, $address, $medicinesJson,
    $discount, $payment, $upiTransactionId, $prescriptionAttached, $prescriptionPath, $noteToOwner,
    $reminderEnabled, $reminderOption, $reminderDays, $reminderDate
);

if ($stmt->execute()) {
    $insertId = $stmt->insert_id;
    $stmt->close();

    // ── Fire the order-confirmation email + SMS placeholder ────────────
    // MODIFIED: added 'prescription_attached' so the confirmation email can
    // show prescription status (send_order_confirmation_email in mailer.php).
    $orderRow = [
        'id'          => $insertId,
        'order_uid'   => $orderUid,
        'first_name'  => $firstName,
        'last_name'   => $lastName,
        'address'     => $address,
        'email'       => $email,
        // ADDED: phone, note_to_owner, prescription_path — not needed by
        // send_order_confirmation_email() (the customer's email) but read
        // by send_owner_order_notification_email() below.
        'phone'       => $phone,
        'note_to_owner' => $noteToOwner,
        'prescription_path' => $prescriptionPath,
        'medicines'   => $medicinesArr,
        'payment_method' => $payment,
        'prescription_attached' => $prescriptionAttached,
        'created_at'  => date('Y-m-d H:i:s'),
    ];

    // send_email()/smtp_send_via_gmail() never throw — they return false and
    // log the SMTP-level error themselves (see mailer.php). This request
    // still succeeds either way; the order is already saved at this point.
    $emailSent = send_order_confirmation_email($orderRow);

    // ── ADDED: notify the shop owner of the new order ───────────────────
    // Sent to OWNER_NOTIFICATION_EMAIL (mail_config.php) with the full
    // order details. Independent of whether the customer's own
    // confirmation email succeeded — failure here is logged only, same as
    // above, and never blocks the order or is shown to the customer.
    if (!send_owner_order_notification_email($orderRow)) {
        error_log("order_details.php: owner notification email FAILED to send for order {$orderUid} (id {$insertId}).");
    }

    // ── ADDED: persist confirmation_email_sent so it's visible in the admin
    // panel and so nothing downstream re-sends this email for the same order
    // (requires a `confirmation_email_sent TINYINT(1) DEFAULT 0` column —
    // see the SQL notes at the end of this change).
    $emailFlagStmt = $conn->prepare("UPDATE orders SET confirmation_email_sent = ? WHERE id = ?");
    $emailFlagInt = $emailSent ? 1 : 0;
    $emailFlagStmt->bind_param("ii", $emailFlagInt, $insertId);
    $emailFlagStmt->execute();
    $emailFlagStmt->close();

    if (!$emailSent) {
        // Order still succeeds — email failure is logged only, never surfaced
        // to the customer as an order failure.
        error_log("order_details.php: confirmation email FAILED to send for order {$orderUid} (id {$insertId}, to '{$email}'). Order was still saved successfully.");
    }

    send_order_sms($phone, "Hi {$firstName}, your Gupta Medical Stores order {$orderUid} has been placed successfully. We'll call before dispatch.");

    echo json_encode(["status" => "success", "id" => $insertId, "order_uid" => $orderUid]);
} else {
    echo json_encode(["status" => "error", "message" => "Could not save order: " . $stmt->error]);
}

$conn->close();