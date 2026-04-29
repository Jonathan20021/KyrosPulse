<?php
/** @var array $contacts */
/** @var array $filters */
/** @var \App\Core\Paginator $paginator */
/** @var array $agents */
/** @var array $allTags */
/** @var int $total */
\App\Core\View::extend('layouts.app');
\App\Core\View::start('content');
?>

<!-- Header premium -->
<div class="mb-7 flex flex-wrap items-end justify-between gap-4">
    <div>
        <div class="flex items-center gap-2 mb-1.5">
            <span class="text-[11px] font-semibold uppercase tracking-[0.12em]" style="color: var(--color-text-tertiary);">CRM · Contactos</span>
        </div>
        <h1 class="text-[28px] font-bold tracking-tight" style="color: var(--color-text-primary); letter-spacing: -0.025em;">Contactos</h1>
        <p class="text-sm mt-1" style="color: var(--color-text-tertiary);"><?= number_format($total) ?> contacto<?= $total === 1 ? '' : 's' ?> en tu base de datos.</p>
    </div>
    <div class="flex items-center gap-2">
        <a href="<?= url('/contacts/import') ?>" class="btn btn-secondary">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
            Importar
        </a>
        <a href="<?= url('/contacts/export') ?>" class="btn btn-secondary">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
            Exportar
        </a>
        <a href="<?= url('/contacts/create') ?>" class="btn btn-primary">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
            Nuevo contacto
        </a>
    </div>
</div>

