<?php
// ============================================================
// UPC FREELANCE — API : messages directs (polling)
// /var/www/html/upc_freelance/app/messages/api-direct.php
// ============================================================

require_once '../../includes/middleware.php';
require_once '../../includes/auth.php';
require_once '../../includes/db.php';

requireLogin();
header('Content-Type: application/json');

$user      = currentUser();
$pdo       = getDB();
$partnerId = (int)($_GET['partner_id'] ?? 0);
$since     = (int)($_GET['since']     ?? 0);

if (!$partnerId) { echo json_encode([]); exit; }

$stmt = $pdo->prepare('
    SELECT dm.id, dm.body, dm.sender_id, dm.created_at,
           u.first_name, u.last_name, u.avatar
    FROM direct_messages dm
    JOIN users u ON u.id = dm.sender_id
    WHERE ((dm.sender_id = ? AND dm.receiver_id = ?)
        OR (dm.sender_id = ? AND dm.receiver_id = ?))
      AND dm.id > ?
    ORDER BY dm.created_at ASC
');
$stmt->execute([$user['id'], $partnerId, $partnerId, $user['id'], $since]);
$msgs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Marquer comme lus
if (!empty($msgs)) {
    $pdo->prepare('UPDATE direct_messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ?')
        ->execute([$partnerId, $user['id']]);
}

echo json_encode($msgs, JSON_UNESCAPED_UNICODE);