<?php
// ============================================================
// UPC FREELANCE — Admin : Gestion des utilisateurs
// ../../admin/users.php
// ============================================================

if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['admin_id'])) {
    header('Location: /upc_freelance/admin/login.php'); exit;
}

require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/middleware.php';
require_once '../includes/admin_middleware.php';

$admin = currentAdmin();

$pdo = getDB();

// Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $uid    = (int)($_POST['user_id'] ?? 0);

    if ($action === 'toggle_active' && $uid) {
        $stmt = $pdo->prepare('SELECT is_active, first_name, last_name FROM users WHERE id = ?');
        $stmt->execute([$uid]);
        $user_row = $stmt->fetch();
        if ($user_row) {
            $newState = $user_row['is_active'] ? 0 : 1;
            $pdo->prepare('UPDATE users SET is_active = ? WHERE id = ?')->execute([$newState, $uid]);
            // Notif à l'utilisateur
            $msg = $newState
                ? 'Votre compte a été réactivé par un administrateur.'
                : 'Votre compte a été suspendu par un administrateur.';
            $pdo->prepare('INSERT INTO notifications (user_id, type, title, body) VALUES (?, ?, ?, ?)')
                ->execute([$uid,
                    $newState ? 'account_activated' : 'account_banned',
                    $newState ? 'Compte réactivé'   : 'Compte suspendu',
                    $msg]);
        }
    }
    if ($action === 'toggle_verified' && $uid) {
        $stmt = $pdo->prepare('SELECT is_verified FROM users WHERE id = ?');
        $stmt->execute([$uid]);
        $current = (int)$stmt->fetchColumn();
        $pdo->prepare('UPDATE users SET is_verified = ? WHERE id = ?')->execute([!$current, $uid]);
    }
    header('Location: /upc_freelance/admin/users.php?' . http_build_query($_GET));
    exit;
}

// Filtres
$search = sanitize($_GET['search'] ?? '');
$role   = sanitize($_GET['role']   ?? '');
$page   = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;

$where  = ['1=1'];
$params = [];
if ($search) {
    $where[]  = '(first_name LIKE ? OR last_name LIKE ? OR email LIKE ?)';
    $params   = array_merge($params, ["%$search%", "%$search%", "%$search%"]);
}
if ($role && in_array($role, ['client','freelancer'])) {
    $where[]  = 'role = ?';
    $params[] = $role;
}
$whereClause = implode(' AND ', $where);

$total  = (int)$pdo->prepare("SELECT COUNT(*) FROM users WHERE $whereClause")->execute($params) ?: 0;
$cstmt  = $pdo->prepare("SELECT COUNT(*) FROM users WHERE $whereClause");
$cstmt->execute($params);
$total  = (int)$cstmt->fetchColumn();
$offset = ($page - 1) * $perPage;

