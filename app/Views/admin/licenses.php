<?php
\App\Core\View::extend('layouts.admin');
\App\Core\View::start('content');
?>

<div class="mb-6 flex items-center justify-between flex-wrap gap-3">
    <div>
        <h1 class="text-2xl font-bold text-white mb-1">Licencias y limite de clientes</h1>
        <p class="text-sm text-slate-400">Control granular del cupo de clientes (contactos) por empresa. El limite efectivo es: override &gt; plan &gt; default.</p>
    </div>
    <div class="flex items-center gap-2 flex-wrap">
        <a href="<?= url('/admin/plans') ?>" class="px-3 py-2 rounded-lg text-sm text-slate-300 hover:text-white bg-white/5 hover:bg-white/10 transition">Editar planes</a>
        <a href="<?= url('/admin/tenants') ?>" class="px-3 py-2 rounded-lg text-sm font-semibold text-white shadow-lg shadow-emerald-500/30" style="background:linear-gradient(135deg,#10B981,#06B6D4);">Empresas</a>
    </div>
</div>

<?php if ($flash = flash('success')): ?>
<div class="mb-4 p-3 rounded-xl border text-sm whitespace-pre-line" style="background: rgba(16,185,129,.08); border-color: rgba(16,185,129,.3); color:#34D399;"><?= e((string) $flash) ?></div>
<?php endif; ?>
<?php if ($flashErr = flash('error')): ?>
<div class="mb-4 p-3 rounded-xl border text-sm" style="background: rgba(244,63,94,.08); border-color: rgba(244,63,94,.3); color:#FB7185;"><?= e((string) $flashErr) ?></div>
<?php endif; ?>

<!-- KPIs globales -->
<div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-6">
    <?php
    $kpiCards = [
        ['Empresas',          number_format((int) $kpi['total_tenants']),  '#10B981'],
        ['En limite',         number_format((int) $kpi['over_limit']),     '#F43F5E'],
        ['Cerca del limite',  number_format((int) $kpi['near_limit']),     '#F59E0B'],
        ['Con override',      number_format((int) $kpi['with_override']),  '#06B6D4'],
        ['Capacidad usada',   $kpi['global_pct'] . '%',                    '#34D399'],
    ];
    foreach ($kpiCards as [$lbl, $val, $clr]):
    ?>
    <div class="admin-card rounded-2xl p-4">
        <div class="text-[10px] uppercase tracking-wider text-slate-400 font-semibold mb-1"><?= e($lbl) ?></div>
        <div class="text-2xl font-bold" style="color: <?= $clr ?>;"><?= e((string) $val) ?></div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Capacidad global -->
<div class="admin-card rounded-2xl p-5 mb-6">
    <div class="flex items-center justify-between mb-2">
        <div>
            <div class="font-bold text-white text-sm">Capacidad agregada del SaaS</div>
            <div class="text-xs text-slate-400 mt-0.5">
                <?= number_format((int) $kpi['total_clients']) ?> clientes activos /
                <?= number_format((int) $kpi['total_capacity']) ?> licenciados
            </div>
        </div>
        <span class="text-2xl font-bold" style="color: <?= ((int) $kpi['global_pct']) >= 90 ? '#F43F5E' : (((int) $kpi['global_pct']) >= 70 ? '#F59E0B' : '#10B981') ?>;"><?= (int) $kpi['global_pct'] ?>%</span>
    </div>
    <div class="h-2 rounded-full bg-white/5 overflow-hidden">
        <div class="h-full rounded-full" style="width: <?= (int) $kpi['global_pct'] ?>%; background: linear-gradient(90deg,#10B981,#06B6D4);"></div>
    </div>
</div>

<!-- Filtros -->
<div class="flex items-center gap-2 mb-4 flex-wrap">
    <?php
    $filters = [
        ''        => 'Todos',
        'warn'    => 'Cerca del limite (85%+)',
        'over'    => 'En el limite (100%)',
        'override'=> 'Con override',
    ];
    foreach ($filters as $f => $lbl):
        $active = (string) ($filter ?? '') === $f;
    ?>
    <a href="<?= url('/admin/licenses' . ($f ? '?filter=' . $f : '')) ?>" class="px-3 py-1.5 rounded-lg text-xs font-semibold transition <?= $active ? 'text-white' : 'text-slate-300 bg-white/5 hover:bg-white/10' ?>" <?= $active ? 'style="background: linear-gradient(135deg,#10B981,#06B6D4);"' : '' ?>><?= e($lbl) ?></a>
    <?php endforeach; ?>
</div>

