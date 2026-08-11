<?php
// ============================================================
// FRAGMENT — Modal retrait (vers compte bancaire + Mobile Money)
// Inclure AVANT </body> dans wallet/index.php
// ============================================================
?>

<!-- ══════════════════════════════════════════════════════════
     MODAL RETRAIT
     ══════════════════════════════════════════════════════════ -->
<div id="withdraw-modal"
     class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-0 sm:p-4"
     style="display:none !important" aria-modal="true" role="dialog">

    <div id="withdraw-backdrop"
         class="absolute inset-0 bg-black/50 backdrop-blur-sm transition-opacity duration-300 opacity-0"></div>

    <div id="withdraw-panel"
         class="relative w-full sm:max-w-lg bg-white rounded-t-3xl sm:rounded-2xl shadow-2xl
                translate-y-full sm:translate-y-6 sm:scale-95 opacity-0
                transition-all duration-300 ease-out overflow-hidden">

        <div class="flex justify-center pt-3 pb-1 sm:hidden">
            <div class="w-10 h-1 rounded-full bg-slate-200"></div>
        </div>

        <!-- Header -->
        <div class="flex items-center justify-between px-6 pt-5 pb-4 border-b border-slate-100">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-gradient-to-br from-violet-500 to-purple-700 flex items-center justify-center shadow-sm">
                    <span class="material-symbols-outlined text-white text-xl">account_balance</span>
                </div>
                <div>
                    <h2 class="font-bold text-slate-800 text-base leading-tight">Retirer mes gains</h2>
                    <p class="text-xs text-slate-400">Commission plateforme : 5 %</p>
                </div>
            </div>
            <button onclick="closeWithdrawModal()"
                    class="w-8 h-8 rounded-full bg-slate-100 hover:bg-slate-200 flex items-center justify-center transition-colors">
                <span class="material-symbols-outlined text-slate-500 text-base">close</span>
            </button>
        </div>

        <div class="px-6 py-5 space-y-5 max-h-[65vh] overflow-y-auto">

            <!-- Solde dispo -->
            <div class="bg-gradient-to-r from-violet-500 to-purple-700 rounded-2xl p-4 flex items-center justify-between">
                <div>
                    <p class="text-violet-200 text-xs mb-0.5">Solde disponible</p>
                    <p class="text-white font-bold text-xl" id="wd-balance-display"><?= money((float)$wallet['balance']) ?></p>
                </div>
                <span class="material-symbols-outlined text-violet-300 text-3xl">account_balance_wallet</span>
            </div>

            <!-- Montant -->
            <div>
                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-widest mb-2">Montant à retirer</label>
                <div class="relative">
                    <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 font-bold text-sm">$</span>
                    <input id="wd-amount" type="number" min="20" step="0.01"
                           placeholder="e.g. 250.00"
                           class="w-full pl-14 pr-4 py-3.5 border-2 border-slate-200 rounded-xl text-slate-800 font-bold text-lg
                                  focus:outline-none focus:border-violet-400 focus:ring-4 focus:ring-violet-50
                                  transition-all placeholder:font-normal placeholder:text-slate-300 placeholder:text-base"
                           oninput="wdUpdateSummary()" />
                </div>
                <p class="text-xs text-slate-400 mt-1.5">Minimum : $20 USD</p>
            </div>

            <!-- Récap commission -->
            <div id="wd-summary" class="hidden rounded-2xl border-2 border-amber-200 bg-gradient-to-br from-amber-50 to-orange-50 p-4 space-y-2.5">
                <p class="text-xs font-bold text-amber-700 uppercase tracking-widest">Récapitulatif</p>
                <div class="flex justify-between text-sm">
                    <span class="text-slate-500">Montant demandé</span>
                    <span id="wd-s-gross" class="font-semibold text-slate-800">—</span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-slate-500">Commission 5 %</span>
                    <span id="wd-s-commission" class="font-semibold text-red-500">—</span>
                </div>
                <div class="border-t-2 border-amber-200 pt-2.5 flex justify-between items-center">
                    <span class="font-bold text-slate-700">Vous recevrez</span>
                    <span id="wd-s-net" class="font-black text-emerald-600 text-xl">—</span>
                </div>
            </div>

            <!-- Méthode de réception -->
            <div>
                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-widest mb-3">Recevoir sur</label>
                <div class="grid grid-cols-2 gap-3">
                    <label class="flex flex-col items-center gap-2 p-4 rounded-2xl border-2 border-slate-200 cursor-pointer
                                  hover:border-indigo-300 hover:bg-indigo-50/50 transition-all
                                  has-[:checked]:border-indigo-500 has-[:checked]:bg-indigo-50 has-[:checked]:shadow-sm">
                        <input type="radio" name="wd-method" value="bank" class="sr-only" onchange="wdSelectMethod('bank')" />
                        <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-indigo-400 to-blue-600 flex items-center justify-center">
                            <span class="material-symbols-outlined text-white text-lg">account_balance</span>
                        </div>
                        <span class="text-sm font-bold text-slate-700">Virement</span>
                        <span class="text-xs text-slate-400 text-center">Compte bancaire / IBAN</span>
                    </label>
                    <label class="flex flex-col items-center gap-2 p-4 rounded-2xl border-2 border-slate-200 cursor-pointer
                                  hover:border-orange-300 hover:bg-orange-50/50 transition-all
                                  has-[:checked]:border-orange-500 has-[:checked]:bg-orange-50 has-[:checked]:shadow-sm">
                        <input type="radio" name="wd-method" value="mobile_money" class="sr-only" onchange="wdSelectMethod('mobile_money')" />
                        <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-orange-400 to-yellow-500 flex items-center justify-center">
                            <span class="material-symbols-outlined text-white text-lg">smartphone</span>
                        </div>
                        <span class="text-sm font-bold text-slate-700">Mobile Money</span>
                        <span class="text-xs text-slate-400 text-center">Orange / MTN / Airtel</span>
                    </label>
                </div>
            </div>

            <!-- Champs virement bancaire -->
            <div id="wd-bank-fields" class="hidden space-y-3">
                <div>
                    <label class="block text-xs font-semibold text-slate-500 mb-1.5">Titulaire du compte</label>
                    <input id="wd-holder" type="text" placeholder="Prénom Nom"
                           class="w-full px-4 py-3 border-2 border-slate-200 rounded-xl text-slate-800
                                  focus:outline-none focus:border-indigo-400 focus:ring-4 focus:ring-indigo-50 transition-all" />
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-500 mb-1.5">Banque</label>
                    <input id="wd-bank" type="text" placeholder="ex : Ecobank, SGBCI, BNI…"
                           class="w-full px-4 py-3 border-2 border-slate-200 rounded-xl text-slate-800
                                  focus:outline-none focus:border-indigo-400 focus:ring-4 focus:ring-indigo-50 transition-all" />
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-500 mb-1.5">IBAN</label>
                    <input id="wd-iban" type="text" maxlength="34" placeholder="FR76 3000 6000 0112 3456 7890 189"
                           oninput="wdFormatIban(this)"
                           class="w-full px-4 py-3 border-2 border-slate-200 rounded-xl font-mono text-slate-800 tracking-wider
                                  focus:outline-none focus:border-indigo-400 focus:ring-4 focus:ring-indigo-50 transition-all" />
                    <p class="text-xs text-slate-400 mt-1">Format FR76 XXXX… ou CI…</p>
                </div>
            </div>

            <!-- Champs Mobile Money retrait -->
            <div id="wd-mobile-fields" class="hidden space-y-3">
                <div>
                    <label class="block text-xs font-semibold text-slate-500 mb-2">Opérateur</label>
                    <div class="grid grid-cols-3 gap-2">
                        <?php foreach ([['orange','Orange','from-orange-500 to-orange-600'],['mtn','MTN','from-yellow-400 to-yellow-500'],['airtel','Airtel','from-red-500 to-red-600']] as [$v,$l,$g]): ?>
                        <label class="flex flex-col items-center gap-1.5 p-3 rounded-xl border-2 border-slate-200 cursor-pointer
                                      hover:border-slate-300 transition-all has-[:checked]:border-orange-400 has-[:checked]:bg-orange-50">
                            <input type="radio" name="wd-operator" value="<?= $v ?>" class="sr-only" />
                            <div class="w-7 h-7 rounded-lg bg-gradient-to-br <?= $g ?> flex items-center justify-center">
                                <span class="material-symbols-outlined text-white text-sm">sim_card</span>
                            </div>
                            <span class="text-xs font-bold text-slate-600"><?= $l ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-500 mb-1.5">Numéro Mobile Money</label>
                    <div class="relative">
                        <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-sm">+243</span>
                        <input id="wd-phone" type="tel" maxlength="10" placeholder="07 00 00 00 00"
                               class="w-full pl-14 pr-4 py-3 border-2 border-slate-200 rounded-xl text-slate-800
                                      focus:outline-none focus:border-orange-400 focus:ring-4 focus:ring-orange-50 transition-all" />
                    </div>
                </div>
            </div>

            <!-- Erreur -->
            <div id="wd-error"
                 class="hidden rounded-xl bg-red-50 border-2 border-red-200 px-4 py-3 text-sm text-red-600 flex items-start gap-2">
                <span class="material-symbols-outlined text-base mt-0.5 flex-shrink-0">error</span>
                <span id="wd-error-text"></span>
            </div>

        </div>

        <!-- Footer -->
        <div class="px-6 pb-6 pt-3 border-t border-slate-100 flex gap-3">
            <button onclick="closeWithdrawModal()"
                    class="flex-1 py-3 rounded-xl border-2 border-slate-200 text-slate-500 text-sm font-semibold
                           hover:bg-slate-50 transition-colors active:scale-95">
                Annuler
            </button>
            <button id="wd-submit-btn" onclick="submitWithdraw()"
                    class="flex-grow py-3 px-6 rounded-xl bg-gradient-to-r from-violet-500 to-purple-700
                           text-white text-sm font-bold shadow-sm hover:opacity-90 transition-all active:scale-95
                           disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2">
                <span id="wd-btn-label">Confirmer le retrait</span>
                <span id="wd-btn-spinner" class="hidden">
                    <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                    </svg>
                </span>
            </button>
        </div>
    </div>
