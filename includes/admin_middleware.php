<?php
// ============================================================
// UPC FREELANCE — Middleware Admin
// /upc_freelance/includes/admin_middleware.php
//
// À inclure en haut de chaque page du panel admin :
//   require_once '../../includes/admin_middleware.php';
// ============================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Pas connecté en tant qu'admin → retour login
if (empty($_SESSION['is_admin']) || empty($_SESSION['admin_id'])) {
    // Effacer toute session utilisateur normale pour éviter confusion
    if (!empty($_SESSION['user_id'])) {
        flash('error', 'Accès réservé aux administrateurs.');
    }
    header('Location: /upc_freelance/public/login.php');
    exit;
}

// Optionnel : page super-admin uniquement
function requireSuperAdmin(): void {
    if (empty($_SESSION['admin_super'])) {
        http_response_code(403);
        die('Accès refusé — Super-admin requis.');
    }
}

// Helper : données admin courantes
function currentAdmin(): array {
    return [
        'id'    => $_SESSION['admin_id'],
        'name'  => $_SESSION['admin_name'],
        'email' => $_SESSION['admin_email'],
        'super' => $_SESSION['admin_super'] ?? false,
    ];
}