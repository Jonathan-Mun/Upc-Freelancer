<?php
// ============================================================
// UPC FREELANCE — Header global
// /var/www/html/upc_freelance/includes/header.php
// ============================================================

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';

$user       = currentUser();
$notifCount = $user ? countUnreadNotifications($user['id']) : 0;
$msgCount   = $user ? countUnreadMessages($user['id'])      : 0;
$wallet     = $user ? getUserWallet($user['id'])            : null;

$BASE = '/upc_freelance';
?>
<!DOCTYPE html>
<html lang="fr" class="light">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<meta name="description" content="UPC Freelance — La plateforme de mise en relation entre étudiants freelances et clients."/>
<title><?= h($pageTitle ?? 'UPC Freelance') ?></title>

<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
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
    font-weight: 600;
}
.sidebar-link {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.625rem 0.75rem;
    color: #43474e;
    font-size: 14px;
    transition: all 0.15s;
    text-decoration: none;
}
.sidebar-link:hover { background:#f1f5f9; transform: translateX(2px); }

/* ── Drawer mobile ─────────────────────────────────────── */
#mob-drawer {
    position: fixed;
    inset: 0;
    z-index: 999;
    display: none;
}
#mob-drawer.open { display: block; }
#mob-drawer-backdrop {
    position: absolute;
    inset: 0;
    background: rgba(0,0,0,0.45);
}
#mob-drawer-panel {
    position: absolute;
    top: 0; left: 0; bottom: 0;
    width: 280px;
    background: #fff;
    box-shadow: 4px 0 24px rgba(0,0,0,0.15);
    display: flex;
    flex-direction: column;
    overflow-y: auto;
    transform: translateX(-100%);
    transition: transform 0.25s ease;
}
#mob-drawer.open #mob-drawer-panel {
    transform: translateX(0);
}
</style>
<?php if ($user): ?>
    <script src="<?= $BASE ?>/app/notifications/notify-client.js" defer></script>
<?php endif; ?>
</head>

<?php if (isset($appLayout) && $appLayout): ?>
<!-- ════════════ LAYOUT APP ════════════ -->
<body class="bg-background text-on-surface font-body-md antialiased min-h-screen flex">

