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
           p.title AS project_title, p.id AS project_id, p.description AS project_desc,
           cl.first_name AS client_fname, cl.last_name AS client_lname, cl.avatar AS client_avatar, cl.is_verified AS client_verified,
           fr.first_name AS freelancer_fname, fr.last_name AS freelancer_lname, fr.avatar AS freelancer_avatar, fr.is_verified AS freelancer_verified
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

// ─── Envoyer un message (AJAX ou form classique) ──────────────
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

        // Réponse JSON si appel AJAX
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            header('Content-Type: application/json');
            echo json_encode(['ok' => true]);
            exit;
        }
    }
    redirect('../../app/contracts/details.php?id=' . $contractId . '#chat');
}

// ─── Soumettre review + compléter le contrat ─────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['complete_with_review'])) {
    verifyCsrf();
    if ($isClient && $contract['status'] === 'active') {
        $rating  = max(1, min(5, (int)($_POST['rating'] ?? 5)));
        $comment = sanitize($_POST['comment'] ?? '');

        // 1. Compléter le contrat
        $pdo->prepare('UPDATE contracts SET status = "completed", completed_at = NOW() WHERE id = ?')
            ->execute([$contractId]);
        $pdo->prepare('UPDATE projects SET status = "completed" WHERE id = ?')
            ->execute([$contract['project_id']]);

        // 2. Libérer le paiement
        $pdo->prepare('UPDATE wallets SET locked = locked - ? WHERE user_id = ?')
            ->execute([$contract['amount'], $contract['client_id']]);
        $pdo->prepare('UPDATE wallets SET balance = balance + ? WHERE user_id = ?')
            ->execute([$contract['amount'], $contract['freelancer_id']]);
        recordTransaction($contract['freelancer_id'], 'payment', $contract['amount'], $contractId,
            'Paiement reçu pour contrat #' . $contractId);

        // 3. Enregistrer la review (si pas déjà faite)
        $existingReview = $pdo->prepare('SELECT id FROM reviews WHERE contract_id = ? AND reviewer_id = ?');
        $existingReview->execute([$contractId, $user['id']]);
        if (!$existingReview->fetch()) {
            $pdo->prepare('INSERT INTO reviews (contract_id, reviewer_id, reviewed_id, rating, comment) VALUES (?, ?, ?, ?, ?)')
                ->execute([$contractId, $user['id'], $contract['freelancer_id'], $rating, $comment ?: null]);

            // Mettre à jour la note moyenne du freelancer
            $avgStmt = $pdo->prepare('
                SELECT AVG(rating) AS avg_rating, COUNT(*) AS total
                FROM reviews WHERE reviewed_id = ?
            ');
            $avgStmt->execute([$contract['freelancer_id']]);
            $avgData = $avgStmt->fetch();
            $pdo->prepare('UPDATE freelancer_profiles SET rating = ?, total_reviews = ? WHERE user_id = ?')
                ->execute([round($avgData['avg_rating'], 2), $avgData['total'], $contract['freelancer_id']]);

            // Mettre à jour total_earned du freelancer
            $pdo->prepare('UPDATE freelancer_profiles SET total_earned = total_earned + ? WHERE user_id = ?')
                ->execute([$contract['amount'], $contract['freelancer_id']]);
        }

        // 4. Notifications
        sendNotification($contract['freelancer_id'], 'payment_received', 'Paiement reçu !',
            money((float)$contract['amount']) . ' ont été crédités sur votre wallet. ' . $user['first_name'] . ' vous a laissé un avis.',
            '/upc_freelance/app/wallet/index.php');

        flash('success', 'Contrat terminé ! Le paiement a été transféré et votre avis enregistré.');
        redirect('../../app/contracts/details.php?id=' . $contractId);
    }
}

