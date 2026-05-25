<?php
// ============================================================
// UPC FREELANCE — Admin : Rapports & Statistiques
// ../../admin/reports.php
// ============================================================

if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['admin_id'])) { header('Location: /upc_freelance/admin/login.php'); exit; }

require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/admin_middleware.php';

$admin = currentAdmin();

$pdo = getDB();

// Inscriptions par mois (12 derniers mois)
$monthly = $pdo->query("
    SELECT DATE_FORMAT(created_at,'%Y-%m') AS month,
           COUNT(*) AS total,
           SUM(role='freelancer') AS freelancers,
           SUM(role='client') AS clients
    FROM users
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY month ORDER BY month ASC
")->fetchAll();

// Projets par catégorie
$byCategory = $pdo->query("
    SELECT c.name, COUNT(p.id) AS nb
    FROM categories c
    LEFT JOIN projects p ON p.category_id = c.id
    GROUP BY c.id ORDER BY nb DESC
")->fetchAll();

// Transactions par mois
$txMonthly = $pdo->query("
    SELECT DATE_FORMAT(created_at,'%Y-%m') AS month,
           SUM(CASE WHEN type='deposit' THEN amount ELSE 0 END) AS deposits,
           SUM(CASE WHEN type='payment' THEN amount ELSE 0 END) AS payments,
           SUM(CASE WHEN type='withdrawal' THEN amount ELSE 0 END) AS withdrawals
    FROM transactions
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY month ORDER BY month ASC
")->fetchAll();

// Top freelancers
$topFreelancers = $pdo->query("
    SELECT u.first_name, u.last_name, fp.rating, fp.total_reviews,
           COUNT(c.id) AS missions, COALESCE(SUM(c.amount),0) AS earned
    FROM users u
    JOIN freelancer_profiles fp ON fp.user_id = u.id
    LEFT JOIN contracts c ON c.freelancer_id = u.id AND c.status = 'completed'
    GROUP BY u.id ORDER BY earned DESC LIMIT 5
")->fetchAll();

// Top clients
$topClients = $pdo->query("
    SELECT u.first_name, u.last_name,
           COUNT(p.id) AS nb_projects, COALESCE(SUM(ct.amount),0) AS spent
    FROM users u
    LEFT JOIN projects p ON p.client_id = u.id
    LEFT JOIN contracts ct ON ct.client_id = u.id AND ct.status = 'completed'
    GROUP BY u.id HAVING nb_projects > 0 ORDER BY spent DESC LIMIT 5
")->fetchAll();
?>
<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Rapports — Admin UPC Freelance</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
<style>*{font-family:'Inter',sans-serif}.material-symbols-outlined{vertical-align:middle}.nav-active{background:#eff4ff;color:#0061a5;border-right:3px solid #0061a5;font-weight:600}</style>
</head><body class="bg-slate-50 min-h-screen flex">
<aside class="w-60 bg-white border-r border-slate-200 flex flex-col sticky top-0 h-screen">
    <div class="p-5 border-b border-slate-100"><div class="flex items-center gap-2.5">
        <svg width="32" height="32" viewBox="0 0 38 38" fill="none"><rect width="38" height="38" rx="10" fill="#002045"/><path d="M10 12 L10 22 Q10 28 19 28 Q28 28 28 22 L28 12" stroke="#fff" stroke-width="2.5" stroke-linecap="round" fill="none"/><circle cx="19" cy="12" r="1.5" fill="#66affe"/><path d="M14 18 L24 18" stroke="#66affe" stroke-width="2" stroke-linecap="round"/></svg>
        <div><p class="font-bold text-sm">UPC Freelance</p><p class="text-xs text-red-500 font-semibold">Administration</p></div>
    </div></div>
    <nav class="flex-1 p-3 space-y-0.5">
        <?php foreach(['dashboard.php'=>['dashboard','Tableau de bord'],'users.php'=>['people','Utilisateurs'],'projects.php'=>['work','Projets'],'transactions.php'=>['receipt_long','Transactions'],'verification.php'=>['verified_user','Vérifications'],'reports.php'=>['bar_chart','Rapports']] as $href=>[$icon,$label]):
        $a=basename($_SERVER['PHP_SELF'])===$href;?>
        <a href="/upc_freelance/admin/<?=$href?>" class="flex items-center gap-2.5 px-3 py-2.5 rounded-lg text-sm <?=$a?'nav-active':'text-slate-600 hover:bg-slate-50'?>">
            <span class="material-symbols-outlined text-lg <?=$a?'text-blue-600':'text-slate-400'?>"><?=$icon?></span><?=$label?>
        </a><?php endforeach;?>
    </nav>
    <div class="p-4 border-t border-slate-100">
        <a href="/upc_freelance/admin/logout.php" class="flex items-center gap-2 px-3 py-2 rounded-lg text-red-500 hover:bg-red-50 text-xs">
            <span class="material-symbols-outlined text-base">logout</span> Déconnexion</a>
    </div>
</aside>

<div class="flex-1 flex flex-col min-w-0">
    <header class="bg-white border-b border-slate-200 px-8 py-4">
        <h1 class="text-xl font-bold">Rapports & Statistiques</h1>
    </header>
    <main class="flex-1 p-8 space-y-8">

        <!-- Inscriptions par mois -->
        <div class="bg-white rounded-2xl border border-slate-100 p-6">
            <h2 class="font-semibold text-slate-900 mb-5">Inscriptions mensuelles (12 derniers mois)</h2>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead><tr class="border-b border-slate-100">
                        <th class="text-left py-2 text-xs font-semibold text-slate-500 uppercase">Mois</th>
                        <th class="text-right py-2 text-xs font-semibold text-slate-500 uppercase">Total</th>
                        <th class="text-right py-2 text-xs font-semibold text-slate-500 uppercase">Freelancers</th>
                        <th class="text-right py-2 text-xs font-semibold text-slate-500 uppercase">Clients</th>
                        <th class="py-2 w-40">Répartition</th>
                    </tr></thead>
                    <tbody class="divide-y divide-slate-50">
                    <?php foreach($monthly as $m):
                        $total = (int)$m['total'];
                        $frPct = $total > 0 ? round($m['freelancers']/$total*100) : 0;
                    ?>
                    <tr>
                        <td class="py-2.5 font-medium text-slate-700"><?=h($m['month'])?></td>
                        <td class="py-2.5 text-right font-bold text-slate-900"><?=$m['total']?></td>
                        <td class="py-2.5 text-right text-purple-600 font-medium"><?=$m['freelancers']?></td>
                        <td class="py-2.5 text-right text-blue-600 font-medium"><?=$m['clients']?></td>
                        <td class="py-2.5 px-2">
                            <div class="flex h-2 rounded-full overflow-hidden bg-slate-100">
                                <div class="bg-purple-400 transition-all" style="width:<?=$frPct?>%"></div>
                                <div class="bg-blue-400 flex-1"></div>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach;?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Top Freelancers -->
            <div class="bg-white rounded-2xl border border-slate-100 p-6">
                <h2 class="font-semibold text-slate-900 mb-4">Top Freelancers</h2>
                <div class="space-y-3">
                    <?php foreach($topFreelancers as $i=>$f):?>
                    <div class="flex items-center gap-3 p-3 rounded-xl bg-slate-50">
                        <span class="w-6 h-6 bg-purple-100 text-purple-700 text-xs font-bold rounded-full flex items-center justify-center flex-shrink-0"><?=$i+1?></span>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-semibold text-slate-900"><?=h($f['first_name'].' '.$f['last_name'])?></p>
                            <p class="text-xs text-slate-400"><?=$f['missions']?> mission<?=$f['missions']>1?'s':''?> · Note: <?=$f['rating']?number_format($f['rating'],1):'N/A'?></p>
                        </div>
                        <span class="text-sm font-bold text-emerald-600 whitespace-nowrap"><?=money((float)$f['earned'])?></span>
                    </div>
                    <?php endforeach;?>
                    <?php if(empty($topFreelancers)):?><p class="text-sm text-slate-400 text-center py-4">Aucune donnée</p><?php endif;?>
                </div>
            </div>

            <!-- Top Clients -->
            <div class="bg-white rounded-2xl border border-slate-100 p-6">
                <h2 class="font-semibold text-slate-900 mb-4">Top Clients</h2>
                <div class="space-y-3">
                    <?php foreach($topClients as $i=>$c):?>
                    <div class="flex items-center gap-3 p-3 rounded-xl bg-slate-50">
                        <span class="w-6 h-6 bg-blue-100 text-blue-700 text-xs font-bold rounded-full flex items-center justify-center flex-shrink-0"><?=$i+1?></span>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-semibold text-slate-900"><?=h($c['first_name'].' '.$c['last_name'])?></p>
                            <p class="text-xs text-slate-400"><?=$c['nb_projects']?> projet<?=$c['nb_projects']>1?'s':''?></p>
                        </div>
                        <span class="text-sm font-bold text-blue-600 whitespace-nowrap"><?=money((float)$c['spent'])?></span>
                    </div>
                    <?php endforeach;?>
                    <?php if(empty($topClients)):?><p class="text-sm text-slate-400 text-center py-4">Aucune donnée</p><?php endif;?>
                </div>
            </div>

            <!-- Projets par catégorie -->
            <div class="bg-white rounded-2xl border border-slate-100 p-6">
                <h2 class="font-semibold text-slate-900 mb-4">Projets par catégorie</h2>
                <?php $maxNb = max(array_column($byCategory,'nb') ?: [1]);?>
                <div class="space-y-3">
                    <?php foreach($byCategory as $cat):
                        $pct = $maxNb > 0 ? round($cat['nb']/$maxNb*100) : 0;
                    ?>
                    <div>
                        <div class="flex justify-between text-sm mb-1">
                            <span class="font-medium text-slate-700"><?=h($cat['name'])?></span>
                            <span class="font-bold text-slate-900"><?=$cat['nb']?></span>
                        </div>
                        <div class="h-2 bg-slate-100 rounded-full overflow-hidden">
                            <div class="h-full bg-blue-500 rounded-full transition-all" style="width:<?=$pct?>%"></div>
                        </div>
                    </div>
                    <?php endforeach;?>
                </div>
            </div>

            <!-- Transactions par mois -->
            <div class="bg-white rounded-2xl border border-slate-100 p-6">
                <h2 class="font-semibold text-slate-900 mb-4">Flux financiers (6 derniers mois)</h2>
                <div class="space-y-3">
                    <?php foreach($txMonthly as $tx):?>
                    <div class="p-3 rounded-xl bg-slate-50">
                        <p class="text-xs font-semibold text-slate-500 mb-2"><?=h($tx['month'])?></p>
                        <div class="grid grid-cols-3 gap-2 text-xs">
                            <div class="text-center">
                                <p class="text-slate-400">Dépôts</p>
                                <p class="font-bold text-green-600"><?=money((float)$tx['deposits'])?></p>
                            </div>
                            <div class="text-center border-x border-slate-200">
                                <p class="text-slate-400">Paiements</p>
                                <p class="font-bold text-blue-600"><?=money((float)$tx['payments'])?></p>
                            </div>
                            <div class="text-center">
                                <p class="text-slate-400">Retraits</p>
                                <p class="font-bold text-red-500"><?=money((float)$tx['withdrawals'])?></p>
                            </div>
                        </div>
                    </div>
                    <?php endforeach;?>
                    <?php if(empty($txMonthly)):?><p class="text-sm text-slate-400 text-center py-4">Aucune donnée</p><?php endif;?>
                </div>
            </div>
        </div>
    </main>
</div>
</body></html>
