<?php
// ============================================================
// UPC FREELANCE — Vérification numéro de téléphone
// /var/www/html/upc_freelance/app/verification/phone.php
// ============================================================

require_once '../../includes/middleware.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../includes/db.php';

requireLogin();

$user   = currentUser();
$pdo    = getDB();
$step   = $_SESSION['phone_verify_step'] ?? 'form'; // form | code
$phone  = $_SESSION['phone_pending']     ?? $user['phone'] ?? '';

// ── Étape 1 : soumettre le numéro ─────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_code'])) {
    verifyCsrf();

    if (!rateLimit('phone_code', 3, 600)) {
        flash('error', 'Trop de tentatives. Réessayez dans 10 minutes.');
        redirect('../../app/verification/phone.php');
    }

    $rawPhone = sanitize($_POST['phone'] ?? '');
    // Normaliser le numéro
    $normalized = preg_replace('/\s+/', '', $rawPhone);
    if (!preg_match('/^\+?[0-9]{8,15}$/', $normalized)) {
        flash('error', 'Numéro de téléphone invalide. Format attendu : +225 07 XX XX XX XX');
        redirect('../../app/verification/phone.php');
    }

    // Générer un code OTP à 6 chiffres
    $otp     = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $expires = time() + 600; // 10 minutes

    $_SESSION['phone_pending']      = $normalized;
    $_SESSION['phone_otp']          = password_hash($otp, PASSWORD_BCRYPT);
    $_SESSION['phone_otp_expires']  = $expires;
    $_SESSION['phone_verify_step']  = 'code';

    // En production : envoyer via SMS (Twilio, Africa's Talking, etc.)
    // Pour le dev : on affiche le code directement (à supprimer en prod)
    error_log('[UPC] OTP pour ' . $normalized . ' : ' . $otp);

    // Simuler l'envoi en dev — stocker en session pour affichage
    if ($_ENV['APP_ENV'] ?? 'dev' === 'dev') {
        $_SESSION['dev_otp'] = $otp; // ← RETIRER EN PRODUCTION
    }

    flash('success', 'Code envoyé au ' . $normalized . ' (valable 10 minutes).');
    redirect('../../app/verification/phone.php');
}

// ── Étape 2 : vérifier le code OTP ────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_code'])) {
    verifyCsrf();

    $enteredCode = sanitize($_POST['otp_code'] ?? '');

    if (empty($_SESSION['phone_otp']) || empty($_SESSION['phone_otp_expires'])) {
        flash('error', 'Session expirée. Veuillez recommencer.');
        unset($_SESSION['phone_verify_step'], $_SESSION['phone_otp'], $_SESSION['phone_otp_expires']);
        redirect('../../app/verification/phone.php');
    }

    if (time() > $_SESSION['phone_otp_expires']) {
        flash('error', 'Code expiré. Veuillez en demander un nouveau.');
        unset($_SESSION['phone_verify_step'], $_SESSION['phone_otp'], $_SESSION['phone_otp_expires']);
        redirect('../../app/verification/phone.php');
    }

    if (!password_verify($enteredCode, $_SESSION['phone_otp'])) {
        flash('error', 'Code incorrect. Vérifiez le code reçu.');
        redirect('../../app/verification/phone.php');
    }

    // ✅ Code correct → enregistrer le numéro
    $verifiedPhone = $_SESSION['phone_pending'];
    $pdo->prepare('UPDATE users SET phone = ?, updated_at = NOW() WHERE id = ?')
        ->execute([$verifiedPhone, $user['id']]);

    // Nettoyer la session
    unset($_SESSION['phone_pending'], $_SESSION['phone_otp'],
          $_SESSION['phone_otp_expires'], $_SESSION['phone_verify_step'], $_SESSION['dev_otp']);

    sendNotification($user['id'], 'phone_verified', 'Téléphone vérifié !',
        'Votre numéro ' . $verifiedPhone . ' a été vérifié avec succès.',
        '/upc_freelance/app/verification/index.php');

    flash('success', 'Numéro de téléphone vérifié avec succès ! ✓');
    redirect('../../app/verification/index.php');
}

// ── Renvoyer le code ──────────────────────────────────────────
if (isset($_GET['resend'])) {
    unset($_SESSION['phone_verify_step'], $_SESSION['phone_otp'],
          $_SESSION['phone_otp_expires'], $_SESSION['dev_otp']);
    flash('info', 'Vous pouvez demander un nouveau code.');
    redirect('../../app/verification/phone.php');
}

