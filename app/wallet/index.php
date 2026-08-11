<?php
// ============================================================
// UPC FREELANCE — Wallet / Portefeuille
// app/wallet/index.php
// ============================================================

require_once '../../includes/middleware.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../includes/db.php';

requireLogin();

$user   = currentUser();
$pdo    = getDB();
$wallet = getUserWallet($user['id']);

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

$stmt = $pdo->prepare('SELECT COALESCE(SUM(amount),0) FROM transactions WHERE user_id = ? AND type = "deposit"');
$stmt->execute([$user['id']]); $totalDeposited = (float)$stmt->fetchColumn();

$stmt = $pdo->prepare('SELECT COALESCE(SUM(amount),0) FROM transactions WHERE user_id = ? AND type = "withdrawal"');
$stmt->execute([$user['id']]); $totalWithdrawn = (float)$stmt->fetchColumn();

$stmt = $pdo->prepare('SELECT COALESCE(SUM(amount),0) FROM transactions WHERE user_id = ? AND type = "payment"');
$stmt->execute([$user['id']]); $totalPayments = (float)$stmt->fetchColumn();

$pageTitle = 'Mon Wallet — UPC Freelance';
$appLayout = true;
require_once '../../includes/header.php';
?>

<style>
.wallet-card-gradient {
    background: linear-gradient(135deg, #1e3a5f 0%, #2563eb 60%, #3b82f6 100%);
    position: relative;
    overflow: hidden;
}
.wallet-card-gradient::before {
    content: '';
    position: absolute;
    top: -40px; right: -40px;
    width: 180px; height: 180px;
    border-radius: 50%;
    background: rgba(255,255,255,0.07);
}
.wallet-card-gradient::after {
    content: '';
    position: absolute;
    bottom: -60px; left: -20px;
    width: 200px; height: 200px;
    border-radius: 50%;
    background: rgba(255,255,255,0.05);
}
.stat-card {
    background: #fff;
    border: 1.5px solid #f1f5f9;
    border-radius: 1.25rem;
    padding: 1.25rem;
    transition: box-shadow .2s, border-color .2s;
}
.stat-card:hover { box-shadow: 0 4px 20px rgba(0,0,0,.07); border-color: #e2e8f0; }
.tx-row { transition: background .15s; }
.tx-row:hover { background: #f8fafc; }
.pill-btn {
    display: inline-flex; align-items: center; justify-content: center; gap: .35rem;
    font-size: .75rem; font-weight: 600; padding: .55rem 1.1rem;
    border-radius: .75rem; border: none; cursor: pointer;
    transition: opacity .15s, transform .1s;
}
.pill-btn:active { transform: scale(.96); }
</style>
<br>
<div class="pt-2 pb-8">
    <?php renderFlash(); ?>

    <!-- ── Header ────────────────────────────────────────── -->
    <div class="flex items-center justify-between mb-7">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">Mon Wallet</h1>
            <p class="text-slate-400 text-sm mt-0.5">Gérez vos fonds et transactions</p>
        </div>
        <a href="/upc_freelance/app/wallet/history.php"
           class="hidden sm:inline-flex items-center gap-1.5 text-xs font-semibold text-secondary border border-secondary/30 px-4 py-2 rounded-xl hover:bg-secondary/5 transition-colors">
            <span class="material-symbols-outlined text-base">receipt_long</span>
            Historique complet
        </a>
    </div>

    <!-- ── Grille principale ─────────────────────────────── -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 mb-7">

        <!-- Carte solde -->
        <div class="wallet-card-gradient text-white rounded-2xl p-6 shadow-lg lg:col-span-1">
            <div class="relative z-10">
                <div class="flex items-center justify-between mb-5">
                    <div>
                        <p class="text-blue-200 text-xs font-semibold uppercase tracking-widest mb-1">Solde disponible</p>
                        <p class="text-4xl font-black tracking-tight"><?= money((float)$wallet['balance']) ?></p>
                    </div>
                    <div class="w-12 h-12 rounded-2xl bg-white/10 flex items-center justify-center">
                        <span class="material-symbols-outlined text-2xl text-blue-100">account_balance_wallet</span>
                    </div>
                </div>

                <?php if ($wallet['locked'] > 0): ?>
                <div class="flex items-center gap-1.5 bg-white/10 rounded-xl px-3 py-2 mb-4 w-fit">
                    <span class="material-symbols-outlined text-sm text-blue-200">lock</span>
                    <span class="text-xs text-blue-100 font-medium"><?= money((float)$wallet['locked']) ?> en attente</span>
                </div>
                <?php endif; ?>

                <!-- Boutons actions -->
                <div class="flex gap-3 mt-4">
                    <button onclick="openDepositModal()"
                            class="pill-btn flex-1 bg-white text-primary hover:opacity-90">
                        <span class="material-symbols-outlined text-base">add_circle</span>
                        Recharger
                    </button>
                    <button onclick="openWithdrawModal()"
                            class="pill-btn flex-1 bg-white/15 text-white border border-white/20 hover:bg-white/25">
                        <span class="material-symbols-outlined text-base">south</span>
                        Retirer
                    </button>
                </div>
            </div>
        </div>

        <!-- Stats -->
        <div class="lg:col-span-2 grid grid-cols-1 sm:grid-cols-3 gap-4">

            <div class="stat-card">
                <div class="flex items-center justify-between mb-3">
                    <p class="text-xs font-semibold text-slate-400 uppercase tracking-widest">Total rechargé</p>
                    <div class="w-8 h-8 rounded-xl bg-emerald-50 flex items-center justify-center">
                        <span class="material-symbols-outlined text-emerald-500 text-base">add_circle</span>
                    </div>
                </div>
                <p class="text-2xl font-black text-slate-800"><?= money($totalDeposited) ?></p>
                <p class="text-xs text-slate-400 mt-1">Dépôts cumulés</p>
            </div>

            <div class="stat-card">
                <div class="flex items-center justify-between mb-3">
                    <p class="text-xs font-semibold text-slate-400 uppercase tracking-widest">
                        <?= $user['role'] === 'client' ? 'Total payé' : 'Total gagné' ?>
                    </p>
                    <div class="w-8 h-8 rounded-xl <?= $user['role'] === 'freelancer' ? 'bg-violet-50' : 'bg-amber-50' ?> flex items-center justify-center">
                        <span class="material-symbols-outlined <?= $user['role'] === 'freelancer' ? 'text-violet-500' : 'text-amber-500' ?> text-base">payments</span>
                    </div>
                </div>
                <p class="text-2xl font-black text-slate-800"><?= money($totalPayments) ?></p>
                <p class="text-xs text-slate-400 mt-1"><?= $user['role'] === 'freelancer' ? 'Via contrats' : 'Projets financés' ?></p>
            </div>

            <div class="stat-card">
                <div class="flex items-center justify-between mb-3">
                    <p class="text-xs font-semibold text-slate-400 uppercase tracking-widest">Total retiré</p>
                    <div class="w-8 h-8 rounded-xl bg-red-50 flex items-center justify-center">
                        <span class="material-symbols-outlined text-red-400 text-base">remove_circle</span>
                    </div>
                </div>
                <p class="text-2xl font-black text-slate-800"><?= money($totalWithdrawn) ?></p>
                <p class="text-xs text-slate-400 mt-1">Retraits effectués</p>
            </div>
        </div>
    </div>

    <!-- ── Historique transactions ────────────────────────── -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100">
            <h2 class="font-bold text-slate-800 flex items-center gap-2">
                <span class="material-symbols-outlined text-slate-400 text-xl">history</span>
                Transactions récentes
            </h2>
            <a href="/upc_freelance/app/wallet/history.php"
               class="text-xs font-semibold text-secondary hover:underline flex items-center gap-0.5">
                Voir tout
                <span class="material-symbols-outlined text-sm">chevron_right</span>
            </a>
        </div>

        <?php if (empty($transactions)): ?>
        <div class="py-16 text-center">
            <div class="w-16 h-16 rounded-2xl bg-slate-50 flex items-center justify-center mx-auto mb-4">
                <span class="material-symbols-outlined text-3xl text-slate-300">receipt_long</span>
            </div>
            <p class="font-semibold text-slate-600 mb-1">Aucune transaction</p>
            <p class="text-slate-400 text-sm">Rechargez votre wallet pour commencer.</p>
            <button onclick="openDepositModal()"
                    class="mt-4 inline-flex items-center gap-1.5 bg-primary text-white text-sm font-semibold px-5 py-2.5 rounded-xl hover:opacity-90 transition-opacity active:scale-95">
                <span class="material-symbols-outlined text-base">add_circle</span>
                Recharger maintenant
            </button>
        </div>
        <?php else: ?>
        <div class="divide-y divide-slate-50">
            <?php
            $typeLabels = [
                'deposit'    => 'Rechargement',
                'withdrawal' => 'Retrait',
                'payment'    => $user['role'] === 'freelancer' ? 'Paiement reçu' : 'Paiement envoyé',
                'refund'     => 'Remboursement',
                'lock'       => 'Montant bloqué',
                'unlock'     => 'Montant libéré',
            ];
            $typeConfig = [
                'deposit'    => ['icon'=>'add_circle',   'bg'=>'bg-emerald-50', 'color'=>'text-emerald-500', 'credit'=>true],
                'withdrawal' => ['icon'=>'south',        'bg'=>'bg-red-50',     'color'=>'text-red-400',     'credit'=>false],
                'payment'    => ['icon'=>'payments',     'bg'=>'bg-blue-50',    'color'=>'text-blue-500',    'credit'=>$user['role']==='freelancer'],
                'refund'     => ['icon'=>'undo',         'bg'=>'bg-emerald-50', 'color'=>'text-emerald-500', 'credit'=>true],
                'lock'       => ['icon'=>'lock',         'bg'=>'bg-amber-50',   'color'=>'text-amber-500',   'credit'=>false],
                'unlock'     => ['icon'=>'lock_open',    'bg'=>'bg-emerald-50', 'color'=>'text-emerald-500', 'credit'=>true],
            ];
            foreach ($transactions as $tx):
                $cfg = $typeConfig[$tx['type']] ?? ['icon'=>'swap_horiz','bg'=>'bg-slate-50','color'=>'text-slate-400','credit'=>true];
            ?>
            <div class="tx-row flex items-center gap-4 px-5 py-4">
                <div class="w-10 h-10 rounded-2xl <?= $cfg['bg'] ?> flex items-center justify-center flex-shrink-0">
                    <span class="material-symbols-outlined <?= $cfg['color'] ?> text-lg"><?= $cfg['icon'] ?></span>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold text-slate-800"><?= $typeLabels[$tx['type']] ?? $tx['type'] ?></p>
                    <p class="text-xs text-slate-400 truncate mt-0.5">
                        <?= $tx['project_title'] ? h(truncate($tx['project_title'], 35)) . ' · ' : '' ?>
                        <?= formatDate($tx['created_at'], 'd/m/Y H:i') ?>
                    </p>
                </div>
                <div class="text-right flex-shrink-0">
                    <p class="font-bold text-sm <?= $cfg['credit'] ? 'text-emerald-600' : 'text-red-500' ?>">
                        <?= $cfg['credit'] ? '+' : '−' ?><?= money((float)$tx['amount']) ?>
                    </p>
                    <p class="text-xs text-slate-400 mt-0.5">→ <?= money((float)$tx['balance_after']) ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

</div>

<?php
// Fragments modaux (doivent être après $wallet chargé)
require_once 'deposit_modal_fragment.php';
require_once 'withdraw_modal_fragment.php';

$appLayout = true;
require_once '../../includes/footer.php';
?>