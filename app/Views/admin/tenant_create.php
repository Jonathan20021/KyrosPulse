<?php
\App\Core\View::extend('layouts.admin');
\App\Core\View::start('content');
?>

<div class="mb-6 flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-bold text-white mb-1">Nueva empresa</h1>
        <p class="text-sm text-slate-400">Crea un tenant desde cero, con owner y plan asignado.</p>
    </div>
    <a href="<?= url('/admin/tenants') ?>" class="text-sm text-slate-400 hover:text-white px-3 py-1.5 rounded-lg hover:bg-white/5">← Volver</a>
</div>

<form action="<?= url('/admin/tenants') ?>" method="POST" class="space-y-4 max-w-4xl">
    <?= csrf_field() ?>

    <!-- Empresa -->
    <div class="admin-card rounded-2xl p-5">
        <h3 class="font-bold text-white mb-3 flex items-center gap-2"><span class="text-xl">🏢</span> Datos de la empresa</h3>
        <div class="grid md:grid-cols-2 gap-3">
            <div>
                <label class="text-[10px] uppercase tracking-wider text-slate-400">Nombre comercial *</label>
                <input type="text" name="company_name" required class="w-full mt-1 px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-white text-sm">
            </div>
            <div>
                <label class="text-[10px] uppercase tracking-wider text-slate-400">Email contacto *</label>
                <input type="email" name="company_email" required class="w-full mt-1 px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-white text-sm">
            </div>
            <div>
                <label class="text-[10px] uppercase tracking-wider text-slate-400">Telefono</label>
                <input type="text" name="company_phone" class="w-full mt-1 px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-white text-sm">
            </div>
            <div class="grid grid-cols-3 gap-2">
                <div>
                    <label class="text-[10px] uppercase tracking-wider text-slate-400">Pais</label>
                    <input type="text" name="country" value="DO" maxlength="2" class="w-full mt-1 px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-white text-sm">
                </div>
                <div>
                    <label class="text-[10px] uppercase tracking-wider text-slate-400">Moneda</label>
                    <input type="text" name="currency" value="USD" maxlength="3" class="w-full mt-1 px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-white text-sm">
                </div>
                <div>
                    <label class="text-[10px] uppercase tracking-wider text-slate-400">Idioma</label>
                    <input type="text" name="language" value="es" maxlength="5" class="w-full mt-1 px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-white text-sm">
                </div>
            </div>
        </div>
    </div>

    <!-- Plan + estado -->
    <div class="admin-card rounded-2xl p-5">
        <h3 class="font-bold text-white mb-3 flex items-center gap-2"><span class="text-xl">📦</span> Licencia</h3>
        <div class="grid md:grid-cols-3 gap-3">
            <div>
                <label class="text-[10px] uppercase tracking-wider text-slate-400">Plan</label>
                <select name="plan_id" class="w-full mt-1 px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-white text-sm">
                    <option value="">— Sin plan —</option>
                    <?php foreach ($plans as $p): ?>
                    <option value="<?= (int) $p['id'] ?>"><?= e($p['name']) ?> · $<?= number_format((float) $p['price_monthly']) ?>/mes</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="text-[10px] uppercase tracking-wider text-slate-400">Estado inicial</label>
                <select name="status" class="w-full mt-1 px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-white text-sm">
                    <option value="trial" selected>Trial</option>
                    <option value="active">Activo</option>
                    <option value="suspended">Suspendido</option>
                </select>
            </div>
            <div>
                <label class="text-[10px] uppercase tracking-wider text-slate-400">Dias de trial</label>
                <input type="number" name="trial_days" value="14" min="1" max="180" class="w-full mt-1 px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-white text-sm">
            </div>
        </div>
    </div>

    <!-- IA -->
    <div class="admin-card rounded-2xl p-5">
        <h3 class="font-bold text-white mb-3 flex items-center gap-2"><span class="text-xl">🤖</span> Asignacion de IA</h3>
        <div class="grid md:grid-cols-2 gap-3">
            <div>
                <label class="text-[10px] uppercase tracking-wider text-slate-400">Proveedor IA global asignado</label>
                <select name="global_ai_provider_id" class="w-full mt-1 px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-white text-sm">
                    <option value="">— Sin asignar (el tenant debera poner su propia key) —</option>
                    <?php foreach ($providers as $p): ?>
                    <option value="<?= (int) $p['id'] ?>"><?= e($p['name']) ?> (<?= e(strtoupper($p['provider'])) ?> · <?= e($p['model']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="text-[10px] uppercase tracking-wider text-slate-400">Cuota mensual de tokens (vacio = ilimitado)</label>
                <input type="number" name="ai_token_quota" placeholder="ej: 1000000" class="w-full mt-1 px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-white text-sm">
            </div>
        </div>
        <p class="text-xs text-slate-500 mt-3">Si asignas un proveedor IA global, se activa automaticamente el bot del tenant. El tenant tambien podra configurar su propia key en cualquier momento (su key tiene prioridad).</p>
    </div>

    <!-- Owner -->
    <div class="admin-card rounded-2xl p-5">
        <h3 class="font-bold text-white mb-3 flex items-center gap-2"><span class="text-xl">👤</span> Owner (administrador del tenant)</h3>
        <div class="grid md:grid-cols-2 gap-3">
            <div>
                <label class="text-[10px] uppercase tracking-wider text-slate-400">Nombre *</label>
                <input type="text" name="owner_first" required class="w-full mt-1 px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-white text-sm">
            </div>
            <div>
                <label class="text-[10px] uppercase tracking-wider text-slate-400">Apellido *</label>
                <input type="text" name="owner_last" required class="w-full mt-1 px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-white text-sm">
            </div>
            <div>
                <label class="text-[10px] uppercase tracking-wider text-slate-400">Email *</label>
                <input type="email" name="owner_email" required class="w-full mt-1 px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-white text-sm">
            </div>
            <div>
                <label class="text-[10px] uppercase tracking-wider text-slate-400">Password (vacio = generar automatica)</label>
                <input type="text" name="owner_password" placeholder="min 8 caracteres" class="w-full mt-1 px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-white text-sm font-mono">
            </div>
        </div>
        <p class="text-xs text-slate-500 mt-3">El email queda verificado automaticamente. Compartele al owner sus credenciales por canal seguro.</p>
    </div>

    <div class="flex justify-end gap-2">
        <a href="<?= url('/admin/tenants') ?>" class="px-4 py-2 rounded-lg text-sm text-slate-300 bg-white/5">Cancelar</a>
        <button type="submit" class="px-6 py-2.5 rounded-xl text-white text-sm font-semibold shadow-xl shadow-violet-500/40" style="background:linear-gradient(135deg,#7C3AED,#06B6D4);">Crear empresa</button>
    </div>
</form>

<?php \App\Core\View::stop(); ?>
