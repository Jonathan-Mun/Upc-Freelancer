<?php
// ============================================================
// UPC FREELANCE — Recharger mon wallet
// /var/www/html/upc_freelance/app/wallet/deposit.php
// ============================================================

require_once '/var/www/html/upc_freelance/includes/middleware.php';
require_once '/var/www/html/upc_freelance/includes/auth.php';
require_once '/var/www/html/upc_freelance/includes/functions.php';
require_once '/var/www/html/upc_freelance/includes/db.php';

requireLogin();

$user   = currentUser();
$wallet = getUserWallet($user['id']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $amount  = (float)($_POST['amount'] ?? 0);
    $method  = sanitize($_POST['method'] ?? '');

    if ($amount < 500) {
        flash('error', 'Le montant minimum est de 500 XOF.');
    } elseif ($amount > 5000000) {
        flash('error', 'Le montant maximum est de 5 000 000 XOF.');
    } else {
        // En production : intégrer une vraie passerelle (Stripe, CinetPay, etc.)
        // Ici on simule le dépôt direct
        $pdo = getDB();
        $pdo->prepare('UPDATE wallets SET balance = balance + ? WHERE user_id = ?')->execute([$amount, $user['id']]);
        recordTransaction($user['id'], 'deposit', $amount, null, 'Rechargement via ' . $method);
        sendNotification($user['id'], 'deposit_success', 'Rechargement réussi !',
            money($amount) . ' ont été crédités sur votre wallet.', '/upc_freelance/app/wallet/index.php');
        flash('success', money($amount) . ' ont été ajoutés à votre wallet !');
        redirect('/var/www/html/upc_freelance/app/wallet/index.php');
    }
}

$pageTitle = 'Recharger mon wallet — UPC Freelance';
$appLayout = true;
require_once '/var/www/html/upc_freelance/includes/header.php';
?>

<div class="mb-8">
    <a href="/upc_freelance/app/wallet/index.php" class="inline-flex items-center gap-1 text-sm text-secondary hover:underline mb-3">
        <span class="material-symbols-outlined text-base">arrow_back</span> Mon wallet
    </a>
    <h1 class="text-2xl font-bold text-primary">Recharger mon compte</h1>
</div>

<div class="max-w-lg">
    <?php renderFlash(); ?>

    <!-- Solde actuel -->
    <div class="bg-primary text-white rounded-2xl p-5 mb-6 flex items-center justify-between">
        <div>
            <p class="text-blue-200 text-xs mb-1">Solde actuel</p>
            <p class="text-2xl font-bold"><?= money((float)$wallet['balance']) ?></p>
        </div>
        <span class="material-symbols-outlined text-4xl text-blue-200">account_balance_wallet</span>
    </div>

    <form method="POST" class="space-y-5">
        <?= csrfField() ?>

        <!-- Montants rapides -->
        <div>
            <label class="block text-sm font-medium text-primary mb-3">Choisir un montant</label>
            <div class="grid grid-cols-3 gap-3 mb-3">
                <?php foreach ([5000, 10000, 25000, 50000, 100000, 250000] as $amt): ?>
                <button type="button" onclick="setAmount(<?= $amt ?>)"
                        class="amount-btn py-3 rounded-xl border-2 border-slate-200 text-sm font-semibold text-primary hover:border-secondary hover:text-secondary hover:bg-secondary/5 transition-all active:scale-95">
                    <?= number_format($amt, 0, ',', ' ') ?>
                </button>
                <?php endforeach; ?>
            </div>
            <div class="relative">
                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-xs text-slate-400 font-medium">CFA</span>
                <input type="number" name="amount" id="amount-input" min="500" step="100"
                       placeholder="Ou saisir un montant personnalisé"
                       class="w-full pl-12 pr-4 py-3 rounded-xl border border-outline-variant focus:border-secondary focus:ring-2 focus:ring-secondary/20 outline-none text-sm transition-all"/>
            </div>
        </div>

        <!-- Mode de paiement -->
        <div>
            <label class="block text-sm font-medium text-primary mb-3">Mode de paiement</label>
            <div class="grid grid-cols-2 gap-3">
                <?php
                $methods = [
                    ['value'=>'mobile_money', 'label'=>'Mobile Money', 'icon'=>'smartphone'],
                    ['value'=>'bank_transfer','label'=>'Virement bancaire','icon'=>'account_balance'],
                    ['value'=>'orange_money', 'label'=>'Orange Money',  'icon'=>'sim_card'],
                    ['value'=>'airtel_money', 'label'=>'Airtel Money',  'icon'=>'payments'],
                ];
                foreach ($methods as $m):
                ?>
                <label class="flex items-center gap-3 p-3 rounded-xl border-2 border-slate-200 cursor-pointer hover:border-secondary/50 transition-all has-[:checked]:border-secondary has-[:checked]:bg-secondary/5">
                    <input type="radio" name="method" value="<?= $m['value'] ?>" class="text-secondary" required/>
                    <span class="material-symbols-outlined text-slate-400 text-xl"><?= $m['icon'] ?></span>
                    <span class="text-sm font-medium text-primary"><?= $m['label'] ?></span>
                </label>
                <?php endforeach; ?>
            </div>
        </div>

        <button type="submit"
                class="w-full bg-primary text-white font-button text-button py-3.5 rounded-xl hover:opacity-90 transition-opacity active:scale-95 shadow-sm">
            <span class="material-symbols-outlined align-middle mr-1">add_circle</span>
            Recharger maintenant
        </button>
    </form>

    <p class="text-xs text-slate-400 text-center mt-4">
        🔒 Transactions sécurisées — Les fonds sont disponibles immédiatement
    </p>
</div>

<script>
function setAmount(val) {
    document.getElementById('amount-input').value = val;
    document.querySelectorAll('.amount-btn').forEach(btn => {
        const isActive = parseInt(btn.textContent.replace(/\s/g,'')) === val;
        btn.classList.toggle('border-secondary', isActive);
        btn.classList.toggle('bg-secondary/5',   isActive);
        btn.classList.toggle('text-secondary',   isActive);
    });
}
</script>

<?php
$appLayout = true;
require_once '/var/www/html/upc_freelance/includes/footer.php';
?>