<!-- Filtros card premium -->
<div class="surface p-4 mb-4">
    <form method="GET" class="grid grid-cols-1 md:grid-cols-12 gap-3">
        <div class="md:col-span-5 relative">
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 pointer-events-none" style="color: var(--color-text-tertiary);" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            <input type="text" name="q" value="<?= e((string) $filters['q']) ?>" placeholder="Buscar por nombre, email, telefono..." class="input pl-10 text-sm">
        </div>
        <select name="status" class="select md:col-span-2 text-sm">
            <option value="">Todos los estados</option>
            <?php foreach (['lead'=>'Lead','active'=>'Activo','inactive'=>'Inactivo','vip'=>'VIP','blocked'=>'Bloqueado'] as $v=>$lab): ?>
            <option value="<?= $v ?>" <?= $filters['status'] === $v ? 'selected' : '' ?>><?= $lab ?></option>
            <?php endforeach; ?>
        </select>
        <select name="agent" class="select md:col-span-2 text-sm">
            <option value="">Cualquier responsable</option>
            <?php foreach ($agents as $a): ?>
            <option value="<?= (int) $a['id'] ?>" <?= ((int) $filters['agent']) === (int) $a['id'] ? 'selected' : '' ?>><?= e($a['first_name'] . ' ' . $a['last_name']) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="tag" class="select md:col-span-2 text-sm">
            <option value="">Cualquier etiqueta</option>
            <?php foreach ($allTags as $t): ?>
            <option value="<?= e($t['name']) ?>" <?= $filters['tag'] === $t['name'] ? 'selected' : '' ?>><?= e($t['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <div class="md:col-span-1 flex gap-1">
            <button type="submit" class="btn btn-primary btn-sm flex-1" title="Aplicar filtros">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
            </button>
            <a href="<?= url('/contacts') ?>" class="btn btn-ghost btn-icon" title="Limpiar"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg></a>
        </div>
    </form>

    <?php if ($paginator->total !== $total): ?>
    <div class="flex items-center gap-2 mt-3 pt-3 border-t" style="border-color: var(--color-border-subtle);">
        <span class="badge badge-primary"><?= number_format($paginator->total) ?> resultado<?= $paginator->total === 1 ? '' : 's' ?> de <?= number_format($total) ?></span>
    </div>
    <?php endif; ?>
</div>

<!-- Tabla premium -->
<div class="surface overflow-hidden">
    <?php if (empty($contacts)): ?>
    <div class="empty-state-pro">
        <div class="empty-icon">👥</div>
        <h4 class="font-bold mb-1" style="color: var(--color-text-primary);">Aun no tienes contactos</h4>
        <p class="text-sm mb-4" style="color: var(--color-text-tertiary);">Importa desde CSV o crea tu primer contacto manualmente.</p>
        <div class="flex gap-2 justify-center">
            <a href="<?= url('/contacts/import') ?>" class="btn btn-secondary btn-sm">Importar CSV</a>
            <a href="<?= url('/contacts/create') ?>" class="btn btn-primary btn-sm">Crear contacto</a>
        </div>
    </div>
    <?php else: ?>
    <div class="overflow-x-auto">
        <table class="kt-table">
            <thead>
                <tr>
                    <th class="w-8"><input type="checkbox" class="rounded"></th>
                    <th>Contacto</th>
                    <th>Empresa</th>
                    <th>Telefono</th>
                    <th>Estado</th>
                    <th>Responsable</th>
                    <th>Etiquetas</th>
                    <th class="text-right">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($contacts as $c):
                    $name = trim(($c['first_name'] ?? '') . ' ' . ($c['last_name'] ?? ''));
                    $statusBadge = match ($c['status']) {
                        'vip'      => 'badge-amber',
                        'active'   => 'badge-emerald',
                        'inactive' => 'badge-slate',
                        'blocked'  => 'badge-rose',
                        default    => 'badge-cyan',
                    };
                    $statusLabel = match ($c['status']) {
                        'vip'      => 'VIP',
                        'active'   => 'Activo',
                        'inactive' => 'Inactivo',
                        'blocked'  => 'Bloqueado',
                        default    => 'Lead',
                    };
                ?>
                <tr>
                    <td><input type="checkbox" class="rounded" value="<?= (int) $c['id'] ?>"></td>
                    <td>
                        <div class="flex items-center gap-3">
                            <div class="avatar avatar-md flex-shrink-0"><?= e(strtoupper(mb_substr($name, 0, 1))) ?></div>
                            <div class="min-w-0">
                                <a href="<?= url('/contacts/' . $c['id']) ?>" class="font-semibold hover:underline truncate block" style="color: var(--color-text-primary);"><?= e($name ?: 'Sin nombre') ?></a>
                                <?php if (!empty($c['email'])): ?>
                                <div class="text-xs truncate" style="color: var(--color-text-tertiary);"><?= e($c['email']) ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </td>
                    <td><?= !empty($c['company']) ? '<span style="color: var(--color-text-secondary);">' . e($c['company']) . '</span>' : '<span style="color: var(--color-text-muted);">—</span>' ?></td>
                    <td>
                        <?php if (!empty($c['phone'])): ?>
                        <a href="https://wa.me/<?= e(preg_replace('/[^\d]/', '', (string) $c['phone'])) ?>" target="_blank" class="font-mono text-xs hover:underline" style="color: var(--color-text-secondary);"><?= e($c['phone']) ?></a>
                        <?php else: ?><span style="color: var(--color-text-muted);">—</span><?php endif; ?>
                    </td>
                    <td><span class="badge <?= $statusBadge ?>"><?= e($statusLabel) ?></span></td>
                    <td>
                        <?php if (!empty($c['agent_first'])): ?>
                            <div class="flex items-center gap-2">
                                <div class="avatar avatar-sm"><?= e(strtoupper(mb_substr((string) $c['agent_first'], 0, 1))) ?></div>
                                <span class="text-xs" style="color: var(--color-text-secondary);"><?= e($c['agent_first']) ?></span>
                            </div>
                        <?php else: ?><span style="color: var(--color-text-muted); font-size: 0.75rem;">Sin asignar</span><?php endif; ?>
                    </td>
                    <td>
                        <div class="flex flex-wrap gap-1 max-w-[200px]">
                            <?php foreach (array_slice(($c['tags'] ?? []), 0, 3) as $t): ?>
                            <span class="chip" style="background: <?= e((string) ($t['color'] ?? '#7C3AED')) ?>15; color: <?= e((string) ($t['color'] ?? '#7C3AED')) ?>; border-color: <?= e((string) ($t['color'] ?? '#7C3AED')) ?>30;"><?= e($t['name']) ?></span>
                            <?php endforeach; ?>
                            <?php if (count($c['tags'] ?? []) > 3): ?>
                            <span class="chip">+<?= count($c['tags']) - 3 ?></span>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td class="text-right">
                        <div class="flex items-center justify-end gap-1">
                            <a href="<?= url('/contacts/' . $c['id']) ?>" class="btn btn-ghost btn-sm">Ver</a>
                            <a href="<?= url('/contacts/' . $c['id'] . '/edit') ?>" class="btn btn-ghost btn-icon" title="Editar">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php if ($paginator->lastPage > 1): ?>
<div class="mt-4 flex justify-end">
    <?= $paginator->links(url('/contacts'), array_filter($filters)) ?>
</div>
<?php endif; ?>

<?php \App\Core\View::stop(); ?>
