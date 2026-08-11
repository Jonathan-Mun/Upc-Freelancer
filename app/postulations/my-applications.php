<?php
// ============================================================
// UPC FREELANCE — Mes candidatures (freelancer)
// ../../app/postulations/my-applications.php
// ============================================================

require_once '../../includes/middleware.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../includes/db.php';

requireRole('freelancer');

$user   = currentUser();
$pdo    = getDB();
$status = sanitize($_GET['status'] ?? '');

$where  = ['po.freelancer_id = ?'];
$params = [$user['id']];
if ($status && in_array($status, ['pending','accepted','rejected','withdrawn'])) {
    $where[]  = 'po.status = ?';
    $params[] = $status;
}

$stmt = $pdo->prepare('
    SELECT po.*, p.title AS project_title, p.budget_min, p.budget_max, p.deadline, p.status AS project_status,
           c.name AS category_name,
           u.first_name AS client_fname, u.last_name AS client_lname,
           u.avatar AS client_avatar, u.is_verified AS client_verified
    FROM postulations po
    JOIN projects p ON p.id = po.project_id
    LEFT JOIN categories c ON c.id = p.category_id
    JOIN users u ON u.id = p.client_id
    WHERE ' . implode(' AND ', $where) . '
    ORDER BY po.created_at DESC
');
$stmt->execute($params);
$applications = $stmt->fetchAll();

$pageTitle = 'Mes candidatures — UPC Freelance';
$appLayout = true;
require_once '../../includes/header.php';
?>
<br>
<?php renderFlash(); ?>

<div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
    <div>
        <h1 class="text-2xl font-bold text-primary">Mes candidatures</h1>
        <p class="text-on-surface-variant text-sm mt-1"><?= count($applications) ?> candidature<?= count($applications) > 1 ? 's' : '' ?></p>
    </div>
    <a href="/upc_freelance/app/projects/list.php"
       class="inline-flex items-center gap-2 bg-primary text-white font-button text-button px-5 py-2.5 rounded-xl hover:opacity-90 transition-opacity active:scale-95">
        <span class="material-symbols-outlined">search</span> Trouver des projets
    </a>
</div>

<!-- Onglets -->
<div class="flex flex-wrap gap-2 mb-6">
    <?php foreach (['' => 'Toutes', 'pending' => 'En attente', 'accepted' => 'Acceptées', 'rejected' => 'Refusées'] as $val => $label): ?>
    <a href="?status=<?= $val ?>"
       class="px-4 py-2 rounded-full text-sm font-medium transition-all <?= $status === $val ? 'bg-primary text-white shadow-sm' : 'bg-white border border-slate-200 text-on-surface-variant hover:border-secondary hover:text-secondary' ?>">
        <?= $label ?>
    </a>
    <?php endforeach; ?>
</div>

<?php if (empty($applications)): ?>
<div class="bg-white rounded-2xl border border-slate-100 p-16 text-center custom-shadow-low">
    <span class="material-symbols-outlined text-5xl text-slate-300 block mb-4">send</span>
    <h3 class="font-semibold text-primary mb-2">Aucune candidature</h3>
    <p class="text-on-surface-variant text-sm mb-6">Parcourez les projets disponibles et postulez dès maintenant.</p>
    <a href="/upc_freelance/app/projects/list.php" class="inline-block bg-primary text-white px-6 py-3 rounded-xl text-sm font-button hover:opacity-90">
        Voir les projets
    </a>
</div>
<?php else: ?>
<div class="grid grid-cols-1 gap-4">
    <?php foreach ($applications as $a):
        $sc = ['pending'=>'amber','accepted'=>'green','rejected'=>'red','withdrawn'=>'gray'][$a['status']] ?? 'gray';
        $sl = ['pending'=>'En attente','accepted'=>'Acceptée','rejected'=>'Refusée','withdrawn'=>'Retirée'][$a['status']] ?? $a['status'];
    ?>
    <div class="bg-white rounded-2xl border border-slate-100 p-5 custom-shadow-low hover:border-secondary/30 transition-all">
        <div class="flex items-start gap-4">
            <?= renderAvatar($a['client_avatar'] ?? null, $a['client_fname'], $a['client_lname'], (bool)($a['client_verified'] ?? false), 'w-12 h-12', 'rounded-xl') ?>
            <div class="flex-1 min-w-0">
                <div class="flex items-start justify-between gap-2 mb-1">
                    <a href="/upc_freelance/app/projects/details.php?id=<?= $a['project_id'] ?>"
                       class="font-semibold text-primary hover:text-secondary transition-colors leading-snug">
                        <?= h($a['project_title']) ?>
                    </a>
                    <span class="inline-block text-xs bg-<?= $sc ?>-100 text-<?= $sc ?>-700 px-2.5 py-1 rounded-full font-medium whitespace-nowrap">
                        <?= $sl ?>
                    </span>
                </div>
                <p class="text-xs text-on-surface-variant mb-3">
                    Client : <?= h($a['client_fname'] . ' ' . $a['client_lname']) ?>
                    · <?= h($a['category_name'] ?? '') ?>
                    · Postulé <?= timeAgo($a['created_at']) ?>
                </p>

                <!-- Ma proposition -->
                <div class="flex flex-wrap gap-4 text-sm mb-3">
                    <div>
                        <p class="text-xs text-slate-400">Ma proposition</p>
                        <p class="font-bold text-secondary"><?= money((float)$a['proposed_price']) ?></p>
                    </div>
                    <?php if ($a['proposed_days']): ?>
                    <div>
                        <p class="text-xs text-slate-400">Délai proposé</p>
                        <p class="font-medium text-primary"><?= $a['proposed_days'] ?> jours</p>
                    </div>
                    <?php endif; ?>
                    <?php if ($a['budget_max'] || $a['budget_min']): ?>
                    <div>
                        <p class="text-xs text-slate-400">Budget client</p>
                        <p class="font-medium text-primary"><?= money((float)($a['budget_max'] ?? $a['budget_min'])) ?></p>
                    </div>
                    <?php endif; ?>
                </div>

                <p class="text-sm text-on-surface-variant italic line-clamp-2 border-l-2 border-secondary/30 pl-3">
                    <?= h(truncate($a['cover_letter'], 150)) ?>
                </p>

                <?php if ($a['status'] === 'accepted'): ?>
                <div class="mt-3 p-3 bg-green-50 rounded-xl border border-green-200">
                    <p class="text-xs text-green-700 font-medium flex items-center gap-1">
                        <span class="material-symbols-outlined text-sm">check_circle</span>
                        Félicitations ! Votre candidature a été acceptée. Un contrat a été créé.
                    </p>
                    <a href="/upc_freelance/app/contracts/list.php" class="text-xs text-green-600 hover:underline mt-1 inline-block">
                        Voir mes contrats →
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php
$appLayout = true;
require_once '../../includes/footer.php';
?>