<?php
// ============================================================
// UPC FREELANCE — Admin : Analyse IA anti-fraude (Groq)
// /admin/api-ai-analyze.php
// ============================================================

ob_start();
set_exception_handler(function($e) {
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Erreur serveur : ' . $e->getMessage()]);
    exit;
});

require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/admin_middleware.php';
require_once '../includes/ai-config.php';

$admin = currentAdmin();
ob_clean();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Méthode non autorisée']);
    exit;
}

$input      = json_decode(file_get_contents('php://input'), true);
$contractId = (int)($input['contract_id'] ?? 0);
if (!$contractId) { echo json_encode(['error' => 'Contrat invalide.']); exit; }

$pdo = getDB();

$stmt = $pdo->prepare('
    SELECT c.*, p.title AS project_title
    FROM contracts c JOIN projects p ON p.id = c.project_id
    WHERE c.id = ?
');
$stmt->execute([$contractId]);
$contract = $stmt->fetch();
if (!$contract) { echo json_encode(['error' => 'Contrat introuvable.']); exit; }

$msgStmt = $pdo->prepare('SELECT body, sender_id, created_at FROM messages WHERE contract_id = ? ORDER BY created_at ASC');
$msgStmt->execute([$contractId]);
$messages = $msgStmt->fetchAll();

if (empty($messages)) {
    echo json_encode(['error' => 'Aucun message à analyser pour ce contrat.']);
    exit;
}

$transcript = '';
foreach ($messages as $m) {
    $label = ((int)$m['sender_id'] === (int)$contract['client_id']) ? 'Client' : 'Freelancer';
    $transcript .= $label . ' (' . $m['created_at'] . ') : ' . $m['body'] . "\n";
}
if (mb_strlen($transcript) > 12000) {
    $transcript = mb_substr($transcript, 0, 12000) . "\n[...conversation tronquée...]";
}

$context = "Montant du contrat : " . $contract['amount'] . " USD\n"
         . "Statut actuel : " . $contract['status'] . "\n"
         . (!empty($contract['cancel_requested_by']) ? "Demande d'annulation du client après livraison possible. Raison donnée : " . $contract['cancel_reason'] . "\n" : '')
         . (!empty($contract['disputed_by']) ? "Litige ouvert. Raison donnée : " . $contract['dispute_reason'] . "\n" : '');

$apiKey = defined('GROQ_API_KEY') ? GROQ_API_KEY : '';
$model  = defined('GROQ_MODEL')   ? GROQ_MODEL   : 'llama-3.3-70b-versatile';
if (empty($apiKey)) { echo json_encode(['error' => "Clé API Groq non configurée."]); exit; }

function callGroqAdmin(string $apiKey, string $model, string $system, string $user, int $maxTokens = 700): ?string {
    $payload = json_encode([
        'model'       => $model,
        'messages'    => [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user',   'content' => $user],
        ],
        'temperature' => 0.3,
        'max_tokens'  => $maxTokens,
    ]);

    $ch = curl_init('https://api.groq.com/openai/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Authorization: Bearer ' . $apiKey],
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err      = curl_error($ch);
    curl_close($ch);

    if ($err || $httpCode !== 200) {
        error_log("Groq error (admin analyze) - HTTP: $httpCode | cURL: $err");
        return null;
    }

    $data = json_decode($response, true);
    return trim($data['choices'][0]['message']['content'] ?? '');
}

$system = 'Tu es un analyste anti-fraude pour une plateforme freelance étudiante ivoirienne. '
        . "Tu examines des échanges entre un client et un freelancer pour détecter des signaux d'alerte : "
        . "tentative de paiement hors plateforme, menaces ou pression, incohérences dans les échanges, "
        . "preuve de livraison suivie d'une annulation suspecte, langage évasif ou agressif. "
        . 'Tu réponds UNIQUEMENT en JSON valide, sans markdown, sans backtick, sans texte avant ou après.';

$prompt = <<<PROMPT
Contexte du contrat :
{$context}

Conversation entre le client et le freelancer :
{$transcript}

Analyse cette conversation et retourne ce JSON exact (sans aucun texte autour) :
{
  "risk_level": "faible",
  "summary": "résumé factuel de la situation en 2-3 phrases en français",
  "red_flags": ["signal d'alerte 1", "signal d'alerte 2"],
  "recommendation": "action concrète recommandée pour l'équipe admin"
}

- risk_level doit être exactement : "faible", "moyen" ou "élevé"
- red_flags : liste vide [] si rien d'anormal détecté
- Base-toi uniquement sur ce qui est écrit dans la conversation, ne suppose rien d'autre
PROMPT;

$raw = callGroqAdmin($apiKey, $model, $system, $prompt, 700);

if (!$raw) {
    echo json_encode(['error' => "Impossible de joindre l'API Groq."]);
    exit;
}

$raw   = preg_replace('/```json\s*/i', '', $raw);
$raw   = preg_replace('/```\s*/i', '', $raw);
$start = strpos($raw, '{');
$end   = strrpos($raw, '}');
if ($start === false || $end === false) {
    echo json_encode(['error' => "L'IA n'a pas retourné de JSON valide."]);
    exit;
}

$analysis = json_decode(substr($raw, $start, $end - $start + 1), true);
if (!$analysis || !isset($analysis['risk_level'])) {
    echo json_encode(['error' => 'Analyse invalide, réessayez.']);
    exit;
}

$analysisJson = json_encode($analysis, JSON_UNESCAPED_UNICODE);
$pdo->prepare('UPDATE contracts SET ai_analysis = ?, ai_analyzed_at = NOW() WHERE id = ?')
    ->execute([$analysisJson, $contractId]);

// Auto-flag si risque élevé détecté
if ($analysis['risk_level'] === 'élevé') {
    $pdo->prepare('UPDATE contracts SET fraud_flag = 1 WHERE id = ?')->execute([$contractId]);
}

echo json_encode(['ok' => true, 'analysis' => $analysis]);