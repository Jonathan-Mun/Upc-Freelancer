<?php
// ============================================================
// UPC FREELANCE — Voir un profil freelancer
// /var/www/html/upc_freelance/app/profile/freelancer-profile.php
// ============================================================

require_once '/var/www/html/upc_freelance/includes/middleware.php';
require_once '/var/www/html/upc_freelance/includes/auth.php';
require_once '/var/www/html/upc_freelance/includes/functions.php';
require_once '/var/www/html/upc_freelance/includes/db.php';

$pdo    = getDB();
$userId = (int)($_GET['id'] ?? 0);
if (!$userId) redirect('/var/www/html/upc_freelance/app/projects/list.php');

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
if (!$freelancer) { http_response_code(404); die('Profil introuvable.'); }

$skills = $freelancer['skills'] ? json_decode($freelancer['skills'], true) : [];

// Projets complétés
$stmt = $pdo->prepare('
    SELECT ct.*, p.title AS project_title, p.id AS project_id,
           u.first_name AS client_fname, u.last_name AS client_lname
    FROM contracts ct
    JOIN projects p ON p.id = ct.project_id
    JOIN users u    ON u.id = ct.client_id
    WHERE ct.freelancer_id = ? AND ct.status = "completed"
    ORDER BY ct.completed_at DESC LIMIT 6
');
$stmt->execute([$userId]);
$completedContracts = $stmt->fetchAll();

// Avis reçus
$stmt = $pdo->prepare('
    SELECT r.*, u.first_name, u.last_name, u.avatar, p.title AS project_title
    FROM reviews r
    JOIN users u ON u.id = r.reviewer_id
    JOIN contracts ct ON ct.id = r.contract_id
    JOIN projects p   ON p.id  = ct.project_id
    WHERE r.reviewed_id = ?
    ORDER BY r.created_at DESC LIMIT 10
');
$stmt->execute([$userId]);
$reviews = $stmt->fetchAll();

$currentUser = currentUser();
$isOwn       = $currentUser && $currentUser['id'] === $userId;

$pageTitle = h($freelancer['first_name'] . ' ' . $freelancer['last_name']) . ' — UPC Freelance';
$appLayout = true;
require_once '/var/www/html/upc_freelance/includes/header.php';
?>

<!-- En-tête profil -->
<div class="bg-white rounded-2xl border border-slate-100 p-6 custom-shadow-low mb-6">
    <div class="flex flex-col md:flex-row gap-6">

        <!-- Avatar -->
        <div class="flex-shrink-0">
            <?php if ($freelancer['avatar']): ?>
            <img src="/upc_freelance/storage/<?= h($freelancer['avatar']) ?>" alt="Avatar"
                 class="w-24 h-24 rounded-2xl object-cover border-2 border-slate-200"/>
            <?php else: ?>
            <div class="w-24 h-24 rounded-2xl bg-primary/10 flex items-center justify-center text-4xl font-bold text-primary border-2 border-slate-200">
                <?= mb_substr($freelancer['first_name'], 0, 1) ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Infos -->
        <div class="flex-1">
            <div class="flex items-start justify-between gap-4 flex-wrap">
                <div>
                    <h1 class="text-2xl font-bold text-primary">
                        <?= h($freelancer['first_name'] . ' ' . $freelancer['last_name']) ?>
                        <?php if ($freelancer['is_verified']): ?>
                        <span class="inline-block ml-1 text-secondary" title="Profil vérifié">
                            <span class="material-symbols-outlined text-xl align-middle" style="font-variation-settings:'FILL' 1">verified</span>
                        </span>
                        <?php endif; ?>
                    </h1>
                    <?php if ($freelancer['title']): ?>
                    <p class="text-on-surface-variant mt-0.5"><?= h($freelancer['title']) ?></p>
                    <?php endif; ?>
                    <?php if ($freelancer['university']): ?>
                    <p class="text-sm text-slate-400 flex items-center gap-1 mt-1">
                        <span class="material-symbols-outlined text-base">school</span>
                        <?= h($freelancer['university']) ?>
                        <?= $freelancer['field_of_study'] ? ' · ' . h($freelancer['field_of_study']) : '' ?>
                    </p>
                    <?php endif; ?>
                </div>

                <div class="flex flex-col items-end gap-2">
                    <?php
                    $availColors = ['available'=>'green','busy'=>'amber','unavailable'=>'red'];
                    $availLabels = ['available'=>'Disponible','busy'=>'Occupé','unavailable'=>'Indisponible'];
                    $av  = $freelancer['availability'] ?? 'available';
                    $avc = $availColors[$av] ?? 'gray';
                    $avl = $availLabels[$av] ?? $av;
                    ?>
                    <span class="inline-flex items-center gap-1.5 text-sm bg-<?= $avc ?>-100 text-<?= $avc ?>-700 px-3 py-1.5 rounded-full font-medium">
                        <span class="w-2 h-2 bg-<?= $avc ?>-500 rounded-full"></span>
                        <?= $avl ?>
                    </span>
                    <?php if ($freelancer['hourly_rate']): ?>
                    <p class="text-sm font-bold text-secondary"><?= money((float)$freelancer['hourly_rate']) ?>/h</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Stats rapides -->
            <div class="flex flex-wrap gap-6 mt-4 text-sm">
                <?php if ($freelancer['rating']): ?>
                <div class="flex items-center gap-1.5">
                    <?= renderStars((float)$freelancer['rating']) ?>
                    <span class="font-bold text-primary"><?= number_format($freelancer['rating'], 1) ?></span>
                    <span class="text-slate-400">(<?= $freelancer['total_reviews'] ?> avis)</span>
                </div>
                <?php endif; ?>
                <div class="flex items-center gap-1 text-slate-500">
                    <span class="material-symbols-outlined text-base">task_alt</span>
                    <?= count($completedContracts) ?> mission<?= count($completedContracts) > 1 ? 's' : '' ?> complétée<?= count($completedContracts) > 1 ? 's' : '' ?>
                </div>
                <?php if ($freelancer['total_earned'] > 0): ?>
                <div class="flex items-center gap-1 text-slate-500">
                    <span class="material-symbols-outlined text-base">payments</span>
                    <?= money((float)$freelancer['total_earned']) ?> gagnés
                </div>
                <?php endif; ?>
            </div>

            <!-- Bio -->
            <?php if ($freelancer['bio']): ?>
            <p class="text-on-surface-variant mt-4 leading-relaxed text-sm max-w-2xl">
                <?= nl2br(h($freelancer['bio'])) ?>
            </p>
            <?php endif; ?>

            <!-- Liens externes -->
            <div class="flex gap-3 mt-4">
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
                <?php if ($isOwn): ?>
                <a href="/upc_freelance/app/profile/edit.php"
                   class="inline-flex items-center gap-1.5 text-xs bg-primary text-white px-3 py-1.5 rounded-full hover:opacity-90 transition-opacity">
                    <span class="material-symbols-outlined text-sm">edit</span> Modifier
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 space-y-6">

        <!-- Compétences -->
        <?php if (!empty($skills)): ?>
        <div class="bg-white rounded-2xl border border-slate-100 p-6 custom-shadow-low">
            <h2 class="font-semibold text-primary mb-4">Compétences</h2>
            <div class="flex flex-wrap gap-2">
                <?php foreach ($skills as $skill): ?>
                <span class="bg-surface-container text-secondary text-sm px-3 py-1.5 rounded-full font-medium">
                    <?= h($skill) ?>
                </span>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Missions complétées -->
        <?php if (!empty($completedContracts)): ?>
        <div class="bg-white rounded-2xl border border-slate-100 p-6 custom-shadow-low">
            <h2 class="font-semibold text-primary mb-4">Missions complétées</h2>
            <div class="space-y-3">
                <?php foreach ($completedContracts as $ct): ?>
                <div class="flex items-center gap-3 p-3 rounded-xl bg-surface-container-low">
                    <span class="material-symbols-outlined text-green-500">task_alt</span>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-primary truncate"><?= h($ct['project_title']) ?></p>
                        <p class="text-xs text-slate-400">par <?= h($ct['client_fname'] . ' ' . $ct['client_lname']) ?> · <?= formatDate($ct['completed_at'] ?? $ct['created_at']) ?></p>
                    </div>
                    <span class="text-sm font-bold text-secondary whitespace-nowrap"><?= money((float)$ct['amount']) ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Avis -->
        <?php if (!empty($reviews)): ?>
        <div class="bg-white rounded-2xl border border-slate-100 p-6 custom-shadow-low">
            <h2 class="font-semibold text-primary mb-5">Avis clients (<?= count($reviews) ?>)</h2>
            <div class="space-y-4">
                <?php foreach ($reviews as $r): ?>
                <div class="border border-slate-100 rounded-xl p-4">
                    <div class="flex items-start justify-between mb-2">
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-8 rounded-full bg-primary/10 flex items-center justify-center text-xs font-bold text-primary">
                                <?= mb_substr($r['first_name'], 0, 1) ?>
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-primary"><?= h($r['first_name'] . ' ' . $r['last_name']) ?></p>
                                <p class="text-xs text-slate-400"><?= timeAgo($r['created_at']) ?></p>
                            </div>
                        </div>
                        <?= renderStars((int)$r['rating']) ?>
                    </div>
                    <?php if ($r['comment']): ?>
                    <p class="text-sm text-on-surface-variant italic">"<?= h($r['comment']) ?>"</p>
                    <?php endif; ?>
                    <p class="text-xs text-slate-400 mt-2">Projet : <?= h($r['project_title']) ?></p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Sidebar -->
    <div class="space-y-5">
        <?php if (!$isOwn && isLoggedIn() && currentUser()['role'] === 'client'): ?>
        <div class="bg-white rounded-2xl border border-slate-100 p-5 custom-shadow-low">
            <h3 class="font-semibold text-primary mb-3">Contacter ce freelancer</h3>
            <p class="text-sm text-on-surface-variant mb-4">Publiez un projet et sélectionnez ce freelancer après sa candidature.</p>
            <a href="/upc_freelance/app/projects/create.php"
               class="block w-full text-center bg-primary text-white font-button text-button py-3 rounded-xl hover:opacity-90 transition-opacity active:scale-95">
                Publier un projet
            </a>
        </div>
        <?php endif; ?>

        <!-- Stats card -->
        <div class="bg-white rounded-2xl border border-slate-100 p-5 custom-shadow-low">
            <h3 class="font-semibold text-primary mb-4">Statistiques</h3>
            <div class="space-y-3">
                <div class="flex justify-between items-center">
                    <span class="text-sm text-on-surface-variant">Missions</span>
                    <span class="font-bold text-primary"><?= count($completedContracts) ?></span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-sm text-on-surface-variant">Note moyenne</span>
                    <span class="font-bold text-primary">
                        <?= $freelancer['rating'] ? number_format($freelancer['rating'], 1) . ' / 5' : 'N/A' ?>
                    </span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-sm text-on-surface-variant">Membre depuis</span>
                    <span class="text-sm text-primary"><?= formatDate($freelancer['created_at']) ?></span>
                </div>
                <?php if ($freelancer['field_of_study']): ?>
                <div class="flex justify-between items-center">
                    <span class="text-sm text-on-surface-variant">Filière</span>
                    <span class="text-sm font-medium text-primary"><?= h($freelancer['field_of_study']) ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
$appLayout = true;
require_once '/var/www/html/upc_freelance/includes/footer.php';
?>
