<?php
// ==============================================
// gemini-client.php — Google Gemini API wrapper
// ==============================================
// Direct cURL calls to Gemini API. No external library needed.

require_once __DIR__ . '/config.php';

/**
 * Send a conversation to Gemini and get a response
 * 
 * @param string $systemPrompt The personality system prompt
 * @param array $conversationHistory Array of ['role' => 'user'|'model', 'content' => '...']
 * @param string $model Which Gemini model to use
 * @return string|false The AI response text, or false on error
 */
function sendToGemini($systemPrompt, $conversationHistory, $model = null) {
    if ($model === null) {
        $model = GEMINI_MODEL_CHAT;
    }
    
    $url = GEMINI_API_URL . $model . ':generateContent?key=' . GEMINI_API_KEY;
    
    // Build the contents array
    $contents = [];
    
    // Check if the model supports system_instruction (Simplified check for projects)
    // Most Gemma models do not support system_instruction field in the payload
    $isGemma = (strpos($model, 'gemma') !== false);
    
    if ($isGemma) {
        // Prepend system prompt to the first user message for Gemma models
        if (!empty($conversationHistory)) {
            $conversationHistory[0]['content'] = "SYSTEM INSTRUCTION: " . $systemPrompt . "\n\nUSER MESSAGE: " . $conversationHistory[0]['content'];
        } else {
            $conversationHistory[] = ['role' => 'user', 'content' => $systemPrompt];
        }
    }
    
    foreach ($conversationHistory as $msg) {
        $role = ($msg['role'] === 'assistant') ? 'model' : 'user';
        $contents[] = [
            'role' => $role,
            'parts' => [['text' => $msg['content']]]
        ];
    }
    
    // Build request payload
    $payload = [
        'contents' => $contents,
        'generationConfig' => [
            'temperature' => 0.9,
            'topP' => 0.95,
            'topK' => 40,
            'maxOutputTokens' => 800
        ],
        'safetySettings' => [
            ['category' => 'HARM_CATEGORY_HARASSMENT', 'threshold' => 'BLOCK_NONE'],
            ['category' => 'HARM_CATEGORY_HATE_SPEECH', 'threshold' => 'BLOCK_NONE'],
            ['category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT', 'threshold' => 'BLOCK_NONE'],
            ['category' => 'HARM_CATEGORY_DANGEROUS_CONTENT', 'threshold' => 'BLOCK_NONE']
        ]
    ];
    
    // Only add system_instruction if not a Gemma model
    if (!$isGemma) {
        $payload['system_instruction'] = [
            'parts' => [['text' => $systemPrompt]]
        ];
    }
    
    // Make the API call
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_TIMEOUT, 40); // Increased timeout for analysis
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($curlError) {
        error_log("Gemini API cURL error for model {$model}: " . $curlError);
        return false;
    }
    
    if ($httpCode !== 200) {
        error_log("Gemini API HTTP {$httpCode} for model {$model}: " . $response);
        return false;
    }
    
    $data = json_decode($response, true);
    
    // Check if parts exist (if not, it might have been blocked despite settings)
    if (!isset($data['candidates'][0]['content']['parts'][0]['text'])) {
        error_log("Gemini API missing text content for model {$model}: " . $response);
        return false;
    }
    
    return $data['candidates'][0]['content']['parts'][0]['text'];
}

/**
 * Send analysis request to Gemini (uses the more powerful model)
 */
function sendAnalysisToGemini($analysisPrompt, $transcript) {
    $contents = [
        ['role' => 'user', 'content' => $transcript]
    ];
    
    return sendToGemini($analysisPrompt, $contents, GEMINI_MODEL_ANALYSIS);
}
