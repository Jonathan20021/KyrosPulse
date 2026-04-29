<?php
\App\Core\View::extend('layouts.app');
\App\Core\View::start('content');
?>
<?php \App\Core\View::include('components.page_header', ['title' => 'Mi perfil', 'subtitle' => 'Tu informacion personal y preferencias.']); ?>
<?php \App\Core\View::include('settings._tabs', ['tab' => 'profile']); ?>

<form action="<?= url('/settings/profile') ?>" method="POST" class="glass rounded-2xl p-5 max-w-3xl">
    <?= csrf_field() ?>
    <input type="hidden" name="_method" value="PUT">
    <div class="grid md:grid-cols-2 gap-3">
        <div>
            <label class="text-xs uppercase tracking-wider dark:text-slate-400 text-slate-500">Nombre</label>
            <input type="text" name="first_name" value="<?= e((string) ($user['first_name'] ?? '')) ?>" class="w-full mt-1 px-3 py-2 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-lg dark:text-white text-slate-900">
        </div>
        <div>
            <label class="text-xs uppercase tracking-wider dark:text-slate-400 text-slate-500">Apellido</label>
            <input type="text" name="last_name" value="<?= e((string) ($user['last_name'] ?? '')) ?>" class="w-full mt-1 px-3 py-2 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-lg dark:text-white text-slate-900">
        </div>
        <div>
            <label class="text-xs uppercase tracking-wider dark:text-slate-400 text-slate-500">Email (no editable)</label>
            <input type="email" disabled value="<?= e((string) ($user['email'] ?? '')) ?>" class="w-full mt-1 px-3 py-2 dark:bg-white/5 bg-slate-100 border dark:border-white/10 border-slate-200 rounded-lg dark:text-slate-400 text-slate-500">
        </div>
        <div>
            <label class="text-xs uppercase tracking-wider dark:text-slate-400 text-slate-500">Telefono</label>
            <input type="tel" name="phone" value="<?= e((string) ($user['phone'] ?? '')) ?>" class="w-full mt-1 px-3 py-2 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-lg dark:text-white text-slate-900">
        </div>
        <div class="md:col-span-2">
            <label class="text-xs uppercase tracking-wider dark:text-slate-400 text-slate-500">Firma del agente (HTML)</label>
            <textarea name="signature" rows="3" class="w-full mt-1 px-3 py-2 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-lg dark:text-white text-slate-900"><?= e((string) ($user['signature'] ?? '')) ?></textarea>
        </div>
    </div>

    <h3 class="font-bold dark:text-white text-slate-900 mt-6 mb-3">Cambiar contrasena</h3>
    <div class="grid md:grid-cols-2 gap-3">
        <input type="password" name="new_password" minlength="8" placeholder="Nueva contrasena" class="px-3 py-2 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-lg dark:text-white text-slate-900">
        <input type="password" name="new_password_confirmation" minlength="8" placeholder="Confirmar contrasena" class="px-3 py-2 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-lg dark:text-white text-slate-900">
    </div>

    <div class="flex justify-end mt-6">
        <button type="submit" class="px-5 py-2 rounded-xl text-white text-sm font-semibold" style="background:linear-gradient(135deg,#7C3AED,#06B6D4)">Guardar perfil</button>
    </div>
</form>
<?php \App\Core\View::stop(); ?>
