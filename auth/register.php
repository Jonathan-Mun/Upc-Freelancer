<?php
// ============================================================
// UPC FREELANCE — Backend Register
// /var/www/html/upc_freelance/auth/register.php
// ============================================================

require_once '/var/www/html/upc_freelance/includes/middleware.php';
require_once '/var/www/html/upc_freelance/includes/auth.php';
require_once '/var/www/html/upc_freelance/includes/functions.php';

allowMethods('POST');
verifyCsrf();

if (!rateLimit('register', 3, 600)) {
    flash('error', 'Trop de tentatives. Réessayez dans 10 minutes.');
    redirect('/var/www/html/upc_freelance/public/register.php');
}

$firstName = sanitize($_POST['first_name'] ?? '');
$lastName  = sanitize($_POST['last_name']  ?? '');
$email     = sanitize($_POST['email']      ?? '');
$university= sanitize($_POST['university'] ?? '');
$password  = $_POST['password']         ?? '';
$passwordC = $_POST['password_confirm'] ?? '';
$role      = in_array($_POST['role'] ?? '', ['client','freelancer']) ? $_POST['role'] : 'freelancer';
$terms     = isset($_POST['terms']);

$errors = [];

if (empty($firstName))          $errors[] = 'Le prénom est requis.';
if (empty($lastName))           $errors[] = 'Le nom est requis.';
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email invalide.';
if (strlen($password) < 8)     $errors[] = 'Le mot de passe doit contenir au moins 8 caractères.';
if ($password !== $passwordC)  $errors[] = 'Les mots de passe ne correspondent pas.';
if (!$terms)                   $errors[] = 'Vous devez accepter les conditions d\'utilisation.';

if (!empty($errors)) {
    flash('error', implode(' ', $errors));
    redirect('/var/www/html/upc_freelance/public/register.php?role=' . $role);
}

$userId = registerUser($firstName, $lastName, $email, $password, $role, $university);

if ($userId === false) {
    flash('error', 'Cet email est déjà utilisé. Essayez de vous connecter.');
    redirect('/var/www/html/upc_freelance/public/register.php?role=' . $role);
}

// Auto-login après inscription
$user = getUserById($userId);
loginUser($user);

sendNotification($userId, 'welcome', 'Bienvenue sur UPC Freelance !',
    'Votre compte a été créé avec succès. Complétez votre profil pour commencer.',
    '/upc_freelance/app/profile/edit.php');

flash('success', 'Compte créé avec succès ! Bienvenue ' . $firstName . ' 🎉');
redirect('/var/www/html/upc_freelance/app/profile/edit.php');
