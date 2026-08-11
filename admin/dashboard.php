<?php
// ============================================================
// UPC FREELANCE — Admin Dashboard
// ../../admin/dashboard.php
// ============================================================
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
require_once '../includes/middleware.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';
require_once '../includes/admin_middleware.php';
$admin = currentAdmin();
// Auth admin via session séparée
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['admin_id'])) {
    redirect('../../admin/login.php');
}

$pdo = getDB();

// Statistiques globales
$stats = [];
$queries = [
    'total_users'       => 'SELECT COUNT(*) FROM users WHERE is_active = 1',
    'freelancers'       => 'SELECT COUNT(*) FROM users WHERE role = "freelancer" AND is_active = 1',
    'clients'           => 'SELECT COUNT(*) FROM users WHERE role = "client" AND is_active = 1',
    'total_projects'    => 'SELECT COUNT(*) FROM projects',
    'open_projects'     => 'SELECT COUNT(*) FROM projects WHERE status = "open"',
    'active_contracts'  => 'SELECT COUNT(*) FROM contracts WHERE status = "active"',
    'completed_contracts'=> 'SELECT COUNT(*) FROM contracts WHERE status = "completed"',
    'total_transactions'=> 'SELECT COALESCE(SUM(amount),0) FROM transactions WHERE type = "payment" AND status = "completed"',
    'pending_verif'     => 'SELECT COUNT(*) FROM verification_docs WHERE status = "pending"',
];
foreach ($queries as $key => $sql) {
    $stats[$key] = $pdo->query($sql)->fetchColumn();
}

