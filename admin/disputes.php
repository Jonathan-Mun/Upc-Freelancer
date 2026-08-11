<?php
// ============================================================
// UPC FREELANCE — Admin : Litiges & vérifications
// /admin/disputes.php
// ============================================================

require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/admin_middleware.php';

$admin = currentAdmin();
$pdo   = getDB();
$tab   = in_array($_GET['tab'] ?? '', ['disputes','cancel','fraud','reports']) ? $_GET['tab'] : 'disputes';

// ── Litiges classiques (pas des demandes d'annulation) ─────────
$disputes = $pdo->query("
    SELECT c.*, p.title AS project_title,
           cl.first_name AS client_fname, cl.last_name AS client_lname,
           fr.first_name AS freelancer_fname, fr.last_name AS freelancer_lname
    FROM contracts c
    JOIN projects p ON p.id = c.project_id
    JOIN users cl ON cl.id = c.client_id
    JOIN users fr ON fr.id = c.freelancer_id
    WHERE c.status = 'disputed' AND c.cancel_requested_by IS NULL
    ORDER BY c.updated_at DESC
")->fetchAll();

// ── Demandes d'annulation après livraison, en attente ──────────
$cancelRequests = $pdo->query("
    SELECT c.*, p.title AS project_title,
           cl.first_name AS client_fname, cl.last_name AS client_lname,
           fr.first_name AS freelancer_fname, fr.last_name AS freelancer_lname
    FROM contracts c
    JOIN projects p ON p.id = c.project_id
    JOIN users cl ON cl.id = c.client_id
    JOIN users fr ON fr.id = c.freelancer_id
    WHERE c.status = 'disputed' AND c.cancel_requested_by IS NOT NULL
    ORDER BY c.updated_at DESC
")->fetchAll();

// ── Contrats signalés (fraude potentielle, IA ou manuel) ────────
$fraudFlagged = $pdo->query("
    SELECT c.*, p.title AS project_title,
           cl.first_name AS client_fname, cl.last_name AS client_lname,
           fr.first_name AS freelancer_fname, fr.last_name AS freelancer_lname
    FROM contracts c
    JOIN projects p ON p.id = c.project_id
    JOIN users cl ON cl.id = c.client_id
    JOIN users fr ON fr.id = c.freelancer_id
    WHERE c.fraud_flag = 1
    ORDER BY c.updated_at DESC
")->fetchAll();

// ── Plaintes en attente ─────────────────────────────────────────
$reports = $pdo->query("
    SELECT cr.*, c.id AS contract_id, p.title AS project_title,
           u.first_name, u.last_name, u.role
    FROM contract_reports cr
    JOIN contracts c ON c.id = cr.contract_id
    JOIN projects p  ON p.id = c.project_id
    JOIN users u     ON u.id = cr.reporter_id
    WHERE cr.status = 'pending'
    ORDER BY cr.created_at DESC
")->fetchAll();

function money2($v) { return number_format((float)$v, 0, ',', ' ') . ' USD'; }
function timeAgo2($d) {
    $s = time() - strtotime($d);
    if ($s < 60) return "à l'instant";
    if ($s < 3600) return floor($s/60) . ' min';
    if ($s < 86400) return floor($s/3600) . ' h';
    return floor($s/86400) . ' j';
}

$navItems = [
    ['dashboard.php',    'dashboard',      'Tableau de bord'],
    ['users.php',        'group',          'Utilisateurs'],
    ['projects.php',     'work',           'Projets'],
    ['transactions.php', 'account_balance','Transactions'],
    ['verification.php', 'verified_user',  'Vérifications'],
    ['disputes.php',     'gavel',          'Litiges'],
    ['reports.php',      'bar_chart',      'Rapports'],
];
$totalPending = count($disputes) + count($cancelRequests) + count($reports);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Litiges — Admin UPC Freelance</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" />
<style>
.material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; font-size: 20px; vertical-align: middle; }
body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
</style>
</head>
<body class="bg-slate-50 min-h-screen lg:flex">

<!-- Topbar mobile -->
<header class="lg:hidden sticky top-0 z-30 bg-white border-b border-slate-200 px-4 py-3 flex items-center justify-between">
    <div class="flex items-center gap-2">
        <div class="w-8 h-8 rounded-lg bg-[#002045] flex items-center justify-center text-white font-bold text-xs">UF</div>
        <span class="font-bold text-sm text-[#002045]">Admin</span>
    </div>
    <button onclick="toggleSidebar()" class="p-2 rounded-lg hover:bg-slate-100">
        <span class="material-symbols-outlined">menu</span>
    </button>
</header>

<!-- Overlay mobile -->
<div id="sidebar-overlay" onclick="toggleSidebar()" class="hidden fixed inset-0 bg-black/40 z-40 lg:hidden"></div>

<!-- Sidebar -->
<aside id="admin-sidebar"
       class="fixed lg:sticky top-0 left-0 h-screen w-72 bg-white border-r border-slate-200 flex flex-col z-50
              -translate-x-full lg:translate-x-0 transition-transform duration-200 ease-out">
    <div class="flex items-center gap-3 px-5 py-5 border-b border-slate-100">
        <div class="w-9 h-9 rounded-xl bg-[#002045] flex items-center justify-center text-white font-bold text-sm">UF</div>
        <div>
            <p class="font-bold text-[#002045] text-sm">UPC Freelance</p>
            <p class="text-xs text-slate-400">Panel administrateur</p>
        </div>
        <button onclick="toggleSidebar()" class="lg:hidden ml-auto p-1.5 rounded-lg hover:bg-slate-100 text-slate-400">
            <span class="material-symbols-outlined text-lg">close</span>
        </button>
    </div>

    <nav class="flex-1 px-3 py-4 space-y-1 overflow-y-auto">
        <?php foreach ($navItems as [$href, $icon, $label]):
            $active = basename($_SERVER['PHP_SELF']) === $href; ?>
        <a href="<?= $href ?>"
           class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-colors
                  <?= $active ? 'bg-[#002045] text-white' : 'text-slate-600 hover:bg-slate-100' ?>">
            <span class="material-symbols-outlined text-lg"><?= $icon ?></span>
            <?= $label ?>
            <?php if ($href === 'disputes.php' && $totalPending > 0): ?>
            <span class="ml-auto text-[10px] font-bold <?= $active ? 'bg-white/20 text-white' : 'bg-red-100 text-red-600' ?> px-2 py-0.5 rounded-full">
                <?= $totalPending ?>
            </span>
            <?php endif; ?>
        </a>
        <?php endforeach; ?>
    </nav>

    <div class="px-5 py-4 border-t border-slate-100">
        <p class="text-xs font-semibold text-[#002045] truncate"><?= h($admin['name']) ?></p>
        <a href="logout.php" class="text-xs text-red-500 hover:underline">Déconnexion</a>
    </div>
</aside>

<div class="flex-1 flex flex-col min-w-0">
    <main class="flex-1 p-4 sm:p-6 lg:p-8 max-w-6xl w-full mx-auto">

        <div class="mb-6">
            <h1 class="text-2xl font-bold text-[#002045] flex items-center gap-2">
                <span class="material-symbols-outlined text-2xl">gavel</span>
                Litiges &amp; vérifications
            </h1>
            <p class="text-sm text-slate-500 mt-1">Gérez les litiges, demandes d'annulation, signalements et contrats suspects.</p>
        </div>

        <!-- Tabs -->
        <div class="flex gap-2 overflow-x-auto pb-1 mb-6 -mx-1 px-1">
            <?php
            $tabs = [
                'disputes' => ['Litiges ouverts',       count($disputes),      'amber'],
                'cancel'   => ["Demandes d'annulation",  count($cancelRequests),'red'],
                'fraud'    => ['Signalés fraude',        count($fraudFlagged),  'purple'],
                'reports'  => ['Plaintes',               count($reports),       'blue'],
            ];
            foreach ($tabs as $key => [$label, $count, $color]): ?>
            <a href="?tab=<?= $key ?>"
               class="flex-shrink-0 flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold border transition-colors
                      <?= $tab === $key ? 'bg-[#002045] text-white border-[#002045]' : 'bg-white text-slate-600 border-slate-200 hover:border-slate-300' ?>">
                <?= $label ?>
                <span class="text-[10px] font-bold px-1.5 py-0.5 rounded-full
                             <?= $tab === $key ? 'bg-white/20' : "bg-{$color}-100 text-{$color}-700" ?>">
                    <?= $count ?>
                </span>
            </a>
            <?php endforeach; ?>
        </div>

        <!-- ══ TAB: Litiges ═══════════════════════════════════ -->
        <?php if ($tab === 'disputes'): ?>
        <div class="space-y-3">
            <?php if (empty($disputes)): ?>
            <div class="bg-white rounded-2xl border border-slate-200 p-10 text-center text-slate-400">
                <span class="material-symbols-outlined text-3xl block mb-2">check_circle</span>
                Aucun litige ouvert pour le moment.
            </div>
            <?php endif; ?>
            <?php foreach ($disputes as $d): ?>
            <div class="bg-white rounded-2xl border border-amber-200 p-4 sm:p-5 flex flex-col sm:flex-row sm:items-center gap-4">
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 mb-1">
                        <span class="text-xs font-bold bg-amber-100 text-amber-700 px-2 py-0.5 rounded-full">Litige</span>
                        <?php if ($d['fraud_flag']): ?>
                        <span class="text-xs font-bold bg-purple-100 text-purple-700 px-2 py-0.5 rounded-full">⚠ Fraude suspectée</span>
                        <?php endif; ?>
                    </div>
                    <p class="font-semibold text-[#002045] truncate"><?= h($d['project_title']) ?></p>
                    <p class="text-xs text-slate-500 mt-0.5">
                        <?= h($d['client_fname'].' '.$d['client_lname']) ?> (client) ↔
                        <?= h($d['freelancer_fname'].' '.$d['freelancer_lname']) ?> (freelancer)
                    </p>
                    <p class="text-xs text-slate-400 mt-1 line-clamp-2"><?= h($d['dispute_reason'] ?? '') ?></p>
                </div>
                <div class="flex items-center gap-4 flex-shrink-0">
                    <div class="text-right">
                        <p class="text-xs text-slate-400">Montant</p>
                        <p class="font-bold text-sm text-[#0061a5]"><?= money2($d['amount']) ?></p>
                    </div>
                    <a href="dispute-details.php?id=<?= $d['id'] ?>"
                       class="bg-[#002045] text-white text-sm font-semibold px-4 py-2.5 rounded-xl hover:opacity-90 transition-opacity whitespace-nowrap">
                        Examiner
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- ══ TAB: Demandes d'annulation ══════════════════════ -->
        <?php if ($tab === 'cancel'): ?>
        <div class="space-y-3">
            <?php if (empty($cancelRequests)): ?>
            <div class="bg-white rounded-2xl border border-slate-200 p-10 text-center text-slate-400">
                <span class="material-symbols-outlined text-3xl block mb-2">check_circle</span>
                Aucune demande d'annulation en attente.
            </div>
            <?php endif; ?>
            <?php foreach ($cancelRequests as $d): ?>
            <div class="bg-white rounded-2xl border border-red-200 p-4 sm:p-5 flex flex-col sm:flex-row sm:items-center gap-4">
                <div class="flex-1 min-w-0">
                    <span class="text-xs font-bold bg-red-100 text-red-700 px-2 py-0.5 rounded-full">Demande d'annulation après livraison</span>
                    <p class="font-semibold text-[#002045] truncate mt-1"><?= h($d['project_title']) ?></p>
                    <p class="text-xs text-slate-500 mt-0.5">
                        Demandée par <?= h($d['client_fname'].' '.$d['client_lname']) ?> (client) ·
                        Freelancer : <?= h($d['freelancer_fname'].' '.$d['freelancer_lname']) ?>
                    </p>
                    <p class="text-xs text-slate-400 mt-1 line-clamp-2"><?= h($d['cancel_reason'] ?? '') ?></p>
                </div>
                <div class="flex items-center gap-4 flex-shrink-0">
                    <div class="text-right">
                        <p class="text-xs text-slate-400">Montant bloqué</p>
                        <p class="font-bold text-sm text-[#0061a5]"><?= money2($d['amount']) ?></p>
                    </div>
                    <a href="dispute-details.php?id=<?= $d['id'] ?>"
                       class="bg-[#002045] text-white text-sm font-semibold px-4 py-2.5 rounded-xl hover:opacity-90 transition-opacity whitespace-nowrap">
                        Vérifier
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- ══ TAB: Fraude ══════════════════════════════════════ -->
        <?php if ($tab === 'fraud'): ?>
        <div class="space-y-3">
            <?php if (empty($fraudFlagged)): ?>
            <div class="bg-white rounded-2xl border border-slate-200 p-10 text-center text-slate-400">
                <span class="material-symbols-outlined text-3xl block mb-2">check_circle</span>
                Aucun contrat signalé pour fraude.
            </div>
            <?php endif; ?>
            <?php foreach ($fraudFlagged as $d): ?>
            <div class="bg-white rounded-2xl border border-purple-200 p-4 sm:p-5 flex flex-col sm:flex-row sm:items-center gap-4">
                <div class="flex-1 min-w-0">
                    <span class="text-xs font-bold bg-purple-100 text-purple-700 px-2 py-0.5 rounded-full">⚠ Fraude suspectée</span>
                    <span class="text-xs font-bold bg-slate-100 text-slate-600 px-2 py-0.5 rounded-full ml-1"><?= $d['status'] ?></span>
                    <p class="font-semibold text-[#002045] truncate mt-1"><?= h($d['project_title']) ?></p>
                    <p class="text-xs text-slate-500 mt-0.5">
                        <?= h($d['client_fname'].' '.$d['client_lname']) ?> ↔ <?= h($d['freelancer_fname'].' '.$d['freelancer_lname']) ?>
                    </p>
                    <p class="text-xs text-slate-400 mt-1 line-clamp-2"><?= h($d['fraud_note'] ?? 'Détecté automatiquement par l\'analyse IA.') ?></p>
                </div>
                <div class="flex items-center gap-4 flex-shrink-0">
                    <div class="text-right">
                        <p class="text-xs text-slate-400">Montant</p>
                        <p class="font-bold text-sm text-[#0061a5]"><?= money2($d['amount']) ?></p>
                    </div>
                    <a href="dispute-details.php?id=<?= $d['id'] ?>"
                       class="bg-[#002045] text-white text-sm font-semibold px-4 py-2.5 rounded-xl hover:opacity-90 transition-opacity whitespace-nowrap">
                        Examiner
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- ══ TAB: Plaintes ═══════════════════════════════════ -->
        <?php if ($tab === 'reports'): ?>
        <div class="space-y-3">
            <?php if (empty($reports)): ?>
            <div class="bg-white rounded-2xl border border-slate-200 p-10 text-center text-slate-400">
                <span class="material-symbols-outlined text-3xl block mb-2">check_circle</span>
                Aucune plainte en attente.
            </div>
            <?php endif; ?>
            <?php foreach ($reports as $r): ?>
            <div class="bg-white rounded-2xl border border-blue-200 p-4 sm:p-5 flex flex-col sm:flex-row sm:items-center gap-4">
                <div class="flex-1 min-w-0">
                    <span class="text-xs font-bold bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full">
                        Signalé par <?= $r['role'] === 'client' ? 'un client' : 'un freelancer' ?>
                    </span>
                    <p class="font-semibold text-[#002045] truncate mt-1"><?= h($r['project_title']) ?></p>
                    <p class="text-xs text-slate-500 mt-0.5"><?= h($r['first_name'].' '.$r['last_name']) ?> · <?= timeAgo2($r['created_at']) ?></p>
                    <p class="text-xs text-slate-400 mt-1 line-clamp-2"><?= h($r['reason']) ?></p>
                </div>
                <a href="dispute-details.php?id=<?= $r['contract_id'] ?>"
                   class="bg-[#002045] text-white text-sm font-semibold px-4 py-2.5 rounded-xl hover:opacity-90 transition-opacity whitespace-nowrap flex-shrink-0">
                    Voir le contrat
                </a>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

    </main>
</div>

<script>
function toggleSidebar() {
    document.getElementById('admin-sidebar').classList.toggle('-translate-x-full');
    document.getElementById('sidebar-overlay').classList.toggle('hidden');
}
</script>
</body>
</html>