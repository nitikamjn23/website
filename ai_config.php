<?php
/**
 * ai_config.php
 * ---------------------------------------------------------------------------
 * Configuration + system prompt for Asha, the AI Nurse Assistant chatbot.
 * Used by asha_chat.php (and a few of these constants are also read by
 * form.php for the WhatsApp button / floating widgets), so this is the
 * SINGLE place to update the shop's phone, WhatsApp number, email, and
 * hours — change them here and every place that shows them updates too.
 *
 * ── SETUP — the one thing you MUST do before Asha will work ────────────
 * 1. Get an API key from your AI provider (e.g. https://platform.openai.com/api-keys
 *    for OpenAI).
 * 2. Paste it below as OPENAI_API_KEY.
 * 3. Never commit this file (with a real key in it) to a public GitHub repo.
 *    If this project is version-controlled, add ai_config.php to .gitignore
 *    and commit ai_config.example.php with the key blanked out instead.
 *
 * Until a real key is set, asha_chat.php responds with a polite
 * "let me connect you with our pharmacy team" message instead of failing
 * silently or throwing an error to the customer. Store-hours/contact/order
 * questions (see SUPPORT_INTENT_KEYWORDS below) work even without an API
 * key at all, since those are answered deterministically, not by the AI.
 * ---------------------------------------------------------------------------
 */

define('OPENAI_API_KEY', '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');   // ← put your real key here
define('OPENAI_MODEL', 'gpt-4o-mini');                    // good balance of cost/quality for a support chatbot
define('OPENAI_API_URL', 'https://api.openai.com/v1/chat/completions');

// ── Shop contact info — the single source of truth ─────────────────────
// Read by: Asha's system prompt + handoff replies below, and by form.php
// (injected into the page as JS constants) for the floating WhatsApp
// button and Asha's own "Contact Pharmacy" / "Chat on WhatsApp" buttons.
// Update a number here and it changes everywhere at once.
define('SHOP_PHONE', '+91 9935922282');            // shown to customers, used for the tel: link
define('SHOP_WHATSAPP', '919935922282');            // digits only (country code + number), used for wa.me links
define('SHOP_EMAIL', 'guptamedicalstore@gmail.com');
// TODO: confirm/adjust actual store hours — a placeholder is used below so
// Asha never has to guess when answering "are you open right now?"
define('SHOP_HOURS_EN', 'Monday–Saturday, 9:00 AM – 9:00 PM');
define('SHOP_HOURS_HI', 'सोमवार–शनिवार, सुबह 9:00 बजे – रात 9:00 बजे');

// ── UPI payment settings — the single, dedicated place to update these ──
// Shown on the Order Summary page when the customer selects UPI as their
// payment method (see #upi-payment-section in form.php).
//
// To change the QR code later WITHOUT touching any code:
//   1. Replace the image file at assets/upi-qr-code.png with the new QR
//      (keep the same filename), OR
//   2. Point UPI_QR_IMAGE below at a different filename/path.
// Same idea for UPI_ID — just edit the string here.
define('UPI_ID', 'nitika033@icici');           // ← replace with the shop's real UPI ID / VPA
define('UPI_QR_IMAGE', 'assets/upi-qr-code.png');      // ← replace this file (or this path) to update the QR code

/**
 * SUPPORT_INTENT_KEYWORDS
 * ---------------------------------------------------------------------------
 * Any customer message containing one of these phrases (case-insensitive,
 * substring match) is an account/order/support question — order tracking,
 * delivery status, cancellations, returns, changes, payment problems,
 * refunds, store timings, contact/address, prescription-upload problems,
 * or general technical trouble. For all of these, Asha does NOT call the
 * AI model at all: she can't see the customer's actual order or account,
 * so guessing would just be inventing an answer. Instead she immediately
 * shows the fixed "please contact our support team" message plus the
 * phone/WhatsApp/email contact block (see asha_support_handoff_reply()).
 *
 * This list is the one place to add/remove trigger phrases; keep it to
 * multi-word phrases where a single word would be too broad (e.g. use
 * "payment issue" rather than bare "payment", since "how does Pay on
 * Delivery work" is a normal informational question Asha should still
 * answer herself).
 * ---------------------------------------------------------------------------
 */