$stmt = $pdo->prepare("
    SELECT u.*,
           fp.university, fp.field_of_study,
           (SELECT COUNT(*) FROM projects p WHERE p.client_id = u.id) AS nb_projects,
           (SELECT COUNT(*) FROM postulations po WHERE po.freelancer_id = u.id) AS nb_applications,
           (SELECT balance FROM wallets w WHERE w.user_id = u.id) AS wallet_balance
    FROM users u
    LEFT JOIN freelancer_profiles fp ON fp.user_id = u.id
    WHERE $whereClause
    ORDER BY u.created_at DESC
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$users = $stmt->fetchAll();
$totalPages = (int)ceil($total / $perPage);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Utilisateurs — Admin UPC Freelance</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
<style>* { font-family: 'Inter', sans-serif; } .material-symbols-outlined { vertical-align: middle; } .nav-active { background: #eff4ff; color: #0061a5; border-right: 3px solid #0061a5; font-weight: 600; }</style>
</head>
<body class="bg-slate-50 text-slate-900 min-h-screen flex">

<!-- Sidebar -->
<aside class="w-60 bg-white border-r border-slate-200 flex flex-col sticky top-0 h-screen">
    <div class="p-5 border-b border-slate-100">
        <div class="flex items-center gap-2.5">
            <svg width="32" height="32" viewBox="0 0 38 38" fill="none"><rect width="38" height="38" rx="10" fill="#002045"/><path d="M10 12 L10 22 Q10 28 19 28 Q28 28 28 22 L28 12" stroke="#fff" stroke-width="2.5" stroke-linecap="round" fill="none"/><circle cx="19" cy="12" r="1.5" fill="#66affe"/><path d="M14 18 L24 18" stroke="#66affe" stroke-width="2" stroke-linecap="round"/></svg>
            <div><p class="font-bold text-sm">UPC Freelance</p><p class="text-xs text-red-500 font-semibold">Administration</p></div>
        </div>
    </div>
    <nav class="flex-1 p-3 space-y-0.5">
        <?php foreach (['dashboard.php'=>['dashboard','Tableau de bord'],'users.php'=>['people','Utilisateurs'],'projects.php'=>['work','Projets'],'transactions.php'=>['receipt_long','Transactions'],'verification.php'=>['verified_user','Vérifications'],'reports.php'=>['bar_chart','Rapports']] as $href => [$icon, $label]):
            $active = basename($_SERVER['PHP_SELF']) === $href; ?>
        <a href="/upc_freelance/admin/<?= $href ?>" class="flex items-center gap-2.5 px-3 py-2.5 rounded-lg text-sm transition-all <?= $active ? 'nav-active' : 'text-slate-600 hover:bg-slate-50' ?>">
            <span class="material-symbols-outlined text-lg <?= $active ? 'text-blue-600' : 'text-slate-400' ?>"><?= $icon ?></span><?= $label ?>
        </a>
        <?php endforeach; ?>
    </nav>
    <div class="p-4 border-t border-slate-100">
        <a href="/upc_freelance/admin/logout.php" class="flex items-center gap-2 px-3 py-2 rounded-lg text-red-500 hover:bg-red-50 text-xs">
            <span class="material-symbols-outlined text-base">logout</span> Déconnexion
        </a>
    </div>
</aside>

<div class="flex-1 flex flex-col min-w-0">
    <header class="bg-white border-b border-slate-200 px-8 py-4 flex items-center justify-between">
        <h1 class="text-xl font-bold">Utilisateurs</h1>
        <span class="text-sm text-slate-500"><?= $total ?> utilisateur<?= $total > 1 ? 's' : '' ?></span>
    </header>

    <main class="flex-1 p-8">

        <!-- Filtres -->
        <form method="GET" class="flex flex-wrap gap-3 mb-6">
            <div class="relative">
                <span class="absolute left-3 top-1/2 -translate-y-1/2 material-symbols-outlined text-slate-400 text-lg">search</span>
                <input type="text" name="search" value="<?= h($search) ?>" placeholder="Nom, email..."
                       class="pl-9 pr-4 py-2 rounded-xl border border-slate-200 text-sm focus:border-blue-500 outline-none w-56"/>
            </div>
            <select name="role" class="px-4 py-2 rounded-xl border border-slate-200 text-sm focus:border-blue-500 outline-none">
                <option value="">Tous les rôles</option>
                <option value="client"     <?= $role === 'client'     ? 'selected' : '' ?>>Clients</option>
                <option value="freelancer" <?= $role === 'freelancer' ? 'selected' : '' ?>>Freelancers</option>
            </select>
            <button type="submit" class="bg-slate-900 text-white px-4 py-2 rounded-xl text-sm hover:bg-slate-800 transition-colors">Filtrer</button>
            <?php if ($search || $role): ?>
            <a href="/upc_freelance/admin/users.php" class="px-4 py-2 rounded-xl border border-slate-200 text-sm text-slate-500 hover:border-slate-300 transition-colors">Effacer</a>
            <?php endif; ?>
        </form>

        <!-- Table -->
        <div class="bg-white rounded-2xl border border-slate-100 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-100 bg-slate-50">
                            <th class="text-left px-5 py-3 text-xs font-semibold text-slate-500 uppercase">Utilisateur</th>
                            <th class="text-left px-5 py-3 text-xs font-semibold text-slate-500 uppercase">Rôle</th>
                            <th class="text-left px-5 py-3 text-xs font-semibold text-slate-500 uppercase">Université</th>
                            <th class="text-left px-5 py-3 text-xs font-semibold text-slate-500 uppercase">Wallet</th>
                            <th class="text-left px-5 py-3 text-xs font-semibold text-slate-500 uppercase">Statut</th>
                            <th class="text-left px-5 py-3 text-xs font-semibold text-slate-500 uppercase">Inscrit le</th>
                            <th class="text-left px-5 py-3 text-xs font-semibold text-slate-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <?php foreach ($users as $u): ?>
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="px-5 py-3">
                                <div class="flex items-center gap-3">
                                    <?= renderAvatar($u['avatar'] ?? null, $u['first_name'], $u['last_name'], (bool)($u['is_verified'] ?? false), 'w-9 h-9', 'rounded-full') ?>
                                    <div>
                                        <p class="font-medium text-slate-900"><?= h($u['first_name'] . ' ' . $u['last_name']) ?></p>
                                        <p class="text-xs text-slate-400"><?= h($u['email']) ?></p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-5 py-3">
                                <span class="text-xs px-2.5 py-1 rounded-full font-medium <?= $u['role'] === 'freelancer' ? 'bg-purple-100 text-purple-700' : 'bg-blue-100 text-blue-700' ?>">
                                    <?= $u['role'] ?>
                                </span>
                            </td>
                            <td class="px-5 py-3 text-slate-500 text-xs"><?= h(truncate($u['university'] ?? '—', 25)) ?></td>
                            <td class="px-5 py-3 font-semibold text-slate-700"><?= money((float)($u['wallet_balance'] ?? 0)) ?></td>
                            <td class="px-5 py-3">
                                <div class="flex gap-1.5">
                                    <span class="text-xs px-2 py-0.5 rounded-full font-medium <?= $u['is_active'] ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-600' ?>">
                                        <?= $u['is_active'] ? 'Actif' : 'Banni' ?>
                                    </span>
                                    <?php if ($u['is_verified']): ?>
                                    <span class="text-xs px-2 py-0.5 rounded-full font-medium bg-emerald-100 text-emerald-700">Vérifié</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="px-5 py-3 text-slate-500 text-xs"><?= formatDate($u['created_at']) ?></td>
                            <td class="px-5 py-3">
                                <div class="flex gap-2">
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="action"  value="toggle_active"/>
                                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>"/>
                                        <?php
                                        $confirmMsg = $u['is_active']
                                            ? 'Bannir ' . $u['first_name'] . ' ' . $u['last_name'] . ' ? Son compte sera désactivé.'
                                            : 'Réactiver ce compte ?';
                                        $btnClass = $u['is_active']
                                            ? 'border-red-200 text-red-600 hover:bg-red-50'
                                            : 'border-green-200 text-green-600 hover:bg-green-50';
                                        ?>
                                        <button type="submit"
                                                title="<?= $u['is_active'] ? 'Bannir' : 'Activer' ?>"
                                                onclick="return confirm('<?= htmlspecialchars($confirmMsg, ENT_QUOTES) ?>')"
                                                class="text-xs px-2.5 py-1.5 rounded-lg border transition-colors <?= $btnClass ?>">
                                            <?= $u['is_active'] ? '🚫 Bannir' : '✓ Activer' ?>
                                        </button>
                                    </form>
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="action"  value="toggle_verified"/>
                                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>"/>
                                        <button type="submit" title="<?= $u['is_verified'] ? 'Retirer vérif.' : 'Vérifier' ?>"
                                                class="text-xs px-2.5 py-1.5 rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50 transition-colors">
                                            <?= $u['is_verified'] ? '✓ Vérifié' : 'Vérifier' ?>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
            <div class="flex items-center justify-between gap-3 px-5 py-4 border-t border-slate-100 flex-wrap">
                <p class="text-xs text-slate-500">
                    <?= ($offset + 1) ?>–<?= min($offset + $perPage, $total) ?> sur <?= $total ?> utilisateur<?= $total > 1 ? 's' : '' ?>
                </p>
                <div class="flex items-center gap-1.5" id="admin-pagination"></div>
            </div>
            <script>
            (function() {
                const cur   = <?= $page ?>;
                const total = <?= $totalPages ?>;
                const base  = '?search=<?= urlencode($search) ?>&role=<?= urlencode($role) ?>&page=';
                const wrap  = document.getElementById('admin-pagination');
                if (!wrap) return;

                function pages(c, t) {
                    if (t <= 7) { const r=[]; for(let i=1;i<=t;i++) r.push(i); return r; }
                    const r = [1];
                    if (c > 3) r.push('...');
                    for (let i=Math.max(2,c-1); i<=Math.min(t-1,c+1); i++) r.push(i);
                    if (c < t-2) r.push('...');
                    r.push(t);
                    return r;
                }

                let html = '';
                // Précédent
                html += cur > 1
                    ? '<a href="'+base+(cur-1)+'" class="px-2 py-1.5 rounded-lg border border-slate-200 text-sm hover:bg-slate-50"><span class="material-symbols-outlined text-base">chevron_left</span></a>'
                    : '<span class="px-2 py-1.5 rounded-lg border border-slate-100 text-slate-300 text-sm"><span class="material-symbols-outlined text-base">chevron_left</span></span>';

                pages(cur, total).forEach(p => {
                    if (p === '...') {
                        html += '<span class="px-2 py-1.5 text-slate-400 text-sm">…</span>';
                    } else if (p === cur) {
                        html += '<span class="px-3 py-1.5 rounded-lg bg-slate-900 text-white border border-slate-900 text-sm font-semibold">'+p+'</span>';
                    } else {
                        html += '<a href="'+base+p+'" class="px-3 py-1.5 rounded-lg border border-slate-200 text-sm hover:border-slate-300">'+p+'</a>';
                    }
                });

                // Suivant
                html += cur < total
                    ? '<a href="'+base+(cur+1)+'" class="px-2 py-1.5 rounded-lg border border-slate-200 text-sm hover:bg-slate-50"><span class="material-symbols-outlined text-base">chevron_right</span></a>'
                    : '<span class="px-2 py-1.5 rounded-lg border border-slate-100 text-slate-300 text-sm"><span class="material-symbols-outlined text-base">chevron_right</span></span>';

                wrap.innerHTML = html;
            })();
            </script>
            <?php endif; ?>
        </div>
    </main>
</div>
</body>
</html>