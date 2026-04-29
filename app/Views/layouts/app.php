<?php
$user = auth() ?? [];
$tenant = tenant() ?? [];
$page = $page ?? 'dashboard';

// Estructura de navegacion del sidebar
$navSections = [
    [
        'label' => 'Principal',
        'items' => [
            ['dashboard',   'Dashboard',          '/dashboard',   'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6'],
            ['contactos',   'Contactos',          '/contacts',    'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z'],
            ['leads',       'Pipeline ventas',    '/leads',       'M13 7h8m0 0v8m0-8l-8 8-4-4-6 6'],
            ['bandeja',     'Bandeja',            '/inbox',       'M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z'],
        ],
    ],
    [
        'label' => 'Trabajo',
        'items' => [
            ['tickets',     'Tickets',            '/tickets',     'M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z'],
            ['tareas',      'Tareas',             '/tasks',       'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4'],
            ['productos',   'Productos',          '/products',    'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4'],
        ],
    ],
    [
        'label' => 'Crecimiento',
        'items' => [
            ['campanas',          'Campanas',           '/campaigns',     'M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z'],
            ['automatizaciones',  'Automatizaciones',   '/automations',   'M13 10V3L4 14h7v7l9-11h-7z'],
            ['reportes',          'Reportes',           '/reports',       'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z'],
        ],
    ],
    [
        'label' => 'Sistema',
        'items' => [
            ['configuracion', 'Configuracion', '/settings', 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z'],
        ],
    ],
];
?>
<!DOCTYPE html>
<html lang="es" x-data="{
    darkMode: localStorage.getItem('km') !== 'light',
    sidebarOpen: false,
    sidebarCollapsed: localStorage.getItem('sb_collapsed') === '1',
    userMenu: false,
    notifMenu: false,
    cmdOpen: false,
    init() {
        this.$watch('darkMode', v => localStorage.setItem('km', v ? 'dark' : 'light'));
        this.$watch('sidebarCollapsed', v => localStorage.setItem('sb_collapsed', v ? '1' : '0'));
    }
}" :class="{ 'dark': darkMode }" @keydown.window.cmd.k.prevent="cmdOpen = !cmdOpen" @keydown.window.ctrl.k.prevent="cmdOpen = !cmdOpen">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= csrf_token() ?>">
    <title><?= e(ucfirst($page)) ?> · <?= e((string) config('app.name', 'Kyros Pulse')) ?></title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">

    <script>
        // Inicializar tema antes de pintar (evita FOUC)
        if (localStorage.getItem('km') !== 'light') document.documentElement.classList.add('dark');
        else document.documentElement.classList.remove('dark');
    </script>

    <script src="https://cdn.tailwindcss.com?plugins=forms"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'system-ui', 'sans-serif'], mono: ['JetBrains Mono', 'monospace'] },
                }
            }
        }
    </script>

    <link rel="stylesheet" href="<?= asset('css/kyros.css') ?>">

    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script defer src="https://unpkg.com/@alpinejs/collapse@3.x.x/dist/cdn.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

    <style>
        body { font-family: 'Inter', system-ui, sans-serif; }
        .sidebar-shell {
            background: var(--color-bg-surface);
            border-right: 1px solid var(--color-border-subtle);
        }
        .dark .sidebar-shell { background: #0A0F25; }
        .topbar-shell {
            background: var(--color-bg-surface);
            border-bottom: 1px solid var(--color-border-subtle);
        }
        .dark .topbar-shell { background: rgba(10, 15, 37, 0.85); backdrop-filter: blur(16px); }

        /* Trigger pill at sidebar/topbar */
        .search-trigger {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 0.75rem;
            font-size: 0.8125rem;
            background: var(--color-bg-subtle);
            border: 1px solid var(--color-border-subtle);
            border-radius: 0.5rem;
            color: var(--color-text-tertiary);
            transition: all .15s;
            width: 100%;
            cursor: pointer;
        }
        .search-trigger:hover { background: var(--color-bg-hover); color: var(--color-text-primary); }
    </style>
</head>
<body class="min-h-screen" style="background: var(--color-bg-base); color: var(--color-text-primary);">

<div class="flex min-h-screen">

    <!-- ====================== SIDEBAR ====================== -->
    <aside :class="[
        sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0',
        sidebarCollapsed ? 'lg:w-[72px]' : 'lg:w-[260px]'
    ]" class="sidebar-shell fixed lg:sticky top-0 left-0 h-screen w-[260px] z-40 transition-all duration-300 flex flex-col">

        <!-- Brand -->
        <div class="h-16 flex items-center justify-between gap-2 px-4 border-b" style="border-color: var(--color-border-subtle);">
            <a href="<?= url('/dashboard') ?>" class="flex items-center gap-2.5 min-w-0">
                <div class="w-9 h-9 rounded-xl flex items-center justify-center flex-shrink-0 shadow-md" style="background: var(--gradient-primary); box-shadow: 0 4px 14px rgba(124,58,237,.3);">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                </div>
                <div x-show="!sidebarCollapsed" class="min-w-0" x-cloak>
                    <div class="font-bold text-[14px] leading-tight" style="color: var(--color-text-primary);">Kyros<span class="gradient-text">Pulse</span></div>
                    <div class="text-[10px] leading-tight truncate mt-0.5" style="color: var(--color-text-tertiary);"><?= e($tenant['name'] ?? 'Tu empresa') ?></div>
                </div>
            </a>
            <button @click="sidebarCollapsed = !sidebarCollapsed" x-show="!sidebarCollapsed" x-cloak
                    class="hidden lg:inline-flex w-7 h-7 rounded-md items-center justify-center flex-shrink-0 transition hover:bg-[color:var(--color-bg-hover)]" style="color: var(--color-text-tertiary);" title="Colapsar">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 19l-7-7 7-7m8 14l-7-7 7-7"/></svg>
            </button>
        </div>

        <!-- Search trigger -->
        <div class="px-3 pt-3" x-show="!sidebarCollapsed" x-cloak>
            <button type="button" @click="cmdOpen = true" class="search-trigger">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <span class="flex-1 text-left">Buscar...</span>
                <span class="kbd">⌘K</span>
            </button>
        </div>
        <!-- Collapsed state: just expand button -->
        <div class="px-3 pt-3" x-show="sidebarCollapsed" x-cloak>
            <button @click="sidebarCollapsed = false" class="w-full h-10 flex items-center justify-center rounded-md transition hover:bg-[color:var(--color-bg-hover)]" style="color: var(--color-text-tertiary);" title="Expandir">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 5l7 7-7 7M5 5l7 7-7 7"/></svg>
            </button>
        </div>

        <!-- Navigation -->
        <nav class="flex-1 overflow-y-auto scrollbar-thin px-3 pt-2 pb-4">
            <?php foreach ($navSections as $section): ?>
                <div class="nav-section-label" x-show="!sidebarCollapsed" x-cloak><?= e($section['label']) ?></div>
                <?php foreach ($section['items'] as $item):
                    $isActive = ($page === $item[0]);
                ?>
                <a href="<?= url($item[2]) ?>" class="nav-link <?= $isActive ? 'active' : '' ?>" :title="sidebarCollapsed ? '<?= e($item[1]) ?>' : ''" :class="sidebarCollapsed ? 'justify-center' : ''">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="<?= $item[3] ?>"/></svg>
                    <span class="truncate" x-show="!sidebarCollapsed" x-cloak><?= e($item[1]) ?></span>
                </a>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </nav>

        <!-- Trial / Plan banner -->
        <?php if (!empty($tenant['trial_ends_at']) && ($tenant['status'] ?? '') === 'trial'):
            $daysLeft = max(0, (int) floor((strtotime($tenant['trial_ends_at']) - time()) / 86400));
        ?>
        <div class="p-3" x-show="!sidebarCollapsed" x-cloak>
            <div class="rounded-xl p-3 relative overflow-hidden" style="background: linear-gradient(135deg, rgba(124,58,237,.1), rgba(6,182,212,.1)); border: 1px solid rgba(124,58,237,.2);">
                <div class="flex items-center gap-2 mb-1.5">
                    <span class="text-sm">🎁</span>
                    <span class="text-xs font-semibold" style="color: var(--color-text-primary);">Periodo de prueba</span>
                </div>
                <div class="flex items-baseline justify-between mb-2">
                    <span class="text-[10px]" style="color: var(--color-text-tertiary);">Te quedan</span>
                    <span class="text-base font-bold gradient-text"><?= $daysLeft ?> dias</span>
                </div>
                <div class="h-1 rounded-full overflow-hidden mb-2" style="background: var(--color-bg-hover);">
                    <div class="h-full rounded-full" style="width: <?= max(5, min(100, $daysLeft * 100 / 14)) ?>%; background: var(--gradient-primary);"></div>
                </div>
                <a href="<?= url('/settings') ?>" class="block text-[11px] font-semibold hover:underline" style="color: var(--color-primary);">Actualizar plan →</a>
            </div>
        </div>
        <?php endif; ?>
    </aside>

    <!-- Backdrop mobile -->
    <div x-show="sidebarOpen" @click="sidebarOpen = false" x-transition class="lg:hidden fixed inset-0 bg-black/60 backdrop-blur-sm z-30" x-cloak></div>

    <!-- ====================== MAIN ====================== -->
    <div class="flex-1 flex flex-col min-w-0">

        <!-- TOPBAR -->
        <header class="topbar-shell sticky top-0 z-30">
            <div class="flex items-center justify-between gap-3 px-4 lg:px-6 h-16">
                <button @click="sidebarOpen = !sidebarOpen" class="lg:hidden p-2 -ml-2 rounded-lg" style="color: var(--color-text-secondary);">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>

                <button type="button" @click="cmdOpen = true" class="search-trigger flex-1 max-w-md hidden md:flex">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <span class="flex-1 text-left">Buscar contactos, conversaciones...</span>
                    <span class="kbd">⌘K</span>
                </button>

                <div class="flex items-center gap-1.5 ml-auto">
                    <button @click="darkMode = !darkMode" class="btn btn-ghost btn-icon" :title="darkMode ? 'Modo claro' : 'Modo oscuro'">
                        <svg x-show="darkMode" class="w-[18px] h-[18px]" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                        <svg x-show="!darkMode" class="w-[18px] h-[18px]" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/></svg>
                    </button>

                    <button class="btn btn-ghost btn-icon hidden sm:flex" title="Ayuda">
                        <svg class="w-[18px] h-[18px]" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </button>

                    <div class="relative" @click.outside="notifMenu = false">
                        <button @click="notifMenu = !notifMenu" class="btn btn-ghost btn-icon relative">
                            <svg class="w-[18px] h-[18px]" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                            <span class="absolute top-1.5 right-1.5 w-2 h-2 bg-rose-500 rounded-full"></span>
                        </button>
                        <div x-show="notifMenu" x-transition x-cloak class="absolute right-0 mt-2 w-80 rounded-xl shadow-xl border overflow-hidden z-50" style="background: var(--color-bg-elevated); border-color: var(--color-border-default);">
                            <div class="px-4 py-3 border-b flex items-center justify-between" style="border-color: var(--color-border-subtle);">
                                <h3 class="font-semibold text-sm" style="color: var(--color-text-primary);">Notificaciones</h3>
                                <button class="text-xs hover:underline" style="color: var(--color-text-tertiary);">Marcar leidas</button>
                            </div>
                            <div class="text-center py-12">
                                <div class="text-4xl mb-2 opacity-50">🔔</div>
                                <p class="text-xs" style="color: var(--color-text-tertiary);">Sin notificaciones</p>
                            </div>
                        </div>
                    </div>

                    <div class="w-px h-6 mx-1" style="background: var(--color-border-subtle);"></div>

                    <div class="relative" @click.outside="userMenu = false">
                        <button @click="userMenu = !userMenu" class="flex items-center gap-2 p-1 pr-2 rounded-lg transition" style="background: var(--color-bg-subtle); border: 1px solid var(--color-border-subtle);">
                            <div class="avatar avatar-md"><?= e(\App\Models\User::initials($user)) ?></div>
                            <div class="hidden md:block text-left">
                                <div class="text-[12px] font-semibold leading-tight" style="color: var(--color-text-primary);"><?= e(\App\Models\User::fullName($user)) ?></div>
                                <div class="text-[10px] leading-tight" style="color: var(--color-text-tertiary);"><?= e(mb_substr((string) ($user['email'] ?? ''), 0, 22)) ?></div>
                            </div>
                            <svg class="w-3 h-3 hidden md:block" style="color: var(--color-text-tertiary);" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <div x-show="userMenu" x-transition x-cloak class="absolute right-0 mt-2 w-60 rounded-xl shadow-xl border overflow-hidden z-50" style="background: var(--color-bg-elevated); border-color: var(--color-border-default);">
                            <div class="p-3 border-b flex items-center gap-3" style="border-color: var(--color-border-subtle);">
                                <div class="avatar avatar-md"><?= e(\App\Models\User::initials($user)) ?></div>
                                <div class="min-w-0">
                                    <div class="font-semibold text-sm truncate" style="color: var(--color-text-primary);"><?= e(\App\Models\User::fullName($user)) ?></div>
                                    <div class="text-xs truncate" style="color: var(--color-text-tertiary);"><?= e($user['email'] ?? '') ?></div>
                                </div>
                            </div>
                            <div class="py-1">
                                <a href="<?= url('/settings/profile') ?>" class="flex items-center gap-2 px-3 py-2 text-sm hover:bg-[color:var(--color-bg-hover)]" style="color: var(--color-text-secondary);">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                    Mi perfil
                                </a>
                                <a href="<?= url('/settings') ?>" class="flex items-center gap-2 px-3 py-2 text-sm hover:bg-[color:var(--color-bg-hover)]" style="color: var(--color-text-secondary);">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                    Configuracion
                                </a>
                            </div>
                            <div class="py-1 border-t" style="border-color: var(--color-border-subtle);">
                                <form action="<?= url('/logout') ?>" method="POST">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="w-full flex items-center gap-2 px-3 py-2 text-sm hover:bg-rose-500/10" style="color: #F43F5E;">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                                        Cerrar sesion
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <!-- Page content -->
        <main class="flex-1 p-4 lg:p-6">
            <?php \App\Core\View::include('components.flash'); ?>
            <?= \App\Core\View::section('content') ?>
        </main>
    </div>