</div>

<!-- ══ Modal succès retrait ══════════════════════════════════ -->
<div id="withdraw-success-modal"
     class="fixed inset-0 z-50 flex items-center justify-center p-4"
     style="display:none !important">
    <div class="absolute inset-0 bg-black/50 backdrop-blur-sm"></div>
    <div id="wd-success-panel"
         class="relative bg-white rounded-2xl shadow-2xl p-8 max-w-sm w-full text-center
                scale-90 opacity-0 transition-all duration-300">
        <div class="w-20 h-20 rounded-full bg-gradient-to-br from-violet-400 to-purple-700 flex items-center justify-center mx-auto mb-5 shadow-lg">
            <span class="material-symbols-outlined text-white text-4xl">check_circle</span>
        </div>
        <h3 class="text-2xl font-bold text-slate-800 mb-1">Retrait enregistré !</h3>
        <p class="text-slate-400 text-sm mb-5">Votre demande est en cours de traitement.</p>
        <div class="bg-slate-50 rounded-2xl p-5 text-left space-y-3 mb-6">
            <div class="flex justify-between text-sm">
                <span class="text-slate-500">Montant demandé</span>
                <span id="wd-res-gross" class="font-semibold text-slate-800">—</span>
            </div>
            <div class="flex justify-between text-sm">
                <span class="text-slate-500">Commission 5 %</span>
                <span id="wd-res-commission" class="font-semibold text-red-500">—</span>
            </div>
            <div class="border-t border-slate-200 pt-3 flex justify-between items-center">
                <span class="font-bold text-slate-700">Vous recevrez</span>
                <span id="wd-res-net" class="font-black text-emerald-600 text-xl">—</span>
            </div>
        </div>
        <button onclick="closeWdSuccessModal()"
                class="w-full py-3.5 rounded-xl bg-gradient-to-r from-violet-500 to-purple-700
                       text-white font-bold text-sm hover:opacity-90 transition-all active:scale-95">
            Fermer
        </button>
    </div>
