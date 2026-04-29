<?php
$user = auth() ?? [];
$page = $page ?? 'admin';
?>
<!DOCTYPE html>
<html lang="es" x-data="{ darkMode: localStorage.getItem('km') !== 'light' }" :class="{ 'dark': darkMode }">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= csrf_token() ?>">
    <title>Super Admin · <?= e((string) config('app.name', 'Kyros Pulse')) ?></title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">

    <script src="https://cdn.tailwindcss.com?plugins=forms"></script>
    <link rel="stylesheet" href="<?= asset('css/kyros.css') ?>">
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script defer src="https://unpkg.com/@alpinejs/collapse@3.x.x/dist/cdn.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script>
        if (localStorage.getItem('km') !== 'light') document.documentElement.classList.add('dark');
        else document.documentElement.classList.remove('dark');
        tailwind.config = { darkMode: 'class', theme: { extend: { fontFamily: { sans: ['Inter', 'system-ui', 'sans-serif'] }, colors: { primary: '#7C3AED', cyan: '#06B6D4' } } } };
    </script>
</head>
<body class="min-h-screen">

<header class="sticky top-0 z-30 backdrop-blur-xl" style="border-bottom: 1px solid var(--color-border-subtle); background: var(--color-bg-surface);">
    <div class="max-w-7xl mx-auto px-6 h-16 flex items-center justify-between gap-4">
        <a href="<?= url('/admin') ?>" class="flex items-center gap-2.5">
            <div class="w-9 h-9 rounded-xl flex items-center justify-center shadow-lg shadow-violet-500/20" style="background: var(--gradient-primary);">
                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
            </div>
            <div>
                <div class="font-bold leading-tight" style="color: var(--color-text-primary);">Kyros<span class="gradient-text">Pulse</span></div>
                <div class="flex items-center gap-1 leading-tight mt-0.5">
                    <span class="badge badge-rose text-[8px] px-1.5">SUPER ADMIN</span>
                </div>
            </div>
        </a>

        <nav class="hidden md:flex items-center gap-1 ml-6">
            <?php
            $tabs = [
                ['Dashboard', '/admin'],
                ['Empresas', '/admin/tenants'],
                ['Planes', '/admin/plans'],
                ['Logs', '/admin/logs'],
            ];
            $current = $_SERVER['REQUEST_URI'] ?? '';
            foreach ($tabs as [$label, $path]):
                $isActive = (str_ends_with($current, $path) || str_ends_with($current, $path . '/'))
                    || ($path === '/admin' && (str_ends_with($current, '/admin') || str_ends_with($current, '/admin/')));
            ?>
            <a href="<?= url($path) ?>" class="px-3 py-1.5 rounded-lg text-sm transition <?= $isActive ? 'font-semibold' : '' ?>"
               style="<?= $isActive ? 'background: var(--color-bg-active); color: var(--color-text-primary);' : 'color: var(--color-text-secondary);' ?>">
                <?= e($label) ?>
            </a>
            <?php endforeach; ?>
        </nav>

        <div class="flex items-center gap-2 ml-auto">
            <button @click="darkMode = !darkMode; localStorage.setItem('km', darkMode ? 'dark' : 'light')" class="btn btn-ghost btn-icon">
                <svg x-show="darkMode" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                <svg x-show="!darkMode" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/></svg>
            </button>
            <div class="w-px h-6" style="background: var(--color-border-subtle);"></div>
            <div class="flex items-center gap-2">
                <div class="avatar avatar-sm"><?= e(\App\Models\User::initials($user)) ?></div>
                <div class="hidden lg:block text-xs">
                    <div class="font-semibold leading-tight" style="color: var(--color-text-primary);"><?= e(\App\Models\User::fullName($user)) ?></div>
                    <div class="leading-tight" style="color: var(--color-text-tertiary);"><?= e($user['email'] ?? '') ?></div>
                </div>
            </div>
            <form action="<?= url('/logout') ?>" method="POST" class="ml-1">
                <?= csrf_field() ?>
                <button class="btn btn-ghost btn-sm text-rose-500 hover:bg-rose-500/10" title="Cerrar sesion">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                </button>
            </form>
        </div>
    </div>
</header>

<main class="max-w-7xl mx-auto px-6 py-8">
    <?php \App\Core\View::include('components.flash'); ?>
    <?= \App\Core\View::section('content') ?>
</main>

</body>
</html>
