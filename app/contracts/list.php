<?php
// ============================================================
// UPC FREELANCE — Liste des contrats
// ../../app/contracts/list.php
// ============================================================

require_once '../../includes/middleware.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../includes/db.php';

requireLogin();

$user   = currentUser();
$pdo    = getDB();
$status = sanitize($_GET['status'] ?? '');
$userId = $user['id'];

$where  = ['(c.client_id = ? OR c.freelancer_id = ?)'];
$params = [$userId, $userId];
if ($status && in_array($status, ['active','completed','cancelled','disputed'])) {
    $where[]  = 'c.status = ?';
    $params[] = $status;
}

$stmt = $pdo->prepare('
    SELECT c.*,
           p.title AS project_title,
           cl.first_name AS client_fname, cl.last_name AS client_lname,
           fr.first_name AS freelancer_fname, fr.last_name AS freelancer_lname,
           (SELECT COUNT(*) FROM messages m WHERE m.contract_id = c.id AND m.sender_id != ? AND m.is_read = 0) AS unread_msgs
    FROM contracts c
    JOIN projects p   ON p.id  = c.project_id
    JOIN users cl     ON cl.id = c.client_id
    JOIN users fr     ON fr.id = c.freelancer_id
    WHERE ' . implode(' AND ', $where) . '
    ORDER BY c.created_at DESC
');
$stmt->execute(array_merge([$userId], $params));
$contracts = $stmt->fetchAll();

$pageTitle = 'Mes contrats — UPC Freelance';
$appLayout = true;
require_once '../../includes/header.php';
?>

<?php renderFlash(); ?>

<div class="flex items-center justify-between mb-8">
    <div>
        <h1 class="text-2xl font-bold text-primary">Mes contrats</h1>
        <p class="text-on-surface-variant text-sm mt-1"><?= count($contracts) ?> contrat<?= count($contracts) > 1 ? 's' : '' ?></p>
    </div>
</div>

<!-- Onglets -->
<div class="flex flex-wrap gap-2 mb-6">
    <?php foreach (['' => 'Tous', 'active' => 'Actifs', 'completed' => 'Terminés', 'cancelled' => 'Annulés'] as $val => $label): ?>
    <a href="?status=<?= $val ?>"
       class="px-4 py-2 rounded-full text-sm font-medium transition-all <?= $status === $val ? 'bg-primary text-white shadow-sm' : 'bg-white border border-slate-200 text-on-surface-variant hover:border-secondary hover:text-secondary' ?>">
        <?= $label ?>
    </a>
    <?php endforeach; ?>
</div>

<?php if (empty($contracts)): ?>
<div class="bg-white rounded-2xl border border-slate-100 p-16 text-center custom-shadow-low">
    <span class="material-symbols-outlined text-5xl text-slate-300 block mb-4">description</span>
    <h3 class="font-semibold text-primary mb-2">Aucun contrat</h3>
    <p class="text-on-surface-variant text-sm">
        <?= $user['role'] === 'client' ? 'Acceptez une candidature pour créer un contrat.' : 'Postule à des projets pour décrocher ton premier contrat.' ?>
    </p>
</div>
<?php else: ?>
<div class="grid grid-cols-1 gap-4">
    <?php foreach ($contracts as $c):
        $sc = ['active'=>'green','completed'=>'blue','cancelled'=>'red','disputed'=>'amber'][$c['status']] ?? 'gray';
        $sl = ['active'=>'Actif','completed'=>'Terminé','cancelled'=>'Annulé','disputed'=>'Litige'][$c['status']] ?? $c['status'];
        $partner = $user['id'] === $c['client_id']
            ? $c['freelancer_fname'] . ' ' . $c['freelancer_lname']
            : $c['client_fname'] . ' ' . $c['client_lname'];
        $partnerRole = $user['id'] === $c['client_id'] ? 'Freelancer' : 'Client';
    ?>
    <a href="/upc_freelance/app/contracts/details.php?id=<?= $c['id'] ?>"
       class="group block bg-white rounded-2xl border border-slate-100 hover:border-secondary/40 hover:shadow-md transition-all p-5 custom-shadow-low">
        <div class="flex items-start gap-4">
            <div class="w-12 h-12 rounded-xl bg-secondary/10 flex items-center justify-center flex-shrink-0">
                <span class="material-symbols-outlined text-secondary">description</span>
            </div>
            <div class="flex-1 min-w-0">
                <div class="flex items-start justify-between gap-2 mb-1">
                    <h3 class="font-semibold text-primary group-hover:text-secondary transition-colors leading-snug">
                        <?= h($c['project_title']) ?>
                    </h3>
                    <div class="flex items-center gap-2 flex-shrink-0">
                        <?php if ($c['unread_msgs'] > 0): ?>
                        <span class="bg-secondary text-white text-xs px-2 py-0.5 rounded-full font-bold">
                            <?= $c['unread_msgs'] ?> msg
                        </span>
                        <?php endif; ?>
                        <span class="inline-block text-xs bg-<?= $sc ?>-100 text-<?= $sc ?>-700 px-2.5 py-1 rounded-full font-medium">
                            <?= $sl ?>
                        </span>
                    </div>
                </div>
                <p class="text-xs text-on-surface-variant mb-3">
                    <?= $partnerRole ?> : <strong><?= h($partner) ?></strong>
                    · Débuté le <?= formatDate($c['start_date'] ?? $c['created_at']) ?>
                    <?= $c['end_date'] ? '· Fin : ' . formatDate($c['end_date']) : '' ?>
                </p>
                <div class="flex items-center gap-6 text-sm">
                    <div>
                        <p class="text-xs text-slate-400">Montant</p>
                        <p class="font-bold text-secondary"><?= money((float)$c['amount']) ?></p>
                    </div>
                    <div class="flex gap-3 text-xs text-slate-500">
                        <span class="flex items-center gap-1">
                            <span class="material-symbols-outlined text-sm">chat</span> Messages
                        </span>
                        <span class="flex items-center gap-1">
                            <span class="material-symbols-outlined text-sm">open_in_new</span> Voir le contrat
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </a>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php
$appLayout = true;
require_once '../../includes/footer.php';
?>
