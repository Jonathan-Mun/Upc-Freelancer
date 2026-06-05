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

$pdo  = getDB();
$user = currentUser();

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
    if (empty($title))  $errors[] = 'Le titre est requis.';
    if (empty($desc))   $errors[] = 'La description est requise.';
    if ($budgetMin < 0) $errors[] = 'Le budget minimum est invalide.';
    if ($budgetMax > 0 && $budgetMax < $budgetMin) $errors[] = 'Le budget max doit être supérieur au min.';

    if (empty($errors)) {
        $pdo->prepare('
            INSERT INTO projects (uuid, client_id, category_id, title, description, budget_min, budget_max, deadline, skills_needed, visibility)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ')->execute([
            generateUUID(), $user['id'], $categoryId ?: null, $title, $desc,
            $budgetMin ?: null, $budgetMax ?: null, $deadline ?: null,
            $skills ? json_encode($skills) : null, $visibility,
        ]);

        $projectId = (int)$pdo->lastInsertId();
        flash('success', 'Projet publié avec succès !');
        redirect('../../app/projects/details.php?id=' . $projectId);
    } else {
        flash('error', implode(' ', $errors));
    }
}

$categories = $pdo->query('SELECT * FROM categories WHERE is_active = 1 ORDER BY name')->fetchAll();
$pageTitle  = 'Créer un projet — UPC Freelance';
$appLayout  = true;
require_once '../../includes/header.php';
?>

<!-- ── En-tête ───────────────────────────────────────────────── -->
<div class="mb-8">
    <a href="/upc_freelance/app/projects/my-projects.php"
       class="inline-flex items-center gap-1 text-sm text-slate-400 hover:text-secondary transition-colors mb-3 group">
        <span class="material-symbols-outlined text-base group-hover:-translate-x-0.5 transition-transform">arrow_back</span>
        Mes projets
    </a>
    <h1 class="font-h1 text-h1 text-primary leading-tight">Publier un projet</h1>
    <p class="text-on-surface-variant text-sm mt-1">
        Décrivez votre besoin pour attirer les meilleurs freelancers.
    </p>
</div>

<?php renderFlash(); ?>

<!-- ════════════════════════════════════════════════════════
     ÉTAPE 1 — Choix du mode de création
     ════════════════════════════════════════════════════════ -->
