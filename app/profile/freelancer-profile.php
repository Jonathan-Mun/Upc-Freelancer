<?php
// ============================================================
// UPC FREELANCE — Voir un profil freelancer
// ../../app/profile/freelancer-profile.php
// ============================================================

require_once '../../includes/middleware.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../includes/db.php';

$pdo    = getDB();
$userId = (int)($_GET['id'] ?? 0);
if (!$userId) redirect('../../app/projects/list.php');

$stmt = $pdo->prepare('
    SELECT u.*, fp.*,
           u.id AS user_id,
           fp.id AS profile_id
    FROM users u
    LEFT JOIN freelancer_profiles fp ON fp.user_id = u.id
    WHERE u.id = ? AND u.role = "freelancer" AND u.is_active = 1
');
$stmt->execute([$userId]);
$freelancer = $stmt->fetch();
if (!$freelancer) {
    http_response_code(404);
    require_once $_SERVER['DOCUMENT_ROOT'] . '/upc_freelance/404.php';
    exit;
}

$skills = $freelancer['skills'] ? json_decode($freelancer['skills'], true) : [];

// Projets complétés — TOUS (pour la modale)
$stmt = $pdo->prepare('
    SELECT ct.*, p.title AS project_title, p.id AS project_id,
           u.first_name AS client_fname, u.last_name AS client_lname
    FROM contracts ct
    JOIN projects p ON p.id = ct.project_id
    JOIN users u    ON u.id = ct.client_id
    WHERE ct.freelancer_id = ? AND ct.status = "completed"
    ORDER BY ct.completed_at DESC
');
$stmt->execute([$userId]);
$allContracts       = $stmt->fetchAll();
$completedContracts = array_slice($allContracts, 0, 6);

// Avis reçus — TOUS (pour la modale)
$stmt = $pdo->prepare('
    SELECT r.*, u.first_name, u.last_name, u.avatar, p.title AS project_title
    FROM reviews r
    JOIN users u ON u.id = r.reviewer_id
    JOIN contracts ct ON ct.id = r.contract_id
    JOIN projects p   ON p.id  = ct.project_id
    WHERE r.reviewed_id = ?
    ORDER BY r.created_at DESC
');
$stmt->execute([$userId]);
$allReviews = $stmt->fetchAll();
$reviews    = array_slice($allReviews, 0, 3);

$currentUser = currentUser();
$isOwn       = $currentUser && $currentUser['id'] === $userId;

$lastDocStatus = null;
if ($isOwn) {
    $stmtDoc = $pdo->prepare('SELECT status FROM verification_docs WHERE user_id = ? ORDER BY created_at DESC LIMIT 1');
    $stmtDoc->execute([$userId]);
    $lastDocStatus = $stmtDoc->fetchColumn();
}

$profileFields = [
    $freelancer['avatar'], $freelancer['title'], $freelancer['bio'],
    $freelancer['university'], $freelancer['skills'], $freelancer['hourly_rate'],
    $freelancer['portfolio_url'] ?? $freelancer['linkedin_url'] ?? $freelancer['github_url'],
];
$filled     = count(array_filter($profileFields));
$completion = (int)round($filled / count($profileFields) * 100);

$availColors = ['available' => 'green', 'busy' => 'amber', 'unavailable' => 'red'];
$availLabels = ['available' => 'Disponible', 'busy' => 'Occupé', 'unavailable' => 'Indisponible'];
$av  = $freelancer['availability'] ?? 'available';
$avc = $availColors[$av] ?? 'gray';
$avl = $availLabels[$av] ?? $av;

$pageTitle = h($freelancer['first_name'] . ' ' . $freelancer['last_name']) . ' — UPC Freelance';
$appLayout = true;
require_once '../../includes/header.php';
?>

<style>
/* ── Reset overflow global ── */
* { box-sizing: border-box; }

.profile-sidebar { position: sticky; top: 5rem; }

/* ── Skill tags ── */
.skill-tag {
    display: inline-flex; align-items: center; gap: 5px;
    background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe;
    font-size: 11px; font-weight: 600; letter-spacing: 0.03em;
    padding: 3px 10px; border-radius: 6px; text-transform: uppercase;
    max-width: 100%; word-break: break-word;
}

/* ── Cards ── */
.section-card {
    background: #fff; border-radius: 14px;
    border: 1px solid #e2e8f0; box-shadow: 0 2px 8px rgba(0,32,69,0.05);
    /* Empêche tout débordement interne */
    overflow: hidden;
    word-break: break-word;
    overflow-wrap: break-word;
}

/* ── Stat rows ── */
.stat-row {
    display: flex; justify-content: space-between; align-items: flex-start;
    gap: 8px; padding: 10px 0; border-bottom: 1px solid #f1f5f9;
}
.stat-row:last-child { border-bottom: none; }
/* Label gauche ne dépasse pas 65% */
.stat-row > span:first-child { flex: 1; min-width: 0; }
/* Valeur droite ne squeeze pas */
.stat-row > span:last-child  { flex-shrink: 0; text-align: right; }

/* ── Avatar initiales ── */
.avatar-initials {
    width: 80px; height: 80px; border-radius: 14px;
    background: #dbeafe; display: flex; align-items: center; justify-content: center;
    font-size: 2rem; font-weight: 700; color: #1d4ed8;
    border: 3px solid #bfdbfe; flex-shrink: 0;
}
@media (min-width: 768px) {
    .avatar-initials { width: 96px; height: 96px; font-size: 2.5rem; }
}

/* ── Portfolio / mission cards ── */
.portfolio-card {
    background: #fff; border-radius: 12px; border: 1px solid #e2e8f0;
    overflow: hidden; transition: transform 0.2s, box-shadow 0.2s;
    word-break: break-word; overflow-wrap: break-word;
}
@media (hover: hover) {
    .portfolio-card:hover { transform: translateY(-3px); box-shadow: 0 8px 24px rgba(0,32,69,0.1); }
}

/* ── Nav sidebar ── */
.nav-link-sidebar {
    display: flex; align-items: center; gap: 10px; padding: 10px 14px;
    border-radius: 8px; font-size: 14px; font-weight: 500; color: #64748b;
    transition: background 0.15s, color 0.15s, transform 0.15s;
    cursor: pointer; text-decoration: none;
}
.nav-link-sidebar:hover  { background: #f1f5f9; color: #1e40af; transform: translateX(3px); }
.nav-link-sidebar.active { background: #eff6ff; color: #1d4ed8; border-right: 3px solid #2563eb; }

/* ── Section header (titre + badge) ── */
.section-heading {
    display: flex; align-items: center;
    flex-wrap: wrap; gap: 8px;
    min-width: 0;
}
.section-heading h2 { margin: 0; }

/* ── Carte mission : montant ne casse pas la ligne ── */
.mission-row {
    display: flex; align-items: flex-start; gap: 10px; min-width: 0;
}
.mission-row .mission-meta { flex: 1; min-width: 0; overflow: hidden; }
.mission-row .mission-amount {
    flex-shrink: 0;
    font-size: 13px; font-weight: 700;
    color: var(--color-secondary, #2563eb);
    white-space: nowrap;
    padding-left: 6px;
}

/* ── Avis : header avec étoiles ── */
.review-header {
    display: flex; align-items: flex-start;
    justify-content: space-between; gap: 8px;
    flex-wrap: wrap; margin-bottom: 10px;
}
.review-header .review-stars { flex-shrink: 0; }

/* ── Textes tronqués ── */
.text-clamp-2 {
    display: -webkit-box; -webkit-line-clamp: 2;
    -webkit-box-orient: vertical; overflow: hidden;
}

/* ── Padding responsive sur section-card ── */
.card-pad  { padding: 16px; }
@media (min-width: 640px)  { .card-pad { padding: 24px; } }
@media (min-width: 1024px) { .card-pad { padding: 32px; } }

/* ── Modales ── */
.modal-overlay {
    position: fixed; inset: 0; z-index: 9999;
    background: rgba(0,20,45,0.55); backdrop-filter: blur(4px);
    display: flex; align-items: flex-end; justify-content: center;
    opacity: 0; pointer-events: none; transition: opacity 0.25s ease;
}
.modal-overlay.open { opacity: 1; pointer-events: all; }
.modal-box {
    background: #fff; border-radius: 20px 20px 0 0;
    width: 100%; max-width: 720px;
    /* 92vh sur mobile pour laisser respirer */
    max-height: 92vh;
    display: flex; flex-direction: column;
    transform: translateY(40px); transition: transform 0.3s cubic-bezier(.22,1,.36,1);
    box-shadow: 0 -8px 40px rgba(0,20,69,0.15);
    /* Empêche le contenu de sortir */
    overflow: hidden;
    word-break: break-word; overflow-wrap: break-word;
}
@media (min-width: 640px) { .modal-box { max-height: 85vh; } }
.modal-overlay.open .modal-box { transform: translateY(0); }
.modal-header {
    display: flex; align-items: center; justify-content: space-between;
    gap: 12px;
    padding: 16px; border-bottom: 1px solid #f1f5f9; flex-shrink: 0;
}
@media (min-width: 640px) { .modal-header { padding: 20px 24px 16px; } }
.modal-header > div { min-width: 0; flex: 1; }
.modal-header h3 { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.modal-body { overflow-y: auto; padding: 16px; flex: 1; }
@media (min-width: 640px) { .modal-body { padding: 20px 24px 28px; } }
.modal-body::-webkit-scrollbar { width: 5px; }
.modal-body::-webkit-scrollbar-track { background: #f8faff; }
.modal-body::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 99px; }

/* ── Bouton voir plus ── */
.btn-see-more {
    display: inline-flex; align-items: center; gap: 6px;
    font-size: 13px; font-weight: 600; color: #2563eb;
    border: 1.5px solid #bfdbfe; background: #eff6ff;
    padding: 8px 18px; border-radius: 10px;
    transition: background 0.15s, transform 0.15s; cursor: pointer;
    white-space: nowrap;
}
.btn-see-more:hover { background: #dbeafe; transform: translateY(-1px); }

/* ── Alerte vérification mobile ── */
.alert-verify {
    display: flex; align-items: flex-start; gap: 12px;
    flex-wrap: wrap;
}
.alert-verify .alert-body { flex: 1; min-width: 0; }
.alert-verify .alert-btn  { flex-shrink: 0; align-self: flex-start; }
</style>
<br>

<div class="flex gap-0 max-w-6xl mx-auto">
    <main class="flex-1 min-w-0 space-y-8">

        <!-- Alerte vérification -->
        <?php if ($isOwn): ?>
            <?php if (!$freelancer['is_verified'] && $lastDocStatus !== 'pending'): ?>
                <div class="mb-6 p-4 bg-amber-50 border border-amber-200 rounded-2xl alert-verify">
                    <div class="w-10 h-10 bg-amber-400 rounded-xl flex items-center justify-center flex-shrink-0">
                        <span class="material-symbols-outlined text-white">shield</span>
                    </div>
                    <div class="alert-body">
                        <p class="font-semibold text-amber-800 text-sm">Compte non vérifié</p>
                        <p class="text-xs text-amber-700 mt-0.5">Obtenez le badge vérifié pour décrocher plus de missions.</p>
                    </div>
                    <a href="/upc_freelance/app/verification/index.php" class="alert-btn bg-amber-500 text-white text-xs px-4 py-2 rounded-xl hover:bg-amber-600 transition-colors whitespace-nowrap">
                        Vérifier →
                    </a>
                </div>
            <?php elseif ($lastDocStatus === 'pending'): ?>
                <div class="mb-6 p-4 bg-blue-50 border border-blue-200 rounded-2xl alert-verify">
                    <div class="w-10 h-10 bg-blue-500 rounded-xl flex items-center justify-center flex-shrink-0">
                        <span class="material-symbols-outlined text-white">hourglass_top</span>
                    </div>
                    <div class="alert-body">
                        <p class="font-semibold text-blue-800 text-sm">Vérification en cours</p>
                        <p class="text-xs text-blue-700 mt-0.5">Votre document est en cours d'examen (24-48h ouvrables).</p>
                    </div>
                    <a href="/upc_freelance/app/verification/index.php" class="alert-btn border border-blue-300 text-blue-700 text-xs px-4 py-2 rounded-xl hover:bg-blue-100/50 transition-colors whitespace-nowrap">
                        Voir le statut
                    </a>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <!-- ── Identité ── -->
        <section id="personal">
            <div class="flex items-start justify-between mb-4 gap-3">
                <div class="min-w-0">
                    <h2 class="text-xl sm:text-2xl font-bold text-primary">Identité</h2>
                    <p class="text-sm text-on-surface-variant mt-1">Présentation publique du freelancer.</p>
                </div>
                <?php if ($isOwn): ?>
                <a href="/upc_freelance/app/profile/edit.php" class="flex-shrink-0 text-xs text-secondary border border-secondary/30 px-3 py-1.5 rounded-full hover:bg-secondary/5 transition-colors flex items-center gap-1">
                    <span class="material-symbols-outlined text-sm">edit</span>
                    <span class="hidden sm:inline">Modifier</span>
                </a>
                <?php endif; ?>
            </div>
            <div class="section-card card-pad">
                <!-- Avatar + nom sur mobile : côte à côte -->
                <div class="flex items-start gap-4 mb-5">
                    <div class="relative flex-shrink-0">
                        <?php if ($freelancer['avatar']): ?>
                        <img src="/upc_freelance/storage/<?= h($freelancer['avatar']) ?>" alt="Avatar"
                             class="w-20 h-20 sm:w-24 sm:h-24 rounded-[14px] object-cover ring-4 ring-blue-100"/>
                        <?php else: ?>
                        <div class="avatar-initials"><?= mb_strtoupper(mb_substr($freelancer['first_name'], 0, 1)) ?></div>
                        <?php endif; ?>
                        <span class="absolute -bottom-2 -right-2 inline-flex items-center gap-1 text-[10px] bg-<?= $avc ?>-100 text-<?= $avc ?>-700 border border-<?= $avc ?>-200 px-1.5 py-0.5 rounded-full font-medium whitespace-nowrap">
                            <span class="w-1.5 h-1.5 bg-<?= $avc ?>-500 rounded-full flex-shrink-0"></span>
                            <?= $avl ?>
                        </span>
                    </div>
                    <div class="flex-1 min-w-0 pt-1">
                        <p class="text-[11px] font-semibold text-slate-400 tracking-widest uppercase mb-1">Nom complet</p>
                        <p class="font-bold text-primary text-base sm:text-lg leading-snug break-words">
                            <?= h($freelancer['first_name'] . ' ' . $freelancer['last_name']) ?>
                            <?php if ($freelancer['is_verified']): ?>
                            <span class="inline-block ml-1 text-secondary align-middle" title="Profil vérifié">
                                <span class="material-symbols-outlined text-base" style="font-variation-settings:'FILL' 1">verified</span>
                            </span>
                            <?php endif; ?>
                        </p>
                        <?php if ($freelancer['title']): ?>
                        <p class="text-sm text-slate-500 mt-1 break-words"><?= h($freelancer['title']) ?></p>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($freelancer['bio']): ?>
                <div class="mb-4">
                    <p class="text-[11px] font-semibold text-slate-400 tracking-widest uppercase mb-1">Bio / Résumé</p>
                    <p class="text-sm text-on-surface-variant leading-relaxed break-words"><?= nl2br(h($freelancer['bio'])) ?></p>
                </div>
                <?php endif; ?>

                <!-- Liens sociaux -->
                <div class="flex flex-wrap gap-2">
                    <?php if ($freelancer['portfolio_url']): ?>
                    <a href="<?= h($freelancer['portfolio_url']) ?>" target="_blank" rel="noopener"
                       class="inline-flex items-center gap-1.5 text-xs text-secondary border border-secondary/30 px-3 py-1.5 rounded-full hover:bg-secondary/5 transition-colors">
                        <span class="material-symbols-outlined text-sm">link</span> Portfolio
                    </a>
                    <?php endif; ?>
                    <?php if ($freelancer['linkedin_url']): ?>
                    <a href="<?= h($freelancer['linkedin_url']) ?>" target="_blank" rel="noopener"
                       class="inline-flex items-center gap-1.5 text-xs text-secondary border border-secondary/30 px-3 py-1.5 rounded-full hover:bg-secondary/5 transition-colors">
                        <span class="material-symbols-outlined text-sm">work</span> LinkedIn
                    </a>
                    <?php endif; ?>
                    <?php if ($freelancer['github_url']): ?>
                    <a href="<?= h($freelancer['github_url']) ?>" target="_blank" rel="noopener"
                       class="inline-flex items-center gap-1.5 text-xs text-secondary border border-secondary/30 px-3 py-1.5 rounded-full hover:bg-secondary/5 transition-colors">
                        <span class="material-symbols-outlined text-sm">code</span> GitHub
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <!-- ── Formation ── -->
        <section id="academic">
            <h2 class="text-xl sm:text-2xl font-bold text-primary mb-4">Formation</h2>
            <div class="space-y-3">
                <?php if ($freelancer['university'] || $freelancer['field_of_study']): ?>
                <div class="section-card card-pad flex items-start gap-3 hover:border-blue-200 transition-colors">
                    <div class="p-2 sm:p-2.5 rounded-lg bg-blue-50 text-blue-700 flex-shrink-0">
                        <span class="material-symbols-outlined" style="font-variation-settings:'FILL' 1">school</span>
                    </div>
                    <div class="flex-1 min-w-0 grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3">
                        <?php if ($freelancer['university']): ?>
                        <div class="min-w-0">
                            <p class="text-[11px] font-semibold text-slate-400 tracking-widest uppercase mb-1">Université</p>
                            <p class="font-medium text-primary text-sm break-words"><?= h($freelancer['university']) ?></p>
                        </div>
                        <?php endif; ?>
                        <?php if ($freelancer['field_of_study']): ?>
                        <div class="min-w-0">
                            <p class="text-[11px] font-semibold text-slate-400 tracking-widest uppercase mb-1">Filière</p>
                            <p class="font-medium text-primary text-sm break-words"><?= h($freelancer['field_of_study']) ?></p>
                        </div>
                        <?php endif; ?>
                        <div class="min-w-0">
                            <p class="text-[11px] font-semibold text-slate-400 tracking-widest uppercase mb-1">Membre depuis</p>
                            <p class="font-medium text-primary text-sm"><?= formatDate($freelancer['created_at']) ?></p>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <div class="section-card p-6 text-center text-slate-400 text-sm">Aucune information académique renseignée.</div>
                <?php endif; ?>
            </div>
        </section>

        <!-- ── Compétences ── -->
        <?php if (!empty($skills)): ?>
        <section id="skills">
            <h2 class="text-xl sm:text-2xl font-bold text-primary mb-4">Compétences</h2>
            <div class="section-card card-pad">
                <div class="flex flex-wrap gap-2">
                    <?php foreach ($skills as $skill): ?>
                    <span class="skill-tag"><?= h($skill) ?></span>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
        <?php endif; ?>

        <!-- ── Missions complétées ── -->
        <?php if (!empty($allContracts)): ?>
        <section id="missions">
            <div class="section-heading mb-4">
                <h2 class="text-xl sm:text-2xl font-bold text-primary">Missions complétées</h2>
                <span class="text-xs bg-green-100 text-green-700 border border-green-200 px-3 py-1 rounded-full font-semibold flex-shrink-0">
                    <?= count($allContracts) ?> mission<?= count($allContracts) > 1 ? 's' : '' ?>
                </span>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <?php foreach ($completedContracts as $ct): ?>
                <div class="section-card portfolio-card p-0">
                    <div class="h-1.5 bg-gradient-to-r from-blue-500 to-blue-300"></div>
                    <div class="p-4">
                        <div class="mission-row">
                            <div class="w-8 h-8 rounded-lg bg-green-100 flex items-center justify-center flex-shrink-0">
                                <span class="material-symbols-outlined text-green-600 text-base">task_alt</span>
                            </div>
                            <div class="mission-meta">
                                <p class="font-semibold text-primary text-sm truncate"><?= h($ct['project_title']) ?></p>
                                <p class="text-xs text-slate-400 mt-0.5 truncate">
                                    par <?= h($ct['client_fname'] . ' ' . $ct['client_lname']) ?>
                                    · <?= formatDate($ct['completed_at'] ?? $ct['created_at']) ?>
                                </p>
                            </div>
                            <span class="mission-amount"><?= money((float)$ct['amount']) ?></span>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php if (count($allContracts) > 6): ?>
            <div class="flex justify-center mt-5">
                <button class="btn-see-more" onclick="openModal('modal-missions')">
                    <span class="material-symbols-outlined text-base">expand_more</span>
                    Voir les <?= count($allContracts) - 6 ?> autres missions
                </button>
            </div>
            <?php endif; ?>
        </section>
        <?php endif; ?>

        <!-- ── Avis clients ── -->
        <?php if (!empty($allReviews)): ?>
        <section id="reviews">
            <div class="section-heading mb-4">
                <h2 class="text-xl sm:text-2xl font-bold text-primary">Avis clients</h2>
                <?php if ($freelancer['rating']): ?>
                <div class="flex items-center gap-1.5 flex-shrink-0">
                    <?= renderStars((float)$freelancer['rating']) ?>
                    <span class="font-bold text-primary text-sm"><?= number_format($freelancer['rating'], 1) ?></span>
                    <span class="text-slate-400 text-xs">(<?= $freelancer['total_reviews'] ?>)</span>
                </div>
                <?php endif; ?>
            </div>
            <div class="space-y-3">
                <?php foreach ($reviews as $r): ?>
                <div class="section-card p-4">
                    <div class="review-header">
                        <div class="flex items-center gap-2.5 min-w-0">
                            <div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center text-sm font-bold text-blue-700 flex-shrink-0">
                                <?= mb_strtoupper(mb_substr($r['first_name'], 0, 1)) ?>
                            </div>
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-primary truncate"><?= h($r['first_name'] . ' ' . $r['last_name']) ?></p>
                                <p class="text-xs text-slate-400"><?= timeAgo($r['created_at']) ?></p>
                            </div>
                        </div>
                        <div class="review-stars"><?= renderStars((int)$r['rating']) ?></div>
                    </div>
                    <?php if ($r['comment']): ?>
                    <p class="text-sm text-on-surface-variant italic leading-relaxed break-words">"<?= h($r['comment']) ?>"</p>
                    <?php endif; ?>
                    <p class="text-xs text-slate-400 mt-2 flex items-center gap-1 min-w-0">
                        <span class="material-symbols-outlined text-sm flex-shrink-0">folder</span>
                        <span class="truncate">Projet : <?= h($r['project_title']) ?></span>
                    </p>
                </div>
                <?php endforeach; ?>
            </div>
            <?php if (count($allReviews) > 3): ?>
            <div class="flex justify-center mt-5">
                <button class="btn-see-more" onclick="openModal('modal-reviews')">
                    <span class="material-symbols-outlined text-base">reviews</span>
                    Voir les <?= count($allReviews) - 3 ?> autres avis
                </button>
            </div>
            <?php endif; ?>
        </section>
        <?php endif; ?>

        <!-- ── Tarif & Stats ── -->
        <section id="billing" class="pb-12">
            <h2 class="text-xl sm:text-2xl font-bold text-primary mb-4">Tarif & Statistiques</h2>
            <div class="section-card card-pad">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 sm:gap-8">
                    <!-- Tarif -->
                    <div>
                        <p class="text-[11px] font-semibold text-slate-400 tracking-widest uppercase mb-3">Tarif horaire</p>
                        <?php if ($freelancer['hourly_rate']): ?>
                        <div class="flex items-baseline gap-2 flex-wrap">
                            <span class="text-2xl sm:text-3xl font-bold text-primary"><?= money((float)$freelancer['hourly_rate']) ?></span>
                            <span class="text-slate-400 text-sm">/ heure</span>
                        </div>
                        <p class="text-xs text-slate-400 italic mt-1">Tarif indicatif, négociable selon le projet.</p>
                        <?php else: ?>
                        <p class="text-sm text-slate-400">Non renseigné</p>
                        <?php endif; ?>
                        <?php if ($freelancer['total_earned'] > 0): ?>
                        <div class="mt-4 p-3 sm:p-4 rounded-xl bg-blue-50 border border-blue-100">
                            <p class="text-[11px] font-semibold text-blue-400 tracking-widest uppercase mb-1">Total gagné</p>
                            <p class="text-lg sm:text-xl font-bold text-blue-800"><?= money((float)$freelancer['total_earned']) ?></p>
                        </div>
                        <?php endif; ?>
                    </div>
                    <!-- Stats -->
                    <div>
                        <p class="text-[11px] font-semibold text-slate-400 tracking-widest uppercase mb-3">Statistiques</p>
                        <div>
                            <div class="stat-row">
                                <span class="text-sm text-on-surface-variant flex items-center gap-1.5">
                                    <span class="material-symbols-outlined text-base text-blue-400 flex-shrink-0">task_alt</span>
                                    <span>Missions</span>
                                </span>
                                <span class="font-bold text-primary"><?= count($allContracts) ?></span>
                            </div>
                            <div class="stat-row">
                                <span class="text-sm text-on-surface-variant flex items-center gap-1.5">
                                    <span class="material-symbols-outlined text-base text-amber-400 flex-shrink-0">star</span>
                                    <span>Note</span>
                                </span>
                                <span class="font-bold text-primary">
                                    <?= $freelancer['rating'] ? number_format($freelancer['rating'], 1) . ' / 5' : 'N/A' ?>
                                </span>
                            </div>
                            <div class="stat-row">
                                <span class="text-sm text-on-surface-variant flex items-center gap-1.5">
                                    <span class="material-symbols-outlined text-base text-slate-400 flex-shrink-0">calendar_month</span>
                                    <span>Membre depuis</span>
                                </span>
                                <span class="text-sm text-primary"><?= formatDate($freelancer['created_at']) ?></span>
                            </div>
                            <?php if ($freelancer['field_of_study']): ?>
                            <div class="stat-row">
                                <span class="text-sm text-on-surface-variant flex items-center gap-1.5">
                                    <span class="material-symbols-outlined text-base text-slate-400 flex-shrink-0">school</span>
                                    <span class="truncate">Filière</span>
                                </span>
                                <span class="text-sm font-medium text-primary text-right break-words max-w-[55%]"><?= h($freelancer['field_of_study']) ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </section>

    </main>
</div>

<!-- ═══════════════════════════════════════════
     MODALE — Toutes les missions
════════════════════════════════════════════ -->
<?php if (count($allContracts) > 6): ?>
<div id="modal-missions" class="modal-overlay" onclick="closeModalOnOverlay(event, 'modal-missions')">
    <div class="modal-box">
        <div class="modal-header">
            <div>
                <h3 class="text-lg font-bold text-primary">Toutes les missions</h3>
                <p class="text-xs text-slate-400 mt-0.5">
                    <?= count($allContracts) ?> mission<?= count($allContracts) > 1 ? 's' : '' ?> complétée<?= count($allContracts) > 1 ? 's' : '' ?>
                </p>
            </div>
            <button onclick="closeModal('modal-missions')"
                    class="w-9 h-9 rounded-full bg-slate-100 hover:bg-slate-200 flex items-center justify-center transition-colors">
                <span class="material-symbols-outlined text-slate-500">close</span>
            </button>
        </div>
        <div class="modal-body">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <?php foreach ($allContracts as $ct): ?>
                <div class="border border-slate-200 rounded-xl overflow-hidden hover:border-blue-200 hover:shadow-sm transition-all">
                    <div class="h-1.5 bg-gradient-to-r from-blue-500 to-blue-300"></div>
                    <div class="p-3 sm:p-4">
                        <div class="mission-row">
                            <div class="w-7 h-7 rounded-lg bg-green-100 flex items-center justify-center flex-shrink-0">
                                <span class="material-symbols-outlined text-green-600 text-sm">task_alt</span>
                            </div>
                            <div class="mission-meta">
                                <p class="font-semibold text-primary text-sm truncate"><?= h($ct['project_title']) ?></p>
                                <p class="text-xs text-slate-400 mt-0.5 truncate">
                                    par <?= h($ct['client_fname'] . ' ' . $ct['client_lname']) ?>
                                    · <?= formatDate($ct['completed_at'] ?? $ct['created_at']) ?>
                                </p>
                            </div>
                            <span class="mission-amount"><?= money((float)$ct['amount']) ?></span>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ═══════════════════════════════════════════
     MODALE — Tous les avis
════════════════════════════════════════════ -->
<?php if (count($allReviews) > 3): ?>
<div id="modal-reviews" class="modal-overlay" onclick="closeModalOnOverlay(event, 'modal-reviews')">
    <div class="modal-box">
        <div class="modal-header">
            <div>
                <h3 class="text-lg font-bold text-primary">Tous les avis clients</h3>
                <div class="flex items-center gap-2 mt-1">
                    <?php if ($freelancer['rating']): ?>
                    <?= renderStars((float)$freelancer['rating']) ?>
                    <span class="text-sm font-bold text-primary"><?= number_format($freelancer['rating'], 1) ?></span>
                    <?php endif; ?>
                    <span class="text-xs text-slate-400"><?= count($allReviews) ?> avis au total</span>
                </div>
            </div>
            <button onclick="closeModal('modal-reviews')"
                    class="w-9 h-9 rounded-full bg-slate-100 hover:bg-slate-200 flex items-center justify-center transition-colors">
                <span class="material-symbols-outlined text-slate-500">close</span>
            </button>
        </div>
        <div class="modal-body space-y-3">
            <?php foreach ($allReviews as $r): ?>
            <div class="bg-slate-50 border border-slate-200 rounded-xl p-4 hover:border-blue-200 transition-colors">
                <div class="review-header">
                    <div class="flex items-center gap-2.5 min-w-0">
                        <div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center text-sm font-bold text-blue-700 flex-shrink-0">
                            <?= mb_strtoupper(mb_substr($r['first_name'], 0, 1)) ?>
                        </div>
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-primary truncate"><?= h($r['first_name'] . ' ' . $r['last_name']) ?></p>
                            <p class="text-xs text-slate-400"><?= timeAgo($r['created_at']) ?></p>
                        </div>
                    </div>
                    <div class="review-stars"><?= renderStars((int)$r['rating']) ?></div>
                </div>
                <?php if ($r['comment']): ?>
                <p class="text-sm text-on-surface-variant italic leading-relaxed mt-2 break-words">"<?= h($r['comment']) ?>"</p>
                <?php endif; ?>
                <p class="text-xs text-slate-400 mt-2 flex items-center gap-1 min-w-0">
                    <span class="material-symbols-outlined text-sm flex-shrink-0">folder</span>
                    <span class="truncate"><?= h($r['project_title']) ?></span>
                </p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
// Active nav on scroll
const sections = document.querySelectorAll('section[id]');
const navLinks  = document.querySelectorAll('.nav-link-sidebar');
const observer  = new IntersectionObserver(entries => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            navLinks.forEach(l => l.classList.remove('active'));
            const active = document.querySelector(`.nav-link-sidebar[href="#${entry.target.id}"]`);
            if (active) active.classList.add('active');
        }
    });
}, { rootMargin: '-30% 0px -60% 0px' });
sections.forEach(s => observer.observe(s));

// Modales
function openModal(id) {
    const el = document.getElementById(id);
    if (!el) return;
    el.classList.add('open');
    document.body.style.overflow = 'hidden';
}

function closeModal(id) {
    const el = document.getElementById(id);
    if (!el) return;
    el.classList.remove('open');
    document.body.style.overflow = '';
}

function closeModalOnOverlay(event, id) {
    if (event.target === document.getElementById(id)) closeModal(id);
}

document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-overlay.open').forEach(m => {
            m.classList.remove('open');
            document.body.style.overflow = '';
        });
    }
});
</script>

<?php
$appLayout = true;
require_once '../../includes/footer.php';
?>