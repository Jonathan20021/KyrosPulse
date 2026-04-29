<?php
\App\Core\View::extend('layouts.error');
\App\Core\View::start('content');
?>
<div class="text-9xl font-black mb-4 bg-gradient-to-br from-purple-500 to-cyan-400 bg-clip-text text-transparent">404</div>
<h1 class="text-2xl font-bold mb-2">Pagina no encontrada</h1>
<p class="text-slate-400 mb-6">La ruta que buscas no existe o fue movida.</p>
<a href="<?= url('/') ?>" class="inline-block px-6 py-3 rounded-xl text-white font-semibold" style="background:linear-gradient(135deg,#7C3AED,#06B6D4)">Volver al inicio</a>
<?php \App\Core\View::stop(); ?>
