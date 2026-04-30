<?php
/** @var string $tab */
$tabs = [
    'general'        => ['Empresa',         '/settings',                '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>'],
    'channels'       => ['Canales WhatsApp', '/settings/channels',      '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>'],
    'integrations'   => ['Integraciones',   '/settings/integrations',   '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M13 10V3L4 14h7v7l9-11h-7z"/>'],
    'integrations_core' => ['Wasapi & IA',  '/settings/integrations/core', '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>'],
    'ai'             => ['IA',              '/settings/ai',             '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>'],
    'users'          => ['Usuarios',        '/settings/users',          '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>'],
    'quick_replies'  => ['Respuestas',      '/settings/quick-replies',  '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M13 10V3L4 14h7v7l9-11h-7z"/>'],
    'profile'        => ['Mi perfil',       '/settings/profile',        '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>'],
];
?>
<div class="surface p-1 mb-6 inline-flex items-center gap-0.5 overflow-x-auto scrollbar-thin">
    <?php foreach ($tabs as $key => [$label, $path, $icon]):
        $active = $key === ($tab ?? 'general');
    ?>
    <a href="<?= url($path) ?>" class="flex items-center gap-1.5 px-3 py-1.5 text-sm rounded-lg transition whitespace-nowrap <?= $active ? 'font-semibold' : '' ?>"
       style="<?= $active ? 'background: var(--color-bg-active); color: var(--color-text-primary);' : 'color: var(--color-text-secondary);' ?>">
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><?= $icon ?></svg>
        <?= e($label) ?>
    </a>
    <?php endforeach; ?>
</div>
