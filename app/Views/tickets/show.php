<?php
\App\Core\View::extend('layouts.app');
\App\Core\View::start('content');
$contactName = trim(($ticket['first_name'] ?? '') . ' ' . ($ticket['last_name'] ?? ''));
?>

<div class="mb-4 flex items-center gap-2 text-xs">
    <a href="<?= url('/tickets') ?>" class="dark:text-slate-400 text-slate-500 hover:underline">&larr; Tickets</a>
    <span class="font-mono dark:text-cyan-400 text-cyan-600"><?= e($ticket['code']) ?></span>
</div>
<div class="mb-6 flex items-start justify-between gap-3 flex-wrap">
    <h1 class="text-2xl font-extrabold dark:text-white text-slate-900"><?= e($ticket['subject']) ?></h1>
</div>

<div class="grid lg:grid-cols-3 gap-4">
    <div class="lg:col-span-2 space-y-4">
        <!-- Update form -->
        <form action="<?= url('/tickets/' . $ticket['id']) ?>" method="POST" class="glass rounded-2xl p-5">
            <?= csrf_field() ?>
            <input type="hidden" name="_method" value="PUT">

            <div class="grid md:grid-cols-3 gap-3 mb-4">
                <div>
                    <label class="text-xs uppercase tracking-wider dark:text-slate-400 text-slate-500">Estado</label>
                    <select name="status" class="w-full mt-1 px-3 py-2 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-lg dark:text-white text-slate-900 text-sm">
                        <?php foreach (['open'=>'Abierto','in_progress'=>'En proceso','waiting'=>'En espera','resolved'=>'Resuelto','closed'=>'Cerrado'] as $v=>$lab): ?>
                        <option value="<?= $v ?>" <?= $ticket['status'] === $v ? 'selected' : '' ?>><?= $lab ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="text-xs uppercase tracking-wider dark:text-slate-400 text-slate-500">Prioridad</label>
                    <select name="priority" class="w-full mt-1 px-3 py-2 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-lg dark:text-white text-slate-900 text-sm">
                        <?php foreach (['critical'=>'Critica','high'=>'Alta','medium'=>'Media','low'=>'Baja'] as $v=>$lab): ?>
                        <option value="<?= $v ?>" <?= $ticket['priority'] === $v ? 'selected' : '' ?>><?= $lab ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="text-xs uppercase tracking-wider dark:text-slate-400 text-slate-500">Asignado a</label>
                    <select name="assigned_to" class="w-full mt-1 px-3 py-2 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-lg dark:text-white text-slate-900 text-sm">
                        <option value="">Sin asignar</option>
                        <?php foreach ($agents as $a): ?>
                        <option value="<?= (int) $a['id'] ?>" <?= ((int) $ticket['assigned_to']) === (int) $a['id'] ? 'selected' : '' ?>><?= e($a['first_name'] . ' ' . $a['last_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="mb-4">
                <label class="text-xs uppercase tracking-wider dark:text-slate-400 text-slate-500">Descripcion</label>
                <textarea name="description" rows="6" class="w-full mt-1 px-3 py-2 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-lg dark:text-white text-slate-900 text-sm"><?= e((string) ($ticket['description'] ?? '')) ?></textarea>
            </div>
            <div class="flex justify-end">
                <button type="submit" class="px-4 py-2 rounded-xl text-white text-sm font-semibold" style="background:linear-gradient(135deg,#7C3AED,#06B6D4)">Guardar</button>
            </div>
        </form>

        <!-- Comments -->
        <div class="glass rounded-2xl p-5">
            <h3 class="font-bold dark:text-white text-slate-900 mb-3">Comentarios</h3>
            <?php if (empty($comments)): ?>
            <p class="text-sm dark:text-slate-400 text-slate-500 mb-4">Aun no hay comentarios.</p>
            <?php else: ?>
            <div class="space-y-3 mb-4">
                <?php foreach ($comments as $c): ?>
                <div class="p-3 rounded-xl <?= $c['is_internal'] ? 'bg-yellow-500/10 border border-yellow-500/30' : 'dark:bg-white/5 bg-slate-50' ?>">
                    <div class="flex items-center justify-between gap-2 mb-1 text-xs">
                        <span class="font-semibold dark:text-white text-slate-900">
                            <?= e(($c['first_name'] ?? '') . ' ' . ($c['last_name'] ?? '')) ?>
                        </span>
                        <span class="dark:text-slate-500 text-slate-400">
                            <?php if ($c['is_internal']): ?>📌 Interno · <?php endif; ?>
                            <?= time_ago((string) $c['created_at']) ?>
                        </span>
                    </div>
                    <div class="text-sm dark:text-slate-200 text-slate-800 whitespace-pre-line"><?= e((string) $c['body']) ?></div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <form action="<?= url('/tickets/' . $ticket['id'] . '/comment') ?>" method="POST" class="space-y-2">
                <?= csrf_field() ?>
                <textarea name="body" rows="3" placeholder="Agrega un comentario..." class="w-full px-3 py-2 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-lg dark:text-white text-slate-900 text-sm"></textarea>
                <div class="flex items-center justify-between gap-2">
                    <label class="flex items-center gap-1 text-xs dark:text-slate-400 text-slate-500">
                        <input type="checkbox" name="is_internal" value="1" class="w-3.5 h-3.5 rounded">
                        Solo para el equipo
                    </label>
                    <button type="submit" class="px-4 py-2 rounded-xl text-white text-sm font-semibold" style="background:linear-gradient(135deg,#7C3AED,#06B6D4)">Comentar</button>
                </div>
            </form>
        </div>
    </div>

    <div class="space-y-4">
        <div class="glass rounded-2xl p-5">
            <h3 class="font-bold dark:text-white text-slate-900 mb-3">Detalles</h3>
            <dl class="space-y-2 text-sm">
                <?php foreach ([
                    ['Estado',    $ticket['status']],
                    ['Prioridad', $ticket['priority']],
                    ['Categoria', $ticket['category']],
                    ['Canal',     $ticket['channel']],
                    ['Cliente',   $contactName ?: '—'],
                    ['Vence',     $ticket['due_at'] ? date('d/m/Y H:i', strtotime($ticket['due_at'])) : '—'],
                    ['Creado',    time_ago((string) $ticket['created_at'])],
                ] as [$k, $v]): ?>
                <div class="flex justify-between gap-2">
                    <dt class="dark:text-slate-400 text-slate-500"><?= $k ?></dt>
                    <dd class="dark:text-white text-slate-900 capitalize"><?= e((string) $v) ?></dd>
                </div>
                <?php endforeach; ?>
            </dl>
        </div>
        <form action="<?= url('/tickets/' . $ticket['id']) ?>" method="POST" onsubmit="return confirm('Eliminar ticket?')" class="glass rounded-2xl p-4">
            <?= csrf_field() ?>
            <input type="hidden" name="_method" value="DELETE">
            <button type="submit" class="w-full px-3 py-2 rounded-xl text-red-500 hover:bg-red-500/10 text-sm">Eliminar ticket</button>
        </form>
    </div>
</div>
<?php \App\Core\View::stop(); ?>
