<?php
// ============================================================
// UPC FREELANCE — Comment ça marche
// /var/www/html/upc_freelance/public/how-it-works.php
// ============================================================

require_once '/var/www/html/upc_freelance/includes/middleware.php';
require_once '/var/www/html/upc_freelance/includes/auth.php';
require_once '/var/www/html/upc_freelance/includes/functions.php';

$pageTitle = 'Comment ça marche — UPC Freelance';
require_once '/var/www/html/upc_freelance/includes/header.php';
?>

<!-- Hero -->
<section class="py-20 bg-surface-container-low">
    <div class="max-w-4xl mx-auto px-8 text-center">
        <h1 class="font-h1 text-h1 text-primary mb-4">Comment ça marche ?</h1>
        <p class="text-on-surface-variant font-body-lg">Simple, rapide et sécurisé. Découvrez comment UPC Freelance connecte talents et projets.</p>
    </div>
</section>

<!-- Pour les clients -->
<section class="py-20 bg-white">
    <div class="max-w-5xl mx-auto px-8">
        <div class="flex items-center gap-3 mb-12">
            <div class="w-12 h-12 bg-secondary rounded-xl flex items-center justify-center">
                <span class="material-symbols-outlined text-white">business</span>
            </div>
            <h2 class="font-h2 text-h2 text-primary">Pour les clients</h2>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
            <?php
            $clientSteps = [
                ['num'=>'1','icon'=>'app_registration','title'=>'Créez votre compte', 'desc'=>'Inscrivez-vous gratuitement en tant que client en quelques minutes.'],
                ['num'=>'2','icon'=>'post_add',        'title'=>'Publiez votre projet','desc'=>'Décrivez votre besoin, définissez votre budget et vos délais.'],
                ['num'=>'3','icon'=>'group',           'title'=>'Choisissez un freelancer','desc'=>'Comparez les candidatures reçues et sélectionnez le meilleur profil.'],
                ['num'=>'4','icon'=>'task_alt',        'title'=>'Validez & payez','desc'=>'Suivez l\'avancement via le chat. Validez le travail pour libérer le paiement.'],
            ];
            foreach ($clientSteps as $s):
            ?>
            <div class="relative">
                <div class="bg-secondary/10 rounded-2xl p-6 h-full">
                    <div class="w-10 h-10 bg-secondary text-white rounded-xl flex items-center justify-center font-bold text-lg mb-4">
                        <?= $s['num'] ?>
                    </div>
                    <span class="material-symbols-outlined text-secondary text-3xl block mb-3"><?= $s['icon'] ?></span>
                    <h3 class="font-bold text-primary mb-2"><?= $s['title'] ?></h3>
                    <p class="text-sm text-on-surface-variant"><?= $s['desc'] ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Pour les freelancers -->
<section class="py-20 bg-surface-container-low">
    <div class="max-w-5xl mx-auto px-8">
        <div class="flex items-center gap-3 mb-12">
            <div class="w-12 h-12 bg-primary rounded-xl flex items-center justify-center">
                <span class="material-symbols-outlined text-white">person</span>
            </div>
            <h2 class="font-h2 text-h2 text-primary">Pour les freelancers</h2>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
            <?php
            $freelancerSteps = [
                ['num'=>'1','icon'=>'app_registration','title'=>'Créez votre profil',    'desc'=>'Inscrivez-vous en tant que freelancer et complétez votre profil avec vos compétences.'],
                ['num'=>'2','icon'=>'search',          'title'=>'Trouvez des projets',   'desc'=>'Parcourez la marketplace et trouvez les missions qui correspondent à vos compétences.'],
                ['num'=>'3','icon'=>'send',            'title'=>'Postulez',              'desc'=>'Envoyez votre candidature avec votre proposition de prix et un message de motivation.'],
                ['num'=>'4','icon'=>'payments',        'title'=>'Soyez payé',            'desc'=>'Réalisez le travail, livrez au client et recevez votre paiement sur votre wallet.'],
            ];
            foreach ($freelancerSteps as $s):
            ?>
            <div class="bg-white rounded-2xl p-6 custom-shadow-low">
                <div class="w-10 h-10 bg-primary text-white rounded-xl flex items-center justify-center font-bold text-lg mb-4"><?= $s['num'] ?></div>
                <span class="material-symbols-outlined text-primary text-3xl block mb-3"><?= $s['icon'] ?></span>
                <h3 class="font-bold text-primary mb-2"><?= $s['title'] ?></h3>
                <p class="text-sm text-on-surface-variant"><?= $s['desc'] ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Sécurité paiement -->
<section class="py-20 bg-white">
    <div class="max-w-4xl mx-auto px-8">
        <h2 class="font-h2 text-h2 text-primary text-center mb-4">Paiements 100% sécurisés</h2>
        <p class="text-center text-on-surface-variant mb-12">Notre système de wallet interne protège les deux parties.</p>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <?php foreach ([
                ['icon'=>'lock',          'color'=>'text-blue-500',  'bg'=>'bg-blue-50',  'title'=>'Dépôt sécurisé',       'desc'=>'Le client dépose les fonds sur son wallet avant le début du projet.'],
                ['icon'=>'account_balance','color'=>'text-amber-500','bg'=>'bg-amber-50', 'title'=>'Fonds bloqués',         'desc'=>'L\'argent est bloqué dans le système et inaccessible pendant le projet.'],
                ['icon'=>'verified',      'color'=>'text-green-500', 'bg'=>'bg-green-50', 'title'=>'Libération automatique','desc'=>'Dès validation du travail par le client, le paiement est transféré au freelancer.'],
            ] as $item): ?>
            <div class="text-center p-6 rounded-2xl border border-slate-100 custom-shadow-low">
                <div class="w-14 h-14 <?= $item['bg'] ?> rounded-2xl flex items-center justify-center mx-auto mb-4">
                    <span class="material-symbols-outlined <?= $item['color'] ?> text-2xl" style="font-variation-settings:'FILL' 1"><?= $item['icon'] ?></span>
                </div>
                <h3 class="font-bold text-primary mb-2"><?= $item['title'] ?></h3>
                <p class="text-sm text-on-surface-variant"><?= $item['desc'] ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- CTA -->
<section class="py-20 bg-primary">
    <div class="max-w-3xl mx-auto px-8 text-center">
        <h2 class="font-h2 text-h2 text-white mb-4">Prêt à commencer ?</h2>
        <div class="flex flex-wrap justify-center gap-4">
            <a href="/upc_freelance/public/register.php?role=client"
               class="bg-white text-primary font-button text-button px-8 py-4 rounded-xl hover:bg-blue-50 transition-colors active:scale-95">
                Je suis client
            </a>
            <a href="/upc_freelance/public/register.php?role=freelancer"
               class="bg-white/10 text-white border border-white/30 font-button text-button px-8 py-4 rounded-xl hover:bg-white/20 transition-colors active:scale-95">
                Je suis freelancer
            </a>
        </div>
    </div>
</section>

<?php require_once '/var/www/html/upc_freelance/includes/footer.php'; ?>
