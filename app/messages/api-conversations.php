<?php
// ============================================================
// UPC FREELANCE — API : conversations (JSON)
// /var/www/html/upc_freelance/app/messages/api-conversations.php
// ============================================================

require_once '../../includes/middleware.php';
require_once '../../includes/auth.php';
require_once '../../includes/db.php';

requireLogin();

header('Content-Type: application/json');

$user   = currentUser();
$pdo    = getDB();
$userId = $user['id'];

$stmt = $pdo->prepare('
    SELECT ct.id, ct.status,
           p.title AS project_title,
           CASE WHEN ct.client_id = ? THEN fr.first_name ELSE cl.first_name END AS partner_fname,
           CASE WHEN ct.client_id = ? THEN fr.last_name  ELSE cl.last_name  END AS partner_lname,
           CASE WHEN ct.client_id = ? THEN fr.avatar     ELSE cl.avatar     END AS partner_avatar,
           (SELECT body       FROM messages WHERE contract_id = ct.id ORDER BY created_at DESC LIMIT 1) AS last_msg,
           (SELECT created_at FROM messages WHERE contract_id = ct.id ORDER BY created_at DESC LIMIT 1) AS last_msg_at,
           (SELECT COUNT(*)   FROM messages WHERE contract_id = ct.id AND sender_id != ? AND is_read = 0) AS unread
    FROM contracts ct
    JOIN projects p ON p.id = ct.project_id
    JOIN users cl   ON cl.id = ct.client_id
    JOIN users fr   ON fr.id = ct.freelancer_id
    WHERE (ct.client_id = ? OR ct.freelancer_id = ?)
    ORDER BY last_msg_at DESC, ct.created_at DESC
');
$stmt->execute([$userId,$userId,$userId,$userId,$userId,$userId]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($rows, JSON_UNESCAPED_UNICODE);