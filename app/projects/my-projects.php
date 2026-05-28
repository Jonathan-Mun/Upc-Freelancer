<?php
// ============================================================
// UPC FREELANCE — Mes projets (client)
// ../../app/projects/my-projects.php
// ============================================================

require_once '../../includes/middleware.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../includes/db.php';

requireRole('client');

$user   = currentUser();
$pdo    = getDB();
$userId = $user['id'];

$tab    = in_array($_GET['tab'] ?? '', ['active','completed','cancelled']) ? $_GET['tab'] : 'active';
$tabMap = [
    'active'    => ['open','in_progress'],
    'completed' => ['completed'],
    'cancelled' => ['cancelled'],
];

$placeholders = implode(',', array_fill(0, count($tabMap[$tab]), '?'));
$params       = array_merge([$userId], $tabMap[$tab]);

$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 5;

$cstmt = $pdo->prepare("SELECT COUNT(*) FROM projects p WHERE p.client_id = ? AND p.status IN ($placeholders)");
$cstmt->execute($params);
$total      = (int)$cstmt->fetchColumn();
$totalPages = (int)ceil($total / $perPage);
$offset     = ($page - 1) * $perPage;

// Données passées au JS pour la pagination côté client
$paginationData = json_encode([
    'currentPage' => $page,
    'totalPages'  => $totalPages,
    'tab'         => $tab,
    'total'       => $total,
    'perPage'     => $perPage,
    'offset'      => $offset,
]);

