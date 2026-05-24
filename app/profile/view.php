<?php
// ============================================================
// UPC FREELANCE — Voir un profil (redirige selon le rôle)
// /var/www/html/upc_freelance/app/profile/view.php
// ============================================================

require_once '/var/www/html/upc_freelance/includes/middleware.php';
require_once '/var/www/html/upc_freelance/includes/auth.php';
require_once '/var/www/html/upc_freelance/includes/functions.php';
require_once '/var/www/html/upc_freelance/includes/db.php';

$pdo    = getDB();
$userId = (int)($_GET['id'] ?? 0);

// Si pas d'ID, voir son propre profil
if (!$userId) {
    requireLogin();
    $userId = currentUser()['id'];
}

$stmt = $pdo->prepare('SELECT role FROM users WHERE id = ? AND is_active = 1');
$stmt->execute([$userId]);
$userRole = $stmt->fetchColumn();

if (!$userRole) {
    http_response_code(404);
    die('Profil introuvable.');
}

if ($userRole === 'freelancer') {
    redirect('/var/www/html/upc_freelance/app/profile/freelancer-profile.php?id=' . $userId);
} else {
    redirect('/var/www/html/upc_freelance/app/profile/client-profile.php?id=' . $userId);
}
