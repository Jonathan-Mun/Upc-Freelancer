<?php
// ============================================================
// UPC FREELANCE — Admin : Gestion des projets
// /var/www/html/upc_freelance/admin/projects.php
// ============================================================

if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['admin_id'])) { header('Location: /upc_freelance/admin/login.php'); exit; }

require_once '/var/www/html/upc_freelance/includes/db.php';
require_once '/var/www/html/upc_freelance/includes/functions.php';

$pdo = getDB();

// Action : changer statut
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $projectId = (int)($_POST['project_id'] ?? 0);
    $newStatus  = sanitize($_POST['new_status'] ?? '');
    $allowed    = ['open','in_progress','completed','cancelled'];
    if ($projectId && in_array($newStatus, $allowed)) {
        $pdo->prepare('UPDATE projects SET status = ? WHERE id = ?')->execute([$newStatus, $projectId]);
    }
    header('Location: /upc_freelance/admin/projects.php?' . http_build_query($_GET)); exit;
}

// Filtres
$search = sanitize($_GET['search'] ?? '');
$status = sanitize($_GET['status'] ?? '');
$page   = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;

$where  = ['1=1'];
$params = [];
if ($search) { $where[] = '(p.title LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ?)'; $params = array_merge($params, ["%$search%","%$search%","%$search%"]); }
if ($status && in_array($status, ['open','in_progress','completed','cancelled'])) { $where[] = 'p.status = ?'; $params[] = $status; }
$wc = implode(' AND ', $where);

$cstmt = $pdo->prepare("SELECT COUNT(*) FROM projects p JOIN users u ON u.id = p.client_id WHERE $wc");
$cstmt->execute($params); $total = (int)$cstmt->fetchColumn();
$offset = ($page - 1) * $perPage; $totalPages = (int)ceil($total / $perPage);