// Derniers utilisateurs
$recentUsers = $pdo->query('
    SELECT * FROM users ORDER BY created_at DESC LIMIT 8
')->fetchAll();

// Dernières transactions
$recentTx = $pdo->query('
    SELECT t.*, u.first_name, u.last_name
    FROM transactions t JOIN users u ON u.id = t.user_id
    ORDER BY t.created_at DESC LIMIT 8
')->fetchAll();

// Projets récents
$recentProjects = $pdo->query('
    SELECT p.*, u.first_name, u.last_name
    FROM projects p JOIN users u ON u.id = p.client_id
    ORDER BY p.created_at DESC LIMIT 6
')->fetchAll();

$pageTitle = 'Admin Dashboard — UPC Freelance';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title><?= $pageTitle ?></title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
<style>
* { font-family: 'Inter', sans-serif; }
.material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400; vertical-align: middle; }
.nav-active { background: #eff4ff; color: #0061a5; border-right: 3px solid #0061a5; font-weight: 600; }
</style>
</head>
<body class="bg-slate-50 text-slate-900 min-h-screen flex">

<!-- Sidebar Admin -->
<aside class="w-60 bg-white border-r border-slate-200 flex flex-col sticky top-0 h-screen">
    <div class="p-5 border-b border-slate-100">
        <div class="flex items-center gap-2.5">
            <svg width="32" height="32" viewBox="0 0 38 38" fill="none" xmlns="http://www.w3.org/2000/svg">
                <rect width="38" height="38" rx="10" fill="#002045"/>
                <path d="M10 12 L10 22 Q10 28 19 28 Q28 28 28 22 L28 12" stroke="#fff" stroke-width="2.5" stroke-linecap="round" fill="none"/>
                <circle cx="19" cy="12" r="1.5" fill="#66affe"/>
                <path d="M14 18 L24 18" stroke="#66affe" stroke-width="2" stroke-linecap="round"/>
            </svg>
            <div>
                <p class="font-bold text-slate-900 text-sm">UPC Freelance</p>
                <p class="text-xs text-red-500 font-semibold">Administration</p>
            </div>
        </div>
    </div>

    <nav class="flex-1 p-3 space-y-0.5">
        <?php
        $navItems = [
            ['icon'=>'dashboard',      'label'=>'Tableau de bord', 'href'=>'dashboard.php'],
            ['icon'=>'people',         'label'=>'Utilisateurs',    'href'=>'users.php'],
            ['icon'=>'work',           'label'=>'Projets',         'href'=>'projects.php'],
            ['icon'=>'receipt_long',   'label'=>'Transactions',    'href'=>'transactions.php'],
            ['icon'=>'verified_user',  'label'=>'Vérifications',   'href'=>'verification.php'],
            ['icon'=>'gavel',          'label'=>'Litiges',         'href'=>'disputes.php'],
            ['icon'=>'bar_chart',      'label'=>'Rapports',        'href'=>'reports.php'],
        ];
        $current = basename($_SERVER['PHP_SELF']);
        foreach ($navItems as $nav):
            $active = $current === $nav['href'];
        ?>
        <a href="/upc_freelance/admin/<?= $nav['href'] ?>"
           class="flex items-center gap-2.5 px-3 py-2.5 rounded-lg text-sm transition-all <?= $active ? 'nav-active' : 'text-slate-600 hover:bg-slate-50' ?>">
            <span class="material-symbols-outlined text-lg <?= $active ? 'text-blue-600' : 'text-slate-400' ?>"><?= $nav['icon'] ?></span>
            <?= $nav['label'] ?>
        </a>
        <?php endforeach; ?>
    </nav>

    <div class="p-4 border-t border-slate-100">
        <div class="flex items-center gap-2 mb-3">
            <div class="w-8 h-8 rounded-full bg-red-100 flex items-center justify-center text-xs font-bold text-red-600">A</div>
            <div>
                <p class="text-xs font-semibold text-slate-800"><?= h($_SESSION['admin_name'] ?? 'Admin') ?></p>
                <p class="text-xs text-slate-400">Super Admin</p>
            </div>
        </div>
        <a href="/upc_freelance/admin/logout.php"
           class="flex items-center gap-2 px-3 py-2 rounded-lg text-red-500 hover:bg-red-50 transition-colors text-xs">
            <span class="material-symbols-outlined text-base">logout</span> Déconnexion
        </a>
    </div>
</aside>

<!-- Main -->
<div class="flex-1 flex flex-col min-w-0">
    <!-- Topbar -->
    <header class="bg-white border-b border-slate-200 px-8 py-4 flex items-center justify-between">
        <h1 class="text-xl font-bold text-slate-900">Tableau de bord</h1>
        <div class="flex items-center gap-3">
            <span class="text-sm text-slate-500"><?= date('d/m/Y H:i') ?></span>
            <?php if ($stats['pending_verif'] > 0): ?>
            <a href="/upc_freelance/admin/verification.php"
               class="inline-flex items-center gap-1.5 bg-amber-100 text-amber-700 text-xs px-3 py-1.5 rounded-full font-medium">
                <span class="material-symbols-outlined text-sm">warning</span>
                <?= $stats['pending_verif'] ?> vérification<?= $stats['pending_verif'] > 1 ? 's' : '' ?> en attente
            </a>
            <?php endif; ?>
        </div>
    </header>

    <main class="flex-1 p-8 space-y-8">

        <!-- Stat cards -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-5">
            <?php
            $cards = [
                ['label'=>'Utilisateurs',          'value'=>number_format($stats['total_users']),        'icon'=>'people',         'color'=>'text-blue-600',  'bg'=>'bg-blue-50'],
                ['label'=>'Freelancers',            'value'=>number_format($stats['freelancers']),        'icon'=>'person',         'color'=>'text-purple-600','bg'=>'bg-purple-50'],
                ['label'=>'Clients',                'value'=>number_format($stats['clients']),            'icon'=>'business',       'color'=>'text-green-600', 'bg'=>'bg-green-50'],
                ['label'=>'Projets publiés',        'value'=>number_format($stats['total_projects']),     'icon'=>'work',           'color'=>'text-orange-600','bg'=>'bg-orange-50'],
                ['label'=>'Projets ouverts',        'value'=>number_format($stats['open_projects']),      'icon'=>'folder_open',    'color'=>'text-cyan-600',  'bg'=>'bg-cyan-50'],
                ['label'=>'Contrats actifs',        'value'=>number_format($stats['active_contracts']),   'icon'=>'description',    'color'=>'text-indigo-600','bg'=>'bg-indigo-50'],
                ['label'=>'Contrats terminés',      'value'=>number_format($stats['completed_contracts']),'icon'=>'task_alt',       'color'=>'text-teal-600',  'bg'=>'bg-teal-50'],
                ['label'=>'Volume transactions',    'value'=>money((float)$stats['total_transactions']),  'icon'=>'payments',       'color'=>'text-emerald-600','bg'=>'bg-emerald-50'],
            ];
            foreach ($cards as $card):
            ?>
            <div class="bg-white rounded-2xl border border-slate-100 p-5">
                <div class="flex items-center justify-between mb-3">
                    <p class="text-xs text-slate-500 uppercase tracking-wide font-medium"><?= $card['label'] ?></p>
                    <div class="w-9 h-9 <?= $card['bg'] ?> rounded-xl flex items-center justify-center">
                        <span class="material-symbols-outlined <?= $card['color'] ?> text-lg"><?= $card['icon'] ?></span>
                    </div>
                </div>
                <p class="text-2xl font-bold text-slate-900"><?= $card['value'] ?></p>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Tables -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

            <!-- Derniers utilisateurs -->
            <div class="bg-white rounded-2xl border border-slate-100 overflow-hidden">
                <div class="flex justify-between items-center p-5 border-b border-slate-100">
                    <h2 class="font-semibold text-slate-900">Derniers inscrits</h2>
                    <a href="/upc_freelance/admin/users.php" class="text-xs text-blue-600 hover:underline">Voir tout</a>
                </div>
                <div class="divide-y divide-slate-50">
                    <?php foreach ($recentUsers as $u): ?>
                    <div class="flex items-center gap-3 px-5 py-3">
                        <div class="w-9 h-9 rounded-full bg-slate-100 flex items-center justify-center text-sm font-bold text-slate-600 flex-shrink-0">
                            <?= mb_substr($u['first_name'], 0, 1) ?>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-slate-900 truncate"><?= h($u['first_name'] . ' ' . $u['last_name']) ?></p>
                            <p class="text-xs text-slate-400 truncate"><?= h($u['email']) ?></p>
                        </div>
                        <div class="flex items-center gap-2 flex-shrink-0">
                            <span class="text-xs px-2 py-0.5 rounded-full font-medium <?= $u['role'] === 'freelancer' ? 'bg-purple-100 text-purple-700' : 'bg-blue-100 text-blue-700' ?>">
                                <?= $u['role'] ?>
                            </span>
                            <span class="text-xs text-slate-400"><?= timeAgo($u['created_at']) ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Dernières transactions -->
            <div class="bg-white rounded-2xl border border-slate-100 overflow-hidden">
                <div class="flex justify-between items-center p-5 border-b border-slate-100">
                    <h2 class="font-semibold text-slate-900">Dernières transactions</h2>
                    <a href="/upc_freelance/admin/transactions.php" class="text-xs text-blue-600 hover:underline">Voir tout</a>
                </div>
                <div class="divide-y divide-slate-50">
                    <?php foreach ($recentTx as $tx):
                        $isCredit = in_array($tx['type'], ['deposit','payment','refund']);
                        $typeLabels = ['deposit'=>'Dépôt','withdrawal'=>'Retrait','payment'=>'Paiement','refund'=>'Remboursement','lock'=>'Bloqué','unlock'=>'Libéré'];
                    ?>
                    <div class="flex items-center gap-3 px-5 py-3">
                        <div class="w-8 h-8 rounded-full <?= $isCredit ? 'bg-green-50' : 'bg-red-50' ?> flex items-center justify-center flex-shrink-0">
                            <span class="material-symbols-outlined text-sm <?= $isCredit ? 'text-green-500' : 'text-red-400' ?>"><?= $isCredit ? 'add_circle' : 'remove_circle' ?></span>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-slate-900"><?= $typeLabels[$tx['type']] ?? $tx['type'] ?></p>
                            <p class="text-xs text-slate-400"><?= h($tx['first_name'] . ' ' . $tx['last_name']) ?></p>
                        </div>
                        <div class="text-right flex-shrink-0">
                            <p class="text-sm font-bold <?= $isCredit ? 'text-green-600' : 'text-red-500' ?>">
                                <?= $isCredit ? '+' : '-' ?><?= money((float)$tx['amount']) ?>
                            </p>
                            <p class="text-xs text-slate-400"><?= timeAgo($tx['created_at']) ?></p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Projets récents -->
        <div class="bg-white rounded-2xl border border-slate-100 overflow-hidden">
            <div class="flex justify-between items-center p-5 border-b border-slate-100">
                <h2 class="font-semibold text-slate-900">Projets récents</h2>
                <a href="/upc_freelance/admin/projects.php" class="text-xs text-blue-600 hover:underline">Voir tout</a>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-100">
                            <th class="text-left px-5 py-3 text-xs font-semibold text-slate-500 uppercase">Titre</th>
                            <th class="text-left px-5 py-3 text-xs font-semibold text-slate-500 uppercase">Client</th>
                            <th class="text-left px-5 py-3 text-xs font-semibold text-slate-500 uppercase">Statut</th>
                            <th class="text-left px-5 py-3 text-xs font-semibold text-slate-500 uppercase">Date</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <?php foreach ($recentProjects as $p):
                            $sc = ['open'=>'green','in_progress'=>'blue','completed'=>'gray','cancelled'=>'red'][$p['status']] ?? 'gray';
                            $sl = ['open'=>'Ouvert','in_progress'=>'En cours','completed'=>'Terminé','cancelled'=>'Annulé'][$p['status']] ?? $p['status'];
                        ?>
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="px-5 py-3 font-medium text-slate-900"><?= h(truncate($p['title'], 50)) ?></td>
                            <td class="px-5 py-3 text-slate-600"><?= h($p['first_name'] . ' ' . $p['last_name']) ?></td>
                            <td class="px-5 py-3">
                                <span class="text-xs bg-<?= $sc ?>-100 text-<?= $sc ?>-700 px-2.5 py-1 rounded-full font-medium"><?= $sl ?></span>
                            </td>
                            <td class="px-5 py-3 text-slate-500"><?= timeAgo($p['created_at']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

</body>
</html>