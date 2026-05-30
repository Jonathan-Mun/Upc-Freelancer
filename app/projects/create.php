<?php
// ============================================================
// UPC FREELANCE — Créer un projet
// ../../app/projects/create.php
// ============================================================

require_once '../../includes/middleware.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../includes/db.php';

requireRole('client', 'freelancer');

$pdo    = getDB();
$user   = currentUser();
try {
    $wallet        = getUserWallet($user['id']);
    $walletBalance = (float)($wallet['balance'] ?? 0);
} catch (\Throwable $e) {
    $walletBalance = 0;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $title      = sanitize($_POST['title']       ?? '');
    $desc       = sanitize($_POST['description'] ?? '');
    $categoryId = (int)($_POST['category_id']    ?? 0);
    $budgetMin  = (float)($_POST['budget_min']   ?? 0);
    $budgetMax  = (float)($_POST['budget_max']   ?? 0);
    $deadline   = sanitize($_POST['deadline']    ?? '');
    $skills     = array_filter(array_map('trim', explode(',', $_POST['skills'] ?? '')));
    $visibility = in_array($_POST['visibility'] ?? 'public', ['public','private']) ? $_POST['visibility'] : 'public';

    $errors = [];
    if (empty($title))      $errors[] = 'Le titre est requis.';
    if (empty($desc))       $errors[] = 'La description est requise.';
    if ($budgetMin < 0)     $errors[] = 'Le budget minimum invalide.';
    if ($budgetMax < $budgetMin && $budgetMax > 0) $errors[] = 'Le budget max doit être supérieur au min.';
    if ($budgetMin > 0 && $budgetMin > $walletBalance)
        $errors[] = 'Le budget minimum ne peut pas dépasser votre solde wallet (' . money($walletBalance) . ').';
    if ($budgetMin > 0 && $budgetMin > $walletBalance)
        $errors[] = 'Le budget minimum ne peut pas dépasser votre solde wallet (' . money($walletBalance) . ').';
    if ($budgetMax > 0 && $budgetMax > $walletBalance)
        $errors[] = 'Le budget maximum ne peut pas dépasser votre solde wallet (' . money($walletBalance) . ').';
    if ($budgetMin > 0 && $budgetMax > 0 && $budgetMin > $budgetMax)
        $errors[] = 'Le budget minimum ne peut pas être supérieur au budget maximum.';

    if (empty($errors)) {
        $uuid = generateUUID();

        $pdo->prepare('
            INSERT INTO projects (uuid, client_id, category_id, title, description, budget_min, budget_max, deadline, skills_needed, visibility)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ')->execute([
            $uuid,
            $user['id'],
            $categoryId ?: null,
            $title,
            $desc,
            $budgetMin ?: null,
            $budgetMax ?: null,
            $deadline  ?: null,
            $skills    ? json_encode($skills) : null,
            $visibility,
        ]);

        $projectId = (int)$pdo->lastInsertId();

        // Projet privé : notifier le client avec le lien de partage
        if ($visibility === 'private') {
            $shareLink = '/upc_freelance/app/projects/details.php?id=' . $projectId . '&token=' . $uuid;
            sendNotification(
                $user['id'],
                'contract_created',
                'Projet privé créé',
                'Partagez ce lien aux freelancers de votre choix : ' . $shareLink,
                $shareLink
            );
        }

        flash('success', 'Projet publié avec succès !');
        redirect('../../app/projects/details.php?id=' . $projectId);
    } else {
        flash('error', implode(' ', $errors));
    }
}

$categories = getDB()->query('SELECT * FROM categories WHERE is_active = 1 ORDER BY name')->fetchAll();
$pageTitle  = 'Créer un projet — UPC Freelance';
$appLayout  = true;
require_once '../../includes/header.php';
?>

