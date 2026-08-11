<?php
// ============================================================
// UPC FREELANCE — Détails du contrat + chat
// ../../app/contracts/details.php
// ============================================================

require_once '../../includes/middleware.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../includes/db.php';

requireLogin();

$user       = currentUser();
$pdo        = getDB();
$contractId = (int)($_GET['id'] ?? 0);
if (!$contractId) redirect('../../app/contracts/list.php');

$stmt = $pdo->prepare('
    SELECT c.*,
           p.title AS project_title, p.id AS project_id,
           cl.first_name AS client_fname, cl.last_name AS client_lname,
           cl.avatar AS client_avatar, cl.is_verified AS client_verified,
           fr.first_name AS freelancer_fname, fr.last_name AS freelancer_lname,
           fr.avatar AS freelancer_avatar, fr.is_verified AS freelancer_verified
    FROM contracts c
    JOIN projects p ON p.id = c.project_id
    JOIN users cl   ON cl.id = c.client_id
    JOIN users fr   ON fr.id = c.freelancer_id
    WHERE c.id = ? AND (c.client_id = ? OR c.freelancer_id = ?)
');
$stmt->execute([$contractId, $user['id'], $user['id']]);
$contract = $stmt->fetch();
if (!$contract) { http_response_code(403); die('Accès refusé.'); }

$isClient     = $user['id'] === $contract['client_id'];
$isFreelancer = $user['id'] === $contract['freelancer_id'];

// ─── Envoyer message ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    verifyCsrf();
    $body = sanitize($_POST['message_body'] ?? '');
    if (!empty($body)) {
        $pdo->prepare('INSERT INTO messages (contract_id, sender_id, body) VALUES (?, ?, ?)')
            ->execute([$contractId, $user['id'], $body]);
        $receiverId = $isClient ? $contract['freelancer_id'] : $contract['client_id'];
        sendNotification($receiverId, 'new_message', 'Nouveau message',
            $user['first_name'] . ' vous a envoyé un message.',
            '/upc_freelance/app/contracts/details.php?id=' . $contractId);
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            header('Content-Type: application/json');
            echo json_encode(['ok' => true]);
            exit;
        }
    }
    redirect('../../app/contracts/details.php?id=' . $contractId);
}

// ─── Upload fichier partagé ───────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_file'])) {
    verifyCsrf();
    if (!empty($_FILES['contract_file']['name'])) {
        $allowedExt = ['pdf','doc','docx','xls','xlsx','zip','rar','jpg','jpeg','png','ppt','pptx'];
        $uploaded = uploadFile($_FILES['contract_file'], 'contract_files', $allowedExt, 10);
        if ($uploaded) {
            $pdo->prepare('
                INSERT INTO contract_files (contract_id, uploaded_by, file_name, file_path, file_size)
                VALUES (?, ?, ?, ?, ?)
            ')->execute([
                $contractId, $user['id'],
                $_FILES['contract_file']['name'],
                $uploaded,
                $_FILES['contract_file']['size'],
            ]);
            $receiverId = $isClient ? $contract['freelancer_id'] : $contract['client_id'];
            sendNotification($receiverId, 'new_file', 'Nouveau fichier partagé',
                $user['first_name'] . ' a partagé un fichier : ' . $_FILES['contract_file']['name'],
                '/upc_freelance/app/contracts/details.php?id=' . $contractId);
            flash('success', 'Fichier envoyé avec succès.');
        } else {
            flash('error', 'Fichier invalide (max 10 Mo — formats acceptés : pdf, doc, docx, xls, xlsx, zip, rar, jpg, png, ppt, pptx).');
        }
    }
    redirect('../../app/contracts/details.php?id=' . $contractId);
}

// ─── Supprimer un fichier partagé (seul l'auteur peut) ────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_file'])) {
    verifyCsrf();
    $fileId = (int)$_POST['delete_file'];
    $stmt = $pdo->prepare('SELECT * FROM contract_files WHERE id=? AND contract_id=? AND uploaded_by=?');
    $stmt->execute([$fileId, $contractId, $user['id']]);
    if ($f = $stmt->fetch()) {
        $fullPath = '../../storage/' . $f['file_path'];
        if (file_exists($fullPath)) unlink($fullPath);
        $pdo->prepare('DELETE FROM contract_files WHERE id=?')->execute([$fileId]);
        flash('success', 'Fichier supprimé.');
    }
    redirect('../../app/contracts/details.php?id=' . $contractId);
}

// ─── Valider + review ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['complete_with_review'])) {
    verifyCsrf();
    if ($isClient && $contract['status'] === 'active') {
        $rating  = max(1, min(5, (int)($_POST['rating'] ?? 5)));
        $comment = sanitize($_POST['comment'] ?? '');

        $pdo->prepare('UPDATE contracts SET status="completed", completed_at=NOW() WHERE id=?')->execute([$contractId]);
        $pdo->prepare('UPDATE projects SET status="completed" WHERE id=?')->execute([$contract['project_id']]);
        $pdo->prepare('UPDATE wallets SET locked=locked-? WHERE user_id=?')->execute([$contract['amount'], $contract['client_id']]);
        $pdo->prepare('UPDATE wallets SET balance=balance+? WHERE user_id=?')->execute([$contract['amount'], $contract['freelancer_id']]);
        recordTransaction($contract['freelancer_id'], 'payment', $contract['amount'], $contractId, 'Paiement reçu pour contrat #'.$contractId);

        $rv = $pdo->prepare('SELECT id FROM reviews WHERE contract_id=? AND reviewer_id=?');
        $rv->execute([$contractId, $user['id']]);
        if (!$rv->fetch()) {
            $pdo->prepare('INSERT INTO reviews (contract_id,reviewer_id,reviewed_id,rating,comment) VALUES (?,?,?,?,?)')->execute([$contractId,$user['id'],$contract['freelancer_id'],$rating,$comment?:null]);
            $avg = $pdo->prepare('SELECT AVG(rating) AS a, COUNT(*) AS c FROM reviews WHERE reviewed_id=?');
            $avg->execute([$contract['freelancer_id']]);
            $d = $avg->fetch();
            $pdo->prepare('UPDATE freelancer_profiles SET rating=?,total_reviews=?,total_earned=total_earned+? WHERE user_id=?')->execute([round($d['a'],2),$d['c'],$contract['amount'],$contract['freelancer_id']]);
        }
        sendNotification($contract['freelancer_id'],'payment_received','Paiement reçu !',money((float)$contract['amount']).' crédités sur votre wallet.','/upc_freelance/app/wallet/index.php');
        flash('success','Contrat terminé ! Paiement transféré.');
        redirect('../../app/contracts/details.php?id='.$contractId);
    }
}

