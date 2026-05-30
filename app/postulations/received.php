<?php
// ============================================================
// UPC FREELANCE — Candidatures reçues (client)
// ../../app/postulations/received.php
// ============================================================

require_once '../../includes/middleware.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../includes/db.php';

requireRole('client', 'freelancer');

$user      = currentUser();
$pdo       = getDB();
$projectId = (int)($_GET['project_id'] ?? 0);

// ─── Accept ──────────────────────────────────────────────────
if (isset($_GET['accept'])) {
    $postId = (int)$_GET['accept'];
    $stmt   = $pdo->prepare('
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
        ')->execute([generateUUID(), $post['project_id'], $user['id'], $post['freelancer_id'], $post['id'], $post['proposed_price']]);
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
            'Votre candidature pour "' . $post['project_title'] . '" a été acceptée.',
            '/upc_freelance/app/contracts/details.php?id=' . $contractId);
        flash('success', 'Candidature acceptée ! Le contrat a été créé.');
    }
    redirect('../../app/postulations/received.php?project_id=' . ($post['project_id'] ?? $projectId));
}

// ─── Reject ───────────────────────────────────────────────────
if (isset($_GET['reject'])) {
    $postId = (int)$_GET['reject'];
    $stmt   = $pdo->prepare('SELECT po.*, p.client_id, p.title FROM postulations po JOIN projects p ON p.id = po.project_id WHERE po.id = ? AND p.client_id = ?');
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

$stmt = $pdo->prepare("
    SELECT po.*,
           p.title AS project_title, p.id AS proj_id, p.skills_needed AS project_skills,
           u.first_name, u.last_name, u.avatar, u.is_verified,
           fp.title AS freelancer_title,
           fp.rating, fp.total_reviews, fp.total_earned,
           fp.skills, fp.university, fp.field_of_study
    FROM postulations po
    JOIN projects p   ON p.id  = po.project_id
    JOIN users u      ON u.id  = po.freelancer_id
    LEFT JOIN freelancer_profiles fp ON fp.user_id = u.id
    WHERE p.client_id = ? $projectFilter
    ORDER BY
        FIELD(po.status,'pending','accepted','rejected','withdrawn'),
        po.created_at DESC
");
$stmt->execute($params);
$postulations = $stmt->fetchAll();

// Projet sélectionné
$currentProject = null;
if ($projectId) {
    $ps = $pdo->prepare('SELECT * FROM projects WHERE id = ? AND client_id = ?');
    $ps->execute([$projectId, $user['id']]);
    $currentProject = $ps->fetch();
}

// Projets pour filtre
$myProjects = $pdo->prepare('SELECT id, title FROM projects WHERE client_id = ? ORDER BY created_at DESC');
$myProjects->execute([$user['id']]);
$myProjects = $myProjects->fetchAll();

// Calcul match compétences
function computeMatch(?array $projectSkills, ?array $freelancerSkills): int {
    if (empty($projectSkills) || empty($freelancerSkills)) return 0;
    $pNorm  = array_map('mb_strtolower', $projectSkills);
    $fNorm  = array_map('mb_strtolower', $freelancerSkills);
    $common = count(array_intersect($pNorm, $fNorm));
    return min(100, (int)round($common / count($pNorm) * 100));
}

// Stats rapides
$nbPending  = count(array_filter($postulations, fn($p) => $p['status'] === 'pending'));
$nbAccepted = count(array_filter($postulations, fn($p) => $p['status'] === 'accepted'));

$pageTitle = 'Candidatures reçues — UPC Freelance';
$appLayout = true;
require_once '../../includes/header.php';
?>
<?php renderFlash(); ?>

<div class="mb-6">
    <div class="flex items-center gap-2 mb-3">
        <a href="/upc_freelance/app/projects/my-projects.php"
           class="flex items-center gap-1 hover:text-secondary transition-colors text-xs font-semibold uppercase tracking-wider text-slate-400">
            <span class="material-symbols-outlined text-sm">arrow_back</span> Mes projets
        </a>
    </div>

    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold text-primary">
                <?php if ($currentProject): ?>
                    <span class="text-secondary"><?= h(truncate($currentProject['title'], 40)) ?></span>
                    <span class="text-slate-400 font-normal text-base ml-1">— candidatures</span>
                <?php else: ?>
                    Toutes les candidatures
                <?php endif; ?>
            </h1>
            <div class="flex flex-wrap items-center gap-2 mt-2">
                <span class="text-xs text-slate-500 flex items-center gap-1">
                    <span class="material-symbols-outlined text-sm">group</span>
                    <strong><?= count($postulations) ?></strong>&nbsp;candidat<?= count($postulations) > 1 ? 's' : '' ?>
                </span>
                <?php if ($nbPending > 0): ?>
                <span class="text-xs bg-amber-100 text-amber-700 px-2 py-0.5 rounded-full font-semibold">
                    <?= $nbPending ?> en attente
                </span>
                <?php endif; ?>
                <?php if ($nbAccepted > 0): ?>
                <span class="text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded-full font-semibold">
                    <?= $nbAccepted ?> acceptée<?= $nbAccepted > 1 ? 's' : '' ?>
                </span>
                <?php endif; ?>
            </div>
        </div>

        <form method="GET" class="flex-shrink-0">
            <select name="project_id" onchange="this.form.submit()"
                    class="px-3 py-2 rounded-lg border border-slate-200 text-sm focus:border-secondary outline-none bg-white">
                <option value="0">Tous les projets</option>
                <?php foreach ($myProjects as $mp): ?>
                <option value="<?= $mp['id'] ?>" <?= $projectId === (int)$mp['id'] ? 'selected' : '' ?>>
                    <?= h(truncate($mp['title'], 30)) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>
</div>

<?php if (empty($postulations)): ?>
<div class="bg-white rounded-xl border border-slate-200 p-12 text-center">
    <span class="material-symbols-outlined text-4xl text-slate-300 block mb-3">inbox</span>
    <h3 class="font-semibold text-primary mb-1">Aucune candidature</h3>
    <p class="text-slate-400 text-sm">Publiez des projets pour attirer des freelancers.</p>
</div>

<?php else: ?>
<div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
    <?php foreach ($postulations as $po):
        $freelancerSkills = $po['skills'] ? json_decode($po['skills'], true) : [];
        $projectSkillsArr = ($currentProject && $currentProject['project_skills'])
                            ? json_decode($currentProject['project_skills'], true) : [];
        $matchPct = computeMatch($projectSkillsArr, $freelancerSkills);

        $sbConf = [
            'pending'   => ['label'=>'En attente', 'class'=>'bg-amber-100 text-amber-700'],
            'accepted'  => ['label'=>'Acceptée',   'class'=>'bg-green-100 text-green-700'],
            'rejected'  => ['label'=>'Refusée',    'class'=>'bg-red-100 text-red-600'],
            'withdrawn' => ['label'=>'Retirée',    'class'=>'bg-slate-100 text-slate-500'],
        ];
        $sb = $sbConf[$po['status']] ?? ['label'=>$po['status'],'class'=>'bg-slate-100 text-slate-500'];
        $isAccepted = $po['status'] === 'accepted';
        $isRejected = $po['status'] === 'rejected';
    ?>
    <div class="bg-white rounded-xl border p-4 transition-all hover:shadow-md
                <?= $isAccepted ? 'border-green-300' : ($isRejected ? 'border-slate-200 opacity-60' : 'border-slate-200') ?>">

        <!-- Header -->
        <div class="flex items-start gap-3 mb-3">
            <div class="relative flex-shrink-0">
                <?php if ($po['avatar']): ?>
                <img src="/upc_freelance/storage/<?= h($po['avatar']) ?>" alt=""
                     class="w-11 h-11 rounded-xl object-cover border border-slate-100"/>
                <?php else: ?>
                <div class="w-11 h-11 rounded-xl bg-blue-50 flex items-center justify-center font-bold text-primary text-sm border border-slate-100">
                    <?= mb_strtoupper(mb_substr($po['first_name'],0,1).mb_substr($po['last_name'],0,1)) ?>
                </div>
                <?php endif; ?>
                <?php if ($po['is_verified']): ?>
                <span class="absolute -bottom-1 -right-1 w-4 h-4 bg-secondary rounded-full flex items-center justify-center border-2 border-white">
                    <span class="material-symbols-outlined text-white" style="font-size:9px;font-variation-settings:'FILL' 1">verified</span>
                </span>
                <?php endif; ?>
            </div>

            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-1.5 flex-wrap">
                    <span class="font-semibold text-primary text-sm"><?= h($po['first_name'].' '.$po['last_name']) ?></span>
                    <span class="text-[10px] px-1.5 py-0.5 rounded-full font-semibold <?= $sb['class'] ?>"><?= $sb['label'] ?></span>
                </div>
                <?php $badgeLabel = $po['freelancer_title'] ?? ($po['university'] ?? null); ?>
                <?php if ($badgeLabel): ?>
                <p class="text-xs text-slate-400 truncate mt-0.5"><?= h(truncate($badgeLabel, 35)) ?></p>
                <?php endif; ?>
                <?php if ($po['rating'] && $po['total_reviews'] > 0): ?>
                <div class="flex items-center gap-1 mt-0.5">
                    <span class="material-symbols-outlined text-amber-400" style="font-size:13px;font-variation-settings:'FILL' 1">star</span>
                    <span class="text-xs font-semibold text-slate-700"><?= number_format($po['rating'],1) ?></span>
                    <span class="text-xs text-slate-400">(<?= $po['total_reviews'] ?>)</span>
                </div>
                <?php endif; ?>
            </div>

            <div class="text-right flex-shrink-0">
                <p class="font-bold text-secondary text-base"><?= money((float)$po['proposed_price']) ?></p>
                <?php if ($po['proposed_days']): ?>
                <p class="text-[10px] text-slate-400 mt-0.5"><?= $po['proposed_days'] ?> jours</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Lettre -->
        <p class="text-xs text-slate-500 italic line-clamp-2 mb-3 leading-relaxed border-l-2 border-slate-100 pl-2">
            <?= h(truncate($po['cover_letter'], 140)) ?>
        </p>

        <!-- Skills + match -->
        <div class="flex flex-wrap items-center gap-1.5 mb-3">
            <?php foreach (array_slice($freelancerSkills, 0, 4) as $skill): ?>
            <span class="bg-slate-50 text-slate-500 px-2 py-0.5 rounded text-[10px] font-medium border border-slate-100"><?= h($skill) ?></span>
            <?php endforeach; ?>
            <?php if (count($freelancerSkills) > 4): ?>
            <span class="text-[10px] text-slate-400">+<?= count($freelancerSkills)-4 ?></span>
            <?php endif; ?>
            <?php if ($matchPct > 0 && !empty($projectSkillsArr)):
                $mc = $matchPct >= 90 ? 'bg-green-50 text-green-700' : ($matchPct >= 70 ? 'bg-blue-50 text-blue-700' : 'bg-orange-50 text-orange-700'); ?>
            <span class="ml-auto <?= $mc ?> px-2 py-0.5 rounded text-[10px] font-bold"><?= $matchPct ?>% match</span>
            <?php endif; ?>
        </div>

        <!-- Actions -->
        <?php if ($po['status'] === 'pending'): ?>
        <div class="flex gap-2 pt-2 border-t border-slate-100">
            <a href="/upc_freelance/app/profile/freelancer-profile.php?id=<?= $po['freelancer_id'] ?>"
               class="flex-1 py-1.5 text-center text-xs font-semibold text-secondary border border-secondary/30 rounded-lg hover:bg-secondary/5 transition-colors">Profil</a>
            <a href="?project_id=<?= $po['proj_id'] ?>&accept=<?= $po['id'] ?>"
               class="flex-1 py-1.5 text-center text-xs font-semibold bg-secondary text-white rounded-lg hover:bg-blue-700 transition-colors">Accepter</a>
            <a href="?project_id=<?= $po['proj_id'] ?>&reject=<?= $po['id'] ?>"
               onclick="return confirm('Confirmer ?')"
               class="flex-1 py-1.5 text-center text-xs font-semibold bg-slate-100 text-slate-500 rounded-lg hover:bg-red-50 hover:text-red-500 transition-colors">Refuser</a>
        </div>
        <?php elseif ($po['status'] === 'accepted'): ?>
        <div class="flex gap-2 pt-2 border-t border-slate-100">
            <a href="/upc_freelance/app/profile/freelancer-profile.php?id=<?= $po['freelancer_id'] ?>"
               class="flex-1 py-1.5 text-center text-xs font-semibold text-secondary border border-secondary/30 rounded-lg hover:bg-secondary/5 transition-colors">Profil</a>
            <a href="/upc_freelance/app/contracts/list.php"
               class="flex-1 py-1.5 text-center text-xs font-semibold bg-green-500 text-white rounded-lg hover:bg-green-600 transition-colors">Contrat</a>
        </div>
        <?php elseif ($po['status'] === 'rejected'): ?>
        <div class="flex items-center justify-between pt-2 border-t border-slate-100">
            <span class="text-xs text-slate-400 italic">Candidature refusée</span>
            <a href="/upc_freelance/app/profile/freelancer-profile.php?id=<?= $po['freelancer_id'] ?>"
               class="text-xs text-secondary hover:underline">Voir le profil</a>
        </div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php $appLayout = true; require_once '../../includes/footer.php'; ?>