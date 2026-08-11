<?php
// ============================================================
// UPC FREELANCE — Dashboard principal (redesign)
// ../app/dashboard.php
// ============================================================

require_once '../includes/middleware.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';

requireLogin();

$user   = currentUser();
$pdo    = getDB();
$wallet = getUserWallet($user['id']);
$userId = $user['id'];
$role   = $user['role'];

// ── Stats selon le rôle ───────────────────────────────────────
$stats = [];
if ($role === 'client') {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM projects WHERE client_id = ?');
    $stmt->execute([$userId]); $stats['projets'] = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM contracts WHERE client_id = ? AND status = "active"');
    $stmt->execute([$userId]); $stats['contrats'] = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare('SELECT COALESCE(SUM(amount),0) FROM transactions WHERE user_id = ? AND type = "payment"');
    $stmt->execute([$userId]); $stats['depenses'] = (float)$stmt->fetchColumn();

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM postulations p JOIN projects pr ON pr.id = p.project_id WHERE pr.client_id = ? AND p.status = "pending"');
    $stmt->execute([$userId]); $stats['postulations'] = (int)$stmt->fetchColumn();

    $recentProjects = $pdo->prepare('
        SELECT p.*, c.name AS category_name,
               (SELECT COUNT(*) FROM postulations WHERE project_id = p.id) AS nb_postulations
        FROM projects p
        LEFT JOIN categories c ON c.id = p.category_id
        WHERE p.client_id = ? ORDER BY p.created_at DESC LIMIT 5
    ');
    $recentProjects->execute([$userId]);
    $recentProjects = $recentProjects->fetchAll();
} else {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM postulations WHERE freelancer_id = ?');
    $stmt->execute([$userId]); $stats['candidatures'] = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM contracts WHERE freelancer_id = ? AND status = "active"');
    $stmt->execute([$userId]); $stats['contrats'] = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare('SELECT COALESCE(SUM(amount),0) FROM transactions WHERE user_id = ? AND type = "payment"');
    $stmt->execute([$userId]); $stats['gains'] = (float)$stmt->fetchColumn();

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM contracts WHERE freelancer_id = ? AND status = "completed"');
    $stmt->execute([$userId]); $stats['termines'] = (int)$stmt->fetchColumn();

    // Note moyenne freelancer
    $stmt = $pdo->prepare('SELECT AVG(rating) FROM reviews WHERE reviewed_id = ?');
    $stmt->execute([$userId]); $stats['rating'] = round((float)$stmt->fetchColumn(), 1);

    $recentProjects = $pdo->query('
        SELECT p.*, c.name AS category_name
        FROM projects p LEFT JOIN categories c ON c.id = p.category_id
        WHERE p.status = "open" ORDER BY p.created_at DESC LIMIT 5
    ')->fetchAll();
}

// ── Contrats actifs avec deadline ─────────────────────────────
$activeContracts = $pdo->prepare('
    SELECT ct.*, p.title AS project_title, p.deadline,
           u.first_name, u.last_name, u.avatar, u.is_verified
    FROM contracts ct
    JOIN projects p ON p.id = ct.project_id
    JOIN users u ON u.id = IF(? = ct.client_id, ct.freelancer_id, ct.client_id)
    WHERE (ct.client_id = ? OR ct.freelancer_id = ?) AND ct.status = "active"
    ORDER BY ct.created_at DESC LIMIT 6
');
$activeContracts->execute([$userId, $userId, $userId]);
$activeContracts = $activeContracts->fetchAll();

// ── Notifications récentes ─────────────────────────────────────
$recentNotifs = $pdo->prepare('SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 6');
$recentNotifs->execute([$userId]);
$recentNotifs = $recentNotifs->fetchAll();

// ── Gains par mois (6 derniers) pour le graphique ─────────────
$monthlyGains = [];
for ($i = 5; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i months"));
    $stmt  = $pdo->prepare("
        SELECT COALESCE(SUM(amount),0) FROM transactions
        WHERE user_id = ? AND type = 'payment'
        AND DATE_FORMAT(created_at,'%Y-%m') = ?
    ");
    $stmt->execute([$userId, $month]);
    $monthlyGains[] = [
        'label'  => mb_strtoupper(strftime('%b', strtotime($month . '-01'))),
        'amount' => (float)$stmt->fetchColumn(),
        'month'  => $month,
    ];
}
$maxGain = max(array_column($monthlyGains, 'amount')) ?: 1;

// ── Vérification doc status ───────────────────────────────────
$stmt = $pdo->prepare('SELECT status FROM verification_docs WHERE user_id = ? ORDER BY created_at DESC LIMIT 1');
$stmt->execute([$userId]);
$lastDocStatus = $stmt->fetchColumn();

// ── Taux de succès (contrats terminés / total) ────────────────
if ($role === 'freelancer') {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM contracts WHERE freelancer_id = ?');
    $stmt->execute([$userId]); $totalContracts = (int)$stmt->fetchColumn();
    $successRate = $totalContracts > 0 ? round(($stats['termines'] / $totalContracts) * 100) : 0;
}

$pageTitle = 'Tableau de bord — UPC Freelance';
$appLayout = true;
require_once '../includes/header.php';
?>

<style>
/* ── Variables ───────────────────────────────────────────── */
:root {
    --navy:   #002045;
    --blue:   #0061a5;
    --sky:    #66affe;
    --light:  #eff4ff;
    --white:  #ffffff;
    --muted:  #64748b;
    --border: #e2e8f0;
    --bg:     #f8f9ff;
}

/* ── Page ────────────────────────────────────────────────── */
.dash-wrap { max-width: 1200px; margin: 0 auto; }

/* ── Welcome banner ──────────────────────────────────────── */
.welcome-banner {
    background: linear-gradient(135deg, var(--navy) 0%, #1a365d 60%, #0061a5 100%);
    border-radius: 20px;
    padding: 28px 32px;
    position: relative;
    overflow: hidden;
    margin-bottom: 28px;
}
.welcome-banner::before {
    content: '';
    position: absolute;
    top: -60px; right: -60px;
    width: 220px; height: 220px;
    border-radius: 50%;
    background: rgba(102,175,254,0.12);
}
.welcome-banner::after {
    content: '';
    position: absolute;
    bottom: -40px; right: 120px;
    width: 120px; height: 120px;
    border-radius: 50%;
    background: rgba(255,255,255,0.05);
}

/* ── KPI cards ───────────────────────────────────────────── */
.kpi-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
    margin-bottom: 28px;
}
@media (max-width: 900px) { .kpi-grid { grid-template-columns: repeat(2,1fr); } }
@media (max-width: 480px) { .kpi-grid { grid-template-columns: 1fr; } }

.kpi-card {
    background: var(--white);
    border-radius: 16px;
    border: 1px solid var(--border);
    padding: 20px 22px;
    box-shadow: 0 2px 8px rgba(0,32,69,.05);
    transition: transform 0.2s, box-shadow 0.2s;
    position: relative;
    overflow: hidden;
}
.kpi-card:hover { transform: translateY(-3px); box-shadow: 0 8px 24px rgba(0,32,69,.1); }
.kpi-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 3px;
    border-radius: 16px 16px 0 0;
}
.kpi-card.accent-blue::before  { background: var(--blue); }
.kpi-card.accent-sky::before   { background: var(--sky); }
.kpi-card.accent-green::before { background: #22c55e; }
.kpi-card.accent-amber::before { background: #f59e0b; }
.kpi-card.accent-purple::before{ background: #8b5cf6; }

.kpi-icon {
    width: 40px; height: 40px;
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    margin-bottom: 12px;
    font-size: 20px;
}
.kpi-label {
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: var(--muted);
    margin-bottom: 4px;
}
.kpi-value {
    font-size: 26px;
    font-weight: 800;
    color: var(--navy);
    line-height: 1;
}
.kpi-sub {
    font-size: 11px;
    color: var(--muted);
    margin-top: 6px;
}
.kpi-link {
    font-size: 11px;
    font-weight: 700;
    color: var(--blue);
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 2px;
    margin-top: 8px;
}
.kpi-link:hover { text-decoration: underline; }

/* ── Main grid ───────────────────────────────────────────── */
.main-grid {
    display: grid;
    grid-template-columns: 1fr 340px;
    gap: 20px;
}
@media (max-width: 1024px) { .main-grid { grid-template-columns: 1fr; } }

/* ── Section card ────────────────────────────────────────── */
.section-card {
    background: var(--white);
    border-radius: 16px;
    border: 1px solid var(--border);
    box-shadow: 0 2px 8px rgba(0,32,69,.05);
    overflow: hidden;
    margin-bottom: 20px;
}
.section-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 18px 22px;
    border-bottom: 1px solid #f1f5f9;
}
.section-title {
    font-size: 15px;
    font-weight: 700;
    color: var(--navy);
}
.section-link {
    font-size: 12px;
    font-weight: 600;
    color: var(--blue);
    text-decoration: none;
}
.section-link:hover { text-decoration: underline; }

/* ── Graph bars ──────────────────────────────────────────── */
.graph-wrap {
    padding: 20px 22px 16px;
    display: flex;
    align-items: flex-end;
    gap: 8px;
    height: 140px;
}
.graph-bar-group {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;
    height: 100%;
    justify-content: flex-end;
}
.graph-bar {
    width: 100%;
    max-width: 36px;
    border-radius: 6px 6px 0 0;
    background: #dce9ff;
    transition: background 0.2s, height 0.6s ease;
    cursor: pointer;
    position: relative;
}
.graph-bar.current { background: var(--blue); }
.graph-bar:hover   { background: var(--sky); }
.graph-bar-tooltip {
    position: absolute;
    top: -30px;
    left: 50%;
    transform: translateX(-50%);
    background: var(--navy);
    color: #fff;
    font-size: 10px;
    font-weight: 700;
    padding: 3px 7px;
    border-radius: 6px;
    white-space: nowrap;
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.15s;
}
.graph-bar:hover .graph-bar-tooltip { opacity: 1; }
.graph-label {
    font-size: 10px;
    font-weight: 700;
    color: var(--muted);
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

/* ── Contract row ────────────────────────────────────────── */
.contract-row {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 14px 22px;
    border-bottom: 1px solid #f8faff;
    text-decoration: none;
    transition: background 0.12s;
}
.contract-row:last-child { border-bottom: none; }
.contract-row:hover { background: #f8faff; }

/* Progress bar inline */
.progress-inline {
    flex: 1;
    height: 4px;
    background: #e5eeff;
    border-radius: 999px;
    overflow: hidden;
}
.progress-fill { height: 100%; background: var(--blue); border-radius: 999px; }

/* ── Notification item ───────────────────────────────────── */
.notif-item {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 14px 20px;
    border-bottom: 1px solid #f8faff;
    transition: background 0.12s;
}
.notif-item:last-child { border-bottom: none; }
.notif-item.unread { background: rgba(219,234,254,0.25); }
.notif-dot {
    width: 8px; height: 8px;
    border-radius: 50%;
    background: var(--blue);
    margin-top: 5px;
    flex-shrink: 0;
}
.notif-icon {
    width: 34px; height: 34px;
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
    font-size: 16px;
}

/* ── Activity timeline ───────────────────────────────────── */
.timeline {
    padding: 16px 22px;
    position: relative;
}
.timeline::before {
    content: '';
    position: absolute;
    left: 33px; top: 16px; bottom: 16px;
    width: 2px;
    background: #e5eeff;
}
.timeline-item {
    display: flex;
    align-items: flex-start;
    gap: 14px;
    margin-bottom: 18px;
    position: relative;
}
.timeline-item:last-child { margin-bottom: 0; }
.timeline-dot {
    width: 14px; height: 14px;
    border-radius: 50%;
    flex-shrink: 0;
    margin-top: 3px;
    border: 3px solid var(--white);
    box-shadow: 0 0 0 2px currentColor;
    position: relative;
    z-index: 1;
}

/* ── Calendar popup ──────────────────────────────────────── */
#calendar-popup {
    position: fixed;
    inset: 0;
    z-index: 1000;
    display: none;
    align-items: center;
    justify-content: center;
    background: rgba(0,0,0,0.4);
    backdrop-filter: blur(4px);
}
#calendar-popup.open { display: flex; }
.cal-panel {
    background: var(--white);
    border-radius: 20px;
    padding: 28px;
    width: 360px;
    box-shadow: 0 24px 48px rgba(0,32,69,.2);
    animation: popIn 0.22s ease;
}
@keyframes popIn {
    from { transform: scale(0.92); opacity: 0; }
    to   { transform: scale(1); opacity: 1; }
}
.cal-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 4px;
    margin-top: 12px;
}
.cal-cell {
    aspect-ratio: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    border-radius: 8px;
    cursor: default;
}
.cal-cell.today      { background: var(--blue); color: #fff; font-weight: 700; }
.cal-cell.has-deadline { background: #fee2e2; color: #dc2626; font-weight: 700; cursor: pointer; }
.cal-cell.has-deadline:hover { background: #fecaca; }
.cal-cell.other-month { color: #cbd5e1; }

/* ── Deadline card ───────────────────────────────────────── */
.deadline-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 0;
    border-bottom: 1px solid #f1f5f9;
}
.deadline-item:last-child { border-bottom: none; }
.deadline-date {
    width: 42px; height: 42px;
    border-radius: 10px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    line-height: 1.2;
}
.deadline-date.urgent { background: #fee2e2; color: #dc2626; }
.deadline-date.soon   { background: #fef3c7; color: #b45309; }
.deadline-date.ok     { background: #dcfce7; color: #15803d; }
</style>

<br>
<?php renderFlash(); ?>

<?php
$stmt = $pdo->prepare('SELECT status FROM verification_docs WHERE user_id = ? ORDER BY created_at DESC LIMIT 1');
$stmt->execute([$userId]);
$lastDocStatus = $stmt->fetchColumn();
if (!$user['is_verified'] && $lastDocStatus !== 'pending'): ?>
<div class="mb-5 px-4 py-3 bg-amber-50 border border-amber-200 rounded-2xl flex items-center gap-3">
    <span class="material-symbols-outlined text-amber-500">shield</span>
    <p class="text-sm text-amber-800 flex-1">Vérifiez votre compte pour accéder à toutes les fonctionnalités.</p>
    <a href="../app/verification/index.php"
       class="text-xs font-bold bg-amber-500 text-white px-3 py-1.5 rounded-lg hover:bg-amber-600 transition-colors whitespace-nowrap">
        Vérifier →
    </a>
</div>
<?php elseif ($lastDocStatus === 'pending'): ?>
<div class="mb-5 px-4 py-3 bg-blue-50 border border-blue-200 rounded-2xl flex items-center gap-3">
    <span class="material-symbols-outlined text-secondary">hourglass_top</span>
    <p class="text-sm text-primary flex-1">Vérification en cours — réponse sous 24-48h.</p>
    <a href="../app/verification/index.php" class="text-xs font-bold text-secondary hover:underline">Voir →</a>
</div>
<?php endif; ?>

<div class="dash-wrap">

<!-- ══ WELCOME BANNER ═══════════════════════════════════════ -->
<div class="welcome-banner">
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:16px;position:relative;z-index:1;">
        <div style="display:flex;align-items:center;gap:16px;">
            <?= renderAvatar($user['avatar'] ?? null, $user['first_name'], $user['last_name'] ?? '', (bool)$user['is_verified'], 'w-14 h-14', 'rounded-2xl') ?>
            <div>
                <p style="font-size:12px;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;color:rgba(255,255,255,0.6);margin-bottom:4px;">
                    <?= $role === 'client' ? 'Tableau de bord Client' : 'Tableau de bord Freelancer' ?>
                </p>
                <h1 style="font-size:22px;font-weight:800;color:#fff;margin:0;">
                    Bonjour, <?= h($user['first_name']) ?>
                </h1>
                <p style="font-size:12px;color:rgba(255,255,255,0.65);margin-top:4px;">
                    <?php
                    $days = ['Sunday'=>'Dimanche','Monday'=>'Lundi','Tuesday'=>'Mardi','Wednesday'=>'Mercredi','Thursday'=>'Jeudi','Friday'=>'Vendredi','Saturday'=>'Samedi'];
                    $months = ['January'=>'janvier','February'=>'février','March'=>'mars','April'=>'avril','May'=>'mai','June'=>'juin','July'=>'juillet','August'=>'août','September'=>'septembre','October'=>'octobre','November'=>'novembre','December'=>'décembre'];
                    $dayName   = $days[date('l')]   ?? date('l');
                    $monthName = $months[date('F')]  ?? date('F');
                    echo $dayName . ' ' . date('d') . ' ' . $monthName . ' ' . date('Y');
                    ?>
                </p>
            </div>
        </div>
        <div style="display:flex;gap:10px;flex-wrap:wrap;">
            <?php if ($role === 'client'): ?>
            <a href="../app/projects/create.php"
               style="display:inline-flex;align-items:center;gap:6px;background:rgba(255,255,255,0.15);color:#fff;border:1px solid rgba(255,255,255,0.25);padding:10px 18px;border-radius:12px;font-size:13px;font-weight:600;text-decoration:none;backdrop-filter:blur(4px);transition:background 0.15s;"
               onmouseover="this.style.background='rgba(255,255,255,0.25)'"
               onmouseout="this.style.background='rgba(255,255,255,0.15)'">
                <span class="material-symbols-outlined text-base">add_circle</span> Nouveau projet
            </a>
            <?php else: ?>
            <a href="../app/projects/list.php"
               style="display:inline-flex;align-items:center;gap:6px;background:rgba(255,255,255,0.15);color:#fff;border:1px solid rgba(255,255,255,0.25);padding:10px 18px;border-radius:12px;font-size:13px;font-weight:600;text-decoration:none;backdrop-filter:blur(4px);transition:background 0.15s;"
               onmouseover="this.style.background='rgba(255,255,255,0.25)'"
               onmouseout="this.style.background='rgba(255,255,255,0.15)'">
                <span class="material-symbols-outlined text-base">search</span> Parcourir les projets
            </a>
            <?php endif; ?>
            <a href="../app/wallet/index.php"
               style="display:inline-flex;align-items:center;gap:6px;background:#66affe;color:#fff;padding:10px 18px;border-radius:12px;font-size:13px;font-weight:700;text-decoration:none;transition:opacity 0.15s;"
               onmouseover="this.style.opacity='0.9'" onmouseout="this.style.opacity='1'">
                <span class="material-symbols-outlined text-base">account_balance_wallet</span>
                <?= money((float)$wallet['balance']) ?>
            </a>
        </div>
    </div>
</div>

<!-- ══ KPI CARDS ════════════════════════════════════════════ -->
<div class="kpi-grid">
    <!-- Wallet / gains -->
    <div class="kpi-card accent-blue">
        <div class="kpi-icon" style="background:#eff6ff;">
            <span class="material-symbols-outlined" style="color:#0061a5;font-size:20px;" >account_balance_wallet</span>
        </div>
        <p class="kpi-label">Solde disponible</p>
        <p class="kpi-value" style="font-size:20px;"><?= money((float)$wallet['balance']) ?></p>
        <?php if ($wallet['locked'] > 0): ?>
        <p class="kpi-sub">🔒 <?= money((float)$wallet['locked']) ?> bloqué</p>
        <?php endif; ?>
        <a href="../app/wallet/index.php" class="kpi-link">Gérer <span class="material-symbols-outlined" style="font-size:12px;">arrow_forward</span></a>
    </div>

    <?php if ($role === 'client'): ?>
    <div class="kpi-card accent-sky">
        <div class="kpi-icon" style="background:#f0f9ff;">
            <span class="material-symbols-outlined" style="color:#0ea5e9;font-size:20px;">folder_open</span>
        </div>
        <p class="kpi-label">Mes projets</p>
        <p class="kpi-value"><?= $stats['projets'] ?></p>
        <p class="kpi-sub"><?= $stats['contrats'] ?> contrat<?= $stats['contrats'] > 1 ? 's' : '' ?> actif<?= $stats['contrats'] > 1 ? 's' : '' ?></p>
        <a href="../app/projects/my-projects.php" class="kpi-link">Voir <span class="material-symbols-outlined" style="font-size:12px;">arrow_forward</span></a>
    </div>
    <div class="kpi-card accent-amber">
        <div class="kpi-icon" style="background:#fffbeb;">
            <span class="material-symbols-outlined" style="color:#f59e0b;font-size:20px;">inbox</span>
        </div>
        <p class="kpi-label">Candidatures</p>
        <p class="kpi-value"><?= $stats['postulations'] ?></p>
        <p class="kpi-sub">en attente de réponse</p>
        <a href="../app/postulations/received.php" class="kpi-link">Gérer <span class="material-symbols-outlined" style="font-size:12px;">arrow_forward</span></a>
    </div>
    <div class="kpi-card accent-green">
        <div class="kpi-icon" style="background:#f0fdf4;">
            <span class="material-symbols-outlined" style="color:#22c55e;font-size:20px;">payments</span>
        </div>
        <p class="kpi-label">Total dépensé</p>
        <p class="kpi-value" style="font-size:18px;"><?= money((float)$stats['depenses']) ?></p>
        <p class="kpi-sub">toutes transactions</p>
        <a href="../app/wallet/index.php" class="kpi-link">Historique <span class="material-symbols-outlined" style="font-size:12px;">arrow_forward</span></a>
    </div>

    <?php else: ?>
    <div class="kpi-card accent-sky">
        <div class="kpi-icon" style="background:#f0f9ff;">
            <span class="material-symbols-outlined" style="color:#0ea5e9;font-size:20px;">send</span>
        </div>
        <p class="kpi-label">Candidatures</p>
        <p class="kpi-value"><?= $stats['candidatures'] ?></p>
        <p class="kpi-sub"><?= $stats['contrats'] ?> contrat<?= $stats['contrats'] > 1 ? 's' : '' ?> actif<?= $stats['contrats'] > 1 ? 's' : '' ?></p>
        <a href="../app/postulations/my-applications.php" class="kpi-link">Voir <span class="material-symbols-outlined" style="font-size:12px;">arrow_forward</span></a>
    </div>
    <div class="kpi-card accent-green">
        <div class="kpi-icon" style="background:#f0fdf4;">
            <span class="material-symbols-outlined" style="color:#22c55e;font-size:20px;">payments</span>
        </div>
        <p class="kpi-label">Gains totaux</p>
        <p class="kpi-value" style="font-size:18px;"><?= money((float)($stats['gains'] ?? 0)) ?></p>
        <p class="kpi-sub"><?= $stats['termines'] ?> mission<?= $stats['termines'] > 1 ? 's' : '' ?> terminée<?= $stats['termines'] > 1 ? 's' : '' ?></p>
        <a href="../app/wallet/index.php" class="kpi-link">Historique <span class="material-symbols-outlined" style="font-size:12px;">arrow_forward</span></a>
    </div>
    <div class="kpi-card accent-amber">
        <div class="kpi-icon" style="background:#fffbeb;">
            <span class="material-symbols-outlined" style="color:#f59e0b;font-size:20px;" style="font-variation-settings:'FILL' 1">star</span>
        </div>
        <p class="kpi-label">Note moyenne</p>
        <p class="kpi-value"><?= $stats['rating'] > 0 ? number_format($stats['rating'], 1) : 'N/A' ?></p>
        <p class="kpi-sub">Taux de succès : <?= $successRate ?? 0 ?>%</p>
        <div style="margin-top:8px;height:4px;background:#e5eeff;border-radius:999px;overflow:hidden;">
            <div style="height:100%;background:#f59e0b;border-radius:999px;width:<?= $successRate ?? 0 ?>%;"></div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- ══ GRILLE PRINCIPALE ════════════════════════════════════ -->
<div class="main-grid">

    <!-- ── Colonne gauche ──────────────────────────────── -->
    <div>

        <!-- Graphique revenus -->
        <div class="section-card">
            <div class="section-head">
                <span class="section-title">Revenus mensuels</span>
                <span style="font-size:12px;color:var(--muted);">6 derniers mois</span>
            </div>
            <div class="graph-wrap">
                <?php foreach ($monthlyGains as $i => $mg):
                    $height = $maxGain > 0 ? max(4, round(($mg['amount'] / $maxGain) * 100)) : 4;
                    $isCurrent = $i === count($monthlyGains) - 1;
                ?>
                <div class="graph-bar-group">
                    <div class="graph-bar <?= $isCurrent ? 'current' : '' ?>"
                         style="height:<?= $height ?>%;">
                        <span class="graph-bar-tooltip"><?= money((float)$mg['amount']) ?></span>
                    </div>
                    <span class="graph-label"><?= $mg['label'] ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php
            $totalPeriod = array_sum(array_column($monthlyGains, 'amount'));
            $lastMonth   = $monthlyGains[count($monthlyGains)-1]['amount'];
            $prevMonth   = $monthlyGains[count($monthlyGains)-2]['amount'];
            $trend       = $prevMonth > 0 ? round((($lastMonth - $prevMonth) / $prevMonth) * 100) : 0;
            ?>
            <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 22px;border-top:1px solid #f1f5f9;">
                <div>
                    <p style="font-size:11px;color:var(--muted);font-weight:600;text-transform:uppercase;letter-spacing:0.06em;">Total 6 mois</p>
                    <p style="font-size:18px;font-weight:800;color:var(--navy);"><?= money($totalPeriod) ?></p>
                </div>
                <div style="display:flex;align-items:center;gap:6px;">
                    <span style="font-size:12px;font-weight:700;color:<?= $trend >= 0 ? '#16a34a' : '#dc2626' ?>;">
                        <?= $trend >= 0 ? '+' : '' ?><?= $trend ?>% ce mois
                    </span>
                    <span class="material-symbols-outlined" style="font-size:16px;color:<?= $trend >= 0 ? '#16a34a' : '#dc2626' ?>;">
                        <?= $trend >= 0 ? 'trending_up' : 'trending_down' ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Contrats actifs -->
        <div class="section-card">
            <div class="section-head">
                <span class="section-title">Contrats en cours</span>
                <a href="../app/contracts/list.php" class="section-link">Voir tout →</a>
            </div>
            <?php if (empty($activeContracts)): ?>
            <div style="padding:40px;text-align:center;color:var(--muted);">
                <span class="material-symbols-outlined" style="font-size:40px;display:block;margin-bottom:10px;color:#cbd5e1;">description</span>
                <p style="font-size:13px;">Aucun contrat actif pour le moment.</p>
                <?php if ($role === 'client'): ?>
                <a href="../app/projects/create.php" style="display:inline-block;margin-top:12px;background:var(--navy);color:#fff;padding:8px 18px;border-radius:10px;font-size:12px;font-weight:600;text-decoration:none;">
                    Créer un projet
                </a>
                <?php else: ?>
                <a href="../app/projects/list.php" style="display:inline-block;margin-top:12px;background:var(--navy);color:#fff;padding:8px 18px;border-radius:10px;font-size:12px;font-weight:600;text-decoration:none;">
                    Parcourir les projets
                </a>
                <?php endif; ?>
            </div>
            <?php else: ?>
            <?php foreach ($activeContracts as $c):
                // Calcul progression basé sur la date de début / fin
                $start    = strtotime($c['created_at']);
                $end      = $c['deadline'] ? strtotime($c['deadline']) : strtotime('+30 days', $start);
                $now      = time();
                $progress = $end > $start ? min(100, max(5, round(($now - $start) / ($end - $start) * 100))) : 50;
                $daysLeft = $c['deadline'] ? max(0, (int)ceil(($end - $now) / 86400)) : null;
            ?>
            <a href="../app/contracts/details.php?id=<?= $c['id'] ?>" class="contract-row">
                <?= renderAvatar($c['avatar'] ?? null, $c['first_name'], $c['last_name'], (bool)($c['is_verified'] ?? false), 'w-10 h-10', 'rounded-xl') ?>
                <div style="flex:1;min-width:0;">
                    <p style="font-weight:700;color:var(--navy);font-size:13px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                        <?= h($c['project_title']) ?>
                    </p>
                    <p style="font-size:11px;color:var(--muted);margin:3px 0 6px;">
                        avec <?= h($c['first_name'] . ' ' . $c['last_name']) ?>
                        <?= $daysLeft !== null ? ' · <span style="color:' . ($daysLeft <= 3 ? '#dc2626' : ($daysLeft <= 7 ? '#f59e0b' : '#16a34a')) . ';font-weight:700;">' . $daysLeft . 'j restants</span>' : '' ?>
                    </p>
                    <div class="progress-inline">
                        <div class="progress-fill" style="width:<?= $progress ?>%;background:<?= $progress > 80 ? '#22c55e' : '#0061a5' ?>;"></div>
                    </div>
                </div>
                <div style="text-align:right;flex-shrink:0;">
                    <p style="font-weight:800;color:var(--blue);font-size:13px;"><?= money((float)$c['amount']) ?></p>
                    <p style="font-size:10px;font-weight:700;color:#16a34a;margin-top:2px;">Actif</p>
                </div>
            </a>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Projets récents / disponibles -->
        <div class="section-card">
            <div class="section-head">
                <span class="section-title"><?= $role === 'client' ? 'Mes projets récents' : 'Projets disponibles' ?></span>
                <?php if ($role === 'client'): ?>
                <a href="../app/projects/create.php"
                   style="display:inline-flex;align-items:center;gap:4px;background:var(--navy);color:#fff;padding:6px 14px;border-radius:8px;font-size:11px;font-weight:700;text-decoration:none;">
                    <span class="material-symbols-outlined" style="font-size:14px;">add</span> Nouveau
                </a>
                <?php else: ?>
                <a href="../app/projects/list.php" class="section-link">Voir tout →</a>
                <?php endif; ?>
            </div>
            <?php if (empty($recentProjects)): ?>
            <div style="padding:32px;text-align:center;color:var(--muted);font-size:13px;">Aucun projet.</div>
            <?php else: ?>
            <?php foreach ($recentProjects as $p):
                $statusColors = ['open'=>['#dcfce7','#15803d','Ouvert'],'in_progress'=>['#dbeafe','#1d4ed8','En cours'],'completed'=>['#f1f5f9','#475569','Terminé'],'cancelled'=>['#fee2e2','#dc2626','Annulé']];
                $sc = $statusColors[$p['status']] ?? ['#f1f5f9','#475569',$p['status']];
            ?>
            <a href="../app/projects/details.php?id=<?= $p['id'] ?>" class="contract-row">
                <div style="width:40px;height:40px;border-radius:10px;background:#f1f5f9;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <span class="material-symbols-outlined" style="color:var(--blue);font-size:20px;">work</span>
                </div>
                <div style="flex:1;min-width:0;">
                    <p style="font-weight:700;color:var(--navy);font-size:13px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= h($p['title']) ?></p>
                    <p style="font-size:11px;color:var(--muted);">
                        <?= isset($p['category_name']) ? h($p['category_name']) : '' ?>
                        <?= isset($p['nb_postulations']) ? ' · ' . $p['nb_postulations'] . ' candidature(s)' : '' ?>
                    </p>
                </div>
                <div style="text-align:right;flex-shrink:0;">
                    <span style="font-size:10px;font-weight:700;padding:3px 8px;border-radius:999px;background:<?= $sc[0] ?>;color:<?= $sc[1] ?>;">
                        <?= $sc[2] ?>
                    </span>
                    <?php if ($p['budget_max'] || $p['budget_min']): ?>
                    <p style="font-size:12px;font-weight:700;color:var(--blue);margin-top:4px;">
                        <?= money((float)($p['budget_max'] ?? $p['budget_min'])) ?>
                    </p>
                    <?php endif; ?>
                </div>
            </a>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- ── Colonne droite ──────────────────────────────── -->
    <div>

        <!-- Échéances + calendrier -->
        <div class="section-card" style="margin-bottom:20px;">
            <div class="section-head">
                <span class="section-title">Échéances</span>
                <button onclick="openCalendar()" class="section-link" style="background:none;border:none;cursor:pointer;display:flex;align-items:center;gap:4px;">
                    <span class="material-symbols-outlined" style="font-size:15px;">calendar_month</span>
                    Calendrier
                </button>
            </div>
            <div style="padding:16px 20px;">
                <?php
                $deadlines = [];
                foreach ($activeContracts as $c) {
                    if ($c['deadline']) {
                        $daysLeft = (int)ceil((strtotime($c['deadline']) - time()) / 86400);
                        $deadlines[] = ['title' => $c['project_title'], 'date' => $c['deadline'], 'days' => $daysLeft];
                    }
                }
                usort($deadlines, fn($a,$b) => $a['days'] - $b['days']);
                ?>
                <?php if (empty($deadlines)): ?>
                <p style="font-size:13px;color:var(--muted);text-align:center;padding:20px 0;">
                    Aucune échéance à venir.
                </p>
                <?php else: ?>
                <?php foreach (array_slice($deadlines, 0, 4) as $d):
                    $urgent = $d['days'] <= 2;
                    $soon   = $d['days'] <= 7;
                    $cls    = $urgent ? 'urgent' : ($soon ? 'soon' : 'ok');
                    $dayName = date('D', strtotime($d['date']));
                    $dayNames = ['Mon'=>'LUN','Tue'=>'MAR','Wed'=>'MER','Thu'=>'JEU','Fri'=>'VEN','Sat'=>'SAM','Sun'=>'DIM'];
                    $dayShort = $dayNames[$dayName] ?? $dayName;
                ?>
                <div class="deadline-item">
                    <div class="deadline-date <?= $cls ?>">
                        <span><?= $dayShort ?></span>
                        <span style="font-size:16px;font-weight:800;"><?= date('d', strtotime($d['date'])) ?></span>
                    </div>
                    <div style="flex:1;min-width:0;">
                        <p style="font-size:13px;font-weight:600;color:var(--navy);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= h(truncate($d['title'], 30)) ?></p>
                        <p style="font-size:11px;color:<?= $urgent ? '#dc2626' : ($soon ? '#b45309' : '#16a34a') ?>;font-weight:600;">
                            <?= $d['days'] === 0 ? "Aujourd'hui !" : ($d['days'] === 1 ? 'Demain' : 'Dans ' . $d['days'] . ' jours') ?>
                        </p>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Notifications -->
        <div class="section-card" style="margin-bottom:20px;">
            <div class="section-head">
                <span class="section-title">Notifications</span>
                <a href="../app/notifications/index.php" class="section-link">Voir tout →</a>
            </div>
            <?php if (empty($recentNotifs)): ?>
            <div style="padding:30px;text-align:center;color:var(--muted);font-size:13px;">
                <span class="material-symbols-outlined" style="font-size:32px;display:block;margin-bottom:8px;color:#cbd5e1;">notifications</span>
                Aucune notification
            </div>
            <?php else: ?>
            <?php
            $notifIcons = [
                'new_message'        => ['chat','#dbeafe','#1d4ed8'],
                'payment_received'   => ['payments','#dcfce7','#15803d'],
                'application_accepted'=>['check_circle','#dcfce7','#15803d'],
                'application_rejected'=>['cancel','#fee2e2','#dc2626'],
                'contract_created'   => ['description','#eff6ff','#1d4ed8'],
                'verification_approved'=>['verified','#dcfce7','#15803d'],
                'direct_message'     => ['chat_bubble','#f3e8ff','#7c3aed'],
            ];
            foreach ($recentNotifs as $n):
                $ni = $notifIcons[$n['type']] ?? ['notifications','#f1f5f9','#64748b'];
            ?>
            <div class="notif-item <?= !$n['is_read'] ? 'unread' : '' ?>">
                <div class="notif-icon" style="background:<?= $ni[0] === 'notifications' ? '#f1f5f9' : $ni[1] ?>;">
                    <span class="material-symbols-outlined" style="font-size:16px;color:<?= $ni[2] ?>;"><?= $ni[0] ?></span>
                </div>
                <div style="flex:1;min-width:0;">
                    <p style="font-size:13px;font-weight:<?= $n['is_read'] ? '500' : '700' ?>;color:var(--navy);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                        <?= h($n['title']) ?>
                    </p>
                    <?php if ($n['body']): ?>
                    <p style="font-size:11px;color:var(--muted);margin-top:2px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                        <?= h(truncate($n['body'], 50)) ?>
                    </p>
                    <?php endif; ?>
                    <p style="font-size:10px;color:#94a3b8;margin-top:3px;"><?= timeAgo($n['created_at']) ?></p>
                </div>
                <?php if (!$n['is_read']): ?>
                <div class="notif-dot"></div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Activités récentes (timeline) -->
        <div class="section-card">
            <div class="section-head">
                <span class="section-title">Activités récentes</span>
            </div>
            <div class="timeline">
                <?php if (empty($recentNotifs)): ?>
                <p style="font-size:13px;color:var(--muted);text-align:center;padding:10px 0;">Aucune activité récente.</p>
                <?php else: ?>
                <?php
                $dotColors = [
                    'payment_received'    => '#22c55e',
                    'new_message'         => '#0061a5',
                    'application_accepted'=> '#22c55e',
                    'application_rejected'=> '#ef4444',
                    'contract_created'    => '#0061a5',
                    'direct_message'      => '#8b5cf6',
                ];
                foreach (array_slice($recentNotifs, 0, 4) as $n):
                    $dotColor = $dotColors[$n['type']] ?? '#94a3b8';
                ?>
                <div class="timeline-item">
                    <div class="timeline-dot" style="color:<?= $dotColor ?>;background:<?= $dotColor ?>;"></div>
                    <div>
                        <p style="font-size:13px;font-weight:600;color:var(--navy);"><?= h($n['title']) ?></p>
                        <?php if ($n['body']): ?>
                        <p style="font-size:11px;color:var(--muted);margin-top:2px;line-height:1.4;"><?= h(truncate($n['body'], 60)) ?></p>
                        <?php endif; ?>
                        <p style="font-size:10px;color:#94a3b8;margin-top:4px;"><?= timeAgo($n['created_at']) ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div><!-- /.main-grid -->
</div><!-- /.dash-wrap -->

<!-- ══ POPUP CALENDRIER ═════════════════════════════════════ -->
<div id="calendar-popup" onclick="if(event.target===this)closeCalendar()">
    <div class="cal-panel">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
            <h3 style="font-size:16px;font-weight:700;color:var(--navy);" id="cal-title"></h3>
            <button onclick="closeCalendar()"
                    style="background:none;border:none;cursor:pointer;padding:6px;border-radius:8px;"
                    onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='none'">
                <span class="material-symbols-outlined" style="color:#64748b;">close</span>
            </button>
        </div>

        <!-- Jours de la semaine -->
        <div class="cal-grid" style="margin-bottom:4px;">
            <?php foreach (['L','M','M','J','V','S','D'] as $d): ?>
            <div style="text-align:center;font-size:10px;font-weight:700;color:#94a3b8;padding:4px 0;"><?= $d ?></div>
            <?php endforeach; ?>
        </div>

        <div class="cal-grid" id="cal-grid"></div>

        <!-- Légende -->
        <div style="display:flex;gap:16px;margin-top:16px;padding-top:14px;border-top:1px solid #f1f5f9;">
            <div style="display:flex;align-items:center;gap:6px;font-size:11px;color:#64748b;">
                <div style="width:10px;height:10px;border-radius:3px;background:#0061a5;"></div> Aujourd'hui
            </div>
            <div style="display:flex;align-items:center;gap:6px;font-size:11px;color:#64748b;">
                <div style="width:10px;height:10px;border-radius:3px;background:#fee2e2;border:1px solid #fca5a5;"></div> Échéance
            </div>
        </div>

        <?php if (!empty($deadlines)): ?>
        <div style="margin-top:14px;padding-top:14px;border-top:1px solid #f1f5f9;">
            <p style="font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.06em;margin-bottom:10px;">Deadlines ce mois</p>
            <?php foreach ($deadlines as $d): ?>
            <div style="display:flex;align-items:center;gap:10px;padding:8px 0;border-bottom:1px solid #f8faff;">
                <span style="font-size:12px;font-weight:700;color:#dc2626;white-space:nowrap;"><?= date('d/m', strtotime($d['date'])) ?></span>
                <span style="font-size:12px;color:var(--navy);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= h(truncate($d['title'], 28)) ?></span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
// ── Calendrier JS ─────────────────────────────────────────
const DEADLINE_DATES = <?= json_encode(array_map(fn($d) => date('Y-m-d', strtotime($d['date'])), $deadlines ?? [])) ?>;

function openCalendar() {
    document.getElementById('calendar-popup').classList.add('open');
    renderCalendar(new Date());
    document.addEventListener('keydown', onEscCal);
}
function closeCalendar() {
    document.getElementById('calendar-popup').classList.remove('open');
    document.removeEventListener('keydown', onEscCal);
}
function onEscCal(e) { if (e.key === 'Escape') closeCalendar(); }

function renderCalendar(date) {
    const months = ['Janvier','Février','Mars','Avril','Mai','Juin','Juillet','Août','Septembre','Octobre','Novembre','Décembre'];
    const today  = new Date(); today.setHours(0,0,0,0);
    const year   = date.getFullYear();
    const month  = date.getMonth();

    document.getElementById('cal-title').textContent = months[month] + ' ' + year;

    const firstDay = new Date(year, month, 1).getDay(); // 0=Sun
    const startOffset = firstDay === 0 ? 6 : firstDay - 1; // Lundi = 0
    const daysInMonth = new Date(year, month + 1, 0).getDate();

    const grid = document.getElementById('cal-grid');
    grid.innerHTML = '';

    // Jours vides avant le 1er
    for (let i = 0; i < startOffset; i++) {
        const cell = document.createElement('div');
        cell.className = 'cal-cell other-month';
        grid.appendChild(cell);
    }

    for (let d = 1; d <= daysInMonth; d++) {
        const cell    = document.createElement('div');
        const cellDate = new Date(year, month, d);
        cellDate.setHours(0,0,0,0);
        const dateStr = cellDate.toISOString().split('T')[0];

        cell.className = 'cal-cell';
        cell.textContent = d;

        if (cellDate.getTime() === today.getTime()) {
            cell.classList.add('today');
        } else if (DEADLINE_DATES.includes(dateStr)) {
            cell.classList.add('has-deadline');
            cell.title = 'Échéance ce jour';
        }
        grid.appendChild(cell);
    }
}
</script>

<?php
$appLayout = true;
require_once '../includes/footer.php';
?>