$stmt = $pdo->prepare("
    SELECT p.*, c.name AS category_name, c.icon AS category_icon, c.slug AS category_slug,
           (SELECT COUNT(*) FROM postulations WHERE project_id = p.id) AS nb_postulations,
           (SELECT COUNT(*) FROM postulations WHERE project_id = p.id AND status = 'pending') AS nb_pending
    FROM projects p
    LEFT JOIN categories c ON c.id = p.category_id
    WHERE p.client_id = ? AND p.status IN ($placeholders)
    ORDER BY p.created_at DESC
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$projects = $stmt->fetchAll();

$s = $pdo->prepare("SELECT
    SUM(status IN ('open','in_progress')) AS nb_active,
    SUM(status = 'completed')             AS nb_completed,
    SUM(status = 'cancelled')             AS nb_cancelled
FROM projects WHERE client_id = ?");
$s->execute([$userId]);
$stats = $s->fetch();

$s2 = $pdo->prepare("
    SELECT COUNT(*) AS total_applications,
           SUM(po.status = 'pending') AS pending_applications
    FROM postulations po
    JOIN projects p ON p.id = po.project_id
    WHERE p.client_id = ?
");
$s2->execute([$userId]);
$appStats = $s2->fetch();

$tabCounts = [
    'active'    => (int)($stats['nb_active']    ?? 0),
    'completed' => (int)($stats['nb_completed'] ?? 0),
    'cancelled' => (int)($stats['nb_cancelled'] ?? 0),
];

$catColors = [
    'dev-web'      => 'bg-blue-50 text-blue-700',
    'design'       => 'bg-purple-50 text-purple-700',
    'marketing'    => 'bg-orange-50 text-orange-700',
    'redaction'    => 'bg-teal-50 text-teal-700',
    'data'         => 'bg-cyan-50 text-cyan-700',
    'video-audio'  => 'bg-pink-50 text-pink-700',
    'finance'      => 'bg-green-50 text-green-700',
    'informatique' => 'bg-indigo-50 text-indigo-700',
];

$pageTitle = 'Mes projets — UPC Freelance';
$appLayout = true;
require_once '../../includes/header.php';
?>

<style>
/* ── En-tête ─────────────────────────────────────────────── */
.page-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 28px;
    flex-wrap: wrap;
}
.page-header h1 { font-size: 22px; font-weight: 700; color: #002045; }
.page-header p  { font-size: 13px; color: #43474e; margin-top: 2px; }

/* ── Stat cards ──────────────────────────────────────────── */
.stat-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 14px;
    margin-bottom: 28px;
}
@media (max-width: 640px) {
    .stat-grid { grid-template-columns: 1fr; gap: 10px; }
}
.stat-card {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 16px 18px;
    display: flex;
    align-items: center;
    gap: 14px;
    box-shadow: 0 2px 8px rgba(26,54,93,.04);
}
.stat-icon {
    width: 42px; height: 42px;
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
}
.stat-label { font-size: 10px; font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase; color: #64748b; }
.stat-value { font-size: 22px; font-weight: 700; color: #002045; line-height: 1.2; margin-top: 2px; }

/* ── Onglets ─────────────────────────────────────────────── */
.tabs {
    display: flex;
    border-bottom: 2px solid #f1f5f9;
    margin-bottom: 20px;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    scrollbar-width: none;
}
.tabs::-webkit-scrollbar { display: none; }
.tab-link {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 10px 16px;
    font-size: 13px;
    font-weight: 500;
    color: #64748b;
    border-bottom: 2px solid transparent;
    margin-bottom: -2px;
    white-space: nowrap;
    transition: color 0.15s, border-color 0.15s;
    text-decoration: none;
}
.tab-link:hover { color: #002045; }
.tab-link.active { color: #0061a5; border-bottom-color: #0061a5; font-weight: 600; }
.tab-badge {
    font-size: 10px;
    font-weight: 700;
    padding: 1px 6px;
    border-radius: 999px;
}
.tab-badge.active  { background: #0061a5; color: #fff; }
.tab-badge.default { background: #f1f5f9; color: #64748b; }

/* ── Carte projet ────────────────────────────────────────── */
.project-card {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 16px;
    box-shadow: 0 2px 8px rgba(26,54,93,.04);
    transition: box-shadow 0.2s, transform 0.15s;
    display: flex;
    flex-direction: column;
    gap: 12px;
}
.project-card:hover {
    box-shadow: 0 6px 20px rgba(26,54,93,.10);
    transform: translateY(-1px);
}
.project-card.cancelled { opacity: 0.65; }

/* Ligne du haut : icône + titre + badge statut */
.card-top {
    display: flex;
    align-items: flex-start;
    gap: 12px;
}
.card-icon {
    width: 42px; height: 42px;
    border-radius: 10px;
    background: #f1f5f9;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
    color: #002045;
}
.card-title-block { flex: 1; min-width: 0; }
.card-title {
    font-size: 14px;
    font-weight: 700;
    color: #002045;
    /* Wrapping sur mobile au lieu de truncate */
    word-break: break-word;
    overflow-wrap: anywhere;
    line-height: 1.4;
    margin-bottom: 4px;
}
.card-meta {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 6px;
}
.meta-chip {
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    padding: 2px 8px;
    border-radius: 4px;
}
.meta-text {
    display: flex;
    align-items: center;
    gap: 3px;
    font-size: 11px;
    color: #64748b;
}
.meta-text .material-symbols-outlined { font-size: 13px; }

.status-badge {
    flex-shrink: 0;
    font-size: 11px;
    font-weight: 600;
    padding: 3px 10px;
    border-radius: 999px;
    white-space: nowrap;
    align-self: flex-start;
}

/* Ligne du bas : stats + actions */
.card-bottom {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    flex-wrap: wrap;
    padding-top: 10px;
    border-top: 1px solid #f1f5f9;
}
.card-stats {
    display: flex;
    align-items: center;
    gap: 14px;
    flex-wrap: wrap;
}
.stat-pill {
    display: flex;
    flex-direction: column;
    align-items: center;
}
.stat-pill-value { font-size: 18px; font-weight: 700; color: #002045; line-height: 1; }
.stat-pill-label { font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: #94a3b8; margin-top: 1px; }
.pending-badge {
    font-size: 10px;
    background: #fef3c7;
    color: #b45309;
    padding: 2px 7px;
    border-radius: 999px;
    font-weight: 600;
}
.card-actions {
    display: flex;
    align-items: center;
    gap: 6px;
    flex-shrink: 0;
}
.btn-icon {
    width: 34px; height: 34px;
    border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
    border: 1px solid #e2e8f0;
    color: #64748b;
    background: #fff;
    cursor: pointer;
    transition: background 0.15s, color 0.15s;
    text-decoration: none;
}
.btn-icon:hover { background: #f1f5f9; color: #002045; }
.btn-action {
    font-size: 12px;
    font-weight: 600;
    padding: 7px 14px;
    border-radius: 9px;
    white-space: nowrap;
    text-decoration: none;
    transition: opacity 0.15s, transform 0.1s;
}
.btn-action:active { transform: scale(0.96); }
.btn-action.amber { background: #f59e0b; color: #fff; }
.btn-action.amber:hover { background: #d97706; }
.btn-action.blue  { background: #0061a5; color: #fff; }
.btn-action.blue:hover  { background: #004d82; }

/* ── Pagination ──────────────────────────────────────────── */
.pagination {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-top: 28px;
    gap: 12px;
    flex-wrap: wrap;
}
.pagination-info { font-size: 12px; color: #64748b; }
.page-btns { display: flex; align-items: center; gap: 4px; }
.page-btn {
    width: 34px; height: 34px;
    display: flex; align-items: center; justify-content: center;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    border: 1px solid #e2e8f0;
    color: #64748b;
    background: #fff;
    text-decoration: none;
    transition: background 0.15s, color 0.15s;
}
.page-btn:hover   { background: #f1f5f9; }
.page-btn.active  { background: #0061a5; color: #fff; border-color: #0061a5; }
.page-btn.disabled { opacity: 0.35; pointer-events: none; }

/* ── État vide ───────────────────────────────────────────── */
.empty-state {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 56px 24px;
    text-align: center;
}
</style>

<?php renderFlash(); ?>

<!-- En-tête -->
<div class="page-header">
    <div>
        <h1>Mes Projets</h1>
        <p>Gérez et suivez vos opportunités publiées.</p>
    </div>
    <a href="/upc_freelance/app/projects/create.php"
       class="inline-flex items-center gap-2 bg-secondary text-white px-5 py-2.5 rounded-xl font-button text-sm
              shadow-sm hover:opacity-90 transition-opacity active:scale-95 flex-shrink-0">
        <span class="material-symbols-outlined text-lg">rocket_launch</span>
        Publier un projet
    </a>
</div>

<!-- Stats -->
<div class="stat-grid">
    <div class="stat-card">
        <div class="stat-icon bg-blue-50"><span class="material-symbols-outlined text-secondary">ads_click</span></div>
        <div>
            <p class="stat-label">Projets actifs</p>
            <p class="stat-value"><?= $tabCounts['active'] ?></p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon bg-emerald-50"><span class="material-symbols-outlined text-emerald-600">group</span></div>
        <div>
            <p class="stat-label">Candidatures</p>
            <p class="stat-value"><?= (int)($appStats['total_applications'] ?? 0) ?></p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon bg-amber-50"><span class="material-symbols-outlined text-amber-600">pending</span></div>
        <div>
            <p class="stat-label">En attente</p>
            <p class="stat-value"><?= (int)($appStats['pending_applications'] ?? 0) ?></p>
        </div>
    </div>
</div>

<!-- Onglets -->
<div class="tabs">
    <?php foreach (['active'=>'Actifs','completed'=>'Terminés','cancelled'=>'Annulés'] as $key => $label): ?>
    <a href="?tab=<?= $key ?>" class="tab-link <?= $tab === $key ? 'active' : '' ?>">
        <?= $label ?>
        <span class="tab-badge <?= $tab === $key ? 'active' : 'default' ?>"><?= $tabCounts[$key] ?></span>
    </a>
    <?php endforeach; ?>
</div>

<!-- Liste projets -->
<?php if (empty($projects)): ?>
<div class="empty-state">
    <span class="material-symbols-outlined text-5xl text-slate-300 block mb-3">folder_open</span>
    <h3 style="font-size:16px;font-weight:600;color:#002045;margin-bottom:6px;">
        Aucun projet <?= ['active'=>'actif','completed'=>'terminé','cancelled'=>'annulé'][$tab] ?>
    </h3>
    <p style="font-size:13px;color:#64748b;margin-bottom:20px;">
        <?= $tab === 'active'
            ? 'Publiez votre premier projet pour recevoir des candidatures.'
            : ($tab === 'completed' ? 'Vos projets terminés apparaîtront ici.' : 'Aucun projet annulé.') ?>
    </p>
    <?php if ($tab === 'active'): ?>
    <a href="/upc_freelance/app/projects/create.php"
       class="inline-flex items-center gap-2 bg-secondary text-white px-5 py-2.5 rounded-xl text-sm font-semibold hover:opacity-90 transition-opacity">
        <span class="material-symbols-outlined text-base">add</span> Créer un projet
    </a>
    <?php endif; ?>
</div>

<?php else: ?>
<div style="display:flex;flex-direction:column;gap:12px;">
    <?php foreach ($projects as $p):
        $statusConfig = [
            'open'        => ['label'=>'Ouvert',   'class'=>'bg-emerald-100 text-emerald-700'],
            'in_progress' => ['label'=>'En cours', 'class'=>'bg-blue-100 text-blue-700'],
            'completed'   => ['label'=>'Terminé',  'class'=>'bg-slate-100 text-slate-600'],
            'cancelled'   => ['label'=>'Annulé',   'class'=>'bg-red-100 text-red-600'],
        ];
        $sc       = $statusConfig[$p['status']] ?? ['label'=>$p['status'],'class'=>'bg-slate-100 text-slate-600'];
        $catBadge = $catColors[$p['category_slug'] ?? ''] ?? 'bg-slate-50 text-slate-600';
    ?>
    <div class="project-card <?= $p['status'] === 'cancelled' ? 'cancelled' : '' ?>">

        <!-- Haut : icône + titre + statut -->
        <div class="card-top">
            <div class="card-icon">
                <span class="material-symbols-outlined"><?= h($p['category_icon'] ?? 'work') ?></span>
            </div>
            <div class="card-title-block">
                <p class="card-title"><?= h($p['title']) ?></p>
                <div class="card-meta">
                    <?php if ($p['category_name']): ?>
                    <span class="meta-chip <?= $catBadge ?>"><?= h($p['category_name']) ?></span>
                    <?php endif; ?>
                    <span class="meta-text">
                        <span class="material-symbols-outlined">calendar_today</span>
                        <?= timeAgo($p['created_at']) ?>
                    </span>
                    <?php if ($p['budget_max'] || $p['budget_min']): ?>
                    <span class="meta-text" style="color:#0061a5;font-weight:600;">
                        <span class="material-symbols-outlined">payments</span>
                        <?= money((float)($p['budget_max'] ?? $p['budget_min'])) ?>
                    </span>
                    <?php endif; ?>
                </div>
            </div>
            <!-- Badge statut en haut à droite -->
            <span class="status-badge <?= $sc['class'] ?>"><?= $sc['label'] ?></span>
        </div>

        <!-- Bas : stats + actions -->
        <div class="card-bottom">
            <div class="card-stats">
                <div class="stat-pill">
                    <span class="stat-pill-value"><?= $p['nb_postulations'] ?></span>
                    <span class="stat-pill-label">candidature<?= $p['nb_postulations'] > 1 ? 's' : '' ?></span>
                </div>
                <?php if ($p['nb_pending'] > 0): ?>
                <span class="pending-badge">
                    <?= $p['nb_pending'] ?> en attente
                </span>
                <?php endif; ?>
            </div>

            <div class="card-actions">
                <!-- Bouton modifier / voir contrat -->
                <?php if ($p['status'] === 'open'): ?>
                <a href="/upc_freelance/app/projects/edit.php?id=<?= $p['id'] ?>"
                   class="btn-icon" title="Modifier">
                    <span class="material-symbols-outlined text-base">edit</span>
                </a>
                <?php elseif ($p['status'] === 'completed'): ?>
                <a href="/upc_freelance/app/contracts/list.php"
                   class="btn-icon" title="Voir le contrat">
                    <span class="material-symbols-outlined text-base">description</span>
                </a>
                <?php endif; ?>

                <!-- Bouton principal -->
                <?php if ($p['nb_postulations'] > 0 && $p['status'] === 'open'): ?>
                <a href="/upc_freelance/app/postulations/received.php?project_id=<?= $p['id'] ?>"
                   class="btn-action amber">
                    Voir les candidatures
                </a>
                <?php else: ?>
                <a href="/upc_freelance/app/projects/details.php?id=<?= $p['id'] ?>"
                   class="btn-action blue">
                    Voir les détails
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Pagination JS -->
<div id="pagination-wrap"></div>

<script>
(function() {
    const cfg = <?= $paginationData ?>;
    const wrap = document.getElementById('pagination-wrap');
    if (!wrap || cfg.totalPages <= 1) return;

    function buildPages(current, total) {
        const pages = [];
        if (total <= 7) {
            for (let i = 1; i <= total; i++) pages.push(i);
            return pages;
        }
        pages.push(1);
        if (current > 3) pages.push('...');
        const start = Math.max(2, current - 1);
        const end   = Math.min(total - 1, current + 1);
        for (let i = start; i <= end; i++) pages.push(i);
        if (current < total - 2) pages.push('...');
        pages.push(total);
        return pages;
    }

    function url(page) {
        return '?tab=' + cfg.tab + '&page=' + page;
    }

    function render() {
        const cur    = cfg.currentPage;
        const total  = cfg.totalPages;
        const from   = cfg.offset + 1;
        const to     = Math.min(cfg.offset + cfg.perPage, cfg.total);
        const pages  = buildPages(cur, total);

        let html = '<div class="pagination">';
        html += '<p class="pagination-info">' + from + '–' + to + ' sur ' + cfg.total + ' projet' + (cfg.total > 1 ? 's' : '') + '</p>';
        html += '<div class="page-btns">';

        // Précédent
        if (cur > 1) {
            html += '<a href="' + url(cur - 1) + '" class="page-btn"><span class="material-symbols-outlined text-lg">chevron_left</span></a>';
        } else {
            html += '<span class="page-btn disabled"><span class="material-symbols-outlined text-lg">chevron_left</span></span>';
        }

        // Pages avec ellipses
        pages.forEach(function(p) {
            if (p === '...') {
                html += '<span class="page-btn" style="border:none;color:#94a3b8;cursor:default;">…</span>';
            } else if (p === cur) {
                html += '<span class="page-btn active">' + p + '</span>';
            } else {
                html += '<a href="' + url(p) + '" class="page-btn">' + p + '</a>';
            }
        });

        // Suivant
        if (cur < total) {
            html += '<a href="' + url(cur + 1) + '" class="page-btn"><span class="material-symbols-outlined text-lg">chevron_right</span></a>';
        } else {
            html += '<span class="page-btn disabled"><span class="material-symbols-outlined text-lg">chevron_right</span></span>';
        }

        html += '</div></div>';
        wrap.innerHTML = html;
    }

    render();
})();
</script>
<?php endif; ?>

<?php $appLayout = true; require_once '../../includes/footer.php'; ?>