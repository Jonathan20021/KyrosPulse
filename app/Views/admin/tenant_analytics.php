<?php
/**
 * @var array $tenant
 * @var array $quota
 * @var array $stats
 */
\App\Core\View::extend('layouts.admin');
\App\Core\View::start('content');

$q = $quota ?? [];
$pct = (int) ($q['used_pct'] ?? 0);
$barColor = $pct >= 95 ? '#BE123C' : ($pct >= 80 ? '#F59E0B' : '#10B981');
?>
<?php \App\Core\View::include('components.page_header', [
    'title'    => $tenant['name'],
    'subtitle' => 'Tenant #' . (int) $tenant['id'] . ' · ' . $tenant['status'] . ' · plan: ' . ($tenant['plan_name'] ?? '—'),
]); ?>

<?php if ($flash = flash('success')): ?>
<div class="mb-4 p-3 rounded-xl border text-sm" style="background: rgba(16,185,129,.08); border-color: rgba(16,185,129,.3); color:#6EE7B7;"><?= e((string) $flash) ?></div>
<?php endif; ?>

<div class="mb-4 flex items-center gap-2">
    <a href="<?= url('/admin/analytics') ?>" class="text-xs px-3 py-1.5 rounded-lg border text-slate-300" style="border-color: rgba(255,255,255,.15);">← Analytics</a>
    <a href="<?= url('/admin/tenants') ?>" class="text-xs px-3 py-1.5 rounded-lg border text-slate-300" style="border-color: rgba(255,255,255,.15);">Empresas →</a>
</div>

<!-- Stats principales -->
<div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-5">
    <?php foreach ([
        ['👥', 'Usuarios',         number_format((int) ($stats['users'] ?? 0))],
        ['📞', 'Contactos',        number_format((int) ($stats['contacts'] ?? 0))],
        ['🛒', 'Ordenes 30d',      number_format((int) ($stats['orders_30d'] ?? 0))],
        ['💵', 'Ingresos 30d',     number_format((float) ($stats['revenue_30d'] ?? 0), 2)],
        ['🧠', 'Agent runs 30d',   number_format((int) ($stats['agent_runs_30d'] ?? 0))],
        ['💎', 'Costo IA 30d',     '$' . number_format((float) ($stats['ai_cost_30d'] ?? 0), 4)],
        ['🪄', 'Workflows activos',number_format((int) ($stats['workflows_active'] ?? 0))],
        ['🪝', 'Webhook endpoints',number_format((int) ($stats['webhook_endpoints'] ?? 0))],
    ] as [$em, $lbl, $val]): ?>
    <div class="glass p-3.5">
        <div class="flex items-center justify-between mb-1">
            <span class="text-[10px] uppercase tracking-wider font-semibold text-slate-400"><?= e($lbl) ?></span>
            <span class="text-base"><?= $em ?></span>
        </div>
        <div class="text-lg font-bold text-white"><?= $val ?></div>
    </div>
    <?php endforeach; ?>
</div>