</div>

<script>
// ══════════════════════════════════════════════════════════
// RETRAIT — JavaScript
// ══════════════════════════════════════════════════════════
const WD_BALANCE    = <?= (float)$wallet['balance'] ?>;
const WD_COMMISSION = 0.05;
const wdFmt = v => new Intl.NumberFormat('en-US',{style:'currency',currency:'USD'}).format(v);
let wdCurrentMethod = null;

function openWithdrawModal() {
    const modal = document.getElementById('withdraw-modal');
    const panel = document.getElementById('withdraw-panel');
    const bd    = document.getElementById('withdraw-backdrop');

    document.getElementById('wd-amount').value = '';
    document.getElementById('wd-summary').classList.add('hidden');
    document.getElementById('wd-bank-fields').classList.add('hidden');
    document.getElementById('wd-mobile-fields').classList.add('hidden');
    document.getElementById('wd-error').classList.add('hidden');
    document.querySelectorAll('[name="wd-method"]').forEach(r => r.checked = false);
    wdCurrentMethod = null;
    wdSetBtnState(false);

    modal.style.removeProperty('display');
    requestAnimationFrame(() => {
        bd.classList.add('opacity-100');
        panel.classList.remove('translate-y-full','sm:translate-y-6','sm:scale-95','opacity-0');
    });
    document.body.style.overflow = 'hidden';
}

