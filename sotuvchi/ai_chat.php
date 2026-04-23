<?php
session_start();
require_once "../config/dokon_db.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'sotuvchi') {
    http_response_code(403);
    echo json_encode(['error' => "Ruxsat yo'q"]);
    exit;
}

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$message = trim($input['message'] ?? '');

if (empty($message)) {
    echo json_encode(['error' => "Xabar bo'sh"]);
    exit;
}

$webhookUrl = 'http://localhost:5678/webhook/ai-chat';
$payload = json_encode(['message' => $message]);

$ch = curl_init($webhookUrl);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    echo json_encode(['error' => "Webhook bilan bog'lanib bo'lmadi: " . $curlError]);
    exit;
}

if ($httpCode !== 200) {
    echo json_encode(['error' => 'Webhook xato qaytardi: HTTP ' . $httpCode]);
    exit;
}

$data = json_decode($response, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode(['reply' => $response]);
    exit;
}

if (isset($data['output'])) {
    echo json_encode(['reply' => $data['output']]);
} elseif (isset($data['response'])) {
    echo json_encode(['reply' => $data['response']]);
} elseif (isset($data['text'])) {
    echo json_encode(['reply' => $data['text']]);
} elseif (isset($data['message'])) {
    echo json_encode(['reply' => $data['message']]);
} elseif (is_array($data) && isset($data[0])) {
    $first = $data[0];
    $reply = $first['output'] ?? $first['response'] ?? $first['text'] ?? $first['message'] ?? json_encode($first);
    echo json_encode(['reply' => $reply]);
} else {
    echo json_encode(['reply' => $response]);
}
