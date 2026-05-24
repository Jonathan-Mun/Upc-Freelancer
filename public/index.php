<?php
// ============================================================
// UPC FREELANCE — Page d'accueil publique
// /var/www/html/upc_freelance/public/index.php
// ============================================================

require_once '/var/www/html/upc_freelance/includes/middleware.php';
require_once '/var/www/html/upc_freelance/includes/auth.php';
require_once '/var/www/html/upc_freelance/includes/functions.php';
require_once '/var/www/html/upc_freelance/includes/db.php';

if (isLoggedIn()) {
    redirect('/var/www/html/upc_freelance/app/dashboard.php');
}

$pageTitle = 'UPC Freelance — La plateforme freelance étudiante';

// Derniers projets
$pdo      = getDB();
$projects = $pdo->query('SELECT p.*, u.first_name, u.last_name, c.name AS category_name
    FROM projects p
    JOIN users u ON u.id = p.client_id
    LEFT JOIN categories c ON c.id = p.category_id
    WHERE p.status = "open" AND p.visibility = "public"
    ORDER BY p.created_at DESC LIMIT 6')->fetchAll();

// Stats plateforme
$stats = [
    'freelancers' => (int) $pdo->query('SELECT COUNT(*) FROM users WHERE role = "freelancer" AND is_active = 1')->fetchColumn(),
    'projects'    => (int) $pdo->query('SELECT COUNT(*) FROM projects')->fetchColumn(),
    'contracts'   => (int) $pdo->query('SELECT COUNT(*) FROM contracts WHERE status = "completed"')->fetchColumn(),
];

$categories = $pdo->query('SELECT * FROM categories WHERE is_active = 1 ORDER BY name')->fetchAll();

require_once '/var/www/html/upc_freelance/includes/header.php';
?>

<!-- ── Hero ──────────────────────────────────────────────── -->
<section class="relative bg-surface-container-low overflow-hidden">
    <div class="absolute inset-0 bg-gradient-to-br from-primary/5 to-secondary/5 pointer-events-none"></div>
    <div class="max-w-7xl mx-auto px-8 py-24 flex flex-col lg:flex-row items-center gap-16">

        <!-- Texte -->
        <div class="lg:w-1/2 space-y-6 z-10">
            <span class="inline-block px-3 py-1 bg-surface-variant text-secondary rounded-full text-label-caps font-label-caps uppercase tracking-widest">
                🎓 Plateforme Étudiante
            </span>
            <h1 class="font-h1 text-h1 text-primary leading-tight max-w-lg">
                Transforme tes compétences en <span class="text-secondary">revenus réels</span>
            </h1>
            <p class="font-body-lg text-body-lg text-on-surface-variant max-w-md">
                Connecte-toi avec des clients qui ont besoin de ton talent. Postule, collabore, sois payé — en toute sécurité.
            </p>
            <div class="flex flex-wrap gap-4 pt-2">
                <a href="/upc_freelance/public/register.php?role=freelancer"
                   class="bg-secondary text-on-secondary font-button text-button px-8 py-4 rounded-xl shadow-sm hover:shadow-md hover:opacity-90 transition-all active:scale-95">
                    Je suis freelance
                </a>
                <a href="/upc_freelance/public/register.php?role=client"
                   class="bg-white text-secondary border-2 border-secondary font-button text-button px-8 py-4 rounded-xl hover:bg-secondary/5 transition-colors active:scale-95">
                    Je cherche du talent
                </a>
            </div>
            <!-- Stats -->
            <div class="flex gap-8 pt-6 border-t border-slate-200">
                <div>
                    <p class="text-2xl font-bold text-primary"><?= number_format($stats['freelancers']) ?>+</p>
                    <p class="text-sm text-on-surface-variant">Freelancers</p>
                </div>
                <div>
                    <p class="text-2xl font-bold text-primary"><?= number_format($stats['projects']) ?>+</p>
                    <p class="text-sm text-on-surface-variant">Projets publiés</p>
                </div>
                <div>
                    <p class="text-2xl font-bold text-primary"><?= number_format($stats['contracts']) ?>+</p>
                    <p class="text-sm text-on-surface-variant">Missions complétées</p>
                </div>
            </div>
        </div>

        <!-- Illustration card -->
        <div class="lg:w-1/2 relative">
            <div class="absolute inset-0 bg-secondary/8 rounded-3xl -rotate-2 transform scale-105"></div>
            <div class="relative bg-white rounded-3xl shadow-xl p-8 space-y-4">
                <div class="flex items-center justify-between mb-6">
                    <span class="font-bold text-primary">Dernières missions</span>
                    <span class="text-xs bg-green-100 text-green-700 px-2 py-1 rounded-full font-medium">● En ligne</span>
                </div>
                <?php foreach (array_slice($projects, 0, 3) as $p): ?>
                <div class="flex items-center gap-3 p-3 rounded-xl bg-surface-container-low">
                    <div class="w-10 h-10 rounded-lg bg-primary-container flex items-center justify-center flex-shrink-0">
                        <span class="material-symbols-outlined text-sm text-white">work</span>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold text-primary truncate"><?= h($p['title']) ?></p>
                        <p class="text-xs text-on-surface-variant"><?= h($p['category_name'] ?? 'Général') ?></p>
                    </div>
                    <span class="text-xs font-bold text-secondary whitespace-nowrap">
                        <?= money((float)($p['budget_max'] ?? $p['budget_min'] ?? 0)) ?>
                    </span>
                </div>
                <?php endforeach; ?>
                <?php if (empty($projects)): ?>
                <p class="text-center text-on-surface-variant text-sm py-4">Soyez le premier à publier un projet !</p>
                <?php endif; ?>
                <a href="/upc_freelance/app/projects/list.php" class="block text-center text-sm text-secondary font-medium hover:underline mt-2">
                    Voir tous les projets →
                </a>
            </div>
        </div>
    </div>
</section>

<!-- ── Comment ça marche ─────────────────────────────────── -->
<section class="py-24 bg-white">
    <div class="max-w-7xl mx-auto px-8">
        <div class="text-center mb-16 space-y-3">
            <h2 class="font-h2 text-h2 text-primary">Comment ça marche</h2>
            <p class="text-on-surface-variant max-w-lg mx-auto">Simple, sécurisé et efficace. En 3 étapes.</p>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-gutter">
            <?php
            $steps = [
                ['icon'=>'post_add',       'color'=>'bg-primary',   'num'=>'01', 'title'=>'Publie ton projet',      'desc'=>'Décris tes besoins, ton budget et ton délai. La plateforme diffuse ton annonce aux freelancers qualifiés.'],
                ['icon'=>'diversity_3',    'color'=>'bg-secondary', 'num'=>'02', 'title'=>'Reçois des candidatures', 'desc'=>'Compare les profils, les portfolios et les propositions. Sélectionne le freelancer qui te convient.'],
                ['icon'=>'verified',       'color'=>'bg-green-600', 'num'=>'03', 'title'=>'Collabore & paye',        'desc'=>'Travaillez ensemble via le chat intégré. Le paiement est sécurisé et libéré à la validation.'],
            ];
            foreach ($steps as $s):
            ?>
            <div class="p-8 rounded-2xl bg-surface-container-low border border-slate-100 hover:-translate-y-1 transition-transform custom-shadow-low">
                <div class="flex items-start justify-between mb-6">
                    <div class="w-12 h-12 <?= $s['color'] ?> text-white rounded-xl flex items-center justify-center">
                        <span class="material-symbols-outlined"><?= $s['icon'] ?></span>
                    </div>
                    <span class="text-4xl font-bold text-slate-100"><?= $s['num'] ?></span>
                </div>
                <h3 class="font-h3 text-h3 text-primary mb-3"><?= $s['title'] ?></h3>
                <p class="text-on-surface-variant font-body-md"><?= $s['desc'] ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ── Catégories ────────────────────────────────────────── -->
<section class="py-20 bg-surface-container-low">
    <div class="max-w-7xl mx-auto px-8">
        <h2 class="font-h2 text-h2 text-primary text-center mb-12">Explorez par catégorie</h2>
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
            <?php foreach ($categories as $cat): ?>
            <a href="/upc_freelance/app/projects/list.php?category=<?= $cat['id'] ?>"
               class="group p-6 bg-white rounded-xl border border-slate-100 hover:border-secondary hover:shadow-md transition-all text-center">
                <div class="w-12 h-12 bg-surface-container-low group-hover:bg-secondary/10 rounded-xl flex items-center justify-center mx-auto mb-3 transition-colors">
                    <span class="material-symbols-outlined text-secondary"><?= h($cat['icon'] ?? 'work') ?></span>
                </div>
                <p class="text-sm font-semibold text-primary"><?= h($cat['name']) ?></p>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ── Derniers projets ───────────────────────────────────── -->
<?php if (!empty($projects)): ?>
<section class="py-20 bg-white">
    <div class="max-w-7xl mx-auto px-8">
        <div class="flex justify-between items-center mb-10">
            <h2 class="font-h2 text-h2 text-primary">Projets récents</h2>
            <a href="/upc_freelance/app/projects/list.php" class="text-secondary font-medium text-sm hover:underline">Voir tout →</a>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ($projects as $p): ?>
            <a href="/upc_freelance/app/projects/details.php?id=<?= $p['id'] ?>"
               class="group block bg-surface-container-low rounded-xl p-6 border border-slate-100 hover:border-secondary hover:shadow-md transition-all">
                <div class="flex justify-between items-start mb-4">
                    <span class="inline-block text-xs bg-blue-50 text-secondary px-2 py-1 rounded-full font-medium">
                        <?= h($p['category_name'] ?? 'Général') ?>
                    </span>
                    <span class="text-xs text-slate-400"><?= timeAgo($p['created_at']) ?></span>
                </div>
                <h3 class="font-semibold text-primary group-hover:text-secondary transition-colors mb-2">
                    <?= h(truncate($p['title'], 60)) ?>
                </h3>
                <p class="text-sm text-on-surface-variant mb-4 line-clamp-2">
                    <?= h(truncate($p['description'], 100)) ?>
                </p>
                <div class="flex justify-between items-center">
                    <span class="text-xs text-slate-500">par <?= h($p['first_name']) ?></span>
                    <?php if ($p['budget_max'] || $p['budget_min']): ?>
                    <span class="text-sm font-bold text-secondary">
                        <?= money((float)($p['budget_max'] ?? $p['budget_min'])) ?>
                    </span>
                    <?php endif; ?>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ── CTA final ─────────────────────────────────────────── -->
<section class="py-24 bg-primary">
    <div class="max-w-3xl mx-auto px-8 text-center">
        <h2 class="font-h2 text-h2 text-white mb-4">Prêt à te lancer ?</h2>
        <p class="text-blue-200 font-body-lg mb-8">Rejoins des milliers d'étudiants qui monétisent leurs compétences dès aujourd'hui.</p>
        <a href="/upc_freelance/public/register.php"
           class="inline-block bg-white text-primary font-button text-button px-10 py-4 rounded-xl hover:bg-blue-50 transition-colors shadow-lg active:scale-95">
            Créer mon compte gratuitement
        </a>
    </div>
</section>

<?php require_once '/var/www/html/upc_freelance/includes/footer.php'; ?>