function closeWithdrawModal() {
    const panel = document.getElementById('withdraw-panel');
    const bd    = document.getElementById('withdraw-backdrop');
    bd.classList.remove('opacity-100');
    panel.classList.add('translate-y-full','sm:translate-y-6','sm:scale-95','opacity-0');
    setTimeout(() => {
        document.getElementById('withdraw-modal').style.display = 'none';
        document.body.style.overflow = '';
    }, 300);
}

document.getElementById('withdraw-backdrop')?.addEventListener('click', closeWithdrawModal);

function wdSelectMethod(method) {
    wdCurrentMethod = method;
    document.getElementById('wd-bank-fields').classList.toggle('hidden', method !== 'bank');
    document.getElementById('wd-mobile-fields').classList.toggle('hidden', method !== 'mobile_money');
    document.getElementById('wd-error').classList.add('hidden');
}

function wdFormatIban(input) {
    let v = input.value.replace(/\s+/g,'').toUpperCase();
    input.value = v.match(/.{1,4}/g)?.join(' ') ?? v;
}

function wdUpdateSummary() {
    const amount  = parseFloat(document.getElementById('wd-amount').value) || 0;
    const summary = document.getElementById('wd-summary');
    if (amount >= 1000) {
        const commission = Math.round(amount * WD_COMMISSION * 100) / 100;
        const net        = Math.round((amount - commission) * 100) / 100;
        document.getElementById('wd-s-gross').textContent      = wdFmt(amount);
        document.getElementById('wd-s-commission').textContent = '− ' + wdFmt(commission);
        document.getElementById('wd-s-net').textContent        = wdFmt(net);
        summary.classList.remove('hidden');
        const input = document.getElementById('wd-amount');
        input.classList.toggle('border-red-400', amount > WD_BALANCE);
        input.classList.toggle('border-slate-200', amount <= WD_BALANCE);
    } else {
        summary.classList.add('hidden');
    }
}

function wdSetBtnState(loading) {
    const btn = document.getElementById('wd-submit-btn');
    btn.disabled = loading;
    document.getElementById('wd-btn-label').textContent = loading ? 'Traitement…' : 'Confirmer le retrait';
    document.getElementById('wd-btn-spinner').classList.toggle('hidden', !loading);
}

