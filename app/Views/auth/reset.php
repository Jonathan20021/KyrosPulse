<?php
/** @var string $token */
/** @var string $email */
\App\Core\View::extend('layouts.auth');
\App\Core\View::start('content');
$errors = $errors ?? [];
?>
<div>
    <div class="mb-7">
        <div class="w-14 h-14 rounded-2xl flex items-center justify-center mb-5" style="background: linear-gradient(135deg, rgba(16,185,129,.15), rgba(6,182,212,.15)); border: 1px solid rgba(16,185,129,.3);">
            <svg class="w-7 h-7 text-emerald-300" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M12 11c0-1.1.9-2 2-2s2 .9 2 2m-4 0v3m0-3h-2m2 0h2M7 7v10a2 2 0 002 2h6a2 2 0 002-2V7a2 2 0 00-2-2H9a2 2 0 00-2 2z"/></svg>
        </div>
        <h1 class="text-3xl font-black text-white mb-2 tracking-tight">Nueva contrasena</h1>
        <p class="text-slate-400 text-sm">Crea una contrasena segura para <span class="text-white font-semibold"><?= e($email) ?></span></p>
    </div>

    <?php \App\Core\View::include('components.flash'); ?>

    <form action="<?= url('/reset-password') ?>" method="POST" class="space-y-4" x-data="{ pwd: '', strength() { const p = this.pwd; let s = 0; if (p.length >= 8) s++; if (/[A-Z]/.test(p)) s++; if (/[0-9]/.test(p)) s++; if (/[^A-Za-z0-9]/.test(p)) s++; return s; } }">
        <?= csrf_field() ?>
        <input type="hidden" name="token" value="<?= e($token) ?>">
        <input type="hidden" name="email" value="<?= e($email) ?>">

        <div>
            <label class="label">Nueva contrasena</label>
            <input type="password" name="password" required minlength="8" x-model="pwd" class="input" placeholder="••••••••" autofocus>
            <div class="mt-1.5 flex gap-1">
                <div class="h-1 flex-1 rounded-full" :class="strength() >= 1 ? 'bg-rose-500' : 'bg-white/10'"></div>
                <div class="h-1 flex-1 rounded-full" :class="strength() >= 2 ? 'bg-amber-500' : 'bg-white/10'"></div>
                <div class="h-1 flex-1 rounded-full" :class="strength() >= 3 ? 'bg-cyan-500' : 'bg-white/10'"></div>
                <div class="h-1 flex-1 rounded-full" :class="strength() >= 4 ? 'bg-emerald-500' : 'bg-white/10'"></div>
            </div>
            <?php if ($e = error_for('password', $errors)): ?><p class="mt-1 text-xs text-rose-400"><?= e($e) ?></p><?php endif; ?>
        </div>
        <div>
            <label class="label">Confirmar contrasena</label>
            <input type="password" name="password_confirmation" required class="input" placeholder="••••••••">
        </div>

        <button type="submit" class="btn btn-primary btn-lg w-full justify-center">
            Restablecer contrasena
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
        </button>
    </form>

    <div class="mt-6 text-center text-sm">
        <a href="<?= url('/login') ?>" class="text-cyan-400 hover:text-cyan-300 font-semibold">← Volver al login</a>
    </div>
</div>
<?php \App\Core\View::stop(); ?>
