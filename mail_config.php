<?php
/**
 * mail_config.php
 * ---------------------------------------------------------------------------
 * Gmail SMTP configuration, used by mailer.php to send order confirmation
 * and reminder emails through your Gmail account instead of PHP's
 * unreliable built-in mail() function.
 *
 * ── IMPORTANT: Gmail does NOT use a simple "API key" for sending mail ───
 * There's no single API-key field Gmail gives you for this. You have two
 * real options:
 *   (a) SMTP + an "App Password" (what this file sets up — simplest, no
 *       extra library, works from any host).
 *   (b) The Gmail API with full OAuth2 (client ID/secret + refresh token) —
 *       more setup, only worth it if you need to send AS a Google
 *       Workspace domain with delegated permissions across many users.
 * For a single shop's order emails, (a) is the standard choice.
 *
 * ── SETUP ────────────────────────────────────────────────────────────────
 * 1. Turn on 2-Step Verification on the Gmail account you want to send
 *    from: https://myaccount.google.com/security
 * 2. Once 2-Step Verification is on, generate an App Password:
 *    https://myaccount.google.com/apppasswords
 *    → App: "Mail", Device: "Other" → name it e.g. "Gupta Medical Stores".
 *    Google shows a 16-character password (like "abcd efgh ijkl mnop") —
 *    copy it WITHOUT the spaces.
 * 3. Paste your Gmail address and that app password below.
 *
 * Do NOT use your normal Gmail login password here — Google blocks that
 * for SMTP and it won't work; it must be an App Password.
 * ---------------------------------------------------------------------------
 */

define('GMAIL_ADDRESS', 'guptamedicalstores505@gmail.com');
define('GMAIL_APP_PASSWORD', 'psulmwnjdfghynxl');

define('GMAIL_SMTP_HOST', 'smtp.gmail.com');
define('GMAIL_SMTP_PORT', 587); // STARTTLS port (465 would be implicit-TLS — this file uses 587)

define('MAIL_FROM_NAME', 'Gupta Medical Stores');

// ADDED: where the "new order" notification email (sent to the shop owner
// every time a customer places an order — see send_owner_order_notification_email()
// in mailer.php) is delivered. Updated from the old vineet15774@gmail.com.
define('OWNER_NOTIFICATION_EMAIL', 'guptamedicalstore@gmail.com');