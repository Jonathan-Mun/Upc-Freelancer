<?php
// ============================================================
// UPC FREELANCE — Notifications
// ../../app/notifications/index.php
// ============================================================

require_once '../../includes/middleware.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../includes/db.php';

requireLogin();

$user = currentUser();
$pdo  = getDB();

// Marquer tout comme lu si demandé
if (isset($_GET['mark_all_read'])) {
    $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ?')->execute([$user['id']]);
    flash('success', 'Toutes les notifications ont été lues.');
    redirect('../../app/notifications/index.php');
}

// Marquer une comme lue
if (isset($_GET['read'])) {
    $notifId = (int)$_GET['read'];
    $stmt    = $pdo->prepare('SELECT * FROM notifications WHERE id = ? AND user_id = ?');
    $stmt->execute([$notifId, $user['id']]);
    $notif = $stmt->fetch();
    if ($notif) {
        $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE id = ?')->execute([$notifId]);
        if ($notif['link']) redirect($notif['link']);
    }
    redirect('../../app/notifications/index.php');
}

$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset  = ($page - 1) * $perPage;

$stmt = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ?');
$stmt->execute([$user['id']]);
$total = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare('SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?');
$stmt->execute([$user['id'], $perPage, $offset]);
$notifications = $stmt->fetchAll();

$unreadCount = countUnreadNotifications($user['id']);

$pageTitle = 'Notifications — UPC Freelance';
$appLayout = true;
require_once '../../includes/header.php';
?>
<br>
<?php renderFlash(); ?>

<!-- En-tête -->
<div class="flex items-center justify-between mb-8">
    <div>
        <h1 class="text-2xl font-bold text-primary">Notifications</h1>
        <p class="text-on-surface-variant text-sm mt-1" id="notif-summary">
            <?= $unreadCount > 0 ? "<strong>$unreadCount</strong> non lue(s)" : 'Tout est lu' ?>
        </p>
    </div>
    <button id="btn-mark-all"
            class="inline-flex items-center gap-1.5 text-sm text-secondary hover:underline <?= $unreadCount > 0 ? '' : 'invisible' ?>">
        <span class="material-symbols-outlined text-base">done_all</span> Tout marquer comme lu
    </button>
</div>

<!-- Container liste (mis à jour en live) -->
<div id="notif-container">
<?php if (empty($notifications)): ?>
    <?= renderEmptyState() ?>
<?php else: ?>
    <?= renderNotifList($notifications) ?>
<?php endif; ?>
</div>

<!-- Pagination (statique, reste en place) -->
<?php $totalPages = (int)ceil($total / $perPage); if ($totalPages > 1): ?>
<div class="flex justify-center gap-2 mt-6">
    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
    <a href="?page=<?= $i ?>"
       class="px-4 py-2 rounded-xl border text-sm transition-colors <?= $i === $page ? 'bg-primary text-white border-primary' : 'border-slate-200 hover:border-secondary hover:text-secondary' ?>">
        <?= $i ?>
    </a>
    <?php endfor; ?>
</div>
<?php endif; ?>

<?php
// ── Helpers HTML ────────────────────────────────────────
function renderEmptyState(): string {
    return '<div class="bg-white rounded-2xl border border-slate-100 p-16 text-center custom-shadow-low">
        <span class="material-symbols-outlined text-5xl text-slate-300 block mb-4">notifications_off</span>
        <h3 class="font-semibold text-primary mb-2">Aucune notification</h3>
        <p class="text-on-surface-variant text-sm">Vous êtes à jour !</p>
    </div>';
}

function renderNotifList(array $notifications): string {
    $typeIcons = [
        'welcome'              => ['icon'=>'celebration',  'color'=>'text-purple-500', 'bg'=>'bg-purple-50'],
        'new_application'      => ['icon'=>'inbox',        'color'=>'text-amber-500',  'bg'=>'bg-amber-50'],
        'application_accepted' => ['icon'=>'check_circle', 'color'=>'text-green-500',  'bg'=>'bg-green-50'],
        'application_rejected' => ['icon'=>'cancel',       'color'=>'text-red-400',    'bg'=>'bg-red-50'],
        'new_message'          => ['icon'=>'chat',         'color'=>'text-blue-500',   'bg'=>'bg-blue-50'],
        'payment_received'     => ['icon'=>'payments',     'color'=>'text-emerald-500','bg'=>'bg-emerald-50'],
        'deposit_success'      => ['icon'=>'add_circle',   'color'=>'text-green-500',  'bg'=>'bg-green-50'],
        'contract_created'     => ['icon'=>'description',  'color'=>'text-secondary',  'bg'=>'bg-blue-50'],
    ];

    $html = '<div class="bg-white rounded-2xl border border-slate-100 custom-shadow-low overflow-hidden">
        <div class="divide-y divide-slate-50" id="notif-list">';

    foreach ($notifications as $n) {
        $ti = $typeIcons[$n['type']] ?? ['icon'=>'notifications','color'=>'text-slate-400','bg'=>'bg-slate-50'];
        $html .= renderNotifRow($n, $ti);
    }

    $html .= '</div></div>';
    return $html;
}

