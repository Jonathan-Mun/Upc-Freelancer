<?php
// ============================================================
// UPC FREELANCE — Backend Login
// /upc_freelance/auth/login.php
// ============================================================

require_once '../includes/middleware.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';

allowMethods('POST');
verifyCsrf();

if (!rateLimit('login', 5, 300)) {
    flash('error', 'Trop de tentatives. Réessayez dans 5 minutes.');
    redirect('../public/login.php');
}

$email    = sanitize($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

if (empty($email) || empty($password)) {
    flash('error', 'Veuillez remplir tous les champs.');
    redirect('../public/login.php');
}

$pdo = getDB();

// ── 1. Vérifier d'abord si c'est un admin ────────────────────
$stmt = $pdo->prepare('SELECT * FROM admin_users WHERE email = ? LIMIT 1');
$stmt->execute([$email]);
$admin = $stmt->fetch();

if ($admin && password_verify($password, $admin['password_hash'])) {
    // Connexion admin
    session_regenerate_id(true);
    $_SESSION['admin_id']    = $admin['id'];
    $_SESSION['admin_name']  = $admin['name'];
    $_SESSION['admin_email'] = $admin['email'];
    $_SESSION['admin_super'] = (bool)$admin['is_super'];
    $_SESSION['is_admin']    = true;

    // Mettre à jour last_login si la colonne existe
    // (optionnel — pas dans le schéma actuel)

    flash('success', 'Bienvenue, ' . $admin['name'] . ' !');
    redirect('../admin/dashboard.php');
}

// ── 2. Sinon vérifier les utilisateurs normaux ────────────────
$user = getUserByEmail($email);

if (!$user || !password_verify($password, $user['password_hash'])) {
    flash('error', 'Email ou mot de passe incorrect.');
    redirect('../public/login.php');
}

if (!$user['is_active']) {
    flash('error', 'Votre compte est désactivé. Contactez le support.');
    redirect('../public/login.php');
}

loginUser($user);
flash('success', 'Bienvenue, ' . $user['first_name'] . ' !');
redirect('../app/dashboard.php');