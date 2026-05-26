<?php
// ============================================================
// UPC FREELANCE — Créer un administrateur
// /upc_freelance/admin/admins/create.php
// ============================================================
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
require_once '../includes/functions.php';
require_once '../includes/db.php';

if (session_status() === PHP_SESSION_NONE) session_start();

$pdo = getDB();
$isAdminSession = false;
$admin = ['id' => 0, 'name' => 'Setup', 'email' => '', 'super' => true];
$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $name     = sanitize($_POST['name']     ?? '');
    $email    = sanitize($_POST['email']    ?? '');
    $password = $_POST['password']          ?? '';
    $confirm  = $_POST['password_confirm']  ?? '';
    $isSuper  = isset($_POST['is_super']) ? 1 : 0;

    // Validations
    if (empty($name))                          $errors[] = 'Le nom est requis.';
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL))
                                               $errors[] = 'Email invalide.';
    if (strlen($password) < 8)                 $errors[] = 'Le mot de passe doit faire au moins 8 caractères.';
    if (!preg_match('/[A-Z]/', $password))     $errors[] = 'Le mot de passe doit contenir au moins une majuscule.';
    if (!preg_match('/[0-9]/', $password))     $errors[] = 'Le mot de passe doit contenir au moins un chiffre.';
    if ($password !== $confirm)                $errors[] = 'Les mots de passe ne correspondent pas.';

    // Email déjà utilisé ?
    if (empty($errors)) {
        $stmt = $pdo->prepare('SELECT id FROM admin_users WHERE email = ?');
        $stmt->execute([$email]);
        if ($stmt->fetch()) $errors[] = 'Cet email est déjà utilisé par un autre administrateur.';
    }

    if (empty($errors)) {
        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        $pdo->prepare('INSERT INTO admin_users (name, email, password_hash, is_super) VALUES (?, ?, ?, ?)')
            ->execute([$name, $email, $hash, $isSuper]);
        $success = true;
        // Reset les champs
        $name = $email = '';
        $isSuper = 0;
    }
}