// ─── Valider le travail (ancien bouton — gardé pour compatibilité) ────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['complete_contract'])) {
    verifyCsrf();
    if ($isClient && $contract['status'] === 'active') {
        $pdo->prepare('UPDATE contracts SET status = "completed", completed_at = NOW() WHERE id = ?')->execute([$contractId]);
        $pdo->prepare('UPDATE projects SET status = "completed" WHERE id = ?')->execute([$contract['project_id']]);

        $pdo->prepare('UPDATE wallets SET locked = locked - ? WHERE user_id = ?')
            ->execute([$contract['amount'], $contract['client_id']]);
        $pdo->prepare('UPDATE wallets SET balance = balance + ? WHERE user_id = ?')
            ->execute([$contract['amount'], $contract['freelancer_id']]);

        recordTransaction($contract['freelancer_id'], 'payment', $contract['amount'], $contractId,
            'Paiement reçu pour contrat #' . $contractId);
        sendNotification($contract['freelancer_id'], 'payment_received', 'Paiement reçu !',
            money((float)$contract['amount']) . ' ont été crédités sur votre wallet.',
            '/upc_freelance/app/wallet/index.php');

        flash('success', 'Contrat terminé ! Le paiement a été transféré au freelancer.');
        redirect('../../app/contracts/details.php?id=' . $contractId);
    }
}

// ─── Marquer messages comme lus ──────────────────────────────
$pdo->prepare('UPDATE messages SET is_read = 1 WHERE contract_id = ? AND sender_id != ?')
    ->execute([$contractId, $user['id']]);

