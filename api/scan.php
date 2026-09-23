<?php
// Etiket scannen met Google Gemini (gratis variant).
// POST multipart met veld "photo" → JSON {wine: {...}} met de herkende gegevens.
// Vereist in api/config.php:  define('GEMINI_API_KEY', '...');
// Optioneel:                  define('GEMINI_MODEL', 'gemini-3.8-flash');
header('Content-Type: application/json');

$configFile = __DIR__ . '/config.php';
if (file_exists($configFile)) require $configFile;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'methode niet toegestaan']);
    exit;
}
if (!defined('GEMINI_API_KEY') || GEMINI_API_KEY === '' || strpos(GEMINI_API_KEY, 'PLAK-HIER') === 0) {
    http_response_code(503);
    echo json_encode(['error' => 'not_configured', 'message' => 'Scannen is nog niet ingesteld: zet GEMINI_API_KEY in api/config.php.']);
    exit;
}

$file = $_FILES['photo'] ?? null;
if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['error' => 'Geen foto ontvangen.']);
    exit;
}
if ($file['size'] > 10 * 1024 * 1024) {
    http_response_code(413);
    echo json_encode(['error' => 'Foto is groter dan 10 MB.']);
    exit;
}
$info = @getimagesize($file['tmp_name']);
$mimes = [IMAGETYPE_JPEG => 'image/jpeg', IMAGETYPE_PNG => 'image/png', IMAGETYPE_WEBP => 'image/webp'];
if (!$info || !isset($mimes[$info[2]])) {
    http_response_code(415);
    echo json_encode(['error' => 'Alleen JPEG, PNG of WebP.']);
    exit;
}
$mime = $mimes[$info[2]];
$data = base64_encode(file_get_contents($file['tmp_name']));

$year = (int) date('Y');
$prompt = <<<TXT
Je bent een ervaren sommelier. Op de foto staat een wijnetiket (of een fles). Lees het etiket en geef de gegevens van deze wijn.
- producer: de producent / het wijnhuis zoals op het etiket.
- name: de naam van de wijn / cuvée / appellation, zonder producent en zonder jaartal.
- year: de jaargang als getal, 0 als die niet zichtbaar is.
- grape: druif of blend (bv. "Chardonnay", "Sangiovese", "Cabernet/Merlot").
- region: regio en land in het Nederlands, bv. "Bourgogne, Frankrijk" of "Mosel, Duitsland".
- country: ISO-landcode met 2 letters, bv. FR, IT, DE, ES.
- color: één van rood, wit, rose, mousserend, dessert.
- windowFrom / windowTo: het drinkvenster in jaartallen (schat op basis van wijn en jaargang).
- status: "now" als de wijn in {$year} op dronk is, "soon" als hij binnen ongeveer 2 jaar op zijn best is of zijn venster bijna voorbij is, anders "wait".
- window: korte Nederlandse toelichting op het drinkvenster.
- character: 2 à 3 zinnen in het Nederlands over stijl en smaak.
- serving: 2 à 3 korte Nederlandse tips (temperatuur, karafferen, gerechten).
- priceEstimate: geschatte winkelprijs per fles in euro in Nederland, 0 als je het niet weet.
- size: flesinhoud in ml: 750 voor een gewone fles, 1500 voor een magnum, 375 voor een halve fles, 3000 voor een dubbele magnum. Kijk naar de inhoud op het etiket (bv. "1,5 L", "150 cl", "Magnum"); 750 als je het niet ziet.
- confidence: "high", "medium" of "low" voor hoe zeker je bent van de herkenning.
- labelText: de belangrijkste tekst die je letterlijk op het etiket leest.
Verzin geen producent of naam die niet op het etiket staat; laat een veld leeg als je het niet kunt lezen.
TXT;

$schema = [
    'type' => 'OBJECT',
    'properties' => [
        'producer' => ['type' => 'STRING'],
        'name' => ['type' => 'STRING'],
        'year' => ['type' => 'INTEGER'],
        'grape' => ['type' => 'STRING'],
        'region' => ['type' => 'STRING'],
        'country' => ['type' => 'STRING'],
        'color' => ['type' => 'STRING', 'enum' => ['rood', 'wit', 'rose', 'mousserend', 'dessert']],
        'windowFrom' => ['type' => 'INTEGER'],
        'windowTo' => ['type' => 'INTEGER'],
        'status' => ['type' => 'STRING', 'enum' => ['now', 'soon', 'wait']],
        'window' => ['type' => 'STRING'],
        'character' => ['type' => 'STRING'],
        'serving' => ['type' => 'ARRAY', 'items' => ['type' => 'STRING']],
        'priceEstimate' => ['type' => 'NUMBER'],
        'size' => ['type' => 'INTEGER'],
        'confidence' => ['type' => 'STRING', 'enum' => ['high', 'medium', 'low']],
        'labelText' => ['type' => 'STRING'],
    ],
    'required' => ['producer', 'name', 'year', 'color', 'status', 'confidence'],
];

$body = json_encode([
    'contents' => [[
        'parts' => [
            ['text' => $prompt],
            ['inline_data' => ['mime_type' => $mime, 'data' => $data]],
        ],
    ]],
    'generationConfig' => [
        'response_mime_type' => 'application/json',
        'response_schema' => $schema,
        'temperature' => 0.2,
    ],
]);

function gemini_call($model, $body) {
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($model) . ':generateContent';
    $headers = ['Content-Type: application/json', 'x-goog-api-key: ' . GEMINI_API_KEY];
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60,
        ]);
        $resp = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
    } else {
        $ctx = stream_context_create(['http' => [
            'method' => 'POST', 'header' => implode("\r\n", $headers), 'content' => $body,
            'timeout' => 60, 'ignore_errors' => true,
        ]]);
        $resp = @file_get_contents($url, false, $ctx);
        $code = 0;
        if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) $code = (int) $m[1];
    }
    return [$code, $resp];
}

// Eerst het slimste gratis model; bij een limiet of fout het lichtere model proberen.
$models = array_values(array_unique(array_filter([
    defined('GEMINI_MODEL') ? GEMINI_MODEL : 'gemini-3.8-flash',
    'gemini-3.5-flash-lite',
])));

$lastError = '';
foreach ($models as $model) {
    [$code, $resp] = gemini_call($model, $body);
    $json = json_decode((string) $resp, true);
    if ($code === 200 && isset($json['candidates'][0]['content']['parts'][0]['text'])) {
        $wine = json_decode($json['candidates'][0]['content']['parts'][0]['text'], true);
        if (is_array($wine)) {
            echo json_encode(['wine' => $wine, 'model' => $model]);
            exit;
        }
        $lastError = 'Onleesbaar antwoord van Gemini.';
        continue;
    }
    $lastError = $json['error']['message'] ?? ('HTTP ' . $code);
    // Alleen doorgaan naar het volgende model bij limiet, onbekend model of serverfout
    if (!in_array($code, [0, 404, 429, 500, 503], true)) break;
}

http_response_code(502);
echo json_encode(['error' => 'Scannen mislukt: ' . $lastError]);
