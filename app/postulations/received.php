<?php
// ============================================================
// UPC FREELANCE — Candidatures reçues (client)
// ../../app/postulations/received.php
// ============================================================
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

require_once '../../includes/middleware.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../includes/db.php';

requireRole('client');

$user      = currentUser();
$pdo       = getDB();
$projectId = (int)($_GET['project_id'] ?? 0);

// ─── Accept ──────────────────────────────────────────────────
if (isset($_GET['accept'])) {
    $postId = (int)$_GET['accept'];
    $stmt = $pdo->prepare('
        SELECT po.*, p.client_id, p.title AS project_title
        FROM postulations po JOIN projects p ON p.id = po.project_id
        WHERE po.id = ? AND p.client_id = ?
    ');
    $stmt->execute([$postId, $user['id']]);
    $post = $stmt->fetch();

    if ($post) {
        $pdo->prepare('
            INSERT INTO contracts (uuid, project_id, client_id, freelancer_id, postulation_id, amount, start_date, status)
            VALUES (?, ?, ?, ?, ?, ?, CURDATE(), "active")
        ')->execute([
            generateUUID(), $post['project_id'], $user['id'],
            $post['freelancer_id'], $post['id'], $post['proposed_price'],
        ]);
        $contractId = (int)$pdo->lastInsertId();

        $pdo->prepare('UPDATE postulations SET status = "accepted" WHERE id = ?')->execute([$post['id']]);
        $pdo->prepare('UPDATE postulations SET status = "rejected" WHERE project_id = ? AND id != ?')->execute([$post['project_id'], $post['id']]);
        $pdo->prepare('UPDATE projects SET status = "in_progress" WHERE id = ?')->execute([$post['project_id']]);

        $wallet = getUserWallet($user['id']);
        if ((float)$wallet['balance'] >= (float)$post['proposed_price']) {
            $pdo->prepare('UPDATE wallets SET balance = balance - ?, locked = locked + ? WHERE user_id = ?')
                ->execute([$post['proposed_price'], $post['proposed_price'], $user['id']]);
            recordTransaction($user['id'], 'lock', $post['proposed_price'], $contractId, 'Montant bloqué pour contrat #' . $contractId);
        }

        sendNotification($post['freelancer_id'], 'application_accepted', 'Candidature acceptée !',
            'Votre candidature pour "' . $post['project_title'] . '" a été acceptée. Un contrat a été créé.',
            '/upc_freelance/app/contracts/details.php?id=' . $contractId);

        flash('success', 'Candidature acceptée ! Le contrat a été créé et le montant bloqué.');
    }
    redirect('../../app/postulations/received.php?project_id=' . ($post['project_id'] ?? $projectId));
}

