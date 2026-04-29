<?php
\App\Core\View::extend('layouts.auth');
\App\Core\View::start('content');
$errors = $errors ?? [];
?>
<div>
    <div class="mb-8">
        <div class="inline-flex items-center gap-2 px-3 py-1 mb-4 rounded-full bg-emerald-500/10 border border-emerald-500/20">
            <svg class="w-3 h-3 text-emerald-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
            <span class="text-xs font-semibold text-emerald-300">14 dias gratis · Sin tarjeta</span>
        </div>
        <h1 class="heading-md text-white mb-2">Crea tu cuenta</h1>
        <p class="text-slate-400 text-sm">Configurate en menos de 2 minutos.</p>
    </div>

    <?php \App\Core\View::include('components.flash'); ?>

    <form action="<?= url('/register') ?>" method="POST" class="space-y-4">
        <?= csrf_field() ?>

        <div>
            <label class="label">Nombre de tu empresa</label>
            <input type="text" name="company_name" required value="<?= old('company_name') ?>" autofocus
                   class="input" placeholder="Mi Empresa SRL">
            <?php if ($e = error_for('company_name', $errors)): ?><p class="mt-1.5 text-xs text-rose-400"><?= e($e) ?></p><?php endif; ?>
        </div>

        <div class="grid sm:grid-cols-2 gap-3">
            <div>
                <label class="label">Nombre</label>
                <input type="text" name="first_name" required value="<?= old('first_name') ?>" class="input" placeholder="Juan">
                <?php if ($e = error_for('first_name', $errors)): ?><p class="mt-1.5 text-xs text-rose-400"><?= e($e) ?></p><?php endif; ?>
            </div>
            <div>
                <label class="label">Apellido</label>
                <input type="text" name="last_name" required value="<?= old('last_name') ?>" class="input" placeholder="Perez">
                <?php if ($e = error_for('last_name', $errors)): ?><p class="mt-1.5 text-xs text-rose-400"><?= e($e) ?></p><?php endif; ?>
            </div>
        </div>

        <div class="grid sm:grid-cols-2 gap-3">
            <div>
                <label class="label">Email</label>
                <input type="email" name="email" required value="<?= old('email') ?>" class="input" placeholder="tu@empresa.com">
                <?php if ($e = error_for('email', $errors)): ?><p class="mt-1.5 text-xs text-rose-400"><?= e($e) ?></p><?php endif; ?>
            </div>
            <div>
                <label class="label">Telefono</label>
                <input type="tel" name="phone" value="<?= old('phone') ?>" class="input" placeholder="+1 809 555 1234">
            </div>
        </div>

        <div class="grid sm:grid-cols-2 gap-3">
            <div>
                <label class="label">Contrasena</label>
                <input type="password" name="password" required minlength="8" class="input" placeholder="••••••••">
                <?php if ($e = error_for('password', $errors)): ?><p class="mt-1.5 text-xs text-rose-400"><?= e($e) ?></p>
                <?php else: ?><p class="mt-1 text-[10px] text-slate-500">Minimo 8 caracteres</p><?php endif; ?>
            </div>
            <div>
                <label class="label">Confirmar</label>
                <input type="password" name="password_confirmation" required class="input" placeholder="••••••••">
            </div>
        </div>

        <label class="flex items-start gap-2 text-sm text-slate-300 cursor-pointer pt-2">
            <input type="checkbox" name="terms" value="1" required class="mt-1 w-4 h-4 rounded border-white/10 bg-white/5 text-violet-500 flex-shrink-0">
            <span>Acepto los <a href="#" class="text-cyan-400 hover:text-cyan-300">Terminos</a> y la <a href="#" class="text-cyan-400 hover:text-cyan-300">Privacidad</a> de Kyros Pulse.</span>
        </label>
        <?php if ($e = error_for('terms', $errors)): ?><p class="text-xs text-rose-400"><?= e($e) ?></p><?php endif; ?>

        <button type="submit" class="btn btn-primary btn-lg w-full justify-center">
            Crear mi cuenta gratis
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
        </button>
    </form>

    <div class="mt-6 text-center text-sm text-slate-400">
        Ya tienes cuenta?
        <a href="<?= url('/login') ?>" class="text-white font-semibold hover:text-violet-400 transition">Iniciar sesion</a>
    </div>
</div>
<?php \App\Core\View::stop(); ?>
