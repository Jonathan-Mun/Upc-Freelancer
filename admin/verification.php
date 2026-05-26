<?php
// ============================================================
// UPC FREELANCE — Admin : Vérifications documents
// ../../admin/verification.php
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
$pdo   = getDB();

// Action approbation / refus
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $docId  = (int)($_POST['doc_id'] ?? 0);
    $action = sanitize($_POST['action'] ?? '');
    $note   = sanitize($_POST['admin_note'] ?? '');

    if ($docId && in_array($action, ['approved','rejected'])) {
        $pdo->prepare('UPDATE verification_docs SET status = ?, admin_note = ?, reviewed_at = NOW() WHERE id = ?')
            ->execute([$action, $note, $docId]);

        $stmt = $pdo->prepare('SELECT user_id FROM verification_docs WHERE id = ?');
        $stmt->execute([$docId]);
        $userId = (int)$stmt->fetchColumn();

        if ($userId) {
            $msg = $action === 'approved'
                ? 'Votre document a été vérifié. Votre compte est maintenant certifié !'
                : 'Votre document a été refusé. ' . ($note ? 'Raison : ' . $note : 'Veuillez soumettre un nouveau document.');

            $pdo->prepare('INSERT INTO notifications (user_id, type, title, body) VALUES (?, ?, ?, ?)')
                ->execute([$userId, 'verification_' . $action,
                           'Document ' . ($action === 'approved' ? 'approuvé' : 'refusé'), $msg]);

            if ($action === 'approved') {
                $pdo->prepare('UPDATE users SET is_verified = 1 WHERE id = ?')->execute([$userId]);
            }
        }
    }
    header('Location: /upc_freelance/admin/verification.php'); exit;
}

$status = sanitize($_GET['status'] ?? 'pending');

