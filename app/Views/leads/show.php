<?php
\App\Core\View::extend('layouts.app');
\App\Core\View::start('content');
$contactName = trim(($lead['first_name'] ?? '') . ' ' . ($lead['last_name'] ?? ''));
?>

<div class="mb-6 flex items-center justify-between gap-3 flex-wrap">
    <div>
        <div class="flex items-center gap-2 mb-1 text-xs">
            <span class="px-2 py-0.5 rounded-full text-white" style="background:<?= e((string) ($lead['stage_color'] ?? '#7C3AED')) ?>"><?= e((string) ($lead['stage_name'] ?? '')) ?></span>
            <span class="dark:text-slate-400 text-slate-500">· Probabilidad <?= (int) $lead['probability'] ?>%</span>
        </div>
        <h1 class="text-2xl font-extrabold dark:text-white text-slate-900"><?= e($lead['title']) ?></h1>
        <?php if ($contactName): ?>
        <a href="<?= url('/contacts/' . $lead['contact_id']) ?>" class="text-sm dark:text-cyan-400 text-cyan-600 hover:underline"><?= e($contactName) ?></a>
        <?php endif; ?>
    </div>
    <div class="flex items-center gap-2">
        <button onclick="aiAnalyze(<?= (int) $lead['id'] ?>)" class="px-4 py-2 rounded-xl glass text-sm dark:text-white text-slate-900 hover:opacity-80 inline-flex items-center gap-2">
            🤖 Analizar con IA
        </button>
    </div>
</div>

