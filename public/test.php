<?php

$apiKey = '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';

$data = [
    "model" => "llama-3.3-70b-versatile",
    "messages" => [
        [
            "role" => "user",
            "content" => "Dis juste OK"
        ]
    ],
    "temperature" => 0,
    "max_tokens" => 256
];

$ch = curl_init();

curl_setopt_array($ch, [
    CURLOPT_URL => 'https://api.groq.com/openai/v1/chat/completions',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey
    ],
    CURLOPT_POSTFIELDS => json_encode($data),
]);

$response = curl_exec($ch);

// ⚠️ Vérif erreur réseau
if ($response === false) {
    echo "Erreur cURL : " . curl_error($ch);
    curl_close($ch);
    exit;
}

$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "<h3>HTTP CODE : $httpCode</h3>";

echo "<pre>";
echo htmlspecialchars($response);
echo "</pre>";

// Test parsing JSON
$json = json_decode($response, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    echo "<h3 style='color:red;'>JSON invalide</h3>";
    echo json_last_error_msg();
} else {
    echo "<h3>Réponse IA :</h3>";
    echo $json['choices'][0]['message']['content'] ?? 'Aucune réponse';
}