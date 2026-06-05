<?php
// ============================================================
// UPC FREELANCE — Chat direct (sans contrat)
// /var/www/html/upc_freelance/app/messages/direct.php
// ============================================================

require_once '../../includes/middleware.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../includes/db.php';

requireLogin();

$user      = currentUser();
$pdo       = getDB();
$partnerId = (int)($_GET['user_id'] ?? 0);

if (!$partnerId || $partnerId === $user['id']) {
    redirect('../../app/messages/inbox.php?tab=direct');
}

// Récupérer le partenaire
$stmt = $pdo->prepare('SELECT id, first_name, last_name, avatar, role, is_verified FROM users WHERE id = ? AND is_active = 1');
$stmt->execute([$partnerId]);
$partner = $stmt->fetch();
if (!$partner) { http_response_code(404); die('Utilisateur introuvable.'); }

// Envoyer un message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_dm'])) {
    verifyCsrf();
    $body = sanitize($_POST['body'] ?? '');
    if (!empty($body)) {
        $pdo->prepare('INSERT INTO direct_messages (sender_id, receiver_id, body) VALUES (?, ?, ?)')
            ->execute([$user['id'], $partnerId, $body]);

        // Notif
        sendNotification($partnerId, 'direct_message', 'Nouveau message',
            $user['first_name'] . ' vous a envoyé un message.',
            '/upc_freelance/app/messages/direct.php?user_id=' . $user['id']);

        // AJAX
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            header('Content-Type: application/json');
            echo json_encode(['ok' => true]);
            exit;
        }
    }
    redirect('../../app/messages/direct.php?user_id=' . $partnerId);
}

// Marquer comme lus
$pdo->prepare('UPDATE direct_messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ?')
    ->execute([$partnerId, $user['id']]);

// Charger les messages
$stmt = $pdo->prepare('
    SELECT dm.id, dm.body, dm.sender_id, dm.created_at, dm.is_read,
           u.first_name, u.last_name, u.avatar
    FROM direct_messages dm
    JOIN users u ON u.id = dm.sender_id
    WHERE (dm.sender_id = ? AND dm.receiver_id = ?)
       OR (dm.sender_id = ? AND dm.receiver_id = ?)
    ORDER BY dm.created_at ASC
');
$stmt->execute([$user['id'], $partnerId, $partnerId, $user['id']]);
$messages = $stmt->fetchAll();
$lastId   = !empty($messages) ? (int)end($messages)['id'] : 0;

$pageTitle = h($partner['first_name'] . ' ' . $partner['last_name']) . ' — UPC Freelance';
$appLayout = true;
require_once '../../includes/header.php';
?>

<?php renderFlash(); ?>

<style>
.chat-wrapper {
    display: flex;
    flex-direction: column;
    height: calc(100vh - 200px);
    min-height: 480px;
    max-height: 760px;
}
.chat-messages {
    flex: 1;
    overflow-y: auto;
    overflow-x: hidden;
    overscroll-behavior: contain;
    scroll-behavior: smooth;
}
.chat-messages::-webkit-scrollbar { width: 4px; }
.chat-messages::-webkit-scrollbar-track { background: transparent; }
.chat-messages::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 4px; }
.chat-footer {
    flex-shrink: 0;
    border-top: 1px solid #f1f5f9;
    background: white;
}
@media (max-width: 1023px) {
    .chat-wrapper {
        height: calc(100dvh - 180px);
        max-height: none;
    }
}
</style>

<!-- Retour -->
<a href="/upc_freelance/app/messages/inbox.php?tab=direct"
   class="inline-flex items-center gap-1 text-sm text-secondary hover:underline mb-6">
    <span class="material-symbols-outlined text-base">arrow_back</span> Messages directs
</a>

