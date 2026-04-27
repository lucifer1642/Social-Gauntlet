<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';

if (!isLoggedIn()) {
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

$agentId = "agent_2801kq1dx0wkej2rjmgysyjtq9zf";
// API Key explicitly provided by user for local testing
$apiKey = "sk_1e371ec1071c47eee767497fc5bb05b47278e63febe63a98";

$url = "https://api.elevenlabs.io/v1/convai/conversation/get_signed_url?agent_id=" . $agentId;

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'xi-api-key: ' . $apiKey
]);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError || $httpCode !== 200) {
    echo json_encode(['error' => 'Failed to generate signed url', 'details' => $response]);
    exit;
}

echo $response;
?>
