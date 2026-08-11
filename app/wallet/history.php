<?php
// ============================================================
// UPC FREELANCE — Historique complet des transactions
// ../../app/wallet/history.php
// ============================================================

require_once '../../includes/middleware.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../includes/db.php';

requireLogin();

$user    = currentUser();
$pdo     = getDB();
$type    = sanitize($_GET['type'] ?? '');
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;

$where  = ['t.user_id = ?'];
$params = [$user['id']];
if ($type && in_array($type, ['deposit','withdrawal','payment','refund','lock','unlock'])) {
    $where[]  = 't.type = ?';
    $params[] = $type;
}
$wc = implode(' AND ', $where);

$cstmt = $pdo->prepare("SELECT COUNT(*) FROM transactions t WHERE $wc");
$cstmt->execute($params);
$total      = (int)$cstmt->fetchColumn();
$totalPages = (int)ceil($total / $perPage);
$offset     = ($page - 1) * $perPage;

$stmt = $pdo->prepare("
    SELECT t.*, p.title AS project_title
    FROM transactions t
    LEFT JOIN contracts c ON c.id = t.contract_id
    LEFT JOIN projects  p ON p.id = c.project_id
    WHERE $wc
    ORDER BY t.created_at DESC
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$transactions = $stmt->fetchAll();

$wallet = getUserWallet($user['id']);

$pageTitle = 'Historique des transactions — UPC Freelance';
$appLayout = true;
require_once '../../includes/header.php';
?>
<br>
<div class="flex items-center justify-between mb-8">
    <div>
        <a href="/upc_freelance/app/wallet/index.php" class="inline-flex items-center gap-1 text-sm text-secondary hover:underline mb-2">
            <span class="material-symbols-outlined text-base">arrow_back</span> Mon wallet
        </a>
        <h1 class="text-2xl font-bold text-primary">Historique des transactions</h1>
        <p class="text-on-surface-variant text-sm mt-1"><?= $total ?> transaction<?= $total > 1 ? 's' : '' ?> · Solde : <strong><?= money((float)$wallet['balance']) ?></strong></p>
    </div>
</div>

<!-- Filtres -->
<div class="flex flex-wrap gap-2 mb-6">
    <?php
    $tabs = [
        ''           => 'Toutes',
        'deposit'    => 'Dépôts',
        'withdrawal' => 'Retraits',
        'payment'    => 'Paiements',
        'refund'     => 'Remboursements',
        'lock'       => 'Bloqués',
        'unlock'     => 'Libérés',
    ];
    foreach ($tabs as $val => $label):
    ?>
    <a href="?type=<?= $val ?>"
       class="px-4 py-2 rounded-full text-sm font-medium transition-all <?= $type === $val ? 'bg-primary text-white shadow-sm' : 'bg-white border border-slate-200 text-on-surface-variant hover:border-secondary hover:text-secondary' ?>">
        <?= $label ?>
    </a>
    <?php endforeach; ?>
</div>

<?php if (empty($transactions)): ?>
<div class="bg-white rounded-2xl border border-slate-100 p-16 text-center custom-shadow-low">
    <span class="material-symbols-outlined text-5xl text-slate-300 block mb-4">receipt_long</span>
    <h3 class="font-semibold text-primary mb-2">Aucune transaction</h3>
    <p class="text-on-surface-variant text-sm">Aucune transaction trouvée pour ce filtre.</p>
</div>
<?php else: ?>
<div class="bg-white rounded-2xl border border-slate-100 custom-shadow-low overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-slate-100 bg-slate-50">
                    <th class="text-left px-5 py-3 text-xs font-semibold text-slate-500 uppercase">Type</th>
                    <th class="text-left px-5 py-3 text-xs font-semibold text-slate-500 uppercase">Description</th>
                    <th class="text-right px-5 py-3 text-xs font-semibold text-slate-500 uppercase">Montant</th>
                    <th class="text-right px-5 py-3 text-xs font-semibold text-slate-500 uppercase">Solde après</th>
                    <th class="text-left px-5 py-3 text-xs font-semibold text-slate-500 uppercase">Statut</th>
                    <th class="text-left px-5 py-3 text-xs font-semibold text-slate-500 uppercase">Date</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                <?php
                $typeLabels = [
                    'deposit'    => ['label'=>'Rechargement',   'icon'=>'add_circle',    'credit'=>true],
                    'withdrawal' => ['label'=>'Retrait',         'icon'=>'remove_circle', 'credit'=>false],
                    'payment'    => ['label'=>'Paiement',        'icon'=>'payments',      'credit'=>$user['role']==='freelancer'],
                    'refund'     => ['label'=>'Remboursement',   'icon'=>'undo',          'credit'=>true],
                    'lock'       => ['label'=>'Montant bloqué',  'icon'=>'lock',          'credit'=>false],
                    'unlock'     => ['label'=>'Montant libéré',  'icon'=>'lock_open',     'credit'=>true],
                ];
                foreach ($transactions as $tx):
                    $ti = $typeLabels[$tx['type']] ?? ['label'=>$tx['type'],'icon'=>'swap_horiz','credit'=>true];
                ?>
                <tr class="hover:bg-slate-50 transition-colors">
                    <td class="px-5 py-3.5">
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-8 rounded-full <?= $ti['credit'] ? 'bg-green-50' : 'bg-red-50' ?> flex items-center justify-center flex-shrink-0">
                                <span class="material-symbols-outlined text-sm <?= $ti['credit'] ? 'text-green-500' : 'text-red-400' ?>"><?= $ti['icon'] ?></span>
                            </div>
                            <span class="font-medium text-primary"><?= $ti['label'] ?></span>
                        </div>
                    </td>
                    <td class="px-5 py-3.5 text-on-surface-variant max-w-[200px]">
                        <p class="truncate"><?= h($tx['description'] ?? ($tx['project_title'] ? 'Projet : ' . $tx['project_title'] : '—')) ?></p>
                    </td>
                    <td class="px-5 py-3.5 text-right font-bold <?= $ti['credit'] ? 'text-green-600' : 'text-red-500' ?>">
                        <?= $ti['credit'] ? '+' : '-' ?><?= money((float)$tx['amount']) ?>
                    </td>
                    <td class="px-5 py-3.5 text-right text-slate-600"><?= money((float)$tx['balance_after']) ?></td>
                    <td class="px-5 py-3.5">
                        <span class="inline-block text-xs px-2 py-0.5 rounded-full font-medium
                            <?= $tx['status'] === 'completed' ? 'bg-green-100 text-green-700' : ($tx['status'] === 'pending' ? 'bg-amber-100 text-amber-700' : 'bg-red-100 text-red-600') ?>">
                            <?= match($tx['status']) { 'completed'=>'Complété', 'pending'=>'En attente', 'failed'=>'Échoué', default=>$tx['status'] } ?>
                        </span>
                    </td>
                    <td class="px-5 py-3.5 text-slate-500 whitespace-nowrap"><?= formatDate($tx['created_at'], 'd/m/Y H:i') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div class="flex justify-center gap-2 p-4 border-t border-slate-100">
        <?php if ($page > 1): ?>
        <a href="?type=<?= $type ?>&page=<?= $page - 1 ?>"
           class="px-4 py-2 rounded-xl border border-slate-200 text-sm hover:border-secondary hover:text-secondary transition-colors">← Précédent</a>
        <?php endif; ?>
        <?php for ($i = max(1,$page-2); $i <= min($totalPages,$page+2); $i++): ?>
        <a href="?type=<?= $type ?>&page=<?= $i ?>"
           class="px-4 py-2 rounded-xl border text-sm transition-colors <?= $i === $page ? 'bg-primary text-white border-primary' : 'border-slate-200 hover:border-secondary hover:text-secondary' ?>">
            <?= $i ?>
        </a>
        <?php endfor; ?>
        <?php if ($page < $totalPages): ?>
        <a href="?type=<?= $type ?>&page=<?= $page + 1 ?>"
           class="px-4 py-2 rounded-xl border border-slate-200 text-sm hover:border-secondary hover:text-secondary transition-colors">Suivant →</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php $appLayout = true; require_once '../../includes/footer.php'; ?>
