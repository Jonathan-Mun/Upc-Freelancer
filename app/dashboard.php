<?php
// ============================================================
// UPC FREELANCE — Dashboard principal
// ../app/dashboard.php
// ============================================================

require_once '../includes/middleware.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';

requireLogin();

$user   = currentUser();
$pdo    = getDB();
$wallet = getUserWallet($user['id']);
$userId = $user['id'];
$role   = $user['role'];

// ─ Stats selon le rôle ────────────────────────────────────────
if ($role === 'client') {
    $stats = [
        'projets'     => (int)$pdo->prepare('SELECT COUNT(*) FROM projects WHERE client_id = ?')->execute([$userId]) ?: 0,
        'contrats'    => (int)$pdo->prepare('SELECT COUNT(*) FROM contracts WHERE client_id = ? AND status = "active"')->execute([$userId]) ?: 0,
        'depenses'    => (float)$pdo->prepare('SELECT COALESCE(SUM(amount),0) FROM transactions WHERE user_id = ? AND type = "payment"')->execute([$userId]) ?: 0,
        'postulations'=> (int)$pdo->prepare('SELECT COUNT(*) FROM postulations p JOIN projects pr ON pr.id = p.project_id WHERE pr.client_id = ? AND p.status = "pending"')->execute([$userId]) ?: 0,
    ];
    // Fix: use proper queries
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM projects WHERE client_id = ?'); $stmt->execute([$userId]); $stats['projets'] = (int)$stmt->fetchColumn();
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM contracts WHERE client_id = ? AND status = "active"'); $stmt->execute([$userId]); $stats['contrats'] = (int)$stmt->fetchColumn();
    $stmt = $pdo->prepare('SELECT COALESCE(SUM(amount),0) FROM transactions WHERE user_id = ? AND type = "payment"'); $stmt->execute([$userId]); $stats['depenses'] = (float)$stmt->fetchColumn();
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM postulations p JOIN projects pr ON pr.id = p.project_id WHERE pr.client_id = ? AND p.status = "pending"'); $stmt->execute([$userId]); $stats['postulations'] = (int)$stmt->fetchColumn();

    // Mes derniers projets
    $recentProjects = $pdo->prepare('SELECT p.*, (SELECT COUNT(*) FROM postulations WHERE project_id = p.id) AS nb_postulations FROM projects p WHERE p.client_id = ? ORDER BY p.created_at DESC LIMIT 5');
    $recentProjects->execute([$userId]);
    $recentProjects = $recentProjects->fetchAll();
} else {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM postulations WHERE freelancer_id = ?'); $stmt->execute([$userId]); $stats['candidatures'] = (int)$stmt->fetchColumn();
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM contracts WHERE freelancer_id = ? AND status = "active"'); $stmt->execute([$userId]); $stats['contrats'] = (int)$stmt->fetchColumn();
    $stmt = $pdo->prepare('SELECT COALESCE(SUM(amount),0) FROM transactions WHERE user_id = ? AND type = "payment"'); $stmt->execute([$userId]); $stats['gains'] = (float)$stmt->fetchColumn();
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM contracts WHERE freelancer_id = ? AND status = "completed"'); $stmt->execute([$userId]); $stats['termines'] = (int)$stmt->fetchColumn();

    // Projets ouverts pour le freelancer
    $recentProjects = $pdo->query('SELECT p.*, c.name AS category_name FROM projects p LEFT JOIN categories c ON c.id = p.category_id WHERE p.status = "open" ORDER BY p.created_at DESC LIMIT 5')->fetchAll();
}

