<?php
// ============================================================
// FRAGMENT — Modal dépôt (Stripe + Mobile Money simulé)
// Inclure AVANT </body> dans wallet/index.php
// Nécessite : $wallet déjà chargé
// ============================================================
?>

<!-- ══════════════════════════════════════════════════════════
     MODAL DÉPÔT
     Ouvrir via : openDepositModal()
     ══════════════════════════════════════════════════════════ -->
<div id="deposit-modal"
     class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-0 sm:p-4"
     style="display:none !important" aria-modal="true" role="dialog">

    <div id="deposit-backdrop"
         class="absolute inset-0 bg-black/50 backdrop-blur-sm transition-opacity duration-300 opacity-0"></div>

    <div id="deposit-panel"
         class="relative w-full sm:max-w-lg bg-white rounded-t-3xl sm:rounded-2xl shadow-2xl
                translate-y-full sm:translate-y-6 sm:scale-95 opacity-0
                transition-all duration-300 ease-out overflow-hidden">

        <!-- Drag handle mobile -->
        <div class="flex justify-center pt-3 pb-1 sm:hidden">
            <div class="w-10 h-1 rounded-full bg-slate-200"></div>
        </div>

        <!-- Header -->
        <div class="flex items-center justify-between px-6 pt-5 pb-4 border-b border-slate-100">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-gradient-to-br from-green-400 to-emerald-600 flex items-center justify-center shadow-sm">
                    <span class="material-symbols-outlined text-white text-xl">add_circle</span>
                </div>
                <div>
                    <h2 class="font-bold text-slate-800 text-base leading-tight">Recharger mon wallet</h2>
                    <p class="text-xs text-slate-400">Fonds disponibles immédiatement</p>
                </div>
            </div>
            <button onclick="closeDepositModal()"
                    class="w-8 h-8 rounded-full bg-slate-100 hover:bg-slate-200 flex items-center justify-center transition-colors">
                <span class="material-symbols-outlined text-slate-500 text-base">close</span>
            </button>
        </div>

        <!-- Solde actuel -->
        <div class="mx-6 mt-5 bg-gradient-to-r from-slate-800 to-slate-700 rounded-2xl p-4 flex items-center justify-between">
            <div>
                <p class="text-slate-400 text-xs mb-0.5">Solde actuel</p>
                <p class="text-white font-bold text-xl" id="dp-balance-display"><?= money((float)$wallet['balance']) ?></p>
            </div>
            <span class="material-symbols-outlined text-slate-500 text-3xl">account_balance_wallet</span>
        </div>

        <!-- Contenu scrollable -->
        <div class="px-6 py-5 space-y-5 max-h-[60vh] overflow-y-auto">

            <!-- Montants rapides -->
            <div>
                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-widest mb-3">Montant rapide</label>
                <div class="grid grid-cols-3 gap-2 mb-3">
                    <?php foreach ([20,50,100,250,500,1000] as $amt): ?>
                    <button type="button" onclick="dpSetAmount(<?= $amt ?>)"
                            data-amount="<?= $amt ?>"
                            class="dp-quick-btn py-2.5 rounded-xl border-2 border-slate-200 text-sm font-bold text-slate-600
                                   hover:border-emerald-400 hover:text-emerald-600 hover:bg-emerald-50
                                   transition-all active:scale-95">
                        <?= number_format($amt, 0, ',', ',') ?>
                    </button>
                    <?php endforeach; ?>
                </div>
                <div class="relative">
                    <span class="absolute left-4 top-1/2 -translate-y-1/2 text-xs font-bold text-slate-400">$</span>
                    <input id="dp-amount" type="number" min="20" step="0.01"
                           placeholder="Or enter a custom amount"
                           class="w-full pl-12 pr-4 py-3 border-2 border-slate-200 rounded-xl text-slate-800 font-semibold
                                  focus:outline-none focus:border-emerald-400 focus:ring-4 focus:ring-emerald-50
                                  transition-all placeholder:font-normal placeholder:text-slate-400"
                           oninput="dpClearQuick()" />
                </div>
            </div>

            <!-- Mode de paiement -->
            <div>
                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-widest mb-3">Mode de paiement</label>
                <div class="grid grid-cols-2 gap-3">

                    <!-- Stripe -->
                    <label id="dp-method-stripe"
                           class="dp-method-card flex flex-col items-center gap-2 p-4 rounded-2xl border-2 border-slate-200
                                  cursor-pointer transition-all hover:border-indigo-300 hover:bg-indigo-50/50
                                  has-[:checked]:border-indigo-500 has-[:checked]:bg-indigo-50 has-[:checked]:shadow-sm">
                        <input type="radio" name="dp-method" value="stripe" class="sr-only" onchange="dpSelectMethod('stripe')" />
                        <svg class="w-8 h-8" viewBox="0 0 60 25" fill="none">
                            <path d="M27.5 10.3c0-1 .8-1.4 2.2-1.4 2 0 4.4.6 6.4 1.6V5.3C33.9 4.5 31.8 4 29.7 4c-4.8 0-8 2.5-8 6.7 0 6.5 8.9 5.5 8.9 8.3 0 1.2-1 1.6-2.4 1.6-2.1 0-4.8-.9-6.9-2V24c2.3 1 4.7 1.4 6.9 1.4 5 0 8.4-2.5 8.4-6.7-.1-7.1-9.1-5.8-9.1-8.4z" fill="#6772E5"/>
                        </svg>
                        <span class="text-sm font-bold text-slate-700">Stripe</span>
                        <span class="text-xs text-slate-400 text-center leading-tight">Carte bancaire</span>
                    </label>

                    <!-- Mobile Money -->
                    <label id="dp-method-mobile"
                           class="dp-method-card flex flex-col items-center gap-2 p-4 rounded-2xl border-2 border-slate-200
                                  cursor-pointer transition-all hover:border-orange-300 hover:bg-orange-50/50
                                  has-[:checked]:border-orange-500 has-[:checked]:bg-orange-50 has-[:checked]:shadow-sm">
                        <input type="radio" name="dp-method" value="mobile_money" class="sr-only" onchange="dpSelectMethod('mobile_money')" />
                        <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-orange-400 to-yellow-500 flex items-center justify-center">
                            <span class="material-symbols-outlined text-white text-lg">smartphone</span>
                        </div>
                        <span class="text-sm font-bold text-slate-700">Mobile Money</span>
                        <span class="text-xs text-slate-400 text-center leading-tight">Orange / MTN / Airtel</span>
                    </label>
                </div>
            </div>

            <!-- Champs Stripe -->
            <div id="dp-stripe-fields" class="hidden space-y-3">
                <div class="bg-indigo-50 border border-indigo-100 rounded-xl px-4 py-3 flex items-center gap-2">
                    <span class="material-symbols-outlined text-indigo-400 text-base">info</span>
                    <p class="text-xs text-indigo-600">Mode test Stripe — utilisez la carte <strong>4242 4242 4242 4242</strong></p>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-500 mb-1.5">Numéro de carte</label>
                    <input id="dp-card-number" type="text" maxlength="19" placeholder="4242 4242 4242 4242"
                           oninput="dpFormatCard(this)"
                           class="w-full px-4 py-3 border-2 border-slate-200 rounded-xl font-mono text-slate-800
                                  focus:outline-none focus:border-indigo-400 focus:ring-4 focus:ring-indigo-50 transition-all" />
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 mb-1.5">Expiration</label>
                        <input id="dp-card-expiry" type="text" maxlength="5" placeholder="MM/AA"
                               oninput="dpFormatExpiry(this)"
                               class="w-full px-4 py-3 border-2 border-slate-200 rounded-xl font-mono text-slate-800
                                      focus:outline-none focus:border-indigo-400 focus:ring-4 focus:ring-indigo-50 transition-all" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 mb-1.5">CVC</label>
                        <input id="dp-card-cvc" type="text" maxlength="3" placeholder="123"
                               class="w-full px-4 py-3 border-2 border-slate-200 rounded-xl font-mono text-slate-800
                                      focus:outline-none focus:border-indigo-400 focus:ring-4 focus:ring-indigo-50 transition-all" />
                    </div>
                </div>
            </div>

            <!-- Champs Mobile Money -->
            <div id="dp-mobile-fields" class="hidden space-y-3">
                <div class="bg-orange-50 border border-orange-100 rounded-xl px-4 py-3 flex items-center gap-2">
                    <span class="material-symbols-outlined text-orange-400 text-base">info</span>
                    <p class="text-xs text-orange-600">Simulation Mobile Money — tout numéro est accepté en mode test</p>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-500 mb-1.5">Opérateur</label>
                    <div class="grid grid-cols-3 gap-2">
                        <?php foreach ([['orange','Orange Money','from-orange-500 to-orange-600'],['mtn','MTN Mobile','from-yellow-400 to-yellow-500'],['airtel','Airtel Money','from-red-500 to-red-600']] as [$v,$l,$g]): ?>
                        <label class="flex flex-col items-center gap-1.5 p-3 rounded-xl border-2 border-slate-200 cursor-pointer
                                      hover:border-slate-300 transition-all has-[:checked]:border-orange-400 has-[:checked]:bg-orange-50">
                            <input type="radio" name="dp-operator" value="<?= $v ?>" class="sr-only" />
                            <div class="w-7 h-7 rounded-lg bg-gradient-to-br <?= $g ?> flex items-center justify-center">
                                <span class="material-symbols-outlined text-white text-sm">sim_card</span>
                            </div>
                            <span class="text-xs font-semibold text-slate-600 text-center leading-tight"><?= $l ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-500 mb-1.5">Numéro Mobile Money</label>
                    <div class="relative">
                        <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-sm">+243</span>
                        <input id="dp-phone" type="tel" maxlength="10" placeholder="07 00 00 00 00"
                               class="w-full pl-14 pr-4 py-3 border-2 border-slate-200 rounded-xl text-slate-800
                                      focus:outline-none focus:border-orange-400 focus:ring-4 focus:ring-orange-50 transition-all" />
                    </div>
                </div>
                <div id="dp-otp-section" class="hidden">
                    <label class="block text-xs font-semibold text-slate-500 mb-1.5">Code OTP reçu par SMS</label>
                    <input id="dp-otp" type="text" maxlength="6" placeholder="_ _ _ _ _ _"
                           class="w-full px-4 py-3 border-2 border-orange-300 rounded-xl font-mono text-slate-800 text-center
                                  tracking-[0.5em] focus:outline-none focus:border-orange-500 focus:ring-4 focus:ring-orange-50 transition-all" />
                    <p class="text-xs text-orange-500 mt-1.5 flex items-center gap-1">
                        <span class="material-symbols-outlined text-sm">schedule</span>
                        Code valable 5 minutes — <button onclick="dpSendOtp()" class="underline font-medium">Renvoyer</button>
                    </p>
                </div>
            </div>

            <!-- Erreur -->
            <div id="dp-error"
                 class="hidden rounded-xl bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-600 flex items-start gap-2">
                <span class="material-symbols-outlined text-base mt-0.5 flex-shrink-0">error</span>
                <span id="dp-error-text"></span>
            </div>

        </div>

        <!-- Footer actions -->
        <div class="px-6 pb-6 pt-3 border-t border-slate-100 space-y-3">
            <!-- Récap montant sélectionné -->
            <div id="dp-amount-recap" class="hidden flex items-center justify-between bg-slate-50 rounded-xl px-4 py-2.5">
                <span class="text-sm text-slate-500">Montant à créditer</span>
                <span id="dp-recap-val" class="font-bold text-slate-800">—</span>
            </div>
            <div class="flex gap-3">
                <button onclick="closeDepositModal()"
                        class="flex-1 py-3 rounded-xl border-2 border-slate-200 text-slate-500 text-sm font-semibold
                               hover:bg-slate-50 transition-colors active:scale-95">
                    Annuler
                </button>
                <button id="dp-submit-btn" onclick="submitDeposit()"
                        class="flex-2 flex-grow py-3 px-6 rounded-xl bg-gradient-to-r from-emerald-500 to-green-600
                               text-white text-sm font-bold shadow-sm hover:opacity-90 transition-all active:scale-95
                               disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2">
                    <span id="dp-btn-label">Recharger maintenant</span>
                    <span id="dp-btn-spinner" class="hidden">
                        <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                        </svg>
                    </span>
                </button>
            </div>
            <p class="text-xs text-slate-400 text-center flex items-center justify-center gap-1">
                <span class="material-symbols-outlined text-sm">lock</span>
                Transactions sécurisées — SSL 256 bits
            </p>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     MODAL SUCCÈS DÉPÔT
     ══════════════════════════════════════════════════════════ -->
