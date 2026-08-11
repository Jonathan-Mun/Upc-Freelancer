<?php
// ============================================================
// UPC FREELANCE — Admin : Détails d'un litige / contrat
// /admin/dispute-details.php
// ============================================================

require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/admin_middleware.php';

$admin      = currentAdmin();
$pdo        = getDB();
$contractId = (int)($_GET['id'] ?? 0);
if (!$contractId) { header('Location: disputes.php'); exit; }

$stmt = $pdo->prepare('
    SELECT c.*, p.title AS project_title, p.id AS project_id,
           cl.first_name AS client_fname, cl.last_name AS client_lname, cl.email AS client_email, cl.phone AS client_phone,
           fr.first_name AS freelancer_fname, fr.last_name AS freelancer_lname, fr.email AS freelancer_email, fr.phone AS freelancer_phone
    FROM contracts c
    JOIN projects p ON p.id = c.project_id
    JOIN users cl   ON cl.id = c.client_id
    JOIN users fr   ON fr.id = c.freelancer_id
    WHERE c.id = ?
');
$stmt->execute([$contractId]);
$contract = $stmt->fetch();
if (!$contract) { header('Location: disputes.php'); exit; }

$flashMsg = null; $flashType = null;

// ─── Actions admin ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = sanitize($_POST['action'] ?? '');
    $note   = sanitize($_POST['note'] ?? '');

    // Trancher un litige / une demande d'annulation en faveur du freelancer (payer)
    if (in_array($action, ['favor_freelancer', 'reject_cancel'], true) && $contract['status'] === 'disputed') {
        $pdo->prepare('UPDATE contracts SET status="completed", completed_at=NOW(), resolved_by=?, resolved_at=NOW(), resolution_note=? WHERE id=?')
            ->execute([$admin['id'], $note, $contractId]);
        $pdo->prepare('UPDATE projects SET status="completed" WHERE id=?')->execute([$contract['project_id']]);
        $pdo->prepare('UPDATE wallets SET locked=locked-? WHERE user_id=?')->execute([$contract['amount'], $contract['client_id']]);
        $pdo->prepare('UPDATE wallets SET balance=balance+? WHERE user_id=?')->execute([$contract['amount'], $contract['freelancer_id']]);
        recordTransaction($contract['freelancer_id'], 'payment', $contract['amount'], $contractId, 'Paiement débloqué par admin (litige #' . $contractId . ')');
        sendNotification($contract['freelancer_id'], 'dispute_resolved', 'Litige résolu en votre faveur',
            money((float)$contract['amount']) . ' ont été crédités sur votre wallet.', '/upc_freelance/app/wallet/index.php');
        sendNotification($contract['client_id'], 'dispute_resolved', 'Litige résolu',
            'Notre équipe a tranché en faveur du freelancer pour le contrat "' . $contract['project_title'] . '".',
            '/upc_freelance/app/contracts/details.php?id=' . $contractId);
        $flashMsg = 'Litige tranché : paiement libéré au freelancer.'; $flashType = 'success';
        $contract['status'] = 'completed';
    }

    // Trancher en faveur du client (rembourser) / approuver l'annulation
    if (in_array($action, ['favor_client', 'approve_cancel'], true) && $contract['status'] === 'disputed') {
        $pdo->prepare('UPDATE contracts SET status="cancelled", resolved_by=?, resolved_at=NOW(), resolution_note=? WHERE id=?')
            ->execute([$admin['id'], $note, $contractId]);
        $pdo->prepare('UPDATE projects SET status="open" WHERE id=?')->execute([$contract['project_id']]);
        $pdo->prepare('UPDATE wallets SET balance=balance+?, locked=locked-? WHERE user_id=?')
            ->execute([$contract['amount'], $contract['amount'], $contract['client_id']]);
        recordTransaction($contract['client_id'], 'unlock', $contract['amount'], $contractId, 'Remboursement décidé par admin (litige #' . $contractId . ')');
        sendNotification($contract['client_id'], 'dispute_resolved', 'Litige résolu en votre faveur',
            money((float)$contract['amount']) . ' ont été remis dans votre wallet.', '/upc_freelance/app/wallet/index.php');
        sendNotification($contract['freelancer_id'], 'dispute_resolved', 'Litige résolu',
            'Notre équipe a tranché en faveur du client pour le contrat "' . $contract['project_title'] . '".',
            '/upc_freelance/app/contracts/details.php?id=' . $contractId);
        $flashMsg = 'Litige tranché : client remboursé.'; $flashType = 'success';
        $contract['status'] = 'cancelled';
    }

    // Marquer / démarquer comme fraude suspectée
    if ($action === 'toggle_fraud') {
        $newFlag = $contract['fraud_flag'] ? 0 : 1;
        $pdo->prepare('UPDATE contracts SET fraud_flag=?, fraud_note=? WHERE id=?')->execute([$newFlag, $note, $contractId]);
        $flashMsg = $newFlag ? 'Contrat marqué comme fraude suspectée.' : 'Signalement fraude levé.';
        $flashType = 'success';
        $contract['fraud_flag'] = $newFlag;
        $contract['fraud_note'] = $note;
    }

    // Traiter une plainte (reviewed / dismissed)
    if ($action === 'handle_report') {
        $reportId   = (int)($_POST['report_id'] ?? 0);
        $newStatus  = $_POST['report_status'] === 'dismissed' ? 'dismissed' : 'reviewed';
        $pdo->prepare('UPDATE contract_reports SET status=?, admin_note=?, reviewed_by=?, reviewed_at=NOW() WHERE id=? AND contract_id=?')
            ->execute([$newStatus, $note, $admin['id'], $reportId, $contractId]);
        $flashMsg = 'Plainte mise à jour.'; $flashType = 'success';
    }
}

