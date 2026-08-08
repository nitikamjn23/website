<?php
/**
 * send_reminders.php
 * ---------------------------------------------------------------------------
 * WHY THIS FILE EXISTS:
 * order_details.php only runs when a customer submits an order — it can't
 * also run again days/weeks later to check "has this reminder date arrived
 * yet?". Something needs to check that on a schedule, independent of any
 * customer visiting the site. That's what this script is for.
 *
 * HOW TO RUN IT:
 * This is a command-line / cron script, not a page customers or the admin
 * ever open in a browser. Set up a scheduled task to run it once a day, e.g.:
 *
 *   Linux/cPanel cron (daily at 9 AM):
 *     0 9 * * *  /usr/bin/php /full/path/to/send_reminders.php
 *
 *   Windows Task Scheduler:
 *     Run: php.exe C:\path\to\send_reminders.php
 *
 * WHERE IT LIVES:
 * Same folder as order_details.php / view_order.php / db.php / mailer.php.
 * It reuses get_db_connection() from db.php and send_reminder_email() from
 * mailer.php — no logic is duplicated.
 * ---------------------------------------------------------------------------
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/mailer.php';

$conn = get_db_connection();

$sql = "SELECT * FROM orders
        WHERE reminder_enabled = 1
          AND reminder_sent = 0
          AND reminder_date IS NOT NULL
          AND reminder_date <= NOW()
          AND email IS NOT NULL AND email != ''";

$result = $conn->query($sql);
$sentCount = 0;

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $row['medicines'] = json_decode($row['medicines'], true) ?: [];

        if (send_reminder_email($row)) {
            $sentCount++;
            $upd = $conn->prepare("UPDATE orders SET reminder_sent = 1 WHERE id = ?");
            $upd->bind_param("i", $row['id']);
            $upd->execute();
            $upd->close();
        }
    }
}

// Command-line friendly output; harmless if opened in a browser too.
echo "Reminder run complete. Emails sent: {$sentCount}" . PHP_EOL;

$conn->close();