function wdShowError(msg) {
    const box = document.getElementById('wd-error');
    document.getElementById('wd-error-text').textContent = msg;
    box.classList.remove('hidden');
    box.scrollIntoView({ behavior:'smooth', block:'nearest' });
}
function wdClearError() { document.getElementById('wd-error').classList.add('hidden'); }

async function submitWithdraw() {
    wdClearError();
    const amount = parseFloat(document.getElementById('wd-amount').value) || 0;

    if (amount < 20)       return wdShowError('Minimum : $20 USD.');
    if (amount > WD_BALANCE) return wdShowError('Solde insuffisant. Disponible : ' + wdFmt(WD_BALANCE) + '.');
    if (!wdCurrentMethod)    return wdShowError('Veuillez choisir un mode de réception.');

    if (wdCurrentMethod === 'bank') {
        const holder = document.getElementById('wd-holder').value.trim();
        const bank   = document.getElementById('wd-bank').value.trim();
        const iban   = document.getElementById('wd-iban').value.replace(/\s/g,'');
        if (!holder) return wdShowError('Veuillez saisir le nom du titulaire.');
        if (!bank)   return wdShowError('Veuillez saisir le nom de votre banque.');
        if (iban.length < 14) return wdShowError('IBAN invalide (minimum 14 caractères).');
    }

    if (wdCurrentMethod === 'mobile_money') {
        const operator = document.querySelector('[name="wd-operator"]:checked');
        const phone    = document.getElementById('wd-phone').value.replace(/\D/g,'');
        if (!operator)    return wdShowError('Veuillez sélectionner un opérateur.');
        if (phone.length < 8) return wdShowError('Numéro de téléphone invalide.');
    }

    wdSetBtnState(true);
    try {
        const form = new FormData();
        form.append('amount', amount);
        form.append('method', wdCurrentMethod);
        if (wdCurrentMethod === 'bank') {
            form.append('account_holder', document.getElementById('wd-holder').value.trim());
            form.append('bank_name',      document.getElementById('wd-bank').value.trim());
            form.append('iban',           document.getElementById('wd-iban').value.replace(/\s/g,''));
        }
        if (wdCurrentMethod === 'mobile_money') {
            form.append('operator', document.querySelector('[name="wd-operator"]:checked')?.value ?? '');
            form.append('phone',    document.getElementById('wd-phone').value);
        }

        const res  = await fetch('/upc_freelance/app/wallet/withdraw.php', { method:'POST', body:form });
        const data = await res.json();

        if (data.success) {
            closeWithdrawModal();
            openWdSuccessModal(data.amount, data.commission, data.net_amount);
            document.getElementById('wd-balance-display').textContent = wdFmt(data.new_balance);
        } else {
            wdShowError(data.message || 'Une erreur est survenue.');
        }
    } catch(e) {
        wdShowError('Impossible de contacter le serveur.');
    } finally {
        wdSetBtnState(false);
    }
}

function openWdSuccessModal(gross, commission, net) {
    document.getElementById('wd-res-gross').textContent      = wdFmt(gross);
    document.getElementById('wd-res-commission').textContent = '− ' + wdFmt(commission);
    document.getElementById('wd-res-net').textContent        = wdFmt(net);
    const modal = document.getElementById('withdraw-success-modal');
    const panel = document.getElementById('wd-success-panel');
    modal.style.removeProperty('display');
    requestAnimationFrame(() => panel.classList.remove('scale-90','opacity-0'));
    document.body.style.overflow = 'hidden';
}
function closeWdSuccessModal() {
    const panel = document.getElementById('wd-success-panel');
    panel.classList.add('scale-90','opacity-0');
    setTimeout(() => {
        document.getElementById('withdraw-success-modal').style.display = 'none';
        document.body.style.overflow = '';
        window.location.reload();
    }, 300);
}
</script>