<div id="deposit-success-modal"
     class="fixed inset-0 z-50 flex items-center justify-center p-4"
     style="display:none !important">
    <div class="absolute inset-0 bg-black/50 backdrop-blur-sm"></div>
    <div id="dp-success-panel"
         class="relative bg-white rounded-2xl shadow-2xl p-8 max-w-sm w-full text-center
                scale-90 opacity-0 transition-all duration-300">
        <div class="w-20 h-20 rounded-full bg-gradient-to-br from-emerald-400 to-green-600 flex items-center justify-center mx-auto mb-5 shadow-lg">
            <span class="material-symbols-outlined text-white text-4xl">check_circle</span>
        </div>
        <h3 class="text-2xl font-bold text-slate-800 mb-1">Rechargement réussi !</h3>
        <p class="text-slate-400 text-sm mb-5">Votre wallet a été crédité avec succès.</p>
        <div class="bg-emerald-50 border border-emerald-100 rounded-2xl p-5 mb-6">
            <p class="text-xs text-emerald-600 font-semibold uppercase tracking-widest mb-1">Montant crédité</p>
            <p id="dp-success-amount" class="text-3xl font-bold text-emerald-600">—</p>
            <p class="text-xs text-slate-400 mt-1">Nouveau solde : <span id="dp-success-balance" class="font-semibold text-slate-600">—</span></p>
        </div>
        <button onclick="closeDpSuccessModal()"
                class="w-full py-3.5 rounded-xl bg-gradient-to-r from-emerald-500 to-green-600
                       text-white font-bold text-sm hover:opacity-90 transition-all active:scale-95">
            Parfait !
        </button>
    </div>
