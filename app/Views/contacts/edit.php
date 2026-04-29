<?php
\App\Core\View::extend('layouts.app');
\App\Core\View::start('content');
$name = trim(($contact['first_name'] ?? '') . ' ' . ($contact['last_name'] ?? ''));
?>
<?php \App\Core\View::include('components.page_header', [
    'title' => 'Editar: ' . $name,
    'subtitle' => 'Actualiza la informacion del contacto.',
]); ?>

<form action="<?= url('/contacts/' . $contact['id']) ?>" method="POST" class="glass rounded-2xl p-6">
    <?= csrf_field() ?>
    <input type="hidden" name="_method" value="PUT">
    <?php \App\Core\View::include('contacts._form', ['contact' => $contact, 'tags' => $tags, 'agents' => $agents, 'allTags' => $allTags, 'errors' => $errors]); ?>
    <div class="flex justify-between gap-2 mt-6 pt-6 border-t dark:border-white/5 border-slate-200">
        <form action="<?= url('/contacts/' . $contact['id']) ?>" method="POST" onsubmit="return confirm('Eliminar contacto?')">
            <?= csrf_field() ?>
            <input type="hidden" name="_method" value="DELETE">
            <button type="submit" class="px-3 py-2 rounded-xl text-red-500 hover:bg-red-500/10 text-sm">Eliminar</button>
        </form>
        <div class="flex gap-2">
            <a href="<?= url('/contacts/' . $contact['id']) ?>" class="px-4 py-2 rounded-xl dark:text-slate-300 text-slate-600 dark:hover:bg-white/5 hover:bg-slate-100 text-sm">Cancelar</a>
            <button type="submit" class="px-5 py-2 rounded-xl text-white text-sm font-semibold" style="background:linear-gradient(135deg,#7C3AED,#06B6D4)">Guardar cambios</button>
        </div>
    </div>
</form>
<?php \App\Core\View::stop(); ?>
