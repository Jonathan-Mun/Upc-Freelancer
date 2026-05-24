<?php
// ============================================================
// UPC FREELANCE — Détails du contrat + chat
// /var/www/html/upc_freelance/app/contracts/details.php
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
    SELECT c.*,
           p.title AS project_title, p.id AS project_id, p.description AS project_desc,
           cl.first_name AS client_fname, cl.last_name AS client_lname, cl.avatar AS client_avatar,
           fr.first_name AS freelancer_fname, fr.last_name AS freelancer_lname, fr.avatar AS freelancer_avatar
    FROM contracts c
    JOIN projects p ON p.id = c.project_id
    JOIN users cl   ON cl.id = c.client_id
    JOIN users fr   ON fr.id = c.freelancer_id
    WHERE c.id = ? AND (c.client_id = ? OR c.freelancer_id = ?)
');
$stmt->execute([$contractId, $user['id'], $user['id']]);
$contract = $stmt->fetch();
if (!$contract) { http_response_code(403); die('Accès refusé.'); }

$isClient     = $user['id'] === $contract['client_id'];
$isFreelancer = $user['id'] === $contract['freelancer_id'];

// ─── Envoyer un message ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    verifyCsrf();
    $body = sanitize($_POST['message_body'] ?? '');
    if (!empty($body)) {
        $pdo->prepare('INSERT INTO messages (contract_id, sender_id, body) VALUES (?, ?, ?)')
            ->execute([$contractId, $user['id'], $body]);
        // Notifier l'autre partie
        $receiverId = $isClient ? $contract['freelancer_id'] : $contract['client_id'];
        sendNotification($receiverId, 'new_message', 'Nouveau message',
            $user['first_name'] . ' vous a envoyé un message.',
            '/upc_freelance/app/contracts/details.php?id=' . $contractId);
    }
    redirect('/var/www/html/upc_freelance/app/contracts/details.php?id=' . $contractId . '#chat');
}

// ─── Valider le travail (libérer le paiement) ─────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['complete_contract'])) {
    verifyCsrf();
    if ($isClient && $contract['status'] === 'active') {
        $pdo->prepare('UPDATE contracts SET status = "completed", completed_at = NOW() WHERE id = ?')->execute([$contractId]);
        $pdo->prepare('UPDATE projects SET status = "completed" WHERE id = ?')->execute([$contract['project_id']]);

        // Libérer le paiement : déduire du locked client et créditer le freelancer
        $pdo->prepare('UPDATE wallets SET locked = locked - ? WHERE user_id = ?')
            ->execute([$contract['amount'], $contract['client_id']]);

        $walletFr = getUserWallet($contract['freelancer_id']);
        $pdo->prepare('UPDATE wallets SET balance = balance + ? WHERE user_id = ?')
            ->execute([$contract['amount'], $contract['freelancer_id']]);

        recordTransaction($contract['freelancer_id'], 'payment', $contract['amount'], $contractId,
            'Paiement reçu pour contrat #' . $contractId);

        sendNotification($contract['freelancer_id'], 'payment_received', 'Paiement reçu !',
            money((float)$contract['amount']) . ' ont été crédités sur votre wallet.',
            '/upc_freelance/app/wallet/index.php');

        flash('success', 'Contrat terminé ! Le paiement a été transféré au freelancer.');
        redirect('/var/www/html/upc_freelance/app/contracts/details.php?id=' . $contractId);
    }
}

// ─── Marquer messages comme lus ──────────────────────────────
$pdo->prepare('UPDATE messages SET is_read = 1 WHERE contract_id = ? AND sender_id != ?')
    ->execute([$contractId, $user['id']]);

