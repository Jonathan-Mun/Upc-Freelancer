<?php
/**
 * Page 404 — UPC Freelance
 * À placer à la racine : /404.php
 *
 * UTILISATION :
 *   // Depuis n'importe quel fichier PHP du projet :
 *   http_response_code(404);
 *   require_once $_SERVER['DOCUMENT_ROOT'] . '/upc_freelance/404.php';
 *   exit;
 */

http_response_code(404);

// Déterminer si l'utilisateur est connecté pour afficher la bonne nav
$isLoggedIn = isset($_SESSION['user_id']);

// BASE URL (adapté à ton projet)
$BASE = '/upc_freelance';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Page introuvable — UPC Freelance</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
        * { font-family: 'Inter', sans-serif; }

        /* Particules flottantes */
        .particle {
            position: absolute;
            border-radius: 50%;
            animation: float var(--dur, 6s) ease-in-out infinite;
            animation-delay: var(--delay, 0s);
            opacity: 0.12;
        }
        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50%       { transform: translateY(-20px) rotate(180deg); }
        }

        /* Chiffre 404 glitch */
        .glitch {
            position: relative;
            animation: glitch-skew 4s infinite linear alternate-reverse;
        }
        .glitch::before,
        .glitch::after {
            content: '404';
            position: absolute;
            inset: 0;
            opacity: 0.06;
        }
        .glitch::before {
            color: #3b82f6;
            animation: glitch-anim 4s infinite linear alternate-reverse;
            clip-path: polygon(0 0, 100% 0, 100% 35%, 0 35%);
            transform: translate(-3px, 2px);
        }
        .glitch::after {
            color: #f59e0b;
            animation: glitch-anim2 4s infinite linear alternate-reverse;
            clip-path: polygon(0 65%, 100% 65%, 100% 100%, 0 100%);
            transform: translate(3px, -2px);
        }
        @keyframes glitch-anim {
            0%   { clip-path: polygon(0 0, 100% 0, 100% 20%, 0 20%); transform: translate(-3px, 2px); }
            25%  { clip-path: polygon(0 40%, 100% 40%, 100% 60%, 0 60%); transform: translate(3px, -2px); }
            50%  { clip-path: polygon(0 70%, 100% 70%, 100% 90%, 0 90%); transform: translate(-3px, 2px); }
            100% { clip-path: polygon(0 10%, 100% 10%, 100% 30%, 0 30%); transform: translate(3px, 0px); }
        }
        @keyframes glitch-anim2 {
            0%   { clip-path: polygon(0 60%, 100% 60%, 100% 80%, 0 80%); transform: translate(3px, -2px); }
            30%  { clip-path: polygon(0 10%, 100% 10%, 100% 40%, 0 40%); transform: translate(-3px, 2px); }
            60%  { clip-path: polygon(0 50%, 100% 50%, 100% 70%, 0 70%); transform: translate(3px, 0px); }
            100% { clip-path: polygon(0 80%, 100% 80%, 100% 100%, 0 100%); transform: translate(-3px, -2px); }
        }
        @keyframes glitch-skew {
            0%   { transform: skew(0deg); }
            20%  { transform: skew(-1deg); }
            40%  { transform: skew(0.5deg); }
            60%  { transform: skew(0deg); }
            100% { transform: skew(0.3deg); }
        }

        /* Pulse sur le point status */
        @keyframes ping-slow {
            0%, 100% { transform: scale(1); opacity: 1; }
            50%       { transform: scale(1.6); opacity: 0; }
        }
        .ping-slow { animation: ping-slow 2s ease-in-out infinite; }
    </style>