// Contrats actifs
$activeContracts = $pdo->prepare('
    SELECT ct.*, p.title AS project_title, u.first_name, u.last_name
    FROM contracts ct
    JOIN projects p ON p.id = ct.project_id
    JOIN users u ON u.id = IF(? = ct.client_id, ct.freelancer_id, ct.client_id)
    WHERE (ct.client_id = ? OR ct.freelancer_id = ?) AND ct.status = "active"
    ORDER BY ct.created_at DESC LIMIT 5
');
$activeContracts->execute([$userId, $userId, $userId]);
$activeContracts = $activeContracts->fetchAll();

// Notifications récentes
$recentNotifs = $pdo->prepare('SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 5');
$recentNotifs->execute([$userId]);
$recentNotifs = $recentNotifs->fetchAll();

$pageTitle  = 'Tableau de bord — UPC Freelance';
$appLayout  = true;
require_once '../includes/header.php';
?>

<?php renderFlash(); ?>

<?php
$stmt = $pdo->prepare('SELECT status FROM verification_docs WHERE user_id = ? ORDER BY created_at DESC LIMIT 1');
$stmt->execute([$userId]);
$lastDocStatus = $stmt->fetchColumn();
if (!$user['is_verified'] && $lastDocStatus !== 'pending'):
?>
<div class="mb-6 p-4 bg-amber-50 border border-amber-200 rounded-2xl flex items-center gap-4">
    <div class="w-10 h-10 bg-amber-400 rounded-xl flex items-center justify-center flex-shrink-0">
        <span class="material-symbols-outlined text-white">shield</span>
    </div>
    <div class="flex-1">
        <p class="font-semibold text-amber-800 text-sm">Compte non vérifié</p>
        <p class="text-xs text-amber-700 mt-0.5">
            Obtenez le badge vérifié pour <?= $role === 'freelancer' ? 'décrocher plus de missions' : 'accéder aux meilleurs freelancers' ?>.
        </p>
    </div>
    <a href="../app/verification/index.php"
       class="bg-amber-500 text-white text-xs font-button px-4 py-2 rounded-xl hover:bg-amber-600 transition-colors active:scale-95 whitespace-nowrap">
         Vérifier →
    </a>
</div>
<?php elseif ($lastDocStatus === 'pending'): ?>
<div class="mb-6 p-4 bg-blue-50 border border-blue-200 rounded-2xl flex items-center gap-4">
    <div class="w-10 h-10 bg-secondary rounded-xl flex items-center justify-center flex-shrink-0">
        <span class="material-symbols-outlined text-white">hourglass_top</span>
    </div>
    <div class="flex-1">
        <p class="font-semibold text-primary text-sm">Vérification en cours</p>
        <p class="text-xs text-on-surface-variant mt-0.5">Votre document est en cours d'examen (24-48h ouvrables).</p>
    </div>
    <a href="../app/verification/index.php"
       class="border border-secondary text-secondary text-xs font-button px-4 py-2 rounded-xl hover:bg-secondary/5 transition-colors whitespace-nowrap">
         Voir le statut
    </a>
</div>
<?php endif; ?>

<div class="mb-8">
    <h1 class="text-2xl font-bold text-primary">
        Bonjour, <?= h($user['first_name']) ?> 👋
    </h1>
    <p class="text-on-surface-variant text-sm mt-1">
        <?= date('l d MMMM Y') ?> — <?= $role === 'client' ? 'Tableau de bord Client' : 'Tableau de bord Freelancer' ?>
    </p>
</div>

<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">

    <div class="bg-primary text-white rounded-2xl p-5 custom-shadow-high col-span-2 lg:col-span-1">
        <div class="flex items-center justify-between mb-3">
            <span class="text-blue-200 text-xs font-label-caps uppercase tracking-widest">Mon Wallet</span>
            <span class="material-symbols-outlined text-blue-200">account_balance_wallet</span>
        </div>
        <p class="text-2xl font-bold"><?= money((float)$wallet['balance']) ?></p>
        <?php if ($wallet['locked'] > 0): ?>
        <p class="text-xs text-blue-300 mt-1">🔒 <?= money((float)$wallet['locked']) ?> bloqué</p>
        <?php endif; ?>
        <a href="../app/wallet/index.php" class="mt-3 inline-block text-xs text-blue-200 hover:text-white">
            Gérer →
        </a>
    </div>

    <?php if ($role === 'client'): ?>
    <div class="bg-white rounded-2xl p-5 border border-slate-100 custom-shadow-low">
        <div class="flex items-center justify-between mb-3">
            <span class="text-on-surface-variant text-xs font-label-caps uppercase tracking-widest">Mes Projets</span>
            <span class="material-symbols-outlined text-secondary text-xl">folder_open</span>
        </div>
        <p class="text-3xl font-bold text-primary"><?= $stats['projets'] ?></p>
        <a href="../app/projects/my-projects.php" class="text-xs text-secondary mt-1 inline-block hover:underline">Voir →</a>
    </div>
    <div class="bg-white rounded-2xl p-5 border border-slate-100 custom-shadow-low">
        <div class="flex items-center justify-between mb-3">
            <span class="text-on-surface-variant text-xs font-label-caps uppercase tracking-widest">Candidatures</span>
            <span class="material-symbols-outlined text-amber-500 text-xl">inbox</span>
        </div>
        <p class="text-3xl font-bold text-primary"><?= $stats['postulations'] ?></p>
        <a href="../app/postulations/received.php" class="text-xs text-secondary mt-1 inline-block hover:underline">Voir →</a>
    </div>
    <div class="bg-white rounded-2xl p-5 border border-slate-100 custom-shadow-low">
        <div class="flex items-center justify-between mb-3">
            <span class="text-on-surface-variant text-xs font-label-caps uppercase tracking-widest">Contrats actifs</span>
            <span class="material-symbols-outlined text-green-500 text-xl">description</span>
        </div>
        <p class="text-3xl font-bold text-primary"><?= $stats['contrats'] ?></p>
        <a href="../app/contracts/list.php" class="text-xs text-secondary mt-1 inline-block hover:underline">Voir →</a>
    </div>

    <?php else: ?>
    <div class="bg-white rounded-2xl p-5 border border-slate-100 custom-shadow-low">
        <div class="flex items-center justify-between mb-3">
            <span class="text-on-surface-variant text-xs font-label-caps uppercase tracking-widest">Candidatures</span>
            <span class="material-symbols-outlined text-secondary text-xl">send</span>
        </div>
        <p class="text-3xl font-bold text-primary"><?= $stats['candidatures'] ?></p>
        <a href="../app/postulations/my-applications.php" class="text-xs text-secondary mt-1 inline-block hover:underline">Voir →</a>
    </div>
    <div class="bg-white rounded-2xl p-5 border border-slate-100 custom-shadow-low">
        <div class="flex items-center justify-between mb-3">
            <span class="text-on-surface-variant text-xs font-label-caps uppercase tracking-widest">Contrats actifs</span>
            <span class="material-symbols-outlined text-green-500 text-xl">description</span>
        </div>
        <p class="text-3xl font-bold text-primary"><?= $stats['contrats'] ?></p>
        <a href="../app/contracts/list.php" class="text-xs text-secondary mt-1 inline-block hover:underline">Voir →</a>
    </div>
    <div class="bg-white rounded-2xl p-5 border border-slate-100 custom-shadow-low">
        <div class="flex items-center justify-between mb-3">
            <span class="text-on-surface-variant text-xs font-label-caps uppercase tracking-widest">Gains totaux</span>
            <span class="material-symbols-outlined text-emerald-500 text-xl">payments</span>
        </div>
        <p class="text-2xl font-bold text-primary"><?= money($stats['gains']) ?></p>
        <a href="../app/wallet/history.php" class="text-xs text-secondary mt-1 inline-block hover:underline">Historique →</a>
    </div>
    <?php endif; ?>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-100 custom-shadow-low overflow-hidden">
        <div class="flex justify-between items-center p-6 border-b border-slate-100">
            <h2 class="font-semibold text-primary">Contrats en cours</h2>
            <a href="../app/contracts/list.php" class="text-xs text-secondary hover:underline">Voir tout</a>
        </div>
        <?php if (empty($activeContracts)): ?>
        <div class="p-12 text-center">
            <span class="material-symbols-outlined text-4xl text-slate-300 block mb-3">description</span>
            <p class="text-on-surface-variant text-sm">Aucun contrat actif pour le moment.</p>
            <?php if ($role === 'client'): ?>
            <a href="../app/projects/create.php" class="mt-4 inline-block bg-primary text-white text-sm px-4 py-2 rounded-lg">
                Créer un projet
            </a>
            <?php else: ?>
            <a href="../app/projects/list.php" class="mt-4 inline-block bg-primary text-white text-sm px-4 py-2 rounded-lg">
                Parcourir les projets
            </a>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <div class="divide-y divide-slate-50">
            <?php foreach ($activeContracts as $c): ?>
            <a href="../app/contracts/details.php?id=<?= $c['id'] ?>"
               class="flex items-center gap-4 p-4 hover:bg-surface-container-low transition-colors">
                <div class="w-10 h-10 rounded-full bg-secondary/10 flex items-center justify-center flex-shrink-0">
                    <span class="text-secondary font-bold text-sm"><?= mb_substr($c['first_name'], 0, 1) ?></span>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold text-primary truncate"><?= h($c['project_title']) ?></p>
                    <p class="text-xs text-on-surface-variant">avec <?= h($c['first_name'] . ' ' . $c['last_name']) ?></p>
                </div>
                <div class="text-right">
                    <p class="text-sm font-bold text-secondary"><?= money((float)$c['amount']) ?></p>
                    <span class="inline-block text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded-full">Actif</span>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <div class="bg-white rounded-2xl border border-slate-100 custom-shadow-low overflow-hidden">
        <div class="flex justify-between items-center p-6 border-b border-slate-100">
            <h2 class="font-semibold text-primary">Notifications</h2>
            <a href="../app/notifications/index.php" class="text-xs text-secondary hover:underline">Voir tout</a>
        </div>
        <?php if (empty($recentNotifs)): ?>
        <div class="p-8 text-center">
            <span class="material-symbols-outlined text-3xl text-slate-300 block mb-2">notifications</span>
            <p class="text-xs text-on-surface-variant">Aucune notification</p>
        </div>
        <?php else: ?>
        <div class="divide-y divide-slate-50">
            <?php foreach ($recentNotifs as $n): ?>
            <div class="p-4 flex gap-3 <?= !$n['is_read'] ? 'bg-blue-50/50' : '' ?>">
                <div class="w-8 h-8 rounded-full bg-secondary/10 flex items-center justify-center flex-shrink-0 mt-0.5">
                    <span class="material-symbols-outlined text-secondary text-sm">notifications</span>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-xs font-semibold text-primary"><?= h($n['title']) ?></p>
                    <p class="text-xs text-on-surface-variant mt-0.5"><?= timeAgo($n['created_at']) ?></p>
                </div>
                <?php if (!$n['is_read']): ?>
                <div class="w-2 h-2 bg-secondary rounded-full mt-2 flex-shrink-0"></div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <div class="lg:col-span-3 bg-white rounded-2xl border border-slate-100 custom-shadow-low overflow-hidden">
        <div class="flex justify-between items-center p-6 border-b border-slate-100">
            <h2 class="font-semibold text-primary">
                <?= $role === 'client' ? 'Mes projets récents' : 'Projets disponibles' ?>
            </h2>
            <?php if ($role === 'client'): ?>
            <a href="../app/projects/create.php" class="inline-flex items-center gap-1 bg-primary text-white text-xs px-3 py-1.5 rounded-lg hover:opacity-90">
                <span class="material-symbols-outlined text-sm">add</span> Nouveau projet
            </a>
            <?php else: ?>
            <a href="../app/projects/list.php" class="text-xs text-secondary hover:underline">Voir tout</a>
            <?php endif; ?>
        </div>
        <?php if (empty($recentProjects)): ?>
        <div class="p-12 text-center">
            <span class="material-symbols-outlined text-4xl text-slate-300 block mb-3">work</span>
            <p class="text-on-surface-variant text-sm">Aucun projet pour le moment.</p>
        </div>
        <?php else: ?>
        <div class="divide-y divide-slate-50">
            <?php foreach ($recentProjects as $p): ?>
            <a href="../app/projects/details.php?id=<?= $p['id'] ?>"
               class="flex items-center gap-4 p-4 hover:bg-surface-container-low transition-colors">
                <div class="w-10 h-10 rounded-xl bg-surface-container flex items-center justify-center flex-shrink-0">
                    <span class="material-symbols-outlined text-secondary text-xl">work</span>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold text-primary truncate"><?= h($p['title']) ?></p>
                    <p class="text-xs text-on-surface-variant">
                        <?= isset($p['category_name']) ? h($p['category_name']) : '' ?>
                        <?= isset($p['nb_postulations']) ? '· ' . $p['nb_postulations'] . ' candidature(s)' : '' ?>
                    </p>
                </div>
                <div class="text-right">
                    <?php
                    $statusColors = ['open'=>'green','in_progress'=>'blue','completed'=>'gray','cancelled'=>'red'];
                    $statusLabels = ['open'=>'Ouvert','in_progress'=>'En cours','completed'=>'Terminé','cancelled'=>'Annulé'];
                    $sc = $statusColors[$p['status']] ?? 'gray';
                    $sl = $statusLabels[$p['status']] ?? $p['status'];
                    ?>
                    <span class="inline-block text-xs bg-<?= $sc ?>-100 text-<?= $sc ?>-700 px-2 py-0.5 rounded-full">
                        <?= $sl ?>
                    </span>
                    <?php if ($p['budget_max'] || $p['budget_min']): ?>
                    <p class="text-xs text-secondary font-semibold mt-1">
                        <?= money((float)($p['budget_max'] ?? $p['budget_min'])) ?>
                    </p>
                    <?php endif; ?>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php
$appLayout = true;
require_once '../includes/footer.php';
?>