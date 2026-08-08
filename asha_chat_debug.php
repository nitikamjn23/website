<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

/**
 * Asha AI — chat backend
 * Called by the widget in form.php via:
 *   POST asha_chat.php  { message, history, lang, order }  ->  { reply }
 *
 * Order/account/support questions are intercepted BEFORE the AI model is
 * ever called (see asha_is_support_intent() in ai_config.php) — Asha can't
 * see real order data, so those get a fixed handoff reply instead of a
 * guess. Everything else goes to the model using asha_system_prompt().
 */
require_once __DIR__ . '/ai_config.php';

// ---------------------------------------------------------------------
// 1. Read & validate the incoming request
// ---------------------------------------------------------------------
$raw  = file_get_contents('php://input');
$body = json_decode($raw, true);

if (!is_array($body) || empty($body['message']) || !is_string($body['message'])) {
    http_response_code(400);
    echo json_encode(['reply' => null, 'error' => 'Missing message']);
    exit;
}

$userMessage = trim($body['message']);
$history     = is_array($body['history'] ?? null) ? $body['history'] : [];
$lang        = in_array(($body['lang'] ?? 'en'), ['en', 'hi'], true) ? $body['lang'] : 'en';
$order       = is_array($body['order'] ?? null) ? $body['order'] : [];

if ($userMessage === '') {
    http_response_code(400);
    echo json_encode(['reply' => null, 'error' => 'Empty message']);
    exit;
}

// ---------------------------------------------------------------------
// 2. Fixed FAQ answers (delivery charges, Pay on Delivery/COD) — always
//    correct, never left to the AI model to phrase from memory. Checked
//    before the support-intent handoff below since these are questions
//    Asha CAN answer confidently, not ones that need a human.
// ---------------------------------------------------------------------
$faqAnswer = asha_faq_answer($userMessage, $lang);
if ($faqAnswer !== null) {
    echo json_encode(['reply' => $faqAnswer], JSON_UNESCAPED_UNICODE);
    exit;
}

// ---------------------------------------------------------------------
// 3. Deterministic handoff for order/account/support questions —
//    never let the model guess at something it can't actually see.
// ---------------------------------------------------------------------
if (asha_is_support_intent($userMessage)) {
    echo json_encode(asha_support_handoff_reply($lang), JSON_UNESCAPED_UNICODE);
    exit;
}

// ---------------------------------------------------------------------
// 4. No real API key configured yet — fail politely instead of a raw error.
// ---------------------------------------------------------------------
$apiKey = OPENAI_API_KEY;
if ($apiKey === '' || str_starts_with($apiKey, 'sk-...') || strtolower($apiKey) === 'your-key-here') {
    $msg = $lang === 'hi'
        ? "मुझे आपको हमारी फार्मेसी टीम से जोड़कर खुशी होगी।\n[[HANDOFF]]"
        : "I'd be happy to connect you with our pharmacy team.\n[[HANDOFF]]";
    echo json_encode(['reply' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

// ---------------------------------------------------------------------
// 5. Build the system prompt (from ai_config.php) + conversation history
// ---------------------------------------------------------------------
$systemPrompt = asha_system_prompt($lang);

if (!empty($order)) {
    $systemPrompt .= "\n\nCONTEXT — this customer's in-progress order (use it directly, don't ask for info already listed here):\n"
        . json_encode($order, JSON_UNESCAPED_UNICODE);
}

$messages = [['role' => 'system', 'content' => $systemPrompt]];

foreach ($history as $turn) {
    $role    = (($turn['role'] ?? '') === 'user') ? 'user' : 'assistant';
    $content = trim((string)($turn['content'] ?? ''));
    if ($content !== '') {
        $messages[] = ['role' => $role, 'content' => $content];
    }
}

$messages[] = ['role' => 'user', 'content' => $userMessage];

// ---------------------------------------------------------------------
// 6. Call the AI provider
// ---------------------------------------------------------------------
$payload = json_encode([
    'model'       => OPENAI_MODEL,
    'messages'    => $messages,
    'temperature' => 0.6,
    'max_tokens'  => 500,
], JSON_UNESCAPED_UNICODE);

$ch = curl_init(OPENAI_API_URL);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey,
    ],
    CURLOPT_POSTFIELDS => $payload,
    CURLOPT_TIMEOUT    => 25,
]);

$response  = curl_exec($ch);
$curlError = curl_error($ch);
$httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($response === false || $curlError) {
    error_log('Asha: cURL error — ' . $curlError);
    http_response_code(502);
    echo json_encode(['reply' => null, 'error' => 'Upstream request failed', 'debug_curl_error' => $curlError]);
    exit;
}

$data = json_decode($response, true);

if ($httpCode >= 400 || !isset($data['choices'][0]['message']['content'])) {
    error_log('Asha: API error (' . $httpCode . ') — ' . $response);
    http_response_code(502);
    // DEBUG BUILD ONLY: echoes OpenAI's actual error back so we can see it in
    // DevTools. Never contains your API key. Delete this file once fixed —
    // do not leave a debug endpoint like this on a live site long-term.
    echo json_encode(['reply' => null, 'error' => 'Upstream error', 'debug_http_code' => $httpCode, 'debug_openai_response' => $data]);
    exit;
}

$reply = trim($data['choices'][0]['message']['content']);

echo json_encode(['reply' => $reply], JSON_UNESCAPED_UNICODE);