<!-- API quota override -->
<div class="glass p-5 mb-5">
    <div class="flex items-start justify-between gap-3 flex-wrap mb-4">
        <div>
            <h3 class="font-bold text-white mb-0.5">🔧 Cuota mensual de API</h3>
            <p class="text-sm text-slate-400">Override la cuota del plan para este tenant. Util para clientes enterprise o concesiones temporales.</p>
        </div>
        <form action="<?= url('/admin/tenants/' . (int) $tenant['id'] . '/quota/reset') ?>" method="POST" onsubmit="return confirm('Reset del periodo? Devuelve contador a 0 y reinicia ventana de 30 dias.')">
            <?= csrf_field() ?>
            <button class="text-xs px-3 py-1.5 rounded-lg border" style="border-color: rgba(255,255,255,.15); color: #fff;">↻ Resetear periodo</button>
        </form>
    </div>

    <div class="grid md:grid-cols-2 gap-4 mb-4">
        <div>
            <div class="text-[10px] uppercase tracking-wider font-semibold text-slate-400 mb-1">Uso actual</div>
            <div class="text-2xl font-bold text-white mb-1">
                <?php if (!empty($q['unlimited'])): ?>
                    Ilimitado
                <?php elseif (!empty($q['no_access'])): ?>
                    Sin acceso
                <?php else: ?>
                    <?= number_format((int) ($q['used'] ?? 0)) ?> <span class="text-base font-normal text-slate-400">/ <?= number_format((int) ($q['quota'] ?? 0)) ?></span>
                <?php endif; ?>
            </div>
            <?php if (!empty($q['quota']) && empty($q['unlimited']) && (int) $q['quota'] > 0): ?>
            <div class="h-2 rounded-full overflow-hidden bg-white/5 mb-1">
                <div class="h-full" style="width: <?= $pct ?>%; background: <?= $barColor ?>;"></div>
            </div>
            <?php endif; ?>
            <div class="text-[11px] text-slate-500">
                Renueva el <?= e(date('d M Y', strtotime((string) ($q['period_resets_at'] ?? 'now')))) ?>
                <?php if (!empty($q['is_overridden'])): ?>
                · <span class="text-violet-300">override admin activo</span>
                <?php endif; ?>
            </div>
        </div>

        <form action="<?= url('/admin/tenants/' . (int) $tenant['id'] . '/quota') ?>" method="POST" class="self-end">
            <?= csrf_field() ?>
            <label class="block text-[10px] uppercase tracking-wider font-semibold text-slate-400 mb-1">Override (vacio = usar plan)</label>
            <div class="flex gap-2">
                <input name="api_quota_override" type="number" min="-1"
                       value="<?= isset($tenant['api_quota_override']) && $tenant['api_quota_override'] !== null ? (int) $tenant['api_quota_override'] : '' ?>"
                       placeholder="Ej: 100000 · -1 = ilimitado · 0 = sin acceso"
                       class="flex-1 px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-white text-sm focus:outline-none focus:ring-2 focus:ring-violet-500/50">
                <button class="px-4 py-2 rounded-lg text-white text-sm font-semibold" style="background: linear-gradient(135deg,#8B5CF6,#06B6D4);">Guardar</button>
            </div>
            <p class="text-[10px] text-slate-500 mt-1.5">El plan dice: <?= isset($tenant['plan_quota']) ? ((int) $tenant['plan_quota'] === -1 ? 'ilimitado' : number_format((int) $tenant['plan_quota'])) : '—' ?>/mes</p>
        </form>
    </div>
</div>

<!-- Info tecnica -->
<div class="glass p-5">
    <h3 class="font-bold text-white mb-3">ℹ Info tecnica</h3>
    <dl class="grid grid-cols-2 md:grid-cols-3 gap-3 text-sm">
        <div>
            <dt class="text-[10px] uppercase font-semibold text-slate-400">UUID</dt>
            <dd class="font-mono text-xs text-slate-300 break-all"><?= e((string) ($tenant['uuid'] ?? '—')) ?></dd>
        </div>
        <div>
            <dt class="text-[10px] uppercase font-semibold text-slate-400">Creado</dt>
            <dd class="text-slate-300"><?= e((string) ($tenant['created_at'] ?? '—')) ?></dd>
        </div>
        <div>
            <dt class="text-[10px] uppercase font-semibold text-slate-400">Ultimo login</dt>
            <dd class="text-slate-300"><?= e((string) ($stats['last_login'] ?? '—')) ?></dd>
        </div>
        <div>
            <dt class="text-[10px] uppercase font-semibold text-slate-400">Onboarding</dt>
            <dd class="text-slate-300">
                <?php if (!empty($tenant['onboarding_completed_at'])): ?>
                    ✓ completado <?= e(date('d M', strtotime((string) $tenant['onboarding_completed_at']))) ?>
                <?php elseif (!empty($tenant['onboarding_skipped'])): ?>
                    salteado
                <?php else: ?>
                    pendiente · step <?= (int) ($tenant['onboarding_step'] ?? 0) ?>/5
                <?php endif; ?>
            </dd>
        </div>
        <div>
            <dt class="text-[10px] uppercase font-semibold text-slate-400">Email</dt>
            <dd class="text-slate-300 break-all"><?= e((string) ($tenant['email'] ?? '—')) ?></dd>
        </div>
        <div>
            <dt class="text-[10px] uppercase font-semibold text-slate-400">IA</dt>
            <dd class="text-slate-300"><?= !empty($tenant['ai_enabled']) ? '✓ habilitada' : '— deshabilitada' ?></dd>
        </div>
    </dl>
</div>
<?php \App\Core\View::end(); ?>
