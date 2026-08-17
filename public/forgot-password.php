<?php
// ============================================================
// UPC FREELANCE — Mot de passe oublié
// ============================================================

require_once '../includes/middleware.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';

if (isLoggedIn()) redirect('../app/dashboard.php');

$sent = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    if (!rateLimit('forgot_pw', 3, 600)) {
        flash('error', 'Trop de tentatives. Réessayez dans 10 minutes.');
    } else {
        $email = sanitize($_POST['email'] ?? '');
        $user  = getUserByEmail($email);

        if ($user) {
            $token   = generateToken(64);
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
            getDB()->prepare('UPDATE users SET reset_token = ?, reset_token_expires_at = ? WHERE id = ?')
                ->execute([$token, $expires, $user['id']]);

            // En production : envoyer un vrai email (PHPMailer, SendGrid, etc.)
            // Pour le dev : afficher le lien directement
            $resetLink = 'https://votre-domaine.com/upc_freelance/auth/reset-password.php?token=' . $token;

            // mail($email, 'Réinitialisation de mot de passe - UPC Freelance',
            //     "Cliquez sur ce lien pour réinitialiser votre mot de passe:\n$resetLink\n\nCe lien expire dans 1 heure.",
            //     'From: noreply@upcfreelance.com');

            // Dev only: log le lien
            error_log('[UPC] Reset link for ' . $email . ': ' . $resetLink);
        }

        // Toujours afficher le même message (sécurité)
        $sent = true;
    }
}

$pageTitle = 'Mot de passe oublié — UPC Freelance';
require_once '../includes/header.php';
?>

<div class="min-h-screen bg-surface-container-low flex items-center justify-center py-16 px-4">
    <div class="w-full max-w-md">

        <div class="text-center mb-8">
            <div class="flex items-center justify-center gap-3 mb-4">
                <svg width="44" height="44" viewBox="0 0 38 38" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <rect width="38" height="38" rx="10" fill="#002045"/>
                    <path d="M10 12 L10 22 Q10 28 19 28 Q28 28 28 22 L28 12" stroke="#ffffff" stroke-width="2.5" stroke-linecap="round" fill="none"/>
                    <circle cx="19" cy="12" r="1.5" fill="#66affe"/>
                    <path d="M14 18 L24 18" stroke="#66affe" stroke-width="2" stroke-linecap="round"/>
                </svg>
                <span class="text-2xl font-bold text-primary">UPC Freelance</span>
            </div>
            <h1 class="text-h3 font-h3 text-primary">Mot de passe oublié ?</h1>
            <p class="text-on-surface-variant text-sm mt-1">Entrez votre email pour recevoir un lien de réinitialisation</p>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-8">
            <?php if ($sent): ?>
            <div class="text-center py-4">
                <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <span class="material-symbols-outlined text-green-500 text-3xl">mark_email_read</span>
                </div>
                <h3 class="font-semibold text-primary mb-2">Email envoyé !</h3>
                <p class="text-sm text-on-surface-variant">
                    Si un compte existe avec cette adresse, vous recevrez un email avec les instructions de réinitialisation dans les prochaines minutes.
                </p>
                <a href="/upc_freelance/public/login.php" class="mt-6 inline-block text-sm text-secondary hover:underline">
                    ← Retour à la connexion
                </a>
            </div>
            <?php else: ?>
            <?php renderFlash(); ?>
            <form method="POST" class="space-y-5">
                <?= csrfField() ?>
                <div>
                    <label class="block text-sm font-medium text-primary mb-1.5">Adresse e-mail</label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 material-symbols-outlined text-slate-400 text-xl">mail</span>
                        <input type="email" name="email" required autocomplete="email"
                               placeholder="vous@exemple.com"
                               class="w-full pl-10 pr-4 py-3 rounded-xl border border-outline-variant focus:border-secondary focus:ring-2 focus:ring-secondary/20 outline-none text-sm transition-all"/>
                    </div>
                </div>
                <button type="submit"
                        class="w-full bg-primary text-on-primary font-button text-button py-3.5 rounded-xl hover:opacity-90 transition-opacity active:scale-95 shadow-sm">
                    Envoyer le lien de réinitialisation
                </button>
            </form>
            <p class="text-center text-sm text-on-surface-variant mt-6">
                <a href="/upc_freelance/public/login.php" class="text-secondary hover:underline">← Retour à la connexion</a>
            </p>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
