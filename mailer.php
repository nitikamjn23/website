<?php
/**
 * mailer.php
 * ---------------------------------------------------------------------------
 * All outgoing customer notifications (order confirmation email, reorder
 * reminder email, and a placeholder for SMS) live here so order_details.php
 * and send_reminders.php can both reuse the same logic instead of duplicating
 * email-building code.
 *
 * NOTE ON DELIVERABILITY:
 * send_email() now sends through Gmail SMTP (see mail_config.php) once you've
 * added your Gmail address + App Password there. Until then, it falls back
 * to PHP's built-in mail(), which works out of the box on some shared hosts
 * but silently does nothing on others (and on most local/dev setups) because
 * no mail transfer agent is configured.
 * ---------------------------------------------------------------------------
 */

require_once __DIR__ . '/mail_config.php';

/**
 * Sends one email over a raw SMTP connection to Gmail, authenticated with
 * an App Password (see mail_config.php for setup). No external library
 * needed — this talks SMTP directly over PHP streams, so it works on any
 * host that allows outbound connections on port 587, including ones
 * without Composer/shell access.
 *
 * Returns false (never throws) on any failure so send_email() can fall
 * back to PHP's mail(). Failures are logged via error_log() for debugging.
 */
function smtp_send_via_gmail(string $to, string $subject, string $htmlBody): bool {
    if (empty($to)) return false;
    if (GMAIL_ADDRESS === 'YOUR_GMAIL_ADDRESS_HERE@gmail.com' || GMAIL_APP_PASSWORD === 'YOUR_16_CHARACTER_APP_PASSWORD_HERE') {
        return false; // not configured yet — caller falls back to mail()
    }

    $socket = @stream_socket_client(
        'tcp://' . GMAIL_SMTP_HOST . ':' . GMAIL_SMTP_PORT,
        $errno, $errstr, 15
    );
    if (!$socket) {
        error_log("smtp_send_via_gmail: connection failed ({$errno}) {$errstr}");
        return false;
    }
    stream_set_timeout($socket, 15);

    // Reads one full (possibly multi-line) SMTP response. Continuation
    // lines look like "250-text", the final line "250 text" (a space in
    // position 4, not a dash) — stop once we see that final line.
    $readResponse = function () use ($socket) {
        $response = '';
        while (($line = fgets($socket, 515)) !== false) {
            $response .= $line;
            if (!isset($line[3]) || $line[3] !== '-') break;
        }
        return $response;
    };
    $expect = function (int $expectedCode) use ($socket, $readResponse) {
        $response = $readResponse();
        $code = (int) substr($response, 0, 3);
        if ($code !== $expectedCode) {
            error_log("smtp_send_via_gmail: expected {$expectedCode}, got: " . trim($response));
            return false;
        }
        return true;
    };
    $send = function (string $data) use ($socket) {
        fwrite($socket, $data . "\r\n");
    };

    if (!$expect(220))                                     { fclose($socket); return false; }
    $send('EHLO localhost');           if (!$expect(250))  { fclose($socket); return false; }
    $send('STARTTLS');                 if (!$expect(220))  { fclose($socket); return false; }

    if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
        error_log('smtp_send_via_gmail: TLS negotiation failed');
        fclose($socket);
        return false;
    }

    $send('EHLO localhost');           if (!$expect(250))  { fclose($socket); return false; }
    $send('AUTH LOGIN');               if (!$expect(334))  { fclose($socket); return false; }
    $send(base64_encode(GMAIL_ADDRESS));      if (!$expect(334))  { fclose($socket); return false; }
    $send(base64_encode(GMAIL_APP_PASSWORD));
    if (!$expect(235)) {
        error_log('smtp_send_via_gmail: authentication failed — check GMAIL_ADDRESS/GMAIL_APP_PASSWORD in mail_config.php (must be an App Password, not your normal Gmail password)');
        fclose($socket);
        return false;
    }

    $send('MAIL FROM:<' . GMAIL_ADDRESS . '>');  if (!$expect(250)) { fclose($socket); return false; }
    $send('RCPT TO:<' . $to . '>');              if (!$expect(250)) { fclose($socket); return false; }
    $send('DATA');                               if (!$expect(354)) { fclose($socket); return false; }

    $encodedSubject = function_exists('mb_encode_mimeheader')
        ? mb_encode_mimeheader($subject, 'UTF-8', 'B', "\r\n")
        : $subject;

    $headers = [
        'From: ' . MAIL_FROM_NAME . ' <' . GMAIL_ADDRESS . '>',
        'To: <' . $to . '>',
        'Subject: ' . $encodedSubject,
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'Date: ' . date('r'),
        'Message-ID: <' . bin2hex(random_bytes(8)) . '@gupta-medical-stores>',
    ];

    // Dot-stuffing: any body line starting with "." must get a second "."
    // prepended, per the SMTP DATA command's rules (RFC 5321 §4.5.2) —
    // otherwise a lone "." on its own line would be misread as end-of-message.
    $bodyLines = explode("\n", str_replace(["\r\n", "\r"], "\n", $htmlBody));
    $stuffedLines = array_map(fn($line) => (isset($line[0]) && $line[0] === '.') ? '.' . $line : $line, $bodyLines);
    $body = implode("\r\n", $stuffedLines);

    $send(implode("\r\n", $headers) . "\r\n\r\n" . $body . "\r\n.");
    if (!$expect(250)) { fclose($socket); return false; }

    $send('QUIT');
    fclose($socket);
    return true;
}