function renderNotifRow(array $n, array $ti): string {
    $unreadBg = !$n['is_read'] ? 'bg-blue-50/40' : '';
    $dot      = !$n['is_read'] ? '<div class="w-2.5 h-2.5 bg-secondary rounded-full flex-shrink-0"></div>' : '';
    $link     = $n['link'] ? '<a href="?read='.$n['id'].'" class="text-xs text-secondary hover:underline whitespace-nowrap">Voir →</a>' : '';
    $body     = $n['body'] ? '<p class="text-sm text-on-surface-variant mt-0.5">'.h($n['body']).'</p>' : '';
    $ago      = timeAgo($n['created_at']);

    return '<div class="flex items-start gap-4 p-4 '.$unreadBg.' hover:bg-surface-container-low transition-colors" data-notif-id="'.$n['id'].'">
        <div class="w-10 h-10 rounded-full '.$ti['bg'].' flex items-center justify-center flex-shrink-0 mt-0.5">
            <span class="material-symbols-outlined '.$ti['color'].' text-xl">'.$ti['icon'].'</span>
        </div>
        <div class="flex-1 min-w-0">
            <p class="text-sm font-semibold text-primary">'.h($n['title']).'</p>
            '.$body.'
            <p class="text-xs text-slate-400 mt-1">'.$ago.'</p>
        </div>
        <div class="flex items-center gap-2 flex-shrink-0">'.$link.$dot.'</div>
    </div>';
}
?>

<script>
// ── Page notifications — live refresh ────────────────────
(function () {
    const API = '/upc_freelance/app/notifications/api-notifications.php';

    const typeIcons = {
        welcome:              { icon: 'celebration',  color: 'text-purple-500', bg: 'bg-purple-50'  },
        new_application:      { icon: 'inbox',        color: 'text-amber-500',  bg: 'bg-amber-50'   },
        application_accepted: { icon: 'check_circle', color: 'text-green-500',  bg: 'bg-green-50'   },
        application_rejected: { icon: 'cancel',       color: 'text-red-400',    bg: 'bg-red-50'     },
        new_message:          { icon: 'chat',         color: 'text-blue-500',   bg: 'bg-blue-50'    },
        payment_received:     { icon: 'payments',     color: 'text-emerald-500',bg: 'bg-emerald-50' },
        deposit_success:      { icon: 'add_circle',   color: 'text-green-500',  bg: 'bg-green-50'   },
        contract_created:     { icon: 'description',  color: 'text-secondary',  bg: 'bg-blue-50'    },
    };

    function makeRow(n) {
        const ti      = typeIcons[n.type] ?? { icon: 'notifications', color: 'text-slate-400', bg: 'bg-slate-50' };
        const unreadBg = !n.is_read ? 'bg-blue-50/40' : '';
        const dot     = !n.is_read ? '<div class="w-2.5 h-2.5 bg-secondary rounded-full flex-shrink-0"></div>' : '';
        const link    = n.link ? `<a href="?read=${n.id}" class="text-xs text-secondary hover:underline whitespace-nowrap">Voir →</a>` : '';
        const body    = n.body ? `<p class="text-sm text-on-surface-variant mt-0.5">${n.body}</p>` : '';
        const div = document.createElement('div');
        div.className = `flex items-start gap-4 p-4 ${unreadBg} hover:bg-surface-container-low transition-colors`;
        div.dataset.notifId = n.id;
        div.style.animation = 'fadeInRow .3s ease';
        div.innerHTML = `
            <div class="w-10 h-10 rounded-full ${ti.bg} flex items-center justify-center flex-shrink-0 mt-0.5">
                <span class="material-symbols-outlined ${ti.color} text-xl">${ti.icon}</span>
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-semibold text-primary">${n.title}</p>
                ${body}
                <p class="text-xs text-slate-400 mt-1">À l'instant</p>
            </div>
            <div class="flex items-center gap-2 flex-shrink-0">${link}${dot}</div>
        `;
        return div;
    }

    function updateSummary(count) {
        const el = document.getElementById('notif-summary');
        if (el) el.innerHTML = count > 0 ? `<strong>${count}</strong> non lue(s)` : 'Tout est lu';
        const btn = document.getElementById('btn-mark-all');
        if (btn) btn.classList.toggle('invisible', count === 0);
    }

    // Injecter les nouvelles notifs en haut de la liste
    function refresh(newNotifs, unreadCount) {
        let list = document.getElementById('notif-list');

        // S'il n'y avait rien avant, reconstruire le container
        if (!list) {
            document.getElementById('notif-container').innerHTML = `
                <div class="bg-white rounded-2xl border border-slate-100 custom-shadow-low overflow-hidden">
                    <div class="divide-y divide-slate-50" id="notif-list"></div>
                </div>`;
            list = document.getElementById('notif-list');
        }

        newNotifs.forEach(n => {
            // Ne pas dupliquer
            if (list.querySelector(`[data-notif-id="${n.id}"]`)) return;
            list.prepend(makeRow(n));
        });

        updateSummary(unreadCount);
    }

    // Bouton "Tout marquer comme lu"
    document.getElementById('btn-mark-all')?.addEventListener('click', async () => {
        await fetch(API + '?action=mark_all', { method: 'POST', credentials: 'same-origin' });
        // Retirer les points bleus et les fonds colorés
        document.querySelectorAll('[data-notif-id]').forEach(row => {
            row.classList.remove('bg-blue-50/40');
            const dot = row.querySelector('.bg-secondary.rounded-full');
            if (dot) dot.remove();
        });
        updateSummary(0);
        if (window.UPCNotifBadge) window.UPCNotifBadge.updateBadges(0);
    });

    // Keyframe
    const s = document.createElement('style');
    s.textContent = `@keyframes fadeInRow {
        from { opacity:0; transform: translateY(-6px); }
        to   { opacity:1; transform: translateY(0); }
    }`;
    document.head.appendChild(s);

    // Exposer pour le polling du header
    window.UPCNotifPage = { refresh };
})();
</script>

<?php
$appLayout = true;
require_once '../../includes/footer.php';
?>