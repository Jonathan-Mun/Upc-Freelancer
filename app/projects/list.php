<?php
// ============================================================
// UPC FREELANCE — Liste des projets (Marketplace)
// ../../app/projects/list.php
// ============================================================

require_once '../../includes/middleware.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../includes/db.php';

$pdo = getDB();

// Filtres
$search     = sanitize($_GET['search']     ?? '');
$categoryId = (int)($_GET['category']      ?? 0);
$budgetMin  = (float)($_GET['budget_min']  ?? 0);
$budgetMax  = (float)($_GET['budget_max']  ?? 0);
$sort       = in_array($_GET['sort'] ?? '', ['recent','budget_asc','budget_desc']) ? $_GET['sort'] : 'recent';
$page       = max(1, (int)($_GET['page']   ?? 1));
$perPage    = 9; // ← 9 projets par page

// Requête
$where  = [
    'p.status = "open"',
    'p.visibility = "public"',
    'p.deadline IS NULL OR p.deadline >= CURDATE()'  // ← IMPORTANT: Exclure les projets expirés
];
$params = [];

if ($search) {
    $where[]  = '(p.title LIKE ? OR p.description LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($categoryId) {
    $where[]  = 'p.category_id = ?';
    $params[] = $categoryId;
}
if ($budgetMin > 0) {
    $where[]  = '(p.budget_min >= ? OR p.budget_max >= ?)';
    $params[] = $budgetMin;
    $params[] = $budgetMin;
}
if ($budgetMax > 0) {
    $where[]  = '(p.budget_max <= ? OR p.budget_min <= ?)';
    $params[] = $budgetMax;
    $params[] = $budgetMax;
}

$whereClause = 'WHERE ' . implode(' AND ', $where);
$orderClause = match($sort) {
    'budget_asc'  => 'ORDER BY COALESCE(p.budget_min, p.budget_max) ASC',
    'budget_desc' => 'ORDER BY COALESCE(p.budget_max, p.budget_min) DESC',
    default       => 'ORDER BY p.created_at DESC',
};

// Total
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM projects p $whereClause");
$countStmt->execute($params);
$total      = (int)$countStmt->fetchColumn();
$totalPages = (int)ceil($total / $perPage);
$offset     = ($page - 1) * $perPage;

