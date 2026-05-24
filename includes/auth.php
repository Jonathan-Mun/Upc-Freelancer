<?php
// ============================================================
// UPC FREELANCE — Authentification
// /var/www/html/upc_freelance/includes/auth.php
// ============================================================

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => true,   // HTTPS obligatoire en prod
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// ─── Vérifier si connecté ────────────────────────────────────
function isLoggedIn(): bool {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function isAdmin(): bool {
    return isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id']);
}

function currentUser(): ?array {
    if (!isLoggedIn()) return null;
    static $user = null;
    if ($user !== null) return $user;
    $stmt = getDB()->prepare('SELECT * FROM users WHERE id = ? AND is_active = 1');
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch() ?: null;
    return $user;
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        flash('error', 'Veuillez vous connecter pour accéder à cette page.');
        redirect('/var/www/html/upc_freelance/public/login.php');
    }
}

function requireRole(string $role): void {
    requireLogin();
    $user = currentUser();
    if (!$user || $user['role'] !== $role) {
        http_response_code(403);
        die('Accès interdit.');
    }
}

function requireAdmin(): void {
    if (!isAdmin()) {
        redirect('/var/www/html/upc_freelance/admin/login.php');
    }
}

// ─── Login / Logout ───────────────────────────────────────────
function loginUser(array $user): void {
    session_regenerate_id(true);
    $_SESSION['user_id']   = $user['id'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];

    // Mettre à jour last_login
    getDB()->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?')->execute([$user['id']]);
}

function logoutUser(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 3600, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

// ─── Trouver un utilisateur ───────────────────────────────────
function getUserByEmail(string $email): ?array {
    $stmt = getDB()->prepare('SELECT * FROM users WHERE email = ? AND is_active = 1');
    $stmt->execute([$email]);
    return $stmt->fetch() ?: null;
}

function getUserById(int $id): ?array {
    $stmt = getDB()->prepare('SELECT * FROM users WHERE id = ? AND is_active = 1');
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

// ─── Register ─────────────────────────────────────────────────
function registerUser(string $firstName, string $lastName, string $email, string $password, string $role, string $university = ''): int|false {
    $pdo = getDB();

    // Vérifier email unique
    $check = $pdo->prepare('SELECT id FROM users WHERE email = ?');
    $check->execute([$email]);
    if ($check->fetch()) return false;

    $uuid = generateUUID();
    $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

    $pdo->prepare('
        INSERT INTO users (uuid, role, first_name, last_name, email, password_hash, university)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ')->execute([$uuid, $role, $firstName, $lastName, $email, $hash, $university]);

    $userId = (int) $pdo->lastInsertId();

    // Créer profil selon rôle
    if ($role === 'freelancer') {
        $pdo->prepare('INSERT INTO freelancer_profiles (user_id) VALUES (?)')->execute([$userId]);
    } else {
        $pdo->prepare('INSERT INTO client_profiles (user_id) VALUES (?)')->execute([$userId]);
    }

    // Créer wallet
    $pdo->prepare('INSERT INTO wallets (user_id) VALUES (?)')->execute([$userId]);

    return $userId;
}
