<?php
// ============================================================
// UPC FREELANCE — Modifier mon profil
// /var/www/html/upc_freelance/app/profile/edit.php
// ============================================================

require_once '../../includes/middleware.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../includes/db.php';

requireLogin();

$user = currentUser();
$pdo  = getDB();

if ($user['role'] === 'freelancer') {
    $stmt = $pdo->prepare('SELECT * FROM freelancer_profiles WHERE user_id = ?');
    $stmt->execute([$user['id']]);
    $profile = $stmt->fetch() ?: [];
} else {
    $stmt = $pdo->prepare('SELECT * FROM client_profiles WHERE user_id = ?');
    $stmt->execute([$user['id']]);
    $profile = $stmt->fetch() ?: [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $firstName = sanitize($_POST['first_name'] ?? '');
    $lastName  = sanitize($_POST['last_name']  ?? '');
    $phone     = sanitize($_POST['phone']      ?? '');

    $avatarPath = $user['avatar'];
    if (!empty($_FILES['avatar']['name'])) {
        $uploaded = uploadFile($_FILES['avatar'], 'avatars', ['jpg','jpeg','png','webp'], 2);
        if ($uploaded) {
            if ($user['avatar'] && file_exists('../../storage/' . $user['avatar'])) {
                unlink('../../storage/' . $user['avatar']);
            }
            $avatarPath = $uploaded;
        } else {
            flash('error', 'Fichier avatar invalide (max 2 Mo, formats : jpg, png, webp).');
        }
    }

    $pdo->prepare('
        UPDATE users
        SET first_name = ?, last_name = ?, phone = ?, avatar = ?, updated_at = NOW()
        WHERE id = ?
    ')->execute([$firstName, $lastName, $phone, $avatarPath, $user['id']]);

    if ($user['role'] === 'freelancer') {
        $title        = sanitize($_POST['title']          ?? '');
        $hourlyRate   = (float)($_POST['hourly_rate']     ?? 0);
        $availability = in_array($_POST['availability'] ?? '', ['available','busy','unavailable'])
                        ? $_POST['availability'] : 'available';
        $skillsRaw    = array_values(array_filter(array_map('trim', explode(',', $_POST['skills'] ?? ''))));
        $bio          = sanitize($_POST['bio']            ?? '');
        $university   = sanitize($_POST['university']     ?? '');
        $fieldStudy   = sanitize($_POST['field_of_study'] ?? '');
        $portfolio    = sanitize($_POST['portfolio_url']  ?? '');
        $linkedin     = sanitize($_POST['linkedin_url']   ?? '');
        $github       = sanitize($_POST['github_url']     ?? '');

        $pdo->prepare('
            INSERT INTO freelancer_profiles
                (user_id, title, hourly_rate, availability, skills,
                 portfolio_url, linkedin_url, github_url,
                 bio, university, field_of_study, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
                title          = VALUES(title),
                hourly_rate    = VALUES(hourly_rate),
                availability   = VALUES(availability),
                skills         = VALUES(skills),
                portfolio_url  = VALUES(portfolio_url),
                linkedin_url   = VALUES(linkedin_url),
                github_url     = VALUES(github_url),
                bio            = VALUES(bio),
                university     = VALUES(university),
                field_of_study = VALUES(field_of_study),
                updated_at     = NOW()
        ')->execute([
            $user['id'], $title, $hourlyRate ?: null, $availability,
            $skillsRaw ? json_encode($skillsRaw) : null,
            $portfolio, $linkedin, $github, $bio, $university, $fieldStudy,
        ]);
    } else {
        $bio         = sanitize($_POST['bio']          ?? '');
        $companyName = sanitize($_POST['company_name'] ?? '');
        $website     = sanitize($_POST['website']      ?? '');

        $pdo->prepare('
            INSERT INTO client_profiles (user_id, bio, company_name, website, updated_at)
            VALUES (?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
                bio          = VALUES(bio),
                company_name = VALUES(company_name),
                website      = VALUES(website),
                updated_at   = NOW()
        ')->execute([$user['id'], $bio, $companyName, $website]);
    }

    flash('success', 'Profil mis à jour avec succès !');
    redirect('edit.php');
}

$skills = ($profile['skills'] ?? null) ? json_decode($profile['skills'], true) : [];

// Calcul complétion
if ($user['role'] === 'freelancer') {
    $profileFields = [
        $user['avatar'],
        $profile['title']       ?? null,
        $profile['bio']         ?? null,
        $profile['university']  ?? null,
        $profile['skills']      ?? null,
        $profile['hourly_rate'] ?? null,
        ($profile['portfolio_url'] ?? null)
            ?: ($profile['linkedin_url'] ?? null)
            ?: ($profile['github_url']   ?? null),
    ];
} else {
    $profileFields = [
        $user['avatar'],
        $profile['bio']          ?? null,
        $profile['company_name'] ?? null,
        $profile['website']      ?? null,
    ];
}

$completion = (int)round(
    count(array_filter($profileFields)) / count($profileFields) * 100
);

$pageTitle = 'Mon profil — UPC Freelance';
$appLayout = true;
require_once '../../includes/header.php';
?>

<style>
/* ── Page wrapper centré ─────────────────────────────────── */
.edit-page-wrap {
    max-width: 780px;
    margin: 0 auto;
    padding-bottom: 5rem;
}

/* ── Cards ───────────────────────────────────────────────── */
.edit-card {
    background: #fff;
    border-radius: 18px;
    border: 1px solid #e8edf5;
    box-shadow: 0 2px 12px rgba(0, 32, 69, 0.06);
    padding: 2rem;
    margin-bottom: 1.5rem;
}

/* ── Section header ──────────────────────────────────────── */
.section-header {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid #f1f5f9;
}
.section-icon {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    background: #eff6ff;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #2563eb;
    flex-shrink: 0;
}
.section-title {
    font-size: 15px;
    font-weight: 700;
    color: #002045;
    letter-spacing: -0.01em;
}

/* ── Labels ──────────────────────────────────────────────── */
.field-label {
    display: block;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.07em;
    text-transform: uppercase;
    color: #94a3b8;
    margin-bottom: 6px;
}

/* ── Inputs ──────────────────────────────────────────────── */
.edit-input {
    width: 100%;
    padding: 11px 14px;
    border-radius: 12px;
    border: 1.5px solid #e2e8f0;
    font-size: 14px;
    color: #002045;
    background: #f8fafc;
    transition: border-color 0.18s, box-shadow 0.18s, background 0.18s;
    outline: none;
}
.edit-input:focus {
    border-color: #3b82f6;
    background: #fff;
    box-shadow: 0 0 0 3px rgba(59,130,246,0.12);
}
.edit-input::placeholder { color: #b0bec5; }

.edit-input-icon-wrap {
    position: relative;
}
.edit-input-icon-wrap .icon {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: #94a3b8;
    font-size: 18px;
    pointer-events: none;
}
.edit-input-icon-wrap .edit-input {
    padding-left: 38px;
}
.edit-input-icon-wrap .prefix-text {
    position: absolute;
    left: 14px;
    top: 50%;
    transform: translateY(-50%);
    font-size: 11px;
    font-weight: 700;
    color: #94a3b8;
}
.edit-input-icon-wrap .edit-input.has-prefix {
    padding-left: 42px;
}

textarea.edit-input {
    resize: vertical;
    min-height: 100px;
    line-height: 1.6;
}

select.edit-input {
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%2394a3b8' stroke-width='2'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 12px center;
    padding-right: 36px;
}

/* ── Avatar upload ───────────────────────────────────────── */
.avatar-upload-wrap {
    display: flex;
    align-items: center;
    gap: 20px;
    padding: 1.25rem;
    background: #f8fafc;
    border-radius: 14px;
    border: 1.5px dashed #cbd5e1;
    margin-bottom: 1.5rem;
    transition: border-color 0.2s;
}
.avatar-upload-wrap:hover { border-color: #93c5fd; }
.avatar-img {
    width: 72px;
    height: 72px;
    border-radius: 14px;
    object-fit: cover;
    border: 3px solid #bfdbfe;
    flex-shrink: 0;
}
.avatar-initials {
    width: 72px;
    height: 72px;
    border-radius: 14px;
    background: #dbeafe;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.8rem;
    font-weight: 700;
    color: #1d4ed8;
    border: 3px solid #bfdbfe;
    flex-shrink: 0;
}
.upload-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: #fff;
    color: #2563eb;
    border: 1.5px solid #bfdbfe;
    padding: 8px 16px;
    border-radius: 10px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.15s, border-color 0.15s;
}
.upload-btn:hover { background: #eff6ff; border-color: #93c5fd; }

/* ── Progress bar ────────────────────────────────────────── */
.progress-wrap {
    background: #f1f5f9;
    border-radius: 14px;
    padding: 1rem 1.25rem;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 16px;
}
.progress-track {
    flex: 1;
    background: #e2e8f0;
    height: 6px;
    border-radius: 999px;
    overflow: hidden;
}
.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #3b82f6, #60a5fa);
    border-radius: 999px;
    transition: width 0.6s ease;
}
.progress-label {
    font-size: 12px;
    font-weight: 700;
    color: #2563eb;
    white-space: nowrap;
}

/* ── Skill tags preview ──────────────────────────────────── */
.skill-preview-tag {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    background: #eff6ff;
    color: #1d4ed8;
    border: 1px solid #bfdbfe;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.05em;
    text-transform: uppercase;
    padding: 4px 10px;
    border-radius: 6px;
}

/* ── Buttons ─────────────────────────────────────────────── */
.btn-primary {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    background: #002045;
    color: #fff;
    font-size: 14px;
    font-weight: 700;
    letter-spacing: 0.01em;
    padding: 14px 24px;
    border-radius: 13px;
    border: none;
    cursor: pointer;
    transition: opacity 0.15s, transform 0.1s;
    box-shadow: 0 4px 14px rgba(0,32,69,0.25);
}
.btn-primary:hover { opacity: 0.9; }
.btn-primary:active { transform: scale(0.97); }

.btn-ghost {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    background: transparent;
    color: #64748b;
    font-size: 14px;
    font-weight: 600;
    padding: 14px 20px;
    border-radius: 13px;
    border: 2px solid #e2e8f0;
    cursor: pointer;
    transition: border-color 0.15s, color 0.15s;
    text-decoration: none;
}
.btn-ghost:hover { border-color: #94a3b8; color: #002045; }

/* ── Grid ────────────────────────────────────────────────── */
.grid-2 {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}
.grid-2 .col-span-2 { grid-column: 1 / -1; }
@media (max-width: 600px) {
    .grid-2 { grid-template-columns: 1fr; }
    .grid-2 .col-span-2 { grid-column: 1; }
}
.field { display: flex; flex-direction: column; }
</style>

<?php renderFlash(); ?>

<div class="edit-page-wrap">

    <!-- En-tête page -->
    <div class="mb-8 text-center">
        <h1 class="text-3xl font-bold text-primary tracking-tight">Mon profil</h1>
        <p class="text-on-surface-variant text-sm mt-2">Complétez votre profil pour attirer plus de clients.</p>
    </div>

    <!-- Barre de progression -->
    <div class="progress-wrap">
        <span class="material-symbols-outlined text-blue-400 text-lg" style="font-variation-settings:'FILL' 1">analytics</span>
        <div style="flex:1">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
                <span style="font-size:13px;font-weight:600;color:#002045;">Complétion du profil</span>
                <span class="progress-label"><?= $completion ?>%</span>
            </div>
            <div class="progress-track">
                <div class="progress-fill" style="width:<?= $completion ?>%"></div>
            </div>
        </div>
    </div>

    <form method="POST" enctype="multipart/form-data" novalidate>
        <?= csrfField() ?>

        <!-- ══ INFORMATIONS PERSONNELLES ═══════════════════ -->
        <div class="edit-card">
            <div class="section-header">
                <div class="section-icon">
                    <span class="material-symbols-outlined text-xl">person</span>
                </div>
                <span class="section-title">Informations personnelles</span>
            </div>

            <!-- Avatar upload -->
            <div class="avatar-upload-wrap">
                <?php if ($user['avatar']): ?>
                <img src="/upc_freelance/storage/<?= h($user['avatar']) ?>" alt="Avatar"
                     class="avatar-img" id="avatar-preview"/>
                <?php else: ?>
                <div class="avatar-initials" id="avatar-preview-default">
                    <?= mb_strtoupper(mb_substr($user['first_name'], 0, 1)) ?>
                </div>
                <?php endif; ?>
                <div>
                    <label class="upload-btn">
                        <span class="material-symbols-outlined text-base">upload</span>
                        Changer la photo
                        <input type="file" name="avatar" accept="image/*" class="hidden" onchange="previewAvatar(this)"/>
                    </label>
                    <p style="font-size:11px;color:#94a3b8;margin-top:6px;">JPG, PNG, WebP · Max 2 Mo</p>
                </div>
            </div>

            <div class="grid-2">
                <div class="field">
                    <label class="field-label">Prénom <span style="color:#ef4444">*</span></label>
                    <input type="text" name="first_name" required value="<?= h($user['first_name']) ?>"
                           class="edit-input"/>
                </div>
                <div class="field">
                    <label class="field-label">Nom <span style="color:#ef4444">*</span></label>
                    <input type="text" name="last_name" required value="<?= h($user['last_name']) ?>"
                           class="edit-input"/>
                </div>
                <div class="field">
                    <label class="field-label">Téléphone</label>
                    <div class="edit-input-icon-wrap">
                        <span class="material-symbols-outlined icon">phone</span>
                        <input type="tel" name="phone" value="<?= h($user['phone'] ?? '') ?>"
                               placeholder="+225 07 XX XX XX XX" class="edit-input"/>
                    </div>
                </div>

                <?php if ($user['role'] === 'freelancer'): ?>
                <div class="field">
                    <label class="field-label">Université / École</label>
                    <div class="edit-input-icon-wrap">
                        <span class="material-symbols-outlined icon">school</span>
                        <input type="text" name="university" value="<?= h($profile['university'] ?? '') ?>"
                               placeholder="Ex: Université FHB" class="edit-input"/>
                    </div>
                </div>
                <div class="field col-span-2">
                    <label class="field-label">Filière / Domaine d'étude</label>
                    <input type="text" name="field_of_study" value="<?= h($profile['field_of_study'] ?? '') ?>"
                           placeholder="Ex: Informatique, Marketing, Design..." class="edit-input"/>
                </div>
                <?php endif; ?>

                <div class="field col-span-2">
                    <label class="field-label">Biographie</label>
                    <textarea name="bio" rows="4" class="edit-input"
                              placeholder="Parlez de vous, de vos compétences, de vos objectifs..."><?= h($profile['bio'] ?? '') ?></textarea>
                </div>
            </div>
        </div>

        <!-- ══ PROFIL FREELANCER ════════════════════════════ -->
        <?php if ($user['role'] === 'freelancer'): ?>
        <div class="edit-card">
            <div class="section-header">
                <div class="section-icon">
                    <span class="material-symbols-outlined text-xl">work</span>
                </div>
                <span class="section-title">Profil Freelancer</span>
            </div>

            <div class="grid-2">
                <div class="field col-span-2">
                    <label class="field-label">Titre professionnel</label>
                    <input type="text" name="title" value="<?= h($profile['title'] ?? '') ?>"
                           placeholder="Ex: Développeur Full-Stack React/PHP" class="edit-input"/>
                </div>
                <div class="field">
                    <label class="field-label">Tarif horaire (USD)</label>
                    <div class="edit-input-icon-wrap">
                        <span class="prefix-text">USD</span>
                        <input type="number" name="hourly_rate" min="0" step="100"
                               value="<?= h($profile['hourly_rate'] ?? '') ?>"
                               placeholder="2000" class="edit-input has-prefix"/>
                    </div>
                    <p style="font-size:11px;color:#94a3b8;margin-top:5px;font-style:italic;">Indicatif — négociable selon le projet.</p>
                </div>
                <div class="field">
                    <label class="field-label">Disponibilité</label>
                    <select name="availability" class="edit-input">
                        <option value="available"   <?= ($profile['availability'] ?? '') === 'available'   ? 'selected' : '' ?>>✅ Disponible</option>
                        <option value="busy"        <?= ($profile['availability'] ?? '') === 'busy'        ? 'selected' : '' ?>>🟡 Occupé (partiellement)</option>
                        <option value="unavailable" <?= ($profile['availability'] ?? '') === 'unavailable' ? 'selected' : '' ?>>🔴 Indisponible</option>
                    </select>
                </div>
                <div class="field col-span-2">
                    <label class="field-label">Compétences <span style="color:#94a3b8;text-transform:none;font-weight:400;letter-spacing:0">(séparées par des virgules)</span></label>
                    <input type="text" name="skills" id="skills-input"
                           value="<?= h(implode(', ', $skills)) ?>"
                           placeholder="PHP, JavaScript, React, Figma, Python..."
                           class="edit-input"/>
                    <div id="skills-tags" style="display:flex;flex-wrap:wrap;gap:6px;margin-top:10px;"></div>
                </div>
            </div>
        </div>

        <!-- ══ LIENS PRO ════════════════════════════════════ -->
        <div class="edit-card">
            <div class="section-header">
                <div class="section-icon">
                    <span class="material-symbols-outlined text-xl">link</span>
                </div>
                <span class="section-title">Liens professionnels</span>
            </div>
            <div class="grid-2">
                <div class="field">
                    <label class="field-label">Portfolio</label>
                    <div class="edit-input-icon-wrap">
                        <span class="material-symbols-outlined icon">language</span>
                        <input type="url" name="portfolio_url" value="<?= h($profile['portfolio_url'] ?? '') ?>"
                               placeholder="https://monportfolio.com" class="edit-input"/>
                    </div>
                </div>
                <div class="field">
                    <label class="field-label">LinkedIn</label>
                    <div class="edit-input-icon-wrap">
                        <span class="material-symbols-outlined icon">work</span>
                        <input type="url" name="linkedin_url" value="<?= h($profile['linkedin_url'] ?? '') ?>"
                               placeholder="https://linkedin.com/in/..." class="edit-input"/>
                    </div>
                </div>
                <div class="field">
                    <label class="field-label">GitHub</label>
                    <div class="edit-input-icon-wrap">
                        <span class="material-symbols-outlined icon">code</span>
                        <input type="url" name="github_url" value="<?= h($profile['github_url'] ?? '') ?>"
                               placeholder="https://github.com/..." class="edit-input"/>
                    </div>
                </div>
            </div>
        </div>

        <!-- ══ PROFIL CLIENT ════════════════════════════════ -->
        <?php else: ?>
        <div class="edit-card">
            <div class="section-header">
                <div class="section-icon">
                    <span class="material-symbols-outlined text-xl">business</span>
                </div>
                <span class="section-title">Profil Client</span>
            </div>
            <div class="grid-2">
                <div class="field">
                    <label class="field-label">Nom de l'entreprise</label>
                    <input type="text" name="company_name" value="<?= h($profile['company_name'] ?? '') ?>"
                           placeholder="Nom de votre entreprise ou projet" class="edit-input"/>
                </div>
                <div class="field">
                    <label class="field-label">Site web</label>
                    <div class="edit-input-icon-wrap">
                        <span class="material-symbols-outlined icon">language</span>
                        <input type="url" name="website" value="<?= h($profile['website'] ?? '') ?>"
                               placeholder="https://monsite.com" class="edit-input"/>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- ══ BOUTONS ══════════════════════════════════════ -->
        <div style="display:flex;gap:12px;align-items:center;">
            <button type="submit" class="btn-primary" style="flex:1">
                Sauvegarder
            </button>
            <a href="view.php?id=<?= $user['id'] ?>" class="btn-ghost">
                Voir le profil
            </a>
        </div>
    </form>
</div>

<script>
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
                    img.className = 'avatar-img';
                    def.replaceWith(img);
                    preview = img;
                }
            }
            if (preview) preview.src = e.target.result;
        };
        reader.readAsDataURL(input.files[0]);
    }
}

const skillInput = document.getElementById('skills-input');
const skillTags  = document.getElementById('skills-tags');
if (skillInput && skillTags) {
    function renderTags() {
        const vals = skillInput.value.split(',').map(s => s.trim()).filter(Boolean);
        skillTags.innerHTML = vals.map(v =>
            `<span class="skill-preview-tag">${v}</span>`
        ).join('');
    }
    skillInput.addEventListener('input', renderTags);
    renderTags();
}
</script>

<?php
$appLayout = true;
require_once '../../includes/footer.php';
?>