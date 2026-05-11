<?php
$user = auth() ?? [];
$tenant = tenant() ?? [];
$page = $page ?? 'dashboard';

// Modulos activos por tenant (controlados desde Super Admin)
$modRestaurant = !empty($tenant['is_restaurant']);

// Features bloqueadas por el plan actual — usado para mostrar candado en el sidebar.
// Cada item de nav tiene un 5to elemento opcional con el nombre de la feature requerida.
$canAi      = plan_can('ai_enabled');
$canApi     = plan_can('api_access');
$canReports = plan_can('advanced_reports');

// Estructura de navegacion del sidebar.
// Tupla: [slug, label, url, svg-path, optional feature-required]
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
];

// Modulo Restaurante: solo se muestra si el tenant lo tiene activo (Super Admin lo activa)
if ($modRestaurant) {
    $navSections[] = [
        'label' => 'Restaurante',
        'items' => [
            ['orders',      'Ordenes',            '/orders',      'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01'],
            ['kitchen',     'Cocina (KDS)',       '/kitchen',     'M3 8a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V8zm5 4a1 1 0 011-1h6a1 1 0 110 2H9a1 1 0 01-1-1zm-3-7v2m14-2v2'],
            ['menu',        'Menu',               '/menu',        'M3 3h18l-2 9H5L3 3zm2 9v8a2 2 0 002 2h10a2 2 0 002-2v-8M9 3v9m6-9v9'],
        ],
    ];
}

$navSections[] = [
    'label' => 'Crecimiento',
    'items' => [
        ['campanas',          'Campanas',           '/campaigns',     'M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z'],
        ['automatizaciones',  'Automatizaciones',   '/automations',   'M13 10V3L4 14h7v7l9-11h-7z'],
        ['workflows',         'Workflows',          '/workflows',     'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4', 'advanced_reports'],
        ['reportes',          'Reportes',           '/reports',       'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z', 'advanced_reports'],
    ],
];

