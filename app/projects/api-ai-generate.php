<?php
// ============================================================
// UPC FREELANCE — API : Génération de projet par IA (Groq)
// ../../app/projects/api-ai-generate.php
// ============================================================

ob_start();
set_exception_handler(function($e) {
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Erreur serveur : ' . $e->getMessage()]);
    exit;
});

require_once '../../includes/middleware.php';
require_once '../../includes/auth.php';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/ai-config.php';

requireRole('client', 'freelancer');
ob_clean();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Méthode non autorisée']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$brief = trim($input['brief'] ?? '');

if (empty($brief) || mb_strlen($brief) < 10) {
    echo json_encode(['error' => 'Décrivez votre projet en au moins 10 caractères.']);
    exit;
}

$apiKey = defined('GROQ_API_KEY') ? GROQ_API_KEY : '';
$model  = defined('GROQ_MODEL')   ? GROQ_MODEL   : 'llama-3.3-70b-versatile';

if (empty($apiKey)) {
    echo json_encode(['error' => 'Clé API Groq non configurée.']);
    exit;
}

// ── Catégories ───────────────────────────────────────────
$pdo        = getDB();
$categories = $pdo->query('SELECT id, name FROM categories WHERE is_active = 1 ORDER BY name')
                  ->fetchAll(PDO::FETCH_ASSOC);
$catList    = implode(', ', array_map(fn($c) => $c['name'].' (id:'.$c['id'].')', $categories));

// ── Fonction appel Groq ───────────────────────────────────
function callGroq(string $apiKey, string $model, string $system, string $user, int $maxTokens = 1024): ?string {
    $payload = json_encode([
        'model'       => $model,
        'messages'    => [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user',   'content' => $user],
        ],
        'temperature' => 0.7,
        'max_tokens'  => $maxTokens,
    ]);

    $ch = curl_init('https://api.groq.com/openai/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ],
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err      = curl_error($ch);
    curl_close($ch);

    if ($err || $httpCode !== 200) {
        // Retourner un marqueur d'erreur lisible plutôt que null
        error_log("Groq error - HTTP: $httpCode | cURL: $err | Response: " . substr($response ?? '', 0, 200));
        return '__ERROR__:' . $httpCode . ':' . $err . ':' . substr($response ?? '', 0, 200);
    }

    $data = json_decode($response, true);
    return trim($data['choices'][0]['message']['content'] ?? '');
}

// ── PASSE 1 : Infos structurées (JSON court sans description) ──
$systemShort = 'Tu es un assistant pour une plateforme freelance ivoirienne. Tu retournes UNIQUEMENT du JSON valide, sans markdown, sans backtick, sans texte avant ou après.';

$promptShort = <<<PROMPT
Brief : "{$brief}"
Catégories : {$catList}

Retourne ce JSON (sans aucun texte autour) :
{
  "title": "titre du projet max 100 caractères",
  "skills": ["technologie1", "technologie2", "technologie3", "technologie4"],
  "budget_min": 10,
  "budget_max": 5000,
  "category_id": 1,
  "deadline_days": 30,
  "visibility": "public"
}

- skills = technologies/langages/outils concrets pour réaliser ce projet (PHP, MySQL, JS, React, Figma, etc.)
- budgets en Francs CFA, réalistes pour un étudiant freelance ivoirien
- deadline_days entre 14 et 90
PROMPT;

$rawShort = callGroq($apiKey, $model, $systemShort, $promptShort, 512);

if (!$rawShort || str_starts_with($rawShort, '__ERROR__')) {
    $parts = $rawShort ? explode(':', $rawShort, 4) : [];
    echo json_encode([
        'error'      => 'Impossible de joindre l\'API Groq.',
        'http_code'  => $parts[1] ?? 'N/A',
        'curl_error' => $parts[2] ?? 'N/A',
        'response'   => $parts[3] ?? 'N/A',
        'key_ok'     => !empty($apiKey),
    ]);
    exit;
}

// Extraire le JSON
$rawShort = preg_replace('/```json\s*/i', '', $rawShort);
$rawShort = preg_replace('/```\s*/i', '', $rawShort);
$start    = strpos($rawShort, '{');
$end      = strrpos($rawShort, '}');
if ($start === false || $end === false) {
    echo json_encode(['error' => 'L\'IA n\'a pas retourné de JSON valide. Réessayez.']);
    exit;
}
$generated = json_decode(substr($rawShort, $start, $end - $start + 1), true);

if (!$generated || !isset($generated['title'])) {
    echo json_encode(['error' => 'Données générées invalides. Réessayez.']);
    exit;
}

// ── PASSE 2 : Description longue (texte libre, sans JSON) ──
$systemDesc = 'Tu es un expert en rédaction de projets freelance pour une plateforme étudiante ivoirienne. Tu rédiges des descriptions professionnelles, détaillées et convaincantes.';

$promptDesc = <<<PROMPT
Rédige une description détaillée pour ce projet freelance :

Titre : {$generated['title']}
Brief client : "{$brief}"

La description doit contenir exactement 5 sections séparées par "|||" :

Paragraphe 1 :
Présente le contexte du projet, son utilité et son environnement.

Paragraphe 2 :
Explique les objectifs recherchés par le client.

Paragraphe 3 :
Décris en détail les fonctionnalités attendues (au moins 5 fonctionnalités).

Paragraphe 4 :
Présente les exigences techniques, contraintes et compatibilités.

Paragraphe 5 :
Décris les modalités de livraison, les délais et les critères de validation.

IMPORTANT :
- N'écris jamais de titre, sous-titre ou nom de section.
- N'écris jamais "Section 1", "Contexte", "Objectifs", "Fonctionnalités", "Exigences techniques" ou "Livraison".
- Chaque partie doit commencer directement par une phrase naturelle comme dans un cahier des charges rédigé par un humain.
- Sépare uniquement les 5 paragraphes avec "|||".
- Ne mets aucun texte avant le premier paragraphe.
- Ne mets aucun texte après le cinquième paragraphe.
- Toute la description doit être rédigée à la première personne, comme si le client écrivait lui-même son besoin.
- N'écris jamais comme un observateur ou un rédacteur externe décrivant le projet.
- La description doit donner l'impression qu'elle a été rédigée directement par le porteur du projet.
Réponds UNIQUEMENT avec le texte des 5 sections séparées par |||, rien d'autre.
PROMPT;

$rawDesc = callGroq($apiKey, $model, $systemDesc, $promptDesc, 1500);

if ($rawDesc && strlen($rawDesc) > 50) {
    // Convertir ||| en doubles sauts de ligne
    $generated['description'] = str_replace('|||', "\n\n", $rawDesc);
} else {
    $generated['description'] = '';
}

// ── Calculer deadline ────────────────────────────────────
if (!empty($generated['deadline_days'])) {
    $generated['deadline'] = date('Y-m-d', strtotime('+' . (int)$generated['deadline_days'] . ' days'));
    unset($generated['deadline_days']);
}

$generated['_debug_raw'] = $rawDesc;
$generated['_debug_len'] = strlen($rawDesc ?? '');
echo json_encode(['ok' => true, 'project' => $generated]);