</div>

<script>
// ══════════════════════════════════════════════════════════
// DÉPÔT — JavaScript
// ══════════════════════════════════════════════════════════
let dpCurrentMethod = null;

const dpFmt = v => new Intl.NumberFormat('en-US', {style:'currency',currency:'USD'}).format(v);

function openDepositModal() {
    const modal = document.getElementById('deposit-modal');
    const panel = document.getElementById('deposit-panel');
    const bd    = document.getElementById('deposit-backdrop');

    // Reset complet
    document.getElementById('dp-amount').value = '';
    document.getElementById('dp-stripe-fields').classList.add('hidden');
    document.getElementById('dp-mobile-fields').classList.add('hidden');
    document.getElementById('dp-error').classList.add('hidden');
    document.getElementById('dp-amount-recap').classList.add('hidden');
    document.getElementById('dp-otp-section').classList.add('hidden');
    document.querySelectorAll('[name="dp-method"]').forEach(r => r.checked = false);
    document.querySelectorAll('.dp-quick-btn').forEach(b => dpResetQuickBtn(b));
    dpCurrentMethod = null;
    dpSetBtnState(false);

    modal.style.removeProperty('display');
    requestAnimationFrame(() => {
        bd.classList.add('opacity-100');
        panel.classList.remove('translate-y-full','sm:translate-y-6','sm:scale-95','opacity-0');
    });
    document.body.style.overflow = 'hidden';
}

