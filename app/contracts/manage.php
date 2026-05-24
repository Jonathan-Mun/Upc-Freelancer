<?php
// ============================================================
// UPC FREELANCE — Gérer un contrat (litige, annulation)
// /var/www/html/upc_freelance/app/contracts/manage.php
// ============================================================

require_once '/var/www/html/upc_freelance/includes/middleware.php';
require_once '/var/www/html/upc_freelance/includes/auth.php';
require_once '/var/www/html/upc_freelance/includes/functions.php';
require_once '/var/www/html/upc_freelance/includes/db.php';

requireLogin();

$user       = currentUser();
$pdo        = getDB();
$contractId = (int)($_GET['id'] ?? 0);
if (!$contractId) redirect('/var/www/html/upc_freelance/app/contracts/list.php');

$stmt = $pdo->prepare('
    SELECT c.*, p.title AS project_title
    FROM contracts c JOIN projects p ON p.id = c.project_id
    WHERE c.id = ? AND (c.client_id = ? OR c.freelancer_id = ?)
');
$stmt->execute([$contractId, $user['id'], $user['id']]);
$contract = $stmt->fetch();
if (!$contract) { http_response_code(403); die('Accès refusé.'); }

$isClient = $user['id'] === $contract['client_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = sanitize($_POST['action'] ?? '');
    $reason = sanitize($_POST['reason'] ?? '');

    if ($action === 'dispute' && $contract['status'] === 'active') {
        $pdo->prepare('UPDATE contracts SET status = "disputed" WHERE id = ?')->execute([$contractId]);
        $receiverId = $isClient ? $contract['freelancer_id'] : $contract['client_id'];
        sendNotification($receiverId, 'dispute_opened', 'Litige ouvert',
            'Un litige a été ouvert sur le contrat "' . $contract['project_title'] . '". Raison : ' . $reason,
            '/upc_freelance/app/contracts/details.php?id=' . $contractId);
        flash('warning', 'Litige ouvert. Notre équipe va examiner la situation.');
    }

    if ($action === 'cancel' && $contract['status'] === 'active' && $isClient) {
        $pdo->prepare('UPDATE contracts SET status = "cancelled" WHERE id = ?')->execute([$contractId]);
        $pdo->prepare('UPDATE projects SET status = "open" WHERE id = ?')->execute([$contract['project_id']]);
        // Débloquer le montant client
        $pdo->prepare('UPDATE wallets SET balance = balance + ?, locked = locked - ? WHERE user_id = ?')
            ->execute([$contract['amount'], $contract['amount'], $contract['client_id']]);
        recordTransaction($contract['client_id'], 'unlock', $contract['amount'], $contractId,
            'Montant libéré suite à annulation contrat #' . $contractId);
        sendNotification($contract['freelancer_id'], 'contract_cancelled', 'Contrat annulé',
            'Le contrat "' . $contract['project_title'] . '" a été annulé par le client.');
        flash('info', 'Contrat annulé. Le montant a été remis dans votre wallet.');
    }

    redirect('/var/www/html/upc_freelance/app/contracts/details.php?id=' . $contractId);
}

$pageTitle = 'Gérer le contrat — UPC Freelance';
$appLayout = true;
require_once '/var/www/html/upc_freelance/includes/header.php';
?>

<div class="mb-8">
    <a href="/upc_freelance/app/contracts/details.php?id=<?= $contractId ?>" class="inline-flex items-center gap-1 text-sm text-secondary hover:underline mb-3">
        <span class="material-symbols-outlined text-base">arrow_back</span> Retour au contrat
    </a>
    <h1 class="text-2xl font-bold text-primary">Gérer le contrat</h1>
    <p class="text-on-surface-variant text-sm mt-1"><?= h($contract['project_title']) ?></p>
</div>

<?php renderFlash(); ?>

<div class="max-w-xl space-y-4">

    <?php if ($contract['status'] === 'active'): ?>

    <!-- Ouvrir un litige -->
    <div class="bg-white rounded-2xl border border-amber-200 p-6 custom-shadow-low">
        <div class="flex items-start gap-3 mb-4">
            <span class="material-symbols-outlined text-amber-500 text-2xl">warning</span>
            <div>
                <h3 class="font-semibold text-primary">Ouvrir un litige</h3>
                <p class="text-sm text-on-surface-variant mt-1">
                    Si vous rencontrez un problème avec votre partenaire, vous pouvez ouvrir un litige. Notre équipe examinera la situation et prendra une décision.
                </p>
            </div>
        </div>
        <form method="POST" class="space-y-3">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="dispute"/>
            <div>
                <label class="block text-sm font-medium text-primary mb-1.5">Raison du litige <span class="text-red-500">*</span></label>
                <textarea name="reason" rows="3" required
                          placeholder="Décrivez le problème rencontré..."
                          class="w-full px-4 py-3 rounded-xl border border-outline-variant focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 outline-none text-sm resize-y"></textarea>
            </div>
            <button type="submit"
                    class="w-full bg-amber-500 text-white font-button text-button py-3 rounded-xl hover:bg-amber-600 transition-colors active:scale-95"
                    onclick="return confirm('Confirmer l\'ouverture du litige ?')">
                <span class="material-symbols-outlined align-middle mr-1">flag</span>
                Ouvrir un litige
            </button>
        </form>
    </div>

    <!-- Annuler (client seulement) -->
    <?php if ($isClient): ?>
    <div class="bg-white rounded-2xl border border-red-200 p-6 custom-shadow-low">
        <div class="flex items-start gap-3 mb-4">
            <span class="material-symbols-outlined text-red-500 text-2xl">cancel</span>
            <div>
                <h3 class="font-semibold text-primary">Annuler le contrat</h3>
                <p class="text-sm text-on-surface-variant mt-1">
                    L'annulation mettra fin au contrat et le montant bloqué (<?= money((float)$contract['amount']) ?>) sera remis dans votre wallet. Cette action est irréversible.
                </p>
            </div>
        </div>
        <form method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="cancel"/>
            <button type="submit"
                    class="w-full bg-red-50 text-red-600 border border-red-200 font-button text-button py-3 rounded-xl hover:bg-red-100 transition-colors active:scale-95"
                    onclick="return confirm('Êtes-vous sûr de vouloir annuler ce contrat ? Cette action est irréversible.')">
                <span class="material-symbols-outlined align-middle mr-1">close</span>
                Annuler le contrat
            </button>
        </form>
    </div>
    <?php endif; ?>

    <?php else: ?>
    <div class="bg-white rounded-2xl border border-slate-100 p-12 text-center custom-shadow-low">
        <span class="material-symbols-outlined text-4xl text-slate-300 block mb-3">lock</span>
        <p class="text-on-surface-variant">
            Ce contrat est <strong><?= $contract['status'] ?></strong> — aucune action disponible.
        </p>
        <a href="/upc_freelance/app/contracts/details.php?id=<?= $contractId ?>"
           class="mt-4 inline-block text-sm text-secondary hover:underline">
            Retour au contrat
        </a>
    </div>
    <?php endif; ?>
</div>

<?php $appLayout = true; require_once '/var/www/html/upc_freelance/includes/footer.php'; ?>
