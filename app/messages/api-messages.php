<?php
// ============================================================
// UPC FREELANCE — API polling messages (AJAX)
// /var/www/html/upc_freelance/app/messages/api-messages.php
// ============================================================

require_once '/var/www/html/upc_freelance/includes/middleware.php';
require_once '/var/www/html/upc_freelance/includes/auth.php';
require_once '/var/www/html/upc_freelance/includes/db.php';

header('Content-Type: application/json');

// Auth obligatoire
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode([]);
    exit;
}

$user       = currentUser();
$pdo        = getDB();
$contractId = (int)($_GET['contract_id'] ?? 0);
$since      = (int)($_GET['since']       ?? 0);

if (!$contractId) {
    echo json_encode([]);
    exit;
}

// Vérifier que l'utilisateur appartient bien à ce contrat
$check = $pdo->prepare('SELECT id FROM contracts WHERE id = ? AND (client_id = ? OR freelancer_id = ?)');
$check->execute([$contractId, $user['id'], $user['id']]);
if (!$check->fetch()) {
    http_response_code(403);
    echo json_encode([]);
    exit;
}

// Récupérer les nouveaux messages depuis $since
$stmt = $pdo->prepare('
    SELECT m.id, m.body, m.sender_id, m.created_at,
           u.first_name, u.last_name, u.avatar
    FROM messages m
    JOIN users u ON u.id = m.sender_id
    WHERE m.contract_id = ? AND m.id > ?
    ORDER BY m.created_at ASC
    LIMIT 50
');
$stmt->execute([$contractId, $since]);
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Marquer comme lus les messages de l'autre personne
if (!empty($messages)) {
    $pdo->prepare('UPDATE messages SET is_read = 1 WHERE contract_id = ? AND sender_id != ? AND id > ?')
        ->execute([$contractId, $user['id'], $since]);
}

echo json_encode($messages);