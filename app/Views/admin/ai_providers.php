<?php
\App\Core\View::extend('layouts.admin');
\App\Core\View::start('content');
$providers = $providers ?? [];
?>

<div class="mb-6 flex items-center justify-between flex-wrap gap-3">
    <div>
        <h1 class="text-2xl font-bold text-white mb-1">Proveedores IA globales</h1>
        <p class="text-sm text-slate-400">Configura las API keys del SaaS. Los tenants sin key propia usaran lo que asignes aqui.</p>
    </div>
    <button onclick="document.getElementById('newProviderForm').scrollIntoView({behavior:'smooth'})" class="px-4 py-2 rounded-xl text-white text-sm font-semibold shadow-lg shadow-violet-500/30" style="background:linear-gradient(135deg,#7C3AED,#06B6D4);">+ Nuevo proveedor</button>
</div>

<?php if (empty($providers)): ?>
<div class="admin-card rounded-2xl p-10 text-center mb-6">
    <div class="text-5xl mb-3">🤖</div>
    <h3 class="font-bold text-white mb-1">Aun no hay proveedores IA globales</h3>
    <p class="text-sm text-slate-400">Agrega abajo una key Claude u OpenAI que el SaaS usara como fallback para tenants sin key propia.</p>
</div>
<?php else: ?>
<div class="space-y-3 mb-6">
    <?php foreach ($providers as $p):
        $color = $p['provider'] === 'openai' ? '#10B981' : '#A78BFA';
        $usagePct = (!empty($p['monthly_token_limit']) && (int) $p['monthly_token_limit'] > 0)
            ? min(100, (int) round(((int) $p['_used_period']) / max(1, (int) $p['monthly_token_limit']) * 100))
            : 0;
    ?>
    <details class="admin-card rounded-2xl">
        <summary class="cursor-pointer list-none p-4 flex items-center justify-between gap-3 flex-wrap">
            <div class="flex items-center gap-3 min-w-0">
                <div class="w-12 h-12 rounded-xl flex items-center justify-center flex-shrink-0" style="background: <?= $color ?>22; color: <?= $color ?>;">
                    <?= $p['provider'] === 'openai' ? '🟢' : '🟣' ?>
                </div>
                <div class="min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="font-bold text-white"><?= e($p['name']) ?></span>
                        <span class="text-[10px] uppercase tracking-wider px-2 py-0.5 rounded font-bold" style="background: <?= $color ?>22; color: <?= $color ?>;"><?= e(strtoupper($p['provider'])) ?></span>
                        <?php if (!empty($p['is_default'])): ?>
                        <span class="text-[10px] px-2 py-0.5 rounded bg-amber-500/15 text-amber-400">★ Default</span>
                        <?php endif; ?>
                        <?php if (!empty($p['is_active'])): ?>
                        <span class="text-[10px] px-2 py-0.5 rounded bg-emerald-500/15 text-emerald-300">Activo</span>
                        <?php else: ?>
                        <span class="text-[10px] px-2 py-0.5 rounded bg-slate-500/20 text-slate-400">Inactivo</span>
                        <?php endif; ?>
                    </div>
                    <div class="text-xs text-slate-500 mt-0.5 font-mono"><?= e($p['model']) ?></div>
                </div>
            </div>
            <div class="text-right flex-shrink-0 text-xs">
                <div class="text-slate-300 font-mono"><?= number_format((int) $p['_used_period']) ?> tokens</div>
                <?php if (!empty($p['monthly_token_limit'])): ?>
                <div class="text-slate-500">de <?= number_format((int) $p['monthly_token_limit']) ?> · <?= $usagePct ?>%</div>
                <?php endif; ?>
                <div class="text-slate-500"><?= (int) $p['_tenants_count'] ?> tenants</div>
            </div>
        </summary>

        <div class="border-t border-white/5 p-4">
            <?php if (!empty($p['monthly_token_limit'])): ?>
            <div class="mb-4">
                <div class="flex items-center justify-between text-xs mb-1">
                    <span class="text-slate-400">Consumo del mes</span>
                    <span class="text-slate-300 font-mono"><?= number_format((int) $p['_used_period']) ?> / <?= number_format((int) $p['monthly_token_limit']) ?></span>
                </div>
                <div class="h-2 rounded-full bg-white/5 overflow-hidden">
                    <div class="h-full rounded-full <?= $usagePct >= 90 ? 'bg-rose-500' : ($usagePct >= 70 ? 'bg-amber-500' : '') ?>"
                         style="width: <?= $usagePct ?>%; <?= $usagePct < 70 ? 'background: linear-gradient(90deg,#7C3AED,#06B6D4);' : '' ?>"></div>
                </div>
            </div>
            <?php endif; ?>

            <form action="<?= url('/admin/ai-providers/' . $p['id']) ?>" method="POST" class="grid md:grid-cols-3 gap-3">
                <?= csrf_field() ?>
                <input type="hidden" name="_method" value="PUT">
                <div class="md:col-span-2">
                    <label class="text-[10px] uppercase tracking-wider text-slate-400">Nombre interno</label>
                    <input type="text" name="name" value="<?= e($p['name']) ?>" required class="w-full mt-1 px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-white text-sm">
                </div>
                <div>
                    <label class="text-[10px] uppercase tracking-wider text-slate-400">Proveedor</label>
                    <select name="provider" class="w-full mt-1 px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-white text-sm">
                        <option value="claude" <?= $p['provider'] === 'claude' ? 'selected' : '' ?>>Claude (Anthropic)</option>
                        <option value="openai" <?= $p['provider'] === 'openai' ? 'selected' : '' ?>>OpenAI (GPT)</option>
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label class="text-[10px] uppercase tracking-wider text-slate-400">API Key (deja en blanco para no cambiar)</label>
                    <input type="password" name="api_key" placeholder="••••••••" class="w-full mt-1 px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-white text-sm font-mono">
                </div>
                <div>
                    <label class="text-[10px] uppercase tracking-wider text-slate-400">Modelo</label>
                    <input type="text" name="model" value="<?= e($p['model']) ?>" class="w-full mt-1 px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-white text-sm font-mono">
                </div>
                <div class="md:col-span-3">
                    <label class="text-[10px] uppercase tracking-wider text-slate-400">Descripcion (opcional)</label>
                    <input type="text" name="description" value="<?= e((string) ($p['description'] ?? '')) ?>" class="w-full mt-1 px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-white text-sm">
                </div>
                <div>
                    <label class="text-[10px] uppercase tracking-wider text-slate-400">Prioridad</label>
                    <input type="number" name="priority" value="<?= (int) $p['priority'] ?>" min="1" max="999" class="w-full mt-1 px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-white text-sm">
                </div>
                <div>
                    <label class="text-[10px] uppercase tracking-wider text-slate-400">Limite tokens/mes (vacio = ilimitado)</label>
                    <input type="number" name="monthly_token_limit" value="<?= e((string) ($p['monthly_token_limit'] ?? '')) ?>" class="w-full mt-1 px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-white text-sm">
                </div>
                <div class="flex items-end gap-3">
                    <label class="flex items-center gap-2 text-sm text-slate-300">
                        <input type="checkbox" name="is_active" value="1" <?= !empty($p['is_active']) ? 'checked' : '' ?> class="w-4 h-4 rounded">
                        Activo
                    </label>
                    <label class="flex items-center gap-2 text-sm text-slate-300">
                        <input type="checkbox" name="is_default" value="1" <?= !empty($p['is_default']) ? 'checked' : '' ?> class="w-4 h-4 rounded">
                        Default
                    </label>
                </div>

                <div class="md:col-span-3 flex items-center justify-between gap-2 pt-3 border-t border-white/5 flex-wrap">
                    <div class="flex items-center gap-2">
                        <button type="button" onclick="testProvider(<?= (int) $p['id'] ?>)" class="px-3 py-1.5 rounded-lg text-xs glass text-slate-300 hover:text-white">Probar conexion</button>
                        <span id="testResult-<?= (int) $p['id'] ?>" class="text-xs"></span>
                    </div>
                    <div class="flex items-center gap-2">
                        <form action="<?= url('/admin/ai-providers/' . $p['id']) ?>" method="POST" class="inline" onsubmit="return confirm('Eliminar? Los tenants asignados quedaran sin proveedor IA.')">
                            <?= csrf_field() ?>
                            <input type="hidden" name="_method" value="DELETE">
                            <button class="px-3 py-1.5 rounded-lg text-xs text-rose-400 hover:bg-rose-500/10">Eliminar</button>
                        </form>
                        <button type="submit" class="px-4 py-2 rounded-lg text-white text-sm font-semibold" style="background:linear-gradient(135deg,#7C3AED,#06B6D4);">Guardar cambios</button>
                    </div>
                </div>
            </form>
        </div>
    </details>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Nuevo proveedor -->
