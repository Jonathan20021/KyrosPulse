<?php
\App\Core\View::extend('layouts.admin');
\App\Core\View::start('content');
?>

<div class="mb-6">
    <h1 class="text-2xl font-bold text-white mb-1">Logs del sistema</h1>
    <p class="text-sm text-slate-400">Auditoria, integraciones y errores recientes.</p>
</div>

<div x-data="{ tab: 'audit' }" class="space-y-3">
    <div class="admin-card rounded-xl p-1 inline-flex flex-wrap">
        <?php foreach (['audit'=>'🔍 Auditoria','whatsapp'=>'💬 WhatsApp','email'=>'📨 Email','ai'=>'🤖 IA'] as $key=>$label): ?>
        <button @click="tab = '<?= $key ?>'" type="button"
                :class="tab === '<?= $key ?>' ? 'text-white' : 'text-slate-400 hover:text-white'"
                class="px-4 py-2 rounded-lg text-sm font-medium transition relative">
            <span :class="tab === '<?= $key ?>' ? 'absolute inset-0 rounded-lg' : 'hidden'" style="background: linear-gradient(135deg,#7C3AED,#06B6D4);"></span>
            <span class="relative z-10"><?= $label ?></span>
        </button>
        <?php endforeach; ?>
    </div>

    <div x-show="tab === 'audit'" class="admin-card rounded-2xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-white/5 text-slate-400 text-[10px] uppercase tracking-wider">
                    <tr><th class="text-left px-4 py-2.5">Accion</th><th class="text-left px-4 py-2.5">Entidad</th><th class="text-left px-4 py-2.5">Usuario</th><th class="text-left px-4 py-2.5">IP</th><th class="text-left px-4 py-2.5">Cuando</th></tr>
                </thead>
                <tbody class="divide-y divide-white/5">
                    <?php foreach ($audit as $a): ?>
                    <tr class="hover:bg-white/[0.03] transition">
                        <td class="px-4 py-2.5 font-mono text-xs text-cyan-400"><?= e((string) $a['action']) ?></td>
                        <td class="px-4 py-2.5 text-xs text-slate-300"><?= e((string) ($a['entity_type'] ?? '')) ?> <?= !empty($a['entity_id']) ? '<span class="text-slate-500">#' . (int) $a['entity_id'] . '</span>' : '' ?></td>
                        <td class="px-4 py-2.5 text-xs text-slate-300"><?= e((string) ($a['email'] ?? '—')) ?></td>
                        <td class="px-4 py-2.5 text-xs font-mono text-slate-500"><?= e((string) ($a['ip_address'] ?? '')) ?></td>
                        <td class="px-4 py-2.5 text-xs text-slate-500"><?= time_ago((string) $a['created_at']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div x-show="tab === 'whatsapp'" x-cloak class="admin-card rounded-2xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-white/5 text-slate-400 text-[10px] uppercase tracking-wider">
                    <tr><th class="text-left px-4 py-2.5">Empresa</th><th class="text-left px-4 py-2.5">Direccion</th><th class="text-left px-4 py-2.5">Endpoint</th><th class="text-left px-4 py-2.5">HTTP</th><th class="text-left px-4 py-2.5">Cuando</th></tr>
                </thead>
                <tbody class="divide-y divide-white/5">
                    <?php foreach ($whatsapp as $w): ?>
                    <tr class="hover:bg-white/[0.03] transition <?= !$w['success'] ? 'bg-rose-500/5' : '' ?>">
                        <td class="px-4 py-2.5 text-xs font-semibold text-white"><?= e((string) ($w['tenant_name'] ?? '—')) ?></td>
                        <td class="px-4 py-2.5 text-xs text-slate-300"><?= e((string) $w['direction']) ?></td>
                        <td class="px-4 py-2.5 text-xs font-mono text-cyan-400"><?= e((string) ($w['endpoint'] ?? '')) ?></td>
                        <td class="px-4 py-2.5 text-xs <?= $w['success'] ? 'text-emerald-400' : 'text-rose-400' ?>"><?= (int) $w['status_code'] ?></td>
                        <td class="px-4 py-2.5 text-xs text-slate-500"><?= time_ago((string) $w['created_at']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div x-show="tab === 'email'" x-cloak class="admin-card rounded-2xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-white/5 text-slate-400 text-[10px] uppercase tracking-wider">
                    <tr><th class="text-left px-4 py-2.5">Para</th><th class="text-left px-4 py-2.5">Asunto</th><th class="text-left px-4 py-2.5">Estado</th><th class="text-left px-4 py-2.5">Cuando</th></tr>
                </thead>
                <tbody class="divide-y divide-white/5">
                    <?php foreach ($email as $em): ?>
                    <tr class="hover:bg-white/[0.03] transition">
                        <td class="px-4 py-2.5 text-xs text-slate-300"><?= e((string) $em['to_email']) ?></td>
                        <td class="px-4 py-2.5 text-xs text-white"><?= e((string) ($em['subject'] ?? '')) ?></td>
                        <td class="px-4 py-2.5 text-xs"><span class="px-2 py-0.5 rounded text-[10px] uppercase font-bold <?= $em['status']==='sent' ? 'bg-emerald-500/15 text-emerald-300' : 'bg-rose-500/15 text-rose-300' ?>"><?= e((string) $em['status']) ?></span></td>
                        <td class="px-4 py-2.5 text-xs text-slate-500"><?= time_ago((string) $em['created_at']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div x-show="tab === 'ai'" x-cloak class="admin-card rounded-2xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-white/5 text-slate-400 text-[10px] uppercase tracking-wider">
                    <tr><th class="text-left px-4 py-2.5">Empresa</th><th class="text-left px-4 py-2.5">Feature</th><th class="text-left px-4 py-2.5">Modelo</th><th class="text-left px-4 py-2.5">Tokens in/out</th><th class="text-left px-4 py-2.5">Tiempo</th><th class="text-left px-4 py-2.5">Cuando</th></tr>
                </thead>
                <tbody class="divide-y divide-white/5">
                    <?php foreach ($ai as $log): ?>
                    <tr class="hover:bg-white/[0.03] transition <?= !$log['success'] ? 'bg-rose-500/5' : '' ?>">
                        <td class="px-4 py-2.5 text-xs font-semibold text-white"><?= e((string) ($log['tenant_name'] ?? '—')) ?></td>
                        <td class="px-4 py-2.5 text-xs font-mono text-cyan-400"><?= e((string) $log['feature']) ?></td>
                        <td class="px-4 py-2.5 text-xs font-mono text-violet-300"><?= e((string) ($log['model'] ?? '')) ?></td>
                        <td class="px-4 py-2.5 text-xs text-slate-300"><?= (int) $log['tokens_input'] ?> / <?= (int) $log['tokens_output'] ?></td>
                        <td class="px-4 py-2.5 text-xs text-slate-400"><?= (int) ($log['duration_ms'] ?? 0) ?>ms</td>
                        <td class="px-4 py-2.5 text-xs text-slate-500"><?= time_ago((string) $log['created_at']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php \App\Core\View::stop(); ?>