<div id="mode-chooser" class="max-w-2xl mx-auto">

    <!-- Icône centrale -->
    <div class="flex flex-col items-center text-center mb-10">
        <div class="w-16 h-16 rounded-2xl bg-surface-container flex items-center justify-center mb-4">
            <span class="material-symbols-outlined text-secondary text-3xl">rocket_launch</span>
        </div>
        <h2 class="font-h3 text-h3 text-primary mb-2">Comment souhaitez-vous créer votre projet ?</h2>
        <p class="text-sm text-on-surface-variant max-w-sm">
            Choisissez la méthode qui vous convient le mieux.
        </p>
    </div>

    <!-- Cards choix -->
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-8">

        <!-- Option IA -->
        <button type="button" onclick="chooseMode('ai')"
                class="group flex flex-col items-start gap-4 p-6 bg-white
                       border-2 border-slate-200 rounded-2xl text-left
                       hover:border-secondary hover:shadow-[0px_8px_24px_rgba(0,97,165,0.12)]
                       hover:-translate-y-0.5 transition-all active:scale-95">

            <div class="flex items-center justify-between w-full">
                <div class="w-12 h-12 rounded-xl bg-secondary/10 flex items-center justify-center flex-shrink-0
                            group-hover:bg-secondary/15 transition-colors">
                    <span class="material-symbols-outlined text-secondary text-2xl"
                          style="font-variation-settings:'FILL' 1">auto_awesome</span>
                </div>
                <span class="text-[10px] font-bold uppercase tracking-wider
                             bg-blue-50 text-secondary px-2.5 py-1 rounded-full border border-blue-100">
                    Recommandé
                </span>
            </div>

            <div>
                <p class="font-semibold text-primary text-base mb-1">Créer avec l'IA</p>
                <p class="text-sm text-on-surface-variant leading-relaxed">
                    Décrivez votre besoin en quelques mots. L'IA génère automatiquement
                    le titre, la description, les compétences et le budget.
                </p>
            </div>

            <div class="flex items-center gap-1.5 text-secondary text-xs font-semibold
                        group-hover:gap-2.5 transition-all">
                <span>Commencer</span>
                <span class="material-symbols-outlined text-sm">arrow_forward</span>
            </div>
        </button>

        <!-- Option manuelle -->
        <button type="button" onclick="chooseMode('manual')"
                class="group flex flex-col items-start gap-4 p-6 bg-white
                       border-2 border-slate-200 rounded-2xl text-left
                       hover:border-slate-400 hover:shadow-[0px_8px_24px_rgba(26,54,93,0.08)]
                       hover:-translate-y-0.5 transition-all active:scale-95">

            <div class="flex items-center justify-between w-full">
                <div class="w-12 h-12 rounded-xl bg-slate-100 flex items-center justify-center flex-shrink-0
                            group-hover:bg-slate-200 transition-colors">
                    <span class="material-symbols-outlined text-slate-500 text-2xl">edit_note</span>
                </div>
                <span class="text-[10px] font-bold uppercase tracking-wider
                             bg-slate-100 text-slate-500 px-2.5 py-1 rounded-full">
                    Contrôle total
                </span>
            </div>

            <div>
                <p class="font-semibold text-primary text-base mb-1">Remplir manuellement</p>
                <p class="text-sm text-on-surface-variant leading-relaxed">
                    Vous avez déjà toutes les informations ? Remplissez chaque
                    champ vous-même à votre rythme.
                </p>
            </div>

            <div class="flex items-center gap-1.5 text-slate-500 text-xs font-semibold
                        group-hover:gap-2.5 transition-all">
                <span>Accéder au formulaire</span>
                <span class="material-symbols-outlined text-sm">arrow_forward</span>
            </div>
        </button>
    </div>

    <!-- Info solde wallet -->
    <?php if ($walletBalance > 0): ?>
    <div class="flex items-center gap-3 p-4 bg-surface-container-low rounded-xl border border-slate-100">
        <span class="material-symbols-outlined text-secondary text-xl flex-shrink-0">account_balance_wallet</span>
        <div>
            <p class="text-sm font-medium text-primary">
                Solde disponible : <strong class="text-secondary"><?= money($walletBalance) ?></strong>
            </p>
            <p class="text-xs text-on-surface-variant mt-0.5">
                Le budget de votre projet ne peut pas dépasser votre solde wallet.
            </p>
        </div>
    </div>
    <?php else: ?>
    <div class="flex items-start gap-3 p-4 bg-amber-50 rounded-xl border border-amber-200">
        <span class="material-symbols-outlined text-amber-500 text-xl flex-shrink-0 mt-0.5">warning</span>
        <div>
            <p class="text-sm font-semibold text-amber-800">Wallet vide</p>
            <p class="text-xs text-amber-700 mt-0.5 leading-relaxed">
                Rechargez votre wallet avant de définir un budget pour votre projet.
                <a href="/upc_freelance/app/wallet/deposit.php" class="underline font-semibold">
                    Recharger maintenant →
                </a>
            </p>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- ════════════════════════════════════════════════════════
     ÉTAPE 2 — Panneau IA
     ════════════════════════════════════════════════════════ -->
