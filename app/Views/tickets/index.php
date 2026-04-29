<?php
\App\Core\View::extend('layouts.app');
\App\Core\View::start('content');

// Stats agregadas
$totalTickets = $paginator->total ?? count($tickets);
$openCount = 0; $criticalCount = 0; $overdueCount = 0; $resolvedCount = 0;
foreach ($tickets as $t) {
    if (!in_array($t['status'], ['resolved','closed'], true)) $openCount++;
    if ($t['priority'] === 'critical') $criticalCount++;
    if ($t['status'] === 'resolved') $resolvedCount++;
    if (!empty($t['due_at']) && strtotime($t['due_at']) < time() && !in_array($t['status'], ['resolved','closed'], true)) $overdueCount++;
}
?>

<!-- Header premium -->
<div class="mb-6 flex flex-wrap items-end justify-between gap-4">
    <div>
        <span class="text-[11px] font-semibold uppercase tracking-[0.12em]" style="color: var(--color-text-tertiary);">Soporte · Tickets</span>
        <h1 class="text-[28px] font-bold tracking-tight" style="color: var(--color-text-primary); letter-spacing: -0.025em;">Tickets</h1>
        <p class="text-sm mt-1" style="color: var(--color-text-tertiary);">Gestion de soporte multicanal con SLA por prioridad.</p>
    </div>
    <div class="flex items-center gap-2">
        <a href="<?= url('/tickets/create') ?>" class="btn btn-primary">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
            Nuevo ticket
        </a>
    </div>
</div>

