<?php
\App\Core\View::extend('layouts.app');
\App\Core\View::start('content');
$agents = $agents ?? [];
$kpi    = $kpi    ?? ['handled'=>0,'sent_messages'=>0,'transferred'=>0,'sales_closed'=>0,'tokens_in'=>0,'tokens_out'=>0];

$categories = [
    'generic'     => ['Generico',     '🤖'],
    'sales'       => ['Ventas',       '💰'],
    'support'     => ['Soporte',      '🎧'],
    'scheduling'  => ['Agendamiento', '📅'],
    'collections' => ['Cobros',       '💳'],
    'onboarding'  => ['Onboarding',   '🚀'],
    'retention'   => ['Retencion',    '🛟'],
];
$days = [
    'monday'    => 'Lun',
    'tuesday'   => 'Mar',
    'wednesday' => 'Mie',
    'thursday'  => 'Jue',
    'friday'    => 'Vie',
    'saturday'  => 'Sab',
    'sunday'    => 'Dom',
];

$decode = function ($v) {
    if (is_array($v)) return $v;
    if (!is_string($v) || $v === '') return [];
    $d = json_decode($v, true);
    return is_array($d) ? $d : [];
};
?>
<?php \App\Core\View::include('components.page_header', ['title' => 'IA y agentes', 'subtitle' => 'Crea multiples agentes IA especializados que atienden por ti.']); ?>
<?php \App\Core\View::include('settings._tabs', ['tab' => 'ai']); ?>

<?php
$autopilotOn = !empty($tenant['ai_force_all']);
?>
<!-- Autopilot Total: boton grande que activa IA en TODAS las conversaciones -->
<div class="rounded-2xl p-5 mb-4 relative overflow-hidden border <?= $autopilotOn ? 'border-emerald-500/40' : 'border-white/10' ?>"
     style="background: <?= $autopilotOn
        ? 'linear-gradient(135deg, rgba(16,185,129,.12), rgba(6,182,212,.08))'
        : 'linear-gradient(135deg, rgba(124,58,237,.06), rgba(15,23,42,.6))' ?>;">
    <div class="absolute -top-16 -right-16 w-56 h-56 rounded-full opacity-30" style="background: radial-gradient(circle, <?= $autopilotOn ? 'rgba(16,185,129,.5)' : 'rgba(124,58,237,.4)' ?>, transparent 70%); filter: blur(50px);"></div>

    <div class="relative flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="flex items-start gap-4 min-w-0">
            <div class="w-14 h-14 rounded-2xl flex items-center justify-center text-3xl flex-shrink-0"
                 style="background: <?= $autopilotOn ? 'linear-gradient(135deg,#10B981,#06B6D4)' : 'linear-gradient(135deg,#7C3AED,#06B6D4)' ?>;">
                <?= $autopilotOn ? '🤖' : '⚡' ?>
            </div>
            <div class="min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                    <h3 class="font-black text-lg dark:text-white text-slate-900">Autopilot Total</h3>
                    <?php if ($autopilotOn): ?>
                    <span class="inline-flex items-center gap-1 text-[10px] px-2 py-0.5 rounded-full font-bold uppercase tracking-wider" style="background: rgba(16,185,129,.18); color:#34D399;">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span> Activo
                    </span>
                    <?php else: ?>
                    <span class="inline-flex items-center gap-1 text-[10px] px-2 py-0.5 rounded-full font-bold uppercase tracking-wider bg-slate-500/15 text-slate-400">○ Inactivo</span>
                    <?php endif; ?>
                </div>
                <p class="text-sm dark:text-slate-300 text-slate-600 mt-1 max-w-xl">
                    <?php if ($autopilotOn): ?>
                        La IA esta respondiendo TODOS los mensajes entrantes en todos los chats automaticamente. Cuando un humano responde manualmente, la IA se pausa 5 minutos en ese chat y luego retoma.
                    <?php else: ?>
                        Activa este modo para que la IA responda <strong>todos</strong> los mensajes entrantes sin tener que activar el bot manualmente en cada conversacion.
                    <?php endif; ?>
                </p>
            </div>
        </div>

        <form action="<?= url('/settings/ai/autopilot') ?>" method="POST" class="flex-shrink-0">
            <?= csrf_field() ?>
            <input type="hidden" name="enable" value="<?= $autopilotOn ? 0 : 1 ?>">
            <button type="submit"
                    onclick="<?= $autopilotOn ? "return confirm('Apagar Autopilot Total? La IA dejara de responder automaticamente en chats nuevos.')" : "return true" ?>"
                    class="w-full md:w-auto px-6 py-3.5 rounded-xl font-bold text-sm flex items-center justify-center gap-2 text-white transition-all hover:scale-[1.02] active:scale-95"
                    style="background: <?= $autopilotOn ? 'linear-gradient(135deg,#F43F5E,#9F1239)' : 'linear-gradient(135deg,#10B981,#06B6D4)' ?>; box-shadow: 0 8px 30px <?= $autopilotOn ? 'rgba(244,63,94,.35)' : 'rgba(16,185,129,.35)' ?>;">
                <?php if ($autopilotOn): ?>
                    <span>⏸</span><span>Apagar Autopilot</span>
                <?php else: ?>
                    <span>▶</span><span>Activar Autopilot Total</span>
                <?php endif; ?>
            </button>
        </form>
    </div>

    <?php if ($autopilotOn): ?>
    <div class="mt-3 grid grid-cols-1 sm:grid-cols-3 gap-2 text-xs">
        <div class="flex items-center gap-1.5 px-3 py-2 rounded-lg bg-white/5">
            <span class="text-emerald-400">✓</span>
            <span class="dark:text-slate-300 text-slate-600">Responde mensajes nuevos</span>
        </div>
        <div class="flex items-center gap-1.5 px-3 py-2 rounded-lg bg-white/5">
            <span class="text-emerald-400">✓</span>
            <span class="dark:text-slate-300 text-slate-600">Atiende chats reabiertos</span>
        </div>
        <div class="flex items-center gap-1.5 px-3 py-2 rounded-lg bg-white/5">
            <span class="text-emerald-400">✓</span>
            <span class="dark:text-slate-300 text-slate-600">Respeta pausa manual del operador</span>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php if ($flash = flash('success')): ?>
