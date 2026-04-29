<?php
\App\Core\View::extend('layouts.app');
\App\Core\View::start('content');

$totalTasks = count($tasks);
$completedTasks = count(array_filter($tasks, fn($t) => $t['status'] === 'completed'));
$overdueTasks = count(array_filter($tasks, fn($t) => !empty($t['due_at']) && strtotime($t['due_at']) < time() && !in_array($t['status'], ['completed','cancelled'], true)));
?>

<!-- Header premium -->
<div class="mb-6 flex flex-wrap items-end justify-between gap-4">
    <div>
        <span class="text-[11px] font-semibold uppercase tracking-[0.12em]" style="color: var(--color-text-tertiary);">Productividad · Tareas</span>
        <h1 class="text-[28px] font-bold tracking-tight" style="color: var(--color-text-primary); letter-spacing: -0.025em;">Tareas y recordatorios</h1>
        <p class="text-sm mt-1" style="color: var(--color-text-tertiary);">Organiza el trabajo del equipo con recordatorios automaticos.</p>
    </div>
    <div class="flex items-center gap-3">
        <div class="flex items-center gap-3 text-sm">
            <div>
                <div class="text-[10px] uppercase font-semibold tracking-wider" style="color: var(--color-text-tertiary);">Completadas</div>
                <div class="text-lg font-bold tabular-nums" style="color: var(--color-emerald);"><?= $completedTasks ?>/<?= $totalTasks ?></div>
            </div>
            <?php if ($overdueTasks > 0): ?>
            <div class="w-px h-8" style="background: var(--color-border-subtle);"></div>
            <div>
                <div class="text-[10px] uppercase font-semibold tracking-wider" style="color: var(--color-text-tertiary);">Vencidas</div>
                <div class="text-lg font-bold tabular-nums" style="color: #FB7185;"><?= $overdueTasks ?></div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="grid lg:grid-cols-3 gap-4">
    <!-- Lista -->
    <div class="lg:col-span-2 space-y-3">
        <!-- Filtros -->
        <form method="GET" class="surface p-3 flex flex-wrap items-center gap-2">
            <div class="flex-1 min-w-[140px] relative">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 pointer-events-none" style="color: var(--color-text-tertiary);" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input type="text" name="q" value="<?= e((string) $filters['q']) ?>" placeholder="Buscar..." class="input pl-10 text-sm py-1.5">
            </div>
            <select name="status" class="select text-sm py-1.5">
                <option value="">Todos los estados</option>
                <?php foreach (['pending'=>'Pendiente','in_progress'=>'En curso','completed'=>'Completa','cancelled'=>'Cancelada'] as $v=>$lab): ?>
                <option value="<?= $v ?>" <?= $filters['status'] === $v ? 'selected' : '' ?>><?= $lab ?></option>
                <?php endforeach; ?>
            </select>
            <label class="flex items-center gap-1.5 text-xs cursor-pointer" style="color: var(--color-text-secondary);">
                <input type="checkbox" name="overdue" value="1" <?= !empty($filters['overdue']) ? 'checked' : '' ?> class="w-3.5 h-3.5 rounded">
                Solo vencidas
            </label>
            <button class="btn btn-primary btn-sm">Filtrar</button>
        </form>

        <div class="surface overflow-hidden">
            <?php if (empty($tasks)): ?>
            <div class="empty-state-pro">
                <div class="empty-icon">✅</div>
                <h4 class="font-bold mb-1" style="color: var(--color-text-primary);">Sin tareas pendientes</h4>
                <p class="text-sm" style="color: var(--color-text-tertiary);">Crea una tarea en el panel de la derecha para mantener el seguimiento.</p>
            </div>
            <?php else: ?>
            <div>
                <?php foreach ($tasks as $t):
                    $overdue = !empty($t['due_at']) && strtotime($t['due_at']) < time() && !in_array($t['status'], ['completed','cancelled'], true);
                    $isComplete = $t['status'] === 'completed';
                    $priorityColor = match ($t['priority'] ?? 'medium') {
                        'urgent' => '#F43F5E',
                        'high'   => '#F59E0B',
                        'medium' => '#06B6D4',
                        default  => '#94A3B8',
                    };
                ?>
                <div class="flex items-start gap-3 px-4 py-3.5 transition border-b group hover:bg-[color:var(--color-bg-subtle)]" style="border-color: var(--color-border-subtle);">
                    <div class="w-1 h-10 rounded-full flex-shrink-0 mt-0.5" style="background: <?= $priorityColor ?>;"></div>

                    <form action="<?= url('/tasks/' . $t['id'] . '/complete') ?>" method="POST" class="mt-0.5">
                        <?= csrf_field() ?>
                        <button type="submit" class="w-5 h-5 rounded-full border-2 transition flex items-center justify-center hover:scale-110" style="<?= $isComplete ? 'background: #10B981; border-color: #10B981;' : 'border-color: var(--color-border-strong);' ?>" title="<?= $isComplete ? 'Marcar como pendiente' : 'Completar' ?>">
                            <?php if ($isComplete): ?>
                            <svg class="w-3 h-3 text-white" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                            <?php endif; ?>
                        </button>
                    </form>

                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between gap-2 mb-0.5">
                            <span class="font-semibold text-sm <?= $isComplete ? 'line-through' : '' ?>" style="color: <?= $isComplete ? 'var(--color-text-muted)' : 'var(--color-text-primary)' ?>;"><?= e($t['title']) ?></span>
                            <?php if (!empty($t['due_at'])): ?>
                            <span class="text-[11px] flex-shrink-0 font-mono <?= $overdue ? 'font-bold' : '' ?>" style="color: <?= $overdue ? '#FB7185' : 'var(--color-text-tertiary)' ?>;">
                                <?= $overdue ? '⚠ ' : '' ?><?= date('d M · H:i', strtotime($t['due_at'])) ?>
                            </span>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($t['description'])): ?>
                        <div class="text-xs truncate" style="color: var(--color-text-tertiary);"><?= e((string) $t['description']) ?></div>
                        <?php endif; ?>
                        <div class="flex items-center gap-2 mt-1.5">
                            <?php if (!empty($t['first_name'])): ?>
                            <div class="flex items-center gap-1.5">
                                <div class="avatar avatar-sm" style="width: 16px; height: 16px; font-size: 8px;"><?= e(strtoupper(mb_substr((string) $t['first_name'], 0, 1))) ?></div>
                                <span class="text-[11px]" style="color: var(--color-text-tertiary);"><?= e($t['first_name'] . ' ' . $t['last_name']) ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($t['priority']) && $t['priority'] !== 'medium'):
                                $pLbl = match($t['priority']) { 'urgent' => 'Urgente', 'high' => 'Alta', 'low' => 'Baja', default => $t['priority'] };
                                $pBadge = match($t['priority']) { 'urgent' => 'badge-rose', 'high' => 'badge-amber', 'low' => 'badge-slate', default => 'badge-cyan' };
                            ?>
                            <span class="badge <?= $pBadge ?>"><?= e($pLbl) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <form action="<?= url('/tasks/' . $t['id']) ?>" method="POST" onsubmit="return confirm('Eliminar?')" class="opacity-0 group-hover:opacity-100 transition">
                        <?= csrf_field() ?>
                        <input type="hidden" name="_method" value="DELETE">
                        <button class="btn btn-ghost btn-icon" style="color: #F43F5E;">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        </button>
                    </form>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Crear tarea (sticky form) -->
    <form action="<?= url('/tasks') ?>" method="POST" class="surface p-5 h-fit lg:sticky lg:top-20">
        <?= csrf_field() ?>
        <div class="flex items-center gap-2 mb-4">
            <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background: var(--gradient-primary);">
                <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
            </div>
            <h3 class="font-bold text-[15px]" style="color: var(--color-text-primary);">Crear tarea</h3>
        </div>
        <div class="space-y-3">
            <div>
                <label class="label">Titulo *</label>
                <input type="text" name="title" required class="input text-sm" placeholder="Llamar a Maria...">
            </div>
            <div>
                <label class="label">Descripcion</label>
                <textarea name="description" rows="2" class="textarea text-sm" placeholder="Detalles..."></textarea>
            </div>
            <div>
                <label class="label">Vence</label>
                <input type="datetime-local" name="due_at" class="input text-sm">
            </div>
            <div>
                <label class="label">Asignar a</label>
                <select name="assigned_to" class="select text-sm">
                    <option value="">Asignar a mi</option>
                    <?php foreach ($agents as $a): ?>
                    <option value="<?= (int) $a['id'] ?>"><?= e($a['first_name'] . ' ' . $a['last_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="label">Prioridad</label>
                <select name="priority" class="select text-sm">
                    <option value="low">Baja</option>
                    <option value="medium" selected>Media</option>
                    <option value="high">Alta</option>
                    <option value="urgent">Urgente</option>
                </select>
            </div>
            <div class="space-y-2 pt-2 border-t" style="border-color: var(--color-border-subtle);">
                <div class="text-[10px] uppercase font-semibold tracking-wider mb-2" style="color: var(--color-text-tertiary);">Recordatorios</div>
                <label class="flex items-center gap-2 text-xs cursor-pointer" style="color: var(--color-text-secondary);">
                    <input type="checkbox" name="remind_email" value="1" class="w-3.5 h-3.5 rounded">
                    📧 Email
                </label>
                <label class="flex items-center gap-2 text-xs cursor-pointer" style="color: var(--color-text-secondary);">
                    <input type="checkbox" name="remind_whatsapp" value="1" class="w-3.5 h-3.5 rounded">
                    💬 WhatsApp
                </label>
            </div>
            <button type="submit" class="btn btn-primary w-full justify-center">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                Crear tarea
            </button>
        </div>
    </form>
</div>

<?php \App\Core\View::stop(); ?>