// ─── Marquer messages comme lus ──────────────────────────────
$pdo->prepare('UPDATE messages SET is_read=1 WHERE contract_id=? AND sender_id!=?')->execute([$contractId, $user['id']]);

// ─── Charger messages ─────────────────────────────────────────
$stmtMsgs = $pdo->prepare('
    SELECT m.id, m.body, m.sender_id, m.created_at,
           u.first_name, u.last_name, u.avatar
    FROM messages m JOIN users u ON u.id=m.sender_id
    WHERE m.contract_id=?
    ORDER BY m.created_at ASC
');
$stmtMsgs->execute([$contractId]);
$messages   = $stmtMsgs->fetchAll();
$lastMsgId  = !empty($messages) ? (int)end($messages)['id'] : 0;

// ─── Fichiers partagés ─────────────────────────────────────────
$stmtFiles = $pdo->prepare('
    SELECT cf.*, u.first_name, u.last_name
    FROM contract_files cf JOIN users u ON u.id = cf.uploaded_by
    WHERE cf.contract_id = ?
    ORDER BY cf.created_at DESC
');
$stmtFiles->execute([$contractId]);
$contractFiles = $stmtFiles->fetchAll();

function contractFileIcon(string $path): string {
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    return match($ext) {
        'pdf'                 => 'picture_as_pdf',
        'doc','docx'          => 'description',
        'xls','xlsx'          => 'table_chart',
        'ppt','pptx'          => 'slideshow',
        'zip','rar'           => 'folder_zip',
        'jpg','jpeg','png'    => 'image',
        default               => 'insert_drive_file',
    };
}
function contractFileSize(int $bytes): string {
    if ($bytes >= 1048576) return round($bytes / 1048576, 1) . ' Mo';
    if ($bytes >= 1024)    return round($bytes / 1024, 1) . ' Ko';
    return $bytes . ' o';
}

$hasReviewed = false;
if ($isClient) {
    $rv = $pdo->prepare('SELECT id FROM reviews WHERE contract_id=? AND reviewer_id=?');
    $rv->execute([$contractId,$user['id']]);
    $hasReviewed = (bool)$rv->fetch();
}

$statusConfig = ['active'=>['green','Actif'],'completed'=>['blue','Terminé'],'cancelled'=>['red','Annulé'],'disputed'=>['amber','Litige']];
[$sc,$sl] = $statusConfig[$contract['status']] ?? ['gray',$contract['status']];

$partner      = $isClient
    ? ['name'=>h($contract['freelancer_fname'].' '.$contract['freelancer_lname']),'avatar'=>$contract['freelancer_avatar'],'verified'=>$contract['freelancer_verified'],'role'=>'Freelancer']
    : ['name'=>h($contract['client_fname'].' '.$contract['client_lname']),        'avatar'=>$contract['client_avatar'],    'verified'=>$contract['client_verified'],    'role'=>'Client'];

$pageTitle = 'Contrat — ' . h($contract['project_title']);
$appLayout = true;
require_once '../../includes/header.php';
?>

<?php renderFlash(); ?>

<!-- ── STYLES chat fixe ──────────────────────────────────────── -->
<style>
/* Zone chat : hauteur fixe, seule la liste de messages scroll */
.chat-wrapper {
    display: flex;
    flex-direction: column;
    height: calc(100vh - 200px);
    min-height: 480px;
    max-height: 760px;
}
.chat-messages {
    flex: 1;
    overflow-y: auto;
    overflow-x: hidden;
    overscroll-behavior: contain;
    scroll-behavior: smooth;
}
.chat-messages::-webkit-scrollbar { width: 4px; }
.chat-messages::-webkit-scrollbar-track { background: transparent; }
.chat-messages::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 4px; }
.chat-footer {
    flex-shrink: 0;
    border-top: 1px solid #f1f5f9;
    background: white;
}

/* Mobile : chat plein écran, sidebar en popup */
@media (max-width: 1023px) {
    .chat-wrapper {
        height: calc(100dvh - 180px);
        max-height: none;
    }
    /* Cache la sidebar sur mobile */
    .contract-sidebar { display: none; }
}
</style>
<br>
<!-- ── En-tête compact ──────────────────────────────────────── -->
<div class="flex items-center gap-3 mb-4">
    <a href="/upc_freelance/app/contracts/list.php"
       class="p-2 rounded-xl border border-slate-200 hover:bg-slate-50 transition-colors text-slate-500 flex-shrink-0">
        <span class="material-symbols-outlined text-lg">arrow_back</span>
    </a>
    <div class="flex-1 min-w-0">
        <p class="text-xs text-slate-400 font-label-caps uppercase tracking-wide">Contrat</p>
        <h1 class="font-semibold text-primary truncate text-sm sm:text-base">
            <?= h($contract['project_title']) ?>
        </h1>
    </div>
    <!-- Badge statut -->
    <span class="flex-shrink-0 text-xs bg-<?= $sc ?>-100 text-<?= $sc ?>-700 px-3 py-1.5 rounded-full font-semibold">
        <?= $sl ?>
    </span>
    <!-- Bouton infos (mobile uniquement) -->
    <button onclick="openContractInfo()"
            class="lg:hidden flex-shrink-0 p-2 rounded-xl border border-slate-200 hover:bg-slate-50 transition-colors text-slate-500"
            title="Infos du contrat">
        <span class="material-symbols-outlined text-lg">info</span>
    </button>
</div>

<!-- ── Grille principale ─────────────────────────────────────── -->
<div class="flex gap-6 items-start">

    <!-- ══ CHAT ══════════════════════════════════════════════ -->
    <div class="flex-1 min-w-0 bg-white rounded-2xl border border-slate-200
                shadow-[0px_4px_12px_rgba(26,54,93,0.05)] overflow-hidden chat-wrapper">

        <!-- Header chat -->
        <div class="flex items-center gap-3 px-4 py-3 border-b border-slate-100 flex-shrink-0 bg-white">
            <!-- Avatar partenaire -->
            <div class="relative flex-shrink-0">
                <?php if ($partner['avatar']): ?>
                <img src="/upc_freelance/storage/<?= h($partner['avatar']) ?>" alt="Avatar"
                     class="w-9 h-9 rounded-full object-cover"/>
                <?php else: ?>
                <div class="w-9 h-9 rounded-full bg-primary/10 flex items-center justify-center text-sm font-bold text-primary">
                    <?= mb_strtoupper(mb_substr(str_replace('&', '', $partner['name']), 0, 1)) ?>
                </div>
                <?php endif; ?>
                <!-- Pastille verte = actif -->
                <?php if ($contract['status'] === 'active'): ?>
                <span class="absolute bottom-0 right-0 w-2.5 h-2.5 bg-emerald-400 rounded-full border-2 border-white"></span>
                <?php endif; ?>
            </div>
            <div class="flex-1 min-w-0">
                <p class="font-semibold text-primary text-sm truncate">
                    <?= $partner['name'] ?>
                    <?php if ($partner['verified']): ?>
                    <span class="material-symbols-outlined text-secondary align-middle"
                          style="font-size:13px;font-variation-settings:'FILL' 1">verified</span>
                    <?php endif; ?>
                </p>
                <p class="text-xs text-slate-400">
                    <?= $partner['role'] ?>
                    · <?= count($messages) ?> message<?= count($messages) > 1 ? 's' : '' ?>
                </p>
            </div>
            <!-- Montant -->
            <div class="flex-shrink-0 text-right">
                <p class="text-xs text-slate-400">Montant</p>
                <p class="font-bold text-secondary text-sm"><?= money((float)$contract['amount']) ?></p>
            </div>
        </div>

        <!-- ── Zone messages scrollable UNIQUEMENT ── -->
        <div class="chat-messages px-4 py-4 space-y-3" id="messages-container">
            <?php if (empty($messages)): ?>
            <div class="flex flex-col items-center justify-center h-full py-16 text-center"
                 id="empty-chat">
                <div class="w-16 h-16 bg-surface-container rounded-2xl flex items-center justify-center mb-4">
                    <span class="material-symbols-outlined text-3xl text-slate-300">chat</span>
                </div>
                <p class="text-sm font-medium text-primary">Démarrez la conversation</p>
                <p class="text-xs text-slate-400 mt-1">Envoyez votre premier message.</p>
            </div>
            <?php endif; ?>

            <?php foreach ($messages as $msg):
                $isMe = $msg['sender_id'] === $user['id'];
            ?>
            <div class="flex <?= $isMe ? 'justify-end' : 'justify-start' ?> gap-2 items-end"
                 data-msg-id="<?= $msg['id'] ?>">
                <!-- Avatar interlocuteur -->
                <?php if (!$isMe): ?>
                <div class="w-7 h-7 rounded-full bg-primary/10 flex items-center justify-center text-xs font-bold text-primary flex-shrink-0">
                    <?= mb_strtoupper(mb_substr($msg['first_name'], 0, 1)) ?>
                </div>
                <?php endif; ?>

                <div class="max-w-[72%] sm:max-w-[65%]">
                    <?php if (!$isMe): ?>
                    <p class="text-[10px] text-slate-400 mb-1 ml-2"><?= h($msg['first_name']) ?></p>
                    <?php endif; ?>
                    <!-- Bulle -->
                    <div class="px-3.5 py-2.5 rounded-2xl <?= $isMe
                        ? 'bg-primary text-white rounded-br-sm'
                        : 'bg-surface-container-low text-on-surface rounded-bl-sm' ?>">
                        <p class="text-sm leading-relaxed break-words"><?= nl2br(h($msg['body'])) ?></p>
                    </div>
                    <p class="text-[10px] text-slate-400 mt-1 <?= $isMe ? 'text-right mr-1' : 'ml-2' ?>">
                        <?= timeAgo($msg['created_at']) ?>
                        <?php if ($isMe): ?>
                        <span class="material-symbols-outlined text-[11px] align-middle text-secondary ml-0.5"
                              style="font-variation-settings:'FILL' 1">done_all</span>
                        <?php endif; ?>
                    </p>
                </div>

                <!-- Avatar moi -->
                <?php if ($isMe): ?>
                <div class="w-7 h-7 rounded-full bg-secondary/10 flex items-center justify-center text-xs font-bold text-secondary flex-shrink-0">
                    <?= mb_strtoupper(mb_substr($user['first_name'], 0, 1)) ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- ── Footer fixe : formulaire envoi ── -->
        <div class="chat-footer px-3 py-3">
            <?php if ($contract['status'] === 'active'): ?>
            <form id="chat-form" action="/upc_freelance/app/contracts/details.php?id=<?= $contractId ?>" method="POST" class="flex items-center gap-2">
                <?= csrfField() ?>
                <input type="hidden" name="send_message" value="1"/>
                <input type="text" name="message_body" id="message-input"
                       placeholder="Écrivez un message…"
                       required autocomplete="off"
                       class="flex-1 px-4 py-2.5 rounded-full border border-outline-variant
                              focus:border-secondary focus:ring-2 focus:ring-secondary/20
                              outline-none text-sm transition-all bg-surface-container-low"/>
                <button type="submit" id="send-btn"
                        class="w-10 h-10 bg-primary text-white rounded-full flex items-center justify-center
                               hover:opacity-90 transition-opacity active:scale-95 flex-shrink-0">
                    <span class="material-symbols-outlined text-lg"
                          style="font-variation-settings:'FILL' 1">send</span>
                </button>
            </form>
            <?php else: ?>
            <div class="flex items-center justify-center gap-2 py-1 text-xs text-slate-400">
                <span class="material-symbols-outlined text-sm">lock</span>
                Chat en lecture seule — contrat <?= $sl ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ══ SIDEBAR (desktop uniquement) ══════════════════════ -->
    <aside class="contract-sidebar w-72 flex-shrink-0 space-y-4 lg:block">

        <!-- Détails contrat -->
        <div class="bg-white rounded-2xl border border-slate-200 p-5
                    shadow-[0px_4px_12px_rgba(26,54,93,0.05)]">
            <h3 class="font-semibold text-primary mb-4 text-sm">Détails du contrat</h3>
            <div class="space-y-3 text-sm">
                <div class="flex justify-between items-center">
                    <span class="text-on-surface-variant">Montant</span>
                    <span class="font-bold text-secondary"><?= money((float)$contract['amount']) ?></span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-on-surface-variant">Début</span>
                    <span class="font-medium text-primary"><?= formatDate($contract['start_date'] ?? $contract['created_at']) ?></span>
                </div>
                <?php if ($contract['end_date']): ?>
                <div class="flex justify-between items-center">
                    <span class="text-on-surface-variant">Fin prévue</span>
                    <span class="font-medium text-primary"><?= formatDate($contract['end_date']) ?></span>
                </div>
                <?php endif; ?>
                <?php if ($contract['completed_at']): ?>
                <div class="flex justify-between items-center">
                    <span class="text-on-surface-variant">Terminé le</span>
                    <span class="font-medium text-emerald-600"><?= formatDate($contract['completed_at']) ?></span>
                </div>
                <?php endif; ?>
            </div>

            <?php if ($isClient && $contract['status'] === 'active'): ?>
            <div class="mt-4 pt-4 border-t border-slate-100">
                <p class="text-xs text-on-surface-variant mb-3 leading-relaxed">
                    Validez le travail pour libérer
                    <strong class="text-primary"><?= money((float)$contract['amount']) ?></strong>.
                </p>
                <button onclick="openReviewPopup()"
                        class="w-full bg-emerald-500 text-white text-sm font-semibold py-2.5 rounded-xl
                               hover:bg-emerald-600 transition-colors active:scale-95
                               flex items-center justify-center gap-2">
                    <span class="material-symbols-outlined text-base" style="font-variation-settings:'FILL' 1">task_alt</span>
                    Valider & libérer
                </button>
            </div>
            <?php endif; ?>

            <?php if ($contract['status'] === 'completed'): ?>
            <div class="mt-4 p-3 bg-emerald-50 rounded-xl border border-emerald-200 flex items-center gap-2">
                <span class="material-symbols-outlined text-emerald-500 text-base"
                      style="font-variation-settings:'FILL' 1">check_circle</span>
                <p class="text-xs text-emerald-700 font-medium">Paiement libéré</p>
            </div>
            <?php endif; ?>
        </div>

        <!-- Fichiers partagés -->
        <div class="bg-white rounded-2xl border border-slate-200 p-5
                    shadow-[0px_4px_12px_rgba(26,54,93,0.05)]">
            <h3 class="font-semibold text-primary mb-4 text-sm flex items-center gap-2">
                <span class="material-symbols-outlined text-base text-secondary">folder_shared</span>
                Fichiers partagés
            </h3>

            <?php if (empty($contractFiles)): ?>
            <p class="text-xs text-slate-400 mb-3">Aucun fichier partagé pour l'instant.</p>
            <?php else: ?>
            <div class="space-y-2 mb-4 max-h-64 overflow-y-auto">
                <?php foreach ($contractFiles as $f): ?>
                <div class="flex items-center gap-2 p-2 rounded-xl border border-slate-100 hover:bg-slate-50 transition-colors">
                    <span class="material-symbols-outlined text-secondary text-lg flex-shrink-0">
                        <?= contractFileIcon($f['file_path']) ?>
                    </span>
                    <div class="flex-1 min-w-0">
                        <a href="/upc_freelance/app/contracts/download-file.php?id=<?= $f['id'] ?>"
                           class="text-sm font-medium text-primary hover:underline truncate block">
                            <?= h($f['file_name']) ?>
                        </a>
                        <p class="text-[11px] text-slate-400">
                            <?= contractFileSize((int)$f['file_size']) ?> · <?= h($f['first_name']) ?> · <?= timeAgo($f['created_at']) ?>
                        </p>
                    </div>
                    <?php if ($f['uploaded_by'] === $user['id']): ?>
                    <form method="POST" onsubmit="return confirm('Supprimer ce fichier ?');">
                        <?= csrfField() ?>
                        <input type="hidden" name="delete_file" value="<?= $f['id'] ?>"/>
                        <button type="submit" class="text-slate-300 hover:text-red-500 transition-colors flex-shrink-0">
                            <span class="material-symbols-outlined text-base">delete</span>
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <?php if ($contract['status'] === 'active'): ?>
            <form method="POST" enctype="multipart/form-data" class="pt-3 border-t border-slate-100">
                <?= csrfField() ?>
                <input type="hidden" name="upload_file" value="1"/>
                <label class="flex items-center justify-center gap-2 py-2.5 rounded-xl border-2 border-dashed border-slate-200
                               text-xs font-semibold text-slate-500 hover:border-secondary hover:text-secondary cursor-pointer transition-colors">
                    <span class="material-symbols-outlined text-base">upload_file</span>
                    Partager un fichier
                    <input type="file" name="contract_file" class="hidden" onchange="this.form.submit()"/>
                </label>
                <p class="text-[10px] text-slate-400 mt-1.5 text-center">Max 10 Mo</p>
            </form>
            <?php endif; ?>
        </div>

        <!-- Participants -->
        <div class="bg-white rounded-2xl border border-slate-200 p-5
                    shadow-[0px_4px_12px_rgba(26,54,93,0.05)]">
            <h3 class="font-semibold text-primary mb-4 text-sm">Participants</h3>
            <?php foreach ([
                ['fname'=>$contract['client_fname'],     'lname'=>$contract['client_lname'],     'avatar'=>$contract['client_avatar'],     'verified'=>$contract['client_verified'],     'role'=>'Client',     'isMe'=>$isClient],
                ['fname'=>$contract['freelancer_fname'], 'lname'=>$contract['freelancer_lname'], 'avatar'=>$contract['freelancer_avatar'], 'verified'=>$contract['freelancer_verified'], 'role'=>'Freelancer', 'isMe'=>$isFreelancer],
            ] as $i=>$p): ?>
            <div class="flex items-center gap-3 <?= $i===1 ? 'pt-3 mt-3 border-t border-slate-100' : '' ?>">
                <div class="relative flex-shrink-0">
                    <?php if ($p['avatar']): ?>
                    <img src="/upc_freelance/storage/<?= h($p['avatar']) ?>" class="w-9 h-9 rounded-full object-cover"/>
                    <?php else: ?>
                    <div class="w-9 h-9 rounded-full bg-primary/10 flex items-center justify-center text-xs font-bold text-primary">
                        <?= mb_strtoupper(mb_substr($p['fname'], 0, 1)) ?>
                    </div>
                    <?php endif; ?>
                    <?php if ($p['verified']): ?>
                    <span class="absolute -bottom-0.5 -right-0.5 w-4 h-4 bg-secondary rounded-full border-2 border-white flex items-center justify-center">
                        <span class="material-symbols-outlined text-white" style="font-size:9px;font-variation-settings:'FILL' 1">verified</span>
                    </span>
                    <?php endif; ?>
                </div>
                <div>
                    <p class="text-sm font-semibold text-primary">
                        <?= h($p['fname'].' '.$p['lname']) ?>
                        <?= $p['isMe'] ? '<span class="text-xs font-normal text-slate-400">(vous)</span>' : '' ?>
                    </p>
                    <p class="text-xs text-slate-400"><?= $p['role'] ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Projet lié -->
        <a href="/upc_freelance/app/projects/details.php?id=<?= $contract['project_id'] ?>"
           class="flex items-center gap-3 bg-white rounded-2xl border border-slate-200 p-4
                  hover:border-secondary/40 hover:shadow-md transition-all
                  shadow-[0px_4px_12px_rgba(26,54,93,0.05)]">
            <span class="material-symbols-outlined text-secondary flex-shrink-0">work</span>
            <div class="flex-1 min-w-0">
                <p class="text-xs text-on-surface-variant">Projet associé</p>
                <p class="text-sm font-medium text-primary truncate"><?= h($contract['project_title']) ?></p>
            </div>
            <span class="material-symbols-outlined text-slate-300 text-base">open_in_new</span>
        </a>

        <!-- Gérer le contrat -->
        <a href="/upc_freelance/app/contracts/manage.php?id=<?= $contractId ?>"
           class="flex items-center gap-3 bg-white rounded-2xl border border-slate-200 p-4
                  hover:border-amber-300 hover:shadow-md transition-all
                  shadow-[0px_4px_12px_rgba(26,54,93,0.05)]">
            <span class="material-symbols-outlined text-amber-500 flex-shrink-0">settings</span>
            <div class="flex-1">
                <p class="text-sm font-medium text-primary">Gérer le contrat</p>
                <p class="text-xs text-slate-400">Litige, annulation…</p>
            </div>
            <span class="material-symbols-outlined text-slate-300 text-base">chevron_right</span>
        </a>
    </aside>
</div>

<!-- ══════════════════════════════════════════════════════
     POPUP INFOS — Mobile uniquement
     ══════════════════════════════════════════════════════ -->
<div id="contract-info-popup"
     class="fixed inset-0 z-50 hidden lg:hidden"
     onclick="if(event.target===this)closeContractInfo()">
    <div class="absolute inset-0 bg-black/50 backdrop-blur-sm"></div>

    <!-- Sheet depuis le bas (style mobile) -->
    <div class="absolute bottom-0 left-0 right-0 bg-white rounded-t-3xl max-h-[85dvh] overflow-y-auto"
         style="animation:slideUp 0.3s ease">
        <!-- Handle -->
        <div class="flex justify-center pt-3 pb-2">
            <div class="w-10 h-1 bg-slate-200 rounded-full"></div>
        </div>

        <div class="px-5 pb-8 space-y-5">
            <!-- Titre + fermer -->
            <div class="flex items-center justify-between py-1">
                <h2 class="font-bold text-primary">Infos du contrat</h2>
                <button onclick="closeContractInfo()"
                        class="p-1.5 rounded-xl hover:bg-slate-100 text-slate-400 transition-colors">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>

            <!-- Détails contrat -->
            <div class="bg-slate-50 rounded-2xl p-4">
                <h3 class="font-semibold text-primary mb-3 text-sm">Détails</h3>
                <div class="space-y-2.5 text-sm">
                    <div class="flex justify-between">
                        <span class="text-slate-500">Montant</span>
                        <span class="font-bold text-secondary"><?= money((float)$contract['amount']) ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-500">Statut</span>
                        <span class="text-xs font-semibold bg-<?= $sc ?>-100 text-<?= $sc ?>-700 px-2 py-0.5 rounded-full">
                            <?= $sl ?>
                        </span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-500">Début</span>
                        <span class="font-medium text-primary"><?= formatDate($contract['start_date'] ?? $contract['created_at']) ?></span>
                    </div>
                    <?php if ($contract['end_date']): ?>
                    <div class="flex justify-between">
                        <span class="text-slate-500">Fin prévue</span>
                        <span class="font-medium text-primary"><?= formatDate($contract['end_date']) ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Fichiers partagés (mobile) -->
            <div class="bg-slate-50 rounded-2xl p-4">
                <h3 class="font-semibold text-primary mb-3 text-sm flex items-center gap-2">
                    <span class="material-symbols-outlined text-base text-secondary">folder_shared</span>
                    Fichiers partagés
                </h3>
                <?php if (empty($contractFiles)): ?>
                <p class="text-xs text-slate-400 mb-3">Aucun fichier partagé pour l'instant.</p>
                <?php else: ?>
                <div class="space-y-2 mb-3 max-h-52 overflow-y-auto">
                    <?php foreach ($contractFiles as $f): ?>
                    <div class="flex items-center gap-2 p-2 rounded-xl bg-white border border-slate-100">
                        <span class="material-symbols-outlined text-secondary text-lg flex-shrink-0">
                            <?= contractFileIcon($f['file_path']) ?>
                        </span>
                        <div class="flex-1 min-w-0">
                            <a href="/upc_freelance/app/contracts/download-file.php?id=<?= $f['id'] ?>"
                               class="text-sm font-medium text-primary hover:underline truncate block">
                                <?= h($f['file_name']) ?>
                            </a>
                            <p class="text-[11px] text-slate-400">
                                <?= contractFileSize((int)$f['file_size']) ?> · <?= h($f['first_name']) ?>
                            </p>
                        </div>
                        <?php if ($f['uploaded_by'] === $user['id']): ?>
                        <form method="POST" onsubmit="return confirm('Supprimer ce fichier ?');">
                            <?= csrfField() ?>
                            <input type="hidden" name="delete_file" value="<?= $f['id'] ?>"/>
                            <button type="submit" class="text-slate-300 hover:text-red-500 transition-colors flex-shrink-0">
                                <span class="material-symbols-outlined text-base">delete</span>
                            </button>
                        </form>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <?php if ($contract['status'] === 'active'): ?>
                <form method="POST" enctype="multipart/form-data">
                    <?= csrfField() ?>
                    <input type="hidden" name="upload_file" value="1"/>
                    <label class="flex items-center justify-center gap-2 py-2.5 rounded-xl border-2 border-dashed border-slate-300
                                   text-xs font-semibold text-slate-500 bg-white cursor-pointer">
                        <span class="material-symbols-outlined text-base">upload_file</span>
                        Partager un fichier
                        <input type="file" name="contract_file" class="hidden" onchange="this.form.submit()"/>
                    </label>
                </form>
                <?php endif; ?>
            </div>

            <!-- Participants -->
            <div class="bg-slate-50 rounded-2xl p-4">
                <h3 class="font-semibold text-primary mb-3 text-sm">Participants</h3>
                <?php foreach ([
                    ['fname'=>$contract['client_fname'],'lname'=>$contract['client_lname'],'avatar'=>$contract['client_avatar'],'verified'=>$contract['client_verified'],'role'=>'Client','isMe'=>$isClient],
                    ['fname'=>$contract['freelancer_fname'],'lname'=>$contract['freelancer_lname'],'avatar'=>$contract['freelancer_avatar'],'verified'=>$contract['freelancer_verified'],'role'=>'Freelancer','isMe'=>$isFreelancer],
                ] as $i=>$p): ?>
                <div class="flex items-center gap-3 <?= $i===1 ? 'pt-3 mt-3 border-t border-slate-200' : '' ?>">
                    <div class="relative flex-shrink-0">
                        <?php if ($p['avatar']): ?>
                        <img src="/upc_freelance/storage/<?= h($p['avatar']) ?>" class="w-10 h-10 rounded-full object-cover"/>
                        <?php else: ?>
                        <div class="w-10 h-10 rounded-full bg-primary/10 flex items-center justify-center text-sm font-bold text-primary">
                            <?= mb_strtoupper(mb_substr($p['fname'],0,1)) ?>
                        </div>
                        <?php endif; ?>
                        <?php if ($p['verified']): ?>
                        <span class="absolute -bottom-0.5 -right-0.5 w-4 h-4 bg-secondary rounded-full border-2 border-white flex items-center justify-center">
                            <span class="material-symbols-outlined text-white" style="font-size:9px;font-variation-settings:'FILL' 1">verified</span>
                        </span>
                        <?php endif; ?>
                    </div>
                    <div>
                        <p class="font-semibold text-primary text-sm">
                            <?= h($p['fname'].' '.$p['lname']) ?>
                            <?= $p['isMe'] ? '<span class="text-xs text-slate-400 font-normal">(vous)</span>' : '' ?>
                        </p>
                        <p class="text-xs text-slate-400"><?= $p['role'] ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Actions -->
            <?php if ($isClient && $contract['status'] === 'active'): ?>
            <button onclick="closeContractInfo(); openReviewPopup();"
                    class="w-full bg-emerald-500 text-white font-semibold py-3.5 rounded-2xl
                           hover:bg-emerald-600 transition-colors active:scale-95
                           flex items-center justify-center gap-2">
                <span class="material-symbols-outlined" style="font-variation-settings:'FILL' 1">task_alt</span>
                Valider & libérer le paiement
            </button>
            <?php endif; ?>

            <div class="grid grid-cols-2 gap-3">
                <a href="/upc_freelance/app/projects/details.php?id=<?= $contract['project_id'] ?>"
                   class="flex items-center justify-center gap-2 py-3 rounded-2xl border border-slate-200
                          text-sm font-medium text-secondary hover:bg-secondary/5 transition-colors">
                    <span class="material-symbols-outlined text-base">work</span>
                    Voir le projet
                </a>
                <a href="/upc_freelance/app/contracts/manage.php?id=<?= $contractId ?>"
                   class="flex items-center justify-center gap-2 py-3 rounded-2xl border border-slate-200
                          text-sm font-medium text-slate-600 hover:bg-slate-50 transition-colors">
                    <span class="material-symbols-outlined text-base">settings</span>
                    Gérer
                </a>
            </div>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════
     POPUP REVIEW (validation + note)
     ══════════════════════════════════════════════════════ -->
<?php if ($isClient && $contract['status'] === 'active'): ?>
<div id="review-popup"
     class="fixed inset-0 z-[60] hidden items-center justify-center p-4"
     onclick="if(event.target===this)closeReviewPopup()">
    <div class="absolute inset-0 bg-black/50 backdrop-blur-sm"></div>

    <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md"
         style="animation:popIn 0.25s ease">
        <div class="flex items-center justify-between px-6 py-5 border-b border-slate-100">
            <div>
                <h2 class="font-bold text-primary text-lg">Valider le travail</h2>
                <p class="text-xs text-slate-400 mt-0.5">Notez le freelancer avant de libérer le paiement</p>
            </div>
            <button onclick="closeReviewPopup()" class="p-2 rounded-xl hover:bg-slate-100 text-slate-400">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>

        <form method="POST" id="review-form">
            <?= csrfField() ?>
            <input type="hidden" name="complete_with_review" value="1"/>
            <div class="px-6 py-5 space-y-5">

                <!-- Profil freelancer -->
                <div class="flex items-center gap-3 p-3 bg-slate-50 rounded-xl border border-slate-100">
                    <?php if ($contract['freelancer_avatar']): ?>
                    <img src="/upc_freelance/storage/<?= h($contract['freelancer_avatar']) ?>" class="w-10 h-10 rounded-full object-cover flex-shrink-0"/>
                    <?php else: ?>
                    <div class="w-10 h-10 rounded-full bg-primary/10 flex items-center justify-center text-sm font-bold text-primary flex-shrink-0">
                        <?= mb_strtoupper(mb_substr($contract['freelancer_fname'],0,1)) ?>
                    </div>
                    <?php endif; ?>
                    <div class="flex-1 min-w-0">
                        <p class="font-semibold text-primary text-sm">
                            <?= h($contract['freelancer_fname'].' '.$contract['freelancer_lname']) ?>
                        </p>
                        <p class="text-xs text-slate-400 truncate"><?= h($contract['project_title']) ?></p>
                    </div>
                    <div class="text-right flex-shrink-0">
                        <p class="text-[10px] text-slate-400">À libérer</p>
                        <p class="font-bold text-emerald-600 text-sm"><?= money((float)$contract['amount']) ?></p>
                    </div>
                </div>

                <!-- Étoiles -->
                <div>
                    <label class="block text-sm font-semibold text-primary mb-3">Note <span class="text-red-500">*</span></label>
                    <div class="flex items-center gap-3">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                        <button type="button" data-star="<?= $i ?>"
                                onclick="setRating(<?= $i ?>)"
                                onmouseover="hoverRating(<?= $i ?>)"
                                onmouseout="resetHover()"
                                class="star-btn transition-transform hover:scale-110 focus:outline-none">
                            <span class="material-symbols-outlined text-3xl text-slate-200"
                                  id="star-<?= $i ?>"
                                  style="font-variation-settings:'FILL' 0">star</span>
                        </button>
                        <?php endfor; ?>
                        <span id="rating-label" class="ml-1 text-sm font-semibold"></span>
                    </div>
                    <input type="hidden" name="rating" id="rating-input" value="5"/>
                </div>

                <!-- Chips -->
                <div>
                    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">Points positifs</p>
                    <div class="flex flex-wrap gap-2">
                        <?php foreach (['Qualité','Ponctualité','Communication','Créativité','Professionnel'] as $chip): ?>
                        <button type="button" onclick="toggleChip(this)" data-chip="<?= $chip ?>"
                                class="chip text-xs font-medium px-3 py-1.5 rounded-full border border-slate-200
                                       text-slate-600 bg-white hover:border-secondary hover:text-secondary transition-all">
                            <?= $chip ?>
                        </button>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Commentaire -->
                <div>
                    <label class="block text-sm font-semibold text-primary mb-2">
                        Commentaire <span class="text-slate-400 font-normal">(optionnel)</span>
                    </label>
                    <textarea name="comment" id="review-comment" rows="3"
                              placeholder="Décrivez votre expérience…"
                              class="w-full px-4 py-3 rounded-xl border border-outline-variant
                                     focus:border-secondary focus:ring-2 focus:ring-secondary/20
                                     outline-none text-sm resize-none transition-all"></textarea>
                </div>
            </div>

            <div class="px-6 pb-6 flex gap-3">
                <button type="button" onclick="closeReviewPopup()"
                        class="flex-1 py-3 rounded-xl border-2 border-slate-200 text-sm font-semibold
                               text-slate-500 hover:border-slate-300 transition-colors">
                    Annuler
                </button>
                <button type="submit" id="confirm-btn"
                        class="flex-1 py-3 rounded-xl bg-emerald-500 text-white text-sm font-bold
                               hover:bg-emerald-600 transition-colors active:scale-95
                               flex items-center justify-center gap-2">
                    <span class="material-symbols-outlined text-base">task_alt</span>
                    Confirmer
                </button>
            </div>
        </form>
    </div>
</div>

<style>
.chip.selected { background:#eff6ff; color:#0061a5; border-color:#0061a5; }
@keyframes popIn  { from{transform:scale(.92);opacity:0} to{transform:scale(1);opacity:1} }
@keyframes popOut { from{transform:scale(1);opacity:1}   to{transform:scale(.92);opacity:0} }
@keyframes slideUp { from{transform:translateY(100%)} to{transform:translateY(0)} }
#review-popup.closing>div:last-child { animation:popOut .18s ease forwards; }
</style>
<?php endif; ?>

<!-- ══════════════════════════════════════════════════════
     SCRIPTS
     ══════════════════════════════════════════════════════ -->
<script>
// ── Popups ────────────────────────────────────────────────────
function openContractInfo() {
    const p = document.getElementById('contract-info-popup');
    p.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}
function closeContractInfo() {
    const p = document.getElementById('contract-info-popup');
    p.classList.add('hidden');
    document.body.style.overflow = '';
}

function openReviewPopup() {
    const p = document.getElementById('review-popup');
    if (!p) return;
    p.classList.remove('hidden');
    p.classList.add('flex');
    document.body.style.overflow = 'hidden';
    setRating(5);
}
function closeReviewPopup() {
    const p = document.getElementById('review-popup');
    if (!p) return;
    p.classList.add('closing');
    setTimeout(() => { p.classList.add('hidden'); p.classList.remove('flex','closing'); document.body.style.overflow = ''; }, 200);
}

document.addEventListener('keydown', e => {
    if (e.key === 'Escape') { closeContractInfo(); closeReviewPopup(); }
});

// ── Étoiles ───────────────────────────────────────────────────
const ratingLabels = ['','Décevant','Passable','Bien','Très bien','Excellent !'];
const ratingColors = ['','#ef4444','#f97316','#eab308','#22c55e','#16a34a'];
let currentRating = 5;

function setRating(n) {
    currentRating = n;
    const ratingInput = document.getElementById('rating-input');
    if (ratingInput) ratingInput.value = n;
    paintStars(n);
    const lbl = document.getElementById('rating-label');
    if (lbl) {
        lbl.textContent = ratingLabels[n];
        lbl.style.color = ratingColors[n];
    }
}
function hoverRating(n) { paintStars(n); }
function resetHover()   { paintStars(currentRating); }
function paintStars(n) {
    for (let i = 1; i <= 5; i++) {
        const el = document.getElementById('star-' + i);
        if (!el) continue;
        const on = i <= n;
        el.style.fontVariationSettings = on ? "'FILL' 1" : "'FILL' 0";
        el.style.color = on ? (n >= 4 ? '#f59e0b' : n >= 3 ? '#fb923c' : '#ef4444') : '#cbd5e1';
    }
}
if (document.getElementById('rating-input')) setRating(5);

// ── Chips ─────────────────────────────────────────────────────
const selectedChips = new Set();
function toggleChip(btn) {
    const chip = btn.dataset.chip;
    selectedChips.has(chip) ? selectedChips.delete(chip) : selectedChips.add(chip);
    btn.classList.toggle('selected', selectedChips.has(chip));
    const ta  = document.getElementById('review-comment');
    let text  = ta.value.replace(/\n?✓ [^\n]+/g,'').trim();
    if (selectedChips.size > 0) text += '\n' + [...selectedChips].map(c=>'✓ '+c).join('\n');
    ta.value = text;
}

// ── Review submit ─────────────────────────────────────────────
document.getElementById('review-form')?.addEventListener('submit', function(e) {
    if (!parseInt(document.getElementById('rating-input').value)) {
        e.preventDefault(); alert('Sélectionnez une note.'); return;
    }
    const btn = document.getElementById('confirm-btn');
    btn.disabled = true;
    btn.innerHTML = '<svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/></svg> Traitement…';
});

// ── Chat temps réel ────────────────────────────────────────────
(function() {
    const contractId  = <?= $contractId ?>;
    const currentUser = <?= $user['id'] ?>;
    const isActive    = <?= $contract['status'] === 'active' ? 'true' : 'false' ?>;
    const container   = document.getElementById('messages-container');
    const form        = document.getElementById('chat-form');
    const input       = document.getElementById('message-input');

    let lastId     = <?= $lastMsgId ?>;
    let totalCount = <?= count($messages) ?>;

    function scrollBottom(force) {
        if (!container) return;
        const nearBottom = container.scrollHeight - container.scrollTop - container.clientHeight < 100;
        if (force || nearBottom) container.scrollTop = container.scrollHeight;
    }
    scrollBottom(true);

    function esc(s) {
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    }
    function timeAgoJs(d) {
        const s = Math.floor((Date.now() - new Date(d)) / 1000);
        if (s < 60) return "À l'instant";
        if (s < 3600) return Math.floor(s/60) + ' min';
        if (s < 86400) return Math.floor(s/3600) + ' h';
        return Math.floor(s/86400) + ' j';
    }

    function buildBubble(msg) {
        const isMe  = parseInt(msg.sender_id) === currentUser;
        const init  = msg.first_name ? msg.first_name.charAt(0).toUpperCase() : '?';
        const div   = document.createElement('div');
        div.className = `flex ${isMe?'justify-end':'justify-start'} gap-2 items-end`;
        div.setAttribute('data-msg-id', msg.id);

        const avatarHtml = `<div class="w-7 h-7 rounded-full ${isMe?'bg-secondary/10 text-secondary':'bg-primary/10 text-primary'} flex items-center justify-center text-xs font-bold flex-shrink-0">${init}</div>`;
        const senderName = !isMe ? `<p class="text-[10px] text-slate-400 mb-1 ml-2">${esc(msg.first_name)}</p>` : '';
        const bubble     = isMe ? 'bg-primary text-white rounded-br-sm' : 'bg-surface-container-low text-on-surface rounded-bl-sm';
        const doneAll    = isMe ? `<span class="material-symbols-outlined text-[11px] align-middle text-secondary ml-0.5" style="font-variation-settings:'FILL' 1">done_all</span>` : '';

        div.innerHTML = `
            ${!isMe ? avatarHtml : ''}
            <div class="max-w-[72%] sm:max-w-[65%]">
                ${senderName}
                <div class="px-3.5 py-2.5 rounded-2xl ${bubble}">
                    <p class="text-sm leading-relaxed break-words">${esc(msg.body).replace(/\n/g,'<br>')}</p>
                </div>
                <p class="text-[10px] text-slate-400 mt-1 ${isMe?'text-right mr-1':'ml-2'}">
                    ${timeAgoJs(msg.created_at)} ${doneAll}
                </p>
            </div>
            ${isMe ? avatarHtml : ''}
        `;
        return div;
    }

    function appendMessages(msgs) {
        if (!msgs.length) return;
        const empty = document.getElementById('empty-chat');
        if (empty) empty.remove();
        msgs.forEach(msg => {
            if (container.querySelector(`[data-msg-id="${msg.id}"]`)) return;
            container.appendChild(buildBubble(msg));
            lastId = Math.max(lastId, parseInt(msg.id));
            totalCount++;
        });
        scrollBottom(false);
    }

    async function poll() {
        try {
            const r = await fetch(`/upc_freelance/app/messages/api-messages.php?contract_id=${contractId}&since=${lastId}`,{credentials:'same-origin'});
            if (r.ok) appendMessages(await r.json());
        } catch {}
    }
    setInterval(poll, 3000);

    if (form && isActive) {
        form.addEventListener('submit', async e => {
            e.preventDefault();
            const body = input.value.trim();
            if (!body) return;

            const sendBtn = document.getElementById('send-btn');
            sendBtn.disabled = true; input.disabled = true;

            // Affichage optimiste
            const tmp = buildBubble({ id:'tmp_'+Date.now(), body, sender_id:currentUser, created_at:new Date().toISOString(), first_name:'', avatar:null });
            tmp.style.opacity = '0.6';
            const empty = document.getElementById('empty-chat');
            if (empty) empty.remove();
            container.appendChild(tmp);
            scrollBottom(true);
            input.value = '';

            try {
                const fd = new FormData(form);
                fd.set('message_body', body);
                const r = await fetch(`/upc_freelance/app/contracts/details.php?id=${contractId}`,{
                    method:'POST', headers:{'X-Requested-With':'XMLHttpRequest'},
                    credentials:'same-origin', body:fd
                });
                tmp.remove();
                if (r.ok) await poll();
                else tmp.style.opacity = '1';
            } catch { tmp.style.opacity = '1'; }
            finally  { sendBtn.disabled = false; input.disabled = false; input.focus(); }
        });
    }
})();
</script>

<?php $appLayout = true; require_once '../../includes/footer.php'; ?>