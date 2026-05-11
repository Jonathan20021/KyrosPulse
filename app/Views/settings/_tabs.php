<?php
/**
 * Settings shell START — abre el layout con sidebar lateral y comienza el
 * area de contenido. Las subpaginas DEBEN cerrar con _tabs_end.php antes
 * de View::stop().
 *
 * Diseno: sidebar agrupado por categoria (Workspace / Canales / Inteligencia /
 * Restaurante / Plataforma), responsive (en mobile colapsa).
 *
 * @var string $tab  slug de la subpagina activa
 */
$tab = $tab ?? 'general';
$tenant = \App\Core\Tenant::current() ?? [];
$isRestaurant = !empty($tenant['is_restaurant']);

$groups = [
    [
        'label' => 'Workspace',
        'items' => [
            ['general',  'Empresa',          '/settings',           '🏢'],
            ['users',    'Usuarios y roles', '/settings/users',     '👥'],
            ['profile',  'Mi perfil',        '/settings/profile',   '👤'],
        ],
    ],
    [
        'label' => 'Canales',
        'items' => [
            ['channels',          'WhatsApp',       '/settings/channels',          '💬'],
            ['integrations_core', 'Wasapi & IA',    '/settings/integrations/core', '🔌'],
            ['integrations',      'Integraciones',  '/settings/integrations',      '🧩'],
            ['routing',           'Routing IA',     '/settings/routing',           '🧭'],
            ['notifications',     'Notificaciones', '/settings/notifications',     '🔔'],
        ],
    ],
    [
        'label' => 'Inteligencia',
        'items' => [
            ['ai',            'Agentes IA',  '/settings/ai',           '🧠'],
            ['quick_replies', 'Respuestas',  '/settings/quick-replies','⚡'],
        ],
    ],
    [
        'label' => 'Restaurante',
        'restaurant_only' => true,
        'items' => [
            ['restaurant', 'Operacion', '/settings/restaurant', '🍽'],
        ],
    ],
    [
        'label' => 'Plataforma',
        'items' => [
            ['security', 'Seguridad', '/settings/security', '🔐'],
            ['api',      'API keys',  '/settings/api-keys', '🔑'],
            ['webhooks', 'Webhooks',  '/settings/webhooks', '🪝'],
            ['alerts',   'Alertas',   '/settings/alerts',   '🚨'],
        ],
    ],
];
?>
<div x-data="{ navOpen: false }" class="set-shell">
    <!-- Mobile toggle (solo visible <1024px) -->
    <button type="button"
            class="set-mobile-toggle"
            @click="navOpen = !navOpen"
            :aria-expanded="navOpen"
            style="grid-column: 1 / -1;">
        <span>📂</span>
        <span>Menu de configuracion</span>
        <svg class="chev w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
    </button>

    <!-- Sidebar -->
    <aside class="set-side" :data-collapsed="!navOpen" data-collapsed="true">
        <?php foreach ($groups as $group):
            if (!empty($group['restaurant_only']) && !$isRestaurant) continue;
        ?>
        <div class="set-side-group">
            <div class="set-side-group-label"><?= e((string) $group['label']) ?></div>
            <?php foreach ($group['items'] as [$slug, $label, $path, $icon]):
                $isActive = $tab === $slug;
            ?>
            <a href="<?= url($path) ?>" class="set-side-item<?= $isActive ? ' is-active' : '' ?>">
                <span class="ico"><?= $icon ?></span>
                <span class="label"><?= e($label) ?></span>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
    </aside>

    <!-- Content area abierta — se cierra en settings/_tabs_end.php -->
    <main class="set-content">