define('SUPPORT_INTENT_KEYWORDS', serialize([
    // Order tracking / delivery status / parcel location
    'track', 'tracking', 'where is my order', 'where is my delivery', 'where is my parcel',
    'delivery status', 'parcel location', 'has my order shipped', 'has my order been dispatched',
    // Cancel / return / change an order
    'cancel my order', 'cancel order', 'cancellation', 'return my order', 'return order',
    'change my order', 'change order', 'modify my order', 'modify order', 'edit my order', 'edit order',
    // Payment issues / refunds
    'payment issue', 'payment problem', 'payment failed', 'payment not working', 'refund',
    // Store timings / contact / address
    'store timing', 'store hours', 'shop timing', 'shop hours', 'opening hours', 'opening time',
    'closing time', 'are you open', 'when do you open', 'when do you close',
    'contact number', 'contact info', 'contact detail', 'phone number', 'your number',
    'shop address', 'store address', 'your address', 'where are you located', 'where is your shop',
    // Delivery availability
    'do you deliver', 'delivery available', 'do you deliver to', 'deliver to my area', 'deliver to my location',
    // Prescription upload PROBLEMS (not "how do I upload", which Asha can
    // still answer herself as a how-to)
    "can't upload", "cant upload", 'upload not working', 'upload issue', 'upload failed', 'upload error',
    // General technical support
    'technical support', 'website not working', 'site not working', 'app not working',
    'error on the site', 'error on this site', "isn't working", 'is not working', 'not loading',
]));

/**
 * ASHA_FAQ — fixed, always-correct answers for a couple of high-frequency
 * questions that customers phrase many different ways. These are answered
 * deterministically (never left to the AI model to phrase from scratch)
 * so the numbers/facts can never drift or be guessed incorrectly.
 *
 * Each entry's "phrases" list is matched case-insensitively as a substring
 * against the customer's message (same style as SUPPORT_INTENT_KEYWORDS
 * above) — covers common variations like "delivery fee", "shipping
 * charges", "COD available?", "cash on delivery?", "pay on delivery?".
 * Add more phrasings here any time a customer asks in a way that isn't
 * already covered.
 */
define('ASHA_FAQ', serialize([
    'delivery_charge' => [
        'phrases' => [
            'delivery charge', 'delivery charges', 'delivery fee', 'delivery fees',
            'delivery cost', 'delivery price', 'shipping charge', 'shipping charges',
            'shipping fee', 'shipping fees', 'shipping cost', 'shipping price',
            'is delivery free', 'free delivery', 'do you charge for delivery',
            'do you charge delivery', 'charge for shipping',
        ],
        'reply_en' => 'Delivery charges are ₹0 — delivery is completely free!',
        'reply_hi' => 'डिलीवरी चार्ज ₹0 है — डिलीवरी बिल्कुल मुफ़्त है!',
    ],
    'pay_on_delivery' => [
        'phrases' => [
            'pay on delivery', 'cash on delivery', 'cod available', 'cod avail',
            'is cod', 'cod option', 'pod available', 'do you have cod',
            'cash on delivery available', 'pay on delivery available',
        ],
        // Bare "cod" (word-boundary) is matched separately below, since a
        // 3-letter word needs a stricter check than a plain substring.
        'reply_en' => 'Yes, Pay on Delivery is available.',
        'reply_hi' => 'जी हाँ, डिलीवरी पर भुगतान (पे ऑन डिलीवरी) उपलब्ध है।',
    ],
]));

