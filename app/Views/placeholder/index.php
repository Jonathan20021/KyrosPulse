<?php
/** @var string $title */
/** @var string $subtitle */
\App\Core\View::extend('layouts.app');
\App\Core\View::start('content');
?>
<div class="mb-6">
    <h1 class="text-2xl font-extrabold dark:text-white text-slate-900"><?= e($title) ?></h1>
    <p class="dark:text-slate-400 text-slate-600 text-sm"><?= e($subtitle) ?></p>
</div>

<div class="glass rounded-2xl p-12 text-center">
    <div class="w-24 h-24 mx-auto rounded-2xl flex items-center justify-center mb-6 text-5xl" style="background:linear-gradient(135deg,rgba(124,58,237,.2),rgba(6,182,212,.2))">
        🚧
    </div>
    <h2 class="text-2xl font-bold dark:text-white text-slate-900 mb-2">Modulo en construccion</h2>
    <p class="dark:text-slate-400 text-slate-600 max-w-md mx-auto mb-6">
        Esta seccion ya tiene su esquema de base de datos, controladores y servicios listos. La interfaz se habilita en la siguiente iteracion.
    </p>
    <a href="<?= url('/dashboard') ?>" class="inline-block px-5 py-2.5 rounded-xl text-white text-sm font-semibold" style="background:linear-gradient(135deg,#7C3AED,#06B6D4)">
        Volver al Dashboard
    </a>
</div>
<?php \App\Core\View::stop(); ?>
