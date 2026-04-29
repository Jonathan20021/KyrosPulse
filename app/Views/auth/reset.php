<?php
/** @var string $token */
/** @var string $email */
\App\Core\View::extend('layouts.auth');
\App\Core\View::start('content');
$errors = $errors ?? [];
?>
<div class="w-full max-w-md">
    <div class="glass rounded-3xl p-8 shadow-2xl">
        <div class="text-center mb-8">
            <h1 class="text-3xl font-extrabold text-white mb-2">Nueva contrasena</h1>
            <p class="text-slate-400">Crea una contrasena segura</p>
        </div>

        <?php \App\Core\View::include('components.flash'); ?>

        <form action="<?= url('/reset-password') ?>" method="POST" class="space-y-5">
            <?= csrf_field() ?>
            <input type="hidden" name="token" value="<?= e($token) ?>">
            <input type="hidden" name="email" value="<?= e($email) ?>">

            <div>
                <label class="block text-sm font-medium text-slate-300 mb-2">Nueva contrasena</label>
                <input type="password" name="password" required minlength="8"
                       class="w-full px-4 py-3 bg-white/5 border border-white/10 rounded-xl text-white"
                       placeholder="********">
                <?php if ($e = error_for('password', $errors)): ?><p class="mt-1 text-xs text-red-400"><?= e($e) ?></p><?php endif; ?>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-300 mb-2">Confirmar contrasena</label>
                <input type="password" name="password_confirmation" required
                       class="w-full px-4 py-3 bg-white/5 border border-white/10 rounded-xl text-white"
                       placeholder="********">
            </div>
            <button type="submit" class="btn-primary w-full text-white py-3 rounded-xl font-semibold" style="background: linear-gradient(135deg,#7C3AED,#06B6D4)">
                Restablecer contrasena
            </button>
        </form>
    </div>
</div>
<?php \App\Core\View::stop(); ?>
