<?php
\App\Core\View::extend('layouts.admin');
\App\Core\View::start('content');
?>
<div class="mb-6">
    <h1 class="text-2xl font-extrabold dark:text-white text-slate-900">Logs del sistema</h1>
    <p class="dark:text-slate-400 text-slate-600 text-sm">Auditoria, integraciones y errores recientes.</p>
</div>

<div x-data="{ tab: 'audit' }" class="space-y-4">
    <div class="flex items-center gap-1 border-b dark:border-white/5 border-slate-200">
        <?php foreach (['audit'=>'Auditoria','whatsapp'=>'WhatsApp','email'=>'Email','ai'=>'IA'] as $key=>$label): ?>
        <button @click="tab = '<?= $key ?>'" :class="tab === '<?= $key ?>' ? 'border-b-2 border-primary dark:text-white text-slate-900 font-semibold' : 'dark:text-slate-400 text-slate-500'" class="px-4 py-2 text-sm">
            <?= $label ?>
        </button>
        <?php endforeach; ?>
    </div>

    <div x-show="tab === 'audit'" class="glass rounded-2xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="dark:bg-white/5 bg-slate-50 dark:text-slate-400 text-slate-500 text-xs uppercase">
                    <tr><th class="text-left px-4 py-2">Accion</th><th class="text-left px-4 py-2">Entidad</th><th class="text-left px-4 py-2">Usuario</th><th class="text-left px-4 py-2">IP</th><th class="text-left px-4 py-2">Cuando</th></tr>
                </thead>
                <tbody class="divide-y dark:divide-white/5 divide-slate-100">
                    <?php foreach ($audit as $a): ?>
                    <tr>
                        <td class="px-4 py-2 font-mono text-xs dark:text-cyan-400 text-cyan-600"><?= e((string) $a['action']) ?></td>
                        <td class="px-4 py-2 text-xs dark:text-slate-300 text-slate-700"><?= e((string) ($a['entity_type'] ?? '')) ?> <?= !empty($a['entity_id']) ? '#' . (int) $a['entity_id'] : '' ?></td>
                        <td class="px-4 py-2 text-xs dark:text-slate-300 text-slate-700"><?= e((string) ($a['email'] ?? '—')) ?></td>
                        <td class="px-4 py-2 text-xs font-mono dark:text-slate-400 text-slate-500"><?= e((string) ($a['ip_address'] ?? '')) ?></td>
                        <td class="px-4 py-2 text-xs dark:text-slate-400 text-slate-500"><?= time_ago((string) $a['created_at']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div x-show="tab === 'whatsapp'" x-cloak class="glass rounded-2xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="dark:bg-white/5 bg-slate-50 dark:text-slate-400 text-slate-500 text-xs uppercase">
                    <tr><th class="text-left px-4 py-2">Empresa</th><th class="text-left px-4 py-2">Direccion</th><th class="text-left px-4 py-2">Endpoint</th><th class="text-left px-4 py-2">HTTP</th><th class="text-left px-4 py-2">Cuando</th></tr>
                </thead>
                <tbody class="divide-y dark:divide-white/5 divide-slate-100">
                    <?php foreach ($whatsapp as $w): ?>
                    <tr class="<?= !$w['success'] ? 'bg-red-500/5' : '' ?>">
                        <td class="px-4 py-2 dark:text-white text-slate-900 text-xs"><?= e((string) ($w['tenant_name'] ?? '—')) ?></td>
                        <td class="px-4 py-2 text-xs dark:text-slate-300 text-slate-700"><?= e((string) $w['direction']) ?></td>
                        <td class="px-4 py-2 text-xs font-mono dark:text-cyan-400 text-cyan-600"><?= e((string) ($w['endpoint'] ?? '')) ?></td>
                        <td class="px-4 py-2 text-xs <?= $w['success'] ? 'text-emerald-400' : 'text-red-400' ?>"><?= (int) $w['status_code'] ?></td>
                        <td class="px-4 py-2 text-xs dark:text-slate-400 text-slate-500"><?= time_ago((string) $w['created_at']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div x-show="tab === 'email'" x-cloak class="glass rounded-2xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="dark:bg-white/5 bg-slate-50 dark:text-slate-400 text-slate-500 text-xs uppercase">
                    <tr><th class="text-left px-4 py-2">Para</th><th class="text-left px-4 py-2">Asunto</th><th class="text-left px-4 py-2">Estado</th><th class="text-left px-4 py-2">Cuando</th></tr>
                </thead>
                <tbody class="divide-y dark:divide-white/5 divide-slate-100">
                    <?php foreach ($email as $em): ?>
                    <tr><td class="px-4 py-2 text-xs"><?= e((string) $em['to_email']) ?></td>
                        <td class="px-4 py-2 text-xs"><?= e((string) ($em['subject'] ?? '')) ?></td>
                        <td class="px-4 py-2 text-xs"><?= e((string) $em['status']) ?></td>
                        <td class="px-4 py-2 text-xs dark:text-slate-400 text-slate-500"><?= time_ago((string) $em['created_at']) ?></td></tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div x-show="tab === 'ai'" x-cloak class="glass rounded-2xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="dark:bg-white/5 bg-slate-50 dark:text-slate-400 text-slate-500 text-xs uppercase">
                    <tr><th class="text-left px-4 py-2">Empresa</th><th class="text-left px-4 py-2">Feature</th><th class="text-left px-4 py-2">Tokens in/out</th><th class="text-left px-4 py-2">Tiempo</th><th class="text-left px-4 py-2">Cuando</th></tr>
                </thead>
                <tbody class="divide-y dark:divide-white/5 divide-slate-100">
                    <?php foreach ($ai as $log): ?>
                    <tr class="<?= !$log['success'] ? 'bg-red-500/5' : '' ?>">
                        <td class="px-4 py-2 text-xs"><?= e((string) ($log['tenant_name'] ?? '—')) ?></td>
                        <td class="px-4 py-2 text-xs font-mono dark:text-cyan-400 text-cyan-600"><?= e((string) $log['feature']) ?></td>
                        <td class="px-4 py-2 text-xs"><?= (int) $log['tokens_input'] ?> / <?= (int) $log['tokens_output'] ?></td>
                        <td class="px-4 py-2 text-xs"><?= (int) $log['duration_ms'] ?>ms</td>
                        <td class="px-4 py-2 text-xs dark:text-slate-400 text-slate-500"><?= time_ago((string) $log['created_at']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php \App\Core\View::stop(); ?>
