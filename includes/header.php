<?php
// ============================================================
// UPC FREELANCE — Header global
// /var/www/html/upc_freelance/includes/header.php
// ============================================================

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';

$user = currentUser();
$notifCount   = $user ? countUnreadNotifications($user['id']) : 0;
$msgCount     = $user ? countUnreadMessages($user['id'])      : 0;
$wallet       = $user ? getUserWallet($user['id'])            : null;

$BASE = '/upc_freelance';
?>
<!DOCTYPE html>
<html lang="fr" class="light">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<meta name="description" content="UPC Freelance — La plateforme de mise en relation entre étudiants freelances et clients."/>
<title><?= h($pageTitle ?? 'UPC Freelance') ?></title>

<!-- Tailwind CSS -->
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>

<!-- Google Fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com"/>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Work+Sans:wght@500;600&display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>

<script id="tailwind-config">
tailwind.config = {
    darkMode: "class",
    theme: {
        extend: {
            colors: {
                "primary":                   "#002045",
                "primary-container":         "#1a365d",
                "on-primary":                "#ffffff",
                "on-primary-container":      "#86a0cd",
                "secondary":                 "#0061a5",
                "secondary-container":       "#66affe",
                "on-secondary":              "#ffffff",
                "on-secondary-container":    "#004172",
                "tertiary":                  "#1b2127",
                "tertiary-container":        "#30363c",
                "on-tertiary":               "#ffffff",
                "surface":                   "#f8f9ff",
                "surface-bright":            "#f8f9ff",
                "surface-dim":               "#ccdbf4",
                "surface-variant":           "#d4e4fc",
                "surface-container-lowest":  "#ffffff",
                "surface-container-low":     "#eff4ff",
                "surface-container":         "#e5eeff",
                "surface-container-high":    "#dce9ff",
                "surface-container-highest": "#d4e4fc",
                "on-surface":                "#0d1c2e",
                "on-surface-variant":        "#43474e",
                "on-background":             "#0d1c2e",
                "background":                "#f8f9ff",
                "outline":                   "#74777f",
                "outline-variant":           "#c4c6cf",
                "error":                     "#ba1a1a",
                "error-container":           "#ffdad6",
                "on-error":                  "#ffffff",
                "on-error-container":        "#93000a",
                "inverse-surface":           "#223144",
                "inverse-on-surface":        "#eaf1ff",
                "inverse-primary":           "#adc7f7",
                "primary-fixed":             "#d6e3ff",
                "primary-fixed-dim":         "#adc7f7",
                "secondary-fixed":           "#d2e4ff",
                "secondary-fixed-dim":       "#9fcaff",
                "surface-tint":              "#455f88",
            },
            borderRadius: {
                DEFAULT: "0.25rem",
                lg:      "0.5rem",
                xl:      "0.75rem",
                "2xl":   "1rem",
                full:    "9999px",
            },
            spacing: {
                xl:        "4rem",
                base:      "4px",
                sm:        "1rem",
                margin:    "32px",
                lg:        "2.5rem",
                gutter:    "24px",
                xs:        "0.5rem",
                max_width: "1280px",
                md:        "1.5rem",
            },
            fontFamily: {
                "body-sm":    ["Inter"],
                "body-md":    ["Inter"],
                "body-lg":    ["Inter"],
                "button":     ["Work Sans"],
                "h1":         ["Inter"],
                "h2":         ["Inter"],
                "h3":         ["Inter"],
                "label-caps": ["Work Sans"],
            },
            fontSize: {
                "body-sm":    ["14px", {lineHeight:"1.5",  letterSpacing:"0",      fontWeight:"400"}],
                "body-md":    ["16px", {lineHeight:"1.6",  letterSpacing:"0",      fontWeight:"400"}],
                "body-lg":    ["18px", {lineHeight:"1.6",  letterSpacing:"0",      fontWeight:"400"}],
                "button":     ["15px", {lineHeight:"1",    letterSpacing:"0.01em", fontWeight:"500"}],
                "h1":         ["40px", {lineHeight:"1.2",  letterSpacing:"-0.02em",fontWeight:"700"}],
                "h2":         ["30px", {lineHeight:"1.3",  letterSpacing:"-0.01em",fontWeight:"600"}],
                "h3":         ["24px", {lineHeight:"1.4",  letterSpacing:"0",      fontWeight:"600"}],
                "label-caps": ["12px", {lineHeight:"1",    letterSpacing:"0.05em", fontWeight:"600"}],
            },
        },
    },
}
</script>