// ─── Recharger les données fraîches ──────────────────────────────
$stmt->execute([$contractId]);
$contract = $stmt->fetch();

$msgStmt = $pdo->prepare('
    SELECT m.*, u.first_name, u.last_name
    FROM messages m JOIN users u ON u.id = m.sender_id
    WHERE m.contract_id = ? ORDER BY m.created_at ASC
');
$msgStmt->execute([$contractId]);
$messages = $msgStmt->fetchAll();

$fileStmt = $pdo->prepare('
    SELECT cf.*, u.first_name, u.last_name
    FROM contract_files cf JOIN users u ON u.id = cf.uploaded_by
    WHERE cf.contract_id = ? ORDER BY cf.created_at DESC
');
$fileStmt->execute([$contractId]);
$files = $fileStmt->fetchAll();

$reportStmt = $pdo->prepare('
    SELECT cr.*, u.first_name, u.last_name, u.role
    FROM contract_reports cr JOIN users u ON u.id = cr.reporter_id
    WHERE cr.contract_id = ? ORDER BY cr.created_at DESC
');
$reportStmt->execute([$contractId]);
$reports = $reportStmt->fetchAll();

$aiAnalysis = $contract['ai_analysis'] ? json_decode($contract['ai_analysis'], true) : null;

function money2($v) { return number_format((float)$v, 0, ',', ' ') . ' USD'; }
function fileIcon2($path) {
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    return match($ext) {
        'pdf' => 'picture_as_pdf', 'doc','docx' => 'description',
        'xls','xlsx' => 'table_chart', 'ppt','pptx' => 'slideshow',
        'zip','rar' => 'folder_zip', 'jpg','jpeg','png' => 'image',
        default => 'insert_drive_file',
    };
}

$statusConfig = ['active'=>['green','Actif'],'completed'=>['blue','Terminé'],'cancelled'=>['red','Annulé'],'disputed'=>['amber','Litige']];
[$sc, $sl] = $statusConfig[$contract['status']] ?? ['gray', $contract['status']];

$navItems = [
    ['dashboard.php',    'dashboard',      'Tableau de bord'],
    ['users.php',        'group',          'Utilisateurs'],
    ['projects.php',     'work',           'Projets'],
    ['transactions.php', 'account_balance','Transactions'],
    ['verification.php', 'verified_user',  'Vérifications'],
    ['disputes.php',     'gavel',          'Litiges'],
    ['reports.php',      'bar_chart',      'Rapports'],
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Litige #<?= $contractId ?> — Admin UPC Freelance</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" />
<style>
.material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; font-size: 20px; vertical-align: middle; }
body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
</style>
</head>
<body class="bg-slate-50 min-h-screen lg:flex">

<header class="lg:hidden sticky top-0 z-30 bg-white border-b border-slate-200 px-4 py-3 flex items-center justify-between">
    <div class="flex items-center gap-2">
        <div class="w-8 h-8 rounded-lg bg-[#002045] flex items-center justify-center text-white font-bold text-xs">UF</div>
        <span class="font-bold text-sm text-[#002045]">Admin</span>
    </div>
    <button onclick="toggleSidebar()" class="p-2 rounded-lg hover:bg-slate-100">
        <span class="material-symbols-outlined">menu</span>
    </button>
</header>

<div id="sidebar-overlay" onclick="toggleSidebar()" class="hidden fixed inset-0 bg-black/40 z-40 lg:hidden"></div>

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
            $active = $href === 'disputes.php'; ?>
        <a href="<?= $href ?>"
           class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-colors
                  <?= $active ? 'bg-[#002045] text-white' : 'text-slate-600 hover:bg-slate-100' ?>">
            <span class="material-symbols-outlined text-lg"><?= $icon ?></span>
            <?= $label ?>
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

        <a href="disputes.php" class="inline-flex items-center gap-1 text-sm text-[#0061a5] hover:underline mb-4">
            <span class="material-symbols-outlined text-base">arrow_back</span> Retour aux litiges
        </a>

        <?php if ($flashMsg): ?>
        <div class="mb-4 p-3 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm font-medium">
            <?= h($flashMsg) ?>
        </div>
        <?php endif; ?>

        <!-- En-tête contrat -->
        <div class="bg-white rounded-2xl border border-slate-200 p-5 sm:p-6 mb-6">
            <div class="flex flex-wrap items-start gap-3 justify-between mb-4">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2 mb-1">
                        <span class="text-xs font-bold bg-<?= $sc ?>-100 text-<?= $sc ?>-700 px-2 py-0.5 rounded-full"><?= $sl ?></span>
                        <?php if ($contract['cancel_requested_by']): ?>
                        <span class="text-xs font-bold bg-red-100 text-red-700 px-2 py-0.5 rounded-full">Demande d'annulation</span>
                        <?php endif; ?>
                        <?php if ($contract['fraud_flag']): ?>
                        <span class="text-xs font-bold bg-purple-100 text-purple-700 px-2 py-0.5 rounded-full">⚠ Fraude suspectée</span>
                        <?php endif; ?>
                    </div>
                    <h1 class="text-xl font-bold text-[#002045]"><?= h($contract['project_title']) ?></h1>
                    <p class="text-xs text-slate-400">Contrat #<?= $contractId ?></p>
                </div>
                <div class="text-right flex-shrink-0">
                    <p class="text-xs text-slate-400">Montant</p>
                    <p class="text-xl font-bold text-[#0061a5]"><?= money2($contract['amount']) ?></p>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-4 border-t border-slate-100">
                <div class="p-3 bg-slate-50 rounded-xl">
                    <p class="text-xs text-slate-400 mb-1">Client</p>
                    <p class="text-sm font-semibold text-[#002045]"><?= h($contract['client_fname'].' '.$contract['client_lname']) ?></p>
                    <p class="text-xs text-slate-500"><?= h($contract['client_email']) ?><?= $contract['client_phone'] ? ' · '.h($contract['client_phone']) : '' ?></p>
                </div>
                <div class="p-3 bg-slate-50 rounded-xl">
                    <p class="text-xs text-slate-400 mb-1">Freelancer</p>
                    <p class="text-sm font-semibold text-[#002045]"><?= h($contract['freelancer_fname'].' '.$contract['freelancer_lname']) ?></p>
                    <p class="text-xs text-slate-500"><?= h($contract['freelancer_email']) ?><?= $contract['freelancer_phone'] ? ' · '.h($contract['freelancer_phone']) : '' ?></p>
                </div>
            </div>

            <?php if ($contract['dispute_reason']): ?>
            <div class="mt-4 p-3 bg-amber-50 rounded-xl border border-amber-200">
                <p class="text-xs font-semibold text-amber-700 mb-1">Raison du litige</p>
                <p class="text-sm text-amber-800"><?= nl2br(h($contract['dispute_reason'])) ?></p>
            </div>
            <?php endif; ?>

            <?php if ($contract['cancel_reason']): ?>
            <div class="mt-3 p-3 bg-red-50 rounded-xl border border-red-200">
                <p class="text-xs font-semibold text-red-700 mb-1">Raison de la demande d'annulation</p>
                <p class="text-sm text-red-800"><?= nl2br(h($contract['cancel_reason'])) ?></p>
            </div>
            <?php endif; ?>

            <?php if ($contract['resolution_note']): ?>
            <div class="mt-3 p-3 bg-blue-50 rounded-xl border border-blue-200">
                <p class="text-xs font-semibold text-blue-700 mb-1">Note de résolution</p>
                <p class="text-sm text-blue-800"><?= nl2br(h($contract['resolution_note'])) ?></p>
            </div>
            <?php endif; ?>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            <!-- Colonne principale -->
            <div class="lg:col-span-2 space-y-6">

                <!-- Analyse IA -->
                <div class="bg-white rounded-2xl border border-slate-200 p-5">
                    <div class="flex items-center justify-between mb-3 flex-wrap gap-2">
                        <h3 class="font-semibold text-[#002045] flex items-center gap-2">
                            <span class="material-symbols-outlined text-purple-500">smart_toy</span>
                            Analyse IA de la conversation
                        </h3>
                        <button id="ai-analyze-btn" onclick="runAiAnalysis()"
                                class="bg-purple-600 text-white text-xs font-bold px-3 py-2 rounded-xl hover:bg-purple-700 transition-colors flex items-center gap-1.5">
                            <span class="material-symbols-outlined text-sm">bolt</span>
                            <span id="ai-btn-label"><?= $aiAnalysis ? 'Ré-analyser' : 'Analyser avec l\'IA' ?></span>
                        </button>
                    </div>

                    <div id="ai-result">
                        <?php if ($aiAnalysis): ?>
                        <?php
                        $riskColors = ['faible' => 'emerald', 'moyen' => 'amber', 'élevé' => 'red'];
                        $riskColor  = $riskColors[$aiAnalysis['risk_level']] ?? 'slate';
                        ?>
                        <div class="p-4 bg-<?= $riskColor ?>-50 border border-<?= $riskColor ?>-200 rounded-xl">
                            <div class="flex items-center gap-2 mb-2">
                                <span class="text-xs font-bold bg-<?= $riskColor ?>-100 text-<?= $riskColor ?>-700 px-2 py-0.5 rounded-full uppercase">
                                    Risque <?= h($aiAnalysis['risk_level']) ?>
                                </span>
                                <span class="text-xs text-slate-400">Analysé le <?= formatDate($contract['ai_analyzed_at']) ?></span>
                            </div>
                            <p class="text-sm text-slate-700 mb-2"><?= h($aiAnalysis['summary']) ?></p>
                            <?php if (!empty($aiAnalysis['red_flags'])): ?>
                            <ul class="text-xs text-slate-600 list-disc list-inside space-y-0.5 mb-2">
                                <?php foreach ($aiAnalysis['red_flags'] as $flag): ?>
                                <li><?= h($flag) ?></li>
                                <?php endforeach; ?>
                            </ul>
                            <?php endif; ?>
                            <p class="text-xs font-semibold text-slate-500">Recommandation : <span class="font-normal"><?= h($aiAnalysis['recommendation']) ?></span></p>
                        </div>
                        <?php else: ?>
                        <p class="text-sm text-slate-400">Aucune analyse effectuée pour l'instant. Cliquez sur "Analyser avec l'IA" pour examiner la conversation.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Historique des messages -->
                <div class="bg-white rounded-2xl border border-slate-200 p-5">
                    <h3 class="font-semibold text-[#002045] mb-4 flex items-center gap-2">
                        <span class="material-symbols-outlined text-slate-400">chat</span>
                        Historique des messages (<?= count($messages) ?>)
                    </h3>
                    <?php if (empty($messages)): ?>
                    <p class="text-sm text-slate-400">Aucun message échangé sur ce contrat.</p>
                    <?php else: ?>
                    <div class="space-y-3 max-h-[500px] overflow-y-auto pr-1">
                        <?php foreach ($messages as $m):
                            $isClientMsg = (int)$m['sender_id'] === (int)$contract['client_id']; ?>
                        <div class="flex <?= $isClientMsg ? 'justify-start' : 'justify-end' ?>">
                            <div class="max-w-[80%]">
                                <p class="text-[10px] text-slate-400 mb-0.5 <?= $isClientMsg ? '' : 'text-right' ?>">
                                    <?= h($m['first_name']) ?> · <?= formatDate($m['created_at']) ?>
                                </p>
                                <div class="px-3 py-2 rounded-xl text-sm <?= $isClientMsg ? 'bg-slate-100 text-slate-700' : 'bg-blue-50 text-blue-800' ?>">
                                    <?= nl2br(h($m['body'])) ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Fichiers partagés -->
                <div class="bg-white rounded-2xl border border-slate-200 p-5">
                    <h3 class="font-semibold text-[#002045] mb-4 flex items-center gap-2">
                        <span class="material-symbols-outlined text-slate-400">folder_shared</span>
                        Fichiers partagés (<?= count($files) ?>)
                    </h3>
                    <?php if (empty($files)): ?>
                    <p class="text-sm text-slate-400">Aucun fichier partagé sur ce contrat.</p>
                    <?php else: ?>
                    <div class="space-y-2">
                        <?php foreach ($files as $f): ?>
                        <div class="flex items-center gap-3 p-2.5 rounded-xl border border-slate-100">
                            <span class="material-symbols-outlined text-[#0061a5]"><?= fileIcon2($f['file_path']) ?></span>
                            <div class="flex-1 min-w-0">
                                <a href="download-file.php?id=<?= $f['id'] ?>" class="text-sm font-medium text-[#002045] hover:underline truncate block">
                                    <?= h($f['file_name']) ?>
                                </a>
                                <p class="text-[11px] text-slate-400">Par <?= h($f['first_name']) ?> · <?= formatDate($f['created_at']) ?></p>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Plaintes liées -->
                <?php if (!empty($reports)): ?>
                <div class="bg-white rounded-2xl border border-slate-200 p-5">
                    <h3 class="font-semibold text-[#002045] mb-4 flex items-center gap-2">
                        <span class="material-symbols-outlined text-slate-400">report</span>
                        Plaintes (<?= count($reports) ?>)
                    </h3>
                    <div class="space-y-3">
                        <?php foreach ($reports as $r): ?>
                        <div class="p-3 rounded-xl border border-slate-100">
                            <div class="flex items-center justify-between gap-2 mb-1">
                                <p class="text-sm font-semibold text-[#002045]">
                                    <?= h($r['first_name'].' '.$r['last_name']) ?>
                                    <span class="text-xs font-normal text-slate-400">(<?= $r['role'] === 'client' ? 'client' : 'freelancer' ?>)</span>
                                </p>
                                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full
                                    <?= $r['status'] === 'pending' ? 'bg-amber-100 text-amber-700' : ($r['status'] === 'reviewed' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500') ?>">
                                    <?= ['pending'=>'En attente','reviewed'=>'Traitée','dismissed'=>'Rejetée'][$r['status']] ?>
                                </span>
                            </div>
                            <p class="text-sm text-slate-600 mb-2"><?= nl2br(h($r['reason'])) ?></p>
                            <?php if ($r['status'] === 'pending'): ?>
                            <form method="POST" class="flex flex-wrap items-center gap-2">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="handle_report"/>
                                <input type="hidden" name="report_id" value="<?= $r['id'] ?>"/>
                                <input type="text" name="note" placeholder="Note (optionnel)"
                                       class="flex-1 min-w-[140px] px-2.5 py-1.5 rounded-lg border border-slate-200 text-xs outline-none focus:border-[#0061a5]"/>
                                <button type="submit" name="report_status" value="reviewed"
                                        class="text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200 px-3 py-1.5 rounded-lg hover:bg-emerald-100">
                                    Marquer traitée
                                </button>
                                <button type="submit" name="report_status" value="dismissed"
                                        class="text-xs font-semibold bg-slate-50 text-slate-500 border border-slate-200 px-3 py-1.5 rounded-lg hover:bg-slate-100">
                                    Rejeter
                                </button>
                            </form>
                            <?php else: ?>
                            <p class="text-xs text-slate-400"><?= h($r['admin_note'] ?? '') ?></p>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Colonne actions -->
            <div class="space-y-4">

                <?php if ($contract['status'] === 'disputed'): ?>
                <div class="bg-white rounded-2xl border border-slate-200 p-5">
                    <h3 class="font-semibold text-[#002045] mb-3 text-sm">Trancher le litige</h3>
                    <form method="POST" class="space-y-3">
                        <?= csrfField() ?>
                        <textarea name="note" rows="3" placeholder="Note de résolution (visible en interne)"
                                  class="w-full px-3 py-2.5 rounded-xl border border-slate-200 text-sm outline-none focus:border-[#0061a5]"></textarea>
                        <button type="submit" name="action" value="<?= $contract['cancel_requested_by'] ? 'reject_cancel' : 'favor_freelancer' ?>"
                                onclick="return confirm('Confirmer : payer le freelancer ?')"
                                class="w-full bg-emerald-500 text-white text-sm font-semibold py-2.5 rounded-xl hover:bg-emerald-600 transition-colors">
                            <span class="material-symbols-outlined text-base align-middle mr-1">task_alt</span>
                            <?= $contract['cancel_requested_by'] ? 'Rejeter la demande — payer le freelancer' : 'Trancher en faveur du freelancer' ?>
                        </button>
                        <button type="submit" name="action" value="<?= $contract['cancel_requested_by'] ? 'approve_cancel' : 'favor_client' ?>"
                                onclick="return confirm('Confirmer : rembourser le client ?')"
                                class="w-full bg-red-50 text-red-600 border border-red-200 text-sm font-semibold py-2.5 rounded-xl hover:bg-red-100 transition-colors">
                            <span class="material-symbols-outlined text-base align-middle mr-1">undo</span>
                            <?= $contract['cancel_requested_by'] ? "Approuver l'annulation — rembourser le client" : 'Trancher en faveur du client' ?>
                        </button>
                    </form>
                </div>
                <?php endif; ?>

                <!-- Flag fraude manuel -->
                <div class="bg-white rounded-2xl border border-slate-200 p-5">
                    <h3 class="font-semibold text-[#002045] mb-3 text-sm flex items-center gap-2">
                        <span class="material-symbols-outlined text-purple-500 text-base">shield</span>
                        Signalement fraude
                    </h3>
                    <form method="POST" class="space-y-3">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="toggle_fraud"/>
                        <textarea name="note" rows="2" placeholder="Note (raison du signalement)"
                                  class="w-full px-3 py-2.5 rounded-xl border border-slate-200 text-sm outline-none focus:border-purple-400"><?= h($contract['fraud_note'] ?? '') ?></textarea>
                        <button type="submit"
                                class="w-full <?= $contract['fraud_flag'] ? 'bg-slate-100 text-slate-600' : 'bg-purple-50 text-purple-700 border border-purple-200' ?> text-sm font-semibold py-2.5 rounded-xl hover:opacity-80 transition-opacity">
                            <?= $contract['fraud_flag'] ? 'Lever le signalement' : 'Marquer comme fraude suspectée' ?>
                        </button>
                    </form>
                </div>

                <a href="/upc_freelance/app/projects/details.php?id=<?= $contract['project_id'] ?>" target="_blank"
                   class="flex items-center gap-3 bg-white rounded-2xl border border-slate-200 p-4 hover:border-slate-300 transition-colors">
                    <span class="material-symbols-outlined text-slate-400">open_in_new</span>
                    <span class="text-sm font-medium text-[#002045]">Voir le projet associé</span>
                </a>
            </div>
        </div>
    </main>
</div>

<script>
function toggleSidebar() {
    document.getElementById('admin-sidebar').classList.toggle('-translate-x-full');
    document.getElementById('sidebar-overlay').classList.toggle('hidden');
}

async function runAiAnalysis() {
    const btn   = document.getElementById('ai-analyze-btn');
    const label = document.getElementById('ai-btn-label');
    const result = document.getElementById('ai-result');
    btn.disabled = true;
    label.textContent = 'Analyse en cours…';

    try {
        const r = await fetch('api-ai-analyze.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ contract_id: <?= $contractId ?> })
        });
        const data = await r.json();

        if (data.error) {
            result.innerHTML = `<div class="p-3 bg-red-50 border border-red-200 rounded-xl text-sm text-red-600">${data.error}</div>`;
        } else {
            location.reload();
        }
    } catch (e) {
        result.innerHTML = `<div class="p-3 bg-red-50 border border-red-200 rounded-xl text-sm text-red-600">Erreur réseau, réessayez.</div>`;
    } finally {
        btn.disabled = false;
        label.textContent = 'Ré-analyser';
    }
}
</script>
</body>
</html>