function closeDepositModal() {
    const modal = document.getElementById('deposit-modal');
    const panel = document.getElementById('deposit-panel');
    const bd    = document.getElementById('deposit-backdrop');
    bd.classList.remove('opacity-100');
    panel.classList.add('translate-y-full','sm:translate-y-6','sm:scale-95','opacity-0');
    setTimeout(() => { modal.style.display = 'none'; document.body.style.overflow = ''; }, 300);
}

document.getElementById('deposit-backdrop')?.addEventListener('click', closeDepositModal);

// Montants rapides
function dpSetAmount(val) {
    document.getElementById('dp-amount').value = val;
    document.querySelectorAll('.dp-quick-btn').forEach(b => {
        const active = parseInt(b.dataset.amount) === val;
        b.classList.toggle('border-emerald-500', active);
        b.classList.toggle('bg-emerald-50', active);
        b.classList.toggle('text-emerald-600', active);
        b.classList.toggle('border-slate-200', !active);
        b.classList.toggle('text-slate-600', !active);
    });
    dpUpdateRecap(val);
}

function dpClearQuick() {
    document.querySelectorAll('.dp-quick-btn').forEach(b => dpResetQuickBtn(b));
    const v = parseFloat(document.getElementById('dp-amount').value) || 0;
    if (v > 0) dpUpdateRecap(v); else document.getElementById('dp-amount-recap').classList.add('hidden');
}

