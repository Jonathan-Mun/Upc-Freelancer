<?php
// ============================================================
// UPC FREELANCE — API : notifications (JSON)
// /var/www/html/upc_freelance/app/notifications/api-notifications.php
// ============================================================

require_once '../../includes/middleware.php';
require_once '../../includes/auth.php';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';

requireLogin();

header('Content-Type: application/json');

$user   = currentUser();
$pdo    = getDB();
$action = $_GET['action'] ?? 'poll';

// ── Action : juste le compteur (pour le badge header) ─────
if ($action === 'count') {
    echo json_encode(['unread' => countUnreadNotifications($user['id'])]);
    exit;
}

// ── Action : marquer tout comme lu ────────────────────────
if ($action === 'mark_all' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ?')
        ->execute([$user['id']]);
    echo json_encode(['ok' => true, 'unread' => 0]);
    exit;
}

// ── Action : marquer une notif comme lue ──────────────────
if ($action === 'mark_one' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id) {
        $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?')
            ->execute([$id, $user['id']]);
    }
    echo json_encode(['ok' => true]);
    exit;
}

// ── Action : poll — renvoie les notifs depuis un certain id
// ?since=<last_known_id>  (0 = tout récupérer pour init)
$since = (int)($_GET['since'] ?? 0);
$limit = (int)($_GET['limit'] ?? 20);
$limit = min($limit, 50); // cap de sécurité

if ($since > 0) {
    // Seulement les nouvelles
    $stmt = $pdo->prepare('
        SELECT id, type, title, body, link, is_read, created_at
        FROM notifications
        WHERE user_id = ? AND id > ?
        ORDER BY created_at DESC
        LIMIT ?
    ');
    $stmt->execute([$user['id'], $since, $limit]);
} else {
    // Initialisation : les 20 dernières
    $stmt = $pdo->prepare('
        SELECT id, type, title, body, link, is_read, created_at
        FROM notifications
        WHERE user_id = ?
        ORDER BY created_at DESC
        LIMIT ?
    ');
    $stmt->execute([$user['id'], $limit]);
}

$notifs = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'unread'        => countUnreadNotifications($user['id']),
    'notifications' => $notifs,
], JSON_UNESCAPED_UNICODE);