<?php
// ============================================================
// UPC FREELANCE — Détails du contrat + chat
// ../../app/contracts/details.php
// ============================================================

require_once '../../includes/middleware.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../includes/db.php';

requireLogin();

$user       = currentUser();
$pdo        = getDB();
$contractId = (int)($_GET['id'] ?? 0);
if (!$contractId) redirect('../../app/contracts/list.php');

$stmt = $pdo->prepare('
    SELECT c.*,
           p.title AS project_title, p.id AS project_id, p.description AS project_desc,
           cl.first_name AS client_fname, cl.last_name AS client_lname, cl.avatar AS client_avatar, cl.is_verified AS client_verified,
           fr.first_name AS freelancer_fname, fr.last_name AS freelancer_lname, fr.avatar AS freelancer_avatar, fr.is_verified AS freelancer_verified
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

// ─── Envoyer un message (AJAX ou form classique) ──────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    verifyCsrf();
    $body = sanitize($_POST['message_body'] ?? '');
    if (!empty($body)) {
        $pdo->prepare('INSERT INTO messages (contract_id, sender_id, body) VALUES (?, ?, ?)')
            ->execute([$contractId, $user['id'], $body]);
        $receiverId = $isClient ? $contract['freelancer_id'] : $contract['client_id'];
        sendNotification($receiverId, 'new_message', 'Nouveau message',
            $user['first_name'] . ' vous a envoyé un message.',
            '/upc_freelance/app/contracts/details.php?id=' . $contractId);

        // Réponse JSON si appel AJAX
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            header('Content-Type: application/json');
            echo json_encode(['ok' => true]);
            exit;
        }
    }
    redirect('../../app/contracts/details.php?id=' . $contractId . '#chat');
}

// ─── Valider le travail ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['complete_contract'])) {
    verifyCsrf();
    if ($isClient && $contract['status'] === 'active') {
        $pdo->prepare('UPDATE contracts SET status = "completed", completed_at = NOW() WHERE id = ?')->execute([$contractId]);
        $pdo->prepare('UPDATE projects SET status = "completed" WHERE id = ?')->execute([$contract['project_id']]);

        $pdo->prepare('UPDATE wallets SET locked = locked - ? WHERE user_id = ?')
            ->execute([$contract['amount'], $contract['client_id']]);
        $pdo->prepare('UPDATE wallets SET balance = balance + ? WHERE user_id = ?')
            ->execute([$contract['amount'], $contract['freelancer_id']]);

        recordTransaction($contract['freelancer_id'], 'payment', $contract['amount'], $contractId,
            'Paiement reçu pour contrat #' . $contractId);
        sendNotification($contract['freelancer_id'], 'payment_received', 'Paiement reçu !',
            money((float)$contract['amount']) . ' ont été crédités sur votre wallet.',
            '/upc_freelance/app/wallet/index.php');

        flash('success', 'Contrat terminé ! Le paiement a été transféré au freelancer.');
        redirect('../../app/contracts/details.php?id=' . $contractId);
    }
}

// ─── Marquer messages comme lus ──────────────────────────────
$pdo->prepare('UPDATE messages SET is_read = 1 WHERE contract_id = ? AND sender_id != ?')
    ->execute([$contractId, $user['id']]);

