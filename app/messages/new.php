<?php
// ============================================================
// UPC FREELANCE — Nouveau message direct
// /var/www/html/upc_freelance/app/messages/new.php
// ============================================================

require_once '../../includes/middleware.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../includes/db.php';

requireLogin();

$user = currentUser();
$pdo  = getDB();

// Recherche d'utilisateurs
$search = sanitize($_GET['q'] ?? '');
$users  = [];
if (strlen($search) >= 2) {
    $stmt = $pdo->prepare("
        SELECT id, first_name, last_name, avatar, role, is_verified
        FROM users
        WHERE id != ?
          AND is_active = 1
          AND (first_name LIKE ? OR last_name LIKE ? OR CONCAT(first_name,' ',last_name) LIKE ?)
        LIMIT 10
    ");
    $stmt->execute([$user['id'], "%$search%", "%$search%", "%$search%"]);
    $users = $stmt->fetchAll();
}

$pageTitle = 'Nouveau message — UPC Freelance';
$appLayout = true;
require_once '../../includes/header.php';
?>

<div class="max-w-xl mx-auto">
    <a href="/upc_freelance/app/messages/inbox.php?tab=direct"
       class="inline-flex items-center gap-1 text-sm text-secondary hover:underline mb-6">
        <span class="material-symbols-outlined text-base">arrow_back</span> Messages directs
    </a>

    <div class="bg-white rounded-2xl border border-slate-100 custom-shadow-low p-6">
        <h1 class="text-xl font-bold text-primary mb-6 flex items-center gap-2">
            <span class="material-symbols-outlined text-secondary">edit</span>
            Nouveau message
        </h1>

        <!-- Barre de recherche -->
        <form method="GET" class="mb-6">
            <label class="block text-sm font-medium text-primary mb-2">
                Rechercher un utilisateur
            </label>
            <div class="relative">
                <span class="absolute left-3 top-1/2 -translate-y-1/2 material-symbols-outlined text-slate-400 text-lg">search</span>
                <input type="text" name="q" value="<?= h($search) ?>"
                       placeholder="Nom du client ou freelancer..."
                       autofocus
                       class="w-full pl-10 pr-4 py-3 rounded-xl border border-outline-variant
                              focus:border-secondary focus:ring-2 focus:ring-secondary/20
                              outline-none text-sm transition-all"/>
            </div>
            <?php if (strlen($search) >= 1 && strlen($search) < 2): ?>
            <p class="text-xs text-slate-400 mt-1">Tapez au moins 2 caractères.</p>
            <?php endif; ?>
        </form>

        <!-- Résultats -->
        <?php if ($search && empty($users)): ?>
        <div class="text-center py-8 text-slate-400">
            <span class="material-symbols-outlined text-4xl block mb-2">person_search</span>
            <p class="text-sm">Aucun utilisateur trouvé pour "<?= h($search) ?>".</p>
        </div>
        <?php elseif (!empty($users)): ?>
        <div class="space-y-2">
            <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-3">
                <?= count($users) ?> résultat<?= count($users) > 1 ? 's' : '' ?>
            </p>
            <?php foreach ($users as $u): ?>
            <a href="/upc_freelance/app/messages/direct.php?user_id=<?= $u['id'] ?>"
               class="flex items-center gap-4 p-4 rounded-xl border border-slate-100
                      hover:border-secondary/40 hover:bg-blue-50/20 transition-all group">
                <?= renderAvatar($u['avatar'] ?? null, $u['first_name'], $u['last_name'], (bool)$u['is_verified'], 'w-11 h-11', 'rounded-full') ?>
                <div class="flex-1 min-w-0">
                    <p class="font-semibold text-primary text-sm flex items-center gap-1.5">
                        <?= h($u['first_name'] . ' ' . $u['last_name']) ?>
                        <?php if ($u['is_verified']): ?>
                        <span class="material-symbols-outlined text-secondary"
                              style="font-size:14px;font-variation-settings:'FILL' 1">verified</span>
                        <?php endif; ?>
                    </p>
                    <span style="font-size:10px;font-weight:700;padding:1px 7px;border-radius:999px;
                                 background:<?= $u['role'] === 'freelancer' ? '#f3e8ff' : '#dbeafe' ?>;
                                 color:<?= $u['role'] === 'freelancer' ? '#7c3aed' : '#1d4ed8' ?>;">
                        <?= $u['role'] === 'freelancer' ? 'Freelancer' : 'Client' ?>
                    </span>
                </div>
                <span class="material-symbols-outlined text-slate-300 group-hover:text-secondary transition-colors">
                    chevron_right
                </span>
            </a>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="text-center py-8 text-slate-400">
            <span class="material-symbols-outlined text-5xl block mb-3">person_search</span>
            <p class="text-sm">Recherchez un client ou freelancer pour démarrer une conversation.</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php $appLayout = true; require_once '../../includes/footer.php'; ?>