<div id="ai-panel" class="hidden max-w-2xl mx-auto">

    <!-- Header avec retour -->
    <div class="flex items-center gap-3 mb-8">
        <button type="button" onclick="chooseMode('chooser')"
                class="p-2 rounded-xl border border-slate-200 hover:bg-slate-50 transition-colors text-slate-500">
            <span class="material-symbols-outlined text-lg">arrow_back</span>
        </button>
        <div>
            <h2 class="font-h3 text-h3 text-primary">Créer avec l'IA</h2>
            <p class="text-sm text-on-surface-variant mt-0.5">Décrivez votre besoin, l'IA fait le reste.</p>
        </div>
        <div class="ml-auto w-10 h-10 rounded-xl bg-secondary/10 flex items-center justify-center">
            <span class="material-symbols-outlined text-secondary"
                  style="font-variation-settings:'FILL' 1">auto_awesome</span>
        </div>
    </div>

    <div class="bg-white rounded-2xl border border-slate-200 p-6
                shadow-[0px_4px_12px_rgba(26,54,93,0.05)]">

        <label class="block text-sm font-medium text-primary mb-2">
            Décrivez votre projet <span class="text-red-500">*</span>
        </label>
        <textarea id="ai-brief" rows="5"
                  placeholder="Ex : J'ai besoin d'un développeur pour créer un site web de vente de vêtements en ligne avec un système de paiement mobile money, un catalogue produits et un espace admin..."
                  class="w-full px-4 py-3 rounded-xl border border-outline-variant
                         focus:border-secondary focus:ring-2 focus:ring-secondary/20
                         outline-none text-sm resize-none transition-all mb-4"></textarea>

        <!-- Exemples cliquables -->
        <div class="mb-5">
            <p class="text-xs text-slate-400 mb-2 font-medium">Exemples rapides :</p>
            <div class="flex flex-wrap gap-2">
                <?php
                $examples = [
                    'Site vitrine pour restaurant',
                    'Application mobile de livraison',
                    'Logo et charte graphique',
                    'Analyse de données Excel',
                ];
                foreach ($examples as $ex):
                ?>
                <button type="button"
                        onclick="document.getElementById('ai-brief').value='<?= $ex ?>'"
                        class="text-xs bg-surface-container text-secondary px-3 py-1.5 rounded-full
                               font-medium hover:bg-secondary/10 transition-colors border border-blue-100
                               active:scale-95">
                    <?= $ex ?>
                </button>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Bouton génération -->
        <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
            <button type="button" onclick="generateWithAI()"
                    id="ai-btn"
                    class="inline-flex items-center justify-center gap-2 bg-secondary text-white
                           px-6 py-3 rounded-xl font-button text-button
                           hover:opacity-90 transition-all active:scale-95 shadow-sm">
                <span class="material-symbols-outlined text-base"
                      style="font-variation-settings:'FILL' 1">auto_awesome</span>
                <span id="ai-btn-text">Générer le projet</span>
            </button>
            <p class="text-xs text-slate-400 text-center sm:text-left">
                Résultat en quelques secondes
            </p>
        </div>

        <!-- Loader -->
        <div id="ai-loading" class="hidden mt-5 flex items-center gap-3 p-4
             bg-surface-container-low rounded-xl border border-slate-100">
            <svg class="animate-spin w-5 h-5 text-secondary flex-shrink-0" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
            </svg>
            <div>
                <p class="text-sm font-medium text-primary">Génération en cours…</p>
                <p class="text-xs text-slate-400">L'IA analyse votre besoin et prépare le formulaire.</p>
            </div>
        </div>

        <!-- Erreur -->
        <div id="ai-error" class="hidden mt-4 p-3 bg-red-50 border border-red-200 rounded-xl
                                   text-sm text-red-600 flex items-center gap-2">
            <span class="material-symbols-outlined text-base flex-shrink-0">error</span>
            <span id="ai-error-text"></span>
        </div>

        <!-- Succès -->
        <div id="ai-regen" class="hidden mt-4 p-4 bg-emerald-50 border border-emerald-200 rounded-xl">
            <p class="text-sm font-semibold text-emerald-800 mb-2 flex items-center gap-1.5">
                <span class="material-symbols-outlined text-base text-emerald-500"
                      style="font-variation-settings:'FILL' 1">check_circle</span>
                Projet généré ! Vérifiez et ajustez si besoin.
            </p>
            <button type="button" onclick="generateWithAI()"
                    class="inline-flex items-center gap-1.5 text-xs font-semibold
                           text-secondary border border-secondary/30 bg-white
                           px-3 py-1.5 rounded-lg hover:bg-blue-50 transition-colors">
                <span class="material-symbols-outlined text-sm">refresh</span>
                Regénérer
            </button>
        </div>
    </div>
</div>

<!-- ════════════════════════════════════════════════════════
     ÉTAPE 3 — Formulaire principal
     ════════════════════════════════════════════════════════ -->
