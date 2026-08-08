<?php
/**
 * medicine_lookup.php
 * ─────────────────────────────────────────────────────────────────────
 * Backend search endpoint used by form.php (the "med-name" autocomplete
 * boxes) and by Asha's inline search card. It reads medicines.csv and
 * returns ONLY what the customer-facing site is allowed to show:
 * medicine name, pack size, and selling price. Stock quantity, cost
 * price, purchase price, rack no., barcode, etc. are never sent to the
 * browser.
 *
 * Request:   GET medicine_lookup.php?q=<search text>
 * Response:  JSON array, e.g.
 *            [{"name":"1AL TAB 1*10 TABS","unit":"1*10 TABS","price":37.99,"fuzzy":false}, ...]
 *
 * Matching:
 *   1. Exact / "starts with" / "contains" matches on the product name
 *      (highest priority — this is what most searches will hit).
 *      "fuzzy": false — these are direct name matches.
 *   2. If that comes up thin, a typo-tolerant fuzzy pass kicks in
 *      (percentage similarity via similar_text(), checked against both
 *      each word and the full product name), so a misspelled search
 *      like "parasetmol" still finds "PARACETAMOL ...", and a generic
 *      name typed for a brand-only product (e.g. "Azitromycin" when the
 *      CSV only stocks "AZITHRO") still surfaces the closest real match
 *      instead of returning nothing. These are flagged "fuzzy": true so
 *      form.php can show them under a "Did you mean" heading instead of
 *      listing them as if they were exact matches.
 *   Results are capped at MAX_RESULTS, most relevant first.
 *
 * Performance: the ~30,000-row CSV is parsed once and cached as JSON
 * next to it. The cache is rebuilt automatically whenever
 * medicines.csv's modified time changes (i.e. whenever you upload a
 * fresh stock export) — no manual cache-clearing needed.
 *
 * NOTE: this file is the single, self-contained source of the CSV
 * search logic. An older medicine_data.php on the server duplicates
 * this same parsing/caching/searching logic but isn't required by any
 * page (form.php only ever calls medicine_lookup.php) — it's safe to
 * delete medicine_data.php to avoid maintaining two copies of the same
 * logic that could drift apart.
 * ─────────────────────────────────────────────────────────────────────
 */

// ── Configuration ──────────────────────────────────────────────────
// Path to the stock CSV. Update this if you move the file.
define('MEDICINES_CSV', __DIR__ . '/medicines.csv');

// Where to keep the parsed cache (must be writable by the web server).
define('MEDICINES_CACHE', __DIR__ . '/medicines_cache.json');

// Which CSV column is charged to the customer. Your export has both
// "M.R.P." and "Sales Price" — change this one line if you'd rather
// charge MRP instead.
define('PRICE_COLUMN', 'Sales Price');

// How many suggestions to return per search.
define('MAX_RESULTS', 8);

// Minimum characters before we bother searching.
define('MIN_QUERY_LEN', 2);

header('Content-Type: application/json; charset=utf-8');

// If something goes wrong, fail as an empty JSON array instead of a
// blank page or a PHP error dump — the front-end's fetch() expects
// JSON no matter what, and a raw PHP warning breaks it silently.
ini_set('display_errors', '0');
set_error_handler(function ($num, $str) { throw new ErrorException($str); });

// Visit medicine_lookup.php?q=dolo&debug=1 in the browser to see
// exactly what's going wrong (missing file, bad permissions, etc.)
// instead of just an empty result. Remove/rename this block once
// everything is working if you don't want it public.
$DEBUG = isset($_GET['debug']);