// ─── Charger messages initiaux ────────────────────────────────
$stmtMsgs = $pdo->prepare('
    SELECT m.id, m.body, m.sender_id, m.created_at, m.is_read,
           u.first_name, u.last_name, u.avatar
    FROM messages m
    JOIN users u ON u.id = m.sender_id
    WHERE m.contract_id = ?
    ORDER BY m.created_at ASC
');
$stmtMsgs->execute([$contractId]);
$messages = $stmtMsgs->fetchAll();

// ID du dernier message pour le polling JS
$lastMsgId = !empty($messages) ? (int)end($messages)['id'] : 0;

// Vérifier si le client a déjà laissé un avis
$hasReviewed = false;
if ($isClient) {
    $rvStmt = $pdo->prepare('SELECT id FROM reviews WHERE contract_id = ? AND reviewer_id = ?');
    $rvStmt->execute([$contractId, $user['id']]);
    $hasReviewed = (bool)$rvStmt->fetch();
}

$pageTitle = 'Contrat — ' . h($contract['project_title']);
$appLayout = true;
require_once '../../includes/header.php';
?>

<?php renderFlash(); ?>

<a href="/upc_freelance/app/contracts/list.php" class="inline-flex items-center gap-1 text-sm text-secondary hover:underline mb-6">
    <span class="material-symbols-outlined text-base">arrow_back</span> Mes contrats
</a>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    <!-- ── Chat ─────────────────────────────────────────── -->
    <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-100 custom-shadow-low overflow-hidden flex flex-col" style="min-height:600px;" id="chat">

        <!-- Header -->
        <div class="flex items-center justify-between p-5 border-b border-slate-100">
            <div>
                <h2 class="font-semibold text-primary"><?= h($contract['project_title']) ?></h2>
                <p class="text-xs text-on-surface-variant" id="msg-count">
                    Chat du contrat · <?= count($messages) ?> message<?= count($messages)>1?'s':'' ?>
                </p>
            </div>
            <?php
            $sc = ['active'=>'green','completed'=>'blue','cancelled'=>'red','disputed'=>'amber'][$contract['status']] ?? 'gray';
            $sl = ['active'=>'Actif','completed'=>'Terminé','cancelled'=>'Annulé','disputed'=>'Litige'][$contract['status']] ?? $contract['status'];
            ?>
            <span class="text-xs bg-<?= $sc ?>-100 text-<?= $sc ?>-700 px-3 py-1.5 rounded-full font-medium"><?= $sl ?></span>
        </div>

        <!-- Messages -->
        <div class="flex-1 overflow-y-auto p-5 space-y-4" id="messages-container">
            <?php if (empty($messages)): ?>
            <div class="text-center py-12" id="empty-chat">
                <span class="material-symbols-outlined text-4xl text-slate-300 block mb-3">chat</span>
                <p class="text-on-surface-variant text-sm">Aucun message. Démarrez la conversation !</p>
            </div>
            <?php endif; ?>

            <?php foreach ($messages as $msg):
                $isMe = $msg['sender_id'] === $user['id'];
            ?>
            <div class="flex <?= $isMe ? 'justify-end' : 'justify-start' ?> gap-2"
                 data-msg-id="<?= $msg['id'] ?>">
                <?php if (!$isMe): ?>
                <?= renderAvatar($msg['avatar'] ?? null, $msg['first_name'], $msg['last_name'], false, 'w-8 h-8', 'rounded-full', 'mt-auto') ?>
                <?php endif; ?>
                <div class="max-w-[70%]">
                    <?php if (!$isMe): ?>
                    <p class="text-xs text-slate-400 mb-1 ml-1"><?= h($msg['first_name']) ?></p>
                    <?php endif; ?>
                    <div class="px-4 py-2.5 rounded-2xl <?= $isMe ? 'bg-primary text-white rounded-tr-sm' : 'bg-surface-container-low text-on-surface rounded-tl-sm' ?>">
                        <p class="text-sm leading-relaxed"><?= nl2br(h($msg['body'])) ?></p>
                    </div>
                    <p class="text-xs text-slate-400 mt-1 <?= $isMe ? 'text-right mr-1' : 'ml-1' ?>">
                        <?= timeAgo($msg['created_at']) ?>
                    </p>
                </div>
                <?php if ($isMe): ?>
                <?= renderAvatar($user['avatar'] ?? null, $user['first_name'], $user['last_name'] ?? '', false, 'w-8 h-8', 'rounded-full', 'mt-auto') ?>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Formulaire envoi -->
        <?php if ($contract['status'] === 'active'): ?>
        <div class="border-t border-slate-100 p-4">
            <form id="chat-form" class="flex gap-3">
                <?= csrfField() ?>
                <input type="hidden" name="send_message" value="1"/>
                <input type="text" name="message_body" id="message-input"
                       placeholder="Écrire un message..." required autocomplete="off"
                       class="flex-1 px-4 py-2.5 rounded-xl border border-outline-variant focus:border-secondary focus:ring-2 focus:ring-secondary/20 outline-none text-sm transition-all"/>
                <button type="submit" id="send-btn"
                        class="bg-primary text-white px-4 py-2.5 rounded-xl hover:opacity-90 transition-opacity active:scale-95">
                    <span class="material-symbols-outlined">send</span>
                </button>
            </form>
        </div>
        <?php else: ?>
        <div class="border-t border-slate-100 p-4 text-center text-sm text-on-surface-variant">
            Ce contrat est terminé. Le chat est en lecture seule.
        </div>
        <?php endif; ?>
    </div>

    <!-- ── Sidebar ───────────────────────────────────────── -->
    <div class="space-y-5">

        <div class="bg-white rounded-2xl border border-slate-100 p-5 custom-shadow-low">
            <h3 class="font-semibold text-primary mb-4">Détails du contrat</h3>
            <div class="space-y-3 text-sm">
                <div class="flex justify-between">
                    <span class="text-on-surface-variant">Montant</span>
                    <span class="font-bold text-secondary text-base"><?= money((float)$contract['amount']) ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-on-surface-variant">Date début</span>
                    <span class="font-medium text-primary"><?= formatDate($contract['start_date'] ?? $contract['created_at']) ?></span>
                </div>
                <?php if ($contract['end_date']): ?>
                <div class="flex justify-between">
                    <span class="text-on-surface-variant">Date fin prévue</span>
                    <span class="font-medium text-primary"><?= formatDate($contract['end_date']) ?></span>
                </div>
                <?php endif; ?>
                <?php if ($contract['completed_at']): ?>
                <div class="flex justify-between">
                    <span class="text-on-surface-variant">Terminé le</span>
                    <span class="font-medium text-green-600"><?= formatDate($contract['completed_at']) ?></span>
                </div>
                <?php endif; ?>
            </div>

            <?php if ($isClient && $contract['status'] === 'active'): ?>
            <div class="mt-5 pt-4 border-t border-slate-100">
                <p class="text-xs text-on-surface-variant mb-3">
                    Validez le travail pour libérer <strong><?= money((float)$contract['amount']) ?></strong>
                    vers le freelancer.
                </p>
                <button type="button" onclick="openReviewPopup()"
                        class="w-full bg-green-500 text-white text-sm font-semibold py-3 rounded-xl
                               hover:bg-green-600 transition-colors active:scale-95
                               flex items-center justify-center gap-2">
                    <span class="material-symbols-outlined text-base">task_alt</span>
                    Valider & libérer le paiement
                </button>
            </div>
            <?php endif; ?>

            <?php if ($isClient && $contract['status'] === 'completed' && $hasReviewed): ?>
            <div class="mt-4 p-3 bg-blue-50 rounded-xl border border-blue-100 flex items-center gap-2">
                <span class="material-symbols-outlined text-secondary text-base"
                      style="font-variation-settings:'FILL' 1">star</span>
                <p class="text-xs text-secondary font-medium">Vous avez déjà laissé un avis.</p>
            </div>
            <?php endif; ?>

            <?php if ($contract['status'] === 'completed'): ?>
            <div class="mt-4 p-3 bg-green-50 rounded-xl border border-green-200 flex items-center gap-2">
                <span class="material-symbols-outlined text-green-500">check_circle</span>
                <p class="text-xs text-green-700 font-medium">Paiement libéré avec succès</p>
            </div>
            <?php endif; ?>
        </div>

        <!-- Participants -->
        <div class="bg-white rounded-2xl border border-slate-100 p-5 custom-shadow-low">
            <h3 class="font-semibold text-primary mb-4">Participants</h3>
            <div class="flex items-center gap-3 mb-3">
                <?= renderAvatar($contract['client_avatar'] ?? null, $contract['client_fname'], $contract['client_lname'], (bool)($contract['client_verified'] ?? false), 'w-10 h-10', 'rounded-full') ?>
                <div>
                    <p class="text-sm font-semibold text-primary flex items-center gap-1">
                        <?= h($contract['client_fname'] . ' ' . $contract['client_lname']) ?>
                        <?php if ($contract['client_verified']): ?>
                        <span class="material-symbols-outlined text-secondary text-base" style="font-variation-settings:'FILL' 1" title="Compte vérifié">verified</span>
                        <?php endif; ?>
                    </p>
                    <p class="text-xs text-slate-400">Client <?= $isClient ? '(vous)' : '' ?></p>
                </div>
            </div>
            <div class="border-t border-slate-100 pt-3 flex items-center gap-3">
                <?= renderAvatar($contract['freelancer_avatar'] ?? null, $contract['freelancer_fname'], $contract['freelancer_lname'], (bool)($contract['freelancer_verified'] ?? false), 'w-10 h-10', 'rounded-full') ?>
                <div>
                    <p class="text-sm font-semibold text-primary flex items-center gap-1">
                        <?= h($contract['freelancer_fname'] . ' ' . $contract['freelancer_lname']) ?>
                        <?php if ($contract['freelancer_verified']): ?>
                        <span class="material-symbols-outlined text-secondary text-base" style="font-variation-settings:'FILL' 1" title="Compte vérifié">verified</span>
                        <?php endif; ?>
                    </p>
                    <p class="text-xs text-slate-400">Freelancer <?= $isFreelancer ? '(vous)' : '' ?></p>
                </div>
            </div>
        </div>

        <!-- Lien projet -->
        <a href="/upc_freelance/app/projects/details.php?id=<?= $contract['project_id'] ?>"
           class="flex items-center gap-3 bg-white rounded-2xl border border-slate-100 p-4 hover:border-secondary/40 transition-colors custom-shadow-low">
            <span class="material-symbols-outlined text-secondary">work</span>
            <div class="flex-1 min-w-0">
                <p class="text-xs text-on-surface-variant">Projet associé</p>
                <p class="text-sm font-medium text-primary truncate"><?= h($contract['project_title']) ?></p>
            </div>
            <span class="material-symbols-outlined text-slate-400 text-base">open_in_new</span>
        </a>
    </div>
</div>

<!-- ══ POPUP REVIEW ══════════════════════════════════════════ -->
<?php if ($isClient && $contract['status'] === 'active'): ?>
<div id="review-popup" class="fixed inset-0 z-50 hidden items-center justify-center p-4"
     onclick="if(event.target===this) closeReviewPopup()">
    <div class="absolute inset-0 bg-black/50 backdrop-blur-sm"></div>

    <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md"
         style="animation: popIn 0.25s ease">
        <!-- Header -->
        <div class="flex items-center justify-between px-6 py-5 border-b border-slate-100">
            <div>
                <h2 class="font-bold text-primary text-lg">Valider le travail</h2>
                <p class="text-xs text-slate-400 mt-0.5">Notez le freelancer avant de libérer le paiement</p>
            </div>
            <button onclick="closeReviewPopup()"
                    class="p-2 rounded-xl hover:bg-slate-100 transition-colors text-slate-400">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>

        <form method="POST" id="review-form">
            <?= csrfField() ?>
            <input type="hidden" name="complete_with_review" value="1"/>

            <div class="px-6 py-5 space-y-5">

                <!-- Profil freelancer -->
                <div class="flex items-center gap-3 p-3 bg-slate-50 rounded-xl border border-slate-100">
                    <?= renderAvatar($contract['freelancer_avatar'] ?? null, $contract['freelancer_fname'], $contract['freelancer_lname'], (bool)($contract['freelancer_verified'] ?? false), 'w-10 h-10', 'rounded-full') ?>
                    <div>
                        <p class="font-semibold text-primary text-sm">
                            <?= h($contract['freelancer_fname'] . ' ' . $contract['freelancer_lname']) ?>
                            <?php if ($contract['freelancer_verified']): ?>
                            <span class="material-symbols-outlined text-secondary text-sm align-middle"
                                  style="font-variation-settings:'FILL' 1">verified</span>
                            <?php endif; ?>
                        </p>
                        <p class="text-xs text-slate-400">Freelancer · <?= h($contract['project_title']) ?></p>
                    </div>
                    <div class="ml-auto text-right">
                        <p class="text-xs text-slate-400">Montant libéré</p>
                        <p class="font-bold text-green-600"><?= money((float)$contract['amount']) ?></p>
                    </div>
                </div>

                <!-- Note étoiles -->
                <div>
                    <label class="block text-sm font-semibold text-primary mb-3">
                        Note globale <span class="text-red-500">*</span>
                    </label>
                    <div class="flex items-center gap-2" id="star-rating">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                        <button type="button" data-star="<?= $i ?>"
                                onclick="setRating(<?= $i ?>)"
                                onmouseover="hoverRating(<?= $i ?>)"
                                onmouseout="resetHover()"
                                class="star-btn transition-transform hover:scale-110 focus:outline-none">
                            <span class="material-symbols-outlined text-4xl text-slate-300"
                                  id="star-<?= $i ?>"
                                  style="font-variation-settings:'FILL' 0">star</span>
                        </button>
                        <?php endfor; ?>
                        <span id="rating-label" class="ml-2 text-sm font-semibold text-slate-500"></span>
                    </div>
                    <input type="hidden" name="rating" id="rating-input" value="5"/>
                </div>

                <!-- Critères rapides (chips) -->
                <div>
                    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">
                        Points positifs (optionnel)
                    </p>
                    <div class="flex flex-wrap gap-2" id="quick-chips">
                        <?php foreach (['Travail de qualité','Livraison rapide','Bonne communication','Créatif','Professionnel','Respecte les délais'] as $chip): ?>
                        <button type="button" onclick="toggleChip(this)"
                                data-chip="<?= $chip ?>"
                                class="chip text-xs font-medium px-3 py-1.5 rounded-full border border-slate-200
                                       text-slate-600 bg-white hover:border-secondary hover:text-secondary
                                       transition-all">
                            <?= $chip ?>
                        </button>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Commentaire libre -->
                <div>
                    <label class="block text-sm font-semibold text-primary mb-2">
                        Commentaire <span class="text-slate-400 font-normal">(optionnel)</span>
                    </label>
                    <textarea name="comment" id="review-comment" rows="3"
                              placeholder="Décrivez votre expérience avec ce freelancer..."
                              class="w-full px-4 py-3 rounded-xl border border-outline-variant
                                     focus:border-secondary focus:ring-2 focus:ring-secondary/20
                                     outline-none text-sm resize-none transition-all"></textarea>
                    <p class="text-xs text-slate-400 mt-1" id="char-count">0 / 500 caractères</p>
                </div>
            </div>

            <!-- Footer popup -->
            <div class="px-6 pb-6 flex gap-3">
                <button type="button" onclick="closeReviewPopup()"
                        class="flex-1 py-3 rounded-xl border-2 border-slate-200 text-sm font-semibold
                               text-slate-500 hover:border-slate-300 transition-colors">
                    Annuler
                </button>
                <button type="submit" id="confirm-btn"
                        class="flex-1 py-3 rounded-xl bg-green-500 text-white text-sm font-bold
                               hover:bg-green-600 transition-colors active:scale-95
                               flex items-center justify-center gap-2">
                    <span class="material-symbols-outlined text-base">task_alt</span>
                    Confirmer & libérer
                </button>
            </div>
        </form>
    </div>
</div>

<style>
@keyframes popIn {
    from { transform: scale(0.92); opacity: 0; }
    to   { transform: scale(1);    opacity: 1; }
}
@keyframes popOut {
    from { transform: scale(1);    opacity: 1; }
    to   { transform: scale(0.92); opacity: 0; }
}
#review-popup.closing > div:last-child {
    animation: popOut 0.18s ease forwards;
}
.chip.selected {
    background: #eff6ff;
    color: #0061a5;
    border-color: #0061a5;
}
</style>
<?php endif; ?>