<div class="max-w-3xl mx-auto">
    <!-- ── Chat direct ──────────────────────────────────────── -->
    <div class="bg-white rounded-2xl border border-slate-100 custom-shadow-low overflow-hidden chat-wrapper">

        <!-- Header -->
        <div class="flex items-center gap-4 px-5 py-4 border-b border-slate-100">
            <a href="/upc_freelance/app/profile/freelancer-profile.php?id=<?= $partnerId ?>">
                <?= renderAvatar($partner['avatar'] ?? null, $partner['first_name'], $partner['last_name'], (bool)$partner['is_verified'], 'w-11 h-11', 'rounded-full') ?>
            </a>
            <div class="flex-1 min-w-0">
                <p class="font-bold text-primary flex items-center gap-1.5">
                    <?= h($partner['first_name'] . ' ' . $partner['last_name']) ?>
                    <?php if ($partner['is_verified']): ?>
                    <span class="material-symbols-outlined text-secondary text-base"
                          style="font-variation-settings:'FILL' 1">verified</span>
                    <?php endif; ?>
                </p>
                <p class="text-xs text-slate-400 flex items-center gap-1.5">
                    <span style="display:inline-block;width:7px;height:7px;border-radius:50%;
                                 background:<?= $partner['role'] === 'freelancer' ? '#8b5cf6' : '#0061a5' ?>;">
                    </span>
                    <?= $partner['role'] === 'freelancer' ? 'Freelancer' : 'Client' ?>
                </p>
            </div>
            <!-- Lien profil -->
            <?php $profileUrl = $partner['role'] === 'freelancer'
                ? '/upc_freelance/app/profile/freelancer-profile.php?id=' . $partnerId
                : '/upc_freelance/app/profile/client-profile.php?id=' . $partnerId; ?>
            <a href="<?= $profileUrl ?>"
               class="flex items-center gap-1.5 text-xs text-secondary border border-secondary/30
                      px-3 py-1.5 rounded-full hover:bg-secondary/5 transition-colors">
                <span class="material-symbols-outlined text-sm">person</span> Voir le profil
            </a>
        </div>

        <!-- Messages -->
        <div class="chat-messages p-5 space-y-4" id="dm-container">
            <?php if (empty($messages)): ?>
            <div class="text-center py-12" id="dm-empty">
                <span class="material-symbols-outlined text-4xl text-slate-300 block mb-3">chat_bubble</span>
                <p class="text-on-surface-variant text-sm">
                    Démarrez la conversation avec
                    <strong><?= h($partner['first_name']) ?></strong> !
                </p>
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
                    <div class="px-4 py-2.5 rounded-2xl
                                <?= $isMe ? 'bg-primary text-white rounded-tr-sm' : 'bg-surface-container-low text-on-surface rounded-tl-sm' ?>">
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

        <!-- Saisie -->
        <div class="chat-footer px-4 py-4">
            <form id="dm-form" class="flex gap-3">
                <?= csrfField() ?>
                <input type="hidden" name="send_dm" value="1"/>
                <input type="text" name="body" id="dm-input"
                       placeholder="Écrire un message à <?= h($partner['first_name']) ?>..."
                       required autocomplete="off"
                       class="flex-1 px-4 py-2.5 rounded-full border border-outline-variant
                              focus:border-secondary focus:ring-2 focus:ring-secondary/20
                              outline-none text-sm transition-all bg-surface-container-low"/>
                <button type="submit" id="dm-send"
                        class="w-10 h-10 bg-primary text-white rounded-full flex items-center justify-center
                               hover:opacity-90 transition-opacity active:scale-95 flex-shrink-0">
                    <span class="material-symbols-outlined">send</span>
                </button>
            </form>
        </div>
    </div>
</div>

