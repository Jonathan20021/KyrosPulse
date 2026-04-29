<?php
\App\Core\View::extend('layouts.app');
\App\Core\View::start('content');
?>
<?php \App\Core\View::include('components.page_header', [
    'title' => 'Nuevo contacto',
    'subtitle' => 'Crear un cliente, lead o prospecto.',
]); ?>

<form action="<?= url('/contacts') ?>" method="POST" class="glass rounded-2xl p-6">
    <?= csrf_field() ?>
    <?php \App\Core\View::include('contacts._form', ['contact' => null, 'tags' => [], 'agents' => $agents, 'allTags' => $allTags, 'errors' => $errors]); ?>
    <div class="flex justify-end gap-2 mt-6 pt-6 border-t dark:border-white/5 border-slate-200">
        <a href="<?= url('/contacts') ?>" class="px-4 py-2 rounded-xl dark:text-slate-300 text-slate-600 dark:hover:bg-white/5 hover:bg-slate-100 text-sm">Cancelar</a>
        <button type="submit" class="px-5 py-2 rounded-xl text-white text-sm font-semibold" style="background:linear-gradient(135deg,#7C3AED,#06B6D4)">Guardar contacto</button>
    </div>
</form>
<?php \App\Core\View::stop(); ?>
