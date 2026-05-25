<?php
// ============================================================
// UPC FREELANCE — Backend Register
// /var/www/html/upc_freelance/auth/register.php
// ============================================================

require_once '../includes/middleware.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

allowMethods('POST');
verifyCsrf();

if (!rateLimit('register', 3, 600)) {
    flash('error', 'Trop de tentatives. Réessayez dans 10 minutes.');
    redirect('../public/register.php');
}

$role        = in_array($_POST['role'] ?? '', ['client','freelancer']) ? $_POST['role'] : 'freelancer';
$firstName   = sanitize($_POST['first_name']     ?? '');
$lastName    = sanitize($_POST['last_name']      ?? '');
$email       = sanitize($_POST['email']          ?? '');
$password    = $_POST['password']                ?? '';
$passwordC   = $_POST['password_confirm']        ?? '';
$terms       = isset($_POST['terms']);

// Champs spécifiques au rôle
$university  = $role === 'freelancer' ? sanitize($_POST['university']     ?? '') : '';
$fieldStudy  = $role === 'freelancer' ? sanitize($_POST['field_of_study'] ?? '') : '';
$companyName = $role === 'client'     ? sanitize($_POST['company_name']   ?? '') : '';
$website     = $role === 'client'     ? sanitize($_POST['website']        ?? '') : '';

// ── Validation ────────────────────────────────────────────────
$errors = [];
if (empty($firstName))                             $errors[] = 'Le prénom est requis.';
if (empty($lastName))                              $errors[] = 'Le nom est requis.';
if (!filter_var($email, FILTER_VALIDATE_EMAIL))    $errors[] = 'Adresse email invalide.';
if (strlen($password) < 8)                         $errors[] = 'Le mot de passe doit contenir au moins 8 caractères.';
if ($password !== $passwordC)                      $errors[] = 'Les mots de passe ne correspondent pas.';
if (!$terms)                                       $errors[] = "Vous devez accepter les conditions d'utilisation.";
if ($role === 'freelancer' && empty($university))  $errors[] = "L'université est requise pour un compte freelancer.";

if (!empty($errors)) {
    flash('error', implode(' ', $errors));
    redirect('../public/register.php?role=' . $role);
}

// ── Création ──────────────────────────────────────────────────
$userId = registerUser(
    $firstName, $lastName, $email, $password, $role,
    $university, $fieldStudy, $companyName, $website
);

if ($userId === false) {
    flash('error', 'Cet email est déjà utilisé. Essayez de vous connecter.');
    redirect('../public/register.php?role=' . $role);
}

// Auto-login
$user = getUserById($userId);
loginUser($user);

sendNotification(
    $userId,
    'welcome',
    'Bienvenue sur UPC Freelance !',
    'Votre compte a été créé avec succès. Complétez votre profil pour commencer.',
    '../app/profile/edit.php'
);

flash('success', 'Compte créé avec succès ! Bienvenue ' . $firstName . ' !');
redirect('../app/profile/edit.php');