<script>
(function () {
    const ME        = <?= $user['id'] ?>;
    const PARTNER   = <?= $partnerId ?>;
    const MY_AVATAR = <?= json_encode($user['avatar'] ?? null) ?>;
    const container = document.getElementById('dm-container');
    const form      = document.getElementById('dm-form');
    const input     = document.getElementById('dm-input');
    let lastId      = <?= $lastId ?>;
    let total       = <?= count($messages) ?>;

    function scrollBottom(force) {
        if (!container) return;
        const near = container.scrollHeight - container.scrollTop - container.clientHeight < 120;
        if (force || near) container.scrollTop = container.scrollHeight;
    }
    scrollBottom(true);

    function esc(s) {
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }
    function timeAgoJs(d) {
        const s = Math.floor((Date.now() - new Date(d)) / 1000);
        if (s < 60)    return 'À l\'instant';
        if (s < 3600)  return Math.floor(s/60) + ' min';
        if (s < 86400) return Math.floor(s/3600) + ' h';
        return Math.floor(s/86400) + ' j';
    }
    function nl2br(s) { return esc(s).replace(/\n/g, '<br>'); }

    function buildBubble(msg) {
        const isMe    = parseInt(msg.sender_id) === ME;
        const align   = isMe ? 'justify-end' : 'justify-start';
        const bubble  = isMe ? 'bg-primary text-white rounded-tr-sm' : 'bg-surface-container-low text-on-surface rounded-tl-sm';
        const tAlign  = isMe ? 'text-right mr-1' : 'ml-1';
        const init    = msg.first_name ? msg.first_name[0].toUpperCase() : '?';
        const avPath  = isMe ? MY_AVATAR : (msg.avatar || null);
        const avHtml  = avPath
            ? `<img src="/upc_freelance/storage/${esc(avPath)}" class="w-8 h-8 rounded-full object-cover flex-shrink-0 mt-auto"/>`
            : `<div class="w-8 h-8 rounded-full ${isMe?'bg-secondary/10 text-secondary':'bg-primary/10 text-primary'} flex items-center justify-center text-xs font-bold flex-shrink-0 mt-auto">${init}</div>`;
        const senderName = !isMe ? `<p class="text-xs text-slate-400 mb-1 ml-1">${esc(msg.first_name)}</p>` : '';

        const div = document.createElement('div');
        div.className = `flex ${align} gap-2`;
        div.setAttribute('data-msg-id', msg.id);
        div.innerHTML = `
            ${!isMe ? avHtml : ''}
            <div class="max-w-[70%]">
                ${senderName}
                <div class="px-4 py-2.5 rounded-2xl ${bubble}">
                    <p class="text-sm leading-relaxed">${nl2br(msg.body)}</p>
                </div>
                <p class="text-xs text-slate-400 mt-1 ${tAlign}">${timeAgoJs(msg.created_at)}</p>
            </div>
            ${isMe ? avHtml : ''}`;
        return div;
    }

    function appendMessages(msgs) {
        if (!msgs.length) return;
        const empty = document.getElementById('dm-empty');
        if (empty) empty.remove();
        msgs.forEach(m => {
            if (container.querySelector(`[data-msg-id="${m.id}"]`)) return;
            container.appendChild(buildBubble(m));
            lastId = Math.max(lastId, parseInt(m.id));
        });
        scrollBottom(false);
    }

    // Polling nouveaux messages
    async function poll() {
        try {
            const res = await fetch(
                `/upc_freelance/app/messages/api-direct.php?partner_id=${PARTNER}&since=${lastId}`,
                { credentials: 'same-origin' }
            );
            if (!res.ok) return;
            appendMessages(await res.json());
        } catch(e) {}
    }
    setInterval(poll, 500);

    // Envoi AJAX
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const body = input.value.trim();
        if (!body) return;

        const btn = document.getElementById('dm-send');
        btn.disabled = input.disabled = true;

        // Optimiste
        const opt = buildBubble({
            id: 'tmp_' + Date.now(), body,
            sender_id: ME, created_at: new Date().toISOString(),
            first_name: '', avatar: null
        });
        opt.style.opacity = '0.6';
        const empty = document.getElementById('dm-empty');
        if (empty) empty.remove();
        container.appendChild(opt);
        scrollBottom(true);
        input.value = '';

        try {
            const fd = new FormData(form);
            fd.set('body', body);
            const res = await fetch(
                `/upc_freelance/app/messages/direct.php?user_id=${PARTNER}`,
                { method:'POST', headers:{'X-Requested-With':'XMLHttpRequest'}, credentials:'same-origin', body:fd }
            );
            if (res.ok) { opt.remove(); await poll(); }
            else opt.style.opacity = '1';
        } catch(err) { opt.style.opacity = '1'; }
        finally { btn.disabled = input.disabled = false; input.focus(); }
    });
})();
</script>

<?php $appLayout = true; require_once '../../includes/footer.php'; ?>