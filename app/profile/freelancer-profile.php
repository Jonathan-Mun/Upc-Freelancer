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

// Calcul taux de complétion du profil
$profileFields = [
    $freelancer['avatar'],
    $freelancer['title'],
    $freelancer['bio'],
    $freelancer['university'],
    $freelancer['skills'],
    $freelancer['hourly_rate'],
    $freelancer['portfolio_url'] ?? $freelancer['linkedin_url'] ?? $freelancer['github_url'],
];
$filled = count(array_filter($profileFields));
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
.profile-sidebar {
    position: sticky;
    top: 5rem;
}
.skill-tag {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: #eff6ff;
    color: #1d4ed8;
    border: 1px solid #bfdbfe;
    font-size: 12px;
    font-weight: 600;
    letter-spacing: 0.04em;
    padding: 4px 12px;
    border-radius: 6px;
    text-transform: uppercase;
}
.section-card {
    background: #fff;
    border-radius: 14px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 2px 8px rgba(0,32,69,0.05);
}
.stat-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 0;
    border-bottom: 1px solid #f1f5f9;
}
.stat-row:last-child { border-bottom: none; }
.avatar-initials {
    width: 96px;
    height: 96px;
    border-radius: 14px;
    background: #dbeafe;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2.5rem;
    font-weight: 700;
    color: #1d4ed8;
    border: 3px solid #bfdbfe;
    flex-shrink: 0;
}
.portfolio-card {
    background: #fff;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    overflow: hidden;
    transition: transform 0.2s, box-shadow 0.2s;
}
.portfolio-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 24px rgba(0,32,69,0.1);
}
.review-card {
    background: #f8faff;
    border-radius: 10px;
    border: 1px solid #e2e8f0;
    padding: 16px;
}
.progress-bar-track {
    width: 100%;
    background: #e2e8f0;
    border-radius: 999px;
    height: 6px;
}
.progress-bar-fill {
    background: #2563eb;
    height: 6px;
    border-radius: 999px;
    transition: width 0.5s ease;
}
.nav-link-sidebar {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 14px;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 500;
    color: #64748b;
    transition: background 0.15s, color 0.15s, transform 0.15s;
    cursor: pointer;
    text-decoration: none;
}
.nav-link-sidebar:hover {
    background: #f1f5f9;
    color: #1e40af;
    transform: translateX(3px);
}
.nav-link-sidebar.active {
    background: #eff6ff;
    color: #1d4ed8;
    border-right: 3px solid #2563eb;
}
.contract-row {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 14px;
    border-radius: 10px;
    background: #f8faff;
    border: 1px solid #e2e8f0;
}
</style>

