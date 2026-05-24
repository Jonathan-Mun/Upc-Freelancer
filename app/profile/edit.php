<?php
// ============================================================
// UPC FREELANCE — Modifier mon profil
// /var/www/html/upc_freelance/app/profile/edit.php
// ============================================================

require_once '/var/www/html/upc_freelance/includes/middleware.php';
require_once '/var/www/html/upc_freelance/includes/auth.php';
require_once '/var/www/html/upc_freelance/includes/functions.php';
require_once '/var/www/html/upc_freelance/includes/db.php';

requireLogin();

$user = currentUser();
$pdo  = getDB();

// Charger profil étendu
if ($user['role'] === 'freelancer') {
    $stmt = $pdo->prepare('SELECT * FROM freelancer_profiles WHERE user_id = ?');
    $stmt->execute([$user['id']]);
    $profile = $stmt->fetch();
} else {
    $stmt = $pdo->prepare('SELECT * FROM client_profiles WHERE user_id = ?');
    $stmt->execute([$user['id']]);
    $profile = $stmt->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $firstName   = sanitize($_POST['first_name']   ?? '');
    $lastName    = sanitize($_POST['last_name']    ?? '');
    $phone       = sanitize($_POST['phone']        ?? '');
    $bio         = sanitize($_POST['bio']          ?? '');
    $university  = sanitize($_POST['university']   ?? '');
    $fieldStudy  = sanitize($_POST['field_of_study'] ?? '');

    // Avatar upload
    $avatarPath = $user['avatar'];
    if (!empty($_FILES['avatar']['name'])) {
        $uploaded = uploadFile($_FILES['avatar'], 'avatars', ['jpg','jpeg','png','webp'], 2);
        if ($uploaded) {
            // Supprimer l'ancien avatar
            if ($user['avatar'] && file_exists('/var/www/html/upc_freelance/storage/' . $user['avatar'])) {
                unlink('/var/www/html/upc_freelance/storage/' . $user['avatar']);
            }
            $avatarPath = $uploaded;
        } else {
            flash('error', 'Fichier avatar invalide (max 2 Mo, formats : jpg, png, webp).');
        }
    }

    $pdo->prepare('
        UPDATE users SET first_name = ?, last_name = ?, phone = ?, bio = ?,
                         university = ?, field_of_study = ?, avatar = ?, updated_at = NOW()
        WHERE id = ?
    ')->execute([$firstName, $lastName, $phone, $bio, $university, $fieldStudy, $avatarPath, $user['id']]);

    // Profil spécifique
    if ($user['role'] === 'freelancer') {
        $title       = sanitize($_POST['title']         ?? '');
        $hourlyRate  = (float)($_POST['hourly_rate']    ?? 0);
        $availability= in_array($_POST['availability'] ?? '', ['available','busy','unavailable']) ? $_POST['availability'] : 'available';
        $skills      = array_filter(array_map('trim', explode(',', $_POST['skills'] ?? '')));
        $portfolio   = sanitize($_POST['portfolio_url'] ?? '');
        $linkedin    = sanitize($_POST['linkedin_url']  ?? '');
        $github      = sanitize($_POST['github_url']    ?? '');

        $pdo->prepare('
            UPDATE freelancer_profiles SET title = ?, hourly_rate = ?, availability = ?,
                   skills = ?, portfolio_url = ?, linkedin_url = ?, github_url = ?, updated_at = NOW()
            WHERE user_id = ?
        ')->execute([$title, $hourlyRate ?: null, $availability, $skills ? json_encode($skills) : null,
                     $portfolio, $linkedin, $github, $user['id']]);
    } else {
        $companyName = sanitize($_POST['company_name'] ?? '');
        $website     = sanitize($_POST['website']      ?? '');
        $pdo->prepare('UPDATE client_profiles SET company_name = ?, website = ?, updated_at = NOW() WHERE user_id = ?')
            ->execute([$companyName, $website, $user['id']]);
    }

    flash('success', 'Profil mis à jour avec succès !');
    redirect('/var/www/html/upc_freelance/app/profile/edit.php');
}

$skills = ($profile['skills'] ?? null) ? json_decode($profile['skills'], true) : [];

