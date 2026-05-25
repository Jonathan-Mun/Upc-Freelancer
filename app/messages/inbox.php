<?php
// ============================================================
// UPC FREELANCE — Messagerie / Inbox
// /var/www/html/upc_freelance/app/messages/inbox.php
// ============================================================

require_once '/var/www/html/upc_freelance/includes/middleware.php';
require_once '/var/www/html/upc_freelance/includes/auth.php';
require_once '/var/www/html/upc_freelance/includes/functions.php';
require_once '/var/www/html/upc_freelance/includes/db.php';

requireLogin();

$user   = currentUser();
$pdo    = getDB();
$userId = $user['id'];

// Tous les contrats actifs avec le dernier message
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
require_once '/var/www/html/upc_freelance/includes/header.php';
?>

<div class="flex items-center justify-between mb-8">
    <div>
        <h1 class="text-2xl font-bold text-primary">Messagerie</h1>
        <p class="text-on-surface-variant text-sm mt-1"><?= count($conversations) ?> conversation<?= count($conversations) > 1 ? 's' : '' ?></p>
    </div>
</div>

<?php if (empty($conversations)): ?>
<div class="bg-white rounded-2xl border border-slate-100 p-16 text-center custom-shadow-low">
    <span class="material-symbols-outlined text-5xl text-slate-300 block mb-4">chat</span>
    <h3 class="font-semibold text-primary mb-2">Aucune conversation</h3>
    <p class="text-on-surface-variant text-sm mb-6">
        Les conversations apparaissent ici une fois un contrat créé.
    </p>
    <?php if ($user['role'] === 'client'): ?>
    <a href="/upc_freelance/app/projects/create.php" class="inline-block bg-primary text-white px-6 py-3 rounded-xl text-sm font-button hover:opacity-90">
        Créer un projet
    </a>
    <?php else: ?>
    <a href="/upc_freelance/app/projects/list.php" class="inline-block bg-primary text-white px-6 py-3 rounded-xl text-sm font-button hover:opacity-90">
        Parcourir les projets
    </a>
    <?php endif; ?>
</div>
<?php else: ?>
<div class="bg-white rounded-2xl border border-slate-100 custom-shadow-low overflow-hidden">
    <div class="divide-y divide-slate-50">
        <?php foreach ($conversations as $conv): ?>
        <a href="/upc_freelance/app/contracts/details.php?id=<?= $conv['id'] ?>#chat"
           class="flex items-center gap-4 px-5 py-4 hover:bg-surface-container-low transition-colors <?= $conv['unread'] > 0 ? 'bg-blue-50/30' : '' ?>">

            <!-- Avatar partenaire -->
            <div class="relative flex-shrink-0">
                <div class="w-12 h-12 rounded-full bg-primary/10 flex items-center justify-center text-lg font-bold text-primary">
                    <?= mb_substr($conv['partner_fname'], 0, 1) ?>
                </div>
                <?php
                $statusColors = ['active'=>'bg-green-400','completed'=>'bg-slate-400','cancelled'=>'bg-red-400'];
                $dotColor     = $statusColors[$conv['status']] ?? 'bg-slate-400';
                ?>
                <span class="absolute bottom-0 right-0 w-3 h-3 <?= $dotColor ?> rounded-full border-2 border-white"></span>
            </div>

            <!-- Contenu -->
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
                    <?= $conv['last_msg'] ? h(truncate($conv['last_msg'], 60)) : '<em class="text-slate-400">Aucun message</em>' ?>
                </p>
            </div>

            <!-- Badge non lus -->
            <?php if ($conv['unread'] > 0): ?>
            <div class="flex-shrink-0">
                <span class="w-6 h-6 bg-secondary text-white text-xs font-bold rounded-full flex items-center justify-center">
                    <?= $conv['unread'] > 9 ? '9+' : $conv['unread'] ?>
                </span>
            </div>
            <?php endif; ?>
        </a>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<?php $appLayout = true; require_once '/var/www/html/upc_freelance/includes/footer.php'; ?>
