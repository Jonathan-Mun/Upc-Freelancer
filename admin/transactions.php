<?php
// ============================================================
// UPC FREELANCE — Admin : Transactions
// ../../admin/transactions.php
// ============================================================
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['admin_id'])) { header('Location: /upc_freelance/admin/login.php'); exit; }

require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/admin_middleware.php';

$admin = currentAdmin();

$pdo    = getDB();
$type   = sanitize($_GET['type']   ?? '');
$search = sanitize($_GET['search'] ?? '');
$page   = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;

$where  = ['1=1'];
$params = [];
if ($search) { $where[] = '(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)'; $params = array_merge($params, ["%$search%","%$search%","%$search%"]); }
if ($type && in_array($type, ['deposit','withdrawal','payment','refund','lock','unlock'])) { $where[] = 't.type = ?'; $params[] = $type; }
$wc = implode(' AND ', $where);

$cstmt = $pdo->prepare("SELECT COUNT(*) FROM transactions t JOIN users u ON u.id = t.user_id WHERE $wc");
$cstmt->execute($params); $total = (int)$cstmt->fetchColumn();
$offset = ($page - 1) * $perPage; $totalPages = (int)ceil($total / $perPage);

$stmt = $pdo->prepare("
    SELECT t.*, u.first_name, u.last_name, u.role, p.title AS project_title
    FROM transactions t
    JOIN users u ON u.id = t.user_id
    LEFT JOIN contracts c ON c.id = t.contract_id
    LEFT JOIN projects  p ON p.id = c.project_id
    WHERE $wc ORDER BY t.created_at DESC LIMIT $perPage OFFSET $offset
");
$stmt->execute($params); $txs = $stmt->fetchAll();

// Totaux
$totals = $pdo->query("SELECT type, SUM(amount) AS total FROM transactions GROUP BY type")->fetchAll(PDO::FETCH_KEY_PAIR);
?>
<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Transactions — Admin UPC Freelance</title>
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
        <?php foreach(['dashboard.php'=>['dashboard','Tableau de bord'],'users.php'=>['people','Utilisateurs'],'projects.php'=>['work','Projets'],'transactions.php'=>['receipt_long','Transactions'],'verification.php'=>['verified_user','Vérifications'],'disputes.php'=>['gavel','Litiges'],'reports.php'=>['bar_chart','Rapports']] as $href=>[$icon,$label]):
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
    <header class="bg-white border-b border-slate-200 px-8 py-4 flex items-center justify-between">
        <h1 class="text-xl font-bold">Transactions</h1>
        <span class="text-sm text-slate-500"><?= $total ?> transaction<?= $total > 1 ? 's' : '' ?></span>
    </header>
    <main class="flex-1 p-8 space-y-6">

        <!-- Cartes totaux -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <?php
            $cards = [
                ['label'=>'Dépôts totaux',  'type'=>'deposit',    'color'=>'text-green-600', 'bg'=>'bg-green-50',  'icon'=>'add_circle'],
                ['label'=>'Retraits totaux', 'type'=>'withdrawal', 'color'=>'text-red-600',   'bg'=>'bg-red-50',    'icon'=>'remove_circle'],
                ['label'=>'Paiements',       'type'=>'payment',    'color'=>'text-blue-600',  'bg'=>'bg-blue-50',   'icon'=>'payments'],
                ['label'=>'Remboursements',  'type'=>'refund',     'color'=>'text-amber-600', 'bg'=>'bg-amber-50',  'icon'=>'undo'],
            ];
            foreach($cards as $card):
            ?>
            <div class="bg-white rounded-2xl border border-slate-100 p-4">
                <div class="flex items-center gap-2 mb-2">
                    <div class="w-8 h-8 <?=$card['bg']?> rounded-xl flex items-center justify-center">
                        <span class="material-symbols-outlined <?=$card['color']?> text-base"><?=$card['icon']?></span>
                    </div>
                    <p class="text-xs text-slate-500 font-medium"><?=$card['label']?></p>
                </div>
                <p class="text-xl font-bold text-slate-900"><?=money((float)($totals[$card['type']]??0))?></p>
            </div>
            <?php endforeach;?>
        </div>

        <!-- Filtres -->
        <form method="GET" class="flex flex-wrap gap-3">
            <div class="relative">
                <span class="absolute left-3 top-1/2 -translate-y-1/2 material-symbols-outlined text-slate-400 text-lg">search</span>
                <input type="text" name="search" value="<?=h($search)?>" placeholder="Utilisateur..."
                       class="pl-9 pr-4 py-2 rounded-xl border border-slate-200 text-sm outline-none w-48 focus:border-blue-500"/>
            </div>
            <select name="type" class="px-4 py-2 rounded-xl border border-slate-200 text-sm outline-none">
                <option value="">Tous les types</option>
                <?php foreach(['deposit'=>'Dépôt','withdrawal'=>'Retrait','payment'=>'Paiement','refund'=>'Remboursement','lock'=>'Bloqué','unlock'=>'Libéré'] as $v=>$l):?>
                <option value="<?=$v?>" <?=$type===$v?'selected':''?>><?=$l?></option>
                <?php endforeach;?>
            </select>
            <button type="submit" class="bg-slate-900 text-white px-4 py-2 rounded-xl text-sm hover:bg-slate-800">Filtrer</button>
        </form>

        <!-- Table -->
        <div class="bg-white rounded-2xl border border-slate-100 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead><tr class="border-b border-slate-100 bg-slate-50">
                        <th class="text-left px-5 py-3 text-xs font-semibold text-slate-500 uppercase">Utilisateur</th>
                        <th class="text-left px-5 py-3 text-xs font-semibold text-slate-500 uppercase">Type</th>
                        <th class="text-left px-5 py-3 text-xs font-semibold text-slate-500 uppercase">Montant</th>
                        <th class="text-left px-5 py-3 text-xs font-semibold text-slate-500 uppercase">Solde après</th>
                        <th class="text-left px-5 py-3 text-xs font-semibold text-slate-500 uppercase">Projet</th>
                        <th class="text-left px-5 py-3 text-xs font-semibold text-slate-500 uppercase">Statut</th>
                        <th class="text-left px-5 py-3 text-xs font-semibold text-slate-500 uppercase">Date</th>
                    </tr></thead>
                    <tbody class="divide-y divide-slate-50">
                    <?php foreach($txs as $tx):
                        $isCredit = in_array($tx['type'],['deposit','payment','refund','unlock']);
                        $tl = ['deposit'=>'Dépôt','withdrawal'=>'Retrait','payment'=>'Paiement','refund'=>'Remboursement','lock'=>'Bloqué','unlock'=>'Libéré'];
                    ?>
                    <tr class="hover:bg-slate-50">
                        <td class="px-5 py-3">
                            <p class="font-medium"><?=h($tx['first_name'].' '.$tx['last_name'])?></p>
                            <p class="text-xs text-slate-400 capitalize"><?=$tx['role']?></p>
                        </td>
                        <td class="px-5 py-3">
                            <span class="inline-flex items-center gap-1 text-xs px-2.5 py-1 rounded-full font-medium <?=$isCredit?'bg-green-100 text-green-700':'bg-red-100 text-red-600'?>">
                                <span class="material-symbols-outlined text-sm"><?=$isCredit?'add':'remove'?></span>
                                <?=$tl[$tx['type']]??$tx['type']?>
                            </span>
                        </td>
                        <td class="px-5 py-3 font-bold <?=$isCredit?'text-green-600':'text-red-500'?>">
                            <?=$isCredit?'+':'-'?><?=money((float)$tx['amount'])?>
                        </td>
                        <td class="px-5 py-3 text-slate-600"><?=money((float)$tx['balance_after'])?></td>
                        <td class="px-5 py-3 text-slate-500 text-xs max-w-[150px]"><p class="truncate"><?=h($tx['project_title']??'—')?></p></td>
                        <td class="px-5 py-3">
                            <span class="text-xs px-2 py-0.5 rounded-full <?=$tx['status']==='completed'?'bg-green-100 text-green-700':'bg-amber-100 text-amber-700'?>">
                                <?=$tx['status']?>
                            </span>
                        </td>
                        <td class="px-5 py-3 text-slate-500 text-xs"><?=formatDate($tx['created_at'],'d/m/Y H:i')?></td>
                    </tr>
                    <?php endforeach;?>
                    </tbody>
                </table>
            </div>
            <?php if($totalPages>1):?>
            <div class="flex justify-center gap-2 p-4 border-t border-slate-100">
                <?php for($i=1;$i<=$totalPages;$i++):?>
                <a href="?page=<?=$i?>&search=<?=urlencode($search)?>&type=<?=$type?>"
                   class="px-3 py-1.5 rounded-lg border text-sm <?=$i===$page?'bg-slate-900 text-white border-slate-900':'border-slate-200 hover:border-slate-300'?>"><?=$i?></a>
                <?php endfor;?>
            </div>
            <?php endif;?>
        </div>
    </main>
</div>
</body></html>