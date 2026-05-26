<?php
// ============================================================
// UPC FREELANCE — Messagerie / Inbox
// /var/www/html/upc_freelance/app/messages/inbox.php
// ============================================================

require_once '../../includes/middleware.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../includes/db.php';

requireLogin();

$user   = currentUser();
$pdo    = getDB();
$userId = $user['id'];

$stmt = $pdo->prepare('
    SELECT ct.*,
           p.title AS project_title,
           CASE WHEN ct.client_id = ? THEN fr.first_name ELSE cl.first_name END AS partner_fname,
           CASE WHEN ct.client_id = ? THEN fr.last_name  ELSE cl.last_name  END AS partner_lname,
           CASE WHEN ct.client_id = ? THEN fr.avatar     ELSE cl.avatar     END AS partner_avatar,
           (SELECT body        FROM messages WHERE contract_id = ct.id ORDER BY created_at DESC LIMIT 1) AS last_msg,
           (SELECT created_at  FROM messages WHERE contract_id = ct.id ORDER BY created_at DESC LIMIT 1) AS last_msg_at,
           (SELECT COUNT(*)    FROM messages WHERE contract_id = ct.id AND sender_id != ? AND is_read = 0) AS unread
    FROM contracts ct
    JOIN projects p ON p.id = ct.project_id
    JOIN users cl   ON cl.id = ct.client_id
    JOIN users fr   ON fr.id = ct.freelancer_id
    WHERE (ct.client_id = ? OR ct.freelancer_id = ?)
    ORDER BY last_msg_at DESC, ct.created_at DESC
');
$stmt->execute([$userId,$userId,$userId,$userId,$userId,$userId]);
$conversations = $stmt->fetchAll();

$pageTitle = 'Messages — UPC Freelance';
$appLayout = true;
require_once '../../includes/header.php';
?>

<div class="flex items-center justify-between mb-8">
    <div>
        <h1 class="text-2xl font-bold text-primary">Messagerie</h1>
        <p class="text-on-surface-variant text-sm mt-1" id="conv-count">
            <?= count($conversations) ?> conversation<?= count($conversations) > 1 ? 's' : '' ?>
        </p>
    </div>
</div>

<div class="bg-white rounded-2xl border border-slate-100 custom-shadow-low overflow-hidden" id="conversations-wrap">
    <div id="conversations-list" class="divide-y divide-slate-50">
    <?php if (empty($conversations)): ?>
        <div class="p-16 text-center">
            <span class="material-symbols-outlined text-5xl text-slate-300 block mb-4">chat</span>
            <h3 class="font-semibold text-primary mb-2">Aucune conversation</h3>
            <p class="text-on-surface-variant text-sm mb-6">
                Les conversations apparaissent ici une fois un contrat créé.
            </p>
            <?php if ($user['role'] === 'client'): ?>
            <a href="/upc_freelance/app/projects/create.php"
               class="inline-block bg-primary text-white px-6 py-3 rounded-xl text-sm font-button hover:opacity-90">
                Créer un projet
            </a>
            <?php else: ?>
            <a href="/upc_freelance/app/projects/list.php"
               class="inline-block bg-primary text-white px-6 py-3 rounded-xl text-sm font-button hover:opacity-90">
                Parcourir les projets
            </a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <?php foreach ($conversations as $conv): ?>
        <?php
        $statusColors = ['active'=>'bg-green-400','completed'=>'bg-slate-400','cancelled'=>'bg-red-400'];
        $dotColor     = $statusColors[$conv['status']] ?? 'bg-slate-400';
        ?>
        <a href="/upc_freelance/app/contracts/details.php?id=<?= $conv['id'] ?>#chat"
           class="flex items-center gap-4 px-5 py-4 hover:bg-surface-container-low transition-colors <?= $conv['unread'] > 0 ? 'bg-blue-50/30' : '' ?>">

            <div class="relative flex-shrink-0">
                <?php if ($conv['partner_avatar']): ?>
                <img src="/upc_freelance/storage/<?= h($conv['partner_avatar']) ?>" alt="Avatar"
                     class="w-12 h-12 rounded-full object-cover"/>
                <?php else: ?>
                <div class="w-12 h-12 rounded-full bg-primary/10 flex items-center justify-center text-lg font-bold text-primary">
                    <?= mb_strtoupper(mb_substr($conv['partner_fname'], 0, 1)) ?>
                </div>
                <?php endif; ?>
                <span class="absolute bottom-0 right-0 w-3 h-3 <?= $dotColor ?> rounded-full border-2 border-white"></span>
            </div>

            <div class="flex-1 min-w-0">
                <div class="flex items-center justify-between mb-0.5">
                    <p class="font-semibold text-primary truncate">
                        <?= h($conv['partner_fname'] . ' ' . $conv['partner_lname']) ?>
                    </p>
                    <span class="text-xs text-slate-400 whitespace-nowrap ml-2">
                        <?= $conv['last_msg_at'] ? timeAgo($conv['last_msg_at']) : '' ?>
                    </span>
                </div>
                <p class="text-xs text-on-surface-variant truncate mb-1"><?= h(truncate($conv['project_title'], 40)) ?></p>
                <p class="text-sm text-slate-500 truncate">
                    <?= $conv['last_msg']
                        ? h(truncate($conv['last_msg'], 60))
                        : '<em class="text-slate-400">Aucun message</em>' ?>
                </p>
            </div>

            <?php if ($conv['unread'] > 0): ?>
            <div class="flex-shrink-0">
                <span class="w-6 h-6 bg-secondary text-white text-xs font-bold rounded-full flex items-center justify-center">
                    <?= $conv['unread'] > 9 ? '9+' : $conv['unread'] ?>
                </span>
            </div>
            <?php endif; ?>
        </a>
        <?php endforeach; ?>
    <?php endif; ?>
    </div>
</div>

<script>
(function () {
    // Helpers de formatage côté JS (miroir de timeAgo PHP)
    function timeAgoJs(dateStr) {
        if (!dateStr) return '';
        const diff = Math.floor((Date.now() - new Date(dateStr)) / 1000);
        if (diff < 60)          return 'À l\'instant';
        if (diff < 3600)        return Math.floor(diff / 60) + ' min';
        if (diff < 86400)       return Math.floor(diff / 3600) + ' h';
        if (diff < 2592000)     return Math.floor(diff / 86400) + ' j';
        return new Date(dateStr).toLocaleDateString('fr-FR');
    }

    function truncateJs(str, len) {
        if (!str) return '';
        return str.length > len ? str.substring(0, len) + '…' : str;
    }

    function escHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    const statusDots = { active: 'bg-green-400', completed: 'bg-slate-400', cancelled: 'bg-red-400' };

    function renderConversations(data) {
        const list    = document.getElementById('conversations-list');
        const counter = document.getElementById('conv-count');
        if (!list) return;

        // Compteur
        if (counter) {
            const n = data.length;
            counter.textContent = n + ' conversation' + (n > 1 ? 's' : '');
        }

        if (data.length === 0) {
            // Pas de conversations — on ne touche pas au HTML statique
            return;
        }

        list.innerHTML = data.map(conv => {
            const dot      = statusDots[conv.status] || 'bg-slate-400';
            const unread   = parseInt(conv.unread) || 0;
            const initiale = conv.partner_fname ? conv.partner_fname.charAt(0).toUpperCase() : '?';
            const avatarHtml = conv.partner_avatar
                ? `<img src="/upc_freelance/storage/${escHtml(conv.partner_avatar)}" alt="Avatar" class="w-12 h-12 rounded-full object-cover"/>`
                : `<div class="w-12 h-12 rounded-full bg-primary/10 flex items-center justify-center text-lg font-bold text-primary">${initiale}</div>`;
            const lastMsgHtml = conv.last_msg
                ? escHtml(truncateJs(conv.last_msg, 60))
                : '<em class="text-slate-400">Aucun message</em>';
            const badgeHtml = unread > 0
                ? `<div class="flex-shrink-0">
                     <span class="w-6 h-6 bg-secondary text-white text-xs font-bold rounded-full flex items-center justify-center">
                       ${unread > 9 ? '9+' : unread}
                     </span>
                   </div>`
                : '';

            return `
            <a href="/upc_freelance/app/contracts/details.php?id=${escHtml(conv.id)}#chat"
               class="flex items-center gap-4 px-5 py-4 hover:bg-surface-container-low transition-colors ${unread > 0 ? 'bg-blue-50/30' : ''}">
                <div class="relative flex-shrink-0">
                    ${avatarHtml}
                    <span class="absolute bottom-0 right-0 w-3 h-3 ${dot} rounded-full border-2 border-white"></span>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex items-center justify-between mb-0.5">
                        <p class="font-semibold text-primary truncate">
                            ${escHtml(conv.partner_fname + ' ' + conv.partner_lname)}
                        </p>
                        <span class="text-xs text-slate-400 whitespace-nowrap ml-2">
                            ${timeAgoJs(conv.last_msg_at)}
                        </span>
                    </div>
                    <p class="text-xs text-on-surface-variant truncate mb-1">${escHtml(truncateJs(conv.project_title, 40))}</p>
                    <p class="text-sm text-slate-500 truncate">${lastMsgHtml}</p>
                </div>
                ${badgeHtml}
            </a>`;
        }).join('');
    }

    // Premier chargement JS immédiat pour synchroniser
    async function poll() {
        try {
            const res  = await fetch('/upc_freelance/app/messages/api-conversations.php', {
                credentials: 'same-origin'
            });
            if (!res.ok) return;
            const data = await res.json();
            renderConversations(data);
        } catch (e) { /* silencieux */ }
    }

    // Polling toutes les 4 secondes
    setInterval(poll, 4000);
})();
</script>

<?php $appLayout = true; require_once '../../includes/footer.php'; ?>