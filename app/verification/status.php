<?php
// ============================================================
// UPC FREELANCE — Statut vérification (page résumé public)
// ../../app/verification/status.php
// ============================================================

require_once '../../includes/middleware.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../includes/db.php';

requireLogin();

$user    = currentUser();
$pdo     = getDB();
$profile = getExtendedProfile($user['id'], $user['role']);

// Docs
$stmt = $pdo->prepare('SELECT * FROM verification_docs WHERE user_id = ? ORDER BY created_at DESC');
$stmt->execute([$user['id']]);
$docs = $stmt->fetchAll();
$latestDoc = $docs[0] ?? null;

// Calculer % complétude
$role = $user['role'];
if ($role === 'freelancer') {
    $fields = [
        $user['avatar'],
        $user['phone'],
        $profile['bio'] ?? null,
        $profile['university'] ?? null,
        $profile['skills'] ?? null,
        $profile['title'] ?? null,
    ];
} else {
    $fields = [
        $user['avatar'],
        $user['phone'],
        $profile['bio'] ?? null,
        $profile['company_name'] ?? null,
    ];
}
$filled  = count(array_filter($fields));
$percent = (int)round($filled / count($fields) * 100);

$pageTitle = 'Statut du compte — UPC Freelance';
$appLayout = true;
require_once '../../includes/header.php';
?>

<div class="mb-8">
    <h1 class="text-2xl font-bold text-primary">Statut de mon compte</h1>
    <p class="text-on-surface-variant text-sm mt-1">Vue d'ensemble de votre niveau de confiance sur la plateforme.</p>
</div>

<!-- Score de confiance -->
<div class="bg-white rounded-2xl border border-slate-100 p-8 custom-shadow-low mb-6">
    <div class="flex flex-col md:flex-row items-center gap-8">

        <!-- Score circulaire -->
        <div class="relative flex-shrink-0">
            <svg width="140" height="140" viewBox="0 0 140 140">
                <circle cx="70" cy="70" r="58" fill="none" stroke="#e2e8f0" stroke-width="12"/>
                <circle cx="70" cy="70" r="58" fill="none"
                        stroke="<?= $percent >= 80 ? '#10b981' : ($percent >= 50 ? '#f59e0b' : '#3b82f6') ?>"
                        stroke-width="12"
                        stroke-linecap="round"
                        stroke-dasharray="<?= round(2 * M_PI * 58) ?>"
                        stroke-dashoffset="<?= round(2 * M_PI * 58 * (1 - $percent / 100)) ?>"
                        transform="rotate(-90 70 70)"/>
            </svg>
            <div class="absolute inset-0 flex flex-col items-center justify-center">
                <span class="text-3xl font-bold text-primary"><?= $percent ?>%</span>
                <span class="text-xs text-slate-400">Complétude</span>
            </div>
        </div>

        <!-- Détails statut -->
        <div class="flex-1 space-y-4">
            <div>
                <h2 class="text-xl font-bold text-primary mb-1">
                    Niveau de confiance :
                    <span class="<?= $percent >= 80 ? 'text-emerald-600' : ($percent >= 50 ? 'text-amber-600' : 'text-secondary') ?>">
                        <?= $percent >= 100 ? 'Excellent' : ($percent >= 80 ? 'Très bon' : ($percent >= 50 ? 'Moyen' : 'Débutant')) ?>
                    </span>
                </h2>
                <p class="text-sm text-on-surface-variant">
                    <?php if ($user['is_verified']): ?>
                    ✅ Votre compte est entièrement vérifié et certifié.
                    <?php elseif ($latestDoc && $latestDoc['status'] === 'pending'): ?>
                    ⏳ Document en cours d'examen — vous serez notifié sous 24-48h.
                    <?php else: ?>
                    📋 Complétez les étapes de vérification pour améliorer votre score.
                    <?php endif; ?>
                </p>
            </div>

            <!-- Badges -->
            <div class="flex flex-wrap gap-2">
                <?php
                $badges = [
                    ['condition' => !empty($user['avatar']),      'label'=>'Photo',       'icon'=>'face',         'color'=>'blue'],
                    ['condition' => !empty($user['phone']),        'label'=>'Téléphone',   'icon'=>'phone',        'color'=>'green'],
                    ['condition' => !empty($profile['bio']),       'label'=>'Bio',         'icon'=>'description',  'color'=>'purple'],
                    ['condition' => $user['is_verified'],          'label'=>'Certifié',    'icon'=>'verified',     'color'=>'emerald'],
                ];
                foreach ($badges as $badge):
                    $c = $badge['condition'] ? $badge['color'] : 'slate';
                ?>
                <div class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full border text-xs font-medium
                    <?= $badge['condition']
                        ? 'bg-' . $c . '-50 border-' . $c . '-200 text-' . $c . '-700'
                        : 'bg-slate-50 border-slate-200 text-slate-400' ?>">
                    <span class="material-symbols-outlined text-sm" style="font-variation-settings:'FILL' <?= $badge['condition'] ? 1 : 0 ?>">
                        <?= $badge['icon'] ?>
                    </span>
                    <?= $badge['label'] ?>
                    <?php if (!$badge['condition']): ?>
                    <span class="opacity-50">✗</span>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- Actions rapides -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
    <a href="/upc_freelance/app/verification/index.php"
       class="bg-white rounded-2xl border border-slate-100 p-5 hover:border-secondary/40 hover:shadow-md transition-all custom-shadow-low flex items-center gap-3">
        <div class="w-10 h-10 bg-blue-50 rounded-xl flex items-center justify-center flex-shrink-0">
            <span class="material-symbols-outlined text-secondary">shield</span>
        </div>
        <div>
            <p class="text-sm font-semibold text-primary">Tableau de vérification</p>
            <p class="text-xs text-slate-400">Voir toutes les étapes</p>
        </div>
    </a>
    <a href="/upc_freelance/app/verification/phone.php"
       class="bg-white rounded-2xl border border-slate-100 p-5 hover:border-secondary/40 hover:shadow-md transition-all custom-shadow-low flex items-center gap-3">
        <div class="w-10 h-10 <?= $user['phone'] ? 'bg-emerald-50' : 'bg-amber-50' ?> rounded-xl flex items-center justify-center flex-shrink-0">
            <span class="material-symbols-outlined <?= $user['phone'] ? 'text-emerald-500' : 'text-amber-500' ?>">phone</span>
        </div>
        <div>
            <p class="text-sm font-semibold text-primary">Téléphone</p>
            <p class="text-xs text-slate-400"><?= $user['phone'] ? h($user['phone']) : 'Non vérifié' ?></p>
        </div>
    </a>
    <a href="/upc_freelance/app/verification/submit.php"
       class="bg-white rounded-2xl border border-slate-100 p-5 hover:border-secondary/40 hover:shadow-md transition-all custom-shadow-low flex items-center gap-3">
        <div class="w-10 h-10 bg-purple-50 rounded-xl flex items-center justify-center flex-shrink-0">
            <span class="material-symbols-outlined text-purple-500">upload_file</span>
        </div>
        <div>
            <p class="text-sm font-semibold text-primary">Soumettre un doc</p>
            <p class="text-xs text-slate-400">
                <?= $latestDoc ? match($latestDoc['status']) {
                    'pending'  => '⏳ En attente',
                    'approved' => '✅ Approuvé',
                    'rejected' => '❌ Refusé',
                    default    => 'Soumettre'
                } : 'Aucun document' ?>
            </p>
        </div>
    </a>
