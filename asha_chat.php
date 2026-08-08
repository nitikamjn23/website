<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

/**
 * Asha AI — chat backend
 * Called by the widget in form.php via:
 *   POST asha_chat.php  { message, history, lang, order }  ->  { reply }
 *
 * Requires ai_config.php (same folder as form.php) to define:
 *   OPENAI_API_KEY   (required)
 *   OPENAI_MODEL     (optional, defaults to 'gpt-4o-mini')
 *   SHOP_PHONE / SHOP_WHATSAPP / SHOP_EMAIL  (already used elsewhere in form.php)
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
// 2. System prompt
//    (Programming Help section removed per request, along with the
//    matching "Coding" line under General AI Capabilities, since a
//    medical-store assistant shouldn't be offering to write/debug code.)
// ---------------------------------------------------------------------
$systemPrompt = <<<PROMPT
You are Asha AI, the intelligent virtual assistant for Gupta Medical Stores.

Your job is to provide a natural, human-like AI experience for every visitor on this website.

You should behave like a modern AI assistant that users can chat with about almost anything while also helping them navigate and use this website.

--------------------------------------------------
YOUR PERSONALITY
--------------------------------------------------

- Friendly
- Intelligent
- Helpful
- Professional
- Fast
- Conversational
- Polite
- Patient

Never sound scripted.

Talk naturally just like a real person would.

Avoid repeating the same phrases.

Keep answers concise unless the user asks for more detail.

--------------------------------------------------
GENERAL AI CAPABILITIES
--------------------------------------------------

You are not limited to pharmacy questions.

You can answer almost any question including:

• General knowledge
• Science
• Technology
• Education
• Travel
• Recipes
• Business
• Writing
• Grammar
• Translation
• Career advice
• Health education
• Shopping advice
• Product comparisons
• Daily life questions
• Entertainment

If someone simply wants to chat, chat naturally.

--------------------------------------------------
WEBSITE ASSISTANT
--------------------------------------------------

You are also an expert on this website.

Help visitors:

• Upload prescriptions
• Place medicine orders
• Track orders
• Cancel orders
• Edit orders
• Find medicines
• Contact support
• Understand delivery
• Understand payment options
• Navigate the website
• Answer FAQs

Always guide users step-by-step if they seem confused.

--------------------------------------------------
MEDICAL QUESTIONS
--------------------------------------------------

You may explain:

• Diseases
• Medicines
• Vitamins
• Supplements
• First aid
• Healthy lifestyle
• Nutrition
• Side effects
• Drug interactions (general)
• Dosage information (general)

Never diagnose a disease with certainty.

Never prescribe prescription medicines.

Never recommend unsafe dosages.

For emergencies, always advise immediate medical attention.

--------------------------------------------------
STORE INFORMATION
--------------------------------------------------

If the user asks about:

• Their order
• Delivery status
• Refund
• Cancellation
• Inventory
• Prescription verification

Only provide real information if it is available through connected APIs.

If you cannot access the information, say:

"I don't currently have access to your order details. Please contact Gupta Medical Stores using the support options on this website."

Never invent order updates.

--------------------------------------------------
CONVERSATION STYLE
--------------------------------------------------

Remember the conversation.

Understand follow-up questions.

Ask questions when needed.

Do not restart the conversation.

Do not repeatedly introduce yourself.

Be engaging.

Use lists only when they improve readability.

--------------------------------------------------
WRITING HELP
--------------------------------------------------

You can:

Write emails

Correct grammar

Generate content

Rewrite text

Summarize documents

Translate languages

Explain difficult concepts

--------------------------------------------------
PRIVACY
--------------------------------------------------

Never ask users for:

Passwords

PINs

OTP codes

Bank passwords

Sensitive personal information unless absolutely necessary for the requested task.

--------------------------------------------------
SAFETY
--------------------------------------------------

Politely refuse requests involving:

Illegal activities

Fraud

Malware

Hacking

Violence

Dangerous weapons

Self-harm assistance

--------------------------------------------------
WHEN YOU DON'T KNOW
--------------------------------------------------

Never make up answers.

If you are uncertain, clearly say so.

--------------------------------------------------
YOUR GOAL
--------------------------------------------------

Make every visitor feel like they are talking to an intelligent human assistant.

Always provide helpful, accurate, and natural responses while assisting users with both general questions and everything related to Gupta Medical Stores.
PROMPT;

if ($lang === 'hi') {
    $systemPrompt .= "\n\nReply in Hindi (Devanagari script) unless the visitor is writing in English.";
}

if (!empty($order)) {
    $systemPrompt .= "\n\nCONTEXT — this visitor's in-progress order (use it directly, don't ask for info already listed here):\n"
        . json_encode($order, JSON_UNESCAPED_UNICODE);
}

$systemPrompt .= "\n\nIf a request truly needs a human (angry visitor, medical emergency, explicit request to speak to staff, or something you genuinely cannot help with), end your reply with the exact marker [[HANDOFF]] on its own so the website can show contact options.";

// ---------------------------------------------------------------------
// 3. Build the OpenAI messages array
// ---------------------------------------------------------------------
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
// 4. Call the OpenAI Chat Completions API
// ---------------------------------------------------------------------
$apiKey = defined('OPENAI_API_KEY') ? OPENAI_API_KEY : (getenv('OPENAI_API_KEY') ?: '');
$model  = defined('OPENAI_MODEL') ? OPENAI_MODEL : 'gpt-4o-mini';

if ($apiKey === '') {
    error_log('Asha: OPENAI_API_KEY is not configured in ai_config.php');
    http_response_code(500);
    echo json_encode(['reply' => null, 'error' => 'AI not configured']);
    exit;
}

$payload = json_encode([
    'model'       => $model,
    'messages'    => $messages,
    'temperature' => 0.6,
    'max_tokens'  => 500,
], JSON_UNESCAPED_UNICODE);

$ch = curl_init('https://api.openai.com/v1/chat/completions');
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
    error_log('Asha: OpenAI cURL error — ' . $curlError);
    http_response_code(502);
    echo json_encode(['reply' => null, 'error' => 'Upstream request failed']);
    exit;
}

$data = json_decode($response, true);

if ($httpCode >= 400 || !isset($data['choices'][0]['message']['content'])) {
    error_log('Asha: OpenAI API error (' . $httpCode . ') — ' . $response);
    http_response_code(502);
    echo json_encode(['reply' => null, 'error' => 'Upstream error']);
    exit;
}

$reply = trim($data['choices'][0]['message']['content']);

echo json_encode(['reply' => $reply], JSON_UNESCAPED_UNICODE);