<!-- Stat cards -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
    <?php
    $stats = [
        ['Total tickets',  $totalTickets,    'En esta vista',     '#7C3AED', 'M3 4h13M3 8h9m-9 4h6m4 0l4-4m0 0l4 4m-4-4v12'],
        ['Abiertos',       $openCount,        'Requieren atencion','#06B6D4', 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z'],
        ['Criticos',       $criticalCount,    'Prioridad alta',   '#F43F5E', 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z'],
        ['Vencidos',       $overdueCount,     'Fuera de SLA',      '#F59E0B', 'M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z'],
    ];
    foreach ($stats as $s): ?>
    <div class="surface p-4 hover-lift">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl flex items-center justify-center" style="background: <?= $s[3] ?>15; color: <?= $s[3] ?>;">
                <svg class="w-[18px] h-[18px]" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="<?= $s[4] ?>"/></svg>
            </div>
            <div class="flex-1 min-w-0">
                <div class="text-[10px] uppercase font-semibold tracking-wider" style="color: var(--color-text-tertiary);"><?= $s[0] ?></div>
                <div class="text-xl font-bold tabular-nums" style="color: var(--color-text-primary);"><?= number_format((int) $s[1]) ?></div>
                <div class="text-[10px]" style="color: var(--color-text-muted);"><?= $s[2] ?></div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Filtros -->
<div class="surface p-4 mb-4">
    <form method="GET" class="grid grid-cols-1 md:grid-cols-12 gap-3">
        <div class="md:col-span-5 relative">
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 pointer-events-none" style="color: var(--color-text-tertiary);" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            <input type="text" name="q" value="<?= e((string) $filters['q']) ?>" placeholder="Buscar por codigo o asunto..." class="input pl-10 text-sm">
        </div>
        <select name="status" class="select md:col-span-3 text-sm">
            <option value="">Todos los estados</option>
            <?php foreach (['open'=>'Abierto','in_progress'=>'En proceso','waiting'=>'En espera','resolved'=>'Resuelto','closed'=>'Cerrado'] as $v=>$lab): ?>
            <option value="<?= $v ?>" <?= $filters['status'] === $v ? 'selected' : '' ?>><?= $lab ?></option>
            <?php endforeach; ?>
        </select>
        <select name="priority" class="select md:col-span-3 text-sm">
            <option value="">Cualquier prioridad</option>
            <?php foreach (['critical'=>'Critica','high'=>'Alta','medium'=>'Media','low'=>'Baja'] as $v=>$lab): ?>
            <option value="<?= $v ?>" <?= $filters['priority'] === $v ? 'selected' : '' ?>><?= $lab ?></option>
            <?php endforeach; ?>
        </select>
        <button class="btn btn-primary btn-sm md:col-span-1">Filtrar</button>
    </form>
</div>

<!-- Tabla -->
<div class="surface overflow-hidden">
    <?php if (empty($tickets)): ?>
    <div class="empty-state-pro">
        <div class="empty-icon">🎫</div>
        <h4 class="font-bold mb-1" style="color: var(--color-text-primary);">Aun no hay tickets</h4>
        <p class="text-sm mb-4" style="color: var(--color-text-tertiary);">Crea tu primer ticket o configura automatizaciones para que se generen solos.</p>
        <a href="<?= url('/tickets/create') ?>" class="btn btn-primary btn-sm">Crear ticket</a>
    </div>
    <?php else: ?>
    <div class="overflow-x-auto">
        <table class="kt-table">
            <thead>
                <tr>
                    <th>Codigo</th>
                    <th>Asunto</th>
                    <th>Cliente</th>
                    <th>Prioridad</th>
                    <th>Estado</th>
                    <th>SLA</th>
                    <th>Agente</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tickets as $t):
                    $name = trim(($t['first_name'] ?? '') . ' ' . ($t['last_name'] ?? ''));
                    $pCls = match ($t['priority']) { 'critical' => 'badge-rose', 'high' => 'badge-amber', 'medium' => 'badge-cyan', default => 'badge-slate' };
                    $pLabel = match ($t['priority']) { 'critical' => 'Critica', 'high' => 'Alta', 'medium' => 'Media', default => 'Baja' };
                    $sCls = match ($t['status']) { 'open' => 'badge-cyan', 'in_progress' => 'badge-primary', 'waiting' => 'badge-amber', 'resolved' => 'badge-emerald', default => 'badge-slate' };
                    $sLabel = match ($t['status']) { 'open' => 'Abierto', 'in_progress' => 'En proceso', 'waiting' => 'En espera', 'resolved' => 'Resuelto', 'closed' => 'Cerrado', default => $t['status'] };
                    $overdue = !empty($t['due_at']) && strtotime($t['due_at']) < time() && !in_array($t['status'], ['resolved','closed'], true);
                ?>
                <tr>
                    <td><code class="font-mono text-xs px-2 py-0.5 rounded" style="background: rgba(6,182,212,0.1); color: #22D3EE;">#<?= e($t['code']) ?></code></td>
                    <td>
                        <a href="<?= url('/tickets/' . $t['id']) ?>" class="font-semibold hover:underline block max-w-md truncate" style="color: var(--color-text-primary);"><?= e($t['subject']) ?></a>
                    </td>
                    <td>
                        <?php if (!empty($name)): ?>
                        <div class="flex items-center gap-2">
                            <div class="avatar avatar-sm"><?= e(strtoupper(mb_substr($name, 0, 1))) ?></div>
                            <span class="text-xs" style="color: var(--color-text-secondary);"><?= e($name) ?></span>
                        </div>
                        <?php else: ?><span style="color: var(--color-text-muted); font-size: 0.75rem;">—</span><?php endif; ?>
                    </td>
                    <td><span class="badge <?= $pCls ?>"><?= e($pLabel) ?></span></td>
                    <td><span class="badge <?= $sCls ?>"><?= e($sLabel) ?></span></td>
                    <td>
                        <?php if (!empty($t['due_at'])): ?>
                        <span class="text-xs font-mono <?= $overdue ? 'font-bold' : '' ?>" style="<?= $overdue ? 'color: #FB7185;' : 'color: var(--color-text-secondary);' ?>">
                            <?= $overdue ? '⚠ ' : '' ?><?= date('d M H:i', strtotime($t['due_at'])) ?>
                        </span>
                        <?php else: ?><span style="color: var(--color-text-muted);">—</span><?php endif; ?>
                    </td>
                    <td>
                        <?php if (!empty($t['agent_first'])): ?>
                        <div class="flex items-center gap-2">
                            <div class="avatar avatar-sm"><?= e(strtoupper(mb_substr((string) $t['agent_first'], 0, 1))) ?></div>
                            <span class="text-xs" style="color: var(--color-text-secondary);"><?= e($t['agent_first']) ?></span>
                        </div>
                        <?php else: ?><span style="color: var(--color-text-muted); font-size: 0.75rem;">Sin asignar</span><?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php if (isset($paginator) && $paginator->lastPage > 1): ?>
<div class="mt-4 flex justify-end">
    <?= $paginator->links(url('/tickets'), array_filter($filters)) ?>
</div>
<?php endif; ?>

<?php \App\Core\View::stop(); ?>
