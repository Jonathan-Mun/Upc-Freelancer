<?php
// ============================================================
// UPC FREELANCE — Retrait wallet
// ../../app/wallet/withdraw.php
// ============================================================

require_once '../../includes/middleware.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../includes/db.php';

requireLogin();

$user   = currentUser();
$pdo    = getDB();
$wallet = getUserWallet($user['id']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $amount  = (float)($_POST['amount']  ?? 0);
    $method  = sanitize($_POST['method'] ?? '');
    $account = sanitize($_POST['account']?? '');

    $errors = [];
    if ($amount < 1000)                         $errors[] = 'Montant minimum de retrait : 1 000 XOF.';
    if ($amount > (float)$wallet['balance'])    $errors[] = 'Solde insuffisant.';
    if (empty($account))                        $errors[] = 'Veuillez saisir votre numéro / RIB.';

    if (empty($errors)) {
        // Déduire du wallet
        $pdo->prepare('UPDATE wallets SET balance = balance - ? WHERE user_id = ?')
            ->execute([$amount, $user['id']]);
        recordTransaction($user['id'], 'withdrawal', $amount, null,
            'Retrait via ' . $method . ' vers ' . $account);

        sendNotification($user['id'], 'withdrawal_requested', 'Retrait en cours',
            money($amount) . ' en cours de traitement vers votre compte ' . $method . '.',
            '/upc_freelance/app/wallet/history.php');

        flash('success', 'Demande de retrait de ' . money($amount) . ' envoyée avec succès !');
        redirect('../../app/wallet/index.php');
    } else {
        flash('error', implode(' ', $errors));
    }
}

$pageTitle = 'Retirer des fonds — UPC Freelance';
$appLayout = true;
require_once '../../includes/header.php';
?>

<div class="mb-8">
    <a href="/upc_freelance/app/wallet/index.php" class="inline-flex items-center gap-1 text-sm text-secondary hover:underline mb-3">
        <span class="material-symbols-outlined text-base">arrow_back</span> Mon wallet
    </a>
    <h1 class="text-2xl font-bold text-primary">Retirer des fonds</h1>
</div>

<div class="max-w-lg">
    <?php renderFlash(); ?>

    <!-- Solde dispo -->
    <div class="bg-primary text-white rounded-2xl p-5 mb-6 flex items-center justify-between">
        <div>
            <p class="text-blue-200 text-xs mb-1">Solde disponible</p>
            <p class="text-3xl font-bold"><?= money((float)$wallet['balance']) ?></p>
            <?php if ($wallet['locked'] > 0): ?>
            <p class="text-xs text-blue-300 mt-1">🔒 <?= money((float)$wallet['locked']) ?> bloqué (non retirable)</p>
            <?php endif; ?>
        </div>
        <span class="material-symbols-outlined text-4xl text-blue-200">account_balance_wallet</span>
    </div>

    <form method="POST" class="space-y-5">
        <?= csrfField() ?>

        <div>
            <label class="block text-sm font-medium text-primary mb-1.5">Montant à retirer (XOF) <span class="text-red-500">*</span></label>
            <div class="relative">
                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-xs text-slate-400 font-medium">CFA</span>
                <input type="number" name="amount" required min="1000" step="100"
                       max="<?= (float)$wallet['balance'] ?>"
                       placeholder="Montant à retirer"
                       class="w-full pl-12 pr-4 py-3 rounded-xl border border-outline-variant focus:border-secondary focus:ring-2 focus:ring-secondary/20 outline-none text-sm transition-all"/>
            </div>
            <p class="text-xs text-slate-400 mt-1">Minimum : 1 000 XOF · Maximum : <?= money((float)$wallet['balance']) ?></p>
        </div>

        <div>
            <label class="block text-sm font-medium text-primary mb-3">Mode de retrait <span class="text-red-500">*</span></label>
            <div class="grid grid-cols-2 gap-3">
                <?php foreach ([
                    ['value'=>'mobile_money', 'label'=>'Mobile Money',    'icon'=>'smartphone'],
                    ['value'=>'bank_transfer','label'=>'Virement bancaire','icon'=>'account_balance'],
                    ['value'=>'orange_money', 'label'=>'Orange Money',    'icon'=>'sim_card'],
                    ['value'=>'airtel_money', 'label'=>'Airtel Money',    'icon'=>'payments'],
                ] as $m): ?>
                <label class="flex items-center gap-3 p-3 rounded-xl border-2 border-slate-200 cursor-pointer hover:border-secondary/50 transition-all has-[:checked]:border-secondary has-[:checked]:bg-secondary/5">
                    <input type="radio" name="method" value="<?= $m['value'] ?>" class="text-secondary" required/>
                    <span class="material-symbols-outlined text-slate-400 text-xl"><?= $m['icon'] ?></span>
                    <span class="text-sm font-medium text-primary"><?= $m['label'] ?></span>
                </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-primary mb-1.5">Numéro / RIB <span class="text-red-500">*</span></label>
            <div class="relative">
                <span class="absolute left-3 top-1/2 -translate-y-1/2 material-symbols-outlined text-slate-400 text-lg">credit_card</span>
                <input type="text" name="account" required
                       placeholder="Ex: +225 07 XX XX XX XX ou IBAN"
                       class="w-full pl-10 pr-4 py-3 rounded-xl border border-outline-variant focus:border-secondary focus:ring-2 focus:ring-secondary/20 outline-none text-sm transition-all"/>
            </div>
        </div>

        <!-- Récapitulatif -->
        <div class="p-4 bg-amber-50 border border-amber-200 rounded-xl">
            <p class="text-sm text-amber-800 flex items-start gap-2">
                <span class="material-symbols-outlined text-base flex-shrink-0 mt-0.5">info</span>
                Les retraits sont traités sous 24-48h ouvrables. Assurez-vous que vos coordonnées bancaires sont correctes.
            </p>
        </div>

        <button type="submit"
                class="w-full bg-primary text-white font-button text-button py-3.5 rounded-xl hover:opacity-90 transition-opacity active:scale-95 shadow-sm"
                onclick="return confirm('Confirmer la demande de retrait ?')">
            <span class="material-symbols-outlined align-middle mr-1">send</span>
            Confirmer le retrait
        </button>
    </form>
</div>

<?php $appLayout = true; require_once '../../includes/footer.php'; ?>