<div id="main-form" class="hidden max-w-3xl">

    <!-- Header avec retour + badge IA si applicable -->
    <div class="flex items-center gap-3 mb-6">
        <button type="button" onclick="backFromForm()"
                class="p-2 rounded-xl border border-slate-200 hover:bg-slate-50 transition-colors text-slate-500 flex-shrink-0">
            <span class="material-symbols-outlined text-lg">arrow_back</span>
        </button>
        <div class="flex-1 min-w-0">
            <h2 class="font-h3 text-h3 text-primary">Détails du projet</h2>
            <p class="text-sm text-on-surface-variant mt-0.5">Remplissez les informations de votre projet.</p>
        </div>
        <!-- Badge IA -->
        <span id="mode-badge" class="hidden flex-shrink-0 inline-flex items-center gap-1.5
              text-xs font-semibold px-3 py-1.5 rounded-full
              bg-blue-50 text-secondary border border-blue-200">
            <span class="material-symbols-outlined text-sm"
                  style="font-variation-settings:'FILL' 1">auto_awesome</span>
            <span class="hidden sm:inline">Rempli par l'IA</span>
        </span>
    </div>

    <form method="POST" enctype="multipart/form-data" class="space-y-5" novalidate>
        <?= csrfField() ?>

        <!-- Informations générales -->
        <div class="bg-white rounded-2xl border border-slate-200 p-6
                    shadow-[0px_4px_12px_rgba(26,54,93,0.05)]">
            <h3 class="font-semibold text-primary mb-5 flex items-center gap-2 text-base">
                <span class="w-6 h-6 rounded-full bg-secondary text-white text-xs font-bold flex items-center justify-center flex-shrink-0">1</span>
                Informations générales
            </h3>
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-primary mb-1.5">
                        Titre du projet <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="title" required maxlength="200"
                           placeholder="Ex: Création d'un site e-commerce pour boutique mode"
                           value="<?= h($_POST['title'] ?? '') ?>"
                           class="w-full px-4 py-3 rounded-xl border border-outline-variant
                                  focus:border-secondary focus:ring-2 focus:ring-secondary/20
                                  outline-none text-sm transition-all"/>
                    <p class="text-xs text-slate-400 mt-1">Max. 200 caractères</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-primary mb-1.5">
                        Description détaillée <span class="text-red-500">*</span>
                    </label>
                    <textarea name="description" id="proj-description" rows="6" required
                              placeholder="Décrivez vos objectifs, livrables, contraintes et contexte..."
                              class="w-full px-4 py-3 rounded-xl border border-outline-variant
                                     focus:border-secondary focus:ring-2 focus:ring-secondary/20
                                     outline-none text-sm transition-all resize-y"><?= h($_POST['description'] ?? '') ?></textarea>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-primary mb-1.5">Catégorie</label>
                        <select name="category_id"
                                class="w-full px-4 py-3 rounded-xl border border-outline-variant
                                       focus:border-secondary outline-none text-sm bg-white">
                            <option value="">-- Sélectionner --</option>
                            <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>"
                                    <?= ($_POST['category_id'] ?? '') == $cat['id'] ? 'selected' : '' ?>>
                                <?= h($cat['name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-primary mb-1.5">Date limite</label>
                        <input type="date" name="deadline"
                               min="<?= date('Y-m-d') ?>"
                               value="<?= h($_POST['deadline'] ?? '') ?>"
                               class="w-full px-4 py-3 rounded-xl border border-outline-variant
                                      focus:border-secondary focus:ring-2 focus:ring-secondary/20
                                      outline-none text-sm transition-all"/>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-primary mb-1.5">
                        Compétences requises
                    </label>
                    <input type="text" name="skills" id="skills-input"
                           value="<?= h(implode(', ', (array)($_POST['skills_arr'] ?? []))) ?>"
                           placeholder="Ex: PHP, React, Figma (séparées par des virgules)"
                           class="w-full px-4 py-3 rounded-xl border border-outline-variant
                                  focus:border-secondary focus:ring-2 focus:ring-secondary/20
                                  outline-none text-sm transition-all"/>
                    <div id="skills-tags" class="flex flex-wrap gap-2 mt-2"></div>
                </div>
            </div>
        </div>

        <!-- Budget -->
        <div class="bg-white rounded-2xl border border-slate-200 p-6
                    shadow-[0px_4px_12px_rgba(26,54,93,0.05)]">
            <h3 class="font-semibold text-primary mb-5 flex items-center gap-2 text-base">
                <span class="w-6 h-6 rounded-full bg-secondary text-white text-xs font-bold flex items-center justify-center flex-shrink-0">2</span>
                Budget
            </h3>

            <!-- Solde wallet inline -->
            <div class="flex items-center gap-2 mb-4 p-3 bg-surface-container-low rounded-xl text-sm">
                <span class="material-symbols-outlined text-secondary text-base">account_balance_wallet</span>
                <span class="text-on-surface-variant">Solde disponible :</span>
                <strong class="text-secondary"><?= money($walletBalance) ?></strong>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-primary mb-1.5">Budget minimum (USD)</label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-xs text-slate-400 font-medium">USD</span>
                        <input type="number" name="budget_min" id="budget_min"
                               min="0" step="100"
                               value="<?= h($_POST['budget_min'] ?? '') ?>"
                               placeholder="0"
                               class="w-full pl-12 pr-4 py-3 rounded-xl border border-outline-variant
                                      focus:border-secondary focus:ring-2 focus:ring-secondary/20
                                      outline-none text-sm transition-all"/>
                    </div>
                    <p id="budget-min-warn" class="hidden text-xs text-red-500 mt-1 flex items-center gap-1">
                        <span class="material-symbols-outlined text-sm">warning</span>
                        <span id="min-warn-text"></span>
                    </p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-primary mb-1.5">Budget maximum (USD)</label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-xs text-slate-400 font-medium">USD</span>
                        <input type="number" name="budget_max" id="budget_max"
                               min="0" step="100"
                               value="<?= h($_POST['budget_max'] ?? '') ?>"
                               placeholder="0"
                               class="w-full pl-12 pr-4 py-3 rounded-xl border border-outline-variant
                                      focus:border-secondary focus:ring-2 focus:ring-secondary/20
                                      outline-none text-sm transition-all"/>
                    </div>
                    <p id="budget-max-warn" class="hidden text-xs text-red-500 mt-1 flex items-center gap-1">
                        <span class="material-symbols-outlined text-sm">warning</span>
                        <span id="max-warn-text"></span>
                    </p>
                </div>
            </div>
        </div>

        <!-- Visibilité -->
        <div class="bg-white rounded-2xl border border-slate-200 p-6
                    shadow-[0px_4px_12px_rgba(26,54,93,0.05)]">
            <h3 class="font-semibold text-primary mb-5 flex items-center gap-2 text-base">
                <span class="w-6 h-6 rounded-full bg-secondary text-white text-xs font-bold flex items-center justify-center flex-shrink-0">3</span>
                Visibilité
            </h3>
            <div class="grid grid-cols-2 gap-3">
                <label class="flex items-start gap-3 p-4 rounded-xl border-2 cursor-pointer transition-all
                              <?= ($_POST['visibility'] ?? 'public') === 'public'
                                    ? 'border-secondary bg-secondary/5'
                                    : 'border-slate-200 hover:border-secondary/40' ?>"
                       onclick="toggleViz(this, 'private-info', false)">
                    <input type="radio" name="visibility" value="public" class="mt-0.5"
                           <?= ($_POST['visibility'] ?? 'public') === 'public' ? 'checked' : '' ?>/>
                    <div>
                        <span class="material-symbols-outlined text-secondary block mb-1">public</span>
                        <p class="text-sm font-semibold text-primary">Public</p>
                        <p class="text-xs text-on-surface-variant">Visible par tous</p>
                    </div>
                </label>
                <label class="flex items-start gap-3 p-4 rounded-xl border-2 cursor-pointer transition-all
                              <?= ($_POST['visibility'] ?? '') === 'private'
                                    ? 'border-secondary bg-secondary/5'
                                    : 'border-slate-200 hover:border-secondary/40' ?>"
                       onclick="toggleViz(this, 'private-info', true)">
                    <input type="radio" name="visibility" value="private" class="mt-0.5"
                           <?= ($_POST['visibility'] ?? '') === 'private' ? 'checked' : '' ?>/>
                    <div>
                        <span class="material-symbols-outlined text-slate-400 block mb-1">lock</span>
                        <p class="text-sm font-semibold text-primary">Privé</p>
                        <p class="text-xs text-on-surface-variant">Sur invitation</p>
                    </div>
                </label>
            </div>

            <!-- Info projet privé -->
            <div id="private-info"
                 class="<?= ($_POST['visibility'] ?? 'public') === 'private' ? '' : 'hidden' ?>
                        mt-4 flex items-start gap-3 p-4 bg-blue-50 rounded-xl border border-blue-100">
                <span class="material-symbols-outlined text-secondary mt-0.5 flex-shrink-0"
                      style="font-variation-settings:'FILL' 1">lock</span>
                <p class="text-xs text-on-surface-variant leading-relaxed">
                    Un lien unique sera généré après publication.
                    Partagez-le uniquement aux freelancers que vous souhaitez inviter.
                </p>
            </div>
        </div>

        <!-- Boutons -->
        <div class="flex flex-col sm:flex-row gap-3 pt-2">
            <button type="submit"
                    id="submit-btn"
                    class="flex-1 bg-primary text-white font-button text-button py-3.5 rounded-xl
                           hover:opacity-90 transition-opacity active:scale-95 shadow-sm
                           flex items-center justify-center gap-2">
                <span class="material-symbols-outlined text-base">publish</span>
                Publier le projet
            </button>
            <a href="/upc_freelance/app/projects/my-projects.php"
               class="sm:w-auto text-center px-6 py-3.5 rounded-xl border-2 border-slate-200
                      text-sm font-medium text-on-surface-variant
                      hover:border-slate-300 transition-colors">
                Annuler
            </a>
        </div>
    </form>
</div>

<script>
// ── Navigation modes ─────────────────────────────────────────
let currentOrigin = 'chooser'; // 'chooser' | 'ai' | 'manual'

function chooseMode(mode) {
    document.getElementById('mode-chooser').classList.add('hidden');
    document.getElementById('ai-panel').classList.add('hidden');
    document.getElementById('main-form').classList.add('hidden');

    if (mode === 'chooser') {
        document.getElementById('mode-chooser').classList.remove('hidden');
    } else if (mode === 'ai') {
        document.getElementById('ai-panel').classList.remove('hidden');
        currentOrigin = 'ai';
    } else if (mode === 'manual') {
        document.getElementById('main-form').classList.remove('hidden');
        document.getElementById('mode-badge').classList.add('hidden');
        currentOrigin = 'manual';
    }

    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function backFromForm() {
    // Si on vient de l'IA, retourner au panneau IA
    if (currentOrigin === 'ai') {
        chooseMode('ai');
    } else {
        chooseMode('chooser');
    }
}

// ── Tags compétences ─────────────────────────────────────────
const skillInput = document.getElementById('skills-input');
const skillTags  = document.getElementById('skills-tags');

function renderTags() {
    if (!skillInput || !skillTags) return;
    const vals = skillInput.value.split(',').map(s => s.trim()).filter(Boolean);
    skillTags.innerHTML = vals.map(v =>
        `<span class="inline-flex items-center gap-1 bg-surface-container text-secondary
                      text-xs px-2.5 py-1 rounded-full font-medium">${v}</span>`
    ).join('');
}
if (skillInput) {
    skillInput.addEventListener('input', renderTags);
    renderTags();
}

// ── Validation budget ─────────────────────────────────────────
const walletLimit  = <?= $walletBalance ?>;
const budgetMinEl  = document.getElementById('budget_min');
const budgetMaxEl  = document.getElementById('budget_max');
const minWarnEl    = document.getElementById('budget-min-warn');
const maxWarnEl    = document.getElementById('budget-max-warn');
const minWarnTxt   = document.getElementById('min-warn-text');
const maxWarnTxt   = document.getElementById('max-warn-text');
const submitBtn    = document.getElementById('submit-btn');

function checkBudget() {
    if (!budgetMinEl || !budgetMaxEl) return;
    const minVal = parseFloat(budgetMinEl.value) || 0;
    const maxVal = parseFloat(budgetMaxEl.value) || 0;

    let minErr = '', maxErr = '';

    if (minVal > 0 && walletLimit > 0 && minVal > walletLimit) {
        minErr = 'Dépasse votre solde (<?= money($walletBalance) ?>)';
    } else if (minVal > 0 && maxVal > 0 && minVal > maxVal) {
        minErr = 'Doit être inférieur au budget max';
    }

    if (maxVal > 0 && walletLimit > 0 && maxVal > walletLimit) {
        maxErr = 'Dépasse votre solde (<?= money($walletBalance) ?>)';
    }

    // Afficher/cacher warnings
    if (minErr) {
        minWarnTxt.textContent = minErr;
        minWarnEl.classList.remove('hidden');
        budgetMinEl.style.borderColor = '#ef4444';
    } else {
        minWarnEl.classList.add('hidden');
        budgetMinEl.style.borderColor = '';
    }

    if (maxErr) {
        maxWarnTxt.textContent = maxErr;
        maxWarnEl.classList.remove('hidden');
        budgetMaxEl.style.borderColor = '#ef4444';
    } else {
        maxWarnEl.classList.add('hidden');
        budgetMaxEl.style.borderColor = '';
    }

    // Bloquer soumission si erreur
    const hasError = !!(minErr || maxErr);
    if (submitBtn) {
        submitBtn.disabled      = hasError;
        submitBtn.style.opacity = hasError ? '0.5' : '';
    }
}

if (budgetMinEl) budgetMinEl.addEventListener('input', checkBudget);
if (budgetMaxEl) budgetMaxEl.addEventListener('input', checkBudget);

// ── Toggle visibilité ─────────────────────────────────────────
function toggleViz(label, infoId, show) {
    // Reset tous les labels
    label.closest('.grid').querySelectorAll('label').forEach(l => {
        l.classList.remove('border-secondary','bg-secondary/5');
        l.classList.add('border-slate-200');
    });
    // Activer le label cliqué
    label.classList.add('border-secondary','bg-secondary/5');
    label.classList.remove('border-slate-200');

    // Afficher/cacher l'info privé
    const info = document.getElementById(infoId);
    if (info) info.classList.toggle('hidden', !show);
}

// ── Génération IA ─────────────────────────────────────────────
async function generateWithAI() {
    const brief    = document.getElementById('ai-brief')?.value.trim() ?? '';
    const btn      = document.getElementById('ai-btn');
    const btnText  = document.getElementById('ai-btn-text');
    const loading  = document.getElementById('ai-loading');
    const errorBox = document.getElementById('ai-error');
    const errorTxt = document.getElementById('ai-error-text');
    const regen    = document.getElementById('ai-regen');

    if (brief.length < 10) {
        errorTxt.textContent = 'Décrivez votre projet en au moins 10 caractères.';
        errorBox.classList.remove('hidden');
        return;
    }

    errorBox.classList.add('hidden');
    regen.classList.add('hidden');
    loading.classList.remove('hidden');
    btn.disabled = true;
    btnText.textContent = 'Génération…';

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
        document.getElementById('mode-badge').classList.remove('hidden');
        regen.classList.remove('hidden');

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
    // Afficher le formulaire
    document.getElementById('ai-panel').classList.add('hidden');
    document.getElementById('main-form').classList.remove('hidden');
    currentOrigin = 'ai';

    setTimeout(() => {
        const titleEl = document.querySelector('[name="title"]');
        if (titleEl && p.title) titleEl.value = p.title;

        const descEl = document.getElementById('proj-description');
        if (descEl && p.description) descEl.value = p.description;

        const skillEl = document.getElementById('skills-input');
        if (skillEl && p.skills) {
            skillEl.value = Array.isArray(p.skills) ? p.skills.join(', ') : p.skills;
            renderTags();
        }

        const bMin = document.getElementById('budget_min');
        if (bMin && p.budget_min) { bMin.value = p.budget_min; bMin.dispatchEvent(new Event('input')); }

        const bMax = document.getElementById('budget_max');
        if (bMax && p.budget_max) { bMax.value = p.budget_max; bMax.dispatchEvent(new Event('input')); }

        const deadEl = document.querySelector('[name="deadline"]');
        if (deadEl && p.deadline) deadEl.value = p.deadline;

        const catEl = document.querySelector('[name="category_id"]');
        if (catEl && p.category_id) catEl.value = String(p.category_id);

        checkBudget();
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }, 50);
}
</script>

<?php $appLayout = true; require_once '../../includes/footer.php'; ?>