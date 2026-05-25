<?php
// ============================================================
// UPC FREELANCE — Liste des projets (Marketplace)
// /var/www/html/upc_freelance/app/projects/list.php
// ============================================================

require_once '/var/www/html/upc_freelance/includes/middleware.php';
require_once '/var/www/html/upc_freelance/includes/auth.php';
require_once '/var/www/html/upc_freelance/includes/functions.php';
require_once '/var/www/html/upc_freelance/includes/db.php';

$pdo = getDB();

// Filtres
$search     = sanitize($_GET['search']   ?? '');
$categoryId = (int)($_GET['category']    ?? 0);
$budgetMin  = (float)($_GET['budget_min']?? 0);
$budgetMax  = (float)($_GET['budget_max']?? 0);
$sort       = in_array($_GET['sort'] ?? '', ['recent','budget_asc','budget_desc']) ? $_GET['sort'] : 'recent';
$page       = max(1, (int)($_GET['page'] ?? 1));
$perPage    = 12;

// Construction requête
$where  = ['p.status = "open"', 'p.visibility = "public"'];
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
    $where[]  = 'p.budget_min >= ?';
    $params[] = $budgetMin;
}
if ($budgetMax > 0) {
    $where[]  = 'p.budget_max <= ?';
    $params[] = $budgetMax;
}

$whereClause = 'WHERE ' . implode(' AND ', $where);
$orderClause = match($sort) {
    'budget_asc'  => 'ORDER BY p.budget_min ASC',
    'budget_desc' => 'ORDER BY p.budget_max DESC',
    default       => 'ORDER BY p.created_at DESC',
};

// Total
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM projects p $whereClause");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$pagination = paginate($total, $perPage, $page, '/upc_freelance/app/projects/list.php?search=' . urlencode($search) . '&category=' . $categoryId . '&sort=' . $sort);

