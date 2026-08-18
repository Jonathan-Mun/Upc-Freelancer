<?php
// ============================================================
// UPC FREELANCE — Footer global
// ============================================================
$BASE = $BASE ?? '/upc_freelance';
$appLayout = $appLayout ?? false;
?>

<?php if ($appLayout): ?>
    </main><!-- /main -->
</div><!-- /content wrapper -->
</body>
</html>

<?php else: ?>
</main>

<!-- Footer public -->
<footer class="bg-primary text-on-primary mt-20">
    <div class="max-w-screen-xl mx-auto px-8 py-16">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-8 mb-12">

            <!-- Logo & desc -->
            <div class="md:col-span-2">
                <div class="flex items-center gap-3 mb-4">
                    <svg width="34" height="34" viewBox="0 0 38 38" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <rect width="38" height="38" rx="10" fill="#1a365d"/>
                        <path d="M10 12 L10 22 Q10 28 19 28 Q28 28 28 22 L28 12" stroke="#ffffff" stroke-width="2.5" stroke-linecap="round" fill="none"/>
                        <circle cx="19" cy="12" r="1.5" fill="#66affe"/>
                        <path d="M14 18 L24 18" stroke="#66affe" stroke-width="2" stroke-linecap="round"/>
                        <path d="M32 8 L34 6 M32 8 L34 10 M32 8 L28 8" stroke="#66affe" stroke-width="1.5" stroke-linecap="round"/>
                    </svg>
                    <span class="text-xl font-bold text-white">UPC Freelance</span>
                </div>
                <p class="text-blue-200 text-sm leading-relaxed max-w-xs">
                    La plateforme de mise en relation entre étudiants freelances talentueux et clients à la recherche de compétences académiques.
                </p>
                <div class="flex gap-3 mt-6">
                    <a href="#" class="w-9 h-9 rounded-full bg-white/10 hover:bg-white/20 flex items-center justify-center transition-colors" aria-label="LinkedIn">
                        <span class="material-symbols-outlined text-sm text-blue-200">link</span>
                    </a>
                    <a href="#" class="w-9 h-9 rounded-full bg-white/10 hover:bg-white/20 flex items-center justify-center transition-colors" aria-label="Twitter">
                        <span class="material-symbols-outlined text-sm text-blue-200">chat</span>
                    </a>
                    <a href="#" class="w-9 h-9 rounded-full bg-white/10 hover:bg-white/20 flex items-center justify-center transition-colors" aria-label="Email">
                        <span class="material-symbols-outlined text-sm text-blue-200">mail</span>
                    </a>
                </div>
            </div>

            <!-- Liens plateforme -->
            <div>
                <h4 class="text-white font-semibold text-sm uppercase tracking-widest mb-4">Plateforme</h4>
                <ul class="space-y-3">
                    <li><a href="<?= $BASE ?>/public/how-it-works.php" class="text-blue-200 text-sm hover:text-white transition-colors">Comment ça marche</a></li>
                    <li><a href="<?= $BASE ?>/public/register.php"     class="text-blue-200 text-sm hover:text-white transition-colors">S'inscrire</a></li>
                    <li><a href="<?= $BASE ?>/public/login.php"        class="text-blue-200 text-sm hover:text-white transition-colors">Connexion</a></li>
                </ul>
            </div>

            <!-- Liens légaux -->
            <div>
                <h4 class="text-white font-semibold text-sm uppercase tracking-widest mb-4">Légal</h4>
                <ul class="space-y-3">
                    <li><a href="<?= $BASE ?>/public/terms.php"   class="text-blue-200 text-sm hover:text-white transition-colors">Conditions d'utilisation</a></li>
                    <li><a href="<?= $BASE ?>/public/contact.php" class="text-blue-200 text-sm hover:text-white transition-colors">Nous contacter</a></li>
                    <li><a href="<?= $BASE ?>/public/about.php"   class="text-blue-200 text-sm hover:text-white transition-colors">À propos</a></li>
                </ul>
            </div>
        </div>

        <div class="border-t border-white/10 pt-8 flex flex-col md:flex-row justify-between items-center gap-4">
            <p class="text-blue-300 text-sm">&copy; <?= date('Y') ?> UPC Freelance. Tous droits réservés.</p>
            <p class="text-blue-400 text-xs">Conçu pour les étudiants, par des étudiants.</p>
        </div>
    </div>
</footer>

</body>
</html>
<?php endif; ?>
