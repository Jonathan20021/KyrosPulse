<?php
/** @var array $conversations */
/** @var array|null $active */
/** @var array $messages */
/** @var array $filters */
/** @var array $agents */
/** @var array $aiAgents */
/** @var array $channels */
/** @var array $quickReplies */
\App\Core\View::extend('layouts.app');
\App\Core\View::start('content');

$activeId    = $active ? (int) $active['id'] : 0;
$contactName = $active ? trim(($active['first_name'] ?? '') . ' ' . ($active['last_name'] ?? '')) : '';

// ======================================================
// Helpers de presentación: linkify URLs + códigos #OR-...
// ======================================================
$baseUrl = url('');
$linkify = static function (string $raw) use ($baseUrl): string {
    $escaped = e($raw);
    $escaped = preg_replace_callback(
        '~(?<![">\w])(https?://[^\s<]+)~i',
        static fn ($m) => '<a href="' . $m[1] . '" target="_blank" rel="noopener" class="msg-link">' . mb_strimwidth($m[1], 0, 60, '…') . '</a>',
        $escaped
    );
    $escaped = preg_replace_callback(
        '~#?(OR-[A-Z0-9-]{4,})~i',
        static fn ($m) => '<a href="' . rtrim($baseUrl, '/') . '/orders" class="msg-order-tag">📦 ' . strtoupper($m[1]) . '</a>',
        $escaped
    );
    return $escaped;
};

// Agrupar archivos y notas para tabs del panel derecho
$conversationFiles = [];
$conversationNotes = [];
$msgCountIn = 0;
$msgCountOut = 0;
$lastInbound = null;
$lastOutbound = null;
foreach ($messages as $m) {
    if (!empty($m['media_url'])) {
        $conversationFiles[] = $m;
    }
    if (!empty($m['is_internal'])) {
        $conversationNotes[] = $m;
    }
    if ($m['direction'] === 'inbound') {
        $msgCountIn++;
        $lastInbound = $m['created_at'];
    } else {
        $msgCountOut++;
        $lastOutbound = $m['created_at'];
    }
}

// "Última vez visto" amigable
$lastSeenLabel = '';
if ($lastInbound) {
    $diff = time() - strtotime((string) $lastInbound);
    if ($diff < 120)        $lastSeenLabel = 'en línea ahora';
    elseif ($diff < 600)    $lastSeenLabel = 'activo hace pocos min';
    elseif ($diff < 3600)   $lastSeenLabel = 'activo hace ' . floor($diff / 60) . ' min';
    elseif ($diff < 86400)  $lastSeenLabel = 'activo hoy';
    else                    $lastSeenLabel = 'visto ' . date('d M', strtotime((string) $lastInbound));
}
?>

<!-- Botones flotantes para reabrir paneles cuando están colapsados -->
<button id="reopenInboxBtn" onclick="toggleInbox()" class="reopen-btn reopen-btn-left hidden" title="Mostrar bandeja">
    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
</button>
<button id="reopenInfoBtn" onclick="toggleInfo()" class="reopen-btn reopen-btn-right hidden" title="Mostrar panel de contacto">
    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
</button>

<script>
// Funciones globales (no dependen de conversación activa)
window.toggleInbox = function() {
    document.body.classList.remove('full-chat');
    document.body.classList.toggle('collapse-inbox');
    localStorage.setItem('kp_collapse_inbox', document.body.classList.contains('collapse-inbox') ? '1' : '0');
};
window.toggleInfo = function() {
    document.body.classList.remove('full-chat');
    document.body.classList.toggle('collapse-info');
    localStorage.setItem('kp_collapse_info', document.body.classList.contains('collapse-info') ? '1' : '0');
};
window.toggleFullChat = function() {
    document.body.classList.toggle('full-chat');
    const btn = document.getElementById('fullChatBtn');
    if (btn) btn.classList.toggle('text-violet-400');
};
// Restaurar estado al cargar
if (localStorage.getItem('kp_collapse_inbox') === '1') document.body.classList.add('collapse-inbox');
if (localStorage.getItem('kp_collapse_info')  === '1') document.body.classList.add('collapse-info');
</script>

