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
$tab    = in_array($_GET['tab'] ?? '', ['contracts','direct']) ? $_GET['tab'] : 'contracts';

// ── Conversations contrats ────────────────────────────────────
$stmt = $pdo->prepare('
    SELECT ct.*,
           p.title AS project_title,
           CASE WHEN ct.client_id = ? THEN fr.first_name ELSE cl.first_name END AS partner_fname,
           CASE WHEN ct.client_id = ? THEN fr.last_name  ELSE cl.last_name  END AS partner_lname,
           CASE WHEN ct.client_id = ? THEN fr.avatar     ELSE cl.avatar     END AS partner_avatar,
           CASE WHEN ct.client_id = ? THEN fr.is_verified ELSE cl.is_verified END AS partner_verified,
           (SELECT body       FROM messages WHERE contract_id = ct.id ORDER BY created_at DESC LIMIT 1) AS last_msg,
           (SELECT created_at FROM messages WHERE contract_id = ct.id ORDER BY created_at DESC LIMIT 1) AS last_msg_at,
           (SELECT COUNT(*)   FROM messages WHERE contract_id = ct.id AND sender_id != ? AND is_read = 0) AS unread
    FROM contracts ct
    JOIN projects p ON p.id = ct.project_id
    JOIN users cl   ON cl.id = ct.client_id
    JOIN users fr   ON fr.id = ct.freelancer_id
    WHERE (ct.client_id = ? OR ct.freelancer_id = ?)
    ORDER BY last_msg_at DESC, ct.created_at DESC
');
$stmt->execute([$userId,$userId,$userId,$userId,$userId,$userId,$userId]);
$conversations = $stmt->fetchAll();

// ── Conversations directes ────────────────────────────────────
// Stratégie : récupérer tous les partenaires uniques avec qui
// l'utilisateur a échangé, puis le dernier message + nb non lus.
$directConvs = [];
try {
    // 1. Récupérer les IDs partenaires uniques
    $stmtP = $pdo->prepare('
        SELECT DISTINCT
            CASE WHEN sender_id = :uid THEN receiver_id ELSE sender_id END AS partner_id
        FROM direct_messages
        WHERE sender_id = :uid2 OR receiver_id = :uid3
    ');
    $stmtP->execute([':uid' => $userId, ':uid2' => $userId, ':uid3' => $userId]);
    $partnerIds = $stmtP->fetchAll(PDO::FETCH_COLUMN);

    if (!empty($partnerIds)) {
        $placeholders = implode(',', array_fill(0, count($partnerIds), '?'));
        // 2. Infos partenaires
        $stmtU = $pdo->prepare("
            SELECT id, first_name, last_name, avatar, role, is_verified
            FROM users WHERE id IN ($placeholders)
        ");
        $stmtU->execute($partnerIds);
        $partnerInfos = [];
        foreach ($stmtU->fetchAll() as $p) {
            $partnerInfos[$p['id']] = $p;
        }

        // 3. Pour chaque partenaire : dernier msg + unread
        foreach ($partnerIds as $pid) {
            if (!isset($partnerInfos[$pid])) continue;
            $p = $partnerInfos[$pid];

            // Dernier message
            $stmtLast = $pdo->prepare('
                SELECT body, created_at FROM direct_messages
                WHERE (sender_id = ? AND receiver_id = ?)
                   OR (sender_id = ? AND receiver_id = ?)
                ORDER BY created_at DESC LIMIT 1
            ');
            $stmtLast->execute([$userId, $pid, $pid, $userId]);
            $last = $stmtLast->fetch();

            // Non lus
            $stmtUnread = $pdo->prepare('
                SELECT COUNT(*) FROM direct_messages
                WHERE sender_id = ? AND receiver_id = ? AND is_read = 0
            ');
            $stmtUnread->execute([$pid, $userId]);
            $unread = (int)$stmtUnread->fetchColumn();

            $directConvs[] = [
                'partner_id'       => $pid,
                'partner_fname'    => $p['first_name'],
                'partner_lname'    => $p['last_name'],
                'partner_avatar'   => $p['avatar'],
                'partner_verified' => $p['is_verified'],
                'partner_role'     => $p['role'],
                'last_msg'         => $last['body'] ?? null,
                'last_msg_at'      => $last['created_at'] ?? null,
                'unread'           => $unread,
            ];
        }

        // Trier par dernier message
        usort($directConvs, fn($a, $b) =>
            strtotime($b['last_msg_at'] ?? '0') - strtotime($a['last_msg_at'] ?? '0')
        );
    }
} catch (\Throwable $e) {
    // Table peut ne pas exister encore
}

// Comptage badges
$contractUnread = array_sum(array_column($conversations, 'unread'));
$directUnread   = array_sum(array_column($directConvs, 'unread'));

$pageTitle = 'Messages — UPC Freelance';
$appLayout = true;
require_once '../../includes/header.php';
?>

<style>
.tab-btn {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 20px;
    font-size: 13px;
    font-weight: 600;
    border-radius: 10px;
    border: none;
    cursor: pointer;
    transition: all 0.15s;
    background: transparent;
    color: #64748b;
}
.tab-btn.active {
    background: #fff;
    color: #002045;
    box-shadow: 0 2px 8px rgba(26,54,93,0.1);
}
.tab-btn:hover:not(.active) { background: #f1f5f9; color: #002045; }
.unread-dot {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 18px;
    height: 18px;
    padding: 0 5px;
    font-size: 10px;
    font-weight: 700;
    border-radius: 999px;
    background: #0061a5;
    color: #fff;
}
.conv-row {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 14px 20px;
    text-decoration: none;
    transition: background 0.12s;
    border-bottom: 1px solid #f8faff;
}
.conv-row:hover { background: #f8faff; }
.conv-row.unread-row { background: rgba(219,234,254,0.2); }
</style>

<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-primary">Messagerie</h1>
        <p class="text-on-surface-variant text-sm mt-1">Tous vos échanges au même endroit.</p>
    </div>
    <!-- Bouton nouveau message direct -->
    <a href="/upc_freelance/app/messages/new.php"
       class="inline-flex items-center gap-2 bg-secondary text-white px-4 py-2.5 rounded-xl
              text-sm font-semibold hover:opacity-90 transition-opacity active:scale-95">
        <span class="material-symbols-outlined text-base">edit</span>
        Nouveau message
    </a>
</div>

<!-- ── Onglets ──────────────────────────────────────────────── -->
<div style="background:#f1f5f9;border-radius:14px;padding:4px;display:inline-flex;gap:2px;margin-bottom:20px;">
    <a href="?tab=contracts"
       class="tab-btn <?= $tab === 'contracts' ? 'active' : '' ?>">
        <span class="material-symbols-outlined text-base">description</span>
        Contrats
        <?php if ($contractUnread > 0): ?>
        <span class="unread-dot"><?= $contractUnread > 9 ? '9+' : $contractUnread ?></span>
        <?php endif; ?>
    </a>
    <a href="?tab=direct"
       class="tab-btn <?= $tab === 'direct' ? 'active' : '' ?>">
        <span class="material-symbols-outlined text-base">chat_bubble</span>
        Messages directs
        <?php if ($directUnread > 0): ?>
        <span class="unread-dot"><?= $directUnread > 9 ? '9+' : $directUnread ?></span>
        <?php endif; ?>
    </a>
</div>

<!-- ── ONGLET CONTRATS ──────────────────────────────────────── -->
<?php if ($tab === 'contracts'): ?>
<div class="bg-white rounded-2xl border border-slate-100 custom-shadow-low overflow-hidden" id="conversations-wrap">
    <div id="conversations-list">
        <?php if (empty($conversations)): ?>
        <div class="p-16 text-center">
            <span class="material-symbols-outlined text-5xl text-slate-300 block mb-4">description</span>
            <h3 class="font-semibold text-primary mb-2">Aucun contrat actif</h3>
            <p class="text-on-surface-variant text-sm mb-6">
                Les conversations de contrat apparaissent ici une fois un contrat créé.
            </p>
            <?php if ($user['role'] === 'client'): ?>
            <a href="/upc_freelance/app/projects/create.php"
               class="inline-block bg-primary text-white px-6 py-3 rounded-xl text-sm font-semibold hover:opacity-90">
                Créer un projet
            </a>
            <?php else: ?>
            <a href="/upc_freelance/app/projects/list.php"
               class="inline-block bg-primary text-white px-6 py-3 rounded-xl text-sm font-semibold hover:opacity-90">
                Parcourir les projets
            </a>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <?php foreach ($conversations as $conv): ?>
        <?php
        $dotColors = ['active'=>'#22c55e','completed'=>'#94a3b8','cancelled'=>'#ef4444'];
        $dot = $dotColors[$conv['status']] ?? '#94a3b8';
        ?>
        <a href="/upc_freelance/app/contracts/details.php?id=<?= $conv['id'] ?>#chat"
           class="conv-row <?= $conv['unread'] > 0 ? 'unread-row' : '' ?>">
            <!-- Avatar -->
            <div style="position:relative;flex-shrink:0;">
                <?= renderAvatar($conv['partner_avatar'] ?? null, $conv['partner_fname'], $conv['partner_lname'], (bool)($conv['partner_verified'] ?? false), 'w-12 h-12', 'rounded-full') ?>
                <span style="position:absolute;bottom:0;right:0;width:11px;height:11px;border-radius:50%;background:<?= $dot ?>;border:2px solid #fff;"></span>
            </div>
            <!-- Contenu -->
            <div style="flex:1;min-width:0;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:2px;">
                    <p style="font-weight:<?= $conv['unread'] > 0 ? '700' : '600' ?>;color:#002045;font-size:14px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                        <?= h($conv['partner_fname'] . ' ' . $conv['partner_lname']) ?>
                    </p>
                    <span style="font-size:11px;color:#94a3b8;white-space:nowrap;margin-left:8px;">
                        <?= $conv['last_msg_at'] ? timeAgo($conv['last_msg_at']) : '' ?>
                    </span>
                </div>
                <!-- Titre projet avec icône -->
                <p style="font-size:11px;color:#64748b;margin-bottom:3px;display:flex;align-items:center;gap:4px;">
                    <span class="material-symbols-outlined" style="font-size:12px;">work</span>
                    <?= h(truncate($conv['project_title'], 40)) ?>
                </p>
                <p style="font-size:13px;color:#64748b;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-weight:<?= $conv['unread'] > 0 ? '500' : '400' ?>;">
                    <?= $conv['last_msg'] ? h(truncate($conv['last_msg'], 55)) : '<em style="color:#94a3b8">Aucun message</em>' ?>
                </p>
            </div>
            <!-- Badge non lu -->
            <?php if ($conv['unread'] > 0): ?>
            <span class="unread-dot"><?= $conv['unread'] > 9 ? '9+' : $conv['unread'] ?></span>
            <?php endif; ?>
        </a>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- ── ONGLET MESSAGES DIRECTS ──────────────────────────────── -->
<?php else: ?>
<div class="bg-white rounded-2xl border border-slate-100 custom-shadow-low overflow-hidden">
    <div id="direct-list">
        <?php if (empty($directConvs)): ?>
        <div class="p-16 text-center">
            <span class="material-symbols-outlined text-5xl text-slate-300 block mb-4">chat_bubble</span>
            <h3 class="font-semibold text-primary mb-2">Aucun message direct</h3>
            <p class="text-on-surface-variant text-sm mb-6">
                Envoyez un message à n'importe quel utilisateur sans avoir de contrat.
            </p>
            <a href="/upc_freelance/app/messages/new.php"
               class="inline-flex items-center gap-2 bg-secondary text-white px-6 py-3 rounded-xl text-sm font-semibold hover:opacity-90">
                <span class="material-symbols-outlined text-base">edit</span>
                Démarrer une conversation
            </a>
        </div>
        <?php else: ?>
        <?php foreach ($directConvs as $dm): ?>
        <a href="/upc_freelance/app/messages/direct.php?user_id=<?= $dm['partner_id'] ?>"
           class="conv-row <?= $dm['unread'] > 0 ? 'unread-row' : '' ?>">
            <!-- Avatar -->
            <div style="position:relative;flex-shrink:0;">
                <?= renderAvatar($dm['partner_avatar'] ?? null, $dm['partner_fname'], $dm['partner_lname'], (bool)($dm['partner_verified'] ?? false), 'w-12 h-12', 'rounded-full') ?>
                <!-- Dot rôle -->
                <span style="position:absolute;bottom:0;right:0;width:11px;height:11px;border-radius:50%;
                             background:<?= $dm['partner_role'] === 'freelancer' ? '#8b5cf6' : '#0061a5' ?>;
                             border:2px solid #fff;" title="<?= $dm['partner_role'] ?>"></span>
            </div>
            <!-- Contenu -->
            <div style="flex:1;min-width:0;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:2px;">
                    <div style="display:flex;align-items:center;gap:6px;overflow:hidden;">
                        <p style="font-weight:<?= $dm['unread'] > 0 ? '700' : '600' ?>;color:#002045;font-size:14px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                            <?= h($dm['partner_fname'] . ' ' . $dm['partner_lname']) ?>
                        </p>
                        <span style="font-size:10px;font-weight:700;padding:1px 6px;border-radius:999px;
                                     background:<?= $dm['partner_role'] === 'freelancer' ? '#f3e8ff' : '#dbeafe' ?>;
                                     color:<?= $dm['partner_role'] === 'freelancer' ? '#7c3aed' : '#1d4ed8' ?>;
                                     white-space:nowrap;">
                            <?= $dm['partner_role'] === 'freelancer' ? 'Freelancer' : 'Client' ?>
                        </span>
                    </div>
                    <span style="font-size:11px;color:#94a3b8;white-space:nowrap;margin-left:8px;">
                        <?= $dm['last_msg_at'] ? timeAgo($dm['last_msg_at']) : '' ?>
                    </span>
                </div>
                <p style="font-size:13px;color:#64748b;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-weight:<?= $dm['unread'] > 0 ? '500' : '400' ?>;">
                    <?= $dm['last_msg'] ? h(truncate($dm['last_msg'], 55)) : '<em style="color:#94a3b8">Démarrer la conversation...</em>' ?>
                </p>
            </div>
            <!-- Badge non lu -->
            <?php if ($dm['unread'] > 0): ?>
            <span class="unread-dot"><?= $dm['unread'] > 9 ? '9+' : $dm['unread'] ?></span>
            <?php endif; ?>
        </a>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<script>
(function () {
    // Polling seulement pour l'onglet contrats
    <?php if ($tab === 'contracts'): ?>
    function timeAgoJs(d) {
        if (!d) return '';
        const s = Math.floor((Date.now() - new Date(d)) / 1000);
        if (s < 60)     return 'À l\'instant';
        if (s < 3600)   return Math.floor(s/60) + ' min';
        if (s < 86400)  return Math.floor(s/3600) + ' h';
        return Math.floor(s/86400) + ' j';
    }
    function esc(s) {
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }
    function trunc(s, n) { return s && s.length > n ? s.slice(0,n)+'…' : (s||''); }

    const statusDots = { active:'#22c55e', completed:'#94a3b8', cancelled:'#ef4444' };

    async function poll() {
        try {
            const res = await fetch('/upc_freelance/app/messages/api-conversations.php', { credentials:'same-origin' });
            if (!res.ok) return;
            const data = await res.json();
            const list = document.getElementById('conversations-list');
            if (!list || !data.length) return;

            list.innerHTML = data.map(c => {
                const dot    = statusDots[c.status] || '#94a3b8';
                const unread = parseInt(c.unread) || 0;
                const init   = c.partner_fname ? c.partner_fname[0].toUpperCase() : '?';
                const img    = c.partner_avatar
                    ? `<img src="/upc_freelance/storage/${esc(c.partner_avatar)}" class="w-12 h-12 rounded-full object-cover"/>`
                    : `<div class="w-12 h-12 rounded-full bg-primary/10 flex items-center justify-center text-lg font-bold text-primary">${init}</div>`;
                const badge  = c.partner_verified && parseInt(c.partner_verified)
                    ? `<span style="position:absolute;bottom:-1px;right:-1px;background:#fff;border-radius:50%;line-height:1"><span class="material-symbols-outlined text-secondary" style="font-size:14px;font-variation-settings:'FILL' 1">verified</span></span>`
                    : '';
                const unreadBadge = unread > 0
                    ? `<span class="unread-dot">${unread > 9 ? '9+' : unread}</span>` : '';
                const lastMsg = c.last_msg
                    ? esc(trunc(c.last_msg, 55))
                    : '<em style="color:#94a3b8">Aucun message</em>';
                return `
                <a href="/upc_freelance/app/contracts/details.php?id=${esc(c.id)}#chat"
                   class="conv-row${unread > 0 ? ' unread-row' : ''}">
                    <div style="position:relative;flex-shrink:0;">
                        <div style="position:relative;display:inline-flex;">${img}${badge}</div>
                        <span style="position:absolute;bottom:0;right:0;width:11px;height:11px;border-radius:50%;background:${dot};border:2px solid #fff;"></span>
                    </div>
                    <div style="flex:1;min-width:0;">
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:2px;">
                            <p style="font-weight:${unread>0?'700':'600'};color:#002045;font-size:14px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${esc(c.partner_fname+' '+c.partner_lname)}</p>
                            <span style="font-size:11px;color:#94a3b8;white-space:nowrap;margin-left:8px;">${timeAgoJs(c.last_msg_at)}</span>
                        </div>
                        <p style="font-size:11px;color:#64748b;margin-bottom:3px;display:flex;align-items:center;gap:4px;">
                            <span class="material-symbols-outlined" style="font-size:12px;">work</span>${esc(trunc(c.project_title,40))}
                        </p>
                        <p style="font-size:13px;color:#64748b;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-weight:${unread>0?'500':'400'};">${lastMsg}</p>
                    </div>
                    ${unreadBadge}
                </a>`;
            }).join('');
        } catch(e) {}
    }
    setInterval(poll, 4000);
    <?php endif; ?>
})();
</script>

<?php $appLayout = true; require_once '../../includes/footer.php'; ?>