/**
 * Low-level mail sender. Tries Gmail SMTP first (once mail_config.php has
 * real credentials in it); falls back to PHP's built-in mail() otherwise,
 * so the site keeps working even before Gmail is configured.
 */
function send_email(string $to, string $subject, string $htmlBody): bool {
    if (empty($to)) return false;

    if (smtp_send_via_gmail($to, $subject, $htmlBody)) {
        return true;
    }

    // Fallback: PHP's built-in mail(). See NOTE ON DELIVERABILITY above —
    // this silently does nothing on hosts without a configured MTA.
    $fromEmail = (GMAIL_ADDRESS !== 'YOUR_GMAIL_ADDRESS_HERE@gmail.com') ? GMAIL_ADDRESS : 'orders@guptamedicalstores.example';
    $fromName  = MAIL_FROM_NAME;

    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    $headers .= "From: {$fromName} <{$fromEmail}>\r\n";

    // mail() returns true if the message was *accepted for delivery*, not
    // that it was actually delivered — keep that in mind while testing.
    return @mail($to, $subject, $htmlBody, $headers);
}

/**
 * Shared header/footer wrapper so every email looks consistent with the
 * site's teal/gold branding.
 */
function email_wrap(string $title, string $bodyHtml): string {
    return "
    <div style='font-family:Arial,sans-serif;background:#faf8f4;padding:24px;'>
      <div style='max-width:560px;margin:0 auto;background:#ffffff;border-radius:10px;overflow:hidden;border:1px solid #e1f5ee;'>
        <div style='background:#085041;color:#fff;padding:20px 24px;'>
          <h2 style='margin:0;font-size:20px;'>Gupta Medical Stores</h2>
          <p style='margin:4px 0 0;font-size:12px;opacity:0.8;'>Trusted Health, Since 1988</p>
        </div>
        <div style='padding:24px;color:#1c2528;font-size:14px;line-height:1.6;'>
          <h3 style='color:#0f6e56;margin-top:0;'>{$title}</h3>
          {$bodyHtml}
        </div>
        <div style='background:#f0ece3;color:#5a6a6f;font-size:12px;padding:14px 24px;'>
          Gupta Medical Stores &middot; +91 9935922282 &middot; guptamedicalstore@gmail.com
        </div>
      </div>
    </div>";
}

