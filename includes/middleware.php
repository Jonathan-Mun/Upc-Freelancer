<?php
// ============================================================
// UPC FREELANCE — Middleware (sécurité globale)
// /var/www/html/upc_freelance/includes/middleware.php
// ============================================================

// Headers de sécurité HTTP
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Content-Security-Policy: default-src 'self'; script-src 'self' https://cdn.tailwindcss.com https://fonts.googleapis.com 'unsafe-inline'; style-src 'self' https://fonts.googleapis.com 'unsafe-inline'; font-src https://fonts.gstatic.com; img-src 'self' data: https:;");

// Rate limiting simple (session-based)
function rateLimit(string $key, int $maxAttempts = 5, int $windowSeconds = 300): bool {
    $now = time();
    if (!isset($_SESSION['rl'][$key])) {
        $_SESSION['rl'][$key] = ['count' => 0, 'start' => $now];
    }
    $rl = &$_SESSION['rl'][$key];
    if ($now - $rl['start'] > $windowSeconds) {
        $rl = ['count' => 0, 'start' => $now];
    }
    $rl['count']++;
    return $rl['count'] <= $maxAttempts;
}

// Bloquer les requêtes en dehors des méthodes attendues
function allowMethods(string ...$methods): void {
    if (!in_array($_SERVER['REQUEST_METHOD'], $methods, true)) {
        http_response_code(405);
        die('Méthode non autorisée.');
    }
}

// Vérifier origine AJAX
function isAjax(): bool {
    return isset($_SERVER['HTTP_X_REQUESTED_WITH'])
        && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}