</div>

<!-- Historique documents -->
<?php if (!empty($docs)): ?>
<div class="bg-white rounded-2xl border border-slate-100 custom-shadow-low overflow-hidden">
    <div class="p-5 border-b border-slate-100">
        <h2 class="font-semibold text-primary">Historique des documents</h2>
    </div>
    <div class="divide-y divide-slate-50">
        <?php
        $docLabels = [
            'student_card' => ['label'=>'Carte étudiante',                'icon'=>'school'],
            'id_card'      => ['label'=>'Carte d\'identité',              'icon'=>'badge'],
            'diploma'      => ['label'=>'Justificatif entreprise/Diplôme','icon'=>'workspace_premium'],
            'other'        => ['label'=>'Autre document',                 'icon'=>'description'],
        ];
        foreach ($docs as $doc):
            $dl  = $docLabels[$doc['doc_type']] ?? ['label'=>$doc['doc_type'],'icon'=>'description'];
            $sc  = ['pending'=>'amber','approved'=>'green','rejected'=>'red'][$doc['status']] ?? 'gray';
            $sl  = ['pending'=>'En attente d\'examen','approved'=>'Approuvé ✓','rejected'=>'Refusé'][$doc['status']] ?? $doc['status'];
        ?>
        <div class="flex items-center gap-4 p-4">
            <div class="w-10 h-10 bg-<?= $sc ?>-50 rounded-xl flex items-center justify-center flex-shrink-0">
                <span class="material-symbols-outlined text-<?= $sc ?>-500"><?= $dl['icon'] ?></span>
            </div>
            <div class="flex-1">
                <p class="text-sm font-semibold text-primary"><?= $dl['label'] ?></p>
                <p class="text-xs text-slate-400">Soumis le <?= formatDate($doc['created_at'], 'd/m/Y à H:i') ?></p>
                <?php if ($doc['admin_note']): ?>
                <p class="text-xs text-slate-500 mt-0.5 italic">Note : <?= h($doc['admin_note']) ?></p>
                <?php endif; ?>
            </div>
            <div class="text-right">
                <span class="inline-block text-xs bg-<?= $sc ?>-100 text-<?= $sc ?>-700 px-2.5 py-1 rounded-full font-medium">
                    <?= $sl ?>
                </span>
                <?php if ($doc['reviewed_at']): ?>
                <p class="text-xs text-slate-400 mt-1">Examiné le <?= formatDate($doc['reviewed_at']) ?></p>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<?php $appLayout = true; require_once '../../includes/footer.php'; ?>
