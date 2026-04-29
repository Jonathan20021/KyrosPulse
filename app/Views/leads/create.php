<?php
\App\Core\View::extend('layouts.app');
\App\Core\View::start('content');
$errors = $errors ?? [];
?>
<?php \App\Core\View::include('components.page_header', [
    'title' => 'Nuevo lead',
    'subtitle' => 'Crea una oportunidad de venta.',
]); ?>

<form action="<?= url('/leads') ?>" method="POST" class="glass rounded-2xl p-6 max-w-3xl">
    <?= csrf_field() ?>
    <?php if ($contact): ?>
    <input type="hidden" name="contact_id" value="<?= (int) $contact['id'] ?>">
    <div class="mb-4 p-3 rounded-xl glass-light text-sm dark:text-slate-300 text-slate-700">
        Asociado a: <strong><?= e(($contact['first_name'] ?? '') . ' ' . ($contact['last_name'] ?? '')) ?></strong>
    </div>
    <?php endif; ?>

    <div class="grid md:grid-cols-2 gap-4">
        <div class="md:col-span-2">
            <label class="block text-sm font-medium dark:text-slate-300 text-slate-700 mb-1.5">Titulo *</label>
            <input type="text" name="title" required value="<?= old('title') ?>" placeholder="Cotizacion 50 unidades..."
                   class="w-full px-4 py-2.5 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-xl dark:text-white text-slate-900">
            <?php if ($e = error_for('title', $errors)): ?><p class="text-xs text-red-400 mt-1"><?= e($e) ?></p><?php endif; ?>
        </div>
        <div>
            <label class="block text-sm font-medium dark:text-slate-300 text-slate-700 mb-1.5">Etapa *</label>
            <select name="stage_id" required class="w-full px-4 py-2.5 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-xl dark:text-white text-slate-900">
                <?php foreach ($stages as $s): ?>
                <option value="<?= (int) $s['id'] ?>"><?= e($s['name']) ?> (<?= (int) $s['probability'] ?>%)</option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium dark:text-slate-300 text-slate-700 mb-1.5">Valor</label>
            <div class="flex gap-2">
                <input type="number" step="0.01" name="value" value="<?= old('value', '0') ?>"
                       class="flex-1 px-4 py-2.5 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-xl dark:text-white text-slate-900">
                <select name="currency" class="px-3 py-2.5 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-xl dark:text-white text-slate-900">
                    <option>USD</option><option>EUR</option><option>DOP</option><option>MXN</option><option>COP</option>
                </select>
            </div>
        </div>
        <div>
            <label class="block text-sm font-medium dark:text-slate-300 text-slate-700 mb-1.5">Fecha estimada de cierre</label>
            <input type="date" name="expected_close"
                   class="w-full px-4 py-2.5 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-xl dark:text-white text-slate-900">
        </div>
        <div>
            <label class="block text-sm font-medium dark:text-slate-300 text-slate-700 mb-1.5">Responsable</label>
            <select name="assigned_to" class="w-full px-4 py-2.5 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-xl dark:text-white text-slate-900">
                <option value="">Sin asignar</option>
                <?php foreach ($agents as $a): ?>
                <option value="<?= (int) $a['id'] ?>"><?= e($a['first_name'] . ' ' . $a['last_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium dark:text-slate-300 text-slate-700 mb-1.5">Fuente</label>
            <input type="text" name="source" value="manual"
                   class="w-full px-4 py-2.5 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-xl dark:text-white text-slate-900">
        </div>
        <div class="md:col-span-2">
            <label class="block text-sm font-medium dark:text-slate-300 text-slate-700 mb-1.5">Descripcion</label>
            <textarea name="description" rows="3"
                      class="w-full px-4 py-2.5 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-xl dark:text-white text-slate-900"></textarea>
        </div>
    </div>
    <div class="flex justify-end gap-2 mt-6 pt-6 border-t dark:border-white/5 border-slate-200">
        <a href="<?= url('/leads') ?>" class="px-4 py-2 rounded-xl dark:text-slate-300 text-slate-600 dark:hover:bg-white/5 hover:bg-slate-100 text-sm">Cancelar</a>
        <button type="submit" class="px-5 py-2 rounded-xl text-white text-sm font-semibold" style="background:linear-gradient(135deg,#7C3AED,#06B6D4)">Crear lead</button>
    </div>
</form>
<?php \App\Core\View::stop(); ?>