function dpResetQuickBtn(b) {
    b.classList.remove('border-emerald-500','bg-emerald-50','text-emerald-600');
    b.classList.add('border-slate-200','text-slate-600');
}

function dpUpdateRecap(val) {
    const recap = document.getElementById('dp-amount-recap');
    document.getElementById('dp-recap-val').textContent = dpFmt(val);
    recap.classList.remove('hidden');
}

// Sélection méthode
function dpSelectMethod(method) {
    dpCurrentMethod = method;
    document.getElementById('dp-stripe-fields').classList.toggle('hidden', method !== 'stripe');
    document.getElementById('dp-mobile-fields').classList.toggle('hidden', method !== 'mobile_money');
    document.getElementById('dp-error').classList.add('hidden');
}

// Formatage carte
function dpFormatCard(input) {
    let v = input.value.replace(/\D/g,'').substring(0,16);
    input.value = v.match(/.{1,4}/g)?.join(' ') ?? v;
}
function dpFormatExpiry(input) {
    let v = input.value.replace(/\D/g,'');
    if (v.length >= 2) v = v.substring(0,2) + '/' + v.substring(2,4);
    input.value = v;
}

// Envoi OTP simulé
function dpSendOtp() {
    const phone = document.getElementById('dp-phone').value.replace(/\s/g,'');
    if (phone.length < 8) return dpShowError('Veuillez saisir un numéro valide avant de demander le code.');
    document.getElementById('dp-otp-section').classList.remove('hidden');
    dpShowInfo('Code OTP envoyé au +225 ' + phone + ' (simulation)');
}

// Erreur / Info
function dpShowError(msg) {
    const box = document.getElementById('dp-error');
    const txt = document.getElementById('dp-error-text');
    txt.textContent = msg;
    box.classList.remove('hidden');
    box.style.background = '#fef2f2';
    box.style.borderColor = '#fecaca';
    box.style.color = '#dc2626';
    box.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}
function dpShowInfo(msg) {
    const box = document.getElementById('dp-error');
    const txt = document.getElementById('dp-error-text');
    txt.textContent = msg;
    box.classList.remove('hidden');
    box.style.background = '#f0fdf4';
    box.style.borderColor = '#bbf7d0';
    box.style.color = '#16a34a';
}
function dpClearError() { document.getElementById('dp-error').classList.add('hidden'); }