</div>

<!-- Command palette -->
<div x-show="cmdOpen" @keydown.escape.window="cmdOpen = false" x-transition.opacity x-cloak class="fixed inset-0 z-50 flex items-start justify-center pt-[15vh] px-4">
    <div @click="cmdOpen = false" class="absolute inset-0 bg-black/60 backdrop-blur-sm"></div>
    <div @click.outside="cmdOpen = false" class="relative w-full max-w-xl rounded-2xl shadow-2xl overflow-hidden" style="background: var(--color-bg-elevated); border: 1px solid var(--color-border-default);">
        <div class="flex items-center gap-2 px-4 py-3 border-b" style="border-color: var(--color-border-subtle);">
            <svg class="w-5 h-5" style="color: var(--color-text-tertiary);" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            <input type="text" placeholder="Escribe un comando o busca..." class="flex-1 bg-transparent border-none outline-none text-sm" style="color: var(--color-text-primary);">
            <span class="kbd">ESC</span>
        </div>
        <div class="max-h-96 overflow-y-auto py-2">
            <?php foreach ([
                ['Dashboard', '/dashboard', '📊'],
                ['Crear contacto', '/contacts/create', '👥'],
                ['Ver contactos', '/contacts', '📇'],
                ['Pipeline', '/leads', '🎯'],
                ['Bandeja', '/inbox', '💬'],
                ['Crear ticket', '/tickets/create', '🎫'],
                ['Crear tarea', '/tasks', '✅'],
                ['Nueva campana', '/campaigns/create', '📢'],
                ['Automatizaciones', '/automations', '⚡'],
                ['Reportes', '/reports', '📈'],
                ['Configuracion', '/settings', '⚙️'],
            ] as $cmd): ?>
            <a href="<?= url($cmd[1]) ?>" class="flex items-center gap-3 px-4 py-2.5 text-sm hover:bg-[color:var(--color-bg-hover)]" style="color: var(--color-text-secondary);">
                <span class="text-base"><?= $cmd[2] ?></span>
                <span class="flex-1"><?= e($cmd[0]) ?></span>
                <svg class="w-4 h-4 opacity-40" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
            </a>
            <?php endforeach; ?>
        </div>
        <div class="px-4 py-2 border-t flex items-center justify-between text-xs" style="border-color: var(--color-border-subtle); color: var(--color-text-tertiary);">
            <span>Navega con <span class="kbd">↑</span> <span class="kbd">↓</span></span>
            <span>Selecciona con <span class="kbd">↵</span></span>
        </div>
    </div>
</div>

</body>
</html>
