<?php
/**
 * db.php
 * ---------------------------------------------------------------------------
 * Single shared database connection, used by order_details.php, view_order.php
 * and send_reminders.php. Centralising this means the DB credentials live in
 * exactly one place instead of being copy-pasted into every script.
 *
 * Usage in any other file:
 *   require_once __DIR__ . '/db.php';
 *   $conn = get_db_connection();
 * ---------------------------------------------------------------------------
 */

// ── Database credentials ────────────────────────────────────────────────
// Update these if your local/hosting environment differs.
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'gms_db');

/**
 * Returns a single shared mysqli connection (created once per request).
 */
function get_db_connection(): mysqli {
    static $conn = null;
    if ($conn === null) {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($conn->connect_error) {
            // In production you'd log this rather than exposing it, but for a
            // small single-shop project a clear failure message is fine.
            die(json_encode(["status" => "error", "message" => "Database connection failed."]));
        }
        $conn->set_charset('utf8mb4');
    }
    return $conn;
}