/**
 * Builds and sends the order-confirmation email. Expects an associative
 * array shaped like a row from the `orders` table, with `medicines` already
 * decoded to an array (not the raw JSON string).
 */
function send_order_confirmation_email(array $order): bool {
    if (empty($order['email'])) return false;

    $medsRows = '';
    foreach ($order['medicines'] as $m) {
        $name = htmlspecialchars($m['name'] ?? '—');
        $qty  = htmlspecialchars((string)($m['qty'] ?? '—'));
        $medsRows .= "<tr><td style='padding:6px 0;border-bottom:1px solid #eee;'>{$name}</td><td style='padding:6px 0;border-bottom:1px solid #eee;text-align:center;'>{$qty}</td></tr>";
    }

    $customerName = htmlspecialchars($order['first_name'] . ' ' . $order['last_name']);
    $address      = nl2br(htmlspecialchars($order['address']));
    $orderId      = htmlspecialchars($order['order_uid'] ?: ('#' . $order['id']));
    $dateTime     = htmlspecialchars($order['created_at']);
    $payment      = htmlspecialchars($order['payment_method']);
    // ADDED: prescription status row. Falls back to "Not Required / Not
    // Provided" when the key isn't present, so this stays safe to call from
    // anywhere that doesn't (yet) pass prescription_attached.
    $prescriptionStatus = !empty($order['prescription_attached'])
        ? "<span style='color:#0f6e56;font-weight:600;'>&#10003; Attached</span>"
        : "<span style='color:#5a6a6f;'>Not Provided</span>";

    $body = "
      <p>Hi {$customerName},</p>
      <p>Thank you for your order! Here are your order details:</p>
      <table style='width:100%;border-collapse:collapse;margin:12px 0;'>
        <tr><td style='padding:4px 0;color:#5a6a6f;'>Order ID</td><td style='padding:4px 0;font-weight:600;'>{$orderId}</td></tr>
        <tr><td style='padding:4px 0;color:#5a6a6f;'>Order Date &amp; Time</td><td style='padding:4px 0;'>{$dateTime}</td></tr>
        <tr><td style='padding:4px 0;color:#5a6a6f;'>Payment Method</td><td style='padding:4px 0;'>{$payment}</td></tr>
        <tr><td style='padding:4px 0;color:#5a6a6f;'>Prescription Status</td><td style='padding:4px 0;'>{$prescriptionStatus}</td></tr>
        <tr><td style='padding:4px 0;color:#5a6a6f;vertical-align:top;'>Delivery Address</td><td style='padding:4px 0;'>{$address}</td></tr>
      </table>
      <table style='width:100%;border-collapse:collapse;margin:12px 0;'>
        <tr><th style='text-align:left;background:#f0ece3;padding:6px;'>Medicine</th><th style='background:#f0ece3;padding:6px;'>Qty</th></tr>
        {$medsRows}
      </table>
      <p>We'll call you on your registered number to confirm stock before dispatch.</p>
      <p style='color:#5a6a6f;font-size:12px;'>Questions? Reach us at +91 9935922282 or guptamedicalstore@gmail.com.</p>
    ";

    return send_email($order['email'], "Your Gupta Medical Stores Order {$orderId} is Confirmed", email_wrap('Order Confirmed', $body));
}

/**
 * ADDED: builds an absolute URL (scheme + host + path) for a path that's
 * relative to this folder — e.g. turns "uploads/prescriptions/x.jpg" into
 * "https://yoursite.com/uploads/prescriptions/x.jpg" — so the prescription
 * link in the owner-notification email works when opened from an email
 * client (a bare relative path wouldn't resolve to anything there). Falls
 * back gracefully if $_SERVER data isn't available (e.g. run from CLI).
 */
function build_absolute_url(string $relativePath): ?string {
    if (empty($_SERVER['HTTP_HOST'])) return null;
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $dir = isset($_SERVER['SCRIPT_NAME']) ? rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/') : '';
    return "{$scheme}://{$_SERVER['HTTP_HOST']}{$dir}/{$relativePath}";
}

