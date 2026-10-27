<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    header('Content-Type: application/json');
    echo json_encode([
        'status'  => 'ok',
        'message' => 'PPKS AI Assistant API is active and ready to receive POST requests.'
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Method not allowed. Use POST.']);
    exit;
}

$config = file_exists(__DIR__ . '/config.php') ? include(__DIR__ . '/config.php') : [];
$key = is_array($config) && !empty($config['OPENROUTER_API_KEY']) ? trim($config['OPENROUTER_API_KEY']) : '';

if (!$key) {
    $key = getenv('OPENROUTER_API_KEY') ?: (getenv('PPKSopenrouterkey') ?: ($_ENV['OPENROUTER_API_KEY'] ?? ''));
}

// If not in env, check if a local .env file exists
if (!$key && file_exists(__DIR__ . '/../.env')) {
    $lines = file(__DIR__ . '/../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if (strpos($line, '#') === 0) continue;
        if (strpos($line, 'OPENROUTER_API_KEY=') === 0) {
            $key = trim(substr($line, strlen('OPENROUTER_API_KEY=')));
            break;
        }
    }
}

if (!$key) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Chat is not configured on the server. Please set OPENROUTER_API_KEY.']);
    exit;
}

$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);
if (!is_array($input)) {
    $input = $_POST;
}
$incoming = isset($input['messages']) && is_array($input['messages']) ? $input['messages'] : [];

$messages = [];
foreach (array_slice($incoming, -16) as $m) {
    if (isset($m['role'], $m['content']) && in_array($m['role'], ['user', 'assistant']) && is_string($m['content'])) {
        $content = function_exists('mb_substr') ? mb_substr($m['content'], 0, 2000) : substr($m['content'], 0, 2000);
        $messages[] = [
            'role'    => $m['role'],
            'content' => $content,
        ];
    }
}

if (empty($messages) || end($messages)['role'] !== 'user') {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode([
        'error' => 'A user message is required. (Raw input bytes: ' . strlen((string)$rawInput) . ')'
    ]);
    exit;
}

$systemPrompt = <<<EOT
You are the admissions assistant for PPKS Baking & Cooking Academy (Pusat Pembangunan Kemahiran Sabah / 沙巴技能发展中心) in Kota Kinabalu, Sabah.

Use ONLY the facts below. Never invent fees, hotel lists, or certificate names that are not listed. Reply in the user's language (English, Malay, or Chinese).

JPK:
- Correct full name: Jabatan Pembangunan Kemahiran (Department of Skills Development / 技能发展局).
- Do NOT call JPK "Jabatan Sumber Manusia".

About:
- Founded 30 March 1999.
- Now: Baking and Cooking academy.
- Also opening Facial / beauty courses. Facial is NOT open yet. Direct facial interest to Chef Ang WhatsApp to join the waitlist.
- Chef is a Halal Certificate Holder (清真证书持有人). Not a Halal licence holder. Never say "Halal license holder".
- Website: https://www.ppks.com.my
- Facebook: Pusat Pembangunan Kemahiran Sabah
- TikTok: https://www.tiktok.com/@ppks.official
- Xiaohongshu: content can be posted there; do not invent a Xiaohongshu URL.

Contact (Chef Ang only):
- WhatsApp: 017-864 0709
- Email: ppks.com.my@gmail.com
- Do not mention Richard or 011-5587 7534.

Address (Lot 11, Windrose Square):
- Lot 11, 2nd Floor, Windrose Square, Jalan Pintas Penampang By Pass, 88300 Penampang, Sabah
- Maps: https://maps.app.goo.gl/9gJ88r6btWrmy1DQA

Flagship 2-year chef course:
- 2 years total: 1 year at the training centre + 1 year practical at a 5-star hotel.
- Completing the 2-year course: 8 certificates. Do not invent the 8 certificate titles. Centre qualification documents will be provided later.
- Also offers UK Diploma and Advanced Diploma in Patisserie and Culinary Art (Warnborough College UK).
- Practical allowance: write only "up to RM1,500 per month" / "实习津贴最高每月 RM1500". Amount depends on placement. Do not mention RM108 or RM150.
- Tuition: ask Chef Ang. Do not invent a number.

1-year SKM:
- 6 months academy + 6 months bakery or hotel, 5 days/week.
- Fee RM17,500, promo RM9,250.
- Includes chef shirt, apron, cap, ingredients, notes, exam fees.
- SKM with JPK: culinary, pastry, bread. Levels 1-3 exist.
- Promo: 50% off for first 5 registrations on Diploma in Patisserie Art, Diploma in Culinary Art, and SKM.
- No SPM. Unlimited age.

Bakery Business Course:
- For people who want to open a bakery business.
- Uses the 9 modules especially costing and bakery management.
- Fees: ask Chef Ang.

Nine course modules (bilingual labels):
1. Ingredient knowledge and function 配料的认识和作用
2. Mixing and processing techniques 搅拌和制作过程
3. Storage of ingredients 原料的储存
4. Baking skill and product finishing 烘焙技巧及成品研讨
5. Personal hygiene check list 个人卫生条例
6. Food handling principles 食物处理原则
7. Costing 成本计算
8. Safety at work 厨房安全
9. Bakery Management 管理学

Hotel internships: placement hotels include:
1. Hilton Kota Kinabalu
2. Grandis Hotels and Resorts (partnership / MOU)
3. Sutera Harbour Resort (Kota Kinabalu, Sabah)
4. Horizon Hotel (豪丽胜酒店)
5. Le Méridien Kota Kinabalu
6. Shangri-La's Tanjung Aru Resort & Spa (Kota Kinabalu)
7. Ming Garden Hotel & Residences
8. Dorsett Grand Labuan
9. Promenade Hotel Kota Kinabalu (亚庇凯城酒店)
10. Shangri-La's Rasa Ria Resort (Kota Kinabalu)
11. Beringgis Beach Resort & Spa
12. The Palace Hotel Kota Kinabalu
13. Genting Malaysia
Le Méridien HR has written that they normally take practical students as permanent employees after training.

Registration:
- Online form submits to Chef Ang WhatsApp.
- 1-week free trial class: student fills the form, Chef Ang contacts them to arrange one free trial week.

When you mention campus, include: [Open in Google Maps](https://maps.app.goo.gl/9gJ88r6btWrmy1DQA)
When you mention WhatsApp or register, include: [WhatsApp Chef Ang](https://wa.me/60178640709)
When you mention the website: [www.ppks.com.my](https://www.ppks.com.my)
Put each link on its own line. Never invent other URLs.

Short, warm, professional.
EOT;

$allMessages = array_merge([['role' => 'system', 'content' => $systemPrompt]], $messages);

$payload = json_encode([
    'model'       => 'qwen/qwen3-235b-a22b-2507',
    'messages'    => $allMessages,
    'temperature' => 0.4,
    'max_tokens'  => 700,
]);

$ch = curl_init('https://openrouter.ai/api/v1/chat/completions');
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => [
        'Authorization: Bearer ' . $key,
        'Content-Type: application/json',
        'HTTP-Referer: https://www.ppks.com.my',
        'X-Title: PPKS Landing Chat',
    ],
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
    CURLOPT_FOLLOWLOCATION => true,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr  = curl_error($ch);
curl_close($ch);

if ($curlErr) {
    http_response_code(502);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Could not reach assistant: ' . $curlErr]);
    exit;
}

http_response_code($httpCode);
header('Content-Type: application/json');
echo $response;
