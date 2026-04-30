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

$activeId = $active ? (int) $active['id'] : 0;
$contactName = $active ? trim(($active['first_name'] ?? '') . ' ' . ($active['last_name'] ?? '')) : '';
?>

<div class="grid grid-cols-1 lg:grid-cols-12 gap-3 h-[calc(100vh-128px)]">

    <!-- ============= LISTA DE CONVERSACIONES ============= -->
    <aside class="lg:col-span-4 xl:col-span-3 surface flex flex-col overflow-hidden">
        <div class="p-4 border-b" style="border-color: var(--color-border-subtle);">
            <div class="flex items-center justify-between mb-3">
                <div class="flex items-center gap-2">
                    <h2 class="font-bold text-[15px]" style="color: var(--color-text-primary);">Bandeja</h2>
                    <span id="inboxCount" class="badge badge-primary badge-dot"><?= count($conversations) ?></span>
                </div>
                <button class="btn btn-ghost btn-icon" title="Filtros">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                </button>
            </div>
            <form method="GET" class="relative mb-3">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 pointer-events-none" style="color: var(--color-text-tertiary);" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input type="text" name="q" value="<?= e((string) $filters['q']) ?>" placeholder="Buscar conversaciones..." class="input pl-10 text-sm">
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
                <span class="text-[10px] uppercase font-semibold flex-shrink-0" style="color: var(--color-text-tertiary);">Numero:</span>
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

        <div class="flex-1 overflow-y-auto scrollbar-thin">
            <?php if (empty($conversations)): ?>
            <div class="empty-state-pro">
                <div class="empty-icon">📭</div>
                <h4 class="font-bold text-sm mb-1" style="color: var(--color-text-primary);">Sin conversaciones</h4>
                <p class="text-xs" style="color: var(--color-text-tertiary);">Conecta Wasapi para empezar a recibir mensajes.</p>
            </div>
            <?php else: foreach ($conversations as $c):
                $name = trim(($c['first_name'] ?? '') . ' ' . ($c['last_name'] ?? '')) ?: ($c['phone'] ?? 'Sin nombre');
                $isActive = (int) $c['id'] === $activeId;
                $hasUnread = (int) $c['unread_count'] > 0;
            ?>
            <a href="<?= url('/inbox/' . $c['id']) ?>" class="flex items-start gap-3 px-4 py-3 transition border-b relative group" style="border-color: var(--color-border-subtle); <?= $isActive ? 'background: var(--color-bg-active);' : '' ?>" onmouseover="if(!<?= $isActive ? 'true':'false' ?>)this.style.background='var(--color-bg-subtle)'" onmouseout="if(!<?= $isActive ? 'true':'false' ?>)this.style.background=''">
                <?php if ($isActive): ?>
                <div class="absolute left-0 top-3 bottom-3 w-[3px] rounded-r-full" style="background: var(--gradient-primary);"></div>
                <?php endif; ?>

                <div class="relative flex-shrink-0">
                    <div class="avatar avatar-md"><?= e(strtoupper(mb_substr($name, 0, 1))) ?></div>
                    <span class="absolute -bottom-0.5 -right-0.5 status-dot status-online" style="border: 2px solid var(--color-bg-surface);"></span>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex items-center justify-between gap-2 mb-0.5">
                        <span class="font-semibold text-sm truncate <?= $hasUnread ? '' : '' ?>" style="color: var(--color-text-primary);"><?= e($name) ?></span>
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

    <!-- ============= CHAT PRINCIPAL ============= -->
    <section class="lg:col-span-8 xl:col-span-6 surface flex flex-col overflow-hidden">
        <?php if (!$active): ?>
        <div class="flex-1 flex items-center justify-center p-12">
            <div class="text-center max-w-md">
                <div class="w-28 h-28 mx-auto rounded-3xl flex items-center justify-center mb-5 text-5xl relative" style="background: var(--gradient-mesh); border: 1px solid var(--color-border-subtle);">
                    <span class="relative z-10">💬</span>
                    <div class="absolute inset-0 rounded-3xl opacity-30" style="background: radial-gradient(circle, rgba(124,58,237,0.4), transparent 70%); filter: blur(20px);"></div>
                </div>
                <h3 class="text-lg font-bold mb-2" style="color: var(--color-text-primary);">Selecciona una conversacion</h3>
                <p class="text-sm" style="color: var(--color-text-tertiary);">Elige una conversacion de la lista para ver los mensajes.</p>
            </div>
        </div>
        <?php else: ?>

        <!-- ===== Chat Header ===== -->
        <div class="flex items-center justify-between gap-3 p-4 border-b" style="border-color: var(--color-border-subtle); background: var(--color-bg-surface);">
            <div class="flex items-center gap-3 min-w-0">
                <div class="relative flex-shrink-0">
                    <div class="avatar avatar-lg"><?= e(strtoupper(mb_substr($contactName ?: 'X', 0, 1))) ?></div>
                    <span class="absolute -bottom-0.5 -right-0.5 status-dot status-online" style="border: 2px solid var(--color-bg-surface); width: 12px; height: 12px;"></span>
                </div>
                <div class="min-w-0">
                    <div class="flex items-center gap-2 mb-0.5">
                        <h2 class="font-bold truncate text-[15px]" style="color: var(--color-text-primary);"><?= e($contactName ?: ($active['phone'] ?? 'Sin nombre')) ?></h2>
                        <?php if (!empty($active['ai_sentiment'])):
                            $cls = match ($active['ai_sentiment']) { 'positive' => 'badge-emerald', 'negative' => 'badge-rose', default => 'badge-slate' };
                            $lbl = match ($active['ai_sentiment']) { 'positive' => 'Positivo', 'negative' => 'Negativo', default => 'Neutral' };
                        ?>
                        <span class="badge <?= $cls ?>"><?= $lbl ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="text-xs flex items-center gap-2 flex-wrap" style="color: var(--color-text-tertiary);">
                        <span class="font-mono"><?= e((string) ($active['phone'] ?? $active['email'] ?? '')) ?></span>
                        <span style="color: var(--color-text-muted);">·</span>
                        <span class="capitalize"><?= e($active['status']) ?></span>
                        <?php if (!empty($active['channel_label'])): ?>
                        <span style="color: var(--color-text-muted);">·</span>
                        <span class="font-semibold flex items-center gap-1" style="color: <?= e((string) ($active['channel_color'] ?? '#7C3AED')) ?>;">
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

                <button onclick="aiAction('summarize')" title="Resumir conversacion" class="btn btn-ghost btn-icon">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                </button>
                <button onclick="aiAction('suggest')" class="btn btn-secondary btn-sm">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    <span class="hidden lg:inline">Sugerir IA</span>
                </button>
                <div class="w-px h-6 mx-1" style="background: var(--color-border-subtle);"></div>
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
                                Cerrar conversacion
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
                            Ver perfil
                        </a>
                    </div>
                </details>
            </div>
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
                <button onclick="aiAction('next')" class="px-2 py-1 rounded-md glass">Proxima accion</button>
                <button onclick="aiAction('score')" class="px-2 py-1 rounded-md glass">Calificar lead</button>
                <button onclick="aiAction('sentiment')" class="px-2 py-1 rounded-md glass">Sentimiento</button>
                <?php if (!empty($active['ai_takeover'])): ?>
                <button onclick="aiTakeoverOff()" class="px-2 py-1 rounded-md" style="background: rgba(244,63,94,.15); color:#FB7185;">Desactivar auto-pilot</button>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- ===== Mensajes ===== -->
        <div id="msgs" class="flex-1 overflow-y-auto p-5 space-y-3" style="background: var(--color-bg-base); background-image: radial-gradient(circle, var(--color-border-subtle) 1px, transparent 1px); background-size: 24px 24px;">
            <?php
            $currentDate = '';
            foreach ($messages as $m):
                $msgDate = date('Y-m-d', strtotime((string) $m['created_at']));
                if ($msgDate !== $currentDate):
                    $currentDate = $msgDate;
                    $todayLabel = date('Y-m-d') === $msgDate ? 'Hoy' : (date('Y-m-d', strtotime('-1 day')) === $msgDate ? 'Ayer' : date('d M', strtotime($msgDate)));
            ?>
            <div class="flex justify-center my-4">
                <span class="px-3 py-1 rounded-full text-[10px] font-semibold uppercase tracking-[0.1em]" style="background: var(--color-bg-elevated); color: var(--color-text-tertiary); border: 1px solid var(--color-border-subtle);"><?= $todayLabel ?></span>
            </div>
            <?php endif;
                $isOut = $m['direction'] === 'outbound';
                $internal = !empty($m['is_internal']);
                $aiGen = !empty($m['is_ai_generated']);
            ?>
            <div class="flex <?= $isOut ? 'justify-end' : 'justify-start' ?> animate-fade-in">
                <div class="max-w-[75%]">
                    <?php if ($internal): ?>
                    <div class="px-4 py-3 rounded-2xl rounded-bl-sm border" style="background: rgba(245,158,11,.08); border-color: rgba(245,158,11,.25); color: #FBBF24;">
                        <div class="text-[10px] uppercase font-semibold tracking-wider mb-1.5 flex items-center gap-1">
                            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path d="M5 3a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2V5a2 2 0 00-2-2H5z"/></svg>
                            Nota interna
                        </div>
                        <div class="text-sm whitespace-pre-line"><?= e((string) $m['content']) ?></div>
                    </div>
                    <?php else: ?>
                    <div class="px-4 py-2.5 <?= $isOut ? 'rounded-2xl rounded-br-sm text-white' : 'rounded-2xl rounded-bl-sm' ?>"
                         style="<?= $isOut ? 'background: var(--gradient-primary); box-shadow: 0 2px 8px rgba(124,58,237,.25);' : 'background: var(--color-bg-elevated); border: 1px solid var(--color-border-subtle); color: var(--color-text-primary); box-shadow: var(--shadow-xs);' ?>">
                        <?php if ($aiGen): ?>
                        <div class="text-[10px] opacity-80 mb-1 flex items-center gap-1">
                            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path d="M11.3 1.046A1 1 0 0112 2v5h4a1 1 0 01.82 1.573l-7 10A1 1 0 018 18v-5H4a1 1 0 01-.82-1.573l7-10a1 1 0 011.12-.38z"/></svg>
                            Generado por IA
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($m['media_url'])): ?>
                        <div class="mb-2 text-xs opacity-80 flex items-center gap-1">
                            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8 4a3 3 0 00-3 3v4a5 5 0 0010 0V7a1 1 0 112 0v4a7 7 0 11-14 0V7a5 5 0 0110 0v4a3 3 0 11-6 0V7a1 1 0 012 0v4a1 1 0 102 0V7a3 3 0 00-3-3z" clip-rule="evenodd"/></svg>
                            <a href="<?= e($m['media_url']) ?>" target="_blank" class="underline">Adjunto</a>
                        </div>
                        <?php endif; ?>
                        <div class="text-sm leading-relaxed whitespace-pre-line"><?= e((string) $m['content']) ?></div>
                    </div>
                    <?php endif; ?>
                    <div class="text-[10px] mt-1.5 flex items-center gap-1 <?= $isOut ? 'justify-end' : '' ?>" style="color: var(--color-text-muted);">
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
        </div>

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
        <div class="border-t p-3" style="border-color: var(--color-border-subtle); background: var(--color-bg-surface);" x-data="{ note: false, qrOpen: false }">
            <?php if (!empty($quickReplies)): ?>
            <div class="relative mb-2">
                <button @click="qrOpen = !qrOpen" type="button" class="text-xs flex items-center gap-1.5 hover:underline font-medium" style="color: var(--color-primary);">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    Respuestas rapidas
                </button>
                <div x-show="qrOpen" @click.outside="qrOpen = false" x-transition x-cloak class="absolute bottom-full left-0 mb-2 w-72 rounded-xl shadow-xl border py-1 z-30 max-h-64 overflow-y-auto" style="background: var(--color-bg-elevated); border-color: var(--color-border-default);">
                    <?php foreach ($quickReplies as $qr): ?>
                    <button type="button" onclick="document.getElementById('msgInput').value = <?= htmlspecialchars(json_encode($qr['body']), ENT_QUOTES) ?>; this.closest('[x-data]').__x.$data.qrOpen = false;" class="dropdown-item w-full">
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
                <div class="flex items-end gap-2">
                    <div class="flex-1">
                        <textarea id="msgInput" rows="2" placeholder="Escribe un mensaje..." class="textarea resize-none text-sm focus-ring"></textarea>
                        <div class="flex items-center justify-between mt-2 text-xs" style="color: var(--color-text-tertiary);">
                            <div class="flex items-center gap-3">
                                <label class="flex items-center gap-1.5 cursor-pointer">
                                    <input type="checkbox" id="isInternal" x-model="note" class="w-3.5 h-3.5 rounded">
                                    <span :class="note ? 'text-amber-400 font-medium' : ''">Nota interna</span>
                                </label>
                                <button type="button" class="hover:text-[color:var(--color-text-primary)] transition" title="Adjuntar">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                                </button>
                                <button type="button" class="hover:text-[color:var(--color-text-primary)] transition" title="Emoji">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                </button>
                            </div>
                            <span style="color: var(--color-text-muted);"><span class="kbd">↵</span> envia · <span class="kbd">Shift</span>+<span class="kbd">↵</span> nueva linea</span>
                        </div>
                    </div>
                    <button type="submit" class="p-3 rounded-xl text-white flex-shrink-0 transition" style="background: var(--gradient-primary); box-shadow: 0 4px 14px rgba(124,58,237,.3);" onmouseover="this.style.transform='translateY(-1px)'; this.style.boxShadow='0 6px 18px rgba(124,58,237,.4)'" onmouseout="this.style.transform=''; this.style.boxShadow='0 4px 14px rgba(124,58,237,.3)'">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                    </button>
                </div>
            </form>
        </div>
        <?php else: ?>
        <div class="p-4 border-t text-center text-sm" style="border-color: var(--color-border-subtle); color: var(--color-text-tertiary);">
            <span class="badge badge-slate mb-2">Cerrada</span>
            <div>Esta conversacion esta cerrada. <form action="<?= url('/inbox/' . $activeId . '/reopen') ?>" method="POST" class="inline"><?= csrf_field() ?><button class="hover:underline font-semibold" style="color: var(--color-primary);">Reabrir conversacion</button></form></div>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </section>

    <!-- ============= SIDEBAR DERECHO - Detalles del contacto ============= -->
    <?php if ($active): ?>
    <aside class="lg:col-span-4 xl:col-span-3 surface overflow-y-auto scrollbar-thin">
        <div class="p-5 text-center border-b relative overflow-hidden" style="border-color: var(--color-border-subtle);">
            <div class="absolute inset-0 opacity-30" style="background: var(--gradient-mesh);"></div>
            <div class="relative">
                <div class="avatar avatar-xl mx-auto mb-3 relative">
                    <?= e(strtoupper(mb_substr($contactName ?: 'X', 0, 1))) ?>
                    <span class="absolute -bottom-0.5 -right-0.5 status-dot status-online" style="border: 3px solid var(--color-bg-surface); width: 14px; height: 14px;"></span>
                </div>
                <h3 class="font-bold text-base" style="color: var(--color-text-primary);"><?= e($contactName ?: 'Sin nombre') ?></h3>
                <?php if (!empty($active['company'])): ?><div class="text-xs mt-0.5" style="color: var(--color-text-tertiary);"><?= e($active['company']) ?></div><?php endif; ?>
                <div class="flex justify-center gap-2 mt-4">
                    <a href="<?= url('/contacts/' . $active['contact_id']) ?>" class="btn btn-secondary btn-sm">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                        Perfil
                    </a>
                    <?php if (!empty($active['phone'])): ?>
                    <a href="https://wa.me/<?= e(preg_replace('/[^\d]/', '', (string) $active['phone'])) ?>" target="_blank" class="btn btn-secondary btn-sm" style="color: #10B981;">
                        <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163a11.867 11.867 0 01-1.587-5.946C.16 5.335 5.495 0 12.05 0a11.817 11.817 0 018.413 3.488 11.824 11.824 0 013.48 8.414c-.003 6.557-5.338 11.892-11.893 11.892a11.9 11.9 0 01-5.688-1.448L.057 24z"/></svg>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="p-5">
            <h4 class="text-[10px] uppercase font-semibold tracking-[0.1em] mb-3" style="color: var(--color-text-tertiary);">Informacion</h4>
            <dl class="space-y-2.5 text-sm mb-5">
                <?php
                $info = [
                    ['Telefono', $active['phone'] ?? '—', 'M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z'],
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
            <div class="rounded-xl border p-3" style="background: rgba(16,185,129,0.05); border-color: rgba(16,185,129,0.2);">
                <div class="flex items-center gap-1.5 mb-1.5">
                    <span class="text-base">🎯</span>
                    <span class="text-[10px] uppercase font-semibold tracking-wider" style="color: #34D399;">Proxima accion sugerida</span>
                </div>
                <div class="text-xs leading-relaxed" style="color: var(--color-text-secondary);"><?= e((string) $active['ai_next_action']) ?></div>
            </div>
            <?php endif; ?>
        </div>
    </aside>
    <?php endif; ?>
</div>

<?php if ($active): ?>
<script>
const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
const convId = <?= $activeId ?>;
const msgsContainer = document.getElementById('msgs');
const msgInput = document.getElementById('msgInput');
let messageFingerprint = null;
let inboxLatest = null;

if (msgsContainer) msgsContainer.scrollTop = msgsContainer.scrollHeight;
if (msgInput) {
    msgInput.addEventListener('keydown', e => {
        if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMessage(); }
    });
}

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
            await pollMessages(true);
            if (data.sent === false) {
                alert('Wasapi no acepto el envio: ' + (data.whatsapp_error || 'Error desconocido'));
            }
        } else alert('Error: ' + (data.error || ''));
    } catch (e) { alert('Error: ' + e.message); }
    finally { msgInput.disabled = false; }
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
            msgsContainer.innerHTML = data.html;
            if (atBottom || force) msgsContainer.scrollTop = msgsContainer.scrollHeight;
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
            if (action === 'suggest') { msgInput.value = data.text; msgInput.focus(); }
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
    if (!confirm('Desactivar el auto-pilot? La IA dejara de responder sola.')) return;
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
    body.innerHTML = '<div class="flex items-center gap-2"><span class="typing-dot"></span><span class="typing-dot"></span><span class="typing-dot"></span> <span class="text-xs">La IA esta redactando y enviando una respuesta...</span></div>';
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
</script>
<?php endif; ?>

<?php \App\Core\View::stop(); ?>