<script>
// ── Popup review ──────────────────────────────────────────
function openReviewPopup() {
    const popup = document.getElementById('review-popup');
    if (!popup) return;
    popup.classList.remove('hidden');
    popup.classList.add('flex');
    document.body.style.overflow = 'hidden';
    setRating(5); // Note par défaut : 5 étoiles
    document.addEventListener('keydown', onEscapeReview);
}

function closeReviewPopup() {
    const popup = document.getElementById('review-popup');
    if (!popup) return;
    popup.classList.add('closing');
    setTimeout(() => {
        popup.classList.add('hidden');
        popup.classList.remove('flex', 'closing');
        document.body.style.overflow = '';
    }, 200);
    document.removeEventListener('keydown', onEscapeReview);
}

function onEscapeReview(e) {
    if (e.key === 'Escape') closeReviewPopup();
}

// ── Système d'étoiles ────────────────────────────────────
const ratingLabels = ['', 'Décevant', 'Passable', 'Bien', 'Très bien', 'Excellent !'];
let currentRating = 5;

function setRating(n) {
    currentRating = n;
    document.getElementById('rating-input').value = n;
    paintStars(n, true);
    document.getElementById('rating-label').textContent = ratingLabels[n];
    document.getElementById('rating-label').style.color =
        n >= 4 ? '#16a34a' : n >= 3 ? '#d97706' : '#dc2626';
}