// CORRECTION : u.university n'existe pas dans users → on joint freelancer_profiles
$stmt = $pdo->prepare('
    SELECT vd.*, u.first_name, u.last_name, u.email, u.role,
           fp.university, fp.field_of_study
    FROM verification_docs vd
    JOIN users u ON u.id = vd.user_id
    LEFT JOIN freelancer_profiles fp ON fp.user_id = u.id
    WHERE vd.status = ?
    ORDER BY vd.created_at DESC
');
$stmt->execute([$status]);
$docs = $stmt->fetchAll();

$counts = [];
foreach (['pending','approved','rejected'] as $s) {
    $cs = $pdo->prepare('SELECT COUNT(*) FROM verification_docs WHERE status = ?');
    $cs->execute([$s]);
    $counts[$s] = (int)$cs->fetchColumn();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Vérifications — Admin UPC Freelance</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
<style>
* { font-family: 'Inter', sans-serif; }
.material-symbols-outlined { vertical-align: middle; }
.nav-active { background:#eff4ff; color:#0061a5; border-right:3px solid #0061a5; font-weight:600; }
</style>
</head>
<body class="bg-slate-50 min-h-screen flex">

<!-- Sidebar -->
<aside class="w-60 bg-white border-r border-slate-200 flex flex-col sticky top-0 h-screen">
    <div class="p-5 border-b border-slate-100">
        <div class="flex items-center gap-2.5">
            <svg width="32" height="32" viewBox="0 0 38 38" fill="none">
                <rect width="38" height="38" rx="10" fill="#002045"/>
                <path d="M10 12 L10 22 Q10 28 19 28 Q28 28 28 22 L28 12" stroke="#fff" stroke-width="2.5" stroke-linecap="round" fill="none"/>
                <circle cx="19" cy="12" r="1.5" fill="#66affe"/>
                <path d="M14 18 L24 18" stroke="#66affe" stroke-width="2" stroke-linecap="round"/>
            </svg>
            <div>
                <p class="font-bold text-sm">UPC Freelance</p>
                <p class="text-xs text-red-500 font-semibold">Administration</p>
            </div>
        </div>
    </div>
    <nav class="flex-1 p-3 space-y-0.5">
        <?php
        $navAdmin = [
            'dashboard.php'     => ['dashboard',      'Tableau de bord'],
            'users.php'         => ['people',          'Utilisateurs'],
            'projects.php'      => ['work',            'Projets'],
            'transactions.php'  => ['receipt_long',    'Transactions'],
            'verification.php'  => ['verified_user',   'Vérifications'],
            'reports.php'       => ['bar_chart',       'Rapports'],
        ];
        foreach ($navAdmin as $href => [$icon, $label]):
            $a = basename($_SERVER['PHP_SELF']) === $href;
        ?>
        <a href="/upc_freelance/admin/<?= $href ?>"
           class="flex items-center gap-2.5 px-3 py-2.5 rounded-lg text-sm <?= $a ? 'nav-active' : 'text-slate-600 hover:bg-slate-50' ?>">
            <span class="material-symbols-outlined text-lg <?= $a ? 'text-blue-600' : 'text-slate-400' ?>"><?= $icon ?></span>
            <?= $label ?>
            <?php if ($href === 'verification.php' && ($counts['pending'] ?? 0) > 0): ?>
            <span class="ml-auto bg-amber-500 text-white text-xs px-1.5 py-0.5 rounded-full"><?= $counts['pending'] ?></span>
            <?php endif; ?>
        </a>
        <?php endforeach; ?>
    </nav>
    <div class="p-4 border-t border-slate-100">
        <p class="text-xs text-slate-400 px-3 mb-2"><?= h($admin['name']) ?></p>
        <a href="/upc_freelance/admin/logout.php"
           class="flex items-center gap-2 px-3 py-2 rounded-lg text-red-500 hover:bg-red-50 text-xs">
            <span class="material-symbols-outlined text-base">logout</span> Déconnexion
        </a>
    </div>
</aside>

<!-- Contenu -->
<div class="flex-1 flex flex-col min-w-0">
    <header class="bg-white border-b border-slate-200 px-8 py-4">
        <h1 class="text-xl font-bold">Vérifications de documents</h1>
    </header>
    <main class="flex-1 p-8">

        <!-- Onglets -->
        <div class="flex gap-3 mb-6">
            <?php foreach (['pending'=>['En attente','amber'],'approved'=>['Approuvés','green'],'rejected'=>['Refusés','red']] as $s => [$l, $c]): ?>
            <a href="?status=<?= $s ?>"
               class="flex items-center gap-2 px-4 py-2 rounded-full text-sm font-medium transition-all
                      <?= $status === $s ? 'bg-'.$c.'-500 text-white shadow-sm' : 'bg-white border border-slate-200 text-slate-600 hover:border-'.$c.'-300' ?>">
                <?= $l ?>
                <span class="text-xs px-1.5 py-0.5 rounded-full font-bold
                             <?= $status === $s ? 'bg-white/30 text-white' : 'bg-'.$c.'-100 text-'.$c.'-700' ?>">
                    <?= $counts[$s] ?? 0 ?>
                </span>
            </a>
            <?php endforeach; ?>
        </div>

        <?php if (empty($docs)): ?>
        <div class="bg-white rounded-2xl border border-slate-100 p-16 text-center">
            <span class="material-symbols-outlined text-5xl text-slate-300 block mb-4">verified_user</span>
            <p class="text-slate-500">
                Aucun document <?= $status === 'pending' ? 'en attente' : ($status === 'approved' ? 'approuvé' : 'refusé') ?>
            </p>
        </div>
        <?php else: ?>
        <div class="grid grid-cols-1 gap-4">
            <?php foreach ($docs as $doc): ?>
            <div class="bg-white rounded-2xl border border-slate-100 p-6">
                <div class="flex items-start gap-4">
                    <div class="w-12 h-12 rounded-xl bg-slate-100 flex items-center justify-center text-xl font-bold text-slate-600 flex-shrink-0">
                        <?= mb_strtoupper(mb_substr($doc['first_name'], 0, 1)) ?>
                    </div>
                    <div class="flex-1">
                        <div class="flex items-start justify-between gap-4 mb-2">
                            <div>
                                <p class="font-bold text-slate-900"><?= h($doc['first_name'] . ' ' . $doc['last_name']) ?></p>
                                <!-- CORRECTION : university vient maintenant de fp (freelancer_profiles) -->
                                <p class="text-xs text-slate-400">
                                    <?= h($doc['email']) ?> · <?= $doc['role'] ?>
                                    <?= $doc['university'] ? ' · ' . h($doc['university']) : '' ?>
                                    <?= $doc['field_of_study'] ? ' (' . h($doc['field_of_study']) . ')' : '' ?>
                                </p>
                            </div>
                            <div class="text-right">
                                <span class="text-xs bg-slate-100 text-slate-600 px-2 py-1 rounded-full">
                                    <?= h($doc['doc_type']) ?>
                                </span>
                                <p class="text-xs text-slate-400 mt-1"><?= formatDate($doc['created_at']) ?></p>
                            </div>
                        </div>

                        <!-- Aperçu document -->
                        <div class="flex items-center gap-3 p-3 bg-slate-50 rounded-xl mb-4">
                            <span class="material-symbols-outlined text-slate-500">description</span>
                            <p class="text-sm text-slate-700 flex-1 truncate"><?= h(basename($doc['file_path'])) ?></p>
                            <a href="/upc_freelance/app/verification/serve.php?id=<?= $doc['id'] ?>" target="_blank"
                               class="text-xs text-blue-600 hover:underline flex items-center gap-1 whitespace-nowrap">
                                <span class="material-symbols-outlined text-base">open_in_new</span> Voir le document
                            </a>
                        </div>

                        <?php if ($doc['status'] === 'pending'): ?>
                        <form method="POST" class="space-y-3">
                            <input type="hidden" name="doc_id" value="<?= $doc['id'] ?>"/>
                            <div>
                                <label class="block text-xs font-medium text-slate-600 mb-1">Note administrative (optionnel)</label>
                                <input type="text" name="admin_note"
                                       placeholder="Raison du refus ou commentaire..."
                                       class="w-full px-3 py-2 rounded-lg border border-slate-200 text-sm outline-none focus:border-blue-400"/>
                            </div>
                            <div class="flex gap-3">
                                <button type="submit" name="action" value="approved"
                                        class="flex-1 bg-green-500 text-white text-sm py-2.5 rounded-xl hover:bg-green-600 transition-colors active:scale-95 flex items-center justify-center gap-1.5">
                                    <span class="material-symbols-outlined text-base">check_circle</span> Approuver
                                </button>
                                <button type="submit" name="action" value="rejected"
                                        class="flex-1 bg-red-50 text-red-600 text-sm py-2.5 rounded-xl hover:bg-red-100 transition-colors active:scale-95 flex items-center justify-center gap-1.5"
                                        onclick="return confirm('Confirmer le refus ?')">
                                    <span class="material-symbols-outlined text-base">cancel</span> Refuser
                                </button>
                            </div>
                        </form>
                        <?php else: ?>
                        <div class="flex items-center gap-2">
                            <span class="material-symbols-outlined <?= $doc['status'] === 'approved' ? 'text-green-500' : 'text-red-400' ?>">
                                <?= $doc['status'] === 'approved' ? 'check_circle' : 'cancel' ?>
                            </span>
                            <p class="text-sm <?= $doc['status'] === 'approved' ? 'text-green-700' : 'text-red-600' ?> font-medium">
                                <?= $doc['status'] === 'approved' ? 'Document approuvé' : 'Document refusé' ?>
                                <?= $doc['admin_note'] ? ' — ' . h($doc['admin_note']) : '' ?>
                            </p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

    </main>
</div>
</body>
</html>