// Liste des admins existants
$admins = $pdo->query('SELECT id, name, email, is_super, created_at FROM admin_users ORDER BY created_at DESC')->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Créer un administrateur — UPC Freelance</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
<style>
* { font-family: 'Inter', sans-serif; }
.material-symbols-outlined {
    font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
    vertical-align: middle;
}
body { background: #0f172a; min-height: 100vh; }

.card {
    background: #1e293b;
    border: 1px solid #334155;
    border-radius: 16px;
}
.field-label {
    display: block;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.07em;
    text-transform: uppercase;
    color: #94a3b8;
    margin-bottom: 6px;
}
.field-input {
    width: 100%;
    background: #0f172a;
    border: 1.5px solid #334155;
    border-radius: 10px;
    padding: 11px 14px;
    color: #f1f5f9;
    font-size: 14px;
    outline: none;
    transition: border-color 0.15s, box-shadow 0.15s;
}
.field-input:focus {
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59,130,246,0.15);
}
.field-input::placeholder { color: #475569; }
.field-input-icon {
    position: relative;
}
.field-input-icon .icon {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: #475569;
    font-size: 18px;
    pointer-events: none;
}
.field-input-icon .field-input { padding-left: 40px; }

.btn-primary {
    width: 100%;
    background: linear-gradient(135deg, #2563eb, #1d4ed8);
    color: #fff;
    font-weight: 700;
    font-size: 14px;
    padding: 13px;
    border-radius: 10px;
    border: none;
    cursor: pointer;
    transition: opacity 0.15s, transform 0.1s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    box-shadow: 0 4px 14px rgba(37,99,235,0.35);
}
.btn-primary:hover   { opacity: 0.9; }
.btn-primary:active  { transform: scale(0.97); }

.strength-bar {
    height: 4px;
    border-radius: 999px;
    background: #1e293b;
    overflow: hidden;
    margin-top: 8px;
}
.strength-fill {
    height: 100%;
    border-radius: 999px;
    transition: width 0.3s, background 0.3s;
}

.toggle-super {
    position: relative;
    display: inline-flex;
    align-items: center;
    cursor: pointer;
    gap: 12px;
}
.toggle-super input { display: none; }
.toggle-track {
    width: 44px; height: 24px;
    background: #334155;
    border-radius: 999px;
    position: relative;
    transition: background 0.2s;
}
.toggle-super input:checked + .toggle-track { background: #2563eb; }
.toggle-thumb {
    position: absolute;
    top: 3px; left: 3px;
    width: 18px; height: 18px;
    background: #fff;
    border-radius: 50%;
    transition: transform 0.2s;
    box-shadow: 0 1px 4px rgba(0,0,0,0.3);
}
.toggle-super input:checked + .toggle-track .toggle-thumb {
    transform: translateX(20px);
}

.admin-row {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 14px 0;
    border-bottom: 1px solid #1e293b;
}
.admin-row:last-child { border-bottom: none; }
.admin-avatar {
    width: 40px; height: 40px;
    border-radius: 10px;
    background: #1e3a5f;
    display: flex; align-items: center; justify-content: center;
    font-weight: 700; font-size: 16px; color: #60a5fa;
    flex-shrink: 0;
}
</style>
</head>
<body>

<!-- Top bar -->
<header style="background:#1e293b;border-bottom:1px solid #334155;padding:14px 32px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:50;">
    <div style="display:flex;align-items:center;gap:12px;">
        <a href="/upc_freelance/admin/dashboard.php" style="display:flex;align-items:center;gap:8px;text-decoration:none;">
            <svg width="32" height="32" viewBox="0 0 38 38" fill="none" xmlns="http://www.w3.org/2000/svg">
                <rect width="38" height="38" rx="10" fill="#002045"/>
                <path d="M10 12 L10 22 Q10 28 19 28 Q28 28 28 22 L28 12" stroke="#fff" stroke-width="2.5" stroke-linecap="round" fill="none"/>
                <circle cx="19" cy="12" r="1.5" fill="#66affe"/>
                <path d="M14 18 L24 18" stroke="#66affe" stroke-width="2" stroke-linecap="round"/>
            </svg>
            <span style="color:#f1f5f9;font-weight:700;font-size:16px;">UPC Admin</span>
        </a>
        <span style="color:#334155;font-size:18px;">›</span>
        <span style="color:#94a3b8;font-size:14px;">Gestion des administrateurs</span>
    </div>
    <div style="display:flex;align-items:center;gap:16px;">
        <?php if ($isAdminSession): ?>
        <div style="display:flex;align-items:center;gap:8px;">
            <div style="width:32px;height:32px;border-radius:8px;background:#1e3a5f;display:flex;align-items:center;justify-content:center;font-weight:700;color:#60a5fa;font-size:13px;">
                <?= mb_strtoupper(mb_substr($admin['name'], 0, 1)) ?>
            </div>
            <div>
                <p style="font-size:13px;font-weight:600;color:#f1f5f9;"><?= h($admin['name']) ?></p>
                <p style="font-size:10px;color:#3b82f6;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;">Super Admin</p>
            </div>
        </div>
        <a href="/upc_freelance/admin/logout.php"
           style="display:flex;align-items:center;gap:6px;color:#ef4444;font-size:13px;text-decoration:none;padding:8px 12px;border-radius:8px;border:1px solid #3f1515;transition:background 0.15s;"
           onmouseover="this.style.background='#1f0a0a'"
           onmouseout="this.style.background='transparent'">
            <span class="material-symbols-outlined" style="font-size:16px;">logout</span> Déconnexion
        </a>
        <?php else: ?>
        <span style="background:#1e3a5f;color:#60a5fa;font-size:11px;font-weight:700;padding:4px 12px;border-radius:999px;text-transform:uppercase;letter-spacing:0.05em;">
            🔧 Mode Setup
        </span>
        <?php endif; ?>
    </div>
</header>

<div style="max-width:1000px;margin:40px auto;padding:0 24px;display:grid;grid-template-columns:1fr 1fr;gap:32px;align-items:start;">

    <!-- ══ Formulaire création ══════════════════════════════ -->
    <div>
        <div style="margin-bottom:24px;">
            <h1 style="font-size:24px;font-weight:700;color:#f1f5f9;margin-bottom:6px;">Créer un administrateur</h1>
            <p style="font-size:14px;color:#64748b;">Ajoutez un nouveau compte d'accès au panel d'administration.</p>
        </div>

        <!-- Alertes -->
        <?php if ($success): ?>
        <div style="background:#052e16;border:1px solid #166534;border-radius:10px;padding:14px 16px;margin-bottom:20px;display:flex;align-items:center;gap:10px;">
            <span class="material-symbols-outlined" style="color:#4ade80;font-size:20px;" >check_circle</span>
            <p style="color:#86efac;font-size:14px;font-weight:500;">Administrateur créé avec succès !</p>
        </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
        <div style="background:#2d0a0a;border:1px solid #7f1d1d;border-radius:10px;padding:14px 16px;margin-bottom:20px;">
            <?php foreach ($errors as $e): ?>
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;last:margin-bottom:0">
                <span class="material-symbols-outlined" style="color:#f87171;font-size:16px;">error</span>
                <p style="color:#fca5a5;font-size:13px;"><?= h($e) ?></p>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="card" style="padding:28px;">
            <form method="POST" novalidate id="create-form">
                <?= csrfField() ?>

                <div style="display:flex;flex-direction:column;gap:18px;">

                    <!-- Nom -->
                    <div>
                        <label class="field-label">Nom complet <span style="color:#ef4444">*</span></label>
                        <div class="field-input-icon">
                            <span class="material-symbols-outlined icon">person</span>
                            <input type="text" name="name" class="field-input"
                                   value="<?= h($name ?? '') ?>"
                                   placeholder="Ex: Jean Kouassi" required/>
                        </div>
                    </div>

                    <!-- Email -->
                    <div>
                        <label class="field-label">Adresse email <span style="color:#ef4444">*</span></label>
                        <div class="field-input-icon">
                            <span class="material-symbols-outlined icon">mail</span>
                            <input type="email" name="email" class="field-input"
                                   value="<?= h($email ?? '') ?>"
                                   placeholder="admin@upcfreelance.com" required/>
                        </div>
                    </div>

                    <!-- Mot de passe -->
                    <div>
                        <label class="field-label">Mot de passe <span style="color:#ef4444">*</span></label>
                        <div class="field-input-icon" style="position:relative;">
                            <span class="material-symbols-outlined icon">lock</span>
                            <input type="password" name="password" id="password-input" class="field-input"
                                   placeholder="Min. 8 caractères, 1 majuscule, 1 chiffre"
                                   oninput="checkStrength(this.value)" required/>
                            <button type="button" onclick="togglePassword('password-input', this)"
                                    style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:#475569;padding:0;">
                                <span class="material-symbols-outlined" style="font-size:18px;">visibility</span>
                            </button>
                        </div>
                        <!-- Barre de force -->
                        <div class="strength-bar">
                            <div class="strength-fill" id="strength-fill" style="width:0%;background:#ef4444;"></div>
                        </div>
                        <p id="strength-label" style="font-size:11px;color:#475569;margin-top:4px;"></p>
                    </div>

                    <!-- Confirmer mot de passe -->
                    <div>
                        <label class="field-label">Confirmer le mot de passe <span style="color:#ef4444">*</span></label>
                        <div class="field-input-icon" style="position:relative;">
                            <span class="material-symbols-outlined icon">lock_reset</span>
                            <input type="password" name="password_confirm" id="confirm-input" class="field-input"
                                   placeholder="Répétez le mot de passe" required/>
                            <button type="button" onclick="togglePassword('confirm-input', this)"
                                    style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:#475569;padding:0;">
                                <span class="material-symbols-outlined" style="font-size:18px;">visibility</span>
                            </button>
                        </div>
                        <p id="match-label" style="font-size:11px;margin-top:4px;"></p>
                    </div>

                    <!-- Super admin toggle -->
                    <div style="background:#0f172a;border-radius:10px;padding:14px 16px;display:flex;align-items:center;justify-content:space-between;">
                        <div>
                            <p style="font-size:14px;font-weight:600;color:#f1f5f9;">Super Administrateur</p>
                            <p style="font-size:12px;color:#64748b;margin-top:2px;">Accès complet — peut créer d'autres admins</p>
                        </div>
                        <label class="toggle-super">
                            <input type="checkbox" name="is_super" <?= !empty($isSuper) ? 'checked' : '' ?>>
                            <div class="toggle-track">
                                <div class="toggle-thumb"></div>
                            </div>
                        </label>
                    </div>

                    <button type="submit" class="btn-primary">
                        <span class="material-symbols-outlined" style="font-size:18px;">admin_panel_settings</span>
                        Créer l'administrateur
                    </button>
                </div>
            </form>
        </div>

        <!-- Info sécurité -->
        <div style="margin-top:16px;padding:12px 16px;background:#1e293b;border-radius:10px;border:1px solid #334155;display:flex;gap:10px;align-items:flex-start;">
            <span class="material-symbols-outlined" style="color:#f59e0b;font-size:18px;flex-shrink:0;margin-top:1px;">security</span>
            <p style="font-size:12px;color:#64748b;line-height:1.6;">
                Les identifiants seront communiqués manuellement à l'administrateur.
                Assurez-vous de transmettre le mot de passe via un canal sécurisé.
            </p>
        </div>
    </div>

    <!-- ══ Liste des admins ══════════════════════════════════ -->
    <div>
        <div style="margin-bottom:24px;">
            <h2 style="font-size:18px;font-weight:700;color:#f1f5f9;margin-bottom:4px;">
                Administrateurs existants
            </h2>
            <p style="font-size:13px;color:#64748b;"><?= count($admins) ?> compte<?= count($admins) > 1 ? 's' : '' ?></p>
        </div>

        <div class="card" style="padding:8px 24px;">
            <?php if (empty($admins)): ?>
            <p style="text-align:center;color:#475569;padding:32px 0;font-size:14px;">Aucun administrateur.</p>
            <?php else: ?>
            <?php foreach ($admins as $a): ?>
            <div class="admin-row">
                <div class="admin-avatar">
                    <?= mb_strtoupper(mb_substr($a['name'], 0, 1)) ?>
                </div>
                <div style="flex:1;min-width:0;">
                    <div style="display:flex;align-items:center;gap:8px;">
                        <p style="font-weight:600;color:#f1f5f9;font-size:14px;"><?= h($a['name']) ?></p>
                        <?php if ($a['is_super']): ?>
                        <span style="background:#1e3a5f;color:#60a5fa;font-size:10px;font-weight:700;padding:2px 8px;border-radius:999px;text-transform:uppercase;letter-spacing:0.05em;">
                            Super
                        </span>
                        <?php endif; ?>
                        <?php if ($isAdminSession && $a['id'] === $admin['id']): ?>
                        <span style="background:#052e16;color:#4ade80;font-size:10px;font-weight:700;padding:2px 8px;border-radius:999px;">
                            Vous
                        </span>
                        <?php endif; ?>
                    </div>
                    <p style="font-size:12px;color:#64748b;margin-top:2px;"><?= h($a['email']) ?></p>
                    <p style="font-size:11px;color:#334155;margin-top:2px;">
                        Créé le <?= date('d/m/Y', strtotime($a['created_at'])) ?>
                    </p>
                </div>
                <!-- Supprimer (sauf soi-même) -->
                <?php if ($isAdminSession && $a['id'] !== $admin['id']): ?>
                <form method="POST" action="/upc_freelance/admin/admins/delete.php"
                      onsubmit="return confirm('Supprimer cet administrateur ?')">
                    <?= csrfField() ?>
                    <input type="hidden" name="admin_id" value="<?= $a['id'] ?>"/>
                    <button type="submit"
                            style="background:none;border:none;cursor:pointer;color:#ef4444;padding:6px;border-radius:8px;transition:background 0.15s;"
                            onmouseover="this.style.background='#2d0a0a'"
                            onmouseout="this.style.background='none'"
                            title="Supprimer">
                        <span class="material-symbols-outlined" style="font-size:18px;">delete</span>
                    </button>
                </form>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// ── Afficher/masquer mot de passe ─────────────────────────
function togglePassword(inputId, btn) {
    const input = document.getElementById(inputId);
    const icon  = btn.querySelector('.material-symbols-outlined');
    if (input.type === 'password') {
        input.type = 'text';
        icon.textContent = 'visibility_off';
    } else {
        input.type = 'password';
        icon.textContent = 'visibility';
    }
}

// ── Force du mot de passe ─────────────────────────────────
function checkStrength(val) {
    const fill  = document.getElementById('strength-fill');
    const label = document.getElementById('strength-label');
    let score = 0;
    if (val.length >= 8)              score++;
    if (/[A-Z]/.test(val))            score++;
    if (/[0-9]/.test(val))            score++;
    if (/[^A-Za-z0-9]/.test(val))     score++;
    if (val.length >= 12)             score++;

    const levels = [
        { pct: '0%',   color: '#334155', label: '' },
        { pct: '25%',  color: '#ef4444', label: '🔴 Très faible' },
        { pct: '50%',  color: '#f97316', label: '🟠 Faible' },
        { pct: '70%',  color: '#eab308', label: '🟡 Moyen' },
        { pct: '90%',  color: '#22c55e', label: '🟢 Fort' },
        { pct: '100%', color: '#10b981', label: '✅ Très fort' },
    ];
    const lvl = levels[score] || levels[0];
    fill.style.width      = lvl.pct;
    fill.style.background = lvl.color;
    label.textContent     = lvl.label;
    label.style.color     = lvl.color;
}

// ── Vérification concordance ──────────────────────────────
document.getElementById('confirm-input').addEventListener('input', function () {
    const pass    = document.getElementById('password-input').value;
    const confirm = this.value;
    const label   = document.getElementById('match-label');
    if (!confirm) { label.textContent = ''; return; }
    if (pass === confirm) {
        label.textContent = '✓ Les mots de passe correspondent';
        label.style.color = '#4ade80';
    } else {
        label.textContent = '✗ Les mots de passe ne correspondent pas';
        label.style.color = '#f87171';
    }
});
</script>

</body>
</html>