function hoverRating(n) { paintStars(n, false); }
function resetHover()   { paintStars(currentRating, true); }

function paintStars(n, filled) {
    for (let i = 1; i <= 5; i++) {
        const el = document.getElementById('star-' + i);
        if (!el) continue;
        const active = i <= n;
        el.style.fontVariationSettings = active ? "'FILL' 1" : "'FILL' 0";
        el.style.color = active
            ? (n >= 4 ? '#f59e0b' : n >= 3 ? '#fb923c' : '#ef4444')
            : '#cbd5e1';
    }
}

// Initialiser à 5 étoiles
setRating(5);

// ── Chips ─────────────────────────────────────────────────
const selectedChips = new Set();

function toggleChip(btn) {
    const chip = btn.dataset.chip;
    if (selectedChips.has(chip)) {
        selectedChips.delete(chip);
        btn.classList.remove('selected');
    } else {
        selectedChips.add(chip);
        btn.classList.add('selected');
    }
    // Ajouter les chips au commentaire
    appendChipsToComment();
}

function appendChipsToComment() {
    const textarea = document.getElementById('review-comment');
    // Retirer les anciennes lignes de chips
let text = textarea.value.replace(/\n?✓ [^\n]+/g, '').trim();
if (selectedChips.size > 0) {
    const chipsText = '\n' + [...selectedChips].map(c => '✓ ' + c).join('\n');
        textarea.value = text + chipsText;
    } else {
        textarea.value = text;
    }
    updateCharCount();
}

