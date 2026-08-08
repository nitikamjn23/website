<?php
/**
 * admin_config.php
 * ---------------------------------------------------------------------------
 * Shop-owner admin login settings, used by admin_login.php (verifies the
 * PIN) and view_order.php (guards the admin page).
 *
 * ADMIN_EMAIL
 *   The email address that, when typed into the Registration form's Email
 *   field on form.php, switches the flow from "customer registration" to
 *   "shop owner PIN login" instead of continuing to Place Order.
 *
 * ADMIN_PASSWORD_HASH
 *   A bcrypt hash produced by PHP's password_hash() — never the raw PIN
 *   itself. This is intentionally left blank below so no one (including
 *   whoever set this file up) can read the real PIN out of the source code.
 *
 * ── HOW TO SET YOUR ADMIN PIN ────────────────────────────────────────────
 * 1. Open generate_admin_hash.php in your browser (it's in the same
 *    folder as this file).
 * 2. Type the PIN/password you want to use for admin login, submit the
 *    form, and copy the generated hash it prints.
 * 3. Paste that hash below as ADMIN_PASSWORD_HASH (replacing the empty
 *    string) and save this file.
 * 4. Delete generate_admin_hash.php from the server — it should never be
 *    left publicly reachable, since anyone who can reach it can generate
 *    hashes (though they'd still need to overwrite this file to use one).
 *
 * Until ADMIN_PASSWORD_HASH is set, admin_login.php will always reject
 * login attempts — customers are never blocked, only the admin login is
 * unavailable.
 * ---------------------------------------------------------------------------
 */

define('ADMIN_EMAIL', 'guptamedicalstore@gmail.com');
define('ADMIN_PASSWORD_HASH', '$2y$10$ey9wLmc2s90Z7uL./Kq2eu2QoM/QCOgwR36LorK66wLK9iUjTQHR.');