/**
 * Returns the fixed answer text for a handful of common, high-frequency
 * questions (delivery charges, Pay on Delivery / COD availability) — see
 * ASHA_FAQ above. Returns null when the message doesn't match any of
 * them, so the caller can fall through to the normal support-intent /
 * AI-model handling.
 */
function asha_faq_answer(string $message, string $lang): ?string {
    $needle = mb_strtolower($message);
    $faq = unserialize(ASHA_FAQ);

    foreach ($faq as $entry) {
        foreach ($entry['phrases'] as $phrase) {
            if (str_contains($needle, $phrase)) {
                return $lang === 'hi' ? $entry['reply_hi'] : $entry['reply_en'];
            }
        }
    }

    // Bare "cod" as its own word ("Is COD available?", "COD?") — checked
    // separately with a word boundary so it doesn't fire on unrelated
    // words that merely contain "cod".
    if (preg_match('/\bcod\b/', $needle)) {
        return $lang === 'hi' ? $faq['pay_on_delivery']['reply_hi'] : $faq['pay_on_delivery']['reply_en'];
    }

    return null;
}

/**
 * Returns true if the message matches a support/account-related intent —
 * see SUPPORT_INTENT_KEYWORDS above for what qualifies and why.
 */
function asha_is_support_intent(string $message): bool {
    $needle = mb_strtolower($message);
    foreach (unserialize(SUPPORT_INTENT_KEYWORDS) as $phrase) {
        if (str_contains($needle, $phrase)) return true;
    }
    return false;
}

/**
 * The fixed, deterministic reply for support/account questions (see
 * asha_is_support_intent()). Ends with [[HANDOFF]] so the front-end shows
 * the phone/WhatsApp/email contact block — the same mechanism the AI
 * model itself uses when it decides to hand off (see the system prompt).
 */
function asha_support_handoff_reply(string $lang): array {
    $text = $lang === 'hi'
        ? "मुझे मदद करने में खुशी होगी। ऑर्डर ट्रैकिंग, रद्दीकरण, रिफंड या अपने ऑर्डर में किसी भी बदलाव के लिए, कृपया सीधे हमारी सहायता टीम से संपर्क करें।\n[[HANDOFF]]"
        : "I'd be happy to help. For order tracking, cancellations, refunds, or any changes to your order, please contact our support team directly.\n[[HANDOFF]]";
    return ['reply' => $text];
}

/**
 * Builds the system prompt sent to the AI model on every request. This is
 * where Asha's identity, tone, knowledge boundaries, and — most importantly
 * — her safety rules live. Nothing here is optional: the safety-rules
 * section is what keeps Asha from diagnosing, prescribing, or giving unsafe
 * dosage advice, regardless of how a customer phrases their question.
 *
 * Note: order/account/support questions are normally intercepted BEFORE
 * this prompt is ever sent (see asha_is_support_intent() in asha_chat.php),
 * so the AI only sees medical questions, general pharmacy questions, and
 * casual conversation/greetings. Rule 6 below is a backstop for anything
 * support-shaped that the keyword list didn't catch.
 */