$navSections[] = [
    'label' => 'Sistema',
    'items' => [
        ['ai_usage',      'Uso de IA',     '/ai/usage', 'M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z', 'ai_enabled'],
        ['configuracion', 'Configuracion', '/settings', 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z'],
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
    <title><?= e(ucfirst($page)) ?> · <?= e((string) config('app.name', 'Evallish Pulse')) ?></title>

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

        /* Sidebar oscuro permanente tipo respond.io */
        .sidebar-shell {
            background: var(--sidebar-bg);
            border-right: 1px solid var(--sidebar-border);
            color: var(--sidebar-text);
            position: relative;
        }
        .sidebar-shell::after {
            content: '';
            position: absolute;
            inset: 0;
            background:
                radial-gradient(at 0% 0%, rgba(16, 185, 129, 0.08) 0px, transparent 35%),
                radial-gradient(at 100% 100%, rgba(79, 70, 229, 0.07) 0px, transparent 30%);
            pointer-events: none;
            z-index: 0;
        }
        .sidebar-shell > * { position: relative; z-index: 1; }

        .topbar-shell {
            background: var(--color-bg-surface);
            border-bottom: 1px solid var(--color-border-subtle);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
        }
        .dark .topbar-shell { background: rgba(11, 18, 32, 0.85); }

        /* Search trigger en sidebar oscuro */
        .search-trigger {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.55rem 0.75rem;
            font-size: 0.8125rem;
            background: rgba(255,255,255,0.04);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 0.55rem;
            color: var(--sidebar-text-muted);
            transition: all .16s;
            width: 100%;
            cursor: pointer;
        }
        .search-trigger:hover {
            background: rgba(255,255,255,0.07);
            color: var(--sidebar-text-active);
            border-color: rgba(255,255,255,0.14);
        }
        .search-trigger .kbd {
            background: rgba(255,255,255,0.06);
            border-color: rgba(255,255,255,0.12);
            color: var(--sidebar-text-muted);
            box-shadow: none;
        }

        /* Search trigger en topbar (light/dark variantes) */
        .search-trigger-top {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.55rem 0.75rem;
            font-size: 0.8125rem;
            background: var(--color-bg-subtle);
            border: 1px solid var(--color-border-subtle);
            border-radius: 0.55rem;
            color: var(--color-text-tertiary);
            transition: all .16s;
            width: 100%;
            cursor: pointer;
        }
        .search-trigger-top:hover {
            background: var(--color-bg-hover);
            color: var(--color-text-primary);
            border-color: var(--color-border-default);
        }

        /* Brand container */
        .brand-mark {
            width: 36px; height: 36px;
            border-radius: 11px;
            background: var(--gradient-primary);
            box-shadow: 0 8px 22px rgba(16, 185, 129, 0.40), inset 0 1px 0 rgba(255,255,255,0.18);
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
        }

        .topbar-icon-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.5rem;
            border-radius: 0.55rem;
            color: var(--color-text-secondary);
            transition: all .15s;
        }
        .topbar-icon-btn:hover {
            background: var(--color-bg-subtle);
            color: var(--color-text-primary);
        }

        /* User pill */
        .user-pill {
            display: flex;
            align-items: center;
            gap: 0.55rem;
            padding: 0.3rem 0.55rem 0.3rem 0.3rem;
            border-radius: 999px;
            background: var(--color-bg-subtle);
            border: 1px solid var(--color-border-subtle);
            transition: all .15s;
        }
        .user-pill:hover {
            background: var(--color-bg-hover);
            border-color: var(--color-border-default);
        }

        /* Trial banner glow */
        .trial-card {
            position: relative;
            border-radius: 14px;
            padding: 0.85rem 0.95rem;
            background: linear-gradient(135deg, rgba(16,185,129,0.16), rgba(79,70,229,0.10));
            border: 1px solid rgba(16,185,129,0.30);
            overflow: hidden;
        }
        .trial-card::after {
            content: '';
            position: absolute;
            inset: -40%;
            background: radial-gradient(circle at top right, rgba(16,185,129,0.30), transparent 60%);
            pointer-events: none;
        }
        .trial-card > * { position: relative; }
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
        <div class="h-16 flex items-center justify-between gap-2 px-4 border-b" style="border-color: var(--sidebar-border);">
            <a href="<?= url('/dashboard') ?>" class="flex items-center gap-2.5 min-w-0">
                <div class="brand-mark" style="background: #FFFFFF; padding: 4px; box-shadow: 0 4px 14px rgba(37, 99, 235, 0.25);">
                    <img src="<?= asset('css/logo.png') ?>" alt="Evallish Pulse" class="w-full h-full object-contain">
                </div>
                <div x-show="!sidebarCollapsed" class="min-w-0" x-cloak>
                    <div class="font-bold text-[14px] leading-tight" style="color:#FFFFFF; letter-spacing:-0.01em;">Evallish<span class="gradient-text">Pulse</span></div>
                    <div class="text-[10px] leading-tight truncate mt-0.5" style="color: var(--sidebar-text-muted);"><?= e($tenant['name'] ?? 'Tu empresa') ?></div>
                </div>
            </a>
            <button @click="sidebarCollapsed = !sidebarCollapsed" x-show="!sidebarCollapsed" x-cloak
                    class="hidden lg:inline-flex w-7 h-7 rounded-md items-center justify-center flex-shrink-0 transition" style="color: var(--sidebar-text-muted); background: transparent;" onmouseover="this.style.background='rgba(255,255,255,.07)';this.style.color='#FFF'" onmouseout="this.style.background='transparent';this.style.color='var(--sidebar-text-muted)'" title="Colapsar">
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
            <button @click="sidebarCollapsed = false" class="w-full h-10 flex items-center justify-center rounded-md transition" style="color: var(--sidebar-text-muted); background: rgba(255,255,255,0.04);" onmouseover="this.style.background='rgba(255,255,255,.08)';this.style.color='#FFF'" onmouseout="this.style.background='rgba(255,255,255,0.04)';this.style.color='var(--sidebar-text-muted)'" title="Expandir">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 5l7 7-7 7M5 5l7 7-7 7"/></svg>
            </button>
        </div>

        <!-- Navigation -->
        <nav class="flex-1 overflow-y-auto scrollbar-thin px-3 pt-2 pb-4">
            <?php foreach ($navSections as $section): ?>
                <div class="nav-section-label" x-show="!sidebarCollapsed" x-cloak><?= e($section['label']) ?></div>
                <?php foreach ($section['items'] as $item):
                    $isActive = ($page === $item[0]);
                    $requiredFeature = $item[4] ?? null;
                    $isLocked = $requiredFeature && !plan_can($requiredFeature);
                ?>
                <a href="<?= url($item[2]) ?>" class="nav-link <?= $isActive ? 'active' : '' ?>" :title="sidebarCollapsed ? '<?= e($item[1]) ?>' : ''" :class="sidebarCollapsed ? 'justify-center' : ''" style="<?= $isLocked ? 'opacity:0.55;' : '' ?>">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="<?= $item[3] ?>"/></svg>
                    <span class="truncate" x-show="!sidebarCollapsed" x-cloak><?= e($item[1]) ?></span>
                    <?php if ($isLocked): ?>
                    <span x-show="!sidebarCollapsed" x-cloak class="ml-auto" title="Requiere actualizar plan">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                    </span>
                    <?php endif; ?>
                </a>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </nav>

        <!-- Trial / Plan banner -->
        <?php if (!empty($tenant['trial_ends_at']) && ($tenant['status'] ?? '') === 'trial'):
            $daysLeft = max(0, (int) floor((strtotime($tenant['trial_ends_at']) - time()) / 86400));
        ?>
        <div class="p-3" x-show="!sidebarCollapsed" x-cloak>
            <div class="trial-card">
                <div class="flex items-center gap-2 mb-1.5">
                    <span class="w-6 h-6 rounded-md flex items-center justify-center" style="background: rgba(16,185,129,0.20);">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="#34D399" viewBox="0 0 24 24" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    </span>
                    <span class="text-xs font-semibold" style="color: #FFFFFF;">Periodo de prueba</span>
                </div>
                <div class="flex items-baseline justify-between mb-2">
                    <span class="text-[10px]" style="color: var(--sidebar-text-muted);">Te quedan</span>
                    <span class="text-base font-bold" style="color: #34D399;"><?= $daysLeft ?> dias</span>
                </div>
                <div class="h-1 rounded-full overflow-hidden mb-2" style="background: rgba(255,255,255,0.08);">
                    <div class="h-full rounded-full" style="width: <?= max(5, min(100, $daysLeft * 100 / 14)) ?>%; background: linear-gradient(90deg, #34D399, #10B981);"></div>
                </div>
                <a href="<?= url('/settings') ?>" class="block text-[11px] font-semibold hover:underline" style="color: #6EE7B7;">Actualizar plan →</a>
            </div>
        </div>
        <?php endif; ?>

        <!-- Footer del sidebar: usuario rapido -->
        <div class="mt-auto px-3 pb-3 pt-2" x-show="!sidebarCollapsed" x-cloak>
            <div class="flex items-center gap-2.5 px-2.5 py-2 rounded-lg transition" style="background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.06);">
                <div class="avatar avatar-sm"><?= e(\App\Models\User::initials($user)) ?></div>
                <div class="min-w-0 flex-1">
                    <div class="text-[11.5px] font-semibold truncate" style="color: #FFFFFF;"><?= e(\App\Models\User::fullName($user)) ?></div>
                    <div class="text-[10px] truncate" style="color: var(--sidebar-text-muted);"><?= e(mb_substr((string) ($user['email'] ?? ''), 0, 26)) ?></div>
                </div>
                <span class="status-dot status-online flex-shrink-0"></span>
            </div>
        </div>
    </aside>

    <!-- Backdrop mobile -->
    <div x-show="sidebarOpen" @click="sidebarOpen = false" x-transition class="lg:hidden fixed inset-0 bg-black/60 backdrop-blur-sm z-30" x-cloak></div>

    <!-- ====================== MAIN ====================== -->
    <div class="flex-1 flex flex-col min-w-0">

        <!-- TOPBAR -->
        <header class="topbar-shell sticky top-0 z-30">
            <div class="flex items-center justify-between gap-3 px-4 lg:px-6 h-16">
                <button @click="sidebarOpen = !sidebarOpen" class="lg:hidden topbar-icon-btn -ml-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>

                <button type="button" @click="cmdOpen = true" class="search-trigger-top flex-1 max-w-md hidden md:flex">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <span class="flex-1 text-left">Buscar contactos, conversaciones...</span>
                    <span class="kbd">⌘K</span>
                </button>

                <div class="flex items-center gap-1 ml-auto">
                    <button @click="darkMode = !darkMode" class="topbar-icon-btn" :title="darkMode ? 'Modo claro' : 'Modo oscuro'">
                        <svg x-show="darkMode" class="w-[18px] h-[18px]" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                        <svg x-show="!darkMode" class="w-[18px] h-[18px]" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/></svg>
                    </button>

                    <button class="topbar-icon-btn hidden sm:inline-flex" title="Ayuda">
                        <svg class="w-[18px] h-[18px]" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </button>

                    <div class="relative" x-data="inboxNotifications()" x-init="start()" @click.outside="open = false">
                        <button @click="open = !open; if (open) markSeen()" class="topbar-icon-btn relative" :title="unread > 0 ? unread + ' mensajes nuevos' : 'Notificaciones'">
                            <svg class="w-[18px] h-[18px]" :class="ringing ? 'bell-ring' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                            <span x-show="unread > 0" x-cloak class="absolute -top-0.5 -right-0.5 min-w-[18px] h-[18px] px-1 rounded-full text-[10px] font-bold text-white flex items-center justify-center"
                                  style="background:#F43F5E; box-shadow: 0 0 0 2px var(--color-bg-surface);">
                                <span x-text="unread > 99 ? '99+' : unread"></span>
                            </span>
                        </button>
                        <div x-show="open" x-transition x-cloak class="absolute right-0 mt-2 w-[360px] rounded-xl shadow-xl border overflow-hidden z-50" style="background: var(--color-bg-elevated); border-color: var(--color-border-default);">
                            <div class="px-4 py-3 border-b flex items-center justify-between" style="border-color: var(--color-border-subtle);">
                                <div class="flex items-center gap-2">
                                    <h3 class="font-semibold text-sm" style="color: var(--color-text-primary);">Bandeja</h3>
                                    <span x-show="unread > 0" x-cloak class="text-[10px] font-bold px-1.5 py-0.5 rounded-full" style="background: rgba(244,63,94,.12); color:#E11D48;">
                                        <span x-text="unread"></span> nuevos
                                    </span>
                                </div>
                                <a href="<?= url('/inbox') ?>" class="text-xs font-medium" style="color: var(--color-primary);">Ver todo →</a>
                            </div>
                            <div class="max-h-[420px] overflow-y-auto scrollbar-thin">
                                <template x-if="items.length === 0">
                                    <div class="text-center py-10">
                                        <div class="text-3xl mb-2 opacity-50">✅</div>
                                        <p class="text-xs" style="color: var(--color-text-tertiary);">Estas al dia. Sin mensajes nuevos.</p>
                                    </div>
                                </template>
                                <template x-for="it in items" :key="it.id">
                                    <a :href="'<?= url('/inbox/') ?>' + it.id" class="flex items-start gap-3 px-4 py-3 border-b transition" style="border-color: var(--color-border-subtle);"
                                       onmouseover="this.style.background='var(--color-bg-hover)'"
                                       onmouseout="this.style.background='transparent'">
                                        <div class="avatar avatar-sm flex-shrink-0" :style="'background: linear-gradient(135deg,' + it.channel_color + ',' + it.channel_color + 'aa);'" x-text="it.initial"></div>
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-center justify-between gap-2 mb-0.5">
                                                <span class="font-semibold text-[13px] truncate" style="color: var(--color-text-primary);" x-text="it.name"></span>
                                                <span class="text-[10px] flex-shrink-0 font-mono" style="color: var(--color-text-muted);" x-text="formatTime(it.last_message_at)"></span>
                                            </div>
                                            <div class="text-[12px] line-clamp-2" style="color: var(--color-text-secondary);" x-text="it.last_message"></div>
                                        </div>
                                        <span x-show="it.unread_count > 0" class="text-[10px] font-bold w-5 h-5 rounded-full flex items-center justify-center text-white flex-shrink-0" style="background: var(--color-primary);" x-text="it.unread_count > 9 ? '9+' : it.unread_count"></span>
                                    </a>
                                </template>
                            </div>
                        </div>
                    </div>

                    <div class="w-px h-6 mx-2" style="background: var(--color-border-subtle);"></div>

                    <div class="relative" @click.outside="userMenu = false">
                        <button @click="userMenu = !userMenu" class="user-pill">
                            <div class="avatar avatar-sm"><?= e(\App\Models\User::initials($user)) ?></div>
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
                ['Uso de IA · Token economy', '/ai/usage', '🤖'],
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

<!-- Audio ping para mensajes nuevos (no bloquea autoplay porque viene tras user gesture / despues de polling) -->
<audio id="kp-ping" preload="auto" style="display:none">
    <!-- Sonido corto codificado en base64 (chime suave ~200ms) -->
    <source src="data:audio/wav;base64,UklGRpQGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YXAGAACAg4eKjY+RkpKSkZCOjImGgn58eHRwbWlmZGFfXl1cXFxcXV5gYWNlZ2pscG90eHt+goWIi42PkJGSkpGQjoyJh4N/e3dzcGxpZmRhYF5dXFxcXF1eYGFjZmhqbXBzdnp9gYSHio2OkJGSkpGQj42KiIWBfnp2cm9ramVjYWBeXVxcXFxdXl9hY2VnamxvcnV4fH+ChomMjo+RkpKSkY+OjImGgn99eXVxbWpnZGJgX11dXFxcXV5fYWNkZ2lscG90d3p+gYWHio2Oj5GRkpGQjo2LiIWCfnp2c29sZ2RiYF5dXVxcXFxdX2BiZGZoamxvcnV4e36ChIeKjI6PkZGRkZCPjYuJhoN/e3h0cGxoZWNgX11cXFxcXF5fYGJkZWhqbG9ydXh7foGEhomLjY6Pj4+Pjo2LiIWCgHt4dXFta2hkYV9eXFxcXFxdXl9hY2RmaWtucHN1eHx/goWIi42Oj4+PkI+OjYuIhYKAfHl2cm9raWZjYV9eXVxcXFxeX2BiY2VnaWxucXN2eXt+gYSGiYqMjo+Pj4+OjYuJhoR/fHl2cm9samdkYV9eXVxcXFxeXmBhY2VnaGttb3FzdnZ5e36AgoSHiYuNjo6Pj46OjYuJh4SCfXp3dHFua2hlY2FeXV1cXFxcXl9gYmRmaGptb3J0d3l8foGDhYeKjI2OjY6OjY2LiomGhIB9end0cm9samhmZGJgXl1dXFxcXF1fYGJjZWdpa21wc3R3en1/g4SGiYuMjY6OjY2NjIqIh4SBfn1, 6dXJwbWtpZ2RiYF9eXl1dXV1eXmBhY2RmaGptb3F0dnl7foGDhYeJio6OjY2NjI2NjImIhYR/fnp4dXJwbmxqaGZkYV9eXl1dXV1eXmBgYWNkZ2hqbW9xc3V4ent+gIODhYiKi42OjY2NjI2NjIuJiIaCgX59enh2c3FvbWtpZ2VkYV9eXV1dXV1eXl5fYGFjZGZna2xucHJ0d3l8f4GDhIaJi4yMjY2NjYyMjIuKiYaFgYB+e3l3dHJxbmxraWdlZGNgXl9eXV1dXl5eXmBgYmRkZmlrbW5wcnV3eHt9gIKEhYeKioyNjY2NjYyMjIuJiIeFhYJ+fHt4dnRycG5tamhnZWRiYV9eXl1dXV5eXl9gYWFjZGZna2xucHJ0d3l7fYCBg4WHiYqLjI2NjYyNjIyLiomIhoWCgX9+e3l3dHJxbmxsamhnZmRjYV9eXl1dXV5eXl9gYWJkZGZna2tucHJ0dXh6fYCBg4WHiImLjI2NjY2MjIyMioqJh4WFg4F+fXt5d3VzcW9tbGtoZ2VkYmFfXl5dXV1eXl5fYGFiZGRmZ2lrbG5wcnR1eHp8f4CCg4WHiYqLjI2NjY2NjIuLiomJh4WEgoB/fXp4dnVycXBubGtpaGZkY2JhX19eXV1dXV5eXmBhYWNkZmdpamxucHJzdHd5e35/gYOFh4iKi4yMjI2MjIuLiomKiYiGhYKBf317eXd1c3JwbmxraWdmZGNiYWBfXl1dXl1eXl5gYGFiZGZnZ2lqbG5wcnR1d3l7fX+AgoSGh4iKi4uMjI2NjIuLiomJiIeGhIKBf316eXd2c3JwbmxsaWlmZGNiYWBfXl5dXl5eXl9gYWNkZmZpaWttcHFzdXZ4ent9foCCg4WHiImKi4yMjIuMi4uKiYmIh4eFhIKBf316eHZ1c3JwbW1raWdmZWNiYWBeXl5dXV5eXl9gYWNkZWdpamxucXJzdXd4ent9f4CChIWHiImLi4yMjIuLi4uKioiHhoWEgoF+fXt5d3Z0cnBubmxraGdlZWNiYWBfXl1dXl1eXl9gYWJjZWZoaWtsbW9wcnR2eHp8f4CChIWHiYqKi4uLi4uLioqIh4aFhIKBf316eXd2dHJxb25tamhmZWRkYmFgX19eXV1eXl5fX2FiYmRlZ2hqamxucHFydXd5e3x/gIKEhoeIiYqKioqKiYmJiIeGhYSCgYB+fHp4dnVzcW9tbGtpaGZkY2JhYF9eXl1dXV5eXmBhYWJkZWZoamttbnBxc3V3eHt9f4CChIaHiImJiYmJiYmIiIeHhoWEg4GAf317eXh2c3FwbmxraWhmZGNiYWBfX15dXV1dXl5fYGFhY2RlZ2lrbW5vcXJ0dnh6fH6AgoSFh4iJiYmJiYmJiIeHhoWEg4KBgH59e3l3dnRycG5tbGppaGZkY2JgX19eXV1dXV1eXmBgYWNkZWZoam1ub3BydXZ4eXt+gIKDhYeIiYmJiYmJiIeHhoWEg4OCgYB+fHp4d3VzcW9tbGppZ2ZkYmFgX19eXV1dXV1eXmBgYWNkZWZoaGptbnBydHV4eXt9f4GDhIeIiYmJiYmIiIeHhoWEg4OCgYB+fXt5d3VzcXBubWppZ2ZkY2JhX19eXV1dXV1eXl9gYWJkZmZoamxtb3FydHV3eXt9f4GDhYaIiImJiYmIiIaGhYWEg4KCgX9+fHp4d3Vzc3FvbWxraGZlY2JhYF9eXl1dXV1dXl5fYGFiYmRlZ2hqa21vcXN1dnl7fX+ChIWGiImJiYmIiIeGhoWEg4KCgYB/fXx5d3VzcW9tbGtpaGZkY2JhYF9eXl5dXV1eXl5fX2BhY2RlZ2hpa21wcnN1d3p7fH9/g4OFhoeIiIiIh4eGhoWEhIODgoF/fnx7eXd1c3FvbWtramdmZGNhYWBeXV1dXV1dXl5fYGBhYmRmZ2hqbG5vcXN1dnl6fX5/goSFhoeIiIiIh4eHhoWEhIODgoB/fnx6eXh1c3FwbmxqaWlnZWRiYWBfXl5dXV1dXV5fX2BhY2NlZ2hqbG5vcXN1d3l7fX5/gYKEhYaHh4iIh4eGhoWFhIODgoB/fnx7eHh2c3FwbmxraWloZmRjYWBfXl5dXV1dXV5eX2BhYmNlZmdpa21vcHJ0dnh6fH5/gIKEhYaHh4eHh4eGhoWFhIODgoF/fXx7eHd2c3JwbmxsamhnZmRjYWBfXl5dXV1dXV5eX2BhYmNlZmhqamttb3FydHZ3en1+gIGDhIWHh4eHh4eGhoWFhISDgoKBgH59fHt4d3VzcW9ubWxqaGdmZGNhYF9eXl5dXV1dXl5fYGFiZGVmaGlrbG9wcnR2eHp7fX+Ag4SFhoeHh4eGhoaGhYSDg4KCgX9+fXt5eHZ0cnBubW1raGdlY2NhYF9eXl1dXV1eXl9gYWJiZGZnaWttbnBxc3V3eXt9foCCg4WGhoeHh4eGhoaGhYSDg4OCgYB+fXt6eHZ1c3FvbW1raGdmZWJhYF9eXl1dXV1dXl5fYGFiY2VmaGlrbW5wcXN1d3l7fX+AgoOFhoaHh4eGhoaFhYSEg4ODgYB/fXt6eHd1c3FvbWxqaWhmZGNhYF9eXl1dXV1eXl9gYWJjZGVnaGptbW9xcnR2eHp8fn+Bg4SFhoeHh4aGhoaFhIOCgYF/f316eXh2dXNxb21sa2loZ2VjYmFgX15eXV1dXV1eX2BhYmNlZmZoaWttb3BydHZ4ent9f4CCg4WGhoaHh4aGhoWFhISDg4OBgH9+fHp4dnVzcW9ubGppZ2VkY2JhX19eXV1dXV1eXmBgYWJjZWZnaWttb29ycnV3eXp8fn+Bg4OFhoaGhoaGhoWFhISDg4KBgH99fHt4dnZ0cnBubGppaWdmZWNiYWBfXl1dXV1dXV5eX2BhYmNlZmZoaWttb3FydXZ4ent9f4GCg4SGhoaGhoaGhYWEhIODgoGAf358e3p3dnVzcG9tbGppaGZlY2JhYF9eXl1dXV1eXl5fYGFhY2RmZmhpa2xtb3FydXZ4ent9f4GBg4SFhoaGhoaFhYSEg4OCgoGAf317enh3dnRycG5tbGloZ2VkY2FgYF9eXV1dXV1dXl9gYWJiZGVnaGlrbG9wcnN2eHl7fX9/gYKEhIWGhoaGhoWFhISDg4KCgYB+fXt5eHd1c3JwbmxraWloZmRjYWBfX15dXV1dXV5eXl9gYWJjY2VmaGlrbm5xcnR2eHl7fX5/gYOEhYWGhoaGhoaFhIODgoKCgYB/fnt6eXd1c3FwbmxsamlnZmRjYWBfX15dXV1dXl5fX2BhYmNlZmZoamttbnByc3V3eXt9f4CBgoOEhYaGhoaGhYWEhIODgoKBgH9+fHt5d3Z1c3FwbWxraWhnZWNjYWBfX15dXV1dXl5eXmBhYmJjZWZoaWttbnByc3V3eXt9foCBg4OFhYaGhoaGhYSEg4OCgoGAf358end2dXNxb21saWloZmVjYmFgX19eXV1dXV1eXl9gYGJjZGVmaGhqa21vcHJzdnh5e31/gIKDhISFhoaGhYWFhISDg4KCgYB/fnx6eXh2dHFwbm1samhnZWNiYWBfX15dXV1dXV5eX2BhYmNkZWZoaWtub3BycnV2eHp8fn+Ag4OEhYWGhoaFhYSEg4OCgYGAf358enl4dnRzcW9tbGppaGdlZGNhYF9fXl1dXV1dXV5fYGFhYmNlZmdpamxtb3BydXZ4ent9f4CCg4SFhoaGhoaFhYSEg4ODgoGBgH59e3p4dnRzcW9tbGppZ2ZlY2JhYF9eXV1dXV1dXV5fYGBhYmRlZmhpa2xtbnByc3V3eXt9f4CCg4SFhYaGhoaGhYSEg4OCgoKBgH9+fHt5dnVzcXBubWxqaGdlZGJhYF9fXl1dXV1dXV5eX2BhYWNkZWZoaWttbnByc3R3eHp8fX+AgoOEhYaGhoaGhYWEhIODgoKBgYB/fnt6eHd1c3FwbmxraGdmZGNiYV9fXl1dXV1dXV5eX2BhYmNkZWdpaWttbnFyc3V3eXt9f4CChISFhoaGhoaGhYSDg4OCgoGAgH99e3p4dnRzcW5tbGloZ2VkY2FgX19eXV1dXV1eXl5fYGFiY2RlZ2hpa21vcHJ0dXh5e31+gIKDhIWGhoaGhoWFhISDg4KCgYGAfnx7enh2dXNxb21samhnZWRjYmBgX15eXV1dXV5eXl9gYWJjZGVmZ2lqbG1vcHJ0dnh6e31/gYKDhISFhoaGhoWFhISDg4OCgYGAf358enl3dnRycG5sa2lnZmVjYmBgX15eXV1dXV1eXl9gYWFiY2VmZ2hqa21ucHFzdXd4en1+gIGCg4SFhoaGhoWFhYSEg4OCgoGAfn58e3l3dXRyb21taWhmZWRjYWFgXl5eXV1dXV1eXl9fYGFhY2RlZ2hqa21ucHFzdXd5e31+f4GCg4SFhYaGhoaFhYSEg4OCgoGBgH9+fHp5d3VzcXBubGppaWdlZGJhYV5eXl1dXV1dXl5fX2BhYmNkZWZoaWttb3BydHZ3enx9f4CChIWFhoaGhoaFhYSEg4OCgoGAf358e3l4dnRzcW9tbGppZ2ZlY2FhYF5eXl1dXV1dXl5fYGBhYmNkZWZoaWttb3BydHZ4ent9f4CChIQ=" type="audio/wav">
</audio>

<script>
// ============================================================================
//  Inbox Notifications — global polling para topbar
//  Funciona en CUALQUIER pagina del app (dashboard, contactos, leads, etc.)
//  Polling cada 12s. En la pagina /inbox se reduce la frecuencia (lo maneja la
//  pagina misma con su propio polling de 3s y aqui es solo decorativo).
// ============================================================================
window.inboxNotifications = function () {
    return {
        unread: 0,
        items: [],
        open: false,
        ringing: false,
        _lastSeen: parseInt(localStorage.getItem('kp_lastSeenUnread') || '0', 10),
        _baseTitle: document.title,
        _intervalId: null,
        _seenIds: new Set(),

        async fetchOnce() {
            try {
                const res = await fetch('<?= url('/inbox/notifications') ?>', { credentials: 'same-origin', headers: { 'Accept': 'application/json' } });
                if (!res.ok) return;
                const data = await res.json();
                if (!data || !data.success) return;

                const previousUnread = this.unread;
                this.unread = data.unread_total | 0;
                this.items = Array.isArray(data.items) ? data.items : [];

                // Detectar nuevos: si el total subio, ringear y sonar
                if (this.unread > previousUnread && previousUnread !== 0) {
                    this.notifyNew();
                }

                // Mantener title con contador
                document.title = this.unread > 0 ? '(' + this.unread + ') ' + this._baseTitle : this._baseTitle;

                // Favicon dot (verde si hay unread)
                this.updateFavicon();
            } catch (e) {
                // silencio: pueden ser errores transitorios de red o sesion expirada
            }
        },

        notifyNew() {
            this.ringing = true;
            setTimeout(() => { this.ringing = false; }, 1400);
            try {
                const a = document.getElementById('kp-ping');
                if (a) { a.currentTime = 0; a.volume = 0.45; a.play().catch(() => {}); }
            } catch (e) {}
            // Notificacion del navegador si tiene permiso
            if (window.Notification && Notification.permission === 'granted' && this.items.length > 0) {
                const top = this.items[0];
                try {
                    const n = new Notification('Nuevo mensaje · ' + top.name, {
                        body: top.last_message || 'Tienes mensajes nuevos',
                        icon: '/favicon.ico',
                        tag: 'kp-inbox-' + top.id,
                    });
                    n.onclick = () => { window.location.href = '<?= url('/inbox/') ?>' + top.id; n.close(); };
                } catch (e) {}
            }
        },

        markSeen() {
            this._lastSeen = Date.now();
            localStorage.setItem('kp_lastSeenUnread', String(this._lastSeen));
        },

        updateFavicon() {
            // Crea dinamicamente un favicon con dot verde si hay unread
            const link = document.querySelector("link[rel*='icon']") || document.createElement('link');
            link.type = 'image/svg+xml';
            link.rel = 'icon';
            const dot = this.unread > 0 ? '<circle cx="48" cy="16" r="14" fill="#F43F5E"/>' : '';
            const svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><rect width="64" height="64" rx="14" fill="#0EA572"/><path d="M28 22 L20 36 H30 V46 L42 30 H32 V22 Z" fill="#fff"/>' + dot + '</svg>';
            link.href = 'data:image/svg+xml;base64,' + btoa(svg);
            document.head.appendChild(link);
        },

        start() {
            this.fetchOnce();
            this._intervalId = setInterval(() => {
                if (!document.hidden) this.fetchOnce();
            }, 12000);
            // Pedir permiso de notificaciones la primera vez que el usuario abre el menu
            const askOnce = () => {
                if (window.Notification && Notification.permission === 'default') {
                    Notification.requestPermission().catch(() => {});
                }
                document.removeEventListener('click', askOnce);
            };
            document.addEventListener('click', askOnce, { once: true });
        },

        formatTime(ts) {
            if (!ts) return '';
            const d = new Date(ts.replace(' ', 'T'));
            if (isNaN(d.getTime())) return '';
            const diff = (Date.now() - d.getTime()) / 1000;
            if (diff < 60) return 'ahora';
            if (diff < 3600) return Math.floor(diff / 60) + 'm';
            if (diff < 86400) return Math.floor(diff / 3600) + 'h';
            return Math.floor(diff / 86400) + 'd';
        },
    };
};
</script>

<style>
@keyframes bell-ring {
    0%, 100% { transform: rotate(0); }
    10%, 30%, 50%, 70%, 90% { transform: rotate(-18deg); }
    20%, 40%, 60%, 80% { transform: rotate(18deg); }
}
.bell-ring { animation: bell-ring 1.2s ease-in-out; transform-origin: 50% 4px; }
.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
</style>

</body>
</html>