</head>
<body class="bg-slate-50 min-h-screen flex flex-col">

    <!-- ===== NAVBAR minimale ===== -->
    <nav class="bg-white border-b border-slate-100 px-6 lg:px-10 py-3.5 flex items-center justify-between">
        <a href="<?= $BASE ?>/index.php" class="flex items-center gap-2 select-none">
            <!-- Logo SVG inline (même que ton header) -->
            <svg width="28" height="28" viewBox="0 0 38 38" fill="none" xmlns="http://www.w3.org/2000/svg">
                <rect width="38" height="38" rx="10" fill="#002045"/>
                <path d="M10 12 L10 22 Q10 28 19 28 Q28 28 28 22 L28 12" stroke="#ffffff" stroke-width="2.5" stroke-linecap="round" fill="none"/>
                <circle cx="19" cy="12" r="1.5" fill="#66affe"/>
                <path d="M14 18 L24 18" stroke="#66affe" stroke-width="2" stroke-linecap="round"/>
            </svg>
            <span class="font-semibold text-slate-800 text-sm tracking-tight">UPC <span class="text-blue-600">Freelance</span></span>
        </a>

        <?php if ($isLoggedIn): ?>
            <a href="<?= $BASE ?>/app/dashboard.php"
               class="text-sm text-slate-500 hover:text-blue-600 transition-colors flex items-center gap-1.5">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                </svg>
                Tableau de bord
            </a>
        <?php else: ?>
            <a href="<?= $BASE ?>/public/login.php"
               class="text-sm bg-blue-600 text-white px-4 py-1.5 rounded-lg hover:bg-blue-700 transition-colors">
                Se connecter
            </a>
        <?php endif; ?>
    </nav>

    <!-- ===== CONTENU PRINCIPAL ===== -->
    <main class="flex-1 flex items-center justify-center px-6 py-20 relative overflow-hidden">

        <!-- Particules décoratives -->
        <div class="particle bg-blue-500 w-16 h-16 top-10 left-[8%]"  style="--dur:7s;  --delay:0s;"></div>
        <div class="particle bg-amber-400 w-8  h-8  top-24 right-[12%]" style="--dur:5s;  --delay:1s;"></div>
        <div class="particle bg-blue-400 w-10 h-10 bottom-20 left-[20%]" style="--dur:8s;  --delay:0.5s;"></div>
        <div class="particle bg-slate-400 w-6  h-6  bottom-32 right-[8%]" style="--dur:6s;  --delay:2s;"></div>
        <div class="particle bg-blue-300 w-12 h-12 top-1/2 left-[5%]"  style="--dur:9s;  --delay:1.5s;"></div>

        <div class="text-center max-w-lg relative z-10">

            <!-- Chiffre 404 -->
            <div class="glitch text-[9rem] sm:text-[11rem] font-black text-slate-800 leading-none select-none mb-2">
                404
            </div>

            <!-- Badge status -->
            <div class="inline-flex items-center gap-2 bg-amber-50 border border-amber-200 text-amber-700
                        text-xs font-medium px-3 py-1.5 rounded-full mb-6">
                <span class="relative flex h-2 w-2">
                    <span class="ping-slow absolute inline-flex h-full w-full rounded-full bg-amber-400"></span>
                    <span class="relative inline-flex rounded-full h-2 w-2 bg-amber-500"></span>
                </span>
                Page introuvable
            </div>

            <!-- Message -->
            <h1 class="text-2xl font-bold text-slate-800 mb-3">
                Cette page n'existe pas
            </h1>
            <p class="text-slate-500 text-sm leading-relaxed mb-10">
                La page que tu cherches a peut-être été déplacée, supprimée,<br class="hidden sm:block">
                ou l'URL saisie contient une erreur.
            </p>

            <!-- Suggestions de liens -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-10">
                <?php
                $links = $isLoggedIn ? [
                    ['href' => $BASE . '/app/dashboard.php',      'icon' => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6', 'label' => 'Tableau de bord'],
                    ['href' => $BASE . '/app/projects/list.php',   'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2', 'label' => 'Les projets'],
                    ['href' => $BASE . '/app/profile/edit.php',    'icon' => 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z', 'label' => 'Mon profil'],
                ] : [
                    ['href' => $BASE . '/index.php',               'icon' => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6', 'label' => 'Accueil'],
                    ['href' => $BASE . '/public/login.php',         'icon' => 'M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1', 'label' => 'Se connecter'],
                    ['href' => $BASE . '/public/register.php',      'icon' => 'M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z', 'label' => 'S\'inscrire'],
                ];
                foreach ($links as $l): ?>
                <a href="<?= $l['href'] ?>"
                   class="flex items-center gap-2.5 bg-white border border-slate-200 hover:border-blue-300
                          hover:shadow-sm text-slate-600 hover:text-blue-600 text-sm font-medium
                          px-4 py-3 rounded-xl transition-all group">
                    <svg class="w-4 h-4 shrink-0 text-slate-400 group-hover:text-blue-500 transition-colors"
                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?= $l['icon'] ?>"/>
                    </svg>
                    <?= $l['label'] ?>
                </a>
                <?php endforeach; ?>
            </div>

            <!-- Bouton retour -->
            <button onclick="history.back()"
                    class="inline-flex items-center gap-2 text-sm text-slate-400 hover:text-slate-600
                           transition-colors cursor-pointer">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                Retourner en arrière
            </button>
        </div>
    </main>

    <!-- ===== FOOTER minimal ===== -->
    <footer class="border-t border-slate-100 bg-white px-6 py-4 text-center text-xs text-slate-400">
        &copy; <?= date('Y') ?> <strong class="text-slate-500">UPC Freelance</strong> — Université Protestant du Congo
    </footer>

</body>
</html>