// ── Compteur caractères ───────────────────────────────────
const textarea  = document.getElementById('review-comment');
const charCount = document.getElementById('char-count');

function updateCharCount() {
    if (!textarea || !charCount) return;
    const len = textarea.value.length;
    charCount.textContent = len + ' / 500 caractères';
    charCount.style.color = len > 450 ? '#ef4444' : '#94a3b8';
    if (len > 500) textarea.value = textarea.value.substring(0, 500);
}

if (textarea) textarea.addEventListener('input', updateCharCount);

// ── Validation avant envoi ────────────────────────────────
document.getElementById('review-form')?.addEventListener('submit', function(e) {
    const rating = parseInt(document.getElementById('rating-input').value);
    if (!rating || rating < 1 || rating > 5) {
        e.preventDefault();
        alert('Veuillez sélectionner une note.');
        return;
    }
    document.getElementById('confirm-btn').disabled = true;
    document.getElementById('confirm-btn').innerHTML =
        '<svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/></svg> Traitement...';
});
</script>

<script>
(function () {
    const contractId  = <?= $contractId ?>;
    const currentUser = <?= $user['id'] ?>;
    const csrfToken   = document.querySelector('input[name="csrf_token"]')?.value ?? '';
    const isActive    = <?= $contract['status'] === 'active' ? 'true' : 'false' ?>;

    const container  = document.getElementById('messages-container');
    const form       = document.getElementById('chat-form');
    const input      = document.getElementById('message-input');
    const countEl    = document.getElementById('msg-count');

    // ── ID du dernier message connu ───────────────────────
    let lastId = <?= $lastMsgId ?>;
    let totalCount = <?= count($messages) ?>;

    // ── Auto-scroll vers le bas ───────────────────────────
    function scrollBottom(force) {
        if (!container) return;
        const nearBottom = container.scrollHeight - container.scrollTop - container.clientHeight < 120;
        if (force || nearBottom) container.scrollTop = container.scrollHeight;
    }
    scrollBottom(true);

    // ── Helpers ───────────────────────────────────────────
    function escHtml(s) {
        return String(s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function timeAgoJs(dateStr) {
        const diff = Math.floor((Date.now() - new Date(dateStr)) / 1000);
        if (diff < 60)      return 'À l\'instant';
        if (diff < 3600)    return Math.floor(diff / 60) + ' min';
        if (diff < 86400)   return Math.floor(diff / 3600) + ' h';
        if (diff < 2592000) return Math.floor(diff / 86400) + ' j';
        return new Date(dateStr).toLocaleDateString('fr-FR');
    }

    function nl2brJs(s) {
        return escHtml(s).replace(/\n/g, '<br>');
    }

    // ── Créer une bulle de message ────────────────────────
    function buildBubble(msg) {
        const isMe     = parseInt(msg.sender_id) === currentUser;
        const initiale = msg.first_name ? msg.first_name.charAt(0).toUpperCase() : '?';
        const align    = isMe ? 'justify-end' : 'justify-start';
        const bubble   = isMe
            ? 'bg-primary text-white rounded-tr-sm'
            : 'bg-surface-container-low text-on-surface rounded-tl-sm';
        const timeAlign = isMe ? 'text-right mr-1' : 'ml-1';
        function buildAvatarHtml(avatarPath, initiale, isCurrentUser) {
            const base  = '/upc_freelance/storage/';
            const color = isCurrentUser ? 'bg-secondary/10 text-secondary' : 'bg-primary/10 text-primary';
            if (avatarPath) {
                return `<img src="${base}${escHtml(avatarPath)}" alt="Avatar" class="w-8 h-8 rounded-full object-cover flex-shrink-0 mt-auto"/>`;
            }
            return `<div class="w-8 h-8 rounded-full ${color} flex items-center justify-center text-xs font-bold flex-shrink-0 mt-auto">${initiale}</div>`;
        }
        const myAvatar    = <?= json_encode($user['avatar'] ?? null) ?>;
        const avatar = buildAvatarHtml(isMe ? myAvatar : (msg.avatar || null), initiale, isMe);
        const senderName = !isMe
            ? `<p class="text-xs text-slate-400 mb-1 ml-1">${escHtml(msg.first_name)}</p>`
            : '';

        const div = document.createElement('div');
        div.className = `flex ${align} gap-2`;
        div.setAttribute('data-msg-id', msg.id);
        div.innerHTML = `
            ${!isMe ? avatar : ''}
            <div class="max-w-[70%]">
                ${senderName}
                <div class="px-4 py-2.5 rounded-2xl ${bubble}">
                    <p class="text-sm leading-relaxed">${nl2brJs(msg.body)}</p>
                </div>
                <p class="text-xs text-slate-400 mt-1 ${timeAlign}">
                    ${timeAgoJs(msg.created_at)}
                </p>
            </div>
            ${isMe ? avatar : ''}
        `;
        return div;
    }

    // ── Ajouter des messages dans le DOM ──────────────────
    function appendMessages(msgs) {
        if (!msgs.length) return;

        // Supprimer le placeholder "aucun message"
        const empty = document.getElementById('empty-chat');
        if (empty) empty.remove();

        msgs.forEach(msg => {
            // Éviter les doublons
            if (container.querySelector(`[data-msg-id="${msg.id}"]`)) return;
            container.appendChild(buildBubble(msg));
            lastId = Math.max(lastId, parseInt(msg.id));
            totalCount++;
        });

        // Mettre à jour le compteur
        if (countEl) {
            countEl.textContent = `Chat du contrat · ${totalCount} message${totalCount > 1 ? 's' : ''}`;
        }

        scrollBottom(false);
    }

    // ── Polling : nouveaux messages ───────────────────────
    async function pollMessages() {
        try {
            const res  = await fetch(
                `/upc_freelance/app/messages/api-messages.php?contract_id=${contractId}&since=${lastId}`,
                { credentials: 'same-origin' }
            );
            if (!res.ok) return;
            const msgs = await res.json();
            appendMessages(msgs);
        } catch (e) { /* silencieux */ }
    }

    setInterval(pollMessages, 3000);

    // ── Envoi AJAX ────────────────────────────────────────
    if (form && isActive) {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const body = input.value.trim();
            if (!body) return;

            const sendBtn = document.getElementById('send-btn');
            sendBtn.disabled = true;
            input.disabled   = true;

            // Affichage optimiste immédiat
            const optimistic = buildBubble({
                id         : 'tmp_' + Date.now(),
                body       : body,
                sender_id  : currentUser,
                created_at : new Date().toISOString(),
                first_name : '',
                last_name  : '',
                avatar     : null,
            });
            optimistic.style.opacity = '0.6';
            const empty = document.getElementById('empty-chat');
            if (empty) empty.remove();
            container.appendChild(optimistic);
            scrollBottom(true);
            input.value = '';

            try {
                const fd = new FormData(form);
                fd.set('message_body', body);
                const res = await fetch(
                    `/upc_freelance/app/contracts/details.php?id=${contractId}`,
                    {
                        method      : 'POST',
                        headers     : { 'X-Requested-With': 'XMLHttpRequest' },
                        credentials : 'same-origin',
                        body        : fd,
                    }
                );

                if (res.ok) {
                    // Supprimer le message optimiste — le polling le ramènera avec son vrai id
                    optimistic.remove();
                    await pollMessages();
                } else {
                    optimistic.style.opacity = '1';
                    optimistic.style.border  = '1px solid red';
                }
            } catch (err) {
                optimistic.style.opacity = '1';
            } finally {
                sendBtn.disabled = false;
                input.disabled   = false;
                input.focus();
            }
        });
    }
})();
</script>

<?php
$appLayout = true;
require_once '../../includes/footer.php';
?>