<div class="mb-4 p-3 rounded-xl border text-sm" style="background: rgba(16,185,129,.08); border-color: rgba(16,185,129,.3); color:#34D399;">
    <?= e((string) $flash) ?>
</div>
<?php endif; ?>

<!-- Widget: Estado de IA del tenant -->
<?php if (!empty($aiSummary) && !empty($aiSummary['source'])):
    $isGlobal = $aiSummary['source'] === 'global';
    $pct = (int) ($aiSummary['pct'] ?? 0);
    $barColor = $pct >= 90 ? '#F43F5E' : ($pct >= 70 ? '#F59E0B' : null);
?>
<div class="rounded-2xl p-5 mb-4 relative overflow-hidden border" style="background: linear-gradient(135deg, rgba(124,58,237,.08), rgba(6,182,212,.06)); border-color: rgba(124,58,237,.25);">
    <div class="absolute -top-12 -right-12 w-48 h-48 rounded-full opacity-30" style="background: radial-gradient(circle, rgba(124,58,237,.4), transparent 70%); filter: blur(40px);"></div>
    <div class="relative grid md:grid-cols-3 gap-4">
        <!-- Provider en uso -->
        <div>
            <div class="text-[10px] uppercase tracking-wider dark:text-slate-400 text-slate-500 mb-1.5 font-semibold">Tu IA actual</div>
            <div class="flex items-center gap-2 mb-1">
                <span class="text-2xl"><?= $aiSummary['provider'] === 'openai' ? '🟢' : '🟣' ?></span>
                <div>
                    <div class="font-bold dark:text-white text-slate-900 text-sm"><?= e((string) $aiSummary['display_name']) ?></div>
                    <div class="text-[11px] dark:text-slate-400 text-slate-500 font-mono"><?= e((string) ($aiSummary['model'] ?? '')) ?></div>
                </div>
            </div>
            <?php if ($isGlobal): ?>
            <div class="inline-flex items-center gap-1 text-[10px] px-2 py-0.5 rounded mt-1" style="background: rgba(6,182,212,.15); color: #06B6D4;">
                <span>🏢</span> Provista por el SaaS
            </div>
            <?php else: ?>
            <div class="inline-flex items-center gap-1 text-[10px] px-2 py-0.5 rounded mt-1" style="background: rgba(16,185,129,.15); color: #10B981;">
                <span>🔑</span> Tu propia API key
            </div>
            <?php endif; ?>
        </div>

        <!-- Consumo -->
        <div class="md:col-span-2">
            <div class="flex items-center justify-between mb-1.5">
                <span class="text-[10px] uppercase tracking-wider dark:text-slate-400 text-slate-500 font-semibold">Consumo del periodo</span>
                <?php if (!empty($aiSummary['period_start'])): ?>
                <span class="text-[10px] dark:text-slate-500 text-slate-400">desde <?= e(date('d M', strtotime((string) $aiSummary['period_start']))) ?></span>
                <?php endif; ?>
            </div>
            <?php if ($isGlobal && $aiSummary['quota']): ?>
                <div class="flex items-baseline justify-between mb-2">
                    <div>
                        <span class="text-2xl font-bold dark:text-white text-slate-900"><?= number_format((int) $aiSummary['used']) ?></span>
                        <span class="text-sm dark:text-slate-400 text-slate-500"> / <?= number_format((int) $aiSummary['quota']) ?> tokens</span>
                    </div>
                    <span class="text-sm font-bold <?= $pct >= 90 ? 'text-rose-400' : ($pct >= 70 ? 'text-amber-400' : 'text-emerald-400') ?>"><?= $pct ?>%</span>
                </div>
                <div class="h-2 rounded-full overflow-hidden dark:bg-white/5 bg-slate-200">
                    <div class="h-full rounded-full transition-all" style="width: <?= $pct ?>%; <?= $barColor ? "background: $barColor;" : 'background: linear-gradient(90deg,#7C3AED,#06B6D4);' ?>"></div>
                </div>
                <?php if ($pct >= 90): ?>
                <p class="text-xs text-rose-400 mt-2">⚠ Estas cerca de tu cuota mensual. Si quieres tokens adicionales, contacta a tu administrador o agrega tu propia API key abajo.</p>
                <?php endif; ?>
            <?php elseif ($isGlobal): ?>
                <div class="flex items-baseline gap-2">
                    <span class="text-2xl font-bold dark:text-white text-slate-900"><?= number_format((int) $aiSummary['used']) ?></span>
                    <span class="text-sm dark:text-slate-400 text-slate-500">tokens · sin limite</span>
                </div>
                <p class="text-[11px] dark:text-slate-500 text-slate-400 mt-1">El SaaS no aplica limite mensual a tu cuenta.</p>
            <?php else: ?>
                <p class="text-sm dark:text-slate-300 text-slate-700 mt-1">Estas usando tu propia API key — el costo va directo a tu cuenta del proveedor. <strong class="dark:text-white text-slate-900">Sin limite</strong> impuesto por el SaaS.</p>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php elseif (!empty($aiSummary) && empty($aiSummary['source'])): ?>