// Projets
$stmt = $pdo->prepare("
    SELECT p.*, c.name AS category_name, c.icon AS category_icon,
           u.first_name, u.last_name,
           (SELECT COUNT(*) FROM postulations WHERE project_id = p.id) AS nb_postulations
    FROM projects p
    LEFT JOIN categories c ON c.id = p.category_id
    JOIN users u ON u.id = p.client_id
    $whereClause
    $orderClause
    LIMIT $perPage OFFSET {$pagination['offset']}
");
$stmt->execute($params);
$projects = $stmt->fetchAll();

// Catégories pour filtre
$categories = $pdo->query('SELECT * FROM categories WHERE is_active = 1 ORDER BY name')->fetchAll();

$pageTitle = 'Marketplace — UPC Freelance';
$appLayout = true;
require_once '/var/www/html/upc_freelance/includes/header.php';
?>

<!-- En-tête -->
<div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
    <div>
        <h1 class="text-2xl font-bold text-primary">Marketplace des projets</h1>
        <p class="text-on-surface-variant text-sm mt-1"><?= $total ?> projet<?= $total > 1 ? 's' : '' ?> disponible<?= $total > 1 ? 's' : '' ?></p>
    </div>
    <?php if (isLoggedIn() && currentUser()['role'] === 'client'): ?>
    <a href="/upc_freelance/app/projects/create.php"
       class="inline-flex items-center gap-2 bg-primary text-white font-button text-button px-5 py-2.5 rounded-xl hover:opacity-90 transition-opacity active:scale-95">
        <span class="material-symbols-outlined">add</span> Publier un projet
    </a>
    <?php endif; ?>
</div>

<div class="flex flex-col lg:flex-row gap-6">

    <!-- ── Filtres sidebar ───────────────────────────────── -->
    <aside class="lg:w-64 flex-shrink-0">
        <form method="GET" class="bg-white rounded-2xl border border-slate-100 p-5 custom-shadow-low space-y-5">
            <h3 class="font-semibold text-primary">Filtres</h3>

            <!-- Recherche -->
            <div>
                <label class="block text-xs font-medium text-on-surface-variant mb-1.5 uppercase tracking-wide">Recherche</label>
                <div class="relative">
                    <span class="absolute left-3 top-1/2 -translate-y-1/2 material-symbols-outlined text-slate-400 text-lg">search</span>
                    <input type="text" name="search" value="<?= h($search) ?>" placeholder="Mot-clé..."
                           class="w-full pl-9 pr-3 py-2.5 rounded-xl border border-outline-variant focus:border-secondary focus:ring-1 focus:ring-secondary/30 outline-none text-sm"/>
                </div>
            </div>

            <!-- Catégorie -->
            <div>
                <label class="block text-xs font-medium text-on-surface-variant mb-1.5 uppercase tracking-wide">Catégorie</label>
                <select name="category" class="w-full py-2.5 px-3 rounded-xl border border-outline-variant text-sm focus:border-secondary outline-none">
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
                <label class="block text-xs font-medium text-on-surface-variant mb-1.5 uppercase tracking-wide">Budget (XOF)</label>
                <div class="grid grid-cols-2 gap-2">
                    <input type="number" name="budget_min" placeholder="Min" value="<?= $budgetMin ?: '' ?>"
                           class="w-full py-2.5 px-3 rounded-xl border border-outline-variant text-sm focus:border-secondary outline-none"/>
                    <input type="number" name="budget_max" placeholder="Max" value="<?= $budgetMax ?: '' ?>"
                           class="w-full py-2.5 px-3 rounded-xl border border-outline-variant text-sm focus:border-secondary outline-none"/>
                </div>
            </div>

            <!-- Tri -->
            <div>
                <label class="block text-xs font-medium text-on-surface-variant mb-1.5 uppercase tracking-wide">Trier par</label>
                <select name="sort" class="w-full py-2.5 px-3 rounded-xl border border-outline-variant text-sm focus:border-secondary outline-none">
                    <option value="recent"       <?= $sort === 'recent'       ? 'selected' : '' ?>>Plus récents</option>
                    <option value="budget_asc"   <?= $sort === 'budget_asc'   ? 'selected' : '' ?>>Budget croissant</option>
                    <option value="budget_desc"  <?= $sort === 'budget_desc'  ? 'selected' : '' ?>>Budget décroissant</option>
                </select>
            </div>

            <button type="submit" class="w-full bg-primary text-white py-2.5 rounded-xl text-sm font-button hover:opacity-90 transition-opacity">
                Appliquer
            </button>
            <?php if ($search || $categoryId || $budgetMin || $budgetMax): ?>
            <a href="/upc_freelance/app/projects/list.php" class="block text-center text-xs text-secondary hover:underline">
                Effacer les filtres
            </a>
            <?php endif; ?>
        </form>
    </aside>

    <!-- ── Grille projets ────────────────────────────────── -->
    <div class="flex-1 min-w-0">
        <?php if (empty($projects)): ?>
        <div class="bg-white rounded-2xl border border-slate-100 p-16 text-center custom-shadow-low">
            <span class="material-symbols-outlined text-5xl text-slate-300 block mb-4">search_off</span>
            <h3 class="font-semibold text-primary mb-2">Aucun projet trouvé</h3>
            <p class="text-on-surface-variant text-sm">Essayez de modifier vos filtres de recherche.</p>
        </div>
        <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4 mb-6">
            <?php foreach ($projects as $p): ?>
            <a href="/upc_freelance/app/projects/details.php?id=<?= $p['id'] ?>"
               class="group block bg-white rounded-2xl border border-slate-100 hover:border-secondary hover:shadow-md transition-all p-5 custom-shadow-low">

                <!-- Catégorie + date -->
                <div class="flex justify-between items-center mb-3">
                    <span class="inline-flex items-center gap-1 text-xs bg-surface-container text-secondary px-2.5 py-1 rounded-full font-medium">
                        <span class="material-symbols-outlined text-sm"><?= h($p['category_icon'] ?? 'work') ?></span>
                        <?= h($p['category_name'] ?? 'Général') ?>
                    </span>
                    <span class="text-xs text-slate-400"><?= timeAgo($p['created_at']) ?></span>
                </div>

                <!-- Titre -->
                <h3 class="font-semibold text-primary group-hover:text-secondary transition-colors mb-2 line-clamp-2 leading-snug">
                    <?= h($p['title']) ?>
                </h3>

                <!-- Description -->
                <p class="text-sm text-on-surface-variant mb-4 line-clamp-2">
                    <?= h(truncate($p['description'], 100)) ?>
                </p>

                <!-- Budget + Deadline -->
                <div class="flex items-center justify-between mb-3">
                    <?php if ($p['budget_min'] || $p['budget_max']): ?>
                    <div>
                        <p class="text-xs text-slate-400">Budget</p>
                        <p class="text-sm font-bold text-secondary">
                            <?php
                            if ($p['budget_min'] && $p['budget_max']) {
                                echo money((float)$p['budget_min']) . ' – ' . money((float)$p['budget_max']);
                            } else {
                                echo money((float)($p['budget_max'] ?? $p['budget_min']));
                            }
                            ?>
                        </p>
                    </div>
                    <?php endif; ?>
                    <?php if ($p['deadline']): ?>
                    <div class="text-right">
                        <p class="text-xs text-slate-400">Délai</p>
                        <p class="text-xs font-medium text-primary"><?= formatDate($p['deadline']) ?></p>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Footer -->
                <div class="flex items-center justify-between pt-3 border-t border-slate-100">
                    <div class="flex items-center gap-1.5">
                        <div class="w-6 h-6 rounded-full bg-primary/10 flex items-center justify-center text-xs font-bold text-primary">
                            <?= mb_substr($p['first_name'], 0, 1) ?>
                        </div>
                        <span class="text-xs text-on-surface-variant"><?= h($p['first_name']) ?></span>
                    </div>
                    <span class="text-xs text-slate-400">
                        <span class="material-symbols-outlined text-sm align-middle">group</span>
                        <?= $p['nb_postulations'] ?> candidature<?= $p['nb_postulations'] > 1 ? 's' : '' ?>
                    </span>
                </div>
            </a>
            <?php endforeach; ?>
        </div>

        <!-- Pagination -->
        <?php if ($pagination['total_pages'] > 1): ?>
        <div class="flex justify-center gap-2">
            <?php if ($pagination['has_prev']): ?>
            <a href="<?= $pagination['base_url'] ?>&page=<?= $page - 1 ?>"
               class="px-4 py-2 rounded-xl border border-slate-200 text-sm hover:border-secondary hover:text-secondary transition-colors">
                ← Précédent
            </a>
            <?php endif; ?>

            <?php for ($i = max(1, $page-2); $i <= min($pagination['total_pages'], $page+2); $i++): ?>
            <a href="<?= $pagination['base_url'] ?>&page=<?= $i ?>"
               class="px-4 py-2 rounded-xl border text-sm transition-colors <?= $i === $page ? 'bg-primary text-white border-primary' : 'border-slate-200 hover:border-secondary hover:text-secondary' ?>">
                <?= $i ?>
            </a>
            <?php endfor; ?>

            <?php if ($pagination['has_next']): ?>
            <a href="<?= $pagination['base_url'] ?>&page=<?= $page + 1 ?>"
               class="px-4 py-2 rounded-xl border border-slate-200 text-sm hover:border-secondary hover:text-secondary transition-colors">
                Suivant →
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php
$appLayout = true;
require_once '/var/www/html/upc_freelance/includes/footer.php';
?>