<div class="grid lg:grid-cols-3 gap-4">
    <div class="lg:col-span-2 space-y-4">
        <!-- Edit form -->
        <form action="<?= url('/leads/' . $lead['id']) ?>" method="POST" class="glass rounded-2xl p-5">
            <?= csrf_field() ?>
            <input type="hidden" name="_method" value="PUT">

            <h3 class="font-bold dark:text-white text-slate-900 mb-4">Detalles</h3>
            <div class="grid md:grid-cols-2 gap-3">
                <div>
                    <label class="text-xs uppercase tracking-wider dark:text-slate-400 text-slate-500">Titulo</label>
                    <input type="text" name="title" value="<?= e($lead['title']) ?>"
                           class="w-full mt-1 px-3 py-2 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-lg dark:text-white text-slate-900">
                </div>
                <div>
                    <label class="text-xs uppercase tracking-wider dark:text-slate-400 text-slate-500">Valor</label>
                    <input type="number" step="0.01" name="value" value="<?= e((string) $lead['value']) ?>"
                           class="w-full mt-1 px-3 py-2 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-lg dark:text-white text-slate-900">
                </div>
                <div>
                    <label class="text-xs uppercase tracking-wider dark:text-slate-400 text-slate-500">Probabilidad %</label>
                    <input type="number" name="probability" value="<?= (int) $lead['probability'] ?>" min="0" max="100"
                           class="w-full mt-1 px-3 py-2 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-lg dark:text-white text-slate-900">
                </div>
                <div>
                    <label class="text-xs uppercase tracking-wider dark:text-slate-400 text-slate-500">Cierre estimado</label>
                    <input type="date" name="expected_close" value="<?= e((string) ($lead['expected_close'] ?? '')) ?>"
                           class="w-full mt-1 px-3 py-2 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-lg dark:text-white text-slate-900">
                </div>
                <div>
                    <label class="text-xs uppercase tracking-wider dark:text-slate-400 text-slate-500">Responsable</label>
                    <select name="assigned_to" class="w-full mt-1 px-3 py-2 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-lg dark:text-white text-slate-900">
                        <option value="">Sin asignar</option>
                        <?php foreach ($agents as $a): ?>
                        <option value="<?= (int) $a['id'] ?>" <?= ((int) $lead['assigned_to']) === (int) $a['id'] ? 'selected' : '' ?>>
                            <?= e($a['first_name'] . ' ' . $a['last_name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="text-xs uppercase tracking-wider dark:text-slate-400 text-slate-500">Fuente</label>
                    <input type="text" name="source" value="<?= e((string) ($lead['source'] ?? '')) ?>"
                           class="w-full mt-1 px-3 py-2 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-lg dark:text-white text-slate-900">
                </div>
                <div class="md:col-span-2">
                    <label class="text-xs uppercase tracking-wider dark:text-slate-400 text-slate-500">Descripcion</label>
                    <textarea name="description" rows="3"
                              class="w-full mt-1 px-3 py-2 dark:bg-white/5 bg-white border dark:border-white/10 border-slate-200 rounded-lg dark:text-white text-slate-900"><?= e((string) ($lead['description'] ?? '')) ?></textarea>
                </div>
            </div>
            <div class="flex justify-end mt-4">
                <button type="submit" class="px-5 py-2 rounded-xl text-white text-sm font-semibold" style="background:linear-gradient(135deg,#7C3AED,#06B6D4)">Guardar</button>
            </div>
        </form>

        <!-- AI panel -->
        <div id="aiPanel" class="glass rounded-2xl p-5" style="<?= empty($lead['ai_recommendation']) ? '' : '' ?>">
            <h3 class="font-bold dark:text-white text-slate-900 mb-2">🤖 Recomendacion IA</h3>
            <div class="text-sm dark:text-slate-300 text-slate-700" id="aiRec">
                <?= !empty($lead['ai_recommendation']) ? e((string) $lead['ai_recommendation']) : '<span class="dark:text-slate-500 text-slate-500">Click "Analizar con IA" para obtener una recomendacion personalizada.</span>' ?>
            </div>
            <?php if ((int) $lead['ai_score'] > 0): ?>
            <div class="mt-3 flex items-center gap-2">
                <span class="text-xs uppercase tracking-wider dark:text-slate-400 text-slate-500">Score IA</span>
                <div class="flex-1 h-2 dark:bg-white/5 bg-slate-200 rounded-full overflow-hidden">
                    <div class="h-full" style="width: <?= (int) $lead['ai_score'] ?>%; background: linear-gradient(90deg,#7C3AED,#06B6D4)"></div>
                </div>
                <span class="font-bold dark:text-white text-slate-900"><?= (int) $lead['ai_score'] ?>/100</span>
            </div>
            <?php endif; ?>
        </div>

        <!-- Conversaciones -->
        <?php if (!empty($conversations)): ?>
        <div class="glass rounded-2xl p-5">
            <h3 class="font-bold dark:text-white text-slate-900 mb-3">Conversaciones recientes</h3>
            <div class="space-y-2">
                <?php foreach ($conversations as $c): ?>
                <a href="<?= url('/inbox/' . $c['id']) ?>" class="flex items-center gap-2 p-2 rounded-lg dark:hover:bg-white/5 hover:bg-slate-50">
                    <span class="px-2 py-0.5 text-xs rounded-full bg-cyan-500/20 text-cyan-300"><?= e($c['channel']) ?></span>
                    <span class="text-sm dark:text-slate-200 text-slate-800 truncate flex-1"><?= e((string) ($c['last_message'] ?? '...')) ?></span>
                    <span class="text-xs dark:text-slate-500 text-slate-500"><?= time_ago((string) $c['last_message_at']) ?></span>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Sidebar -->
    <div class="space-y-4">
        <div class="glass rounded-2xl p-5">
            <h3 class="font-bold dark:text-white text-slate-900 mb-3">Resumen</h3>
            <div class="space-y-3">
                <div>
                    <div class="text-xs uppercase tracking-wider dark:text-slate-400 text-slate-500">Valor</div>
                    <div class="text-2xl font-extrabold dark:text-white text-slate-900"><?= format_currency((float) $lead['value'], (string) ($lead['currency'] ?? 'USD')) ?></div>
                </div>
                <div>
                    <div class="text-xs uppercase tracking-wider dark:text-slate-400 text-slate-500">Esperado (ponderado)</div>
                    <div class="text-lg font-bold dark:text-white text-slate-900"><?= format_currency((float) $lead['value'] * (int) $lead['probability'] / 100) ?></div>
                </div>
                <div>
                    <div class="text-xs uppercase tracking-wider dark:text-slate-400 text-slate-500">Estado</div>
                    <div class="font-semibold dark:text-white text-slate-900 capitalize"><?= e($lead['status']) ?></div>
                </div>
                <?php if (!empty($lead['agent_first'])): ?>
                <div>
                    <div class="text-xs uppercase tracking-wider dark:text-slate-400 text-slate-500">Responsable</div>
                    <div class="font-semibold dark:text-white text-slate-900"><?= e($lead['agent_first'] . ' ' . $lead['agent_last']) ?></div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <form action="<?= url('/leads/' . $lead['id']) ?>" method="POST" onsubmit="return confirm('Eliminar este lead?')" class="glass rounded-2xl p-4">
            <?= csrf_field() ?>
            <input type="hidden" name="_method" value="DELETE">
            <button type="submit" class="w-full px-3 py-2 rounded-xl text-red-500 hover:bg-red-500/10 text-sm">Eliminar lead</button>
        </form>
    </div>
</div>

<script>
async function aiAnalyze(id) {
    const panel = document.getElementById('aiRec');
    panel.textContent = 'Analizando...';
    try {
        const res = await fetch('<?= url('/leads/') ?>' + id + '/ai', {
            method: 'POST',
            headers: {
                'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content,
                'X-Requested-With': 'XMLHttpRequest',
            }
        });
        const data = await res.json();
        if (data.success) {
            panel.textContent = data.recommendation || 'Sin recomendacion.';
            if (data.score) location.reload();
        } else {
            panel.textContent = 'No se pudo conectar con la IA. Verifica tu API key de Claude en Configuracion.';
        }
    } catch (e) {
        panel.textContent = 'Error: ' + e.message;
    }
}
</script>

<?php \App\Core\View::stop(); ?>
