<?php
require_once __DIR__ . '/maps_config.php';
require_once __DIR__ . '/ai_config.php'; // single source of truth for SHOP_PHONE / SHOP_WHATSAPP / SHOP_EMAIL / hours
require_once __DIR__ . '/admin_config.php'; // ADDED: ADMIN_EMAIL, used below to branch Registration into the owner PIN login
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Gupta Medical Stores</title>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;600;700&family=DM+Sans:wght@300;400;500;600&family=Noto+Sans+Devanagari:wght@400;500;600;700&display=swap" rel="stylesheet"/>
  <!-- Google Maps JavaScript API — powers the live delivery tracking map on
       the Order Confirmation page. Key + restrictions are set up in
       maps_config.php. Loaded with `defer` since it's only used once the
       customer reaches the confirmation page (initTracking() guards for it
       not being ready yet, in case the key is missing/invalid). -->
  <script src="https://maps.googleapis.com/maps/api/js?key=<?php echo htmlspecialchars(GOOGLE_MAPS_BROWSER_KEY); ?>" defer></script>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
 
    :root {
      --teal:        #0f6e56;
      --teal-light:  #e1f5ee;
      --teal-mid:    #1d9e75;
      --teal-dark:   #085041;
      --cream:       #faf8f4;
      --cream-dark:  #f0ece3;
      --gold:        #ba7517;
      --gold-light:  #faeeda;
      --text:        #1c2528;
      --text-muted:  #5a6a6f;
      --red:         #a32d2d;
      --red-light:   #fcebeb;
      --border:      rgba(15,110,86,0.15);
      --shadow:      0 4px 24px rgba(15,110,86,0.10);
      --radius:      12px;
      --radius-sm:   8px;
      font-size: 16px;
    }
 
    body {
      font-family: 'DM Sans', sans-serif;
      background: var(--cream);
      color: var(--text);
      min-height: 100vh;
      display: flex;
      flex-direction: column;
    }

    /* When Hindi is active, layer Devanagari font on top so Hindi glyphs render
       correctly while Latin text (brand name, numbers) keeps its original font */
    body.lang-hi {
      font-family: 'Noto Sans Devanagari', 'DM Sans', sans-serif;
    }
    body.lang-hi .logo-text,
    body.lang-hi .page-heading h1,
    body.lang-hi .section-title,
    body.lang-hi .summary-header h1 {
      font-family: 'Noto Sans Devanagari', 'Cormorant Garamond', serif;
    }

    /* ══════════════ WELCOME (LANDING) SCREEN ══════════════ */
    #welcome-screen {
      position: fixed;
      inset: 0;
      z-index: 999;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      text-align: center;
      padding: 2rem;
      overflow: hidden;
      cursor: pointer;
      background:
        radial-gradient(circle at 15% 20%, rgba(29,158,117,0.35) 0%, transparent 45%),
        radial-gradient(circle at 85% 80%, rgba(186,117,23,0.25) 0%, transparent 50%),
        linear-gradient(135deg, #052e26 0%, #085041 45%, #0f6e56 100%);
      transition: opacity 0.7s ease, transform 0.7s ease;
    }
    /* Signature element: a slow-drifting field of soft "pill capsule" shapes,
       evoking the pharmacy without being literal or cluttered */
    #welcome-screen::before {
      content: '';
      position: absolute;
      inset: -10%;
      background-image:
        repeating-linear-gradient(120deg, rgba(255,255,255,0.035) 0 2px, transparent 2px 90px);
      animation: driftBg 18s linear infinite;
      pointer-events: none;
    }
    @keyframes driftBg {
      from { transform: translate(0, 0); }
      to   { transform: translate(-80px, -60px); }
    }
    #welcome-screen.leaving {
      opacity: 0;
      transform: scale(1.06);
      pointer-events: none;
    }
    .welcome-icon {
      width: 64px;
      height: 64px;
      border-radius: 16px;
      background: rgba(255,255,255,0.1);
      border: 1px solid rgba(255,255,255,0.18);
      display: flex;
      align-items: center;
      justify-content: center;
      margin-bottom: 1.8rem;
      backdrop-filter: blur(6px);
    }
    .welcome-icon svg { width: 34px; height: 34px; }
    .welcome-text {
      font-family: 'Noto Sans Devanagari', 'Cormorant Garamond', serif;
      font-weight: 600;
      color: #fff;
      font-size: clamp(1.6rem, 4.4vw, 3.1rem);
      line-height: 1.35;
      max-width: 900px;
      min-height: 1.4em;
    }
    .welcome-text .cursor {
      display: inline-block;
      width: 3px;
      background: var(--gold);
      margin-left: 4px;
      animation: blinkCursor 0.85s step-end infinite;
      height: 0.85em;
      vertical-align: -0.1em;
    }
    @keyframes blinkCursor {
      0%, 100% { opacity: 1; }
      50% { opacity: 0; }
    }
    .welcome-continue {
      margin-top: 2.6rem;
      color: rgba(255,255,255,0.75);
      font-size: 0.95rem;
      letter-spacing: 0.06em;
      text-transform: uppercase;
      font-weight: 500;
      opacity: 0;
      transition: opacity 0.6s ease;
    }
    /* JS adds .ready once the typewriter finishes, which starts the blink loop */
    .welcome-continue.ready {
      opacity: 0.9;
      animation: fadeBlink 1.8s ease-in-out infinite;
    }
    @keyframes fadeBlink {
      0%, 100% { opacity: 0.35; }
      50% { opacity: 0.9; }
    }

    /* Hide the main site until the welcome screen has been dismissed */
    #site-content { display: none; }
    #site-content.revealed {
      display: flex;
      flex-direction: column;
      flex: 1;
      animation: siteFadeIn 0.6s ease;
    }
    @keyframes siteFadeIn {
      from { opacity: 0; transform: translateY(10px); }
      to   { opacity: 1; transform: translateY(0); }
    }

    /* ══════════════ LANGUAGE SELECTOR ══════════════ */
    .lang-switch {
      display: flex;
      align-items: center;
      gap: 6px;
      background: rgba(255,255,255,0.08);
      border: 1px solid rgba(255,255,255,0.18);
      border-radius: 20px;
      padding: 4px;
      margin-left: 1rem;
    }
    .lang-switch button {
      border: none;
      background: transparent;
      color: rgba(255,255,255,0.65);
      font-family: 'DM Sans', sans-serif;
      font-size: 0.78rem;
      font-weight: 600;
      letter-spacing: 0.03em;
      padding: 6px 13px;
      border-radius: 16px;
      cursor: pointer;
      transition: background 0.2s, color 0.2s;
    }
    .lang-switch button.active {
      background: var(--teal-mid);
      color: white;
    }
    .header-right {
      display: flex;
      align-items: center;
    }
 
    /* ── HEADER ── */
    header {
      background: var(--teal-dark);
      padding: 0 2.5rem;
      display: flex;
      align-items: center;
      justify-content: space-between;
      height: 68px;
      position: sticky;
      top: 0;
      z-index: 100;
      box-shadow: 0 2px 12px rgba(4,52,44,0.35);
    }
    .logo {
      display: flex;
      align-items: center;
      gap: 12px;
    }
    .logo-icon {
      width: 38px;
      height: 38px;
      background: white;
      border-radius: 8px;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .logo-icon svg { width: 24px; height: 24px; }
    .logo-text {
      font-family: 'Cormorant Garamond', serif;
      color: white;
      font-size: 1.4rem;
      font-weight: 600;
      line-height: 1.2;
      letter-spacing: 0.01em;
    }
    .logo-text span[data-i18n="brandTagline"] {
      display: block;
      font-size: 0.75rem;
      font-family: 'DM Sans', sans-serif;
      font-weight: 300;
      color: rgba(255,255,255,0.7);
      letter-spacing: 0.08em;
      text-transform: uppercase;
    }
    .step-bar {
      display: flex;
      align-items: center;
      gap: 0;
    }
    .step {
      display: flex;
      align-items: center;
      gap: 8px;
      padding: 6px 16px;
      border-radius: 20px;
      font-size: 0.8rem;
      font-weight: 500;
      color: rgba(255,255,255,0.5);
      transition: all 0.3s;
      cursor: default;
    }
    .step.active {
      background: rgba(255,255,255,0.15);
      color: white;
    }
    .step.done {
      color: var(--teal-mid);
    }
    .step.step-clickable {
      cursor: pointer;
    }
    .step.step-clickable:hover {
      background: rgba(255,255,255,0.12);
      color: white;
    }
    .step-num {
      width: 22px;
      height: 22px;
      border-radius: 50%;
      background: rgba(255,255,255,0.15);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 0.72rem;
      font-weight: 600;
      transition: all 0.3s;
    }
    .step.active .step-num {
      background: var(--teal-mid);
      color: white;
    }
    .step.done .step-num {
      background: var(--teal-mid);
      color: white;
    }
    .step-sep {
      width: 28px;
      height: 1px;
      background: rgba(255,255,255,0.2);
    }
 
    /* ── PAGES ── */
    .page {
      display: none;
      flex: 1;
      animation: fadeSlide 0.4s ease forwards;
    }
    .page.active { display: block; }
 
    @keyframes fadeSlide {
      from { opacity: 0; transform: translateY(18px); }
      to   { opacity: 1; transform: translateY(0); }
    }
 
    /* ── PAGE 1: REGISTRATION ── */
    .reg-wrapper {
      max-width: 620px;
      margin: 3rem auto;
      padding: 0 1.5rem 3rem;
    }
    .page-heading {
      text-align: center;
      margin-bottom: 2.5rem;
    }
    .page-heading h1 {
      font-family: 'Cormorant Garamond', serif;
      font-size: 2.6rem;
      font-weight: 700;
      color: var(--teal-dark);
      line-height: 1.15;
    }
    .page-heading p {
      margin-top: 0.5rem;
      color: var(--text-muted);
      font-size: 0.95rem;
    }
    .heading-rule {
      display: flex;
      align-items: center;
      gap: 12px;
      margin: 1rem auto 0;
      width: 220px;
    }
    .heading-rule::before, .heading-rule::after {
      content: '';
      flex: 1;
      height: 1px;
      background: var(--border);
    }
    .heading-rule-icon {
      width: 8px;
      height: 8px;
      background: var(--teal-mid);
      border-radius: 50%;
    }
 
    .card {
      background: white;
      border-radius: var(--radius);
      border: 1px solid var(--border);
      padding: 2rem 2.2rem;
      box-shadow: var(--shadow);
    }
 
    .form-row {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 1rem;
    }
    .form-group {
      margin-bottom: 1.25rem;
    }
    .form-group.full { grid-column: 1 / -1; }
 
    label {
      display: block;
      font-size: 0.82rem;
      font-weight: 500;
      color: var(--text-muted);
      text-transform: uppercase;
      letter-spacing: 0.05em;
      margin-bottom: 6px;
    }
    input[type="text"],
    input[type="tel"],
    textarea,
    select {
      width: 100%;
      padding: 11px 14px;
      border: 1.5px solid var(--border);
      border-radius: var(--radius-sm);
      font-family: 'DM Sans', sans-serif;
      font-size: 0.95rem;
      color: var(--text);
      background: var(--cream);
      transition: border-color 0.2s, box-shadow 0.2s;
      outline: none;
      appearance: none;
    }
    input:focus, select:focus, textarea:focus {
      border-color: var(--teal-mid);
      box-shadow: 0 0 0 3px rgba(29,158,117,0.12);
      background: white;
    }
    input.error, select.error {
      border-color: var(--red);
      box-shadow: 0 0 0 3px rgba(163,45,45,0.1);
    }
    select {
      cursor: pointer;
      background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' fill='none'%3E%3Cpath d='M1 1l5 5 5-5' stroke='%230f6e56' stroke-width='1.5' stroke-linecap='round'/%3E%3C/svg%3E");
      background-repeat: no-repeat;
      background-position: right 14px center;
      padding-right: 38px;
    }
    textarea { resize: vertical; min-height: 80px; }
 
    .error-msg {
      font-size: 0.78rem;
      color: var(--red);
      margin-top: 4px;
      display: none;
    }
    .error-msg.show { display: block; }
 
    .checkbox-group {
      display: flex;
      align-items: center;
      gap: 10px;
      margin: 1rem 0 1.5rem;
      padding: 14px 16px;
      background: var(--teal-light);
      border-radius: var(--radius-sm);
      border: 1px solid rgba(29,158,117,0.25);
    }
    .checkbox-group input[type="checkbox"] {
      width: 18px;
      height: 18px;
      accent-color: var(--teal);
      cursor: pointer;
      flex-shrink: 0;
    }
    .checkbox-group label {
      margin: 0;
      font-size: 0.88rem;
      text-transform: none;
      letter-spacing: 0;
      color: var(--teal-dark);
      cursor: pointer;
      font-weight: 400;
    }
    .checkbox-group label a {
      color: var(--teal);
      text-decoration: underline;
    }
 
    .btn-primary {
      width: 100%;
      padding: 14px;
      background: var(--teal);
      color: white;
      border: none;
      border-radius: var(--radius-sm);
      font-family: 'DM Sans', sans-serif;
      font-size: 1rem;
      font-weight: 600;
      cursor: pointer;
      transition: background 0.2s, transform 0.15s;
      letter-spacing: 0.02em;
    }
    .btn-primary:hover { background: var(--teal-dark); transform: translateY(-1px); }
    .btn-primary:active { transform: translateY(0); }
 
    /* ── PAGE 2: ORDER ── */
    .order-wrapper {
      max-width: 860px;
      margin: 2.5rem auto;
      padding: 0 1.5rem 3rem;
    }
 
    .order-grid {
      display: grid;
      grid-template-columns: 1fr 340px;
      gap: 1.5rem;
      align-items: start;
    }
 
    .order-table-card { grid-column: 1; }
    .order-sidebar    { grid-column: 2; display: flex; flex-direction: column; gap: 1.2rem; }
 
    .section-title {
      font-family: 'Cormorant Garamond', serif;
      font-size: 1.35rem;
      font-weight: 700;
      color: var(--teal-dark);
      margin-bottom: 1.2rem;
      display: flex;
      align-items: center;
      gap: 10px;
    }
    .section-title::after {
      content: '';
      flex: 1;
      height: 1px;
      background: var(--border);
    }
 
    .medicine-table-scroll {
      width: 100%;
      overflow-x: auto;
      -webkit-overflow-scrolling: touch;
    }
    .medicine-table {
      width: 100%;
      min-width: 520px; /* below this, the columns get too cramped to read —
                            the wrapper above scrolls horizontally instead */
      border-collapse: collapse;
      table-layout: fixed; /* makes column widths predictable so the
                               medicine-name input can reliably fill its
                               column instead of shrinking to its default
                               browser width */
    }
    .medicine-table thead th {
      background: var(--teal);
      color: white;
      padding: 11px 14px;
      font-size: 0.82rem;
      font-weight: 600;
      text-align: left;
      letter-spacing: 0.04em;
      text-transform: uppercase;
    }
    .medicine-table thead th:first-child { border-radius: 8px 0 0 8px; }
    .medicine-table thead th:last-child  { border-radius: 0 8px 8px 0; }
    .medicine-table tbody tr:nth-child(odd)  { background: var(--cream); }
    .medicine-table tbody tr:nth-child(even) { background: white; }
    .medicine-table tbody tr:hover { background: var(--teal-light); }
    .medicine-table td {
      padding: 7px 10px;
    }
    .medicine-table td:first-child { width: 42px; text-align: center; color: var(--text-muted); font-size: 0.82rem; font-weight: 500; }
    .medicine-table td:last-child  { width: 110px; }
 
    .medicine-table input[type="text"] {
      width: 100%; /* fill the (now predictable, thanks to table-layout:fixed)
                      column width instead of the browser's default ~20-char
                      input size — this is what was cutting off long names */
      padding: 8px 10px;
      font-size: 0.9rem;
      border-radius: 6px;
      border: 1.5px solid transparent;
      background: transparent;
    }
    .medicine-table input[type="text"]:focus {
      background: white;
      border-color: var(--teal-mid);
    }
 
    .qty-input {
      width: 90px !important;
      text-align: center;
    }

    .medicine-table td.unit-cell { width: 90px; text-align: center; color: var(--text-muted); font-size: 0.82rem; }
    .add-medicine-btn {
      margin-top: 14px;
      display: inline-flex;
      align-items: center;
      gap: 6px;
    }
    .med-unit-value { display: inline-block; padding: 3px 8px; }

    /* Medicine-name cell needs to be a positioning context for the
       suggestions dropdown, and needs to sit above the rows below it
       when the dropdown is open. */
    .medicine-table td.med-name-cell { position: relative; overflow: visible; }
    .med-name-wrap { position: relative; }

    .med-suggestions {
      display: none;
      position: absolute;
      top: calc(100% + 4px);
      left: 0;
      right: 0;
      background: #fff;
      border: 1px solid var(--border);
      border-radius: 8px;
      box-shadow: 0 10px 26px rgba(8,80,65,0.18);
      max-height: 240px;
      overflow-y: auto;
      z-index: 60;
      text-align: left;
    }
    .med-suggestions.show { display: block; }
    .med-suggestion-item {
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 10px;
      padding: 8px 10px;
      font-size: 0.83rem;
      cursor: pointer;
      border-bottom: 1px solid var(--border);
    }
    .med-suggestion-item:last-child { border-bottom: none; }
    .med-suggestion-item:hover,
    .med-suggestion-item.active { background: var(--teal-light); }
    .med-suggestion-item .sugg-name { color: var(--text); flex: 1; }
    .med-suggestion-item .sugg-price { color: var(--teal-dark); font-weight: 600; white-space: nowrap; font-size: 0.8rem; }
    .med-suggestion-empty {
      padding: 9px 10px;
      font-size: 0.82rem;
      color: var(--text-muted);
    }

    /* Price field turns teal + slightly bold once it's been auto-filled
       from the stock list, so it's visually clear where the number came
       from — still editable in case the shop wants to override it. */
    .price-input.auto-filled {
      color: var(--teal-dark);
      font-weight: 600;
    }

    /* ══════════════ WHATSAPP FLOATING CTA + POINTER INDICATOR ══════════════ */
    .whatsapp-cta-wrap {
      position: fixed;
      bottom: 20px;
      right: 20px;
      z-index: 999; /* stays just under Asha's widget (1001) in case they ever line up */
      display: flex;
      align-items: center;
      gap: 8px;
    }
    /* Simple, elegant pointer toward the WhatsApp button — text + a small
       arrow that nudges side-to-side to draw the eye, without being flashy. */
    .whatsapp-pointer {
      display: flex;
      align-items: center;
      gap: 6px;
      color: #128c4a;
      font-weight: 600;
      font-size: 0.82rem;
      white-space: nowrap;
    }
    .whatsapp-pointer-arrow {
      display: inline-block;
      font-size: 1.1rem;
      line-height: 1;
      animation: whatsappArrowNudge 1.4s ease-in-out infinite;
    }
    .whatsapp-pointer-icon {
      width: 15px;
      height: 15px;
      border-radius: 50%;
      flex-shrink: 0;
    }
    @keyframes whatsappArrowNudge {
      0%, 100% { transform: translateX(0); }
      50% { transform: translateX(5px); }
    }
    .whatsapp-cta-btn {
      display: flex;
      align-items: center;
      justify-content: center;
      background-color: #25D366;
      width: 60px;
      height: 60px;
      padding: 0;
      border-radius: 50%;
      color: #fff;
      text-decoration: none;
      flex-shrink: 0;
      box-shadow: 0 4px 12px rgba(0,0,0,0.3);
    }
    .whatsapp-cta-btn img {
      width: 30px;
      height: 30px;
    }
    /* On narrow screens, hide the pointer text so nothing crowds the
       corner or pushes other content out of place. The button itself
       stays the same icon-only circle at every screen size. */
    @media (max-width: 600px) {
      .whatsapp-pointer { display: none; }
    }

    /* ══════════════ ASHA — AI NURSE ASSISTANT WIDGET ══════════════ */
    #asha-widget {
      position: fixed;
      right: 20px;
      bottom: 96px; /* sits directly above the 60px WhatsApp button + its 20px gap */
      z-index: 1001;
      font-family: 'DM Sans', sans-serif;
    }
    #asha-widget.hidden { display: none; }

    #asha-bubble {
      display: flex;
      align-items: center;
      gap: 8px;
      background: linear-gradient(135deg, var(--teal-mid), var(--teal-dark));
      color: #fff;
      border: none;
      border-radius: 999px;
      padding: 10px 18px 10px 10px;
      box-shadow: 0 6px 18px rgba(8,80,65,0.35);
      cursor: pointer;
      font-family: inherit;
      font-weight: 600;
      font-size: 0.88rem;
    }
    #asha-bubble .asha-bubble-avatar {
      width: 34px;
      height: 34px;
      border-radius: 50%;
      background: rgba(255,255,255,0.18);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.1rem;
      animation: ashaBubbleFloat 3s ease-in-out infinite;
    }
    @keyframes ashaBubbleFloat {
      0%, 100% { transform: translateY(0); }
      50% { transform: translateY(-3px); }
    }
    #asha-widget.open #asha-bubble { display: none; }

    #asha-panel {
      display: none;
      flex-direction: column;
      width: min(360px, calc(100vw - 40px));
      height: min(560px, calc(100vh - 140px));
      background: #fff;
      border-radius: var(--radius);
      box-shadow: 0 12px 40px rgba(8,80,65,0.28);
      overflow: hidden;
      border: 1px solid var(--border);
      position: absolute;
      right: 0;
      bottom: 0;
      animation: ashaPanelIn 0.25s ease;
    }
    @keyframes ashaPanelIn {
      from { opacity: 0; transform: translateY(12px) scale(0.98); }
      to   { opacity: 1; transform: translateY(0) scale(1); }
    }
    #asha-widget.open #asha-panel { display: flex; }

    .asha-header {
      position: relative;
      background: linear-gradient(135deg, #052e26 0%, var(--teal-dark) 55%, var(--teal) 100%);
      color: #fff;
      padding: 16px 18px 14px;
      flex-shrink: 0;
    }
    .asha-close {
      position: absolute;
      top: 10px;
      right: 12px;
      background: rgba(255,255,255,0.14);
      border: none;
      color: #fff;
      width: 26px;
      height: 26px;
      border-radius: 50%;
      cursor: pointer;
      font-size: 0.8rem;
      line-height: 1;
    }
    .asha-header-scene {
      display: flex;
      align-items: flex-end;
      justify-content: center;
      gap: 22px;
      height: 54px;
      margin-bottom: 6px;
    }
    .asha-char { position: relative; }
    .asha-char-emoji { font-size: 2.1rem; display: inline-block; }
    .asha-char-pharmacist .asha-char-emoji {
      animation: ashaFloat 2.6s ease-in-out infinite;
    }
    .asha-char-wave {
      position: absolute;
      top: -6px;
      right: -14px;
      font-size: 1.1rem;
      transform-origin: 70% 70%;
      animation: ashaWave 1.8s ease-in-out infinite;
    }
    .asha-char-rider .asha-char-emoji {
      display: inline-block;
      animation: ashaScooter 2.2s ease-in-out infinite;
    }
    @keyframes ashaFloat {
      0%, 100% { transform: translateY(0); }
      50% { transform: translateY(-5px); }
    }
    @keyframes ashaWave {
      0%, 100% { transform: rotate(0deg); }
      20% { transform: rotate(18deg); }
      40% { transform: rotate(-8deg); }
      60% { transform: rotate(14deg); }
      80% { transform: rotate(0deg); }
    }
    @keyframes ashaScooter {
      0%, 100% { transform: translateX(0) translateY(0); }
      50% { transform: translateX(3px) translateY(-2px); }
    }
    .asha-header-text { text-align: center; }
    .asha-header-title {
      font-family: 'Cormorant Garamond', serif;
      font-weight: 700;
      font-size: 1.25rem;
      line-height: 1.1;
    }
    .asha-header-subtitle {
      font-size: 0.74rem;
      letter-spacing: 0.04em;
      text-transform: uppercase;
      color: rgba(255,255,255,0.75);
      margin-top: 2px;
    }

    .asha-messages {
      flex: 1;
      overflow-y: auto;
      padding: 14px;
      display: flex;
      flex-direction: column;
      gap: 10px;
      background: var(--cream);
    }
    .asha-msg { max-width: 84%; display: flex; flex-direction: column; }
    .asha-msg.user { align-self: flex-end; align-items: flex-end; }
    .asha-msg.bot  { align-self: flex-start; align-items: flex-start; }
    .asha-msg-bubble {
      padding: 9px 12px;
      border-radius: 14px;
      font-size: 0.87rem;
      line-height: 1.5;
      white-space: pre-wrap;
    }
    .asha-msg.user .asha-msg-bubble {
      background: var(--teal);
      color: #fff;
      border-bottom-right-radius: 4px;
    }
    .asha-msg.bot .asha-msg-bubble {
      background: #fff;
      color: var(--text);
      border: 1px solid var(--border);
      border-bottom-left-radius: 4px;
    }
    .asha-msg-time {
      font-size: 0.68rem;
      color: var(--text-muted);
      margin-top: 3px;
      padding: 0 4px;
    }
    .asha-typing .asha-msg-bubble {
      display: flex;
      gap: 4px;
      align-items: center;
      padding: 11px 14px;
    }
    .asha-typing-dot {
      width: 6px; height: 6px; border-radius: 50%;
      background: var(--teal-mid);
      animation: ashaTypingDot 1s infinite ease-in-out;
    }
    .asha-typing-dot:nth-child(2) { animation-delay: 0.15s; }
    .asha-typing-dot:nth-child(3) { animation-delay: 0.3s; }
    @keyframes ashaTypingDot {
      0%, 60%, 100% { opacity: 0.3; transform: translateY(0); }
      30% { opacity: 1; transform: translateY(-3px); }
    }

    .asha-handoff-actions {
      display: flex;
      flex-wrap: wrap;
      gap: 6px;
      margin-top: 8px;
    }
    .asha-handoff-actions a, .asha-handoff-actions button {
      font-family: inherit;
      font-size: 0.78rem;
      font-weight: 600;
      padding: 7px 11px;
      border-radius: 8px;
      border: 1.5px solid var(--teal);
      background: #fff;
      color: var(--teal-dark);
      text-decoration: none;
      cursor: pointer;
    }
    .asha-handoff-actions a.whatsapp { border-color: #25D366; color: #128c4a; }

    .asha-quick-chips-toggle {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 8px;
      padding: 7px 14px;
      border-top: 1px solid var(--border);
      flex-shrink: 0;
      font-size: 0.72rem;
      font-weight: 600;
      color: var(--text-muted);
      letter-spacing: 0.02em;
      cursor: pointer;
      user-select: none;
    }
    .asha-quick-chips-toggle:hover { color: var(--teal-dark); }
    .asha-quick-chips-arrow {
      font-size: 0.65rem;
      transition: transform 0.25s ease;
      display: inline-block;
    }
    .asha-quick-chips-toggle.collapsed .asha-quick-chips-arrow { transform: rotate(-90deg); }
    .asha-quick-chips-wrap {
      overflow: hidden;
      flex-shrink: 0;
      transition: max-height 0.3s ease, opacity 0.25s ease;
      opacity: 1;
    }
    .asha-quick-chips-wrap.collapsed { opacity: 0; }
    .asha-quick-chips {
      display: flex;
      flex-wrap: wrap;
      gap: 6px;
      padding: 8px 12px;
      flex-shrink: 0;
    }
    .asha-chip {
      font-family: inherit;
      font-size: 0.76rem;
      padding: 6px 10px;
      border-radius: 999px;
      border: 1px solid var(--border);
      background: var(--cream);
      color: var(--text);
      cursor: pointer;
      white-space: nowrap;
    }
    .asha-chip:hover { background: var(--teal-light); border-color: var(--teal-mid); }

    /* Inline action buttons inside a bot message bubble (used by the
       prescription-upload-via-chat card). */
    .asha-action-row {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
      margin-top: 6px;
      max-width: 82%;
    }
    .asha-action-btn {
      font-family: inherit;
      font-size: 0.8rem;
      font-weight: 600;
      padding: 8px 14px;
      border-radius: 999px;
      border: 1.5px solid var(--teal-mid);
      background: white;
      color: var(--teal-dark);
      cursor: pointer;
    }
    .asha-action-btn:hover { background: var(--teal-light); }

    .asha-disclaimer {
      font-size: 0.68rem;
      color: var(--text-muted);
      padding: 6px 14px;
      border-top: 1px solid var(--border);
      flex-shrink: 0;
      line-height: 1.4;
    }

    @media (max-width: 480px) {
      #asha-widget { right: 12px; bottom: 88px; }
      #asha-panel { width: calc(100vw - 24px); height: min(72vh, 560px); }
    }

    .sidebar-contact {
      background: var(--teal-dark);
      color: white;
      border-radius: var(--radius);
      padding: 1.4rem 1.5rem;
      font-size: 0.88rem;
    }
    .sidebar-contact h4 {
      font-family: 'Cormorant Garamond', serif;
      font-size: 1.1rem;
      font-weight: 600;
      margin-bottom: 0.8rem;
      color: rgba(255,255,255,0.9);
    }
    .contact-item {
      display: flex;
      align-items: center;
      gap: 10px;
      margin-bottom: 0.6rem;
      color: rgba(255,255,255,0.8);
    }
    .contact-item svg { flex-shrink: 0; opacity: 0.7; }
    .contact-item a { color: var(--teal-mid); text-decoration: none; word-break: break-all; }
    .contact-item a:hover { text-decoration: underline; }
 
    .doctors-card {
      grid-column: 1 / -1;
      margin-top: 1rem;
    }
    .doctors-card .section-title { margin-top: 0; }
 
    .doctors-table {
      width: 100%;
      border-collapse: separate;
      border-spacing: 0;
      border-radius: var(--radius-sm);
      overflow: hidden;
      border: 1px solid var(--border);
    }
    .doctors-table thead th {
      background: var(--gold-light);
      color: var(--gold);
      padding: 11px 16px;
      text-align: left;
      font-size: 0.8rem;
      text-transform: uppercase;
      letter-spacing: 0.05em;
      font-weight: 600;
      border-bottom: 1px solid rgba(186,117,23,0.2);
    }
    .doctors-table tbody tr:nth-child(odd)  { background: white; }
    .doctors-table tbody tr:nth-child(even) { background: var(--cream); }
    .doctors-table tbody tr:hover { background: var(--gold-light); transition: background 0.2s; }
    .doctors-table td {
      padding: 12px 16px;
      font-size: 0.9rem;
      border-bottom: 1px solid var(--border);
    }
    .doctors-table tbody tr:last-child td { border-bottom: none; }
    .badge {
      display: inline-block;
      padding: 3px 10px;
      border-radius: 20px;
      font-size: 0.75rem;
      font-weight: 500;
    }
    .badge-teal { background: var(--teal-light); color: var(--teal-dark); }
    .badge-gold { background: var(--gold-light); color: #6b4007; }
 
    .order-actions {
      grid-column: 1 / -1;
      display: flex;
      justify-content: flex-end;
      gap: 1rem;
      margin-top: 1rem;
    }
    .btn-secondary {
      padding: 13px 28px;
      background: white;
      color: var(--teal);
      border: 1.5px solid var(--border);
      border-radius: var(--radius-sm);
      font-family: 'DM Sans', sans-serif;
      font-size: 0.95rem;
      font-weight: 500;
      cursor: pointer;
      transition: all 0.2s;
    }
    .btn-secondary:hover { background: var(--cream-dark); }
 
    /* ── PAGE 3: SUMMARY ── */
    .summary-wrapper {
      max-width: 760px;
      margin: 2.5rem auto;
      padding: 0 1.5rem 4rem;
    }
    .summary-header {
      text-align: center;
      padding: 2rem 2rem 1.5rem;
      background: linear-gradient(135deg, var(--teal-dark) 0%, var(--teal) 100%);
      border-radius: var(--radius) var(--radius) 0 0;
      color: white;
    }
    .summary-header h1 {
      font-family: 'Cormorant Garamond', serif;
      font-size: 2rem;
      font-weight: 700;
    }
    .summary-header p { font-size: 0.88rem; opacity: 0.8; margin-top: 4px; }
 
    .order-id {
      display: inline-block;
      margin-top: 12px;
      padding: 4px 16px;
      background: rgba(255,255,255,0.15);
      border-radius: 20px;
      font-size: 0.82rem;
      letter-spacing: 0.08em;
    }
 
    .summary-body {
      background: white;
      border: 1px solid var(--border);
      border-top: none;
      border-radius: 0 0 var(--radius) var(--radius);
      overflow: hidden;
    }
    .summary-section {
      padding: 1.6rem 2rem;
      border-bottom: 1px solid var(--border);
    }
    .summary-section:last-child { border-bottom: none; }
    .summary-section-title {
      font-size: 0.75rem;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.08em;
      color: var(--teal-mid);
      margin-bottom: 1rem;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    .summary-section-title svg { width: 14px; height: 14px; }
 
    .info-grid {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 1rem;
    }
    .info-item {}
    .info-item .info-label {
      font-size: 0.78rem;
      color: var(--text-muted);
      margin-bottom: 2px;
    }
    .info-item .info-value {
      font-size: 0.95rem;
      font-weight: 500;
      color: var(--text);
    }
 
    .summary-med-table {
      width: 100%;
      border-collapse: collapse;
    }
    .summary-med-table thead th {
      background: var(--cream-dark);
      padding: 9px 14px;
      text-align: left;
      font-size: 0.78rem;
      text-transform: uppercase;
      letter-spacing: 0.05em;
      color: var(--text-muted);
      font-weight: 600;
    }
    .summary-med-table tbody td {
      padding: 10px 14px;
      font-size: 0.9rem;
      border-bottom: 1px solid var(--border);
      color: var(--text);
    }
    .summary-med-table tbody tr:last-child td { border-bottom: none; }
    .summary-med-table tbody tr:hover { background: var(--cream); }
 
    .summary-actions {
      display: flex;
      gap: 1rem;
      padding: 1.5rem 2rem;
      background: var(--cream);
      border-top: 1px solid var(--border);
      justify-content: flex-end;
    }
    .btn-print {
      padding: 12px 28px;
      background: var(--gold);
      color: white;
      border: none;
      border-radius: var(--radius-sm);
      font-family: 'DM Sans', sans-serif;
      font-size: 0.95rem;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.2s;
    }
    .btn-print:hover { background: #9a620d; }
 
    /* ── FOOTER ── */
    footer {
      background: var(--teal-dark);
      color: rgba(255,255,255,0.55);
      text-align: center;
      padding: 1rem;
      font-size: 0.78rem;
    }
    footer strong { color: rgba(255,255,255,0.85); }
 
    /* ── RESPONSIVE ── */
    @media (max-width: 680px) {
      .step-bar { display: none; }
      .order-grid { grid-template-columns: 1fr; }
      .order-sidebar { grid-column: 1; }
      .appointment-doctors-grid { grid-template-columns: 1fr; }
      .info-grid { grid-template-columns: 1fr; }
      header { padding: 0 1.2rem; }
      .welcome-icon { width: 52px; height: 52px; margin-bottom: 1.2rem; }
      .welcome-icon svg { width: 28px; height: 28px; }
      .welcome-continue { margin-top: 1.8rem; font-size: 0.82rem; }
      .lang-switch button { padding: 5px 10px; font-size: 0.72rem; }
    }
 
    /* ══════════════ PRESCRIPTION UPLOAD SECTION ══════════════ */
    .prescription-card {
      grid-column: 1 / -1;
      margin-top: 1rem;
    }
    .prescription-card .section-title { margin-top: 0; }
    .prescription-hint {
      font-size: 0.85rem;
      color: var(--text-muted);
      margin-bottom: 1rem;
      line-height: 1.6;
    }
    .prescription-required-note {
      display: none;
      align-items: center;
      gap: 8px;
      background: var(--gold-light);
      color: #6b4007;
      border: 1px solid rgba(186,117,23,0.3);
      border-radius: var(--radius-sm);
      padding: 10px 14px;
      font-size: 0.85rem;
      margin-bottom: 1rem;
    }
    .prescription-required-note.show { display: flex; }
    .upload-dropzone {
      border: 2px dashed var(--border);
      border-radius: var(--radius-sm);
      background: var(--cream);
      padding: 2rem 1.5rem;
      text-align: center;
      cursor: pointer;
      transition: border-color 0.2s, background 0.2s;
      position: relative;
    }
    .upload-dropzone:hover,
    .upload-dropzone.dragover {
      border-color: var(--teal-mid);
      background: var(--teal-light);
    }
    .upload-dropzone.error {
      border-color: var(--red);
      background: var(--red-light);
    }
    .upload-dropzone input[type="file"] {
      position: absolute;
      inset: 0;
      opacity: 0;
      cursor: pointer;
    }
    .upload-dropzone svg { width: 30px; height: 30px; margin-bottom: 8px; color: var(--teal-mid); }

    /* ══════════════ UPI PAYMENT (Order Summary page) ══════════════ */
    #upi-payment-section { background: var(--teal-light); }
    #upi-payment-section .summary-section-title { color: var(--teal-dark); }
    .upi-payment-inner {
      display: flex;
      flex-wrap: wrap;
      gap: 1.4rem;
      align-items: flex-start;
    }
    .upi-qr-box {
      background: white;
      border: 1.5px solid var(--border);
      border-radius: var(--radius-sm);
      padding: 12px;
      text-align: center;
      flex-shrink: 0;
    }
    .upi-qr-box img {
      width: 168px;
      height: 168px;
      object-fit: contain;
      display: block;
      border-radius: 4px;
    }
    .upi-qr-box .upi-id-text {
      margin-top: 8px;
      font-size: 0.8rem;
      color: var(--text-muted);
      word-break: break-all;
    }
    .upi-payment-details { flex: 1; min-width: 220px; }
    .upi-payment-hint {
      font-size: 0.85rem;
      color: var(--text);
      line-height: 1.6;
      margin-bottom: 0.9rem;
    }
    .upi-ref-input {
      width: 100%;
      padding: 11px 14px;
      border: 1.5px solid var(--border);
      border-radius: var(--radius-sm);
      font-family: 'DM Sans', sans-serif;
      font-size: 0.92rem;
      color: var(--text);
      background: white;
      outline: none;
    }
    .upi-ref-input:focus { border-color: var(--teal-mid); }
    .upi-ref-label {
      display: block;
      font-size: 0.82rem;
      font-weight: 600;
      color: var(--teal-dark);
      margin-bottom: 6px;
    }
    @media (max-width: 480px) {
      .upi-qr-box img { width: 140px; height: 140px; }
    }
    .upload-dropzone-text {
      font-size: 0.92rem;
      font-weight: 500;
      color: var(--teal-dark);
    }
    .upload-dropzone-sub {
      font-size: 0.78rem;
      color: var(--text-muted);
      margin-top: 4px;
    }

    /* "Scan Your Prescription" — camera entry point */
    .scan-prescription-row {
      display: flex;
      align-items: center;
      gap: 12px;
      margin-top: 0.9rem;
    }
    .scan-divider-text {
      font-size: 0.78rem;
      color: var(--text-muted);
      text-transform: uppercase;
      letter-spacing: 0.05em;
    }
    .scan-prescription-btn {
      flex: 1;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      padding: 11px 16px;
      border-radius: var(--radius);
      border: 1.5px dashed var(--teal-mid);
      background: var(--teal-light);
      color: var(--teal-dark);
      font-family: inherit;
      font-weight: 600;
      font-size: 0.88rem;
      cursor: pointer;
      transition: background 0.15s ease, border-color 0.15s ease;
    }
    .scan-prescription-btn:hover {
      background: #d7f0e6;
      border-color: var(--teal-dark);
    }

    /* Desktop webcam capture modal */
    .webcam-modal-backdrop {
      display: none;
      position: fixed;
      inset: 0;
      background: rgba(8, 20, 17, 0.6);
      z-index: 2000;
      align-items: center;
      justify-content: center;
      padding: 20px;
    }
    .webcam-modal-backdrop.show { display: flex; }
    .webcam-modal {
      background: #fff;
      border-radius: var(--radius);
      padding: 1.4rem;
      width: min(480px, 100%);
      box-shadow: 0 20px 60px rgba(0,0,0,0.35);
    }
    .webcam-modal-title {
      font-family: 'Cormorant Garamond', serif;
      font-size: 1.3rem;
      font-weight: 700;
      color: var(--teal-dark);
      margin-bottom: 0.9rem;
    }
    .webcam-video-wrap {
      position: relative;
      width: 100%;
      aspect-ratio: 4 / 3;
      background: #0a0f0d;
      border-radius: 10px;
      overflow: hidden;
      margin-bottom: 1rem;
    }
    .webcam-video-wrap video,
    .webcam-video-wrap img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      display: block;
    }
    .webcam-modal-actions {
      display: flex;
      gap: 10px;
      flex-wrap: wrap;
      justify-content: flex-end;
    }

    .upload-preview {
      display: none;
      align-items: center;
      gap: 14px;
      margin-top: 1rem;
      padding: 12px 14px;
      background: var(--cream);
      border: 1px solid var(--border);
      border-radius: var(--radius-sm);
    }
    .upload-preview.show { display: flex; }
    .upload-preview img {
      width: 56px;
      height: 56px;
      object-fit: cover;
      border-radius: 6px;
      border: 1px solid var(--border);
      flex-shrink: 0;
    }
    .upload-preview .file-icon {
      width: 56px;
      height: 56px;
      border-radius: 6px;
      background: var(--teal-light);
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
      color: var(--teal);
      font-size: 1.6rem;
      font-weight: 700;
    }
    .upload-preview-info { flex: 1; min-width: 0; }
    .upload-preview-name {
      font-size: 0.88rem;
      font-weight: 600;
      color: var(--text);
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }
    .upload-preview-size { font-size: 0.78rem; color: var(--text-muted); margin-top: 2px; }
    .upload-remove-btn {
      background: none;
      border: none;
      color: var(--red);
      font-size: 0.82rem;
      font-weight: 600;
      cursor: pointer;
      padding: 6px 10px;
      border-radius: 6px;
      flex-shrink: 0;
    }
    .upload-remove-btn:hover { background: var(--red-light); }

    /* ══════════════ APPOINTMENT (left) + DOCTORS (right) LAYOUT ══════════════ */
    .appointment-doctors-grid {
      grid-column: 1 / -1;
      display: grid;
      grid-template-columns: 300px 1fr;
      gap: 1.5rem;
      align-items: start;
      margin-top: 1rem;
    }
    .appointment-doctors-grid .sidebar-contact { margin: 0; height: 100%; }
    .appointment-doctors-grid .doctors-card { grid-column: auto; margin-top: 0; }

    /* ══════════════ PRICE / PAYMENT FIELDS ══════════════ */
    .medicine-table td.price-cell { width: 100px; }
    .price-input { width: 90px !important; text-align: right; }
    .payment-card .checkbox-group { margin: 0.5rem 0; }
    .payment-card .checkbox-group:last-child { margin-bottom: 0; }

    /* ══════════════ PAGE 4: ORDER CONFIRMATION ══════════════ */
    .confirm-wrapper {
      max-width: 820px;
      margin: 2.5rem auto;
      padding: 0 1.5rem 4rem;
    }
    .confirm-hero {
      text-align: center;
      padding: 2.4rem 2rem;
      background: linear-gradient(135deg, var(--teal-dark) 0%, var(--teal) 100%);
      border-radius: var(--radius) var(--radius) 0 0;
      color: white;
    }
    .confirm-hero .check-badge {
      width: 60px;
      height: 60px;
      border-radius: 50%;
      background: rgba(255,255,255,0.15);
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 1rem;
    }
    .confirm-hero h1 {
      font-family: 'Cormorant Garamond', serif;
      font-size: 2rem;
      font-weight: 700;
    }
    .confirm-hero p { font-size: 0.92rem; opacity: 0.85; margin-top: 6px; }

    .confirm-body {
      background: white;
      border: 1px solid var(--border);
      border-top: none;
      overflow: hidden;
    }
    .confirm-totals {
      display: flex;
      justify-content: space-between;
      padding: 8px 0;
      font-size: 0.92rem;
      color: var(--text);
    }
    .confirm-totals.grand {
      border-top: 1px dashed var(--border);
      margin-top: 8px;
      padding-top: 12px;
      font-size: 1.05rem;
      font-weight: 700;
      color: var(--teal-dark);
    }

    /* ══════════════ LIVE DELIVERY TRACKING ══════════════ */
    .tracking-section {
      background: white;
      border: 1px solid var(--border);
      border-top: none;
      border-radius: 0 0 var(--radius) var(--radius);
      overflow: hidden;
    }
    .tracking-status-bar {
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      gap: 12px;
      padding: 1.2rem 2rem;
      background: var(--teal-light);
      border-bottom: 1px solid var(--border);
    }
    .tracking-status-live {
      display: flex;
      align-items: center;
      gap: 10px;
      font-size: 0.95rem;
      font-weight: 600;
      color: var(--teal-dark);
    }
    .live-dot {
      width: 10px;
      height: 10px;
      border-radius: 50%;
      background: #2ecc71;
      box-shadow: 0 0 0 rgba(46,204,113,0.6);
      animation: pulseDot 1.6s infinite;
    }
    @keyframes pulseDot {
      0%   { box-shadow: 0 0 0 0 rgba(46,204,113,0.55); }
      70%  { box-shadow: 0 0 0 9px rgba(46,204,113,0); }
      100% { box-shadow: 0 0 0 0 rgba(46,204,113,0); }
    }
    .tracking-meta {
      display: flex;
      gap: 22px;
      flex-wrap: wrap;
      font-size: 0.85rem;
      color: var(--teal-dark);
    }
    .tracking-meta b { font-size: 0.95rem; }

    .tracking-steps {
      display: flex;
      justify-content: space-between;
      padding: 1.4rem 2rem 0.6rem;
      position: relative;
    }
    .tracking-step {
      flex: 1;
      text-align: center;
      position: relative;
      font-size: 0.75rem;
      color: var(--text-muted);
      font-weight: 500;
    }
    .tracking-step .dot {
      width: 16px;
      height: 16px;
      border-radius: 50%;
      background: var(--cream-dark);
      border: 2px solid var(--border);
      margin: 0 auto 8px;
      transition: all 0.35s;
      position: relative;
      z-index: 2;
    }
    .tracking-step::before {
      content: '';
      position: absolute;
      top: 8px;
      left: -50%;
      width: 100%;
      height: 2px;
      background: var(--border);
      z-index: 1;
    }
    .tracking-step:first-child::before { display: none; }
    .tracking-step.done .dot { background: var(--teal-mid); border-color: var(--teal-mid); }
    .tracking-step.done::before { background: var(--teal-mid); }
    .tracking-step.active .dot {
      background: var(--gold);
      border-color: var(--gold);
      box-shadow: 0 0 0 4px var(--gold-light);
    }
    .tracking-step.active { color: var(--teal-dark); font-weight: 700; }

    #delivery-map {
      width: 100%;
      height: 320px;
      border-top: 1px solid var(--border);
      border-bottom: 1px solid var(--border);
    }
    .tracking-note {
      display: flex;
      gap: 10px;
      align-items: flex-start;
      padding: 1rem 2rem;
      font-size: 0.8rem;
      color: var(--text-muted);
      line-height: 1.6;
    }

    @media print {
      header, footer, .order-actions, .summary-actions, .step-bar,
      #delivery-map, .tracking-section, .confirm-actions { display: none !important; }
      body { background: white; }
      .summary-wrapper, .confirm-wrapper { margin: 0; max-width: 100%; }
    }

    /* ══════════════ WELCOME SCREEN — TAGLINE + BOTTOM BLESSING ══════════════ */
    .welcome-tagline {
      font-family: 'DM Sans', sans-serif;
      font-weight: 500;
      color: rgba(255,255,255,0.82);
      font-size: clamp(0.95rem, 1.9vw, 1.25rem);
      letter-spacing: 0.03em;
      margin-top: 0.9rem;
      opacity: 0;
      transition: opacity 0.7s ease, transform 0.7s ease;
      transform: translateY(6px);
    }
    .welcome-tagline.ready {
      opacity: 0.92;
      transform: translateY(0);
    }
    .welcome-bottom-msg {
      position: absolute;
      bottom: 2.4rem;
      left: 50%;
      transform: translateX(-50%) translateY(6px);
      width: 100%;
      max-width: 640px;
      padding: 0 1.5rem;
      font-family: 'Noto Sans Devanagari', 'Cormorant Garamond', serif;
      font-weight: 700;
      color: var(--gold-light);
      font-size: clamp(0.9rem, 2.1vw, 1.15rem);
      letter-spacing: 0.01em;
      opacity: 0;
      transition: opacity 0.8s ease, transform 0.8s ease;
    }
    .welcome-bottom-msg.ready {
      opacity: 0.95;
      transform: translateX(-50%) translateY(0);
    }
    @media (max-width: 680px) {
      .welcome-bottom-msg { bottom: 1.4rem; }
    }
  </style>


</head>
<body>

<!-- ══════════════ WELCOME (LANDING) SCREEN ══════════════ -->
<div id="welcome-screen">
  <div class="welcome-icon">
    <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
      <path d="M12 3v18M3 12h18" stroke="#ffffff" stroke-width="2.5" stroke-linecap="round"/>
      <rect x="7" y="7" width="10" height="10" rx="2" stroke="#ffffff" stroke-width="1.5" fill="none"/>
    </svg>
  </div>
  <div class="welcome-text" id="welcome-text"><span class="cursor"></span></div>
  <div class="welcome-tagline" id="welcome-tagline">Care That Reaches Your Doorstep</div>
  <div class="welcome-continue" id="welcome-continue">Click anywhere to continue</div>
  <div class="welcome-bottom-msg" id="welcome-bottom-msg">आप हमेशा स्वस्थ रहें, यही हमारी कामना है।</div>
</div>

<!-- WhatsApp Floating CTA + Pointer Indicator -->
<div class="whatsapp-cta-wrap">
  <div class="whatsapp-pointer">
    <span class="whatsapp-pointer-text" data-i18n="whatsappPointerText">Order Directly Here</span>
    <img src="https://cdn-icons-png.flaticon.com/512/733/733585.png" width="15" alt="" class="whatsapp-pointer-icon">
    <span class="whatsapp-pointer-arrow" aria-hidden="true">➜</span>
  </div>
  <a href="https://wa.me/<?php echo htmlspecialchars(SHOP_WHATSAPP); ?>?text=Hello%20Gupta%20Medical%20Store,%20I%20want%20to%20order%20medicines"
     target="_blank" rel="noopener" class="whatsapp-cta-btn" aria-label="Order Directly via WhatsApp" title="Order Directly via WhatsApp">
    <img src="https://cdn-icons-png.flaticon.com/512/733/733585.png" width="30" alt="WhatsApp">
  </a>
</div>

<!-- ══════════════ MAIN SITE (hidden until welcome screen is dismissed) ══════════════ -->
<div id="site-content">

<!-- ADDED: Admin PIN login modal — shown instead of continuing to Place
     Order when the Registration email matches ADMIN_EMAIL. Reuses the
     webcam-modal-backdrop styling for a consistent look. -->
<div class="webcam-modal-backdrop" id="admin-pin-modal-backdrop">
  <div class="webcam-modal">
    <div class="webcam-modal-title">Shop Owner Login</div>
    <p style="color:var(--text-muted);font-size:0.9rem;margin-bottom:1rem;">
      Enter your admin PIN to continue to the order management panel.
    </p>
    <div class="form-group full">
      <label for="admin-pin">PIN</label>
      <input type="password" id="admin-pin" autocomplete="off" inputmode="numeric" placeholder="••••••" style="width:100%;padding:10px;border:1px solid var(--border);border-radius:var(--radius-sm);font-size:1rem;">
      <div class="error-msg" id="err-admin-pin">Incorrect PIN. Access denied.</div>
    </div>
    <div class="webcam-modal-actions">
      <button type="button" class="btn-secondary" onclick="closeAdminPinModal()">Cancel</button>
      <button type="button" class="btn-primary" id="admin-pin-submit-btn" onclick="submitAdminPin()">Login</button>
    </div>
  </div>
</div>

<!-- ══════════════ ASHA — AI NURSE ASSISTANT (floating chat widget) ══════════════
     Only shown on the "Place Order" and "Order Summary" steps (toggled from
     goTo() via updateAshaVisibility()). Sits above the WhatsApp button so
     neither one covers the other. -->
<div id="asha-widget" class="hidden">

  <!-- Minimized bubble -->
  <button type="button" id="asha-bubble" onclick="toggleAsha()" aria-label="Ask Asha">
    <span class="asha-bubble-avatar">👩‍⚕️</span>
    <span class="asha-bubble-label" data-i18n="ashaBubbleLabel">Ask Asha 👩‍⚕️</span>
  </button>

  <!-- Expanded chat panel -->
  <div id="asha-panel" role="dialog" aria-label="Asha, AI Nurse Assistant">
    <div class="asha-header">
      <button type="button" class="asha-close" onclick="toggleAsha(false)" aria-label="Close chat" data-i18n-aria="ashaCloseLabel">✕</button>
      <div class="asha-header-scene">
        <div class="asha-char asha-char-pharmacist">
          <span class="asha-char-emoji">👩‍⚕️</span>
          <span class="asha-char-wave">👋</span>
        </div>
        <div class="asha-char asha-char-rider">
          <span class="asha-char-emoji">🛵</span>
        </div>
      </div>
      <div class="asha-header-text">
        <div class="asha-header-title" data-i18n="ashaHeaderTitle">Asha</div>
        <div class="asha-header-subtitle" data-i18n="ashaHeaderSubtitle">AI Nurse Assistant</div>
      </div>
    </div>

    <div class="asha-messages" id="asha-messages"></div>

    <div class="asha-quick-chips-toggle" id="asha-quick-chips-toggle" role="button" tabindex="0" aria-expanded="true" aria-controls="asha-quick-chips-wrap">
      <span data-i18n="ashaQuickChipsLabel">Quick options</span>
      <span class="asha-quick-chips-arrow">▾</span>
    </div>
    <div class="asha-quick-chips-wrap" id="asha-quick-chips-wrap">
      <div class="asha-quick-chips" id="asha-quick-chips"></div>
    </div>

    <div class="asha-disclaimer" data-i18n="ashaDisclaimer">
      Asha provides general educational information and does not replace advice from a licensed doctor or pharmacist.
    </div>

  </div>
</div>

<!-- ══════════════ HEADER ══════════════ -->
<header>
  <div class="logo">
    <div class="logo-icon">
      <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M12 3v18M3 12h18" stroke="#0f6e56" stroke-width="2.5" stroke-linecap="round"/>
        <rect x="7" y="7" width="10" height="10" rx="2" stroke="#0f6e56" stroke-width="1.5" fill="none"/>
      </svg>
    </div>
    <div class="logo-text">
      <span data-i18n="brand">Gupta Medical Stores</span>
      <span data-i18n="brandTagline">Trusted Health, Since 1988</span>
    </div>
  </div>

  <div class="header-right">
    <div class="step-bar">
      <div class="step active" id="step1" onclick="goToStep(1)"><div class="step-num">1</div><span data-i18n="stepReg">Registration</span></div>
      <div class="step-sep"></div>
      <div class="step" id="step2" onclick="goToStep(2)"><div class="step-num">2</div><span data-i18n="stepOrder">Place Order</span></div>
      <div class="step-sep"></div>
      <div class="step" id="step3" onclick="goToStep(3)"><div class="step-num">3</div><span data-i18n="stepSummary">Summary</span></div>
    </div>

    <!-- Language Selector (top-right corner, requirement #4) -->
    <div class="lang-switch">
      <button id="lang-en" class="active" onclick="setLanguage('en')">EN</button>
      <button id="lang-hi" onclick="setLanguage('hi')">हिन्दी</button>
    </div>
  </div>
</header>
 
 
<!-- ══════════════ PAGE 1: REGISTRATION ══════════════ -->
<div class="page active" id="page1">
  <div class="reg-wrapper">
    <div class="page-heading">
      <h1 data-i18n="regTitle">Register to Order</h1>
      <p data-i18n="regSubtitle">Create your account to place an order with us</p>
      <div class="heading-rule"><div class="heading-rule-icon"></div></div>
    </div>
 
    <div class="card">
      <div class="form-row">
        <div class="form-group">
          <label for="firstName" data-i18n="labelFirstName">First Name</label>
          <input type="text" id="firstName" />
          <div class="error-msg" id="err-firstName" data-i18n="errFirstName">First name is required.</div>
        </div>
        <div class="form-group">
          <label for="lastName" data-i18n="labelLastName">Last Name</label>
          <input type="text" id="lastName"/>
          <div class="error-msg" id="err-lastName" data-i18n="errLastName">Last name is required.</div>
        </div>
        <div class="form-group">
          <label for="gender" data-i18n="labelGender">Gender</label>
          <select id="gender">
            <option value="" data-i18n="genderSelect">— Select —</option>
            <option value="Male" data-i18n="genderMale">Male</option>
            <option value="Female" data-i18n="genderFemale">Female</option>
            <option value="Others" data-i18n="genderOthers">Others</option>
          </select>
          <div class="error-msg" id="err-gender" data-i18n="errGender">Please select a gender.</div>
        </div>
        <div class="form-group">
          <label for="phone" data-i18n="labelPhone">Whatsapp Number</label>
          <input type="tel" id="phone" placeholder="+91 XXXXX XXXXX"/>
          <div class="error-msg" id="err-phone" data-i18n="errPhone">Enter a valid 10-digit number.</div>
        </div>
        <div class="form-group">
          <label for="email" data-i18n="labelEmail">Email Address</label>
          <input type="text" id="email" placeholder="you@example.com"/>
          <div class="error-msg" id="err-email" data-i18n="errEmail">Enter a valid email address.</div>
        </div>
        <div class="form-group full">
          <label for="address" data-i18n="labelAddress">Address</label>
          <textarea id="address" placeholder="House No., Street, City, State, PIN" data-i18n-placeholder="placeholderAddress"></textarea>
          <div class="error-msg" id="err-address" data-i18n="errAddress">Address is required.</div>
        </div>
        <div class="form-group">
          <label for="pincode" data-i18n="labelPincode">PIN Code</label>
          <input type="text" id="pincode" inputmode="numeric" maxlength="6" placeholder="231001" data-i18n-placeholder="placeholderPincode"/>
          <div class="error-msg" id="err-pincode" data-i18n="errPincodeFormat">Please enter a valid 6-digit PIN code.</div>
        </div>
      </div>
 
      <div class="checkbox-group">
        <input type="checkbox" id="terms"/>
        <label for="terms"><span data-i18n="termsPre">I agree to the</span> <a href="#" data-i18n="termsLink">terms and conditions</a> <span data-i18n="termsPost">of Gupta Medical Stores</span></label>
      </div>
      <div class="error-msg" id="err-terms" data-i18n="errTerms" style="margin-top:-10px;margin-bottom:12px;">You must agree to the terms.</div>
 
      <button class="btn-primary" onclick="validateRegistration()" data-i18n="btnRegister">Register &amp; Continue →</button>
    </div>
  </div>
</div>
 
 
<!-- ══════════════ PAGE 2: ORDER ══════════════ -->
<div class="page" id="page2">
  <div class="order-wrapper">
    <div class="page-heading" style="text-align:left;margin-bottom:1.8rem;">
      <h1 style="font-family:'Cormorant Garamond',serif;font-size:2.2rem;color:var(--teal-dark);" data-i18n="orderTitle">Place Your Order</h1>
      <p style="color:var(--text-muted);margin-top:4px;" data-i18n="orderSubtitle">Fill in the medicines you need and their quantities</p>
    </div>
     <div style="text-align:center; margin-bottom:20px;">
   
  </div>
    <div class="order-grid">
 
      <!-- Medicine Table -->
      <div class="card order-table-card">
        <div class="section-title" data-i18n="medListTitle">Medicine List</div>
        <div class="medicine-table-scroll">
        <table class="medicine-table">
          <thead>
            <tr>
              <th style="width:34px;">#</th>
              <th data-i18n="colMedName">Medicine Name</th>
              <th style="width:90px;" data-i18n="colQty">Quantity</th>
              <th style="width:90px;" data-i18n="colUnit">Pack</th>
              <th style="width:100px;" data-i18n="colPrice">Price (₹)</th>
            </tr>
          </thead>
          <tbody id="medicine-rows"></tbody>
        </table>
        </div>
        <button type="button" class="btn-secondary add-medicine-btn" id="add-medicine-btn" data-i18n="btnAddMedicine">+ Add Another Medicine</button>
      </div>
 
      <!-- Sidebar -->
      <div class="order-sidebar">

        <!-- Payment Method -->
        <div class="card payment-card">
          <div class="section-title" style="font-size:1.1rem;" data-i18n="paymentTitle">Pay on Delivery</div>
          <div class="checkbox-group" style="background:var(--cream);border-color:var(--border);">
            <input type="radio" name="payment" id="payCod" value="Cash on Delivery" checked/>
            <label for="payCod" style="color:var(--text);" data-i18n="payCod">Cash on Delivery</label>
          </div>
          <div class="checkbox-group" style="background:var(--cream);border-color:var(--border);">
            <input type="radio" name="payment" id="payUpi" value="UPI"/>
            <label for="payUpi" style="color:var(--text);" data-i18n="payUpi">UPI</label>
          </div>
          </div>
      </div>
 
      <!-- Prescription Upload -->
      <div class="card prescription-card" id="prescription-card">
        <div class="section-title" data-i18n="prescriptionTitle">Upload Prescription</div>
        <p class="prescription-hint" data-i18n="prescriptionHint">
          If your order contains prescription-only medicines, please upload a clear photo or scan of a valid doctor's prescription. Accepted formats: JPG, JPEG, PNG, PDF.
        </p>
        <div class="prescription-required-note" id="prescription-required-note">
          <span>⚠</span>
          <span data-i18n="prescriptionRequiredNote">One or more medicines in your list typically require a prescription. Please upload it below to continue.</span>
        </div>
        <div class="upload-dropzone" id="upload-dropzone">
          <input type="file" id="prescription-file" accept=".jpg,.jpeg,.png,image/jpeg,image/png,application/pdf"/>
          <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 4v11m0-11l-4 4m4-4l4 4M5 17v2a2 2 0 002 2h10a2 2 0 002-2v-2" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
          <div class="upload-dropzone-text" data-i18n="uploadDropzoneText">Click or drag your prescription here</div>
          <div class="upload-dropzone-sub" data-i18n="uploadDropzoneSub">JPG, JPEG, PNG or PDF — max 5MB</div>
        </div>

        <!-- "Scan Your Prescription" — second entry point into the SAME
             upload pipeline as the dropzone above (handlePrescriptionFile()).
             On phones this opens the native camera (rear, via capture=
             "environment"); on desktop, if a webcam is available, it opens
             a small in-page capture modal; otherwise it just falls back to
             a normal file picker. See scanPrescriptionBtn's click handler
             further down for the branching logic. -->
        <div class="scan-prescription-row">
          <span class="scan-divider-text" data-i18n="orDividerText">or</span>
          <button type="button" class="scan-prescription-btn" id="scan-prescription-btn" data-i18n="scanPrescriptionBtn">📷 Scan Your Prescription</button>
          <!-- capture="environment" is what makes mobile browsers (Android
               Chrome, iOS Safari) open the native rear camera directly
               instead of a file browser. Desktops without a camera simply
               ignore `capture` and show their normal file picker. -->
          <input type="file" id="prescription-camera-input" accept="image/*" capture="environment" style="display:none;" />
        </div>

        <div class="upload-preview" id="upload-preview">
          <img id="upload-preview-img" style="display:none;" alt="Prescription preview"/>
          <div class="file-icon" id="upload-preview-icon" style="display:none;">PDF</div>
          <div class="upload-preview-info">
            <div class="upload-preview-name" id="upload-preview-name"></div>
            <div class="upload-preview-size" id="upload-preview-size"></div>
          </div>
          <button type="button" class="upload-remove-btn" id="upload-remove-btn" data-i18n="uploadRemove">Remove</button>
        </div>
        <div class="error-msg" id="err-prescription" data-i18n="errPrescription">Please upload a valid prescription (JPG, JPEG, PNG or PDF) to continue.</div>
      </div>

      <!-- Desktop webcam capture modal — only opened when the browser
           reports a usable camera via getUserMedia(); otherwise
           scan-prescription-btn skips straight to the file picker instead. -->
      <div class="webcam-modal-backdrop" id="webcam-modal-backdrop">
        <div class="webcam-modal">
          <div class="webcam-modal-title" data-i18n="webcamModalTitle">Scan Prescription</div>
          <div class="webcam-video-wrap">
            <video id="webcam-video" autoplay playsinline muted></video>
            <canvas id="webcam-canvas" style="display:none;"></canvas>
            <img id="webcam-captured-img" style="display:none;" alt="Captured prescription"/>
          </div>
          <div class="webcam-modal-actions">
            <button type="button" class="btn-secondary" id="webcam-cancel-btn" data-i18n="webcamCancel">Cancel</button>
            <button type="button" class="btn-secondary" id="webcam-retake-btn" style="display:none;" data-i18n="webcamRetake">Retake</button>
            <button type="button" class="btn-primary" id="webcam-capture-btn" data-i18n="webcamCapture">📸 Capture</button>
            <button type="button" class="btn-primary" id="webcam-use-btn" style="display:none;" data-i18n="webcamUsePhoto">Use Photo</button>
          </div>
        </div>
      </div>
 
      <!-- Appointment (left) + Doctors Table (right) -->
      <div class="appointment-doctors-grid">
      <!-- Contact / Book an Appointment -->
      <div class="sidebar-contact">
        <h4 data-i18n="bookAppointment">Book an Appointment</h4>
        <p style="font-size:0.82rem;color:rgba(255,255,255,0.6);margin-bottom:0.9rem;" data-i18n="contactUs">To book an appointment, contact us:</p>
        <div class="contact-item">
          <svg viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg" style="width:14px;height:14px;">
            <path d="M2 3a1 1 0 011-1h2.5l1 3L5 6.5a10 10 0 004.5 4.5L11 9.5l3 1v2.5a1 1 0 01-1 1A13 13 0 012 3z" stroke="white" stroke-width="1.2"/>
          </svg>
          <?php echo htmlspecialchars(SHOP_PHONE); ?>
        </div>
        <div class="contact-item">
          <svg viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg" style="width:14px;height:14px;">
            <rect x="1" y="3" width="14" height="10" rx="2" stroke="white" stroke-width="1.2"/>
            <path d="M1 5l7 5 7-5" stroke="white" stroke-width="1.2"/>
          </svg>
          <a href="mailto:<?php echo htmlspecialchars(SHOP_EMAIL); ?>"><?php echo htmlspecialchars(SHOP_EMAIL); ?></a>
        </div>
      </div>
      <div class="card doctors-card">
        <div class="section-title" data-i18n="doctorsTitle">Doctors Available at GMS</div>
        <table class="doctors-table">
          <thead>
            <tr>
              <th data-i18n="colSpecialist">Specialist</th>
              <th data-i18n="colDayTime">Day &amp; Time</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td><span class="badge badge-teal" data-i18n="specNeuro">Neurosurgeon</span></td>
              <td data-i18n="timeNeuro">Tuesday, 2:00 PM – 4:00 PM</td>
            </tr>
            <tr>
              <td><span class="badge badge-gold" data-i18n="specSkin">Skin Doctor</span></td>
              <td data-i18n="timeSkin">Sunday, 11:00 AM – 3:00 PM</td>
            </tr>
          </tbody>
        </table>
      </div>
      </div><!-- /appointment-doctors-grid -->
 
      <!-- Action buttons -->
      <div class="order-actions">
        <button class="btn-secondary" onclick="goTo(1)" data-i18n="btnBack">← Back</button>
        <button class="btn-primary" style="width:auto;padding:13px 36px;" onclick="placeOrder()" data-i18n="btnPlaceOrder">Place Order →</button>
      </div>
 
    </div>
  </div>
</div>
 
 
<!-- ══════════════ PAGE 3: SUMMARY ══════════════ -->
<div class="page" id="page3">
  <div class="summary-wrapper">
    <div class="page-heading" style="margin-bottom:1.5rem;text-align:center;">
      <h1 style="font-family:'Cormorant Garamond',serif;font-size:2.2rem;color:var(--teal-dark);" data-i18n="summaryTitle">Order Summary</h1>
      <p style="color:var(--text-muted);" data-i18n="summarySubtitle">Review your order before we process it</p>
    </div>
 
    <div style="border-radius:var(--radius);overflow:hidden;box-shadow:var(--shadow);">
      <div class="summary-header">
        <h1 data-i18n="brand">Gupta Medical Stores</h1>
        <p data-i18n="brandTagline">Trusted Health, Since 1988</p>
        <div class="order-id" id="order-id">Order #GMS-000001</div>
      </div>
 
      <div class="summary-body">
 
        <!-- Patient Info -->
        <div class="summary-section">
          <div class="summary-section-title">
            <svg viewBox="0 0 16 16" fill="none"><circle cx="8" cy="5" r="3" stroke="#1d9e75" stroke-width="1.3"/><path d="M2 14c0-3.31 2.69-6 6-6s6 2.69 6 6" stroke="#1d9e75" stroke-width="1.3" stroke-linecap="round"/></svg>
            <span data-i18n="customerInfo">Customer Information</span>
          </div>
          <div class="info-grid" id="summary-patient"></div>
        </div>
 
        <!-- Medicines -->
        <div class="summary-section">
          <div class="summary-section-title">
            <svg viewBox="0 0 16 16" fill="none"><rect x="2" y="2" width="12" height="12" rx="2" stroke="#1d9e75" stroke-width="1.3"/><path d="M8 5v6M5 8h6" stroke="#1d9e75" stroke-width="1.3" stroke-linecap="round"/></svg>
            <span data-i18n="medicinesOrdered">Medicines Ordered</span>
          </div>
          <table class="summary-med-table">
            <thead>
              <tr>
                <th style="width:50px;">#</th>
                <th data-i18n="colMedName">Medicine Name</th>
                <th style="width:100px;text-align:center;" data-i18n="colQty">Quantity</th>
                <th style="width:110px;text-align:right;" data-i18n="colPrice">Price (₹)</th>
              </tr>
            </thead>
            <tbody id="summary-medicines"></tbody>
          </table>
        </div>

        <!-- UPI Payment — shown only when "UPI" was selected as the payment
             method on the Place Order page. Lets the customer scan the shop's
             QR code, pay, and optionally note the transaction/reference ID
             for the shop to verify against their UPI app before dispatch.
             Toggled by buildSummary() via the #upi-payment-section id. -->
        <div class="summary-section" id="upi-payment-section" style="display:none;">
          <div class="summary-section-title">
            <svg viewBox="0 0 16 16" fill="none"><rect x="2" y="2" width="5" height="5" rx="1" stroke="#0f6e56" stroke-width="1.3"/><rect x="9" y="2" width="5" height="5" rx="1" stroke="#0f6e56" stroke-width="1.3"/><rect x="2" y="9" width="5" height="5" rx="1" stroke="#0f6e56" stroke-width="1.3"/><path d="M9 9h2v2H9zM13 9h2v2h-2zM9 13h2v2H9zM13 13h2v2h-2z" fill="#0f6e56"/></svg>
            <span data-i18n="upiPaymentTitle">Pay via UPI</span>
          </div>
          <div class="upi-payment-inner">
            <div class="upi-qr-box">
              <img src="<?php echo htmlspecialchars(defined('UPI_QR_IMAGE') ? UPI_QR_IMAGE : 'assets/upi-qr-code.png'); ?>" data-i18n-alt="upiQrAlt" alt="UPI QR Code" />
              <?php if (defined('UPI_ID')): ?>
                <div class="upi-id-text"><?php echo htmlspecialchars(UPI_ID); ?></div>
              <?php endif; ?>
            </div>
            <div class="upi-payment-details">
              <p class="upi-payment-hint" data-i18n="upiPaymentHint">
                Scan the QR code with any UPI app (Google Pay, PhonePe, Paytm, etc.) and complete the payment for this order. Once paid, you can confirm your order below.
              </p>
              <label class="upi-ref-label" for="upiRefId" data-i18n="upiRefLabel">UPI Transaction / Reference ID (optional)</label>
              <input type="text" id="upiRefId" class="upi-ref-input" autocomplete="off" maxlength="50"
                data-i18n-placeholder="upiRefPlaceholder" placeholder="e.g. 123456789012" />
              <p style="font-size:0.78rem;color:var(--text-muted);margin-top:6px;" data-i18n="upiRefHint">
                Adding this helps us verify your payment faster — it's optional, and you can still confirm your order without it.
              </p>
            </div>
          </div>
        </div>

        <!-- Owner Note (customer-editable — saved with the order) -->
        <div class="summary-section" style="background:var(--gold-light);">
          <div class="summary-section-title" style="color:var(--gold);border-color:rgba(186,117,23,0.3);">
            <svg viewBox="0 0 16 16" fill="none"><path d="M8 1v14M1 8h14" stroke="#ba7517" stroke-width="1.5" stroke-linecap="round"/></svg>
            <span data-i18n="ownerNoteTitle">Note for Shop Owner</span>
          </div>
          <p style="font-size:0.82rem;color:#6b4007;line-height:1.6;margin-bottom:10px;" data-i18n="ownerNoteHint">
            Optional — add any special instructions for the shop, e.g. delivery time, substitution preference, etc.
          </p>
          <textarea id="ownerNote" maxlength="500" rows="3"
            style="width:100%;padding:11px 14px;border:1.5px solid rgba(186,117,23,0.35);border-radius:var(--radius-sm);font-family:'DM Sans',sans-serif;font-size:0.92rem;color:var(--text);background:white;resize:vertical;outline:none;"
            data-i18n-placeholder="ownerNotePlaceholder"
            placeholder="e.g. Please deliver after 6 PM. Call before delivery."
            oninput="document.getElementById('ownerNoteCounter').textContent = this.value.length"></textarea>
          <div style="text-align:right;font-size:0.75rem;color:#6b4007;opacity:0.75;margin-top:4px;">
            <span id="ownerNoteCounter">0</span>/500
          </div>
        </div>

        <!-- Medicine Reorder Reminder -->
        <div class="summary-section" id="reminder-section">
          <div class="summary-section-title">
            <svg viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="6.5" stroke="#1d9e75" stroke-width="1.3"/><path d="M8 4.5V8l2.5 1.5" stroke="#1d9e75" stroke-width="1.3" stroke-linecap="round"/></svg>
            <span data-i18n="reminderTitle">Medicine Reorder Reminder</span>
          </div>
          <p style="font-size:0.85rem;color:var(--text-muted);margin-bottom:1rem;line-height:1.6;" data-i18n="reminderHint">
            Optional — we'll email you a reminder when it's time to reorder these medicines.
          </p>
          <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:10px;">
            <div class="checkbox-group" style="margin:0;background:var(--cream);border-color:var(--border);">
              <input type="radio" name="reminder" id="remNone" value="none" checked/>
              <label for="remNone" style="color:var(--text);" data-i18n="reminderNone">No Reminder</label>
            </div>
            <!-- MODIFIED: preset reminder options changed from week1/week2/month1/month2/month3
                 (7/14/30/60/90 days) to the 7/15/30/45/60-day set requested for the site,
                 so the values shown here, the day counts in getReminderSelection() below,
                 and order_details.php's reminderDayMap all now agree. -->
            <div class="checkbox-group" style="margin:0;background:var(--cream);border-color:var(--border);">
              <input type="radio" name="reminder" id="remDay7" value="day7"/>
              <label for="remDay7" style="color:var(--text);" data-i18n="reminder7Days">7 Days</label>
            </div>
            <div class="checkbox-group" style="margin:0;background:var(--cream);border-color:var(--border);">
              <input type="radio" name="reminder" id="remDay15" value="day15"/>
              <label for="remDay15" style="color:var(--text);" data-i18n="reminder15Days">15 Days</label>
            </div>
            <div class="checkbox-group" style="margin:0;background:var(--cream);border-color:var(--border);">
              <input type="radio" name="reminder" id="remDay30" value="day30"/>
              <label for="remDay30" style="color:var(--text);" data-i18n="reminder30Days">30 Days</label>
            </div>
            <div class="checkbox-group" style="margin:0;background:var(--cream);border-color:var(--border);">
              <input type="radio" name="reminder" id="remDay45" value="day45"/>
              <label for="remDay45" style="color:var(--text);" data-i18n="reminder45Days">45 Days</label>
            </div>
            <div class="checkbox-group" style="margin:0;background:var(--cream);border-color:var(--border);">
              <input type="radio" name="reminder" id="remDay60" value="day60"/>
              <label for="remDay60" style="color:var(--text);" data-i18n="reminder60Days">60 Days</label>
            </div>
            <div class="checkbox-group" style="margin:0;background:var(--cream);border-color:var(--border);grid-column:span 2;">
              <input type="radio" name="reminder" id="remCustom" value="custom"/>
              <label for="remCustom" style="color:var(--text);white-space:nowrap;" data-i18n="reminderCustomLabel">In</label>
              <input type="text" id="remCustomDays" inputmode="numeric" placeholder="30"
                style="width:60px;padding:6px 8px;border:1.5px solid var(--border);border-radius:6px;background:white;font-size:0.88rem;text-align:center;"
                onfocus="document.getElementById('remCustom').checked = true;"/>
              <span style="color:var(--text);font-size:0.88rem;" data-i18n="reminderCustomDaysUnit">day(s)</span>
            </div>
          </div>
          <div class="error-msg" id="err-remCustomDays" data-i18n="errReminderDays">Enter a number of days between 1 and 365.</div>
        </div>
 
      </div><!-- /summary-body -->

      <!-- Order Total — computed automatically from each medicine's price
           (already qty-inclusive) and shown right above the Confirm Order
           button so the customer sees the final amount before confirming. -->
      <div class="summary-section" id="summary-total-section" style="border-top:1.5px solid var(--border);">
        <div class="confirm-totals grand"><span data-i18n="totalPaidLabel">Total Amount</span><span id="summary-total">₹0.00</span></div>
      </div>
 
      <div class="summary-actions">
        <button class="btn-secondary" onclick="goTo(2)" data-i18n="btnEditOrder">← Edit Order</button>
        <button class="btn-print" onclick="window.print()" data-i18n="btnPrint">🖨 Print Order</button>
        <button class="btn-primary" id="confirmOrderBtn" style="width:auto;padding:12px 28px;" onclick="confirmOrder()" data-i18n="btnConfirm">✓ Confirm Order</button>
      </div>
    </div><!-- /card -->
 
  </div>
</div>


<!-- ══════════════ PAGE 4: ORDER CONFIRMATION + LIVE TRACKING ══════════════ -->
<div class="page" id="page4">
  <div class="confirm-wrapper">

    <div style="border-radius:var(--radius);overflow:hidden;box-shadow:var(--shadow);">

      <!-- Hero / Confirmation Message -->
      <div class="confirm-hero">
        <div class="check-badge">
          <svg viewBox="0 0 24 24" fill="none" width="30" height="30"><path d="M4 12l6 6L20 6" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </div>
        <h1 data-i18n="confirmHeading">Your order has been placed successfully!</h1>
        <p data-i18n="confirmSubtext">Thank you for shopping with Gupta Medical Stores. A confirmation has been sent to your registered number.</p>
        <div class="order-id" id="confirm-order-id">Order #GMS-000001</div>
      </div>

      <div class="confirm-body">

        <!-- Order meta: date/time, payment, delivery estimate -->
        <div class="summary-section">
          <div class="summary-section-title">
            <svg viewBox="0 0 16 16" fill="none"><rect x="2" y="3" width="12" height="11" rx="1.5" stroke="#1d9e75" stroke-width="1.3"/><path d="M2 6h12M5 2v3M11 2v3" stroke="#1d9e75" stroke-width="1.3" stroke-linecap="round"/></svg>
            <span data-i18n="orderDetails">Order Details</span>
          </div>
          <div class="info-grid">
            <div class="info-item"><div class="info-label" data-i18n="orderIdLabel">Order ID</div><div class="info-value" id="confirm-order-id-2">GMS-000001</div></div>
            <div class="info-item"><div class="info-label" data-i18n="orderDateLabel">Order Date &amp; Time</div><div class="info-value" id="confirm-datetime">—</div></div>
            <div class="info-item"><div class="info-label" data-i18n="paymentTitle">Pay on Delivery</div><div class="info-value" id="confirm-payment">—</div></div>
            <div class="info-item"><div class="info-label" data-i18n="estDeliveryLabel">Estimated Delivery</div><div class="info-value" id="confirm-eta">—</div></div>
            <div class="info-item"><div class="info-label" data-i18n="reminderTitle">Medicine Reorder Reminder</div><div class="info-value" id="confirm-reminder">—</div></div>
            <div class="info-item" id="confirm-upi-wrap" style="display:none;"><div class="info-label" data-i18n="upiRefLabel">UPI Transaction / Reference ID (optional)</div><div class="info-value" id="confirm-upi">—</div></div>
            <div class="info-item" id="confirm-note-wrap" style="display:none;grid-column:1/-1"><div class="info-label" data-i18n="ownerNoteTitle">Note for Shop Owner</div><div class="info-value" id="confirm-note">—</div></div>
          </div>
        </div>

        <!-- Customer + delivery address -->
        <div class="summary-section">
          <div class="summary-section-title">
            <svg viewBox="0 0 16 16" fill="none"><circle cx="8" cy="5" r="3" stroke="#1d9e75" stroke-width="1.3"/><path d="M2 14c0-3.31 2.69-6 6-6s6 2.69 6 6" stroke="#1d9e75" stroke-width="1.3" stroke-linecap="round"/></svg>
            <span data-i18n="customerInfo">Customer Information</span>
          </div>
          <div class="info-grid" id="confirm-patient"></div>
        </div>

        <!-- Items -->
        <div class="summary-section">
          <div class="summary-section-title">
            <svg viewBox="0 0 16 16" fill="none"><rect x="2" y="2" width="12" height="12" rx="2" stroke="#1d9e75" stroke-width="1.3"/><path d="M8 5v6M5 8h6" stroke="#1d9e75" stroke-width="1.3" stroke-linecap="round"/></svg>
            <span data-i18n="medicinesOrdered">Medicines Ordered</span>
          </div>
          <table class="summary-med-table">
            <thead>
              <tr>
                <th style="width:50px;">#</th>
                <th data-i18n="colMedName">Medicine Name</th>
                <th style="width:100px;text-align:center;" data-i18n="colQty">Quantity</th>
                <th style="width:110px;text-align:right;" data-i18n="colPrice">Price (₹)</th>
              </tr>
            </thead>
            <tbody id="confirm-medicines"></tbody>
          </table>
          <div style="margin-top:14px;padding-top:10px;">
            <div class="confirm-totals"><span data-i18n="subtotalLabel">Subtotal</span><span id="confirm-subtotal">₹0</span></div>
            <div class="confirm-totals grand"><span data-i18n="totalPaidLabel">Total Amount</span><span id="confirm-total">₹0</span></div>
          </div>
        </div>

      </div><!-- /confirm-body -->

      <div class="summary-actions confirm-actions">
        <button class="btn-print" onclick="window.print()" data-i18n="btnPrint">🖨 Print Order</button>
        <button class="btn-primary" style="width:auto;padding:12px 28px;" onclick="newOrder()" data-i18n="btnNewOrder">＋ Start New Order</button>
      </div>

      <!-- ── LIVE DELIVERY TRACKING ── -->
      <div class="tracking-section">
        <div class="tracking-status-bar">
          <div class="tracking-status-live">
            <span class="live-dot"></span>
            <span id="tracking-status-text" data-i18n="statusPreparing">Preparing your order</span>
          </div>
          <div class="tracking-meta">
            <span><b id="tracking-eta">—</b> <span data-i18n="etaLabel">ETA</span></span>
            <span><b id="tracking-distance">—</b> <span data-i18n="distanceLabel">remaining</span></span>
          </div>
        </div>

        <div class="tracking-steps" id="tracking-steps">
          <div class="tracking-step" data-step="0"><div class="dot"></div><span data-i18n="statusStepPreparing">Preparing</span></div>
          <div class="tracking-step" data-step="1"><div class="dot"></div><span data-i18n="statusStepPickedUp">Picked Up</span></div>
          <div class="tracking-step" data-step="2"><div class="dot"></div><span data-i18n="statusStepOutForDelivery">Out for Delivery</span></div>
          <div class="tracking-step" data-step="3"><div class="dot"></div><span data-i18n="statusStepDelivered">Delivered</span></div>
        </div>

        <div id="delivery-map"></div>

        <p class="tracking-note">
          <span>ℹ️</span>
          <span data-i18n="trackingNote">This is a simulated live tracking view for demo purposes. Connect a real GPS feed from your delivery partner's app to replace the simulated marker with actual live coordinates.</span>
        </p>
      </div>

    </div><!-- /card -->

  </div>
</div>
 
 
<!-- ══════════════ FOOTER ══════════════ -->
<footer>
  <strong data-i18n="brand">Gupta Medical Stores GMS</strong>
  &nbsp;·&nbsp;
  <?php echo htmlspecialchars(SHOP_PHONE); ?>
  &nbsp;·&nbsp;
  <?php echo htmlspecialchars(SHOP_EMAIL); ?>
</footer>

</div><!-- /site-content -->
 
 
<!-- ══════════════ JAVASCRIPT ══════════════ -->
<script>
  /* ══════════════════════════════════════════════════════════════
     TRANSLATIONS
     All visible UI text lives here. Add a language by adding a new
     key (e.g. "mr") with the same set of keys as "en" / "hi".
     ══════════════════════════════════════════════════════════════ */
  const translations = {
    en: {
      brand: "Gupta Medical Stores",
      brandTagline: "Trusted Health, Since 1988",
      stepReg: "Registration",
      stepOrder: "Place Order",
      stepSummary: "Summary",

      regTitle: "Register to Order",
      regSubtitle: "Create your account to place an order with us",
      labelFirstName: "First Name",
      labelLastName: "Last Name",
      labelGender: "Gender",
      labelPhone: "Whatsapp Number",
      labelEmail: "Email Address",
      labelAddress: "Address",
      labelPincode: "PIN Code",
      errFirstName: "First name is required.",
      errLastName: "Last name is required.",
      errGender: "Please select a gender.",
      errPhone: "Enter a valid 10-digit number.",
      errEmail: "Enter a valid email address.",
      errAddress: "Address is required.",
      errPincodeFormat: "Please enter a valid 6-digit PIN code.",
      errPincodeArea: "Sorry, we don't deliver to your area yet. We'll reach your area soon!!",
      errTerms: "You must agree to the terms.",
      genderSelect: "— Select —",
      genderMale: "Male",
      genderFemale: "Female",
      genderOthers: "Others",
      placeholderAddress: "House No., Street, City, State, PIN",
      placeholderPincode: "231001",
      termsPre: "I agree to the",
      termsLink: "terms and conditions",
      termsPost: "of Gupta Medical Stores",
      btnRegister: "Register & Continue →",

      orderTitle: "Place Your Order",
      orderSubtitle: "Fill in the medicines you need and their quantities",
      medListTitle: "Medicine List",
      colMedName: "Medicine Name",
      colQty: "Quantity",
      colUnit: "Pack",
      bookAppointment: "Book an Appointment",
      whatsappCtaText: "Order Directly via WhatsApp",
      whatsappPointerText: "Order Directly Here",
      contactUs: "To book an appointment, contact us:",
      doctorsTitle: "Doctors Available at GMS",
      colSpecialist: "Specialist",
      colDayTime: "Day & Time",
      specNeuro: "Neurosurgeon",
      specSkin: "Skin Doctor",
      timeNeuro: "Tuesday, 2:00 PM – 4:00 PM",
      timeSkin: "Sunday, 11:00 AM – 3:00 PM",
      btnBack: "← Back",
      btnPlaceOrder: "Place Order →",
      medPlaceholderName: "Medicine name",
      medPlaceholderQty: "Qty",
      colPrice: "Price (₹)",
      btnAddMedicine: "+ Add Another Medicine",

      paymentTitle: "Pay on Delivery",
      payCod: "Cash on Delivery",
      payUpi: "UPI",

      upiPaymentTitle: "Pay via UPI",
      upiPaymentHint: "Scan the QR code with any UPI app (Google Pay, PhonePe, Paytm, etc.) and complete the payment for this order. Once paid, you can confirm your order below.",
      upiQrAlt: "UPI QR Code",
      upiRefLabel: "UPI Transaction / Reference ID (optional)",
      upiRefPlaceholder: "e.g. 123456789012",
      upiRefHint: "Adding this helps us verify your payment faster — it's optional, and you can still confirm your order without it.",

      prescriptionTitle: "Upload Prescription",
      prescriptionHint: "If your order contains prescription-only medicines, please upload a clear photo or scan of a valid doctor's prescription. Accepted formats: JPG, JPEG, PNG, PDF.",
      prescriptionRequiredNote: "One or more medicines in your list typically require a prescription. Please upload it below to continue.",
      uploadDropzoneText: "Click or drag your prescription here",
      uploadDropzoneSub: "JPG, JPEG, PNG or PDF — max 5MB",
      uploadRemove: "Remove",
      errPrescription: "Please upload a valid prescription (JPG, JPEG, PNG or PDF) to continue.",
      orDividerText: "or",
      scanPrescriptionBtn: "📷 Scan Your Prescription",
      webcamModalTitle: "Scan Prescription",
      webcamCancel: "Cancel",
      webcamRetake: "Retake",
      webcamCapture: "📸 Capture",
      webcamUsePhoto: "Use Photo",
      errPrescriptionType: "Only JPG, JPEG, PNG or PDF files are allowed.",
      errPrescriptionSize: "File is too large. Please upload a file under 5MB.",

      summaryTitle: "Order Summary",
      summarySubtitle: "Review your order before we process it",
      customerInfo: "Customer Information",
      medicinesOrdered: "Medicines Ordered",
      ownerNoteTitle: "Note for Shop Owner",
      ownerNoteHint: "Optional — add any special instructions for the shop, e.g. delivery time, substitution preference, etc.",
      ownerNotePlaceholder: "e.g. Please deliver after 6 PM. Call before delivery.",
      reminderTitle: "Medicine Reorder Reminder",
      reminderHint: "Optional — we'll email you a reminder when it's time to reorder these medicines.",
      reminderNone: "No Reminder",
      /* MODIFIED: renamed from reminder1Week/reminder2Weeks/reminder1Month/
         reminder2Months/reminder3Months to match the new 7/15/30/45/60-day presets. */
      reminder7Days: "7 Days",
      reminder15Days: "15 Days",
      reminder30Days: "30 Days",
      reminder45Days: "45 Days",
      reminder60Days: "60 Days",
      reminderCustomLabel: "In",
      reminderCustomDaysUnit: "day(s)",
      errReminderDays: "Enter a number of days between 1 and 365.",
      btnEditOrder: "← Edit Order",
      btnPrint: "🖨 Print Order",
      btnConfirm: "✓ Confirm Order",
      btnSubmitting: "Saving Order…",
      alertOrderFailed: "We couldn't save your order — please check your connection and try again.",
      allRightsReserved: "All Rights Reserved",

      summaryFullName: "Full Name",
      alertNoMeds: "Please enter at least one medicine before placing the order.",
      confirmNewOrder: "Order confirmed! Start a new order? This will reset the form.",

      confirmHeading: "Your order has been placed successfully!",
      confirmSubtext: "Thank you for shopping with Gupta Medical Stores. A confirmation has been sent to your registered number.",
      orderDetails: "Order Details",
      orderIdLabel: "Order ID",
      orderDateLabel: "Order Date & Time",
      estDeliveryLabel: "Estimated Delivery",
      subtotalLabel: "Subtotal",
      totalPaidLabel: "Total Amount",
      btnNewOrder: "＋ Start New Order",
      statusPreparing: "Preparing your order",
      statusPickedUp: "Your order has been picked up",
      statusOutForDelivery: "Delivery Partner is on the way",
      statusDelivered: "Delivered — enjoy your health!",
      etaLabel: "ETA",
      distanceLabel: "remaining",
      statusStepPreparing: "Preparing",
      statusStepPickedUp: "Picked Up",
      statusStepOutForDelivery: "Out for Delivery",
      statusStepDelivered: "Delivered",
      trackingNote: "This is a simulated live tracking view for demo purposes. Connect a real GPS feed from your delivery partner's app to replace the simulated marker with actual live coordinates.",

      ashaBubbleLabel: "Ask Asha 👩‍⚕️",
      ashaHeaderTitle: "Asha",
      ashaHeaderSubtitle: "AI Nurse Assistant",
      ashaGreeting: "Hi! I'm Asha 👩‍⚕️, your AI Nurse Assistant. I'm here to help you with medicines, your orders, and any questions about Gupta Medical Stores. How can I help you today?",
      ashaInputPlaceholder: "Type your message…",
      ashaSend: "Send",
      ashaDisclaimer: "Asha provides general educational information and does not replace advice from a licensed doctor or pharmacist.",
      ashaTyping: "Asha is typing…",
      ashaQuickFever: "🤒 I have a fever",
      ashaQuickCold: "🤧 Cold & Cough",
      ashaQuickTrack: "📦 Track My Order",
      ashaQuickPrescription: "📋 Upload Prescription",
      ashaQuickPayment: "💳 Pay on Delivery",
      ashaQuickDelivery: "🚚 Delivery Charges",
      ashaQuickContact: "📞 Contact Pharmacy",
      ashaQuickWhatsapp: "💬 Chat on WhatsApp",
      ashaHandoffIntro: "I'd be happy to connect you with our pharmacy team.",
      ashaContactBtn: "📞 Contact Pharmacy",
      ashaWhatsappBtn: "💬 Chat on WhatsApp",
      ashaHours: "Store hours: Mon–Sat, 9:00 AM – 9:00 PM",
      ashaQuickChipsLabel: "Quick options",
      ashaErrorMsg: "Sorry, I'm having trouble connecting right now. Please try again, or reach our pharmacy team directly.",
      ashaCloseLabel: "Close chat",

      ashaSymptomIntro: "I'm sorry to hear that. Based on your symptoms, you may consider medicines like:",
      ashaSymptomOutro: "You can order these directly from our e-store by adding them to your medicine list. This is general guidance only and not a substitute for professional medical advice — if your symptoms are severe or persist, please consult a doctor.",
      ashaSymptomNoMatch: "I couldn't find a matching product in our current medicine list for that. Please consult a doctor or contact our pharmacy team directly, especially if your symptoms are severe or persist.",

      ashaPrescriptionIntro: "Sure — you can upload a photo/PDF of your prescription or scan it with your camera. This follows the same secure process as the prescription upload on our order form.",
      ashaPrescriptionUploadBtn: "📁 Upload File",
      ashaPrescriptionScanBtn: "📷 Scan with Camera",
      ashaPrescriptionUploaded: "Got it — \"{fileName}\" has been attached to your order. You'll see it in the Prescription section on the Place Order page too.",
      ashaPrescriptionError: "That didn't go through: {error} Please try again."
    },
    hi: {
      brand: "गुप्ता मेडिकल स्टोर्स",
      brandTagline: "भरोसेमंद स्वास्थ्य सेवा, 1988 से",
      stepReg: "पंजीकरण",
      stepOrder: "ऑर्डर करें",
      stepSummary: "सारांश",

      regTitle: "ऑर्डर हेतु पंजीकरण करें",
      regSubtitle: "हमारे साथ ऑर्डर देने के लिए अपना खाता बनाएं",
      labelFirstName: "पहला नाम",
      labelLastName: "अंतिम नाम",
      labelGender: "लिंग",
      labelPhone: "व्हाट्सएप नंबर",
      labelEmail: "ईमेल पता",
      labelAddress: "पता",
      labelPincode: "पिन कोड",
      errFirstName: "पहला नाम आवश्यक है।",
      errLastName: "अंतिम नाम आवश्यक है।",
      errGender: "कृपया लिंग चुनें।",
      errPhone: "मान्य 10 अंकों का नंबर दर्ज करें।",
      errEmail: "कृपया एक मान्य ईमेल पता दर्ज करें।",
      errAddress: "पता आवश्यक है।",
      errPincodeFormat: "कृपया एक मान्य 6 अंकों का पिन कोड दर्ज करें।",
      errPincodeArea: "क्षमा करें, हम अभी आपके क्षेत्र में डिलीवरी नहीं करते हैं। हम जल्द ही आपके क्षेत्र में पहुंचेंगे!!",
      errTerms: "आपको नियम व शर्तों से सहमत होना होगा।",
      genderSelect: "— चुनें —",
      genderMale: "पुरुष",
      genderFemale: "महिला",
      genderOthers: "अन्य",
      placeholderAddress: "मकान नंबर, गली, शहर, राज्य, पिन कोड",
      placeholderPincode: "231001",
      termsPre: "मैं",
      termsLink: "नियम और शर्तों",
      termsPost: "से सहमत हूं",
      btnRegister: "पंजीकरण करें और जारी रखें →",

      orderTitle: "अपना ऑर्डर दें",
      orderSubtitle: "आवश्यक दवाइयां और उनकी मात्रा भरें",
      medListTitle: "दवाइयों की सूची",
      colMedName: "दवा का नाम",
      colQty: "मात्रा",
      colUnit: "पैक",
      bookAppointment: "अपॉइंटमेंट बुक करें",
      whatsappCtaText: "व्हाट्सएप के ज़रिए सीधे ऑर्डर करें",
      whatsappPointerText: "यहां सीधे ऑर्डर करें",
      contactUs: "अपॉइंटमेंट बुक करने के लिए हमसे संपर्क करें:",
      doctorsTitle: "GMS में उपलब्ध डॉक्टर",
      colSpecialist: "विशेषज्ञ",
      colDayTime: "दिन व समय",
      specNeuro: "न्यूरोसर्जन",
      specSkin: "त्वचा रोग विशेषज्ञ",
      timeNeuro: "मंगलवार — दोपहर 2:00 से 4:00 बजे तक",
      timeSkin: "रविवार — सुबह 11:00 से दोपहर 3:00 बजे तक",
      btnBack: "← वापस",
      btnPlaceOrder: "ऑर्डर करें →",
      medPlaceholderName: "दवा का नाम",
      medPlaceholderQty: "मात्रा",
      colPrice: "मूल्य (₹)",
      btnAddMedicine: "+ एक और दवा जोड़ें",

      paymentTitle: "डिलीवरी पर भुगतान",
      payCod: "डिलीवरी पर नकद भुगतान",
      payUpi: "यूपीआई",

      upiPaymentTitle: "यूपीआई से भुगतान करें",
      upiPaymentHint: "किसी भी यूपीआई ऐप (Google Pay, PhonePe, Paytm आदि) से क्यूआर कोड स्कैन करें और इस ऑर्डर का भुगतान पूरा करें। भुगतान करने के बाद, आप नीचे अपना ऑर्डर पुष्ट कर सकते हैं।",
      upiQrAlt: "यूपीआई क्यूआर कोड",
      upiRefLabel: "यूपीआई ट्रांज़ैक्शन / रेफरेंस आईडी (वैकल्पिक)",
      upiRefPlaceholder: "जैसे 123456789012",
      upiRefHint: "इसे जोड़ने से हमें आपके भुगतान को जल्दी सत्यापित करने में मदद मिलती है — यह वैकल्पिक है, आप इसके बिना भी अपना ऑर्डर पुष्ट कर सकते हैं।",

      prescriptionTitle: "प्रिस्क्रिप्शन अपलोड करें",
      prescriptionHint: "यदि आपके ऑर्डर में केवल प्रिस्क्रिप्शन पर मिलने वाली दवाइयां हैं, तो कृपया डॉक्टर के मान्य प्रिस्क्रिप्शन की स्पष्ट फोटो या स्कैन अपलोड करें। स्वीकृत प्रारूप: JPG, JPEG, PNG, PDF.",
      prescriptionRequiredNote: "आपकी सूची में एक या अधिक दवाइयों के लिए आमतौर पर प्रिस्क्रिप्शन आवश्यक होता है। जारी रखने के लिए कृपया इसे नीचे अपलोड करें।",
      uploadDropzoneText: "अपना प्रिस्क्रिप्शन यहां क्लिक करें या खींचें",
      uploadDropzoneSub: "JPG, JPEG, PNG या PDF — अधिकतम 5MB",
      uploadRemove: "हटाएं",
      errPrescription: "जारी रखने के लिए कृपया एक मान्य प्रिस्क्रिप्शन (JPG, JPEG, PNG या PDF) अपलोड करें।",
      orDividerText: "या",
      scanPrescriptionBtn: "📷 अपनी प्रिस्क्रिप्शन स्कैन करें",
      webcamModalTitle: "प्रिस्क्रिप्शन स्कैन करें",
      webcamCancel: "रद्द करें",
      webcamRetake: "फिर से लें",
      webcamCapture: "📸 कैप्चर करें",
      webcamUsePhoto: "फ़ोटो का उपयोग करें",
      errPrescriptionType: "केवल JPG, JPEG, PNG या PDF फ़ाइलें स्वीकार्य हैं।",
      errPrescriptionSize: "फ़ाइल बहुत बड़ी है। कृपया 5MB से छोटी फ़ाइल अपलोड करें।",

      summaryTitle: "ऑर्डर सारांश",
      summarySubtitle: "प्रोसेस करने से पहले अपना ऑर्डर जांचें",
      customerInfo: "ग्राहक जानकारी",
      medicinesOrdered: "ऑर्डर की गई दवाइयां",
      ownerNoteTitle: "दुकान मालिक के लिए नोट",
      ownerNoteHint: "वैकल्पिक — दुकान के लिए कोई विशेष निर्देश जोड़ें, जैसे डिलीवरी का समय, विकल्प वरीयता आदि।",
      ownerNotePlaceholder: "जैसे, कृपया शाम 6 बजे के बाद डिलीवर करें। डिलीवरी से पहले कॉल करें।",
      reminderTitle: "दवा पुनः ऑर्डर रिमाइंडर",
      reminderHint: "वैकल्पिक — जब दवाइयां दोबारा ऑर्डर करने का समय हो, हम आपको ईमेल द्वारा याद दिलाएंगे।",
      reminderNone: "कोई रिमाइंडर नहीं",
      /* MODIFIED: renamed to match the new 7/15/30/45/60-day presets. */
      reminder7Days: "7 दिन",
      reminder15Days: "15 दिन",
      reminder30Days: "30 दिन",
      reminder45Days: "45 दिन",
      reminder60Days: "60 दिन",
      reminderCustomLabel: "इसमें",
      reminderCustomDaysUnit: "दिन",
      errReminderDays: "कृपया 1 से 365 के बीच दिनों की संख्या दर्ज करें।",
      btnEditOrder: "← ऑर्डर संपादित करें",
      btnPrint: "🖨 ऑर्डर प्रिंट करें",
      btnConfirm: "✓ ऑर्डर पुष्ट करें",
      btnSubmitting: "ऑर्डर सहेजा जा रहा है…",
      alertOrderFailed: "आपका ऑर्डर सहेजा नहीं जा सका — कृपया अपना कनेक्शन जांचें और पुनः प्रयास करें।",
      allRightsReserved: "सर्वाधिकार सुरक्षित",

      summaryFullName: "पूरा नाम",
      alertNoMeds: "ऑर्डर देने से पहले कृपया कम से कम एक दवा दर्ज करें।",
      confirmNewOrder: "ऑर्डर की पुष्टि हो गई! नया ऑर्डर शुरू करें? इससे फॉर्म रीसेट हो जाएगा।",

      confirmHeading: "आपका ऑर्डर सफलतापूर्वक दे दिया गया है!",
      confirmSubtext: "गुप्ता मेडिकल स्टोर्स के साथ खरीदारी के लिए धन्यवाद। आपके पंजीकृत नंबर पर पुष्टि भेज दी गई है।",
      orderDetails: "ऑर्डर विवरण",
      orderIdLabel: "ऑर्डर आईडी",
      orderDateLabel: "ऑर्डर की तारीख व समय",
      estDeliveryLabel: "अनुमानित डिलीवरी",
      subtotalLabel: "उप-योग",
      totalPaidLabel: "कुल राशि",
      btnNewOrder: "＋ नया ऑर्डर शुरू करें",
      statusPreparing: "आपका ऑर्डर तैयार किया जा रहा है",
      statusPickedUp: "आपका ऑर्डर पिक अप कर लिया गया है",
      statusOutForDelivery: "डिलीवरी पार्टनर रास्ते में है",
      statusDelivered: "डिलीवर हो गया — आपके स्वास्थ्य के लिए शुभकामनाएं!",
      etaLabel: "अनुमानित समय",
      distanceLabel: "शेष दूरी",
      statusStepPreparing: "तैयार हो रहा है",
      statusStepPickedUp: "पिक अप हुआ",
      statusStepOutForDelivery: "डिलीवरी के लिए निकला",
      statusStepDelivered: "डिलीवर हो गया",
      trackingNote: "यह डेमो के लिए एक सिम्युलेटेड लाइव ट्रैकिंग दृश्य है। वास्तविक लाइव निर्देशांक के लिए अपने डिलीवरी पार्टनर के ऐप से वास्तविक जीपीएस फ़ीड जोड़ें।",

      ashaBubbleLabel: "आशा से पूछें 👩‍⚕️",
      ashaHeaderTitle: "आशा",
      ashaHeaderSubtitle: "एआई नर्स सहायक",
      ashaGreeting: "नमस्ते! मैं आशा 👩‍⚕️ हूं, आपकी एआई नर्स सहायक। मैं दवाइयों, आपके ऑर्डर और गुप्ता मेडिकल स्टोर्स से जुड़े किसी भी सवाल में आपकी मदद के लिए यहां हूं। मैं आज आपकी कैसे मदद कर सकती हूं?",
      ashaInputPlaceholder: "अपना संदेश लिखें…",
      ashaSend: "भेजें",
      ashaDisclaimer: "आशा सामान्य शैक्षिक जानकारी प्रदान करती है और यह किसी लाइसेंस प्राप्त डॉक्टर या फार्मासिस्ट की सलाह का विकल्प नहीं है।",
      ashaTyping: "आशा लिख रही है…",
      ashaQuickFever: "🤒 मुझे बुखार है",
      ashaQuickCold: "🤧 सर्दी और खांसी",
      ashaQuickTrack: "📦 मेरा ऑर्डर ट्रैक करें",
      ashaQuickPrescription: "📋 प्रिस्क्रिप्शन अपलोड करें",
      ashaQuickPayment: "💳 डिलीवरी पर भुगतान",
      ashaQuickDelivery: "🚚 डिलीवरी शुल्क",
      ashaQuickContact: "📞 फार्मेसी से संपर्क करें",
      ashaQuickWhatsapp: "💬 व्हाट्सएप पर चैट करें",
      ashaHandoffIntro: "मुझे आपको हमारी फार्मेसी टीम से जोड़कर खुशी होगी।",
      ashaContactBtn: "📞 फार्मेसी से संपर्क करें",
      ashaWhatsappBtn: "💬 व्हाट्सएप पर चैट करें",
      ashaHours: "स्टोर समय: सोम–शनि, सुबह 9:00 – रात 9:00",
      ashaQuickChipsLabel: "त्वरित विकल्प",
      ashaErrorMsg: "क्षमा करें, अभी कनेक्ट करने में समस्या हो रही है। कृपया फिर से कोशिश करें, या सीधे हमारी फार्मेसी टीम से संपर्क करें।",
      ashaCloseLabel: "चैट बंद करें",

      ashaSymptomIntro: "यह सुनकर खेद हुआ। आपके लक्षणों के आधार पर, आप इन दवाइयों पर विचार कर सकते हैं:",
      ashaSymptomOutro: "आप इन्हें अपनी दवा सूची में जोड़कर सीधे हमारे ई-स्टोर से ऑर्डर कर सकते हैं। यह केवल सामान्य जानकारी है और किसी पेशेवर चिकित्सीय सलाह का विकल्प नहीं है — यदि आपके लक्षण गंभीर हैं या बने रहते हैं, तो कृपया डॉक्टर से सलाह लें।",
      ashaSymptomNoMatch: "मुझे हमारी वर्तमान दवा सूची में इसके लिए कोई मिलती-जुलती दवा नहीं मिली। कृपया डॉक्टर से सलाह लें या सीधे हमारी फार्मेसी टीम से संपर्क करें, खासकर यदि आपके लक्षण गंभीर हैं या बने रहते हैं।",

      ashaPrescriptionIntro: "ज़रूर — आप अपने प्रिस्क्रिप्शन की फोटो/PDF अपलोड कर सकते हैं या अपने कैमरे से स्कैन कर सकते हैं। यह हमारे ऑर्डर फॉर्म पर मौजूद प्रिस्क्रिप्शन अपलोड जैसी ही सुरक्षित प्रक्रिया है।",
      ashaPrescriptionUploadBtn: "📁 फ़ाइल अपलोड करें",
      ashaPrescriptionScanBtn: "📷 कैमरे से स्कैन करें",
      ashaPrescriptionUploaded: "हो गया — \"{fileName}\" आपके ऑर्डर के साथ जोड़ दिया गया है। यह Place Order पेज के प्रिस्क्रिप्शन सेक्शन में भी दिखेगा।",
      ashaPrescriptionError: "यह अपलोड नहीं हो सका: {error} कृपया पुनः प्रयास करें।"
    }
  };

  // Shop contact details — sourced from ai_config.php (the single place to
  // update these), so every place in this script that shows a phone number,
  // WhatsApp link, or email reads from here instead of a hardcoded string.
  const SHOP_PHONE     = "<?php echo addslashes(SHOP_PHONE); ?>";
  const SHOP_WHATSAPP  = "<?php echo addslashes(SHOP_WHATSAPP); ?>";
  const SHOP_EMAIL     = "<?php echo addslashes(SHOP_EMAIL); ?>";
  // ADDED: lets validateRegistration() recognize the shop owner's email and
  // switch to the PIN login prompt instead of continuing as a customer.
  // Not a secret by itself — the actual PIN is verified server-side in
  // admin_login.php, which is the only place ADMIN_PASSWORD_HASH is read.
  const ADMIN_EMAIL    = "<?php echo addslashes(ADMIN_EMAIL); ?>";

  // Current language, restored from a previous visit if available
  let currentLang = localStorage.getItem('gms_lang') || 'en';

  /* Apply the active language to every element tagged with data-i18n /
     data-i18n-placeholder. Called on load and whenever the language changes. */
  function applyTranslations() {
    const dict = translations[currentLang];
    document.querySelectorAll('[data-i18n]').forEach(el => {
      const key = el.getAttribute('data-i18n');
      if (dict[key] !== undefined) el.textContent = dict[key];
    });
    document.querySelectorAll('[data-i18n-placeholder]').forEach(el => {
      const key = el.getAttribute('data-i18n-placeholder');
      if (dict[key] !== undefined) el.placeholder = dict[key];
    });
    document.querySelectorAll('[data-i18n-aria]').forEach(el => {
      const key = el.getAttribute('data-i18n-aria');
      if (dict[key] !== undefined) el.setAttribute('aria-label', dict[key]);
    });
    document.querySelectorAll('[data-i18n-alt]').forEach(el => {
      const key = el.getAttribute('data-i18n-alt');
      if (dict[key] !== undefined) el.alt = dict[key];
    });
    // Medicine-row inputs are generated dynamically, so translate them separately
    document.querySelectorAll('.med-name').forEach(el => el.placeholder = dict.medPlaceholderName);
    document.querySelectorAll('.med-qty').forEach(el => el.placeholder = dict.medPlaceholderQty);
    document.querySelectorAll('.med-price').forEach(el => el.placeholder = '0.00');
  }

  /* Switch the active language instantly (no reload) and remember the choice */
  function setLanguage(lang) {
    currentLang = lang;
    localStorage.setItem('gms_lang', lang);

    document.getElementById('lang-en').classList.toggle('active', lang === 'en');
    document.getElementById('lang-hi').classList.toggle('active', lang === 'hi');
    document.body.classList.toggle('lang-hi', lang === 'hi');

    applyTranslations();

    // Quick-reply chips are JS-injected (not data-i18n tagged), so refresh
    // their labels explicitly when the language changes.
    if (typeof ashaRenderQuickChips === 'function') ashaRenderQuickChips();

    // If the summary page already has data rendered on it, re-render it so
    // its dynamically-built labels (Full Name, etc.) switch too
    if (state.reg && state.reg.firstName) buildSummary(false);

    // Refresh dynamic text on the confirmation/tracking page (data-i18n
    // covers static labels automatically; these are JS-injected strings)
    if (state.orderId && document.getElementById('page4').classList.contains('active')) {
      const activeStepEl = document.querySelector('.tracking-step.active');
      if (activeStepEl) setTrackingStep(parseInt(activeStepEl.dataset.step));
    }
  }

  /* ── State ── */
  const state = {
    reg: {},
    medicines: [],
    payment: 'Cash on Delivery',
    upiTransactionId: '',
    prescription: null // { name, size, type, dataUrl }
  };

  /* ── Prescription requirement ──
     A simple keyword list of common Schedule-H / prescription-only medicines.
     If any medicine name entered by the customer matches (case-insensitive,
     partial match) one of these keywords, a prescription upload becomes
     mandatory before the order can proceed. Extend this list as needed. */
  const PRESCRIPTION_KEYWORDS = [
    'azithromycin', 'amoxicillin', 'augmentin', 'alprazolam', 'tramadol',
    'metformin', 'amlodipine', 'atorvastatin', 'pantoprazole', 'levothyroxine',
    'insulin', 'ciprofloxacin', 'doxycycline', 'diazepam', 'clonazepam',
    'losartan', 'telmisartan', 'omeprazole', 'montelukast', 'prednisolone',
    'metoprolol', 'gabapentin', 'sertraline', 'escitalopram', 'warfarin'
  ];

  function medicinesRequirePrescription() {
    const names = Array.from(document.querySelectorAll('.med-name')).map(el => el.value.trim().toLowerCase());
    return names.some(n => n && PRESCRIPTION_KEYWORDS.some(k => n.includes(k)));
  }

  /* Show/hide the "prescription likely required" hint as the customer types */
  function refreshPrescriptionNote() {
    const note = document.getElementById('prescription-required-note');
    if (!note) return;
    note.classList.toggle('show', medicinesRequirePrescription());
  }
 
  /* ── Build medicine rows ──
     Starts with a small number of rows visible; the customer can add
     as many more as they need via the "Add Another Medicine" button
     (see addMedicineRow() below). medicineRowCount tracks the next
     row number so newly-added rows keep unique numbering/ids even
     after rows already on the page have been filled in. */
  const DEFAULT_MEDICINE_ROWS = 3;
  let medicineRowCount = 0;

  function medicineRowHtml(i) {
    const dict = translations[currentLang];
    return `
      <tr>
        <td>${i}</td>
        <td class="med-name-cell">
          <div class="med-name-wrap">
            <input type="text" class="med-name" placeholder="${dict.medPlaceholderName}" autocomplete="off" />
            <div class="med-suggestions" id="med-suggest-${i}"></div>
          </div>
        </td>
        <td><input type="text" class="med-qty qty-input" placeholder="${dict.medPlaceholderQty}" /></td>
        <td class="unit-cell"><span class="med-unit-value">—</span></td>
        <td class="price-cell"><input type="text" inputmode="decimal" class="med-price price-input" placeholder="0.00" /></td>
      </tr>`;
  }

  /* Appends one new medicine row to the table. Because every listener
     below (input/keydown/focusout on #medicine-rows, and the
     querySelectorAll('.med-name') etc. used when placing the order)
     is either delegated on the tbody or re-queries the whole document,
     a freshly-added row is picked up automatically — no extra wiring
     needed, and no limit on how many rows can be added. */
  function addMedicineRow() {
    medicineRowCount++;
    const tbody = document.getElementById('medicine-rows');
    tbody.insertAdjacentHTML('beforeend', medicineRowHtml(medicineRowCount));
  }

  (function buildMedicineRows() {
    for (let i = 1; i <= DEFAULT_MEDICINE_ROWS; i++) addMedicineRow();
  })();

  document.getElementById('add-medicine-btn').addEventListener('click', addMedicineRow);

  /* ══════════════════════════════════════════════════════════════
     MEDICINE NAME AUTOCOMPLETE + AUTO-PRICING FROM medicines.csv
     ══════════════════════════════════════════════════════════════
     As the customer types a medicine name, medicine_lookup.php (which
     reads medicines.csv) is queried and matching names appear as a
     clickable dropdown under that row's input (handles typos/partial
     names fine, since the backend matches on "contains", not just exact
     text). Picking a suggestion — by click or Enter — fills the Pack and
     Price cells from the stock list, and Price recalculates automatically
     whenever Quantity changes (Price = Sales Price × Quantity). The
     Price field stays editable by hand for anything not found in
     medicines.csv, or if the shop wants to override the number; manual
     edits stop the auto-recalculation for that row so they're never
     silently overwritten. */
  const rowMedicineMatch = new WeakMap(); // <tr> → { unit, price } currently matched

  function debounce(fn, wait) {
    let t;
    return (...args) => { clearTimeout(t); t = setTimeout(() => fn(...args), wait); };
  }

  async function fetchMedicineMatches(query) {
    try {
      const res = await fetch('medicine_lookup.php?q=' + encodeURIComponent(query));
      return res.ok ? await res.json() : [];
    } catch (e) {
      return []; // offline / server hiccup — customer can still type a price by hand
    }
  }

  function escapeHtml(str) {
    return String(str).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  }

  function closeSuggestions(row) {
    const box = row.querySelector('.med-suggestions');
    if (box) { box.classList.remove('show'); box.innerHTML = ''; }
    row._medicineOptions = null;
  }

  function selectMedicine(row, match) {
    const nameInput  = row.querySelector('.med-name');
    const qtyInput   = row.querySelector('.med-qty');
    const unitEl     = row.querySelector('.med-unit-value');

    nameInput.value = match.name;
    rowMedicineMatch.set(row, match);
    unitEl.textContent = match.unit || '—';
    if (!qtyInput.value) qtyInput.value = 1; // default so a price shows immediately
    recalcRowPrice(row);
    closeSuggestions(row);
    qtyInput.focus();
    qtyInput.select();
  }

  function renderSuggestions(row, matches) {
    const box = row.querySelector('.med-suggestions');
    if (!box) return;

    if (!matches.length) {
      box.innerHTML = `<div class="med-suggestion-empty">No matching medicine found</div>`;
      box.classList.add('show');
      row._medicineOptions = null;
      return;
    }

    row._medicineOptions = matches;
    box.innerHTML = matches.map((m, idx) => `
      <div class="med-suggestion-item" data-idx="${idx}">
        <span class="sugg-name">${escapeHtml(m.name)}</span>
        <span class="sugg-price">₹${Number(m.price).toFixed(2)}</span>
      </div>`).join('');
    box.classList.add('show');

    box.querySelectorAll('.med-suggestion-item').forEach(item => {
      // mousedown (not click) fires before the input's blur event, so the
      // dropdown doesn't close itself before the click is registered.
      item.addEventListener('mousedown', (e) => {
        e.preventDefault();
        const idx = parseInt(item.dataset.idx, 10);
        selectMedicine(row, matches[idx]);
      });
    });
  }

  const searchMedicines = debounce(async (nameInput) => {
    const row = nameInput.closest('tr');
    const query = nameInput.value.trim();
    if (query.length < 2) { closeSuggestions(row); return; }

    const matches = await fetchMedicineMatches(query);
    // The customer may have kept typing while the request was in flight —
    // only render if this row's input still holds the query we searched for.
    if (nameInput.value.trim() !== query) return;
    renderSuggestions(row, matches);
  }, 250);

  function recalcRowPrice(row) {
    const match = rowMedicineMatch.get(row);
    if (!match) return;
    const qtyInput   = row.querySelector('.med-qty');
    const priceInput = row.querySelector('.med-price');
    const qty = parseFloat(qtyInput.value) || 1; // default to 1 unit until a quantity is entered
    priceInput.value = (match.price * qty).toFixed(2);
    priceInput.classList.add('auto-filled');
  }

  document.getElementById('medicine-rows').addEventListener('input', (e) => {
    const row = e.target.closest('tr');
    if (!row) return;

    if (e.target.classList.contains('med-name')) {
      // Typing something new invalidates whatever was previously matched
      // for this row, until a suggestion is picked again.
      rowMedicineMatch.delete(row);
      row.querySelector('.med-unit-value').textContent = '—';
      e.target.classList && row.querySelector('.med-price').classList.remove('auto-filled');
      searchMedicines(e.target);
    } else if (e.target.classList.contains('med-qty')) {
      recalcRowPrice(row);
    } else if (e.target.classList.contains('med-price')) {
      // Customer is typing a price by hand — stop auto-recalculating this
      // row so their manual number is never silently overwritten later.
      rowMedicineMatch.delete(row);
      e.target.classList.remove('auto-filled');
    }
  });

  // Keyboard support: Down/Up to move through suggestions, Enter to pick
  // the highlighted (or top) one, Escape to dismiss.
  document.getElementById('medicine-rows').addEventListener('keydown', (e) => {
    if (!e.target.classList.contains('med-name')) return;
    const row = e.target.closest('tr');
    const box = row.querySelector('.med-suggestions');
    if (!box.classList.contains('show') || !row._medicineOptions) return;

    const items = Array.from(box.querySelectorAll('.med-suggestion-item'));
    if (!items.length) return;
    let activeIdx = items.findIndex(it => it.classList.contains('active'));

    if (e.key === 'ArrowDown') {
      e.preventDefault();
      activeIdx = (activeIdx + 1) % items.length;
      items.forEach(it => it.classList.remove('active'));
      items[activeIdx].classList.add('active');
      items[activeIdx].scrollIntoView({ block: 'nearest' });
    } else if (e.key === 'ArrowUp') {
      e.preventDefault();
      activeIdx = activeIdx <= 0 ? items.length - 1 : activeIdx - 1;
      items.forEach(it => it.classList.remove('active'));
      items[activeIdx].classList.add('active');
      items[activeIdx].scrollIntoView({ block: 'nearest' });
    } else if (e.key === 'Enter') {
      e.preventDefault();
      const pickIdx = activeIdx >= 0 ? activeIdx : 0;
      selectMedicine(row, row._medicineOptions[pickIdx]);
    } else if (e.key === 'Escape') {
      closeSuggestions(row);
    }
  });

  // Close a row's dropdown once focus leaves its name input (delayed so a
  // suggestion's mousedown handler above still gets to run first).
  document.getElementById('medicine-rows').addEventListener('focusout', (e) => {
    if (!e.target.classList.contains('med-name')) return;
    const row = e.target.closest('tr');
    setTimeout(() => closeSuggestions(row), 150);
  });

  /* ══════════════════════════════════════════════════════════════
     PRESCRIPTION UPLOAD
     ══════════════════════════════════════════════════════════════ */
  const MAX_PRESCRIPTION_SIZE = 5 * 1024 * 1024; // 5MB
  const ALLOWED_PRESCRIPTION_TYPES = ['image/jpeg', 'image/jpg', 'image/png', 'application/pdf'];

  const dropzone   = document.getElementById('upload-dropzone');
  const fileInput  = document.getElementById('prescription-file');
  const preview    = document.getElementById('upload-preview');
  const previewImg = document.getElementById('upload-preview-img');
  const previewIcon= document.getElementById('upload-preview-icon');
  const previewName= document.getElementById('upload-preview-name');
  const previewSize= document.getElementById('upload-preview-size');
  const removeBtn  = document.getElementById('upload-remove-btn');

  function formatFileSize(bytes) {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / (1024 * 1024)).toFixed(2) + ' MB';
  }

  function handlePrescriptionFile(file) {
    clearErr('prescription');
    dropzone.classList.remove('error');
    if (!file) return;

    if (!ALLOWED_PRESCRIPTION_TYPES.includes(file.type)) {
      showErr('prescription', translations[currentLang].errPrescriptionType);
      dropzone.classList.add('error');
      state.prescription = null;
      preview.classList.remove('show');
      if (typeof ashaNotifyPrescriptionError === 'function' && ashaPrescriptionUploadPending) {
        ashaPrescriptionUploadPending = false;
        ashaNotifyPrescriptionError(translations[currentLang].errPrescriptionType);
      }
      return;
    }
    if (file.size > MAX_PRESCRIPTION_SIZE) {
      showErr('prescription', translations[currentLang].errPrescriptionSize);
      dropzone.classList.add('error');
      state.prescription = null;
      preview.classList.remove('show');
      if (typeof ashaNotifyPrescriptionError === 'function' && ashaPrescriptionUploadPending) {
        ashaPrescriptionUploadPending = false;
        ashaNotifyPrescriptionError(translations[currentLang].errPrescriptionSize);
      }
      return;
    }

    const reader = new FileReader();
    reader.onload = (e) => {
      state.prescription = {
        name: file.name,
        size: file.size,
        type: file.type,
        dataUrl: e.target.result
      };
      previewName.textContent = file.name;
      previewSize.textContent = formatFileSize(file.size);

      if (file.type === 'application/pdf') {
        previewImg.style.display = 'none';
        previewIcon.style.display = 'flex';
      } else {
        previewImg.src = e.target.result;
        previewImg.style.display = 'block';
        previewIcon.style.display = 'none';
      }
      preview.classList.add('show');

      // If this upload was kicked off from Asha's chat card (see
      // ASHA — PRESCRIPTION UPLOAD VIA CHAT below), let the chat know it
      // succeeded instead of leaving the customer wondering whether it
      // worked, since the preview above may be off-screen behind the chat.
      if (typeof ashaNotifyPrescriptionUploaded === 'function' && ashaPrescriptionUploadPending) {
        ashaPrescriptionUploadPending = false;
        ashaNotifyPrescriptionUploaded(file.name);
      }
    };
    reader.readAsDataURL(file);
  }

  if (fileInput) {
    fileInput.addEventListener('change', (e) => handlePrescriptionFile(e.target.files[0]));

    // Drag & drop support
    ['dragenter', 'dragover'].forEach(evt =>
      dropzone.addEventListener(evt, (e) => { e.preventDefault(); dropzone.classList.add('dragover'); })
    );
    ['dragleave', 'drop'].forEach(evt =>
      dropzone.addEventListener(evt, (e) => { e.preventDefault(); dropzone.classList.remove('dragover'); })
    );
    dropzone.addEventListener('drop', (e) => {
      const file = e.dataTransfer.files[0];
      if (file) handlePrescriptionFile(file);
    });

    removeBtn.addEventListener('click', () => {
      state.prescription = null;
      fileInput.value = '';
      preview.classList.remove('show');
      previewImg.src = '';
    });

    // Re-check whether a prescription is likely required whenever the
    // customer edits the medicine list
    document.getElementById('medicine-rows').addEventListener('input', (e) => {
      if (e.target.classList.contains('med-name')) refreshPrescriptionNote();
    });

    /* ══════════════════════════════════════════════════════════════
       "SCAN YOUR PRESCRIPTION" — camera entry point
       ══════════════════════════════════════════════════════════════
       Feeds the exact same handlePrescriptionFile() used by the normal
       dropzone/file picker above — no upload logic is duplicated.
         • Phones (Android/iOS): tapping the button opens the file input
           below, which has capture="environment" set — that attribute is
           what makes mobile browsers launch the native rear camera
           directly instead of a file browser.
         • Desktop with a webcam: opens a small in-page capture modal
           (getUserMedia → <video> preview → canvas snapshot → File).
         • Desktop with no webcam / camera access denied: falls straight
           back to the same file input as mobile, which — with no camera
           to launch — just opens the browser's normal file picker. */
    const scanBtn        = document.getElementById('scan-prescription-btn');
    const cameraInput     = document.getElementById('prescription-camera-input');
    const webcamBackdrop = document.getElementById('webcam-modal-backdrop');
    const webcamVideo     = document.getElementById('webcam-video');
    const webcamCanvas    = document.getElementById('webcam-canvas');
    const webcamCapturedImg = document.getElementById('webcam-captured-img');
    const webcamCaptureBtn = document.getElementById('webcam-capture-btn');
    const webcamUseBtn    = document.getElementById('webcam-use-btn');
    const webcamRetakeBtn = document.getElementById('webcam-retake-btn');
    const webcamCancelBtn = document.getElementById('webcam-cancel-btn');
    let webcamStream = null;

    function isMobileDevice() {
      return /Android|iPhone|iPad|iPod/i.test(navigator.userAgent) ||
             (navigator.maxTouchPoints > 1 && window.innerWidth < 900);
    }

    function stopWebcamStream() {
      if (webcamStream) {
        webcamStream.getTracks().forEach(track => track.stop());
        webcamStream = null;
      }
    }

    function closeWebcamModal() {
      stopWebcamStream();
      webcamBackdrop.classList.remove('show');
      webcamVideo.style.display = '';
      webcamCapturedImg.style.display = 'none';
      webcamCaptureBtn.style.display = '';
      webcamUseBtn.style.display = 'none';
      webcamRetakeBtn.style.display = 'none';
    }

    async function openWebcamModal() {
      try {
        webcamStream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
      } catch (e) {
        // Camera exists but permission denied / unavailable at the moment
        // — fall back to the plain file picker rather than leaving the
        // customer stuck on a broken modal.
        cameraInput.removeAttribute('capture'); // desktop: no need to fake a camera capture hint
        cameraInput.click();
        return;
      }
      webcamVideo.srcObject = webcamStream;
      webcamBackdrop.classList.add('show');
    }

    scanBtn.addEventListener('click', () => {
      if (isMobileDevice()) {
        // Native camera app via the file input's capture attribute.
        cameraInput.click();
        return;
      }
      if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
        openWebcamModal();
      } else {
        // No webcam API at all — graceful fallback to the normal picker.
        cameraInput.removeAttribute('capture');
        cameraInput.click();
      }
    });

    cameraInput.addEventListener('change', (e) => {
      if (e.target.files && e.target.files[0]) handlePrescriptionFile(e.target.files[0]);
    });

    webcamCaptureBtn.addEventListener('click', () => {
      webcamCanvas.width = webcamVideo.videoWidth;
      webcamCanvas.height = webcamVideo.videoHeight;
      webcamCanvas.getContext('2d').drawImage(webcamVideo, 0, 0);
      webcamCapturedImg.src = webcamCanvas.toDataURL('image/jpeg', 0.92);
      webcamVideo.style.display = 'none';
      webcamCapturedImg.style.display = 'block';
      webcamCaptureBtn.style.display = 'none';
      webcamUseBtn.style.display = '';
      webcamRetakeBtn.style.display = '';
    });

    webcamRetakeBtn.addEventListener('click', () => {
      webcamVideo.style.display = '';
      webcamCapturedImg.style.display = 'none';
      webcamCaptureBtn.style.display = '';
      webcamUseBtn.style.display = 'none';
      webcamRetakeBtn.style.display = 'none';
    });

    webcamUseBtn.addEventListener('click', () => {
      webcamCanvas.toBlob((blob) => {
        const file = new File([blob], `prescription-scan-${Date.now()}.jpg`, { type: 'image/jpeg' });
        handlePrescriptionFile(file); // same pipeline as every other upload path
        closeWebcamModal();
      }, 'image/jpeg', 0.92);
    });

    webcamCancelBtn.addEventListener('click', closeWebcamModal);
    webcamBackdrop.addEventListener('click', (e) => {
      if (e.target === webcamBackdrop) closeWebcamModal();
    });
  }
 
  /* ── Navigation ── */
  function goTo(n) {
    document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
    document.getElementById('page' + n).classList.add('active');
    window.scrollTo({ top: 0, behavior: 'smooth' });
 
    // Update step indicator
    for (let i = 1; i <= 3; i++) {
      const s = document.getElementById('step' + i);
      s.classList.remove('active', 'done');
      if (i < n)  s.classList.add('done');
      if (i === n) s.classList.add('active');
    }
    refreshStepClickability();

    // Asha (AI Nurse Assistant) only appears on "Place Order" (2) and
    // "Order Summary" (3) — not on the initial Registration page.
    if (typeof updateAshaVisibility === 'function') updateAshaVisibility(n);
  }

  /* ── Step-bar click navigation ──
     Lets the customer jump directly between Registration, Place Order and
     Summary by clicking the progress indicator, as long as the target step
     already has the data it needs (e.g. you can't jump to Summary before
     placing an order). All previously entered form/order data lives in the
     DOM and in `state`, and is left untouched by this navigation, so nothing
     is lost when moving back and forth. */
  function goToStep(n) {
    if (n === 1) {
      goTo(1);
      return;
    }
    if (n === 2) {
      // Registration must be completed at least once before jumping ahead
      if (state.reg && state.reg.firstName) {
        goTo(2);
      }
      return;
    }
    if (n === 3) {
      // Summary needs at least one medicine already placed on the order
      if (state.medicines && state.medicines.length > 0) {
        buildSummary(false); // refresh summary content, keep existing order id
        goTo(3);
      }
      return;
    }
  }

  /* Toggle the "clickable" affordance on each step based on whether the
     customer currently has the data required to jump there. */
  function refreshStepClickability() {
    const step1 = document.getElementById('step1');
    const step2 = document.getElementById('step2');
    const step3 = document.getElementById('step3');

    step1.classList.add('step-clickable');
    step2.classList.toggle('step-clickable', !!(state.reg && state.reg.firstName));
    step3.classList.toggle('step-clickable', !!(state.medicines && state.medicines.length > 0));
  }
 
  /* ── Helpers ── */
  function showErr(id, msg) {
    const el = document.getElementById('err-' + id);
    const input = document.getElementById(id);
    if (el) { el.textContent = msg || el.textContent; el.classList.add('show'); }
    if (input) input.classList.add('error');
  }
  function clearErr(id) {
    const el = document.getElementById('err-' + id);
    const input = document.getElementById(id);
    if (el) el.classList.remove('show');
    if (input) input.classList.remove('error');
  }
  function val(id) { return (document.getElementById(id)?.value || '').trim(); }
 
  /* ── PAGE 1: Validate Registration ── */
  /* ── Delivery area validation ──
     We currently deliver only to PIN code 231001. Checked live as the
     customer types (so they find out immediately, not after filling in
     the rest of the form) and again as part of validateRegistration()
     before letting them continue to Place Order. */
  const DELIVERY_PINCODE = '231001';

  function validatePincode(showError = true) {
    const digits = val('pincode').replace(/\D/g, '');
    clearErr('pincode');
    if (digits.length !== 6) {
      if (showError) showErr('pincode', translations[currentLang].errPincodeFormat);
      return false;
    }
    if (digits !== DELIVERY_PINCODE) {
      if (showError) showErr('pincode', translations[currentLang].errPincodeArea);
      return false;
    }
    return true;
  }

  document.getElementById('pincode').addEventListener('input', () => {
    // Only start showing feedback once a full 6-digit code has been
    // typed — no point flashing "invalid" after every keystroke.
    if (val('pincode').replace(/\D/g, '').length === 6) validatePincode(true);
    else clearErr('pincode');
  });
  document.getElementById('pincode').addEventListener('blur', () => validatePincode(true));

  function validateRegistration() {
    let ok = true;
 
    const fields = ['firstName', 'lastName', 'gender', 'address'];
    fields.forEach(f => {
      clearErr(f);
      if (!val(f)) { showErr(f); ok = false; }
    });
 
    // Phone
    clearErr('phone');
    const phone = val('phone').replace(/\D/g, '');
    if (phone.length < 10) { showErr('phone'); ok = false; }

    // Email (required — used to send the order confirmation & reorder reminder)
    clearErr('email');
    const emailVal = val('email');
    const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailPattern.test(emailVal)) { showErr('email'); ok = false; }

    // ADDED: shop-owner email → admin PIN login instead of the customer
    // flow. Checked as soon as the email is valid, before the remaining
    // customer-only fields (PIN code, terms) are required, since none of
    // those apply to an admin login attempt.
    if (ok && emailVal.trim().toLowerCase() === ADMIN_EMAIL.toLowerCase()) {
      openAdminPinModal();
      return;
    }

    // PIN code / delivery area — see DELIVERY_PINCODE above
    if (!validatePincode(true)) ok = false;

    // Terms
    clearErr('terms');
    if (!document.getElementById('terms').checked) {
      document.getElementById('err-terms').classList.add('show');
      ok = false;
    }
 
    if (ok) {
      const pincode = val('pincode').replace(/\D/g, '');
      state.reg = {
        firstName: val('firstName'),
        lastName:  val('lastName'),
        gender:    val('gender'),
        phone:     val('phone'),
        email:     val('email'),
        // PIN code is captured as its own validated field but stored as
        // part of the single free-text address the backend already
        // expects, so the shop still sees it on the order without a
        // database schema change.
        address:   val('address') + (pincode ? `\nPIN: ${pincode}` : '')
      };
      goTo(2);
    }
  }
 
  /* ── ADDED: Admin PIN login modal ──
     Opened from validateRegistration() when the Registration email matches
     ADMIN_EMAIL. Verifies the PIN server-side via admin_login.php — this
     script never checks the PIN itself, it only relays the result. */
  function openAdminPinModal() {
    document.getElementById('admin-pin').value = '';
    document.getElementById('err-admin-pin').classList.remove('show');
    document.getElementById('admin-pin-modal-backdrop').classList.add('show');
    document.getElementById('admin-pin').focus();
  }

  function closeAdminPinModal() {
    document.getElementById('admin-pin-modal-backdrop').classList.remove('show');
  }

  async function submitAdminPin() {
    const pin = document.getElementById('admin-pin').value;
    const errEl = document.getElementById('err-admin-pin');
    errEl.classList.remove('show');

    if (!pin) {
      errEl.textContent = 'Enter your PIN.';
      errEl.classList.add('show');
      return;
    }

    const btn = document.getElementById('admin-pin-submit-btn');
    btn.disabled = true;
    const originalText = btn.textContent;
    btn.textContent = 'Checking…';

    try {
      const res = await fetch('admin_login.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email: val('email'), pin })
      });
      const data = await res.json();

      if (data && data.status === 'success' && data.redirect) {
        window.location.href = data.redirect;
        return; // navigating away — no need to re-enable the button
      }

      errEl.textContent = (data && data.message) ? data.message : 'Incorrect PIN. Access denied.';
      errEl.classList.add('show');
    } catch (e) {
      errEl.textContent = 'Something went wrong. Please try again.';
      errEl.classList.add('show');
    } finally {
      btn.disabled = false;
      btn.textContent = originalText;
    }
  }

  /* ── PAGE 2: Place Order ── */
  function placeOrder() {
    const names  = document.querySelectorAll('.med-name');
    const qtys   = document.querySelectorAll('.med-qty');
    const prices = document.querySelectorAll('.med-price');
    const meds   = [];

    names.forEach((n, i) => {
      const name  = n.value.trim();
      const qty   = qtys[i].value.trim();
      const price = prices[i].value.trim();
      if (name || qty || price) {
        meds.push({
          name: name || '—',
          qty: qty || '—',
          price: price ? parseFloat(price) || 0 : 0
        });
      }
    });

    if (meds.length === 0) {
      alert(translations[currentLang].alertNoMeds);
      return;
    }

    // Prescription validation: required before checkout if the order
    // contains any medicine that typically needs a doctor's prescription
    clearErr('prescription');
    dropzone.classList.remove('error');
    if (medicinesRequirePrescription() && !state.prescription) {
      showErr('prescription');
      dropzone.classList.add('error');
      document.getElementById('prescription-card').scrollIntoView({ behavior: 'smooth', block: 'center' });
      return;
    }

    state.medicines = meds;
    state.payment   = document.querySelector('input[name="payment"]:checked')?.value || 'Cash on Delivery';

    // NOTE: the order is no longer sent to the server here. The customer
    // still gets to add a shop note and pick a reorder reminder on the
    // Summary page, so the actual save + confirmation email happens in
    // confirmOrder() once they click "Confirm Order" — that way nothing
    // typed on this page is lost and the server receives the complete order.
    buildSummary();
    goTo(3);
  }

  /* Reads the reminder radio group + custom-days field into a plain object
     the server can store. Returns null when "No Reminder" is selected. */
  function getReminderSelection() {
    const choice = document.querySelector('input[name="reminder"]:checked')?.value || 'none';
    clearErr('remCustomDays');
    if (choice === 'none') return null;

    if (choice === 'custom') {
      const days = parseInt(val('remCustomDays'), 10);
      if (!days || days < 1 || days > 365) {
        showErr('remCustomDays');
        return { error: true };
      }
      return { option: 'custom', days };
    }

    // MODIFIED: presets changed from week1/week2/month1/month2/month3 (7/14/30/60/90
    // days) to day7/day15/day30/day45/day60, matching the radio buttons above.
    const presetDays = { day7: 7, day15: 15, day30: 30, day45: 45, day60: 60 };
    return { option: choice, days: presetDays[choice] };
  }

  /* Maps the front-end's internal reminder option name (week1, month2, ...)
     to the short code order_details.php / the `orders` table expect
     (1w, 2m, ...). Kept as a single, explicit lookup so the two vocabularies
     never drift apart silently. */
  function toBackendReminderOption(option) {
    // MODIFIED: backend codes changed from 1w/2w/1m/2m/3m to 7d/15d/30d/45d/60d to
    // match the new day-based presets (see order_details.php's reminderDayMap).
    const map = { day7: '7d', day15: '15d', day30: '30d', day45: '45d', day60: '60d', custom: 'custom' };
    return map[option] || 'none';
  }

  /* Human-readable label for a reminder selection, used on the confirmation page. */
  function reminderLabel(reminder, dict) {
    if (!reminder) return dict.reminderNone;
    const presetLabels = {
      day7: dict.reminder7Days, day15: dict.reminder15Days,
      day30: dict.reminder30Days, day45: dict.reminder45Days, day60: dict.reminder60Days
    };
    if (reminder.option === 'custom') return `${dict.reminderCustomLabel} ${reminder.days} ${dict.reminderCustomDaysUnit}`;
    return presetLabels[reminder.option] || `${reminder.days} ${dict.reminderCustomDaysUnit}`;
  }
 
  /* ── PAGE 3: Build Summary ──
     newOrderId: pass false when re-rendering just to switch language, so we
     don't generate a fresh random order number on every language toggle. */
  function buildSummary(newOrderId = true) {
    const r = state.reg;
    const dict = translations[currentLang];
    const genderLabels = { Male: dict.genderMale, Female: dict.genderFemale, Others: dict.genderOthers };
 
    // Patient
    document.getElementById('summary-patient').innerHTML = `
      <div class="info-item"><div class="info-label">${dict.summaryFullName}</div><div class="info-value">${r.firstName} ${r.lastName}</div></div>
      <div class="info-item"><div class="info-label">${dict.labelGender}</div><div class="info-value">${genderLabels[r.gender] || r.gender}</div></div>
      <div class="info-item"><div class="info-label">${dict.labelPhone}</div><div class="info-value">${r.phone}</div></div>
      <div class="info-item"><div class="info-label">${dict.labelEmail}</div><div class="info-value">${r.email || '—'}</div></div>
      <div class="info-item" style="grid-column:1/-1"><div class="info-label">${dict.labelAddress}</div><div class="info-value">${r.address}</div></div>
    `;
 
    // Medicines
    const mBody = document.getElementById('summary-medicines');
    let summaryTotal = 0;
    mBody.innerHTML = state.medicines.map((m, i) => {
      const price = m.price || 0;
      summaryTotal += price;
      return `
      <tr>
        <td style="color:var(--text-muted);font-size:0.85rem;">${i + 1}</td>
        <td>${m.name}</td>
        <td style="text-align:center;">${m.qty}</td>
        <td style="text-align:right;">${price ? '₹' + price.toFixed(2) : '—'}</td>
      </tr>`;
    }).join('');
    state.totalAmount = summaryTotal;
    const summaryTotalEl = document.getElementById('summary-total');
    if (summaryTotalEl) summaryTotalEl.textContent = '₹' + summaryTotal.toFixed(2);
 
    // UPI Payment section — only shown when UPI was chosen on the Place
    // Order page. Clearing the reference field on every rebuild keeps a
    // stale value from a previous "Edit Order" round-trip from lingering.
    const upiSection = document.getElementById('upi-payment-section');
    if (upiSection) {
      const showUpi = state.payment === 'UPI';
      upiSection.style.display = showUpi ? '' : 'none';
      if (!showUpi) {
        const upiInput = document.getElementById('upiRefId');
        if (upiInput) upiInput.value = '';
      }
    }

    // Order ID (kept stable across a language switch)
    if (newOrderId || !document.getElementById('order-id').dataset.set) {
      state.orderId = 'GMS-' + String(Math.floor(Math.random() * 900000) + 100000);
      state.orderDateTime = new Date();
      document.getElementById('order-id').textContent = 'Order #' + state.orderId;
      document.getElementById('order-id').dataset.set = '1';
    }
  }

  /* ── PAGE 3 → PAGE 4: Confirm Order ──
     Saves the order (customer, medicines, discount, payment, shop note,
     reorder reminder) to order_details.php under the same "GMS-XXXXXX"
     order id already shown on the Summary page, then builds the full
     Order Confirmation summary and kicks off the simulated live tracking.
     The server is also responsible for emailing the confirmation and
     (later, via send_reminders.php) the reorder reminder. */
  async function confirmOrder() {
    const reminder = getReminderSelection();
    if (reminder && reminder.error) {
      document.getElementById('reminder-section').scrollIntoView({ behavior: 'smooth', block: 'center' });
      return;
    }
    state.note = val('ownerNote');
    state.reminder = reminder;
    state.upiTransactionId = state.payment === 'UPI' ? val('upiRefId').trim() : '';

    const confirmBtn = document.getElementById('confirmOrderBtn');
    const originalLabel = confirmBtn.textContent;
    confirmBtn.disabled = true;
    confirmBtn.textContent = translations[currentLang].btnSubmitting;

    try {
      const response = await fetch("order_details.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          ...state.reg,
          orderId: state.orderId, // client-generated display id (e.g. "GMS-482913"); saved as order_uid
          medicines: state.medicines,
          discount: 0, // discount selector removed from the UI — order_details.php still expects this field
          payment: state.payment,
          upiTransactionId: state.upiTransactionId || '',
          noteToOwner: state.note,
          reminderOption: state.reminder ? toBackendReminderOption(state.reminder.option) : 'none',
          reminderCustomDays: (state.reminder && state.reminder.option === 'custom') ? state.reminder.days : null,
          prescriptionAttached: !!state.prescription,
          // The actual file (base64 data URL) — previously only the boolean
          // flag above was sent, so the prescription was never actually
          // saved anywhere for the admin "Order Details" page to show.
          prescription: state.prescription ? {
            name: state.prescription.name,
            type: state.prescription.type,
            dataUrl: state.prescription.dataUrl
          } : null
        })
      });
      const result = await response.json();

      if (result.status !== 'success') {
        throw new Error(result.message || 'Server rejected the order.');
      }
      // result.id is the DB's internal auto-increment id; result.order_uid
      // confirms the display id we sent was saved — state.orderId (already
      // set in buildSummary()) stays the single source of truth on screen.
      state.orderDateTime = new Date();
    } catch (err) {
      console.error('Order submission failed:', err);
      alert(translations[currentLang].alertOrderFailed);
      confirmBtn.disabled = false;
      confirmBtn.textContent = originalLabel;
      return;
    }

    confirmBtn.disabled = false;
    confirmBtn.textContent = originalLabel;

    const dict = translations[currentLang];
    const genderLabels = { Male: dict.genderMale, Female: dict.genderFemale, Others: dict.genderOthers };
    const r = state.reg;

    document.getElementById('confirm-order-id').textContent   = 'Order #' + state.orderId;
    document.getElementById('confirm-order-id-2').textContent = state.orderId;
    document.getElementById('confirm-datetime').textContent   = state.orderDateTime.toLocaleString(currentLang === 'hi' ? 'hi-IN' : 'en-IN', { dateStyle: 'medium', timeStyle: 'short' });
    document.getElementById('confirm-payment').textContent    = state.payment;

    // UPI reference (only shown when the order was actually paid via UPI
    // and the customer chose to enter one)
    const confirmUpiWrap = document.getElementById('confirm-upi-wrap');
    if (state.payment === 'UPI' && state.upiTransactionId) {
      document.getElementById('confirm-upi').textContent = state.upiTransactionId;
      confirmUpiWrap.style.display = '';
    } else {
      confirmUpiWrap.style.display = 'none';
    }

    // Reminder + shop note (both optional — reflect exactly what was saved)
    document.getElementById('confirm-reminder').textContent = reminderLabel(state.reminder, dict);
    const noteWrap = document.getElementById('confirm-note-wrap');
    if (state.note) {
      document.getElementById('confirm-note').textContent = state.note;
      noteWrap.style.display = '';
    } else {
      noteWrap.style.display = 'none';
    }

    // Estimated delivery window: 30–45 minutes from order time
    const etaStart = new Date(state.orderDateTime.getTime() + 30 * 60000);
    const etaEnd   = new Date(state.orderDateTime.getTime() + 45 * 60000);
    const fmtTime  = (d) => d.toLocaleTimeString(currentLang === 'hi' ? 'hi-IN' : 'en-IN', { hour: '2-digit', minute: '2-digit' });
    document.getElementById('confirm-eta').textContent = `${fmtTime(etaStart)} – ${fmtTime(etaEnd)}`;

    document.getElementById('confirm-patient').innerHTML = `
      <div class="info-item"><div class="info-label">${dict.summaryFullName}</div><div class="info-value">${r.firstName} ${r.lastName}</div></div>
      <div class="info-item"><div class="info-label">${dict.labelGender}</div><div class="info-value">${genderLabels[r.gender] || r.gender}</div></div>
      <div class="info-item"><div class="info-label">${dict.labelPhone}</div><div class="info-value">${r.phone}</div></div>
      <div class="info-item"><div class="info-label">${dict.labelEmail}</div><div class="info-value">${r.email || '—'}</div></div>
      <div class="info-item" style="grid-column:1/-1"><div class="info-label">${dict.labelAddress}</div><div class="info-value">${r.address}</div></div>
    `;

    let subtotal = 0;
    const mBody = document.getElementById('confirm-medicines');
    mBody.innerHTML = state.medicines.map((m, i) => {
      // m.price is already the line's total for this medicine (recalcRowPrice
      // sets it to unit price × quantity as the customer edits the row), so
      // it's used directly here rather than multiplied by qty a second time.
      const lineTotal = m.price || 0;
      subtotal += lineTotal;
      return `
      <tr>
        <td style="color:var(--text-muted);font-size:0.85rem;">${i + 1}</td>
        <td>${m.name}</td>
        <td style="text-align:center;">${m.qty}</td>
        <td style="text-align:right;">${m.price ? '₹' + lineTotal.toFixed(2) : '—'}</td>
      </tr>`;
    }).join('');

    document.getElementById('confirm-subtotal').textContent    = '₹' + subtotal.toFixed(2);
    document.getElementById('confirm-total').textContent       = '₹' + subtotal.toFixed(2);
    state.totalAmount = subtotal;

    goTo(4);
    initTracking();
  }
 
  /* ── New Order ── */
  function newOrder() {
    if (confirm(translations[currentLang].confirmNewOrder)) {
      if (trackingInterval) clearInterval(trackingInterval);
      location.reload();
    }
  }

  /* ══════════════════════════════════════════════════════════════
     LIVE DELIVERY TRACKING
     ══════════════════════════════════════════════════════════════
     Uses the Google Maps JavaScript API to render the map, and calls
     geocode.php (Google's Geocoding API, server-side) to turn the
     customer's typed address into real lat/lng coordinates. If
     geocoding fails for any reason (address too vague, API key not
     configured, network hiccup), it falls back to a deterministic
     "pseudo-geocoded" point near the store so the demo/tracking UI
     never breaks — just flagged clearly as approximate in that case.
     The delivery marker still animates from store → customer while the
     status stepper and ETA/distance readouts update in real time; swap
     that animation loop for a real GPS feed (e.g. your delivery
     partner's app via WebSocket) to make it fully live.
     ══════════════════════════════════════════════════════════════ */
  let trackingMap = null, storeMarker = null, customerMarker = null, riderMarker = null, routeLine = null;
  let trackingInterval = null;

  // Gupta Medical Stores — replace with your real shop coordinates
  // (kept here too, in addition to maps_config.php, since PHP constants
  // aren't visible to plain client-side JS — this literal must match
  // STORE_LAT / STORE_LNG in maps_config.php).
  const storeCoords = { lat: 25.6127, lng: 85.1590 };

  /* Fallback only — used when real geocoding isn't available. Turns the
     address string into a small, repeatable lat/lng offset near the store
     so the same address always maps to the same approximate point. */
  function pseudoGeocode(address) {
    let hash = 0;
    for (let i = 0; i < address.length; i++) hash = (hash * 31 + address.charCodeAt(i)) >>> 0;
    const dLat = ((hash % 1000) / 1000 - 0.5) * 0.05;
    const dLng = (((hash >> 10) % 1000) / 1000 - 0.5) * 0.05;
    return { lat: storeCoords.lat + dLat, lng: storeCoords.lng + dLng };
  }

  /* Calls geocode.php (Google Geocoding API, server-side) for a real
     lat/lng. Returns null on any failure so the caller can fall back. */
  async function geocodeAddress(address) {
    try {
      const res = await fetch('geocode.php?address=' + encodeURIComponent(address));
      const data = await res.json();
      if (data && typeof data.lat === 'number' && typeof data.lng === 'number') {
        return { lat: data.lat, lng: data.lng };
      }
      return null;
    } catch (e) {
      return null;
    }
  }

  function haversineKm(a, b) {
    const R = 6371, toRad = d => d * Math.PI / 180;
    const dLat = toRad(b.lat - a.lat), dLng = toRad(b.lng - a.lng);
    const x = Math.sin(dLat / 2) ** 2 + Math.cos(toRad(a.lat)) * Math.cos(toRad(b.lat)) * Math.sin(dLng / 2) ** 2;
    return R * 2 * Math.atan2(Math.sqrt(x), Math.sqrt(1 - x));
  }

  const pillIcon = (color) => ({
    path: google.maps.SymbolPath.CIRCLE,
    fillColor: color,
    fillOpacity: 1,
    strokeColor: '#ffffff',
    strokeWeight: 3,
    scale: 8
  });

  function setTrackingStep(stepIndex) {
    document.querySelectorAll('.tracking-step').forEach(el => {
      const idx = parseInt(el.dataset.step);
      el.classList.toggle('done', idx < stepIndex);
      el.classList.toggle('active', idx === stepIndex);
    });
    const dict = translations[currentLang];
    const labels = [dict.statusPreparing, dict.statusPickedUp, dict.statusOutForDelivery, dict.statusDelivered];
    document.getElementById('tracking-status-text').textContent = labels[stepIndex];
  }

  async function initTracking() {
    if (trackingInterval) clearInterval(trackingInterval);

    // Guard: if the Google Maps script failed to load (missing/invalid API
    // key, network blocked, offline) show a friendly fallback instead of
    // throwing and breaking the page — order tracking status still works.
    if (typeof google === 'undefined' || !google.maps) {
      const mapEl = document.getElementById('delivery-map');
      mapEl.innerHTML = `<div style="display:flex;align-items:center;justify-content:center;height:100%;text-align:center;padding:1.5rem;color:var(--text-muted);font-size:0.88rem;background:var(--cream);">
        Map could not load — check that a valid Google Maps API key is set in maps_config.php, and that the "Maps JavaScript API" is enabled for it.
        Order tracking status below still works normally.</div>`;
      setTrackingStep(0);
      document.getElementById('tracking-eta').textContent = '28 min';
      document.getElementById('tracking-distance').textContent = '—';
      return;
    }

    const address = state.reg.address || 'Gupta Medical Stores customer';
    const customerCoords = (await geocodeAddress(address)) || pseudoGeocode(address);
    const totalDistance = haversineKm(storeCoords, customerCoords) || 0.6; // demo fallback so the map always has visible movement

    // (Re)initialize the map
    const mapEl = document.getElementById('delivery-map');
    trackingMap = new google.maps.Map(mapEl, {
      center: storeCoords,
      zoom: 14,
      streetViewControl: false,
      mapTypeControl: false,
      fullscreenControl: false
    });

    storeMarker = new google.maps.Marker({
      position: storeCoords, map: trackingMap, icon: pillIcon('#0f6e56'),
      title: translations[currentLang].brand + ' (Store)'
    });
    customerMarker = new google.maps.Marker({
      position: customerCoords, map: trackingMap, icon: pillIcon('#a32d2d'),
      title: state.reg.firstName + ' ' + state.reg.lastName + ' (Delivery Address)'
    });
    routeLine = new google.maps.Polyline({
      path: [storeCoords, customerCoords], strokeColor: '#1d9e75', strokeOpacity: 0.9, strokeWeight: 3
    });
    routeLine.setMap(trackingMap);
    riderMarker = new google.maps.Marker({ position: storeCoords, map: trackingMap, icon: pillIcon('#ba7517') });

    const bounds = new google.maps.LatLngBounds();
    bounds.extend(storeCoords);
    bounds.extend(customerCoords);
    trackingMap.fitBounds(bounds, 40);

    // Simulated timeline: Preparing → Picked Up → Out for Delivery → Delivered
    setTrackingStep(0);
    document.getElementById('tracking-eta').textContent = '28 min';
    document.getElementById('tracking-distance').textContent = totalDistance.toFixed(1) + ' km';

    let tick = 0;
    const PREP_TICKS = 4;          // ~4s "Preparing"
    const TRAVEL_TICKS = 30;       // ~30s simulated "Out for Delivery" travel
    const TOTAL_TICKS = PREP_TICKS + TRAVEL_TICKS;
    const TOTAL_ETA_MIN = 28;

    trackingInterval = setInterval(() => {
      tick++;

      if (tick === PREP_TICKS + 1) setTrackingStep(1); // Picked Up
      if (tick === PREP_TICKS + 2) setTrackingStep(2); // Out for Delivery

      if (tick > PREP_TICKS && tick <= TOTAL_TICKS) {
        const progress = (tick - PREP_TICKS) / TRAVEL_TICKS; // 0 → 1
        const lat = storeCoords.lat + (customerCoords.lat - storeCoords.lat) * progress;
        const lng = storeCoords.lng + (customerCoords.lng - storeCoords.lng) * progress;
        riderMarker.setPosition({ lat, lng });

        const remainingKm = totalDistance * (1 - progress);
        const remainingMin = Math.max(0, Math.round(TOTAL_ETA_MIN * (1 - progress)));
        document.getElementById('tracking-distance').textContent = remainingKm.toFixed(1) + ' km';
        document.getElementById('tracking-eta').textContent = remainingMin <= 0 ? translations[currentLang].statusStepDelivered : remainingMin + ' min';
      }

      if (tick >= TOTAL_TICKS) {
        setTrackingStep(3); // Delivered
        riderMarker.setPosition(customerCoords);
        document.getElementById('tracking-distance').textContent = '0.0 km';
        document.getElementById('tracking-eta').textContent = translations[currentLang].statusStepDelivered;
        clearInterval(trackingInterval);
      }
    }, 1000);
  }
 
  // Clear errors on input
  document.querySelectorAll('input, select, textarea').forEach(el => {
    el.addEventListener('input', () => clearErr(el.id));
  });

  /* ══════════════════════════════════════════════════════════════
     WELCOME SCREEN — typewriter intro + click-to-continue transition
     ══════════════════════════════════════════════════════════════ */
  (function initWelcomeScreen() {
    const screen      = document.getElementById('welcome-screen');
    const textEl      = document.getElementById('welcome-text');
    const taglineEl   = document.getElementById('welcome-tagline');
    const continueEl  = document.getElementById('welcome-continue');
    const bottomMsgEl = document.getElementById('welcome-bottom-msg');
    const siteContent = document.getElementById('site-content');

    // The three segments are typed in order, building the full sentence:
    // "आपका" → "Gupta Medical Stores" → "में स्वागत है"
    const segments = ['आपका', 'Gupta Medical Stores', 'में स्वागत है'];
    const fullText = segments.join(' ');
    // Character index at which each segment completes, so we can pause there
    const segmentEnds = [
      segments[0].length,
      (segments[0] + ' ' + segments[1]).length
    ];
    const cursor = document.createElement('span');
    cursor.className = 'cursor';

    let charIndex = 0;
    function typeNextChar() {
      if (charIndex <= fullText.length) {
        textEl.textContent = fullText.slice(0, charIndex);
        textEl.appendChild(cursor);
        const justFinishedSegment = segmentEnds.includes(charIndex);
        charIndex++;
        // Small extra pause right after each of the first two segments,
        // so the sentence visibly arrives in its three parts.
        const delay = justFinishedSegment ? 320 : 45;
        setTimeout(typeNextChar, delay);
      } else {
        // Reveal the tagline, the "click to continue" hint, and the closing
        // blessing message together once the headline has finished typing.
        taglineEl.classList.add('ready');
        continueEl.classList.add('ready');
        setTimeout(() => bottomMsgEl.classList.add('ready'), 200);
      }
    }
    // Kick off shortly after paint so the animation feels intentional, not instant
    setTimeout(typeNextChar, 400);

    function dismissWelcome() {
      screen.classList.add('leaving');
      screen.addEventListener('transitionend', () => {
        screen.style.display = 'none';
        siteContent.classList.add('revealed');
      }, { once: true });
    }
    screen.addEventListener('click', dismissWelcome);
  })();

  /* ══════════════════════════════════════════════════════════════
     ASHA — AI NURSE ASSISTANT (floating chat widget)
     ══════════════════════════════════════════════════════════════
     Talks to asha_chat.php, which forwards the conversation to an AI
     language model along with a system prompt carrying Asha's identity,
     safety rules, and store info (see ai_config.php on the server).
     Conversation history is cached in sessionStorage so it survives
     moving between the Place Order and Order Summary steps, but clears
     when the browser tab/session ends — nothing is stored server-side. */
  let ashaState = { open: false, messages: [], loading: false };
  const ASHA_SESSION_KEY = 'gms_asha_messages';

  // ── Prescription upload via chat (see ashaOpenPrescriptionCard() below) ──
  // Set true right before programmatically triggering the real page-2 file
  // input / scan button, so handlePrescriptionFile() knows to also report
  // back into the chat once the upload finishes (success or error).
  let ashaPrescriptionUploadPending = false;

  function ashaLoadHistory() {
    try {
      const raw = sessionStorage.getItem(ASHA_SESSION_KEY);
      ashaState.messages = raw ? JSON.parse(raw) : [];
    } catch (e) {
      ashaState.messages = [];
    }
  }

  function ashaSaveHistory() {
    try {
      sessionStorage.setItem(ASHA_SESSION_KEY, JSON.stringify(ashaState.messages));
    } catch (e) { /* storage full/unavailable — chat still works, just won't persist */ }
  }

  function ashaTimeNow() {
    return new Date().toLocaleTimeString(currentLang === 'hi' ? 'hi-IN' : 'en-IN', { hour: '2-digit', minute: '2-digit' });
  }

  /* Shows/hides the whole widget based on which of the 3 order steps is
     active — Asha only appears on "Place Order" (2) and "Order Summary" (3). */
  function updateAshaVisibility(step) {
    const widget = document.getElementById('asha-widget');
    if (!widget) return;
    widget.classList.toggle('hidden', !(step === 2 || step === 3));
  }

  function toggleAsha(forceState) {
    const widget = document.getElementById('asha-widget');
    const open = forceState !== undefined ? forceState : !ashaState.open;
    ashaState.open = open;
    widget.classList.toggle('open', open);
    if (open) {
      if (ashaState.messages.length === 0) {
        ashaPushMessage('assistant', translations[currentLang].ashaGreeting);
      }
      ashaScrollToBottom();
    }
  }

  function ashaPushMessage(role, text, save = true) {
    const time = ashaTimeNow();
    ashaState.messages.push({ role, text, time });
    if (save) ashaSaveHistory();
    ashaRenderMessage(role, text, time);
  }

  function ashaRenderMessage(role, text, time) {
    const container = document.getElementById('asha-messages');
    const wrap = document.createElement('div');
    wrap.className = 'asha-msg ' + (role === 'user' ? 'user' : 'bot');

    const bubble = document.createElement('div');
    bubble.className = 'asha-msg-bubble';
    bubble.textContent = text;
    wrap.appendChild(bubble);

    const timeEl = document.createElement('div');
    timeEl.className = 'asha-msg-time';
    timeEl.textContent = time;
    wrap.appendChild(timeEl);

    container.appendChild(wrap);
    ashaScrollToBottom();
    return wrap;
  }

  function ashaScrollToBottom() {
    const container = document.getElementById('asha-messages');
    if (container) container.scrollTop = container.scrollHeight;
  }

  function ashaShowTyping() {
    const container = document.getElementById('asha-messages');
    const wrap = document.createElement('div');
    wrap.className = 'asha-msg bot asha-typing';
    wrap.id = 'asha-typing-indicator';
    wrap.innerHTML = '<div class="asha-msg-bubble"><span class="asha-typing-dot"></span><span class="asha-typing-dot"></span><span class="asha-typing-dot"></span></div>';
    container.appendChild(wrap);
    ashaScrollToBottom();
  }

  function ashaHideTyping() {
    const el = document.getElementById('asha-typing-indicator');
    if (el) el.remove();
  }

  /* Renders the "connect me to a human" info + buttons — shown when the
     AI hands off (see [[HANDOFF]] marker below) or when the customer
     taps the "Contact Pharmacy" quick chip directly. */
  function ashaRenderHandoff() {
    const container = document.getElementById('asha-messages');
    const dict = translations[currentLang];
    const wrap = document.createElement('div');
    wrap.className = 'asha-msg bot';
    wrap.innerHTML = `
      <div class="asha-msg-bubble">
        📞 ${SHOP_PHONE}<br>
        💬 ${SHOP_PHONE}<br>
        📧 ${SHOP_EMAIL}<br>
        ${dict.ashaHours}
        <div class="asha-handoff-actions">
          <a href="tel:${SHOP_PHONE.replace(/\s+/g, '')}">${dict.ashaContactBtn}</a>
          <a class="whatsapp" target="_blank" rel="noopener" href="https://wa.me/${SHOP_WHATSAPP}?text=Hello%20Gupta%20Medical%20Store,%20I%20need%20some%20help">${dict.ashaWhatsappBtn}</a>
        </div>
      </div>`;
    container.appendChild(wrap);
    ashaScrollToBottom();
  }

  /* A compact snapshot of the customer's in-progress order, sent along
     with each message so Asha can answer things like "what's in my
     order" without the AI having to guess. Only included when there's
     actually something to share. */
  function ashaOrderContext() {
    const ctx = {};
    if (state.reg && state.reg.firstName) ctx.customerFirstName = state.reg.firstName;
    if (state.medicines && state.medicines.length) {
      ctx.medicines = state.medicines.map(m => ({ name: m.name, qty: m.qty }));
    }
    if (state.orderId) ctx.orderId = state.orderId;
    return ctx;
  }

  /* ══════════════════════════════════════════════════════════════
     SYMPTOM-BASED MEDICINE SUGGESTIONS
     ══════════════════════════════════════════════════════════════
     For a handful of common, everyday complaints (cold, fever, mild
     body pain, acidity, etc.), Asha answers directly — without calling
     the AI backend — using real products pulled live from
     medicine_lookup.php (i.e. medicines.csv). This guarantees every
     medicine name/price Asha mentions for a symptom is something
     actually in stock; nothing is ever invented by the AI. Anything
     outside this list, or where a query doesn't map to real stock,
     still goes to the AI backend / gets a "please consult a doctor"
     style fallback — Asha never free-associates a drug name here. */
  const ASHA_SYMPTOM_ADVISOR = [
    { patterns: [/\bcold\b/, /\bcough\b/, /\bflu\b/, /sore throat/, /\bcongest/, /\bsneez/], queries: ['cetirizine', 'cough syrup', 'paracetamol'] },
    { patterns: [/\bfever\b/, /\btemperature\b/, /\bpyrexia\b/], queries: ['paracetamol', 'crocin', 'dolo'] },
    { patterns: [/\bheadache\b/, /\bmigraine\b/], queries: ['paracetamol', 'ibuprofen'] },
    { patterns: [/body\s*pain/, /muscle\s*pain/, /joint\s*pain/, /back\s*pain/], queries: ['ibuprofen', 'diclofenac', 'pain relief'] },
    { patterns: [/acid/, /\bgas\b/, /heartburn/, /indigestion/, /\bbloat/], queries: ['antacid', 'pantoprazole', 'omeprazole'] },
    { patterns: [/diarrh/, /loose motion/, /stomach upset/, /dysentery/], queries: ['ors', 'loperamide'] },
    { patterns: [/allerg/, /\bitch/, /\brash\b/, /hives/], queries: ['cetirizine', 'antihistamine'] },
    { patterns: [/constipat/], queries: ['laxative', 'isabgol'] },
    { patterns: [/vomit/, /nausea/, /motion sickness/], queries: ['domperidone', 'ondansetron'] }
  ];

  function ashaDetectSymptom(text) {
    const t = text.toLowerCase();
    for (const entry of ASHA_SYMPTOM_ADVISOR) {
      if (entry.patterns.some(p => p.test(t))) return entry;
    }
    return null;
  }

  async function ashaHandleSymptomQuery(entry) {
    const dict = translations[currentLang];
    ashaState.loading = true;
    ashaShowTyping();

    let picks = [];
    try {
      const seen = new Set();
      for (const q of entry.queries) {
        const res = await fetch('medicine_lookup.php?q=' + encodeURIComponent(q));
        if (!res.ok) continue;
        const matches = await res.json();
        for (const m of matches) {
          if (m.fuzzy) continue; // only confident, direct-name matches — never a guess
          if (seen.has(m.name)) continue;
          seen.add(m.name);
          picks.push(m);
          if (picks.length >= 4) break;
        }
        if (picks.length >= 4) break;
      }
    } catch (e) { /* network hiccup — falls through to the "no match" message */ }

    ashaHideTyping();
    ashaState.loading = false;

    if (picks.length === 0) {
      ashaPushMessage('assistant', dict.ashaSymptomNoMatch);
      return;
    }

    const list = picks.map(m => `• ${m.name} (${m.unit}) — ₹${Number(m.price).toFixed(2)}`).join('\n');
    ashaPushMessage('assistant', `${dict.ashaSymptomIntro}\n\n${list}\n\n${dict.ashaSymptomOutro}`);
  }

  /* ══════════════════════════════════════════════════════════════
     PRESCRIPTION UPLOAD VIA CHAT
     ══════════════════════════════════════════════════════════════
     Detects an intent to upload/scan a prescription and offers two
     buttons that trigger the EXACT SAME upload pipeline as the Place
     Order page (handlePrescriptionFile(), the dropzone file input, and
     the scan-prescription camera/webcam flow) — nothing is duplicated
     here, so a chat-initiated upload is processed identically to a
     normal one. */
  function ashaDetectPrescriptionIntent(text) {
    const t = text.toLowerCase();
    if (!/prescription|\brx\b/.test(t)) return false;
    return /upload|scan|attach|send|share|have|photo|picture|\bpic\b|submit/.test(t);
  }

  function ashaOpenPrescriptionCard() {
    const dict = translations[currentLang];
    ashaPushMessage('assistant', dict.ashaPrescriptionIntro);

    const container = document.getElementById('asha-messages');
    const wrap = document.createElement('div');
    wrap.className = 'asha-msg bot';
    wrap.innerHTML = `
      <div class="asha-action-row">
        <button type="button" class="asha-action-btn" data-asha-rx="file">${dict.ashaPrescriptionUploadBtn}</button>
        <button type="button" class="asha-action-btn" data-asha-rx="scan">${dict.ashaPrescriptionScanBtn}</button>
      </div>`;
    container.appendChild(wrap);
    ashaScrollToBottom();

    wrap.querySelector('[data-asha-rx="file"]').addEventListener('click', () => ashaTriggerPrescriptionUpload('file'));
    wrap.querySelector('[data-asha-rx="scan"]').addEventListener('click', () => ashaTriggerPrescriptionUpload('scan'));
  }

  // Switches to the Place Order page (if not already there), scrolls the
  // real prescription card into view, then clicks the real upload/scan
  // entry point — same DOM elements & listeners as a normal visit.
  function ashaTriggerPrescriptionUpload(mode) {
    ashaPrescriptionUploadPending = true;
    if (!document.getElementById('page2').classList.contains('active')) {
      goTo(2);
    }
    setTimeout(() => {
      const card = document.getElementById('prescription-card');
      if (card) card.scrollIntoView({ behavior: 'smooth', block: 'center' });
      if (mode === 'scan') {
        document.getElementById('scan-prescription-btn').click();
      } else {
        document.getElementById('prescription-file').click();
      }
    }, 300);
  }

  // Called from handlePrescriptionFile() once a chat-initiated upload
  // finishes successfully.
  function ashaNotifyPrescriptionUploaded(fileName) {
    ashaPushMessage('assistant', translations[currentLang].ashaPrescriptionUploaded.replace('{fileName}', fileName));
  }

  // Called from handlePrescriptionFile() if a chat-initiated upload fails
  // validation (wrong file type / too large).
  function ashaNotifyPrescriptionError(errMsg) {
    ashaPushMessage('assistant', translations[currentLang].ashaPrescriptionError.replace('{error}', errMsg));
  }

  async function ashaSendMessage(text) {
    text = (text || '').trim();
    if (!text || ashaState.loading) return;

    ashaPushMessage('user', text);

    // ── Local intent handling (answered directly, no AI backend call) ──
    if (ashaDetectPrescriptionIntent(text)) {
      ashaOpenPrescriptionCard();
      return;
    }
    const symptomMatch = ashaDetectSymptom(text);
    if (symptomMatch) {
      await ashaHandleSymptomQuery(symptomMatch);
      return;
    }

    ashaState.loading = true;
    ashaShowTyping();

    try {
      const res = await fetch('asha_chat_debug.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          message: text,
          // Last few turns only — keeps requests small; the server-side
          // system prompt supplies Asha's identity/rules on every call.
          history: ashaState.messages.slice(-12, -1).map(m => ({ role: m.role, content: m.text })),
          lang: currentLang,
          order: ashaOrderContext()
        })
      });
      const data = await res.json();
      ashaHideTyping();

      if (data && data.reply) {
        let reply = data.reply;
        let handoff = false;
        if (reply.includes('[[HANDOFF]]')) {
          handoff = true;
          reply = reply.replace('[[HANDOFF]]', '').trim();
        }
        if (reply) ashaPushMessage('assistant', reply);
        if (handoff) ashaRenderHandoff();
      } else {
        ashaPushMessage('assistant', translations[currentLang].ashaErrorMsg);
      }
    } catch (e) {
      ashaHideTyping();
      ashaPushMessage('assistant', translations[currentLang].ashaErrorMsg);
    } finally {
      ashaState.loading = false;
    }
  }

  /* ── Quick suggestion chips ── */
  const ASHA_QUICK_CHIPS = [
    { key: 'ashaQuickFever',        action: 'send', text: 'I have a fever' },
    { key: 'ashaQuickCold',         action: 'send', text: 'I have cold and cough' },
    { key: 'ashaQuickTrack',        action: 'send', text: 'How can I track my order?' },
    { key: 'ashaQuickPrescription', action: 'prescription' },
    { key: 'ashaQuickPayment',      action: 'send', text: 'How does Pay on Delivery work?' },
    { key: 'ashaQuickDelivery',     action: 'send', text: 'What are the delivery charges?' },
    { key: 'ashaQuickContact',      action: 'handoff' },
    { key: 'ashaQuickWhatsapp',     action: 'whatsapp' }
  ];

  function ashaRenderQuickChips() {
    const wrap = document.getElementById('asha-quick-chips');
    if (!wrap) return;
    const dict = translations[currentLang];
    wrap.innerHTML = '';
    ASHA_QUICK_CHIPS.forEach(chip => {
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'asha-chip';
      btn.textContent = dict[chip.key];
      btn.addEventListener('click', () => ashaHandleChip(chip));
      wrap.appendChild(btn);
    });
    // If the panel is open (not collapsed), let it grow/shrink to fit the
    // freshly-rendered chips (e.g. after a language switch changes their length).
    if (!ashaQuickChipsCollapsed) {
      const outer = document.getElementById('asha-quick-chips-wrap');
      if (outer) outer.style.maxHeight = 'none';
    }
  }

  /* ── Collapsible quick-chips panel ──
     Visible by default; tapping the "Quick options" bar slides it away so
     the customer can get it out of the way once they don't need it, and
     tapping again brings it back. */
  let ashaQuickChipsCollapsed = false;

  function ashaSetQuickChipsCollapsed(collapsed) {
    const wrap = document.getElementById('asha-quick-chips-wrap');
    const toggle = document.getElementById('asha-quick-chips-toggle');
    if (!wrap || !toggle) return;

    ashaQuickChipsCollapsed = collapsed;
    toggle.classList.toggle('collapsed', collapsed);
    toggle.setAttribute('aria-expanded', String(!collapsed));

    if (collapsed) {
      // Lock in the current rendered height first so the browser has a
      // starting point to animate from, then collapse to 0 on next frame.
      wrap.style.maxHeight = wrap.scrollHeight + 'px';
      requestAnimationFrame(() => {
        wrap.classList.add('collapsed');
        wrap.style.maxHeight = '0px';
      });
    } else {
      wrap.classList.remove('collapsed');
      wrap.style.maxHeight = wrap.scrollHeight + 'px';
      const onEnd = (e) => {
        if (e.propertyName !== 'max-height') return;
        if (!ashaQuickChipsCollapsed) wrap.style.maxHeight = 'none';
        wrap.removeEventListener('transitionend', onEnd);
      };
      wrap.addEventListener('transitionend', onEnd);
    }
  }

  function ashaInitQuickChipsToggle() {
    const toggle = document.getElementById('asha-quick-chips-toggle');
    if (!toggle) return;
    toggle.addEventListener('click', () => ashaSetQuickChipsCollapsed(!ashaQuickChipsCollapsed));
    toggle.addEventListener('keydown', (e) => {
      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        ashaSetQuickChipsCollapsed(!ashaQuickChipsCollapsed);
      }
    });
  }

  function ashaHandleChip(chip) {
    if (chip.action === 'send') {
      ashaSendMessage(chip.text);
    } else if (chip.action === 'handoff') {
      ashaPushMessage('assistant', translations[currentLang].ashaHandoffIntro);
      ashaRenderHandoff();
    } else if (chip.action === 'whatsapp') {
      window.open(`https://wa.me/${SHOP_WHATSAPP}?text=Hello%20Gupta%20Medical%20Store,%20I%20need%20some%20help`, '_blank');
    } else if (chip.action === 'prescription') {
      ashaOpenPrescriptionCard();
    }
  }

  // Restore any conversation already going from earlier in this browser
  // session (e.g. the customer went from Place Order back to Registration
  // and forward again) and (re)build the quick-chip row.
  // ADDED: Enter key submits the admin PIN modal, same as clicking Login.
  document.getElementById('admin-pin').addEventListener('keydown', (e) => {
    if (e.key === 'Enter') { e.preventDefault(); submitAdminPin(); }
  });

  ashaLoadHistory();
  ashaState.messages.forEach(m => ashaRenderMessage(m.role, m.text, m.time));
  ashaRenderQuickChips();
  ashaInitQuickChipsToggle();

  /* ── Initial language setup ── */
  document.getElementById('lang-en').classList.toggle('active', currentLang === 'en');
  document.getElementById('lang-hi').classList.toggle('active', currentLang === 'hi');
  document.body.classList.toggle('lang-hi', currentLang === 'hi');
  applyTranslations();
  refreshStepClickability();
</script>
 
</body>

</html>