$pageTitle = 'Mon profil — UPC Freelance';
$appLayout = true;
require_once '/var/www/html/upc_freelance/includes/header.php';
?>

<?php renderFlash(); ?>

<div class="mb-8">
    <h1 class="text-2xl font-bold text-primary">Mon profil</h1>
    <p class="text-on-surface-variant text-sm mt-1">Complétez votre profil pour attirer plus de clients ou freelancers.</p>
</div>

<form method="POST" enctype="multipart/form-data" class="max-w-3xl space-y-6" novalidate>
    <?= csrfField() ?>

    <!-- ── Avatar & infos de base ────────────────────────── -->
    <div class="bg-white rounded-2xl border border-slate-100 p-6 custom-shadow-low">
        <h2 class="font-semibold text-primary mb-5 flex items-center gap-2">
            <span class="material-symbols-outlined text-secondary">person</span>
            Informations personnelles
        </h2>

        <!-- Avatar -->
        <div class="flex items-center gap-5 mb-6">
            <div class="relative">
                <?php if ($user['avatar']): ?>
                <img src="/upc_freelance/storage/<?= h($user['avatar']) ?>" alt="Avatar"
                     class="w-20 h-20 rounded-full object-cover border-2 border-slate-200" id="avatar-preview"/>
                <?php else: ?>
                <div class="w-20 h-20 rounded-full bg-primary/10 flex items-center justify-center text-3xl font-bold text-primary border-2 border-slate-200" id="avatar-preview-default">
                    <?= mb_substr($user['first_name'], 0, 1) ?>
                </div>
                <?php endif; ?>
            </div>
            <div>
                <label class="cursor-pointer inline-flex items-center gap-2 bg-surface-container text-secondary text-sm font-medium px-4 py-2 rounded-xl hover:bg-surface-container-high transition-colors">
                    <span class="material-symbols-outlined text-base">upload</span>
                    Changer la photo
                    <input type="file" name="avatar" accept="image/*" class="hidden" onchange="previewAvatar(this)"/>
                </label>
                <p class="text-xs text-slate-400 mt-1.5">JPG, PNG, WebP · Max 2 Mo</p>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-primary mb-1.5">Prénom <span class="text-red-500">*</span></label>
                <input type="text" name="first_name" required value="<?= h($user['first_name']) ?>"
                       class="w-full px-4 py-3 rounded-xl border border-outline-variant focus:border-secondary focus:ring-2 focus:ring-secondary/20 outline-none text-sm transition-all"/>
            </div>
            <div>
                <label class="block text-sm font-medium text-primary mb-1.5">Nom <span class="text-red-500">*</span></label>
                <input type="text" name="last_name" required value="<?= h($user['last_name']) ?>"
                       class="w-full px-4 py-3 rounded-xl border border-outline-variant focus:border-secondary focus:ring-2 focus:ring-secondary/20 outline-none text-sm transition-all"/>
            </div>
            <div>
                <label class="block text-sm font-medium text-primary mb-1.5">Téléphone</label>
                <div class="relative">
                    <span class="absolute left-3 top-1/2 -translate-y-1/2 material-symbols-outlined text-slate-400 text-lg">phone</span>
                    <input type="tel" name="phone" value="<?= h($user['phone'] ?? '') ?>"
                           placeholder="+225 07 XX XX XX XX"
                           class="w-full pl-10 pr-4 py-3 rounded-xl border border-outline-variant focus:border-secondary focus:ring-2 focus:ring-secondary/20 outline-none text-sm transition-all"/>
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-primary mb-1.5">Université / École</label>
                <div class="relative">
                    <span class="absolute left-3 top-1/2 -translate-y-1/2 material-symbols-outlined text-slate-400 text-lg">school</span>
                    <input type="text" name="university" value="<?= h($user['university'] ?? '') ?>"
                           placeholder="Ex: Université Félix Houphouët-Boigny"
                           class="w-full pl-10 pr-4 py-3 rounded-xl border border-outline-variant focus:border-secondary focus:ring-2 focus:ring-secondary/20 outline-none text-sm transition-all"/>
                </div>
            </div>
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-primary mb-1.5">Filière / Domaine d'étude</label>
                <input type="text" name="field_of_study" value="<?= h($user['field_of_study'] ?? '') ?>"
                       placeholder="Ex: Informatique, Marketing, Design..."
                       class="w-full px-4 py-3 rounded-xl border border-outline-variant focus:border-secondary focus:ring-2 focus:ring-secondary/20 outline-none text-sm transition-all"/>
            </div>
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-primary mb-1.5">Biographie</label>
                <textarea name="bio" rows="4"
                          placeholder="Parlez de vous, de vos compétences, de vos objectifs..."
                          class="w-full px-4 py-3 rounded-xl border border-outline-variant focus:border-secondary focus:ring-2 focus:ring-secondary/20 outline-none text-sm transition-all resize-y"><?= h($user['bio'] ?? '') ?></textarea>
            </div>
        </div>
    </div>

    <!-- ── Profil Freelancer ─────────────────────────────── -->
    <?php if ($user['role'] === 'freelancer'): ?>
    <div class="bg-white rounded-2xl border border-slate-100 p-6 custom-shadow-low">
        <h2 class="font-semibold text-primary mb-5 flex items-center gap-2">
            <span class="material-symbols-outlined text-secondary">work</span>
            Profil Freelancer
        </h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-primary mb-1.5">Titre professionnel</label>
                <input type="text" name="title" value="<?= h($profile['title'] ?? '') ?>"
                       placeholder="Ex: Développeur Full-Stack React/PHP"
                       class="w-full px-4 py-3 rounded-xl border border-outline-variant focus:border-secondary focus:ring-2 focus:ring-secondary/20 outline-none text-sm transition-all"/>
            </div>
            <div>
                <label class="block text-sm font-medium text-primary mb-1.5">Tarif horaire (XOF)</label>
                <div class="relative">
                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-xs text-slate-400 font-medium">CFA</span>
                    <input type="number" name="hourly_rate" min="0" step="100"
                           value="<?= h($profile['hourly_rate'] ?? '') ?>"
                           placeholder="2000"
                           class="w-full pl-12 pr-4 py-3 rounded-xl border border-outline-variant focus:border-secondary focus:ring-2 focus:ring-secondary/20 outline-none text-sm transition-all"/>
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-primary mb-1.5">Disponibilité</label>
                <select name="availability" class="w-full px-4 py-3 rounded-xl border border-outline-variant focus:border-secondary outline-none text-sm">
                    <option value="available"   <?= ($profile['availability'] ?? '') === 'available'   ? 'selected' : '' ?>>✅ Disponible</option>
                    <option value="busy"        <?= ($profile['availability'] ?? '') === 'busy'        ? 'selected' : '' ?>>🟡 Occupé (disponible partiellement)</option>
                    <option value="unavailable" <?= ($profile['availability'] ?? '') === 'unavailable' ? 'selected' : '' ?>>🔴 Indisponible</option>
                </select>
            </div>
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-primary mb-1.5">Compétences</label>
                <input type="text" name="skills" id="skills-input"
                       value="<?= h(implode(', ', $skills)) ?>"
                       placeholder="PHP, JavaScript, React, Figma, Python..."
                       class="w-full px-4 py-3 rounded-xl border border-outline-variant focus:border-secondary focus:ring-2 focus:ring-secondary/20 outline-none text-sm transition-all"/>
                <div id="skills-tags" class="flex flex-wrap gap-2 mt-2"></div>
            </div>
            <div>
                <label class="block text-sm font-medium text-primary mb-1.5">Portfolio URL</label>
                <div class="relative">
                    <span class="absolute left-3 top-1/2 -translate-y-1/2 material-symbols-outlined text-slate-400 text-lg">link</span>
                    <input type="url" name="portfolio_url" value="<?= h($profile['portfolio_url'] ?? '') ?>"
                           placeholder="https://monportfolio.com"
                           class="w-full pl-10 pr-4 py-3 rounded-xl border border-outline-variant focus:border-secondary focus:ring-2 focus:ring-secondary/20 outline-none text-sm transition-all"/>
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-primary mb-1.5">LinkedIn</label>
                <div class="relative">
                    <span class="absolute left-3 top-1/2 -translate-y-1/2 material-symbols-outlined text-slate-400 text-lg">link</span>
                    <input type="url" name="linkedin_url" value="<?= h($profile['linkedin_url'] ?? '') ?>"
                           placeholder="https://linkedin.com/in/..."
                           class="w-full pl-10 pr-4 py-3 rounded-xl border border-outline-variant focus:border-secondary focus:ring-2 focus:ring-secondary/20 outline-none text-sm transition-all"/>
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-primary mb-1.5">GitHub</label>
                <div class="relative">
                    <span class="absolute left-3 top-1/2 -translate-y-1/2 material-symbols-outlined text-slate-400 text-lg">code</span>
                    <input type="url" name="github_url" value="<?= h($profile['github_url'] ?? '') ?>"
                           placeholder="https://github.com/..."
                           class="w-full pl-10 pr-4 py-3 rounded-xl border border-outline-variant focus:border-secondary focus:ring-2 focus:ring-secondary/20 outline-none text-sm transition-all"/>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Profil Client ─────────────────────────────────── -->
    <?php else: ?>
    <div class="bg-white rounded-2xl border border-slate-100 p-6 custom-shadow-low">
        <h2 class="font-semibold text-primary mb-5 flex items-center gap-2">
            <span class="material-symbols-outlined text-secondary">business</span>
            Profil Client
        </h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-primary mb-1.5">Nom de l'entreprise</label>
                <input type="text" name="company_name" value="<?= h($profile['company_name'] ?? '') ?>"
                       placeholder="Nom de votre entreprise ou projet"
                       class="w-full px-4 py-3 rounded-xl border border-outline-variant focus:border-secondary focus:ring-2 focus:ring-secondary/20 outline-none text-sm transition-all"/>
            </div>
            <div>
                <label class="block text-sm font-medium text-primary mb-1.5">Site web</label>
                <div class="relative">
                    <span class="absolute left-3 top-1/2 -translate-y-1/2 material-symbols-outlined text-slate-400 text-lg">language</span>
                    <input type="url" name="website" value="<?= h($profile['website'] ?? '') ?>"
                           placeholder="https://monsite.com"
                           class="w-full pl-10 pr-4 py-3 rounded-xl border border-outline-variant focus:border-secondary focus:ring-2 focus:ring-secondary/20 outline-none text-sm transition-all"/>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Boutons -->
    <div class="flex gap-3">
        <button type="submit"
                class="flex-1 bg-primary text-white font-button text-button py-3.5 rounded-xl hover:opacity-90 transition-opacity active:scale-95 shadow-sm">
            <span class="material-symbols-outlined align-middle mr-1">save</span>
            Sauvegarder les modifications
        </button>
        <a href="/upc_freelance/app/profile/view.php?id=<?= $user['id'] ?>"
           class="px-6 py-3.5 rounded-xl border-2 border-slate-200 text-sm font-medium text-on-surface-variant hover:border-slate-300 transition-colors">
            Voir mon profil
        </a>
    </div>
</form>

<script>
// Preview avatar
function previewAvatar(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => {
            let preview = document.getElementById('avatar-preview');
            if (!preview) {
                const def = document.getElementById('avatar-preview-default');
                if (def) {
                    const img = document.createElement('img');
                    img.id        = 'avatar-preview';
                    img.className = 'w-20 h-20 rounded-full object-cover border-2 border-slate-200';
                    def.replaceWith(img);
                    preview = img;
                }
            }
            if (preview) preview.src = e.target.result;
        };
        reader.readAsDataURL(input.files[0]);
    }
}

// Skills tags
const skillInput = document.getElementById('skills-input');
const skillTags  = document.getElementById('skills-tags');
if (skillInput && skillTags) {
    function renderTags() {
        const vals = skillInput.value.split(',').map(s => s.trim()).filter(Boolean);
        skillTags.innerHTML = vals.map(v =>
            `<span class="inline-flex items-center gap-1 bg-surface-container text-secondary text-xs px-2.5 py-1 rounded-full font-medium">${v}</span>`
        ).join('');
    }
    skillInput.addEventListener('input', renderTags);
    renderTags();
}
</script>

<?php
$appLayout = true;
require_once '/var/www/html/upc_freelance/includes/footer.php';
?>