// ── Load (and cache) the medicine list ────────────────────────────
function loadMedicines(): array {
    if (!file_exists(MEDICINES_CSV)) {
        return [];
    }

    $csvMTime = filemtime(MEDICINES_CSV);

    if (file_exists(MEDICINES_CACHE)) {
        $cached = json_decode(file_get_contents(MEDICINES_CACHE), true);
        if (is_array($cached) && ($cached['source_mtime'] ?? null) === $csvMTime) {
            return $cached['records'];
        }
    }

    $records = parseMedicinesCsv(MEDICINES_CSV);

    file_put_contents(MEDICINES_CACHE, json_encode([
        'source_mtime' => $csvMTime,
        'records'      => $records,
    ]));

    return $records;
}

function parseMedicinesCsv(string $path): array {
    $handle = fopen($path, 'r');
    if (!$handle) return [];

    $header = null;
    $colIndex = [];
    $records = [];

    while (($row = fgetcsv($handle)) !== false) {
        // Find the real header row (the file starts with a couple of
        // letterhead/title rows before the actual "Code,Product Name,..." row).
        if ($header === null) {
            if (isset($row[0], $row[1]) && trim($row[0]) === 'Code' && trim($row[1]) === 'Product Name') {
                $header = $row;
                foreach ($header as $i => $colName) {
                    $colIndex[trim($colName)] = $i;
                }
            }
            continue;
        }

        $nameIdx  = $colIndex['Product Name'] ?? null;
        $unitIdx  = $colIndex['Unit'] ?? null;
        $priceIdx = $colIndex[PRICE_COLUMN] ?? null;

        if ($nameIdx === null || $priceIdx === null) continue;

        $name = trim($row[$nameIdx] ?? '');
        if ($name === '') continue; // blank/summary rows at the end of the file

        $priceRaw = str_replace(',', '', trim($row[$priceIdx] ?? '0'));
        $price = (float) $priceRaw;

        $csvUnit = trim($row[$unitIdx] ?? '');
        $pack = extractPack($name, $csvUnit);

        $records[] = [
            'name'   => $name,
            'search' => normalize($name),
            'unit'   => $pack,
            'price'  => round($price, 2),
        ];
    }

    fclose($handle);
    return $records;
}

// Pulls a human-friendly pack description (e.g. "1*10 TABS", "30 ml")
// off the end of the product name, so the "Pack" column on the site
// shows something meaningful instead of the raw CSV unit code.
function extractPack(string $name, string $fallbackUnit): string {
    if (preg_match('/^(.*?)\s+([\d.]+\s*(?:\*\s*[\d.]+)?\s*(?:ML|ml|Ml|GM|gm|Gm|MG|mg|KG|kg|TABS?|Tabs?|CAPS?|Caps?|SACHETS?|Sachets?)\.?)\s*$/u', $name, $m)) {
        return trim($m[2]);
    }
    return $fallbackUnit !== '' ? $fallbackUnit : '—';
}

// Lowercases, strips punctuation, collapses whitespace — used for both
// stored records and the incoming query so comparisons are fair.
function normalize(string $s): string {
    $s = strtolower($s);
    $s = preg_replace('/[^a-z0-9\s]/', ' ', $s);
    $s = preg_replace('/\s+/', ' ', $s);
    return trim($s);
}