// ─── Charger messages ─────────────────────────────────────────
$messages = $pdo->prepare('
    SELECT m.*, u.first_name, u.last_name, u.avatar
    FROM messages m JOIN users u ON u.id = m.sender_id
    WHERE m.contract_id = ?
    ORDER BY m.created_at ASC
');
$messages->execute([$contractId]);
$messages = $messages->fetchAll();

$pageTitle = 'Contrat — ' . h($contract['project_title']);
$appLayout = true;
require_once '/var/www/html/upc_freelance/includes/header.php';
?>

<?php renderFlash(); ?>

<!-- Retour -->
<a href="/upc_freelance/app/contracts/list.php" class="inline-flex items-center gap-1 text-sm text-secondary hover:underline mb-6">
    <span class="material-symbols-outlined text-base">arrow_back</span> Mes contrats
</a>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    <!-- ── Chat ─────────────────────────────────────────── -->
    <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-100 custom-shadow-low overflow-hidden flex flex-col" style="min-height:600px;" id="chat">

        <!-- Header chat -->
        <div class="flex items-center justify-between p-5 border-b border-slate-100">
            <div>
                <h2 class="font-semibold text-primary"><?= h($contract['project_title']) ?></h2>
                <p class="text-xs text-on-surface-variant">Chat du contrat · <?= count($messages) ?> message<?= count($messages)>1?'s':'' ?></p>
            </div>
            <?php
            $sc = ['active'=>'green','completed'=>'blue','cancelled'=>'red','disputed'=>'amber'][$contract['status']] ?? 'gray';
            $sl = ['active'=>'Actif','completed'=>'Terminé','cancelled'=>'Annulé','disputed'=>'Litige'][$contract['status']] ?? $contract['status'];
            ?>
            <span class="text-xs bg-<?= $sc ?>-100 text-<?= $sc ?>-700 px-3 py-1.5 rounded-full font-medium"><?= $sl ?></span>
        </div>

        <!-- Messages -->
        <div class="flex-1 overflow-y-auto p-5 space-y-4" id="messages-container">
            <?php if (empty($messages)): ?>
            <div class="text-center py-12">
                <span class="material-symbols-outlined text-4xl text-slate-300 block mb-3">chat</span>
                <p class="text-on-surface-variant text-sm">Aucun message. Démarrez la conversation !</p>
            </div>
            <?php endif; ?>
            <?php foreach ($messages as $msg):
                $isMe = $msg['sender_id'] === $user['id'];
            ?>
            <div class="flex <?= $isMe ? 'justify-end' : 'justify-start' ?> gap-2">
                <?php if (!$isMe): ?>
                <div class="w-8 h-8 rounded-full bg-primary/10 flex items-center justify-center text-xs font-bold text-primary flex-shrink-0 mt-auto">
                    <?= mb_substr($msg['first_name'], 0, 1) ?>
                </div>
                <?php endif; ?>
                <div class="max-w-[70%]">
                    <?php if (!$isMe): ?>
                    <p class="text-xs text-slate-400 mb-1 ml-1"><?= h($msg['first_name']) ?></p>
                    <?php endif; ?>
                    <div class="px-4 py-2.5 rounded-2xl <?= $isMe ? 'bg-primary text-white rounded-tr-sm' : 'bg-surface-container-low text-on-surface rounded-tl-sm' ?>">
                        <p class="text-sm leading-relaxed"><?= nl2br(h($msg['body'])) ?></p>
                    </div>
                    <p class="text-xs text-slate-400 mt-1 <?= $isMe ? 'text-right mr-1' : 'ml-1' ?>">
                        <?= timeAgo($msg['created_at']) ?>
                    </p>
                </div>
                <?php if ($isMe): ?>
                <div class="w-8 h-8 rounded-full bg-secondary/10 flex items-center justify-center text-xs font-bold text-secondary flex-shrink-0 mt-auto">
                    <?= mb_substr($user['first_name'], 0, 1) ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Formulaire envoi -->
        <?php if ($contract['status'] === 'active'): ?>
        <div class="border-t border-slate-100 p-4">
            <form method="POST" class="flex gap-3">
                <?= csrfField() ?>
                <input type="hidden" name="send_message" value="1"/>
                <input type="text" name="message_body" placeholder="Écrire un message..." required
                       autocomplete="off"
                       class="flex-1 px-4 py-2.5 rounded-xl border border-outline-variant focus:border-secondary focus:ring-2 focus:ring-secondary/20 outline-none text-sm transition-all"/>
                <button type="submit"
                        class="bg-primary text-white px-4 py-2.5 rounded-xl hover:opacity-90 transition-opacity active:scale-95">
                    <span class="material-symbols-outlined">send</span>
                </button>
            </form>
        </div>
        <?php else: ?>
        <div class="border-t border-slate-100 p-4 text-center text-sm text-on-surface-variant">
            Ce contrat est terminé. Le chat est en lecture seule.
        </div>
        <?php endif; ?>
    </div>

    <!-- ── Sidebar contrat ───────────────────────────────── -->
    <div class="space-y-5">

        <!-- Infos contrat -->
        <div class="bg-white rounded-2xl border border-slate-100 p-5 custom-shadow-low">
            <h3 class="font-semibold text-primary mb-4">Détails du contrat</h3>
            <div class="space-y-3 text-sm">
                <div class="flex justify-between">
                    <span class="text-on-surface-variant">Montant</span>
                    <span class="font-bold text-secondary text-base"><?= money((float)$contract['amount']) ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-on-surface-variant">Date début</span>
                    <span class="font-medium text-primary"><?= formatDate($contract['start_date'] ?? $contract['created_at']) ?></span>
                </div>
                <?php if ($contract['end_date']): ?>
                <div class="flex justify-between">
                    <span class="text-on-surface-variant">Date fin prévue</span>
                    <span class="font-medium text-primary"><?= formatDate($contract['end_date']) ?></span>
                </div>
                <?php endif; ?>
                <?php if ($contract['completed_at']): ?>
                <div class="flex justify-between">
                    <span class="text-on-surface-variant">Terminé le</span>
                    <span class="font-medium text-green-600"><?= formatDate($contract['completed_at']) ?></span>
                </div>
                <?php endif; ?>
            </div>

            <!-- Libérer paiement (client) -->
            <?php if ($isClient && $contract['status'] === 'active'): ?>
            <div class="mt-5 pt-4 border-t border-slate-100">
                <p class="text-xs text-on-surface-variant mb-3">
                    Validez le travail pour libérer le paiement de <strong><?= money((float)$contract['amount']) ?></strong> vers le freelancer.
                </p>
                <form method="POST" onsubmit="return confirm('Confirmer la fin du contrat et libérer le paiement ?')">
                    <?= csrfField() ?>
                    <button type="submit" name="complete_contract"
                            class="w-full bg-green-500 text-white text-sm font-button py-3 rounded-xl hover:bg-green-600 transition-colors active:scale-95 flex items-center justify-center gap-2">
                        <span class="material-symbols-outlined text-base">task_alt</span>
                        Valider & libérer le paiement
                    </button>
                </form>
            </div>
            <?php endif; ?>

            <?php if ($contract['status'] === 'completed'): ?>
            <div class="mt-4 p-3 bg-green-50 rounded-xl border border-green-200 flex items-center gap-2">
                <span class="material-symbols-outlined text-green-500">check_circle</span>
                <p class="text-xs text-green-700 font-medium">Paiement libéré avec succès</p>
            </div>
            <?php endif; ?>
        </div>

        <!-- Participants -->
        <div class="bg-white rounded-2xl border border-slate-100 p-5 custom-shadow-low">
            <h3 class="font-semibold text-primary mb-4">Participants</h3>
            <!-- Client -->
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 rounded-full bg-primary/10 flex items-center justify-center font-bold text-primary">
                    <?= mb_substr($contract['client_fname'], 0, 1) ?>
                </div>
                <div>
                    <p class="text-sm font-semibold text-primary"><?= h($contract['client_fname'] . ' ' . $contract['client_lname']) ?></p>
                    <p class="text-xs text-slate-400">Client <?= $isClient ? '(vous)' : '' ?></p>
                </div>
            </div>
            <div class="border-t border-slate-100 pt-3 flex items-center gap-3">
                <div class="w-10 h-10 rounded-full bg-secondary/10 flex items-center justify-center font-bold text-secondary">
                    <?= mb_substr($contract['freelancer_fname'], 0, 1) ?>
                </div>
                <div>
                    <p class="text-sm font-semibold text-primary"><?= h($contract['freelancer_fname'] . ' ' . $contract['freelancer_lname']) ?></p>
                    <p class="text-xs text-slate-400">Freelancer <?= $isFreelancer ? '(vous)' : '' ?></p>
                </div>
            </div>
        </div>

        <!-- Lien projet -->
        <a href="/upc_freelance/app/projects/details.php?id=<?= $contract['project_id'] ?>"
           class="flex items-center gap-3 bg-white rounded-2xl border border-slate-100 p-4 hover:border-secondary/40 transition-colors custom-shadow-low">
            <span class="material-symbols-outlined text-secondary">work</span>
            <div class="flex-1 min-w-0">
                <p class="text-xs text-on-surface-variant">Projet associé</p>
                <p class="text-sm font-medium text-primary truncate"><?= h($contract['project_title']) ?></p>
            </div>
            <span class="material-symbols-outlined text-slate-400 text-base">open_in_new</span>
        </a>
    </div>
</div>

<script>
// Auto-scroll chat vers le bas
const container = document.getElementById('messages-container');
if (container) container.scrollTop = container.scrollHeight;
</script>

<?php
$appLayout = true;
require_once '/var/www/html/upc_freelance/includes/footer.php';
?>