$step     = $_SESSION['phone_verify_step'] ?? 'form';
$devOtp   = $_SESSION['dev_otp']           ?? null;

$pageTitle = 'Vérifier mon téléphone — UPC Freelance';
$appLayout = true;
require_once '../../includes/header.php';
?>

<div class="mb-8">
    <a href="/upc_freelance/app/verification/index.php" class="inline-flex items-center gap-1 text-sm text-secondary hover:underline mb-3">
        <span class="material-symbols-outlined text-base">arrow_back</span> Vérification du compte
    </a>
    <h1 class="text-2xl font-bold text-primary">Vérifier mon téléphone</h1>
    <p class="text-on-surface-variant text-sm mt-1">
        Un numéro vérifié renforce la confiance et sécurise votre compte.
    </p>
</div>

<div class="max-w-md">
    <?php renderFlash(); ?>

    <?php if ($step === 'form'): ?>
    <!-- ── Étape 1 : Saisir le numéro ──────────────────────── -->
    <div class="bg-white rounded-2xl border border-slate-100 p-8 custom-shadow-low">

        <!-- Statut actuel -->
        <?php if ($user['phone']): ?>
        <div class="flex items-center gap-3 p-3 bg-emerald-50 rounded-xl border border-emerald-200 mb-6">
            <span class="material-symbols-outlined text-emerald-500" style="font-variation-settings:'FILL' 1">check_circle</span>
            <div>
                <p class="text-sm font-semibold text-emerald-700">Numéro actuel</p>
                <p class="text-sm text-emerald-600"><?= h($user['phone']) ?></p>
            </div>
        </div>
        <?php endif; ?>

        <form method="POST" class="space-y-5" novalidate>
            <?= csrfField() ?>
            <div>
                <label class="block text-sm font-medium text-primary mb-1.5">
                    Numéro de téléphone <span class="text-red-500">*</span>
                </label>
                <div class="flex gap-2">
                    <!-- Indicatif pays -->
                    <select id="country-code" class="px-3 py-3 rounded-xl border border-outline-variant focus:border-secondary outline-none text-sm bg-surface-container-low flex-shrink-0 w-32">
                        <option value="+225">🇨🇮 +225</option>
                        <option value="+33">🇫🇷 +33</option>
                        <option value="+32">🇧🇪 +32</option>
                        <option value="+41">🇨🇭 +41</option>
                        <option value="+221">🇸🇳 +221</option>
                        <option value="+237">🇨🇲 +237</option>
                        <option value="+243">🇨🇩 +243</option>
                        <option value="+212">🇲🇦 +212</option>
                        <option value="+213">🇩🇿 +213</option>
                        <option value="+216">🇹🇳 +216</option>
                    </select>
                    <div class="relative flex-1">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 material-symbols-outlined text-slate-400 text-lg">phone</span>
                        <input type="tel" name="phone" id="phone-input" required
                               placeholder="07 XX XX XX XX"
                               value="<?= h(str_replace(['+225','+33','+32','+41','+221','+237','+243','+212','+213','+216'], '', $user['phone'] ?? '')) ?>"
                               class="w-full pl-10 pr-4 py-3 rounded-xl border border-outline-variant focus:border-secondary focus:ring-2 focus:ring-secondary/20 outline-none text-sm transition-all"/>
                    </div>
                </div>
                <p class="text-xs text-slate-400 mt-1">Un code de vérification à 6 chiffres vous sera envoyé par SMS.</p>
            </div>

            <button type="submit" name="send_code"
                    class="w-full bg-primary text-white font-button text-button py-3.5 rounded-xl hover:opacity-90 transition-opacity active:scale-95 shadow-sm">
                <span class="material-symbols-outlined align-middle mr-1">sms</span>
                Envoyer le code par SMS
            </button>
        </form>
    </div>

    <?php else: ?>
    <!-- ── Étape 2 : Saisir le code OTP ───────────────────── -->
    <div class="bg-white rounded-2xl border border-slate-100 p-8 custom-shadow-low">

        <div class="text-center mb-8">
            <div class="w-16 h-16 bg-secondary/10 rounded-2xl flex items-center justify-center mx-auto mb-4">
                <span class="material-symbols-outlined text-secondary text-3xl">sms</span>
            </div>
            <h2 class="font-bold text-primary text-lg">Code envoyé !</h2>
            <p class="text-sm text-on-surface-variant mt-1">
                Entrez le code à 6 chiffres envoyé au<br>
                <strong class="text-primary"><?= h($phone) ?></strong>
            </p>
            <p class="text-xs text-slate-400 mt-1">Le code expire dans 10 minutes.</p>
        </div>

        <?php if ($devOtp): ?>
        <!-- Bandeau DEV uniquement -->
        <div class="mb-5 p-3 bg-amber-50 border border-amber-200 rounded-xl">
            <p class="text-xs text-amber-700 font-semibold flex items-center gap-1 mb-1">
                <span class="material-symbols-outlined text-sm">developer_mode</span>
                Mode développement (à supprimer en production)
            </p>
            <p class="text-sm font-mono font-bold text-amber-800 tracking-widest text-center text-2xl">
                <?= h($devOtp) ?>
            </p>
        </div>
        <?php endif; ?>

        <form method="POST" class="space-y-5" novalidate>
            <?= csrfField() ?>
            <div>
                <label class="block text-sm font-medium text-primary mb-3 text-center">Code de vérification</label>
                <!-- Input OTP stylisé -->
                <div class="flex justify-center gap-3 mb-2" id="otp-boxes">
                    <?php for ($i = 0; $i < 6; $i++): ?>
                    <input type="text" maxlength="1" inputmode="numeric" pattern="[0-9]"
                           class="otp-digit w-12 h-14 text-center text-2xl font-bold border-2 border-outline-variant rounded-xl focus:border-secondary focus:ring-2 focus:ring-secondary/20 outline-none transition-all"
                           autocomplete="off"/>
                    <?php endfor; ?>
                </div>
                <input type="hidden" name="otp_code" id="otp-hidden"/>
            </div>

            <button type="submit" name="verify_code" id="verify-btn"
                    class="w-full bg-primary text-white font-button text-button py-3.5 rounded-xl hover:opacity-90 transition-opacity active:scale-95 shadow-sm">
                <span class="material-symbols-outlined align-middle mr-1">verified</span>
                Vérifier le code
            </button>
        </form>

        <div class="flex items-center justify-between mt-5 pt-4 border-t border-slate-100">
            <a href="?resend=1" class="text-sm text-secondary hover:underline flex items-center gap-1">
                <span class="material-symbols-outlined text-base">refresh</span>
                Renvoyer un code
            </a>
            <a href="/upc_freelance/app/verification/phone.php"
               class="text-sm text-on-surface-variant hover:underline">
                Changer de numéro
            </a>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
