<?php
// ============================================================
// UPC FREELANCE — Wallet / Portefeuille
// /var/www/html/upc_freelance/app/wallet/index.php
// ============================================================

require_once '/var/www/html/upc_freelance/includes/middleware.php';
require_once '/var/www/html/upc_freelance/includes/auth.php';
require_once '/var/www/html/upc_freelance/includes/functions.php';
require_once '/var/www/html/upc_freelance/includes/db.php';

requireLogin();

$user   = currentUser();
$pdo    = getDB();
$wallet = getUserWallet($user['id']);

// Dernières transactions
$stmt = $pdo->prepare('
    SELECT t.*, c.id AS contract_id_ref, p.title AS project_title
    FROM transactions t
    LEFT JOIN contracts c ON c.id = t.contract_id
    LEFT JOIN projects  p ON p.id = c.project_id
    WHERE t.user_id = ?
    ORDER BY t.created_at DESC LIMIT 10
');
$stmt->execute([$user['id']]);
$transactions = $stmt->fetchAll();

// Stats
$stmt = $pdo->prepare('SELECT COALESCE(SUM(amount),0) FROM transactions WHERE user_id = ? AND type = "deposit"');
$stmt->execute([$user['id']]); $totalDeposited = (float)$stmt->fetchColumn();

$stmt = $pdo->prepare('SELECT COALESCE(SUM(amount),0) FROM transactions WHERE user_id = ? AND type = "withdrawal"');
$stmt->execute([$user['id']]); $totalWithdrawn = (float)$stmt->fetchColumn();

$stmt = $pdo->prepare('SELECT COALESCE(SUM(amount),0) FROM transactions WHERE user_id = ? AND type = "payment"');
$stmt->execute([$user['id']]); $totalPayments = (float)$stmt->fetchColumn();

$pageTitle = 'Mon Wallet — UPC Freelance';
$appLayout = true;
require_once '/var/www/html/upc_freelance/includes/header.php';
?>

<?php renderFlash(); ?>

<div class="mb-8">
    <h1 class="text-2xl font-bold text-primary">Mon Wallet</h1>
    <p class="text-on-surface-variant text-sm mt-1">Gérez vos fonds et transactions</p>
</div>

<!-- ── Cartes solde ────────────────────────────────────────── -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-5 mb-8">

    <!-- Solde disponible -->
    <div class="md:col-span-1 bg-primary text-white rounded-2xl p-6 custom-shadow-high">
        <div class="flex items-center justify-between mb-4">
            <p class="text-blue-200 text-xs font-label-caps uppercase tracking-widest">Solde disponible</p>
            <span class="material-symbols-outlined text-blue-200">account_balance_wallet</span>
        </div>
        <p class="text-4xl font-bold mb-1"><?= money((float)$wallet['balance']) ?></p>
        <?php if ($wallet['locked'] > 0): ?>
        <p class="text-sm text-blue-300 flex items-center gap-1">
            <span class="material-symbols-outlined text-base">lock</span>
            <?= money((float)$wallet['locked']) ?> bloqué(s)
        </p>
        <?php endif; ?>
        <div class="flex gap-3 mt-5">
            <a href="/upc_freelance/app/wallet/deposit.php"
               class="flex-1 text-center bg-white text-primary text-xs font-button py-2.5 rounded-xl hover:bg-blue-50 transition-colors active:scale-95">
                Recharger
            </a>
            <a href="/upc_freelance/app/wallet/withdraw.php"
               class="flex-1 text-center bg-white/10 text-white text-xs font-button py-2.5 rounded-xl hover:bg-white/20 transition-colors active:scale-95 border border-white/20">
                Retirer
            </a>
        </div>
    </div>

    <!-- Stats -->
    <div class="bg-white rounded-2xl border border-slate-100 p-5 custom-shadow-low">
        <div class="flex items-center justify-between mb-3">
            <p class="text-xs text-on-surface-variant font-label-caps uppercase">Total rechargé</p>
            <span class="material-symbols-outlined text-green-500">add_circle</span>
        </div>
        <p class="text-2xl font-bold text-primary"><?= money($totalDeposited) ?></p>
    </div>
    <div class="bg-white rounded-2xl border border-slate-100 p-5 custom-shadow-low">
        <div class="flex items-center justify-between mb-3">
            <p class="text-xs text-on-surface-variant font-label-caps uppercase">
                <?= $user['role'] === 'client' ? 'Total payé' : 'Total gagné' ?>
            </p>
            <span class="material-symbols-outlined <?= $user['role'] === 'freelancer' ? 'text-secondary' : 'text-amber-500' ?>">payments</span>
        </div>
        <p class="text-2xl font-bold text-primary"><?= money($totalPayments) ?></p>
    </div>
</div>

<!-- ── Historique transactions ────────────────────────────── -->
<div class="bg-white rounded-2xl border border-slate-100 custom-shadow-low overflow-hidden">
    <div class="flex justify-between items-center p-5 border-b border-slate-100">
        <h2 class="font-semibold text-primary">Historique des transactions</h2>
        <a href="/upc_freelance/app/wallet/history.php" class="text-xs text-secondary hover:underline">Voir tout</a>
    </div>

    <?php if (empty($transactions)): ?>
    <div class="p-12 text-center">
        <span class="material-symbols-outlined text-4xl text-slate-300 block mb-3">receipt_long</span>
        <p class="text-on-surface-variant text-sm">Aucune transaction pour le moment.</p>
    </div>
    <?php else: ?>
    <div class="divide-y divide-slate-50">
        <?php foreach ($transactions as $tx):
            $isCredit = in_array($tx['type'], ['deposit','payment','unlock','refund']);
            $typeLabels = [
                'deposit'    => 'Rechargement',
                'withdrawal' => 'Retrait',
                'payment'    => $user['role'] === 'freelancer' ? 'Paiement reçu' : 'Paiement envoyé',
                'refund'     => 'Remboursement',
                'lock'       => 'Montant bloqué',
                'unlock'     => 'Montant libéré',
            ];
            $typeIcons = [
                'deposit'    => ['icon'=>'add_circle',  'color'=>'text-green-500'],
                'withdrawal' => ['icon'=>'remove_circle','color'=>'text-red-500'],
                'payment'    => ['icon'=>'payments',    'color'=>$isCredit?'text-blue-500':'text-orange-500'],
                'refund'     => ['icon'=>'undo',        'color'=>'text-green-500'],
                'lock'       => ['icon'=>'lock',        'color'=>'text-amber-500'],
                'unlock'     => ['icon'=>'lock_open',   'color'=>'text-green-500'],
            ];
            $ti = $typeIcons[$tx['type']] ?? ['icon'=>'swap_horiz','color'=>'text-slate-400'];
        ?>
        <div class="flex items-center gap-4 p-4">
            <div class="w-10 h-10 rounded-full bg-slate-50 flex items-center justify-center flex-shrink-0">
                <span class="material-symbols-outlined <?= $ti['color'] ?>"><?= $ti['icon'] ?></span>
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-primary"><?= $typeLabels[$tx['type']] ?? $tx['type'] ?></p>
                <p class="text-xs text-on-surface-variant">
                    <?= $tx['project_title'] ? h(truncate($tx['project_title'], 40)) . ' · ' : '' ?>
                    <?= formatDate($tx['created_at'], 'd/m/Y H:i') ?>
                </p>
            </div>
            <div class="text-right">
                <p class="font-bold <?= $isCredit ? 'text-green-600' : 'text-red-500' ?>">
                    <?= $isCredit ? '+' : '-' ?><?= money((float)$tx['amount']) ?>
                </p>
                <p class="text-xs text-slate-400">Solde: <?= money((float)$tx['balance_after']) ?></p>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<?php
$appLayout = true;
require_once '/var/www/html/upc_freelance/includes/footer.php';
?>