/**
 * ADDED: builds and sends the "new order placed" notification email to the
 * shop owner (OWNER_NOTIFICATION_EMAIL, see mail_config.php). Called from
 * order_details.php right after an order is saved, for every order —
 * separate from send_order_confirmation_email() above, which goes to the
 * customer instead. Expects the same order-row shape as that function,
 * plus 'phone', 'note_to_owner', and 'prescription_path'.
 */
function send_owner_order_notification_email(array $order): bool {
    $customerName = htmlspecialchars(trim(($order['first_name'] ?? '') . ' ' . ($order['last_name'] ?? '')));
    $orderId      = htmlspecialchars($order['order_uid'] ?: ('#' . $order['id']));
    $dateTime     = htmlspecialchars($order['created_at'] ?? date('Y-m-d H:i:s'));
    $phone        = htmlspecialchars($order['phone'] ?? '—');
    $email        = htmlspecialchars($order['email'] ?: '—');
    $address      = nl2br(htmlspecialchars($order['address'] ?? ''));
    $payment      = htmlspecialchars($order['payment_method'] ?? '—');
    $note         = !empty($order['note_to_owner']) ? nl2br(htmlspecialchars($order['note_to_owner'])) : '<em>None</em>';

    // The PIN code is already embedded inside the free-text address as
    // "PIN: NNNNNN" (see form.php's validateRegistration()) — pull it out
    // separately here so it shows as its own row, same as requested.
    $pincode = '—';
    if (!empty($order['address']) && preg_match('/PIN:\s*(\d{4,6})/i', $order['address'], $m)) {
        $pincode = htmlspecialchars($m[1]);
    }

    $medsRows = '';
    foreach (($order['medicines'] ?? []) as $med) {
        $name = htmlspecialchars($med['name'] ?? '—');
        $qty  = htmlspecialchars((string)($med['qty'] ?? '—'));
        $medsRows .= "<tr><td style='padding:6px 0;border-bottom:1px solid #eee;'>{$name}</td><td style='padding:6px 0;border-bottom:1px solid #eee;text-align:center;'>{$qty}</td></tr>";
    }

    $prescriptionRow = "<span style='color:#5a6a6f;'>Not Provided</span>";
    if (!empty($order['prescription_attached'])) {
        if (!empty($order['prescription_path'])) {
            $url = build_absolute_url($order['prescription_path']);
            $prescriptionRow = $url
                ? "<a href='" . htmlspecialchars($url) . "' target='_blank' style='color:#0f6e56;font-weight:600;'>&#10003; View Prescription</a>"
                : "<span style='color:#0f6e56;font-weight:600;'>&#10003; Attached (see admin panel)</span>";
        } else {
            $prescriptionRow = "<span style='color:#0f6e56;font-weight:600;'>&#10003; Attached (see admin panel)</span>";
        }
    }

    $body = "
      <p>A new order has been placed on the website.</p>
      <table style='width:100%;border-collapse:collapse;margin:12px 0;'>
        <tr><td style='padding:4px 0;color:#5a6a6f;'>Order ID</td><td style='padding:4px 0;font-weight:600;'>{$orderId}</td></tr>
        <tr><td style='padding:4px 0;color:#5a6a6f;'>Date &amp; Time</td><td style='padding:4px 0;'>{$dateTime}</td></tr>
        <tr><td style='padding:4px 0;color:#5a6a6f;'>Customer Name</td><td style='padding:4px 0;'>{$customerName}</td></tr>
        <tr><td style='padding:4px 0;color:#5a6a6f;'>Mobile Number</td><td style='padding:4px 0;'>{$phone}</td></tr>
        <tr><td style='padding:4px 0;color:#5a6a6f;'>Email Address</td><td style='padding:4px 0;'>{$email}</td></tr>
        <tr><td style='padding:4px 0;color:#5a6a6f;vertical-align:top;'>Delivery Address</td><td style='padding:4px 0;'>{$address}</td></tr>
        <tr><td style='padding:4px 0;color:#5a6a6f;'>PIN Code</td><td style='padding:4px 0;'>{$pincode}</td></tr>
        <tr><td style='padding:4px 0;color:#5a6a6f;'>Payment Method</td><td style='padding:4px 0;'>{$payment}</td></tr>
        <tr><td style='padding:4px 0;color:#5a6a6f;'>Prescription</td><td style='padding:4px 0;'>{$prescriptionRow}</td></tr>
        <tr><td style='padding:4px 0;color:#5a6a6f;vertical-align:top;'>Note for Shop Owner</td><td style='padding:4px 0;'>{$note}</td></tr>
      </table>
      <table style='width:100%;border-collapse:collapse;margin:12px 0;'>
        <tr><th style='text-align:left;background:#f0ece3;padding:6px;'>Medicine</th><th style='background:#f0ece3;padding:6px;'>Qty</th></tr>
        {$medsRows}
      </table>
      <p style='color:#5a6a6f;font-size:12px;'>Full details, including any prescription file, are also available in the admin panel (view_order.php).</p>
    ";

    return send_email(OWNER_NOTIFICATION_EMAIL, "New Order Received — {$orderId}", email_wrap('New Order Notification', $body));
}

