<?php
// ============================================================
// UPC FREELANCE — Vérification de compte (index)
// ../../app/verification/index.php
// ============================================================

require_once '../../includes/middleware.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../includes/db.php';

requireLogin();

$user   = currentUser();
$pdo    = getDB();
$userId = $user['id'];
$role   = $user['role'];

// Charger le profil étendu
$profile = getExtendedProfile($userId, $role);

// Charger les docs soumis
$stmt = $pdo->prepare('SELECT * FROM verification_docs WHERE user_id = ? ORDER BY created_at DESC');
$stmt->execute([$userId]);
$docs = $stmt->fetchAll();

// Organiser par type
$docsByType = [];
foreach ($docs as $doc) {
    $docsByType[$doc['doc_type']] = $doc;
}

// Calcul complétude du profil
$checkItems = [];

if ($role === 'freelancer') {
    $checkItems = [
        ['key' => 'avatar',       'label' => 'Photo de profil',        'value' => $user['avatar'],                     'link' => '/upc_freelance/app/profile/edit.php'],
        ['key' => 'phone',        'label' => 'Numéro de téléphone',    'value' => $user['phone'],                      'link' => '/upc_freelance/app/verification/phone.php'],
        ['key' => 'bio',          'label' => 'Biographie',             'value' => $profile['bio'] ?? null,             'link' => '/upc_freelance/app/profile/edit.php'],
        ['key' => 'university',   'label' => 'Université',             'value' => $profile['university'] ?? null,      'link' => '/upc_freelance/app/profile/edit.php'],
        ['key' => 'skills',       'label' => 'Compétences',            'value' => $profile['skills'] ?? null,          'link' => '/upc_freelance/app/profile/edit.php'],
        ['key' => 'title',        'label' => 'Titre professionnel',    'value' => $profile['title'] ?? null,           'link' => '/upc_freelance/app/profile/edit.php'],
        ['key' => 'student_card', 'label' => 'Carte étudiante',        'value' => $docsByType['student_card']['status'] ?? null, 'link' => '/upc_freelance/app/verification/submit.php'],
    ];
} else {
    $isCompany = !empty($profile['company_name']);
    $checkItems = [
        ['key' => 'avatar',       'label' => 'Photo de profil',        'value' => $user['avatar'],                                'link' => '/upc_freelance/app/profile/edit.php'],
        ['key' => 'phone',        'label' => 'Numéro de téléphone',    'value' => $user['phone'],                                 'link' => '/upc_freelance/app/verification/phone.php'],
        ['key' => 'bio',          'label' => 'Description',            'value' => $profile['bio'] ?? null,                       'link' => '/upc_freelance/app/profile/edit.php'],
        ['key' => 'id_doc',       'label' => $isCompany ? 'Justificatif entreprise' : 'Pièce d\'identité',
                                   'value' => $docsByType[$isCompany ? 'diploma' : 'id_card']['status'] ?? null,
                                   'link' => '/upc_freelance/app/verification/submit.php'],
    ];
}

$filled    = count(array_filter($checkItems, fn($c) => !empty($c['value']) && $c['value'] !== 'rejected'));
$total     = count($checkItems);
$percent   = (int)round($filled / $total * 100);
$isVerified = (bool)$user['is_verified'];

// Dernier doc soumis
$lastDoc = $docs[0] ?? null;

$pageTitle = 'Vérification du compte — UPC Freelance';
$appLayout = true;
require_once '../../includes/header.php';
?>

<?php renderFlash(); ?>

<!-- En-tête -->
<div class="mb-8">
    <h1 class="text-2xl font-bold text-primary">Vérification du compte</h1>
    <p class="text-on-surface-variant text-sm mt-1">
        Complétez toutes les étapes pour obtenir le badge vérifié et inspirer confiance aux clients.
    </p>
</div>

<!-- Bannière statut global -->
<?php if ($isVerified): ?>
<div class="bg-emerald-50 border border-emerald-200 rounded-2xl p-5 mb-6 flex items-center gap-4">
    <div class="w-12 h-12 bg-emerald-500 rounded-xl flex items-center justify-center flex-shrink-0">
        <span class="material-symbols-outlined text-white text-2xl" style="font-variation-settings:'FILL' 1">verified</span>
    </div>
    <div>
        <p class="font-bold text-emerald-800">Compte vérifié ✓</p>
        <p class="text-sm text-emerald-600 mt-0.5">Votre compte est certifié. Le badge vérifié est visible sur votre profil.</p>
    </div>
