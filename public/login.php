<?php
// ============================================================
// UPC FREELANCE — Page connexion (publique)
// /var/www/html/upc_freelance/public/login.php
// ============================================================

require_once '/var/www/html/upc_freelance/includes/middleware.php';
require_once '/var/www/html/upc_freelance/includes/auth.php';
require_once '/var/www/html/upc_freelance/includes/functions.php';

if (isLoggedIn()) redirect('/var/www/html/upc_freelance/app/dashboard.php');

$pageTitle = 'Connexion — UPC Freelance';
require_once '/var/www/html/upc_freelance/includes/header.php';
?>

<div class="min-h-screen bg-surface-container-low flex items-center justify-center py-16 px-4">
    <div class="w-full max-w-md">

        <!-- Logo -->
        <div class="text-center mb-8">
            <div class="flex items-center justify-center gap-3 mb-4">
                <svg width="44" height="44" viewBox="0 0 38 38" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <rect width="38" height="38" rx="10" fill="#002045"/>
                    <path d="M10 12 L10 22 Q10 28 19 28 Q28 28 28 22 L28 12" stroke="#ffffff" stroke-width="2.5" stroke-linecap="round" fill="none"/>
                    <circle cx="19" cy="12" r="1.5" fill="#66affe"/>
                    <path d="M14 18 L24 18" stroke="#66affe" stroke-width="2" stroke-linecap="round"/>
                    <path d="M32 8 L34 6 M32 8 L34 10 M32 8 L28 8" stroke="#66affe" stroke-width="1.5" stroke-linecap="round"/>
                </svg>
                <span class="text-2xl font-bold text-primary">UPC Freelance</span>
            </div>
            <h1 class="text-h3 font-h3 text-primary">Bon retour !</h1>
            <p class="text-on-surface-variant text-sm mt-1">Connectez-vous à votre espace</p>
        </div>

        <!-- Card -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-8">
            <?php renderFlash(); ?>

            <form action="/upc_freelance/auth/login.php" method="POST" class="space-y-5" novalidate>
                <?= csrfField() ?>

                <div>
                    <label class="block text-sm font-medium text-primary mb-1.5" for="email">Adresse e-mail</label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 material-symbols-outlined text-slate-400 text-xl">mail</span>
                        <input type="email" id="email" name="email" required autocomplete="email"
                               value="<?= h($_GET['email'] ?? '') ?>"
                               placeholder="vous@exemple.com"
                               class="w-full pl-10 pr-4 py-3 rounded-xl border border-outline-variant focus:border-secondary focus:ring-2 focus:ring-secondary/20 outline-none text-sm transition-all"/>
                    </div>
                </div>

                <div>
                    <div class="flex justify-between items-center mb-1.5">
                        <label class="block text-sm font-medium text-primary" for="password">Mot de passe</label>
                        <a href="/upc_freelance/public/forgot-password.php" class="text-xs text-secondary hover:underline">Mot de passe oublié ?</a>
                    </div>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 material-symbols-outlined text-slate-400 text-xl">lock</span>
                        <input type="password" id="password" name="password" required autocomplete="current-password"
                               placeholder="••••••••"
                               class="w-full pl-10 pr-12 py-3 rounded-xl border border-outline-variant focus:border-secondary focus:ring-2 focus:ring-secondary/20 outline-none text-sm transition-all"/>
                        <button type="button" onclick="togglePassword()" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600">
                            <span id="eye-icon" class="material-symbols-outlined text-xl">visibility</span>
                        </button>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <input type="checkbox" id="remember" name="remember" class="rounded border-outline-variant text-secondary"/>
                    <label for="remember" class="text-sm text-on-surface-variant">Se souvenir de moi</label>
                </div>

                <button type="submit"
                        class="w-full bg-primary text-on-primary font-button text-button py-3.5 rounded-xl hover:opacity-90 transition-opacity active:scale-95 shadow-sm">
                    Se connecter
                </button>
            </form>

            <p class="text-center text-sm text-on-surface-variant mt-6">
                Pas encore de compte ?
                <a href="/upc_freelance/public/register.php" class="text-secondary font-medium hover:underline">S'inscrire gratuitement</a>
            </p>
        </div>

        <p class="text-center text-xs text-slate-400 mt-6">
            En vous connectant, vous acceptez nos
            <a href="/upc_freelance/public/terms.php" class="hover:underline">Conditions d'utilisation</a>.
        </p>
    </div>
</div>

<script>
function togglePassword() {
    const input = document.getElementById('password');
    const icon  = document.getElementById('eye-icon');
    if (input.type === 'password') {
        input.type = 'text';
        icon.textContent = 'visibility_off';
    } else {
        input.type = 'password';
        icon.textContent = 'visibility';
    }
}
</script>

<?php require_once '/var/www/html/upc_freelance/includes/footer.php'; ?>
