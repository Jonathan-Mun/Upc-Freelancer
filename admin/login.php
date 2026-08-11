<?php
// ============================================================
// UPC FREELANCE — Admin Login
// ../../admin/login.php
// ============================================================

if (session_status() === PHP_SESSION_NONE) session_start();
if (!empty($_SESSION['admin_id'])) {
    header('Location: /upc_freelance/admin/dashboard.php');
    exit;
}

require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/middleware.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!rateLimit('admin_login', 5, 300)) {
        $error = 'Trop de tentatives. Réessayez dans 5 minutes.';
    } else {
        $email    = sanitize($_POST['email']    ?? '');
        $password = $_POST['password'] ?? '';

        $stmt = getDB()->prepare('SELECT * FROM admin_users WHERE email = ?');
        $stmt->execute([$email]);
        $admin = $stmt->fetch();

        if ($admin && password_verify($password, $admin['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['admin_id']   = $admin['id'];
            $_SESSION['admin_name'] = $admin['name'];
            header('Location: /upc_freelance/admin/dashboard.php');
            exit;
        }
        $error = 'Email ou mot de passe incorrect.';
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Admin — UPC Freelance</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
<style>* { font-family: 'Inter', sans-serif; } .material-symbols-outlined { vertical-align: middle; }</style>
</head>
<body class="min-h-screen bg-slate-100 flex items-center justify-center">
    <div class="w-full max-w-md px-4">

        <div class="text-center mb-8">
            <div class="flex items-center justify-center gap-3 mb-4">
                <svg width="44" height="44" viewBox="0 0 38 38" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <rect width="38" height="38" rx="10" fill="#002045"/>
                    <path d="M10 12 L10 22 Q10 28 19 28 Q28 28 28 22 L28 12" stroke="#fff" stroke-width="2.5" stroke-linecap="round" fill="none"/>
                    <circle cx="19" cy="12" r="1.5" fill="#66affe"/>
                    <path d="M14 18 L24 18" stroke="#66affe" stroke-width="2" stroke-linecap="round"/>
                </svg>
                <div class="text-left">
                    <p class="font-bold text-slate-900 text-lg">UPC Freelance</p>
                    <p class="text-xs text-red-600 font-semibold uppercase tracking-wide">Administration</p>
                </div>
            </div>
            <h1 class="text-2xl font-bold text-slate-900">Accès administrateur</h1>
            <p class="text-slate-500 text-sm mt-1">Zone réservée — Accès restreint</p>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-8">
            <?php if ($error): ?>
            <div class="mb-4 p-3 bg-red-50 border border-red-200 text-red-700 rounded-xl text-sm flex items-center gap-2">
                <span class="material-symbols-outlined text-base">error</span>
                <?= h($error) ?>
            </div>
            <?php endif; ?>

            <form method="POST" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">Email administrateur</label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 material-symbols-outlined text-slate-400 text-lg">mail</span>
                        <input type="email" name="email" required
                               value="<?= h($_POST['email'] ?? '') ?>"
                               placeholder="admin@upcfreelance.com"
                               class="w-full pl-10 pr-4 py-3 rounded-xl border border-slate-200 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 outline-none text-sm"/>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">Mot de passe</label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 material-symbols-outlined text-slate-400 text-lg">lock</span>
                        <input type="password" name="password" required placeholder="••••••••"
                               class="w-full pl-10 pr-4 py-3 rounded-xl border border-slate-200 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 outline-none text-sm"/>
                    </div>
                </div>
                <button type="submit"
                        class="w-full bg-slate-900 text-white py-3 rounded-xl text-sm font-semibold hover:bg-slate-800 transition-colors active:scale-95">
                    Se connecter
                </button>
            </form>
        </div>

        <p class="text-center text-xs text-slate-400 mt-6">
            🔒 Connexion sécurisée — UPC Freelance &copy; <?= date('Y') ?>
        </p>
    </div>
</body>
</html>
