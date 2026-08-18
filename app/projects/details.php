<?php
// ============================================================
// UPC FREELANCE — Détails d'un projet
// /var/www/html/upc_freelance/app/projects/details.php
// ============================================================
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

require_once '../../includes/middleware.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../includes/db.php';

requireLogin();
$pdo       = getDB();
$projectId = (int)($_GET['id'] ?? 0);
if (!$projectId) { redirect('../../app/projects/list.php'); }

// ── Requête principale ─────────────────────────────────────
// CORRECTION : u.university n'existe pas dans users.
// Pour un client, les infos supplémentaires sont dans client_profiles.
$stmt = $pdo->prepare('
    SELECT p.*, c.name AS category_name, c.icon AS category_icon,
           u.first_name, u.last_name, u.avatar,
           cp.company_name, cp.rating AS client_rating, cp.total_reviews
    FROM projects p
    JOIN users u ON u.id = p.client_id
    LEFT JOIN categories c       ON c.id      = p.category_id
    LEFT JOIN client_profiles cp ON cp.user_id = u.id
    WHERE p.id = ?
');
$stmt->execute([$projectId]);
$project = $stmt->fetch();
if (!$project) { http_response_code(404); die('Projet introuvable.'); }

// Incrémenter vues
$pdo->prepare('UPDATE projects SET views_count = views_count + 1 WHERE id = ?')->execute([$projectId]);

// Compétences requises
$skills = $project['skills_needed'] ? json_decode($project['skills_needed'], true) : [];

// Postulations
$user     = currentUser();
$isOwner      = $user && $user['id'] === $project['client_id'];
// Un freelancer ne peut pas postuler sur son propre projet (s'il a les deux rôles)
$isOwnProject = $user && $user['id'] === $project['client_id'];
$hasApplied   = false;

if ($user && $user['role'] === 'freelancer') {
    $stmt = $pdo->prepare('SELECT id FROM postulations WHERE project_id = ? AND freelancer_id = ?');
    $stmt->execute([$projectId, $user['id']]);
    $hasApplied = (bool)$stmt->fetch();
}

$postulations = [];
if ($isOwner) {
    // ── Requête postulations ───────────────────────────────
    // CORRECTION : u.university n'existe pas dans users.
    // On joint freelancer_profiles pour récupérer university et field_of_study.
    $stmt = $pdo->prepare('
        SELECT po.*,
               u.first_name, u.last_name, u.avatar, u.is_verified,
               fp.title AS freelancer_title,
               fp.university, fp.field_of_study,
               fp.rating, fp.total_reviews
        FROM postulations po
        JOIN users u ON u.id = po.freelancer_id
        LEFT JOIN freelancer_profiles fp ON fp.user_id = u.id
        WHERE po.project_id = ?
        ORDER BY po.created_at DESC
    ');
    $stmt->execute([$projectId]);
    $postulations = $stmt->fetchAll();
}

// Nombre de candidatures (sidebar)
$stmtCount = $pdo->prepare('SELECT COUNT(*) FROM postulations WHERE project_id = ?');
$stmtCount->execute([$projectId]);
$postulationCount = (int)$stmtCount->fetchColumn();

// 3 derniers postulants (sidebar) — visibles par tous
// On récupère TOUS les postulants pour la popup (pas de LIMIT)
$stmt = $pdo->prepare('
    SELECT po.id, po.proposed_price, po.proposed_days, po.status, po.created_at,
           po.cover_letter,
           u.id AS freelancer_id, u.first_name, u.last_name, u.avatar, u.is_verified,
           fp.title AS freelancer_title, fp.rating, fp.total_reviews, fp.skills, fp.field_of_study
    FROM postulations po
    JOIN users u ON u.id = po.freelancer_id
    LEFT JOIN freelancer_profiles fp ON fp.user_id = u.id
    WHERE po.project_id = ?
    ORDER BY po.created_at DESC
');
$stmt->execute([$projectId]);
$allPostulants  = $stmt->fetchAll();
$lastPostulants = array_slice($allPostulants, 0, 3);

$pageTitle = h($project['title']) . ' — UPC Freelance';
$appLayout = true;
require_once '../../includes/header.php';
?>
<br>
<?php renderFlash(); ?>

<!-- Retour -->
<a href="/upc_freelance/app/projects/list.php" class="inline-flex items-center gap-1 text-sm text-secondary hover:underline mb-6">
    <span class="material-symbols-outlined text-base">arrow_back</span> Retour aux projets
</a>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    <!-- ── Contenu principal ─────────────────────────────── -->
    <div class="lg:col-span-2 space-y-5">

        <!-- Header projet -->
        <div class="bg-white rounded-2xl border border-slate-100 p-6 custom-shadow-low">
            <div class="flex items-start justify-between gap-4 mb-4">
                <div>
                    <?php if ($project['category_name']): ?>
                    <span class="inline-flex items-center gap-1 text-xs bg-surface-container text-secondary px-2.5 py-1 rounded-full font-medium mb-3">
                        <span class="material-symbols-outlined text-sm"><?= h($project['category_icon'] ?? 'work') ?></span>
                        <?= h($project['category_name']) ?>
                    </span>
                    <?php endif; ?>
                    <h1 class="text-xl font-bold text-primary leading-snug"><?= h($project['title']) ?></h1>
                    <p class="text-xs text-slate-400 mt-2">
                        Publié <?= timeAgo($project['created_at']) ?> · <?= $project['views_count'] ?> vue(s)
                    </p>
                </div>
                <?php
                $statusColors = ['open'=>'green','in_progress'=>'blue','completed'=>'gray','cancelled'=>'red'];
                $statusLabels = ['open'=>'Ouvert','in_progress'=>'En cours','completed'=>'Terminé','cancelled'=>'Annulé'];
                $sc = $statusColors[$project['status']] ?? 'gray';
                $sl = $statusLabels[$project['status']] ?? $project['status'];
                ?>
                <span class="inline-block text-sm bg-<?= $sc ?>-100 text-<?= $sc ?>-700 px-3 py-1.5 rounded-full font-medium whitespace-nowrap">
                    <?= $sl ?>
                </span>
            </div>

            <!-- Description -->
            <div class="prose prose-sm max-w-none text-on-surface-variant leading-relaxed">
                <?= nl2br(h($project['description'])) ?>
            </div>

            <!-- Skills -->
            <?php if (!empty($skills)): ?>
            <div class="mt-5 pt-5 border-t border-slate-100">
                <p class="text-xs font-medium text-on-surface-variant mb-2 uppercase tracking-wide">Compétences requises</p>
                <div class="flex flex-wrap gap-2">
                    <?php foreach ($skills as $skill): ?>
                    <span class="bg-surface-container text-secondary text-xs px-3 py-1 rounded-full font-medium">
                        <?= h($skill) ?>
                    </span>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Postulations reçues (client propriétaire) -->
        <?php if ($isOwner && !empty($postulations)): ?>
        <div class="bg-white rounded-2xl border border-slate-100 overflow-hidden custom-shadow-low">
            <div class="flex justify-between items-center p-5 border-b border-slate-100">
                <h2 class="font-semibold text-primary">
                    Candidatures reçues
                    <span class="ml-2 text-xs bg-secondary text-white px-2 py-0.5 rounded-full"><?= count($postulations) ?></span>
                </h2>
                <a href="/upc_freelance/app/postulations/received.php?project_id=<?= $projectId ?>" class="text-xs text-secondary hover:underline">
                    Gérer toutes →
                </a>
            </div>
            <div class="divide-y divide-slate-50">
                <?php foreach (array_slice($postulations, 0, 5) as $po): ?>
                <div class="p-5 flex items-start gap-4">
                    <?= renderAvatar($po['avatar'] ?? null, $po['first_name'], $po['last_name'] ?? '', (bool)($po['is_verified'] ?? false), 'w-10 h-10', 'rounded-full') ?>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between gap-2">
                            <p class="font-semibold text-primary text-sm"><?= h($po['first_name'] . ' ' . $po['last_name']) ?></p>
                            <span class="text-sm font-bold text-secondary whitespace-nowrap"><?= money((float)$po['proposed_price']) ?></span>
                        </div>
                        <!-- CORRECTION : university vient maintenant de fp (freelancer_profiles) -->
                        <p class="text-xs text-slate-400 mb-2">
                            <?= $po['university'] ? h($po['university']) : '' ?>
                            <?= $po['university'] && $po['proposed_days'] ? ' · ' : '' ?>
                            <?= $po['proposed_days'] ? $po['proposed_days'] . ' jours' : '' ?>
                        </p>
                        <p class="text-sm text-on-surface-variant line-clamp-2"><?= h(truncate($po['cover_letter'], 120)) ?></p>
                        <?php if ($po['status'] === 'pending'): ?>
                        <div class="flex gap-2 mt-3">
                            <a href="/upc_freelance/app/postulations/received.php?project_id=<?= $projectId ?>&accept=<?= $po['id'] ?>"
                               class="text-xs bg-green-500 text-white px-3 py-1.5 rounded-lg hover:opacity-90 transition-opacity">
                                ✓ Accepter
                            </a>
                            <a href="/upc_freelance/app/postulations/received.php?project_id=<?= $projectId ?>&reject=<?= $po['id'] ?>"
                               class="text-xs bg-red-50 text-red-600 px-3 py-1.5 rounded-lg hover:bg-red-100 transition-colors">
                                ✕ Refuser
                            </a>
                        </div>
                        <?php else: ?>
                        <span class="inline-block mt-2 text-xs px-2 py-0.5 rounded-full <?= $po['status']==='accepted' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>">
                            <?= $po['status'] === 'accepted' ? 'Accepté' : 'Refusé' ?>
                        </span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Formulaire postulation (freelancer) -->
        <?php if ($user && $user['role'] === 'freelancer' && $project['status'] === 'open' && !$isOwnProject): ?>
        <div class="bg-white rounded-2xl border border-slate-100 p-6 custom-shadow-low">
            <?php if ($hasApplied): ?>
            <div class="text-center py-6">
                <span class="material-symbols-outlined text-4xl text-green-400 block mb-3">check_circle</span>
                <p class="font-semibold text-primary">Candidature envoyée !</p>
                <p class="text-sm text-on-surface-variant mt-1">Vous avez déjà postulé à ce projet.</p>
                <a href="/upc_freelance/app/postulations/my-applications.php" class="mt-4 inline-block text-sm text-secondary hover:underline">
                    Voir mes candidatures →
                </a>
            </div>
            <?php else: ?>
            <h2 class="font-semibold text-primary mb-5 flex items-center gap-2">
                <span class="material-symbols-outlined text-secondary">send</span>
                Postuler à ce projet
            </h2>
            <form action="/upc_freelance/app/postulations/apply.php" method="POST" class="space-y-4" novalidate>
                <?= csrfField() ?>
                <input type="hidden" name="project_id" value="<?= $projectId ?>"/>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-primary mb-1.5">Mon tarif (USD) <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-xs text-slate-400 font-medium">USD</span>
                            <input type="number" name="proposed_price" required min="0" placeholder="50"
                                   class="w-full pl-12 pr-4 py-3 rounded-xl border border-outline-variant focus:border-secondary focus:ring-2 focus:ring-secondary/20 outline-none text-sm"/>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-primary mb-1.5">Délai estimé (jours)</label>
                        <input type="number" name="proposed_days" min="1" placeholder="7"
                               class="w-full px-4 py-3 rounded-xl border border-outline-variant focus:border-secondary focus:ring-2 focus:ring-secondary/20 outline-none text-sm"/>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-primary mb-1.5">Message de motivation <span class="text-red-500">*</span></label>
                    <textarea name="cover_letter" rows="5" required
                              placeholder="Expliquez pourquoi vous êtes le meilleur candidat pour ce projet. Mentionnez vos expériences similaires..."
                              class="w-full px-4 py-3 rounded-xl border border-outline-variant focus:border-secondary focus:ring-2 focus:ring-secondary/20 outline-none text-sm resize-y"></textarea>
                </div>
                <button type="submit"
                        class="w-full bg-secondary text-white font-button text-button py-3.5 rounded-xl hover:opacity-90 transition-opacity active:scale-95 shadow-sm">
                    <span class="material-symbols-outlined align-middle mr-1">send</span>
                    Envoyer ma candidature
                </button>
            </form>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- ── Sidebar ────────────────────────────────────────── -->
    <div class="space-y-5">

        <!-- Budget & deadline -->
        <div class="bg-white rounded-2xl border border-slate-100 p-5 custom-shadow-low">
            <h3 class="font-semibold text-primary mb-4">Détails du projet</h3>
            <div class="space-y-3">
                <?php if ($project['budget_min'] || $project['budget_max']): ?>
                <div class="flex items-center justify-between">
                    <span class="text-sm text-on-surface-variant flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-base text-slate-400">payments</span> Budget
                    </span>
                    <span class="text-sm font-bold text-secondary">
                        <?php
                        if ($project['budget_min'] && $project['budget_max'])
                            echo money((float)$project['budget_min']) . ' – ' . money((float)$project['budget_max']);
                        else
                            echo money((float)($project['budget_max'] ?? $project['budget_min']));
                        ?>
                    </span>
                </div>
                <?php endif; ?>
                <?php if ($project['deadline']): ?>
                <div class="flex items-center justify-between">
                    <span class="text-sm text-on-surface-variant flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-base text-slate-400">calendar_today</span> Délai
                    </span>
                    <span class="text-sm font-medium text-primary"><?= formatDate($project['deadline']) ?></span>
                </div>
                <?php endif; ?>
                <div class="flex items-center justify-between">
                    <span class="text-sm text-on-surface-variant flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-base text-slate-400">group</span> Candidatures
                    </span>
                    <span class="text-sm font-medium text-primary"><?= $postulationCount ?></span>
                </div>
            </div>
        </div>

        <!-- Profil client -->
        <div class="bg-white rounded-2xl border border-slate-100 p-5 custom-shadow-low">
            <h3 class="font-semibold text-primary mb-4">À propos du client</h3>
            <div class="flex items-center gap-3 mb-4">
                <?= renderAvatar($project['avatar'] ?? null, $project['first_name'], $project['last_name'], false, 'w-12 h-12', 'rounded-full') ?>
                <div>
                    <p class="font-semibold text-primary text-sm"><?= h($project['first_name'] . ' ' . $project['last_name']) ?></p>
                    <?php if ($project['company_name']): ?>
                    <p class="text-xs text-on-surface-variant"><?= h($project['company_name']) ?></p>
                    <?php endif; ?>
                </div>
            </div>
            <?php if ($project['client_rating']): ?>
            <div class="flex items-center gap-2 mb-3">
                <?= renderStars((float)$project['client_rating']) ?>
                <span class="text-xs text-slate-400">
                    <?= number_format($project['client_rating'], 1) ?> (<?= $project['total_reviews'] ?> avis)
                </span>
            </div>
            <?php endif; ?>
            <a href="/upc_freelance/app/profile/client-profile.php?id=<?= $project['client_id'] ?>"
               class="block w-full text-center text-sm border border-secondary text-secondary py-2 rounded-xl hover:bg-secondary/5 transition-colors">
                Voir le profil
            </a>
        </div>

        <!-- 3 derniers postulants (sidebar) -->
        <?php if (!empty($lastPostulants)): ?>
        <div class="bg-white rounded-2xl border border-slate-100 p-5 custom-shadow-low">
            <div class="flex items-center justify-between mb-4">
                <h3 class="font-semibold text-primary flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-secondary text-base">group</span>
                    Candidatures
                    <span class="ml-1 bg-secondary text-white text-[10px] font-bold px-1.5 py-0.5 rounded-full">
                        <?= $postulationCount ?>
                    </span>
                </h3>
                <?php if ($postulationCount > 3): ?>
                <button onclick="openPopup()" class="text-xs text-secondary hover:underline font-medium">
                    Voir toutes →
                </button>
                <?php endif; ?>
            </div>

            <!-- 3 derniers -->
            <div class="space-y-3">
                <?php foreach ($lastPostulants as $lp):
                    $lpColors = [
                        'pending'   => ['bg-amber-100','text-amber-700','En attente'],
                        'accepted'  => ['bg-green-100','text-green-700','Accepté'],
                        'rejected'  => ['bg-red-100','text-red-600','Refusé'],
                        'withdrawn' => ['bg-slate-100','text-slate-500','Retiré'],
                    ];
                    $lpStatus = $lpColors[$lp['status']] ?? ['bg-slate-100','text-slate-500',$lp['status']];
                ?>
                <a href="/upc_freelance/app/profile/freelancer-profile.php?id=<?= $lp['freelancer_id'] ?>"
                   class="flex items-center gap-3 p-3 rounded-xl bg-slate-50 border border-slate-100
                          hover:border-secondary/40 hover:bg-blue-50/30 transition-all cursor-pointer">
                    <?= renderAvatar($lp['avatar'] ?? null, $lp['first_name'], $lp['last_name'], (bool)($lp['is_verified'] ?? false), 'w-9 h-9', 'rounded-full') ?>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold text-primary truncate flex items-center gap-1">
                            <?= h($lp['first_name'] . ' ' . $lp['last_name']) ?>
                            <?php if ($lp['is_verified']): ?>
                            <span class="material-symbols-outlined text-secondary"
                                  style="font-size:13px;font-variation-settings:'FILL' 1">verified</span>
                            <?php endif; ?>
                        </p>
                        <p class="text-xs text-slate-400 truncate">
                            <?= $lp['freelancer_title'] ? h($lp['freelancer_title']) : 'Freelancer' ?>
                        </p>
                        <?php if ($lp['rating']): ?>
                        <div class="flex items-center gap-0.5 mt-0.5"><?= renderStars((float)$lp['rating']) ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="text-right flex-shrink-0">
                        <p class="text-xs font-bold text-secondary"><?= money((float)$lp['proposed_price']) ?></p>
                        <?php if ($lp['proposed_days']): ?>
                        <p class="text-[10px] text-slate-400"><?= $lp['proposed_days'] ?>j</p>
                        <?php endif; ?>
                        <?php if ($isOwner): ?>
                        <span class="inline-block mt-1 text-[10px] font-semibold px-1.5 py-0.5 rounded-full
                                     <?= $lpStatus[0] ?> <?= $lpStatus[1] ?>">
                            <?= $lpStatus[2] ?>
                        </span>
                        <?php endif; ?>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>

            <!-- Bouton ouvrir popup -->
            <button onclick="openPopup()"
                    class="mt-4 flex items-center justify-center gap-1.5 w-full py-2.5 rounded-xl
                           border border-secondary text-secondary text-sm font-semibold
                           hover:bg-secondary/5 transition-colors active:scale-95">
                <span class="material-symbols-outlined text-base">people</span>
                Voir les <?= $postulationCount ?> candidature<?= $postulationCount > 1 ? 's' : '' ?>
            </button>
        </div>
        <?php endif; ?>

        <!-- ══ POPUP : tous les postulants ══════════════════ -->
        <?php if (!empty($allPostulants)): ?>
        <div id="popup-postulants"
             class="fixed inset-0 z-50 hidden"
             onclick="if(event.target===this) closePopup()">
            <!-- Backdrop -->
            <div class="absolute inset-0 bg-black/50 backdrop-blur-sm"></div>

            <!-- Panel -->
            <div class="absolute inset-y-0 right-0 w-full max-w-lg bg-white shadow-2xl flex flex-col"
                 style="animation: slideIn 0.25s ease">
                <!-- Header popup -->
                <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100">
                    <div>
                        <h2 class="font-bold text-primary text-lg">Candidatures</h2>
                        <p class="text-xs text-slate-400">
                            <?= $postulationCount ?> freelancer<?= $postulationCount > 1 ? 's' : '' ?>
                            ont postulé à ce projet
                        </p>
                    </div>
                    <button onclick="closePopup()"
                            class="p-2 rounded-xl hover:bg-slate-100 transition-colors text-slate-500">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>

                <!-- Liste scrollable -->
                <div class="flex-1 overflow-y-auto px-4 py-4 space-y-3">
                    <?php foreach ($allPostulants as $ap):
                        $apColors = [
                            'pending'   => ['#fef3c7','#b45309','En attente'],
                            'accepted'  => ['#dcfce7','#15803d','Accepté'],
                            'rejected'  => ['#fee2e2','#dc2626','Refusé'],
                            'withdrawn' => ['#f1f5f9','#64748b','Retiré'],
                        ];
                        $apStatus = $apColors[$ap['status']] ?? ['#f1f5f9','#64748b',$ap['status']];
                        $apSkills = $ap['skills'] ? json_decode($ap['skills'], true) : [];
                    ?>
                    <a href="/upc_freelance/app/profile/freelancer-profile.php?id=<?= $ap['freelancer_id'] ?>"
                       class="block rounded-2xl border border-slate-100 bg-white p-4
                              hover:border-secondary/40 hover:shadow-md transition-all group">

                        <!-- Ligne 1 : avatar + nom + badge vérifié + prix -->
                        <div class="flex items-start gap-3 mb-3">
                            <?= renderAvatar($ap['avatar'] ?? null, $ap['first_name'], $ap['last_name'], (bool)($ap['is_verified'] ?? false), 'w-11 h-11', 'rounded-xl') ?>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-1.5 flex-wrap">
                                    <p class="font-bold text-primary text-sm group-hover:text-secondary transition-colors">
                                        <?= h($ap['first_name'] . ' ' . $ap['last_name']) ?>
                                    </p>
                                    <?php if ($ap['is_verified']): ?>
                                    <span class="inline-flex items-center gap-0.5 bg-blue-50 text-secondary
                                                 text-[10px] font-bold px-1.5 py-0.5 rounded-full border border-blue-100">
                                        <span class="material-symbols-outlined"
                                              style="font-size:11px;font-variation-settings:'FILL' 1">verified</span>
                                        Vérifié
                                    </span>
                                    <?php endif; ?>
                                </div>
                                <p class="text-xs text-slate-400 truncate">
                                    <?= $ap['freelancer_title'] ? h($ap['freelancer_title']) : 'Freelancer' ?>
                                    <?= $ap['field_of_study'] ? ' · ' . h($ap['field_of_study']) : '' ?>
                                </p>
                                <?php if ($ap['rating']): ?>
                                <div class="flex items-center gap-1 mt-0.5">
                                    <?= renderStars((float)$ap['rating']) ?>
                                    <span class="text-[10px] text-slate-400">
                                        (<?= $ap['total_reviews'] ?> avis)
                                    </span>
                                </div>
                                <?php endif; ?>
                            </div>
                            <!-- Prix + délai -->
                            <div class="text-right flex-shrink-0">
                                <p class="font-bold text-secondary text-sm"><?= money((float)$ap['proposed_price']) ?></p>
                                <?php if ($ap['proposed_days']): ?>
                                <p class="text-[10px] text-slate-400"><?= $ap['proposed_days'] ?> jours</p>
                                <?php endif; ?>
                                <?php if ($isOwner): ?>
                                <span class="inline-block mt-1 text-[10px] font-semibold px-2 py-0.5 rounded-full"
                                      style="background:<?= $apStatus[0] ?>;color:<?= $apStatus[1] ?>">
                                    <?= $apStatus[2] ?>
                                </span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Skills -->
                        <?php if (!empty($apSkills)): ?>
                        <div class="flex flex-wrap gap-1.5 mb-2">
                            <?php foreach (array_slice($apSkills, 0, 4) as $sk): ?>
                            <span class="text-[10px] font-semibold bg-surface-container text-secondary
                                         px-2 py-0.5 rounded-full">
                                <?= h($sk) ?>
                            </span>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>

                        <!-- Extrait du message -->
                        <?php if ($ap['cover_letter']): ?>
                        <p class="text-xs text-slate-500 italic border-l-2 border-secondary/30 pl-2.5 leading-relaxed line-clamp-2">
                            "<?= h(truncate($ap['cover_letter'], 140)) ?>"
                        </p>
                        <?php endif; ?>

                        <!-- Voir profil -->
                        <div class="mt-3 flex items-center gap-1 text-xs font-semibold text-secondary
                                    group-hover:gap-2 transition-all">
                            <span class="material-symbols-outlined text-sm">person</span>
                            Voir le profil
                            <span class="material-symbols-outlined text-sm">arrow_forward</span>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>

                <?php if ($isOwner): ?>
                <!-- Footer owner : lien vers received.php -->
                <div class="px-4 py-4 border-t border-slate-100">
                    <a href="/upc_freelance/app/postulations/received.php?project_id=<?= $projectId ?>"
                       class="flex items-center justify-center gap-2 w-full py-3 rounded-xl
                              bg-primary text-white text-sm font-semibold
                              hover:opacity-90 transition-opacity active:scale-95">
                        <span class="material-symbols-outlined text-base">manage_accounts</span>
                        Gérer les candidatures
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <style>
        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to   { transform: translateX(0);    opacity: 1; }
        }
        @keyframes slideOut {
            from { transform: translateX(0);    opacity: 1; }
            to   { transform: translateX(100%); opacity: 0; }
        }
        #popup-postulants.closing > div:last-child {
            animation: slideOut 0.2s ease forwards;
        }
        </style>
        <?php endif; ?>

        <!-- Actions client (propriétaire) -->
        <?php if ($isOwner): ?>
        <div class="bg-white rounded-2xl border border-slate-100 p-5 custom-shadow-low space-y-2">
            <a href="/upc_freelance/app/projects/edit.php?id=<?= $projectId ?>"
               class="flex items-center gap-2 w-full px-4 py-2.5 rounded-xl border border-slate-200 text-sm hover:border-secondary hover:text-secondary transition-colors">
                <span class="material-symbols-outlined text-base">edit</span> Modifier le projet
            </a>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
// ── Copier le lien de partage ─────────────────────────────
function copyShareLink() {
    const input = document.getElementById('share-link');
    const btn   = document.getElementById('copy-btn');
    if (!input) return;
    input.select();
    navigator.clipboard.writeText(input.value).then(() => {
        btn.innerHTML = '<span class="material-symbols-outlined text-sm">check</span> Copié !';
        btn.style.color = '#16a34a';
        setTimeout(() => {
            btn.innerHTML = '<span class="material-symbols-outlined text-sm">content_copy</span> Copier';
            btn.style.color = '';
        }, 2000);
    });
}

// ── Popup candidatures ────────────────────────────────────
function openPopup() {
    const popup = document.getElementById('popup-postulants');
    if (!popup) return;
    popup.classList.remove('hidden', 'closing');
    document.body.style.overflow = 'hidden';
    document.addEventListener('keydown', onEscapePopup);
}

function closePopup() {
    const popup = document.getElementById('popup-postulants');
    if (!popup) return;
    popup.classList.add('closing');
    setTimeout(() => {
        popup.classList.add('hidden');
        popup.classList.remove('closing');
        document.body.style.overflow = '';
    }, 220);
    document.removeEventListener('keydown', onEscapePopup);
}

function onEscapePopup(e) {
    if (e.key === 'Escape') closePopup();
}
</script>

<?php
$appLayout = true;
require_once '../../includes/footer.php';
?>