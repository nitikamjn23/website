<?php
/**
 * view_order.php  (Admin — "Order Details" page)
 * ---------------------------------------------------------------------------
 * Shows every order with every field the customer submitted: contact info,
 * medicines, delivery address, payment method, prescription flag, the
 * customer's note to the shop, their reminder choice, and current status.
 *
 * This replaces both the old view_order.php and the separate
 * "orderdetail.php" the shop owner mentioned — one admin page is enough for
 * a shop this size, so the two were merged rather than kept as duplicates.
 *
 * Also lets the shop owner update an order's status (Pending / Confirmed /
 * Out for Delivery / Delivered / Cancelled) directly from this page.
 * ---------------------------------------------------------------------------
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/admin_config.php';

// ── ADDED: admin authentication guard ───────────────────────────────────
// This page must never be reachable by a direct URL visit unless the
// visitor has already passed the PIN check in admin_login.php (triggered
// from form.php's Registration page). No valid admin session → bounce
// straight back to the public form instead of showing any order data.
session_start();
if (empty($_SESSION['is_admin']) || strcasecmp($_SESSION['admin_email'] ?? '', ADMIN_EMAIL) !== 0) {
    header('Location: form.php');
    exit;
}

$conn = get_db_connection();

// ── Handle a status update (posted from the dropdown further down) ─────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $orderId = (int) $_POST['order_id'];
    $newStatus = $_POST['status'];
    $allowedStatuses = ['Pending', 'Confirmed', 'Out for Delivery', 'Delivered', 'Cancelled'];

    if (in_array($newStatus, $allowedStatuses, true)) {
        $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $newStatus, $orderId);
        $stmt->execute();
        $stmt->close();
    }
    // Redirect so refreshing the page doesn't re-submit the form
    header("Location: view_order.php");
    exit;
}

$result = $conn->query("SELECT * FROM orders ORDER BY id DESC");

// MODIFIED: labels changed from 1w/2w/1m/2m/3m to the new 7d/15d/30d/45d/60d
// codes (see order_details.php's reminderDayMap and form.php's
// toBackendReminderOption()).
$reminderLabels = [
    'custom' => 'Custom',
    '7d' => '7 Days',
    '15d' => '15 Days',
    '30d' => '30 Days',
    '45d' => '45 Days',
    '60d' => '60 Days',
    'none' => '—',
];
$statusOptions = ['Pending', 'Confirmed', 'Out for Delivery', 'Delivered', 'Cancelled'];
$statusColors = [
    'Pending' => '#ba7517',
    'Confirmed' => '#1d9e75',
    'Out for Delivery' => '#0f6e56',
    'Delivered' => '#085041',
    'Cancelled' => '#a32d2d',
];
?>
<!DOCTYPE html>
<html>
<head>
  <title>All Orders — Gupta Medical Stores</title>
  <meta charset="UTF-8">
  <style>
    * { box-sizing: border-box; }
    body { font-family: Arial, sans-serif; padding: 24px; background: #faf8f4; color: #1c2528; }
    h2 { color: #085041; margin-bottom: 4px; }
    .sub { color: #5a6a6f; margin-bottom: 20px; font-size: 0.9rem; }
    table { border-collapse: collapse; width: 100%; background: #fff; box-shadow: 0 2px 10px rgba(15,110,86,0.08); }
    th, td { border: 1px solid #e1e8e6; padding: 10px 12px; font-size: 0.85rem; vertical-align: top; }
    th { background: #0f6e56; color: #fff; text-align: left; position: sticky; top: 0; }
    tr:nth-child(even) { background: #faf8f4; }
    .meds-list { margin: 0; padding-left: 16px; }
    .note-cell { max-width: 220px; white-space: pre-wrap; }
    .badge { display: inline-block; padding: 3px 10px; border-radius: 12px; color: #fff; font-size: 0.75rem; font-weight: 600; }
    .flag-yes { color: #0f6e56; font-weight: 600; }
    .flag-no  { color: #a32d2d; }
    select, button { font-family: inherit; font-size: 0.82rem; padding: 4px 6px; border-radius: 6px; border: 1px solid #ccc; }
    button { background: #0f6e56; color: #fff; border: none; cursor: pointer; margin-left: 6px; }
    button:hover { background: #085041; }
    .no-orders { padding: 20px; text-align: center; color: #5a6a6f; }
  </style>
</head>
<body>

<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;">
  <div>
    <h2>All Orders — Gupta Medical Stores</h2>
    <p class="sub">Complete order details, including reminders and shop notes submitted by customers.</p>
  </div>
  <!-- ADDED: logout link — ends the admin session via admin_logout.php -->
  <a href="admin_logout.php" style="background:#a32d2d;color:#fff;padding:8px 16px;border-radius:6px;text-decoration:none;font-size:0.85rem;font-weight:600;">Logout</a>
</div>

<?php if ($result && $result->num_rows > 0): ?>
<table>
<tr>
  <th>Order ID</th>
  <th>Customer</th>
  <th>Contact</th>
  <th>Address</th>
  <th>Medicines</th>
  <th>Total</th>
  <th>Payment</th>
  <th>Prescription</th>
  <th>Note for Shop Owner</th>
  <th>Reminder</th>
  <!-- ADDED: shows whether the order-confirmation email actually went out,
       backed by the new confirmation_email_sent column. -->
  <th>Email</th>
  <th>Status</th>
  <th>Order Date</th>
</tr>
<?php while ($row = $result->fetch_assoc()): ?>
<tr>
  <td><?= htmlspecialchars($row['order_uid'] ?: ('#' . $row['id'])) ?></td>
  <td><?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?><br><small><?= htmlspecialchars($row['gender'] ?: '—') ?></small></td>
  <td>
    📞 <?= htmlspecialchars($row['phone']) ?><br>
    ✉️ <?= htmlspecialchars($row['email'] ?: '—') ?>
  </td>
  <td><?= nl2br(htmlspecialchars($row['address'])) ?></td>
  <td>
    <?php
    $meds = json_decode($row['medicines'], true);
    $orderTotal = 0;
    if (!empty($meds)) {
        echo '<ul class="meds-list">';
        foreach ($meds as $m) {
            $name  = htmlspecialchars($m['name'] ?? '—');
            $qty   = htmlspecialchars((string)($m['qty'] ?? '—'));
            $price = isset($m['price']) && $m['price'] !== '' ? (float)$m['price'] : 0;
            $orderTotal += $price;
            $priceText = $price ? '₹' . htmlspecialchars((string)$m['price']) : '';
            echo "<li>{$name} (Qty: {$qty}" . ($priceText ? ", {$priceText}" : "") . ")</li>";
        }
        echo '</ul>';
    } else {
        echo '—';
    }
    ?>
  </td>
  <td><strong>₹<?= number_format($orderTotal, 2) ?></strong></td>
  <td>
    <?= htmlspecialchars($row['payment_method'] ?? 'Cash on Delivery') ?>
    <?php if (($row['payment_method'] ?? '') === 'UPI' && !empty($row['upi_transaction_id'])): ?>
      <br><small>Ref: <?= htmlspecialchars($row['upi_transaction_id']) ?></small>
    <?php endif; ?>
  </td>
  <td>
    <?php if (!empty($row['prescription_attached'])): ?>
      <?php if (!empty($row['prescription_path'])):
        $rxPath = htmlspecialchars($row['prescription_path']);
        $rxExt  = strtolower(pathinfo($row['prescription_path'], PATHINFO_EXTENSION));
      ?>
        <?php if ($rxExt === 'pdf'): ?>
          <a class="flag-yes" href="<?= $rxPath ?>" target="_blank" rel="noopener">📄 View PDF</a>
        <?php else: ?>
          <a href="<?= $rxPath ?>" target="_blank" rel="noopener">
            <img src="<?= $rxPath ?>" alt="Prescription" style="max-width:90px;max-height:90px;border-radius:6px;border:1px solid #e1e8e6;display:block;">
          </a>
        <?php endif; ?>
      <?php else: ?>
        <!-- Order placed before the prescription-save fix, or the file
             failed to save server-side — the flag is true but no file
             exists to show. -->
        <span class="flag-yes">✔ Attached <small>(file unavailable)</small></span>
      <?php endif; ?>
    <?php else: ?>
      <span class="flag-no">— None</span>
    <?php endif; ?>
  </td>
  <td class="note-cell"><?= $row['note_to_owner'] ? nl2br(htmlspecialchars($row['note_to_owner'])) : '<em>None</em>' ?></td>
  <td>
    <?php if (!empty($row['reminder_enabled'])): ?>
      <?= htmlspecialchars($reminderLabels[$row['reminder_option']] ?? $row['reminder_option']) ?>
      <?php if ($row['reminder_option'] === 'custom'): ?>
        (<?= (int)$row['reminder_days'] ?> days)
      <?php endif; ?>
      <br><small>Due: <?= htmlspecialchars($row['reminder_date'] ?: '—') ?></small>
      <br><small><?= $row['reminder_sent'] ? '✔ Sent' : '⏳ Pending' ?></small>
    <?php else: ?>
      <em>Not requested</em>
    <?php endif; ?>
  </td>
  <td>
    <!-- ADDED: reflects the confirmation_email_sent flag set by order_details.php
         right after send_order_confirmation_email() runs. -->
    <?php if (!empty($row['confirmation_email_sent'])): ?>
      <span class="flag-yes">✔ Sent</span>
    <?php else: ?>
      <span class="flag-no">✘ Failed</span>
    <?php endif; ?>
  </td>
  <td>
    <span class="badge" style="background:<?= $statusColors[$row['status']] ?? '#5a6a6f' ?>;">
      <?= htmlspecialchars($row['status'] ?: 'Pending') ?>
    </span>
    <form method="post" style="margin-top:6px;display:flex;gap:4px;">
      <input type="hidden" name="order_id" value="<?= (int)$row['id'] ?>">
      <select name="status">
        <?php foreach ($statusOptions as $opt): ?>
          <option value="<?= $opt ?>" <?= ($row['status'] === $opt) ? 'selected' : '' ?>><?= $opt ?></option>
        <?php endforeach; ?>
      </select>
      <button type="submit" name="update_status" value="1">Update</button>
    </form>
  </td>
  <td><?= htmlspecialchars($row['created_at']) ?></td>
</tr>
<?php endwhile; ?>
</table>
<?php else: ?>
  <div class="no-orders">No orders yet.</div>
<?php endif; ?>

</body>
</html>
<?php $conn->close(); ?>