<?php
\App\Core\View::extend('layouts.app');
\App\Core\View::start('content');
?>

<div class="mb-4 text-xs"><a href="<?= url('/campaigns') ?>" class="dark:text-slate-400 text-slate-500 hover:underline">&larr; Campanas</a></div>

<div class="mb-6 flex items-start justify-between gap-3 flex-wrap">
    <div>
        <h1 class="text-2xl font-extrabold dark:text-white text-slate-900"><?= e($campaign['name']) ?></h1>
        <p class="dark:text-slate-400 text-slate-600 text-sm capitalize"><?= e($campaign['channel']) ?> · <?= e($campaign['status']) ?></p>
    </div>
    <div class="flex gap-2">
        <?php if (in_array($campaign['status'], ['draft','scheduled'], true)): ?>
        <form action="<?= url('/campaigns/' . $campaign['id'] . '/send') ?>" method="POST" onsubmit="return confirm('Enviar la campana ahora a ' + <?= (int) $campaign['total_recipients'] ?> + ' destinatarios?')">
            <?= csrf_field() ?>
            <button type="submit" class="px-4 py-2 rounded-xl text-white text-sm font-semibold" style="background:linear-gradient(135deg,#7C3AED,#06B6D4)">🚀 Enviar ahora</button>
        </form>
        <?php endif; ?>
        <form action="<?= url('/campaigns/' . $campaign['id']) ?>" method="POST" onsubmit="return confirm('Eliminar la campana?')">
            <?= csrf_field() ?>
            <input type="hidden" name="_method" value="DELETE">
            <button class="px-4 py-2 rounded-xl text-red-500 hover:bg-red-500/10 text-sm">Eliminar</button>
        </form>
    </div>
</div>

<div class="grid grid-cols-2 md:grid-cols-6 gap-3 mb-6">
    <?php
    $kpis = [
        ['Audiencia',   $campaign['total_recipients']],
        ['Enviados',    $campaign['total_sent']],
        ['Entregados',  $campaign['total_delivered']],
        ['Leidos',      $campaign['total_read']],
        ['Respondidos', $campaign['total_replied']],
        ['Fallidos',    $campaign['total_failed']],
    ];
    foreach ($kpis as [$k, $v]): ?>
    <div class="glass rounded-xl p-3">
        <div class="text-xs dark:text-slate-400 text-slate-500"><?= e($k) ?></div>
        <div class="text-2xl font-extrabold dark:text-white text-slate-900"><?= number_format((int) $v) ?></div>
    </div>
    <?php endforeach; ?>
</div>

<div class="grid lg:grid-cols-3 gap-4">
    <div class="glass rounded-2xl p-5">
        <h3 class="font-bold dark:text-white text-slate-900 mb-3">Mensaje</h3>
        <div class="dark:bg-white/5 bg-slate-50 p-4 rounded-xl text-sm dark:text-slate-200 text-slate-800 whitespace-pre-line"><?= e((string) $campaign['message']) ?></div>
    </div>

    <div class="lg:col-span-2 glass rounded-2xl p-5">
        <h3 class="font-bold dark:text-white text-slate-900 mb-3">Destinatarios (ultimos 100)</h3>
        <?php if (empty($recipients)): ?>
        <p class="text-sm dark:text-slate-400 text-slate-500">Sin destinatarios.</p>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="dark:text-slate-400 text-slate-500 text-xs uppercase">
                    <tr>
                        <th class="text-left px-2 py-1">Contacto</th>
                        <th class="text-left px-2 py-1">Telefono</th>
                        <th class="text-left px-2 py-1">Estado</th>
                        <th class="text-left px-2 py-1">Enviado</th>
                    </tr>
                </thead>
                <tbody class="divide-y dark:divide-white/5 divide-slate-100">
                    <?php foreach ($recipients as $r):
                        $name = trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? ''));
                        $cls = match ($r['status']) {
                            'sent','delivered','read' => 'bg-emerald-500/20 text-emerald-300',
                            'replied' => 'bg-cyan-500/20 text-cyan-300',
                            'failed'  => 'bg-red-500/20 text-red-300',
                            default   => 'bg-slate-500/20 text-slate-300',
                        };
                    ?>
                    <tr>
                        <td class="px-2 py-2 dark:text-white text-slate-900"><?= e($name ?: '—') ?></td>
                        <td class="px-2 py-2 dark:text-slate-400 text-slate-500"><?= e((string) ($r['phone'] ?? '')) ?></td>
                        <td class="px-2 py-2"><span class="px-2 py-0.5 text-xs rounded-full <?= $cls ?>"><?= e($r['status']) ?></span></td>
                        <td class="px-2 py-2 dark:text-slate-400 text-slate-500 text-xs"><?= $r['sent_at'] ? time_ago((string) $r['sent_at']) : '—' ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php \App\Core\View::stop(); ?>
