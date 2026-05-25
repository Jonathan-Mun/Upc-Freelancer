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

$total = (int)$pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ?')->execute([$user['id']]) ?: 0;
$stmt  = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ?');
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

<?php renderFlash(); ?>

<div class="flex items-center justify-between mb-8">
    <div>
        <h1 class="text-2xl font-bold text-primary">Notifications</h1>
        <p class="text-on-surface-variant text-sm mt-1">
            <?= $unreadCount > 0 ? "<strong>$unreadCount</strong> non lue(s)" : 'Tout est lu' ?>
        </p>
    </div>
    <?php if ($unreadCount > 0): ?>
    <a href="?mark_all_read=1" class="inline-flex items-center gap-1.5 text-sm text-secondary hover:underline">
        <span class="material-symbols-outlined text-base">done_all</span> Tout marquer comme lu
    </a>
    <?php endif; ?>
</div>

<?php if (empty($notifications)): ?>
<div class="bg-white rounded-2xl border border-slate-100 p-16 text-center custom-shadow-low">
    <span class="material-symbols-outlined text-5xl text-slate-300 block mb-4">notifications_off</span>
    <h3 class="font-semibold text-primary mb-2">Aucune notification</h3>
    <p class="text-on-surface-variant text-sm">Vous êtes à jour !</p>
</div>
<?php else: ?>
<div class="bg-white rounded-2xl border border-slate-100 custom-shadow-low overflow-hidden">
    <?php
    $typeIcons = [
        'welcome'              => ['icon'=>'celebration',    'color'=>'text-purple-500', 'bg'=>'bg-purple-50'],
        'new_application'      => ['icon'=>'inbox',          'color'=>'text-amber-500',  'bg'=>'bg-amber-50'],
        'application_accepted' => ['icon'=>'check_circle',   'color'=>'text-green-500',  'bg'=>'bg-green-50'],
        'application_rejected' => ['icon'=>'cancel',         'color'=>'text-red-400',    'bg'=>'bg-red-50'],
        'new_message'          => ['icon'=>'chat',           'color'=>'text-blue-500',   'bg'=>'bg-blue-50'],
        'payment_received'     => ['icon'=>'payments',       'color'=>'text-emerald-500','bg'=>'bg-emerald-50'],
        'deposit_success'      => ['icon'=>'add_circle',     'color'=>'text-green-500',  'bg'=>'bg-green-50'],
        'contract_created'     => ['icon'=>'description',    'color'=>'text-secondary',  'bg'=>'bg-blue-50'],
    ];
    ?>
    <div class="divide-y divide-slate-50">
        <?php foreach ($notifications as $n):
            $ti = $typeIcons[$n['type']] ?? ['icon'=>'notifications','color'=>'text-slate-400','bg'=>'bg-slate-50'];
        ?>
        <div class="flex items-start gap-4 p-4 <?= !$n['is_read'] ? 'bg-blue-50/40' : '' ?> hover:bg-surface-container-low transition-colors">
            <div class="w-10 h-10 rounded-full <?= $ti['bg'] ?> flex items-center justify-center flex-shrink-0 mt-0.5">
                <span class="material-symbols-outlined <?= $ti['color'] ?> text-xl"><?= $ti['icon'] ?></span>
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-semibold text-primary"><?= h($n['title']) ?></p>
                <?php if ($n['body']): ?>
                <p class="text-sm text-on-surface-variant mt-0.5"><?= h($n['body']) ?></p>
                <?php endif; ?>
                <p class="text-xs text-slate-400 mt-1"><?= timeAgo($n['created_at']) ?></p>
            </div>
            <div class="flex items-center gap-2 flex-shrink-0">
                <?php if ($n['link']): ?>
                <a href="?read=<?= $n['id'] ?>"
                   class="text-xs text-secondary hover:underline whitespace-nowrap">Voir →</a>
                <?php endif; ?>
                <?php if (!$n['is_read']): ?>
                <div class="w-2.5 h-2.5 bg-secondary rounded-full"></div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Pagination -->
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
<?php endif; ?>

<?php
$appLayout = true;
require_once '../../includes/footer.php';
?>
