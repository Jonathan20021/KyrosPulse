<?php
$user = auth() ?? [];
$page = $page ?? 'admin';
$current = $_SERVER['REQUEST_URI'] ?? '';
$brand   = (string) (\App\Models\SaasSetting::get('brand_name', config('app.name', 'Kyros Pulse')));

$navSections = [
    [
        'label' => 'Operacion',
        'items' => [
            ['/admin',              'Dashboard',  'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6'],
            ['/admin/tenants',      'Empresas',   'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5'],
            ['/admin/users',        'Usuarios',   'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z'],
            ['/admin/plans',        'Planes',     'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2'],
        ],
    ],
    [
        'label' => 'IA y motor',
        'items' => [
            ['/admin/ai-providers', 'IA Globales','M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z'],
        ],
    ],
    [
        'label' => 'Marketing',
        'items' => [
            ['/admin/branding',     'Branding',   'M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01'],
            ['/admin/changelog',    'Changelog',  'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'],
        ],
    ],
    [
        'label' => 'Sistema',
        'items' => [
            ['/admin/logs',         'Logs',       'M19 11H5m14-7H5a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2V6a2 2 0 00-2-2zM9 8h6'],
        ],
    ],
];
?>
<!DOCTYPE html>
<html lang="es" x-data="{
    darkMode: localStorage.getItem('km') !== 'light',
    sidebarCollapsed: localStorage.getItem('admin_sb') === '1',
    init() {
        this.$watch('darkMode', v => { localStorage.setItem('km', v ? 'dark' : 'light'); v ? document.documentElement.classList.add('dark') : document.documentElement.classList.remove('dark'); });
        this.$watch('sidebarCollapsed', v => localStorage.setItem('admin_sb', v ? '1' : '0'));
    }
}" :class="{ 'dark': darkMode }">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= csrf_token() ?>">
    <title>Super Admin · <?= e($brand) ?></title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">

    <script src="https://cdn.tailwindcss.com?plugins=forms,typography"></script>
    <link rel="stylesheet" href="<?= asset('css/kyros.css') ?>">
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script defer src="https://unpkg.com/@alpinejs/collapse@3.x.x/dist/cdn.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script>
        if (localStorage.getItem('km') !== 'light') document.documentElement.classList.add('dark');
        else document.documentElement.classList.remove('dark');
        tailwind.config = { darkMode: 'class', theme: { extend: { fontFamily: { sans: ['Inter', 'system-ui', 'sans-serif'], mono: ['JetBrains Mono','monospace'] }, colors: { primary: '#7C3AED', cyan: '#06B6D4' }, animation: { 'fade-up': 'fadeUp 0.5s ease forwards', 'fade-in': 'fadeIn 0.4s ease forwards' }, keyframes: { fadeUp: { '0%': { opacity: 0, transform: 'translateY(8px)' }, '100%': { opacity: 1, transform: 'translateY(0)' } }, fadeIn: { '0%':{opacity:0}, '100%':{opacity:1} } } } } };
    </script>
    <style>
        .admin-shell { background: linear-gradient(180deg, #050817 0%, #0A0F25 100%); }
        .admin-card  { background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.08); backdrop-filter: blur(12px); }
        .admin-card:hover { border-color: rgba(124,58,237,0.4); }
        .admin-nav-link { display:flex; align-items:center; gap:.75rem; padding:.55rem .85rem; border-radius:.65rem; font-size:.85rem; transition: all .2s; color:#CBD5E1; }
        .admin-nav-link:hover { background: rgba(255,255,255,0.05); color:#fff; }
        .admin-nav-link.active { background: linear-gradient(135deg, rgba(124,58,237,.15), rgba(6,182,212,.10)); color:#fff; box-shadow: inset 2px 0 0 #7C3AED; }
        .admin-nav-section { font-size: 10px; font-weight:700; text-transform:uppercase; letter-spacing:.12em; color:#64748B; padding: 0.75rem 0.85rem 0.4rem; }
        @media (prefers-reduced-motion: no-preference) {
            .admin-card { animation: fadeUp .5s ease both; }
        }
    </style>
</head>
<body class="min-h-screen text-slate-200 admin-shell">

<div class="min-h-screen flex" x-data="{ open:false }">
    <!-- Sidebar -->
    <aside :class="sidebarCollapsed ? 'w-[68px]' : 'w-[240px]'"
           class="hidden lg:flex flex-col border-r border-white/5 bg-[#070C20]/80 backdrop-blur-xl sticky top-0 h-screen transition-all duration-200">
        <div class="p-4 flex items-center justify-between gap-2 border-b border-white/5">
            <a href="<?= url('/admin') ?>" class="flex items-center gap-2 min-w-0">
                <div class="w-9 h-9 rounded-xl flex items-center justify-center flex-shrink-0 shadow-lg shadow-violet-500/30" style="background: linear-gradient(135deg,#7C3AED,#06B6D4);">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                </div>
                <div x-show="!sidebarCollapsed" x-cloak class="min-w-0">
                    <div class="font-bold text-white text-sm leading-tight truncate"><?= e($brand) ?></div>
                    <div class="text-[10px] font-bold tracking-wider text-rose-400 leading-tight">SUPER ADMIN</div>
                </div>
            </a>
            <button @click="sidebarCollapsed = !sidebarCollapsed" class="p-1 rounded text-slate-500 hover:text-white hover:bg-white/5">
                <svg x-show="!sidebarCollapsed" x-cloak class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 19l-7-7 7-7m8 14l-7-7 7-7"/></svg>
                <svg x-show="sidebarCollapsed" x-cloak class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 5l7 7-7 7M5 5l7 7-7 7"/></svg>
            </button>
        </div>

        <nav class="flex-1 overflow-y-auto py-3 space-y-1 px-2">
            <?php foreach ($navSections as $section): ?>
            <div x-show="!sidebarCollapsed" x-cloak class="admin-nav-section"><?= e($section['label']) ?></div>
            <?php foreach ($section['items'] as [$path, $label, $icon]):
                $isActive = ($path === '/admin')
                    ? (rtrim($current, '/') === url('/admin') || str_ends_with(rtrim($current, '/'), '/admin'))
                    : str_contains($current, $path);
            ?>
            <a href="<?= url($path) ?>" class="admin-nav-link <?= $isActive ? 'active' : '' ?>"
               :class="sidebarCollapsed ? 'justify-center' : ''"
               :title="sidebarCollapsed ? '<?= e($label) ?>' : ''">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="<?= $icon ?>"/></svg>
                <span x-show="!sidebarCollapsed" x-cloak class="truncate"><?= e($label) ?></span>
            </a>
            <?php endforeach; endforeach; ?>
        </nav>

        <div class="border-t border-white/5 p-3 flex items-center gap-2" x-show="!sidebarCollapsed" x-cloak>
            <div class="avatar avatar-sm flex-shrink-0"><?= e(\App\Models\User::initials($user)) ?></div>
            <div class="flex-1 min-w-0">
                <div class="font-semibold text-white text-xs truncate"><?= e(\App\Models\User::fullName($user)) ?></div>
                <div class="text-[10px] text-slate-500 truncate"><?= e($user['email'] ?? '') ?></div>
            </div>
            <form action="<?= url('/logout') ?>" method="POST">
                <?= csrf_field() ?>
                <button class="p-1.5 rounded-md text-rose-400 hover:bg-rose-500/10" title="Salir">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                </button>
            </form>
        </div>
    </aside>

    <!-- Mobile open button -->
    <button @click="open = true" class="lg:hidden fixed top-3 left-3 z-40 p-2 rounded-lg bg-white/10 backdrop-blur-xl text-white">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
    </button>

    <!-- Mobile sidebar drawer -->
    <div x-show="open" x-transition.opacity class="lg:hidden fixed inset-0 z-30 bg-black/70 backdrop-blur-sm" @click="open=false" x-cloak></div>
    <aside x-show="open" x-transition class="lg:hidden fixed left-0 top-0 bottom-0 z-40 w-72 bg-[#070C20] border-r border-white/10 overflow-y-auto" x-cloak>
        <div class="p-4 flex items-center justify-between border-b border-white/5">
            <span class="font-bold text-white"><?= e($brand) ?> · <span class="text-rose-400 text-xs">ADMIN</span></span>
            <button @click="open=false" class="p-1 text-slate-400">✕</button>
        </div>
        <nav class="p-3 space-y-1">
            <?php foreach ($navSections as $section): ?>
            <div class="admin-nav-section"><?= e($section['label']) ?></div>
            <?php foreach ($section['items'] as [$path, $label, $icon]): ?>
            <a href="<?= url($path) ?>" class="admin-nav-link">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="<?= $icon ?>"/></svg>
                <span><?= e($label) ?></span>
            </a>
            <?php endforeach; endforeach; ?>
        </nav>
    </aside>

    <!-- Main -->
    <main class="flex-1 flex flex-col min-w-0">
        <header class="sticky top-0 z-20 backdrop-blur-xl border-b border-white/5 bg-[#070C20]/70">
            <div class="px-6 h-14 flex items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <div class="text-xs text-slate-500">Panel de control</div>
                    <span class="text-slate-600">/</span>
                    <div class="text-sm font-semibold text-white"><?= e($_currentPageLabel ?? 'Super Admin') ?></div>
                </div>
                <div class="flex items-center gap-2">
                    <a href="<?= url('/dashboard') ?>" class="text-xs text-slate-400 hover:text-white px-3 py-1.5 rounded-lg hover:bg-white/5 transition flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                        Volver al CRM
                    </a>
                    <button @click="darkMode = !darkMode" class="p-2 rounded-lg text-slate-400 hover:text-white hover:bg-white/5 transition">
                        <svg x-show="darkMode" x-cloak class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                        <svg x-show="!darkMode" x-cloak class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/></svg>
                    </button>
                </div>
            </div>
        </header>

        <div class="px-6 py-6 flex-1">
            <?php \App\Core\View::include('components.flash'); ?>
            <?= \App\Core\View::section('content') ?>
        </div>
    </main>
</div>

</body>
</html>