function dpSetBtnState(loading) {
    const btn = document.getElementById('dp-submit-btn');
    btn.disabled = loading;
    document.getElementById('dp-btn-label').textContent = loading ? 'Traitement…' : 'Recharger maintenant';
    document.getElementById('dp-btn-spinner').classList.toggle('hidden', !loading);
}

// Soumission
async function submitDeposit() {
    dpClearError();
    const amount = parseFloat(document.getElementById('dp-amount').value) || 0;

    if (amount < 20)       return dpShowError('Le montant minimum est de 20 USD.');
    if (amount > 5000)   return dpShowError('Le montant maximum est de 5 000 USD.');
    if (!dpCurrentMethod)   return dpShowError('Veuillez choisir un mode de paiement.');

    if (dpCurrentMethod === 'stripe') {
        const card   = document.getElementById('dp-card-number').value.replace(/\s/g,'');
        const expiry = document.getElementById('dp-card-expiry').value;
        const cvc    = document.getElementById('dp-card-cvc').value;
        if (card.length !== 16)    return dpShowError('Numéro de carte invalide.');
        if (expiry.length !== 5)   return dpShowError('Date d\'expiration invalide.');
        if (cvc.length !== 3)      return dpShowError('CVC invalide.');
    }

    if (dpCurrentMethod === 'mobile_money') {
        const operator = document.querySelector('[name="dp-operator"]:checked');
        const phone    = document.getElementById('dp-phone').value.replace(/\s/g,'');
        if (!operator)             return dpShowError('Veuillez sélectionner un opérateur.');
        if (phone.length < 8)      return dpShowError('Numéro de téléphone invalide.');
        // Afficher le champ OTP si pas encore fait
        const otpSection = document.getElementById('dp-otp-section');
        if (otpSection.classList.contains('hidden')) {
            otpSection.classList.remove('hidden');
            dpShowInfo('Code OTP envoyé au +225 ' + phone + ' (simulation). Saisissez 123456 pour tester.');
            return;
        }
        const otp = document.getElementById('dp-otp').value.replace(/\s/g,'');
        if (otp !== '123456')      return dpShowError('Code OTP invalide. Utilisez 123456 pour tester.');
    }

    dpSetBtnState(true);

    try {
        const form = new FormData();
        form.append('amount', amount);
        form.append('method', dpCurrentMethod);
        if (dpCurrentMethod === 'mobile_money') {
            form.append('operator', document.querySelector('[name="dp-operator"]:checked')?.value ?? '');
            form.append('phone', document.getElementById('dp-phone').value);
        }

        const res  = await fetch('/upc_freelance/app/wallet/deposit_handler.php', { method: 'POST', body: form });
        const data = await res.json();

        if (data.success) {
            closeDepositModal();
            openDpSuccessModal(data.amount, data.new_balance);
        } else {
            dpShowError(data.message || 'Une erreur est survenue.');
        }
    } catch(e) {
        dpShowError('Impossible de contacter le serveur. Vérifiez votre connexion.');
    } finally {
        dpSetBtnState(false);
    }
}

// Modal succès
function openDpSuccessModal(amount, newBalance) {
    document.getElementById('dp-success-amount').textContent  = dpFmt(amount);
    document.getElementById('dp-success-balance').textContent = dpFmt(newBalance);
    const modal = document.getElementById('deposit-success-modal');
    const panel = document.getElementById('dp-success-panel');
    modal.style.removeProperty('display');
    requestAnimationFrame(() => panel.classList.remove('scale-90','opacity-0'));
    document.body.style.overflow = 'hidden';
}
function closeDpSuccessModal() {
    const panel = document.getElementById('dp-success-panel');
    panel.classList.add('scale-90','opacity-0');
    setTimeout(() => {
        document.getElementById('deposit-success-modal').style.display = 'none';
        document.body.style.overflow = '';
        window.location.reload();
    }, 300);
}
</script>