<style>
.material-symbols-outlined {
    font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
    vertical-align: middle;
}
.custom-shadow-low  { box-shadow: 0 4px 12px rgba(26,54,93,.05); }
.custom-shadow-high { box-shadow: 0 8px 24px rgba(26,54,93,.12); }
.nav-active {
    background: #eff4ff;
    color: #0061a5;
    border-right: 3px solid #0061a5;
    font-weight: 600;
}
.sidebar-link {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.625rem 0.75rem;
    border-radius: 0.5rem;
    color: #43474e;
    font-size: 14px;
    transition: all 0.15s;
}
.sidebar-link:hover { background:#f1f5f9; transform: translateX(2px); }
</style>
</head>

<?php if (isset($appLayout) && $appLayout): ?>
<!-- ════════════════════════════════════════════════
     LAYOUT APP (utilisateur connecté) — sidebar
     ════════════════════════════════════════════════ -->
<body class="bg-background text-on-surface font-body-md antialiased min-h-screen flex">

<!-- Sidebar -->
<aside class="hidden lg:flex flex-col w-64 bg-white border-r border-slate-200 sticky top-0 h-screen p-4 z-40">

    <!-- Logo -->
    <a href="<?= $BASE ?>/app/dashboard.php" class="flex items-center gap-3 mb-8 px-2 group">
        <!-- SVG Logo UPC Freelance -->
        <svg width="38" height="38" viewBox="0 0 38 38" fill="none" xmlns="http://www.w3.org/2000/svg" class="flex-shrink-0">
            <rect width="38" height="38" rx="10" fill="#002045"/>
            <path d="M10 12 L10 22 Q10 28 19 28 Q28 28 28 22 L28 12" stroke="#ffffff" stroke-width="2.5" stroke-linecap="round" fill="none"/>
            <circle cx="19" cy="12" r="1.5" fill="#66affe"/>
            <path d="M14 18 L24 18" stroke="#66affe" stroke-width="2" stroke-linecap="round"/>
            <path d="M32 8 L34 6 M32 8 L34 10 M32 8 L28 8" stroke="#66affe" stroke-width="1.5" stroke-linecap="round"/>
        </svg>
        <div>
            <span class="block text-base font-bold text-primary leading-tight">UPC Freelance</span>
            <span class="block text-[10px] text-slate-400 font-label-caps uppercase tracking-widest">Plateforme Étudiante</span>
        </div>
    </a>

    <!-- Navigation -->
    <nav class="flex-1 space-y-1">
        <?php
        $currentFile = basename($_SERVER['PHP_SELF']);
        $currentDir  = basename(dirname($_SERVER['PHP_SELF']));
        $navItems = [
            ['icon'=>'dashboard',           'label'=>'Tableau de bord', 'href'=>$BASE.'/app/dashboard.php',                'dir'=>'app',          'file'=>'dashboard.php'],
            ['icon'=>'search',              'label'=>'Trouver un projet','href'=>$BASE.'/app/projects/list.php',           'dir'=>'projects',     'file'=>'list.php'],
            ['icon'=>'add_circle',          'label'=>'Créer un projet', 'href'=>$BASE.'/app/projects/create.php',          'dir'=>'projects',     'file'=>'create.php'],
            ['icon'=>'folder_open',         'label'=>'Mes projets',     'href'=>$BASE.'/app/projects/my-projects.php',     'dir'=>'projects',     'file'=>'my-projects.php'],
            ['icon'=>'send',                'label'=>'Candidatures',    'href'=>$BASE.'/app/postulations/my-applications.php','dir'=>'postulations','file'=>'my-applications.php'],
            ['icon'=>'description',         'label'=>'Contrats',        'href'=>$BASE.'/app/contracts/list.php',           'dir'=>'contracts',    'file'=>'list.php'],
            ['icon'=>'chat',                'label'=>'Messages',        'href'=>$BASE.'/app/messages/inbox.php',           'dir'=>'messages',     'file'=>'inbox.php'],
            ['icon'=>'account_balance_wallet','label'=>'Wallet',        'href'=>$BASE.'/app/wallet/index.php',             'dir'=>'wallet',       'file'=>'index.php'],
            ['icon'=>'notifications',       'label'=>'Notifications',   'href'=>$BASE.'/app/notifications/index.php',      'dir'=>'notifications','file'=>'index.php'],
            ['icon'=>'person',              'label'=>'Mon profil',      'href'=>$BASE.'/app/profile/edit.php',             'dir'=>'profile',      'file'=>'edit.php'],
        ];
        foreach ($navItems as $nav):
            $isActive = ($currentDir === $nav['dir'] && $currentFile === $nav['file'])
                     || ($currentFile === $nav['file'] && $currentDir === 'app');
            $class = $isActive ? 'sidebar-link nav-active' : 'sidebar-link';
        ?>
        <a href="<?= $nav['href'] ?>" class="<?= $class ?>">
            <span class="material-symbols-outlined text-xl <?= $isActive ? 'text-secondary' : 'text-slate-400' ?>">
                <?= $nav['icon'] ?>
            </span>
            <span><?= $nav['label'] ?></span>
            <?php if ($nav['icon'] === 'notifications' && $notifCount > 0): ?>
            <span class="ml-auto bg-red-500 text-white text-[10px] font-bold rounded-full w-5 h-5 flex items-center justify-center">
                <?= $notifCount > 9 ? '9+' : $notifCount ?>
            </span>
            <?php endif; ?>
            <?php if ($nav['icon'] === 'chat' && $msgCount > 0): ?>
            <span class="ml-auto bg-secondary text-white text-[10px] font-bold rounded-full w-5 h-5 flex items-center justify-center">
                <?= $msgCount > 9 ? '9+' : $msgCount ?>
            </span>
            <?php endif; ?>
        </a>
        <?php endforeach; ?>
    </nav>

    <!-- User & Logout -->
    <div class="mt-auto pt-4 border-t border-slate-100">
        <?php if ($wallet): ?>
        <div class="mb-3 px-2 py-2 bg-surface-container-low rounded-lg">
            <p class="text-[10px] text-slate-400 font-label-caps uppercase">Mon wallet</p>
            <p class="text-base font-bold text-primary"><?= money((float)$wallet['balance']) ?></p>
        </div>
        <?php endif; ?>
        <div class="flex items-center gap-2 px-2 mb-3">
            <?php if ($user && $user['avatar']): ?>
            <img src="<?= $BASE ?>/storage/<?= h($user['avatar']) ?>" alt="Avatar" class="w-8 h-8 rounded-full object-cover border border-slate-200"/>
            <?php else: ?>
            <div class="w-8 h-8 rounded-full bg-primary-container flex items-center justify-center text-white text-sm font-bold">
                <?= mb_substr($user['first_name'] ?? 'U', 0, 1) ?>
            </div>
            <?php endif; ?>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-semibold text-primary truncate"><?= h(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?></p>
                <p class="text-[10px] text-slate-400 capitalize"><?= h($user['role'] ?? '') ?></p>
            </div>
        </div>
        <a href="<?= $BASE ?>/auth/logout.php" class="flex items-center gap-2 px-3 py-2 rounded-lg text-red-500 hover:bg-red-50 transition-colors text-sm">
            <span class="material-symbols-outlined text-base">logout</span>
            Se déconnecter
        </a>
    </div>
</aside>

<!-- Main content wrapper -->
<div class="flex-1 flex flex-col min-w-0">
    <!-- Top bar mobile -->
    <header class="lg:hidden fixed top-0 left-0 right-0 z-50 bg-white border-b border-slate-200 h-14 flex items-center justify-between px-4">
        <button id="mob-menu-btn" class="p-1">
            <span class="material-symbols-outlined">menu</span>
        </button>
        <svg width="24" height="24" viewBox="0 0 38 38" fill="none" xmlns="http://www.w3.org/2000/svg">
            <rect width="38" height="38" rx="10" fill="#002045"/>
            <path d="M10 12 L10 22 Q10 28 19 28 Q28 28 28 22 L28 12" stroke="#fff" stroke-width="2.5" stroke-linecap="round" fill="none"/>
            <circle cx="19" cy="12" r="1.5" fill="#66affe"/>
            <path d="M14 18 L24 18" stroke="#66affe" stroke-width="2" stroke-linecap="round"/>
        </svg>
        <a href="<?= $BASE ?>/app/notifications/index.php" class="relative p-1">
            <span class="material-symbols-outlined text-slate-600">notifications</span>
            <?php if ($notifCount > 0): ?>
            <span class="absolute top-0 right-0 bg-red-500 text-white text-[9px] rounded-full w-4 h-4 flex items-center justify-center"><?= $notifCount ?></span>
            <?php endif; ?>
        </a>
    </header>
    <main class="flex-1 lg:pt-0 pt-14 p-6 lg:p-8">

<?php else: ?>
<!-- ════════════════════════════════════════════════
     LAYOUT PUBLIC — topbar
     ════════════════════════════════════════════════ -->
<body class="bg-background text-on-surface font-body-md antialiased">

<header class="fixed top-0 w-full z-50 bg-white/95 backdrop-blur-sm border-b border-slate-200 shadow-sm">
    <div class="flex justify-between items-center px-6 h-16 max-w-screen-xl mx-auto">

        <!-- Logo -->
        <a href="<?= $BASE ?>/public/index.php" class="flex items-center gap-3">
            <svg width="34" height="34" viewBox="0 0 38 38" fill="none" xmlns="http://www.w3.org/2000/svg">
                <rect width="38" height="38" rx="10" fill="#002045"/>
                <path d="M10 12 L10 22 Q10 28 19 28 Q28 28 28 22 L28 12" stroke="#ffffff" stroke-width="2.5" stroke-linecap="round" fill="none"/>
                <circle cx="19" cy="12" r="1.5" fill="#66affe"/>
                <path d="M14 18 L24 18" stroke="#66affe" stroke-width="2" stroke-linecap="round"/>
                <path d="M32 8 L34 6 M32 8 L34 10 M32 8 L28 8" stroke="#66affe" stroke-width="1.5" stroke-linecap="round"/>
            </svg>
            <span class="text-lg font-bold text-primary tracking-tight">UPC Freelance</span>
        </a>

        <!-- Nav desktop -->
        <nav class="hidden md:flex items-center gap-6 text-sm font-medium">
            <a href="<?= $BASE ?>/public/index.php"         class="text-slate-600 hover:text-secondary transition-colors">Accueil</a>
            <a href="<?= $BASE ?>/public/how-it-works.php"  class="text-slate-600 hover:text-secondary transition-colors">Comment ça marche</a>
            <a href="<?= $BASE ?>/app/projects/list.php"    class="text-slate-600 hover:text-secondary transition-colors">Projets</a>
            <a href="<?= $BASE ?>/public/contact.php"       class="text-slate-600 hover:text-secondary transition-colors">Contact</a>
        </nav>

        <!-- Auth buttons -->
        <div class="flex items-center gap-3">
            <?php if (isLoggedIn()): ?>
            <a href="<?= $BASE ?>/app/dashboard.php" class="bg-primary text-on-primary font-button text-button px-4 py-2 rounded-lg hover:opacity-90 transition-opacity active:scale-95">
                Mon espace
            </a>
            <?php else: ?>
            <a href="<?= $BASE ?>/public/login.php"    class="text-sm font-medium text-secondary hover:underline">Connexion</a>
            <a href="<?= $BASE ?>/public/register.php" class="bg-primary text-on-primary font-button text-button px-4 py-2 rounded-lg hover:opacity-90 transition-opacity active:scale-95">
                S'inscrire
            </a>
            <?php endif; ?>
        </div>
    </div>
</header>

<main class="pt-16">
<?php endif; ?>
