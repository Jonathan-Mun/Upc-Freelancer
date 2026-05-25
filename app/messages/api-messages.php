<?php
// ============================================================
// UPC FREELANCE — API : messages d'un contrat (JSON)
// /var/www/html/upc_freelance/app/messages/api-messages.php
// ============================================================

require_once '../../includes/middleware.php';
require_once '../../includes/auth.php';
require_once '../../includes/db.php';

requireLogin();

header('Content-Type: application/json');

$user       = currentUser();
$pdo        = getDB();
$contractId = (int)($_GET['contract_id'] ?? 0);
$since      = (int)($_GET['since'] ?? 0); // id du dernier message connu

if (!$contractId) { echo json_encode([]); exit; }

// Vérifier que l'utilisateur a accès à ce contrat
$stmt = $pdo->prepare('SELECT id FROM contracts WHERE id = ? AND (client_id = ? OR freelancer_id = ?)');
$stmt->execute([$contractId, $user['id'], $user['id']]);
if (!$stmt->fetch()) { http_response_code(403); echo json_encode([]); exit; }

// Récupérer uniquement les messages plus récents que $since
$stmt = $pdo->prepare('
    SELECT m.id, m.body, m.sender_id, m.created_at, m.is_read,
           u.first_name, u.last_name, u.avatar
    FROM messages m
    JOIN users u ON u.id = m.sender_id
    WHERE m.contract_id = ? AND m.id > ?
    ORDER BY m.created_at ASC
');
$stmt->execute([$contractId, $since]);
$msgs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Marquer comme lus les messages reçus
if (!empty($msgs)) {
    $pdo->prepare('UPDATE messages SET is_read = 1 WHERE contract_id = ? AND sender_id != ?')
        ->execute([$contractId, $user['id']]);
}

echo json_encode($msgs, JSON_UNESCAPED_UNICODE);