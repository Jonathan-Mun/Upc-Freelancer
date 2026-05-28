<?php
// ============================================================
// UPC FREELANCE — Créer un projet
// ../../app/projects/create.php
// ============================================================

require_once '../../includes/middleware.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../includes/db.php';

requireRole('client', 'freelancer'); // Seuls les clients et freelancers peuvent créer des projets

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
            createNotification(
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
                    <textarea name="description" rows="6" required
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
                        Budget min (USD)
                    </label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-xs text-slate-400 font-medium">USD</span>
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
                        Budget max (USD)
                    </label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-xs text-slate-400 font-medium">USD</span>
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

<?php
$appLayout = true;
require_once '../../includes/footer.php';
?>