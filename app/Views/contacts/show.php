<?php
\App\Core\View::extend('layouts.app');
\App\Core\View::start('content');
$name = trim(($contact['first_name'] ?? '') . ' ' . ($contact['last_name'] ?? ''));
?>

<div class="mb-6 flex items-center justify-between gap-3 flex-wrap">
    <div class="flex items-center gap-4">
        <div class="w-16 h-16 rounded-2xl flex items-center justify-center text-white text-xl font-bold" style="background:linear-gradient(135deg,#10B981,#06B6D4)">
            <?= e(strtoupper(mb_substr($name, 0, 1))) ?>
        </div>
        <div>
            <h1 class="text-2xl font-extrabold dark:text-white text-slate-900"><?= e($name) ?></h1>
            <p class="dark:text-slate-400 text-slate-600 text-sm">
                <?= e((string) ($contact['company'] ?? 'Sin empresa')) ?>
                <?php if (!empty($contact['email'])): ?> · <?= e($contact['email']) ?><?php endif; ?>
            </p>
        </div>
    </div>
    <div class="flex items-center gap-2">
        <?php if (!empty($contact['whatsapp'])): ?>
        <a href="https://wa.me/<?= e(preg_replace('/[^\d]/', '', (string) $contact['whatsapp'])) ?>" target="_blank"
           class="px-4 py-2 rounded-xl bg-emerald-500 hover:bg-emerald-600 text-white text-sm font-semibold inline-flex items-center gap-2">
            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654z"/></svg>
            WhatsApp
        </a>
        <?php endif; ?>
        <a href="<?= url('/contacts/' . $contact['id'] . '/edit') ?>" class="px-4 py-2 rounded-xl glass text-sm dark:text-white text-slate-900 hover:opacity-80">Editar</a>
    </div>
</div>

