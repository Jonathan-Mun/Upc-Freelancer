<?php
// ============================================================
// UPC FREELANCE — Supprimer un administrateur
// ============================================================

require_once '../includes/admin_middleware.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';

requireSuperAdmin();
allowMethods('POST');
verifyCsrf();

$pdo     = getDB();
$admin   = currentAdmin();
$adminId = (int)($_POST['admin_id'] ?? 0);

// Empêcher de se supprimer soi-même
if ($adminId && $adminId !== $admin['id']) {
    $pdo->prepare('DELETE FROM admin_users WHERE id = ?')->execute([$adminId]);
    flash('success', 'Administrateur supprimé.');
} else {
    flash('error', 'Action impossible.');
}

redirect('/upc_freelance/admin/admins/create.php');