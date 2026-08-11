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
        'secure'   => false,  // true en HTTPS production
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

    $pdo  = getDB();
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ? AND is_active = 1');
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch() ?: null;
    return $user;
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        flash('error', 'Veuillez vous connecter pour accéder à cette page.');
        redirect('/upc_freelance/public/login.php');
    }
}

// Accepte un rôle unique ('client') ou plusieurs rôles ('client','freelancer')
function requireRole(string ...$roles): void {
    requireLogin();
    $user = currentUser();
    if (!$user || !in_array($user['role'], $roles, true)) {
        http_response_code(403);
        die('Accès interdit.');
    }
}

function requireAdmin(): void {
    if (!isAdmin()) {
        redirect('/upc_freelance/admin/login.php');
    }
}

// ─── Login / Logout ───────────────────────────────────────────
function loginUser(array $user): void {
    session_regenerate_id(true);
    $_SESSION['user_id']   = $user['id'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];

    getDB()->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?')
           ->execute([$user['id']]);
}

function logoutUser(): void {
    if (isset($_SESSION['user_id'])) {
        getDB()->prepare('UPDATE users SET remember_token = NULL WHERE id = ?')
               ->execute([$_SESSION['user_id']]);
    }
    setcookie('remember_me', '', time() - 3600, '/');

    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 3600,
            $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

// ─── Remember Me ────────────────────────────
function createRememberToken(int $userId): void {
    $token = bin2hex(random_bytes(32));

    getDB()->prepare('UPDATE users SET remember_token = ? WHERE id = ?')
           ->execute([$token, $userId]);

    setcookie('remember_me', $userId . ':' . $token, [
        'expires'  => time() + 60 * 60 * 24 * 30, // 30 jours
        'path'     => '/',
        'secure'   => false, // true en HTTPS production
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function attemptRememberLogin(): void {
    if (isLoggedIn() || empty($_COOKIE['remember_me'])) return;

    $parts = explode(':', $_COOKIE['remember_me'], 2);
    if (count($parts) !== 2) return;
    [$userId, $token] = $parts;

    $user = getUserById((int)$userId);
    if ($user && !empty($user['remember_token']) && hash_equals($user['remember_token'], $token)) {
        loginUser($user);
    } else {
        setcookie('remember_me', '', time() - 3600, '/');
    }
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
// users  : données communes (auth + identité de base)
// profils : données spécifiques au rôle
function registerUser(
    string $firstName,
    string $lastName,
    string $email,
    string $password,
    string $role,
    string $university  = '',
    string $fieldStudy  = '',
    string $companyName = '',
    string $website     = ''
): int|false {
    $pdo = getDB();

    // Vérifier unicité email
    $check = $pdo->prepare('SELECT id FROM users WHERE email = ?');
    $check->execute([$email]);
    if ($check->fetch()) return false;

    $uuid = generateUUID();
    $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

    // Insérer dans users (données communes uniquement)
    $pdo->prepare('
        INSERT INTO users (uuid, role, first_name, last_name, email, password_hash, phone)
        VALUES (?, ?, ?, ?, ?, ?, NULL)
    ')->execute([$uuid, $role, $firstName, $lastName, $email, $hash]);

    $userId = (int)$pdo->lastInsertId();

    // Créer le profil étendu selon le rôle
    if ($role === 'freelancer') {
        $pdo->prepare('
            INSERT INTO freelancer_profiles
                (user_id, university, field_of_study, availability)
            VALUES (?, ?, ?, "available")
        ')->execute([$userId, $university ?: null, $fieldStudy ?: null]);
    } else {
        $pdo->prepare('
            INSERT INTO client_profiles
                (user_id, company_name, website)
            VALUES (?, ?, ?)
        ')->execute([$userId, $companyName ?: null, $website ?: null]);
    }

    // Créer le wallet
    $pdo->prepare('INSERT INTO wallets (user_id, balance, locked) VALUES (?, 0.00, 0.00)')
        ->execute([$userId]);

    return $userId;
}

attemptRememberLogin();