<div class="grid lg:grid-cols-3 gap-4">
    <!-- Info -->
    <div class="glass rounded-2xl p-5">
        <h3 class="font-bold dark:text-white text-slate-900 mb-4">Informacion</h3>
        <dl class="space-y-2 text-sm">
            <?php
            $rows = [
                ['Telefono',    $contact['phone']           ?? '—'],
                ['WhatsApp',    $contact['whatsapp']        ?? '—'],
                ['Email',       $contact['email']           ?? '—'],
                ['Documento',   $contact['document_number'] ?? '—'],
                ['Direccion',   $contact['address']         ?? '—'],
                ['Ciudad',      $contact['city']            ?? '—'],
                ['Pais',        $contact['country']         ?? '—'],
                ['Fuente',      $contact['source']          ?? '—'],
                ['Estado',      $contact['status']          ?? '—'],
                ['Score IA',    ($contact['score'] ?? 0) . ' / 100'],
                ['Valor estimado', $contact['estimated_value'] ? format_currency((float) $contact['estimated_value']) : '—'],
                ['Ultima interaccion', $contact['last_interaction'] ? time_ago($contact['last_interaction']) : '—'],
                ['Creado',      time_ago((string) $contact['created_at'])],
            ];
            foreach ($rows as [$k, $v]): ?>
            <div class="flex justify-between gap-4">
                <dt class="dark:text-slate-400 text-slate-500"><?= e($k) ?></dt>
                <dd class="dark:text-white text-slate-900 text-right"><?= e((string) $v) ?></dd>
            </div>
            <?php endforeach; ?>
        </dl>

        <?php if (!empty($tags)): ?>
        <div class="mt-4 pt-4 border-t dark:border-white/5 border-slate-200">
            <div class="text-xs uppercase tracking-wider dark:text-slate-400 text-slate-500 mb-2">Etiquetas</div>
            <div class="flex flex-wrap gap-1.5">
                <?php foreach ($tags as $t): ?>
                <span class="px-2 py-0.5 text-xs rounded-full text-white" style="background:<?= e((string) $t['color']) ?>"><?= e($t['name']) ?></span>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($contact['notes'])): ?>
        <div class="mt-4 pt-4 border-t dark:border-white/5 border-slate-200">
            <div class="text-xs uppercase tracking-wider dark:text-slate-400 text-slate-500 mb-2">Notas</div>
            <p class="text-sm dark:text-slate-300 text-slate-700 whitespace-pre-line"><?= e((string) $contact['notes']) ?></p>
        </div>
        <?php endif; ?>

        <!-- Memoria del cliente (Fase 3) -->
        <?php
            $prefs = isset($contact['preferences']) && is_string($contact['preferences'])
                ? (json_decode((string) $contact['preferences'], true) ?: [])
                : (is_array($contact['preferences'] ?? null) ? $contact['preferences'] : []);
            $rfmSegment = (string) ($contact['rfm_segment'] ?? '');
            $lifetimeOrders = (int) ($contact['lifetime_orders'] ?? 0);
            $lifetimeValue  = (float) ($contact['lifetime_value'] ?? 0);
            $lastOrderAt    = $contact['last_order_at'] ?? null;
            $segMeta = [
                'vip'     => ['🌟 VIP',     'background: linear-gradient(135deg,#FBBF24,#F59E0B); color:#fff'],
                'regular' => ['Regular',    'background: rgba(16,185,129,.15); color:#10B981'],
                'nuevo'   => ['Nuevo',      'background: rgba(6,182,212,.15); color:#06B6D4'],
                'dormido' => ['💤 Dormido', 'background: rgba(245,158,11,.15); color:#F59E0B'],
                'perdido' => ['Perdido',    'background: rgba(244,63,94,.15); color:#F43F5E'],
            ];
        ?>
        <?php if ($lifetimeOrders > 0 || !empty($prefs)): ?>
        <div class="mt-4 pt-4 border-t dark:border-white/5 border-slate-200">
            <div class="flex items-center justify-between mb-3">
                <div class="text-xs uppercase tracking-wider dark:text-slate-400 text-slate-500 font-semibold">🧠 Memoria del cliente</div>
                <?php if ($rfmSegment !== '' && isset($segMeta[$rfmSegment])): ?>
                    <span class="px-2 py-0.5 text-xs rounded-full font-semibold" style="<?= e($segMeta[$rfmSegment][1]) ?>">
                        <?= e($segMeta[$rfmSegment][0]) ?>
                    </span>
                <?php endif; ?>
            </div>

            <?php if ($lifetimeOrders > 0): ?>
            <div class="grid grid-cols-2 gap-2 mb-3">
                <div class="rounded-lg p-2.5" style="background: var(--color-bg-subtle);">
                    <div class="text-[10px] uppercase tracking-wider dark:text-slate-400 text-slate-500">Pedidos</div>
                    <div class="font-bold text-lg dark:text-white text-slate-900"><?= number_format($lifetimeOrders) ?></div>
                </div>
                <div class="rounded-lg p-2.5" style="background: var(--color-bg-subtle);">
                    <div class="text-[10px] uppercase tracking-wider dark:text-slate-400 text-slate-500">Total gastado</div>
                    <div class="font-bold text-lg dark:text-white text-slate-900">$<?= number_format($lifetimeValue, 2) ?></div>
                </div>
                <?php if (!empty($prefs['avg_ticket'])): ?>
                <div class="rounded-lg p-2.5" style="background: var(--color-bg-subtle);">
                    <div class="text-[10px] uppercase tracking-wider dark:text-slate-400 text-slate-500">Ticket promedio</div>
                    <div class="font-bold text-sm dark:text-white text-slate-900">$<?= number_format((float) $prefs['avg_ticket'], 2) ?></div>
                </div>
                <?php endif; ?>
                <?php if (!empty($contact['rfm_score'])): ?>
                <div class="rounded-lg p-2.5" style="background: var(--color-bg-subtle);">
                    <div class="text-[10px] uppercase tracking-wider dark:text-slate-400 text-slate-500">RFM</div>
                    <div class="font-bold text-sm dark:text-white text-slate-900"><?= (int) $contact['rfm_score'] ?> / 1000</div>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php if (!empty($prefs['favorite_items'])): ?>
            <div class="mb-3">
                <div class="text-[10px] uppercase tracking-wider dark:text-slate-400 text-slate-500 font-semibold mb-1.5">Items favoritos</div>
                <div class="flex flex-wrap gap-1.5">
                    <?php foreach (array_slice($prefs['favorite_items'], 0, 5) as $f): ?>
                    <span class="px-2 py-1 text-xs rounded-lg" style="background: rgba(16,185,129,.10); color: #10B981; font-weight: 500;">
                        <?= e((string) ($f['name'] ?? '')) ?>
                        <span class="opacity-60 ml-1"><?= (int) ($f['times'] ?? 0) ?>×</span>
                    </span>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if (!empty($prefs['preferred_days']) || !empty($prefs['preferred_hours'])): ?>
            <div class="mb-3 text-xs dark:text-slate-300 text-slate-700">
                <span class="dark:text-slate-400 text-slate-500">Suele pedir:</span>
                <?php if (!empty($prefs['preferred_days'])): ?>
                    <strong><?= e(implode('/', array_slice($prefs['preferred_days'], 0, 3))) ?></strong>
                <?php endif; ?>
                <?php if (!empty($prefs['preferred_hours'])): ?>
                    a las <strong><?= e(implode('h, ', array_slice($prefs['preferred_hours'], 0, 2))) ?>h</strong>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php if (!empty($contact['notes_ai'])): ?>
            <div class="mt-2 pt-2 border-t dark:border-white/5 border-slate-200">
                <div class="text-[10px] uppercase tracking-wider dark:text-slate-400 text-slate-500 font-semibold mb-1">Notas IA</div>
                <p class="text-xs dark:text-slate-300 text-slate-700 whitespace-pre-line"><?= e((string) $contact['notes_ai']) ?></p>
            </div>
            <?php endif; ?>

            <form action="<?= e(url('/contacts/' . (int) $contact['id'] . '/refresh-memory')) ?>" method="POST" class="mt-3">
                <?= csrf_field() ?>
                <button type="submit" class="w-full py-1.5 text-xs rounded-lg font-semibold" style="background: var(--color-bg-subtle); color: var(--color-text-secondary); border: 1px solid var(--color-border-subtle);">
                    🔄 Refrescar perfil
                </button>
            </form>
        </div>
        <?php endif; ?>
    </div>

    <!-- Timeline -->
    <div class="lg:col-span-2 glass rounded-2xl p-5">
        <h3 class="font-bold dark:text-white text-slate-900 mb-4">Actividad</h3>
        <?php if (empty($timeline)): ?>
        <div class="text-sm dark:text-slate-400 text-slate-500 text-center py-8">Sin actividad aun.</div>
        <?php else: ?>
        <div class="space-y-3">
            <?php foreach ($timeline as $t):
                $iconCls = match ($t['kind']) {
                    'message' => $t['direction'] === 'inbound' ? 'bg-cyan-500/20 text-cyan-300' : 'bg-emerald-500/20 text-emerald-300',
                    'task'    => 'bg-yellow-500/20 text-yellow-300',
                    'ticket'  => 'bg-pink-500/20 text-pink-300',
                    default   => 'bg-slate-500/20 text-slate-300',
                };
                $icon = match ($t['kind']) {
                    'message' => '💬', 'task' => '✅', 'ticket' => '🎫', default => '•',
                };
                $label = match ($t['kind']) {
                    'message' => $t['direction'] === 'inbound' ? 'Mensaje recibido' : 'Mensaje enviado',
                    'task'    => 'Tarea',
                    'ticket'  => 'Ticket',
                    default   => 'Actividad',
                };
            ?>
            <div class="flex gap-3 p-3 rounded-xl dark:hover:bg-white/5 hover:bg-slate-50">
                <div class="w-9 h-9 rounded-full <?= $iconCls ?> flex items-center justify-center flex-shrink-0"><?= $icon ?></div>
                <div class="flex-1 min-w-0">
                    <div class="flex items-center justify-between gap-2 mb-1">
                        <span class="text-xs uppercase tracking-wider dark:text-slate-400 text-slate-500"><?= $label ?></span>
                        <span class="text-xs dark:text-slate-500 text-slate-500"><?= time_ago((string) $t['created_at']) ?></span>
                    </div>
                    <div class="text-sm dark:text-slate-200 text-slate-800 truncate"><?= e((string) ($t['body'] ?? '')) ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php \App\Core\View::stop(); ?>