<form id="newProviderForm" action="<?= url('/admin/ai-providers') ?>" method="POST" class="admin-card rounded-2xl p-5">
    <?= csrf_field() ?>
    <h3 class="font-bold text-white mb-3">Nuevo proveedor IA global</h3>
    <div class="grid md:grid-cols-3 gap-3">
        <div class="md:col-span-2">
            <label class="text-[10px] uppercase tracking-wider text-slate-400">Nombre interno</label>
            <input type="text" name="name" required placeholder="Claude Premium / OpenAI Backup" class="w-full mt-1 px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-white text-sm">
        </div>
        <div>
            <label class="text-[10px] uppercase tracking-wider text-slate-400">Proveedor</label>
            <select name="provider" class="w-full mt-1 px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-white text-sm">
                <option value="claude">Claude (Anthropic)</option>
                <option value="openai">OpenAI (GPT)</option>
            </select>
        </div>
        <div class="md:col-span-2">
            <label class="text-[10px] uppercase tracking-wider text-slate-400">API Key</label>
            <input type="password" name="api_key" required placeholder="sk-ant-... o sk-..." class="w-full mt-1 px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-white text-sm font-mono">
        </div>
        <div>
            <label class="text-[10px] uppercase tracking-wider text-slate-400">Modelo</label>
            <input type="text" name="model" required placeholder="claude-sonnet-4-6 / gpt-4o-mini" class="w-full mt-1 px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-white text-sm font-mono">
        </div>
        <div class="md:col-span-3">
            <label class="text-[10px] uppercase tracking-wider text-slate-400">Descripcion (visible solo en admin)</label>
            <input type="text" name="description" class="w-full mt-1 px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-white text-sm">
        </div>
        <div>
            <label class="text-[10px] uppercase tracking-wider text-slate-400">Prioridad</label>
            <input type="number" name="priority" value="100" min="1" max="999" class="w-full mt-1 px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-white text-sm">
        </div>
        <div>
            <label class="text-[10px] uppercase tracking-wider text-slate-400">Limite tokens/mes (opcional)</label>
            <input type="number" name="monthly_token_limit" placeholder="ilimitado" class="w-full mt-1 px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-white text-sm">
        </div>
        <div class="flex items-end gap-3">
            <label class="flex items-center gap-2 text-sm text-slate-300">
                <input type="checkbox" name="is_active" value="1" checked class="w-4 h-4 rounded">
                Activo
            </label>
            <label class="flex items-center gap-2 text-sm text-slate-300">
                <input type="checkbox" name="is_default" value="1" class="w-4 h-4 rounded">
                Default
            </label>
        </div>
    </div>
    <button type="submit" class="mt-4 px-5 py-2 rounded-xl text-white text-sm font-semibold shadow-lg shadow-violet-500/30" style="background:linear-gradient(135deg,#7C3AED,#06B6D4);">Crear proveedor</button>
</form>

<script>
const csrf = document.querySelector('meta[name="csrf-token"]').content;
async function testProvider(id) {
    const out = document.getElementById('testResult-' + id);
    out.textContent = 'Probando...';
    out.className = 'text-xs text-slate-400';
    try {
        const res = await fetch('<?= url('/admin/ai-providers/') ?>' + id + '/test', {
            method: 'POST',
            headers: { 'X-CSRF-Token': csrf, 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await res.json();
        if (data.success) { out.textContent = '✓ ' + data.message; out.className = 'text-xs text-emerald-400'; }
        else { out.textContent = '✗ ' + (data.error || 'Fallo'); out.className = 'text-xs text-rose-400'; }
    } catch (e) { out.textContent = '✗ ' + e.message; out.className = 'text-xs text-rose-400'; }
}
</script>

<?php \App\Core\View::stop(); ?>
