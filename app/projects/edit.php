<?php
// ============================================================
// UPC FREELANCE — Modifier un projet
// ../../app/projects/edit.php
// ============================================================

require_once '../../includes/middleware.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../includes/db.php';

requireRole('client');

$user      = currentUser();
$pdo       = getDB();
$projectId = (int)($_GET['id'] ?? 0);
if (!$projectId) redirect('../../app/projects/my-projects.php');

$stmt = $pdo->prepare('SELECT * FROM projects WHERE id = ? AND client_id = ?');
$stmt->execute([$projectId, $user['id']]);
$project = $stmt->fetch();
if (!$project) { http_response_code(403); die('Accès refusé.'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $title      = sanitize($_POST['title']       ?? '');
    $desc       = sanitize($_POST['description'] ?? '');
    $categoryId = (int)($_POST['category_id']    ?? 0);
    $budgetMin  = (float)($_POST['budget_min']   ?? 0);
    $budgetMax  = (float)($_POST['budget_max']   ?? 0);
    $deadline   = sanitize($_POST['deadline']    ?? '');
    $skills     = array_filter(array_map('trim', explode(',', $_POST['skills'] ?? '')));

    if (empty($title) || empty($desc)) {
        flash('error', 'Titre et description sont requis.');
    } else {
        $pdo->prepare('
            UPDATE projects SET title=?, description=?, category_id=?, budget_min=?,
                   budget_max=?, deadline=?, skills_needed=?, updated_at=NOW()
            WHERE id=? AND client_id=?
        ')->execute([$title, $desc, $categoryId ?: null, $budgetMin ?: null,
                     $budgetMax ?: null, $deadline ?: null,
                     $skills ? json_encode($skills) : null,
                     $projectId, $user['id']]);
        flash('success', 'Projet mis à jour avec succès !');
        redirect('../../app/projects/details.php?id=' . $projectId);
    }
}

$categories = $pdo->query('SELECT * FROM categories WHERE is_active=1 ORDER BY name')->fetchAll();
$skills = $project['skills_needed'] ? json_decode($project['skills_needed'], true) : [];

$pageTitle = 'Modifier le projet — UPC Freelance';
$appLayout = true;
require_once '../../includes/header.php';
?>

<?php renderFlash(); ?>

<div class="mb-8">
    <a href="/upc_freelance/app/projects/details.php?id=<?= $projectId ?>" class="inline-flex items-center gap-1 text-sm text-secondary hover:underline mb-3">
        <span class="material-symbols-outlined text-base">arrow_back</span> Retour au projet
    </a>
    <h1 class="text-2xl font-bold text-primary">Modifier le projet</h1>
</div>

<div class="max-w-3xl">
    <form method="POST" class="space-y-6">
        <?= csrfField() ?>
        <div class="bg-white rounded-2xl border border-slate-100 p-6 custom-shadow-low space-y-4">
            <h2 class="font-semibold text-primary">Informations générales</h2>
            <div>
                <label class="block text-sm font-medium text-primary mb-1.5">Titre <span class="text-red-500">*</span></label>
                <input type="text" name="title" required value="<?= h($project['title']) ?>"
                       class="w-full px-4 py-3 rounded-xl border border-outline-variant focus:border-secondary focus:ring-2 focus:ring-secondary/20 outline-none text-sm"/>
            </div>
            <div>
                <label class="block text-sm font-medium text-primary mb-1.5">Description <span class="text-red-500">*</span></label>
                <textarea name="description" rows="6" required
                          class="w-full px-4 py-3 rounded-xl border border-outline-variant focus:border-secondary focus:ring-2 focus:ring-secondary/20 outline-none text-sm resize-y"><?= h($project['description']) ?></textarea>
            </div>
            <div>
                <label class="block text-sm font-medium text-primary mb-1.5">Catégorie</label>
                <select name="category_id" class="w-full px-4 py-3 rounded-xl border border-outline-variant focus:border-secondary outline-none text-sm">
                    <option value="">-- Catégorie --</option>
                    <?php foreach($categories as $cat):?>
                    <option value="<?=$cat['id']?>" <?=$project['category_id']==$cat['id']?'selected':''?>><?=h($cat['name'])?></option>
                    <?php endforeach;?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-primary mb-1.5">Compétences</label>
                <input type="text" name="skills" value="<?= h(implode(', ', $skills)) ?>"
                       placeholder="PHP, React, Figma..."
                       class="w-full px-4 py-3 rounded-xl border border-outline-variant focus:border-secondary focus:ring-2 focus:ring-secondary/20 outline-none text-sm"/>
            </div>
        </div>

        <div class="bg-white rounded-2xl border border-slate-100 p-6 custom-shadow-low">
            <h2 class="font-semibold text-primary mb-4">Budget & Délai</h2>
            <div class="grid grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-primary mb-1.5">Budget min (XOF)</label>
                    <input type="number" name="budget_min" min="0" value="<?= h($project['budget_min'] ?? '') ?>"
                           class="w-full px-4 py-3 rounded-xl border border-outline-variant focus:border-secondary outline-none text-sm"/>
                </div>
                <div>
                    <label class="block text-sm font-medium text-primary mb-1.5">Budget max (XOF)</label>
                    <input type="number" name="budget_max" min="0" value="<?= h($project['budget_max'] ?? '') ?>"
                           class="w-full px-4 py-3 rounded-xl border border-outline-variant focus:border-secondary outline-none text-sm"/>
                </div>
                <div>
                    <label class="block text-sm font-medium text-primary mb-1.5">Date limite</label>
                    <input type="date" name="deadline" value="<?= h($project['deadline'] ?? '') ?>"
                           class="w-full px-4 py-3 rounded-xl border border-outline-variant focus:border-secondary outline-none text-sm"/>
                </div>
            </div>
        </div>

        <div class="flex gap-3">
            <button type="submit" class="flex-1 bg-primary text-white font-button text-button py-3.5 rounded-xl hover:opacity-90 transition-opacity active:scale-95 shadow-sm">
                <span class="material-symbols-outlined align-middle mr-1">save</span> Sauvegarder
            </button>
            <a href="/upc_freelance/app/projects/details.php?id=<?= $projectId ?>"
               class="px-6 py-3.5 rounded-xl border-2 border-slate-200 text-sm font-medium text-on-surface-variant hover:border-slate-300 transition-colors">
                Annuler
            </a>
        </div>
    </form>
</div>

<?php $appLayout = true; require_once '../../includes/footer.php'; ?>
