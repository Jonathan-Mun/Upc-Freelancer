<?php
// ============================================================
// UPC FREELANCE — Déconnexion Admin
// /upc_freelance/admin/logout.php
// ============================================================

if (session_status() === PHP_SESSION_NONE) session_start();

// Supprimer uniquement les données admin
unset(
    $_SESSION['admin_id'],
    $_SESSION['admin_name'],
    $_SESSION['admin_email'],
    $_SESSION['admin_super'],
    $_SESSION['is_admin']
);

// Si plus rien en session, détruire complètement
if (empty($_SESSION)) {
    session_destroy();
}

header('Location: /upc_freelance/public/login.php');
exit;