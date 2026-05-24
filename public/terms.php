<?php
// ============================================================
// UPC FREELANCE — Conditions d'utilisation
// /var/www/html/upc_freelance/public/terms.php
// ============================================================

require_once '/var/www/html/upc_freelance/includes/middleware.php';
require_once '/var/www/html/upc_freelance/includes/auth.php';
require_once '/var/www/html/upc_freelance/includes/functions.php';

$pageTitle = "Conditions d'utilisation — UPC Freelance";
require_once '/var/www/html/upc_freelance/includes/header.php';
?>

<section class="py-20 bg-white">
    <div class="max-w-3xl mx-auto px-8">
        <h1 class="font-h1 text-h1 text-primary mb-4">Conditions d'utilisation</h1>
        <p class="text-on-surface-variant mb-10">Dernière mise à jour : <?= date('d/m/Y') ?></p>

        <?php
        $sections = [
            ['title'=>'1. Acceptation des conditions',
             'content'=>'En accédant à la plateforme UPC Freelance, vous acceptez d\'être lié par les présentes conditions d\'utilisation. Si vous n\'acceptez pas ces conditions, vous ne pouvez pas utiliser nos services.'],
            ['title'=>'2. Description du service',
             'content'=>'UPC Freelance est une plateforme de mise en relation entre étudiants freelances et clients cherchant des services numériques. Nous facilitons la rencontre, le contrat et le paiement, mais ne sommes pas parties aux accords conclus entre utilisateurs.'],
            ['title'=>'3. Inscription et comptes',
             'content'=>'Pour utiliser la plateforme, vous devez créer un compte avec des informations exactes et complètes. Vous êtes responsable de la confidentialité de vos identifiants. Toute activité sur votre compte vous engage. Vous devez être âgé d\'au moins 16 ans pour vous inscrire.'],
            ['title'=>'4. Rôles et responsabilités',
             'content'=>'Les clients s\'engagent à fournir des informations exactes sur leurs projets, à payer les freelancers selon les termes convenus, et à ne pas utiliser la plateforme à des fins frauduleuses. Les freelancers s\'engagent à fournir des services de qualité, dans les délais convenus et à ne pas proposer de travail contraire aux lois en vigueur.'],
            ['title'=>'5. Système de paiement (Wallet)',
             'content'=>'La plateforme utilise un système de wallet interne. Les fonds déposés sont sécurisés et ne peuvent être utilisés qu\'au sein de la plateforme. Les retraits sont traités dans un délai de 24 à 48 heures ouvrables. UPC Freelance peut prélever une commission sur les transactions.'],
            ['title'=>'6. Propriété intellectuelle',
             'content'=>'Les livrables produits dans le cadre d\'un contrat appartiennent au client après paiement complet, sauf accord contraire stipulé dans le contrat. Le freelancer conserve le droit de mentionner le projet dans son portfolio.'],
            ['title'=>'7. Confidentialité',
             'content'=>'Les informations personnelles collectées sont utilisées uniquement pour faire fonctionner la plateforme. Elles ne sont pas vendues à des tiers. Pour plus de détails, consultez notre Politique de confidentialité.'],
            ['title'=>'8. Résolution des litiges',
             'content'=>'En cas de litige entre un client et un freelancer, UPC Freelance propose une médiation. L\'équipe examinera les preuves soumises et rendra une décision qui s\'impose aux deux parties. En cas de fraude avérée, le compte concerné sera suspendu.'],
            ['title'=>'9. Modifications',
             'content'=>'UPC Freelance se réserve le droit de modifier ces conditions à tout moment. Les utilisateurs seront notifiés par email ou notification sur la plateforme. L\'utilisation continue des services après modification vaut acceptation des nouvelles conditions.'],
            ['title'=>'10. Contact',
             'content'=>'Pour toute question concernant ces conditions, contactez-nous à : legal@upcfreelance.com'],
        ];
        foreach ($sections as $s):
        ?>
        <div class="mb-10">
            <h2 class="text-xl font-bold text-primary mb-3"><?= h($s['title']) ?></h2>
            <p class="text-on-surface-variant leading-relaxed"><?= h($s['content']) ?></p>
        </div>
        <?php endforeach; ?>

        <div class="mt-12 p-6 bg-surface-container-low rounded-2xl border border-slate-100">
            <p class="text-sm text-on-surface-variant">
                En utilisant UPC Freelance, vous confirmez avoir lu et accepté ces conditions d'utilisation.
                Pour toute question, contactez-nous via <a href="/upc_freelance/public/contact.php" class="text-secondary hover:underline">notre page de contact</a>.
            </p>
        </div>
    </div>
</section>

<?php require_once '/var/www/html/upc_freelance/includes/footer.php'; ?>
