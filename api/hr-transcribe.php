<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/session-manager.php';

if (!isLoggedIn()) {
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'POST required']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$sessionId = intval($input['session_id'] ?? 0);
$audioBase64 = trim($input['audio_base64'] ?? '');
$mimeType = trim($input['mime_type'] ?? 'audio/webm');

if (!$sessionId || $audioBase64 === '') {
    echo json_encode(['error' => 'Missing audio payload']);
    exit;
}

$session = getSession($sessionId, $_SESSION['user_id']);
if (!$session || ($session['mode'] ?? 'standard') !== 'hr') {
    echo json_encode(['error' => 'HR session not found']);
    exit;
}

$url = GEMINI_API_URL . GEMINI_MODEL_ANALYSIS . ':generateContent?key=' . GEMINI_API_KEY;
$prompt = "Transcribe this interview audio accurately into plain text. Return only the spoken words, no labels, no punctuation cleanup commentary.";

$payload = [
    'contents' => [[
        'role' => 'user',
        'parts' => [
            ['text' => $prompt],
            [
                'inlineData' => [
                    'mimeType' => $mimeType,
                    'data' => $audioBase64
                ]
            ]
        ]
    ]],
    'generationConfig' => [
        'temperature' => 0.1,
        'maxOutputTokens' => 512
    ]
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_TIMEOUT, 90);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    echo json_encode(['error' => 'Transcription network error']);
    exit;
}

if ($httpCode !== 200) {
    echo json_encode(['error' => 'Transcription unavailable']);
    exit;
}

$data = json_decode($response, true);
$text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
$text = trim(preg_replace('/\s+/', ' ', $text));

if ($text === '') {
    echo json_encode(['error' => 'No speech transcribed']);
    exit;
}

echo json_encode([
    'success' => true,
    'text' => $text
]);