<!-- Layout global : sidebar gauche + contenu principal -->
<div class="flex gap-0 max-w-6xl mx-auto">
    <!-- ══ CONTENU PRINCIPAL ═══════════════════════════════════ -->
    <main class="flex-1 min-w-0 space-y-8">

        <!-- ── SECTION : Identité ──────────────────────────── -->
        <section id="personal">
            <div class="flex items-end justify-between mb-4">
                <div>
                    <h2 class="text-2xl font-bold text-primary">Identité</h2>
                    <p class="text-sm text-on-surface-variant mt-1">Présentation publique du freelancer.</p>
                </div>
                <?php if ($isOwn): ?>
                <a href="/upc_freelance/app/profile/edit.php" class="text-xs text-secondary border border-secondary/30 px-3 py-1.5 rounded-full hover:bg-secondary/5 transition-colors flex items-center gap-1">
                    <span class="material-symbols-outlined text-sm">edit</span> Modifier
                </a>
                <?php endif; ?>
            </div>

            <div class="section-card p-8">
                <div class="flex flex-col md:flex-row gap-8 items-start">

                    <!-- Avatar -->
                    <div class="relative shrink-0">
                        <?php if ($freelancer['avatar']): ?>
                        <img src="/upc_freelance/storage/<?= h($freelancer['avatar']) ?>" alt="Avatar"
                             class="w-24 h-24 rounded-[14px] object-cover ring-4 ring-blue-100"/>
                        <?php else: ?>
                        <div class="avatar-initials">
                            <?= mb_strtoupper(mb_substr($freelancer['first_name'], 0, 1)) ?>
                        </div>
                        <?php endif; ?>
                        <!-- Badge dispo -->
                        <span class="absolute -bottom-2 -right-2 inline-flex items-center gap-1 text-xs bg-<?= $avc ?>-100 text-<?= $avc ?>-700 border border-<?= $avc ?>-200 px-2 py-0.5 rounded-full font-medium whitespace-nowrap">
                            <span class="w-1.5 h-1.5 bg-<?= $avc ?>-500 rounded-full"></span>
                            <?= $avl ?>
                        </span>
                    </div>

                    <!-- Infos -->
                    <div class="flex-1 space-y-3">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <p class="text-[11px] font-semibold text-slate-400 tracking-widest uppercase mb-1">Nom complet</p>
                                <p class="font-bold text-primary text-lg">
                                    <?= h($freelancer['first_name'] . ' ' . $freelancer['last_name']) ?>
                                    <?php if ($freelancer['is_verified']): ?>
                                    <span class="inline-block ml-1 text-secondary align-middle" title="Profil vérifié">
                                        <span class="material-symbols-outlined text-base" style="font-variation-settings:'FILL' 1">verified</span>
                                    </span>
                                    <?php endif; ?>
                                </p>
                            </div>
                            <?php if ($freelancer['title']): ?>
                            <div>
                                <p class="text-[11px] font-semibold text-slate-400 tracking-widest uppercase mb-1">Titre professionnel</p>
                                <p class="text-primary font-medium"><?= h($freelancer['title']) ?></p>
                            </div>
                            <?php endif; ?>
                        </div>

                        <?php if ($freelancer['bio']): ?>
                        <div>
                            <p class="text-[11px] font-semibold text-slate-400 tracking-widest uppercase mb-1">Bio / Résumé</p>
                            <p class="text-sm text-on-surface-variant leading-relaxed">
                                <?= nl2br(h($freelancer['bio'])) ?>
                            </p>
                        </div>
                        <?php endif; ?>

                        <!-- Liens -->
                        <div class="flex flex-wrap gap-2 pt-1">
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
                </div>
            </div>
        </section>

        <!-- ── SECTION : Formation ─────────────────────────── -->
        <section id="academic">
            <h2 class="text-2xl font-bold text-primary mb-4">Formation</h2>
            <div class="space-y-3">
                <?php if ($freelancer['university'] || $freelancer['field_of_study']): ?>
                <div class="section-card p-5 flex items-start gap-4 hover:border-blue-200 transition-colors group">
                    <div class="p-2.5 rounded-lg bg-blue-50 text-blue-700 shrink-0">
                        <span class="material-symbols-outlined" style="font-variation-settings:'FILL' 1">school</span>
                    </div>
                    <div class="flex-1 grid grid-cols-1 md:grid-cols-3 gap-4">
                        <?php if ($freelancer['university']): ?>
                        <div>
                            <p class="text-[11px] font-semibold text-slate-400 tracking-widest uppercase mb-1">Université</p>
                            <p class="font-medium text-primary text-sm"><?= h($freelancer['university']) ?></p>
                        </div>
                        <?php endif; ?>
                        <?php if ($freelancer['field_of_study']): ?>
                        <div>
                            <p class="text-[11px] font-semibold text-slate-400 tracking-widest uppercase mb-1">Filière</p>
                            <p class="font-medium text-primary text-sm"><?= h($freelancer['field_of_study']) ?></p>
                        </div>
                        <?php endif; ?>
                        <div>
                            <p class="text-[11px] font-semibold text-slate-400 tracking-widest uppercase mb-1">Membre depuis</p>
                            <p class="font-medium text-primary text-sm"><?= formatDate($freelancer['created_at']) ?></p>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <div class="section-card p-6 text-center text-slate-400 text-sm">
                    Aucune information académique renseignée.
                </div>
                <?php endif; ?>
            </div>
        </section>

        <!-- ── SECTION : Compétences ───────────────────────── -->
        <?php if (!empty($skills)): ?>
        <section id="skills">
            <h2 class="text-2xl font-bold text-primary mb-4">Compétences</h2>
            <div class="section-card p-8">
                <div class="flex flex-wrap gap-2">
                    <?php foreach ($skills as $skill): ?>
                    <span class="skill-tag"><?= h($skill) ?></span>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
        <?php endif; ?>

        <!-- ── SECTION : Missions ──────────────────────────── -->
        <?php if (!empty($completedContracts)): ?>
        <section id="missions">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-2xl font-bold text-primary">Missions complétées</h2>
                <span class="text-xs bg-green-100 text-green-700 border border-green-200 px-3 py-1 rounded-full font-semibold">
                    <?= count($completedContracts) ?> mission<?= count($completedContracts) > 1 ? 's' : '' ?>
                </span>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <?php foreach ($completedContracts as $ct): ?>
                <div class="section-card portfolio-card p-0 overflow-hidden">
                    <div class="h-2 bg-gradient-to-r from-blue-500 to-blue-300"></div>
                    <div class="p-5">
                        <div class="flex items-start gap-3">
                            <div class="w-9 h-9 rounded-lg bg-green-100 flex items-center justify-center shrink-0">
                                <span class="material-symbols-outlined text-green-600 text-lg">task_alt</span>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="font-semibold text-primary text-sm truncate"><?= h($ct['project_title']) ?></p>
                                <p class="text-xs text-slate-400 mt-0.5">
                                    par <?= h($ct['client_fname'] . ' ' . $ct['client_lname']) ?>
                                    · <?= formatDate($ct['completed_at'] ?? $ct['created_at']) ?>
                                </p>
                            </div>
                            <span class="text-sm font-bold text-secondary whitespace-nowrap"><?= money((float)$ct['amount']) ?></span>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <!-- ── SECTION : Avis ──────────────────────────────── -->
        <?php if (!empty($reviews)): ?>
        <section id="reviews">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-2xl font-bold text-primary">Avis clients</h2>
                <?php if ($freelancer['rating']): ?>
                <div class="flex items-center gap-2">
                    <?= renderStars((float)$freelancer['rating']) ?>
                    <span class="font-bold text-primary"><?= number_format($freelancer['rating'], 1) ?></span>
                    <span class="text-slate-400 text-sm">(<?= $freelancer['total_reviews'] ?> avis)</span>
                </div>
                <?php endif; ?>
            </div>
            <div class="space-y-4">
                <?php foreach ($reviews as $r): ?>
                <div class="section-card p-5">
                    <div class="flex items-start justify-between mb-3">
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-full bg-blue-100 flex items-center justify-center text-sm font-bold text-blue-700">
                                <?= mb_strtoupper(mb_substr($r['first_name'], 0, 1)) ?>
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-primary"><?= h($r['first_name'] . ' ' . $r['last_name']) ?></p>
                                <p class="text-xs text-slate-400"><?= timeAgo($r['created_at']) ?></p>
                            </div>
                        </div>
                        <?= renderStars((int)$r['rating']) ?>
                    </div>
                    <?php if ($r['comment']): ?>
                    <p class="text-sm text-on-surface-variant italic leading-relaxed">"<?= h($r['comment']) ?>"</p>
                    <?php endif; ?>
                    <p class="text-xs text-slate-400 mt-3 flex items-center gap-1">
                        <span class="material-symbols-outlined text-sm">folder</span>
                        Projet : <?= h($r['project_title']) ?>
                    </p>
                </div>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <!-- ── SECTION : Tarif ─────────────────────────────── -->
        <section id="billing" class="pb-12">
            <h2 class="text-2xl font-bold text-primary mb-4">Tarif & Statistiques</h2>
            <div class="section-card p-8">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">

                    <!-- Tarif horaire -->
                    <div>
                        <p class="text-[11px] font-semibold text-slate-400 tracking-widest uppercase mb-3">Tarif horaire</p>
                        <?php if ($freelancer['hourly_rate']): ?>
                        <div class="flex items-baseline gap-2">
                            <span class="text-3xl font-bold text-primary"><?= money((float)$freelancer['hourly_rate']) ?></span>
                            <span class="text-slate-400 text-sm">/ heure</span>
                        </div>
                        <p class="text-xs text-slate-400 italic mt-1">Tarif indicatif, négociable selon le projet.</p>
                        <?php else: ?>
                        <p class="text-sm text-slate-400">Non renseigné</p>
                        <?php endif; ?>

                        <?php if ($freelancer['total_earned'] > 0): ?>
                        <div class="mt-5 p-4 rounded-xl bg-blue-50 border border-blue-100">
                            <p class="text-[11px] font-semibold text-blue-400 tracking-widest uppercase mb-1">Total gagné</p>
                            <p class="text-xl font-bold text-blue-800"><?= money((float)$freelancer['total_earned']) ?></p>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Stats -->
                    <div>
                        <p class="text-[11px] font-semibold text-slate-400 tracking-widest uppercase mb-3">Statistiques</p>
                        <div>
                            <div class="stat-row">
                                <span class="text-sm text-on-surface-variant flex items-center gap-1.5">
                                    <span class="material-symbols-outlined text-base text-blue-400">task_alt</span> Missions
                                </span>
                                <span class="font-bold text-primary"><?= count($completedContracts) ?></span>
                            </div>
                            <div class="stat-row">
                                <span class="text-sm text-on-surface-variant flex items-center gap-1.5">
                                    <span class="material-symbols-outlined text-base text-amber-400">star</span> Note
                                </span>
                                <span class="font-bold text-primary">
                                    <?= $freelancer['rating'] ? number_format($freelancer['rating'], 1) . ' / 5' : 'N/A' ?>
                                </span>
                            </div>
                            <div class="stat-row">
                                <span class="text-sm text-on-surface-variant flex items-center gap-1.5">
                                    <span class="material-symbols-outlined text-base text-slate-400">calendar_month</span> Membre depuis
                                </span>
                                <span class="text-sm text-primary"><?= formatDate($freelancer['created_at']) ?></span>
                            </div>
                            <?php if ($freelancer['field_of_study']): ?>
                            <div class="stat-row">
                                <span class="text-sm text-on-surface-variant flex items-center gap-1.5">
                                    <span class="material-symbols-outlined text-base text-slate-400">school</span> Filière
                                </span>
                                <span class="text-sm font-medium text-primary"><?= h($freelancer['field_of_study']) ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </section>

    </main>
</div>

<script>
// Active nav link on scroll
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
</script>

<?php
$appLayout = true;
require_once '../../includes/footer.php';
?>