$stmt = $pdo->prepare("
    SELECT p.*, u.first_name, u.last_name, c.name AS cat_name,
           (SELECT COUNT(*) FROM postulations WHERE project_id = p.id) AS nb_post
    FROM projects p
    JOIN users u ON u.id = p.client_id
    LEFT JOIN categories c ON c.id = p.category_id
    WHERE $wc ORDER BY p.created_at DESC LIMIT $perPage OFFSET $offset
");
$stmt->execute($params); $projects = $stmt->fetchAll();

$adminSidebar = '/upc_freelance/admin/';
?>
<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"/><meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Projets — Admin UPC Freelance</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
<style>*{font-family:'Inter',sans-serif;}.material-symbols-outlined{vertical-align:middle;}.nav-active{background:#eff4ff;color:#0061a5;border-right:3px solid #0061a5;font-weight:600;}</style>
</head><body class="bg-slate-50 text-slate-900 min-h-screen flex">
<aside class="w-60 bg-white border-r border-slate-200 flex flex-col sticky top-0 h-screen">
    <div class="p-5 border-b border-slate-100"><div class="flex items-center gap-2.5">
        <svg width="32" height="32" viewBox="0 0 38 38" fill="none"><rect width="38" height="38" rx="10" fill="#002045"/><path d="M10 12 L10 22 Q10 28 19 28 Q28 28 28 22 L28 12" stroke="#fff" stroke-width="2.5" stroke-linecap="round" fill="none"/><circle cx="19" cy="12" r="1.5" fill="#66affe"/><path d="M14 18 L24 18" stroke="#66affe" stroke-width="2" stroke-linecap="round"/></svg>
        <div><p class="font-bold text-sm">UPC Freelance</p><p class="text-xs text-red-500 font-semibold">Administration</p></div>
    </div></div>
    <nav class="flex-1 p-3 space-y-0.5">
        <?php foreach(['dashboard.php'=>['dashboard','Tableau de bord'],'users.php'=>['people','Utilisateurs'],'projects.php'=>['work','Projets'],'transactions.php'=>['receipt_long','Transactions'],'verification.php'=>['verified_user','Vérifications'],'reports.php'=>['bar_chart','Rapports']] as $href=>[$icon,$label]):
        $a=basename($_SERVER['PHP_SELF'])===$href;?>
        <a href="/upc_freelance/admin/<?=$href?>" class="flex items-center gap-2.5 px-3 py-2.5 rounded-lg text-sm transition-all <?=$a?'nav-active':'text-slate-600 hover:bg-slate-50'?>">
            <span class="material-symbols-outlined text-lg <?=$a?'text-blue-600':'text-slate-400'?>"><?=$icon?></span><?=$label?>
        </a><?php endforeach;?>
    </nav>
    <div class="p-4 border-t border-slate-100">
        <a href="/upc_freelance/admin/logout.php" class="flex items-center gap-2 px-3 py-2 rounded-lg text-red-500 hover:bg-red-50 text-xs">
            <span class="material-symbols-outlined text-base">logout</span> Déconnexion
        </a>
    </div>
</aside>

<div class="flex-1 flex flex-col min-w-0">
    <header class="bg-white border-b border-slate-200 px-8 py-4 flex items-center justify-between">
        <h1 class="text-xl font-bold">Projets</h1>
        <span class="text-sm text-slate-500"><?= $total ?> projet<?= $total > 1 ? 's' : '' ?></span>
    </header>
    <main class="flex-1 p-8">
        <!-- Filtres -->
        <form method="GET" class="flex flex-wrap gap-3 mb-6">
            <div class="relative">
                <span class="absolute left-3 top-1/2 -translate-y-1/2 material-symbols-outlined text-slate-400 text-lg">search</span>
                <input type="text" name="search" value="<?= h($search) ?>" placeholder="Titre, client..."
                       class="pl-9 pr-4 py-2 rounded-xl border border-slate-200 text-sm outline-none w-56 focus:border-blue-500"/>
            </div>
            <select name="status" class="px-4 py-2 rounded-xl border border-slate-200 text-sm outline-none">
                <option value="">Tous les statuts</option>
                <?php foreach(['open'=>'Ouvert','in_progress'=>'En cours','completed'=>'Terminé','cancelled'=>'Annulé'] as $v=>$l):?>
                <option value="<?=$v?>" <?=$status===$v?'selected':''?>><?=$l?></option>
                <?php endforeach;?>
            </select>
            <button type="submit" class="bg-slate-900 text-white px-4 py-2 rounded-xl text-sm hover:bg-slate-800">Filtrer</button>
        </form>

        <div class="bg-white rounded-2xl border border-slate-100 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead><tr class="border-b border-slate-100 bg-slate-50">
                        <th class="text-left px-5 py-3 text-xs font-semibold text-slate-500 uppercase">Titre</th>
                        <th class="text-left px-5 py-3 text-xs font-semibold text-slate-500 uppercase">Client</th>
                        <th class="text-left px-5 py-3 text-xs font-semibold text-slate-500 uppercase">Catégorie</th>
                        <th class="text-left px-5 py-3 text-xs font-semibold text-slate-500 uppercase">Candidatures</th>
                        <th class="text-left px-5 py-3 text-xs font-semibold text-slate-500 uppercase">Budget</th>
                        <th class="text-left px-5 py-3 text-xs font-semibold text-slate-500 uppercase">Statut</th>
                        <th class="text-left px-5 py-3 text-xs font-semibold text-slate-500 uppercase">Date</th>
                        <th class="text-left px-5 py-3 text-xs font-semibold text-slate-500 uppercase">Action</th>
                    </tr></thead>
                    <tbody class="divide-y divide-slate-50">
                    <?php foreach($projects as $p):
                        $sc=['open'=>'green','in_progress'=>'blue','completed'=>'gray','cancelled'=>'red'][$p['status']]??'gray';
                        $sl=['open'=>'Ouvert','in_progress'=>'En cours','completed'=>'Terminé','cancelled'=>'Annulé'][$p['status']]??$p['status'];
                    ?>
                    <tr class="hover:bg-slate-50">
                        <td class="px-5 py-3 font-medium max-w-[180px]"><p class="truncate"><?=h($p['title'])?></p></td>
                        <td class="px-5 py-3 text-slate-600"><?=h($p['first_name'].' '.$p['last_name'])?></td>
                        <td class="px-5 py-3 text-slate-500 text-xs"><?=h($p['cat_name']??'—')?></td>
                        <td class="px-5 py-3 text-center font-bold text-slate-700"><?=$p['nb_post']?></td>
                        <td class="px-5 py-3 text-secondary font-semibold text-xs"><?=$p['budget_max']||$p['budget_min']?money((float)($p['budget_max']??$p['budget_min'])):'—'?></td>
                        <td class="px-5 py-3"><span class="text-xs bg-<?=$sc?>-100 text-<?=$sc?>-700 px-2.5 py-1 rounded-full font-medium"><?=$sl?></span></td>
                        <td class="px-5 py-3 text-slate-500 text-xs"><?=formatDate($p['created_at'])?></td>
                        <td class="px-5 py-3">
                            <form method="POST" class="flex gap-1">
                                <input type="hidden" name="project_id" value="<?=$p['id']?>"/>
                                <select name="new_status" class="text-xs px-2 py-1 rounded-lg border border-slate-200 outline-none">
                                    <?php foreach(['open'=>'Ouvert','in_progress'=>'En cours','completed'=>'Terminé','cancelled'=>'Annulé'] as $v=>$l):?>
                                    <option value="<?=$v?>" <?=$p['status']===$v?'selected':''?>><?=$l?></option>
                                    <?php endforeach;?>
                                </select>
                                <button type="submit" class="text-xs bg-slate-800 text-white px-2 py-1 rounded-lg hover:bg-slate-700">OK</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach;?>
                    </tbody>
                </table>
            </div>
            <?php if($totalPages>1):?>
            <div class="flex justify-center gap-2 p-4 border-t border-slate-100">
                <?php for($i=1;$i<=$totalPages;$i++):?>
                <a href="?page=<?=$i?>&search=<?=urlencode($search)?>&status=<?=$status?>"
                   class="px-3 py-1.5 rounded-lg border text-sm <?=$i===$page?'bg-slate-900 text-white border-slate-900':'border-slate-200 hover:border-slate-300'?>"><?=$i?></a>
                <?php endfor;?>
            </div>
            <?php endif;?>
        </div>
    </main>
</div>
</body></html>
