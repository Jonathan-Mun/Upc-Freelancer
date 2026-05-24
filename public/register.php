<?php
// ============================================================
// UPC FREELANCE — Inscription
// /var/www/html/upc_freelance/public/register.php
// ============================================================

require_once '/var/www/html/upc_freelance/includes/middleware.php';
require_once '/var/www/html/upc_freelance/includes/auth.php';
require_once '/var/www/html/upc_freelance/includes/functions.php';

if (isLoggedIn()) redirect('/var/www/html/upc_freelance/app/dashboard.php');

$role      = in_array($_GET['role'] ?? '', ['client','freelancer']) ? $_GET['role'] : 'freelancer';
$pageTitle = 'Inscription — UPC Freelance';
require_once '/var/www/html/upc_freelance/includes/header.php';
?>

<div class="min-h-screen bg-surface-container-low flex items-center justify-center py-16 px-4">
    <div class="w-full max-w-lg">

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
            <h1 class="text-h3 font-h3 text-primary">Créer votre compte</h1>
            <p class="text-on-surface-variant text-sm mt-1">Rejoignez la communauté étudiante</p>
        </div>

        <!-- Role toggle -->
        <div class="flex bg-white border border-slate-200 rounded-xl p-1 mb-6 shadow-sm">
            <a href="?role=freelancer"
               class="flex-1 flex items-center justify-center gap-2 py-2.5 rounded-lg text-sm font-medium transition-all <?= $role==='freelancer' ? 'bg-primary text-white shadow-sm' : 'text-on-surface-variant hover:bg-slate-50' ?>">
                <span class="material-symbols-outlined text-base">person</span>
                Je suis Freelancer
            </a>
            <a href="?role=client"
               class="flex-1 flex items-center justify-center gap-2 py-2.5 rounded-lg text-sm font-medium transition-all <?= $role==='client' ? 'bg-primary text-white shadow-sm' : 'text-on-surface-variant hover:bg-slate-50' ?>">
                <span class="material-symbols-outlined text-base">business</span>
                Je suis Client
            </a>
        </div>

        <!-- Card -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-8">
            <?php renderFlash(); ?>

            <form action="/upc_freelance/auth/register.php" method="POST" class="space-y-4" novalidate>
                <?= csrfField() ?>
                <input type="hidden" name="role" value="<?= h($role) ?>"/>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-primary mb-1.5">Prénom</label>
                        <input type="text" name="first_name" required placeholder="Jean"
                               class="w-full px-4 py-3 rounded-xl border border-outline-variant focus:border-secondary focus:ring-2 focus:ring-secondary/20 outline-none text-sm transition-all"/>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-primary mb-1.5">Nom</label>
                        <input type="text" name="last_name" required placeholder="Dupont"
                               class="w-full px-4 py-3 rounded-xl border border-outline-variant focus:border-secondary focus:ring-2 focus:ring-secondary/20 outline-none text-sm transition-all"/>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-primary mb-1.5">Adresse e-mail</label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 material-symbols-outlined text-slate-400 text-xl">mail</span>
                        <input type="email" name="email" required placeholder="vous@exemple.com"
                               class="w-full pl-10 pr-4 py-3 rounded-xl border border-outline-variant focus:border-secondary focus:ring-2 focus:ring-secondary/20 outline-none text-sm transition-all"/>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-primary mb-1.5">Université / École</label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 material-symbols-outlined text-slate-400 text-xl">school</span>
                        <input type="text" name="university" placeholder="Ex: Université Paris-Saclay"
                               class="w-full pl-10 pr-4 py-3 rounded-xl border border-outline-variant focus:border-secondary focus:ring-2 focus:ring-secondary/20 outline-none text-sm transition-all"/>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-primary mb-1.5">Mot de passe</label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 material-symbols-outlined text-slate-400 text-xl">lock</span>
                        <input type="password" id="pw1" name="password" required placeholder="Minimum 8 caractères"
                               class="w-full pl-10 pr-12 py-3 rounded-xl border border-outline-variant focus:border-secondary focus:ring-2 focus:ring-secondary/20 outline-none text-sm transition-all"/>
                        <button type="button" onclick="togglePw('pw1','eye1')" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400">
                            <span id="eye1" class="material-symbols-outlined text-xl">visibility</span>
                        </button>
                    </div>
                    <!-- Force indicateur -->
                    <div class="mt-2 flex gap-1">
                        <div id="s1" class="h-1 flex-1 rounded-full bg-slate-200 transition-colors"></div>
                        <div id="s2" class="h-1 flex-1 rounded-full bg-slate-200 transition-colors"></div>
                        <div id="s3" class="h-1 flex-1 rounded-full bg-slate-200 transition-colors"></div>
                        <div id="s4" class="h-1 flex-1 rounded-full bg-slate-200 transition-colors"></div>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-primary mb-1.5">Confirmer le mot de passe</label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 material-symbols-outlined text-slate-400 text-xl">lock</span>
                        <input type="password" id="pw2" name="password_confirm" required placeholder="Répéter le mot de passe"
                               class="w-full pl-10 pr-12 py-3 rounded-xl border border-outline-variant focus:border-secondary focus:ring-2 focus:ring-secondary/20 outline-none text-sm transition-all"/>
                        <button type="button" onclick="togglePw('pw2','eye2')" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400">
                            <span id="eye2" class="material-symbols-outlined text-xl">visibility</span>
                        </button>
                    </div>
                </div>

                <div class="flex items-start gap-2 pt-1">
                    <input type="checkbox" id="terms" name="terms" required class="mt-1 rounded border-outline-variant text-secondary"/>
                    <label for="terms" class="text-sm text-on-surface-variant">
                        J'accepte les <a href="/upc_freelance/public/terms.php" class="text-secondary hover:underline">Conditions d'utilisation</a> et la Politique de confidentialité
                    </label>
                </div>

                <button type="submit"
                        class="w-full bg-primary text-on-primary font-button text-button py-3.5 rounded-xl hover:opacity-90 transition-opacity active:scale-95 shadow-sm mt-2">
                    Créer mon compte <?= $role === 'freelancer' ? 'Freelancer' : 'Client' ?>
                </button>
            </form>

            <p class="text-center text-sm text-on-surface-variant mt-6">
                Déjà un compte ?
                <a href="/upc_freelance/public/login.php" class="text-secondary font-medium hover:underline">Se connecter</a>
            </p>
        </div>
    </div>
</div>

<script>
function togglePw(inputId, iconId) {
    const inp = document.getElementById(inputId);
    const ico = document.getElementById(iconId);
    inp.type = inp.type === 'password' ? 'text' : 'password';
    ico.textContent = inp.type === 'password' ? 'visibility' : 'visibility_off';
}

document.getElementById('pw1').addEventListener('input', function() {
    const v = this.value, bars = [s1,s2,s3,s4];
    let score = 0;
    if (v.length >= 8)  score++;
    if (/[A-Z]/.test(v)) score++;
    if (/[0-9]/.test(v)) score++;
    if (/[^A-Za-z0-9]/.test(v)) score++;
    const colors = ['bg-red-400','bg-orange-400','bg-yellow-400','bg-green-500'];
    bars.forEach((b,i) => {
        b.className = 'h-1 flex-1 rounded-full transition-colors ' + (i < score ? colors[score-1] : 'bg-slate-200');
    });
});
</script>

<?php require_once '/var/www/html/upc_freelance/includes/footer.php'; ?>
