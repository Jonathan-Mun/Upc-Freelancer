<?php
// ============================================================
// UPC FREELANCE — Mes projets (client)
// /var/www/html/upc_freelance/app/projects/my-projects.php
// ============================================================

require_once '/var/www/html/upc_freelance/includes/middleware.php';
require_once '/var/www/html/upc_freelance/includes/auth.php';
require_once '/var/www/html/upc_freelance/includes/functions.php';
require_once '/var/www/html/upc_freelance/includes/db.php';

requireRole('client');

$user   = currentUser();
$pdo    = getDB();
$status = sanitize($_GET['status'] ?? '');

$where  = ['p.client_id = ?'];
$params = [$user['id']];
if ($status && in_array($status, ['open','in_progress','completed','cancelled'])) {
    $where[]  = 'p.status = ?';
    $params[] = $status;
}

$stmt = $pdo->prepare('
    SELECT p.*, c.name AS category_name,
           (SELECT COUNT(*) FROM postulations WHERE project_id = p.id) AS nb_postulations,
           (SELECT COUNT(*) FROM postulations WHERE project_id = p.id AND status = "pending") AS nb_pending
    FROM projects p
    LEFT JOIN categories c ON c.id = p.category_id
    WHERE ' . implode(' AND ', $where) . '
    ORDER BY p.created_at DESC
');
$stmt->execute($params);
$projects = $stmt->fetchAll();

$pageTitle = 'Mes projets — UPC Freelance';
$appLayout = true;
require_once '/var/www/html/upc_freelance/includes/header.php';
?>

<?php renderFlash(); ?>

<!-- En-tête -->
<div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
    <div>
        <h1 class="text-2xl font-bold text-primary">Mes projets</h1>
        <p class="text-on-surface-variant text-sm mt-1"><?= count($projects) ?> projet<?= count($projects) > 1 ? 's' : '' ?></p>
    </div>
    <a href="/upc_freelance/app/projects/create.php"
       class="inline-flex items-center gap-2 bg-primary text-white font-button text-button px-5 py-2.5 rounded-xl hover:opacity-90 transition-opacity active:scale-95">
        <span class="material-symbols-outlined">add</span> Nouveau projet
    </a>
</div>

<!-- Filtres statut -->
<div class="flex flex-wrap gap-2 mb-6">
    <?php
    $tabs = ['' => 'Tous', 'open' => 'Ouverts', 'in_progress' => 'En cours', 'completed' => 'Terminés', 'cancelled' => 'Annulés'];
    foreach ($tabs as $val => $label):
    ?>
    <a href="?status=<?= $val ?>"
       class="px-4 py-2 rounded-full text-sm font-medium transition-all <?= $status === $val ? 'bg-primary text-white shadow-sm' : 'bg-white border border-slate-200 text-on-surface-variant hover:border-secondary hover:text-secondary' ?>">
        <?= $label ?>
    </a>
    <?php endforeach; ?>
</div>

<?php if (empty($projects)): ?>
<div class="bg-white rounded-2xl border border-slate-100 p-16 text-center custom-shadow-low">
    <span class="material-symbols-outlined text-5xl text-slate-300 block mb-4">folder_open</span>
    <h3 class="font-semibold text-primary mb-2">Aucun projet</h3>
    <p class="text-on-surface-variant text-sm mb-6">Publiez votre premier projet pour recevoir des candidatures.</p>
    <a href="/upc_freelance/app/projects/create.php"
       class="inline-block bg-primary text-white font-button text-button px-6 py-3 rounded-xl hover:opacity-90 transition-opacity">
        Créer un projet
    </a>
</div>
<?php else: ?>
<div class="grid grid-cols-1 gap-4">
    <?php foreach ($projects as $p):
        $sc = ['open'=>'green','in_progress'=>'blue','completed'=>'gray','cancelled'=>'red'][$p['status']] ?? 'gray';
        $sl = ['open'=>'Ouvert','in_progress'=>'En cours','completed'=>'Terminé','cancelled'=>'Annulé'][$p['status']] ?? $p['status'];
    ?>
    <div class="bg-white rounded-2xl border border-slate-100 hover:border-secondary/30 hover:shadow-md transition-all p-5 custom-shadow-low">
        <div class="flex items-start gap-4">
            <div class="w-12 h-12 rounded-xl bg-surface-container flex items-center justify-center flex-shrink-0">
                <span class="material-symbols-outlined text-secondary">work</span>
            </div>
            <div class="flex-1 min-w-0">
                <div class="flex items-start justify-between gap-2 mb-1">
                    <h3 class="font-semibold text-primary leading-snug"><?= h($p['title']) ?></h3>
                    <span class="inline-block text-xs bg-<?= $sc ?>-100 text-<?= $sc ?>-700 px-2.5 py-1 rounded-full font-medium whitespace-nowrap">
                        <?= $sl ?>
                    </span>
                </div>
                <p class="text-sm text-on-surface-variant line-clamp-2 mb-3"><?= h(truncate($p['description'], 120)) ?></p>
                <div class="flex flex-wrap items-center gap-4 text-xs text-slate-400">
                    <?php if ($p['category_name']): ?>
                    <span class="flex items-center gap-1">
                        <span class="material-symbols-outlined text-sm">label</span>
                        <?= h($p['category_name']) ?>
                    </span>
                    <?php endif; ?>
                    <span class="flex items-center gap-1">
                        <span class="material-symbols-outlined text-sm">group</span>
                        <?= $p['nb_postulations'] ?> candidature<?= $p['nb_postulations'] > 1 ? 's' : '' ?>
                        <?php if ($p['nb_pending'] > 0): ?>
                        <span class="bg-amber-100 text-amber-700 px-1.5 py-0.5 rounded-full font-medium"><?= $p['nb_pending'] ?> en attente</span>
                        <?php endif; ?>
                    </span>
                    <?php if ($p['budget_max'] || $p['budget_min']): ?>
                    <span class="flex items-center gap-1 text-secondary font-semibold">
                        <span class="material-symbols-outlined text-sm">payments</span>
                        <?= money((float)($p['budget_max'] ?? $p['budget_min'])) ?>
                    </span>
                    <?php endif; ?>
                    <span><?= timeAgo($p['created_at']) ?></span>
                </div>
            </div>
            <div class="flex flex-col gap-2 flex-shrink-0">
                <a href="/upc_freelance/app/projects/details.php?id=<?= $p['id'] ?>"
                   class="px-3 py-1.5 text-xs border border-secondary text-secondary rounded-lg hover:bg-secondary/5 transition-colors whitespace-nowrap">
                    Voir
                </a>
                <?php if ($p['status'] === 'open'): ?>
                <a href="/upc_freelance/app/projects/edit.php?id=<?= $p['id'] ?>"
                   class="px-3 py-1.5 text-xs border border-slate-200 text-on-surface-variant rounded-lg hover:border-slate-300 transition-colors whitespace-nowrap">
                    Modifier
                </a>
                <?php endif; ?>
                <?php if ($p['nb_postulations'] > 0): ?>
                <a href="/upc_freelance/app/postulations/received.php?project_id=<?= $p['id'] ?>"
                   class="px-3 py-1.5 text-xs bg-amber-50 text-amber-700 rounded-lg hover:bg-amber-100 transition-colors whitespace-nowrap">
                    <?= $p['nb_postulations'] ?> candidature<?= $p['nb_postulations'] > 1 ? 's' : '' ?>
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php
$appLayout = true;
require_once '/var/www/html/upc_freelance/includes/footer.php';
?>
