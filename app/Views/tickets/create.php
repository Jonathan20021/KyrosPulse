<?php
\App\Core\View::extend('layouts.app');
\App\Core\View::start('content');
$errors = $errors ?? [];
?>
<?php \App\Core\View::include('components.page_header', ['title' => 'Nuevo ticket', 'subtitle' => 'Registra un caso de soporte.']); ?>

<form action="<?= url('/tickets') ?>" method="POST" class="glass rounded-2xl p-6 max-w-3xl">
    <?= csrf_field() ?>
    <div class="grid md:grid-cols-2 gap-4">
        <div class="md:col-span-2">
            <label class="block text-sm font-medium dark:text-slate-300 text-slate-700 mb-1.5">Asunto *</label>
            <input type="text" name="subject" required value="<?= old('subject') ?>"
                   class="w-full px-4 py-2.5 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-xl dark:text-white text-slate-900">
            <?php if ($e = error_for('subject', $errors)): ?><p class="text-xs text-red-400 mt-1"><?= e($e) ?></p><?php endif; ?>
        </div>
        <div>
            <label class="block text-sm font-medium dark:text-slate-300 text-slate-700 mb-1.5">Prioridad</label>
            <select name="priority" class="w-full px-4 py-2.5 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-xl dark:text-white text-slate-900">
                <option value="low">Baja</option>
                <option value="medium" selected>Media</option>
                <option value="high">Alta</option>
                <option value="critical">Critica</option>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium dark:text-slate-300 text-slate-700 mb-1.5">Categoria</label>
            <input type="text" name="category" value="general"
                   class="w-full px-4 py-2.5 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-xl dark:text-white text-slate-900">
        </div>
        <div class="md:col-span-2">
            <label class="block text-sm font-medium dark:text-slate-300 text-slate-700 mb-1.5">Asignar a</label>
            <select name="assigned_to" class="w-full px-4 py-2.5 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-xl dark:text-white text-slate-900">
                <option value="">Sin asignar</option>
                <?php foreach ($agents as $a): ?>
                <option value="<?= (int) $a['id'] ?>"><?= e($a['first_name'] . ' ' . $a['last_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="md:col-span-2">
            <label class="block text-sm font-medium dark:text-slate-300 text-slate-700 mb-1.5">Descripcion</label>
            <textarea name="description" rows="5"
                      class="w-full px-4 py-2.5 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-xl dark:text-white text-slate-900"></textarea>
        </div>
    </div>
    <div class="flex justify-end gap-2 mt-6 pt-6 border-t dark:border-white/5 border-slate-200">
        <a href="<?= url('/tickets') ?>" class="px-4 py-2 rounded-xl dark:text-slate-300 text-slate-600 dark:hover:bg-white/5 hover:bg-slate-100 text-sm">Cancelar</a>
        <button type="submit" class="px-5 py-2 rounded-xl text-white text-sm font-semibold" style="background:linear-gradient(135deg,#7C3AED,#06B6D4)">Crear ticket</button>
    </div>
</form>
<?php \App\Core\View::stop(); ?>
