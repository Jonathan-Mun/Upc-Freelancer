<?php
// ============================================================
// UPC FREELANCE — Voir un profil client
// /var/www/html/upc_freelance/app/profile/client-profile.php
// ============================================================

require_once '../../includes/middleware.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../includes/db.php';

$pdo    = getDB();
$userId = (int)($_GET['id'] ?? 0);
if (!$userId) redirect('../../app/projects/list.php');

$stmt = $pdo->prepare('
    SELECT u.*, cp.company_name, cp.website, cp.rating, cp.total_reviews, cp.total_spent
    FROM users u
    LEFT JOIN client_profiles cp ON cp.user_id = u.id
    WHERE u.id = ? AND u.role = "client" AND u.is_active = 1
');
$stmt->execute([$userId]);
$client = $stmt->fetch();
if (!$client) { http_response_code(404); die('Profil introuvable.'); }

// Projets publiés
$stmt = $pdo->prepare('
    SELECT p.*, c.name AS category_name,
           (SELECT COUNT(*) FROM postulations WHERE project_id = p.id) AS nb_postulations
    FROM projects p
    LEFT JOIN categories c ON c.id = p.category_id
    WHERE p.client_id = ? AND p.visibility = "public"
    ORDER BY p.created_at DESC LIMIT 6
');
$stmt->execute([$userId]);
$projects = $stmt->fetchAll();

// Avis reçus
$stmt = $pdo->prepare('
    SELECT r.*, u.first_name, u.last_name, p.title AS project_title
    FROM reviews r
    JOIN users u    ON u.id  = r.reviewer_id
    JOIN contracts ct ON ct.id = r.contract_id
    JOIN projects p ON p.id   = ct.project_id
    WHERE r.reviewed_id = ?
    ORDER BY r.created_at DESC LIMIT 6
');
$stmt->execute([$userId]);
$reviews = $stmt->fetchAll();

$currentUser = currentUser();
$isOwn       = $currentUser && $currentUser['id'] === $userId;

$pageTitle = h($client['first_name'] . ' ' . $client['last_name']) . ' — UPC Freelance';
$appLayout = true;
require_once '../../includes/header.php';
?>
<br>
<!-- En-tête -->
<div class="bg-white rounded-2xl border border-slate-100 p-6 custom-shadow-low mb-6">
    <div class="flex flex-col md:flex-row gap-6">

        <!-- Avatar -->
        <div class="flex-shrink-0">
            <?php if ($client['avatar']): ?>
            <img src="/upc_freelance/storage/<?= h($client['avatar']) ?>" alt="Avatar"
                 class="w-24 h-24 rounded-2xl object-cover border-2 border-slate-200"/>
            <?php else: ?>
            <div class="w-24 h-24 rounded-2xl bg-secondary/10 flex items-center justify-center text-4xl font-bold text-secondary border-2 border-slate-200">
                <?= mb_substr($client['first_name'], 0, 1) ?>
            </div>
            <?php endif; ?>
        </div>

        <div class="flex-1">
            <div class="flex items-start justify-between gap-4 flex-wrap">
                <div>
                    <h1 class="text-2xl font-bold text-primary">
                        <?= h($client['first_name'] . ' ' . $client['last_name']) ?>
                        <?php if ($client['is_verified']): ?>
                        <span class="material-symbols-outlined text-xl text-secondary align-middle" style="font-variation-settings:'FILL' 1">verified</span>
                        <?php endif; ?>
                    </h1>
                    <?php if ($client['company_name']): ?>
                    <p class="text-on-surface-variant"><?= h($client['company_name']) ?></p>
                    <?php endif; ?>
                    <?php if ($client['university']): ?>
                    <p class="text-sm text-slate-400 flex items-center gap-1 mt-1">
                        <span class="material-symbols-outlined text-base">school</span>
                        <?= h($client['university']) ?>
                    </p>
                    <?php endif; ?>
                </div>
                <span class="inline-flex items-center gap-1.5 text-sm bg-blue-100 text-blue-700 px-3 py-1.5 rounded-full font-medium">
                    <span class="material-symbols-outlined text-base">business</span> Client
                </span>
            </div>

            <!-- Stats -->
            <div class="flex flex-wrap gap-6 mt-4 text-sm">
                <?php if ($client['rating']): ?>
                <div class="flex items-center gap-1.5">
                    <?= renderStars((float)$client['rating']) ?>
                    <span class="font-bold text-primary"><?= number_format($client['rating'], 1) ?></span>
                    <span class="text-slate-400">(<?= $client['total_reviews'] ?> avis)</span>
                </div>
                <?php endif; ?>
                <div class="flex items-center gap-1 text-slate-500">
                    <span class="material-symbols-outlined text-base">work</span>
                    <?= count($projects) ?> projet<?= count($projects) > 1 ? 's' : '' ?> publié<?= count($projects) > 1 ? 's' : '' ?>
                </div>
            </div>

            <?php if ($client['bio']): ?>
            <p class="text-on-surface-variant mt-4 text-sm leading-relaxed max-w-2xl">
                <?= nl2br(h($client['bio'])) ?>
            </p>
            <?php endif; ?>

            <div class="flex gap-3 mt-4">
                <?php if ($client['website']): ?>
                <a href="<?= h($client['website']) ?>" target="_blank" rel="noopener"
                   class="inline-flex items-center gap-1.5 text-xs text-secondary border border-secondary/30 px-3 py-1.5 rounded-full hover:bg-secondary/5 transition-colors">
                    <span class="material-symbols-outlined text-sm">language</span> Site web
                </a>
                <?php endif; ?>
                <?php if ($isOwn): ?>
                <a href="/upc_freelance/app/profile/edit.php"
                   class="inline-flex items-center gap-1.5 text-xs bg-primary text-white px-3 py-1.5 rounded-full hover:opacity-90">
                    <span class="material-symbols-outlined text-sm">edit</span> Modifier
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 space-y-6">

        <!-- Projets publiés -->
        <?php if (!empty($projects)): ?>
        <div class="bg-white rounded-2xl border border-slate-100 p-6 custom-shadow-low">
            <h2 class="font-semibold text-primary mb-5">Projets publiés</h2>
            <div class="space-y-3">
                <?php foreach ($projects as $p):
                    $sc = ['open'=>'green','in_progress'=>'blue','completed'=>'gray','cancelled'=>'red'][$p['status']] ?? 'gray';
                    $sl = ['open'=>'Ouvert','in_progress'=>'En cours','completed'=>'Terminé','cancelled'=>'Annulé'][$p['status']] ?? $p['status'];
                ?>
                <a href="/upc_freelance/app/projects/details.php?id=<?= $p['id'] ?>"
                   class="flex items-center gap-3 p-4 rounded-xl bg-surface-container-low hover:bg-surface-container transition-colors">
                    <div class="w-10 h-10 rounded-xl bg-white border border-slate-200 flex items-center justify-center flex-shrink-0">
                        <span class="material-symbols-outlined text-secondary">work</span>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold text-primary truncate"><?= h($p['title']) ?></p>
                        <p class="text-xs text-slate-400">
                            <?= h($p['category_name'] ?? 'Général') ?>
                            · <?= $p['nb_postulations'] ?> candidature<?= $p['nb_postulations'] > 1 ? 's' : '' ?>
                        </p>
                    </div>
                    <span class="text-xs bg-<?= $sc ?>-100 text-<?= $sc ?>-700 px-2 py-0.5 rounded-full whitespace-nowrap"><?= $sl ?></span>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Avis -->
        <?php if (!empty($reviews)): ?>
        <div class="bg-white rounded-2xl border border-slate-100 p-6 custom-shadow-low">
            <h2 class="font-semibold text-primary mb-5">Avis reçus (<?= count($reviews) ?>)</h2>
            <div class="space-y-4">
                <?php foreach ($reviews as $r): ?>
                <div class="border border-slate-100 rounded-xl p-4">
                    <div class="flex items-start justify-between mb-2">
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-8 rounded-full bg-secondary/10 flex items-center justify-center text-xs font-bold text-secondary">
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
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Sidebar -->
    <div class="space-y-5">
        <div class="bg-white rounded-2xl border border-slate-100 p-5 custom-shadow-low">
            <h3 class="font-semibold text-primary mb-4">Informations</h3>
            <div class="space-y-3 text-sm">
                <div class="flex justify-between">
                    <span class="text-on-surface-variant">Rôle</span>
                    <span class="font-medium text-primary">Client</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-on-surface-variant">Membre depuis</span>
                    <span class="font-medium text-primary"><?= formatDate($client['created_at']) ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-on-surface-variant">Note</span>
                    <span class="font-bold text-primary"><?= $client['rating'] ? number_format($client['rating'], 1) . '/5' : 'N/A' ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-on-surface-variant">Projets publiés</span>
                    <span class="font-bold text-primary"><?= count($projects) ?></span>
                </div>
            </div>
        </div>

        <?php if (!$isOwn && isLoggedIn() && currentUser()['role'] === 'freelancer'): ?>
        <div class="bg-white rounded-2xl border border-slate-100 p-5 custom-shadow-low">
            <a href="/upc_freelance/app/projects/list.php"
               class="block w-full text-center bg-primary text-white font-button text-button py-3 rounded-xl hover:opacity-90 transition-opacity active:scale-95">
                Voir ses projets
            </a>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php
$appLayout = true;
require_once '../../includes/footer.php';
?>