/**
 * Builds and sends the "time to reorder" reminder email. Called by
 * send_reminders.php for any order whose reminder_date has arrived.
 */
function send_reminder_email(array $order): bool {
    if (empty($order['email'])) return false;

    $customerName = htmlspecialchars($order['first_name'] . ' ' . $order['last_name']);
    $orderId      = htmlspecialchars($order['order_uid'] ?: ('#' . $order['id']));

    $medsList = '';
    foreach ($order['medicines'] as $m) {
        $name = htmlspecialchars($m['name'] ?? '—');
        $medsList .= "<li>{$name}</li>";
    }

    $body = "
      <p>Hi {$customerName},</p>
      <p>This is a friendly reminder from Gupta Medical Stores — based on your last order ({$orderId}), it might be time to reorder your medicines:</p>
      <ul>{$medsList}</ul>
      <p>Reply to this email or message us on WhatsApp at +91 9935922282 to place your reorder.</p>
    ";

    return send_email($order['email'], "Time to Reorder Your Medicines — Gupta Medical Stores", email_wrap('Reorder Reminder', $body));
}

/**
 * SMS PLACEHOLDER
 * ---------------------------------------------------------------------------
 * No SMS provider is wired up yet. This function currently just logs what
 * *would* have been sent, so the rest of the codebase (order_details.php)
 * can already call it everywhere it needs to, and the only thing left to do
 * later is fill in the body of this one function.
 *
 * To go live with a real provider, replace the body below with, e.g.:
 *
 *   // --- Twilio example ---
 *   // $sid = 'YOUR_TWILIO_SID'; $token = 'YOUR_TWILIO_AUTH_TOKEN';
 *   // $from = '+1XXXXXXXXXX';
 *   // $ch = curl_init("https://api.twilio.com/2010-04-01/Accounts/$sid/Messages.json");
 *   // curl_setopt($ch, CURLOPT_POST, true);
 *   // curl_setopt($ch, CURLOPT_USERPWD, "$sid:$token");
 *   // curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(['To' => $phone, 'From' => $from, 'Body' => $message]));
 *   // curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
 *   // curl_exec($ch); curl_close($ch);
 *
 *   // --- MSG91 / Fast2SMS example ---
 *   // $ch = curl_init("https://api.msg91.com/api/v5/flow/");
 *   // ... set headers with your MSG91 authkey, POST JSON body with $phone/$message ...
 */
function send_order_sms(string $phone, string $message): bool {
    if (empty($phone)) return false;

    // TODO: replace this log line with a real SMS API call (see comment block above).
    error_log("[SMS placeholder] Would send to {$phone}: {$message}");
    return true;
}