function searchMedicines(array $records, string $query): array {
    $q = normalize($query);
    if ($q === '') return [];

    $scored = [];

    // Pass 1 — substring matching (fast, and covers the vast majority
    // of real searches since most customers type real, if partial, names).
    foreach ($records as $rec) {
        $pos = strpos($rec['search'], $q);
        if ($pos === false) continue;

        if ($pos === 0) {
            $score = 0; // starts with the query
        } elseif (preg_match('/(?:^|\s)' . preg_quote($q, '/') . '/', $rec['search'])) {
            $score = 1; // matches at the start of a word
        } else {
            $score = 2; // matches somewhere inside
        }
        $scored[$rec['name']] = ['rec' => $rec, 'score' => $score, 'tiebreak' => strlen($rec['name'])];
    }

    // Pass 2 — typo-tolerant fuzzy matching. Only runs when the exact
    // pass came up thin, and only for queries with enough characters for
    // a similarity check to be meaningful.
    //
    // NOTE: this used to compare via levenshtein() with a small integer
    // "max edit distance" cap (min(3, len/3)). That worked for short
    // one-letter typos, but failed badly on anything longer or on brand
    // vs. generic mismatches — e.g. "Azitromycin" (11 chars) vs. the
    // in-stock brand "AZITHRO" (7 chars) has an edit distance of 5,
    // which blew past the distance cap and matched *nothing*, even
    // though "AZITHRO" is obviously the closest thing in stock. Using a
    // percentage-based similarity (PHP's similar_text()) instead of a
    // raw distance cap scales correctly regardless of word length, and
    // ranks true near-misses (like PARACETAMOL for "Paracetmol") above
    // weaker coincidental matches instead of tying them all together.
    if (count($scored) < MAX_RESULTS && strlen($q) >= 3) {
        $minPercent = 55.0; // similarity floor — tuned so real typos match
                             // but unrelated short words don't

        foreach ($records as $rec) {
            if (isset($scored[$rec['name']])) continue; // already matched above

            $bestPercent = 0.0;

            // Compare against each word in the product name (catches a
            // typo'd single word inside a longer product name)...
            foreach (explode(' ', $rec['search']) as $word) {
                if ($word === '') continue;
                // Skip comparisons that can't possibly be close — keeps
                // this pass fast across ~30k records.
                if (abs(strlen($word) - strlen($q)) > strlen($q)) continue;
                similar_text($q, $word, $percent);
                if ($percent > $bestPercent) $bestPercent = $percent;
            }

            // ...and against the full normalized name (catches
            // multi-word generic names, e.g. "pantoprazole 40" typed
            // against a CSV entry stored as "pantop 40 tabs").
            similar_text($q, $rec['search'], $wholePercent);
            if ($wholePercent > $bestPercent) $bestPercent = $wholePercent;

            if ($bestPercent >= $minPercent) {
                $scored[$rec['name']] = [
                    'rec' => $rec,
                    // Higher similarity = lower (better) score, always
                    // ranked below real substring matches (score 0-2).
                    'score' => 10 + (100 - $bestPercent) / 10,
                    'tiebreak' => strlen($rec['name']),
                    'fuzzy' => true, // spelling-corrected match, not a direct name match
                ];
            }
        }
    }

    usort($scored, function ($a, $b) {
        return $a['score'] <=> $b['score'] ?: $a['tiebreak'] <=> $b['tiebreak'];
    });

    $out = [];
    foreach (array_slice($scored, 0, MAX_RESULTS) as $entry) {
        $out[] = [
            'name'  => $entry['rec']['name'],
            'unit'  => $entry['rec']['unit'],
            'price' => $entry['rec']['price'],
            'fuzzy' => $entry['fuzzy'] ?? false, // true = typo-corrected "Did you mean" suggestion
        ];
    }
    return $out;
}

// ── Handle the request ────────────────────────────────────────────
try {
    $q = trim($_GET['q'] ?? '');

    if ($DEBUG) {
        echo json_encode([
            'csv_path'        => MEDICINES_CSV,
            'csv_exists'      => file_exists(MEDICINES_CSV),
            'csv_readable'    => is_readable(MEDICINES_CSV),
            'cache_path'      => MEDICINES_CACHE,
            'cache_dir_writable' => is_writable(dirname(MEDICINES_CACHE)),
        ]);
        exit;
    }

    if (strlen($q) < MIN_QUERY_LEN) {
        echo json_encode([]);
        exit;
    }

    $records = loadMedicines();
    $results = searchMedicines($records, $q);

    echo json_encode($results);
} catch (\Throwable $e) {
    // Never let a PHP error/warning reach the browser as broken JSON —
    // the front-end just treats this the same as "no matches found".
    http_response_code(200);
    echo json_encode([]);
}