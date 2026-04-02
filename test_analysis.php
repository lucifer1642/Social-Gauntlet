<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/gemini-client.php';

$dummyTranscript = "TOPIC: Asking for a raise\n\n--- ROUND 1: Micromanager ---\n[USER]: I believe I deserve a raise because I have exceeded all my KPIs this quarter.\nAI_PERSONA: Exceeded? Or just met the bare minimum while taking too many breaks?\n\n[USER]: No, I literally doubled the target sales.\nAI_PERSONA: Doubled, or just got lucky with one client? I need to see every email you sent.\n";

$analysisPrompt = "You are a communication psychologist. Analyze the following transcript for behavioral patterns. Respond in JSON format.";

echo "Testing Gemini Analysis API with model: " . GEMINI_MODEL_ANALYSIS . "\n";
echo "Prompt size: " . strlen($analysisPrompt) . " chars\n";
echo "Transcript size: " . strlen($dummyTranscript) . " chars\n";

$response = sendAnalysisToGemini($analysisPrompt, $dummyTranscript);

if ($response === false) {
    echo "FAILED: Analysis service returned false.\n";
    echo "Check C:/xampp/apache/logs/error.log or project error logs for details.\n";
} else {
    echo "SUCCESS! Received response:\n";
    echo "--------------------------\n";
    echo $response . "\n";
}