<div class="chat-layout grid grid-cols-1 lg:grid-cols-12 gap-3 h-[calc(100vh-128px)]">

    <!-- ============================================================ -->
    <!-- LISTA DE CONVERSACIONES                                       -->
    <!-- ============================================================ -->
    <aside class="inbox-pane lg:col-span-4 xl:col-span-3 surface flex flex-col overflow-hidden">
        <div class="p-4 border-b" style="border-color: var(--color-border-subtle);">
            <div class="flex items-center justify-between mb-3">
                <div class="flex items-center gap-2">
                    <h2 class="font-bold text-[15px]" style="color: var(--color-text-primary);">Bandeja</h2>
                    <span id="inboxCount" class="badge badge-primary badge-dot"><?= count($conversations) ?></span>
                </div>
                <div class="flex items-center gap-0.5">
                    <button id="onlyUnreadBtn" class="btn btn-ghost btn-icon" title="Solo no leídas">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                    </button>
                    <details class="relative">
                        <summary class="btn btn-ghost btn-icon cursor-pointer list-none" title="Ordenar">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 4h13M3 8h9m-9 4h9m5-4v12m0 0l-4-4m4 4l4-4"/></svg>
                        </summary>
                        <div class="absolute right-0 top-full mt-1 w-44 rounded-xl shadow-xl border py-1 z-30" style="background: var(--color-bg-elevated); border-color: var(--color-border-default);">
                            <button class="dropdown-item w-full" data-sort="recent"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3"/><circle cx="12" cy="12" r="9"/></svg>Más recientes</button>
                            <button class="dropdown-item w-full" data-sort="unread"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><circle cx="12" cy="12" r="4" fill="currentColor"/></svg>No leídas primero</button>
                            <button class="dropdown-item w-full" data-sort="name"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7h6M3 12h12M3 17h18"/></svg>Por nombre A→Z</button>
                        </div>
                    </details>
                    <button onclick="toggleInbox()" class="btn btn-ghost btn-icon hidden lg:inline-flex" title="Colapsar bandeja">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                    </button>
                </div>
            </div>
            <form method="GET" class="relative mb-3">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 pointer-events-none" style="color: var(--color-text-tertiary);" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input type="text" name="q" value="<?= e((string) $filters['q']) ?>" placeholder="Buscar conversaciones..." class="input pl-10 text-sm">
                <?php if (!empty($filters['q'])): ?>
                <a href="<?= url('/inbox') ?>" class="absolute right-3 top-1/2 -translate-y-1/2" style="color: var(--color-text-tertiary);" title="Limpiar">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </a>
                <?php endif; ?>
            </form>

            <div class="flex items-center gap-1.5 overflow-x-auto scrollbar-thin pb-1">
                <?php foreach ([''=>'Todas', 'open'=>'Abiertas', 'pending'=>'En espera', 'resolved'=>'Resueltas', 'all'=>'Cerradas'] as $key => $label):
                    $current = $filters['status'] === $key;
                    $url = url('/inbox') . ($key !== '' ? '?status=' . urlencode($key) : '');
                ?>
                <a href="<?= e($url) ?>" class="px-2.5 py-1 rounded-full text-[11px] font-medium whitespace-nowrap transition <?= $current ? 'font-semibold' : '' ?>"
                   style="<?= $current ? 'background: var(--color-bg-active); color: var(--color-text-primary); border: 1px solid rgba(124,58,237,0.2);' : 'background: var(--color-bg-subtle); color: var(--color-text-tertiary);' ?>">
                    <?= $label ?>
                </a>
                <?php endforeach; ?>
            </div>

            <?php if (!empty($channels) && count($channels) > 1): ?>
            <div class="flex items-center gap-1.5 overflow-x-auto scrollbar-thin pb-1 mt-2">
                <span class="text-[10px] uppercase font-semibold flex-shrink-0" style="color: var(--color-text-tertiary);">Número:</span>
                <a href="<?= url('/inbox') ?>" class="px-2 py-0.5 rounded-full text-[10px] whitespace-nowrap" style="<?= empty($filters['channel_id']) ? 'background: var(--color-bg-active); color: var(--color-text-primary);' : 'background: var(--color-bg-subtle); color: var(--color-text-tertiary);' ?>">Todos</a>
                <?php foreach ($channels as $ch):
                    $cActive = (int) ($filters['channel_id'] ?? 0) === (int) $ch['id'];
                ?>
                <a href="<?= url('/inbox?channel_id=' . $ch['id']) ?>" class="px-2 py-0.5 rounded-full text-[10px] whitespace-nowrap flex items-center gap-1"
                   style="<?= $cActive ? 'background:' . e($ch['color']) . '22; color:' . e($ch['color']) . '; border:1px solid ' . e($ch['color']) . '55;' : 'background: var(--color-bg-subtle); color: var(--color-text-tertiary);' ?>">
                    <span class="w-1.5 h-1.5 rounded-full" style="background: <?= e($ch['color']) ?>;"></span>
                    <?= e(mb_strimwidth($ch['label'], 0, 14, '...')) ?>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <div id="convoList" class="flex-1 overflow-y-auto scrollbar-thin">
            <?php if (empty($conversations)): ?>
            <div class="empty-state-pro">
                <div class="empty-icon">📭</div>
                <h4 class="font-bold text-sm mb-1" style="color: var(--color-text-primary);">Sin conversaciones</h4>
                <p class="text-xs" style="color: var(--color-text-tertiary);">Conecta WhatsApp para empezar a recibir mensajes.</p>
            </div>
            <?php else: foreach ($conversations as $c):
                $name = trim(($c['first_name'] ?? '') . ' ' . ($c['last_name'] ?? '')) ?: ($c['phone'] ?? 'Sin nombre');
                $isActive = (int) $c['id'] === $activeId;
                $hasUnread = (int) $c['unread_count'] > 0;
                $hasAi = !empty($c['ai_agent_id']) || !empty($c['ai_takeover']);
            ?>
            <a href="<?= url('/inbox/' . $c['id']) ?>"
               class="convo-item flex items-start gap-3 px-4 py-3 transition border-b relative group"
               data-name="<?= e(strtolower($name)) ?>"
               data-unread="<?= $hasUnread ? '1' : '0' ?>"
               data-time="<?= e((string) ($c['last_message_at'] ?? $c['updated_at'])) ?>"
               style="border-color: var(--color-border-subtle); <?= $isActive ? 'background: var(--color-bg-active);' : '' ?>">
                <?php if ($isActive): ?>
                <div class="absolute left-0 top-3 bottom-3 w-[3px] rounded-r-full" style="background: var(--gradient-primary);"></div>
                <?php endif; ?>

                <div class="relative flex-shrink-0">
                    <div class="avatar avatar-md"><?= e(strtoupper(mb_substr($name, 0, 1))) ?></div>
                    <span class="absolute -bottom-0.5 -right-0.5 status-dot status-online" style="border: 2px solid var(--color-bg-surface);"></span>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex items-center justify-between gap-2 mb-0.5">
                        <span class="font-semibold text-sm truncate" style="color: var(--color-text-primary);"><?= e($name) ?></span>
                        <span class="text-[10px] flex-shrink-0 font-mono" style="color: <?= $hasUnread ? 'var(--color-primary)' : 'var(--color-text-muted)' ?>;"><?= time_ago((string) ($c['last_message_at'] ?? $c['updated_at'])) ?></span>
                    </div>
                    <div class="flex items-center gap-1 mb-1 flex-wrap">
                        <span class="badge <?= $c['channel'] === 'whatsapp' ? 'badge-emerald' : ($c['channel'] === 'email' ? 'badge-cyan' : 'badge-slate') ?>" style="font-size: 8px; padding: 1px 5px;">
                            <?= match($c['channel']) { 'whatsapp' => 'WA', 'email' => 'EMAIL', 'instagram' => 'IG', 'facebook' => 'FB', default => strtoupper((string) $c['channel']) } ?>
                        </span>
                        <?php if (!empty($c['channel_label'])): ?>
                        <span class="text-[8px] font-semibold uppercase tracking-wider px-1.5 rounded" style="background: <?= e((string) ($c['channel_color'] ?? '#7C3AED')) ?>22; color: <?= e((string) ($c['channel_color'] ?? '#7C3AED')) ?>;" title="<?= e((string) ($c['channel_phone'] ?? '')) ?>">
                            <?= e(mb_strimwidth((string) $c['channel_label'], 0, 12, '..')) ?>
                        </span>
                        <?php endif; ?>
                        <?php if ($hasAi): ?>
                        <span class="text-[8px] font-semibold uppercase tracking-wider px-1.5 rounded inline-flex items-center gap-0.5" style="background: rgba(124,58,237,.15); color:#A78BFA;" title="IA asignada">🤖 IA</span>
                        <?php endif; ?>
                        <?php if (!empty($c['is_starred'])): ?><span class="text-amber-400 text-[10px]">★</span><?php endif; ?>
                        <?php if (!empty($c['ai_sentiment'])):
                            $emoji = match($c['ai_sentiment']) { 'positive' => '😊', 'negative' => '😞', default => '😐' };
                        ?><span class="text-[9px]"><?= $emoji ?></span><?php endif; ?>
                    </div>
                    <div class="flex items-center justify-between gap-2">
                        <p class="text-xs truncate flex-1" style="color: <?= $hasUnread ? 'var(--color-text-secondary)' : 'var(--color-text-tertiary)' ?>; font-weight: <?= $hasUnread ? '500' : '400' ?>;"><?= e((string) ($c['last_message'] ?? '')) ?></p>
                        <?php if ($hasUnread): ?>
                        <span class="flex-shrink-0 min-w-[18px] h-[18px] px-1.5 rounded-full text-[10px] font-bold flex items-center justify-center text-white" style="background: var(--gradient-primary);"><?= (int) $c['unread_count'] ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </a>
            <?php endforeach; endif; ?>
        </div>
    </aside>

    <!-- ============================================================ -->
    <!-- CHAT PRINCIPAL                                                -->
    <!-- ============================================================ -->
    <section class="chat-pane lg:col-span-8 xl:col-span-6 surface flex flex-col overflow-hidden relative">
        <?php if (!$active): ?>
        <div class="flex-1 flex items-center justify-center p-12">
            <div class="text-center max-w-md">
                <div class="w-28 h-28 mx-auto rounded-3xl flex items-center justify-center mb-5 text-5xl relative" style="background: var(--gradient-mesh); border: 1px solid var(--color-border-subtle);">
                    <span class="relative z-10">💬</span>
                    <div class="absolute inset-0 rounded-3xl opacity-30" style="background: radial-gradient(circle, rgba(124,58,237,0.4), transparent 70%); filter: blur(20px);"></div>
                </div>
                <h3 class="text-lg font-bold mb-2" style="color: var(--color-text-primary);">Selecciona una conversación</h3>
                <p class="text-sm" style="color: var(--color-text-tertiary);">Elige una conversación de la lista para ver los mensajes.</p>
            </div>
        </div>
        <?php else: ?>

        <!-- ===== Chat Header ===== -->
        <div class="chat-header flex items-center justify-between gap-3 p-3.5 border-b" style="border-color: var(--color-border-subtle); background: var(--color-bg-surface);">
            <div class="flex items-center gap-3 min-w-0 flex-1">
                <div class="relative flex-shrink-0">
                    <div class="avatar avatar-lg"><?= e(strtoupper(mb_substr($contactName ?: 'X', 0, 1))) ?></div>
                    <span class="absolute -bottom-0.5 -right-0.5 status-dot status-online pulse-online" style="border: 2px solid var(--color-bg-surface); width: 12px; height: 12px;"></span>
                </div>
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2 mb-0.5 flex-wrap">
                        <h2 class="font-bold truncate text-[15px]" style="color: var(--color-text-primary);"><?= e($contactName ?: ($active['phone'] ?? 'Sin nombre')) ?></h2>
                        <?php if (!empty($active['ai_sentiment'])):
                            $cls = match ($active['ai_sentiment']) { 'positive' => 'badge-emerald', 'negative' => 'badge-rose', default => 'badge-slate' };
                            $lbl = match ($active['ai_sentiment']) { 'positive' => 'Positivo', 'negative' => 'Negativo', default => 'Neutral' };
                        ?>
                        <span class="badge <?= $cls ?>"><?= $lbl ?></span>
                        <?php endif; ?>
                        <?php if (!empty($active['lifecycle_stage'])): ?>
                        <span class="text-[9px] uppercase tracking-wider font-bold px-1.5 py-0.5 rounded" style="background: var(--color-bg-subtle); color: var(--color-text-secondary);"><?= e((string) $active['lifecycle_stage']) ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="text-[11px] flex items-center flex-wrap subhead" style="color: var(--color-text-tertiary);">
                        <?php if ($lastSeenLabel): ?>
                        <span class="subhead-item flex items-center gap-1" style="color:#34D399;">
                            <span class="w-1.5 h-1.5 rounded-full" style="background:#10B981;"></span>
                            <?= e($lastSeenLabel) ?>
                        </span>
                        <?php endif; ?>
                        <span class="subhead-item font-mono"><?= e((string) ($active['phone'] ?? $active['email'] ?? '')) ?></span>
                        <span class="subhead-item capitalize"><?= e($active['status']) ?></span>
                        <?php if (!empty($active['channel_label'])): ?>
                        <span class="subhead-item font-semibold flex items-center gap-1" style="color: <?= e((string) ($active['channel_color'] ?? '#7C3AED')) ?>;">
                            <span class="w-1.5 h-1.5 rounded-full" style="background: <?= e((string) ($active['channel_color'] ?? '#7C3AED')) ?>;"></span>
                            <?= e((string) $active['channel_label']) ?>
                        </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="flex items-center gap-1 flex-shrink-0">
                <?php
                    $aiActive = !empty($active['ai_agent_id']) || !empty($active['ai_takeover']);
                    $currentAi = null;
                    if (!empty($active['ai_agent_id']) && !empty($aiAgents)) {
                        foreach ($aiAgents as $aa) {
                            if ((int) $aa['id'] === (int) $active['ai_agent_id']) { $currentAi = $aa; break; }
                        }
                    }
                ?>
                <!-- AI Agent assignment -->
                <details class="relative">
                    <summary class="btn btn-ghost btn-sm cursor-pointer list-none flex items-center gap-1.5" style="<?= $aiActive ? 'background: linear-gradient(135deg, rgba(124,58,237,.15), rgba(6,182,212,.15)); color: #A78BFA;' : '' ?>" title="Agente IA">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                        <span class="hidden lg:inline text-xs"><?= $currentAi ? 'IA: ' . e(mb_strimwidth($currentAi['name'], 0, 14, '...')) : 'Asignar IA' ?></span>
                    </summary>
                    <div class="absolute right-0 top-full mt-1 w-72 rounded-xl shadow-xl border py-2 z-30" style="background: var(--color-bg-elevated); border-color: var(--color-border-default);">
                        <div class="px-3 pb-2 mb-2 border-b" style="border-color: var(--color-border-subtle);">
                            <div class="flex items-center gap-2 mb-1">
                                <span class="text-[10px] uppercase font-bold tracking-wider" style="color: var(--color-text-tertiary);">Modo IA</span>
                            </div>
                            <label class="flex items-center justify-between gap-2 p-2 rounded-lg cursor-pointer hover:bg-[color:var(--color-bg-subtle)]">
                                <span class="text-sm dark:text-white text-slate-900">Auto-pilot (la IA contesta sola)</span>
                                <input type="checkbox" id="aiTakeoverToggle" <?= !empty($active['ai_takeover']) ? 'checked' : '' ?> class="w-4 h-4 rounded">
                            </label>
                            <button type="button" onclick="aiRunNow()" class="w-full mt-2 px-3 py-2 rounded-lg text-white text-xs font-semibold" style="background: var(--gradient-primary);">
                                ⚡ Que la IA responda ahora
                            </button>
                        </div>
                        <div class="px-3 mb-1">
                            <span class="text-[10px] uppercase font-bold tracking-wider" style="color: var(--color-text-tertiary);">Asignar agente IA</span>
                        </div>
                        <?php if (empty($aiAgents)): ?>
                        <div class="px-3 py-2 text-xs" style="color: var(--color-text-tertiary);">
                            No hay agentes IA. <a href="<?= url('/settings/ai') ?>" class="underline" style="color: var(--color-primary);">Crear uno</a>.
                        </div>
                        <?php else: ?>
                        <div class="max-h-56 overflow-y-auto">
                            <button type="button" onclick="assignAi(null)" class="dropdown-item w-full <?= empty($active['ai_agent_id']) ? 'font-semibold' : '' ?>">
                                <span>— Sin agente IA —</span>
                            </button>
                            <?php foreach ($aiAgents as $aa): ?>
                            <button type="button" onclick="assignAi(<?= (int) $aa['id'] ?>)" class="dropdown-item w-full <?= (int) ($active['ai_agent_id'] ?? 0) === (int) $aa['id'] ? 'font-semibold' : '' ?>">
                                <span class="w-6 h-6 rounded-lg flex items-center justify-center text-xs flex-shrink-0" style="background: linear-gradient(135deg,#7C3AED,#06B6D4); color:white;">🤖</span>
                                <div class="flex-1 min-w-0">
                                    <div class="text-sm truncate"><?= e($aa['name']) ?></div>
                                    <div class="text-[10px] truncate" style="color: var(--color-text-tertiary);"><?= e((string) ($aa['role'] ?? 'Sin rol definido')) ?></div>
                                </div>
                                <?php if (!empty($aa['is_default'])): ?><span class="badge badge-cyan text-[9px]">Principal</span><?php endif; ?>
                            </button>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </details>

                <button onclick="aiAction('summarize')" title="Resumir conversación" class="btn btn-ghost btn-icon">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                </button>
                <button onclick="aiAction('suggest')" class="btn btn-secondary btn-sm">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    <span class="hidden lg:inline">Sugerir IA</span>
                </button>
                <div class="w-px h-6 mx-1" style="background: var(--color-border-subtle);"></div>
                <button onclick="toggleSearch()" class="btn btn-ghost btn-icon" title="Buscar en la conversación (⌘K)">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </button>
                <button onclick="toggleFullChat()" class="btn btn-ghost btn-icon hidden lg:inline-flex" title="Pantalla completa (F)" id="fullChatBtn">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"/></svg>
                </button>
                <button onclick="toggleStar(<?= $activeId ?>)" class="btn btn-ghost btn-icon <?= !empty($active['is_starred']) ? 'text-amber-400' : '' ?>" title="Importante">
                    <svg class="w-4 h-4" fill="<?= !empty($active['is_starred']) ? 'currentColor' : 'none' ?>" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/></svg>
                </button>
                <details class="relative">
                    <summary class="btn btn-ghost btn-icon cursor-pointer list-none">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01"/></svg>
                    </summary>
                    <div class="absolute right-0 top-full mt-1 w-60 rounded-xl shadow-xl border py-1 z-30" style="background: var(--color-bg-elevated); border-color: var(--color-border-default);">
                        <form action="<?= url('/inbox/' . $activeId . '/assign') ?>" method="POST" class="px-3 py-2">
                            <?= csrf_field() ?>
                            <div class="label">Asignar a</div>
                            <select name="user_id" onchange="this.form.submit()" class="input text-xs py-1.5">
                                <option value="">Sin asignar</option>
                                <?php foreach ($agents as $a): ?>
                                <option value="<?= (int) $a['id'] ?>" <?= ((int) $active['assigned_to']) === (int) $a['id'] ? 'selected' : '' ?>><?= e($a['first_name'] . ' ' . $a['last_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                        <?php if ($active['status'] !== 'closed'): ?>
                        <form action="<?= url('/inbox/' . $activeId . '/close') ?>" method="POST">
                            <?= csrf_field() ?>
                            <button type="submit" class="dropdown-item w-full">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                Cerrar conversación
                            </button>
                        </form>
                        <?php else: ?>
                        <form action="<?= url('/inbox/' . $activeId . '/reopen') ?>" method="POST">
                            <?= csrf_field() ?>
                            <button type="submit" class="dropdown-item w-full">Reabrir</button>
                        </form>
                        <?php endif; ?>
                        <a href="<?= url('/contacts/' . $active['contact_id']) ?>" class="dropdown-item">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                            Ver perfil completo
                        </a>
                    </div>
                </details>
            </div>
        </div>

        <!-- ===== Search bar (toggle) ===== -->
        <div id="chatSearchBar" class="hidden px-4 py-2 border-b flex items-center gap-2" style="border-color: var(--color-border-subtle); background: var(--color-bg-elevated);">
            <svg class="w-4 h-4 flex-shrink-0" style="color: var(--color-text-tertiary);" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            <input id="chatSearchInput" type="text" placeholder="Buscar en esta conversación..." class="flex-1 bg-transparent outline-none text-sm" style="color: var(--color-text-primary);">
            <span id="chatSearchCount" class="text-[10px] font-mono" style="color: var(--color-text-tertiary);"></span>
            <button onclick="toggleSearch()" class="btn btn-ghost btn-icon" title="Cerrar">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <!-- ===== Labels / chips de la conversación ===== -->
        <?php
        $convLabels = [];
        if (!empty($active['ai_sentiment']) && $active['ai_sentiment'] === 'negative') {
            $convLabels[] = ['🔥 Atención requerida', '#F87171', 'rgba(244,63,94,.12)'];
        }
        if ((int) ($active['score'] ?? 0) >= 70) {
            $convLabels[] = ['⭐ Hot lead', '#FBBF24', 'rgba(251,191,36,.12)'];
        }
        if (!empty($active['lifecycle_stage']) && in_array($active['lifecycle_stage'], ['customer', 'vip'], true)) {
            $convLabels[] = ['💎 ' . ucfirst($active['lifecycle_stage']), '#A78BFA', 'rgba(124,58,237,.12)'];
        }
        if ($msgCountIn > 20) {
            $convLabels[] = ['💬 Conversación larga', '#06B6D4', 'rgba(6,182,212,.12)'];
        }
        if (!empty($conversationFiles)) {
            $convLabels[] = ['📎 ' . count($conversationFiles) . ' adjunto' . (count($conversationFiles) === 1 ? '' : 's'), '#94A3B8', 'rgba(148,163,184,.10)'];
        }
        if (empty($convLabels)) {
            $convLabels[] = ['✨ Conversación nueva', '#34D399', 'rgba(16,185,129,.10)'];
        }
        ?>
        <div class="px-4 py-2 border-b flex items-center gap-1.5 overflow-x-auto scrollbar-thin" style="border-color: var(--color-border-subtle);">
            <span class="text-[9px] uppercase font-bold tracking-wider flex-shrink-0" style="color: var(--color-text-tertiary);">Labels</span>
            <?php foreach ($convLabels as [$txt, $col, $bg]): ?>
            <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full whitespace-nowrap" style="background: <?= $bg ?>; color: <?= $col ?>;"><?= e($txt) ?></span>
            <?php endforeach; ?>
            <button class="text-[10px] font-semibold px-2 py-0.5 rounded-full whitespace-nowrap flex-shrink-0" style="background: var(--color-bg-subtle); color: var(--color-text-tertiary);" onclick="addCustomTag()" title="Añadir etiqueta">+ Etiqueta</button>
            <button class="text-[10px] font-semibold px-2 py-0.5 rounded-full whitespace-nowrap ml-auto flex-shrink-0" style="background: var(--color-bg-subtle); color: var(--color-text-tertiary);" onclick="showShortcuts()" title="Ver atajos (?)">⌨ ?</button>
        </div>

        <!-- ===== Barra de IA avanzada ===== -->
        <?php if (!empty($active['ai_agent_id']) || !empty($active['ai_takeover'])): ?>
        <div id="aiBanner" class="mx-4 mt-3 mb-1 px-3 py-2 rounded-xl border flex items-center justify-between flex-wrap gap-2 text-xs" style="background: linear-gradient(135deg, rgba(124,58,237,.10), rgba(6,182,212,.10)); border-color: rgba(124,58,237,.35); color: #C4B5FD;">
            <div class="flex items-center gap-2">
                <span class="relative flex h-2 w-2">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full opacity-60" style="background:#A78BFA;"></span>
                    <span class="relative inline-flex rounded-full h-2 w-2" style="background:#A78BFA;"></span>
                </span>
                <span><strong><?= !empty($active['ai_takeover']) ? 'Auto-pilot activo' : 'Agente IA asignado' ?>:</strong> <?= e($currentAi['name'] ?? 'agente principal') ?></span>
            </div>
            <div class="flex items-center gap-1.5">
                <button onclick="aiAction('next')" class="px-2 py-1 rounded-md glass">Próxima acción</button>
                <button onclick="aiAction('score')" class="px-2 py-1 rounded-md glass">Calificar lead</button>
                <button onclick="aiAction('sentiment')" class="px-2 py-1 rounded-md glass">Sentimiento</button>
                <?php if (!empty($active['ai_takeover'])): ?>
                <button onclick="aiTakeoverOff()" class="px-2 py-1 rounded-md" style="background: rgba(244,63,94,.15); color:#FB7185;">Desactivar auto-pilot</button>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- ===== Drag-and-drop overlay ===== -->
        <div id="dropOverlay" class="hidden absolute inset-0 z-30 flex items-center justify-center pointer-events-none" style="background: rgba(124,58,237,.08); backdrop-filter: blur(8px); border: 3px dashed rgba(124,58,237,.6); margin: 60px;">
            <div class="text-center pointer-events-none">
                <div class="text-5xl mb-3">📎</div>
                <div class="text-lg font-bold" style="color: var(--color-primary);">Suelta aquí para adjuntar</div>
                <div class="text-xs mt-1" style="color: var(--color-text-tertiary);">Imágenes, documentos, audio, video</div>
            </div>
        </div>

        <!-- ===== Mensajes ===== -->
        <div id="msgs" class="flex-1 overflow-y-auto p-5 space-y-2" style="background: var(--color-bg-base); background-image: radial-gradient(circle, var(--color-border-subtle) 1px, transparent 1px); background-size: 24px 24px;">
            <?php
            $currentDate = '';
            foreach ($messages as $m):
                $msgDate = date('Y-m-d', strtotime((string) $m['created_at']));
                if ($msgDate !== $currentDate):
                    $currentDate = $msgDate;
                    $todayLabel = date('Y-m-d') === $msgDate ? 'Hoy' : (date('Y-m-d', strtotime('-1 day')) === $msgDate ? 'Ayer' : date('d M Y', strtotime($msgDate)));
            ?>
            <div class="flex justify-center my-4">
                <span class="px-3 py-1 rounded-full text-[10px] font-semibold uppercase tracking-[0.1em]" style="background: var(--color-bg-elevated); color: var(--color-text-tertiary); border: 1px solid var(--color-border-subtle);"><?= $todayLabel ?></span>
            </div>
            <?php endif;
                $isOut    = $m['direction'] === 'outbound';
                $internal = !empty($m['is_internal']);
                $aiGen    = !empty($m['is_ai_generated']);
                $body     = (string) $m['content'];
                $isImg    = !empty($m['media_url']) && preg_match('~\.(jpe?g|png|gif|webp)(\?|$)~i', (string) $m['media_url']);
                $isAud    = !empty($m['media_url']) && preg_match('~\.(mp3|ogg|wav|m4a|opus)(\?|$)~i', (string) $m['media_url']);
                $isVid    = !empty($m['media_url']) && preg_match('~\.(mp4|webm|mov)(\?|$)~i', (string) $m['media_url']);
            ?>
            <div class="msg-row flex <?= $isOut ? 'justify-end' : 'justify-start' ?> animate-fade-in group" data-search="<?= e(strtolower($body)) ?>">
                <div class="max-w-[78%] relative">
                    <?php if ($internal): ?>
                    <div class="px-4 py-3 rounded-2xl rounded-bl-sm border" style="background: rgba(245,158,11,.08); border-color: rgba(245,158,11,.25); color: #FBBF24;">
                        <div class="text-[10px] uppercase font-semibold tracking-wider mb-1.5 flex items-center gap-1">
                            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path d="M5 3a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2V5a2 2 0 00-2-2H5z"/></svg>
                            Nota interna
                        </div>
                        <div class="text-sm whitespace-pre-line"><?= $linkify($body) ?></div>
                    </div>
                    <?php else: ?>
                    <div class="msg-bubble px-4 py-2.5 <?= $isOut ? 'rounded-2xl rounded-br-sm text-white' : 'rounded-2xl rounded-bl-sm' ?>"
                         style="<?= $isOut ? 'background: var(--gradient-primary); box-shadow: 0 2px 8px rgba(124,58,237,.25);' : 'background: var(--color-bg-elevated); border: 1px solid var(--color-border-subtle); color: var(--color-text-primary); box-shadow: var(--shadow-xs);' ?>">
                        <?php if ($aiGen): ?>
                        <div class="text-[10px] opacity-80 mb-1 flex items-center gap-1">
                            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path d="M11.3 1.046A1 1 0 0112 2v5h4a1 1 0 01.82 1.573l-7 10A1 1 0 018 18v-5H4a1 1 0 01-.82-1.573l7-10a1 1 0 011.12-.38z"/></svg>
                            Generado por IA
                        </div>
                        <?php endif; ?>
                        <?php if ($isImg): ?>
                        <a href="<?= e((string) $m['media_url']) ?>" target="_blank" class="block mb-2 -mx-1 -mt-1">
                            <img src="<?= e((string) $m['media_url']) ?>" alt="" class="rounded-xl max-h-64 w-auto" style="max-width:100%;" loading="lazy">
                        </a>
                        <?php elseif ($isAud): ?>
                        <audio controls class="mb-2 w-full" style="max-width:280px;">
                            <source src="<?= e((string) $m['media_url']) ?>">
                        </audio>
                        <?php elseif ($isVid): ?>
                        <video controls class="mb-2 rounded-xl" style="max-height:240px; max-width:100%;">
                            <source src="<?= e((string) $m['media_url']) ?>">
                        </video>
                        <?php elseif (!empty($m['media_url'])): ?>
                        <div class="mb-2 text-xs opacity-80 flex items-center gap-1">
                            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8 4a3 3 0 00-3 3v4a5 5 0 0010 0V7a1 1 0 112 0v4a7 7 0 11-14 0V7a5 5 0 0110 0v4a3 3 0 11-6 0V7a1 1 0 012 0v4a1 1 0 102 0V7a3 3 0 00-3-3z" clip-rule="evenodd"/></svg>
                            <a href="<?= e($m['media_url']) ?>" target="_blank" class="underline">Adjunto</a>
                        </div>
                        <?php endif; ?>
                        <div class="text-sm leading-relaxed whitespace-pre-line"><?= $linkify($body) ?></div>
                    </div>
                    <!-- Hover toolbar -->
                    <div class="msg-toolbar absolute top-1 <?= $isOut ? 'left-0 -translate-x-full -ml-1' : 'right-0 translate-x-full mr-1' ?> opacity-0 group-hover:opacity-100 transition-opacity flex items-center gap-0.5 rounded-lg p-0.5 shadow-lg" style="background: var(--color-bg-elevated); border: 1px solid var(--color-border-subtle);">
                        <button class="p-1 rounded hover:bg-white/5" title="Copiar" onclick="copyMsg(this)" data-text="<?= e($body) ?>">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" style="color: var(--color-text-secondary);"><path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                        </button>
                        <button class="p-1 rounded hover:bg-white/5" title="Citar" onclick="quoteMsg(this)" data-text="<?= e($body) ?>">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" style="color: var(--color-text-secondary);"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/></svg>
                        </button>
                    </div>
                    <?php endif; ?>
                    <div class="text-[10px] mt-1 flex items-center gap-1 <?= $isOut ? 'justify-end' : '' ?>" style="color: var(--color-text-muted);">
                        <?php if (!empty($m['first_name'])): ?><span class="font-medium"><?= e($m['first_name']) ?></span><span>·</span><?php endif; ?>
                        <span class="font-mono"><?= date('H:i', strtotime((string) $m['created_at'])) ?></span>
                        <?php if ($isOut): ?>
                        <?php $statusIcon = match($m['status']) {
                            'sent'      => '<svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>',
                            'delivered' => '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7M9 17l4-4"/></svg>',
                            'read'      => '<svg class="w-3.5 h-3.5" fill="none" stroke="#06B6D4" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7M9 17l4-4"/></svg>',
                            default     => '',
                        }; ?>
                        <?= $statusIcon ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>

            <!-- Smart reply chips (sugerencias contextuales) -->
            <?php
            // Mostrar smart replies si el último mensaje es entrante (cliente espera respuesta)
            $showSmart = false;
            if (!empty($messages)) {
                $last = end($messages);
                $showSmart = ($last['direction'] === 'inbound' && empty($last['is_internal']));
            }
            $smartChips = [
                ['👋', 'Saludo', 'Hola! Gracias por escribirnos. ¿En qué te puedo ayudar?'],
                ['💬', 'Información', 'Claro, con gusto te doy más detalles. ¿Qué te gustaría saber exactamente?'],
                ['📋', 'Catálogo', 'Te comparto nuestro catálogo. Avísame si algo te interesa.'],
                ['⏱', 'Espera', 'Perfecto, dame un momento que valido la información y te respondo.'],
                ['✅', 'Confirmar', '¡Listo! Ya quedó procesado. ¿Algo más en lo que te pueda ayudar?'],
                ['🤖', 'Sugerir IA', '__AI_SUGGEST__'],
            ];
            ?>
            <?php if ($showSmart): ?>
            <div id="smartReplies" class="flex flex-wrap gap-1.5 pt-2 pb-1 px-1">
                <span class="text-[10px] uppercase font-bold tracking-wider w-full" style="color: var(--color-text-tertiary);">💡 Respuestas sugeridas</span>
                <?php foreach ($smartChips as [$emoji, $label, $body]): ?>
                <button type="button" class="smart-chip" onclick="<?= $body === '__AI_SUGGEST__' ? 'aiAction(\'suggest\')' : 'fillComposer(' . htmlspecialchars(json_encode($body), ENT_QUOTES) . ')' ?>">
                    <span><?= $emoji ?></span><span><?= e($label) ?></span>
                </button>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Indicador "está escribiendo" (placeholder) -->
            <div id="typingIndicator" class="hidden flex justify-start animate-fade-in">
                <div class="px-4 py-3 rounded-2xl rounded-bl-sm flex items-center gap-1.5" style="background: var(--color-bg-elevated); border: 1px solid var(--color-border-subtle);">
                    <span class="typing-dot"></span><span class="typing-dot"></span><span class="typing-dot"></span>
                </div>
            </div>
        </div>

        <!-- Botón flotante "Ir abajo" -->
        <button id="scrollDownBtn" onclick="scrollMsgsToBottom(true)" class="hidden absolute right-5 z-20 rounded-full w-10 h-10 flex items-center justify-center shadow-xl" style="bottom: 180px; background: var(--color-bg-elevated); border:1px solid var(--color-border-default); color: var(--color-text-secondary);" title="Ir al final">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 14l-7 7m0 0l-7-7m7 7V3"/></svg>
            <span id="scrollDownBadge" class="hidden absolute -top-1 -right-1 min-w-[16px] h-4 px-1 rounded-full text-[9px] font-bold flex items-center justify-center text-white" style="background: var(--gradient-primary);">0</span>
        </button>

        <!-- AI Panel -->
        <div id="aiPanel" class="hidden mx-4 mb-2 p-3 rounded-xl border relative overflow-hidden" style="background: linear-gradient(135deg, rgba(124,58,237,.08), rgba(6,182,212,.08)); border-color: rgba(124,58,237,.3);">
            <div class="absolute -top-10 -right-10 w-32 h-32 rounded-full opacity-30" style="background: radial-gradient(circle, rgba(124,58,237,0.5), transparent 70%); filter: blur(20px);"></div>
            <div class="flex items-start gap-3 relative">
                <div class="w-9 h-9 rounded-xl flex items-center justify-center flex-shrink-0" style="background: var(--gradient-primary);">
                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                </div>
                <div class="flex-1 text-sm" id="aiPanelBody" style="color: var(--color-text-primary);"></div>
                <button onclick="document.getElementById('aiPanel').classList.add('hidden')" class="opacity-50 hover:opacity-100 p-1" style="color: var(--color-text-tertiary);">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
        </div>

        <?php if ($active['status'] !== 'closed'): ?>
        <!-- ===== Composer ===== -->
        <div class="border-t" style="border-color: var(--color-border-subtle); background: var(--color-bg-surface);" x-data="{ note: false, qrOpen: false, emojiOpen: false, attachOpen: false }">

            <!-- Reply preview (cita interna) -->
            <div id="replyPreview" class="hidden px-4 pt-2">
                <div class="flex items-start gap-2 px-3 py-2 rounded-lg border-l-2" style="background: var(--color-bg-subtle); border-left-color: var(--color-primary);">
                    <svg class="w-3.5 h-3.5 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" style="color: var(--color-primary);"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/></svg>
                    <div class="flex-1 min-w-0">
                        <div class="text-[10px] uppercase font-semibold tracking-wider mb-0.5" style="color: var(--color-primary);">Citando</div>
                        <div id="replyPreviewText" class="text-xs truncate" style="color: var(--color-text-secondary);"></div>
                    </div>
                    <button onclick="cancelReply()" class="opacity-60 hover:opacity-100 flex-shrink-0">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
            </div>

            <!-- Attachment preview -->
            <div id="attachPreview" class="hidden px-4 pt-2">
                <div class="flex items-center gap-2 px-3 py-2 rounded-lg" style="background: var(--color-bg-subtle); border: 1px solid var(--color-border-subtle);">
                    <span class="text-base">📎</span>
                    <div class="flex-1 min-w-0">
                        <div id="attachName" class="text-xs font-semibold truncate" style="color: var(--color-text-primary);"></div>
                        <div id="attachSize" class="text-[10px]" style="color: var(--color-text-tertiary);"></div>
                    </div>
                    <button onclick="cancelAttach()" class="opacity-60 hover:opacity-100">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <p class="text-[10px] mt-1" style="color: var(--color-text-tertiary);">⚠ El envío de archivos requiere configurar el bucket de medios. <a href="<?= url('/settings/integrations') ?>" class="underline" style="color: var(--color-primary);">Ver integraciones</a></p>
            </div>

            <div class="p-3">
                <?php if (!empty($quickReplies)): ?>
                <div class="relative mb-2 inline-block">
                    <button @click="qrOpen = !qrOpen" type="button" class="text-xs flex items-center gap-1.5 hover:underline font-medium" style="color: var(--color-primary);">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                        Respuestas rápidas
                    </button>
                    <div x-show="qrOpen" @click.outside="qrOpen = false" x-transition x-cloak class="absolute bottom-full left-0 mb-2 w-72 rounded-xl shadow-xl border py-1 z-30 max-h-64 overflow-y-auto" style="background: var(--color-bg-elevated); border-color: var(--color-border-default);">
                        <?php foreach ($quickReplies as $qr): ?>
                        <button type="button" onclick="document.getElementById('msgInput').value = <?= htmlspecialchars(json_encode($qr['body']), ENT_QUOTES) ?>; document.getElementById('msgInput').focus(); updateCharCount(); this.closest('[x-data]').__x.$data.qrOpen = false;" class="dropdown-item w-full">
                            <span class="kbd"><?= e($qr['shortcut']) ?></span>
                            <span class="flex-1 truncate"><?= e((string) ($qr['title'] ?? '')) ?></span>
                        </button>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <form id="composer" onsubmit="event.preventDefault(); sendMessage();">
                    <?php if (!empty($channels) && count($channels) > 1 && !$active['channel_id']): ?>
                    <div class="flex items-center gap-2 mb-2">
                        <span class="text-[10px] font-semibold uppercase tracking-wider" style="color: var(--color-text-tertiary);">Enviar desde:</span>
                        <select id="channelSelect" class="text-xs px-2 py-1 rounded-lg border" style="background: var(--color-bg-subtle); border-color: var(--color-border-subtle); color: var(--color-text-primary);">
                            <?php foreach ($channels as $ch): if ($ch['status'] !== 'active') continue; ?>
                            <option value="<?= (int) $ch['id'] ?>" <?= !empty($ch['is_default']) ? 'selected' : '' ?>>
                                <?= e($ch['label']) ?> · <?= e($ch['phone']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php elseif ($active['channel_id'] && !empty($active['channel_label'])): ?>
                    <div class="text-[11px] mb-2 flex items-center gap-1.5" style="color: var(--color-text-tertiary);">
                        <span style="color: var(--color-text-tertiary);">Enviando desde:</span>
                        <span class="font-semibold flex items-center gap-1" style="color: <?= e((string) ($active['channel_color'] ?? '#7C3AED')) ?>;">
                            <span class="w-1.5 h-1.5 rounded-full" style="background: <?= e((string) ($active['channel_color'] ?? '#7C3AED')) ?>;"></span>
                            <?= e((string) $active['channel_label']) ?>
                            <span class="font-mono opacity-70">(<?= e((string) ($active['channel_phone'] ?? '')) ?>)</span>
                        </span>
                    </div>
                    <input type="hidden" id="channelSelect" value="<?= (int) $active['channel_id'] ?>">
                    <?php endif; ?>

                    <!-- ============================================ -->
                    <!-- AI ACTION BAR — superpoderes IA del composer -->
                    <!-- ============================================ -->
                    <div class="ai-bar mb-2">
                        <div class="ai-bar-glow"></div>
                        <div class="ai-bar-inner">
                            <span class="ai-bar-label">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                                IA
                            </span>
                            <button type="button" onclick="aiTransform('suggest')" class="ai-pill" title="Sugerir respuesta basada en la conversación">
                                <span>⚡</span><span>Sugerir</span>
                            </button>
                            <button type="button" onclick="aiTransform('improve')" class="ai-pill" title="Mejorar redacción">
                                <span>✨</span><span>Mejorar</span>
                            </button>
                            <button type="button" onclick="aiTransform('formal')" class="ai-pill" title="Tono más formal">
                                <span>💼</span><span>Formal</span>
                            </button>
                            <button type="button" onclick="aiTransform('casual')" class="ai-pill" title="Tono más amigable">
                                <span>😊</span><span>Amigable</span>
                            </button>
                            <button type="button" onclick="aiTransform('shorter')" class="ai-pill" title="Acortar">
                                <span>✂</span><span>Acortar</span>
                            </button>
                            <button type="button" onclick="aiTransform('longer')" class="ai-pill" title="Expandir con detalles">
                                <span>📏</span><span>Expandir</span>
                            </button>
                            <button type="button" onclick="aiTransform('fix')" class="ai-pill" title="Corregir ortografía y gramática">
                                <span>📝</span><span>Corregir</span>
                            </button>
                            <button type="button" onclick="aiTransform('translate')" class="ai-pill" title="Traducir al idioma del cliente">
                                <span>🌐</span><span>Traducir</span>
                            </button>
                            <button type="button" onclick="aiTransform('emojify')" class="ai-pill" title="Añadir emojis apropiados">
                                <span>🎨</span><span>Emojis</span>
                            </button>
                            <button type="button" onclick="aiTransform('continue')" class="ai-pill" title="Continuar escribiendo">
                                <span>➡</span><span>Continuar</span>
                            </button>
                        </div>
                    </div>

                    <!-- Barra de formato (Markdown WhatsApp) compacta -->
                    <div class="format-row flex items-center gap-0.5 mb-1.5 flex-wrap">
                        <button type="button" onclick="wrapSel('*','*')" class="format-btn" title="Negrita (Ctrl+B)"><b>B</b></button>
                        <button type="button" onclick="wrapSel('_','_')" class="format-btn" title="Cursiva (Ctrl+I)"><i>I</i></button>
                        <button type="button" onclick="wrapSel('~','~')" class="format-btn" title="Tachado"><s>S</s></button>
                        <button type="button" onclick="wrapSel('\x60\x60\x60\n','\n\x60\x60\x60')" class="format-btn" title="Bloque de código">{ }</button>
                        <button type="button" onclick="prefixLines('• ')" class="format-btn" title="Lista">•</button>
                        <button type="button" onclick="prefixLines('> ')" class="format-btn" title="Cita">›</button>
                        <span class="format-divider"></span>
                        <button type="button" onclick="insertVariable()" class="format-btn" title="Insertar variable">{{}}</button>
                        <button type="button" onclick="aiAction('summarize')" class="format-btn" title="Resumir conversación">📋</button>
                        <span class="ml-auto flex items-center gap-1.5">
                            <span id="composerToneTag" class="hidden tone-tag"></span>
                        </span>
                    </div>

                    <!-- ====== Composer Shell PREMIUM ====== -->
                    <div class="composer-shell-premium" id="composerShell">
                        <div class="composer-glow"></div>

                        <!-- Toolbar izquierda con acciones flotantes -->
                        <div class="composer-tools-left">
                            <!-- Adjuntar -->
                            <div class="relative">
                                <button @click.prevent="attachOpen = !attachOpen; emojiOpen = false" type="button" class="composer-icon-btn-pro" title="Adjuntar">
                                    <svg class="w-[18px] h-[18px]" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                                </button>
                                <div x-show="attachOpen" @click.outside="attachOpen = false" x-transition x-cloak class="absolute bottom-full left-0 mb-3 w-48 rounded-xl shadow-2xl border py-1 z-30" style="background: var(--color-bg-elevated); border-color: var(--color-border-default);">
                                    <button type="button" onclick="document.getElementById('fileInputMedia').click(); this.closest('[x-data]').__x.$data.attachOpen = false;" class="dropdown-item w-full">
                                        <span class="text-base">🖼</span><span>Imagen</span>
                                    </button>
                                    <button type="button" onclick="document.getElementById('fileInputDoc').click(); this.closest('[x-data]').__x.$data.attachOpen = false;" class="dropdown-item w-full">
                                        <span class="text-base">📄</span><span>Documento</span>
                                    </button>
                                    <button type="button" onclick="alert('Compartir ubicación: configura tu integración de mapas en Ajustes.')" class="dropdown-item w-full">
                                        <span class="text-base">📍</span><span>Ubicación</span>
                                    </button>
                                    <button type="button" onclick="alert('Compartir contacto: próximamente.')" class="dropdown-item w-full">
                                        <span class="text-base">👤</span><span>Contacto</span>
                                    </button>
                                </div>
                            </div>
                            <input type="file" id="fileInputMedia" class="hidden" accept="image/*" onchange="onFilePicked(event)">
                            <input type="file" id="fileInputDoc" class="hidden" accept=".pdf,.doc,.docx,.xls,.xlsx,.csv,.txt" onchange="onFilePicked(event)">

                            <!-- Emoji -->
                            <div class="relative">
                                <button @click.prevent="emojiOpen = !emojiOpen; attachOpen = false" type="button" class="composer-icon-btn-pro" title="Emoji">
                                    <svg class="w-[18px] h-[18px]" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                </button>
                                <div x-show="emojiOpen" @click.outside="emojiOpen = false" x-transition x-cloak class="absolute bottom-full left-0 mb-3 w-72 rounded-xl shadow-2xl border p-2 z-30" style="background: var(--color-bg-elevated); border-color: var(--color-border-default);">
                                    <div class="grid grid-cols-8 gap-1 max-h-56 overflow-y-auto">
                                        <?php
                                        $emojis = ['😀','😃','😄','😁','😆','😅','🤣','😂','🙂','🙃','😉','😊','😇','🥰','😍','🤩','😘','😗','😚','😙','🥲','😋','😛','😜','🤪','😝','🤑','🤗','🤭','🤫','🤔','🤐','😐','😑','😶','😏','😒','🙄','😬','🤥','😌','😔','😪','🤤','😴','😷','🤒','🤕','🥵','🥶','🥴','😵','🤯','🤠','🥳','😎','🤓','🧐','😕','🙁','☹','😮','😯','😲','😳','🥺','😦','😧','😨','😰','😥','😢','😭','😱','😖','😣','😞','😓','😩','😫','🥱','😤','😡','😠','🤬','😈','👿','💀','💩','🤡','👹','👺','👻','👽','🤖','😺','😸','😹','😻','😼','😽','🙀','😿','😾','👍','👎','👌','✌','🤞','🤟','🤘','🤙','👈','👉','👆','👇','✋','🖐','🖖','👋','🤚','🤛','🤜','✊','👊','🙌','🙏','💪','🦾','✍','💯','💥','🔥','⭐','🌟','✨','⚡','☀','🌈','💧','🎉','🎊','🎁','🎂','🍕','🍔','🍟','🌮','🍣','🍜','🥗','🍰','🍫','🍦','☕','🍺','🍷','🍾','💰','💵','💳','📞','📱','💻','⌨','🖥','📷','📹','🎮','🎵','🎶','📺','📻','📚','📝','✏','📌','📍','🔑','🔒','🔓','💡','🔔','📅','⏰','⌚','✅','❌','⚠','🚀','🌍','🌎','🚗','🚕','🛵','🛒','🏠','🏢','🛏','🚿','🚽','🛁'];
                                        foreach ($emojis as $em): ?>
                                        <button type="button" onclick="insertEmoji('<?= $em ?>')" class="text-xl rounded hover:bg-white/5 p-1 leading-none"><?= $em ?></button>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Voz -->
                            <button type="button" id="voiceBtn" onclick="toggleVoice()" class="composer-icon-btn-pro" title="Mensaje de voz">
                                <svg class="w-[18px] h-[18px]" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 11a7 7 0 01-14 0m7 7v4m-4 0h8m-4-4a3 3 0 003-3V5a3 3 0 00-6 0v10a3 3 0 003 3z"/></svg>
                            </button>
                        </div>

                        <!-- Textarea + ghost -->
                        <div class="composer-textarea-wrap">
                            <textarea id="msgInput" rows="1" placeholder="Escribe un mensaje, o pulsa ✨ Mejorar para que la IA lo redacte..." oninput="autoGrow(this); updateCharCount(); detectTone();"></textarea>
                            <div id="aiOverlay" class="ai-overlay hidden">
                                <div class="ai-overlay-shimmer"></div>
                                <div class="ai-overlay-text">
                                    <span class="dot-pulse"></span>
                                    <span id="aiOverlayLabel">La IA está procesando...</span>
                                </div>
                            </div>
                        </div>

                        <!-- Toolbar derecha (Send group) -->
                        <div class="composer-tools-right">
                            <button type="button" onclick="alert('Programar envío: próximamente.')" class="composer-icon-btn-pro" title="Programar envío">
                                <svg class="w-[18px] h-[18px]" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            </button>
                            <button type="submit" class="composer-send-pro" title="Enviar (Enter)">
                                <span class="composer-send-glow"></span>
                                <svg class="w-[18px] h-[18px] relative" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M13 6l6 6-6 6"/></svg>
                            </button>
                        </div>
                    </div>

                    <!-- ====== Toolbar inferior ====== -->
                    <div class="composer-footer flex items-center justify-between mt-2 text-xs flex-wrap gap-2" style="color: var(--color-text-tertiary);">
                        <div class="flex items-center gap-3">
                            <label class="composer-switch" :class="note ? 'on' : ''">
                                <input type="checkbox" id="isInternal" x-model="note" class="hidden">
                                <span class="composer-switch-track"></span>
                                <span class="text-[11px] font-medium" :class="note ? 'text-amber-400' : ''">📒 Nota interna</span>
                            </label>
                            <span class="font-mono text-[10px] opacity-70"><span id="charCount">0</span> · <span id="wordCount">0</span> palabras</span>
                        </div>
                        <div class="flex items-center gap-1.5 text-[10px]" style="color: var(--color-text-muted);">
                            <span class="kbd">↵</span><span>envía</span>
                            <span class="opacity-50">·</span>
                            <span class="kbd">Shift</span>+<span class="kbd">↵</span><span>nueva línea</span>
                            <span class="opacity-50">·</span>
                            <span class="kbd">⌃</span>+<span class="kbd">␣</span><span>IA</span>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        <?php else: ?>
        <div class="p-4 border-t text-center text-sm" style="border-color: var(--color-border-subtle); color: var(--color-text-tertiary);">
            <span class="badge badge-slate mb-2">Cerrada</span>
            <div>Esta conversación está cerrada. <form action="<?= url('/inbox/' . $activeId . '/reopen') ?>" method="POST" class="inline"><?= csrf_field() ?><button class="hover:underline font-semibold" style="color: var(--color-primary);">Reabrir conversación</button></form></div>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </section>

    <!-- ============================================================ -->
    <!-- SIDEBAR DERECHO con tabs                                     -->
    <!-- ============================================================ -->
    <?php if ($active): ?>
    <aside class="info-pane lg:col-span-4 xl:col-span-3 surface flex flex-col overflow-hidden">
        <!-- Hero -->
        <div class="p-5 text-center border-b relative overflow-hidden flex-shrink-0" style="border-color: var(--color-border-subtle);">
            <div class="absolute inset-0 opacity-30" style="background: var(--gradient-mesh);"></div>
            <button onclick="toggleInfo()" class="absolute top-2 right-2 z-10 p-1.5 rounded-lg opacity-60 hover:opacity-100 transition" style="color: var(--color-text-tertiary); background: var(--color-bg-subtle);" title="Colapsar panel">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
            </button>
            <div class="relative">
                <div class="avatar avatar-xl mx-auto mb-3 relative">
                    <?= e(strtoupper(mb_substr($contactName ?: 'X', 0, 1))) ?>
                    <span class="absolute -bottom-0.5 -right-0.5 status-dot status-online" style="border: 3px solid var(--color-bg-surface); width: 14px; height: 14px;"></span>
                </div>
                <h3 class="font-bold text-base" style="color: var(--color-text-primary);"><?= e($contactName ?: 'Sin nombre') ?></h3>
                <?php if (!empty($active['company'])): ?><div class="text-xs mt-0.5" style="color: var(--color-text-tertiary);"><?= e($active['company']) ?></div><?php endif; ?>
                <div class="flex justify-center gap-2 mt-3">
                    <a href="<?= url('/contacts/' . $active['contact_id']) ?>" class="btn btn-secondary btn-sm">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                        Perfil
                    </a>
                    <?php if (!empty($active['phone'])): ?>
                    <a href="https://wa.me/<?= e(preg_replace('/[^\d]/', '', (string) $active['phone'])) ?>" target="_blank" class="btn btn-secondary btn-sm" style="color: #10B981;" title="Abrir en WhatsApp">
                        <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163a11.867 11.867 0 01-1.587-5.946C.16 5.335 5.495 0 12.05 0a11.817 11.817 0 018.413 3.488 11.824 11.824 0 013.48 8.414c-.003 6.557-5.338 11.892-11.893 11.892a11.9 11.9 0 01-5.688-1.448L.057 24z"/></svg>
                    </a>
                    <a href="tel:<?= e((string) $active['phone']) ?>" class="btn btn-secondary btn-sm" title="Llamar">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                    </a>
                    <?php endif; ?>
                </div>

                <!-- Mini stats -->
                <div class="grid grid-cols-3 gap-2 mt-4 text-center">
                    <div class="rounded-lg p-2" style="background: var(--color-bg-subtle);">
                        <div class="text-base font-bold" style="color: var(--color-text-primary);"><?= $msgCountIn ?></div>
                        <div class="text-[9px] uppercase tracking-wider" style="color: var(--color-text-tertiary);">Recibidos</div>
                    </div>
                    <div class="rounded-lg p-2" style="background: var(--color-bg-subtle);">
                        <div class="text-base font-bold" style="color: var(--color-text-primary);"><?= $msgCountOut ?></div>
                        <div class="text-[9px] uppercase tracking-wider" style="color: var(--color-text-tertiary);">Enviados</div>
                    </div>
                    <div class="rounded-lg p-2" style="background: var(--color-bg-subtle);">
                        <div class="text-base font-bold" style="color: <?= ((int)($active['score'] ?? 0)) > 70 ? '#10B981' : (((int)($active['score'] ?? 0)) > 40 ? '#F59E0B' : 'var(--color-text-primary)') ?>;"><?= (int) ($active['score'] ?? 0) ?></div>
                        <div class="text-[9px] uppercase tracking-wider" style="color: var(--color-text-tertiary);">Score</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="info-tabs flex border-b flex-shrink-0 overflow-x-auto scrollbar-thin" style="border-color: var(--color-border-subtle);">
            <?php
            $tabs = [
                ['info',     'Info',      'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z'],
                ['orders',   'Pedidos',   'M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z'],
                ['files',    'Archivos',  'M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z'],
                ['notes',    'Notas',     'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z'],
                ['activity', 'Actividad', 'M13 10V3L4 14h7v7l9-11h-7z'],
            ];
            foreach ($tabs as [$key, $label, $icon]):
                $cnt = match($key) {
                    'orders'   => 0,
                    'files'    => count($conversationFiles),
                    'notes'    => count($conversationNotes),
                    'activity' => count($messages),
                    default    => 0,
                };
            ?>
            <button class="tab-btn flex items-center justify-center gap-1 px-2.5 py-2.5 text-[11px] font-semibold transition flex-shrink-0" data-tab="<?= $key ?>"
                    style="<?= $key === 'info' ? 'color: var(--color-primary); border-bottom: 2px solid var(--color-primary);' : 'color: var(--color-text-tertiary); border-bottom: 2px solid transparent;' ?>">
                <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="<?= $icon ?>"/></svg>
                <span class="whitespace-nowrap"><?= $label ?></span>
                <?php if ($cnt > 0): ?>
                <span class="text-[9px] px-1 rounded-full font-bold" style="background: var(--color-bg-subtle); color: var(--color-text-secondary);"><?= $cnt ?></span>
                <?php endif; ?>
            </button>
            <?php endforeach; ?>
        </div>

        <div class="flex-1 overflow-y-auto scrollbar-thin">
            <!-- TAB: INFO -->
            <div data-tab-content="info" class="p-4">
                <?php
                // AI Insights heurísticos derivados del último mensaje del cliente
                $lastClientMsg = '';
                foreach (array_reverse($messages) as $rm) {
                    if ($rm['direction'] === 'inbound' && empty($rm['is_internal'])) { $lastClientMsg = strtolower((string) $rm['content']); break; }
                }
                $intent = '—'; $intentColor = '#94A3B8'; $intentEmoji = '💬';
                if ($lastClientMsg) {
                    if (preg_match('/\b(comprar|precio|cuesta|orden|pedido|delivery|cuenta|factura|pagar)\b/u', $lastClientMsg)) { $intent = 'Compra'; $intentColor = '#10B981'; $intentEmoji = '💰'; }
                    elseif (preg_match('/\b(ayuda|problema|error|reclamo|queja|no funciona|no me llega)\b/u', $lastClientMsg)) { $intent = 'Soporte'; $intentColor = '#F59E0B'; $intentEmoji = '🛟'; }
                    elseif (preg_match('/\b(info|información|saber|consulta|duda|cómo|que es|que son)\b/u', $lastClientMsg)) { $intent = 'Información'; $intentColor = '#06B6D4'; $intentEmoji = 'ℹ'; }
                    elseif (preg_match('/\b(hola|buenas|saludos|buen día|buenos días)\b/u', $lastClientMsg)) { $intent = 'Saludo'; $intentColor = '#A78BFA'; $intentEmoji = '👋'; }
                }
                $urgency = 'Normal'; $urgColor = '#94A3B8';
                if (preg_match('/\b(urgente|ya|inmediato|rápido|ahora|emergencia)\b/u', $lastClientMsg)) { $urgency = 'Alta'; $urgColor = '#F87171'; }
                $lang = 'ES';
                if (preg_match('/\b(hello|please|thanks|how|what|when)\b/i', $lastClientMsg)) { $lang = 'EN'; }
                ?>
                <!-- AI Insights -->
                <div class="rounded-xl border p-3 mb-4" style="background: linear-gradient(135deg, rgba(124,58,237,.06), rgba(6,182,212,.06)); border-color: rgba(124,58,237,.25);">
                    <div class="flex items-center justify-between mb-2">
                        <div class="flex items-center gap-1.5">
                            <span class="text-base">🧠</span>
                            <span class="text-[10px] uppercase font-bold tracking-wider" style="color: var(--color-primary);">AI Insights</span>
                        </div>
                        <button onclick="aiAction('summarize')" class="text-[10px] underline opacity-70 hover:opacity-100" style="color: var(--color-primary);">Refrescar</button>
                    </div>
                    <div class="grid grid-cols-2 gap-2 text-xs">
                        <div class="rounded-lg p-2" style="background: var(--color-bg-elevated);">
                            <div class="text-[9px] uppercase font-bold tracking-wider opacity-70" style="color: <?= $intentColor ?>;">Intención</div>
                            <div class="font-bold mt-0.5" style="color: <?= $intentColor ?>;"><?= $intentEmoji ?> <?= e($intent) ?></div>
                        </div>
                        <div class="rounded-lg p-2" style="background: var(--color-bg-elevated);">
                            <div class="text-[9px] uppercase font-bold tracking-wider opacity-70" style="color: <?= $urgColor ?>;">Urgencia</div>
                            <div class="font-bold mt-0.5" style="color: <?= $urgColor ?>;"><?= e($urgency) ?></div>
                        </div>
                        <div class="rounded-lg p-2" style="background: var(--color-bg-elevated);">
                            <div class="text-[9px] uppercase font-bold tracking-wider opacity-70" style="color: var(--color-text-tertiary);">Idioma</div>
                            <div class="font-bold mt-0.5" style="color: var(--color-text-primary);">🌐 <?= $lang ?></div>
                        </div>
                        <div class="rounded-lg p-2" style="background: var(--color-bg-elevated);">
                            <div class="text-[9px] uppercase font-bold tracking-wider opacity-70" style="color: var(--color-text-tertiary);">Mensajes</div>
                            <div class="font-bold mt-0.5" style="color: var(--color-text-primary);"><?= count($messages) ?> total</div>
                        </div>
                    </div>
                </div>

                <h4 class="text-[10px] uppercase font-semibold tracking-[0.1em] mb-3" style="color: var(--color-text-tertiary);">Información</h4>
                <dl class="space-y-2.5 text-sm mb-4">
                    <?php
                    $info = [
                        ['Teléfono', $active['phone'] ?? '—', 'M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z'],
                        ['Email', $active['email'] ?? '—', 'M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z'],
                        ['Score IA', ($active['score'] ?? 0) . '/100', 'M13 10V3L4 14h7v7l9-11h-7z'],
                    ];
                    foreach ($info as [$k, $v, $icon]): ?>
                    <div class="flex items-center gap-3">
                        <div class="w-7 h-7 rounded-lg flex items-center justify-center flex-shrink-0" style="background: var(--color-bg-subtle); color: var(--color-text-tertiary);">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="<?= $icon ?>"/></svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="text-[10px] uppercase tracking-wider" style="color: var(--color-text-tertiary);"><?= $k ?></div>
                            <div class="truncate font-semibold text-sm" style="color: var(--color-text-primary);"><?= e((string) $v) ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </dl>

                <?php if (!empty($active['ai_summary'])): ?>
                <div class="rounded-xl border p-3 mb-3" style="background: linear-gradient(135deg, rgba(124,58,237,0.04), rgba(6,182,212,0.04)); border-color: rgba(124,58,237,0.2);">
                    <div class="flex items-center gap-1.5 mb-1.5">
                        <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20" style="color: var(--color-primary);"><path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9zM4 5a2 2 0 012-2 1 1 0 100 2H5v11h10V5h-1a1 1 0 100-2 2 2 0 012 2v11a2 2 0 01-2 2H5a2 2 0 01-2-2V5z"/></svg>
                        <span class="text-[10px] uppercase font-semibold tracking-wider" style="color: var(--color-primary);">Resumen IA</span>
                    </div>
                    <div class="text-xs leading-relaxed" style="color: var(--color-text-secondary);"><?= e((string) $active['ai_summary']) ?></div>
                </div>
                <?php endif; ?>

                <?php if (!empty($active['ai_next_action'])): ?>
                <div class="rounded-xl border p-3 mb-3" style="background: rgba(16,185,129,0.05); border-color: rgba(16,185,129,0.2);">
                    <div class="flex items-center gap-1.5 mb-1.5">
                        <span class="text-base">🎯</span>
                        <span class="text-[10px] uppercase font-semibold tracking-wider" style="color: #34D399;">Próxima acción sugerida</span>
                    </div>
                    <div class="text-xs leading-relaxed" style="color: var(--color-text-secondary);"><?= e((string) $active['ai_next_action']) ?></div>
                </div>
                <?php endif; ?>

                <!-- Acciones rápidas -->
                <h4 class="text-[10px] uppercase font-semibold tracking-[0.1em] mb-2 mt-4" style="color: var(--color-text-tertiary);">Acciones rápidas</h4>
                <div class="grid grid-cols-2 gap-2">
                    <a href="<?= url('/leads/create?contact_id=' . (int) $active['contact_id']) ?>" class="quick-action-btn">
                        <span class="text-base">📈</span><span>Crear lead</span>
                    </a>
                    <a href="<?= url('/tickets/create?contact_id=' . (int) $active['contact_id']) ?>" class="quick-action-btn">
                        <span class="text-base">🎫</span><span>Crear ticket</span>
                    </a>
                    <a href="<?= url('/orders/create?contact_id=' . (int) $active['contact_id']) ?>" class="quick-action-btn">
                        <span class="text-base">📦</span><span>Crear orden</span>
                    </a>
                    <a href="<?= url('/tasks/create?contact_id=' . (int) $active['contact_id']) ?>" class="quick-action-btn">
                        <span class="text-base">✅</span><span>Tarea</span>
                    </a>
                </div>
            </div>

            <!-- TAB: PEDIDOS -->
            <div data-tab-content="orders" class="p-4 hidden">
                <div id="ordersTabBody" class="space-y-2">
                    <div class="text-center py-8" style="color: var(--color-text-tertiary);">
                        <div class="text-3xl mb-2">📦</div>
                        <div class="text-xs">Cargando pedidos...</div>
                    </div>
                </div>
            </div>

            <!-- TAB: ARCHIVOS -->
            <div data-tab-content="files" class="p-4 hidden">
                <?php if (empty($conversationFiles)): ?>
                <div class="text-center py-8" style="color: var(--color-text-tertiary);">
                    <div class="text-3xl mb-2">📁</div>
                    <div class="text-xs">No hay archivos compartidos</div>
                </div>
                <?php else: ?>
                <div class="grid grid-cols-3 gap-2">
                    <?php foreach ($conversationFiles as $f):
                        $url = (string) $f['media_url'];
                        $isImg = preg_match('~\.(jpe?g|png|gif|webp)(\?|$)~i', $url);
                    ?>
                    <a href="<?= e($url) ?>" target="_blank" class="aspect-square rounded-lg overflow-hidden flex items-center justify-center" style="background: var(--color-bg-subtle); border: 1px solid var(--color-border-subtle);">
                        <?php if ($isImg): ?>
                        <img src="<?= e($url) ?>" class="w-full h-full object-cover" loading="lazy" alt="">
                        <?php else: ?>
                        <span class="text-2xl">📄</span>
                        <?php endif; ?>
                    </a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- TAB: ACTIVIDAD -->
            <div data-tab-content="activity" class="p-4 hidden">
                <?php if (empty($messages)): ?>
                <div class="text-center py-8" style="color: var(--color-text-tertiary);">
                    <div class="text-3xl mb-2">📜</div>
                    <div class="text-xs">Sin actividad</div>
                </div>
                <?php else: ?>
                <ol class="relative border-l ml-2 space-y-3" style="border-color: var(--color-border-subtle);">
                    <?php foreach (array_reverse(array_slice($messages, -25)) as $ev):
                        $isIn = $ev['direction'] === 'inbound';
                        $isInt = !empty($ev['is_internal']);
                        $isAI = !empty($ev['is_ai_generated']);
                        $color = $isInt ? '#FBBF24' : ($isAI ? '#A78BFA' : ($isIn ? '#06B6D4' : '#10B981'));
                        $icon  = $isInt ? '📝' : ($isAI ? '🤖' : ($isIn ? '⬇' : '⬆'));
                        $title = $isInt ? 'Nota interna' : ($isAI ? 'Respuesta IA' : ($isIn ? 'Recibido' : 'Enviado'));
                    ?>
                    <li class="ml-4">
                        <span class="absolute -left-[7px] flex items-center justify-center w-3.5 h-3.5 rounded-full text-[8px]" style="background: <?= $color ?>; color: white;"></span>
                        <div class="flex items-center gap-1.5 mb-0.5">
                            <span class="text-xs"><?= $icon ?></span>
                            <span class="text-[10px] uppercase font-bold tracking-wider" style="color: <?= $color ?>;"><?= $title ?></span>
                            <span class="text-[10px] font-mono ml-auto" style="color: var(--color-text-tertiary);"><?= date('d M H:i', strtotime((string) $ev['created_at'])) ?></span>
                        </div>
                        <p class="text-xs leading-snug truncate" style="color: var(--color-text-secondary);"><?= e(mb_strimwidth((string) $ev['content'], 0, 90, '…')) ?></p>
                    </li>
                    <?php endforeach; ?>
                </ol>
                <?php endif; ?>
            </div>

            <!-- TAB: NOTAS -->
            <div data-tab-content="notes" class="p-4 hidden">
                <?php if (empty($conversationNotes)): ?>
                <div class="text-center py-8" style="color: var(--color-text-tertiary);">
                    <div class="text-3xl mb-2">📝</div>
                    <div class="text-xs">No hay notas internas todavía</div>
                    <p class="text-[10px] mt-2 opacity-70">Marca "Nota interna" en el composer para añadir una.</p>
                </div>
                <?php else: ?>
                <div class="space-y-2">
                    <?php foreach (array_reverse($conversationNotes) as $n): ?>
                    <div class="rounded-lg p-3 text-xs" style="background: rgba(245,158,11,.08); border:1px solid rgba(245,158,11,.25); color:#FCD34D;">
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-[10px] uppercase font-bold tracking-wider"><?= e((string) ($n['first_name'] ?? 'Tú')) ?></span>
                            <span class="text-[10px] font-mono opacity-70"><?= date('d M H:i', strtotime((string) $n['created_at'])) ?></span>
                        </div>
                        <div class="whitespace-pre-line"><?= e((string) $n['content']) ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </aside>
    <?php endif; ?>
</div>

<!-- ====== Estilos especificos del chat ====== -->
<style>
.composer-icon-btn {
    width: 32px; height: 32px;
    display: flex; align-items: center; justify-content: center;
    border-radius: 8px; transition: background .15s ease, color .15s ease;
    color: var(--color-text-tertiary); background: transparent; border: none; cursor: pointer;
}
.composer-icon-btn:hover { background: rgba(124,58,237,.10); color: var(--color-primary); }
.composer-icon-btn.recording { background: rgba(239,68,68,.15); color: #FB7185; animation: pulseRec 1.4s ease-in-out infinite; }
@keyframes pulseRec { 0%,100% { opacity: 1; } 50% { opacity: .6; } }

.composer-send {
    width: 40px; height: 40px;
    display: flex; align-items: center; justify-content: center;
    border-radius: 12px; color: white;
    background: var(--gradient-primary);
    box-shadow: 0 4px 14px rgba(124,58,237,.3);
    transition: transform .15s ease, box-shadow .15s ease;
    border: none; cursor: pointer;
}
.composer-send:hover { transform: translateY(-1px); box-shadow: 0 6px 18px rgba(124,58,237,.4); }
.composer-send:active { transform: translateY(0); }

.composer-shell:focus-within {
    border-color: rgba(124,58,237,.5);
    box-shadow: 0 0 0 3px rgba(124,58,237,.1);
}

/* ============================================================== */
/* COMPOSER PREMIUM — AI ACTION BAR + GLASS SHELL                  */
/* ============================================================== */

/* AI Bar (superpoderes) */
.ai-bar {
    position: relative;
    border-radius: 14px;
    padding: 1px;
    background: linear-gradient(135deg, rgba(124,58,237,.4), rgba(6,182,212,.35), rgba(16,185,129,.25));
    background-size: 200% 200%;
    animation: aiGradient 8s ease infinite;
}
.ai-bar-glow {
    position: absolute; inset: 0;
    background: linear-gradient(135deg, rgba(124,58,237,.5), rgba(6,182,212,.4));
    border-radius: 14px;
    filter: blur(16px);
    opacity: .25;
    z-index: -1;
}
.ai-bar-inner {
    display: flex; align-items: center; gap: 6px;
    padding: 6px 8px;
    background: var(--color-bg-elevated);
    border-radius: 13px;
    overflow-x: auto;
    scrollbar-width: none;
}
.ai-bar-inner::-webkit-scrollbar { display: none; }
.ai-bar-label {
    display: inline-flex; align-items: center; gap: 3px;
    padding: 2px 8px;
    font-size: 9px; font-weight: 800; letter-spacing: .12em;
    text-transform: uppercase;
    background: linear-gradient(135deg, #A78BFA, #06B6D4);
    -webkit-background-clip: text; background-clip: text; -webkit-text-fill-color: transparent;
    flex-shrink: 0;
}
.ai-bar-label svg { color: #A78BFA; -webkit-text-fill-color: currentColor; }

.ai-pill {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 5px 10px;
    font-size: 11px; font-weight: 600;
    border-radius: 999px;
    background: var(--color-bg-subtle);
    border: 1px solid transparent;
    color: var(--color-text-secondary);
    cursor: pointer; flex-shrink: 0;
    transition: all .15s ease;
    white-space: nowrap;
}
.ai-pill:hover {
    background: linear-gradient(135deg, rgba(124,58,237,.15), rgba(6,182,212,.12));
    border-color: rgba(124,58,237,.3);
    color: var(--color-text-primary);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(124,58,237,.18);
}
.ai-pill:active { transform: translateY(0); }
.ai-pill.processing {
    background: linear-gradient(135deg, rgba(124,58,237,.25), rgba(6,182,212,.20));
    color: var(--color-text-primary);
    pointer-events: none;
}
.ai-pill.processing::after {
    content: '';
    width: 8px; height: 8px;
    border: 2px solid currentColor; border-top-color: transparent;
    border-radius: 50%;
    animation: spin .6s linear infinite;
    margin-left: 4px;
}
@keyframes aiGradient {
    0%,100% { background-position: 0% 50%; }
    50% { background-position: 100% 50%; }
}
@keyframes spin { to { transform: rotate(360deg); } }

/* Format toolbar refinada */
.format-row { padding: 0 4px; }
.format-divider { width: 1px; height: 14px; background: var(--color-border-subtle); margin: 0 6px; align-self: center; }

/* Tone tag (live) */
.tone-tag {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 2px 8px;
    font-size: 10px; font-weight: 700;
    border-radius: 999px;
    background: rgba(124,58,237,.10);
    color: #A78BFA;
}

/* Composer Shell PREMIUM (glass) */
.composer-shell-premium {
    position: relative;
    display: flex; align-items: flex-end; gap: 8px;
    padding: 8px;
    border-radius: 18px;
    background: linear-gradient(135deg, rgba(255,255,255,.02), rgba(255,255,255,.01));
    border: 1px solid var(--color-border-subtle);
    backdrop-filter: blur(8px);
    transition: border-color .2s ease, box-shadow .2s ease;
    box-shadow:
        0 1px 0 rgba(255,255,255,.04) inset,
        0 1px 3px rgba(0,0,0,.20);
}
.composer-shell-premium::before {
    content: '';
    position: absolute; inset: -1px;
    border-radius: 18px;
    padding: 1px;
    background: linear-gradient(135deg, rgba(124,58,237,.5), rgba(6,182,212,.5), rgba(16,185,129,.4));
    -webkit-mask: linear-gradient(#000 0 0) content-box, linear-gradient(#000 0 0);
    -webkit-mask-composite: xor; mask-composite: exclude;
    opacity: 0;
    transition: opacity .25s ease;
    pointer-events: none;
}
.composer-shell-premium:focus-within::before { opacity: 1; }
.composer-shell-premium:focus-within {
    box-shadow:
        0 0 0 4px rgba(124,58,237,.08),
        0 8px 24px rgba(124,58,237,.12);
}
.composer-glow {
    position: absolute; inset: -8px;
    background: radial-gradient(circle at 50% 100%, rgba(124,58,237,.18), transparent 70%);
    border-radius: 24px;
    z-index: -1;
    pointer-events: none;
    opacity: 0;
    transition: opacity .25s ease;
}
.composer-shell-premium:focus-within ~ .composer-glow,
.composer-shell-premium:focus-within .composer-glow { opacity: 1; }

.composer-tools-left, .composer-tools-right {
    display: flex; align-items: center; gap: 2px;
    flex-shrink: 0;
    padding-bottom: 2px;
}
.composer-icon-btn-pro {
    width: 36px; height: 36px;
    display: flex; align-items: center; justify-content: center;
    border-radius: 10px;
    color: var(--color-text-tertiary);
    background: transparent; border: none; cursor: pointer;
    transition: all .15s ease;
}
.composer-icon-btn-pro:hover {
    background: rgba(124,58,237,.10);
    color: var(--color-primary);
    transform: scale(1.05);
}
.composer-icon-btn-pro.recording {
    background: linear-gradient(135deg, rgba(239,68,68,.20), rgba(244,63,94,.15));
    color: #FB7185;
    animation: pulseRec 1.4s ease-in-out infinite;
}

.composer-textarea-wrap {
    flex: 1; min-width: 0;
    position: relative;
    display: flex;
    align-items: center;
}
.composer-textarea-wrap textarea {
    width: 100%;
    background: transparent;
    border: none; outline: none;
    resize: none;
    font-size: 14px;
    line-height: 1.55;
    color: var(--color-text-primary);
    padding: 8px 4px;
    max-height: 180px;
    font-family: inherit;
}
.composer-textarea-wrap textarea::placeholder {
    color: var(--color-text-muted);
    opacity: .8;
}

/* AI overlay (muestra cuando IA procesa el texto) */
.ai-overlay {
    position: absolute; inset: 0;
    display: flex; align-items: center; justify-content: center;
    border-radius: 12px;
    background: linear-gradient(135deg, rgba(124,58,237,.08), rgba(6,182,212,.06));
    backdrop-filter: blur(2px);
    z-index: 5;
    pointer-events: none;
}
.ai-overlay-shimmer {
    position: absolute; inset: 0;
    background: linear-gradient(110deg, transparent 30%, rgba(124,58,237,.18) 50%, transparent 70%);
    background-size: 250% 100%;
    animation: shimmer 1.5s linear infinite;
    border-radius: 12px;
}
@keyframes shimmer {
    0% { background-position: 250% 0; }
    100% { background-position: -50% 0; }
}
.ai-overlay-text {
    position: relative;
    display: flex; align-items: center; gap: 8px;
    padding: 6px 14px;
    background: var(--color-bg-elevated);
    border-radius: 999px;
    box-shadow: 0 4px 12px rgba(0,0,0,.2);
    font-size: 12px; font-weight: 600;
    color: var(--color-text-secondary);
}
.dot-pulse {
    width: 8px; height: 8px; border-radius: 50%;
    background: linear-gradient(135deg, #A78BFA, #06B6D4);
    animation: dotPulse 1.4s ease-in-out infinite;
}
@keyframes dotPulse {
    0%, 100% { opacity: .4; transform: scale(.85); }
    50% { opacity: 1; transform: scale(1.15); box-shadow: 0 0 12px rgba(124,58,237,.6); }
}

/* Send button premium */
.composer-send-pro {
    position: relative;
    width: 44px; height: 44px;
    display: flex; align-items: center; justify-content: center;
    border-radius: 14px; color: white;
    background: linear-gradient(135deg, #7C3AED 0%, #6366F1 50%, #06B6D4 100%);
    background-size: 200% 200%;
    border: none; cursor: pointer;
    transition: all .2s ease;
    box-shadow:
        0 4px 14px rgba(124,58,237,.35),
        0 1px 3px rgba(0,0,0,.2),
        inset 0 1px 0 rgba(255,255,255,.2);
    overflow: hidden;
}
.composer-send-pro:hover {
    transform: translateY(-2px) scale(1.03);
    background-position: 100% 100%;
    box-shadow:
        0 8px 22px rgba(124,58,237,.5),
        0 2px 4px rgba(0,0,0,.2),
        inset 0 1px 0 rgba(255,255,255,.25);
}
.composer-send-pro:active { transform: translateY(0); }
.composer-send-glow {
    position: absolute; inset: 0;
    background: radial-gradient(circle, rgba(255,255,255,.4), transparent 70%);
    opacity: 0;
    transition: opacity .25s ease;
}
.composer-send-pro:hover .composer-send-glow { opacity: 1; }

/* Switch nota interna */
.composer-switch {
    display: inline-flex; align-items: center; gap: 8px;
    cursor: pointer;
    padding: 4px 8px;
    border-radius: 999px;
    transition: background .15s ease;
}
.composer-switch:hover { background: var(--color-bg-subtle); }
.composer-switch-track {
    position: relative;
    width: 28px; height: 16px;
    border-radius: 999px;
    background: var(--color-bg-subtle);
    border: 1px solid var(--color-border-subtle);
    transition: background .2s ease, border-color .2s ease;
    flex-shrink: 0;
}
.composer-switch-track::after {
    content: '';
    position: absolute; top: 1px; left: 1px;
    width: 12px; height: 12px;
    border-radius: 50%;
    background: var(--color-text-tertiary);
    transition: transform .2s cubic-bezier(.5,1.5,.5,1), background .2s ease;
}
.composer-switch.on .composer-switch-track {
    background: linear-gradient(135deg, rgba(245,158,11,.3), rgba(244,63,94,.25));
    border-color: rgba(245,158,11,.5);
}
.composer-switch.on .composer-switch-track::after {
    transform: translateX(12px);
    background: #FBBF24;
}

.msg-link { text-decoration: underline; opacity: .9; word-break: break-all; }
.msg-link:hover { opacity: 1; }
.msg-order-tag {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 2px 8px; border-radius: 6px;
    background: rgba(16,185,129,.15); color: #34D399;
    font-size: 11px; font-weight: 600; text-decoration: none;
    margin: 2px 0;
}
.msg-order-tag:hover { background: rgba(16,185,129,.25); }

.pulse-online::before {
    content: '';
    position: absolute; inset: -2px;
    border-radius: 50%;
    background: #10B981;
    animation: pulseRing 2s ease-out infinite;
}
@keyframes pulseRing {
    0% { transform: scale(.8); opacity: .8; }
    80%, 100% { transform: scale(1.6); opacity: 0; }
}

.tab-btn:hover { color: var(--color-text-primary) !important; }
.tab-btn.active { color: var(--color-primary) !important; border-bottom-color: var(--color-primary) !important; }

.quick-action-btn {
    display: flex; align-items: center; gap: 8px;
    padding: 8px 10px; border-radius: 10px;
    background: var(--color-bg-subtle); border: 1px solid var(--color-border-subtle);
    color: var(--color-text-secondary); font-size: 12px; font-weight: 500;
    transition: all .15s ease;
}
.quick-action-btn:hover { background: var(--color-bg-active); color: var(--color-text-primary); border-color: rgba(124,58,237,.3); }

.msg-row.search-hit .msg-bubble {
    box-shadow: 0 0 0 2px rgba(245,158,11,.6) !important;
}
.msg-row.search-dim { opacity: .35; }

.typing-dot {
    width: 6px; height: 6px; border-radius: 50%;
    background: var(--color-text-tertiary);
    display: inline-block;
    animation: typingBlink 1.2s infinite;
}
.typing-dot:nth-child(2) { animation-delay: .2s; }
.typing-dot:nth-child(3) { animation-delay: .4s; }
@keyframes typingBlink { 0%, 60%, 100% { opacity: .3; transform: translateY(0); } 30% { opacity: 1; transform: translateY(-3px); } }

/* Smart reply chips */
.smart-chip {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 6px 12px; border-radius: 999px;
    background: var(--color-bg-elevated); border: 1px solid var(--color-border-subtle);
    color: var(--color-text-secondary); font-size: 12px; font-weight: 500;
    cursor: pointer; transition: all .15s ease;
}
.smart-chip:hover {
    background: linear-gradient(135deg, rgba(124,58,237,.10), rgba(6,182,212,.10));
    border-color: rgba(124,58,237,.4);
    color: var(--color-text-primary);
    transform: translateY(-1px);
}

/* Botones de formato Markdown */
.format-btn {
    width: 28px; height: 26px;
    display: inline-flex; align-items: center; justify-content: center;
    border-radius: 6px; font-size: 12px;
    background: transparent; border: none; cursor: pointer;
    color: var(--color-text-tertiary);
    transition: background .15s ease, color .15s ease;
}
.format-btn:hover { background: rgba(124,58,237,.10); color: var(--color-primary); }

/* ========== Layout responsivo de los 3 paneles ========== */
@media (min-width: 1024px) {
    .chat-layout {
        grid-template-columns: 320px minmax(0, 1fr) 320px !important;
        transition: grid-template-columns .25s ease;
    }
    .chat-layout > .inbox-pane { grid-column: 1 / 2 !important; }
    .chat-layout > .chat-pane  { grid-column: 2 / 3 !important; }
    .chat-layout > .info-pane  { grid-column: 3 / 4 !important; }
}
@media (min-width: 1280px) {
    .chat-layout { grid-template-columns: 360px minmax(0, 1fr) 360px !important; }
}

/* Estados de colapso (solo desktop) */
@media (min-width: 1024px) {
    body.collapse-inbox .chat-layout { grid-template-columns: 0 minmax(0, 1fr) 360px !important; }
    body.collapse-info  .chat-layout { grid-template-columns: 360px minmax(0, 1fr) 0 !important; }
    body.collapse-inbox.collapse-info .chat-layout,
    body.full-chat .chat-layout { grid-template-columns: 0 minmax(0, 1fr) 0 !important; }

    body.collapse-inbox .inbox-pane,
    body.full-chat .inbox-pane { display: none !important; }

    body.collapse-info .info-pane,
    body.full-chat .info-pane { display: none !important; }

    /* Botones flotantes para reabrir */
    body.collapse-inbox #reopenInboxBtn,
    body.full-chat #reopenInboxBtn { display: flex !important; }
    body.collapse-info  #reopenInfoBtn,
    body.full-chat #reopenInfoBtn { display: flex !important; }
}

.reopen-btn {
    position: fixed; top: 50%; transform: translateY(-50%);
    width: 28px; height: 56px;
    align-items: center; justify-content: center;
    background: var(--color-bg-elevated);
    border: 1px solid var(--color-border-default);
    box-shadow: var(--shadow-md);
    color: var(--color-text-secondary);
    z-index: 40;
    transition: background .15s ease, color .15s ease;
}
.reopen-btn:hover {
    background: var(--color-bg-active);
    color: var(--color-primary);
}
.reopen-btn-left  { left: 0;  border-left: none;  border-radius: 0 12px 12px 0; }
.reopen-btn-right { right: 0; border-right: none; border-radius: 12px 0 0 12px; }

/* Items de conversación con hover suave y rail violeta */
.convo-item { transition: background .15s ease; }
.convo-item:not(.is-active):hover { background: var(--color-bg-subtle); }

/* Subtítulo del header con separadores ::before */
.subhead { gap: 6px 8px; row-gap: 2px; }
.subhead .subhead-item:not(:first-child)::before {
    content: '·';
    margin-right: 8px;
    opacity: .5;
    color: var(--color-text-muted);
}

/* Tabs scrollables sin scrollbar visible */
.info-tabs::-webkit-scrollbar { height: 0; }
.info-tabs { scrollbar-width: none; }

/* Fullscreen chat (legado, mantenido) */
body.full-chat .grid.lg\:grid-cols-12 > aside:first-child,
body.full-chat .grid.lg\:grid-cols-12 > aside:last-child { display: none !important; }
body.full-chat .grid.lg\:grid-cols-12 > section {
    grid-column: 1 / -1 !important;
}

/* Drag-over state */
body.drag-active #dropOverlay { display: flex !important; }

/* Activity timeline */
ol.relative > li > span.absolute { top: 4px; }

/* Shortcuts modal */
.shortcuts-modal {
    position: fixed; inset: 0; z-index: 9999;
    background: rgba(0,0,0,.6); backdrop-filter: blur(8px);
    display: flex; align-items: center; justify-content: center;
    padding: 20px;
}
.shortcuts-modal.hidden { display: none; }
.shortcuts-modal-card {
    background: var(--color-bg-elevated);
    border: 1px solid var(--color-border-default);
    border-radius: 20px;
    max-width: 520px; width: 100%;
    max-height: 80vh; overflow-y: auto;
    padding: 24px;
}
.shortcut-row {
    display: flex; align-items: center; justify-content: space-between;
    padding: 8px 0; font-size: 13px;
    border-bottom: 1px solid var(--color-border-subtle);
}
.shortcut-row:last-child { border-bottom: none; }
</style>

<!-- Modal de atajos de teclado -->
<div id="shortcutsModal" class="shortcuts-modal hidden" onclick="if(event.target===this)hideShortcuts()">
    <div class="shortcuts-modal-card">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-base font-bold flex items-center gap-2" style="color: var(--color-text-primary);">⌨ Atajos de teclado</h3>
            <button onclick="hideShortcuts()" class="opacity-60 hover:opacity-100" style="color: var(--color-text-secondary);">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <div>
            <div class="shortcut-row"><span style="color: var(--color-text-secondary);">Enviar mensaje</span><span><span class="kbd">↵</span></span></div>
            <div class="shortcut-row"><span style="color: var(--color-text-secondary);">Nueva línea</span><span><span class="kbd">Shift</span> + <span class="kbd">↵</span></span></div>
            <div class="shortcut-row"><span style="color: var(--color-text-secondary);">Limpiar composer</span><span><span class="kbd">Esc</span></span></div>
            <div class="shortcut-row"><span style="color: var(--color-text-secondary);">Buscar en chat</span><span><span class="kbd">Ctrl</span> + <span class="kbd">K</span></span></div>
            <div class="shortcut-row"><span style="color: var(--color-text-secondary);">Pantalla completa</span><span><span class="kbd">F</span></span></div>
            <div class="shortcut-row"><span style="color: var(--color-text-secondary);">Negrita</span><span><span class="kbd">Ctrl</span> + <span class="kbd">B</span></span></div>
            <div class="shortcut-row"><span style="color: var(--color-text-secondary);">Cursiva</span><span><span class="kbd">Ctrl</span> + <span class="kbd">I</span></span></div>
            <div class="shortcut-row"><span style="color: var(--color-text-secondary);">Sugerir respuesta IA</span><span><span class="kbd">Ctrl</span> + <span class="kbd">Space</span></span></div>
            <div class="shortcut-row"><span style="color: var(--color-text-secondary);">Marcar nota interna</span><span><span class="kbd">Ctrl</span> + <span class="kbd">Shift</span> + <span class="kbd">N</span></span></div>
            <div class="shortcut-row"><span style="color: var(--color-text-secondary);">Mostrar atajos</span><span><span class="kbd">?</span></span></div>
        </div>
        <p class="text-[11px] mt-4 opacity-70" style="color: var(--color-text-tertiary);">Tip: Pega <code class="kbd">*texto*</code> para negrita, <code class="kbd">_texto_</code> para cursiva (formato WhatsApp).</p>
    </div>
</div>

<?php if ($active): ?>
<script>
const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
const convId = <?= $activeId ?>;
const msgsContainer = document.getElementById('msgs');
const msgInput = document.getElementById('msgInput');
let messageFingerprint = null;
let inboxLatest = null;
let unreadInChat = 0;

// ====== Inicialización ======
scrollMsgsToBottom(true);
updateCharCount();
autoGrow(msgInput);

if (msgInput) {
    msgInput.addEventListener('keydown', e => {
        if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMessage(); }
        if (e.key === 'Escape') { msgInput.value = ''; cancelReply(); cancelAttach(); autoGrow(msgInput); updateCharCount(); }
    });
}

// ====== Composer helpers ======
function autoGrow(el) {
    if (!el) return;
    el.style.height = 'auto';
    el.style.height = Math.min(el.scrollHeight, 160) + 'px';
}
function updateCharCount() {
    const cc = document.getElementById('charCount');
    const wc = document.getElementById('wordCount');
    if (msgInput) {
        if (cc) cc.textContent = msgInput.value.length;
        if (wc) {
            const w = msgInput.value.trim().split(/\s+/).filter(Boolean).length;
            wc.textContent = w;
        }
    }
}

// ====== Detector de tono (heurístico, frontend) ======
function detectTone() {
    const tag = document.getElementById('composerToneTag');
    if (!tag || !msgInput) return;
    const t = msgInput.value.toLowerCase().trim();
    if (!t || t.length < 8) { tag.classList.add('hidden'); return; }
    let label = '', emoji = '', color = '#A78BFA', bg = 'rgba(124,58,237,.10)';
    if (/[!?]{2,}|urgente|ya|ahora mismo|ahora!|inmediato|emergencia/.test(t)) {
        label = 'Urgente'; emoji = '🚨'; color = '#FB7185'; bg = 'rgba(244,63,94,.12)';
    } else if (/gracias|por favor|estimad|atentamente|cordialmente|saludos cordiales/.test(t)) {
        label = 'Formal'; emoji = '💼'; color = '#06B6D4'; bg = 'rgba(6,182,212,.12)';
    } else if (/jaja|jeje|😂|🤣|genial|excelente|perfecto|listo|dale/.test(t)) {
        label = 'Amigable'; emoji = '😊'; color = '#34D399'; bg = 'rgba(16,185,129,.12)';
    } else if (/disculp|lamento|perdón|sentimos|inconveniente/.test(t)) {
        label = 'Empático'; emoji = '🤝'; color = '#FBBF24'; bg = 'rgba(245,158,11,.12)';
    } else {
        label = 'Neutral'; emoji = '💬';
    }
    tag.innerHTML = '<span>' + emoji + '</span><span>' + label + '</span>';
    tag.style.background = bg;
    tag.style.color = color;
    tag.classList.remove('hidden');
}
function insertEmoji(em) {
    if (!msgInput) return;
    const start = msgInput.selectionStart, end = msgInput.selectionEnd;
    msgInput.value = msgInput.value.slice(0, start) + em + msgInput.value.slice(end);
    msgInput.selectionStart = msgInput.selectionEnd = start + em.length;
    msgInput.focus();
    updateCharCount();
}
function copyMsg(btn) {
    const t = btn.dataset.text || '';
    navigator.clipboard.writeText(t).then(() => {
        btn.classList.add('text-emerald-400');
        setTimeout(() => btn.classList.remove('text-emerald-400'), 800);
    });
}
function quoteMsg(btn) {
    const t = (btn.dataset.text || '').slice(0, 200);
    const preview = document.getElementById('replyPreview');
    const txt = document.getElementById('replyPreviewText');
    if (preview && txt) {
        txt.textContent = t;
        preview.classList.remove('hidden');
    }
    if (msgInput) {
        const quote = '> ' + t.replace(/\n/g, '\n> ') + '\n';
        msgInput.value = quote + msgInput.value;
        msgInput.focus();
        autoGrow(msgInput);
        updateCharCount();
    }
}
function cancelReply() {
    const preview = document.getElementById('replyPreview');
    if (preview) preview.classList.add('hidden');
}
function onFilePicked(ev) {
    const f = ev.target.files && ev.target.files[0];
    if (!f) return;
    const preview = document.getElementById('attachPreview');
    document.getElementById('attachName').textContent = f.name;
    document.getElementById('attachSize').textContent = (f.size / 1024).toFixed(1) + ' KB · ' + (f.type || 'archivo');
    if (preview) preview.classList.remove('hidden');
}
function cancelAttach() {
    const preview = document.getElementById('attachPreview');
    if (preview) preview.classList.add('hidden');
    const f1 = document.getElementById('fileInputMedia'); if (f1) f1.value = '';
    const f2 = document.getElementById('fileInputDoc');   if (f2) f2.value = '';
}

// ====== Voz (UI + grabación local) ======
let mediaRecorder = null;
async function toggleVoice() {
    const btn = document.getElementById('voiceBtn');
    if (!navigator.mediaDevices) { alert('Tu navegador no soporta grabación de audio.'); return; }
    if (mediaRecorder && mediaRecorder.state === 'recording') {
        mediaRecorder.stop();
        return;
    }
    try {
        const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
        mediaRecorder = new MediaRecorder(stream);
        const chunks = [];
        mediaRecorder.ondataavailable = (e) => chunks.push(e.data);
        mediaRecorder.onstart = () => { btn.classList.add('recording'); };
        mediaRecorder.onstop = () => {
            btn.classList.remove('recording');
            stream.getTracks().forEach(t => t.stop());
            const blob = new Blob(chunks, { type: 'audio/webm' });
            const f = new File([blob], 'voice-' + Date.now() + '.webm', { type: 'audio/webm' });
            const dt = new DataTransfer();
            dt.items.add(f);
            const inp = document.getElementById('fileInputMedia');
            if (inp) { inp.files = dt.files; onFilePicked({ target: inp }); }
        };
        mediaRecorder.start();
    } catch (e) { alert('No se pudo acceder al micrófono: ' + e.message); }
}

// ====== Search en conversación ======
function toggleSearch() {
    const bar = document.getElementById('chatSearchBar');
    bar.classList.toggle('hidden');
    if (!bar.classList.contains('hidden')) {
        document.getElementById('chatSearchInput').focus();
    } else {
        clearSearchHits();
    }
}
function clearSearchHits() {
    document.querySelectorAll('.msg-row').forEach(r => r.classList.remove('search-hit', 'search-dim'));
    document.getElementById('chatSearchCount').textContent = '';
    const input = document.getElementById('chatSearchInput');
    if (input) input.value = '';
}
document.addEventListener('input', e => {
    if (e.target && e.target.id === 'chatSearchInput') {
        const q = e.target.value.trim().toLowerCase();
        const rows = document.querySelectorAll('.msg-row');
        if (!q) {
            rows.forEach(r => r.classList.remove('search-hit', 'search-dim'));
            document.getElementById('chatSearchCount').textContent = '';
            return;
        }
        let n = 0;
        rows.forEach(r => {
            const txt = r.dataset.search || '';
            if (txt.includes(q)) { r.classList.add('search-hit'); r.classList.remove('search-dim'); n++; }
            else { r.classList.remove('search-hit'); r.classList.add('search-dim'); }
        });
        document.getElementById('chatSearchCount').textContent = n + ' coincidencias';
        // Scroll a la primera coincidencia
        const first = document.querySelector('.msg-row.search-hit');
        if (first) first.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
});

// ====== Tabs sidebar ======
document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        const tab = btn.dataset.tab;
        document.querySelectorAll('.tab-btn').forEach(b => {
            b.style.color = 'var(--color-text-tertiary)';
            b.style.borderBottomColor = 'transparent';
            b.classList.remove('active');
        });
        btn.style.color = 'var(--color-primary)';
        btn.style.borderBottomColor = 'var(--color-primary)';
        btn.classList.add('active');
        document.querySelectorAll('[data-tab-content]').forEach(c => c.classList.add('hidden'));
        const content = document.querySelector('[data-tab-content="' + tab + '"]');
        if (content) content.classList.remove('hidden');
        if (tab === 'orders' && !content.dataset.loaded) loadOrdersTab(content);
    });
});

async function loadOrdersTab(container) {
    const body = document.getElementById('ordersTabBody');
    try {
        const res = await fetch('<?= url('/orders/recent?conversation_id=') ?>' + convId + '&since=' + encodeURIComponent('2000-01-01'));
        const data = await res.json();
        if (!data.success) { body.innerHTML = '<div class="text-center py-8 text-xs" style="color:var(--color-text-tertiary)">Error cargando pedidos.</div>'; return; }
        if (!data.orders.length) {
            body.innerHTML = '<div class="text-center py-8" style="color: var(--color-text-tertiary);"><div class="text-3xl mb-2">📦</div><div class="text-xs">Sin pedidos aún</div></div>';
            container.dataset.loaded = '1';
            return;
        }
        body.innerHTML = data.orders.map(o => {
            const t = parseFloat(o.total).toFixed(2);
            const cur = o.currency || 'DOP';
            const tipo = o.delivery_type === 'pickup' ? '🛍 Pickup' : (o.delivery_type === 'dine_in' ? '🍴 Mesa' : '🛵 Delivery');
            const stColor = ({
                'new':'#94A3B8','confirmed':'#06B6D4','preparing':'#F59E0B',
                'ready':'#22C55E','out_for_delivery':'#7C3AED','delivered':'#10B981','cancelled':'#EF4444'
            })[o.status] || '#94A3B8';
            return `
                <a href="<?= url('/orders/') ?>${o.id}" class="block rounded-xl p-3 transition" style="background: var(--color-bg-subtle); border:1px solid var(--color-border-subtle);">
                    <div class="flex items-start justify-between gap-2 mb-1">
                        <div class="font-semibold text-sm" style="color: var(--color-text-primary);">#${o.code}</div>
                        <span class="text-[9px] uppercase font-bold tracking-wider px-1.5 py-0.5 rounded-full" style="background:${stColor}22; color:${stColor};">${o.status}</span>
                    </div>
                    <div class="text-[10px] mb-2" style="color: var(--color-text-tertiary);">${tipo} · ${(o.created_at || '').slice(0,16).replace('T',' ')}</div>
                    <div class="flex items-center justify-between gap-2">
                        <span class="font-bold" style="background: linear-gradient(135deg,#7C3AED,#06B6D4); -webkit-background-clip: text; background-clip: text; -webkit-text-fill-color: transparent;">${cur} ${t}</span>
                        ${o.is_ai_generated == 1 ? '<span class="text-[9px] font-bold uppercase tracking-wider px-1.5 rounded" style="background:rgba(124,58,237,.15); color:#A78BFA;">🤖 IA</span>' : ''}
                    </div>
                </a>
            `;
        }).join('');
        container.dataset.loaded = '1';
    } catch (e) {
        body.innerHTML = '<div class="text-center py-8 text-xs" style="color:var(--color-text-tertiary)">Error: ' + e.message + '</div>';
    }
}

// ====== Scroll bottom button ======
function scrollMsgsToBottom(force) {
    if (!msgsContainer) return;
    msgsContainer.scrollTop = msgsContainer.scrollHeight;
    unreadInChat = 0;
    updateScrollDownBadge();
}
function updateScrollDownBadge() {
    const btn = document.getElementById('scrollDownBtn');
    const badge = document.getElementById('scrollDownBadge');
    if (!btn) return;
    const atBottom = msgsContainer.scrollTop + msgsContainer.clientHeight >= msgsContainer.scrollHeight - 80;
    if (atBottom) { btn.classList.add('hidden'); }
    else { btn.classList.remove('hidden'); }
    if (unreadInChat > 0 && badge) { badge.textContent = unreadInChat; badge.classList.remove('hidden'); }
    else if (badge) { badge.classList.add('hidden'); }
}
if (msgsContainer) msgsContainer.addEventListener('scroll', updateScrollDownBadge);

// ====== Send message ======
async function sendMessage() {
    const text = msgInput.value.trim();
    if (!text) return;
    const isInternal = document.getElementById('isInternal').checked;
    const chSelect = document.getElementById('channelSelect');
    const channelId = chSelect ? parseInt(chSelect.value, 10) : null;
    msgInput.disabled = true;
    try {
        const res = await fetch('<?= url('/inbox/') ?>' + convId + '/send', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken, 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({ message: text, is_internal: isInternal ? 1 : 0, channel_id: channelId || null })
        });
        const data = await res.json();
        if (data.success) {
            msgInput.value = '';
            cancelReply();
            cancelAttach();
            autoGrow(msgInput);
            updateCharCount();
            await pollMessages(true);
            if (data.sent === false) {
                alert('No se pudo enviar al canal: ' + (data.whatsapp_error || 'Error desconocido'));
            }
        } else alert('Error: ' + (data.error || ''));
    } catch (e) { alert('Error: ' + e.message); }
    finally { msgInput.disabled = false; msgInput.focus(); }
}

async function pollMessages(force = false) {
    if (!msgsContainer) return;
    try {
        const res = await fetch('<?= url('/inbox/') ?>' + convId + '/messages', {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await res.json();
        if (!data.success) return;
        const atBottom = msgsContainer.scrollTop + msgsContainer.clientHeight >= msgsContainer.scrollHeight - 80;
        if (force || (messageFingerprint !== null && data.fingerprint !== messageFingerprint)) {
            const prevHeight = msgsContainer.scrollHeight;
            msgsContainer.innerHTML = data.html;
            if (atBottom || force) { msgsContainer.scrollTop = msgsContainer.scrollHeight; }
            else if (!force && messageFingerprint !== null) { unreadInChat++; updateScrollDownBadge(); }
        }
        messageFingerprint = data.fingerprint;
    } catch (e) {}
}

async function pollInboxState() {
    try {
        const res = await fetch('<?= url('/inbox/live') ?>', {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await res.json();
        if (!data.success) return;
        const badge = document.getElementById('inboxCount');
        if (badge) badge.textContent = data.count;
        if (inboxLatest !== null && data.latest !== inboxLatest && !document.hidden) {
            await pollMessages(false);
        }
        inboxLatest = data.latest;
    } catch (e) {}
}

pollMessages(false);
pollInboxState();
setInterval(() => {
    if (!document.hidden) {
        pollMessages(false);
        pollInboxState();
    }
}, 3000);

// ====== AI Transform: aplica IA al texto del composer ======
async function aiTransform(mode) {
    const text = msgInput ? msgInput.value.trim() : '';

    // Modos que NO necesitan texto previo (sugerencia)
    const standaloneOk = ['suggest'];
    if (!text && !standaloneOk.includes(mode)) {
        return aiAction('suggest');
    }

    const overlay = document.getElementById('aiOverlay');
    const overlayLabel = document.getElementById('aiOverlayLabel');
    const labelMap = {
        suggest:   'Generando sugerencia...',
        improve:   'Mejorando redacción...',
        formal:    'Reescribiendo en tono formal...',
        casual:    'Reescribiendo en tono amigable...',
        shorter:   'Acortando...',
        longer:    'Expandiendo con detalles...',
        fix:       'Corrigiendo gramática...',
        translate: 'Traduciendo...',
        emojify:   'Añadiendo emojis...',
        continue:  'Continuando el mensaje...',
    };
    if (overlayLabel) overlayLabel.textContent = labelMap[mode] || 'Procesando...';
    if (overlay) overlay.classList.remove('hidden');

    const pill = document.querySelector('.ai-pill[onclick*="\'' + mode + '\'"]');
    if (pill) pill.classList.add('processing');

    try {
        const isSuggest = mode === 'suggest';
        const url = '<?= url('/inbox/') ?>' + convId + '/ai?action=' + mode;
        const res = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(isSuggest ? {} : { text }),
        });
        const data = await res.json();
        if (data.success && data.text) {
            const newText = String(data.text).trim();
            if (mode === 'continue') {
                msgInput.value = (text ? text + ' ' : '') + newText;
            } else {
                msgInput.value = newText;
            }
            msgInput.focus();
            autoGrow(msgInput);
            updateCharCount();
            detectTone();
            // micro-feedback visual
            msgInput.style.transition = 'background .3s ease';
            msgInput.style.background = 'rgba(124,58,237,.06)';
            setTimeout(() => { msgInput.style.background = ''; }, 600);
        } else {
            const aiPanel = document.getElementById('aiPanel');
            const aiBody  = document.getElementById('aiPanelBody');
            if (aiPanel && aiBody) {
                aiPanel.classList.remove('hidden');
                aiBody.textContent = data.error || 'No se pudo procesar con IA.';
            } else {
                alert(data.error || 'No se pudo procesar con IA.');
            }
        }
    } catch (e) {
        alert('Error: ' + e.message);
    } finally {
        if (overlay) overlay.classList.add('hidden');
        if (pill) pill.classList.remove('processing');
    }
}

// ====== AI Actions ======
async function aiAction(action) {
    const panel = document.getElementById('aiPanel');
    const body = document.getElementById('aiPanelBody');
    panel.classList.remove('hidden');
    body.innerHTML = '<div class="flex items-center gap-2"><span class="typing-dot"></span><span class="typing-dot"></span><span class="typing-dot"></span> <span class="text-xs">Procesando con IA...</span></div>';
    try {
        const res = await fetch('<?= url('/inbox/') ?>' + convId + '/ai?action=' + action, {
            method: 'POST',
            headers: { 'X-CSRF-Token': csrfToken, 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await res.json();
        if (data.success) {
            body.textContent = data.text;
            if (action === 'suggest') { msgInput.value = data.text; msgInput.focus(); autoGrow(msgInput); updateCharCount(); }
        } else body.textContent = data.error || 'No se pudo conectar con la IA.';
    } catch (e) { body.textContent = 'Error: ' + e.message; }
}

async function toggleStar(id) {
    await fetch('<?= url('/inbox/') ?>' + id + '/star', {
        method: 'POST',
        headers: { 'X-CSRF-Token': csrfToken, 'X-Requested-With': 'XMLHttpRequest' }
    });
    location.reload();
}

async function assignAi(agentId) {
    try {
        const res = await fetch('<?= url('/inbox/') ?>' + convId + '/ai/assign', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken, 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({ agent_id: agentId }),
        });
        const data = await res.json();
        if (data.success) location.reload();
        else alert('No se pudo asignar IA: ' + (data.error || ''));
    } catch (e) { alert('Error: ' + e.message); }
}

async function aiTakeoverOff() {
    if (!confirm('¿Desactivar el auto-pilot? La IA dejará de responder sola.')) return;
    await fetch('<?= url('/inbox/') ?>' + convId + '/ai/takeover', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken, 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify({ enable: false }),
    });
    location.reload();
}

document.addEventListener('change', async (e) => {
    if (e.target && e.target.id === 'aiTakeoverToggle') {
        const enable = e.target.checked;
        try {
            const res = await fetch('<?= url('/inbox/') ?>' + convId + '/ai/takeover', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken, 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify({ enable: enable }),
            });
            const data = await res.json();
            if (data.success) location.reload();
            else { e.target.checked = !enable; alert('No se pudo cambiar el modo: ' + (data.error || '')); }
        } catch (err) { e.target.checked = !enable; alert('Error: ' + err.message); }
    }
});

async function aiRunNow() {
    const panel = document.getElementById('aiPanel');
    const body  = document.getElementById('aiPanelBody');
    panel.classList.remove('hidden');
    body.innerHTML = '<div class="flex items-center gap-2"><span class="typing-dot"></span><span class="typing-dot"></span><span class="typing-dot"></span> <span class="text-xs">La IA está redactando y enviando una respuesta...</span></div>';
    try {
        const res = await fetch('<?= url('/inbox/') ?>' + convId + '/ai/run', {
            method: 'POST',
            headers: { 'X-CSRF-Token': csrfToken, 'X-Requested-With': 'XMLHttpRequest' },
        });
        const data = await res.json();
        if (data.success) {
            body.innerHTML = '<div class="text-sm">✓ Mensaje enviado por la IA: <span class="opacity-80">' + (data.text || '').replace(/</g,'&lt;') + '</span></div>';
            await pollMessages(true);
        } else {
            body.textContent = 'No se pudo: ' + (data.error || data.reason || 'fallo desconocido');
        }
    } catch (e) { body.textContent = 'Error: ' + e.message; }
}

// ====== Filtros locales: solo no leídas + ordenamiento ======
const onlyUnreadBtn = document.getElementById('onlyUnreadBtn');
let onlyUnread = false;
if (onlyUnreadBtn) {
    onlyUnreadBtn.addEventListener('click', () => {
        onlyUnread = !onlyUnread;
        onlyUnreadBtn.style.color = onlyUnread ? 'var(--color-primary)' : '';
        onlyUnreadBtn.style.background = onlyUnread ? 'rgba(124,58,237,.10)' : '';
        document.querySelectorAll('.convo-item').forEach(it => {
            it.style.display = (onlyUnread && it.dataset.unread !== '1') ? 'none' : '';
        });
    });
}
document.querySelectorAll('[data-sort]').forEach(b => {
    b.addEventListener('click', (e) => {
        e.preventDefault();
        const mode = b.dataset.sort;
        const list = document.getElementById('convoList');
        if (!list) return;
        const items = Array.from(list.querySelectorAll('.convo-item'));
        items.sort((a, b) => {
            if (mode === 'name') return (a.dataset.name || '').localeCompare(b.dataset.name || '');
            if (mode === 'unread') {
                const u = (parseInt(b.dataset.unread || '0', 10)) - (parseInt(a.dataset.unread || '0', 10));
                if (u !== 0) return u;
                return (b.dataset.time || '').localeCompare(a.dataset.time || '');
            }
            return (b.dataset.time || '').localeCompare(a.dataset.time || '');
        });
        items.forEach(i => list.appendChild(i));
        // Cerrar dropdown
        const det = b.closest('details'); if (det) det.open = false;
    });
});

// ====== Smart-reply chips ======
function fillComposer(text) {
    if (!msgInput) return;
    msgInput.value = text;
    msgInput.focus();
    autoGrow(msgInput);
    updateCharCount();
}

// ====== Markdown / formato WhatsApp ======
function wrapSel(pre, post) {
    if (!msgInput) return;
    const start = msgInput.selectionStart, end = msgInput.selectionEnd;
    const selected = msgInput.value.slice(start, end) || 'texto';
    const wrapped = pre + selected + post;
    msgInput.value = msgInput.value.slice(0, start) + wrapped + msgInput.value.slice(end);
    msgInput.selectionStart = start + pre.length;
    msgInput.selectionEnd = start + pre.length + selected.length;
    msgInput.focus();
    autoGrow(msgInput);
    updateCharCount();
}
function prefixLines(prefix) {
    if (!msgInput) return;
    const start = msgInput.selectionStart, end = msgInput.selectionEnd;
    const before = msgInput.value.slice(0, start);
    const sel = msgInput.value.slice(start, end) || 'línea';
    const after = msgInput.value.slice(end);
    const newSel = sel.split('\n').map(l => prefix + l).join('\n');
    msgInput.value = before + newSel + after;
    msgInput.selectionStart = start;
    msgInput.selectionEnd = start + newSel.length;
    msgInput.focus();
    autoGrow(msgInput);
    updateCharCount();
}
function insertVariable() {
    const vars = ['{{first_name}}', '{{last_name}}', '{{company}}', '{{phone}}'];
    const choice = prompt('Insertar variable:\n' + vars.map((v, i) => (i + 1) + ') ' + v).join('\n') + '\n\nNúmero (1-' + vars.length + '):');
    const idx = parseInt(choice, 10) - 1;
    if (idx >= 0 && idx < vars.length) insertEmoji(vars[idx]);
}
function translateMessage() {
    if (!msgInput || !msgInput.value.trim()) { alert('Escribe primero un mensaje a traducir.'); return; }
    aiAction('translate');
}

// (toggleFullChat, toggleInbox, toggleInfo definidas arriba en el script global)

// ====== Modal de atajos ======
function showShortcuts() { document.getElementById('shortcutsModal').classList.remove('hidden'); }
function hideShortcuts() { document.getElementById('shortcutsModal').classList.add('hidden'); }

// ====== Etiquetas personalizadas (local) ======
function addCustomTag() {
    const tag = prompt('Etiqueta nueva (ej: VIP, Reclamo, Demo):');
    if (!tag || !tag.trim()) return;
    const key = 'kp_tags_' + convId;
    const list = JSON.parse(localStorage.getItem(key) || '[]');
    list.push(tag.trim().slice(0, 24));
    localStorage.setItem(key, JSON.stringify(list));
    renderCustomTags();
}
function removeCustomTag(idx) {
    const key = 'kp_tags_' + convId;
    const list = JSON.parse(localStorage.getItem(key) || '[]');
    list.splice(idx, 1);
    localStorage.setItem(key, JSON.stringify(list));
    renderCustomTags();
}
function renderCustomTags() {
    const key = 'kp_tags_' + convId;
    const list = JSON.parse(localStorage.getItem(key) || '[]');
    document.querySelectorAll('.custom-tag').forEach(el => el.remove());
    const container = document.querySelector('.px-4.py-2.border-b.flex.items-center.gap-1\\.5');
    if (!container) return;
    const addBtn = container.querySelector('button[onclick="addCustomTag()"]');
    list.forEach((t, i) => {
        const sp = document.createElement('span');
        sp.className = 'custom-tag text-[10px] font-semibold px-2 py-0.5 rounded-full whitespace-nowrap inline-flex items-center gap-1';
        sp.style.cssText = 'background: rgba(124,58,237,.12); color:#A78BFA;';
        sp.innerHTML = '🏷 ' + t.replace(/</g, '&lt;') + ' <button onclick="removeCustomTag(' + i + ')" style="opacity:.6;cursor:pointer;background:none;border:none;color:inherit;padding:0;line-height:1;">×</button>';
        if (addBtn) container.insertBefore(sp, addBtn);
    });
}
renderCustomTags();

// ====== Drag-and-drop archivos ======
(function() {
    if (!msgsContainer) return;
    let dragCounter = 0;
    const target = document.querySelector('section.surface');
    if (!target) return;

    target.addEventListener('dragenter', e => {
        if (!e.dataTransfer || !e.dataTransfer.types.includes('Files')) return;
        e.preventDefault();
        dragCounter++;
        document.body.classList.add('drag-active');
    });
    target.addEventListener('dragover', e => {
        if (!e.dataTransfer || !e.dataTransfer.types.includes('Files')) return;
        e.preventDefault();
    });
    target.addEventListener('dragleave', e => {
        dragCounter--;
        if (dragCounter <= 0) { dragCounter = 0; document.body.classList.remove('drag-active'); }
    });
    target.addEventListener('drop', e => {
        if (!e.dataTransfer || !e.dataTransfer.files || !e.dataTransfer.files[0]) return;
        e.preventDefault();
        dragCounter = 0;
        document.body.classList.remove('drag-active');
        const f = e.dataTransfer.files[0];
        const inp = document.getElementById('fileInputMedia');
        if (inp) {
            const dt = new DataTransfer();
            dt.items.add(f);
            inp.files = dt.files;
            onFilePicked({ target: inp });
        }
    });
})();

// ====== Atajos globales de teclado ======
document.addEventListener('keydown', e => {
    const target = e.target;
    const isInComposer = target && target.id === 'msgInput';
    const isInInput = target && (target.tagName === 'INPUT' || target.tagName === 'TEXTAREA');

    // Ctrl/Cmd + K — buscar en chat
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
        e.preventDefault();
        toggleSearch();
        return;
    }
    // Ctrl/Cmd + Space — sugerir IA
    if ((e.ctrlKey || e.metaKey) && e.key === ' ') {
        e.preventDefault();
        aiAction('suggest');
        return;
    }
    // Ctrl/Cmd + Shift + N — alternar nota interna
    if ((e.ctrlKey || e.metaKey) && e.shiftKey && e.key.toLowerCase() === 'n') {
        e.preventDefault();
        const ck = document.getElementById('isInternal');
        if (ck) { ck.checked = !ck.checked; ck.dispatchEvent(new Event('change')); }
        return;
    }
    // Ctrl + B / I dentro del composer
    if (isInComposer && (e.ctrlKey || e.metaKey)) {
        if (e.key.toLowerCase() === 'b') { e.preventDefault(); wrapSel('*', '*'); return; }
        if (e.key.toLowerCase() === 'i') { e.preventDefault(); wrapSel('_', '_'); return; }
    }
    // Si NO estás escribiendo en input/textarea:
    if (!isInInput) {
        if (e.key === '?') { e.preventDefault(); showShortcuts(); return; }
        if (e.key.toLowerCase() === 'f') { e.preventDefault(); toggleFullChat(); return; }
        if (e.key === 'Escape') {
            const m = document.getElementById('shortcutsModal');
            if (m && !m.classList.contains('hidden')) { hideShortcuts(); return; }
        }
    }
});

// =========================================================
// Pop-up de orden creada: detecta nuevas órdenes via polling
// =========================================================
let lastOrderCheck = new Date().toISOString();
const knownOrderIds = new Set();

async function checkNewOrders() {
    try {
        const res = await fetch('<?= url('/orders/recent?conversation_id=') ?>' + convId + '&since=' + encodeURIComponent(lastOrderCheck), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await res.json();
        if (!data.success) return;
        lastOrderCheck = data.now || lastOrderCheck;
        for (const o of (data.orders || [])) {
            if (knownOrderIds.has(o.id)) continue;
            knownOrderIds.add(o.id);
            showOrderToast(o);
        }
    } catch (e) {}
}

function showOrderToast(o) {
    try {
        const ctx = new (window.AudioContext || window.webkitAudioContext)();
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.connect(gain); gain.connect(ctx.destination);
        osc.frequency.setValueAtTime(880, ctx.currentTime);
        osc.frequency.exponentialRampToValueAtTime(1320, ctx.currentTime + 0.15);
        gain.gain.setValueAtTime(0.15, ctx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.4);
        osc.start(); osc.stop(ctx.currentTime + 0.4);
    } catch (e) {}

    const wrapper = document.getElementById('orderToastContainer') || (() => {
        const el = document.createElement('div');
        el.id = 'orderToastContainer';
        el.style.cssText = 'position:fixed; top:20px; right:20px; z-index:10000; display:flex; flex-direction:column; gap:12px; pointer-events:none;';
        document.body.appendChild(el);
        return el;
    })();

    const total = parseFloat(o.total).toFixed(2);
    const cur = o.currency || 'DOP';
    const tipo = o.delivery_type === 'pickup' ? '🛍 Pickup' : (o.delivery_type === 'dine_in' ? '🍴 Mesa' : '🛵 Delivery');
    const aiBadge = o.is_ai_generated == 1 ? '<span style="padding:1px 6px;border-radius:4px;background:rgba(124,58,237,.2);color:#A78BFA;font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;">🤖 IA</span>' : '';

    const toast = document.createElement('a');
    toast.href = '<?= url('/orders/') ?>' + o.id;
    toast.target = '_blank';
    toast.style.cssText = `
        pointer-events: auto;
        display: block;
        min-width: 320px;
        max-width: 380px;
        background: linear-gradient(135deg, rgba(16,185,129,.12), rgba(124,58,237,.12));
        border: 1px solid rgba(16,185,129,.4);
        backdrop-filter: blur(20px);
        border-radius: 16px;
        padding: 14px 16px;
        box-shadow: 0 10px 40px rgba(0,0,0,.4), 0 0 30px rgba(16,185,129,.15);
        color: white;
        text-decoration: none;
        transform: translateX(120%);
        transition: transform .35s cubic-bezier(.2,.9,.3,1.4);
        cursor: pointer;
    `;
    toast.innerHTML = `
        <div style="display:flex; align-items:start; gap:12px;">
            <div style="font-size:32px; flex-shrink:0; filter: drop-shadow(0 0 8px rgba(16,185,129,.5));">🎉</div>
            <div style="flex:1; min-width:0;">
                <div style="display:flex; align-items:center; gap:6px; margin-bottom:2px; flex-wrap:wrap;">
                    <span style="font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:.1em; color:#10B981;">Nueva orden</span>
                    ${aiBadge}
                </div>
                <div style="font-weight:700; font-size:15px; margin-bottom:1px;">#${o.code}</div>
                <div style="font-size:12px; color:rgba(255,255,255,.7); margin-bottom:8px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                    ${o.customer_name || 'Cliente'} · ${tipo}
                </div>
                <div style="display:flex; align-items:center; justify-content:space-between; gap:8px;">
                    <div style="font-size:18px; font-weight:800; background: linear-gradient(135deg,#10B981,#06B6D4); -webkit-background-clip: text; background-clip: text; -webkit-text-fill-color: transparent;">${cur} ${total}</div>
                    <span style="font-size:11px; font-weight:600; padding:4px 10px; border-radius:8px; background:rgba(255,255,255,.08);">Ver →</span>
                </div>
            </div>
            <button onclick="event.preventDefault();event.stopPropagation();this.parentElement.parentElement.remove()" style="background:none; border:none; color:rgba(255,255,255,.5); cursor:pointer; padding:0; font-size:18px; line-height:1;">×</button>
        </div>
    `;
    wrapper.appendChild(toast);
    requestAnimationFrame(() => { toast.style.transform = 'translateX(0)'; });
    setTimeout(() => {
        toast.style.transform = 'translateX(120%)';
        setTimeout(() => toast.remove(), 400);
    }, 12000);
}

setInterval(() => { if (!document.hidden) checkNewOrders(); }, 5000);
checkNewOrders();
</script>
<?php endif; ?>

<?php \App\Core\View::stop(); ?>