// ─── Charger messages initiaux ────────────────────────────────
$stmtMsgs = $pdo->prepare('
    SELECT m.id, m.body, m.sender_id, m.created_at, m.is_read,
           u.first_name, u.last_name, u.avatar
    FROM messages m
    JOIN users u ON u.id = m.sender_id
    WHERE m.contract_id = ?
    ORDER BY m.created_at ASC
');
$stmtMsgs->execute([$contractId]);
$messages = $stmtMsgs->fetchAll();

// ID du dernier message pour le polling JS
$lastMsgId = !empty($messages) ? (int)end($messages)['id'] : 0;

$pageTitle = 'Contrat — ' . h($contract['project_title']);
$appLayout = true;
require_once '../../includes/header.php';
?>

<?php renderFlash(); ?>

<a href="/upc_freelance/app/contracts/list.php" class="inline-flex items-center gap-1 text-sm text-secondary hover:underline mb-6">
    <span class="material-symbols-outlined text-base">arrow_back</span> Mes contrats
</a>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    <!-- ── Chat ─────────────────────────────────────────── -->
    <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-100 custom-shadow-low overflow-hidden flex flex-col" style="min-height:600px;" id="chat">

        <!-- Header -->
        <div class="flex items-center justify-between p-5 border-b border-slate-100">
            <div>
                <h2 class="font-semibold text-primary"><?= h($contract['project_title']) ?></h2>
                <p class="text-xs text-on-surface-variant" id="msg-count">
                    Chat du contrat · <?= count($messages) ?> message<?= count($messages)>1?'s':'' ?>
                </p>
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
            <div class="text-center py-12" id="empty-chat">
                <span class="material-symbols-outlined text-4xl text-slate-300 block mb-3">chat</span>
                <p class="text-on-surface-variant text-sm">Aucun message. Démarrez la conversation !</p>
            </div>
            <?php endif; ?>

            <?php foreach ($messages as $msg):
                $isMe = $msg['sender_id'] === $user['id'];
            ?>
            <div class="flex <?= $isMe ? 'justify-end' : 'justify-start' ?> gap-2"
                 data-msg-id="<?= $msg['id'] ?>">
                <?php if (!$isMe): ?>
                <?= renderAvatar($msg['avatar'] ?? null, $msg['first_name'], $msg['last_name'], false, 'w-8 h-8', 'rounded-full', 'mt-auto') ?>
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
                <?= renderAvatar($user['avatar'] ?? null, $user['first_name'], $user['last_name'] ?? '', false, 'w-8 h-8', 'rounded-full', 'mt-auto') ?>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Formulaire envoi -->
        <?php if ($contract['status'] === 'active'): ?>
        <div class="border-t border-slate-100 p-4">
            <form id="chat-form" class="flex gap-3">
                <?= csrfField() ?>
                <input type="hidden" name="send_message" value="1"/>
                <input type="text" name="message_body" id="message-input"
                       placeholder="Écrire un message..." required autocomplete="off"
                       class="flex-1 px-4 py-2.5 rounded-xl border border-outline-variant focus:border-secondary focus:ring-2 focus:ring-secondary/20 outline-none text-sm transition-all"/>
                <button type="submit" id="send-btn"
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

    <!-- ── Sidebar ───────────────────────────────────────── -->
    <div class="space-y-5">

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

            <?php if ($isClient && $contract['status'] === 'active'): ?>
            <div class="mt-5 pt-4 border-t border-slate-100">
                <p class="text-xs text-on-surface-variant mb-3">
                    Validez le travail pour libérer <strong><?= money((float)$contract['amount']) ?></strong> vers le freelancer.
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
            <div class="flex items-center gap-3 mb-3">
                <?= renderAvatar($contract['client_avatar'] ?? null, $contract['client_fname'], $contract['client_lname'], (bool)($contract['client_verified'] ?? false), 'w-10 h-10', 'rounded-full') ?>
                <div>
                    <p class="text-sm font-semibold text-primary flex items-center gap-1">
                        <?= h($contract['client_fname'] . ' ' . $contract['client_lname']) ?>
                    </p>
                    <p class="text-xs text-slate-400">Client <?= $isClient ? '(vous)' : '' ?></p>
                </div>
            </div>
            <div class="border-t border-slate-100 pt-3 flex items-center gap-3">
                <?= renderAvatar($contract['freelancer_avatar'] ?? null, $contract['freelancer_fname'], $contract['freelancer_lname'], (bool)($contract['freelancer_verified'] ?? false), 'w-10 h-10', 'rounded-full') ?>
                <div>
                    <p class="text-sm font-semibold text-primary flex items-center gap-1">
                        <?= h($contract['freelancer_fname'] . ' ' . $contract['freelancer_lname']) ?>
                    </p>
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
(function () {
    const contractId  = <?= $contractId ?>;
    const currentUser = <?= $user['id'] ?>;
    const csrfToken   = document.querySelector('input[name="csrf_token"]')?.value ?? '';
    const isActive    = <?= $contract['status'] === 'active' ? 'true' : 'false' ?>;

    const container  = document.getElementById('messages-container');
    const form       = document.getElementById('chat-form');
    const input      = document.getElementById('message-input');
    const countEl    = document.getElementById('msg-count');

    // ── ID du dernier message connu ───────────────────────
    let lastId = <?= $lastMsgId ?>;
    let totalCount = <?= count($messages) ?>;

    // ── Auto-scroll vers le bas ───────────────────────────
    function scrollBottom(force) {
        if (!container) return;
        const nearBottom = container.scrollHeight - container.scrollTop - container.clientHeight < 120;
        if (force || nearBottom) container.scrollTop = container.scrollHeight;
    }
    scrollBottom(true);

    // ── Helpers ───────────────────────────────────────────
    function escHtml(s) {
        return String(s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function timeAgoJs(dateStr) {
        const diff = Math.floor((Date.now() - new Date(dateStr)) / 1000);
        if (diff < 60)      return 'À l\'instant';
        if (diff < 3600)    return Math.floor(diff / 60) + ' min';
        if (diff < 86400)   return Math.floor(diff / 3600) + ' h';
        if (diff < 2592000) return Math.floor(diff / 86400) + ' j';
        return new Date(dateStr).toLocaleDateString('fr-FR');
    }

    function nl2brJs(s) {
        return escHtml(s).replace(/\n/g, '<br>');
    }

    // ── Créer une bulle de message ────────────────────────
    function buildBubble(msg) {
        const isMe     = parseInt(msg.sender_id) === currentUser;
        const initiale = msg.first_name ? msg.first_name.charAt(0).toUpperCase() : '?';
        const align    = isMe ? 'justify-end' : 'justify-start';
        const bubble   = isMe
            ? 'bg-primary text-white rounded-tr-sm'
            : 'bg-surface-container-low text-on-surface rounded-tl-sm';
        const timeAlign = isMe ? 'text-right mr-1' : 'ml-1';
        function buildAvatarHtml(avatarPath, initiale, isCurrentUser) {
            const base  = '/upc_freelance/storage/';
            const color = isCurrentUser ? 'bg-secondary/10 text-secondary' : 'bg-primary/10 text-primary';
            if (avatarPath) {
                return `<img src="${base}${escHtml(avatarPath)}" alt="Avatar" class="w-8 h-8 rounded-full object-cover flex-shrink-0 mt-auto"/>`;
            }
            return `<div class="w-8 h-8 rounded-full ${color} flex items-center justify-center text-xs font-bold flex-shrink-0 mt-auto">${initiale}</div>`;
        }
        const myAvatar    = <?= json_encode($user['avatar'] ?? null) ?>;
        const avatar = buildAvatarHtml(isMe ? myAvatar : (msg.avatar || null), initiale, isMe);
        const senderName = !isMe
            ? `<p class="text-xs text-slate-400 mb-1 ml-1">${escHtml(msg.first_name)}</p>`
            : '';

        const div = document.createElement('div');
        div.className = `flex ${align} gap-2`;
        div.setAttribute('data-msg-id', msg.id);
        div.innerHTML = `
            ${!isMe ? avatar : ''}
            <div class="max-w-[70%]">
                ${senderName}
                <div class="px-4 py-2.5 rounded-2xl ${bubble}">
                    <p class="text-sm leading-relaxed">${nl2brJs(msg.body)}</p>
                </div>
                <p class="text-xs text-slate-400 mt-1 ${timeAlign}">
                    ${timeAgoJs(msg.created_at)}
                </p>
            </div>
            ${isMe ? avatar : ''}
        `;
        return div;
    }

    // ── Ajouter des messages dans le DOM ──────────────────
    function appendMessages(msgs) {
        if (!msgs.length) return;

        // Supprimer le placeholder "aucun message"
        const empty = document.getElementById('empty-chat');
        if (empty) empty.remove();

        msgs.forEach(msg => {
            // Éviter les doublons
            if (container.querySelector(`[data-msg-id="${msg.id}"]`)) return;
            container.appendChild(buildBubble(msg));
            lastId = Math.max(lastId, parseInt(msg.id));
            totalCount++;
        });

        // Mettre à jour le compteur
        if (countEl) {
            countEl.textContent = `Chat du contrat · ${totalCount} message${totalCount > 1 ? 's' : ''}`;
        }

        scrollBottom(false);
    }

    // ── Polling : nouveaux messages ───────────────────────
    async function pollMessages() {
        try {
            const res  = await fetch(
                `/upc_freelance/app/messages/api-messages.php?contract_id=${contractId}&since=${lastId}`,
                { credentials: 'same-origin' }
            );
            if (!res.ok) return;
            const msgs = await res.json();
            appendMessages(msgs);
        } catch (e) { /* silencieux */ }
    }

    setInterval(pollMessages, 3000);

    // ── Envoi AJAX ────────────────────────────────────────
    if (form && isActive) {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const body = input.value.trim();
            if (!body) return;

            const sendBtn = document.getElementById('send-btn');
            sendBtn.disabled = true;
            input.disabled   = true;

            // Affichage optimiste immédiat
            const optimistic = buildBubble({
                id         : 'tmp_' + Date.now(),
                body       : body,
                sender_id  : currentUser,
                created_at : new Date().toISOString(),
                first_name : '',
                last_name  : '',
                avatar     : null,
            });
            optimistic.style.opacity = '0.6';
            const empty = document.getElementById('empty-chat');
            if (empty) empty.remove();
            container.appendChild(optimistic);
            scrollBottom(true);
            input.value = '';

            try {
                const fd = new FormData(form);
                fd.set('message_body', body);
                const res = await fetch(
                    `/upc_freelance/app/contracts/details.php?id=${contractId}`,
                    {
                        method      : 'POST',
                        headers     : { 'X-Requested-With': 'XMLHttpRequest' },
                        credentials : 'same-origin',
                        body        : fd,
                    }
                );

                if (res.ok) {
                    // Supprimer le message optimiste — le polling le ramènera avec son vrai id
                    optimistic.remove();
                    await pollMessages();
                } else {
                    optimistic.style.opacity = '1';
                    optimistic.style.border  = '1px solid red';
                }
            } catch (err) {
                optimistic.style.opacity = '1';
            } finally {
                sendBtn.disabled = false;
                input.disabled   = false;
                input.focus();
            }
        });
    }
})();
</script>

<?php
$appLayout = true;
require_once '../../includes/footer.php';
?>