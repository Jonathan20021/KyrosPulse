<?php
\App\Core\View::extend('layouts.app');
\App\Core\View::start('content');
?>
<?php \App\Core\View::include('components.page_header', [
    'title' => 'Importar contactos',
    'subtitle' => 'Sube un CSV con tus contactos. Soporta nombre, apellido, empresa, email, telefono, whatsapp, ciudad, pais, fuente, estado, notas.',
]); ?>

<form action="<?= url('/contacts/import') ?>" method="POST" enctype="multipart/form-data" class="glass rounded-2xl p-6 max-w-2xl">
    <?= csrf_field() ?>

    <div class="border-2 border-dashed dark:border-white/10 border-slate-200 rounded-2xl p-10 text-center mb-4">
        <div class="text-5xl mb-3">📂</div>
        <h3 class="font-bold dark:text-white text-slate-900 mb-1">Selecciona tu archivo CSV</h3>
        <p class="text-sm dark:text-slate-400 text-slate-500 mb-4">UTF-8, primera fila como encabezado.</p>
        <input type="file" name="csv" accept=".csv,text/csv" required class="block mx-auto text-sm dark:text-slate-300 text-slate-700">
    </div>

    <div class="glass-light rounded-xl p-4 mb-4">
        <h4 class="font-semibold dark:text-white text-slate-900 mb-2 text-sm">Encabezados aceptados</h4>
        <code class="text-xs dark:text-cyan-300 text-cyan-700 block bg-black/20 p-3 rounded-lg overflow-x-auto whitespace-nowrap">
            first_name, last_name, company, email, phone, whatsapp, city, country, source, status, notes
        </code>
        <p class="text-xs dark:text-slate-400 text-slate-500 mt-2">Tambien acepta variantes en espanol: nombre, apellido, empresa, correo, telefono, pais.</p>
    </div>

    <div class="flex justify-end gap-2">
        <a href="<?= url('/contacts') ?>" class="px-4 py-2 rounded-xl dark:text-slate-300 text-slate-600 dark:hover:bg-white/5 hover:bg-slate-100 text-sm">Cancelar</a>
        <button type="submit" class="px-5 py-2 rounded-xl text-white text-sm font-semibold" style="background:linear-gradient(135deg,#7C3AED,#06B6D4)">Importar</button>
    </div>
</form>
<?php \App\Core\View::stop(); ?>