// ─── Reject ───────────────────────────────────────────────────
if (isset($_GET['reject'])) {
    $postId = (int)$_GET['reject'];
    $stmt   = $pdo->prepare('
        SELECT po.*, p.client_id, p.title
        FROM postulations po
        JOIN projects p ON p.id = po.project_id
        WHERE po.id = ? AND p.client_id = ?
    ');
    $stmt->execute([$postId, $user['id']]);
    $post = $stmt->fetch();
    if ($post && $post['status'] === 'pending') {
        $pdo->prepare('UPDATE postulations SET status = "rejected" WHERE id = ?')->execute([$postId]);
        sendNotification($post['freelancer_id'], 'application_rejected', 'Candidature non retenue',
            'Votre candidature pour "' . $post['title'] . '" n\'a pas été retenue.',
            '/upc_freelance/app/postulations/my-applications.php');
        flash('info', 'Candidature refusée.');
    }
    redirect('../../app/postulations/received.php?project_id=' . ($post['project_id'] ?? $projectId));
}

// ─── Lister ───────────────────────────────────────────────────
$projectFilter = '';
$params        = [$user['id']];
if ($projectId) {
    $projectFilter = 'AND p.id = ?';
    $params[]      = $projectId;
}

// CORRECTION : u.university supprimé — on lit fp.university depuis freelancer_profiles
$stmt = $pdo->prepare("
    SELECT po.*, p.title AS project_title, p.id AS proj_id,
           u.first_name, u.last_name, u.avatar,
           fp.title AS freelancer_title, fp.rating, fp.total_reviews,
           fp.skills, fp.university, fp.field_of_study
    FROM postulations po
    JOIN projects p ON p.id = po.project_id
    JOIN users u ON u.id = po.freelancer_id
    LEFT JOIN freelancer_profiles fp ON fp.user_id = u.id
    WHERE p.client_id = ? $projectFilter
    ORDER BY po.created_at DESC
");
$stmt->execute($params);
$postulations = $stmt->fetchAll();

// Projets pour filtre
$myProjects = $pdo->prepare('SELECT id, title FROM projects WHERE client_id = ? ORDER BY created_at DESC');
$myProjects->execute([$user['id']]);
$myProjects = $myProjects->fetchAll();

$pageTitle = 'Candidatures reçues — UPC Freelance';
$appLayout = true;
require_once '../../includes/header.php';
?>

<?php renderFlash(); ?>

<div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
    <div>
        <h1 class="text-2xl font-bold text-primary">Candidatures reçues</h1>
        <p class="text-on-surface-variant text-sm mt-1"><?= count($postulations) ?> candidature<?= count($postulations) > 1 ? 's' : '' ?></p>
    </div>
    <form method="GET">
        <select name="project_id" onchange="this.form.submit()"
                class="px-4 py-2.5 rounded-xl border border-slate-200 text-sm focus:border-secondary outline-none">
            <option value="0">Tous les projets</option>
            <?php foreach ($myProjects as $mp): ?>
            <option value="<?= $mp['id'] ?>" <?= $projectId === (int)$mp['id'] ? 'selected' : '' ?>>
                <?= h(truncate($mp['title'], 40)) ?>
            </option>
            <?php endforeach; ?>
        </select>
    </form>
</div>

<?php if (empty($postulations)): ?>
<div class="bg-white rounded-2xl border border-slate-100 p-16 text-center custom-shadow-low">
    <span class="material-symbols-outlined text-5xl text-slate-300 block mb-4">inbox</span>
    <h3 class="font-semibold text-primary mb-2">Aucune candidature</h3>
    <p class="text-on-surface-variant text-sm">Publiez des projets pour recevoir des candidatures.</p>
</div>
<?php else: ?>
<div class="grid grid-cols-1 gap-4">
    <?php foreach ($postulations as $po):
        $sc     = ['pending'=>'amber','accepted'=>'green','rejected'=>'red'][$po['status']] ?? 'gray';
        $sl     = ['pending'=>'En attente','accepted'=>'Acceptée','rejected'=>'Refusée'][$po['status']] ?? $po['status'];
        $skills = $po['skills'] ? json_decode($po['skills'], true) : [];
    ?>
    <div class="bg-white rounded-2xl border border-slate-100 p-6 custom-shadow-low">
        <div class="flex items-start gap-4">

            <!-- Avatar -->
            <div class="flex-shrink-0">
                <?php if ($po['avatar']): ?>
                <img src="/upc_freelance/storage/<?= h($po['avatar']) ?>" alt="Avatar"
                     class="w-14 h-14 rounded-xl object-cover"/>
                <?php else: ?>
                <div class="w-14 h-14 rounded-xl bg-primary/10 flex items-center justify-center font-bold text-primary text-xl">
                    <?= mb_strtoupper(mb_substr($po['first_name'], 0, 1)) ?>
                </div>
                <?php endif; ?>
            </div>

            <div class="flex-1 min-w-0">
                <div class="flex items-start justify-between gap-3 mb-1">
                    <div>
                        <a href="/upc_freelance/app/profile/freelancer-profile.php?id=<?= $po['freelancer_id'] ?>"
                           class="font-bold text-primary hover:text-secondary transition-colors">
                            <?= h($po['first_name'] . ' ' . $po['last_name']) ?>
                        </a>
                        <?php if ($po['freelancer_title']): ?>
                        <p class="text-xs text-on-surface-variant"><?= h($po['freelancer_title']) ?></p>
                        <?php endif; ?>
                        <!-- CORRECTION : university vient de fp (freelancer_profiles) -->
                        <?php if ($po['university']): ?>
                        <p class="text-xs text-slate-400 flex items-center gap-1 mt-0.5">
                            <span class="material-symbols-outlined text-sm">school</span>
                            <?= h($po['university']) ?>
                            <?= $po['field_of_study'] ? ' · ' . h($po['field_of_study']) : '' ?>
                        </p>
                        <?php endif; ?>
                    </div>
                    <span class="inline-block text-xs bg-<?= $sc ?>-100 text-<?= $sc ?>-700 px-2.5 py-1 rounded-full font-medium whitespace-nowrap">
                        <?= $sl ?>
                    </span>
                </div>

                <?php if ($po['rating']): ?>
                <div class="flex items-center gap-1 mb-2">
                    <?= renderStars((float)$po['rating']) ?>
                    <span class="text-xs text-slate-400"><?= number_format($po['rating'], 1) ?> (<?= $po['total_reviews'] ?>)</span>
                </div>
                <?php endif; ?>

                <?php if (!empty($skills)): ?>
                <div class="flex flex-wrap gap-1.5 mb-3">
                    <?php foreach (array_slice($skills, 0, 5) as $s): ?>
                    <span class="text-xs bg-surface-container text-secondary px-2 py-0.5 rounded-full"><?= h($s) ?></span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <div class="grid grid-cols-3 gap-4 mb-3 text-sm">
                    <div>
                        <p class="text-xs text-slate-400">Proposition</p>
                        <p class="font-bold text-secondary text-base"><?= money((float)$po['proposed_price']) ?></p>
                    </div>
                    <?php if ($po['proposed_days']): ?>
                    <div>
                        <p class="text-xs text-slate-400">Délai</p>
                        <p class="font-medium text-primary"><?= $po['proposed_days'] ?> jours</p>
                    </div>
                    <?php endif; ?>
                    <div>
                        <p class="text-xs text-slate-400">Projet</p>
                        <p class="text-xs font-medium text-primary truncate"><?= h(truncate($po['project_title'], 30)) ?></p>
                    </div>
                </div>

                <blockquote class="text-sm text-on-surface-variant border-l-2 border-secondary/30 pl-3 mb-4 italic line-clamp-3">
                    <?= h($po['cover_letter']) ?>
                </blockquote>

                <?php if ($po['status'] === 'pending'): ?>
                <div class="flex gap-3 flex-wrap">
                    <a href="?project_id=<?= $po['proj_id'] ?>&accept=<?= $po['id'] ?>"
                       class="inline-flex items-center gap-1.5 bg-green-500 text-white text-sm px-4 py-2 rounded-xl hover:bg-green-600 transition-colors active:scale-95">
                        <span class="material-symbols-outlined text-base">check</span> Accepter
                    </a>
                    <a href="?project_id=<?= $po['proj_id'] ?>&reject=<?= $po['id'] ?>"
                       class="inline-flex items-center gap-1.5 bg-red-50 text-red-600 text-sm px-4 py-2 rounded-xl hover:bg-red-100 transition-colors active:scale-95"
                       onclick="return confirm('Confirmer le refus de cette candidature ?')">
                        <span class="material-symbols-outlined text-base">close</span> Refuser
                    </a>
                    <a href="/upc_freelance/app/profile/freelancer-profile.php?id=<?= $po['freelancer_id'] ?>"
                       class="inline-flex items-center gap-1.5 border border-slate-200 text-sm px-4 py-2 rounded-xl hover:border-secondary hover:text-secondary transition-colors">
                        <span class="material-symbols-outlined text-base">person</span> Voir le profil
                    </a>
                </div>
                <?php elseif ($po['status'] === 'accepted'): ?>
                <a href="/upc_freelance/app/contracts/list.php"
                   class="inline-flex items-center gap-1.5 text-sm text-green-600 font-medium hover:underline">
                    <span class="material-symbols-outlined text-base">description</span> Voir le contrat
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php
$appLayout = true;
require_once '../../includes/footer.php';
?>