</div>
<?php elseif ($lastDoc && $lastDoc['status'] === 'pending'): ?>
<div class="bg-amber-50 border border-amber-200 rounded-2xl p-5 mb-6 flex items-center gap-4">
    <div class="w-12 h-12 bg-amber-400 rounded-xl flex items-center justify-center flex-shrink-0">
        <span class="material-symbols-outlined text-white text-2xl">hourglass_top</span>
    </div>
    <div>
        <p class="font-bold text-amber-800">Vérification en cours</p>
        <p class="text-sm text-amber-700 mt-0.5">Votre document est en cours d'examen par notre équipe (24-48h ouvrables).</p>
    </div>
</div>
<?php else: ?>
<div class="bg-blue-50 border border-blue-200 rounded-2xl p-5 mb-6 flex items-center gap-4">
    <div class="w-12 h-12 bg-secondary rounded-xl flex items-center justify-center flex-shrink-0">
        <span class="material-symbols-outlined text-white text-2xl">shield</span>
    </div>
    <div class="flex-1">
        <p class="font-bold text-primary">Compte non vérifié</p>
        <p class="text-sm text-on-surface-variant mt-0.5">Complétez les étapes ci-dessous pour obtenir votre certification.</p>
    </div>
    <a href="/upc_freelance/app/verification/submit.php"
       class="bg-primary text-white text-sm font-button px-4 py-2.5 rounded-xl hover:opacity-90 transition-opacity active:scale-95 whitespace-nowrap">
        Commencer →
    </a>
