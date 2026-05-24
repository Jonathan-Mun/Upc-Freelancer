<?php
// ============================================================
// UPC FREELANCE — Détails d'un projet
// /var/www/html/upc_freelance/app/projects/details.php
// ============================================================

require_once '/var/www/html/upc_freelance/includes/middleware.php';
require_once '/var/www/html/upc_freelance/includes/auth.php';
require_once '/var/www/html/upc_freelance/includes/functions.php';
require_once '/var/www/html/upc_freelance/includes/db.php';

$pdo       = getDB();
$projectId = (int)($_GET['id'] ?? 0);
if (!$projectId) { redirect('/var/www/html/upc_freelance/app/projects/list.php'); }

$project = $pdo->prepare('
    SELECT p.*, c.name AS category_name, c.icon AS category_icon,
           u.first_name, u.last_name, u.avatar, u.university,
           cp.company_name, cp.rating AS client_rating, cp.total_reviews
    FROM projects p
    JOIN users u ON u.id = p.client_id
    LEFT JOIN categories c  ON c.id  = p.category_id
    LEFT JOIN client_profiles cp ON cp.user_id = u.id
    WHERE p.id = ?
');
$project->execute([$projectId]);
$project = $project->fetch();
if (!$project) { http_response_code(404); die('Projet introuvable.'); }

// Incrémenter vues
$pdo->prepare('UPDATE projects SET views_count = views_count + 1 WHERE id = ?')->execute([$projectId]);

// Compétences
$skills = $project['skills_needed'] ? json_decode($project['skills_needed'], true) : [];

// Postulations (pour le client)
$user      = currentUser();
$isOwner   = $user && $user['id'] === $project['client_id'];
$hasApplied = false;

if ($user && $user['role'] === 'freelancer') {
    $stmt = $pdo->prepare('SELECT id FROM postulations WHERE project_id = ? AND freelancer_id = ?');
    $stmt->execute([$projectId, $user['id']]);
    $hasApplied = (bool)$stmt->fetch();
}

$postulations = [];
if ($isOwner) {
    $stmt = $pdo->prepare('
        SELECT po.*, u.first_name, u.last_name, u.avatar, u.university,
               fp.title AS freelancer_title, fp.rating, fp.total_reviews
        FROM postulations po
        JOIN users u ON u.id = po.freelancer_id
        LEFT JOIN freelancer_profiles fp ON fp.user_id = u.id
        WHERE po.project_id = ?
        ORDER BY po.created_at DESC
    ');
    $stmt->execute([$projectId]);
    $postulations = $stmt->fetchAll();
}

$pageTitle = h($project['title']) . ' — UPC Freelance';
$appLayout = true;
require_once '/var/www/html/upc_freelance/includes/header.php';
?>

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

        <!-- Postulations (pour le client) -->
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
                    <div class="w-10 h-10 rounded-full bg-primary/10 flex items-center justify-center font-bold text-primary flex-shrink-0">
                        <?= mb_substr($po['first_name'], 0, 1) ?>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between">
                            <p class="font-semibold text-primary text-sm"><?= h($po['first_name'] . ' ' . $po['last_name']) ?></p>
                            <span class="text-sm font-bold text-secondary"><?= money((float)$po['proposed_price']) ?></span>
                        </div>
                        <p class="text-xs text-slate-400 mb-2"><?= h($po['university'] ?? '') ?> · <?= $po['proposed_days'] ? $po['proposed_days'] . ' jours' : '' ?></p>
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
        <?php if ($user && $user['role'] === 'freelancer' && $project['status'] === 'open'): ?>
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
                        <label class="block text-sm font-medium text-primary mb-1.5">Mon tarif (XOF) <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-xs text-slate-400">CFA</span>
                            <input type="number" name="proposed_price" required min="0" placeholder="50000"
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

    <!-- ── Sidebar infos ─────────────────────────────────── -->
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
                    <span class="text-sm font-medium text-primary">
                        <?= $pdo->prepare('SELECT COUNT(*) FROM postulations WHERE project_id = ?') && ($st = $pdo->prepare('SELECT COUNT(*) FROM postulations WHERE project_id = ?')) && $st->execute([$projectId]) ? (int)$st->fetchColumn() : 0 ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Profil client -->
        <div class="bg-white rounded-2xl border border-slate-100 p-5 custom-shadow-low">
            <h3 class="font-semibold text-primary mb-4">À propos du client</h3>
            <div class="flex items-center gap-3 mb-4">
                <div class="w-12 h-12 rounded-full bg-primary/10 flex items-center justify-center font-bold text-primary text-lg flex-shrink-0">
                    <?= mb_substr($project['first_name'], 0, 1) ?>
                </div>
                <div>
                    <p class="font-semibold text-primary text-sm"><?= h($project['first_name'] . ' ' . $project['last_name']) ?></p>
                    <?php if ($project['company_name']): ?>
                    <p class="text-xs text-on-surface-variant"><?= h($project['company_name']) ?></p>
                    <?php endif; ?>
                    <?php if ($project['university']): ?>
                    <p class="text-xs text-slate-400"><?= h($project['university']) ?></p>
                    <?php endif; ?>
                </div>
            </div>
            <?php if ($project['client_rating']): ?>
            <div class="flex items-center gap-2 mb-3">
                <?= renderStars((float)$project['client_rating']) ?>
                <span class="text-xs text-slate-400"><?= number_format($project['client_rating'], 1) ?> (<?= $project['total_reviews'] ?> avis)</span>
            </div>
            <?php endif; ?>
            <a href="/upc_freelance/app/profile/client-profile.php?id=<?= $project['client_id'] ?>"
               class="block w-full text-center text-sm border border-secondary text-secondary py-2 rounded-xl hover:bg-secondary/5 transition-colors">
                Voir le profil
            </a>
        </div>

        <!-- Actions client -->
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

<?php
$appLayout = true;
require_once '/var/www/html/upc_freelance/includes/footer.php';
?>