<!-- ── Sidebar desktop ───────────────────────────────────── -->
<aside class="hidden lg:flex flex-col w-64 bg-white border-r border-slate-200 sticky top-0 h-screen p-4 z-40">

    <a href="<?= $BASE ?>/app/dashboard.php" class="flex items-center gap-3 mb-8 px-2 group">
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

    <nav class="flex-1 space-y-1">
        <?php
        $currentFile = basename($_SERVER['PHP_SELF']);
        $currentDir  = basename(dirname($_SERVER['PHP_SELF']));
        
        // ── AJOUT DU PREMIER : Intégration de l'élément 'verification' dans le tableau ──
        $navItems = [
            ['icon'=>'dashboard',              'label'=>'Tableau de bord',  'href'=>$BASE.'/app/dashboard.php',                    'dir'=>'app',          'file'=>'dashboard.php'],
            ['icon'=>'search',                 'label'=>'Trouver un projet','href'=>$BASE.'/app/projects/list.php',                'dir'=>'projects',     'file'=>'list.php'],
            ['icon'=>'add_circle',             'label'=>'Créer un projet',  'href'=>$BASE.'/app/projects/create.php',              'dir'=>'projects',     'file'=>'create.php'],
            ['icon'=>'folder_open',            'label'=>'Mes projets',      'href'=>$BASE.'/app/projects/my-projects.php',         'dir'=>'projects',     'file'=>'my-projects.php'],
            ['icon'=>'send',                   'label'=>'Candidatures',     'href'=>$BASE.'/app/postulations/my-applications.php', 'dir'=>'postulations', 'file'=>'my-applications.php'],
            ['icon'=>'description',            'label'=>'Contrats',         'href'=>$BASE.'/app/contracts/list.php',               'dir'=>'contracts',    'file'=>'list.php'],
            ['icon'=>'chat',                   'label'=>'Messages',         'href'=>$BASE.'/app/messages/inbox.php',               'dir'=>'messages',     'file'=>'inbox.php'],
            ['icon'=>'account_balance_wallet', 'label'=>'Wallet',           'href'=>$BASE.'/app/wallet/index.php',                 'dir'=>'wallet',       'file'=>'index.php'],
            ['icon'=>'notifications',          'label'=>'Notifications',    'href'=>$BASE.'/app/notifications/index.php',          'dir'=>'notifications','file'=>'index.php'],
            ['icon'=>'verified_user',          'label'=>'Vérification',     'href'=>$BASE.'/app/verification/index.php',           'dir'=>'verification', 'file'=>'index.php'],
            ['icon'=>'person',                 'label'=>'Mon profil',       'href'=>$BASE.'/app/profile/view.php?id='.$user['id'], 'dir'=>'profile',      'file'=>'view.php'],
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
            <?php if ($nav['icon'] === 'notifications'): ?>
            <span id="badge-notif-sidebar"
                  class="ml-auto bg-red-500 text-white text-[10px] font-bold rounded-full w-5 h-5 items-center justify-center <?= $notifCount > 0 ? 'flex' : 'hidden' ?>">
                <?= $notifCount > 9 ? '9+' : $notifCount ?>
            </span>
            <?php endif; ?>
            <?php if ($nav['icon'] === 'chat'): ?>
            <span id="badge-msg-sidebar"
                  class="ml-auto bg-secondary text-white text-[10px] font-bold rounded-full w-5 h-5 items-center justify-center <?= $msgCount > 0 ? 'flex' : 'hidden' ?>">
                <?= $msgCount > 9 ? '9+' : $msgCount ?>
            </span>
            <?php endif; ?>
        </a>
        <?php endforeach; ?>
    </nav>

    <div class="mt-auto pt-4 border-t border-slate-100">
        <?php if ($wallet): ?>
        <div class="mb-3 px-2 py-2 bg-surface-container-low rounded-lg">
            <p class="text-[10px] text-slate-400 font-label-caps uppercase">Mon wallet</p>
            <p class="text-base font-bold text-primary"><?= money((float)$wallet['balance']) ?></p>
        </div>
        <?php endif; ?>
        <div class="flex items-center gap-2 px-2 mb-3">
            <?php if ($user && $user['avatar']): ?>
            <img src="<?= $BASE ?>/storage/<?= h($user['avatar']) ?>" alt="Avatar"
                 class="w-8 h-8 rounded-full object-cover border border-slate-200"/>
            <?php else: ?>
            <div class="w-8 h-8 rounded-full bg-primary-container flex items-center justify-center text-white text-sm font-bold">
                <?= mb_strtoupper(mb_substr($user['first_name'] ?? 'U', 0, 1)) ?>
            </div>
            <?php endif; ?>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-semibold text-primary truncate">
                    <?= h(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?>
                </p>
                <p class="text-[10px] text-slate-400 capitalize"><?= h($user['role'] ?? '') ?></p>
            </div>
        </div>
        <a href="<?= $BASE ?>/auth/logout.php"
           class="flex items-center gap-2 px-3 py-2 rounded-lg text-red-500 hover:bg-red-50 transition-colors text-sm">
            <span class="material-symbols-outlined text-base">logout</span>
            Se déconnecter
        </a>
    </div>
</aside>

<!-- ── Drawer mobile ─────────────────────────────────────── -->
<div id="mob-drawer">
    <div id="mob-drawer-backdrop"></div>
    <div id="mob-drawer-panel" class="p-4">

        <!-- Header du drawer -->
        <div class="flex items-center justify-between mb-6">
            <div class="flex items-center gap-3">
                <svg width="32" height="32" viewBox="0 0 38 38" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <rect width="38" height="38" rx="10" fill="#002045"/>
                    <path d="M10 12 L10 22 Q10 28 19 28 Q28 28 28 22 L28 12" stroke="#fff" stroke-width="2.5" stroke-linecap="round" fill="none"/>
                    <circle cx="19" cy="12" r="1.5" fill="#66affe"/>
                    <path d="M14 18 L24 18" stroke="#66affe" stroke-width="2" stroke-linecap="round"/>
                </svg>
                <span class="font-bold text-primary">UPC Freelance</span>
            </div>
            <button id="mob-drawer-close" class="p-1 rounded-lg hover:bg-slate-100 transition-colors">
                <span class="material-symbols-outlined text-slate-500">close</span>
            </button>
        </div>

        <!-- User info dans le drawer -->
        <?php if ($user): ?>
        <div class="flex items-center gap-3 p-3 bg-surface-container-low rounded-xl mb-5">
            <?php if ($user['avatar']): ?>
            <img src="<?= $BASE ?>/storage/<?= h($user['avatar']) ?>" alt="Avatar"
                 class="w-10 h-10 rounded-full object-cover"/>
            <?php else: ?>
            <div class="w-10 h-10 rounded-full bg-primary flex items-center justify-center text-white font-bold">
                <?= mb_strtoupper(mb_substr($user['first_name'] ?? 'U', 0, 1)) ?>
            </div>
            <?php endif; ?>
            <div class="min-w-0">
                <p class="font-semibold text-primary text-sm truncate">
                    <?= h(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?>
                </p>
                <p class="text-xs text-slate-400 capitalize"><?= h($user['role'] ?? '') ?></p>
            </div>
            <?php if ($wallet): ?>
            <div class="ml-auto text-right shrink-0">
                <p class="text-[10px] text-slate-400">Wallet</p>
                <p class="text-sm font-bold text-primary"><?= money((float)$wallet['balance']) ?></p>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Nav dans le drawer -->
        <nav class="space-y-1 flex-1">
            <?php foreach ($navItems as $nav):
                $isActive = ($currentDir === $nav['dir'] && $currentFile === $nav['file'])
                         || ($currentFile === $nav['file'] && $currentDir === 'app');
                $class = $isActive ? 'sidebar-link nav-active' : 'sidebar-link';
            ?>
            <a href="<?= $nav['href'] ?>" class="<?= $class ?>">
                <span class="material-symbols-outlined text-xl <?= $isActive ? 'text-secondary' : 'text-slate-400' ?>">
                    <?= $nav['icon'] ?>
                </span>
                <span><?= $nav['label'] ?></span>
                <?php if ($nav['icon'] === 'notifications'): ?>
                <span id="badge-notif-drawer"
                      class="ml-auto bg-red-500 text-white text-[10px] font-bold rounded-full w-5 h-5 items-center justify-center <?= $notifCount > 0 ? 'flex' : 'hidden' ?>">
                    <?= $notifCount > 9 ? '9+' : $notifCount ?>
                </span>
                <?php endif; ?>
                <?php if ($nav['icon'] === 'chat'): ?>
                <span id="badge-msg-drawer"
                      class="ml-auto bg-secondary text-white text-[10px] font-bold rounded-full w-5 h-5 items-center justify-center <?= $msgCount > 0 ? 'flex' : 'hidden' ?>">
                    <?= $msgCount > 9 ? '9+' : $msgCount ?>
                </span>
                <?php endif; ?>
            </a>
            <?php endforeach; ?>
        </nav>

        <!-- Déconnexion -->
        <div class="mt-6 pt-4 border-t border-slate-100">
            <a href="<?= $BASE ?>/auth/logout.php"
               class="flex items-center gap-2 px-3 py-2 rounded-lg text-red-500 hover:bg-red-50 transition-colors text-sm">
                <span class="material-symbols-outlined text-base">logout</span>
                Se déconnecter
            </a>
        </div>
    </div>
</div>

<!-- ── Main content ──────────────────────────────────────── -->
<div class="flex-1 flex flex-col min-w-0">

    <!-- Top bar mobile -->
    <header class="lg:hidden fixed top-0 left-0 right-0 z-50 bg-white border-b border-slate-200 h-14 flex items-center justify-between px-4">
        <button id="mob-menu-btn" class="p-2 rounded-lg hover:bg-slate-100 transition-colors">
            <span class="material-symbols-outlined text-slate-700">menu</span>
        </button>
        <svg width="28" height="28" viewBox="0 0 38 38" fill="none" xmlns="http://www.w3.org/2000/svg">
            <rect width="38" height="38" rx="10" fill="#002045"/>
            <path d="M10 12 L10 22 Q10 28 19 28 Q28 28 28 22 L28 12" stroke="#fff" stroke-width="2.5" stroke-linecap="round" fill="none"/>
            <circle cx="19" cy="12" r="1.5" fill="#66affe"/>
            <path d="M14 18 L24 18" stroke="#66affe" stroke-width="2" stroke-linecap="round"/>
        </svg>
        <div class="flex items-center gap-2">
            <a href="<?= $BASE ?>/app/messages/inbox.php" class="relative p-2 rounded-lg hover:bg-slate-100 transition-colors">
                <span class="material-symbols-outlined text-slate-600 text-xl">chat</span>
                <span id="badge-msg-topbar"
                      class="absolute top-1 right-1 bg-secondary text-white text-[9px] rounded-full w-4 h-4 items-center justify-center font-bold <?= $msgCount > 0 ? 'flex' : 'hidden' ?>">
                    <?= $msgCount > 9 ? '9+' : $msgCount ?>
                </span>
            </a>
            <a href="<?= $BASE ?>/app/notifications/index.php" class="relative p-2 rounded-lg hover:bg-slate-100 transition-colors">
                <span class="material-symbols-outlined text-slate-600 text-xl">notifications</span>
                <span id="badge-notif-topbar"
                      class="absolute top-1 right-1 bg-red-500 text-white text-[9px] rounded-full w-4 h-4 items-center justify-center font-bold <?= $notifCount > 0 ? 'flex' : 'hidden' ?>">
                    <?= $notifCount > 9 ? '9+' : $notifCount ?>
                </span>
            </a>
        </div>
    </header>

    <main class="flex-1 lg:pt-0 pt-14 p-6 lg:p-8">

<script>
// ── Hamburger drawer ─────────────────────────────────────
(function () {
    const btn      = document.getElementById('mob-menu-btn');
    const drawer   = document.getElementById('mob-drawer');
    const closeBtn = document.getElementById('mob-drawer-close');
    const backdrop = document.getElementById('mob-drawer-backdrop');

    function openDrawer()  { drawer.classList.add('open');    document.body.style.overflow = 'hidden'; }
    function closeDrawer() { drawer.classList.remove('open'); document.body.style.overflow = '';       }

    if (btn)      btn.addEventListener('click', openDrawer);
    if (closeBtn) closeBtn.addEventListener('click', closeDrawer);
    if (backdrop) backdrop.addEventListener('click', closeDrawer);

    // Fermer avec Escape
    document.addEventListener('keydown', e => { if (e.key === 'Escape') closeDrawer(); });
})();

// ── Notification + Message polling ───────────────────────
(function () {
    const BASE           = '/upc_freelance';
    const NOTIF_API      = BASE + '/app/notifications/api-notifications.php';
    const MSG_API        = BASE + '/app/messages/api-conversations.php';
    const INTERVAL       = 2000; // 2 secondes
    const IS_NOTIF_PAGE  = window.location.pathname.includes('/notifications/');
    const IS_INBOX_PAGE  = window.location.pathname.includes('/messages/');

    // ── Badges ────────────────────────────────────────────
    const NOTIF_BADGES = ['badge-notif-sidebar', 'badge-notif-drawer', 'badge-notif-topbar'];
    const MSG_BADGES   = ['badge-msg-sidebar',   'badge-msg-drawer',   'badge-msg-topbar'];

    function formatCount(n) { return n > 9 ? '9+' : String(n); }

    function updateBadges(ids, count) {
        ids.forEach(id => {
            const el = document.getElementById(id);
            if (!el) return;
            if (count > 0) {
                el.textContent = formatCount(count);
                el.classList.remove('hidden');
                el.classList.add('flex');
            } else {
                el.classList.add('hidden');
                el.classList.remove('flex');
            }
        });
    }

    function pulseBadges(ids) {
        ids.forEach(id => {
            const el = document.getElementById(id);
            if (!el || el.classList.contains('hidden')) return;
            el.style.transform  = 'scale(1.35)';
            el.style.transition = 'transform 0.15s ease';
            setTimeout(() => { el.style.transform = 'scale(1)'; }, 200);
        });
    }

    // ── Toast (même design que la page notifications) ────
    function showToast(ti, title, body, link) {
        // Décaler les toasts existants
        document.querySelectorAll('.upc-toast').forEach((t, i) => {
            t.style.bottom = (24 + (i + 1) * 82) + 'px';
        });

        const toast = document.createElement('div');
        toast.className = 'upc-toast';
        toast.style.cssText = `
            position:fixed; bottom:24px; right:24px; z-index:9999;
            background:#fff; color:#0d1c2e;
            padding:14px 16px; border-radius:16px;
            box-shadow:0 8px 28px rgba(0,32,69,.18);
            display:flex; align-items:flex-start; gap:12px;
            max-width:320px; width:calc(100vw - 48px);
            animation:slideInToast .25s ease;
            cursor:${link ? 'pointer' : 'default'};
            border:1px solid #e2e8f0;
        `;
        toast.innerHTML = `
            <div style="
                width:40px; height:40px; border-radius:50%;
                background:${ti.bg};
                display:flex; align-items:center; justify-content:center;
                flex-shrink:0;
            ">
                <span class="material-symbols-outlined" style="
                    font-size:20px; color:${ti.color};
                    font-variation-settings:'FILL' 1;
                ">${ti.icon}</span>
            </div>
            <div style="flex:1;min-width:0;padding-top:2px;">
                <p style="font-weight:700;font-size:13px;color:#002045;margin:0 0 3px;">${title}</p>
                ${body ? `<p style="font-size:12px;color:#43474e;margin:0;line-height:1.4;overflow:hidden;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;">${body}</p>` : ''}
            </div>
            <button onclick="this.parentElement.remove()" style="
                background:none;border:none;color:#94a3b8;
                cursor:pointer;font-size:18px;line-height:1;
                flex-shrink:0;margin-top:-2px;padding:2px;
            ">×</button>
        `;
        if (link) toast.addEventListener('click', e => { if (e.target.tagName !== 'BUTTON') window.location.href = link; });
        document.body.appendChild(toast);
        setTimeout(() => {
            toast.style.animation = 'slideOutToast .2s ease forwards';
            setTimeout(() => toast.remove(), 200);
        }, 5000);
    }

    // Keyframes (injectés une seule fois)
    if (!document.getElementById('upc-toast-styles')) {
        const s = document.createElement('style');
        s.id = 'upc-toast-styles';
        s.textContent = `
            @keyframes slideInToast {
                from { transform:translateX(110%); opacity:0; }
                to   { transform:translateX(0);    opacity:1; }
            }
            @keyframes slideOutToast {
                from { transform:translateX(0);    opacity:1; }
                to   { transform:translateX(110%); opacity:0; }
            }
        `;
        document.head.appendChild(s);
    }

    // ── Icônes notifs (Material Symbols — même design que la page notifs)
    const NOTIF_ICONS = {
        welcome:              { icon: 'celebration',  color: '#9333ea', bg: '#f5f3ff' },
        new_application:      { icon: 'inbox',        color: '#d97706', bg: '#fffbeb' },
        application_accepted: { icon: 'check_circle', color: '#16a34a', bg: '#f0fdf4' },
        application_rejected: { icon: 'cancel',       color: '#f87171', bg: '#fef2f2' },
        new_message:          { icon: 'chat',         color: '#3b82f6', bg: '#eff6ff' },
        payment_received:     { icon: 'payments',     color: '#059669', bg: '#ecfdf5' },
        deposit_success:      { icon: 'add_circle',   color: '#16a34a', bg: '#f0fdf4' },
        contract_created:     { icon: 'description',  color: '#0061a5', bg: '#eff4ff' },
    };
    const NOTIF_DEFAULT = { icon: 'notifications', color: '#64748b', bg: '#f8fafc' };

    // ── État ──────────────────────────────────────────────
    let lastNotifId  = 0;
    let prevMsgCount = <?= (int)($msgCount ?? 0) ?>;

    // ── Poll messages — badge uniquement, pas de toast ────
    async function pollMsgs() {
        try {
            const res   = await fetch(MSG_API, { credentials: 'same-origin' });
            if (!res.ok) return;
            const convs = await res.json();

            const total = convs.reduce((acc, c) => acc + (parseInt(c.unread) || 0), 0);
            updateBadges(MSG_BADGES, total);
            if (prevMsgCount < total) pulseBadges(MSG_BADGES);
            prevMsgCount = total;

            if (IS_INBOX_PAGE && window.UPCInboxPage) window.UPCInboxPage.refresh(convs, total);
        } catch (e) {}
    }

    // ── Poll notifications — badge + toast pour tout ──────
    async function pollNotifs() {
        try {
            const res  = await fetch(NOTIF_API + '?action=poll&since=' + lastNotifId + '&limit=5', { credentials: 'same-origin' });
            if (!res.ok) return;
            const data = await res.json();

            const count  = data.unread ?? 0;
            const notifs = data.notifications ?? [];

            updateBadges(NOTIF_BADGES, count);

            if (lastNotifId > 0 && notifs.length > 0) {
                pulseBadges(NOTIF_BADGES);
                notifs.slice(0, 3).forEach(n => {
                    const ti = NOTIF_ICONS[n.type] || NOTIF_DEFAULT;
                    showToast(ti, n.title, n.body, n.link);
                });
                if (IS_NOTIF_PAGE && window.UPCNotifPage) window.UPCNotifPage.refresh(notifs, count);
            }

            if (notifs.length > 0) lastNotifId = Math.max(...notifs.map(n => n.id));
        } catch (e) {}
    }

    // ── Init ──────────────────────────────────────────────
    async function init() {
        try {
            // Notifs : récupère le dernier id sans toast
            const r1   = await fetch(NOTIF_API + '?action=poll&since=0&limit=1', { credentials: 'same-origin' });
            const d1   = await r1.json();
            const n1   = d1.notifications ?? [];
            if (n1.length > 0) lastNotifId = n1[0].id;
            updateBadges(NOTIF_BADGES, d1.unread ?? 0);

            // Messages : init du compteur
            const r2    = await fetch(MSG_API, { credentials: 'same-origin' });
            const convs = await r2.json();
            prevMsgCount = convs.reduce((acc, c) => acc + (parseInt(c.unread) || 0), 0);
            updateBadges(MSG_BADGES, prevMsgCount);
        } catch (e) {}
    }

    init().then(() => {
        setInterval(async () => {
            await pollMsgs();   // messages en premier
            await pollNotifs(); // notifs ensuite — new_message déjà filtrés
        }, INTERVAL);
    });

    // Exposer
    window.UPCNotifBadge = { updateBadges: (c) => updateBadges(NOTIF_BADGES, c) };
    window.UPCMsgBadge   = { updateBadges: (c) => updateBadges(MSG_BADGES, c) };
})();
</script>

<?php else: ?>
<!-- ════════════ LAYOUT PUBLIC ════════════ -->
<body class="bg-background text-on-surface font-body-md antialiased">

<!-- Drawer mobile public -->
<div id="pub-drawer" style="position:fixed;inset:0;z-index:999;display:none;">
    <div id="pub-drawer-backdrop" style="position:absolute;inset:0;background:rgba(0,0,0,0.45);"></div>
    <div id="pub-drawer-panel" style="
        position:absolute;top:0;right:0;bottom:0;width:280px;
        background:#fff;box-shadow:-4px 0 24px rgba(0,0,0,0.15);
        display:flex;flex-direction:column;padding:24px 20px;
        transform:translateX(100%);transition:transform 0.25s ease;
    ">
        <!-- Header drawer -->
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:28px;">
            <div style="display:flex;align-items:center;gap:10px;">
                <svg width="30" height="30" viewBox="0 0 38 38" fill="none">
                    <rect width="38" height="38" rx="10" fill="#002045"/>
                    <path d="M10 12 L10 22 Q10 28 19 28 Q28 28 28 22 L28 12" stroke="#fff" stroke-width="2.5" stroke-linecap="round" fill="none"/>
                    <circle cx="19" cy="12" r="1.5" fill="#66affe"/>
                    <path d="M14 18 L24 18" stroke="#66affe" stroke-width="2" stroke-linecap="round"/>
                </svg>
                <span style="font-weight:700;color:#002045;font-size:15px;">UPC Freelance</span>
            </div>
            <button id="pub-drawer-close" style="background:none;border:none;cursor:pointer;padding:6px;border-radius:8px;">
                <span class="material-symbols-outlined" style="color:#64748b;font-size:22px;">close</span>
            </button>
        </div>

        <!-- Nav links -->
        <nav style="display:flex;flex-direction:column;gap:4px;flex:1;">
            <?php
            $pubCurrent = $_SERVER['REQUEST_URI'];
            $pubLinks = [
                ['href' => $BASE.'/public/index.php',        'label' => 'Accueil',           'icon' => 'home'],
                ['href' => $BASE.'/public/how-it-works.php', 'label' => 'Comment ça marche', 'icon' => 'help_outline'],
                ['href' => $BASE.'/app/projects/list.php',   'label' => 'Projets',           'icon' => 'search'],
                ['href' => $BASE.'/public/about.php',        'label' => 'À propos',          'icon' => 'info'],
                ['href' => $BASE.'/public/contact.php',      'label' => 'Contact',           'icon' => 'mail'],
            ];
            foreach ($pubLinks as $pl):
                $isActive = str_contains($pubCurrent, basename($pl['href']));
            ?>
            <a href="<?= $pl['href'] ?>" style="
                display:flex;align-items:center;gap:12px;
                padding:12px 14px;border-radius:10px;
                color:<?= $isActive ? '#0061a5' : '#43474e' ?>;
                background:<?= $isActive ? '#eff4ff' : 'transparent' ?>;
                font-size:14px;font-weight:<?= $isActive ? '600' : '400' ?>;
                text-decoration:none;transition:background 0.15s;
            " onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='<?= $isActive ? '#eff4ff' : 'transparent' ?>'">
                <span class="material-symbols-outlined" style="font-size:20px;color:<?= $isActive ? '#0061a5' : '#94a3b8' ?>;"><?= $pl['icon'] ?></span>
                <?= $pl['label'] ?>
            </a>
            <?php endforeach; ?>
        </nav>

        <!-- Boutons auth en bas du drawer -->
        <div style="display:flex;flex-direction:column;gap:10px;margin-top:24px;padding-top:20px;border-top:1px solid #e2e8f0;">
            <?php if (isLoggedIn()): ?>
            <a href="<?= $BASE ?>/app/dashboard.php" style="
                display:flex;align-items:center;justify-content:center;gap:8px;
                background:#002045;color:#fff;padding:13px;border-radius:12px;
                font-size:14px;font-weight:700;text-decoration:none;
            ">
                <span class="material-symbols-outlined" style="font-size:18px;">dashboard</span>
                Mon espace
            </a>
            <?php else: ?>
            <a href="<?= $BASE ?>/public/register.php" style="
                display:flex;align-items:center;justify-content:center;
                background:#002045;color:#fff;padding:13px;border-radius:12px;
                font-size:14px;font-weight:700;text-decoration:none;
            ">S'inscrire</a>
            <a href="<?= $BASE ?>/public/login.php" style="
                display:flex;align-items:center;justify-content:center;
                border:1.5px solid #e2e8f0;color:#0061a5;padding:12px;border-radius:12px;
                font-size:14px;font-weight:600;text-decoration:none;
            ">Connexion</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<header class="fixed top-0 w-full z-50 bg-white/95 backdrop-blur-sm border-b border-slate-200 shadow-sm">
    <div class="flex justify-between items-center px-4 md:px-6 h-16 max-w-screen-xl mx-auto">

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
            <a href="<?= $BASE ?>/public/index.php"        class="text-slate-600 hover:text-secondary transition-colors">Accueil</a>
            <a href="<?= $BASE ?>/public/how-it-works.php" class="text-slate-600 hover:text-secondary transition-colors">Comment ça marche</a>
            <a href="<?= $BASE ?>/public/about.php"        class="text-slate-600 hover:text-secondary transition-colors">À propos</a>
            <a href="<?= $BASE ?>/public/contact.php"      class="text-slate-600 hover:text-secondary transition-colors">Contact</a>
        </nav>

        <!-- Boutons desktop -->
        <div class="hidden md:flex items-center gap-3">
            <?php if (isLoggedIn()): ?>
            <a href="<?= $BASE ?>/app/dashboard.php"
               class="bg-primary text-on-primary font-button text-button px-4 py-2 rounded-lg hover:opacity-90 transition-opacity active:scale-95">
                Mon espace
            </a>
            <?php else: ?>
            <a href="<?= $BASE ?>/public/login.php" class="text-sm font-medium text-secondary hover:underline">Connexion</a>
            <a href="<?= $BASE ?>/public/register.php"
               class="bg-primary text-on-primary font-button text-button px-4 py-2 rounded-lg hover:opacity-90 transition-opacity active:scale-95">
                S'inscrire
            </a>
            <?php endif; ?>
        </div>

        <!-- Hamburger mobile -->
        <button id="pub-menu-btn" class="md:hidden p-2 rounded-lg hover:bg-slate-100 transition-colors">
            <span class="material-symbols-outlined text-slate-700">menu</span>
        </button>
    </div>
</header>

<script>
(function () {
    const btn      = document.getElementById('pub-menu-btn');
    const drawer   = document.getElementById('pub-drawer');
    const panel    = document.getElementById('pub-drawer-panel');
    const closeBtn = document.getElementById('pub-drawer-close');
    const backdrop = document.getElementById('pub-drawer-backdrop');

    function open()  {
        drawer.style.display = 'block';
        requestAnimationFrame(() => panel.style.transform = 'translateX(0)');
        document.body.style.overflow = 'hidden';
    }
    function close() {
        panel.style.transform = 'translateX(100%)';
        setTimeout(() => drawer.style.display = 'none', 250);
        document.body.style.overflow = '';
    }

    btn?.addEventListener('click', open);
    closeBtn?.addEventListener('click', close);
    backdrop?.addEventListener('click', close);
    document.addEventListener('keydown', e => { if (e.key === 'Escape') close(); });
})();
</script>

<main class="pt-16">
<?php endif; ?>