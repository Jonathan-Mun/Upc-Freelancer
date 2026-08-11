<?php
// ============================================================
// UPC FREELANCE — À propos
// /var/www/html/upc_freelance/public/about.php
// ============================================================

require_once '../includes/middleware.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

$pageTitle = 'À propos — UPC Freelance';
require_once '../includes/header.php';
?>

<section class="py-20 bg-white">
    <div class="max-w-4xl mx-auto px-8">

        <div class="text-center mb-16">
            <h1 class="font-h1 text-h1 text-primary mb-4">À propos d'UPC Freelance</h1>
            <p class="text-on-surface-variant font-body-lg max-w-2xl mx-auto">
                Une plateforme créée par des étudiants, pour des étudiants — conçue pour transformer les compétences académiques en opportunités professionnelles réelles.
            </p>
        </div>

        <!-- Mission -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-16">
            <div class="bg-surface-container-low rounded-2xl p-8">
                <div class="w-12 h-12 bg-primary rounded-xl flex items-center justify-center mb-4">
                    <span class="material-symbols-outlined text-white">flag</span>
                </div>
                <h2 class="text-xl font-bold text-primary mb-3">Notre mission</h2>
                <p class="text-on-surface-variant leading-relaxed">
                    Connecter les étudiants ayant des compétences numériques avec des clients qui ont de vrais besoins, dans un environnement sécurisé, éducatif et professionnel.
                </p>
            </div>
            <div class="bg-surface-container-low rounded-2xl p-8">
                <div class="w-12 h-12 bg-secondary rounded-xl flex items-center justify-center mb-4">
                    <span class="material-symbols-outlined text-white">visibility</span>
                </div>
                <h2 class="text-xl font-bold text-primary mb-3">Notre vision</h2>
                <p class="text-on-surface-variant leading-relaxed">
                    Devenir la référence des plateformes freelance étudiantes en Afrique francophone, en valorisant les talents locaux et en favorisant l'entrepreneuriat étudiant.
                </p>
            </div>
        </div>

        <!-- Valeurs -->
        <h2 class="text-h2 font-h2 text-primary text-center mb-10">Nos valeurs</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-16">
            <?php
            $values = [
                ['icon'=>'security',    'title'=>'Sécurité',     'desc'=>'Paiements sécurisés via wallet interne, contrats encadrés et données protégées.'],
                ['icon'=>'school',      'title'=>'Excellence',   'desc'=>'Nous encourageons la montée en compétences et la qualité de travail des étudiants.'],
                ['icon'=>'handshake',   'title'=>'Confiance',    'desc'=>'Système de notation mutuelle et vérification d\'identité pour une communauté fiable.'],
            ];
            foreach ($values as $v):
            ?>
            <div class="text-center p-6 rounded-2xl border border-slate-100 bg-white custom-shadow-low">
                <div class="w-14 h-14 bg-surface-container-low rounded-2xl flex items-center justify-center mx-auto mb-4">
                    <span class="material-symbols-outlined text-secondary text-2xl"><?= $v['icon'] ?></span>
                </div>
                <h3 class="font-bold text-primary mb-2"><?= $v['title'] ?></h3>
                <p class="text-sm text-on-surface-variant"><?= $v['desc'] ?></p>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- CTA -->
        <div class="bg-primary rounded-2xl p-10 text-center text-white">
            <h2 class="text-2xl font-bold mb-3">Rejoignez la communauté</h2>
            <p class="text-blue-200 mb-6">Des milliers d'étudiants nous font déjà confiance.</p>
            <a href="/upc_freelance/public/register.php"
               class="inline-block bg-white text-primary font-button text-button px-8 py-3 rounded-xl hover:bg-blue-50 transition-colors active:scale-95">
                Créer mon compte
            </a>
        </div>
    </div>
</section>

<?php require_once '../includes/footer.php'; ?>
