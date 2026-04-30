<?php
\App\Core\View::extend('layouts.auth');
\App\Core\View::start('content');
$errors = $errors ?? [];
?>
<div>
    <div class="mb-7">
        <div class="inline-flex items-center gap-2 px-3 py-1 mb-4 rounded-full bg-emerald-500/10 border border-emerald-500/20">
            <svg class="w-3 h-3 text-emerald-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
            <span class="text-[11px] font-semibold text-emerald-300">14 dias gratis · Sin tarjeta · Cancela cuando quieras</span>
        </div>
        <h1 class="text-3xl font-black text-white mb-2 tracking-tight">Crea tu cuenta</h1>
        <p class="text-slate-400 text-sm">Configurate en menos de 2 minutos. Conecta tu primer numero y empieza a vender hoy.</p>
    </div>

    <?php \App\Core\View::include('components.flash'); ?>

    <form action="<?= url('/register') ?>" method="POST" class="space-y-3.5" x-data="{ pwd: '', strength() { const p = this.pwd; let s = 0; if (p.length >= 8) s++; if (/[A-Z]/.test(p)) s++; if (/[0-9]/.test(p)) s++; if (/[^A-Za-z0-9]/.test(p)) s++; return s; } }">
        <?= csrf_field() ?>

        <div>
            <label class="label">Nombre de tu empresa</label>
            <div class="relative">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                <input type="text" name="company_name" required value="<?= old('company_name') ?>" autofocus
                       class="input pl-10" placeholder="Mi Empresa SRL">
            </div>
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
                <input type="password" name="password" required minlength="8" x-model="pwd" class="input" placeholder="••••••••">
                <div class="mt-1.5 flex gap-1">
                    <div class="h-1 flex-1 rounded-full" :class="strength() >= 1 ? 'bg-rose-500' : 'bg-white/10'"></div>
                    <div class="h-1 flex-1 rounded-full" :class="strength() >= 2 ? 'bg-amber-500' : 'bg-white/10'"></div>
                    <div class="h-1 flex-1 rounded-full" :class="strength() >= 3 ? 'bg-cyan-500' : 'bg-white/10'"></div>
                    <div class="h-1 flex-1 rounded-full" :class="strength() >= 4 ? 'bg-emerald-500' : 'bg-white/10'"></div>
                </div>
                <p class="mt-1 text-[10px]" :class="strength() >= 3 ? 'text-emerald-400' : 'text-slate-500'">
                    <span x-show="strength() === 0">Minimo 8 caracteres</span>
                    <span x-show="strength() === 1" x-cloak>Debil — agrega mayusculas</span>
                    <span x-show="strength() === 2" x-cloak>Aceptable — agrega numeros o simbolos</span>
                    <span x-show="strength() === 3" x-cloak>Buena</span>
                    <span x-show="strength() >= 4" x-cloak>✓ Excelente</span>
                </p>
                <?php if ($e = error_for('password', $errors)): ?><p class="mt-1 text-xs text-rose-400"><?= e($e) ?></p><?php endif; ?>
            </div>
            <div>
                <label class="label">Confirmar</label>
                <input type="password" name="password_confirmation" required class="input" placeholder="••••••••">
            </div>
        </div>

        <label class="flex items-start gap-2 text-sm text-slate-300 cursor-pointer pt-1">
            <input type="checkbox" name="terms" value="1" required class="mt-1 w-4 h-4 rounded border-white/10 bg-white/5 text-violet-500 flex-shrink-0">
            <span class="text-xs leading-relaxed">Acepto los <a href="#" class="text-cyan-400 hover:text-cyan-300">Terminos</a> y la <a href="#" class="text-cyan-400 hover:text-cyan-300">Privacidad</a>.</span>
        </label>
        <?php if ($e = error_for('terms', $errors)): ?><p class="text-xs text-rose-400"><?= e($e) ?></p><?php endif; ?>

        <button type="submit" class="btn btn-primary btn-lg w-full justify-center group">
            Crear mi cuenta gratis
            <svg class="w-4 h-4 group-hover:translate-x-0.5 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
        </button>

        <div class="grid grid-cols-3 gap-2 pt-2">
            <div class="flex flex-col items-center text-center p-2 rounded-lg bg-white/[0.02] border border-white/5">
                <svg class="w-4 h-4 text-emerald-400 mb-1" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.7-9.3a1 1 0 00-1.4-1.4L9 10.6 7.7 9.3a1 1 0 00-1.4 1.4l2 2a1 1 0 001.4 0l4-4z" clip-rule="evenodd"/></svg>
                <span class="text-[10px] text-slate-400">Sin tarjeta</span>
            </div>
            <div class="flex flex-col items-center text-center p-2 rounded-lg bg-white/[0.02] border border-white/5">
                <svg class="w-4 h-4 text-emerald-400 mb-1" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.7-9.3a1 1 0 00-1.4-1.4L9 10.6 7.7 9.3a1 1 0 00-1.4 1.4l2 2a1 1 0 001.4 0l4-4z" clip-rule="evenodd"/></svg>
                <span class="text-[10px] text-slate-400">Setup 5 min</span>
            </div>
            <div class="flex flex-col items-center text-center p-2 rounded-lg bg-white/[0.02] border border-white/5">
                <svg class="w-4 h-4 text-emerald-400 mb-1" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.7-9.3a1 1 0 00-1.4-1.4L9 10.6 7.7 9.3a1 1 0 00-1.4 1.4l2 2a1 1 0 001.4 0l4-4z" clip-rule="evenodd"/></svg>
                <span class="text-[10px] text-slate-400">Cancela cuando quieras</span>
            </div>
        </div>
    </form>

    <div class="mt-6 text-center text-sm text-slate-400">
        Ya tienes cuenta?
        <a href="<?= url('/login') ?>" class="text-white font-semibold hover:text-violet-400 transition">Iniciar sesion →</a>
    </div>
</div>
<?php \App\Core\View::stop(); ?>
