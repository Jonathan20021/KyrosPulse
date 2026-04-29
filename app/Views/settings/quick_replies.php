<?php
\App\Core\View::extend('layouts.app');
\App\Core\View::start('content');
?>
<?php \App\Core\View::include('components.page_header', ['title' => 'Respuestas rapidas', 'subtitle' => 'Plantillas que tus agentes pueden insertar con un click en el inbox.']); ?>
<?php \App\Core\View::include('settings._tabs', ['tab' => 'quick_replies']); ?>

<div class="grid lg:grid-cols-3 gap-4">
    <form action="<?= url('/settings/quick-replies') ?>" method="POST" class="glass rounded-2xl p-5 h-fit">
        <?= csrf_field() ?>
        <h3 class="font-bold dark:text-white text-slate-900 mb-3">Nueva respuesta</h3>
        <div class="space-y-3">
            <input type="text" name="shortcut" required placeholder="/saludo" class="w-full px-3 py-2 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-lg dark:text-white text-slate-900 text-sm font-mono">
            <input type="text" name="title" placeholder="Titulo (opcional)" class="w-full px-3 py-2 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-lg dark:text-white text-slate-900 text-sm">
            <textarea name="body" rows="4" required placeholder="Contenido de la respuesta..." class="w-full px-3 py-2 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-lg dark:text-white text-slate-900 text-sm"></textarea>
            <button type="submit" class="w-full px-4 py-2 rounded-xl text-white text-sm font-semibold" style="background:linear-gradient(135deg,#7C3AED,#06B6D4)">Guardar</button>
        </div>
    </form>

    <div class="lg:col-span-2 space-y-2">
        <?php if (empty($replies)): ?>
        <div class="glass rounded-2xl p-6 text-center text-sm dark:text-slate-400 text-slate-500">Aun no hay respuestas rapidas.</div>
        <?php else: foreach ($replies as $r): ?>
        <div class="glass rounded-2xl p-4 flex items-start justify-between gap-3">
            <div class="min-w-0 flex-1">
                <div class="flex items-center gap-2 mb-1">
                    <span class="font-mono text-xs px-2 py-0.5 rounded dark:bg-cyan-500/20 bg-cyan-100 dark:text-cyan-300 text-cyan-700"><?= e($r['shortcut']) ?></span>
                    <?php if (!empty($r['title'])): ?><span class="text-sm dark:text-white text-slate-900"><?= e($r['title']) ?></span><?php endif; ?>
                </div>
                <p class="text-sm dark:text-slate-300 text-slate-700 whitespace-pre-line"><?= e((string) $r['body']) ?></p>
            </div>
            <form action="<?= url('/settings/quick-replies/' . $r['id']) ?>" method="POST" onsubmit="return confirm('Eliminar?')">
                <?= csrf_field() ?>
                <input type="hidden" name="_method" value="DELETE">
                <button class="text-red-500 hover:bg-red-500/10 rounded p-1">&times;</button>
            </form>
        </div>
        <?php endforeach; endif; ?>
    </div>
</div>
<?php \App\Core\View::stop(); ?>
