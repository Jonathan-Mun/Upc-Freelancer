<?php
require_once '../../includes/ai-config.php';

header('Content-Type: application/json');

$input = json_decode(file_get_contents("php://input"), true);
$brief = $input['brief'] ?? '';

if (!$brief) {
    http_response_code(400);
    echo json_encode(["error" => "Brief manquant"]);
    exit;
}

$payload = [
    "model" => GROQ_MODEL,
    "messages" => [
        ["role" => "user", "content" => "Génère un projet freelance basé sur : " . $brief]
    ],
    "temperature" => 0.7
];

$ch = curl_init("https://api.groq.com/openai/v1/chat/completions");

curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        "Content-Type: application/json",
        "Authorization: Bearer " . GROQ_API_KEY
    ],
    CURLOPT_POSTFIELDS => json_encode($payload),
]);

$response = curl_exec($ch);

if ($response === false) {
    http_response_code(500);
    echo json_encode([
        "error" => "Erreur cURL",
        "details" => curl_error($ch)
    ]);
    curl_close($ch);
    exit;
}

$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// 🔥 IMPORTANT : vérifier réponse vide
if (empty($response)) {
    http_response_code(500);
    echo json_encode(["error" => "Réponse vide de l'API"]);
    exit;
}

// Si erreur API
if ($httpCode >= 400) {
    http_response_code($httpCode);
    echo json_encode([
        "error" => "Erreur Groq",
        "raw" => $response
    ]);
    exit;
}

// 🔥 sécurité JSON (évite crash frontend)
json_decode($response);
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(500);
    echo json_encode([
        "error" => "Réponse Groq invalide JSON",
        "raw" => $response
    ]);
    exit;
}

echo $response;