// Combiner l'indicatif + numéro
<?php if ($step === 'form'): ?>
document.querySelector('form').addEventListener('submit', function() {
    const code  = document.getElementById('country-code').value;
    const input = document.getElementById('phone-input');
    const raw   = input.value.replace(/\s+/g, '').replace(/^0/, '');
    input.value = code + raw;
});
<?php else: ?>
// Gestion OTP boxes
const otpBoxes = document.querySelectorAll('.otp-digit');
const hidden   = document.getElementById('otp-hidden');

otpBoxes.forEach((box, idx) => {
    box.addEventListener('input', e => {
        const val = e.target.value.replace(/\D/g,'');
        e.target.value = val;
        if (val && idx < otpBoxes.length - 1) otpBoxes[idx + 1].focus();
        updateHidden();
    });
    box.addEventListener('keydown', e => {
        if (e.key === 'Backspace' && !e.target.value && idx > 0) {
            otpBoxes[idx - 1].focus();
            otpBoxes[idx - 1].value = '';
            updateHidden();
        }
    });
    box.addEventListener('paste', e => {
        e.preventDefault();
        const pasted = e.clipboardData.getData('text').replace(/\D/g,'').slice(0,6);
        pasted.split('').forEach((ch, i) => { if (otpBoxes[i]) otpBoxes[i].value = ch; });
        if (otpBoxes[pasted.length - 1]) otpBoxes[pasted.length - 1].focus();
        updateHidden();
    });
});

function updateHidden() {
    hidden.value = [...otpBoxes].map(b => b.value).join('');
    const full   = hidden.value.length === 6;
    const btn    = document.getElementById('verify-btn');
    btn.disabled = !full;
    btn.className = btn.className.replace(full ? 'opacity-50 cursor-not-allowed' : '', '');
    if (full) otpBoxes.forEach(b => b.classList.add('border-secondary','bg-secondary/5'));
}

// Auto-submit si les 6 chiffres sont entrés
setInterval(() => { if(hidden.value.length === 6 && document.activeElement !== document.getElementById('verify-btn')) {} }, 300);

// Focus premier champ
otpBoxes[0]?.focus();
<?php endif; ?>
</script>

<?php $appLayout = true; require_once '../../includes/footer.php'; ?>