<div class="rounded-2xl p-5 mb-4 border" style="background: rgba(244,63,94,.06); border-color: rgba(244,63,94,.25);">
    <div class="flex items-center gap-3">
        <div class="text-3xl">⚠</div>
        <div>
            <div class="font-bold dark:text-white text-slate-900 mb-0.5">Sin IA disponible</div>
            <p class="text-sm dark:text-slate-300 text-slate-700">No tienes API key propia ni un proveedor IA global asignado. Pide al administrador del SaaS que te asigne uno o agrega tu propia key en <a href="<?= url('/settings/integrations/core') ?>" class="underline">Integraciones</a>.</p>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- KPIs -->
<div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-4">
    <?php
    $cards = [
        ['Conversaciones IA',  $kpi['handled'],       '💬', 'Conversaciones atendidas en 30d'],
        ['Mensajes enviados',  $kpi['sent_messages'], '⚡', 'Mensajes generados por IA en 30d'],
        ['Escalados a humano', $kpi['transferred'],   '🙋', 'Handoffs en 30d'],
        ['Ventas cerradas',    $kpi['sales_closed'],  '💰', 'Marcadas con [CLOSE_SALE]'],
        ['Tokens (in / out)',  number_format($kpi['tokens_in']) . ' / ' . number_format($kpi['tokens_out']), '🧮', 'Consumo de tokens 30d'],
    ];
    foreach ($cards as [$label, $value, $icon, $hint]):
    ?>
    <div class="glass rounded-2xl p-4" title="<?= e($hint) ?>">
        <div class="flex items-center justify-between mb-1">
            <span class="text-[10px] uppercase tracking-wider dark:text-slate-400 text-slate-500"><?= e($label) ?></span>
            <span class="text-base"><?= $icon ?></span>
        </div>
        <div class="text-xl font-bold dark:text-white text-slate-900"><?= e((string) $value) ?></div>
    </div>
    <?php endforeach; ?>
</div>