// Projets
$stmt = $pdo->prepare("
    SELECT p.*, c.name AS category_name, c.icon AS category_icon, c.slug AS category_slug,
           u.first_name, u.last_name, u.avatar, u.is_verified,
           (SELECT COUNT(*) FROM postulations WHERE project_id = p.id) AS nb_postulations
    FROM projects p
    LEFT JOIN categories c ON c.id = p.category_id
    JOIN users u ON u.id = p.client_id
    $whereClause
    $orderClause
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$projects = $stmt->fetchAll();

// Catégories
$categories = $pdo->query('SELECT * FROM categories WHERE is_active = 1 ORDER BY name')->fetchAll();

// Couleurs badge catégorie
$catColors = [
    'dev-web'      => 'bg-blue-50 text-blue-700 border-blue-100',
    'design'       => 'bg-purple-50 text-purple-700 border-purple-100',
    'marketing'    => 'bg-orange-50 text-orange-700 border-orange-100',
    'redaction'    => 'bg-teal-50 text-teal-700 border-teal-100',
    'data'         => 'bg-cyan-50 text-cyan-700 border-cyan-100',
    'video-audio'  => 'bg-pink-50 text-pink-700 border-pink-100',
    'finance'      => 'bg-green-50 text-green-700 border-green-100',
    'informatique' => 'bg-indigo-50 text-indigo-700 border-indigo-100',
];

// Construire l'URL de base pour la pagination (conserve tous les filtres)
$baseUrl = '/upc_freelance/app/projects/list.php?'
    . http_build_query(array_filter([
        'search'     => $search,
        'category'   => $categoryId ?: null,
        'budget_min' => $budgetMin ?: null,
        'budget_max' => $budgetMax ?: null,
        'sort'       => $sort !== 'recent' ? $sort : null,
    ]));

$pageTitle = 'Marketplace — UPC Freelance';
$appLayout = true;
require_once '../../includes/header.php';
?>
<br>
<!-- ── En-tête ────────────────────────────────────────────────── -->
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-8">
    <div>
        <h1 class="font-h1 text-h1 text-primary leading-tight">Marketplace</h1>
        <p class="text-on-surface-variant text-sm mt-1">
            <strong class="text-primary"><?= $total ?></strong>
            projet<?= $total > 1 ? 's' : '' ?> disponible<?= $total > 1 ? 's' : '' ?>
            <?php if ($page > 1): ?>
            — page <?= $page ?> / <?= $totalPages ?>
            <?php endif; ?>
        </p>
    </div>
    <?php if (isLoggedIn() && currentUser()['role'] === 'client'): ?>
    <a href="/upc_freelance/app/projects/create.php"
       class="inline-flex items-center gap-2 bg-secondary text-white font-button text-button
              px-5 py-2.5 rounded-xl shadow-[0px_4px_12px_rgba(0,97,165,0.25)]
              hover:-translate-y-0.5 transition-all active:scale-95 w-fit">
        <span class="material-symbols-outlined text-[18px]">add</span>
        Publier un projet
    </a>
    <?php endif; ?>
</div>

<div class="flex flex-col lg:flex-row gap-6">

    <!-- ── Filtres sidebar ───────────────────────────────────── -->
    <aside class="lg:w-64 flex-shrink-0">
        <form method="GET"
              class="bg-white rounded-xl border border-slate-200 p-5
                     shadow-[0px_4px_12px_rgba(26,54,93,0.05)] space-y-5 sticky top-20">

            <div class="flex items-center justify-between">
                <h3 class="font-semibold text-primary">Filtres</h3>
                <?php if ($search || $categoryId || $budgetMin || $budgetMax): ?>
                <a href="/upc_freelance/app/projects/list.php"
                   class="text-xs text-secondary hover:underline flex items-center gap-0.5">
                    <span class="material-symbols-outlined text-sm">close</span> Effacer
                </a>
                <?php endif; ?>
            </div>

            <!-- Recherche -->
            <div>
                <label class="block font-label-caps text-label-caps text-on-surface-variant mb-1.5 uppercase">
                    Recherche
                </label>
                <div class="relative">
                    <span class="absolute left-3 top-1/2 -translate-y-1/2 material-symbols-outlined text-slate-400 text-lg">search</span>
                    <input type="text" name="search" value="<?= h($search) ?>"
                           placeholder="Mot-clé..."
                           class="w-full pl-9 pr-3 py-2.5 rounded-xl border border-outline-variant
                                  focus:border-secondary focus:ring-1 focus:ring-secondary/30
                                  outline-none text-sm"/>
                </div>
            </div>

            <!-- Catégorie -->
            <div>
                <label class="block font-label-caps text-label-caps text-on-surface-variant mb-1.5 uppercase">
                    Catégorie
                </label>
                <select name="category"
                        class="w-full py-2.5 px-3 rounded-xl border border-outline-variant
                               text-sm focus:border-secondary outline-none bg-white">
                    <option value="0">Toutes les catégories</option>
                    <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['id'] ?>" <?= $categoryId === (int)$cat['id'] ? 'selected' : '' ?>>
                        <?= h($cat['name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Budget -->
            <div>
                <label class="block font-label-caps text-label-caps text-on-surface-variant mb-1.5 uppercase">
                    Budget (USD)
                </label>
                <div class="grid grid-cols-2 gap-2">
                    <input type="number" name="budget_min" placeholder="Min"
                           value="<?= $budgetMin ?: '' ?>"
                           class="w-full py-2.5 px-3 rounded-xl border border-outline-variant
                                  text-sm focus:border-secondary outline-none"/>
                    <input type="number" name="budget_max" placeholder="Max"
                           value="<?= $budgetMax ?: '' ?>"
                           class="w-full py-2.5 px-3 rounded-xl border border-outline-variant
                                  text-sm focus:border-secondary outline-none"/>
                </div>
            </div>

            <!-- Tri -->
            <div>
                <label class="block font-label-caps text-label-caps text-on-surface-variant mb-1.5 uppercase">
                    Trier par
                </label>
                <select name="sort"
                        class="w-full py-2.5 px-3 rounded-xl border border-outline-variant
                               text-sm focus:border-secondary outline-none bg-white">
                    <option value="recent"      <?= $sort === 'recent'      ? 'selected' : '' ?>>Plus récents</option>
                    <option value="budget_asc"  <?= $sort === 'budget_asc'  ? 'selected' : '' ?>>Budget croissant</option>
                    <option value="budget_desc" <?= $sort === 'budget_desc' ? 'selected' : '' ?>>Budget décroissant</option>
                </select>
            </div>

            <button type="submit"
                    class="w-full bg-primary text-white py-2.5 rounded-xl text-sm font-button
                           hover:opacity-90 transition-opacity active:scale-95">
                Appliquer les filtres
            </button>
        </form>
    </aside>

    <!-- ── Grille projets ─────────────────────────────────────── -->
    <div class="flex-1 min-w-0">

        <?php if (empty($projects)): ?>
        <div class="bg-white rounded-xl border border-slate-200 p-16 text-center
                    shadow-[0px_4px_12px_rgba(26,54,93,0.05)]">
            <span class="material-symbols-outlined text-5xl text-slate-300 block mb-4">search_off</span>
            <h3 class="font-semibold text-primary mb-2">Aucun projet trouvé</h3>
            <p class="text-on-surface-variant text-sm mb-4">Essayez de modifier vos filtres de recherche.</p>
            <a href="/upc_freelance/app/projects/list.php"
               class="inline-block text-sm text-secondary hover:underline">
                Voir tous les projets
            </a>
        </div>

        <?php else: ?>

        <!-- Grille 3 colonnes → 9 cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4 mb-8">
            <?php foreach ($projects as $p):
                $catBadge = $catColors[$p['category_slug'] ?? ''] ?? 'bg-slate-50 text-slate-600 border-slate-100';
                $isDeadlineClose = false;
                if ($p['deadline']) {
                    $daysLeft = (int)((strtotime($p['deadline']) - time()) / 86400);
                    $isDeadlineClose = $daysLeft >= 0 && $daysLeft <= 3;
                }
            ?>
            <a href="/upc_freelance/app/projects/details.php?id=<?= $p['id'] ?>"
               class="group flex flex-col bg-white rounded-xl border border-slate-200
                      hover:border-secondary hover:shadow-[0px_8px_24px_rgba(26,54,93,0.12)]
                      hover:-translate-y-0.5 transition-all p-5
                      shadow-[0px_4px_12px_rgba(26,54,93,0.05)]">

                <!-- Catégorie + date -->
                <div class="flex items-center justify-between mb-3">
                    <span class="inline-flex items-center gap-1 text-[10px] font-label-caps font-semibold
                                 uppercase px-2.5 py-1 rounded-full border <?= $catBadge ?>">
                        <span class="material-symbols-outlined text-sm"><?= h($p['category_icon'] ?? 'work') ?></span>
                        <?= h($p['category_name'] ?? 'Général') ?>
                    </span>
                    <span class="text-xs text-slate-400"><?= timeAgo($p['created_at']) ?></span>
                </div>

                <!-- Titre -->
                <h3 class="font-semibold text-base text-primary group-hover:text-secondary
                           transition-colors mb-2 line-clamp-2 leading-snug flex-1">
                    <?= h($p['title']) ?>
                </h3>

                <!-- Description -->
                <p class="text-sm text-on-surface-variant mb-4 line-clamp-2 leading-relaxed">
                    <?= h(truncate($p['description'], 90)) ?>
                </p>

                <!-- Budget + deadline -->
                <?php if ($p['budget_min'] || $p['budget_max'] || $p['deadline']): ?>
                <div class="flex items-end justify-between mb-4">
                    <?php if ($p['budget_min'] || $p['budget_max']): ?>
                    <div>
                        <p class="text-[10px] font-label-caps text-slate-400 uppercase mb-0.5">Budget</p>
                        <p class="text-sm font-bold text-secondary">
                            <?php
                            if ($p['budget_min'] && $p['budget_max'])
                                echo money((float)$p['budget_min']) . ' – ' . money((float)$p['budget_max']);
                            else
                                echo money((float)($p['budget_max'] ?? $p['budget_min']));
                            ?>
                        </p>
                    </div>
                    <?php endif; ?>
                    <?php if ($p['deadline']): ?>
                    <div class="text-right">
                        <p class="text-[10px] font-label-caps text-slate-400 uppercase mb-0.5">
                            <?= $isDeadlineClose ? 'Urgent' : 'Délai' ?>
                        </p>
                        <p class="text-xs font-medium <?= $isDeadlineClose ? 'text-red-500 font-bold' : 'text-primary' ?>">
                            <?= formatDate($p['deadline']) ?>
                        </p>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <!-- Footer : client + candidatures -->
                <div class="flex items-center justify-between pt-3 border-t border-slate-100 mt-auto">
                    <div class="flex items-center gap-2">
                        <?php if ($p['avatar']): ?>
                        <img src="/upc_freelance/storage/<?= h($p['avatar']) ?>" alt="Avatar"
                             class="w-6 h-6 rounded-full object-cover flex-shrink-0"/>
                        <?php else: ?>
                        <div class="w-6 h-6 rounded-full bg-primary/10 flex items-center justify-center text-[10px] font-bold text-primary flex-shrink-0">
                            <?= mb_strtoupper(mb_substr($p['first_name'],0,1)) ?>
                        </div>
                        <?php endif; ?>
                        <span class="text-xs text-on-surface-variant truncate max-w-[80px]">
                            <?= h($p['first_name']) ?>
                        </span>
                        <?php if ($p['is_verified']): ?>
                        <span class="material-symbols-outlined text-secondary"
                              style="font-size:13px;font-variation-settings:'FILL' 1"
                              title="Client vérifié">verified</span>
                        <?php endif; ?>
                    </div>
                    <span class="flex items-center gap-1 text-xs text-slate-400 whitespace-nowrap">
                        <span class="material-symbols-outlined text-sm">group</span>
                        <?= $p['nb_postulations'] ?>
                        candidature<?= $p['nb_postulations'] > 1 ? 's' : '' ?>
                    </span>
                </div>
            </a>
            <?php endforeach; ?>
        </div>

        <!-- ── Pagination ─────────────────────────────────────── -->
        <?php if ($totalPages > 1): ?>
        <div class="flex flex-col sm:flex-row items-center justify-between gap-4 pt-2">

            <!-- Infos -->
            <p class="text-xs text-slate-500 order-2 sm:order-1">
                Affichage
                <strong class="text-primary"><?= ($offset + 1) ?></strong>–<strong class="text-primary"><?= min($offset + $perPage, $total) ?></strong>
                sur <strong class="text-primary"><?= $total ?></strong> projet<?= $total > 1 ? 's' : '' ?>
            </p>

            <!-- Boutons -->
            <div class="flex items-center gap-1.5 order-1 sm:order-2">

                <!-- Première page -->
                <?php if ($page > 1): ?>
                <a href="<?= $baseUrl ?>&page=1"
                   class="p-2 border border-slate-200 rounded-xl hover:bg-slate-50 transition-colors"
                   title="Première page">
                    <span class="material-symbols-outlined text-[18px] text-slate-500">first_page</span>
                </a>
                <!-- Précédent -->
                <a href="<?= $baseUrl ?>&page=<?= $page - 1 ?>"
                   class="p-2 border border-slate-200 rounded-xl hover:bg-slate-50 transition-colors">
                    <span class="material-symbols-outlined text-[18px] text-slate-500">chevron_left</span>
                </a>
                <?php else: ?>
                <button disabled class="p-2 border border-slate-200 rounded-xl opacity-30 cursor-not-allowed">
                    <span class="material-symbols-outlined text-[18px]">first_page</span>
                </button>
                <button disabled class="p-2 border border-slate-200 rounded-xl opacity-30 cursor-not-allowed">
                    <span class="material-symbols-outlined text-[18px]">chevron_left</span>
                </button>
                <?php endif; ?>

                <!-- Pages numérotées (fenêtre de 5) -->
                <?php
                $window  = 2; // pages de chaque côté
                $start   = max(1, $page - $window);
                $end     = min($totalPages, $page + $window);
                // Toujours afficher au moins 5 pages
                if ($end - $start < 4) {
                    if ($start === 1) $end = min($totalPages, $start + 4);
                    else $start = max(1, $end - 4);
                }
                ?>

                <?php if ($start > 1): ?>
                <a href="<?= $baseUrl ?>&page=1"
                   class="w-9 h-9 flex items-center justify-center rounded-xl text-sm
                          border border-slate-200 text-slate-600 hover:bg-slate-50 transition-colors">
                    1
                </a>
                <?php if ($start > 2): ?>
                <span class="w-9 h-9 flex items-center justify-center text-slate-400 text-sm">…</span>
                <?php endif; ?>
                <?php endif; ?>

                <?php for ($i = $start; $i <= $end; $i++): ?>
                <a href="<?= $baseUrl ?>&page=<?= $i ?>"
                   class="w-9 h-9 flex items-center justify-center rounded-xl text-sm font-semibold
                          transition-colors
                          <?= $i === $page
                                ? 'bg-secondary text-white shadow-sm border border-secondary'
                                : 'border border-slate-200 text-slate-600 hover:bg-slate-50' ?>">
                    <?= $i ?>
                </a>
                <?php endfor; ?>

                <?php if ($end < $totalPages): ?>
                <?php if ($end < $totalPages - 1): ?>
                <span class="w-9 h-9 flex items-center justify-center text-slate-400 text-sm">…</span>
                <?php endif; ?>
                <a href="<?= $baseUrl ?>&page=<?= $totalPages ?>"
                   class="w-9 h-9 flex items-center justify-center rounded-xl text-sm
                          border border-slate-200 text-slate-600 hover:bg-slate-50 transition-colors">
                    <?= $totalPages ?>
                </a>
                <?php endif; ?>

                <!-- Suivant -->
                <?php if ($page < $totalPages): ?>
                <a href="<?= $baseUrl ?>&page=<?= $page + 1 ?>"
                   class="p-2 border border-slate-200 rounded-xl hover:bg-slate-50 transition-colors">
                    <span class="material-symbols-outlined text-[18px] text-slate-500">chevron_right</span>
                </a>
                <!-- Dernière page -->
                <a href="<?= $baseUrl ?>&page=<?= $totalPages ?>"
                   class="p-2 border border-slate-200 rounded-xl hover:bg-slate-50 transition-colors"
                   title="Dernière page">
                    <span class="material-symbols-outlined text-[18px] text-slate-500">last_page</span>
                </a>
                <?php else: ?>
                <button disabled class="p-2 border border-slate-200 rounded-xl opacity-30 cursor-not-allowed">
                    <span class="material-symbols-outlined text-[18px]">chevron_right</span>
                </button>
                <button disabled class="p-2 border border-slate-200 rounded-xl opacity-30 cursor-not-allowed">
                    <span class="material-symbols-outlined text-[18px]">last_page</span>
                </button>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php endif; ?>
    </div>
</div>

<?php $appLayout = true; require_once '../../includes/footer.php'; ?>