</div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    <!-- ── Checklist complétude ───────────────────────── -->
    <div class="lg:col-span-2 space-y-4">

        <!-- Barre de progression -->
        <div class="bg-white rounded-2xl border border-slate-100 p-6 custom-shadow-low">
            <div class="flex items-center justify-between mb-3">
                <h2 class="font-semibold text-primary">Complétude du profil</h2>
                <span class="text-2xl font-bold <?= $percent >= 100 ? 'text-emerald-600' : ($percent >= 60 ? 'text-amber-600' : 'text-red-500') ?>">
                    <?= $percent ?>%
                </span>
            </div>
            <div class="w-full bg-slate-100 rounded-full h-3 overflow-hidden mb-2">
                <div class="h-3 rounded-full transition-all duration-700
                    <?= $percent >= 100 ? 'bg-emerald-500' : ($percent >= 60 ? 'bg-amber-400' : 'bg-secondary') ?>"
                     style="width: <?= $percent ?>%"></div>
            </div>
            <p class="text-xs text-on-surface-variant"><?= $filled ?> / <?= $total ?> éléments complétés</p>
        </div>

        <!-- Checklist items -->
        <div class="bg-white rounded-2xl border border-slate-100 custom-shadow-low overflow-hidden">
            <div class="p-5 border-b border-slate-100">
                <h2 class="font-semibold text-primary">Étapes de vérification</h2>
            </div>
            <div class="divide-y divide-slate-50">
                <?php foreach ($checkItems as $item):
                    $isDone    = !empty($item['value']) && $item['value'] !== 'rejected';
                    $isPending = $item['value'] === 'pending';
                    $isApproved= $item['value'] === 'approved';
                    $isRejected= $item['value'] === 'rejected';

                    // Icône & couleur
                    if ($isApproved || ($isDone && !in_array($item['key'], ['student_card','id_doc','id_card','diploma']))) {
                        $iconColor = 'text-emerald-500'; $icon = 'check_circle'; $fill = 1;
                        $badge = '<span class="text-xs bg-emerald-100 text-emerald-700 px-2 py-0.5 rounded-full font-medium">Complété</span>';
                    } elseif ($isPending) {
                        $iconColor = 'text-amber-400'; $icon = 'hourglass_top'; $fill = 0;
                        $badge = '<span class="text-xs bg-amber-100 text-amber-700 px-2 py-0.5 rounded-full font-medium">En attente</span>';
                    } elseif ($isRejected) {
                        $iconColor = 'text-red-400'; $icon = 'cancel'; $fill = 1;
                        $badge = '<span class="text-xs bg-red-100 text-red-600 px-2 py-0.5 rounded-full font-medium">Refusé</span>';
                    } else {
                        $iconColor = 'text-slate-300'; $icon = 'radio_button_unchecked'; $fill = 0;
                        $badge = '<span class="text-xs bg-slate-100 text-slate-500 px-2 py-0.5 rounded-full font-medium">À compléter</span>';
                    }
                ?>
                <div class="flex items-center gap-4 p-4">
                    <span class="material-symbols-outlined <?= $iconColor ?> text-2xl flex-shrink-0"
                          style="font-variation-settings:'FILL' <?= $fill ?>">
                        <?= $icon ?>
                    </span>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold text-primary"><?= h($item['label']) ?></p>
                        <?php if ($isRejected && isset($docsByType[$item['key']]['admin_note'])): ?>
                        <p class="text-xs text-red-500 mt-0.5">Refus : <?= h($docsByType[$item['key']]['admin_note'] ?? '') ?></p>
                        <?php endif; ?>
                    </div>
                    <?= $badge ?>
                    <?php if (!$isDone || $isRejected): ?>
                    <a href="<?= h($item['link']) ?>"
                       class="text-xs text-secondary hover:underline whitespace-nowrap flex items-center gap-1">
                        <span class="material-symbols-outlined text-sm">arrow_forward</span>
                        <?= $isRejected ? 'Resoumettre' : 'Compléter' ?>
                    </a>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Documents soumis -->
        <?php if (!empty($docs)): ?>
        <div class="bg-white rounded-2xl border border-slate-100 custom-shadow-low overflow-hidden">
            <div class="p-5 border-b border-slate-100">
                <h2 class="font-semibold text-primary">Documents soumis</h2>
            </div>
            <div class="divide-y divide-slate-50">
                <?php
                $docLabels = [
                    'student_card' => 'Carte étudiante',
                    'id_card'      => 'Carte d\'identité',
                    'diploma'      => 'Justificatif entreprise / Diplôme',
                    'other'        => 'Autre document',
                ];
                foreach ($docs as $doc):
                    $sc = ['pending'=>'amber','approved'=>'green','rejected'=>'red'][$doc['status']] ?? 'gray';
                    $sl = ['pending'=>'En attente','approved'=>'Approuvé','rejected'=>'Refusé'][$doc['status']] ?? $doc['status'];
                ?>
                <div class="flex items-center gap-4 p-4">
                    <div class="w-10 h-10 bg-slate-50 rounded-xl flex items-center justify-center flex-shrink-0">
                        <span class="material-symbols-outlined text-slate-400">description</span>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold text-primary"><?= $docLabels[$doc['doc_type']] ?? $doc['doc_type'] ?></p>
                        <p class="text-xs text-slate-400">Soumis le <?= formatDate($doc['created_at']) ?></p>
                        <?php if ($doc['admin_note'] && $doc['status'] === 'rejected'): ?>
                        <p class="text-xs text-red-500 mt-0.5">📋 <?= h($doc['admin_note']) ?></p>
                        <?php endif; ?>
                    </div>
                    <span class="text-xs bg-<?= $sc ?>-100 text-<?= $sc ?>-700 px-2.5 py-1 rounded-full font-medium">
                        <?= $sl ?>
                    </span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- ── Sidebar info ───────────────────────────────── -->
    <div class="space-y-5">

        <!-- Statut compte -->
        <div class="bg-white rounded-2xl border border-slate-100 p-5 custom-shadow-low">
            <h3 class="font-semibold text-primary mb-4">Statut du compte</h3>
            <div class="space-y-3">
                <div class="flex justify-between items-center text-sm">
                    <span class="text-on-surface-variant flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-base text-slate-400">badge</span> Certification
                    </span>
                    <?php if ($isVerified): ?>
                    <span class="inline-flex items-center gap-1 text-emerald-600 font-semibold text-xs">
                        <span class="material-symbols-outlined text-sm" style="font-variation-settings:'FILL' 1">verified</span> Vérifié
                    </span>
                    <?php else: ?>
                    <span class="text-slate-400 text-xs font-medium">Non vérifié</span>
                    <?php endif; ?>
                </div>
                <div class="flex justify-between items-center text-sm">
                    <span class="text-on-surface-variant flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-base text-slate-400">phone</span> Téléphone
                    </span>
                    <?php if ($user['phone']): ?>
                    <span class="text-emerald-600 font-semibold text-xs flex items-center gap-1">
                        <span class="material-symbols-outlined text-sm" style="font-variation-settings:'FILL' 1">check_circle</span>
                        <?= h($user['phone']) ?>
                    </span>
                    <?php else: ?>
                    <a href="/upc_freelance/app/verification/phone.php" class="text-xs text-secondary hover:underline font-medium">
                        + Ajouter
                    </a>
                    <?php endif; ?>
                </div>
                <div class="flex justify-between items-center text-sm">
                    <span class="text-on-surface-variant flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-base text-slate-400">mail</span> Email
                    </span>
                    <span class="text-emerald-600 font-semibold text-xs flex items-center gap-1">
                        <span class="material-symbols-outlined text-sm" style="font-variation-settings:'FILL' 1">check_circle</span>
                        Confirmé
                    </span>
                </div>
                <div class="flex justify-between items-center text-sm">
                    <span class="text-on-surface-variant flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-base text-slate-400">person</span> Rôle
                    </span>
                    <span class="text-xs px-2 py-0.5 rounded-full font-medium <?= $role === 'freelancer' ? 'bg-purple-100 text-purple-700' : 'bg-blue-100 text-blue-700' ?>">
                        <?= $role === 'freelancer' ? 'Freelancer' : 'Client' ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Pourquoi vérifier ? -->
        <div class="bg-white rounded-2xl border border-slate-100 p-5 custom-shadow-low">
            <h3 class="font-semibold text-primary mb-4">Pourquoi se vérifier ?</h3>
            <ul class="space-y-3 text-sm">
                <?php
                $benefits = $role === 'freelancer' ? [
                    ['icon'=>'verified',      'text'=>'Badge vérifié visible sur votre profil'],
                    ['icon'=>'trending_up',   'text'=>'3x plus de chances d\'être sélectionné'],
                    ['icon'=>'security',      'text'=>'Accès aux projets premium'],
                    ['icon'=>'payments',      'text'=>'Retraits sans limite de montant'],
                ] : [
                    ['icon'=>'verified',      'text'=>'Badge vérifié pour inspirer confiance'],
                    ['icon'=>'groups',        'text'=>'Accès aux meilleurs freelancers'],
                    ['icon'=>'payments',      'text'=>'Limite de dépôt augmentée'],
                    ['icon'=>'support_agent', 'text'=>'Support prioritaire'],
                ];
                foreach ($benefits as $b):
                ?>
                <li class="flex items-start gap-2.5">
                    <span class="material-symbols-outlined text-secondary text-base flex-shrink-0 mt-0.5"><?= $b['icon'] ?></span>
                    <span class="text-on-surface-variant"><?= $b['text'] ?></span>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <!-- Actions rapides -->
        <div class="space-y-2">
            <?php if (!$user['phone']): ?>
            <a href="/upc_freelance/app/verification/phone.php"
               class="flex items-center gap-3 w-full bg-white border border-slate-100 rounded-xl p-4 hover:border-secondary/40 transition-colors custom-shadow-low">
                <span class="material-symbols-outlined text-secondary">phone</span>
                <div>
                    <p class="text-sm font-semibold text-primary">Vérifier mon téléphone</p>
                    <p class="text-xs text-slate-400">Ajouter et confirmer votre numéro</p>
                </div>
                <span class="ml-auto material-symbols-outlined text-slate-300">arrow_forward_ios</span>
            </a>
            <?php endif; ?>
            <a href="/upc_freelance/app/verification/submit.php"
               class="flex items-center gap-3 w-full bg-white border border-slate-100 rounded-xl p-4 hover:border-secondary/40 transition-colors custom-shadow-low">
                <span class="material-symbols-outlined text-secondary">upload_file</span>
                <div>
                    <p class="text-sm font-semibold text-primary">Soumettre un document</p>
                    <p class="text-xs text-slate-400">
                        <?= $role === 'freelancer' ? 'Carte étudiante, diplôme...' : 'Pièce d\'identité, RCCM...' ?>
                    </p>
                </div>
                <span class="ml-auto material-symbols-outlined text-slate-300">arrow_forward_ios</span>
            </a>
        </div>
    </div>
</div>

<?php $appLayout = true; require_once '../../includes/footer.php'; ?>