<div class="grid lg:grid-cols-2 gap-4 mb-4">
    <form action="<?= url('/settings/ai') ?>" method="POST" class="glass rounded-2xl p-5">
        <?= csrf_field() ?>
        <input type="hidden" name="_method" value="PUT">
        <h3 class="font-bold dark:text-white text-slate-900 mb-3">Configuracion global IA</h3>
        <div class="space-y-3">
            <div>
                <label class="text-xs uppercase tracking-wider dark:text-slate-400 text-slate-500">Nombre del asistente (visible al cliente)</label>
                <input type="text" name="ai_assistant_name" value="<?= e((string) ($tenant['ai_assistant_name'] ?? 'Asistente')) ?>"
                       class="w-full mt-1 px-3 py-2 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-lg dark:text-white text-slate-900">
            </div>
            <div>
                <label class="text-xs uppercase tracking-wider dark:text-slate-400 text-slate-500">Tono general</label>
                <input type="text" name="ai_tone" value="<?= e((string) ($tenant['ai_tone'] ?? 'profesional, cercano y claro')) ?>"
                       class="w-full mt-1 px-3 py-2 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-lg dark:text-white text-slate-900">
            </div>
            <label class="flex items-center gap-2 dark:text-slate-300 text-slate-700">
                <input type="checkbox" name="ai_enabled" value="1" <?= !empty($tenant['ai_enabled']) ? 'checked' : '' ?> class="w-4 h-4 rounded">
                Activar bot IA en mensajes entrantes (master switch)
            </label>
            <div>
                <label class="text-xs uppercase tracking-wider dark:text-slate-400 text-slate-500">Mensaje de bienvenida</label>
                <textarea name="welcome_message" rows="2" class="w-full mt-1 px-3 py-2 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-lg dark:text-white text-slate-900"><?= e((string) ($tenant['welcome_message'] ?? '')) ?></textarea>
            </div>
            <div>
                <label class="text-xs uppercase tracking-wider dark:text-slate-400 text-slate-500">Mensaje fuera de horario</label>
                <textarea name="out_of_hours_msg" rows="2" class="w-full mt-1 px-3 py-2 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-lg dark:text-white text-slate-900"><?= e((string) ($tenant['out_of_hours_msg'] ?? '')) ?></textarea>
            </div>
            <button type="submit" class="px-5 py-2 rounded-xl text-white text-sm font-semibold" style="background:linear-gradient(135deg,#7C3AED,#06B6D4)">Guardar global</button>
        </div>
    </form>

    <form action="<?= url('/settings/ai/knowledge') ?>" method="POST" class="glass rounded-2xl p-5">
        <?= csrf_field() ?>
        <h3 class="font-bold dark:text-white text-slate-900 mb-3">Agregar a base de conocimiento</h3>
        <p class="text-xs dark:text-slate-400 text-slate-500 mb-3">Articulos que TODOS los agentes IA pueden citar al cliente.</p>
        <div class="space-y-3">
            <input type="text" name="category" required placeholder="Categoria: empresa, productos, faq..."
                   class="w-full px-3 py-2 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-lg dark:text-white text-slate-900">
            <input type="text" name="title" required placeholder="Titulo del articulo"
                   class="w-full px-3 py-2 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-lg dark:text-white text-slate-900">
            <textarea name="content" rows="5" required placeholder="Contenido del articulo, FAQ, politica..."
                      class="w-full px-3 py-2 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-lg dark:text-white text-slate-900"></textarea>
            <button type="submit" class="px-5 py-2 rounded-xl text-white text-sm font-semibold" style="background:linear-gradient(135deg,#7C3AED,#06B6D4)">Agregar</button>
        </div>
    </form>
</div>

<!-- ============================ AGENTES IA ============================ -->
<div class="flex items-center justify-between mb-3">
    <h2 class="text-lg font-bold dark:text-white text-slate-900">Equipo de agentes IA</h2>
    <button onclick="document.getElementById('newAgentForm').scrollIntoView({behavior:'smooth'})" class="px-4 py-2 rounded-xl text-white text-sm font-semibold" style="background:linear-gradient(135deg,#7C3AED,#06B6D4)">+ Nuevo agente</button>
</div>

<?php if (empty($agents)): ?>
<div class="glass rounded-2xl p-8 text-center mb-4">
    <div class="text-5xl mb-3">🤖</div>
    <h4 class="font-bold dark:text-white text-slate-900 mb-1">Aun no tienes agentes IA</h4>
    <p class="text-sm dark:text-slate-400 text-slate-500 mb-3">Crea agentes especializados (ventas, soporte, agendamiento...) y la IA los enrutara automaticamente segun el mensaje del cliente.</p>