function asha_system_prompt(string $lang): string {
    $hours = $lang === 'hi' ? SHOP_HOURS_HI : SHOP_HOURS_EN;
    $phone = SHOP_PHONE;
    $email = SHOP_EMAIL;
    $languageInstruction = $lang === 'hi'
        ? 'Respond in Hindi (Devanagari script), matching the customer\'s selected language, unless the customer switches to English mid-conversation.'
        : 'Respond in English unless the customer writes in Hindi, in which case switch to Hindi.';

    return <<<PROMPT
You are Asha, an AI Nurse Assistant for Gupta Medical Stores, a neighbourhood
pharmacy. Talk like a genuinely helpful, modern AI assistant — natural,
warm, and conversational, the way a thoughtful person would text back, not
like a scripted bot reciting fixed lines. Keep replies concise (a few short
sentences, or a short list when that's clearer) since customers are
chatting on their phone. Vary your phrasing — don't reuse the exact same
sentence structure reply after reply. Use the conversation history you're
given to stay consistent with what's already been said, and ask a brief
follow-up question when it would genuinely help you give a better answer.

{$languageInstruction}

STORE INFORMATION YOU MAY SHARE:
- Phone: {$phone}
- Email: {$email}
- Hours: {$hours}
- Orders are Cash on Delivery, UPI, or Card.
- Customers can upload a prescription photo/PDF on the order form (or use
  the "Scan Your Prescription" camera option); it's required automatically
  when the order contains a prescription-only medicine.
- Customers can opt into an email reminder (in 1/2 weeks, 1/2/3 months, or
  a custom number of days) to reorder their medicines.

HOW TO TRIAGE EVERY MESSAGE — decide which of these it is before replying:
1. GREETING OR CASUAL CONVERSATION ("hi", "thank you", "how are you") —
   respond warmly and briefly, like a friendly person would. No need to
   launch into disclaimers or store info unless it's relevant.
2. ORDER / ACCOUNT / SUPPORT QUESTION (tracking, delivery status,
   cancellations, returns, changing an order, payment problems, refunds,
   store timings, contact info, shop address, delivery availability,
   prescription-upload problems, technical/website issues) — you cannot
   see any customer's actual order or account, so never guess or invent
   a status. Say something like "I'd be happy to help. For order
   tracking, cancellations, refunds, or any changes to your order, please
   contact our support team directly." and end your reply with the exact
   token [[HANDOFF]] on its own line so the website shows the contact
   buttons. (In practice, most of these are already caught before they
   reach you — but if one slips through, always handle it this way rather
   than answering as if you know the order's status.)
3. GENERAL PHARMACY / SITE QUESTION (how to place an order, how Pay on
   Delivery works, how to upload a prescription, what a reminder does,
   what payment methods exist) — answer directly and helpfully using the
   store information above.
4. MEDICINE / SYMPTOM QUESTION (what a medicine treats, general side
   effects, whether something is usually OTC or prescription-only, "I
   have a headache/fever/cold — what can I take?") — answer using the
   safety rules below.
5. ANYTHING ELSE YOU'RE NOT CONFIDENT ABOUT — it's better to say so and
   hand off than to guess. Use the same [[HANDOFF]] pattern as #2.

STRICT SAFETY RULES FOR MEDICINE/SYMPTOM QUESTIONS — NEVER BREAK THESE:
1. NEVER diagnose a disease or condition. You can describe what symptoms
   are commonly associated with, but never say "you have X."
2. NEVER recommend, name, or confirm availability of antibiotics or any
   prescription-only / controlled medicine — not even if the customer says
   they've taken it before or insists they don't need a doctor. Redirect
   them to consult a doctor or pharmacist for those.
3. NEVER give a specific dosage, frequency, or duration ("take 2 tablets
   every 6 hours for 3 days"). You may say a medicine is "commonly used
   for" something, but dosing decisions belong to the product label, a
   pharmacist, or a doctor.
4. ALWAYS recommend seeing a doctor for: symptoms lasting more than 2-3
   days, severe or worsening symptoms, chest pain, difficulty breathing,
   high fever, symptoms in infants/pregnant people/elderly with other
   conditions, or anything that sounds urgent — if in doubt, say to seek
   medical care promptly rather than downplaying it.
5. ALWAYS make clear you're not a substitute for a licensed doctor or
   pharmacist when discussing symptoms or medicines — briefly and
   naturally, not as a copy-pasted legal disclaimer every message (the
   website already shows a permanent disclaimer under the chat).
6. Never invent a specific product name, price, or stock availability you
   weren't given in this conversation — point the customer to the "Search
   Medicine" option or the Medicine Name field on the order form instead,
   which checks the store's real, current list.

Keep your tone warm and natural but never so casual that it undercuts the
safety guidance above.
PROMPT;
}