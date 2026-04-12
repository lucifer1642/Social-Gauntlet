<?php
// ==============================================
// gemini-client.php — Dual-Provider AI Client
// Chat: NVIDIA NIM (Qwen 3) | Analysis: Google Gemini
// ==============================================
require_once __DIR__ . '/config.php';

/**
 * Send a conversation to Qwen via NVIDIA NIM (used for live chat rounds)
 */
function sendToGemini($systemPrompt, $conversationHistory, $model = null) {
    if ($model === null) {
        $model = NVIDIA_MODEL_CHAT;
    }
    
    // Build OpenAI-compatible messages
    $messages = [
        ['role' => 'system', 'content' => $systemPrompt]
    ];
    
    foreach ($conversationHistory as $msg) {
        $role = ($msg['role'] === 'model' || $msg['role'] === 'assistant') ? 'assistant' : 'user';
        $messages[] = [
            'role' => $role,
            'content' => $msg['content']
        ];
    }
    
    $payload = [
        'model' => $model,
        'messages' => $messages,
        'temperature' => 0.8,
        'top_p' => 0.9,
        'max_tokens' => 800
    ];
    
    $ch = curl_init(NVIDIA_API_URL);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . NVIDIA_API_KEY
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_TIMEOUT, 60); 
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($curlError) {
        error_log("NVIDIA API cURL error for model {$model}: " . $curlError);
        return false;
    }
    
    if ($httpCode !== 200) {
        error_log("NVIDIA API HTTP {$httpCode} for model {$model}: " . $response);
        return false;
    }
    
    $data = json_decode($response, true);
    
    if (!isset($data['choices'][0]['message']['content'])) {
        error_log("NVIDIA API missing text content. Data: " . $response);
        return false;
    }
    
    return $data['choices'][0]['message']['content'];
}

/**
 * Send analysis/report request to Google Gemini with Qwen Fallback
 */
function sendAnalysisToGemini($analysisPrompt, $transcript) {
    // 1. Try Gemini First
    $geminiModel = GEMINI_MODEL_ANALYSIS;
    $geminiUrl = GEMINI_API_URL . $geminiModel . ':generateContent?key=' . GEMINI_API_KEY;
    
    $geminiPayload = [
        'system_instruction' => [
            'parts' => [['text' => $analysisPrompt]]
        ],
        'contents' => [
            [
                'role' => 'user',
                'parts' => [['text' => $transcript]]
            ]
        ],
        'generationConfig' => [
            'temperature' => 0.7,
            'topP' => 0.9,
            'maxOutputTokens' => 8192
        ]
    ];
    
    $ch1 = curl_init($geminiUrl);
    curl_setopt($ch1, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch1, CURLOPT_POST, true);
    curl_setopt($ch1, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch1, CURLOPT_POSTFIELDS, json_encode($geminiPayload));
    curl_setopt($ch1, CURLOPT_TIMEOUT, 90);
    curl_setopt($ch1, CURLOPT_SSL_VERIFYPEER, false);
    
    $response1 = curl_exec($ch1);
    $httpCode1 = curl_getinfo($ch1, CURLINFO_HTTP_CODE);
    curl_close($ch1);
    
    // If Gemini succeeded, return it immediately
    if ($httpCode1 === 200) {
        $data1 = json_decode($response1, true);
        if (isset($data1['candidates'][0]['content']['parts'][0]['text'])) {
            return $data1['candidates'][0]['content']['parts'][0]['text'];
        }
    }
    
    // 2. Gemini Failed (likely 429 Quota Exceeded). Fallback to Qwen (NVIDIA)
    error_log("Gemini failed with HTTP {$httpCode1}. Falling back to Qwen.");
    
    $nvModel = NVIDIA_MODEL_CHAT;
    $nvMessages = [
        ['role' => 'system', 'content' => $analysisPrompt],
        ['role' => 'user', 'content' => $transcript]
    ];
    
    $nvPayload = [
        'model' => $nvModel,
        'messages' => $nvMessages,
        'temperature' => 0.7,
        'top_p' => 0.9,
        'max_tokens' => 8192
    ];
    
    $ch2 = curl_init(NVIDIA_API_URL);
    curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch2, CURLOPT_POST, true);
    curl_setopt($ch2, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . NVIDIA_API_KEY
    ]);
    curl_setopt($ch2, CURLOPT_POSTFIELDS, json_encode($nvPayload));
    curl_setopt($ch2, CURLOPT_TIMEOUT, 120);
    curl_setopt($ch2, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch2, CURLOPT_SSL_VERIFYHOST, 0);
    
    $response2 = curl_exec($ch2);
    $httpCode2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
    curl_close($ch2);
    
    $data2 = json_decode($response2, true);
    return $data2['choices'][0]['message']['content'] ?? false;
}