</div>
<?php else: ?>
<div class="grid md:grid-cols-2 gap-3 mb-6">
    <?php foreach ($agents as $a):
        $cat = (string) ($a['category'] ?? 'generic');
        [$catLabel, $catIcon] = $categories[$cat] ?? $categories['generic'];
        $triggers = $decode($a['trigger_keywords'] ?? null);
        $transfers = $decode($a['transfer_keywords'] ?? null);
        $channels = $decode($a['channels'] ?? null);
        $hours = $decode($a['working_hours'] ?? null);
        $emoji = trim((string) ($a['avatar_emoji'] ?? '')) ?: $catIcon;
    ?>
    <details class="glass rounded-2xl p-4">
        <summary class="cursor-pointer list-none">
            <div class="flex items-start justify-between gap-3">
                <div class="flex items-start gap-3 min-w-0">
                    <div class="w-12 h-12 rounded-xl flex items-center justify-center text-2xl flex-shrink-0" style="background: linear-gradient(135deg,rgba(124,58,237,.2),rgba(6,182,212,.2));">
                        <?= e($emoji) ?>
                    </div>
                    <div class="min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="font-bold dark:text-white text-slate-900"><?= e($a['name']) ?></span>
                            <span class="text-[10px] px-2 py-0.5 rounded bg-violet-500/15 text-violet-300 uppercase tracking-wider"><?= e($catLabel) ?></span>
                            <?php if (!empty($a['is_default'])): ?><span class="text-[10px] px-2 py-0.5 rounded bg-cyan-500/15 text-cyan-300">Principal</span><?php endif; ?>
                            <?php if (!empty($a['auto_reply_enabled'])): ?>
                            <span class="text-[10px] px-2 py-0.5 rounded bg-emerald-500/15 text-emerald-300">● Auto</span>
                            <?php else: ?>
                            <span class="text-[10px] px-2 py-0.5 rounded bg-slate-500/15 text-slate-400">Pausado</span>
                            <?php endif; ?>
                        </div>
                        <div class="text-xs dark:text-slate-400 text-slate-500 mt-0.5"><?= e((string) ($a['role'] ?? 'Sin rol definido')) ?></div>
                        <?php if (!empty($triggers)): ?>
                        <div class="mt-1.5 flex flex-wrap gap-1">
                            <?php foreach (array_slice($triggers, 0, 5) as $kw): ?>
                            <span class="text-[10px] px-1.5 py-0.5 rounded bg-white/5 dark:text-slate-400 text-slate-500">#<?= e($kw) ?></span>
                            <?php endforeach; ?>
                            <?php if (count($triggers) > 5): ?><span class="text-[10px] dark:text-slate-500 text-slate-400">+<?= count($triggers)-5 ?></span><?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="flex items-center gap-1 flex-shrink-0">
                    <form action="<?= url('/settings/ai/agents/' . $a['id'] . '/toggle') ?>" method="POST" class="inline">
                        <?= csrf_field() ?>
                        <button class="p-1.5 rounded-md glass" title="<?= !empty($a['auto_reply_enabled']) ? 'Pausar auto-reply' : 'Activar auto-reply' ?>">
                            <?= !empty($a['auto_reply_enabled']) ? '⏸' : '▶' ?>
                        </button>
                    </form>
                </div>
            </div>
        </summary>

        <!-- Form de edicion -->
        <form action="<?= url('/settings/ai/agents/' . $a['id']) ?>" method="POST" class="mt-4 pt-4 border-t dark:border-white/10 border-slate-200 space-y-3">
            <?= csrf_field() ?>
            <input type="hidden" name="_method" value="PUT">

            <div class="grid md:grid-cols-2 gap-3">
                <div>
                    <label class="text-xs uppercase tracking-wider dark:text-slate-400 text-slate-500">Nombre</label>
                    <input type="text" name="name" value="<?= e($a['name']) ?>" required class="w-full mt-1 px-3 py-2 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-lg dark:text-white text-slate-900">
                </div>
                <div>
                    <label class="text-xs uppercase tracking-wider dark:text-slate-400 text-slate-500">Categoria</label>
                    <select name="category" class="w-full mt-1 px-3 py-2 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-lg dark:text-white text-slate-900">
                        <?php foreach ($categories as $val => [$label, $i]): ?>
                        <option value="<?= e($val) ?>" <?= $cat === $val ? 'selected' : '' ?>><?= e($i . ' ' . $label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="text-xs uppercase tracking-wider dark:text-slate-400 text-slate-500">Rol interno</label>
                    <input type="text" name="role" value="<?= e((string) ($a['role'] ?? '')) ?>" placeholder="Vendedor consultivo, soporte tecnico..." class="w-full mt-1 px-3 py-2 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-lg dark:text-white text-slate-900">
                </div>
                <div>
                    <label class="text-xs uppercase tracking-wider dark:text-slate-400 text-slate-500">Avatar (emoji)</label>
                    <input type="text" name="avatar_emoji" value="<?= e((string) ($a['avatar_emoji'] ?? '')) ?>" placeholder="🤖 💰 🎧" maxlength="4" class="w-full mt-1 px-3 py-2 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-lg dark:text-white text-slate-900">
                </div>
                <div class="md:col-span-2">
                    <label class="text-xs uppercase tracking-wider dark:text-slate-400 text-slate-500">Objetivo</label>
                    <textarea name="objective" rows="2" class="w-full mt-1 px-3 py-2 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-lg dark:text-white text-slate-900"><?= e((string) ($a['objective'] ?? '')) ?></textarea>
                </div>
                <div class="md:col-span-2">
                    <label class="text-xs uppercase tracking-wider dark:text-slate-400 text-slate-500">Instrucciones operativas (manual del agente)</label>
                    <textarea name="instructions" rows="6" placeholder="Reglas, flujo de venta, datos a pedir, politicas, ejemplos..." class="w-full mt-1 px-3 py-2 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-lg dark:text-white text-slate-900"><?= e((string) ($a['instructions'] ?? '')) ?></textarea>
                </div>
                <div>
                    <label class="text-xs uppercase tracking-wider dark:text-slate-400 text-slate-500">Tono</label>
                    <input type="text" name="tone" value="<?= e((string) ($a['tone'] ?? '')) ?>" class="w-full mt-1 px-3 py-2 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-lg dark:text-white text-slate-900">
                </div>
                <div>
                    <label class="text-xs uppercase tracking-wider dark:text-slate-400 text-slate-500">Modelo (opcional)</label>
                    <input type="text" name="model" value="<?= e((string) ($a['model'] ?? '')) ?>" placeholder="hereda del tenant" class="w-full mt-1 px-3 py-2 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-lg dark:text-white text-slate-900">
                </div>

                <div>
                    <label class="text-xs uppercase tracking-wider dark:text-slate-400 text-slate-500">Prioridad (1=alta, 999=baja)</label>
                    <input type="number" name="priority" value="<?= (int) ($a['priority'] ?? 100) ?>" min="1" max="999" class="w-full mt-1 px-3 py-2 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-lg dark:text-white text-slate-900">
                </div>
                <div>
                    <label class="text-xs uppercase tracking-wider dark:text-slate-400 text-slate-500">Reintentos antes de escalar</label>
                    <input type="number" name="max_retries" value="<?= (int) ($a['max_retries'] ?? 3) ?>" min="1" max="20" class="w-full mt-1 px-3 py-2 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-lg dark:text-white text-slate-900">
                </div>

                <div class="md:col-span-2">
                    <label class="text-xs uppercase tracking-wider dark:text-slate-400 text-slate-500">Palabras clave que activan este agente (separa por coma)</label>
                    <textarea name="trigger_keywords" rows="2" placeholder="precio, comprar, plan, cotizacion, factura..."
                              class="w-full mt-1 px-3 py-2 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-lg dark:text-white text-slate-900"><?= e(implode(', ', $triggers)) ?></textarea>
                </div>
                <div class="md:col-span-2">
                    <label class="text-xs uppercase tracking-wider dark:text-slate-400 text-slate-500">Palabras que disparan transferencia a humano</label>
                    <textarea name="transfer_keywords" rows="2" placeholder="humano, agente real, hablar con persona, queja, demanda..."
                              class="w-full mt-1 px-3 py-2 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-lg dark:text-white text-slate-900"><?= e(implode(', ', $transfers)) ?></textarea>
                </div>

                <div class="md:col-span-2">
                    <label class="text-xs uppercase tracking-wider dark:text-slate-400 text-slate-500">Canales donde opera (vacio = todos)</label>
                    <div class="mt-2 flex flex-wrap gap-2">
                        <?php foreach (['whatsapp','email','webchat','instagram','facebook','telegram'] as $ch):
                            $checked = empty($channels) ? false : in_array($ch, $channels, true);
                        ?>
                        <label class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg cursor-pointer text-xs dark:bg-white/5 bg-slate-50 border dark:border-white/10 border-slate-200">
                            <input type="checkbox" name="channels[]" value="<?= e($ch) ?>" <?= $checked ? 'checked' : '' ?> class="w-3.5 h-3.5 rounded">
                            <span class="dark:text-slate-300 text-slate-700"><?= ucfirst($ch) ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="md:col-span-2">
                    <label class="text-xs uppercase tracking-wider dark:text-slate-400 text-slate-500">Horario de operacion (vacio = 24/7)</label>
                    <div class="mt-2 grid grid-cols-1 sm:grid-cols-2 gap-2">
                        <?php foreach ($days as $key => $label):
                            $cfg = $hours[$key] ?? ['enabled'=>false,'start'=>'09:00','end'=>'18:00'];
                        ?>
                        <div class="flex items-center gap-2 px-2 py-1.5 rounded-lg dark:bg-white/5 bg-slate-50 border dark:border-white/10 border-slate-200 text-xs">
                            <label class="flex items-center gap-1.5 w-16 dark:text-slate-300 text-slate-700">
                                <input type="checkbox" name="agent_hours[<?= $key ?>][enabled]" value="1" <?= !empty($cfg['enabled']) ? 'checked' : '' ?> class="w-3 h-3 rounded">
                                <span><?= $label ?></span>
                            </label>
                            <input type="time" name="agent_hours[<?= $key ?>][start]" value="<?= e((string) ($cfg['start'] ?? '09:00')) ?>" class="flex-1 px-2 py-1 rounded dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 dark:text-white text-slate-900">
                            <span class="dark:text-slate-500 text-slate-400">-</span>
                            <input type="time" name="agent_hours[<?= $key ?>][end]" value="<?= e((string) ($cfg['end'] ?? '18:00')) ?>" class="flex-1 px-2 py-1 rounded dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 dark:text-white text-slate-900">
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <label class="flex items-center gap-2 text-sm dark:text-slate-300 text-slate-700">
                    <input type="checkbox" name="auto_reply_enabled" value="1" <?= !empty($a['auto_reply_enabled']) ? 'checked' : '' ?> class="w-4 h-4 rounded">
                    Responder automaticamente
                </label>
                <label class="flex items-center gap-2 text-sm dark:text-slate-300 text-slate-700">
                    <input type="checkbox" name="is_default" value="1" <?= !empty($a['is_default']) ? 'checked' : '' ?> class="w-4 h-4 rounded">
                    Agente principal (fallback)
                </label>
            </div>

            <div class="flex flex-wrap items-center justify-end gap-2 pt-3 border-t dark:border-white/10 border-slate-200">
                <form action="<?= url('/settings/ai/agents/' . $a['id'] . '/duplicate') ?>" method="POST" class="inline">
                    <?= csrf_field() ?>
                    <button class="px-3 py-1.5 rounded-lg text-xs glass dark:text-slate-300 text-slate-600">Duplicar</button>
                </form>
                <form action="<?= url('/settings/ai/agents/' . $a['id']) ?>" method="POST" class="inline" onsubmit="return confirm('Eliminar este agente?')">
                    <?= csrf_field() ?>
                    <input type="hidden" name="_method" value="DELETE">
                    <button class="px-3 py-1.5 rounded-lg text-xs text-red-500 hover:bg-red-500/10">Eliminar</button>
                </form>
                <button type="submit" class="px-4 py-2 rounded-lg text-white text-sm font-semibold" style="background:linear-gradient(135deg,#7C3AED,#06B6D4)">Guardar cambios</button>
            </div>
        </form>
    </details>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Form NUEVO agente -->
<form id="newAgentForm" action="<?= url('/settings/ai/agents') ?>" method="POST" class="glass rounded-2xl p-5">
    <?= csrf_field() ?>
    <h3 class="font-bold dark:text-white text-slate-900 mb-3">Crear nuevo agente IA</h3>
    <div class="grid md:grid-cols-2 gap-3">
        <div>
            <label class="text-xs uppercase tracking-wider dark:text-slate-400 text-slate-500">Nombre</label>
            <input type="text" name="name" required placeholder="Vendedor IA, Soporte IA, Agendador..." class="w-full mt-1 px-3 py-2 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-lg dark:text-white text-slate-900">
        </div>
        <div>
            <label class="text-xs uppercase tracking-wider dark:text-slate-400 text-slate-500">Categoria</label>
            <select name="category" class="w-full mt-1 px-3 py-2 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-lg dark:text-white text-slate-900">
                <?php foreach ($categories as $val => [$label, $i]): ?>
                <option value="<?= e($val) ?>"><?= e($i . ' ' . $label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="text-xs uppercase tracking-wider dark:text-slate-400 text-slate-500">Rol</label>
            <input type="text" name="role" placeholder="Vendedor consultivo de planes..." class="w-full mt-1 px-3 py-2 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-lg dark:text-white text-slate-900">
        </div>
        <div>
            <label class="text-xs uppercase tracking-wider dark:text-slate-400 text-slate-500">Avatar (emoji)</label>
            <input type="text" name="avatar_emoji" value="🤖" maxlength="4" class="w-full mt-1 px-3 py-2 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-lg dark:text-white text-slate-900">
        </div>
        <div class="md:col-span-2">
            <label class="text-xs uppercase tracking-wider dark:text-slate-400 text-slate-500">Objetivo</label>
            <textarea name="objective" rows="2" placeholder="Que debe lograr este agente"
                      class="w-full mt-1 px-3 py-2 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-lg dark:text-white text-slate-900"></textarea>
        </div>
        <div class="md:col-span-2">
            <label class="text-xs uppercase tracking-wider dark:text-slate-400 text-slate-500">Instrucciones operativas</label>
            <textarea name="instructions" rows="6" placeholder="Reglas, flujo, datos que pedir, cuando transferir, ejemplos..."
                      class="w-full mt-1 px-3 py-2 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-lg dark:text-white text-slate-900"></textarea>
        </div>
        <div>
            <label class="text-xs uppercase tracking-wider dark:text-slate-400 text-slate-500">Tono</label>
            <input type="text" name="tone" value="profesional, claro y orientado a vender" class="w-full mt-1 px-3 py-2 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-lg dark:text-white text-slate-900">
        </div>
        <div>
            <label class="text-xs uppercase tracking-wider dark:text-slate-400 text-slate-500">Prioridad</label>
            <input type="number" name="priority" value="100" min="1" max="999" class="w-full mt-1 px-3 py-2 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-lg dark:text-white text-slate-900">
        </div>
        <div class="md:col-span-2">
            <label class="text-xs uppercase tracking-wider dark:text-slate-400 text-slate-500">Palabras clave que activan este agente (coma separadas)</label>
            <input type="text" name="trigger_keywords" placeholder="precio, comprar, plan, factura, cotizacion" class="w-full mt-1 px-3 py-2 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-lg dark:text-white text-slate-900">
        </div>
        <label class="flex items-center gap-2 text-sm dark:text-slate-300 text-slate-700">
            <input type="checkbox" name="auto_reply_enabled" value="1" checked class="w-4 h-4 rounded">
            Responder automaticamente
        </label>
        <label class="flex items-center gap-2 text-sm dark:text-slate-300 text-slate-700">
            <input type="checkbox" name="is_default" value="1" <?= empty($agents) ? 'checked' : '' ?> class="w-4 h-4 rounded">
            Agente principal (recibe todo lo no enrutado)
        </label>
    </div>
    <button type="submit" class="mt-4 px-5 py-2 rounded-xl text-white text-sm font-semibold" style="background:linear-gradient(135deg,#7C3AED,#06B6D4)">Crear agente</button>
</form>

<!-- Base de conocimiento listado -->
<div class="glass rounded-2xl p-5 mt-4">
    <h3 class="font-bold dark:text-white text-slate-900 mb-3">Base de conocimiento (<?= count($knowledge) ?>)</h3>
    <?php if (empty($knowledge)): ?>
    <p class="text-sm dark:text-slate-400 text-slate-500">Aun no hay articulos. Agrega arriba para entrenar a TODOS los agentes.</p>
    <?php else: ?>
    <div class="space-y-2">
        <?php foreach ($knowledge as $k): ?>
        <div class="p-3 rounded-xl dark:bg-white/5 bg-slate-50 border dark:border-white/5 border-slate-200">
            <div class="flex items-start justify-between gap-3 mb-1">
                <div class="min-w-0">
                    <span class="text-xs uppercase tracking-wider dark:text-cyan-400 text-cyan-600"><?= e($k['category']) ?></span>
                    <div class="font-semibold dark:text-white text-slate-900"><?= e($k['title']) ?></div>
                </div>
                <form action="<?= url('/settings/ai/knowledge/' . $k['id']) ?>" method="POST" onsubmit="return confirm('Eliminar?')">
                    <?= csrf_field() ?>
                    <input type="hidden" name="_method" value="DELETE">
                    <button class="text-red-500 hover:bg-red-500/10 rounded p-1">&times;</button>
                </form>
            </div>
            <p class="text-sm dark:text-slate-300 text-slate-700 whitespace-pre-line"><?= e((string) $k['content']) ?></p>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<?php \App\Core\View::stop(); ?>