<!-- Tabla / cards -->
<div class="space-y-2">
    <?php if (empty($rows)): ?>
    <div class="admin-card rounded-2xl p-8 text-center text-slate-400 text-sm">No hay tenants en este filtro.</div>
    <?php endif; ?>

    <?php foreach ($rows as $r):
        $pct = (int) $r['clients_percent'];
        $barClr = $pct >= 100 ? '#F43F5E' : ($pct >= 85 ? '#F59E0B' : null);
        $hitAt = $r['client_limit_hit_at'] ?? null;
    ?>
    <details class="admin-card rounded-2xl">
        <summary class="cursor-pointer list-none p-4 flex items-center gap-4 flex-wrap">
            <div class="w-11 h-11 rounded-xl flex items-center justify-center text-base font-bold text-white flex-shrink-0" style="background: linear-gradient(135deg,#10B981,#06B6D4);"><?= e(strtoupper(mb_substr((string) $r['name'], 0, 2))) ?></div>
            <div class="flex-1 min-w-[220px]">
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="font-bold text-white truncate"><?= e((string) $r['name']) ?></span>
                    <?php if (!empty($r['plan_name'])): ?>
                    <span class="text-[10px] px-2 py-0.5 rounded bg-emerald-500/15 text-emerald-300"><?= e((string) $r['plan_name']) ?></span>
                    <?php endif; ?>
                    <?php if ($r['limit_source'] === 'override'): ?>
                    <span class="text-[10px] px-2 py-0.5 rounded bg-cyan-500/15 text-cyan-300">override</span>
                    <?php endif; ?>
                    <?php if (!$r['locked_bool']): ?>
                    <span class="text-[10px] px-2 py-0.5 rounded bg-amber-500/15 text-amber-300">limite suave</span>
                    <?php endif; ?>
                </div>
                <div class="text-xs text-slate-500 mt-0.5"><?= e((string) $r['email']) ?></div>
            </div>

            <div class="flex-1 min-w-[260px]">
                <div class="flex items-center justify-between mb-1">
                    <span class="text-xs font-mono text-slate-300">
                        <?= number_format((int) $r['clients_used']) ?> / <?= number_format((int) $r['limit_effective']) ?> clientes
                    </span>
                    <span class="text-xs font-bold" style="color: <?= $pct >= 100 ? '#F43F5E' : ($pct >= 85 ? '#F59E0B' : '#10B981') ?>;"><?= $pct ?>%</span>
                </div>
                <div class="h-1.5 rounded-full bg-white/5 overflow-hidden">
                    <div class="h-full rounded-full" style="width: <?= min(100, $pct) ?>%; <?= $barClr ? 'background:' . $barClr . ';' : 'background: linear-gradient(90deg,#10B981,#06B6D4);' ?>"></div>
                </div>
                <?php if ($hitAt): ?>
                <div class="text-[10px] text-rose-400 mt-1">Limite golpeado <?= time_ago((string) $hitAt) ?></div>
                <?php endif; ?>
            </div>

            <div class="text-right text-xs text-slate-500 flex-shrink-0">
                <?= e((string) $r['status']) ?>
            </div>
        </summary>

        <div class="border-t border-white/5 p-4 space-y-3">
            <form action="<?= url('/admin/tenants/' . (int) $r['id'] . '/client-limit') ?>" method="POST" class="grid md:grid-cols-4 gap-3 items-end">
                <?= csrf_field() ?>
                <input type="hidden" name="_redirect" value="/admin/licenses<?= $filter ? '?filter=' . urlencode((string) $filter) : '' ?>">
                <div>
                    <label class="text-[10px] uppercase tracking-wider text-slate-400">Override (vacio = usar plan)</label>
                    <input type="number" name="max_contacts_override" min="0" value="<?= $r['max_contacts_override'] !== null ? (int) $r['max_contacts_override'] : '' ?>" placeholder="<?= !empty($r['plan_max_contacts']) ? 'Plan: ' . (int) $r['plan_max_contacts'] : 'sin plan' ?>" class="w-full mt-1 px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-white text-sm">
                </div>
                <div>
                    <label class="text-[10px] uppercase tracking-wider text-slate-400">Plan asignado</label>
                    <div class="mt-1 px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-slate-300 text-sm">
                        <?= e((string) ($r['plan_name'] ?? '— sin plan —')) ?>
                        <?php if (!empty($r['plan_max_contacts'])): ?>
                        <span class="text-slate-500">· max <?= number_format((int) $r['plan_max_contacts']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <div>
                    <label class="flex items-start gap-2 px-3 py-2 rounded-lg bg-white/5 border border-white/10 cursor-pointer hover:bg-white/10">
                        <input type="checkbox" name="client_limit_locked" value="1" <?= !empty($r['locked_bool']) ? 'checked' : '' ?> class="w-3.5 h-3.5 rounded mt-0.5">
                        <div class="flex-1">
                            <div class="text-xs font-semibold text-white">Bloqueo duro</div>
                            <div class="text-[10px] text-slate-400 leading-tight">Si esta activo, al alcanzar el limite no se podran crear mas clientes manualmente. Inbound de WhatsApp solo lo marca como advertencia.</div>
                        </div>
                    </label>
                </div>
                <div>
                    <button type="submit" class="w-full px-4 py-2 rounded-lg text-white text-sm font-semibold shadow-lg shadow-emerald-500/20" style="background:linear-gradient(135deg,#10B981,#06B6D4);">Guardar limite</button>
                </div>
            </form>

            <div class="grid md:grid-cols-3 gap-2 text-xs">
                <div class="p-2.5 rounded-lg bg-white/5 border border-white/10">
                    <div class="text-[10px] uppercase tracking-wider text-slate-400">Limite efectivo</div>
                    <div class="text-white font-bold mt-0.5"><?= number_format((int) $r['limit_effective']) ?> clientes <span class="text-[10px] text-slate-500">· fuente <?= e((string) $r['limit_source']) ?></span></div>
                </div>
                <div class="p-2.5 rounded-lg bg-white/5 border border-white/10">
                    <div class="text-[10px] uppercase tracking-wider text-slate-400">Restante</div>
                    <div class="text-white font-bold mt-0.5"><?= number_format((int) $r['clients_remaining']) ?> slots</div>
                </div>
                <div class="p-2.5 rounded-lg bg-white/5 border border-white/10">
                    <div class="text-[10px] uppercase tracking-wider text-slate-400">UUID</div>
                    <code class="text-[10px] text-slate-300 font-mono"><?= e((string) ($r['uuid'] ?? '')) ?></code>
                </div>
            </div>
        </div>
    </details>
    <?php endforeach; ?>
</div>

<?php \App\Core\View::stop(); ?>
