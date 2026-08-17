<?php
// ============================================================
// UPC FREELANCE — Contact
// ============================================================

require_once '../includes/middleware.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

$sent = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $name    = sanitize($_POST['name']    ?? '');
    $email   = sanitize($_POST['email']   ?? '');
    $subject = sanitize($_POST['subject'] ?? '');
    $message = sanitize($_POST['message'] ?? '');
    // En prod : envoyer par email / enregistrer en BDD
    $sent = true;
}

$pageTitle = 'Contact — UPC Freelance';
require_once '../includes/header.php';
?>

<section class="py-20 bg-surface-container-low">
    <div class="max-w-4xl mx-auto px-8">
        <div class="text-center mb-14">
            <h1 class="font-h1 text-h1 text-primary mb-4">Contactez-nous</h1>
            <p class="text-on-surface-variant font-body-lg">Une question ? Un problème ? Nous sommes là pour vous aider.</p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

            <!-- Infos contact -->
            <div class="space-y-5">
                <?php foreach ([
                    ['icon'=>'mail',      'title'=>'Email',    'val'=>'support@upcfreelance.com'],
                    ['icon'=>'phone',     'title'=>'Téléphone','val'=>'+243 84 43 35 560'],
                    ['icon'=>'location_on','title'=>'Adresse', 'val'=>'Kinshasa, RDC'],
                ] as $info): ?>
                <div class="bg-white rounded-2xl border border-slate-100 p-5 custom-shadow-low flex items-start gap-4">
                    <div class="w-10 h-10 bg-surface-container-low rounded-xl flex items-center justify-center flex-shrink-0">
                        <span class="material-symbols-outlined text-secondary"><?= $info['icon'] ?></span>
                    </div>
                    <div>
                        <p class="text-xs text-on-surface-variant font-medium uppercase tracking-wide mb-1"><?= $info['title'] ?></p>
                        <p class="text-sm font-semibold text-primary"><?= h($info['val']) ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Formulaire -->
            <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-100 p-8 custom-shadow-low">
                <?php if ($sent): ?>
                <div class="text-center py-8">
                    <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <span class="material-symbols-outlined text-green-500 text-3xl">check_circle</span>
                    </div>
                    <h3 class="font-bold text-primary mb-2">Message envoyé !</h3>
                    <p class="text-sm text-on-surface-variant">Nous vous répondrons dans les 24-48h.</p>
                    <a href="/upc_freelance/public/contact.php" class="mt-4 inline-block text-sm text-secondary hover:underline">Envoyer un autre message</a>
                </div>
                <?php else: ?>
                <form method="POST" class="space-y-4">
                    <?= csrfField() ?>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-primary mb-1.5">Nom complet</label>
                            <input type="text" name="name" required placeholder="Jonathan Mundayi"
                                   class="w-full px-4 py-3 rounded-xl border border-outline-variant focus:border-secondary focus:ring-2 focus:ring-secondary/20 outline-none text-sm"/>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-primary mb-1.5">Email</label>
                            <input type="email" name="email" required placeholder="vous@exemple.com"
                                   class="w-full px-4 py-3 rounded-xl border border-outline-variant focus:border-secondary focus:ring-2 focus:ring-secondary/20 outline-none text-sm"/>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-primary mb-1.5">Sujet</label>
                        <select name="subject" class="w-full px-4 py-3 rounded-xl border border-outline-variant focus:border-secondary outline-none text-sm">
                            <option value="general">Question générale</option>
                            <option value="payment">Problème de paiement</option>
                            <option value="account">Problème de compte</option>
                            <option value="dispute">Litige / signalement</option>
                            <option value="other">Autre</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-primary mb-1.5">Message</label>
                        <textarea name="message" rows="5" required placeholder="Décrivez votre demande en détail..."
                                  class="w-full px-4 py-3 rounded-xl border border-outline-variant focus:border-secondary focus:ring-2 focus:ring-secondary/20 outline-none text-sm resize-y"></textarea>
                    </div>
                    <button type="submit"
                            class="w-full bg-primary text-white font-button text-button py-3.5 rounded-xl hover:opacity-90 transition-opacity active:scale-95 shadow-sm">
                        <span class="material-symbols-outlined align-middle mr-1">send</span>
                        Envoyer le message
                    </button>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<?php require_once '../includes/footer.php'; ?>
