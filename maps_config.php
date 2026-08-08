<?php
/**
 * maps_config.php
 * ---------------------------------------------------------------------------
 * Google Maps configuration for the live delivery-tracking map on the Order
 * Confirmation page (form.php) and real address geocoding (geocode.php).
 *
 * ── SETUP ────────────────────────────────────────────────────────────────
 * 1. Go to https://console.cloud.google.com/google/maps-apis and create (or
 *    select) a project.
 * 2. Under "APIs & Services" → enable these two:
 *      - "Maps JavaScript API"   (renders the map itself, in the browser)
 *      - "Geocoding API"         (turns a typed address into real lat/lng)
 * 3. Under "Credentials", create an API key.
 * 4. Google Maps requires a billing account on the project — Google gives a
 *    recurring monthly free credit that comfortably covers a small shop's
 *    traffic, but a card must be on file or map/geocoding calls will fail.
 * 5. Paste your key below.
 *
 * ── ONE KEY VS. TWO (Google's own recommendation) ───────────────────────
 * GOOGLE_MAPS_BROWSER_KEY is embedded directly into the page's HTML — it's
 * visible to anyone who views page source. Restrict it in Cloud Console
 * ("API restrictions" + "Application restrictions" → HTTP referrers) to
 * only your website's domain and only the Maps JavaScript API, so it can't
 * be copied and used elsewhere.
 *
 * GOOGLE_MAPS_SERVER_KEY is used only server-side (from geocode.php),
 * never sent to the browser. Google recommends a *separate* key here,
 * restricted to your server's IP address and only the Geocoding API.
 *
 * If you'd rather keep setup simple for now, it's fine to use the SAME key
 * for both — just restrict it to HTTP referrers (server-to-server calls
 * from geocode.php aren't affected by a referrer restriction, only
 * browser requests are).
 * ---------------------------------------------------------------------------
 */

define('GOOGLE_MAPS_BROWSER_KEY', 'AIzaSyCJXBEVOdqA1ftyh89R8eTM6Yd1cYL1eEs');
define('GOOGLE_MAPS_SERVER_KEY', 'AIzaSyCJXBEVOdqA1ftyh89R8eTM6Yd1cYL1eEs'); // can reuse the browser key — see note above

// Gupta Medical Stores — replace with your real shop coordinates.
// (Right-click your shop's exact spot on Google Maps → the lat/lng shown
// at the top of the menu that pops up is what you want here.)
// NOTE: form.php also has a `storeCoords` JS constant that must match these
// two numbers — plain client-side JS can't read PHP constants directly.
define('STORE_LAT', 25.6127);
define('STORE_LNG', 85.1590);