<!-- En-tête -->
<div class="mb-8">
    <a href="/upc_freelance/app/projects/my-projects.php" class="inline-flex items-center gap-1 text-sm text-secondary hover:underline mb-3">
        <span class="material-symbols-outlined text-base">arrow_back</span> Mes projets
    </a>
    <h1 class="text-2xl font-bold text-primary">Publier un nouveau projet</h1>
    <p class="text-on-surface-variant text-sm mt-1">Décrivez votre besoin pour attirer les meilleurs freelancers.</p>
</div>

<div class="max-w-3xl">
    <?php renderFlash(); ?>

    <!-- ══ CHOIX DU MODE ══ -->
    <div id="mode-chooser" class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-8">
        <button type="button" onclick="chooseMode('ai')"
                class="mode-btn flex flex-col items-center gap-3 p-6 bg-white border-2 border-slate-200
                       rounded-2xl hover:border-secondary hover:shadow-lg transition-all group text-left">
            <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-blue-500 to-indigo-600
                        flex items-center justify-center shadow-md group-hover:scale-105 transition-transform">
                <span class="material-symbols-outlined text-white text-3xl"
                      style="font-variation-settings:'FILL' 1">auto_awesome</span>
            </div>
            <div>
                <p class="font-bold text-primary text-base">Créer avec l'IA ✨</p>
                <p class="text-xs text-on-surface-variant mt-1 leading-relaxed">
                    Décrivez votre besoin en quelques mots, l'IA remplit tout automatiquement.
                    Vous n'avez plus qu'à vérifier et publier.
                </p>
            </div>
            <span class="text-[10px] font-bold uppercase tracking-wider bg-blue-50 text-blue-600
                         px-2.5 py-1 rounded-full">Recommandé</span>
        </button>

        <button type="button" onclick="chooseMode('manual')"
                class="mode-btn flex flex-col items-center gap-3 p-6 bg-white border-2 border-slate-200
                       rounded-2xl hover:border-slate-400 hover:shadow-lg transition-all group text-left">
            <div class="w-14 h-14 rounded-2xl bg-slate-100 flex items-center justify-center
                        shadow-md group-hover:scale-105 transition-transform">
                <span class="material-symbols-outlined text-slate-500 text-3xl">edit_note</span>
            </div>
            <div>
                <p class="font-bold text-primary text-base">Remplir manuellement</p>
                <p class="text-xs text-on-surface-variant mt-1 leading-relaxed">
                    Vous avez déjà toutes les infos ? Remplissez le formulaire vous-même,
                    champ par champ.
                </p>
            </div>
            <span class="text-[10px] font-bold uppercase tracking-wider bg-slate-100 text-slate-500
                         px-2.5 py-1 rounded-full">Contrôle total</span>
        </button>
    </div>

    <!-- ══ PANNEAU IA ══ -->
    <div id="ai-panel" class="hidden mb-6">
        <div class="bg-gradient-to-br from-blue-50 to-indigo-50 border border-blue-200 rounded-2xl p-6">
            <div class="flex items-center gap-3 mb-4">
                <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-blue-500 to-indigo-600
                            flex items-center justify-center flex-shrink-0">
                    <span class="material-symbols-outlined text-white text-lg"
                          style="font-variation-settings:'FILL' 1">auto_awesome</span>
                </div>
                <div>
                    <p class="font-bold text-primary text-sm">Décrivez votre projet à l'IA</p>
                    <p class="text-xs text-on-surface-variant">Quelques lignes suffisent — plus c'est précis, mieux c'est.</p>
                </div>
            </div>

            <textarea id="ai-brief" rows="4" placeholder="Ex : J'ai besoin d'un développeur pour créer un site web de vente de vêtements en ligne avec un système de paiement mobile money, un catalogue produits et un espace admin..."
                      class="w-full px-4 py-3 rounded-xl border border-blue-200 bg-white
                             focus:border-secondary focus:ring-2 focus:ring-secondary/20
                             outline-none text-sm resize-none transition-all"></textarea>

            <div class="flex items-center gap-3 mt-3">
                <button type="button" onclick="generateWithAI()"
                        id="ai-btn"
                        class="inline-flex items-center gap-2 bg-gradient-to-r from-blue-600 to-indigo-600
                               text-white px-5 py-2.5 rounded-xl font-semibold text-sm
                               hover:opacity-90 transition-all active:scale-95 shadow-sm">
                    <span class="material-symbols-outlined text-base"
                          style="font-variation-settings:'FILL' 1">auto_awesome</span>
                    <span id="ai-btn-text">Générer le projet</span>
                </button>
                <button type="button" onclick="chooseMode('chooser')"
                        class="text-xs text-slate-400 hover:text-slate-600 transition-colors">
                    ← Retour
                </button>
            </div>

            <!-- Loader -->
            <div id="ai-loading" class="hidden mt-4 flex items-center gap-3 text-sm text-secondary">
                <svg class="animate-spin w-4 h-4 text-secondary" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                </svg>
                L'IA génère votre projet, patientez quelques secondes...
            </div>

            <!-- Erreur IA -->
            <div id="ai-error" class="hidden mt-3 p-3 bg-red-50 border border-red-200 rounded-xl
                                       text-xs text-red-600 flex items-center gap-2">
                <span class="material-symbols-outlined text-base">error</span>
                <span id="ai-error-text"></span>
            </div>

            <!-- Suggestion de régénération -->
            <div id="ai-regen" class="hidden mt-4 p-3 bg-white border border-blue-200 rounded-xl">
                <p class="text-xs font-semibold text-primary mb-2 flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-sm text-green-500"
                          style="font-variation-settings:'FILL' 1">check_circle</span>
                    Projet généré ! Vérifiez et ajustez si besoin.
                </p>
                <div class="flex gap-2 flex-wrap">
                    <button type="button" onclick="generateWithAI()"
                            class="inline-flex items-center gap-1.5 text-xs font-semibold
                                   text-secondary border border-secondary/30 bg-blue-50
                                   px-3 py-1.5 rounded-lg hover:bg-blue-100 transition-colors">
                        <span class="material-symbols-outlined text-sm">refresh</span>
                        Regénérer
                    </button>
                    <button type="button" onclick="chooseMode('chooser')"
                            class="inline-flex items-center gap-1.5 text-xs font-semibold
                                   text-slate-500 border border-slate-200
                                   px-3 py-1.5 rounded-lg hover:bg-slate-50 transition-colors">
                        <span class="material-symbols-outlined text-sm">edit</span>
                        Changer de description
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- ══ FORMULAIRE PRINCIPAL (caché au départ) ══ -->
    <div id="main-form" class="hidden">
    <!-- Badge mode actif -->
    <div id="mode-badge" class="mb-4 hidden">
        <span class="inline-flex items-center gap-1.5 text-xs font-semibold px-3 py-1.5 rounded-full
                     bg-blue-50 text-blue-700 border border-blue-200">
            <span class="material-symbols-outlined text-sm"
                  style="font-variation-settings:'FILL' 1">auto_awesome</span>
            Généré par l'IA — vérifiez et ajustez avant de publier
        </span>
    </div>

    <form method="POST" enctype="multipart/form-data" class="space-y-6" novalidate>
        <?= csrfField() ?>

        <!-- Informations générales -->
        <div class="bg-white rounded-2xl border border-slate-100 p-6 custom-shadow-low">
            <h2 class="font-semibold text-primary mb-5 flex items-center gap-2">
                <span class="material-symbols-outlined text-secondary">info</span>
                Informations générales
            </h2>
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-primary mb-1.5">Titre du projet <span class="text-red-500">*</span></label>
                    <input type="text" name="title" required maxlength="200"
                           placeholder="Ex: Création d'un site e-commerce pour boutique mode"
                           value="<?= h($_POST['title'] ?? '') ?>"
                           class="w-full px-4 py-3 rounded-xl border border-outline-variant focus:border-secondary focus:ring-2 focus:ring-secondary/20 outline-none text-sm transition-all"/>
                    <p class="text-xs text-slate-400 mt-1">Soyez précis et accrocheur (max. 200 caractères)</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-primary mb-1.5">Description détaillée <span class="text-red-500">*</span></label>
                    <textarea name="description" id="proj-description" rows="6" required
                              placeholder="Décrivez votre projet en détail : objectifs, livrables attendus, contraintes, contexte..."
                              class="w-full px-4 py-3 rounded-xl border border-outline-variant focus:border-secondary focus:ring-2 focus:ring-secondary/20 outline-none text-sm transition-all resize-y"><?= h($_POST['description'] ?? '') ?></textarea>
                </div>

                <div>
                    <label class="block text-sm font-medium text-primary mb-1.5">Catégorie</label>
                    <select name="category_id" class="w-full px-4 py-3 rounded-xl border border-outline-variant focus:border-secondary outline-none text-sm">
                        <option value="">-- Sélectionner une catégorie --</option>
                        <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= ($_POST['category_id'] ?? '') == $cat['id'] ? 'selected' : '' ?>>
                            <?= h($cat['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-primary mb-1.5">Compétences requises</label>
                    <input type="text" name="skills" id="skills-input"
                           value="<?= h(implode(', ', (array)($_POST['skills_arr'] ?? []))) ?>"
                           placeholder="Ex: PHP, React, Figma (séparées par des virgules)"
                           class="w-full px-4 py-3 rounded-xl border border-outline-variant focus:border-secondary focus:ring-2 focus:ring-secondary/20 outline-none text-sm transition-all"/>
                    <div id="skills-tags" class="flex flex-wrap gap-2 mt-2"></div>
                </div>
            </div>
        </div>

        <!-- Budget & délai -->
        <div class="bg-white rounded-2xl border border-slate-100 p-6 custom-shadow-low">
            <h2 class="font-semibold text-primary mb-5 flex items-center gap-2">
                <span class="material-symbols-outlined text-secondary">payments</span>
                Budget & Délai
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-primary mb-1.5">
                        Budget min (XOF)
                        <span class="ml-1 text-xs font-normal text-slate-400">— solde : <strong class="text-secondary"><?= money($walletBalance) ?></strong></span>
                    </label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-xs text-slate-400 font-medium">CFA</span>
                        <input type="number" name="budget_min" id="budget_min"
                               min="0" step="100" max="<?= $walletBalance ?>"
                               value="<?= h($_POST['budget_min'] ?? '') ?>"
                               placeholder="0"
                               class="w-full pl-12 pr-4 py-3 rounded-xl border border-outline-variant focus:border-secondary focus:ring-2 focus:ring-secondary/20 outline-none text-sm transition-all"/>
                    </div>
                    <p id="budget-min-warn" class="hidden text-xs text-red-500 mt-1 flex items-center gap-1">
                        <span class="material-symbols-outlined text-sm">warning</span>
                        <span id="min-warn-text">Dépasse votre solde (<?= money($walletBalance) ?>)</span>
                    </p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-primary mb-1.5">
                        Budget max (XOF)
                        <span class="ml-1 text-xs font-normal text-slate-400">— solde : <strong class="text-secondary"><?= money($walletBalance) ?></strong></span>
                    </label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-xs text-slate-400 font-medium">CFA</span>
                        <input type="number" name="budget_max" id="budget_max"
                               min="0" step="100" max="<?= $walletBalance ?>"
                               value="<?= h($_POST['budget_max'] ?? '') ?>"
                               placeholder="0"
                               class="w-full pl-12 pr-4 py-3 rounded-xl border border-outline-variant focus:border-secondary focus:ring-2 focus:ring-secondary/20 outline-none text-sm transition-all"/>
                    </div>
                    <p id="budget-warn" class="hidden text-xs text-red-500 mt-1 flex items-center gap-1">
                        <span class="material-symbols-outlined text-sm">warning</span>
                        Dépasse votre solde (<?= money($walletBalance) ?>)
                    </p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-primary mb-1.5">Date limite</label>
                    <input type="date" name="deadline" min="<?= date('Y-m-d') ?>"
                           value="<?= h($_POST['deadline'] ?? '') ?>"
                           class="w-full px-4 py-3 rounded-xl border border-outline-variant focus:border-secondary focus:ring-2 focus:ring-secondary/20 outline-none text-sm transition-all"/>
                </div>
            </div>
        </div>

        <!-- Visibilité -->
        <div class="bg-white rounded-2xl border border-slate-100 p-6 custom-shadow-low">
            <h2 class="font-semibold text-primary mb-5 flex items-center gap-2">
                <span class="material-symbols-outlined text-secondary">visibility</span>
                Visibilité
            </h2>
            <div class="grid grid-cols-2 gap-3">
                <label class="relative flex items-start gap-3 p-4 rounded-xl border-2 cursor-pointer transition-all <?= ($_POST['visibility'] ?? 'public') === 'public' ? 'border-secondary bg-secondary/5' : 'border-slate-200 hover:border-secondary/40' ?>">
                    <input type="radio" name="visibility" value="public" class="mt-1" <?= ($_POST['visibility'] ?? 'public') === 'public' ? 'checked' : '' ?> onchange="this.closest('.grid').querySelectorAll('label').forEach(l=>l.classList.remove('border-secondary','bg-secondary/5')); this.closest('label').classList.add('border-secondary','bg-secondary/5')"/>
                    <div>
                        <span class="material-symbols-outlined text-secondary block mb-1">public</span>
                        <p class="text-sm font-semibold text-primary">Public</p>
                        <p class="text-xs text-on-surface-variant">Visible par tous les freelancers</p>
                    </div>
                </label>
                <label class="relative flex items-start gap-3 p-4 rounded-xl border-2 cursor-pointer transition-all <?= ($_POST['visibility'] ?? '') === 'private' ? 'border-secondary bg-secondary/5' : 'border-slate-200 hover:border-secondary/40' ?>">
                    <input type="radio" name="visibility" value="private" class="mt-1" <?= ($_POST['visibility'] ?? '') === 'private' ? 'checked' : '' ?> onchange="this.closest('.grid').querySelectorAll('label').forEach(l=>l.classList.remove('border-secondary','bg-secondary/5')); this.closest('label').classList.add('border-secondary','bg-secondary/5')"/>
                    <div>
                        <span class="material-symbols-outlined text-slate-400 block mb-1">lock</span>
                        <p class="text-sm font-semibold text-primary">Privé</p>
                        <p class="text-xs text-on-surface-variant">Seulement sur invitation</p>
                    </div>
                </label>
            </div>
        </div>

        <!-- Lien de partage (projet privé) -->
        <div id="private-link-info" class="<?= ($_POST['visibility'] ?? 'public') === 'private' ? '' : 'hidden' ?> bg-blue-50 border border-blue-200 rounded-2xl p-5">
            <div class="flex items-start gap-3">
                <span class="material-symbols-outlined text-secondary mt-0.5" style="font-variation-settings:'FILL' 1">lock</span>
                <div>
                    <p class="text-sm font-semibold text-primary mb-1">Projet privé — lien de partage</p>
                    <p class="text-xs text-on-surface-variant leading-relaxed">
                        Une fois le projet créé, un lien unique sera généré et vous sera envoyé par notification.
                        Partagez-le uniquement aux freelancers que vous souhaitez inviter.
                    </p>
                    <div class="flex items-center gap-2 mt-3 p-2.5 bg-white rounded-lg border border-blue-200">
                        <span class="material-symbols-outlined text-base text-slate-400">link</span>
                        <span class="text-xs text-slate-500 font-mono">/app/projects/details.php?id=...&token=<uuid></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Boutons -->
        <div class="flex gap-3">
            <button type="submit"
                    class="flex-1 bg-primary text-white font-button text-button py-3.5 rounded-xl hover:opacity-90 transition-opacity active:scale-95 shadow-sm">
                <span class="material-symbols-outlined align-middle mr-1">publish</span>
                Publier le projet
            </button>
            <a href="/upc_freelance/app/projects/my-projects.php"
               class="px-6 py-3.5 rounded-xl border-2 border-slate-200 text-sm font-medium text-on-surface-variant hover:border-slate-300 transition-colors">
                Annuler
            </a>
        </div>
    </form>
</div>

<script>
// ── Tags compétences ─────────────────────────────────────
const skillInput = document.getElementById('skills-input');
const skillTags  = document.getElementById('skills-tags');
function renderTags() {
    const vals = skillInput.value.split(',').map(s => s.trim()).filter(Boolean);
    skillTags.innerHTML = vals.map(v =>
        `<span class="inline-flex items-center gap-1 bg-surface-container text-secondary text-xs px-2.5 py-1 rounded-full font-medium">${v}</span>`
    ).join('');
}
skillInput.addEventListener('input', renderTags);
renderTags();

// ── Validation budget wallet ──────────────────────────────
const budgetMax    = document.getElementById('budget_max');
const budgetMin    = document.getElementById('budget_min');
const budgetWarn   = document.getElementById('budget-warn');
const minWarn      = document.getElementById('budget-min-warn');
const minWarnText  = document.getElementById('min-warn-text');
const submitBtn    = document.querySelector('button[type="submit"]');
const walletLimit  = <?= $walletBalance ?>;

function checkBudget() {
    const maxVal = parseFloat(budgetMax.value) || 0;
    const minVal = parseFloat(budgetMin.value) || 0;

    const minOverWallet = minVal > 0 && minVal > walletLimit;
    const maxOverWallet = maxVal > 0 && maxVal > walletLimit;
    const minOverMax    = minVal > 0 && maxVal > 0 && minVal > maxVal;

    // Warn budget min
    if (minOverWallet) {
        minWarnText.textContent = 'Dépasse votre solde (<?= money($walletBalance) ?>)';
        minWarn.classList.remove('hidden');
        budgetMin.style.borderColor = '#ef4444';
    } else if (minOverMax) {
        minWarnText.textContent = 'Ne peut pas être supérieur au budget max';
        minWarn.classList.remove('hidden');
        budgetMin.style.borderColor = '#ef4444';
    } else {
        minWarn.classList.add('hidden');
        budgetMin.style.borderColor = '';
    }

    // Warn budget max
    budgetWarn.classList.toggle('hidden', !maxOverWallet);
    budgetMax.style.borderColor = maxOverWallet ? '#ef4444' : '';

    // Bloquer soumission
    const hasError = minOverWallet || maxOverWallet || minOverMax;
    submitBtn.disabled      = hasError;
    submitBtn.style.opacity = hasError ? '0.5' : '';
}
budgetMax.addEventListener('input', checkBudget);
budgetMin.addEventListener('input', checkBudget);

// ── Affichage conditionnel bloc projet privé ──────────────
const radios      = document.querySelectorAll('input[name="visibility"]');
const privatInfo  = document.getElementById('private-link-info');

radios.forEach(r => {
    r.addEventListener('change', () => {
        privatInfo.classList.toggle('hidden', r.value !== 'private' || !r.checked);
    });
});
</script>

    </form>
    </div><!-- /#main-form -->
</div><!-- /.max-w-3xl -->

<script>
// ── Navigation modes ─────────────────────────────────────
function chooseMode(mode) {
    const chooser  = document.getElementById('mode-chooser');
    const aiPanel  = document.getElementById('ai-panel');
    const mainForm = document.getElementById('main-form');
    const badge    = document.getElementById('mode-badge');

    chooser.classList.add('hidden');
    aiPanel.classList.add('hidden');
    mainForm.classList.add('hidden');

    if (mode === 'chooser') {
        chooser.classList.remove('hidden');
        document.getElementById('ai-regen').classList.add('hidden');
    } else if (mode === 'ai') {
        aiPanel.classList.remove('hidden');
    } else if (mode === 'manual') {
        mainForm.classList.remove('hidden');
        badge.classList.add('hidden');
    }
}

// ── Génération IA (via API PHP → Anthropic) ──────────────
async function generateWithAI() {
    const brief    = document.getElementById('ai-brief').value.trim();
    const btn      = document.getElementById('ai-btn');
    const btnText  = document.getElementById('ai-btn-text');
    const loading  = document.getElementById('ai-loading');
    const errorBox = document.getElementById('ai-error');
    const errorTxt = document.getElementById('ai-error-text');
    const regen    = document.getElementById('ai-regen');

    if (brief.length < 10) {
        errorTxt.textContent = 'Veuillez décrire votre projet en au moins 10 caractères.';
        errorBox.classList.remove('hidden');
        return;
    }

    errorBox.classList.add('hidden');
    regen.classList.add('hidden');
    loading.classList.remove('hidden');
    btn.disabled = true;
    btnText.textContent = 'Génération...';

    try {
        const res  = await fetch('/upc_freelance/app/projects/api-ai-generate.php', {
            method:      'POST',
            headers:     { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body:        JSON.stringify({ brief })
        });
        const data = await res.json();

        if (data.error) {
            errorTxt.textContent = data.error;
            errorBox.classList.remove('hidden');
            return;
        }

        fillForm(data.project);
        // main-form déjà rendu visible dans fillForm()
        document.getElementById('mode-badge').classList.remove('hidden');
        regen.classList.remove('hidden');
        setTimeout(() => document.getElementById('main-form').scrollIntoView({ behavior: 'smooth', block: 'start' }), 100);

    } catch (e) {
        errorTxt.textContent = 'Erreur réseau : ' + e.message;
        errorBox.classList.remove('hidden');
    } finally {
        loading.classList.add('hidden');
        btn.disabled = false;
        btnText.textContent = 'Regénérer';
    }
}

function fillForm(p) {
    // Rendre le formulaire visible AVANT de remplir les champs
    // pour que querySelector trouve bien les éléments du DOM
    const mainForm = document.getElementById('main-form');
    mainForm.classList.remove('hidden');

    // Petit délai pour laisser le DOM se mettre à jour
    setTimeout(() => {
        // Titre
        const titleEl = document.querySelector('[name="title"]');
        if (titleEl && p.title) titleEl.value = p.title;

        // Description — convertir \n en vrais sauts de ligne
        const descEl = document.getElementById('proj-description');
        if (descEl) {
            descEl.value = p.description;



        }

        // Compétences
        const skillEl = document.getElementById('skills-input');
        if (skillEl && p.skills) {
            skillEl.value = Array.isArray(p.skills) ? p.skills.join(', ') : p.skills;
            renderTags();
            // Déclencher l'event pour mettre à jour les tags visuels
            skillEl.dispatchEvent(new Event('input'));
        }

        // Budget min
        const bMinEl = document.getElementById('budget_min');
        if (bMinEl && p.budget_min) {
            bMinEl.value = p.budget_min;
            bMinEl.dispatchEvent(new Event('input'));
        }

        // Budget max
        const bMaxEl = document.getElementById('budget_max');
        if (bMaxEl && p.budget_max) {
            bMaxEl.value = p.budget_max;
            bMaxEl.dispatchEvent(new Event('input'));
        }

        // Date limite
        const deadEl = document.querySelector('[name="deadline"]');
        if (deadEl && p.deadline) deadEl.value = p.deadline;

        // Catégorie
        const catEl = document.querySelector('[name="category_id"]');
        if (catEl && p.category_id) catEl.value = String(p.category_id);

        // Visibilité
        if (p.visibility) {
            const radios = document.querySelectorAll('[name="visibility"]');
            radios.forEach(r => {
                r.checked = (r.value === p.visibility);
                r.closest('label').classList.toggle('border-secondary', r.checked);
                r.closest('label').classList.toggle('bg-secondary/5', r.checked);
            });
            document.getElementById('private-link-info')
                ?.classList.toggle('hidden', p.visibility !== 'private');
        }

        // Valider budgets
        checkBudget();
    }, 50);
}
</script>

<?php
$appLayout = true;
require_once '../../includes/footer.php';
?>