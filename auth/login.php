<?php
// ============================================================
// UPC FREELANCE — Backend Login
// /var/www/html/upc_freelance/auth/login.php
// ============================================================

require_once '/var/www/html/upc_freelance/includes/middleware.php';
require_once '/var/www/html/upc_freelance/includes/auth.php';
require_once '/var/www/html/upc_freelance/includes/functions.php';

allowMethods('POST');
verifyCsrf();

if (!rateLimit('login', 5, 300)) {
    flash('error', 'Trop de tentatives. Réessayez dans 5 minutes.');
    redirect('/var/www/html/upc_freelance/public/login.php');
}

$email    = sanitize($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

if (empty($email) || empty($password)) {
    flash('error', 'Veuillez remplir tous les champs.');
    redirect('/var/www/html/upc_freelance/public/login.php');
}

$user = getUserByEmail($email);

if (!$user || !password_verify($password, $user['password_hash'])) {
    flash('error', 'Email ou mot de passe incorrect.');
    redirect('/var/www/html/upc_freelance/public/login.php');
}

if (!$user['is_active']) {
    flash('error', 'Votre compte est désactivé. Contactez le support.');
    redirect('/var/www/html/upc_freelance/public/login.php');
}

loginUser($user);
flash('success', 'Bienvenue, ' . $user['first_name'] . ' !');
redirect('/var/www/html/upc_freelance/app/dashboard.php');
